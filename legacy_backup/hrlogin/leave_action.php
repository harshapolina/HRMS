<?php
session_name('HRSESSID');
session_start();
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
$user_id = $_SESSION['id'];
$action = $_POST['action'] ?? '';
if ($action === 'request_leave') {
    $leave_type_id = $_POST['leave_type_id'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $reason = $_POST['reason'];
    // 1. Calculate number of days
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $interval = $start->diff($end);
    $days = $interval->days + 1;
    // 2. Check if user has enough balance
    // First, ensure balance record exists for this year
    $year = date('Y');
    $res_check = $con->query("SELECT id FROM leave_balances WHERE user_id = $user_id AND leave_type_id = $leave_type_id AND year = $year");
    if ($res_check->num_rows === 0) {
        // Initialize balance from leave_types annual_limit
        $con->query("INSERT INTO leave_balances (user_id, leave_type_id, year, used_count) VALUES ($user_id, $leave_type_id, $year, 0)");
    }
    // Now fetch balance
    $res_bal = $con->query("SELECT (t.annual_limit - b.used_count) as remaining FROM leave_types t JOIN leave_balances b ON t.id = b.leave_type_id WHERE b.user_id = $user_id AND b.leave_type_id = $leave_type_id AND b.year = $year");
    $row_bal = $res_bal->fetch_assoc();
    $remaining = $row_bal['remaining'];
    if ($days > $remaining) {
        $_SESSION['leave_error'] = "Insufficient leave balance. You requested $days days but only $remaining remaining.";
    } else {
        // 3. Insert Request
        $stmt = $con->prepare('INSERT INTO leave_requests (user_id, leave_type_id, start_date, end_date, reason, status) VALUES (?, ?, ?, ?, ?, "Pending")');
        $stmt->bind_param('iisss', $user_id, $leave_type_id, $start_date, $end_date, $reason);
        $stmt->execute();
        $stmt->close();
        
        $_SESSION['leave_success'] = "Leave request submitted successfully!";
    }
}
header('Location: employee_portal.php');
exit;
?>
