<?php
include '../config.php';

// Initialize database connection
$config = new Config();
$db = $config->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = trim($_POST['id']);

    if (empty($id)) {
        echo json_encode(["status" => "error", "message" => "Invalid API ID."]);
        exit;
    }

    // Delete the API from the database
    $deleteQuery = "DELETE FROM project_apis WHERE id = :id";
    $deleteStmt = $db->prepare($deleteQuery);
    $success = $deleteStmt->execute(['id' => $id]);

    if ($success) {
        echo json_encode(["status" => "success", "message" => "API deleted successfully."]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to delete API. Please try again."]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Invalid request method."]);
}
