<?php
session_name('HRSESSID');
session_start();
date_default_timezone_set('Asia/Kolkata');
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'employee') {
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
    exit('Failed to connect to MySQL: ' . $e->getMessage());
}
hr_ensure_user_attendance_table($con);

$user_id = $_SESSION['id'];
$action = $_POST['action'];
$today = date('Y-m-d');
$now = date('H:i:s');
$latitude = $_POST['latitude'] ?? null;
$longitude = $_POST['longitude'] ?? null;
$ip = $_SERVER['REMOTE_ADDR'] ?? '';

if ($action === 'punch_in') {
    // 1. Fetch settings for "Late" calculation
    $office_start = '09:00:00';
    $grace_mins = 15;
    $res = $con->query("SELECT setting_key, setting_value FROM hr_settings WHERE setting_key IN ('office_start_time', 'grace_period_minutes')");
    while($row = $res->fetch_assoc()) {
        if ($row['setting_key'] == 'office_start_time') $office_start = $row['setting_value'];
        if ($row['setting_key'] == 'grace_period_minutes') $grace_mins = (int)$row['setting_value'];
    }
    // 2. Calculate if late
    $start_time_limit = strtotime($office_start) + ($grace_mins * 60);
    $current_time = strtotime($now);
    $status = ($current_time > $start_time_limit) ? 'Late' : 'Present';
    
    // Check if user already has an attendance record
    $stmt = $con->prepare('SELECT today_punch_in, history_json FROM user_attendance WHERE user_id = ?');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->store_result();
    $has_row = ($stmt->num_rows > 0);
    $existing_punch_in = null;
    $existing_history_json = null;
    if ($has_row) {
        $stmt->bind_result($existing_punch_in, $existing_history_json);
        $stmt->fetch();
    }
    $stmt->close();

    if (!$has_row) {
        $history_arr = [
            $today => [
                'punch_in' => $now,
                'punch_out' => null,
                'status' => $status,
                'latitude_in' => $latitude,
                'longitude_in' => $longitude,
                'latitude_out' => null,
                'longitude_out' => null,
                'ip_address' => $ip,
                'total_hours' => null
            ]
        ];
        $history_json = json_encode($history_arr);
        $stmt = $con->prepare('INSERT INTO user_attendance (user_id, today_date, today_punch_in, today_status, today_lat_in, today_lng_in, today_ip, history_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('isssssss', $user_id, $today, $now, $status, $latitude, $longitude, $ip, $history_json);
        $stmt->execute();
        $stmt->close();
    } else {
        $history_arr = json_decode($existing_history_json, true) ?: [];
        // Only set punch in details if today's record doesn't exist in history
        if (!isset($history_arr[$today])) {
            $history_arr[$today] = [
                'punch_in' => $now,
                'punch_out' => null,
                'status' => $status,
                'latitude_in' => $latitude,
                'longitude_in' => $longitude,
                'latitude_out' => null,
                'longitude_out' => null,
                'ip_address' => $ip,
                'total_hours' => null
            ];
            $history_json = json_encode($history_arr);
            
            $stmt = $con->prepare('UPDATE user_attendance SET today_date = ?, today_punch_in = ?, today_status = ?, today_lat_in = ?, today_lng_in = ?, today_ip = ?, today_punch_out = NULL, today_lat_out = NULL, today_lng_out = NULL, today_total_hours = NULL, history_json = ? WHERE user_id = ?');
            $stmt->bind_param('sssssssi', $today, $now, $status, $latitude, $longitude, $ip, $history_json, $user_id);
            $stmt->execute();
            $stmt->close();
        }
    }
} elseif ($action === 'punch_out') {
    // 1. Fetch today_punch_in and history_json
    $stmt = $con->prepare('SELECT today_punch_in, history_json FROM user_attendance WHERE user_id = ?');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->bind_result($today_punch_in, $history_json);
    $stmt->fetch();
    $stmt->close();

    $break_mins = 0;
    $res = $con->query("SELECT setting_value FROM hr_settings WHERE setting_key = 'break_time_minutes'");
    if ($row = $res->fetch_assoc()) $break_mins = (int)$row['setting_value'];
    
    $total_hours = 0;
    if ($today_punch_in) {
        $start = new DateTime($today_punch_in);
        $end = new DateTime($now);
        $interval = $start->diff($end);
        
        // Calculate total hours as decimal
        $hours = $interval->h + ($interval->i / 60) + ($interval->s / 3600);
        $total_hours = max(0, $hours - ($break_mins / 60));
    }
    
    // Update history json
    $history_arr = json_decode($history_json, true) ?: [];
    if (!isset($history_arr[$today])) {
        // Fallback in case punch in wasn't properly initialized in history
        $history_arr[$today] = [
            'punch_in' => $today_punch_in ?: $now,
            'punch_out' => $now,
            'status' => 'Present',
            'latitude_in' => $latitude,
            'longitude_in' => $longitude,
            'latitude_out' => $latitude,
            'longitude_out' => $longitude,
            'ip_address' => $ip,
            'total_hours' => $total_hours
        ];
    } else {
        $history_arr[$today]['punch_out'] = $now;
        $history_arr[$today]['latitude_out'] = $latitude;
        $history_arr[$today]['longitude_out'] = $longitude;
        $history_arr[$today]['total_hours'] = $total_hours;
    }
    $history_json_updated = json_encode($history_arr);
    
    // Update database
    $stmt = $con->prepare('UPDATE user_attendance SET today_punch_out = ?, today_lat_out = ?, today_lng_out = ?, today_total_hours = ?, history_json = ? WHERE user_id = ?');
    $stmt->bind_param('sssssi', $now, $latitude, $longitude, $total_hours, $history_json_updated, $user_id);
    $stmt->execute();
    $stmt->close();
}
header('Location: employee_portal.php');
exit;
