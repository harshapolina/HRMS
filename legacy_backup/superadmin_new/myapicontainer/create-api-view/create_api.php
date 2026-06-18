<?php
include '../config.php';

// Initialize database connection
$config = new Config();
$db = $config->getConnection();

// Make sure to always return JSON
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "Invalid request method."]);
    exit;
}

// Gather & sanitize inputs
$project_name_raw = isset($_POST['project_name']) ? trim($_POST['project_name']) : null;
$assign_user_raw  = isset($_POST['assign_user']) ? trim($_POST['assign_user']) : '';
$lead_source_raw  = isset($_POST['source_name']) ? trim($_POST['source_name']) : null;
$type             = ($lead_source_raw === 'group') ? 'group' : 'other';
$group_name_raw   = isset($_POST['group_name']) ? trim($_POST['group_name']) : null;

// For groups, project_name should be set to group_name
if ($type === 'group') {
    $project_name = $group_name_raw !== '' ? $group_name_raw : null;
} else {
    $project_name = $project_name_raw !== '' ? $project_name_raw : null;
}

$assign_user = $assign_user_raw !== '' ? $assign_user_raw : null;
$lead_source = $lead_source_raw !== '' ? $lead_source_raw : null;
$group_name  = $group_name_raw !== '' ? $group_name_raw : null;

// Basic server-side validation
if (empty($lead_source)) {
    echo json_encode(["status" => "error", "message" => "Lead source is required."]);
    exit;
}

if ($type === 'group') {
    if (empty($group_name)) {
        echo json_encode(["status" => "error", "message" => "Group name is required for type 'group'."]);
        exit;
    }
} else {
    if (empty($project_name)) {
        echo json_encode(["status" => "error", "message" => "Project name is required."]);
        exit;
    }
}

// You required assign_user earlier - keep that requirement if desired
if (empty($assign_user)) {
    echo json_encode(["status" => "error", "message" => "Assign user(s) are required."]);
    exit;
}

// Generate a unique API key (safe fallback)
try {
    $api_key = bin2hex(random_bytes(16));
} catch (Exception $e) {
    // fallback
    $api_key = bin2hex(openssl_random_pseudo_bytes(16));
}

// Duplicate check depending on type
try {
    if ($type === 'group') {
        // Check same type 'group' + same group_name + same lead_source
        $checkQuery = "SELECT id FROM project_apis WHERE type = 'group' AND group_name = :group_name AND lead_source = :lead_source LIMIT 1";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->execute([
            ':group_name' => $group_name,
            ':lead_source' => $lead_source
        ]);
    } else {
        // For normal APIs, check project_name + lead_source and ensure it's not a row marked as 'group'
        $checkQuery = "SELECT id FROM project_apis WHERE (type != 'group' OR type IS NULL) AND project_name = :project_name AND lead_source = :lead_source LIMIT 1";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->execute([
            ':project_name' => $project_name,
            ':lead_source'  => $lead_source
        ]);
    }

    if ($checkStmt->rowCount() > 0) {
        echo json_encode([
            "status" => "error",
            "message" => $type === 'group' ? "This group already exists for the selected lead source." : "This project name already exists for the selected lead source."
        ]);
        exit;
    }

    // Insert new API details
    $insertQuery = "INSERT INTO project_apis (project_name, api_key, assign_user, lead_source, type, group_name)
                    VALUES (:project_name, :api_key, :assign_user, :lead_source, :type, :group_name)";

    $insertStmt = $db->prepare($insertQuery);
    $success = $insertStmt->execute([
        ':project_name' => $project_name,
        ':api_key'      => $api_key,
        ':assign_user'  => $assign_user,
        ':lead_source'  => $lead_source,
        ':type'         => $type,
        ':group_name'   => $group_name
    ]);

    if ($success) {
        $lastInsertId = $db->lastInsertId();
        echo json_encode([
            "status" => "success",
            "message" => $type === 'group' ? "Group created successfully." : "API created successfully.",
            "api_details" => [
                "id" => $lastInsertId,
                "project_name" => $project_name,
                "group_name" => $group_name,
                "api_key" => $api_key,
                "assign_user" => $assign_user,
                "lead_source" => $lead_source,
                "type" => $type
            ]
        ]);
        exit;
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to create. Please try again."]);
        exit;
    }

} catch (PDOException $ex) {
    // Log $ex->getMessage() to your error log (do not echo raw DB errors in production)
    error_log('DB error in create-api.php: ' . $ex->getMessage());
    echo json_encode(["status" => "error", "message" => "Database error occurred."]);
    exit;
}
