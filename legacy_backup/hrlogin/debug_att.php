<?php
$DATABASE_HOST = 'localhost';
$DATABASE_USER = 'u797909128_demoproject';
$DATABASE_PASS = 'QK&0/aF@5';
$DATABASE_NAME = 'u797909128_demo';

$con = mysqli_connect($DATABASE_HOST, $DATABASE_USER, $DATABASE_PASS, $DATABASE_NAME);
if (mysqli_connect_errno()) {
    exit('Failed to connect to MySQL: ' . mysqli_connect_error());
}

$user_id_koushik = 5;
$user_id_uname41 = 1; 

$sql = "SELECT user_id, punch_date, status FROM attendance_logs WHERE user_id IN (1, 5) AND MONTH(punch_date) = 4 AND YEAR(punch_date) = 2026 ORDER BY user_id, punch_date";
$res = $con->query($sql);

echo "User ID | Date | Status\n";
echo "-----------------------\n";
while($row = $res->fetch_assoc()) {
    echo $row['user_id'] . " | " . $row['punch_date'] . " | " . $row['status'] . "\n";
}
?>
