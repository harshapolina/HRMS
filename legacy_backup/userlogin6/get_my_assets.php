<?php
/**
 * get_my_assets.php
 * Returns company assets assigned to the logged-in userlogin6 user.
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

// Resolve numeric user ID from tablename
$tablename = $_SESSION['tablename'];
$stmtId = $conn->prepare("SELECT id FROM accounts WHERE tablename = :tablename LIMIT 1");
$stmtId->bindParam(':tablename', $tablename, PDO::PARAM_STR);
$stmtId->execute();
$row = $stmtId->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    echo json_encode([]);
    exit;
}
$userId = (int)$row['id'];

try {
    $stmt = $conn->prepare(
        "SELECT a.asset_name, a.asset_type, a.serial_number, aa.assigned_date, aa.notes
         FROM asset_assignments aa
         JOIN assets a ON a.id = aa.asset_id
         WHERE aa.employee_id = :uid
         ORDER BY aa.assigned_date DESC"
    );
    $stmt->execute(['uid' => $userId]);
    $assets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($assets);
} catch (PDOException $e) {
    // Table may not exist — return empty safely
    echo json_encode([]);
}
