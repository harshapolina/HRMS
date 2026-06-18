<?php
// google_lead.php - robust lead ingestion with group-aware round-robin + guaranteed insertion

include '../../config.php';
header('Content-Type: application/json; charset=utf-8');

$config = new Config();
$db = $config->getConnection();

ini_set('max_execution_time', 0);
set_time_limit(0);

$CRM_API_KEY_SERVER = "533f4175837e145064605e15e12c7273f98746fec7459b0168af9394a22c6efab6bba75cce18a3555250e473f4907d22aaae3e3f12e46dd8ef22fac38737c537"; // keep original
$created_at = (new DateTime('now', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d H:i:s');

// Test DB connection
try {
    $db->query("SELECT 1");
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database connection failed.", "error" => $e->getMessage()]);
    exit;
}

// ---------- BLOCKED IPS CONFIG ----------
$blocked_ips = [
    '124.123.2.134',
    '171.79.54.195'
];

function ipMatches($ip, $pattern) {
    $ip = trim($ip);
    $pattern = trim($pattern);
    if (strpos($pattern, '/') !== false) {
        list($subnet, $mask) = explode('/', $pattern, 2);
        $ip_long = ip2long($ip);
        $subnet_long = ip2long($subnet);
        if ($ip_long === false || $subnet_long === false) return false;
        $mask = (int)$mask;
        $mask_long = ($mask === 0) ? 0 : (~0 << (32 - $mask)) & 0xFFFFFFFF;
        return ($ip_long & $mask_long) === ($subnet_long & $mask_long);
    }
    if (strpos($pattern, '*') !== false) {
        $regex = '^' . str_replace('\*', '\d{1,3}', preg_quote($pattern, '/')) . '$';
        return preg_match("/$regex/", $ip) === 1;
    }
    return $ip === $pattern;
}

function normalizePhoneNumber($rawNumber) {
    $number = preg_replace('/\D/', '', (string)$rawNumber);
    if (preg_match('/^(?:91|0)?(\d{10})$/', $number, $matches)) {
        return $matches[1];
    }
    return $number;
}

function maskNumber($number) {
    $len = strlen($number);
    if ($len <= 3) return str_repeat("x", $len);
    return str_repeat("x", $len - 3) . substr($number, -3);
}
function maskEmail($email) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return "xxx@xxx.com";
    [$userPart, $domain] = explode("@", $email);
    $userMasked = substr($userPart, 0, 3) . str_repeat("x", max(0, strlen($userPart) - 3));
    $domainParts = explode(".", $domain);
    $domainName = substr($domainParts[0], 0, 2) . str_repeat("x", max(0, strlen($domainParts[0]) - 2));
    $ext = isset($domainParts[1]) ? $domainParts[1] : "com";
    return $userMasked . "@" . $domainName . "." . $ext;
}

// Only POST accepted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Invalid request method. Only POST is allowed."]);
    exit;
}

$rawJson = file_get_contents("php://input");
$data = json_decode($rawJson, true);
if (!$data) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid or empty JSON payload."]);
    exit;
}

$shouldNotify = true;
$headers = function_exists('getallheaders') ? getallheaders() : [];
$api_key = $headers['API-Key'] ?? $headers['api-key'] ?? $headers['HTTP_API_KEY'] ?? '';

if (!$api_key) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "API Key missing in headers"]);
    exit;
}

// Fetch project row by API key
try {
    $stmt = $db->prepare("SELECT * FROM project_apis WHERE api_key = :api_key LIMIT 1");
    $stmt->execute(['api_key' => $api_key]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Failed to fetch project.", "error" => $e->getMessage()]);
    exit;
}
if (!$project) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Invalid API key"]);
    exit;
}

// Parse payload
$name = strtolower(trim(preg_replace('/\s+/', ' ', $data['name'] ?? '')));
$email = strtolower(trim($data['email'] ?? ''));
$raw_number = $data['number'] ?? '';
$number = normalizePhoneNumber($raw_number);
$location = trim($data['location'] ?? '');
$project_name = $project['project_name'] ?? '';
$subsource_of_lead = strtolower(trim(preg_replace('/\s+/', ' ', $data['subsource_of_lead'] ?? '')));

// Blocked IP check (if location contains IP)
if (!empty($location)) {
    foreach ($blocked_ips as $pattern) {
        if (ipMatches($location, $pattern)) {
            $shouldNotify = false;
            http_response_code(200);
            echo json_encode([
                "status" => "blocked",
                "message" => "Lead from blocked IP not inserted.",
                "blocked_ip" => $location,
                "matched_pattern" => $pattern
            ]);
            exit;
        }
    }
}

if (!$number) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid phone number"]);
    exit;
}

// ---------- GROUP-AWARE ROUND-ROBIN (atomic and safe) ----------
$assigned_user = null;
$user_number = null;
$fetched_user_row = null;
$debug_info = [];

try {
    // Start transaction and lock the project row so concurrent requests don't pick the same user
    $db->beginTransaction();

    // Lock the project row to get group_id and assign_user
    $lockStmt = $db->prepare(
        "SELECT id, assign_user, group_id, last_assigned_user, project_name, lead_source 
         FROM project_apis WHERE api_key = :api_key LIMIT 1 FOR UPDATE"
    );
    $lockStmt->execute([':api_key' => $api_key]);
    $lockedProject = $lockStmt->fetch(PDO::FETCH_ASSOC);

    if (!$lockedProject) {
        // Shouldn't happen (we fetched earlier) — use the earlier fetched project data
        $debug_info['lock_error'] = "Project row missing at lock-time.";
        $lockedProject = $project;
    }

    // Initialize variables
    $assign_users = [];
    $groupToUpdateId = null;
    $chosenGroupLastAssigned = null;

    // Step 1: Parse group_id (if any) - supports single int or CSV
    $groupIds = [];
    $rawGroupId = $lockedProject['group_id'] ?? null;
    if ($rawGroupId !== null && trim((string)$rawGroupId) !== '') {
        $raw = trim((string)$rawGroupId);
        if (ctype_digit($raw)) {
            $groupIds = [(int)$raw];
        } else {
            $parts = array_filter(array_map('trim', explode(',', $raw)), 'strlen');
            $groupIds = array_map('intval', $parts);
            $groupIds = array_values(array_unique($groupIds));
        }
    }

    // Step 2: If group exists, lock group rows and collect users from group's assign_user
    // NOTE: When a group is assigned, both Facebook and Google APIs share the same round-robin
    // rotation by using the group's last_assigned_user field. The FOR UPDATE lock ensures
    // that concurrent requests (Facebook cron + Google real-time) properly serialize access.
    if (!empty($groupIds)) {
        sort($groupIds, SORT_NUMERIC);
        $groupToUpdateId = $groupIds[0]; // deterministic owner of pointer

        // Build placeholders for IN clause
        $placeholders = [];
        $binds = [];
        foreach ($groupIds as $i => $gid) {
            $ph = ':gid' . $i;
            $placeholders[] = $ph;
            $binds[$ph] = (int)$gid;
        }
        $inG = implode(',', $placeholders);

        // Lock and read group rows in one shot (WITH FOR UPDATE to prevent race conditions)
        // This lock ensures that if Facebook API is processing leads simultaneously (every 10 min cron),
        // it will wait for this transaction to complete, ensuring proper round-robin
        $gsql = "SELECT id, assign_user, last_assigned_user FROM project_apis WHERE id IN ($inG) AND type = 'group' FOR UPDATE";
        $gstmt = $db->prepare($gsql);
        $gstmt->execute($binds);
        $groupRows = $gstmt->fetchAll(PDO::FETCH_ASSOC);

        // Collect users from all group rows
        $collected = [];
        foreach ($groupRows as $gr) {
            $au = trim((string)$gr['assign_user']);
            if ($au !== '') {
                $parts = array_map('trim', explode(',', $au));
                foreach ($parts as $p) {
                    if ($p !== '') $collected[] = $p;
                }
            }
            // Get the last_assigned_user from the chosen group (pointer owner)
            // This pointer is shared with Facebook API when both use the same group
            if ((int)$gr['id'] === (int)$groupToUpdateId) {
                $chosenGroupLastAssigned = $gr['last_assigned_user'] ?? null;
            }
        }
        if (!empty($collected)) {
            $assign_users = array_values(array_unique($collected));
            $debug_info['group_users_collected'] = $assign_users;
            $debug_info['chosen_group_for_pointer'] = $groupToUpdateId;
            $debug_info['chosen_group_last_assigned'] = $chosenGroupLastAssigned;
        } else {
            // groups exist but no assign_user found on them — we'll fallback below
            $debug_info['group_rows_empty_assign_user'] = true;
        }
    }

    // Step 3: Fallback to project row assign_user if no group or group has no users
    // NOTE: When no group is assigned, Google API works independently using its own
    // project row's assign_user and last_assigned_user pointer (separate from Facebook)
    if (empty($assign_users)) {
        $assign_users_raw = $lockedProject['assign_user'] ?? ($project['assign_user'] ?? '');
        $assign_users = array_filter(array_map('trim', explode(',', (string)$assign_users_raw)), fn($v) => $v !== '');
        $assign_users = array_values($assign_users);
        $debug_info['fallback_to_project_users'] = $assign_users;
    }

    // Step 4: Choose pointer source based on whether group is used
    // If group exists: use group's last_assigned_user (shared with Facebook)
    // If no group: use project's last_assigned_user (Google-specific, independent from Facebook)
    $last_assigned_user = null;
    if (!empty($groupToUpdateId)) {
        // Group mode: use group's last_assigned_user pointer (shared rotation with Facebook)
        $last_assigned_user = $chosenGroupLastAssigned ?? null;
    } else {
        // No group: use project's last_assigned_user pointer (Google-only rotation)
        // This ensures Google and Facebook work independently when no group is assigned
        $last_assigned_user = $lockedProject['last_assigned_user'] ?? null;
    }

    // Step 5: Round-robin selection - compute next index and iterate to find first candidate with phone
    if (!empty($assign_users)) {
        $current_user_index = ($last_assigned_user !== null) ? array_search($last_assigned_user, $assign_users, true) : false;
        $totalAssignUsers = count($assign_users);
        $next_user_index = ($current_user_index === false || $current_user_index === $totalAssignUsers - 1) ? 0 : $current_user_index + 1;

        $tried_users = [];
        $skipped_users = [];

        // Iterate through users to find first one with phone number
        for ($i = 0; $i < $totalAssignUsers; $i++) {
            $idx = ($next_user_index + $i) % $totalAssignUsers;
            $candidate = $assign_users[$idx];
            $tried_users[] = $candidate;

            // Check if candidate has phone number
            $uStmt = $db->prepare("SELECT phonenumber FROM accounts WHERE LOWER(tablename) = LOWER(:tablename) LIMIT 1");
            $uStmt->execute([':tablename' => $candidate]);
            $candidateRow = $uStmt->fetch(PDO::FETCH_ASSOC);

            if (!$candidateRow || empty(trim($candidateRow['phonenumber'] ?? ''))) {
                $skipped_users[] = $candidate;
                continue;
            }

            // Found valid candidate with phone
            $assigned_user = $candidate;
            $user_number = trim($candidateRow['phonenumber']);
            $fetched_user_row = $candidateRow;
            $next_user_index = $idx;
            break;
        }

        $debug_info['tried_users'] = $tried_users;
        $debug_info['skipped_users'] = $skipped_users;
    }

    // Debug info
    $debug_info['group_ids'] = $groupIds ?? [];
    $debug_info['assign_users_final'] = $assign_users;
    $debug_info['last_assigned_user_used_for_rotation'] = $last_assigned_user;
    $debug_info['group_pointer_owner'] = $groupToUpdateId;

    // Fallback: no candidate with phone
    if (!$assigned_user) {
        $assigned_user = "unassigned";
        $user_number = null;
        $debug_info['assigned_user_fallback'] = "No valid assigned user with phone available; lead will be stored as unassigned.";
        // don't roll back, we want the insert to happen
    }

    // NOTE: we intentionally keep the transaction open here so we can insert and then update pointer atomically
    // Do NOT commit yet.

} catch (Exception $e) {
    // Something failed during selection — rollback to avoid locks and continue with fallback insertion
    if ($db->inTransaction()) {
        try { $db->rollBack(); } catch (Exception $ex) {}
    }
    $assigned_user = $assigned_user ?? "unassigned";
    $user_number = $user_number ?? null;
    $debug_info['round_robin_exception'] = $e->getMessage();
    $debug_info['assigned_user_fallback'] = "Round-robin exception; storing lead as unassigned and continuing.";
    // We will insert without the selection lock below
}

// ---------- Insert lead (guaranteed) ----------
$normalized_assigned_user_number = $user_number ? normalizePhoneNumber($user_number) : null;

try {
    // If a transaction is still open from the selection phase, keep it.
    // Otherwise open a fresh transaction for insertion (we will not be able to atomically update pointer in that case).
    $weHaveSelectionTransaction = $db->inTransaction();
    if (!$weHaveSelectionTransaction) $db->beginTransaction();

    // Duplicate check (by number + project)
    $checkDuplicateQuery = "SELECT id FROM shi_upload_data WHERE number = :number AND project = :project";
    $checkStmt = $db->prepare($checkDuplicateQuery);
    $checkStmt->execute(['number' => $number, 'project' => $project_name]);

    if ($checkStmt->rowCount() > 0) {
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
        $updateCountQuery = "UPDATE shi_upload_data SET lead_count = lead_count + 1, updated_at = :updated_at WHERE id = :id";
        $updateStmt = $db->prepare($updateCountQuery);
        $updateStmt->execute(['updated_at' => $created_at, 'id' => $existing['id']]);

        $shouldNotify = false;
        if ($db->inTransaction()) $db->commit();
        echo json_encode(["status" => "duplicate", "message" => "Duplicate lead — lead_count incremented.", "debug_info" => $debug_info]);
        exit;
    }

    // Insert new lead using assigned_user (may be 'unassigned')
    $insertQuery1 = "INSERT INTO shi_upload_data (name, email, number, location, type, source_of_lead, assign_to_user, created_at, project, lead_count, subsource_of_lead)
                     VALUES (:name, :email, :number, :location, '3 BHK', :source_of_lead, :assign_to_user, :created_at, :project, 1, :subsource_of_lead)";
    $stmt1 = $db->prepare($insertQuery1);
    $stmt1->execute([
        'name' => $name,
        'email' => $email,
        'number' => $number,
        'location' => $location,
        'source_of_lead' => $project['lead_source'],
        'assign_to_user' => $assigned_user,
        'created_at' => $created_at,
        'project' => $project_name,
        'subsource_of_lead' => $subsource_of_lead
    ]);

    $id_of_shi_upload_data = $db->lastInsertId();

    // Insert user remarks (still helpful even if unassigned)
    $insertQuery2 = "INSERT INTO user_remarks (upload_data_id, user_unique_id, assign_project_name, created_at)
                     VALUES (:id_of_shi_upload_data, :assign_to_user, :project_name, :created_at)";
    $stmt2 = $db->prepare($insertQuery2);
    $stmt2->execute([
        'id_of_shi_upload_data' => $id_of_shi_upload_data,
        'assign_to_user' => $assigned_user,
        'project_name' => $project_name,
        'created_at' => $created_at
    ]);

    // Step 6: Persist pointer update ONLY AFTER successful lead insertion
    // This ensures round-robin pointer is only updated when lead is actually saved to database
    // If pointer update fails, the transaction will rollback (including lead insertion) to maintain data integrity
    if ($assigned_user !== "unassigned") {
        if (!empty($groupToUpdateId)) {
            // Group mode: update group's last_assigned_user (we already locked these group rows above with FOR UPDATE)
            // This update is atomic and shared with Facebook API - both APIs will continue rotation from this point
            $upd = $db->prepare("UPDATE project_apis SET last_assigned_user = :last_assigned_user WHERE id = :gid AND type = 'group'");
            $upd->execute([
                'last_assigned_user' => $assigned_user,
                'gid' => $groupToUpdateId
            ]);
            $debug_info['pointer_updated_on_group'] = $groupToUpdateId;
        } else {
            // No group: update project's last_assigned_user pointer (Google-specific)
            // This pointer is independent from Facebook API - each API maintains its own rotation
            $upd = $db->prepare("UPDATE project_apis SET last_assigned_user = :last_assigned_user WHERE api_key = :api_key");
            $upd->execute([
                'last_assigned_user' => $assigned_user,
                'api_key' => $api_key
            ]);
            $debug_info['pointer_updated_on_project'] = $api_key;
        }
    }

    // Update project lead count (safe)
    $leadCountQuery = "SELECT COUNT(*) AS lead_count FROM shi_upload_data WHERE project = :project_name AND source_of_lead = :source_of_lead";
    $leadCountStmt = $db->prepare($leadCountQuery);
    $leadCountStmt->execute(['project_name' => $project_name, 'source_of_lead' => $project['lead_source']]);
    $leadCount = $leadCountStmt->fetchColumn();

    $updateCountQuery = "UPDATE project_apis SET fb_form_leads = :lead_count WHERE project_name = :project_name AND lead_source = :source_of_lead";
    $updateCountStmt = $db->prepare($updateCountQuery);
    $updateCountStmt->execute([
        'lead_count' => $leadCount,
        'project_name' => $project_name,
        'source_of_lead' => $project['lead_source']
    ]);

    // Commit the transaction (releases lock if one was held)
    if ($db->inTransaction()) $db->commit();

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    http_response_code(500);
    file_put_contents("lead_insert_error.log", date("Y-m-d H:i:s") . " | " . $e->getMessage() . " | payload: " . json_encode($data) . PHP_EOL, FILE_APPEND);
    echo json_encode(["status" => "error", "message" => "Failed to insert lead.", "error" => $e->getMessage(), "debug_info" => $debug_info]);
    exit;
}

// ---------- Notifications (run separately; failure here must not affect lead insertion) ----------
$notifyResult = null;
$fast2smsResult = null;
if ($shouldNotify && $assigned_user !== "unassigned") {
    try {
        $maskedNumber = maskNumber($number);
        $maskedEmail = maskEmail($email);
        $name_project = $project_name;

        $notifyPayload = [
            "title" => "📌 New Lead for {$name_project}",
            "body"  => "👤 {$name}\n📞 {$maskedNumber}\n✉️ {$maskedEmail}\n\nPlease follow up promptly.",
            "user_codes" => [$assigned_user],
            "url" => "https://searchhomesindia.in"
        ];

        $ch = curl_init("https://notification.mnts.in/api/notify-users");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $CRM_API_KEY_SERVER",
            "Content-Type: application/json"
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($notifyPayload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $notifyResp = curl_exec($ch);
        $notifyHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            $notifyResult = ["status" => "error", "message" => curl_error($ch)];
        } else {
            $successCodes = [200,201,202];
            $notifyResult = ["status" => in_array($notifyHttp, $successCodes) ? "success" : "error", "http_code" => $notifyHttp, "response" => json_decode($notifyResp, true)];
        }
        curl_close($ch);
    } catch (Exception $e) {
        $notifyResult = ["status" => "error", "message" => $e->getMessage()];
        file_put_contents("notify_error.log", date("Y-m-d H:i:s") . " | " . $e->getMessage() . PHP_EOL, FILE_APPEND);
    }

    // store notification + receipts (best-effort)
    try {
        $user_codes = $notifyPayload['user_codes'] ?? [];
        if (!is_array($user_codes)) $user_codes = [$user_codes];

        $meta = ['lead_id' => $id_of_shi_upload_data ?? null, 'project' => $name_project, 'source' => $project['lead_source'] ?? null, 'notify_api_result' => $notifyResult];

        // Use a separate transaction for notifications
        $db->beginTransaction();
        $sql = "INSERT INTO notifications (title, body, url, type, icon, sender, meta, created_at) VALUES (:title, :body, :url, :type, :icon, :sender, :meta, :created_at)";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':title' => $notifyPayload['title'],
            ':body' => $notifyPayload['body'],
            ':url' => $notifyPayload['url'] ?? null,
            ':type' => 'lead',
            ':icon' => null,
            ':sender' => 'system',
            ':meta' => json_encode($meta),
            ':created_at' => date("Y-m-d H:i:s")
        ]);
        $nid = $db->lastInsertId();
        if (!empty($user_codes)) {
            $ins = $db->prepare("INSERT INTO notification_receipts (notification_id, user_code, created_at) VALUES (:nid, :uc, :created_at)");
            $now = date('Y-m-d H:i:s');
            foreach ($user_codes as $uc) $ins->execute([':nid' => $nid, ':uc' => trim($uc), ':created_at' => $now]);
        }
        $db->commit();
        $notifyResult['db_inserted_notification_id'] = $nid;
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        file_put_contents("notify_db_error.log", date("Y-m-d H:i:s") . " | " . $e->getMessage() . PHP_EOL, FILE_APPEND);
    }

    // Fast2SMS (best-effort)
    try {
        if (!empty($normalized_assigned_user_number)) {
            $shortProjectName = strlen($project_name) > 10 ? substr($project_name, 0, 10) : $project_name;
            $shortName = strlen($name) > 7 ? substr($name, 0, 7) : $name;
            $shortEmail = strlen($email) > 7 ? substr($email, 0, 8) : $email;

            $fields = [
                "sender_id" => "SHHOME",
                "message" => "Property- $shortProjectName. Name- $shortName, Mobile-.XXXXX., Email- $shortEmail Regards, SearchHomes india pvt ltd",
                "template_id" => "1207163731895114985",
                "entity_id" => "1201159178483176795",
                "route" => "dlt_manual",
                "numbers" => $normalized_assigned_user_number,
            ];

            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => "https://www.fast2sms.com/dev/bulkV2",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => json_encode($fields),
                CURLOPT_HTTPHEADER => [
                    "authorization: RKnKg7po5EXg8lVwwYLYnZHFcoBBHEqWKfh4juLfSuuuZCCbPj4nFjzsSnGV",
                    "accept: */*",
                    "cache-control: no-cache",
                    "content-type: application/json"
                ],
                CURLOPT_TIMEOUT => 10
            ]);

            $response = curl_exec($curl);
            $err = curl_error($curl);
            $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            if ($err) {
                $fast2smsResult = ["status" => "error", "message" => $err];
                file_put_contents("fast2sms_error.log", date("Y-m-d H:i:s") . " | ERROR | " . $err . " | payload: " . json_encode($fields) . PHP_EOL, FILE_APPEND);
            } else {
                $fast2smsResult = ["status" => "success", "http_code" => $httpcode, "response" => $response];
                file_put_contents("fast2sms.log", date("Y-m-d H:i:s") . " | RESPONSE | " . $response . " | payload: " . json_encode($fields) . PHP_EOL, FILE_APPEND);
            }
        }
    } catch (Exception $e) {
        $fast2smsResult = ["status" => "error", "message" => $e->getMessage()];
        file_put_contents("fast2sms_error.log", date("Y-m-d H:i:s") . " | EXCEPTION | " . $e->getMessage() . PHP_EOL, FILE_APPEND);
    }
}

// Final response
http_response_code(200);
echo json_encode([
    "status" => "success",
    "message" => "Lead successfully inserted.",
    "lead_id" => $id_of_shi_upload_data ?? null,
    "assigned_user" => $assigned_user,
    "notify" => $notifyResult,
    "sms" => $fast2smsResult,
    "debug_info" => $debug_info
]);
exit;
?>