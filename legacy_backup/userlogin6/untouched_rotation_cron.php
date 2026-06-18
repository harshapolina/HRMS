<?php
/**
 * untouched_rotation_cron.php
 *
 * Auto-rotates untouched Pending leads every 30 minutes.
 * Rotation scope: direct team under the lead owner's immediate manager.
 * Stops rotating as soon as the lead is updated by any user.
 *
 * Suggested scheduler:
 * *\/5 * * * * php /path/to/userlogin6/untouched_rotation_cron.php
 */

require_once __DIR__ . '/config.php';

date_default_timezone_set('Asia/Kolkata');

$config = new Config();
$conn = $config->getConnection();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$logFile = __DIR__ . '/notifications/untouched_rotation_cron.log';
$runLimit = 300;

function logShift($msg)
{
    global $logFile;
    $date = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$date] $msg\n", FILE_APPEND);
    echo "[$date] $msg\n";
}

function ensureRotationColumns(PDO $conn)
{
    try {
        $conn->exec("ALTER TABLE user_remarks ADD COLUMN last_auto_shift_at DATETIME NULL");
        logShift('Added column user_remarks.last_auto_shift_at');
    } catch (PDOException $e) {
        if ($e->getCode() !== '42S21') {
            throw $e;
        }
    }

    try {
        $conn->exec("ALTER TABLE user_remarks ADD COLUMN auto_shift_count INT NOT NULL DEFAULT 0");
        logShift('Added column user_remarks.auto_shift_count');
    } catch (PDOException $e) {
        if ($e->getCode() !== '42S21') {
            throw $e;
        }
    }
}

function jsonArrayLengthSafe($json)
{
    if ($json === null || $json === '') {
        return 0;
    }
    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        return 0;
    }
    return count($decoded);
}

function normalizeRoleForRotation($rawType)
{
    $s = strtolower(trim((string)$rawType));
    switch ($s) {
        case 'ceo':
        case 'promoter':
            return 'promoter';
        case 'business_head':
        case 'business head':
        case 'bh':
            return 'business_head';
        case 'manager':
        case 'm':
            return 'manager';
        case 'team_lead':
        case 'team lead':
        case 'tl':
        case 'team_leader':
            return 'team_lead';
        case 'sales_executive':
        case 'sales executive':
        case 'se':
        case 'sales':
        case 'user':
        case 'u':
        default:
            return 'user';
    }
}

function getNormalizedUserRole(PDO $conn, $userTablename)
{
    static $roleCache = [];
    $key = strtolower(trim((string)$userTablename));

    if (isset($roleCache[$key])) {
        return $roleCache[$key];
    }

    $stmt = $conn->prepare("SELECT user_type FROM accounts WHERE tablename = :tn AND is_active = 1 LIMIT 1");
    $stmt->execute([':tn' => $userTablename]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $roleCache[$key] = normalizeRoleForRotation($row['user_type'] ?? 'user');
    return $roleCache[$key];
}

function isEndUserRole($normalizedRole)
{
    return $normalizedRole === 'user';
}

function isStillUntouchedPending(array $row)
{
    if (($row['history_h'] ?? null) !== 0 && (int)($row['history_h'] ?? 0) !== 0) {
        return false;
    }

    if (strcasecmp(trim((string)($row['status'] ?? '')), 'Pending') !== 0) {
        return false;
    }

    if (trim((string)($row['remarks'] ?? '')) !== '') {
        return false;
    }

    if (!empty($row['follow_up_date']) || !empty($row['follow_up_time'])) {
        return false;
    }

    if (jsonArrayLengthSafe($row['history'] ?? null) > 0) {
        return false;
    }

    if (jsonArrayLengthSafe($row['call_history'] ?? null) > 0) {
        return false;
    }

    return true;
}

function getImmediateSupervisors(PDO $conn, $userTablename)
{
    $stmt = $conn->prepare("SELECT assign_user FROM accounts WHERE tablename = :tn AND is_active = 1 LIMIT 1");
    $stmt->execute([':tn' => $userTablename]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || empty($row['assign_user'])) {
        return [];
    }

    return array_values(array_filter(array_map('trim', explode(',', (string)$row['assign_user']))));
}

function getDirectTeamUnderSupervisor(PDO $conn, $supervisorTablename)
{
    $stmt = $conn->prepare("SELECT tablename, user_type FROM accounts WHERE is_active = 1 AND FIND_IN_SET(:mgr, assign_user) > 0 ORDER BY tablename ASC");
    $stmt->execute([':mgr' => $supervisorTablename]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $users = [];
    foreach ($rows as $row) {
        $normalizedRole = normalizeRoleForRotation($row['user_type'] ?? 'user');
        if (isEndUserRole($normalizedRole)) {
            $users[] = $row['tablename'];
        }
    }

    return $users;
}

function resolveRotationTeam(PDO $conn, $owner)
{
    $supervisors = getImmediateSupervisors($conn, $owner);
    if (empty($supervisors)) {
        return [null, []];
    }

    $fallbackSupervisor = null;
    $fallbackTeam = [];

    foreach ($supervisors as $supervisor) {
        $teamUsers = getDirectTeamUnderSupervisor($conn, $supervisor);
        if (empty($teamUsers)) {
            continue;
        }

        // Prefer the first immediate supervisor whose direct team has at least 2 users including current owner.
        if (in_array($owner, $teamUsers, true) && count($teamUsers) >= 2) {
            return [$supervisor, $teamUsers];
        }

        // Keep a fallback (used only for stamping last_auto_shift_at when no rotation possible).
        if ($fallbackSupervisor === null) {
            $fallbackSupervisor = $supervisor;
            $fallbackTeam = $teamUsers;
        }
    }

    return [$fallbackSupervisor, $fallbackTeam];
}

function pickNextUserInLoop(array $teamUsers, $currentOwner)
{
    $count = count($teamUsers);
    if ($count < 2) {
        return null;
    }

    $idx = array_search($currentOwner, $teamUsers, true);
    if ($idx === false) {
        foreach ($teamUsers as $u) {
            if ($u !== $currentOwner) {
                return $u;
            }
        }
        return null;
    }

    for ($step = 1; $step <= $count; $step++) {
        $candidate = $teamUsers[($idx + $step) % $count];
        if ($candidate !== $currentOwner) {
            return $candidate;
        }
    }

    return null;
}

function shiftLeadToUser(PDO $conn, array $lockedRow, $targetUser)
{
    $uploadId = (int)$lockedRow['upload_data_id'];
    $remarkId = (int)$lockedRow['id'];
    $projectName = $lockedRow['assign_project_name'] ?? '';

    $stmtTargetActive = $conn->prepare("SELECT id FROM user_remarks WHERE upload_data_id = :uid AND user_unique_id = :target AND history_h = 0 LIMIT 1 FOR UPDATE");
    $stmtTargetActive->execute([':uid' => $uploadId, ':target' => $targetUser]);
    $targetActiveId = $stmtTargetActive->fetchColumn();

    if ($targetActiveId !== false) {
        $stmtMarkCurrentHistory = $conn->prepare("UPDATE user_remarks SET history_h = 1, assigned_by = 'system_auto_shift', last_auto_shift_at = NOW(), auto_shift_count = auto_shift_count + 1 WHERE id = :id AND history_h = 0");
        $stmtMarkCurrentHistory->execute([':id' => $remarkId]);
    } else {
        $stmtTargetHidden = $conn->prepare("SELECT id FROM user_remarks WHERE upload_data_id = :uid AND user_unique_id = :target AND history_h = 1 ORDER BY id DESC LIMIT 1 FOR UPDATE");
        $stmtTargetHidden->execute([':uid' => $uploadId, ':target' => $targetUser]);
        $targetHiddenId = $stmtTargetHidden->fetchColumn();

        if ($targetHiddenId !== false) {
            $stmtActivateHidden = $conn->prepare("UPDATE user_remarks SET history_h = 0, assigned_by = 'system_auto_shift', last_auto_shift_at = NOW(), auto_shift_count = auto_shift_count + 1 WHERE id = :id");
            $stmtActivateHidden->execute([':id' => $targetHiddenId]);

            $stmtMarkCurrentHistory = $conn->prepare("UPDATE user_remarks SET history_h = 1 WHERE id = :id AND history_h = 0");
            $stmtMarkCurrentHistory->execute([':id' => $remarkId]);
        } else {
            $stmtMoveCurrent = $conn->prepare("UPDATE user_remarks SET user_unique_id = :target, assigned_by = 'system_auto_shift', assign_project_name = :project_name, last_auto_shift_at = NOW(), auto_shift_count = auto_shift_count + 1 WHERE id = :id AND history_h = 0");
            $stmtMoveCurrent->execute([
                ':target' => $targetUser,
                ':project_name' => $projectName,
                ':id' => $remarkId
            ]);
        }
    }

    $stmtUpload = $conn->prepare("UPDATE shi_upload_data SET assign_to_user = :target WHERE id = :id");
    $stmtUpload->execute([':target' => $targetUser, ':id' => $uploadId]);
}

logShift('--- Starting untouched auto-rotation ---');

try {
    ensureRotationColumns($conn);

    $candidateSql = "
        SELECT ur.id, ur.upload_data_id, ur.user_unique_id, ur.assign_project_name, ur.status,
               ur.remarks, ur.follow_up_date, ur.follow_up_time, ur.history, ur.call_history,
               ur.created_at, ur.last_auto_shift_at, ur.history_h
        FROM user_remarks ur
        WHERE ur.history_h = 0
          AND ur.status = 'Pending'
          AND (
                (ur.last_auto_shift_at IS NULL AND ur.created_at <= DATE_SUB(NOW(), INTERVAL 30 MINUTE))
             OR (ur.last_auto_shift_at IS NOT NULL AND ur.last_auto_shift_at <= DATE_SUB(NOW(), INTERVAL 30 MINUTE))
          )
        ORDER BY COALESCE(ur.last_auto_shift_at, ur.created_at) ASC
        LIMIT :lim
    ";

    $stmtCandidates = $conn->prepare($candidateSql);
    $stmtCandidates->bindValue(':lim', (int)$runLimit, PDO::PARAM_INT);
    $stmtCandidates->execute();
    $candidates = $stmtCandidates->fetchAll(PDO::FETCH_ASSOC);

    if (empty($candidates)) {
        logShift('No eligible untouched leads found for rotation.');
        exit;
    }

    $shifted = 0;
    $skipped = 0;

    foreach ($candidates as $candidate) {
        $uploadId = (int)$candidate['upload_data_id'];

        try {
            $conn->beginTransaction();

            $stmtLock = $conn->prepare("SELECT id, upload_data_id, user_unique_id, assign_project_name, status, remarks, follow_up_date, follow_up_time, history, call_history, created_at, last_auto_shift_at, history_h FROM user_remarks WHERE id = :id FOR UPDATE");
            $stmtLock->execute([':id' => (int)$candidate['id']]);
            $lockedRow = $stmtLock->fetch(PDO::FETCH_ASSOC);

            if (!$lockedRow) {
                $conn->rollBack();
                $skipped++;
                continue;
            }

            if (!isStillUntouchedPending($lockedRow)) {
                $conn->rollBack();
                $skipped++;
                continue;
            }

            $owner = trim((string)$lockedRow['user_unique_id']);
            $ownerRole = getNormalizedUserRole($conn, $owner);

            // Rotation applies only to end-user role accounts.
            if (!isEndUserRole($ownerRole)) {
                $stmtStamp = $conn->prepare("UPDATE user_remarks SET last_auto_shift_at = NOW() WHERE id = :id");
                $stmtStamp->execute([':id' => (int)$lockedRow['id']]);
                $conn->commit();
                $skipped++;
                continue;
            }

            [$supervisor, $teamUsers] = resolveRotationTeam($conn, $owner);

            if (!$supervisor) {
                $stmtStamp = $conn->prepare("UPDATE user_remarks SET last_auto_shift_at = NOW() WHERE id = :id");
                $stmtStamp->execute([':id' => (int)$lockedRow['id']]);
                $conn->commit();
                $skipped++;
                continue;
            }

            $nextUser = pickNextUserInLoop($teamUsers, $owner);

            if (!$nextUser) {
                $stmtStamp = $conn->prepare("UPDATE user_remarks SET last_auto_shift_at = NOW() WHERE id = :id");
                $stmtStamp->execute([':id' => (int)$lockedRow['id']]);
                $conn->commit();
                $skipped++;
                continue;
            }

            shiftLeadToUser($conn, $lockedRow, $nextUser);
            $conn->commit();

            $shifted++;
            logShift("Shifted lead {$uploadId} from {$owner} to {$nextUser} (supervisor: {$supervisor})");
        } catch (Exception $leadEx) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            $skipped++;
            logShift("Error while shifting lead {$uploadId}: " . $leadEx->getMessage());
        }
    }

    logShift("Completed untouched auto-rotation. shifted={$shifted}, skipped={$skipped}, scanned=" . count($candidates));
} catch (Exception $e) {
    logShift('CRITICAL ERROR: ' . $e->getMessage());
    logShift('Trace: ' . $e->getTraceAsString());
    http_response_code(500);
}
