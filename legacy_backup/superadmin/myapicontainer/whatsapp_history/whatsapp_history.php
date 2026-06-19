<?php
/**
 * ============================================================================
 * WHATSAPP HISTORY API  —  whatsapp_history.php
 * ============================================================================
 *
 * Three modes in ONE file:
 *
 *  1. GET  ?lead_phone=9440698613
 *         → Read messages from user_remarks.whatsapp_history (for CRM sidebar)
 *
 *  2. POST (no action param)
 *         → Store a message into user_remarks.whatsapp_history
 *           Auth: header  x-wa-store-secret: wa-store-2026
 *                  OR param  ?secret=wa-store-2026
 *           Body: { lead_number, message, sender_number, direction, timestamp }
 *           Test via Postman — no session required for this mode.
 *
 *  3. POST ?action=send
 *         → Proxy: forward lead payload to Esha callback/lead API
 *           (called by the CRM send button, session required)
 *
 * ============================================================================
 */

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');

// ── Sync to IST for native timestamps ──────────────────────────────────────────
date_default_timezone_set('Asia/Kolkata');

// ── Config ────────────────────────────────────────────────────────────────────
define('ESHA_BASE',        'https://omegaappbuilder.com');   // ← new domain (no agent. prefix)
define('ESHA_KEY',         getenv('ESHA_KEY') ?: 'whsec_008283d646aba0038a769475bb7b67a17676d42ee4111002b87d31f7fed18d98');
define('ESHA_ADMIN_TOKEN', getenv('ESHA_ADMIN_TOKEN') ?: 'spool_admin_523f6e1341451f1bc876fa3b'); // Bearer token for /api/messages/send
define('TENANT_ID',        getenv('ESHA_TENANT_ID') ?: 'tenant_omega_ba8790e7364b');     // kept for reference
define('STORE_SECRET',     getenv('ESHA_STORE_SECRET') ?: 'wa-store-2026');


// ── DB (CRM database via userlogin6/config.php) ───────────────────────────────
require_once dirname(__DIR__, 3) . '/userlogin6/config.php';
$config = new Config();
$conn = $config->getConnection();

// ── Helpers ───────────────────────────────────────────────────────────────────
function last10h($ph)
{
    return substr(preg_replace('/\D/', '', (string) $ph), -10);
}

function ensureWaColumn($conn)
{
    $conn->exec("ALTER TABLE user_remarks ADD COLUMN IF NOT EXISTS whatsapp_history TEXT NULL DEFAULT NULL");
    try {
        $conn->exec("ALTER TABLE user_remarks ADD COLUMN IF NOT EXISTS unread_wa_count INT DEFAULT 0");
    } catch (Exception $e) {
    }
}

function tsToEpoch($value)
{
    $value = trim((string) $value);
    if ($value === '')
        return null;
    $t = strtotime($value);
    return ($t === false) ? null : $t;
}

function isDuplicateWaEntry($history, $candidate, $windowSeconds = 20)
{
    if (empty($history) || !is_array($history))
        return false;

    $candMsg = strtolower(trim(preg_replace('/\s+/', ' ', (string) ($candidate['message'] ?? ''))));
    $candSender = trim((string) ($candidate['sender_number'] ?? ''));
    $candDirection = strtoupper(trim((string) ($candidate['direction'] ?? '')));
    $candRole = strtolower(trim((string) ($candidate['role'] ?? '')));
    $candLead = trim((string) ($candidate['lead_number'] ?? ''));
    $candTimeRaw = trim((string) ($candidate['time'] ?? ''));
    $candTs = tsToEpoch($candTimeRaw);

    // Check recent entries first; duplicates from retries are usually adjacent.
    for ($i = count($history) - 1; $i >= 0 && $i >= count($history) - 20; $i--) {
        $h = $history[$i];
        if (!is_array($h))
            continue;

        $hMsg = strtolower(trim(preg_replace('/\s+/', ' ', (string) ($h['message'] ?? ''))));
        $hSender = trim((string) ($h['sender_number'] ?? ''));
        $hDirection = strtoupper(trim((string) ($h['direction'] ?? '')));
        $hRole = strtolower(trim((string) ($h['role'] ?? '')));
        $hLead = trim((string) ($h['lead_number'] ?? ''));

        if ($hMsg !== $candMsg || $hSender !== $candSender || $hDirection !== $candDirection || $hRole !== $candRole || $hLead !== $candLead) {
            continue;
        }

        $hTimeRaw = trim((string) ($h['time'] ?? ''));
        if ($candTimeRaw !== '' && $hTimeRaw !== '' && $candTimeRaw === $hTimeRaw) {
            return true;
        }

        $hTs = tsToEpoch($hTimeRaw);
        if ($candTs !== null && $hTs !== null && abs($candTs - $hTs) <= (int) $windowSeconds) {
            return true;
        }
    }

    return false;
}

function findLeadRow($conn, $l10, $leadId = null, $rowId = null)
{
    // If row_id (user_remarks.id) is provided, use it directly for exact match
    if (!empty($rowId)) {
        $numericRowId = (int) preg_replace('/[^0-9]/', '', (string) $rowId);
        if ($numericRowId > 0) {
            $stmt = $conn->prepare("
                SELECT ur.id           AS remark_id,
                       sud.id          AS lead_id,
                       ur.user_unique_id,
                       ur.whatsapp_history,
                       ur.wa_auto_reply
                FROM   user_remarks ur
                JOIN   shi_upload_data sud ON ur.upload_data_id = sud.id
                WHERE  ur.id = :rid
                LIMIT  1
            ");
            $stmt->execute([':rid' => $numericRowId]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($res)
                return $res;
        }
    }

    // If lead_id (upload_data_id) is provided, use it directly for exact match
    if (!empty($leadId)) {
        $numericLeadId = (int) preg_replace('/[^0-9]/', '', (string) $leadId);
        $stmt = $conn->prepare("
            SELECT sud.id          AS lead_id,
                   ur.user_unique_id,
                   ur.whatsapp_history,
                   ur.wa_auto_reply
            FROM   shi_upload_data sud
            LEFT JOIN user_remarks ur ON ur.upload_data_id = sud.id
            WHERE  sud.id = :lid
            ORDER  BY (ur.whatsapp_history IS NOT NULL) DESC
            LIMIT  1
        ");
        $stmt->execute([':lid' => $numericLeadId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Fallback: search by phone (last 10 digits), prefer rows with existing history
    $stmt = $conn->prepare("
        SELECT sud.id          AS lead_id,
               ur.user_unique_id,
               ur.whatsapp_history,
               ur.wa_auto_reply
        FROM   shi_upload_data sud
        LEFT JOIN user_remarks ur ON ur.upload_data_id = sud.id
        WHERE  sud.number LIKE :ph
        ORDER  BY (ur.whatsapp_history IS NOT NULL) DESC,
                  ur.upload_data_id DESC
        LIMIT  1
    ");
    $stmt->execute([':ph' => '%' . $l10]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// ── JIT Sync Helper ─────────────────────────────────────────────────────────────
function syncEshaJIT($conn, $payload)
{
    if (empty($payload['rowId']) || empty($payload['salespersonId']))
        return false;
    $numericId = preg_replace('/[^0-9]/', '', $payload['rowId']);
    if (empty($numericId))
        return false;

    // If the ID matches a user_remarks entry, get the associated upload_data_id. Or if it's already an upload_data_id, use it directly.
    $stmt = $conn->prepare("
        SELECT sud.id AS uploadDataId, sud.project, sud.number 
        FROM shi_upload_data sud 
        LEFT JOIN user_remarks ur ON ur.upload_data_id = sud.id 
        WHERE ur.id = :id OR sud.id = :id
        ORDER BY (ur.id IS NOT NULL) DESC
        LIMIT 1
    ");
    $stmt->execute([':id' => (int) $numericId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $projectRaw = $row['project'] ?? 'Default Project';
        $projectSafe = strtolower(preg_replace('/[^a-z0-9_]/i', '_', $projectRaw));
        $rawPhone = preg_replace('/\D/', '', (string) $row['number']);
        $e164Phone = (strlen($rawPhone) == 10) ? '+91' . $rawPhone : '+' . $rawPhone;

        // ── New /api/initiate payload (snake_case, arrays) ──────────────────
        $assignPayload = [
            'salesperson_id'      => $payload['salespersonId'] ?? 'sp_agent',
            'salesperson_phone'   => !empty($payload['senderPhone']) ? $payload['senderPhone'] : '+919632056699',
            'project_ids'         => ['proj_' . $projectSafe . ($projectRaw ? '(' . $projectRaw . ')' : '')],
            'row_ids'             => [$payload['rowId']],
            'lead_numbers'        => [$e164Phone],
            'sendInitialOutreach' => false, // JIT re-init: no fresh AI outreach
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => ESHA_BASE . '/api/initiate',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($assignPayload),
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
            ],
        ]);
        $jitBody = curl_exec($ch);
        $jitHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $logLine = '[' . date('Y-m-d H:i:s') . '] [JIT-SYNC] req=' . json_encode($assignPayload) . ' res=' . $jitBody . PHP_EOL;
        @file_put_contents(dirname(__DIR__, 3) . '/userlogin6/wa_auto.log', $logLine, FILE_APPEND);

        $jitBodyArr = json_decode($jitBody, true);
        if ($jitHttp >= 200 && $jitHttp < 300 && !empty($jitBodyArr['ok'])) {
            return "JIT_SUCCESS";
        }
        return "JIT_API_ERR_" . $jitHttp . "_" . $jitBody;
    }

    $logLine = '[' . date('Y-m-d H:i:s') . '] [JIT-SYNC-FAIL] rowId=' . $payload['rowId'] . ' Not found in CRM DB.' . PHP_EOL;
    @file_put_contents(dirname(__DIR__, 3) . '/userlogin6/wa_auto.log', $logLine, FILE_APPEND);

    return "DB_ROW_NOT_FOUND_" . $numericId;
}

// ── Route: ?action=pool_add  (Omega Pool — session required) ─────────────────
if (($_GET['action'] ?? '') === 'pool_add') {
    if (session_status() === PHP_SESSION_NONE)
        session_start();
    if (empty($_SESSION['tablename']) && empty($_SESSION['loggedin'])) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
        exit;
    }

    $payload = json_decode(file_get_contents('php://input'), true);
    if (empty($payload['numbers']) || !is_array($payload['numbers'])) {
        echo json_encode(['ok' => false, 'error' => 'numbers[] array is required']);
        exit;
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => 'https://omegaappbuilder.com/api/pool/add',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode(['numbers' => $payload['numbers']]),
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    ]);
    $body    = curl_exec($ch);
    $err     = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err) {
        echo json_encode(['ok' => false, 'error' => $err]);
        exit;
    }

    http_response_code($httpCode);
    echo $body;
    exit;
}

// ── Route: ?action=download_attachment  (Proxy for cross-origin downloads) ───────────────
if (($_GET['action'] ?? '') === 'download_attachment') {
    if (session_status() === PHP_SESSION_NONE)
        session_start();
    if (empty($_SESSION['tablename']) && empty($_SESSION['loggedin'])) {
        http_response_code(401);
        exit('Not authenticated');
    }

    $url = $_GET['url'] ?? '';
    // Decode html entities in case URL has &amp;
    $url = html_entity_decode($url);

    // IMPORTANT: Spaces in the URL will cause curl to send an invalid HTTP request line 
    // resulting in a 400 or 404 from Nginx/Cloudflare. We must ensure spaces are encoded.
    $url = str_replace(' ', '%20', $url);

    if (filter_var($url, FILTER_VALIDATE_URL) === false) {
        http_response_code(400);
        exit('Invalid URL');
    }

    $filename = $_GET['filename'] ?? basename(parse_url($url, PHP_URL_PATH));
    if (empty($filename))
        $filename = 'attachment';

    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . str_replace('"', '\\"', $filename) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');

    // Prevent any preceding whitespace from corrupting the binary data
    while (ob_get_level()) {
        ob_end_clean();
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, false); // Stream directly
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
    // Ignore SSL errors for external AWS buckets sometimes
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // If the proxy blocked us or it was a 404, the file might still be "downloaded" as an error HTML, which corrupts it.
    // However, Streaming mode (CURLOPT_RETURNTRANSFER = false) immediately outputs it. So we just exit.
    exit;
}

// ── Route: ?action=bulk_assign  (Esha proxy — session required) ──────────────
if (($_GET['action'] ?? '') === 'bulk_assign') {
    if (session_status() === PHP_SESSION_NONE)
        session_start();
    if (empty($_SESSION['tablename']) && empty($_SESSION['loggedin'])) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
        exit;
    }

    set_time_limit(0); // Prevent PHP script timeout for bulk requests

    $payload = json_decode(file_get_contents('php://input'), true);
    if (empty($payload)) {
        echo json_encode(['ok' => false, 'error' => 'Empty payload']);
        exit;
    }

    // Transform JS legacy payload → new /api/initiate format
    $rowIdsArr   = array_values(array_filter(array_map('trim', explode(',', $payload['rowIds']   ?? ''))));
    $phonesArr   = array_values(array_filter(array_map('trim', explode(',', $payload['leadPhones'] ?? ''))));
    $projectId   = trim($payload['projectIds']   ?? '');
    $projectName = trim($payload['projectNames'] ?? '');

    $initiatePayload = [
        'salesperson_id'      => $payload['salespersonId']    ?? '',
        'salesperson_phone'   => $payload['salespersonPhone'] ?? '+919632056699',
        'project_ids'         => [$projectId . ($projectName ? '(' . $projectName . ')' : '')],
        'row_ids'             => $rowIdsArr,
        'lead_numbers'        => $phonesArr,
        'sendInitialOutreach' => true,
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => ESHA_BASE . '/api/initiate',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($initiatePayload),
        CURLOPT_TIMEOUT        => 120,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
        ],
    ]);
    $body = curl_exec($ch);
    $err = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($err) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $err]);
        exit;
    }

    $eshaResp = json_decode($body, true);
    if ($eshaResp === null) {
        // Esha returned an invalid JSON (e.g. 504 Gateway Timeout or an Nginx 500 HTML page)
        http_response_code($httpCode > 0 ? $httpCode : 500);
        echo json_encode([
            'ok' => false,
            'error' => "Esha API Error (HTTP $httpCode)",
            'details' => substr(trim(strip_tags($body)), 0, 300)
        ]);
        exit;
    }

    http_response_code($httpCode);
    echo $body;
    exit;
}

// ── Route: ?action=sales_send  (Esha proxy — session required) ───────────────
if (($_GET['action'] ?? '') === 'sales_send') {
    if (session_status() === PHP_SESSION_NONE)
        session_start();
    if (empty($_SESSION['tablename']) && empty($_SESSION['loggedin'])) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
        exit;
    }

    $payload = json_decode(file_get_contents('php://input'), true);

    if (empty($payload)) {
        echo json_encode(['ok' => false, 'error' => 'Empty payload']);
        exit;
    }

    // ── /api/messages/send payload: row_id + text (JSON, not multipart) ──────────
    $sendPayload = [
        'row_id' => $payload['rowId'] ?? $payload['row_id'] ?? '',
        'text'   => $payload['text'] ?? '',
    ];

    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . ESHA_ADMIN_TOKEN,
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => ESHA_BASE . '/api/messages/send',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($sendPayload),
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => $headers,
    ]);
    $body = curl_exec($ch);
    $err = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $eshaResp = json_decode($body, true);

    // JIT Sync: if the lead isn't initiated yet, register via /api/initiate then retry
    if ($httpCode == 404 || $httpCode == 400 || $httpCode == 403 || (!empty($eshaResp['error']) && strpos(str_replace(' ', '_', $eshaResp['error']), 'not_') !== false)) {
        $jitResult = syncEshaJIT($conn, $payload);
        if ($jitResult === "JIT_SUCCESS" || $jitResult === true) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => ESHA_BASE . '/api/messages/send',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode($sendPayload),
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_HTTPHEADER     => $headers,
            ]);
            $body = curl_exec($ch);
            $err = curl_error($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $eshaResp = json_decode($body, true);
            curl_close($ch);
        } else {
            $eshaResp['error'] = (isset($eshaResp['error']) ? $eshaResp['error'] : 'Unknown Error') . ' | JIT_FAILED: ' . $jitResult;
            $body = json_encode($eshaResp);
        }
    }

    if ($err) {
        echo json_encode(['ok' => false, 'error' => $err]);
        exit;
    }

    http_response_code($httpCode);
    echo $body;
    exit;
}

// ── Route: ?action=auto_reply  (Esha proxy — session required) ───────────────
if (($_GET['action'] ?? '') === 'auto_reply') {
    if (session_status() === PHP_SESSION_NONE)
        session_start();
    if (empty($_SESSION['tablename']) && empty($_SESSION['loggedin'])) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
        exit;
    }

    $payload = json_decode(file_get_contents('php://input'), true);
    if (empty($payload)) {
        echo json_encode(['ok' => false, 'error' => 'Empty payload']);
        exit;
    }

    // ── Transform incoming payload → new /api/auto-reply format ─────────────────
    $autoReplyPayload = [
        'row_id'  => $payload['rowId'] ?? '',
        'enabled' => isset($payload['autoReplyEnabled']) ? (bool) $payload['autoReplyEnabled'] : true,
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => ESHA_BASE . '/api/auto-reply',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($autoReplyPayload),
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
        ],
    ]);
    $body = curl_exec($ch);
    $err = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $eshaResp = json_decode($body, true);

    // JIT Sync: if the lead isn't known to the API, initiate it then retry
    if ($httpCode == 404 || $httpCode == 400 || $httpCode == 403) {
        $jitResult = syncEshaJIT($conn, $payload);
        if ($jitResult === "JIT_SUCCESS" || $jitResult === true) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => ESHA_BASE . '/api/auto-reply',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode($autoReplyPayload),
                CURLOPT_TIMEOUT        => 20,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            ]);
            $body = curl_exec($ch);
            $err = curl_error($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $eshaResp = json_decode($body, true);
            curl_close($ch);
        } else {
            $eshaResp['error'] = (isset($eshaResp['error']) ? $eshaResp['error'] : 'Unknown Error') . ' | JIT_FAILED: ' . $jitResult;
            $body = json_encode($eshaResp);
        }
    }

    if ($err) {
        echo json_encode(['ok' => false, 'error' => $err]);
        exit;
    }

    // Persist auto-reply state to DB on success (ok:true OR 2xx HTTP code)
    $isSuccess = (!empty($eshaResp['ok']) || ($httpCode >= 200 && $httpCode < 300));
    if ($isSuccess && !empty($payload['rowId'])) {
        $numericId = preg_replace('/[^0-9]/', '', $payload['rowId']);
        if (!empty($numericId)) {
            try {
                $conn->exec("ALTER TABLE user_remarks ADD COLUMN IF NOT EXISTS wa_auto_reply TINYINT(1) DEFAULT 1");
            } catch (Throwable $e) {
            }

            $val = empty($payload['autoReplyEnabled']) ? 0 : 1;
            $conn->prepare("UPDATE user_remarks SET wa_auto_reply = ? WHERE id = ?")
                ->execute([$val, (int) $numericId]);
        }
    }


    http_response_code($httpCode);
    echo $body;
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// ── Route: POST (no action) — Store a message (Postman-testable) ──────────────
if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    @file_put_contents(__DIR__ . '/wh_in.log', "[" . date('Y-m-d H:i:s') . "] METHOD: POST, HEADERS: " . json_encode($_SERVER) . "\nBODY: " . $rawInput . "\n\n", FILE_APPEND);

    // No auth required — open webhook endpoint for Omega sender-pool callbacks.

    $body = json_decode(file_get_contents('php://input'), true);
    if (empty($body)) {
        echo json_encode(['ok' => false, 'error' => 'Empty or invalid JSON body']);
        exit;
    }

    // Required fields
    $leadPhone = trim($body['lead_number'] ?? '');
    $messageText = trim($body['message'] ?? '');
    $senderNumber = trim($body['sender_number'] ?? '');
    $bodyLeadId = trim($body['lead_id'] ?? ''); // optional: exact upload_data_id
    $bodyRowId = trim($body['row_id'] ?? ''); // optional: exact user_remarks.id

    // Check if there are attachments
    $mediaUrl = trim($body['media_url'] ?? $body['file_url'] ?? $body['attachment_url'] ?? '');
    $attachments = $body['attachments'] ?? null;
    $hasMedia = !empty($mediaUrl) || !empty($attachments);

    if (empty($leadPhone) || (empty($messageText) && !$hasMedia)) {
        echo json_encode(['ok' => false, 'error' => 'lead_number and message/media are required']);
        exit;
    }

    $direction = strtoupper(trim($body['direction'] ?? 'INBOUND'));
    if (!in_array($direction, ['INBOUND', 'OUTBOUND']))
        $direction = 'INBOUND';
    $role = ($direction === 'INBOUND') ? 'lead' : 'esha';

    // Auto sender_number: INBOUND = lead phone, OUTBOUND = Esha number
    if (empty($senderNumber)) {
        $senderNumber = ($direction === 'INBOUND') ? last10h($leadPhone) : '9632056699';
    }

    $timestamp = trim($body['time_stamp'] ?? $body['time-stamp'] ?? $body['timestamp'] ?? '') ?: date('Y-m-d H:i:s');

    $l10 = last10h($leadPhone);
    // Use exact row_id or lead_id if provided, else search by phone
    $row = findLeadRow($conn, $l10, $bodyLeadId ?: null, $bodyRowId ?: null);

    if (!$row || empty($row['lead_id'])) {
        echo json_encode([
            'ok' => false,
            'error' => 'Lead not found for phone ' . $l10,
            'tip' => 'Check that the number exists in shi_upload_data.number',
        ]);
        exit;
    }

    ensureWaColumn($conn);

    $leadId = (int) $row['lead_id'];
    $userId = $row['user_unique_id'];

    // ── DEBUG MODE: return trace without writing ────────────────────────────
    if (!empty($_GET['debug'])) {
        // Count existing user_remarks rows for this upload_data_id
        $dbgStmt = $conn->prepare("SELECT id, user_unique_id, created_at, (whatsapp_history IS NOT NULL) AS has_history FROM user_remarks WHERE upload_data_id = :id");
        $dbgStmt->execute([':id' => $leadId]);
        $existingRows = $dbgStmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode([
            'debug' => true,
            'resolved_lead_id' => $leadId,
            'row_user_uid' => $userId,
            'existing_user_remarks_rows' => $existingRows,
            'body_lead_id' => $bodyLeadId,
            'phone_l10' => $l10,
            'would_update' => "UPDATE user_remarks SET whatsapp_history=... WHERE upload_data_id=$leadId",
        ], JSON_PRETTY_PRINT);
        exit;
    }

    // Load existing history
    $history = [];
    if (!empty($row['whatsapp_history'])) {
        $history = json_decode($row['whatsapp_history'], true) ?: [];
    }

    // New entry (4 core fields + meta)
    $entry = [
        'lead_number' => $l10,
        'message' => $messageText,
        'sender_number' => $senderNumber,
        'role' => $role,
        'direction' => $direction,
        'time' => $timestamp,
    ];

    if ($mediaUrl)
        $entry['media_url'] = $mediaUrl;
    if ($attachments)
        $entry['attachments'] = $attachments;

    if (isDuplicateWaEntry($history, $entry, 20)) {
        echo json_encode([
            'ok' => true,
            'deduped' => true,
            'lead_id' => $leadId,
            'stored' => $entry,
            'total' => count($history),
            'message' => 'Duplicate webhook message ignored',
        ]);
        exit;
    }

    $history[] = $entry;

    $json = json_encode($history, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    $remarkId = $row['remark_id'] ?? null;
    $totalRowsForLead = 1;

    // UPDATE specifically the user_remarks row if row_id was provided to isolate chat per project.
    // NEVER INSERT — we don't want to create new rows from the API.
    if (!empty($remarkId)) {
        $updated = $conn->prepare("UPDATE user_remarks SET whatsapp_history=:wh, unread_wa_count = COALESCE(unread_wa_count, 0) + 1 WHERE id=:rid");
        $updated->execute([':wh' => $json, ':rid' => $remarkId]);
    } else {
        $updated = $conn->prepare("UPDATE user_remarks SET whatsapp_history=:wh, unread_wa_count = COALESCE(unread_wa_count, 0) + 1 WHERE upload_data_id=:id");
        $updated->execute([':wh' => $json, ':id' => $leadId]);

        $afterCount = $conn->prepare("SELECT COUNT(*) FROM user_remarks WHERE upload_data_id = :id");
        $afterCount->execute([':id' => $leadId]);
        $totalRowsForLead = (int) $afterCount->fetchColumn();
    }

    if ($updated->rowCount() === 0) {
        echo json_encode(['ok' => false, 'error' => 'No user_remarks row found for lead_id ' . $leadId . '. The lead must exist in CRM first.']);
        exit;
    }

    echo json_encode([
        'ok' => true,
        'lead_id' => $leadId,
        'stored' => $entry,
        'total' => count($history),
        'rows_in_db_for_lead' => $totalRowsForLead, // shows if duplicate rows exist
        'message' => 'Saved. Open WhatsApp chat history in CRM to see it.',
    ]);
    exit;
}

// ── Route: GET ?lead_phone=... — Read history (CRM sidebar, session required) ─
if ($method === 'GET') {
    if (session_status() === PHP_SESSION_NONE)
        session_start();
    if (empty($_SESSION['tablename']) && empty($_SESSION['loggedin'])) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
        exit;
    }

    $rawPhone = trim($_GET['lead_phone'] ?? '');
    $getLeadId = trim($_GET['lead_id'] ?? ''); // optional: exact upload_data_id
    $getRowId = trim($_GET['row_id'] ?? ''); // optional: exact user_remarks.id

    if (empty($rawPhone) && empty($getLeadId) && empty($getRowId)) {
        echo json_encode(['status' => 'error', 'message' => 'lead_phone, lead_id, or row_id is required']);
        exit;
    }

    $l10 = last10h($rawPhone);
    // Use exact row_id or lead_id if provided, else phone-based search
    $row = findLeadRow($conn, $l10, $getLeadId ?: null, $getRowId ?: null);

    // Debug: log every GET request and what was found
    @file_put_contents(__DIR__ . '/wh_get.log', "[" . date('Y-m-d H:i:s') . "] GET params: phone={$rawPhone} l10={$l10} row_id={$getRowId} lead_id={$getLeadId} | found_row=" . json_encode($row ? ['remark_id' => $row['remark_id'] ?? null, 'lead_id' => $row['lead_id'] ?? null, 'has_history' => !empty($row['whatsapp_history']), 'history_chars' => strlen($row['whatsapp_history'] ?? '')] : null) . "\n", FILE_APPEND);

    if (!$row || empty($row['whatsapp_history'])) {
        echo json_encode([
            'status' => 'success',
            'messages' => [],
            'phone' => '+91' . $l10,
            'auto_reply' => ($row && isset($row['wa_auto_reply'])) ? (bool) $row['wa_auto_reply'] : true
        ]);
        exit;
    }

    $history = json_decode($row['whatsapp_history'], true) ?: [];

    // Reset unread message count since the chat is now being opened
    if (!empty($row['remark_id'])) {
        $conn->exec("UPDATE user_remarks SET unread_wa_count = 0 WHERE id = " . (int) $row['remark_id']);
    }

    // Sort chronologically
    usort($history, function ($a, $b) {
        return (strtotime($a['time'] ?? '') ?: 0) - (strtotime($b['time'] ?? '') ?: 0);
    });

    // --- APPLY LIMIT AND OFFSET ---
    $limit = isset($_GET['limit']) ? max(1, (int) $_GET['limit']) : 0;
    $offset = isset($_GET['offset']) ? max(0, (int) $_GET['offset']) : 0;
    $has_more = false;

    if ($limit > 0) {
        $totalMessages = count($history);
        $sliceStart = $totalMessages - $limit - $offset;
        $sliceLength = $limit;

        if ($sliceStart < 0) {
            $sliceLength += $sliceStart; // shrink the slice
            $sliceStart = 0;
        }

        if ($sliceLength > 0) {
            $history = array_slice($history, $sliceStart, $sliceLength);
            $has_more = ($totalMessages - $limit - $offset) > 0;
        } else {
            $history = [];
        }
    }

    echo json_encode([
        'status' => 'success',
        'phone' => '+91' . $l10,
        'lead_id' => $row['lead_id'] ?? null,
        'auto_reply' => isset($row['wa_auto_reply']) ? (bool) $row['wa_auto_reply'] : true,
        'has_more' => $has_more,
        'messages' => array_values($history),
    ]);
    exit;
}

// ── Method not allowed ────────────────────────────────────────────────────────
http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Method not allowed. Use GET or POST.']);
