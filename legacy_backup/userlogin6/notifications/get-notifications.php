<?php
// get-notifications.php
include '../config.php';
header('Content-Type: application/json; charset=utf-8');
session_start();

// Use your session key (you used 'tablename' previously)
$user_code = $_SESSION['tablename'] ?? null;
if (!$user_code) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'User not authenticated']);
    exit;
}

$limit = isset($_GET['limit']) ? max(1, (int) $_GET['limit']) : 20;
$offset = isset($_GET['offset']) ? max(0, (int) $_GET['offset']) : 0;
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$type = isset($_GET['type']) ? trim($_GET['type']) : '';
$is_read = isset($_GET['is_read']) ? ($_GET['is_read'] === '1' ? 1 : 0) : null;

$config = new Config();
$db = $config->getConnection();

function ensureNotificationReadColumns(PDO $db): void
{
    $check = $db->prepare("SHOW COLUMNS FROM notification_receipts LIKE :col");

    $check->execute([':col' => 'is_read']);
    if (!$check->fetch(PDO::FETCH_ASSOC)) {
        $db->exec("ALTER TABLE notification_receipts ADD COLUMN is_read TINYINT(1) NOT NULL DEFAULT 0");
    }

    $check->execute([':col' => 'read_at']);
    if (!$check->fetch(PDO::FETCH_ASSOC)) {
        $db->exec("ALTER TABLE notification_receipts ADD COLUMN read_at DATETIME NULL");
    }
}

try {
    ensureNotificationReadColumns($db);

    // unread count
    $stmt = $db->prepare("SELECT COUNT(*) FROM notification_receipts WHERE user_code = :uc AND is_read = 0");
    $stmt->execute([':uc' => $user_code]);
    $unread_count = (int) $stmt->fetchColumn();

    // Build where clause and params
    $where = "r.user_code = :uc";
    $params = [':uc' => $user_code];

    if ($type !== '') {
        $where .= " AND n.type = :type";
        $params[':type'] = $type;
    }

    if ($is_read !== null) {
        $where .= " AND r.is_read = :is_read";
        $params[':is_read'] = $is_read;
    }

    if ($q !== '') {
        // simple LIKE search on title/body/meta
        $where .= " AND (n.title LIKE :q OR n.body LIKE :q OR n.meta LIKE :q)";
        $params[':q'] = '%' . $q . '%';
    }

    // total count for pagination
    $countSql = "SELECT COUNT(*) FROM notification_receipts r JOIN notifications n ON n.id = r.notification_id WHERE $where";
    $countStmt = $db->prepare($countSql);
    $countStmt->execute($params);
    $total_count = (int) $countStmt->fetchColumn();

    // main query with limit/offset
    $sql = "SELECT n.id AS notification_id, n.title, n.body, n.url, n.type, n.icon, n.meta, n.created_at,
                   r.is_read
            FROM notification_receipts r
            JOIN notifications n ON n.id = r.notification_id
            WHERE $where
            ORDER BY n.created_at DESC
            LIMIT :limit OFFSET :offset";
    $stmt = $db->prepare($sql);

    // bind params for where
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    // bind limit and offset as integers
    $stmt->bindValue(':limit', (int) $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', (int) $offset, PDO::PARAM_INT);

    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // decode meta JSON
    foreach ($rows as &$r) {
        $r['meta'] = $r['meta'] ? json_decode($r['meta'], true) : null;
    }

    $has_more = ($offset + count($rows)) < $total_count;

    echo json_encode([
        'status' => 'success',
        'unread_count' => $unread_count,
        'notifications' => $rows,
        'total_count' => $total_count,
        'has_more' => $has_more
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
