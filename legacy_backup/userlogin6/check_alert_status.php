<?php
    session_start();
    require_once 'config.php';

    $user_id = $_SESSION['tablename']; // Unique user ID from session

    $config = new Config();
    $conn = $config->getConnection();

    // Fetch the latest alert
    $query = "SELECT a.alert_id, a.alert_message 
            FROM alerts a 
            LEFT JOIN user_alerts ua 
            ON a.alert_id = ua.alert_id AND ua.user_id = :user_id 
            WHERE ua.alert_accepted IS NULL 
            ORDER BY a.created_at DESC 
            LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bindValue(':user_id', $user_id, PDO::PARAM_STR);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        echo json_encode(['alert_accepted' => false, 'alert_message' => $result['alert_message'], 'alert_id' => $result['alert_id']]);
    } else {
        echo json_encode(['alert_accepted' => true]);
    }
?>