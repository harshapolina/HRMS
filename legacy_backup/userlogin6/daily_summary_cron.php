<?php
// daily_summary_cron.php
// This script checks for Overdue, Today's Followup, and Untouched leads for all active users.
// It sends a single consolidated notification summarizing their counts.
// It's meant to be run via a cron job (e.g., daily at 9am).

require_once 'config.php';
$config = new Config();
$conn = $config->getConnection();
date_default_timezone_set('Asia/Kolkata');

// Setup notification constants (same as update_status.php for consistency)
$CRM_API_KEY_SERVER = "533f4175837e145064605e15e12c7273f98746fec7459b0168af9394a22c6efab6bba75cce18a3555250e473f4907d22aaae3e3f12e46dd8ef22fac38737c537";
$logFile = __DIR__ . "/notifications/daily_summary_cron.log";

function logMsg($msg)
{
  global $logFile;
  $date = date('Y-m-d H:i:s');
  file_put_contents($logFile, "[$date] $msg\n", FILE_APPEND);
  echo "[$date] $msg<br>\n";
}

logMsg("--- Starting Daily Summary Cron Script ---");

try {
  // 1. Get all active users
  $stmtUsers = $conn->prepare("SELECT tablename, username, user_type FROM accounts WHERE is_active = 1");
  $stmtUsers->execute();
  $activeUsers = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

  logMsg("Found " . count($activeUsers) . " active users.");

  $notificationsSent = 0;

  // 2. Prepare SQL queries for the 3 metrics based on dashboard logic from update_status.php

  // Overdue Leads SQL - Excludes leads skipped today
  $sqlOverdue = "
        SELECT COUNT(*) as cnt 
        FROM user_remarks ur
        INNER JOIN shi_upload_data sud ON sud.id = ur.upload_data_id
        WHERE ur.status IN ('Follow Up', 'RNR', 'Call Back', 'Pending', 'Interested', 'Fix Site Visit', 'Site Visit Done', 'VC Done', 'Re site visit', 'Not Connected')
        AND ur.status != 'Converted'
        AND ur.status != 'Already Booked'
        AND ur.follow_up_date IS NOT NULL 
        AND ur.follow_up_time IS NOT NULL 
        AND CONCAT(ur.follow_up_date, ' ', ur.follow_up_time) < DATE_SUB(NOW(), INTERVAL 1 DAY)
        AND ur.user_unique_id = :userUniqueId
        AND ur.history_h = 0
        AND (ur.overdue_skipped_date IS NULL OR ur.overdue_skipped_date != CURDATE())
    ";
  $stmtOverdue = $conn->prepare($sqlOverdue);

  // Today's Followup Leads SQL
  $todayDate = date('Y-m-d');
  $todayStart = $todayDate . ' 00:00:00';
  $todayEnd = $todayDate . ' 23:59:59';
  $statusesToday = "('Follow Up', 'Interested', 'Call Back', 'RNR', 'Fix Site Visit', 'VC Done')";

  $sqlToday = "
        SELECT COUNT(*) as cnt 
        FROM user_remarks ur
        INNER JOIN shi_upload_data sud ON sud.id = ur.upload_data_id
        WHERE ur.user_unique_id = :userUniqueId
        AND ur.status IN $statusesToday
        AND (
            (
                ur.follow_up_date IS NOT NULL
                AND ur.follow_up_date >= :todayStart
                AND ur.follow_up_date < :todayEnd
            )
            OR (
                JSON_TYPE(ur.history) = 'ARRAY'
                AND JSON_LENGTH(ur.history) > 0
                AND STR_TO_DATE(
                    JSON_UNQUOTE(
                        JSON_EXTRACT(ur.history, CONCAT('$[', JSON_LENGTH(ur.history) - 1, '].followUpDate'))
                    ), '%Y-%m-%d'
                ) >= :todayStart
                AND STR_TO_DATE(
                    JSON_UNQUOTE(
                        JSON_EXTRACT(ur.history, CONCAT('$[', JSON_LENGTH(ur.history) - 1, '].followUpDate'))
                    ), '%Y-%m-%d'
                ) < :todayEnd
            )
        )
        AND ur.history_h = 0
    ";
  $stmtToday = $conn->prepare($sqlToday);

  // Untouched Leads SQL (equivalent to "Pending" leads per front-end logic)
  $sqlUntouched = "
        SELECT COUNT(*) as cnt 
        FROM user_remarks ur 
        INNER JOIN shi_upload_data sud ON sud.id = ur.upload_data_id
        WHERE ur.status = 'Pending'
        AND ur.user_unique_id = :userUniqueId
        AND ur.history_h = 0
    ";
  $stmtUntouched = $conn->prepare($sqlUntouched);


  // 3. Loop through each user to check their counts
  foreach ($activeUsers as $user) {
    $userUniqueId = $user['tablename'];
    $username = $user['username'] ?? $userUniqueId;
    $userType = $user['user_type'] ?? 'user';

    // Get Overdue count
    $stmtOverdue->execute([':userUniqueId' => $userUniqueId]);
    $overdueCount = (int) ($stmtOverdue->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);

    // Get Today's Followup count
    $stmtToday->execute([
      ':userUniqueId' => $userUniqueId,
      ':todayStart' => $todayStart,
      ':todayEnd' => $todayEnd
    ]);
    $todayCount = (int) ($stmtToday->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);

    // Get Untouched (Pending) count
    $stmtUntouched->execute([':userUniqueId' => $userUniqueId]);
    $untouchedCount = (int) ($stmtUntouched->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);

    // If at least one of these metrics is > 0, build and send the notification
    if ($overdueCount > 0 || $todayCount > 0 || $untouchedCount > 0) {
      // Build log message showing only non-zero counts
      $logParts = [];
      if ($overdueCount > 0) {
        $logParts[] = "Overdue: $overdueCount";
      }
      if ($todayCount > 0) {
        $logParts[] = "Today: $todayCount";
      }
      if ($untouchedCount > 0) {
        $logParts[] = "Untouched: $untouchedCount";
      }
      logMsg("Calculated for $username ($userUniqueId) -> " . implode(" | ", $logParts));

      // Build dynamic message body (only include counts > 0)
      $msgLines = ["Good Morning! Here is your lead summary for today:\n"];
      if ($overdueCount > 0) {
        $msgLines[] = "🔴 Overdue: {$overdueCount}";
      }
      if ($todayCount > 0) {
        $msgLines[] = "🟡 Today's Followup: {$todayCount}";
      }
      if ($untouchedCount > 0) {
        $msgLines[] = "⚪ Untouched: {$untouchedCount}";
      }

      $msgLines[] = "\nPlease check your dashboard and follow up promptly.";
      $bodyContent = implode("\n", $msgLines);

      // 4. Prepare Notification Payload
      $notifyUserCodes = [$userUniqueId];
      if (in_array(strtolower(trim($userType)), ['promoter', 'ceo'])) {
          $stmtSA = $conn->prepare("SELECT tablename FROM adminaccounts WHERE role = 'superuseradmin'");
          $stmtSA->execute();
          $superAdmins = $stmtSA->fetchAll(PDO::FETCH_COLUMN) ?: [];
          $notifyUserCodes = array_values(array_unique(array_merge($notifyUserCodes, $superAdmins)));
      }

      $notifyPayload = [
        "title" => "📅 Daily Leads Summary",
        "body" => $bodyContent,
        "user_codes" => $notifyUserCodes,
        "url" => "https://mnts.in/incentiveapp_integration/userlogin1/userlogin6/user_lead",
        "project_code" => "Mnt_reos_nfs"
      ];

      // 5. Send API Request
      $ch = curl_init("https://notification.mnts.in/api/notify-users");
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $CRM_API_KEY_SERVER",
        "Content-Type: application/json"
      ]);
      curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($notifyPayload));
      curl_setopt($ch, CURLOPT_TIMEOUT, 10);

      $notifyResp = curl_exec($ch);
      $notifyHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);

      $notifyResult = null;
      if (curl_errno($ch)) {
        $notifyResult = ["status" => "error", "message" => curl_error($ch)];
        logMsg("Error calling notify logic for $userUniqueId: " . curl_error($ch));
      } else {
        $successCodes = [200, 201, 202];
        $notifyResult = [
          "status" => in_array($notifyHttp, $successCodes) ? "success" : "error",
          "http_code" => $notifyHttp,
          "response" => json_decode($notifyResp, true)
        ];

        if (in_array($notifyHttp, $successCodes)) {
          $notificationsSent++;
        } else {
          logMsg("Failed to send notification for $userUniqueId. HTTP Code: $notifyHttp. Response: $notifyResp");
        }
      }
      curl_close($ch);

      // 6. Store in local notifications tracking database (Best-effort logging)
      try {
        $meta = [
          'notify_api_result' => $notifyResult,
          'counts' => [
            'overdue' => $overdueCount,
            'today' => $todayCount,
            'untouched' => $untouchedCount
          ]
        ];

        $conn->beginTransaction();
        $sqlInsertNotif = "INSERT INTO notifications (title, body, url, type, icon, sender, meta, created_at) VALUES (:title, :body, :url, :type, :icon, :sender, :meta, :created_at)";
        $stmt = $conn->prepare($sqlInsertNotif);
        $stmt->execute([
          ':title' => $notifyPayload['title'],
          ':body' => $notifyPayload['body'],
          ':url' => $notifyPayload['url'],
          ':type' => 'alert', // Type: alert
          ':icon' => null,
          ':sender' => 'system',
          ':meta' => json_encode($meta),
          ':created_at' => date("Y-m-d H:i:s")
        ]);
        $nid = $conn->lastInsertId();

        $insReceipt = $conn->prepare("INSERT INTO notification_receipts (notification_id, user_code, created_at) VALUES (:nid, :uc, :created_at)");
        foreach ($notifyUserCodes as $uc) {
            $insReceipt->execute([':nid' => $nid, ':uc' => trim($uc), ':created_at' => date('Y-m-d H:i:s')]);
        }

        $conn->commit();
      } catch (Exception $e) {
        if ($conn->inTransaction()) {
          $conn->rollBack();
        }
        logMsg("DB Logging error for $userUniqueId notification: " . $e->getMessage());
      }

    } else {
      // logMsg("User $username ($userUniqueId) has 0 across all 3 metrics. Skipping.");
    }
  }

  logMsg("--- Finished. Total consolidated notifications successfully dispatched: $notificationsSent ---");

} catch (Exception $e) {
  logMsg("CRITICAL ERROR: " . $e->getMessage());
}
?>