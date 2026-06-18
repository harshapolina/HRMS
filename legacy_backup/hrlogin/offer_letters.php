<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['loggedin'])) {
    header('Location: /');
    exit;
}

require_once __DIR__ . '/db.php';
$db = new Database();
$db->ensureOfferLettersSynced();
$offerStats = $db->getOfferLetterStats();

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

include('htmlopen.php');
?>
<link rel="stylesheet" href="./assets/css/unified_table_styles.css?v=<?php echo time(); ?>" />
<link rel="stylesheet" href="./assets/css/Users.css?v=<?php echo time(); ?>" />
<link rel="stylesheet" href="./assets/css/style_dashboard.css?v=<?php echo time(); ?>" />
<style>
  body, html {
    background: var(--bg-gradient) !important;
    background-attachment: fixed !important;
    color: var(--text-dark) !important;
  }
  .incentivemain, .content { background: transparent !important; border: none !important; box-shadow: none !important; }

  .offer-controls-card {
      background: transparent !important;
      border: none !important;
      box-shadow: none !important;
      padding: 0 !important;
      margin-top: 0 !important;
  }
  .offer-controls-card .control-left { display: none !important; }
  .offer-controls-card .control-bar {
      justify-content: flex-start !important;
      gap: 16px !important;
      flex-wrap: nowrap !important;
      align-items: center !important;
  }
  .offer-controls-card .header-tools-wrapper { width: 100% !important; }
  .offer-controls-card .btn-column-visibility,
  .offer-controls-card .btn-new-offer,
  .offer-controls-card .btn-export-offers {
      min-width: 136px !important;
      white-space: nowrap !important;
  }
  .offer-controls-card .btn-new-offer {
      background: #227477 !important;
      color: #fff !important;
      border: none !important;
      min-width: 170px !important;
      justify-content: center !important;
  }
  .offer-controls-card .btn-export-offers {
      background: #ffffff !important;
      color: #227477 !important;
      border: 1px solid #cde8e6 !important;
  }
  #offerFilterForm { display: flex; flex-direction: column; align-items: flex-end; gap: 8px; }
  #offerFilterForm .filter-group { display: flex; align-items: center; gap: 8px; }
  #offerFilterForm .offer-status-filter {
      width: 118px !important;
      border-radius: 8px !important;
      font-size: 13px !important;
  }
  @media (min-width: 768px) {
      #offerFilterForm {
          width: 100% !important;
          display: flex !important;
          flex-direction: row !important;
          align-items: center !important;
          justify-content: flex-start !important;
          flex-wrap: nowrap !important;
          gap: 12px !important;
      }
      #offerFilterForm .filter-group { flex-wrap: nowrap !important; gap: 12px !important; align-items: center !important; }
      #offerFilterForm .filter-group:first-child { flex: 1 1 auto !important; min-width: 0 !important; }
      #offerFilterForm .filter-group:last-child { margin-left: auto !important; flex: 0 0 auto !important; }
      #offerFilterForm .search-box { width: 100% !important; }
      #offerFilterForm .search-input { width: 100% !important; min-width: 320px !important; }

      /* Uniform toolbar height — match Status dropdown */
      .offer-controls-card .offer-status-filter,
      .offer-controls-card #offerFilterForm .search-input,
      .offer-controls-card .btn-export-offers,
      .offer-controls-card #bulkEmailOffersBtn,
      .offer-controls-card .btn-new-offer,
      .offer-controls-card .page-size-selector {
          height: 38px !important;
          min-height: 38px !important;
          max-height: 38px !important;
          box-sizing: border-box !important;
      }

      .offer-controls-card .offer-status-filter {
          padding: 0 28px 0 12px !important;
          font-size: 14px !important;
          line-height: 1.5 !important;
          border: 1px solid #ddd !important;
          background-color: #fff !important;
          display: inline-flex !important;
          align-items: center !important;
      }

      .offer-controls-card #offerFilterForm .search-input {
          padding: 0 16px 0 44px !important;
          line-height: normal !important;
          border-radius: 8px !important;
          border: 1px solid #ddd !important;
          font-size: 14px !important;
      }

      .offer-controls-card .btn-export-offers,
      .offer-controls-card #bulkEmailOffersBtn,
      .offer-controls-card .btn-new-offer {
          padding: 0 18px !important;
          display: inline-flex !important;
          align-items: center !important;
          justify-content: center !important;
          border-radius: 8px !important;
          font-size: 14px !important;
      }

      .offer-controls-card .page-size-selector {
          padding: 0 10px !important;
          display: inline-flex !important;
          align-items: center !important;
          justify-content: center !important;
      }

      .offer-controls-card .page-size-selector select.users-limit-select {
          height: 100% !important;
          padding: 0 4px !important;
          line-height: normal !important;
      }
  }

  @media (min-width: 768px) and (max-width: 1024px) {
      .offer-controls-card .control-bar {
          justify-content: flex-start !important;
          flex-wrap: wrap !important;
      }
      #offerFilterForm {
          flex-wrap: wrap !important;
          gap: 12px !important;
          width: 100% !important;
      }
      #offerFilterForm .filter-group {
          flex-wrap: wrap !important;
          gap: 8px !important;
          width: 100% !important;
      }
      #offerFilterForm .filter-group:first-child {
          flex: 1 1 100% !important;
          min-width: 100% !important;
      }
      #offerFilterForm .filter-group:last-child {
          margin-left: 0 !important;
          flex: 1 1 auto !important;
          justify-content: flex-start !important;
          gap: 8px !important;
      }
      #offerFilterForm .search-input {
          width: 100% !important;
          min-width: 100% !important;
      }
  }

  .offer-controls-card .page-size-selector {
      display: inline-flex !important;
      align-items: center !important;
      justify-content: center !important;
      flex: 0 0 auto !important;
      min-width: 64px !important;
      max-width: 76px !important;
      padding: 8px 10px !important;
      border-radius: 8px !important;
      border: 1px solid #ddd !important;
      background: #ffffff !important;
  }
  .offer-controls-card .page-size-selector select.users-limit-select {
      border: 0 !important;
      background: transparent !important;
      padding: 2px 4px !important;
      font-size: 14px !important;
      width: 100% !important;
      cursor: pointer !important;
  }
  .hr-offers-page .user-data-table {
      border-collapse: separate !important;
      border-spacing: 0 10px !important;
      background: transparent !important;
  }
  .hr-offers-page .user-data-table.unified-table tbody tr {
      background: var(--table-bg) !important;
      border-radius: 12px !important;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.06) !important;
  }
  .hr-offers-page .user-data-table tbody td { border: none !important; background: transparent !important; }

  .offer-pagination-section { margin-top: 8px; }
  .offer-action-link {
      border: 0;
      background: transparent;
      padding: 2px 4px;
      line-height: 1;
      cursor: pointer;
  }
  .offer-action-link.is-disabled,
  .offer-card-status-btn.is-disabled {
      opacity: 0.45;
      cursor: not-allowed !important;
      pointer-events: none;
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
  .summary-section .summary-card.stat-card-late.active-filter { background: #fef3c7 !important; border: 3px solid #f59e0b !important; }
  .summary-section .summary-card.stat-card-present.active-filter { background: #d1fae5 !important; border: 3px solid #10b981 !important; }
  .summary-section .summary-card.stat-card-headcount.active-filter { background: #e0f2fe !important; border: 3px solid #0ea5e9 !important; }
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

  .mobile-only-view,
  .desktop-only-view {
      display: block;
  }

  .hr-offers-page .container-fluid {
      padding-top: 6px !important;
  }

  .hr-offers-page .user-table-container {
      background: transparent !important;
      border: none !important;
      box-shadow: none !important;
  }

  /* ===================================
     DARK MODE OVERRIDES (Charcoal Monochrome)
     =================================== */
  body.dark-mode, body.dark-mode html {
      color: #ffffff !important;
  }

  body.dark-mode .summary-section .summary-card {
      background: rgba(0, 0, 0, 0.6) !important;
      color: rgba(255, 255, 255, 0.95) !important;
      border-color: rgba(255, 255, 255, 0.15) !important;
      box-shadow: none !important;
      transition: all 0.25s ease-in-out !important;
  }
  body.dark-mode .summary-section .summary-card:hover {
      background: rgba(20, 20, 20, 0.8) !important;
      transform: translateY(-2px) !important;
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4) !important;
  }
  body.dark-mode .summary-section .summary-card .summary-text {
      color: rgba(255, 255, 255, 0.95) !important;
  }
  body.dark-mode .summary-section .summary-card.stat-card-present {
      border: 2px solid #10b981 !important;
  }
  body.dark-mode .summary-section .summary-card.stat-card-headcount {
      border: 2px solid #0ea5e9 !important;
  }
  body.dark-mode .summary-section .summary-card.stat-card-late {
      border: 2px solid #f59e0b !important;
  }

  /* Active Filter Styles - Dark Mode */
  body.dark-mode .summary-section .summary-card.stat-card-late.active-filter { background: rgba(245, 158, 11, 0.15) !important; border: 3px solid #f59e0b !important; }
  body.dark-mode .summary-section .summary-card.stat-card-present.active-filter { background: rgba(16, 185, 129, 0.15) !important; border: 3px solid #10b981 !important; }
  body.dark-mode .summary-section .summary-card.stat-card-headcount.active-filter { background: rgba(14, 165, 233, 0.15) !important; border: 3px solid #0ea5e9 !important; }
  body.dark-mode .summary-arrow {
      background: rgba(255, 255, 255, 0.05) !important;
      border-color: rgba(255, 255, 255, 0.1) !important;
      color: #ffffff !important;
  }
  body.dark-mode .summary-arrow:hover {
      background: rgba(255, 255, 255, 0.1) !important;
      border-color: rgba(255, 255, 255, 0.2) !important;
  }

  /* Filters & Controls */
  body.dark-mode .offer-controls-card .search-input {
      background-color: rgba(255, 255, 255, 0.05) !important;
      color: #ffffff !important;
      border: none !important;
      border-color: transparent !important;
      outline: none !important;
      box-shadow: none !important;
  }
  body.dark-mode .offer-controls-card .search-input:focus {
      border: none !important;
      border-color: transparent !important;
      outline: none !important;
      box-shadow: none !important;
      background-color: rgba(255, 255, 255, 0.08) !important;
  }
  body.dark-mode .offer-controls-card .search-input::placeholder {
      color: rgba(255, 255, 255, 0.5) !important;
  }

  /* Dark mode — search bar: no white/light border (overrides Users.css / style_dashboard / desktop #ddd) */
  body.dark-mode .hr-offers-page .offer-controls-card .filter-group-search .search-input,
  body.dark-mode .hr-offers-page .offer-controls-card #offerFilterForm .search-input,
  body.dark-mode .content.hr-offers-page .search-input,
  body.dark-mode .hr-offers-page input#offerSearch {
      border: none !important;
      border-color: transparent !important;
      outline: none !important;
      box-shadow: none !important;
  }
  body.dark-mode .hr-offers-page .offer-controls-card .filter-group-search .search-input:focus,
  body.dark-mode .hr-offers-page .offer-controls-card #offerFilterForm .search-input:focus,
  body.dark-mode .content.hr-offers-page .search-input:focus,
  body.dark-mode .hr-offers-page input#offerSearch:focus {
      border: none !important;
      border-color: transparent !important;
      outline: none !important;
      box-shadow: none !important;
  }
  body.dark-mode .offer-controls-card .offer-status-filter {
      background-color: rgba(255, 255, 255, 0.05) !important;
      color: #ffffff !important;
      border-color: rgba(255, 255, 255, 0.1) !important;
      background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23ffffff' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e") !important;
      background-repeat: no-repeat !important;
      background-position: right 0.75rem center !important;
      background-size: 16px 12px !important;
  }
  body.dark-mode .offer-controls-card .offer-status-filter option {
      background-color: #1e1e24 !important;
      color: #ffffff !important;
  }
  body.dark-mode .offer-controls-card .btn-export-offers,
  body.dark-mode .offer-controls-card #bulkEmailOffersBtn {
      background: #2a2a2a !important;
      color: #ffffff !important;
      border: 1px solid #3d3d3d !important;
  }
  body.dark-mode .offer-controls-card .btn-export-offers:hover,
  body.dark-mode .offer-controls-card #bulkEmailOffersBtn:hover {
      background: #333333 !important;
  }
  body.dark-mode .offer-controls-card .btn-new-offer {
      background: rgba(255, 255, 255, 0.05) !important;
      border: 1px solid rgba(255, 255, 255, 0.1) !important;
      color: #ffffff !important;
  }
  body.dark-mode .offer-controls-card .btn-new-offer:hover {
      background: rgba(255, 255, 255, 0.1) !important;
      border-color: rgba(255, 255, 255, 0.2) !important;
      color: #ffffff !important;
  }
  body.dark-mode .offer-controls-card .btn-new-offer i,
  body.dark-mode .offer-controls-card .btn-new-offer span {
      color: #ffffff !important;
  }
  /* Rows limit — match Leave Management (no pseudo arrows / no layered backgrounds) */
  .hr-offers-page .offer-controls-card .page-size-selector::after,
  .hr-offers-page .offer-controls-card .page-size-selector::before {
      content: none !important;
      display: none !important;
  }
  body.dark-mode .hr-offers-page .offer-controls-card .page-size-selector,
  body.dark-mode .offer-controls-card .page-size-selector {
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
  body.dark-mode .hr-offers-page .offer-controls-card .page-size-selector select.users-limit-select,
  body.dark-mode .offer-controls-card .page-size-selector select.users-limit-select,
  body.dark-mode .offer-controls-card .page-size-selector #offer-rows-limit {
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
  body.dark-mode .offer-controls-card .page-size-selector:focus-within {
      border-color: #2a8c90 !important;
      box-shadow: 0 0 0 2px rgba(42, 140, 144, 0.2) !important;
  }
  body.dark-mode .offer-controls-card select.users-limit-select option,
  body.dark-mode .offer-controls-card #offer-rows-limit option {
      background-color: #1e1e24 !important;
      color: #ffffff !important;
  }

  /* Table badges */
  body.dark-mode .badge.bg-success {
      background-color: rgba(16, 185, 129, 0.15) !important;
      color: #34d399 !important;
      border: 1px solid rgba(16, 185, 129, 0.3) !important;
  }
  body.dark-mode .badge.bg-danger {
      background-color: rgba(239, 68, 68, 0.15) !important;
      color: #f87171 !important;
      border: 1px solid rgba(239, 68, 68, 0.3) !important;
  }
  body.dark-mode .badge.bg-info {
      background-color: rgba(14, 165, 233, 0.15) !important;
      color: #38bdf8 !important;
      border: 1px solid rgba(14, 165, 233, 0.3) !important;
  }
  body.dark-mode .badge.bg-warning {
      background-color: rgba(245, 158, 11, 0.15) !important;
      color: #fbbf24 !important;
      border: 1px solid rgba(245, 158, 11, 0.3) !important;
  }

  /* Offer Modal */
  body.dark-mode #offerModal .modal-content {
      background-color: #1e1e1e !important;
      color: #ffffff !important;
      border: 1px solid #333333 !important;
  }
  body.dark-mode #offerModal .modal-header {
      background: #1e1e1e !important;
      color: #ffffff !important;
      border-bottom: 1px solid #333333 !important;
  }
  body.dark-mode #offerModal .modal-body {
      background-color: #1e1e1e !important;
      color: #ffffff !important;
  }
  body.dark-mode #offerModal .form-label {
      color: #aaaaaa !important;
  }
  body.dark-mode #offerModal .form-control,
  body.dark-mode #offerModal .form-select {
      background-color: rgba(255, 255, 255, 0.05) !important;
      color: #ffffff !important;
      border: 1px solid rgba(255, 255, 255, 0.1) !important;
  }
  body.dark-mode #offerModal .form-control:focus,
  body.dark-mode #offerModal .form-select:focus {
      border-color: #ffffff !important;
      box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.1) !important;
  }
  body.dark-mode #offerModal .modal-footer {
      background-color: #181818 !important;
      border-top: 1px solid #333333 !important;
  }
  body.dark-mode #offerModal .btn-secondary {
      background: #2a2a2a !important;
      border: 1px solid #3d3d3d !important;
      color: #ffffff !important;
  }
  body.dark-mode #offerModal .btn-primary {
      background: #ffffff !important;
      color: #000000 !important;
      border: none !important;
      font-weight: 600 !important;
  }
  body.dark-mode #offerModal .btn-primary:hover {
      background: #dddddd !important;
  }

  /* Dark mode — table header lighter than row cards (match Attendance Report) */
  body.dark-mode .hr-offers-page .user-data-table thead th {
      background: #2c2c2e !important;
      color: rgba(255, 255, 255, 0.65) !important;
      border-bottom: 1px solid rgba(255, 255, 255, 0.12) !important;
      box-shadow: none !important;
  }
  body.dark-mode .hr-offers-page .user-data-table.unified-table tbody tr {
      background: rgba(44, 44, 46, 0.4) !important;
      border: 1px solid rgba(255, 255, 255, 0.12) !important;
      box-shadow: none !important;
  }
  body.dark-mode .hr-offers-page .user-data-table.unified-table tbody tr:hover {
      background: rgba(255, 255, 255, 0.06) !important;
      border-color: rgba(255, 255, 255, 0.15) !important;
  }
  body.dark-mode .hr-offers-page .user-data-table tbody td {
      background: transparent !important;
      color: rgba(255, 255, 255, 0.85) !important;
  }

  /* Pagination */
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

  /* Mobile Overrides */
  body.dark-mode .mobile-only-view h1 {
      color: #ffffff !important;
  }
  body.dark-mode .candidate-card {
      background: #1e1e1e !important;
      border-color: #333333 !important;
      color: #ffffff !important;
  }
  body.dark-mode .candidate-card h3 {
      color: #ffffff !important;
  }
  body.dark-mode .candidate-card .btn-light {
      background: #2a2a2a !important;
      border-color: #3d3d3d !important;
      color: #ffffff !important;
  }
  body.dark-mode .candidate-card .btn-light:hover {
      background: #333333 !important;
  }
  body.dark-mode #empty-state {
      background: #1e1e1e !important;
      border-color: #333333 !important;
      color: #ffffff !important;
  }
  body.dark-mode #empty-state p {
      color: #ffffff !important;
  }

  /* Custom Modal Enhancements */
  .application-modal {
      border-radius: 24px !important;
      border: none !important;
      overflow: hidden !important;
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25) !important;
  }
  .application-header {
      background: linear-gradient(135deg, #1e6063 0%, #2a8c90 100%) !important;
      color: white !important;
      padding: 24px 32px !important;
      border: none !important;
  }
  .application-icon {
      width: 48px !important;
      height: 48px !important;
      background: rgba(255, 255, 255, 0.2) !important;
      border-radius: 12px !important;
      display: flex !important;
      align-items: center !important;
      justify-content: center !important;
      font-size: 24px !important;
  }
  .application-form-container {
      padding: 32px !important;
      padding-top: 40px !important;
      background: #f8fafc !important;
  }
  .form-section {
      background: white !important;
      border-radius: 20px !important;
      padding: 24px !important;
      margin-bottom: 0px !important;
      border: 1px solid #e2e8f0 !important;
      box-shadow: 0 2px 4px rgba(0,0,0,0.02) !important;
  }
  .section-header {
      display: flex !important;
      align-items: center !important;
      gap: 10px !important;
      margin-bottom: 24px !important;
      color: #1e6063 !important;
      font-weight: 700 !important;
      font-size: 1.1rem !important;
  }
  .section-header i {
      font-size: 1.25rem !important;
      opacity: 0.8 !important;
  }

  /* Custom Floating Labels */
  .form-floating-custom {
      position: relative !important;
  }
  .form-floating-custom label {
      position: absolute !important;
      top: -10px !important;
      left: 12px !important;
      background: white !important;
      padding: 0 8px !important;
      font-size: 12px !important;
      font-weight: 700 !important;
      color: #64748b !important;
      z-index: 2 !important;
      transition: all 0.2s !important;
      white-space: nowrap !important;
      max-width: calc(100% - 24px) !important;
      overflow: hidden !important;
      text-overflow: ellipsis !important;
  }
  .form-floating-custom .form-control,
  .form-floating-custom .form-select {
      padding: 14px 16px 14px 44px !important;
      border-radius: 12px !important;
      border: 1.5px solid #e2e8f0 !important;
      font-size: 15px !important;
      color: #1e293b !important;
      height: 56px !important;
      transition: all 0.2s !important;
  }
  .form-floating-custom .form-control:focus,
  .form-floating-custom .form-select:focus {
      border-color: #2a8c90 !important;
      box-shadow: 0 0 0 4px rgba(42, 140, 144, 0.1) !important;
      outline: none !important;
  }
  .form-floating-custom .input-icon {
      position: absolute !important;
      left: 16px !important;
      top: 50% !important;
      transform: translateY(-50%) !important;
      color: #94a3b8 !important;
      font-size: 18px !important;
  }

  .form-floating-custom input[type="date"] {
      padding-left: 16px !important;
  }

  /* Dark Mode support for Offer Modal */
  body.dark-mode #offerModal .modal-content {
      background: #121212 !important;
      border: 1px solid #2d2d2d !important;
      color: #ffffff !important;
  }
  body.dark-mode #offerModal .application-header {
      background: #121212 !important;
      border-bottom: 1px solid #2d2d2d !important;
  }
  body.dark-mode #offerModal .application-icon {
      background: #1a1a1a !important;
      border: 1px solid #2d2d2d !important;
      color: #ffffff !important;
  }
  body.dark-mode #offerModal .application-form-container {
      background: #000000 !important;
  }
  body.dark-mode #offerModal .form-section {
      background: #121212 !important;
      border-color: #2d2d2d !important;
      box-shadow: none !important;
  }
  body.dark-mode #offerModal .section-header {
      color: #ffffff !important;
  }
  body.dark-mode #offerModal .form-floating-custom label {
      background: #121212 !important;
      color: #aaaaaa !important;
  }
  body.dark-mode #offerModal .form-floating-custom .form-control,
  body.dark-mode #offerModal .form-floating-custom .form-select {
      background-color: rgba(255, 255, 255, 0.05) !important;
      border-color: rgba(255, 255, 255, 0.1) !important;
      color: #ffffff !important;
  }
  body.dark-mode #offerModal .form-floating-custom .form-control:focus,
  body.dark-mode #offerModal .form-floating-custom .form-select:focus {
      border-color: #ffffff !important;
      box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.15) !important;
  }
  body.dark-mode #offerModal .form-floating-custom .form-select option {
      background-color: #121212 !important;
      color: #ffffff !important;
  }
  body.dark-mode #offerModal .form-floating-custom .input-icon {
      color: #888888 !important;
  }
  body.dark-mode #offerModal .modal-footer {
      background: #121212 !important;
      border-top: 1px solid #2d2d2d !important;
  }
  body.dark-mode #offerModal .modal-footer .btn-light {
      background: #1a1a1a !important;
      border: 1px solid #3d3d3d !important;
      color: #ffffff !important;
  }
  body.dark-mode #offerModal .modal-footer .btn-light:hover {
      background: #2d2d2d !important;
  }
  body.dark-mode #offerModal .modal-footer .btn-primary {
      background: #ffffff !important;
      color: #000000 !important;
      border: none !important;
      box-shadow: none !important;
  }
  body.dark-mode #offerModal .modal-footer .btn-primary:hover {
      background: #dddddd !important;
      color: #000000 !important;
  }

  /* Mobile UI — matches attendance report pattern */
  @media (max-width: 767.98px) {
      .content.hr-offers-page {
          padding: 10px !important;
      }

      .hr-offers-page .container-fluid {
          padding-top: 10px !important;
          padding-bottom: calc(72px + env(safe-area-inset-bottom, 0px)) !important;
      }

      .hr-offers-page .summary-wrapper,
      .hr-offers-page .summary-wrapper.pt-1,
      .hr-offers-page .summary-wrapper.mb-2 {
          padding-top: 0 !important;
          padding-bottom: 0 !important;
          margin-bottom: 8px !important;
      }

      .hr-offers-page .summary-section {
          padding: 4px 5px !important;
      }

      .hr-offers-page .offer-controls-card.glass-card,
      .hr-offers-page .offer-controls-card {
          background: transparent !important;
          border: none !important;
          box-shadow: none !important;
          padding: 0 !important;
          margin-top: 0 !important;
          margin-bottom: 8px !important;
      }

      .hr-offers-page .offer-controls-card .control-bar {
          gap: 0 !important;
          margin-top: 0 !important;
          margin-bottom: 0 !important;
      }

      .hr-offers-page .offer-controls-card #offerFilterForm {
          margin-bottom: 0 !important;
      }

      .hr-offers-page .offer-controls-card .control-right,
      .hr-offers-page .offer-controls-card .header-tools-wrapper {
          width: 100% !important;
      }

      .hr-offers-page .user-table-container {
          margin-top: 0 !important;
          padding: 0 !important;
          padding-top: 0 !important;
      }

      .hr-offers-page .user-data-table {
          border-spacing: 0 10px !important;
      }

      .hr-offers-page .offer-controls-card #offerFilterForm {
          display: grid !important;
          grid-template-columns: 1fr auto !important;
          grid-template-areas: "search limit" !important;
          gap: 10px !important;
          align-items: stretch !important;
          width: 100% !important;
      }

      .hr-offers-page .filter-group-search {
          grid-area: search !important;
          width: auto !important;
          min-width: 0 !important;
      }

      .hr-offers-page .offer-controls-card .filter-group-search .search-box {
          width: 100% !important;
          margin-bottom: 0 !important;
      }

      .hr-offers-page .filter-group-actions {
          display: contents !important;
      }

      .hr-offers-page .filter-group-actions .page-size-selector {
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

      .hr-offers-page .filter-group-actions .page-size-selector .users-limit-select {
          width: 100% !important;
          height: 100% !important;
          background: transparent !important;
          border: 0 !important;
          text-align: center !important;
          font-weight: 800 !important;
          font-size: 14px !important;
          padding: 0 !important;
      }

      .hr-offers-page .offer-controls-card .filter-group-search .search-input {
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

      .hr-offers-page .offer-controls-card .filter-group-search .search-icon {
          position: absolute !important;
          left: 14px !important;
          top: 50% !important;
          transform: translateY(-50%) !important;
          pointer-events: none !important;
          z-index: 2 !important;
      }

      .hr-offers-page .filter-group-actions .offer-status-filter,
      .hr-offers-page .filter-group-actions .btn-export-offers,
      .hr-offers-page .filter-group-actions #bulkEmailOffersBtn,
      .hr-offers-page .filter-group-actions .btn-new-offer {
          display: none !important;
      }

      .hr-offers-page .user-table-scroll-wrapper {
          display: none !important;
      }

      .hr-offers-page .offer-pagination-section {
          padding-bottom: 0.5rem !important;
      }

      /* Bottom nav */
      .offer-mobile-bottom-nav {
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

      .offer-mobile-nav-btn {
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
          cursor: pointer;
      }

      .offer-mobile-nav-btn i { font-size: 18px; }
      .offer-mobile-actions-btn { color: #ffa600; }
      .offer-mobile-status-btn { color: #2563eb; }
      .offer-mobile-new-btn { color: #03ac47; }
      .offer-mobile-nav-btn.active { background: rgba(15, 23, 42, 0.06); }

      /* Actions & status sheets */
      .offer-mobile-sheet {
          position: fixed;
          inset: 0;
          z-index: 1002;
          display: none;
          align-items: flex-end;
      }

      .offer-mobile-sheet.active { display: flex; }

      .offer-mobile-sheet-backdrop {
          position: absolute;
          inset: 0;
          background: rgba(15, 23, 42, 0.45);
      }

      .offer-mobile-sheet-panel {
          position: relative;
          width: 100%;
          background: #fff;
          border-radius: 20px 20px 0 0;
          padding: 16px 16px calc(16px + env(safe-area-inset-bottom, 0px));
      }

      .offer-mobile-sheet-panel h6 {
          margin: 0 0 12px;
          font-size: 0.95rem;
          font-weight: 800;
      }

      .offer-mobile-sheet-options {
          display: flex;
          flex-direction: column;
          gap: 8px;
      }

      .offer-mobile-sheet-option,
      .offer-mobile-status-option {
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

      .offer-mobile-status-option.selected {
          border-color: #2563eb;
          background: rgba(37, 99, 235, 0.08);
          color: #1d4ed8;
      }

      /* Sleek offer cards */
      .mobile-offers-container { padding: 0; margin-top: 0; }

      .mobile-offers-list {
          display: flex;
          flex-direction: column;
          gap: 8px;
      }

      .mobile-offer-card {
          background: #ffffff;
          border-radius: 12px;
          border: 1px solid #eef2f7;
          box-shadow: 0 1px 6px rgba(15, 23, 42, 0.05);
          overflow: hidden;
      }

      .mobile-offer-card.expanded {
          box-shadow: 0 4px 14px rgba(15, 23, 42, 0.08);
      }

      .offer-card-main {
          display: flex;
          align-items: center;
          gap: 10px;
          padding: 10px 12px;
          min-height: 52px;
      }

      .offer-card-info {
          flex: 1;
          min-width: 0;
          display: flex;
          flex-direction: column;
          gap: 3px;
      }

      .offer-card-meta-line {
          display: flex;
          align-items: center;
          gap: 6px;
          min-width: 0;
      }

      .offer-card-id {
          font-size: 0.72rem;
          font-weight: 700;
          color: #94a3b8;
          flex-shrink: 0;
      }

      .offer-card-date-dot { color: #cbd5e1; font-size: 0.7rem; }

      .offer-card-date {
          font-size: 0.72rem;
          font-weight: 600;
          color: #64748b;
          white-space: nowrap;
          overflow: hidden;
          text-overflow: ellipsis;
      }

      .offer-card-name-line {
          display: flex;
          align-items: center;
          flex-wrap: nowrap;
          gap: 8px;
          min-width: 0;
      }

      .offer-card-name {
          font-size: 0.92rem;
          font-weight: 700;
          color: #0f172a;
          overflow: hidden;
          text-overflow: ellipsis;
          white-space: nowrap;
      }

      .mobile-offer-card .status-badge {
          padding: 3px 8px !important;
          border-radius: 999px !important;
          font-size: 0.62rem !important;
          flex-shrink: 0;
      }

      .offer-card-expand-btn {
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
          cursor: pointer;
      }

      .offer-card-expand-btn.active { background: #227477; }

      .offer-card-detail-panel {
          display: none;
          border-top: 1px solid #f1f5f9;
          padding: 10px 12px 12px;
          background: #f8fafc;
      }

      .offer-mobile-detail-grid {
          display: grid;
          grid-template-columns: 1fr 1fr;
          gap: 8px 12px;
          margin-bottom: 10px;
      }

      .offer-mobile-detail-item.offer-detail-full { grid-column: 1 / -1; }

      .offer-mobile-detail-label {
          font-size: 0.65rem;
          font-weight: 700;
          text-transform: uppercase;
          color: #94a3b8;
      }

      .offer-mobile-detail-value {
          font-size: 0.82rem;
          font-weight: 600;
          color: #334155;
          word-break: break-word;
      }

      .offer-card-status-actions {
          display: flex;
          gap: 8px;
          margin-bottom: 10px;
      }

      .offer-card-status-btn {
          flex: 1;
          border: 0;
          background: transparent;
          padding: 6px;
          font-size: 1.1rem;
          cursor: pointer;
      }

      .offer-card-main .offer-card-select {
          display: inline-flex;
          align-items: center;
          justify-content: center;
          flex: 0 0 auto;
          margin: 0;
          padding: 0;
          border: none;
          background: transparent;
          cursor: pointer;
      }

      .offer-card-main .offer-card-select input[type="checkbox"] {
          width: 18px;
          height: 18px;
          margin: 0;
          cursor: pointer;
      }

      .offer-card-actions {
          display: flex;
          align-items: center;
          gap: 8px;
      }

      .offer-card-action-btn {
          flex: 1;
          text-align: center;
          padding: 10px;
          border-radius: 10px;
          font-size: 0.85rem;
          font-weight: 700;
          text-decoration: none;
          border: 1px solid #e2e8f0;
          background: #fff;
          color: #334155;
          cursor: pointer;
      }

      .offer-card-action-btn.offer-btn-email { color: #3b82f6 !important; }
      .offer-card-action-btn.offer-btn-edit { color: #6366f1 !important; }
      .offer-card-action-btn.offer-btn-delete { color: #c62828 !important; flex: 0 0 auto; min-width: 44px; }

      body.dark-mode .mobile-offer-card {
          background: rgba(18, 18, 18, 0.85);
          border-color: rgba(255, 255, 255, 0.1);
      }

      body.dark-mode .offer-card-name { color: #f8fafc; }
      body.dark-mode .offer-card-detail-panel { background: rgba(255, 255, 255, 0.03); }
      body.dark-mode .offer-mobile-detail-value { color: #e2e8f0; }
      body.dark-mode .offer-mobile-bottom-nav {
          background: rgba(22, 22, 24, 0.92);
          border-top-color: rgba(255, 255, 255, 0.08);
      }

      body.dark-mode .offer-mobile-sheet-panel { background: #1e1e24; }
      body.dark-mode .offer-mobile-sheet-option,
      body.dark-mode .offer-mobile-status-option {
          background: rgba(255, 255, 255, 0.05);
          border-color: rgba(255, 255, 255, 0.1);
          color: #e2e8f0;
      }

      body.dark-mode .hr-offers-page .offer-controls-card .filter-group-search .search-input {
          background: rgba(255, 255, 255, 0.05) !important;
          border: none !important;
          border-color: transparent !important;
          outline: none !important;
          box-shadow: none !important;
          color: #fff !important;
      }

      body.dark-mode .hr-offers-page .filter-group-actions .page-size-selector {
          background: rgba(255, 255, 255, 0.05) !important;
          border: 1px solid rgba(255, 255, 255, 0.1) !important;
          box-shadow: none !important;
          overflow: hidden !important;
      }

      body.dark-mode .hr-offers-page .filter-group-actions .page-size-selector .users-limit-select,
      body.dark-mode .hr-offers-page .filter-group-actions .page-size-selector #offer-rows-limit {
          color: #ffffff !important;
          background-color: transparent !important;
          background-image: none !important;
          border: 0 !important;
          backdrop-filter: none !important;
          -webkit-appearance: menulist !important;
          appearance: menulist !important;
          font-weight: 500 !important;
      }

      /* Generate Offer modal — above fixed mobile header (navbar z-index 99999) */
      #offerModal.modal {
          z-index: 100200 !important;
          padding-top: calc(56px + env(safe-area-inset-top, 0px)) !important;
          padding-bottom: calc(12px + env(safe-area-inset-bottom, 0px)) !important;
      }
      body.offer-modal-open .modal-backdrop.show {
          z-index: 100190 !important;
      }
      body.offer-modal-open .offer-mobile-bottom-nav {
          display: none !important;
      }
      #offerModal .modal-dialog {
          margin: 0.5rem auto !important;
          max-width: calc(100vw - 1rem) !important;
          max-height: calc(100vh - 72px - env(safe-area-inset-top, 0px) - env(safe-area-inset-bottom, 0px)) !important;
          align-items: flex-start !important;
          min-height: 0 !important;
      }
      #offerModal .modal-dialog.modal-dialog-centered {
          min-height: calc(100% - 1rem) !important;
      }
      #offerModal .modal-content.application-modal {
          max-height: 100% !important;
          display: flex !important;
          flex-direction: column !important;
          border-radius: 16px !important;
      }
      #offerModal .modal-body {
          overflow-y: auto !important;
          -webkit-overflow-scrolling: touch;
          flex: 1 1 auto !important;
          min-height: 0 !important;
      }
      #offerModal .application-header {
          padding: 16px 18px !important;
          flex-shrink: 0 !important;
      }
      #offerModal .application-form-container {
          padding: 16px !important;
          padding-top: 20px !important;
      }
      #offerModal .modal-footer {
          flex-shrink: 0 !important;
          display: flex !important;
          flex-direction: column-reverse !important;
          gap: 10px !important;
          padding: 12px 16px calc(12px + env(safe-area-inset-bottom, 0px)) !important;
      }
      #offerModal .modal-footer .btn {
          width: 100% !important;
          min-height: 48px !important;
          margin: 0 !important;
      }
  }

  @media (min-width: 768px) {
      .offer-mobile-bottom-nav,
      .offer-mobile-sheet {
          display: none !important;
      }

      /* Tighter vertical spacing — match Leave Management */
      .hr-offers-page .container-fluid {
          padding-top: 6px !important;
      }

      .hr-offers-page .summary-wrapper,
      .hr-offers-page .summary-wrapper.pt-1,
      .hr-offers-page .summary-wrapper.mb-2 {
          padding-top: 0 !important;
          margin-bottom: 8px !important;
      }

      .hr-offers-page .summary-section {
          padding: 4px 5px !important;
      }

      .hr-offers-page .offer-controls-card.glass-card,
      .hr-offers-page .offer-controls-card {
          padding: 0 !important;
          margin-top: 0 !important;
          margin-bottom: 8px !important;
      }

      .hr-offers-page .offer-controls-card .control-bar {
          margin-top: 0 !important;
          margin-bottom: 0 !important;
      }

      .hr-offers-page .offer-controls-card #offerFilterForm {
          margin-bottom: 0 !important;
      }

      .hr-offers-page .user-table-container {
          margin-top: 0 !important;
          padding-top: 0 !important;
      }

      .hr-offers-page .user-data-table {
          border-spacing: 0 10px !important;
      }
  }

  /* Offer create modal — desktop + fallback stacking */
  #offerModal.modal { z-index: 100200 !important; }

  /* Letter editor modals (shared with Employees page) */
  #appointmentLetterModal.modal { z-index: 100100 !important; }
  #signatureModal.modal { z-index: 100110 !important; }
  .swal2-container { z-index: 100200 !important; }
  body.letter-preview-open #letterPreviewModal.modal { z-index: 100120 !important; }
  body.letter-preview-open .modal-backdrop.show:last-of-type { z-index: 100115 !important; }
  .letter-preview-dialog { max-width: 96vw !important; width: 96vw !important; margin: 2vh auto !important; }
  .letter-preview-dialog .modal-content { height: 92vh; max-height: 92vh; display: flex; flex-direction: column; }
  .letter-preview-dialog .modal-body { flex: 1 1 auto; min-height: 0; overflow: hidden; }
  .letter-preview-body { background: #e8ecef; height: 100%; min-height: 0; }
  .letter-preview-frame { display: block; width: 100%; height: 100% !important; min-height: 0 !important; border: none; background: #e8ecef; }
  .letter-preview-loading { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; background: #e8ecef; z-index: 2; }
  body.dark-mode .letter-preview-body,
  body.dark-mode .letter-preview-loading,
  body.dark-mode .letter-preview-frame,
  html.dark-mode .letter-preview-body,
  html.dark-mode .letter-preview-loading,
  html.dark-mode .letter-preview-frame { background: #1a1a1a; }
  body.dark-mode #appointmentLetterModal .modal-content,
  body.dark-mode #letterPreviewModal .modal-content,
  html.dark-mode #appointmentLetterModal .modal-content,
  html.dark-mode #letterPreviewModal .modal-content { background: #1a1a1a; color: #e8e8e8; border: 1px solid #3d3d3d; }
  body.dark-mode #letterPreviewModal .modal-header,
  html.dark-mode #letterPreviewModal .modal-header { background: #242424 !important; border-bottom: 1px solid #3d3d3d !important; color: #f5f5f5 !important; }
  body.dark-mode #letterPreviewModal .modal-footer,
  html.dark-mode #letterPreviewModal .modal-footer { background: #1a1a1a !important; border-top: 1px solid #3d3d3d !important; }
</style>
<link rel="stylesheet" href="./assets/css/mobile_list_top_chrome.css?v=<?php echo time(); ?>" />

<?php include('header.php'); ?>

<div class="content hr-offers-page">
  <div class="container-fluid">
    <div class="col-12">

        <div class="summary-wrapper pt-1 mb-2">
            <button type="button" class="summary-arrow left" id="summaryLeft" aria-label="Scroll summary left">‹</button>
            <div class="summary-section" id="summaryScroll">
                <div class="summary-card stat-card-late">
                    <span class="summary-text" style="font-weight: 600;">Draft : <?php echo (int)$offerStats['draft']; ?></span>
                </div>
                <div class="summary-card stat-card-present">
                    <span class="summary-text" style="font-weight: 600;">Sent : <?php echo (int)$offerStats['sent']; ?>
                        (<?php echo (int)$offerStats['sent_percent']; ?>%)</span>
                </div>
                <div class="summary-card stat-card-headcount">
                    <span class="summary-text" style="font-weight: 600;">Accepted : <?php echo (int)$offerStats['accepted']; ?></span>
                </div>
            </div>
            <button type="button" class="summary-arrow right" id="summaryRight" aria-label="Scroll summary right">›</button>
        </div>

        <div class="glass-card p-4 mb-4 offer-controls-card">
          <div class="control-bar d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div class="control-right d-flex align-items-center justify-content-end gap-2 flex-grow-1 header-tools-wrapper">
              <div id="offerFilterForm">
                <div class="filter-group filter-group-search">
                  <div class="search-box">
                    <svg class="search-icon" width="18" height="18" viewBox="0 0 18 18" fill="none" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); pointer-events: none; z-index: 1;">
                      <circle cx="8" cy="8" r="6" stroke="#999" stroke-width="1.5"/>
                      <path d="M12.5 12.5L16 16" stroke="#999" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                    <input type="text" class="search-input" id="offerSearch" placeholder="Search users..." style="padding-left: 44px;">
                  </div>
                </div>
                <div class="filter-group filter-group-actions">
                  <select class="form-select offer-status-filter" id="offerStatusFilter" title="Status">
                    <option value="">Status</option>
                    <option value="Draft">Draft</option>
                    <option value="Sent">Sent</option>
                    <option value="Accepted">Accepted</option>
                    <option value="Rejected">Rejected</option>
                  </select>
                  <button type="button" class="btn-export-offers" id="exportOffersBtn">
                    <i class="bi bi-download"></i> <span class="btn-text">Export CSV</span>
                  </button>
                  <button type="button" class="btn-filter" id="bulkEmailOffersBtn">
                    <i class="bi bi-envelope"></i> <span class="btn-text">Bulk Email</span>
                  </button>
                  <button type="button" class="btn-column-visibility btn-new-offer" onclick="openOfferModal()">
                    <i class="bi bi-plus-lg"></i> <span class="btn-text">New Offer Letter</span>
                  </button>
                  <div class="page-size-selector">
                    <select id="offer-rows-limit" class="users-limit-select">
                      <option value="10" selected>10</option>
                      <option value="50">50</option>
                      <option value="100">100</option>
                      <option value="200">200</option>
                    </select>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="table-wrap">
          <div class="user-table-container">
            <div class="user-table-scroll-wrapper d-none d-md-block">
              <table class="user-data-table unified-table">
                <thead>
                  <tr>
                    <th><input type="checkbox" id="selectAllOffers"></th>
                    <th>Candidate Name</th>
                    <th>Position</th>
                    <th>Salary (Monthly)</th>
                    <th>Joining Date</th>
                    <th>Status</th>
                    <th>Emailed At</th>
                    <th>Created At</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody id="offerTableBody">
                  <!-- AJAX loaded -->
                </tbody>
              </table>
            </div>

            <!-- Mobile: sleek cards (matches attendance report) -->
            <div class="mobile-offers-container d-block d-md-none">
              <div class="mobile-offers-list" id="mobileOffersList"></div>
              <div class="mobile-offer-card text-center py-5 text-muted d-none" id="mobileOffersEmpty">
                <i class="bi bi-inbox fs-1 mb-2 d-block"></i>
                No offer letters found.
              </div>
            </div>
          </div>
          <div class="offer-pagination-section py-4">
            <div class="d-flex flex-column align-items-center gap-3">
              <div class="pagination-info text-muted" id="offerPaginationInfo" style="font-weight: 500; font-size: 0.95rem;">
                Showing 0 to 0 of 0 entries
              </div>
              <div class="pagination-controls d-flex gap-2" id="offerPaginationControls"></div>
            </div>
          </div>
        </div>
    </div>
  </div>
</div>

<!-- Mobile bottom toolbar -->
<nav class="offer-mobile-bottom-nav d-md-none" aria-label="Offer actions">
    <button type="button" class="offer-mobile-nav-btn offer-mobile-actions-btn" id="offerMobileActionsBtn">
        <i class="bi bi-funnel-fill"></i>
        <span>Actions</span>
    </button>
    <button type="button" class="offer-mobile-nav-btn offer-mobile-status-btn" id="offerMobileStatusBtn">
        <i class="bi bi-ui-checks-grid"></i>
        <span>Status</span>
    </button>
    <button type="button" class="offer-mobile-nav-btn offer-mobile-new-btn" id="offerMobileNewBtn">
        <i class="bi bi-plus-lg"></i>
        <span>New</span>
    </button>
</nav>

<!-- Mobile actions sheet -->
<div id="offerMobileActionsSheet" class="offer-mobile-sheet d-md-none" aria-hidden="true">
    <div class="offer-mobile-sheet-backdrop" id="offerMobileActionsBackdrop"></div>
    <div class="offer-mobile-sheet-panel" role="dialog" aria-modal="true">
        <h6>Offer Actions</h6>
        <div class="offer-mobile-sheet-options">
            <button type="button" class="offer-mobile-sheet-option" id="offerMobileExportBtn">
                <i class="bi bi-download me-2"></i> Export CSV
            </button>
            <button type="button" class="offer-mobile-sheet-option" id="offerMobileBulkEmailBtn">
                <i class="bi bi-envelope me-2"></i> Bulk Email
            </button>
            <button type="button" class="offer-mobile-sheet-option" id="offerMobileSelectAllBtn">
                <i class="bi bi-check2-square me-2"></i> Select All on Page
            </button>
        </div>
    </div>
</div>

<!-- Mobile status picker -->
<div id="offerMobileStatusSheet" class="offer-mobile-sheet d-md-none" aria-hidden="true">
    <div class="offer-mobile-sheet-backdrop" id="offerMobileStatusBackdrop"></div>
    <div class="offer-mobile-sheet-panel" role="dialog" aria-modal="true">
        <h6>Filter by Status</h6>
        <div class="offer-mobile-sheet-options">
            <button type="button" class="offer-mobile-status-option" data-status="">All Status</button>
            <button type="button" class="offer-mobile-status-option" data-status="Draft">Draft</button>
            <button type="button" class="offer-mobile-status-option" data-status="Sent">Sent</button>
            <button type="button" class="offer-mobile-status-option" data-status="Accepted">Accepted</button>
            <button type="button" class="offer-mobile-status-option" data-status="Rejected">Rejected</button>
        </div>
    </div>
</div>

<!-- Offer Modal -->
<div class="modal fade" id="offerModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content application-modal">
      <div class="modal-header application-header">
        <div class="d-flex align-items-center gap-3">
            <div class="application-icon">
                <i class="bi bi-file-earmark-plus-fill"></i>
            </div>
            <div>
                <h5 class="modal-title fw-bold mb-0" id="offerModalLabel">Generate New Offer Letter</h5>
            </div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0">
        <form id="offerForm">
          <input type="hidden" id="offer_id" name="id">
          <input type="hidden" id="hidden_user_id" name="user_id">
          <input type="hidden" name="action" value="upsert">
          
          <div class="application-form-container">
            <div class="form-section mb-0">
                <div class="section-header">
                    <i class="bi bi-file-earmark-text"></i>
                    <span>Candidate & Job Details</span>
                </div>
                <div class="row g-4">
                    <div class="col-md-6">
                      <div class="form-floating-custom">
                        <select class="form-select" id="user_select" required>
                          <option value="">Loading users...</option>
                        </select>
                        <label for="user_select">Select Created User</label>
                        <i class="bi bi-person-circle input-icon"></i>
                      </div>
                    </div>
                    
                    <div class="col-md-6">
                      <div class="form-floating-custom">
                        <input type="text" class="form-control" name="candidate_name" id="offer_candidate_name" readonly required>
                        <label for="offer_candidate_name">Candidate Name</label>
                        <i class="bi bi-person input-icon"></i>
                      </div>
                    </div>
                    
                    <div class="col-md-6">
                      <div class="form-floating-custom">
                        <input type="email" class="form-control" name="email" readonly required>
                        <label for="offer_email">Email Address</label>
                        <i class="bi bi-envelope input-icon"></i>
                      </div>
                    </div>
                    
                    <div class="col-md-6">
                      <div class="form-floating-custom">
                        <input type="text" class="form-control" name="phone" readonly required>
                        <label for="offer_phone">Phone Number</label>
                        <i class="bi bi-telephone input-icon"></i>
                      </div>
                    </div>
                    
                    <div class="col-md-6">
                      <div class="form-floating-custom">
                        <input type="text" class="form-control" name="position" readonly required>
                        <label for="offer_position">Position / Designation</label>
                        <i class="bi bi-briefcase input-icon"></i>
                      </div>
                    </div>
                    
                    <div class="col-md-6">
                      <div class="form-floating-custom">
                        <input type="text" class="form-control" name="department" readonly>
                        <label for="offer_department">Department</label>
                        <i class="bi bi-building input-icon"></i>
                      </div>
                    </div>
                    
                    <div class="col-md-6">
                      <div class="form-floating-custom">
                        <input type="number" step="0.01" class="form-control" name="monthly_salary" readonly required>
                        <label for="offer_monthly_salary">Monthly CTC (₹)</label>
                        <i class="bi bi-currency-rupee input-icon"></i>
                      </div>
                    </div>
                    
                    <div class="col-md-6">
                      <div class="form-floating-custom">
                        <input type="date" class="form-control" name="joining_date" readonly required>
                        <label for="offer_joining_date">Joining Date</label>
                      </div>
                    </div>
                    
                    <div class="col-md-12">
                      <div class="form-floating-custom">
                        <input type="text" class="form-control" name="reporting_manager" readonly>
                        <label for="offer_reporting_manager">Reporting Manager</label>
                        <i class="bi bi-person-badge input-icon"></i>
                      </div>
                    </div>
                </div>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer border-0 p-4">
        <button type="button" class="btn btn-light px-4 py-2 rounded-3 fw-bold" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary px-5 py-2 rounded-3 fw-bold" onclick="saveOffer()" style="background: linear-gradient(135deg, #1e6063 0%, #2a8c90 100%); border: none; box-shadow: 0 4px 12px rgba(42, 140, 144, 0.3);">
            <i class="bi bi-cloud-check-fill me-2"></i> Save Offer Letter
        </button>
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
<!-- jQuery, Bootstrap, Summernote already loaded via htmlopen.php — do not reload jQuery or Summernote breaks -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="assets/js/summary-cards.js?v=<?php echo time(); ?>"></script>
<script src="hrmain.js?v=<?php echo time(); ?>"></script>
<script>
function toggleMobileOfferCard(btn) {
    const card = btn.closest('.mobile-offer-card');
    const panel = card?.querySelector('.offer-card-detail-panel');
    const icon = btn.querySelector('i');
    if (!card || !panel) return;

    const isOpen = card.classList.contains('expanded');
    document.querySelectorAll('.mobile-offer-card.expanded').forEach(c => {
        if (c === card) return;
        c.classList.remove('expanded');
        const p = c.querySelector('.offer-card-detail-panel');
        if (p) p.style.display = 'none';
        const b = c.querySelector('.offer-card-expand-btn');
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

let usersList = [];
let allOffers = [];
let selectedOfferIds = new Set();
let offerCurrentPage = 1;
let offerTotalPages = 1;
let offerTotalEntries = 0;

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, ch => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    }[ch]));
}

function escapeJs(value) {
    return String(value ?? '').replace(/\\/g, '\\\\').replace(/'/g, "\\'");
}

function formatCurrency(value) {
    const amount = parseFloat(value || 0);
    return '₹' + amount.toLocaleString('en-IN');
}

function formatDateTime(value) {
    return value ? escapeHtml(value) : '<span class="text-muted">Not emailed</span>';
}

function canMarkOfferDecision(offer) {
    return Boolean(offer && offer.emailed_at);
}

function getOfferQueryParams(page = offerCurrentPage) {
    return {
        action: 'fetch_all',
        search: $('#offerSearch').val() || '',
        status: $('#offerStatusFilter').val() || '',
        limit: $('#offer-rows-limit').val() || 10,
        page
    };
}

function renderOffers(offers) {
    let html = '';
    let mobileHtml = '';

    offers.forEach(offer => {
        const isChecked = selectedOfferIds.has(String(offer.id)) ? 'checked' : '';
        const userId = parseInt(offer.user_id || 0, 10);
        const canUpdateStatus = canMarkOfferDecision(offer);
        const statusBtnClass = canUpdateStatus ? '' : 'is-disabled';
        const statusBtnTitle = canUpdateStatus ? '' : 'Offer must be emailed first';
        const editLink = userId > 0
            ? `<a href="javascript:void(0)" onclick="editOfferLetterContent(${userId})" class="action-icon" style="color:#6366f1 !important;" title="Edit offer letter content"><i class="bi bi-pencil-square"></i></a>`
            : '';
        const candidateName = escapeHtml(offer.candidate_name);
        const candidateNameJs = escapeJs(offer.candidate_name);

        html += `<tr>
            <td><input type="checkbox" class="offer-row-checkbox" value="${offer.id}" ${isChecked}></td>
            <td><strong>${candidateName}</strong><br><small>${escapeHtml(offer.email)}</small></td>
            <td>${escapeHtml(offer.position)}</td>
            <td>${formatCurrency(offer.monthly_salary)}</td>
            <td>${escapeHtml(offer.joining_date)}</td>
            <td>
              <span class="badge ${getStatusBadge(offer.offer_status)}">${escapeHtml(offer.offer_status)}</span>
              <div class="mt-1 d-flex justify-content-center gap-1">
                <button type="button" class="offer-action-link text-success ${statusBtnClass}" onclick="updateOfferStatus(${offer.id}, 'Accepted')" title="${statusBtnTitle || 'Mark Accepted'}"><i class="bi bi-check-circle"></i></button>
                <button type="button" class="offer-action-link text-danger ${statusBtnClass}" onclick="updateOfferStatus(${offer.id}, 'Rejected')" title="${statusBtnTitle || 'Mark Rejected'}"><i class="bi bi-x-circle"></i></button>
              </div>
            </td>
            <td>${formatDateTime(offer.emailed_at)}${offer.emailed_by ? `<br><small>${escapeHtml(offer.emailed_by)}</small>` : ''}</td>
            <td>${escapeHtml(offer.created_at)}</td>
            <td>
              <div class="action-icons">
                ${editLink}
                <a href="javascript:void(0)" onclick="emailOffer(${offer.id}, '${candidateNameJs}')" class="action-icon" style="color:#3b82f6 !important;" title="Email Offer Letter"><i class="bi bi-envelope"></i></a>
                <a href="javascript:void(0)" onclick="deleteOffer(${offer.id})" class="action-icon delete" title="Delete"><i class="bi bi-trash"></i></a>
              </div>
            </td>
        </tr>`;

        mobileHtml += `
        <div class="mobile-offer-card" data-offer-id="${offer.id}">
            <div class="offer-card-main">
                <label class="offer-card-select" title="Select for bulk email">
                    <input type="checkbox" class="offer-row-checkbox" value="${offer.id}" ${isChecked}>
                </label>
                <div class="offer-card-info">
                    <div class="offer-card-meta-line">
                        <span class="offer-card-id">#${offer.id}</span>
                        <span class="offer-card-date-dot">·</span>
                        <span class="offer-card-date">${escapeHtml(offer.joining_date || '—')}</span>
                    </div>
                    <div class="offer-card-name-line">
                        <span class="offer-card-name">${candidateName}</span>
                        <span class="badge status-badge ${getStatusBadge(offer.offer_status)}">${escapeHtml(offer.offer_status)}</span>
                    </div>
                </div>
                <button type="button" class="offer-card-expand-btn" aria-expanded="false"
                    onclick="toggleMobileOfferCard(this)" title="Show details">
                    <i class="bi bi-chevron-down"></i>
                </button>
            </div>
            <div class="offer-card-detail-panel">
                <div class="offer-mobile-detail-grid">
                    <div class="offer-mobile-detail-item">
                        <span class="offer-mobile-detail-label">Position</span>
                        <span class="offer-mobile-detail-value">${escapeHtml(offer.position)}</span>
                    </div>
                    <div class="offer-mobile-detail-item">
                        <span class="offer-mobile-detail-label">Salary</span>
                        <span class="offer-mobile-detail-value">${formatCurrency(offer.monthly_salary)}</span>
                    </div>
                    <div class="offer-mobile-detail-item offer-detail-full">
                        <span class="offer-mobile-detail-label">Email</span>
                        <span class="offer-mobile-detail-value">${escapeHtml(offer.email)}</span>
                    </div>
                    <div class="offer-mobile-detail-item">
                        <span class="offer-mobile-detail-label">Emailed At</span>
                        <span class="offer-mobile-detail-value">${offer.emailed_at ? escapeHtml(offer.emailed_at) : 'Not emailed'}${offer.emailed_by ? ' · ' + escapeHtml(offer.emailed_by) : ''}</span>
                    </div>
                    <div class="offer-mobile-detail-item">
                        <span class="offer-mobile-detail-label">Created At</span>
                        <span class="offer-mobile-detail-value">${escapeHtml(offer.created_at)}</span>
                    </div>
                </div>
                <div class="offer-card-status-actions">
                    <button type="button" class="offer-card-status-btn text-success ${statusBtnClass}" onclick="updateOfferStatus(${offer.id}, 'Accepted')" title="${statusBtnTitle || 'Mark Accepted'}"><i class="bi bi-check-circle"></i></button>
                    <button type="button" class="offer-card-status-btn text-danger ${statusBtnClass}" onclick="updateOfferStatus(${offer.id}, 'Rejected')" title="${statusBtnTitle || 'Mark Rejected'}"><i class="bi bi-x-circle"></i></button>
                </div>
                <div class="offer-card-actions">
                    ${userId > 0 ? `<button type="button" class="offer-card-action-btn offer-btn-edit" onclick="editOfferLetterContent(${userId})">Edit</button>` : ''}
                    <button type="button" class="offer-card-action-btn offer-btn-email" onclick="emailOffer(${offer.id}, '${candidateNameJs}')"><i class="bi bi-envelope"></i> Email</button>
                    <button type="button" class="offer-card-action-btn offer-btn-delete" onclick="deleteOffer(${offer.id})"><i class="bi bi-trash"></i></button>
                </div>
            </div>
        </div>`;
    });

    $('#offerTableBody').html(html || '<tr><td colspan="9" class="text-center">No offer letters found</td></tr>');
    $('#mobileOffersList').html(mobileHtml);

    if (offers.length > 0) {
        $('#mobileOffersEmpty').addClass('d-none');
        $('#mobileOffersList').removeClass('d-none');
    } else {
        $('#mobileOffersEmpty').removeClass('d-none');
        $('#mobileOffersList').addClass('d-none');
    }

    $('.offer-row-checkbox').on('change', function() {
        if (this.checked) selectedOfferIds.add(String(this.value));
        else selectedOfferIds.delete(String(this.value));
        syncSelectAllOffers();
    });
    syncSelectAllOffers();
}

function renderOfferPagination() {
    const limit = parseInt($('#offer-rows-limit').val(), 10) || 10;
    const start = offerTotalEntries > 0 ? ((offerCurrentPage - 1) * limit) + 1 : 0;
    const end = Math.min(offerCurrentPage * limit, offerTotalEntries);
    $('#offerPaginationInfo').text(`Showing ${start} to ${end} of ${offerTotalEntries} entries`);

    let html = '';
    const prevDisabled = offerCurrentPage <= 1 ? 'disabled' : '';
    const nextDisabled = offerCurrentPage >= offerTotalPages ? 'disabled' : '';
    html += `<button type="button" class="page-btn ${prevDisabled}" onclick="goToOfferPage(${offerCurrentPage - 1})">←</button>`;
    const startPage = Math.max(1, offerCurrentPage - 2);
    const endPage = Math.min(offerTotalPages, offerCurrentPage + 2);
    for (let i = startPage; i <= endPage; i++) {
        html += `<button type="button" class="page-btn ${offerCurrentPage === i ? 'active' : ''}" onclick="goToOfferPage(${i})">${i}</button>`;
    }
    html += `<button type="button" class="page-btn ${nextDisabled}" onclick="goToOfferPage(${offerCurrentPage + 1})">→</button>`;
    $('#offerPaginationControls').html(html);
}

function goToOfferPage(page) {
    if (page < 1 || page > offerTotalPages || page === offerCurrentPage) return;
    loadOffers(page);
}

function syncSelectAllOffers() {
    const visibleCheckboxes = $('.offer-row-checkbox');
    const allVisibleChecked = visibleCheckboxes.length > 0 && visibleCheckboxes.toArray().every(cb => cb.checked);
    $('#selectAllOffers').prop('checked', allVisibleChecked);
}

function resetOfferSelection() {
    selectedOfferIds.clear();
    $('#selectAllOffers').prop('checked', false);
}

async function postJson(url, data) {
    const body = new URLSearchParams();
    Object.keys(data).forEach(key => body.append(key, data[key]));
    const response = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
        body: body.toString()
    });
    const text = await response.text();
    let parsed = null;
    try {
        parsed = text ? JSON.parse(text) : null;
    } catch (e) {
        throw new Error(text.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim() || `Request failed with HTTP ${response.status}.`);
    }
    if (!response.ok || !parsed || parsed.status !== 'success') {
        throw new Error((parsed && parsed.message) ? parsed.message : `Request failed with HTTP ${response.status}.`);
    }
    return parsed;
}

function formatCsvDate(value) {
    if (!value) return '';
    const raw = String(value).trim();
    const match = raw.match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}):(\d{2}))?/);
    if (!match) return raw;
    const [, y, m, d, hh, mm, ss] = match;
    const datePart = `${d}-${m}-${y}`;
    return hh ? `${datePart} ${hh}:${mm}:${ss || '00'}` : datePart;
}

function csvCell(value, forceText = false) {
    const text = forceText ? formatCsvDate(value) : String(value ?? '');
    const escaped = text.replace(/"/g, '""');
    return forceText ? `="${escaped}"` : `"${escaped}"`;
}

async function exportOffersCsv() {
    try {
        const params = getOfferQueryParams(1);
        params.limit = 2000;
        params.page = 1;
        const res = await $.getJSON('offer_letter_action.php', params);
        const dateColumns = new Set([5, 7, 9]);
        const rows = (res.data || []).map(offer => [
            offer.id,
            offer.candidate_name,
            offer.email,
            offer.position,
            offer.monthly_salary,
            offer.joining_date,
            offer.offer_status,
            offer.emailed_at || '',
            offer.emailed_by || '',
            offer.created_at
        ]);
        const header = ['ID', 'Candidate Name', 'Email', 'Position', 'Monthly Salary', 'Joining Date', 'Status', 'Emailed At', 'Emailed By', 'Created At'];
        const toCsvRow = row => row.map((value, index) => csvCell(value, dateColumns.has(index))).join(',');
        const csv = '\uFEFF' + [header, ...rows].map(toCsvRow).join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'offer_letters.csv';
    link.click();
    URL.revokeObjectURL(link.href);
    } catch (err) {
        Swal.fire('Export failed', err.message || 'Could not export offer letters.', 'error');
    }
}

async function sendOfferEmail(id) {
    return postJson('mail_offer_letter.php', { offer_id: id });
}

function updateOfferStatus(id, status) {
    const offer = allOffers.find(item => String(item.id) === String(id));
    if (!canMarkOfferDecision(offer)) {
        Swal.fire('Email required', 'Please send the offer email first, then mark it as Accepted or Rejected.', 'info');
        return;
    }
    Swal.fire({
        title: `Mark as ${status}?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, update'
    }).then(async result => {
        if (!result.isConfirmed) return;
        try {
            const res = await postJson('offer_letter_action.php', { action: 'update_status', id, status });
            Swal.fire('Updated', res.message, 'success');
            loadOffers(offerCurrentPage);
        } catch (err) {
            Swal.fire('Error', err.message || 'Failed to update status.', 'error');
        }
    });
}

$(document).ready(function() {
    loadOffers();

    let searchTimer;
    $('#offerSearch').on('input', function() {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => {
            resetOfferSelection();
            loadOffers(1);
        }, 250);
    });
    $('#offerStatusFilter, #offer-rows-limit').on('change', function() {
        resetOfferSelection();
        loadOffers(1);
    });

    // Setup summary cards as filters
    const cardDraft = document.querySelector('.summary-section .stat-card-late');
    const cardSent = document.querySelector('.summary-section .stat-card-present');
    const cardAccepted = document.querySelector('.summary-section .stat-card-headcount');
    const statusFilter = document.getElementById('offerStatusFilter');
    
    if (statusFilter) {
        function updateCardHighlights() {
            const currentVal = statusFilter.value;
            [cardDraft, cardSent, cardAccepted].forEach(c => c?.classList.remove('active-filter'));
            
            if (currentVal === 'Draft') {
                cardDraft?.classList.add('active-filter');
            } else if (currentVal === 'Sent') {
                cardSent?.classList.add('active-filter');
            } else if (currentVal === 'Accepted') {
                cardAccepted?.classList.add('active-filter');
            }
        }
        
        updateCardHighlights();
        $(statusFilter).on('change', updateCardHighlights);
        
        cardDraft?.addEventListener('click', function() {
            const isSelected = statusFilter.value === 'Draft';
            statusFilter.value = isSelected ? '' : 'Draft';
            $(statusFilter).trigger('change');
        });
        cardSent?.addEventListener('click', function() {
            const isSelected = statusFilter.value === 'Sent';
            statusFilter.value = isSelected ? '' : 'Sent';
            $(statusFilter).trigger('change');
        });
        cardAccepted?.addEventListener('click', function() {
            const isSelected = statusFilter.value === 'Accepted';
            statusFilter.value = isSelected ? '' : 'Accepted';
            $(statusFilter).trigger('change');
        });
    }
    $('#exportOffersBtn').on('click', exportOffersCsv);
    $('#selectAllOffers').on('change', function() {
        $('.offer-row-checkbox').each((_, cb) => {
            cb.checked = this.checked;
            if (this.checked) selectedOfferIds.add(String(cb.value));
            else selectedOfferIds.delete(String(cb.value));
        });
    });
    $('#bulkEmailOffersBtn').on('click', bulkEmailOffers);

    /* Mobile bottom nav */
    const offerMobileActionsBtn = document.getElementById('offerMobileActionsBtn');
    const offerMobileStatusBtn = document.getElementById('offerMobileStatusBtn');
    const offerMobileNewBtn = document.getElementById('offerMobileNewBtn');
    const offerMobileActionsSheet = document.getElementById('offerMobileActionsSheet');
    const offerMobileStatusSheet = document.getElementById('offerMobileStatusSheet');
    const offerMobileActionsBackdrop = document.getElementById('offerMobileActionsBackdrop');
    const offerMobileStatusBackdrop = document.getElementById('offerMobileStatusBackdrop');
    const offerStatusFilterEl = document.getElementById('offerStatusFilter');

    function closeOfferMobileSheets() {
        offerMobileActionsSheet?.classList.remove('active');
        offerMobileActionsSheet?.setAttribute('aria-hidden', 'true');
        offerMobileStatusSheet?.classList.remove('active');
        offerMobileStatusSheet?.setAttribute('aria-hidden', 'true');
        offerMobileActionsBtn?.classList.remove('active');
        offerMobileStatusBtn?.classList.remove('active');
    }

    function openOfferMobileActionsSheet() {
        closeOfferMobileSheets();
        offerMobileActionsSheet?.classList.add('active');
        offerMobileActionsSheet?.setAttribute('aria-hidden', 'false');
        offerMobileActionsBtn?.classList.add('active');
    }

    function openOfferMobileStatusSheet() {
        closeOfferMobileSheets();
        if (!offerMobileStatusSheet || !offerStatusFilterEl) return;
        offerMobileStatusSheet.querySelectorAll('.offer-mobile-status-option').forEach(opt => {
            opt.classList.toggle('selected', opt.dataset.status === offerStatusFilterEl.value);
        });
        offerMobileStatusSheet.classList.add('active');
        offerMobileStatusSheet.setAttribute('aria-hidden', 'false');
        offerMobileStatusBtn?.classList.add('active');
    }

    offerMobileActionsBtn?.addEventListener('click', () => {
        if (offerMobileActionsSheet?.classList.contains('active')) {
            closeOfferMobileSheets();
        } else {
            openOfferMobileActionsSheet();
        }
    });
    offerMobileStatusBtn?.addEventListener('click', () => {
        if (offerMobileStatusSheet?.classList.contains('active')) {
            closeOfferMobileSheets();
        } else {
            openOfferMobileStatusSheet();
        }
    });
    offerMobileNewBtn?.addEventListener('click', () => {
        closeOfferMobileSheets();
        openOfferModal();
    });
    offerMobileActionsBackdrop?.addEventListener('click', closeOfferMobileSheets);
    offerMobileStatusBackdrop?.addEventListener('click', closeOfferMobileSheets);
    document.getElementById('offerMobileExportBtn')?.addEventListener('click', () => {
        closeOfferMobileSheets();
        exportOffersCsv();
    });
    document.getElementById('offerMobileBulkEmailBtn')?.addEventListener('click', () => {
        closeOfferMobileSheets();
        bulkEmailOffers();
    });
    document.getElementById('offerMobileSelectAllBtn')?.addEventListener('click', () => {
        closeOfferMobileSheets();
        const checkboxes = $('.offer-row-checkbox');
        const allChecked = checkboxes.length > 0 && checkboxes.toArray().every(cb => cb.checked);
        checkboxes.each((_, cb) => {
            cb.checked = !allChecked;
            if (cb.checked) selectedOfferIds.add(String(cb.value));
            else selectedOfferIds.delete(String(cb.value));
        });
        $('#selectAllOffers').prop('checked', !allChecked && checkboxes.length > 0);
    });
    offerMobileStatusSheet?.querySelectorAll('.offer-mobile-status-option').forEach(opt => {
        opt.addEventListener('click', () => {
            if (!offerStatusFilterEl) return;
            offerStatusFilterEl.value = opt.dataset.status;
            closeOfferMobileSheets();
            $(offerStatusFilterEl).trigger('change');
        });
    });

    $(document).on('click', '.mobile-offer-card', function (e) {
        if (window.innerWidth > 768) return;
        if ($(e.target).closest('.offer-card-detail-panel, .offer-card-action-btn, .offer-card-status-btn, .offer-card-expand-btn, .offer-row-checkbox, .offer-card-select').length) return;
        const btn = this.querySelector('.offer-card-expand-btn');
        if (btn) btn.click();
    });

    $('#user_select').on('change', function() {
        const userId = $(this).val();
        $('#hidden_user_id').val(userId);
        if (userId) {
            const user = usersList.find(u => u.id == userId);
            if (user) {
                $('#offer_candidate_name').val(user.username || '');
                $('[name="email"]').val(user.useremail || '');
                $('[name="phone"]').val(user.phonenumber || '');
                $('[name="position"]').val(user.user_type || '');
                $('[name="department"]').val(user.project_name || '');
                $('[name="monthly_salary"]').val(user.salary || '0');
                $('[name="joining_date"]').val(user.doj || '');
                $('[name="reporting_manager"]').val(user.assign_user || '');
            }
        } else {
            $('#offer_candidate_name').val('');
            $('[name="email"]').val('');
            $('[name="phone"]').val('');
            $('[name="position"]').val('');
            $('[name="department"]').val('');
            $('[name="monthly_salary"]').val('');
            $('[name="joining_date"]').val('');
            $('[name="reporting_manager"]').val('');
        }
    });
});

let usersDropdownLoaded = false;
let usersDropdownLoading = null;

function ensureUsersDropdownLoaded() {
    if (usersDropdownLoaded) {
        return Promise.resolve();
    }
    if (usersDropdownLoading) {
        return usersDropdownLoading;
    }
    usersDropdownLoading = $.ajax({
        url: 'fetch_users.php?offer_dropdown=1',
        type: 'GET',
        dataType: 'json'
    }).then(function(res) {
        if (res && !res.error && Array.isArray(res)) {
            usersList = res;
            let selectHtml = '<option value="">-- Select Created User --</option>';
            res.forEach(user => {
                selectHtml += `<option value="${user.id}">${user.username} (${user.useremail || 'No email'})</option>`;
            });
            $('#user_select').html(selectHtml);
            usersDropdownLoaded = true;
        }
    }).catch(function() {
        usersDropdownLoading = null;
    });
    return usersDropdownLoading;
}

function loadOffers(page = 1) {
    offerCurrentPage = page;
    $.ajax({
        url: 'offer_letter_action.php',
        type: 'GET',
        data: getOfferQueryParams(page),
        dataType: 'json',
        success: function(res) {
            allOffers = Array.isArray(res.data) ? res.data : [];
            offerTotalEntries = parseInt(res.total || 0, 10);
            offerTotalPages = parseInt(res.total_pages || 1, 10);
            offerCurrentPage = parseInt(res.page || 1, 10);
            renderOffers(allOffers);
            renderOfferPagination();
        },
        error: function(xhr, status, error) {
            console.error('Failed to load offer letters:', status, error);
            $('#offerTableBody').html('<tr><td colspan="9" class="text-center text-danger">Failed to load offer letters. Please refresh the page.</td></tr>');
            Swal.fire('Error', 'Failed to load offer letters. Please check your connection and try again.', 'error');
        }
    });
}

function getStatusBadge(status) {
    switch(status) {
        case 'Accepted': return 'bg-success';
        case 'Rejected': return 'bg-danger';
        case 'Sent': return 'bg-info';
        case 'Draft': return 'bg-warning text-dark';
        default: return 'bg-secondary';
    }
}

function emailOffer(id, name) {
    const offer = allOffers.find(item => String(item.id) === String(id));
    const resendNote = offer && offer.emailed_at
        ? `<br><small class="text-muted">Last emailed on ${escapeHtml(offer.emailed_at)}${offer.emailed_by ? ' by ' + escapeHtml(offer.emailed_by) : ''}</small>`
        : '';
    Swal.fire({
        title: offer && offer.emailed_at ? 'Resend Offer Letter?' : 'Email Offer Letter?',
        html: `Send the offer letter to <strong>${escapeHtml(name)}</strong> via email?${resendNote}`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3b82f6',
        confirmButtonText: '<i class="bi bi-envelope"></i> Yes, Send Email',
        cancelButtonText: 'Cancel',
        showLoaderOnConfirm: true,
        preConfirm: () => {
            return sendOfferEmail(id).catch(err => {
                Swal.showValidationMessage('Error: ' + (err.message || 'Failed to send email.'));
            });
        },
        allowOutsideClick: () => !Swal.isLoading()
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire('Sent!', result.value.message, 'success');
            loadOffers(offerCurrentPage);
        }
    });
}

function openOfferModal() {
    $('#offerForm')[0].reset();
    $('#offer_id').val('');
    $('#hidden_user_id').val('');
    $('#user_select').val('').prop('disabled', false);
    $('#offerModalLabel').text('Generate New Offer Letter');
    ensureUsersDropdownLoaded();

    const modalEl = document.getElementById('offerModal');
    if (!modalEl) return;

    if (modalEl.parentElement !== document.body) {
        document.body.appendChild(modalEl);
    }

    let modal = bootstrap.Modal.getInstance(modalEl);
    if (modal) modal.dispose();
    modal = new bootstrap.Modal(modalEl, { backdrop: true, focus: true });

    modalEl.addEventListener('shown.bs.modal', function onOfferShown() {
        document.body.classList.add('offer-modal-open');
        modalEl.removeEventListener('shown.bs.modal', onOfferShown);
    }, { once: true });

    modalEl.addEventListener('hidden.bs.modal', function onOfferHidden() {
        document.body.classList.remove('offer-modal-open');
        modalEl.removeEventListener('hidden.bs.modal', onOfferHidden);
    }, { once: true });

    modal.show();
}

/** Open Summernote offer letter editor on this page (same as Employees User360). */
function editOfferLetterContent(userId) {
    const uid = parseInt(userId, 10);
    if (!uid) {
        Swal.fire('Cannot edit', 'This offer is not linked to an employee account.', 'info');
        return;
    }
    if (typeof window.openLetterModal !== 'function') {
        Swal.fire('Error', 'Letter editor is not available. Please refresh the page.', 'error');
        return;
    }
    window.openLetterModal('offer_letter', uid);
}

function saveOffer() {
    $.ajax({
        url: 'offer_letter_action.php',
        type: 'POST',
        data: $('#offerForm').serialize(),
        dataType: 'json',
        success: function(res) {
            if (res.status === 'success') {
                Swal.fire('Success', res.message, 'success');
                bootstrap.Modal.getInstance(document.getElementById('offerModal')).hide();
                loadOffers(1);
            } else {
                Swal.fire('Error', res.message || 'Failed to save offer letter.', 'error');
            }
        },
        error: function() {
            Swal.fire('Error', 'Failed to save offer letter.', 'error');
        }
    });
}

async function bulkEmailOffers() {
    const ids = Array.from(selectedOfferIds);
    if (ids.length === 0) {
        Swal.fire('Select offers', 'Choose at least one offer letter to email.', 'info');
        return;
    }
    const result = await Swal.fire({
        title: `Email ${ids.length} offer letter${ids.length > 1 ? 's' : ''}?`,
        text: 'This will send emails one by one.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, send'
    });
    if (!result.isConfirmed) return;

    let sent = 0;
    const failures = [];
    Swal.fire({
        title: 'Sending offer letters...',
        html: 'Please wait.',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });

    for (const id of ids) {
        try {
            await sendOfferEmail(id);
            sent++;
        } catch (err) {
            failures.push(`#${id}: ${err.message || 'Failed'}`);
        }
    }

    resetOfferSelection();
    loadOffers(offerCurrentPage);
    if (failures.length > 0) {
        Swal.fire('Bulk email finished', `${sent} sent, ${failures.length} failed.<br><small>${failures.join('<br>')}</small>`, 'warning');
    } else {
        Swal.fire('Sent', `${sent} offer letter${sent !== 1 ? 's' : ''} sent successfully.`, 'success');
    }
}

function deleteOffer(id) {
    Swal.fire({
        title: 'Delete this offer?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: 'offer_letter_action.php',
                type: 'POST',
                data: { action: 'delete', id: id },
                dataType: 'json',
                success: function(res) {
                    if (res.status === 'success') {
                        Swal.fire('Deleted!', res.message, 'success');
                        loadOffers(offerCurrentPage);
                    }
                }
            });
        }
    });
}
</script>
</body>
</html>
