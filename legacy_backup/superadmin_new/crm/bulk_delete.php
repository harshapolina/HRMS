<?php
header('Content-Type: application/json');
session_start();
include '../config.php';

$config = new Config();
$conn = $config->getConnection();

// ✅ 1. RECOVER LOGIC - Check this first
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['recover_id'])) {
    $recoverId = intval($_POST['recover_id']);
    $username = isset($_SESSION['username']) ? $_SESSION['username'] : 'system';

    // Fetch the lead from deleted_item
    $stmtFetch = $conn->prepare("SELECT * FROM deleted_item WHERE original_id = :id");
    $stmtFetch->execute([':id' => $recoverId]);
    $lead = $stmtFetch->fetch(PDO::FETCH_ASSOC);

    if ($lead) {
        // Insert back into shi_upload_data
        $stmtInsert = $conn->prepare("INSERT INTO shi_upload_data (
            id, name, email, number, location, source_of_lead, created_at, assign_to_user, status,
            project, assign_project_name, type, page_id, fb_created_time
        ) VALUES (
            :id, :name, :email, :number, :location, :source_of_lead, :created_at, :assign_to_user, :status,
            :project, :assign_project_name, :type, :page_id, :fb_created_time
        )");

        $stmtInsert->execute([
            ':id' => $lead['original_id'],
            ':name' => $lead['name'],
            ':email' => $lead['email'],
            ':number' => $lead['number'],
            ':location' => $lead['location'],
            ':source_of_lead' => $lead['source_of_lead'],
            ':created_at' => $lead['created_at'],
            ':assign_to_user' => $lead['assign_to_user'],
            ':status' => $lead['status'],
            ':project' => $lead['project'],
            ':assign_project_name' => $lead['assign_project_name'],
            ':type' => $lead['type'],
            ':page_id' => $lead['page_id'],
            ':fb_created_time' => $lead['fb_created_time']
        ]);

        // Update recovered flag in deleted_item
        $stmtUpdate = $conn->prepare("UPDATE deleted_item SET recovered = 1, recovered_at = NOW() WHERE original_id = :id");
        $stmtUpdate->execute([':id' => $recoverId]);

        echo json_encode(['status' => 'success', 'message' => 'Lead recovered successfully']);
        exit();
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Lead not found for recovery']);
        exit();
    }
}

// ✅ 1. permanent delete LOGIC - Check this first
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['permanent_delete_id'])) {

    $permanentDeleteId = intval($_POST['permanent_delete_id']);

    // Delete from deleted_item table
    $stmtDelete = $conn->prepare("DELETE FROM deleted_item WHERE original_id = :id");
    if ($stmtDelete->execute([':id' => $permanentDeleteId])) {
        echo json_encode(['status' => 'success', 'message' => 'Lead permanently deleted']);
        exit();
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to permanently delete lead']);
        exit();
    }
}

// ✅ 2. DELETE LOGIC - only runs if not recovering
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['row_ids'])) {
    $rowIds = json_decode($_POST['row_ids'], true);

    if (!empty($rowIds)) {
        $rowIds = array_map('intval', $rowIds);
        $rowIdsStr = implode(',', $rowIds);

        $deletedBy = isset($_SESSION['username']) ? $_SESSION['username'] : 'system';

        // Step 1: Fetch records to be deleted
        $selectQuery = "SELECT * FROM shi_upload_data WHERE id IN ($rowIdsStr)";
        $result = $conn->query($selectQuery);

        if ($result && $result->rowCount() > 0) {
            $leads = $result->fetchAll(PDO::FETCH_ASSOC);

            // Step 2: Insert into deleted_item
            $insertQuery = "INSERT INTO deleted_item (
                original_id, name, email, number, location, source_of_lead, created_at, assign_to_user, status,
                project, assign_project_name, type, page_id, fb_created_time,
                deleted_by, deleted_at, recovered, recovered_at
            ) VALUES (
                :original_id, :name, :email, :number, :location, :source_of_lead, :created_at, :assign_to_user, :status,
                :project, :assign_project_name, :type, :page_id, :fb_created_time,
                :deleted_by, NOW(), 0, NULL
            )";

            $stmtInsert = $conn->prepare($insertQuery);

            foreach ($leads as $lead) {
                $stmtInsert->execute([
                    ':original_id' => $lead['id'],
                    ':name' => $lead['name'],
                    ':email' => $lead['email'],
                    ':number' => $lead['number'],
                    ':location' => $lead['location'],
                    ':source_of_lead' => $lead['source_of_lead'],
                    ':created_at' => $lead['created_at'],
                    ':assign_to_user' => $lead['assign_to_user'],
                    ':status' => $lead['status'],
                    ':project' => $lead['project'],
                    ':assign_project_name' => $lead['assign_project_name'],
                    ':type' => $lead['type'],
                    ':page_id' => !empty($lead['page_id']) ? $lead['page_id'] : 'no id', // 👈 This handles missing page_id
                    ':fb_created_time' => $lead['fb_created_time'],
                    ':deleted_by' => $deletedBy
                ]);
            }

            // Step 3: Delete from original table
            $deleteQuery = "DELETE FROM shi_upload_data WHERE id IN ($rowIdsStr)";
            $stmtDelete = $conn->prepare($deleteQuery);

            if ($stmtDelete->execute()) {
                echo json_encode(['status' => 'success']);
                exit();
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Error deleting records']);
                exit();
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'No matching records found']);
            exit();
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No rows selected for deletion']);
        exit();
    }
}

echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
exit();
