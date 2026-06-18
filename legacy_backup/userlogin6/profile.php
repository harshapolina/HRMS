<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$pageTitle    = 'My Profile';
$isProfilePage = true;
require_once 'config.php';
include 'htmlopen.php';
include 'header.php';
?>
<style>
  /* Profile page: hide greeting/filters but keep notifications + profile icon */
  .header.leads-header .header-left {
    display: none !important;
  }

  .header.leads-header .month-filter {
    display: none !important;
  }

  .header.leads-header .header-name-select {
    display: none !important;
  }

  .profile-page-wrap {
    display: block;
    padding: 0;
    min-height: 100vh;
    margin-bottom: 20px;
  }

  .profile-card {
    width: 100%;
    max-width: none;
    min-height: 100vh;
    border-radius: 0;
    box-shadow: none;
    overflow: hidden;
  }

  body.dark-mode .profile-card,
  [data-theme="dark"] .profile-card {
    background: transparent;
    color: #ffffff;
  }
  body.dark-mode .profile-card {
    background: #1e1e2d;
    color: #eee;
  }

  .drawer-header {
    padding: 25px 30px;
    background: linear-gradient(135deg, #1e6063 0%, #2a8c90 100%);
    color: #fff;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-radius: 10px;
  }
  body.dark-mode .drawer-header,
  [data-theme="dark"] .drawer-header {
    background: rgba(200, 200, 200, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.12);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
    backdrop-filter: blur(35px);
    -webkit-backdrop-filter: blur(35px);
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
    background: rgba(255, 255, 255, .2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    border: 3px solid rgba(255, 255, 255, .3);
  }

  .drawer-title h3 {
    margin: 0 0 5px;
    font-weight: 700;
    font-size: 1.4rem;
  }

  .drawer-title p {
    margin: 0;
    opacity: .8;
    font-size: .9rem;
  }

  .status-badge-inline {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-top: 6px;
    font-size: .85rem;
    font-weight: 600;
  }

  .drawer-tabs {
    display: flex;
    padding: 0 20px;
    border-bottom: 1px solid #e2e8f0;
    overflow-x: auto;
  }

  body.dark-mode .drawer-tabs,
  [data-theme="dark"] .drawer-tabs {
    background: rgba(200, 200, 200, 0.06);
    border-color: rgba(255, 255, 255, 0.12);
    backdrop-filter: blur(35px);
    -webkit-backdrop-filter: blur(35px);
  }
  body.dark-mode .drawer-tabs {
    background: rgba(0, 0, 0, .1);
    border-color: rgba(255, 255, 255, .1);
  }

  .drawer-tab {
    padding: 15px 20px;
    cursor: pointer;
    font-weight: 600;
    color: #888;
    border-bottom: 3px solid transparent;
    transition: all .2s;
    white-space: nowrap;
    font-size: .95rem;
  }

  body.dark-mode .drawer-tab,
  [data-theme="dark"] .drawer-tab { color: rgba(255, 255, 255, 0.8); }
  body.dark-mode .drawer-tab:hover,
  [data-theme="dark"] .drawer-tab:hover { background: rgba(255, 255, 255, 0.1); }
  body.dark-mode .drawer-tab.active,
  [data-theme="dark"] .drawer-tab.active { color: #ffffff; border-bottom-color: #ffffff; }
  .drawer-tab:hover {
    color: #2a8c90;
  }

  .drawer-tab.active {
    color: #2a8c90;
    border-bottom-color: #2a8c90;
  }

  body.dark-mode .drawer-tab {
    color: #aaa;
  }

  body.dark-mode .drawer-tab.active {
    color: #4fd1d5;
    border-bottom-color: #4fd1d5;
  }

  .drawer-body {
    padding: 28px 30px;
  }

  .drawer-section {
    display: none;
    animation: fadeIn .3s;
  }

  .drawer-section.active {
    display: block;
  }

  @keyframes fadeIn {
    from {
      opacity: 0;
      transform: translateY(6px)
    }

    to {
      opacity: 1;
      transform: translateY(0)
    }
  }

  .drawer-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
  }

  .drawer-grid.basic-info-grid {
    grid-template-columns: repeat(4, 1fr);
  }

  @media(max-width: 992px) {
    .drawer-grid.basic-info-grid {
      grid-template-columns: repeat(2, 1fr);
    }
  }

  @media(max-width: 600px) {
    .drawer-grid.basic-info-grid {
      grid-template-columns: 1fr;
    }
  }

  .info-box {
    background: #f8fafc;
    padding: 15px;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
  }

  body.dark-mode .info-box,
  [data-theme="dark"] .info-box {
    background: rgba(200, 200, 200, 0.06);
    border-color: rgba(255, 255, 255, 0.12);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
    backdrop-filter: blur(35px);
    -webkit-backdrop-filter: blur(35px);
  }
  body.dark-mode .info-box {
    background: rgba(255, 255, 255, .04);
    border-color: rgba(255, 255, 255, .08);
  }

  .info-label {
    font-size: .75rem;
    text-transform: uppercase;
    color: #888;
    margin-bottom: 5px;
    font-weight: 700;
    letter-spacing: .5px;
  }

  .info-value {
    font-size: 1rem;
    font-weight: 600;
    color: #1e293b;
    word-break: break-word;
  }

  body.dark-mode .info-value,
  [data-theme="dark"] .info-value { color: #ffffff; }
  body.dark-mode .info-value {
    color: #eee;
  }

  .section-title {
    font-weight: 700;
    font-size: 1.1rem;
    margin: 0 0 15px;
    color: #1e293b;
  }

  body.dark-mode .section-title,
  [data-theme="dark"] .section-title { color: #ffffff; }
  body.dark-mode .section-title {
    color: #eee;
  }

  /* Attendance Calendar */
  .att-summary-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin-bottom: 24px;
  }

  .att-card {
    padding: 14px;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    text-align: center;
    background: #f8fafc;
  }

  body.dark-mode .att-card,
  [data-theme="dark"] .att-card {
    background: rgba(200, 200, 200, 0.06);
    border-color: rgba(255, 255, 255, 0.12);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
    backdrop-filter: blur(35px);
    -webkit-backdrop-filter: blur(35px);
  }
  body.dark-mode .att-card {
    background: rgba(255, 255, 255, .04);
    border-color: rgba(255, 255, 255, .08);
  }

  .att-card .att-val {
    font-size: 1.6rem;
    font-weight: 800;
    display: block;
  }

  .att-card .att-lbl {
    font-size: .75rem;
    text-transform: uppercase;
    color: #888;
    font-weight: 600;
  }

  .att-present .att-val {
    color: #10b981;
  }

  .att-absent .att-val {
    color: #ef4444;
  }

  .att-late .att-val {
    color: #f59e0b;
  }

  .att-leave .att-val {
    color: #3b82f6;
  }

  .cal-nav {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 18px;
  }

  .btn-cal-nav {
    background: #0d9488;
    color: #fff;
    border: none;
    border-radius: 10px;
    padding: 7px 18px;
    font-weight: 600;
    cursor: pointer;
    transition: background .2s;
  }
  body.dark-mode .btn-cal-nav,
  [data-theme="dark"] .btn-cal-nav {
    background: rgba(255, 255, 255, 0.15);
    color: #ffffff;
  }

  .btn-cal-nav:hover {
    background: #097969;
  }
  body.dark-mode .btn-cal-nav:hover,
  [data-theme="dark"] .btn-cal-nav:hover {
    background: rgba(255, 255, 255, 0.25);
  }

  .cal-title {
    font-weight: 700;
    color: #0d9488;
    font-size: 1.1rem;
  }
  body.dark-mode .cal-title,
  [data-theme="dark"] .cal-title { color: #ffffff; }

  .attendance-calendar {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 6px;
    padding: 14px;
    border-radius: 14px;
    border: 1px solid #e2e8f0;
  }

  body.dark-mode .attendance-calendar,
  [data-theme="dark"] .attendance-calendar {
    background: rgba(200, 200, 200, 0.06);
    border-color: rgba(255, 255, 255, 0.12);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
    backdrop-filter: blur(35px);
    -webkit-backdrop-filter: blur(35px);
  }
  body.dark-mode .attendance-calendar {
    background: rgba(0, 0, 0, .15);
    border-color: rgba(255, 255, 255, .08);
  }

  .cal-day-label {
    text-align: center;
    font-size: 11px;
    font-weight: 800;
    color: #64748b;
    padding-bottom: 10px;
  }

  .cal-date {
    aspect-ratio: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    font-weight: 700;
    border-radius: 10px;
    background: #fff;
    border: 1px solid #e2e8f0;
    color: #1e293b;
    gap: 3px;
    position: relative;
  }

  body.dark-mode .cal-date,
  [data-theme="dark"] .cal-date {
    background: rgba(200, 200, 200, 0.06);
    border-color: rgba(255, 255, 255, 0.12);
    color: rgba(255, 255, 255, 0.9);
  }
  body.dark-mode .cal-date {
    background: rgba(255, 255, 255, .05);
    border-color: rgba(255, 255, 255, .08);
    color: #ddd;
  }

  .cal-date.today {
    border: 2px solid #0d9488;
    color: #0d9488;
  }
  body.dark-mode .cal-date.today,
  [data-theme="dark"] .cal-date.today {
    border-color: rgba(255, 255, 255, 0.7);
    color: #ffffff;
  }

  .cal-date.empty {
    background: transparent;
    border: none;
  }

  .cal-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
  }

  .cal-date.present .cal-dot {
    background: #3b82f6;
  }

  .cal-date.late .cal-dot {
    background: #f59e0b;
  }

  .cal-date.absent .cal-dot {
    background: #ef4444;
  }

  .cal-date.holiday .cal-dot {
    background: #3b82f6;
  }

  .cal-date.clickable {
    cursor: pointer;
    transition: transform .15s ease, box-shadow .15s ease;
  }

  .cal-date.clickable:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 14px rgba(15, 23, 42, 0.12);
  }

  .att-modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.35);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    padding: 16px;
  }

  .att-modal {
    width: 100%;
    max-width: 420px;
    background: #fff;
    border-radius: 14px;
    padding: 18px 20px;
    box-shadow: 0 18px 40px rgba(15, 23, 42, 0.25);
  }

  .att-modal h6 {
    margin: 0 0 8px;
    font-size: 1rem;
    color: #0f172a;
  }

  .att-modal .att-row {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px dashed #e2e8f0;
    font-size: .88rem;
    color: #334155;
  }

  .att-modal .att-row:last-child {
    border-bottom: none;
  }

  .att-modal .att-label {
    font-weight: 700;
    color: #64748b;
  }

  .att-modal .att-close {
    border: none;
    background: #e2e8f0;
    color: #0f172a;
    padding: 6px 10px;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
  }

  body.dark-mode .att-modal,
  [data-theme="dark"] .att-modal {
    background: #1f2937;
    color: #e5e7eb;
  }

  body.dark-mode .att-modal h6,
  [data-theme="dark"] .att-modal h6 {
    color: #f8fafc;
  }

  body.dark-mode .att-modal .att-row,
  [data-theme="dark"] .att-modal .att-row {
    border-color: rgba(255, 255, 255, 0.12);
    color: #e5e7eb;
  }

  body.dark-mode .att-modal .att-label,
  [data-theme="dark"] .att-modal .att-label {
    color: rgba(255, 255, 255, 0.7);
  }

  /* Salary Table */
  .salary-table {
    width: 100%;
    border-collapse: collapse;
    font-size: .9rem;
  }

  body.dark-mode .salary-table th,
  [data-theme="dark"] .salary-table th {
    background: rgba(200, 200, 200, 0.08);
    color: #ffffff;
    border-bottom: 1px solid rgba(255, 255, 255, 0.12);
  }
  body.dark-mode .salary-table td,
  [data-theme="dark"] .salary-table td {
    color: rgba(255, 255, 255, 0.9);
    border-color: rgba(255, 255, 255, 0.08);
  }
  .salary-table th {
    background: #f1f5f9;
    padding: 10px 14px;
    text-align: left;
    font-size: .75rem;
    text-transform: uppercase;
    color: #64748b;
    font-weight: 700;
  }

  .salary-table td {
    padding: 10px 14px;
    border-bottom: 1px solid #f1f5f9;
    color: #1e293b;
  }

  .salary-table tr:last-child td {
    border: none;
  }

  body.dark-mode .salary-table th {
    background: rgba(255, 255, 255, .05);
    color: #aaa;
  }

  body.dark-mode .salary-table td {
    color: #ddd;
    border-color: rgba(255, 255, 255, .06);
  }

  .salary-table .total-row td {
    background: #e0f2f1;
    font-weight: 700;
    color: #0d9488;
  }

  .salary-table .net-row td {
    background: #0d9488;
    color: #fff;
    font-weight: 800;
    font-size: 1rem;
    border-radius: 0 0 8px 8px;
  }
  body.dark-mode .salary-table .net-row td,
  [data-theme="dark"] .salary-table .net-row td {
    background: rgba(255, 255, 255, 0.18);
    color: #ffffff;
  }

  .salary-table .deduction-row td {
    color: #ef4444;
  }

  body.dark-mode .salary-table .total-row td,
  [data-theme="dark"] .salary-table .total-row td,
  body.dark-mode .salary-table .highlight-row td,
  [data-theme="dark"] .salary-table .highlight-row td {
    background: rgba(200, 200, 200, 0.12);
    color: #ffffff;
  }
  body.dark-mode .salary-table tr.highlight-row,
  [data-theme="dark"] .salary-table tr.highlight-row {
    background-color: rgba(200, 200, 200, 0.12);
    box-shadow: none;
  }

  /* Assets */
  .asset-table {
    width: 100%;
    border-collapse: collapse;
    font-size: .9rem;
  }

  body.dark-mode .asset-table th,
  [data-theme="dark"] .asset-table th {
    background: rgba(200, 200, 200, 0.08);
    color: #ffffff;
    border-bottom: 1px solid rgba(255, 255, 255, 0.12);
  }
  body.dark-mode .asset-table td,
  [data-theme="dark"] .asset-table td {
    border-color: rgba(255, 255, 255, 0.08);
    color: rgba(255, 255, 255, 0.9);
  }
  .asset-table th {
    background: #f1f5f9;
    padding: 10px 14px;
    text-align: left;
    font-size: .75rem;
    text-transform: uppercase;
    color: #64748b;
    font-weight: 700;
  }

  .asset-table td {
    padding: 12px 14px;
    border-bottom: 1px solid #f1f5f9;
  }

  body.dark-mode .asset-table th {
    background: rgba(255, 255, 255, .05);
    color: #aaa;
  }

  body.dark-mode .asset-table td {
    border-color: rgba(255, 255, 255, .06);
    color: #ddd;
  }

  .no-data-msg {
    text-align: center;
    padding: 30px;
    color: #94a3b8;
    font-size: .95rem;
  }

  /* HR Suggestion */
  .hr-suggest {
    border-top: 1px dashed #e2e8f0;
    margin-top: 24px;
    padding-top: 18px;
  }

  body.dark-mode .hr-suggest ul,
  [data-theme="dark"] .hr-suggest ul { color: rgba(255, 255, 255, 0.7); }
  .hr-suggest h6 {
    color: #8b5cf6;
    font-weight: 700;
    margin-bottom: 10px;
  }

  .hr-suggest ul {
    font-size: .88rem;
    color: #64748b;
    padding-left: 20px;
  }

  body.dark-mode .hr-suggest ul {
    color: #aaa;
  }

  /* Payslip table */
  .payslip-table-wrap {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    width: 100%;
  }
  .payslip-table {
    width: 100%;
    min-width: 520px;
    border-collapse: collapse;
    font-size: .88rem;
    margin-top: 10px;
  }

  body.dark-mode .payslip-table th,
  [data-theme="dark"] .payslip-table th {
    background: rgba(200, 200, 200, 0.08);
    color: #ffffff;
    border-bottom: 1px solid rgba(255, 255, 255, 0.12);
  }
  body.dark-mode .payslip-table td,
  [data-theme="dark"] .payslip-table td {
    border-color: rgba(255, 255, 255, 0.08);
    color: rgba(255, 255, 255, 0.9);
  }
  .payslip-table th {
    background: #f1f5f9;
    padding: 9px 12px;
    text-align: left;
    font-size: .72rem;
    text-transform: uppercase;
    color: #64748b;
    font-weight: 700;
  }

  .payslip-table td {
    padding: 10px 12px;
    border-bottom: 1px solid #f1f5f9;
  }

  .payslip-download {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 10px;
    border-radius: 8px;
    background: #0d9488;
    color: #fff;
    font-size: .75rem;
    font-weight: 600;
    text-decoration: none;
  }

  .payslip-download:hover {
    background: #0f766e;
    color: #fff;
  }

  body.dark-mode .payslip-table th {
    background: rgba(255, 255, 255, .05);
    color: #aaa;
  }

  body.dark-mode .payslip-table td {
    border-color: rgba(255, 255, 255, .06);
    color: #ddd;
  }

  @media(max-width:600px) {
    .att-summary-grid {
      grid-template-columns: repeat(2, 1fr);
    }

    .drawer-grid {
      grid-template-columns: 1fr;
    }

    .drawer-body {
      padding: 18px;
    }

    .drawer-header {
      padding: 18px;
    }

    .drawer-title h3 {
      font-size: 1.1rem;
    }
  }
</style>

<div class="profile-page-wrap">
  <div class="profile-card">

    <!-- Header -->
    <div class="drawer-header">
      <div class="drawer-header-content">
        <div class="drawer-avatar"><i class="fas fa-user"></i></div>
        <div class="drawer-title">
          <h3 id="prof_name">Loading...</h3>
          <p>Employee ID: <span id="prof_emp_id">---</span></p>
          <div class="status-badge-inline">
            <span id="prof_status_badge" style="font-size:.82rem;padding:3px 12px;border-radius:20px;background:rgba(255,255,255,.2);">---</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Tabs -->
    <div class="drawer-tabs">
      <div class="drawer-tab active" onclick="switchProfileTab('overview')">Overview</div>
      <div class="drawer-tab" onclick="switchProfileTab('attendance')">Attendance</div>
      <div class="drawer-tab" onclick="switchProfileTab('payroll')">Payroll</div>
      <div class="drawer-tab" onclick="switchProfileTab('assets')">Assets &amp; Docs</div>
    </div>

    <!-- Body -->
    <div class="drawer-body">

      <!-- OVERVIEW TAB -->
      <div class="drawer-section active" id="ptab_overview">
        <h5 class="section-title">Basic Information</h5>
        <div class="drawer-grid basic-info-grid">
          <div class="info-box">
            <div class="info-label">Email</div>
            <div class="info-value" id="prof_email">---</div>
          </div>
          <div class="info-box">
            <div class="info-label">Contact No</div>
            <div class="info-value" id="prof_contact">---</div>
          </div>
          <div class="info-box">
            <div class="info-label">Date of Joining</div>
            <div class="info-value" id="prof_doj">---</div>
          </div>
          <div class="info-box">
            <div class="info-label">Monthly CTC</div>
            <div class="info-value" id="prof_salary">---</div>
          </div>
          <div class="info-box" id="prof_assigned_users_box" style="display: none; grid-column: 1 / -1;">
            <div class="info-label">Assigned Users</div>
            <div class="info-value" id="prof_assigned_users">---</div>
          </div>
        </div>
        <h5 class="section-title" style="margin-top:8px;">Project Details</h5>
        <div class="drawer-grid">
          <div class="info-box">
            <div class="info-label">Project Name</div>
            <div class="info-value" id="prof_project">---</div>
          </div>
          <div class="info-box">
            <div class="info-label">Role Type</div>
            <div class="info-value" id="prof_role">---</div>
          </div>
        </div>
        <div class="hr-suggest">
          <h6><i class="bi bi-lightbulb"></i> HR Suggestions</h6>
          <ul>
            <li><strong>Activity Timeline:</strong> Track when this user logged in, completed tasks, or applied for leave.</li>
            <li><strong>Performance Notes:</strong> Add a private section here to drop notes about performance or warnings.</li>
          </ul>
        </div>
      </div>

      <!-- ATTENDANCE TAB -->
      <div class="drawer-section" id="ptab_attendance">
        <h5 class="section-title" id="att_month_title">This Month's Attendance</h5>
        <div class="att-summary-grid">
          <div class="att-card att-present"><span class="att-val" id="att_present">0</span><span class="att-lbl">Days Present</span></div>
          <div class="att-card att-absent"><span class="att-val" id="att_absent">0</span><span class="att-lbl">Days Absent</span></div>
          <div class="att-card att-late"><span class="att-val" id="att_late">0</span><span class="att-lbl">Days Late</span></div>
          <div class="att-card att-leave"><span class="att-val" id="att_leave">0</span><span class="att-lbl">Leaves Taken</span></div>
        </div>
        <div class="cal-nav">
          <button class="btn-cal-nav" onclick="changeProfileMonth(-1)"><i class="bi bi-chevron-left me-1"></i> Prev</button>
          <span class="cal-title" id="cal_month_title"><?php echo date('F Y'); ?></span>
          <button class="btn-cal-nav" onclick="changeProfileMonth(1)">Next <i class="bi bi-chevron-right ms-1"></i></button>
        </div>
        <div class="attendance-calendar" id="profileCalendar"></div>
      </div>

      <!-- PAYROLL TAB -->
      <div class="drawer-section" id="ptab_payroll">
        <h5 class="section-title"><i class="bi bi-wallet2 me-2"></i>Salary Structure (CTC)</h5>
        <div id="prof_salary_structure">
          <div class="no-data-msg">
            <div class="spinner-border spinner-border-sm me-2"></div> Loading...
          </div>
        </div>
        <hr style="margin:24px 0;opacity:.1;">
        <h5 class="section-title">Recent Payslips</h5>
        <div class="payslip-table-wrap">
          <table class="payslip-table">
            <thead>
              <tr>
                <th>Month/Year</th>
                <th>Net Pay</th>
                <th>Generated On</th>
                <th>Download</th>
              </tr>
            </thead>
            <tbody id="prof_payslip_list">
              <tr>
                <td colspan="4" class="no-data-msg">Loading...</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ASSETS TAB -->
      <div class="drawer-section" id="ptab_assets">
        <h5 class="section-title">Company Assets Assigned</h5>
        <div class="table-responsive">
          <table class="asset-table">
            <thead>
              <tr>
                <th>Asset</th>
                <th>Type</th>
                <th>Serial No.</th>
                <th>Assigned On</th>
              </tr>
            </thead>
            <tbody id="prof_assets_list">
              <tr>
                <td colspan="4" class="no-data-msg">Loading...</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div id="prof_no_assets" class="no-data-msg" style="display:none;">No assets currently assigned to you.</div>
      </div>

    </div><!-- /drawer-body -->
  </div><!-- /profile-card -->
</div>

<div class="att-modal-overlay" id="attDetailOverlay">
  <div class="att-modal" role="dialog" aria-modal="true">
    <h6 id="attDetailTitle">Attendance Details</h6>
    <div class="att-row"><span class="att-label">Status</span><span id="attDetailStatus">---</span></div>
    <div class="att-row"><span class="att-label">Punch In</span><span id="attDetailIn">---</span></div>
    <div class="att-row"><span class="att-label">Punch Out</span><span id="attDetailOut">---</span></div>
    <div class="att-row"><span class="att-label">Total Hours</span><span id="attDetailHours">---</span></div>
    <div style="display:flex;justify-content:flex-end;margin-top:12px;">
      <button class="att-close" id="attDetailClose" type="button">Close</button>
    </div>
  </div>
</div>

<script>
  /* ======== Profile Page JS ======== */
  let profCalMonth = new Date().getMonth();
  let profCalYear = new Date().getFullYear();
  let profUserData = null;

  const MONTHS = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];

  // Tab switching
  function switchProfileTab(tab) {
    document.querySelectorAll('.drawer-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.drawer-section').forEach(s => s.classList.remove('active'));
    document.querySelectorAll('.drawer-tab').forEach(t => {
      if (t.textContent.toLowerCase().includes(tab.toLowerCase())) t.classList.add('active');
    });
    const sec = document.getElementById('ptab_' + tab);
    if (sec) sec.classList.add('active');
  }

  // Load profile data
  fetch('get_my_profile.php')
    .then(r => r.json())
    .then(user => {
      if (user.error) {
        console.error(user.error);
        return;
      }
      profUserData = user;
      document.getElementById('prof_name').textContent = user.username || 'My Profile';
      document.getElementById('prof_emp_id').textContent = (user.employee_id || 'N/A') + (user.tablename ? ' (' + user.tablename + ')' : '');
      document.getElementById('prof_email').textContent = user.useremail || '---';
      document.getElementById('prof_contact').textContent = user.phonenumber || '---';
      document.getElementById('prof_doj').textContent = user.doj || '---';
      document.getElementById('prof_salary').textContent = user.salary ? '₹' + user.salary : '---';
      document.getElementById('prof_project').textContent = user.project_name || 'Unassigned';
      document.getElementById('prof_role').textContent = user.user_type || '---';

      // Assigned users / subordinates list
      const assignedBox = document.getElementById('prof_assigned_users_box');
      const assignedVal = document.getElementById('prof_assigned_users');
      if (assignedBox && assignedVal) {
        if (user.subordinates && user.subordinates.length > 0) {
          assignedBox.style.display = 'block';
          assignedVal.textContent = user.subordinates.join(', ');
        } else {
          assignedBox.style.display = 'block';
          assignedVal.textContent = 'None';
        }
      }
      // Status badge
      const badge = document.getElementById('prof_status_badge');
      if (user.is_active == 1) {
        badge.textContent = 'Active';
        badge.style.background = 'rgba(16,185,129,.3)';
      } else {
        badge.textContent = 'Inactive';
        badge.style.background = 'rgba(239,68,68,.3)';
      }
      // Salary structure
      renderProfileSalary(user);
      // Payslips
      loadProfilePayslips(user.id);
      // Assets
      loadProfileAssets(user.id);
    })
    .catch(e => console.error('Profile load error:', e));

  // Load attendance on page load
  loadProfileAttendance(profCalMonth + 1, profCalYear);

  function changeProfileMonth(delta) {
    profCalMonth += delta;
    if (profCalMonth < 0) {
      profCalMonth = 11;
      profCalYear--;
    } else if (profCalMonth > 11) {
      profCalMonth = 0;
      profCalYear++;
    }
    loadProfileAttendance(profCalMonth + 1, profCalYear);
  }

  function loadProfileAttendance(month, year) {
    document.getElementById('cal_month_title').textContent = MONTHS[month - 1] + ' ' + year;
    document.getElementById('att_month_title').textContent = MONTHS[month - 1] + ' Attendance Summary';
    fetch(`profile_attendance.php?month=${month}&year=${year}`)
      .then(r => r.json())
      .then(logs => renderProfileCalendar(logs, month, year))
      .catch(() => renderProfileCalendar({}, month, year));
  }

  function renderProfileCalendar(logs, month, year) {
    const cal = document.getElementById('profileCalendar');
    cal.innerHTML = '';
    ['SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT'].forEach(d => {
      const lbl = document.createElement('div');
      lbl.className = 'cal-day-label';
      lbl.textContent = d;
      cal.appendChild(lbl);
    });
    const firstDay = new Date(year, month - 1, 1).getDay();
    const daysInMonth = new Date(year, month, 0).getDate();
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    let counts = {
      present: 0,
      absent: 0,
      late: 0,
      leave: 0
    };

    for (let i = 0; i < firstDay; i++) {
      const e = document.createElement('div');
      e.className = 'cal-date empty';
      cal.appendChild(e);
    }
    for (let d = 1; d <= daysInMonth; d++) {
      const dateStr = `${year}-${String(month).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
      const dayDate = new Date(year, month - 1, d);
      const el = document.createElement('div');
      el.className = 'cal-date';
      el.dataset.date = dateStr;
      const num = document.createElement('span');
      num.textContent = d;
      el.appendChild(num);
      if (dayDate.getTime() === today.getTime()) el.classList.add('today');
      const isSunday = dayDate.getDay() === 0;
      const isPast = dayDate < today;
      let log = logs[dateStr];
      if (isSunday) {
        log = {
          status: 'Holiday',
          reason: 'Sunday'
        };
      } else if (!log && isPast) {
        log = {
          status: 'Absent',
          reason: 'No punch record'
        };
      }
      if (log) {
        const s = (typeof log === 'object' ? log.status : log).toLowerCase();
        const reason = typeof log === 'object' ? log.reason : '';
        let cls = 'absent';
        if (s === 'present') {
          cls = 'present';
          counts.present++;
        } else if (s === 'late') {
          cls = 'late';
          counts.present++;
          counts.late++;
        } else if (s === 'absent' || s === 'late-absent') {
          cls = 'absent';
          counts.absent++;
        } else {
          cls = 'holiday';
          counts.leave++;
        }
        el.classList.add(cls);
        if (reason) el.title = reason;
        const dot = document.createElement('div');
        dot.className = 'cal-dot';
        el.appendChild(dot);
      }
      el.classList.add('clickable');
      el.addEventListener('click', function () {
        openAttendanceDetail(dateStr);
      });
      cal.appendChild(el);
    }
    document.getElementById('att_present').textContent = counts.present;
    document.getElementById('att_absent').textContent = counts.absent;
    document.getElementById('att_late').textContent = counts.late;
    document.getElementById('att_leave').textContent = counts.leave;
  }

  function formatTime(value, dateStr) {
    if (!value) return '-';
    const raw = String(value);
    try {
      if (raw.includes(' ')) {
        return new Date(raw.replace(' ', 'T')).toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' });
      }
      return new Date(dateStr + 'T' + raw).toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' });
    } catch (e) {
      return raw;
    }
  }

  function openAttendanceDetail(dateStr) {
    const overlay = document.getElementById('attDetailOverlay');
    const title = document.getElementById('attDetailTitle');
    const statusEl = document.getElementById('attDetailStatus');
    const inEl = document.getElementById('attDetailIn');
    const outEl = document.getElementById('attDetailOut');
    const hrsEl = document.getElementById('attDetailHours');

    title.textContent = 'Attendance - ' + dateStr;
    statusEl.textContent = 'Loading...';
    inEl.textContent = '---';
    outEl.textContent = '---';
    hrsEl.textContent = '---';

    overlay.style.display = 'flex';

    fetch(`profile_attendance.php?date=${encodeURIComponent(dateStr)}`)
      .then(r => r.json())
      .then(res => {
        if (res && res.status === 'success') {
          const data = res.data || {};
          statusEl.textContent = data.status || '---';
          inEl.textContent = formatTime(data.punch_in, dateStr);
          outEl.textContent = formatTime(data.punch_out, dateStr);
          hrsEl.textContent = (data.total_hours !== null && data.total_hours !== undefined) ? `${data.total_hours} hrs` : '-';
        } else {
          statusEl.textContent = (res && res.message) ? res.message : 'No record';
          inEl.textContent = '-';
          outEl.textContent = '-';
          hrsEl.textContent = '-';
        }
      })
      .catch(() => {
        statusEl.textContent = 'Unable to load';
        inEl.textContent = '-';
        outEl.textContent = '-';
        hrsEl.textContent = '-';
      });
  }

  document.getElementById('attDetailClose').addEventListener('click', function () {
    document.getElementById('attDetailOverlay').style.display = 'none';
  });
  document.getElementById('attDetailOverlay').addEventListener('click', function (e) {
    if (e.target.id === 'attDetailOverlay') {
      e.currentTarget.style.display = 'none';
    }
  });

  function renderProfileSalary(user) {
    const container = document.getElementById('prof_salary_structure');
    const ctc = parseFloat(user.salary) || 0;
    const yearly = ctc * 12;
    const hasManual = ['first_amount', 'second_amount', 'third_amount', 'fourth_amount', 'fifth_amount', 'sixth_amount']
      .some(k => user[k] && parseFloat(user[k]) > 0);
    const pfEmp = hasManual ? (parseFloat(user.fifth_amount) || 0) : 1800;
    const basic = hasManual ? (parseFloat(user.first_amount) || 0) : Math.round(ctc * 0.500006);
    const hra = hasManual ? (parseFloat(user.second_amount) || 0) : Math.round(ctc * 0.200004);
    const conv = hasManual ? (parseFloat(user.third_amount) || 0) : Math.round(ctc * 0.069997);
    const gross = ctc - pfEmp;
    const spec = hasManual ? (parseFloat(user.fourth_amount) || 0) : (gross - (basic + hra + conv));
    const deds = hasManual ? (parseFloat(user.sixth_amount) || 0) : (1800 + 200 + 817);
    const net = gross - deds;
    const row = (label, monthly, yearly, cls = '') =>
      `<tr class="${cls}"><td>${label}</td><td>₹${monthly.toLocaleString()}</td><td>₹${(yearly||monthly*12).toLocaleString()}</td></tr>`;
    container.innerHTML = `
    <table class="salary-table">
      <thead><tr><th>Component</th><th>Monthly</th><th>Yearly</th></tr></thead>
      <tbody>
        ${row('Total CTC (Cost to Company)', ctc, yearly, 'total-row')}
        ${row('Basic Salary', basic, basic*12)}
        ${row('HRA (House Rent Allowance)', hra, hra*12)}
        ${row('Conveyance Allowance', conv, conv*12)}
        ${row('Special Allowance', spec, spec*12)}
        ${row('PF (Employer Part)', pfEmp, pfEmp*12, 'highlight-row')}
        ${row('Monthly Gross', gross, gross*12)}
        <tr class="deduction-row"><td>Standard Deductions (PF, PT, Med)</td><td>-₹${deds.toLocaleString()}</td><td>-₹${(deds*12).toLocaleString()}</td></tr>
        ${row('Net Take Home Pay', net, net*12, 'net-row')}
      </tbody>
    </table>`;
  }

  function loadProfilePayslips(userId) {
    const tb = document.getElementById('prof_payslip_list');
    fetch(`../hrlogin/payslips_api.php?user_id=${encodeURIComponent(userId)}&action=history`)
      .then(r => r.json())
      .then(resp => {
        // API returns { status: 'success', data: [{month, year, net_pay, created_at}] }
        const list = (resp && resp.status === 'success' && Array.isArray(resp.data)) ? resp.data : [];
        if (!list.length) {
          tb.innerHTML = '<tr><td colspan="4" class="no-data-msg">No Payslips Generated Yet.</td></tr>';
          return;
        }
        const MONTHS_SHORT = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        tb.innerHTML = list.slice(0, 6).map(p => {
          const monthYear = p.month_year || (MONTHS_SHORT[p.month] + ' ' + p.year) || '---';
          const netPay = parseFloat(p.net_pay || p.net_salary || 0).toLocaleString();
          const genOn = (p.created_at || '---').split(' ')[0]; // date only
          const source = p.source || '';
          const pid = p.id || '';
          const dlUrl = `payslip_download.php?source=${encodeURIComponent(source)}&id=${encodeURIComponent(pid)}&month=${encodeURIComponent(p.month)}&year=${encodeURIComponent(p.year)}&auto=1`;
          return `<tr><td>${monthYear}</td><td>₹${netPay}</td><td>${genOn}</td><td><a class="payslip-download" href="${dlUrl}" target="_blank" rel="noopener"><i class="bi bi-download"></i> PDF</a></td></tr>`;
        }).join('');
      })
      .catch(() => {
        tb.innerHTML = '<tr><td colspan="4" class="no-data-msg">Payslip data not available.</td></tr>';
      });
  }

  function loadProfileAssets(userId) {
    const tb = document.getElementById('prof_assets_list');
    const msg = document.getElementById('prof_no_assets');
    // Use dedicated userlogin6 endpoint that queries asset_assignments + assets tables
    fetch(`get_my_assets.php`)
      .then(r => r.json())
      .then(assets => {
        if (!Array.isArray(assets) || !assets.length) {
          tb.innerHTML = '';
          msg.style.display = 'block';
          return;
        }
        msg.style.display = 'none';
        tb.innerHTML = assets.map(a =>
          `<tr><td>${a.asset_name||'---'}</td><td>${a.asset_type||'---'}</td><td>${a.serial_number||'---'}</td><td>${a.assigned_date||'---'}</td></tr>`
        ).join('');
      })
      .catch(() => {
        tb.innerHTML = '';
        msg.textContent = 'Asset data not available.';
        msg.style.display = 'block';
      });
  }
</script>

<?php require_once 'htmlclose.php'; ?>