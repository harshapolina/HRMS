<?php
$skip_superadmin_css = true;

// Session and redirects MUST run before htmlopen.php (it sends HTML output)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['loggedin'])) {
    header('Location: /');
    exit;
}
$allowed_roles = ['hradminuser'];
$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : '';
if (!in_array($user_role, $allowed_roles)) {
    header('Location: access_denied.html');
    exit;
}
$nameuser = $_SESSION['username'];
if (!isset($_SESSION['tablename']) && !empty($_SESSION['username'])) {
    $_SESSION['tablename'] = $_SESSION['username'];
}

include('htmlopen.php');

require_once __DIR__ . '/includes/db_mysqli.php';
try {
    $conn = hr_mysqli_connect();
} catch (Throwable $e) {
    die('Connection failed: ' . $e->getMessage());
}
$sql = "SELECT username, tablename, user_type FROM accounts WHERE user_type IN ('promoter', 'business head', 'manager', 'team lead') AND is_active = 1";
$result = $conn->query($sql);
$assignUsers = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
      $assignUsers[] = $row;
    }
}

// Fetch all unique user_types for the designation dropdown
$existingRoles = [];
$role_sql = "SELECT DISTINCT user_type FROM accounts WHERE user_type IS NOT NULL AND user_type != '' ORDER BY user_type ASC";
$role_result = $conn->query($role_sql);
if ($role_result && $role_result->num_rows > 0) {
    while ($row = $role_result->fetch_assoc()) {
        $existingRoles[] = $row['user_type'];
    }
}

$conn->close();

// Release session lock while the HTML page renders (lets fetch_users.php run in parallel)
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

// Pagination Variables
$allowedLimits = [10, 50, 100, 200, 300];
$recordsPerPage = isset($_GET['limit']) && in_array((int) $_GET['limit'], $allowedLimits, true) ? (int) $_GET['limit'] : 10;
$currentPage = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
?>
<link rel="stylesheet" href="./assets/css/unified_table_styles.css?v=<?php echo time(); ?>" />
<link rel="stylesheet" href="./assets/css/Users.css?v=<?php echo time(); ?>" />
<link rel="stylesheet" href="./assets/css/style_dashboard.css?v=<?php echo time(); ?>" />
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

  .user-table-container, .user-table-scroll-wrapper, .user-data-table { background: transparent !important; border: none !important; }
  .user-data-table { border-collapse: separate !important; border-spacing: 0 10px !important; width: 100% !important; }
  
  /* Search & Filter Layout - Desktop Defaults */
  .control-bar { display: flex; justify-content: space-between; align-items: flex-end; gap: 15px; margin-bottom: 20px; }
  .control-right { display: flex; align-items: center; gap: 10px; }
  .filter-group { display: flex; align-items: center; gap: 8px; }

  .btn-new-user {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 18px;
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    color: #555;
    cursor: pointer;
    transition: all 0.3s ease;
  }
  .btn-new-user:hover {
    background: #f5f5f5;
    border-color: #bbb;
    color: #227477;
  }
  .btn-new-user i {
    color: #227477;
    font-size: 1.1rem;
  }

  body.dark-mode .btn-new-user {
    background: rgba(255, 255, 255, 0.05) !important;
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
    color: #ffffff !important;
  }
  body.dark-mode .btn-new-user:hover {
    background: rgba(255, 255, 255, 0.1) !important;
    border-color: rgba(255, 255, 255, 0.2) !important;
    color: #ffffff !important;
  }
  body.dark-mode .btn-new-user i {
    color: #2a8c90 !important;
  }

  /* Users controls â€” match Attendance Report toolbar */
  .users-controls-card {
    background: transparent !important;
    border: none !important;
    box-shadow: none !important;
    padding: 0 !important;
    margin-top: 0 !important;
  }

  .users-controls-card .control-left {
    display: none !important;
  }

  .users-controls-card .control-right,
  .users-controls-card .header-tools-wrapper {
    width: 100% !important;
    flex-grow: 1 !important;
  }

  .users-controls-card #usersToolbar {
    width: 100% !important;
    display: flex !important;
    align-items: center !important;
    justify-content: flex-start !important;
    flex-wrap: nowrap !important;
    gap: 12px !important;
  }

  .users-controls-card .filter-group {
    display: flex !important;
    align-items: center !important;
    flex-wrap: nowrap !important;
    gap: 10px !important;
  }

  .users-controls-card .filter-group-search {
    flex: 1 1 auto !important;
    min-width: 0 !important;
  }

  .users-controls-card .filter-group-actions {
    flex: 0 0 auto !important;
    margin-left: auto !important;
  }

  @media (min-width: 769px) {
    .users-controls-card .control-bar {
      align-items: center !important;
    }

    .users-controls-card #usersToolbar {
      align-items: center !important;
    }

    .users-controls-card .filter-group {
      align-items: center !important;
    }

    /* Uniform toolbar height â€” match form-select (All Status style) */
    .users-controls-card #usersToolbar .search-input,
    .users-controls-card .btn-new-user,
    .users-controls-card .btn-filter,
    .users-controls-card .btn-column-visibility,
    .users-controls-card .page-size-selector {
      height: 38px !important;
      min-height: 38px !important;
      max-height: 38px !important;
      box-sizing: border-box !important;
    }

    .users-controls-card #usersToolbar .search-input {
      padding: 0 16px 0 44px !important;
      line-height: normal !important;
      border-radius: 8px !important;
      border: 1px solid #ddd !important;
      font-size: 14px !important;
      width: 100% !important;
      min-width: 320px !important;
    }

    .users-controls-card .btn-new-user,
    .users-controls-card .btn-filter,
    .users-controls-card .btn-column-visibility {
      padding: 0 18px !important;
      display: inline-flex !important;
      align-items: center !important;
      justify-content: center !important;
      border-radius: 8px !important;
      font-size: 14px !important;
    }

    .users-controls-card .page-size-selector {
      padding: 0 10px !important;
      display: inline-flex !important;
      align-items: center !important;
      justify-content: center !important;
      border-radius: 8px !important;
      border: 1px solid #ddd !important;
      background: #ffffff !important;
      min-width: 64px !important;
      max-width: 76px !important;
    }

    .users-controls-card .page-size-selector .users-limit-select {
      height: 100% !important;
      padding: 0 4px !important;
      line-height: normal !important;
      border: 0 !important;
      background: transparent !important;
      font-size: 14px !important;
      font-weight: 500 !important;
      text-align: center !important;
    }
  }

  /* Mobile-specific adjustments (top chrome only â€” matches attendance_report.php) */
  @media (max-width: 768px) {
    .users-page.content {
      padding: 10px !important;
    }

    .users-page .summary-wrapper,
    .users-page .summary-wrapper.pt-1,
    .users-page .summary-wrapper.mb-2 {
      padding-top: 0 !important;
      padding-bottom: 0 !important;
      margin-bottom: 8px !important;
    }

    .users-page .summary-section {
      padding: 4px 5px !important;
    }

    .users-page .users-controls-card {
      padding: 0 !important;
      margin-top: 0 !important;
      margin-bottom: 8px !important;
    }

    .users-page .users-controls-card .control-bar {
      margin-top: 0 !important;
      margin-bottom: 0 !important;
    }

    .users-page .user-table-container {
      padding-top: 0 !important;
    }

    .users-page .user-data-table {
      border-spacing: 0 10px !important;
    }

    .users-page .control-bar {
      flex-direction: column !important;
      align-items: stretch !important;
      gap: 0 !important;
      margin-top: 0 !important;
      margin-bottom: 0 !important;
    }

    .users-page .control-right,
    .users-page .header-tools-wrapper {
      width: 100% !important;
    }

    .users-page #usersToolbar {
      display: grid !important;
      grid-template-columns: 1fr auto !important;
      grid-template-areas: "search limit" !important;
      gap: 10px !important;
      align-items: stretch !important;
    }

    .users-page .filter-group-search {
      grid-area: search !important;
      width: auto !important;
      min-width: 0 !important;
    }

    .users-page .filter-group-search .search-box {
      width: 100% !important;
      margin-bottom: 0 !important;
      position: relative;
    }

    .users-page .filter-group-search .search-input {
      width: 100% !important;
      min-width: 100% !important;
      padding-top: 12px !important;
      padding-bottom: 12px !important;
      padding-left: 44px !important;
      border-radius: 12px !important;
      font-size: 14px !important;
      height: auto !important;
    }

    .users-page .filter-group-search .search-icon {
      position: absolute !important;
      left: 14px !important;
      top: 50% !important;
      transform: translateY(-50%) !important;
      pointer-events: none !important;
      z-index: 2 !important;
    }

    .users-page .filter-group-actions {
      display: contents !important;
    }

    .users-page .filter-group-actions .page-size-selector {
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

    .users-page .filter-group-actions .page-size-selector #users-limit {
      width: 100% !important;
      height: 100% !important;
      background: transparent !important;
      border: 0 !important;
      text-align: center !important;
      font-weight: 800 !important;
      font-size: 14px !important;
      padding: 0 !important;
      margin: 0 !important;
    }

    .users-page .filter-group-actions .btn-new-user,
    .users-page .filter-group-actions .btn-filter,
    .users-page .filter-group-actions .column-visibility-wrapper {
      display: none !important;
    }

    .users-page .user-table-container {
      margin-top: 0 !important;
    }

    .users-page .container-fluid {
      padding-bottom: calc(72px + env(safe-area-inset-bottom, 0px)) !important;
    }

    .users-page .floating-clear-btn {
      bottom: calc(78px + env(safe-area-inset-bottom, 0px)) !important;
    }

    .users-page .column-dropdown.show,
    .users-page .column-dropdown.usr-mobile-column-dropdown.show,
    #columnDropdown.usr-mobile-column-dropdown,
    #columnDropdown.usr-mobile-column-dropdown.show {
      position: fixed !important;
      left: auto !important;
      right: 12px !important;
      top: auto !important;
      bottom: calc(72px + env(safe-area-inset-bottom, 0px)) !important;
      transform: none !important;
      width: min(260px, calc(100vw - 24px)) !important;
      max-height: 50vh;
      overflow-y: auto;
      z-index: 1001 !important;
      display: none;
      margin: 0 !important;
      border: 1px solid #e2e8f0 !important;
    }

    body.dark-mode #columnDropdown.usr-mobile-column-dropdown.show {
      border-color: rgba(255, 255, 255, 0.1) !important;
    }

    #columnDropdown.usr-mobile-column-dropdown::-webkit-scrollbar-thumb {
      background: #cbd5e1 !important;
    }

    #columnDropdown.usr-mobile-column-dropdown::-webkit-scrollbar-thumb:hover {
      background: #94a3b8 !important;
    }

    #columnDropdown.usr-mobile-column-dropdown.show {
      display: block !important;
    }

    body.dark-mode .users-page .filter-group-actions .page-size-selector {
      background: rgba(255, 255, 255, 0.05) !important;
      border: 1px solid rgba(255, 255, 255, 0.1) !important;
      box-shadow: none !important;
      overflow: hidden !important;
    }

    body.dark-mode .users-page .filter-group-actions .page-size-selector #users-limit {
      color: #ffffff !important;
      background-color: transparent !important;
      background-image: none !important;
      border: 0 !important;
      backdrop-filter: none !important;
      -webkit-appearance: menulist !important;
      appearance: menulist !important;
      font-weight: 500 !important;
    }

    /* Fixed bottom nav â€” same pattern as Attendance Report */
    .usr-mobile-bottom-nav {
      display: flex;
      justify-content: space-evenly;
      align-items: stretch;
      position: fixed;
      bottom: 0;
      left: 0;
      right: 0;
      background: #fff;
      box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
      z-index: 1000;
      gap: 4px;
      border-top: 1px solid #e5e7eb;
      padding: 6px 0 calc(6px + env(safe-area-inset-bottom, 0px));
    }

    .usr-mobile-nav-btn {
      flex: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      background: transparent;
      border: none;
      font-size: 12px;
      font-weight: 600;
      cursor: pointer;
      gap: 3px;
      color: #64748b;
      padding: 4px 2px;
      border-radius: 16px;
      transition: color 0.2s ease, background 0.2s ease;
    }

    .usr-mobile-nav-btn i {
      font-size: 18px;
      line-height: 1;
    }

    .usr-mobile-nav-btn.usr-mobile-filter-btn { color: #ffa600; }
    .usr-mobile-add-btn { color: #2563eb; }
    .usr-mobile-columns-btn { color: #03ac47; }
    .usr-mobile-nav-btn.active { background: rgba(15, 23, 42, 0.06); }

    body.dark-mode .usr-mobile-bottom-nav {
      background: rgba(22, 22, 24, 0.92);
      border-top-color: rgba(255, 255, 255, 0.08);
      box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.4);
    }

    body.dark-mode .usr-mobile-nav-btn { color: rgba(255, 255, 255, 0.65); }
    body.dark-mode .usr-mobile-nav-btn.usr-mobile-filter-btn { color: #ffb347; }
    body.dark-mode .usr-mobile-nav-btn.usr-mobile-add-btn { color: #60a5fa; }
    body.dark-mode .usr-mobile-nav-btn.usr-mobile-columns-btn { color: #34d399; }

    /* Assign Asset modal — above mobile navbar (99999) and scrollable on small screens */
    #assignAssetFromDrawerModal {
      padding-top: calc(56px + env(safe-area-inset-top, 0px)) !important;
      padding-bottom: calc(12px + env(safe-area-inset-bottom, 0px)) !important;
    }
    #assignAssetFromDrawerModal .modal-dialog {
      margin: 0.5rem auto !important;
      max-height: calc(100vh - 72px - env(safe-area-inset-top, 0px) - env(safe-area-inset-bottom, 0px)) !important;
      display: flex !important;
      align-items: stretch !important;
    }
    #assignAssetFromDrawerModal .modal-content {
      max-height: 100% !important;
      display: flex !important;
      flex-direction: column !important;
      overflow: hidden !important;
    }
    #assignAssetFromDrawerModal .modal-body {
      overflow-y: auto !important;
      -webkit-overflow-scrolling: touch;
      flex: 1 1 auto !important;
    }
    #assignAssetFromDrawerModal .form-select,
    #assignAssetFromDrawerModal .form-control {
      font-size: 16px !important;
      min-height: 48px !important;
    }
    #assignAssetFromDrawerModal .modal-footer {
      flex-shrink: 0 !important;
      display: flex !important;
      flex-direction: column-reverse !important;
      gap: 10px !important;
      padding: 12px 16px calc(12px + env(safe-area-inset-bottom, 0px)) !important;
    }
    #assignAssetFromDrawerModal .modal-footer .btn {
      width: 100% !important;
      min-height: 48px !important;
      margin: 0 !important;
    }

    /* Profile Drawer Mobile */
    .profile-drawer { width: 100vw !important; height: 100vh !important; border-radius: 0 !important; max-width: none !important; max-height: none !important; }
    .drawer-header { padding: 20px !important; }
    .drawer-header-content { gap: 12px !important; }
    .drawer-avatar { width: 50px !important; height: 50px !important; font-size: 1.5rem !important; }
    .drawer-title h3 { font-size: 1.2rem !important; }
    .drawer-tabs { padding: 0 10px !important; overflow-x: auto; }
    .drawer-tab { padding: 12px 15px !important; font-size: 0.85rem !important; white-space: nowrap; }
    .drawer-body { padding: 20px !important; }
    .drawer-grid { grid-template-columns: 1fr !important; }

    /* Fix for header/button stacking in drawer tabs */
    .drawer-section .d-flex.justify-content-between.align-items-center:not(.info-box):not(.calendar-nav-header) {
        flex-direction: column !important;
        align-items: flex-start !important;
        gap: 12px !important;
    }
    .drawer-section .btn-sm {
        width: 100% !important;
        white-space: nowrap !important;
        text-align: center !important;
        padding: 8px !important;
    }
    /* Side-by-side icon buttons for Assets section */
    .drawer-section .d-flex.gap-2 .btn-sm {
        width: auto !important;
        min-width: 44px !important;
        flex: 1;
    }

    /* Attendance Calendar Mobile Optimizations */
    .drawer-section .calendar-reason {
        display: none !important;
    }
    .drawer-section .calendar-date {
        aspect-ratio: 1 !important;
        padding: 4px !important;
        gap: 2px !important;
        font-size: 13px !important;
    }
    .drawer-section .status-dot {
        width: 5px !important;
        height: 5px !important;
        margin-top: 1px !important;
    }

    /* Mobile Payslip Cards */
    .payslip-mobile-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        box-shadow: 0 4px 6px -1px rgba(0,0,0,0.03);
    }
    .border-top-dashed {
        border-top: 1px dashed rgba(0, 0, 0, 0.08) !important;
    }

    /* Payslip Generator Mobile Optimization */
    #payslipGeneratorModal .modal-dialog { margin: 0 !important; max-width: 100% !important; }
    #payslipGeneratorModal .modal-content { border-radius: 0 !important; min-height: 100vh !important; }
    #payslipGeneratorModal .modal-body { max-height: calc(100vh - 110px) !important; overflow-y: auto !important; }
    .generator-split { grid-template-columns: 1fr !important; min-height: auto !important; }
    .generator-settings { border-right: none !important; border-bottom: 1px solid #f1f5f9; padding: 15px !important; }
    .generator-preview { padding: 15px !important; background: #fff !important; }
    .preview-card { padding: 15px !important; box-shadow: none !important; border: 1px solid #e2e8f0 !important; }
    .preview-card h5 { font-size: 18px !important; }
    .preview-card table { font-size: 11px !important; }
    .preview-card td { padding: 4px 6px !important; }
  }

  /* Global Modal Z-Index Fix - Ensure modals appear above the User 360 Drawer (z-index 2600) */
  .modal { z-index: 3500 !important; }
  .modal-backdrop { z-index: 3490 !important; }
  #appointmentLetterModal.modal { z-index: 100100 !important; }
  #signatureModal.modal { z-index: 100110 !important; }
  #assignAssetFromDrawerModal.modal { z-index: 100200 !important; }
  body.assign-asset-modal-open .modal-backdrop.show { z-index: 100190 !important; }
  body.assign-asset-modal-open .usr-mobile-bottom-nav { display: none !important; }
  body.assign-asset-modal-open .profile-drawer,
  body.assign-asset-modal-open .profile-drawer-overlay { pointer-events: none !important; }
  .note-editor .note-modal,
  .note-modal { z-index: 100105 !important; }
  
  /* Force SweetAlert to appear above EVERYTHING (Side Drawer, Modals, etc.) */
  .swal2-container, .swal2-popup {
      z-index: 999999 !important;
  }
  
  @media (max-width: 480px) {
    .filter-group { flex-wrap: wrap; }
  }

  /* Unified Table Row Styling */
  .user-data-table tbody tr { 
      background: var(--table-bg) !important; 
      border-radius: 12px !important; 
      transition: transform 0.2s;
  }
  .user-data-table tbody tr:hover { 
      transform: translateY(-2px); 
      background: var(--table-hover) !important;
  }
  .user-data-table th, .user-data-table td { border: none !important; padding: 14px 16px !important; color: var(--text-primary) !important; }
  .user-data-table thead th { 
      background: transparent !important; 
      color: var(--text-muted) !important; 
      border-bottom: 2px solid var(--table-border) !important; 
      font-weight: 600; 
      text-transform: uppercase; 
      font-size: 11.5px !important; 
  }

  /* Custom Multi-select UI */
  .custom-multiselect { position: relative; border: 1px solid var(--table-border); border-radius: 8px; padding: 5px 10px; cursor: pointer; background: var(--table-bg); min-height: 42px; }
  #selected_tags { display: flex; flex-wrap: wrap; gap: 5px; margin-top: 5px; }
  .tag { background: #0d6efd; color: #fff; padding: 2px 8px; border-radius: 6px; font-size: 13px; display: flex; align-items: center; }
  .dropdown { display: none; position: absolute; top: 100%; left: 0; right: 0; background: var(--table-bg); border: 1px solid var(--table-border); z-index: 999; max-height: 150px; overflow-y: auto; border-radius: 5px; box-shadow: var(--shadow); }
  .dropdown-item { padding: 8px 12px; cursor: pointer; color: var(--text-primary); }
  .dropdown-item:hover { background-color: var(--table-hover); }

  /* Summary ribbon â€” match attendance_report.php */
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
      flex-wrap: nowrap;
  }
  .summary-section::-webkit-scrollbar { display: none; }
  .summary-section .summary-card {
      background: #ffffff !important;
      border-radius: 50px !important;
      padding: 10px 25px !important;
      color: #333 !important;
      min-width: 180px !important;
      min-height: auto !important;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05) !important;
      border: 1px solid #e0e0e0 !important;
      font-weight: 600 !important;
      display: inline-flex !important;
      align-items: center !important;
      justify-content: center !important;
  }
  .summary-section .summary-card:not(.stat-card-late) {
      cursor: pointer !important;
      transition: all 0.25s ease-in-out !important;
  }
  .summary-section .summary-card:not(.stat-card-late):hover {
      transform: translateY(-2px) !important;
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1) !important;
  }
  .summary-section .summary-card.stat-card-headcount { border: 2px solid #0ea5e9 !important; background: #ffffff !important; }
  .summary-section .summary-card.stat-card-present { border: 2px solid #10b981 !important; background: #ffffff !important; }
  .summary-section .summary-card.stat-card-absent { border: 2px solid #ef4444 !important; background: #ffffff !important; }
  .summary-section .summary-card.stat-card-late { border: 2px solid #f59e0b !important; background: #ffffff !important; }

  /* Active Filter Styles - Light Mode */
  .summary-section .summary-card.stat-card-headcount.active-filter { background: #f0f9ff !important; border: 3px solid #0ea5e9 !important; }
  .summary-section .summary-card.stat-card-absent.active-filter { background: #fef2f2 !important; border: 3px solid #ef4444 !important; }
  .summary-section .summary-text,
  .summary-section .summary-text span {
      color: #333 !important;
      font-size: inherit !important;
      text-transform: none !important;
      letter-spacing: normal !important;
  }
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

  /* Dark mode specific tweaks */
  body.dark-mode .summary-section .summary-card {
      background: rgba(0, 0, 0, 0.6) !important;
      backdrop-filter: blur(25px) saturate(180%) !important;
      -webkit-backdrop-filter: blur(25px) saturate(180%) !important;
      color: rgba(255, 255, 255, 0.95) !important;
      border-color: rgba(255, 255, 255, 0.1) !important;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3) !important;
  }
  body.dark-mode .summary-section .summary-card:hover {
      background: rgba(20, 20, 20, 0.8) !important;
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4) !important;
  }
  body.dark-mode .summary-section .summary-card.stat-card-headcount { border-color: rgba(14, 165, 233, 0.6) !important; }
  body.dark-mode .summary-section .summary-card.stat-card-present { border-color: rgba(16, 185, 129, 0.6) !important; }
  body.dark-mode .summary-section .summary-card.stat-card-absent { border-color: rgba(239, 68, 68, 0.6) !important; }
  body.dark-mode .summary-section .summary-card.stat-card-late { border-color: rgba(245, 158, 11, 0.6) !important; }

  /* Active Filter Styles - Dark Mode */
  body.dark-mode .summary-section .summary-card.stat-card-headcount.active-filter { background: rgba(14, 165, 233, 0.15) !important; border: 3px solid #0ea5e9 !important; }
  body.dark-mode .summary-section .summary-card.stat-card-absent.active-filter { background: rgba(239, 68, 68, 0.15) !important; border: 3px solid #ef4444 !important; }
  
  body.dark-mode .summary-section .summary-text,
  body.dark-mode .summary-section .summary-text span {
      color: rgba(255, 255, 255, 0.95) !important;
  }
  body.dark-mode .summary-arrow {
      background: rgba(0, 0, 0, 0.6) !important;
      border: 1px solid rgba(255, 255, 255, 0.1) !important;
      color: rgba(255, 255, 255, 0.8) !important;
  }
  body.dark-mode .summary-arrow:hover {
      background: rgba(30, 30, 30, 0.9) !important;
      color: #fff !important;
  }

  body.dark-mode .form-select,
  body.dark-mode .form-control {
      background-color: rgba(255, 255, 255, 0.05) !important;
      color: #ffffff !important;
      border-color: rgba(255, 255, 255, 0.1) !important;
  }
  body.dark-mode .search-input {
      background-color: rgba(255, 255, 255, 0.05) !important;
      color: #ffffff !important;
      border: none !important;
      border-color: transparent !important;
      box-shadow: none !important;
      outline: none !important;
  }

  body.dark-mode .search-input:focus {
      border: none !important;
      border-color: transparent !important;
      box-shadow: none !important;
      outline: none !important;
  }
  body.dark-mode .search-input::placeholder {
      color: rgba(255, 255, 255, 0.5) !important;
  }
  body.dark-mode .usr-custom-dropdown-input,
  body.dark-mode .users-filter-input {
      color: #ffffff !important;
      background-color: rgba(255, 255, 255, 0.05) !important;
      border-color: rgba(255, 255, 255, 0.1) !important;
  }
  body.dark-mode input#tableSearchInput {
      color: #ffffff !important;
      background-color: rgba(255, 255, 255, 0.05) !important;
      border: none !important;
      border-color: transparent !important;
      box-shadow: none !important;
      outline: none !important;
  }

  body.dark-mode input#tableSearchInput:focus {
      border: none !important;
      border-color: transparent !important;
      box-shadow: none !important;
      outline: none !important;
  }

  body.dark-mode .tag {
      background: #0d6efd !important;
      color: #fff !important;
      border: 1px solid rgba(255, 255, 255, 0.1);
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
  body.dark-mode .pagination-info {
      color: #aaaaaa !important;
  }
  body.dark-mode .pagination-info span {
      color: #ffffff !important;
      font-weight: 600;
  }
  body.dark-mode .users-limit-select option {
      background-color: #1a1a1a !important;
      color: #ffffff !important;
  }

  /* FNF Icon Styling */
  .action-icon.fnf { color: #2a8c90 !important; } /* Teal for FNF */
  .action-icon.fnf:hover { background: rgba(42, 140, 144, 0.1) !important; }
  
  #fnfModal .modal-content {
      border-radius: 24px;
      border: none;
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
  }
  #fnfModal .modal-header {
      background: linear-gradient(135deg, #1e6063 0%, #2a8c90 100%);
      color: white;
      border-radius: 20px 20px 0 0;
      padding: 1.5rem;
  }
  #fnfModal .modal-body { padding: 2rem; }
  .fnf-label { font-size: 0.8rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 5px; display: block; }
  .fnf-value-box { 
      background: #f8fafc; 
      padding: 15px; 
      border-radius: 12px; 
      border: 1px solid #e2e8f0;
      margin-bottom: 20px;
  }
  .net-settlement-amount { font-size: 1.5rem; font-weight: 800; color: #2a8c90; }
  .info-box {
      background-color: #ffffff;
      transition: all 0.2s ease;
  }
  .info-box:hover {
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
      border-color: #cbd5e1 !important;
  }
  #fnfModal thead {
      background-color: #f8fafc;
  }
  .exit-documents-container {
      background: #ffffff;
      border: 1px solid #e2e8f0;
      border-radius: 16px;
      padding: 20px;
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
  }
  .btn-teal {
      background: linear-gradient(135deg, #1e6063 0%, #2a8c90 100%);
      border: none;
      color: white !important;
      font-weight: 600;
      box-shadow: 0 4px 12px rgba(42, 140, 144, 0.2);
      transition: all 0.2s ease;
  }
  .btn-teal:hover {
      background: linear-gradient(135deg, #184c4f 0%, #1e6063 100%);
      color: white !important;
      box-shadow: 0 6px 16px rgba(42, 140, 144, 0.3);
  }
  .btn-outline-teal {
      border: 1.5px solid #2a8c90;
      color: #2a8c90;
      background: transparent;
      font-weight: 600;
      transition: all 0.2s ease;
  }
  .btn-outline-teal:hover {
      background: rgba(42, 140, 144, 0.05);
      color: #1e6063;
      border-color: #1e6063;
  }
  #fnfModal .info-value.text-success {
      background: rgba(34, 197, 94, 0.1);
      color: #16a34a !important;
      padding: 4px 10px;
      border-radius: 20px;
      font-size: 11px;
      font-weight: 700;
      display: inline-block;
  }
  #fnfModal .info-value.text-muted {
      background: #f1f5f9;
      color: #64748b !important;
      padding: 4px 10px;
      border-radius: 20px;
      font-size: 11px;
      font-weight: 700;
      display: inline-block;
  }

  body.dark-mode #fnfModal .modal-content {
      background: #121212 !important;
      color: #ffffff !important;
      border: 1px solid #2d2d2d !important;
  }
  body.dark-mode #fnfModal .modal-header {
      background: #121212 !important;
      border-bottom: 1px solid #2d2d2d !important;
      color: #ffffff !important;
  }
  body.dark-mode #fnfModal .modal-header h5 {
      color: #ffffff !important;
  }
  body.dark-mode #fnfModal .btn-close {
      filter: none !important;
      background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23fff'%3e%3cpath d='M.293.293a1 1 0 0 1 1.414 0L8 6.586 14.293.293a1 1 0 1 1 1.414 1.414L9.414 8l6.293 6.293a1 1 0 0 1-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 0 1-1.414-1.414L6.586 8 .293 1.707a1 1 0 0 1 0-1.414z'/%3e%3c/svg%3e") !important;
      opacity: 0.8 !important;
  }
  body.dark-mode #fnfModal .btn-close:hover {
      opacity: 1 !important;
  }
  body.dark-mode #fnfModal .fnf-label {
      color: #888888 !important;
  }
  body.dark-mode #fnfModal #fnf_employee_name {
      color: #ffffff !important;
  }
  body.dark-mode #fnfModal .form-control,
  body.dark-mode #fnfModal .form-select {
      background-color: #000000 !important;
      color: #ffffff !important;
      border-color: #2d2d2d !important;
  }
  body.dark-mode #fnfModal .form-control:focus,
  body.dark-mode #fnfModal .form-select:focus {
      border-color: #ffffff !important;
      box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.15) !important;
  }
  body.dark-mode #fnfModal option {
      background-color: #121212 !important;
      color: #ffffff !important;
  }
  body.dark-mode #fnfModal .exit-documents-container {
      background: #181818 !important;
      border: 1px solid #2d2d2d !important;
      box-shadow: none !important;
  }
  body.dark-mode #fnfModal h5,
  body.dark-mode #fnfModal h6 {
      color: #ffffff !important;
  }
  body.dark-mode #fnfModal .info-box {
      background-color: #1a1a1a !important;
      border-color: #2d2d2d !important;
      color: #ffffff !important;
  }
  body.dark-mode #fnfModal .info-box:hover {
      border-color: #3d3d3d !important;
  }
  body.dark-mode #fnfModal .info-box .info-label {
      color: #888888 !important;
  }
  body.dark-mode #fnfModal .info-box .info-value.text-muted {
      color: #ffffff !important;
  }
  body.dark-mode #fnfModal .btn-teal {
      background: #2a8c90 !important;
      color: #ffffff !important;
      border: 1px solid #2a8c90 !important;
      box-shadow: none !important;
  }
  body.dark-mode #fnfModal .btn-teal:hover {
      background: #1e6063 !important;
      color: #ffffff !important;
      border-color: #1e6063 !important;
  }
  body.dark-mode #fnfModal .btn-outline-teal {
      background: transparent !important;
      border: 1.5px solid #2a8c90 !important;
      color: #2a8c90 !important;
  }
  body.dark-mode #fnfModal .btn-outline-teal:hover {
      background: rgba(42, 140, 144, 0.1) !important;
      color: #ffffff !important;
      border-color: #2a8c90 !important;
  }
  body.dark-mode #fnfModal .btn-outline-primary {
      border-color: #2a8c90 !important;
      color: #2a8c90 !important;
      background: transparent !important;
  }
  body.dark-mode #fnfModal .btn-outline-primary:hover {
      background: #2a8c90 !important;
      color: #ffffff !important;
  }
  body.dark-mode #fnfModal .btn-outline-secondary {
      border-color: #64748b !important;
      color: #cbd5e1 !important;
      background: transparent !important;
  }
  body.dark-mode #fnfModal .btn-outline-secondary:hover {
      background: #64748b !important;
      color: #ffffff !important;
  }
  body.dark-mode #fnfModal table {
      color: #ffffff !important;
  }
  body.dark-mode #fnfModal thead,
  body.dark-mode #fnfModal thead th {
      background-color: #1a1a1a !important;
      color: #ffffff !important;
      border-bottom: 1px solid #2d2d2d !important;
  }
  body.dark-mode #fnfModal tbody td {
      background-color: #262626 !important;
      color: #ffffff !important;
      border-bottom: 1px solid #2d2d2d !important;
  }
  body.dark-mode #fnfModal tbody tr:hover td {
      background-color: #1e1e1e !important;
  }
  body.dark-mode #fnfModal .info-value.text-success {
      background: rgba(74, 222, 128, 0.1) !important;
      color: #4ade80 !important;
  }
  body.dark-mode #fnfModal .info-value.text-muted {
      background: #1a1a1a !important;
      color: #aaaaaa !important;
  }

  /* FNF Modal â€” mobile only (desktop unchanged) */
  @media (max-width: 768px) {
      #fnfModal .modal-dialog {
          margin: 0.5rem auto;
          max-width: calc(100vw - 1rem);
          min-height: calc(100% - 1rem);
      }
      #fnfModal .modal-content {
          max-height: calc(100vh - 1rem);
          display: flex;
          flex-direction: column;
          border-radius: 16px;
      }
      #fnfModal .modal-header {
          padding: 14px 16px !important;
          border-radius: 16px 16px 0 0 !important;
          flex-shrink: 0;
      }
      #fnfModal .modal-header .application-icon {
          width: 36px !important;
          height: 36px !important;
          font-size: 17px !important;
      }
      #fnfModal .modal-header .modal-title {
          font-size: 1rem !important;
      }
      #fnfModal .modal-body {
          padding: 16px !important;
          overflow-y: auto;
          flex: 1 1 auto;
          -webkit-overflow-scrolling: touch;
      }
      #fnfModal #fnf_employee_name {
          font-size: 1.2rem !important;
          word-break: break-word;
      }
      #fnfModal .exit-documents-container {
          padding: 14px;
      }
      #fnfModal .exit-documents-container .info-box.d-flex {
          flex-direction: column !important;
          align-items: stretch !important;
          gap: 12px;
          padding: 14px !important;
      }
      #fnfModal .exit-documents-container .info-box > .d-flex.gap-1 {
          flex-direction: row !important;
          flex-wrap: nowrap;
          width: 100%;
          gap: 6px !important;
      }
      #fnfModal .exit-documents-container .info-box .btn {
          flex: 0 0 auto;
          width: auto;
          min-width: 38px;
          min-height: 38px;
          max-height: 38px;
          padding: 6px 10px !important;
          justify-content: center;
      }
      #fnfModal .exit-documents-container .info-box .btn .bi {
          margin: 0 !important;
      }
      #fnfModal .exit-documents-container .info-box .btn .me-1 {
          margin-right: 0 !important;
      }
      #fnfModal .fnf-footer-actions {
          flex-direction: column !important;
          gap: 10px !important;
      }
      #fnfModal .fnf-footer-actions .btn {
          width: 100% !important;
      }
      #fnfModal .table-responsive {
          -webkit-overflow-scrolling: touch;
      }
      #fnfModal .table {
          font-size: 0.8rem !important;
      }
      #fnfModal .table thead th,
      #fnfModal .table tbody td {
          padding: 8px 10px !important;
          white-space: nowrap;
      }
      #fnfModal #fnf_payslips_body .btn {
          min-width: 36px;
          min-height: 36px;
          padding: 4px 8px !important;
      }
  }

  @media (max-width: 480px) {
      #fnfModal .modal-dialog {
          margin: 0;
          max-width: 100%;
          min-height: 100%;
      }
      #fnfModal .modal-content {
          max-height: 100vh;
          border-radius: 0;
      }
      #fnfModal .modal-header {
          border-radius: 0 !important;
      }
  }

  /* User Profile Drawer (360 View) */
  .profile-drawer-overlay {
      position: fixed;
      top: 0; left: 0; right: 0; bottom: 0;
      background: rgba(0,0,0,0.4);
      backdrop-filter: blur(4px);
      z-index: 2500;
      opacity: 0;
      visibility: hidden;
      transition: all 0.3s ease;
  }
  .profile-drawer-overlay.active {
      opacity: 1;
      visibility: visible;
  }
  .profile-drawer {
      position: fixed;
      top: 50%; left: 50%;
      transform: translate(-50%, -50%) scale(0.95);
      width: 90vw;
      max-width: 1000px;
      height: 90vh;
      max-height: 95vh;
      border-radius: 20px;
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(20px);
      box-shadow: 0 25px 50px rgba(0,0,0,0.2);
      z-index: 2600;
      opacity: 0;
      visibility: hidden;
      transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
      display: flex;
      flex-direction: column;
      overflow: hidden;
  }
  .profile-drawer.active {
      transform: translate(-50%, -50%) scale(1);
      opacity: 1;
      visibility: visible;
  }
  body.dark-mode .profile-drawer {
      background: #1a1a1a;
      box-shadow: -10px 0 30px rgba(0,0,0,0.5);
      color: #e8e8e8;
  }
  .drawer-header {
      padding: 25px 30px;
      background: linear-gradient(135deg, #1e6063 0%, #2a8c90 100%);
      color: white;
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      position: relative;
  }
  .drawer-header-content {
      display: flex;
      gap: 20px;
      align-items: center;
  }
  .drawer-avatar {
      width: 70px;
      height: 70px;
      border-radius: 50%;
      background: rgba(255,255,255,0.2);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 2rem;
      border: 3px solid rgba(255,255,255,0.3);
  }
  .drawer-title h3 {
      margin: 0 0 5px 0;
      font-weight: 700;
      font-size: 1.5rem;
  }
  .drawer-title p {
      margin: 0;
      opacity: 0.8;
      font-size: 0.9rem;
  }
  .close-drawer {
      background: none;
      border: none;
      color: white;
      font-size: 1.5rem;
      cursor: pointer;
      opacity: 0.7;
      transition: opacity 0.2s;
  }
  .close-drawer:hover { opacity: 1; }
  
  .drawer-tabs {
      display: flex;
      padding: 0 20px;
      border-bottom: 1px solid var(--table-border, #eee);
      background: rgba(255,255,255,0.5);
      -ms-overflow-style: none;  /* IE and Edge */
      scrollbar-width: none;  /* Firefox */
  }
  .drawer-tabs::-webkit-scrollbar {
      display: none;
  }
  body.dark-mode .drawer-tabs {
      background: #1a1a1a;
      border-color: #3d3d3d;
  }
  .drawer-tab {
      padding: 15px 20px;
      cursor: pointer;
      font-weight: 600;
      color: var(--text-muted, #666);
      border-bottom: 3px solid transparent;
      transition: all 0.2s;
  }
  .drawer-tab:hover {
      color: #2a8c90;
  }
  .drawer-tab.active {
      color: #2a8c90;
      border-bottom-color: #2a8c90;
  }
  body.dark-mode .drawer-tab { color: #9a9a9a; }
  body.dark-mode .drawer-tab:hover { color: #d4d4d4; }
  body.dark-mode .drawer-tab.active { color: #f5f5f5; border-bottom-color: #f5f5f5; }

  .drawer-body {
      flex: 1;
      overflow-y: auto;
      padding: 25px 30px;
  }
  .drawer-section {
      display: none;
      animation: fadeIn 0.3s;
  }
  .drawer-section.active {
      display: block;
  }
  
  .drawer-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 15px;
      margin-bottom: 20px;
  }
  .info-box {
      background: var(--table-bg, #f8f9fa);
      padding: 15px;
      border-radius: 12px;
      border: 1px solid var(--table-border, #eee);
  }
  body.dark-mode .info-box {
      background: rgba(255,255,255,0.03);
      border-color: rgba(255,255,255,0.05);
  }
  .info-label {
      font-size: 0.8rem;
      text-transform: uppercase;
      color: var(--text-muted, #888);
      margin-bottom: 5px;
      font-weight: 700;
  }
  .info-value {
      font-size: 1.05rem;
      font-weight: 600;
      color: var(--text-dark, #333);
      word-break: break-word;
  }
  body.dark-mode .info-value { color: #eee; }

  .drawer-actions {
      display: flex;
      gap: 15px;
      margin-top: 20px;
  }
  .btn-drawer-edit {
      flex: 1;
      background: #2a8c90;
      color: white;
      border: none;
      padding: 12px;
      border-radius: 8px;
      font-weight: 600;
      cursor: pointer;
      transition: background 0.2s;
  }
  .btn-drawer-edit:hover { background: #1e6063; }
  
  /* Mock UI styles for extra tabs */
  .mock-placeholder {
      text-align: center;
      padding: 40px 20px;
      background: rgba(42, 140, 144, 0.05);
      border-radius: 12px;
      border: 1px dashed rgba(42, 140, 144, 0.3);
  }
  .mock-placeholder i {
      font-size: 3rem;
      color: #2a8c90;
      margin-bottom: 15px;
  }
  .mock-placeholder h4 {
      font-weight: 600;
      color: var(--text-dark, #333);
  }
  body.dark-mode .mock-placeholder h4 { color: #eee; }
  .suggestion-badge {
      display: inline-block;
      background: #8b5cf6;
      color: white;
      font-size: 0.7rem;
      padding: 3px 8px;
      border-radius: 12px;
      margin-left: 10px;
      vertical-align: middle;
  }

  /* TinyMCE in Modal Fix */
  .tox-tinymce-aux { z-index: 9999 !important; }

  .att-val {
      font-size: 16px;
      font-weight: 700;
      color: #1e293b;
  }
  
  /* Modern Payslip Generator Styles */
  #payslipGeneratorModal .modal-xl {
      max-width: 1180px !important;
  }
  #payslipGeneratorModal .modal-content {
      border: none;
      border-radius: 28px;
      background: rgba(255, 255, 255, 0.9);
      backdrop-filter: blur(20px);
      box-shadow: 0 25px 70px -12px rgba(0, 0, 0, 0.3);
  }
  #payslipGeneratorModal .modal-header {
      background: linear-gradient(135deg, #1e6063 0%, #2a8c90 100%);
      color: white;
      padding: 16px 32px;
      border: none;
  }
  #payslipGeneratorModal .modal-body {
      max-height: calc(100vh - 200px);
      overflow-y: auto;
  }
  .generator-split {
      display: grid;
      grid-template-columns: 380px 1fr;
      gap: 0;
      min-height: 480px;
  }
  .generator-settings {
      padding: 20px;
      background: #fff;
      border-right: 1px solid #f1f5f9;
  }
  .generator-preview {
      padding: 20px;
      background: #f8fafc;
      display: flex;
      flex-direction: column;
      justify-content: center;
  }
  .preview-card {
      background: white;
      border-radius: 20px;
      padding: 30px;
      box-shadow: 0 10px 25px rgba(0,0,0,0.05);
      border: 1px solid #e2e8f0;
      position: relative;
      overflow: hidden;
  }
  .preview-card::before {
      content: '';
      position: absolute;
      top: 0; left: 0; right: 0; height: 5px;
      background: #0d9488;
  }
  .preview-header {
      display: flex;
      justify-content: space-between;
      margin-bottom: 25px;
      padding-bottom: 15px;
      border-bottom: 1px dashed #e2e8f0;
  }
  .preview-section-title {
      font-size: 10px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 1px;
      color: #94a3b8;
      margin-bottom: 15px;
  }
  .preview-row {
      display: flex;
      justify-content: space-between;
      margin-bottom: 8px;
      font-size: 13px;
  }
  .preview-label { color: #64748b; }
  .preview-value { font-weight: 600; color: #1e293b; }
  .preview-net-box {
      margin-top: 25px;
      background: #f0fdfa;
      padding: 20px;
      border-radius: 15px;
      text-align: center;
      border: 1px solid #ccfbf1;
  }
  .preview-net-amount {
      font-size: 28px;
      font-weight: 800;
      color: #0d9488;
      display: block;
  }
  
  .payslip-input-card {
      background: #f8fafc;
      border-radius: 12px;
      padding: 15px;
      margin-bottom: 15px;
      border: 1px solid #e2e8f0;
  }
  .payslip-input-card h6 {
      font-size: 11px;
      font-weight: 800;
      text-transform: uppercase;
      color: #475569;
      margin-bottom: 12px;
      display: flex;
      align-items: center;
      gap: 8px;
  }
  
  /* Status Toggle Switch */
  .theme-switch { position: relative; display: inline-block; width: 44px; height: 22px; }
  .theme-switch input { opacity: 0; width: 0; height: 0; }
  .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: .4s; border-radius: 34px; }
  .slider:before { position: absolute; content: ""; height: 16px; width: 16px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
  input:checked + .slider { background-color: #10b981; }
  input:checked + .slider:before { transform: translateX(22px); }
  
  .mock-placeholder {
    padding: 30px;
    background: #f8fafc;
    border: 2px dashed #e2e8f0;
    border-radius: 16px;
    text-align: center;
    color: #64748b;
  }
  .mock-placeholder i { font-size: 3rem; margin-bottom: 10px; opacity: 0.5; }
  
  .suggestion-badge {
    background: #eef2ff;
    color: #6366f1;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    padding: 2px 8px;
    border-radius: 20px;
    letter-spacing: 0.5px;
  }
  
  /* Drawer Status Visibility */
  #drawer_status_text.text-success { color: #4ade80 !important; }
  #drawer_status_text.text-danger { color: #fca5a5 !important; }
  /* Attendance Calendar Styles */
  .attendance-calendar {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 8px;
    background: #f8fafc;
    padding: 15px;
    border-radius: 16px;
    border: 1px solid #e2e8f0;
  }
  .calendar-nav-header h5 {
    color: #0d9488 !important;
    font-size: 1.25rem;
  }
  .btn-calendar-nav {
    background: #0d9488;
    color: white !important;
    border-radius: 12px;
    padding: 6px 16px;
    font-weight: 600;
    font-size: 14px;
    border: none;
    transition: all 0.2s;
  }
  .btn-calendar-nav:hover {
    background: #097969;
    transform: scale(1.05);
  }
  .calendar-day-label {
    text-align: center;
    font-size: 13px;
    font-weight: 800;
    color: #475569;
    padding-bottom: 15px;
    text-transform: uppercase;
  }
  .calendar-date {
    aspect-ratio: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    font-weight: 700;
    border-radius: 12px;
    background: white;
    border: 1px solid #e2e8f0;
    color: #1e293b;
    position: relative;
    gap: 4px;
    transition: all 0.2s;
  }
  .calendar-date:hover { 
    transform: translateY(-2px); 
    box-shadow: 0 4px 12px rgba(0,0,0,0.08); 
    border-color: #cbd5e1;
  }
  .calendar-date.today { border: 2px solid #0d9488; color: #0d9488; }
  .calendar-date.present { border-color: #e2e8f0; }
  .calendar-date.absent { border-color: #e2e8f0; }
  .calendar-date.late { border-color: #e2e8f0; }
  .calendar-date.empty { background: transparent; border: none; box-shadow: none; }
  
  .status-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    margin-top: 2px;
  }
  .calendar-date.present .status-dot { background: #3b82f6; } /* Blue for present */
  .calendar-date.absent .status-dot { background: #ef4444; }  /* Red for absent */
  .calendar-date.late .status-dot { background: #f59e0b; }   /* Orange for late */
  .calendar-date.leave .status-dot { background: #3b82f6; }
  .calendar-date.holiday .status-dot { background: #94a3b8; } /* Sunday / weekly off */
  .calendar-date.company-holiday {
    background: #f5f3ff;
    border-color: #c4b5fd;
  }
  .calendar-date.company-holiday .status-dot { background: #7c3aed; } /* Company holiday from HR calendar */
  .calendar-date.company-holiday .calendar-reason { color: #6d28d9; font-weight: 600; }
  
  .calendar-reason {
    font-size: 7px;
    color: #94a3b8;
    max-width: 90%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    position: absolute;
    bottom: 2px;
    font-weight: 500;
  }
  
  /* Ensure Summernote editor allows absolute positioning of signatures */
  .note-editable { position: relative !important; min-height: 600px !important; }
  .signature-container {
    position: relative !important;
    display: inline-block !important;
    width: 0 !important;
    height: 0 !important;
    vertical-align: bottom !important;
    overflow: visible !important;
  }
  .signature-stamp, img.signature-stamp {
    position: absolute !important;
    max-width: 220px !important;
    height: auto !important;
    z-index: 10;
    transition: none !important; /* Disable transitions during drag */
  }

  /* HR signature placeholder â€” visible and clickable in the letter editor */
  .note-editable .sig-placeholder {
    display: inline-block !important;
    margin: 12px 0 !important;
    padding: 10px 14px !important;
    color: #475569 !important;
    background: #f8fafc !important;
    border: 1px dashed #94a3b8 !important;
    border-radius: 8px !important;
    font-size: 12px !important;
    font-weight: 600 !important;
    cursor: pointer !important;
    user-select: none !important;
    min-width: 220px;
    text-align: center;
  }
  .note-editable .sig-placeholder:hover {
    background: #eef2ff !important;
    border-color: #6366f1 !important;
    color: #4338ca !important;
  }

  /* Offer letter preview in editor: avoid fixed header/footer blocking clicks */
  .note-editable .offer-letter-doc {
    padding-top: 0 !important;
    padding-bottom: 0 !important;
  }
  .note-editable .offer-letter-doc .header-fixed,
  .note-editable .offer-letter-doc .footer-fixed {
    position: relative !important;
    top: auto !important;
    bottom: auto !important;
    left: auto !important;
    width: 100% !important;
    height: auto !important;
    z-index: 1 !important;
  }
  .note-editable .offer-letter-doc .letter-watermark {
    position: relative !important;
    top: auto !important;
    left: auto !important;
    transform: none !important;
    margin: 20px 0 !important;
    opacity: 0.06 !important;
  }
  .note-editable .offer-letter-doc .header-space,
  .note-editable .offer-letter-doc .footer-space {
    height: 12px !important;
  }
  .note-editable .offer-letter-doc {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    line-height: 1.55;
    color: #333;
  }
  .note-editable .offer-letter-doc .offer-letter-logo {
    max-height: 58px;
    width: auto;
    display: block;
  }
  .note-editable .offer-letter-doc .offer-letter-logo--watermark {
    max-width: 420px;
    width: 70%;
    margin: 0 auto;
    opacity: 0.08;
  }
  .note-editable .offer-letter-doc .company-info {
    text-align: right;
    font-size: 11px;
    color: #333;
    line-height: 1.7;
    font-weight: 600;
  }
  .note-editable .offer-letter-doc .header-border {
    height: 2px;
    background: linear-gradient(to right, #115b82 0%, #115b82 75%, #f5a623 75%, #f5a623 88%, #e63946 88%, #e63946 100%);
    margin-top: 8px;
  }
  .note-editable .offer-letter-doc .letter-title {
    text-align: center;
    font-size: 17px;
    font-weight: 800;
    text-decoration: underline;
    margin: 18px 0 22px;
    color: #115b82;
  }
  .note-editable .offer-letter-doc .content-body {
    font-size: 13px;
    text-align: justify;
  }
  .note-editable .offer-letter-doc .content-body p {
    margin-bottom: 12px;
  }
  .note-editable .offer-letter-doc .salary-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
    margin-top: 12px;
  }
  .note-editable .offer-letter-doc .salary-table th,
  .note-editable .offer-letter-doc .salary-table td {
    border: 1px solid #ddd;
    padding: 8px 10px;
    text-align: center;
  }
  .note-editable .offer-letter-doc .salary-table td:first-child {
    text-align: left;
    font-weight: 600;
  }
  .note-editable .offer-letter-doc .salary-table .category-row,
  .note-editable .offer-letter-doc .salary-table .total-row {
    background: #004d80;
    color: #fff;
    font-weight: 700;
  }
  .note-editable .offer-letter-doc .salary-table .category-row td {
    text-align: left;
  }
  .note-editable .offer-letter-doc .footer-title {
    margin: 0;
    font-size: 20px;
    color: #222;
    font-weight: 800;
  }
  .note-editable .offer-letter-doc .footer-address {
    margin: 2px 0 8px;
    font-size: 12px;
    color: #444;
    font-weight: 600;
  }
  .note-editable .offer-letter-doc .footer-bottom-bar {
    background: #115b82;
    color: #fff;
    font-size: 10px;
    font-weight: 700;
    text-align: center;
    padding: 8px 12px;
  }
  .note-editable .offer-letter-doc .letter-layout-table {
    width: 100%;
    border-collapse: collapse;
  }
  .note-editable .offer-letter-doc .letter-layout-table td {
    border: none;
    vertical-align: top;
  }
  
  /* Salary Structure UI */
  .salary-structure-box {
    background: #f8fafc;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    overflow: hidden;
  }
  .salary-table {
    width: 100%;
    margin-bottom: 0;
    font-size: 13px;
  }
  .salary-table th {
    background: #f1f5f9;
    color: #475569;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 11px;
    letter-spacing: 0.5px;
    padding: 10px 15px;
    border-bottom: 1px solid #e2e8f0;
  }
  .salary-table td {
    padding: 10px 15px;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
  }
  .salary-table tr:last-child td { border-bottom: none; }
  .salary-table .row-label { font-weight: 500; color: #1e293b; }
  .salary-table .row-value { font-family: 'JetBrains Mono', monospace; font-weight: 600; text-align: right; }
  .salary-table .row-yearly { color: #64748b; font-size: 12px; text-align: right; }
  .salary-table .highlight-row { background: #eff6ff; }
  .salary-table .total-row { background: #1e293b; color: white; }
  .salary-table .total-row .row-label { color: #cbd5e1; }
  .salary-table .total-row .row-value { color: #38bdf8; }
  .salary-table .deduction-row { color: #ef4444; }
  .salary-table .net-row { background: #f0fdf4; border-top: 2px solid #22c55e; }
  .salary-table .net-row .row-label { color: #166534; font-weight: 700; }
  .salary-table .net-row .row-value { color: #15803d; font-size: 15px; }

    @media print {
      .sig-placeholder, .note-editor .note-toolbar, .note-resizebar, .modal-header, .modal-footer, .btn, .no-print { 
          display: none !important; 
      }
      .modal-body { padding: 0 !important; }
      .signature-container {
          position: relative !important;
          display: inline-block !important;
          width: 0 !important;
          height: 0 !important;
          vertical-align: bottom !important;
          overflow: visible !important;
      }
      .signature-stamp, img.signature-stamp {
          position: absolute !important;
          max-width: 220px !important;
          height: auto !important;
          z-index: 10;
          border: none !important;
          outline: none !important;
      }
    }

  /* High-fidelity Dark Mode overrides for User 360 Card (Profile Drawer) Calendar and Payroll */
  
  /* 1) Attendance Calendar Dark Mode (User 360 drawer — neutral) */
  body.dark-mode .profile-drawer .attendance-calendar {
      background: #242424 !important;
      border-color: #3d3d3d !important;
  }
  body.dark-mode .profile-drawer .calendar-day-label {
      color: #9a9a9a !important;
  }
  body.dark-mode .calendar-date {
      background: rgba(255, 255, 255, 0.04) !important;
      border-color: rgba(255, 255, 255, 0.06) !important;
      color: #ffffff !important;
  }
  body.dark-mode .profile-drawer .calendar-date:hover {
      background: rgba(255, 255, 255, 0.08) !important;
      border-color: #6b6b6b !important;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3) !important;
  }
  body.dark-mode .profile-drawer .calendar-date.today {
      border: 2px solid #a3a3a3 !important;
      color: #f5f5f5 !important;
  }
  body.dark-mode .calendar-date.present {
      border-color: rgba(255, 255, 255, 0.06) !important;
  }
  body.dark-mode .calendar-date.absent {
      border-color: rgba(255, 255, 255, 0.06) !important;
  }
  body.dark-mode .calendar-date.late {
      border-color: rgba(255, 255, 255, 0.06) !important;
  }
  body.dark-mode .calendar-date.empty {
      background: transparent !important;
      border: none !important;
  }
  body.dark-mode .calendar-date.company-holiday {
      background: rgba(124, 58, 237, 0.18) !important;
      border-color: rgba(167, 139, 250, 0.55) !important;
  }
  body.dark-mode .calendar-date.company-holiday .status-dot {
      background: #a78bfa !important;
  }
  body.dark-mode .calendar-date.company-holiday .calendar-reason {
      color: #c4b5fd !important;
  }

  /* 2) Payroll (Salary Structure) Dark Mode — User 360 drawer */
  body.dark-mode .profile-drawer .salary-structure-box,
  body.dark-mode .profile-drawer #overview_salary_structure_container {
      background: #242424 !important;
      border-color: #3d3d3d !important;
  }
  body.dark-mode .salary-table th {
      background: rgba(255, 255, 255, 0.05) !important;
      color: #e2e8f0 !important;
      border-bottom: 1px solid rgba(255, 255, 255, 0.08) !important;
  }
  body.dark-mode .salary-table td {
      border-bottom: 1px solid rgba(255, 255, 255, 0.04) !important;
      color: #e2e8f0 !important;
  }
  body.dark-mode .salary-table .row-label {
      color: #e2e8f0 !important;
  }
  body.dark-mode .salary-table .row-yearly {
      color: #94a3b8 !important;
  }
  body.dark-mode .profile-drawer .salary-table .highlight-row {
      background: rgba(255, 255, 255, 0.06) !important;
      color: #e8e8e8 !important;
  }
  body.dark-mode .profile-drawer .salary-table .net-row {
      background: rgba(255, 255, 255, 0.08) !important;
      border-top: 2px solid #6b6b6b !important;
  }
  body.dark-mode .profile-drawer .salary-table .net-row .row-label,
  body.dark-mode .profile-drawer .salary-table .net-row .row-value {
      color: #f5f5f5 !important;
  }
  
  /* Profile Drawer Tables (Recent Payslips & Other General Tables) */
  body.dark-mode .profile-drawer table {
      background-color: transparent !important;
  }
  body.dark-mode .profile-drawer table tr {
      background-color: transparent !important;
  }
  body.dark-mode .profile-drawer table thead th {
      background: rgba(255, 255, 255, 0.05) !important;
      color: #ffffff !important;
      border-color: rgba(255, 255, 255, 0.1) !important;
  }
  body.dark-mode .profile-drawer table tbody td {
      background-color: transparent !important;
      color: #eee !important;
      border-color: rgba(255, 255, 255, 0.05) !important;
  }
  body.dark-mode .profile-drawer table.table-hover tbody tr:hover td {
      background-color: rgba(255, 255, 255, 0.02) !important;
  }
  body.dark-mode .profile-drawer .text-muted {
      color: #9a9a9a !important;
  }

  /* User 360 — Company Assets serial number badge */
  .profile-drawer .drawer-asset-serial {
      display: inline-block;
      padding: 4px 10px;
      border-radius: 8px;
      font-size: 0.75rem;
      font-weight: 600;
      letter-spacing: 0.02em;
      background: #f1f5f9;
      color: #334155;
      border: 1px solid #e2e8f0;
  }
  body.dark-mode .profile-drawer .drawer-asset-serial,
  html.dark-mode .profile-drawer .drawer-asset-serial {
      background: #333333 !important;
      color: #e8e8e8 !important;
      border-color: #525252 !important;
  }

  body.dark-mode .payslip-mobile-card {
      background: rgba(255, 255, 255, 0.03) !important;
      border-color: rgba(255, 255, 255, 0.08) !important;
      box-shadow: none !important;
  }
  body.dark-mode .border-top-dashed {
      border-top-color: rgba(255, 255, 255, 0.08) !important;
  }

  /* Payroll Configuration modal — neutral dark mode only (body + html.dark-mode) */
  body.dark-mode #payslipGeneratorModal .modal-content,
  html.dark-mode #payslipGeneratorModal .modal-content {
      background: #1a1a1a !important;
      color: #e8e8e8 !important;
      border-color: #3d3d3d !important;
  }
  body.dark-mode #payslipGeneratorModal .modal-header,
  html.dark-mode #payslipGeneratorModal .modal-header {
      background: #242424 !important;
      border-bottom: 1px solid #3d3d3d !important;
      color: #f5f5f5 !important;
  }
  body.dark-mode #payslipGeneratorModal .modal-title,
  html.dark-mode #payslipGeneratorModal .modal-title {
      color: #f5f5f5 !important;
  }
  body.dark-mode #payslipGeneratorModal .btn-close,
  html.dark-mode #payslipGeneratorModal .btn-close {
      filter: none !important;
      background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23f5f5f5'%3e%3cpath d='M.293.293a1 1 0 0 1 1.414 0L8 6.586 14.293.293a1 1 0 1 1 1.414 1.414L9.414 8l6.293 6.293a1 1 0 0 1-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 0 1-1.414-1.414L6.586 8 .293 1.707a1 1 0 0 1 0-1.414z'/%3e%3c/svg%3e") !important;
      opacity: 0.85 !important;
  }
  body.dark-mode #payslipGeneratorModal .generator-settings,
  html.dark-mode #payslipGeneratorModal .generator-settings {
      background: #1a1a1a !important;
      border-right: 1px solid #3d3d3d !important;
  }
  body.dark-mode #payslipGeneratorModal .generator-preview,
  html.dark-mode #payslipGeneratorModal .generator-preview {
      background: #1a1a1a !important;
  }
  body.dark-mode #payslipGeneratorModal .payslip-input-card,
  html.dark-mode #payslipGeneratorModal .payslip-input-card {
      background: #242424 !important;
      border: 1px solid #3d3d3d !important;
  }
  body.dark-mode #payslipGeneratorModal .payslip-input-card h6,
  html.dark-mode #payslipGeneratorModal .payslip-input-card h6 {
      color: #9a9a9a !important;
  }
  body.dark-mode #payslipGeneratorModal .payslip-input-card input,
  body.dark-mode #payslipGeneratorModal .payslip-input-card select,
  html.dark-mode #payslipGeneratorModal .payslip-input-card input,
  html.dark-mode #payslipGeneratorModal .payslip-input-card select {
      background-color: #1a1a1a !important;
      border: 1px solid #3d3d3d !important;
      color: #f5f5f5 !important;
  }
  body.dark-mode #payslipGeneratorModal .payslip-input-card label,
  html.dark-mode #payslipGeneratorModal .payslip-input-card label {
      color: #9a9a9a !important;
  }
  body.dark-mode #payslipGeneratorModal #payslip_paid_days,
  html.dark-mode #payslipGeneratorModal #payslip_paid_days {
      background-color: #1a1a1a !important;
      border-color: #3d3d3d !important;
      color: #f5f5f5 !important;
  }
  body.dark-mode #payslipGeneratorModal .preview-card,
  html.dark-mode #payslipGeneratorModal .preview-card {
      background: #242424 !important;
      border: 1px solid #3d3d3d !important;
      color: #f5f5f5 !important;
      box-shadow: none !important;
  }
  body.dark-mode #payslipGeneratorModal .preview-card::before,
  html.dark-mode #payslipGeneratorModal .preview-card::before {
      background: #9a9a9a !important;
  }
  body.dark-mode #payslipGeneratorModal .preview-card h5,
  body.dark-mode #payslipGeneratorModal .preview-card strong,
  body.dark-mode #payslipGeneratorModal .preview-card #preview_period,
  body.dark-mode #payslipGeneratorModal .preview-card #preview_name,
  html.dark-mode #payslipGeneratorModal .preview-card h5,
  html.dark-mode #payslipGeneratorModal .preview-card strong,
  html.dark-mode #payslipGeneratorModal .preview-card #preview_period,
  html.dark-mode #payslipGeneratorModal .preview-card #preview_name {
      color: #f5f5f5 !important;
  }
  body.dark-mode #payslipGeneratorModal .preview-card span.text-muted,
  body.dark-mode #payslipGeneratorModal #preview_words,
  html.dark-mode #payslipGeneratorModal .preview-card span.text-muted,
  html.dark-mode #payslipGeneratorModal #preview_words {
      color: #9a9a9a !important;
  }
  body.dark-mode #payslipGeneratorModal .preview-card div[style*="border-top"],
  body.dark-mode #payslipGeneratorModal .preview-card div[style*="border: 1px solid #cbd5e1"],
  html.dark-mode #payslipGeneratorModal .preview-card div[style*="border-top"],
  html.dark-mode #payslipGeneratorModal .preview-card div[style*="border: 1px solid #cbd5e1"] {
      border-color: #3d3d3d !important;
  }
  body.dark-mode #payslipGeneratorModal .preview-card td,
  body.dark-mode #payslipGeneratorModal .preview-card td[id^="preview_"],
  html.dark-mode #payslipGeneratorModal .preview-card td,
  html.dark-mode #payslipGeneratorModal .preview-card td[id^="preview_"] {
      border-color: #3d3d3d !important;
      color: #f5f5f5 !important;
  }
  body.dark-mode #payslipGeneratorModal .preview-card td[style*="border-right"],
  html.dark-mode #payslipGeneratorModal .preview-card td[style*="border-right"] {
      border-right: 1px solid #3d3d3d !important;
  }
  body.dark-mode #payslipGeneratorModal .preview-card td[id="preview_pf"],
  body.dark-mode #payslipGeneratorModal .preview-card td[id="preview_pt"],
  body.dark-mode #payslipGeneratorModal .preview-card td[id="preview_medical"],
  body.dark-mode #payslipGeneratorModal .preview-card td[id="preview_custom_ded"],
  body.dark-mode #payslipGeneratorModal .preview-card td[id="preview_lop_amount"],
  html.dark-mode #payslipGeneratorModal .preview-card td[id="preview_pf"],
  html.dark-mode #payslipGeneratorModal .preview-card td[id="preview_pt"],
  html.dark-mode #payslipGeneratorModal .preview-card td[id="preview_medical"],
  html.dark-mode #payslipGeneratorModal .preview-card td[id="preview_custom_ded"],
  html.dark-mode #payslipGeneratorModal .preview-card td[id="preview_lop_amount"] {
      color: #e8a0a0 !important;
  }
  body.dark-mode #payslipGeneratorModal .preview-card div[style*="background: #fff;"],
  html.dark-mode #payslipGeneratorModal .preview-card div[style*="background: #fff;"] {
      background: #1a1a1a !important;
      border-color: #3d3d3d !important;
  }
  body.dark-mode #payslipGeneratorModal #preview_net_pay,
  html.dark-mode #payslipGeneratorModal #preview_net_pay {
      color: #f5f5f5 !important;
  }
  body.dark-mode #payslipGeneratorModal .preview-value,
  html.dark-mode #payslipGeneratorModal .preview-value {
      color: #f5f5f5 !important;
  }
  body.dark-mode #payslipGeneratorModal .preview-net-box,
  html.dark-mode #payslipGeneratorModal .preview-net-box {
      background: #1a1a1a !important;
      border-color: #3d3d3d !important;
  }
  body.dark-mode #payslipGeneratorModal .modal-footer,
  html.dark-mode #payslipGeneratorModal .modal-footer {
      background: #1a1a1a !important;
      border-top: 1px solid #3d3d3d !important;
  }
  body.dark-mode #payslipGeneratorModal .modal-footer .btn-outline-primary,
  body.dark-mode #payslipGeneratorModal .modal-footer .btn-light,
  html.dark-mode #payslipGeneratorModal .modal-footer .btn-outline-primary,
  html.dark-mode #payslipGeneratorModal .modal-footer .btn-light {
      background: #404040 !important;
      border: 1px solid #525252 !important;
      color: #f5f5f5 !important;
  }
  body.dark-mode #payslipGeneratorModal .modal-footer .btn-outline-primary:hover,
  body.dark-mode #payslipGeneratorModal .modal-footer .btn-light:hover,
  html.dark-mode #payslipGeneratorModal .modal-footer .btn-outline-primary:hover,
  html.dark-mode #payslipGeneratorModal .modal-footer .btn-light:hover {
      background: #525252 !important;
      color: #ffffff !important;
  }
  body.dark-mode #payslipGeneratorModal .modal-footer .btn-primary,
  html.dark-mode #payslipGeneratorModal .modal-footer .btn-primary {
      background: #e8e8e8 !important;
      color: #1a1a1a !important;
      border: none !important;
      box-shadow: none !important;
  }
  body.dark-mode #payslipGeneratorModal .modal-footer .btn-primary:hover,
  html.dark-mode #payslipGeneratorModal .modal-footer .btn-primary:hover {
      background: #f5f5f5 !important;
      color: #1a1a1a !important;
  }

  /* 4) Appointment Letter Modal & Summernote Dark Mode Overrides */
  body.dark-mode #appointmentLetterModal .modal-content {
      background-color: #1e1e2d !important;
      color: #ffffff !important;
      border: 1px solid rgba(255, 255, 255, 0.08) !important;
  }
  body.dark-mode #appointmentLetterModal .modal-footer {
      background-color: #181824 !important;
      border-top: 1px solid rgba(255, 255, 255, 0.08) !important;
  }
  body.dark-mode #appointmentLetterModal .modal-footer .btn-secondary {
      background: rgba(255, 255, 255, 0.05) !important;
      border: 1px solid rgba(255, 255, 255, 0.08) !important;
      color: #ffffff !important;
  }
  /* Letter preview — above letter editor (100100) when opened from Preview button */
  body.letter-preview-open #letterPreviewModal.modal {
    z-index: 100120 !important;
  }
  body.letter-preview-open .modal-backdrop.show:last-of-type {
    z-index: 100115 !important;
  }
  .letter-preview-dialog {
    max-width: 96vw !important;
    width: 96vw !important;
    margin: 2vh auto !important;
  }
  .letter-preview-dialog .modal-content {
    height: 92vh;
    max-height: 92vh;
    display: flex;
    flex-direction: column;
  }
  .letter-preview-dialog .modal-body {
    flex: 1 1 auto;
    min-height: 0;
    overflow: hidden;
  }
  .letter-preview-body {
    background: #e8ecef;
    height: 100%;
    min-height: 0;
  }
  .letter-preview-frame {
    display: block;
    width: 100%;
    height: 100% !important;
    min-height: 0 !important;
    border: none;
    background: #e8ecef;
  }
  .letter-preview-loading {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #e8ecef;
    z-index: 2;
  }
  /* Letter / Payslip preview modal — neutral dark mode only */
  body.dark-mode .letter-preview-body,
  body.dark-mode .letter-preview-loading,
  body.dark-mode .letter-preview-frame,
  html.dark-mode .letter-preview-body,
  html.dark-mode .letter-preview-loading,
  html.dark-mode .letter-preview-frame {
    background: #1a1a1a !important;
  }
  body.dark-mode #letterPreviewModal .modal-content,
  html.dark-mode #letterPreviewModal .modal-content {
    background: #1a1a1a !important;
    color: #e8e8e8 !important;
    border: 1px solid #3d3d3d !important;
  }
  body.dark-mode #letterPreviewModal .modal-header,
  html.dark-mode #letterPreviewModal .modal-header {
    background: #242424 !important;
    border-bottom: 1px solid #3d3d3d !important;
    color: #f5f5f5 !important;
  }
  body.dark-mode #letterPreviewModal .modal-title,
  html.dark-mode #letterPreviewModal .modal-title {
    color: #f5f5f5 !important;
  }
  body.dark-mode #letterPreviewModal .modal-footer,
  html.dark-mode #letterPreviewModal .modal-footer {
    background: #1a1a1a !important;
    border-top: 1px solid #3d3d3d !important;
  }
  body.dark-mode #letterPreviewModal .modal-footer .btn-secondary,
  html.dark-mode #letterPreviewModal .modal-footer .btn-secondary {
    background: #404040 !important;
    border: 1px solid #525252 !important;
    color: #f5f5f5 !important;
  }
  body.dark-mode #letterPreviewModal .modal-footer .btn-outline-primary,
  html.dark-mode #letterPreviewModal .modal-footer .btn-outline-primary {
    background: transparent !important;
    border-color: #6b6b6b !important;
    color: #e8e8e8 !important;
  }
  body.dark-mode #letterPreviewModal .modal-footer .btn-outline-primary:hover,
  html.dark-mode #letterPreviewModal .modal-footer .btn-outline-primary:hover {
    background: #333333 !important;
    color: #f5f5f5 !important;
  }
  body.dark-mode #letterPreviewModal .modal-footer .btn-primary,
  html.dark-mode #letterPreviewModal .modal-footer .btn-primary {
    background: #525252 !important;
    border: 1px solid #6b6b6b !important;
    color: #f5f5f5 !important;
  }
  body.dark-mode #letterPreviewModal .modal-footer .btn-primary:hover,
  html.dark-mode #letterPreviewModal .modal-footer .btn-primary:hover {
    background: #6b6b6b !important;
    color: #ffffff !important;
  }
  body.dark-mode #letterPreviewModal .letter-preview-loading .text-muted,
  html.dark-mode #letterPreviewModal .letter-preview-loading .text-muted {
    color: #9a9a9a !important;
  }
  body.dark-mode #letterPreviewModal .letter-preview-loading .spinner-border,
  html.dark-mode #letterPreviewModal .letter-preview-loading .spinner-border {
    color: #9a9a9a !important;
  }

  body.dark-mode #appointmentLetterModal .modal-footer .btn-secondary:hover {
      background: rgba(255, 255, 255, 0.1) !important;
  }

  /* Summernote Editor Outer Wrapper */
  body.dark-mode .note-editor.note-frame {
      background-color: #181824 !important;
      border: 1px solid rgba(255, 255, 255, 0.08) !important;
  }
  body.dark-mode .note-editor.note-frame .note-statusbar {
      background-color: #181824 !important;
      border-top: 1px solid rgba(255, 255, 255, 0.08) !important;
  }
  body.dark-mode .note-editor.note-frame .note-statusbar .note-resizebar {
      border-top: 1px solid rgba(255, 255, 255, 0.08) !important;
  }

  /* Summernote Toolbar */
  body.dark-mode .note-toolbar {
      background-color: #1e1e2d !important;
      border-bottom: 1px solid rgba(255, 255, 255, 0.08) !important;
  }
  body.dark-mode .note-btn {
      background-color: rgba(255, 255, 255, 0.03) !important;
      border: 1px solid rgba(255, 255, 255, 0.08) !important;
      color: #e2e8f0 !important;
  }
  body.dark-mode .note-btn:hover,
  body.dark-mode .note-btn.active,
  body.dark-mode .note-btn:focus {
      background-color: rgba(255, 255, 255, 0.1) !important;
      color: #ffffff !important;
  }
  body.dark-mode .note-btn i,
  body.dark-mode .note-btn .note-icon-caret {
      color: #e2e8f0 !important;
  }
  body.dark-mode .note-btn:hover i,
  body.dark-mode .note-btn:hover .note-icon-caret {
      color: #ffffff !important;
  }

  /* Summernote Editable Text Area */
  body.dark-mode .note-editable {
      background-color: #12121a !important;
      color: #ffffff !important;
  }
  
  /* Force all paragraph elements, headers, list items, spans, table elements inside editable container to be white */
  body.dark-mode .note-editable,
  body.dark-mode .note-editable *,
  body.dark-mode .note-editable p,
  body.dark-mode .note-editable span,
  body.dark-mode .note-editable div,
  body.dark-mode .note-editable h1,
  body.dark-mode .note-editable h2,
  body.dark-mode .note-editable h3,
  body.dark-mode .note-editable h4,
  body.dark-mode .note-editable h5,
  body.dark-mode .note-editable h6,
  body.dark-mode .note-editable td,
  body.dark-mode .note-editable th,
  body.dark-mode .note-editable tr,
  body.dark-mode .note-editable table,
  body.dark-mode .note-editable li,
  body.dark-mode .note-editable ul,
  body.dark-mode .note-editable ol,
  body.dark-mode .note-editable a,
  body.dark-mode .note-editable strong,
  body.dark-mode .note-editable b,
  body.dark-mode .note-editable i,
  body.dark-mode .note-editable em,
  body.dark-mode .note-editable u {
      color: #ffffff !important;
  }

  /* Override inline background colors on tables inside the Summernote editor */
  body.dark-mode .note-editable table {
      border-color: rgba(255, 255, 255, 0.15) !important;
  }
  body.dark-mode .note-editable td,
  body.dark-mode .note-editable th {
      border-color: rgba(255, 255, 255, 0.15) !important;
      background-color: transparent !important;
  }
  
  /* CTC Header Row: was style="background-color: #f2f2f2;" or "#f2f2f2" */
  body.dark-mode .note-editable tr[style*="f2f2f2"],
  body.dark-mode .note-editable tr[style*="f2f2f2"] td,
  body.dark-mode .note-editable td[style*="f2f2f2"] {
      background-color: #1e1e2d !important;
      color: #38bdf8 !important;
  }
  body.dark-mode .note-editable tr[style*="f2f2f2"] td strong,
  body.dark-mode .note-editable td[style*="f2f2f2"] strong {
      color: #38bdf8 !important;
  }

  /* Earnings, Statutory Benefit, Deductions Section Header Row: was style="background-color: #f9f9f9;" or "#f9f9f9" */
  body.dark-mode .note-editable tr[style*="f9f9f9"],
  body.dark-mode .note-editable tr[style*="f9f9f9"] td,
  body.dark-mode .note-editable td[style*="f9f9f9"] {
      background-color: rgba(255, 255, 255, 0.05) !important;
      color: #e2e8f0 !important;
  }
  body.dark-mode .note-editable tr[style*="f9f9f9"] td strong,
  body.dark-mode .note-editable td[style*="f9f9f9"] strong {
      color: #cbd5e1 !important;
  }

  /* Monthly Gross Row: was style="background-color: #e6f7ff;" (light blue) */
  body.dark-mode .note-editable tr[style*="e6f7ff"],
  body.dark-mode .note-editable tr[style*="e6f7ff"] td,
  body.dark-mode .note-editable td[style*="e6f7ff"] {
      background-color: rgba(14, 165, 233, 0.15) !important;
      color: #38bdf8 !important;
  }
  body.dark-mode .note-editable tr[style*="e6f7ff"] td strong,
  body.dark-mode .note-editable td[style*="e6f7ff"] strong {
      color: #38bdf8 !important;
  }

  /* Net Pay Row: was style="background-color: #d4edda;" (light green) */
  body.dark-mode .note-editable tr[style*="d4edda"],
  body.dark-mode .note-editable tr[style*="d4edda"] td,
  body.dark-mode .note-editable td[style*="d4edda"] {
      background-color: rgba(16, 185, 129, 0.15) !important;
      color: #34d399 !important;
  }
  body.dark-mode .note-editable tr[style*="d4edda"] td strong,
  body.dark-mode .note-editable td[style*="d4edda"] strong {
      color: #34d399 !important;
  }

  /* Offer Letter Doc Dark Mode Overrides */
  body.dark-mode .note-editable .offer-letter-doc {
      color: #ffffff !important;
  }
  body.dark-mode .note-editable .offer-letter-doc .company-info,
  body.dark-mode .note-editable .offer-letter-doc .footer-address,
  body.dark-mode .note-editable .offer-letter-doc p,
  body.dark-mode .note-editable .offer-letter-doc td,
  body.dark-mode .note-editable .offer-letter-doc th,
  body.dark-mode .note-editable .offer-letter-doc strong,
  body.dark-mode .note-editable .offer-letter-doc span {
      color: #ffffff !important;
  }
  body.dark-mode .note-editable .offer-letter-doc .letter-title {
      color: #38bdf8 !important;
  }
  body.dark-mode .note-editable .offer-letter-doc .salary-table th,
  body.dark-mode .note-editable .offer-letter-doc .salary-table td {
      border-color: rgba(255, 255, 255, 0.15) !important;
  }
  body.dark-mode .note-editable .sig-placeholder {
    color: #cbd5e1 !important;
    background: rgba(99, 102, 241, 0.12) !important;
    border-color: #818cf8 !important;
  }
  body.dark-mode .note-editable .sig-placeholder:hover {
    color: #e0e7ff !important;
    background: rgba(99, 102, 241, 0.22) !important;
  }

  body.dark-mode .note-editable .offer-letter-doc .salary-table .category-row,
  body.dark-mode .note-editable .offer-letter-doc .salary-table .total-row {
      background-color: rgba(14, 165, 233, 0.2) !important;
      color: #38bdf8 !important;
  }

  /* Summernote Dropdown Menus */
  body.dark-mode .note-dropdown-menu {
      background-color: #1e1e2d !important;
      border: 1px solid rgba(255, 255, 255, 0.08) !important;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4) !important;
  }
  body.dark-mode .note-dropdown-menu a.note-dropdown-item {
      color: #e2e8f0 !important;
  }
  body.dark-mode .note-dropdown-menu a.note-dropdown-item:hover {
      background-color: rgba(255, 255, 255, 0.05) !important;
      color: #ffffff !important;
  }

  /* Summernote Popovers */
  body.dark-mode .note-popover {
      background-color: #1e1e2d !important;
      border: 1px solid rgba(255, 255, 255, 0.08) !important;
  }
  body.dark-mode .note-popover .popover-content {
      background-color: #1e1e2d !important;
  }
  body.dark-mode .note-popover .popover-arrow::after {
      border-bottom-color: #1e1e2d !important;
      border-top-color: #1e1e2d !important;
  }

  /* Summernote Dialogs & Modals */
  body.dark-mode .note-modal .modal-content {
      background-color: #1e1e2d !important;
      color: #ffffff !important;
      border: 1px solid rgba(255, 255, 255, 0.08) !important;
  }
  body.dark-mode .note-modal .modal-header {
      border-bottom: 1px solid rgba(255, 255, 255, 0.08) !important;
  }
  body.dark-mode .note-modal .modal-footer {
      border-top: 1px solid rgba(255, 255, 255, 0.08) !important;
  }
  body.dark-mode .note-form-label {
      color: #cbd5e1 !important;
  }
  body.dark-mode .note-input {
      background-color: #12121a !important;
      color: #ffffff !important;
      border: 1px solid rgba(255, 255, 255, 0.1) !important;
  }

  /* Redesigned Overview section styling */
  .overview-card {
      background: #ffffff;
      border: 1px solid #e2e8f0;
      border-radius: 0;
      overflow: hidden;
      box-shadow: 0 1px 3px rgba(0,0,0,0.05);
      margin-bottom: 24px;
  }
  .overview-grid-3 {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 24px;
      padding: 24px;
  }
  .overview-grid-2 {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 24px;
      padding: 24px;
  }
  .overview-field {
      display: flex;
      flex-direction: column;
      gap: 6px;
  }
  .overview-label {
      font-size: 10px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      color: #64748b;
      margin: 0;
  }
  .overview-value {
      font-size: 14px;
      font-weight: 600;
      color: #1e293b;
      margin: 0;
      word-break: break-all;
  }
  .overview-password-label-row {
      display: flex;
      align-items: center;
      gap: 6px;
  }
  .overview-password-toggle {
      padding: 0;
      border: none;
      background: none;
      box-shadow: none;
      line-height: 1;
      color: #64748b;
      text-decoration: none;
  }
  .overview-password-toggle:hover {
      color: #334155;
  }
  body.dark-mode .overview-password-toggle,
  html.dark-mode .overview-password-toggle {
      color: #9a9a9a !important;
  }
  body.dark-mode .overview-password-toggle:hover,
  html.dark-mode .overview-password-toggle:hover {
      color: #d4d4d4 !important;
  }
  .btn-drawer-edit {
      background-color: #318e8e !important;
      border-radius: 8px !important;
      padding: 14px !important;
      font-size: 15px !important;
      font-weight: 600 !important;
      color: white !important;
      display: flex !important;
      align-items: center !important;
      justify-content: center !important;
      gap: 8px !important;
      transition: all 0.2s ease-in-out !important;
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1) !important;
      max-width: 448px !important;
      margin: 0 auto !important;
      width: 100% !important;
  }
  .btn-drawer-edit:hover {
      background-color: #287575 !important;
  }
  .btn-drawer-edit:active {
      transform: scale(0.98) !important;
  }

  /* Responsive styling for tablet/mobile */
  @media (max-width: 768px) {
      .overview-grid-3 {
          grid-template-columns: repeat(2, 1fr);
      }
  }
  @media (max-width: 480px) {
      .overview-grid-3, .overview-grid-2 {
          grid-template-columns: 1fr;
      }
  }

  /* User 360 drawer — neutral dark mode (no teal / no blue tint) */
  body.dark-mode .profile-drawer,
  html.dark-mode .profile-drawer {
      background: #1a1a1a !important;
      border: 1px solid #3d3d3d !important;
      box-shadow: 0 25px 50px rgba(0, 0, 0, 0.55) !important;
      color: #e8e8e8 !important;
      backdrop-filter: none !important;
  }
  body.dark-mode .profile-drawer .drawer-header,
  html.dark-mode .profile-drawer .drawer-header {
      background: #242424 !important;
      border-bottom: 1px solid #3d3d3d !important;
      color: #f5f5f5 !important;
  }
  body.dark-mode .profile-drawer .drawer-avatar,
  html.dark-mode .profile-drawer .drawer-avatar {
      background: rgba(255, 255, 255, 0.08) !important;
      border-color: rgba(255, 255, 255, 0.15) !important;
      color: #e8e8e8 !important;
  }
  body.dark-mode .profile-drawer .drawer-tabs,
  html.dark-mode .profile-drawer .drawer-tabs {
      background: #1a1a1a !important;
      border-bottom: 1px solid #3d3d3d !important;
  }
  body.dark-mode .profile-drawer .drawer-tab,
  html.dark-mode .profile-drawer .drawer-tab {
      color: #9a9a9a !important;
  }
  body.dark-mode .profile-drawer .drawer-tab:hover,
  html.dark-mode .profile-drawer .drawer-tab:hover {
      color: #d4d4d4 !important;
  }
  body.dark-mode .profile-drawer .drawer-tab.active,
  html.dark-mode .profile-drawer .drawer-tab.active {
      color: #f5f5f5 !important;
      border-bottom-color: #f5f5f5 !important;
  }
  body.dark-mode .profile-drawer .drawer-body,
  html.dark-mode .profile-drawer .drawer-body {
      background: #1a1a1a !important;
  }
  body.dark-mode .profile-drawer .overview-card,
  html.dark-mode .profile-drawer .overview-card {
      background: #242424 !important;
      border-color: #3d3d3d !important;
      border-radius: 0 !important;
      box-shadow: none !important;
  }
  body.dark-mode .profile-drawer .overview-label,
  html.dark-mode .profile-drawer .overview-label {
      color: #9a9a9a !important;
  }
  body.dark-mode .profile-drawer .overview-value,
  html.dark-mode .profile-drawer .overview-value {
      color: #f5f5f5 !important;
  }
  body.dark-mode .profile-drawer h5,
  html.dark-mode .profile-drawer h5 {
      color: #f5f5f5 !important;
  }
  body.dark-mode .profile-drawer .info-box,
  html.dark-mode .profile-drawer .info-box {
      background: #242424 !important;
      border-color: #3d3d3d !important;
  }
  body.dark-mode .profile-drawer .btn-drawer-edit,
  html.dark-mode .profile-drawer .btn-drawer-edit {
      background-color: #404040 !important;
      color: #f5f5f5 !important;
      border: 1px solid #525252 !important;
      box-shadow: none !important;
  }
  body.dark-mode .profile-drawer .btn-drawer-edit:hover,
  html.dark-mode .profile-drawer .btn-drawer-edit:hover {
      background-color: #525252 !important;
      color: #ffffff !important;
  }
  body.dark-mode .profile-drawer .close-drawer,
  html.dark-mode .profile-drawer .close-drawer {
      color: #f5f5f5 !important;
      opacity: 0.85 !important;
  }
  body.dark-mode .profile-drawer .close-drawer:hover,
  html.dark-mode .profile-drawer .close-drawer:hover {
      opacity: 1 !important;
  }

  @media (min-width: 769px) {
    .usr-mobile-bottom-nav {
      display: none !important;
    }

    /* Tighter vertical spacing â€” match Leave Management */
    .users-page .container-fluid {
      padding-top: 6px !important;
    }

    .users-page .summary-wrapper,
    .users-page .summary-wrapper.pt-1,
    .users-page .summary-wrapper.mb-2 {
      padding-top: 0 !important;
      margin-bottom: 8px !important;
    }

    .users-page .summary-section {
      padding: 4px 5px !important;
    }

    .users-page .users-controls-card {
      padding: 0 !important;
      margin-top: 0 !important;
      margin-bottom: 8px !important;
    }

    .users-page .users-controls-card .control-bar {
      margin-top: 0 !important;
      margin-bottom: 0 !important;
    }

    .users-page .user-table-container {
      margin-top: 0 !important;
      padding-top: 0 !important;
    }

    .users-page .user-data-table {
      border-spacing: 0 10px !important;
    }
  }

  /* Dark mode â€” employees search bar: no white/light border (overrides Users.css / style_dashboard) */
  body.dark-mode .users-page .users-controls-card .filter-group-search .search-input,
  body.dark-mode .users-page .users-controls-card #usersToolbar .search-input,
  body.dark-mode .users-page .search-input,
  body.dark-mode .users-page input#tableSearchInput {
      border: none !important;
      border-color: transparent !important;
      outline: none !important;
      box-shadow: none !important;
  }

  body.dark-mode .users-page .users-controls-card .filter-group-search .search-input:focus,
  body.dark-mode .users-page input#tableSearchInput:focus {
      border: none !important;
      border-color: transparent !important;
      outline: none !important;
      box-shadow: none !important;
  }

  /* Dark mode â€” table header lighter than row cards (match Attendance Report).
     Must target #example: unified_table_styles uses #example with higher specificity than .user-data-table alone. */
  body.dark-mode .users-page table#example.user-data-table.unified-table thead th,
  body.dark-mode .users-page #example.user-data-table thead th {
      background: #2c2c2e !important;
      color: rgba(255, 255, 255, 0.65) !important;
      border-bottom: 1px solid rgba(255, 255, 255, 0.12) !important;
      box-shadow: none !important;
      border-radius: 0 !important;
  }
  body.dark-mode .users-page table#example.user-data-table.unified-table tbody tr,
  body.dark-mode .users-page #example.user-data-table tbody tr.user-data-row,
  body.dark-mode .users-page #example tbody tr {
      background: rgba(44, 44, 46, 0.4) !important;
      border: 1px solid rgba(255, 255, 255, 0.12) !important;
      box-shadow: none !important;
  }
  body.dark-mode .users-page table#example.user-data-table.unified-table tbody tr:hover,
  body.dark-mode .users-page #example.user-data-table tbody tr.user-data-row:hover,
  body.dark-mode .users-page #example tbody tr:hover {
      background: rgba(255, 255, 255, 0.06) !important;
      border-color: rgba(255, 255, 255, 0.15) !important;
  }
  body.dark-mode .users-page table#example.user-data-table tbody td,
  body.dark-mode .users-page #example.user-data-table tbody td,
  body.dark-mode .users-page #example tbody td {
      background: transparent !important;
      color: rgba(255, 255, 255, 0.85) !important;
  }

  /* Dark mode â€” rows limit selector (match Leave Management) */
  .users-page .users-controls-card .page-size-selector::after,
  .users-page .users-controls-card .page-size-selector::before {
      content: none !important;
      display: none !important;
  }
  body.dark-mode .users-page .users-controls-card .page-size-selector {
      display: inline-flex !important;
      align-items: center !important;
      justify-content: center !important;
      min-width: 64px !important;
      max-width: 76px !important;
      padding: 8px 10px !important;
      background: rgba(255, 255, 255, 0.05) !important;
      border: 1px solid rgba(255, 255, 255, 0.1) !important;
      box-sizing: border-box !important;
      box-shadow: none !important;
      overflow: hidden !important;
  }
  body.dark-mode .users-page .users-controls-card .page-size-selector select.users-limit-select,
  body.dark-mode .users-page .users-controls-card .page-size-selector #users-limit {
      color: #ffffff !important;
      background-color: transparent !important;
      background-image: none !important;
      border: 0 !important;
      box-shadow: none !important;
      outline: none !important;
      backdrop-filter: none !important;
      -webkit-backdrop-filter: none !important;
      -webkit-appearance: menulist !important;
      appearance: menulist !important;
      font-size: 14px !important;
      font-weight: 500 !important;
      width: 100% !important;
      text-align: center !important;
      cursor: pointer !important;
  }
  body.dark-mode .users-page .users-controls-card .page-size-selector:focus-within {
      border-color: #2a8c90 !important;
      box-shadow: 0 0 0 2px rgba(42, 140, 144, 0.2) !important;
  }
  body.dark-mode .users-page .users-controls-card .page-size-selector select option {
      background-color: #1e1e24 !important;
      color: #ffffff !important;
  }
</style>
<link rel="stylesheet" href="./assets/css/mobile_list_top_chrome.css?v=<?php echo time(); ?>" />
<?php include('header.php'); ?>
<div class="content users-page">
  <div class="container-fluid">
    <div class="row">
      <div class="col-12">
        <div class="summary-wrapper pt-1 mb-2">
          <button type="button" class="summary-arrow left" id="summaryLeft" aria-label="Scroll summary left"><i class="bi bi-chevron-left"></i></button>
          <div class="summary-section" id="summaryScroll">
              <div class="summary-card stat-card-headcount">
                  <span class="summary-text" style="font-weight: 600;">Active Employees : <span id="activeusers">0</span></span>
              </div>
              <div class="summary-card stat-card-absent">
                  <span class="summary-text" style="font-weight: 600;">Inactive Employees : <span id="deactiveusers">0</span></span>
              </div>
              <div class="summary-card stat-card-present">
                  <span class="summary-text" style="font-weight: 600;">Assigned Employees : <span id="assignednuser">0</span></span>
              </div>
              <div class="summary-card stat-card-late">
                  <span class="summary-text" style="font-weight: 600;">Total Salary : <span id="totalsalary">0</span></span>
              </div>
          </div>
          <button type="button" class="summary-arrow right" id="summaryRight" aria-label="Scroll summary right"><i class="bi bi-chevron-right"></i></button>
        </div>
        <div class="users-controls-card">
        <div class="control-bar d-flex justify-content-between align-items-center flex-wrap gap-3">
          <div class="control-left"></div>
          <div class="control-right d-flex align-items-center justify-content-end gap-2 flex-grow-1 header-tools-wrapper">
            <div id="usersToolbar">
                <div class="filter-group filter-group-search">
                    <div class="search-box">
                      <svg class="search-icon" width="18" height="18" viewBox="0 0 18 18" fill="none" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); pointer-events: none; z-index: 1;">
                        <circle cx="8" cy="8" r="6" stroke="#999" stroke-width="1.5"/>
                        <path d="M12.5 12.5L16 16" stroke="#999" stroke-width="1.5" stroke-linecap="round"/>
                      </svg>
                      <input type="text" class="search-input" id="tableSearchInput" placeholder="Search users..." style="padding-left: 44px;">
                    </div>
                </div>
                <div class="filter-group filter-group-actions">
                <button class="btn-new-user" onclick="openAddNewUserModal()">
                    <i class="bi bi-person-plus-fill"></i> <span class="btn-text">New Application</span>
                </button>
                <button class="btn-filter" id="openFilterBtn">
                   <i class="bi bi-filter"></i> <span class="btn-text">Filters</span>
                </button>
                <div class="column-visibility-wrapper">
                    <button class="btn-column-visibility" id="columnVisibilityBtn">
                        <i class="bi bi-layout-three-columns"></i> <span class="btn-text">Column Visibility</span>
                    </button>
                    <div class="column-dropdown" id="columnDropdown">
                        <label><input type="checkbox" data-col="1"> ID</label>
                        <label><input type="checkbox" data-col="2"> Status</label>
                        <label><input type="checkbox" data-col="3"> Name</label>
                        <label><input type="checkbox" data-col="4"> Email</label>
                        <label><input type="checkbox" data-col="5"> Contact</label>
                        <label><input type="checkbox" data-col="6"> Password</label>
                        <label><input type="checkbox" data-col="7"> Monthly CTC</label>
                        <label><input type="checkbox" data-col="8"> DOJ</label>
                        <label><input type="checkbox" data-col="9"> DOB</label>
                        <label><input type="checkbox" data-col="10"> Unique ID</label>
                        <label><input type="checkbox" data-col="11"> Employee ID</label>
                        <label><input type="checkbox" data-col="12"> Basic Salary</label>
                        <label><input type="checkbox" data-col="13"> HRA</label>
                        <label><input type="checkbox" data-col="14"> Conveyance</label>
                        <label><input type="checkbox" data-col="15"> Special Allowance</label>
                        <label><input type="checkbox" data-col="16"> PF (Employer)</label>
                        <label><input type="checkbox" data-col="17"> Deductions</label>
                        <label><input type="checkbox" data-col="18"> Project Name</label>
                        <label><input type="checkbox" data-col="19"> Project Type</label>
                        <label><input type="checkbox" data-col="20"> City</label>
                        <label><input type="checkbox" data-col="21"> Role Type</label>
                        <label><input type="checkbox" data-col="22"> Assign User</label>
                        <label><input type="checkbox" data-col="23"> Created At</label>
                        <label><input type="checkbox" data-col="24"> Inactive At</label>
                        <label><input type="checkbox" data-col="25"> Action</label>
                    </div>
                </div>
                <div class="page-size-selector">
                    <select id="users-limit" class="users-limit-select">
                        <?php foreach ($allowedLimits as $v): ?>
                            <option value="<?php echo $v; ?>" <?php echo $recordsPerPage === $v ? 'selected' : ''; ?>><?php echo $v; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                </div>
            </div>
          </div>
        </div>
        </div>
        <div class="table-wrap">
          <div class="user-table-container">
            <div class="user-table-scroll-wrapper">
              <table id="example" class="user-data-table unified-table">
                <thead>
                  <tr>
                    <th class="checkbox-col"><input type="checkbox" id="selectAll"></th>
                    <th>ID</th><th>Status</th><th>Name</th><th>Email</th><th>Contact</th><th>Password</th>
                    <th>Monthly CTC</th><th>DOJ</th><th>DOB</th><th>Unique ID</th><th>Employee ID</th>
                    <th>Basic</th><th>HRA</th><th>Conveyance</th><th>Special</th><th>PF (Emp)</th><th>Deds</th>
                    <th>Project Name</th><th>Project Type</th><th>City</th><th>Role Type</th><th>Assign User</th>
                    <th>Created At</th><th>Inactive At</th><th>Action</th>
                  </tr>
                </thead>
                <tbody id="incentiveuser">
                  </tbody>
              </table>
            </div>
          </div>
        </div>
        <div class="pagination-section">
          <div class="pagination-info">
            Showing <span id="showingStart">0</span> to <span id="showingEnd">0</span> of <span id="totalEntries">0</span> entries
          </div>
          <div class="pagination-controls">
            <button class="page-btn" id="prevPageBtn" aria-label="Previous page"><i class="bi bi-chevron-left"></i></button>
            <button class="page-btn active" id="currentPageBtn">1</button>
            <button class="page-btn" id="nextPageBtn" aria-label="Next page"><i class="bi bi-chevron-right"></i></button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Mobile bottom toolbar (Attendance Report pattern) -->
<nav class="usr-mobile-bottom-nav d-md-none" aria-label="Employee actions">
    <button type="button" class="usr-mobile-nav-btn usr-mobile-filter-btn" id="usrMobileFilterBtn">
        <i class="bi bi-funnel-fill"></i>
        <span>Filter</span>
    </button>
    <button type="button" class="usr-mobile-nav-btn usr-mobile-add-btn" id="usrMobileAddBtn">
        <i class="bi bi-person-plus-fill"></i>
        <span>Add</span>
    </button>
    <button type="button" class="usr-mobile-nav-btn usr-mobile-columns-btn" id="usrMobileColumnsBtn">
        <i class="bi bi-layout-three-columns"></i>
        <span>Columns</span>
    </button>
</nav>

<?php include('add_user_modal.php'); ?>
<?php include('filter_modal.php'); ?>

<!-- FNF Modal -->
<div class="modal fade" id="fnfModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header d-flex justify-content-between align-items-center" style="background: linear-gradient(135deg, #1e6063 0%, #2a8c90 100%); color: white; border-radius: 20px 20px 0 0; padding: 20px 24px; border: none;">
        <div class="d-flex align-items-center gap-3">
            <div class="application-icon" style="width: 42px; height: 42px; background: rgba(255, 255, 255, 0.15); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px;">
                <i class="bi bi-file-earmark-check"></i>
            </div>
            <div>
                <h5 class="modal-title fw-bold mb-0" style="font-size: 1.1rem;">FNF Settlement</h5>
            </div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" style="padding: 24px 32px;">
        <div class="mb-4">
            <span class="fnf-label" style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 4px; display: block;">Employee Name</span>
            <h4 id="fnf_employee_name" class="mb-0" style="font-weight: 800; color: #1e293b; font-size: 1.5rem;">â€”</h4>
        </div>

        <form id="fnfForm">
            <input type="hidden" id="fnf_user_id">
            <input type="hidden" id="fnf_net_settlement">
            <input type="hidden" id="fnf_assets_returned">
            <input type="hidden" id="fnf_last_working_day">
            <input type="hidden" id="fnf_status">

            <!-- Exit Documents Section -->
            <div class="exit-documents-container mt-2">
                <span class="fnf-label mb-3 d-block" style="color: #2a8c90; font-size: 0.85rem;"><i class="bi bi-file-earmark-pdf me-1"></i> Exit Documents</span>
                
                <!-- No Dues Certificate -->
                <div class="mb-3">
                    <h6 class="mb-2" style="font-weight: 700; font-size: 0.95rem; color: #1e293b;">No Dues Certificate</h6>
                    <div class="info-box d-flex justify-content-between align-items-center p-3 rounded-3 border" style="background: #f8fafc; border-color: #e2e8f0; border-left: 4px solid #2a8c90 !important;">
                        <div>
                            <div class="info-label" style="font-size: 10px; text-transform: uppercase; color: #64748b; font-weight: 700; margin-bottom: 4px;">Status</div>
                            <div class="info-value text-muted" id="fnf_no_dues_status" style="font-size: 13px; font-weight: 600;">Not Generated</div>
                        </div>
                        <div class="d-flex gap-1">
                            <button type="button" class="btn btn-sm btn-teal" onclick="openLetterModal('no_dues_certificate')" title="Generate / Edit" style="font-size: 11px; padding: 6px 12px; border-radius: 8px;">
                                <i class="bi bi-pencil-square me-1"></i> <span class="d-none d-md-inline">Generate / Edit</span>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-teal" onclick="mailLetter('no_dues_certificate')" id="btn_fnf_mail_no_dues" style="display:none; font-size: 11px; padding: 6px 12px; border-radius: 8px;" title="Mail">
                                <i class="bi bi-envelope me-1"></i> <span class="d-none d-md-inline">Mail</span>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-teal" onclick="previewLetter('no_dues_certificate')" id="btn_fnf_preview_no_dues" style="display:none; font-size: 11px; padding: 6px 12px; border-radius: 8px;" title="Preview final output">
                                <i class="bi bi-eye me-1"></i> <span class="d-none d-md-inline">Preview</span>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-teal" onclick="printLetter('no_dues_certificate')" id="btn_fnf_print_no_dues" style="display:none; font-size: 11px; padding: 6px 12px; border-radius: 8px;" title="Print">
                                <i class="bi bi-printer me-1"></i> <span class="d-none d-md-inline">Print</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Relieving Letter -->
                <div class="mb-3">
                    <h6 class="mb-2" style="font-weight: 700; font-size: 0.95rem; color: #1e293b;">Relieving Letter</h6>
                    <div class="info-box d-flex justify-content-between align-items-center p-3 rounded-3 border" style="background: #f8fafc; border-color: #e2e8f0; border-left: 4px solid #2a8c90 !important;">
                        <div>
                            <div class="info-label" style="font-size: 10px; text-transform: uppercase; color: #64748b; font-weight: 700; margin-bottom: 4px;">Status</div>
                            <div class="info-value text-muted" id="fnf_relieving_status" style="font-size: 13px; font-weight: 600;">Not Generated</div>
                        </div>
                        <div class="d-flex gap-1">
                            <button type="button" class="btn btn-sm btn-teal" onclick="openLetterModal('relieving_letter')" title="Generate / Edit" style="font-size: 11px; padding: 6px 12px; border-radius: 8px;">
                                <i class="bi bi-pencil-square me-1"></i> <span class="d-none d-md-inline">Generate / Edit</span>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-teal" onclick="mailLetter('relieving_letter')" id="btn_fnf_mail_relieving" style="display:none; font-size: 11px; padding: 6px 12px; border-radius: 8px;" title="Mail">
                                <i class="bi bi-envelope me-1"></i> <span class="d-none d-md-inline">Mail</span>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-teal" onclick="previewLetter('relieving_letter')" id="btn_fnf_preview_relieving" style="display:none; font-size: 11px; padding: 6px 12px; border-radius: 8px;" title="Preview final output">
                                <i class="bi bi-eye me-1"></i> <span class="d-none d-md-inline">Preview</span>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-teal" onclick="printLetter('relieving_letter')" id="btn_fnf_print_relieving" style="display:none; font-size: 11px; padding: 6px 12px; border-radius: 8px;" title="Print">
                                <i class="bi bi-printer me-1"></i> <span class="d-none d-md-inline">Print</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Past Payslips Section -->
                <div class="mt-4">
                    <h6 class="mb-2" style="font-weight: 700; font-size: 0.95rem; color: #1e293b;">Past Payslips</h6>
                    <div class="info-box p-0 rounded-3 border overflow-hidden">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0" style="font-size: 0.85rem; vertical-align: middle;">
                                <thead>
                                    <tr style="background: #f8fafc;">
                                        <th class="ps-3 py-2" style="color: #475569; font-weight: 700;">Month</th>
                                        <th class="py-2" style="color: #475569; font-weight: 700;">Payout</th>
                                        <th class="text-center py-2" style="color: #475569; font-weight: 700;">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="fnf_payslips_body">
                                    <tr><td colspan="3" class="text-center py-3 text-muted">Loading payslips...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-4 d-flex gap-2 fnf-footer-actions">
                <button type="button" class="btn btn-outline-teal w-50 py-2.5" onclick="saveFnf()" style="border-radius: 10px;">
                    Save Details
                </button>
                <button type="button" class="btn btn-teal w-50 py-2.5" onclick="sendFinalFnfMail()" id="btn_send_final_fnf_mail" style="border-radius: 10px;">
                    <i class="bi bi-send-check me-1"></i> Send Final Mail
                </button>
            </div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php
$__letter_editor_modals = __DIR__ . '/includes/letter_editor_modals.php';
if (is_readable($__letter_editor_modals)) {
    include $__letter_editor_modals;
}
?>
<!-- jQuery, Bootstrap, Summernote loaded via htmlopen.php -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script src="hrmain.js?v=<?= time(); ?>"></script>
<script src="assets/js/summary-cards.js?v=<?= time(); ?>"></script>
<script>
// --- 2) Search and Debounce Logic Restored ---
function debounce(e,t){ let timer; return function(...args){ clearTimeout(timer); timer = setTimeout(() => e.apply(this,args), t); } }
function searchTable() {
    if (typeof applyFilters === "function") {
        applyFilters();
    } else {
        const searchValue = document.getElementById("tableSearchInput").value.toLowerCase().trim();
        const rows = document.querySelectorAll("#incentiveuser tr.user-data-row");
        rows.forEach(row => {
            const cells = row.querySelectorAll("td");
            const searchableText = `${cells[1]?.innerText ?? ""} ${cells[2]?.innerText ?? ""} ${cells[3]?.innerText ?? ""} ${cells[4]?.innerText ?? ""} ${cells[5]?.innerText ?? ""}`.toLowerCase();
            row.style.display = searchableText.includes(searchValue) ? "" : "none";
            const expandRow = row.nextElementSibling;
            if (expandRow?.classList.contains("user-expand-row")) { expandRow.style.display = "none"; }
        });
        window.currentUsersPage = 1;
        if(typeof updateUsersPagination === "function") updateUsersPagination();
    }
}
document.getElementById("tableSearchInput")?.addEventListener("input", debounce(searchTable, 300));
// --- 3) Column Visibility Toggle Restored ---
document.addEventListener("DOMContentLoaded", function () {
    const dropdown = document.getElementById("columnDropdown");
    const table = document.querySelector(".user-data-table");
    const columnBtn = document.getElementById("columnVisibilityBtn");
    const usrMobileColumnsBtn = document.getElementById("usrMobileColumnsBtn");
    const usrMobileFilterBtn = document.getElementById("usrMobileFilterBtn");
    const usrMobileAddBtn = document.getElementById("usrMobileAddBtn");
    if (!dropdown || !table) return;
    
    const checkboxes = dropdown.querySelectorAll("input[type='checkbox']");

    function portalMobileColumnDropdown() {
        if (window.innerWidth > 768 || dropdown.dataset.portaled === '1') return;
        document.body.appendChild(dropdown);
        dropdown.classList.add('usr-mobile-column-dropdown');
        dropdown.dataset.portaled = '1';
    }

    function toggleMobileColumnDropdown(forceClose = false) {
        portalMobileColumnDropdown();
        if (forceClose) {
            dropdown.classList.remove('show');
            usrMobileColumnsBtn?.classList.remove('active');
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
        usrMobileColumnsBtn?.classList.toggle('active', dropdown.classList.contains('show'));
    }

    if (columnBtn) { 
        columnBtn.addEventListener("click", function (e) { 
            e.stopPropagation();
            if (window.innerWidth <= 768) {
                toggleMobileColumnDropdown();
            } else {
                dropdown.classList.toggle("show");
            }
        }); 
    }

    usrMobileColumnsBtn?.addEventListener("click", (e) => {
        e.stopPropagation();
        toggleMobileColumnDropdown();
    });

    usrMobileFilterBtn?.addEventListener("click", () => {
        document.getElementById("openFilterBtn")?.click();
    });

    usrMobileAddBtn?.addEventListener("click", () => {
        if (typeof openAddNewUserModal === "function") {
            openAddNewUserModal();
        }
    });
    
    document.addEventListener("click", function (e) {
        if (e.target.closest('#columnDropdown') || e.target.closest('#usrMobileColumnsBtn') || e.target.closest('#columnVisibilityBtn')) {
            return;
        }
        dropdown.classList.remove("show");
        usrMobileColumnsBtn?.classList.remove('active');
    });
    dropdown.addEventListener("click", function (e) { e.stopPropagation(); });
    const COLUMN_PREFS_KEY = 'hrlogin_users_column_visibility';

    function toggleColumn(index, show) {
        table.querySelectorAll(`thead tr th:nth-child(${index + 1}), tbody tr td:nth-child(${index + 1})`).forEach(cell => {
            cell.style.display = show ? "table-cell" : "none";
        });
    }

    function getResponsiveDefaultCols() {
        const width = window.innerWidth;
        if (width >= 1440) return [1, 2, 3, 4, 5, 6, 7, 9, 25];
        if (width > 1024) return [1, 2, 3, 4, 5, 8, 25];
        if (width > 768) return [1, 2, 3, 4, 5, 25];
        if (width > 480) return [1, 2, 3, 25];
        return [2, 3, 25];
    }

    function loadColumnPreferences() {
        try {
            const raw = localStorage.getItem(COLUMN_PREFS_KEY);
            if (!raw) return null;
            const parsed = JSON.parse(raw);
            if (!Array.isArray(parsed)) return null;
            const validCols = new Set(
                Array.from(checkboxes).map(cb => parseInt(cb.dataset.col, 10))
            );
            const cols = parsed
                .map(n => parseInt(n, 10))
                .filter(n => !isNaN(n) && validCols.has(n));
            return cols.length ? cols : null;
        } catch (e) {
            return null;
        }
    }

    function saveColumnPreferences() {
        const visible = [];
        checkboxes.forEach(cb => {
            if (cb.checked) visible.push(parseInt(cb.dataset.col, 10));
        });
        localStorage.setItem(COLUMN_PREFS_KEY, JSON.stringify(visible));
    }

    function applyColumnVisibility(colsToShow) {
        const showSet = new Set(colsToShow);
        checkboxes.forEach(cb => {
            const col = parseInt(cb.dataset.col, 10);
            const shouldShow = showSet.has(col);
            cb.checked = shouldShow;
            toggleColumn(col, shouldShow);
        });
        table.style.width = "max-content";
    }

    checkboxes.forEach(cb => {
        cb.addEventListener("change", function () {
            toggleColumn(parseInt(this.dataset.col, 10), this.checked);
            saveColumnPreferences();
        });
    });

    window.applyDefaults = function () {
        const saved = loadColumnPreferences();
        const cols = saved || getResponsiveDefaultCols();
        applyColumnVisibility(cols);
    };
    
    let resizeTimer;
    window.addEventListener("resize", () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => { 
            window.applyDefaults(); 
            if (window.innerWidth >= 1025) dropdown.classList.remove("show"); 
        }, 180);
    });
    applyDefaults();
});
</script>
<script src="assets/js/summary-cards.js?v=<?= time(); ?>"></script>
<button id="floatingClearFilters" class="floating-clear-btn">
    <i class="bi bi-x-circle"></i>
    Clear Filters
</button>

<!-- User 360 Profile Drawer -->
<div class="profile-drawer-overlay" id="profileDrawerOverlay" onclick="closeUserProfileDrawer()"></div>
<div class="profile-drawer" id="profileDrawer">
    <div class="drawer-header">
        <div class="drawer-header-content">
            <div class="drawer-avatar">
                <i class="bi bi-person"></i>
            </div>
            <div class="drawer-title">
                <h3 id="drawer_user_name">John Doe</h3>
                <!-- Activation Toggle -->
                <div class="d-flex align-items-center mt-2">
                    <span class="me-2 fw-bold" style="font-size: 0.85rem;">Status:</span>
                    <label class="theme-switch">
                        <input type="checkbox" id="userActivationToggle" onchange="toggleUserActivation()">
                        <span class="slider"></span>
                    </label>
                    <span id="drawer_status_text" class="ms-2 fw-bold" style="font-size: 0.85rem;">Inactive</span>
                </div>
            </div>
        </div>
        <button class="close-drawer" onclick="closeUserProfileDrawer()"><i class="bi bi-x-lg"></i></button>
    </div>
    
    <div class="drawer-tabs">
        <div class="drawer-tab active" onclick="switchDrawerTab('overview')">Overview</div>
        <div class="drawer-tab" onclick="switchDrawerTab('attendance')">Attendance</div>
        <div class="drawer-tab" onclick="switchDrawerTab('payroll')">Payroll</div>
        <div class="drawer-tab" onclick="switchDrawerTab('assets')">Assets & Docs</div>
    </div>

    <div class="drawer-body">
        <!-- Overview Tab -->
        <div class="drawer-section active" id="drawer_tab_overview">
            <h5 class="mb-3" style="font-weight: 700;">Basic Information</h5>
            <div class="overview-card shadow-sm">
                <div class="overview-grid-3">
                    <!-- Email -->
                    <div class="overview-field">
                        <span class="overview-label">Email</span>
                        <p class="overview-value" id="drawer_email">---</p>
                    </div>
                    <!-- Contact No -->
                    <div class="overview-field">
                        <span class="overview-label">Contact No</span>
                        <p class="overview-value" id="drawer_contact">---</p>
                    </div>
                    <!-- Date of Birth -->
                    <div class="overview-field">
                        <span class="overview-label">Date of Birth</span>
                        <p class="overview-value" id="drawer_dob">---</p>
                    </div>
                    <!-- Unique ID -->
                    <div class="overview-field">
                        <span class="overview-label">Unique ID</span>
                        <p class="overview-value" id="drawer_unique_id">---</p>
                    </div>
                    <!-- Employee ID -->
                    <div class="overview-field">
                        <span class="overview-label">Employee ID</span>
                        <p class="overview-value" id="drawer_employee_id">---</p>
                    </div>
                    <!-- Password -->
                    <div class="overview-field">
                        <div class="overview-password-label-row">
                            <span class="overview-label">Password</span>
                            <button type="button" class="overview-password-toggle" onclick="toggleDrawerPasswordVisibility()" aria-label="Show password">
                                <i class="bi bi-eye" id="drawer_password_icon"></i>
                            </button>
                        </div>
                        <p class="overview-value" id="drawer_password">••••••••</p>
                    </div>
                    <!-- Date of Joining -->
                    <div class="overview-field">
                        <span class="overview-label">Date of Joining</span>
                        <p class="overview-value" id="drawer_doj">---</p>
                    </div>
                    <!-- Monthly CTC -->
                    <div class="overview-field">
                        <span class="overview-label">Monthly CTC</span>
                        <p class="overview-value" id="drawer_salary">---</p>
                    </div>
                    <!-- Created At -->
                    <div class="overview-field" id="drawer_created_at_container">
                        <span class="overview-label">Created At</span>
                        <p class="overview-value" id="drawer_created_at">---</p>
                    </div>
                    <!-- Inactive At -->
                    <div class="overview-field" id="drawer_inactive_at_container" style="display: none;">
                        <span class="overview-label">Inactive At</span>
                        <p class="overview-value" id="drawer_inactive_at">---</p>
                    </div>
                </div>
            </div>

            <h5 class="mb-3 mt-4" style="font-weight: 700;">Project Details</h5>
            <div class="overview-card shadow-sm">
                <div class="overview-grid-2">
                    <!-- Project Name -->
                    <div class="overview-field">
                        <span class="overview-label">Project Name</span>
                        <p class="overview-value" id="drawer_project">---</p>
                    </div>
                    <!-- Project Type -->
                    <div class="overview-field">
                        <span class="overview-label">Project Type</span>
                        <p class="overview-value" id="drawer_project_type">---</p>
                    </div>
                    <!-- City -->
                    <div class="overview-field">
                        <span class="overview-label">City</span>
                        <p class="overview-value" id="drawer_city">---</p>
                    </div>
                    <!-- Role Type -->
                    <div class="overview-field">
                        <span class="overview-label">Role Type</span>
                        <p class="overview-value" id="drawer_role">---</p>
                    </div>
                    <!-- Assigned Users -->
                    <div class="overview-field">
                        <span class="overview-label">Assigned Users</span>
                        <p class="overview-value" id="drawer_assigned_users">---</p>
                    </div>
                </div>
            </div>

            <h5 class="mb-3 mt-4" style="font-weight: 700;"><i class="bi bi-wallet2 me-2"></i> Salary Structure (CTC)</h5>
            <div id="overview_salary_structure_container" class="overview-card shadow-sm mb-4">
                <div class="text-center p-4 text-muted">
                    <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                    Calculating structure...
                </div>
            </div>

            <div class="drawer-actions">
                <button class="btn-drawer-edit" id="drawerEditBtn" onclick="openEditModalFromDrawer()"><i class="bi bi-pencil-square"></i> Edit Details</button>
            </div>
            
            <div class="mt-4 pt-3" style="border-top: 1px dashed var(--table-border);">
                <h6 style="color: #8b5cf6; font-weight: 700;"><i class="bi bi-lightbulb"></i> HR Suggestions</h6>
                <ul style="font-size: 0.9rem; color: var(--text-muted); padding-left: 20px; margin-top: 10px;">
                    <li><strong>Activity Timeline:</strong> Track when this user logged in, completed tasks, or applied for leave.</li>
                    <li><strong>Performance Notes:</strong> Add a private section here to drop notes about performance or warnings.</li>
                </ul>
            </div>
        </div>

        <!-- Attendance Tab -->
        <div class="drawer-section" id="drawer_tab_attendance">
            <h5 class="mb-3" style="font-weight: 700;">This Month's Attendance</h5>
            <div class="drawer-grid">
                <div class="info-box" style="border-left: 4px solid #10b981;">
                    <div class="info-label">Days Present</div>
                    <div class="info-value" id="drawer_att_present">0</div>
                </div>
                <div class="info-box" style="border-left: 4px solid #ef4444;">
                    <div class="info-label">Days Absent</div>
                    <div class="info-value" id="drawer_att_absent">0</div>
                </div>
                <div class="info-box" style="border-left: 4px solid #f59e0b;">
                    <div class="info-label">Days Late</div>
                    <div class="info-value" id="drawer_att_late">0</div>
                </div>
                <div class="info-box" style="border-left: 4px solid #3b82f6;">
                    <div class="info-label">Leaves Taken</div>
                    <div class="info-value" id="drawer_att_leave">0</div>
                </div>
            </div>
            
            <div class="attendance-calendar-wrapper mt-4">
                <div class="calendar-nav-header d-flex justify-content-between align-items-center mb-4">
                    <button class="btn btn-calendar-nav" onclick="changeCalendarMonth(-1)">
                        <i class="bi bi-chevron-left me-1"></i> Prev
                    </button>
                    <h5 class="mb-0 fw-bold text-center" id="calendarMonthTitle" style="color: #0d9488;">May 2026</h5>
                    <button class="btn btn-calendar-nav" onclick="changeCalendarMonth(1)">
                        Next <i class="bi bi-chevron-right ms-1"></i>
                    </button>
                </div>
                <div id="attendanceCalendar" class="attendance-calendar">
                    <!-- Calendar injected via JS -->
                </div>
            </div>
        </div>

        <!-- Payroll Tab -->
        <div class="drawer-section" id="drawer_tab_payroll">
            <!-- Salary Structure Section -->
            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 style="font-weight: 700; margin:0;"><i class="bi bi-wallet2 me-2"></i> Salary Structure (CTC)</h5>
                    <button class="btn btn-sm btn-outline-primary" onclick="toggleSalaryEdit()"><i class="bi bi-pencil-square"></i> Edit Structure</button>
                </div>
                <div id="salary_structure_container" class="salary-structure-box">
                    <div class="text-center p-4 text-muted">
                        <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                        Calculating structure...
                    </div>
                </div>
            </div>

            <hr class="my-4" style="opacity: 0.1;">

            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 style="font-weight: 700; margin:0;">Recent Payslips</h5>
            </div>
            <div class="table-responsive d-none d-md-block">
                <table class="table table-hover table-sm">
                    <thead>
                        <tr>
                            <th>Month/Year</th>
                            <th>Net Pay</th>
                            <th>Generated On</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="drawer_payslip_history">
                        <tr><td colspan="4" class="text-center text-muted">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Mobile View for Payslips -->
            <div id="drawer_payslip_history_mobile" class="d-md-none">
                <div class="text-center text-muted p-3">Loading...</div>
            </div>
            <button class="btn btn-outline-primary mt-3 w-100" onclick="openPayslipGeneratorModal()"><i class="bi bi-plus-circle"></i> Generate New Payslip</button>
        </div>

        <!-- Assets & Docs Tab -->
        <div class="drawer-section" id="drawer_tab_assets">
            <!-- Appointment Letter Section -->
            <h5 class="mb-2" style="font-weight: 700;">Appointment Letter</h5>
            <div class="info-box d-flex justify-content-between align-items-center mb-4">
                <div>
                    <div class="info-label">Status</div>
                    <div class="info-value text-muted" id="appointment_letter_status">Not Generated</div>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-primary" onclick="openLetterModal('appointment_letter')" title="Generate / Edit"><i class="bi bi-pencil-square"></i> <span class="d-none d-md-inline">Generate / Edit</span></button>
                    <button class="btn btn-sm btn-outline-info" onclick="mailLetter('appointment_letter')" id="btn_mail_appointment_letter" style="display:none;" title="Mail"><i class="bi bi-envelope"></i> <span class="d-none d-md-inline">Mail</span></button>
                    <button class="btn btn-sm btn-outline-primary" onclick="previewLetter('appointment_letter')" id="btn_preview_appointment_letter" style="display:none;" title="Preview final output"><i class="bi bi-eye"></i> <span class="d-none d-md-inline">Preview</span></button>
                    <button class="btn btn-sm btn-outline-secondary" onclick="printLetter('appointment_letter')" id="btn_print_appointment_letter" style="display:none;" title="Print"><i class="bi bi-printer"></i> <span class="d-none d-md-inline">Print</span></button>
                </div>
            </div>

            <!-- Offer Letter Section -->
            <h5 class="mb-2" style="font-weight: 700;">Offer Letter</h5>
            <div class="info-box d-flex justify-content-between align-items-center mb-4">
                <div>
                    <div class="info-label">Status</div>
                    <div class="info-value text-muted" id="offer_letter_status">Not Generated</div>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-primary" onclick="openLetterModal('offer_letter')" title="Generate / Edit"><i class="bi bi-pencil-square"></i> <span class="d-none d-md-inline">Generate / Edit</span></button>
                    <button class="btn btn-sm btn-outline-info" onclick="mailLetter('offer_letter')" id="btn_mail_offer_letter" style="display:none;" title="Mail"><i class="bi bi-envelope"></i> <span class="d-none d-md-inline">Mail</span></button>
                    <button class="btn btn-sm btn-outline-primary" onclick="previewLetter('offer_letter')" id="btn_preview_offer_letter" style="display:none;" title="Preview final output"><i class="bi bi-eye"></i> <span class="d-none d-md-inline">Preview</span></button>
                    <button class="btn btn-sm btn-outline-secondary" onclick="printLetter('offer_letter')" id="btn_print_offer_letter" style="display:none;" title="Print"><i class="bi bi-printer"></i> <span class="d-none d-md-inline">Print</span></button>
                </div>
            </div>

            <!-- Relieving Letter Section -->
            <!-- Moved to FNF Modal -->

            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 style="font-weight: 700; margin:0;">Company Assets</h5>
            </div>
            <div class="drawer-asset-container mt-3">
                <div class="table-responsive">
                    <table class="unified-table" style="font-size: 0.85rem;">
                        <thead>
                            <tr>
                                <th>Asset</th>
                                <th>Serial No.</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody id="drawer_assets_body">
                            <!-- Loaded via JS -->
                        </tbody>
                    </table>
                </div>
                <div class="text-center mt-3" id="no_assets_message" style="display: none;">
                    <div class="text-muted small mb-3">No assets currently assigned to this user.</div>
                </div>
                <div class="text-center">
                    <button class="btn btn-outline-primary btn-sm w-100" onclick="openAssignAssetFromDrawer()">
                        <i class="bi bi-plus-circle me-1"></i> Assign New Asset
                    </button>
                </div>
            </div>
        </div>
        </div>
    </div>
</div>

<!-- Modal: Assign Asset from Drawer -->
<div class="modal fade" id="assignAssetFromDrawerModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" style="background: #0d9488; color: white;">
                <h5 class="modal-title" style="font-weight: 700;">Assign Asset</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="assignAssetFromDrawerForm">
                <div class="modal-body">
                    <input type="hidden" name="assign_asset_action" value="1">
                    <input type="hidden" name="employee_id" id="assign_asset_user_id">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Select Asset</label>
                        <select name="asset_id" id="drawerAssetSelect" class="form-select" required>
                            <option value="">-- Choose Available Asset --</option>
                            <!-- Populated via AJAX -->
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Assignment Date</label>
                        <input type="date" name="assigned_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Notes</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="e.g. Initial setup completed"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success px-4" style="background: #0d9488; border: none;">Complete Assignment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Payslip Generator Modal -->
<div class="modal fade" id="payslipGeneratorModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title m-0"><i class="bi bi-receipt-cutoff me-2"></i> Payroll Configuration</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0">
        <form id="payslipGeneratorForm">
            <div class="generator-split">
                <!-- Settings Panel -->
                <div class="generator-settings">
                    <div class="payslip-input-card">
                        <h6><i class="bi bi-calendar-event"></i> Period & Attendance</h6>
                        <div class="row g-2">
                            <div class="col-6">
                                <select class="form-select form-select-sm" id="payslip_month" onchange="fetchAttendanceForPayslip()">
                                    <option value="1">January</option><option value="2">February</option><option value="3">March</option>
                                    <option value="4">April</option><option value="5">May</option><option value="6">June</option>
                                    <option value="7">July</option><option value="8">August</option><option value="9">September</option>
                                    <option value="10">October</option><option value="11">November</option><option value="12">December</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <select class="form-select form-select-sm" id="payslip_year" onchange="fetchAttendanceForPayslip()">
                                    <option value="2026">2026</option><option value="2025">2025</option>
                                </select>
                            </div>
                            <div class="col-4 mt-2">
                                <label class="small text-muted mb-1 d-block">Calendar Days</label>
                                <input type="number" id="payslip_calendar_days" class="form-control form-control-sm" readonly>
                            </div>
                            <div class="col-4 mt-2">
                                <label class="small text-muted mb-1 d-block">Sundays</label>
                                <input type="number" id="payslip_sunday_count" class="form-control form-control-sm" readonly title="Excluded from working days when Sundays are not paid">
                            </div>
                            <div class="col-4 mt-2">
                                <label class="small text-muted mb-1 d-block">Working Days</label>
                                <input type="number" id="payslip_working_days" class="form-control form-control-sm" readonly>
                            </div>
                            <div class="col-4 mt-2">
                                <label class="small text-muted mb-1 d-block">Paid Days</label>
                                <input type="number" id="payslip_paid_days" class="form-control form-control-sm" oninput="syncLops()" style="background: #f0fdfa; font-weight: 700; color: #0d9488;">
                            </div>
                            <div class="col-4 mt-2">
                                <label class="small text-muted mb-1 d-block">LOPs (Days)</label>
                                <input type="number" id="payslip_lops" class="form-control form-control-sm" oninput="calculatePayslip()">
                            </div>
                            <input type="hidden" id="payslip_total_days" value="30">
                            <input type="hidden" id="payslip_sundays_are_paid" value="1">
                        </div>
                    </div>

                    <div class="payslip-input-card">
                        <h6><i class="bi bi-cash-stack"></i> Earnings Overrides</h6>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="small text-muted mb-1 d-block">Basic</label>
                                <input type="number" class="form-control form-control-sm payslip-earning" id="payslip_basic" oninput="calculatePayslip()">
                            </div>
                            <div class="col-6">
                                <label class="small text-muted mb-1 d-block">HRA</label>
                                <input type="number" class="form-control form-control-sm payslip-earning" id="payslip_hra" oninput="calculatePayslip()">
                            </div>
                            <div class="col-6">
                                <label class="small text-muted mb-1 d-block">Conveyance</label>
                                <input type="number" class="form-control form-control-sm payslip-earning" id="payslip_conveyance" oninput="calculatePayslip()">
                            </div>
                            <div class="col-6">
                                <label class="small text-muted mb-1 d-block">Special</label>
                                <input type="number" class="form-control form-control-sm payslip-earning" id="payslip_special" oninput="calculatePayslip()">
                            </div>
                            <div class="col-12 mt-2">
                                <label class="small text-muted mb-1 d-block">Bonus/Incentive</label>
                                <input type="number" class="form-control form-control-sm payslip-earning" id="payslip_bonus" oninput="calculatePayslip()" placeholder="0">
                            </div>
                        </div>
                    </div>

                    <div class="payslip-input-card mb-0">
                        <h6><i class="bi bi-shield-check"></i> Deductions</h6>
                        <div class="row g-2">
                            <div class="col-4">
                                <label class="small text-muted mb-1 d-block">PF</label>
                                <input type="number" class="form-control form-control-sm payslip-deduction" id="payslip_pf" oninput="calculatePayslip()">
                            </div>
                            <div class="col-4">
                                <label class="small text-muted mb-1 d-block">PT</label>
                                <input type="number" class="form-control form-control-sm payslip-deduction" id="payslip_pt" oninput="calculatePayslip()">
                            </div>
                            <div class="col-4">
                                <label class="small text-muted mb-1 d-block">Medical</label>
                                <input type="number" class="form-control form-control-sm payslip-deduction" id="payslip_medical" oninput="calculatePayslip()">
                            </div>
                            <div class="col-12 mt-2">
                                <label class="small text-muted mb-1 d-block">Other Deductions</label>
                                <input type="number" class="form-control form-control-sm payslip-deduction" id="payslip_custom_deduction" oninput="calculatePayslip()" placeholder="0">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Preview Panel -->
                <div class="generator-preview">
                    <div class="preview-card" style="background: white; border-radius: 12px; padding: 25px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #333;">
                        <div style="text-align: center; margin-bottom: 20px;">
                            <h5 class="fw-bold mb-0" style="color: #005691; font-size: 24px;">Search Homes India Pvt Ltd</h5>
                            <span class="small text-muted fw-bold text-uppercase" style="font-size: 11px;">Salary Disbursement Statement</span>
                        </div>
                        
                        <div style="border-top: 2px solid #005691; margin: 15px 0;"></div>
                        
                        <div class="row mb-3" style="font-size: 13px;">
                            <div class="col-6"><strong>Employee:</strong> <span id="preview_name">---</span></div>
                            <div class="col-6 text-end"><strong>Period:</strong> <span id="preview_period">May 2026</span></div>
                        </div>

                        <div style="display: flex; gap: 15px; margin-bottom: 5px;">
                            <div style="flex: 1; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase;">Earnings</div>
                            <div style="flex: 1; font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase;">Deductions</div>
                        </div>

                        <div style="border: 1px solid #cbd5e1; border-radius: 4px; overflow: hidden; margin-bottom: 20px;">
                            <table style="width: 100%; border-collapse: collapse;">
                                <tr>
                                    <td style="width: 50%; vertical-align: top; padding: 8px; border-right: 1px solid #cbd5e1;">
                                        <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
                                            <tr><td style="padding: 6px 10px; border: 1px solid #e2e8f0;">Basic</td><td style="padding: 6px 10px; border: 1px solid #e2e8f0; text-align: right; font-weight: 600;" id="preview_basic">â‚¹0</td></tr>
                                            <tr><td style="padding: 6px 10px; border: 1px solid #e2e8f0;">HRA</td><td style="padding: 6px 10px; border: 1px solid #e2e8f0; text-align: right; font-weight: 600;" id="preview_hra">â‚¹0</td></tr>
                                            <tr><td style="padding: 6px 10px; border: 1px solid #e2e8f0;">Conveyance</td><td style="padding: 6px 10px; border: 1px solid #e2e8f0; text-align: right; font-weight: 600;" id="preview_conveyance">â‚¹0</td></tr>
                                            <tr><td style="padding: 6px 10px; border: 1px solid #e2e8f0;">Special</td><td style="padding: 6px 10px; border: 1px solid #e2e8f0; text-align: right; font-weight: 600;" id="preview_special">â‚¹0</td></tr>
                                        </table>
                                    </td>
                                    <td style="width: 50%; vertical-align: top; padding: 8px;">
                                        <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
                                            <tr><td style="padding: 6px 10px; border: 1px solid #e2e8f0;">PF</td><td style="padding: 6px 10px; border: 1px solid #e2e8f0; text-align: right; color: #ef4444; font-weight: 600;" id="preview_pf">â‚¹0</td></tr>
                                            <tr><td style="padding: 6px 10px; border: 1px solid #e2e8f0;">PT</td><td style="padding: 6px 10px; border: 1px solid #e2e8f0; text-align: right; color: #ef4444; font-weight: 600;" id="preview_pt">â‚¹0</td></tr>
                                            <tr><td style="padding: 6px 10px; border: 1px solid #e2e8f0;">Medical</td><td style="padding: 6px 10px; border: 1px solid #e2e8f0; text-align: right; color: #ef4444; font-weight: 600;" id="preview_medical">â‚¹0</td></tr>
                                            <tr id="preview_lop_row" style="display: none;"><td style="padding: 6px 10px; border: 1px solid #e2e8f0;">LOP Ded.</td><td style="padding: 6px 10px; border: 1px solid #e2e8f0; text-align: right; color: #ef4444; font-weight: 600;" id="preview_lop_amount">â‚¹0</td></tr>
                                            <tr><td style="padding: 6px 10px; border: 1px solid #e2e8f0;">Other</td><td style="padding: 6px 10px; border: 1px solid #e2e8f0; text-align: right; color: #ef4444; font-weight: 600;" id="preview_custom_ded">â‚¹0</td></tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <div style="padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px; background: #fff; text-align: right;">
                            <div style="font-size: 20px; font-weight: 800; color: #1e293b;" id="preview_net_pay">Net Pay: â‚¹0</div>
                            <div style="font-size: 11px; color: #64748b; font-style: italic;" id="preview_words">Zero Rupees Only</div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
      </div>
      <div class="modal-footer" style="background: #fff; border-top: 1px solid #f1f5f9; padding: 20px 30px;">
        <button type="button" class="btn btn-outline-primary fw-bold px-4" onclick="previewPayslipFromGenerator()" style="border-radius:10px;"><i class="bi bi-eye me-1"></i> Preview</button>
        <button type="button" class="btn btn-light fw-bold px-4 ms-auto" data-bs-dismiss="modal" style="border-radius:10px;">Cancel</button>
        <button type="button" class="btn btn-primary fw-bold px-5 py-2 shadow-sm" onclick="savePayslip()" style="background: #0d9488; border: none; border-radius: 10px;">
            <i class="bi bi-check-all me-2"></i> Confirm & Generate
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Dark mode employees table: must load after unified_table_styles #example rules -->
<style>
  body.dark-mode .users-page table#example.user-data-table.unified-table thead th,
  body.dark-mode .users-page #example.user-data-table thead th {
      background: #2c2c2e !important;
      color: rgba(255, 255, 255, 0.65) !important;
      border-bottom: 1px solid rgba(255, 255, 255, 0.12) !important;
  }
  body.dark-mode .users-page table#example.user-data-table.unified-table tbody tr,
  body.dark-mode .users-page #example tbody tr.user-data-row,
  body.dark-mode .users-page #example tbody tr {
      background: rgba(44, 44, 46, 0.4) !important;
      border: 1px solid rgba(255, 255, 255, 0.12) !important;
      box-shadow: none !important;
  }
  body.dark-mode .users-page table#example.user-data-table tbody td,
  body.dark-mode .users-page #example tbody td {
      background: transparent !important;
  }
</style>

</body>
</html>
