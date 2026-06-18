<?php
session_start();
require_once 'config.php';
header('Content-Type: application/json');

// Validate session variable
if (!isset($_SESSION['tablename'])) {
    echo json_encode(['error' => "Session variable 'tablename' not set."]);
    exit;
}

$tablename = $_SESSION['tablename'];
$userType = $_SESSION['user_type'] ?? 'user';

// Hierarchy helper functions (copied from update_status.php)
function normalize_role($rawType) {
    $s = strtolower(trim((string)$rawType));
    switch ($s) {
        case 'p':
        case 'promoter':
        case 'ceo':
        case 'c':
            return 'promoter';
        case 'd':
        case 'director':
        case 'bh':
        case 'bhead':
        case 'business head':
        case 'business_head':
            return 'business_head';
        case 'm':
        case 'manager':
            return 'manager';
        case 'tl':
        case 'lead':
        case 'team lead':
        case 'team_lead':
            return 'team_lead';
        case 'u':
        case 'user':
        case 'sales':
        case 'sales_executive':
        case 'sales executive':
        case 'se':
            return 'user';
        default:
            return 'user';
    }
}

function role_level($normalizedRole) {
    $map = [
        'promoter' => 1,
        'business_head' => 2,
        'manager' => 3,
        'team_lead' => 4,
        'user' => 5,
    ];
    return $map[strtolower(trim((string)$normalizedRole))] ?? 5;
}

function get_accessible_users(PDO $conn, $userTablename, $userRole) {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'superuseradmin') {
        $stmt = $conn->prepare("SELECT tablename FROM accounts WHERE is_active = 1");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    $accessible = [$userTablename];
    $normalizedRole = normalize_role($userRole);
    $userLevel = role_level($normalizedRole);
    
    if ($userLevel < 5) {
        $subordinates = [];
        $visited = [];
        $queue = [$userTablename];
        $maxDepth = 10;
        $currentDepth = 0;
        
        while (!empty($queue) && $currentDepth < $maxDepth) {
            $currentLevel = [];
            foreach ($queue as $currentUser) {
                if (isset($visited[$currentUser])) continue;
                $visited[$currentUser] = true;
                
                    $stmt = $conn->prepare("
                        SELECT tablename FROM accounts 
                        WHERE FIND_IN_SET(:manager, assign_user) > 0 AND is_active = 1
                    ");
                $stmt->bindParam(':manager', $currentUser, PDO::PARAM_STR);
                $stmt->execute();
                $directReports = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
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
        $accessible = array_merge($accessible, $subordinates);
    }
    return array_unique($accessible);
}

function get_total_eoi_count(PDO $conn, array $users) {
    if (empty($users)) {
        return 0;
    }

    $placeholders = implode(',', array_fill(0, count($users), '?'));
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM usereoidata WHERE source_table IN ($placeholders)");

    foreach (array_values($users) as $index => $user) {
        $stmt->bindValue($index + 1, $user, PDO::PARAM_STR);
    }

    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    return $result ? (int)$result['count'] : 0;
}

try {
    $config = new Config();
    $conn = $config->getConnection();

    // Get the selected user from query parameter (default to all accessible EOIs)
    $selectedUser = isset($_GET['selected_user']) ? $_GET['selected_user'] : 'all';
    
    // Get all accessible users for the current logged-in user
    $accessibleUsers = get_accessible_users($conn, $tablename, $userType);
    
    // Security check: ensure selected user is accessible to current user (allow "all" aggregate)
    if ($selectedUser !== 'all' && !in_array($selectedUser, $accessibleUsers)) {
        echo json_encode(['error' => 'Access denied to selected user data.']);
        exit;
    }

    // If request is for user hierarchy data
    if (isset($_GET['get_hierarchy']) && $_GET['get_hierarchy'] == '1') {
        $hierarchyData = [];
        
        // All EOI aggregate entry
        $totalCount = get_total_eoi_count($conn, $accessibleUsers);

        $hierarchyData[] = [
            'user' => 'all',
            'name' => 'All EOI',
            'count' => $totalCount,
            'is_current_user' => false,
            'is_all' => true
        ];

        // Get current user's EOI count
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM usereoidata WHERE source_table = :tablename");
        $stmt->bindParam(':tablename', $tablename, PDO::PARAM_STR);
        $stmt->execute();
        $myCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        $hierarchyData[] = [
            'user' => $tablename,
            'name' => 'My EOI',
            'count' => $myCount,
            'is_current_user' => true
        ];
        
        // Get subordinate users with their EOI counts
        foreach ($accessibleUsers as $user) {
            if ($user !== $tablename) {
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM usereoidata WHERE source_table = :user");
                $stmt->bindParam(':user', $user, PDO::PARAM_STR);
                $stmt->execute();
                $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                
                // Get user's actual name from accounts table
                $nameStmt = $conn->prepare("SELECT username FROM accounts WHERE tablename = :tablename");
                $nameStmt->bindParam(':tablename', $user, PDO::PARAM_STR);
                $nameStmt->execute();
                $userName = $nameStmt->fetch(PDO::FETCH_ASSOC);
                
                $hierarchyData[] = [
                    'user' => $user,
                    'name' => $userName ? $userName['username'] : $user,
                    'count' => $count,
                    'is_current_user' => false
                ];
            }
        }
        
        echo json_encode($hierarchyData);
        exit;
    }

    // Fetch EOI data for selected user
    if ($selectedUser === 'all') {
        $placeholders = implode(',', array_fill(0, count($accessibleUsers), '?'));
        $sql = "SELECT * FROM usereoidata WHERE source_table IN ($placeholders) ORDER BY id DESC";
        $stmt = $conn->prepare($sql);

        foreach (array_values($accessibleUsers) as $index => $user) {
            $stmt->bindValue($index + 1, $user, PDO::PARAM_STR);
        }

        $stmt->execute();
    } else {
        $sql = "SELECT * FROM usereoidata WHERE source_table = :selectedUser ORDER BY id DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':selectedUser', $selectedUser, PDO::PARAM_STR);
        $stmt->execute();
    }

    $data = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $data[] = [
            "id" => $row['id'],
            "bookingDate" => $row['booking_date'],
            "bookingMonth" => $row['booking_month'],
            "builderName" => $row['builder'],
            "projectName" => $row['project'],
            "customerName" => $row['customer_name'],
            "contactNumber" => $row['contact_number'],
            "email" => $row['email_id'],
            "projectType" => $row['project_type'],
            "leadSource" => $row['source_lead'] ?? '',
            "remarks" => $row['remarks'] ?? '',
            "sourceTable" => $row['source_table']
        ];
    }

    // DataTables expects {"data": [...]} or sometimes a plain array
    echo json_encode($data);

} catch (PDOException $e) {
    echo json_encode(['error' => "Error: " . $e->getMessage()]);
}
?>
