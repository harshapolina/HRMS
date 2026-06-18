<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/includes/db_mysqli.php';

try {
    $con = hr_mysqli_connect();
    
    // Check tables
    echo "<h3>Original attendance_logs rows (First 50):</h3>";
    $result = $con->query("SELECT id, user_id, punch_date, punch_in, punch_out FROM attendance_logs ORDER BY id ASC LIMIT 50");
    if ($result) {
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
        echo "<tr><th>Log ID</th><th>User ID</th><th>Punch Date</th><th>Punch In</th><th>Punch Out</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . $row['user_id'] . "</td>";
            echo "<td>" . $row['punch_date'] . "</td>";
            echo "<td>" . $row['punch_in'] . "</td>";
            echo "<td>" . $row['punch_out'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "Failed to query attendance_logs: " . $con->error;
    }
    
    $con->close();
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage();
}
?>
