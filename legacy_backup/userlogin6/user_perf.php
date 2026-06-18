<?php
ini_set('display_errors', 1);        // DEV: enable during debugging; disable in production
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config.php'; // adjust path if needed
date_default_timezone_set('Asia/Kolkata');
header('Content-Type: application/json; charset=utf-8');

function debug_log($msg) {
    @file_put_contents('/tmp/user_perf_debug.log', date('[Y-m-d H:i:s] ') . $msg . PHP_EOL, FILE_APPEND);
}

// Shared hierarchy helpers (aligned with dashboard_data.php)
if (!function_exists('normalize_role')) {
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
                return 'business_head';
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
}

if (!function_exists('role_level')) {
    function role_level($normalizedRole) {
        $map = [
            'promoter' => 1,
            'business_head' => 2,
            'manager' => 3,
            'team_lead' => 4,
            'user' => 5,
        ];
        $r = strtolower(trim((string)$normalizedRole));
        return $map[$r] ?? 5;
    }
}

if (!function_exists('get_user_by_tablename')) {
    function get_user_by_tablename(PDO $conn, $tablename) {
        $stmt = $conn->prepare("SELECT tablename, username, useremail, user_type, assign_user, doj, employee_id FROM accounts WHERE tablename = :tn LIMIT 1");
        $stmt->bindParam(':tn', $tablename, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

if (!function_exists('build_upward_chain')) {
    function build_upward_chain(PDO $conn, $startUserRow) {
        $chain = [];
        if (!$startUserRow) return $chain;
        $visited = [];

        $curr = $startUserRow;
        $chain[] = [
            'tablename' => $curr['tablename'] ?? null,
            'username' => $curr['username'] ?? null,
            'useremail' => $curr['useremail'] ?? null,
            'user_type' => normalize_role($curr['user_type'] ?? 'user'),
            'doj' => $curr['doj'] ?? null,
            'employee_id' => $curr['employee_id'] ?? null
        ];
        $visited[$curr['tablename'] ?? ''] = true;

        $processManagers = function($userRow, &$chain, &$visited, $depth = 0) use (&$processManagers, $conn) {
            if ($depth > 10) return;
            $assignees = isset($userRow['assign_user']) ? array_filter(array_map('trim', explode(',', $userRow['assign_user']))) : [];
            foreach ($assignees as $assignee) {
                if (isset($visited[$assignee])) continue;
                $manager = get_user_by_tablename($conn, $assignee);
                if (!$manager) continue;
                $chain[] = [
                    'tablename' => $manager['tablename'] ?? null,
                    'username' => $manager['username'] ?? null,
                    'useremail' => $manager['useremail'] ?? null,
                    'user_type' => normalize_role($manager['user_type'] ?? 'user'),
                    'doj' => $manager['doj'] ?? null,
                    'employee_id' => $manager['employee_id'] ?? null
                ];
                $visited[$assignee] = true;
                $processManagers($manager, $chain, $visited, $depth + 1);
            }
        };

        $processManagers($curr, $chain, $visited);
        return $chain;
    }
}

if (!function_exists('canAccessUserData')) {
    function canAccessUserData(PDO $conn, $currentUserTablename, $targetUserTablename) {
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'superuseradmin') {
            return true;
        }
        if ($currentUserTablename === $targetUserTablename) {
            return true;
        }

        $currentUser = get_user_by_tablename($conn, $currentUserTablename);
        if (!$currentUser) return false;
        $targetUser = get_user_by_tablename($conn, $targetUserTablename);
        if (!$targetUser) return false;

        $targetRole = normalize_role($targetUser['user_type'] ?? 'user');
        $targetManagers = !empty($targetUser['assign_user']) ? array_filter(array_map('trim', explode(',', $targetUser['assign_user']))) : [];
        if (empty($targetManagers) && $targetRole !== 'promoter') {
            return false;
        }
        if (in_array($currentUserTablename, $targetManagers, true)) {
            return true;
        }
        foreach ($targetManagers as $managerTablename) {
            $manager = get_user_by_tablename($conn, $managerTablename);
            if (!$manager) continue;
            $managerChain = build_upward_chain($conn, $manager);
            foreach ($managerChain as $chainUser) {
                if (($chainUser['tablename'] ?? null) === $currentUserTablename) {
                    return true;
                }
            }
        }
        return false;
    }
}

try {
    session_start();
    $db = new Config();
    $conn = $db->getConnection();

    // NOTE: you confirmed this session key is correct for your app
    // Check for impersonate parameter first (for embedded mode from superadmin)
    $currentUserTablename = null;
    if (isset($_GET['impersonate']) && !empty($_GET['impersonate'])) {
        $currentUserTablename = trim((string)$_GET['impersonate']);
    } elseif (isset($_GET['current_user']) && !empty($_GET['current_user'])) {
        $currentUserTablename = trim((string)$_GET['current_user']);
    } else {
        $currentUserTablename = $_SESSION['tablename'] ?? null;
    }

    $action = $_GET['action'] ?? 'users';
    $debugMode = isset($_GET['debug']) && $_GET['debug'] == '1';

    // Common debug structure
    $debug = [
        'received' => [
            'action' => $action,
            'user_param' => $_GET['user'] ?? null,
            'month_raw' => $_GET['month'] ?? null,
            'start' => $_GET['start'] ?? null,
            'end' => $_GET['end'] ?? null,
            'session_tablename' => $currentUserTablename
        ],
        'notes' => []
    ];

    if ($debugMode) debug_log("ENTER action={$action} user=" . ($debug['received']['user_param'] ?? '') . " session=".$currentUserTablename);

    // helper to normalize role strings to a common hierarchy key
    $normalizeRole = function ($raw) {
        $s = strtolower(trim((string)$raw));
        $s = str_replace('_', ' ', $s);
        switch ($s) {
            case 'ceo':
            case 'promoter':
                return 'promoter';
            case 'business head':
            case 'businesshead':
            case 'bh':
                return 'business head';
            case 'manager':
            case 'm':
                return 'manager';
            case 'team lead':
            case 'teamlead':
            case 'team leader':
            case 'tl':
                return 'team lead';
            case 'sales executive':
            case 'sales':
            case 'user':
            default:
                return 'user';
        }
    };

    // ---------- ACTION: users (aligned with dashboard dropdown) ----------
    if ($action === 'users') {
        // If we cannot identify the current user, return empty list
        if (!$currentUserTablename) {
            echo json_encode(['users' => []]);
            exit;
        }

        // Fetch all active accounts
        $stmt = $conn->prepare("SELECT tablename, username, user_type, assign_user, is_active FROM accounts WHERE is_active = 1");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $final = [];
        foreach ($rows as $row) {
            $norm = normalize_role($row['user_type'] ?? 'user');
            if ($norm === 'director') {
                continue; // exclude director similar to dashboard
            }

            $canAccess = canAccessUserData($conn, $currentUserTablename, $row['tablename']);
            $isCurrentUser = ($row['tablename'] === $currentUserTablename);
            $isAssignedUser = !empty(trim($row['assign_user'] ?? ''));
            $isTopLevel = ($norm === 'promoter');

            if ($canAccess && ($isCurrentUser || $isAssignedUser || $isTopLevel)) {
                $row['user_type'] = $norm;
                $row['role_level'] = role_level($norm);
                $final[] = $row;
            }
        }

        // Sort: self first, then role level, then username
        usort($final, function($a, $b) use ($currentUserTablename) {
            $selfA = ($a['tablename'] === $currentUserTablename) ? 0 : 1;
            $selfB = ($b['tablename'] === $currentUserTablename) ? 0 : 1;
            if ($selfA !== $selfB) return $selfA <=> $selfB;
            $lvlCmp = ($a['role_level'] ?? 99) <=> ($b['role_level'] ?? 99);
            if ($lvlCmp !== 0) return $lvlCmp;
            return strcasecmp($a['username'] ?? '', $b['username'] ?? '');
        });

        // Map to response shape without exposing roles in labels
        $users = array_map(function($u) {
            return [
                'id' => (string)($u['tablename'] ?? ''),
                'label' => $u['username'] ?? ''
            ];
        }, $final);

        $resp = ['users' => $users];
        if ($debugMode) $resp['debug'] = $debug;
        echo json_encode($resp);
        exit;
    }

    // ---------- ACTION: data (enhanced debugging) ----------
    if ($action === 'data') {
        $user = $_GET['user'] ?? '';
        $monthRaw = $_GET['month'] ?? '';
        $start = $_GET['start'] ?? '';
        $end = $_GET['end'] ?? '';
        $yearParam = $_GET['year'] ?? null; // optional if you want to pass year in future

        // normalize month: accept 'Jul', 'july', '7', '07', 'October', etc.
        $month = $monthRaw;
        if (!empty($month) && !is_numeric($month)) {
            $map = [
              'jan'=>1,'feb'=>2,'mar'=>3,'apr'=>4,'may'=>5,'jun'=>6,
              'jul'=>7,'aug'=>8,'sep'=>9,'oct'=>10,'nov'=>11,'dec'=>12,
              'january'=>1,'february'=>2,'march'=>3,'april'=>4,'may'=>5,'june'=>6,
              'july'=>7,'august'=>8,'september'=>9,'october'=>10,'november'=>11,'december'=>12
            ];
            $low = strtolower(trim($month));
            if (isset($map[$low])) $month = $map[$low];
            else $month = '';
        }
        $month = ($month !== '') ? (int)$month : '';

        $debug['received']['month_normalized'] = $month;

        // attach extra debug data
        $debug['received']['user'] = $user;
        $debug['received']['filters'] = ['month_raw' => $monthRaw, 'month' => $month, 'start' => $start, 'end' => $end, 'yearParam' => $yearParam];

        if ($debugMode) debug_log("data: user={$user} monthRaw={$monthRaw} monthNorm={$month} start={$start} end={$end}");

        if (!$user) {
            $out = ['error' => 'Missing user parameter'];
            if ($debugMode) $out['debug'] = $debug;
            echo json_encode($out);
            exit;
        }

        // security: ensure requested user is descendant (if current user set)
        // Skip authorization check if:
        // 1. User is superadmin (superuseradmin role)
        // 2. Request is from embedded mode with impersonate parameter (superadmin viewing)
        $isSuperAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'superuseradmin';
        $isEmbeddedMode = isset($_GET['impersonate']) && !empty($_GET['impersonate']);
        
        if ($currentUserTablename && !$isSuperAdmin && !$isEmbeddedMode) {
            $ok = canAccessUserData($conn, $currentUserTablename, $user);
            if ($debugMode) {
                $debug['notes']['auth_ok'] = !!$ok;
                $debug['notes']['is_superadmin'] = $isSuperAdmin;
                $debug['notes']['is_embedded'] = $isEmbeddedMode;
                debug_log("data: auth_ok=" . json_encode(!!$ok) . " is_superadmin=" . ($isSuperAdmin ? '1' : '0') . " is_embedded=" . ($isEmbeddedMode ? '1' : '0'));
            }
            if (!$ok) {
                http_response_code(403);
                $out = ['error' => 'Not authorized to view this user'];
                if ($debugMode) $out['debug'] = $debug;
                echo json_encode($out);
                exit;
            }
        } else if ($debugMode) {
            $debug['notes']['auth_skipped'] = true;
            $debug['notes']['is_superadmin'] = $isSuperAdmin;
            $debug['notes']['is_embedded'] = $isEmbeddedMode;
            debug_log("data: auth_skipped is_superadmin=" . ($isSuperAdmin ? '1' : '0') . " is_embedded=" . ($isEmbeddedMode ? '1' : '0'));
        }

        // Fetch only latest active rows for this user (one row per lead)
        // so previous owner/project history doesn't leak into current user's performance.
        $params = [':user' => $user];
        $sql = <<<SQL
    SELECT ur.*
    FROM user_remarks ur
    INNER JOIN (
        SELECT upload_data_id, MAX(id) AS latest_id
        FROM user_remarks
        WHERE user_unique_id = :user AND history_h = 0
        GROUP BY upload_data_id
    ) latest ON latest.latest_id = ur.id
    WHERE ur.user_unique_id = :user AND ur.history_h = 0
    SQL;
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $debug['notes']['fetched_rows_count'] = count($rows);
        if ($debugMode) {
            $debug['notes']['fetch_sql'] = $sql;
            $debug['notes']['rows_sample'] = array_slice($rows, 0, 3);
            debug_log("data: fetched_rows=" . count($rows));
        }

        // Aggregation state & debug counters
        $statusCounts = [];
        $dailyCounts = [];

        $counters = [
            'total_parsed_entries' => 0,
            'accepted_by_date' => 0,
            'rejected_no_timestamp' => 0,
            'rejected_date_out_of_range' => 0,
            'rejected_no_status' => 0,
            'included_in_statusCounts' => 0
        ];

        $parsed_samples = []; // sample parsed entries for debug

        // local helper: check if date passes filters
        $acceptDate = function($dateStr) use ($month, $start, $end, $yearParam) {
            if (!$dateStr) return false;
            $ts = strtotime($dateStr);
            if ($ts === false) return false;
            $dY = (int)date('Y', $ts);
            $dM = (int)date('n', $ts);

            if (!empty($yearParam) && is_numeric($yearParam)) {
                // if year param provided, check year too
                if ($dY !== (int)$yearParam) return false;
            } else {
                // default: use current year when month filter applied
                if ($month && $dY !== (int)date('Y')) {
                    // If entry is not in current year, treat accordingly (do not accept)
                    // NOTE: you may want to change this behavior to accept any year - adjust if needed
                    return false;
                }
            }

            if ($month && $month !== '') {
                if ($dM !== (int)$month) return false;
                return true;
            }

            if ($start && $end) {
                $startStr = strlen($start) === 10 ? $start . ' 00:00:00' : $start;
                $endStr = strlen($end) === 10 ? $end . ' 23:59:59' : $end;
                $s = strtotime($startStr);
                $e = strtotime($endStr);
                if ($s === false || $e === false) return false;
                return ($ts >= $s && $ts <= $e);
            }

            // no filter: accept
            return true;
        };

        // ── UNIFIED PASS: Daily activity and status counts from history JSON (call/touch activity) ───
        // Both the daily line graph and the status cards/donut chart are driven by the exact same history logs.
        foreach ($rows as $rIndex => $r) {
            $assignmentStart = null;
            if (!empty($r['created_at'])) {
                $rowCreatedTs = strtotime($r['created_at']);
                if ($rowCreatedTs !== false) {
                    $assignmentStart = $rowCreatedTs;
                }
            }

            $parsedEntries = [];

            // Primary: 'history' column
            if (!empty($r['history'])) {
                $try = json_decode($r['history'], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($try)) {
                    foreach ($try as $entry) if (is_array($entry)) $parsedEntries[] = $entry;
                } else {
                    $debug['notes'][] = 'history json error: ' . json_last_error_msg();
                    if ($debugMode) debug_log("data: history json error for row id={$r['id']}: " . json_last_error_msg());
                }
            }

            // collect a few parsed samples
            if ($debugMode && count($parsed_samples) < 8) {
                $parsed_samples[] = array_slice($parsedEntries, 0, 8);
            }

            // Count history entries toward daily activity and status distribution
            foreach ($parsedEntries as $entry) {
                $counters['total_parsed_entries']++;

                // timestamp preference: entry timestamp -> time -> followUpDate+followUpTime
                $time = null;
                if (!empty($entry['timestamp'])) $time = $entry['timestamp'];
                elseif (!empty($entry['time'])) $time = $entry['time'];
                elseif (!empty($entry['followUpDate']) && !empty($entry['followUpTime'])) {
                    $time = $entry['followUpDate'] . ' ' . $entry['followUpTime'];
                }

                if (empty($time)) {
                    $counters['rejected_no_timestamp']++;
                    continue;
                }

                $entryTs = strtotime($time);
                if ($entryTs === false) {
                    $counters['rejected_no_timestamp']++;
                    continue;
                }

                // Only count activity STRICTLY AFTER assignment
                if ($assignmentStart !== null && $entryTs <= $assignmentStart) {
                    continue;
                }

                $timeForFilter = date('Y-m-d H:i:s', $entryTs);
                if (!$acceptDate($timeForFilter)) {
                    $counters['rejected_date_out_of_range']++;
                    continue;
                }

                $counters['accepted_by_date']++;

                // Add to daily counts
                $dateKey = date('Y-m-d', $entryTs);
                if ($dateKey) $dailyCounts[$dateKey] = ($dailyCounts[$dateKey] ?? 0) + 1;

                // Add to status counts (aligned with calls)
                $status = trim($entry['status'] ?? $entry['user_status'] ?? $entry['remark'] ?? '');
                if (!empty($status)) {
                    $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
                    $counters['included_in_statusCounts']++;
                }
            }
        } // end foreach rows


        // prepare debug summaries
        if ($debugMode) {
            $debug['notes']['parsed_samples'] = $parsed_samples;
            $debug['notes']['counters'] = $counters;
            $debug['notes']['statusCounts_before_return'] = $statusCounts;
            debug_log("data debug counters: " . json_encode($counters));
        }

        // finalize daily array
        ksort($dailyCounts);
        $daily = [];
        foreach ($dailyCounts as $d => $c) $daily[] = ['date' => $d, 'count' => (int)$c];

        $out = ['statusCounts' => $statusCounts, 'daily' => $daily];
        if ($debugMode) $out['debug'] = $debug;
        echo json_encode($out);
        exit;
    }
    
    //This is profile zone start
        if ($action === 'perf_status') {
            $debug = []; // collect debug data to send to console
            $debug[] = "Entered perf_status";
        
            // Resolve user (param or session)
            $requestUser = isset($_GET['user']) ? trim((string)$_GET['user']) : '';
            $debug[] = "Request param user: " . $requestUser;
        
            if ($requestUser === '') {
                if (!empty($currentUserTablename)) {
                    $user = $currentUserTablename;
                    $debug[] = "Using session user: " . $user;
                } else {
                    echo json_encode(['error' => 'Missing user and no session available', 'debug' => $debug]);
                    exit;
                }
            } else {
                $user = $requestUser;
                // --- Authorization: ensure session user can view requested user (if different) ---
                // Skip authorization check if superadmin or embedded mode
                $isSuperAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'superuseradmin';
                $isEmbeddedMode = isset($_GET['impersonate']) && !empty($_GET['impersonate']);
                
                if (!empty($currentUserTablename) && isset($requestUser) && $requestUser !== '' && $requestUser !== $currentUserTablename && !$isSuperAdmin && !$isEmbeddedMode) {
                    $ok = canAccessUserData($conn, $currentUserTablename, $requestUser);
                    $debug[] = "auth_check: session={$currentUserTablename} requested={$requestUser} ok=" . ($ok ? '1' : '0');
                    if (!$ok) {
                        http_response_code(403);
                        echo json_encode(['error' => 'Not authorized to view this user', 'debug' => $debug]);
                        exit;
                    }
                } else if ($isSuperAdmin || $isEmbeddedMode) {
                    $debug[] = "auth_check: skipped (superadmin=" . ($isSuperAdmin ? '1' : '0') . " embedded=" . ($isEmbeddedMode ? '1' : '0') . ")";
                }
                $debug[] = "Using requested user: " . $user;
            }
        
            $days = intval($_GET['days'] ?? 7);
            $dailyThreshold = intval($_GET['daily_threshold'] ?? 5);
            $debug[] = "Days window: {$days}, dailyThreshold: {$dailyThreshold}";
        
            // Fetch latest active rows only (one per lead for current assignee)
            $stmt = $conn->prepare(<<<SQL
SELECT ur.upload_data_id, ur.history, ur.created_at
FROM user_remarks ur
INNER JOIN (
    SELECT upload_data_id, MAX(id) AS latest_id
    FROM user_remarks
    WHERE user_unique_id = :user AND history_h = 0
    GROUP BY upload_data_id
) latest ON latest.latest_id = ur.id
WHERE ur.user_unique_id = :user AND ur.history_h = 0
SQL
            );
            $stmt->execute([':user' => $user]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $debug[] = "Fetched " . count($rows) . " latest active rows from user_remarks";
        
            // Prepare date range
            $tz = new DateTimeZone(date_default_timezone_get());
            $today = new DateTime('now', $tz);
            $dates = [];
            for ($i = 0; $i < $days; $i++) {
                $d = clone $today;
                $d->modify("-{$i} days");
                $dates[$d->format('Y-m-d')] = 0;
            }
        
            // Parse each history JSON
            foreach ($rows as $rIndex => $r) {
                $assignmentStart = null;
                if (!empty($r['created_at'])) {
                    $assignmentTs = strtotime($r['created_at']);
                    if ($assignmentTs !== false) {
                        $assignmentStart = $assignmentTs;
                    }
                }
 
                foreach (['history'] as $col) {
                    if (empty($r[$col])) continue;
                    $arr = json_decode($r[$col], true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $debug[] = "Row {$rIndex} {$col} JSON error: " . json_last_error_msg();
                        continue;
                    }
                    foreach ($arr as $entry) {
                        if (!is_array($entry)) continue;
                        $time = $entry['timestamp'] ?? ($entry['followUpDate'] ?? null);
                        if (!$time) continue;
                        $ts = strtotime($time);
                        if ($ts === false) continue;
 
                        // Reject entries at OR before assignment time (use <= not <)
                        // so history entries auto-created at assignment moment are excluded.
                        if ($assignmentStart !== null && $ts <= $assignmentStart) {
                            continue;
                        }
 
                        $dStr = date('Y-m-d', $ts);
                        if (isset($dates[$dStr])) $dates[$dStr]++;
                    }
                }
            }
        
            // Compute summary
            $todayStr = $today->format('Y-m-d');
            $todayCount = $dates[$todayStr] ?? 0;
            $sum = array_sum($dates);
            $avg = ($days > 0) ? round($sum / $days, 2) : 0.0;
        
            // ----------------- NEW: enhanced goal & progress logic -----------------
            $debug[] = "Dates bucket: " . json_encode($dates);
            $debug[] = "Today: {$todayCount}, Sum: {$sum}, Avg: {$avg}";
            
            // --- allow overrides by query params for testing ---
            $reqDailyTarget = isset($_GET['daily_target']) && is_numeric($_GET['daily_target']) ? intval($_GET['daily_target']) : null;
            $reqWarningCut  = isset($_GET['warning_cut']) && is_numeric($_GET['warning_cut']) ? intval($_GET['warning_cut']) : null;
            
            // --- attempt to load per-user goal from accounts table (optional; skip if not present) ---
            $perUserGoal = null;
            try {
                $stmtGoal = $conn->prepare("SELECT COALESCE(NULLIF(goal_daily, ''), NULL) AS g FROM accounts WHERE tablename = :tn LIMIT 1");
                $stmtGoal->execute([':tn' => $user]);
                $rgoal = $stmtGoal->fetch(PDO::FETCH_ASSOC);
                if ($rgoal && isset($rgoal['g']) && $rgoal['g'] !== null) {
                    // cast to int safely
                    $perUserGoal = is_numeric($rgoal['g']) ? intval($rgoal['g']) : null;
                    $debug[] = "Loaded per-user goal from accounts: " . var_export($perUserGoal, true);
                }
            } catch (Exception $e) {
                // table/column might not exist; just continue with defaults
                $debug[] = "Could not load per-user goal: " . $e->getMessage();
            }
            
            // --- final thresholds (priority: query param > per-user goal > default) ---
            $defaultGoal = 100;   // default calls per day target
            $defaultWarning = 80; // default warning threshold
            
            $dailyTarget = $reqDailyTarget ?? $perUserGoal ?? $defaultGoal;
            $warningCut  = $reqWarningCut ?? intval(max(1, floor($dailyTarget * 0.8))); // default warning 80% of goal if not set
            
            $debug[] = "Threshold selection: reqDailyTarget=" . var_export($reqDailyTarget, true) . ", perUserGoal=" . var_export($perUserGoal, true) . ", finalGoal={$dailyTarget}, warningCut={$warningCut}";
            
            // --- existing dangerous / consecutive-days logic ---
            $dangerousDays = isset($_GET['dangerous_days']) && is_numeric($_GET['dangerous_days']) ? intval($_GET['dangerous_days']) : 3;
            $consec = 0;
            krsort($dates);
            foreach ($dates as $d => $count) {
                if ($count < $dailyThreshold) {
                    $consec++;
                } else {
                    break;
                }
            }
            $dangerous = ($consec >= $dangerousDays);
            
            // --- decide status using goal/warningCut, and produce diff/percent ---
            if ($todayCount >= $dailyTarget) {
                $status = 'ok';
            } elseif ($todayCount >= $warningCut) {
                $status = 'warning';
            } else {
                $status = 'low';
            }
            
            $diffToGoal = max(0, $dailyTarget - $todayCount);           // calls remaining to reach today's goal
            $percentOfGoal = $dailyTarget > 0 ? round(($todayCount / $dailyTarget) * 100, 1) : 0.0;
            
            // helpful textual message for UI
            if ($diffToGoal === 0) {
                $message = "Goal met — great job!";
            } else {
                $message = "{$diffToGoal} calls to reach today's goal of {$dailyTarget}.";
            }
            
            // optional: show how many more per remaining hours? (skip for now, kept simple)
            
            // final debug notes
            $debug[] = "Goal={$dailyTarget}, WarningCut={$warningCut}, Today={$todayCount}, Diff={$diffToGoal}, Percent={$percentOfGoal}%, Consec={$consec}, Dangerous={$dangerous}";
            
            // --- RESPONSE ---
            $response = [
              'today' => $todayCount,
              'status' => $status,
              'dangerous' => $dangerous,
              'consec' => $consec,
              'goal' => $dailyTarget,
              'diff' => $diffToGoal,
              'percent' => $percentOfGoal,
              'message' => $message,
              'rolling' => ['days' => $days, 'sum' => $sum, 'avg' => $avg],
              'dates' => $dates,
              'thresholds' => [
                'daily_threshold' => $dailyThreshold,
                'dangerous_days' => $dangerousDays,
                'goal' => $dailyTarget,
                'warning_cut' => $warningCut
              ],
              'debug' => $debug
            ];
            
            echo json_encode($response);
            exit;
        }
    //This is profile zone End

    // default
    $out = ['error' => 'Invalid action'];
    if ($debugMode) $out['debug'] = $debug;
    echo json_encode($out);
    exit;

} catch (Exception $e) {
    debug_log("EXCEPTION in user_perf.php: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    http_response_code(500);
    $resp = ['error' => $e->getMessage()];
    if (isset($debugMode) && $debugMode) $resp['debug'] = ['exception' => $e->getMessage()];
    echo json_encode($resp);
    exit;
}
