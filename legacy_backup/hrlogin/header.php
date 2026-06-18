<?php
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}
date_default_timezone_set('Asia/Kolkata');
// Ensure tablename is set (fallback to username) for notification API
if (session_status() === PHP_SESSION_ACTIVE
    && !isset($_SESSION['tablename'])
    && !empty($_SESSION['username'])) {
    $_SESSION['tablename'] = $_SESSION['username'];
}

if (!isset($HR_WEB_BASE)) {
  require_once dirname(__FILE__) . '/hr_paths.inc.php';
}
$hr_web = rtrim(isset($HR_WEB_BASE) ? $HR_WEB_BASE : '', '/');
$hr_avatar_src = $hr_web . '/../userlogin6/assets/dataimage/ayu.jpg';
$hr_script = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
$hr_search_placeholder = (strpos($hr_script, 'payment-tracking') !== false)
  ? 'Search payments...'
  : 'Search assets and employees...';
// Determine active classes (Robust detection)
$active_dashboard = (strpos($hr_script, 'dashboard.php') !== false) ? 'active' : '';
$active_users = (strpos($hr_script, 'users.php') !== false) ? 'active' : '';
$active_attendance = (strpos($hr_script, 'attendance_report.php') !== false) ? 'active' : '';
$active_payroll = (strpos($hr_script, 'payroll.php') !== false) ? 'active' : '';
$active_payslip = (strpos($hr_script, 'payslip.php') !== false) ? 'active' : '';
$active_settings = (strpos($hr_script, 'hr_settings.php') !== false) ? 'active' : '';
$active_leaves = (strpos($hr_script, 'hr_leaves.php') !== false) ? 'active' : '';
$active_assets = (strpos($hr_script, 'companyassets.php') !== false) ? 'active' : '';
$active_offer = (strpos($hr_script, 'offer_letters.php') !== false) ? 'active' : '';
?>
<?php
// header.php - Partial UI component (Sidebar and Navbar only)
// This file should be included AFTER htmlopen.php
?>
<div class="main-wrapper">
  <style>
    .tablehiddenrows {
      transition: opacity 0.5s ease-out, max-height 0.5s ease-out;
      display: none;
    }
    /* Header Notification Dropdown */
    .header-notification-popup {
      position: absolute;
      top: 75px;
      right: 10px;
      width: 360px;
      max-height: 480px;
      background: rgba(255, 255, 255, 0.9) !important;
      backdrop-filter: blur(25px) saturate(180%) !important;
      border: 1px solid rgba(255, 255, 255, 0.3) !important;
      border-radius: 20px;
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.3) !important;
      z-index: 9999;
      display: none;
      flex-direction: column;
      overflow: hidden;
      animation: popupSlide 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
    .notification-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 1rem 1.25rem;
      border-bottom: 1px solid var(--border-color, #e2e8f0);
      background: rgba(255, 255, 255, .7);
    }
    .notification-header h3 {
      font-size: 1.1rem;
      font-weight: 600;
      color: #333;
      margin: 0;
    }
    .mark-all-read {
      background: 0 0;
      border: none;
      color: #227477;
      font-size: .85rem;
      font-weight: 500;
      cursor: pointer;
      padding: .25rem .5rem;
      border-radius: 6px;
    }
    .closebtn-notif:hover {
      color: #dc2626 !important;
      transform: scale(1.1);
    }
    .notification-list {
      flex: 1;
      overflow-y: auto;
      max-height: 380px;
      scrollbar-width: none;
    }
    .notification-item {
      display: flex;
      align-items: center;
      padding: 1rem 1.25rem;
      gap: .75rem;
      border-bottom: 1px solid var(--border-color, #e2e8f0);
      position: relative;
      cursor: pointer;
      transition: background 0.2s;
    }
    .notification-item:hover {
      background: #f8fafc;
    }
    .notification-item.unread::before {
      content: '';
      position: absolute;
      top: 50%;
      left: 6px;
      transform: translateY(-50%);
      width: 6px;
      height: 6px;
      border-radius: 50%;
      background: #227477;
    }
    .notification-icon {
      width: 36px;
      height: 36px;
      border-radius: 10px;
      background: rgba(34, 116, 119, .1);
      display: flex;
      align-items: center;
      justify-content: center;
      color: #227477;
      flex-shrink: 0;
    }
    .notification-content {
      flex: 1;
    }
    .notification-content p {
      margin: 0 0 .25rem;
      color: #333;
      font-size: .95rem;
      line-height: 1.4;
    }
    .notification-time {
      font-size: .75rem;
      color: #64748b;
    }
    .notification-footer {
      padding: .75rem 1.25rem;
      border-top: 1px solid var(--border-color, #e2e8f0);
      background: rgba(255, 255, 255, .7);
      text-align: center;
    }
    .view-all {
      color: #227477;
      font-weight: 500;
      font-size: .9rem;
      text-decoration: none;
    }
    /* Dark mode overrides */
    body.dark-mode .header-notification-popup {
      background: rgba(40, 40, 40, 1) !important;
      border: 1px solid rgba(255, 255, 255, 0.1) !important;
      box-shadow: 0 12px 40px rgba(0, 0, 0, 0.6) !important;
    }
    body.dark-mode .notification-header,
    body.dark-mode .notification-footer {
      background: rgba(255, 255, 255, 0.03) !important;
      border-color: rgba(255, 255, 255, 0.05) !important;
    }
    body.dark-mode .notification-header h3,
    body.dark-mode .notification-footer .view-all {
      color: #fff !important;
    }
    body.dark-mode .notification-item {
      border-color: rgba(255, 255, 255, 0.1) !important;
    }
    body.dark-mode .notification-item:hover {
      background: rgba(255, 255, 255, 0.08) !important;
    }
    body.dark-mode .notification-content p {
      color: #eee !important;
    }
    body.dark-mode .notification-time {
      color: #aaa !important;
    }
    /* --- Premium Profile Popup Enhancements --- */
    .user-info-popup {
      border: 1px solid rgba(255, 255, 255, 0.3) !important;
      backdrop-filter: blur(25px) saturate(180%) !important;
      background: rgba(255, 255, 255, 0.8) !important;
      overflow: hidden;
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25) !important;
      animation: popupSlide 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
    @keyframes popupSlide {
      from { opacity: 0; transform: translateY(10px) scale(0.95); }
      to { opacity: 1; transform: translateY(0) scale(1); }
    }
    .user-info-header {
      background: linear-gradient(135deg, #1e6063 0%, #2a8c90 100%) !important;
      padding: 30px 20px !important;
      border-bottom: 4px solid rgba(255, 255, 255, 0.1);
    }
    .user-avatar-large {
      width: 80px !important;
      height: 80px !important;
      border: 4px solid rgba(255, 255, 255, 0.2) !important;
      box-shadow: 0 8px 16px rgba(0,0,0,0.2);
      transition: transform 0.3s ease;
    }
    .user-avatar-large:hover {
      transform: scale(1.05) rotate(3deg);
    }
    .user-details h4 {
      font-size: 1.25rem !important;
      letter-spacing: -0.02em;
      margin-bottom: 4px !important;
      text-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .user-info-body {
      padding: 12px !important;
    }
    .user-info-item {
      padding: 14px 18px !important;
      margin: 4px 0 !important;
      font-weight: 600 !important;
      font-size: 0.92rem !important;
      border-radius: 12px !important;
      border: 1px solid transparent;
      transition: all 0.2s ease !important;
    }
    .user-info-item i {
      font-size: 1.1rem;
      transition: transform 0.2s ease;
    }
    .user-info-item:hover {
      background: rgba(42, 140, 144, 0.1) !important;
      border-color: rgba(42, 140, 144, 0.1);
      transform: translateX(5px) !important;
    }
    .user-info-item:hover i {
      transform: scale(1.2);
    }
    .user-info-item.logout-btn {
      color: #dc2626 !important;
      background: rgba(220, 38, 38, 0.03) !important;
    }
    .user-info-item.logout-btn:hover {
      background: rgba(220, 38, 38, 0.08) !important;
      border-color: rgba(220, 38, 38, 0.1);
      color: #b91c1c !important;
    }
    /* Dark mode adjustments for popup */
    body.dark-mode .user-info-popup {
      background: rgba(15, 23, 42, 0.9) !important;
      border-color: rgba(255, 255, 255, 0.1) !important;
    }
    body.dark-mode .user-info-item {
      color: #e2e8f0 !important;
    }
    body.dark-mode .user-info-item:hover {
      background: rgba(255, 255, 255, 0.05) !important;
    }
    .user-profile-sidebar {
      border: 2px solid rgba(42, 140, 144, 0.1) !important;
      padding: 2px !important;
      background: white !important;
      box-shadow: 0 4px 10px rgba(0,0,0,0.05);
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
    }
    .user-profile-sidebar:hover {
      border-color: rgba(42, 140, 144, 0.4) !important;
      transform: scale(1.05) translateY(-1px);
      box-shadow: 0 6px 15px rgba(0,0,0,0.08);
    }

    /* Toggle Switch Styles */
    .theme-switch {
      position: relative;
      display: inline-block;
      width: 40px;
      height: 22px;
    }
    .theme-switch input {
      opacity: 0;
      width: 0;
      height: 0;
    }
    .slider {
      position: absolute;
      cursor: pointer;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background-color: #ccc;
      transition: .4s;
      border-radius: 34px;
    }
    .slider:before {
      position: absolute;
      content: "";
      height: 16px;
      width: 16px;
      left: 3px;
      bottom: 3px;
      background-color: white;
      transition: .4s;
      border-radius: 50%;
    }
    input:checked + .slider {
      background-color: #227477;
    }
    input:checked + .slider:before {
      transform: translateX(18px);
    }
    body.dark-mode .slider {
      background-color: rgba(255, 255, 255, 0.2);
    }
    body.dark-mode input:checked + .slider {
      background-color: #525252;
    }

    /* Sidebar header toggle - Hidden by default, shown only on mobile */
    .sidebar-header-toggle {
      display: none !important;
    }

    .hr-navbar {
      z-index: 99999 !important;
    }

    /* Desktop UI Fixes: Hiding mobile elements on desktop */
    @media (min-width: 1025px) {
      .navbar-left-items, .mobile-logo { 
        display: none !important; 
      }
    }

    /* YouTube Style Mobile Header (Up to 1024px) */
    @media (max-width: 1024px) {
      body { padding-top: 56px !important; }
      
      .hr-navbar {
        display: flex !important;
        flex-direction: row !important;
        justify-content: space-between !important;
        align-items: center !important;
        padding: 0 16px 0 8px !important;
        height: 56px !important;
        background: rgba(255, 255, 255, 0.15) !important;
        backdrop-filter: blur(12px) !important;
        -webkit-backdrop-filter: blur(12px) !important;
        border-bottom: none !important;
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        right: 0 !important;
        width: 100% !important;
        z-index: 2000 !important;
        box-shadow: none !important;
      }

      /* Hide navbar right items (notifications and profile) on mobile */
      .navbar-right-items {
        display: none !important;
      }
      
      /* Hide navbar logo when sidebar is open to avoid overlap */
      body.sidebar-open .navbar-left-items {
        opacity: 0 !important;
        visibility: hidden !important;
      }
      
      body.dark-mode .hr-navbar {
        background: rgba(17, 24, 39, 0.15) !important;
        backdrop-filter: blur(12px) !important;
        -webkit-backdrop-filter: blur(12px) !important;
        border-bottom: none !important;
      }
      
      .navbar-left-items {
        display: flex !important;
        align-items: center !important;
        gap: 12px !important;
      }
      
      .mobile-menu-toggle {
        display: flex !important;
        order: 1 !important;
        background: none !important;
        border: none !important;
        padding: 4px !important;
        color: var(--text-primary, #333) !important;
        cursor: pointer;
      }
      
      body.dark-mode .mobile-menu-toggle { color: #fff !important; }
      
      .mobile-logo-link {
        order: 2 !important;
        display: flex !important;
        align-items: center !important;
      }

      .mobile-logo {
        display: block !important;
        height: 28px !important;
        width: auto !important;
      }

      .sidebar-header-toggle {
        display: flex !important;
        background: none !important;
        border: none !important;
        color: var(--text-primary, #333) !important;
        font-size: 1.8rem !important;
        padding: 0 !important;
        cursor: pointer !important;
        align-items: center;
        justify-content: center;
        position: relative !important;
        z-index: 1000 !important;
        width: 36px !important;
        height: 48px !important;
        pointer-events: all !important;
      }
      body.dark-mode .sidebar-header-toggle { color: #fff !important; }
        
      .sidebar .incentivelogo {
        display: flex !important;
        flex-direction: row !important;
        align-items: center !important;
        justify-content: flex-start !important;
        padding: 0 4px !important;
        height: 64px !important;
        gap: 0 !important;
        border-bottom: 1px solid rgba(0,0,0,0.05);
      }
        
      body.dark-mode .sidebar .incentivelogo {
        border-bottom-color: rgba(255,255,255,0.05);
      }

      .sidebar .logo {
        display: flex !important;
        align-items: center !important;
        margin: 0 !important;
        padding: 0 !important;
      }
        
      .sidebar .logo-expanded {
        height: 42px !important;
        width: auto !important;
        display: block !important;
        margin-left: -2px !important;
      }

      /* Click outside to close - Sidebar Overlay */
      .sidebar-overlay {
        display: block !important;
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        right: 0 !important;
        bottom: 0 !important;
        background: rgba(0, 0, 0, 0.5) !important;
        z-index: 100000 !important;
        opacity: 0 !important;
        visibility: hidden !important;
        transition: opacity 0.3s ease, visibility 0.3s ease !important;
        pointer-events: auto !important;
      }

      body.sidebar-open .sidebar-overlay {
        opacity: 1 !important;
        visibility: visible !important;
      }

      .sidebar {
        z-index: 100001 !important;
        background: linear-gradient(135deg, rgb(209, 229, 230) 0%, rgb(202, 242, 245) 100%) !important;
        box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1) !important;
        border-right: 1px solid rgba(0, 0, 0, 0.05) !important;
      }

      body.dark-mode .sidebar {
        background: #1a1a1a !important;
        border-right: 1px solid #3d3d3d !important;
      }
      
      .navbar-right-items {
        display: flex !important;
        align-items: center !important;
        gap: 8px !important;
        margin-top: 0 !important;
      }
      
      .header-btn {
        background: none !important;
        border: none !important;
        color: var(--text-primary, #333) !important;
        font-size: 1.25rem !important;
        padding: 4px !important;
      }
      
      #notificationBtn {
        width: 32px !important;
        height: 32px !important;
        background: rgba(34, 116, 119, 0.1) !important;
      }
      
      body.dark-mode .header-btn { color: #fff !important; }
      
      .user-profile-sidebar {
        width: 32px !important;
        height: 32px !important;
        border-radius: 50% !important;
        overflow: hidden !important;
        border: none !important;
        padding: 0 !important;
        background: none !important;
      }
      
      .user-avatar-small {
        width: 100% !important;
        height: 100% !important;
        object-fit: cover !important;
      }
      
      /* Hide elements not needed in mobile navbar */
      .navbar-right-items > .user-info { display: none !important; }
      .header-btn i.fa-bell { font-size: 1.2rem; }
    }
    .sidebar.close .theme-text,
    .sidebar.close .theme-switch {
      display: none !important;
    }
    .sidebar.close .theme-actions-inner {
      justify-content: center !important;
      padding-left: 0 !important;
    }
  </style>
</head>
<body>
  <!-- Loader -->
  <div class="loading bodyloader" id="loader" style="display:none">
    <div class="loading__container">
      <div class="loading__ring loading__ring--orange"></div>
      <div class="loading__ring loading__ring--green"></div>
      <div class="loading__ring loading__ring--blue"></div>
    </div>
  </div>
  <div class="incentivemain">
    <!-- Navbar: Parity with Super Admin -->
    <nav class="navbar hr-navbar" style="position: relative;">
      <div class="navbar-left-items">
        <button type="button" class="mobile-menu-toggle" aria-label="Toggle menu" onclick="toggleleftsidebar()">
          <i class="bi bi-list toggle-icon-mobile" style="font-size: 1.5rem;"></i>
        </button>
        <a href="dashboard.php" class="mobile-logo-link" style="text-decoration: none; display: flex; align-items: center;">
          <span style="font-weight: 700; letter-spacing: -0.05em; font-size: 20px; color: #006A80;">SEARCHHOMES</span>
          <span style="font-size: 10px; font-weight: 700; color: #006A80; margin-left: 4px; align-self: flex-end; margin-bottom: 2px;">INDIA</span>
        </a>
      </div>

      <div class="navbar-right-items" style="display: flex; align-items: center; justify-content: flex-end; gap: 16px; margin-top: 16px;">
        <button class="header-btn" id="notificationBtn" style="display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; padding: 0; margin: 0; border-radius: 50%; background: rgba(34, 116, 119, 0.1); border: none; position: relative;">
          <span id="notifBadge" style="display:none"></span>
          <i class="fas fa-bell" style="color: var(--primary-teal); font-size: 1.2rem; margin: 0;"></i>
          <div class="notification-badge"></div>
        </button>
        <div class="header-notification-popup" id="notifDropdownPopup" style="display: none; top: 60px;"></div>
        <div class="user-profile-sidebar" id="more_profile_icon" style="display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 50%; padding: 0 !important; margin: 0; cursor: pointer;">
          <img class="user-avatar-small" src="<?php echo $hr_avatar_src; ?>" alt="" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover; margin: 0;">
          <div class="user-info" style="display: none;">
            <div class="user-name-small"></div>
          </div>
        </div>
      </div>
      <!-- Profile Content Popup matching Super Admin -->
      <div class="profile-content user-info-popup" id="profile-content_box" style="display: none;">
        <div class="user-info-header">
          <div class="closebtn1" onclick="document.getElementById('profile-content_box').style.display='none'"><i
              class="bi bi-x-circle-fill"></i></div>
          <div class="user-avatar-large">
            <img src="<?php echo $hr_avatar_src; ?>" alt="User Avatar">
          </div>
          <div class="user-details">
            <h4 style="margin:0; font-weight:700;">
              <?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'HR Admin'; ?></h4>
            <p style="margin:0; opacity:0.8; font-size:0.9rem;">Administrator</p>
          </div>
        </div>
        <div class="user-info-body">
          <a href="profile.php" class="user-info-item">
            <i class="bi bi-person-circle"></i> My Profile
          </a>
          <a href="logout.php" class="user-info-item logout-btn">
            <i class="bi bi-box-arrow-right"></i> Logout
          </a>
        </div>
      </div>
    </nav>
    <!-- Sidebar: 100% Parity with Super Admin Shell -->
    <!-- Mobile Overlay moved outside sidebar for proper stacking -->
    <div class="sidebar-overlay" onclick="toggleleftsidebar()"></div>
      <div class="sidebar" id="sidebar">
          <div class="incentivelogo">
            <button type="button" class="sidebar-header-toggle" onclick="event.stopPropagation(); document.body.classList.remove('sidebar-open');">
              <i class="bi bi-list"></i>
            </button>
            <a href="dashboard.php" class="logo" style="text-decoration: none; display: flex; align-items: center; padding-left: 10px;">
              <img class="logo-expanded" src="../superadmin/assets/dataimage/hlogo.png" alt="Logo" />
              <img class="logo-collapsed" src="../superadmin/assets/dataimage/mecntec-icon.png" alt="Logo" />
            </a>
          </div>
      <div style="flex: 1; overflow-y: auto; overflow-x: hidden;">
        <ul class="side-menu">
          <li class="<?php echo $active_dashboard; ?>">
            <a href="dashboard.php">
              <i class="bi bi-grid-fill iconclassforall"></i>
              <span class="menu-title">Dashboard</span>
            </a>
          </li>
          <li class="<?php echo $active_users; ?>">
            <a href="users.php">
              <i class="bi bi-people-fill iconclassforall"></i>
              <span class="menu-title">Employees</span>
            </a>
          </li>
          <li class="<?php echo $active_attendance; ?>">
            <a href="attendance_report.php">
              <i class="bi bi-calendar-check-fill iconclassforall"></i>
              <span class="menu-title">Attendance Report</span>
            </a>
          </li>
          <li class="<?php echo (strpos($hr_script, 'live_tracking.php') !== false) ? 'active' : ''; ?>">
            <a href="live_tracking.php">
              <i class="bi bi-geo-alt-fill iconclassforall"></i>
              <span class="menu-title">Live Tracking</span>
            </a>
          </li>
          <li class="<?php echo $active_leaves; ?>">
            <a href="hr_leaves.php">
                <i class="bi bi-calendar-event-fill iconclassforall"></i>
                <span class="menu-title">Leave Management</span>
            </a>
          </li>
          <li class="<?php echo $active_payroll; ?>">
            <a href="payroll.php">
              <i class="bi bi-wallet-fill iconclassforall"></i>
              <span class="menu-title">Payroll</span>
            </a>
          </li>
          <li class="<?php echo $active_offer; ?>">
            <a href="offer_letters.php">
              <i class="bi bi-file-earmark-person-fill iconclassforall"></i>
              <span class="menu-title">Offer Letter</span>
            </a>
          </li>
          <li class="<?php echo $active_payslip; ?>">
            <a href="payslip.php">
              <i class="bi bi-file-earmark-text-fill iconclassforall"></i>
              <span class="menu-title">Payslip</span>
            </a>
          </li>
          <li class="<?php echo $active_assets; ?>">
            <a href="companyassets.php">
                <i class="bi bi-box-seam-fill iconclassforall"></i>
                <span class="menu-title">Company Assets</span>
            </a>
          </li>

          <li class="<?php echo $active_settings; ?>">
            <a href="hr_settings.php">
              <i class="bi bi-gear-fill iconclassforall"></i>
              <span class="menu-title">HR Settings</span>
            </a>
          </li>
          <li>
            <a href="logout.php" style="color: #ef4444 !important;">
              <i class="bi bi-box-arrow-right iconclassforall" style="color: #ef4444 !important;"></i>
              <span class="menu-title">Logout</span>
            </a>
          </li>
        </ul>
      </div>
      <!-- Theme Actions -->
      <div class="theme-actions-container" style="padding: 20px 10px; border-top: 1px solid rgba(0,0,0,0.03);">
        <div class="theme-actions-inner" style="display: flex; align-items: center; justify-content: space-between; padding-left: 10px;">
          <div style="display: flex; align-items: center; gap: 10px; color: var(--primary-teal-dark);">
            <i id="darkModeIcon" class="bi bi-moon-fill iconclassforall"></i>
            <span class="menu-title theme-text" style="font-size: 0.85rem; font-weight: 600;">Dark Mode</span>
          </div>
          <label class="theme-switch">
            <input type="checkbox" id="themeToggleSuperadmin" onchange="toggleDarkMode()">
            <span class="slider"></span>
          </label>
        </div>
      </div>
      <div id="sidebar-toggle" class="sidebar-toggle-main" onclick="toggleleftsidebar()">
        <i class="bi bi-chevron-left toggle-icon"></i>
      </div>
    </div>
    <script>
      // Theme Logic
      function toggleDarkMode() {
        const body = document.body;
        const isDark = body.classList.toggle('dark-mode');
        localStorage.setItem('theme', isDark ? 'dark' : 'light');
        updateThemeIcons();
      }
      function updateThemeIcons() {
        const isDark = document.body.classList.contains('dark-mode');
        const icon = document.getElementById('darkModeIcon');
        const checkbox = document.getElementById('themeToggleSuperadmin');
        if (icon) icon.className = isDark ? 'bi bi-sun-fill' : 'bi bi-moon-fill';
        if (checkbox) checkbox.checked = isDark;
      }
      // Sidebar Logic
      function toggleleftsidebar() {
        const sidebar = document.getElementById('sidebar');
        const body = document.body;
        const isMobile = window.innerWidth <= 1024;
        if (isMobile) {
          // Mobile: Drawer mode
          body.classList.toggle('sidebar-open');
          // Ensure collapsed state is off on mobile
          body.classList.remove('sidebar-collapsed');
          sidebar.classList.remove('close');
        } else {
          // Desktop: Collapse mode
          const isClosed = sidebar.classList.toggle('close');
          body.classList.toggle('sidebar-collapsed', isClosed);
          localStorage.setItem('sidebarCollapsed', isClosed);
          // Ensure drawer state is off on desktop
          body.classList.remove('sidebar-open');
        }
      }
      // Initialize state
      document.addEventListener('DOMContentLoaded', () => {
        if (localStorage.getItem('theme') === 'dark') {
          document.body.classList.add('dark-mode');
        }
        if (localStorage.getItem('sidebarCollapsed') === 'true') {
          document.getElementById('sidebar')?.classList.add('close');
          document.body.classList.add('sidebar-collapsed');
        }
        updateThemeIcons();

        // Toggle theme on clicking the theme action container if sidebar is closed (or click is not on the switch)
        const themeActionsInner = document.querySelector('.theme-actions-inner');
        if (themeActionsInner) {
          themeActionsInner.style.cursor = 'pointer';
          themeActionsInner.addEventListener('click', (e) => {
            if (!e.target.closest('.theme-switch')) {
              toggleDarkMode();
            }
          });
        }
        
        // Link global search to DataTables if available
        const searchInput = document.getElementById('globalSearchInput');
        if (searchInput && window.jQuery) {
          searchInput.addEventListener('input', function() {
            if (window.table) window.table.search(this.value).draw();
            else if (window.$ && $.fn.DataTable.isDataTable('#example')) {
              $('#example').DataTable().search(this.value).draw();
            }
          });
        }
      });
      // Notification Logic
      document.addEventListener('DOMContentLoaded', () => {
        const notifBtn = document.getElementById('notificationBtn');
        const popup = document.getElementById('notifDropdownPopup');
        const badge = document.getElementById('notifBadge');
        if (notifBtn && popup) {
          const notifApiBase = '../superadmin/notifications';
          // Inject structure once
          popup.innerHTML = `
            <div class="notification-header">
              <h3>Notifications</h3>
              <div style="display: flex; align-items: center; gap: 12px;">
                <button class="mark-all-read" id="saMarkAllBtn">Mark all as read</button>
                <div class="closebtn-notif" id="saCloseNotifBtn" style="cursor: pointer; color: var(--text-gray, #64748b); font-size: 1.15rem; display: flex; align-items: center; justify-content: center; transition: all 0.2s;" onclick="document.getElementById('notifDropdownPopup').style.display='none'">
                  <i class="bi bi-x-circle-fill"></i>
                </div>
              </div>
            </div>
            <div class="notification-search-wrapper" style="padding:0.6rem 1rem;border-bottom:1px solid var(--border-color,#e2e8f0);">
              <input type="text" id="saNotifSearch" placeholder="Search notifications…"
                style="width:100%;padding:0.45rem 0.75rem;border:1px solid var(--border-color,#ddd);border-radius:8px;font-size:0.88rem;outline:none;">
            </div>
            <div class="notification-list" id="saNotifList"></div>
            <div class="notification-footer">
              <a href="#" class="view-all">View All Notifications</a>
            </div>
          `;
          const listEl = document.getElementById('saNotifList');
          const notifSearchInput = document.getElementById('saNotifSearch');
          const markAllBtn = document.getElementById('saMarkAllBtn');
          let notifications = [];
          let offset = 0;
          const limit = 10;
          let searchQuery = '';
          let isLoading = false;
          let hasMore = true;
          notifBtn.addEventListener('click', e => {
            e.stopPropagation();
            const isVisible = popup.style.display === 'flex';
            if (document.getElementById('profile-content_box')) {
              document.getElementById('profile-content_box').style.display = 'none';
            }
            if (!isVisible) {
              popup.style.display = 'flex';
              resetAndLoad();
            } else {
              popup.style.display = 'none';
            }
          });
          document.addEventListener('click', e => {
            if (!popup.contains(e.target) && !notifBtn.contains(e.target)) {
              popup.style.display = 'none';
            }
          });

          // Profile Popup Logic
          const profileBtn = document.getElementById('more_profile_icon');
          const profilePopup = document.getElementById('profile-content_box');
          if (profileBtn && profilePopup) {
            profileBtn.addEventListener('click', e => {
              e.stopPropagation();
              const isVisible = profilePopup.style.display === 'block';
              if (popup) popup.style.display = 'none'; // Close notif if open
              profilePopup.style.display = isVisible ? 'none' : 'block';
            });
            document.addEventListener('click', e => {
              if (!profilePopup.contains(e.target) && !profileBtn.contains(e.target)) {
                profilePopup.style.display = 'none';
              }
            });
          }
          // Search Listener
          if (notifSearchInput) {
            notifSearchInput.addEventListener('input', debounce(() => {
              searchQuery = notifSearchInput.value.trim();
              resetAndLoad();
            }, 300));
          }

          // Mark All Read Listener
          if (markAllBtn) {
            markAllBtn.addEventListener('click', async () => {
              const unreadIds = notifications.filter(n => n.is_read == 0).map(n => n.notification_id);
              if (unreadIds.length > 0) {
                const success = await markAsRead(unreadIds);
                if (success) {
                  notifications.forEach(n => n.is_read = 1);
                  renderList(notifications, true);
                  updateBadge(0);
                }
              }
            });
          }

          async function resetAndLoad() {
            offset = 0;
            hasMore = true;
            notifications = [];
            listEl.innerHTML = '<div style="padding:1.5rem;text-align:center;color:#888;"><i class="fas fa-circle-notch fa-spin"></i> Loading…</div>';
            await loadMore();
          }

          async function loadMore() {
            if (isLoading || !hasMore) return;
            isLoading = true;
            try {
              let url = `${notifApiBase}/get-notifications.php?limit=${limit}&offset=${offset}`;
              if (searchQuery) url += `&q=${encodeURIComponent(searchQuery)}`;
              
              const res = await fetch(url, { credentials: 'same-origin' });
              if (!res.ok) throw new Error(`HTTP ${res.status}`);
              
              const data = await res.json();
              if (data.status === 'success') {
                const newItems = data.notifications || [];
                if (offset === 0) notifications = newItems;
                else notifications = notifications.concat(newItems);
                
                hasMore = data.has_more;
                offset += newItems.length;
                renderList(notifications, offset === newItems.length);
                updateBadge(data.unread_count);
              } else {
                throw new Error(data.message || 'Unknown API error');
              }
            } catch (err) {
              console.error('Notif load error:', err);
              if (offset === 0) {
                listEl.innerHTML = `<div style="padding:2rem;text-align:center;color:#ef4444;">
                  <i class="fas fa-exclamation-circle" style="font-size:2rem;margin-bottom:10px;"></i>
                  <p>Failed to load notifications.</p>
                  <button onclick="location.reload()" class="btn btn-sm btn-outline-danger" style="margin-top:10px;">Retry</button>
                </div>`;
              }
            } finally {
              isLoading = false;
            }
          }
          function debounce(fn, wait) {
            let t; return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), wait); };
          }

          function renderList(items, replace = true) {
            if (replace) listEl.innerHTML = '';
            if (!items || items.length === 0) {
              listEl.innerHTML = '<div style="padding:2rem;text-align:center;color:#888;">No notifications found</div>';
              return;
            }
            items.forEach(n => {
              const item = document.createElement('div');
              item.className = 'notification-item' + (n.is_read == 0 ? ' unread' : '');
              let iconClass = 'fa-bell';
              if (n.type === 'lead') iconClass = 'fa-user-plus';
              if (n.type === 'alert') iconClass = 'fa-exclamation-circle';
              item.innerHTML = `
                <div class="notification-icon"><i class="fas ${iconClass}"></i></div>
                <div class="notification-content">
                  <p>${n.body || n.message || ''}</p>
                  <div class="notification-time">${formatTimeAgo(n.created_at)}</div>
                </div>
              `;
              item.addEventListener('click', async () => {
                if (n.is_read == 0) {
                  const success = await markAsRead([n.notification_id]);
                  if (success) item.classList.remove('unread');
                }
              });
              listEl.appendChild(item);
            });
          }
          async function markAsRead(ids) {
            try {
              const res = await fetch(`${notifApiBase}/mark-read.php`, {
                method: 'POST',
                headers: {
                  'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                  notification_ids: ids
                }),
                credentials: 'same-origin'
              });
              return res.ok;
            } catch (err) {
              return false;
            }
          }
          function updateBadge(count) {
            if (!badge) return;
            if (count > 0) {
              badge.textContent = count;
              badge.style.display = 'flex';
            } else {
              badge.style.display = 'none';
            }
          }
          function formatTimeAgo(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString.replace(' ', 'T'));
            const s = Math.floor((Date.now() - date.getTime()) / 1000);
            if (s < 60) return 'Just now';
            if (s < 3600) return Math.floor(s / 60) + 'm ago';
            if (s < 86400) return Math.floor(s / 3600) + 'h ago';
            return Math.floor(s / 86400) + 'd ago';
          }
          // Initial count
          fetch(`${notifApiBase}/get-notifications.php?limit=1&offset=0`, {
              credentials: 'same-origin'
            })
            .then(r => r.ok ? r.json() : {status: 'error'})
            .then(data => {
              if (data && data.status === 'success') updateBadge(data.unread_count);
            })
            .catch(err => console.log('Notifications unavailable (Silent)'));
        }
      });
    </script>