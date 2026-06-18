<?php
include '../config.php';
header('Content-Type: application/json');
session_start();

$user_code = $_SESSION['tablename'] ?? null;
if (!$user_code) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'User not authenticated']);
    exit;
}

$config = new Config();
$db = $config->getConnection();

function ensureNotificationReadColumns(PDO $db): void
{
    $check = $db->prepare("SHOW COLUMNS FROM notification_receipts LIKE :col");

    $check->execute([':col' => 'is_read']);
    if (!$check->fetch(PDO::FETCH_ASSOC)) {
        $db->exec("ALTER TABLE notification_receipts ADD COLUMN is_read TINYINT(1) NOT NULL DEFAULT 0");
    }

    $check->execute([':col' => 'read_at']);
    if (!$check->fetch(PDO::FETCH_ASSOC)) {
        $db->exec("ALTER TABLE notification_receipts ADD COLUMN read_at DATETIME NULL");
    }
}

try {
    ensureNotificationReadColumns($db);

    $sql = 'UPDATE notification_receipts SET is_read = 1, read_at = :read_at WHERE user_code = :uc AND is_read = 0';
    $stmt = $db->prepare($sql);
    $stmt->execute([':read_at' => date('Y-m-d H:i:s'), ':uc' => $user_code]);
    echo json_encode(['status' => 'success', 'marked' => $stmt->rowCount()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
