<?php
session_start();

require '../../vendor/autoload.php';
require '../config.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
if (!isset($_GET['download']) || $_GET['download'] != 1) {
    header('Content-Type: application/json');
}

// Increase limits for large uploads
ini_set('memory_limit', '1G');
ini_set('max_execution_time', 300);
set_time_limit(300);

$config = new Config();
$conn = $config->getConnection();
$useruniqueId = $_SESSION['tablename'];

if (isset($_GET['get_users']) && $_GET['get_users'] == 1) {
    // Query to fetch active users
    $userQuery = "SELECT tablename FROM accounts WHERE is_active = 1";
    $userStmt = $conn->prepare($userQuery);
    $userStmt->execute();
    $users = $userStmt->fetchAll(PDO::FETCH_ASSOC);

    // Return users as options
    foreach ($users as $user) {
        echo "<option value='{$user['tablename']}'>{$user['tablename']}</option>";
        echo "$useruniqueId";
    }
    exit; // Terminate script after sending user options
}

if (isset($_GET['get_filter_value']) && $_GET['get_filter_value'] == 1) {
    try {
        $column = $_GET['column'] ?? null;
        $search = $_GET['search'] ?? '';

        // ✅ Include 'status' in allowed columns
        if (!in_array($column, ['name', 'email', 'number', 'location', 'source_of_lead', 'project', 'assign_to_user', 'status'])) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid column']);
            exit();
        }

        // ✅ Special case: assign_to_user with comma-separated values
        if ($column === 'assign_to_user') {
            $stmt = $conn->prepare("SELECT DISTINCT `$column` FROM shi_upload_data WHERE `$column` IS NOT NULL AND `$column` != ''");
            $stmt->execute();
            $values = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $unique_users = [];
            foreach ($values as $value) {
                $userList = explode(',', $value);
                foreach ($userList as $user) {
                    $trimmedUser = trim($user);
                    if (
                        $trimmedUser !== '' &&
                        !in_array($trimmedUser, $unique_users) &&
                        stripos($trimmedUser, $search) !== false
                    ) {
                        $unique_users[] = $trimmedUser;
                    }
                }
            }
            $results = $unique_users;

        // ✅ Special case: status from user_remarks table
        } elseif ($column === 'status') {
            $stmt = $conn->prepare("SELECT DISTINCT `status` FROM user_remarks WHERE `status` IS NOT NULL AND `status` != '' AND `status` LIKE ? ORDER BY `status` ASC LIMIT 20");
            $stmt->execute(["%$search%"]);
            $results = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // ✅ Default: other columns from shi_upload_data
        } else {
            $stmt = $conn->prepare("SELECT DISTINCT `$column` FROM shi_upload_data WHERE `$column` IS NOT NULL AND `$column` != '' AND `$column` LIKE ? ORDER BY `$column` ASC LIMIT 20");
            $stmt->execute(["%$search%"]);
            $results = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }

        echo json_encode(['status' => 'success', 'filters' => $results]);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

if (isset($_GET['get_data']) && $_GET['get_data'] == 1) {
    try {
        // Fetch the total count of rows
        $sql = "SELECT COUNT(*) as total FROM shi_upload_data";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $totalResult = $stmt->fetch(PDO::FETCH_ASSOC);

        // Fetch the count of rows where 'assign_to_user' is empty (unassigned)
        $sqlUnassigned = "SELECT COUNT(*) as unassigned FROM shi_upload_data WHERE assign_to_user IS NULL OR assign_to_user = ''";
        $stmtUnassigned = $conn->prepare($sqlUnassigned);
        $stmtUnassigned->execute();
        $unassignedResult = $stmtUnassigned->fetch(PDO::FETCH_ASSOC);

        // Fetch the count of rows where 'assign_to_user' matches the logged-in user ($useruniqueId)
        $sqlMyLeads = "SELECT COUNT(*) as myLeads FROM shi_upload_data WHERE assign_to_user = :useruniqueId";
        $stmtMyLeads = $conn->prepare($sqlMyLeads);
        $stmtMyLeads->bindParam(':useruniqueId', $useruniqueId);
        $stmtMyLeads->execute();
        $myLeadsResult = $stmtMyLeads->fetch(PDO::FETCH_ASSOC);

        // Fetch the total count of rows from Admin table 
        $sqlbooked = "SELECT COUNT(*) as bookedLeads FROM admintable";
        $stmtbooked = $conn->prepare($sqlbooked);
        $stmtbooked->execute();
        $bookedLeads = $stmtbooked->fetch(PDO::FETCH_ASSOC);

        // Fetch the count of "Active Leads" where the status is NOT "Not Interested" for any users
        $sqlActiveLeads = "
            SELECT COUNT(DISTINCT upload_data_id) as activeLeads
            FROM user_remarks 
            WHERE upload_data_id NOT IN (
                SELECT upload_data_id 
                FROM user_remarks 
                GROUP BY upload_data_id 
                HAVING SUM(CASE WHEN status = 'Not Interested' THEN 1 ELSE 0 END) = COUNT(*)
            )
        ";
        $stmtActiveLeads = $conn->prepare($sqlActiveLeads);
        $stmtActiveLeads->execute();
        $activeLeadsResult = $stmtActiveLeads->fetch(PDO::FETCH_ASSOC);

        // Fetch the count of "Dropped Leads" where ALL statuses for a lead are "Not Interested"
        $sqlDroppedLeads = "
            SELECT COUNT(DISTINCT upload_data_id) as droppedLeads
            FROM user_remarks 
            WHERE upload_data_id IN (
                SELECT upload_data_id 
                FROM user_remarks 
                GROUP BY upload_data_id 
                HAVING SUM(CASE WHEN status = 'Not Interested' THEN 1 ELSE 0 END) = COUNT(*)
            )
        ";
        $stmtDroppedLeads = $conn->prepare($sqlDroppedLeads);
        $stmtDroppedLeads->execute();
        $droppedLeadsResult = $stmtDroppedLeads->fetch(PDO::FETCH_ASSOC);

        // Fetch the count of "Fresh Leads" created in the last 5 days
        $sqlFreshLeads = "
            SELECT COUNT(*) as freshLeads 
            FROM shi_upload_data 
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 5 DAY)
        ";
        $stmtFreshLeads = $conn->prepare($sqlFreshLeads);
        $stmtFreshLeads->execute();
        $freshLeadsResult = $stmtFreshLeads->fetch(PDO::FETCH_ASSOC);

        // Fetch the count of "Pending Leads" where any user has marked the status as "Pending"
        $sqlPendingLeads = "
            SELECT COUNT(DISTINCT upload_data_id) as pendingLeads 
            FROM user_remarks 
            WHERE status = 'Pending'
        ";
        $stmtPendingLeads = $conn->prepare($sqlPendingLeads);
        $stmtPendingLeads->execute();
        $pendingLeadsResult = $stmtPendingLeads->fetch(PDO::FETCH_ASSOC);
        
        $eoicount = "SELECT COUNT(*) as totaleoi FROM usereoidata";
        $eoicounter = $conn->prepare($eoicount);
        $eoicounter->execute();
        $totaleoi = $eoicounter->fetch(PDO::FETCH_ASSOC);
        
        $delete_count = "SELECT COUNT(*) as totaldelete FROM deleted_item";
        $delete_counter = $conn->prepare($delete_count);
        $delete_counter->execute();
        $totaldelete = $delete_counter->fetch(PDO::FETCH_ASSOC);

        // Return all counts as a JSON object
        echo json_encode([
            'total' => $totalResult['total'],
            'unassigned' => $unassignedResult['unassigned'],
            'myLeads' => $myLeadsResult['myLeads'],
            'bookedLeads' => $bookedLeads['bookedLeads'],
            'activeLeads' => $activeLeadsResult['activeLeads'],
            'droppedLeads' => $droppedLeadsResult['droppedLeads'],
            'freshLeads' => $freshLeadsResult['freshLeads'], // Add fresh leads count
            'pendingLeads' => $pendingLeadsResult['pendingLeads'],  // Add dropped leads count
            'totaleoi' => $totaleoi['totaleoi'],
            'totaldelete' => $totaldelete['totaldelete']
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            'total' => 0, 
            'unassigned' => 0, 
            'myLeads' => 0, 
            'bookedLeads' => 0, 
            'activeLeads' => 0, 
            'droppedLeads' => 0,
            'freshLeads' => 0, 
            'pendingLeads' => 0,
            'totaleoi' => 0,
            'totaldelete' =>0 
        ]); // Return 0 for all counts in case of error
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(["status" => "error", "message" => "File upload error: Code " . $_FILES['file']['error']]);
        exit;
    }

    $filePath = $_FILES['file']['tmp_name'];

    try {
        $spreadsheet = IOFactory::load($filePath);
        $sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);

        if (count($sheetData) <= 1) {
            echo json_encode(["status" => "error", "message" => "Excel file seems to be empty or has only headers."]);
            exit;
        }

        // Prepare insert/update statements
        $insertStmt = $conn->prepare("INSERT INTO shi_upload_data (name, email, number, location, created_at, project) 
                                      VALUES (:name, :email, :number, :location, :created_at, :project)");

        $updateStmt = $conn->prepare("UPDATE shi_upload_data 
                                      SET project = :project 
                                      WHERE name = :name AND email = :email AND number = :number AND (project IS NULL OR project = '')");

        // Normalize and collect all unique keys
        $rows = [];
        $keys = [];

        foreach ($sheetData as $index => $row) {
            if ($index == 1) continue; // skip header

            $name = trim($row['A']);
            $email = strtolower(trim($row['B']));
            $number = preg_replace('/\D/', '', $row['C']);
            $location = trim($row['D']);
            $project = trim($row['E']);

            if (!$name || !$email || !$number) continue;

            $key = $name . '|' . $email . '|' . $number;
            $rows[$key] = [
                'name' => $name,
                'email' => $email,
                'number' => $number,
                'location' => $location,
                'project' => $project
            ];
            $keys[] = [$name, $email, $number];
        }

        // Chunk keys to prevent SQL length limits
        $existing = [];
        $chunkSize = 1000;
        for ($i = 0; $i < count($keys); $i += $chunkSize) {
            $chunk = array_slice($keys, $i, $chunkSize);
            $placeholders = implode(',', array_fill(0, count($chunk), '(?, ?, ?)'));

            $flatParams = [];
            foreach ($chunk as $set) {
                $flatParams = array_merge($flatParams, $set);
            }

            $stmt = $conn->prepare("SELECT name, email, number, project 
                                    FROM shi_upload_data 
                                    WHERE (name, email, number) IN ($placeholders)");
            $stmt->execute($flatParams);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $key = $row['name'] . '|' . $row['email'] . '|' . $row['number'];
                $existing[$key] = $row;
            }
        }

        // Start transaction
        $conn->beginTransaction();

        $inserted = $updated = $skipped = 0;
        $errorRows = [];

        foreach ($rows as $key => $data) {
            try {
                if (isset($existing[$key])) {
                    if (empty($existing[$key]['project'])) {
                        $updateStmt->execute([
                            ':project' => $data['project'],
                            ':name' => $data['name'],
                            ':email' => $data['email'],
                            ':number' => $data['number'],
                        ]);
                        $updated++;
                    } else {
                        $skipped++;
                    }
                } else {
                    $insertStmt->execute([
                        ':name' => $data['name'],
                        ':email' => $data['email'],
                        ':number' => $data['number'],
                        ':location' => $data['location'],
                        ':created_at' => (new DateTime('now', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d H:i:s'),
                        ':project' => $data['project'],
                    ]);
                    $inserted++;
                }
            } catch (Exception $e) {
                $skipped++;
                $errorRows[] = $data;
            }
        }

        $conn->commit();

        echo json_encode([
            "status" => "success",
            "message" => "Upload complete. Inserted: $inserted, Updated: $updated, Skipped: $skipped.",
            "errors" => count($errorRows)
        ]);
    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode(["status" => "error", "message" => "Error processing Excel file: " . $e->getMessage()]);
    }
    exit;
}

// Get the parameters from the request this for the pagenation php script 
try {
    // Get query parameters
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $rowsPerPage = isset($_GET['rowsPerPage']) ? (int)$_GET['rowsPerPage'] : 10;
    $searchQuery = isset($_GET['searchQuery']) ? trim($_GET['searchQuery']) : '';
    $multiFilters = isset($_GET['multiFilters']) ? json_decode($_GET['multiFilters'], true) : [];
    $startDate = isset($_GET['startDate']) ? $_GET['startDate'] : '';
    $endDate = isset($_GET['endDate']) ? $_GET['endDate'] : '';
    $startRow = ($page - 1) * $rowsPerPage;
    $showDeletedOnly = isset($_GET['showDeletedOnly']) && $_GET['showDeletedOnly'] == '1';
    $currentFilter = isset($_GET['currentFilter']) ? $_GET['currentFilter'] : '';
    // $download = isset($_GET['download']) && $_GET['download'] == 1; // ✅ NEW

    if (!is_array($multiFilters)) {
        $multiFilters = []; // Ensure it's always an array
    }

    // If date range exists inside multiFilters, override the values
    if (isset($multiFilters['start_date']) && isset($multiFilters['end_date'])) {
        $startDate = $multiFilters['start_date'];
        $endDate = $multiFilters['end_date'];
    }

    // Set table name based on flag
    $tableName = $showDeletedOnly ? 'deleted_item' : 'shi_upload_data';

    // Base query and count query
    $baseQuery = "SELECT * FROM `$tableName` WHERE 1";
    $countQuery = "SELECT COUNT(*) as total FROM `$tableName` WHERE 1";
    
    // Special restriction for NoUser323
    if (trim(strtolower($useruniqueId)) == "nouser323" && $tableName == "shi_upload_data") {
        $allowedProjects = [
            "Godrej Elaris GT",
            "Godrej Raipur Plots Leads",
            "Godrej The GreenFront",
            "Lodha Hinjewadi GT",
            "Godrej Evergreen Square Pune - GT",
            "Godrej Eden Estate Phase 3 Pune - GT",
            "Godrej The Gale Pune-GT",
            "Godrej River Royale Pune",
            "Godrej Nagpur Plots Lead",
            "Lodha Panache GT",
            "Kohinoor launch leads",
            "Kohinoor Launch Leads New",
            "Lodha Prelaunch Leads New",
            "Kohinoor Launch lead New",
            "Godrej Kharadi",
            "Godrej The Aqua Retreat",
            "Godrej Emerald Waters",
            "Godrej Aqua Retreat",
            "Lodha Panache - New tower",
            "Godrej Raipur Plot"
        ];
    
        // Normalize (case-insensitive)
        $normalized = array_map(function ($p) {
            return "'" . strtolower(trim(addslashes($p))) . "'";
        }, $allowedProjects);
    
        $projectList = implode(",", $normalized);
    
        // Apply safe filter
        $baseQuery  .= " AND LOWER(REGEXP_REPLACE(TRIM(project), '[[:space:]]+', ' ')) IN ($projectList)";
        $countQuery .= " AND LOWER(REGEXP_REPLACE(TRIM(project), '[[:space:]]+', ' ')) IN ($projectList)";
    }

    $queryParams = []; // Parameters for binding

    $validStatuses = [
        'Active', 'New', 'Pending', 'Dropped', 'Fake', 'RNR', 'Call Back', 'Already Booked',
        'Not Interested', 'Interested', 'Follow Up', 'Fix Site Visit', 'Site Visit Done',
        'Converted', 'Not Connected'
    ];

    if (in_array($currentFilter, $validStatuses)) {
        // Filter by a specific status
        $baseQuery .= " AND status = :statusFilter";
        $countQuery .= " AND status = :statusFilter";
        $queryParams[':statusFilter'] = $currentFilter;

    } elseif ($currentFilter === 'my') {
        // Filter by currently assigned user
        $baseQuery .= " AND assign_to_user = :currentUser";
        $countQuery .= " AND assign_to_user = :currentUser";
        $queryParams[':currentUser'] = $useruniqueId;

    } elseif ($currentFilter === 'unassigned') {
        // Filter leads with no assignment
        $baseQuery .= " AND (assign_to_user IS NULL OR assign_to_user = '')";
        $countQuery .= " AND (assign_to_user IS NULL OR assign_to_user = '')";

    } elseif ($currentFilter === 'dropped') {
        // Filter dropped leads based on user_remarks subquery
        $baseQuery .= " AND id IN (
            SELECT upload_data_id FROM user_remarks 
            GROUP BY upload_data_id 
            HAVING SUM(CASE WHEN status = 'Not Interested' THEN 1 ELSE 0 END) = COUNT(*)
        )";
        $countQuery .= " AND id IN (
            SELECT upload_data_id FROM user_remarks 
            GROUP BY upload_data_id 
            HAVING SUM(CASE WHEN status = 'Not Interested' THEN 1 ELSE 0 END) = COUNT(*)
        )";

    } elseif ($currentFilter === 'active') {
        // Filter active leads based on user_remarks subquery
        $baseQuery .= " AND id NOT IN (
            SELECT upload_data_id FROM user_remarks 
            GROUP BY upload_data_id 
            HAVING SUM(CASE WHEN status IN ('Not Interested', 'Fake', 'Already Booked') THEN 1 ELSE 0 END) = COUNT(*)
        )";
        $countQuery .= " AND id IN (
            SELECT upload_data_id 
            FROM user_remarks 
            GROUP BY upload_data_id 
            HAVING SUM(CASE WHEN status IN ('Not Interested', 'Fake', 'Already Booked') THEN 1 ELSE 0 END) < COUNT(*)
        )";
    } elseif ($currentFilter === 'pending') {
        $baseQuery .= " AND id IN (
            SELECT DISTINCT upload_data_id FROM user_remarks WHERE status = 'Pending'
        )";
        $countQuery .= " AND id IN (
            SELECT DISTINCT upload_data_id FROM user_remarks WHERE status = 'Pending'
        )";
    } elseif ($currentFilter === 'fresh') {
        $baseQuery .= " AND created_at >= DATE_SUB(CURDATE(), INTERVAL 5 DAY)";
        $countQuery .= " AND created_at >= DATE_SUB(CURDATE(), INTERVAL 5 DAY)";
    }

    // Multi-filters
    foreach ($multiFilters as $column => $values) {
        if (!empty($values) && is_array($values)) {
            if ($column === 'status') {
                // Handle status filtering using user_remarks table
                $statusConditions = [];
                foreach ($values as $index => $statusVal) {
                    $paramKey = ":status_filter_$index";
                    $statusConditions[] = $paramKey;
                    $queryParams[$paramKey] = $statusVal;
                }
    
                $statusConditionStr = implode(", ", $statusConditions);
    
                $baseQuery .= " AND id IN (
                    SELECT upload_data_id FROM user_remarks 
                    WHERE status IN ($statusConditionStr)
                )";
    
                $countQuery .= " AND id IN (
                    SELECT upload_data_id FROM user_remarks 
                    WHERE status IN ($statusConditionStr)
                )";
            } else {
                // Normal filter for main table columns
                $filterConditions = [];
                foreach ($values as $index => $val) {
                    $paramKey = ":filter_{$column}_$index";
                    $filterConditions[] = "`$column` LIKE $paramKey";
                    $queryParams[$paramKey] = "%$val%";
                }
                $condition = " AND (" . implode(" OR ", $filterConditions) . ")";
                $baseQuery .= $condition;
                $countQuery .= $condition;
            }
        }
    }

    // Search across multiple columns
    if (!empty($searchQuery)) {
        $searchColumns = ['name', 'email', 'number', 'location', 'source_of_lead', 'project', 'assign_to_user'];
        $searchConditions = [];
        foreach ($searchColumns as $column) {
            $paramKey = ":search_{$column}";
            $searchConditions[] = "`$column` LIKE $paramKey";
            $queryParams[$paramKey] = "%$searchQuery%";
        }
        $condition = " AND (" . implode(' OR ', $searchConditions) . ")";
        $baseQuery .= $condition;
        $countQuery .= $condition;
    }

    // Date filter
    if (!empty($startDate) && !empty($endDate)) {
        $baseQuery .= " AND DATE(`created_at`) BETWEEN :startDate AND :endDate";
        $countQuery .= " AND DATE(`created_at`) BETWEEN :startDate AND :endDate";
        $queryParams[':startDate'] = $startDate;
        $queryParams[':endDate'] = $endDate;
    }

    // Order by created_at if available
    $baseQuery .= " ORDER BY created_at DESC";

    // Add pagination
    $baseQuery .= " LIMIT :startRow, :rowsPerPage";
    $queryParams[':startRow'] = $startRow;
    $queryParams[':rowsPerPage'] = $rowsPerPage;

    // Execute main data query
    $stmt = $conn->prepare($baseQuery);
    foreach ($queryParams as $key => $val) {
        if ($key === ':startRow' || $key === ':rowsPerPage') {
            $stmt->bindValue($key, $val, PDO::PARAM_INT);
        } elseif ($key === ':startDate' || $key === ':endDate') {
            $stmt->bindValue($key, $val, PDO::PARAM_STR);
        } else {
            $stmt->bindValue($key, $val, PDO::PARAM_STR);
        }
    }
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Execute count query
    $countStmt = $conn->prepare($countQuery);
    foreach ($queryParams as $key => $val) {
        if ($key !== ':startRow' && $key !== ':rowsPerPage') {
            $countStmt->bindValue($key, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
    }
    $countStmt->execute();
    $totalRows = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Return JSON response
    echo json_encode([
        'data' => $data,
        'totalRows' => (int)$totalRows,
        'currentPage' => $page,
        'rowsPerPage' => $rowsPerPage,
    ]);

} catch (Exception $e) {
    file_put_contents('debug.log', "Error: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}
?>