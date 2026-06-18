<?php
// Include database config
include 'config.php';

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get selected row IDs, assigned users, and project name from the form
    $selectedIds = explode(',', $_POST['selected_ids']);  // Selected row IDs
    $assignedUsers = $_POST['users'];  // Selected users (useruniqueid)
    $assignProjectName = $_POST['assignprojectname']; // Project name to assign

    // Create an instance of Config class
    $config = new Config();
    $conn = $config->getConnection();

    // Prepare the update query with the additional column for 'assign_project_name'
    $updateQuery = "UPDATE shi_upload_data 
                    SET assign_to_user = ?, assign_project_name = ?
                    WHERE id IN (" . implode(',', array_fill(0, count($selectedIds), '?')) . ")";

    $stmt = $conn->prepare($updateQuery);

    // Bind the 'assign_to_user' and 'assign_project_name' values
    $assignedUserStr = implode(',', $assignedUsers);
    $stmt->bindValue(1, $assignedUserStr);
    $stmt->bindValue(2, $assignProjectName);

    // Bind the selected row IDs (starting from position 3)
    foreach ($selectedIds as $index => $id) {
        $stmt->bindValue($index + 3, $id);  // Starts from position 3
    }

    // Execute the update query
    $stmt->execute();

    // Redirect back to the main page with a success message
    header("Location: upload_data.php?status=Assigned successfully");
    exit();
}
?>