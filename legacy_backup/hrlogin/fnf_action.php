<?php
session_start();
require_once 'db.php';
$db = new Database();
$db->createFnfTable(); // Ensure table exists

header('Content-Type: application/json');

if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'hradminuser') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'get_fnf_details') {
    $user_id = $_POST['user_id'] ?? 0;
    $settlement = $db->getFnfSettlement($user_id);
    $pending_assets = $db->checkPendingAssets($user_id);
    
    // Fetch basic user info (salary, name, etc.) for letters
    $sql = "SELECT id, username, employee_id, user_type, project_name, doj, salary, deactivated_at FROM accounts WHERE id = :uid";
    $stmt = $db->getConnection()->prepare($sql);
    $stmt->execute(['uid' => $user_id]);
    $user = $stmt->fetch();

    $payslips = $db->searchPayslips(null, $user_id);
    
    echo json_encode([
        'status' => 'success',
        'settlement' => $settlement,
        'pending_assets' => $pending_assets,
        'user' => $user,
        'payslips' => $payslips
    ]);
} 
elseif ($action === 'save_fnf') {
    $data = [
        'uid' => $_POST['user_id'],
        'lwd' => $_POST['last_working_day'],
        'salary' => $_POST['unpaid_salary'],
        'leaves' => $_POST['leave_encashment'],
        'bonus' => $_POST['bonus_incentives'],
        'deductions' => $_POST['deductions'],
        'net' => $_POST['net_settlement'],
        'status' => $_POST['status'],
        'assets' => $_POST['assets_returned']
    ];
    
    if ($db->upsertFnfSettlement($data)) {
        echo json_encode(['status' => 'success', 'message' => 'FNF Settlement saved successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to save settlement']);
    }
}
?>
