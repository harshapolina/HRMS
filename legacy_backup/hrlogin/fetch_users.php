<?php
/**
 * JSON API for the users table and single-user edit payloads.
 * List mode: server-side pagination, search, and filters (page + limit required).
 * Legacy: ?all=1 returns full array for dropdowns.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function fetch_users_send_json($payload, int $httpCode = 200): void
{
    if (!headers_sent()) {
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');
    }
    $flags = JSON_UNESCAPED_UNICODE;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }
    $json = json_encode($payload, $flags);
    if ($json === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to encode response data.']);
        exit;
    }
    echo $json;
    exit;
}

if (!isset($_SESSION['loggedin'])) {
    fetch_users_send_json(['error' => 'Unauthorized access. Please log in.'], 401);
}

session_write_close();

require_once __DIR__ . '/includes/db_mysqli.php';

$allowedLimits = [10, 50, 100, 200, 300];

$fieldColumns = [
    'filterID'      => 'CAST(id AS CHAR)',
    'username'      => 'username',
    'email'         => 'useremail',
    'Contactnumber' => 'phonenumber',
    'Password'      => 'epassword',
    'inhandsalary'  => 'CAST(salary AS CHAR)',
    'DateOfJoining' => 'doj',
    'DateOfBirth'   => 'dob',
    'uniqueid'      => 'tablename',
    'EmployeeId'    => 'employee_id',
    'assignuser'    => 'assign_user',
    'roletype'      => 'user_type',
    'Projectname'   => 'project_name',
];

$listColumns = '
    id, username, useremail, phonenumber, salary, doj, dob,
    tablename AS uniqueid, employee_id,
    one_amt AS first_amount, two_amt AS second_amount,
    thrid_amt AS third_amount, forth_amt AS fourth_amount,
    fifth_amt AS fifth_amount, sixth_amt AS sixth_amount,
    project_name, project_type, city, user_type, assign_user, created_at,
    deactivated_at AS inactive_at, is_active
';

$fullColumns = '
    id, username, useremail, phonenumber, epassword, salary, doj, dob,
    tablename AS uniqueid, employee_id,
    one_amt AS first_amount, two_amt AS second_amount,
    thrid_amt AS third_amount, forth_amt AS fourth_amount,
    fifth_amt AS fifth_amount, sixth_amt AS sixth_amount,
    project_name, project_type, city, user_type, assign_user, created_at,
    deactivated_at AS inactive_at, is_active
';

function fetch_users_parse_list(string $raw): array
{
    if ($raw === '') {
        return [];
    }
    $parts = array_map('trim', explode(',', $raw));
    return array_values(array_filter($parts, static fn($v) => $v !== ''));
}

function fetch_users_append_like_group(array &$where, array &$types, array &$bind, string $expr, array $values): void
{
    if ($values === []) {
        return;
    }
    $parts = [];
    foreach ($values as $value) {
        $parts[] = "LOWER({$expr}) LIKE ?";
        $types .= 's';
        $bind[] = '%' . strtolower($value) . '%';
    }
    $where[] = '(' . implode(' OR ', $parts) . ')';
}

function fetch_users_append_status_group(array &$where, array &$types, array &$bind, array $values): void
{
    if ($values === []) {
        return;
    }
    $flags = [];
    foreach ($values as $value) {
        $v = strtolower($value);
        if ($v === 'active') {
            $flags[1] = true;
        } elseif ($v === 'inactive') {
            $flags[0] = true;
        }
    }
    if ($flags === []) {
        return;
    }
    $parts = [];
    if (isset($flags[1])) {
        $parts[] = 'is_active = 1';
    }
    if (isset($flags[0])) {
        $parts[] = 'is_active = 0';
    }
    $where[] = '(' . implode(' OR ', $parts) . ')';
}

function fetch_users_build_where(array $fieldColumns, ?string $cardFilter = null): array
{
    $where = ['1=1'];
    $types = '';
    $bind = [];

    $search = trim($_GET['search'] ?? '');
    if ($search !== '') {
        $like = '%' . strtolower($search) . '%';
        $where[] = '(
            LOWER(CAST(id AS CHAR)) LIKE ?
            OR LOWER(username) LIKE ?
            OR LOWER(useremail) LIKE ?
            OR LOWER(phonenumber) LIKE ?
            OR LOWER(CASE WHEN is_active = 1 THEN \'active\' ELSE \'inactive\' END) LIKE ?
        )';
        $types .= 'sssss';
        array_push($bind, $like, $like, $like, $like, $like);
    }

    foreach ($fieldColumns as $field => $column) {
        $multiKey = 'f_' . $field;
        if (isset($_GET[$multiKey])) {
            $values = fetch_users_parse_list((string) $_GET[$multiKey]);
            if ($field === 'status') {
                fetch_users_append_status_group($where, $types, $bind, $values);
            } else {
                fetch_users_append_like_group($where, $types, $bind, $column, $values);
            }
        }

        if (isset($_GET[$field]) && trim((string) $_GET[$field]) !== '') {
            $value = trim((string) $_GET[$field]);
            if ($field === 'status') {
                fetch_users_append_status_group($where, $types, $bind, [$value]);
            } else {
                fetch_users_append_like_group($where, $types, $bind, $column, [$value]);
            }
        }
    }

    if ($cardFilter === 'active') {
        $where[] = 'is_active = 1';
    } elseif ($cardFilter === 'inactive') {
        $where[] = 'is_active = 0';
    }

    return [
        'sql'   => implode(' AND ', $where),
        'types' => $types,
        'bind'  => $bind,
    ];
}

function fetch_users_bind(mysqli_stmt $stmt, string $types, array $bind): void
{
    if ($types === '') {
        return;
    }
    $stmt->bind_param($types, ...$bind);
}

try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    $conn = hr_mysqli_connect();

    if (isset($_GET['id'])) {
        $id = (int) $_GET['id'];
        $query = "SELECT {$fullColumns} FROM accounts WHERE id = ? LIMIT 1";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();
        $conn->close();

        if ($row) {
            fetch_users_send_json($row);
        }
        fetch_users_send_json(['error' => 'User not found'], 404);
    }

    if (isset($_GET['distinct'])) {
        $field = (string) $_GET['distinct'];
        $q = trim($_GET['q'] ?? '');
        $max = min(200, max(20, (int) ($_GET['max'] ?? 100)));

        if ($field === 'status') {
            $values = ['Active', 'Inactive'];
            if ($q !== '') {
                $qLower = strtolower($q);
                $values = array_values(array_filter($values, static fn($v) => str_contains(strtolower($v), $qLower)));
            }
            $conn->close();
            fetch_users_send_json(['values' => $values]);
        }

        if (!isset($fieldColumns[$field])) {
            fetch_users_send_json(['error' => 'Invalid distinct field'], 400);
        }

        $column = $fieldColumns[$field];
        $sql = "SELECT DISTINCT {$column} AS val FROM accounts
                WHERE {$column} IS NOT NULL AND TRIM(CAST({$column} AS CHAR)) != ''";
        $types = '';
        $bind = [];
        if ($q !== '') {
            $sql .= ' AND LOWER(CAST(' . $column . ' AS CHAR)) LIKE ?';
            $types = 's';
            $bind[] = '%' . strtolower($q) . '%';
        }
        $sql .= ' ORDER BY val ASC LIMIT ?';
        $types .= 'i';
        $bind[] = $max;

        $stmt = $conn->prepare($sql);
        fetch_users_bind($stmt, $types, $bind);
        $stmt->execute();
        $result = $stmt->get_result();
        $values = [];
        while ($row = $result->fetch_assoc()) {
            $values[] = $row['val'];
        }
        $stmt->close();
        $conn->close();
        fetch_users_send_json(['values' => $values]);
    }

    if (isset($_GET['all']) && $_GET['all'] === '1') {
        $maxAll = min(2000, max(50, (int) ($_GET['max'] ?? 500)));
        $query = "SELECT id, username, useremail, is_active FROM accounts ORDER BY id DESC LIMIT ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $maxAll);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        $stmt->close();
        $conn->close();
        fetch_users_send_json($data);
    }

    if (isset($_GET['offer_dropdown']) && $_GET['offer_dropdown'] === '1') {
        $maxOffer = min(2000, max(50, (int) ($_GET['max'] ?? 500)));
        $query = "SELECT id, username, useremail, phonenumber, salary, doj, project_name, user_type, assign_user
            FROM accounts
            WHERE is_active = 1
            ORDER BY username ASC
            LIMIT ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $maxOffer);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        $stmt->close();
        $conn->close();
        fetch_users_send_json($data);
    }

    $page = max(1, (int) ($_GET['page'] ?? 1));
    $limit = (int) ($_GET['limit'] ?? 10);
    if (!in_array($limit, $allowedLimits, true)) {
        $limit = 10;
    }
    $offset = ($page - 1) * $limit;
    $cardFilter = trim($_GET['card_filter'] ?? '');

    $baseWhere = fetch_users_build_where($fieldColumns, null);
    $listWhere = fetch_users_build_where($fieldColumns, $cardFilter !== '' ? $cardFilter : null);

    $summarySql = "SELECT
            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active_count,
            SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) AS inactive_count
        FROM accounts WHERE {$baseWhere['sql']}";
    $summaryStmt = $conn->prepare($summarySql);
    fetch_users_bind($summaryStmt, $baseWhere['types'], $baseWhere['bind']);
    $summaryStmt->execute();
    $summaryRow = $summaryStmt->get_result()->fetch_assoc() ?: [];
    $summaryStmt->close();

    $countSql = "SELECT COUNT(*) AS total, COALESCE(SUM(salary), 0) AS total_salary
        FROM accounts WHERE {$listWhere['sql']}";
    $countStmt = $conn->prepare($countSql);
    fetch_users_bind($countStmt, $listWhere['types'], $listWhere['bind']);
    $countStmt->execute();
    $countRow = $countStmt->get_result()->fetch_assoc() ?: ['total' => 0, 'total_salary' => 0];
    $countStmt->close();

    $total = (int) ($countRow['total'] ?? 0);
    $totalPages = max(1, (int) ceil($total / $limit));
    if ($page > $totalPages) {
        $page = $totalPages;
        $offset = ($page - 1) * $limit;
    }

    $dataSql = "SELECT {$listColumns} FROM accounts
        WHERE {$listWhere['sql']}
        ORDER BY id DESC
        LIMIT ? OFFSET ?";
    $dataTypes = $listWhere['types'] . 'ii';
    $dataBind = array_merge($listWhere['bind'], [$limit, $offset]);
    $dataStmt = $conn->prepare($dataSql);
    fetch_users_bind($dataStmt, $dataTypes, $dataBind);
    $dataStmt->execute();
    $result = $dataStmt->get_result();
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    $dataStmt->close();
    $conn->close();

    $activeCount = (int) ($summaryRow['active_count'] ?? 0);
    $inactiveCount = (int) ($summaryRow['inactive_count'] ?? 0);

    fetch_users_send_json([
        'data'        => $data,
        'total'       => $total,
        'page'        => $page,
        'limit'       => $limit,
        'total_pages' => $totalPages,
        'summary'     => [
            'active'       => $activeCount,
            'inactive'     => $inactiveCount,
            'assigned'     => $activeCount + $inactiveCount,
            'total_salary' => (float) ($countRow['total_salary'] ?? 0),
        ],
    ]);
} catch (Throwable $e) {
    error_log('fetch_users.php: ' . $e->getMessage());
    fetch_users_send_json(['error' => 'Server error while loading users. Please refresh the page.'], 500);
}
