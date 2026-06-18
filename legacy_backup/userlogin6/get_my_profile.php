<?php
/**
 * get_my_profile.php
 * Returns the logged-in user's own profile data from the accounts table.
 * Used by profile.php in userlogin6.
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

$tablename = $_SESSION['tablename'];

try {
    $stmt = $conn->prepare(
        "SELECT id, username, useremail, phonenumber, salary, doj, dob,
                employee_id, user_type, project_name, project_type,
                is_active, tablename,
                one_amt   AS first_amount,
                two_amt   AS second_amount,
                thrid_amt AS third_amount,
                forth_amt AS fourth_amount,
                fifth_amt AS fifth_amount,
                sixth_amt AS sixth_amount
         FROM accounts
         WHERE tablename = :tablename
         LIMIT 1"
    );
    $stmt->bindParam(':tablename', $tablename, PDO::PARAM_STR);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['error' => 'User not found']);
        exit;
    }

    // Fetch subordinate users recursively
    $subordinates = [];
    $visited = [];
    $queue = [$tablename];
    $maxDepth = 10;
    $currentDepth = 0;

    while (!empty($queue) && $currentDepth < $maxDepth) {
        $currentLevel = [];
        foreach ($queue as $currentUser) {
            if (isset($visited[$currentUser])) {
                continue;
            }
            $visited[$currentUser] = true;

            $stmtSub = $conn->prepare("
                SELECT tablename 
                FROM accounts 
                WHERE FIND_IN_SET(:manager, assign_user) > 0 
                AND is_active = 1
            ");
            $stmtSub->bindParam(':manager', $currentUser, PDO::PARAM_STR);
            $stmtSub->execute();
            $directReports = $stmtSub->fetchAll(PDO::FETCH_COLUMN);

            foreach ($directReports as $report) {
                if (!isset($visited[$report])) {
                    $subordinates[] = $report;
                    $currentLevel[] = $report;
                }
            }
        }
        $queue = $currentLevel;
        $currentDepth++;
    }

    $user['subordinates'] = array_unique($subordinates);

    echo json_encode($user);
} catch (PDOException $e) {
    echo json_encode(['error' => 'DB error: ' . $e->getMessage()]);
}
