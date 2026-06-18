<?php
/**
 * save_call_log.php
 * ─────────────────────────────────────────────────────────────────────────────
 * Receives call log data POST-ed by the Android app after every call and
 * saves it to the call_logs table in the CRM database.
 *
 * Endpoint URL: https://connect.mecntech.com/save_call_log.php
 *
 * ── Run this SQL once on your server to create the table ─────────────────────
 *
 *  CREATE TABLE IF NOT EXISTS call_logs (
 *      id               INT AUTO_INCREMENT PRIMARY KEY,
 *      user_id          INT          NOT NULL DEFAULT 0,
 *      lead_id          INT          DEFAULT NULL,
 *      phone_number     VARCHAR(25)  NOT NULL,
 *      contact_name     VARCHAR(100) DEFAULT '',
 *      call_type        ENUM('incoming','outgoing','missed','unknown') DEFAULT 'unknown',
 *      duration_seconds INT          NOT NULL DEFAULT 0,
 *      called_at        DATETIME     NOT NULL,
 *      created_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
 *      INDEX idx_user   (user_id),
 *      INDEX idx_lead   (lead_id),
 *      INDEX idx_phone  (phone_number),
 *      INDEX idx_date   (called_at)
 *  );
 *
 * ─────────────────────────────────────────────────────────────────────────────
 */

require_once 'config.php';   // same Config class used across all CRM files

header('Content-Type: application/json');

// ── Only accept POST ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

// ── DB connection via existing Config class ───────────────────────────────────
$config = new Config();
$pdo    = $config->getConnection();

// ── Sanitise & validate inputs ────────────────────────────────────────────────
$phone    = trim($_POST['phone']        ?? '');
$duration = max(0, (int)($_POST['duration']     ?? 0));
$dateMs   = (int)($_POST['date']        ?? 0);      // Android sends milliseconds
$callType = trim($_POST['call_type']    ?? 'unknown');
$name     = trim($_POST['contact_name'] ?? '');
$leadId   = (isset($_POST['lead_id']) && $_POST['lead_id'] !== '') ? (int)$_POST['lead_id'] : null;
$userId   = max(0, (int)($_POST['user_id']      ?? 0));

if (empty($phone)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'phone is required']);
    exit;
}

// Validate call_type against allowed ENUM values
$allowedTypes = ['incoming', 'outgoing', 'missed', 'unknown'];
if (!in_array($callType, $allowedTypes, true)) {
    $callType = 'unknown';
}

// Convert Android ms timestamp → MySQL DATETIME in IST (+05:30, matches CRM timezone)
if ($dateMs > 0) {
    $dt = new DateTime('@' . (int)($dateMs / 1000));
    $dt->setTimezone(new DateTimeZone('Asia/Kolkata'));
    $calledAt = $dt->format('Y-m-d H:i:s');
} else {
    $calledAt = (new DateTime('now', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d H:i:s');
}

// ── Prevent duplicate entries (same phone + same called_at) ──────────────────
$checkStmt = $pdo->prepare(
    "SELECT id FROM call_logs WHERE phone_number = :phone AND called_at = :called_at LIMIT 1"
);
$checkStmt->execute([':phone' => $phone, ':called_at' => $calledAt]);
if ($checkStmt->fetch()) {
    echo json_encode(['status' => 'ok', 'message' => 'duplicate — already saved']);
    exit;
}

// ── Insert the call log record ────────────────────────────────────────────────
$stmt = $pdo->prepare("
    INSERT INTO call_logs
        (user_id, lead_id, phone_number, contact_name, call_type, duration_seconds, called_at)
    VALUES
        (:user_id, :lead_id, :phone, :name, :call_type, :duration, :called_at)
");

$stmt->execute([
    ':user_id'   => $userId,
    ':lead_id'   => $leadId,
    ':phone'     => $phone,
    ':name'      => $name,
    ':call_type' => $callType,
    ':duration'  => $duration,
    ':called_at' => $calledAt,
]);

echo json_encode([
    'status'  => 'ok',
    'id'      => (int)$pdo->lastInsertId(),
    'message' => 'Call log saved successfully',
]);
