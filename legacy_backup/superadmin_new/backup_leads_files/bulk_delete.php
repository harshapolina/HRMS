<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['row_ids'])) {
    include 'config.php';

    // Get the selected row IDs
    $rowIds = $_POST['row_ids'];

    if (!empty($rowIds)) {
        $rowIds = array_map('intval', $rowIds); // Sanitize input
        $rowIdsStr = implode(',', $rowIds); // Convert to string for query

        // Create an instance of Config class
        $config = new Config();
        $conn = $config->getConnection();

        // Prepare the delete query
        $deleteQuery = "DELETE FROM shi_upload_data WHERE id IN ($rowIdsStr)";
        $stmt = $conn->prepare($deleteQuery);

        // Execute the query
        if ($stmt->execute()) {
            // Redirect with a success message
            header("Location: /superadmin_new/upload_data?status=Deleted successfully");
            exit();
        } else {
            // Redirect with an error message
            header("Location: /superadmin_new/upload_data?status=Error deleting records");
            exit();
        }
    } else {
        // Redirect if no rows were selected
        header("Location: /superadmin_new/upload_data?status=No rows selected for deletion");
        exit();
    }
} else {
    // Redirect if the request method is not POST or no row_ids[] are sent
    header("Location: /superadmin_new/upload_data?status=Invalid request");
    exit();
}
