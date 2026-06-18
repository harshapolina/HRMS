<?php
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

require __DIR__ . '/../config.php';

function createLeadAssignmentNotification(PDO $conn, string $userCode, int $leadId, string $leadName, string $projectName, string $createdAt): void
{
    try {
        $title = 'New Lead Assigned';
        $displayLead = trim($leadName) !== '' ? $leadName : "#{$leadId}";
        $body = "Lead {$displayLead} has been assigned to you";
        if ($projectName !== '') {
            $body .= " for project {$projectName}";
        }
        $body .= '.';

        $meta = json_encode([
            'lead_id' => $leadId,
            'lead_name' => $leadName,
            'project' => $projectName,
            'source' => 'superadmin_cron',
        ]);

        $insNotification = $conn->prepare(
            'INSERT INTO notifications (title, body, url, type, icon, sender, meta, created_at)
             VALUES (:title, :body, :url, :type, :icon, :sender, :meta, :created_at)'
        );
        $insNotification->execute([
            ':title' => $title,
            ':body' => $body,
            ':url' => '/userlogin6/user_lead.php',
            ':type' => 'lead',
            ':icon' => 'fas fa-user-plus',
            ':sender' => 'system',
            ':meta' => $meta,
            ':created_at' => $createdAt,
        ]);

        $notificationId = (int)$conn->lastInsertId();
        if ($notificationId > 0) {
            $insReceipt = $conn->prepare(
                'INSERT INTO notification_receipts (notification_id, user_code, created_at)
                 VALUES (:nid, :uc, :created_at)'
            );
            $insReceipt->execute([
                ':nid' => $notificationId,
                ':uc' => $userCode,
                ':created_at' => $createdAt,
            ]);

            // External API Push Notification
            try {
                $apiKey = '533f4175837e145064605e15e12c7273f98746fec7459b0168af9394a22c6efab6bba75cce18a3555250e473f4907d22aaae3e3f12e46dd8ef22fac38737c537';
                $payload = [
                    "title" => $title,
                    "body" => $body,
                    "user_codes" => [$userCode],
                    "url" => "https://searchhomesindia.in"
                ];

                $ch = curl_init("https://notification.mnts.in/api/notify-users");
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    "Authorization: Bearer " . $apiKey,
                    "Content-Type: application/json"
                ]);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
                curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                curl_exec($ch);
                curl_close($ch);
            }
            catch (Exception $apiEx) {
                error_log("Superadmin CRM Assignment API error: " . $apiEx->getMessage());
            }
        }
    }
    catch (Exception $e) {
        error_log("createLeadAssignmentNotification error: " . $e->getMessage());
    }
}

try {
    $config = new Config();
    $conn = $config->getConnection();
    date_default_timezone_set('Asia/Kolkata');

    // Ensure last_run_at exists so interval starts after immediate assignment.
    try {
        $conn->query('SELECT last_run_at FROM cron_job LIMIT 1');
    } catch (Exception $e) {
        $conn->exec('ALTER TABLE cron_job ADD COLUMN last_run_at DATETIME NULL DEFAULT NULL');
    }

    $rawIds = isset($_POST['row_ids']) ? trim((string)$_POST['row_ids']) : '';
    $projectName = isset($_POST['project_name']) ? trim((string)$_POST['project_name']) : '';
    $location = isset($_POST['location']) ? trim((string)$_POST['location']) : '';
    $assignedUsersCsv = isset($_POST['assigned_user']) ? trim((string)$_POST['assigned_user']) : '';
    $intervalTime = isset($_POST['interval_time']) ? (int)$_POST['interval_time'] : 0;

    if ($rawIds === '' || $projectName === '' || $location === '' || $assignedUsersCsv === '' || $intervalTime < 1) {
        echo json_encode(['status' => 'error', 'message' => 'Missing or invalid input']);
        exit;
    }

    // Keep only valid numeric IDs and preserve order.
    $ids = array_filter(array_map('trim', explode(',', $rawIds)), function ($id) {
        return ctype_digit($id);
    });

    if (empty($ids)) {
        echo json_encode(['status' => 'error', 'message' => 'No valid lead IDs selected']);
        exit;
    }

    $ids = array_values(array_unique($ids));

    // Parse assigned users (comma-separated) and preserve chosen order.
    $assignedUsers = array_values(array_unique(array_filter(array_map('trim', explode(',', $assignedUsersCsv)))));
    if (empty($assignedUsers)) {
        echo json_encode(['status' => 'error', 'message' => 'At least one assigned user is required']);
        exit;
    }

    // Validate that all selected users exist.
    $placeholders = implode(',', array_fill(0, count($assignedUsers), '?'));
    $userCheck = $conn->prepare("SELECT tablename FROM accounts WHERE tablename IN ($placeholders)");
    $userCheck->execute($assignedUsers);
    $existingUsers = $userCheck->fetchAll(PDO::FETCH_COLUMN);
    $existingUsers = array_map('strval', $existingUsers);

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

    $assignedUsersCsv = implode(',', $assignedUsers);
    $firstAssignedUser = $assignedUsers[0];

    $now = date('Y-m-d H:i:s');
    $firstLeadId = array_shift($ids); // Pop first ID for immediate assignment.
    $remainingCsv = implode(',', $ids);
    $remainingCount = count($ids);

    $conn->beginTransaction();

    // 1) Immediate first assignment
    $leadStmt = $conn->prepare('SELECT name, project FROM shi_upload_data WHERE id = :id FOR UPDATE');
    $leadStmt->execute([':id' => $firstLeadId]);
    $leadRow = $leadStmt->fetch(PDO::FETCH_ASSOC);

    if (!$leadRow) {
        throw new Exception('First lead not found for immediate assignment');
    }

    $updLead = $conn->prepare(
        'UPDATE shi_upload_data
         SET assign_to_user = :user,
             project = :project,
             lead_count = IFNULL(lead_count, 0) + 1,
             updated_at = :t
         WHERE id = :id'
    );
    $updLead->execute([
        ':user' => $firstAssignedUser,
        ':project' => $projectName,
        ':t' => $now,
        ':id' => $firstLeadId,
    ]);

    $leadName = trim((string)($leadRow['name'] ?? ''));
    $remarkProject = $projectName;
    $insRemark = $conn->prepare(
        'INSERT INTO user_remarks (upload_data_id, user_unique_id, assign_project_name, created_at)
         VALUES (:id, :user, :proj, :t)'
    );
    $insRemark->execute([
        ':id' => $firstLeadId,
        ':user' => $firstAssignedUser,
        ':proj' => $remarkProject,
        ':t' => $now,
    ]);

    createLeadAssignmentNotification($conn, $firstAssignedUser, (int)$firstLeadId, $leadName, (string)$remarkProject, $now);

    // 2) Create cron row only for remaining leads.
    $cronJobId = null;
    if ($remainingCount > 0) {
        $insert = $conn->prepare(
            'INSERT INTO cron_job (
                row_id,
                assigned_user,
                project_name,
                source_lead,
                last_assigned_user,
                interval_time,
                location,
                last_run_at
            )
            VALUES (
                :row_id,
                :assigned_user,
                :project_name,
                :source_lead,
                :last_assigned_user,
                :interval_time,
                :location,
                :last_run_at
            )'
        );

        $insert->execute([
            ':row_id' => $remainingCsv,
            ':assigned_user' => $assignedUsersCsv,
            ':project_name' => $projectName,
            ':source_lead' => 'superadmin',
            ':last_assigned_user' => $firstAssignedUser,
            ':interval_time' => $intervalTime,
            ':location' => $location,
            ':last_run_at' => $now,
        ]);

        $cronJobId = $conn->lastInsertId();
    }

    $conn->commit();

    echo json_encode([
        'status' => 'success',
        'message' => $remainingCount > 0
            ? 'First lead assigned immediately. Cron job created for remaining leads.'
            : 'Lead assigned immediately. All selected leads are completed.',
        'cron_job_id' => $cronJobId,
        'first_assigned_lead_id' => $firstLeadId,
        'remaining_row_ids' => $remainingCsv,
        'remaining_count' => $remainingCount,
        'assigned_users' => $assignedUsersCsv,
        'last_assigned_user' => $firstAssignedUser,
    ]);
} catch (Exception $e) {
    if (isset($conn) && $conn instanceof PDO && $conn->inTransaction()) {
        $conn->rollBack();
    }
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Server error: ' . $e->getMessage()]);
}
