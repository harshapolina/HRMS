<?php
session_start();
require_once 'config.php';

// Ensure the user is logged in
if (!isset($_SESSION['tablename'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

$user_id = $_SESSION['tablename'];

// Decode the JSON POST request
$input = json_decode(file_get_contents('php://input'), true);

if (isset($input['alert_id'])) {
    $alert_id = $input['alert_id'];

    $config = new Config();
    $conn = $config->getConnection();

    // Check if the entry already exists
    $checkQuery = "SELECT COUNT(*) FROM user_alerts WHERE user_id = :user_id AND alert_id = :alert_id";
    $stmt = $conn->prepare($checkQuery);
    $stmt->bindValue(':user_id', $user_id, PDO::PARAM_STR);
    $stmt->bindValue(':alert_id', $alert_id, PDO::PARAM_INT);
    $stmt->execute();
    $exists = $stmt->fetchColumn();

    if ($exists) {
        // Update if it exists
        $updateQuery = "UPDATE user_alerts SET alert_accepted = 1 WHERE user_id = :user_id AND alert_id = :alert_id";
        $stmt = $conn->prepare($updateQuery);
    } else {
        // Insert if it doesn't exist
        $insertQuery = "INSERT INTO user_alerts (user_id, alert_id, alert_accepted) VALUES (:user_id, :alert_id, 1)";
        $stmt = $conn->prepare($insertQuery);
    }

    $stmt->bindValue(':user_id', $user_id, PDO::PARAM_STR);
    $stmt->bindValue(':alert_id', $alert_id, PDO::PARAM_INT);

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database operation failed']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Alert ID not provided']);
}
?>