<?php
session_start();
// error_reporting(E_ALL);
// ini_set('display_errors', 1);
require '../config.php';

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
    }
    exit; // Terminate script after sending user options
}

// --- return groups for dropdown ---
// ------------------ Group endpoints (improved) ------------------

// GET groups (dropdown)
if (isset($_GET['get_groups']) && $_GET['get_groups'] == 1) {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $q = "SELECT id, group_name, project_name FROM project_apis WHERE type = 'group' ORDER BY group_name ASC";
        $stm = $conn->prepare($q);
        $stm->execute();
        $groups = $stm->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($groups);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }
    exit;
}

/**
 * assign_to_group
 * Append group id+name to each selected API row (CSV-safe), return updated ids and optionally final CSVs.
 * Expects POST JSON: { selected_ids: [..], group_id: N }
 */
if (isset($_GET['assign_to_group']) && $_GET['assign_to_group'] == 1) {
    header('Content-Type: application/json; charset=utf-8');
    $input = json_decode(file_get_contents('php://input'), true);
    $selected = $input['selected_ids'] ?? [];
    $groupId = isset($input['group_id']) ? (int)$input['group_id'] : 0;

    if (!is_array($selected) || empty($selected) || $groupId <= 0) {
        echo json_encode(['status'=>'error','message'=>'Invalid input (no selected ids or invalid group)']);
        exit;
    }
    $selected = array_values(array_unique(array_map('intval', $selected)));

    try {
        // validate group
        $gq = "SELECT id, group_name FROM project_apis WHERE id = :gid AND type = 'group' LIMIT 1";
        $gstm = $conn->prepare($gq);
        $gstm->execute([':gid' => $groupId]);
        $group = $gstm->fetch(PDO::FETCH_ASSOC);
        if (!$group) { echo json_encode(['status'=>'error','message'=>'Group not found']); exit; }
        $groupName = (string)$group['group_name'];

        // fetch only selected non-group rows
        $placeholders = [];
        $binds = [];
        foreach ($selected as $i => $id) { $ph=":id{$i}"; $placeholders[]=$ph; $binds[$ph]=$id; }
        $in = implode(',', $placeholders);
        $fetchSql = "SELECT id, group_id, group_name FROM project_apis WHERE id IN ($in) AND (type IS NULL OR type != 'group')";
        $fetchStmt = $conn->prepare($fetchSql);
        $fetchStmt->execute($binds);
        $rows = $fetchStmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($rows)) { echo json_encode(['status'=>'error','message'=>'No API rows found to update']); exit; }

        // prepare update
        $updateSql = "UPDATE project_apis SET group_id = :group_ids, group_name = :group_names, updated_at = NOW() WHERE id = :id";
        $updateStmt = $conn->prepare($updateSql);

        $conn->beginTransaction();
        $updated = [];
        foreach ($rows as $r) {
            $rid = (int)$r['id'];
            $oldIds = array_filter(array_map('trim', explode(',', (string)$r['group_id'])), 'strlen');
            $oldNames = array_filter(array_map('trim', explode(',', (string)$r['group_name'])), 'strlen');
            $oldIdsNorm = array_map('strval', $oldIds);

            $gidStr = (string)$groupId;
            if (!in_array($gidStr, $oldIdsNorm, true)) {
                $oldIdsNorm[] = $gidStr;
                $oldNames[] = $groupName;
            } else {
                if (!in_array($groupName, $oldNames, true)) $oldNames[] = $groupName;
            }
            $newIdsCsv = count($oldIdsNorm) ? implode(',', $oldIdsNorm) : null;
            $newNamesCsv = count($oldNames) ? implode(',', array_map('trim', $oldNames)) : null;

            $updateStmt->execute([':group_ids'=>$newIdsCsv, ':group_names'=>$newNamesCsv, ':id'=>$rid]);
            $updated[] = $rid;
        }
        $conn->commit();

        echo json_encode(['status'=>'success','message'=>'Groups assigned','requested'=>count($selected),'updated_ids'=>$updated]);
    } catch (Exception $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        http_response_code(500);
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }
    exit;
}

/**
 * get_group_details
 * Returns group meta, rows that belong to the group, and candidate rows not in group.
 * GET params: ?get_group_details=1&id=<groupId>
 */
if (isset($_GET['get_group_details']) && $_GET['get_group_details'] == 1 && isset($_GET['id'])) {
    header('Content-Type: application/json; charset=utf-8');
    $gid = (int)$_GET['id'];
    try {
        $gq = "SELECT id, project_name, group_name, assign_user, group_id FROM project_apis WHERE id = :gid AND type = 'group' LIMIT 1";
        $gstm = $conn->prepare($gq);
        $gstm->execute([':gid'=>$gid]);
        $group = $gstm->fetch(PDO::FETCH_ASSOC);
        if (!$group) { echo json_encode(['status'=>'error','message'=>'Group not found']); exit; }

        // fetch all non-group rows (you can limit fields)
        $rowsQ = "SELECT id, project_name, api_key, lead_source, assign_user, created_at, group_id FROM project_apis WHERE (type IS NULL OR type != 'group')";
        $rowsStmt = $conn->prepare($rowsQ);
        $rowsStmt->execute();
        $allRows = $rowsStmt->fetchAll(PDO::FETCH_ASSOC);

        $inGroup = $notInGroup = [];
        foreach ($allRows as $r) {
            $gidField = trim((string)$r['group_id']);
            $in = false;
            if ($gidField !== '') {
                $parts = array_map('trim', explode(',', $gidField));
                foreach ($parts as $p) if ((string)$p === (string)$gid) { $in = true; break; }
            }
            if ($in) $inGroup[] = $r; else $notInGroup[] = $r;
        }
        echo json_encode(['status'=>'success','group'=>$group,'rows'=>$inGroup,'candidates'=>$notInGroup]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }
    exit;
}

/**
 * update_group
 * Update group's assign_user CSV and update membership as per rows array.
 * Expects JSON: { group_id: N, assign_users: [...], rows: [ids] }
 * This will ensure rows listed are in the group's group_id CSV and others are not.
 */
if (isset($_GET['action']) && $_GET['action'] === 'update_group') {
    header('Content-Type: application/json; charset=utf-8');
    $input = json_decode(file_get_contents('php://input'), true);
    $gid = isset($input['group_id']) ? (int)$input['group_id'] : 0;
    $assignUsers = isset($input['assign_users']) && is_array($input['assign_users']) ? array_values(array_unique(array_map('trim',$input['assign_users']))) : [];
    $rows = isset($input['rows']) && is_array($input['rows']) ? array_map('intval', $input['rows']) : [];
    if ($gid <= 0) { echo json_encode(['status'=>'error','message'=>'Invalid group id']); exit; }

    try {
        $conn->beginTransaction();
        // validate group exists
        $g = $conn->prepare("SELECT id FROM project_apis WHERE id = :gid AND type = 'group' LIMIT 1");
        $g->execute([':gid'=>$gid]);
        if (!$g->fetch()) { $conn->rollBack(); echo json_encode(['status'=>'error','message'=>'Group not found']); exit; }

        // update group's assign_user CSV
        $assignCsv = count($assignUsers) ? implode(',', $assignUsers) : null;
        $up = $conn->prepare("UPDATE project_apis SET assign_user = :au WHERE id = :gid");
        $up->execute([':au'=>$assignCsv, ':gid'=>$gid]);

        // Fetch all non-group rows IDs to operate on (to avoid scanning unrelated rows)
        $idsStmt = $conn->prepare("SELECT id, group_id, group_name FROM project_apis WHERE (type IS NULL OR type != 'group')");
        $idsStmt->execute();
        $allRows = $idsStmt->fetchAll(PDO::FETCH_ASSOC);

        // Prepare statement to update a row's group_id (and keep group_name untouched here — you may add logic if needed)
        $updRow = $conn->prepare("UPDATE project_apis SET group_id = :g WHERE id = :id");

        $rowsSet = array_flip($rows); // quick lookup

        foreach ($allRows as $r) {
            $rid = (int)$r['id'];
            $existing = trim((string)($r['group_id'] ?? ''));
            $parts = $existing === '' ? [] : array_values(array_unique(array_filter(array_map('trim', explode(',', $existing)))));
            // add or remove gid
            if (isset($rowsSet[$rid])) {
                if (!in_array((string)$gid, $parts, true)) $parts[] = (string)$gid;
            } else {
                $parts = array_values(array_filter($parts, function($p) use ($gid){ return (string)$p !== (string)$gid; }));
            }
            $new = count($parts) ? implode(',', $parts) : null;
            $updRow->execute([':g'=>$new, ':id'=>$rid]);
        }

        $conn->commit();
        echo json_encode(['status'=>'success','message'=>'Group updated']);
    } catch (Exception $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        http_response_code(500);
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }
    exit;
}

/**
 * add_rows_to_group
 * POST JSON: { group_id: N, ids: [..] }
 * Appends group id to each row's group_id CSV and also appends group_name to group_name CSV.
 */
if (isset($_GET['action']) && $_GET['action'] === 'add_rows_to_group') {
    header('Content-Type: application/json; charset=utf-8');
    $input = json_decode(file_get_contents('php://input'), true);
    $gid = isset($input['group_id']) ? (int)$input['group_id'] : 0;
    $ids = isset($input['ids']) && is_array($input['ids']) ? array_map('intval', $input['ids']) : [];
    if ($gid <= 0 || empty($ids)) { echo json_encode(['status'=>'error','message'=>'Invalid input']); exit; }

    try {
        // validate group & fetch group_name
        $gq = $conn->prepare("SELECT group_name FROM project_apis WHERE id = :gid AND type = 'group' LIMIT 1");
        $gq->execute([':gid'=>$gid]);
        $g = $gq->fetch(PDO::FETCH_ASSOC);
        if (!$g) { echo json_encode(['status'=>'error','message'=>'Group not found']); exit; }
        $groupName = (string)$g['group_name'];

        $conn->beginTransaction();
        $fetch = $conn->prepare("SELECT id, group_id, group_name FROM project_apis WHERE id = :id AND (type IS NULL OR type != 'group') LIMIT 1");
        $update = $conn->prepare("UPDATE project_apis SET group_id = :gids, group_name = :gnames WHERE id = :id");
        $updated = [];

        foreach ($ids as $id) {
            $fetch->execute([':id'=>$id]);
            $row = $fetch->fetch(PDO::FETCH_ASSOC);
            if (!$row) continue;
            $existingIds = $row['group_id'] ?? '';
            $existingNames = $row['group_name'] ?? '';

            $parts = $existingIds === '' ? [] : array_values(array_unique(array_filter(array_map('trim', explode(',', $existingIds)))));
            $names = $existingNames === '' ? [] : array_values(array_unique(array_filter(array_map('trim', explode(',', $existingNames)))));

            if (!in_array((string)$gid, $parts, true)) $parts[] = (string)$gid;
            if (!in_array($groupName, $names, true)) $names[] = $groupName;

            $newIds = count($parts) ? implode(',', $parts) : null;
            $newNames = count($names) ? implode(',', $names) : null;

            $update->execute([':gids'=>$newIds, ':gnames'=>$newNames, ':id'=>$id]);
            $updated[] = $id;
        }
        $conn->commit();
        echo json_encode(['status'=>'success','message'=>'Rows added to group','updated_ids'=>$updated]);
    } catch (Exception $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        http_response_code(500);
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }
    exit;
}

/**
 * remove_rows_from_group
 * POST JSON: { group_id: N, ids: [..] }
 * Removes group id & name from rows (keeps other groups).
 */
if (isset($_GET['action']) && $_GET['action'] === 'remove_rows_from_group') {
    header('Content-Type: application/json; charset=utf-8');
    $input = json_decode(file_get_contents('php://input'), true);
    $gid = isset($input['group_id']) ? (int)$input['group_id'] : 0;
    $ids = isset($input['ids']) && is_array($input['ids']) ? array_map('intval', $input['ids']) : [];
    if ($gid <= 0 || empty($ids)) { echo json_encode(['status'=>'error','message'=>'Invalid input']); exit; }

    try {
        // get group name to remove from group_name CSV
        $gq = $conn->prepare("SELECT group_name FROM project_apis WHERE id = :gid AND type = 'group' LIMIT 1");
        $gq->execute([':gid'=>$gid]);
        $g = $gq->fetch(PDO::FETCH_ASSOC);
        $groupName = $g ? (string)$g['group_name'] : null;

        $conn->beginTransaction();
        $fetch = $conn->prepare("SELECT id, group_id, group_name FROM project_apis WHERE id = :id AND (type IS NULL OR type != 'group') LIMIT 1");
        $update = $conn->prepare("UPDATE project_apis SET group_id = :gids, group_name = :gnames WHERE id = :id");
        $updated = [];

        foreach ($ids as $id) {
            $fetch->execute([':id'=>$id]);
            $row = $fetch->fetch(PDO::FETCH_ASSOC);
            if (!$row) continue;
            $existingIds = trim((string)($row['group_id'] ?? ''));
            $existingNames = trim((string)($row['group_name'] ?? ''));

            $parts = $existingIds === '' ? [] : array_values(array_filter(array_map('trim', explode(',', $existingIds))));
            $names = $existingNames === '' ? [] : array_values(array_filter(array_map('trim', explode(',', $existingNames))));

            $parts = array_values(array_filter($parts, function($p) use ($gid){ return (string)$p !== (string)$gid; }));
            if ($groupName !== null) {
                $names = array_values(array_filter($names, function($n) use ($groupName){ return (string)$n !== (string)$groupName; }));
            }

            $newIds = count($parts) ? implode(',', $parts) : null;
            $newNames = count($names) ? implode(',', $names) : null;

            $update->execute([':gids'=>$newIds, ':gnames'=>$newNames, ':id'=>$id]);
            $updated[] = $id;
        }
        $conn->commit();
        echo json_encode(['status'=>'success','message'=>'Rows removed from group','updated_ids'=>$updated]);
    } catch (Exception $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        http_response_code(500);
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }
    exit;
}

/**
 * create_group
 * POST JSON: { group_name: 'Name' }
 */
if (isset($_GET['action']) && $_GET['action'] === 'create_group') {
    header('Content-Type: application/json; charset=utf-8');
    $input = json_decode(file_get_contents('php://input'), true);
    $name = trim($input['group_name'] ?? '');
    if ($name === '') { echo json_encode(['status'=>'error','message'=>'Group name required']); exit; }
    try {
        $ins = $conn->prepare("INSERT INTO project_apis (project_name, group_name, type, created_at) VALUES (:pn, :gn, 'group', NOW())");
        $ins->execute([':pn'=>$name, ':gn'=>$name]);
        echo json_encode(['status'=>'success','message'=>'Group created','id'=>$conn->lastInsertId()]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }
    exit;
}

/**
 * delete_group
 * POST JSON: { group_id: N }
 * Deletes group row and removes gid+name from all rows
 */
if (isset($_GET['action']) && $_GET['action'] === 'delete_group') {
    header('Content-Type: application/json; charset=utf-8');
    $input = json_decode(file_get_contents('php://input'), true);
    $gid = isset($input['group_id']) ? (int)$input['group_id'] : 0;
    if ($gid <= 0) { echo json_encode(['status'=>'error','message'=>'Invalid group id']); exit; }
    try {
        $conn->beginTransaction();
        // get group name
        $gq = $conn->prepare("SELECT group_name FROM project_apis WHERE id = :gid AND type = 'group' LIMIT 1");
        $gq->execute([':gid'=>$gid]);
        $g = $gq->fetch(PDO::FETCH_ASSOC);
        $groupName = $g ? (string)$g['group_name'] : null;

        // remove gid & name from every non-group row (use a single SELECT then update loop)
        $rowsQ = $conn->prepare("SELECT id, group_id, group_name FROM project_apis WHERE (type IS NULL OR type != 'group')");
        $rowsQ->execute();
        $rows = $rowsQ->fetchAll(PDO::FETCH_ASSOC);
        $upd = $conn->prepare("UPDATE project_apis SET group_id = :g, group_name = :gn WHERE id = :id");
        foreach ($rows as $r) {
            $existing = trim((string)($r['group_id'] ?? ''));
            if ($existing === '') {
                // no change
                continue;
            }
            $parts = array_values(array_filter(array_map('trim', explode(',', $existing))));
            $parts = array_values(array_filter($parts, function($p) use ($gid){ return (string)$p !== (string)$gid; }));
            $new = count($parts) ? implode(',', $parts) : null;

            $names = trim((string)($r['group_name'] ?? '')) !== '' ? array_values(array_filter(array_map('trim', explode(',', (string)$r['group_name'])))) : [];
            if ($groupName !== null) {
                $names = array_values(array_filter($names, function($n) use ($groupName){ return (string)$n !== (string)$groupName; }));
            }
            $newNames = count($names) ? implode(',', $names) : null;

            $upd->execute([':g'=>$new, ':gn'=>$newNames, ':id'=>$r['id']]);
        }

        // delete group row itself
        $del = $conn->prepare("DELETE FROM project_apis WHERE id = :gid AND type = 'group'");
        $del->execute([':gid'=>$gid]);

        $conn->commit();
        echo json_encode(['status'=>'success','message'=>'Group deleted']);
    } catch (Exception $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        http_response_code(500);
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
    }
    exit;
}
// All group settings End 

// Get the parameters from the request this for the pagenation php script 
// Pagination parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$rowsPerPage = isset($_GET['rowsPerPage']) ? (int)$_GET['rowsPerPage'] : 10;
$searchQuery = isset($_GET['searchQuery']) ? $_GET['searchQuery'] : '';
$startRow = ($page - 1) * $rowsPerPage;

// Base SQL query
// $sql = "SELECT * FROM project_apis WHERE 1=1";
// $countQuery = "SELECT COUNT(*) as total FROM project_apis WHERE 1=1";

// Base SQL query — exclude rows that are groups
$sql = "SELECT * FROM project_apis WHERE (type IS NULL OR type != 'group')";
// Count query
$countQuery = "SELECT COUNT(*) as total FROM project_apis WHERE (type IS NULL OR type != 'group')";

// 🔥 Apply allowed project filter only for this 1 user
if (trim(strtolower($useruniqueId)) == "nouser323") {
    $allowedProjects = [
        "Godrej Rajendranagar1212"
        // "Ramky Rajendranagar",
        // "Godrej Shettigere Rd",
        // "Godrej Thanisandra",
        // "Godrej Thanisandra New",
        // "Godrej Thanisandra New Form",
        // "Godrej Thanisandra New Form",
        // "Godrej Magarpatta",
        // "Godrej Magarpatta-OTP Vrified",
        // "Godrej Woodscapes cross hoskote",
        // "Godrej Yelahanka P",
        // "Godrej Aqua",
        // "Godrej regal pavilion L hyd",
        // "godrej Gale",
        // "godrej varodara",
        // "godrej doddaballapur plot",
        // "Lodha Hinjewadi",
        // "brigade hyd",
        // "shi_upload_data",
        // "Godrej MSR City",
        // "godrej Yelahanka ppc",
        // "lodha panache",
        // "godrej hoskote",
        // "Godrej hoskote",
        // "Godrej shettigere rd."
    ];

    // Convert array into placeholders like (:p1, :p2 ...)
    $placeholders = [];
    foreach ($allowedProjects as $index => $project) {
        $placeholders[] = ":p" . $index;
    }

    $inQuery = implode(",", $placeholders);
    $sql .= " AND project_name IN ($inQuery)";
    $countQuery .= " AND project_name IN ($inQuery)";
}

// Apply search condition
if (!empty($searchQuery)) {
    $sql .= " AND (project_name LIKE :search OR api_key LIKE :search OR lead_source LIKE :search)";
    $countQuery .= " AND (project_name LIKE :search OR api_key LIKE :search OR lead_source LIKE :search)";
}

// Add pagination limit
$sql .= " LIMIT :startRow, :rowsPerPage";

// Prepare statement
$stmt = $conn->prepare($sql);
$countStmt = $conn->prepare($countQuery);

// Bind allowed projects for nouser323
if (trim(strtolower($useruniqueId)) == "nouser323") {
    foreach ($allowedProjects as $index => $project) {
        $stmt->bindValue(":p".$index, $project, PDO::PARAM_STR);
        $countStmt->bindValue(":p".$index, $project, PDO::PARAM_STR);
    }
}

// Bind search if exists
if (!empty($searchQuery)) {
    $searchTerm = '%' . $searchQuery . '%';
    $stmt->bindValue(':search', $searchTerm, PDO::PARAM_STR);
    $countStmt->bindValue(':search', $searchTerm, PDO::PARAM_STR);
}

// Bind pagination
$stmt->bindValue(':startRow', $startRow, PDO::PARAM_INT);
$stmt->bindValue(':rowsPerPage', $rowsPerPage, PDO::PARAM_INT);
$stmt->execute();

// Fetch data
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch count
$countStmt->execute();
$totalRows = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

// JSON Response
$response = [
    'data' => $data,
    'totalRows' => $totalRows,
    'currentPage' => $page,
    'rowsPerPage' => $rowsPerPage
];

echo json_encode($response);

?>