<?php
// leads_cron.php – DB (cron_job) → Lead Update → Round-Robin

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "=== CRON STARTED === <br>";

require __DIR__ . '/../../../config.php';

function createLeadAssignmentNotification(PDO $db, string $userCode, int $leadId, string $leadName, string $projectName, string $createdAt): void
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

        $insNotification = $db->prepare(
            "INSERT INTO notifications (title, body, url, type, icon, sender, meta, created_at)
             VALUES (:title, :body, :url, :type, :icon, :sender, :meta, :created_at)"
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

        $notificationId = (int)$db->lastInsertId();
        if ($notificationId > 0) {
            $insReceipt = $db->prepare(
                "INSERT INTO notification_receipts (notification_id, user_code, created_at)
                 VALUES (:nid, :uc, :created_at)"
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
                error_log("Leads Cron Assignment API error: " . $apiEx->getMessage());
            }
        }
    }
    catch (Exception $e) {
        error_log("createLeadAssignmentNotification error: " . $e->getMessage());
    }
}

$config = new Config();
$db = $config->getConnection();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

ini_set('max_execution_time', 0);
set_time_limit(0);
date_default_timezone_set('Asia/Kolkata');

// Schema checks for interval and round-robin tracking.
try {
    $db->query("SELECT last_run_at FROM cron_job LIMIT 1");
} catch (Exception $e) {
    $db->exec("ALTER TABLE cron_job ADD COLUMN last_run_at DATETIME NULL DEFAULT NULL");
}

try {
    $db->query("SELECT last_assigned_user FROM cron_job LIMIT 1");
} catch (Exception $e) {
    $db->exec("ALTER TABLE cron_job ADD COLUMN last_assigned_user VARCHAR(255) NULL DEFAULT NULL");
}

$lockStmt = $db->query("SELECT GET_LOCK('shi_leads_cron_lock', 0)");
$gotLock = (int)$lockStmt->fetchColumn();
if ($gotLock !== 1) {
    echo "Cron already running.<br>";
    exit;
}

try {
    $jobsStmt = $db->query(
        "SELECT *
         FROM cron_job
         WHERE row_id IS NOT NULL
           AND TRIM(row_id) != ''
         ORDER BY id ASC"
    );
    $jobs = $jobsStmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$jobs) {
        echo "No active cron jobs with pending leads.<br>";
        exit;
    }

    $nowTs = time();

    foreach ($jobs as $job) {
        $leadIds = array_values(array_filter(array_map('trim', explode(',', (string)$job['row_id']))));
        if (empty($leadIds)) {
            continue;
        }

        $intervalMinutes = max(1, (int)($job['interval_time'] ?? 0));
        $intervalSeconds = $intervalMinutes * 60;
        $lastRunTs = !empty($job['last_run_at']) ? strtotime((string)$job['last_run_at']) : false;

        if ($lastRunTs !== false && ($nowTs - $lastRunTs) < $intervalSeconds) {
            continue;
        }

        // If script was delayed, process multiple due leads in one run.
        $dueRuns = 1;
        if ($lastRunTs !== false) {
            $dueRuns = (int)floor(($nowTs - $lastRunTs) / $intervalSeconds);
            $dueRuns = max(1, $dueRuns);
        }
        $dueRuns = min($dueRuns, count($leadIds));

        $selectedUsers = array_values(array_unique(array_filter(array_map('trim', explode(',', (string)($job['assigned_user'] ?? ''))))));
        if (empty($selectedUsers)) {
            echo "Job ID {$job['id']}: No assigned users configured. Skipping.<br>";
            continue;
        }

        $initialAssignedCode = $selectedUsers[0];
        $lastAssignedCode = trim((string)($job['last_assigned_user'] ?? ''));

        $startIndex = 0;
        foreach ($selectedUsers as $index => $userCode) {
            if ($userCode === $initialAssignedCode) {
                $startIndex = $index;
                break;
            }
        }

        $rotatedUsers = array_merge(
            array_slice($selectedUsers, $startIndex),
            array_slice($selectedUsers, 0, $startIndex)
        );

        $processedForJob = 0;

        try {
            $db->beginTransaction();

            for ($i = 0; $i < $dueRuns; $i++) {
                if (empty($leadIds)) {
                    break;
                }

                $targetLeadId = array_shift($leadIds);

                $nextUserCode = null;
                if ($lastAssignedCode === '') {
                    $nextUserCode = (string)($rotatedUsers[0] ?? '');
                } else {
                    $lastIndex = -1;
                    foreach ($rotatedUsers as $idx => $userCode) {
                        if ((string)$userCode === $lastAssignedCode) {
                            $lastIndex = $idx;
                            break;
                        }
                    }

                    if ($lastIndex === -1) {
                        $nextUserCode = (string)($rotatedUsers[0] ?? '');
                    } else {
                        $nextIndex = ($lastIndex + 1) % count($rotatedUsers);
                        $nextUserCode = (string)($rotatedUsers[$nextIndex] ?? '');
                    }
                }

                $nextUserCode = trim($nextUserCode);
                if ($nextUserCode === '') {
                    continue;
                }

                $leadQuery = $db->prepare("SELECT name, project FROM shi_upload_data WHERE id = :id FOR UPDATE");
                $leadQuery->execute([':id' => $targetLeadId]);
                $lead = $leadQuery->fetch(PDO::FETCH_ASSOC);

                if (!$lead) {
                    echo "Job ID {$job['id']}: Lead #{$targetLeadId} not found. Skipping.<br>";
                    continue;
                }

                $nowSql = date('Y-m-d H:i:s');
                $leadName = trim((string)($lead['name'] ?? ''));
                $projName = trim((string)($job['project_name'] ?? ''));
                if ($projName === '') {
                    $projName = trim((string)($lead['project'] ?? ''));
                }

                $updLead = $db->prepare(
                    "UPDATE shi_upload_data
                     SET assign_to_user = :user,
                         project = :project,
                         lead_count = IFNULL(lead_count, 0) + 1,
                         updated_at = :t
                     WHERE id = :id"
                );
                $updLead->execute([
                    ':user' => $nextUserCode,
                    ':project' => $projName,
                    ':t' => $nowSql,
                    ':id' => $targetLeadId,
                ]);

                $insRemark = $db->prepare(
                    "INSERT INTO user_remarks (upload_data_id, user_unique_id, assign_project_name, created_at)
                     VALUES (:id, :user, :proj, :t)"
                );
                $insRemark->execute([
                    ':id' => $targetLeadId,
                    ':user' => $nextUserCode,
                    ':proj' => $projName,
                    ':t' => $nowSql,
                ]);

                createLeadAssignmentNotification($db, $nextUserCode, (int)$targetLeadId, $leadName, (string)$projName, $nowSql);

                $lastAssignedCode = $nextUserCode;
                $processedForJob++;
                echo "Job ID {$job['id']}: Assigned Lead #{$targetLeadId} to {$nextUserCode}<br>";
            }

            if ($processedForJob > 0) {
                $newLastRunAtTs = $lastRunTs !== false
                    ? ($lastRunTs + ($processedForJob * $intervalSeconds))
                    : $nowTs;
                $newLastRunAt = date('Y-m-d H:i:s', $newLastRunAtTs);

                if (empty($leadIds)) {
                    $delCron = $db->prepare("DELETE FROM cron_job WHERE id = :id");
                    $delCron->execute([':id' => $job['id']]);
                    echo "Job ID {$job['id']}: Completed and removed from cron_job.<br>";
                } else {
                    $updCron = $db->prepare(
                        "UPDATE cron_job
                         SET row_id = :remaining,
                             last_assigned_user = :lastUser,
                             last_run_at = :t
                         WHERE id = :id"
                    );
                    $updCron->execute([
                        ':remaining' => implode(',', $leadIds),
                        ':lastUser' => $lastAssignedCode,
                        ':t' => $newLastRunAt,
                        ':id' => $job['id'],
                    ]);
                }
            }

            $db->commit();
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            echo "Job ID {$job['id']} Error: " . $e->getMessage() . "<br>";
        }
    }
} finally {
    $db->query("SELECT RELEASE_LOCK('shi_leads_cron_lock')");
}

echo "=== CRON FINISHED === <br>";
?>