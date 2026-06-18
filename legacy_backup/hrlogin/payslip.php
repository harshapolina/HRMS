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

$payroll_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
?>

<link rel="stylesheet" href="./assets/css/style_dashboard.css?v=<?php echo time(); ?>" />
<link rel="stylesheet" href="./assets/css/Users.css?v=<?php echo time(); ?>" />
<link rel="stylesheet" href="./assets/css/unified_table_styles.css?v=<?php echo time(); ?>" />
<style>
    :root {
        --active-bg: #008080;
        --active-bg-light: rgba(0, 128, 128, 0.08);
        --primary-teal: #0d9488;
        --primary-teal-dark: #0f766e;
        --text-dark: #1e293b;
        --text-gray: #64748b;
        --border-color: #e2e8f0;
    }

    body, html {
        background: var(--bg-gradient) !important;
        background-attachment: fixed !important;
        background-size: cover !important;
        color: var(--text-dark) !important;
    }
    body.dark-mode, body.dark-mode html {
        background: linear-gradient(135deg, #2c2c2e 0%, #1c1c1e 50%, #2c2c2e 100%) !important;
        background-attachment: fixed !important;
        background-size: cover !important;
        color: rgba(255, 255, 255, 0.95) !important;
        color-scheme: dark !important;
    }

    @media (min-width: 769px) {
        .mobile-only-payslip {
            display: none !important;
        }
    }
    @media (max-width: 768px) {
        .desktop-only-payslip {
            display: none !important;
        }
    }

    .payslip-mobile-list {
        display: none;
    }
    .payslip-mobile-sheet {
        display: none;
    }

    .hr-payslip-page.content.mgmt-center {
        background: transparent !important;
        border: none !important;
        box-shadow: none !important;
        min-height: calc(100vh - 80px);
        padding-left: 12px !important;
        padding-right: 12px !important;
    }
    @media (min-width: 768px) {
        .hr-payslip-page.content.mgmt-center {
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
    #payslipPaginationWrap,
    #payslipPaginationWrapMobile {
        margin-top: 0.5rem;
     }
    #payslipPaginationWrap .page-btn,
    #payslipPaginationWrapMobile .page-btn {
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
    #payslipPaginationWrap .page-btn.disabled,
    #payslipPaginationWrapMobile .page-btn.disabled {
        pointer-events: none;
        opacity: 0.45;
    }
    #payslipPaginationWrap .page-btn.active,
    #payslipPaginationWrapMobile .page-btn.active {
        background: var(--active-bg, #008080) !important;
        border-color: var(--primary-teal-dark, #0f766e) !important;
        color: #ffffff !important;
    }
    */
    
    /* Payslip Preview Modal Dark Mode Overrides */
    body.dark-mode #payslipModal .modal-content,
    html.dark-mode #payslipModal .modal-content {
        background-color: #1e1e1e !important;
        color: #ffffff !important;
        border: 1px solid #333333 !important;
    }

    body.dark-mode #payslipModal .modal-header,
    html.dark-mode #payslipModal .modal-header {
        background: #1e1e1e !important;
        color: #ffffff !important;
        border-bottom: 1px solid #333333 !important;
    }

    body.dark-mode #payslipModal .modal-body,
    html.dark-mode #payslipModal .modal-body {
        background-color: #1e1e1e !important;
        color: #ffffff !important;
    }

    body.dark-mode #payslipModal .preview-card,
    html.dark-mode #payslipModal .preview-card {
        background-color: #1f1f22 !important;
        color: #ffffff !important;
    }

    body.dark-mode #payslipModal .preview-card h1,
    html.dark-mode #payslipModal .preview-card h1 {
        color: #ffffff !important;
    }

    body.dark-mode #payslipModal .preview-card p,
    html.dark-mode #payslipModal .preview-card p {
        color: #aaaaaa !important;
    }

    body.dark-mode #payslipModal .preview-card div[style*="border-top"],
    html.dark-mode #payslipModal .preview-card div[style*="border-top"] {
        border-color: #ffffff !important;
    }

    body.dark-mode #payslipModal table,
    body.dark-mode #payslipModal td,
    body.dark-mode #payslipModal th,
    html.dark-mode #payslipModal table,
    html.dark-mode #payslipModal td,
    html.dark-mode #payslipModal th {
        border-color: #3d3d3d !important;
        color: #ffffff !important;
        background-color: #262626 !important;
    }

    body.dark-mode #payslipModal tr[style*="background"],
    html.dark-mode #payslipModal tr[style*="background"] {
        background-color: #222222 !important;
    }

    body.dark-mode #payslipModal .preview-card td,
    html.dark-mode #payslipModal .preview-card td {
        background-color: #262626 !important;
    }

    body.dark-mode #payslipModal .preview-card strong,
    html.dark-mode #payslipModal .preview-card strong {
        color: #ffffff !important;
    }

    body.dark-mode #payslipModal div[style*="background: #fff"],
    body.dark-mode #payslipModal div[style*="background:#fff"],
    body.dark-mode #payslipModal div[style*="background: #ffffff"],
    body.dark-mode #payslipModal div[style*="background:#ffffff"],
    html.dark-mode #payslipModal div[style*="background: #fff"],
    html.dark-mode #payslipModal div[style*="background:#fff"],
    html.dark-mode #payslipModal div[style*="background: #ffffff"],
    html.dark-mode #payslipModal div[style*="background:#ffffff"] {
        background-color: #262626 !important;
        border-color: #3d3d3d !important;
    }

    body.dark-mode #payslipModal div[style*="background"] span,
    html.dark-mode #payslipModal div[style*="background"] span {
        color: #ffffff !important;
    }

    body.dark-mode #payslipModal #p_words,
    html.dark-mode #payslipModal #p_words {
        color: #aaaaaa !important;
    }

    body.dark-mode #payslipModal #p_deductions_breakdown td,
    html.dark-mode #payslipModal #p_deductions_breakdown td {
        background-color: #262626 !important;
        border-color: #3d3d3d !important;
        color: #ffffff !important;
    }

    body.dark-mode #payslipModal .modal-footer,
    html.dark-mode #payslipModal .modal-footer {
        background-color: #181818 !important;
        border-top: 1px solid #333333 !important;
    }

    body.dark-mode #payslipModal .modal-footer .btn-secondary,
    html.dark-mode #payslipModal .modal-footer .btn-secondary {
        background: #2a2a2a !important;
        border: 1px solid #3d3d3d !important;
        color: #ffffff !important;
    }

    body.dark-mode #payslipModal #btnDownloadSingle,
    html.dark-mode #payslipModal #btnDownloadSingle {
        background: #ffffff !important;
        color: #000000 !important;
        border: none !important;
    }

    body.dark-mode #payslipModal #btnDownloadSingle:hover,
    html.dark-mode #payslipModal #btnDownloadSingle:hover {
        background: #dddddd !important;
    }

    body.dark-mode #payslipModal #p_net,
    html.dark-mode #payslipModal #p_net {
        color: #f8fafc !important;
    }
    
    /* Animated floating backgrounds - only in light mode for cleaner look */
    body:not(.dark-mode)::before, body:not(.dark-mode)::after {
        content: ''; position: fixed; width: 200vw; height: 200vh; border-radius: 50%; z-index: -1; opacity: .3;
        animation: 15s ease-in-out infinite alternate float;
    }
    body:not(.dark-mode)::before { background: radial-gradient(circle, #d2b4ff 0, transparent 70%); top: -10vh; right: -50vw; }
    body:not(.dark-mode)::after { background: radial-gradient(circle, #f9eb9c 0, transparent 70%); bottom: -100vh; left: -50vw; animation-delay: 2.5s; }
    @keyframes float { 0% { transform: translateY(0) scale(1); } 100% { transform: translateY(-20px) scale(1.05); } }

    /* Page Header */
    .page-header-p {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
    }

    .page-header-p h2 {
        font-size: 1.75rem;
        font-weight: 800;
        color: var(--text-dark);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    /* Summary Bar */
    .summary-bar {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
        margin-bottom: 30px;
    }

    .summary-card-p {
        background: #fff;
        padding: 20px;
        border-radius: 16px;
        border: 1px solid var(--border-color);
        display: flex;
        align-items: center;
        gap: 15px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    }

    .sum-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
        background: var(--active-bg-light);
        color: var(--active-bg);
    }

    .sum-info h4 {
        margin: 0;
        font-size: 1.25rem;
        font-weight: 800;
        color: var(--text-dark);
    }

    .sum-info p {
        margin: 0;
        font-size: 0.8rem;
        color: var(--text-gray);
        font-weight: 500;
    }

    /* Search & Filter Bar */
    .filter-card-p {
        background: #fff;
        padding: 20px;
        border-radius: 16px;
        border: 1px solid var(--border-color);
        margin-bottom: 30px;
        display: flex;
        gap: 15px;
        align-items: center;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05);
    }

    .filter-group {
        flex: 1;
    }

    .filter-group label {
        display: block;
        font-size: 0.75rem;
        font-weight: 700;
        color: var(--text-gray);
        margin-bottom: 6px;
        text-transform: uppercase;
    }

    .filter-select {
        height: 45px;
        border-radius: 10px;
        border: 1px solid var(--border-color);
        padding: 0 15px;
        font-weight: 500;
        color: var(--text-dark);
    }

    .btn-search-p {
        background: var(--active-bg);
        color: #fff;
        border: none;
        padding: 0 25px;
        height: 45px;
        border-radius: 10px;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 8px;
        margin-top: 22px;
        transition: all 0.2s ease;
    }

    .btn-search-p:hover {
        background: var(--primary-teal-dark);
        transform: translateY(-1px);
    }

    /* Summary cards — match attendance report (override Users.css blue pills) */
    .hr-payslip-page .summary-section .summary-card {
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
    .hr-payslip-page .summary-section .summary-card:hover {
        transform: translateY(-2px) !important;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1) !important;
    }
    .hr-payslip-page .summary-section .summary-card.stat-card-headcount {
        border: 2px solid #0ea5e9 !important;
    }
    .hr-payslip-page .summary-section .summary-card.stat-card-present {
        border: 2px solid #10b981 !important;
    }
    .hr-payslip-page .summary-section .summary-card.stat-card-late {
        border: 2px solid #f59e0b !important;
    }
    .hr-payslip-page .summary-section .summary-card .summary-text {
        color: #333 !important;
    }
    body.dark-mode .hr-payslip-page .summary-section .summary-card {
        background: rgba(255, 255, 255, 0.05) !important;
        color: #f8fafc !important;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2) !important;
    }
    body.dark-mode .hr-payslip-page .summary-section .summary-card .summary-text {
        color: #f8fafc !important;
    }
    body.dark-mode .hr-payslip-page .summary-section .summary-card.stat-card-headcount {
        border-color: rgba(14, 165, 233, 0.6) !important;
    }
    body.dark-mode .hr-payslip-page .summary-section .summary-card.stat-card-present {
        border-color: rgba(16, 185, 129, 0.6) !important;
    }
    body.dark-mode .hr-payslip-page .summary-section .summary-card.stat-card-late {
        border-color: rgba(245, 158, 11, 0.6) !important;
    }

    /* Results Table styling is now handled by unified_table_styles.css */

    .status-pill {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.7rem;
        font-weight: 700;
    }

    .status-generated { background: #dcfce7; color: #166534; }

    .action-group {
        display: flex;
        gap: 8px;
        justify-content: flex-end;
    }

    .btn-table-action {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 1px solid var(--border-color);
        background: #fff;
        color: var(--text-gray);
        transition: all 0.2s;
    }

    .btn-table-action:hover {
        background: var(--active-bg);
        color: #fff;
        border-color: var(--active-bg);
    }

    /* Floating Export Button */
    .btn-export-bulk {
        background: #1e293b;
        color: #fff;
        border: none;
        padding: 10px 20px;
        border-radius: 10px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 10px;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        transition: all 0.2s;
    }

    .btn-export-bulk:hover {
        background: #000;
        transform: translateY(-1px);
    }

    /* Dark mode specific tweaks */
    body.dark-mode .summary-card-p,
    body.dark-mode .filter-card-p {
        background: rgba(255, 255, 255, 0.05) !important;
        border-color: rgba(255, 255, 255, 0.1) !important;
        color: #fff !important;
    }
    body.dark-mode .sum-info h4,
    body.dark-mode .page-header-p h2,
    body.dark-mode .filter-group label {
        color: #fff !important;
    }
    body.dark-mode .filter-select {
        background-color: #262626 !important;
        color: #ffffff !important;
        border-color: #333333 !important;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23ffffff' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e") !important;
        background-repeat: no-repeat !important;
        background-position: right 0.75rem center !important;
        background-size: 16px 12px !important;
    }
    body.dark-mode select {
        background-color: #262626 !important;
        color: #ffffff !important;
        color-scheme: dark !important;
    }
    body.dark-mode .filter-select option,
    body.dark-mode select option {
        background-color: #1e1e1e !important;
        color: #ffffff !important;
        color-scheme: dark !important;
    }
    body.dark-mode .btn-table-action {
        background: rgba(255, 255, 255, 0.1) !important;
        border-color: rgba(255, 255, 255, 0.1) !important;
        color: #fff !important;
    }
    body.dark-mode .status-pill.status-generated {
        background: rgba(16, 185, 129, 0.2) !important;
        color: #10b981 !important;
    }

    /* Modal Styling */
    .modal-content { border-radius: 20px; border: none; overflow: hidden; }
    body.dark-mode .modal-content { background: #1e1e21 !important; color: #fff !important; }
    .modal-header { padding: 25px; border-bottom: 1px solid #f1f5f9; }
    body.dark-mode .modal-header { border-bottom-color: rgba(255, 255, 255, 0.1) !important; }
    .modal-body { padding: 0; }

    /* Payslip Template (Simplified for PDF) - Always Light */
    .payslip-pdf-view {
        padding: 40px;
        background: #fff !important;
        color: #000 !important;
    }

    .pdf-header {
        display: flex;
        justify-content: space-between;
        border-bottom: 2px solid var(--primary-teal);
        padding-bottom: 20px;
        margin-bottom: 25px;
    }

    .pdf-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 30px;
        margin-bottom: 30px;
    }

    .pdf-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 20px;
    }

    .pdf-table th { background: #f8fafc; padding: 10px; border-bottom: 2px solid #e2e8f0; color: #000 !important; }
    .pdf-table td { padding: 12px 10px; border-bottom: 1px solid #f1f5f9; color: #000 !important; }

    /* Hide template from normal view */
    #printablePayslip { display: none; }

    /* Print/PDF Export Container Style (Forcing light theme for printing) */
    .payslip-pdf-print-container {
        width: 800px !important;
        background: #ffffff !important;
        color: #333333 !important;
        padding: 40px !important;
        box-sizing: border-box !important;
    }
    .payslip-pdf-print-container .preview-card {
        background: #ffffff !important;
        color: #333333 !important;
    }
    .payslip-pdf-print-container .preview-card h1 {
        color: #005691 !important;
    }
    .payslip-pdf-print-container .preview-card p {
        color: #666666 !important;
    }
    .payslip-pdf-print-container table,
    .payslip-pdf-print-container td,
    .payslip-pdf-print-container th {
        border-color: #cbd5e1 !important;
        color: #333333 !important;
        background-color: #ffffff !important;
    }
    .payslip-pdf-print-container tr[style*="background"] {
        background-color: #f8fafc !important;
    }
    .payslip-pdf-print-container strong {
        color: #000000 !important;
    }
    .payslip-pdf-print-container #p_words {
        color: #666666 !important;
    }
    .payslip-pdf-print-container #p_net {
        color: #1e293b !important;
    }

    /* Custom premium scrollbar for the scrollable modal body */
    #payslipModal .modal-body::-webkit-scrollbar {
        width: 6px !important;
        height: 6px !important;
    }
    #payslipModal .modal-body::-webkit-scrollbar-track {
        background: transparent !important;
    }
    #payslipModal .modal-body::-webkit-scrollbar-thumb {
        background: rgba(0, 77, 128, 0.25) !important;
        border-radius: 10px !important;
    }
    #payslipModal .modal-body::-webkit-scrollbar-thumb:hover {
        background: rgba(0, 77, 128, 0.45) !important;
    }
    #payslipModal .modal-body {
        scrollbar-width: thin !important;
        scrollbar-color: rgba(0, 77, 128, 0.25) transparent !important;
    }

    @media (max-width: 768px) {
        .summary-bar, .filter-card-p { grid-template-columns: 1fr; flex-direction: column; }

        /* 0. Disable vertical centering on mobile and push modal down to clear the fixed top navbar */
        #payslipModal .modal-dialog-centered {
            align-items: flex-start !important;
            min-height: auto !important;
        }
        #payslipModal .modal-dialog {
            margin: 75px auto 1rem auto !important;
            max-width: calc(100% - 1rem) !important;
            max-height: calc(100vh - 90px) !important;
        }
        #payslipModal .modal-content {
            max-height: calc(100vh - 90px) !important;
        }

        /* 1. Modal Body Padding reduction on mobile */
        #payslipModal .modal-body {
            padding: 16px !important;
        }
        
        /* 2. Company Name & Subtitle responsive sizing */
        #payslipModal .preview-card h1 {
            font-size: 22px !important;
            margin-top: 10px !important;
        }
        #payslipModal .preview-card p {
            font-size: 14px !important;
        }
        #payslipModal .preview-card div[style*="margin-bottom: 40px"],
        #payslipModal .preview-card div[style*="margin-bottom:40px"] {
            margin-bottom: 20px !important;
        }
        
        /* 3. Info Table stacking */
        #payslipModal .payslip-info-table,
        #payslipModal .payslip-info-table tbody,
        #payslipModal .payslip-info-table tr,
        #payslipModal .payslip-info-table td {
            display: block !important;
            width: 100% !important;
        }
        #payslipModal .payslip-info-table td {
            border: 1px solid #e2e8f0 !important;
            border-bottom: none !important;
            box-sizing: border-box !important;
            padding: 10px 12px !important;
            font-size: 13px !important;
        }
        #payslipModal .payslip-info-table tr:last-child td:last-child {
            border-bottom: 1px solid #e2e8f0 !important;
        }
        body.dark-mode #payslipModal .payslip-info-table td {
            border-color: #3d3d3d !important;
        }
        
        /* 4. Earnings/Deductions labels stacking/hiding */
        #payslipModal .payslip-labels-container {
            display: none !important;
        }
        
        /* 5. Breakdown layout stacking */
        #payslipModal .payslip-breakdown-table {
            display: block !important;
            width: 100% !important;
        }
        #payslipModal .payslip-breakdown-table > tbody,
        #payslipModal .payslip-breakdown-table > tbody > tr {
            display: block !important;
            width: 100% !important;
        }
        #payslipModal .payslip-breakdown-left,
        #payslipModal .payslip-breakdown-right {
            display: block !important;
            width: 100% !important;
            box-sizing: border-box !important;
            padding: 8px !important;
            border-right: none !important;
        }
        #payslipModal .payslip-breakdown-left {
            border-bottom: 1px dashed #cbd5e1 !important;
        }
        body.dark-mode #payslipModal .payslip-breakdown-left {
            border-bottom-color: #3d3d3d !important;
        }
        
        #payslipModal .payslip-breakdown-left::before {
            content: "Earnings";
            display: block;
            font-size: 12px;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            margin-bottom: 8px;
            padding-left: 4px;
        }
        #payslipModal .payslip-breakdown-right::before {
            content: "Deductions";
            display: block;
            font-size: 12px;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            margin-bottom: 8px;
            padding-left: 4px;
            margin-top: 10px;
        }
        
        /* 6. Totals row stacking */
        #payslipModal .payslip-totals-row {
            display: block !important;
            width: 100% !important;
        }
        #payslipModal .payslip-total-left,
        #payslipModal .payslip-total-right {
            display: block !important;
            width: 100% !important;
            box-sizing: border-box !important;
            border: none !important;
            padding: 8px 12px !important;
            font-size: 14px !important;
        }
        #payslipModal .payslip-total-left {
            border-bottom: 1px solid #cbd5e1 !important;
        }
        body.dark-mode #payslipModal .payslip-total-left {
            border-bottom-color: #3d3d3d !important;
        }
        
        /* 7. Net Pay Banner responsive sizing */
        #payslipModal .preview-card div[style*="margin-top: 40px"],
        #payslipModal .preview-card div[style*="margin-top:40px"] {
            margin-top: 20px !important;
            padding: 16px !important;
            text-align: center !important;
        }
        #payslipModal .preview-card div[style*="margin-top: 40px"] span,
        #payslipModal .preview-card div[style*="margin-top:40px"] span {
            font-size: 18px !important;
        }

        /* —— Payslip mobile UI (matches attendance / company assets) —— */
        .hr-payslip-page.content.mgmt-center {
            padding: 10px !important;
            overflow-x: hidden;
        }

        .mobile-only-payslip {
            padding: 0 !important;
            padding-bottom: calc(72px + env(safe-area-inset-bottom, 0px)) !important;
        }

        .hr-payslip-page .summary-wrapper {
            padding-top: 0 !important;
            margin-bottom: 8px !important;
        }

        .hr-payslip-page .summary-section {
            padding: 4px 5px !important;
        }

        .hr-payslip-page .payslip-controls-card {
            padding: 0 !important;
            margin-bottom: 8px !important;
            background: transparent !important;
            border: none !important;
            box-shadow: none !important;
        }

        .hr-payslip-page .payslip-toolbar-form {
            display: grid !important;
            grid-template-columns: 1fr auto !important;
            grid-template-areas: "search limit" !important;
            gap: 10px !important;
            width: 100% !important;
        }

        .hr-payslip-page .payslip-toolbar-form .filter-group-search {
            grid-area: search !important;
            min-width: 0 !important;
        }

        .hr-payslip-page .payslip-toolbar-form .search-box {
            position: relative;
            width: 100%;
        }

        .hr-payslip-page .payslip-toolbar-form .search-icon-mobile {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            pointer-events: none;
            z-index: 1;
        }

        .hr-payslip-page .payslip-toolbar-form .search-input {
            width: 100% !important;
            padding: 12px 12px 12px 42px !important;
            border-radius: 12px !important;
            border: 1px solid #e2e8f0 !important;
            font-size: 14px !important;
            background: #fff !important;
        }

        .hr-payslip-page .payslip-toolbar-form .filter-group-actions {
            display: contents !important;
        }

        .hr-payslip-page .payslip-toolbar-form .page-size-selector {
            grid-area: limit !important;
            position: relative !important;
            display: flex !important;
            width: 72px !important;
            min-width: 72px !important;
            height: 48px !important;
            border-radius: 12px !important;
            background: #fff !important;
            border: 1px solid #e2e8f0 !important;
            align-items: center !important;
            justify-content: center !important;
            overflow: hidden !important;
        }

        .hr-payslip-page .payslip-toolbar-form .page-size-selector::after {
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

        .hr-payslip-page .payslip-toolbar-form .page-size-selector #rowsPerPageMobile {
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

        .hr-payslip-page .payslip-mobile-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .mobile-payslip-card {
            background: #fff;
            border-radius: 12px;
            border: 1px solid #eef2f7;
            box-shadow: 0 1px 6px rgba(15, 23, 42, 0.05);
            overflow: hidden;
        }

        .mobile-payslip-card.expanded {
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.08);
        }

        .payslip-card-main {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            min-height: 52px;
        }

        .payslip-card-info {
            flex: 1;
            min-width: 0;
        }

        .payslip-card-meta-line {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 3px;
        }

        .payslip-card-meta {
            font-size: 0.72rem;
            font-weight: 700;
            color: #94a3b8;
        }

        .payslip-card-meta-dot {
            color: #cbd5e1;
            font-size: 0.7rem;
        }

        .payslip-card-name-line {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }

        .payslip-card-name-group {
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 0;
            flex: 1;
        }

        .payslip-card-name {
            font-size: 0.92rem;
            font-weight: 700;
            color: #0f172a;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            min-width: 0;
        }

        .payslip-card-name-group .status-pill {
            flex-shrink: 0;
            padding: 3px 8px !important;
            font-size: 0.58rem !important;
            border-radius: 999px !important;
        }

        .payslip-card-net {
            font-size: 0.88rem;
            font-weight: 800;
            color: var(--primary-teal);
            white-space: nowrap;
        }

        .payslip-card-expand-btn {
            width: 32px;
            height: 32px;
            min-width: 32px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            background: #f8fafc;
            color: #64748b;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }

        .payslip-card-expand-btn.active {
            background: rgba(13, 148, 136, 0.1);
            border-color: rgba(13, 148, 136, 0.25);
            color: var(--primary-teal);
        }

        .payslip-card-detail-panel {
            display: none;
            padding: 0 12px 12px;
            border-top: 1px solid #f1f5f9;
        }

        .payslip-mobile-detail-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            padding: 8px 0;
            border-bottom: 1px dashed #eef2f7;
        }

        .payslip-mobile-detail-row:last-of-type {
            border-bottom: none;
        }

        .payslip-mobile-detail-label {
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            color: #94a3b8;
        }

        .payslip-mobile-detail-value {
            font-size: 0.82rem;
            font-weight: 600;
            color: #334155;
            text-align: right;
        }

        .payslip-card-actions {
            display: flex;
            gap: 8px;
            margin-top: 10px;
        }

        .payslip-card-action-btn {
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
        }

        .payslip-card-action-btn.primary {
            background: var(--primary-teal);
            border-color: var(--primary-teal);
            color: #fff;
        }

        .payslip-mobile-empty {
            text-align: center;
            padding: 40px 16px;
            color: #94a3b8;
            font-weight: 600;
            background: #fff;
            border-radius: 12px;
            border: 1px dashed #e2e8f0;
        }

        .payslip-mobile-bottom-nav {
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

        .payslip-mobile-nav-btn {
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

        .payslip-mobile-nav-btn i {
            font-size: 18px;
        }

        .payslip-mobile-nav-btn.payslip-mobile-employee-btn { color: #ffa600; }
        .payslip-mobile-nav-btn.payslip-mobile-period-btn { color: #2563eb; }
        .payslip-mobile-nav-btn.payslip-mobile-actions-btn { color: #03ac47; }
        .payslip-mobile-nav-btn.active {
            background: rgba(15, 23, 42, 0.06);
        }

        .payslip-mobile-sheet {
            display: block;
            position: fixed;
            inset: 0;
            z-index: 1001;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.2s ease;
        }

        .payslip-mobile-sheet.active {
            pointer-events: auto;
            opacity: 1;
        }

        .payslip-mobile-sheet-backdrop {
            position: absolute;
            inset: 0;
            background: rgba(15, 23, 42, 0.45);
        }

        .payslip-mobile-sheet-panel {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            background: #fff;
            border-radius: 20px 20px 0 0;
            padding: 16px 16px calc(16px + env(safe-area-inset-bottom, 0px));
            max-height: 70vh;
            overflow-y: auto;
            box-shadow: 0 -8px 30px rgba(0, 0, 0, 0.12);
        }

        .payslip-mobile-sheet-panel h6 {
            margin: 0 0 12px;
            font-size: 0.95rem;
            font-weight: 800;
            color: #0f172a;
        }

        .payslip-mobile-sheet-options {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .payslip-mobile-sheet-option {
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

        .payslip-mobile-sheet-option.selected {
            border-color: #0d9488;
            background: rgba(13, 148, 136, 0.08);
            color: #0f766e;
        }

        .payslip-mobile-action-btn {
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

        .payslip-mobile-action-btn.download-all {
            background: #1e293b;
            border-color: #1e293b;
            color: #fff;
        }

        .hr-payslip-page .floating-clear-btn {
            bottom: calc(78px + env(safe-area-inset-bottom, 0px)) !important;
        }

        body.dark-mode .hr-payslip-page .payslip-toolbar-form .search-input,
        body.dark-mode .hr-payslip-page .payslip-toolbar-form .page-size-selector {
            background: rgba(255, 255, 255, 0.05) !important;
            border-color: rgba(255, 255, 255, 0.1) !important;
            color: #f8fafc !important;
        }

        body.dark-mode .mobile-payslip-card {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(255, 255, 255, 0.1);
        }

        body.dark-mode .payslip-card-name { color: #f8fafc; }
        body.dark-mode .payslip-mobile-detail-value { color: #e2e8f0; }
        body.dark-mode .payslip-mobile-bottom-nav {
            background: #121212;
            border-color: rgba(255, 255, 255, 0.1);
        }

        body.dark-mode .payslip-mobile-sheet-panel {
            background: #1a1a1a;
        }

        body.dark-mode .payslip-mobile-sheet-panel h6 { color: #f8fafc; }
        body.dark-mode .payslip-mobile-sheet-option,
        body.dark-mode .payslip-mobile-action-btn {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(255, 255, 255, 0.12);
            color: #e2e8f0;
        }
    }
</style>

<div class="content mgmt-center hr-payslip-page">
    <!-- BEGIN: Desktop UI -->
    <div class="container-fluid desktop-only-payslip">
        <!-- Page Header -->
        <div class="page-header-p">
            <div>
                <h2><i class="bi bi-wallet2"></i> Payslip Management Center</h2>
                <p class="text-muted mb-0">View, download and export employee payroll documents</p>
            </div>
            <button class="btn-export-bulk" onclick="downloadAllPayslips()">
                <i class="bi bi-file-earmark-zip"></i>
                <span>Download All (ZIP)</span>
            </button>
        </div>

        <!-- Summary Statistics -->
        <div class="summary-bar">
            <div class="summary-card-p">
                <div class="sum-icon"><i class="bi bi-cash-stack"></i></div>
                <div class="sum-info">
                    <h4 id="sumTotalPayout">₹0</h4>
                    <p>Total Payout (Month)</p>
                </div>
            </div>
            <div class="summary-card-p">
                <div class="sum-icon"><i class="bi bi-person-badge"></i></div>
                <div class="sum-info">
                    <h4 id="sumAvgSalary">₹0</h4>
                    <p>Average Net Salary</p>
                </div>
            </div>
            <div class="summary-card-p">
                <div class="sum-icon"><i class="bi bi-people"></i></div>
                <div class="sum-info">
                    <h4 id="sumEmpCount">0</h4>
                    <p>Employees Paid</p>
                </div>
            </div>
        </div>

        <!-- Filter Card -->
        <div class="filter-card-p">
            <div class="filter-group">
                <label>Employee Name</label>
                <select id="employeeSelect" class="form-select filter-select">
                    <option value="">All Employees</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Payroll Period</label>
                <select id="monthSelect" class="form-select filter-select">
                    <!-- Loaded via JS -->
                </select>
            </div>
            <div class="filter-group">
                <label>Rows Per Page</label>
                <select id="rowsPerPage" class="form-select filter-select" onchange="changePageSize(this.value)">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
            <button id="btnMainSearch" class="btn-search-p" onclick="lookupPayslip()">
                <i class="bi bi-search"></i> Find Payslips
            </button>
        </div>

        <!-- Results Header -->
        <div class="mb-3">
            <span id="resultsCountLabel" style="font-weight: 600; font-size: 0.85rem; color: var(--text-gray);">
                Generated Payroll History
            </span>
        </div>

        <!-- Results Table -->
        <div id="payslipTableContainer" class="user-table-container">
            <table class="user-data-table unified-table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Period</th>
                        <th>Net Salary</th>
                        <th>Status</th>
                        <th style="text-align: right; padding-right: 20px !important;">Action</th>
                    </tr>
                </thead>
                <tbody id="recentTableBody">
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 40px; color: #64748b;">
                            <span class="spinner-border spinner-border-sm me-2"></span> Initializing data...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div id="payslipPaginationWrap" class="pagination-section py-3" style="display: none;">
            <div class="d-flex flex-column align-items-center gap-2">
                <div class="pagination-info text-muted small" id="payslipPaginationInfo"></div>
                <div class="pagination-controls d-flex gap-2 flex-wrap justify-content-center" id="payslipPaginationControls"></div>
            </div>
        </div>
    </div>
    <!-- END: Desktop UI -->

    <!-- BEGIN: Mobile UI -->
    <div class="mobile-only-payslip">
        <div class="summary-wrapper pt-1 mb-2">
            <button type="button" class="summary-arrow left" id="summaryLeft" aria-label="Scroll summary left">‹</button>
            <div class="summary-section" id="summaryScroll">
                <div class="summary-card stat-card-headcount">
                    <span class="summary-text" style="font-weight: 600;">Total Payout : <span id="sumTotalPayoutMobile">₹0</span></span>
                </div>
                <div class="summary-card stat-card-present">
                    <span class="summary-text" style="font-weight: 600;">Avg Net Salary : <span id="sumAvgSalaryMobile">₹0</span></span>
                </div>
                <div class="summary-card stat-card-late">
                    <span class="summary-text" style="font-weight: 600;">Employees Paid : <span id="sumEmpCountMobile">0</span></span>
                </div>
            </div>
            <button type="button" class="summary-arrow right" id="summaryRight" aria-label="Scroll summary right">›</button>
        </div>

        <div class="glass-card payslip-controls-card mb-2">
            <div class="payslip-toolbar-form">
                <div class="filter-group-search">
                    <div class="search-box">
                        <i class="bi bi-search search-icon-mobile"></i>
                        <input type="text" id="payslipMobileSearch" class="search-input" placeholder="Search employee..." autocomplete="off">
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
            <select id="employeeSelectMobile" class="visually-hidden" tabindex="-1" aria-hidden="true">
                <option value="">All Employees</option>
            </select>
            <select id="monthSelectMobile" class="visually-hidden" tabindex="-1" aria-hidden="true"></select>
        </div>

        <div class="mb-2 px-1">
            <span id="resultsCountLabelMobile" style="font-weight: 600; font-size: 0.8rem; color: var(--text-gray);">Generated Payroll History</span>
        </div>

        <div class="payslip-mobile-list" id="recentMobileContainer"></div>

        <div id="payslipPaginationWrapMobile" class="pagination-section py-3" style="display: none;">
            <div class="d-flex flex-column align-items-center gap-2">
                <div class="pagination-info text-muted small" id="payslipPaginationInfoMobile"></div>
                <div class="pagination-controls d-flex gap-2 flex-wrap justify-content-center" id="payslipPaginationControlsMobile"></div>
            </div>
        </div>
    </div>
    <!-- END: Mobile UI -->
</div>

<nav class="payslip-mobile-bottom-nav d-md-none" aria-label="Payslip actions">
    <button type="button" class="payslip-mobile-nav-btn payslip-mobile-employee-btn" id="payslipMobileEmployeeBtn">
        <i class="bi bi-person-fill"></i>
        <span>Employee</span>
    </button>
    <button type="button" class="payslip-mobile-nav-btn payslip-mobile-period-btn" id="payslipMobilePeriodBtn">
        <i class="bi bi-calendar3"></i>
        <span>Period</span>
    </button>
    <button type="button" class="payslip-mobile-nav-btn payslip-mobile-actions-btn" id="payslipMobileActionsBtn">
        <i class="bi bi-download"></i>
        <span>Actions</span>
    </button>
</nav>

<div id="payslipMobileEmployeeSheet" class="payslip-mobile-sheet d-md-none" aria-hidden="true">
    <div class="payslip-mobile-sheet-backdrop" id="payslipMobileEmployeeBackdrop"></div>
    <div class="payslip-mobile-sheet-panel" role="dialog" aria-modal="true" aria-labelledby="payslipMobileEmployeeTitle">
        <h6 id="payslipMobileEmployeeTitle">Filter by Employee</h6>
        <div class="payslip-mobile-sheet-options" id="payslipMobileEmployeeOptions"></div>
    </div>
</div>

<div id="payslipMobilePeriodSheet" class="payslip-mobile-sheet d-md-none" aria-hidden="true">
    <div class="payslip-mobile-sheet-backdrop" id="payslipMobilePeriodBackdrop"></div>
    <div class="payslip-mobile-sheet-panel" role="dialog" aria-modal="true" aria-labelledby="payslipMobilePeriodTitle">
        <h6 id="payslipMobilePeriodTitle">Payroll Period</h6>
        <div class="payslip-mobile-sheet-options" id="payslipMobilePeriodOptions"></div>
    </div>
</div>

<div id="payslipMobileActionsSheet" class="payslip-mobile-sheet d-md-none" aria-hidden="true">
    <div class="payslip-mobile-sheet-backdrop" id="payslipMobileActionsBackdrop"></div>
    <div class="payslip-mobile-sheet-panel" role="dialog" aria-modal="true" aria-labelledby="payslipMobileActionsTitle">
        <h6 id="payslipMobileActionsTitle">Payslip Actions</h6>
        <button type="button" class="payslip-mobile-action-btn download-all" onclick="downloadAllPayslips(); closePayslipMobileSheets();">
            <i class="bi bi-file-earmark-zip-fill"></i> Download All (ZIP)
        </button>
        <button type="button" class="payslip-mobile-action-btn" onclick="lookupPayslip(); closePayslipMobileSheets();">
            <i class="bi bi-arrow-clockwise"></i> Refresh Results
        </button>
    </div>
</div>

<button type="button" id="payslipFloatingClearFilters" class="floating-clear-btn d-md-none" style="display:none;">
    <i class="bi bi-x-circle"></i> Clear Filters
</button>

<!-- ================= MODALS ================= -->

<!-- Payslip Preview Modal -->
<div class="modal fade" id="payslipModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content" style="border-radius: 12px; border: none; box-shadow: 0 20px 40px rgba(0,0,0,0.15);">
            <div class="modal-header" style="background: #004d80; color: white; border-radius: 12px 12px 0 0; padding: 15px 25px;">
                <h5 class="modal-title" style="font-weight: 600; font-size: 1.1rem;"><i class="bi bi-file-earmark-text me-2"></i> Payslip Preview</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" style="padding: 40px; background: #fff;">
                <div class="preview-card" style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #333;">
                    <div style="text-align: center; margin-bottom: 40px;">
                        <h1 style="margin: 0; color: #005691; font-size: 32px; font-weight: 800;">Search Homes India Pvt Ltd</h1>
                        <p style="margin: 10px 0; color: #666; font-size: 18px;">Payslip for the period of <span id="p_month_year">May 2026</span></p>
                    </div>
                    
                    <div style="border-top: 2px solid #005691; margin: 20px 0;"></div>
                    
                    <table class="payslip-info-table" style="width: 100%; margin-bottom: 30px; border-collapse: collapse;">
                        <tr>
                            <td style="padding: 12px 15px; border: 1px solid #e2e8f0; width: 50%; font-size: 15px;"><strong>Employee Name:</strong> <span id="p_name">---</span></td>
                            <td style="padding: 12px 15px; border: 1px solid #e2e8f0; width: 50%; font-size: 15px;"><strong>Designation:</strong> <span id="p_designation">---</span></td>
                        </tr>
                        <tr>
                            <td style="padding: 12px 15px; border: 1px solid #e2e8f0; font-size: 15px;"><strong><span id="p_days_label">Total Days</span>:</strong> <span id="p_total_days">31</span></td>
                            <td style="padding: 12px 15px; border: 1px solid #e2e8f0; font-size: 15px;"><strong>Loss of Pay (Days):</strong> <span id="p_lop_days">0</span></td>
                        </tr>
                        <tr id="p_calendar_row" style="display: none;">
                            <td colspan="2" style="padding: 12px 15px; border: 1px solid #e2e8f0; font-size: 14px;"><strong>Calendar:</strong> <span id="p_calendar_days">30</span> days &nbsp;|&nbsp; <strong>Sundays (excluded):</strong> <span id="p_sunday_count">0</span></td>
                        </tr>
                    </table>
                    
                    <div class="payslip-labels-container" style="display: flex; gap: 20px; margin-bottom: 5px;">
                        <div style="flex: 1; font-size: 14px; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-bottom: 10px;">Earnings</div>
                        <div style="flex: 1; font-size: 14px; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-bottom: 10px;">Deductions</div>
                    </div>
                    
                    <div class="payslip-breakdown-container" style="border: 1px solid #cbd5e1; border-radius: 4px; overflow: hidden; margin-bottom: 30px;">
                        <table class="payslip-breakdown-table" style="width: 100%; border-collapse: collapse;">
                            <tr>
                                <td class="payslip-breakdown-left" style="width: 50%; vertical-align: top; padding: 10px; border-right: 1px solid #cbd5e1;">
                                    <div id="p_earnings_breakdown"></div>
                                </td>
                                <td class="payslip-breakdown-right" style="width: 50%; vertical-align: top; padding: 10px;">
                                    <div id="p_deductions_breakdown" style="padding: 0;">
                                        <!-- Dynamic deductions sub-table will be injected here -->
                                    </div>
                                </td>
                            </tr>
                            <tr class="payslip-totals-row" style="background: #fff; font-weight: 700; font-size: 15px;">
                                <td class="payslip-total-left" style="border: 1px solid #cbd5e1; border-right: 1px solid #cbd5e1;">
                                    <div style="display: flex; justify-content: space-between; padding: 5px 10px;">
                                        <span>Total Earnings:</span>
                                        <span id="p_total_earnings">₹0</span>
                                    </div>
                                </td>
                                <td class="payslip-total-right" style="border: 1px solid #cbd5e1;">
                                    <div style="display: flex; justify-content: space-between; padding: 5px 10px;">
                                        <span>Total Deductions:</span>
                                        <span id="p_deductions" style="color: #ef4444;">₹0</span>
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <div style="margin-top: 40px; padding: 25px; border: 1px solid #e2e8f0; border-radius: 12px; background: #fff; text-align: right; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
                        <span style="font-size: 24px; font-weight: 800; color: #1e293b;">Net Pay: <span id="p_net">₹0</span></span>
                        <div style="font-size: 13px; font-weight: 500; color: #666; margin-top: 5px; font-style: italic;" id="p_words">Zero Rupees Only</div>
                    </div>

                    <div style="margin-top: 40px; text-align: center; font-size: 11px; color: #888; border-top: 1px solid #eee; padding-top: 20px;">
                        This is a computer-generated document and does not require a physical signature.
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="padding: 20px 40px; background: #f8fafc; border-radius: 0 0 12px 12px;">
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary px-4" id="btnDownloadSingle" style="background: #004d80; border: none;">
                    <i class="bi bi-download me-2"></i> Download PDF
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Hidden template for PDF generation -->
<div id="printablePayslip">
    <!-- This will be cloned/updated by JS before PDF generation -->
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="assets/js/summary-cards.js?v=<?php echo time(); ?>"></script>
<script>
    const payrollId = <?php echo $payroll_id; ?>;
    let currentData = [];
    let payslipCurrentPage = 1;
    let payslipTotalRecords = 0;
    let PAYSLIP_PAGE_SIZE = 10;
    let payslipMobileSearchQuery = '';

    function isPayslipMobileView() {
        return window.innerWidth <= 768;
    }

    function closePayslipMobileSheets() {
        $('.payslip-mobile-sheet').removeClass('active').attr('aria-hidden', 'true');
        $('.payslip-mobile-nav-btn').removeClass('active');
    }

    function openPayslipMobileSheet(sheetId, btnId) {
        closePayslipMobileSheets();
        const sheet = document.getElementById(sheetId);
        if (!sheet) return;
        sheet.classList.add('active');
        sheet.setAttribute('aria-hidden', 'false');
        if (btnId) document.getElementById(btnId)?.classList.add('active');
    }

    function populatePayslipMobileEmployeeOptions() {
        const container = $('#payslipMobileEmployeeOptions');
        container.empty();
        const current = $('#employeeSelectMobile').val() || '';
        container.append(`<button type="button" class="payslip-mobile-sheet-option${current === '' ? ' selected' : ''}" data-employee-id="">All Employees</button>`);
        $('#employeeSelectMobile option').each(function() {
            const val = String($(this).val() || '');
            if (!val) return;
            const selected = val === current ? ' selected' : '';
            container.append(`<button type="button" class="payslip-mobile-sheet-option${selected}" data-employee-id="${val}">${$(this).text()}</button>`);
        });
    }

    function populatePayslipMobilePeriodOptions() {
        const container = $('#payslipMobilePeriodOptions');
        container.empty();
        const current = $('#monthSelectMobile').val() || '';
        $('#monthSelectMobile option').each(function() {
            const val = String($(this).val() || '');
            if (!val) return;
            const selected = val === current ? ' selected' : '';
            container.append(`<button type="button" class="payslip-mobile-sheet-option${selected}" data-period="${val}">${$(this).text()}</button>`);
        });
    }

    function updatePayslipMobileFilterUI() {
        const hasEmployee = !!($('#employeeSelectMobile').val() || '');
        const hasSearch = !!payslipMobileSearchQuery;
        const showClear = hasEmployee || hasSearch;
        $('#payslipFloatingClearFilters').css('display', showClear ? 'flex' : 'none');
        $('#payslipMobileEmployeeBtn').toggleClass('active', hasEmployee);
    }

    function clearPayslipMobileFilters() {
        payslipMobileSearchQuery = '';
        $('#payslipMobileSearch').val('');
        $('#employeeSelectMobile').val('');
        $('#employeeSelect').val('');
        payslipCurrentPage = 1;
        updatePayslipMobileFilterUI();
        lookupPayslip();
    }

    function togglePayslipMobileCard(btn) {
        const card = btn.closest('.mobile-payslip-card');
        const panel = card?.querySelector('.payslip-card-detail-panel');
        const icon = btn.querySelector('i');
        if (!card || !panel) return;

        const isOpen = card.classList.contains('expanded');
        document.querySelectorAll('.mobile-payslip-card.expanded').forEach(function(c) {
            if (c === card) return;
            c.classList.remove('expanded');
            const p = c.querySelector('.payslip-card-detail-panel');
            if (p) p.style.display = 'none';
            const b = c.querySelector('.payslip-card-expand-btn');
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

    function getPayslipDisplayData() {
        let rows = Array.isArray(currentData) ? currentData.slice() : [];
        if (isPayslipMobileView() && payslipMobileSearchQuery) {
            const q = payslipMobileSearchQuery.toLowerCase();
            rows = rows.filter(function(r) {
                return String(r.employee_name || '').toLowerCase().includes(q)
                    || String(r.employee_id || '').toLowerCase().includes(q)
                    || String(r.month_year || '').toLowerCase().includes(q);
            });
        }
        return rows;
    }

    function changePageSize(val, source) {
        PAYSLIP_PAGE_SIZE = parseInt(val);
        payslipCurrentPage = 1;
        
        // Sync selectors
        if (source === 'mobile') {
            document.getElementById('rowsPerPage').value = val;
        } else {
            const mob = document.getElementById('rowsPerPageMobile');
            if (mob) mob.value = val;
        }
        
        lookupPayslip(false, 1);
    }

    // 1. Initial Load & Dynamic Search
    $(document).ready(function() {
        populateFilters();

        if (typeof initSummaryCardsScroll === 'function') {
            initSummaryCardsScroll();
        }

        let payslipSearchTimer = null;
        $('#payslipMobileSearch').on('input', function() {
            clearTimeout(payslipSearchTimer);
            payslipSearchTimer = setTimeout(function() {
                payslipMobileSearchQuery = ($('#payslipMobileSearch').val() || '').trim();
                payslipCurrentPage = 1;
                updatePayslipMobileFilterUI();
                renderPayslipPage();
            }, 250);
        });

        $('#payslipMobileEmployeeBtn').on('click', function() {
            if ($('#payslipMobileEmployeeSheet').hasClass('active')) {
                closePayslipMobileSheets();
            } else {
                populatePayslipMobileEmployeeOptions();
                openPayslipMobileSheet('payslipMobileEmployeeSheet', 'payslipMobileEmployeeBtn');
            }
        });

        $('#payslipMobilePeriodBtn').on('click', function() {
            if ($('#payslipMobilePeriodSheet').hasClass('active')) {
                closePayslipMobileSheets();
            } else {
                populatePayslipMobilePeriodOptions();
                openPayslipMobileSheet('payslipMobilePeriodSheet', 'payslipMobilePeriodBtn');
            }
        });

        $('#payslipMobileActionsBtn').on('click', function() {
            if ($('#payslipMobileActionsSheet').hasClass('active')) {
                closePayslipMobileSheets();
            } else {
                openPayslipMobileSheet('payslipMobileActionsSheet', 'payslipMobileActionsBtn');
            }
        });

        $('#payslipMobileEmployeeBackdrop, #payslipMobilePeriodBackdrop, #payslipMobileActionsBackdrop').on('click', closePayslipMobileSheets);

        $('#payslipMobileEmployeeOptions').on('click', '.payslip-mobile-sheet-option', function() {
            const eid = String($(this).data('employee-id') || '');
            $('#employeeSelectMobile').val(eid);
            $('#employeeSelect').val(eid);
            payslipCurrentPage = 1;
            updatePayslipMobileFilterUI();
            closePayslipMobileSheets();
            lookupPayslip();
        });

        $('#payslipMobilePeriodOptions').on('click', '.payslip-mobile-sheet-option', function() {
            const period = String($(this).data('period') || '');
            $('#monthSelectMobile').val(period);
            $('#monthSelect').val(period);
            payslipCurrentPage = 1;
            closePayslipMobileSheets();
            lookupPayslip();
        });

        $('#payslipFloatingClearFilters').on('click', clearPayslipMobileFilters);

        $(document).on('click', '.mobile-payslip-card', function(e) {
            if (!isPayslipMobileView()) return;
            if ($(e.target).closest('.payslip-card-detail-panel, .payslip-card-expand-btn, .payslip-card-action-btn').length) return;
            const expandBtn = this.querySelector('.payslip-card-expand-btn');
            if (expandBtn) expandBtn.click();
        });
        
        if (payrollId) {
            // If linked from payroll page, view specific id
            viewPayslipModal(payrollId);
        }
        
        // Initial sync of rowsPerPage dropdowns
        changePageSize($('#rowsPerPage').val(), 'desktop');
        
        // Initial table load (Recent/Current Month)
        lookupPayslip(true); // Load current month (includes summary in one request)

        // Add change listeners for immediate updates
        $('#employeeSelect, #monthSelect, #employeeSelectMobile, #monthSelectMobile').on('change', function() {
            if (this.id === 'employeeSelect') {
                $('#employeeSelectMobile').val($(this).val());
            } else if (this.id === 'employeeSelectMobile') {
                $('#employeeSelect').val($(this).val());
            } else if (this.id === 'monthSelect') {
                $('#monthSelectMobile').val($(this).val());
            } else if (this.id === 'monthSelectMobile') {
                $('#monthSelect').val($(this).val());
            }
            updatePayslipMobileFilterUI();
            lookupPayslip();
        });
    });

    function populateFilters() {
        // Months (Last 6)
        const monthNames = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
        const d = new Date();
        for (let i = 0; i < 6; i++) {
            let date = new Date(d.getFullYear(), d.getMonth() - i, 1);
            let label = monthNames[date.getMonth()] + " " + date.getFullYear();
            $('#monthSelect, #monthSelectMobile').append(`<option value="${label}" ${i===0 ? 'selected' : ''}>${label}</option>`);
        }

        // Employees
        $.ajax({
            url: 'action.php',
            method: 'GET',
            data: { fetch_users_json: 1 },
            dataType: 'json',
            success: function(users) {
                const select = $('#employeeSelect, #employeeSelectMobile');
                if (users && Array.isArray(users)) {
                    users.forEach(u => {
                        select.append(`<option value="${u.id}">${u.username} (${u.emp_code})</option>`);
                    });
                }
            },
            error: function(xhr, status, error) {
                console.error('Employee load failed:', error);
            }
        });
    }

    function applyPayslipSummary(res) {
        if (!res) return;
        const totalText = '₹' + parseFloat(res.total_payout || 0).toLocaleString();
        const avgText = '₹' + parseFloat(res.avg_salary || 0).toLocaleString(undefined, {maximumFractionDigits: 0});
        const countText = res.total_emps || 0;

        $('#sumTotalPayout, #sumTotalPayoutMobile').text(totalText);
        $('#sumAvgSalary, #sumAvgSalaryMobile').text(avgText);
        $('#sumEmpCount, #sumEmpCountMobile').text(countText);
    }

    function lookupPayslip(isInitial = false, page = 1) {
        const isMobile = window.innerWidth <= 768;
        const eid = isMobile ? $('#employeeSelectMobile').val() : $('#employeeSelect').val();
        const month = isMobile ? $('#monthSelectMobile').val() : $('#monthSelect').val();
        
        if (!isInitial && !month) {
            Swal.fire('Tip', 'Please select at least a pay period.', 'info');
            return;
        }

        $('#btnMainSearch').html('<span class="spinner-border spinner-border-sm"></span> Searching...');
        
        $.ajax({
            url: 'action.php',
            method: 'GET',
            data: { search_payslips: 1, eid: eid, month: month, page: page, limit: PAYSLIP_PAGE_SIZE },
            dataType: 'json',
            success: function(res) {
                $('#btnMainSearch').html('<i class="bi bi-search"></i> Find Payslips');
                const rows = Array.isArray(res) ? res : (Array.isArray(res.data) ? res.data : []);
                payslipTotalRecords = (res && res.total != null) ? parseInt(res.total, 10) : rows.length;
                payslipCurrentPage = (res && res.page) ? parseInt(res.page, 10) : page;
                currentData = rows;
                renderPayslipPage();
                updatePayslipMobileFilterUI();
                if (res && res.summary) {
                    applyPayslipSummary(res.summary);
                } else if (month) {
                    fetchSummary(month, eid);
                }
            },
            error: function(xhr, status, error) {
                $('#btnMainSearch').html('<i class="bi bi-search"></i> Find Payslips');
                console.error('Lookup failed:', error);
                renderTable([]); 
            }
        });
    }

    function fetchSummary(month, eid = null) {
        $.ajax({
            url: 'action.php',
            method: 'GET',
            data: { fetch_salary_summary: 1, month: month, eid: eid },
            dataType: 'json',
            success: function(res) {
                applyPayslipSummary(res);
            }
        });
    }

    function renderTable(data) {
        currentData = Array.isArray(data) ? data : [];
        payslipCurrentPage = 1;
        payslipTotalRecords = currentData.length;
        renderPayslipPage();
    }

    function updatePayslipPaginationUI() {
        const pairs = [
            { wrap: document.getElementById('payslipPaginationWrap'), info: document.getElementById('payslipPaginationInfo'), controls: document.getElementById('payslipPaginationControls') },
            { wrap: document.getElementById('payslipPaginationWrapMobile'), info: document.getElementById('payslipPaginationInfoMobile'), controls: document.getElementById('payslipPaginationControlsMobile') }
        ];
        const total = payslipMobileSearchQuery ? getPayslipDisplayData().length : payslipTotalRecords;
        pairs.forEach(function (p) {
            if (!p.wrap || !p.info || !p.controls) return;
            if (total === 0) {
                p.wrap.style.display = 'none';
                return;
            }
            const totalPages = Math.max(1, Math.ceil(total / PAYSLIP_PAGE_SIZE));
            if (payslipCurrentPage > totalPages) payslipCurrentPage = totalPages;
            if (payslipCurrentPage < 1) payslipCurrentPage = 1;
            const start = (payslipCurrentPage - 1) * PAYSLIP_PAGE_SIZE;
            const end = Math.min(start + PAYSLIP_PAGE_SIZE, total);
            p.info.textContent = 'Showing ' + (total ? (start + 1) : 0) + ' to ' + end + ' of ' + total + ' entries';
            p.controls.innerHTML = '';
            p.wrap.style.display = 'flex';
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
                p.controls.appendChild(a);
            }
            addBtn('&larr;', payslipCurrentPage <= 1, function () {
                lookupPayslip(false, payslipCurrentPage - 1);
            });
            const win = 5;
            let s = Math.max(1, payslipCurrentPage - 2);
            let e2 = Math.min(totalPages, s + win - 1);
            if (e2 - s < win - 1) s = Math.max(1, e2 - win + 1);
            for (let i = s; i <= e2; i++) {
                (function (pi) {
                    const a = document.createElement('a');
                    a.href = '#';
                    a.className = 'page-btn' + (pi === payslipCurrentPage ? ' active' : '');
                    a.textContent = pi;
                    a.addEventListener('click', function (e) {
                        e.preventDefault();
                        lookupPayslip(false, pi);
                    });
                    p.controls.appendChild(a);
                })(i);
            }
            addBtn('&rarr;', payslipCurrentPage >= totalPages, function () {
                lookupPayslip(false, payslipCurrentPage + 1);
            });
        });
    }

    function renderPayslipPage() {
        const displayData = getPayslipDisplayData();
        let html = '';
        let mobileHtml = '';
        const displayTotal = displayData.length;
        const total = payslipMobileSearchQuery ? displayTotal : payslipTotalRecords;
        if (displayTotal > 0) {
            $('#resultsCountLabel').text('Found ' + total + ' records for selected criteria');
            $('#resultsCountLabelMobile').text('Found ' + total + ' record' + (total === 1 ? '' : 's'));
            displayData.forEach(function (r) {
                const netFormatted = parseFloat(r.net_salary).toLocaleString();
                html += `
                    <tr class="user-data-row">
                        <td>
                            <div style="font-weight:700; color: var(--text-dark);">${r.employee_name}</div>
                            <div style="font-size:0.7rem; color:#64748b;">CODE: ${r.employee_id}</div>
                        </td>
                        <td><span style="font-weight:600;">${r.month_year}</span></td>
                        <td style="font-weight:800; color: var(--primary-teal);">₹${parseFloat(r.net_salary).toLocaleString()}</td>
                        <td><span class="status-pill status-generated">Generated</span></td>
                        <td style="text-align:right; padding-right: 20px !important;">
                            <div class="action-group">
                                <button class="btn-table-action" title="View" onclick="viewPayslipModal(${r.id})">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <button class="btn-table-action" title="Download PDF" onclick="downloadRowPDF(${r.id})">
                                    <i class="bi bi-download"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;

                mobileHtml += `
                    <div class="mobile-payslip-card">
                        <div class="payslip-card-main">
                            <div class="payslip-card-info">
                                <div class="payslip-card-meta-line">
                                    <span class="payslip-card-meta">${r.month_year}</span>
                                </div>
                                <div class="payslip-card-name-line">
                                    <div class="payslip-card-name-group">
                                        <span class="payslip-card-name">${r.employee_name}</span>
                                        <span class="status-pill status-generated">Generated</span>
                                    </div>
                                    <span class="payslip-card-net">₹${netFormatted}</span>
                                </div>
                            </div>
                            <button type="button" class="payslip-card-expand-btn" onclick="togglePayslipMobileCard(this)" aria-expanded="false" aria-label="Expand payslip details">
                                <i class="bi bi-chevron-down"></i>
                            </button>
                        </div>
                        <div class="payslip-card-detail-panel">
                            <div class="payslip-mobile-detail-row">
                                <span class="payslip-mobile-detail-label">Employee Code</span>
                                <span class="payslip-mobile-detail-value">${r.employee_id}</span>
                            </div>
                            <div class="payslip-mobile-detail-row">
                                <span class="payslip-mobile-detail-label">Period</span>
                                <span class="payslip-mobile-detail-value">${r.month_year}</span>
                            </div>
                            <div class="payslip-mobile-detail-row">
                                <span class="payslip-mobile-detail-label">Net Salary</span>
                                <span class="payslip-mobile-detail-value">₹${netFormatted}</span>
                            </div>
                            <div class="payslip-card-actions">
                                <button type="button" class="payslip-card-action-btn" onclick="viewPayslipModal(${r.id})">
                                    <i class="bi bi-eye"></i> View
                                </button>
                                <button type="button" class="payslip-card-action-btn primary" onclick="downloadRowPDF(${r.id})">
                                    <i class="bi bi-download"></i> PDF
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            });
        } else {
            $('#resultsCountLabel').text('No records found');
            $('#resultsCountLabelMobile').text(payslipMobileSearchQuery ? 'No matching records' : 'No records found');
            html = '<tr class="user-data-row"><td colspan="5" style="text-align:center; padding: 60px; color:#64748b;">No processed payroll discovered for this selection.</td></tr>';
            mobileHtml = '<div class="payslip-mobile-empty">No processed payroll discovered for this selection.</div>';
        }
        $('#recentTableBody').html(html);
        $('#recentMobileContainer').html(mobileHtml);
        updatePayslipPaginationUI();
    }

    function viewPayslipModal(id) {
        $.ajax({
            url: 'action.php',
            method: 'GET',
            data: { fetch_payslip_data: 1, id: id },
            dataType: 'json',
            success: function(res) {
                if (!res || !res.id) {
                    Swal.fire('Error', 'Payslip not found.', 'error');
                    return;
                }
                updatePayslipUI(res);
                $('#btnDownloadSingle').off('click').on('click', () => downloadRowPDF(id));
                new bootstrap.Modal(document.getElementById('payslipModal')).show();
            },
            error: function() {
                Swal.fire('Error', 'Failed to load payslip details.', 'error');
            }
        });
    }

    function fmtRupee(amount) {
        return '₹' + Math.round(parseFloat(amount) || 0).toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 0 });
    }

    function getPayslipView(res) {
        if (res.payslip_view) return res.payslip_view;
        const totalDays = parseFloat(res.total_days) || 30;
        const presentDays = parseFloat(res.present_days) || 0;
        const lops = Math.max(0, totalDays - presentDays);
        const monthlyGross = parseFloat(res.base_salary) || 0;
        const hasManual = (
            (parseFloat(res.one_amt) > 0) ||
            (parseFloat(res.two_amt) > 0) ||
            (parseFloat(res.thrid_amt) > 0) ||
            (parseFloat(res.forth_amt) > 0) ||
            (parseFloat(res.fifth_amt) > 0) ||
            (parseFloat(res.sixth_amt) > 0)
        );
        let basic, hra, conveyance, special, bonus, pf, pt, medical, custom;
        bonus = parseFloat(res.incentives) || 0;
        if (hasManual) {
            basic = parseFloat(res.one_amt) || 0;
            hra = parseFloat(res.two_amt) || 0;
            conveyance = parseFloat(res.thrid_amt) || 0;
            special = parseFloat(res.forth_amt) || 0;
            pf = parseFloat(res.fifth_amt) || 0;
            pt = 0;
            medical = 0;
            custom = parseFloat(res.sixth_amt) || 0;
        } else {
            basic = Math.round(monthlyGross * 0.5);
            hra = Math.round(monthlyGross * 0.2);
            conveyance = Math.round(monthlyGross * 0.07);
            special = monthlyGross - (basic + hra + conveyance);
            pf = 1800;
            pt = 200;
            medical = 817;
            custom = 0;
        }
        const grossBase = basic + hra + conveyance + special;
        const lopAmount = totalDays > 0 ? Math.round(grossBase * (lops / totalDays)) : 0;
        const totalEarnings = grossBase + bonus;
        const totalDeductions = pf + pt + medical + custom + lopAmount;
        return {
            month_year: res.month_year,
            employee_name: res.employee_name,
            emp_code: res.emp_code || res.employee_id,
            designation: res.designation || 'Employee',
            pay_denominator: totalDays,
            lops: lops,
            calendar_days: null,
            sunday_count: null,
            sundays_are_paid: true,
            earnings: { basic, hra, conveyance, special, bonus },
            deductions: { pf, pt, medical, custom },
            lop_amount: lopAmount,
            total_earnings: totalEarnings,
            total_deductions: totalDeductions,
            net_pay: parseFloat(res.net_salary) || (totalEarnings - totalDeductions)
        };
    }

    function buildBreakdownTableRows(items, valueColor) {
        let html = '<table style="width: 100%; border-collapse: collapse;">';
        items.forEach(function (item, idx) {
            const border = idx < items.length - 1 ? 'border-bottom: 1px solid #eee;' : '';
            html += `<tr><td style="padding: 10px 15px; border: 1px solid #e2e8f0; font-size: 14px; ${border}">${item.label}</td>` +
                `<td style="padding: 10px 15px; border: 1px solid #e2e8f0; text-align: right; font-weight: 600; font-size: 14px; ${border}${valueColor ? ' color: ' + valueColor + ';' : ''}">${fmtRupee(item.value)}</td></tr>`;
        });
        html += '</table>';
        return html;
    }

    function updatePayslipUI(res) {
        const view = getPayslipView(res);
        $('#p_month_year').text(view.month_year || res.month_year);
        $('#p_name').text(view.employee_name || res.employee_name);
        $('#p_code').text(view.emp_code || res.emp_code || res.employee_id);
        $('#p_designation').text(view.designation || res.designation || 'Employee');

        $('#p_days_label').text(view.sundays_are_paid === false ? 'Working Days' : 'Total Days');
        $('#p_total_days').text(view.pay_denominator);
        $('#p_lop_days').text(view.lops > 0 ? view.lops : 0);

        if (view.sundays_are_paid === false && view.sunday_count != null) {
            $('#p_calendar_row').show();
            $('#p_calendar_days').text(view.calendar_days || view.pay_denominator);
            $('#p_sunday_count').text(view.sunday_count);
        } else {
            $('#p_calendar_row').hide();
        }

        const e = view.earnings;
        const earningsItems = [
            { label: 'Basic', value: e.basic },
            { label: 'HRA', value: e.hra },
            { label: 'Conveyance', value: e.conveyance },
            { label: 'Special Allowance', value: e.special }
        ];
        if (parseFloat(e.bonus) > 0) {
            earningsItems.push({ label: 'Bonus/Incentive', value: e.bonus });
        }
        $('#p_earnings_breakdown').html(buildBreakdownTableRows(earningsItems));

        const d = view.deductions;
        const deductionItems = [];
        if (parseFloat(d.pf) > 0) deductionItems.push({ label: 'PF', value: d.pf });
        if (parseFloat(d.pt) > 0) deductionItems.push({ label: 'Professional Tax', value: d.pt });
        if (parseFloat(d.medical) > 0) deductionItems.push({ label: 'Medical Benefit', value: d.medical });
        if (parseFloat(d.custom) > 0) deductionItems.push({ label: 'Other Deductions', value: d.custom });
        if (parseFloat(view.lop_amount) > 0) deductionItems.push({ label: 'LOP Deduction', value: view.lop_amount });
        $('#p_deductions_breakdown').html(buildBreakdownTableRows(deductionItems, '#ef4444'));

        $('#p_total_earnings').text(fmtRupee(view.total_earnings));
        $('#p_deductions').text(fmtRupee(view.total_deductions));
        $('#p_net').text(fmtRupee(view.net_pay));
        $('#p_words').text(numberToWords(Math.round(view.net_pay)) + ' Rupees Only');
    }

    function generatePayslipHtml(r) {
        const view = getPayslipView(r);
        const e = view.earnings;
        const d = view.deductions;
        const daysLabel = view.sundays_are_paid === false ? 'Working Days' : 'Total Days';

        const leftRows = [
            { name: 'Basic', value: e.basic },
            { name: 'HRA', value: e.hra },
            { name: 'Conveyance', value: e.conveyance },
            { name: 'Special Allowance', value: e.special }
        ];
        if (parseFloat(e.bonus) > 0) leftRows.push({ name: 'Bonus/Incentive', value: e.bonus });

        const deductionsList = [];
        if (parseFloat(d.pf) > 0) deductionsList.push({ name: 'PF', value: d.pf });
        if (parseFloat(d.pt) > 0) deductionsList.push({ name: 'Professional Tax', value: d.pt });
        if (parseFloat(d.medical) > 0) deductionsList.push({ name: 'Medical Benefit', value: d.medical });
        if (parseFloat(d.custom) > 0) deductionsList.push({ name: 'Other Deductions', value: d.custom });
        if (parseFloat(view.lop_amount) > 0) deductionsList.push({ name: 'LOP Deduction', value: view.lop_amount });

        const maxRows = Math.max(leftRows.length, deductionsList.length);
        let itemsHtml = '';
        for (let i = 0; i < maxRows; i++) {
            const left = leftRows[i] || { name: '', value: null };
            const right = deductionsList[i] || { name: '', value: null };
            const leftValText = left.value !== null ? fmtRupee(left.value) : '';
            const rightValText = right.value !== null ? fmtRupee(right.value) : '';
            itemsHtml += `
                <tr style="border-bottom: 1px solid #e2e8f0; font-size: 14px;">
                    <td style="padding: 12px 15px; border-right: 1px solid #cbd5e1; color: #334155;">${left.name}</td>
                    <td style="padding: 12px 15px; text-align: right; border-right: 1px solid #cbd5e1; font-weight: 600; color: #1e293b;">${leftValText}</td>
                    <td style="padding: 12px 15px; border-right: 1px solid #cbd5e1; color: #334155;">${right.name}</td>
                    <td style="padding: 12px 15px; text-align: right; font-weight: 600; color: #ef4444;">${rightValText}</td>
                </tr>
            `;
        }

        const calendarRow = (view.sundays_are_paid === false && view.sunday_count != null)
            ? `<tr style="border: 1px solid #e2e8f0;"><td colspan="2" style="padding: 12px 15px;"><strong>Calendar:</strong> ${view.calendar_days || view.pay_denominator} days &nbsp;|&nbsp; <strong>Sundays (excluded):</strong> ${view.sunday_count}</td></tr>`
            : '';

        return `
            <div style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #333; padding: 40px; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; width: 720px; box-sizing: border-box; margin: 0 auto;">
                <div style="text-align: center; margin-bottom: 30px;">
                    <h1 style="margin: 0; color: #005691; font-size: 28px; font-weight: 800; letter-spacing: -0.5px;">Search Homes India Pvt Ltd</h1>
                    <p style="margin: 8px 0 0 0; color: #64748b; font-size: 16px; font-weight: 600;">Payslip for the period of ${view.month_year || r.month_year}</p>
                </div>
                <div style="border-top: 3px solid #005691; margin-bottom: 25px;"></div>
                <table style="width: 100%; margin-bottom: 25px; border-collapse: collapse; font-size: 14px;">
                    <tr style="border: 1px solid #e2e8f0;">
                        <td style="padding: 12px 15px; border-right: 1px solid #e2e8f0; width: 50%;"><strong>Employee Name:</strong> <span style="color: #0f172a; font-weight: 600; margin-left: 5px;">${view.employee_name || r.employee_name}</span></td>
                        <td style="padding: 12px 15px; width: 50%;"><strong>Designation:</strong> <span style="color: #0f172a; font-weight: 600; margin-left: 5px;">${view.designation || r.designation || 'Employee'}</span></td>
                    </tr>
                    <tr style="border: 1px solid #e2e8f0;">
                        <td style="padding: 12px 15px; border-right: 1px solid #e2e8f0;"><strong>${daysLabel}:</strong> <span style="color: #0f172a; font-weight: 600; margin-left: 5px;">${view.pay_denominator}</span></td>
                        <td style="padding: 12px 15px;"><strong>Loss of Pay (Days):</strong> <span style="color: #0f172a; font-weight: 600; margin-left: 5px;">${view.lops}</span></td>
                    </tr>
                    ${calendarRow}
                </table>
                <div style="border: 1px solid #cbd5e1; border-radius: 6px; overflow: hidden; margin-bottom: 25px;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr style="background: #f8fafc; border-bottom: 2px solid #cbd5e1; font-size: 13px; font-weight: 700; text-transform: uppercase; color: #475569; letter-spacing: 0.5px;">
                            <td style="padding: 12px 15px; width: 35%; border-right: 1px solid #cbd5e1;">Earnings</td>
                            <td style="padding: 12px 15px; width: 15%; text-align: right; border-right: 1px solid #cbd5e1;">Amount</td>
                            <td style="padding: 12px 15px; width: 35%; border-right: 1px solid #cbd5e1;">Deductions</td>
                            <td style="padding: 12px 15px; width: 15%; text-align: right;">Amount</td>
                        </tr>
                        ${itemsHtml}
                        <tr style="background: #f8fafc; font-weight: 700; font-size: 14px; border-top: 2px solid #cbd5e1; color: #1e293b;">
                            <td style="padding: 12px 15px; border-right: 1px solid #cbd5e1;">Total Earnings:</td>
                            <td style="padding: 12px 15px; text-align: right; border-right: 1px solid #cbd5e1;">${fmtRupee(view.total_earnings)}</td>
                            <td style="padding: 12px 15px; border-right: 1px solid #cbd5e1;">Total Deductions:</td>
                            <td style="padding: 12px 15px; text-align: right; color: #ef4444;">${fmtRupee(view.total_deductions)}</td>
                        </tr>
                    </table>
                </div>
                <table style="width: 100%; margin-top: 30px; border: 1px solid #e2e8f0; border-radius: 8px; background: #f8fafc; border-collapse: separate; border-spacing: 0;">
                    <tr>
                        <td style="padding: 20px 25px; vertical-align: middle;">
                            <div style="font-size: 13px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">Net Salary Payable</div>
                            <div style="font-size: 14px; font-weight: 600; color: #475569; margin-top: 4px; font-style: italic;">In Words: ${numberToWords(Math.round(view.net_pay))} Rupees Only</div>
                        </td>
                        <td style="padding: 20px 25px; text-align: right; vertical-align: middle; width: 30%;">
                            <span style="font-size: 26px; font-weight: 800; color: #0f172a;">${fmtRupee(view.net_pay)}</span>
                        </td>
                    </tr>
                </table>
                <div style="margin-top: 40px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #f1f5f9; padding-top: 20px;">
                    This is a computer-generated document and does not require a physical signature.
                </div>
            </div>
        `;
    }

    function exportPayslipPdf(r, shouldSave = true) {
        // Construct the template from scratch
        const htmlContent = generatePayslipHtml(r);
        
        const tempContainer = document.createElement('div');
        tempContainer.innerHTML = htmlContent;
        
        // Wrap in a zero-height container to hide it from view using fixed positioning
        // This prevents window scrolling from introducing vertical offset blanks in html2canvas
        const wrapper = document.createElement('div');
        wrapper.style.height = '0';
        wrapper.style.overflow = 'hidden';
        wrapper.style.position = 'fixed';
        wrapper.style.top = '0';
        wrapper.style.left = '0';
        wrapper.style.zIndex = '-9999';
        wrapper.appendChild(tempContainer);
        document.body.appendChild(wrapper);
        
        const opt = {
            margin: 10,
            filename: `Payslip_${r.employee_name.replace(/\s+/g, '_')}_${r.month_year.replace(/\s+/g, '_')}.pdf`,
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { 
                scale: 2, 
                useCORS: true,
                backgroundColor: '#ffffff',
                logging: false,
                scrollY: 0,
                scrollX: 0
            },
            jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
        };
        
        if (shouldSave) {
            return html2pdf().set(opt).from(tempContainer.firstElementChild).save().then(() => {
                document.body.removeChild(wrapper);
            }).catch(err => {
                console.error(err);
                document.body.removeChild(wrapper);
            });
        } else {
            return html2pdf().set(opt).from(tempContainer.firstElementChild).output('blob').then(blob => {
                document.body.removeChild(wrapper);
                return blob;
            }).catch(err => {
                console.error(err);
                document.body.removeChild(wrapper);
                throw err;
            });
        }
    }

    async function downloadRowPDF(id) {
        $.ajax({
            url: 'action.php',
            method: 'GET',
            data: { fetch_payslip_data: 1, id: id },
            dataType: 'json',
            success: function(res) {
                if (res && res.id) {
                    exportPayslipPdf(res, true);
                } else {
                    Swal.fire('Error', 'Payslip not found.', 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'Failed to load payslip for download.', 'error');
            }
        });
    }

    async function downloadAllPayslips() {
        if (typeof html2pdf === 'undefined' || typeof JSZip === 'undefined') {
            Swal.fire('Loading', 'Please wait for components to initialize...', 'info');
            return;
        }

        const isMobile = window.innerWidth <= 768;
        const month = isMobile ? $('#monthSelectMobile').val() : $('#monthSelect').val();
        if (!month) {
            Swal.fire('Tip', 'Select a period from filters first.', 'warning');
            return;
        }
        
        Swal.fire({
            title: 'Bulk Export',
            text: `Gathering records for ${month}...`,
            allowOutsideClick: false,
            didOpen: () => { Swal.showLoading(); }
        });

        try {
            const response = await fetch(`action.php?fetch_payroll=1&month=${encodeURIComponent(month)}`);
            const employees = await response.json();

            if (!employees || employees.length === 0) {
                Swal.fire('No Data', 'No processed records for this period.', 'error');
                return;
            }

            const zip = new JSZip();
            
            for (let i = 0; i < employees.length; i++) {
                const emp = employees[i];
                Swal.update({ text: `Processing: ${emp.employee_name} (${i + 1}/${employees.length})` });
                
                const pdfBlob = await exportPayslipPdf(emp, false);
                zip.file(`${emp.employee_name}_${month.replace(' ', '_')}.pdf`, pdfBlob);
            }

            Swal.update({ title: 'Compressing...', text: 'Building package...' });
            const zipContent = await zip.generateAsync({ type: "blob" });
            const link = document.createElement("a");
            link.href = URL.createObjectURL(zipContent);
            link.download = `Payslips_Batch_${month.replace(' ', '_')}.zip`;
            link.click();
            Swal.fire('Exported', `Successfully generated ${employees.length} payslips.`, 'success');
        } catch (error) {
            console.error(error);
            Swal.fire('Error', 'Failed to generate bulk package.', 'error');
        }
    }

    function numberToWords(num) {
        const a = ['','One ','Two ','Three ','Four ', 'Five ','Six ','Seven ','Eight ','Nine ','Ten ','Eleven ','Twelve ','Thirteen ','Fourteen ','Fifteen ','Sixteen ','Seventeen ','Eighteen ','Nineteen '];
        const b = ['', '', 'Twenty','Thirty','Forty','Fifty', 'Sixty','Seventy','Eighty','Ninety'];
        if ((num = num.toString()).length > 9) return 'overflow';
        let n = ('000000000' + num).substr(-9).match(/^(\d{2})(\d{2})(\d{2})(\d{1})(\d{2})$/);
        if (!n) return ''; 
        let str = '';
        str += (n[1] != 0) ? (a[Number(n[1])] || b[n[1][0]] + ' ' + a[n[1][1]]) + 'Crore ' : '';
        str += (n[2] != 0) ? (a[Number(n[2])] || b[n[2][0]] + ' ' + a[n[2][1]]) + 'Lakh ' : '';
        str += (n[3] != 0) ? (a[Number(n[3])] || b[n[3][0]] + ' ' + a[n[3][1]]) + 'Thousand ' : '';
        str += (n[4] != 0) ? (a[Number(n[4])] || b[n[4][0]] + ' ' + a[n[4][1]]) + 'Hundred ' : '';
        str += (n[5] != 0) ? ((str != '') ? 'and ' : '') + (a[Number(n[5])] || b[n[5][0]] + ' ' + a[n[5][1]]) : '';
        return str.trim();
    }

    (function() {
        const load = (src) => {
            const s = document.createElement('script');
            s.src = src;
            document.head.appendChild(s);
        };
        if (!window.html2pdf) load('https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js');
        if (!window.JSZip) load('https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js');
        if (typeof Swal === 'undefined') load('https://cdn.jsdelivr.net/npm/sweetalert2@11');
    })();
</script>

<?php include('htmlclose.php'); ?>