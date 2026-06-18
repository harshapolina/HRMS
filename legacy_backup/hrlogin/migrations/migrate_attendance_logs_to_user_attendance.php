<?php
/**
 * One-time migration: attendance_logs → user_attendance.history_json
 * Run from CLI: php migrations/migrate_attendance_logs_to_user_attendance.php
 * Or open once as HR admin via browser (then delete or restrict access).
 */
declare(strict_types=1);

$isCli = PHP_SAPI === 'cli';
if (!$isCli) {
    session_start();
    if (!isset($_SESSION['loggedin']) || ($_SESSION['role'] ?? '') !== 'hradminuser') {
        http_response_code(403);
        exit('Forbidden');
    }
    session_write_close();
}

require_once dirname(__DIR__) . '/includes/db_mysqli.php';

try {
    $con = hr_mysqli_connect();
    hr_ensure_user_attendance_table($con);

    $count_res = $con->query('SELECT COUNT(*) AS count FROM user_attendance');
    $existing = $count_res ? (int) ($count_res->fetch_assoc()['count'] ?? 0) : 0;
    if ($existing > 0) {
        $msg = "Skipped: user_attendance already has {$existing} row(s).";
        echo $isCli ? $msg . PHP_EOL : $msg;
        exit(0);
    }

    $logs_exist = $con->query("SHOW TABLES LIKE 'attendance_logs'");
    if (!$logs_exist || $logs_exist->num_rows === 0) {
        echo $isCli ? "No attendance_logs table.\n" : 'No attendance_logs table.';
        exit(0);
    }

    $logs_res = $con->query('SELECT user_id, punch_date, punch_in, punch_out, status, latitude_in, longitude_in, latitude_out, longitude_out, ip_address, total_hours FROM attendance_logs ORDER BY user_id, punch_date ASC');
    if (!$logs_res) {
        throw new RuntimeException('Failed to read attendance_logs.');
    }

    $grouped = [];
    while ($log = $logs_res->fetch_assoc()) {
        $uid = (int) $log['user_id'];
        $grouped[$uid][] = $log;
    }

    $todayStr = date('Y-m-d');
    $insertFull = $con->prepare('INSERT INTO user_attendance (
        user_id, today_punch_in, today_punch_out, today_status,
        today_lat_in, today_lng_in, today_lat_out, today_lng_out,
        today_ip, today_total_hours, history_json
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $insertHistory = $con->prepare('INSERT INTO user_attendance (user_id, history_json) VALUES (?, ?)');

    $migrated = 0;
    foreach ($grouped as $uid => $userLogs) {
        $history = [];
        $todayLog = null;
        foreach ($userLogs as $log) {
            $date = $log['punch_date'];
            $history[$date] = [
                'punch_in' => $log['punch_in'],
                'punch_out' => $log['punch_out'],
                'status' => $log['status'],
                'latitude_in' => $log['latitude_in'],
                'longitude_in' => $log['longitude_in'],
                'latitude_out' => $log['latitude_out'],
                'longitude_out' => $log['longitude_out'],
                'ip_address' => $log['ip_address'],
                'total_hours' => $log['total_hours'] !== null ? (float) $log['total_hours'] : null,
            ];
            if ($date === $todayStr) {
                $todayLog = $log;
            }
        }
        $historyJson = json_encode($history);
        if ($todayLog) {
            $insertFull->bind_param(
                'issssssssds',
                $uid,
                $todayLog['punch_in'],
                $todayLog['punch_out'],
                $todayLog['status'],
                $todayLog['latitude_in'],
                $todayLog['longitude_in'],
                $todayLog['latitude_out'],
                $todayLog['longitude_out'],
                $todayLog['ip_address'],
                $todayLog['total_hours'],
                $historyJson
            );
            $insertFull->execute();
        } else {
            $insertHistory->bind_param('is', $uid, $historyJson);
            $insertHistory->execute();
        }
        $migrated++;
    }

    $msg = "Migrated {$migrated} user(s) from attendance_logs.";
    echo $isCli ? $msg . PHP_EOL : $msg;
} catch (Throwable $e) {
    error_log('migrate_attendance_logs: ' . $e->getMessage());
    $err = 'Migration failed: ' . $e->getMessage();
    echo $isCli ? $err . PHP_EOL : $err;
    exit(1);
}
