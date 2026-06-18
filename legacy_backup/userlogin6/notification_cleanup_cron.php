<?php
/**
 * notification_cleanup_cron.php
 *
 * Deletes notifications older than a retention window along with related receipts.
 * Customize the retention by passing days as a CLI arg or env var:
 *   php notification_cleanup_cron.php 3
 *   NOTIFICATION_RETENTION_DAYS=3 php notification_cleanup_cron.php
 */

require_once 'config.php';

date_default_timezone_set('Asia/Kolkata');

$config = new Config();
$conn = $config->getConnection();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);


$logFile = __DIR__ . '/notifications/notification_cleanup_cron.log';

function logMsg($msg)
{
    global $logFile;
    $date = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$date] $msg\n", FILE_APPEND);
    echo "[$date] $msg<br>\n";
}

logMsg('--- Starting notification cleanup ---');

try {
    $defaultDays = 3;
    $envDays = getenv('NOTIFICATION_RETENTION_DAYS');
    $argDays = $argv[1] ?? null;
    $rawDays = $argDays !== null ? $argDays : $envDays;

    $days = $defaultDays;
    if ($rawDays !== false && $rawDays !== null && $rawDays !== '') {
        $days = (int)$rawDays;
        if ($days <= 0) {
            throw new InvalidArgumentException('Retention days must be a positive integer.');
        }
    }

    $cutoff = date('Y-m-d H:i:s', strtotime('-' . $days . ' days'));
    logMsg("Using retention window: {$days} day(s)");

    $conn->beginTransaction();

    $stmtReceipts = $conn->prepare(
        "DELETE nr FROM notification_receipts nr
         INNER JOIN notifications n ON n.id = nr.notification_id
         WHERE n.created_at < :cutoff"
    );
    $stmtReceipts->execute([':cutoff' => $cutoff]);
    $deletedReceipts = $stmtReceipts->rowCount();

    $stmtNotifications = $conn->prepare(
        "DELETE FROM notifications WHERE created_at < :cutoff"
    );
    $stmtNotifications->execute([':cutoff' => $cutoff]);
    $deletedNotifications = $stmtNotifications->rowCount();

    $conn->commit();

    logMsg("Deleted receipts: {$deletedReceipts}");
    logMsg("Deleted notifications: {$deletedNotifications}");
    logMsg('--- Cleanup completed successfully ---');
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    logMsg('CRITICAL ERROR: ' . $e->getMessage());
    http_response_code(500);
}
