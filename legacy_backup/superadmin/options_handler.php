<?php
$allowedTypes = ['developera', 'bprojecta', 'tprojecta'];
$type = $_REQUEST['type'] ?? '';
$type = preg_replace('/[^a-z]/', '', strtolower($type)); // Basic sanitation

if (!in_array($type, $allowedTypes)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid type']);
    exit;
}

$dir = realpath(__DIR__ . '/../userlogin/options');

$file = "$dir/{$type}.txt";

// Ensure folder exists
if (!file_exists($dir)) mkdir($dir, 0755, true);

$action = $_REQUEST['action'] ?? 'get';

if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $value = trim($_POST['value'] ?? '');

    if ($value === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Value is empty']);
        exit;
    }

    // Read existing values
    $existing = file_exists($file) ? file($file, FILE_IGNORE_NEW_LINES) : [];

    // Save if not duplicate
    if (!in_array($value, $existing)) {
        file_put_contents($file, $value . PHP_EOL, FILE_APPEND);
    }

    echo json_encode(['success' => true]);
    exit;

} elseif ($action === 'get') {
    $options = file_exists($file) ? file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
    echo json_encode(array_unique($options));
    exit;

} else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid action']);
    exit;
}
