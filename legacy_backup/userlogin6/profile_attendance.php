<?php
/**
 * profile_attendance.php
 * Returns attendance log for the logged-in user for a given month/year.
 * Response format: { "YYYY-MM-DD": { "status": "Present|Absent|Late|...", "reason": "..." }, ... }
 * If date=YYYY-MM-DD is provided, returns a single-day detail payload.
 */
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['loggedin']) || !isset($_SESSION['tablename'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once 'config.php';
$config = new Config();
$conn   = $config->getConnection();

// Resolve the numeric user ID from tablename
$tablename = $_SESSION['tablename'];
$stmtId = $conn->prepare("SELECT id FROM accounts WHERE tablename = :tablename LIMIT 1");
$stmtId->bindParam(':tablename', $tablename, PDO::PARAM_STR);
$stmtId->execute();
$row = $stmtId->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    echo json_encode(['error' => 'User not found']);
    exit;
}
$userId = (int)$row['id'];

// Single-day detail if date param is provided
$date = isset($_GET['date']) ? trim((string)$_GET['date']) : '';
if ($date !== '') {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid date']);
        exit;
    }

    $stmt = $conn->prepare(
        "SELECT status, punch_in, punch_out, total_hours
         FROM attendance_logs
         WHERE user_id = :uid AND punch_date = :pdate
         LIMIT 1"
    );
    $stmt->execute(['uid' => $userId, 'pdate' => $date]);
    $log = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($log) {
        echo json_encode([
            'status' => 'success',
            'data' => [
                'status' => $log['status'] ?? '---',
                'punch_in' => $log['punch_in'] ?? null,
                'punch_out' => $log['punch_out'] ?? null,
                'total_hours' => $log['total_hours'] ?? null
            ]
        ]);
        exit;
    }

    echo json_encode([
        'status' => 'success',
        'data' => [
            'status' => 'Absent',
            'punch_in' => null,
            'punch_out' => null,
            'total_hours' => null
        ]
    ]);
    exit;
}

// Month / Year parameters
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$year  = isset($_GET['year'])  ? (int)$_GET['year']  : (int)date('Y');

$monthPad = str_pad($month, 2, '0', STR_PAD_LEFT);
$startDate = "$year-$monthPad-01";
$endDate   = date('Y-m-t', strtotime($startDate));

try {
    // Query attendance_logs — note: 'reason' column does NOT exist in this table
    $stmt = $conn->prepare(
        "SELECT punch_date, status
         FROM attendance_logs
         WHERE user_id = :uid
           AND punch_date BETWEEN :start AND :end
         ORDER BY punch_date ASC"
    );
    $stmt->execute(['uid' => $userId, 'start' => $startDate, 'end' => $endDate]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $result = [];
    foreach ($rows as $r) {
        $result[$r['punch_date']] = [
            'status' => $r['status'] ?? 'Absent',
            'reason' => ''   // column does not exist; keep key so JS doesn't break
        ];
    }
    echo json_encode($result);
} catch (PDOException $e) {
    // Return empty safely — JS will mark past days as Absent
    echo json_encode([]);
}
