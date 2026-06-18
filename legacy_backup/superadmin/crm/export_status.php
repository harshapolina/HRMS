<?php
// export_status.php
session_start();
require '../config.php';
require_once __DIR__ . '/export_table_helper.php';

header('Content-Type: application/json');

$config = new Config();
$conn = $config->getConnection();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
try {
    ensureExportJobsTable($conn);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

$jobId = isset($_GET['jobId']) ? (int)$_GET['jobId'] : 0;
$token = $_GET['token'] ?? '';

if (!$jobId || !$token) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing jobId or token']);
    exit;
}

$stmt = $conn->prepare("SELECT id, status, file_name, file_path, expires_at, error FROM export_jobs WHERE id=? AND token=?");
$stmt->execute([$jobId, $token]);
$job = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$job) {
    http_response_code(404);
    echo json_encode(['error' => 'Job not found']);
    exit;
}


// Build base path dynamically from the current script location so it stays correct across hosts.
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/export_status.php'), '/\\');
if ($basePath === '') {
    $basePath = '/';
}


$response = [
    'jobId' => (int)$job['id'],
    'status' => $job['status'],
];

// Surface server-side error details when a job fails so the UI can show a meaningful message.
if ($job['status'] === 'failed' && isset($job['error']) && $job['error'] !== '') {
    $response['error'] = $job['error'];
}

if ($job['status'] === 'done') {
    $response['downloadUrl'] = $basePath . "/download_export.php?jobId={$job['id']}&token={$token}";
    $response['fileName'] = $job['file_name'];
}

echo json_encode($response);
