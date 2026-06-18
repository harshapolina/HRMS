<?php
// export_status.php
session_start();
require '../config.php';

header('Content-Type: application/json');

$config = new Config();
$conn = $config->getConnection();

$jobId = isset($_GET['jobId']) ? (int)$_GET['jobId'] : 0;
$token = $_GET['token'] ?? '';

if (!$jobId || !$token) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing jobId or token']);
    exit;
}

$stmt = $conn->prepare("SELECT id, status, file_name, file_path, expires_at FROM export_jobs WHERE id=? AND token=?");
$stmt->execute([$jobId, $token]);
$job = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$job) {
    http_response_code(404);
    echo json_encode(['error' => 'Job not found']);
    exit;
}

$response = [
    'jobId' => (int)$job['id'],
    'status' => $job['status']
];

if ($job['status'] === 'done') {
    $response['downloadUrl'] = "/superadmin_new/crm/download_export.php?jobId={$job['id']}&token={$token}";
    $response['fileName'] = $job['file_name'];
}

echo json_encode($response);
