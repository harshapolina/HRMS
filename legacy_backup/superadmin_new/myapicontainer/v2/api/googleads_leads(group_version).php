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

    $lockStmt = $db->prepare(
        "SELECT id, assign_user, group_id, last_assigned_user, project_name, lead_source 
         FROM project_apis WHERE api_key = :api_key LIMIT 1 FOR UPDATE"
    );
    $lockStmt->execute([':api_key' => $api_key]);
    $lockedProject = $lockStmt->fetch(PDO::FETCH_ASSOC);

    if (!$lockedProject) {
        // Shouldn't happen (we fetched earlier) — keep going but mark debug
        $debug_info['lock_error'] = "Project row missing at lock-time.";
        // do not exit — we will fallback to safe insertion below
    }

    // Build assign_users list (prefer users from group rows if any)
    $assign_users = [];
    $assign_users_raw = $lockedProject['assign_user'] ?? ($project['assign_user'] ?? '');

    // Interpret group_id stored on project row: may be CSV of group row IDs or single int
    $groupIds = [];
    $rawGroupId = $lockedProject['group_id'] ?? null;
    if ($rawGroupId !== null && $rawGroupId !== '') {
        $rawGroupIdStr = (string)$rawGroupId;
        if (ctype_digit($rawGroupIdStr)) {
            $groupIds = [(int)$rawGroupIdStr];
        } else {
            $groupIds = array_filter(array_map('trim', explode(',', $rawGroupIdStr)), 'strlen');
            $groupIds = array_map('intval', $groupIds);
            $groupIds = array_values(array_unique($groupIds));
        }
    }

    if (!empty($groupIds)) {
        // fetch assign_user fields from those group rows
        $placeholders = [];
        $binds = [];
        foreach ($groupIds as $i => $gid) {
            $ph = ':gid' . $i;
            $placeholders[] = $ph;
            $binds[$ph] = (int)$gid;
        }
        $inG = implode(',', $placeholders);
        $gsql = "SELECT assign_user FROM project_apis WHERE id IN ($inG) AND type = 'group'";
        $gstmt = $db->prepare($gsql);
        $gstmt->execute($binds);
        $groupRows = $gstmt->fetchAll(PDO::FETCH_ASSOC);

        $collected = [];
        foreach ($groupRows as $gr) {
            $au = trim((string)$gr['assign_user']);
            if ($au !== '') {
                $parts = array_map('trim', explode(',', $au));
                foreach ($parts as $p) if ($p !== '') $collected[] = $p;
            }
        }
        $assign_users = array_values(array_unique($collected));
        $debug_info['group_users_collected'] = $assign_users;
    }

    // Fallback to project assign_user if nothing collected
    if (empty($assign_users)) {
        $assign_users = array_filter(array_map('trim', explode(',', (string)$assign_users_raw)), fn($v) => $v !== '');
        $assign_users = array_values($assign_users);
        $debug_info['fallback_to_project_users'] = $assign_users;
    }

    // Round-robin selection using last_assigned_user from locked row
    $last_assigned_user = $lockedProject['last_assigned_user'] ?? null;
    $current_user_index = ($last_assigned_user !== null) ? array_search($last_assigned_user, $assign_users, true) : false;
    $totalAssignUsers = count($assign_users);
    $next_user_index = ($current_user_index === false || $current_user_index === $totalAssignUsers - 1) ? 0 : $current_user_index + 1;

    $tried_users = [];
    $skipped_users = [];

    if ($totalAssignUsers > 0) {
        for ($i = 0; $i < $totalAssignUsers; $i++) {
            $idx = ($next_user_index + $i) % $totalAssignUsers;
            $candidate = $assign_users[$idx];
            $tried_users[] = $candidate;

            $uStmt = $db->prepare("SELECT phonenumber FROM accounts WHERE tablename = :tablename LIMIT 1");
            $uStmt->execute([':tablename' => $candidate]);
            $candidateRow = $uStmt->fetch(PDO::FETCH_ASSOC);

            if (!$candidateRow || empty(trim($candidateRow['phonenumber'] ?? ''))) {
                $skipped_users[] = $candidate;
                continue;
            }

            // chosen
            $assigned_user = $candidate;
            $user_number = trim($candidateRow['phonenumber']);
            $fetched_user_row = $candidateRow;
            $next_user_index = $idx;
            break;
        }
    }

    $debug_info['group_ids'] = $groupIds ?? [];
    $debug_info['assign_users_final'] = $assign_users;
    $debug_info['last_assigned_user'] = $last_assigned_user;
    $debug_info['tried_users'] = $tried_users;
    $debug_info['skipped_users'] = $skipped_users;

    // Do NOT abort insertion if assigned_user wasn't chosen.
    if (!$assigned_user) {
        // fallback mode — guarantee insertion by marking 'unassigned'
        $assigned_user = "unassigned";
        $user_number = null;
        $debug_info['assigned_user_fallback'] = "No valid assigned user with phone available; lead will be stored as unassigned.";
        // We intentionally DO NOT rollBack here; we want to continue and insert the lead
    }

    // At this point we have $assigned_user (either real or 'unassigned').
    // Do NOT commit yet; we will commit after insert + updating last_assigned_user (if applicable).

} catch (Exception $e) {
    // If anything went wrong in selection, ensure we set fallback assigned_user and continue
    if ($db->inTransaction()) {
        // keep transaction open if possible — but safe fallback: rollback to avoid lock issues
        try { $db->rollBack(); } catch (Exception $ex) {}
    }
    $assigned_user = $assigned_user ?? "unassigned";
    $user_number = $user_number ?? null;
    $debug_info['round_robin_exception'] = $e->getMessage();
    $debug_info['assigned_user_fallback'] = "Round-robin exception; storing lead as unassigned and continuing.";
    // continue to insertion below (do not exit)
}

// ---------- Insert lead (guaranteed) ----------
$normalized_assigned_user_number = $user_number ? normalizePhoneNumber($user_number) : null;

try {
    // If a transaction is still open (we left it open during successful lock flow), keep it.
    // If it was rolled back earlier due to exception, start a fresh transaction for insertion.
    if (!$db->inTransaction()) $db->beginTransaction();

    // Duplicate check
    $checkDuplicateQuery = "SELECT id FROM shi_upload_data WHERE number = :number AND project = :project";
    $checkStmt = $db->prepare($checkDuplicateQuery);
    $checkStmt->execute(['number' => $number, 'project' => $project_name]);

    if ($checkStmt->rowCount() > 0) {
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
        $updateCountQuery = "UPDATE shi_upload_data SET lead_count = lead_count + 1, updated_at = :updated_at WHERE id = :id";
        $updateStmt = $db->prepare($updateCountQuery);
        $updateStmt->execute(['updated_at' => $created_at, 'id' => $existing['id']]);

        $shouldNotify = false;
        // commit and return duplicate response
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

    // If we had a real assigned_user (not 'unassigned') and we earlier had the project row locked,
    // update last_assigned_user on project_apis so round-robin advances.
    if ($assigned_user !== "unassigned") {
        $updateQuery = "UPDATE project_apis SET last_assigned_user = :last_assigned_user WHERE api_key = :api_key";
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->execute([
            'last_assigned_user' => $assigned_user,
            'api_key' => $api_key
        ]);
    }

    // Update project lead count (safe, using project_name + lead_source)
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

    // commit the insertion transaction (this releases the lock if one was held)
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
            ':created_at' => date('Y-m-d H:i:s')
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
