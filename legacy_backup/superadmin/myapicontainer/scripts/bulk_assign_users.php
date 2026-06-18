<?php
session_start();
header('Content-Type: application/json');

require '../config.php';

// Initialize database connection
$config = new Config();
$conn = $config->getConnection();

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// Validate input parameters
if (!isset($_POST['api_ids']) || !isset($_POST['users'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters.']);
    exit;
}

$apiIds = $_POST['api_ids'];
$users = $_POST['users'];

// Validate api_ids is an array and not empty
if (!is_array($apiIds) || empty($apiIds)) {
    echo json_encode(['success' => false, 'message' => 'No API IDs provided.']);
    exit;
}

// Validate users is not empty
if (empty($users)) {
    echo json_encode(['success' => false, 'message' => 'No users selected.']);
    exit;
}

// Clean up the users string (it comes as comma-separated from the frontend)
$assignUsers = trim($users);

try {
    // Begin transaction
    $conn->beginTransaction();
    
    // Prepare update statement
    $updateQuery = "UPDATE project_apis SET assign_user = :assign_user WHERE id = :id";
    $stmt = $conn->prepare($updateQuery);
    
    $successCount = 0;
    $failCount = 0;
    
    // Update each API
    foreach ($apiIds as $apiId) {
        $apiId = trim($apiId);
        if (!empty($apiId)) {
            $success = $stmt->execute([
                'assign_user' => $assignUsers,
                'id' => $apiId
            ]);
            
            if ($success && $stmt->rowCount() > 0) {
                $successCount++;
            } else {
                $failCount++;
            }
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    if ($successCount > 0) {
        echo json_encode([
            'success' => true,
            'message' => "Successfully assigned users to {$successCount} API(s).",
            'updated_count' => $successCount,
            'failed_count' => $failCount
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No APIs were updated. Please try again.'
        ]);
    }
    
} catch (Exception $e) {
    // Rollback on error
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
