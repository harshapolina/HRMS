<?php
// followup_reminder_cron.php
// This script checks for leads with follow-up time within the next 10 minutes
// and sends reminder notifications to users
// Run this via cron every 5 minutes

require_once 'config.php';
$config = new Config();
$conn = $config->getConnection();
date_default_timezone_set('Asia/Kolkata');

// Setup notification constants
$CRM_API_KEY_SERVER = "533f4175837e145064605e15e12c7273f98746fec7459b0168af9394a22c6efab6bba75cce18a3555250e473f4907d22aaae3e3f12e46dd8ef22fac38737c537";
$logFile = __DIR__ . "/notifications/followup_reminder_cron.log";

function logMsg($msg)
{
  global $logFile;
  $date = date('Y-m-d H:i:s');
  file_put_contents($logFile, "[$date] $msg\n", FILE_APPEND);
  echo "[$date] $msg<br>\n";
}

logMsg("--- Starting Follow-up Reminder Cron Script ---");

try {
  // Calculate time window: now to 10 minutes from now
  $now = date('Y-m-d H:i:s');
  $tenMinutesLater = date('Y-m-d H:i:s', strtotime('+10 minutes'));
  
  logMsg("Checking for follow-ups between $now and $tenMinutesLater");

  // Query to find leads that need follow-up within next 10 minutes
  // Only include active statuses that require follow-up
  $sql = "
    SELECT 
      ur.upload_data_id as lead_id,
      ur.user_unique_id,
      ur.status,
      ur.follow_up_date,
      ur.follow_up_time,
      CONCAT(ur.follow_up_date, ' ', ur.follow_up_time) as followup_datetime,
      sud.name as lead_name,
      sud.number as lead_phone,
      ur.assign_project_name as project_name,
      acc.username,
      acc.user_type
    FROM user_remarks ur
    INNER JOIN shi_upload_data sud ON sud.id = ur.upload_data_id
    LEFT JOIN accounts acc ON acc.tablename = ur.user_unique_id
    WHERE ur.status IN ('Follow Up', 'RNR', 'Call Back', 'Interested', 'Fix Site Visit', 'Site Visit Done', 'VC Done', 'Re site visit', 'Not Connected')
      AND ur.status != 'Converted'
      AND ur.status != 'Already Booked'
      AND ur.follow_up_date IS NOT NULL 
      AND ur.follow_up_time IS NOT NULL
      AND CONCAT(ur.follow_up_date, ' ', ur.follow_up_time) >= :now
      AND CONCAT(ur.follow_up_date, ' ', ur.follow_up_time) <= :tenMinutesLater
      AND ur.history_h = 0
    ORDER BY ur.user_unique_id, CONCAT(ur.follow_up_date, ' ', ur.follow_up_time) ASC
  ";

  $stmt = $conn->prepare($sql);
  $stmt->execute([
    ':now' => $now,
    ':tenMinutesLater' => $tenMinutesLater
  ]);
  $upcomingLeads = $stmt->fetchAll(PDO::FETCH_ASSOC);

  logMsg("Found " . count($upcomingLeads) . " leads requiring follow-up in next 10 minutes");

  if (count($upcomingLeads) === 0) {
    logMsg("No leads to notify. Exiting.");
    exit;
  }

  // Group leads by user to send consolidated notifications
  $leadsByUser = [];
  foreach ($upcomingLeads as $lead) {
    $userCode = $lead['user_unique_id'];
    if (!isset($leadsByUser[$userCode])) {
      $leadsByUser[$userCode] = [
        'username' => $lead['username'] ?? $userCode,
        'user_type' => $lead['user_type'] ?? 'user',
        'leads' => []
      ];
    }
    $leadsByUser[$userCode]['leads'][] = $lead;
  }

  $notificationsSent = 0;

  // Process each user's leads
  foreach ($leadsByUser as $userCode => $userData) {
    $username = $userData['username'];
    $userType = $userData['user_type'];
    $leads = $userData['leads'];
    $leadCount = count($leads);

    logMsg("Processing $leadCount lead(s) for user $username ($userCode)");

    // Check if we've already sent a notification for these specific leads
    // (to avoid duplicate notifications if cron runs multiple times)
    $alreadySent = [];
    foreach ($leads as $lead) {
      $checkSql = "
        SELECT n.id 
        FROM notifications n
        JOIN notification_receipts nr ON nr.notification_id = n.id
        WHERE n.type = 'followup_reminder'
          AND nr.user_code = :userCode
          AND n.meta LIKE :leadIdPattern
          AND n.created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
      ";
      $checkStmt = $conn->prepare($checkSql);
      $checkStmt->execute([
        ':userCode' => $userCode,
        ':leadIdPattern' => '%"lead_id":"' . $lead['lead_id'] . '"%'
      ]);
      if ($checkStmt->fetch()) {
        $alreadySent[] = $lead['lead_id'];
        logMsg("  Already sent notification for lead ID {$lead['lead_id']} recently. Skipping.");
      }
    }

    // Filter out already notified leads
    $leadsToNotify = array_filter($leads, function($lead) use ($alreadySent) {
      return !in_array($lead['lead_id'], $alreadySent);
    });

    if (count($leadsToNotify) === 0) {
      logMsg("  All leads already notified. Skipping user $username.");
      continue;
    }

    // Build notification message
    if (count($leadsToNotify) === 1) {
      $lead = $leadsToNotify[0];
      $followupTime = date('g:i A', strtotime($lead['followup_datetime']));
      
      $title = "⏰ Follow-up Reminder";
      $body = "Follow-up scheduled in 10 minutes!\n\n";
      $body .= "Lead: {$lead['lead_name']}\n";
      $body .= "Status: {$lead['status']}\n";
      $body .= "Time: $followupTime\n";
      if ($lead['project_name']) {
        $body .= "Project: {$lead['project_name']}\n";
      }
      $body .= "\nPlease prepare for your follow-up call.";
      
      $metaLeads = [
        [
          'lead_id' => $lead['lead_id'],
          'lead_name' => $lead['lead_name'],
          'status' => $lead['status'],
          'followup_time' => $lead['followup_datetime']
        ]
      ];
    } else {
      $title = "⏰ Follow-up Reminders";
      $body = count($leadsToNotify) . " follow-ups scheduled in the next 10 minutes:\n\n";
      
      $metaLeads = [];
      foreach ($leadsToNotify as $idx => $lead) {
        $num = $idx + 1;
        $followupTime = date('g:i A', strtotime($lead['followup_datetime']));
        $body .= "{$num}. {$lead['lead_name']} - {$lead['status']} @ $followupTime\n";
        
        $metaLeads[] = [
          'lead_id' => $lead['lead_id'],
          'lead_name' => $lead['lead_name'],
          'status' => $lead['status'],
          'followup_time' => $lead['followup_datetime']
        ];
      }
      
      $body .= "\nPlease check your dashboard for details.";
    }

    // Append superadmins if the user is a promoter
    $notifyUserCodes = [$userCode];
    if (in_array(strtolower(trim($userType)), ['promoter', 'ceo'])) {
        $stmtSA = $conn->prepare("SELECT tablename FROM adminaccounts WHERE role = 'superuseradmin'");
        $stmtSA->execute();
        $superAdmins = $stmtSA->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $notifyUserCodes = array_values(array_unique(array_merge($notifyUserCodes, $superAdmins)));
    }

    // Prepare notification payload for API
    $notifyPayload = [
      "title" => $title,
      "body" => $body,
      "user_codes" => $notifyUserCodes,
      "url" => "https://mnts.in/incentiveapp_integration/userlogin1/userlogin6/user_lead",
      "project_code" => "Mnt_reos_nfs"
    ];

    // Send API Request
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
      logMsg("  Error calling notify API for $userCode: " . curl_error($ch));
    } else {
      $successCodes = [200, 201, 202];
      $notifyResult = [
        "status" => in_array($notifyHttp, $successCodes) ? "success" : "error",
        "http_code" => $notifyHttp,
        "response" => json_decode($notifyResp, true)
      ];

      if (in_array($notifyHttp, $successCodes)) {
        $notificationsSent++;
        logMsg("  ✓ Notification sent successfully to $username");
      } else {
        logMsg("  ✗ Failed to send notification to $username. HTTP Code: $notifyHttp. Response: $notifyResp");
      }
    }
    curl_close($ch);

    // Store in local notifications database
    try {
      $meta = [
        'notify_api_result' => $notifyResult,
        'leads' => $metaLeads,
        'count' => count($leadsToNotify)
      ];

      $conn->beginTransaction();
      
      $sqlInsertNotif = "INSERT INTO notifications (title, body, url, type, icon, sender, meta, created_at) 
                         VALUES (:title, :body, :url, :type, :icon, :sender, :meta, :created_at)";
      $stmt = $conn->prepare($sqlInsertNotif);
      $stmt->execute([
        ':title' => $title,
        ':body' => $body,
        ':url' => 'https://mnts.in/incentiveapp_integration/userlogin1/userlogin6/user_lead',
        ':type' => 'followup_reminder',
        ':icon' => 'bell',
        ':sender' => 'system',
        ':meta' => json_encode($meta),
        ':created_at' => date("Y-m-d H:i:s")
      ]);
      $nid = $conn->lastInsertId();

      $insReceipt = $conn->prepare("INSERT INTO notification_receipts (notification_id, user_code, created_at) 
                                     VALUES (:nid, :uc, :created_at)");
      foreach ($notifyUserCodes as $uc) {
          $insReceipt->execute([
            ':nid' => $nid, 
            ':uc' => trim($uc), 
            ':created_at' => date('Y-m-d H:i:s')
          ]);
      }

      $conn->commit();
      logMsg("  ✓ Notification stored in database (ID: $nid)");
    } catch (Exception $e) {
      if ($conn->inTransaction()) {
        $conn->rollBack();
      }
      logMsg("  ✗ DB logging error for $userCode: " . $e->getMessage());
    }
  }

  logMsg("--- Finished. Total notifications sent: $notificationsSent ---");

} catch (Exception $e) {
  logMsg("CRITICAL ERROR: " . $e->getMessage());
  logMsg("Stack trace: " . $e->getTraceAsString());
}
?>
