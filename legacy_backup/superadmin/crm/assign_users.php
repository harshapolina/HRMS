<?php
include '../config.php';

function getAssignedUsers(PDO $conn, string $rowId): array
{
    $query = "SELECT user_unique_id FROM user_remarks WHERE upload_data_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$rowId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $rawIds = isset($_POST['selected_ids']) ? trim((string)$_POST['selected_ids']) : '';
    $rawUsers = isset($_POST['users']) ? trim((string)$_POST['users']) : '';
    $assignProjectName = isset($_POST['assignprojectname']) ? trim((string)$_POST['assignprojectname']) : '';

    if ($rawIds === '' || $rawUsers === '') {
        echo json_encode(['status' => 'error', 'message' => 'Missing lead IDs or assigned users']);
        exit;
    }

    $selectedIds = array_values(array_unique(array_filter(array_map('trim', explode(',', $rawIds)), function ($id) {
        return ctype_digit($id);
    })));
    $assignedUsers = array_values(array_unique(array_filter(array_map('trim', explode(',', $rawUsers)))));

    if (empty($selectedIds)) {
        echo json_encode(['status' => 'error', 'message' => 'No valid lead IDs selected']);
        exit;
    }

    if (empty($assignedUsers)) {
        echo json_encode(['status' => 'error', 'message' => 'At least one assigned user is required']);
        exit;
    }

    $config = new Config();
    $conn = $config->getConnection();
    date_default_timezone_set('Asia/Kolkata');

    $placeholders = implode(',', array_fill(0, count($assignedUsers), '?'));
    $userCheck = $conn->prepare("SELECT tablename FROM accounts WHERE tablename IN ($placeholders)");
    $userCheck->execute($assignedUsers);
    $existingUsers = array_map('strval', $userCheck->fetchAll(PDO::FETCH_COLUMN));

    $invalidUsers = array_values(array_filter($assignedUsers, function ($u) use ($existingUsers) {
        return !in_array($u, $existingUsers, true);
    }));

    if (!empty($invalidUsers)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Assigned users not found: ' . implode(', ', $invalidUsers),
        ]);
        exit;
    }

    $now = date('Y-m-d H:i:s');
    $totalLeads = count($selectedIds);
    $totalUsers = count($assignedUsers);

    $leadQuery = $conn->prepare('SELECT name, project FROM shi_upload_data WHERE id = :id FOR UPDATE');
    $updLead = $conn->prepare(
        'UPDATE shi_upload_data
         SET assign_to_user = :user,
             assign_project_name = :assign_project,
             project = :project,
             lead_count = IFNULL(lead_count, 0) + 1,
             updated_at = :t
         WHERE id = :id'
    );
    $insRemark = $conn->prepare(
        'INSERT INTO user_remarks (upload_data_id, user_unique_id, status, remarks, assign_project_name, created_at)
         VALUES (:id, :user, :status, :remarks, :proj, :t)'
    );

    try {
        $conn->beginTransaction();

        foreach ($selectedIds as $idx => $leadId) {
            $assignedUser = $assignedUsers[$idx % $totalUsers];

            $leadQuery->execute([':id' => $leadId]);
            $leadRow = $leadQuery->fetch(PDO::FETCH_ASSOC);
            if (!$leadRow) {
                continue;
            }

            $effectiveProject = $assignProjectName !== ''
                ? $assignProjectName
                : trim((string)($leadRow['project'] ?? ''));

            $updLead->execute([
                ':user' => $assignedUser,
                ':assign_project' => $effectiveProject,
                ':project' => $effectiveProject,
                ':t' => $now,
                ':id' => $leadId,
            ]);

            $insRemark->execute([
                ':id' => $leadId,
                ':user' => $assignedUser,
                ':status' => 'Pending',
                ':remarks' => '',
                ':proj' => $effectiveProject,
                ':t' => $now,
            ]);
        }

        $conn->commit();

        echo json_encode([
            'status' => 'success',
            'message' => "Assigned {$totalLeads} lead(s)",
        ]);
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Server error: ' . $e->getMessage()]);
    }
    exit;
}

if (isset($_GET['row_id'])) {
    $config = new Config();
    $conn = $config->getConnection();
    $assignedUsers = getAssignedUsers($conn, (string)$_GET['row_id']);
    echo json_encode(['assignedUsers' => $assignedUsers]);
    exit;
}
?>