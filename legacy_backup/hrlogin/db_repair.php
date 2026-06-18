<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/includes/db_mysqli.php';

try {
    $con = hr_mysqli_connect();
    echo "<h3>Database Connected Successfully</h3>";
    
    // Attempt to add the column directly
    echo "Attempting to add column 'today_date' to 'user_attendance' table...<br>";
    
    $query = "ALTER TABLE user_attendance ADD COLUMN today_date DATE DEFAULT NULL AFTER user_id";
    if ($con->query($query)) {
        echo "<span style='color: green; font-weight: bold;'>SUCCESS: today_date column added successfully!</span><br>";
    } else {
        echo "<span style='color: red; font-weight: bold;'>ERROR: " . $con->error . "</span> (Error Code: " . $con->errno . ")<br>";
    }
    
    // Show table structure
    echo "<h4>Current structure of user_attendance:</h4>";
    $result = $con->query("DESCRIBE user_attendance");
    if ($result) {
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Default']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Extra']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "Failed to describe table: " . $con->error;
    }
    
    $con->close();
} catch (Throwable $e) {
    echo "<h3>Fatal Error: " . $e->getMessage() . "</h3>";
}
?>
