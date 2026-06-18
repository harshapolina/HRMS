<?php
/**
 * Admin-only capacity diagnostic: PHP limits, MySQL connections, attendance_logs indexes.
 * Upload to production and open once (then restrict or delete). Requires hradminuser session.
 */
session_start();
header('Content-Type: text/html; charset=utf-8');

if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'hradminuser') {
    http_response_code(403);
    echo '<h1>403 Forbidden</h1><p>Log in as HR admin to run capacity diagnostics.</p>';
    exit;
}

require_once __DIR__ . '/config.php';

$applyIndex = isset($_GET['apply_index']) && $_GET['apply_index'] === '1';
$report = [
    'generated_at' => date('c'),
    'php' => [],
    'session' => [],
    'mysql' => [],
    'attendance_logs' => [],
    'recommendations' => [],
    'index_apply' => null,
];

$report['php'] = [
    'version' => PHP_VERSION,
    'sapi' => PHP_SAPI,
    'max_execution_time' => (int) ini_get('max_execution_time'),
    'memory_limit' => ini_get('memory_limit'),
    'post_max_size' => ini_get('post_max_size'),
    'max_input_time' => (int) ini_get('max_input_time'),
    'apcu_enabled' => function_exists('apcu_fetch'),
    'opcache_enabled' => function_exists('opcache_get_status') && @opcache_get_status(false),
];

$report['session'] = [
    'save_handler' => ini_get('session.save_handler'),
    'save_path' => ini_get('session.save_path'),
    'gc_maxlifetime' => (int) ini_get('session.gc_maxlifetime'),
    'cookie_name' => session_name(),
];

$db = new Config();
$pdo = $db->getConnection();

if (!$pdo) {
    $report['mysql']['connected'] = false;
    $report['recommendations'][] = 'Database connection failed — check config.php credentials on this server.';
} else {
    $report['mysql']['connected'] = true;

    $vars = ['max_connections', 'wait_timeout', 'interactive_timeout', 'max_user_connections', 'innodb_buffer_pool_size'];
    foreach ($vars as $var) {
        try {
            $stmt = $pdo->query("SHOW VARIABLES LIKE " . $pdo->quote($var));
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $report['mysql']['variables'][$var] = $row['Value'] ?? null;
        } catch (Throwable $e) {
            $report['mysql']['variables'][$var] = 'unavailable';
        }
    }

    try {
        $stmt = $pdo->query('SHOW STATUS LIKE "Threads_connected"');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $report['mysql']['threads_connected'] = $row['Value'] ?? null;
    } catch (Throwable $e) {
        $report['mysql']['threads_connected'] = 'unavailable';
    }

    try {
        $stmt = $pdo->query('SHOW STATUS LIKE "Max_used_connections"');
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $report['mysql']['max_used_connections'] = $row['Value'] ?? null;
    } catch (Throwable $e) {
        $report['mysql']['max_used_connections'] = 'unavailable';
    }

    try {
        $stmt = $pdo->query('SHOW INDEX FROM attendance_logs');
        $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $report['attendance_logs']['indexes'] = $indexes;

        $hasUserDateUnique = false;
        foreach ($indexes as $idx) {
            if ((int) ($idx['Non_unique'] ?? 1) === 0) {
                $keyName = $idx['Key_name'] ?? '';
                $cols = [];
                foreach ($indexes as $c) {
                    if (($c['Key_name'] ?? '') === $keyName) {
                        $cols[(int) ($c['Seq_in_index'] ?? 0)] = $c['Column_name'] ?? '';
                    }
                }
                ksort($cols);
                if (array_values($cols) === ['user_id', 'punch_date']) {
                    $hasUserDateUnique = true;
                    break;
                }
            }
        }
        $report['attendance_logs']['has_unique_user_date'] = $hasUserDateUnique;

        if (!$hasUserDateUnique) {
            $report['recommendations'][] = 'Missing UNIQUE(user_id, punch_date) on attendance_logs — punch idempotency and performance at scale depend on this index.';
            if ($applyIndex) {
                try {
                    $pdo->exec('ALTER TABLE attendance_logs ADD UNIQUE KEY uniq_user_punch_date (user_id, punch_date)');
                    $report['index_apply'] = 'Created UNIQUE KEY uniq_user_punch_date (user_id, punch_date).';
                    $report['attendance_logs']['has_unique_user_date'] = true;
                } catch (Throwable $e) {
                    $report['index_apply'] = 'Failed to create index: ' . $e->getMessage();
                }
            }
        } else {
            $report['recommendations'][] = 'UNIQUE(user_id, punch_date) index is present on attendance_logs.';
        }
    } catch (Throwable $e) {
        $report['attendance_logs']['error'] = $e->getMessage();
        $report['recommendations'][] = 'Could not inspect attendance_logs: ' . $e->getMessage();
    }
}

$maxConn = (int) ($report['mysql']['variables']['max_connections'] ?? 0);
if ($maxConn > 0 && $maxConn < 200) {
    $report['recommendations'][] = "MySQL max_connections is {$maxConn}. For 10k punch-ins in 2 minutes on shared hosting, expect connection exhaustion unless you upgrade hosting or add pooling.";
}

if (($report['session']['save_handler'] ?? '') === 'files') {
    $report['recommendations'][] = 'PHP sessions use file storage. Under burst load, migrate sessions to Redis on a VPS for better concurrency.';
}

if (!($report['php']['apcu_enabled'] ?? false)) {
    $report['recommendations'][] = 'APCu is not enabled. hr_settings still uses per-request cache; enable APCu on production for cross-request settings caching.';
}

$report['recommendations'][] = 'Target load: ~170 req/s for 10k users in 2 minutes. Compare PHP worker count (Hostinger panel) to this number.';
$report['recommendations'][] = 'Run load test: loadtests/punch_load_test.js (k6) starting with 500 virtual users.';

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>HR Capacity Diagnostics</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 24px; color: #1e293b; }
        h1 { font-size: 1.4rem; }
        h2 { font-size: 1.1rem; margin-top: 1.5rem; }
        table { border-collapse: collapse; width: 100%; max-width: 900px; margin-bottom: 1rem; }
        th, td { border: 1px solid #cbd5e1; padding: 8px 10px; text-align: left; font-size: 0.9rem; }
        th { background: #f1f5f9; }
        .ok { color: #166534; }
        .warn { color: #b45309; }
        .bad { color: #b91c1c; }
        ul { max-width: 900px; }
        code { background: #f8fafc; padding: 2px 6px; border-radius: 4px; }
    </style>
</head>
<body>
    <h1>HR Punch Capacity Diagnostics</h1>
    <p>Generated at <?php echo h($report['generated_at']); ?></p>

    <h2>PHP runtime</h2>
    <table>
        <?php foreach ($report['php'] as $key => $value): ?>
            <tr><th><?php echo h($key); ?></th><td><?php echo h(is_bool($value) ? ($value ? 'yes' : 'no') : $value); ?></td></tr>
        <?php endforeach; ?>
    </table>

    <h2>Session</h2>
    <table>
        <?php foreach ($report['session'] as $key => $value): ?>
            <tr><th><?php echo h($key); ?></th><td><?php echo h($value); ?></td></tr>
        <?php endforeach; ?>
    </table>

    <h2>MySQL</h2>
    <?php if (!($report['mysql']['connected'] ?? false)): ?>
        <p class="bad">Not connected.</p>
    <?php else: ?>
        <table>
            <tr><th>threads_connected</th><td><?php echo h($report['mysql']['threads_connected'] ?? ''); ?></td></tr>
            <tr><th>max_used_connections</th><td><?php echo h($report['mysql']['max_used_connections'] ?? ''); ?></td></tr>
            <?php foreach (($report['mysql']['variables'] ?? []) as $key => $value): ?>
                <tr><th><?php echo h($key); ?></th><td><?php echo h($value); ?></td></tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <h2>attendance_logs indexes</h2>
    <?php if (isset($report['attendance_logs']['error'])): ?>
        <p class="bad"><?php echo h($report['attendance_logs']['error']); ?></p>
    <?php else: ?>
        <p>
            UNIQUE(user_id, punch_date):
            <?php if ($report['attendance_logs']['has_unique_user_date'] ?? false): ?>
                <span class="ok">present</span>
            <?php else: ?>
                <span class="bad">missing</span>
                — <a href="?apply_index=1">Create index now</a>
            <?php endif; ?>
        </p>
        <?php if ($report['index_apply']): ?>
            <p><?php echo h($report['index_apply']); ?></p>
        <?php endif; ?>
        <?php if (!empty($report['attendance_logs']['indexes'])): ?>
            <table>
                <tr>
                    <?php foreach (array_keys($report['attendance_logs']['indexes'][0]) as $col): ?>
                        <th><?php echo h($col); ?></th>
                    <?php endforeach; ?>
                </tr>
                <?php foreach ($report['attendance_logs']['indexes'] as $row): ?>
                    <tr>
                        <?php foreach ($row as $cell): ?>
                            <td><?php echo h($cell); ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    <?php endif; ?>

    <h2>Recommendations</h2>
    <ul>
        <?php foreach ($report['recommendations'] as $item): ?>
            <li><?php echo h($item); ?></li>
        <?php endforeach; ?>
    </ul>

    <p><a href="dashboard.php">Back to dashboard</a></p>
</body>
</html>
