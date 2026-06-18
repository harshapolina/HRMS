<?php
require_once __DIR__ . '/includes/db_mysqli.php';
try {
    $con = hr_mysqli_connect();
} catch (Throwable $e) {
    exit('Failed to connect to MySQL: ' . $e->getMessage());
}

$sql = "CREATE TABLE IF NOT EXISTS location_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    latitude DECIMAL(10,8),
    longitude DECIMAL(11,8),
    accuracy FLOAT,
    recorded_at DATETIME,
    session_id VARCHAR(100),
    INDEX(user_id, recorded_at)
)";

if (mysqli_query($con, $sql)) {
    echo "Table created successfully.";
} else {
    echo "Error creating table: " . mysqli_error($con);
}
mysqli_close($con);
?>
