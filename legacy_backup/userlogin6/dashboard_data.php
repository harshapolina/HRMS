<?php
// dashboard_data.php - Fixed version with proper date filtering for aggregated analytics
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

session_start();
include 'config.php';

// Profiling Tracker
$start_time = microtime(true);
$profiling = [];
function recordProfiling($label, &$profiling, $start_time)
{
    global $last_profile_time;
    if (!isset($last_profile_time))
        $last_profile_time = $start_time;
    $now = microtime(true);
    $profiling[$label] = round(($now - $last_profile_time) * 1000, 2) . ' ms';
    $last_profile_time = $now;
}

// Ensure user is logged in
if (!isset($_SESSION['tablename'])) {
    echo json_encode(['status' => 'error', 'message' => 'User not logged in']);
    exit;
}

// IMPORTANT: Release session lock to allow concurrent batch fetches from frontend/loopback!
// Without this, PHP natively serializes all AJAX requests sharing the same cookie session_id.
session_write_close();

// User ID
// Priority:
// 1) explicit user_id from query (e.g., manager selecting a user)
// 2) impersonate param (used when superadmin embeds user dashboard)
// 3) fallback to session tablename
$user_unique_id = null;
$explicitly_requested_user = false;

if (isset($_GET['user_id']) && $_GET['user_id'] !== '') {
    $user_unique_id = $_GET['user_id'];
    $explicitly_requested_user = true;
} elseif (isset($_GET['impersonate']) && $_GET['impersonate'] !== '') {
    $user_unique_id = $_GET['impersonate'];
} else {
    $user_unique_id = $_SESSION['tablename'];
}

$current_user_type = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : '';

// Request Type Flags
$is_total_request = isset($_GET['total']) && $_GET['total'] === 'true';
$has_date_range = !empty($_GET['start_date']) && !empty($_GET['end_date']);
$is_aggregated_analytics = isset($_GET['aggregated_analytics']) && $_GET['aggregated_analytics'] === 'true';

// Date Range or Month/Year
$start_date = $has_date_range ? $_GET['start_date'] : null;
$end_date = $has_date_range ? $_GET['end_date'] : null;
$month = !$has_date_range ? (isset($_GET['month']) ? (int) $_GET['month'] : (int) date('n')) : null;
$year = !$has_date_range ? (isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y')) : null;

// Convert $month and $year into boundary strings for SQL BETWEEN queries instead of numbers
if ($month && $year) {
    $month_start = sprintf("%04d-%02d-01 00:00:00", $year, $month);
    $month_end = date("Y-m-t 23:59:59", strtotime($month_start));

    // We override these so existing bindParam(':month', $month) injections work seamlessly!
    $month = $month_start;
    $year = $month_end;
}

// If requesting aggregated analytics with no explicit date range or month/year, default to ALL-TIME totals
if ($is_aggregated_analytics && !$has_date_range && !isset($_GET['month']) && !isset($_GET['year'])) {
    $is_total_request = true;
    $month = null;
    $year = null;
}

$project_filter_list = [];
$project_filter_clause_ur = "";
$project_filter_params_positional = [];
$project_filter_named_clause_ur = "";
$project_filter_named_params = [];
$booking_project_filter_clause = "";
$booking_project_filter_params = [];
$booking_project_filter_named_clause = "";

$date_column_preference = 'created_at';
if (isset($_GET['date_column'])) {
    $requestedColumn = strtolower(trim((string) $_GET['date_column']));
    if ($requestedColumn === 'updated_at') {
        $date_column_preference = 'updated_at';
    }
}

$ur_date_field = 'ur.' . $date_column_preference;
$eoi_date_field_raw = 'created_at';
$eoi_date_field = applyDateFieldPreference($eoi_date_field_raw);

if (!empty($_GET['project_filter'])) {
    $project_filter_list = array_filter(array_map('trim', explode(',', $_GET['project_filter'])));

    if (!empty($project_filter_list)) {
        $positional_placeholders = str_repeat('?,', count($project_filter_list) - 1) . '?';
        $project_filter_clause_ur = " AND ur.assign_project_name IN ($positional_placeholders)";
        $project_filter_params_positional = $project_filter_list;

        // Reuse the same filter for bookings (admintable.project)
        $booking_project_filter_clause = " AND project IN ($positional_placeholders)";
        $booking_project_filter_params = $project_filter_list;

        $named_placeholders = [];
        foreach ($project_filter_list as $idx => $projectName) {
            $placeholder = ":project_filter_{$idx}";
            $named_placeholders[] = $placeholder;
            $project_filter_named_params[$placeholder] = $projectName;
        }

        if (!empty($named_placeholders)) {
            $project_filter_named_clause_ur = " AND ur.assign_project_name IN (" . implode(',', $named_placeholders) . ")";
            $booking_project_filter_named_clause = " AND project IN (" . implode(',', $named_placeholders) . ")";
        }
    }
}

$response = [
    'status' => 'success',
    'datetime' => date('h:i a, d F Y'),
    'ceo' => null,
    'manager' => null,
    'standings' => [],
    'total_eoi' => 0,
    'myLeads' => 0,
    'total_bookings' => 0,
    'total_revenue' => 0,
    'source_stats' => [
        'google_count' => 0,
        'facebook_count' => 0,
        'other_count' => 0
    ],
    'status_counts' => [
        'pending_count' => 0,
        'site_visit_done_count' => 0,
        'followup_count' => 0,
        'fix_site_visit_count' => 0
    ],
    'assigned_users' => [],
    'analytics' => [
        'detailed_status_counts' => [],
        'detailed_source_counts' => [],
        'total_leads' => 0,
        'conversion_rate' => 0,
        'todays_followup' => 0,
        'site_visits_pending' => 0
    ],
    'aggregated_analytics' => [
        'detailed_status_counts' => [],
        'detailed_source_counts' => [],
        'total_leads' => 0,
        'total_users' => 0,
        'total_bookings' => 0,
        'total_eoi' => 0,
        'conversion_rate' => 0,
        'todays_followup' => 0,
        'site_visits_pending' => 0,
        'user_wise_data' => []
    ],
    'hierarchy' => [
        'current_user' => null,
        'manager' => null,
        'ceo' => null,
        'chain' => []
    ],
    'profiling' => []
];

function applyDateFieldPreference($fieldExpression)
{
    global $date_column_preference;
    if ($date_column_preference === 'updated_at') {
        return str_replace('created_at', 'updated_at', $fieldExpression);
    }
    return $fieldExpression;
}

// Add this after the session start and before the main try-catch block

// Handle manager team request
if (isset($_GET['manager_team']) && $_GET['manager_team'] === 'true' && isset($_GET['manager_id'])) {
    $manager_id = $_GET['manager_id'];

    try {
        $config = new Config();
        $conn = $config->getConnection();

        // Check if current user can access the requested manager's team data
        if (!canAccessUserData($conn, $_SESSION['tablename'], $manager_id)) {
            echo json_encode(['status' => 'error', 'message' => 'Access denied']);
            exit;
        }

        $query = "SELECT tablename, username, useremail, user_type 
                  FROM accounts 
                  WHERE assign_user = ?
                    AND is_active = 1
                  ORDER BY username ASC";

        $stmt = $conn->prepare($query);
        $stmt->execute([$manager_id]);
        $all_team_members = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Filter team members based on access control and clean user types
        $team_members = [];
        foreach ($all_team_members as $member) {
            if (canAccessUserData($conn, $_SESSION['tablename'], $member['tablename'])) {
                $raw_type = $member['user_type'] ?? 'user';
                $cleaned_type = strtolower(trim(strval($raw_type)));

                if ($cleaned_type === 'm' || $cleaned_type === 'manager') {
                    $member['user_type'] = 'manager';
                } elseif ($cleaned_type === 'c' || $cleaned_type === 'ceo') {
                    $member['user_type'] = 'ceo';
                } else {
                    $member['user_type'] = 'user';
                }

                $team_members[] = $member;
            }
        }

        echo json_encode([
            'status' => 'success',
            'team_members' => $team_members
        ]);
        exit;

    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Error fetching team members: ' . $e->getMessage()
        ]);
        exit;
    }
}

// Handle fetch project names request
if (isset($_GET['fetch_project_names']) && $_GET['fetch_project_names'] === 'true') {
    try {
        $config = new Config();
        $conn = $config->getConnection();

        // Get unique project names from user_remarks table
        // Only fetch projects that the current user has access to
        $current_user_tablename = $_SESSION['tablename'];

        // Get current user details to determine access rights
        $currentUser = get_user_by_tablename($conn, $current_user_tablename);
        $currentRole = normalize_role($currentUser['user_type'] ?? 'user');

        if ($currentRole === 'promoter') {
            // Promoters can see all project names
            $query = "SELECT DISTINCT assign_project_name 
                      FROM user_remarks 
                      WHERE assign_project_name IS NOT NULL 
                        AND assign_project_name != '' 
                        AND assign_project_name != 'NULL'
                      ORDER BY assign_project_name ASC";
            $stmt = $conn->prepare($query);
            $stmt->execute();
        } else {
            // Other users can only see projects from users they have access to
            $subordinates = get_subordinate_user_ids($conn, $current_user_tablename);
            $user_ids = array_merge([$current_user_tablename], $subordinates);

            $placeholders = str_repeat('?,', count($user_ids) - 1) . '?';
            $query = "SELECT DISTINCT assign_project_name 
                      FROM user_remarks 
                      WHERE assign_project_name IS NOT NULL 
                        AND assign_project_name != '' 
                        AND assign_project_name != 'NULL'
                        AND user_unique_id IN ($placeholders)
                      ORDER BY assign_project_name ASC";
            $stmt = $conn->prepare($query);
            $stmt->execute($user_ids);
        }

        $projectNames = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Clean and filter project names
        $cleanProjectNames = array_filter(array_map('trim', $projectNames), function ($name) {
            return !empty($name) && $name !== 'NULL' && $name !== 'null';
        });

        echo json_encode([
            'status' => 'success',
            'project_names' => array_values($cleanProjectNames)
        ]);
        exit;

    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Error fetching project names: ' . $e->getMessage()
        ]);
        exit;
    }
}

// Handle fetch users by projects request
if (isset($_GET['fetch_users_by_projects']) && $_GET['fetch_users_by_projects'] === 'true') {
    try {
        $config = new Config();
        $conn = $config->getConnection();

        // Get project filter from request
        $project_list = [];
        if (isset($_GET['projects']) && !empty($_GET['projects'])) {
            if (is_array($_GET['projects'])) {
                $project_list = $_GET['projects'];
            } else {
                $project_list = array_map('trim', explode(',', $_GET['projects']));
            }
            $project_list = array_filter($project_list);
        }

        if (empty($project_list)) {
            echo json_encode([
                'status' => 'success',
                'users' => []
            ]);
            exit;
        }

        // Get current user details for access control
        $current_user_tablename = $_SESSION['tablename'];
        $currentUser = get_user_by_tablename($conn, $current_user_tablename);
        $currentRole = normalize_role($currentUser['user_type'] ?? 'user');

        // Build query to get users who have data for the selected projects
        $projectPlaceholders = str_repeat('?,', count($project_list) - 1) . '?';

        if ($currentRole === 'promoter') {
            // Promoters can see all users with data in these projects
            $query = "SELECT DISTINCT ur.user_unique_id, a.username, a.useremail, a.user_type
                      FROM user_remarks ur
                      JOIN accounts a ON a.tablename = ur.user_unique_id
                      WHERE ur.assign_project_name IN ($projectPlaceholders)
                        AND a.is_active = 1
                      ORDER BY a.username ASC";
            $stmt = $conn->prepare($query);
            $stmt->execute($project_list);
        } else {
            // Other users can only see users they have access to
            $query = "SELECT DISTINCT ur.user_unique_id, a.username, a.useremail, a.user_type
                      FROM user_remarks ur
                      JOIN accounts a ON a.tablename = ur.user_unique_id
                      WHERE ur.assign_project_name IN ($projectPlaceholders)
                        AND a.is_active = 1
                        AND (ur.user_unique_id = ? 
                             OR FIND_IN_SET(?, a.assign_user))
                      ORDER BY a.username ASC";
            $params = array_merge($project_list, [$current_user_tablename, $current_user_tablename]);
            $stmt = $conn->prepare($query);
            $stmt->execute($params);
        }

        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'status' => 'success',
            'users' => $users
        ]);
        exit;

    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Error fetching users by projects: ' . $e->getMessage()
        ]);
        exit;
    }
}

// Handle booking data request
if (isset($_GET['get_bookings']) && $_GET['get_bookings'] === 'true') {
    try {
        $config = new Config();
        $conn = $config->getConnection();

        // Get current user or selected user - match the parameter name from JavaScript
        $user_unique_id = isset($_GET['user']) && $_GET['user'] !== ''
            ? $_GET['user']
            : $_SESSION['tablename'];

        // Check access control for booking data
        if ($user_unique_id !== $_SESSION['tablename'] && !canAccessUserData($conn, $_SESSION['tablename'], $user_unique_id)) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Access denied: You do not have permission to view this user\'s booking data'
            ]);
            exit;
        }

        // Date filtering - use same logic as dashboard
        $has_date_range = !empty($_GET['start_date']) && !empty($_GET['end_date']);
        $is_total_request = false; // For monthly view, we always want filtered data
        $month = isset($_GET['month']) ? (int) $_GET['month'] : (int) date('n');
        $year = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');
        $start_date = $_GET['start_date'] ?? null;
        $end_date = $_GET['end_date'] ?? null;

        // Build date filter inline to match dashboard logic
        $dateFilter = "";
        if ($has_date_range) {
            $dateFilter = " AND booking_date BETWEEN :start_date AND :end_date";
        } else {
            $dateFilter = " AND MONTH(booking_date) = :month AND YEAR(booking_date) = :year";
        }

        // --- NEW: Expand hierarchy ---
        $subordinates = get_subordinate_user_ids($conn, $user_unique_id);
        $all_users = array_unique(array_merge([$user_unique_id], $subordinates));
        
        $placeholders = [];
        foreach ($all_users as $index => $u) {
            $placeholders[] = ":usr_$index";
        }
        $in_clause = implode(',', $placeholders);

        // Query to fetch booking data from admintable
        $query = "SELECT 
                    COALESCE(unit_no, '') as unit_no,
                    COALESCE(project, '') as project,
                    COALESCE(project_type, '') as project_type,
                    COALESCE(builder, '') as builder,
                    COALESCE(agreement_value, 0) as agreement_value,
                    COALESCE(cashback, 0) as cashback,
                    COALESCE(revenue, 0) as total_revenue,
                    COALESCE(crevenue, 0) as actual_revenue,
                    COALESCE(ccashback, 0) as commission,
                    COALESCE(customer_name, '') as customer_name,
                    COALESCE(booking_date) as booking_date
                  FROM admintable 
                  WHERE source_table IN ($in_clause)
                  AND astatus != 'Canceled'" .
            $dateFilter .
            " ORDER BY booking_date DESC LIMIT 100";

        $stmt = $conn->prepare($query);
        
        // Bind all users
        foreach ($all_users as $index => $u) {
            $stmt->bindValue(":usr_$index", $u, PDO::PARAM_STR);
        }

        // Bind date parameters to match dashboard logic
        if ($has_date_range) {
            $stmt->bindValue(':start_date', $start_date, PDO::PARAM_STR);
            $stmt->bindValue(':end_date', $end_date, PDO::PARAM_STR);
        } elseif (!$is_total_request) {
            $stmt->bindValue(':month', $month, PDO::PARAM_INT);
            $stmt->bindValue(':year', $year, PDO::PARAM_INT);
        }

        $stmt->execute();
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Format the data for display
        $formattedBookings = [];
        foreach ($bookings as $booking) {
            $formattedBookings[] = [
                'unit_no' => !empty($booking['unit_no']) ? $booking['unit_no'] : 'N/A',
                'project_type' => !empty($booking['project_type']) ? $booking['project_type'] : 'N/A',
                'customer_name' => !empty($booking['customer_name']) ? $booking['customer_name'] : 'N/A',
                'builder' => !empty($booking['builder']) ? $booking['builder'] : 'N/A',
                'project' => !empty($booking['project']) ? $booking['project'] : 'N/A',
                'agreement_value' => number_format(floatval($booking['agreement_value']), 2),
                'revenue' => number_format(floatval($booking['total_revenue']), 2),
                'actual_revenue' => number_format(floatval($booking['actual_revenue']), 2),
                'commission' => number_format(floatval($booking['commission']), 2),
                'cashback' => number_format(floatval($booking['cashback']), 2)
            ];
        }

        echo json_encode([
            'status' => 'success',
            'bookings' => $formattedBookings,
            'count' => count($formattedBookings),
            'debug' => [
                'user_id' => $user_unique_id,
                'month' => $month,
                'year' => $year,
                'has_date_range' => $has_date_range,
                'start_date' => $_GET['start_date'] ?? null,
                'end_date' => $_GET['end_date'] ?? null,
                'query' => $query,
                'date_filter' => $dateFilter
            ]
        ]);
        exit;

    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to fetch booking data: ' . $e->getMessage(),
            'debug' => [
                'user_id' => $user_unique_id ?? 'N/A',
                'month' => $month ?? 'N/A',
                'year' => $year ?? 'N/A',
                'line' => $e->getLine(),
                'file' => basename($e->getFile())
            ]
        ]);
        exit;
    }
}

// ---------- Helper functions to keep JSON shape chart-friendly ----------
// Coerce any numeric-ish value to int safely
function _to_int($v)
{
    if ($v === null)
        return 0;
    if (is_numeric($v))
        return (int) $v;
    if (is_string($v))
        return (int) preg_replace('/[^0-9\-]/', '', $v);
    return 0;
}

// Detect associative array
function _is_assoc(array $arr)
{
    if ($arr === [])
        return false;
    return array_keys($arr) !== range(0, count($arr) - 1);
}

// Flatten and aggregate status counts to [ ['status' => string, 'count' => int], ... ]
function normalize_status_counts($data)
{
    $totals = [];

    $flatten = function ($rows) use (&$flatten, &$totals) {
        if (!is_array($rows))
            return;
        // If this is an associative row, treat as single record
        if (_is_assoc($rows)) {
            // Try common key variants
            $status = $rows['status'] ?? $rows['status_name'] ?? $rows['label'] ?? null;
            $count = $rows['count'] ?? $rows['total'] ?? $rows['cnt'] ?? null;
            if ($status !== null) {
                $totals[(string) $status] = ($totals[(string) $status] ?? 0) + _to_int($count);
                return;
            }
            // Some payloads use a nested `data` property
            if (isset($rows['data']) && is_array($rows['data'])) {
                $flatten($rows['data']);
                return;
            }
        }
        // Otherwise iterate list
        foreach ($rows as $r) {
            if (is_array($r))
                $flatten($r);
        }
    };

    $flatten($data);

    // Build normalized list
    $out = [];
    foreach ($totals as $status => $count) {
        $label = (string) $status;
        $out[] = ['status' => $label, 'label' => $label, 'count' => _to_int($count)];
    }
    // Sort by count desc
    usort($out, function ($a, $b) {
        return $b['count'] <=> $a['count'];
    });
    return $out;
}

// Flatten and aggregate source counts to [ ['source_of_lead' => string, 'count' => int], ... ]
function normalize_source_counts($data)
{
    $totals = [];

    $flatten = function ($rows) use (&$flatten, &$totals) {
        if (!is_array($rows))
            return;
        if (_is_assoc($rows)) {
            $src = $rows['source_of_lead'] ?? $rows['source'] ?? $rows['label'] ?? $rows['name'] ?? null;
            $count = $rows['count'] ?? $rows['total'] ?? $rows['cnt'] ?? null;
            if ($src !== null) {
                $totals[(string) $src] = ($totals[(string) $src] ?? 0) + _to_int($count);
                return;
            }
            if (isset($rows['data']) && is_array($rows['data'])) {
                $flatten($rows['data']);
                return;
            }
        }
        foreach ($rows as $r) {
            if (is_array($r))
                $flatten($r);
        }
    };

    $flatten($data);

    $out = [];
    foreach ($totals as $src => $count) {
        $label = (string) $src;
        $out[] = ['source_of_lead' => $label, 'label' => $label, 'count' => _to_int($count)];
    }
    usort($out, function ($a, $b) {
        return $b['count'] <=> $a['count'];
    });
    return $out;
}

// === New hierarchy helpers ===
function normalize_role($rawType)
{
    $s = strtolower(trim((string) $rawType));
    switch ($s) {
        // Promoter (highest)
        case 'p':
        case 'promoter':
        case 'ceo': // map legacy to promoter
        case 'c':
            return 'promoter';
        // Director (removed) -> treat as Business Head
        case 'd':
        case 'director':
            return 'business_head';
        // Business Head
        case 'bh':
        case 'bhead':
        case 'business head':
        case 'business_head':
            return 'business_head';
        // Manager
        case 'm':
        case 'manager':
            return 'manager';
        // Team Lead
        case 'tl':
        case 'lead':
        case 'team lead':
        case 'team_lead':
            return 'team_lead';
        // Sales/User catch-alls map to user
        case 'u':
        case 'user':
        case 'sales':
        case 'sales_executive':
        case 'sales executive':
        case 'se':
            return 'user';
        default:
            return 'user';
    }
}

function role_level($normalizedRole)
{
    // Compressed levels after removing 'director'
    $map = [
        'promoter' => 1,
        'business_head' => 2,
        'manager' => 3,
        'team_lead' => 4,
        'user' => 5,
    ];
    $r = strtolower(trim((string) $normalizedRole));
    return $map[$r] ?? 5;
}

// --- Helper: Fetch a user row by tablename (with in-memory cache to prevent N+1 query locks) ---
function get_user_by_tablename(PDO $conn, $tablename)
{
    if (!$tablename)
        return null;

    // Use an aggressive static cache to avoid hundreds of redundant SQL queries during hierarchy checks
    static $user_lookup_cache = [];
    if (array_key_exists($tablename, $user_lookup_cache)) {
        return $user_lookup_cache[$tablename];
    }

    $q = "SELECT tablename, username, useremail, user_type, doj, employee_id, assign_user
          FROM accounts WHERE tablename = :tid AND is_active = 1";
    $stmt = $conn->prepare($q);
    $stmt->bindParam(':tid', $tablename, PDO::PARAM_STR);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $user_lookup_cache[$tablename] = $row ?: null;
    return $user_lookup_cache[$tablename];
}

// --- Helper: Build upward hierarchy chain starting from a user's assign_user ---

// Modify the build_upward_chain function to handle multiple managers
function build_upward_chain(PDO $conn, $startUserRow)
{
    $chain = [];
    if (!$startUserRow)
        return $chain;
    $visited = [];

    // Include current user as first element
    $curr = $startUserRow;
    $chain[] = [
        'tablename' => $curr['tablename'] ?? null,
        'username' => $curr['username'] ?? null,
        'useremail' => $curr['useremail'] ?? null,
        'user_type' => normalize_role($curr['user_type'] ?? 'user'),
        'doj' => $curr['doj'] ?? null,
        'employee_id' => $curr['employee_id'] ?? null
    ];
    $visited[$curr['tablename'] ?? ''] = true;

    // Process all managers recursively
    $processManagers = function ($userRow, &$chain, &$visited, $depth = 0) use (&$processManagers, $conn) {
        if ($depth > 10)
            return; // Prevent infinite recursion

        $assignees = isset($userRow['assign_user']) ?
            array_filter(array_map('trim', explode(',', $userRow['assign_user']))) :
            [];

        foreach ($assignees as $assignee) {
            if (isset($visited[$assignee]))
                continue;

            $manager = get_user_by_tablename($conn, $assignee);
            if (!$manager)
                continue;

            $chain[] = [
                'tablename' => $manager['tablename'] ?? null,
                'username' => $manager['username'] ?? null,
                'useremail' => $manager['useremail'] ?? null,
                'user_type' => normalize_role($manager['user_type'] ?? 'user'),
                'doj' => $manager['doj'] ?? null,
                'employee_id' => $manager['employee_id'] ?? null
            ];
            $visited[$assignee] = true;

            // Recursively process this manager's managers
            $processManagers($manager, $chain, $visited, $depth + 1);
        }
    };

    $processManagers($curr, $chain, $visited);
    return $chain;
}

// Modify the canAccessUserData function to handle multiple managers
function canAccessUserData($conn, $currentUserTablename, $targetUserTablename)
{
    // Superadmin (from superadmin panel) can access everyone's data.
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'superuseradmin') {
        return true;
    }
    // User can always access their own data
    if ($currentUserTablename === $targetUserTablename) {
        return true;
    }

    // Get current user details
    $currentUser = get_user_by_tablename($conn, $currentUserTablename);
    if (!$currentUser)
        return false;

    // Get target user details
    $targetUser = get_user_by_tablename($conn, $targetUserTablename);
    if (!$targetUser)
        return false;

    $currentRole = normalize_role($currentUser['user_type'] ?? 'user');
    $targetRole = normalize_role($targetUser['user_type'] ?? 'user');

    // Get target's managers as array
    $targetManagers = !empty($targetUser['assign_user']) ?
        array_filter(array_map('trim', explode(',', $targetUser['assign_user']))) :
        [];

    // If target has no managers and isn't a promoter, they're invisible
    if (empty($targetManagers) && $targetRole !== 'promoter') {
        return false;
    }

    // Check if current user is one of the target's direct managers
    if (in_array($currentUserTablename, $targetManagers)) {
        return true;
    }

    // Check each of target's managers' upward chain
    foreach ($targetManagers as $managerTablename) {
        $manager = get_user_by_tablename($conn, $managerTablename);
        if (!$manager)
            continue;

        $managerChain = build_upward_chain($conn, $manager);
        foreach ($managerChain as $chainUser) {
            if ($chainUser['tablename'] === $currentUserTablename) {
                return true;
            }
        }
    }

    return false;
}

function get_subordinate_user_ids($conn, $currentUserTablename)
{
    $subordinates = [];
    $visited = [];
    $queue = [$currentUserTablename];

    while (!empty($queue)) {
        $current = array_shift($queue);
        if (in_array($current, $visited))
            continue;
        $visited[] = $current;

        // Find users who have $current in their assign_user
        $query = "SELECT tablename FROM accounts WHERE FIND_IN_SET(?, assign_user)";
        $stmt = $conn->prepare($query);
        $stmt->execute([$current]);
        $directSubs = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($directSubs as $sub) {
            if (!in_array($sub, $visited) && !in_array($sub, $queue)) {
                $queue[] = $sub;
                $subordinates[] = $sub;
            }
        }
    }

    return $subordinates;
}

// Add this function near the other helper functions
function calculateTeamMemberQualityRange($conn, $user_tablename, $has_date_range, $start_date, $end_date, $month, $year)
{
    global $ur_date_field, $project_filter_clause_ur, $project_filter_params_positional;
    // Build filters
    $dateFilter = "";
    $dateParams = [];
    $params = [$user_tablename];
    $dateFieldExpression = $ur_date_field;

    if ($has_date_range && $start_date && $end_date) {
        $dateFilter = " AND {$dateFieldExpression} BETWEEN ? AND ?";
        $dateParams[] = $start_date . ' 00:00:00';
        $dateParams[] = $end_date . ' 23:59:59';
    } elseif ($month && $year) {
        $dateFilter = " AND {$dateFieldExpression} BETWEEN ? AND ?";
        $dateParams[] = $month;
        $dateParams[] = $year;
    }

    $projectFilter = $project_filter_clause_ur;

    // Query to get all leads with history for the user in the date range
    $query = "SELECT ur.history 
              FROM user_remarks ur
              WHERE ur.user_unique_id = ?
                AND ur.history_h = 0
                AND ur.history IS NOT NULL
                AND ur.history != ''
                AND ur.history != '[]'";

    if (!empty($projectFilter)) {
        $query .= $projectFilter;
        $params = array_merge($params, $project_filter_params_positional);
    }

    $query .= $dateFilter;
    $params = array_merge($params, $dateParams);

    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Calculate quality range for each lead and sum
    $totalQualityRange = 0;
    foreach ($results as $historyJson) {
        $totalQualityRange += calculateQualityRange($historyJson);
    }

    return $totalQualityRange;
}

// Add this function to calculate FSV and SVD counts
function calculateTeamMemberSiteVisits($conn, $user_tablename, $has_date_range, $start_date, $end_date, $month, $year)
{
    global $ur_date_field;
    // Build date filter
    $dateFilter = "";
    $params = [$user_tablename];
    $dateFieldExpression = $ur_date_field;

    if ($has_date_range && $start_date && $end_date) {
        $dateFilter = " AND {$dateFieldExpression} BETWEEN ? AND ?";
        $params[] = $start_date . ' 00:00:00';
        $params[] = $end_date . ' 23:59:59';
    } elseif ($month && $year) {
        $dateFilter = " AND {$dateFieldExpression} BETWEEN ? AND ?";
        $params[] = $month;
        $params[] = $year;
    }

    // Query to get FSV and SVD counts
    // Special logic using history column:
    // - Fix Site Visit: Count if "Fix Site Visit" appears in history AND "Site Visit Done" does NOT appear after it in history
    // - Site Visit Done: Count if "Site Visit Done" appears anywhere in history (regardless of current status)
    $query = "SELECT 
                SUM(CASE 
                    WHEN ur.history IS NOT NULL 
                    AND JSON_TYPE(ur.history) = 'ARRAY'
                    AND JSON_SEARCH(ur.history, 'one', 'Fix Site Visit', NULL, '$[*].status') IS NOT NULL 
                    AND (
                        JSON_SEARCH(ur.history, 'one', 'Site Visit Done', NULL, '$[*].status') IS NULL
                        OR CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(JSON_SEARCH(ur.history, 'one', 'Site Visit Done', NULL, '$[*].status'), '[', -1), ']', 1) AS UNSIGNED) < 
                           CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(JSON_SEARCH(ur.history, 'one', 'Fix Site Visit', NULL, '$[*].status'), '[', -1), ']', 1) AS UNSIGNED)
                    )
                    THEN 1 ELSE 0 
                END) as fsv_count,
                SUM(CASE 
                    WHEN ur.history IS NOT NULL 
                    AND JSON_TYPE(ur.history) = 'ARRAY'
                    AND JSON_SEARCH(ur.history, 'one', 'Site Visit Done', NULL, '$[*].status') IS NOT NULL 
                    THEN 1 ELSE 0 
                END) as svd_count
              FROM user_remarks ur
              WHERE ur.user_unique_id = ?
                AND ur.history_h = 0
                $dateFilter";

    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    return [
        'fsv_count' => (int) ($result['fsv_count'] ?? 0),
        'svd_count' => (int) ($result['svd_count'] ?? 0)
    ];
}

function calculateQualityRange($historyJson)
{
    if (empty($historyJson)) {
        return 0;
    }

    // Parse JSON history
    $history = json_decode($historyJson, true);
    if (!is_array($history) || empty($history)) {
        return 0;
    }

    // Define zero-quality statuses
    $zeroQualityStatuses = ['Pending', 'Already Booked', 'Not Interested', 'Fake'];
    $initialCheckStatuses = ['RNR', 'Not Connected'];

    // Get first status
    $firstStatus = isset($history[0]['status']) ? trim($history[0]['status']) : '';

    // Case 1: First status is zero-quality
    if (in_array($firstStatus, $zeroQualityStatuses)) {
        return 0;
    }

    // Case 2: First status is RNR or Not Connected
    if (in_array($firstStatus, $initialCheckStatuses)) {
        // Check if there's a second entry
        if (count($history) < 2) {
            return 0; // No second entry, count as 0
        }

        $secondStatus = isset($history[1]['status']) ? trim($history[1]['status']) : '';

        // Combine all excluded statuses for second check
        $excludedStatuses = array_merge($zeroQualityStatuses, $initialCheckStatuses);

        // If second status is NOT in excluded list, count as 1
        if (!in_array($secondStatus, $excludedStatuses)) {
            return 1;
        }

        return 0;
    }

    // Case 3: First status is any other status (quality lead)
    return 1;
}

/**
 * Get quality range for a user with date filtering
 */
function getUserQualityRange($conn, $user_id, $is_total_request, $has_date_range, $start_date = null, $end_date = null, $month = null, $year = null)
{
    global $ur_date_field, $project_filter_clause_ur, $project_filter_params_positional;
    // Build date filter
    $dateFilter = "";
    $dateParams = [];
    $params = [$user_id];
    $dateFieldExpression = $ur_date_field;

    if (!$is_total_request) {
        if ($has_date_range && $start_date && $end_date) {
            $dateFilter = " AND {$dateFieldExpression} BETWEEN ? AND ?";
            $dateParams[] = $start_date . ' 00:00:00';
            $dateParams[] = $end_date . ' 23:59:59';
        } elseif ($month && $year) {
            $dateFilter = " AND {$dateFieldExpression} BETWEEN ? AND ?";
            $dateParams[] = $month;
            $dateParams[] = $year;
        }
    }

    $projectFilter = $project_filter_clause_ur;

    // Query to get all leads with history for the user in the date range
    $query = "SELECT ur.history 
              FROM user_remarks ur
              WHERE ur.user_unique_id = ?
                AND ur.history_h = 0
                AND ur.history IS NOT NULL
                AND ur.history != ''
                AND ur.history != '[]'";

    if (!empty($projectFilter)) {
        $query .= $projectFilter;
        $params = array_merge($params, $project_filter_params_positional);
    }

    $query .= $dateFilter;
    $params = array_merge($params, $dateParams);

    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Calculate quality range for each lead and sum
    $totalQualityRange = 0;
    foreach ($results as $historyJson) {
        $totalQualityRange += calculateQualityRange($historyJson);
    }

    return $totalQualityRange;
}


try {
    $config = new Config();
    $conn = $config->getConnection();

    // === CEO ===
    $stmt = $conn->prepare("SELECT username FROM accounts WHERE user_type = 'ceo' LIMIT 1");
    $stmt->execute();
    if ($ceo = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $response['ceo'] = $ceo['username'];
    }

    // === Manager ===
    $stmt = $conn->prepare("SELECT username FROM accounts WHERE user_type = 'manager' LIMIT 1");
    $stmt->execute();
    if ($manager = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $response['manager'] = $manager['username'];
    }

    // === Get All Assigned Users (Including Current User) ===
    $all_assigned_users = [];

    // Add this after line ~200 (after the aggregated analytics section)

    // === GET TEAM MEMBERS FOR A SPECIFIC MANAGER ===
// === GET TEAM MEMBERS FOR A SPECIFIC MANAGER/CEO ===
// === GET TEAM MEMBERS FOR A SPECIFIC MANAGER/CEO ===
// === GET TEAM MEMBERS FOR A SPECIFIC MANAGER/CEO ===
    if (isset($_GET['get_team_members']) && $_GET['get_team_members'] === 'true' && isset($_GET['leader_id'])) {
        $leader_id = $_GET['leader_id'];

        // Get date filter parameters
        $has_date_range = !empty($_GET['start_date']) && !empty($_GET['end_date']);
        $month = isset($_GET['month']) ? (int) $_GET['month'] : null;
        $year = isset($_GET['year']) ? (int) $_GET['year'] : null;

        try {
            // Get the leader's information first
            $query = "SELECT tablename, username, useremail, user_type 
                  FROM accounts 
                  WHERE tablename = :leader_id AND is_active = 1";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':leader_id', $leader_id, PDO::PARAM_STR);
            $stmt->execute();
            $leader = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$leader) {
                echo json_encode(['status' => 'error', 'message' => 'Leader not found']);
                exit;
            }

            // Clean leader's user type
            $raw_type = $leader['user_type'] ?? 'user';
            $cleaned_type = strtolower(trim(strval($raw_type)));

            if ($cleaned_type === 'm' || $cleaned_type === 'manager') {
                $leader['user_type'] = 'manager';
            } elseif ($cleaned_type === 'c' || $cleaned_type === 'ceo') {
                $leader['user_type'] = 'ceo';
            } else {
                $leader['user_type'] = 'user';
            }

            // Check if current user can access the requested leader's team data
            if (!canAccessUserData($conn, $_SESSION['tablename'], $leader_id)) {
                echo json_encode(['status' => 'error', 'message' => 'Access denied']);
                exit;
            }

            // Get team members assigned to this leader (including the leader themselves)
            $query = "SELECT tablename, username, useremail, user_type 
            FROM accounts 
            WHERE (FIND_IN_SET(:leader_id, assign_user) > 0 
                OR tablename = :leader_id3)
            AND is_active = 1
                  ORDER BY 
                    CASE 
                        WHEN tablename = :leader_id4 THEN 0  -- Leader first
                        WHEN user_type IN ('ceo', 'manager') THEN 1  -- Then other managers/CEOs
                        ELSE 2  -- Then regular users
                    END,
                    username ASC";

            $stmt = $conn->prepare($query);
            $stmt->bindParam(':leader_id', $leader_id, PDO::PARAM_STR);
            $stmt->bindParam(':leader_id3', $leader_id, PDO::PARAM_STR);
            $stmt->bindParam(':leader_id4', $leader_id, PDO::PARAM_STR);
            $stmt->execute();
            $all_team_members = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Filter team members based on access control
            $team_members = [];
            foreach ($all_team_members as $member) {
                if (canAccessUserData($conn, $_SESSION['tablename'], $member['tablename'])) {
                    $team_members[] = $member;
                }
            }

            // Clean team members' user types and map directors to business head
            foreach ($team_members as &$member) {
                $raw_type = $member['user_type'] ?? 'user';
                $cleaned_type = strtolower(trim(strval($raw_type)));

                if ($cleaned_type === 'm' || $cleaned_type === 'manager') {
                    $member['user_type'] = 'manager';
                } elseif ($cleaned_type === 'c' || $cleaned_type === 'ceo') {
                    $member['user_type'] = 'ceo';
                } elseif ($cleaned_type === 'd' || $cleaned_type === 'director') {
                    // Map removed role to business head for display
                    $member['user_type'] = 'business_head';
                } else {
                    $member['user_type'] = 'user';
                }
            }
            unset($member);

            // Fetch performance data for each team member with date filters
            $team_performance_data = [];
            global $ur_date_field;
            foreach ($team_members as $member) {
                $dateFieldExpression = $ur_date_field;
                $member_tablename = $member['tablename'];

                // Build date filter for this member
                $dateFilter = "";
                $dateParams = [];

                if ($has_date_range) {
                    $dateFilter = " AND {$dateFieldExpression} BETWEEN ? AND ?";
                    $dateParams = [$_GET['start_date'] . ' 00:00:00', $_GET['end_date'] . ' 23:59:59'];
                } elseif ($month && $year) {
                    $dateFilter = " AND {$dateFieldExpression} BETWEEN ? AND ?";
                    $dateParams = [$month, $year];
                }

                // Get member's lead count for the period
                $query = "SELECT COUNT(*) as leads 
              FROM user_remarks ur
              WHERE ur.user_unique_id = ?
                AND ur.history_h = 0
                $dateFilter";

                $stmt = $conn->prepare($query);
                $stmt->execute(array_merge([$member_tablename], $dateParams));
                $lead_data = $stmt->fetch(PDO::FETCH_ASSOC);

                // Get status distribution for the period
                $query = "SELECT ur.status, COUNT(*) as count
              FROM user_remarks ur
              WHERE ur.user_unique_id = ?
                AND ur.history_h = 0
                $dateFilter
              GROUP BY ur.status";

                $stmt = $conn->prepare($query);
                $stmt->execute(array_merge([$member_tablename], $dateParams));
                $status_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Get bookings for this period (match source_table or CSV assign_user)
                $bookingQuery = "SELECT COUNT(*) as bookings 
                     FROM admintable 
                     WHERE (source_table = ? OR FIND_IN_SET(?, assign_user) > 0)
                     AND astatus != 'Canceled'";
                $bookingParams = [$member_tablename, $member_tablename];

                if ($has_date_range) {
                    $bookingQuery .= " AND booking_date BETWEEN ? AND ?";
                    $bookingParams = array_merge($bookingParams, [$_GET['start_date'], $_GET['end_date']]);
                } elseif ($month && $year) {
                    $bookingQuery .= " AND booking_date BETWEEN ? AND ?";
                    $bookingParams = array_merge($bookingParams, [$month, $year]);
                }

                $stmt = $conn->prepare($bookingQuery);
                $stmt->execute($bookingParams);
                $booking_data = $stmt->fetch(PDO::FETCH_ASSOC);

                // Get EOI for this period
                $eoiQuery = "SELECT COUNT(*) as eoi 
                 FROM usereoidata 
                 WHERE source_table = ? 
                 AND (canceleoi IS NULL OR canceleoi = 0)";
                $eoiParams = [$member_tablename];

                if ($has_date_range) {
                    $eoiQuery .= " AND booking_date BETWEEN ? AND ?";
                    $eoiParams = array_merge($eoiParams, [$_GET['start_date'], $_GET['end_date']]);
                } elseif ($month && $year) {
                    $eoiQuery .= " AND booking_date BETWEEN ? AND ?";
                    $eoiParams = array_merge($eoiParams, [$month, $year]);
                }

                $stmt = $conn->prepare($eoiQuery);
                $stmt->execute($eoiParams);
                $eoi_data = $stmt->fetch(PDO::FETCH_ASSOC);

                // NEW: Get Quality Range for team member
                $quality_range = calculateTeamMemberQualityRange(
                    $conn,
                    $member_tablename,
                    $has_date_range,
                    $_GET['start_date'] ?? null,
                    $_GET['end_date'] ?? null,
                    $month,
                    $year
                );

                // NEW: Get FSV and SVD counts for team member
                $site_visit_data = calculateTeamMemberSiteVisits(
                    $conn,
                    $member_tablename,
                    $has_date_range,
                    $_GET['start_date'] ?? null,
                    $_GET['end_date'] ?? null,
                    $month,
                    $year
                );

                $team_performance_data[$member_tablename] = [
                    'leads' => (int) ($lead_data['leads'] ?? 0),
                    'bookings' => (int) ($booking_data['bookings'] ?? 0),
                    'eoi' => (int) ($eoi_data['eoi'] ?? 0),
                    'status_distribution' => $status_data,
                    // NEW: Add QR, FSV, and SVD data
                    'quality_range' => $quality_range,
                    'fsv_count' => $site_visit_data['fsv_count'],
                    'svd_count' => $site_visit_data['svd_count']
                ];
            }


            echo json_encode([
                'status' => 'success',
                'leader' => $leader,
                'team_members' => $team_members,
                'team_performance_data' => $team_performance_data,
                'date_filters_applied' => $has_date_range || ($month && $year)
            ]);
            exit;

        } catch (Exception $e) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Error fetching team members: ' . $e->getMessage()
            ]);
            exit;
        }
    }

    // Build assigned_users by new hierarchy
    // Determine current user's normalized role and level
    $stmt = $conn->prepare("SELECT user_type FROM accounts WHERE tablename = :tablename");
    $stmt->bindParam(':tablename', $_SESSION['tablename'], PDO::PARAM_STR);
    $stmt->execute();
    $currentRow = $stmt->fetch(PDO::FETCH_ASSOC);
    $currentNormalized = normalize_role($currentRow['user_type'] ?? $current_user_type ?? 'user');
    $currentLevel = role_level($currentNormalized);

    // Fetch all active accounts with assign_user field
    $stmt = $conn->prepare("SELECT tablename, username, useremail, user_type, assign_user FROM accounts WHERE is_active = 1");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $all_assigned_users = [];
    foreach ($rows as $row) {
        $norm = normalize_role($row['user_type'] ?? 'user');
        if ($norm === 'director')
            continue; // exclude director from lists

        $lvl = role_level($norm);
        $assignUser = trim($row['assign_user'] ?? '');

        // Use hierarchy-based access control
        $canAccess = canAccessUserData($conn, $_SESSION['tablename'], $row['tablename']);

        // Only include users that the current user can access
        // AND who are assigned to someone (except promoter who may not have assign_user)
        $isCurrentUser = ($row['tablename'] === $_SESSION['tablename']);
        $isAssignedUser = !empty($assignUser);
        $isTopLevel = ($norm === 'promoter');

        if ($canAccess && ($isCurrentUser || $isAssignedUser || $isTopLevel)) {
            $row['user_type'] = $norm;
            $row['role_level'] = $lvl;
            $all_assigned_users[] = $row;
        }
    }

    // Sort: self first, then by role level asc, then username asc
    usort($all_assigned_users, function ($a, $b) {
        $selfA = ($a['tablename'] === $_SESSION['tablename']) ? 0 : 1;
        $selfB = ($b['tablename'] === $_SESSION['tablename']) ? 0 : 1;
        if ($selfA !== $selfB)
            return $selfA <=> $selfB;
        $lvlCmp = ($a['role_level'] ?? 99) <=> ($b['role_level'] ?? 99);
        if ($lvlCmp !== 0)
            return $lvlCmp;
        return strcasecmp($a['username'] ?? '', $b['username'] ?? '');
    });

    $response['assigned_users'] = $all_assigned_users;

    // === Access Control Validation ===
    // Check if current user can access the requested user's data
    if ($user_unique_id !== $_SESSION['tablename'] && !canAccessUserData($conn, $_SESSION['tablename'], $user_unique_id)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Access denied: You do not have permission to view this user\'s data'
        ]);
        exit;
    }

    // === Selected User Type ===
    if ($user_unique_id !== $_SESSION['tablename']) {
        $stmt = $conn->prepare("SELECT user_type FROM accounts WHERE tablename = :tablename");
        $stmt->bindParam(':tablename', $user_unique_id, PDO::PARAM_STR);
        $stmt->execute();
        $selectedUserRow = $stmt->fetch(PDO::FETCH_ASSOC);
        $selected_user_type = $selectedUserRow['user_type'] ?? 'user';
    } else {
        $selected_user_type = $current_user_type;
    }

    $normalized_selected_role = normalize_role($selected_user_type ?? 'user');
    $includeAssignedUsersForBookings = $normalized_selected_role !== 'user';

    // === Utility: WHERE Clause (date filter) ===
    function buildDateFilter($field, $is_total, $has_range, $month = null, $year = null)
    {
        $field = applyDateFieldPreference($field);
        if ($is_total) {
            return ""; // no filter
        } elseif ($has_range) {
            return " AND $field BETWEEN :start_date AND :end_date";
        } else {
            return " AND $field BETWEEN :month AND :year";
        }
    }

    $response['quality_range'] = getUserQualityRange(
        $conn,
        $user_unique_id,
        $is_total_request,
        $has_date_range,
        $start_date,
        $end_date,
        $month,
        $year
    );

    recordProfiling('Build_Assigned_Users_Hierarchy', $profiling, $start_time);

    // === AGGREGATED ANALYTICS FOR ALL ASSIGNED USERS ===
    // Note: $all_assigned_users already filtered by hierarchy access control above
    if ($is_aggregated_analytics && !empty($all_assigned_users)) {
        global $ur_date_field;

        // Handle user filtering if provided
        if (!empty($_GET['filtered_users'])) {
            $filtered_user_list = explode(',', $_GET['filtered_users']);
            $filtered_user_list = array_map('trim', $filtered_user_list);
            $filtered_user_list = array_filter($filtered_user_list);

            // Filter all_assigned_users to only include the selected users
            $all_assigned_users = array_filter($all_assigned_users, function ($user) use ($filtered_user_list) {
                return in_array($user['tablename'], $filtered_user_list);
            });
        } elseif ($explicitly_requested_user) {
            // OPTIMIZATION: If a specific user was chosen, we must isolate them to cut SQL time.
            // HOWEVER: We MUST ALSO INCLUDE their subordinates! If Javascript tries to build 
            // the Hierarchy Tree and the sub-users are missing, it will panic and fire dozens of 
            // curl loopbacks taking 30 seconds! By returning the whole tree natively here, we solve it mathematically.
            $target_user = get_user_by_tablename($conn, $user_unique_id);
            if ($target_user) {
                $subordinates = get_subordinate_user_ids($conn, $target_user);
                $allowed_ids = array_merge([$user_unique_id], $subordinates);

                $all_assigned_users = array_filter($all_assigned_users, function ($user) use ($allowed_ids) {
                    return in_array($user['tablename'], $allowed_ids);
                });
            } else {
                $all_assigned_users = array_filter($all_assigned_users, function ($user) use ($user_unique_id) {
                    return $user['tablename'] === $user_unique_id;
                });
            }
        }

        // Create list of user tablenames for IN clause
        $user_tablenames = array_column($all_assigned_users, 'tablename');
        $placeholders = str_repeat('?,', count($user_tablenames) - 1) . '?';

        // Build date filter for aggregated queries
        $dateFilter = "";
        $dateParams = [];
        $dateFieldExpression = $ur_date_field;

        if ($has_date_range) {
            $dateFilter = " AND {$dateFieldExpression} BETWEEN ? AND ?";
            $dateParams = [$start_date, $end_date];
        } elseif (!$is_total_request && $month && $year) {
            $dateFilter = " AND {$dateFieldExpression} BETWEEN ? AND ?";
            $dateParams = [$month, $year];
        }

        // Build project filter for aggregated queries using shared configuration
        $projectFilter = $project_filter_clause_ur;
        $projectParams = $project_filter_params_positional;

        // --- Aggregated Status Counts ---
        $query = "SELECT 
                    ur.status,
                    COUNT(*) as count
                  FROM user_remarks ur
                  WHERE ur.user_unique_id IN ($placeholders)
                    AND ur.history_h = 0
                    $dateFilter
                    $projectFilter
                  GROUP BY ur.status
                  ORDER BY count DESC";

        $stmt = $conn->prepare($query);
        $allParams = array_merge($user_tablenames, $dateParams, $projectParams ?? []);
        $stmt->execute($allParams);
        $response['aggregated_analytics']['detailed_status_counts'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // --- Aggregated Source Counts ---
        $query = "SELECT 
                    sud.source_of_lead,
                    COUNT(*) as count
                  FROM user_remarks ur
                  LEFT JOIN shi_upload_data sud ON sud.id = ur.upload_data_id
                  WHERE ur.user_unique_id IN ($placeholders)
                    AND ur.history_h = 0
                    AND sud.source_of_lead IS NOT NULL
                    AND sud.source_of_lead != ''
                    $dateFilter
                    $projectFilter
                  GROUP BY sud.source_of_lead
                  ORDER BY count DESC";

        $stmt = $conn->prepare($query);
        $allParams = array_merge($user_tablenames, $dateParams, $projectParams ?? []);
        $stmt->execute($allParams);
        $response['aggregated_analytics']['detailed_source_counts'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // --- Aggregated Total Leads ---
        $query = "SELECT COUNT(*) AS total_leads
                  FROM user_remarks ur
                  WHERE ur.user_unique_id IN ($placeholders)
                    AND ur.history_h = 0
                    $dateFilter
                    $projectFilter";

        $stmt = $conn->prepare($query);
        $allParams = array_merge($user_tablenames, $dateParams, $projectParams ?? []);
        $stmt->execute($allParams);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $response['aggregated_analytics']['total_leads'] = (int) ($row['total_leads'] ?? 0);

        // --- Aggregated Conversion Rate ---
        $query = "SELECT 
                    COUNT(*) as total_leads,
                    SUM(CASE WHEN ur.status IN ('Converted') THEN 1 ELSE 0 END) as converted_leads
                  FROM user_remarks ur
                  WHERE ur.user_unique_id IN ($placeholders)
                    AND ur.history_h = 0
                    $dateFilter
                    $projectFilter";

        $stmt = $conn->prepare($query);
        $allParams = array_merge($user_tablenames, $dateParams, $projectParams ?? []);
        $stmt->execute($allParams);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $totalLeads = (int) ($row['total_leads'] ?? 0);
        $convertedLeads = (int) ($row['converted_leads'] ?? 0);
        // Keep a computed conversion_rate as float with more precision; frontend may format as needed
        $response['aggregated_analytics']['conversion_rate'] = $totalLeads > 0 ? (($convertedLeads / $totalLeads) * 100) : 0;
        // Also expose raw counts for precise client-side formatting
        $response['aggregated_analytics']['converted_leads'] = $convertedLeads;
        $response['aggregated_analytics']['total_leads_for_rate'] = $totalLeads;

        // --- Aggregated Today's Follow Up ---
        $query = "SELECT COUNT(*) AS todays_followup
                  FROM user_remarks ur
                  WHERE ur.user_unique_id IN ($placeholders)
                    AND ur.history_h = 0
                    AND (ur.status = 'Today\'s Follow Up' 
                         OR (ur.follow_up_date = CURDATE() AND ur.status = 'Follow Up'))
                    $projectFilter";

        $stmt = $conn->prepare($query);
        $allParams = array_merge($user_tablenames, $projectParams ?? []);
        $stmt->execute($allParams);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $response['aggregated_analytics']['todays_followup'] = (int) ($row['todays_followup'] ?? 0);

        // --- Aggregated Site Visits Pending ---
        $query = "SELECT COUNT(*) AS site_visits_pending
                  FROM user_remarks ur
                  WHERE ur.user_unique_id IN ($placeholders)
                    AND ur.history_h = 0
                    AND ur.status = 'Fix Site Visit'
                    $projectFilter";

        $stmt = $conn->prepare($query);
        $allParams = array_merge($user_tablenames, $projectParams ?? []);
        $stmt->execute($allParams);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $response['aggregated_analytics']['site_visits_pending'] = (int) ($row['site_visits_pending'] ?? 0);

        // Set total users count
        $response['aggregated_analytics']['total_users'] = count($all_assigned_users);

        // --- Get aggregated bookings and EOI for the filtered period ---
        $bookingDateFilter = "";
        $bookingDateParams = [];

        if ($has_date_range) {
            $bookingDateFilter = " AND booking_date BETWEEN ? AND ?";
            $bookingDateParams = [$start_date, $end_date];
        } elseif (!$is_total_request && $month && $year) {
            $bookingDateFilter = " AND booking_date BETWEEN ? AND ?";
            $bookingDateParams = [$month, $year];
        }

        // Apply project filter to aggregated booking counts (admintable.project)
        $bookingProjectFilter = $booking_project_filter_clause;
        $bookingProjectParams = $booking_project_filter_params;

        // Total Bookings - Now calculated from individual user data (see after user_wise_data loop)
        // $query = "SELECT COUNT(*) AS total_bookings
        //   FROM admintable
        //   WHERE source_table IN ($placeholders)
        //     AND astatus != 'Canceled'
        //   $bookingDateFilter";

        // $stmt = $conn->prepare($query);
        // $stmt->execute(array_merge($user_tablenames, $bookingDateParams));
        // $row = $stmt->fetch(PDO::FETCH_ASSOC);
        // $response['aggregated_analytics']['total_bookings'] = (int)($row['total_bookings'] ?? 0);

        // Total EOI
        $query = "SELECT COUNT(*) AS total_eoi
                  FROM usereoidata
                  WHERE source_table IN ($placeholders)
                    
                  $bookingDateFilter";

        $stmt = $conn->prepare($query);
        $stmt->execute(array_merge($user_tablenames, $bookingDateParams));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $response['aggregated_analytics']['total_eoi'] = (int) ($row['total_eoi'] ?? 0);


        // ============================
        // OPTIMIZATION: Bulk fetch ALL metrics (Bookings, Leads, EOIs, Statuses, Sources)
        // ============================
        $currentRole = normalize_role($currentUser['user_type'] ?? 'user');
        $promoter_tablename = $currentUser['tablename'] ?? '';
        $userTablenames = array_map(function ($u) {
            return $u['tablename'];
        }, $all_assigned_users);
        if ($currentRole === 'promoter' && $promoter_tablename && !in_array($promoter_tablename, $userTablenames)) {
            $userTablenames[] = $promoter_tablename;
        }

        $bookingsByUser = [];
        $cancelledBookingsByUser = [];
        $leadsByUser = [];
        $convertedByUser = [];
        $eoisByUser = [];
        $statusesByUser = [];
        $sourcesByUser = [];

        if (!empty($userTablenames)) {
            // --- 1. BULK BOOKINGS ---
            $bulkBookingDateFilter = "";
            $bulkBookingParams = [];
            $bookingDateField = "a.booking_date"; // Fallback removed to preserve Index

            if ($has_date_range) {
                $bulkBookingDateFilter = " AND {$bookingDateField} BETWEEN ? AND ?";
                $bulkBookingParams = [$start_date, $end_date];
            } elseif (!$is_total_request && $month && $year) {
                $bulkBookingDateFilter = " AND {$bookingDateField} BETWEEN ? AND ?";
                $bulkBookingParams = [$month, $year];
            }

            $bulkBookingProjectFilter = "";
            $bulkBookingProjectParams = [];
            if (!empty($booking_project_filter_clause)) {
                $bulkBookingProjectFilter = str_replace("admintable.", "a.", $booking_project_filter_clause);
                $bulkBookingProjectFilter = preg_replace('/\bproject\b/', 'a.project', $bulkBookingProjectFilter);
                $bulkBookingProjectParams = $booking_project_filter_params;
            }

            // --- 1. BULK BOOKINGS (PHP DISTRIBUTION MAP TO AVOID FULL TABLE SCANS) ---
            $bookingsByUser = array_fill_keys($userTablenames, 0);
            $cancelledBookingsByUser = array_fill_keys($userTablenames, 0);

            // OPTIMIZATION: If we only requested a specific isolated handful of users (e.g. up to 200 users),
            // injecting the FIND_IN_SET into the WHERE clause prevents the grouping logic from memory-thrashing 
            // 100,000+ irrelevant rows. This saves ~5+ seconds of processing time for managers!
            $bookingUserFilter = "";
            $bookingUserParams = [];
            if (count($userTablenames) <= 200 && count($userTablenames) > 0) {
                // Break into chunks if it's too large to prevent parsing bottlenecks, though 200 is extremely safe for MySQL
                $conditions = [];
                foreach ($userTablenames as $uid) {
                    $conditions[] = "(a.source_table = ? OR FIND_IN_SET(?, a.assign_user) > 0)";
                    $bookingUserParams[] = $uid;
                    $bookingUserParams[] = $uid;
                }
                $bookingUserFilter = " AND (" . implode(' OR ', $conditions) . ")";
            }

            $flatBookingQuery = "
                SELECT source_table, assign_user, astatus, COUNT(a.id) AS bookings
                FROM admintable a
                WHERE 1=1
                    $bulkBookingDateFilter
                    $bulkBookingProjectFilter
                    $bookingUserFilter
                GROUP BY source_table, assign_user, astatus";

            $stmt = $conn->prepare($flatBookingQuery);
            $stmt->execute(array_merge($bulkBookingParams, $bulkBookingProjectParams, $bookingUserParams));
            $allBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($allBookings as $row) {
                $sourceMatch = $row['source_table'];
                $isCanceled = ($row['astatus'] === 'Canceled');
                $count = (int) $row['bookings'];

                // Keep track of which users receive credit to avoid double counting per record block
                $creditedUsers = [];

                if (!empty($sourceMatch) && in_array($sourceMatch, $userTablenames)) {
                    $creditedUsers[$sourceMatch] = true;
                }

                if (!empty($row['assign_user'])) {
                    $assigns = explode(',', $row['assign_user']);
                    foreach ($assigns as $au) {
                        $au = trim($au);
                        if (!empty($au) && in_array($au, $userTablenames)) {
                            $creditedUsers[$au] = true;
                        }
                    }
                }

                foreach ($creditedUsers as $uid => $trueVal) {
                    if ($isCanceled) {
                        $cancelledBookingsByUser[$uid] += $count;
                    } else {
                        $bookingsByUser[$uid] += $count;
                    }
                }
            }

            recordProfiling('Bulk_Bookings_Scan', $profiling, $start_time);

            // --- 2. BULK LEADS AND CONVERTED ---
            $query = "SELECT 
                        ur.user_unique_id, COUNT(*) as leads,
                        SUM(CASE WHEN ur.status IN ('Converted') THEN 1 ELSE 0 END) as converted_leads
                      FROM user_remarks ur
                      WHERE ur.user_unique_id IN ($placeholders)
                        AND ur.history_h = 0
                        $dateFilter
                        $projectFilter
                      GROUP BY ur.user_unique_id";
            $stmt = $conn->prepare($query);
            $stmt->execute(array_merge($userTablenames, $dateParams, $projectParams ?? []));
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $leadsByUser[$row['user_unique_id']] = (int) $row['leads'];
                $convertedByUser[$row['user_unique_id']] = (int) $row['converted_leads'];
            }

            recordProfiling('Bulk_Leads_Scan', $profiling, $start_time);

            // --- 3. BULK EOIS ---
            $eoiDateFilter = "";
            $eoiDateParams = [];
            if ($has_date_range) {
                $eoiDateFilter = " AND booking_date BETWEEN ? AND ?";
                $eoiDateParams = [$start_date, $end_date];
            } elseif (!$is_total_request && $month && $year) {
                $eoiDateFilter = " AND booking_date BETWEEN ? AND ?";
                $eoiDateParams = [$month, $year];
            }
            $query = "SELECT source_table AS user_unique_id, COUNT(*) as eoi 
                      FROM usereoidata 
                      WHERE source_table IN ($placeholders) 
                        AND (canceleoi IS NULL OR canceleoi = 0)
                        $eoiDateFilter
                      GROUP BY source_table";
            $stmt = $conn->prepare($query);
            $stmt->execute(array_merge($userTablenames, $eoiDateParams));
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $eoisByUser[$row['user_unique_id']] = (int) $row['eoi'];
            }

            // --- 4. BULK STATUSES ---
            $query = "SELECT ur.user_unique_id, ur.status, COUNT(*) as count
                      FROM user_remarks ur
                      WHERE ur.user_unique_id IN ($placeholders)
                        AND ur.history_h = 0
                        $dateFilter
                        $projectFilter
                      GROUP BY ur.user_unique_id, ur.status";
            $stmt = $conn->prepare($query);
            $stmt->execute(array_merge($userTablenames, $dateParams, $projectParams ?? []));
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $uid = $row['user_unique_id'];
                if (!isset($statusesByUser[$uid]))
                    $statusesByUser[$uid] = [];
                $statusesByUser[$uid][] = ['status' => $row['status'], 'count' => (int) $row['count']];
            }

            // --- 5. BULK SOURCES ---
            $query = "SELECT ur.user_unique_id, sud.source_of_lead, COUNT(*) as count
                      FROM user_remarks ur
                      LEFT JOIN shi_upload_data sud ON sud.id = ur.upload_data_id
                      WHERE ur.user_unique_id IN ($placeholders)
                        AND ur.history_h = 0
                        AND sud.source_of_lead IS NOT NULL AND sud.source_of_lead != ''
                        $dateFilter
                        $projectFilter
                      GROUP BY ur.user_unique_id, sud.source_of_lead";
            $stmt = $conn->prepare($query);
            $stmt->execute(array_merge($userTablenames, $dateParams, $projectParams ?? []));
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $uid = $row['user_unique_id'];
                if (!isset($sourcesByUser[$uid]))
                    $sourcesByUser[$uid] = [];
                $sourcesByUser[$uid][] = ['source_of_lead' => $row['source_of_lead'], 'count' => (int) $row['count']];
            }

            recordProfiling('Bulk_Misc_Statuses_Sources', $profiling, $start_time);
        }

        // --- User-wise Data for Individual Cards ---
        $user_wise_data = [];
        foreach ($all_assigned_users as $user) {
            $user_tablename = $user['tablename'];

            $leads_count = (int) ($leadsByUser[$user_tablename] ?? 0);
            $converted_count = (int) ($convertedByUser[$user_tablename] ?? 0);

            // Skip individual heavy queries. Pull directly from fast mem arrays.
            $user_wise_data[] = [
                'name' => $user['username'],
                'tablename' => $user['tablename'],
                'email' => $user['useremail'],
                'user_type' => $user['user_type'] ?? 'user',
                'leads' => $leads_count,
                // Quality Range and FSV/SVD offloaded to background js batch fetches
                'quality_range' => 0,
                'fsv_count' => 0,
                'svd_count' => 0,
                'eoi' => (int) ($eoisByUser[$user_tablename] ?? 0),
                'bookings' => (int) ($bookingsByUser[$user_tablename] ?? 0),
                'cancelled_bookings' => (int) ($cancelledBookingsByUser[$user_tablename] ?? 0),
                'converted_leads' => $converted_count,
                'conversion_rate' => $leads_count > 0 ? (($converted_count / $leads_count) * 100) : 0,
                'leadStatus' => $statusesByUser[$user_tablename] ?? [],
                'leadSources' => $sourcesByUser[$user_tablename] ?? []
            ];
        }

        $response['aggregated_analytics']['user_wise_data'] = $user_wise_data;

        // Calculate total bookings and cancelled bookings from individual user data
        $total_bookings_from_users = 0;
        $total_cancelled_bookings_from_users = 0;
        foreach ($user_wise_data as $user_data) {
            $total_bookings_from_users += $user_data['bookings'];
            $total_cancelled_bookings_from_users += $user_data['cancelled_bookings'];
        }
        $response['aggregated_analytics']['total_bookings'] = $total_bookings_from_users;
        $response['aggregated_analytics']['cancelled_bookings'] = $total_cancelled_bookings_from_users;
    }

    // === INDIVIDUAL USER DATA (EXISTING FUNCTIONALITY) ===

    // --- Recent Leads ---
    $recentDateField = applyDateFieldPreference('ur.created_at');
    $query = "SELECT 
                        ur.id,
                        ur.upload_data_id,
                        ur.status,
                        ur.assign_project_name,
                        {$recentDateField} AS created_at,
                        COALESCE(sud.name, 'Unknown Lead') as name,
                        COALESCE(sud.source_of_lead, 'Unknown Source') as source_of_lead
                    FROM user_remarks ur
                    LEFT JOIN shi_upload_data sud ON sud.id = ur.upload_data_id
                    WHERE ur.user_unique_id = :user_unique_id
                        AND ur.history_h = 0"
        . $project_filter_named_clause_ur
        . buildDateFilter('ur.created_at', $is_total_request, $has_date_range, $month, $year) . "
                    ORDER BY {$recentDateField} DESC
                    LIMIT 10";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':user_unique_id', $user_unique_id, PDO::PARAM_STR);
    if ($has_date_range) {
        $stmt->bindParam(':start_date', $start_date, PDO::PARAM_STR);
        $stmt->bindParam(':end_date', $end_date, PDO::PARAM_STR);
    } elseif (!$is_total_request) {
        $stmt->bindParam(':month', $month, PDO::PARAM_STR);
        $stmt->bindParam(':year', $year, PDO::PARAM_STR);
    }
    if (!empty($project_filter_named_params)) {
        foreach ($project_filter_named_params as $placeholder => $value) {
            $stmt->bindValue($placeholder, $value, PDO::PARAM_STR);
        }
    }
    $stmt->execute();
    $response['standings'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- EOIs ---
    if ($selected_user_type === 'manager' || $selected_user_type === 'ceo') {
        $query = "SELECT COUNT(*) AS total_eoi 
                  FROM usereoidata 
                  WHERE source_table = :user_unique_id
                  AND (canceleoi IS NULL OR canceleoi = 0)" .
            buildDateFilter("booking_date", $is_total_request, $has_date_range, $month, $year);
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':user_unique_id', $user_unique_id, PDO::PARAM_STR);

    } else {
        $query = "SELECT COUNT(*) AS total_eoi 
                  FROM usereoidata 
                  WHERE source_table = :user_unique_id
                  AND (canceleoi IS NULL OR canceleoi = 0)" .
            buildDateFilter("booking_date", $is_total_request, $has_date_range, $month, $year);
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':user_unique_id', $user_unique_id, PDO::PARAM_STR);
    }
    if ($has_date_range) {
        $stmt->bindParam(':start_date', $start_date, PDO::PARAM_STR);
        $stmt->bindParam(':end_date', $end_date, PDO::PARAM_STR);
    } elseif (!$is_total_request) {
        $stmt->bindParam(':month', $month, PDO::PARAM_STR);
        $stmt->bindParam(':year', $year, PDO::PARAM_STR);
    }
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $response['total_eoi'] = (int) ($row['total_eoi'] ?? 0);

    // --- My Leads ---
    $query = "SELECT COUNT(*) AS myLeads 
                                FROM user_remarks ur
                                WHERE ur.user_unique_id = :user_unique_id
                                    AND ur.history_h = 0"
        . $project_filter_named_clause_ur
        . buildDateFilter("ur.created_at", $is_total_request, $has_date_range, $month, $year);
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':user_unique_id', $user_unique_id, PDO::PARAM_STR);
    if ($has_date_range) {
        $stmt->bindParam(':start_date', $start_date, PDO::PARAM_STR);
        $stmt->bindParam(':end_date', $end_date, PDO::PARAM_STR);
    } elseif (!$is_total_request) {
        $stmt->bindParam(':month', $month, PDO::PARAM_STR);
        $stmt->bindParam(':year', $year, PDO::PARAM_STR);
    }
    if (!empty($project_filter_named_params)) {
        foreach ($project_filter_named_params as $placeholder => $value) {
            $stmt->bindValue($placeholder, $value, PDO::PARAM_STR);
        }
    }
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $response['myLeads'] = (int) ($row['myLeads'] ?? 0);


    // --- Source Stats ---
    $query = "SELECT 
                                SUM(CASE WHEN LOWER(sud.source_of_lead) LIKE '%google%' THEN 1 ELSE 0 END) AS google_count,
                                SUM(CASE WHEN LOWER(sud.source_of_lead) LIKE '%facebook%' THEN 1 ELSE 0 END) AS facebook_count,
                                SUM(CASE WHEN sud.source_of_lead IS NULL 
                                                 OR (LOWER(sud.source_of_lead) NOT LIKE '%google%' 
                                                         AND LOWER(sud.source_of_lead) NOT LIKE '%facebook%') 
                                        THEN 1 ELSE 0 END) AS other_count
                            FROM user_remarks ur
                            LEFT JOIN shi_upload_data sud ON sud.id = ur.upload_data_id
                            WHERE ur.user_unique_id = :user_unique_id
                                AND ur.history_h = 0"
        . $project_filter_named_clause_ur
        . buildDateFilter("ur.created_at", $is_total_request, $has_date_range, $month, $year);
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':user_unique_id', $user_unique_id, PDO::PARAM_STR);
    if ($has_date_range) {
        $stmt->bindParam(':start_date', $start_date, PDO::PARAM_STR);
        $stmt->bindParam(':end_date', $end_date, PDO::PARAM_STR);
    } elseif (!$is_total_request) {
        $stmt->bindParam(':month', $month, PDO::PARAM_STR);
        $stmt->bindParam(':year', $year, PDO::PARAM_STR);
    }
    if (!empty($project_filter_named_params)) {
        foreach ($project_filter_named_params as $placeholder => $value) {
            $stmt->bindValue($placeholder, $value, PDO::PARAM_STR);
        }
    }
    $stmt->execute();
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $response['source_stats'] = $row;
    }

    // --- Status Counts ---
    // Special logic for Fix Site Visit and Site Visit Done using history column:
    // - Fix Site Visit: Count if "Fix Site Visit" appears in history AND "Site Visit Done" does NOT appear after it in history
    // - Site Visit Done: Count if "Site Visit Done" appears anywhere in history (regardless of current status)
    $query = "SELECT 
                                SUM(CASE WHEN ur.status = 'Pending' THEN 1 ELSE 0 END) AS pending_count,
                                SUM(CASE 
                                    WHEN ur.history IS NOT NULL 
                                    AND JSON_TYPE(ur.history) = 'ARRAY'
                                    AND JSON_SEARCH(ur.history, 'one', 'Site Visit Done', NULL, '$[*].status') IS NOT NULL 
                                    THEN 1 ELSE 0 
                                END) AS site_visit_done_count,
                                SUM(CASE WHEN ur.status = 'Follow Up' THEN 1 ELSE 0 END) AS followup_count,
                                SUM(CASE 
                                    WHEN ur.history IS NOT NULL 
                                    AND JSON_TYPE(ur.history) = 'ARRAY'
                                    AND JSON_SEARCH(ur.history, 'one', 'Fix Site Visit', NULL, '$[*].status') IS NOT NULL 
                                    AND (
                                        JSON_SEARCH(ur.history, 'one', 'Site Visit Done', NULL, '$[*].status') IS NULL
                                        OR CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(JSON_SEARCH(ur.history, 'one', 'Site Visit Done', NULL, '$[*].status'), '[', -1), ']', 1) AS UNSIGNED) < 
                                           CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(JSON_SEARCH(ur.history, 'one', 'Fix Site Visit', NULL, '$[*].status'), '[', -1), ']', 1) AS UNSIGNED)
                                    )
                                    THEN 1 ELSE 0 
                                END) AS fix_site_visit_count
                            FROM user_remarks ur
                            WHERE ur.user_unique_id = :user_unique_id
                                AND ur.history_h = 0"
        . $project_filter_named_clause_ur
        . buildDateFilter("ur.created_at", $is_total_request, $has_date_range, $month, $year);
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':user_unique_id', $user_unique_id, PDO::PARAM_STR);
    if ($has_date_range) {
        $stmt->bindParam(':start_date', $start_date, PDO::PARAM_STR);
        $stmt->bindParam(':end_date', $end_date, PDO::PARAM_STR);
    } elseif (!$is_total_request) {
        $stmt->bindParam(':month', $month, PDO::PARAM_STR);
        $stmt->bindParam(':year', $year, PDO::PARAM_STR);
    }
    if (!empty($project_filter_named_params)) {
        foreach ($project_filter_named_params as $placeholder => $value) {
            $stmt->bindValue($placeholder, $value, PDO::PARAM_STR);
        }
    }
    $stmt->execute();
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $response['status_counts'] = $row;
    }

    // --- Bookings & Revenue ---
    // ====================
    $bookingOwnershipClause = $includeAssignedUsersForBookings
        ? "(source_table = :user_unique_id OR FIND_IN_SET(:user_unique_id2, assign_user) > 0)"
        : "source_table = :user_unique_id";

    // Booking Count
// ====================
    $currentYear = date('Y');
    $currentMonth = date('n');

    if ($currentMonth >= 4) {
        // April or later - current financial year
        $financialYearStart = $currentYear . '-04-01';
        $financialYearEnd = ($currentYear + 1) . '-03-31';
    } else {
        // January-March - previous financial year  
        $financialYearStart = ($currentYear - 1) . '-04-01';
        $financialYearEnd = $currentYear . '-03-31';
    }

    // ====================
// VECTORIZED SINGLE-SCAN BOOKINGS & REVENUE METRICS
// ====================
// We compress 5 un-indexable table scans into a single combined scan to eliminate severe lag on single-user targeting.

    $generalDateCond = trim(buildDateFilter("booking_date", $is_total_request, $has_date_range, $month, $year));
    if (strpos($generalDateCond, 'AND ') === 0) {
        $generalDateCond = substr($generalDateCond, 4);
    }
    if (empty($generalDateCond)) {
        $generalDateCond = "1=1";
    }

    $projectCond = trim($booking_project_filter_named_clause);
    if (strpos($projectCond, 'AND ') === 0) {
        $projectCond = substr($projectCond, 4);
    }
    if (empty($projectCond)) {
        $projectCond = "1=1";
    }
    
    /*
     * IMPORTANT:
     * Do not reuse the same named date placeholders multiple times
     * inside one prepared statement. MySQL PDO can throw HY093.
     */
    if ($is_total_request) {
        $generalDateCond = "1=1";
    } elseif ($has_date_range) {
        $generalDateCond = "booking_date BETWEEN :start_date_1 AND :end_date_1";
        $generalDateCondCancelled = "booking_date BETWEEN :start_date_2 AND :end_date_2";
        $generalDateCondRevenue = "booking_date BETWEEN :start_date_3 AND :end_date_3";
    } else {
        $generalDateCond = "booking_date BETWEEN :month_1 AND :year_1";
        $generalDateCondCancelled = "booking_date BETWEEN :month_2 AND :year_2";
        $generalDateCondRevenue = "booking_date BETWEEN :month_3 AND :year_3";
    }
    
    if ($is_total_request) {
        $generalDateCondCancelled = "1=1";
        $generalDateCondRevenue = "1=1";
    }
    
    $queryCombined = "
    SELECT 
        COUNT(CASE WHEN astatus != 'Canceled' 
            AND booking_date BETWEEN :financial_year_start_1 AND :financial_year_end_1
            AND ({$projectCond})
        THEN 1 END) AS total_bookings_modal,
        
        COALESCE(SUM(CASE WHEN astatus != 'Canceled' 
            AND booking_date BETWEEN :financial_year_start_2 AND :financial_year_end_2
            AND ({$projectCond})
        THEN agreement_value ELSE 0 END), 0) AS total_revenue_modal,
        
        COUNT(CASE WHEN astatus != 'Canceled' 
            AND ({$generalDateCond}) 
            AND ({$projectCond})
        THEN 1 END) AS total_bookings,
        
        COUNT(CASE WHEN astatus = 'Canceled' 
            AND ({$generalDateCondCancelled}) 
            AND ({$projectCond})
        THEN 1 END) AS cancelled_bookings,
        
        COALESCE(SUM(CASE WHEN astatus != 'Canceled' 
            AND ({$generalDateCondRevenue})
            AND ({$projectCond})
        THEN agreement_value ELSE 0 END), 0) AS total_revenue
    FROM admintable
    WHERE {$bookingOwnershipClause}
    ";
    
    $stmtComb = $conn->prepare($queryCombined);
    
    $stmtComb->bindValue(':user_unique_id', $user_unique_id, PDO::PARAM_STR);
    if ($includeAssignedUsersForBookings) {
        $stmtComb->bindValue(':user_unique_id2', $user_unique_id, PDO::PARAM_STR);
    }
    
    $stmtComb->bindValue(':financial_year_start_1', $financialYearStart, PDO::PARAM_STR);
    $stmtComb->bindValue(':financial_year_end_1', $financialYearEnd, PDO::PARAM_STR);
    $stmtComb->bindValue(':financial_year_start_2', $financialYearStart, PDO::PARAM_STR);
    $stmtComb->bindValue(':financial_year_end_2', $financialYearEnd, PDO::PARAM_STR);
    
    if ($has_date_range) {
        $stmtComb->bindValue(':start_date_1', $start_date, PDO::PARAM_STR);
        $stmtComb->bindValue(':end_date_1', $end_date, PDO::PARAM_STR);
    
        $stmtComb->bindValue(':start_date_2', $start_date, PDO::PARAM_STR);
        $stmtComb->bindValue(':end_date_2', $end_date, PDO::PARAM_STR);
    
        $stmtComb->bindValue(':start_date_3', $start_date, PDO::PARAM_STR);
        $stmtComb->bindValue(':end_date_3', $end_date, PDO::PARAM_STR);
    } elseif (!$is_total_request) {
        $stmtComb->bindValue(':month_1', $month, PDO::PARAM_STR);
        $stmtComb->bindValue(':year_1', $year, PDO::PARAM_STR);
    
        $stmtComb->bindValue(':month_2', $month, PDO::PARAM_STR);
        $stmtComb->bindValue(':year_2', $year, PDO::PARAM_STR);
    
        $stmtComb->bindValue(':month_3', $month, PDO::PARAM_STR);
        $stmtComb->bindValue(':year_3', $year, PDO::PARAM_STR);
    }
    
    if (!empty($project_filter_named_params)) {
        foreach ($project_filter_named_params as $placeholder => $value) {
            $stmtComb->bindValue($placeholder, $value, PDO::PARAM_STR);
        }
    }
    
    $stmtComb->execute();
    $combRow = $stmtComb->fetch(PDO::FETCH_ASSOC);

    $response['total_bookings_modal'] = (int) ($combRow['total_bookings_modal'] ?? 0);
    $response['total_revenue_modal'] = (float) ($combRow['total_revenue_modal'] ?? 0);
    $response['total_bookings'] = (int) ($combRow['total_bookings'] ?? 0);
    $response['cancelled_bookings'] = (int) ($combRow['cancelled_bookings'] ?? 0);
    $response['total_revenue'] = (float) ($combRow['total_revenue'] ?? 0);


    // === ANALYTICS DATA FOR POPUP (INDIVIDUAL USER - WITH DATE FILTER) ===

    // --- Detailed Status Counts (With Date Filter) ---
    $query = "SELECT 
                                ur.status,
                                COUNT(*) as count
                            FROM user_remarks ur
                            WHERE ur.user_unique_id = :user_unique_id
                                AND ur.history_h = 0"
        . $project_filter_named_clause_ur
        . buildDateFilter("ur.created_at", $is_total_request, $has_date_range, $month, $year) . "
                            GROUP BY ur.status
                            ORDER BY count DESC";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':user_unique_id', $user_unique_id, PDO::PARAM_STR);
    if ($has_date_range) {
        $stmt->bindParam(':start_date', $start_date, PDO::PARAM_STR);
        $stmt->bindParam(':end_date', $end_date, PDO::PARAM_STR);
    } elseif (!$is_total_request) {
        $stmt->bindParam(':month', $month, PDO::PARAM_STR);
        $stmt->bindParam(':year', $year, PDO::PARAM_STR);
    }
    if (!empty($project_filter_named_params)) {
        foreach ($project_filter_named_params as $placeholder => $value) {
            $stmt->bindValue($placeholder, $value, PDO::PARAM_STR);
        }
    }
    $stmt->execute();
    $response['analytics']['detailed_status_counts'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- Detailed Source Counts (With Date Filter) ---
    $query = "SELECT 
    sud.source_of_lead,
    COUNT(*) as count
FROM user_remarks ur
LEFT JOIN shi_upload_data sud ON sud.id = ur.upload_data_id
WHERE ur.user_unique_id = :user_unique_id
    AND ur.history_h = 0
    AND sud.source_of_lead IS NOT NULL
    AND sud.source_of_lead != ''
    " . $project_filter_named_clause_ur .
        buildDateFilter("ur.created_at", $is_total_request, $has_date_range, $month, $year) . "
GROUP BY sud.source_of_lead
ORDER BY count DESC";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':user_unique_id', $user_unique_id, PDO::PARAM_STR);
    if ($has_date_range) {
        $stmt->bindParam(':start_date', $start_date, PDO::PARAM_STR);
        $stmt->bindParam(':end_date', $end_date, PDO::PARAM_STR);
    } elseif (!$is_total_request) {
        $stmt->bindParam(':month', $month, PDO::PARAM_STR);
        $stmt->bindParam(':year', $year, PDO::PARAM_STR);
    }
    if (!empty($project_filter_named_params)) {
        foreach ($project_filter_named_params as $placeholder => $value) {
            $stmt->bindValue($placeholder, $value, PDO::PARAM_STR);
        }
    }
    $stmt->execute();
    $response['analytics']['detailed_source_counts'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // === VECTORIZED ALL-TIME LEAD AND STATUS METRICS ===
    // Combined 4 redundant network round-trips computing duplicate numbers into a single grouped query
    $query = "SELECT 
                COUNT(*) as total_leads,
                SUM(CASE WHEN ur.status = 'Converted' THEN 1 ELSE 0 END) as converted_leads,
                SUM(CASE WHEN ur.status = 'Site Visit Done' THEN 1 ELSE 0 END) as site_visits_done
              FROM user_remarks ur
              WHERE ur.user_unique_id = :user_unique_id
                AND ur.history_h = 0";

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':user_unique_id', $user_unique_id, PDO::PARAM_STR);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $totalLeads = (int) ($row['total_leads'] ?? 0);
    $convertedLeads = (int) ($row['converted_leads'] ?? 0);
    $siteVisitsDone = (int) ($row['site_visits_done'] ?? 0);

    $response['analytics']['total_leads'] = $totalLeads;
    $response['analytics']['conversion_rate'] = $totalLeads > 0 ? (($convertedLeads / $totalLeads) * 100) : 0;
    $response['analytics']['converted_leads'] = $convertedLeads;
    $response['analytics']['site_visits_done'] = $siteVisitsDone;

    recordProfiling('Individual_User_Popup_Metrics', $profiling, $start_time);

    // === HIERARCHY DATA ===

    // --- Current User Details (selected user) ---
    $query = "SELECT tablename, username, useremail, user_type, doj, assign_user, employee_id
              FROM accounts 
              WHERE tablename = :user_unique_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':user_unique_id', $user_unique_id, PDO::PARAM_STR);
    $stmt->execute();
    $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($currentUser) {
        $response['hierarchy']['current_user'] = [
            'username' => $currentUser['username'],
            'useremail' => $currentUser['useremail'],
            'user_type' => $currentUser['user_type'],
            'doj' => $currentUser['doj'],
            'employee_id' => $currentUser['employee_id'],
            'assign_user' => $currentUser['assign_user']
        ];

        // Build full upward chain (current user -> leader -> ...)
        $response['hierarchy']['chain'] = build_upward_chain($conn, $currentUser);

        // Direct manager details (if any)
        if (!empty($currentUser['assign_user'])) {
            $mgr = get_user_by_tablename($conn, trim((string) $currentUser['assign_user']));
            if ($mgr) {
                $response['hierarchy']['manager'] = [
                    'username' => $mgr['username'],
                    'useremail' => $mgr['useremail'],
                    'user_type' => $mgr['user_type'],
                    'doj' => $mgr['doj'],
                    'employee_id' => $mgr['employee_id']
                ];
            }
        }
    }

    // --- CEO Details ---
    $query = "SELECT username, useremail, user_type, doj, employee_id
              FROM accounts 
              WHERE user_type = 'ceo' 
              ORDER BY created_at ASC 
              LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $ceo = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($ceo) {
        $response['hierarchy']['ceo'] = [
            'username' => $ceo['username'],
            'useremail' => $ceo['useremail'],
            'user_type' => $ceo['user_type'],
            'doj' => $ceo['doj'],
            'employee_id' => $ceo['employee_id']
        ];
    }

    recordProfiling('Hierarchy_Building', $profiling, $start_time);
    $response['profiling'] = $profiling;
    $response['profiling']['Total_Execution_Time'] = round((microtime(true) - $start_time) * 1000, 2) . ' ms';

} catch (Exception $e) {
    $response = [
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ];
}

header('Content-Type: application/json');
echo json_encode($response);
exit;