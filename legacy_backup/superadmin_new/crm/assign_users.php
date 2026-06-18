<?php
// Include database config
include '../config.php';

// Function to get currently assigned users for a given row
function getAssignedUsers($conn, $rowId) {
    $query = "SELECT user_unique_id FROM user_remarks WHERE upload_data_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$rowId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Function to update 'shi_upload_data' table with new and existing users
function updateShiUploadData($conn, $newAssignedUsers, $assignProjectName, $selectedIds) {
    foreach ($selectedIds as $id) {
        $query = "SELECT assign_to_user FROM shi_upload_data WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$id]);
        $existingUsers = $stmt->fetchColumn();

        // Merge old and new users and filter out empty values
        $allUsers = array_unique(array_filter(array_merge(explode(',', $existingUsers), $newAssignedUsers)));
        $allUsersStr = implode(',', $allUsers);

        $updateQuery = "UPDATE shi_upload_data 
                        SET assign_to_user = ?, assign_project_name = ? 
                        WHERE id = ?";
        $stmt = $conn->prepare($updateQuery);
        $stmt->execute([$allUsersStr, $assignProjectName, $id]);
    }
}

// Insert new users, remove unassigned users, and update project name in 'user_remarks'
function manageUserRemarks($conn, $assignedUsers, $selectedIds, $assignProjectName, $usersToRemove = []) {
    foreach ($selectedIds as $rowId) {
        $existingUsers = getAssignedUsers($conn, $rowId);
        $newUsers = array_diff($assignedUsers, $existingUsers);
        $usersToActuallyRemove = array_intersect($existingUsers, $usersToRemove);

        // Insert only new users with the project name
        if (!empty($newUsers)) {
            $insertRemarkQuery = "INSERT INTO user_remarks (upload_data_id, user_unique_id, status, remarks, assign_project_name)
                                  VALUES (?, ?, 'Pending', '', ?)";
            $stmt = $conn->prepare($insertRemarkQuery);
            foreach ($newUsers as $newUser) {
                $stmt->execute([$rowId, $newUser, $assignProjectName]);
            }
        }

        // Remove users if necessary
        if (!empty($usersToActuallyRemove)) {
            $deleteRemarkQuery = "DELETE FROM user_remarks WHERE upload_data_id = ? AND user_unique_id = ?";
            $stmt = $conn->prepare($deleteRemarkQuery);
            foreach ($usersToActuallyRemove as $userToRemove) {
                $stmt->execute([$rowId, $userToRemove]);
            }
        }
    }
}

// Handling POST request
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $selectedIds = explode(',', $_POST['selected_ids']);
    $assignedUsers = array_filter(explode(',', $_POST['users']));
    $assignProjectName = $_POST['assignprojectname'];
    $usersToRemove = isset($_POST['remove_users']) ? array_filter(explode(',', $_POST['remove_users'])) : [];

    $config = new Config();
    $conn = $config->getConnection();

    updateShiUploadData($conn, $assignedUsers, $assignProjectName, $selectedIds);
    manageUserRemarks($conn, $assignedUsers, $selectedIds, $assignProjectName, $usersToRemove);

    // Send response back as JSON without any redirection
    header('Content-Type: application/json'); // Ensure the response is in JSON format
    echo json_encode([
        'status' => 'success', 
        'message' => 'Assigned successfully'
    ]);
    exit();  // Terminate the script after sending the response
}

// AJAX call for retrieving assigned users
if (isset($_GET['row_id'])) {
    $config = new Config();
    $conn = $config->getConnection();
    $assignedUsers = getAssignedUsers($conn, $_GET['row_id']);
    echo json_encode(['assignedUsers' => $assignedUsers]);
    exit();
}
?>