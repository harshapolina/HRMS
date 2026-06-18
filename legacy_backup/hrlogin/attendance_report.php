<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
date_default_timezone_set('Asia/Kolkata');
// Redirect to login if not logged in or not an admin
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'hradminuser') {
    header('Location: /');
    exit;
}
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}
require_once __DIR__ . '/includes/db_mysqli.php';
try {
    $con = hr_mysqli_connect();
} catch (Throwable $e) {
    exit('Failed to connect to MySQL. Check database host/credentials in config.php (Hostinger hPanel → Databases → MySQL hostname).');
}
hr_ensure_user_attendance_table($con);
// CSV EXPORT LOGIC (Must be before any output)
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $from_date = isset($_GET['from']) ? $_GET['from'] : date('Y-m-d');
    $to_date = isset($_GET['to']) ? $_GET['to'] : date('Y-m-d');
    $search_query = isset($_GET['search']) ? $_GET['search'] : '';
    $status_filter = isset($_GET['status']) ? $_GET['status'] : '';
    $search_param = "%$search_query%";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Attendance_Export_' . $from_date . '_to_' . $to_date . '.csv');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($output, ['ID', 'Emp ID', 'Unique ID', 'Name', 'Email', 'Contact', 'Role', 'Date', 'Punch In', 'Punch Out', 'Work Hours', 'Status', 'IP Address']);
    // Determine query style (all employees for single day or logs for range)
    $is_range = ($from_date !== $to_date);
    if (!empty($search_query) || $is_range) {
        $from_esc_csv = mysqli_real_escape_string($con, $from_date);
        $to_esc_csv = mysqli_real_escape_string($con, $to_date);
        $having = "";
        if ($status_filter) {
            $having = ($status_filter === 'On Leave') ? " HAVING status COLLATE utf8mb4_unicode_ci NOT IN ('Present', 'Late', 'Absent') " : " HAVING status COLLATE utf8mb4_unicode_ci = ? ";
        }
        $sql = "SELECT a.id, a.username, a.employee_id, a.tablename as uniqueid, a.useremail, a.phonenumber as contact, a.user_type as role, DATE_ADD('$from_esc_csv', INTERVAL d.n DAY) as date,
                       JSON_UNQUOTE(JSON_EXTRACT(ua.history_json, CONCAT('$.\"', DATE_FORMAT(DATE_ADD('$from_esc_csv', INTERVAL d.n DAY), '%Y-%m-%d'), '\".punch_in'))) as punch_in,
                       JSON_UNQUOTE(JSON_EXTRACT(ua.history_json, CONCAT('$.\"', DATE_FORMAT(DATE_ADD('$from_esc_csv', INTERVAL d.n DAY), '%Y-%m-%d'), '\".punch_out'))) as punch_out,
                       CAST(JSON_UNQUOTE(JSON_EXTRACT(ua.history_json, CONCAT('$.\"', DATE_FORMAT(DATE_ADD('$from_esc_csv', INTERVAL d.n DAY), '%Y-%m-%d'), '\".total_hours'))) AS DECIMAL(5,2)) as total_hours,
                       JSON_UNQUOTE(JSON_EXTRACT(ua.history_json, CONCAT('$.\"', DATE_FORMAT(DATE_ADD('$from_esc_csv', INTERVAL d.n DAY), '%Y-%m-%d'), '\".latitude_in'))) as latitude_in,
                       JSON_UNQUOTE(JSON_EXTRACT(ua.history_json, CONCAT('$.\"', DATE_FORMAT(DATE_ADD('$from_esc_csv', INTERVAL d.n DAY), '%Y-%m-%d'), '\".longitude_in'))) as longitude_in,
                       JSON_UNQUOTE(JSON_EXTRACT(ua.history_json, CONCAT('$.\"', DATE_FORMAT(DATE_ADD('$from_esc_csv', INTERVAL d.n DAY), '%Y-%m-%d'), '\".latitude_out'))) as latitude_out,
                       JSON_UNQUOTE(JSON_EXTRACT(ua.history_json, CONCAT('$.\"', DATE_FORMAT(DATE_ADD('$from_esc_csv', INTERVAL d.n DAY), '%Y-%m-%d'), '\".longitude_out'))) as longitude_out,
                       COALESCE(
                           JSON_UNQUOTE(JSON_EXTRACT(ua.history_json, CONCAT('$.\"', DATE_FORMAT(DATE_ADD('$from_esc_csv', INTERVAL d.n DAY), '%Y-%m-%d'), '\".status'))),
                           (SELECT t.leave_name FROM leave_requests lr JOIN leave_types t ON lr.leave_type_id = t.id WHERE lr.user_id = a.id AND lr.status = 'Approved' AND DATE_ADD('$from_esc_csv', INTERVAL d.n DAY) BETWEEN lr.start_date AND lr.end_date LIMIT 1),
                           'Absent'
                       ) as status,
                       JSON_UNQUOTE(JSON_EXTRACT(ua.history_json, CONCAT('$.\"', DATE_FORMAT(DATE_ADD('$from_esc_csv', INTERVAL d.n DAY), '%Y-%m-%d'), '\".ip_address'))) as ip_address
                FROM accounts a 
                CROSS JOIN (
                    SELECT 0 as n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10
                    UNION SELECT 11 UNION SELECT 12 UNION SELECT 13 UNION SELECT 14 UNION SELECT 15 UNION SELECT 16 UNION SELECT 17 UNION SELECT 18 UNION SELECT 19 UNION SELECT 20
                    UNION SELECT 21 UNION SELECT 22 UNION SELECT 23 UNION SELECT 24 UNION SELECT 25 UNION SELECT 26 UNION SELECT 27 UNION SELECT 28 UNION SELECT 29 UNION SELECT 30
                ) as d
                LEFT JOIN user_attendance ua ON a.id = ua.user_id
                WHERE DATE_ADD('$from_esc_csv', INTERVAL d.n DAY) <= '$to_esc_csv'
                AND (a.username LIKE ? OR a.employee_id LIKE ? OR a.useremail LIKE ? OR a.id LIKE ?)
                $having ORDER BY date DESC, a.username ASC";
        $stmt = $con->prepare($sql);
        $search_param = "%$search_query%";
        if ($status_filter && $status_filter !== 'On Leave')
            $stmt->bind_param('sssss', $search_param, $search_param, $search_param, $search_param, $status_filter);
        else
            $stmt->bind_param('ssss', $search_param, $search_param, $search_param, $search_param);
    } else {
        // Daily View logic (Single Day)
        $having = "";
        if ($status_filter) {
            $having = ($status_filter === 'On Leave') ? " HAVING status COLLATE utf8mb4_unicode_ci NOT IN ('Present', 'Late', 'Absent') " : " HAVING status COLLATE utf8mb4_unicode_ci = ? ";
        }
        if ($from_date === date('Y-m-d')) {
            $sql = "SELECT a.id, a.username, a.employee_id, a.tablename as uniqueid, a.useremail, a.phonenumber as contact, a.user_type as role, ? as date, 
                           CASE WHEN ua.today_date = '$from_date' THEN ua.today_punch_in ELSE NULL END as punch_in,
                           CASE WHEN ua.today_date = '$from_date' THEN ua.today_punch_out ELSE NULL END as punch_out,
                           CASE WHEN ua.today_date = '$from_date' THEN ua.today_total_hours ELSE NULL END as total_hours,
                           CASE WHEN ua.today_date = '$from_date' THEN ua.today_lat_in ELSE NULL END as latitude_in,
                           CASE WHEN ua.today_date = '$from_date' THEN ua.today_lng_in ELSE NULL END as longitude_in,
                           CASE WHEN ua.today_date = '$from_date' THEN ua.today_lat_out ELSE NULL END as latitude_out,
                           CASE WHEN ua.today_date = '$from_date' THEN ua.today_lng_out ELSE NULL END as longitude_out,
                           CONVERT(COALESCE(
                               CASE WHEN ua.today_date = '$from_date' THEN ua.today_status ELSE NULL END, 
                               (SELECT t.leave_name FROM leave_requests lr JOIN leave_types t ON lr.leave_type_id = t.id WHERE lr.user_id = a.id AND lr.status = 'Approved' AND ? BETWEEN lr.start_date AND lr.end_date LIMIT 1), 
                               'Absent'
                           ) USING utf8mb4) as status, 
                           CASE WHEN ua.today_date = '$from_date' THEN ua.today_ip ELSE NULL END as ip_address 
                    FROM accounts a LEFT JOIN user_attendance ua ON a.id = ua.user_id
                    $having ORDER BY status DESC, a.username ASC";
        } else {
            $path_prefix = '$.\"' . $from_date . '\"';
            $sql = "SELECT a.id, a.username, a.employee_id, a.tablename as uniqueid, a.useremail, a.phonenumber as contact, a.user_type as role, ? as date, 
                           JSON_UNQUOTE(JSON_EXTRACT(ua.history_json, '{$path_prefix}.punch_in')) as punch_in,
                           JSON_UNQUOTE(JSON_EXTRACT(ua.history_json, '{$path_prefix}.punch_out')) as punch_out,
                           CAST(JSON_UNQUOTE(JSON_EXTRACT(ua.history_json, '{$path_prefix}.total_hours')) AS DECIMAL(5,2)) as total_hours,
                           JSON_UNQUOTE(JSON_EXTRACT(ua.history_json, '{$path_prefix}.latitude_in')) as latitude_in,
                           JSON_UNQUOTE(JSON_EXTRACT(ua.history_json, '{$path_prefix}.longitude_in')) as longitude_in,
                           JSON_UNQUOTE(JSON_EXTRACT(ua.history_json, '{$path_prefix}.latitude_out')) as latitude_out,
                           JSON_UNQUOTE(JSON_EXTRACT(ua.history_json, '{$path_prefix}.longitude_out')) as longitude_out,
                           COALESCE(
                               JSON_UNQUOTE(JSON_EXTRACT(ua.history_json, '{$path_prefix}.status')),
                               (SELECT t.leave_name FROM leave_requests lr JOIN leave_types t ON lr.leave_type_id = t.id WHERE lr.user_id = a.id AND lr.status = 'Approved' AND ? BETWEEN lr.start_date AND lr.end_date LIMIT 1),
                               'Absent'
                           ) as status,
                           JSON_UNQUOTE(JSON_EXTRACT(ua.history_json, '{$path_prefix}.ip_address')) as ip_address 
                    FROM accounts a LEFT JOIN user_attendance ua ON a.id = ua.user_id
                    $having ORDER BY status DESC, a.username ASC";
        }
        $stmt = $con->prepare($sql);
        if ($status_filter && $status_filter !== 'On Leave')
            $stmt->bind_param('sss', $from_date, $from_date, $status_filter);
        else
            $stmt->bind_param('ss', $from_date, $from_date);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        fputcsv($output, [
            $row['id'],
            $row['employee_id'],
            $row['uniqueid'],
            $row['username'],
            $row['useremail'],
            "\u{200B}" . $row['contact'],
            $row['role'],
            date('d/m/Y', strtotime($row['date'])),
            $row['punch_in'] ? date('h:i A', strtotime($row['punch_in'])) : '-',
            $row['punch_out'] ? date('h:i A', strtotime($row['punch_out'])) : '-',
            round($row['total_hours'], 1) . ' hrs',
            $row['status'],
            $row['ip_address'] ?: '-'
        ]);
    }
    fclose($output);
    exit;
}
$skip_superadmin_css = true;
require_once __DIR__ . '/htmlopen.php';
// Pagination Settings (Matches Incentive Users)
$allowedLimits = [10, 50, 100, 200, 300];
$recordsPerPage = isset($_GET['limit']) && in_array((int) $_GET['limit'], $allowedLimits) ? (int) $_GET['limit'] : 10;
$currentPage = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$offset = ($currentPage - 1) * $recordsPerPage;
// Fetch Office Settings for calculations
$office_end_time = '18:00:00'; // Default
$res_settings = $con->query("SELECT setting_value FROM hr_settings WHERE setting_key = 'office_end_time'");
if ($row_s = $res_settings->fetch_assoc())
    $office_end_time = $row_s['setting_value'];
// Dashboard Metrics (Calculated for the selected date)
// Date Range Logic
$from_date = isset($_GET['from']) ? $_GET['from'] : date('Y-m-d');
$to_date = isset($_GET['to']) ? $_GET['to'] : date('Y-m-d');
// Quick filters handling
if (isset($_GET['range'])) {
    if ($_GET['range'] === 'today') {
        $from_date = $to_date = date('Y-m-d');
    } elseif ($_GET['range'] === 'week') {
        $from_date = date('Y-m-d', strtotime('monday this week'));
        $to_date = date('Y-m-d', strtotime('sunday this week'));
    } elseif ($_GET['range'] === 'month') {
        $from_date = date('Y-m-01');
        $to_date = date('Y-m-t');
    }
}
$search_query = isset($_GET['search']) ? $_GET['search'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
// Dashboard Metrics (Calculated for the selected range)
// 1. Total Headcount (Active employees only)
$total_headcount = 0;
$res_hc = $con->query("SELECT COUNT(*) as count FROM accounts WHERE is_active = 1");
if ($row_hc = $res_hc->fetch_assoc()) {
    $total_headcount = (int) $row_hc['count'];
}
// 2. Metrics for selected range — SQL aggregates (avoid decoding full history_json in PHP)
$present_count = 0;
$late_count = 0;
$missing_out_count = 0;
$todayStr = date('Y-m-d');
$from_esc_metrics = mysqli_real_escape_string($con, $from_date);
$to_esc_metrics = mysqli_real_escape_string($con, $to_date);
$is_single_day = ($from_date === $to_date);

if ($is_single_day) {
    if ($from_date === $todayStr) {
        $metrics_sql = "SELECT
            SUM(CASE WHEN ua.today_date = '$todayStr' AND ua.today_status IS NOT NULL AND ua.today_status NOT IN ('Not Logged', 'Absent', 'Late-Absent', '') THEN 1 ELSE 0 END) AS present_count,
            SUM(CASE WHEN ua.today_date = '$todayStr' AND ua.today_status = 'Late' THEN 1 ELSE 0 END) AS late_count,
            SUM(CASE WHEN ua.today_date = '$todayStr' AND ua.today_status IS NOT NULL AND ua.today_status NOT IN ('Not Logged', 'Absent', 'Late-Absent', '')
                AND (ua.today_punch_out IS NULL OR ua.today_punch_out = '' OR ua.today_punch_out = '00:00:00') THEN 1 ELSE 0 END) AS missing_out_count
            FROM accounts a
            LEFT JOIN user_attendance ua ON a.id = ua.user_id
            WHERE a.is_active = 1";
    } else {
        $json_path = '$."' . $from_esc_metrics . '".status';
        $json_out_path = '$."' . $from_esc_metrics . '".punch_out';
        $metrics_sql = "SELECT
            SUM(CASE WHEN st IS NOT NULL AND st NOT IN ('Absent', 'Late-Absent', '', 'Not Logged') THEN 1 ELSE 0 END) AS present_count,
            SUM(CASE WHEN st = 'Late' THEN 1 ELSE 0 END) AS late_count,
            SUM(CASE WHEN st IS NOT NULL AND st NOT IN ('Absent', 'Late-Absent', '', 'Not Logged')
                AND (po IS NULL OR po = '' OR po = 'null') THEN 1 ELSE 0 END) AS missing_out_count
            FROM (
                SELECT JSON_UNQUOTE(JSON_EXTRACT(ua.history_json, '{$json_path}')) AS st,
                       JSON_UNQUOTE(JSON_EXTRACT(ua.history_json, '{$json_out_path}')) AS po
                FROM accounts a
                LEFT JOIN user_attendance ua ON a.id = ua.user_id
                WHERE a.is_active = 1
            ) AS day_rows";
    }
    $metrics_res = $con->query($metrics_sql);
    if ($metrics_res && ($metrics_row = $metrics_res->fetch_assoc())) {
        $present_count = (int) ($metrics_row['present_count'] ?? 0);
        $late_count = (int) ($metrics_row['late_count'] ?? 0);
        $missing_out_count = (int) ($metrics_row['missing_out_count'] ?? 0);
    }
} else {
    $range_metrics_sql = "SELECT
        SUM(CASE WHEN status IS NOT NULL AND status NOT IN ('Absent', 'Late-Absent', 'Not Logged', '') THEN 1 ELSE 0 END) AS present_count,
        SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) AS late_count,
        SUM(CASE WHEN status IS NOT NULL AND status NOT IN ('Absent', 'Late-Absent', 'Not Logged', '')
            AND (punch_out IS NULL OR punch_out = '' OR punch_out = '00:00:00') THEN 1 ELSE 0 END) AS missing_out_count
        FROM attendance_logs
        WHERE punch_date BETWEEN '{$from_esc_metrics}' AND '{$to_esc_metrics}'";
    $range_res = $con->query($range_metrics_sql);
    if ($range_res && ($range_row = $range_res->fetch_assoc())) {
        $present_count = (int) ($range_row['present_count'] ?? 0);
        $late_count = (int) ($range_row['late_count'] ?? 0);
        $missing_out_count = (int) ($range_row['missing_out_count'] ?? 0);
    }
}
// Calculate Leave Count for the selected date
$leave_count = ($from_date === $to_date) ? 0 : '---';
if ($from_date === $to_date) {
    if ($from_date === date('Y-m-d')) {
        $sql_leaves_count = "SELECT COUNT(*) as count FROM accounts a 
                             WHERE a.id NOT IN (SELECT user_id FROM user_attendance WHERE today_date = '$from_date' AND today_status IS NOT NULL AND today_status != 'Not Logged')
                             AND (SELECT lr.id FROM leave_requests lr 
                                  WHERE lr.user_id = a.id AND lr.status = 'Approved' 
                                  AND '$from_date' BETWEEN lr.start_date AND lr.end_date LIMIT 1) IS NOT NULL";
    } else {
        $sql_leaves_count = "SELECT COUNT(*) as count FROM accounts a 
                             WHERE a.id NOT IN (SELECT user_id FROM user_attendance WHERE JSON_UNQUOTE(JSON_EXTRACT(history_json, '$.\"$from_date\".status')) IS NOT NULL AND JSON_UNQUOTE(JSON_EXTRACT(history_json, '$.\"$from_date\".status')) NOT IN ('Absent', 'Late-Absent', ''))
                             AND (SELECT lr.id FROM leave_requests lr 
                                  WHERE lr.user_id = a.id AND lr.status = 'Approved' 
                                  AND '$from_date' BETWEEN lr.start_date AND lr.end_date LIMIT 1) IS NOT NULL";
    }
    $res_lc = $con->query($sql_leaves_count);
    if ($res_lc && $row_lc = $res_lc->fetch_assoc()) {
        $leave_count = $row_lc['count'];
    }
}
// For absents in a range, it's complex, so for simplicity we show daily absent average or total records
$absent_count = ($from_date === $to_date) ? max(0, $total_headcount - $present_count - $leave_count) : '---';
$present_percent = ($from_date === $to_date && $total_headcount > 0) ? round(($present_count / $total_headcount) * 100) : '--';
// Fetch Attendance Logs for Table with Pagination
$totalEntries = 0;
$from_esc = mysqli_real_escape_string($con, $from_date);
$to_esc = mysqli_real_escape_string($con, $to_date);

$is_range = ($from_date !== $to_date);
if (!empty($search_query) || $is_range) {
    // RANGE VIEW OR SEARCH VIEW: Show actual logs
    $view_mode = "Records from " . date('d M', strtotime($from_date)) . " to " . date('d M Y', strtotime($to_date));
    if (!empty($search_query))
        $view_mode .= " (Searching: $search_query)";
    // Get Total Count for Pagination
    $search_param = "%$search_query%";
    $having = "";
    if ($status_filter) {
        $having = ($status_filter === 'On Leave') ? " HAVING status COLLATE utf8mb4_unicode_ci NOT IN ('Present', 'Late', 'Absent') " : " HAVING status COLLATE utf8mb4_unicode_ci = ? ";
    }
    $sql_count = "SELECT COUNT(*) as total FROM (
                    SELECT a.id, 
                           COALESCE(
                               JSON_UNQUOTE(JSON_EXTRACT(ua.history_json, CONCAT('$.\"', DATE_FORMAT(DATE_ADD('$from_esc', INTERVAL d.n DAY), '%Y-%m-%d'), '\".status'))),
                               'Absent'
                           ) as status
                    FROM accounts a
                    CROSS JOIN (
                        SELECT 0 as n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10
                        UNION SELECT 11 UNION SELECT 12 UNION SELECT 13 UNION SELECT 14 UNION SELECT 15 UNION SELECT 16 UNION SELECT 17 UNION SELECT 18 UNION SELECT 19 UNION SELECT 20
                        UNION SELECT 21 UNION SELECT 22 UNION SELECT 23 UNION SELECT 24 UNION SELECT 25 UNION SELECT 26 UNION SELECT 27 UNION SELECT 28 UNION SELECT 29 UNION SELECT 30
                    ) as d
                    LEFT JOIN user_attendance ua ON a.id = ua.user_id
                    WHERE DATE_ADD('$from_esc', INTERVAL d.n DAY) <= '$to_esc'
                    AND (a.username LIKE ? OR a.employee_id LIKE ? OR a.useremail LIKE ? OR a.id LIKE ?)
                    $having
                  ) as t";
    $stmt_c = $con->prepare($sql_count);
    if ($status_filter && $status_filter !== 'On Leave')
        $stmt_c->bind_param('sssss', $search_param, $search_param, $search_param, $search_param, $status_filter);
    else
        $stmt_c->bind_param('ssss', $search_param, $search_param, $search_param, $search_param);
    $stmt_c->execute();
    $totalEntries = $stmt_c->get_result()->fetch_assoc()['total'];

    $sql = "SELECT a.username, a.id as user_id, a.employee_id, a.tablename as uniqueid, a.useremail, a.phonenumber as contact, a.user_type as role,
                   DATE_ADD('$from_esc', INTERVAL d.n DAY) as punch_date,
                   JSON_UNQUOTE(JSON_EXTRACT(ua.history_json, CONCAT('$.\"', DATE_FORMAT(DATE_ADD('$from_esc', INTERVAL d.n DAY), '%Y-%m-%d'), '\".punch_in'))) as punch_in,
                   JSON_UNQUOTE(JSON_EXTRACT(ua.history_json, CONCAT('$.\"', DATE_FORMAT(DATE_ADD('$from_esc', INTERVAL d.n DAY), '%Y-%m-%d'), '\".punch_out'))) as punch_out,
                   CAST(JSON_UNQUOTE(JSON_EXTRACT(ua.history_json, CONCAT('$.\"', DATE_FORMAT(DATE_ADD('$from_esc', INTERVAL d.n DAY), '%Y-%m-%d'), '\".total_hours'))) AS DECIMAL(5,2)) as total_hours,
                   JSON_UNQUOTE(JSON_EXTRACT(ua.history_json, CONCAT('$.\"', DATE_FORMAT(DATE_ADD('$from_esc', INTERVAL d.n DAY), '%Y-%m-%d'), '\".latitude_in'))) as latitude_in,
                   JSON_UNQUOTE(JSON_EXTRACT(ua.history_json, CONCAT('$.\"', DATE_FORMAT(DATE_ADD('$from_esc', INTERVAL d.n DAY), '%Y-%m-%d'), '\".longitude_in'))) as longitude_in,
                   JSON_UNQUOTE(JSON_EXTRACT(ua.history_json, CONCAT('$.\"', DATE_FORMAT(DATE_ADD('$from_esc', INTERVAL d.n DAY), '%Y-%m-%d'), '\".latitude_out'))) as latitude_out,
                   JSON_UNQUOTE(JSON_EXTRACT(ua.history_json, CONCAT('$.\"', DATE_FORMAT(DATE_ADD('$from_esc', INTERVAL d.n DAY), '%Y-%m-%d'), '\".longitude_out'))) as longitude_out,
                   COALESCE(
                       JSON_UNQUOTE(JSON_EXTRACT(ua.history_json, CONCAT('$.\"', DATE_FORMAT(DATE_ADD('$from_esc', INTERVAL d.n DAY), '%Y-%m-%d'), '\".status'))),
                       (SELECT t.leave_name FROM leave_requests lr JOIN leave_types t ON lr.leave_type_id = t.id WHERE lr.user_id = a.id AND lr.status = 'Approved' AND DATE_ADD('$from_esc', INTERVAL d.n DAY) BETWEEN lr.start_date AND lr.end_date LIMIT 1),
                       'Absent'
                   ) as status, 
                   JSON_UNQUOTE(JSON_EXTRACT(ua.history_json, CONCAT('$.\"', DATE_FORMAT(DATE_ADD('$from_esc', INTERVAL d.n DAY), '%Y-%m-%d'), '\".ip_address'))) as ip_address 
            FROM accounts a
            CROSS JOIN (
                SELECT 0 as n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10
                UNION SELECT 11 UNION SELECT 12 UNION SELECT 13 UNION SELECT 14 UNION SELECT 15 UNION SELECT 16 UNION SELECT 17 UNION SELECT 18 UNION SELECT 19 UNION SELECT 20
                UNION SELECT 21 UNION SELECT 22 UNION SELECT 23 UNION SELECT 24 UNION SELECT 25 UNION SELECT 26 UNION SELECT 27 UNION SELECT 28 UNION SELECT 29 UNION SELECT 30
            ) as d
            LEFT JOIN user_attendance ua ON a.id = ua.user_id
            WHERE DATE_ADD('$from_esc', INTERVAL d.n DAY) <= '$to_esc'
            AND (a.username LIKE ? OR a.employee_id LIKE ? OR a.useremail LIKE ? OR a.id LIKE ?)
            $having
            ORDER BY punch_date DESC, a.username ASC LIMIT ? OFFSET ?";

    $stmt = $con->prepare($sql);
    if ($status_filter && $status_filter !== 'On Leave') {
        $stmt->bind_param('sssssii', $search_param, $search_param, $search_param, $search_param, $status_filter, $recordsPerPage, $offset);
    } else {
        $stmt->bind_param('ssssii', $search_param, $search_param, $search_param, $search_param, $recordsPerPage, $offset);
    }
} else {
    // DEFAULT DAILY VIEW: Show ALL employees (Single Day)
    $view_mode = "Attendance Log: " . date('F j, Y', strtotime($from_date));

    // Get Total Count for Pagination
    $sql_count = "SELECT COUNT(*) as total FROM accounts";
    $totalEntries = $con->query($sql_count)->fetch_assoc()['total'];
    $having = "";
    if ($status_filter) {
        $having = ($status_filter === 'On Leave') ? " HAVING status COLLATE utf8mb4_unicode_ci NOT IN ('Present', 'Late', 'Absent') " : " HAVING status COLLATE utf8mb4_unicode_ci = ? ";
    }
    if ($from_date === date('Y-m-d')) {
        $sql = "SELECT a.username, a.id as user_id, a.employee_id, a.tablename as uniqueid, a.useremail, a.phonenumber as contact, a.user_type as role,
                       ? as punch_date, 
                       CASE WHEN ua.today_date = '$from_date' THEN ua.today_punch_in ELSE NULL END as punch_in,
                       CASE WHEN ua.today_date = '$from_date' THEN ua.today_punch_out ELSE NULL END as punch_out,
                       CASE WHEN ua.today_date = '$from_date' THEN ua.today_total_hours ELSE NULL END as total_hours,
                       CASE WHEN ua.today_date = '$from_date' THEN ua.today_lat_in ELSE NULL END as latitude_in,
                       CASE WHEN ua.today_date = '$from_date' THEN ua.today_lng_in ELSE NULL END as longitude_in,
                       CASE WHEN ua.today_date = '$from_date' THEN ua.today_lat_out ELSE NULL END as latitude_out,
                       CASE WHEN ua.today_date = '$from_date' THEN ua.today_lng_out ELSE NULL END as longitude_out,
                       CONVERT(COALESCE(
                           CASE WHEN ua.today_date = '$from_date' THEN ua.today_status ELSE NULL END, 
                           (SELECT t.leave_name FROM leave_requests lr 
                            JOIN leave_types t ON lr.leave_type_id = t.id 
                            WHERE lr.user_id = a.id AND lr.status = 'Approved' 
                            AND ? BETWEEN lr.start_date AND lr.end_date LIMIT 1),
                           'Absent') USING utf8mb4) as status, 
                       CASE WHEN ua.today_date = '$from_date' THEN ua.today_ip ELSE NULL END as ip_address 
                FROM accounts a
                LEFT JOIN user_attendance ua ON a.id = ua.user_id
                $having
                ORDER BY status DESC, a.username ASC LIMIT ? OFFSET ?";
    } else {
        $path_prefix = '$.\"' . $from_date . '\"';
        $sql = "SELECT a.username, a.id as user_id, a.employee_id, a.tablename as uniqueid, a.useremail, a.phonenumber as contact, a.user_type as role,
                       ? as punch_date, 
                       JSON_UNQUOTE(JSON_EXTRACT(ua.history_json, '{$path_prefix}.punch_in')) as punch_in,
                       JSON_UNQUOTE(JSON_EXTRACT(ua.history_json, '{$path_prefix}.punch_out')) as punch_out,
                       CAST(JSON_UNQUOTE(JSON_EXTRACT(ua.history_json, '{$path_prefix}.total_hours')) AS DECIMAL(5,2)) as total_hours,
                       JSON_UNQUOTE(JSON_EXTRACT(ua.history_json, '{$path_prefix}.latitude_in')) as latitude_in,
                       JSON_UNQUOTE(JSON_EXTRACT(ua.history_json, '{$path_prefix}.longitude_in')) as longitude_in,
                       JSON_UNQUOTE(JSON_EXTRACT(ua.history_json, '{$path_prefix}.latitude_out')) as latitude_out,
                       JSON_UNQUOTE(JSON_EXTRACT(ua.history_json, '{$path_prefix}.longitude_out')) as longitude_out,
                       COALESCE(
                           JSON_UNQUOTE(JSON_EXTRACT(ua.history_json, '{$path_prefix}.status')),
                           (SELECT t.leave_name FROM leave_requests lr 
                            JOIN leave_types t ON lr.leave_type_id = t.id 
                            WHERE lr.user_id = a.id AND lr.status = 'Approved' 
                            AND ? BETWEEN lr.start_date AND lr.end_date LIMIT 1),
                           'Absent') as status, 
                       JSON_UNQUOTE(JSON_EXTRACT(ua.history_json, '{$path_prefix}.ip_address')) as ip_address 
                FROM accounts a
                LEFT JOIN user_attendance ua ON a.id = ua.user_id
                $having
                ORDER BY status DESC, a.username ASC LIMIT ? OFFSET ?";
    }

    $stmt = $con->prepare($sql);
    if ($status_filter && $status_filter !== 'On Leave') {
        $stmt->bind_param('sssii', $from_date, $from_date, $status_filter, $recordsPerPage, $offset);
    } else {
        $stmt->bind_param('ssii', $from_date, $from_date, $recordsPerPage, $offset);
    }
}
$stmt->execute();
$result = $stmt->get_result();
$attendance_rows = [];
while ($row = $result->fetch_assoc()) {
    $attendance_rows[] = $row;
}
function att_status_badge_class($status)
{
    if ($status === 'Present')
        return 'badge-present';
    if ($status === 'Late')
        return 'badge-late';
    if ($status === 'Absent')
        return 'badge-absent';
    return 'badge-leave';
}
$totalPages = ceil($totalEntries / $recordsPerPage);
$showingStart = $offset + 1;
$showingEnd = min($offset + $recordsPerPage, $totalEntries);
?>
<link rel="stylesheet" href="./assets/css/style_dashboard.css?v=<?php echo time(); ?>" />
<link rel="stylesheet" href="./assets/css/unified_table_styles.css?v=<?php echo time(); ?>" />
<link rel="stylesheet" href="./assets/css/Users.css?v=<?php echo time(); ?>" />
<style>
    /* Base background - respects global theme */
    body,
    html {
        background: var(--bg-gradient) !important;
        background-attachment: fixed !important;
        background-size: cover !important;
        color: var(--text-dark) !important;
    }

    .incentivemain,
    .content {
        background: transparent !important;
        border: none !important;
        box-shadow: none !important;
    }

    /* Animated floating backgrounds - only in light mode for cleaner look */
    body:not(.dark-mode)::before,
    body:not(.dark-mode)::after {
        content: '';
        position: fixed;
        width: 200vw;
        height: 200vh;
        border-radius: 50%;
        z-index: -1;
        opacity: .3;
        animation: 15s ease-in-out infinite alternate float;
    }

    body::before {
        background: radial-gradient(circle, #d2b4ff 0, transparent 70%);
        top: -10vh;
        right: -50vw;
    }

    body::after {
        background: radial-gradient(circle, #f9eb9c 0, transparent 70%);
        bottom: -100vh;
        left: -50vw;
        animation-delay: 2.5s;
    }

    @keyframes float {
        0% {
            transform: translateY(0) scale(1);
        }

        100% {
            transform: translateY(-20px) scale(1.05);
        }
    }

    .glass-card {
        background: var(--table-bg) !important;
        backdrop-filter: blur(15px);
        border-radius: 20px;
        border: 1px solid var(--table-border);
        box-shadow: var(--shadow);
        color: var(--text-primary);
    }

    /* Internal Table Scrolling & Sticky Header */
    .user-table-scroll-wrapper {
        max-height: 580px;
        overflow-y: auto !important;
        overflow-x: auto !important;
        background: transparent !important;
        border: none !important;
        padding-bottom: 20px;
    }

    .user-data-table thead th {
        position: sticky !important;
        top: 0;
        z-index: 1000;
        background: var(--table-head-bg) !important;
        border-bottom: 2px solid var(--table-border) !important;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.02);
        color: var(--text-secondary) !important;
    }

    /* Reduced spacing for "top" look */
    .container-fluid {
        padding-top: 10px !important;
    }

    /* Match Employees page spacing near sidebar/content edge */
    @media (min-width: 769px) {
        .content {
            padding: 8px 10px 12px !important;
        }

        .content .container-fluid {
            padding-left: 0 !important;
            padding-right: 0 !important;
            padding-top: 6px !important;
        }

        .summary-wrapper {
            padding-left: 0 !important;
            margin-left: 0 !important;
        }
    }

    /* Calendar Specific Styles */
    .attendance-calendar-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 8px;
        max-width: 450px;
        margin: 0 auto;
        padding: 10px;
    }

    .calendar-day {
        aspect-ratio: 1;
        border-radius: 8px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--text-primary);
        background: var(--table-hover);
        border: 1px solid var(--table-border);
        position: relative;
    }

    .calendar-day.empty {
        background: transparent;
        border: none;
    }

    .calendar-header-day {
        font-size: 0.7rem;
        font-weight: 800;
        color: var(--text-muted);
        text-transform: uppercase;
        text-align: center;
        padding-bottom: 5px;
    }

    .status-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        margin-top: 3px;
    }

    .dot-present {
        background: #2e7d32;
    }

    .dot-late {
        background: #f9a825;
    }

    .dot-absent {
        background: #c62828;
    }

    .dot-dayoff {
        background: #2196f3;
    }

    .dot-company-holiday {
        background: #7c3aed;
    }

    .calendar-nav {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
        max-width: 450px;
        margin: 10px auto;
    }

    .calendar-nav button {
        background: var(--primary-teal);
        color: white;
        border: none;
        padding: 5px 12px;
        border-radius: 8px;
        cursor: pointer;
        transition: 0.2s;
        font-size: 0.85rem;
    }

    .calendar-nav button:hover {
        background: var(--primary-teal-dark);
    }

    .calendar-month-title {
        font-weight: 700;
        color: var(--primary-teal);
        font-size: 1.1rem;
    }

    .user-expand-row {
        background: var(--table-bg) !important;
        border-bottom: 1px solid var(--table-border) !important;
    }

    .user-expand-content {
        padding: 20px;
        border-radius: 0 0 12px 12px;
        color: var(--text-primary);
    }

    .user-expand-btn {
        border: none;
        background: transparent;
        color: var(--primary-teal);
        font-size: 1.2rem;
        cursor: pointer;
        transition: transform 0.3s;
        padding: 5px 15px;
        display: flex !important;
        align-items: center;
        justify-content: center;
        min-width: 40px;
        min-height: 40px;
    }

    .user-expand-btn.active {
        transform: rotate(90deg);
        color: #2e7d32;
    }

    .stat-pill {
        padding: 20px;
        border-radius: 20px;
        background: var(--table-bg);
        box-shadow: var(--shadow);
        border: 1px solid var(--table-border);
        transition: transform 0.3s ease;
    }

    .stat-pill:hover {
        transform: translateY(-5px);
    }

    .stat-label {
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    /* Hide button text on desktop to restore icon-only look */
    .btn-text {
        display: none;
    }

    /* Desktop control bar refinements - Align with Leave Management */
    @media (min-width: 769px) {
        #attendanceFilterForm { 
            display: flex; 
            flex-direction: row;
            align-items: center;
            justify-content: space-between;
            flex-wrap: nowrap;
            gap: 12px; 
            width: 100%;
        }
        
        .filter-group { 
            display: flex !important; 
            align-items: center !important; 
            gap: 12px !important; 
            width: auto !important;
            flex-wrap: nowrap !important;
        }

        #attendanceFilterForm .filter-group:first-child {
            flex: 1 1 auto !important;
            min-width: 0 !important;
        }

        #attendanceFilterForm .filter-group:last-child {
            flex: 0 0 auto !important;
        }

        #attendanceFilterForm .filter-group-search {
            flex: 1 1 auto !important;
            min-width: 0 !important;
        }

        #attendanceFilterForm .filter-group-actions {
            flex: 0 0 auto !important;
        }
        
        .header-tools-wrapper {
            display: flex !important;
            align-items: center !important;
            justify-content: flex-end !important;
            gap: 2px !important;
            flex-grow: 1 !important;
        }

        .btn-filter, .btn-column-visibility {
            padding: 10px 16px !important;
            width: auto !important;
            background: white !important;
            color: #666 !important;
            border: 1px solid #ddd !important;
            border-radius: 10px !important;
            height: 38px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 8px !important;
            font-size: 0.85rem !important;
            font-weight: 600 !important;
            transition: all 0.3s ease !important;
        }

        .btn-column-visibility {
            background: #3b82f6 !important;
            color: white !important;
            border-color: #3b82f6 !important;
        }
        
        .btn-filter:hover {
            background: #f8fafc !important;
            border-color: #cbd5e1 !important;
        }
        
        .btn-column-visibility:hover {
            background: #2563eb !important;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3) !important;
        }

        .btn-text {
            display: inline-block !important;
        }
        
        #attendance-limit {
            width: 75px !important;
            background-color: #fff !important;
            height: 38px !important;
            border-radius: 10px !important;
        }
        
        #quickStatus {
            width: 140px !important;
            background-color: #fff !important;
            height: 38px !important;
            border-radius: 10px !important;
        }
        
        .search-box {
            position: relative !important;
            width: 100% !important;
        }
        
        .search-box .search-input {
            width: 100% !important;
            min-width: 280px !important;
            height: 38px !important;
            border-radius: 10px !important;
            padding-left: 44px !important;
            background: #fff !important;
            border: 1px solid #ddd !important;
        }
    }

    .stat-value {
        font-size: 1.8rem;
        font-weight: 800;
        color: var(--primary-teal);
    }

    /* Attendance controls should match Employees page layout */
    .attendance-controls-card {
        background: transparent !important;
        border: none !important;
        box-shadow: none !important;
        padding: 0 !important;
    }

    .attendance-controls-card .control-bar {
        flex-wrap: nowrap !important;
        align-items: center !important;
        gap: 10px !important;
    }

    .attendance-controls-card .control-left {
        display: none !important;
    }

    .attendance-controls-card .control-right,
    .attendance-controls-card .header-tools-wrapper {
        width: 100% !important;
        flex-grow: 1 !important;
    }

    .attendance-controls-card #attendanceFilterForm {
        width: 100% !important;
        display: flex !important;
        align-items: center !important;
        justify-content: flex-start !important;
        flex-wrap: nowrap !important;
        gap: 12px !important;
    }

    .attendance-controls-card .filter-group {
        display: flex !important;
        align-items: center !important;
        flex-wrap: nowrap !important;
        gap: 10px !important;
    }

    .attendance-controls-card .filter-group-search {
        flex: 1 1 auto !important;
        min-width: 0 !important;
    }

    .attendance-controls-card .filter-group-actions {
        flex: 0 0 auto !important;
        margin-left: auto !important;
    }

    .attendance-controls-card .search-box .search-input {
        width: 100% !important;
        min-width: 420px !important;
        background: #ffffff !important;
        border: 1px solid #ddd !important;
        border-radius: 8px !important;
    }

    .attendance-controls-card .page-size-selector {
        padding: 0 12px !important;
        border-radius: 8px !important;
        background: #fff !important;
        border: 1px solid #ddd !important;
    }

    .attendance-controls-card #attendance-limit {
        width: 64px !important;
        border: 0 !important;
        box-shadow: none !important;
        background: transparent !important;
    }

    /* Dark mode visibility fixes for attendance controls */
    body.dark-mode .attendance-controls-card .search-box .search-input,
    body.dark-mode .attendance-controls-card .btn-filter,
    body.dark-mode .attendance-controls-card .page-size-selector {
        background: rgba(255, 255, 255, 0.05) !important;
        color: #ffffff !important;
        border-color: rgba(255, 255, 255, 0.1) !important;
    }
    body.dark-mode .attendance-controls-card #quickStatus {
        background-color: rgba(255, 255, 255, 0.05) !important;
        color: #ffffff !important;
        border-color: rgba(255, 255, 255, 0.1) !important;
    }
    body.dark-mode .attendance-controls-card #attendance-limit {
        background: transparent !important;
        background-color: transparent !important;
        background-image: none !important;
        color: #ffffff !important;
        border: 0 !important;
        backdrop-filter: none !important;
        -webkit-backdrop-filter: none !important;
        -webkit-appearance: menulist !important;
        appearance: menulist !important;
    }

    body.dark-mode .attendance-controls-card .page-size-selector::after,
    body.dark-mode .attendance-controls-card .page-size-selector::before {
        content: none !important;
        display: none !important;
    }

    body.dark-mode .attendance-controls-card .search-box .search-input::placeholder {
        color: rgba(248, 250, 252, 0.65) !important;
    }

    body.dark-mode .attendance-controls-card #quickStatus option,
    body.dark-mode .attendance-controls-card #attendance-limit option {
        background: #1f2937 !important;
        color: #f8fafc !important;
    }

    body.dark-mode .attendance-controls-card .btn-column-visibility {
        background: #3b82f6 !important;
        color: #ffffff !important;
        border-color: #3b82f6 !important;
    }

    /* Status dropdown wrapper:
       - Desktop: behave like normal select (no icon overlay)
       - Mobile: icon-only styles are defined inside the mobile media query */
    .status-icon-select {
        display: contents;
    }

    .status-icon-select > i {
        display: none !important;
    }

    .status-icon-select > #quickStatus {
        opacity: 1 !important;
        position: static !important;
        inset: auto !important;
    }

    /* ===== Final consistency lock: match Employees toolbar exactly ===== */
    .attendance-controls-card .control-bar {
        gap: 16px !important;
        margin-top: 0 !important;
        margin-bottom: 0 !important;
        align-items: center !important;
    }

    .attendance-controls-card #attendanceFilterForm {
        align-items: center !important;
    }

    .attendance-controls-card .filter-group {
        align-items: center !important;
    }

    .attendance-controls-card .control-right {
        display: flex !important;
        align-items: center !important;
        gap: 12px !important;
    }

    .attendance-controls-card #attendanceFilterForm {
        gap: 12px !important;
    }

    .attendance-controls-card .filter-group {
        gap: 12px !important;
    }

    .attendance-controls-card .search-box .search-input {
        width: 100% !important;
        min-width: 420px !important;
        font-size: 14px !important;
        border: 1px solid #ddd !important;
        border-radius: 8px !important;
        background: #ffffff !important;
    }

    .attendance-controls-card #quickStatus,
    .attendance-controls-card .btn-filter,
    .attendance-controls-card .btn-column-visibility {
        display: inline-flex !important;
        align-items: center !important;
        gap: 8px !important;
        border-radius: 8px !important;
        font-size: 14px !important;
        font-weight: 500 !important;
    }

    .attendance-controls-card #quickStatus,
    .attendance-controls-card .btn-filter {
        background: #ffffff !important;
        color: #555 !important;
        border: 1px solid #ddd !important;
    }

    .attendance-controls-card .btn-column-visibility {
        background: #3b82f6 !important;
        color: #ffffff !important;
        border: 1px solid #3b82f6 !important;
    }

    .attendance-controls-card .btn-column-visibility:hover {
        background: #1976d2 !important;
        border-color: #1976d2 !important;
    }

    .attendance-controls-card .btn-filter:hover {
        background: #f5f5f5 !important;
        border-color: #bbb !important;
    }

    .attendance-controls-card .page-size-selector {
        display: inline-flex !important;
        align-items: center !important;
        border-radius: 8px !important;
        border: 1px solid #ddd !important;
        background: #ffffff !important;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02) !important;
    }

    .attendance-controls-card #attendance-limit {
        width: 46px !important;
        padding: 0 !important;
        border: 0 !important;
        background: transparent !important;
        font-size: 14px !important;
        color: #333 !important;
    }

    /* Uniform toolbar height — match All Status (#quickStatus) */
    .attendance-controls-card #quickStatus,
    .attendance-controls-card .search-box .search-input,
    .attendance-controls-card .btn-filter,
    .attendance-controls-card .btn-column-visibility,
    .attendance-controls-card .page-size-selector {
        height: 38px !important;
        min-height: 38px !important;
        max-height: 38px !important;
        box-sizing: border-box !important;
    }

    .attendance-controls-card .search-box .search-input {
        padding: 0 16px 0 44px !important;
        line-height: normal !important;
    }

    .attendance-controls-card #quickStatus {
        padding: 0 28px 0 12px !important;
        width: 140px !important;
        line-height: 1.5 !important;
    }

    .attendance-controls-card .btn-filter,
    .attendance-controls-card .btn-column-visibility {
        padding: 0 18px !important;
        justify-content: center !important;
    }

    .attendance-controls-card .page-size-selector {
        padding: 0 10px !important;
        justify-content: center !important;
        min-width: 64px !important;
    }

    .attendance-controls-card #attendance-limit {
        height: 100% !important;
        text-align: center !important;
        font-weight: 500 !important;
    }

    /* Light mode — chevron arrows (background:transparent / shorthand strips native arrow) */
    body:not(.dark-mode) .attendance-controls-card #quickStatus,
    body:not(.dark-mode) .attendance-report-page .attendance-controls-card #quickStatus {
        background-color: #ffffff !important;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%2364748b' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e") !important;
        background-repeat: no-repeat !important;
        background-position: right 10px center !important;
        background-size: 14px 10px !important;
        -webkit-appearance: none !important;
        appearance: none !important;
    }

    body:not(.dark-mode) .attendance-controls-card #attendance-limit,
    body:not(.dark-mode) .attendance-report-page .attendance-controls-card #attendance-limit {
        background-color: transparent !important;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%2364748b' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e") !important;
        background-repeat: no-repeat !important;
        background-position: right 2px center !important;
        background-size: 14px 10px !important;
        -webkit-appearance: none !important;
        appearance: none !important;
        padding-right: 16px !important;
    }

    body.dark-mode .attendance-controls-card .search-box .search-input,
    body.dark-mode .attendance-controls-card .btn-filter,
    body.dark-mode .attendance-controls-card .page-size-selector,
    body.dark-mode .attendance-controls-card .btn-column-visibility {
        background: rgba(255, 255, 255, 0.05) !important;
        color: #ffffff !important;
        border-color: rgba(255, 255, 255, 0.1) !important;
    }
    body.dark-mode .attendance-controls-card #quickStatus {
        background-color: rgba(255, 255, 255, 0.05) !important;
        color: #ffffff !important;
        border-color: rgba(255, 255, 255, 0.1) !important;
    }

    /* In dark mode, Column Visibility should NOT be blue (match Employees) */
    body.dark-mode .attendance-controls-card .btn-column-visibility {
        box-shadow: none !important;
    }

    body.dark-mode .attendance-controls-card #attendance-limit {
        color: #ffffff !important;
    }

    body.dark-mode .attendance-controls-card .search-box .search-input::placeholder {
        color: rgba(255, 255, 255, 0.6) !important;
    }

    @media (max-width: 768px) {
        body.dark-mode .attendance-controls-card .btn-filter,
        body.dark-mode .attendance-controls-card .page-size-selector {
            background: rgba(255, 255, 255, 0.05) !important;
            color: #ffffff !important;
            border-color: rgba(255, 255, 255, 0.1) !important;
        }
        body.dark-mode .attendance-controls-card #quickStatus {
            background-color: rgba(255, 255, 255, 0.05) !important;
            color: #ffffff !important;
            border-color: rgba(255, 255, 255, 0.1) !important;
        }

        body.dark-mode .attendance-controls-card .status-icon-select {
            background: rgba(255, 255, 255, 0.05) !important;
            border-color: rgba(255, 255, 255, 0.1) !important;
        }

        body.dark-mode .attendance-controls-card .status-icon-select i {
            color: #ffffff !important;
        }

        body.dark-mode .attendance-controls-card #attendance-limit {
            color: #ffffff !important;
        }

        body.dark-mode .attendance-controls-card #quickStatus option,
        body.dark-mode .attendance-controls-card #attendance-limit option {
            background: #111827 !important;
            color: #f8fafc !important;
        }
    }

    /* Stat Card Specific Colors (White Pill with Colored Border) - Per User Image */
    .summary-section .summary-card {
        background: #ffffff !important;
        border-radius: 50px !important;
        padding: 10px 25px !important;
        color: #333 !important;
        min-width: 180px !important;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05) !important;
        border: 1px solid #e0e0e0 !important;
        font-weight: 600 !important;
        cursor: pointer !important;
        transition: all 0.25s ease-in-out !important;
    }
    .summary-section .summary-card:hover {
        transform: translateY(-2px) !important;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1) !important;
    }

    /* Active Filter Styles - Light Mode */
    .summary-section .summary-card.stat-card-present.active-filter { background: #d1fae5 !important; border: 3px solid #10b981 !important; }
    .summary-section .summary-card.stat-card-absent.active-filter { background: #fee2e2 !important; border: 3px solid #ef4444 !important; }
    .summary-section .summary-card.stat-card-late.active-filter { background: #fef3c7 !important; border: 3px solid #f59e0b !important; }
    .summary-section .summary-card.stat-card-headcount.active-filter { background: #e0f2fe !important; border: 3px solid #0ea5e9 !important; }
    .summary-section .summary-card.stat-card-leave.active-filter { background: #f3e8ff !important; border: 3px solid #8b5cf6 !important; }

    .summary-section .summary-card.stat-card-headcount {
        border: 2px solid #0ea5e9 !important;
    }

    .summary-section .summary-card.stat-card-present {
        border: 2px solid #10b981 !important;
    }

    .summary-section .summary-card.stat-card-absent {
        border: 2px solid #ef4444 !important;
    }

    .summary-section .summary-card.stat-card-late {
        border: 2px solid #f59e0b !important;
    }

    .summary-section .summary-card.stat-card-leave {
        border: 2px solid #8b5cf6 !important;
    }

    /* Status Badges */
    .badge-present {
        background: #e8f5e9 !important;
        color: #2e7d32 !important;
    }

    .badge-late {
        background: #fffde7 !important;
        color: #f9a825 !important;
    }

    .badge-absent {
        background: #ffebee !important;
        color: #c62828 !important;
    }

    .badge-leave {
        background: #e0f2fe !important;
        color: #0369a1 !important;
    }

    /* Expanded Row Layout */
    .attendance-flex-container {
        display: flex;
        gap: 30px;
        align-items: flex-start;
        margin-top: 10px;
        flex-wrap: wrap;
    }

    .calendar-wrapper {
        flex: 1;
        min-width: 350px;
    }

    .summary-side-panel {
        width: 280px;
        padding: 15px;
        background: var(--table-hover);
        border-radius: 15px;
        border: 1px solid var(--table-border);
    }

    .summary-mini-card {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px;
        background: var(--table-bg);
        border-radius: 12px;
        margin-bottom: 10px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
        border: 1px solid var(--table-border);
        color: var(--text-primary);
    }

    .mini-card-icon {
        width: 35px;
        height: 35px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
    }

    .mini-card-info {
        display: flex;
        flex-direction: column;
    }

    .mini-card-label {
        font-size: 0.7rem;
        font-weight: 700;
        color: var(--text-muted);
        text-transform: uppercase;
    }

    .mini-card-value {
        font-size: 1.1rem;
        font-weight: 800;
        color: var(--text-primary);
    }

    .icon-p {
        background: #e8f5e9;
        color: #2e7d32;
    }

    .icon-l {
        background: #fffde7;
        color: #f9a825;
    }

    .icon-a {
        background: #ffebee;
        color: #c62828;
    }

    .icon-lv {
        background: #e0f2fe;
        color: #0369a1;
    }

    /* Dark mode specific tweaks */
    /* Top Summary Cards in Dark Mode (Monochrome with outlines) */
    body.dark-mode .summary-section .summary-card {
        background: rgba(0, 0, 0, 0.6) !important;
        color: rgba(255, 255, 255, 0.95) !important;
        border-color: rgba(255, 255, 255, 0.15) !important;
        box-shadow: none !important;
        transition: all 0.25s ease-in-out !important;
    }
    body.dark-mode .summary-section .summary-card:hover {
        background: rgba(20, 20, 20, 0.8) !important;
        transform: translateY(-2px) !important;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4) !important;
    }
    body.dark-mode .summary-section .summary-card.stat-card-headcount { border-color: rgba(14, 165, 233, 0.6) !important; }
    body.dark-mode .summary-section .summary-card.stat-card-present { border-color: rgba(16, 185, 129, 0.6) !important; }
    body.dark-mode .summary-section .summary-card.stat-card-absent { border-color: rgba(239, 68, 68, 0.6) !important; }
    body.dark-mode .summary-section .summary-card.stat-card-late { border-color: rgba(245, 158, 11, 0.6) !important; }
    body.dark-mode .summary-section .summary-card.stat-card-leave { border-color: rgba(139, 92, 246, 0.6) !important; }

    /* Active Filter Styles - Dark Mode */
    body.dark-mode .summary-section .summary-card.stat-card-present.active-filter { background: rgba(16, 185, 129, 0.15) !important; border: 3px solid #10b981 !important; }
    body.dark-mode .summary-section .summary-card.stat-card-absent.active-filter { background: rgba(239, 68, 68, 0.15) !important; border: 3px solid #ef4444 !important; }
    body.dark-mode .summary-section .summary-card.stat-card-late.active-filter { background: rgba(245, 158, 11, 0.15) !important; border: 3px solid #f59e0b !important; }
    body.dark-mode .summary-section .summary-card.stat-card-headcount.active-filter { background: rgba(14, 165, 233, 0.15) !important; border: 3px solid #0ea5e9 !important; }
    body.dark-mode .summary-section .summary-card.stat-card-leave.active-filter { background: rgba(139, 92, 246, 0.15) !important; border: 3px solid #8b5cf6 !important; }
    body.dark-mode .summary-section .summary-card span {
        color: rgba(255, 255, 255, 0.95) !important;
    }
    body.dark-mode .summary-arrow {
        background: rgba(255, 255, 255, 0.05) !important;
        border-color: rgba(255, 255, 255, 0.1) !important;
        color: #ffffff !important;
    }
    body.dark-mode .summary-arrow:hover {
        background: rgba(255, 255, 255, 0.1) !important;
        border-color: rgba(255, 255, 255, 0.2) !important;
    }

    /* Side Panel & Summary Cards */
    body.dark-mode .summary-side-panel {
        background: #121212 !important;
        border: 1px solid #2d2d2d !important;
        color: #ffffff !important;
    }
    body.dark-mode .summary-side-panel h6 {
        color: #ffffff !important;
    }
    body.dark-mode .summary-mini-card {
        background: #000000 !important;
        border: 1px solid #2d2d2d !important;
        color: #ffffff !important;
        box-shadow: none !important;
    }
    body.dark-mode .mini-card-label {
        color: #888888 !important;
    }
    body.dark-mode .mini-card-value {
        color: #ffffff !important;
    }

    /* Icon chips for summary panel */
    body.dark-mode .icon-p { background: rgba(16, 185, 129, 0.15) !important; color: #34d399 !important; }
    body.dark-mode .icon-l { background: rgba(245, 158, 11, 0.15) !important; color: #fbbf24 !important; }
    body.dark-mode .icon-a { background: rgba(239, 68, 68, 0.15) !important; color: #f87171 !important; }
    body.dark-mode .icon-lv { background: rgba(14, 165, 233, 0.15) !important; color: #38bdf8 !important; }

    /* Calendar Grid cells */
    body.dark-mode .calendar-day {
        background: #000000 !important;
        border-color: #2d2d2d !important;
        color: #ffffff !important;
    }
    body.dark-mode .calendar-day.empty {
        background: transparent !important;
        border: none !important;
    }
    body.dark-mode .calendar-header-day {
        color: #888888 !important;
    }
    body.dark-mode .calendar-month-title {
        color: #ffffff !important;
    }
    body.dark-mode .calendar-nav button {
        background: #1a1a1a !important;
        border: 1px solid #3d3d3d !important;
        color: #ffffff !important;
    }
    body.dark-mode .calendar-nav button:hover {
        background: #2d2d2d !important;
    }

    /* Calendar indicator dots */
    body.dark-mode .dot-present { background: #34d399 !important; }
    body.dark-mode .dot-late { background: #fbbf24 !important; }
    body.dark-mode .dot-absent { background: #f87171 !important; }
    body.dark-mode .dot-dayoff { background: #38bdf8 !important; }
    body.dark-mode .dot-company-holiday { background: #a78bfa !important; }

    /* Fixed Search Bar visibility in dark mode */
    body.dark-mode .search-input,
    body.dark-mode .form-select,
    body.dark-mode .form-control,
    body.dark-mode #tableSearchInput {
        background-color: rgba(255, 255, 255, 0.05) !important;
        color: #ffffff !important;
        border-color: rgba(255, 255, 255, 0.1) !important;
    }
    body.dark-mode .search-input::placeholder,
    body.dark-mode #tableSearchInput::placeholder {
        color: rgba(255, 255, 255, 0.5) !important;
    }

    /* Force status badge colors in dark mode */
    body.dark-mode .status-badge.badge-present {
        background: rgba(16, 185, 129, 0.15) !important;
        color: #34d399 !important;
        border: 1px solid rgba(16, 185, 129, 0.3) !important;
    }
    body.dark-mode .status-badge.badge-late {
        background: rgba(245, 158, 11, 0.15) !important;
        color: #fbbf24 !important;
        border: 1px solid rgba(245, 158, 11, 0.3) !important;
    }
    body.dark-mode .status-badge.badge-absent {
        background: rgba(239, 68, 68, 0.15) !important;
        color: #f87171 !important;
        border: 1px solid rgba(239, 68, 68, 0.3) !important;
    }
    body.dark-mode .status-badge.badge-leave {
        background: rgba(14, 165, 233, 0.15) !important;
        color: #38bdf8 !important;
        border: 1px solid rgba(14, 165, 233, 0.3) !important;
    }

    /* Pagination Arrow visibility in Dark Mode */
    body.dark-mode .page-btn {
        color: rgba(255, 255, 255, 0.8) !important;
        background: rgba(0, 0, 0, 0.6) !important;
        border: 1px solid rgba(255, 255, 255, 0.1) !important;
    }
    body.dark-mode .page-btn:hover:not(.disabled):not(.active) {
        background: rgba(255, 255, 255, 0.1) !important;
        color: #ffffff !important;
    }
    body.dark-mode .page-btn.disabled {
        color: rgba(255, 255, 255, 0.3) !important;
        background: rgba(0, 0, 0, 0.2) !important;
        border-color: rgba(255, 255, 255, 0.05) !important;
    }
    body.dark-mode .page-btn.active {
        background: #ffffff !important;
        color: #000000 !important;
        border-color: #ffffff !important;
        font-weight: bold !important;
    }

    /* Modal Input visibility in Dark Mode */
    body.dark-mode .users-filter-input {
        background-color: #000000 !important;
        color: #ffffff !important;
        border: 1px solid #2d2d2d !important;
    }

    body.dark-mode .usr-custom-dropdown-wrapper {
        background: #000000 !important;
        border: 1px solid #2d2d2d !important;
    }

    body.dark-mode .usr-custom-dropdown-wrapper:focus-within {
        border-color: #ffffff !important;
        box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.1) !important;
    }

    body.dark-mode .usr-custom-dropdown-input {
        background-color: transparent !important;
        color: #ffffff !important;
        border: none !important;
    }

    body.dark-mode .usr-custom-dropdown-list {
        background: #121212 !important;
        border: 1px solid #2d2d2d !important;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.5) !important;
    }

    body.dark-mode .usr-dd-item {
        color: #ffffff !important;
    }

    body.dark-mode .usr-dd-item:hover {
        background: #2d2d2d !important;
        color: #ffffff !important;
    }

    body.dark-mode .usr-dd-item.selected {
        background: #1a1a1a !important;
        color: #ffffff !important;
    }

    body.dark-mode .usr-dd-check {
        color: #ffffff !important;
    }

    body.dark-mode .usr-dd-empty {
        color: #888888 !important;
    }

    body.dark-mode .usr-chip {
        background: #2d2d2d !important;
        border: 1px solid #3d3d3d !important;
        color: #ffffff !important;
    }

    body.dark-mode .usr-chip-remove {
        color: #aaaaaa !important;
    }

    body.dark-mode .usr-chip-remove:hover {
        color: #ff5c5c !important;
    }

    body.dark-mode .users-filter-modal .users-filter-content {
        background: #121212 !important;
        border: 1px solid #2d2d2d !important;
        box-shadow: none !important;
    }

    body.dark-mode .users-filter-modal .users-filter-header {
        border-bottom: 1px solid #2d2d2d !important;
        background: transparent !important;
    }

    body.dark-mode .users-filter-modal .users-filter-footer {
        border-top: 1px solid #2d2d2d !important;
        background: transparent !important;
    }

    body.dark-mode .users-filter-modal .users-filter-close {
        color: #ffffff !important;
    }

    body.dark-mode .users-filter-modal .users-filter-item label {
        background: #121212 !important;
        color: #ffffff !important;
    }

    /* Summary Section & Scroll Wrapper */
    .summary-wrapper {
        position: relative;
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 10px;
        padding: 0 5px;
    }

    .summary-section {
        display: flex;
        gap: 15px;
        overflow-x: auto;
        scroll-behavior: smooth;
        -ms-overflow-style: none;
        scrollbar-width: none;
        padding: 6px 5px;
        flex-grow: 1;
        -webkit-overflow-scrolling: touch;
    }

    .summary-section::-webkit-scrollbar {
        display: none;
    }

    .summary-arrow {
        background: white;
        border: 1px solid #ddd;
        border-radius: 50%;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        z-index: 2;
        transition: all 0.3s ease;
        color: #666;
        font-size: 18px;
        flex-shrink: 0;
    }

    .summary-arrow:hover {
        background: #f8fafc;
        color: var(--primary-teal);
        border-color: var(--primary-teal);
    }

    .summary-arrow.left {
        left: 0;
    }

    .summary-arrow.right {
        right: 0;
    }

    /* --- Mobile-Specific Overrides --- */
    @media (max-width: 768px) {
        .content {
            padding: 10px !important;
        }

        .control-bar {
            flex-direction: column !important;
            align-items: stretch !important;
            gap: 0.75rem !important;
        }

        .control-left,
        .control-right {
            width: 100% !important;
        }

        /* Control Header on Mobile */
        .control-header-mobile {
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            margin-bottom: 0.5rem !important;
        }

        .control-header-mobile h3 {
            font-size: 1.3rem !important;
            margin: 0 !important;
            color: #1e293b !important;
        }

        .control-header-mobile .mobile-date {
            display: block !important;
            color: #64748b;
            font-size: 0.85rem;
        }

        .search-box {
            width: 100% !important;
            position: relative;
            margin-bottom: 0.75rem !important;
        }

        .search-input {
            width: 100% !important;
            height: 48px !important;
            border-radius: 12px !important;
            padding-left: 44px !important;
        }

        .search-icon {
            left: 14px !important;
            top: 50% !important;
            transform: translateY(-50%) !important;
        }

        /* Match Employees mobile: search full width + 1-row controls grid */
        .attendance-controls-card {
            padding: 0 !important;
            margin-bottom: 14px !important;
        }

        .attendance-controls-card #attendanceFilterForm {
            display: flex !important;
            flex-direction: column !important;
            gap: 12px !important;
            width: 100% !important;
        }

        .attendance-controls-card .filter-group-search {
            width: 100% !important;
        }

        .attendance-controls-card .filter-group-search .search-box {
            margin-bottom: 0 !important;
        }

        /* Make mobile search identical to Employees */
        .attendance-controls-card .filter-group-search .search-box {
            width: 100% !important;
        }

        .attendance-controls-card .filter-group-search .search-input {
            width: 100% !important;
            min-width: 100% !important;
            height: auto !important;
            padding-top: 12px !important;
            padding-bottom: 12px !important;
            padding-left: 44px !important;
            border-radius: 12px !important;
            font-size: 14px !important;
        }

        /* Ensure search icon sits inside input */
        .attendance-controls-card .filter-group-search .search-icon {
            position: absolute !important;
            left: 14px !important;
            top: 50% !important;
            transform: translateY(-50%) !important;
            pointer-events: none !important;
            z-index: 2 !important;
        }

        /* Hide page title on mobile — keep summary cards visible */
        .attendance-report-page .control-header-mobile {
            display: none !important;
        }

        .attendance-report-page .summary-wrapper,
        .attendance-report-page .summary-wrapper.pt-1,
        .attendance-report-page .summary-wrapper.mb-2 {
            padding-top: 0 !important;
            margin-bottom: 8px !important;
        }

        .attendance-report-page .summary-section {
            padding: 4px 5px !important;
        }

        .attendance-report-page .attendance-controls-card.glass-card,
        .attendance-report-page .attendance-controls-card {
            padding: 0 !important;
            margin-top: 0 !important;
            margin-bottom: 8px !important;
            background: transparent !important;
            border: none !important;
            box-shadow: none !important;
        }

        .attendance-report-page .attendance-controls-card .control-bar {
            margin-top: 0 !important;
            margin-bottom: 0 !important;
        }

        .attendance-report-page .attendance-controls-card #attendanceFilterForm {
            margin-bottom: 0 !important;
        }

        .attendance-report-page .user-table-container {
            margin-top: 0 !important;
            padding-top: 0 !important;
        }

        /* Leads-style mobile toolbar: search + rows limit on top; actions in bottom nav */
        .attendance-controls-card #attendanceFilterForm {
            display: grid !important;
            grid-template-columns: 1fr auto !important;
            grid-template-areas: "search limit" !important;
            gap: 10px !important;
            align-items: stretch !important;
        }

        .attendance-controls-card .filter-group-search {
            grid-area: search !important;
            width: auto !important;
            min-width: 0 !important;
        }

        .attendance-controls-card .filter-group-actions {
            display: contents !important;
        }

        .attendance-controls-card .filter-group-actions .page-size-selector {
            grid-area: limit !important;
            display: flex !important;
            width: 72px !important;
            min-width: 72px !important;
            max-width: 72px !important;
            height: 48px !important;
            border-radius: 12px !important;
            background: #ffffff !important;
            border: 1px solid #e2e8f0 !important;
            box-shadow: none !important;
            padding: 0 8px !important;
            align-items: center !important;
            justify-content: center !important;
        }

        body:not(.dark-mode) .attendance-controls-card .filter-group-actions .page-size-selector #attendance-limit {
            width: 100% !important;
            height: 100% !important;
            background-color: transparent !important;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%2364748b' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e") !important;
            background-repeat: no-repeat !important;
            background-position: right 0 center !important;
            background-size: 12px 9px !important;
            border: 0 !important;
            text-align: center !important;
            font-weight: 800 !important;
            font-size: 14px !important;
            padding: 0 14px 0 0 !important;
            -webkit-appearance: none !important;
            appearance: none !important;
        }

        /* Hide toolbar controls from layout — bottom nav triggers them */
        .attendance-controls-card .filter-group-actions .status-icon-select,
        .attendance-controls-card .filter-group-actions .btn-filter,
        .attendance-controls-card .filter-group-actions .column-visibility-wrapper {
            display: none !important;
        }

        /* Fixed bottom nav — matches userlogin leads mobile pattern */
        .att-mobile-bottom-nav {
            display: flex;
            justify-content: space-evenly;
            align-items: stretch;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: #fff;
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            gap: 4px;
            border-top: 1px solid #e5e7eb;
            padding: 6px 0 calc(6px + env(safe-area-inset-bottom, 0px));
        }

        .att-mobile-nav-btn {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: transparent;
            border: none;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            gap: 3px;
            color: #64748b;
            padding: 4px 2px;
            border-radius: 16px;
            transition: color 0.2s ease, background 0.2s ease;
        }

        .att-mobile-nav-btn i {
            font-size: 18px;
            line-height: 1;
        }

        .att-mobile-nav-btn.att-mobile-filter-btn {
            color: #ffa600;
        }

        .att-mobile-nav-btn.att-mobile-status-btn {
            color: #2563eb;
        }

        .att-mobile-nav-btn.att-mobile-columns-btn {
            color: #03ac47;
        }

        .att-mobile-nav-btn.active {
            background: rgba(15, 23, 42, 0.06);
        }

        .attendance-report-page .container-fluid {
            padding-bottom: calc(72px + env(safe-area-inset-bottom, 0px)) !important;
        }

        .attendance-report-page .pagination-section {
            padding-bottom: 0.5rem !important;
        }

        .attendance-report-page .floating-clear-btn {
            bottom: calc(78px + env(safe-area-inset-bottom, 0px)) !important;
        }

        .attendance-report-page .column-dropdown.show,
        .attendance-report-page .column-dropdown.att-mobile-column-dropdown.show,
        #columnDropdown.att-mobile-column-dropdown,
        #columnDropdown.att-mobile-column-dropdown.show {
            position: fixed !important;
            left: auto !important;
            right: 12px !important;
            top: auto !important;
            bottom: calc(72px + env(safe-area-inset-bottom, 0px)) !important;
            transform: none !important;
            width: min(280px, calc(100vw - 32px)) !important;
            max-height: 50vh;
            overflow-y: auto;
            scrollbar-width: none;
            z-index: 1001 !important;
            display: none;
            margin: 0 !important;
        }

        #columnDropdown.att-mobile-column-dropdown::-webkit-scrollbar {
            width: 0 !important;
            height: 0 !important;
            display: none !important;
        }

        #columnDropdown.att-mobile-column-dropdown.show {
            display: block !important;
        }

        /* Filter modal — 2 even rows: Close+Apply, then Export+Clear */
        #filterModal.custom-show .users-filter-footer {
            display: grid !important;
            grid-template-columns: 1fr 1fr;
            gap: 8px !important;
            padding: 12px 14px calc(12px + env(safe-area-inset-bottom, 0px)) !important;
            border-top: 1px solid #eef2f7 !important;
        }

        #filterModal.custom-show .users-filter-footer > a.btn-outline-success,
        #filterModal.custom-show .users-filter-footer .btn-users-close,
        #filterModal.custom-show .users-filter-footer .btn-users-clear,
        #filterModal.custom-show .users-filter-footer .btn-users-apply {
            margin: 0 !important;
            min-height: 40px;
            padding: 8px 10px !important;
            font-size: 0.78rem !important;
            font-weight: 600 !important;
            border-radius: 10px !important;
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
            width: 100%;
        }

        #filterModal.custom-show .users-filter-footer > a.btn-outline-success {
            order: 1;
        }

        #filterModal.custom-show .users-filter-footer .btn-users-apply {
            order: 2;
        }

        #filterModal.custom-show .users-filter-footer .btn-users-close {
            order: 3;
        }

        #filterModal.custom-show .users-filter-footer .btn-users-clear {
            order: 4;
        }

        body.dark-mode #filterModal.custom-show .users-filter-footer {
            border-top-color: rgba(255, 255, 255, 0.08) !important;
        }

        /* Mobile status picker sheet */
        .att-mobile-status-sheet {
            position: fixed;
            inset: 0;
            z-index: 1002;
            display: none;
            align-items: flex-end;
            justify-content: center;
        }

        .att-mobile-status-sheet.active {
            display: flex;
        }

        .att-mobile-status-backdrop {
            position: absolute;
            inset: 0;
            background: rgba(15, 23, 42, 0.45);
        }

        .att-mobile-status-panel {
            position: relative;
            width: 100%;
            max-width: 520px;
            background: #fff;
            border-radius: 20px 20px 0 0;
            padding: 16px 16px calc(16px + env(safe-area-inset-bottom, 0px));
            box-shadow: 0 -8px 30px rgba(0, 0, 0, 0.12);
        }

        .att-mobile-status-panel h6 {
            margin: 0 0 12px;
            font-size: 0.95rem;
            font-weight: 800;
            color: #0f172a;
        }

        .att-mobile-status-options {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .att-mobile-status-option {
            width: 100%;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            border-radius: 12px;
            padding: 12px 14px;
            text-align: left;
            font-size: 0.92rem;
            font-weight: 600;
            color: #334155;
            cursor: pointer;
        }

        .att-mobile-status-option.selected {
            border-color: #2563eb;
            background: rgba(37, 99, 235, 0.08);
            color: #1d4ed8;
        }

        body.dark-mode .att-mobile-bottom-nav {
            background: rgba(22, 22, 24, 0.92);
            border-top-color: rgba(255, 255, 255, 0.08);
            box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.4);
        }

        body.dark-mode .att-mobile-nav-btn {
            color: rgba(255, 255, 255, 0.65);
        }

        body.dark-mode .att-mobile-nav-btn.att-mobile-filter-btn {
            color: #ffb347;
        }

        body.dark-mode .att-mobile-nav-btn.att-mobile-status-btn {
            color: #60a5fa;
        }

        body.dark-mode .att-mobile-nav-btn.att-mobile-columns-btn {
            color: #34d399;
        }

        body.dark-mode .att-mobile-status-panel {
            background: #1e1e24;
        }

        body.dark-mode .att-mobile-status-panel h6 {
            color: #f8fafc;
        }

        body.dark-mode .att-mobile-status-option {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(255, 255, 255, 0.1);
            color: #e2e8f0;
        }

        body.dark-mode .att-mobile-status-option.selected {
            border-color: #60a5fa;
            background: rgba(96, 165, 250, 0.12);
            color: #93c5fd;
        }

        body.dark-mode .attendance-report-page .attendance-controls-card .filter-group-actions .page-size-selector {
            background: rgba(255, 255, 255, 0.05) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            box-shadow: none !important;
        }

        body.dark-mode .attendance-report-page .attendance-controls-card .filter-group-actions .page-size-selector #attendance-limit {
            color: #ffffff !important;
            background-color: transparent !important;
            background-image: none !important;
            border: 0 !important;
            backdrop-filter: none !important;
            -webkit-backdrop-filter: none !important;
            -webkit-appearance: menulist !important;
            appearance: menulist !important;
            font-weight: 500 !important;
        }

        /* Mobile: hide desktop table — card list shown instead */
        .attendance-report-page .user-table-scroll-wrapper {
            display: none !important;
        }

        /* Sleek mobile attendance cards */
        .mobile-attendance-container {
            padding: 0;
            margin-top: 0;
        }

        .mobile-attendance-list-header {
            display: none !important;
        }

        .mobile-attendance-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .mobile-attendance-card {
            background: #ffffff;
            border-radius: 12px;
            border: 1px solid #eef2f7;
            box-shadow: 0 1px 6px rgba(15, 23, 42, 0.05);
            overflow: hidden;
            transition: box-shadow 0.2s ease;
        }

        .mobile-attendance-card.expanded {
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.08);
        }

        .att-card-main {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            min-height: 52px;
        }

        .att-card-info {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .att-card-meta-line {
            display: flex;
            align-items: center;
            gap: 6px;
            min-width: 0;
        }

        .att-card-id {
            font-size: 0.72rem;
            font-weight: 700;
            color: #94a3b8;
            flex-shrink: 0;
        }

        .att-card-date-dot {
            color: #cbd5e1;
            font-size: 0.7rem;
            flex-shrink: 0;
        }

        .att-card-date {
            font-size: 0.72rem;
            font-weight: 600;
            color: #64748b;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .att-card-name-line {
            display: flex;
            align-items: center;
            flex-wrap: nowrap;
            gap: 8px;
            min-width: 0;
            flex: 1;
        }

        .att-card-name {
            font-size: 0.92rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.2;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .mobile-attendance-card .status-badge {
            padding: 3px 8px !important;
            border-radius: 999px !important;
            font-size: 0.62rem !important;
            font-weight: 700 !important;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .att-card-expand-btn {
            width: 32px !important;
            height: 32px !important;
            min-width: 32px !important;
            min-height: 32px !important;
            border-radius: 50% !important;
            background: #1e1e2d !important;
            color: #fff !important;
            border: none !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            padding: 0 !important;
            flex-shrink: 0;
        }

        .att-card-expand-btn i {
            font-size: 0.85rem;
            transition: transform 0.2s ease;
        }

        .att-card-expand-btn.active {
            background: var(--primary-teal) !important;
        }

        .att-card-detail-panel {
            display: none;
            border-top: 1px solid #f1f5f9;
            padding: 10px 12px 12px;
            background: #f8fafc;
        }

        .att-card-detail-panel .att-mobile-detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px 12px;
            margin-bottom: 10px;
        }

        .att-card-detail-panel .att-mobile-detail-item {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .att-card-detail-panel .att-mobile-detail-item[style*="display: none"] {
            display: none !important;
        }

        .att-card-detail-panel .att-mobile-detail-label {
            font-size: 0.65rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: #94a3b8;
        }

        .att-card-detail-panel .att-mobile-detail-value {
            font-size: 0.82rem;
            font-weight: 600;
            color: #334155;
            word-break: break-word;
        }

        .att-card-details-btn {
            width: 100%;
            border: none;
            border-radius: 10px;
            padding: 10px 14px;
            background: var(--primary-teal, #227477);
            color: #fff;
            font-size: 0.85rem;
            font-weight: 700;
            cursor: pointer;
        }

        body.dark-mode .mobile-attendance-card {
            background: rgba(18, 18, 18, 0.85);
            border-color: rgba(255, 255, 255, 0.1);
        }

        .att-card-detail-panel .att-mobile-detail-item[data-col="8"] {
            display: none !important;
        }

        body.dark-mode .att-card-name {
            color: #f8fafc;
        }

        body.dark-mode .att-card-date {
            color: #94a3b8;
        }

        body.dark-mode .att-card-detail-panel {
            background: rgba(255, 255, 255, 0.03);
            border-top-color: rgba(255, 255, 255, 0.08);
        }

        body.dark-mode .att-card-detail-panel .att-mobile-detail-value {
            color: #e2e8f0;
        }

        body.dark-mode .att-card-expand-btn {
            background: #2a2a2a !important;
        }

        body.dark-mode .att-card-expand-btn.active {
            background: var(--primary-teal) !important;
        }

        /* Expand content opens in overlay — keep row hidden in list */
        .attendance-report-page .user-expand-row {
            display: none !important;
        }

        .user-expand-row td {
            padding: 0 !important;
            border: none !important;
        }

        .attendance-flex-container {
            flex-direction: column !important;
            gap: 15px !important;
        }

        .calendar-wrapper,
        .summary-side-panel {
            width: 100% !important;
            min-width: 0 !important;
        }

        .user-expand-content {
            background: var(--table-bg) !important;
            border: 1px solid var(--table-border) !important;
            border-radius: 16px !important;
            padding: 20px 10px !important;
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.05) !important;
            margin: 0 0 15px 0 !important;
        }

        .summary-side-panel {
            padding: 10px 5px !important;
            background: transparent !important;
            border: none !important;
            box-shadow: none !important;
        }
        
        /* Monthly summary cards - cleaner premium mobile UI */
        .summary-mini-card {
            border-radius: 16px !important;
            padding: 14px 16px !important;
            background: rgba(255, 255, 255, 0.92) !important;
            border: 1px solid rgba(226, 232, 240, 0.9) !important;
            margin-bottom: 12px !important;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06) !important;
            gap: 12px !important;
        }

        .mini-card-icon {
            width: 40px !important;
            height: 40px !important;
            border-radius: 999px !important;
            font-size: 1.2rem !important;
            background: transparent !important;
            border: none !important;
            box-shadow: none !important;
        }

        .mini-card-label {
            font-size: 0.72rem !important;
            letter-spacing: 0.06em !important;
        }

        .mini-card-value {
            font-size: 1.25rem !important;
            font-weight: 900 !important;
        }

        /* Make icon chips subtle (no extra white tile) */
        .icon-p { background: rgba(16, 185, 129, 0.12) !important; color: #10b981 !important; }
        .icon-l { background: rgba(245, 158, 11, 0.14) !important; color: #f59e0b !important; }
        .icon-a { background: rgba(239, 68, 68, 0.12) !important; color: #ef4444 !important; }
        .icon-lv { background: rgba(56, 189, 248, 0.14) !important; color: #0284c7 !important; }

        .mini-card-info {
            gap: 2px !important;
        }

        body.dark-mode .summary-mini-card {
            background: #000000 !important;
            border-color: #2d2d2d !important;
            box-shadow: none !important;
        }

        body.dark-mode .icon-p { background: rgba(16, 185, 129, 0.15) !important; }
        body.dark-mode .icon-l { background: rgba(245, 158, 11, 0.15) !important; }
        body.dark-mode .icon-a { background: rgba(239, 68, 68, 0.15) !important; }
        body.dark-mode .icon-lv { background: rgba(14, 165, 233, 0.15) !important; }

        .attendance-calendar-grid {
            gap: 4px !important;
            padding: 5px !important;
            max-width: 100% !important;
        }

        .calendar-day {
            font-size: 0.85rem !important;
            border-radius: 8px !important;
        }

        .calendar-header-day {
            font-size: 0.7rem !important;
            padding-bottom: 4px !important;
        }

        .calendar-nav {
            margin: 0 5px 15px 5px !important;
            max-width: 100% !important;
        }

        /* Mobile details overlay card (calendar + summary) */
        .attendance-details-overlay {
            position: fixed;
            inset: 0;
            z-index: 3000;
            display: none;
            align-items: flex-end;
            justify-content: center;
        }

        .attendance-details-overlay.active {
            display: flex;
        }

        .attendance-details-backdrop {
            position: absolute;
            inset: 0;
            background: rgba(15, 23, 42, 0.45);
            backdrop-filter: blur(2px);
        }

        .attendance-details-card {
            position: relative;
            width: 100%;
            max-height: 92vh;
            background: var(--table-bg);
            border-radius: 20px 20px 0 0;
            box-shadow: 0 -8px 30px rgba(15, 23, 42, 0.15);
            display: flex;
            flex-direction: column;
            animation: attDetailsSlideUp 0.28s ease-out;
        }

        @keyframes attDetailsSlideUp {
            from { transform: translateY(100%); opacity: 0.6; }
            to { transform: translateY(0); opacity: 1; }
        }

        .attendance-details-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 16px 18px;
            border-bottom: 1px solid var(--table-border);
            flex-shrink: 0;
        }

        .attendance-details-card-header h5 {
            margin: 0;
            font-size: 1.05rem;
            font-weight: 800;
            color: var(--primary-teal);
        }

        .att-details-subtitle {
            display: block;
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--text-muted);
            margin-bottom: 2px;
        }

        .attendance-details-close {
            border: none;
            background: rgba(34, 116, 119, 0.1);
            color: var(--primary-teal);
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .attendance-details-card-body {
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
            padding: 12px 14px 20px;
        }

        .attendance-details-card-body .user-expand-content {
            margin: 0 !important;
            padding: 0 !important;
            border: none !important;
            box-shadow: none !important;
            background: transparent !important;
        }

        /* Calendar card: hide info grid — calendar + summary only */
        .attendance-details-card-body .att-mobile-detail-grid {
            display: none !important;
        }

        body.dark-mode .attendance-details-card {
            background: #121212 !important;
            border-top: 1px solid #2d2d2d !important;
        }

        body.dark-mode .attendance-details-card-header {
            border-bottom-color: #2d2d2d !important;
        }

        body.dark-mode .attendance-details-close {
            background: rgba(42, 140, 144, 0.15) !important;
            color: #2a8c90 !important;
        }

        .att-mobile-detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px 14px;
            padding: 0 4px 16px;
            margin-bottom: 12px;
            border-bottom: 1px solid var(--table-border);
        }

        .att-mobile-detail-item {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .att-mobile-detail-label {
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--text-muted);
        }

        .att-mobile-detail-value {
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--text-primary);
            word-break: break-word;
        }
    }

    @media (min-width: 769px) {
        .attendance-details-overlay {
            display: none !important;
        }

        .att-mobile-detail-grid {
            display: none !important;
        }

        .att-details-btn-text {
            display: none !important;
        }

        .att-mobile-bottom-nav,
        .att-mobile-status-sheet {
            display: none !important;
        }

        /* Tighter vertical spacing — match Leave Management */
        .attendance-report-page .summary-wrapper,
        .attendance-report-page .summary-wrapper.pt-1,
        .attendance-report-page .summary-wrapper.mb-2 {
            padding-top: 0 !important;
            margin-bottom: 8px !important;
        }

        .attendance-report-page .summary-section {
            padding: 4px 5px !important;
        }

        .attendance-report-page .attendance-controls-card.glass-card,
        .attendance-report-page .attendance-controls-card {
            padding: 0 !important;
            margin-top: 0 !important;
            margin-bottom: 8px !important;
            background: transparent !important;
            border: none !important;
            box-shadow: none !important;
        }

        .attendance-report-page .attendance-controls-card .control-bar {
            margin-top: 0 !important;
            margin-bottom: 0 !important;
        }

        .attendance-report-page .attendance-controls-card #attendanceFilterForm {
            margin-bottom: 0 !important;
        }

        .attendance-report-page .user-table-container {
            margin-top: 0 !important;
            padding-top: 0 !important;
        }

        .attendance-report-page .user-data-table {
            border-spacing: 0 10px !important;
        }
    }
    /* Dark mode — attendance toolbar matches Leave Management (desktop + mobile) */
    body.dark-mode .attendance-report-page .attendance-controls-card .search-box .search-input,
    body.dark-mode .attendance-report-page .attendance-controls-card .btn-filter,
    body.dark-mode .attendance-report-page .attendance-controls-card #quickStatus,
    body.dark-mode .attendance-report-page .attendance-controls-card .btn-column-visibility {
        background-color: rgba(255, 255, 255, 0.05) !important;
        color: #ffffff !important;
        border: 1px solid rgba(255, 255, 255, 0.1) !important;
        box-shadow: none !important;
    }

    body.dark-mode .attendance-report-page .attendance-controls-card #quickStatus {
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23ffffff' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e") !important;
        background-repeat: no-repeat !important;
        background-position: right 10px center !important;
        background-size: 14px 10px !important;
        padding-right: 28px !important;
        -webkit-appearance: none !important;
        appearance: none !important;
        color-scheme: dark !important;
    }

    body.dark-mode .attendance-report-page .attendance-controls-card .page-size-selector::before,
    body.dark-mode .attendance-report-page .attendance-controls-card .page-size-selector::after {
        content: none !important;
        display: none !important;
    }

    body.dark-mode .attendance-report-page .attendance-controls-card .page-size-selector {
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        min-width: 64px !important;
        max-width: 76px !important;
        padding: 0 10px !important;
        background: rgba(255, 255, 255, 0.05) !important;
        border: 1px solid rgba(255, 255, 255, 0.1) !important;
        box-sizing: border-box !important;
        box-shadow: none !important;
    }

    body.dark-mode .attendance-report-page .attendance-controls-card #attendance-limit {
        width: 100% !important;
        height: 100% !important;
        color: #ffffff !important;
        background-color: transparent !important;
        background-image: none !important;
        border: 0 !important;
        border-color: transparent !important;
        box-shadow: none !important;
        outline: none !important;
        backdrop-filter: none !important;
        -webkit-backdrop-filter: none !important;
        -webkit-appearance: menulist !important;
        appearance: menulist !important;
        font-size: 14px !important;
        font-weight: 500 !important;
        text-align: center !important;
        cursor: pointer !important;
    }

    body.dark-mode .attendance-report-page .attendance-controls-card .page-size-selector:focus-within {
        border-color: #2a8c90 !important;
        box-shadow: 0 0 0 2px rgba(42, 140, 144, 0.2) !important;
    }

    body.dark-mode .attendance-report-page .attendance-controls-card #quickStatus option,
    body.dark-mode .attendance-report-page .attendance-controls-card #attendance-limit option {
        background-color: #1e1e24 !important;
        color: #ffffff !important;
    }

    body.dark-mode .attendance-report-page .attendance-controls-card .search-box .search-input::placeholder {
        color: rgba(255, 255, 255, 0.55) !important;
        opacity: 1 !important;
    }
</style>
<?php include('header.php'); ?>
<div class="content attendance-report-page">
    <div class="container-fluid">

        <!-- Dashboard Metrics Ribbon (Scrollable Wrapper) -->
        <div class="summary-wrapper pt-1 mb-2">
            <button class="summary-arrow left" id="summaryLeft">‹</button>
            <div class="summary-section" id="summaryScroll">
                <div class="summary-card stat-card-headcount">
                    <span class="summary-text" style="font-weight: 600;">Total Headcount :
                        <?php echo $total_headcount; ?></span>
                </div>
                <div class="summary-card stat-card-present">
                    <span class="summary-text" style="font-weight: 600;">Present : <?php echo $present_count; ?>
                        (<?php echo $present_percent; ?>%)</span>
                </div>
                <div class="summary-card stat-card-absent">
                    <span class="summary-text" style="font-weight: 600;">Absents : <?php echo $absent_count; ?></span>
                </div>
                <div class="summary-card stat-card-late">
                    <span class="summary-text" style="font-weight: 600;">Late Arrivals :
                        <?php echo $late_count; ?></span>
                </div>
                <div class="summary-card stat-card-leave">
                    <span class="summary-text" style="font-weight: 600;">On Leave :
                        <?php echo $leave_count; ?></span>
                </div>
            </div>
            <button class="summary-arrow right" id="summaryRight">›</button>
        </div>
        <!-- Filter & Control Card -->
        <div class="glass-card p-4 mb-4 attendance-controls-card">
            <div class="control-bar d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div class="control-left">
                    <div class="control-header-mobile">
                        <h3 class="mb-0" style="color: var(--primary-teal); font-weight: 700; font-size: 1.4rem;">
                            Attendance Log</h3>
                        <div class="mobile-date d-md-none"><?php echo date('M d, Y'); ?></div>
                        <p class="text-muted mb-0 small d-none d-md-block"><?php echo $view_mode; ?></p>
                    </div>
                </div>

                <div class="control-right d-flex align-items-center justify-content-end gap-2 flex-grow-1 header-tools-wrapper">
                    <form action="attendance_report.php" id="attendanceFilterForm" method="GET">
                        <input type="hidden" name="from" id="finalFrom" value="<?php echo $from_date; ?>">
                        <input type="hidden" name="to" id="finalTo" value="<?php echo $to_date; ?>">

                        <div class="filter-group filter-group-search">
                            <div class="search-box">
                                <svg class="search-icon" width="18" height="18" viewBox="0 0 18 18" fill="none"
                                    style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); pointer-events: none; z-index: 1;">
                                    <circle cx="8" cy="8" r="6" stroke="#999" stroke-width="1.5" />
                                    <path d="M12.5 12.5L16 16" stroke="#999" stroke-width="1.5" stroke-linecap="round" />
                                </svg>
                                <input type="text" name="search" id="tableSearchInput" class="search-input"
                                    placeholder="Search attendance..."
                                    value="<?php echo htmlspecialchars($search_query); ?>">
                            </div>
                        </div>

                        <div class="filter-group filter-group-actions">
                            <div class="status-icon-select">
                                <select name="status" id="quickStatus" class="form-select"
                                    onchange="this.form.submit()" aria-label="Status">
                                    <option value="">All Status</option>
                                <option value="Present" <?php echo $status_filter == 'Present' ? 'selected' : ''; ?>>Present
                                </option>
                                <option value="Late" <?php echo $status_filter == 'Late' ? 'selected' : ''; ?>>Late</option>
                                <option value="Absent" <?php echo $status_filter == 'Absent' ? 'selected' : ''; ?>>Absent
                                </option>
                                <option value="On Leave" <?php echo $status_filter == 'On Leave' ? 'selected' : ''; ?>>On
                                    Leave</option>
                                </select>
                                <i class="bi bi-funnel"></i>
                            </div>

                            <button type="button" class="btn btn-outline-secondary px-3 btn-filter" id="openFilterBtn">
                                <i class="bi bi-filter"></i> <span class="btn-text">Filters</span>
                            </button>

                            <div class="column-visibility-wrapper">
                                <button type="button" class="btn-column-visibility" id="columnVisibilityBtn">
                                    <i class="bi bi-layout-three-columns"></i> <span class="btn-text">Column Visibility</span>
                                </button>
                                <div class="column-dropdown" id="columnDropdown">
                                    <label><input type="checkbox" data-col="1" checked> ID</label>
                                    <label><input type="checkbox" data-col="2" checked> EMP ID</label>
                                    <label><input type="checkbox" data-col="3" checked> UNIQUE ID</label>
                                    <label><input type="checkbox" data-col="4" checked> NAME</label>
                                    <label><input type="checkbox" data-col="5" checked> EMAIL</label>
                                    <label><input type="checkbox" data-col="6" checked> CONTACT</label>
                                    <label><input type="checkbox" data-col="7" checked> ROLE</label>
                                    <label><input type="checkbox" data-col="8" checked> DATE</label>
                                    <label><input type="checkbox" data-col="9" checked> PUNCH IN</label>
                                    <label><input type="checkbox" data-col="10" checked> PUNCH OUT</label>
                                    <label><input type="checkbox" data-col="11" checked> WORK HRS</label>
                                    <label><input type="checkbox" data-col="12" checked> STATUS</label>
                                    <label><input type="checkbox" data-col="13" checked> DETAILS</label>
                                </div>
                            </div>

                            <div class="page-size-selector">
                                <select id="attendance-limit" name="limit" class="form-select"
                                    onchange="this.form.submit()">
                                    <?php foreach ($allowedLimits as $v): ?>
                                        <option value="<?php echo $v; ?>" <?php echo $recordsPerPage === $v ? 'selected' : ''; ?>>
                                            <?php echo $v; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <!-- Table Section (desktop) -->
        <div class="user-table-container" style="background: transparent !important; border: none !important;">
            <div class="user-table-scroll-wrapper d-none d-md-block">
                <table class="user-data-table unified-table">
                    <thead>
                        <tr>
                            <th class="ps-4">ID</th>
                            <th>EMP ID</th>
                            <th>UNIQUE ID</th>
                            <th>NAME</th>
                            <th>EMAIL</th>
                            <th>CONTACT</th>
                            <th>ROLE</th>
                            <th>DATE</th>
                            <th>PUNCH IN</th>
                            <th>PUNCH OUT</th>
                            <th>WORK HRS</th>
                            <th>STATUS</th>
                            <th class="pe-4 text-center att-mobile-action-col"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($attendance_rows) > 0): ?>
                            <?php foreach ($attendance_rows as $row): ?>
                                <?php
                                $work_hours = ($row['total_hours'] > 0) ? round($row['total_hours'], 1) . ' hrs' : '-';
                                $status_class = att_status_badge_class($row['status']);
                                ?>
                                <tr class="user-data-row">
                                    <td class="text-muted" data-label="ID"><span class="att-cell-value"><?php echo $row['user_id']; ?></span></td>
                                    <td class="fw-bold" style="color: #227477;" data-label="EMP ID">
                                        <span class="att-cell-value"><?php echo htmlspecialchars($row['employee_id'] ?? ''); ?></span></td>
                                    <td class="text-muted small" data-label="UNIQUE ID">
                                        <span class="att-cell-value"><?php echo htmlspecialchars($row['uniqueid'] ?? ''); ?></span></td>
                                    <td class="fw-bold" data-label="NAME"><?php echo htmlspecialchars($row['username']); ?></td>
                                    <td class="small" data-label="EMAIL">
                                        <span class="att-cell-value"><?php echo htmlspecialchars($row['useremail'] ?? ''); ?></span></td>
                                    <td class="small" data-label="CONTACT">
                                        <span class="att-cell-value"><?php echo htmlspecialchars($row['contact'] ?? ''); ?></span></td>
                                    <td class="small text-muted" data-label="ROLE">
                                        <span class="att-cell-value"><?php echo htmlspecialchars($row['role'] ?? ''); ?></span></td>
                                    <td style="white-space: nowrap;" data-label="DATE">
                                        <span class="att-cell-value"><?php echo date('d-m-Y', strtotime($row['punch_date'])); ?></span></td>
                                    <td data-label="PUNCH IN">
                                        <span class="att-cell-value">
                                        <?php echo $row['punch_in'] ? date('h:i A', strtotime($row['punch_in'])) : '-'; ?>
                                        <?php if ($row['latitude_in'] && $row['longitude_in']): ?>
                                            <a href="https://www.google.com/maps?q=<?php echo $row['latitude_in']; ?>,<?php echo $row['longitude_in']; ?>"
                                                target="_blank" title="View Location" style="color: #227477; margin-left: 5px;">
                                                <i class="bi bi-geo-alt-fill"></i>
                                            </a>
                                        <?php endif; ?>
                                        </span>
                                    </td>
                                    <td data-label="PUNCH OUT">
                                        <span class="att-cell-value">
                                        <?php echo $row['punch_out'] ? date('h:i A', strtotime($row['punch_out'])) : '-'; ?>
                                        <?php if ($row['latitude_out'] && $row['longitude_out']): ?>
                                            <a href="https://www.google.com/maps?q=<?php echo $row['latitude_out']; ?>,<?php echo $row['longitude_out']; ?>"
                                                target="_blank" title="View Location" style="color: #ef4444; margin-left: 5px;">
                                                <i class="bi bi-geo-alt-fill"></i>
                                            </a>
                                        <?php endif; ?>
                                        </span>
                                    </td>
                                    <td class="fw-bold text-dark" data-label="WORK HRS"><span class="att-cell-value"><?php echo $work_hours; ?></span></td>
                                    <td data-label="STATUS">
                                        <span class="att-cell-value">
                                        <span class="status-badge <?php echo $status_class; ?>"
                                            style="padding: 6px 14px; border-radius: 10px; font-weight: 600;">
                                            <?php echo $row['status']; ?>
                                        </span>
                                        </span>
                                    </td>
                                    <td class="user-action-cell">
                                        <button type="button" class="user-expand-btn" data-userid="<?php echo $row['user_id']; ?>"
                                            onclick="toggleAttendanceRow(this)" title="View details">
                                            <i class="bi bi-chevron-right"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr class="user-expand-row" id="expand-<?php echo $row['user_id']; ?>" style="display:none;">
                                    <td colspan="13">
                                        <div class="user-expand-content">
                                            <div class="att-mobile-detail-grid">
                                                <div class="att-mobile-detail-item">
                                                    <span class="att-mobile-detail-label">Emp ID</span>
                                                    <span class="att-mobile-detail-value"><?php echo htmlspecialchars($row['employee_id'] ?? '—'); ?></span>
                                                </div>
                                                <div class="att-mobile-detail-item">
                                                    <span class="att-mobile-detail-label">Unique ID</span>
                                                    <span class="att-mobile-detail-value"><?php echo htmlspecialchars($row['uniqueid'] ?? '—'); ?></span>
                                                </div>
                                                <div class="att-mobile-detail-item">
                                                    <span class="att-mobile-detail-label">Date</span>
                                                    <span class="att-mobile-detail-value"><?php echo date('d-m-Y', strtotime($row['punch_date'])); ?></span>
                                                </div>
                                                <div class="att-mobile-detail-item">
                                                    <span class="att-mobile-detail-label">Work Hrs</span>
                                                    <span class="att-mobile-detail-value"><?php echo $work_hours; ?></span>
                                                </div>
                                                <div class="att-mobile-detail-item">
                                                    <span class="att-mobile-detail-label">Punch In</span>
                                                    <span class="att-mobile-detail-value"><?php echo $row['punch_in'] ? date('h:i A', strtotime($row['punch_in'])) : '-'; ?></span>
                                                </div>
                                                <div class="att-mobile-detail-item">
                                                    <span class="att-mobile-detail-label">Punch Out</span>
                                                    <span class="att-mobile-detail-value"><?php echo $row['punch_out'] ? date('h:i A', strtotime($row['punch_out'])) : '-'; ?></span>
                                                </div>
                                                <div class="att-mobile-detail-item">
                                                    <span class="att-mobile-detail-label">Email</span>
                                                    <span class="att-mobile-detail-value"><?php echo htmlspecialchars($row['useremail'] ?? '—'); ?></span>
                                                </div>
                                                <div class="att-mobile-detail-item">
                                                    <span class="att-mobile-detail-label">Contact</span>
                                                    <span class="att-mobile-detail-value"><?php echo htmlspecialchars($row['contact'] ?? '—'); ?></span>
                                                </div>
                                            </div>
                                            <div class="attendance-flex-container">
                                                <!-- Calendar Side -->
                                                <div class="calendar-wrapper">
                                                    <div class="calendar-nav">
                                                        <button onclick="changeMonth(<?php echo $row['user_id']; ?>, -1)"><i
                                                                class="bi bi-chevron-left"></i> Prev</button>
                                                        <div class="calendar-month-title"
                                                            id="month-title-<?php echo $row['user_id']; ?>">Month Year</div>
                                                        <button onclick="changeMonth(<?php echo $row['user_id']; ?>, 1)">Next <i
                                                                class="bi bi-chevron-right"></i></button>
                                                    </div>
                                                    <div class="attendance-calendar-grid"
                                                        id="calendar-grid-<?php echo $row['user_id']; ?>">
                                                        <!-- Calendar Generated via JS -->
                                                    </div>
                                                </div>
                                                <!-- Summary Side -->
                                                <div class="summary-side-panel">
                                                    <h6
                                                        style="font-weight: 800; color: #227477; margin-bottom: 15px; font-size: 0.85rem; letter-spacing: 0.5px;">
                                                        MONTHLY SUMMARY</h6>

                                                    <div class="summary-mini-card">
                                                        <div class="mini-card-icon icon-p"><i class="bi bi-person-check"></i>
                                                        </div>
                                                        <div class="mini-card-info">
                                                            <span class="mini-card-label">Total Present</span>
                                                            <span class="mini-card-value"
                                                                id="stats-p-<?php echo $row['user_id']; ?>">0</span>
                                                        </div>
                                                    </div>
                                                    <div class="summary-mini-card">
                                                        <div class="mini-card-icon icon-l"><i class="bi bi-clock-history"></i>
                                                        </div>
                                                        <div class="mini-card-info">
                                                            <span class="mini-card-label">Total Late</span>
                                                            <span class="mini-card-value"
                                                                id="stats-l-<?php echo $row['user_id']; ?>">0</span>
                                                        </div>
                                                    </div>
                                                    <div class="summary-mini-card">
                                                        <div class="mini-card-icon icon-a"><i class="bi bi-person-x"></i></div>
                                                        <div class="mini-card-info">
                                                            <span class="mini-card-label">Total Absents</span>
                                                            <span class="mini-card-value"
                                                                id="stats-a-<?php echo $row['user_id']; ?>">0</span>
                                                        </div>
                                                    </div>
                                                    <div class="summary-mini-card">
                                                        <div class="mini-card-icon icon-lv"><i class="bi bi-calendar-event"></i>
                                                        </div>
                                                        <div class="mini-card-info">
                                                            <span class="mini-card-label">Leaves / Offs</span>
                                                            <span class="mini-card-value"
                                                                id="stats-lv-<?php echo $row['user_id']; ?>">0</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr class="empty-row">
                                <td colspan="13" class="empty-cell py-5 text-center text-muted">
                                    No attendance records found.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Mobile: sleek card list with inline dropdown -->
            <div class="mobile-attendance-container d-block d-md-none">
                <?php if (count($attendance_rows) > 0): ?>
                    <div class="mobile-attendance-list">
                        <?php foreach ($attendance_rows as $row): ?>
                            <?php
                            $work_hours = ($row['total_hours'] > 0) ? round($row['total_hours'], 1) . ' hrs' : '-';
                            $status_class = att_status_badge_class($row['status']);
                            $punch_in = $row['punch_in'] ? date('h:i A', strtotime($row['punch_in'])) : '-';
                            $punch_out = $row['punch_out'] ? date('h:i A', strtotime($row['punch_out'])) : '-';
                            ?>
                            <div class="mobile-attendance-card" data-userid="<?php echo (int) $row['user_id']; ?>">
                                <div class="att-card-main">
                                    <div class="att-card-info">
                                        <div class="att-card-meta-line">
                                            <span class="att-card-id">#<?php echo (int) $row['user_id']; ?></span>
                                            <span class="att-card-date-dot">·</span>
                                            <span class="att-card-date"><?php echo date('d M Y', strtotime($row['punch_date'])); ?></span>
                                        </div>
                                        <div class="att-card-name-line">
                                            <span class="att-card-name"><?php echo htmlspecialchars($row['username']); ?></span>
                                            <span class="status-badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($row['status']); ?></span>
                                        </div>
                                    </div>
                                    <button type="button" class="att-card-expand-btn" data-userid="<?php echo $row['user_id']; ?>"
                                        onclick="toggleMobileAttendanceCard(this)" title="Show details" aria-expanded="false">
                                        <i class="bi bi-chevron-down"></i>
                                    </button>
                                </div>
                                <div class="att-card-detail-panel">
                                    <div class="att-mobile-detail-grid">
                                        <div class="att-mobile-detail-item" data-col="2">
                                            <span class="att-mobile-detail-label">Emp ID</span>
                                            <span class="att-mobile-detail-value"><?php echo htmlspecialchars($row['employee_id'] ?? '—'); ?></span>
                                        </div>
                                        <div class="att-mobile-detail-item" data-col="3">
                                            <span class="att-mobile-detail-label">Unique ID</span>
                                            <span class="att-mobile-detail-value"><?php echo htmlspecialchars($row['uniqueid'] ?? '—'); ?></span>
                                        </div>
                                        <div class="att-mobile-detail-item" data-col="5">
                                            <span class="att-mobile-detail-label">Email</span>
                                            <span class="att-mobile-detail-value"><?php echo htmlspecialchars($row['useremail'] ?? '—'); ?></span>
                                        </div>
                                        <div class="att-mobile-detail-item" data-col="6">
                                            <span class="att-mobile-detail-label">Contact</span>
                                            <span class="att-mobile-detail-value"><?php echo htmlspecialchars($row['contact'] ?? '—'); ?></span>
                                        </div>
                                        <div class="att-mobile-detail-item" data-col="7">
                                            <span class="att-mobile-detail-label">Role</span>
                                            <span class="att-mobile-detail-value"><?php echo htmlspecialchars($row['role'] ?? '—'); ?></span>
                                        </div>
                                        <div class="att-mobile-detail-item" data-col="8">
                                            <span class="att-mobile-detail-label">Date</span>
                                            <span class="att-mobile-detail-value"><?php echo date('d M Y', strtotime($row['punch_date'])); ?></span>
                                        </div>
                                        <div class="att-mobile-detail-item" data-col="9">
                                            <span class="att-mobile-detail-label">Punch In</span>
                                            <span class="att-mobile-detail-value"><?php echo $punch_in; ?></span>
                                        </div>
                                        <div class="att-mobile-detail-item" data-col="10">
                                            <span class="att-mobile-detail-label">Punch Out</span>
                                            <span class="att-mobile-detail-value"><?php echo $punch_out; ?></span>
                                        </div>
                                        <div class="att-mobile-detail-item" data-col="11">
                                            <span class="att-mobile-detail-label">Work Hrs</span>
                                            <span class="att-mobile-detail-value"><?php echo $work_hours; ?></span>
                                        </div>
                                    </div>
                                    <button type="button" class="att-card-details-btn" data-userid="<?php echo $row['user_id']; ?>"
                                        onclick="openAttendanceDetailsCard(this)" data-col-toggle="13">
                                        View Calendar Details
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="mobile-attendance-card text-center py-5 text-muted">
                        <i class="bi bi-inbox fs-1 mb-2 d-block"></i>
                        No attendance records found.
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <!-- Pagination Section (Matches Users.php) -->
        <div class="pagination-section py-4">
            <div class="pagination-info text-center mb-3 text-muted" style="font-weight: 500;">
                Showing <?php echo $showingStart; ?> to <?php echo $showingEnd; ?> of <?php echo $totalEntries; ?>
                entries
            </div>
            <div class="pagination-controls d-flex justify-content-center gap-2">
                <a href="attendance_report.php?page=<?php echo max(1, $currentPage - 1); ?>&from=<?php echo $from_date; ?>&to=<?php echo $to_date; ?>&search=<?php echo urlencode($search_query); ?>&status=<?php echo urlencode($status_filter); ?>&limit=<?php echo $recordsPerPage; ?>"
                    class="page-btn <?php echo $currentPage <= 1 ? 'disabled' : ''; ?>"
                    style="text-decoration: none;">←</a>

                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="attendance_report.php?page=<?php echo $i; ?>&from=<?php echo $from_date; ?>&to=<?php echo $to_date; ?>&search=<?php echo urlencode($search_query); ?>&status=<?php echo urlencode($status_filter); ?>&limit=<?php echo $recordsPerPage; ?>"
                        class="page-btn <?php echo $i === $currentPage ? 'active' : ''; ?>"
                        style="text-decoration: none;"><?php echo $i; ?></a>
                <?php endfor; ?>

                <a href="attendance_report.php?page=<?php echo min($totalPages, $currentPage + 1); ?>&from=<?php echo $from_date; ?>&to=<?php echo $to_date; ?>&search=<?php echo urlencode($search_query); ?>&status=<?php echo urlencode($status_filter); ?>&limit=<?php echo $recordsPerPage; ?>"
                    class="page-btn <?php echo $currentPage >= $totalPages ? 'disabled' : ''; ?>"
                    style="text-decoration: none;">→</a>
            </div>
        </div>
    </div>
</div>

<!-- Mobile bottom toolbar (Leads-style): Filter | Status | Columns -->
<nav class="att-mobile-bottom-nav d-md-none" aria-label="Attendance actions">
    <button type="button" class="att-mobile-nav-btn att-mobile-filter-btn" id="attMobileFilterBtn">
        <i class="bi bi-funnel-fill"></i>
        <span>Filter</span>
    </button>
    <button type="button" class="att-mobile-nav-btn att-mobile-status-btn<?php echo $status_filter ? ' active' : ''; ?>" id="attMobileStatusBtn">
        <i class="bi bi-ui-checks-grid"></i>
        <span>Status</span>
    </button>
    <button type="button" class="att-mobile-nav-btn att-mobile-columns-btn" id="attMobileColumnsBtn">
        <i class="bi bi-layout-three-columns"></i>
        <span>Columns</span>
    </button>
</nav>

<!-- Mobile status picker (uses existing #quickStatus + form submit) -->
<div id="attMobileStatusSheet" class="att-mobile-status-sheet d-md-none" aria-hidden="true">
    <div class="att-mobile-status-backdrop" id="attMobileStatusBackdrop"></div>
    <div class="att-mobile-status-panel" role="dialog" aria-modal="true" aria-labelledby="attMobileStatusTitle">
        <h6 id="attMobileStatusTitle">Filter by Status</h6>
        <div class="att-mobile-status-options">
            <button type="button" class="att-mobile-status-option" data-status="">All Status</button>
            <button type="button" class="att-mobile-status-option" data-status="Present">Present</button>
            <button type="button" class="att-mobile-status-option" data-status="Late">Late</button>
            <button type="button" class="att-mobile-status-option" data-status="Absent">Absent</button>
            <button type="button" class="att-mobile-status-option" data-status="On Leave">On Leave</button>
        </div>
    </div>
</div>

<!-- Mobile: calendar + summary details card -->
<div id="attendanceDetailsOverlay" class="attendance-details-overlay" aria-hidden="true">
    <div class="attendance-details-backdrop" onclick="closeAttendanceDetailsCard()"></div>
    <div class="attendance-details-card" role="dialog" aria-modal="true" aria-labelledby="attDetailsEmployeeName">
        <div class="attendance-details-card-header">
            <div>
                <span class="att-details-subtitle">Attendance Details</span>
                <h5 id="attDetailsEmployeeName">Employee</h5>
            </div>
            <button type="button" class="attendance-details-close" onclick="closeAttendanceDetailsCard()" aria-label="Close">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="attendance-details-card-body" id="attendanceDetailsBody"></div>
    </div>
</div>

<script>
    let currentCalendarDates = {}; // Store {userId: {month, year}}
    let activeAttendanceDetailsUserId = null;

    function isAttendanceMobileView() {
        return window.innerWidth <= 768;
    }

    function toggleMobileAttendanceCard(btn) {
        const card = btn.closest('.mobile-attendance-card');
        const panel = card?.querySelector('.att-card-detail-panel');
        const icon = btn.querySelector('i');
        if (!card || !panel) return;

        const isOpen = card.classList.contains('expanded');

        document.querySelectorAll('.mobile-attendance-card.expanded').forEach(c => {
            if (c === card) return;
            c.classList.remove('expanded');
            const p = c.querySelector('.att-card-detail-panel');
            if (p) p.style.display = 'none';
            const b = c.querySelector('.att-card-expand-btn');
            if (b) {
                b.classList.remove('active');
                b.setAttribute('aria-expanded', 'false');
                const i = b.querySelector('i');
                if (i) i.className = 'bi bi-chevron-down';
            }
        });

        if (isOpen) {
            card.classList.remove('expanded');
            panel.style.display = 'none';
            btn.classList.remove('active');
            btn.setAttribute('aria-expanded', 'false');
            if (icon) icon.className = 'bi bi-chevron-down';
        } else {
            card.classList.add('expanded');
            panel.style.display = 'block';
            btn.classList.add('active');
            btn.setAttribute('aria-expanded', 'true');
            if (icon) icon.className = 'bi bi-chevron-up';
        }
    }

    function closeAttendanceDetailsCard(resetBtns = true) {
        const overlay = document.getElementById('attendanceDetailsOverlay');
        const body = document.getElementById('attendanceDetailsBody');
        if (!overlay || !body) return;

        const content = body.querySelector('.user-expand-content');
        if (content && activeAttendanceDetailsUserId) {
            const expandRow = document.getElementById('expand-' + activeAttendanceDetailsUserId);
            if (expandRow) {
                expandRow.querySelector('td').appendChild(content);
            }
        }

        overlay.classList.remove('active');
        overlay.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        activeAttendanceDetailsUserId = null;

        if (resetBtns) {
            document.querySelectorAll('.user-expand-btn, .att-card-expand-btn').forEach(b => {
                b.classList.remove('active');
                const icon = b.querySelector('i');
                if (icon) {
                    icon.className = b.classList.contains('att-card-expand-btn')
                        ? (b.closest('.mobile-attendance-card')?.classList.contains('expanded') ? 'bi bi-chevron-up' : 'bi bi-chevron-down')
                        : 'bi bi-chevron-right';
                }
            });
        }
    }

    function openAttendanceDetailsCard(btn) {
        const userId = btn.dataset.userid;
        const overlay = document.getElementById('attendanceDetailsOverlay');
        const body = document.getElementById('attendanceDetailsBody');
        const expandRow = document.getElementById('expand-' + userId);
        if (!overlay || !body || !expandRow) return;

        if (activeAttendanceDetailsUserId === userId && overlay.classList.contains('active')) {
            closeAttendanceDetailsCard();
            return;
        }

        closeAttendanceDetailsCard(false);

        const content = expandRow.querySelector('.user-expand-content');
        const dataRow = btn.closest('tr.user-data-row');
        const card = btn.closest('.mobile-attendance-card');
        const nameEl = document.getElementById('attDetailsEmployeeName');
        let employeeName = 'Employee';
        if (dataRow) {
            const nameCell = dataRow.querySelector('td[data-label="NAME"]');
            if (nameCell) employeeName = nameCell.textContent.trim();
        } else if (card) {
            const cardName = card.querySelector('.att-card-name');
            if (cardName) employeeName = cardName.textContent.trim();
        }
        if (nameEl) nameEl.textContent = employeeName;

        body.appendChild(content);
        overlay.classList.add('active');
        overlay.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        activeAttendanceDetailsUserId = userId;

        document.querySelectorAll('.user-expand-btn').forEach(b => {
            b.classList.remove('active');
            const icon = b.querySelector('i');
            if (icon) {
                icon.className = b.classList.contains('att-card-expand-btn')
                    ? 'bi bi-chevron-down'
                    : 'bi bi-chevron-right';
            }
        });
        btn.classList.add('active');
        const icon = btn.querySelector('i');
        if (icon) icon.className = 'bi bi-chevron-down';

        if (!currentCalendarDates[userId]) {
            const now = new Date();
            currentCalendarDates[userId] = { month: now.getMonth() + 1, year: now.getFullYear() };
        }
        renderCalendar(userId);
    }

    function toggleAttendanceRow(btn) {
        if (isAttendanceMobileView() && btn.classList.contains('att-card-expand-btn')) {
            toggleMobileAttendanceCard(btn);
            return;
        }

        const userId = btn.dataset.userid;
        const expandRow = document.getElementById('expand-' + userId);
        const icon = btn.querySelector('i');

        // Close others
        document.querySelectorAll('.user-expand-row').forEach(row => {
            if (row.id !== 'expand-' + userId) row.style.display = 'none';
        });
        document.querySelectorAll('.user-expand-btn').forEach(b => {
            if (b !== btn) {
                b.classList.remove('active');
                b.querySelector('i').className = 'bi bi-chevron-right';
            }
        });
        if (expandRow.style.display === 'none') {
            expandRow.style.display = 'table-row';
            btn.classList.add('active');
            icon.className = 'bi bi-chevron-down';

            // Initialize to current month if not set
            if (!currentCalendarDates[userId]) {
                const now = new Date();
                currentCalendarDates[userId] = { month: now.getMonth() + 1, year: now.getFullYear() };
            }
            renderCalendar(userId);
        } else {
            expandRow.style.display = 'none';
            btn.classList.remove('active');
            icon.className = 'bi bi-chevron-right';
        }
    }
    function changeMonth(userId, delta) {
        let { month, year } = currentCalendarDates[userId];
        month += delta;
        if (month < 1) { month = 12; year--; }
        if (month > 12) { month = 1; year++; }
        currentCalendarDates[userId] = { month, year };
        renderCalendar(userId);
    }
    function renderCalendar(userId) {
        const { month, year } = currentCalendarDates[userId];
        const grid = document.getElementById('calendar-grid-' + userId);
        const title = document.getElementById('month-title-' + userId);

        const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
        title.innerText = monthNames[month - 1] + " " + year;

        grid.innerHTML = '<div style="text-align:center; grid-column: 1/8; padding: 20px;">Loading History...</div>';
        fetch(`fetch_attendance_history.php?user_id=${userId}&month=${month}&year=${year}`)
            .then(res => res.json())
            .then(logs => {
                const date = new Date(year, month - 1, 1);
                const daysInMonth = new Date(year, month, 0).getDate();
                const firstDay = date.getDay(); // 0 (Sun) to 6 (Sat)

                let html = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].map(d => `<div class="calendar-header-day">${d}</div>`).join('');

                // Stats Counters
                let countP = 0, countL = 0, countA = 0, countLV = 0;
                // Empty slots for first week
                for (let i = 0; i < firstDay; i++) {
                    html += '<div class="calendar-day empty"></div>';
                }

                for (let d = 1; d <= daysInMonth; d++) {
                    const dateStr = `${year}-${String(month).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
                    const dayOfWeek = (firstDay + d - 1) % 7;
                    let dotClass = '';
                    let dayTitle = '';

                    if (logs && logs[dateStr]) {
                        const log = logs[dateStr];
                        const status = (typeof log === 'object' ? log.status : log) || "";
                        const statusLower = status.toLowerCase();
                        const reason = (typeof log === 'object' ? log.reason : '') || '';
                        if (reason) dayTitle = ` title="${reason.replace(/"/g, '&quot;')}"`;

                        if (log.is_company_holiday) { dotClass = 'dot-company-holiday'; countLV++; }
                        else if (statusLower === 'present') { dotClass = 'dot-present'; countP++; }
                        else if (statusLower === 'late') { dotClass = 'dot-late'; countP++; countL++; }
                        else if (statusLower === 'absent' || statusLower === 'late-absent') { dotClass = 'dot-absent'; countA++; }
                        else { dotClass = 'dot-dayoff'; countLV++; } // Leave / Sunday
                    } else if (dayOfWeek === 0) {
                        dotClass = 'dot-dayoff';
                        dayTitle = ' title="Sunday"';
                        countLV++;
                    } else {
                        const today = new Date();
                        today.setHours(0, 0, 0, 0);
                        const checkDate = new Date(year, month - 1, d);
                        if (checkDate < today) { dotClass = 'dot-absent'; countA++; }
                    }
                    html += `
                    <div class="calendar-day"${dayTitle}>
                        ${d}
                        <div class="status-dot ${dotClass}"></div>
                    </div>
                `;
                }
                grid.innerHTML = html;
                // Update Stats Display
                document.getElementById(`stats-p-${userId}`).innerText = countP;
                document.getElementById(`stats-l-${userId}`).innerText = countL;
                document.getElementById(`stats-a-${userId}`).innerText = countA;
                document.getElementById(`stats-lv-${userId}`).innerText = countLV;
            })
            .catch(err => {
                console.error("Calendar fetch error:", err);
                grid.innerHTML = '<div style="text-align:center; grid-column: 1/8; padding: 20px; color: red;">Failed to load history.</div>';
            });
    }
    // --- Column Visibility Logic ---
    document.addEventListener("DOMContentLoaded", function () {
        const dropdown = document.getElementById("columnDropdown");
        const table = document.querySelector(".user-data-table");
        const columnBtn = document.getElementById("columnVisibilityBtn");
        if (!dropdown || !table) return;

        const checkboxes = dropdown.querySelectorAll("input[type='checkbox']");
        const attMobileColumnsBtn = document.getElementById("attMobileColumnsBtn");
        const MOBILE_ROW_COLS = new Set([1, 4, 8, 12]);

        function portalMobileColumnDropdown() {
            if (window.innerWidth > 768 || dropdown.dataset.portaled === '1') return;
            document.body.appendChild(dropdown);
            dropdown.classList.add('att-mobile-column-dropdown');
            dropdown.dataset.portaled = '1';
        }

        function syncMobileDetailColumns() {
            if (window.innerWidth > 768) return;
            checkboxes.forEach(cb => {
                if (MOBILE_ROW_COLS.has(parseInt(cb.dataset.col, 10))) {
                    cb.checked = true;
                }
            });
            checkboxes.forEach(cb => {
                const col = parseInt(cb.dataset.col, 10);
                const show = cb.checked;

                document.querySelectorAll(`.att-card-detail-panel .att-mobile-detail-item[data-col="${col}"]`).forEach(el => {
                    el.style.display = show ? '' : 'none';
                });

                document.querySelectorAll(`.att-card-details-btn[data-col-toggle="${col}"]`).forEach(el => {
                    el.style.display = show ? '' : 'none';
                });
            });
        }

        function toggleMobileColumnDropdown(forceClose = false) {
            portalMobileColumnDropdown();
            if (forceClose) {
                dropdown.classList.remove('show');
                attMobileColumnsBtn?.classList.remove('active');
                return;
            }
            dropdown.classList.toggle('show');
            if (dropdown.classList.contains('show')) {
                dropdown.style.setProperty('top', 'auto', 'important');
                dropdown.style.setProperty('bottom', 'calc(72px + env(safe-area-inset-bottom, 0px))', 'important');
                dropdown.style.setProperty('left', 'auto', 'important');
                dropdown.style.setProperty('right', '12px', 'important');
                dropdown.style.setProperty('transform', 'none', 'important');
            }
            attMobileColumnsBtn?.classList.toggle('active', dropdown.classList.contains('show'));
        }

        if (columnBtn) {
            columnBtn.addEventListener("click", function (e) {
                e.stopPropagation();
                if (window.innerWidth <= 768) {
                    toggleMobileColumnDropdown();
                } else {
                    dropdown.classList.toggle("show");
                }
            });
        }

        document.addEventListener("click", function (e) {
            if (e.target.closest('#columnDropdown') || e.target.closest('#attMobileColumnsBtn') || e.target.closest('#columnVisibilityBtn')) {
                return;
            }
            dropdown.classList.remove("show");
            attMobileColumnsBtn?.classList.remove('active');
        });
        dropdown.addEventListener("click", function (e) { e.stopPropagation(); });

        function toggleColumn(index, show) {
            if (window.innerWidth <= 768) {
                syncMobileDetailColumns();
                return;
            }
            table.querySelectorAll(`thead tr th:nth-child(${index}), tbody tr:not(.user-expand-row) td:nth-child(${index})`).forEach(cell => {
                if (show) {
                    cell.style.removeProperty("display");
                } else {
                    cell.style.setProperty("display", "none", "important");
                }
            });
            const visibleCount = Array.from(checkboxes).filter(cb => cb.checked).length;
            document.querySelectorAll('.user-expand-row td').forEach(td => {
                td.colSpan = visibleCount;
            });
        }
        function saveAttendancePrefs() {
            const prefs = Array.from(checkboxes).map(cb => ({
                col: cb.dataset.col,
                checked: cb.checked
            }));
            localStorage.setItem('attendance_report_columns', JSON.stringify(prefs));
        }
        function restoreAttendancePrefs() {
            const saved = localStorage.getItem('attendance_report_columns');
            if (saved) {
                const prefs = JSON.parse(saved);
                prefs.forEach(p => {
                    const cb = dropdown.querySelector(`input[data-col='${p.col}']`);
                    if (cb) {
                        cb.checked = p.checked;
                        toggleColumn(parseInt(p.col), p.checked);
                    }
                });
                return true;
            }
            return false;
        }

        checkboxes.forEach(cb => {
            cb.addEventListener("change", function () {
                toggleColumn(parseInt(this.dataset.col, 10), this.checked);
                if (window.innerWidth <= 768) {
                    syncMobileDetailColumns();
                }
                saveAttendancePrefs();
            });
        });

        window.applyAttendanceDefaults = function () {
            const width = window.innerWidth;
            if (width <= 768) {
                if (restoreAttendancePrefs()) {
                    syncMobileDetailColumns();
                    return;
                }
                const defaultMobileDetailCols = [2, 3, 9, 10, 11, 13];
                checkboxes.forEach(cb => {
                    const col = parseInt(cb.dataset.col, 10);
                    cb.checked = MOBILE_ROW_COLS.has(col) || defaultMobileDetailCols.includes(col);
                });
                syncMobileDetailColumns();
                return;
            }
            if (restoreAttendancePrefs()) return;
            let defaultCols = [1, 2, 4, 8, 11, 12, 13];
            if (width >= 1440) defaultCols = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13];
            else if (width > 1024) defaultCols = [1, 2, 4, 5, 8, 11, 12, 13];
            else defaultCols = [2, 4, 8, 12, 13];
            checkboxes.forEach(cb => {
                const col = parseInt(cb.dataset.col);
                const shouldShow = defaultCols.includes(col);
                cb.checked = shouldShow;
                toggleColumn(col, shouldShow);
            });
            if (width <= 768) {
                table.style.width = "100%";
            } else {
                table.style.width = "max-content";
            }
        }

        let resizeTimer;
        window.addEventListener("resize", () => {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(() => {
                if (!isAttendanceMobileView() && activeAttendanceDetailsUserId) {
                    closeAttendanceDetailsCard();
                }
                window.applyAttendanceDefaults();
            }, 180);
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeAttendanceDetailsCard();
        });

        applyAttendanceDefaults();

        document.querySelectorAll(".attendance-report-page .user-data-table tbody tr.user-data-row").forEach(row => {
            row.addEventListener("click", function (e) {
                if (window.innerWidth > 768) return;
                if (e.target.closest("button") || e.target.closest("a")) return;
                const expandBtn = row.querySelector(".user-expand-btn");
                if (expandBtn) expandBtn.click();
            });
        });

        document.querySelectorAll(".mobile-attendance-card").forEach(card => {
            card.addEventListener("click", function (e) {
                if (window.innerWidth > 768) return;
                if (e.target.closest(".att-card-detail-panel") || e.target.closest(".att-card-details-btn") || e.target.closest(".att-card-expand-btn")) return;
                const expandBtn = card.querySelector(".att-card-expand-btn");
                if (expandBtn) expandBtn.click();
            });
        });

        /* Mobile bottom nav — layout only, triggers existing controls */
        const attMobileFilterBtn = document.getElementById("attMobileFilterBtn");
        const attMobileStatusBtn = document.getElementById("attMobileStatusBtn");
        const attMobileStatusSheet = document.getElementById("attMobileStatusSheet");
        const attMobileStatusBackdrop = document.getElementById("attMobileStatusBackdrop");
        const quickStatus = document.getElementById("quickStatus");

        function closeAttMobileStatusSheet() {
            if (!attMobileStatusSheet) return;
            attMobileStatusSheet.classList.remove("active");
            attMobileStatusSheet.setAttribute("aria-hidden", "true");
            attMobileStatusBtn?.classList.remove("active");
        }

        function openAttMobileStatusSheet() {
            if (!attMobileStatusSheet || !quickStatus) return;
            attMobileStatusSheet.querySelectorAll(".att-mobile-status-option").forEach(opt => {
                opt.classList.toggle("selected", opt.dataset.status === quickStatus.value);
            });
            attMobileStatusSheet.classList.add("active");
            attMobileStatusSheet.setAttribute("aria-hidden", "false");
            attMobileStatusBtn?.classList.add("active");
        }

        attMobileFilterBtn?.addEventListener("click", () => {
            document.getElementById("openFilterBtn")?.click();
        });

        attMobileStatusBtn?.addEventListener("click", () => {
            if (attMobileStatusSheet?.classList.contains("active")) {
                closeAttMobileStatusSheet();
            } else {
                toggleMobileColumnDropdown(true);
                openAttMobileStatusSheet();
            }
        });

        attMobileColumnsBtn?.addEventListener("click", (e) => {
            e.stopPropagation();
            closeAttMobileStatusSheet();
            toggleMobileColumnDropdown();
        });

        attMobileStatusBackdrop?.addEventListener("click", closeAttMobileStatusSheet);

        attMobileStatusSheet?.querySelectorAll(".att-mobile-status-option").forEach(opt => {
            opt.addEventListener("click", () => {
                if (!quickStatus) return;
                quickStatus.value = opt.dataset.status;
                closeAttMobileStatusSheet();
                document.getElementById("attendanceFilterForm")?.submit();
            });
        });

    });
</script>
<script src="assets/js/summary-cards.js?v=<?php echo time(); ?>"></script>
<!-- Floating Clear Filters Button -->
<button id="floatingClearFilters" class="floating-clear-btn">
    <i class="bi bi-x-circle"></i>
    Clear Filters
</button>
<script>
    let filtersApplied = <?php echo ($search_query !== '' || isset($_GET['range']) || ($from_date !== date('Y-m-01') && $from_date !== date('Y-m-d'))) ? 'true' : 'false'; ?>;
    function checkIfFiltersActive() {
        const floatingBtn = document.getElementById("floatingClearFilters");
        if (floatingBtn) floatingBtn.style.display = filtersApplied ? "flex" : "none";
    }
    // Real-time Search Debounce
    let filterTimeout;
    function debounceFilter() {
        clearTimeout(filterTimeout);
        filterTimeout = setTimeout(() => {
            document.getElementById('attendanceFilterForm').submit();
        }, 600);
    }
    // USRDD Engine - Advanced Filters
    let usrdd_selected = {};
    function initUsrdd() {
        document.querySelectorAll('.usr-custom-dropdown-input').forEach(input => {
            const field = input.id;
            usrdd_selected[field] = [];
            input.addEventListener('focus', () => showUsrddList(field));
            input.addEventListener('input', () => filterUsrddList(field, input.value));
            document.addEventListener('click', (e) => {
                if (!e.target.closest(`#usrdd-${field}`)) hideUsrddList(field);
            });
        });
    }
    function showUsrddList(field) {
        const list = document.getElementById(`usrdd-${field}-list`);
        if (!list) return;
        // Col mapping for attendance table
        const colMap = { 'filterID': 1, 'username': 3, 'workhours': 10 };
        const colIndex = colMap[field];
        const uniqueValues = new Set();
        document.querySelectorAll(".user-data-table tbody tr.user-data-row").forEach(row => {
            const val = row.cells[colIndex]?.innerText.trim();
            if (val && val !== '-') uniqueValues.add(val);
        });
        let html = "";
        Array.from(uniqueValues).sort().forEach(val => {
            const selected = usrdd_selected[field].includes(val);
            html += `<div class="usr-dd-item ${selected ? 'selected' : ''}" onclick="toggleUsrdd('${field}', '${val}')">
            ${selected ? '<span class="usr-dd-check">✓</span>' : ''} ${val}
        </div>`;
        });
        list.innerHTML = html || '<div class="usr-dd-empty">No values found</div>';
        list.classList.add('open');
    }
    window.toggleUsrdd = function (field, val) {
        const idx = usrdd_selected[field].indexOf(val);
        if (idx > -1) usrdd_selected[field].splice(idx, 1);
        else usrdd_selected[field].push(val);
        renderUsrddChips(field);
        showUsrddList(field);
    };
    function renderUsrddChips(field) {
        const cont = document.getElementById(`usrdd-${field}-chips`);
        if (!cont) return;
        cont.innerHTML = usrdd_selected[field].map(v => `
        <div class="usr-chip">${v}<span class="usr-chip-remove" onclick="toggleUsrdd('${field}', '${v}')">×</span></div>
    `).join('');
    }
    function filterUsrddList(field, q) {
        const items = document.querySelectorAll(`#usrdd-${field}-list .usr-dd-item`);
        items.forEach(i => i.style.display = i.innerText.toLowerCase().includes(q.toLowerCase()) ? 'flex' : 'none');
    }
    function hideUsrddList(field) {
        document.getElementById(`usrdd-${field}-list`)?.classList.remove('open');
    }
    // Modal Toggle Logic
    $(document).ready(function () {
        const modal = $('#filterModal');

        // Move modal to body immediately to escape any blurred containers
        modal.appendTo('body');
        $('#openFilterBtn').on('click', function () {
            $('body').addClass('modal-open');
            modal.addClass('custom-show');
        });
        function closeModal() {
            modal.removeClass('custom-show');
            $('body').removeClass('modal-open');
        }
        $('#closeFilter, .btn-users-close').on('click', closeModal);

        $('#applyFiltersBtn').on('click', function () {
            // Sync Modal Dates to Main Hidden Form
            const startDate = $('#filterFromDate').val();
            const endDate = $('#filterToDate').val();
            $('#finalFrom').val(startDate);
            $('#finalTo').val(endDate);

            // Sync Dropdown Chips to Search Input (Priority: Name > ID > WorkHours)
            let searchQuery = "";
            if (usrdd_selected['username'] && usrdd_selected['username'].length > 0) {
                searchQuery = usrdd_selected['username'][0];
            } else if (usrdd_selected['filterID'] && usrdd_selected['filterID'].length > 0) {
                searchQuery = usrdd_selected['filterID'][0];
            } else if (usrdd_selected['workhours'] && usrdd_selected['workhours'].length > 0) {
                searchQuery = usrdd_selected['workhours'][0];
            }

            if (searchQuery) {
                $('#tableSearchInput').val(searchQuery);
            }

            $('#attendanceFilterForm').submit();
        });
        $('#clearFiltersBtn, #floatingClearFilters').on('click', function () {
            window.location.href = 'attendance_report.php';
        });
        initUsrdd();
        checkIfFiltersActive();
        // Universal Search Logic (From Incentive Users)
        const searchInput = document.getElementById("tableSearchInput");
        if (searchInput) {
            searchInput.addEventListener("keyup", function () {
                const query = this.value.toLowerCase();
                const rows = document.querySelectorAll(".user-data-table tbody tr.user-data-row");

                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(query) ? "" : "none";
                });
            });
            // Prevent Enter key from submitting the form (reloading page)
            searchInput.addEventListener("keydown", function (e) {
                if (e.key === "Enter") e.preventDefault();
            });
        }

        // Setup summary cards as filters
        const cardHeadcount = document.querySelector('.summary-section .stat-card-headcount');
        const cardPresent = document.querySelector('.summary-section .stat-card-present');
        const cardAbsent = document.querySelector('.summary-section .stat-card-absent');
        const cardLate = document.querySelector('.summary-section .stat-card-late');
        const cardLeave = document.querySelector('.summary-section .stat-card-leave');
        const quickStatus = document.getElementById('quickStatus');
        
        if (quickStatus) {
            const currentVal = quickStatus.value;
            
            // Add active-filter class to the selected card based on current status
            if (currentVal === 'Present') {
                cardPresent?.classList.add('active-filter');
            } else if (currentVal === 'Absent') {
                cardAbsent?.classList.add('active-filter');
            } else if (currentVal === 'Late') {
                cardLate?.classList.add('active-filter');
            } else if (currentVal === 'On Leave') {
                cardLeave?.classList.add('active-filter');
            }
            
            // Setup click listeners to toggle status and submit the form
            cardPresent?.addEventListener('click', function () {
                quickStatus.value = (currentVal === 'Present') ? '' : 'Present';
                quickStatus.form.submit();
            });
            cardAbsent?.addEventListener('click', function () {
                quickStatus.value = (currentVal === 'Absent') ? '' : 'Absent';
                quickStatus.form.submit();
            });
            cardLate?.addEventListener('click', function () {
                quickStatus.value = (currentVal === 'Late') ? '' : 'Late';
                quickStatus.form.submit();
            });
            cardLeave?.addEventListener('click', function () {
                quickStatus.value = (currentVal === 'On Leave') ? '' : 'On Leave';
                quickStatus.form.submit();
            });
            cardHeadcount?.addEventListener('click', function () {
                quickStatus.value = '';
                quickStatus.form.submit();
            });
        }
    });
</script>
<?php include 'filter_modal_attendance.php'; ?>
<?php include 'htmlclose.php'; ?>