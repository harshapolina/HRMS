<?php
// mark-read.php
include '../config.php';
header('Content-Type: application/json');
session_start();

$user_code = $_SESSION['tablename'] ?? null;
if (!$user_code) {
    http_response_code(401);
    echo json_encode(['status'=>'error','message'=>'User not authenticated']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$ids = $data['notification_ids'] ?? [];

if (!is_array($ids) || empty($ids)) {
    http_response_code(400);
    echo json_encode(['status'=>'error','message'=>'notification_ids required (array)']);
    exit;
}

$config = new Config();
$db = $config->getConnection();

try {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    // params: read_at, user_code, ...ids
    $params = array_merge([date('Y-m-d H:i:s'), $user_code], $ids);
    $sql = "UPDATE notification_receipts
            SET is_read = 1, read_at = ?
            WHERE user_code = ? AND notification_id IN ($placeholders) AND is_read = 0";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    echo json_encode(['status'=>'success','marked' => $stmt->rowCount()]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
}
