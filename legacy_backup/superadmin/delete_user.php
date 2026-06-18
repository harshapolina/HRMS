<?php
header('Content-Type: application/json');

try {

    $pdo = new PDO(
        "mysql:host=localhost;dbname=u797909128_demo",
        "u797909128_demoproject",
        "QK&0/aF@5",
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    if (!isset($_GET['id'])) {
        echo json_encode(["success" => false, "message" => "Missing ID"]);
        exit;
    }

    $id = (int) $_GET['id'];

    /* 🔥 MATCH BOSS LOGIC */
    $stmtFetch = $pdo->prepare("SELECT tablename FROM accounts WHERE id = :id");
    $stmtFetch->execute(['id' => $id]);
    $row = $stmtFetch->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(["success" => false, "message" => "User not found"]);
        exit;
    }

    $tablename = $row['tablename'];

    /* 🔥 DELETE ALERTS */
    $stmtAlerts = $pdo->prepare("DELETE FROM user_alerts WHERE user_id = :tablename");
    $stmtAlerts->execute(['tablename' => $tablename]);

    /* 🔥🔥🔥 CRITICAL FIX — DELETE DEPENDENT HISTORY */
    $stmtHistory = $pdo->prepare("DELETE FROM assign_user_history WHERE user_id = :id");
    $stmtHistory->execute(['id' => $id]);

    /* 🔥 DELETE USER */
    $stmtDelete = $pdo->prepare("DELETE FROM accounts WHERE id = :id");
    $stmtDelete->execute(['id' => $id]);

    echo json_encode(["success" => true]);

} catch (Exception $e) {

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
