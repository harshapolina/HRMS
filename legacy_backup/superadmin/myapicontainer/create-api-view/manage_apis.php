<?php include('../htmlopen.php'); ?>
<!-- Include CSS -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />
<link href="https://cdnjs.cloudflare.com/ajax/libs/select2-bootstrap-5-theme/1.3.0/select2-bootstrap-5-theme.min.css"
    rel="stylesheet" />
<!-- Include Font -->
<link href="https://fonts.googleapis.com/css2?family=Lexend+Deca:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<!-- Include JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
<style>
    #responseMessage {
        padding: 10px;
        margin-bottom: 10px;
        border: 1px solid transparent;
        border-radius: 5px;
        display: none;
    }

    #responseMessage.success {
        background-color: #d4edda;
        color: #155724;
        border-color: #c3e6cb;
    }

    #responseMessage.error {
        background-color: #f8d7da;
        color: #721c24;
        border-color: #f5c6cb;
    }

    /* Assigned user badge */
    .assigned-user {
        display: inline-flex;
        align-items: center;
        background: #eef2ff;
        border: 1px solid #c7d2fe;
        border-radius: 16px;
        padding: 4px 10px;
        font-size: 13px;
        font-weight: 500;
        color: #1e3a8a;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
    }

    .assigned-user:hover {
        background: #f1f5f9 !important;
        transform: translateY(-1px) !important;
    }

    /* Optional avatar before username */
    .assigned-user .avatar {
        width: 20px !important;
        height: 20px !important;
        border-radius: 50% !important;
        background-color: #e5e7eb !important;
        margin-right: 6px !important;
        display: inline-block !important;
    }

    /* Remove button */
    .removeUserBtn {
        background: transparent;
        border: none;
        border-radius: 50%;
        width: 18px;
        height: 18px;
        font-size: 14px;
        color: #1e3a8a;
        margin-left: 6px;
        cursor: pointer;
        line-height: 1;
    }

    .removeUserBtn:hover {
        background: #fecaca;
        color: #b91c1c;
    }

    /* Form labels */
    #editApiForm label {
        color: #0d6efd !important;
        font-weight: 600 !important;
        font-size: 14px !important;
    }

    /* Select2 dropdown */
    .select2-container--bootstrap-5 .select2-selection {
        min-height: 42px !important;
        border-radius: 8px !important;
        font-size: 14px !important;
        padding: 6px !important;
    }

    .select2-container--bootstrap-5 .select2-selection--multiple .select2-search {
        height: 100% !important;
    }

    .select2-selection__choice {
        background-color: #0d6efd !important;
        color: white !important;
        border-radius: 4px !important;
        padding: 2px 8px !important;
    }


    .select2-results__options {
        max-height: 200px !important;
        overflow-y: auto !important;
        scrollbar-width: none;
    }

    .select2-container .select2-selection--multiple .select2-selection__rendered {
        max-height: 50px !important;
        overflow-y: scroll !important;
        scrollbar-width: none;
    }

    /* Shared */
    .loginBtn {
        box-sizing: border-box;
        position: relative;
        /* width: 13em;  - apply for fixed size */
        padding: 0 15px 0 46px;
        border: none;
        text-align: left;
        line-height: 0px;
        white-space: nowrap;
        border-radius: 0.5em;
        font-size: 0.9rem;
        color: #FFF;
    }

    .loginBtn:before {
        content: "";
        box-sizing: border-box;
        position: absolute;
        top: 0.2rem;
        left: 0;
        width: 34px;
        height: 100%;
    }

    .loginBtn:focus {
        outline: none;
    }

    .loginBtn:active {
        box-shadow: inset 0 0 0 32px rgba(0, 0, 0, 0.1);
    }

    /* Facebook */
    .loginBtn--facebook {
        height: 100%;
        background-color: #4C69BA;
        background-image: linear-gradient(#4C69BA, #3B55A0);
        /*font-family: "Helvetica neue", Helvetica Neue, Helvetica, Arial, sans-serif;*/
        text-shadow: 0 -1px 0 #354C8C;
    }

    .loginBtn--facebook:before {
        background: url('https://s3-us-west-2.amazonaws.com/s.cdpn.io/14082/icon_facebook.png') 6px 6px no-repeat;
    }

    .loginBtn--facebook:hover,
    .loginBtn--facebook:focus {
        background-color: #5B7BD5;
        background-image: linear-gradient(#5B7BD5, #4864B1);
    }

    /* Checkbox styling */
    .row-checkbox {
        width: 14px;
        height: 14px;
        cursor: pointer;
        accent-color: #3b82f6;
        transition: transform 0.2s ease;
    }

    .row-checkbox:hover {
        transform: scale(1.1);
    }

    /* Highlight selected rows */
    tr:has(.row-checkbox:checked) {
        background-color: rgba(59, 130, 246, 0.1) !important;
    }

    tr:has(.row-checkbox:checked):hover {
        background-color: rgba(59, 130, 246, 0.15) !important;
    }

    /* Table header checkbox alignment */
    #myTable thead th:first-child {
        text-align: center;
        vertical-align: middle;
    }

    #myTable tbody td:first-child {
        text-align: center;
        vertical-align: middle;
    }

    /* Dark Mode Overrides for Select2 */
    body.dark-mode .select2-container--bootstrap-5 .select2-selection {
        background-color: rgba(44, 44, 46, 0.4) !important;
        border: 1px solid rgba(255, 255, 255, 0.1) !important;
        color: #ffffff !important;
    }

    body.dark-mode .select2-container--bootstrap-5 .select2-selection--multiple .select2-search__field {
        color: #ffffff !important;
    }

    /* Selected User Tags - Transparent Background */
    body.dark-mode .select2-container--bootstrap-5 .select2-selection__choice {
        background-color: transparent !important;
        border: 1px solid rgba(255, 255, 255, 0.2) !important;
        color: #ffffff !important;
    }

    body.dark-mode .select2-container--bootstrap-5 .select2-selection__choice__display {
        color: #ffffff !important;
    }

    body.dark-mode .select2-container--bootstrap-5 .select2-selection__choice__remove {
        color: rgba(255, 255, 255, 0.7) !important;
    }

    body.dark-mode .select2-container--bootstrap-5 .select2-selection__choice__remove:hover {
        color: #ffffff !important;
        background-color: rgba(255, 255, 255, 0.1) !important;
    }

    /* Dropdown Options */
    body.dark-mode .select2-dropdown {
        background-color: #1e1e1e !important;
        border: 1px solid rgba(255, 255, 255, 0.1) !important;
        color: #ffffff !important;
    }

    body.dark-mode .select2-container--bootstrap-5 .select2-results__option {
        color: #ffffff !important;
        background-color: transparent !important;
    }

    body.dark-mode .select2-container--bootstrap-5 .select2-results__option--highlighted[aria-selected] {
        background-color: rgba(255, 255, 255, 0.1) !important;
        color: #ffffff !important;
    }

    body.dark-mode .select2-container--bootstrap-5 .select2-results__option[aria-selected="true"] {
        background-color: rgba(59, 130, 246, 0.2) !important;
    }

    /* Table cell padding - High specificity to override unified_table_styles.css */
    #myTable.unified-table tbody td,
    table#myTable tbody td,
    .dt-layout-row-mid #myTable tbody td {
        padding: 14px !important;
    }

    /* User Badge Styling - Matching leads page */
    .user-badge {
        text-transform: lowercase;
        display: inline-block;
        padding: 6px 12px;
        background: #e8e7f8;
        color: #4a4ac6;
        border-radius: 20px;
        font-size: 13px;
        font-weight: 600;
        white-space: nowrap;
        box-shadow: 0 2px 4px rgba(99, 102, 241, 0.2);
        transition: all 0.2s ease;
        max-width: 200px;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .unified-table tbody td,
    .fold-table tbody tr td,
    #myTable tbody td,
    #example tbody td,
    table.stripe tbody td,
    table.display tbody td {
        padding: 10px !important;
    }

    .user-badge.clickable {
        cursor: pointer;
    }

    .user-badge.clickable:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(99, 102, 241, 0.3);
        background: #5558e3;
        color: #fff;
    }

    /* API Source icon alignment */
    .user-cell {
        padding: 8px !important;
        max-width: 220px;
        overflow: hidden;
    }

    /* Center align API Source column header and cells */
    #myTable thead th:nth-child(5),
    .api-source-cell {
        text-align: center;
        vertical-align: middle;
        padding: 8px !important;
    }

    /* Center align Leads Count column */
    #myTable thead th:nth-child(6),
    #myTable tbody td:nth-child(6) {
        text-align: center;
    }

    /* Center align API Key and Actions headers */
    #myTable thead th:nth-child(3),
    #myTable thead th:nth-child(7) {
        text-align: center;
    }

    /* Chevron icon styling */
    .chevron-cell {
        text-align: center;
        vertical-align: middle;
        cursor: pointer;
        padding: 8px !important;
    }

    .chevron-icon {
        width: 25px;
        height: 25px;
        background: #000;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
    }

    .chevron-icon:hover {
        background: #333;
        transform: scale(1.1);
    }

    .chevron-icon i {
        color: white;
        font-size: 14px;
        transition: transform 0.3s ease;
    }

    .row-expanded .chevron-icon {
        background: #000000;
    }

    .row-expanded .chevron-icon:hover {
        background: #000000;
    }

    /* Expanded row styling */
    .expanded-row {
        background: #f8f9fa !important;
    }

    .expanded-row td {
        padding: 0 !important;
    }

    .expanded-content {
        padding: 20px;
        animation: slideDown 0.3s ease;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .expanded-header {
        text-align: left;
        font-size: 16px;
        font-weight: 600;
        color: #1f2937;
        padding-bottom: 5px;
    }

    .expanded-body {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }

    .detail-grid {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 5px;
    }

    .detail-item {
        display: flex;
        flex-direction: row;
        align-items: center;
        gap: 10px;
    }

    .detail-label {
        font-size: 12px;
        font-weight: 600;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .detail-value {
        font-size: 14px;
        color: #1f2937;
        font-weight: 500;
        word-break: break-word;
    }

    /* Two-column layout for screens larger than 1024px */
    @media (min-width: 1024px) {
        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            grid-auto-flow: row;
            gap: 12px 30px;
            align-items: start;
        }

        .detail-item {
            display: flex;
            flex-direction: row;
            align-items: center;
            gap: 10px;
        }
    }

    /* Expanded actions section */
    .expanded-actions {
        padding-top: 5px;
        border-top: 1px solid #e5e7eb;
    }

    .action-header {
        text-align: left;
        font-size: 14px;
        font-weight: 600;
        color: #1f2937;
        margin-bottom: 5px;
    }

    .action-buttons {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .action-btn {
        padding: 8px 16px;
        border: none;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .action-btn i {
        font-size: 14px;
    }

    .edit-btn {
        background: #10b981;
        color: white;
    }

    .edit-btn:hover {
        background: #059669;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(16, 185, 129, 0.3);
    }

    .delete-btn {
        background: #ef4444;
        color: white;
    }

    .delete-btn:hover {
        background: #dc2626;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(239, 68, 68, 0.3);
    }

    .curl-btn {
        background: #6366f1;
        color: white;
    }

    .curl-btn:hover {
        background: #5558e3;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(99, 102, 241, 0.3);
    }

    .report-btn {
        background: #14b8a6;
        color: white;
    }

    .report-btn:hover {
        background: #0d9488;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(20, 184, 166, 0.3);
    }

    .dt-layout-header {
        overflow-y: unset !important;
    }

    /* Responsive: Hide Assigned Users, API Source, and Leads Count columns on mobile */
    @media (max-width: 767px) {

        /* Hide Assigned Users column (4th column) */
        #myTable thead th:nth-child(4),
        #myTable tbody td:nth-child(4),
        /* Hide API Source column (5th column) */
        #myTable thead th:nth-child(5),
        #myTable tbody td:nth-child(5),
        /* Hide Leads Count column (6th column) */
        #myTable thead th:nth-child(6),
        #myTable tbody td:nth-child(6) {
            display: none !important;
        }
    }

    @media (max-width: 670px) {

        .pagination,
        .nested-pagination {
            flex-direction: row !important;
            gap: 0.6rem !important;
            padding: 0.75rem !important;
        }
    }

    /* Responsive: Optimize expanded row for small mobile screens */
    @media (max-width: 468px) {
        .expanded-content {
            padding: 12px;
        }

        .action-buttons {
            display: flex;
            gap: 0.3rem;
            flex-wrap: nowrap;
        }

        .expanded-header {
            font-size: 14px;
            padding-bottom: 4px;
        }

        .detail-item {
            gap: 6px;
            flex-wrap: wrap;
        }

        .detail-label {
            font-size: 10px;
        }

        .detail-value {
            font-size: 11px;
            word-break: break-word;
            overflow-wrap: break-word;
            flex: 1;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            cursor: pointer;
            transition: all 0.2s ease;
            max-width: 200px;
        }

        .detail-value:hover {
            color: #6366f1;
        }

        .detail-value.expanded {
            white-space: normal;
            overflow: visible;
        }

        .action-header {
            font-size: 12px;
            margin-bottom: 4px;
        }

        .action-buttons {
            display: flex !important;
            gap: 6px !important;
            flex-wrap: nowrap !important;
            overflow: auto;
            scrollbar-width: none;
        }

        .action-btn {
            padding: 6px 10px;
            font-size: 11px;
            gap: 4px;
            justify-content: center;
            white-space: nowrap;
        }

        .action-btn i {
            font-size: 12px;
        }

        /* Stack search bar and toolbar in separate rows */
        .dt-layout-row-top {
            flex-direction: column !important;
            gap: 10px !important;
        }

        .leads-controls-row,
        .dt-layout-row-top>div {
            flex-direction: column !important;
            width: 100% !important;
        }

        .leads-search-box {
            width: 100% !important;
            margin-bottom: 8px !important;
        }

        .leads-toolbar-controls {
            width: 100% !important;
            justify-content: start !important;
        }

        .leads-controls-row {
            gap: 1px !important;
        }

        .leads-search-box i {
            top: 20% !important;
        }

        .leads-toolbar-controls {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: nowrap;
        }
    }

    /* ========================================= */
    /* FILTER MODAL STYLING - Based on Leads Page */
    /* ========================================= */

    /* Form-item styling for fieldset-style labels */
    .form-item {
        margin-bottom: 0.3rem;
        position: relative !important;
    }

    .form-item input,
    .form-item select {
        display: block !important;
        background: transparent !important;
        transition: .3s !important;
        padding: 0 15px !important;
        border-radius: 5px !important;
        width: 100% !important;
        font-size: 14px !important;
        font-weight: 500 !important;
        color: #0000009e !important;
        height: 44px !important;
        border: 1px solid #dee2e6 !important;
    }

    .form-item label {
        position: absolute !important;
        cursor: text !important;
        z-index: 2000 !important;
        left: 10px !important;
        font-weight: 600 !important;
        background: #fff !important;
        padding: 0 10px !important;
        transition: .3s !important;
        font-size: 11px !important;
        top: -8px !important;
        color: #000000 !important;
    }

    .form-item input:focus,
    .form-item select:focus {
        border-color: #1b6c9f !important;
        outline: none !important;
        box-shadow: none !important;
    }

    .form-item input::placeholder,
    .form-item select::placeholder {
        color: #999 !important;
        font-weight: normal !important;
    }

    /* Modal fixes */
    #apiFilterModal {
        z-index: 1050 !important;
    }

    #apiFilterModal .modal-dialog {
        z-index: 1051 !important;
        max-width: 500px;
        overflow: visible !important;
        margin: 3rem auto !important;
    }

    #apiFilterModal .modal-content {
        z-index: 1052 !important;
        background-color: #fff !important;
        overflow: visible !important;
        border-radius: 15px;
        margin: 10px;
    }

    #apiFilterModal .modal-body {
        max-height: 70vh;
        overflow-y: auto;
        overflow-x: visible;
        padding: 20px;
        position: relative;
    }

    #apiFilterModal .modal-header {
        background: white;
        color: #000000;
        border-bottom: none;
        padding: 20px;
        border-top-left-radius: 15px;
        border-top-right-radius: 15px;

    }

    #apiFilterModal .modal-header .btn-close {
        color: #000000;
    }

    #apiFilterModal .modal-footer {
        border-top: 1px solid #e9ecef;
        padding: 15px;
    }

    .modal-backdrop {
        z-index: 1040 !important;
    }

    .modal-backdrop.show {
        opacity: 0.5 !important;
    }

    /* Fix for date picker overflow */
    #apiFilterModal input[type="date"]::-webkit-calendar-picker-indicator {
        cursor: pointer;
    }

    #apiFilterModal input[type="date"] {
        position: relative;
        z-index: 1;
    }

    /* Ensure select dropdowns stay within bounds */
    #apiFilterModal select {
        position: relative;
        z-index: 1;
    }

    #apiFilterModal .form-select {
        overflow: visible !important;
    }

    /* Additional spacing for form items to accommodate dropdowns */
    #apiFilterModal .form-item {
        margin-bottom: 1.5rem !important;
        position: relative;
        z-index: 1;
    }

    /* Ensure proper spacing at the bottom of modal body */
    #apiFilterModal .modal-body .row:last-child {
        margin-bottom: 0;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        #apiFilterModal .modal-dialog {
            margin: 1rem auto !important;
        }
    }

    /* ========================================= */
    /* MODERN LAYOUT STYLING - Leads Page Style */
/*group manager CSS START*/
/* Base styles with overflow prevention */
#groupManagerModal{--gm-bg:#fbfdff;--gm-surface:#ffffff;--gm-muted:#74808a;--gm-primary:#1767b2;--gm-accent:#ffb400;--gm-success:#1e8e3e;--gm-danger:#d23f3f;font-family:'Lexend Deca', sans-serif !important;color:#15303b;overflow-x:hidden}#groupManagerModal .modal-dialog{overflow-x:hidden}#groupManagerModal .gm--card{border-radius:12px;overflow:visible;box-shadow:0 12px 40px rgba(20,40,60,.08);border:none}#groupManagerModal .gm--header{background:linear-gradient(180deg,#fff,#fbfcff);padding:18px 22px;border-bottom:1px solid rgba(18,40,56,.06);display:flex;align-items:center;justify-content:space-between;overflow:hidden}#groupManagerModal .gm--title{margin:0;font-weight:700;font-size:1.15rem;color:#0f2a3a;letter-spacing:-.2px}#groupManagerModal .gm--close{background:0 0;border:none;color:#56707b;font-size:18px;opacity:.9}#groupManagerModal .gm--body{background:var(--gm-bg);padding:18px;overflow-x:hidden;width:100%;box-sizing:border-box}#groupManagerModal .gm--grid{display:grid;grid-template-columns:300px 1fr 240px;gap:20px;align-items:start;width:100%;box-sizing:border-box}#groupManagerModal .gm--pane{background:var(--gm-surface);border-radius:10px;padding:14px;border:1px solid rgba(16,38,56,.04);box-shadow:0 6px 18px rgba(16,38,56,.02);overflow:hidden;box-sizing:border-box}#groupManagerModal .gm--left{display:flex;flex-direction:column;gap:12px;min-height:420px;background:rgba(23,103,178,0.04);border-right:1px solid rgba(16,38,56,0.02)}#groupManagerModal .gm--right{display:flex;flex-direction:column;gap:10px;min-height:420px}#groupManagerModal .gm--sidebar{display:flex;flex-direction:column;gap:15px;min-height:420px}#groupManagerModal .gm--sidebar-label{font-weight:700;font-size:0.75rem;text-transform:uppercase;color:var(--gm-muted);letter-spacing:0.5px;margin-bottom:5px}#groupManagerModal .gm--sidebar .btn{text-align:left;padding:12px 15px;line-height:1.4;white-space:normal;font-weight:600;display:flex;align-items:center;height:auto;border-radius:10px;transition:all 0.2s ease}#groupManagerModal .gm--sidebar .btn:hover:not(:disabled){transform:translateY(-2px);box-shadow:0 4px 12px rgba(0,0,0,0.05)}#groupManagerModal .gm--search-row,#groupManagerModal .gm--search-rows{display:flex;gap:8px;align-items:center}#groupManagerModal .gm--search-input{flex:1;padding:10px 12px;border-radius:10px;border:1px solid rgba(16,38,56,.06);background:linear-gradient(180deg,#fff,#fbfdff);font-size:.95rem;color:#0d2a36;outline:0;transition:box-shadow .12s,border-color .12s,transform .05s;max-width:100%;box-sizing:border-box}#groupManagerModal .gm--search-input:focus{border-color:rgba(23,103,178,.18);box-shadow:0 6px 22px rgba(23,103,178,.06);transform:translateY(-1px)}#groupManagerModal .gm--icon-btn{min-width:40px;min-height:40px;display:inline-grid;place-items:center;border-radius:10px;border:1px solid rgba(16,38,56,.04);background:#fff;cursor:pointer;color:#3c5563;font-weight:700;flex-shrink:0}#groupManagerModal .gm--icon-btn:hover{background:#f3f8ff;transform:translateY(-1px)}#groupManagerModal .gm--list-wrap{flex:1;overflow:auto;border-radius:10px;border:1px solid rgba(16,38,56,.03);background:linear-gradient(180deg,#fff,#fbfdff);padding:8px}#groupManagerModal .gm--groups-list{width:100%;height:100%;border:none;padding:6px;font-size:.96rem;color:#173240;background:0 0;outline:0;-webkit-appearance:none}#groupManagerModal .gm--groups-list option{padding:8px;font-weight:600}#groupManagerModal .gm--controls{display:flex;gap:10px;align-items:center;justify-content:space-between;flex-wrap:wrap}#groupManagerModal .gm--controls .btn{border-radius:8px;padding:8px 12px;font-weight:600}#groupManagerModal .gm--details-head{margin-bottom:4px}#groupManagerModal .gm--group-title{margin:0;font-size:1.05rem;font-weight:700;color:#0c2e3a;overflow:hidden;text-overflow:ellipsis}#groupManagerModal .gm--small-meta{color:var(--gm-muted);font-size:.88rem}#groupManagerModal .gm--assign-row{display:flex;gap:10px;align-items:center}#groupManagerModal .gm--assign-row .select2-container--bootstrap-5 .select2-selection--multiple{border-radius:8px;min-height:48px;padding:6px 8px;border:1px solid rgba(16,38,56,.06);background:#fff;box-shadow:none}#groupManagerModal .gm--assign-row .select2-container--bootstrap-5 .select2-selection__choice{background:#1976d2;border:none;color:#fff;font-weight:600;margin:4px 6px 4px 0;border-radius:6px}#groupManagerModal .gm--hint{color:var(--gm-muted);font-size:.86rem}#groupManagerModal .gm--rows-container{max-height:260px;overflow-y:auto;overflow-x:hidden;border-radius:10px;border:1px solid rgba(16,38,56,.03);background:linear-gradient(180deg,#fff,#fbfdff);box-shadow:inset 0 1px 0 rgba(255,255,255,.6);box-sizing:border-box}#groupManagerModal .gm--rows-container .form-check{display:flex;gap:12px;align-items:flex-start;border-radius:8px;transition:background .08s,transform .06s;margin-bottom:8px;border:1px solid rgba(16,38,56,.02);overflow:hidden}#groupManagerModal .gm--rows-container .form-check:hover{background:rgba(14,56,112,.03);transform:translateY(-2px)}#groupManagerModal .gm--rows-container .form-check input[type=checkbox]{margin-top:6px;width:18px;height:18px;flex-shrink:0}#groupManagerModal .gm--rows-container label{font-weight:600;color:#132b34;font-size:.95rem;overflow-wrap:break-word;word-break:break-word}#groupManagerModal .gm--rows-actions{display:flex;gap:12px;margin-top:10px;flex-wrap:wrap}#groupManagerModal .gm--rows-actions .btn{padding:10px 14px;border-radius:8px;font-weight:700;box-shadow:0 6px 18px rgba(22,103,178,.06)}#groupManagerModal .gm--rows-actions .btn-warning{background:var(--gm-accent);color:#15303b;border:none}#groupManagerModal .gm--footer-actions{display:flex;gap:12px;justify-content:flex-end;margin-top:14px;align-items:center}#groupManagerModal .modal-dialog{max-width:1300px;margin:30px auto}#groupManagerModal .modal-content{overflow:hidden}#groupManagerModal .gm--list-wrap::-webkit-scrollbar,#groupManagerModal .gm--rows-container::-webkit-scrollbar{height:10px;width:10px}#groupManagerModal .gm--list-wrap::-webkit-scrollbar-thumb,#groupManagerModal .gm--rows-container::-webkit-scrollbar-thumb{background:rgba(22,103,178,.12);border-radius:8px}#groupManagerModal .gm--list-wrap::-webkit-scrollbar-track,#groupManagerModal .gm--rows-container::-webkit-scrollbar-track{background:0 0}#groupManagerModal .gm--alert{padding:10px 12px;border-radius:8px;background:#fff6e6;border:1px solid rgba(235,150,25,.12);color:#6b3f00;font-weight:600}#groupManagerModal .gm--empty{padding:24px;border-radius:8px;color:var(--gm-muted);font-size:.95rem;text-align:center;border:1px dashed rgba(16,38,56,.04);background:linear-gradient(180deg,#fff,#fbfdff)}#groupManagerModal .gm--search-rows{display:flex;gap:8px;align-items:center;max-width:250px}
@media (max-width:1200px){#groupManagerModal .modal-dialog{max-width:98%;margin:10px auto}#groupManagerModal .gm--grid{grid-template-columns:280px 1fr 220px}}
@media (max-width:1000px){#groupManagerModal .modal-dialog{max-width: 96%; margin: 10px auto}#groupManagerModal .gm--grid{grid-template-columns:250px 1fr;gap:15px}#groupManagerModal .gm--sidebar{grid-column: span 2; flex-direction: row; min-height: auto; flex-wrap: wrap}}
@media (max-width:820px){
    #groupManagerModal .modal-dialog{max-width:96%;margin:8px auto}
    #groupManagerModal .modal-content{overflow-x: hidden; max-width: 100%}
    #groupManagerModal .gm--grid{grid-template-columns:1fr;gap:15px}
    #groupManagerModal .gm--left{order:1; min-height: auto; width: 100%; max-width: 100%; grid-column: 1}
    #groupManagerModal .gm--right{order:2; min-height: auto; width: 100%; max-width: 100%; grid-column: 1}
    #groupManagerModal .gm--sidebar{order:3; min-height: auto; flex-direction: column; width: 100%; max-width: 100%; grid-column: 1}
    #groupManagerModal .gm--rows-container{max-height:300px; overflow-x: auto}
    #groupManagerModal .gm--body{padding: 12px; overflow-x: hidden; max-width: 100%}
    #groupManagerModal .gm--rows-container label{word-break: break-word; overflow-wrap: break-word}
}
@media (max-width:576px){
    #groupManagerModal .modal{overflow-x: hidden !important}
    #groupManagerModal .modal-dialog{max-width: 100%; margin: 0 auto; width: 100vw; padding: 10px;}
    #groupManagerModal .modal-content{margin: 0; border-radius: 0; max-width: 100vw; border: none}
    #groupManagerModal .gm--header{padding: 12px 15px}
    #groupManagerModal .gm--title{font-size: 1rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: calc(100vw - 100px)}
    #groupManagerModal .gm--grid{gap: 10px; grid-template-columns: 1fr}
    #groupManagerModal .gm--pane{padding: 10px; box-sizing: border-box; width: 100%; max-width: 100%}
    #groupManagerModal .gm--left{width: 100%; max-width: 100%; grid-column: 1}
    #groupManagerModal .gm--right{width: 100%; max-width: 100%; grid-column: 1}
    #groupManagerModal .gm--sidebar{width: 100%; max-width: 100%; grid-column: 1}
    #groupManagerModal .gm--body{padding: 10px; overflow-x: hidden; max-width: 100vw; box-sizing: border-box}
    #groupManagerModal .gm--footer-actions{flex-direction: column; width: 100%}
    #groupManagerModal .gm--footer-actions .btn{width: 100%}
    #groupManagerModal .gm--assign-row{flex-direction: column; align-items: stretch}
    #groupManagerModal .gm--sidebar .btn{font-size: 0.85rem; padding: 10px; white-space: normal; word-break: break-word}
    #groupManagerModal .gm--rows-container{overflow-x: auto; box-sizing: border-box}
    #groupManagerModal .gm--rows-container .form-check{overflow: hidden}
    #groupManagerModal .gm--rows-container label{font-size: 0.85rem; word-break: break-word; overflow-wrap: break-word; max-width: 100%}
    #groupManagerModal .gm--rows-container label strong{font-size: 0.9rem; display: block; overflow: hidden; text-overflow: ellipsis}
    #groupManagerModal .gm--rows-container label small{font-size: 0.75rem; display: block; word-break: break-all}
    #groupManagerModal .gm--search-rows{max-width: none; width: 100%; margin-top: 5px}
    #groupManagerModal .gm--search-input{font-size: 0.9rem; max-width: 100%; box-sizing: border-box}
    #groupManagerModal .gm--groups-list{font-size: 0.85rem}
    #groupManagerModal .gm--groups-list option{white-space: normal; word-break: break-word}
    #groupManagerModal .select2-container{max-width: 100% !important; width: 100% !important}
    #groupManagerModal .gm--group-title{font-size: 0.95rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap}
    #groupManagerModal .gm--controls{flex-wrap: wrap}
    #groupManagerModal .gm--controls .btn{flex: 1 1 auto; min-width: 120px}
}
/*group manager CSS End*/

/* Group Manager Dark Mode Start */
body.dark-mode #groupManagerModal {
    --gm-bg: #1a1a1c;
    --gm-surface: #242426;
    --gm-muted: #a1a1aa;
    --gm-primary: #3b82f6;
    color: #e4e4e7;
}

body.dark-mode #groupManagerModal .gm--card {
    background-color: var(--gm-surface);
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.4);
}

body.dark-mode #groupManagerModal .gm--header {
    background: linear-gradient(180deg, #2a2a2c, #242426);
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

body.dark-mode #groupManagerModal .gm--title {
    color: #ffffff;
}

body.dark-mode #groupManagerModal .gm--close {
    color: #a1a1aa;
}

body.dark-mode #groupManagerModal .gm--pane {
    background: #1e1e20;
    border: 1px solid rgba(255, 255, 255, 0.06);
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.2);
}

body.dark-mode #groupManagerModal .gm--search-input,
body.dark-mode #groupManagerModal .gm--row-search-input {
    background: #2a2a2c;
    border: 1px solid rgba(255, 255, 255, 0.1);
    color: #ffffff;
}

body.dark-mode #groupManagerModal .gm--search-input:focus {
    border-color: var(--gm-primary);
    box-shadow: 0 6px 22px rgba(59, 130, 246, 0.15);
}

body.dark-mode #groupManagerModal .gm--icon-btn {
    background: #2a2a2c;
    border: 1px solid rgba(255, 255, 255, 0.1);
    color: #e4e4e7;
}

body.dark-mode #groupManagerModal .gm--icon-btn:hover {
    background: #333336;
}

body.dark-mode #groupManagerModal .gm--list-wrap {
    background: #1a1a1c;
    border: 1px solid rgba(255, 255, 255, 0.06);
}

body.dark-mode #groupManagerModal .gm--groups-list {
    color: #e4e4e7;
}

body.dark-mode #groupManagerModal .gm--groups-list option {
    background-color: #1a1a1c;
}

body.dark-mode #groupManagerModal .gm--groups-list option:checked {
    background-color: #3b424e !important;
    color: #ffffff !important;
}

body.dark-mode #groupManagerModal .gm--group-title {
    color: #ffffff;
}

body.dark-mode #groupManagerModal .gm--rows-container {
    background: #1a1a1c;
    border: 1px solid rgba(255, 255, 255, 0.06);
}

body.dark-mode #groupManagerModal .gm--rows-container .form-check {
    border: 1px solid rgba(255, 255, 255, 0.04);
}

body.dark-mode #groupManagerModal .gm--rows-container .form-check:hover {
    background: rgba(255, 255, 255, 0.03);
}

body.dark-mode #groupManagerModal .gm--rows-container label {
    color: #e4e4e7;
}

body.dark-mode #groupManagerModal .gm--rows-container .text-muted {
    color: #a1a1aa !important;
}

body.dark-mode #groupManagerModal .gm--empty {
    background: #1a1a1c;
    border: 1px solid rgba(255, 255, 255, 0.06);
}

body.dark-mode #groupManagerModal .gm--alert {
    background: rgba(107, 63, 0, 0.2);
    border: 1px solid rgba(235, 150, 25, 0.2);
    color: #ffb400;
}

body.dark-mode #groupManagerModal .gm--footer-actions {
    border-top: 1px solid rgba(255, 255, 255, 0.08);
}

body.dark-mode #groupManagerModal .btn-light {
    background-color: #2a2a2c;
    border-color: rgba(255, 255, 255, 0.1);
    color: #e4e4e7;
}

body.dark-mode #groupManagerModal .btn-light:hover {
    background-color: #333336;
    border-color: rgba(255, 255, 255, 0.2);
}

body.dark-mode #groupManagerModal .gm--search-rows .form-control-sm {
    background-color: #959595 !important;
    border-color: rgba(255, 255, 255, 0.1);
    color: #ffffff;
}

/* Scrollbar dark mode */
body.dark-mode #groupManagerModal .gm--list-wrap::-webkit-scrollbar-thumb,
body.dark-mode #groupManagerModal .gm--rows-container::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.1);
}

body.dark-mode #groupManagerModal .gm--list-wrap::-webkit-scrollbar-thumb:hover,
body.dark-mode #groupManagerModal .gm--rows-container::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.2);
}

body.dark-mode #groupManagerModal .gm--left {
    background: rgba(255, 255, 255, 0.04) !important;
    border-right-color: rgba(255, 255, 255, 0.05) !important;
}

body.dark-mode #groupManagerModal .gm--sidebar .bg-light {
    background-color: #2a2a2c !important;
    border-color: rgba(255, 255, 255, 0.1) !important;
    color: #a1a1aa !important;
}

body.dark-mode #groupManagerModal .gm--sidebar .bg-light p {
    color: #e4e4e7 !important;
}
body.dark-mode .bg-light{
    background-color: #281f1f !important;
    color: #e4e4e7 !important;
}
/* Group Manager Dark Mode End */
    /* ========================================= */

    /* Controls Row Layout */
    .leads-controls-row {
        width: 100%;
        display: flex;
        align-items: center;
        gap: 15px;
    }

    /* Search Box - Left Side */
    .leads-search-box {
        width: 100%;
    }

    .leads-search-box i {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #9ca3af;
        font-size: 14px;
        pointer-events: none;
    }

    .leads-search-box input {
        width: 100%;
        padding: 8px 12px 8px 36px;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        font-size: 13px;
        transition: all 0.2s ease;
        outline: none;
    }

    .leads-search-box input:focus {
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .leads-search-box input::placeholder {
        color: #9ca3af;
    }

    /* Toolbar Controls - Right Side */
    .leads-toolbar-controls {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: nowrap;
    }

    /* Icon-Only Toolbar Buttons */
    .toolbar-icon-btn {
        width: 36px;
        height: 36px;
        padding: 0;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        background: #fff;
        color: #64748b;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s ease;
        font-size: 16px;
        position: relative;
    }

    .toolbar-icon-btn:hover:not(:disabled) {
        background: #f8f9fa;
        border-color: #cbd5e1;
        color: #3b82f6;
        transform: translateY(-1px);
    }

    .toolbar-icon-btn:active:not(:disabled) {
        transform: translateY(0);
    }

    .toolbar-icon-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    /* Row Selector */
    .rowSelector_wrap {
        display: flex;
        align-items: center;
        position: relative;
    }

    .rowSelector_wrap::after {
        content: '\25BE';
        position: absolute;
        right: 8px;
        top: 50%;
        transform: translateY(-50%);
        color: #495057;
        font-size: 20px;
        pointer-events: none;
    }

    .rowSelector_wrap select {
        padding: 6px 28px 6px 10px;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        background: #fff;
        font-size: 13px;
        cursor: pointer;
        outline: none;
        appearance: none;
        -webkit-appearance: none;
        -moz-appearance: none;
        transition: all 0.2s ease;
    }

    .rowSelector_wrap select:hover {
        border-color: #cbd5e1;
        background-color: #f8f9fa;
    }

    .rowSelector_wrap select:focus {
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    /* Column Visibility Dropdown */
    .Visibility_dropdown {
        position: relative;
    }

    .Visibility_dropdown-content {
        position: absolute;
        right: 0;
        top: calc(100% + 8px);
        background: #fff;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
        padding: 12px;
        min-width: 200px;
        max-width: 250px;
        z-index: 1050;
        display: none;
        max-height: 400px;
        overflow-y: auto;
    }

    .Visibility_dropdown-content.show,
    .Visibility_dropdown.active .Visibility_dropdown-content {
        display: block !important;
    }

    /* Column visibility options styling */
    .Visibility_dropdown-content label {
        display: flex;
        align-items: center;
        padding: 6px 8px;
        margin: 0;
        cursor: pointer;
        border-radius: 4px;
        transition: background 0.2s ease;
        font-size: 13px;
        color: #374151;
        white-space: nowrap;
    }

    .Visibility_dropdown-content label:hover {
        background: #f3f4f6;
    }

    .Visibility_dropdown-content input[type="checkbox"] {
        margin-right: 8px;
        cursor: pointer;
        width: 16px;
        height: 16px;
    }

    .dt-layout-row-top-header {
        background: none !important;
        backdrop-filter: none !important;
        margin-bottom: 0rem !important;
        padding: 0rem !important;
        border: 0px solid rgba(0, 0, 0, 0.208) !important;
        border-top: 0px !important;
        border-left: 0px !important;
        border-right: 0px !important;
        padding: 0px 0px !important;
        width: 100% !important;
    }

    .dt-layout-row-top {
        width: auto;
        background: none !important;
        backdrop-filter: none !important;
        border-radius: 0rem !important;
        margin-bottom: 0rem !important;
        padding: 0rem !important;
        border: none !important;
        position: relative !important;
        z-index: 100 !important;
    }

    .dt-layout-foot {
        background: none !important;
        backdrop-filter: none !important;
        border-radius: 0rem !important;
        border: none !important;
    }

    .side-menu li.sideactive70 {
        background: var(--shicol);
        position: relative;
    }

    .side-menu li.sideactive70 a {
        color: white;
    }

    /* Base button styles */
    button.activebutton.accessbtn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background-color: #4CAF50;
        color: white;
        border-color: #1aa54f !important;
        border-radius: 8px;
        padding: 0px 7px;
        font-size: 12px;
        font-weight: bold;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    /* Icon spacing */
    button.activebutton.accessbtn i {
        margin-right: 8px;
        /* Space between icon and text */
        font-size: 0.9rem;
        /* Icon size */
    }

    /* Hover effects */
    button.activebutton.accessbtn:hover {
        background-color: #45a049;
        /* Darker green on hover */
        box-shadow: 0 6px 8px rgba(0, 0, 0, 0.15);
        /* Enhanced shadow */
    }

    /* Active effects */
    button.activebutton.accessbtn:active {
        transform: scale(0.98);
        /* Slightly shrink on click */
        background-color: #3e8e41;
        /* Even darker green */
    }

    /* Focus styles */
    button.activebutton.accessbtn:focus {
        outline: 2px solid #80C1FF;
        /* Light blue outline */
        outline-offset: 2px;
        /* Space between button and outline */
    }

    /* Deactive button styles */
    button.deactivebutton.accessbtn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background-color: #f44336;
        color: white;
        border: none;
        border-radius: 8px;
        padding: 0px 7px;
        font-size: 11px;
        font-weight: bold;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    .modal-title {
        font-size: medium !important;
    }

    /* Hover effects */
    button.deactivebutton.accessbtn:hover {
        background-color: #e53935;
        /* Darker red on hover */
        box-shadow: 0 6px 8px rgba(0, 0, 0, 0.15);
        /* Enhanced shadow */
    }

    /* Active effects */
    button.deactivebutton.accessbtn:active {
        transform: scale(0.98);
        /* Slightly shrink on click */
        background-color: #d32f2f;
        /* Even darker red */
    }

    body.dark-mode .modal-footer {
        border-top: 1px solid transparent !important;
    }

    /* Focus styles */
    button.deactivebutton.accessbtn:focus {
        outline: 2px solid #FFCDD2;
        /* Light red outline */
        outline-offset: 2px;
        /* Space between button and outline */
    }

    button.deactivebutton.accessbtn i {
        margin-right: 8px;
        /* Space between icon and text */
        font-size: 0.9rem;
        /* Icon size */
    }

    .unified-table thead th:last-child,
    .fold-table thead th:last-child,
    #myTable thead th:last-child,
    #example thead th:last-child {
        border-radius: 0 0px 0 0 !important;
    }

    body.dark-mode .modal-content {
        background-color: rgba(30, 30, 30, 0.95) !important
    }

    body.dark-mode .modal-header {
        background-color: rgba(30, 30, 30, 0.95) !important
    }

    body.dark-mode .select2-container--bootstrap-5 .select2-selection {
        background-color: rgba(30, 30, 30, 0.95) !important
    }

    body.dark-mode .select2-container--default .select2-selection--multiple {
        background-color: transparent !important
    }

    body.dark-mode #apiFilterModal .modal-content {
        background: rgba(30, 30, 30, 0.95) !important;
    }

    body.dark-mode .form-item label {
        color: #ffffff !important;
        background: transparent !important;
        backdrop-filter: blur(20px);
    }

    body.dark-mode .Visibility_dropdown-content {
        background: rgba(30, 30, 30, 0.95) !important;
    }

    body.dark-mode .Visibility_dropdown-content label {
        border-bottom: 1px solid #eef2f612;
    }

    body.dark-mode .toolbar-icon-btn {
        color: white;
        background: transparent !important;
        border: 1px solid #dee2e64d;
    }

    body.dark-mode .rowSelector_wrap,
    .leadstatus_wrap {
        background: transparent !important;
    }

    body.dark-mode .unified-table thead,
    .fold-table thead,
    #myTable thead,
    #example thead,
    table.stripe thead,
    table.display thead {
        background: #454545 !important;
    }

    body.dark-mode .assigned-user {
        background: transparent !important;

    }
    
    body.dark-mode .removeUserBtn {
        color: #ffffff;
    }

    body.dark-mode #editApiForm label {
        color: #ffffff !important;
    }

    body.dark-mode .select2-container--bootstrap-5 .select2-dropdown .select2-results__options .select2-results__option {
        background: rgba(30, 30, 30, 0.95) !important;
    }

    body.dark-mode .user-badge {
        background: #e8e7f836 !important;
    }

    body.dark-mode .swal2-popup {
        background: rgba(30, 30, 30, 0.95) !important;
        backdrop-filter: blur(10px) !important;
        border: 1px solid rgba(255, 255, 255, 0.1) !important;
        color: #ffffff !important;
    }

    body.dark-mode .modal-header .btn-close {
        filter: invert(1) grayscale(100%) !important;
    }

    body.dark-mode #jumpButton {
        border-top-right-radius: 5px;
        border-bottom-right-radius: 5px;
        background: #007bff;
        color: white !important;
    }

    body.dark-mode .search-filters,
    body.dark-mode .leads-search-box,
    body.dark-mode .add-button-container {
        border: none !important;
    }

    body.dark-mode .deactivebutton i {
        color: white;
    }

    /* Dark mode fix for Group Manager checkboxes */
    body.dark-mode .gm_rowCheckbox {
        accent-color: #3b82f6 !important;
        border-color: rgba(255, 255, 255, 0.2) !important;
        background-color: rgba(255, 255, 255, 0.05) !important;
    }

    body.dark-mode .gm_rowCheckbox:checked {
        background-color: #272829 !important;
        border-color: #3b82f6 !important;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20'%3e%3cpath fill='none' stroke='%23fff' stroke-linecap='round' stroke-linejoin='round' stroke-width='3' d='m6 10 3 3 6-6'/%3e%3c/svg%3e") !important;
    }

    /* General row-checkbox dark mode visibility */
    body.dark-mode .row-checkbox:checked {
        accent-color: #3b82f6 !important;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20'%3e%3cpath fill='none' stroke='%23fff' stroke-linecap='round' stroke-linejoin='round' stroke-width='3' d='m6 10 3 3 6-6'/%3e%3c/svg%3e") !important;
    }

    @media (min-width: 1400px) {
        .container, .container-lg, .container-md, .container-sm, .container-xl, .container-xxl {
            max-width: 100% !important;
        }
    }
    @media (min-width: 992px) {
        .container, .container-lg, .container-md, .container-sm {
            max-width: 100% !important;
        }
    }
    @media (min-width: 768px) {
        .container, .container-md, .container-sm {
            max-width: 100% !important;
        }
    }
    @media (min-width: 576px) {
        .container, .container-sm {
            max-width: 100% !important;
        }
    }
    @media (max-width: 545px) {
        .container, .container-fluid, .container-lg, .container-md, .container-sm, .container-xl, .container-xxl {
            max-width: 100% !important;
        }
    }
</style>
<link rel="stylesheet" href="../../assets/css/unified_table_styles.css" />
</head>
<?php include('../../header.php'); ?>
<!-- Main Content -->
<div class="content">
    <!-- this is main table structure start -->
    <div class="container">
        <!-- API Creation Popup start-->
        <div class="modal fade" id="apicreationModal" role="dialog"
            aria-labelledby="apicreationModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <form id="createApiForm" method="POST">

                        <div class="modal-header">
                            <h6 class="modal-title">API Creation</h6>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>

                        <div class="modal-body">
                            <div class="container">
                                <div class="row">

                                    <div class="col-md-12 mb-2">
                                        <div class="form-item">
                                            <label for="source_name">Lead Source</label>
                                            <select name="source_name" id="source_name" required=""
                                                class="form-control form-control-lg custom-input">
                                                <option value="">Select Source</option>
                                                <option value="google ads">Google</option>
                                                <option value="magicbricks ads">Magicbricks</option>
                                                <option value="99acres ads">99acres</option>
                                                <option value="housing.com ads">housing.com</option>
                                                <option value="WhatsApp Bot">WhatsApp Bot</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-md-12 mb-2">
                                        <div class="form-item">
                                            <label for="projectname">Project Name:</label>
                                            <input type="text" id="project_name" required name="project_name"
                                                class="form-control form-control-lg custom-input">
                                        </div>
                                    </div>

                                    <div class="col-md-12 mb-2">
                                        <div class="form-item">
                                            <label for="users">Select Users</label>
                                            <select id="create_users" name="users[]" multiple=""
                                                class="form-control form-control-lg custom-input searchable-dropdown">

                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-md-12 mb-2">
                                        <div class="form-item">
                                            <!-- <label for="assign_user">Assign Users (comma-separated IDs)</label> -->
                                            <input type="hidden" id="assign_user" name="assign_user">
                                            <div id="createAssignedUsersContainer" class="d-flex flex-wrap mt-2"></div>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <div class="col-lg-12 text-center">
                                <button type="submit" name="submit" class="btn btn-sm btn-primary">Create API</button>
                                <button type="button" class="btn btn-sm btn-secondary"
                                    data-bs-dismiss="modal">Close</button>
                            </div>
                        </div>

                    </form>
                </div>
            </div>
        </div>
        <!-- API creation PopUp model End -->
        <!-- API EDIT POPUP MODEL START -->
        <div class="modal fade" id="editApiModal" aria-labelledby="editApiModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form id="editApiForm">
                        <div class="modal-header">
                            <h6 class="modal-title">Edit API</h6>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>

                        <div class="modal-body">
                            <input type="hidden" id="editApiId" name="api_id">

                            <label class="fw-bold text-primary">Assigned Users</label>
                            <div id="assignedUsersContainer" class="mb-3"></div>

                            <div class="form-item mt-3">
                                <label for="edit_users" class="form-label fw-bold text-primary">Select New Users</label>
                                <select id="edit_users" class="form-control" multiple style="width:100%">
                                    <!-- Dynamic options -->
                                </select>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="submit" class="btn btn-sm btn-primary">Save Changes</button>
                            <button type="button" class="btn btn-sm btn-secondary"
                                data-bs-dismiss="modal">Close</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <!-- API EDIT POPUP MODEL END -->
        <!-- Modal Structure for Assigned Users Start -->
        <div class="modal fade" id="assignUserModal" role="dialog" aria-labelledby="assignUserModalLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h6 class="modal-title" id="assignUserModalLabel">Assigned Users</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p id="assignUserList"></p>
                    </div>
                </div>
            </div>
        </div>
        <!-- Modal Structure for Assigned Users End -->

        <!-- Filter Modal Start -->
        <div class="modal fade" id="apiFilterModal" aria-labelledby="apiFilterModalLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="apiFilterModalLabel">
                            <i class="bi bi-funnel me-2"></i>Filter APIs
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="apiFilterForm">
                            <div class="row g-3">
                                <!-- Date Range -->
                                <div class="col-md-6">
                                    <div class="form-item">
                                        <label for="filter_date_from">Date From</label>
                                        <input type="date" class="form-control" id="filter_date_from">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-item">
                                        <label for="filter_date_to">Date To</label>
                                        <input type="date" class="form-control" id="filter_date_to">
                                    </div>
                                </div>

                                <!-- Project Name -->
                                <div class="col-12">
                                    <div class="form-item">
                                        <label for="filter_project_name">Project Name</label>
                                        <input type="text" class="form-control" id="filter_project_name"
                                            placeholder="Enter project name">
                                    </div>
                                </div>

                                <!-- API Source -->
                                <div class="col-12">
                                    <div class="form-item">
                                        <label for="filter_api_source">API Source</label>
                                        <select class="form-select" id="filter_api_source">
                                            <option value="">All Sources</option>
                                            <option value="google ads">Google Ads</option>
                                            <option value="facebook ads">Facebook Ads</option>
                                            <option value="magicbricks ads">Magicbricks Ads</option>
                                            <option value="99acres ads">99Acres Ads</option>
                                            <option value="housing.com ads">Housing.com Ads</option>
                                            <option value="WhatsApp Bot">WhatsApp Bot</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Assigned User -->
                                <div class="col-12">
                                    <div class="form-item">
                                        <label for="filter_assigned_user">Assigned User</label>
                                        <input type="text" class="form-control" id="filter_assigned_user"
                                            placeholder="Enter user name">
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" id="clearApiFilter">
                            <i class="bi bi-x-circle me-1"></i>Clear
                        </button>
                        <button type="button" class="btn btn-primary" id="applyApiFilter">
                            <i class="bi bi-check-circle me-1"></i>Apply Filter
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <!-- Filter Modal End -->

        <!-- Assign Users Modal Start -->
        <div class="modal fade" id="assignModal" aria-labelledby="assignModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="assignModalLabel">
                            <i class="bi bi-people me-2"></i>Assign Users to Selected APIs
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="assignUsersForm">
                            <div class="form-item">
                                <label for="bulk-user-select">Select Users to Assign</label>
                                <select class="form-control" id="bulk-user-select" multiple="multiple"
                                    style="width: 100%;">
                                    <!-- Users will be loaded dynamically -->
                                </select>
                                <small class="form-text text-muted">You can select multiple users</small>
                            </div>
                            <div class="mt-3">
                                <p class="text-muted mb-1">
                                    <strong>Selected APIs:</strong> <span id="selected-api-count">0</span>
                                </p>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle me-1"></i>Cancel
                        </button>
                        <button type="button" class="btn btn-primary" id="submitBulkAssign">
                            <i class="bi bi-check-circle me-1"></i>Assign Users
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <!-- Assign Users Modal End -->

        <!-- Assign to Group Modal Start -->
        <div class="modal fade" id="assignGroupModal" aria-labelledby="assignGroupModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="assignGroupModalLabel">
                            <i class="bi bi-grid-3x3-gap-fill me-2"></i>Assign APIs to Group
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="assignGroupForm">
                            <div class="form-item">
                                <label for="bulk-group-select">Select Group</label>
                                <select class="form-control" id="bulk-group-select" style="width: 100%;">
                                    <!-- Groups will be loaded dynamically -->
                                </select>
                            </div>
                            <div class="mt-3">
                                <p class="text-muted mb-1">
                                    <strong>Selected APIs:</strong> <span id="assign-group-api-count">0</span>
                                </p>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="submitBulkGroupAssign">Assign to Group</button>
                    </div>
                </div>
            </div>
        </div>
        <!-- Assign to Group Modal End -->

        <!-- Group Manager Modal Start -->
        <div class="modal fade" id="groupManagerModal" aria-labelledby="groupManagerModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-xl">
                <div class="modal-content gm--card">
                    <div class="gm--header">
                        <div class="d-flex align-items-center gap-3">
                            <h5 class="gm--title"><i class="bi bi-grid-3x3-gap-fill me-2"></i>Group Manager</h5>
                            <span class="badge bg-light text-dark border" id="gmGroupsTotal">0 groups</span>
                        </div>
                        <button type="button" class="gm--close" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i></button>
                    </div>
                    
                    <div class="gm--body">
                        <div class="gm--grid">
                            <!-- Left: Group Selection -->
                            <div class="gm--pane gm--left">
                                <div class="gm--search-row">
                                    <input type="text" id="gmSearchInput" class="gm--search-input" placeholder="Search groups...">
                                    <button class="gm--icon-btn" id="gmCreateGroupBtn" title="New Group"><i class="bi bi-plus-lg"></i></button>
                                </div>
                                <div class="gm--list-wrap">
                                    <select id="gmGroupsList" class="gm--groups-list" size="10">
                                        <!-- Groups loaded here -->
                                    </select>
                                </div>
                                <div class="gm--controls">
                                    <button class="btn btn-outline-secondary btn-sm" id="gmRefreshBtn" title="Refresh List">
                                        <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                                    </button>
                                    <button class="btn btn-outline-danger btn-sm" id="gmDeleteGroupBtn" disabled>
                                        <i class="bi bi-trash me-1"></i>Delete Group
                                    </button>
                                </div>
                            </div>

                            <!-- Middle: Group Details & API Rows -->
                            <div class="gm--pane gm--right d-flex flex-column">
                                <div id="gmDetailPane" class="flex-grow-1 d-flex flex-column">
                                    <div class="gm--details-head mb-3">
                                        <h6 class="gm--group-title" id="gmSelectedName">Selected Group Name</h6>
                                        <span class="gm--small-meta">Manage users and API associations</span>
                                    </div>

                                    <div class="mb-3 gm--assign-row">
                                        <label class="form-label fw-bold small text-uppercase text-muted">Assigned Users</label>
                                        <select id="gmAssignUsers" class="form-control" multiple="multiple" style="width: 100%;">
                                            <!-- Users loaded here -->
                                        </select>
                                    </div>

                                    <div class="flex-grow-1 d-flex flex-column" style="min-height: 0;">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <label class="form-label fw-bold small text-uppercase text-muted m-0">Associated API Rows</label>
                                            <div class="gm--search-rows">
                                                <input type="text" id="gmRowSearch" class="form-control form-control-sm" placeholder="Filter rows...">
                                            </div>
                                        </div>
                                        <div class="gm--rows-container p-2" id="gmRowsList">
                                            <!-- Rows loaded here -->
                                        </div>
                                    </div>

                                    <div id="gmSaveNotice" class="gm--alert mt-3" style="display: none;">
                                        <i class="bi bi-info-circle me-2"></i>You have unsaved changes
                                    </div>

                                    <div class="gm--footer-actions mt-auto pt-3 border-top">
                                        <button class="btn btn-primary" id="gmSaveGroupBtn">
                                            <i class="bi bi-check2-circle me-1"></i>Save Group Changes
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Right: Actions Sidebar -->
                            <div class="gm--pane gm--sidebar d-flex flex-column gap-2">
                                <h6 class="gm--sidebar-label">Action Center</h6>
                                <button class="btn btn-light border" id="gmAddCheckedRows" disabled>
                                    <i class="bi bi-plus-circle me-2 text-primary"></i>
                                    Add Selected API Rows to Group
                                </button>
                                <button class="btn btn-light border" id="gmRemoveCheckedRows" disabled>
                                    <i class="bi bi-dash-circle me-2 text-danger"></i>
                                    Remove Selected APIs from Group
                                </button>
                                
                                <div class="mt-auto p-3 bg-light rounded-3 border">
                                    <small class="text-muted d-block mb-1"><i class="bi bi-info-circle me-1"></i> Quick Tip</small>
                                    <p class="small m-0">Select multiple rows from the list to perform bulk actions.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Group Manager Modal End -->

        <div class="row">
            <div id="responseMessage"></div>
            <div id="uploadMessage" class="alert" style="display: none;"></div>
            <div class="dt-layout-row-wrapper" style="padding: 0px 5px!important;">
                <div class="col-lg-12">
                    <div class="dt-layout-row-top-header">
                        <div class="dt-layout-header">
                            <a
                                href="https://www.facebook.com/v15.0/dialog/oauth?client_id=1994857044364536&redirect_uri=https://www.searchhomesindia.in/superadmin/myapicontainer/facebook/login&scope=pages_manage_ads,pages_read_engagement,leads_retrieval">
                                <button class="loginBtn loginBtn--facebook">
                                    Login with Facebook
                                </button>
                            </a>
                            <button class="activebutton accessbtn" onclick="fetchLeads()"><i
                                    class="bi bi-arrow-repeat"></i> Sync Leads</button>
                            <button class="deactivebutton accessbtn" onclick="refreshTokens()"><i
                                    class="bi bi-arrow-clockwise"></i> Refresh Tokens</button>
                            <!-- Group Manager Button -->
                            <button type="button" class="deactivebutton accessbtn" id="openGroupManagerBtn"
                                title="Manage Groups"
                                style="background-color: #1a73e8; color: white; border: none; border-radius: 8px; padding: 0px 12px; font-size: 11px; font-weight: bold; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); display: inline-flex; align-items: center; justify-content: center; gap: 6px;">
                                <i class="bi bi-grid-3x3-gap-fill" style="font-size: 1rem;"></i>
                                Manage Groups
                            </button>
                        </div>
                    </div>
                    <div class="dt-layout-row-top mt-2">
                        <div class="leads-controls-row">
                            <!-- Search Box - Left Side -->
                            <div class="leads-search-box">
                                <i class="bi bi-search"></i>
                                <input type="text" id="searchInput" placeholder="Search...">
                            </div>

                            <div class="leads-toolbar-controls">
                                <button class="toolbar-icon-btn" data-bs-toggle="modal"
                                    data-bs-target="#apicreationModal" title="API Creation">
                                    <i class="bi bi-braces"></i>
                                </button>
                                <button class="toolbar-icon-btn" id="assign-button" data-bs-toggle="modal"
                                    data-bs-target="#assignModal" disabled title="Assign Users">
                                    <i class="bi bi-people"></i>
                                </button>
                                <button class="toolbar-icon-btn" id="delete-selected-btn" title="Delete Selected">
                                    <i class="bi bi-trash"></i>
                                </button>
                                <button class="toolbar-icon-btn" data-bs-toggle="modal" data-bs-target="#apiFilterModal"
                                    title="Filter">
                                    <i class="bi bi-filter"></i>
                                </button>
                                <button class="toolbar-icon-btn Visibility_dropdown" title="Column Visibility">
                                    <i class="bi bi-layout-three-columns"></i>
                                    <div class="Visibility_dropdown-content" id="columnSelector"></div>
                                </button>
                                <div class="rowSelector_wrap">
                                    <select id="rowSelector">
                                        <option value="10">10</option>
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
                <div class="col-lg-12">
                    <div class="dt-layout-row-mid">
                        <div class="scrollable-table">
                            <table id="myTable">
                                <thead>
                                    <tr>
                                        <th style="width: 40px;">
                                            <input type="checkbox" id="select-all" class="row-checkbox">
                                        </th>
                                        <th>Project Name</th>
                                        <th>API Key</th>
                                        <th>Assigned Users</th>
                                        <th>API Source</th>
                                        <th>Leads Count</th>
                                        <th>Actions</th>
                                        <th>Create At</th>
                                        <th style="width: 50px;"></th>
                                    </tr>
                                </thead>
                                <tbody id="uploaddata">

                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-lg-12 mt-2">
                    <div class="dt-layout-foot">
                        <!-- showing page nos  -->
                        <div id="rowInfo"></div>
                        <!-- pagination div  -->
                        <div class="pagination" id="pagination">
                            <button id="prevButton" disabled><i class="bi bi-arrow-left"></i></button>
                            <span id="pageNumbers"></span>
                            <button id="nextButton" disabled><i class="bi bi-arrow-right"></i></button>
                        </div>
                        <!-- jump on page no div  -->
                        <div id="jumpToPage" class="search">
                            <input type="number" id="jumpToPageInput" class="searchTerm" placeholder="Page No."
                                min="1" />
                            <button id="jumpButton" class="searchButton">Jump</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Floating Clear Filters Button -->
<button id="clearFiltersBtn" class="floating-clear-filters" style="display: none;">
    <i class="bi bi-x-circle"></i>
    Clear Filters
</button>

<style>
    .floating-clear-filters {
        position: fixed;
        bottom: 30px;
        right: 30px;
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        color: white;
        border: none;
        padding: 14px 28px;
        border-radius: 50px;
        font-size: 15px;
        font-weight: 600;
        box-shadow: 0 8px 20px rgba(239, 68, 68, 0.4);
        cursor: pointer;
        z-index: 1000;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: all 0.3s ease;
    }

    @media(max-width: 768px) {
        .floating-clear-filters {
            padding: 5px 12px;
            font-size: 12px;
        }
    }

    .floating-clear-filters:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 28px rgba(239, 68, 68, 0.5);
    }

    .floating-clear-filters:active {
        transform: translateY(0);
    }

    .floating-clear-filters i {
        font-size: 18px;
    }
</style>

<script>
    document.getElementById('source_name').addEventListener('change', function () {
        const fbFormField = document.getElementById('fb_form_id_container');
        if (this.value === 'facebook ads') {
            fbFormField.style.display = 'block'; // Show Facebook Form ID field
        } else {
            fbFormField.style.display = 'none'; // Hide Facebook Form ID field
        }
    });
</script>
<script>
    function fetchLeads() {
        fetch('https://www.searchhomesindia.in/superadmin/myapicontainer/facebook/fetch_leads')
            .then(response => response.text()) // Or .json() if it's JSON
            .then(data => {
                Swal.fire({
                    toast: true,
                    position: 'bottom',
                    icon: 'success',
                    title: 'Leads Synced Successfully',
                    text: data,
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true,
                    customClass: {
                        popup: 'custom-toast-popup'
                    }
                });
                console.log('Response:', data);
            })
            .catch(error => {
                console.error('Error syncing leads:', error);
                Swal.fire({
                    toast: true,
                    position: 'bottom',
                    icon: 'error',
                    title: 'Failed to sync leads. Check the console for details.',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true,
                    customClass: {
                        popup: 'custom-toast-popup'
                    }
                });
            });
    }

    function refreshTokens() {
        fetch('https://www.searchhomesindia.in/superadmin/myapicontainer/facebook/refresh_token')
            .then(response => response.text()) // Or .json() if it's JSON
            .then(data => {
                Swal.fire({
                    toast: true,
                    position: 'bottom',
                    icon: 'success',
                    title: 'Tokens Refreshed Successfully',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true,
                    customClass: {
                        popup: 'custom-toast-popup'
                    }
                });
                console.log('Response:', data);
            })
            .catch(error => {
                console.error('Error refreshing tokens:', error);
                Swal.fire({
                    toast: true,
                    position: 'bottom',
                    icon: 'error',
                    title: 'Failed to refresh tokens. Check the console for details.',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true,
                    customClass: {
                        popup: 'custom-toast-popup'
                    }
                });
            });
    }

</script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const sourceSelect = document.getElementById('source_name');
        const projectInput = document.getElementById('project_name');
        const assignInput = document.getElementById('assign_user');
        const userSelect = document.getElementById('create_users');

        const formFields = [projectInput.closest('.col-md-12'), assignInput.closest('.col-md-12'), userSelect.closest('.col-md-12')];

        sourceSelect.addEventListener('change', function () {
            const value = this.value;

            const portalSources = ['magicbricks ads', '99acres ads', 'housing.com ads'];
            const isPortal = portalSources.includes(value);

            if (isPortal) {
                // Auto-fill project name and assign_user
                projectInput.value = 'Portal API';
                assignInput.value = 'Vipul0001'; // Replace with your desired default value
                userSelect.selectedIndex = -1;

                // Hide other input fields
                formFields.forEach(field => {
                    field.style.display = 'none';
                });
            } else {
                // Clear values and show fields again
                projectInput.value = '';
                assignInput.value = '';
                formFields.forEach(field => {
                    field.style.display = 'block';
                });
            }
        });

        // Column Visibility Toggle Handler
        const visibilityButton = document.querySelector('.toolbar-icon-btn.Visibility_dropdown');
        const visibilityDropdown = document.querySelector('.Visibility_dropdown-content');

        if (visibilityButton && visibilityDropdown) {
            // Toggle dropdown on button click
            visibilityButton.addEventListener('click', function (e) {
                e.stopPropagation();
                visibilityDropdown.classList.toggle('show');
            });

            // Close dropdown when clicking outside
            document.addEventListener('click', function (e) {
                if (!visibilityButton.contains(e.target) && !visibilityDropdown.contains(e.target)) {
                    visibilityDropdown.classList.remove('show');
                }
            });

            // Keep dropdown open when clicking on checkboxes inside
            visibilityDropdown.addEventListener('click', function (e) {
                e.stopPropagation();
            });
        }

        // Initialize Column Visibility
        const table = document.getElementById('myTable');
        const columnSelector = document.getElementById('columnSelector');

        if (table && columnSelector) {
            const headers = table.querySelectorAll('thead th');
            // Columns to hide by default: API Key (index 2), Actions (index 6), and Created At (index 7)
            const hiddenByDefault = [2, 6, 7];

            // Create checkbox for each column
            headers.forEach((header, index) => {
                const label = document.createElement('label');
                label.style.display = 'flex';
                label.style.alignItems = 'center';
                label.style.padding = '6px 8px';
                label.style.cursor = 'pointer';
                label.style.borderRadius = '4px';
                label.style.transition = 'background 0.2s ease';

                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                // Check if this column should be hidden by default
                const isHiddenByDefault = hiddenByDefault.includes(index);
                checkbox.checked = !isHiddenByDefault;
                checkbox.dataset.columnIndex = index;
                checkbox.style.marginRight = '8px';

                // Hide column if it's in the hiddenByDefault array
                if (isHiddenByDefault) {
                    header.style.display = 'none';
                }

                const columnName = header.textContent.trim();
                label.appendChild(checkbox);
                label.appendChild(document.createTextNode(columnName));

                // Toggle column visibility
                checkbox.addEventListener('change', function () {
                    const isChecked = this.checked;
                    // Toggle header
                    header.style.display = isChecked ? '' : 'none';

                    // Toggle all cells in this column
                    const rows = table.querySelectorAll('tbody tr');
                    rows.forEach(row => {
                        const cell = row.cells[index];
                        if (cell) {
                            cell.style.display = isChecked ? '' : 'none';
                        }
                    });
                });

                // Hover effect
                label.addEventListener('mouseenter', function () {
                    this.style.background = '#f3f4f6';
                });
                label.addEventListener('mouseleave', function () {
                    this.style.background = '';
                });

                columnSelector.appendChild(label);
            });
        }

        // Filter functionality
        const applyFilterBtn = document.getElementById('applyApiFilter');
        const clearFilterBtn = document.getElementById('clearApiFilter');
        const clearFiltersBtnMain = document.getElementById('clearFiltersBtn'); // Main page clear button
        const filterModal = document.getElementById('apiFilterModal');
        let currentFilters = {};

        // Helper function to toggle Clear Filters button visibility
        function toggleClearFiltersButton() {
            if (Object.keys(currentFilters).length > 0) {
                clearFiltersBtnMain.style.display = 'inline-flex';
            } else {
                clearFiltersBtnMain.style.display = 'none';
            }
        }

        // Clear filters from main page button
        if (clearFiltersBtnMain) {
            clearFiltersBtnMain.addEventListener('click', function () {
                // Clear all filter inputs
                document.getElementById('filter_project_name').value = '';
                document.getElementById('filter_api_source').value = '';
                document.getElementById('filter_assigned_user').value = '';
                document.getElementById('filter_date_from').value = '';
                document.getElementById('filter_date_to').value = '';
                currentFilters = {};

                // Fetch data without filters
                const currentPage = 1;
                const rowsPerPage = parseInt(document.getElementById('rowSelector').value, 10);
                const searchQuery = document.getElementById('searchInput').value;
                const url = `action_api.php?page=${currentPage}&rowsPerPage=${rowsPerPage}&searchQuery=${encodeURIComponent(searchQuery)}&filter=`;

                fetch(url)
                    .then(response => response.json())
                    .then(data => {
                        updateTable(data.data);
                        updatePagination(data.totalRows, data.currentPage, data.rowsPerPage);
                        getCellModel();
                        toggleClearFiltersButton(); // Hide the button
                        console.log('Filters cleared from main page');
                    })
                    .catch(error => {
                        console.error('Error clearing filter:', error);
                    });
            });
        }

        // Apply Filter Handler
        if (applyFilterBtn) {
            applyFilterBtn.addEventListener('click', function () {
                const filterData = {
                    project_name: document.getElementById('filter_project_name').value.trim(),
                    api_source: document.getElementById('filter_api_source').value,
                    assigned_user: document.getElementById('filter_assigned_user').value.trim(),
                    date_from: document.getElementById('filter_date_from').value,
                    date_to: document.getElementById('filter_date_to').value
                };

                Object.keys(filterData).forEach(key => {
                    if (!filterData[key]) delete filterData[key];
                });

                currentFilters = filterData;
                const filterString = JSON.stringify(filterData);
                const currentPage = 1;
                const rowsPerPage = parseInt(document.getElementById('rowSelector').value, 10);
                const searchQuery = document.getElementById('searchInput').value;
                const url = `action_api.php?page=${currentPage}&rowsPerPage=${rowsPerPage}&searchQuery=${encodeURIComponent(searchQuery)}&filter=${encodeURIComponent(filterString)}`;

                fetch(url)
                    .then(response => response.json())
                    .then(data => {
                        updateTable(data.data);
                        updatePagination(data.totalRows, data.currentPage, data.rowsPerPage);
                        getCellModel();

                        // Properly close modal and remove backdrop
                        const modal = bootstrap.Modal.getInstance(filterModal);
                        if (modal) {
                            modal.hide();
                        }
                        // Remove backdrop manually
                        document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
                        document.body.classList.remove('modal-open');
                        document.body.style.overflow = '';
                        document.body.style.paddingRight = '';

                        toggleClearFiltersButton(); // Show clear button
                        console.log('Filter applied successfully');
                    })
                    .catch(error => {
                        console.error('Error applying filter:', error);
                        alert('Error applying filter. Please try again.');
                    });
            });
        }

        // Clear Filter Handler
        if (clearFilterBtn) {
            clearFilterBtn.addEventListener('click', function () {
                document.getElementById('filter_project_name').value = '';
                document.getElementById('filter_api_source').value = '';
                document.getElementById('filter_assigned_user').value = '';
                document.getElementById('filter_date_from').value = '';
                document.getElementById('filter_date_to').value = '';
                currentFilters = {};

                const currentPage = 1;
                const rowsPerPage = parseInt(document.getElementById('rowSelector').value, 10);
                const searchQuery = document.getElementById('searchInput').value;
                const url = `action_api.php?page=${currentPage}&rowsPerPage=${rowsPerPage}&searchQuery=${encodeURIComponent(searchQuery)}&filter=`;

                fetch(url)
                    .then(response => response.json())
                    .then(data => {
                        updateTable(data.data);
                        updatePagination(data.totalRows, data.currentPage, data.rowsPerPage);
                        getCellModel();

                        // Properly close modal and remove backdrop
                        const modal = bootstrap.Modal.getInstance(filterModal);
                        if (modal) {
                            modal.hide();
                        }
                        // Remove backdrop manually
                        document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
                        document.body.classList.remove('modal-open');
                        document.body.style.overflow = '';
                        document.body.style.paddingRight = '';

                        toggleClearFiltersButton(); // Hide clear button
                        console.log('Filters cleared successfully');
                    })
                    .catch(error => {
                        console.error('Error clearing filter:', error);
                        alert('Error clearing filter. Please try again.');
                    });
            });
        }
    });
</script>

<script>
    // Assign Users Modal Handler
    $(document).ready(function () {
        // Initialize Select2 for bulk user selection
        $('#bulk-user-select').select2({
            dropdownParent: $('#assignModal .modal-content'),
            theme: 'bootstrap-5',
            placeholder: 'Select users',
            allowClear: true,
            width: '100%',
            dropdownAutoWidth: true
        });

        // Load users when modal is shown
        $('#assignModal').on('show.bs.modal', function () {
            loadUsersForAssignment();
            updateSelectedAPICount();
        });

        // Function to load users for assignment
        function loadUsersForAssignment() {
            $.ajax({
                url: 'action_api.php?get_users=1',
                method: 'GET',
                success: function (htmlOptions) {
                    const select = $('#bulk-user-select');
                    select.empty();
                    select.append(htmlOptions);
                    select.trigger('change');
                },
                error: function () {
                    console.error('Failed to load users');
                    alert('Failed to load users. Please try again.');
                }
            });
        }

        // Update selected API count
        function updateSelectedAPICount() {
            const selectedCount = $('.row-checkbox:not(#select-all):checked').length;
            $('#selected-api-count').text(selectedCount);
        }

        // Handle bulk assign submission
        $('#submitBulkAssign').click(function () {
            const selectedUsers = $('#bulk-user-select').val();
            const selectedAPIIds = window.getSelectedRowIds();

            if (!selectedUsers || selectedUsers.length === 0) {
                alert('Please select at least one user to assign.');
                return;
            }

            if (selectedAPIIds.length === 0) {
                alert('No APIs selected.');
                return;
            }

            // Prepare data for submission
            const assignData = {
                api_ids: selectedAPIIds,
                users: selectedUsers.join(',')
            };

            // Submit via AJAX
            $.ajax({
                url: '../scripts/bulk_assign_users.php',
                method: 'POST',
                data: assignData,
                dataType: 'json',
                success: function (response) {
                    if (response.success) {
                        // Show success toast notification
                        Swal.fire({
                            toast: true,
                            position: 'bottom',
                            icon: 'success',
                            title: 'Users assigned successfully!',
                            showConfirmButton: false,
                            timer: 3000,
                            timerProgressBar: true,
                            customClass: {
                                popup: 'custom-toast-popup'
                            }
                        });
                        $('#assignModal').modal('hide');
                        location.reload(); // Refresh to show updated assignments
                    } else {
                        // Show error toast notification
                        Swal.fire({
                            toast: true,
                            position: 'bottom',
                            icon: 'error',
                            title: response.message || 'Failed to assign users',
                            showConfirmButton: false,
                            timer: 3000,
                            timerProgressBar: true,
                            customClass: {
                                popup: 'custom-toast-popup'
                            }
                        });
                    }
                },
                error: function () {
                    // Show error toast notification
                    Swal.fire({
                        toast: true,
                        position: 'bottom',
                        icon: 'error',
                        title: 'Error assigning users. Please try again.',
                        showConfirmButton: false,
                        timer: 3000,
                        timerProgressBar: true,
                        customClass: {
                            popup: 'custom-toast-popup'
                        }
                    });
                }
            });
        });

        // Handle bulk delete button click
        $('#delete-selected-btn').click(function () {
            const selectedAPIIds = window.getSelectedRowIds();

            if (selectedAPIIds.length === 0) {
                Swal.fire({
                    toast: true,
                    position: 'bottom',
                    icon: 'warning',
                    title: 'Please select at least one API to delete.',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true,
                    customClass: {
                        popup: 'custom-toast-popup'
                    }
                });
                return;
            }

            // Confirm deletion with styled SweetAlert2 popup
            Swal.fire({
                title: 'Are you sure?',
                text: `You are about to delete ${selectedAPIIds.length} selected API(s). This action cannot be undone.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, delete them!',
                cancelButtonText: 'No, cancel!'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show loading state
                    $('#delete-selected-btn').prop('disabled', true);
                    $('#delete-selected-btn').html('<i class="bi bi-hourglass-split"></i>');

                    // Submit delete request via AJAX
                    $.ajax({
                        url: 'action_api.php',
                        method: 'POST',
                        data: {
                            bulk_delete: 1,
                            api_ids: selectedAPIIds
                        },
                        dataType: 'json',
                        success: function (response) {
                            if (response.success) {
                                Swal.fire({
                                    toast: true,
                                    position: 'bottom',
                                    icon: 'success',
                                    title: 'APIs deleted successfully!',
                                    showConfirmButton: false,
                                    timer: 3000,
                                    timerProgressBar: true,
                                    customClass: {
                                        popup: 'custom-toast-popup'
                                    }
                                });
                                location.reload(); // Refresh the page
                            } else {
                                Swal.fire({
                                    toast: true,
                                    position: 'bottom',
                                    icon: 'error',
                                    title: response.message || 'Failed to delete APIs',
                                    showConfirmButton: false,
                                    timer: 3000,
                                    timerProgressBar: true,
                                    customClass: {
                                        popup: 'custom-toast-popup'
                                    }
                                });
                                $('#delete-selected-btn').prop('disabled', false);
                                $('#delete-selected-btn').html('<i class="bi bi-trash"></i>');
                            }
                        },
                        error: function (xhr, status, error) {
                            console.error('Delete error:', error);
                            Swal.fire({
                                toast: true,
                                position: 'bottom',
                                icon: 'error',
                                title: 'Error deleting APIs. Please try again.',
                                showConfirmButton: false,
                                timer: 3000,
                                timerProgressBar: true,
                                customClass: {
                                    popup: 'custom-toast-popup'
                                }
                            });
                            $('#delete-selected-btn').prop('disabled', false);
                            $('#delete-selected-btn').html('<i class="bi bi-trash"></i>');
                        }
                    });
                }
            });
        });

        // Enable/disable delete and assign buttons based on checkbox selection
        $(document).on('change', '.row-checkbox', function () {
            const selectedCount = $('.row-checkbox:not(#select-all):checked').length;
            const deleteBtn = $('#delete-selected-btn');
            const assignBtn = $('#assign-button');

            if (selectedCount > 0) {
                deleteBtn.prop('disabled', false);
                assignBtn.prop('disabled', false);
            } else {
                deleteBtn.prop('disabled', true);
                assignBtn.prop('disabled', true);
            }
        });
    });
</script>

<script>
    // Fix for modal backdrop persistence issue
    $(document).ready(function () {
        // Allow focus on SweetAlert2 inputs when a Bootstrap modal is open
        // Using capture phase (true) to intercept before Bootstrap's focus enforcement
        document.addEventListener('focusin', function (e) {
            if (e.target.closest && e.target.closest(".swal2-container")) {
                e.stopImmediatePropagation();
            }
        }, true);

        // Clean up modal backdrops when any modal is hidden
        $('.modal').on('hidden.bs.modal', function () {
            // Remove all modal backdrops
            $('.modal-backdrop').remove();
            // Remove modal-open class from body
            $('body').removeClass('modal-open');
            // Reset body padding
            $('body').css('padding-right', '');
        });

        // Additional cleanup for page reload after API creation
        if ($('.modal-backdrop').length > 0) {
            $('.modal-backdrop').remove();
            $('body').removeClass('modal-open');
            $('body').css('padding-right', '');
        }
    });
</script>

<!--group Manager JS Start-->
<script>
    !function(){let e=document.getElementById("gmSearchInput"),t=document.getElementById("gmClearSearch"),n=document.getElementById("gmGroupsList"),o=document.getElementById("gmRowSearch"),l=document.getElementById("gmClearRowSearch"),i=document.getElementById("gmRowsList"),r=null,s=$("#gmAssignUsers");function a(e,t){return!!e&&!!t&&-1!==e.toString().toLowerCase().indexOf(t.toLowerCase())}function c(){let t=(e.value||"").trim();for(let o=0;o<n.options.length;o++){let l=n.options[o],i=""===t||a(l.text,t)||a(l.value,t);l.style.display=i?"":"none"}let r=Array.from(n.options).some(e=>"none"!==e.style.display);if(r)n._noResultOption&&(n.remove(n._noResultOption.index),n._noResultOption=null);else if(!n._noResultOption){let s=document.createElement("option");s.text="No groups match your search",s.disabled=!0,s.style.color="#888",n.add(s),n._noResultOption=s}}function u(){let e=(o.value||"").trim();if(!i)return;let t=i.querySelectorAll(".form-check"),n=!1;if(t.forEach(t=>{let o=t.innerText||t.textContent||"",l=""===e||a(o,e);t.style.display=l?"":"none",l&&(n=!0)}),n)i._noRes&&(i._noRes.remove(),i._noRes=null);else if(!i._noRes){let l=document.createElement("div");l.className="text-muted",l.style.padding="8px",l.textContent="No API rows match your search.",i.appendChild(l),i._noRes=l}}if(e)e.addEventListener("input",c);if(o)o.addEventListener("input",u);window.gm_applyClientFilters=function(){c(),u()}}();
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  // Elements
  const openBtn = document.getElementById('openGroupManagerBtn');
  const modalEl = document.getElementById('groupManagerModal');
  const gmGroupsList = document.getElementById('gmGroupsList');
  const gmAssignUsers = $('#gmAssignUsers'); // use Select2
  const gmRowsList = document.getElementById('gmRowsList');
  const gmDetailPane = document.getElementById('gmDetailPane');
  const gmSaveNotice = document.getElementById('gmSaveNotice');

  // Buttons
  const gmCreateGroupBtn = document.getElementById('gmCreateGroupBtn');
  const gmDeleteGroupBtn = document.getElementById('gmDeleteGroupBtn');
  const gmRefreshBtn = document.getElementById('gmRefreshBtn');
  const gmSaveGroupBtn = document.getElementById('gmSaveGroupBtn');
  const gmAddCheckedRows = document.getElementById('gmAddCheckedRows');
  const gmRemoveCheckedRows = document.getElementById('gmRemoveCheckedRows');

  // helper show professional toast success (using Swal if available, else fallback)
  function showGMToast(icon, title) {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            toast: true,
            position: 'bottom',
            icon: icon,
            title: title,
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            customClass: { popup: 'custom-toast-popup' }
        });
    } else {
        alert(title);
    }
  }

  // init select2 for assign users
  function initAssignUsersSelect() {
    gmAssignUsers.select2({
      dropdownParent: $('#groupManagerModal .modal-content'),
      theme: 'bootstrap-5',
      placeholder: 'Search & select users',
      allowClear: true,
      width: '100%'
    });
  }
  initAssignUsersSelect();

  // open modal
  if (openBtn && modalEl) {
    openBtn.addEventListener('click', async () => {
        try {
            // Disable focus enforcement for this modal instance to allow sub-popups like Swal to work
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl, {
                focus: false
            });
            modal.show();
            await loadGroupsList();
        } catch (err) {
            console.error('Modal activation error:', err);
            $(modalEl).modal('show');
        }
    });
  }

  // load list of groups
  async function loadGroupsList() {
    gmGroupsList.innerHTML = '';
    gmDetailPane.style.display = 'none';
    gmDeleteGroupBtn.disabled = true;
    try {
      const res = await fetch('action_api.php?get_groups=1');
      if (!res.ok) throw new Error('Failed to fetch groups');
      const groups = await res.json();
      if (!Array.isArray(groups) || groups.length === 0) {
        gmGroupsList.innerHTML = '<option disabled>No groups found</option>';
        document.getElementById('gmGroupsTotal').textContent = '0 groups';
        return;
      }
      groups.forEach(g => {
        const opt = document.createElement('option');
        opt.value = g.id;
        opt.textContent = (g.group_name || 'Group') + (g.project_name ? ' — ' + g.project_name : '');
        gmGroupsList.appendChild(opt);
      });
      document.getElementById('gmGroupsTotal').textContent = `${groups.length} groups`;
      
      // Auto-select first group if available
      if (groups.length > 0) {
        gmGroupsList.selectedIndex = 0;
        await loadSelectedGroup();
      }
    } catch (err) {
      console.error(err);
      showGMToast('error', 'Could not load groups list');
    }
  }

  // when group selected
  gmGroupsList.addEventListener('change', loadSelectedGroup);

  // load group details
  async function loadSelectedGroup() {
    const gid = gmGroupsList.value;
    if (!gid) {
        gmDetailPane.style.display = 'none';
        gmDeleteGroupBtn.disabled = true;
        return;
    }
    gmRowsList.innerHTML = '<div class="text-center p-3"><i class="bi bi-hourglass-split"></i> Loading...</div>';
    gmSaveNotice.style.display = 'none';
    gmDeleteGroupBtn.disabled = false;

    try {
      const res = await fetch(`action_api.php?get_group_details=1&id=${encodeURIComponent(gid)}`);
      if (!res.ok) throw new Error('Failed to fetch group details');
      const data = await res.json();
      
      document.getElementById('gmSelectedName').innerText = `${data.group.group_name || 'Group'} (id: ${data.group.id})`;
      
      // Load users
      const usersRes = await fetch('action_api.php?get_users=1');
      const optionsHtml = await usersRes.text();
      gmAssignUsers.html(optionsHtml);
      gmAssignUsers.val((data.group.assign_user || '').split(',').map(s => s.trim()).filter(Boolean)).trigger('change');

      // Populate rows
      gmRowsList.innerHTML = '';
      const inGroup = data.rows || [];
      const candidates = data.candidates || [];

      const renderRow = (r, checked) => {
          const div = document.createElement('div');
          div.className = 'form-check';
          div.innerHTML = `
              <input class="form-check-input gm_rowCheckbox" type="checkbox" value="${r.id}" id="gm_row_${r.id}" ${checked ? 'checked' : ''}>
              <label class="form-check-label" for="gm_row_${r.id}">
                  <strong>${r.project_name}</strong><br>
                  <small class="text-muted">${r.api_key} | ${r.lead_source}</small>
              </label>
          `;
          gmRowsList.appendChild(div);
      };

      inGroup.forEach(r => renderRow(r, true));
      candidates.forEach(r => renderRow(r, false));

      gmDetailPane.style.display = 'flex';
      updateCheckedButtons();
    } catch (err) {
      console.error(err);
      showGMToast('error', 'Failed to load group details');
    }
  }

  // Update buttons based on checkboxes
  function updateCheckedButtons() {
    const checked = document.querySelectorAll('.gm_rowCheckbox:checked').length;
    gmAddCheckedRows.disabled = checked === 0;
    gmRemoveCheckedRows.disabled = checked === 0;
  }
  gmRowsList.addEventListener('change', (e) => {
    if (e.target.classList.contains('gm_rowCheckbox')) {
        updateCheckedButtons();
        gmSaveNotice.style.display = 'block';
    }
  });

  // Save changes
  gmSaveGroupBtn.addEventListener('click', async () => {
    const gid = gmGroupsList.value;
    const selectedUsers = gmAssignUsers.val() || [];
    const checkedRows = Array.from(document.querySelectorAll('.gm_rowCheckbox:checked')).map(cb => cb.value);
    
    try {
      const res = await fetch('action_api.php?action=update_group', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ group_id: gid, assign_users: selectedUsers, rows: checkedRows })
      });
      const json = await res.json();
      if (json.status === 'success') {
        showGMToast('success', 'Group saved successfully');
        gmSaveNotice.style.display = 'none';
        await loadSelectedGroup();
      } else {
        showGMToast('error', json.message || 'Error saving group');
      }
    } catch (err) {
      console.error(err);
      showGMToast('error', 'Network error');
    }
  });

  // Action Center: Add Selected API Rows to Group
  gmAddCheckedRows.addEventListener('click', async () => {
    const gid = gmGroupsList.value;
    const checkedRows = Array.from(document.querySelectorAll('.gm_rowCheckbox:checked')).map(cb => cb.value);
    if (!gid || checkedRows.length === 0) return;

    try {
      const res = await fetch('action_api.php?action=add_rows_to_group', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ group_id: gid, ids: checkedRows })
      });
      const json = await res.json();
      if (json.status === 'success') {
        showGMToast('success', 'Rows added to group');
        await loadSelectedGroup();
      } else {
        showGMToast('error', json.message || 'Error adding rows');
      }
    } catch (err) {
      console.error(err);
      showGMToast('error', 'Network error');
    }
  });

  // Action Center: Remove Selected APIs from Group
  gmRemoveCheckedRows.addEventListener('click', async () => {
    const gid = gmGroupsList.value;
    const checkedRows = Array.from(document.querySelectorAll('.gm_rowCheckbox:checked')).map(cb => cb.value);
    if (!gid || checkedRows.length === 0) return;

    const result = await Swal.fire({
      title: 'Remove from Group?',
      text: "This will remove the selected API rows from this group.",
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Yes, remove them'
    });
    if (!result.isConfirmed) return;

    try {
      const res = await fetch('action_api.php?action=remove_rows_from_group', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ group_id: gid, ids: checkedRows })
      });
      const json = await res.json();
      if (json.status === 'success') {
        showGMToast('success', 'Rows removed from group');
        await loadSelectedGroup();
      } else {
        showGMToast('error', json.message || 'Error removing rows');
      }
    } catch (err) {
      console.error(err);
      showGMToast('error', 'Network error');
    }
  });

  // Create group
  gmCreateGroupBtn.addEventListener('click', async () => {
    const { value: name } = await Swal.fire({
      title: 'Create New Group',
      input: 'text',
      inputLabel: 'Group Name',
      showCancelButton: true,
      inputValidator: (value) => { if (!value) return 'You need to write something!'; },
      didOpen: () => {
        setTimeout(() => {
          const input = Swal.getInput();
          if (input) {
            input.focus();
            // Force focus again after a short delay to be sure
            setTimeout(() => input.focus(), 100);
          }
        }, 100);
      }
    });
    if (!name) return;
    try {
      const res = await fetch('action_api.php?action=create_group', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ group_name: name })
      });
      const json = await res.json();
      if (json.status === 'success') {
        showGMToast('success', 'Group created');
        await loadGroupsList();
      } else showGMToast('error', json.message || 'Create failed');
    } catch (err) { showGMToast('error', 'Network error'); }
  });

  // Delete group
  gmDeleteGroupBtn.addEventListener('click', async () => {
    const gid = gmGroupsList.value;
    const result = await Swal.fire({
      title: 'Are you sure?',
      text: "This will remove group assignment from all associated API rows.",
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes, delete it!'
    });
    if (!result.isConfirmed) return;
    try {
      const res = await fetch('action_api.php?action=delete_group', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ group_id: gid })
      });
      const json = await res.json();
      if (json.status === 'success') {
        showGMToast('success', 'Group deleted');
        await loadGroupsList();
      } else showGMToast('error', json.message || 'Delete failed');
    } catch (err) { showGMToast('error', 'Network error'); }
  });

  // Refresh List
  gmRefreshBtn.addEventListener('click', async () => {
    const gid = gmGroupsList.value;
    const btnIcon = gmRefreshBtn.querySelector('i');
    
    // Add rotating animation
    btnIcon.classList.add('bi-arrow-repeat');
    btnIcon.classList.remove('bi-arrow-clockwise');
    gmRefreshBtn.disabled = true;
    
    try {
        await loadGroupsList();
        if (gid) {
            gmGroupsList.value = gid;
            await loadSelectedGroup();
        }
        showGMToast('success', 'Group list refreshed');
    } catch (err) {
        showGMToast('error', 'Failed to refresh list');
    } finally {
        gmRefreshBtn.disabled = false;
        btnIcon.classList.remove('bi-arrow-repeat');
        btnIcon.classList.add('bi-arrow-clockwise');
    }
  });

  // Bulk Assign Handler (Outside Group Manager)
  const submitBulkGroupAssign = document.getElementById('submitBulkGroupAssign');
  const bulkGroupSelect = document.getElementById('bulk-group-select');

  $('#assignGroupModal').on('show.bs.modal', async function () {
      const selected = window.getSelectedRowIds();
      document.getElementById('assign-group-api-count').textContent = selected.length;
      
      // Load groups for dropdown
      try {
          const res = await fetch('action_api.php?get_groups=1');
          const groups = await res.json();
          bulkGroupSelect.innerHTML = '<option value="">-- Choose a Group --</option>';
          groups.forEach(g => {
              const opt = document.createElement('option');
              opt.value = g.id;
              opt.textContent = g.group_name;
              bulkGroupSelect.appendChild(opt);
          });
      } catch (err) { console.error(err); }
  });

  if (submitBulkGroupAssign) {
    submitBulkGroupAssign.addEventListener('click', async () => {
        const selectedIds = window.getSelectedRowIds();
        const groupId = bulkGroupSelect.value;
        if (!groupId) { showGMToast('warning', 'Please select a group'); return; }

        try {
            const res = await fetch('action_api.php?assign_to_group=1', {
                method: 'POST',
                headers: {'Content-Type':'application/json'},
                body: JSON.stringify({ selected_ids: selectedIds, group_id: groupId })
            });
            const json = await res.json();
            if (json.status === 'success') {
                showGMToast('success', 'APIs assigned to group');
                const modalEl = document.getElementById('assignGroupModal');
                if (modalEl) {
                    try {
                        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                        modal.hide();
                    } catch (err) {
                        $(modalEl).modal('hide');
                    }
                }
                // Refresh table if possible
                if (typeof fetchData === 'function') {
                    const page = (typeof currentPage !== 'undefined') ? currentPage : 1;
                    const rows = (typeof rowsPerPage !== 'undefined') ? rowsPerPage : (document.getElementById('rowSelector')?.value || 10);
                    const search = document.getElementById('searchInput')?.value || '';
                    fetchData(page, rows, search);
                } else {
                    location.reload();
                }
            } else {
                showGMToast('error', json.message || 'Failed to assign');
            }
        } catch (err) { showGMToast('error', 'Network error'); }
    });
  }

});
</script>

<?php include('../htmlclose.php'); ?>