<?php
session_start();
// Redirect to login if not logged in or not an admin
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'hradminuser') {
    exit(json_encode(['error' => 'Unauthorized']));
}
session_write_close();

require_once __DIR__ . '/includes/db_mysqli.php';

$userId = (int) ($_GET['user_id'] ?? 0);
$month = str_pad((string) ($_GET['month'] ?? date('m')), 2, '0', STR_PAD_LEFT);
$year = (string) ($_GET['year'] ?? date('Y'));

$start_date = "$year-$month-01";
$end_date = date('Y-m-t', strtotime($start_date));
$logs = [];

date_default_timezone_set('Asia/Kolkata');

try {
    $con = hr_mysqli_connect();

    $con->query("CREATE TABLE IF NOT EXISTS company_holidays (
        id INT AUTO_INCREMENT PRIMARY KEY,
        holiday_date DATE NOT NULL,
        reason VARCHAR(255) NOT NULL,
        created_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_holiday_date (holiday_date)
    )");

    // Prefer indexed attendance_logs for the month (avoids decoding full history_json blob)
    $sql = "SELECT punch_date, punch_in, punch_out, status, total_hours
            FROM attendance_logs
            WHERE user_id = ? AND punch_date BETWEEN ? AND ?
            ORDER BY punch_date ASC";
    $stmt = $con->prepare($sql);
    $stmt->bind_param('iss', $userId, $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $date = $row['punch_date'];
        $punch_in = $row['punch_in'] ?? null;
        $punch_out = $row['punch_out'] ?? null;
        $reasonStr = '';
        if ($punch_in) {
            $reasonStr = date('g:i A', strtotime($punch_in));
            if ($punch_out) {
                $reasonStr .= ' - ' . date('g:i A', strtotime($punch_out));
            } else {
                $reasonStr .= ' - No Out';
            }
        }
        $logs[$date] = [
            'status' => $row['status'] ?? '',
            'punch_in' => $punch_in,
            'punch_out' => $punch_out,
            'total_hours' => $row['total_hours'] ?? null,
            'reason' => $reasonStr,
        ];
    }
    $stmt->close();

    // Fallback: merge from user_attendance JSON for dates missing in attendance_logs
    $sqlUa = 'SELECT today_date, today_punch_in, today_punch_out, today_status, today_total_hours, history_json
              FROM user_attendance WHERE user_id = ? LIMIT 1';
    $stmtUa = $con->prepare($sqlUa);
    $stmtUa->bind_param('i', $userId);
    $stmtUa->execute();
    $uaResult = $stmtUa->get_result();
    $uaRow = $uaResult ? $uaResult->fetch_assoc() : null;
    $stmtUa->close();

    $todayStr = date('Y-m-d');
    if ($uaRow) {
        if ($todayStr >= $start_date && $todayStr <= $end_date && !isset($logs[$todayStr])) {
            $today_date = $uaRow['today_date'] ?? null;
            if ($today_date === $todayStr) {
                $status = $uaRow['today_status'] ?? '';
                if ($status && $status !== 'Not Logged') {
                    $punch_in = $uaRow['today_punch_in'] ?? null;
                    $punch_out = $uaRow['today_punch_out'] ?? null;
                    $reasonStr = '';
                    if ($punch_in) {
                        $reasonStr = date('g:i A', strtotime($punch_in));
                        $reasonStr .= $punch_out ? (' - ' . date('g:i A', strtotime($punch_out))) : ' - No Out';
                    }
                    $logs[$todayStr] = [
                        'status' => $status,
                        'punch_in' => $punch_in,
                        'punch_out' => $punch_out,
                        'total_hours' => $uaRow['today_total_hours'] ?? null,
                        'reason' => $reasonStr,
                    ];
                }
            }
        }

        $history = json_decode($uaRow['history_json'] ?? '', true) ?: [];
        foreach ($history as $date => $record) {
            if ($date === $todayStr || $date < $start_date || $date > $end_date || isset($logs[$date])) {
                continue;
            }
            $punch_in = $record['punch_in'] ?? null;
            $punch_out = $record['punch_out'] ?? null;
            $reasonStr = '';
            if ($punch_in) {
                $reasonStr = date('g:i A', strtotime($punch_in));
                $reasonStr .= $punch_out ? (' - ' . date('g:i A', strtotime($punch_out))) : ' - No Out';
            }
            $logs[$date] = [
                'status' => $record['status'] ?? '',
                'punch_in' => $punch_in,
                'punch_out' => $punch_out,
                'total_hours' => $record['total_hours'] ?? null,
                'reason' => $reasonStr,
            ];
        }
    }

    // Approved leave overlay
    $sql = "SELECT lr.start_date, lr.end_date, lr.reason, t.leave_name
            FROM leave_requests lr
            JOIN leave_types t ON lr.leave_type_id = t.id
            WHERE lr.user_id = ? AND lr.status = 'Approved'
            AND (lr.start_date <= ? AND lr.end_date >= ?)";
    $stmt = $con->prepare($sql);
    $stmt->bind_param('iss', $userId, $end_date, $start_date);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $begin = new DateTime(max($start_date, $row['start_date']));
        $end = new DateTime(min($end_date, $row['end_date']));
        $end->modify('+1 day');
        $daterange = new DatePeriod($begin, new DateInterval('P1D'), $end);
        foreach ($daterange as $date) {
            $d = $date->format('Y-m-d');
            if (!isset($logs[$d]) || in_array($logs[$d]['status'] ?? '', ['Absent', 'Late-Absent'], true)) {
                $logs[$d] = [
                    'status' => $row['leave_name'],
                    'reason' => $row['reason'],
                ];
            }
        }
    }
    $stmt->close();

    // Company holidays
    $hStmt = $con->prepare('SELECT holiday_date, reason FROM company_holidays WHERE holiday_date BETWEEN ? AND ?');
    if ($hStmt) {
        $hStmt->bind_param('ss', $start_date, $end_date);
        $hStmt->execute();
        $hRes = $hStmt->get_result();
        while ($hRow = $hRes->fetch_assoc()) {
            $d = $hRow['holiday_date'];
            $logs[$d] = [
                'status' => 'Holiday',
                'reason' => $hRow['reason'],
                'is_company_holiday' => true,
            ];
        }
        $hStmt->close();
    }

    $con->close();
} catch (Throwable $e) {
    error_log('fetch_attendance_history.php: ' . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Failed to load attendance history.']);
    exit;
}

header('Content-Type: application/json');
echo json_encode($logs);
