<?php
/**
 * ============================================================================
 * LEADS PAGE - BACKEND API ENDPOINT
 * ============================================================================
 * 
 * ORGANIZATION STRUCTURE:
 * 
 * 1. CONFIGURATION & INITIALIZATION
 *    - Session, Database connection
 *    - Error reporting setup
 * 
 * 2. HELPER FUNCTIONS
 *    - Role normalization & hierarchy
 *    - User access management
 * 
 * 3. TAG COUNT API (OPTIMIZED FOR 1L+ RECORDS)
 *    - Individual count endpoints (11 types)
 *    - Optimized SQL queries
 *    - Filter handling
 * 
 * 4. LEAD DATA API
 *    - DataTable server-side processing
 *    - Pagination, Search, Filtering
 * 
 * 5. LEAD OPERATIONS
 *    - Add, Update, Delete leads
 *    - Status updates, Assignments
 *    - Bulk operations
 * 
 * 6. UTILITY ENDPOINTS
 *    - Filter dropdown values
 *    - User hierarchy
 * 
 * ============================================================================
 * PERFORMANCE OPTIMIZATIONS:
 * - Optimized WHERE clause ordering
 * - Query timeout handling (30s)
 * - Efficient filter processing
 * - Indexed column usage
 * ============================================================================
 */

$CRM_API_KEY_SERVER = "533f4175837e145064605e15e12c7273f98746fec7459b0168af9394a22c6efab6bba75cce18a3555250e473f4907d22aaae3e3f12e46dd8ef22fac38737c537"; // keep original

// Set JSON header first to ensure proper response format
header('Content-Type: application/json');

// Enable error reporting
ini_set('display_errors', 0); // Disable display to prevent HTML in JSON response
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

session_start();

// Check session validity
if (!isset($_SESSION['tablename']) || empty($_SESSION['tablename'])) {
    echo json_encode(['status' => 'error', 'message' => 'Session expired. Please login again.']);
    exit;
}

include 'config.php';
$useruniqueId = $_SESSION['tablename'];
$mainUser = $useruniqueId;
$userType = $_SESSION['user_type'];

$config = new Config();
$conn = $config->getConnection();

// ============================================================================
// SECTION 1: HELPER FUNCTIONS
// ============================================================================

// Hierarchy helper functions
function normalize_role_update($rawType)
{
    $s = strtolower(trim((string) $rawType));
    switch ($s) {
        case 'ceo':
        case 'promoter':
            return 'promoter';
        case 'business_head':
        case 'business head':
        case 'bh':
            return 'business_head';
        case 'manager':
        case 'm':
            return 'manager';
        case 'team_lead':
        case 'team lead':
        case 'tl':
        case 'team_leader':
            return 'team_lead';
        case 'sales_executive':
        case 'sales executive':
        case 'se':
        case 'sales':
        case 'user':
        case 'u':
        default:
            return 'user';
    }
}

function role_level_update($normalizedRole)
{
    $map = [
        'promoter' => 1,
        'business_head' => 2,
        'manager' => 3,
        'team_lead' => 4,
        'user' => 5,
    ];
    return $map[strtolower(trim((string) $normalizedRole))] ?? 5;
}

function get_accessible_users_update(PDO $conn, $userTablename, $userRole)
{
    $accessible = [$userTablename];
    $normalizedRole = normalize_role_update($userRole);
    $userLevel = role_level_update($normalizedRole);

    if ($userLevel < 5) {
        $subordinates = [];
        $visited = [];
        $queue = [$userTablename];
        $maxDepth = 10;
        $currentDepth = 0;

        while (!empty($queue) && $currentDepth < $maxDepth) {
            $currentLevel = [];
            foreach ($queue as $currentUser) {
                if (isset($visited[$currentUser]))
                    continue;
                $visited[$currentUser] = true;

                // $stmt = $conn->prepare("
                //     SELECT tablename FROM accounts 
                //     WHERE assign_user = :manager AND is_active = 1
                // ");
                $stmt = $conn->prepare("
                    SELECT tablename FROM accounts 
                    WHERE FIND_IN_SET(:manager, assign_user) > 0 AND is_active = 1
                ");
                $stmt->bindParam(':manager', $currentUser, PDO::PARAM_STR);
                $stmt->execute();
                $directReports = $stmt->fetchAll(PDO::FETCH_COLUMN);

                foreach ($directReports as $report) {
                    if (!isset($visited[$report])) {
                        $subordinates[] = $report;
                        $currentLevel[] = $report;
                    }
                }
            }
            $queue = $currentLevel;
            $currentDepth++;
        }
        $accessible = array_merge($accessible, $subordinates);
    }
    return array_unique($accessible);
}

/**
 * Get superior users in the hierarchy (managers, business heads, promoters)
 * Traverses upward through the assign_user chain
 * 
 * @param PDO $conn Database connection
 * @param string $userTablename The user's tablename
 * @return array Array of superior user tablenames (empty if no superiors)
 */
function get_superior_users_update(PDO $conn, $userTablename)
{
    $superiors = [];
    $visited = [];
    $currentUser = $userTablename;
    $maxDepth = 10; // Prevent infinite loops
    $depth = 0;

    while ($depth < $maxDepth) {
        // Prevent circular references
        if (isset($visited[$currentUser])) {
            break;
        }
        $visited[$currentUser] = true;

        // Get assign_user for current user
        $stmt = $conn->prepare("SELECT assign_user FROM accounts WHERE tablename = :tn AND is_active = 1 LIMIT 1");
        $stmt->bindParam(':tn', $currentUser, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || empty($row['assign_user'])) {
            break; // No more superiors
        }

        // Handle comma-separated assign_user (multiple managers)
        $assignUsers = array_filter(array_map('trim', explode(',', $row['assign_user'])));

        foreach ($assignUsers as $superior) {
            if (!isset($visited[$superior])) {
                $superiors[] = $superior;
                // For next iteration, follow the first superior in the chain
                if ($currentUser === $userTablename) {
                    $currentUser = $superior;
                }
            }
        }

        // If we added superiors but currentUser hasn't changed (shouldn't happen), break
        if ($currentUser === $userTablename && !empty($superiors)) {
            break;
        }

        $depth++;
    }

    return array_unique($superiors);
}

/**
 * Get subordinate users in the hierarchy (team leads, users below the manager)
 * Traverses downward through accounts where assign_user contains the manager
 * 
 * @param PDO $conn Database connection
 * @param string $userTablename The user's tablename
 * @param string $userRole The user's role
 * @return array Array of subordinate user tablenames (empty if no subordinates)
 */
function get_subordinate_users_update(PDO $conn, $userTablename, $userRole)
{
    $subordinates = [];
    $visited = [];
    $queue = [$userTablename];
    $maxDepth = 10; // Prevent infinite loops
    $currentDepth = 0;

    while (!empty($queue) && $currentDepth < $maxDepth) {
        $currentLevel = [];
        foreach ($queue as $currentUser) {
            if (isset($visited[$currentUser])) {
                continue;
            }
            $visited[$currentUser] = true;

            // Get all users who have this user as their manager (in assign_user field)
            $stmt = $conn->prepare("
                SELECT tablename 
                FROM accounts 
                WHERE FIND_IN_SET(:manager, assign_user) > 0 
                AND is_active = 1
            ");
            $stmt->bindParam(':manager', $currentUser, PDO::PARAM_STR);
            $stmt->execute();
            $directReports = $stmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($directReports as $report) {
                if (!isset($visited[$report])) {
                    $subordinates[] = $report;
                    $currentLevel[] = $report; // Add to queue for next level
                }
            }
        }
        $queue = $currentLevel;
        $currentDepth++;
    }

    return array_unique($subordinates);
}

/**
 * Get user details by tablename
 * 
 * @param PDO $conn Database connection
 * @param string $tablename The user's tablename
 * @return array|null User details or null if not found
 */
function get_user_details_update(PDO $conn, $tablename)
{
    $stmt = $conn->prepare("SELECT username, useremail, user_type FROM accounts WHERE tablename = :tn AND is_active = 1 LIMIT 1");
    $stmt->bindParam(':tn', $tablename, PDO::PARAM_STR);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Helper to get all superadmin users
 */
function get_superadmin_users_update(PDO $conn)
{
    try {
        $stmt = $conn->prepare("SELECT tablename FROM adminaccounts WHERE role = 'superuseradmin'");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    } catch (Exception $e) {
        return [];
    }
}

function build_notification_target_url_update($type, array $meta = [])
{
    $baseUrl = 'https://mnts.in/incentiveapp_integration/userlogin1/userlogin6/user_lead';
    $type = strtolower(trim((string) $type));
    $text = strtolower(trim((string) ($meta['title'] ?? '')) . ' ' . trim((string) ($meta['body'] ?? '')));

    if ($type === 'overdue_skip_alert' || strpos($text, 'overdue lead') !== false || strpos($text, 'overdue leads') !== false) {
        $query = [
            'filter' => 'overdueLeads',
            'teamView' => 'on',
            'managerView' => 'true'
        ];
        if (!empty($meta['skipped_by_id'])) {
            $query['filterUser'] = (string) $meta['skipped_by_id'];
        }
        return $baseUrl . '?' . http_build_query($query);
    }

    if ($type === 'followup_reminder' || strpos($text, 'follow-up') !== false || strpos($text, 'follow up') !== false) {
        return $baseUrl . '?filter=followLeads';
    }

    if (strpos($text, 'lead') !== false) {
        return $baseUrl;
    }

    return $baseUrl;
}

function mask_lead_number_for_notification_update($number)
{
    $digits = preg_replace('/\D+/', '', (string) $number);
    if ($digits === '') {
        return '';
    }

    if (strlen($digits) >= 10) {
        return 'xxxxx' . substr($digits, -5);
    }

    if (strlen($digits) <= 5) {
        return $digits;
    }

    return str_repeat('x', strlen($digits) - 5) . substr($digits, -5);
}

/**
 * Send notification to user hierarchy (local DB + external API)
 * 
 * @param PDO $conn Database connection
 * @param string $userTablename The user who triggered the action
 * @param string $title Notification title
 * @param string $body Notification body
 * @param string $type Notification type
 * @param array $meta Additional metadata
 * @return array Result of notification operations
 */
function send_hierarchy_notification_update(PDO $conn, $userTablename, $title, $body, $type, $meta = [])
{
    global $CRM_API_KEY_SERVER;

    $results = ['db' => false, 'api' => false, 'superiors_count' => 0];
    $notificationUrl = build_notification_target_url_update($type, ['title' => $title, 'body' => $body] + $meta);

    try {
        // Get superior users
        $superiorUsers = get_superior_users_update($conn, $userTablename);
        $results['superiors_count'] = count($superiorUsers);

        if (empty($superiorUsers)) {
            return $results;
        }

        // Add superadmins to the recipients
        $superAdmins = get_superadmin_users_update($conn);
        $superiorUsers = array_values(array_unique(array_merge($superiorUsers, $superAdmins)));

        // 1. Send via External API
        try {
            $notifyPayload = [
                "title" => $title,
                "body" => $body,
                "user_codes" => $superiorUsers,
                "url" => $notificationUrl,
                "project_code" => "Mnt_reos_nfs"
            ];

            $ch = curl_init("https://notification.mnts.in/api/notify-users");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer " . $CRM_API_KEY_SERVER,
                "Content-Type: application/json"
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($notifyPayload));
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);

            $notifyResp = curl_exec($ch);
            curl_close($ch);
            $results['api'] = true;
        } catch (Exception $apiEx) {
            error_log("Hierarchy API Notification error: " . $apiEx->getMessage());
        }

        // 2. Store in local DB
        try {
            $conn->beginTransaction();
            $sql = "INSERT INTO notifications (title, body, url, type, icon, sender, meta, created_at) 
                    VALUES (:title, :body, :url, :type, :icon, :sender, :meta, :created_at)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                ':title' => $title,
                ':body' => $body,
                ':url' => $notificationUrl,
                ':type' => $type,
                ':icon' => 'fas fa-exclamation-triangle',
                ':sender' => $userTablename,
                ':meta' => json_encode($meta),
                ':created_at' => date("Y-m-d H:i:s")
            ]);
            $nid = $conn->lastInsertId();

            $ins = $conn->prepare("INSERT INTO notification_receipts (notification_id, user_code, created_at) 
                                   VALUES (:nid, :uc, :created_at)");
            $now = date('Y-m-d H:i:s');
            foreach ($superiorUsers as $uc) {
                $ins->execute([':nid' => $nid, ':uc' => trim($uc), ':created_at' => $now]);
            }
            $conn->commit();
            $results['db'] = true;
        } catch (Exception $dbEx) {
            if ($conn->inTransaction())
                $conn->rollBack();
            error_log("Hierarchy DB Notification error: " . $dbEx->getMessage());
        }

    } catch (Exception $e) {
        error_log("send_hierarchy_notification_update general error: " . $e->getMessage());
    }

    return $results;
}

/**
 * Send notification to user hierarchy via external API only (no local DB storage)
 *
 * @param PDO $conn Database connection
 * @param string $userTablename The user who triggered the action
 * @param string $title Notification title
 * @param string $body Notification body
 * @param array $meta Additional metadata (kept for parity and future use)
 * @return array Result of notification operation
 */
function send_hierarchy_device_notification_update(PDO $conn, $userTablename, $title, $body, $meta = [])
{
    global $CRM_API_KEY_SERVER;

    $results = ['api' => false, 'superiors_count' => 0];
    $notificationUrl = build_notification_target_url_update('', ['title' => $title, 'body' => $body] + $meta);

    try {
        $superiorUsers = get_superior_users_update($conn, $userTablename);
        $results['superiors_count'] = count($superiorUsers);

        if (empty($superiorUsers)) {
            return $results;
        }

        // Add superadmins to the recipients
        $superAdmins = get_superadmin_users_update($conn);
        $superiorUsers = array_values(array_unique(array_merge($superiorUsers, $superAdmins)));

        $notifyPayload = [
            "title" => $title,
            "body" => $body,
            "user_codes" => $superiorUsers,
            "url" => $notificationUrl,
            "project_code" => "Mnt_reos_nfs"
        ];

        $ch = curl_init("https://notification.mnts.in/api/notify-users");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer " . $CRM_API_KEY_SERVER,
            "Content-Type: application/json"
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($notifyPayload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        curl_exec($ch);
        $notifyHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            error_log("Hierarchy Device Notification error: " . curl_error($ch));
        } else {
            $results['api'] = in_array($notifyHttp, [200, 201, 202], true);
        }

        curl_close($ch);
    } catch (Exception $e) {
        error_log("send_hierarchy_device_notification_update error: " . $e->getMessage());
    }

    return $results;
}

// ============================================================================
// WHATSAPP HISTORY — SAVE
// POST update_status.php?save_whatsapp_history=1
// Body: { lead_id, lead_name, lead_phone, project, sent_by, sent_by_name, timestamp }
// ============================================================================
if (isset($_GET['save_whatsapp_history']) && $_GET['save_whatsapp_history'] == '1') {
    header('Content-Type: application/json');
    try {
        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $leadId = (int) ($body['lead_id'] ?? 0);
        $remarkId = (int) ($body['remark_id'] ?? 0);
        $uploadId = (int) ($body['upload_id'] ?? 0);
        $userId = $useruniqueId;

        if ((!$leadId && !$uploadId && !$remarkId) || !$userId) {
            echo json_encode(['status' => 'error', 'message' => 'Missing identifiers or session']);
            exit;
        }

        // Auto-create whatsapp_history column if it doesn't exist yet
        $conn->exec("
            ALTER TABLE user_remarks
            ADD COLUMN IF NOT EXISTS whatsapp_history TEXT NULL DEFAULT NULL
        ");

        // Fetch existing whatsapp_history for this lead + user
        if ($remarkId) {
            $sel = $conn->prepare("SELECT whatsapp_history FROM user_remarks WHERE id = :id LIMIT 1");
            $sel->execute([':id' => $remarkId]);
        } else {
            $sel = $conn->prepare("SELECT whatsapp_history FROM user_remarks WHERE upload_data_id = :id AND user_unique_id = :uid LIMIT 1");
            $sel->execute([':id' => $uploadId ?: $leadId, ':uid' => $userId]);
        }
        $row = $sel->fetch(PDO::FETCH_ASSOC);

        $history = [];
        if ($row && !empty($row['whatsapp_history'])) {
            $history = json_decode($row['whatsapp_history'], true) ?: [];
        }

        // Build new entry (same style as regular history)
        $entry = [
            'lead_name' => $body['lead_name'] ?? '',
            'lead_phone' => $body['lead_phone'] ?? '',
            'project' => $body['project'] ?? '',
            'sent_by' => $body['sent_by'] ?? $userId,
            'sent_by_name' => $body['sent_by_name'] ?? '',
            'message' => 'Esha AI WhatsApp outreach sent',
            'status' => $body['api_status'] ?? 'sent',
            'timestamp' => date('Y-m-d H:i:s'),
        ];
        $history[] = $entry;

        if ($row !== false) {
            // Row exists — update it
            if ($remarkId) {
                $upd = $conn->prepare("UPDATE user_remarks SET whatsapp_history = :wh WHERE id = :id");
                $upd->execute([':wh' => json_encode($history, JSON_UNESCAPED_UNICODE), ':id' => $remarkId]);
            } else {
                $upd = $conn->prepare("UPDATE user_remarks SET whatsapp_history = :wh WHERE upload_data_id = :id AND user_unique_id = :uid");
                $upd->execute([':wh' => json_encode($history, JSON_UNESCAPED_UNICODE), ':id' => $uploadId ?: $leadId, ':uid' => $userId]);
            }
        } else {
            // No user_remarks row for this lead+user — do NOT insert a ghost row.
            // The lead must exist in user_remarks first (created by normal CRM flow).
            error_log("save_whatsapp_history: no user_remarks row for upload_data_id=$leadId, user=$userId — skipping insert to prevent ghost row");
            echo json_encode(['status' => 'skipped', 'message' => 'No CRM row found for this lead. WhatsApp history not saved.']);
            exit;
        }

        echo json_encode(['status' => 'success', 'entry' => $entry]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ============================================================================
// WHATSAPP HISTORY — GET
// GET update_status.php?get_whatsapp_history=1&lead_id=XXX
// ============================================================================
if (isset($_GET['get_whatsapp_history']) && $_GET['get_whatsapp_history'] == '1') {
    header('Content-Type: application/json');
    try {
        $leadId = (int) ($_GET['lead_id'] ?? 0);
        $remarkId = (int) ($_GET['remark_id'] ?? 0);
        $userId = $useruniqueId;

        if (!$leadId && !$remarkId) {
            echo json_encode(['status' => 'error', 'message' => 'Missing identifiers']);
            exit;
        }

        if ($remarkId) {
            $sel = $conn->prepare("SELECT whatsapp_history FROM user_remarks WHERE id = :id LIMIT 1");
            $sel->execute([':id' => $remarkId]);
        } else {
            $sel = $conn->prepare("SELECT whatsapp_history FROM user_remarks WHERE upload_data_id = :id AND user_unique_id = :uid LIMIT 1");
            $sel->execute([':id' => $leadId, ':uid' => $userId]);
        }
        $row = $sel->fetch(PDO::FETCH_ASSOC);

        $history = [];
        if ($row && !empty($row['whatsapp_history'])) {
            $history = json_decode($row['whatsapp_history'], true) ?: [];
        }

        echo json_encode(['status' => 'success', 'history' => $history]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// Get request data for updates or page requests
$data = json_decode(file_get_contents("php://input"), true);
date_default_timezone_set('Asia/Kolkata'); // Set your desired timezone

$dateFrom = isset($_GET['start_date']) ? $_GET['start_date'] : (isset($_GET['month']) ? date('Y-m-01', mktime(0, 0, 0, $_GET['month'], 1, $_GET['year'])) : date('Y-m-01'));
$dateTo = isset($_GET['end_date']) ? $_GET['end_date'] : (isset($_GET['month']) ? date('Y-m-t', mktime(0, 0, 0, $_GET['month'], 1, $_GET['year'])) : date('Y-m-t'));

// Check if we have date filters from multiFilters
if (isset($_GET['multiFilters']) && !empty($_GET['multiFilters'])) {
    $multiFilters = json_decode($_GET['multiFilters'], true);

    if (isset($multiFilters['start_date']) && !empty($multiFilters['start_date'])) {
        $dateFrom = $multiFilters['start_date'];
    }

    if (isset($multiFilters['end_date']) && !empty($multiFilters['end_date'])) {
        $dateTo = $multiFilters['end_date'];
    }
}


// ============================================================================
// GET NEXT OVERDUE LEAD TO PROCESS
// ============================================================================
if (isset($_GET['get_next_overdue_lead']) && $_GET['get_next_overdue_lead'] == '1') {
    try {
        $today = date('Y-m-d');

        // Get accessible users for the current user
        $accessibleUsers = get_accessible_users_update($conn, $useruniqueId, $_SESSION['user_type'] ?? 'user');

        if (count($accessibleUsers) === 0) {
            echo json_encode([
                'status' => 'success',
                'lead' => null,
                'remaining' => 0,
                'debug' => 'No accessible users'
            ]);
            exit;
        }

        // Build named placeholders for IN clause
        $userPlaceholders = [];
        $userBindings = [];
        foreach ($accessibleUsers as $index => $userId) {
            $placeholder = ":user_$index";
            $userPlaceholders[] = $placeholder;
            $userBindings[$placeholder] = $userId;
        }
        $placeholdersString = implode(',', $userPlaceholders);

        // Get overdue leads that haven't been shown today yet
        $query = "
            SELECT 
                ur.upload_data_id as id,
                sud.name,
                sud.number,
                ur.status,
                ur.follow_up_date,
                ur.follow_up_time,
                sud.assign_project_name,
                ur.user_unique_id
            FROM user_remarks ur
            INNER JOIN shi_upload_data sud ON sud.id = ur.upload_data_id
            LEFT JOIN overdue_leads_tracking olt ON 
                olt.lead_id = ur.upload_data_id 
                AND olt.user_unique_id = :mainUser
                AND olt.shown_date = :today
            WHERE ur.history_h = 0
            AND ur.user_unique_id IN ($placeholdersString)
            AND ur.status IN ('Follow Up', 'RNR', 'Call Back', 'Pending', 'Interested', 'Fix Site Visit', 'Site Visit Done', 'VC Done', 'Re site visit', 'Not Connected')
            AND ur.status NOT IN ('Converted', 'Already Booked')
            AND ur.follow_up_date IS NOT NULL
            AND ur.follow_up_time IS NOT NULL
            AND CONCAT(ur.follow_up_date, ' ', ur.follow_up_time) < DATE_SUB(NOW(), INTERVAL 1 DAY)
            AND olt.id IS NULL
            ORDER BY CONCAT(ur.follow_up_date, ' ', ur.follow_up_time) ASC
            LIMIT 1
        ";

        $stmt = $conn->prepare($query);
        $stmt->bindValue(':mainUser', $useruniqueId, PDO::PARAM_STR);
        $stmt->bindValue(':today', $today, PDO::PARAM_STR);

        // Bind all user parameters
        foreach ($userBindings as $placeholder => $userId) {
            $stmt->bindValue($placeholder, $userId, PDO::PARAM_STR);
        }

        $stmt->execute();
        $lead = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get remaining count
        $countQuery = "
            SELECT COUNT(DISTINCT ur.upload_data_id) as count
            FROM user_remarks ur
            INNER JOIN shi_upload_data sud ON sud.id = ur.upload_data_id
            LEFT JOIN overdue_leads_tracking olt ON 
                olt.lead_id = ur.upload_data_id 
                AND olt.user_unique_id = :mainUser2
                AND olt.shown_date = :today2
            WHERE ur.history_h = 0
            AND ur.user_unique_id IN ($placeholdersString)
            AND ur.status IN ('Follow Up', 'RNR', 'Call Back', 'Pending', 'Interested', 'Fix Site Visit', 'Site Visit Done', 'VC Done', 'Re site visit', 'Not Connected')
            AND ur.status NOT IN ('Converted', 'Already Booked')
            AND ur.follow_up_date IS NOT NULL
            AND ur.follow_up_time IS NOT NULL
            AND CONCAT(ur.follow_up_date, ' ', ur.follow_up_time) < DATE_SUB(NOW(), INTERVAL 1 DAY)
            AND olt.id IS NULL
        ";

        $countStmt = $conn->prepare($countQuery);
        $countStmt->bindValue(':mainUser2', $useruniqueId, PDO::PARAM_STR);
        $countStmt->bindValue(':today2', $today, PDO::PARAM_STR);

        // Bind all user parameters
        foreach ($userBindings as $placeholder => $userId) {
            $countStmt->bindValue($placeholder, $userId, PDO::PARAM_STR);
        }

        $countStmt->execute();
        $remaining = (int) ($countStmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);

        echo json_encode([
            'status' => 'success',
            'lead' => $lead ?: null,
            'remaining' => $remaining,
            'debug' => [
                'user' => $useruniqueId,
                'today' => $today,
                'accessible_users_count' => count($accessibleUsers)
            ]
        ]);
        exit;
    } catch (Exception $e) {
        error_log("Get next overdue lead error: " . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage(),
            'error_details' => $e->getTraceAsString(),
            'lead' => null,
            'remaining' => 0
        ]);
        exit;
    }
}

// ============================================================================
// DEBUG: CHECK SESSION AND USER ACCESS
// ============================================================================
if (isset($_GET['debug_session']) && $_GET['debug_session'] == '1') {
    try {
        $accessibleUsers = get_accessible_users_update($conn, $useruniqueId, $_SESSION['user_type'] ?? 'user');

        // Count how many overdue leads each user has
        $userCounts = [];
        foreach ($accessibleUsers as $userId) {
            $stmt = $conn->prepare("
                SELECT COUNT(*) as cnt
                FROM user_remarks 
                WHERE user_unique_id = ?
                AND follow_up_date IS NOT NULL 
                AND follow_up_time IS NOT NULL
                AND CONCAT(follow_up_date, ' ', follow_up_time) < DATE_SUB(NOW(), INTERVAL 1 DAY)
            ");
            $stmt->execute([$userId]);
            $userCounts[$userId] = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];
        }

        echo json_encode([
            'status' => 'success',
            'session_user' => $useruniqueId,
            'user_type' => $_SESSION['user_type'] ?? 'unknown',
            'accessible_users' => $accessibleUsers,
            'overdue_counts_by_user' => $userCounts,
            'total_accessible_overdue' => array_sum($userCounts)
        ], JSON_PRETTY_PRINT);
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

// ============================================================================
// DEBUG: CHECK OVERDUE COLUMN STATUS
// ============================================================================
if (isset($_GET['debug_overdue_column']) && $_GET['debug_overdue_column'] == '1') {
    try {
        // Check if column exists
        $columnCheck = $conn->query("
            SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'user_remarks' 
            AND COLUMN_NAME LIKE '%skip%'
        ");
        $columns = $columnCheck->fetchAll(PDO::FETCH_ASSOC);

        // Get sample data
        $sampleQuery = $conn->query("
            SELECT upload_data_id, overdue_skipped_at, follow_up_date, follow_up_time, status
            FROM user_remarks 
            WHERE follow_up_date IS NOT NULL 
            AND follow_up_time IS NOT NULL
            ORDER BY upload_data_id DESC
            LIMIT 10
        ");
        $sampleData = $sampleQuery->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'status' => 'success',
            'columns' => $columns,
            'sample_data' => $sampleData,
            'current_time' => date('Y-m-d H:i:s')
        ], JSON_PRETTY_PRINT);
        exit;
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
        exit;
    }
}

// ============================================================================
// GET ALL OVERDUE LEADS AT ONCE (SIMPLE QUEUE MODE)
// ============================================================================
if (isset($_GET['get_all_overdue_leads']) && $_GET['get_all_overdue_leads'] == '1') {
    try {
        // Validate session
        if (!isset($useruniqueId) || empty($useruniqueId)) {
            error_log("get_all_overdue_leads - ERROR: No valid user session");
            echo json_encode([
                'status' => 'error',
                'message' => 'Invalid session'
            ]);
            exit;
        }

        // CHECK TOGGLE STATE FIRST - THIS IS THE MOST IMPORTANT CHECK
        // NULL or 1 = enabled (default), 0 = disabled
        $toggleCheck = $conn->prepare("SELECT COALESCE(overdue_popup_enabled, 1) as is_enabled FROM accounts WHERE tablename = :tn");
        $toggleCheck->bindValue(':tn', $useruniqueId, PDO::PARAM_STR);
        $toggleCheck->execute();
        $toggleData = $toggleCheck->fetch(PDO::FETCH_ASSOC);

        // Log for debugging with timestamp
        error_log("[" . date('Y-m-d H:i:s') . "] get_all_overdue_leads - User: {$useruniqueId}, Toggle value from DB: " . ($toggleData['is_enabled'] ?? 'NULL'));

        // If toggle is explicitly disabled (0), return empty - NO EXCEPTIONS
        if ($toggleData && (int) $toggleData['is_enabled'] === 0) {
            error_log("[" . date('Y-m-d H:i:s') . "] get_all_overdue_leads - ❌ POPUPS DISABLED for user {$useruniqueId}. Returning empty array.");
            echo json_encode([
                'status' => 'success',
                'leads' => [],
                'count' => 0,
                'popup_disabled' => true,
                'message' => 'Overdue popup is disabled for your account'
            ]);
            exit;
        }

        error_log("[" . date('Y-m-d H:i:s') . "] get_all_overdue_leads - ✅ Popups ENABLED for user {$useruniqueId}. Fetching leads...");
        $today = date('Y-m-d');

        // Get accessible users for the current user
        // OPTIMIZED: Force overdue popups to ONLY show the logged-in user's own leads (ignore Team View)
        $accessibleUsers = [$useruniqueId];

        if (count($accessibleUsers) === 0) {
            echo json_encode([
                'status' => 'success',
                'leads' => [],
                'count' => 0
            ]);
            exit;
        }

        // Build named placeholders for IN clause
        $userPlaceholders = [];
        $userBindings = [];
        foreach ($accessibleUsers as $index => $userId) {
            $placeholder = ":user_$index";
            $userPlaceholders[] = $placeholder;
            $userBindings[$placeholder] = $userId;
        }
        $placeholdersString = implode(',', $userPlaceholders);

        // Check if column exists, create if not
        try {
            $columnCheck = $conn->query("
                SELECT COUNT(*) as col_exists 
                FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'user_remarks' 
                AND COLUMN_NAME = 'overdue_skipped_at'
            ");
            $colExists = $columnCheck->fetch(PDO::FETCH_ASSOC)['col_exists'];

            if (!$colExists) {
                $conn->exec("
                    ALTER TABLE user_remarks 
                    ADD COLUMN overdue_skipped_at DATETIME DEFAULT NULL 
                    COMMENT '30-minute skip tracking - reappears exactly 30min after skip'
                ");
            }
        } catch (Exception $e) {
            error_log("Column check/create error: " . $e->getMessage());
        }

        // FETCH ALL OVERDUE LEADS (no user filter - matches skip_all behavior)
        // This shows all overdue leads in database, respecting 30-minute skip window
        $countQuery = "
            SELECT COUNT(DISTINCT ur.upload_data_id) as total
            FROM user_remarks ur
            INNER JOIN shi_upload_data sud ON sud.id = ur.upload_data_id
            WHERE ur.history_h = 0
            AND ur.user_unique_id IN ($placeholdersString)
            AND ur.status IN ('Follow Up', 'RNR', 'Call Back', 'Pending', 'Interested', 'Fix Site Visit', 'Site Visit Done', 'VC Done', 'Re site visit', 'Not Connected')
            AND ur.status NOT IN ('Converted', 'Already Booked')
            AND ur.follow_up_date IS NOT NULL
            AND ur.follow_up_time IS NOT NULL
            AND CONCAT(ur.follow_up_date, ' ', ur.follow_up_time) < DATE_SUB(NOW(), INTERVAL 1 DAY)
            AND (ur.overdue_skipped_at IS NULL OR DATE_ADD(ur.overdue_skipped_at, INTERVAL 30 MINUTE) <= NOW())
        ";

        $countStmt = $conn->prepare($countQuery);
        foreach ($userBindings as $placeholder => $userId) {
            $countStmt->bindValue($placeholder, $userId, PDO::PARAM_STR);
        }
        $countStmt->execute();
        $totalCount = (int) ($countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        // Get ALL overdue leads that haven't been skipped in last 24 hours
        $query = "
            SELECT 
                ur.upload_data_id as id,
                sud.name,
                sud.number,
                ur.status,
                ur.follow_up_date,
                ur.follow_up_time,
                sud.assign_project_name,
                ur.user_unique_id
            FROM user_remarks ur
            INNER JOIN shi_upload_data sud ON sud.id = ur.upload_data_id
            WHERE ur.history_h = 0
            AND ur.user_unique_id IN ($placeholdersString)
            AND ur.status IN ('Follow Up', 'RNR', 'Call Back', 'Pending', 'Interested', 'Fix Site Visit', 'Site Visit Done', 'VC Done', 'Re site visit', 'Not Connected')
            AND ur.status NOT IN ('Converted', 'Already Booked')
            AND ur.follow_up_date IS NOT NULL
            AND ur.follow_up_time IS NOT NULL
            AND CONCAT(ur.follow_up_date, ' ', ur.follow_up_time) < DATE_SUB(NOW(), INTERVAL 1 DAY)
            AND (ur.overdue_skipped_at IS NULL OR DATE_ADD(ur.overdue_skipped_at, INTERVAL 30 MINUTE) <= NOW())
            ORDER BY CONCAT(ur.follow_up_date, ' ', ur.follow_up_time) ASC
        ";

        $stmt = $conn->prepare($query);
        foreach ($userBindings as $placeholder => $userId) {
            $stmt->bindValue($placeholder, $userId, PDO::PARAM_STR);
        }
        $stmt->execute();
        $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'status' => 'success',
            'leads' => $leads,
            'total_count' => $totalCount,
            'count' => count($leads)
        ]);
        exit;
    } catch (Exception $e) {
        error_log("Get all overdue leads error: " . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage(),
            'leads' => [],
            'count' => 0
        ]);
        exit;
    }
}

// ============================================================================
// SKIP ALL REMAINING OVERDUE LEADS
// ============================================================================
if (isset($_GET['skip_all_overdue_leads']) && $_GET['skip_all_overdue_leads'] == '1') {
    try {
        $today = date('Y-m-d');

        // Get accessible users for the current user
        // OPTIMIZED: Force overdue popups to ONLY show the logged-in user's own leads (ignore Team View)
        $accessibleUsers = [$useruniqueId];

        if (count($accessibleUsers) === 0) {
            echo json_encode(['status' => 'success', 'count' => 0, 'message' => 'No accessible users']);
            exit;
        }

        // Build named placeholders for IN clause
        $userPlaceholders = [];
        $userBindings = [];
        foreach ($accessibleUsers as $index => $userId) {
            $placeholder = ":user_$index";
            $userPlaceholders[] = $placeholder;
            $userBindings[$placeholder] = $userId;
        }
        $placeholdersString = implode(',', $userPlaceholders);

        // Check if column exists, create if not
        try {
            $columnCheck = $conn->query("
                SELECT COUNT(*) as col_exists 
                FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'user_remarks' 
                AND COLUMN_NAME = 'overdue_skipped_at'
            ");
            $colExists = $columnCheck->fetch(PDO::FETCH_ASSOC)['col_exists'];

            if (!$colExists) {
                $conn->exec("
                    ALTER TABLE user_remarks 
                    ADD COLUMN overdue_skipped_at DATETIME DEFAULT NULL 
                    COMMENT '30-minute skip tracking - reappears exactly 30min after skip'
                ");
            }
        } catch (Exception $e) {
            error_log("Column check/create error: " . $e->getMessage());
        }

        // Start explicit transaction to ensure data is committed
        try {
            if (!$conn->inTransaction()) {
                $conn->beginTransaction();
            }
        } catch (Exception $e) {
            error_log("Transaction start error: " . $e->getMessage());
        }

        // UPDATE ALL OVERDUE LEADS (no user filter - update everything shown in frontend)
        // This handles both "My Leads" and "Team View" modes
        $updateQuery = "
            UPDATE user_remarks ur
            INNER JOIN shi_upload_data sud ON sud.id = ur.upload_data_id
            SET ur.overdue_skipped_at = NOW()
            WHERE ur.history_h = 0
            AND ur.user_unique_id IN ($placeholdersString)
            AND ur.status IN ('Follow Up', 'RNR', 'Call Back', 'Pending', 'Interested', 'Fix Site Visit', 'Site Visit Done', 'VC Done', 'Re site visit', 'Not Connected')
            AND ur.status NOT IN ('Converted', 'Already Booked')
            AND ur.follow_up_date IS NOT NULL
            AND ur.follow_up_time IS NOT NULL
            AND CONCAT(ur.follow_up_date, ' ', ur.follow_up_time) < DATE_SUB(NOW(), INTERVAL 1 DAY)
            AND (ur.overdue_skipped_at IS NULL OR DATE_ADD(ur.overdue_skipped_at, INTERVAL 30 MINUTE) <= NOW())
        ";

        $stmt = $conn->prepare($updateQuery);
        foreach ($userBindings as $placeholder => $userId) {
            $stmt->bindValue($placeholder, $userId, PDO::PARAM_STR);
        }
        $executeResult = $stmt->execute();
        $skippedCount = $stmt->rowCount();

        // Force commit to ensure data is persisted
        $conn->commit();

        // VERIFY: Check what was actually stored in the database (match UPDATE conditions)
        $verifyQuery = "
            SELECT COUNT(*) as updated_count,
                   COUNT(CASE WHEN ur.overdue_skipped_at IS NOT NULL THEN 1 END) as has_value_count,
                   MAX(ur.overdue_skipped_at) as latest_skip_time
            FROM user_remarks ur
            INNER JOIN shi_upload_data sud ON sud.id = ur.upload_data_id
            WHERE ur.history_h = 0
            AND ur.user_unique_id IN ($placeholdersString)
            AND ur.status IN ('Follow Up', 'RNR', 'Call Back', 'Pending', 'Interested', 'Fix Site Visit', 'Site Visit Done', 'VC Done', 'Re site visit', 'Not Connected')
            AND ur.status NOT IN ('Converted', 'Already Booked')
            AND ur.follow_up_date IS NOT NULL
            AND ur.follow_up_time IS NOT NULL
            AND CONCAT(ur.follow_up_date, ' ', ur.follow_up_time) < DATE_SUB(NOW(), INTERVAL 1 DAY)
        ";

        $verifyStmt = $conn->prepare($verifyQuery);
        foreach ($userBindings as $placeholder => $userId) {
            $verifyStmt->bindValue($placeholder, $userId, PDO::PARAM_STR);
        }
        $verifyStmt->execute();
        $verifyResult = $verifyStmt->fetch(PDO::FETCH_ASSOC);

        // Debug logging
        error_log("Skip all overdue - Execute result: " . ($executeResult ? 'true' : 'false'));
        error_log("Skip all overdue - Rows affected: " . $skippedCount);
        error_log("Skip all overdue - Verify: " . json_encode($verifyResult));

        // ====================================================================
        // SEND NOTIFICATION TO SUPERIOR USERS (BULK SKIP)
        // ====================================================================
        if ($skippedCount > 0) {
            $currentUserDetails = get_user_details_update($conn, $useruniqueId);
            if ($currentUserDetails) {
                $userName = $currentUserDetails['username'];
                $title = "Multiple Overdue Leads Skipped";
                $body = "$userName skipped $skippedCount overdue leads";
                $meta = [
                    'count' => $skippedCount,
                    'skipped_by' => $userName,
                    'skipped_by_id' => $useruniqueId,
                    'skip_type' => 'bulk',
                    'skipped_at' => date('Y-m-d H:i:s')
                ];
                send_hierarchy_notification_update($conn, $useruniqueId, $title, $body, 'overdue_skip_alert', $meta);
            }
        }
        // ====================================================================

        echo json_encode([
            'status' => 'success',
            'count' => $skippedCount,
            'message' => "Skipped $skippedCount overdue leads",
            'debug' => [
                'executed' => $executeResult,
                'rows_affected' => $skippedCount,
                'verification' => $verifyResult,
                'current_time' => date('Y-m-d H:i:s')
            ]
        ]);
        exit;
    } catch (Exception $e) {
        // Rollback transaction on error
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        error_log("Skip all overdue leads error: " . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage(),
            'count' => 0
        ]);
        exit;
    }
}

// ============================================================================
// MARK OVERDUE LEAD AS PROCESSED
// ============================================================================
if (isset($_GET['mark_overdue_lead']) && $_GET['mark_overdue_lead'] == '1') {
    try {
        $leadId = $_POST['lead_id'] ?? null;
        $actionStatus = $_POST['action_status'] ?? 'skipped'; // skipped or updated

        if (!$leadId) {
            echo json_encode(['status' => 'error', 'message' => 'Lead ID required']);
            exit;
        }

        // Check if column exists, create if not
        try {
            $columnCheck = $conn->query("
                SELECT COUNT(*) as col_exists 
                FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'user_remarks' 
                AND COLUMN_NAME = 'overdue_skipped_at'
            ");
            $colExists = $columnCheck->fetch(PDO::FETCH_ASSOC)['col_exists'];

            if (!$colExists) {
                $conn->exec("
                    ALTER TABLE user_remarks 
                    ADD COLUMN overdue_skipped_at DATETIME DEFAULT NULL 
                    COMMENT '30-minute skip tracking - reappears exactly 30min after skip'
                ");
            }
        } catch (Exception $e) {
            error_log("Column check/create error: " . $e->getMessage());
        }

        // OPTIMIZED: Store exact timestamp for 30-minute tracking
        // Only mark as skipped if action is 'skipped', otherwise leave it null (for 'updated' status)
        if ($actionStatus === 'skipped') {
            $updateStmt = $conn->prepare("
                UPDATE user_remarks 
                SET overdue_skipped_at = NOW()
                WHERE upload_data_id = :leadId 
                AND history_h = 0
            ");

            $updateStmt->execute([
                ':leadId' => $leadId
            ]);

            $rowsAffected = $updateStmt->rowCount();

            // Debug: Check what was actually stored
            $checkStmt = $conn->prepare("
                SELECT overdue_skipped_at, upload_data_id 
                FROM user_remarks 
                WHERE upload_data_id = :leadId
            ");
            $checkStmt->execute([':leadId' => $leadId]);
            $checkResult = $checkStmt->fetch(PDO::FETCH_ASSOC);

            // Debug logging
            error_log("Mark overdue lead - Lead ID: $leadId, User ID: $useruniqueId, Rows affected: $rowsAffected");
            error_log("Mark overdue lead - Stored value: " . ($checkResult['overdue_skipped_at'] ?? 'NULL'));

            // ====================================================================
            // SEND NOTIFICATION TO SUPERIOR USERS (SINGLE SKIP) - DEVICE ONLY
            // ====================================================================
            if ($rowsAffected > 0) {
                // Get lead details
                $leadDetailsStmt = $conn->prepare("SELECT name, number, assign_project_name FROM shi_upload_data WHERE id = :leadId LIMIT 1");
                $leadDetailsStmt->execute([':leadId' => $leadId]);
                $leadDetails = $leadDetailsStmt->fetch(PDO::FETCH_ASSOC);

                $currentUserDetails = get_user_details_update($conn, $useruniqueId);

                if ($leadDetails && $currentUserDetails) {
                    $userName = $currentUserDetails['username'];
                    $leadName = $leadDetails['name'] ?? 'Unknown';
                    $leadNumber = $leadDetails['number'] ?? '';
                    $maskedLeadNumber = mask_lead_number_for_notification_update($leadNumber);
                    $projectName = $leadDetails['assign_project_name'] ?? '';

                    $title = "Overdue Lead Skipped";
                    $body = "$userName skipped overdue lead for $leadName ($maskedLeadNumber)";
                    if ($projectName) {
                        $body .= " - $projectName";
                    }

                    $meta = [
                        'lead_id' => $leadId,
                        'lead_name' => $leadName,
                        'lead_number' => $maskedLeadNumber,
                        'project_name' => $projectName,
                        'skipped_by' => $userName,
                        'skipped_by_id' => $useruniqueId,
                        'skip_type' => 'single',
                        'skipped_at' => date('Y-m-d H:i:s')
                    ];
                    send_hierarchy_device_notification_update($conn, $useruniqueId, $title, $body, $meta);
                }
            }
            // ====================================================================

            $action_taken = 'skipped';
        } else {
            // For 'updated' status, just acknowledge - no need to mark anything
            // The lead will naturally not appear in overdue list anymore if status changed
            $action_taken = 'updated';
        }

        echo json_encode([
            'status' => 'success',
            'message' => 'Lead marked as ' . $action_taken,
            'lead_id' => $leadId,
            'action' => $actionStatus
        ]);
        exit;
    } catch (Exception $e) {
        error_log("Mark overdue lead error: " . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage(),
            'error_details' => $e->getTraceAsString()
        ]);
        exit;
    }
}

// ============================================================================
// GET OVERDUE POPUP TOGGLE SETTINGS
// For user: returns their own toggle state
// For managers/higher: returns their own + all subordinates' toggle states
// ============================================================================
if (isset($_GET['get_overdue_toggle_settings']) && $_GET['get_overdue_toggle_settings'] == '1') {

    header('Content-Type: application/json');

    try {

        // Validate session
        if (!isset($_SESSION['user_type']) || empty($useruniqueId)) {
            throw new Exception("Session invalid. Please refresh the page.");
        }

        $currentUserType = $_SESSION['user_type'];
        $currentUserNormalized = normalize_role_update($currentUserType);

        /* ==============================
         GET CURRENT USER SETTINGS
         ============================== */

        $stmt = $conn->prepare("
            SELECT 
                tablename,
                username,
                user_type,
                COALESCE(overdue_popup_enabled,1) AS overdue_popup_enabled,
                overdue_popup_locked_by
            FROM accounts
            WHERE tablename = :tn
            AND is_active = 1
            LIMIT 1
        ");

        $stmt->execute([
            ':tn' => $useruniqueId
        ]);

        $userSettings = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$userSettings) {
            throw new Exception("User not found");
        }

        $response = [
            'status' => 'success',
            'user' => [
                'tablename' => $userSettings['tablename'],
                'username' => $userSettings['username'],
                'user_type' => $userSettings['user_type'],
                'enabled' => (bool) $userSettings['overdue_popup_enabled'],
                'locked' => !empty($userSettings['overdue_popup_locked_by']),
                'locked_by' => $userSettings['overdue_popup_locked_by'],
                'can_modify' => empty($userSettings['overdue_popup_locked_by'])
            ],
            'subordinates' => []
        ];

        /* ==============================
         LOAD SUBORDINATES
         ============================== */

        if (in_array($currentUserNormalized, ['manager', 'team_lead', 'business_head', 'promoter'])) {

            $subordinates = get_subordinate_users_update($conn, $useruniqueId, $currentUserType);

            // Clean array indexes
            $subordinates = array_values(array_unique($subordinates));

            if (!empty($subordinates)) {

                $placeholders = implode(',', array_fill(0, count($subordinates), '?'));

                $sql = "
                    SELECT 
                        tablename,
                        username,
                        user_type,
                        COALESCE(overdue_popup_enabled,1) AS overdue_popup_enabled,
                        overdue_popup_locked_by
                    FROM accounts
                    WHERE tablename IN ($placeholders)
                    AND is_active = 1
                    ORDER BY 
                        CASE LOWER(REPLACE(user_type,' ','_'))
                            WHEN 'manager' THEN 1
                            WHEN 'team_lead' THEN 2
                            WHEN 'user' THEN 3
                            ELSE 4
                        END,
                        username
                ";

                $stmt = $conn->prepare($sql);
                $stmt->execute($subordinates);

                $subSettings = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($subSettings as $sub) {

                    $response['subordinates'][] = [
                        'tablename' => $sub['tablename'],
                        'username' => $sub['username'],
                        'user_type' => $sub['user_type'],
                        'enabled' => (bool) $sub['overdue_popup_enabled'],
                        'locked' => !empty($sub['overdue_popup_locked_by']),
                        'locked_by' => $sub['overdue_popup_locked_by']
                    ];

                }
            }
        }

        echo json_encode($response);
        exit;

    } catch (Throwable $e) {

        error_log("Toggle settings error: " . $e->getMessage());

        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);

        exit;
    }
}

// ============================================================================
// UPDATE OVERDUE POPUP TOGGLE
// User can update their own toggle (if not locked by manager)
// Managers can update subordinates' toggles and lock/unlock them
// ============================================================================
if (isset($_POST['update_overdue_toggle']) && $_POST['update_overdue_toggle'] == '1') {
    try {
        $targetUser = isset($_POST['target_user']) ? trim($_POST['target_user']) : $useruniqueId;
        $enabled = isset($_POST['enabled']) ? (bool) $_POST['enabled'] : true;
        $lockToggle = isset($_POST['lock']) ? (bool) $_POST['lock'] : false; // Whether to lock the toggle

        $currentUserType = $_SESSION['user_type'] ?? 'user';
        $currentUserNormalized = normalize_role_update($currentUserType);

        // Check if target user exists
        $stmt = $conn->prepare("
            SELECT tablename, username, overdue_popup_locked_by 
            FROM accounts 
            WHERE tablename = :tn AND is_active = 1
        ");
        $stmt->bindParam(':tn', $targetUser, PDO::PARAM_STR);
        $stmt->execute();
        $targetUserData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$targetUserData) {
            throw new Exception("Target user not found");
        }

        // Case 1: User updating their own toggle
        if ($targetUser === $useruniqueId) {
            // Check if toggle is locked by a manager
            if (!empty($targetUserData['overdue_popup_locked_by'])) {
                throw new Exception("Your overdue popup toggle is locked by your manager. Contact them to make changes.");
            }

            // Log before update
            error_log("About to update toggle for user: {$useruniqueId}, enabled value: " . ($enabled ? '1' : '0'));

            // Update own toggle (cannot lock own toggle)
            $stmt = $conn->prepare("
                UPDATE accounts 
                SET overdue_popup_enabled = :enabled
                WHERE tablename = :tn
            ");
            $stmt->bindParam(':enabled', $enabled, PDO::PARAM_INT);
            $stmt->bindParam(':tn', $useruniqueId, PDO::PARAM_STR);
            $updateSuccess = $stmt->execute();

            // Log the update for debugging
            error_log("Overdue toggle UPDATE executed. Success: " . ($updateSuccess ? 'YES' : 'NO'));
            error_log("Rows affected: " . $stmt->rowCount());
            error_log("Target user: {$useruniqueId}, Enabled: " . ($enabled ? 'ENABLED' : 'DISABLED'));

            // If no rows were affected, log error
            if ($stmt->rowCount() === 0) {
                error_log("WARNING: UPDATE query affected 0 rows. User may not exist or value unchanged.");
            }

            // Verify the update
            $verify = $conn->prepare("SELECT overdue_popup_enabled FROM accounts WHERE tablename = :tn");
            $verify->bindParam(':tn', $useruniqueId, PDO::PARAM_STR);
            $verify->execute();
            $verifyData = $verify->fetch(PDO::FETCH_ASSOC);
            error_log("Verified value in DB after UPDATE: " . ($verifyData['overdue_popup_enabled'] ?? 'NULL'));

            // ====================================================================
            // SEND NOTIFICATION TO SUPERIOR USERS WHEN USER CHANGES THEIR TOGGLE
            // ====================================================================
            try {
                // Get current user's details
                $userStmt = $conn->prepare("SELECT username, user_type FROM accounts WHERE tablename = :tn LIMIT 1");
                $userStmt->bindParam(':tn', $useruniqueId, PDO::PARAM_STR);
                $userStmt->execute();
                $currentUserDetails = $userStmt->fetch(PDO::FETCH_ASSOC);

                // Get superior users (managers/business heads above this user)
                $superiorUsers = get_superior_users_update($conn, $useruniqueId);

                if (!empty($superiorUsers) && $currentUserDetails) {
                    $conn->beginTransaction();

                    // Prepare notification content
                    $userName = $currentUserDetails['username'];
                    $statusText = $enabled ? 'enabled' : 'disabled';
                    $title = "Overdue Popup Toggle " . ucfirst($statusText);
                    $body = "$userName $statusText their overdue popup toggle";

                    $meta = [
                        'user_id' => $useruniqueId,
                        'username' => $userName,
                        'user_type' => $currentUserDetails['user_type'],
                        'toggle_status' => $statusText,
                        'enabled' => $enabled,
                        'changed_at' => date('Y-m-d H:i:s')
                    ];

                    // Insert notification
                    $sqlInsertNotif = "INSERT INTO notifications (title, body, url, type, icon, sender, meta, created_at) 
                                       VALUES (:title, :body, :url, :type, :icon, :sender, :meta, :created_at)";
                    $notifStmt = $conn->prepare($sqlInsertNotif);
                    $notifStmt->execute([
                        ':title' => $title,
                        ':body' => $body,
                        ':url' => 'user_lead.php',
                        ':type' => 'overdue_toggle_change',
                        ':icon' => $enabled ? 'fas fa-bell' : 'fas fa-bell-slash',
                        ':sender' => $useruniqueId,
                        ':meta' => json_encode($meta),
                        ':created_at' => date('Y-m-d H:i:s')
                    ]);
                    $notificationId = $conn->lastInsertId();

                    // Insert notification receipts for each superior user
                    $insReceipt = $conn->prepare("INSERT INTO notification_receipts (notification_id, user_code, created_at) 
                                                   VALUES (:nid, :uc, :created_at)");
                    foreach ($superiorUsers as $superiorId) {
                        $insReceipt->execute([
                            ':nid' => $notificationId,
                            ':uc' => $superiorId,
                            ':created_at' => date('Y-m-d H:i:s')
                        ]);
                    }

                    $conn->commit();
                    error_log("Overdue toggle notification sent to " . count($superiorUsers) . " superior users. User: $userName, Status: $statusText");
                }
            } catch (Exception $notifEx) {
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
                error_log("Notification error (overdue_toggle_change): " . $notifEx->getMessage());
                // Don't fail the main operation if notification fails
            }
            // ====================================================================

            echo json_encode([
                'status' => 'success',
                'message' => 'Your overdue popup toggle has been ' . ($enabled ? 'enabled' : 'disabled'),
                'target_user' => $targetUser,
                'enabled' => $enabled,
                'locked' => false,
                'verified_db_value' => (int) ($verifyData['overdue_popup_enabled'] ?? 1)
            ]);
            exit;
        }

        // Case 2: Manager updating subordinate's toggle
        // Verify that currentUser is manager/higher of targetUser
        $subordinates = get_subordinate_users_update($conn, $useruniqueId, $currentUserType);

        if (!in_array($targetUser, $subordinates)) {
            throw new Exception("You don't have permission to modify this user's toggle");
        }

        // Manager can update and lock/unlock
        $lockedBy = $lockToggle ? $useruniqueId : null;

        $stmt = $conn->prepare("
            UPDATE accounts 
            SET 
                overdue_popup_enabled = :enabled,
                overdue_popup_locked_by = :locked_by
            WHERE tablename = :tn
        ");
        $stmt->bindParam(':enabled', $enabled, PDO::PARAM_INT);
        $stmt->bindParam(':locked_by', $lockedBy, PDO::PARAM_STR);
        $stmt->bindParam(':tn', $targetUser, PDO::PARAM_STR);
        $stmt->execute();

        $lockStatus = $lockToggle ? 'locked' : 'unlocked';

        echo json_encode([
            'status' => 'success',
            'message' => "Toggle for {$targetUserData['username']} has been " . ($enabled ? 'enabled' : 'disabled') . " and $lockStatus",
            'target_user' => $targetUser,
            'enabled' => $enabled,
            'locked' => $lockToggle,
            'locked_by' => $lockedBy
        ]);
        exit;
    } catch (Exception $e) {
        error_log("Update overdue toggle error: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage(),
            'error_line' => $e->getLine(),
            'error_file' => basename($e->getFile())
        ]);
        exit;
    }
}

// ============================================================================
// LOCK/UNLOCK OVERDUE POPUP TOGGLE
// Manager can lock/unlock subordinate's toggle without changing enabled state
// ============================================================================
if (isset($_POST['lock_overdue_toggle']) && $_POST['lock_overdue_toggle'] == '1') {
    try {
        $targetUser = isset($_POST['target_user']) ? trim($_POST['target_user']) : null;
        $shouldLock = isset($_POST['lock']) ? (bool) $_POST['lock'] : true;

        if (!$targetUser) {
            throw new Exception("Target user is required");
        }

        // Cannot lock your own toggle
        if ($targetUser === $useruniqueId) {
            throw new Exception("You cannot lock your own toggle");
        }

        $currentUserType = $_SESSION['user_type'] ?? 'user';

        // Check if target user exists
        $stmt = $conn->prepare("
            SELECT tablename, username 
            FROM accounts 
            WHERE tablename = :tn AND is_active = 1
        ");
        $stmt->bindParam(':tn', $targetUser, PDO::PARAM_STR);
        $stmt->execute();
        $targetUserData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$targetUserData) {
            throw new Exception("Target user not found");
        }

        // Verify that currentUser is manager/higher of targetUser
        $subordinates = get_subordinate_users_update($conn, $useruniqueId, $currentUserType);

        if (!in_array($targetUser, $subordinates)) {
            throw new Exception("You don't have permission to lock/unlock this user's toggle");
        }

        // Update lock status
        $lockedBy = $shouldLock ? $useruniqueId : null;

        $stmt = $conn->prepare("
            UPDATE accounts 
            SET overdue_popup_locked_by = :locked_by
            WHERE tablename = :tn
        ");
        $stmt->bindParam(':locked_by', $lockedBy, PDO::PARAM_STR);
        $stmt->bindParam(':tn', $targetUser, PDO::PARAM_STR);
        $stmt->execute();

        $lockStatus = $shouldLock ? 'locked' : 'unlocked';

        echo json_encode([
            'status' => 'success',
            'message' => "Toggle for {$targetUserData['username']} has been $lockStatus",
            'target_user' => $targetUser,
            'locked' => $shouldLock,
            'locked_by' => $lockedBy
        ]);
        exit;
    } catch (Exception $e) {
        error_log("Lock overdue toggle error: " . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
        exit;
    }
}

// ============================================================================
// DEBUG: CHECK TOGGLE STATE IN DATABASE (TEMPORARY DIAGNOSTIC)
// ============================================================================
if (isset($_GET['debug_toggle_state']) && $_GET['debug_toggle_state'] == '1') {
    try {
        $stmt = $conn->prepare("
            SELECT 
                tablename,
                username, 
                overdue_popup_enabled,
                overdue_popup_locked_by
            FROM accounts 
            WHERE tablename = :tn
        ");
        $stmt->bindParam(':tn', $useruniqueId, PDO::PARAM_STR);
        $stmt->execute();
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'status' => 'success',
            'tablename' => $useruniqueId,
            'database_value' => $data,
            'raw_enabled_value' => $data['overdue_popup_enabled'],
            'enabled_as_bool' => (bool) $data['overdue_popup_enabled'],
            'coalesce_value' => $data['overdue_popup_enabled'] ?? 1
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
        exit;
    }
}

// ============================================================================
// CHECK UPCOMING FOLLOW-UPS (next 10 minutes)
// ============================================================================
if (isset($_GET['check_upcoming_followups']) && $_GET['check_upcoming_followups'] == '1') {
    try {
        $now = date('Y-m-d H:i:s');
        $tenMinutesLater = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        // Get follow-ups for current user in next 10 minutes
        $sql = "
            SELECT 
                ur.upload_data_id as lead_id,
                ur.user_unique_id,
                ur.status,
                ur.follow_up_date,
                ur.follow_up_time,
                CONCAT(ur.follow_up_date, ' ', ur.follow_up_time) as followup_datetime,
                sud.name as lead_name,
                sud.number as lead_phone,
                ur.assign_project_name as project_name,
                TIMESTAMPDIFF(MINUTE, NOW(), CONCAT(ur.follow_up_date, ' ', ur.follow_up_time)) as minutes_until
            FROM user_remarks ur
            INNER JOIN shi_upload_data sud ON sud.id = ur.upload_data_id
            WHERE ur.user_unique_id = :userUniqueId
                AND ur.status IN ('Follow Up', 'RNR', 'Call Back', 'Interested', 'EOI', 'Fix Site Visit', 'Site Visit Done', 'VC Done', 'Re site visit', 'Not Connected')
                AND ur.status != 'Converted'
                AND ur.status != 'Already Booked'
                AND ur.follow_up_date IS NOT NULL 
                AND ur.follow_up_time IS NOT NULL
                AND CONCAT(ur.follow_up_date, ' ', ur.follow_up_time) >= :now
                AND CONCAT(ur.follow_up_date, ' ', ur.follow_up_time) <= :tenMinutesLater
                AND ur.history_h = 0
            ORDER BY CONCAT(ur.follow_up_date, ' ', ur.follow_up_time) ASC
        ";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':userUniqueId' => $useruniqueId,
            ':now' => $now,
            ':tenMinutesLater' => $tenMinutesLater
        ]);
        $upcomingLeads = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'status' => 'success',
            'count' => count($upcomingLeads),
            'leads' => $upcomingLeads,
            'check_time' => $now
        ]);
        exit;
    } catch (Exception $e) {
        error_log("Check upcoming follow-ups error: " . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
        exit;
    }
}

// ============================================================================
// STORE FOLLOW-UP REMINDER NOTIFICATION
// ============================================================================
if (isset($_POST['store_followup_notification']) && $_POST['store_followup_notification'] == '1') {
    try {
        $title = $_POST['title'] ?? 'Follow-up Reminder';
        $body = $_POST['body'] ?? '';
        $leadsData = isset($_POST['leads']) ? json_decode($_POST['leads'], true) : [];

        if (empty($body)) {
            echo json_encode(['status' => 'error', 'message' => 'Notification body is required']);
            exit;
        }

        // Check if notification for this specific lead's followup_time was already sent
        // Extract lead_id and followup_time from leadsData
        $leadIds = array_column($leadsData, 'lead_id');
        $followupTimes = array_column($leadsData, 'followup_time');

        if (!empty($leadIds) && !empty($followupTimes)) {
            // Check if we already have a notification for these lead(s) with same followup time
            $leadIdStr = implode(',', $leadIds);
            $followupTime = $followupTimes[0]; // Use first lead's followup time

            $checkRecent = $conn->prepare("
                SELECT n.id 
                FROM notifications n
                JOIN notification_receipts nr ON nr.notification_id = n.id
                WHERE n.type = 'followup_reminder'
                  AND nr.user_code = :userCode
                  AND JSON_EXTRACT(n.meta, '$.leads[0].lead_id') = :leadId
                  AND JSON_EXTRACT(n.meta, '$.leads[0].followup_time') = :followupTime
            ");
            $checkRecent->execute([
                ':userCode' => $useruniqueId,
                ':leadId' => $leadIds[0],
                ':followupTime' => $followupTime
            ]);

            if ($checkRecent->fetch()) {
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Duplicate notification prevented - already notified for this follow-up',
                    'duplicate' => true
                ]);
                exit;
            }
        }

        // Store notification in database - DISABLED
        /*
        $meta = [
            'leads' => $leadsData,
            'count' => count($leadsData),
            'reminder_type' => 'followup_10min'
        ];

        $conn->beginTransaction();

        $sqlInsertNotif = "INSERT INTO notifications (title, body, url, type, icon, sender, meta, created_at) 
                           VALUES (:title, :body, :url, :type, :icon, :sender, :meta, :created_at)";
        $stmt = $conn->prepare($sqlInsertNotif);
        $stmt->execute([
            ':title' => $title,
            ':body' => $body,
            ':url' => 'https://mnts.in/incentiveapp_integration/userlogin1/userlogin6/user_lead',
            ':type' => 'followup_reminder',
            ':icon' => 'fas fa-clock',
            ':sender' => 'system',
            ':meta' => json_encode($meta),
            ':created_at' => date("Y-m-d H:i:s")
        ]);
        $notificationId = $conn->lastInsertId();

        $insReceipt = $conn->prepare("INSERT INTO notification_receipts (notification_id, user_code, created_at) 
                                       VALUES (:nid, :uc, :created_at)");
        $insReceipt->execute([
            ':nid' => $notificationId,
            ':uc' => $useruniqueId,
            ':created_at' => date('Y-m-d H:i:s')
        ]);

        $conn->commit();

        echo json_encode([
            'status' => 'success',
            'message' => 'Notification stored successfully',
            'notification_id' => $notificationId
        ]);
        */
        echo json_encode([
            'status' => 'success',
            'message' => 'Follow-up reminder skipped (notifications disabled)'
        ]);
        exit;

    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        error_log("Store follow-up notification error: " . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
        exit;
    }
}

// ============================================================================
// CHECK EXACT-TIME FOLLOW-UPS (at scheduled time)
// ============================================================================
if (isset($_GET['check_exact_followups']) && $_GET['check_exact_followups'] == '1') {
    try {
        $now = date('Y-m-d H:i:s');
        $currentDate = date('Y-m-d');
        $currentTime = date('H:i:s');

        // Narrow window for exact time: from start of today up to +2 minutes from current time
        // This ensures popup appears at scheduled time and DOES NOT disappear if missed
        $startOfToday = date('Y-m-d 00:00:00');
        $twoMinutesLater = date('Y-m-d H:i:s', strtotime('+2 minutes'));

        // Get follow-ups that are scheduled for today (up to +2 mins in future)
        $sql = "
            SELECT 
                ur.upload_data_id as lead_id,
                ur.user_unique_id,
                ur.status,
                ur.follow_up_date,
                ur.follow_up_time,
                CONCAT(ur.follow_up_date, ' ', ur.follow_up_time) as followup_datetime,
                sud.name as lead_name,
                sud.number as lead_phone,
                ur.assign_project_name as project_name,
                ur.remarks,
                TIMESTAMPDIFF(MINUTE, CONCAT(ur.follow_up_date, ' ', ur.follow_up_time), NOW()) as minutes_passed
            FROM user_remarks ur
            INNER JOIN shi_upload_data sud ON sud.id = ur.upload_data_id
            WHERE ur.user_unique_id = :userUniqueId
                AND ur.status IN ('Follow Up', 'RNR', 'Call Back', 'Interested', 'EOI', 'Fix Site Visit', 'Site Visit Done', 'VC Done', 'Re site visit', 'Not Connected')
                AND ur.status != 'Converted'
                AND ur.status != 'Already Booked'
                AND ur.follow_up_date IS NOT NULL 
                AND ur.follow_up_time IS NOT NULL
                AND ur.follow_up_date = CURDATE()
                AND CONCAT(ur.follow_up_date, ' ', ur.follow_up_time) <= :twoMinutesLater
                AND ur.history_h = 0
            ORDER BY CONCAT(ur.follow_up_date, ' ', ur.follow_up_time) ASC
        ";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':userUniqueId' => $useruniqueId,
            ':twoMinutesLater' => $twoMinutesLater
        ]);
        $exactTimeLeads = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'status' => 'success',
            'count' => count($exactTimeLeads),
            'leads' => $exactTimeLeads,
            'check_time' => $now,
            'window_start' => $twoMinutesAgo,
            'window_end' => $twoMinutesLater,
            'debug' => [
                'server_time' => $now,
                'server_date' => $currentDate,
                'server_time_only' => $currentTime,
                'user_id' => $useruniqueId
            ]
        ]);
        exit;
    } catch (Exception $e) {
        error_log("Check exact follow-ups error: " . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
        exit;
    }
}

// ============================================================================
// RESCHEDULE FOLLOW-UP (skip and reschedule for 24 hours later)
// ============================================================================
if (isset($_POST['reschedule_followup']) && $_POST['reschedule_followup'] == '1') {
    try {
        $leadId = $_POST['lead_id'] ?? null;
        $skipMode = strtolower(trim($_POST['skip_mode'] ?? 'single'));

        if (!$leadId) {
            echo json_encode(['status' => 'error', 'message' => 'Lead ID required']);
            exit;
        }

        // Get current follow-up datetime
        $stmt = $conn->prepare("
            SELECT follow_up_date, follow_up_time 
            FROM user_remarks 
            WHERE upload_data_id = :leadId AND user_unique_id = :userUniqueId
        ");
        $stmt->execute([
            ':leadId' => $leadId,
            ':userUniqueId' => $useruniqueId
        ]);
        $currentFollowup = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$currentFollowup) {
            echo json_encode(['status' => 'error', 'message' => 'Lead not found']);
            exit;
        }

        // Always reschedule to TOMORROW from today (not +24h from old overdue date).
        // If we used +24h from the old date, a lead overdue for 5 days would cascade
        // through each day one-by-one and incorrectly appear in "Today's Follow-Up".
        $newDate = date('Y-m-d', strtotime('+1 day'));
        $newTime = $currentFollowup['follow_up_time'] ?: '09:00:00'; // keep original time or default

        // Update follow-up date/time
        $updateStmt = $conn->prepare("
            UPDATE user_remarks 
            SET follow_up_date = :newDate, 
                follow_up_time = :newTime
            WHERE upload_data_id = :leadId 
            AND user_unique_id = :userUniqueId
        ");
        $updateStmt->execute([
            ':newDate' => $newDate,
            ':newTime' => $newTime,
            ':leadId'  => $leadId,
            ':userUniqueId' => $useruniqueId
        ]);

        // Get lead details for notification
        $leadDetailsStmt = $conn->prepare("SELECT name, number, assign_project_name FROM shi_upload_data WHERE id = :leadId LIMIT 1");
        $leadDetailsStmt->execute([':leadId' => $leadId]);
        $leadDetails = $leadDetailsStmt->fetch(PDO::FETCH_ASSOC);

        $currentUserDetails = get_user_details_update($conn, $useruniqueId);

        if ($leadDetails && $currentUserDetails) {
            $userName = $currentUserDetails['username'];
            $leadName = $leadDetails['name'] ?? 'Unknown';
            $leadNumber = $leadDetails['number'] ?? '';
            $maskedLeadNumber = mask_lead_number_for_notification_update($leadNumber);
            $projectName = $leadDetails['assign_project_name'] ?? '';

            $title = "Follow-up Popup Skipped";
            $body = "$userName skipped/rescheduled an exact-time follow-up for $leadName ($maskedLeadNumber)";
            if ($projectName) {
                $body .= " - $projectName";
            }
            $body .= ". New follow-up set for $newDateTime.";

            $meta = [
                'lead_id' => $leadId,
                'lead_name' => $leadName,
                'lead_number' => $maskedLeadNumber,
                'project_name' => $projectName,
                'skipped_by' => $userName,
                'skipped_by_id' => $useruniqueId,
                'skip_type' => $skipMode === 'bulk' ? 'followup_reschedule_bulk' : 'followup_reschedule_single',
                'new_datetime' => $newDateTime,
                'skipped_at' => date('Y-m-d H:i:s')
            ];

            // Single popup skip -> device only, Bulk popup skip -> device + DB
            if ($skipMode === 'bulk') {
                send_hierarchy_notification_update($conn, $useruniqueId, $title, $body, 'followup_skip_alert', $meta);
            } else {
                send_hierarchy_device_notification_update($conn, $useruniqueId, $title, $body, $meta);
            }
        }

        echo json_encode([
            'status' => 'success',
            'message' => 'Follow-up rescheduled for 24 hours later',
            'new_datetime' => $newDateTime,
            'new_date' => $newDate,
            'new_time' => $newTime
        ]);
        exit;

    } catch (Exception $e) {
        error_log("Reschedule follow-up error: " . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
        exit;
    }
}

// ============================================================================
// DEBUG: Show all follow-ups for current user
// ============================================================================
if (isset($_GET['debug_followups']) && $_GET['debug_followups'] == '1') {
    try {
        $now = date('Y-m-d H:i:s');

        $sql = "
            SELECT 
                ur.upload_data_id as lead_id,
                sud.name as lead_name,
                ur.status,
                ur.follow_up_date,
                ur.follow_up_time,
                CONCAT(ur.follow_up_date, ' ', ur.follow_up_time) as followup_datetime,
                ur.assign_project_name as project_name,
                TIMESTAMPDIFF(MINUTE, NOW(), CONCAT(ur.follow_up_date, ' ', ur.follow_up_time)) as minutes_until,
                CASE 
                    WHEN CONCAT(ur.follow_up_date, ' ', ur.follow_up_time) < NOW() THEN 'OVERDUE'
                    WHEN CONCAT(ur.follow_up_date, ' ', ur.follow_up_time) BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 10 MINUTE) THEN 'WITHIN_10_MIN'
                    WHEN CONCAT(ur.follow_up_date, ' ', ur.follow_up_time) BETWEEN DATE_SUB(NOW(), INTERVAL 10 MINUTE) AND DATE_ADD(NOW(), INTERVAL 5 MINUTE) THEN 'EXACT_TIME_WINDOW'
                    ELSE 'FUTURE'
                END as time_status
            FROM user_remarks ur
            INNER JOIN shi_upload_data sud ON sud.id = ur.upload_data_id
            WHERE ur.user_unique_id = :userUniqueId
                AND ur.follow_up_date IS NOT NULL 
                AND ur.follow_up_time IS NOT NULL
                AND ur.history_h = 0
            ORDER BY CONCAT(ur.follow_up_date, ' ', ur.follow_up_time) ASC
            LIMIT 50
        ";

        $stmt = $conn->prepare($sql);
        $stmt->execute([':userUniqueId' => $useruniqueId]);
        $followups = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'status' => 'success',
            'count' => count($followups),
            'server_time' => $now,
            'server_timezone' => date_default_timezone_get(),
            'followups' => $followups
        ], JSON_PRETTY_PRINT);
        exit;

    } catch (Exception $e) {
        error_log("Debug followups error: " . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
        exit;
    }
}


// ============================================================================
// GET OVERDUE LEADS COUNT (matching cron logic)
// ============================================================================
if (isset($_GET['get_overdue_count']) && $_GET['get_overdue_count'] == '1') {
    try {
        // Set a short timeout for this query
        $conn->setAttribute(PDO::ATTR_TIMEOUT, 10);

        // CHECK IF USER HAS OVERDUE POPUPS ENABLED - CRITICAL CHECK
        // NULL or 1 = enabled (default), 0 = disabled
        $toggleCheck = $conn->prepare("SELECT COALESCE(overdue_popup_enabled, 1) as is_enabled FROM accounts WHERE tablename = :tn AND is_active = 1");
        $toggleCheck->bindParam(':tn', $useruniqueId, PDO::PARAM_STR);
        $toggleCheck->execute();
        $toggleData = $toggleCheck->fetch(PDO::FETCH_ASSOC);

        // If toggle is explicitly disabled (0), return 0 - NO EXCEPTIONS
        if ($toggleData && (int) $toggleData['is_enabled'] === 0) {
            error_log("[" . date('Y-m-d H:i:s') . "] get_overdue_count - ❌ POPUPS DISABLED for user {$useruniqueId}. Returning 0.");
            echo json_encode([
                'status' => 'success',
                'count' => 0,
                'popup_disabled' => true
            ]);
            exit;
        }

        error_log("[" . date('Y-m-d H:i:s') . "] get_overdue_count - ✅ Popups ENABLED for user {$useruniqueId}. Counting leads...");

        $currentUserType = $_SESSION['user_type'] ?? 'user';
        $currentUserNormalized = normalize_role_update($currentUserType);
        $currentUserLevel = role_level_update($currentUserNormalized);

        // Get accessible users for the current user
        $accessibleUsers = get_accessible_users_update($conn, $useruniqueId, $currentUserType);

        // Build the WHERE clause for accessible users
        $userCondition = '';
        if (count($accessibleUsers) > 0) {
            $placeholders = implode(',', array_fill(0, count($accessibleUsers), '?'));
            $userCondition = "AND ur.user_unique_id IN ($placeholders)";
        }

        // Check if column exists, create if not
        try {
            $columnCheck = $conn->query("
                SELECT COUNT(*) as col_exists 
                FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'user_remarks' 
                AND COLUMN_NAME = 'overdue_skipped_at'
            ");
            $colExists = $columnCheck->fetch(PDO::FETCH_ASSOC)['col_exists'];

            if (!$colExists) {
                $conn->exec("
                    ALTER TABLE user_remarks 
                    ADD COLUMN overdue_skipped_at DATETIME DEFAULT NULL 
                    COMMENT '30-minute skip tracking - reappears exactly 30min after skip'
                ");
            }
        } catch (Exception $e) {
            error_log("Column check/create error: " . $e->getMessage());
        }

        // Count overdue leads (matching daily_summary_cron.php logic exactly)
        // Overdue = follow_up_date + follow_up_time is more than 1 day in the past
        // COUNT ALL OVERDUE LEADS (no user filter - matches get_all and skip_all)
        $query = "
            SELECT COUNT(DISTINCT ur.upload_data_id) as count
            FROM user_remarks ur
            INNER JOIN shi_upload_data sud ON sud.id = ur.upload_data_id
            WHERE ur.history_h = 0
            AND ur.status IN ('Follow Up', 'RNR', 'Call Back', 'Pending', 'Interested', 'Fix Site Visit', 'Site Visit Done', 'VC Done', 'Re site visit', 'Not Connected')
            AND ur.status != 'Converted'
            AND ur.status != 'Already Booked'
            AND ur.follow_up_date IS NOT NULL
            AND ur.follow_up_time IS NOT NULL
            AND CONCAT(ur.follow_up_date, ' ', ur.follow_up_time) < DATE_SUB(NOW(), INTERVAL 1 DAY)
            AND (ur.overdue_skipped_at IS NULL OR DATE_ADD(ur.overdue_skipped_at, INTERVAL 30 MINUTE) <= NOW())
        ";

        $stmt = $conn->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $overdueCount = (int) ($result['count'] ?? 0);

        echo json_encode([
            'status' => 'success',
            'count' => $overdueCount
        ]);
        exit;
    } catch (Exception $e) {
        error_log("Get overdue count error: " . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to fetch overdue count',
            'count' => 0
        ]);
        exit;
    }
}


// Handle the update request
if (isset($data['update'])) {
    $id             = (int) $data['rowId'];
    $status         = $data['status'];
    $notes          = $data['notes'];
    $followUpDate   = $data['followUpDate'];
    $followUpTime   = $data['followUpTime'];
    $leadOwner      = $data['user_unique_id'];   // actual owner of the lead
    $leadIdentity   = $data['lead_identity']   ?? null;
    $budget         = $data['budget']          ?? null;
    $locationStatus = $data['location_status'] ?? null;
    date_default_timezone_set('Asia/Kolkata');

    if (empty($leadOwner)) {
        echo json_encode(['status' => 'error', 'message' => 'User unique ID is missing.']);
        exit;
    }

    try {
        // Fetch existing history for this lead+owner (single query)
        $stmt = $conn->prepare(
            "SELECT history FROM user_remarks
             WHERE upload_data_id = :id AND user_unique_id = :owner"
        );
        $stmt->execute([':id' => $id, ':owner' => $leadOwner]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $history = json_decode($row['history'] ?? '[]', true);
        if (!is_array($history)) $history = [];

        // Append new history entry
        $history[] = [
            'status'         => $status,
            'notes'          => $notes,
            'followUpDate'   => $followUpDate,
            'followUpTime'   => $followUpTime,
            'leadIdentity'   => $leadIdentity,
            'budget'         => $budget,
            'locationStatus' => $locationStatus,
            'timestamp'      => date('Y-m-d H:i:s'),
            'update_by'      => $mainUser,
        ];

        // Update lead status, remarks, follow-up, and appended history
        $stmt = $conn->prepare(
            "UPDATE user_remarks
             SET status          = :status,
                 remarks         = :remarks,
                 follow_up_date  = :followUpDate,
                 follow_up_time  = :followUpTime,
                 history         = :history,
                 lead_identity   = :leadIdentity,
                 budget          = :budget,
                 location_status = :locationStatus
             WHERE upload_data_id = :id AND user_unique_id = :owner"
        );
        $stmt->execute([
            ':status'         => $status,
            ':remarks'        => $notes,
            ':followUpDate'   => $followUpDate,
            ':followUpTime'   => $followUpTime,
            ':history'        => json_encode($history),
            ':leadIdentity'   => $leadIdentity,
            ':budget'         => $budget,
            ':locationStatus' => $locationStatus,
            ':id'             => $id,
            ':owner'          => $leadOwner,
        ]);

        echo json_encode([
            'status'          => 'success',
            'message'         => 'Row updated successfully',
            'updated_status'  => $status,
            'updated_remarks' => $notes,
            'history'         => $history,
        ]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'An error occurred: ' . $e->getMessage()]);
    }
    exit;
}
// Call history — true = log a new click, false/absent = read-only count
if (isset($data['history_call'])) {
    $id = (int) $data['rowId'];
    date_default_timezone_set('Asia/Kolkata');

    try {
        // Fetch current call history (always needed for count)
        $stmt = $conn->prepare(
            "SELECT call_history FROM user_remarks
             WHERE upload_data_id = :id AND user_unique_id = :uid"
        );
        $stmt->execute([':id' => $id, ':uid' => $useruniqueId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $call_history   = json_decode($row['call_history'] ?? '[]', true);
        $call_history   = is_array($call_history) ? $call_history : [];

        if ($data['history_call'] === true) {
            // Append new click entry
            $call_history[] = [
                'click_attempted' => count($call_history) + 1,
                'timestamp'       => date('Y-m-d H:i:s'),
            ];

            $stmt = $conn->prepare(
                "UPDATE user_remarks SET call_history = :ch
                 WHERE upload_data_id = :id AND user_unique_id = :uid"
            );
            $stmt->execute([':ch' => json_encode($call_history), ':id' => $id, ':uid' => $useruniqueId]);
        }

        echo json_encode(['status' => 'success', 'total_clicks' => count($call_history)]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}
// Handle the fetch history request
if (isset($data['fetchHistory'])) {
    $id           = (int) $data['rowId'];
    $useruniqueId = $data['user_unique_id'];
    try {
        // Single JOIN query — replaces 2 separate round-trips
        $stmt = $conn->prepare("
            SELECT ur.history, ur.created_at, ur.assigned_by,
                   sud.name, sud.number
            FROM user_remarks ur
            INNER JOIN shi_upload_data sud ON sud.id = ur.upload_data_id
            WHERE ur.upload_data_id = :id
              AND ur.user_unique_id  = :useruniqueId
            ORDER BY ur.id DESC
            LIMIT 1
        ");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':useruniqueId', $useruniqueId);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            echo json_encode([
                'status'       => 'success',
                'history'      => json_decode($row['history'] ?? '[]', true) ?: [],
                'assignedDate' => $row['created_at'],
                'assignedBy'   => $row['assigned_by'],
                'lead_user'    => $row['name'],
                'lead_number'  => $row['number'],
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'No data found']);
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}
// Handle fetchCallHistory request
if (isset($data['fetchCallHistory'])) {
    $id           = (int) $data['rowId'];
    $useruniqueId = $data['user_unique_id'];
    try {
        // Single JOIN query — replaces 2 separate round-trips
        $stmt = $conn->prepare("
            SELECT ur.call_history, ur.created_at, ur.assigned_by,
                   sud.name, sud.number
            FROM user_remarks ur
            INNER JOIN shi_upload_data sud ON sud.id = ur.upload_data_id
            WHERE ur.upload_data_id = :id
              AND ur.user_unique_id  = :useruniqueId
            ORDER BY ur.id DESC
            LIMIT 1
        ");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':useruniqueId', $useruniqueId);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            echo json_encode([
                'status'       => 'success',
                'history'      => json_decode($row['call_history'] ?? '[]', true) ?: [],
                'assignedDate' => $row['created_at']  ?? null,
                'assignedBy'   => $row['assigned_by'] ?? null,
                'lead_user'    => $row['name']         ?? null,
                'lead_number'  => $row['number']       ?? null,
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'No data found']);
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}


if (isset($data['reassign'])) {
    $id             = (int) $data['reassignRowId'];
    $assignUser     = $data['assignUser'];
    $projectName    = $data['projectName'];
    $includeHistory = (bool) $data['includeHistory'];
    $useruniqueId   = $_SESSION['tablename'] ?? null;
    date_default_timezone_set('Asia/Kolkata');

    if (!$useruniqueId) {
        echo json_encode(['status' => 'error', 'message' => 'User unique ID is missing.']);
        exit;
    }

    // Fire-and-forget notification — defined once, called at every success exit
    $sendNotify = function () use ($assignUser, $projectName, $useruniqueId) {
        if (empty($assignUser) || $assignUser === 'unassigned') return;
        try {
            $ch = curl_init('https://notification.mnts.in/api/notify-users');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_HTTPHEADER     => [
                    'Authorization: Bearer ' . $GLOBALS['CRM_API_KEY_SERVER'],
                    'Content-Type: application/json',
                ],
                CURLOPT_POSTFIELDS => json_encode([
                    'title'        => "\xF0\x9F\x93\x8C Lead Assigned by {$useruniqueId}",
                    'body'         => "\xF0\x9F\x93\x8C Lead Assigned by {$useruniqueId}\nProject Name: {$projectName}\n\nPlease follow up promptly.",
                    'user_codes'   => [$assignUser],
                    'url'          => 'https://mnts.in/incentiveapp_integration/userlogin1/userlogin6/user_lead',
                    'project_code' => 'Mnt_reos_nfs',
                ]),
            ]);
            curl_exec($ch);
            curl_close($ch);
        } catch (Exception $ignored) {}
    };

    try {
        $conn->beginTransaction();

        // 1) Lock upload row to serialize operations on this lead
        $stmtLock = $conn->prepare('SELECT id, assign_to_user FROM shi_upload_data WHERE id = :id FOR UPDATE');
        $stmtLock->execute([':id' => $id]);
        if ($stmtLock->rowCount() === 0) throw new Exception('Upload row not found.');
        $uploadRow = $stmtLock->fetch(PDO::FETCH_ASSOC);

        // ── FAST PATH: lead already on the same user in upload_data ──────────
        if ($uploadRow['assign_to_user'] === $assignUser) {
            // Fetch both hidden + active state in one query
            $stmtState = $conn->prepare(
                'SELECT history_h FROM user_remarks
                 WHERE upload_data_id = :id AND user_unique_id = :user
                 ORDER BY history_h ASC LIMIT 2'
            );
            $stmtState->execute([':id' => $id, ':user' => $assignUser]);
            $states    = $stmtState->fetchAll(PDO::FETCH_COLUMN);
            $hasActive = in_array(0, $states, true);
            $hasHidden = in_array(1, $states, true);

            if ($hasHidden) {
                $conn->prepare(
                    'UPDATE user_remarks
                     SET history_h = 0, created_at = NOW(), updated_at = NOW(),
                         assign_project_name = :pn, assigned_by = :ab
                     WHERE upload_data_id = :id AND user_unique_id = :user AND history_h = 1'
                )->execute([':pn' => $projectName, ':ab' => $useruniqueId, ':id' => $id, ':user' => $assignUser]);
                $conn->commit();
                echo json_encode(['status' => 'success', 'message' => 'Lead reassigned to the same user and made visible again.']);
                exit;
            }

            if (!$hasActive) {
                $conn->prepare(
                    "INSERT INTO user_remarks
                     (upload_data_id, user_unique_id, assign_project_name, assigned_by, created_at,
                      status, remarks, history, follow_up_date, follow_up_time,
                      lead_identity, budget, location_status, call_history, history_h)
                     VALUES (:id, :user, :pn, :ab, NOW(),
                             'Pending', '', '[]', NULL, NULL, 'N/A', 'N/A', 'N/A', '[]', 0)"
                )->execute([':id' => $id, ':user' => $assignUser, ':pn' => $projectName, ':ab' => $useruniqueId]);
                $conn->commit();
                echo json_encode(['status' => 'success', 'message' => 'Lead reassigned and new remark created.']);
                exit;
            }

            $conn->commit();
            echo json_encode(['status' => 'success', 'message' => 'Lead already assigned to the specified user.']);
            exit;
        }

        // 2) Auth check — lock the current active remark row
        $accessibleUsers = array_values(array_unique(get_accessible_users_update($conn, $useruniqueId, $userType)));
        if (empty($accessibleUsers)) throw new Exception('You are not authorized to reassign this lead.');

        $ph       = implode(',', array_map(fn($i) => ":u{$i}", array_keys($accessibleUsers)));
        $stmtAuth = $conn->prepare(
            "SELECT ur.id AS remark_id, ur.user_unique_id
             FROM user_remarks ur
             WHERE ur.upload_data_id = :id AND ur.history_h = 0
               AND ur.user_unique_id IN ({$ph})
             LIMIT 1 FOR UPDATE"
        );
        $stmtAuth->bindValue(':id', $id, PDO::PARAM_INT);
        foreach ($accessibleUsers as $i => $u) $stmtAuth->bindValue(":u{$i}", $u, PDO::PARAM_STR);
        $stmtAuth->execute();
        $prevRow = $stmtAuth->fetch(PDO::FETCH_ASSOC);
        if (!$prevRow) throw new Exception('You are not authorized to reassign this lead.');

        $remarkId     = (int) $prevRow['remark_id'];
        $currentOwner = $prevRow['user_unique_id'];
        $sameUser     = (trim($currentOwner) === trim($assignUser));

        // Check target user's remark states (active + hidden) in ONE query
        $stmtTarget = $conn->prepare(
            'SELECT history_h FROM user_remarks
             WHERE upload_data_id = :id AND user_unique_id = :user
             ORDER BY history_h ASC LIMIT 2'
        );
        $stmtTarget->execute([':id' => $id, ':user' => $assignUser]);
        $targetStates = $stmtTarget->fetchAll(PDO::FETCH_COLUMN);
        $existsActive = in_array(0, $targetStates, true);
        $existsHidden = in_array(1, $targetStates, true);

        // Unhide target's hidden remark if no active one exists yet
        if ($existsHidden && !$existsActive) {
            $conn->prepare(
                'UPDATE user_remarks
                 SET history_h = 0, created_at = NOW(), updated_at = NOW(),
                     assign_project_name = :pn, assigned_by = :ab
                 WHERE upload_data_id = :id AND user_unique_id = :user AND history_h = 1'
            )->execute([':pn' => $projectName, ':ab' => $useruniqueId, ':id' => $id, ':user' => $assignUser]);
            $existsActive = true;
        }

        // ── SAME REMARK OWNER ────────────────────────────────────────────────
        if ($sameUser) {
            $conn->prepare('UPDATE shi_upload_data SET assign_to_user = :u WHERE id = :id')
                 ->execute([':u' => $assignUser, ':id' => $id]);
            $conn->prepare(
                'UPDATE user_remarks
                 SET assign_project_name = :pn, assigned_by = :ab,
                     created_at = NOW(), updated_at = NOW()
                 WHERE id = :rid'
            )->execute([':pn' => $projectName, ':ab' => $useruniqueId, ':rid' => $remarkId]);
            $conn->commit();
            $sendNotify();
            echo json_encode(['status' => 'success', 'message' => 'Lead updated for the same user.']);
            exit;
        }

        // ── WITH HISTORY (move) ───────────────────────────────────────────────
        if ($includeHistory) {
            $conn->prepare('UPDATE shi_upload_data SET assign_to_user = :u WHERE id = :id')
                 ->execute([':u' => $assignUser, ':id' => $id]);

            if ($existsActive) {
                // Touch existing active remark for target, archive the old one
                $conn->prepare(
                    'UPDATE user_remarks
                     SET created_at = NOW(), updated_at = NOW(),
                         assign_project_name = :pn, assigned_by = :ab
                     WHERE upload_data_id = :id AND user_unique_id = :user AND history_h = 0'
                )->execute([':pn' => $projectName, ':ab' => $useruniqueId, ':id' => $id, ':user' => $assignUser]);
                $conn->prepare('UPDATE user_remarks SET history_h = 1 WHERE id = :rid AND history_h = 0')
                     ->execute([':rid' => $remarkId]);
                $conn->commit();
                $sendNotify();
                echo json_encode(['status' => 'success', 'message' => 'Lead assigned (existing active remark for target user found). No duplicate created.']);
            } else {
                // Move the locked remark row directly to target user
                $conn->prepare(
                    'UPDATE user_remarks
                     SET user_unique_id = :user, assign_project_name = :pn,
                         assigned_by = :ab, created_at = NOW(), updated_at = NOW()
                     WHERE id = :rid'
                )->execute([':user' => $assignUser, ':pn' => $projectName, ':ab' => $useruniqueId, ':rid' => $remarkId]);
                $conn->commit();
                $sendNotify();
                echo json_encode(['status' => 'success', 'message' => 'Lead reassigned with history (moved).']);
            }
            exit;
        }

        // ── FRESH / NO HISTORY ────────────────────────────────────────────────
        // Archive the old remark
        $conn->prepare('UPDATE user_remarks SET history_h = 1 WHERE id = :rid AND history_h = 0')
             ->execute([':rid' => $remarkId]);

        // Update upload_data ownership
        $conn->prepare('UPDATE shi_upload_data SET assign_to_user = :u WHERE id = :id')
             ->execute([':u' => $assignUser, ':id' => $id]);

        if (!$existsActive) {
            try {
                $conn->prepare(
                    "INSERT INTO user_remarks
                     (upload_data_id, user_unique_id, assign_project_name, assigned_by, created_at,
                      status, remarks, history, follow_up_date, follow_up_time,
                      lead_identity, budget, location_status, call_history, history_h)
                     VALUES (:id, :user, :pn, :ab, NOW(),
                             'Pending', '', '[]', NULL, NULL, 'N/A', 'N/A', 'N/A', '[]', 0)"
                )->execute([':id' => $id, ':user' => $assignUser, ':pn' => $projectName, ':ab' => $useruniqueId]);
            } catch (PDOException $e) {
                if ($e->getCode() !== '23000') throw $e; // ignore duplicate-key, re-throw others
            }
        }

        $conn->commit();
        $sendNotify();
        echo json_encode(['status' => 'success', 'message' => 'Lead reassigned as fresh (duplicate prevented if already existed).']);
        exit;

    } catch (Exception $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'An error occurred: ' . $e->getMessage()]);
        exit;
    }
}


if (isset($data['bulkDelete']) && $data['bulkDelete'] === true) {
    $rowIds = array_map('intval', $data['rowIds'] ?? []);
    $currentUserId = $_SESSION['tablename'] ?? '';
    $currentUserName = $_SESSION['username'] ?? '';

    if ($currentUserId !== 'rahul00761' && $currentUserName !== 'rahul00761') {
        echo json_encode(['status' => 'error', 'message' => 'You are not authorized to delete leads.']);
        exit;
    }

    if (empty($rowIds)) {
        echo json_encode(['status' => 'error', 'message' => 'No leads selected for deletion.']);
        exit;
    }

    try {
        $conn->beginTransaction();

        $placeholders = implode(',', array_fill(0, count($rowIds), '?'));

        // Only remove remarks linked to the selected leads. Do NOT delete the lead rows
        // from `shi_upload_data` here — preserve the main lead records.
        $stmtRemarks = $conn->prepare("DELETE FROM user_remarks WHERE upload_data_id IN ($placeholders)");
        foreach ($rowIds as $index => $id) {
            $stmtRemarks->bindValue($index + 1, $id, PDO::PARAM_INT);
        }
        $stmtRemarks->execute();

        $deletedRemarks = $stmtRemarks->rowCount();

        $conn->commit();

        echo json_encode([
            'status' => 'success',
            'message' => 'Remarks deleted.',
            'deletedRemarksCount' => $deletedRemarks
        ]);
        exit;
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        echo json_encode(['status' => 'error', 'message' => 'Delete failed: ' . $e->getMessage()]);
        exit;
    }
}


//This is function is for BUlk assign Rows
// ── CHECK BULK DUPLICATES ──────────────────────────────────────────────────
// Pre-flight: tells the JS how many selected leads already belong to the
// target user so the UI can offer "Assign All" vs "Assign Unique".
if (isset($data['checkBulkDuplicates']) && $data['checkBulkDuplicates'] === true) {
    $rowIds     = array_map('intval', $data['rowIds'] ?? []);
    $assignUser = trim($data['assignUser'] ?? '');

    if (empty($rowIds) || empty($assignUser)) {
        echo json_encode(['status' => 'ok', 'commonCount' => 0, 'uniqueCount' => 0,
                          'total' => 0, 'commonIds' => [], 'uniqueIds' => []]);
        exit;
    }

    $inPH = implode(',', array_fill(0, count($rowIds), '?'));

    // A lead is "common" when the target user already owns it
    // (assign_to_user match) OR has an active remark (history_h = 0) for it.
    $stmt = $conn->prepare("
        SELECT DISTINCT ud.id
        FROM shi_upload_data ud
        WHERE ud.id IN ({$inPH})
          AND (
            ud.assign_to_user = ?
            OR EXISTS (
                SELECT 1 FROM user_remarks ur
                WHERE ur.upload_data_id = ud.id
                  AND ur.user_unique_id  = ?
                  AND ur.history_h       = 0
            )
          )
    ");

    $p = 1;
    foreach ($rowIds as $rid) $stmt->bindValue($p++, $rid, PDO::PARAM_INT);
    $stmt->bindValue($p++, $assignUser, PDO::PARAM_STR);
    $stmt->bindValue($p,   $assignUser, PDO::PARAM_STR);
    $stmt->execute();

    $commonIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN, 0));
    $uniqueIds = array_values(array_diff($rowIds, $commonIds));

    echo json_encode([
        'status'      => 'ok',
        'commonCount' => count($commonIds),
        'uniqueCount' => count($uniqueIds),
        'total'       => count($rowIds),
        'commonIds'   => $commonIds,
        'uniqueIds'   => $uniqueIds,
    ]);
    exit;
}

if (isset($data['bulkAssign']) && $data['bulkAssign'] === true) {
    $rowIds         = array_map('intval', $data['rowIds'] ?? []);
    $assignUser     = $data['assignUser'];
    $projectName    = $data['projectName'];
    $includeHistory = (bool) $data['includeHistory'];
    $useruniqueId   = $_SESSION['tablename'] ?? null;
    $userType       = $_SESSION['user_type'] ?? ($userType ?? '');
    $userRole       = $_SESSION['role']      ?? ($userRole ?? '');
    $isElevated     = in_array(strtolower((string) $userRole), ['promoter', 'business head', 'manager', 'team lead'], true)
                   || in_array(strtolower((string) $userType), ['promoter', 'business head', 'manager', 'team lead'], true);
    date_default_timezone_set('Asia/Kolkata');


    if (!$useruniqueId) {
        echo json_encode(['status' => 'error', 'message' => 'User unique ID is missing.']);
        exit;
    }
    if (empty($rowIds)) {
        echo json_encode(['status' => 'error', 'message' => 'No rows to assign.']);
        exit;
    }

    try {
        $conn->beginTransaction();

        // Lock all upload_data rows for these ids (serialize)
        $inPlaceholders = implode(',', array_fill(0, count($rowIds), '?'));
        $stmtLock = $conn->prepare("SELECT id FROM shi_upload_data WHERE id IN ($inPlaceholders) FOR UPDATE");
        foreach ($rowIds as $k => $rid)
            $stmtLock->bindValue($k + 1, $rid, PDO::PARAM_INT);
        $stmtLock->execute();

        // Pre-fetch existing active remarks for target assignUser among these ids
        $stmtExisting = $conn->prepare("
            SELECT upload_data_id 
            FROM user_remarks 
            WHERE upload_data_id IN ($inPlaceholders)
              AND user_unique_id = ?
              AND history_h = 0
        ");
        // bind ids (positional parameters)
        $paramIndex = 1;
        foreach ($rowIds as $rid) {
            $stmtExisting->bindValue($paramIndex++, $rid, PDO::PARAM_INT);
        }
        // bind assignUser as the next positional parameter
        $stmtExisting->bindValue($paramIndex, $assignUser);
        $stmtExisting->execute();
        $alreadyAssigned = $stmtExisting->fetchAll(PDO::FETCH_COLUMN, 0);
        $alreadyAssigned = array_flip($alreadyAssigned); // quick lookup

        // Pre-fetch hidden remarks (history_h = 1) for target user - these need to be reset
        $stmtHidden = $conn->prepare("
            SELECT upload_data_id 
            FROM user_remarks 
            WHERE upload_data_id IN ($inPlaceholders)
              AND user_unique_id = ?
              AND history_h = 1
        ");
        $paramIndex = 1;
        foreach ($rowIds as $rid) {
            $stmtHidden->bindValue($paramIndex++, $rid, PDO::PARAM_INT);
        }
        $stmtHidden->bindValue($paramIndex, $assignUser);
        $stmtHidden->execute();
        $hiddenRemarks = $stmtHidden->fetchAll(PDO::FETCH_COLUMN, 0);
        $hiddenRemarks = array_flip($hiddenRemarks); // quick lookup

        // Reset history_h to 0 for hidden remarks of target user
        // IMPORTANT: Only reset hidden remarks where there is NO existing active remark
        // for the same user+lead. Without this guard, a stale old hidden remark (e.g. "Fake")
        // would be resurrected and conflict with an already active remark (e.g. "Not Interested"),
        // causing wrong statuses to appear after bulk assign.
        if (!empty($hiddenRemarks)) {
            $hiddenIds = array_keys($hiddenRemarks);
            $hiddenPlaceholders = implode(',', array_fill(0, count($hiddenIds), '?'));
            $stmtResetHidden = $conn->prepare("
                UPDATE user_remarks SET history_h = 0, created_at = NOW(), updated_at = NOW()
                WHERE upload_data_id IN ($hiddenPlaceholders)
                  AND user_unique_id = ?
                  AND history_h = 1
                  AND NOT EXISTS (
                      SELECT 1 FROM user_remarks AS ur_check
                      WHERE ur_check.upload_data_id = user_remarks.upload_data_id
                        AND ur_check.user_unique_id = user_remarks.user_unique_id
                        AND ur_check.history_h = 0
                  )
            ");
            $paramIndex = 1;
            foreach ($hiddenIds as $hid) {
                $stmtResetHidden->bindValue($paramIndex++, $hid, PDO::PARAM_INT);
            }
            $stmtResetHidden->bindValue($paramIndex, $assignUser);
            $stmtResetHidden->execute();

            // Re-fetch which ones were actually reset (some may have been skipped)
            $stmtVerifyReset = $conn->prepare("
                SELECT upload_data_id FROM user_remarks
                WHERE upload_data_id IN ($hiddenPlaceholders) AND user_unique_id = ? AND history_h = 0
            ");
            $paramIndex = 1;
            foreach ($hiddenIds as $hid)
                $stmtVerifyReset->bindValue($paramIndex++, $hid, PDO::PARAM_INT);
            $stmtVerifyReset->bindValue($paramIndex, $assignUser);
            $stmtVerifyReset->execute();
            foreach ($stmtVerifyReset->fetchAll(PDO::FETCH_COLUMN, 0) as $hid)
                $alreadyAssigned[$hid] = true;
        }

        // ── Prepare reusable statements once (auth type is constant per request) ──
        if ($isElevated) {
            $stmtAuth = $conn->prepare(
                "SELECT id, user_unique_id FROM user_remarks ur
                 WHERE upload_data_id = :id AND history_h = 0
                 ORDER BY id DESC LIMIT 1 FOR UPDATE"
            );
        } elseif ($userType === 'manager' || $userType === 'ceo') {
            $stmtAuth = $conn->prepare(
                "SELECT id, user_unique_id FROM user_remarks ur
                 WHERE upload_data_id = :id
                   AND (ur.user_unique_id = :userUniqueId
                        OR ur.user_unique_id IN (
                            SELECT tablename FROM accounts WHERE FIND_IN_SET(:userUniqueId2, assign_user) > 0
                        ))
                 LIMIT 1 FOR UPDATE"
            );
        } else {
            $stmtAuth = $conn->prepare(
                "SELECT id, user_unique_id FROM user_remarks ur
                 WHERE upload_data_id = :id AND ur.user_unique_id = :userUniqueId
                 LIMIT 1 FOR UPDATE"
            );
        }

        $stmtMarkHistory  = $conn->prepare("UPDATE user_remarks SET history_h = 1 WHERE id = :remark_id AND history_h = 0");
        $stmtMoveRemark   = $conn->prepare("
            UPDATE user_remarks
            SET user_unique_id = :assign_user, assign_project_name = :project_name,
                assigned_by = :assigned_by, created_at = NOW(), updated_at = NOW()
            WHERE id = :remark_id
        ");
        $stmtSameOwner    = $conn->prepare("
            UPDATE user_remarks
            SET assign_project_name = :project_name, assigned_by = :assigned_by,
                created_at = NOW(), updated_at = NOW()
            WHERE id = :remark_id
        ");
        $stmtTouchRemark  = $conn->prepare("
            UPDATE user_remarks
            SET created_at = NOW(), updated_at = NOW(),
                assign_project_name = :project_name, assigned_by = :assigned_by
            WHERE upload_data_id = :id AND user_unique_id = :assign_user AND history_h = 0
        ");
        $stmtInsertRemark = $conn->prepare("
            INSERT INTO user_remarks
              (upload_data_id, user_unique_id, assign_project_name, assigned_by,
               created_at, status, remarks, history, follow_up_date, follow_up_time,
               lead_identity, budget, location_status, call_history, history_h)
            VALUES
              (:id, :assign_user, :project_name, :assigned_by, :created_at,
               'Pending', '', '[]', NULL, NULL, 'N/A', 'N/A', 'N/A', '[]', 0)
        ");

        $updatedIds = []; // collect for batch shi_upload_data update

        foreach ($rowIds as $id) {
            // Authorization
            $stmtAuth->bindValue(':id', $id, PDO::PARAM_INT);
            if (!$isElevated) {
                $stmtAuth->bindValue(':userUniqueId', $useruniqueId);
                if ($userType === 'manager' || $userType === 'ceo')
                    $stmtAuth->bindValue(':userUniqueId2', $useruniqueId);
            }
            $stmtAuth->execute();
            if ($stmtAuth->rowCount() === 0) continue; // unauthorized

            $orig         = $stmtAuth->fetch(PDO::FETCH_ASSOC);
            $remarkId     = (int) $orig['id'];
            $currentOwner = $orig['user_unique_id'];

            // Same owner — just refresh project/assigned_by, no history change
            if (trim($currentOwner) === trim($assignUser)) {
                $stmtSameOwner->execute([':project_name' => $projectName, ':assigned_by' => $useruniqueId, ':remark_id' => $remarkId]);
                $updatedIds[] = $id;
                continue;
            }

            if ($includeHistory) {
                if (isset($alreadyAssigned[$id])) {
                    // Target already has active remark → touch it, archive current owner's
                    $stmtTouchRemark->execute([':project_name' => $projectName, ':assigned_by' => $useruniqueId, ':id' => $id, ':assign_user' => $assignUser]);
                    $stmtMarkHistory->execute([':remark_id' => $remarkId]);
                } else {
                    // Move remark to target user
                    $stmtMoveRemark->execute([':assign_user' => $assignUser, ':project_name' => $projectName, ':assigned_by' => $useruniqueId, ':remark_id' => $remarkId]);
                }
            } else {
                // Fresh assign → archive old, then insert or touch target's remark
                $stmtMarkHistory->execute([':remark_id' => $remarkId]);

                if (isset($alreadyAssigned[$id])) {
                    $stmtTouchRemark->execute([':project_name' => $projectName, ':assigned_by' => $useruniqueId, ':id' => $id, ':assign_user' => $assignUser]);
                } else {
                    try {
                        $stmtInsertRemark->execute([':id' => $id, ':assign_user' => $assignUser, ':project_name' => $projectName, ':assigned_by' => $useruniqueId, ':created_at' => date('Y-m-d H:i:s')]);
                    } catch (PDOException $e) {
                        if ($e->getCode() != '23000') throw $e;
                        // duplicate key from concurrent request — ignore
                    }
                }
            }

            $updatedIds[] = $id;
        }

        // ── Batch update shi_upload_data in one round-trip ───────────────────
        if (!empty($updatedIds)) {
            $updPlaceholders = implode(',', array_fill(0, count($updatedIds), '?'));
            $stmtBatchUpd    = $conn->prepare("UPDATE shi_upload_data SET assign_to_user = ? WHERE id IN ($updPlaceholders)");
            $stmtBatchUpd->bindValue(1, $assignUser);
            foreach ($updatedIds as $k => $uid)
                $stmtBatchUpd->bindValue($k + 2, $uid, PDO::PARAM_INT);
            $stmtBatchUpd->execute();
        }

        $conn->commit();


        // ── Notification (fire-and-forget, outside transaction) ──────────────
        if (!empty($assignUser) && $assignUser !== 'unassigned') {
            try {
                $numLeads      = count($rowIds);
                $notifyPayload = [
                    'title'        => "📌 Lead Assigned by {$useruniqueId}",
                    'body'         => "📌 Lead Assigned by {$useruniqueId}\nNumber of leads assigned: {$numLeads}\nProject Name: {$projectName}\n\nPlease follow up promptly.",
                    'user_codes'   => [$assignUser],
                    'url'          => 'https://mnts.in/incentiveapp_integration/userlogin1/userlogin6/user_lead',
                    'project_code' => 'Mnt_reos_nfs',
                ];
                $ch = curl_init('https://notification.mnts.in/api/notify-users');
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST           => true,
                    CURLOPT_HTTPHEADER     => ["Authorization: Bearer $CRM_API_KEY_SERVER", 'Content-Type: application/json'],
                    CURLOPT_POSTFIELDS     => json_encode($notifyPayload),
                    CURLOPT_TIMEOUT        => 10,
                ]);
                curl_exec($ch);
                curl_close($ch);
            } catch (Exception $e) {
                // notification failure must never block the response
            }
        }

        echo json_encode(['status' => 'success', 'message' => 'Leads assigned successfully.']);
    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'An error occurred: ' . $e->getMessage()]);
    }
    exit;
}

// THis is for fitch rows based on the followup date
if (isset($data['fetchFollowUps'])) {
    date_default_timezone_set('Asia/Kolkata'); // Set your desired timezone
    $todayDate = date('Y-m-d'); // Get today's date

    try {
        // Fetch rows where follow-up date is today or earlier
        $query = "SELECT * FROM user_remarks WHERE follow_up_date = :todayDate";
        $stmt = $conn->prepare($query);
        $stmt->bindValue(':todayDate', $todayDate);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['status' => 'success', 'rows' => $rows]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// ============================================================================
// GET TODAY'S FOLLOWUP LEADS FOR MANDATORY POPUP
// This endpoint returns all leads that have follow-up scheduled for today
// and belong to the current logged-in user. Used by the Today's Followup Popup.
// ============================================================================
if (isset($_GET['get_todays_followup_leads']) && $_GET['get_todays_followup_leads'] == '1') {
    date_default_timezone_set('Asia/Kolkata');
    $todayDate = date('Y-m-d');

    try {
        // Get the current user's unique ID from session
        $currentUserTablename = $_SESSION['tablename'] ?? $useruniqueId ?? null;

        if (!$currentUserTablename) {
            echo json_encode(['status' => 'error', 'message' => 'User not authenticated', 'leads' => []]);
            exit;
        }

        // Fetch leads where:
        // 1. follow_up_date is today
        // 2. Belongs to the current user
        // 3. Status is one of the active follow-up statuses
        // 4. Not a historical record (history_h = 0)
        $query = "
            SELECT 
                ur.id,
                ur.upload_data_id,
                ur.user_unique_id,
                ur.status,
                ur.remarks,
                ur.follow_up_date,
                ur.follow_up_time,
                ur.assign_project_name,
                ur.lead_identity,
                ur.budget,
                ur.location_status,
                ur.created_at,
                sud.name,
                sud.email,
                sud.number,
                sud.source_of_lead,
                sud.location
            FROM user_remarks ur
            INNER JOIN shi_upload_data sud ON sud.id = ur.upload_data_id
            WHERE ur.user_unique_id = :user_id
            AND ur.history_h = 0
            AND ur.follow_up_date = :todayDate
            AND ur.status IN ('Follow Up', 'Interested', 'Call Back', 'RNR', 'Fix Site Visit', 'Pending', 'Site Visit Done', 'VC Done', 'Re site visit', 'Not Connected')
            ORDER BY ur.follow_up_time ASC, ur.created_at DESC
        ";

        $stmt = $conn->prepare($query);
        $stmt->bindValue(':user_id', $currentUserTablename, PDO::PARAM_STR);
        $stmt->bindValue(':todayDate', $todayDate, PDO::PARAM_STR);
        $stmt->execute();
        $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

        error_log("📋 Today's Followup Leads for user {$currentUserTablename}: " . count($leads) . " leads found");

        echo json_encode([
            'status' => 'success',
            'leads' => $leads,
            'count' => count($leads),
            'date' => $todayDate,
            'user' => $currentUserTablename
        ]);
    } catch (Exception $e) {
        error_log("❌ Error fetching today's followup leads: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => $e->getMessage(), 'leads' => []]);
    }
    exit;
}

// Get The Users In DropDown
if (isset($_GET['get_users']) && $_GET['get_users'] == 1) {

    $currentUserTablename = $_SESSION['tablename'] ?? $useruniqueId ?? null;

    if (!$currentUserTablename) {
        echo "<option value=''>No users available</option>";
        exit;
    }

    $getAllUsers = isset($_GET['all_users']) && $_GET['all_users'] == 1;

    try {

        if ($getAllUsers) {

            $stmt = $conn->prepare("
                SELECT tablename, username, user_type
                FROM accounts
                WHERE is_active = 1
                ORDER BY username
            ");
            $stmt->execute();

        } else {

            $stmt = $conn->prepare("
                SELECT user_type
                FROM accounts
                WHERE tablename = :tablename
                LIMIT 1
            ");
            $stmt->execute([':tablename' => $currentUserTablename]);
            $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);

            $role = strtolower(trim($currentUser['user_type'] ?? 'user'));

            /* ===============================
             HIERARCHY FILTERING
             =============================== */

            if ($role == 'promoter') {

                $stmt = $conn->prepare("
                    SELECT tablename, username, user_type
                    FROM accounts
                    WHERE is_active = 1
                    ORDER BY username
                ");
                $stmt->execute();

            } elseif ($role == 'business head' || $role == 'business_head') {

                $stmt = $conn->prepare("
                    SELECT tablename, username, user_type
                    FROM accounts
                    WHERE user_type != 'promoter'
                    AND is_active = 1
                    ORDER BY username
                ");
                $stmt->execute();

            } elseif ($role == 'manager') {

                // Include full downline (team leads + their users), not only direct reports.
                $subordinates = get_subordinate_users_update($conn, $currentUserTablename, $role);
                if (!empty($subordinates)) {
                    $placeholders = implode(',', array_fill(0, count($subordinates), '?'));
                    $stmt = $conn->prepare("
                        SELECT tablename, username, user_type
                        FROM accounts
                        WHERE tablename IN ($placeholders)
                        AND is_active = 1
                        ORDER BY username
                    ");
                    $stmt->execute(array_values($subordinates));
                } else {
                    $stmt = $conn->prepare("
                        SELECT tablename, username, user_type
                        FROM accounts
                        WHERE 1 = 0
                    ");
                    $stmt->execute();
                }

            } elseif ($role == 'team lead' || $role == 'team_lead') {

                $subordinates = get_subordinate_users_update($conn, $currentUserTablename, $role);
                if (!empty($subordinates)) {
                    $placeholders = implode(',', array_fill(0, count($subordinates), '?'));
                    $stmt = $conn->prepare("
                        SELECT tablename, username, user_type
                        FROM accounts
                        WHERE tablename IN ($placeholders)
                        AND is_active = 1
                        ORDER BY username
                    ");
                    $stmt->execute(array_values($subordinates));
                } else {
                    $stmt = $conn->prepare("
                        SELECT tablename, username, user_type
                        FROM accounts
                        WHERE 1 = 0
                    ");
                    $stmt->execute();
                }

            } else {

                $stmt = $conn->prepare("
                    SELECT tablename, username, user_type
                    FROM accounts
                    WHERE tablename = :user
                    AND is_active = 1
                ");
                $stmt->execute([':user' => $currentUserTablename]);
            }
        }

        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$users) {
            echo "<option value=''>No users found</option>";
            exit;
        }

        /* ===============================
         ROLE NORMALIZATION
         =============================== */

        $normalizeRole = function (?string $rawType): string {
            $s = strtolower(trim((string) $rawType));
            switch ($s) {
                case 'ceo':
                case 'promoter':
                    return 'promoter';
                case 'business_head':
                case 'business head':
                case 'bh':
                    return 'business_head';
                case 'manager':
                case 'm':
                    return 'manager';
                case 'team_lead':
                case 'team lead':
                case 'tl':
                case 'team_leader':
                    return 'team_lead';
                default:
                    return 'user';
            }
        };

        $roleToLabel = [
            'promoter' => 'P',
            'business_head' => 'BH',
            'manager' => 'M',
            'team_lead' => 'TL',
            'user' => 'U',
        ];

        /* ===============================
         OUTPUT OPTIONS
         =============================== */

        foreach ($users as $user) {

            $tablename = htmlspecialchars($user['tablename'], ENT_QUOTES, 'UTF-8');
            $usernameRaw = $user['username'] ?? $tablename;
            $username = htmlspecialchars($usernameRaw, ENT_QUOTES, 'UTF-8');

            $normalizedRole = $normalizeRole($user['user_type'] ?? '');
            $posLabel = $roleToLabel[$normalizedRole] ?? '';

            // Avoid duplicate label
            $displayName = $username;
            if ($posLabel) {
                $endsWithCode = preg_match('/\s(' . preg_quote($posLabel, '/') . ')$/i', $usernameRaw);
                if (!$endsWithCode) {
                    $displayName = "{$username} - {$posLabel}";
                }
            }

            echo "<option value='{$tablename}' data-position='" .
                htmlspecialchars($posLabel, ENT_QUOTES, 'UTF-8') .
                "'>{$displayName}</option>";
        }

    } catch (Throwable $e) {

        error_log("User load error: " . $e->getMessage());
        echo "<option value=''>Error loading users</option>";
    }

    exit;
}

// Remote search for header dropdown options (unique values across full dataset)
// Used by Leads page header filters so search works beyond lazily loaded items.
if (isset($_GET['get_unique_values']) && (int) $_GET['get_unique_values'] === 1) {
    try {
        $columnIndex = isset($_GET['columnIndex']) ? (int) $_GET['columnIndex'] : 0;
        $fieldKey = isset($_GET['fieldKey']) ? trim((string) $_GET['fieldKey']) : '';
        $q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
        $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        $perPage = isset($_GET['perPage']) ? max(5, min(200, (int) $_GET['perPage'])) : 40;
        $offset = ($page - 1) * $perPage;

        // Same context params as table fetching so results match current view
        $searchQuery = isset($_GET['searchQuery']) ? (string) $_GET['searchQuery'] : '';
        $filter = isset($_GET['filter']) ? trim((string) $_GET['filter']) : '';
        if (in_array($filter, ['followupLeads', 'followup', 'follow-up', 'FollowUp'], true)) {
            $filter = 'followLeads';
        }
        $multiFilters = isset($_GET['multiFilters']) ? json_decode($_GET['multiFilters'], true) : [];
        if (!is_array($multiFilters))
            $multiFilters = [];
        $managerToggle = isset($_GET['managerToggle']) ? (int) $_GET['managerToggle'] : 0;
        $filterUserParam = isset($_GET['filterUser']) ? trim((string) $_GET['filterUser']) : '';
        $managerViewParam = isset($_GET['managerView']) ? filter_var($_GET['managerView'], FILTER_VALIDATE_BOOLEAN) : false;

        // Map dropdown request -> SQL field.
        // Supports:
        // - header dropdowns via columnIndex
        // - filter modal suggestions via fieldKey
        $fieldKeyMap = [
            'name' => ['sql' => 'sud.name', 'selfKey' => 'name'],
            'email' => ['sql' => 'sud.email', 'selfKey' => 'email'],
            'number' => ['sql' => 'sud.number', 'selfKey' => 'number'],
            'location' => ['sql' => 'ur.location_status', 'selfKey' => 'location'],
            'budget' => ['sql' => 'ur.budget', 'selfKey' => 'budget'],
            'remarks' => ['sql' => 'ur.remarks', 'selfKey' => 'remarks'],
            'updated_at' => ['sql' => 'ur.updated_at', 'selfKey' => 'updated_at'],
            'source_of_lead' => ['sql' => 'sud.source_of_lead', 'selfKey' => 'source_of_lead'],
            'status' => ['sql' => 'ur.status', 'selfKey' => 'status'],
            'assign_project_name' => ['sql' => 'ur.assign_project_name', 'selfKey' => 'assign_project_name'],
            'user' => ['sql' => 'ur.user_unique_id', 'selfKey' => 'user'],
            'lead_identity' => ['sql' => 'ur.lead_identity', 'selfKey' => 'lead_identity'],
            // header-only / special
            'id' => ['sql' => 'sud.id', 'selfKey' => 'id'],
            'created_at' => ['sql' => 'ur.created_at', 'selfKey' => 'created_at'],
        ];
        $columnIndexToFieldKey = [
            1 => 'name',
            2 => 'assign_project_name',
            3 => 'id',
            4 => 'created_at',
            5 => 'email',
            6 => 'location',
            7 => 'budget',
            8 => 'remarks',
            9 => 'updated_at',
            10 => 'user',
            11 => 'source_of_lead',
        ];

        $resolvedKey = '';
        if ($fieldKey !== '' && isset($fieldKeyMap[$fieldKey])) {
            $resolvedKey = $fieldKey;
        } elseif (isset($columnIndexToFieldKey[$columnIndex])) {
            $resolvedKey = $columnIndexToFieldKey[$columnIndex];
        }
        if ($resolvedKey === '' || !isset($fieldKeyMap[$resolvedKey])) {
            echo json_encode(['values' => [], 'hasMore' => false]);
            exit;
        }
        $valueField = $fieldKeyMap[$resolvedKey]['sql'];

        if ($resolvedKey === 'status') {
            $allStatuses = [
                'Pending',
                'Fake',
                'RNR',
                'Call Back',
                'Already Booked',
                'Not Interested',
                'Interested',
                'EOI',
                'Fix Site Visit',
                'Site Visit Done',
                'VC Done',
                'Converted',
                'Re site visit',
                'NQFTP',
                'Not Connected'
            ];
            $matchedStatuses = [];
            foreach ($allStatuses as $s) {
                if ($q === '' || stripos($s, $q) !== false) {
                    $matchedStatuses[] = $s;
                }
            }
            sort($matchedStatuses);

            $pagedStatuses = array_slice($matchedStatuses, $offset, $perPage);

            echo json_encode(['values' => $pagedStatuses, 'hasMore' => (count($matchedStatuses) > $offset + $perPage)]);
            exit;
        }

        // Remove self-filter so user can still see all options while searching that dropdown
        $selfKey = $fieldKeyMap[$resolvedKey]['selfKey'] ?? '';
        if ($selfKey !== '' && isset($multiFilters[$selfKey])) {
            unset($multiFilters[$selfKey]);
        }

        // OPTIMIZED: Cache user role lookup (used in multiple places)
        static $userRoleCacheUnique = [];
        $cacheKeyUnique = $useruniqueId;
        if (!isset($userRoleCacheUnique[$cacheKeyUnique])) {
            $stmt = $conn->prepare("SELECT user_type FROM accounts WHERE tablename = :tablename LIMIT 1");
            $stmt->bindParam(':tablename', $useruniqueId, PDO::PARAM_STR);
            $stmt->execute();
            $currentUserRow = $stmt->fetch(PDO::FETCH_ASSOC);
            $userRoleCacheUnique[$cacheKeyUnique] = $currentUserRow['user_type'] ?? 'user';
        }
        $currentUserType = $userRoleCacheUnique[$cacheKeyUnique];

        $normalizedRole = normalize_role_update($currentUserType);
        $canUseManagerToggle = ($normalizedRole !== 'user');

        // OPTIMIZED: Cache accessible users (expensive operation)
        static $accessibleUsersCacheUnique = [];
        $usersCacheKeyUnique = $useruniqueId . '_' . $currentUserType;
        if (!isset($accessibleUsersCacheUnique[$usersCacheKeyUnique])) {
            $accessibleUsersCacheUnique[$usersCacheKeyUnique] = get_accessible_users_update($conn, $useruniqueId, $currentUserType);
        }
        $accessibleUsers = $accessibleUsersCacheUnique[$usersCacheKeyUnique];

        $whereClauses = [];
        $bindings = [];

        $forceSpecificUser = false;
        $useAccessibleUsers = false;
        if ($filterUserParam !== '' && in_array($filterUserParam, $accessibleUsers, true)) {
            $whereClauses[] = "ur.user_unique_id = :forcedUser";
            $bindings[':forcedUser'] = [$filterUserParam, PDO::PARAM_STR];
            $forceSpecificUser = true;
        } elseif ($managerToggle === 1 && $canUseManagerToggle) {
            $subordinateUsers = array_values(array_filter($accessibleUsers, function ($u) use ($useruniqueId) {
                return $u !== $useruniqueId;
            }));
            if (count($subordinateUsers) > 0) {
                $ph = [];
                foreach ($subordinateUsers as $i => $u) {
                    $p = ":accessible_user_$i";
                    $ph[] = $p;
                    $bindings[$p] = [$u, PDO::PARAM_STR];
                }
                $whereClauses[] = "ur.user_unique_id IN (" . implode(',', $ph) . ")";
                $useAccessibleUsers = true;
            } else {
                $whereClauses[] = "ur.user_unique_id = :userUniqueId";
                $bindings[':userUniqueId'] = [$useruniqueId, PDO::PARAM_STR];
            }
        } else {
            $whereClauses[] = "ur.user_unique_id = :userUniqueId";
            $bindings[':userUniqueId'] = [$useruniqueId, PDO::PARAM_STR];
        }

        // Apply quick filter (same set as table; keep it minimal here)
        $filterConditions = [
            'myLeads' => "ur.user_unique_id = :userUniqueId",
            'bookedLeads' => "ur.status = 'Converted'",
            'droppedLeads' => "ur.status IN ('Not Interested', 'Fake')",
            'activeLeads' => "ur.status NOT IN ('Not Interested', 'Fake', 'Already Booked', 'Converted') AND ur.status IS NOT NULL AND ur.status != ''",
            'freshLeads' => "ur.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)",
            'pendingLeads' => "ur.status = 'Pending'",
            'followLeads' => "LOWER(TRIM(ur.status)) = 'follow up'",
            'overdueLeads' => "ur.status IN ('Follow Up', 'RNR', 'Call Back', 'Pending', 'Interested', 'Fix Site Visit', 'Site Visit Done', 'VC Done', 'Re site visit', 'Not Connected') AND ur.status != 'Converted' AND ur.status != 'Already Booked' AND ur.follow_up_date IS NOT NULL AND ur.follow_up_time IS NOT NULL AND CONCAT(ur.follow_up_date, ' ', ur.follow_up_time) < DATE_SUB(NOW(), INTERVAL 1 DAY)",
            'paidAds' => "LOWER(TRIM(sud.source_of_lead)) IN ('google ads', 'facebook ads', 'magicbricks ads', '99acres ads', 'housing.com ads')",
            'SHI_D' => "(sud.source_of_lead IS NULL OR LOWER(TRIM(sud.source_of_lead)) NOT IN ('google ads', 'facebook ads', 'magicbricks ads', '99acres ads', 'housing.com ads'))",
        ];
        if ($filter !== '' && isset($filterConditions[$filter])) {
            // Skip myLeads in team view; table does similar to avoid binding issues
            if (!($filter === 'myLeads' && $managerToggle === 1 && $canUseManagerToggle) && !$forceSpecificUser) {
                $whereClauses[] = $filterConditions[$filter];
            }
        }

        // Date range from multiFilters if present
        $startDate = !empty($multiFilters['start_date']) ? $multiFilters['start_date'] . ' 00:00:00' : null;
        $endDate = !empty($multiFilters['end_date']) ? $multiFilters['end_date'] . ' 23:59:59' : null;
        if (!empty($startDate) && !empty($endDate)) {
            $whereClauses[] = "ur.created_at BETWEEN :startDate AND :endDate";
            $bindings[':startDate'] = [$startDate, PDO::PARAM_STR];
            $bindings[':endDate'] = [$endDate, PDO::PARAM_STR];
        }
        unset($multiFilters['start_date'], $multiFilters['end_date']);

        // MultiFilters - OPTIMIZED: All columns now supported
        $allowedFilters = [
            'name' => 'sud.name',
            'email' => 'sud.email',
            'id' => 'sud.id', // NEW: ID column
            'created_at' => 'ur.created_at', // NEW: Created At column
            'number' => 'sud.number',
            'location' => 'ur.location_status',
            'budget' => 'ur.budget',
            'remarks' => 'ur.remarks',
            'updated_at' => 'ur.updated_at',
            'source_of_lead' => 'LOWER(TRIM(sud.source_of_lead))',
            'status' => 'ur.status',
            'assign_project_name' => 'ur.assign_project_name',
            'user' => 'ur.user_unique_id',
            'lead_identity' => 'ur.lead_identity',
        ];

        foreach ($multiFilters as $key => $value) {
            if ($value === '' || $value === null || !isset($allowedFilters[$key]))
                continue;
            $field = $allowedFilters[$key];

            if ($key === 'assign_project_name') {
                $values = is_array($value) ? $value : [$value];
            } else {
                $normalizedValue = is_array($value) ? implode(',', $value) : (string) $value;
                $values = (strpos($normalizedValue, ',') !== false)
                    ? array_values(array_filter(array_map('trim', explode(',', $normalizedValue)), function ($v) {
                        return $v !== '';
                    }))
                    : [$normalizedValue];
            }

            $values = array_map(function ($v) {
                return is_string($v) ? trim($v) : $v;
            }, $values);
            $values = array_map(function ($v) {
                return is_string($v) ? trim($v) : $v;
            }, $values);
            $values = array_map(function ($v) {
                return is_string($v) ? trim($v) : $v;
            }, $values);
            $values = array_map(function ($v) {
                return is_string($v) ? trim($v) : $v;
            }, $values);
            $values = array_values(array_filter($values, function ($v) {
                return $v !== '' && $v !== null;
            }));
            if (empty($values))
                continue;

            if (count($values) > 1) {
                $ph = [];
                foreach ($values as $i => $val) {
                    $p = ":filter_{$key}_{$i}";
                    $ph[] = $p;
                    $bindings[$p] = [$val, PDO::PARAM_STR];
                }
                $whereClauses[] = "$field IN (" . implode(',', $ph) . ")";
            } else {
                $p = ":filter_{$key}";
                $bindings[$p] = [
                    ($key === 'source_of_lead') ? ('%' . strtolower(trim($values[0])) . '%') : ('%' . $values[0] . '%'),
                    PDO::PARAM_STR
                ];
                $whereClauses[] = "$field LIKE $p";
            }
        }

        // Global table searchQuery (match table behavior)
        if ($searchQuery !== '') {
            $whereClauses[] = "(
                sud.name LIKE :global_search OR
                sud.email LIKE :global_search OR
                sud.number LIKE :global_search OR
                ur.assign_project_name LIKE :global_search OR
                ur.user_unique_id LIKE :global_search OR
                sud.source_of_lead LIKE :global_search
            )";
            $bindings[':global_search'] = ['%' . $searchQuery . '%', PDO::PARAM_STR];
        }

        // Apply option query 'q' against the value field
        if ($q !== '') {
            if ($resolvedKey === 'source_of_lead') {
                $whereClauses[] = "LOWER(TRIM($valueField)) LIKE :q";
                $bindings[':q'] = ['%' . strtolower(trim($q)) . '%', PDO::PARAM_STR];
            } else {
                $whereClauses[] = "$valueField LIKE :q";
                $bindings[':q'] = ['%' . $q . '%', PDO::PARAM_STR];
            }
        }

        // OPTIMIZED: Build query with history_h filter first for performance
        $sql = "
            SELECT DISTINCT $valueField AS value
            FROM shi_upload_data sud
            LEFT JOIN user_remarks ur ON sud.id = ur.upload_data_id AND ur.history_h = 0
        ";
        // OPTIMIZED: Add history_h filter first, then other conditions
        $allWhereClauses = ["ur.history_h = 0", "$valueField IS NOT NULL", "TRIM($valueField) <> ''"];
        if (!empty($whereClauses)) {
            $allWhereClauses = array_merge($allWhereClauses, $whereClauses);
        }
        $sql .= " WHERE " . implode(' AND ', $allWhereClauses);
        $sql .= " ORDER BY value ASC LIMIT :limit OFFSET :offset";

        $stmt = $conn->prepare($sql);
        foreach ($bindings as $param => $info) {
            [$val, $type] = $info;
            $stmt->bindValue($param, $val, $type);
        }
        $stmt->bindValue(':limit', (int) $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int) $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $values = [];
        foreach ($rows as $r) {
            $v = isset($r['value']) ? trim((string) $r['value']) : '';
            if ($v !== '')
                $values[] = $v;
        }

        // "hasMore" probe
        $hasMore = count($values) === $perPage;
        echo json_encode(['values' => $values, 'hasMore' => $hasMore]);
    } catch (Exception $e) {
        echo json_encode(['values' => [], 'hasMore' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ============================================================================
// BOOKINGS FILTER: Get unique values from admintable (paginated + searchable)
// Used by bookings filter dropdowns — same pattern as get_unique_values above.
// ============================================================================
if (isset($_GET['get_booking_unique_values']) && (int) $_GET['get_booking_unique_values'] === 1) {
    try {
        $fieldKey = isset($_GET['fieldKey']) ? trim((string) $_GET['fieldKey']) : '';
        $q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
        $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        $perPage = isset($_GET['perPage']) ? max(5, min(200, (int) $_GET['perPage'])) : 20;
        $offset = ($page - 1) * $perPage;

        // Map fieldKey -> actual admintable column
        $fieldKeyMap = [
            'builder' => 'builder',
            'project' => 'project',
            'unit' => 'unit_no',
            'customer' => 'customer_name',
            'contact' => 'contact_number',
            'email' => 'email_id',
            'status' => 'astatus',
            'city' => 'city',
            'salesperson' => 'source_table',
        ];

        if (!$fieldKey || !isset($fieldKeyMap[$fieldKey])) {
            echo json_encode(['values' => [], 'hasMore' => false]);
            exit;
        }

        $col = $fieldKeyMap[$fieldKey];

        // Determine which rows this user may see.
        // Admintable uses source_table column to identify the user (same as $useruniqueId / $tablename).
        $currentUserTablename = $_SESSION['tablename'] ?? $useruniqueId ?? null;
        if (!$currentUserTablename) {
            echo json_encode(['values' => [], 'hasMore' => false]);
            exit;
        }

        // Get user role to check if manager/ceo can see subordinates
        $stmtRole = $conn->prepare("SELECT user_type FROM accounts WHERE tablename = :tn LIMIT 1");
        $stmtRole->bindValue(':tn', $currentUserTablename, PDO::PARAM_STR);
        $stmtRole->execute();
        $roleRow = $stmtRole->fetch(PDO::FETCH_ASSOC);
        $bkRole = $roleRow['user_type'] ?? 'user';
        $bkNormalizedRole = normalize_role_update($bkRole);

        // Build WHERE clauses
        $whereClauses = [];
        $bindings = [];

        if ($bkNormalizedRole === 'user') {
            // Regular user — only own bookings
            $whereClauses[] = 'source_table = :src_table';
            $bindings[':src_table'] = [$currentUserTablename, PDO::PARAM_STR];
        } else {
            // Manager/CEO/Admin — see all accessible users' bookings
            $accessibleUsers = get_accessible_users_update($conn, $currentUserTablename, $bkRole);
            if (!empty($accessibleUsers)) {
                $ph = [];
                foreach ($accessibleUsers as $i => $u) {
                    $p = ":src_u_$i";
                    $ph[] = $p;
                    $bindings[$p] = [$u, PDO::PARAM_STR];
                }
                $whereClauses[] = 'source_table IN (' . implode(',', $ph) . ')';
            } else {
                $whereClauses[] = 'source_table = :src_table';
                $bindings[':src_table'] = [$currentUserTablename, PDO::PARAM_STR];
            }

            // Optional filterUser param (manager viewing a specific user)
            $filterUserParam = isset($_GET['filterUser']) ? trim((string) $_GET['filterUser']) : '';
            if ($filterUserParam !== '' && in_array($filterUserParam, $accessibleUsers, true)) {
                // Override: only this user's bookings
                $whereClauses = ['source_table = :forced_user'];
                $bindings = [':forced_user' => [$filterUserParam, PDO::PARAM_STR]];
            }
        }

        // Column must be non-empty
        $whereClauses[] = "`$col` IS NOT NULL";
        $whereClauses[] = "TRIM(`$col`) <> ''";

        // Search term filter
        if ($q !== '') {
            $whereClauses[] = "`$col` LIKE :q";
            $bindings[':q'] = ['%' . $q . '%', PDO::PARAM_STR];
        }

        $whereSQL = implode(' AND ', $whereClauses);

        $sql = "SELECT DISTINCT `$col` AS value
                FROM admintable
                WHERE $whereSQL
                ORDER BY `$col` ASC
                LIMIT :lim OFFSET :off";

        $stmt = $conn->prepare($sql);
        foreach ($bindings as $param => $info) {
            [$val, $type] = $info;
            $stmt->bindValue($param, $val, $type);
        }
        $stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $values = [];
        foreach ($rows as $r) {
            $v = isset($r['value']) ? trim((string) $r['value']) : '';
            if ($v !== '')
                $values[] = $v;
        }

        $hasMore = (count($values) === $perPage);
        echo json_encode(['values' => $values, 'hasMore' => $hasMore]);
    } catch (Exception $e) {
        echo json_encode(['values' => [], 'hasMore' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Handle Count Logic
if (isset($_GET['get_data']) && $_GET['get_data'] == 1) {
    try {
        // Check if the toggle is enabled and if user can use it
        $toggleEnabled = isset($_GET['toggle_enabled']) && $_GET['toggle_enabled'] == '1';
        $filterUserParam = isset($_GET['filterUser']) ? trim($_GET['filterUser']) : '';
        $managerViewParam = isset($_GET['managerView']) ? filter_var($_GET['managerView'], FILTER_VALIDATE_BOOLEAN) : false;

        // Get additional filter parameters to match table filtering
        $dateFrom = isset($_GET['dateFrom']) ? $_GET['dateFrom'] : '';
        $dateTo = isset($_GET['dateTo']) ? $_GET['dateTo'] : '';
        $multiFilters = isset($_GET['multiFilters']) ? json_decode($_GET['multiFilters'], true) : [];
        $activeFilter = isset($_GET['filter']) ? $_GET['filter'] : '';
        $searchQuery = isset($_GET['searchQuery']) ? $_GET['searchQuery'] : '';
        $columnsSearch = isset($_GET['columns']) ? $_GET['columns'] : [];

        // Get current user's role and check if they can use manager toggle
        $stmt = $conn->prepare("SELECT user_type FROM accounts WHERE tablename = :tablename");
        $stmt->bindParam(':tablename', $useruniqueId, PDO::PARAM_STR);
        $stmt->execute();
        $currentUserRow = $stmt->fetch(PDO::FETCH_ASSOC);
        $currentUserType = $currentUserRow['user_type'] ?? 'user';
        $normalizedRole = normalize_role_update($currentUserType);
        $canUseManagerToggle = ($normalizedRole !== 'user');
        $accessibleUsers = get_accessible_users_update($conn, $useruniqueId, $currentUserType);

        // Build additional WHERE conditions based on filters
        $additionalConditions = [];
        $commonBindings = [];

        // Date range filter
        if (!empty($dateFrom) && !empty($dateTo)) {
            $dateFromParam = strlen($dateFrom) === 10 ? $dateFrom . ' 00:00:00' : $dateFrom;
            $dateToParam = strlen($dateTo) === 10 ? $dateTo . ' 23:59:59' : $dateTo;
            $additionalConditions[] = "ur.created_at BETWEEN :dateFrom AND :dateTo";
            $commonBindings[':dateFrom'] = [$dateFromParam, PDO::PARAM_STR];
            $commonBindings[':dateTo'] = [$dateToParam, PDO::PARAM_STR];
        }

        // Multi-filters (status, source, etc.) - OPTIMIZED: All columns now support filtering
        $multiFilterMap = [
            'status' => ['field' => 'ur.status', 'type' => 'equals'],
            'source_of_lead' => ['field' => 'LOWER(TRIM(sud.source_of_lead))', 'type' => 'lower_like'],
            'name' => ['field' => 'sud.name', 'type' => 'like'],
            'email' => ['field' => 'sud.email', 'type' => 'like'],
            'number' => ['field' => 'sud.number', 'type' => 'like'],
            'location' => ['field' => 'ur.location_status', 'type' => 'like'],
            'budget' => ['field' => 'ur.budget', 'type' => 'like'],
            'remarks' => ['field' => 'ur.remarks', 'type' => 'like'],
            'updated_at' => ['field' => 'ur.updated_at', 'type' => 'equals'],
            'assign_project_name' => ['field' => 'ur.assign_project_name', 'type' => 'like'],
            'user' => ['field' => 'ur.user_unique_id', 'type' => 'like'],
            'lead_identity' => ['field' => 'ur.lead_identity', 'type' => 'like'],
            'id' => ['field' => 'sud.id', 'type' => 'equals'], // NEW: ID column filtering
            'created_at' => ['field' => 'ur.created_at', 'type' => 'like'] // NEW: Created At column filtering
        ];

        foreach ($multiFilters as $key => $value) {
            if ($value === '' || $value === 'All' || !isset($multiFilterMap[$key])) {
                continue;
            }

            // OPTIMIZED: Skip ID and created_at if they're already processed as column searches
            // This prevents duplicate filtering and ensures column search takes precedence
            $skipIfInColumns = false;
            if (($key === 'id' || $key === 'created_at') && !empty($columnsSearch)) {
                foreach ($columnsSearch as $colIdx => $col) {
                    if (!empty($col['search']['value'])) {
                        if (($key === 'id' && $colIdx == 3) || ($key === 'created_at' && $colIdx == 4)) {
                            $skipIfInColumns = true;
                            break;
                        }
                    }
                }
            }
            if ($skipIfInColumns) {
                continue; // Skip multiFilter processing if already handled as column search (prevents duplicate WHERE clauses)
            }

            $filterMeta = $multiFilterMap[$key];
            $field = $filterMeta['field'];
            $comparisonType = $filterMeta['type'];

            // OPTIMIZED: Handle CSV strings from header dropdown filters (e.g., "value1,value2")
            // Also handle arrays and single values
            if (is_array($value)) {
                $rawValues = $value;
            } elseif ($key !== 'assign_project_name' && is_string($value) && strpos($value, ',') !== false) {
                // Split CSV string into array (header dropdown filters use CSV format)
                $rawValues = array_map('trim', explode(',', $value));
            } else {
                // Keep full value intact for fields (like assign_project_name) that may contain commas
                $rawValues = [$value];
            }

            $cleanValues = [];
            foreach ($rawValues as $rawValue) {
                if ($rawValue !== '' && $rawValue !== 'All') {
                    $cleanValues[] = $rawValue;
                }
            }

            if (empty($cleanValues)) {
                continue;
            }

            // Build conditions and bindings
            if ($comparisonType === 'equals') {
                $placeholders = [];
                foreach ($cleanValues as $idx => $cleanValue) {
                    $paramName = ":filter_{$key}_{$idx}";
                    $placeholders[] = $paramName;
                    $commonBindings[$paramName] = [$cleanValue, PDO::PARAM_STR];
                }
                $additionalConditions[] = sprintf('%s IN (%s)', $field, implode(',', $placeholders));
            } else {
                $orConditions = [];
                foreach ($cleanValues as $idx => $cleanValue) {
                    $paramName = ":filter_{$key}_{$idx}";
                    $orConditions[] = sprintf('%s = %s', $field, $paramName);

                    if ($comparisonType === 'lower_like') {
                        $commonBindings[$paramName] = [strtolower(trim($cleanValue)), PDO::PARAM_STR];
                    } else {
                        $commonBindings[$paramName] = [$cleanValue, PDO::PARAM_STR];
                    }
                }
                if (!empty($orConditions)) {
                    $additionalConditions[] = '(' . implode(' OR ', $orConditions) . ')';
                }
            }
        }

        // Add column-specific search filters
        if (!empty($columnsSearch) && is_array($columnsSearch)) {
            $columnFields = [
                1 => 'sud.name',
                2 => 'ur.assign_project_name',
                3 => 'sud.id',
                4 => 'ur.created_at',
                5 => 'sud.email',
                6 => 'ur.location_status',
                7 => 'ur.budget',
                8 => 'ur.remarks',
                9 => 'ur.updated_at',
                10 => 'ur.user_unique_id',
                11 => 'sud.source_of_lead'
            ];

            foreach ($columnsSearch as $i => $col) {
                if (!empty($col['search']['value']) && isset($columnFields[$i])) {
                    $searchVal = $col['search']['value'];
                    $field = $columnFields[$i];

                    if (strpos($searchVal, '|') !== false) {
                        // Multiple values (OR condition) - OPTIMIZED for ID and Created At
                        $vals = explode('|', $searchVal);
                        $placeholders = [];
                        foreach ($vals as $idx => $val) {
                            $paramName = ":col_search_{$i}_{$idx}";
                            $placeholders[] = $paramName;
                            // OPTIMIZED: For ID (column 3) and Created At (column 4), ensure exact match
                            $commonBindings[$paramName] = [$val, PDO::PARAM_STR];
                        }
                        $additionalConditions[] = "$field IN (" . implode(',', $placeholders) . ")";
                    } else {
                        // Single value (exact match) - OPTIMIZED for ID and Created At
                        $paramName = ":col_search_{$i}";
                        if ($i == 3 || $i == 4) {
                            // ID (3) and Created At (4) columns - exact match
                            $additionalConditions[] = "$field = $paramName";
                        } else {
                            $additionalConditions[] = "$field = $paramName";
                        }
                        $commonBindings[$paramName] = [$searchVal, PDO::PARAM_STR];
                    }
                }
            }
        }

        // Add global search query
        if (!empty($searchQuery)) {
            $searchParts = [
                "sud.name LIKE :global_search",
                "sud.email LIKE :global_search",
                "sud.number LIKE :global_search",
                "sud.location LIKE :global_search",
                "sud.source_of_lead LIKE :global_search",
                "ur.status LIKE :global_search",
                "ur.assign_project_name LIKE :global_search",
                "ur.user_unique_id LIKE :global_search"
            ];
            $searchTerm = '%' . $searchQuery . '%';
            $commonBindings[':global_search'] = [$searchTerm, PDO::PARAM_STR];
            $additionalConditions[] = '(' . implode(' OR ', $searchParts) . ')';
        }

        $additionalWhereSQL = '';
        if (!empty($additionalConditions)) {
            $additionalWhereSQL = ' AND ' . implode(' AND ', $additionalConditions);
        }

        $bindCommonParams = function (PDOStatement $statement) use ($commonBindings) {
            foreach ($commonBindings as $param => $details) {
                $statement->bindValue($param, $details[0], $details[1]);
            }
        };

        // Base condition for where the user is assigned
        $forceSpecificUserForCount = false;
        $isElevatedViewer = in_array(strtolower(trim((string) $userType)), ['promoter', 'superuseradmin', 'admin'], true);
        if ($isElevatedViewer) {
            // Elevated roles see all active leads regardless of assignment
            $userCondition = "1=1";
            $useAccessibleUsersForCount = false;
        } elseif ($filterUserParam !== '' && in_array($filterUserParam, $accessibleUsers, true)) {
            $userCondition = "ur.user_unique_id = :forcedUser";
            $useAccessibleUsersForCount = false;
            $forceSpecificUserForCount = true;
        } elseif ($toggleEnabled && $canUseManagerToggle) {
            // Team view: get subordinates only (exclude current user)
            // Remove current user from accessible users for team view
            $subordinateUsers = array_filter($accessibleUsers, function ($user) use ($useruniqueId) {
                return $user !== $useruniqueId;
            });

            if (count($subordinateUsers) > 0) {
                $placeholders = [];
                for ($i = 0; $i < count($subordinateUsers); $i++) {
                    $placeholders[] = ":accessible_user_count_$i";
                }
                $userCondition = "ur.user_unique_id IN (" . implode(',', $placeholders) . ")";
                $useAccessibleUsersForCount = true;
                $accessibleUsers = array_values($subordinateUsers); // Re-index array for binding
            } else {
                // User has no subordinates, show no data in team view
                $userCondition = "ur.user_unique_id = 'no_subordinates'";
                $useAccessibleUsersForCount = false;
            }
        } else {
            // Individual view or regular user
            $userCondition = "ur.user_unique_id = :userUniqueId";
            $useAccessibleUsersForCount = false;
        }

        // Fetch the count of rows where 'assign_to_user' matches the logged-in user or based on the toggle
        // Use DISTINCT to count unique leads only (avoid counting same lead multiple times)
        $sqlMyLeads = "
            SELECT COUNT(*) as myLeads 
            FROM user_remarks ur
            INNER JOIN shi_upload_data sud ON sud.id = ur.upload_data_id
            WHERE {$userCondition}
            AND ur.history_h = 0
            {$additionalWhereSQL}
        ";

        $stmtMyLeads = $conn->prepare($sqlMyLeads);

        // Bind parameters based on hierarchy
        if ($forceSpecificUserForCount && $filterUserParam !== '') {
            $stmtMyLeads->bindParam(':forcedUser', $filterUserParam, PDO::PARAM_STR);
        } elseif (isset($useAccessibleUsersForCount) && $useAccessibleUsersForCount) {
            for ($i = 0; $i < count($accessibleUsers); $i++) {
                $stmtMyLeads->bindValue(":accessible_user_count_$i", $accessibleUsers[$i], PDO::PARAM_STR);
            }
        } else {
            $stmtMyLeads->bindParam(':userUniqueId', $useruniqueId);
        }
        $bindCommonParams($stmtMyLeads);
        $stmtMyLeads->execute();
        $myLeadsResult = $stmtMyLeads->fetch(PDO::FETCH_ASSOC);

        // Fetch the total count of "Booked Leads" where status is "Converted"
        $sqlbooked = "
            SELECT COUNT(*) as bookedLeads 
            FROM user_remarks ur
            INNER JOIN shi_upload_data sud ON sud.id = ur.upload_data_id
            WHERE ur.status = 'Converted'
            AND {$userCondition}
            AND ur.history_h = 0
            {$additionalWhereSQL}
        ";
        $stmtbooked = $conn->prepare($sqlbooked);

        if ($forceSpecificUserForCount && $filterUserParam !== '') {
            $stmtbooked->bindParam(':forcedUser', $filterUserParam, PDO::PARAM_STR);
        } elseif (isset($useAccessibleUsersForCount) && $useAccessibleUsersForCount) {
            for ($i = 0; $i < count($accessibleUsers); $i++) {
                $stmtbooked->bindValue(":accessible_user_count_$i", $accessibleUsers[$i], PDO::PARAM_STR);
            }
        } else {
            $stmtbooked->bindParam(':userUniqueId', $useruniqueId);
        }
        $bindCommonParams($stmtbooked);
        $stmtbooked->execute();
        $bookedLeads = $stmtbooked->fetch(PDO::FETCH_ASSOC);

        // Fetch the count of "Dropped Leads" where ALL statuses for a lead are "Not Interested"
        $sqlDroppedLeads = "
            SELECT COUNT(*) as droppedLeads 
            FROM user_remarks ur 
            INNER JOIN shi_upload_data sud ON sud.id = ur.upload_data_id
            WHERE ur.status IN ('Not Interested', 'Fake')
            AND {$userCondition}
            AND ur.history_h = 0
            {$additionalWhereSQL}
        ";
        $stmtDroppedLeads = $conn->prepare($sqlDroppedLeads);

        if ($forceSpecificUserForCount && $filterUserParam !== '') {
            $stmtDroppedLeads->bindParam(':forcedUser', $filterUserParam, PDO::PARAM_STR);
        } elseif (isset($useAccessibleUsersForCount) && $useAccessibleUsersForCount) {
            for ($i = 0; $i < count($accessibleUsers); $i++) {
                $stmtDroppedLeads->bindValue(":accessible_user_count_$i", $accessibleUsers[$i], PDO::PARAM_STR);
            }
        } else {
            $stmtDroppedLeads->bindParam(':userUniqueId', $useruniqueId);
        }
        $bindCommonParams($stmtDroppedLeads);
        $stmtDroppedLeads->execute();
        $droppedLeadsResult = $stmtDroppedLeads->fetch(PDO::FETCH_ASSOC);


        // Fetch the count of "Active Leads" where the status is NOT "Not Interested", "Fake", "Already Booked", or "Converted" for any users
        $sqlActiveLeads = "
            SELECT COUNT(*) as activeLeads 
            FROM user_remarks ur
            INNER JOIN shi_upload_data sud ON sud.id = ur.upload_data_id
            WHERE ur.status NOT IN ('Not Interested', 'Fake', 'Already Booked', 'Converted')
            AND (ur.status IS NOT NULL AND ur.status != '') 
            AND {$userCondition}
            AND ur.history_h = 0
            {$additionalWhereSQL}
        ";
        $stmtActiveLeads = $conn->prepare($sqlActiveLeads);

        if ($forceSpecificUserForCount && $filterUserParam !== '') {
            $stmtActiveLeads->bindParam(':forcedUser', $filterUserParam, PDO::PARAM_STR);
        } elseif (isset($useAccessibleUsersForCount) && $useAccessibleUsersForCount) {
            for ($i = 0; $i < count($accessibleUsers); $i++) {
                $stmtActiveLeads->bindValue(":accessible_user_count_$i", $accessibleUsers[$i], PDO::PARAM_STR);
            }
        } else {
            $stmtActiveLeads->bindParam(':userUniqueId', $useruniqueId);
        }
        $bindCommonParams($stmtActiveLeads);
        $stmtActiveLeads->execute();
        $activeLeadsResult = $stmtActiveLeads->fetch(PDO::FETCH_ASSOC);

        // Fetch the count of "Fresh Leads" created in the last 5 days
        $sqlFreshLeads = "
            SELECT COUNT(*) as freshLeads 
            FROM user_remarks ur 
            INNER JOIN shi_upload_data sud ON sud.id = ur.upload_data_id
            WHERE ur.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
            AND {$userCondition}
            AND ur.history_h = 0
            {$additionalWhereSQL}
        ";
        $stmtFreshLeads = $conn->prepare($sqlFreshLeads);

        if ($forceSpecificUserForCount && $filterUserParam !== '') {
            $stmtFreshLeads->bindParam(':forcedUser', $filterUserParam, PDO::PARAM_STR);
        } elseif (isset($useAccessibleUsersForCount) && $useAccessibleUsersForCount) {
            for ($i = 0; $i < count($accessibleUsers); $i++) {
                $stmtFreshLeads->bindValue(":accessible_user_count_$i", $accessibleUsers[$i], PDO::PARAM_STR);
            }
        } else {
            $stmtFreshLeads->bindParam(':userUniqueId', $useruniqueId);
        }

        $bindCommonParams($stmtFreshLeads);
        $stmtFreshLeads->execute();
        $freshLeadsResult = $stmtFreshLeads->fetch(PDO::FETCH_ASSOC); // Fetch the count of "Pending Leads" where any user has marked the status as "Pending"
        $sqlPendingLeads = "
            SELECT COUNT(*) as pendingLeads 
            FROM user_remarks ur 
            INNER JOIN shi_upload_data sud ON sud.id = ur.upload_data_id
            WHERE ur.status = 'Pending'
            AND {$userCondition}
            AND ur.history_h = 0
            {$additionalWhereSQL}
        ";
        $stmtPendingLeads = $conn->prepare($sqlPendingLeads);

        if ($forceSpecificUserForCount && $filterUserParam !== '') {
            $stmtPendingLeads->bindParam(':forcedUser', $filterUserParam, PDO::PARAM_STR);
        } elseif (isset($useAccessibleUsersForCount) && $useAccessibleUsersForCount) {
            for ($i = 0; $i < count($accessibleUsers); $i++) {
                $stmtPendingLeads->bindValue(":accessible_user_count_$i", $accessibleUsers[$i], PDO::PARAM_STR);
            }
        } else {
            $stmtPendingLeads->bindParam(':userUniqueId', $useruniqueId);
        }

        $bindCommonParams($stmtPendingLeads);
        $stmtPendingLeads->execute();
        $pendingLeadsResult = $stmtPendingLeads->fetch(PDO::FETCH_ASSOC);

        // Fetch the count of "Follow Up" where any user has marked the status as "Follow Up"
        $sqlFollowLeads = "
            SELECT COUNT(*) as followupLeads 
            FROM user_remarks ur
            INNER JOIN shi_upload_data sud ON sud.id = ur.upload_data_id
            WHERE ur.status = 'Follow Up'
            AND {$userCondition}
            AND ur.history_h = 0
            {$additionalWhereSQL}
        ";
        $stmtFollowLeads = $conn->prepare($sqlFollowLeads);

        if ($forceSpecificUserForCount && $filterUserParam !== '') {
            $stmtFollowLeads->bindParam(':forcedUser', $filterUserParam, PDO::PARAM_STR);
        } elseif (isset($useAccessibleUsersForCount) && $useAccessibleUsersForCount) {
            for ($i = 0; $i < count($accessibleUsers); $i++) {
                $stmtFollowLeads->bindValue(":accessible_user_count_$i", $accessibleUsers[$i], PDO::PARAM_STR);
            }
        } else {
            $stmtFollowLeads->bindParam(':userUniqueId', $useruniqueId);
        }

        $bindCommonParams($stmtFollowLeads);
        $stmtFollowLeads->execute();
        $followLeadsResult = $stmtFollowLeads->fetch(PDO::FETCH_ASSOC);

        // Fetch the count of "Overdue" leads
        $sqlOverdueLeads = "
            SELECT COUNT(*) as overdueLeads 
            FROM user_remarks ur
            INNER JOIN shi_upload_data sud ON sud.id = ur.upload_data_id
            WHERE ur.status IN ('Follow Up', 'RNR', 'Call Back', 'Pending', 'Interested', 'Fix Site Visit', 'Site Visit Done', 'VC Done', 'Re site visit', 'Not Connected')
            AND ur.status != 'Converted'
            AND ur.status != 'Already Booked'
            AND ur.follow_up_date IS NOT NULL 
            AND ur.follow_up_time IS NOT NULL 
            AND CONCAT(ur.follow_up_date, ' ', ur.follow_up_time) < DATE_SUB(NOW(), INTERVAL 1 DAY)
            AND {$userCondition}
            AND ur.history_h = 0
            {$additionalWhereSQL}
        ";
        $stmtOverdueLeads = $conn->prepare($sqlOverdueLeads);

        if ($forceSpecificUserForCount && $filterUserParam !== '') {
            $stmtOverdueLeads->bindParam(':forcedUser', $filterUserParam, PDO::PARAM_STR);
        } elseif (isset($useAccessibleUsersForCount) && $useAccessibleUsersForCount) {
            for ($i = 0; $i < count($accessibleUsers); $i++) {
                $stmtOverdueLeads->bindValue(":accessible_user_count_$i", $accessibleUsers[$i], PDO::PARAM_STR);
            }
        } else {
            $stmtOverdueLeads->bindParam(':userUniqueId', $useruniqueId);
        }
        $bindCommonParams($stmtOverdueLeads);
        $stmtOverdueLeads->execute();
        $overdueLeadsResult = $stmtOverdueLeads->fetch(PDO::FETCH_ASSOC);

        // Fetch the count for today's collection
        $todayDate = date('Y-m-d');
        $todayStart = $todayDate . ' 00:00:00';
        $todayEnd = $todayDate . ' 23:59:59';
        $statuses = "('Follow Up', 'Interested', 'Call Back', 'RNR', 'Fix Site Visit')";

        $sqlTodayCollection = "
            SELECT COUNT(*) as todayCollection 
            FROM user_remarks ur
            INNER JOIN shi_upload_data sud ON sud.id = ur.upload_data_id
            WHERE {$userCondition}
            AND ur.status IN {$statuses}
            AND (
                (
                    ur.follow_up_date IS NOT NULL
                    AND ur.follow_up_date >= :todayStart
                    AND ur.follow_up_date < :todayEnd
                )
                OR (
                    JSON_TYPE(ur.history) = 'ARRAY'
                    AND JSON_LENGTH(ur.history) > 0
                    AND STR_TO_DATE(
                        JSON_UNQUOTE(
                            JSON_EXTRACT(ur.history, CONCAT('$[', JSON_LENGTH(ur.history) - 1, '].followUpDate'))
                        ), '%Y-%m-%d'
                    ) >= :todayStart
                    AND STR_TO_DATE(
                        JSON_UNQUOTE(
                            JSON_EXTRACT(ur.history, CONCAT('$[', JSON_LENGTH(ur.history) - 1, '].followUpDate'))
                        ), '%Y-%m-%d'
                    ) < :todayEnd
                )
            )
            AND ur.history_h = 0
            {$additionalWhereSQL}
        ";
        $stmtTodayCollection = $conn->prepare($sqlTodayCollection);

        if ($forceSpecificUserForCount && $filterUserParam !== '') {
            $stmtTodayCollection->bindParam(':forcedUser', $filterUserParam, PDO::PARAM_STR);
        } elseif (isset($useAccessibleUsersForCount) && $useAccessibleUsersForCount) {
            for ($i = 0; $i < count($accessibleUsers); $i++) {
                $stmtTodayCollection->bindValue(":accessible_user_count_$i", $accessibleUsers[$i], PDO::PARAM_STR);
            }
        } else {
            $stmtTodayCollection->bindParam(':userUniqueId', $useruniqueId);
        }
        $bindCommonParams($stmtTodayCollection);
        $stmtTodayCollection->bindParam(':todayStart', $todayStart);
        $stmtTodayCollection->bindParam(':todayEnd', $todayEnd);
        $stmtTodayCollection->execute();
        $todayCollectionResult = $stmtTodayCollection->fetch(PDO::FETCH_ASSOC);

        // Fetch the count for Google Ads and Facebook Ads leads (respecting toggle state)
        $sqlAdLeads = "
            SELECT COUNT(*) as adLeads 
            FROM shi_upload_data sud
            INNER JOIN user_remarks ur ON sud.id = ur.upload_data_id
            WHERE LOWER(TRIM(sud.source_of_lead)) IN ('google ads', 'facebook ads', 'magicbricks ads', '99acres ads', 'housing.com ads')
            AND {$userCondition}
            AND ur.history_h = 0
            {$additionalWhereSQL}
        ";
        $stmtAdLeads = $conn->prepare($sqlAdLeads);

        if ($forceSpecificUserForCount && $filterUserParam !== '') {
            $stmtAdLeads->bindParam(':forcedUser', $filterUserParam, PDO::PARAM_STR);
        } elseif (isset($useAccessibleUsersForCount) && $useAccessibleUsersForCount) {
            for ($i = 0; $i < count($accessibleUsers); $i++) {
                $stmtAdLeads->bindValue(":accessible_user_count_$i", $accessibleUsers[$i], PDO::PARAM_STR);
            }
        } else {
            $stmtAdLeads->bindParam(':userUniqueId', $useruniqueId);
        }
        $bindCommonParams($stmtAdLeads);
        $stmtAdLeads->execute();
        $adLeadsResult = $stmtAdLeads->fetch(PDO::FETCH_ASSOC);

        // Fetch the count for leads where source_of_lead is NOT ads (respecting toggle state)
        $sqlOtherLeads = "
            SELECT COUNT(*) as otherLeads 
            FROM shi_upload_data sud
            INNER JOIN user_remarks ur ON sud.id = ur.upload_data_id
            WHERE (
                sud.source_of_lead IS NULL OR 
                LOWER(TRIM(sud.source_of_lead)) NOT IN ('google ads', 'facebook ads', 'magicbricks ads', '99acres ads', 'housing.com ads')
            )
            AND {$userCondition}
            AND ur.history_h = 0
            {$additionalWhereSQL}
        ";
        $stmtOtherLeads = $conn->prepare($sqlOtherLeads);

        if ($forceSpecificUserForCount && $filterUserParam !== '') {
            $stmtOtherLeads->bindParam(':forcedUser', $filterUserParam, PDO::PARAM_STR);
        } elseif (isset($useAccessibleUsersForCount) && $useAccessibleUsersForCount) {
            for ($i = 0; $i < count($accessibleUsers); $i++) {
                $stmtOtherLeads->bindValue(":accessible_user_count_$i", $accessibleUsers[$i], PDO::PARAM_STR);
            }
        } else {
            $stmtOtherLeads->bindParam(':userUniqueId', $useruniqueId);
        }
        $bindCommonParams($stmtOtherLeads);
        $stmtOtherLeads->execute();
        $otherLeadsResult = $stmtOtherLeads->fetch(PDO::FETCH_ASSOC);


        // Return all counts as a JSON object
        echo json_encode([
            'myLeads' => $myLeadsResult['myLeads'],
            'bookedLeads' => $bookedLeads['bookedLeads'],
            'droppedLeads' => $droppedLeadsResult['droppedLeads'],
            'activeLeads' => $activeLeadsResult['activeLeads'],
            'freshLeads' => $freshLeadsResult['freshLeads'],
            'pendingLeads' => $pendingLeadsResult['pendingLeads'],
            'followupLeads' => $followLeadsResult['followupLeads'],
            'overdueLeads' => $overdueLeadsResult['overdueLeads'],
            'today_collection' => $todayCollectionResult['todayCollection'],
            'paidAds' => $adLeadsResult['adLeads'], // New count added here
            'shi_d' => $otherLeadsResult['otherLeads'] // New "SHI-D" Count
        ]);
    } catch (PDOException $e) {
        // Return 0 for all counts in case of error
        echo json_encode([
            'myLeads' => 0,
            'bookedLeads' => 0,
            'droppedLeads' => 0,
            'activeLeads' => 0,
            'freshLeads' => 0,
            'pendingLeads' => 0,
            'followupLeads' => 0,
            'overdueLeads' => 0,
            'today_collection' => 0,
            'paidAds' => 0, // Default to 0 in case of error
            'shi_d' => 0, // Default to 0 in case of error
        ]);
    }
    exit;
}
// Handle Count Logic End

// ============================================================================
// SECTION 3: TAG COUNT API (OPTIMIZED FOR 1L+ RECORDS)
// ============================================================================
// CONSOLIDATED endpoint - Returns ALL tag counts in a single API call
// This replaces 11 individual API calls with just 1 for maximum performance
// ============================================================================

// Handle ALL Tag Counts in Single Request - SUPER OPTIMIZED ENDPOINT
if (isset($_GET['get_all_tag_counts']) && $_GET['get_all_tag_counts'] == '1') {
    try {
        // Get filter parameters - Parse once, reuse for all counts
        $toggleEnabled = isset($_GET['toggle_enabled']) && $_GET['toggle_enabled'] == '1';
        $filterUserParam = isset($_GET['filterUser']) ? trim($_GET['filterUser']) : '';
        $managerViewParam = isset($_GET['managerView']) ? filter_var($_GET['managerView'], FILTER_VALIDATE_BOOLEAN) : false;
        $dateFrom = isset($_GET['dateFrom']) ? $_GET['dateFrom'] : '';
        $dateTo = isset($_GET['dateTo']) ? $_GET['dateTo'] : '';
        $multiFilters = isset($_GET['multiFilters']) ? json_decode($_GET['multiFilters'], true) : [];
        $searchQuery = isset($_GET['searchQuery']) ? trim($_GET['searchQuery']) : '';
        $columnsSearch = isset($_GET['columns']) ? $_GET['columns'] : [];

        // Cache user role lookup
        $stmt = $conn->prepare("SELECT user_type FROM accounts WHERE tablename = :tablename LIMIT 1");
        $stmt->bindParam(':tablename', $useruniqueId, PDO::PARAM_STR);
        $stmt->execute();
        $currentUserRow = $stmt->fetch(PDO::FETCH_ASSOC);
        $currentUserType = $currentUserRow['user_type'] ?? 'user';
        $normalizedRole = normalize_role_update($currentUserType);
        $canUseManagerToggle = ($normalizedRole !== 'user');

        // Cache accessible users
        $accessibleUsers = get_accessible_users_update($conn, $useruniqueId, $currentUserType);

        // Build additional WHERE conditions based on filters
        $additionalConditions = [];
        $commonBindings = [];

        // Date range filter - Check both GET params AND multiFilters
        if (!empty($dateFrom) && !empty($dateTo)) {
            $dateFromParam = strlen($dateFrom) === 10 ? $dateFrom . ' 00:00:00' : $dateFrom;
            $dateToParam = strlen($dateTo) === 10 ? $dateTo . ' 23:59:59' : $dateTo;
            $additionalConditions[] = "ur.created_at BETWEEN :dateFrom AND :dateTo";
            $commonBindings[':dateFrom'] = [$dateFromParam, PDO::PARAM_STR];
            $commonBindings[':dateTo'] = [$dateToParam, PDO::PARAM_STR];
        } elseif (
            isset($multiFilters['start_date']) && isset($multiFilters['end_date']) &&
            !empty($multiFilters['start_date']) && !empty($multiFilters['end_date'])
        ) {
            // Fallback: use dates from multiFilters if GET params are empty
            $dateFromParam = strlen($multiFilters['start_date']) === 10 ? $multiFilters['start_date'] . ' 00:00:00' : $multiFilters['start_date'];
            $dateToParam = strlen($multiFilters['end_date']) === 10 ? $multiFilters['end_date'] . ' 23:59:59' : $multiFilters['end_date'];
            $additionalConditions[] = "ur.created_at BETWEEN :dateFrom AND :dateTo";
            $commonBindings[':dateFrom'] = [$dateFromParam, PDO::PARAM_STR];
            $commonBindings[':dateTo'] = [$dateToParam, PDO::PARAM_STR];
        }

        // Multi-filters (INCLUDING status for tag counts)
        $multiFilterMap = [
            'status' => ['field' => 'ur.status', 'type' => 'equals'], // INCLUDED: Status filter affects tag counts
            'source_of_lead' => ['field' => 'LOWER(TRIM(sud.source_of_lead))', 'type' => 'lower_like'],
            'name' => ['field' => 'sud.name', 'type' => 'like'],
            'email' => ['field' => 'sud.email', 'type' => 'like'],
            'number' => ['field' => 'sud.number', 'type' => 'like'],
            'location' => ['field' => 'ur.location_status', 'type' => 'like'],
            'budget' => ['field' => 'ur.budget', 'type' => 'like'],
            'remarks' => ['field' => 'ur.remarks', 'type' => 'like'],
            'updated_at' => ['field' => 'ur.updated_at', 'type' => 'equals'],
            'assign_project_name' => ['field' => 'ur.assign_project_name', 'type' => 'like'],
            'user' => ['field' => 'ur.user_unique_id', 'type' => 'equals'],
            'lead_identity' => ['field' => 'ur.lead_identity', 'type' => 'equals'],
            'id' => ['field' => 'sud.id', 'type' => 'equals'],
            'created_at' => ['field' => 'ur.created_at', 'type' => 'equals']
        ];

        foreach ($multiFilters as $key => $value) {
            if ($value === '' || $value === 'All' || !isset($multiFilterMap[$key])) {
                continue;
            }

            $filterMeta = $multiFilterMap[$key];
            $field = $filterMeta['field'];
            $comparisonType = $filterMeta['type'];

            if (is_array($value)) {
                $rawValues = $value;
            } elseif ($key !== 'assign_project_name' && is_string($value) && strpos($value, ',') !== false) {
                $rawValues = array_map('trim', explode(',', $value));
            } else {
                $rawValues = [$value];
            }

            $cleanValues = [];
            foreach ($rawValues as $rawValue) {
                if ($rawValue !== '' && $rawValue !== 'All') {
                    $cleanValues[] = $rawValue;
                }
            }

            if (empty($cleanValues)) {
                continue;
            }

            if ($comparisonType === 'equals') {
                $placeholders = [];
                foreach ($cleanValues as $idx => $cleanValue) {
                    $paramName = ":filter_{$key}_{$idx}";
                    $placeholders[] = $paramName;
                    if ($key === 'id') {
                        $commonBindings[$paramName] = [is_numeric($cleanValue) ? (int) $cleanValue : $cleanValue, is_numeric($cleanValue) ? PDO::PARAM_INT : PDO::PARAM_STR];
                    } else {
                        $commonBindings[$paramName] = [$cleanValue, PDO::PARAM_STR];
                    }
                }
                $additionalConditions[] = sprintf('%s IN (%s)', $field, implode(',', $placeholders));
            } else {
                $orConditions = [];
                foreach ($cleanValues as $idx => $cleanValue) {
                    $paramName = ":filter_{$key}_{$idx}";
                    $orConditions[] = sprintf('%s = %s', $field, $paramName);

                    if ($comparisonType === 'lower_like') {
                        $commonBindings[$paramName] = [strtolower(trim($cleanValue)), PDO::PARAM_STR];
                    } else {
                        $commonBindings[$paramName] = [$cleanValue, PDO::PARAM_STR];
                    }
                }
                if (!empty($orConditions)) {
                    $additionalConditions[] = '(' . implode(' OR ', $orConditions) . ')';
                }
            }
        }

        // Global search query
        if (!empty($searchQuery)) {
            $searchParts = [
                "sud.name LIKE :global_search",
                "sud.email LIKE :global_search",
                "sud.number LIKE :global_search",
                "sud.location LIKE :global_search",
                "sud.source_of_lead LIKE :global_search",
                "ur.status LIKE :global_search",
                "ur.assign_project_name LIKE :global_search",
                "ur.user_unique_id LIKE :global_search"
            ];
            $searchTerm = '%' . $searchQuery . '%';
            $commonBindings[':global_search'] = [$searchTerm, PDO::PARAM_STR];
            $additionalConditions[] = '(' . implode(' OR ', $searchParts) . ')';
        }

        $additionalWhereSQL = '';
        if (!empty($additionalConditions)) {
            $additionalWhereSQL = ' AND ' . implode(' AND ', $additionalConditions);
        }

        // Build user condition
        $forceSpecificUser = false;
        $useAccessibleUsers = false;
        if ($filterUserParam !== '' && in_array($filterUserParam, $accessibleUsers, true)) {
            $userCondition = "ur.user_unique_id = :forcedUser";
            $forceSpecificUser = true;
        } elseif ($toggleEnabled && $canUseManagerToggle) {
            $subordinateUsers = array_filter($accessibleUsers, function ($user) use ($useruniqueId) {
                return $user !== $useruniqueId;
            });

            if (count($subordinateUsers) > 0) {
                $placeholders = [];
                for ($i = 0; $i < count($subordinateUsers); $i++) {
                    $placeholders[] = ":accessible_user_$i";
                }
                $userCondition = "ur.user_unique_id IN (" . implode(',', $placeholders) . ")";
                $useAccessibleUsers = true;
                $accessibleUsers = array_values($subordinateUsers);
            } else {
                $userCondition = "ur.user_unique_id = 'no_subordinates'";
            }
        } else {
            $userCondition = "ur.user_unique_id = :userUniqueId";
        }

        // Today's date for specific counts
        $todayDate = date('Y-m-d');
        $todayStart = $todayDate . ' 00:00:00';
        $todayEnd = $todayDate . ' 23:59:59';

        // SINGLE OPTIMIZED QUERY - Get all counts at once using conditional aggregation
        $sql = "SELECT 
            COUNT(*) as myLeads,
            SUM(CASE WHEN ur.status = 'Converted' THEN 1 ELSE 0 END) as bookedLeads,
            SUM(CASE WHEN ur.status NOT IN ('Not Interested', 'Fake', 'Already Booked', 'Converted') AND ur.status IS NOT NULL AND ur.status != '' THEN 1 ELSE 0 END) as activeLeads,
            SUM(CASE WHEN ur.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 1 ELSE 0 END) as freshLeads,

            SUM(CASE WHEN ur.status = 'Pending' THEN 1 ELSE 0 END) as pendingLeads,
            SUM(CASE WHEN ur.status IN ('Not Interested', 'Fake') THEN 1 ELSE 0 END) as droppedLeads,
            SUM(CASE WHEN ur.status = 'Follow Up' THEN 1 ELSE 0 END) as followLeads,
            SUM(CASE WHEN ur.status IN ('Follow Up', 'RNR', 'Call Back', 'Pending', 'Interested', 'Fix Site Visit', 'Site Visit Done', 'VC Done', 'Re site visit', 'Not Connected') 
                AND ur.status != 'Converted' AND ur.status != 'Already Booked'
                AND ur.follow_up_date IS NOT NULL AND ur.follow_up_time IS NOT NULL 
                AND CONCAT(ur.follow_up_date, ' ', ur.follow_up_time) < DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 1 ELSE 0 END) as overdueLeads,
            SUM(CASE WHEN ur.status IN ('Follow Up', 'Interested', 'Call Back', 'RNR', 'Fix Site Visit')
                AND (
                    -- Lead HAS history: trust only the last history entry's followUpDate (user-set).
                    -- Prevents skip-bumped leads (column=today, history=Apr 25) from counting.
                    (
                        JSON_TYPE(ur.history) = 'ARRAY'
                        AND JSON_LENGTH(ur.history) > 0
                        AND DATE(STR_TO_DATE(
                            JSON_UNQUOTE(JSON_EXTRACT(ur.history, CONCAT('$[', JSON_LENGTH(ur.history) - 1, '].followUpDate'))),
                            '%Y-%m-%d'
                        )) = CURDATE()
                    )
                    OR
                    -- Lead has NO history yet: fall back to follow_up_date column.
                    (
                        (ur.history IS NULL OR JSON_LENGTH(ur.history) = 0)
                        AND ur.follow_up_date IS NOT NULL
                        AND DATE(ur.follow_up_date) = CURDATE()
                    )
                ) THEN 1 ELSE 0 END) as today_collection,
            SUM(CASE WHEN LOWER(TRIM(sud.source_of_lead)) IN ('google ads', 'facebook ads', 'magicbricks ads', '99acres ads', 'housing.com ads') THEN 1 ELSE 0 END) as paidAds,
            SUM(CASE WHEN sud.source_of_lead IS NULL OR LOWER(TRIM(sud.source_of_lead)) NOT IN ('google ads', 'facebook ads', 'magicbricks ads', '99acres ads', 'housing.com ads') THEN 1 ELSE 0 END) as shi_d
        FROM user_remarks ur 
        INNER JOIN shi_upload_data sud ON sud.id = ur.upload_data_id 
        WHERE ur.history_h = 0 AND {$userCondition} {$additionalWhereSQL}";

        $stmt = $conn->prepare($sql);

        // Bind user parameter
        if ($forceSpecificUser && $filterUserParam !== '') {
            $stmt->bindParam(':forcedUser', $filterUserParam, PDO::PARAM_STR);
        } elseif ($useAccessibleUsers) {
            for ($i = 0; $i < count($accessibleUsers); $i++) {
                $stmt->bindValue(":accessible_user_$i", $accessibleUsers[$i], PDO::PARAM_STR);
            }
        } else {
            $stmt->bindParam(':userUniqueId', $useruniqueId);
        }

        // today_collection now uses CURDATE() directly — no :todayStart/:todayEnd bindings needed.

        // Bind common filter parameters
        foreach ($commonBindings as $param => $details) {
            $stmt->bindValue($param, $details[0], $details[1]);
        }

        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Return all counts in single response
        echo json_encode([
            'status' => 'success',
            'counts' => [
                'myLeads' => (int) ($result['myLeads'] ?? 0),
                'bookedLeads' => (int) ($result['bookedLeads'] ?? 0),
                'activeLeads' => (int) ($result['activeLeads'] ?? 0),
                'freshLeads' => (int) ($result['freshLeads'] ?? 0),
                'pendingLeads' => (int) ($result['pendingLeads'] ?? 0),
                'droppedLeads' => (int) ($result['droppedLeads'] ?? 0),
                'followLeads' => (int) ($result['followLeads'] ?? 0),
                'overdueLeads' => (int) ($result['overdueLeads'] ?? 0),
                'today_collection' => (int) ($result['today_collection'] ?? 0),
                'paidAds' => (int) ($result['paidAds'] ?? 0),
                'shi_d' => (int) ($result['shi_d'] ?? 0)
            ]
        ]);
    } catch (PDOException $e) {
        error_log("Error fetching all tag counts: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'error' => $e->getMessage(), 'counts' => []]);
    }
    exit;
}

// Individual tag count endpoints (kept for backwards compatibility but deprecated)
// Handles: myLeads, bookedLeads, activeLeads, freshLeads, pendingLeads,
//          droppedLeads, followLeads, overdueLeads, today_collection,
//          paidAds, shi_d
// ============================================================================

// Handle Individual Tag Count Requests - DEPRECATED (use get_all_tag_counts instead)
if (isset($_GET['get_tag_count']) && !empty($_GET['get_tag_count'])) {
    $countType = $_GET['get_tag_count'];
    $validCountTypes = ['myLeads', 'bookedLeads', 'activeLeads', 'freshLeads', 'pendingLeads', 'droppedLeads', 'followLeads', 'overdueLeads', 'today_collection', 'paidAds', 'shi_d'];

    if (!in_array($countType, $validCountTypes)) {
        echo json_encode(['count' => 0, 'error' => 'Invalid count type']);
        exit;
    }

    try {
        // Get filter parameters - OPTIMIZED: Parse once, reuse
        $toggleEnabled = isset($_GET['toggle_enabled']) && $_GET['toggle_enabled'] == '1';
        $filterUserParam = isset($_GET['filterUser']) ? trim($_GET['filterUser']) : '';
        $managerViewParam = isset($_GET['managerView']) ? filter_var($_GET['managerView'], FILTER_VALIDATE_BOOLEAN) : false;
        $dateFrom = isset($_GET['dateFrom']) ? $_GET['dateFrom'] : '';
        $dateTo = isset($_GET['dateTo']) ? $_GET['dateTo'] : '';
        $multiFilters = isset($_GET['multiFilters']) ? json_decode($_GET['multiFilters'], true) : [];
        $searchQuery = isset($_GET['searchQuery']) ? trim($_GET['searchQuery']) : '';
        $columnsSearch = isset($_GET['columns']) ? $_GET['columns'] : [];

        // OPTIMIZED: Cache user role lookup (used in multiple places)
        static $userRoleCache = [];
        $cacheKey = $useruniqueId;
        if (!isset($userRoleCache[$cacheKey])) {
            $stmt = $conn->prepare("SELECT user_type FROM accounts WHERE tablename = :tablename LIMIT 1");
            $stmt->bindParam(':tablename', $useruniqueId, PDO::PARAM_STR);
            $stmt->execute();
            $currentUserRow = $stmt->fetch(PDO::FETCH_ASSOC);
            $userRoleCache[$cacheKey] = $currentUserRow['user_type'] ?? 'user';
        }
        $currentUserType = $userRoleCache[$cacheKey];
        $normalizedRole = normalize_role_update($currentUserType);
        $canUseManagerToggle = ($normalizedRole !== 'user');

        // OPTIMIZED: Cache accessible users (expensive operation)
        static $accessibleUsersCache = [];
        $usersCacheKey = $useruniqueId . '_' . $currentUserType;
        if (!isset($accessibleUsersCache[$usersCacheKey])) {
            $accessibleUsersCache[$usersCacheKey] = get_accessible_users_update($conn, $useruniqueId, $currentUserType);
        }
        $accessibleUsers = $accessibleUsersCache[$usersCacheKey];

        // Build additional WHERE conditions based on filters
        $additionalConditions = [];
        $commonBindings = [];

        // Date range filter
        if (!empty($dateFrom) && !empty($dateTo)) {
            $dateFromParam = strlen($dateFrom) === 10 ? $dateFrom . ' 00:00:00' : $dateFrom;
            $dateToParam = strlen($dateTo) === 10 ? $dateTo . ' 23:59:59' : $dateTo;
            $additionalConditions[] = "ur.created_at BETWEEN :dateFrom AND :dateTo";
            $commonBindings[':dateFrom'] = [$dateFromParam, PDO::PARAM_STR];
            $commonBindings[':dateTo'] = [$dateToParam, PDO::PARAM_STR];
        }

        // Multi-filters - OPTIMIZED: All columns now support filtering
        $multiFilterMap = [
            'status' => ['field' => 'ur.status', 'type' => 'equals'],
            'source_of_lead' => ['field' => 'LOWER(TRIM(sud.source_of_lead))', 'type' => 'lower_like'],
            'name' => ['field' => 'sud.name', 'type' => 'like'],
            'email' => ['field' => 'sud.email', 'type' => 'like'],
            'number' => ['field' => 'sud.number', 'type' => 'like'],
            'location' => ['field' => 'ur.location_status', 'type' => 'like'],
            'budget' => ['field' => 'ur.budget', 'type' => 'like'],
            'remarks' => ['field' => 'ur.remarks', 'type' => 'like'],
            'updated_at' => ['field' => 'ur.updated_at', 'type' => 'equals'],
            'assign_project_name' => ['field' => 'ur.assign_project_name', 'type' => 'like'],
            'user' => ['field' => 'ur.user_unique_id', 'type' => 'equals'],
            'lead_identity' => ['field' => 'ur.lead_identity', 'type' => 'equals'],
            'id' => ['field' => 'sud.id', 'type' => 'equals'], // ID column filtering (exact match)
            'created_at' => ['field' => 'ur.created_at', 'type' => 'equals'], // Created At column filtering (exact match for date strings)
            'start_date' => ['field' => 'ur.created_at', 'type' => 'date_range'],
            'end_date' => ['field' => 'ur.created_at', 'type' => 'date_range']
        ];

        foreach ($multiFilters as $key => $value) {
            if ($value === '' || $value === 'All' || !isset($multiFilterMap[$key])) {
                continue;
            }

            $filterMeta = $multiFilterMap[$key];
            $field = $filterMeta['field'];
            $comparisonType = $filterMeta['type'];

            // OPTIMIZED: Handle CSV strings from header dropdown filters (e.g., "value1,value2")
            // Also handle arrays and single values
            if (is_array($value)) {
                $rawValues = $value;
            } elseif ($key !== 'assign_project_name' && is_string($value) && strpos($value, ',') !== false) {
                // Split CSV string into array (header dropdown filters use CSV format)
                $rawValues = array_map('trim', explode(',', $value));
            } else {
                // Keep comma-containing project names intact
                $rawValues = [$value];
            }

            $cleanValues = [];
            foreach ($rawValues as $rawValue) {
                if ($rawValue !== '' && $rawValue !== 'All') {
                    $cleanValues[] = $rawValue;
                }
            }

            if (empty($cleanValues)) {
                continue;
            }

            if ($comparisonType === 'equals') {
                $placeholders = [];
                foreach ($cleanValues as $idx => $cleanValue) {
                    $paramName = ":filter_{$key}_{$idx}";
                    $placeholders[] = $paramName;
                    // OPTIMIZED: Handle ID as integer, Created At as string (date)
                    if ($key === 'id') {
                        $commonBindings[$paramName] = [is_numeric($cleanValue) ? (int) $cleanValue : $cleanValue, is_numeric($cleanValue) ? PDO::PARAM_INT : PDO::PARAM_STR];
                    } else {
                        $commonBindings[$paramName] = [$cleanValue, PDO::PARAM_STR];
                    }
                }
                $additionalConditions[] = sprintf('%s IN (%s)', $field, implode(',', $placeholders));
            } else {
                $orConditions = [];
                foreach ($cleanValues as $idx => $cleanValue) {
                    $paramName = ":filter_{$key}_{$idx}";
                    $orConditions[] = sprintf('%s = %s', $field, $paramName);

                    if ($comparisonType === 'lower_like') {
                        $commonBindings[$paramName] = [strtolower(trim($cleanValue)), PDO::PARAM_STR];
                    } else {
                        $commonBindings[$paramName] = [$cleanValue, PDO::PARAM_STR];
                    }
                }
                if (!empty($orConditions)) {
                    $additionalConditions[] = '(' . implode(' OR ', $orConditions) . ')';
                }
            }
        }

        // Column-specific search filters
        if (!empty($columnsSearch) && is_array($columnsSearch)) {
            $columnFields = [
                1 => 'sud.name',
                2 => 'ur.assign_project_name',
                3 => 'sud.id',
                4 => 'ur.created_at',
                5 => 'sud.email',
                6 => 'ur.location_status',
                7 => 'ur.budget',
                8 => 'ur.remarks',
                9 => 'ur.updated_at',
                10 => 'ur.user_unique_id',
                11 => 'sud.source_of_lead'
            ];

            foreach ($columnsSearch as $i => $col) {
                if (!empty($col['search']['value']) && isset($columnFields[$i])) {
                    $searchVal = $col['search']['value'];
                    $field = $columnFields[$i];

                    if (strpos($searchVal, '|') !== false) {
                        $vals = explode('|', $searchVal);
                        $placeholders = [];
                        foreach ($vals as $idx => $val) {
                            $paramName = ":col_search_{$i}_{$idx}";
                            $placeholders[] = $paramName;
                            $commonBindings[$paramName] = [$val, PDO::PARAM_STR];
                        }
                        $additionalConditions[] = "$field IN (" . implode(',', $placeholders) . ")";
                    } else {
                        $paramName = ":col_search_{$i}";
                        $additionalConditions[] = "$field = $paramName";
                        $commonBindings[$paramName] = [$searchVal, PDO::PARAM_STR];
                    }
                }
            }
        }

        // Global search query
        if (!empty($searchQuery)) {
            $searchParts = [
                "sud.name LIKE :global_search",
                "sud.email LIKE :global_search",
                "sud.number LIKE :global_search",
                "sud.location LIKE :global_search",
                "sud.source_of_lead LIKE :global_search",
                "ur.status LIKE :global_search",
                "ur.assign_project_name LIKE :global_search",
                "ur.user_unique_id LIKE :global_search"
            ];
            $searchTerm = '%' . $searchQuery . '%';
            $commonBindings[':global_search'] = [$searchTerm, PDO::PARAM_STR];
            $additionalConditions[] = '(' . implode(' OR ', $searchParts) . ')';
        }

        $additionalWhereSQL = '';
        if (!empty($additionalConditions)) {
            $additionalWhereSQL = ' AND ' . implode(' AND ', $additionalConditions);
        }

        $bindCommonParams = function (PDOStatement $statement) use ($commonBindings) {
            foreach ($commonBindings as $param => $details) {
                $statement->bindValue($param, $details[0], $details[1]);
            }
        };

        // Build user condition
        $forceSpecificUserForCount = false;
        if ($filterUserParam !== '' && in_array($filterUserParam, $accessibleUsers, true)) {
            $userCondition = "ur.user_unique_id = :forcedUser";
            $useAccessibleUsersForCount = false;
            $forceSpecificUserForCount = true;
        } elseif ($toggleEnabled && $canUseManagerToggle) {
            $subordinateUsers = array_filter($accessibleUsers, function ($user) use ($useruniqueId) {
                return $user !== $useruniqueId;
            });

            if (count($subordinateUsers) > 0) {
                $placeholders = [];
                for ($i = 0; $i < count($subordinateUsers); $i++) {
                    $placeholders[] = ":accessible_user_count_$i";
                }
                $userCondition = "ur.user_unique_id IN (" . implode(',', $placeholders) . ")";
                $useAccessibleUsersForCount = true;
                $accessibleUsers = array_values($subordinateUsers);
            } else {
                $userCondition = "ur.user_unique_id = 'no_subordinates'";
                $useAccessibleUsersForCount = false;
            }
        } else {
            $userCondition = "ur.user_unique_id = :userUniqueId";
            $useAccessibleUsersForCount = false;
        }

        // Build SQL based on count type - SUPER OPTIMIZED for 1L+ records
        // WHERE clause order: Most selective filters first (history_h, status, dates) -> user condition -> additional filters
        // Using COUNT(*) with proper WHERE ordering for optimal index usage
        $sql = '';
        switch ($countType) {
            case 'myLeads':
                // OPTIMIZED: history_h first (most selective ~90% reduction), then user, then additional filters
                $sql = "SELECT COUNT(*) as count FROM user_remarks ur INNER JOIN shi_upload_data sud ON sud.id = ur.upload_data_id WHERE ur.history_h = 0 AND {$userCondition} {$additionalWhereSQL}";
                break;
            case 'bookedLeads':
                // OPTIMIZED: status first (selective), then history_h, then user
                $sql = "SELECT COUNT(*) as count FROM user_remarks ur INNER JOIN shi_upload_data sud ON sud.id = ur.upload_data_id WHERE ur.status = 'Converted' AND ur.history_h = 0 AND {$userCondition} {$additionalWhereSQL}";
                break;
            case 'droppedLeads':
                // OPTIMIZED: status IN first, then history_h, then user
                $sql = "SELECT COUNT(*) as count FROM user_remarks ur INNER JOIN shi_upload_data sud ON sud.id = ur.upload_data_id WHERE ur.status IN ('Not Interested', 'Fake') AND ur.history_h = 0 AND {$userCondition} {$additionalWhereSQL}";
                break;
            case 'activeLeads':
                // OPTIMIZED: status NOT IN first, then history_h, then user
                $sql = "SELECT COUNT(*) as count FROM user_remarks ur INNER JOIN shi_upload_data sud ON sud.id = ur.upload_data_id WHERE ur.status NOT IN ('Not Interested', 'Fake', 'Already Booked', 'Converted') AND ur.status IS NOT NULL AND ur.status != '' AND ur.history_h = 0 AND {$userCondition} {$additionalWhereSQL}";
                break;
            case 'freshLeads':
                // New leads assigned in the last 24 hours — regardless of current status.
                // Using NOW() instead of CURDATE() gives a true rolling 24h window.
                $sql = "SELECT COUNT(*) as count FROM user_remarks ur INNER JOIN shi_upload_data sud ON sud.id = ur.upload_data_id WHERE ur.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY) AND ur.history_h = 0 AND {$userCondition} {$additionalWhereSQL}";
                break;
            case 'pendingLeads':
                // OPTIMIZED: status first, then history_h, then user
                $sql = "SELECT COUNT(*) as count FROM user_remarks ur INNER JOIN shi_upload_data sud ON sud.id = ur.upload_data_id WHERE ur.status = 'Pending' AND ur.history_h = 0 AND {$userCondition} {$additionalWhereSQL}";
                break;
            case 'followLeads':
                // OPTIMIZED: status first, then history_h, then user
                $sql = "SELECT COUNT(*) as count FROM user_remarks ur INNER JOIN shi_upload_data sud ON sud.id = ur.upload_data_id WHERE ur.status = 'Follow Up' AND ur.history_h = 0 AND {$userCondition} {$additionalWhereSQL}";
                break;
            case 'overdueLeads':
                // OPTIMIZED: status filter first, then date comparison, then history_h, then user
                $sql = "SELECT COUNT(*) as count FROM user_remarks ur INNER JOIN shi_upload_data sud ON sud.id = ur.upload_data_id WHERE ur.status IN ('Follow Up', 'RNR', 'Call Back', 'Pending', 'Interested', 'Fix Site Visit', 'Site Visit Done', 'VC Done', 'Re site visit', 'Not Connected') AND ur.status != 'Converted' AND ur.status != 'Already Booked' AND ur.follow_up_date IS NOT NULL AND ur.follow_up_time IS NOT NULL AND CONCAT(ur.follow_up_date, ' ', ur.follow_up_time) < DATE_SUB(NOW(), INTERVAL 1 DAY) AND ur.history_h = 0 AND {$userCondition} {$additionalWhereSQL}";
                break;
            case 'today_collection':
                $statuses = "('Follow Up', 'Interested', 'Call Back', 'RNR', 'Fix Site Visit')";
                // Trust the last history entry's followUpDate (what the user actually scheduled).
                // Only fall back to the follow_up_date column for leads that have no history yet.
                // This prevents skip-bumped leads (where column = today but history says Apr 25)
                // from incorrectly appearing in Today's Follow-Up.
                $sql = "SELECT COUNT(*) as count FROM user_remarks ur INNER JOIN shi_upload_data sud ON sud.id = ur.upload_data_id WHERE ur.status IN {$statuses} AND ur.history_h = 0 AND {$userCondition} AND (
                    (
                        JSON_TYPE(ur.history) = 'ARRAY' AND JSON_LENGTH(ur.history) > 0
                        AND DATE(STR_TO_DATE(JSON_UNQUOTE(JSON_EXTRACT(ur.history, CONCAT('\$[', JSON_LENGTH(ur.history) - 1, '].followUpDate'))), '%Y-%m-%d')) = CURDATE()
                    )
                    OR (
                        (ur.history IS NULL OR JSON_LENGTH(ur.history) = 0)
                        AND ur.follow_up_date IS NOT NULL AND DATE(ur.follow_up_date) = CURDATE()
                    )
                ) {$additionalWhereSQL}";
                break;
            case 'paidAds':
                // OPTIMIZED: source filter first, then history_h, then user
                $sql = "SELECT COUNT(*) as count FROM shi_upload_data sud INNER JOIN user_remarks ur ON sud.id = ur.upload_data_id WHERE LOWER(TRIM(sud.source_of_lead)) IN ('google ads', 'facebook ads', 'magicbricks ads', '99acres ads', 'housing.com ads') AND ur.history_h = 0 AND {$userCondition} {$additionalWhereSQL}";
                break;
            case 'shi_d':
                // OPTIMIZED: source filter first, then history_h, then user
                $sql = "SELECT COUNT(*) as count FROM shi_upload_data sud INNER JOIN user_remarks ur ON sud.id = ur.upload_data_id WHERE (sud.source_of_lead IS NULL OR LOWER(TRIM(sud.source_of_lead)) NOT IN ('google ads', 'facebook ads', 'magicbricks ads', '99acres ads', 'housing.com ads')) AND ur.history_h = 0 AND {$userCondition} {$additionalWhereSQL}";
                break;
        }

        if (empty($sql)) {
            echo json_encode(['count' => 0, 'error' => 'Invalid count type']);
            exit;
        }

        // Set query timeout for large datasets (30 seconds) - OPTIMIZED for 1L+ records
        try {
            $conn->setAttribute(PDO::ATTR_TIMEOUT, 30);
        } catch (Exception $e) {
            // Some PDO drivers don't support ATTR_TIMEOUT, ignore
        }

        $stmt = $conn->prepare($sql);

        // Bind user parameter
        if ($forceSpecificUserForCount && $filterUserParam !== '') {
            $stmt->bindParam(':forcedUser', $filterUserParam, PDO::PARAM_STR);
        } elseif (isset($useAccessibleUsersForCount) && $useAccessibleUsersForCount) {
            for ($i = 0; $i < count($accessibleUsers); $i++) {
                $stmt->bindValue(":accessible_user_count_$i", $accessibleUsers[$i], PDO::PARAM_STR);
            }
        } else {
            $stmt->bindParam(':userUniqueId', $useruniqueId);
        }

        // Bind common parameters
        $bindCommonParams($stmt);

        // today_collection now uses CURDATE() directly — no extra bindings needed.

        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $count = (int) ($result['count'] ?? 0);

        echo json_encode(['count' => $count, 'type' => $countType]);
    } catch (PDOException $e) {
        error_log("Error fetching tag count {$countType}: " . $e->getMessage());
        echo json_encode(['count' => 0, 'error' => $e->getMessage()]);
    }
    exit;
}

// Handle the GET request for data fetching
// Debugging: Log incoming parameters
try {
    // Get query parameters
    $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
    $rowsPerPage = isset($_GET['rowsPerPage']) ? (int) $_GET['rowsPerPage'] : 10;
    $searchQuery = isset($_GET['searchQuery']) ? $_GET['searchQuery'] : '';
    // Normalise filter names (handles minor variants for Follow Up etc.)
    $filter = isset($_GET['filter']) ? trim($_GET['filter']) : '';
    if (in_array($filter, ['followupLeads', 'followup', 'follow-up', 'FollowUp'], true)) {
        $filter = 'followLeads';
    }
    $multiFilters = isset($_GET['multiFilters']) ? json_decode($_GET['multiFilters'], true) : [];
    $managerToggle = isset($_GET['managerToggle']) ? (int) $_GET['managerToggle'] : 0;
    $filterUserParam = isset($_GET['filterUser']) ? trim($_GET['filterUser']) : '';
    $managerViewParam = isset($_GET['managerView']) ? filter_var($_GET['managerView'], FILTER_VALIDATE_BOOLEAN) : false;
    $createdAtSortDirection = isset($_GET['order_dir']) ? strtolower(trim($_GET['order_dir'])) : '';
    if (!in_array($createdAtSortDirection, ['asc', 'desc'], true)) {
        $createdAtSortDirection = '';
    }
    // Prefer the raw 'start' offset (sent by DataTables AND lazy scroll fetches).
    // Lazy fetches set start=exactRowOffset; page-based fallback is only for
    // requests that don't supply start (legacy callers).
    if (isset($_GET['start']) && is_numeric($_GET['start'])) {
        $startRow = (int) $_GET['start'];
    } else {
        $startRow = ($page - 1) * $rowsPerPage;
    }


    $whereClauses = [];

    $startDate = !empty($multiFilters['start_date']) ? $multiFilters['start_date'] . ' 00:00:00' : null;
    $endDate = !empty($multiFilters['end_date']) ? $multiFilters['end_date'] . ' 23:59:59' : null;

    // OPTIMIZED Base query - Using LEFT JOIN with history_h filter for performance
    // This ensures each lead appears only once, matching the COUNT(DISTINCT) logic in badge counts
    // OPTIMIZED: history_h filter in JOIN condition for better index usage
    $baseQuery = "
    SELECT sud.*, ur.id AS remark_id, ur.status AS user_status, ur.remarks AS user_remarks, 
    ur.assign_project_name, ur.user_unique_id, ur.lead_identity, ur.budget, ur.location_status,
    ur.follow_up_date, ur.follow_up_time, ur.updated_at, ur.whatsapp_history,
    CASE
        WHEN ur.assigned_by IS NOT NULL
             AND (LOWER(ur.assigned_by) LIKE '%superadmin%' OR LOWER(ur.assigned_by) LIKE '%super admin%')
        THEN DATE_SUB(ur.created_at, INTERVAL 330 MINUTE)
        ELSE ur.created_at
    END AS created_at,
    CASE 
        WHEN ur.status = 'Pending' THEN DATEDIFF(NOW(), ur.created_at)
        ELSE NULL 
    END AS days_untouched,
    CASE 
    WHEN ur.status IN ('Follow Up', 'RNR', 'Call Back', 'Pending', 'Interested', 'Fix Site Visit', 'Site Visit Done', 'VC Done', 'Re site visit', 'Not Connected') 
    AND ur.status != 'Converted'
    AND ur.status != 'Already Booked'
    AND ur.follow_up_date IS NOT NULL 
    AND ur.follow_up_time IS NOT NULL 
    AND CONCAT(ur.follow_up_date, ' ', ur.follow_up_time) < DATE_SUB(NOW(), INTERVAL 1 DAY)
    THEN 1 
    ELSE 0 
    END AS is_overdue,
    CASE 
        WHEN ur.status IN ('Follow Up', 'RNR', 'Call Back', 'Pending', 'Interested', 'Fix Site Visit', 'Site Visit Done', 'VC Done', 'Re site visit', 'Not Connected') 
        AND ur.status != 'Converted'
        AND ur.status != 'Already Booked'
        AND ur.follow_up_date IS NOT NULL 
        AND ur.follow_up_time IS NOT NULL 
        AND CONCAT(ur.follow_up_date, ' ', ur.follow_up_time) < DATE_SUB(NOW(), INTERVAL 1 DAY)
        THEN DATEDIFF(NOW(), CONCAT(ur.follow_up_date, ' ', ur.follow_up_time))
        ELSE NULL 
    END AS overdue_from_days
    FROM shi_upload_data sud
    LEFT JOIN user_remarks ur ON sud.id = ur.upload_data_id AND ur.history_h = 0
";

    // OPTIMIZED: Cache user role lookup (used in multiple places)
    static $userRoleCacheMain = [];
    $cacheKeyMain = $useruniqueId;
    if (!isset($userRoleCacheMain[$cacheKeyMain])) {
        $stmt = $conn->prepare("SELECT user_type FROM accounts WHERE tablename = :tablename LIMIT 1");
        $stmt->bindParam(':tablename', $useruniqueId, PDO::PARAM_STR);
        $stmt->execute();
        $currentUserRow = $stmt->fetch(PDO::FETCH_ASSOC);
        $userRoleCacheMain[$cacheKeyMain] = $currentUserRow['user_type'] ?? 'user';
    }
    $currentUserType = $userRoleCacheMain[$cacheKeyMain];

    $normalizedRole = normalize_role_update($currentUserType);
    $canUseManagerToggle = ($normalizedRole !== 'user');

    // OPTIMIZED: Cache accessible users (expensive operation)
    static $accessibleUsersCacheMain = [];
    $usersCacheKeyMain = $useruniqueId . '_' . $currentUserType;
    if (!isset($accessibleUsersCacheMain[$usersCacheKeyMain])) {
        $accessibleUsersCacheMain[$usersCacheKeyMain] = get_accessible_users_update($conn, $useruniqueId, $currentUserType);
    }
    $accessibleUsers = $accessibleUsersCacheMain[$usersCacheKeyMain];

    $forceSpecificUser = false;
    $forcedUserPlaceholderAdded = false; // Track whether :forcedUser placeholder was added to WHERE clause
    if ($filterUserParam !== '' && in_array($filterUserParam, $accessibleUsers, true)) {
        // Honor explicit user selection coming from dashboard
        $whereClauses[] = "ur.user_unique_id = :forcedUser";
        $forceSpecificUser = true;
        $forcedUserPlaceholderAdded = true;
    } elseif ($managerToggle === 1 && $canUseManagerToggle) {
        // Team view: show data for subordinates only (exclude current user)
        error_log("📋 Team view for user=$useruniqueId: all_accessible=" . implode(',', $accessibleUsers));

        // Remove current user from accessible users for team view
        $subordinateUsers = array_filter($accessibleUsers, function ($user) use ($useruniqueId) {
            return $user !== $useruniqueId;
        });

        error_log("📋 Subordinates only (excluding $useruniqueId): " . implode(',', $subordinateUsers));

        if (count($subordinateUsers) > 0) {
            $placeholders = [];
            for ($i = 0; $i < count($subordinateUsers); $i++) {
                $placeholders[] = ":accessible_user_$i";
            }
            $whereClauses[] = "ur.user_unique_id IN (" . implode(',', $placeholders) . ")";
            $useAccessibleUsers = true;
            $accessibleUsers = array_values($subordinateUsers); // Re-index array
        } else {
            // No subordinates: fall back to own data instead of returning empty
            $whereClauses[] = "ur.user_unique_id = :userUniqueId";
            $useAccessibleUsers = false;
        }
    } else {
        // Individual view or user role: show only own data
        $whereClauses[] = "ur.user_unique_id = :userUniqueId";
        $useAccessibleUsers = false;
    }

    // Filters
    // Determine which user parameter to use for myLeads filter
    $myLeadsUserParam = ($forceSpecificUser && $filterUserParam !== '') ? ':forcedUser' : ':userUniqueId';
    $filterConditions = [
        'myLeads' => "ur.user_unique_id = {$myLeadsUserParam}",
        'bookedLeads' => "ur.status = 'Converted'",
        'droppedLeads' => "ur.status IN ('Not Interested', 'Fake')",
        'activeLeads' => "ur.status NOT IN ('Not Interested', 'Fake', 'Already Booked', 'Converted') AND ur.status IS NOT NULL AND ur.status != ''",
        'freshLeads' => "ur.created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)",

        'pendingLeads' => "ur.status = 'Pending'",
        // Follow Up: allow case/whitespace variants
        'followLeads' => "LOWER(TRIM(ur.status)) = 'follow up'",
        'overdueLeads' => "ur.status IN ('Follow Up', 'RNR', 'Call Back', 'Pending', 'Interested', 'Fix Site Visit', 'Site Visit Done', 'VC Done', 'Re site visit', 'Not Connected') AND ur.status != 'Converted' AND ur.status != 'Already Booked' AND ur.follow_up_date IS NOT NULL AND ur.follow_up_time IS NOT NULL AND CONCAT(ur.follow_up_date, ' ', ur.follow_up_time) < DATE_SUB(NOW(), INTERVAL 1 DAY)",
        'Fake' => "ur.status = 'Fake'",
        'RNR' => "ur.status = 'RNR'",
        'Call Back' => "ur.status = 'Call Back'",
        'Already Booked' => "ur.status = 'Already Booked'",
        'Not Interested' => "ur.status = 'Not Interested'",
        'Interested' => "ur.status = 'Interested'",
        // Special logic for Fix Site Visit: Count if "Fix Site Visit" appears in history AND "Site Visit Done" does NOT appear after it in history
        'Fix Site Visit' => "ur.history IS NOT NULL AND JSON_TYPE(ur.history) = 'ARRAY' AND JSON_SEARCH(ur.history, 'one', 'Fix Site Visit', NULL, '$[*].status') IS NOT NULL AND (JSON_SEARCH(ur.history, 'one', 'Site Visit Done', NULL, '$[*].status') IS NULL OR CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(JSON_SEARCH(ur.history, 'one', 'Site Visit Done', NULL, '$[*].status'), '[', -1), ']', 1) AS UNSIGNED) < CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(JSON_SEARCH(ur.history, 'one', 'Fix Site Visit', NULL, '$[*].status'), '[', -1), ']', 1) AS UNSIGNED))",
        // Special logic for Site Visit Done: Count if "Site Visit Done" appears anywhere in history (regardless of current status)
        'Site Visit Done' => "ur.history IS NOT NULL AND JSON_TYPE(ur.history) = 'ARRAY' AND JSON_SEARCH(ur.history, 'one', 'Site Visit Done', NULL, '$[*].status') IS NOT NULL",
        'Converted' => "ur.status = 'Converted'",
        'Re site visit' => "ur.status = 'Re site visit'",
        'NQFTP' => "ur.status = 'NQFTP'",
        'Not Connected' => "ur.status = 'Not Connected'",
        'paidAds' => "LOWER(TRIM(sud.source_of_lead)) IN ('google ads', 'facebook ads', 'magicbricks ads', '99acres ads', 'housing.com ads')",
        'SHI_D' => "(sud.source_of_lead IS NULL OR LOWER(TRIM(sud.source_of_lead)) NOT IN ('google ads', 'facebook ads', 'magicbricks ads', '99acres ads', 'housing.com ads'))",
        'today_collection' => "(
            LOWER(TRIM(ur.status)) IN ('follow up', 'interested', 'call back', 'rnr', 'fix site visit')
            AND (
                -- Lead HAS history: trust only the last history entry's followUpDate (user-set).
                -- This prevents skip-bumped leads (column=today, history=Apr 25) from showing.
                (
                    JSON_TYPE(ur.history) = 'ARRAY'
                    AND JSON_LENGTH(ur.history) > 0
                    AND DATE(STR_TO_DATE(
                        JSON_UNQUOTE(JSON_EXTRACT(ur.history, CONCAT('\$[', JSON_LENGTH(ur.history) - 1, '].followUpDate'))),
                        '%Y-%m-%d'
                    )) = CURDATE()
                )
                OR
                -- Lead has NO history yet: fall back to follow_up_date column.
                (
                    (ur.history IS NULL OR JSON_LENGTH(ur.history) = 0)
                    AND ur.follow_up_date IS NOT NULL
                    AND DATE(ur.follow_up_date) = CURDATE()
                )
            )
        )"
    ];

    // Apply filter only if it's compatible with current view mode
    if (!empty($filter) && isset($filterConditions[$filter])) {
        // Special handling for 'myLeads' filter
        if ($filter === 'myLeads') {
            // Skip 'myLeads' filter in team view or when a specific user is already forced
            $currentRole = strtolower(trim((string) $userType));
            if (in_array($currentRole, ['promoter', 'superuseradmin', 'admin'], true)) {
                // Elevated roles see all leads
                $whereClauses[] = "1=1";
                $useAccessibleUsers = false;
                $forceSpecificUser = false;
            } elseif ($managerToggle === 1 && $canUseManagerToggle) {
                // In team view, 'myLeads' filter doesn't make sense and causes binding errors
                error_log("⚠️ 'myLeads' filter skipped in team view - showing all team data instead");
            } elseif ($forceSpecificUser && $filterUserParam !== '') {
                // When a specific user is forced, 'myLeads' filter is redundant (already filtering by that user)
                error_log("⚠️ 'myLeads' filter skipped when specific user is forced - already filtering by user");
            } else {
                // DO NOT ADD it again! It was already added at line 5730. 
                // Adding it twice causes PDO HY093 Invalid parameter number error
                // $whereClauses[] = $filterConditions[$filter];
            }
        } else {
            $whereClauses[] = $filterConditions[$filter];
        }
    } else {
        // Unrecognised filter names should not bypass filtering logic
        $filter = '';
    }

    // Date Range
    // if (!empty($startDate) && !empty($endDate)) {
    //     $whereClauses[] = "ur.created_at BETWEEN :startDate AND :endDate";
    // }
    if (!empty($startDate) && !empty($endDate)) {
        $whereClauses[] = "ur.created_at BETWEEN :startDate AND :endDate";
    }

    // Multi-filters
    $allowedFilters = [
        'name' => 'sud.name',
        'email' => 'sud.email',
        'id' => 'sud.id',
        'created_at' => 'ur.created_at',
        'number' => 'sud.number',
        'location' => 'ur.location_status',
        'budget' => 'ur.budget',
        'remarks' => 'ur.remarks',
        'updated_at' => 'ur.updated_at',
        'source_of_lead' => 'LOWER(TRIM(sud.source_of_lead))',
        'status' => 'ur.status',
        'assign_project_name' => 'ur.assign_project_name',
        'user' => 'ur.user_unique_id',
        'lead_identity' => 'ur.lead_identity'
    ];

    // Track bindings for status so we can apply history-aware matching (Fix Site Visit, Site Visit Done)
    $statusFilterBindings = [];

    if (!empty($multiFilters)) {
        foreach ($multiFilters as $column => $value) {
            if (empty($value) || !isset($allowedFilters[$column])) {
                continue;
            }

            // History-aware status filtering to include past Fix Site Visit / Site Visit Done
            if ($column === 'status') {
                $statusValues = is_array($value)
                    ? $value
                    : (strpos($value, ',') !== false ? array_map('trim', explode(',', $value)) : [$value]);
                $statusValues = array_values(array_filter($statusValues, function ($v) {
                    return $v !== '';
                }));

                if (!empty($statusValues)) {
                    $statusConditions = [];
                    foreach ($statusValues as $idx => $statusVal) {
                        $paramName = ":filter_status_{$idx}";
                        $statusFilterBindings[$paramName] = $statusVal;

                        if (strcasecmp($statusVal, 'Fix Site Visit') === 0) {
                            $statusConditions[] = "(ur.status = {$paramName} OR (ur.history IS NOT NULL AND JSON_TYPE(ur.history) = 'ARRAY' AND JSON_SEARCH(ur.history, 'one', 'Fix Site Visit', NULL, '$[*].status') IS NOT NULL AND (JSON_SEARCH(ur.history, 'one', 'Site Visit Done', NULL, '$[*].status') IS NULL OR CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(JSON_SEARCH(ur.history, 'one', 'Site Visit Done', NULL, '$[*].status'), '[', -1), ']', 1) AS UNSIGNED) < CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(JSON_SEARCH(ur.history, 'one', 'Fix Site Visit', NULL, '$[*].status'), '[', -1), ']', 1) AS UNSIGNED))))";
                        } elseif (strcasecmp($statusVal, 'Site Visit Done') === 0) {
                            $statusConditions[] = "(ur.status = {$paramName} OR (ur.history IS NOT NULL AND JSON_TYPE(ur.history) = 'ARRAY' AND JSON_SEARCH(ur.history, 'one', 'Site Visit Done', NULL, '$[*].status') IS NOT NULL))";
                        } else {
                            $statusConditions[] = "{$allowedFilters[$column]} = {$paramName}";
                        }
                    }

                    if (!empty($statusConditions)) {
                        $whereClauses[] = '(' . implode(' OR ', $statusConditions) . ')';
                    }
                }

                continue;
            }

            // Default handling for non-status filters
            if ($column === 'assign_project_name') {
                $values = is_array($value) ? $value : [$value];
            } else {
                $normalizedValue = is_array($value) ? implode(',', $value) : $value;
                $values = (strpos($normalizedValue, ',') !== false)
                    ? array_map('trim', explode(',', $normalizedValue))
                    : [$normalizedValue];
            }

            $values = array_values(array_filter($values, function ($v) {
                return $v !== '' && $v !== null;
            }));

            if (empty($values)) {
                continue;
            }

            if (count($values) > 1) {
                // Multiple values: use IN clause
                $placeholders = [];
                foreach ($values as $idx => $val) {
                    $placeholders[] = ":filter_{$column}_{$idx}";
                }
                $whereClauses[] = "{$allowedFilters[$column]} IN (" . implode(',', $placeholders) . ")";
            } else {
                // Single value: use exact match
                $whereClauses[] = "{$allowedFilters[$column]} = :filter_{$column}";
            }
        }
    }

    // Column filters from DataTables
    if (isset($_GET['columns'])) {
        $columns = $_GET['columns'];
        $columnFields = [
            1 => 'sud.name',
            2 => 'ur.assign_project_name',
            3 => 'sud.id',
            4 => 'ur.created_at',
            5 => 'sud.email',
            6 => 'ur.location_status',
            7 => 'ur.budget',
            8 => 'ur.remarks',
            9 => 'ur.updated_at',
            10 => 'ur.user_unique_id',
            11 => 'sud.source_of_lead'
        ];
        foreach ($columns as $i => $col) {
            if (!empty($col['search']['value']) && isset($columnFields[$i])) {
                $searchVal = $col['search']['value'];
                $field = $columnFields[$i];
                if (strpos($searchVal, '|') !== false) {
                    $vals = explode('|', $searchVal);
                    $placeholders = [];
                    foreach ($vals as $idx => $val) {
                        $placeholders[] = ":col{$i}_{$idx}";
                    }
                    $whereClauses[] = "$field IN (" . implode(',', $placeholders) . ")";
                } else {
                    $whereClauses[] = "$field = :col{$i}";
                }
            }
        }
    }

    if (!empty($searchQuery)) {
        $searchTerm = '%' . trim($searchQuery) . '%';
        // OPTIMIZED: Use unique parameter binding for all LIKE clauses to avoid HY093 error
        $searchParts = [
            "sud.name LIKE :search0",
            "sud.email LIKE :search1",
            "sud.number LIKE :search2",
            "sud.location LIKE :search3",
            "sud.source_of_lead LIKE :search4",
            "ur.status LIKE :search5",
            "ur.assign_project_name LIKE :search6",
            "ur.user_unique_id LIKE :search7"
        ];
        $whereClauses[] = '(' . implode(' OR ', $searchParts) . ')';
    }

    // OPTIMIZED: Final WHERE clause - Add history_h filter first for optimal performance
    // This ensures we only process active records (history_h = 0)
    if (!empty($whereClauses)) {
        // Ensure history_h = 0 is always first in WHERE clause for optimal index usage
        $historyFilter = "ur.history_h = 0";
        $otherClauses = array_filter($whereClauses, function ($clause) {
            return strpos($clause, 'history_h') === false;
        });
        $baseQuery .= ' WHERE ' . $historyFilter . ' AND ' . implode(' AND ', $otherClauses);
    } else {
        // Even if no other filters, add history_h filter for performance
        $baseQuery .= ' WHERE ur.history_h = 0';
    }

    // Order
    if ($createdAtSortDirection !== '') {
        $orderClause = " ORDER BY ur.created_at " . strtoupper($createdAtSortDirection);
    } else {
        $orderClause = " ORDER BY ur.created_at DESC";
        if ($filter === 'RNR') {
            $orderClause = " ORDER BY ur.status = 'RNR' DESC, ur.updated_at ASC, ur.created_at DESC";
        } elseif ($filter === 'today_collection') {
            $orderClause = " ORDER BY COALESCE(
            STR_TO_DATE(ur.follow_up_time, '%H:%i'),
            STR_TO_DATE(
                JSON_UNQUOTE(
                    JSON_EXTRACT(ur.history, CONCAT('$[', JSON_LENGTH(ur.history) - 1, '].followUpTime'))
                ), '%H:%i'
            )
        ) ASC";
        }
    }
    $baseQuery .= $orderClause;

    // Pagination
    $baseQuery .= " LIMIT :startRow, :rowsPerPage";

    // Prepare and bind
    $stmt = $conn->prepare($baseQuery);

    // Handle hierarchy-based parameter binding
    // Always bind :forcedUser if the placeholder was added, regardless of $forceSpecificUser state
    if ($forcedUserPlaceholderAdded && $filterUserParam !== '') {
        $stmt->bindParam(':forcedUser', $filterUserParam, PDO::PARAM_STR);
    }
    if (isset($useAccessibleUsers) && $useAccessibleUsers) {
        // Bind accessible users array for IN clause using named parameters
        for ($i = 0; $i < count($accessibleUsers); $i++) {
            $stmt->bindValue(":accessible_user_$i", $accessibleUsers[$i], PDO::PARAM_STR);
        }
    } elseif (!$forcedUserPlaceholderAdded) {
        // Only bind :userUniqueId if it's actually in the query to avoid HY093 Invalid parameter number error
        if (strpos($baseQuery, ':userUniqueId') !== false) {
            $stmt->bindParam(':userUniqueId', $useruniqueId, PDO::PARAM_STR);
        }
    }

    if (!empty($startDate) && !empty($endDate)) {
        $stmt->bindParam(':startDate', $startDate);
        $stmt->bindParam(':endDate', $endDate);
    }

    if (!empty($statusFilterBindings)) {
        foreach ($statusFilterBindings as $param => $val) {
            $stmt->bindValue($param, $val, PDO::PARAM_STR);
        }
    }

    if (!empty($multiFilters)) {
        foreach ($multiFilters as $column => $value) {
            if (empty($value) || !isset($allowedFilters[$column])) {
                continue;
            }

            if ($column === 'status') {
                continue; // Status handled separately for history-aware matching
            }

            if ($column === 'assign_project_name') {
                $values = is_array($value) ? $value : [$value];
            } else {
                $normalizedValue = is_array($value) ? implode(',', $value) : $value;
                $values = (strpos($normalizedValue, ',') !== false)
                    ? array_map('trim', explode(',', $normalizedValue))
                    : [$normalizedValue];
            }

            $values = array_values(array_filter($values, function ($v) {
                return $v !== '' && $v !== null;
            }));

            if (count($values) > 1) {
                // Multiple values: bind each value for IN clause
                foreach ($values as $idx => $val) {
                    $stmt->bindValue(":filter_{$column}_{$idx}", $val, PDO::PARAM_STR);
                }
            } elseif (!empty($values)) {
                // Single value: use exact match
                $normalizedVal = ($column === 'source_of_lead')
                    ? strtolower(trim($values[0]))
                    : $values[0];
                $stmt->bindValue(":filter_$column", $normalizedVal, PDO::PARAM_STR);
            }
        }
    }

    if (isset($_GET['columns'])) {
        $columns = $_GET['columns'];
        $columnFields = [
            1 => 'sud.name',
            2 => 'ur.assign_project_name',
            3 => 'sud.id',
            4 => 'ur.created_at',
            5 => 'sud.email',
            6 => 'ur.location_status',
            7 => 'ur.budget',
            8 => 'ur.remarks',
            9 => 'ur.updated_at',
            10 => 'ur.user_unique_id',
            11 => 'sud.source_of_lead'
        ];
        foreach ($columns as $i => $col) {
            if (!empty($col['search']['value']) && isset($columnFields[$i])) {
                $searchVal = $col['search']['value'];
                if (strpos($searchVal, '|') !== false) {
                    $vals = explode('|', $searchVal);
                    foreach ($vals as $idx => $val) {
                        $stmt->bindValue(":col{$i}_{$idx}", $val, PDO::PARAM_STR);
                    }
                } else {
                    $stmt->bindValue(":col{$i}", $searchVal, PDO::PARAM_STR);
                }
            }
        }
    }

    // OPTIMIZED: Bind search parameter only if search exists
    if (!empty($searchQuery)) {
        $searchTerm = '%' . trim($searchQuery) . '%';
        for ($s = 0; $s <= 7; $s++) {
            $stmt->bindParam(":search{$s}", $searchTerm, PDO::PARAM_STR);
        }
    }

    $stmt->bindParam(':startRow', $startRow, PDO::PARAM_INT);
    $stmt->bindParam(':rowsPerPage', $rowsPerPage, PDO::PARAM_INT);

    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($data as &$row) {
        $wa_interest = 'none';
        if (!empty($row['whatsapp_history'])) {
            if (preg_match_all('/\[Interest Status:\s*([^\]]+)\]/i', $row['whatsapp_history'], $matches)) {
                $status = strtoupper(trim(end($matches[1])));
                if (strpos($status, 'NOT INTERESTED') !== false) {
                    $wa_interest = 'not_interested';
                } elseif (strpos($status, 'INTERESTED') !== false) {
                    $wa_interest = 'interested';
                } elseif (strpos($status, 'NEUTRAL') !== false) {
                    $wa_interest = 'neutral';
                }
            }
        }
        $row['wa_interest'] = $wa_interest;
        unset($row['whatsapp_history']); // Free bandwidth as UI fetches it independently later
    }

    // OPTIMIZED: Total count query - Same WHERE clause structure as main query
    $countQuery = "
        SELECT COUNT(*) AS totalRows
        FROM shi_upload_data sud
        LEFT JOIN user_remarks ur ON sud.id = ur.upload_data_id AND ur.history_h = 0
    ";
    // OPTIMIZED: Use same WHERE clause structure as main query (history_h first)
    if (!empty($whereClauses)) {
        $historyFilter = "ur.history_h = 0";
        $otherClauses = array_filter($whereClauses, function ($clause) {
            return strpos($clause, 'history_h') === false;
        });
        $countQuery .= ' WHERE ' . $historyFilter . ' AND ' . implode(' AND ', $otherClauses);
    } else {
        $countQuery .= ' WHERE ur.history_h = 0';
    }
    $countStmt = $conn->prepare($countQuery);

    // Handle hierarchy-based parameter binding for count query
    // Always bind :forcedUser if the placeholder was added, regardless of $forceSpecificUser state
    if ($forcedUserPlaceholderAdded && $filterUserParam !== '') {
        $countStmt->bindParam(':forcedUser', $filterUserParam, PDO::PARAM_STR);
    }
    if (isset($useAccessibleUsers) && $useAccessibleUsers) {
        // Bind accessible users array for IN clause using named parameters
        for ($i = 0; $i < count($accessibleUsers); $i++) {
            $countStmt->bindValue(":accessible_user_$i", $accessibleUsers[$i], PDO::PARAM_STR);
        }
    } elseif (!$forcedUserPlaceholderAdded) {
        // Only bind :userUniqueId if it's actually in the query to avoid HY093 Invalid parameter number error
        if (strpos($countQuery, ':userUniqueId') !== false) {
            $countStmt->bindParam(':userUniqueId', $useruniqueId, PDO::PARAM_STR);
        }
    }

    if (!empty($startDate) && !empty($endDate)) {
        $countStmt->bindParam(':startDate', $startDate);
        $countStmt->bindParam(':endDate', $endDate);
    }

    if (!empty($statusFilterBindings)) {
        foreach ($statusFilterBindings as $param => $val) {
            $countStmt->bindValue($param, $val, PDO::PARAM_STR);
        }
    }

    if (!empty($multiFilters)) {
        foreach ($multiFilters as $column => $value) {
            if (empty($value) || !isset($allowedFilters[$column])) {
                continue;
            }

            if ($column === 'status') {
                continue; // Status handled separately for history-aware matching
            }

            if ($column === 'assign_project_name') {
                $values = is_array($value) ? $value : [$value];
            } else {
                $normalizedValue = is_array($value) ? implode(',', $value) : $value;
                $values = (strpos($normalizedValue, ',') !== false)
                    ? array_map('trim', explode(',', $normalizedValue))
                    : [$normalizedValue];
            }

            $values = array_values(array_filter($values, function ($v) {
                return $v !== '' && $v !== null;
            }));

            if (count($values) > 1) {
                // Multiple values: bind each value for IN clause
                foreach ($values as $idx => $val) {
                    $countStmt->bindValue(":filter_{$column}_{$idx}", $val, PDO::PARAM_STR);
                }
            } elseif (!empty($values)) {
                // Single value: use exact match
                $normalizedVal = ($column === 'source_of_lead')
                    ? strtolower(trim($values[0]))
                    : $values[0];
                $countStmt->bindValue(":filter_$column", $normalizedVal, PDO::PARAM_STR);
            }
        }
    }

    if (isset($_GET['columns'])) {
        $columns = $_GET['columns'];
        $columnFields = [
            1 => 'sud.name',
            2 => 'ur.assign_project_name',
            3 => 'sud.id',
            4 => 'ur.created_at',
            5 => 'sud.email',
            6 => 'ur.location_status',
            7 => 'ur.budget',
            8 => 'ur.remarks',
            9 => 'ur.updated_at',
            10 => 'ur.user_unique_id',
            11 => 'sud.source_of_lead'
        ];
        foreach ($columns as $i => $col) {
            if (!empty($col['search']['value']) && isset($columnFields[$i])) {
                $searchVal = $col['search']['value'];
                if (strpos($searchVal, '|') !== false) {
                    $vals = explode('|', $searchVal);
                    foreach ($vals as $idx => $val) {
                        $countStmt->bindValue(":col{$i}_{$idx}", $val, PDO::PARAM_STR);
                    }
                } else {
                    $countStmt->bindValue(":col{$i}", $searchVal, PDO::PARAM_STR);
                }
            }
        }
    }

    // OPTIMIZED: Bind search parameter for count query
    if (!empty($searchQuery)) {
        $searchTerm = '%' . trim($searchQuery) . '%';
        for ($s = 0; $s <= 7; $s++) {
            $countStmt->bindParam(":search{$s}", $searchTerm, PDO::PARAM_STR);
        }
    }

    $countStmt->execute();
    $totalRows = $countStmt->fetch(PDO::FETCH_ASSOC)['totalRows'];

    // Add hierarchy information to response
    $allAccessibleUsers = get_accessible_users_update($conn, $useruniqueId, $currentUserType);
    $hierarchyInfo = [
        'current_user_level' => role_level_update(normalize_role_update($currentUserType)),
        'accessible_user_count' => count($allAccessibleUsers),
        'can_manage_team' => count($allAccessibleUsers) > 1,
        'current_user_role' => normalize_role_update($currentUserType),
        'can_use_manager_toggle' => $canUseManagerToggle,
        'manager_toggle_enabled' => $managerToggle === 1 && $canUseManagerToggle
    ];

    // Output the data + count + hierarchy info
    echo json_encode([
        'data' => $data,
        'totalRows' => $totalRows,
        'currentPage' => $page,
        'rowsPerPage' => $rowsPerPage,
        'hierarchyInfo' => $hierarchyInfo,
        'managerToggleEnabled' => $managerToggle === 1
    ]);

} catch (Exception $e) {
    // OPTIMIZED: Return valid JSON error response for DataTables
    error_log("DataTables Ajax error: " . $e->getMessage());
    echo json_encode([
        'data' => [],
        'totalRows' => 0,
        'error' => $e->getMessage()
    ]);
}
?>