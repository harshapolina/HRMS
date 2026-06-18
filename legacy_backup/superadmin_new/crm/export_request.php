<?php
// export_request.php
session_start();
require '../config.php';
require 'export_queries.php';

header('Content-Type: application/json');

$config = new Config();
$conn = $config->getConnection();
$useruniqueId = $_SESSION['tablename'] ?? 'guest';

try {
    // Build params (support GET or POST)
    $params = [];
    $params['page'] = $_REQUEST['page'] ?? null;
    $params['rowsPerPage'] = $_REQUEST['rowsPerPage'] ?? null;
    $params['searchQuery'] = $_REQUEST['searchQuery'] ?? null;
    $params['startDate'] = $_REQUEST['startDate'] ?? null;
    $params['endDate'] = $_REQUEST['endDate'] ?? null;
    $params['showDeletedOnly'] = $_REQUEST['showDeletedOnly'] ?? null;
    $params['currentFilter'] = $_REQUEST['currentFilter'] ?? null;

    // multiFilters can be a JSON string or an array (from GET/POST). Normalize it.
    $multi = [];
    if (isset($_REQUEST['multiFilters'])) {
        $raw = $_REQUEST['multiFilters'];
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $multi = ($decoded !== null) ? $decoded : [];
        } elseif (is_array($raw)) {
            $multi = $raw;
        } else {
            $multi = [];
        }
    }
    $params['multiFilters'] = $multi;

    // Create job
    $token = bin2hex(random_bytes(16));
    $expiresAt = (new DateTime('+24 hours'))->format('Y-m-d H:i:s');

    $insert = $conn->prepare("INSERT INTO export_jobs (user_id, token, params, status, expires_at) VALUES (?, ?, ?, 'pending', ?)");
    $insert->execute([$useruniqueId, $token, json_encode($params), $expiresAt]);
    $jobId = $conn->lastInsertId();

    echo json_encode([
        'jobId' => (int)$jobId,
        'token' => $token,
        'status' => 'pending'
    ]);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}
