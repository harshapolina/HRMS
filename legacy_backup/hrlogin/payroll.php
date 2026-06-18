<?php
$skip_superadmin_css = true;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['loggedin'])) {
    header('Location: /');
    exit;
}
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

include('htmlopen.php');
include('header.php');
?>
<link rel="stylesheet" href="./assets/css/style_dashboard.css?v=<?php echo time(); ?>" />
<link rel="stylesheet" href="./assets/css/Users.css?v=<?php echo time(); ?>" />
<link rel="stylesheet" href="./assets/css/unified_table_styles.css?v=<?php echo time(); ?>" />
<?php

// Simple month generator for selection
$months = [];
for ($i = 0; $i < 6; $i++) {
    $months[] = date('M Y', strtotime("-$i month"));
}
?>

<style>
    /* Base background - respects global theme */
    body, html {
        background: var(--bg-gradient) !important;
        background-attachment: fixed !important;
        background-size: cover !important;
        color: var(--text-dark) !important;
    }
    
    .content { background: transparent !important; border: none !important; box-shadow: none !important; }
    
    /* Animated floating backgrounds - only in light mode for cleaner look */
    body:not(.dark-mode)::before, body:not(.dark-mode)::after {
        content: ''; position: fixed; width: 200vw; height: 200vh; border-radius: 50%; z-index: -1; opacity: .3;
        animation: 15s ease-in-out infinite alternate float;
    }
    body:not(.dark-mode)::before { background: radial-gradient(circle, #d2b4ff 0, transparent 70%); top: -10vh; right: -50vw; }
    body:not(.dark-mode)::after { background: radial-gradient(circle, #f9eb9c 0, transparent 70%); bottom: -100vh; left: -50vw; animation-delay: 2.5s; }
    @keyframes float { 0% { transform: translateY(0) scale(1); } 100% { transform: translateY(-20px) scale(1.05); } }

    .payroll-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        flex-wrap: wrap;
        gap: 15px;
    }
    .month-selector {
        background: #fff;
        height: 44px; /* Fixed height matching search input and run payroll button */
        padding: 0 15px; /* Removed vertical padding, flex centers content */
        border-radius: 12px;
        border: 1px solid var(--border-color);
        display: flex;
        align-items: center;
        gap: 10px;
        box-shadow: var(--shadow);
    }
    .month-selector select {
        border: none;
        outline: none;
        font-weight: 600;
        color: var(--primary-teal-dark);
        cursor: pointer;
        background: transparent;
    }
    .run-payroll-btn {
        background: var(--active-bg);
        color: #fff;
        border: none;
        height: 44px; /* Fixed height matching search input and month selector */
        padding: 0 25px; /* Removed vertical padding, flex centers content */
        border-radius: 12px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 10px;
        transition: var(--transition);
        box-shadow: 0 4px 12px rgba(34, 116, 119, 0.2);
    }
    .run-payroll-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 15px rgba(34, 116, 119, 0.3);
    }
    .run-payroll-btn:disabled {
        background: #94a3b8;
        cursor: not-allowed;
        transform: none;
    }
    .status-badge {
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
    }
    .status-processed {
        background: rgba(16, 185, 129, 0.1);
        color: #10b981;
    }
    .status-pending {
        background: rgba(245, 158, 11, 0.1);
        color: #f59e0b;
    }
    .empty-state {
        text-align: center;
        padding: 50px;
        background: #fff;
        border-radius: 16px;
        border: 1px dashed var(--border-color);
    }
    .empty-state i {
        font-size: 3rem;
        color: var(--text-light);
        margin-bottom: 15px;
    }

    /* Dark mode specific tweaks */
    body.dark-mode .month-selector {
        background: rgba(255, 255, 255, 0.05) !important;
        border-color: rgba(255, 255, 255, 0.1) !important;
    }
    body.dark-mode .month-selector select,
    body.dark-mode .month-selector i {
        color: #fff !important;
    }
    body.dark-mode .empty-state {
        background: rgba(255, 255, 255, 0.03) !important;
        border-color: rgba(255, 255, 255, 0.1) !important;
        color: #fff !important;
    }
    body.dark-mode .empty-state h3 { color: #fff !important; }
    body.dark-mode .user-table-container { background: transparent !important; border: none !important; }
    body.dark-mode .status-badge.status-processed { background: rgba(16, 185, 129, 0.2) !important; color: #10b981 !important; }
    body.dark-mode .status-badge.status-pending { background: rgba(245, 158, 11, 0.2) !important; color: #f59e0b !important; }

    /* Summary cards — match attendance report */
    .hr-payroll-page .summary-section .summary-card {
        background: #ffffff !important;
        border-radius: 50px !important;
        padding: 10px 25px !important;
        color: #333 !important;
        min-width: 180px !important;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05) !important;
        border: 1px solid #e0e0e0 !important;
        font-weight: 600 !important;
        cursor: default !important;
        transition: all 0.25s ease-in-out !important;
    }
    .hr-payroll-page .summary-section .summary-card.stat-card-headcount { border: 2px solid #0ea5e9 !important; }
    .hr-payroll-page .summary-section .summary-card.stat-card-present { border: 2px solid #10b981 !important; }
    .hr-payroll-page .summary-section .summary-card.stat-card-late { border: 2px solid #f59e0b !important; }
    .hr-payroll-page .summary-section .summary-card .summary-text { color: #333 !important; }
    body.dark-mode .hr-payroll-page .summary-section .summary-card {
        background: rgba(255, 255, 255, 0.05) !important;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2) !important;
    }
    body.dark-mode .hr-payroll-page .summary-section .summary-card .summary-text { color: #f8fafc !important; }
    body.dark-mode .hr-payroll-page .summary-section .summary-card.stat-card-headcount { border-color: rgba(14, 165, 233, 0.6) !important; }
    body.dark-mode .hr-payroll-page .summary-section .summary-card.stat-card-present { border-color: rgba(16, 185, 129, 0.6) !important; }
    body.dark-mode .hr-payroll-page .summary-section .summary-card.stat-card-late { border-color: rgba(245, 158, 11, 0.6) !important; }

    .payroll-mobile-list { display: none; }
    .payroll-mobile-sheet { display: none; }

    @media (max-width: 767px) {
        .desktop-only-payroll { display: none !important; }
        .mobile-only-payroll { display: block !important; }
    }
    @media (min-width: 768px) {
        .mobile-only-payroll { display: none !important; }
    }

    .hr-payroll-page.content {
        padding-left: 12px !important;
        padding-right: 12px !important;
    }
    @media (min-width: 768px) {
        .hr-payroll-page.content {
            padding-left: 1rem !important;
            padding-right: 1rem !important;
        }
    }

    .user-table-container {
        border: none !important;
        box-shadow: none !important;
        background: transparent !important;
    }

    /* Commented out duplicate local pagination styles in favor of global unified_table_styles.css
    #payrollPaginationWrap {
        margin-top: 0.5rem;
    }
    #payrollPaginationWrap .page-btn {
        text-decoration: none;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        width: 40px !important;
        height: 40px !important;
        padding: 0 !important;
        border-radius: 8px !important;
        font-weight: 600 !important;
        transition: all 0.2s ease !important;
    }
    #payrollPaginationWrap .page-btn.disabled {
        pointer-events: none;
        opacity: 0.45;
    }
    #payrollPaginationWrap .page-btn.active {
        background: var(--active-bg, #008080) !important;
        border-color: var(--primary-teal-dark, #0f766e) !important;
        color: #ffffff !important;
    }
    */
    body.dark-mode .search-input {
        background-color: #2a2a2d !important;
        color: #ffffff !important;
        border-color: rgba(255, 255, 255, 0.2) !important;
    }
    body.dark-mode .search-input::placeholder {
        color: rgba(255, 255, 255, 0.6) !important;
    }

    @media (max-width: 767px) {
        .hr-payroll-page .container-fluid {
            padding-bottom: calc(72px + env(safe-area-inset-bottom, 0px)) !important;
        }

        .hr-payroll-page .payroll-controls-card {
            padding: 0 !important;
            margin-bottom: 8px !important;
            background: transparent !important;
            border: none !important;
            box-shadow: none !important;
        }

        .hr-payroll-page .payroll-toolbar-form {
            display: grid !important;
            grid-template-columns: 1fr auto !important;
            grid-template-areas: "search limit" !important;
            gap: 10px !important;
        }

        .hr-payroll-page .payroll-toolbar-form .filter-group-search { grid-area: search !important; min-width: 0 !important; }
        .hr-payroll-page .payroll-toolbar-form .search-box { position: relative; width: 100%; }
        .hr-payroll-page .payroll-toolbar-form .search-icon-mobile {
            position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
            color: #94a3b8; pointer-events: none; z-index: 1;
        }
        .hr-payroll-page .payroll-toolbar-form .search-input {
            width: 100% !important;
            padding: 12px 12px 12px 42px !important;
            border-radius: 12px !important;
            border: 1px solid #e2e8f0 !important;
            font-size: 14px !important;
            background: #fff !important;
        }
        .hr-payroll-page .payroll-toolbar-form .filter-group-actions { display: contents !important; }
        .hr-payroll-page .payroll-toolbar-form .page-size-selector {
            grid-area: limit !important;
            position: relative !important;
            display: flex !important;
            width: 72px !important;
            height: 48px !important;
            border-radius: 12px !important;
            background: #fff !important;
            border: 1px solid #e2e8f0 !important;
            align-items: center !important;
            overflow: hidden !important;
        }
        .hr-payroll-page .payroll-toolbar-form .page-size-selector::after {
            content: "" !important;
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
        .hr-payroll-page .payroll-toolbar-form .page-size-selector #rowsPerPageMobile {
            width: 100% !important;
            height: 100% !important;
            margin: 0 !important;
            padding: 0 18px 0 2px !important;
            border: 0 !important;
            background: transparent !important;
            text-align: center !important;
            font-weight: 800 !important;
            font-size: 14px !important;
            -webkit-appearance: none !important;
            appearance: none !important;
        }

        .hr-payroll-page .payroll-mobile-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .mobile-payroll-card {
            background: #fff;
            border-radius: 12px;
            border: 1px solid #eef2f7;
            box-shadow: 0 1px 6px rgba(15, 23, 42, 0.05);
            overflow: hidden;
        }
        .mobile-payroll-card.expanded { box-shadow: 0 4px 14px rgba(15, 23, 42, 0.08); }

        .payroll-card-main {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            min-height: 52px;
        }
        .payroll-card-info { flex: 1; min-width: 0; }
        .payroll-card-meta-line { margin-bottom: 3px; }
        .payroll-card-meta {
            font-size: 0.72rem;
            font-weight: 700;
            color: #94a3b8;
        }
        .payroll-card-name-line {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }
        .payroll-card-name-group {
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 0;
            flex: 1;
        }
        .payroll-card-name {
            font-size: 0.92rem;
            font-weight: 700;
            color: #0f172a;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .payroll-card-name-group .status-badge {
            flex-shrink: 0;
            padding: 3px 8px !important;
            font-size: 0.58rem !important;
            border-radius: 999px !important;
        }
        .payroll-card-net {
            font-size: 0.88rem;
            font-weight: 800;
            color: var(--primary-teal, #0d9488);
            white-space: nowrap;
        }
        .payroll-card-net.negative { color: #ef4444; }
        .payroll-card-expand-btn {
            width: 32px; height: 32px; min-width: 32px;
            border: 1px solid #e2e8f0; border-radius: 10px;
            background: #f8fafc; color: #64748b;
            display: inline-flex; align-items: center; justify-content: center;
            cursor: pointer;
        }
        .payroll-card-expand-btn.active {
            background: rgba(13, 148, 136, 0.1);
            border-color: rgba(13, 148, 136, 0.25);
            color: #0d9488;
        }
        .payroll-card-detail-panel {
            display: none;
            padding: 0 12px 12px;
            border-top: 1px solid #f1f5f9;
        }
        .payroll-mobile-detail-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 8px 0;
            border-bottom: 1px dashed #eef2f7;
        }
        .payroll-mobile-detail-row:last-of-type { border-bottom: none; }
        .payroll-mobile-detail-label {
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            color: #94a3b8;
        }
        .payroll-mobile-detail-value {
            font-size: 0.82rem;
            font-weight: 600;
            color: #334155;
            text-align: right;
        }
        .payroll-card-actions {
            display: flex;
            gap: 8px;
            margin-top: 10px;
        }
        .payroll-card-action-btn {
            flex: 1;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            border-radius: 10px;
            padding: 10px 12px;
            font-size: 0.8rem;
            font-weight: 700;
            color: #334155;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            text-decoration: none;
        }
        .payroll-card-action-btn.primary {
            background: var(--active-bg, #008080);
            border-color: var(--active-bg, #008080);
            color: #fff;
        }

        .payroll-mobile-bottom-nav {
            display: flex;
            justify-content: space-evenly;
            position: fixed;
            bottom: 0; left: 0; right: 0;
            background: #fff;
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            border-top: 1px solid #e5e7eb;
            padding: 6px 0 calc(6px + env(safe-area-inset-bottom, 0px));
        }
        .payroll-mobile-nav-btn {
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
        }
        .payroll-mobile-nav-btn i { font-size: 18px; }
        .payroll-mobile-nav-btn.payroll-mobile-period-btn { color: #2563eb; }
        .payroll-mobile-nav-btn.payroll-mobile-actions-btn { color: #03ac47; }
        .payroll-mobile-nav-btn.active { background: rgba(15, 23, 42, 0.06); }

        .payroll-mobile-sheet {
            display: block;
            position: fixed;
            inset: 0;
            z-index: 1001;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.2s ease;
        }
        .payroll-mobile-sheet.active {
            pointer-events: auto;
            opacity: 1;
        }
        .payroll-mobile-sheet-backdrop {
            position: absolute;
            inset: 0;
            background: rgba(15, 23, 42, 0.45);
        }
        .payroll-mobile-sheet-panel {
            position: absolute;
            left: 0; right: 0; bottom: 0;
            background: #fff;
            border-radius: 20px 20px 0 0;
            padding: 16px 16px calc(16px + env(safe-area-inset-bottom, 0px));
            max-height: 70vh;
            overflow-y: auto;
            box-shadow: 0 -8px 30px rgba(0, 0, 0, 0.12);
        }
        .payroll-mobile-sheet-panel h6 {
            margin: 0 0 12px;
            font-size: 0.95rem;
            font-weight: 800;
            color: #0f172a;
        }
        .payroll-mobile-sheet-options {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .payroll-mobile-sheet-option {
            width: 100%;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            border-radius: 12px;
            padding: 12px 14px;
            text-align: left;
            font-size: 0.92rem;
            font-weight: 600;
            color: #334155;
            cursor: pointer;
        }
        .payroll-mobile-sheet-option.selected {
            border-color: #0d9488;
            background: rgba(13, 148, 136, 0.08);
            color: #0f766e;
        }
        .payroll-mobile-action-btn {
            width: 100%;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            border-radius: 12px;
            padding: 12px 14px;
            text-align: left;
            font-size: 0.92rem;
            font-weight: 600;
            color: #334155;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
        }
        .payroll-mobile-action-btn.run-payroll {
            background: var(--active-bg, #008080);
            border-color: var(--active-bg, #008080);
            color: #fff;
        }

        .hr-payroll-page .floating-clear-btn {
            bottom: calc(78px + env(safe-area-inset-bottom, 0px)) !important;
        }

        body.dark-mode .mobile-payroll-card {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(255, 255, 255, 0.1);
        }
        body.dark-mode .payroll-card-name { color: #f8fafc; }
        body.dark-mode .payroll-mobile-detail-value { color: #e2e8f0; }
        body.dark-mode .payroll-mobile-bottom-nav {
            background: #121212;
            border-color: rgba(255, 255, 255, 0.1);
        }
        body.dark-mode .payroll-mobile-sheet-panel { background: #1a1a1a; }
        body.dark-mode .payroll-mobile-sheet-panel h6 { color: #f8fafc; }
        body.dark-mode .payroll-mobile-sheet-option,
        body.dark-mode .payroll-mobile-action-btn {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(255, 255, 255, 0.12);
            color: #e2e8f0;
        }
    }
</style>

<div class="content hr-payroll-page">
    <div class="container-fluid">
        <!-- BEGIN: Desktop Header -->
        <div class="payroll-header desktop-only-payroll">
            <div>
                <h2 style="font-weight: 700; color: var(--text-dark); margin: 0;">Payroll Management</h2>
                <p style="color: var(--text-gray); margin-top: 5px;">Manage employee salaries and generate payslips</p>
            </div>
            <div style="display: flex; gap: 15px; align-items: center; justify-content: flex-end; flex: 1;">
                <div class="search-box" style="flex: 1; min-width: 320px; position: relative;">
                    <svg class="search-icon" width="18" height="18" viewBox="0 0 18 18" fill="none" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); pointer-events: none; z-index: 1;">
                      <circle cx="8" cy="8" r="6" stroke="#999" stroke-width="1.5"/>
                      <path d="M12.5 12.5L16 16" stroke="#999" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                    <input type="text" class="search-input" id="payrollSearch" placeholder="Search employees..." style="padding-left: 44px; height: 44px; border-radius: 12px; border: 1px solid var(--table-border, #e2e8f0); background: #fff; width: 100%; box-shadow: var(--shadow); font-weight: 500;">
                </div>
                <div class="month-selector">
                    <i class="bi bi-list-ol"></i>
                    <select id="rowsPerPage" onchange="changePageSize(this.value)">
                        <option value="10">10 per page</option>
                        <option value="25">25 per page</option>
                        <option value="50">50 per page</option>
                        <option value="100">100 per page</option>
                    </select>
                </div>
                <div class="month-selector">
                    <i class="bi bi-calendar3"></i>
                    <select id="payrollMonth" onchange="loadPayroll()">
                        <?php foreach($months as $m): ?>
                            <option value="<?php echo $m; ?>"><?php echo $m; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="run-payroll-btn" id="runPayrollBtn" onclick="runPayroll()">
                    <i class="bi bi-play-circle-fill"></i>
                    <span>Run Payroll</span>
                </button>
            </div>
        </div>
        <!-- END: Desktop Header -->

        <!-- BEGIN: Mobile UI -->
        <div class="mobile-only-payroll">
            <div class="summary-wrapper pt-1 mb-2">
                <button type="button" class="summary-arrow left" id="summaryLeft" aria-label="Scroll summary left">‹</button>
                <div class="summary-section" id="summaryScroll">
                    <div class="summary-card stat-card-headcount">
                        <span class="summary-text" style="font-weight: 600;">Total Payout : <span id="sumPayrollTotalMobile">₹0</span></span>
                    </div>
                    <div class="summary-card stat-card-present">
                        <span class="summary-text" style="font-weight: 600;">Avg Net Salary : <span id="sumPayrollAvgMobile">₹0</span></span>
                    </div>
                    <div class="summary-card stat-card-late">
                        <span class="summary-text" style="font-weight: 600;">Employees : <span id="sumPayrollCountMobile">0</span></span>
                    </div>
                </div>
                <button type="button" class="summary-arrow right" id="summaryRight" aria-label="Scroll summary right">›</button>
            </div>

            <div class="glass-card payroll-controls-card mb-2">
                <div class="payroll-toolbar-form">
                    <div class="filter-group-search">
                        <div class="search-box">
                            <i class="bi bi-search search-icon-mobile"></i>
                            <input type="text" id="payrollMobileSearch" class="search-input" placeholder="Search employees..." autocomplete="off">
                        </div>
                    </div>
                    <div class="filter-group-actions">
                        <div class="page-size-selector">
                            <select id="rowsPerPageMobile" class="form-select" onchange="changePageSize(this.value, 'mobile')" aria-label="Rows per page">
                                <option value="10">10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                        </div>
                    </div>
                </div>
                <select id="payrollMonthMobile" class="visually-hidden" tabindex="-1" aria-hidden="true" onchange="syncMonths('mobile')">
                    <?php foreach($months as $m): ?>
                        <option value="<?php echo $m; ?>"><?php echo $m; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-2 px-1">
                <span id="payrollResultsLabelMobile" style="font-weight: 600; font-size: 0.8rem; color: var(--text-gray);">Payroll Records</span>
            </div>

            <div id="payrollMobileContainer" class="payroll-mobile-list"></div>
        </div>
        <!-- END: Mobile UI -->

        <div class="table-wrap">
            <!-- BEGIN: Desktop Table -->
            <div id="payrollTableContainer" class="user-table-container desktop-only-payroll">
                <table class="user-data-table unified-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Employee Name</th>
                            <th>Base Salary</th>
                            <th>Present Days</th>
                            <th>Total Days</th>
                            <th>Deductions</th>
                            <th>Net Salary</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="payrollBody">
                        <!-- Loaded via AJAX -->
                    </tbody>
                </table>
            </div>
            <!-- END: Desktop Table -->

            <div id="emptyState" class="empty-state" style="display: none;">
                <i class="bi bi-cash-stack"></i>
                <h3>No Payroll Data Found</h3>
                <p>Select a month and click "Run Payroll" to process salaries.</p>
            </div>

            <div id="payrollPaginationWrap" class="pagination-section py-3" style="display: none;">
                <div class="d-flex flex-column align-items-center gap-2">
                    <div class="pagination-info text-muted small" id="payrollPaginationInfo"></div>
                    <div class="pagination-controls d-flex gap-2 flex-wrap justify-content-center" id="payrollPaginationControls"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<nav class="payroll-mobile-bottom-nav d-md-none" aria-label="Payroll actions">
    <button type="button" class="payroll-mobile-nav-btn payroll-mobile-period-btn" id="payrollMobilePeriodBtn">
        <i class="bi bi-calendar3"></i>
        <span>Period</span>
    </button>
    <button type="button" class="payroll-mobile-nav-btn payroll-mobile-actions-btn" id="payrollMobileActionsBtn">
        <i class="bi bi-lightning-charge-fill"></i>
        <span>Actions</span>
    </button>
</nav>

<div id="payrollMobilePeriodSheet" class="payroll-mobile-sheet d-md-none" aria-hidden="true">
    <div class="payroll-mobile-sheet-backdrop" id="payrollMobilePeriodBackdrop"></div>
    <div class="payroll-mobile-sheet-panel" role="dialog" aria-modal="true" aria-labelledby="payrollMobilePeriodTitle">
        <h6 id="payrollMobilePeriodTitle">Payroll Period</h6>
        <div class="payroll-mobile-sheet-options" id="payrollMobilePeriodOptions"></div>
    </div>
</div>

<div id="payrollMobileActionsSheet" class="payroll-mobile-sheet d-md-none" aria-hidden="true">
    <div class="payroll-mobile-sheet-backdrop" id="payrollMobileActionsBackdrop"></div>
    <div class="payroll-mobile-sheet-panel" role="dialog" aria-modal="true" aria-labelledby="payrollMobileActionsTitle">
        <h6 id="payrollMobileActionsTitle">Payroll Actions</h6>
        <button type="button" class="payroll-mobile-action-btn run-payroll" id="runPayrollBtnMobile" onclick="runPayroll(); closePayrollMobileSheets();">
            <i class="bi bi-play-circle-fill"></i> Run Payroll
        </button>
        <button type="button" class="payroll-mobile-action-btn" onclick="downloadPayrollCSV(); closePayrollMobileSheets();">
            <i class="bi bi-file-earmark-spreadsheet"></i> Export CSV
        </button>
        <button type="button" class="payroll-mobile-action-btn" onclick="loadPayroll(); closePayrollMobileSheets();">
            <i class="bi bi-arrow-clockwise"></i> Refresh
        </button>
    </div>
</div>

<button type="button" id="payrollFloatingClearFilters" class="floating-clear-btn d-md-none" style="display:none;">
    <i class="bi bi-x-circle"></i> Clear Filters
</button>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="assets/js/summary-cards.js?v=<?php echo time(); ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    let payrollAllRows = [];
    let payrollCurrentPage = 1;
    let PAYROLL_PAGE_SIZE = 10;

    function isPayrollMobileView() {
        return window.innerWidth <= 767;
    }

    function closePayrollMobileSheets() {
        document.querySelectorAll('.payroll-mobile-sheet').forEach(function(sheet) {
            sheet.classList.remove('active');
            sheet.setAttribute('aria-hidden', 'true');
        });
        document.querySelectorAll('.payroll-mobile-nav-btn').forEach(function(btn) {
            btn.classList.remove('active');
        });
    }

    function openPayrollMobileSheet(sheetId, btnId) {
        closePayrollMobileSheets();
        const sheet = document.getElementById(sheetId);
        if (!sheet) return;
        sheet.classList.add('active');
        sheet.setAttribute('aria-hidden', 'false');
        if (btnId) document.getElementById(btnId)?.classList.add('active');
    }

    function populatePayrollMobilePeriodOptions() {
        const container = document.getElementById('payrollMobilePeriodOptions');
        const monthSelect = document.getElementById('payrollMonthMobile');
        if (!container || !monthSelect) return;
        const current = monthSelect.value || '';
        container.innerHTML = '';
        Array.from(monthSelect.options).forEach(function(opt) {
            const val = String(opt.value || '');
            if (!val) return;
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'payroll-mobile-sheet-option' + (val === current ? ' selected' : '');
            btn.dataset.period = val;
            btn.textContent = opt.textContent;
            container.appendChild(btn);
        });
    }

    function updatePayrollMobileFilterUI() {
        const query = (document.getElementById('payrollMobileSearch')?.value || '').trim();
        const showClear = !!query;
        const clearBtn = document.getElementById('payrollFloatingClearFilters');
        if (clearBtn) clearBtn.style.display = showClear ? 'flex' : 'none';
    }

    function clearPayrollMobileFilters() {
        const mob = document.getElementById('payrollMobileSearch');
        const desk = document.getElementById('payrollSearch');
        if (mob) mob.value = '';
        if (desk) desk.value = '';
        payrollCurrentPage = 1;
        updatePayrollMobileFilterUI();
        renderPayrollPage();
    }

    function togglePayrollMobileCard(btn) {
        const card = btn.closest('.mobile-payroll-card');
        const panel = card?.querySelector('.payroll-card-detail-panel');
        const icon = btn.querySelector('i');
        if (!card || !panel) return;

        const isOpen = card.classList.contains('expanded');
        document.querySelectorAll('.mobile-payroll-card.expanded').forEach(function(c) {
            if (c === card) return;
            c.classList.remove('expanded');
            const p = c.querySelector('.payroll-card-detail-panel');
            if (p) p.style.display = 'none';
            const b = c.querySelector('.payroll-card-expand-btn');
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

    function updatePayrollSummaryCards(rows) {
        const total = rows.reduce(function(sum, r) {
            return sum + (parseFloat(r.net_salary) || 0);
        }, 0);
        const avg = rows.length ? total / rows.length : 0;
        const totalEl = document.getElementById('sumPayrollTotalMobile');
        const avgEl = document.getElementById('sumPayrollAvgMobile');
        const countEl = document.getElementById('sumPayrollCountMobile');
        if (totalEl) totalEl.textContent = '₹' + total.toLocaleString(undefined, { maximumFractionDigits: 0 });
        if (avgEl) avgEl.textContent = '₹' + Math.round(avg).toLocaleString();
        if (countEl) countEl.textContent = String(rows.length);
    }

    function changePageSize(val, source) {
        PAYROLL_PAGE_SIZE = parseInt(val);
        payrollCurrentPage = 1;
        
        // Sync selectors
        if (source === 'mobile') {
            document.getElementById('rowsPerPage').value = val;
        } else {
            const mob = document.getElementById('rowsPerPageMobile');
            if (mob) mob.value = val;
        }
        
        renderPayrollPage();
    }

    function appendPayrollRow(row, body, mobileContainer) {
        const tr = document.createElement('tr');
        tr.className = 'user-data-row';
        tr.innerHTML = `
            <td>${row.id}</td>
            <td class="fw-bold">${row.employee_name}</td>
            <td>₹${parseFloat(row.base_salary).toLocaleString()}</td>
            <td>${row.present_days}</td>
            <td>${row.total_days}</td>
            <td>₹${parseFloat(row.deductions).toLocaleString()}</td>
            <td class="fw-bold text-success">₹${parseFloat(row.net_salary).toLocaleString()}</td>
            <td><span class="status-badge status-processed">${row.status}</span></td>
            <td>
                <a href="payslip.php?id=${row.id}" class="action-btn edit-btn" title="View Payslip">
                    <i class="bi bi-file-earmark-text"></i>
                </a>
            </td>
        `;
        body.appendChild(tr);
        if (mobileContainer) {
            const netSalaryVal = parseFloat(row.net_salary);
            const netClass = netSalaryVal < 0 ? 'payroll-card-net negative' : 'payroll-card-net';
            const statusClass = String(row.status || '').toLowerCase() === 'processed' ? 'status-processed' : 'status-pending';
            const empCode = row.employee_id || row.emp_code || row.id;
            const card = document.createElement('div');
            card.className = 'mobile-payroll-card';
            card.innerHTML = `
                <div class="payroll-card-main">
                    <div class="payroll-card-info">
                        <div class="payroll-card-meta-line">
                            <span class="payroll-card-meta">ID ${empCode}</span>
                        </div>
                        <div class="payroll-card-name-line">
                            <div class="payroll-card-name-group">
                                <span class="payroll-card-name">${row.employee_name}</span>
                                <span class="status-badge ${statusClass}">${row.status}</span>
                            </div>
                            <span class="${netClass}">₹${netSalaryVal.toLocaleString()}</span>
                        </div>
                    </div>
                    <button type="button" class="payroll-card-expand-btn" onclick="togglePayrollMobileCard(this)" aria-expanded="false" aria-label="Expand payroll details">
                        <i class="bi bi-chevron-down"></i>
                    </button>
                </div>
                <div class="payroll-card-detail-panel">
                    <div class="payroll-mobile-detail-row">
                        <span class="payroll-mobile-detail-label">Base Salary</span>
                        <span class="payroll-mobile-detail-value">₹${parseFloat(row.base_salary).toLocaleString()}</span>
                    </div>
                    <div class="payroll-mobile-detail-row">
                        <span class="payroll-mobile-detail-label">Present / Total</span>
                        <span class="payroll-mobile-detail-value">${row.present_days} / ${row.total_days} days</span>
                    </div>
                    <div class="payroll-mobile-detail-row">
                        <span class="payroll-mobile-detail-label">Deductions</span>
                        <span class="payroll-mobile-detail-value">₹${parseFloat(row.deductions).toLocaleString()}</span>
                    </div>
                    <div class="payroll-card-actions">
                        <a href="payslip.php?id=${row.id}" class="payroll-card-action-btn primary">
                            <i class="bi bi-file-earmark-text"></i> View Payslip
                        </a>
                    </div>
                </div>
            `;
            mobileContainer.appendChild(card);
        }
    }

    function getFilteredPayrollRows() {
        const query = (document.getElementById('payrollSearch')?.value || document.getElementById('payrollMobileSearch')?.value || '').toLowerCase().trim();
        if (!query) return payrollAllRows;
        return payrollAllRows.filter(row => {
            const name = (row.employee_name || '').toLowerCase();
            const id = String(row.id || '');
            const empCode = String(row.employee_id || row.emp_code || '');
            return name.includes(query) || id.includes(query) || empCode.includes(query);
        });
    }

    function updatePayrollPaginationUI(filteredRows = payrollAllRows) {
        const wrap = document.getElementById('payrollPaginationWrap');
        const info = document.getElementById('payrollPaginationInfo');
        const controls = document.getElementById('payrollPaginationControls');
        if (!wrap || !info || !controls) return;
        const total = filteredRows.length;
        if (total === 0) {
            wrap.style.display = 'none';
            return;
        }
        const totalPages = Math.max(1, Math.ceil(total / PAYROLL_PAGE_SIZE));
        if (payrollCurrentPage > totalPages) payrollCurrentPage = totalPages;
        if (payrollCurrentPage < 1) payrollCurrentPage = 1;
        const start = (payrollCurrentPage - 1) * PAYROLL_PAGE_SIZE;
        const end = Math.min(start + PAYROLL_PAGE_SIZE, total);
        info.textContent = 'Showing ' + (total ? (start + 1) : 0) + ' to ' + end + ' of ' + total + ' entries';
        controls.innerHTML = '';
        wrap.style.display = 'flex';
        // Keep page navigation buttons visible even for 1 page
        // if (totalPages <= 1) return;

        function addBtn(label, disabled, onClick) {
            const a = document.createElement('a');
            a.href = '#';
            a.className = 'page-btn' + (disabled ? ' disabled' : '');
            a.innerHTML = label;
            a.addEventListener('click', function (e) {
                e.preventDefault();
                if (!disabled) onClick();
            });
            controls.appendChild(a);
        }
        addBtn('&larr;', payrollCurrentPage <= 1, function () {
            payrollCurrentPage--;
            renderPayrollPage();
        });
        const win = 5;
        let s = Math.max(1, payrollCurrentPage - 2);
        let e2 = Math.min(totalPages, s + win - 1);
        if (e2 - s < win - 1) s = Math.max(1, e2 - win + 1);
        for (let i = s; i <= e2; i++) {
            (function (pi) {
                const a = document.createElement('a');
                a.href = '#';
                a.className = 'page-btn' + (pi === payrollCurrentPage ? ' active' : '');
                a.textContent = pi;
                a.addEventListener('click', function (e) {
                    e.preventDefault();
                    payrollCurrentPage = pi;
                    renderPayrollPage();
                });
                controls.appendChild(a);
            })(i);
        }
        addBtn('&rarr;', payrollCurrentPage >= totalPages, function () {
            payrollCurrentPage++;
            renderPayrollPage();
        });
    }

    function renderPayrollPage() {
        const body = document.getElementById('payrollBody');
        const mobileContainer = document.getElementById('payrollMobileContainer');
        const container = document.getElementById('payrollTableContainer');
        const emptyState = document.getElementById('emptyState');
        if (!body) return;
        body.innerHTML = '';
        if (mobileContainer) mobileContainer.innerHTML = '';
        
        const filteredRows = getFilteredPayrollRows();
        const total = filteredRows.length;
        if (total === 0) {
            if (container) container.style.display = 'none';
            if (mobileContainer) {
                mobileContainer.innerHTML = '<div class="payslip-mobile-empty" style="text-align:center;padding:40px 16px;color:#94a3b8;font-weight:600;background:#fff;border-radius:12px;border:1px dashed #e2e8f0;">No payroll records for this period.</div>';
            }
            emptyState.style.display = isPayrollMobileView() ? 'none' : 'block';
            updatePayrollSummaryCards(filteredRows);
            const labelMobile = document.getElementById('payrollResultsLabelMobile');
            if (labelMobile) labelMobile.textContent = 'No payroll records';
            updatePayrollPaginationUI(filteredRows);
            return;
        }
        const totalPages = Math.ceil(total / PAYROLL_PAGE_SIZE) || 1;
        if (payrollCurrentPage > totalPages) payrollCurrentPage = totalPages;
        if (payrollCurrentPage < 1) payrollCurrentPage = 1;
        const start = (payrollCurrentPage - 1) * PAYROLL_PAGE_SIZE;
        const slice = filteredRows.slice(start, start + PAYROLL_PAGE_SIZE);
        if (container) container.style.display = '';
        if (mobileContainer) mobileContainer.style.display = '';
        emptyState.style.display = 'none';
        slice.forEach(function (row) {
            appendPayrollRow(row, body, mobileContainer);
        });
        updatePayrollSummaryCards(filteredRows);
        const labelMobile = document.getElementById('payrollResultsLabelMobile');
        if (labelMobile) {
            labelMobile.textContent = filteredRows.length
                ? 'Found ' + filteredRows.length + ' record' + (filteredRows.length === 1 ? '' : 's')
                : 'No payroll records';
        }
        updatePayrollPaginationUI(filteredRows);
    }

    function syncMonths(source) {
        if (source === 'mobile') {
            document.getElementById('payrollMonth').value = document.getElementById('payrollMonthMobile').value;
        } else {
            const mob = document.getElementById('payrollMonthMobile');
            if (mob) mob.value = document.getElementById('payrollMonth').value;
        }
        loadPayroll();
    }

    function loadPayroll() {
        const month = document.getElementById('payrollMonth').value;

        $.ajax({
            url: 'action.php',
            method: 'GET',
            data: { fetch_payroll: 1, month: month },
            success: function(data) {
                try {
                    console.log("Payroll Data Received:", data);
                    const payroll = (typeof data === 'string') ? JSON.parse(data) : data;
                    payrollAllRows = Array.isArray(payroll) ? payroll : [];
                    payrollCurrentPage = 1;
                    renderPayrollPage();
                } catch (e) {
                    console.error("Payroll Parse Error:", e);
                    console.error("Raw response server sent:", data);
                    Swal.fire('Data Error', 'The server returned an invalid response. Check console for details.', 'error');
                    payrollAllRows = [];
                    renderPayrollPage();
                }
            }
        });
    }

    function downloadPayrollCSV() {
        const month = document.getElementById('payrollMonth').value;
        window.location.href = `action.php?export_payroll=1&month=${encodeURIComponent(month)}`;
    }

    function runPayroll() {
        const month = document.getElementById('payrollMonth').value;
        const btn = document.getElementById('runPayrollBtn');
        const btnMobile = document.getElementById('runPayrollBtnMobile');
        
        Swal.fire({
            title: 'Run Payroll for ' + month + '?',
            text: "This will process salaries for all active employees from attendance logs and auto-generate detailed payslip records.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#227477',
            confirmButtonText: 'Yes, Process'
        }).then((result) => {
            if (result.isConfirmed) {
                if (btn) {
                    btn.disabled = true;
                    btn.innerHTML = '<i class="bi bi-arrow-repeat rotate"></i> <span>Processing...</span>';
                }
                if (btnMobile) {
                    btnMobile.disabled = true;
                    btnMobile.innerHTML = '<i class="bi bi-arrow-repeat rotate"></i> Processing...';
                }
                
                $.ajax({
                    url: 'action.php',
                    method: 'POST',
                    data: { run_payroll: 1, month: month },
                    success: function(response) {
                        const res = (typeof response === 'string') ? JSON.parse(response) : response;
                        if (btn) {
                            btn.disabled = false;
                            btn.innerHTML = '<i class="bi bi-play-circle-fill"></i> <span>Run Payroll</span>';
                        }
                        if (btnMobile) {
                            btnMobile.disabled = false;
                            btnMobile.innerHTML = '<i class="bi bi-play-circle-fill"></i> Run Payroll';
                        }
                        
                        if (res.status === 'success') {
                            const count = res.processed || 0;
                            Swal.fire('Success!', 'Processed ' + count + ' employees and auto-generated ' + (res.payslips_saved || count) + ' payslips.', 'success');
                            loadPayroll();
                        } else {
                            Swal.fire('Error', 'Something went wrong.', 'error');
                        }
                    }
                });
            }
        });
    }

    // Add CSS for rotation animation
    const style = document.createElement('style');
    style.innerHTML = `
        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        .rotate {
            animation: rotate 1s linear infinite;
        }
    `;
    document.head.appendChild(style);

    // Initial load
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof initSummaryCardsScroll === 'function') {
            initSummaryCardsScroll();
        }

        loadPayroll();
        syncMonths('desktop');
        changePageSize(document.getElementById('rowsPerPage').value, 'desktop');

        let payrollSearchTimer = null;
        $('#payrollSearch').on('input', function() {
            const val = this.value;
            const mob = document.getElementById('payrollMobileSearch');
            if (mob) mob.value = val;
            payrollCurrentPage = 1;
            updatePayrollMobileFilterUI();
            renderPayrollPage();
        });
        $('#payrollMobileSearch').on('input', function() {
            clearTimeout(payrollSearchTimer);
            payrollSearchTimer = setTimeout(function() {
                const val = document.getElementById('payrollMobileSearch').value;
                const desk = document.getElementById('payrollSearch');
                if (desk) desk.value = val;
                payrollCurrentPage = 1;
                updatePayrollMobileFilterUI();
                renderPayrollPage();
            }, 250);
        });

        $('#payrollMobilePeriodBtn').on('click', function() {
            if ($('#payrollMobilePeriodSheet').hasClass('active')) {
                closePayrollMobileSheets();
            } else {
                populatePayrollMobilePeriodOptions();
                openPayrollMobileSheet('payrollMobilePeriodSheet', 'payrollMobilePeriodBtn');
            }
        });

        $('#payrollMobileActionsBtn').on('click', function() {
            if ($('#payrollMobileActionsSheet').hasClass('active')) {
                closePayrollMobileSheets();
            } else {
                openPayrollMobileSheet('payrollMobileActionsSheet', 'payrollMobileActionsBtn');
            }
        });

        $('#payrollMobilePeriodBackdrop, #payrollMobileActionsBackdrop').on('click', closePayrollMobileSheets);
        $('#payrollFloatingClearFilters').on('click', clearPayrollMobileFilters);

        $('#payrollMobilePeriodOptions').on('click', '.payroll-mobile-sheet-option', function() {
            const period = String($(this).data('period') || '');
            $('#payrollMonthMobile').val(period);
            $('#payrollMonth').val(period);
            payrollCurrentPage = 1;
            closePayrollMobileSheets();
            loadPayroll();
        });

        $(document).on('click', '.mobile-payroll-card', function(e) {
            if (!isPayrollMobileView()) return;
            if ($(e.target).closest('.payroll-card-detail-panel, .payroll-card-expand-btn, .payroll-card-action-btn').length) return;
            const expandBtn = this.querySelector('.payroll-card-expand-btn');
            if (expandBtn) expandBtn.click();
        });
    });
</script>

<?php include('htmlclose.php'); ?>
