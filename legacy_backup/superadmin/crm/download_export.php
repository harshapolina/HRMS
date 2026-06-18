<?php
// download_export.php
require '../config.php';
require_once __DIR__ . '/export_table_helper.php';
session_start();

$config = new Config();
$conn = $config->getConnection();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
try {
    ensureExportJobsTable($conn);
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Export table not available: ' . $e->getMessage();
    exit;
}

$jobId = isset($_GET['jobId']) ? (int)$_GET['jobId'] : 0;
$token = $_GET['token'] ?? '';

if (!$jobId || !$token) {
    http_response_code(400);
    echo "Invalid request";
    exit;
}

$stmt = $conn->prepare("SELECT file_path, file_name, expires_at FROM export_jobs WHERE id=? AND token=? AND status='done'");
$stmt->execute([$jobId, $token]);
$job = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$job) {
    http_response_code(404);
    echo "File not ready or invalid token";
    exit;
}

if (!empty($job['expires_at']) && strtotime($job['expires_at']) < time()) {
    http_response_code(410);
    echo "File expired";
    exit;
}

$file = $job['file_path'];
if (!file_exists($file)) {
    http_response_code(404);
    echo "File not found";
    exit;
}

$filename = $job['file_name'] ?: basename($file);
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="'.basename($filename).'"');
header('Content-Length: ' . filesize($file));
readfile($file);
exit;
