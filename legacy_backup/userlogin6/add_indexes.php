<?php
/**
 * add_indexes.php  —  ONE-TIME safe index migration
 * ─────────────────────────────────────────────────
 * Run once via browser: http://your-site/userlogin6/add_indexes.php
 * Or via CLI: php add_indexes.php
 *
 * Safety: every index is checked in INFORMATION_SCHEMA before creation.
 * Adding indexes NEVER changes data or query results — only speeds them up.
 * DELETE this file after running.
 */

require_once 'config.php';
$cfg  = new Config();
$conn = $cfg->getConnection();

// ── Fetch current DB name ─────────────────────────────────────────────────
$dbName = $conn->query('SELECT DATABASE()')->fetchColumn();

// ── Helper: check if index already exists ─────────────────────────────────
function indexExists(PDO $conn, string $db, string $table, string $indexName): bool {
    $stmt = $conn->prepare("
        SELECT COUNT(*) FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = :db
          AND TABLE_NAME   = :tbl
          AND INDEX_NAME   = :idx
    ");
    $stmt->execute([':db' => $db, ':tbl' => $table, ':idx' => $indexName]);
    return (int) $stmt->fetchColumn() > 0;
}

// ── Helper: check if FULLTEXT index exists ────────────────────────────────
function fulltextExists(PDO $conn, string $db, string $table, string $indexName): bool {
    $stmt = $conn->prepare("
        SELECT COUNT(*) FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = :db
          AND TABLE_NAME   = :tbl
          AND INDEX_NAME   = :idx
          AND INDEX_TYPE   = 'FULLTEXT'
    ");
    $stmt->execute([':db' => $db, ':tbl' => $table, ':idx' => $indexName]);
    return (int) $stmt->fetchColumn() > 0;
}

$results = [];

// ═════════════════════════════════════════════════════════════════════════════
// TABLE: user_remarks
// ═════════════════════════════════════════════════════════════════════════════

$indexes_user_remarks = [

    // Used by: badge counts, status filters, overdue queries, history fetch
    // Covers: WHERE user_unique_id = X AND history_h = 0 AND status = Y
    'idx_ur_user_status_h' => "
        ALTER TABLE user_remarks
        ADD INDEX idx_ur_user_status_h (user_unique_id, history_h, status)
    ",

    // Used by: fetchHistory, fetchCallHistory, reassign auth check
    // Covers: WHERE upload_data_id = X AND history_h = 0
    'idx_ur_upload_h' => "
        ALTER TABLE user_remarks
        ADD INDEX idx_ur_upload_h (upload_data_id, history_h)
    ",

    // Used by: reassign target-state check, bulkAssign existing-remark check
    // Covers: WHERE upload_data_id = X AND user_unique_id = Y AND history_h = Z
    'idx_ur_upload_user_h' => "
        ALTER TABLE user_remarks
        ADD INDEX idx_ur_upload_user_h (upload_data_id, user_unique_id, history_h)
    ",

    // Used by: overdue popup queries (follow_up_date + time range)
    // Covers: WHERE user_unique_id = X AND follow_up_date = Y AND history_h = 0
    'idx_ur_followup' => "
        ALTER TABLE user_remarks
        ADD INDEX idx_ur_followup (user_unique_id, history_h, follow_up_date, follow_up_time)
    ",

];

foreach ($indexes_user_remarks as $indexName => $sql) {
    if (indexExists($conn, $dbName, 'user_remarks', $indexName)) {
        $results[] = ['table' => 'user_remarks', 'index' => $indexName, 'status' => 'ALREADY EXISTS — skipped'];
    } else {
        try {
            $conn->exec($sql);
            $results[] = ['table' => 'user_remarks', 'index' => $indexName, 'status' => '✅ CREATED'];
        } catch (PDOException $e) {
            $results[] = ['table' => 'user_remarks', 'index' => $indexName, 'status' => '❌ ERROR: ' . $e->getMessage()];
        }
    }
}

// ═════════════════════════════════════════════════════════════════════════════
// TABLE: shi_upload_data
// ═════════════════════════════════════════════════════════════════════════════

$indexes_shi_upload = [

    // Used by: reassign upload lock, bulkAssign lock
    // Covers: WHERE id = X (primary key already exists, this is for assign_to_user filter)
    'idx_sud_assign_user' => "
        ALTER TABLE shi_upload_data
        ADD INDEX idx_sud_assign_user (assign_to_user)
    ",

    // Used by: overdue/followup queries joining to user_remarks
    // Covers: JOIN ON id = upload_data_id (covered by PK, but explicit for optimizer)
    'idx_sud_project' => "
        ALTER TABLE shi_upload_data
        ADD INDEX idx_sud_project (assign_project_name)
    ",

];

// FULLTEXT index for search — check separately
$fulltextIndexName = 'ft_lead_search';
if (fulltextExists($conn, $dbName, 'shi_upload_data', $fulltextIndexName)) {
    $results[] = ['table' => 'shi_upload_data', 'index' => $fulltextIndexName, 'status' => 'ALREADY EXISTS — skipped'];
} else {
    try {
        // Check which columns exist before adding FULLTEXT
        $cols = $conn->query("
            SELECT COLUMN_NAME FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = '{$dbName}' AND TABLE_NAME = 'shi_upload_data'
              AND COLUMN_NAME IN ('name','number','email')
        ")->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($cols)) {
            $colList = implode(',', $cols);
            $conn->exec("ALTER TABLE shi_upload_data ADD FULLTEXT INDEX ft_lead_search ({$colList})");
            $results[] = ['table' => 'shi_upload_data', 'index' => $fulltextIndexName, 'status' => "✅ CREATED on columns: {$colList}"];
        } else {
            $results[] = ['table' => 'shi_upload_data', 'index' => $fulltextIndexName, 'status' => '⚠️ SKIPPED — columns name/number/email not found'];
        }
    } catch (PDOException $e) {
        $results[] = ['table' => 'shi_upload_data', 'index' => $fulltextIndexName, 'status' => '❌ ERROR: ' . $e->getMessage()];
    }
}

foreach ($indexes_shi_upload as $indexName => $sql) {
    if (indexExists($conn, $dbName, 'shi_upload_data', $indexName)) {
        $results[] = ['table' => 'shi_upload_data', 'index' => $indexName, 'status' => 'ALREADY EXISTS — skipped'];
    } else {
        try {
            $conn->exec($sql);
            $results[] = ['table' => 'shi_upload_data', 'index' => $indexName, 'status' => '✅ CREATED'];
        } catch (PDOException $e) {
            $results[] = ['table' => 'shi_upload_data', 'index' => $indexName, 'status' => '❌ ERROR: ' . $e->getMessage()];
        }
    }
}

// ═════════════════════════════════════════════════════════════════════════════
// OUTPUT
// ═════════════════════════════════════════════════════════════════════════════
header('Content-Type: text/plain; charset=utf-8');
echo "═══════════════════════════════════════════════════════\n";
echo " Index Migration Report — " . date('Y-m-d H:i:s') . "\n";
echo " Database: {$dbName}\n";
echo "═══════════════════════════════════════════════════════\n\n";

$created = 0;
$skipped = 0;
$errors  = 0;

foreach ($results as $r) {
    printf("  %-20s %-30s %s\n", $r['table'], $r['index'], $r['status']);
    if (str_starts_with($r['status'], '✅')) $created++;
    elseif (str_starts_with($r['status'], '❌')) $errors++;
    else $skipped++;
}

echo "\n═══════════════════════════════════════════════════════\n";
echo " Created: {$created}  |  Already existed: {$skipped}  |  Errors: {$errors}\n";
echo "═══════════════════════════════════════════════════════\n";
echo "\n⚠️  DELETE this file (add_indexes.php) after running.\n";
?>
