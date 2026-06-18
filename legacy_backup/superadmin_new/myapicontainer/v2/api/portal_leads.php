<?php
include '../../config.php';

// Initialize database connection
$config = new Config();
$db = $config->getConnection();

// Get the raw POST body and decode it
$data = json_decode(file_get_contents("php://input"), true);

// Validate required fields
$requiredFields = ['name', 'email', 'number', 'location', 'project'];
foreach ($requiredFields as $field) {
    if (empty($data[$field])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Missing field: $field"]);
        exit;
    }
}

// Get API-Key from headers
$headers = getallheaders();
if (!isset($headers['API-Key'])) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Missing API-Key in headers."]);
    exit;
}
$apiKey = $headers['API-Key'];

// Fetch lead_source using API-Key
$query = "SELECT lead_source FROM project_apis WHERE api_key = :api_key";
$stmt = $db->prepare($query);
$stmt->execute(['api_key' => $apiKey]);
$result = $stmt->fetch();

if (!$result) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Invalid API-Key."]);
    exit;
}

$leadSource = $result['lead_source'];
$projectName = $data['project'];
$defaultUser = "Vipul0001";
$originalApiKey = $headers['API-Key'];
$projectName = $data['project'];
$apiKeyn = md5($originalApiKey . '_' . $projectName); // Unique api key
$created_at = (new DateTime('now', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d H:i:s');

// Insert into portal_leads_data (you can change this table name as needed)
try {
    $insertQuery = "INSERT INTO project_apis (project_name, api_key, lead_source, assign_user, created_at) 
                    VALUES (:project_name, :api_key, :lead_source, :assign_user, :created_at)";
    $insertStmt = $db->prepare($insertQuery);
    $insertStmt->execute([
        'project_name' => $projectName,
        'api_key' => $apiKeyn,
        'lead_source' => $leadSource,
        'assign_user' => $defaultUser,
        'created_at' => $created_at
    ]);
} catch (PDOException $e) {
    // If it's a duplicate entry error, continue silently
    if ($e->errorInfo[1] == 1062) {
        // Duplicate entry, ignore and continue
    } else {
        // For any other DB error, rethrow
        throw $e;
    }
}

// Validate API Key
$query = "SELECT * FROM project_apis WHERE project_name = :project_name AND lead_source = :lead_source";
$stmt = $db->prepare($query);
$stmt->execute([
    'project_name' => $projectName,
    'lead_source' => $leadSource
]);
$project = $stmt->fetch();

if (!$project) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Invalid API key"]);
    exit;
}

// $assign_users = explode(',', $project['assign_user']);
$assign_users = array_map('trim', explode(',', $project['assign_user']));
$last_assigned_user = $project['last_assigned_user'];
$current_user_index = array_search($last_assigned_user, $assign_users);

// Determine the next user in the round-robin assignment
$next_user_index = ($current_user_index === false || $current_user_index === count($assign_users) - 1) ? 0 : $current_user_index + 1;
$next_user = $assign_users[$next_user_index];
// Determine the next user in the round-robin assignment
// $next_user = $assign_users[($current_user_index === false || $current_user_index === count($assign_users) - 1) ? 0 : $current_user_index + 1];

// Fetch the assigned user's number
$userQuery = "SELECT phonenumber FROM accounts WHERE tablename = :tablename";
$userStmt = $db->prepare($userQuery);
$userStmt->execute(['tablename' => $next_user]);
$user = $userStmt->fetch();

// Add user fetch details to debug info
$debug_info["fetched_user"] = $user;

if (!$user || empty($user['phonenumber'])) {
    // Include debug info in the response for better analysis
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Assigned user's phone number not found.",
        "debug_info" => $debug_info
    ]);
    exit;
}

$user_number = $user['phonenumber'];

// Extract payload data
$name = $data['name'] ?? '';
$email = $data['email'] ?? '';
$number = $data['number'] ?? '';
$location = $data['location'] ?? '';

try {
    // Start a transaction
    $db->beginTransaction();

    // Check for duplicate in `shi_upload_data`
    $checkDuplicateQuery = "SELECT id FROM shi_upload_data WHERE email = :email AND number = :number AND project = :project";
    $checkStmt = $db->prepare($checkDuplicateQuery);
    $checkStmt->execute([
        'email' => $email,
        'number' => $number,
        'project' => $project['project_name']
    ]);

    if ($checkStmt->rowCount() > 0) {
        // Record already exists
        http_response_code(409);
        echo json_encode(["status" => "error", "message" => "Duplicate lead detected."]);
        $db->rollBack(); // Rollback the transaction
        exit;
    }

    // Insert into `shi_upload_data` table
    $insertQuery1 = "INSERT INTO shi_upload_data 
        (name, email, number, location, type, source_of_lead, assign_to_user, created_at, project)
        VALUES 
        (:name, :email, :number, :location, '3 BHK', :source_of_lead, :assign_to_user, :created_at, :project)";
    $stmt1 = $db->prepare($insertQuery1);
    $stmt1->execute([
        'name' => $name,
        'email' => $email,
        'number' => $number,
        'location' => $location,
        'source_of_lead' => $project['lead_source'],
        'assign_to_user' => $next_user,
        'created_at' => $created_at, // ✅ Indian Standard Time
        'project' => $project['project_name']
    ]);
    
    // Get the last inserted ID
    $id_of_shi_upload_data = $db->lastInsertId();
    
    // Insert into `user_remarks` table
    $insertQuery2 = "INSERT INTO user_remarks 
        (upload_data_id, user_unique_id, assign_project_name, created_at)
        VALUES 
        (:id_of_shi_upload_data, :assign_to_user, :project_name, :created_at)";
    $stmt2 = $db->prepare($insertQuery2);
    $stmt2->execute([
        'id_of_shi_upload_data' => $id_of_shi_upload_data,
        'assign_to_user' => $next_user,
        'project_name' => $project['project_name'],
        'created_at' => $created_at // ✅ Indian Standard Time
    ]);
    
    // Update the last assigned user (if you also have a created_at column for logs, include it here too)
    $updateQuery = "UPDATE project_apis 
                    SET last_assigned_user = :last_assigned_user, updated_at = :created_at 
                    WHERE project_name = :project_name AND lead_source = :source_of_lead";
    $updateStmt = $db->prepare($updateQuery);
    $updateStmt->execute([
        'last_assigned_user' => $next_user,
        'project_name' => $project['project_name'],
        'source_of_lead' => $project['lead_source'],
        'created_at' => $created_at // ✅ updates with IST
    ]);
    
    // Google leads counter Start
    $leadCountQuery = "SELECT COUNT(*) AS lead_count FROM shi_upload_data 
                        WHERE project = :project_name AND source_of_lead = :source_of_lead";
    $leadCountStmt = $db->prepare($leadCountQuery);
    $leadCountStmt->execute([
        'project_name' => $project['project_name'],
        'source_of_lead' => $project['lead_source']
    ]);
    $leadCount = $leadCountStmt->fetchColumn();
    
    // Update the count_form_leads column in the project_apis table
    $updateCountQuery = "UPDATE project_apis 
                        SET fb_form_leads = :lead_count, updated_at = :created_at 
                        WHERE project_name = :project_name AND lead_source = :source_of_lead";
    $updateCountStmt = $db->prepare($updateCountQuery);
    $updateCountStmt->execute([
        'lead_count' => $leadCount,
        'project_name' => $project['project_name'],
        'source_of_lead' => $project['lead_source'],
        'created_at' => $created_at // ✅ Indian Standard Time
    ]);
    // Google leads counter End
    
    // Commit the transaction
    $db->commit();

    // -----------------------------
    // External API logic (ONLY for prestige projects)
    // -----------------------------
    // Define prestige projects (you can expand or change this list)
    $prestigeProjects = [
        'Eden Park at The Prestige City',
        'Meridian Park at The Prestige City',
        'Prestige City Avalon Park',
        'Avalon Park At The Prestige City',
        'Prestige Elm Park',
        'Prestige Lavender Fields',
        'Prestige Serenity Shores',
        'Prestige Suncrest',
        'SVS Silver Oaks'
    ];
    
    // Normalize project names for case-insensitive match
    $lowerPrestige = array_map('strtolower', $prestigeProjects);
    $incomingProjectLower = strtolower(trim($project['project_name'] ?? ''));
    
    // Normalize lead_source
    $leadSourceLower = strtolower(trim($project['lead_source'] ?? ''));
    
    // Only proceed if project is one of prestige projects AND lead_source is one of the two ad sources
    $allowedLeadSources = ['99acres ads', 'magicbricks ads'];
    
    if (in_array($incomingProjectLower, $lowerPrestige, true) && in_array($leadSourceLower, $allowedLeadSources, true)) {
    
        // External API endpoint and keys (move keys to env in production)
        $externalApiUrl = "https://www.searchhomesindia.in/superadmin_new/myapicontainer/v2/api/googleads_leads";
        $externalKeys = [
            '99acres ads'     => "caa8f8050dbe4216a5dc1d9a7c237e97",
            'magicbricks ads' => "112734f3257274b15e1dfac7a257f37e"
        ];
    
        // Pick the API key based on the lead source
        $externalApiKey = $externalKeys[$leadSourceLower] ?? null;
    
        if ($externalApiKey) {
            // Prepare payload (you already chose to send $location)
            $payloadData = [
                "name"     => $name,
                "email"    => $email,
                "number"   => $number,
                "location" => $location
                // If you decide later to include project mapping, add "project" => $mappedProjectName
            ];
    
            $ch = curl_init($externalApiUrl);
            $payloadJson = json_encode($payloadData);
    
            $curlHeaders = [
                "Content-Type: application/json",
                "API-Key: {$externalApiKey}"
            ];
    
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payloadJson);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
            $extResponse = curl_exec($ch);
            $curlErr    = curl_error($ch);
            $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
    
            // Log request/response
            $logEntry = date("Y-m-d H:i:s") .
                " | LeadID: {$id_of_shi_upload_data}" .
                " | ExternalAPI: {$project['lead_source']}" .
                " | URL: {$externalApiUrl}" .
                " | Payload: {$payloadJson}" .
                " | HTTP_STATUS: {$httpStatus}" .
                " | RESPONSE: " . ($extResponse ?? 'NULL') .
                " | CURL_ERROR: " . ($curlErr ?? 'NULL') . PHP_EOL;
    
            file_put_contents(__DIR__ . '/external_api.log', $logEntry, FILE_APPEND);
        } else {
            // This should not happen because we only allowed two exact lead sources, but safe-guard anyway
            $logEntry = date("Y-m-d H:i:s") . " | LeadID: {$id_of_shi_upload_data} | ExternalAPI: SKIPPED (no API key found for lead_source) | lead_source: {$project['lead_source']} | project: {$project['project_name']}" . PHP_EOL;
            file_put_contents(__DIR__ . '/external_api.log', $logEntry, FILE_APPEND);
        }
    } else {
        // Not a prestige project or not a permitted ad lead source — do not call external APIs
        $reason = [];
        if (!in_array($incomingProjectLower, $lowerPrestige, true)) $reason[] = 'project_not_in_prestige_list';
        if (!in_array($leadSourceLower, $allowedLeadSources, true)) $reason[] = 'lead_source_not_allowed';
    
        $logEntry = date("Y-m-d H:i:s") .
            " | LeadID: {$id_of_shi_upload_data}" .
            " | ExternalAPI: NOT_CALLED" .
            " | project: {$project['project_name']}" .
            " | lead_source: {$project['lead_source']}" .
            " | reason: " . implode(',', $reason) . PHP_EOL;
    
        file_put_contents(__DIR__ . '/external_api.log', $logEntry, FILE_APPEND);
    }

    // -----------------------------
    // End external API logic
    // -----------------------------

    echo json_encode(["status" => "success", "message" => "Lead successfully inserted."]);
} catch (Exception $e) {
    $db->rollBack();
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Failed to insert lead.", "error" => $e->getMessage()]);
}
exit;
?>