<?php
ob_start();

header('Content-Type: application/json');
require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['tablename'])) {
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit;
}

$tablename = $_SESSION['tablename'];
$user_type = $_SESSION['user_type'] ?? 'user';

define('OPENROUTER_API_KEY', getenv('OPENROUTER_API_KEY') ?: 'sk-or-v1-placeholder-key');
// Using reliable models (very cheap, ~$0.0001 per query)
$FREE_MODELS = [
    'google/gemini-flash-1.5-8b',          // Google Gemini - very cheap
    'openai/gpt-3.5-turbo',                 // OpenAI GPT-3.5 Turbo - reliable
    'anthropic/claude-3-haiku',             // Anthropic Claude Haiku - fast & cheap
    'meta-llama/llama-3-8b-instruct',       // Meta Llama 3 8B
];

$rawInput    = file_get_contents('php://input');
$input       = json_decode($rawInput, true);
$userMessage = trim($input['message'] ?? '');

if (empty($userMessage)) {
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'Empty message']);
    exit;
}

$config = new Config();
$conn   = $config->getConnection();

// =============================================================
//  DYNAMIC DATABASE SCHEMA READER
// =============================================================
function getDatabaseSchema($conn, $tablename, $user_type) {
    try {
        // Get all tables in the database
        $tablesStmt = $conn->query("SHOW TABLES");
        $tables = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);
        
        $schemaInfo = [];
        
        foreach ($tables as $table) {
            // Get columns for each table
            $columnsStmt = $conn->query("SHOW COLUMNS FROM `$table`");
            $columns = $columnsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            $columnDetails = [];
            foreach ($columns as $col) {
                $columnDetails[] = $col['Field'] . ' (' . $col['Type'] . ')';
            }
            
            // Get sample row count
            $countStmt = $conn->query("SELECT COUNT(*) as cnt FROM `$table`");
            $count = $countStmt->fetch(PDO::FETCH_ASSOC)['cnt'];
            
            $schemaInfo[$table] = [
                'columns' => $columnDetails,
                'count' => $count
            ];
        }
        
        return $schemaInfo;
    } catch (Exception $e) {
        return [];
    }
}

// =============================================================
//  AI-POWERED SQL GENERATOR (OpenRouter)
// =============================================================
function generateSQLWithAI($message, $schema, $tablename, $user_type, $models) {
    $debugLog = [];
    
    // Build comprehensive schema context
    $schemaText = "Database Schema:\n";
    foreach ($schema as $table => $info) {
        $schemaText .= "\nTable: $table (Rows: {$info['count']})\n";
        $schemaText .= "Columns: " . implode(', ', $info['columns']) . "\n";
    }
    
    $systemPrompt = "You are a MySQL query expert. Convert the user's natural language question into a valid MySQL SELECT query.

DATA MODEL HINTS:
- Bookings and payments live in table 'admintable'; always include filter WHERE source_table = '$tablename'.
- Leads / follow ups / calls / remarks / next actions live in table 'user_remarks'.
- Do not invent tables. If unsure, pick admintable for booking/payments, user_remarks for leads/remarks.

$schemaText

CRITICAL RULES:
1. Output ONLY the raw SQL query - no markdown, no backticks, no explanations
2. Use ONLY SELECT statements (no INSERT/UPDATE/DELETE)
3. For 'admintable' table, ALWAYS add this filter: WHERE source_table = '$tablename'
4. Current logged-in user: $tablename (user_type: $user_type)
5. Today's date: " . date('Y-m-d') . ", current month: " . date('Y-m') . "
6. Always use LIMIT clause (default: LIMIT 20 for lists, LIMIT 1 for single records)
7. Use meaningful column aliases with 'AS'
8. For date comparisons, use proper MySQL date functions
9. For month filters, use format YYYY-MM (e.g., '2026-02')
10. Use LOWER() for case-insensitive text searches

EXAMPLES:
Question: 'show latest bookings'
Answer: SELECT id, customer_name, project, unit_no, agreement_value, booking_month, astatus FROM admintable WHERE source_table = '$tablename' ORDER BY id DESC LIMIT 20

Question: 'total revenue this month'
Answer: SELECT SUM(revenue) as total_revenue, COUNT(*) as bookings FROM admintable WHERE source_table = '$tablename' AND booking_month = '" . date('Y-m') . "'

Question: 'how many customers'
Answer: SELECT COUNT(DISTINCT customer_name) as total_customers FROM admintable WHERE source_table = '$tablename'

Question: 'pending payments'
Answer: SELECT customer_name, project, agreement_value, recived_amt, (agreement_value - recived_amt) as pending FROM admintable WHERE source_table = '$tablename' AND (agreement_value - recived_amt) > 0 ORDER BY pending DESC LIMIT 20

Question: 'show recent lead follow ups'
Answer: SELECT id, lead_name, phone, status, next_action_date, remarks FROM user_remarks ORDER BY id DESC LIMIT 20

Now generate SQL for: \"$message\"

Remember: Output ONLY the SQL query, nothing else!";

    foreach ($models as $model) {
        $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_POSTFIELDS => json_encode([
                'model'    => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user',   'content' => $message],
                ],
                'temperature' => 0.3,
                'max_tokens' => 500,
            ]),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . OPENROUTER_API_KEY,
                'HTTP-Referer: https://searchhomesindia.in',
                'X-OpenRouter-Title: CRM Database Assistant',
            ],
        ]);
        
        $response = curl_exec($ch);
        $err      = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $debugLog[] = "Model: $model | HTTP: $httpCode | Error: " . ($err ?: 'none');
        
        if ($err) {
            continue; // Try next model
        }
        
        $res = json_decode($response, true);
        
        if (isset($res['error'])) {
            $debugLog[] = "API Error: " . json_encode($res['error']);
            continue; // Try next model
        }
        
        if (!isset($res['choices'][0]['message']['content'])) {
            $debugLog[] = "No content in response: " . substr($response, 0, 200);
            continue;
        }
        
        $sqlRaw = $res['choices'][0]['message']['content'] ?? '';
        
        if (empty($sqlRaw)) {
            continue;
        }
        
        // Clean up the SQL (remove markdown, code blocks, extra whitespace)
        $sql = $sqlRaw;
        $sql = preg_replace('/```sql\s*/i', '', $sql);
        $sql = preg_replace('/```\s*/', '', $sql);
        $sql = preg_replace('/\n+/', ' ', $sql);
        $sql = preg_replace('/\s+/', ' ', $sql);
        $sql = trim($sql);
        $sql = rtrim($sql, ';');
        
        // Validate it's a SELECT query
        if (preg_match('/^\s*SELECT\s/i', $sql)) {
            return ['success' => true, 'sql' => $sql, 'model' => $model];
        } else {
            $debugLog[] = "Not SELECT query: " . substr($sql, 0, 100);
        }
    }
    
    return ['success' => false, 'error' => 'All AI models failed', 'debug' => implode(' | ', $debugLog)];
}
// =============================================================
//  MAIN EXECUTION LOGIC - AI ONLY
// =============================================================

// Step 1: Check for basic greetings
$msg = strtolower($userMessage);
if (preg_match('/^(hi|hello|hey|good\s*(morning|afternoon|evening))\b/', $msg)) {
    ob_end_clean();
    echo json_encode([
        'status'  => 'success',
        'results' => [],
        'message' => 'Hello! Ask me anything about your database - bookings, revenue, customers, expenses, and more!',
        'source'  => 'Quick Response',
    ]);
    exit;
}

if (preg_match('/\b(help|what can you)\b/', $msg)) {
    ob_end_clean();
    echo json_encode([
        'status'  => 'success',
        'results' => [],
        'message' => 'I can answer questions like:\n• Show latest bookings\n• Total revenue this month\n• Pending payments\n• Show customers\n• Which projects have most bookings?\n\nJust ask naturally!',
        'source'  => 'Quick Response',
    ]);
    exit;
}

// Step 2: Get database schema for AI
$schema = getDatabaseSchema($conn, $tablename, $user_type);

if (empty($schema)) {
    ob_end_clean();
    echo json_encode([
        'status'  => 'error',
        'message' => 'Unable to read database schema. Please try again.',
    ]);
    exit;
}

// Step 3: Use AI to generate SQL from your prompt
$aiResult = generateSQLWithAI($userMessage, $schema, $tablename, $user_type, $FREE_MODELS);

if (!$aiResult['success']) {
    $debugInfo = $aiResult['debug'] ?? '';
    
    // Check for common issues
    $needsPrivacy = strpos($debugInfo, 'data policy') !== false || strpos($debugInfo, 'privacy') !== false;
    $needsCredits = strpos($debugInfo, 'credits') !== false || strpos($debugInfo, 'rate') !== false;
    
    $message = 'AI could not generate SQL query. ';
    if ($needsPrivacy) {
        $message .= 'Configure privacy settings at: https://openrouter.ai/settings/privacy';
    } elseif ($needsCredits) {
        $message .= 'OpenRouter account may need credits. Add $5 at: https://openrouter.ai/credits';
    } else {
        $message .= 'Please check your OpenRouter API settings or try again.';
    }
    
    ob_end_clean();
    echo json_encode([
        'status'  => 'error',
        'message' => $message,
        'debug' => substr($debugInfo, 0, 200),
        'suggestions' => [
            'Show latest bookings',
            'Total revenue this month',
            'Show customers',
            'Pending payments',
        ]
    ]);
    exit;
}

$sql = $aiResult['sql'];
$source = 'AI (' . $aiResult['model'] . ')';

// Step 4: Execute the SQL query
try {
    ob_end_clean();
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format response message
    $message = count($results) . ' result(s) found.';
    
    if (count($results) === 0) {
        $message = 'No results found for your query.';
    } elseif (count($results) === 1 && count($results[0]) === 1) {
        // Single value result (like COUNT, SUM)
        $value = reset($results[0]);
        $key = key($results[0]);
        if (is_numeric($value)) {
            $message = ucfirst(str_replace('_', ' ', $key)) . ': ' . number_format($value, 2);
        }
    }

    echo json_encode([
        'status'  => 'success',
        'results' => $results,
        'message' => $message,
        'query'   => $sql,
        'source'  => $source,
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Query execution error: ' . $e->getMessage(),
        'query'   => $sql,
    ]);
}
?>
