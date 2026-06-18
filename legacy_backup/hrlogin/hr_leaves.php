<?php
$skip_superadmin_css = true;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'hradminuser') {
    header('Location: /');
    exit;
}

require_once __DIR__ . '/includes/db_mysqli.php';
try {
    $con = hr_mysqli_connect();
} catch (Throwable $e) {
    $con = null;
}

$message = '';

function leave_compute_days(string $start_date, string $end_date): int
{
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    return $start->diff($end)->days + 1;
}

// Handle Approval / Rejection
if ($con && isset($_GET['action'], $_GET['id']) && in_array($_GET['action'], ['approve', 'reject'], true)) {
    $id = (int) $_GET['id'];
    $status = $_GET['action'] === 'approve' ? 'Approved' : 'Rejected';

    $stmt_req = $con->prepare('SELECT user_id, leave_type_id, start_date, end_date, status FROM leave_requests WHERE id = ? LIMIT 1');
    if ($stmt_req) {
        $stmt_req->bind_param('i', $id);
        $stmt_req->execute();
        $request = $stmt_req->get_result()->fetch_assoc();
        $stmt_req->close();

        if ($request && $request['status'] === 'Pending') {
            if ($status === 'Approved') {
                $days = leave_compute_days($request['start_date'], $request['end_date']);
                $year = (int) date('Y', strtotime($request['start_date']));
                $user_id = (int) $request['user_id'];
                $leave_type_id = (int) $request['leave_type_id'];

                $stmt_bal = $con->prepare(
                    'UPDATE leave_balances SET used_count = used_count + ?
                     WHERE user_id = ? AND leave_type_id = ? AND year = ?'
                );
                if ($stmt_bal) {
                    $stmt_bal->bind_param('iiii', $days, $user_id, $leave_type_id, $year);
                    $stmt_bal->execute();
                    $stmt_bal->close();
                }
            }

            $stmt_upd = $con->prepare('UPDATE leave_requests SET status = ? WHERE id = ?');
            if ($stmt_upd) {
                $stmt_upd->bind_param('si', $status, $id);
                $stmt_upd->execute();
                $stmt_upd->close();
            }
            $message = "<div class='alert alert-success'>Request $status successfully!</div>";
        }
    }
}

// Pagination & Filtering Settings
$allowedLimits = [10, 50, 100, 200];
$recordsPerPage = isset($_GET['limit']) && in_array((int)$_GET['limit'], $allowedLimits) ? (int)$_GET['limit'] : 10;
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($currentPage - 1) * $recordsPerPage;

$search_query = isset($_GET['search']) ? $_GET['search'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$from_date = isset($_GET['from']) ? $_GET['from'] : '';
$to_date = isset($_GET['to']) ? $_GET['to'] : '';

$where_clauses = ["1=1"];
$params = [];
$types = "";

if ($search_query) {
    $where_clauses[] = "(a.username LIKE ? OR a.employee_id LIKE ? OR a.useremail LIKE ?)";
    $search_param = "%$search_query%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
    $types .= "sss";
}

if ($status_filter) {
    $where_clauses[] = "l.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if ($from_date && $to_date) {
    $where_clauses[] = "(l.start_date <= ? AND l.end_date >= ?)";
    $params = array_merge($params, [$to_date, $from_date]);
    $types .= "ss";
}

$where_sql = implode(" AND ", $where_clauses);

// Get Total Count
$count_sql = "SELECT COUNT(*) as total FROM leave_requests l JOIN accounts a ON l.user_id = a.id WHERE $where_sql";
$stmt_c = $con->prepare($count_sql);
if ($params) $stmt_c->bind_param($types, ...$params);
$stmt_c->execute();
$totalEntries = (int) ($stmt_c->get_result()->fetch_assoc()['total'] ?? 0);
$stmt_c->close();
$totalPages = max(1, (int) ceil($totalEntries / $recordsPerPage));
if ($currentPage > $totalPages) {
    $currentPage = $totalPages;
    $offset = ($currentPage - 1) * $recordsPerPage;
}

// Fetch Requests
$sql = "SELECT l.*, a.username, a.id as account_id, a.employee_id, a.tablename as uniqueid, a.useremail, a.phonenumber as contact, a.user_type as role, t.leave_name 
        FROM leave_requests l 
        JOIN accounts a ON l.user_id = a.id 
        JOIN leave_types t ON l.leave_type_id = t.id 
        WHERE $where_sql
        ORDER BY l.created_at DESC LIMIT ? OFFSET ?";
$params_final = array_merge($params, [$recordsPerPage, $offset]);
$types_final = $types . "ii";

$stmt = $con->prepare($sql);
$stmt->bind_param($types_final, ...$params_final);
$stmt->execute();
$res = $stmt->get_result();

$leave_requests = [];
while ($row = $res->fetch_assoc()) {
    $row['leave_days'] = leave_compute_days($row['start_date'], $row['end_date']);
    $leave_requests[] = $row;
}
$stmt->close();

// Calculate Showing Info
$showingStart = $totalEntries > 0 ? $offset + 1 : 0;
$showingEnd = min($offset + $recordsPerPage, $totalEntries);

// Global summary stats (not affected by table filters)
$leave_stats = ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0, 'pending_percent' => 0];
$stats_res = $con->query("SELECT
    COUNT(*) AS total,
    SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) AS pending,
    SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) AS approved,
    SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) AS rejected
    FROM leave_requests");
if ($stats_res && ($stats_row = $stats_res->fetch_assoc())) {
    $leave_stats['total'] = (int)($stats_row['total'] ?? 0);
    $leave_stats['pending'] = (int)($stats_row['pending'] ?? 0);
    $leave_stats['approved'] = (int)($stats_row['approved'] ?? 0);
    $leave_stats['rejected'] = (int)($stats_row['rejected'] ?? 0);
    $leave_stats['pending_percent'] = $leave_stats['total'] > 0
        ? (int)round(($leave_stats['pending'] / $leave_stats['total']) * 100)
        : 0;
}

function leave_status_badge_class($status)
{
    $map = ['Pending' => 'badge-pending', 'Approved' => 'badge-approved', 'Rejected' => 'badge-rejected'];
    return $map[$status] ?? 'badge-pending';
}

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

require_once __DIR__ . '/htmlopen.php';
?>
<link rel="stylesheet" href="./assets/css/style_dashboard.css?v=<?php echo time(); ?>" />
<link rel="stylesheet" href="./assets/css/unified_table_styles.css?v=<?php echo time(); ?>" />
<link rel="stylesheet" href="./assets/css/Users.css?v=<?php echo time(); ?>" />
<style>
  /* Base background - respects global theme */
  body, html {
    background: var(--bg-gradient) !important;
    background-attachment: fixed !important;
    background-size: cover !important;
    color: var(--text-dark) !important;
  }
  
  .incentivemain, .content { background: transparent !important; border: none !important; box-shadow: none !important; }
  
  /* Animated floating backgrounds - only in light mode for cleaner look */
  body:not(.dark-mode)::before, body:not(.dark-mode)::after {
    content: ''; position: fixed; width: 200vw; height: 200vh; border-radius: 50%; z-index: -1; opacity: .3;
    animation: 15s ease-in-out infinite alternate float;
  }
  body:not(.dark-mode)::before { background: radial-gradient(circle, #d2b4ff 0, transparent 70%); top: -10vh; right: -50vw; }
  body:not(.dark-mode)::after { background: radial-gradient(circle, #f9eb9c 0, transparent 70%); bottom: -100vh; left: -50vw; animation-delay: 2.5s; }
  @keyframes float { 0% { transform: translateY(0) scale(1); } 100% { transform: translateY(-20px) scale(1.05); } }

  .glass-card {
    background: var(--table-bg) !important;
    backdrop-filter: blur(15px);
    border-radius: 20px;
    border: 1px solid var(--table-border);
    box-shadow: var(--shadow);
  }
  .status-badge { padding: 6px 14px; border-radius: 10px; font-weight: 600; font-size: 0.85rem; }
  .badge-pending { background: #fffde7 !important; color: #f9a825 !important; }
  .badge-approved { background: #e8f5e9 !important; color: #2e7d32 !important; }
  .badge-rejected { background: #ffebee !important; color: #c62828 !important; }
  
  .action-btn { 
      padding: 8px 16px; border-radius: 10px; font-size: 0.8rem; font-weight: 700; text-decoration: none; transition: 0.3s;
      display: inline-flex; align-items: center; justify-content: center; min-width: 90px;
  }
  .btn-approve { background: #2e7d32; color: white !important; border: none; }
  .btn-approve:hover { background: #1b5e20; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(46,125,50,0.3); }
  .btn-reject { background: #c62828; color: white !important; border: none; }
  .btn-reject:hover { background: #b71c1c; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(198,40,40,0.3); }
  
  .actions-cell { display: flex; gap: 8px; justify-content: center; align-items: center; }

  /* Leave page root — overrides generic unified-table breakpoints + column-hide behavior */
  .hr-leaves-page .user-table-container {
      background: transparent !important;
      border: none !important;
      box-shadow: none !important;
  }

  .hr-leaves-page .container-fluid {
      padding-top: 10px !important;
  }

  .hr-leaves-page .user-table-scroll-wrapper {
      background: transparent !important;
  }

  .hr-leaves-page .user-data-table {
      border-collapse: separate !important;
      border-spacing: 0 12px !important;
      background: transparent !important;
  }

  .hr-leaves-page .user-data-table.unified-table tbody tr {
      background: var(--table-bg) !important;
      border-radius: 12px !important;
      overflow: hidden !important;
      border: none !important;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.06) !important;
  }

  .hr-leaves-page .user-data-table tbody td {
      border: none !important;
      background: transparent !important;
  }

  /* Rounded “card” edges on data rows only — JS adds edge classes on visible tbody cells */
  .hr-leaves-page .user-data-table thead th {
      border-radius: 0 !important;
  }

  .hr-leaves-page .user-data-table tbody td.leave-edge-left {
      border-top-left-radius: 12px !important;
      border-bottom-left-radius: 12px !important;
  }
  .hr-leaves-page .user-data-table tbody td.leave-edge-right {
      border-top-right-radius: 12px !important;
      border-bottom-right-radius: 12px !important;
  }
  .hr-leaves-page .user-data-table tbody td.leave-single-cell {
      border-radius: 12px !important;
  }

  /* ==========================================================================
     Premium Dark Mode Styles (Neutral Monochrome)
     ========================================================================== */

  /* Glassmorphism Cards */
  body.dark-mode .glass-card {
      background: rgba(0, 0, 0, 0.6) !important;
      backdrop-filter: blur(25px) saturate(180%) !important;
      -webkit-backdrop-filter: blur(25px) saturate(180%) !important;
      border: 1px solid rgba(255, 255, 255, 0.1) !important;
      box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.5) !important;
  }
  
  /* Form Inputs, Selects, and Dropdowns */
  body.dark-mode .form-select,
  body.dark-mode .form-control {
      background-color: rgba(255, 255, 255, 0.05) !important;
      color: #ffffff !important;
      border-color: rgba(255, 255, 255, 0.1) !important;
      backdrop-filter: blur(10px) !important;
      -webkit-backdrop-filter: blur(10px) !important;
  }
  body.dark-mode .search-input {
      background-color: rgba(255, 255, 255, 0.05) !important;
      color: #ffffff !important;
      border: 1px solid transparent !important;
      backdrop-filter: blur(10px) !important;
      -webkit-backdrop-filter: blur(10px) !important;
  }
  body.dark-mode .form-select:focus,
  body.dark-mode .form-control:focus {
      border-color: #ffffff !important;
      box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.25) !important;
      background-color: rgba(0, 0, 0, 0.8) !important;
  }
  body.dark-mode .search-input:focus {
      border: none !important;
      border-color: transparent !important;
      outline: none !important;
      box-shadow: none !important;
      background-color: rgba(255, 255, 255, 0.08) !important;
  }
  body.dark-mode .search-input::placeholder {
      color: rgba(255, 255, 255, 0.5) !important;
  }

  /* Dark mode — search bar: no white/light border (overrides Users.css / style_dashboard / desktop #ddd) */
  body.dark-mode .hr-leaves-page .leave-controls-card .filter-group-search .search-input,
  body.dark-mode .hr-leaves-page .leave-controls-card #leaveFilterForm .search-input,
  body.dark-mode .content.hr-leaves-page .search-input,
  body.dark-mode .hr-leaves-page input#leaveSearch {
      border: none !important;
      border-color: transparent !important;
      outline: none !important;
      box-shadow: none !important;
  }
  body.dark-mode .hr-leaves-page .leave-controls-card .filter-group-search .search-input:focus,
  body.dark-mode .hr-leaves-page .leave-controls-card #leaveFilterForm .search-input:focus,
  body.dark-mode .content.hr-leaves-page .search-input:focus,
  body.dark-mode .hr-leaves-page input#leaveSearch:focus {
      border: none !important;
      border-color: transparent !important;
      outline: none !important;
      box-shadow: none !important;
  }

  /* Table Headers & Rows — header lighter than row cards (match Attendance Report) */
  body.dark-mode .hr-leaves-page .user-data-table thead th {
      background: #2c2c2e !important;
      color: rgba(255, 255, 255, 0.65) !important;
      border-bottom: 1px solid rgba(255, 255, 255, 0.12) !important;
      font-weight: 600 !important;
      text-transform: uppercase !important;
      letter-spacing: 0.5px !important;
  }
  body.dark-mode .hr-leaves-page .user-data-table.unified-table tbody tr {
      background: var(--table-bg) !important;
      border: 1px solid var(--table-border) !important;
      box-shadow: none !important;
  }
  body.dark-mode .hr-leaves-page .user-data-table.unified-table tbody tr:hover {
      background: var(--table-hover) !important;
      border-color: var(--table-border) !important;
      box-shadow: none !important;
      transform: translateY(-2px) scale(1.005) !important;
  }

  /* Table Cells & Text Readability */
  body.dark-mode .hr-leaves-page .user-data-table tbody td {
      color: rgba(255, 255, 255, 0.85) !important;
  }
  body.dark-mode .hr-leaves-page .user-data-table tbody td.fw-bold {
      color: rgba(255, 255, 255, 0.95) !important;
  }
  body.dark-mode .hr-leaves-page .user-data-table tbody tr td:first-child,
  body.dark-mode .hr-leaves-page .user-data-table tbody tr td.fw-bold[style*="color: #227477"],
  body.dark-mode .hr-leaves-page .user-data-table tbody tr td[style*="color: #227477"] {
      color: #ffffff !important; /* Strictly white/monochrome */
  }
  body.dark-mode .hr-leaves-page .user-data-table tbody tr td div[style*="color: #777"] {
      color: rgba(255, 255, 255, 0.5) !important;
  }

  /* Toolbar Filter & Column Visibility Buttons */
  body.dark-mode .hr-leaves-page .btn-filter,
  body.dark-mode .hr-leaves-page .btn-column-visibility {
      background: rgba(255, 255, 255, 0.05) !important;
      border: 1px solid rgba(255, 255, 255, 0.1) !important;
      color: #ffffff !important;
  }
  body.dark-mode .hr-leaves-page .btn-filter:hover,
  body.dark-mode .hr-leaves-page .btn-column-visibility:hover {
      background: rgba(255, 255, 255, 0.1) !important;
      border-color: rgba(255, 255, 255, 0.25) !important;
      color: #ffffff !important;
  }

  /* Limit Select & Dropdown Options styling */
  body.dark-mode .hr-leaves-page .leave-controls-card .users-limit-select {
      background-color: transparent !important;
      background-image: none !important;
      color: #ffffff !important;
  }
  body.dark-mode .leave-controls-card .column-dropdown {
      background: rgba(18, 18, 18, 0.98) !important;
      backdrop-filter: blur(15px) !important;
      -webkit-backdrop-filter: blur(15px) !important;
      border: 1px solid rgba(255, 255, 255, 0.1) !important;
      box-shadow: 0 10px 25px rgba(0,0,0,0.5) !important;
  }
  body.dark-mode .leave-controls-card .column-dropdown label {
      color: rgba(255, 255, 255, 0.8) !important;
  }
  body.dark-mode .leave-controls-card .column-dropdown label:hover {
      background: rgba(255, 255, 255, 0.05) !important;
      color: #ffffff !important;
  }

  /* Pagination Styling */
  body.dark-mode .page-btn {
      color: rgba(255, 255, 255, 0.8) !important;
      background: rgba(0, 0, 0, 0.6) !important;
      border: 1px solid rgba(255, 255, 255, 0.1) !important;
  }
  body.dark-mode .page-btn:hover:not(.disabled):not(.active) {
      background: rgba(255, 255, 255, 0.1) !important;
      color: #ffffff !important;
  }
  body.dark-mode .page-btn.disabled {
      color: rgba(255, 255, 255, 0.3) !important;
      background: rgba(0, 0, 0, 0.2) !important;
      border-color: rgba(255, 255, 255, 0.05) !important;
  }
  body.dark-mode .page-btn.active {
      background: #ffffff !important;
      color: #000000 !important;
      border-color: #ffffff !important;
      font-weight: bold !important;
  }

  /* Vibrant Neon-Glow Status Badges */
  body.dark-mode .status-badge {
      padding: 6px 14px !important;
      border-radius: 10px !important;
      font-weight: 600 !important;
      font-size: 0.85rem !important;
      display: inline-block !important;
  }
  body.dark-mode .status-badge.badge-pending {
      background: rgba(245, 158, 11, 0.15) !important;
      color: #fbbf24 !important;
      border: 1px solid rgba(245, 158, 11, 0.3) !important;
  }
  body.dark-mode .status-badge.badge-approved {
      background: rgba(16, 185, 129, 0.15) !important;
      color: #34d399 !important;
      border: 1px solid rgba(16, 185, 129, 0.3) !important;
  }
  body.dark-mode .status-badge.badge-rejected {
      background: rgba(239, 68, 68, 0.15) !important;
      color: #f87171 !important;
      border: 1px solid rgba(239, 68, 68, 0.3) !important;
  }

  /* Search & Filter Layout - Desktop Defaults */
  #leaveFilterForm { display: flex; flex-direction: column; align-items: flex-end; gap: 8px; }
  .filter-group { display: flex; align-items: center; gap: 8px; }

  /* Leave controls: match Employees/Attendance single-row toolbar */
  .leave-controls-card {
      background: transparent !important;
      border: none !important;
      box-shadow: none !important;
      padding: 0 !important;
      margin-top: 0 !important;
  }

  body.dark-mode .leave-controls-card {
      background: transparent !important;
      border: none !important;
      box-shadow: none !important;
  }

  /* Remove empty left block so search starts at far left */
  .leave-controls-card .control-left {
      display: none !important;
  }

  .leave-controls-card .control-bar {
      justify-content: flex-start !important;
      gap: 16px !important;
      flex-wrap: nowrap !important;
  }

  .leave-controls-card .header-tools-wrapper {
      width: 100% !important;
  }

  .leave-controls-card .btn-column-visibility {
      min-width: 136px !important;
      white-space: nowrap !important;
  }

  @media (min-width: 769px) {
      #leaveFilterForm {
          width: 100% !important;
          display: flex !important;
          flex-direction: row !important;
          align-items: center !important;
          justify-content: flex-start !important;
          flex-wrap: nowrap !important;
          gap: 12px !important;
      }

      #leaveFilterForm .filter-group {
          flex-wrap: nowrap !important;
          gap: 12px !important;
          align-items: center !important;
      }

      #leaveFilterForm .filter-group:first-child {
          flex: 1 1 auto !important;
          min-width: 0 !important;
      }

      #leaveFilterForm .filter-group:last-child {
          margin-left: auto !important;
          flex: 0 0 auto !important;
      }

      #leaveFilterForm .search-box {
          width: 100% !important;
      }

      #leaveFilterForm .search-input {
          width: 100% !important;
          min-width: 320px !important;
      }

      /* Uniform toolbar height — match All Status (form-select) */
      .leave-controls-card .leave-status-select,
      .leave-controls-card #leaveFilterForm .search-input,
      .leave-controls-card .btn-filter,
      .leave-controls-card .btn-column-visibility,
      .leave-controls-card .page-size-selector {
          height: 38px !important;
          min-height: 38px !important;
          max-height: 38px !important;
          box-sizing: border-box !important;
      }

      .leave-controls-card .leave-status-select {
          padding: 0 28px 0 12px !important;
          font-size: 14px !important;
          line-height: 1.5 !important;
          border-radius: 8px !important;
          border: 1px solid #ddd !important;
          background-color: #fff !important;
          display: inline-flex !important;
          align-items: center !important;
      }

      .leave-controls-card #leaveFilterForm .search-input {
          padding: 0 16px 0 44px !important;
          line-height: normal !important;
          border-radius: 8px !important;
          border: 1px solid #ddd !important;
          font-size: 14px !important;
      }

      .leave-controls-card .btn-filter,
      .leave-controls-card .btn-column-visibility {
          padding: 0 18px !important;
          display: inline-flex !important;
          align-items: center !important;
          justify-content: center !important;
          border-radius: 8px !important;
          font-size: 14px !important;
      }

      .leave-controls-card .page-size-selector {
          padding: 0 10px !important;
          display: inline-flex !important;
          align-items: center !important;
          justify-content: center !important;
      }

      .leave-controls-card .page-size-selector select.users-limit-select {
          height: 100% !important;
          padding: 0 4px !important;
          line-height: normal !important;
      }
  }

  /* Users.css adds ::after on .page-size-selector ≤427px — stacks with native select = two arrows */
  .hr-leaves-page .leave-controls-card .page-size-selector::after,
  .hr-leaves-page .leave-controls-card .page-size-selector::before {
      content: none !important;
      display: none !important;
  }

  /* Rows limit — mirror Employee (`users.php`): native caret, compact box */
  .leave-controls-card .page-size-selector {
      display: inline-flex !important;
      align-items: center !important;
      justify-content: center !important;
      flex: 0 0 auto !important;
      flex-shrink: 0 !important;
      position: relative !important;
      min-width: 64px !important;
      max-width: 76px !important;
      padding: 8px 10px !important;
      border-radius: 8px !important;
      border: 1px solid #ddd !important;
      background: #ffffff !important;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02) !important;
      overflow: hidden !important;
      box-sizing: border-box !important;
  }

  .leave-controls-card .page-size-selector select.users-limit-select {
      border: 0 !important;
      margin: 0 !important;
      background: transparent !important;
      box-shadow: none !important;
      outline: none !important;
      padding: 2px 4px !important;
      color: #333 !important;
      font-size: 14px !important;
      font-weight: 500 !important;
      width: 100% !important;
      min-width: 0 !important;
      max-width: 100% !important;
      -webkit-appearance: menulist !important;
      appearance: menulist !important;
      cursor: pointer !important;
      text-align: center !important;
  }

  body.dark-mode .leave-controls-card .page-size-selector {
      background: rgba(255, 255, 255, 0.05) !important;
      border: 1px solid rgba(255, 255, 255, 0.1) !important;
      box-sizing: border-box !important;
  }

  body.dark-mode .leave-controls-card .page-size-selector select.users-limit-select {
      color: #ffffff !important;
      background-color: transparent !important;
      background-image: none !important;
      border-color: transparent !important;
      backdrop-filter: none !important;
      -webkit-appearance: menulist !important;
      appearance: menulist !important;
  }

  /* Employee page: Filters + column btn use `.btn-filter` not outline-secondary */
  .leave-controls-card #openFilterBtn {
      border: 1px solid #ddd !important;
      background: white !important;
      color: #555 !important;
  }

  body.dark-mode .leave-controls-card #openFilterBtn {
      background: rgba(255, 255, 255, 0.05) !important;
      border: 1px solid rgba(255, 255, 255, 0.1) !important;
      color: #fff !important;
  }

  /* Mobile — legacy toolbar tweaks (spacing handled in attendance-style block below) */
  @media (max-width: 768px) {
    .hr-leaves-page .user-table-container {
        margin-top: 0 !important;
    }
    .actions-cell { flex-direction: column; gap: 6px; }
    
    /* Navbar tweaks via specificity */
    .hr-navbar { padding: 0 15px !important; }
    .navbar-right-items { gap: 10px !important; }
    .user-profile-sidebar { padding: 0 !important; width: 40px !important; height: 40px !important; justify-content: center !important; }
    .user-avatar-small { width: 32px !important; height: 32px !important; margin: 0 !important; }
    
    /* Table tweaks */
    .unified-table tbody td { padding: 12px 8px !important; font-size: 12px !important; }
    .unified-table thead th { padding: 10px 8px !important; font-size: 11px !important; }
  }
  
  @media (max-width: 480px) {
    .pagination-controls { gap: 4px !important; }
    .page-btn { padding: 6px 8px !important; font-size: 0.75rem !important; }
  }

  /* Mobile: hide the table scroll wrapper so the card layout shows instead */
  @media (max-width: 768px) {
      .hr-leaves-page .user-table-scroll-wrapper {
          display: none !important;
      }
  }

  /* Scoped dark toolbar — avoids double-chevrons / overflow vs Employee page */
  body.dark-mode .leave-controls-card select[name="status"] {
      background-color: rgba(255, 255, 255, 0.05) !important;
      border: 1px solid rgba(255, 255, 255, 0.1) !important;
      color: #ffffff !important;
      background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23ffffff' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e") !important;
      background-repeat: no-repeat !important;
      background-position: right 10px center !important;
      background-size: 14px 10px !important;
      padding-right: 28px !important;
      -webkit-appearance: none !important;
      appearance: none !important;
  }

  body.dark-mode .leave-controls-card select[name="status"] option {
      background-color: #1e1e24 !important;
      color: #ffffff !important;
  }

  body.dark-mode .leave-controls-card select[name="limit"] option {
      background-color: #1e1e24 !important;
      color: #ffffff !important;
  }

  body.dark-mode .leave-controls-card select[name="status"]:focus,
  body.dark-mode .leave-controls-card #openFilterBtn:focus {
      border-color: #2a8c90 !important;
      outline: none !important;
      box-shadow: 0 0 0 2px rgba(42, 140, 144, 0.2) !important;
  }

  body.dark-mode .leave-controls-card .page-size-selector:focus-within {
      border-color: #2a8c90 !important;
      box-shadow: 0 0 0 2px rgba(42, 140, 144, 0.2) !important;
  }

  .summary-wrapper {
      position: relative;
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 10px;
      padding: 0 5px;
  }
  .summary-section {
      display: flex;
      gap: 15px;
      overflow-x: auto;
      scroll-behavior: smooth;
      -ms-overflow-style: none;
      scrollbar-width: none;
      padding: 6px 5px;
      flex-grow: 1;
  }
  .summary-section::-webkit-scrollbar { display: none; }
  .summary-section .summary-card {
      background: #ffffff !important;
      border-radius: 50px !important;
      padding: 10px 25px !important;
      color: #333 !important;
      min-width: 180px !important;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05) !important;
      border: 1px solid #e0e0e0 !important;
      font-weight: 600 !important;
      cursor: pointer !important;
      transition: all 0.25s ease-in-out !important;
  }
  .summary-section .summary-card:hover {
      transform: translateY(-2px) !important;
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1) !important;
  }
  .summary-section .summary-card.stat-card-headcount { border: 2px solid #0ea5e9 !important; }
  .summary-section .summary-card.stat-card-present { border: 2px solid #10b981 !important; }
  .summary-section .summary-card.stat-card-absent { border: 2px solid #ef4444 !important; }
  .summary-section .summary-card.stat-card-late { border: 2px solid #f59e0b !important; }

  /* Active Filter Styles - Light Mode */
  .summary-section .summary-card.stat-card-present.active-filter { background: #d1fae5 !important; border: 3px solid #10b981 !important; }
  .summary-section .summary-card.stat-card-absent.active-filter { background: #fee2e2 !important; border: 3px solid #ef4444 !important; }
  .summary-section .summary-card.stat-card-late.active-filter { background: #fef3c7 !important; border: 3px solid #f59e0b !important; }
  .summary-arrow {
      background: white;
      border: 1px solid #ddd;
      border-radius: 50%;
      width: 32px;
      height: 32px;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
      z-index: 2;
      flex-shrink: 0;
      color: #666;
      font-size: 18px;
  }

  /* Summary Card & Arrow Dark Mode Overrides */
  body.dark-mode .summary-section .summary-card {
      background: rgba(0, 0, 0, 0.6) !important;
      backdrop-filter: blur(25px) saturate(180%) !important;
      -webkit-backdrop-filter: blur(25px) saturate(180%) !important;
      color: rgba(255, 255, 255, 0.95) !important;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3) !important;
      transition: all 0.25s ease-in-out !important;
  }
  body.dark-mode .summary-section .summary-card:hover {
      background: rgba(20, 20, 20, 0.8) !important;
      transform: translateY(-2px) !important;
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4) !important;
  }
  body.dark-mode .summary-section .summary-card.stat-card-late {
      border: 2px solid rgba(245, 158, 11, 0.6) !important;
  }
  body.dark-mode .summary-section .summary-card.stat-card-present {
      border: 2px solid rgba(16, 185, 129, 0.6) !important;
  }
  body.dark-mode .summary-section .summary-card.stat-card-absent {
      border: 2px solid rgba(239, 68, 68, 0.6) !important;
  }

  /* Active Filter Styles - Dark Mode */
  body.dark-mode .summary-section .summary-card.stat-card-late.active-filter { background: rgba(245, 158, 11, 0.15) !important; border: 3px solid #f59e0b !important; }
  body.dark-mode .summary-section .summary-card.stat-card-present.active-filter { background: rgba(16, 185, 129, 0.15) !important; border: 3px solid #10b981 !important; }
  body.dark-mode .summary-section .summary-card.stat-card-absent.active-filter { background: rgba(239, 68, 68, 0.15) !important; border: 3px solid #ef4444 !important; }
  body.dark-mode .summary-arrow {
      background: rgba(0, 0, 0, 0.6) !important;
      border: 1px solid rgba(255, 255, 255, 0.1) !important;
      color: rgba(255, 255, 255, 0.8) !important;
  }
  body.dark-mode .summary-arrow:hover {
      background: rgba(30, 30, 30, 0.9) !important;
      color: #fff !important;
  }

  /* Sleek Premium Action Buttons (Dark Mode) */
  body.dark-mode .btn-approve {
      background: #059669 !important;
      color: white !important;
      box-shadow: 0 4px 10px rgba(5, 150, 105, 0.3) !important;
  }
  body.dark-mode .btn-approve:hover {
      background: #10b981 !important;
      box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4) !important;
  }
  body.dark-mode .btn-reject {
      background: #dc2626 !important;
      color: white !important;
      box-shadow: 0 4px 10px rgba(220, 38, 38, 0.3) !important;
  }
  body.dark-mode .btn-reject:hover {
      background: #ef4444 !important;
      box-shadow: 0 4px 15px rgba(239, 68, 68, 0.4) !important;
  }

  /* Filter Modal Dark Mode Overrides */
  body.dark-mode .users-filter-modal .users-filter-content {
      background: rgba(18, 18, 18, 0.98) !important;
      backdrop-filter: blur(25px) saturate(180%) !important;
      -webkit-backdrop-filter: blur(25px) saturate(180%) !important;
      border: 1px solid rgba(255, 255, 255, 0.12) !important;
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6) !important;
  }
  body.dark-mode .users-filter-modal .users-filter-header,
  body.dark-mode .users-filter-modal .users-filter-footer {
      border-color: rgba(255, 255, 255, 0.1) !important;
  }
  body.dark-mode .users-filter-modal .users-filter-close {
      color: rgba(255, 255, 255, 0.6) !important;
  }
  body.dark-mode .users-filter-modal .users-filter-close:hover {
      color: #ffffff !important;
  }
  body.dark-mode .users-filter-modal .users-filter-item label {
      color: rgba(255, 255, 255, 0.6) !important;
  }
  body.dark-mode .users-filter-modal .users-filter-input {
      background: rgba(255, 255, 255, 0.05) !important;
      border: 1px solid rgba(255, 255, 255, 0.1) !important;
      color: #ffffff !important;
  }
  body.dark-mode .users-filter-modal .users-filter-input:focus {
      border-color: #ffffff !important;
      box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.25) !important;
  }
  body.dark-mode .users-filter-modal [style*="color: #227477"] {
      color: #ffffff !important; /* Strictly white label override */
  }
  body.dark-mode .users-filter-modal hr {
      border-color: rgba(255, 255, 255, 0.1) !important;
  }
  
  /* Outline secondary buttons ("Today", "Week", "Month") */
  body.dark-mode .users-filter-modal .btn-outline-secondary {
      border-color: rgba(255, 255, 255, 0.15) !important;
      color: rgba(255, 255, 255, 0.8) !important;
  }
  body.dark-mode .users-filter-modal .btn-outline-secondary:hover {
      background: rgba(255, 255, 255, 0.1) !important;
      color: #ffffff !important;
      border-color: rgba(255, 255, 255, 0.3) !important;
  }

  /* Modal Footer Buttons */
  body.dark-mode .users-filter-modal .btn-users-close {
      background: rgba(255, 255, 255, 0.05) !important;
      border: 1px solid rgba(255, 255, 255, 0.1) !important;
      color: #ffffff !important;
  }
  body.dark-mode .users-filter-modal .btn-users-close:hover {
      background: rgba(255, 255, 255, 0.1) !important;
  }
  body.dark-mode .users-filter-modal .btn-users-clear {
      background: rgba(239, 68, 68, 0.15) !important;
      border: 1px solid rgba(239, 68, 68, 0.3) !important;
      color: #f87171 !important;
  }
  body.dark-mode .users-filter-modal .btn-users-clear:hover {
      background: rgba(239, 68, 68, 0.25) !important;
      color: #ffffff !important;
  }
  body.dark-mode .users-filter-modal .btn-users-apply {
      background: #ffffff !important;
      color: #000000 !important;
      border: none !important;
      font-weight: 600 !important;
  }
  body.dark-mode .users-filter-modal .btn-users-apply:hover {
      background: rgba(255, 255, 255, 0.85) !important;
  }

  /* Mobile UI — matches attendance report pattern */
  @media (max-width: 768px) {
      .content.hr-leaves-page {
          padding: 10px !important;
      }

      .hr-leaves-page .container-fluid {
          padding-top: 10px !important;
          padding-bottom: calc(72px + env(safe-area-inset-bottom, 0px)) !important;
      }

      .hr-leaves-page .control-header-mobile {
          display: none !important;
      }

      .hr-leaves-page .summary-wrapper,
      .hr-leaves-page .summary-wrapper.pt-1,
      .hr-leaves-page .summary-wrapper.mb-2 {
          padding-top: 0 !important;
          padding-bottom: 0 !important;
          margin-bottom: 8px !important;
      }

      .hr-leaves-page .summary-section {
          padding: 4px 5px !important;
      }

      .hr-leaves-page .leave-controls-card.glass-card {
          background: transparent !important;
          border: none !important;
          box-shadow: none !important;
          padding: 0 !important;
          margin-top: 0 !important;
          margin-bottom: 8px !important;
      }

      .hr-leaves-page .leave-controls-card .control-bar {
          gap: 0 !important;
          margin: 0 !important;
      }

      .hr-leaves-page .leave-controls-card .control-right,
      .hr-leaves-page .leave-controls-card .header-tools-wrapper {
          width: 100% !important;
      }

      .hr-leaves-page .user-table-container {
          margin-top: 0 !important;
          padding: 0 !important;
      }

      .hr-leaves-page .leave-controls-card #leaveFilterForm {
          display: grid !important;
          grid-template-columns: 1fr auto !important;
          grid-template-areas: "search limit" !important;
          gap: 10px !important;
          align-items: stretch !important;
          width: 100% !important;
      }

      .hr-leaves-page .filter-group-search {
          grid-area: search !important;
          width: auto !important;
          min-width: 0 !important;
      }

      .hr-leaves-page .leave-controls-card .filter-group-search .search-box {
          width: 100% !important;
          margin-bottom: 0 !important;
      }

      .hr-leaves-page .filter-group-actions {
          display: contents !important;
      }

      .hr-leaves-page .filter-group-actions .page-size-selector {
          grid-area: limit !important;
          display: flex !important;
          width: 72px !important;
          min-width: 72px !important;
          max-width: 72px !important;
          height: 48px !important;
          border-radius: 12px !important;
          background: #ffffff !important;
          border: 1px solid #e2e8f0 !important;
          box-shadow: none !important;
          padding: 0 8px !important;
          align-items: center !important;
          justify-content: center !important;
      }

      .hr-leaves-page .filter-group-actions .page-size-selector .users-limit-select {
          width: 100% !important;
          height: 100% !important;
          background: transparent !important;
          border: 0 !important;
          text-align: center !important;
          font-weight: 800 !important;
          font-size: 14px !important;
          padding: 0 !important;
      }

      .hr-leaves-page .leave-controls-card .filter-group-search .search-input {
          width: 100% !important;
          min-width: 100% !important;
          height: auto !important;
          padding-top: 12px !important;
          padding-bottom: 12px !important;
          padding-left: 44px !important;
          border-radius: 12px !important;
          font-size: 14px !important;
          border: 1px solid #e2e8f0 !important;
          box-shadow: none !important;
      }

      .hr-leaves-page .leave-controls-card .filter-group-search .search-icon {
          position: absolute !important;
          left: 14px !important;
          top: 50% !important;
          transform: translateY(-50%) !important;
          pointer-events: none !important;
          z-index: 2 !important;
      }

      .hr-leaves-page .filter-group-actions .leave-status-select,
      .hr-leaves-page .filter-group-actions #openFilterBtn,
      .hr-leaves-page .filter-group-actions .column-visibility-wrapper {
          display: none !important;
      }

      .hr-leaves-page .user-table-scroll-wrapper {
          display: none !important;
      }

      /* Bottom nav */
      .leave-mobile-bottom-nav {
          display: flex;
          justify-content: space-evenly;
          position: fixed;
          bottom: 0;
          left: 0;
          right: 0;
          background: #fff;
          box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
          z-index: 1000;
          border-top: 1px solid #e5e7eb;
          padding: 6px 0 calc(6px + env(safe-area-inset-bottom, 0px));
      }

      .leave-mobile-nav-btn {
          flex: 1;
          display: flex;
          flex-direction: column;
          align-items: center;
          justify-content: center;
          background: transparent;
          border: none;
          font-size: 12px;
          font-weight: 600;
          gap: 3px;
          color: #64748b;
          padding: 4px 2px;
          border-radius: 16px;
      }

      .leave-mobile-nav-btn i { font-size: 18px; }
      .leave-mobile-filter-btn { color: #ffa600; }
      .leave-mobile-status-btn { color: #2563eb; }
      .leave-mobile-columns-btn { color: #03ac47; }
      .leave-mobile-nav-btn.active { background: rgba(15, 23, 42, 0.06); }

      /* Column dropdown — bottom right, no blue stripe */
      #columnDropdown.leave-mobile-column-dropdown,
      #columnDropdown.leave-mobile-column-dropdown.show {
          position: fixed !important;
          left: auto !important;
          right: 12px !important;
          top: auto !important;
          bottom: calc(72px + env(safe-area-inset-bottom, 0px)) !important;
          transform: none !important;
          width: min(240px, calc(100vw - 24px)) !important;
          max-height: 50vh;
          overflow-y: auto;
          z-index: 1001 !important;
          border: 1px solid #e2e8f0 !important;
          border-right: 1px solid #e2e8f0 !important;
          border-radius: 12px !important;
          box-shadow: 0 10px 25px rgba(15, 23, 42, 0.12) !important;
          display: none;
      }

      #columnDropdown.leave-mobile-column-dropdown.show {
          display: block !important;
      }

      /* Status sheet */
      .leave-mobile-status-sheet {
          position: fixed;
          inset: 0;
          z-index: 1002;
          display: none;
          align-items: flex-end;
      }

      .leave-mobile-status-sheet.active { display: flex; }

      .leave-mobile-status-backdrop {
          position: absolute;
          inset: 0;
          background: rgba(15, 23, 42, 0.45);
      }

      .leave-mobile-status-panel {
          position: relative;
          width: 100%;
          background: #fff;
          border-radius: 20px 20px 0 0;
          padding: 16px 16px calc(16px + env(safe-area-inset-bottom, 0px));
      }

      .leave-mobile-status-panel h6 {
          margin: 0 0 12px;
          font-size: 0.95rem;
          font-weight: 800;
      }

      .leave-mobile-status-options {
          display: flex;
          flex-direction: column;
          gap: 8px;
      }

      .leave-mobile-status-option {
          width: 100%;
          border: 1px solid #e2e8f0;
          background: #f8fafc;
          border-radius: 12px;
          padding: 12px 14px;
          text-align: left;
          font-size: 0.92rem;
          font-weight: 600;
          color: #334155;
      }

      .leave-mobile-status-option.selected {
          border-color: #2563eb;
          background: rgba(37, 99, 235, 0.08);
          color: #1d4ed8;
      }

      /* Filter modal footer */
      #filterModal.custom-show .users-filter-footer {
          display: grid !important;
          grid-template-columns: 1fr 1fr;
          gap: 8px !important;
          padding: 12px 14px calc(12px + env(safe-area-inset-bottom, 0px)) !important;
      }

      #filterModal.custom-show .users-filter-footer .btn-users-close,
      #filterModal.custom-show .users-filter-footer .btn-users-clear,
      #filterModal.custom-show .users-filter-footer .btn-users-apply {
          min-height: 40px;
          padding: 8px 10px !important;
          font-size: 0.78rem !important;
          font-weight: 600 !important;
          border-radius: 10px !important;
          margin: 0 !important;
          width: 100%;
      }

      #filterModal.custom-show .users-filter-footer .btn-users-apply { order: 1; }
      #filterModal.custom-show .users-filter-footer .btn-users-close { order: 2; }
      #filterModal.custom-show .users-filter-footer .btn-users-clear {
          order: 3;
          grid-column: 1 / -1;
      }

      .hr-leaves-page .pagination-section {
          padding-bottom: 0.5rem !important;
      }

      /* Sleek leave cards */
      .mobile-leaves-container { padding: 0; margin-top: 0; }

      .mobile-leaves-list {
          display: flex;
          flex-direction: column;
          gap: 8px;
      }

      .mobile-leave-card {
          background: #ffffff;
          border-radius: 12px;
          border: 1px solid #eef2f7;
          box-shadow: 0 1px 6px rgba(15, 23, 42, 0.05);
          overflow: hidden;
      }

      .mobile-leave-card.expanded {
          box-shadow: 0 4px 14px rgba(15, 23, 42, 0.08);
      }

      .leave-card-main {
          display: flex;
          align-items: center;
          gap: 10px;
          padding: 10px 12px;
          min-height: 52px;
      }

      .leave-card-info {
          flex: 1;
          min-width: 0;
          display: flex;
          flex-direction: column;
          gap: 3px;
      }

      .leave-card-meta-line {
          display: flex;
          align-items: center;
          gap: 6px;
          min-width: 0;
      }

      .leave-card-id {
          font-size: 0.72rem;
          font-weight: 700;
          color: #94a3b8;
          flex-shrink: 0;
      }

      .leave-card-date-dot { color: #cbd5e1; font-size: 0.7rem; }

      .leave-card-date {
          font-size: 0.72rem;
          font-weight: 600;
          color: #64748b;
          white-space: nowrap;
          overflow: hidden;
          text-overflow: ellipsis;
      }

      .leave-card-name-line {
          display: flex;
          align-items: center;
          flex-wrap: nowrap;
          gap: 8px;
          min-width: 0;
      }

      .leave-card-name {
          font-size: 0.92rem;
          font-weight: 700;
          color: #0f172a;
          overflow: hidden;
          text-overflow: ellipsis;
          white-space: nowrap;
      }

      .mobile-leave-card .status-badge {
          padding: 3px 8px !important;
          border-radius: 999px !important;
          font-size: 0.62rem !important;
          flex-shrink: 0;
      }

      .leave-card-expand-btn {
          width: 32px;
          height: 32px;
          min-width: 32px;
          border-radius: 50%;
          background: #1e1e2d;
          color: #fff;
          border: none;
          display: inline-flex;
          align-items: center;
          justify-content: center;
          flex-shrink: 0;
      }

      .leave-card-expand-btn.active { background: #227477; }

      .leave-card-detail-panel {
          display: none;
          border-top: 1px solid #f1f5f9;
          padding: 10px 12px 12px;
          background: #f8fafc;
      }

      .leave-mobile-detail-grid {
          display: grid;
          grid-template-columns: 1fr 1fr;
          gap: 8px 12px;
          margin-bottom: 10px;
      }

      .leave-mobile-detail-item.leave-detail-full { grid-column: 1 / -1; }

      .leave-mobile-detail-item[data-col="9"] { display: none !important; }

      .leave-mobile-detail-label {
          font-size: 0.65rem;
          font-weight: 700;
          text-transform: uppercase;
          color: #94a3b8;
      }

      .leave-mobile-detail-value {
          font-size: 0.82rem;
          font-weight: 600;
          color: #334155;
          word-break: break-word;
      }

      .leave-card-actions {
          display: flex;
          gap: 8px;
      }

      .leave-card-action-btn {
          flex: 1;
          text-align: center;
          padding: 10px;
          border-radius: 10px;
          font-size: 0.85rem;
          font-weight: 700;
          text-decoration: none;
      }

      .leave-btn-approve { background: #2e7d32; color: #fff !important; }
      .leave-btn-reject { background: #c62828; color: #fff !important; }

      body.dark-mode .mobile-leave-card {
          background: rgba(18, 18, 18, 0.85);
          border-color: rgba(255, 255, 255, 0.1);
      }

      body.dark-mode .leave-card-name { color: #f8fafc; }
      body.dark-mode .leave-card-detail-panel { background: rgba(255, 255, 255, 0.03); }
      body.dark-mode .leave-mobile-detail-value { color: #e2e8f0; }
      body.dark-mode .leave-mobile-bottom-nav {
          background: rgba(22, 22, 24, 0.92);
          border-top-color: rgba(255, 255, 255, 0.08);
      }

      body.dark-mode .leave-mobile-status-panel { background: #1e1e24; }

      body.dark-mode .hr-leaves-page .leave-controls-card .filter-group-search .search-input {
          background: rgba(255, 255, 255, 0.06) !important;
          border: none !important;
          border-color: transparent !important;
          outline: none !important;
          box-shadow: none !important;
          color: #fff !important;
      }

      body.dark-mode .hr-leaves-page .filter-group-actions .page-size-selector {
          background: rgba(255, 255, 255, 0.06) !important;
          border-color: rgba(255, 255, 255, 0.18) !important;
      }
  }

  @media (min-width: 769px) {
      .leave-mobile-bottom-nav,
      .leave-mobile-status-sheet {
          display: none !important;
      }

      /* Tighter vertical spacing: header → summary → toolbar → table */
      .hr-leaves-page .container-fluid {
          padding-top: 6px !important;
      }

      .hr-leaves-page .summary-wrapper,
      .hr-leaves-page .summary-wrapper.pt-1,
      .hr-leaves-page .summary-wrapper.mb-2 {
          padding-top: 0 !important;
          margin-bottom: 8px !important;
      }

      .hr-leaves-page .summary-section {
          padding: 4px 5px !important;
      }

      .hr-leaves-page .leave-controls-card.glass-card,
      .hr-leaves-page .leave-controls-card {
          padding: 0 !important;
          margin-top: 0 !important;
          margin-bottom: 8px !important;
          background: transparent !important;
          border: none !important;
          box-shadow: none !important;
      }

      .hr-leaves-page .leave-controls-card .control-bar {
          margin-top: 0 !important;
          margin-bottom: 0 !important;
      }

      .hr-leaves-page .leave-controls-card #leaveFilterForm {
          margin-bottom: 0 !important;
      }

      .hr-leaves-page .user-table-container {
          margin-top: 0 !important;
          padding-top: 0 !important;
      }

      .hr-leaves-page .user-data-table {
          border-spacing: 0 10px !important;
      }
  }
</style>
<link rel="stylesheet" href="./assets/css/mobile_list_top_chrome.css?v=<?php echo time(); ?>" />
<?php include('header.php'); ?>
<div class="content hr-leaves-page">
    <div class="container-fluid">
        <div class="summary-wrapper pt-1 mb-2">
            <button type="button" class="summary-arrow left" id="summaryLeft" aria-label="Scroll summary left">‹</button>
            <div class="summary-section" id="summaryScroll">
                <div class="summary-card stat-card-late">
                    <span class="summary-text" style="font-weight: 600;">Pending : <?php echo $leave_stats['pending']; ?>
                        (<?php echo $leave_stats['pending_percent']; ?>%)</span>
                </div>
                <div class="summary-card stat-card-present">
                    <span class="summary-text" style="font-weight: 600;">Approved : <?php echo $leave_stats['approved']; ?></span>
                </div>
                <div class="summary-card stat-card-absent">
                    <span class="summary-text" style="font-weight: 600;">Rejected : <?php echo $leave_stats['rejected']; ?></span>
                </div>
            </div>
            <button type="button" class="summary-arrow right" id="summaryRight" aria-label="Scroll summary right">›</button>
        </div>

        <div class="glass-card p-4 mb-4 leave-controls-card">
            <div class="control-bar d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div class="control-left">
                    <div class="control-header-mobile">
                        <h3 class="mb-0" style="color: #227477; font-weight: 700;">Leave Requests</h3>
                        <div class="mobile-date d-md-none"><?php echo date('M d, Y'); ?></div>
                        <p class="text-muted mb-0 d-none d-md-block">Approve or reject employee leave applications</p>
                    </div>
                </div>
                
                <div class="control-right d-flex align-items-center justify-content-end gap-2 flex-grow-1 header-tools-wrapper">
                    <form action="hr_leaves.php" method="GET" id="leaveFilterForm">
                        <div class="filter-group filter-group-search">
                            <div class="search-box">
                                <svg class="search-icon" width="18" height="18" viewBox="0 0 18 18" fill="none" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); pointer-events: none; z-index: 1;">
                                    <circle cx="8" cy="8" r="6" stroke="#999" stroke-width="1.5"/>
                                    <path d="M12.5 12.5L16 16" stroke="#999" stroke-width="1.5" stroke-linecap="round"/>
                                </svg>
                                <input type="text" name="search" id="leaveSearch" class="search-input" placeholder="Search leaves..."
                                       value="<?php echo htmlspecialchars($search_query); ?>" style="padding-left: 44px;">
                            </div>
                        </div>

                        <div class="filter-group filter-group-actions">
                            <select name="status" id="quickStatus" class="form-select leave-status-select" onchange="this.form.submit()">
                                <option value="">All Status</option>
                                <option value="Pending" <?php echo $status_filter == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="Approved" <?php echo $status_filter == 'Approved' ? 'selected' : ''; ?>>Approved</option>
                                <option value="Rejected" <?php echo $status_filter == 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                            </select>

                            <button type="button" class="btn-filter" id="openFilterBtn">
                                <i class="bi bi-filter"></i> <span class="btn-text">Filters</span>
                            </button>

                            <div class="column-visibility-wrapper">
                                <button type="button" class="btn-column-visibility" id="columnVisibilityBtn">
                                    <i class="bi bi-layout-three-columns"></i> <span class="btn-text">Column Visibility</span>
                                </button>
                                <div class="column-dropdown" id="columnDropdown">
                                    <label><input type="checkbox" data-col="1"> ID</label>
                                    <label><input type="checkbox" data-col="2"> EMP ID</label>
                                    <label><input type="checkbox" data-col="3"> UNIQUE ID</label>
                                    <label><input type="checkbox" data-col="4"> EMPLOYEE</label>
                                    <label><input type="checkbox" data-col="5"> EMAIL</label>
                                    <label><input type="checkbox" data-col="6"> CONTACT</label>
                                    <label><input type="checkbox" data-col="7"> ROLE</label>
                                    <label><input type="checkbox" data-col="8"> TYPE</label>
                                    <label><input type="checkbox" data-col="9"> DATES</label>
                                    <label><input type="checkbox" data-col="10"> DAYS</label>
                                    <label><input type="checkbox" data-col="11"> REASON</label>
                                    <label><input type="checkbox" data-col="12"> STATUS</label>
                                    <label><input type="checkbox" data-col="13"> ACTIONS</label>
                                </div>
                            </div>

                            <div class="page-size-selector">
                                <select name="limit" id="leave-rows-limit" class="users-limit-select" onchange="this.form.submit()">
                                    <?php foreach ($allowedLimits as $l): ?>
                                        <option value="<?php echo $l; ?>" <?php echo $recordsPerPage == $l ? 'selected' : ''; ?>><?php echo $l; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php echo $message; ?>
        <div class="user-table-container">
            <div class="user-table-scroll-wrapper d-none d-md-block">
                <table class="user-data-table unified-table">
                    <thead>
                        <tr>
                            <th class="ps-4">ID</th>
                            <th>EMP ID</th>
                            <th>UNIQUE ID</th>
                            <th>EMPLOYEE</th>
                            <th>EMAIL</th>
                            <th>CONTACT</th>
                            <th>ROLE</th>
                            <th>TYPE</th>
                            <th>DATES</th>
                            <th>DAYS</th>
                            <th>REASON</th>
                            <th>STATUS</th>
                            <th class="pe-4 text-center">ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($leave_requests) > 0): ?>
                            <?php foreach ($leave_requests as $row): ?>
                                <?php
                                    $days = (int) ($row['leave_days'] ?? 1);
                                    $status_class = leave_status_badge_class($row['status']);
                                ?>
                                <tr class="user-data-row">
                                    <td class="ps-4 text-center fw-bold" style="color: #227477;"><?php echo $row['account_id']; ?></td>
                                    <td class="fw-bold" style="color: #227477;"><?php echo htmlspecialchars($row['employee_id'] ?? ''); ?></td>
                                    <td class="small text-muted"><?php echo htmlspecialchars($row['uniqueid'] ?? ''); ?></td>
                                    <td class="fw-bold"><?php echo htmlspecialchars($row['username']); ?></td>
                                    <td class="small"><?php echo htmlspecialchars($row['useremail'] ?? ''); ?></td>
                                    <td class="small"><?php echo htmlspecialchars($row['contact'] ?? ''); ?></td>
                                    <td class="small text-muted"><?php echo htmlspecialchars($row['role'] ?? ''); ?></td>
                                    <td><?php echo $row['leave_name']; ?></td>
                                    <td>
                                        <div style="font-size: 0.9rem;"><?php echo date('d M Y', strtotime($row['start_date'])); ?></div>
                                        <div style="font-size: 0.75rem; color: #777;">to <?php echo date('d M Y', strtotime($row['end_date'])); ?></div>
                                    </td>
                                    <td><span class="fw-bold"><?php echo $days; ?></span></td>
                                    <td style="max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo htmlspecialchars($row['reason']); ?>">
                                        <?php echo htmlspecialchars($row['reason']); ?>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $status_class; ?>"><?php echo $row['status']; ?></span>
                                    </td>
                                    <td class="pe-4">
                                        <div class="actions-cell">
                                            <?php if ($row['status'] === 'Pending'): ?>
                                                <a href="hr_leaves.php?action=approve&id=<?php echo $row['id']; ?>" class="action-btn btn-approve">Approve</a>
                                                <a href="hr_leaves.php?action=reject&id=<?php echo $row['id']; ?>" class="action-btn btn-reject">Reject</a>
                                            <?php else: ?>
                                                <span class="text-muted small">-</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr class="leave-empty-row">
                                <td colspan="13" class="text-center py-5 text-muted leave-single-cell">No leave requests found.</td>
                            </tr>
                        <?php endif; ?>
                </tbody>
                </table>
            </div>

            <!-- Mobile: sleek cards (matches attendance report) -->
            <div class="mobile-leaves-container d-block d-md-none">
                <?php if (count($leave_requests) > 0): ?>
                    <div class="mobile-leaves-list">
                        <?php foreach ($leave_requests as $row): ?>
                            <?php
                                $days = (int) ($row['leave_days'] ?? 1);
                                $status_class = leave_status_badge_class($row['status']);
                                $date_label = date('d M Y', strtotime($row['start_date']));
                                if ($row['start_date'] !== $row['end_date']) {
                                    $date_label = date('d M', strtotime($row['start_date'])) . ' – ' . date('d M Y', strtotime($row['end_date']));
                                }
                            ?>
                            <div class="mobile-leave-card" data-leave-id="<?php echo (int) $row['id']; ?>">
                                <div class="leave-card-main">
                                    <div class="leave-card-info">
                                        <div class="leave-card-meta-line">
                                            <span class="leave-card-id">#<?php echo (int) $row['account_id']; ?></span>
                                            <span class="leave-card-date-dot">·</span>
                                            <span class="leave-card-date"><?php echo $date_label; ?></span>
                                        </div>
                                        <div class="leave-card-name-line">
                                            <span class="leave-card-name"><?php echo htmlspecialchars($row['username']); ?></span>
                                            <span class="status-badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($row['status']); ?></span>
                                        </div>
                                    </div>
                                    <button type="button" class="leave-card-expand-btn" aria-expanded="false"
                                        onclick="toggleMobileLeaveCard(this)" title="Show details">
                                        <i class="bi bi-chevron-down"></i>
                                    </button>
                                </div>
                                <div class="leave-card-detail-panel">
                                    <div class="leave-mobile-detail-grid">
                                        <div class="leave-mobile-detail-item" data-col="2">
                                            <span class="leave-mobile-detail-label">Emp ID</span>
                                            <span class="leave-mobile-detail-value"><?php echo htmlspecialchars($row['employee_id'] ?? '—'); ?></span>
                                        </div>
                                        <div class="leave-mobile-detail-item" data-col="3">
                                            <span class="leave-mobile-detail-label">Unique ID</span>
                                            <span class="leave-mobile-detail-value"><?php echo htmlspecialchars($row['uniqueid'] ?? '—'); ?></span>
                                        </div>
                                        <div class="leave-mobile-detail-item" data-col="5">
                                            <span class="leave-mobile-detail-label">Email</span>
                                            <span class="leave-mobile-detail-value"><?php echo htmlspecialchars($row['useremail'] ?? '—'); ?></span>
                                        </div>
                                        <div class="leave-mobile-detail-item" data-col="6">
                                            <span class="leave-mobile-detail-label">Contact</span>
                                            <span class="leave-mobile-detail-value"><?php echo htmlspecialchars($row['contact'] ?? '—'); ?></span>
                                        </div>
                                        <div class="leave-mobile-detail-item" data-col="7">
                                            <span class="leave-mobile-detail-label">Role</span>
                                            <span class="leave-mobile-detail-value"><?php echo htmlspecialchars($row['role'] ?? '—'); ?></span>
                                        </div>
                                        <div class="leave-mobile-detail-item" data-col="8">
                                            <span class="leave-mobile-detail-label">Leave Type</span>
                                            <span class="leave-mobile-detail-value"><?php echo htmlspecialchars($row['leave_name']); ?></span>
                                        </div>
                                        <div class="leave-mobile-detail-item" data-col="9">
                                            <span class="leave-mobile-detail-label">Dates</span>
                                            <span class="leave-mobile-detail-value"><?php echo date('d M Y', strtotime($row['start_date'])); ?> – <?php echo date('d M Y', strtotime($row['end_date'])); ?></span>
                                        </div>
                                        <div class="leave-mobile-detail-item" data-col="10">
                                            <span class="leave-mobile-detail-label">Duration</span>
                                            <span class="leave-mobile-detail-value"><?php echo $days; ?> <?php echo $days > 1 ? 'Days' : 'Day'; ?></span>
                                        </div>
                                        <div class="leave-mobile-detail-item leave-detail-full" data-col="11">
                                            <span class="leave-mobile-detail-label">Reason</span>
                                            <span class="leave-mobile-detail-value"><?php echo htmlspecialchars($row['reason']); ?></span>
                                        </div>
                                    </div>
                                    <?php if ($row['status'] === 'Pending'): ?>
                                        <div class="leave-card-actions" data-col-toggle="13">
                                            <a href="hr_leaves.php?action=approve&id=<?php echo $row['id']; ?>"
                                               onclick="return confirmAction('Approve', '<?php echo htmlspecialchars($row['username'], ENT_QUOTES); ?>');"
                                               class="leave-card-action-btn leave-btn-approve">Approve</a>
                                            <a href="hr_leaves.php?action=reject&id=<?php echo $row['id']; ?>"
                                               onclick="return confirmAction('Reject', '<?php echo htmlspecialchars($row['username'], ENT_QUOTES); ?>');"
                                               class="leave-card-action-btn leave-btn-reject">Reject</a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="mobile-leave-card text-center py-5 text-muted">
                        <i class="bi bi-inbox fs-1 mb-2 d-block"></i>
                        No leave requests found.
                    </div>
                <?php endif; ?>
            </div>

            <!-- Pagination Footer (Centered Stacked Style) -->
            <div class="pagination-section py-4">
                <div class="d-flex flex-column align-items-center gap-3">
                    <div class="pagination-info text-muted" style="font-weight: 500; font-size: 0.95rem;">
                        Showing <?php echo $showingStart; ?> to <?php echo $showingEnd; ?> of <?php echo $totalEntries; ?> entries
                    </div>
                    
                    <div class="pagination-controls d-flex gap-2">
                        <a href="hr_leaves.php?page=<?php echo max(1, $currentPage - 1); ?>&from=<?php echo $from_date; ?>&to=<?php echo $to_date; ?>&search=<?php echo urlencode($search_query); ?>&status=<?php echo urlencode($status_filter); ?>&limit=<?php echo $recordsPerPage; ?>" 
                           class="page-btn <?php echo $currentPage <= 1 ? 'disabled' : ''; ?>" style="text-decoration: none;">&larr;</a>
                        
                        <?php 
                        $startPage = max(1, $currentPage - 2);
                        $endPage = min($totalPages, $currentPage + 2);
                        for ($i = $startPage; $i <= $endPage; $i++): 
                        ?>
                            <a href="hr_leaves.php?page=<?php echo $i; ?>&from=<?php echo $from_date; ?>&to=<?php echo $to_date; ?>&search=<?php echo urlencode($search_query); ?>&status=<?php echo urlencode($status_filter); ?>&limit=<?php echo $recordsPerPage; ?>" 
                               class="page-btn <?php echo $currentPage == $i ? 'active' : ''; ?>" style="text-decoration: none;"><?php echo $i; ?></a>
                        <?php endfor; ?>
                        
                        <a href="hr_leaves.php?page=<?php echo min($totalPages, $currentPage + 1); ?>&from=<?php echo $from_date; ?>&to=<?php echo $to_date; ?>&search=<?php echo urlencode($search_query); ?>&status=<?php echo urlencode($status_filter); ?>&limit=<?php echo $recordsPerPage; ?>" 
                           class="page-btn <?php echo $currentPage >= $totalPages ? 'disabled' : ''; ?>" style="text-decoration: none;">&rarr;</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Mobile bottom toolbar -->
<nav class="leave-mobile-bottom-nav d-md-none" aria-label="Leave actions">
    <button type="button" class="leave-mobile-nav-btn leave-mobile-filter-btn" id="leaveMobileFilterBtn">
        <i class="bi bi-funnel-fill"></i>
        <span>Filter</span>
    </button>
    <button type="button" class="leave-mobile-nav-btn leave-mobile-status-btn<?php echo $status_filter ? ' active' : ''; ?>" id="leaveMobileStatusBtn">
        <i class="bi bi-ui-checks-grid"></i>
        <span>Status</span>
    </button>
    <button type="button" class="leave-mobile-nav-btn leave-mobile-columns-btn" id="leaveMobileColumnsBtn">
        <i class="bi bi-layout-three-columns"></i>
        <span>Columns</span>
    </button>
</nav>

<!-- Mobile status picker -->
<div id="leaveMobileStatusSheet" class="leave-mobile-status-sheet d-md-none" aria-hidden="true">
    <div class="leave-mobile-status-backdrop" id="leaveMobileStatusBackdrop"></div>
    <div class="leave-mobile-status-panel" role="dialog" aria-modal="true">
        <h6>Filter by Status</h6>
        <div class="leave-mobile-status-options">
            <button type="button" class="leave-mobile-status-option" data-status="">All Status</button>
            <button type="button" class="leave-mobile-status-option" data-status="Pending">Pending</button>
            <button type="button" class="leave-mobile-status-option" data-status="Approved">Approved</button>
            <button type="button" class="leave-mobile-status-option" data-status="Rejected">Rejected</button>
        </div>
    </div>
</div>

<!-- Add Filter Modal -->
<?php include 'filter_modal_leaves.php'; ?>

<script src="assets/js/summary-cards.js?v=<?php echo time(); ?>"></script>
<script>
function toggleMobileLeaveCard(btn) {
    const card = btn.closest('.mobile-leave-card');
    const panel = card?.querySelector('.leave-card-detail-panel');
    const icon = btn.querySelector('i');
    if (!card || !panel) return;

    const isOpen = card.classList.contains('expanded');
    document.querySelectorAll('.mobile-leave-card.expanded').forEach(c => {
        if (c === card) return;
        c.classList.remove('expanded');
        const p = c.querySelector('.leave-card-detail-panel');
        if (p) p.style.display = 'none';
        const b = c.querySelector('.leave-card-expand-btn');
        if (b) {
            b.classList.remove('active');
            b.setAttribute('aria-expanded', 'false');
            const i = b.querySelector('i');
            if (i) i.className = 'bi bi-chevron-down';
        }
    });

    if (isOpen) {
        card.classList.remove('expanded');
        panel.style.display = 'none';
        btn.classList.remove('active');
        btn.setAttribute('aria-expanded', 'false');
        if (icon) icon.className = 'bi bi-chevron-down';
    } else {
        card.classList.add('expanded');
        panel.style.display = 'block';
        btn.classList.add('active');
        btn.setAttribute('aria-expanded', 'true');
        if (icon) icon.className = 'bi bi-chevron-up';
    }
}

$(document).ready(function() {
    const root = document.querySelector('.hr-leaves-page');

    /** After hiding columns via display:none, the visible left/right cells are no longer :first/:last —
     * unified_table_styles also clears radii ≤1024px. Apply edge classes so row “cards” match Employee page. */
    function syncLeaveTableCorners() {
        if (!root) return;
        const tableEl = root.querySelector('.user-data-table');
        if (!tableEl) return;

        function wipeAndCorners(cells) {
            [...cells].forEach(function (cell) {
                cell.classList.remove('leave-edge-left', 'leave-edge-right', 'leave-single-cell');
            });
            const list = [...cells].filter(function (c) {
                return window.getComputedStyle(c).display !== 'none';
            });
            if (list.length === 0) return;
            if (list.length === 1) {
                list[0].classList.add('leave-single-cell');
                return;
            }
            list[0].classList.add('leave-edge-left');
            list[list.length - 1].classList.add('leave-edge-right');
        }

        tableEl.querySelectorAll('thead tr th').forEach(function (th) {
            th.classList.remove('leave-edge-left', 'leave-edge-right', 'leave-single-cell');
            th.style.borderRadius = '0';
        });
        tableEl.querySelectorAll('tbody tr').forEach(function (row) {
            wipeAndCorners(row.querySelectorAll('td'));
        });
    }

    // --- Column visibility ---
    const dropdown = document.getElementById("columnDropdown");
    const table = root ? root.querySelector(".user-data-table") : null;
    const columnBtn = document.getElementById("columnVisibilityBtn");
    const leaveMobileColumnsBtn = document.getElementById("leaveMobileColumnsBtn");
    const MOBILE_ROW_COLS = new Set([1, 4, 9, 12]);

    function portalLeaveColumnDropdown() {
        if (window.innerWidth > 768 || !dropdown || dropdown.dataset.portaled === '1') return;
        document.body.appendChild(dropdown);
        dropdown.classList.add('leave-mobile-column-dropdown');
        dropdown.dataset.portaled = '1';
    }

    function syncMobileLeaveDetailColumns() {
        if (window.innerWidth > 768 || !dropdown) return;
        const checkboxes = dropdown.querySelectorAll("input[type='checkbox']");
        checkboxes.forEach(cb => {
            if (MOBILE_ROW_COLS.has(parseInt(cb.dataset.col, 10))) cb.checked = true;
        });
        checkboxes.forEach(cb => {
            const col = parseInt(cb.dataset.col, 10);
            const show = cb.checked;
            document.querySelectorAll(`.leave-card-detail-panel .leave-mobile-detail-item[data-col="${col}"]`).forEach(el => {
                el.style.display = show ? '' : 'none';
            });
            document.querySelectorAll(`.leave-card-actions[data-col-toggle="${col}"]`).forEach(el => {
                el.style.display = show ? '' : 'none';
            });
        });
    }

    function toggleLeaveColumnDropdown(forceClose = false) {
        portalLeaveColumnDropdown();
        if (!dropdown) return;
        if (forceClose) {
            dropdown.classList.remove('show');
            leaveMobileColumnsBtn?.classList.remove('active');
            return;
        }
        dropdown.classList.toggle('show');
        if (dropdown.classList.contains('show')) {
            dropdown.style.setProperty('top', 'auto', 'important');
            dropdown.style.setProperty('bottom', 'calc(72px + env(safe-area-inset-bottom, 0px))', 'important');
            dropdown.style.setProperty('left', 'auto', 'important');
            dropdown.style.setProperty('right', '12px', 'important');
            dropdown.style.setProperty('transform', 'none', 'important');
        }
        leaveMobileColumnsBtn?.classList.toggle('active', dropdown.classList.contains('show'));
    }

    if (dropdown && table) {
        const checkboxes = dropdown.querySelectorAll("input[type='checkbox']");

        if (columnBtn) {
            columnBtn.addEventListener("click", function (e) {
                e.stopPropagation();
                if (window.innerWidth <= 768) {
                    toggleLeaveColumnDropdown();
                } else {
                    dropdown.classList.toggle("show");
                }
            });
        }

        document.addEventListener("click", function (e) {
            if (e.target.closest('#columnDropdown') || e.target.closest('#leaveMobileColumnsBtn') || e.target.closest('#columnVisibilityBtn')) {
                return;
            }
            dropdown.classList.remove("show");
            leaveMobileColumnsBtn?.classList.remove('active');
        });
        dropdown.addEventListener("click", function (e) {
            e.stopPropagation();
        });

        function toggleColumn(index, show) {
            if (window.innerWidth <= 768) {
                syncMobileLeaveDetailColumns();
                return;
            }
            table.querySelectorAll(`thead tr th:nth-child(${index}), tbody tr td:nth-child(${index})`).forEach(cell => {
                cell.style.display = show ? "table-cell" : "none";
            });
        }

        function saveLeavePrefs() {
            const prefs = Array.from(checkboxes).map(cb => ({
                col: cb.dataset.col,
                checked: cb.checked
            }));
            localStorage.setItem('leave_report_columns', JSON.stringify(prefs));
        }

        function restoreLeavePrefs() {
            const saved = localStorage.getItem('leave_report_columns');
            if (!saved) return false;
            const prefs = JSON.parse(saved);
            prefs.forEach(p => {
                const cb = dropdown.querySelector(`input[data-col='${p.col}']`);
                if (cb) {
                    cb.checked = p.checked;
                    toggleColumn(parseInt(p.col, 10), p.checked);
                }
            });
            syncLeaveTableCorners();
            return true;
        }

        checkboxes.forEach(cb => {
            cb.addEventListener("change", function () {
                toggleColumn(parseInt(this.dataset.col, 10), this.checked);
                if (window.innerWidth <= 768) syncMobileLeaveDetailColumns();
                saveLeavePrefs();
                syncLeaveTableCorners();
            });
        });

        function applyLeaveDefaults() {
            const width = window.innerWidth;
            if (width <= 768) {
                if (restoreLeavePrefs()) {
                    syncMobileLeaveDetailColumns();
                    return;
                }
                const defaultMobileDetailCols = [2, 3, 8, 10, 11, 13];
                checkboxes.forEach(cb => {
                    const col = parseInt(cb.dataset.col, 10);
                    cb.checked = MOBILE_ROW_COLS.has(col) || defaultMobileDetailCols.includes(col);
                });
                syncMobileLeaveDetailColumns();
                return;
            }
            if (restoreLeavePrefs()) {
                table.style.width = "max-content";
                return;
            }
            let defaultCols = [1, 2, 4, 8, 9, 10, 12, 13];
            if (width >= 1440) defaultCols = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13];
            else if (width > 1024) defaultCols = [1, 2, 4, 5, 8, 9, 10, 12, 13];
            else if (width > 768) defaultCols = [2, 4, 8, 10, 12, 13];

            checkboxes.forEach(cb => {
                const col = parseInt(cb.dataset.col, 10);
                const shouldShow = defaultCols.includes(col);
                cb.checked = shouldShow;
                toggleColumn(col, shouldShow);
            });
            table.style.width = "max-content";
            syncLeaveTableCorners();
        }

        let resizeTimer;
        window.addEventListener("resize", function () {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function () {
                applyLeaveDefaults();
                syncLeaveTableCorners();
            }, 180);
        });

        applyLeaveDefaults();

        /* Mobile bottom nav */
        const leaveMobileFilterBtn = document.getElementById('leaveMobileFilterBtn');
        const leaveMobileStatusBtn = document.getElementById('leaveMobileStatusBtn');
        const leaveMobileStatusSheet = document.getElementById('leaveMobileStatusSheet');
        const leaveMobileStatusBackdrop = document.getElementById('leaveMobileStatusBackdrop');
        const quickStatus = document.getElementById('quickStatus');

        function closeLeaveMobileStatusSheet() {
            leaveMobileStatusSheet?.classList.remove('active');
            leaveMobileStatusSheet?.setAttribute('aria-hidden', 'true');
            leaveMobileStatusBtn?.classList.remove('active');
        }

        function openLeaveMobileStatusSheet() {
            if (!leaveMobileStatusSheet || !quickStatus) return;
            leaveMobileStatusSheet.querySelectorAll('.leave-mobile-status-option').forEach(opt => {
                opt.classList.toggle('selected', opt.dataset.status === quickStatus.value);
            });
            leaveMobileStatusSheet.classList.add('active');
            leaveMobileStatusSheet.setAttribute('aria-hidden', 'false');
            leaveMobileStatusBtn?.classList.add('active');
        }

        leaveMobileFilterBtn?.addEventListener('click', () => {
            document.getElementById('openFilterBtn')?.click();
        });
        leaveMobileStatusBtn?.addEventListener('click', () => {
            if (leaveMobileStatusSheet?.classList.contains('active')) {
                closeLeaveMobileStatusSheet();
            } else {
                toggleLeaveColumnDropdown(true);
                openLeaveMobileStatusSheet();
            }
        });
        leaveMobileColumnsBtn?.addEventListener('click', (e) => {
            e.stopPropagation();
            closeLeaveMobileStatusSheet();
            toggleLeaveColumnDropdown();
        });
        leaveMobileStatusBackdrop?.addEventListener('click', closeLeaveMobileStatusSheet);
        leaveMobileStatusSheet?.querySelectorAll('.leave-mobile-status-option').forEach(opt => {
            opt.addEventListener('click', () => {
                if (!quickStatus) return;
                quickStatus.value = opt.dataset.status;
                closeLeaveMobileStatusSheet();
                quickStatus.form.submit();
            });
        });
    } else if (table) {
        syncLeaveTableCorners();
    }

    document.querySelectorAll('.mobile-leave-card').forEach(card => {
        card.addEventListener('click', function (e) {
            if (window.innerWidth > 768) return;
            if (e.target.closest('.leave-card-detail-panel') || e.target.closest('.leave-card-action-btn') || e.target.closest('.leave-card-expand-btn')) return;
            const btn = card.querySelector('.leave-card-expand-btn');
            if (btn) btn.click();
        });
    });

    // --- Filter Modal Logic ---
    const filterModal = document.getElementById('filterModal');
    const openFilterBtn = document.getElementById('openFilterBtn');
    const closeFilterBtn = document.getElementById('closeFilter');
    const closeFilterFooter = document.getElementById('closeFilterFooter');
    const applyFiltersBtn = document.getElementById('applyFiltersBtn');
    const clearFiltersBtn = document.getElementById('clearFiltersBtn');

    function openModal() { if (filterModal) filterModal.classList.add('custom-show'); }
    function closeModal() { if (filterModal) filterModal.classList.remove('custom-show'); }

    if (openFilterBtn) openFilterBtn.addEventListener('click', openModal);
    if (closeFilterBtn) closeFilterBtn.addEventListener('click', closeModal);
    if (closeFilterFooter) closeFilterFooter.addEventListener('click', closeModal);
    
    // Close on backdrop click
    if (filterModal) {
        filterModal.addEventListener('click', function(e) {
            if (e.target === filterModal) closeModal();
        });
    }

    if (applyFiltersBtn) {
        applyFiltersBtn.addEventListener('click', function() {
            const fromDate = document.getElementById('filterFromDate').value;
            const toDate = document.getElementById('filterToDate').value;
            const status = document.getElementById('filterStatus').value;
            
            // Construct new URL
            const url = new URL(window.location.href);
            if (fromDate) url.searchParams.set('from', fromDate);
            else url.searchParams.delete('from');
            if (toDate) url.searchParams.set('to', toDate);
            else url.searchParams.delete('to');
            if (status) url.searchParams.set('status', status);
            else url.searchParams.delete('status');
            
            url.searchParams.set('page', 1); // Reset to first page
            window.location.href = url.href;
        });
    }

    if (clearFiltersBtn) {
        clearFiltersBtn.addEventListener('click', function() {
            window.location.href = 'hr_leaves.php';
        });
    }

    // Setup summary cards as filters
    const cardPending = document.querySelector('.summary-section .stat-card-late');
    const cardApproved = document.querySelector('.summary-section .stat-card-present');
    const cardRejected = document.querySelector('.summary-section .stat-card-absent');
    const statusSelect = document.getElementById('quickStatus');
    
    if (statusSelect) {
        function highlightLeaveSummaryCard() {
            [cardPending, cardApproved, cardRejected].forEach(c => c?.classList.remove('active-filter'));
            if (statusSelect.value === 'Pending') {
                cardPending?.classList.add('active-filter');
            } else if (statusSelect.value === 'Approved') {
                cardApproved?.classList.add('active-filter');
            } else if (statusSelect.value === 'Rejected') {
                cardRejected?.classList.add('active-filter');
            }
        }

        highlightLeaveSummaryCard();

        cardPending?.addEventListener('click', function () {
            statusSelect.value = (statusSelect.value === 'Pending') ? '' : 'Pending';
            statusSelect.form.submit();
        });
        cardApproved?.addEventListener('click', function () {
            statusSelect.value = (statusSelect.value === 'Approved') ? '' : 'Approved';
            statusSelect.form.submit();
        });
        cardRejected?.addEventListener('click', function () {
            statusSelect.value = (statusSelect.value === 'Rejected') ? '' : 'Rejected';
            statusSelect.form.submit();
        });
    }
});

function confirmAction(type, name) {
    return confirm(`Are you sure you want to ${type.toLowerCase()} the leave request for ${name}?`);
}
</script>
<?php include 'htmlclose.php'; ?>