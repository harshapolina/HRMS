<?php
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'hradminuser') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once 'config.php';
$config = new Config();
$con = $config->getConnection();

$employeeId = $_GET['employee_id'] ?? 0;
$date = $_GET['date'] ?? date('Y-m-d');

$start_date = $date . " 00:00:00";
$end_date = $date . " 23:59:59";

try {
    if (!$con) {
        echo json_encode([]);
        exit;
    }
    
    // Auto-repair the table if it was created with the wrong column name 'recorded_at'
    try {
        $con->exec("ALTER TABLE location_history CHANGE recorded_at captured_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
    } catch (Exception $e) {
        // Ignore if column doesn't exist or already renamed
    }

    $sql = "SELECT latitude, longitude, captured_at, accuracy 
            FROM location_history 
            WHERE user_id = :uid AND captured_at BETWEEN :start AND :end 
            ORDER BY captured_at ASC";
    
    $stmt = $con->prepare($sql);
    
    if (!$stmt) {
        // Table probably doesn't exist yet or query error
        echo json_encode([]);
        exit;
    }
    
    $stmt->execute([
        'uid' => $employeeId,
        'start' => $start_date,
        'end' => $end_date
    ]);
    
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($data);
} catch (Exception $e) {
    // If table doesn't exist or other error, just return empty array
    echo json_encode([]);
}
?>
