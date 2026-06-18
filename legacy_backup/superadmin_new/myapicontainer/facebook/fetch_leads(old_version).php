<?php
session_start();
include '../config.php';

if (!defined('CRM_API_KEY_SERVER')) {
    define('CRM_API_KEY_SERVER', '533f4175837e145064605e15e12c7273f98746fec7459b0168af9394a22c6efab6bba75cce18a3555250e473f4907d22aaae3e3f12e46dd8ef22fac38737c537');
}
ini_set('max_execution_time', 0);
set_time_limit(0);
date_default_timezone_set('Asia/Kolkata');

$config = new Config();
$db = $config->getConnection();
$created_at = (new DateTime())->format('Y-m-d H:i:s');

// Progress file
$progressFile = __DIR__ . "/progress.txt";
$progress = [
    'token_index' => 0,
    'form_index' => 0,
    'next_url' => null,
    'phase' => 'tokens'
];
if (file_exists($progressFile)) {
    $content = @file_get_contents($progressFile);
    $json = @json_decode($content, true);
    if (is_array($json)) {
        $progress = array_merge($progress, $json);
    }
}

// Helper logger
function logmsg($msg) {
    echo "[" . date('Y-m-d H:i:s') . "] " . $msg . "<br>";
    @ob_flush();
    @flush();
}

// Save progress atomically
// PATCH: atomic write using temp+rename and LOCK_EX
function save_progress($progressFile, $progress) {
    $tmp = $progressFile . '.tmp';
    $data = @json_encode($progress, JSON_UNESCAPED_SLASHES);
    if ($data === false) {
        $data = '{}';
    }
    if (@file_put_contents($tmp, $data, LOCK_EX) !== false) {
        @rename($tmp, $progressFile);
    } else {
        @file_put_contents($progressFile, $data);
    }
}

// cURL fetch with retry/backoff
function fetch_url_curl($url, $maxRetries = 4, $timeout = 60) {
    $attempt = 0;
    $baseSleep = 1;
    while ($attempt <= $maxRetries) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FAILONERROR => false,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_USERAGENT => 'FBLeadsSync/1.0',
        ]);
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($resp !== false && $httpCode >= 200 && $httpCode < 300) {
            return ['success' => true, 'http' => $httpCode, 'body' => $resp];
        }

        if ($httpCode == 429 || $httpCode >= 500 || $resp === false) {
            $attempt++;
            $sleep = (int)(pow(2, $attempt) * $baseSleep);
            logmsg("HTTP {$httpCode} or network error; retry #{$attempt} after {$sleep}s. err: {$err}");
            sleep($sleep);
            continue;
        }

        logmsg("HTTP {$httpCode} - not retrying. err: {$err}");
        return ['success' => false, 'http' => $httpCode, 'body' => $resp, 'error' => $err];
    }

    return ['success' => false, 'http' => null, 'body' => null, 'error' => 'max_retries_exceeded'];
}

// Check if database has facebook_lead_id column
$has_fb_lead_col = false;
try {
    $colCheck = $db->query("SHOW COLUMNS FROM shi_upload_data LIKE 'facebook_lead_id'")->fetch();
    if ($colCheck) $has_fb_lead_col = true;
} catch (Throwable $e) {
    // non-fatal; continue
    file_put_contents(__DIR__ . "/fetch_fb_leads_errors.log", date("Y-m-d H:i:s") . " | COL_CHECK_ERR | " . $e->__toString() . PHP_EOL, FILE_APPEND);
}

// Load all tokens
$stmt = $db->prepare("SELECT * FROM facebook_tokens WHERE expires_at > NOW() ORDER BY id ASC");
$stmt->execute();
$tokens = $stmt->fetchAll();
$totalTokens = count($tokens);
if ($totalTokens === 0) {
    die("No valid tokens found.");
}

// Batch size (defensive patch)
// PATCH: handle max_execution_time=0 (unlimited) and set sane default
$maxExecutionTime = (int) ini_get('max_execution_time'); // cast to int
$estimatedTimePerToken = 2; // seconds estimated per token
$defaultBatchSize = 20;
if ($maxExecutionTime > 10) {
    $batchSize = max(1, (int) floor($maxExecutionTime / $estimatedTimePerToken));
} else {
    $batchSize = $defaultBatchSize;
}
$processed = 0;
$scriptStartTime = time(); // optional runtime guard

// --- Helper functions that might be reused (guard redeclare) ---
if (!function_exists('maskNumber')) {
    function maskNumber($number) {
        $number = (string) ($number ?? '');
        $len = strlen($number);
        if ($len <= 3) return str_repeat("x", $len);
        return str_repeat("x", $len - 3) . substr($number, -3);
    }
}
if (!function_exists('maskEmail')) {
    function maskEmail($email) {
        $email = (string) ($email ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return "xxx@xxx.com";
        }
        $parts = explode("@", $email);
        if (count($parts) < 2) return "xxx@xxx.com";
        [$userPart, $domain] = $parts;
        $userMasked = substr($userPart, 0, 3) . str_repeat("x", max(0, strlen($userPart) - 3));
        $domainParts = explode(".", $domain);
        $domainName = substr($domainParts[0], 0, 2) . str_repeat("x", max(0, strlen($domainParts[0]) - 2));
        $ext = isset($domainParts[1]) ? $domainParts[1] : "com";
        return $userMasked . "@" . $domainName . "." . $ext;
    }
}

// Ensure insertNotificationAndReceipts helper exists (used later)
// PATCH: catch Throwable in helper
if (!function_exists('insertNotificationAndReceipts')) {
    function insertNotificationAndReceipts(PDO $db, $title, $body, array $user_codes = [], $url = null, $type = null, $icon = null, $sender = null, $meta = null) {
        try {
            $db->beginTransaction();

            $sql = "INSERT INTO notifications (title, body, url, type, icon, sender, meta, created_at)
                    VALUES (:title, :body, :url, :type, :icon, :sender, :meta, :created_at)";
            $stmt = $db->prepare($sql);

            // safe meta json (helper-level guard)
            $metaJson = null;
            if ($meta !== null) {
                $metaJson = @json_encode($meta, JSON_PARTIAL_OUTPUT_ON_ERROR);
                if ($metaJson === false) $metaJson = '{}';
            }

            $stmt->execute([
                ':title' => $title,
                ':body' => $body,
                ':url' => $url,
                ':type' => $type,
                ':icon' => $icon,
                ':sender' => $sender,
                ':meta' => $metaJson,
                ':created_at' => date('Y-m-d H:i:s')
            ]);
            $nid = $db->lastInsertId();

            if (!empty($user_codes)) {
                // adapt to your schema: this assumes is_read column exists
                $ins = $db->prepare("INSERT INTO notification_receipts (notification_id, user_code, is_read, created_at) VALUES (:nid, :uc, :is_read, :created_at)");
                $now = date('Y-m-d H:i:s');
                foreach ($user_codes as $uc) {
                    $ins->execute([
                        ':nid' => $nid,
                        ':uc' => trim($uc),
                        ':is_read' => 0,
                        ':created_at' => $now
                    ]);
                }
            }

            $db->commit();
            return $nid;
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            file_put_contents(__DIR__ . "/notify_db_error.log", date("Y-m-d H:i:s") . " | " . $e->getMessage() . PHP_EOL, FILE_APPEND);
            return false;
        }
    }
}

// Loop over tokens
for ($t = $progress['token_index']; $t < $totalTokens; $t++) {
    // PATCH: wrap the entire token iteration so a token-level fatal doesn't stop the loop
    try {
        $token = $tokens[$t];
        $page_access_token = $token['page_access_token'];
        $page_id = $token['page_id'];
        logmsg("Processing page ID: {$page_id} (token_idx={$t})");

        $forms_url = "https://graph.facebook.com/v15.0/{$page_id}/leadgen_forms?access_token={$page_access_token}&limit=100";
        $forms_result = fetch_url_curl($forms_url);
        if (!$forms_result['success']) {
            logmsg("Failed to fetch forms for page {$page_id}, skipping.");
            $progress = ['token_index' => $t + 1, 'form_index' => 0, 'next_url' => null, 'phase' => 'tokens'];
            save_progress($progressFile, $progress);
            continue;
        }
        $forms_data = json_decode($forms_result['body'], true);
        if (!isset($forms_data['data']) || !is_array($forms_data['data'])) {
            logmsg("No forms data for page {$page_id}.");
            $progress = ['token_index' => $t + 1, 'form_index' => 0, 'next_url' => null, 'phase' => 'tokens'];
            save_progress($progressFile, $progress);
            continue;
        }

        $forms = $forms_data['data'];
        $formsCount = count($forms);
        $startForm = ($progress['token_index'] === $t) ? (int)$progress['form_index'] : 0;

        for ($f = $startForm; $f < $formsCount; $f++) {
            $form = $forms[$f];
            $form_id = $form['id'];
            $form_name = $form['name'] ?? 'Unnamed Form';
            logmsg("  Processing form: {$form_name} ({$form_id}) (form_idx={$f})");

            $progress = ['token_index' => $t, 'form_index' => $f, 'phase' => 'forms', 'next_url' => null];
            save_progress($progressFile, $progress);

            $leads_url = "https://graph.facebook.com/v15.0/{$form_id}/leads?access_token={$page_access_token}&limit=100";
            $next_url = (!empty($progress['next_url'])) ? $progress['next_url'] : $leads_url;

            // Ensure project_apis row exists
            try {
                $check_stmt = $db->prepare("SELECT * FROM project_apis WHERE api_key = :api_key LIMIT 1");
                $check_stmt->execute(['api_key' => $form_id]);
                $project = $check_stmt->fetch();
                if (!$project) {
                    $insertApi = $db->prepare("INSERT INTO project_apis (project_name, api_key, lead_source, fb_form_leads) VALUES (?, ?, ?, 0)");
                    $insertApi->execute([$form_name, $form_id, 'facebook ads']);
                    $check_stmt->execute(['api_key' => $form_id]);
                    $project = $check_stmt->fetch();
                }
            } catch (Throwable $e) {
                // non-fatal: log and continue (project row creation not critical)
                file_put_contents(__DIR__ . "/fetch_fb_leads_errors.log", date("Y-m-d H:i:s") . " | PROJECT_API_ERR | form={$form_id} | " . $e->__toString() . PHP_EOL, FILE_APPEND);
            }

            while ($next_url) {
                $progress['next_url'] = $next_url;
                $progress['phase'] = 'paging';
                save_progress($progressFile, $progress);

                $leads_result = fetch_url_curl($next_url);
                if (!$leads_result['success']) {
                    logmsg("    Failed to fetch leads page. Will resume from saved next_url.");
                    break;
                }

                $leads_data = json_decode($leads_result['body'], true);
                if (!isset($leads_data['data']) || !is_array($leads_data['data'])) {
                    logmsg("    No data on leads page.");
                    $next_url = null;
                    $progress['next_url'] = null;
                    save_progress($progressFile, $progress);
                    break;
                }

                foreach ($leads_data['data'] as $lead) {
                    try {
                        // --- Round Robin Assignment ---
                        try {
                            $db->beginTransaction();
                            $projStmt = $db->prepare("SELECT assign_user, last_assigned_user_fb FROM project_apis WHERE api_key = :api_key FOR UPDATE");
                            $projStmt->execute(['api_key' => $form_id]);
                            $projectRow = $projStmt->fetch(PDO::FETCH_ASSOC);

                            $assign_users = [];
                            if ($projectRow && !empty($projectRow['assign_user'])) {
                                $assign_users = array_values(array_filter(array_map('trim', explode(',', $projectRow['assign_user']))));
                            }

                            if (empty($assign_users)) {
                                $next_user = null;
                            } else {
                                $last_assigned = $projectRow['last_assigned_user_fb'] ?? null;
                                $current_index = ($last_assigned !== null) ? array_search($last_assigned, $assign_users, true) : false;
                                $next_index = ($current_index === false) ? 0 : ($current_index + 1) % count($assign_users);
                                $next_user = $assign_users[$next_index];

                                $upd = $db->prepare("UPDATE project_apis SET last_assigned_user_fb = :u WHERE api_key = :api_key");
                                $upd->execute(['u' => $next_user, 'api_key' => $form_id]);
                            }
                            $db->commit();
                        } catch (Throwable $e) {
                            if ($db->inTransaction()) $db->rollBack();
                            $next_user = null;
                            logmsg("Round-robin assignment failed: " . $e->getMessage());
                            file_put_contents(__DIR__ . "/fetch_fb_leads_errors.log", date("Y-m-d H:i:s") . " | ROUND_ROBIN_ERR | " . $e->__toString() . PHP_EOL, FILE_APPEND);
                        }

                        if ($next_user) {
                            $userQuery = $db->prepare("SELECT phonenumber FROM accounts WHERE tablename = :tablename LIMIT 1");
                            $userQuery->execute(['tablename' => $next_user]);
                            $user = $userQuery->fetch();
                            if (!$user || empty($user['phonenumber'])) {
                                $next_user = null;
                            } else {
                                $user_number = $user['phonenumber'];
                            }
                        }

                        // Extract lead fields
                        $name = null; $email = null; $phone = null;
                        if (isset($lead['field_data']) && is_array($lead['field_data'])) {
                            foreach ($lead['field_data'] as $field) {
                                $normalized_name = strtolower(str_replace(['_', ' '], '', $field['name'] ?? ''));
                                $field_value = $field['values'][0] ?? null;
                                if (in_array($normalized_name, ['fullname','name','yourname']) && !$name) $name = $field_value;
                                elseif ($normalized_name === 'email' && !$email) $email = $field_value;
                                elseif (in_array($normalized_name, ['phonenumber','phone','phone_number']) && !$phone) $phone = $field_value;
                            }
                        }

                        // Duplicate check (safer: prefer fb id, then email, then phone)
                        $fb_lead_id = $lead['id'] ?? null;
                        $exists = false;
                        if ($fb_lead_id && $has_fb_lead_col) {
                            $dupQ = $db->prepare("SELECT id FROM shi_upload_data WHERE facebook_lead_id = :fbid LIMIT 1");
                            $dupQ->execute(['fbid' => $fb_lead_id]);
                            if ($dupQ->fetch()) $exists = true;
                        } else {
                            if (!empty($email)) {
                                $dupQ = $db->prepare("SELECT id FROM shi_upload_data WHERE email = :email AND project = :project LIMIT 1");
                                $dupQ->execute(['email' => $email, 'project' => $form_name]);
                                if ($dupQ->fetch()) $exists = true;
                            } elseif (!empty($phone)) {
                                $dupQ = $db->prepare("SELECT id FROM shi_upload_data WHERE number = :phone AND project = :project LIMIT 1");
                                $dupQ->execute(['phone' => $phone, 'project' => $form_name]);
                                if ($dupQ->fetch()) $exists = true;
                            }
                        }

                        $skip_emails = ['sidatt007@gmail.com','test@fb.com','usha.joshi2712@gmail.com','ayeshataskiya01@gmail.com','ayeshataskiya483@gmail.com', 'xhcucfvxhdiccoc@gmail.com'];
                        $skip_names = ['Roopa Pruthvi','salu singhfz'];
                        if (in_array($email, $skip_emails, true) || in_array($name, $skip_names, true)) continue;
                        if ($exists) continue;

                        // Normalize FB created_time
                        $fbCreated = null;
                        if (!empty($lead['created_time'])) {
                            try {
                                $dt = new DateTime($lead['created_time']);
                                $fbCreated = $dt->format('Y-m-d H:i:s');
                            } catch (Throwable $e) {
                                $fbCreated = $lead['created_time'];
                            }
                        }

                        // Insert lead
                        if ($has_fb_lead_col) {
                            $ins = $db->prepare("
                                INSERT INTO shi_upload_data (facebook_lead_id, page_id, name, email, number, fb_created_time, project, source_of_lead, location, assign_to_user, created_at)
                                VALUES (:fbid, :page_id, :name, :email, :number, :fb_created_time, :project, :source_of_lead, :location, :assign_to_user, :created_at)
                            ");
                            $ins->execute([
                                ':fbid' => $fb_lead_id,
                                ':page_id' => $page_id,
                                ':name' => $name,
                                ':email' => $email,
                                ':number' => $phone,
                                ':fb_created_time' => $fbCreated,
                                ':project' => $form_name,
                                ':source_of_lead' => 'facebook ads',
                                ':location' => 'NO Location Found',
                                ':assign_to_user' => $next_user,
                                ':created_at' => $created_at
                            ]);
                        } else {
                            $ins = $db->prepare("
                                INSERT INTO shi_upload_data (page_id, name, email, number, fb_created_time, project, source_of_lead, location, assign_to_user, created_at)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                            ");
                            $ins->execute([
                                $page_id, $name, $email, $phone, $fbCreated,
                                $form_name, 'facebook ads', 'NO Location Found', $next_user, $created_at
                            ]);
                        }
                        $last_insert_id = $db->lastInsertId();

                        if ($next_user && $last_insert_id) {
                            $stmt1 = $db->prepare("INSERT INTO user_remarks (upload_data_id, user_unique_id, assign_project_name, created_at) VALUES (?, ?, ?, ?)");
                            $stmt1->execute([$last_insert_id, $next_user, $form_name, $created_at]);
                        }

                        // ------------------- Safe Notification Block (drop-in) -------------------
                        $notifyResultSafe = [
                            'status' => 'skipped',
                            'http_code' => null,
                            'curl_error' => null,
                            'response_decoded' => null,
                            'response_raw' => null,
                            'payload_sent' => null,
                            'error_message' => null
                        ];

                        try {
                            // Prepare variables
                            $phone_for_mask = isset($phone) ? $phone : (isset($number) ? $number : '');
                            $email_for_mask = isset($email) ? $email : '';
                            $name_for_notify = isset($name) ? $name : 'No Name';
                            $projectName = isset($project['project_name']) ? $project['project_name'] : (isset($form_name) ? $form_name : 'Project');
                            $user_code = isset($next_user) ? $next_user : null;
                            $lead_id = isset($last_insert_id) ? $last_insert_id : null;
                            $page_id = isset($page_id) ? $page_id : null;

                            if (empty($user_code)) {
                                $notifyResultSafe['status'] = 'skipped_no_user';
                                $notifyResultSafe['error_message'] = 'No user_code (next_user) available to notify.';
                                file_put_contents(__DIR__ . "/notify_api.log", date("Y-m-d H:i:s") . " | SKIP: no user_code available" . PHP_EOL, FILE_APPEND);
                            } else {
                                $maskedNumber = maskNumber($phone_for_mask);
                                $maskedEmail  = maskEmail($email_for_mask);

                                $notifyPayload = [
                                    "title" => "📌 New Lead for {$projectName}",
                                    "body"  => "👤 {$name_for_notify}\n📞 {$maskedNumber}\n✉️ {$maskedEmail}\n\nPlease follow up promptly.",
                                    "user_codes" => [$user_code],
                                    "url" => "https://searchhomesindia.in",
                                    "meta" => [
                                        "lead_id" => $lead_id,
                                        "project" => $projectName,
                                        "page_id" => $page_id
                                    ]
                                ];

                                $notifyResultSafe['payload_sent'] = $notifyPayload;

                                if (!defined('CRM_API_KEY_SERVER') || empty(CRM_API_KEY_SERVER)) {
                                    $notifyResultSafe['status'] = 'skipped_no_key';
                                    $notifyResultSafe['error_message'] = 'CRM_API_KEY_SERVER not defined; skipping notify call.';
                                    file_put_contents(__DIR__ . "/notify_api.log", date("Y-m-d H:i:s") . " | SKIP: CRM_API_KEY_SERVER not defined" . PHP_EOL, FILE_APPEND);
                                } else {
                                    try {
                                        $notifyUrl = "https://notification.mnts.in/api/notify-users";
                                        $headers = [
                                            "Authorization: Bearer " . CRM_API_KEY_SERVER,
                                            "Content-Type: application/json"
                                        ];

                                        $ch = curl_init($notifyUrl);
                                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                        curl_setopt($ch, CURLOPT_POST, true);
                                        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                                        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($notifyPayload));
                                        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                                        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);

                                        $notifyResp = @curl_exec($ch);
                                        $notifyHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                                        $curlErrNo = curl_errno($ch);
                                        $curlErr = $curlErrNo ? curl_error($ch) : null;
                                        curl_close($ch);

                                        $decodedResp = null;
                                        if (is_string($notifyResp) && strlen($notifyResp) > 0) {
                                            $decodedResp = @json_decode($notifyResp, true);
                                            if ($decodedResp === null && json_last_error() !== JSON_ERROR_NONE) {
                                                $decodedResp = null;
                                            }
                                        }

                                        $successCodes = [200, 201, 202];
                                        $notifyResultSafe['http_code'] = $notifyHttp;
                                        $notifyResultSafe['curl_error'] = $curlErr;
                                        $notifyResultSafe['response_raw'] = $notifyResp;
                                        $notifyResultSafe['response_decoded'] = $decodedResp;

                                        if ($curlErrNo) {
                                            $notifyResultSafe['status'] = 'error_curl';
                                            $notifyResultSafe['error_message'] = 'cURL error: ' . $curlErr;
                                        } elseif (!in_array((int)$notifyHttp, $successCodes, true)) {
                                            $notifyResultSafe['status'] = 'error_http';
                                            $notifyResultSafe['error_message'] = 'Non-success HTTP code: ' . $notifyHttp;
                                        } else {
                                            $notifyResultSafe['status'] = 'success';
                                            $notifyResultSafe['error_message'] = null;
                                        }

                                        file_put_contents(__DIR__ . "/notify_api.log", date("Y-m-d H:i:s") . " | NOTIFY_RESULT | " . @json_encode($notifyResultSafe) . PHP_EOL, FILE_APPEND);
                                    } catch (Throwable $innerEx) {
                                        $notifyResultSafe['status'] = 'error_exception';
                                        $notifyResultSafe['error_message'] = 'Exception during notify call: ' . $innerEx->getMessage();
                                        file_put_contents(__DIR__ . "/notify_api.log", date("Y-m-d H:i:s") . " | NOTIFY_EXCEPTION | " . $innerEx->getMessage() . PHP_EOL, FILE_APPEND);
                                    }
                                }
                            }
                        } catch (Throwable $ex) {
                            $notifyResultSafe['status'] = 'error_unexpected';
                            $notifyResultSafe['error_message'] = 'Unexpected error: ' . $ex->getMessage();
                            file_put_contents(__DIR__ . "/notify_api.log", date("Y-m-d H:i:s") . " | UNEXPECTED_NOTIFY_ERROR | " . $ex->getMessage() . PHP_EOL, FILE_APPEND);
                        }

                        // Persist notification into notifications + receipts (non-fatal)
                        try {
                            // Only attempt DB insert if we have a usable PDO instance
                            $dbInsertStatus = 'skipped_no_db_or_user';
                            if (isset($db) && ($db instanceof PDO)) {
                                // derive user codes: prefer notifyPayload.user_codes, fallback to single $user_code
                                $user_codes = [];
                                if (!empty($notifyPayload['user_codes']) && is_array($notifyPayload['user_codes'])) {
                                    $user_codes = $notifyPayload['user_codes'];
                                } elseif (!empty($user_code)) {
                                    $user_codes = is_array($user_code) ? $user_code : [$user_code];
                                }

                                if (!empty($user_codes)) {
                                    // safe truncate of response_raw
                                    $rawResp = $notifyResultSafe['response_raw'] ?? null;
                                    $rawRespTrunc = null;
                                    if (is_string($rawResp)) {
                                        $rawRespTrunc = (strlen($rawResp) > 1000) ? substr($rawResp, 0, 1000) . '...' : $rawResp;
                                    } else {
                                        $rawRespTrunc = $rawResp;
                                    }

                                    $metaForDb = [
                                        'lead_id' => $lead_id ?? null,
                                        'project' => $projectName ?? null,
                                        'page_id' => $page_id ?? null,
                                        'notify_api_result' => [
                                            'status' => $notifyResultSafe['status'] ?? null,
                                            'http_code' => $notifyResultSafe['http_code'] ?? null,
                                            'curl_error' => $notifyResultSafe['curl_error'] ?? null,
                                            'response_raw' => $rawRespTrunc
                                        ]
                                    ];

                                    $title = $notifyPayload['title'] ?? "📌 New Lead for " . ($projectName ?? 'Project');
                                    $body  = $notifyPayload['body']  ?? ("👤 " . ($name_for_notify ?? 'No Name') . "\n📞 " . (isset($maskedNumber) ? $maskedNumber : '') . "\n✉️ " . (isset($maskedEmail) ? $maskedEmail : '') . "\n\nPlease follow up promptly.");
                                    $url   = $notifyPayload['url'] ?? "https://searchhomesindia.in";

                                    try {
                                        $nid = insertNotificationAndReceipts(
                                            $db,
                                            $title,
                                            $body,
                                            $user_codes,
                                            $url,
                                            'lead',     // type
                                            null,       // icon
                                            'system',   // sender
                                            $metaForDb
                                        );

                                        if ($nid === false) {
                                            $dbInsertStatus = 'failed';
                                            file_put_contents(__DIR__ . "/notify_db_error.log", date("Y-m-d H:i:s") . " | Failed to write notification for lead " . ($lead_id ?? 'unknown') . PHP_EOL, FILE_APPEND);
                                        } else {
                                            $dbInsertStatus = 'success';
                                            $notifyResultSafe['db_inserted_notification_id'] = $nid;

                                            // Optional: update unread count per user if helper exists
                                            if (function_exists('countUnreadNotifications')) {
                                                try {
                                                    $notifyResultSafe['unread_after_insert'] = [];
                                                    foreach ($user_codes as $uc) {
                                                        $notifyResultSafe['unread_after_insert'][trim($uc)] = countUnreadNotifications($db, trim($uc));
                                                    }
                                                } catch (Throwable $cntEx) {
                                                    file_put_contents(__DIR__ . "/notify_db_error.log", date("Y-m-d H:i:s") . " | UNREAD_COUNT_FAIL | " . $cntEx->getMessage() . PHP_EOL, FILE_APPEND);
                                                }
                                            }
                                        }
                                    } catch (Throwable $dbEx) {
                                        $dbInsertStatus = 'error_exception';
                                        file_put_contents(__DIR__ . "/notify_db_error.log", date("Y-m-d H:i:s") . " | NOTIFY_DB_EXCEPTION | " . $dbEx->getMessage() . PHP_EOL, FILE_APPEND);
                                        $notifyResultSafe['db_exception_message'] = $dbEx->getMessage();
                                    }
                                } else {
                                    $dbInsertStatus = 'skipped_no_user_codes';
                                    file_put_contents(__DIR__ . "/notify_api.log", date("Y-m-d H:i:s") . " | SKIP_DB_INSERT_NO_USERCODES | lead=" . ($lead_id ?? 'unknown') . PHP_EOL, FILE_APPEND);
                                }
                            } else {
                                file_put_contents(__DIR__ . "/notify_api.log", date("Y-m-d H:i:s") . " | SKIP_DB_INSERT_NO_DB | lead=" . ($lead_id ?? 'unknown') . PHP_EOL, FILE_APPEND);
                            }

                            $notifyResultSafe['db_insert_status'] = $dbInsertStatus;
                        } catch (Throwable $outerEx) {
                            file_put_contents(__DIR__ . "/notify_db_error.log", date("Y-m-d H:i:s") . " | UNEXPECTED_PERSIST_ERROR | " . $outerEx->getMessage() . PHP_EOL, FILE_APPEND);
                            $notifyResultSafe['db_insert_status'] = 'error_outer_exception';
                            $notifyResultSafe['db_insert_exception'] = $outerEx->getMessage();
                        }

                        // SMS notify (non-fatal)
                        if (!empty($user_number)) {
                            $shortProjectName = strlen($form_name) > 10 ? substr($form_name, 0, 10) : $form_name;
                            $shortName = strlen($name ?? '') > 7 ? substr($name ?? '', 0, 7) : ($name ?? '');
                            $shortEmail = strlen($email ?? '') > 8 ? substr($email ?? '', 0, 8) : ($email ?? '');
                            $fields = [
                                "sender_id" => "SHHOME",
                                "message" => "Property- $shortProjectName. Name- $shortName, Mobile-.XXXXX., Email- $shortEmail Regards, SearchHomes india pvt ltd",
                                "template_id" => "1207163731895114985",
                                "entity_id" => "1201159178483176795",
                                "route" => "dlt_manual",
                                "numbers" => $user_number,
                            ];
                            $ch = curl_init("https://www.fast2sms.com/dev/bulkV2");
                            curl_setopt_array($ch, [
                                CURLOPT_RETURNTRANSFER => true,
                                CURLOPT_CUSTOMREQUEST => "POST",
                                CURLOPT_POSTFIELDS => json_encode($fields),
                                CURLOPT_HTTPHEADER => [
                                    "authorization: RKnKg7po5EXg8lVwwYLYnZHFcoBBHEqWKfh4juLfSuuuZCCbPj4nFjzsSnGV",
                                    "content-type: application/json"
                                ],
                                CURLOPT_TIMEOUT => 10
                            ]);
                            $smsResp = @curl_exec($ch);
                            $smsErr = curl_error($ch);
                            $smsHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                            curl_close($ch);
                            if ($smsErr || ($smsHttp < 200 || $smsHttp >= 300)) {
                                file_put_contents(__DIR__ . "/sms_notify.log", date('Y-m-d H:i:s') . " | SMS_FAIL | http={$smsHttp} err={$smsErr} resp=" . substr($smsResp ?? '',0,1000) . PHP_EOL, FILE_APPEND);
                            }
                        }

                        logmsg("      Lead inserted (fb_id: {$fb_lead_id}, assigned: {$next_user}).");
                    } catch (Throwable $e) {
                        // PATCH: catch Throwable so no fatal stops loop
                        logmsg("      Error while inserting lead: " . $e->getMessage());
                        file_put_contents(__DIR__ . "/fetch_fb_leads_errors.log", date("Y-m-d H:i:s") . " | LEAD_PROC_ERR | " . $e->__toString() . PHP_EOL, FILE_APPEND);
                        continue;
                    }
                }

                $next_url = $leads_data['paging']['next'] ?? null;
                $progress['next_url'] = $next_url;
                save_progress($progressFile, $progress);
                sleep(1);
            }

            $progress['next_url'] = null;
            $progress['form_index'] = $f + 1;
            $progress['phase'] = 'forms';
            save_progress($progressFile, $progress);

            try {
                $countQ = $db->prepare("SELECT COUNT(*) as cnt FROM shi_upload_data WHERE project = :project");
                $countQ->execute(['project' => $form_name]);
                $cnt = $countQ->fetchColumn();
                $updCount = $db->prepare("UPDATE project_apis SET fb_form_leads = :cnt WHERE api_key = :api_key");
                $updCount->execute(['cnt' => $cnt, 'api_key' => $form_id]);
            } catch (Throwable $e) {
                file_put_contents(__DIR__ . "/fetch_fb_leads_errors.log", date("Y-m-d H:i:s") . " | UPDATE_FB_COUNT_ERR | " . $e->__toString() . PHP_EOL, FILE_APPEND);
            }
        }

        $progress = ['token_index' => $t + 1, 'form_index' => 0, 'next_url' => null, 'phase' => 'tokens'];
        save_progress($progressFile, $progress);

        $processed++;
        sleep(1);

        // PATCH: use meta-refresh because headers may already be sent by logmsg()
        if ($processed >= $batchSize && ($t + 1) < $totalTokens) {
            logmsg("Restarting script for remaining tokens...");
            echo '<meta http-equiv="refresh" content="2;url=' . htmlspecialchars($_SERVER['PHP_SELF']) . '">';
            exit;
        }
    } catch (Throwable $tokenLevelErr) {
        // Log token-level fatal and continue with next token
        file_put_contents(__DIR__ . "/fetch_fb_leads_errors.log", date("Y-m-d H:i:s") . " | TOKEN_LOOP_ERR | token_idx={$t} | " . $tokenLevelErr->__toString() . PHP_EOL, FILE_APPEND);
        $progress = ['token_index' => $t + 1, 'form_index' => 0, 'next_url' => null, 'phase' => 'tokens'];
        save_progress($progressFile, $progress);
        continue;
    }
}

if (file_exists($progressFile)) unlink($progressFile);
logmsg("Leads synced successfully.");
?>
