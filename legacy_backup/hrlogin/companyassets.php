<?php 
session_start();
// Redirect to login if not logged in or not an admin
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'hradminuser') {
    header('Location: /');
    exit;
}
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}
require_once 'db.php';
$db = new Database();
$employees = $db->getActiveAccountsForAssignment();
$asset_employee_lookup = ['byName' => [], 'byCode' => [], 'byId' => []];
foreach ($employees as $emp) {
    $empId = (int) ($emp['id'] ?? 0);
    if ($empId <= 0) {
        continue;
    }
    $asset_employee_lookup['byId'][(string) $empId] = $empId;
    $empName = strtolower(trim((string) ($emp['username'] ?? '')));
    $empCode = strtolower(trim((string) ($emp['employee_id'] ?? '')));
    if ($empName !== '') {
        $asset_employee_lookup['byName'][$empName] = $empId;
    }
    if ($empCode !== '') {
        $asset_employee_lookup['byCode'][$empCode] = $empId;
    }
}
$asset_counts = $db->getAssetSummaryCounts();
$assets_available = $db->getAvailableAssetsForDropdown();
$asset_total_count = $asset_counts['total'];
$asset_assigned_count = $asset_counts['assigned'];
$asset_available_count = $asset_counts['available'];
$skip_superadmin_css = true;
include __DIR__ . '/htmlopen.php'; 
?>
<!-- Add specific styling for Asset Management -->
<link rel="stylesheet" href="./assets/css/style_dashboard.css?v=<?php echo time(); ?>" />
<link rel="stylesheet" href="./assets/css/Users.css?v=<?php echo time(); ?>" />
<link rel="stylesheet" href="./assets/css/unified_table_styles.css?v=<?php echo time(); ?>" />
<?php include __DIR__ . '/user_profile_drawer_styles.php'; ?>
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

    .asset-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        flex-wrap: wrap;
        gap: 20px;
    }
    .asset-tabs {
        border: none !important;
        margin-bottom: 0;
        background: var(--table-hover);
        padding: 4px;
        border-radius: 12px;
        display: flex !important;
        width: 100%;
    }
    .asset-tabs .nav-item {
        flex: 1;
        min-width: 0;
    }
    .asset-tabs .nav-link {
        border: none !important;
        color: var(--text-muted) !important;
        font-weight: 600;
        width: 100%;
        text-align: center;
        padding: 6px 12px !important;
        border-radius: 10px !important;
        font-size: 0.85rem;
        line-height: 1.3;
        transition: 0.3s;
    }
    .asset-tabs .nav-link.active {
        background: var(--primary-teal) !important;
        color: white !important;
        box-shadow: 0 4px 12px rgba(34, 116, 119, 0.2);
    }
    .btn-asset-primary {
        background: var(--primary-teal);
        color: white !important;
        border: none;
        padding: 12px 24px;
        border-radius: 12px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: 0.3s;
    }
    .btn-asset-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(34, 116, 119, 0.3);
        color: white !important;
    }
    .status-badge {
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
    }
    .status-available { background: rgba(16, 185, 129, 0.15); color: #10b981; }
    .status-assigned { background: rgba(59, 130, 246, 0.15); color: #3b82f6; }
    .status-faulty { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
    
    /* Summary cards — match attendance report */
    .company-assets-page .summary-section .summary-card {
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
    .company-assets-page .summary-section .summary-card:hover {
        transform: translateY(-2px) !important;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1) !important;
    }
    .company-assets-page .summary-section .summary-card.stat-card-headcount {
        border: 2px solid #0ea5e9 !important;
    }
    .company-assets-page .summary-section .summary-card.stat-card-present {
        border: 2px solid #10b981 !important;
    }
    .company-assets-page .summary-section .summary-card.stat-card-late {
        border: 2px solid #f59e0b !important;
    }
    .company-assets-page .summary-section .summary-card.stat-card-headcount.active-filter {
        background: #e0f2fe !important;
        border: 3px solid #0ea5e9 !important;
    }
    .company-assets-page .summary-section .summary-card.stat-card-present.active-filter {
        background: #d1fae5 !important;
        border: 3px solid #10b981 !important;
    }
    .company-assets-page .summary-section .summary-card.stat-card-late.active-filter {
        background: #fef3c7 !important;
        border: 3px solid #f59e0b !important;
    }

    .company-assets-page .summary-wrapper {
        width: 100%;
        margin-bottom: 0.5rem !important;
    }
    .company-assets-page .summary-section {
        display: flex !important;
        flex-wrap: nowrap !important;
        gap: 10px !important;
        overflow-x: auto !important;
        width: 100% !important;
        padding: 6px 0 !important;
    }
    .company-assets-page .summary-section .summary-card {
        flex: 0 0 auto !important;
        white-space: nowrap !important;
    }

    .assets-controls-card {
        background: transparent !important;
        border: none !important;
        box-shadow: none !important;
        padding: 0 !important;
        margin-bottom: 0.75rem !important;
    }
    .assets-controls-card .control-bar {
        flex-wrap: nowrap !important;
        align-items: center !important;
        gap: 10px !important;
    }
    .assets-controls-card .control-left {
        display: none !important;
    }
    .assets-controls-card .control-right,
    .assets-controls-card .header-tools-wrapper {
        width: 100% !important;
        flex-grow: 1 !important;
    }
    .assets-controls-card .assets-toolbar-form {
        width: 100% !important;
        display: flex !important;
        align-items: center !important;
        justify-content: flex-start !important;
        flex-wrap: nowrap !important;
        gap: 12px !important;
    }
    .assets-controls-card .filter-group {
        display: flex !important;
        align-items: center !important;
        flex-wrap: nowrap !important;
        gap: 10px !important;
    }
    .assets-controls-card .filter-group-search {
        flex: 1 1 auto !important;
        min-width: 0 !important;
    }
    .assets-controls-card .filter-group-actions {
        flex: 0 0 auto !important;
        margin-left: auto !important;
        flex-wrap: nowrap !important;
    }
    .assets-tabs-bar {
        width: 100%;
        margin-bottom: 0.75rem;
    }
    .assets-tabs-bar .asset-tabs {
        width: 100%;
    }
    .company-assets-page .user-table-container {
        margin-top: 0 !important;
        padding-top: 0 !important;
    }
    .company-assets-page .user-data-table {
        border-spacing: 0 10px !important;
        border-collapse: separate !important;
    }
    .assets-controls-card .search-box {
        position: relative !important;
        width: 100% !important;
    }
    .assets-controls-card .search-box .search-input {
        width: 100% !important;
        min-width: 320px !important;
        height: 38px !important;
        border-radius: 8px !important;
        padding-left: 44px !important;
        background: #fff !important;
        border: 1px solid #ddd !important;
    }
    .assets-controls-card .status-icon-select {
        display: inline-flex !important;
        align-items: center !important;
        flex-shrink: 0;
    }
    .assets-controls-card .status-icon-select > i {
        display: none !important;
    }
    .assets-controls-card .status-icon-select .form-select,
    .assets-controls-card .asset-type-select-wrap .form-select {
        min-width: 0 !important;
        width: auto !important;
        height: 38px !important;
        border-radius: 8px !important;
        padding: 0 32px 0 12px !important;
        background-color: #fff !important;
        border: 1px solid #ddd !important;
        font-weight: 600 !important;
        font-size: 0.875rem !important;
        color: #333 !important;
        white-space: nowrap !important;
        text-overflow: clip !important;
        -webkit-appearance: none !important;
        appearance: none !important;
    }
    .assets-controls-card .status-icon-select .form-select {
        min-width: 128px !important;
    }
    .assets-controls-card .asset-type-select-wrap .form-select {
        min-width: 168px !important;
    }
    body:not(.dark-mode) .assets-controls-card #assetStatusFilter,
    body:not(.dark-mode) .assets-controls-card #assetTypeFilter {
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%2364748b' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e") !important;
        background-repeat: no-repeat !important;
        background-position: right 10px center !important;
        background-size: 14px 10px !important;
    }
    .assets-controls-card .page-size-selector {
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        padding: 0 10px !important;
        min-width: 64px !important;
        height: 38px !important;
        border-radius: 8px !important;
        background: #fff !important;
        border: 1px solid #ddd !important;
        flex-shrink: 0;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
    }
    .assets-controls-card .page-size-selector #assets-limit {
        width: 46px !important;
        height: 100% !important;
        border: 0 !important;
        box-shadow: none !important;
        background-color: transparent !important;
        font-weight: 600 !important;
        color: #333 !important;
        text-align: center !important;
        padding: 0 16px 0 0 !important;
        -webkit-appearance: none !important;
        appearance: none !important;
    }
    body:not(.dark-mode) .assets-controls-card #assets-limit {
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%2364748b' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e") !important;
        background-repeat: no-repeat !important;
        background-position: right 2px center !important;
        background-size: 14px 10px !important;
    }
    @media (min-width: 769px) {
        .company-assets-page .assets-controls-card .page-size-selector::after,
        .company-assets-page .assets-controls-card .page-size-selector::before {
            content: none !important;
            display: none !important;
        }
    }
    .company-assets-page .btn-asset-primary {
        height: 38px !important;
        padding: 0 16px !important;
        font-size: 0.85rem !important;
        white-space: nowrap !important;
    }
    .company-assets-page .asset-assigned-user-link {
        color: var(--primary-teal) !important;
        font-weight: 700;
        text-decoration: none;
        cursor: pointer;
        background: none;
        border: none;
        padding: 0;
        font: inherit;
        text-align: left;
        pointer-events: auto;
    }
    .company-assets-page .asset-assigned-user-link:hover {
        text-decoration: underline;
    }
    .company-assets-page .asset-assigned-user-link .small {
        font-weight: 600;
    }
    /* Asset overview modal card — self-contained fixed overlay */
    #assetOverviewOverlay {
        display: none !important;
        position: fixed !important;
        inset: 0 !important;
        width: 100vw !important;
        height: 100vh !important;
        margin: 0 !important;
        padding: 0 !important;
        background: rgba(15, 23, 42, 0.52) !important;
        backdrop-filter: blur(4px);
        z-index: 100300 !important;
        opacity: 1 !important;
        visibility: visible !important;
    }
    #assetOverviewOverlay.active {
        display: block !important;
    }
    #assetOverviewDrawer {
        display: none !important;
        position: fixed !important;
        top: 50% !important;
        left: 50% !important;
        right: auto !important;
        bottom: auto !important;
        transform: translate(-50%, -50%) !important;
        width: min(1000px, 92vw) !important;
        height: min(90vh, 880px) !important;
        max-width: 92vw !important;
        max-height: 90vh !important;
        margin: 0 !important;
        border-radius: 20px !important;
        background: rgba(255, 255, 255, 0.98) !important;
        box-shadow: 0 25px 60px rgba(0, 0, 0, 0.28) !important;
        z-index: 100310 !important;
        flex-direction: column !important;
        overflow: hidden !important;
        opacity: 1 !important;
        visibility: visible !important;
    }
    #assetOverviewDrawer.active {
        display: flex !important;
    }
    #assetOverviewDrawer .drawer-header {
        padding: 22px 28px;
        background: linear-gradient(135deg, #1e6063 0%, #2a8c90 100%);
        color: #fff;
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        flex-shrink: 0;
    }
    #assetOverviewDrawer .drawer-header-content {
        display: flex;
        gap: 18px;
        align-items: center;
    }
    #assetOverviewDrawer .drawer-avatar {
        width: 64px;
        height: 64px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.75rem;
        border: 3px solid rgba(255, 255, 255, 0.3);
        flex-shrink: 0;
    }
    #assetOverviewDrawer .drawer-title h3 {
        margin: 0;
        font-weight: 700;
        font-size: 1.4rem;
    }
    #assetOverviewDrawer .close-drawer {
        background: none;
        border: none;
        color: #fff;
        font-size: 1.35rem;
        cursor: pointer;
        opacity: 0.85;
        line-height: 1;
        padding: 4px;
    }
    #assetOverviewDrawer .close-drawer:hover {
        opacity: 1;
    }
    #assetOverviewDrawer .drawer-body {
        flex: 1;
        overflow-y: auto;
        padding: 20px 28px 28px;
        -webkit-overflow-scrolling: touch;
    }
    #assetOverviewDrawer .salary-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }
    #assetOverviewDrawer .salary-table th,
    #assetOverviewDrawer .salary-table td {
        padding: 10px 12px;
        border-bottom: 1px solid #e2e8f0;
    }
    #assetOverviewDrawer .salary-table .row-value,
    #assetOverviewDrawer .salary-table .row-yearly {
        text-align: right;
    }
    #assetOverviewDrawer .salary-table .total-row {
        background: #1e293b;
        color: #fff;
    }
    #assetOverviewDrawer .salary-table .net-row {
        background: #f0fdf4;
    }
    body.dark-mode #assetOverviewDrawer {
        background: #1a1a1a !important;
        color: #e8e8e8;
    }
    @media (max-width: 768px) {
        #assetOverviewDrawer {
            top: 0 !important;
            left: 0 !important;
            transform: none !important;
            width: 100vw !important;
            height: 100vh !important;
            max-width: 100vw !important;
            max-height: 100vh !important;
            border-radius: 0 !important;
        }
    }
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
        word-break: break-word;
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
        color: #64748b;
    }
    body.dark-mode .overview-card {
        background: rgba(255, 255, 255, 0.05);
        border-color: rgba(255, 255, 255, 0.12);
    }
    body.dark-mode .overview-label { color: #94a3b8; }
    body.dark-mode .overview-value { color: #e2e8f0; }
    @media (max-width: 768px) {
        .overview-grid-3 { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 480px) {
        .overview-grid-3, .overview-grid-2 { grid-template-columns: 1fr; }
    }
    .modal-content {
        border-radius: 24px;
        border: none;
        box-shadow: 0 20px 50px rgba(0,0,0,0.1);
        background: var(--table-bg);
        color: var(--text-primary);
    }
    .modal-header { border-bottom: 1px solid var(--table-border); padding: 25px; }
    .modal-body { padding: 25px; }
    
    /* Input Styling */
    .form-control, .form-select {
        border-radius: 12px !important;
        padding: 12px 16px !important;
        background: var(--table-bg) !important;
        border: 1px solid var(--table-border) !important;
        color: var(--text-primary) !important;
    }

    /* Dark mode specific tweaks */
    body.dark-mode .company-assets-page .summary-section .summary-card {
        background: rgba(255, 255, 255, 0.05) !important;
        color: #f1f5f9 !important;
        border-color: rgba(255, 255, 255, 0.15) !important;
    }
    body.dark-mode .company-assets-page .summary-section .summary-card.stat-card-headcount.active-filter {
        background: rgba(14, 165, 233, 0.15) !important;
        border: 3px solid #0ea5e9 !important;
    }
    body.dark-mode .company-assets-page .summary-section .summary-card.stat-card-present.active-filter {
        background: rgba(16, 185, 129, 0.15) !important;
        border: 3px solid #10b981 !important;
    }
    body.dark-mode .company-assets-page .summary-section .summary-card.stat-card-late.active-filter {
        background: rgba(245, 158, 11, 0.15) !important;
        border: 3px solid #f59e0b !important;
    }
    body.dark-mode .assets-controls-card .search-box .search-input,
    body.dark-mode .assets-controls-card .status-icon-select .form-select,
    body.dark-mode .assets-controls-card .asset-type-select-wrap .form-select,
    body.dark-mode .assets-controls-card .page-size-selector {
        background: rgba(255, 255, 255, 0.05) !important;
        border-color: rgba(255, 255, 255, 0.12) !important;
        color: #f1f5f9 !important;
    }
    body.dark-mode .assets-controls-card #assetStatusFilter,
    body.dark-mode .assets-controls-card #assetTypeFilter {
        background-color: rgba(255, 255, 255, 0.05) !important;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23ffffff' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e") !important;
        background-repeat: no-repeat !important;
        background-position: right 10px center !important;
        background-size: 14px 10px !important;
    }
    body.dark-mode .assets-controls-card .page-size-selector #assets-limit {
        background-color: transparent !important;
        color: #f1f5f9 !important;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23ffffff' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e") !important;
        background-repeat: no-repeat !important;
        background-position: right 2px center !important;
        background-size: 14px 10px !important;
        -webkit-appearance: none !important;
        appearance: none !important;
    }
    body.dark-mode .modal-content {
        background: rgba(18, 18, 18, 0.98) !important;
        border-color: rgba(255, 255, 255, 0.12) !important;
        backdrop-filter: blur(25px) saturate(180%) !important;
        -webkit-backdrop-filter: blur(25px) saturate(180%) !important;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6) !important;
    }
    body.dark-mode .form-control, 
    body.dark-mode .form-select {
        background-color: #2a2a2d !important;
        color: #ffffff !important;
        border-color: rgba(255, 255, 255, 0.2) !important;
    }
    body.dark-mode .status-available { background: rgba(16, 185, 129, 0.2); color: #10b981; }
    body.dark-mode .status-assigned { background: rgba(59, 130, 246, 0.2); color: #3b82f6; }
    body.dark-mode .status-faulty { background: rgba(239, 68, 68, 0.2); color: #ef4444; }

    /* Theme-aware Serial Badge */
    .serial-badge {
        background: var(--table-hover) !important;
        color: var(--text-primary) !important;
        padding: 6px 12px !important;
        border-radius: 8px !important;
        font-weight: 600 !important;
        border: 1px solid var(--table-border) !important;
        font-size: 0.8rem;
    }
    body.dark-mode .serial-badge {
        background: rgba(255, 255, 255, 0.1) !important;
        border-color: rgba(255, 255, 255, 0.15) !important;
    }

    .company-assets-page .container-fluid {
        padding-top: 10px !important;
        max-width: 100%;
        overflow-x: hidden;
    }

    /* Mobile — hidden on desktop */
    .asset-mobile-bottom-nav,
    .asset-mobile-sheet {
        display: none;
    }

    .asset-mobile-list {
        display: none;
    }

    .asset-toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        margin-bottom: 18px;
        flex-wrap: wrap;
    }

    @media (max-width: 768px) {
        .company-assets-page.content {
            padding: 10px !important;
            overflow-x: hidden;
        }

        .company-assets-page .container-fluid {
            padding-left: 0 !important;
            padding-right: 0 !important;
            padding-top: 10px !important;
            padding-bottom: calc(72px + env(safe-area-inset-bottom, 0px)) !important;
        }

        .company-assets-page .summary-wrapper,
        .company-assets-page .summary-wrapper.pt-1,
        .company-assets-page .summary-wrapper.mb-2 {
            padding-top: 0 !important;
            margin-bottom: 8px !important;
        }

        .company-assets-page .summary-section {
            padding: 4px 5px !important;
        }

        .company-assets-page .assets-controls-card.glass-card,
        .company-assets-page .assets-controls-card {
            padding: 0 !important;
            margin-top: 0 !important;
            margin-bottom: 8px !important;
            background: transparent !important;
            border: none !important;
            box-shadow: none !important;
        }

        .company-assets-page .assets-controls-card .assets-toolbar-form {
            display: grid !important;
            grid-template-columns: 1fr auto !important;
            grid-template-areas: "search limit" !important;
            gap: 10px !important;
            width: 100% !important;
            align-items: stretch !important;
        }

        .company-assets-page .assets-controls-card .filter-group-search {
            grid-area: search !important;
            width: auto !important;
            min-width: 0 !important;
            flex: none !important;
        }

        .company-assets-page .assets-controls-card .filter-group-search .search-box {
            width: 100% !important;
            margin-bottom: 0 !important;
        }

        .company-assets-page .assets-controls-card .filter-group-search .search-input {
            width: 100% !important;
            min-width: 0 !important;
            height: auto !important;
            padding: 12px 12px 12px 44px !important;
            border-radius: 12px !important;
            font-size: 14px !important;
        }

        .company-assets-page .assets-controls-card .filter-group-actions {
            display: contents !important;
        }

        .company-assets-page .assets-controls-card .filter-group-actions .status-icon-select,
        .company-assets-page .assets-controls-card .filter-group-actions .asset-type-select-wrap,
        .company-assets-page .assets-controls-card .filter-group-actions .asset-clear-filters,
        .company-assets-page .assets-controls-card .filter-group-actions .btn-asset-primary {
            display: none !important;
        }

        .company-assets-page .assets-controls-card .filter-group-actions .page-size-selector {
            grid-area: limit !important;
            position: relative !important;
            display: flex !important;
            width: 72px !important;
            min-width: 72px !important;
            max-width: 72px !important;
            height: 48px !important;
            border-radius: 12px !important;
            background: #ffffff !important;
            border: 1px solid #e2e8f0 !important;
            box-shadow: none !important;
            padding: 0 6px !important;
            align-items: center !important;
            justify-content: center !important;
            margin-left: 0 !important;
            overflow: hidden !important;
        }

        .company-assets-page .assets-controls-card .filter-group-actions .page-size-selector::before {
            content: none !important;
            display: none !important;
        }

        .company-assets-page .assets-controls-card .filter-group-actions .page-size-selector::after {
            content: "" !important;
            display: block !important;
            position: absolute !important;
            right: 8px !important;
            top: 50% !important;
            transform: translateY(-50%) !important;
            width: 10px !important;
            height: 10px !important;
            pointer-events: none !important;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%2364748b' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e") !important;
            background-repeat: no-repeat !important;
            background-position: center !important;
            background-size: contain !important;
        }

        body.dark-mode .company-assets-page .assets-controls-card .filter-group-actions .page-size-selector::after {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23ffffff' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e") !important;
        }

        .company-assets-page .assets-controls-card .filter-group-actions .page-size-selector #assets-limit {
            width: 100% !important;
            height: 100% !important;
            min-height: 0 !important;
            margin: 0 !important;
            padding: 0 18px 0 2px !important;
            border: 0 !important;
            border-radius: 0 !important;
            box-shadow: none !important;
            background: transparent !important;
            background-image: none !important;
            text-align: center !important;
            font-weight: 800 !important;
            font-size: 14px !important;
            line-height: 48px !important;
            -webkit-appearance: none !important;
            appearance: none !important;
            box-sizing: border-box !important;
        }

        .company-assets-page .assets-tabs-bar {
            margin-bottom: 8px !important;
        }

        .company-assets-page .assets-tabs-bar .asset-tabs .nav-link {
            font-size: 0.78rem;
            padding: 8px 8px !important;
        }

        .company-assets-page .user-table-container {
            margin-top: 0 !important;
            padding-top: 0 !important;
            border: none !important;
            background: transparent !important;
        }

        .company-assets-page .user-table-scroll-wrapper {
            display: none !important;
        }

        .company-assets-page .asset-mobile-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .company-assets-page .pagination-section {
            padding-bottom: 0.5rem !important;
        }

        .company-assets-page .floating-clear-btn {
            bottom: calc(78px + env(safe-area-inset-bottom, 0px)) !important;
        }

        /* Sleek mobile asset cards — matches attendance report */
        .mobile-asset-card {
            background: #ffffff;
            border-radius: 12px;
            border: 1px solid #eef2f7;
            box-shadow: 0 1px 6px rgba(15, 23, 42, 0.05);
            overflow: hidden;
            transition: box-shadow 0.2s ease;
        }

        .mobile-asset-card.expanded {
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.08);
        }

        .asset-card-main {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            min-height: 52px;
        }

        .asset-card-info {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .asset-card-meta-line {
            display: flex;
            align-items: center;
            gap: 6px;
            min-width: 0;
        }

        .asset-card-meta {
            font-size: 0.72rem;
            font-weight: 700;
            color: #94a3b8;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .asset-card-meta-dot {
            color: #cbd5e1;
            font-size: 0.7rem;
            flex-shrink: 0;
        }

        .asset-card-name-line {
            display: flex;
            align-items: center;
            flex-wrap: nowrap;
            gap: 8px;
            min-width: 0;
        }

        .asset-card-name {
            font-size: 0.92rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.2;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .mobile-asset-card .status-badge,
        .asset-type-pill {
            padding: 3px 8px !important;
            border-radius: 999px !important;
            font-size: 0.62rem !important;
            font-weight: 700 !important;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .asset-type-pill {
            background: rgba(34, 116, 119, 0.12);
            color: #227477;
        }

        .asset-card-expand-btn {
            width: 32px !important;
            height: 32px !important;
            min-width: 32px !important;
            min-height: 32px !important;
            border-radius: 50% !important;
            background: #1e1e2d !important;
            color: #fff !important;
            border: none !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            padding: 0 !important;
            flex-shrink: 0;
        }

        .asset-card-expand-btn.active {
            background: var(--primary-teal) !important;
        }

        .asset-card-expand-btn i {
            font-size: 0.85rem;
            transition: transform 0.2s ease;
        }

        .asset-card-detail-panel {
            display: none;
            border-top: 1px solid #f1f5f9;
            padding: 10px 12px 12px;
            background: #f8fafc;
        }

        .asset-mobile-detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px 12px;
            margin-bottom: 10px;
        }

        .asset-mobile-detail-item {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .asset-mobile-detail-item.full {
            grid-column: 1 / -1;
        }

        .asset-mobile-detail-label {
            font-size: 0.65rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: #94a3b8;
        }

        .asset-mobile-detail-value {
            font-size: 0.82rem;
            font-weight: 600;
            color: #334155;
            word-break: break-word;
        }

        .asset-card-action-btn {
            width: 100%;
            border: none;
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 0.85rem;
            font-weight: 700;
            cursor: pointer;
        }

        .asset-card-return-btn {
            background: #fff;
            color: #ef4444;
            border: 1px solid #ef4444 !important;
        }

        .asset-mobile-empty {
            text-align: center;
            padding: 2.5rem 1rem;
            color: #64748b;
            font-weight: 600;
            border-radius: 12px;
            border: 1px solid #eef2f7;
            background: #ffffff;
        }

        /* Fixed bottom nav — matches attendance report */
        .asset-mobile-bottom-nav {
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

        .asset-mobile-nav-btn {
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

        .asset-mobile-nav-btn i {
            font-size: 18px;
            line-height: 1;
        }

        .asset-mobile-nav-btn.asset-mobile-type-btn { color: #ffa600; }
        .asset-mobile-nav-btn.asset-mobile-status-btn { color: #2563eb; }
        .asset-mobile-nav-btn.asset-mobile-actions-btn { color: #03ac47; }
        .asset-mobile-nav-btn.active {
            background: rgba(15, 23, 42, 0.06);
        }

        /* Bottom sheets */
        .asset-mobile-sheet {
            display: block;
            position: fixed;
            inset: 0;
            z-index: 1001;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.2s ease;
        }

        .asset-mobile-sheet.active {
            pointer-events: auto;
            opacity: 1;
        }

        .asset-mobile-sheet-backdrop {
            position: absolute;
            inset: 0;
            background: rgba(15, 23, 42, 0.45);
        }

        .asset-mobile-sheet-panel {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            background: #fff;
            border-radius: 16px 16px 0 0;
            padding: 16px 16px calc(16px + env(safe-area-inset-bottom, 0px));
            transform: translateY(100%);
            transition: transform 0.25s ease;
        }

        .asset-mobile-sheet.active .asset-mobile-sheet-panel {
            transform: translateY(0);
        }

        .asset-mobile-sheet-panel h6 {
            font-size: 0.95rem;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 12px;
        }

        .asset-mobile-sheet-options {
            display: flex;
            flex-direction: column;
            gap: 8px;
            max-height: 50vh;
            overflow-y: auto;
        }

        .asset-mobile-sheet-option {
            width: 100%;
            text-align: left;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px 14px;
            background: #fff;
            font-size: 0.9rem;
            font-weight: 600;
            color: #334155;
            cursor: pointer;
        }

        .asset-mobile-sheet-option.selected {
            border-color: var(--primary-teal);
            background: rgba(34, 116, 119, 0.08);
            color: var(--primary-teal);
        }

        .asset-mobile-action-btn {
            width: 100%;
            border: none;
            border-radius: 10px;
            padding: 12px 14px;
            font-size: 0.9rem;
            font-weight: 700;
            cursor: pointer;
            margin-bottom: 8px;
        }

        .asset-mobile-action-btn.assign {
            background: var(--primary-teal);
            color: #fff;
        }

        .asset-mobile-action-btn.register {
            background: #1e293b;
            color: #fff;
        }

        body.dark-mode .mobile-asset-card {
            background: rgba(18, 18, 18, 0.85);
            border-color: rgba(255, 255, 255, 0.1);
        }

        body.dark-mode .asset-card-name { color: #f8fafc; }
        body.dark-mode .asset-card-meta { color: #94a3b8; }
        body.dark-mode .asset-card-detail-panel { background: rgba(255, 255, 255, 0.03); border-color: rgba(255, 255, 255, 0.08); }
        body.dark-mode .asset-mobile-detail-value { color: #e2e8f0; }
        body.dark-mode .asset-mobile-empty { background: rgba(255, 255, 255, 0.05); border-color: rgba(255, 255, 255, 0.1); color: #94a3b8; }
        body.dark-mode .asset-mobile-bottom-nav { background: #121212; border-color: rgba(255, 255, 255, 0.1); }
        body.dark-mode .asset-mobile-nav-btn { color: #94a3b8; }
        body.dark-mode .asset-mobile-sheet-panel { background: #1a1a1a; }
        body.dark-mode .asset-mobile-sheet-panel h6 { color: #f8fafc; }
        body.dark-mode .asset-mobile-sheet-option { background: rgba(255, 255, 255, 0.05); border-color: rgba(255, 255, 255, 0.12); color: #e2e8f0; }

        #assignAssetModal.modal,
        #addAssetModal.modal {
            z-index: 100200 !important;
            padding-top: 72px !important;
        }
        body.assign-asset-page-modal-open .modal-backdrop.show {
            z-index: 100190 !important;
        }

        #assignAssetModal .modal-dialog,
        #addAssetModal .modal-dialog {
            margin: 0.5rem auto 1rem auto !important;
            min-height: calc(100% - 72px) !important;
            align-items: flex-start !important;
        }

        #assignAssetModal .modal-content,
        #addAssetModal .modal-content {
            max-height: calc(100vh - 92px) !important;
            display: flex;
            flex-direction: column;
        }

        #assignAssetModal .modal-header,
        #addAssetModal .modal-header {
            padding: 18px 18px 14px 18px !important;
        }

        #assignAssetModal .modal-body,
        #addAssetModal .modal-body {
            padding: 14px 18px 10px 18px !important;
            overflow-y: auto;
        }

        #assignAssetModal .modal-footer,
        #addAssetModal .modal-footer {
            padding: 10px 18px 14px 18px !important;
            gap: 8px;
            flex-direction: column-reverse !important;
        }

        #assignAssetModal .modal-footer .btn,
        #addAssetModal .modal-footer .btn {
            width: 100% !important;
            min-height: 48px !important;
        }

        #assignAssetModal .form-select,
        #assignAssetModal .form-control,
        #addAssetModal .form-select,
        #addAssetModal .form-control {
            font-size: 16px !important;
            min-height: 48px !important;
        }
    }
</style>
<?php include __DIR__ . '/header.php'; ?>
<div class="content company-assets-page">
    <div class="container-fluid">

        <!-- Summary Cards (top — matches attendance report) -->
        <div class="summary-wrapper pt-1 mb-2">
            <button type="button" class="summary-arrow left" id="summaryLeft" aria-label="Scroll summary left">‹</button>
            <div class="summary-section" id="summaryScroll">
                <div class="summary-card stat-card-headcount" id="assetSummaryTotal">
                    <span class="summary-text" style="font-weight: 600;">Total Assets : <span id="assetTotalCount"><?php echo (int) $asset_total_count; ?></span></span>
                </div>
                <div class="summary-card stat-card-present" id="assetSummaryAssigned" data-status="assigned">
                    <span class="summary-text" style="font-weight: 600;">Assigned Assets : <span id="assetAssignedCount"><?php echo (int) $asset_assigned_count; ?></span></span>
                </div>
                <div class="summary-card stat-card-late" id="assetSummaryAvailable" data-status="available">
                    <span class="summary-text" style="font-weight: 600;">Available Assets : <span id="assetAvailableCount"><?php echo (int) $asset_available_count; ?></span></span>
                </div>
            </div>
            <button type="button" class="summary-arrow right" id="summaryRight" aria-label="Scroll summary right">›</button>
        </div>

        <!-- Controls toolbar (single row — matches attendance report) -->
        <div class="glass-card p-4 mb-3 assets-controls-card">
            <div class="control-bar d-flex justify-content-between align-items-center">
                <div class="control-right d-flex align-items-center justify-content-end gap-2 flex-grow-1 header-tools-wrapper">
                    <div class="assets-toolbar-form">
                        <div class="filter-group filter-group-search">
                            <div class="search-box">
                                <svg class="search-icon" width="18" height="18" viewBox="0 0 18 18" fill="none"
                                    style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); pointer-events: none; z-index: 1;">
                                    <circle cx="8" cy="8" r="6" stroke="#999" stroke-width="1.5" />
                                    <path d="M12.5 12.5L16 16" stroke="#999" stroke-width="1.5" stroke-linecap="round" />
                                </svg>
                                <input type="text" class="search-input" id="assetSearchInput" placeholder="Search assets, serial, employee..." autocomplete="off">
                            </div>
                        </div>
                        <div class="filter-group filter-group-actions">
                            <div class="status-icon-select" id="assetStatusFilterWrap">
                                <select id="assetStatusFilter" class="form-select" aria-label="Status">
                                    <option value="">All Status</option>
                                    <option value="assigned">Assigned</option>
                                    <option value="available">Available</option>
                                    <option value="faulty">Faulty</option>
                                </select>
                                <i class="bi bi-funnel"></i>
                            </div>
                            <div class="asset-type-select-wrap">
                                <select id="assetTypeFilter" class="form-select" aria-label="Asset type">
                                    <option value="">All Asset Types</option>
                                </select>
                            </div>
                            <button type="button" class="btn btn-outline-secondary px-3 btn-filter asset-clear-filters" id="assetClearFilters" style="display:none;">
                                <i class="bi bi-x-circle"></i> <span class="btn-text">Clear</span>
                            </button>
                            <button type="button" class="btn-asset-primary" data-bs-toggle="modal" data-bs-target="#assignAssetModal">
                                <i class="bi bi-person-plus-fill"></i> <span class="btn-text">Quick Assign</span>
                            </button>
                            <button type="button" class="btn-asset-primary" style="background: #1e293b;" data-bs-toggle="modal" data-bs-target="#addAssetModal">
                                <i class="bi bi-plus-circle-fill"></i> <span class="btn-text">Register</span>
                            </button>
                            <div class="page-size-selector">
                                <select id="assets-limit" class="form-select" aria-label="Rows per page">
                                    <option value="10" selected>10</option>
                                    <option value="50">50</option>
                                    <option value="100">100</option>
                                    <option value="200">200</option>
                                    <option value="300">300</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- View tabs -->
        <div class="assets-tabs-bar">
            <ul class="nav nav-tabs asset-tabs" id="assetTabs" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active" id="holdings-tab" data-bs-toggle="tab" data-bs-target="#holdings" type="button" role="tab">Active Assignments</button>
                </li>
                <li class="nav-item">
                    <button type="button" class="nav-link" id="library-tab" data-bs-toggle="tab" data-bs-target="#library" role="tab">Asset Library</button>
                </li>
            </ul>
        </div>

        <!-- Table section -->
        <div class="user-table-container">
            <div class="tab-content" id="assetTabsContent">
                <div class="tab-pane fade show active" id="holdings" role="tabpanel">
                    <div class="user-table-scroll-wrapper d-none d-md-block">
                        <table class="user-data-table unified-table">
                            <thead>
                                <tr>
                                    <th>Asset Details</th>
                                    <th>Serial Number</th>
                                    <th>Assigned To</th>
                                    <th>Assigned Date</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody id="holdingsBody"></tbody>
                        </table>
                    </div>
                    <div class="asset-mobile-list d-md-none" id="holdingsMobileList"></div>
                </div>
                <div class="tab-pane fade" id="library" role="tabpanel">
                    <div class="user-table-scroll-wrapper d-none d-md-block">
                        <table class="user-data-table unified-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Asset Name</th>
                                    <th>Type</th>
                                    <th>Serial Number</th>
                                    <th>Status</th>
                                    <th class="text-center">Added At</th>
                                </tr>
                            </thead>
                            <tbody id="libraryBody"></tbody>
                        </table>
                    </div>
                    <div class="asset-mobile-list d-md-none" id="libraryMobileList"></div>
                </div>
            </div>
        </div>

        <!-- Pagination (matches attendance report) -->
        <div class="pagination-section py-4" id="assetsPaginationSection">
            <div class="pagination-info text-center mb-3 text-muted" id="assetsPaginationInfo" style="font-weight: 500;"></div>
            <div class="pagination-controls d-flex justify-content-center gap-2" id="assetsPaginationControls"></div>
        </div>
    </div>
</div>

<!-- Mobile bottom toolbar (matches attendance report) -->
<nav class="asset-mobile-bottom-nav d-md-none" aria-label="Asset actions">
    <button type="button" class="asset-mobile-nav-btn asset-mobile-type-btn" id="assetMobileTypeBtn">
        <i class="bi bi-tags-fill"></i>
        <span>Type</span>
    </button>
    <button type="button" class="asset-mobile-nav-btn asset-mobile-status-btn" id="assetMobileStatusBtn">
        <i class="bi bi-ui-checks-grid"></i>
        <span>Status</span>
    </button>
    <button type="button" class="asset-mobile-nav-btn asset-mobile-actions-btn" id="assetMobileActionsBtn">
        <i class="bi bi-plus-circle-fill"></i>
        <span>Actions</span>
    </button>
</nav>

<!-- Mobile type filter sheet -->
<div id="assetMobileTypeSheet" class="asset-mobile-sheet d-md-none" aria-hidden="true">
    <div class="asset-mobile-sheet-backdrop" id="assetMobileTypeBackdrop"></div>
    <div class="asset-mobile-sheet-panel" role="dialog" aria-modal="true" aria-labelledby="assetMobileTypeTitle">
        <h6 id="assetMobileTypeTitle">Filter by Asset Type</h6>
        <div class="asset-mobile-sheet-options" id="assetMobileTypeOptions"></div>
    </div>
</div>

<!-- Mobile status filter sheet -->
<div id="assetMobileStatusSheet" class="asset-mobile-sheet d-md-none" aria-hidden="true">
    <div class="asset-mobile-sheet-backdrop" id="assetMobileStatusBackdrop"></div>
    <div class="asset-mobile-sheet-panel" role="dialog" aria-modal="true" aria-labelledby="assetMobileStatusTitle">
        <h6 id="assetMobileStatusTitle">Filter by Status</h6>
        <div class="asset-mobile-sheet-options">
            <button type="button" class="asset-mobile-sheet-option" data-status="">All Status</button>
            <button type="button" class="asset-mobile-sheet-option" data-status="assigned">Assigned</button>
            <button type="button" class="asset-mobile-sheet-option" data-status="available">Available</button>
            <button type="button" class="asset-mobile-sheet-option" data-status="faulty">Faulty</button>
        </div>
    </div>
</div>

<!-- Mobile actions sheet -->
<div id="assetMobileActionsSheet" class="asset-mobile-sheet d-md-none" aria-hidden="true">
    <div class="asset-mobile-sheet-backdrop" id="assetMobileActionsBackdrop"></div>
    <div class="asset-mobile-sheet-panel" role="dialog" aria-modal="true" aria-labelledby="assetMobileActionsTitle">
        <h6 id="assetMobileActionsTitle">Asset Actions</h6>
        <button type="button" class="asset-mobile-action-btn assign" id="assetMobileAssignBtn">
            <i class="bi bi-person-plus-fill"></i> Quick Assign
        </button>
        <button type="button" class="asset-mobile-action-btn register" id="assetMobileRegisterBtn">
            <i class="bi bi-plus-circle-fill"></i> Register Asset
        </button>
    </div>
</div>

<button type="button" id="assetFloatingClearFilters" class="floating-clear-btn d-md-none" style="display:none;">
    <i class="bi bi-x-circle"></i> Clear Filters
</button>

<!-- Modal: Add New Asset -->
<div class="modal fade" id="addAssetModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" style="font-weight: 700;">Register Company Asset</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addAssetForm">
                <div class="modal-body">
                    <input type="hidden" name="add_asset_action" value="1">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Asset Name</label>
                        <input type="text" name="asset_name" class="form-control" placeholder="e.g. MacBook Air M2" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Asset Type</label>
                        <select name="asset_type" class="form-select" required>
                            <option value="Laptop">Laptop</option>
                            <option value="Mobile">Mobile Phone</option>
                            <option value="SIM">SIM Card</option>
                            <option value="Mouse">Mouse/Keyboard</option>
                            <option value="Headset">Headset</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Serial Number</label>
                        <input type="text" name="serial_number" class="form-control" placeholder="Unique S/N or ID" required>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal" style="border-radius: 10px;">Cancel</button>
                    <button type="submit" class="btn btn-asset-primary px-4">Register Asset</button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- Modal: Assign Asset -->
<div class="modal fade" id="assignAssetModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" style="font-weight: 700;">Assign Asset to Employee</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="assignAssetForm">
                <div class="modal-body">
                    <input type="hidden" name="assign_asset_action" value="1">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Select Asset</label>
                        <select name="asset_id" id="availableAssetSelect" class="form-select" required>
                            <option value="">-- Choose Available Asset --</option>
                            <?php foreach($assets_available as $a): ?>
                                <option value="<?php echo $a['id']; ?>"><?php echo htmlspecialchars($a['asset_name'] . " (" . $a['serial_number'] . ")"); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Assign to Employee</label>
                        <select name="employee_id" class="form-select" required>
                            <option value="">-- Choose Employee --</option>
                            <?php foreach($employees as $e): ?>
                                <option value="<?php echo $e['id']; ?>"><?php echo htmlspecialchars($e['username'] . " [#" . $e['employee_id'] . "]"); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Assignment Date</label>
                        <input type="date" name="assigned_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Notes</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="e.g. Condition check, mouse included" style="border-radius: 12px;"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal" style="border-radius: 10px;">Cancel</button>
                    <button type="submit" class="btn btn-success px-4" style="border-radius: 10px; font-weight:600;">Complete Assignment</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="assets/js/summary-cards.js?v=<?php echo time(); ?>"></script>
<script>
    let selectedAssetType = '';
    let selectedAssetStatus = '';
    let assetSearchQuery = '';
    let holdingsData = [];
    let libraryData = [];
    let assetSearchTimer = null;
    let holdingsCurrentPage = 1;
    let libraryCurrentPage = 1;
    let assetsPerPage = 10;
    const assetAllowedLimits = [10, 50, 100, 200, 300];
    const assetEmployeeLookup = <?php echo json_encode($asset_employee_lookup, JSON_UNESCAPED_UNICODE); ?>;

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, ch => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
        }[ch]));
    }

    function getEmployeeUserId(row) {
        const raw = row.employee_user_id ?? row.employee_id ?? row.user_id;
        const id = parseInt(raw, 10);
        return Number.isFinite(id) && id > 0 ? id : 0;
    }

    function resolveEmployeeUserId(row) {
        const directId = getEmployeeUserId(row);
        if (directId > 0) {
            return directId;
        }
        const nameKey = String(row.employee_name ?? '').trim().toLowerCase();
        const codeKey = String(row.emp_code ?? '').trim().toLowerCase();
        if (nameKey && assetEmployeeLookup.byName && assetEmployeeLookup.byName[nameKey]) {
            return parseInt(assetEmployeeLookup.byName[nameKey], 10);
        }
        if (codeKey && assetEmployeeLookup.byCode && assetEmployeeLookup.byCode[codeKey]) {
            return parseInt(assetEmployeeLookup.byCode[codeKey], 10);
        }
        return 0;
    }

    let assetOverviewPasswordVisible = false;
    let assetOverviewPasswordValue = '';

    function setAssetOverviewText(id, value) {
        const el = document.getElementById(id);
        if (el) el.textContent = value || '---';
    }

    function renderAssetOverviewSalary(user) {
        const container = document.getElementById('assetOverviewSalaryStructure');
        if (!container) return;

        const monthlyCTC = parseFloat(user.salary) || 0;
        const yearlyCTC = monthlyCTC * 12;
        const hasManual = (
            (user.first_amount && parseFloat(user.first_amount) > 0) ||
            (user.second_amount && parseFloat(user.second_amount) > 0) ||
            (user.third_amount && parseFloat(user.third_amount) > 0) ||
            (user.fourth_amount && parseFloat(user.fourth_amount) > 0) ||
            (user.fifth_amount && parseFloat(user.fifth_amount) > 0) ||
            (user.sixth_amount && parseFloat(user.sixth_amount) > 0)
        );
        const basic = hasManual ? (parseFloat(user.first_amount) || 0) : Math.round(monthlyCTC * 0.5);
        const hra = hasManual ? (parseFloat(user.second_amount) || 0) : Math.round(monthlyCTC * 0.2);
        const conveyance = hasManual ? (parseFloat(user.third_amount) || 0) : Math.round(monthlyCTC * 0.07);
        const pfEmployer = hasManual ? (parseFloat(user.fifth_amount) || 0) : Math.min(1800, Math.round(basic * 0.12));
        const monthlyGross = monthlyCTC - pfEmployer;
        const specialAllowance = hasManual ? (parseFloat(user.fourth_amount) || 0) : (monthlyGross - (basic + hra + conveyance));
        const totalDeds = hasManual ? (parseFloat(user.sixth_amount) || 0) : (pfEmployer + 200 + 817);
        const netPay = monthlyGross - totalDeds;
        const fmt = n => '₹' + Number(n).toLocaleString('en-IN');

        container.innerHTML = `
            <div style="padding: 16px 20px;">
                <table class="salary-table" style="width:100%;">
                    <thead>
                        <tr>
                            <th style="text-align:left;">Earnings & Benefits</th>
                            <th style="text-align:right;">Monthly</th>
                            <th style="text-align:right;">Yearly</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="total-row">
                            <td class="row-label">Total CTC</td>
                            <td class="row-value">${fmt(monthlyCTC)}</td>
                            <td class="row-yearly">${fmt(yearlyCTC)}</td>
                        </tr>
                        <tr><td class="row-label">Basic Salary</td><td class="row-value">${fmt(basic)}</td><td class="row-yearly">${fmt(basic * 12)}</td></tr>
                        <tr><td class="row-label">HRA</td><td class="row-value">${fmt(hra)}</td><td class="row-yearly">${fmt(hra * 12)}</td></tr>
                        <tr><td class="row-label">Conveyance</td><td class="row-value">${fmt(conveyance)}</td><td class="row-yearly">${fmt(conveyance * 12)}</td></tr>
                        <tr><td class="row-label">Special Allowance</td><td class="row-value">${fmt(specialAllowance)}</td><td class="row-yearly">${fmt(specialAllowance * 12)}</td></tr>
                        <tr class="highlight-row"><td class="row-label">PF (Employer)</td><td class="row-value">${fmt(pfEmployer)}</td><td class="row-yearly">${fmt(pfEmployer * 12)}</td></tr>
                        <tr class="fw-bold"><td class="row-label">Monthly Gross</td><td class="row-value">${fmt(monthlyGross)}</td><td class="row-yearly">${fmt(monthlyGross * 12)}</td></tr>
                        <tr class="deduction-row"><td class="row-label">Total Deductions</td><td class="row-value">${fmt(totalDeds)}</td><td class="row-yearly">${fmt(totalDeds * 12)}</td></tr>
                        <tr class="net-row"><td class="row-label">Net Pay</td><td class="row-value">${fmt(netPay)}</td><td class="row-yearly">${fmt(netPay * 12)}</td></tr>
                    </tbody>
                </table>
            </div>
        `;
    }

    function populateAssetOverviewDrawer(user) {
        setAssetOverviewText('assetOverviewUserName', user.username || 'Unknown User');
        const statusEl = document.getElementById('assetOverviewStatusText');
        const createdWrap = document.getElementById('assetOverviewCreatedAtWrap');
        const inactiveWrap = document.getElementById('assetOverviewInactiveAtWrap');

        if (user.is_active == 1) {
            if (statusEl) {
                statusEl.textContent = 'Active';
                statusEl.className = 'fw-bold text-success';
            }
            if (createdWrap) createdWrap.style.display = '';
            if (inactiveWrap) inactiveWrap.style.display = 'none';
            setAssetOverviewText('assetOverviewCreatedAt', user.created_at);
        } else {
            if (statusEl) {
                statusEl.textContent = 'Inactive';
                statusEl.className = 'fw-bold text-danger';
            }
            if (createdWrap) createdWrap.style.display = 'none';
            if (inactiveWrap) inactiveWrap.style.display = '';
            setAssetOverviewText('assetOverviewInactiveAt', user.inactive_at);
        }

        setAssetOverviewText('assetOverviewEmail', user.useremail);
        setAssetOverviewText('assetOverviewContact', user.phonenumber);
        setAssetOverviewText('assetOverviewDob', user.dob);
        setAssetOverviewText('assetOverviewUniqueId', user.uniqueid);
        setAssetOverviewText('assetOverviewEmployeeId', user.employee_id);
        setAssetOverviewText('assetOverviewDoj', user.doj);
        setAssetOverviewText('assetOverviewSalary', user.salary ? `₹${user.salary}` : '---');
        setAssetOverviewText('assetOverviewProject', user.project_name || 'Unassigned');
        setAssetOverviewText('assetOverviewProjectType', user.project_type);
        setAssetOverviewText('assetOverviewRole', user.user_type);
        setAssetOverviewText('assetOverviewAssignedUsers', user.assign_user);

        assetOverviewPasswordValue = user.epassword || '';
        assetOverviewPasswordVisible = false;
        setAssetOverviewText('assetOverviewPassword', '••••••••');
        const pwdIcon = document.getElementById('assetOverviewPasswordIcon');
        if (pwdIcon) pwdIcon.className = 'bi bi-eye';

        renderAssetOverviewSalary(user);
    }

    function closeAssetEmployeeOverview() {
        const overlay = document.getElementById('assetOverviewOverlay');
        const drawer = document.getElementById('assetOverviewDrawer');
        if (overlay) {
            overlay.classList.remove('active');
            overlay.setAttribute('aria-hidden', 'true');
        }
        if (drawer) {
            drawer.classList.remove('active');
            drawer.setAttribute('aria-hidden', 'true');
        }
        document.body.style.overflow = '';
        document.body.classList.remove('asset-overview-open');
    }
    window.closeAssetEmployeeOverview = closeAssetEmployeeOverview;

    function openAssetEmployeeProfile(userId) {
        const parsedId = parseInt(userId, 10);
        if (!Number.isFinite(parsedId) || parsedId <= 0) return;

        const overlay = document.getElementById('assetOverviewOverlay');
        const drawer = document.getElementById('assetOverviewDrawer');
        if (!overlay || !drawer) return;

        overlay.classList.add('active');
        drawer.classList.add('active');
        overlay.setAttribute('aria-hidden', 'false');
        drawer.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        document.body.classList.add('asset-overview-open');

        setAssetOverviewText('assetOverviewUserName', 'Loading...');
        setAssetOverviewText('assetOverviewStatusText', '...');
        const salaryBox = document.getElementById('assetOverviewSalaryStructure');
        if (salaryBox) {
            salaryBox.innerHTML = '<div class="text-center p-4 text-muted"><div class="spinner-border spinner-border-sm me-2" role="status"></div>Loading...</div>';
        }

        fetch(`fetch_users.php?id=${parsedId}`)
            .then(res => res.json())
            .then(user => {
                if (!user || user.error) {
                    setAssetOverviewText('assetOverviewUserName', 'User not found');
                    if (salaryBox) salaryBox.innerHTML = '<div class="text-center p-4 text-muted">Unable to load employee details.</div>';
                    return;
                }
                populateAssetOverviewDrawer(user);
            })
            .catch(() => {
                setAssetOverviewText('assetOverviewUserName', 'Error loading user');
                if (salaryBox) salaryBox.innerHTML = '<div class="text-center p-4 text-muted">Failed to load employee details.</div>';
            });
    }
    window.openAssetEmployeeProfile = openAssetEmployeeProfile;

    document.addEventListener('click', function(e) {
        const trigger = e.target.closest('.asset-assigned-user-link');
        if (!trigger) return;
        e.preventDefault();
        e.stopPropagation();
        const userId = trigger.getAttribute('data-employee-user-id');
        if (userId) {
            openAssetEmployeeProfile(userId);
        }
    }, true);

    function renderAssignedToHtml(row) {
        const userId = resolveEmployeeUserId(row);
        const name = escapeHtml(row.employee_name);
        const code = escapeHtml(row.emp_code);
        if (!userId) {
            return `<span style="color: var(--primary-teal); font-weight: 700;">${name} <span class="small text-muted">[${code}]</span></span>`;
        }
        return `<button type="button" class="asset-assigned-user-link" data-employee-user-id="${userId}" onclick="openAssetEmployeeProfile(${userId}); return false;" title="View employee overview">${name} <span class="small text-muted">[${code}]</span></button>`;
    }

    function normalizeAssetType(value) {
        return String(value ?? '').trim().toLowerCase();
    }

    function getSearchHaystack(row, mode) {
        const fields = [row.asset_name, row.asset_type, row.serial_number, row.id];
        if (mode === 'holdings') {
            fields.push(row.employee_name, row.emp_code, row.assigned_date);
        } else {
            fields.push(row.status, row.created_at);
        }
        return fields.map(v => String(v ?? '').toLowerCase()).join(' ');
    }

    function applyAssetFilters(data, mode) {
        let filtered = Array.isArray(data) ? [...data] : [];
        if (selectedAssetType) {
            filtered = filtered.filter(row => normalizeAssetType(row.asset_type) === selectedAssetType);
        }
        if (mode === 'library' && selectedAssetStatus) {
            filtered = filtered.filter(row => String(row.status ?? '').toLowerCase() === selectedAssetStatus);
        }
        if (assetSearchQuery) {
            const q = assetSearchQuery.toLowerCase().trim();
            filtered = filtered.filter(row => getSearchHaystack(row, mode).includes(q));
        }
        return filtered;
    }

    function getActiveAssetTabMode() {
        return $('#library-tab').hasClass('active') ? 'library' : 'holdings';
    }

    function getAssetPageState(mode) {
        return mode === 'library'
            ? { current: libraryCurrentPage, set: v => { libraryCurrentPage = v; } }
            : { current: holdingsCurrentPage, set: v => { holdingsCurrentPage = v; } };
    }

    function paginateAssetRows(data, mode) {
        const totalEntries = data.length;
        const totalPages = Math.max(1, Math.ceil(totalEntries / assetsPerPage) || 1);
        const pageState = getAssetPageState(mode);
        if (pageState.current > totalPages) {
            pageState.set(totalPages);
        }
        if (pageState.current < 1) {
            pageState.set(1);
        }
        const startIndex = (pageState.current - 1) * assetsPerPage;
        const pageRows = data.slice(startIndex, startIndex + assetsPerPage);
        return {
            rows: pageRows,
            totalEntries,
            totalPages,
            currentPage: pageState.current,
            showingStart: totalEntries ? startIndex + 1 : 0,
            showingEnd: totalEntries ? Math.min(startIndex + assetsPerPage, totalEntries) : 0
        };
    }

    function renderAssetPagination(meta) {
        const section = $('#assetsPaginationSection');
        if (!meta.totalEntries) {
            section.hide();
            return;
        }
        section.show();
        $('#assetsPaginationInfo').text(
            `Showing ${meta.showingStart} to ${meta.showingEnd} of ${meta.totalEntries} entries`
        );

        let html = '';
        const prevDisabled = meta.currentPage <= 1 ? 'disabled' : '';
        const nextDisabled = meta.currentPage >= meta.totalPages ? 'disabled' : '';
        html += `<button type="button" class="page-btn ${prevDisabled}" data-asset-page="${meta.currentPage - 1}">←</button>`;
        for (let i = 1; i <= meta.totalPages; i++) {
            html += `<button type="button" class="page-btn ${meta.currentPage === i ? 'active' : ''}" data-asset-page="${i}">${i}</button>`;
        }
        html += `<button type="button" class="page-btn ${nextDisabled}" data-asset-page="${meta.currentPage + 1}">→</button>`;
        $('#assetsPaginationControls').html(html);
    }

    function goToAssetPage(page) {
        const mode = getActiveAssetTabMode();
        const filtered = applyAssetFilters(mode === 'library' ? libraryData : holdingsData, mode);
        const totalPages = Math.max(1, Math.ceil(filtered.length / assetsPerPage) || 1);
        if (page < 1 || page > totalPages) return;
        getAssetPageState(mode).set(page);
        refreshViews();
    }

    function resetAssetPagination() {
        holdingsCurrentPage = 1;
        libraryCurrentPage = 1;
    }

    function updateSummaryCards() {
        const total = libraryData.length;
        const assigned = libraryData.filter(row => String(row.status ?? '').toLowerCase() === 'assigned').length;
        const available = libraryData.filter(row => String(row.status ?? '').toLowerCase() === 'available').length;
        $('#assetTotalCount').text(total);
        $('#assetAssignedCount').text(assigned);
        $('#assetAvailableCount').text(available);
    }

    function updateActiveSummaryCard() {
        $('#assetSummaryAssigned, #assetSummaryAvailable').removeClass('active-filter');
        if (selectedAssetStatus === 'assigned') {
            $('#assetSummaryAssigned').addClass('active-filter');
        } else if (selectedAssetStatus === 'available') {
            $('#assetSummaryAvailable').addClass('active-filter');
        }
    }

    function updateClearFiltersButton() {
        const hasFilters = !!(assetSearchQuery || selectedAssetType || selectedAssetStatus);
        $('#assetClearFilters').css('display', hasFilters ? 'inline-flex' : 'none');
        $('#assetFloatingClearFilters').css('display', hasFilters ? 'flex' : 'none');
        $('#assetMobileTypeBtn').toggleClass('active', !!selectedAssetType);
        $('#assetMobileStatusBtn').toggleClass('active', !!selectedAssetStatus);
    }

    function toggleStatusFilterVisibility() {
        const isLibraryTab = $('#library-tab').hasClass('active');
        $('#assetStatusFilterWrap').toggle(isLibraryTab);
        $('#assetMobileStatusBtn').toggle(isLibraryTab);
    }

    function closeAssetMobileSheets() {
        $('.asset-mobile-sheet').removeClass('active').attr('aria-hidden', 'true');
        $('.asset-mobile-nav-btn').removeClass('active');
    }

    function openAssetMobileSheet(sheetId, btnId) {
        closeAssetMobileSheets();
        const sheet = document.getElementById(sheetId);
        if (!sheet) return;
        sheet.classList.add('active');
        sheet.setAttribute('aria-hidden', 'false');
        if (btnId) document.getElementById(btnId)?.classList.add('active');
    }

    function populateAssetMobileTypeOptions() {
        const container = $('#assetMobileTypeOptions');
        container.empty();
        const types = Array.from(new Set(
            [...holdingsData, ...libraryData]
                .map(row => String(row.asset_type ?? '').trim())
                .filter(Boolean)
        )).sort((a, b) => a.localeCompare(b));

        container.append(
            `<button type="button" class="asset-mobile-sheet-option${selectedAssetType ? '' : ' selected'}" data-type="">All Asset Types</button>`
        );
        types.forEach(type => {
            const val = normalizeAssetType(type);
            const selected = selectedAssetType === val ? ' selected' : '';
            container.append(
                `<button type="button" class="asset-mobile-sheet-option${selected}" data-type="${escapeHtml(val)}">${escapeHtml(type)}</button>`
            );
        });
    }

    function syncAssetMobileStatusSheet() {
        $('#assetMobileStatusSheet .asset-mobile-sheet-option').each(function() {
            $(this).toggleClass('selected', String($(this).data('status') || '') === selectedAssetStatus);
        });
    }

    function toggleMobileAssetCard(btn) {
        const card = btn.closest('.mobile-asset-card');
        const panel = card?.querySelector('.asset-card-detail-panel');
        const icon = btn.querySelector('i');
        if (!card || !panel) return;

        const isOpen = card.classList.contains('expanded');
        document.querySelectorAll('.mobile-asset-card.expanded').forEach(c => {
            if (c === card) return;
            c.classList.remove('expanded');
            const p = c.querySelector('.asset-card-detail-panel');
            if (p) p.style.display = 'none';
            const b = c.querySelector('.asset-card-expand-btn');
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
    window.toggleMobileAssetCard = toggleMobileAssetCard;

    function updateTypeFilterOptions() {
        const select = $('#assetTypeFilter');
        const currentValue = select.val() || '';
        const types = Array.from(new Set(
            [...holdingsData, ...libraryData]
                .map(row => String(row.asset_type ?? '').trim())
                .filter(Boolean)
        )).sort((a, b) => a.localeCompare(b));

        select.empty().append('<option value="">All Asset Types</option>');
        types.forEach(type => {
            select.append(
                $('<option></option>')
                    .val(normalizeAssetType(type))
                    .text(type)
            );
        });
        select.val(currentValue);
        populateAssetMobileTypeOptions();
    }

    function renderHoldingsMobile(data) {
        const list = $('#holdingsMobileList');
        list.empty();
        if (!data.length) {
            const emptyMsg = assetSearchQuery || selectedAssetType
                ? 'No assignments match your search or filters.'
                : 'No active assignments found.';
            list.append(`
                <div class="asset-mobile-empty">
                    <i class="bi bi-inbox fs-1 mb-2 d-block"></i>
                    ${emptyMsg}
                </div>
            `);
            return;
        }
        data.forEach(row => {
            list.append(`
                <div class="mobile-asset-card">
                    <div class="asset-card-main">
                        <div class="asset-card-info">
                            <div class="asset-card-meta-line">
                                <span class="asset-card-meta">${escapeHtml(row.serial_number)}</span>
                                <span class="asset-card-meta-dot">·</span>
                                <span class="asset-card-meta">${escapeHtml(row.assigned_date)}</span>
                            </div>
                            <div class="asset-card-name-line">
                                <span class="asset-card-name">${escapeHtml(row.asset_name)}</span>
                                <span class="asset-type-pill">${escapeHtml(row.asset_type)}</span>
                            </div>
                        </div>
                        <button type="button" class="asset-card-expand-btn" onclick="toggleMobileAssetCard(this)" title="Show details" aria-expanded="false">
                            <i class="bi bi-chevron-down"></i>
                        </button>
                    </div>
                    <div class="asset-card-detail-panel">
                        <div class="asset-mobile-detail-grid">
                            <div class="asset-mobile-detail-item full">
                                <span class="asset-mobile-detail-label">Serial Number</span>
                                <span class="asset-mobile-detail-value"><span class="serial-badge">${escapeHtml(row.serial_number)}</span></span>
                            </div>
                            <div class="asset-mobile-detail-item">
                                <span class="asset-mobile-detail-label">Assigned To</span>
                                <span class="asset-mobile-detail-value">${renderAssignedToHtml(row)}</span>
                            </div>
                            <div class="asset-mobile-detail-item">
                                <span class="asset-mobile-detail-label">Employee ID</span>
                                <span class="asset-mobile-detail-value">${escapeHtml(row.emp_code)}</span>
                            </div>
                            <div class="asset-mobile-detail-item full">
                                <span class="asset-mobile-detail-label">Assigned Date</span>
                                <span class="asset-mobile-detail-value">${escapeHtml(row.assigned_date)}</span>
                            </div>
                        </div>
                        <button type="button" class="asset-card-action-btn asset-card-return-btn" onclick="returnAsset(${row.id})">
                            <i class="bi bi-arrow-return-left"></i> Return Asset
                        </button>
                    </div>
                </div>
            `);
        });
    }

    function renderLibraryMobile(data) {
        const list = $('#libraryMobileList');
        list.empty();
        if (!data.length) {
            const emptyMsg = assetSearchQuery || selectedAssetType || selectedAssetStatus
                ? 'No assets match your search or filters.'
                : 'No assets in library.';
            list.append(`
                <div class="asset-mobile-empty">
                    <i class="bi bi-inbox fs-1 mb-2 d-block"></i>
                    ${emptyMsg}
                </div>
            `);
            return;
        }
        data.forEach(row => {
            const statusClass = 'status-' + String(row.status).toLowerCase();
            list.append(`
                <div class="mobile-asset-card">
                    <div class="asset-card-main">
                        <div class="asset-card-info">
                            <div class="asset-card-meta-line">
                                <span class="asset-card-meta">#${escapeHtml(row.id)}</span>
                                <span class="asset-card-meta-dot">·</span>
                                <span class="asset-card-meta">${escapeHtml(row.asset_type)}</span>
                            </div>
                            <div class="asset-card-name-line">
                                <span class="asset-card-name">${escapeHtml(row.asset_name)}</span>
                                <span class="status-badge ${statusClass}">${escapeHtml(row.status)}</span>
                            </div>
                        </div>
                        <button type="button" class="asset-card-expand-btn" onclick="toggleMobileAssetCard(this)" title="Show details" aria-expanded="false">
                            <i class="bi bi-chevron-down"></i>
                        </button>
                    </div>
                    <div class="asset-card-detail-panel">
                        <div class="asset-mobile-detail-grid">
                            <div class="asset-mobile-detail-item full">
                                <span class="asset-mobile-detail-label">Serial Number</span>
                                <span class="asset-mobile-detail-value"><span class="serial-badge">${escapeHtml(row.serial_number)}</span></span>
                            </div>
                            <div class="asset-mobile-detail-item full">
                                <span class="asset-mobile-detail-label">Added At</span>
                                <span class="asset-mobile-detail-value">${escapeHtml(row.created_at)}</span>
                            </div>
                        </div>
                    </div>
                </div>
            `);
        });
    }

    function renderHoldingsTable(data) {
        const body = $('#holdingsBody');
        body.empty();
        if (!data.length) {
            const emptyMsg = assetSearchQuery || selectedAssetType
                ? 'No assignments match your search or filters.'
                : 'No active assignments found.';
            body.append(`<tr><td colspan="5" class="text-center py-5 text-muted">${emptyMsg}</td></tr>`);
            return;
        }
        data.forEach(row => {
            body.append(`
                <tr class="user-data-row">
                    <td>
                        <div class="fw-bold">${escapeHtml(row.asset_name)}</div>
                        <div class="small text-muted">${escapeHtml(row.asset_type)}</div>
                    </td>
                    <td><span class="serial-badge">${escapeHtml(row.serial_number)}</span></td>
                    <td class="fw-bold">${renderAssignedToHtml(row)}</td>
                    <td>${escapeHtml(row.assigned_date)}</td>
                    <td class="text-center">
                        <button onclick="returnAsset(${row.id})" class="btn btn-outline-danger btn-sm" style="border-radius: 10px; font-weight:700;">
                            <i class="bi bi-arrow-return-left"></i> Return
                        </button>
                    </td>
                </tr>
            `);
        });
    }

    function renderLibraryTable(data) {
        const body = $('#libraryBody');
        body.empty();
        if (!data.length) {
            const emptyMsg = assetSearchQuery || selectedAssetType || selectedAssetStatus
                ? 'No assets match your search or filters.'
                : 'No assets in library.';
            body.append(`<tr><td colspan="6" class="text-center py-5 text-muted">${emptyMsg}</td></tr>`);
            return;
        }
        data.forEach(row => {
            const statusClass = 'status-' + String(row.status).toLowerCase();
            body.append(`
                <tr class="user-data-row">
                    <td class="text-muted">#${escapeHtml(row.id)}</td>
                    <td class="fw-bold">${escapeHtml(row.asset_name)}</td>
                    <td>${escapeHtml(row.asset_type)}</td>
                    <td><span class="serial-badge">${escapeHtml(row.serial_number)}</span></td>
                    <td><span class="status-badge ${statusClass}">${escapeHtml(row.status)}</span></td>
                    <td class="text-center text-muted small">${escapeHtml(row.created_at)}</td>
                </tr>
            `);
        });
    }

    function refreshViews() {
        const holdingsFiltered = applyAssetFilters(holdingsData, 'holdings');
        const libraryFiltered = applyAssetFilters(libraryData, 'library');
        const holdingsPage = paginateAssetRows(holdingsFiltered, 'holdings');
        const libraryPage = paginateAssetRows(libraryFiltered, 'library');

        renderHoldingsTable(holdingsPage.rows);
        renderHoldingsMobile(holdingsPage.rows);
        renderLibraryTable(libraryPage.rows);
        renderLibraryMobile(libraryPage.rows);

        const activeMode = getActiveAssetTabMode();
        renderAssetPagination(activeMode === 'library' ? libraryPage : holdingsPage);

        updateSummaryCards();
        updateTypeFilterOptions();
        updateActiveSummaryCard();
        updateClearFiltersButton();
    }

    function loadHoldings() {
        return $.ajax({
            url: 'action.php',
            method: 'GET',
            dataType: 'json',
            data: { fetch_active_assignments: 1 },
            success: function(response) {
                holdingsData = Array.isArray(response) ? response : [];
            }
        });
    }

    function loadLibrary() {
        return $.ajax({
            url: 'action.php',
            method: 'GET',
            data: { fetch_assets_list: 1 },
            success: function(response) {
                const parsed = (typeof response === 'string') ? JSON.parse(response) : response;
                libraryData = Array.isArray(parsed) ? parsed : (Array.isArray(parsed.data) ? parsed.data : []);
            }
        });
    }

    function applySummaryStatusFilter(status) {
        selectedAssetStatus = (selectedAssetStatus === status) ? '' : status;
        $('#assetStatusFilter').val(selectedAssetStatus);
        resetAssetPagination();
        const libraryTab = document.getElementById('library-tab');
        if (libraryTab && typeof bootstrap !== 'undefined') {
            bootstrap.Tab.getOrCreateInstance(libraryTab).show();
        } else {
            $('#library-tab').tab('show');
        }
        toggleStatusFilterVisibility();
        refreshViews();
    }

    function clearAssetFilters() {
        assetSearchQuery = '';
        selectedAssetType = '';
        selectedAssetStatus = '';
        $('#assetSearchInput').val('');
        $('#assetTypeFilter').val('');
        $('#assetStatusFilter').val('');
        resetAssetPagination();
        refreshViews();
    }
    function returnAsset(assignmentId) {
        Swal.fire({
            title: 'Confirm Asset Return',
            text: "Mark this asset as returned to inventory?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#227477',
            confirmButtonText: 'Yes, Return it'
        }).then((result) => {
            if (result.isConfirmed) {
                const today = new Date().toISOString().split('T')[0];
                $.ajax({
                    url: 'action.php',
                    method: 'POST',
                    data: { return_asset_action: 1, assignment_id: assignmentId, returned_date: today },
                    success: function(res) {
                        $('#assetTabsContent').html(res); // Temporary view refresh or just reload data
                        Swal.fire('Success', 'Asset returned successfully!', 'success');
                        loadAll();
                        setTimeout(() => window.location.reload(), 1000); // Reload to update dropdowns
                    }
                });
            }
        });
    }
    function loadAll() {
        $.when(loadHoldings(), loadLibrary()).always(function() {
            refreshViews();
        });
    }
    // Forms Handling
    $('#addAssetForm').on('submit', function(e){
        e.preventDefault();
        $.ajax({
            url: 'action.php',
            method: 'POST',
            data: $(this).serialize(),
            success: function(res) {
                $('#addAssetModal').modal('hide');
                Swal.fire('Registered!', 'Asset added to inventory.', 'success');
                loadAll();
                setTimeout(() => window.location.reload(), 1000);
            }
        });
    });
    $('#assignAssetForm').on('submit', function(e){
        e.preventDefault();
        $.ajax({
            url: 'action.php',
            method: 'POST',
            data: $(this).serialize(),
            success: function(res) {
                $('#assignAssetModal').modal('hide');
                Swal.fire('Assigned!', 'Asset linked to employee.', 'success');
                loadAll();
                setTimeout(() => window.location.reload(), 1000);
            }
        });
    });
    $(document).ready(function(){
        document.body.classList.add('company-assets-page');
        ['assignAssetModal', 'addAssetModal', 'assetOverviewOverlay', 'assetOverviewDrawer'].forEach(id => {
            const modalEl = document.getElementById(id);
            if (modalEl && modalEl.parentElement !== document.body) {
                document.body.appendChild(modalEl);
            }
        });
        const assetOverviewOverlay = document.getElementById('assetOverviewOverlay');
        const assetOverviewDrawer = document.getElementById('assetOverviewDrawer');
        if (assetOverviewOverlay && !assetOverviewOverlay.dataset.boundClose) {
            assetOverviewOverlay.dataset.boundClose = '1';
            assetOverviewOverlay.addEventListener('click', closeAssetEmployeeOverview);
        }
        if (assetOverviewDrawer && !assetOverviewDrawer.dataset.boundStop) {
            assetOverviewDrawer.dataset.boundStop = '1';
            assetOverviewDrawer.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        }
        const assignModalEl = document.getElementById('assignAssetModal');
        if (assignModalEl) {
            assignModalEl.addEventListener('shown.bs.modal', function () {
                document.body.classList.add('assign-asset-page-modal-open');
                closeAssetMobileSheets();
            });
            assignModalEl.addEventListener('hidden.bs.modal', function () {
                document.body.classList.remove('assign-asset-page-modal-open');
            });
        }
        const addModalEl = document.getElementById('addAssetModal');
        if (addModalEl) {
            addModalEl.addEventListener('shown.bs.modal', closeAssetMobileSheets);
        }
        $('#assetTypeFilter').on('change', function() {
            selectedAssetType = normalizeAssetType($(this).val());
            resetAssetPagination();
            refreshViews();
        });
        $('#assetStatusFilter').on('change', function() {
            selectedAssetStatus = String($(this).val() || '').toLowerCase();
            updateActiveSummaryCard();
            resetAssetPagination();
            refreshViews();
        });
        $('#assetSearchInput').on('input', function() {
            clearTimeout(assetSearchTimer);
            assetSearchTimer = setTimeout(function() {
                assetSearchQuery = $('#assetSearchInput').val() || '';
                resetAssetPagination();
                refreshViews();
            }, 250);
        });
        $('#assets-limit').on('change', function() {
            const nextLimit = parseInt($(this).val(), 10);
            assetsPerPage = assetAllowedLimits.includes(nextLimit) ? nextLimit : 10;
            resetAssetPagination();
            refreshViews();
        });
        $('#assetsPaginationControls').on('click', '.page-btn:not(.disabled)', function() {
            const page = parseInt($(this).data('asset-page'), 10);
            if (!Number.isNaN(page)) {
                goToAssetPage(page);
            }
        });
        $('#assetClearFilters, #assetFloatingClearFilters').on('click', clearAssetFilters);

        $('#assetMobileTypeBtn').on('click', function() {
            if ($('#assetMobileTypeSheet').hasClass('active')) {
                closeAssetMobileSheets();
            } else {
                populateAssetMobileTypeOptions();
                openAssetMobileSheet('assetMobileTypeSheet', 'assetMobileTypeBtn');
            }
        });

        $('#assetMobileStatusBtn').on('click', function() {
            if ($('#assetMobileStatusSheet').hasClass('active')) {
                closeAssetMobileSheets();
            } else {
                syncAssetMobileStatusSheet();
                openAssetMobileSheet('assetMobileStatusSheet', 'assetMobileStatusBtn');
            }
        });

        $('#assetMobileActionsBtn').on('click', function() {
            if ($('#assetMobileActionsSheet').hasClass('active')) {
                closeAssetMobileSheets();
            } else {
                openAssetMobileSheet('assetMobileActionsSheet', 'assetMobileActionsBtn');
            }
        });

        $('#assetMobileTypeBackdrop, #assetMobileStatusBackdrop, #assetMobileActionsBackdrop').on('click', closeAssetMobileSheets);

        $('#assetMobileTypeOptions').on('click', '.asset-mobile-sheet-option', function() {
            selectedAssetType = normalizeAssetType($(this).data('type'));
            $('#assetTypeFilter').val(selectedAssetType);
            resetAssetPagination();
            closeAssetMobileSheets();
            refreshViews();
        });

        $('#assetMobileStatusSheet .asset-mobile-sheet-option').on('click', function() {
            selectedAssetStatus = String($(this).data('status') || '').toLowerCase();
            $('#assetStatusFilter').val(selectedAssetStatus);
            updateActiveSummaryCard();
            resetAssetPagination();
            closeAssetMobileSheets();
            refreshViews();
        });

        function openAssetModal(modalId) {
            const modalEl = document.getElementById(modalId);
            if (!modalEl) return;
            closeAssetMobileSheets();
            if (typeof bootstrap !== 'undefined') {
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            } else {
                $(modalEl).modal('show');
            }
        }

        $('#assetMobileAssignBtn').on('click', function() {
            openAssetModal('assignAssetModal');
        });

        $('#assetMobileRegisterBtn').on('click', function() {
            openAssetModal('addAssetModal');
        });

        $('#assetOverviewPasswordToggle').on('click', function() {
            assetOverviewPasswordVisible = !assetOverviewPasswordVisible;
            setAssetOverviewText('assetOverviewPassword', assetOverviewPasswordVisible ? (assetOverviewPasswordValue || '---') : '••••••••');
            const pwdIcon = document.getElementById('assetOverviewPasswordIcon');
            if (pwdIcon) pwdIcon.className = assetOverviewPasswordVisible ? 'bi bi-eye-slash' : 'bi bi-eye';
        });

        $(document).on('click', '.asset-assigned-user-link', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const userId = $(this).attr('data-employee-user-id') || $(this).data('employeeUserId');
            openAssetEmployeeProfile(userId);
        });

        $(document).on('keydown', function(e) {
            if (e.key === 'Escape') closeAssetEmployeeOverview();
        });

        $(document).on('click', '.mobile-asset-card', function(e) {
            if (window.innerWidth > 768) return;
            if ($(e.target).closest('.asset-card-detail-panel, .asset-card-expand-btn, .asset-card-action-btn, .asset-assigned-user-link').length) return;
            const expandBtn = this.querySelector('.asset-card-expand-btn');
            if (expandBtn) expandBtn.click();
        });
        $('#assetSummaryTotal').on('click', function() {
            clearAssetFilters();
            const libraryTab = document.getElementById('library-tab');
            if (libraryTab && typeof bootstrap !== 'undefined') {
                bootstrap.Tab.getOrCreateInstance(libraryTab).show();
            }
        });
        $('#assetSummaryAssigned').on('click', function() {
            applySummaryStatusFilter('assigned');
        });
        $('#assetSummaryAvailable').on('click', function() {
            applySummaryStatusFilter('available');
        });
        $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function() {
            toggleStatusFilterVisibility();
            refreshViews();
        });
        if (typeof initSummaryCardsScroll === 'function') {
            initSummaryCardsScroll();
        }
        toggleStatusFilterVisibility();
        loadAll();
    });
</script>
<?php include __DIR__ . '/asset_user_overview_drawer.php'; ?>
<?php include __DIR__ . '/htmlclose.php'; ?>