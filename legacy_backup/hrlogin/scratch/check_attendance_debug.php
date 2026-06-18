<?php
$DATABASE_HOST = 'localhost';
$DATABASE_USER = 'u797909128_demoproject';
$DATABASE_PASS = 'QK&0/aF@5';
$DATABASE_NAME = 'u797909128_demo';
$con = mysqli_connect($DATABASE_HOST, $DATABASE_USER, $DATABASE_PASS, $DATABASE_NAME);

$username = 'username41';
$res = $con->query("SELECT id FROM accounts WHERE username = '$username'");
$user = $res->fetch_assoc();
$userId = $user['id'];

echo "User ID for $username: $userId\n";

$start = '2026-04-01';
$end = '2026-04-30';

$res = $con->query("SELECT punch_date, status FROM attendance_logs WHERE user_id = $userId AND punch_date BETWEEN '$start' AND '$end'");
echo "Attendance logs for April 2026:\n";
while($row = $res->fetch_assoc()) {
    echo $row['punch_date'] . ": " . $row['status'] . "\n";
}

$res = $con->query("SELECT start_date, end_date, status FROM leave_requests WHERE user_id = $userId AND status = 'Approved' AND (start_date <= '$end' AND end_date >= '$start')");
echo "Approved leaves for April 2026:\n";
while($row = $res->fetch_assoc()) {
    echo $row['start_date'] . " to " . $row['end_date'] . ": " . $row['status'] . "\n";
}
?>
