<?php
session_start();

// Optional: enable verbose PHP errors when debug=1 is passed (development only)
if (isset($_GET['debug']) && $_GET['debug'] == '1') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

// Safe includes: try multiple vendor paths (same as superadmin_new uses)
$psAvailable = false;
$autoloadCandidates = [
    __DIR__ . '/../../vendor/autoload.php', // superadmin/vendor/ (old path)
    __DIR__ . '/../../../vendor/autoload.php', // root-level vendor/ (same as superadmin_new)
    __DIR__ . '/../../../../vendor/autoload.php', // one level higher
];
foreach ($autoloadCandidates as $autoloadPath) {
    if (file_exists($autoloadPath)) {
        require $autoloadPath;
        $psAvailable = true;
        break;
    }
}

$configPath = __DIR__ . '/../config.php';
if (!file_exists($configPath)) {
    // Return a JSON error so clients don't receive a blank 500 page
    if (!isset($_GET['download']) || $_GET['download'] != 1) {
        header('Content-Type: application/json');
    }
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Server configuration missing: config.php not found']);
    exit;
}
require $configPath;

// Note: Do NOT place `use` statements inside conditional blocks — that causes a PHP parse error
// when the file is parsed. We'll reference PhpSpreadsheet classes with fully-qualified names
// when needed (e.g. `\PhpOffice\PhpSpreadsheet\IOFactory::load(...)`).
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

const UPLOAD_CHUNK_SIZE = 100;

function getUploadJobsDir()
{
    $dir = __DIR__ . '/tmp_upload_jobs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

function getUploadJobPaths($jobId)
{
    $safe = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$jobId);
    $base = rtrim(getUploadJobsDir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $safe;
    return [
        'meta' => $base . '.meta.json',
        'rows' => $base . '.rows.json',
    ];
}

function buildNormalizedUploadRows($sheetData)
{
    $rows = [];
    foreach ($sheetData as $index => $row) {
        if ($index == 1)
            continue; // header

        $name = trim((string)($row['A'] ?? ''));
        $email = strtolower(trim((string)($row['B'] ?? '')));
        $number = preg_replace('/\D/', '', (string)($row['C'] ?? ''));
        $location = trim((string)($row['D'] ?? ''));
        $project = trim((string)($row['E'] ?? ''));
        $source_of_lead = trim((string)($row['F'] ?? ''));
        if ($source_of_lead === '')
            $source_of_lead = 'NFS';

        if (!$name || !$email || !$number)
            continue;

        $key = $name . '|' . $email . '|' . $number;
        $rows[$key] = [
            'name' => $name,
            'email' => $email,
            'number' => $number,
            'location' => $location,
            'project' => $project,
            'source_of_lead' => $source_of_lead,
        ];
    }
    return array_values($rows);
}

function fetchExistingRowsMapForChunk($conn, $chunkRows)
{
    if (empty($chunkRows))
        return [];

    $chunkKeys = [];
    foreach ($chunkRows as $r) {
        $chunkKeys[] = [$r['name'], $r['email'], $r['number']];
    }

    $placeholders = implode(',', array_fill(0, count($chunkKeys), '(?, ?, ?)'));
    $flatParams = [];
    foreach ($chunkKeys as $set) {
        $flatParams[] = $set[0];
        $flatParams[] = $set[1];
        $flatParams[] = $set[2];
    }

    $stmt = $conn->prepare("SELECT name, email, number, project FROM shi_upload_data WHERE (name, email, number) IN ($placeholders)");
    $stmt->execute($flatParams);

    $existing = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $k = $row['name'] . '|' . $row['email'] . '|' . $row['number'];
        $existing[$k] = $row;
    }
    return $existing;
}

function processUploadChunk($conn, $allRows, $offset, $chunkSize)
{
    $chunkRows = array_slice($allRows, $offset, $chunkSize);
    if (empty($chunkRows)) {
        return [
            'processed' => 0,
            'inserted' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];
    }

    $existing = fetchExistingRowsMapForChunk($conn, $chunkRows);

    $insertStmt = $conn->prepare("\n        INSERT INTO shi_upload_data \n        (name, email, number, location, created_at, project, source_of_lead) \n        VALUES (:name, :email, :number, :location, :created_at, :project, :source_of_lead)\n    ");

    $updateStmt = $conn->prepare("\n        UPDATE shi_upload_data \n        SET project = :project, source_of_lead = :source_of_lead\n        WHERE name = :name \n        AND email = :email \n        AND number = :number \n        AND (project IS NULL OR project = '')\n    ");

    $inserted = 0;
    $updated = 0;
    $skipped = 0;
    $errors = 0;

    $conn->beginTransaction();
    try {
        foreach ($chunkRows as $data) {
            try {
                $key = $data['name'] . '|' . $data['email'] . '|' . $data['number'];
                if (isset($existing[$key])) {
                    if (empty($existing[$key]['project'])) {
                        $updateStmt->execute([
                            ':project' => $data['project'],
                            ':source_of_lead' => $data['source_of_lead'],
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
                        ':source_of_lead' => $data['source_of_lead'],
                    ]);
                    $inserted++;
                }
            } catch (Throwable $e) {
                $skipped++;
                $errors++;
            }
        }

        $conn->commit();
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        throw $e;
    }

    return [
        'processed' => count($chunkRows),
        'inserted' => $inserted,
        'updated' => $updated,
        'skipped' => $skipped,
        'errors' => $errors,
    ];
}

function finalizeUploadProgress($meta)
{
    $total = max(0, (int)($meta['total_rows'] ?? 0));
    $processed = max(0, (int)($meta['processed_rows'] ?? 0));
    $percent = ($total > 0) ? (int)floor(($processed / $total) * 100) : 100;
    if ($percent > 100)
        $percent = 100;
    $done = $processed >= $total;
    return [
        'job_id' => $meta['job_id'] ?? '',
        'total_rows' => $total,
        'processed_rows' => $processed,
        'percent' => $percent,
        'inserted' => (int)($meta['inserted'] ?? 0),
        'updated' => (int)($meta['updated'] ?? 0),
        'skipped' => (int)($meta['skipped'] ?? 0),
        'errors' => (int)($meta['errors'] ?? 0),
        'done' => $done,
    ];
}

if (($_GET['action'] ?? '') === 'start_upload' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!isset($_FILES['file'])) {
            throw new RuntimeException('No file provided');
        }
        if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('File upload error: Code ' . $_FILES['file']['error']);
        }

        $ioFactoryClass = '\\PhpOffice\\PhpSpreadsheet\\IOFactory';
        if (!$psAvailable || !class_exists($ioFactoryClass)) {
            throw new RuntimeException('Excel parser not available on server (PhpSpreadsheet not loaded).');
        }

        $spreadsheet = $ioFactoryClass::load($_FILES['file']['tmp_name']);
        $sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
        if (count($sheetData) <= 1) {
            throw new RuntimeException('Excel file seems to be empty or has only headers.');
        }

        $rows = buildNormalizedUploadRows($sheetData);
        if (empty($rows)) {
            throw new RuntimeException('No valid lead rows found in file.');
        }

        $jobId = bin2hex(random_bytes(16));
        $paths = getUploadJobPaths($jobId);

        $meta = [
            'job_id' => $jobId,
            'created_by' => $useruniqueId,
            'created_at' => date('c'),
            'status' => 'processing',
            'total_rows' => count($rows),
            'processed_rows' => 0,
            'inserted' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];

        file_put_contents($paths['rows'], json_encode($rows, JSON_UNESCAPED_UNICODE));

        // Process first chunk immediately so user sees instant progress.
        $first = processUploadChunk($conn, $rows, 0, UPLOAD_CHUNK_SIZE);
        $meta['processed_rows'] += $first['processed'];
        $meta['inserted'] += $first['inserted'];
        $meta['updated'] += $first['updated'];
        $meta['skipped'] += $first['skipped'];
        $meta['errors'] += $first['errors'];
        if ($meta['processed_rows'] >= $meta['total_rows']) {
            $meta['status'] = 'done';
            @unlink($paths['rows']);
        }

        file_put_contents($paths['meta'], json_encode($meta, JSON_UNESCAPED_UNICODE));

        echo json_encode([
            'status' => 'success',
            'message' => 'Upload started',
            'progress' => finalizeUploadProgress($meta),
        ]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to start upload: ' . $e->getMessage(),
        ]);
    }
    exit;
}

if (($_GET['action'] ?? '') === 'process_upload' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $jobId = trim((string)($_GET['job_id'] ?? $_POST['job_id'] ?? ''));
        if ($jobId === '') {
            throw new RuntimeException('job_id is required');
        }

        $paths = getUploadJobPaths($jobId);
        if (!file_exists($paths['meta'])) {
            throw new RuntimeException('Upload job not found');
        }

        $meta = json_decode((string)file_get_contents($paths['meta']), true);
        if (!is_array($meta)) {
            throw new RuntimeException('Invalid job metadata');
        }

        if (($meta['status'] ?? '') !== 'done') {
            if (!file_exists($paths['rows'])) {
                throw new RuntimeException('Upload job data missing');
            }

            $rows = json_decode((string)file_get_contents($paths['rows']), true);
            if (!is_array($rows)) {
                throw new RuntimeException('Invalid upload job rows');
            }

            $offset = (int)($meta['processed_rows'] ?? 0);
            $res = processUploadChunk($conn, $rows, $offset, UPLOAD_CHUNK_SIZE);
            $meta['processed_rows'] = $offset + (int)$res['processed'];
            $meta['inserted'] = (int)($meta['inserted'] ?? 0) + (int)$res['inserted'];
            $meta['updated'] = (int)($meta['updated'] ?? 0) + (int)$res['updated'];
            $meta['skipped'] = (int)($meta['skipped'] ?? 0) + (int)$res['skipped'];
            $meta['errors'] = (int)($meta['errors'] ?? 0) + (int)$res['errors'];

            if ((int)$meta['processed_rows'] >= (int)$meta['total_rows']) {
                $meta['status'] = 'done';
                @unlink($paths['rows']);
            }

            file_put_contents($paths['meta'], json_encode($meta, JSON_UNESCAPED_UNICODE));
        }

        $progress = finalizeUploadProgress($meta);
        $msg = $progress['done']
            ? ('Upload complete. Inserted: ' . $progress['inserted'] . ', Updated: ' . $progress['updated'] . ', Skipped: ' . $progress['skipped'] . '.')
            : 'Processing in progress';

        echo json_encode([
            'status' => 'success',
            'message' => $msg,
            'progress' => $progress,
        ]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to process upload: ' . $e->getMessage(),
        ]);
    }
    exit;
}

if (isset($_GET['get_users']) && $_GET['get_users'] == 1) {
    // Query to fetch active users
    $userQuery = "SELECT tablename FROM accounts WHERE is_active = 1";
    $userStmt = $conn->prepare($userQuery);
    $userStmt->execute();
    $users = $userStmt->fetchAll(PDO::FETCH_ASSOC);

    // Return users as options
    header('Content-Type: text/html; charset=utf-8');
    foreach ($users as $user) {
        echo "<option value='{$user['tablename']}'>{$user['tablename']}</option>";
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
        }
        elseif ($column === 'status') {
            $stmt = $conn->prepare("SELECT DISTINCT `status` FROM user_remarks WHERE `status` IS NOT NULL AND `status` != '' AND `status` LIKE ? ORDER BY `status` ASC LIMIT 20");
            $stmt->execute(["%$search%"]);
            $results = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // ✅ Default: other columns from shi_upload_data
        }
        else {
            $stmt = $conn->prepare("SELECT DISTINCT `$column` FROM shi_upload_data WHERE `$column` IS NOT NULL AND `$column` != '' AND `$column` LIKE ? ORDER BY `$column` ASC LIMIT 20");
            $stmt->execute(["%$search%"]);
            $results = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }

        echo json_encode(['status' => 'success', 'filters' => $results]);
    }
    catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit();
}

if (isset($_GET['get_filtered_counts']) && $_GET['get_filtered_counts'] == 1) {
    try {
        // Get filter parameters
        $searchQuery = isset($_GET['searchQuery']) ? trim($_GET['searchQuery']) : '';
        $multiFilters = isset($_GET['multiFilters']) ? json_decode($_GET['multiFilters'], true) : [];
        $currentFilter = isset($_GET['currentFilter']) ? $_GET['currentFilter'] : '';
        $showDeletedOnly = isset($_GET['showDeletedOnly']) && $_GET['showDeletedOnly'] == '1';

        if (!is_array($multiFilters)) {
            $multiFilters = [];
        }

        // Build base WHERE clause
        $tableName = $showDeletedOnly ? 'deleted_item' : 'shi_upload_data';
        $whereClause = " WHERE 1";
        $queryParams = [];

        $latestStatusSubquery = "
            SELECT ur.upload_data_id, ur.status
            FROM user_remarks ur
            INNER JOIN (
                SELECT upload_data_id, MAX(id) AS max_id
                FROM user_remarks
                GROUP BY upload_data_id
            ) latest ON latest.upload_data_id = ur.upload_data_id AND latest.max_id = ur.id
        ";

        // Apply same filters as main query
        if (trim(strtolower($useruniqueId)) == "nouser323" && $tableName == "shi_upload_data") {
            $allowedProjects = [
                "Godrej Rajendranagar", "Ramky Rajendranagar", "Godrej Shettigere Rd",
                "Godrej Thanisandra", "Godrej Thanisandra New", "Godrej Green Pune",
                "Godrej Aqua", "Godrej Azure", "Untitled form 23/09/2025, 14:10",
                "Godrej aqua", "Godrej regal pavilion L hyd", "GLO new plan",
                "godrej Gale", "godrej varodara", "Godrej Greenfront", "Godej Azure",
                "Godrej MSR City", "godrej doddaballapur plot", "Lodha Hinjewadi",
                "godrej doddaballapur plot-copy", "brigade hyd", "vadodara plt 2",
                "Godrej Thanisandra New Form", "Godrej Magarpatta", "Godrej hoskote",
                "godrej magarpatta", "Godrej Magarpatta-OTP Vrified"
            ];
            $normalized = array_map(function ($p) {
                return "'" . strtolower(trim(addslashes($p))) . "'";
            }, $allowedProjects);
            $projectList = implode(",", $normalized);
            $whereClause .= " AND LOWER(REGEXP_REPLACE(TRIM(project), '[[:space:]]+', ' ')) IN ($projectList)";
        }

        // Apply currentFilter (align with main query logic)
        $validStatuses = [
            'Active', 'New', 'Pending', 'Dropped', 'Fake', 'RNR', 'Call Back', 'Already Booked',
            'Not Interested', 'Interested', 'Follow Up', 'Fix Site Visit', 'Site Visit Done',
            'Converted', 'Not Connected'
        ];

        if ($currentFilter === 'my') {
            $whereClause .= " AND assign_to_user = :currentUser";
            $queryParams[':currentUser'] = $useruniqueId;
        }
        elseif ($currentFilter === 'unassigned') {
            $whereClause .= " AND (assign_to_user IS NULL OR assign_to_user = '')";
        }
        elseif (in_array($currentFilter, $validStatuses, true)) {
            $whereClause .= " AND id IN (
                SELECT ls.upload_data_id FROM ($latestStatusSubquery) AS ls WHERE ls.status = :statusFilter
            )";
            $queryParams[':statusFilter'] = $currentFilter;
        }

        // Apply multiFilters (match main query logic, including status via user_remarks)
        foreach ($multiFilters as $column => $values) {
            if (!empty($values) && is_array($values)) {
                if ($column === 'status') {
                    $statusConditions = [];
                    foreach ($values as $index => $statusVal) {
                        $paramKey = ":status_filter_$index";
                        $statusConditions[] = $paramKey;
                        $queryParams[$paramKey] = $statusVal;
                    }

                    if (!empty($statusConditions)) {
                        $statusList = implode(', ', $statusConditions);
                        $whereClause .= " AND id IN (
                            SELECT ls.upload_data_id FROM ($latestStatusSubquery) AS ls
                            WHERE ls.status IN ($statusList)
                        )";
                    }
                }
                else {
                    $filterConditions = [];
                    foreach ($values as $index => $val) {
                        $paramKey = ":filter_{$column}_$index";
                        $filterConditions[] = "`$column` LIKE $paramKey";
                        $queryParams[$paramKey] = "%$val%";
                    }
                    $whereClause .= " AND (" . implode(" OR ", $filterConditions) . ")";
                }
            }
        }

        // Date range (matches main query logic)
        $startDate = $multiFilters['start_date'] ?? '';
        $endDate = $multiFilters['end_date'] ?? '';
        if (!empty($startDate) && !empty($endDate)) {
            $whereClause .= " AND DATE(`created_at`) BETWEEN :startDate AND :endDate";
            $queryParams[':startDate'] = $startDate;
            $queryParams[':endDate'] = $endDate;
        }

        // Apply search
        if (!empty($searchQuery)) {
            $searchColumns = ['name', 'email', 'number', 'location', 'source_of_lead', 'project', 'assign_to_user'];
            $searchConditions = [];
            foreach ($searchColumns as $column) {
                $paramKey = ":search_{$column}";
                $searchConditions[] = "`$column` LIKE $paramKey";
                $queryParams[$paramKey] = "%$searchQuery%";
            }
            $whereClause .= " AND (" . implode(' OR ', $searchConditions) . ")";
        }

        // Calculate counts
        // 1. Total filtered count
        $totalSql = "SELECT COUNT(*) as total FROM `$tableName` $whereClause";
        $totalStmt = $conn->prepare($totalSql);
        foreach ($queryParams as $key => $val) {
            $totalStmt->bindValue($key, $val, PDO::PARAM_STR);
        }
        $totalStmt->execute();
        $total = $totalStmt->fetch(PDO::FETCH_ASSOC)['total'];

        // 2. Unassigned count (from filtered data)
        $unassignedSql = "SELECT COUNT(*) as unassigned FROM `$tableName` $whereClause AND (assign_to_user IS NULL OR assign_to_user = '')";
        $unassignedStmt = $conn->prepare($unassignedSql);
        foreach ($queryParams as $key => $val) {
            $unassignedStmt->bindValue($key, $val, PDO::PARAM_STR);
        }
        $unassignedStmt->execute();
        $unassigned = $unassignedStmt->fetch(PDO::FETCH_ASSOC)['unassigned'];

        // 3. My Leads count
        $myLeadsSql = "SELECT COUNT(*) as myLeads FROM `$tableName` $whereClause AND assign_to_user = :myUser";
        $myLeadsStmt = $conn->prepare($myLeadsSql);
        $myLeadsStmt->bindValue(':myUser', $useruniqueId, PDO::PARAM_STR);
        foreach ($queryParams as $key => $val) {
            $myLeadsStmt->bindValue($key, $val, PDO::PARAM_STR);
        }
        $myLeadsStmt->execute();
        $myLeads = $myLeadsStmt->fetch(PDO::FETCH_ASSOC)['myLeads'];

        // 4-9. Counts that don't change with filters (global counts)
        // These remain the same regardless of current filter
        $eoiSql = "SELECT COUNT(*) as totaleoi FROM usereoidata";
        $eoiStmt = $conn->prepare($eoiSql);
        $eoiStmt->execute();
        $totaleoi = $eoiStmt->fetch(PDO::FETCH_ASSOC)['totaleoi'];

        // Deleted leads count should respect current filters/search and exclude recovered leads
        // $whereClause already contains " WHERE 1", so we just need to add our condition
        $deletedSql = "SELECT COUNT(*) as totaldelete FROM deleted_item $whereClause AND (recovered IS NULL OR recovered = 0)";
        $deletedStmt = $conn->prepare($deletedSql);
        foreach ($queryParams as $key => $val) {
            $deletedStmt->bindValue($key, $val, PDO::PARAM_STR);
        }
        $deletedStmt->execute();
        $totaldelete = $deletedStmt->fetch(PDO::FETCH_ASSOC)['totaldelete'];

        // For status-based counts, use the filtered IDs
        $idsSql = "SELECT id FROM `$tableName` $whereClause";
        $idsStmt = $conn->prepare($idsSql);
        foreach ($queryParams as $key => $val) {
            $idsStmt->bindValue($key, $val, PDO::PARAM_STR);
        }
        $idsStmt->execute();
        $filteredIds = $idsStmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($filteredIds)) {
            $activeLeads = 0;
            $droppedLeads = 0;
            $freshLeads = 0;
            $pendingLeads = 0;
            $bookedLeads = 0;
            $todayCollection = 0;
            $paidAds = 0;
            $shiD = 0;
            $followLeads = 0;
            $overdueLeads = 0;
        }
        else {
            $idsPlaceholder = implode(',', array_fill(0, count($filteredIds), '?'));
            $todayDate = date('Y-m-d');
            $todayStart = $todayDate . ' 00:00:00';
            $todayEnd = $todayDate . ' 23:59:59';
            $adsSources = "('google ads', 'facebook ads', 'magicbricks ads', '99acres ads', 'housing.com ads')";
            $overdueStatuses = "('Follow Up', 'RNR', 'Call Back', 'Pending', 'Interested', 'Fix Site Visit', 'Site Visit Done', 'Re site visit', 'Not Connected')";
            $overdueCutoff = (new DateTime('now', new DateTimeZone('Asia/Kolkata')))->modify('-1 day')->format('Y-m-d H:i:s');

            // Active leads (same definition as main query: exclude leads whose statuses are entirely NI/Fake/Already Booked)
            $activeSql = "SELECT COUNT(*) AS activeLeads FROM (
                SELECT upload_data_id
                FROM user_remarks
                WHERE upload_data_id IN ($idsPlaceholder)
                GROUP BY upload_data_id
                HAVING SUM(CASE WHEN status IN ('Not Interested','Fake','Already Booked') THEN 1 ELSE 0 END) < COUNT(*)
            ) AS active_ids";
            $activeStmt = $conn->prepare($activeSql);
            foreach ($filteredIds as $idx => $id) {
                $activeStmt->bindValue($idx + 1, $id, PDO::PARAM_INT);
            }
            $activeStmt->execute();
            $activeLeads = $activeStmt->fetch(PDO::FETCH_ASSOC)['activeLeads'];

            // Dropped leads
            $droppedSql = "SELECT COUNT(DISTINCT upload_data_id) as droppedLeads
                FROM user_remarks 
                WHERE upload_data_id IN ($idsPlaceholder)
                AND upload_data_id IN (
                    SELECT upload_data_id FROM user_remarks 
                    WHERE upload_data_id IN ($idsPlaceholder)
                    GROUP BY upload_data_id 
                    HAVING SUM(CASE WHEN status = 'Not Interested' THEN 1 ELSE 0 END) = COUNT(*)
                )";
            $droppedStmt = $conn->prepare($droppedSql);
            foreach ($filteredIds as $idx => $id) {
                $droppedStmt->bindValue($idx + 1, $id, PDO::PARAM_INT);
            }
            foreach ($filteredIds as $idx => $id) {
                $droppedStmt->bindValue(count($filteredIds) + $idx + 1, $id, PDO::PARAM_INT);
            }
            $droppedStmt->execute();
            $droppedLeads = $droppedStmt->fetch(PDO::FETCH_ASSOC)['droppedLeads'];

            // Fresh leads (created today from filtered set)
            $freshSql = "SELECT COUNT(*) as freshLeads 
                FROM `$tableName` 
                WHERE id IN ($idsPlaceholder)
                AND DATE(created_at) = CURDATE()";
            $freshStmt = $conn->prepare($freshSql);
            foreach ($filteredIds as $idx => $id) {
                $freshStmt->bindValue($idx + 1, $id, PDO::PARAM_INT);
            }
            $freshStmt->execute();
            $freshLeads = $freshStmt->fetch(PDO::FETCH_ASSOC)['freshLeads'];

            // Pending leads (latest status only)
            $pendingSql = "SELECT COUNT(*) as pendingLeads 
                FROM ($latestStatusSubquery) AS ls
                WHERE ls.upload_data_id IN ($idsPlaceholder)
                AND ls.status = 'Pending'";
            $pendingStmt = $conn->prepare($pendingSql);
            foreach ($filteredIds as $idx => $id) {
                $pendingStmt->bindValue($idx + 1, $id, PDO::PARAM_INT);
            }
            $pendingStmt->execute();
            $pendingLeads = $pendingStmt->fetch(PDO::FETCH_ASSOC)['pendingLeads'];

            // Booked leads (latest status only)
            $bookedSql = "SELECT COUNT(*) as bookedLeads
                FROM ($latestStatusSubquery) AS ls
                WHERE ls.upload_data_id IN ($idsPlaceholder)
                AND ls.status = 'Converted'";
            $bookedStmt = $conn->prepare($bookedSql);
            foreach ($filteredIds as $idx => $id) {
                $bookedStmt->bindValue($idx + 1, $id, PDO::PARAM_INT);
            }
            $bookedStmt->execute();
            $bookedLeads = $bookedStmt->fetch(PDO::FETCH_ASSOC)['bookedLeads'];

            $todaySql = "SELECT COUNT(DISTINCT upload_data_id) as today_collection
                FROM user_remarks 
                WHERE upload_data_id IN ($idsPlaceholder)
                AND status IN ('Follow Up', 'Interested', 'Call Back', 'RNR', 'Fix Site Visit')
                AND follow_up_date IS NOT NULL
                AND CONCAT(follow_up_date, ' ', COALESCE(follow_up_time, '00:00:00')) >= ?
                AND CONCAT(follow_up_date, ' ', COALESCE(follow_up_time, '23:59:59')) <= ?";
            $todayStmt = $conn->prepare($todaySql);
            foreach ($filteredIds as $idx => $id) {
                $todayStmt->bindValue($idx + 1, $id, PDO::PARAM_INT);
            }
            $todayStmt->bindValue(count($filteredIds) + 1, $todayStart);
            $todayStmt->bindValue(count($filteredIds) + 2, $todayEnd);
            $todayStmt->execute();
            $todayCollection = $todayStmt->fetch(PDO::FETCH_ASSOC)['today_collection'];

            $adsSql = "SELECT COUNT(*) as paidAds FROM `$tableName` 
                WHERE id IN ($idsPlaceholder)
                AND LOWER(TRIM(source_of_lead)) IN $adsSources";
            $adsStmt = $conn->prepare($adsSql);
            foreach ($filteredIds as $idx => $id) {
                $adsStmt->bindValue($idx + 1, $id, PDO::PARAM_INT);
            }
            $adsStmt->execute();
            $paidAds = $adsStmt->fetch(PDO::FETCH_ASSOC)['paidAds'];

            $shiSql = "SELECT COUNT(*) as shi_d FROM `$tableName`
                WHERE id IN ($idsPlaceholder)
                AND (source_of_lead IS NULL OR LOWER(TRIM(source_of_lead)) NOT IN $adsSources)";
            $shiStmt = $conn->prepare($shiSql);
            foreach ($filteredIds as $idx => $id) {
                $shiStmt->bindValue($idx + 1, $id, PDO::PARAM_INT);
            }
            $shiStmt->execute();
            $shiD = $shiStmt->fetch(PDO::FETCH_ASSOC)['shi_d'];

            // Follow-up leads (latest status only)
            $followSql = "SELECT COUNT(*) as followLeads
                FROM ($latestStatusSubquery) AS ls
                WHERE ls.upload_data_id IN ($idsPlaceholder)
                AND ls.status = 'Follow Up'";
            $followStmt = $conn->prepare($followSql);
            foreach ($filteredIds as $idx => $id) {
                $followStmt->bindValue($idx + 1, $id, PDO::PARAM_INT);
            }
            $followStmt->execute();
            $followLeads = $followStmt->fetch(PDO::FETCH_ASSOC)['followLeads'];

            $overdueSql = "SELECT COUNT(DISTINCT upload_data_id) as overdueLeads
                FROM user_remarks 
                WHERE upload_data_id IN ($idsPlaceholder)
                AND status IN $overdueStatuses
                AND status != 'Converted'
                AND follow_up_date IS NOT NULL AND follow_up_time IS NOT NULL
                AND CONCAT(follow_up_date, ' ', follow_up_time) < ?";
            $overdueStmt = $conn->prepare($overdueSql);
            foreach ($filteredIds as $idx => $id) {
                $overdueStmt->bindValue($idx + 1, $id, PDO::PARAM_INT);
            }
            $overdueStmt->bindValue(count($filteredIds) + 1, $overdueCutoff);
            $overdueStmt->execute();
            $overdueLeads = $overdueStmt->fetch(PDO::FETCH_ASSOC)['overdueLeads'];
        }

        echo json_encode([
            'total' => (int)$total,
            'unassigned' => (int)$unassigned,
            'myLeads' => (int)$myLeads,
            'totaleoi' => (int)$totaleoi,
            'totaldelete' => (int)$totaldelete,
            'activeLeads' => (int)$activeLeads,
            'droppedLeads' => (int)$droppedLeads,
            'freshLeads' => (int)$freshLeads,
            'pendingLeads' => (int)$pendingLeads,
            'bookedLeads' => (int)($bookedLeads ?? 0),
            'today_collection' => (int)($todayCollection ?? 0),
            'paidAds' => (int)($paidAds ?? 0),
            'shi_d' => (int)($shiD ?? 0),
            'followLeads' => (int)($followLeads ?? 0),
            'overdueLeads' => (int)($overdueLeads ?? 0)
        ]);
    }
    catch (PDOException $e) {
        echo json_encode([
            'total' => 0, 'unassigned' => 0, 'myLeads' => 0,
            'totaleoi' => 0, 'totaldelete' => 0, 'activeLeads' => 0,
            'droppedLeads' => 0, 'freshLeads' => 0, 'pendingLeads' => 0,
            'error' => $e->getMessage()
        ]);
    }
    exit;
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

        // Fetch the count of "Fresh Leads" created today
        $sqlFreshLeads = "
            SELECT COUNT(*) as freshLeads 
            FROM shi_upload_data 
            WHERE DATE(created_at) = CURDATE()
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

        $delete_count = "SELECT COUNT(*) as totaldelete FROM deleted_item WHERE (recovered IS NULL OR recovered = 0)";
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
            'pendingLeads' => $pendingLeadsResult['pendingLeads'], // Add dropped leads count
            'totaleoi' => $totaleoi['totaleoi'],
            'totaldelete' => $totaldelete['totaldelete']
        ]);
    }
    catch (PDOException $e) {
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
            'totaldelete' => 0
        ]); // Return 0 for all counts in case of error
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {

    if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode([
            "status" => "error",
            "message" => "File upload error: Code " . $_FILES['file']['error']
        ]);
        exit;
    }

    $filePath = $_FILES['file']['tmp_name'];

    try {
        $ioFactoryClass = '\\PhpOffice\\PhpSpreadsheet\\IOFactory';
        if (!$psAvailable || !class_exists($ioFactoryClass)) {
            throw new RuntimeException('Excel parser not available on server (PhpSpreadsheet not loaded).');
        }

        $spreadsheet = $ioFactoryClass::load($filePath);
        $sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);

        if (count($sheetData) <= 1) {
            echo json_encode([
                "status" => "error",
                "message" => "Excel file seems to be empty or has only headers."
            ]);
            exit;
        }

        // ✅ Insert with new column
        $insertStmt = $conn->prepare("
            INSERT INTO shi_upload_data 
            (name, email, number, location, created_at, project, source_of_lead) 
            VALUES (:name, :email, :number, :location, :created_at, :project, :source_of_lead)
        ");

        // ✅ Update also includes source_of_lead
        $updateStmt = $conn->prepare("
            UPDATE shi_upload_data 
            SET project = :project, source_of_lead = :source_of_lead
            WHERE name = :name 
            AND email = :email 
            AND number = :number 
            AND (project IS NULL OR project = '')
        ");

        $rows = [];
        $keys = [];

        // ✅ Read Excel data
        foreach ($sheetData as $index => $row) {
            if ($index == 1)
                continue; // skip header

            $name = trim($row['A']);
            $email = strtolower(trim($row['B']));
            $number = preg_replace('/\D/', '', $row['C']);
            $location = trim($row['D']);
            $project = trim($row['E']);

            // ✅ NEW COLUMN (with default)
            $source_of_lead = !empty(trim($row['F'])) ? trim($row['F']) : 'NFS';

            if (!$name || !$email || !$number)
                continue;

            $key = $name . '|' . $email . '|' . $number;

            $rows[$key] = [
                'name' => $name,
                'email' => $email,
                'number' => $number,
                'location' => $location,
                'project' => $project,
                'source_of_lead' => $source_of_lead
            ];

            $keys[] = [$name, $email, $number];
        }

        // ✅ Fetch existing records in chunks
        $existing = [];
        $chunkSize = 1000;

        for ($i = 0; $i < count($keys); $i += $chunkSize) {
            $chunk = array_slice($keys, $i, $chunkSize);
            $placeholders = implode(',', array_fill(0, count($chunk), '(?, ?, ?)'));

            $flatParams = [];
            foreach ($chunk as $set) {
                $flatParams = array_merge($flatParams, $set);
            }

            $stmt = $conn->prepare("
                SELECT name, email, number, project 
                FROM shi_upload_data 
                WHERE (name, email, number) IN ($placeholders)
            ");

            $stmt->execute($flatParams);

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $key = $row['name'] . '|' . $row['email'] . '|' . $row['number'];
                $existing[$key] = $row;
            }
        }

        // ✅ Start transaction
        $conn->beginTransaction();

        $inserted = 0;
        $updated = 0;
        $skipped = 0;
        $errorRows = [];

        foreach ($rows as $key => $data) {
            try {
                if (isset($existing[$key])) {

                    // Update only if project empty
                    if (empty($existing[$key]['project'])) {
                        $updateStmt->execute([
                            ':project' => $data['project'],
                            ':source_of_lead' => $data['source_of_lead'],
                            ':name' => $data['name'],
                            ':email' => $data['email'],
                            ':number' => $data['number'],
                        ]);
                        $updated++;
                    }
                    else {
                        $skipped++;
                    }

                }
                else {

                    $insertStmt->execute([
                        ':name' => $data['name'],
                        ':email' => $data['email'],
                        ':number' => $data['number'],
                        ':location' => $data['location'],
                        ':created_at' => (new DateTime('now', new DateTimeZone('Asia/Kolkata')))
                        ->format('Y-m-d H:i:s'),
                        ':project' => $data['project'],
                        ':source_of_lead' => $data['source_of_lead'],
                    ]);

                    $inserted++;
                }

            }
            catch (Throwable $e) {
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

    }
    catch (Throwable $e) {

        if ($conn->inTransaction()) {
            $conn->rollBack();
        }

        http_response_code(500);

        echo json_encode([
            "status" => "error",
            "message" => "Error processing Excel file: " . $e->getMessage()
        ]);
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

    // Base query and count query with latest status from user_remarks
    // For deleted_item table, use original_id instead of id for user_remarks lookup
    if ($showDeletedOnly) {
        $baseQuery = "SELECT s.*, s.original_id as id,
            (SELECT status FROM user_remarks ur WHERE ur.upload_data_id = s.original_id ORDER BY ur.id DESC LIMIT 1) AS latest_status,
            (SELECT budget FROM user_remarks ur WHERE ur.upload_data_id = s.original_id ORDER BY ur.id DESC LIMIT 1) AS latest_budget
            FROM `$tableName` s WHERE 1 AND (s.recovered IS NULL OR s.recovered = 0)";
        $countQuery = "SELECT COUNT(*) as total FROM `$tableName` s WHERE 1 AND (s.recovered IS NULL OR s.recovered = 0)";
    }
    else {
        $baseQuery = "SELECT s.*, 
            (SELECT status FROM user_remarks ur WHERE ur.upload_data_id = s.id ORDER BY ur.id DESC LIMIT 1) AS latest_status,
            (SELECT budget FROM user_remarks ur WHERE ur.upload_data_id = s.id ORDER BY ur.id DESC LIMIT 1) AS latest_budget
            FROM `$tableName` s WHERE 1";
        $countQuery = "SELECT COUNT(*) as total FROM `$tableName` s WHERE 1";
    }

    $latestStatusSubquery = "
        SELECT ur.upload_data_id, ur.status
        FROM user_remarks ur
        INNER JOIN (
            SELECT upload_data_id, MAX(id) AS max_id
            FROM user_remarks
            GROUP BY upload_data_id
        ) latest ON latest.upload_data_id = ur.upload_data_id AND latest.max_id = ur.id
    ";

    // Special restriction for NoUser323
    if (trim(strtolower($useruniqueId)) == "nouser323" && $tableName == "shi_upload_data") {
        $allowedProjects = [
            "Godrej Rajendranagar",
            "Ramky Rajendranagar",
            "Godrej Shettigere Rd",
            "Godrej Thanisandra",
            "Godrej Thanisandra New",
            "Godrej Green Pune",
            "Godrej Aqua",
            "Godrej Azure",
            "Untitled form 23/09/2025, 14:10",
            "Godrej aqua",
            "Godrej regal pavilion L hyd",
            "GLO new plan",
            "godrej Gale",
            "godrej varodara",
            "Godrej Greenfront",
            "Godej Azure",
            "Godrej MSR City",
            "godrej doddaballapur plot",
            "Lodha Hinjewadi",
            "godrej doddaballapur plot-copy",
            "brigade hyd",
            "vadodara plt 2",
            "Godrej Thanisandra New Form",
            "Godrej Magarpatta",
            "Godrej hoskote",
            "godrej magarpatta",
            "Godrej Magarpatta",
            "Godrej Magarpatta-OTP Vrified"
        ];

        // Normalize (case-insensitive)
        $normalized = array_map(function ($p) {
            return "'" . strtolower(trim(addslashes($p))) . "'";
        }, $allowedProjects);

        $projectList = implode(",", $normalized);

        // Apply safe filter
        $baseQuery .= " AND LOWER(REGEXP_REPLACE(TRIM(project), '[[:space:]]+', ' ')) IN ($projectList)";
        $countQuery .= " AND LOWER(REGEXP_REPLACE(TRIM(project), '[[:space:]]+', ' ')) IN ($projectList)";
    }

    $queryParams = []; // Parameters for binding

    $validStatuses = [
        'Active', 'New', 'Pending', 'Dropped', 'Fake', 'RNR', 'Call Back', 'Already Booked',
        'Not Interested', 'Interested', 'Follow Up', 'Fix Site Visit', 'Site Visit Done',
        'Converted', 'Not Connected'
    ];

    if (in_array($currentFilter, $validStatuses)) {
        // Filter by latest status only
        $baseQuery .= " AND id IN (
            SELECT ls.upload_data_id FROM ($latestStatusSubquery) AS ls WHERE ls.status = :statusFilter
        )";
        $countQuery .= " AND id IN (
            SELECT ls.upload_data_id FROM ($latestStatusSubquery) AS ls WHERE ls.status = :statusFilter
        )";
        $queryParams[':statusFilter'] = $currentFilter;

    }
    elseif ($currentFilter === 'my') {
        // Filter by currently assigned user
        $baseQuery .= " AND assign_to_user = :currentUser";
        $countQuery .= " AND assign_to_user = :currentUser";
        $queryParams[':currentUser'] = $useruniqueId;

    }
    elseif ($currentFilter === 'unassigned') {
        // Filter leads with no assignment
        $baseQuery .= " AND (assign_to_user IS NULL OR assign_to_user = '')";
        $countQuery .= " AND (assign_to_user IS NULL OR assign_to_user = '')";

    }
    elseif ($currentFilter === 'dropped') {
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

    }
    elseif ($currentFilter === 'active') {
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
    }
    elseif ($currentFilter === 'pending') {
        $baseQuery .= " AND id IN (
            SELECT ls.upload_data_id FROM ($latestStatusSubquery) AS ls WHERE ls.status = 'Pending'
        )";
        $countQuery .= " AND id IN (
            SELECT ls.upload_data_id FROM ($latestStatusSubquery) AS ls WHERE ls.status = 'Pending'
        )";
    }
    elseif ($currentFilter === 'fresh') {
        $baseQuery .= " AND DATE(created_at) = CURDATE()";
        $countQuery .= " AND DATE(created_at) = CURDATE()";
    }
    elseif ($currentFilter === 'booked') {
        $baseQuery .= " AND id IN (
            SELECT ls.upload_data_id FROM ($latestStatusSubquery) AS ls WHERE ls.status = 'Converted'
        )";
        $countQuery .= " AND id IN (
            SELECT ls.upload_data_id FROM ($latestStatusSubquery) AS ls WHERE ls.status = 'Converted'
        )";
    }
    elseif ($currentFilter === 'follow') {
        $baseQuery .= " AND id IN (
            SELECT ls.upload_data_id FROM ($latestStatusSubquery) AS ls WHERE ls.status = 'Follow Up'
        )";
        $countQuery .= " AND id IN (
            SELECT ls.upload_data_id FROM ($latestStatusSubquery) AS ls WHERE ls.status = 'Follow Up'
        )";
    }
    elseif ($currentFilter === 'overdue') {
        $overdueCutoff = (new DateTime('now', new DateTimeZone('Asia/Kolkata')))->modify('-1 day')->format('Y-m-d H:i:s');
        $baseQuery .= " AND id IN (
            SELECT DISTINCT upload_data_id FROM user_remarks 
            WHERE status IN ('Follow Up', 'RNR', 'Call Back', 'Pending', 'Interested', 'Fix Site Visit', 'Site Visit Done', 'Re site visit', 'Not Connected')
            AND status != 'Converted'
            AND follow_up_date IS NOT NULL AND follow_up_time IS NOT NULL
            AND CONCAT(follow_up_date, ' ', follow_up_time) < :overdueCutoff
        )";
        $countQuery .= " AND id IN (
            SELECT DISTINCT upload_data_id FROM user_remarks 
            WHERE status IN ('Follow Up', 'RNR', 'Call Back', 'Pending', 'Interested', 'Fix Site Visit', 'Site Visit Done', 'Re site visit', 'Not Connected')
            AND status != 'Converted'
            AND follow_up_date IS NOT NULL AND follow_up_time IS NOT NULL
            AND CONCAT(follow_up_date, ' ', follow_up_time) < :overdueCutoff
        )";
        $queryParams[':overdueCutoff'] = $overdueCutoff;
    }
    elseif ($currentFilter === 'today') {
        $baseQuery .= " AND id IN (
            SELECT DISTINCT upload_data_id FROM user_remarks 
            WHERE status IN ('Follow Up', 'Interested', 'Call Back', 'RNR', 'Fix Site Visit')
            AND follow_up_date >= CURDATE() AND follow_up_date < DATE_ADD(CURDATE(), INTERVAL 1 DAY)
        )";
        $countQuery .= " AND id IN (
            SELECT DISTINCT upload_data_id FROM user_remarks 
            WHERE status IN ('Follow Up', 'Interested', 'Call Back', 'RNR', 'Fix Site Visit')
            AND follow_up_date >= CURDATE() AND follow_up_date < DATE_ADD(CURDATE(), INTERVAL 1 DAY)
        )";
    }
    elseif ($currentFilter === 'ads') {
        $baseQuery .= " AND LOWER(TRIM(source_of_lead)) IN ('google ads', 'facebook ads', 'magicbricks ads', '99acres ads', 'housing.com ads')";
        $countQuery .= " AND LOWER(TRIM(source_of_lead)) IN ('google ads', 'facebook ads', 'magicbricks ads', '99acres ads', 'housing.com ads')";
    }
    elseif ($currentFilter === 'shi_d') {
        $baseQuery .= " AND (source_of_lead IS NULL OR LOWER(TRIM(source_of_lead)) NOT IN ('google ads', 'facebook ads', 'magicbricks ads', '99acres ads', 'housing.com ads'))";
        $countQuery .= " AND (source_of_lead IS NULL OR LOWER(TRIM(source_of_lead)) NOT IN ('google ads', 'facebook ads', 'magicbricks ads', '99acres ads', 'housing.com ads'))";
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
                        SELECT ls.upload_data_id FROM ($latestStatusSubquery) AS ls
                        WHERE ls.status IN ($statusConditionStr)
                    )";

                $countQuery .= " AND id IN (
                        SELECT ls.upload_data_id FROM ($latestStatusSubquery) AS ls
                        WHERE ls.status IN ($statusConditionStr)
                    )";
            }
            else {
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
        }
        elseif ($key === ':startDate' || $key === ':endDate') {
            $stmt->bindValue($key, $val, PDO::PARAM_STR);
        }
        else {
            $stmt->bindValue($key, $val, PDO::PARAM_STR);
        }
    }
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Execute count query
    $countStmt = $conn->prepare($countQuery);
    foreach ($queryParams as $key => $val) {
        if ($key !== ':startRow' && $key !== ':rowsPerPage') {
            $countStmt->bindValue($key, $val, is_int($val) ?PDO::PARAM_INT : PDO::PARAM_STR);
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

}
catch (Exception $e) {
    file_put_contents('debug.log', "Error: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}
?>