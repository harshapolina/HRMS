<?php
/**
 * BACKGROUND CRON JOB - Auto WhatsApp For New Leads
 * 
 * DESCRIPTION:
 * This script runs independently in the background (e.g., every 1 minute via Cron/Task Scheduler).
 * It scans the `user_remarks` table for any newly inserted, active leads that haven't received
 * a WhatsApp message yet (wa_bot_sent = 0), and triggers the Esha Bot via `wa_auto_send.php`.
 * 
 * SETUP:
 * Set up your server's cron job to hit this script via URL or PHP CLI:
 * * * * * curl -s http://yourdomain.com/userlogin6/cron_auto_whatsapp.php
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/wa_auto_send.php';

$config = new Config();
$conn = $config->getConnection();

// ============================================================================
// 1. ONE-TIME DB SETUP (Safe to run every time)
// ============================================================================
try {
    // Attempt to add the tracking column. This will naturally fail if it already exists.
    $conn->exec("ALTER TABLE user_remarks ADD COLUMN wa_bot_sent TINYINT(1) DEFAULT 0");
    
    // If it succeeds, this is the VERY FIRST run. We MUST mark all existing database rows 
    // as "sent" (1) so we don't accidentally spam 10,000 old leads from years ago.
    $conn->exec("UPDATE user_remarks SET wa_bot_sent = 1 WHERE wa_bot_sent = 0");
    
    echo "Setup: Database column 'wa_bot_sent' created. All existing historical records marked as safe.\n";
    // We exit here on the first run to allow the DB to settle. 
    // The next cron run a minute later will pick up fresh leads.
    exit;
} catch (PDOException $e) {
    // 42S21 means Duplicate column name (it already exists). This is the expected normal state.
    if ($e->getCode() != '42S21') {
        die("Fatal DB Error ensuring tracking column exists: " . $e->getMessage());
    }
}


// ============================================================================
// 2. PROCESS NEW LEADS
// ============================================================================

// We process a maximum of 25 leads per minute to respect API rate limits and prevent timeouts.
$stmt = $conn->prepare("
    SELECT id, upload_data_id 
    FROM user_remarks 
    WHERE wa_bot_sent = 0 
      AND history_h = 0 
      AND status = 'Pending'
    ORDER BY created_at ASC
    LIMIT 25 FOR UPDATE
");

try {
    $conn->beginTransaction();
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rows)) {
        echo "No new leads awaiting WhatsApp.\n";
        $conn->commit();
        exit;
    }

    $processed = 0;
    foreach ($rows as $row) {
        $remarkId = $row['id'];
        $uploadDataId = $row['upload_data_id'];

        // 1. Trigger the actual Esha WhatsApp API push
        // (This function internally also writes the 'Sent' message log into `whatsapp_history` column)
        triggerAutoWhatsApp($conn, $remarkId);

        // 2. Mark as successfully pushed so the cron never processes it again
        $updateStmt = $conn->prepare("UPDATE user_remarks SET wa_bot_sent = 1 WHERE id = :id");
        $updateStmt->execute([':id' => $remarkId]);
        
        $processed++;
    }

    $conn->commit();
    echo "Success: Processed $processed leads.\n";

} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    echo "Error processing leads: " . $e->getMessage();
}