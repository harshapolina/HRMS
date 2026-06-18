<?php
include '../config.php';

// Initialize database connection
$config = new Config();
$db = $config->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $api_id = $_POST['api_id'];
    $assign_user = trim($_POST['assign_user']);

    if (empty($api_id)) {
        echo json_encode(["status" => "error", "message" => "API ID is required."]);
        exit;
    }

    // Update the assigned users in the database
    $updateQuery = "UPDATE project_apis SET assign_user = :assign_user WHERE id = :id";
    $stmt = $db->prepare($updateQuery);
    $success = $stmt->execute([
        'assign_user' => $assign_user,
        'id' => $api_id,
    ]);

    if ($success) {
        echo json_encode(["status" => "success", "message" => "API updated successfully."]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to update API. Please try again."]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Invalid request method."]);
}
?>