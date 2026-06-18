<?php
// Get current page path to determine active menu item
$current_path = isset($_SERVER['REQUEST_URI']) ? strtok($_SERVER['REQUEST_URI'], '?') : '';

// Determine which menu item should be active
$active_dashboard = (strpos($current_path, '/incentiveapp_integration/userlogin1/superadmin/dashboard') !== false || strpos($current_path, '/superadmin/dashboard') !== false || $current_path === '/dashboard' || basename($_SERVER['PHP_SELF']) === 'dashboard.php') ? 'sideactive' : '';
$active_property = (strpos($current_path, '/incentiveapp_integration/userlogin1/superadmin/property-bookings') !== false || strpos($current_path, '/superadmin/property-bookings') !== false || basename($_SERVER['PHP_SELF']) === 'property-bookings.php') ? 'sideactive1' : '';
$active_expenses = (strpos($current_path, '/incentiveapp_integration/userlogin1/superadmin/expenses') !== false || strpos($current_path, '/superadmin/expenses') !== false || basename($_SERVER['PHP_SELF']) === 'expenses.php') ? 'sideactive2' : '';
$active_incentive = (strpos($current_path, '/incentiveapp_integration/userlogin1/superadmin/incentive-tracking') !== false || strpos($current_path, '/superadmin/incentive-tracking') !== false || basename($_SERVER['PHP_SELF']) === 'incentive-tracking.php') ? 'sideactive3' : '';
$active_payment = (strpos($current_path, '/incentiveapp_integration/userlogin1/superadmin/payment-tracking') !== false || strpos($current_path, '/superadmin/payment-tracking') !== false || basename($_SERVER['PHP_SELF']) === 'payment-tracking.php') ? 'sideactive4' : '';
$active_assets = (strpos($current_path, '/incentiveapp_integration/userlogin1/superadmin/companyassets') !== false || strpos($current_path, '/superadmin/companyassets') !== false || basename($_SERVER['PHP_SELF']) === 'companyassets.php') ? 'sideactive5' : '';
$active_users = (strpos($current_path, '/incentiveapp_integration/userlogin1/superadmin/users') !== false || strpos($current_path, '/superadmin/users') !== false || basename($_SERVER['PHP_SELF']) === 'users.php') ? 'sideactive6' : '';
$active_alert = (strpos($current_path, '/incentiveapp_integration/userlogin1/superadmin/superadmin_create_alert') !== false || strpos($current_path, '/superadmin/superadmin_create_alert') !== false || basename($_SERVER['PHP_SELF']) === 'superadmin_create_alert.php') ? 'sideactive7' : '';
$active_leads = (strpos($current_path, '/incentiveapp_integration/userlogin1/superadmin/crm/leads_data') !== false || strpos($current_path, '/superadmin/crm/leads_data') !== false || strpos($current_path, '/crm/leads_data') !== false || basename($_SERVER['PHP_SELF']) === 'leads_data.php') ? 'sideactive8' : '';
$active_cron_tracker = (
  strpos($current_path, '/incentiveapp_integration/userlogin1/superadmin/crm/cron_tracker') !== false ||
  strpos($current_path, '/superadmin/crm/cron_tracker') !== false ||
  strpos($current_path, '/crm/cron_tracker') !== false ||
  strpos($current_path, '/incentiveapp_integration/userlogin1/superadmin/crm/cron-tracker') !== false ||
  strpos($current_path, '/superadmin/crm/cron-tracker') !== false ||
  strpos($current_path, '/crm/cron-tracker') !== false ||
  basename($_SERVER['PHP_SELF']) === 'cron_tracker.php' ||
  basename($_SERVER['PHP_SELF']) === 'cron-tracker.php'
) ? 'sideactive9' : '';
$active_apis = (strpos($current_path, '/incentiveapp_integration/userlogin1/superadmin/myapicontainer/create-api-view/manage_apis') !== false || strpos($current_path, '/superadmin/myapicontainer/create-api-view/manage_apis') !== false || basename($_SERVER['PHP_SELF']) === 'manage_apis.php') ? 'sideactive70' : '';

// Flag to detect if we are on the superadmin dashboard page
$is_superadmin_dashboard_page = ($active_dashboard === 'sideactive');

// Resolve relative path for userlogin6 assets (differs when CRM pages include this header from a subfolder)
$userlogin6AssetsBase = (strpos($_SERVER['PHP_SELF'], '/myapicontainer/') !== false)
  ? '../../../userlogin6'
  : ((strpos($_SERVER['PHP_SELF'], '/crm/') !== false)
    ? '../../userlogin6'
    : '../userlogin6');

$superadminNotifBase = './notifications';
if (strpos($_SERVER['PHP_SELF'], '/crm/') !== false) {
  $superadminNotifBase = '../notifications';
} elseif (strpos($_SERVER['PHP_SELF'], '/myapicontainer/') !== false) {
  $superadminNotifBase = '../../notifications';
}
?>
<style>
  .tablehiddenrows {
    transition: opacity 0.5s ease-out, max-height 0.5s ease-out;
    display: none;
  }

  body {
    font-family: "Lexend Deca", sans-serif;
    font-optical-sizing: auto;
    font-style: normal;
  }

  /* Toggle for viewing userlogin6 dashboard from superadmin dashboard only */
  .userlogin6-toggle-wrapper {
    display: flex;
    align-items: center;
    font-size: 12px;
    white-space: nowrap;
  }

  .userlogin6-toggle-label {
    color: #4b5563;
    font-weight: 500;
    font-size: 13px;
    white-space: normal;
    line-height: 1.2;
    word-break: break-word;
    max-width: 110px;
    display: inline-block;
  }

  @media (max-width: 480px) {
    .userlogin6-toggle-label {
      font-size: 11px;
      max-width: 80px;
      line-height: 1.15;
      text-align: left;
      display: inline-block;
    }
  }

  .userlogin6-toggle-switch {
    position: relative;
    display: inline-block;
    width: 40px;
    height: 20px;
  }

  .userlogin6-toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
  }

  .userlogin6-toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #d1d5db;
    transition: .2s;
    border-radius: 999px;
  }

  .userlogin6-toggle-slider:before {
    position: absolute;
    content: "";
    height: 16px;
    width: 16px;
    left: 2px;
    bottom: 2px;
    background-color: white;
    transition: .2s;
    border-radius: 50%;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.25);
  }

  .userlogin6-toggle-switch input:checked+.userlogin6-toggle-slider {
    background-color: #2563eb;
  }

  .userlogin6-toggle-switch input:checked+.userlogin6-toggle-slider:before {
    transform: translateX(20px);
  }

  /* Menu Group Dropdown Styles */
  .menu-group {
    position: relative;
  }

  .menu-group-toggle {
    display: flex !important;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    padding: 8px 10px !important;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 600;
    color: #227477 !important;
    background: transparent;
    transition: var(--transition);
    cursor: pointer;
  }

  .menu-group-toggle .menu-title {
    flex: 1;
    text-align: left;
    transition: var(--transition);
  }

  .menu-group-toggle .menu-chevron {
    font-size: 12px;
    transition: transform 0.3s ease;
    margin-left: auto;
    color: #227477;
  }

  .menu-group-toggle:hover {
    background: rgba(42, 140, 144, 0.1) !important;
    color: #227477 !important;
    transform: none;
  }

  .menu-group.open .menu-chevron {
    transform: rotate(180deg);
  }

  /* Submenu Styles */
  .submenu {
    list-style: none;
    padding: 0;
    margin: 0;
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.3s ease, opacity 0.3s ease;
    opacity: 0;
    padding-left: 0px;
  }

  .menu-group.open .submenu {
    max-height: 600px;
    opacity: 1;
    margin-top: 4px;
    margin-bottom: 4px;
  }

  /* .submenu .sidemenuli {
    margin: 2px 0;
  } */

  .submenu .sidemenuli a {
    padding: 8px 18px 8px 4px !important;
    font-size: 0.85rem !important;
  }

  /* When sidebar is collapsed - Desktop hover submenu */
  body.sidebar-collapsed .sidebar,
  .sidebar.close {
    overflow: visible !important;
  }

  body.sidebar-collapsed .menu-group,
  .sidebar.close .menu-group {
    position: relative;
    overflow: visible !important;
  }

  body.sidebar-collapsed .menu-group-toggle .menu-title,
  body.sidebar-collapsed .menu-group-toggle .menu-chevron,
  .sidebar.close .menu-group-toggle .menu-title,
  .sidebar.close .menu-group-toggle .menu-chevron {
    opacity: 0;
    visibility: hidden;
    width: 0;
  }

  body.sidebar-collapsed .menu-group-toggle,
  .sidebar.close .menu-group-toggle {
    justify-content: center;
    padding: 12px 10px !important;
  }

  /* Collapsed sidebar - submenu appears on hover/click as popup */
  body.sidebar-collapsed .submenu,
  .sidebar.close .submenu {
    position: absolute;
    left: 20px;
    top: 0;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.2), 0 10px 10px -5px rgba(0, 0, 0, 0.1);
    padding: 8px;
    margin: 0;
    z-index: 5000;
    border: 1px solid rgba(226, 232, 240, 0.8);
    display: none;
    max-height: none;
    overflow: visible;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.2s ease, transform 0.2s ease;
    transform: translateX(10px);
  }

  body.sidebar-collapsed .menu-group.open .submenu,
  .sidebar.close .menu-group.open .submenu {
    display: block;
    opacity: 1;
    pointer-events: all;
    transform: translateX(20px);
  }

  body.sidebar-collapsed .submenu .sidemenuli a,
  .sidebar.close .submenu .sidemenuli a {
    padding: 10px 16px !important;
    font-size: 13px !important;
  }

  /* Highlight parent menu group when child is active */
  .menu-group.has-active-child>.menu-group-toggle {
    background: rgb(131 131 131 / 15%) !important;
    color: #227477 !important;
  }

  body.sidebar-collapsed .menu-group.has-active-child>.menu-group-toggle,
  .sidebar.close .menu-group.has-active-child>.menu-group-toggle {
    background: rgba(42, 140, 144, 0.2) !important;
  }

  /* Dark Mode Support */
  body.dark-mode .menu-group-toggle {
    color: #cbd5e0 !important;
  }

  body.dark-mode .menu-group-toggle:hover {
    background: rgba(255 255 255 /25%) !important;
    color: #4fd1c5 !important;
  }

  body.dark-mode .menu-chevron {
    color: #cbd5e0 !important;
  }

  body.dark-mode.sidebar-collapsed .menu-group.has-active-child>.menu-group-toggle,
  body.dark-mode .sidebar.close .menu-group.has-active-child>.menu-group-toggle {
    background: rgba(255, 255, 255, 0.1) !important;
  }

  /* Dark mode specific styles for absolute popover to fix visibility */
  body.dark-mode.sidebar-collapsed .submenu,
  body.dark-mode .sidebar.close .submenu {
    background: rgba(30, 30, 30, 0.95) !important;
    backdrop-filter: blur(12px) saturate(150%) !important;
    -webkit-backdrop-filter: blur(12px) saturate(150%) !important;
    border: 1px solid rgba(255, 255, 255, 0.12) !important;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.6), 0 0 0 1px rgba(255, 255, 255, 0.05) !important;
  }

  body.dark-mode.sidebar-collapsed .submenu .sidemenuli a,
  body.dark-mode .sidebar.close .submenu .sidemenuli a {
    color: rgba(255, 255, 255, 0.8) !important;
  }

  body.dark-mode.sidebar-collapsed .submenu .sidemenuli a:hover,
  body.dark-mode .sidebar.close .submenu .sidemenuli a:hover {
    background: rgba(255, 255, 255, 0.1) !important;
    color: #fff !important;
  }

  body.dark-mode.sidebar-collapsed .submenu .sidemenuli[class*="sideactive"] a,
  body.dark-mode .sidebar.close .submenu .sidemenuli[class*="sideactive"] a {
    color: #fff !important;
  }

  /* Mobile responsiveness */
  @media screen and (max-width: 1024px) {
    .submenu .sidemenuli a {
      padding: 8px 5px 8px 0px !important;
      font-size: 0.85rem !important;
    }

    body.sidebar-collapsed .submenu,
    .sidebar.close .submenu {
      position: static;
      background: transparent;
      box-shadow: none;
      border: none;
      padding: 0;
      margin: 0;
    }

    body.sidebar-collapsed .menu-group.open .submenu,
    .sidebar.close .menu-group.open .submenu {
      display: block;
      max-height: 600px;
      opacity: 1;
      margin-top: 4px;
    }

    body.sidebar-collapsed .submenu .sidemenuli a,
    .sidebar.close .submenu .sidemenuli a {
      padding: 10px 18px 10px 40px !important;
    }

    body.sidebar-collapsed .menu-group-toggle .menu-title,
    body.sidebar-collapsed .menu-group-toggle .menu-chevron,
    .sidebar.close .menu-group-toggle .menu-title,
    .sidebar.close .menu-group-toggle .menu-chevron {
      opacity: 1;
      visibility: visible;
      width: auto;
    }

    body.sidebar-collapsed .menu-group-toggle,
    .sidebar.close .menu-group-toggle {
      justify-content: space-between;
      padding: 12px 18px !important;
    }
  }

  /* Active submenu item highlighting */
  .submenu .sidemenuli.sideactive a,
  .submenu .sidemenuli.sideactive1 a,
  .submenu .sidemenuli.sideactive2 a,
  .submenu .sidemenuli.sideactive3 a,
  .submenu .sidemenuli.sideactive4 a,
  .submenu .sidemenuli.sideactive5 a,
  .submenu .sidemenuli.sideactive6 a,
  .submenu .sidemenuli.sideactive7 a,
  .submenu .sidemenuli.sideactive8 a,
  .submenu .sidemenuli.sideactive9 a,
  .submenu .sidemenuli.sideactive70 a {
    color: #fff !important;
    background: radial-gradient(circle at 20% 20%, #42A1A7 0%, transparent 25%),
      radial-gradient(circle at 70% 90%, #42A1A7 0%, transparent 30%),
      var(--primary-teal-dark) !important;
  }

  .modal-container-eoi .result-box {
    padding: 1rem;
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
    border-radius: 12px;
    text-align: center;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
  }

  .modal-container-eoi .form-actions .cancel-btn {
    background: #6b7280 !important;
    color: #fff !important;
    border-radius: 13px !important;
    padding: 10px 14px !important;
    border: none !important;
  }

  .modal-container-eoi .form-actions .filter-submit {
    padding: 10px 14px !important;
    border-radius: 13px !important;
  }

  /* Calculator-specific (non-colliding) classes */
  .modal-container-eoi .calc-fieldset {
    border: 1px solid black !important;
    border-radius: 10px !important;
    padding: 0.6rem !important;
    background: transparent !important;
    position: relative !important;
  }

  body.dark-mode .modal-container-eoi .calc-fieldset {
    border: 1px solid #ffffff7d !important;
  }

  .modal-container-eoi .calc-legend {
    width: fit-content;
    font-size: 0.75rem !important;
    color: #374151 !important;
    padding: 0 6px !important;
    position: absolute !important;
    top: -8px !important;
    left: 12px !important;
    background: #fff !important;
    font-weight: 600 !important;
  }

  body.dark-mode .modal-container-eoi .calc-legend {
    background: rgba(30, 30, 30, 0.95) !important;
    color: white !important;
  }

  .modal-container-eoi .calc-input {
    font-size: 0.875rem !important;
    padding: 0.5rem 0.75rem !important;
    border: none !important;
    border-radius: 8px !important;
    width: 100% !important;
    background: #fff !important;
    color: #111827 !important;
    box-shadow: none !important;
  }

  body.dark-mode .modal-container-eoi .calc-input {
    color: white !important;
    background: transparent !important;
  }

  .modal-container-eoi .calc-input::placeholder {
    color: #6b7280 !important;
  }

  .modal-container-eoi .calc-input:focus {
    outline: none !important;
    box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.06) !important;
  }

  .modal-container-eoi .calc-result-box {
    padding: 1rem;
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
    border-radius: 12px;
    text-align: center;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
  }

  .modal-container-eoi .calc-form-actions .calc-cancel-btn {
    background: #6b7280 !important;
    color: #fff !important;
    border-radius: 13px !important;
    padding: 10px 14px !important;
    border: none !important;
  }

  .modal-container-eoi .calc-form-actions .calc-submit-btn {
    background-color: #28a745 !important;
    color: white !important;
    border: none !important;
    font-weight: 600 !important;
    transition: all 0.3s ease !important;
    border-radius: 13px !important;
    padding: 10px 14px !important;
  }

  .modal-container-eoi .calc-form-actions .calc-submit-btn:hover {
    background-color: #218838 !important;
    transform: translateY(-1px) !important;
    box-shadow: 0 4px 8px rgba(40, 167, 69, 0.2) !important;
  }

  /* Dark Mode Toggle Switch (Match userlogin6) */
  .theme-switch {
    position: relative;
    width: 44px;
    height: 24px;
    margin-left: auto;
    flex-shrink: 0;
    display: inline-block;
  }

  .theme-switch input {
    opacity: 0;
    width: 0;
    height: 0;
  }

  .theme-switch .slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    border-radius: 24px;
    transition: .4s;
  }

  .theme-switch .slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background-color: #fff;
    border-radius: 50%;
    transition: .4s;
  }

  .theme-switch input:checked+.slider {
    background-color: #227477;
  }

  .theme-switch input:checked+.slider:before {
    transform: translateX(20px);
  }
</style>
</head>

<body>
  <!-- Loader html -->
  <div class="loader-container">
    <div class="loader">
      <div class="loader-logo">
        <img src="/incentiveapp_integration/userlogin1/superadmin/assets/dataimage/mecntec-icon.png" alt="logo">
      </div>
    </div>
  </div>
  <!-- Loader html End -->
  <!-- incentive main start -->
  <div class="incentivemain">

    <!-- Navbar -->
    <nav class="navbar">

      <!-- Right side items wrapper -->
      <div class="navbar-right-items">
        <?php if ($is_superadmin_dashboard_page): ?>
          <!-- Toggle: Show userlogin6 dashboard (Koushik promoter) inside superadmin dashboard -->
          <div class="userlogin6-toggle-wrapper">
            <span class="userlogin6-toggle-label">User Dashboard (Koushik)</span>
            <label class="userlogin6-toggle-switch" aria-label="Toggle Koushik user dashboard view">
              <input type="checkbox" id="userlogin6-dashboard-toggle" onchange="handleUserlogin6Toggle(this)">
              <span class="userlogin6-toggle-slider"></span>
            </label>
          </div>
        <?php endif; ?>

        <button class="header-btn tooltip" id="notificationBtn" data-tooltip="Notifications">
          <span id="notifBadge" style="display:none"></span>
          <i class="fas fa-bell"></i>
          <div class="notification-badge"></div>
        </button>

        <div class="header-notification-popup" id="notifDropdownPopup" style="display: none;"></div>

        <div class="user-profile-sidebar" id="more_profile_icon">
          <img class="user-avatar-small" src="<?php echo $userlogin6AssetsBase; ?>/assets/dataimage/ayu.jpg" alt="">
          <div class="user-info">
            <div class="user-name-small">
              <!-- User Name -->
            </div>
          </div>
        </div>
      </div>
      <!-- End navbar-right-items -->

      <button type="button" class="mobile-menu-toggle" aria-label="Toggle menu">
        <i class="bi bi-list toggle-icon-mobile"></i>
      </button>

      <?php if ($active_property === 'sideactive1'): ?>
        <!-- Adjustments/Filter Icon for Property Bookings Page - Swapped with hamburger -->
        <a href="#" class="adjustments-icon" id="rightSidebarToggleHeader" aria-label="Toggle actions menu">
          <i class="bi bi-sliders iconclassforall"></i>
        </a>
      <?php endif; ?>

      <div class="profile-content user-info-popup" id="profile-content_box">
        <div class="user-info-header">
          <div class="closebtn1"><i class="bi iconclassforall bi-x-circle-fill"></i></div>
          <div class="user-avatar-large">
            <img src="https://mnts.in/incentiveapp_integration/userlogin1/userlogin6/assets/dataimage/ayu.jpg"
              alt="User Avatar">
          </div>
          <div class="user-details">
            <h3><?php echo $nameuser ?></h3>
          </div>
        </div>
        <div class="user-info-actions">
          <a href="#." class="user-action-link">
            <i class="bi iconclassforall bi-lock-fill"></i> Forget Password
          </a>
          <a href="/userlogin" target="_blank" class="user-action-link">
            <i class="bi iconclassforall bi-box-arrow-right"></i> User login
          </a>
          <button class="user-action-link" onclick="submitLoginForm()">
            <i class="bi iconclassforall bi-box-arrow-right"></i> Hr login
          </button>
          <a href="../logout" class="user-action-link logout">
            <i class="bx bx-log-out-circle"></i> Logout
          </a>
        </div>
      </div>

    </nav>
    <!-- End of Navbar -->

    <!--Left Sidebar -->
    <div class="sidebar" id="sidebar">
      <div class="incentivelogo">
        <a href="#" class="logo" aria-label="SearchHomes India">
          <img class="logo-expanded" src="/incentiveapp_integration/userlogin1/superadmin/assets/dataimage/hlogo.png"
            alt="SearchHomes India" />
          <img class="logo-collapsed"
            src="/incentiveapp_integration/userlogin1/superadmin/assets/dataimage/mecntec-icon.png"
            alt="SearchHomes India" />
        </a>
      </div>
      <div class="sidebar-divider" aria-hidden="true"></div>
      <ul class="side-menu" id="side-menu">


        <!-- Main Menu Dropdown (includes Dashboard) -->
        <li class="sidemenuli menu-group">
          <a href="#" class="menu-group-toggle" onclick="toggleMenuGroup(event, 'mainmenu')">
            <i class="bi iconclassforall bi-grid-fill"></i>
            <span class="menu-title">Main Menu</span>
            <i class="bi bi-chevron-down menu-chevron"></i>
          </a>
          <ul class="submenu" id="submenu-mainmenu">
            <li class="sidemenuli <?php echo $active_dashboard; ?>">
              <a href="/incentiveapp_integration/userlogin1/superadmin/dashboard"
                class="nav-link<?php echo ($active_dashboard ? ' active' : ''); ?>">
                <i class="bi iconclassforall bi-back"></i>
                <span>Dashboard</span>
              </a>
            </li>
            <li class="sidemenuli <?php echo $active_property; ?>">
              <a href="/incentiveapp_integration/userlogin1/superadmin/property-bookings"
                class="nav-link<?php echo ($active_property ? ' active' : ''); ?>">
                <i class="bi iconclassforall bi-calendar-check-fill"></i>
                <span>Property Bookings</span>
              </a>
            </li>
            <li class="sidemenuli <?php echo $active_expenses; ?>">
              <a href="/incentiveapp_integration/userlogin1/superadmin/expenses"
                class="nav-link<?php echo ($active_expenses ? ' active' : ''); ?>">
                <i class="bi iconclassforall bi-currency-rupee"></i>
                <span>Company Expenses</span>
              </a>
            </li>
            <li class="sidemenuli <?php echo $active_incentive; ?>">
              <a href="/incentiveapp_integration/userlogin1/superadmin/incentive-tracking"
                class="nav-link<?php echo ($active_incentive ? ' active' : ''); ?>">
                <i class="bi iconclassforall bi-pie-chart-fill"></i>
                <span>Incentive Tracker</span>
              </a>
            </li>
          </ul>
        </li>


        <!-- CRM Dropdown -->
        <li class="sidemenuli menu-group">
          <a href="#" class="menu-group-toggle" onclick="toggleMenuGroup(event, 'crm')">
            <i class="bi bi-bar-chart-fill iconclassforall"></i>
            <span class="menu-title">CRM</span>
            <i class="bi bi-chevron-down menu-chevron"></i>
          </a>
          <ul class="submenu" id="submenu-crm">
            <li class="sidemenuli <?php echo $active_leads; ?>">
              <a href="/incentiveapp_integration/userlogin1/superadmin/crm/leads_data"
                class="nav-link<?php echo ($active_leads ? ' active' : ''); ?>">
                <i class="bi iconclassforall bi-database-fill"></i>
                <span>Leads Data</span>
              </a>
            </li>
            <li class="sidemenuli <?php echo $active_cron_tracker; ?>">
              <a href="/incentiveapp_integration/userlogin1/superadmin/crm/cron_tracker"
                class="nav-link<?php echo ($active_cron_tracker ? ' active' : ''); ?>">
                <i class="bi iconclassforall bi-clock-history"></i>
                <span>Cron Tracker</span>
              </a>
            </li>

            <?php if ($tablename !== 'subham323'): ?>
              <li class="sidemenuli <?php echo $active_alert; ?>">
                <a href="/incentiveapp_integration/userlogin1/superadmin/superadmin_create_alert"
                  class="nav-link<?php echo ($active_alert ? ' active' : ''); ?>">
                  <i class="bi iconclassforall bi-megaphone-fill"></i>
                  <span>Create Notice</span>
                </a>
              </li>

              <li class="sidemenuli <?php echo $active_apis; ?>">
                <a href="/incentiveapp_integration/userlogin1/superadmin/myapicontainer/create-api-view/manage_apis"
                  class="nav-link<?php echo ($active_apis ? ' active' : ''); ?>">
                  <i class="bi iconclassforall bi-gear-fill"></i>
                  <span>Global Configuration</span>
                </a>
              </li>
            <?php endif; ?>
          </ul>
        </li>

        <!-- Department Dropdown -->
        <li class="sidemenuli menu-group">
          <a href="#" class="menu-group-toggle" onclick="toggleMenuGroup(event, 'department')">
            <i class="bi bi-diagram-3-fill iconclassforall"></i>
            <span class="menu-title">Department</span>
            <i class="bi bi-chevron-down menu-chevron"></i>
          </a>
          <ul class="submenu" id="submenu-department">
            <li class="sidemenuli <?php echo $active_payment; ?>">
              <a href="/incentiveapp_integration/userlogin1/superadmin/payment-tracking"
                class="nav-link<?php echo ($active_payment ? ' active' : ''); ?>">
                <i class="bi iconclassforall bi-credit-card-fill"></i>
                <span>Payment Tracker</span>
              </a>
            </li>
            <li class="sidemenuli <?php echo $active_assets; ?>">
              <a href="/incentiveapp_integration/userlogin1/superadmin/companyassets"
                class="nav-link<?php echo ($active_assets ? ' active' : ''); ?>">
                <i class='bi bi-box-fill iconclassforall'></i>
                <span>Company Assets</span>
              </a>
            </li>
          </ul>
        </li>

        <!-- Accounts Dropdown -->
        <li class="sidemenuli menu-group">
          <a href="#" class="menu-group-toggle" onclick="toggleMenuGroup(event, 'accounts')">
            <i class="bi bi-wallet-fill iconclassforall"></i>
            <span class="menu-title">Accounts</span>
            <i class="bi bi-chevron-down menu-chevron"></i>
          </a>
          <ul class="submenu" id="submenu-accounts">
            <li class="sidemenuli">
              <a href="/hrlogin/createuser/" target="_blank">
                <i class="bi iconclassforall bi-person-plus-fill"></i>
                <span>Create User</span>
              </a>
            </li>
            <li class="sidemenuli <?php echo $active_users; ?>">
              <a href="/incentiveapp_integration/userlogin1/superadmin/users"
                class="nav-link<?php echo ($active_users ? ' active' : ''); ?>">
                <i class="bi iconclassforall bi-people-fill"></i>
                <span>Users</span>
              </a>
            </li>
          </ul>
        </li>

        <li class="sidemenuli">
          <a href="#" class="dark-mode-toggle" onclick="toggleDarkMode(event)" style="display: flex; align-items: center; justify-content: space-between; padding-right: 15px;">
            <div style="display: flex; align-items: center; gap: 5px;">
              <i class="bi iconclassforall bi-moon-fill" id="darkModeIcon"></i>
              <span>Dark Mode</span>
            </div>
            <label class="theme-switch" style="margin: 0; pointer-events: none;">
              <input type="checkbox" id="themeToggleSuperadmin">
              <span class="slider"></span>
            </label>
          </a>
        </li>
        <li class="sidemenuli">
          <a href="./logout" class="logout" style="color: #dc3545; font-size: 15px;gap: 15px;margin-left: 10px;">
            <i class="fas fa-sign-out-alt" style="color: #dc3545;"></i>
            <span style="color: #dc3545 !important;">Logout</span>
          </a>
        </li>

      </ul>

      <button type="button" class="sidebar-toggle-main" aria-label="Toggle sidebar" aria-controls="sidebar"
        aria-expanded="true" onclick="toggleleftsidebar()">
        <svg class="svg-inline--fa fa-angles-left toggle-icon-desktop" aria-hidden="true" focusable="false"
          data-prefix="fas" data-icon="angles-left" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"
          data-fa-i2svg="">
          <path fill="currentColor"
            d="M41.4 233.4c-12.5 12.5-12.5 32.8 0 45.3l160 160c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L109.3 256 246.6 118.6c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0l-160 160zm352-160l-160 160c-12.5 12.5-12.5 32.8 0 45.3l160 160c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L301.3 256 438.6 118.6c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0z">
          </path>
        </svg>
      </button>
    </div>
    <!-- End of Left Sidebar -->

    <!-- Calculator Modal -->
    <div class="modal-overlay" id="calculatorModalOverlay" style="display: none;">
      <div class="modal-container-eoi" style="max-width: 500px; height: max-content">
        <div class="modal-header">
          <h3>Incentive Calculator</h3>
          <button type="button" class="modal-close-btn" onclick="closeCalculatorModal()">&times;</button>
        </div>

        <div class="modal-body">
          <form id="calculator-form">
            <div class="section">
              <div class="grid" style="grid-template-columns: 1fr;">
                <div class="field">
                  <fieldset class="fieldset-label calc-fieldset">
                    <legend class="field-legend calc-legend">Current Salary</legend>
                    <input type="number" id="d1" name="salary" class="calc-input calc-input-lg"
                      placeholder="Enter current salary" required />
                  </fieldset>
                </div>
                <div class="field">
                  <fieldset class="fieldset-label calc-fieldset">
                    <legend class="field-legend calc-legend">Generated Revenue</legend>
                    <input type="number" id="d2" name="revenue" class="calc-input calc-input-lg"
                      placeholder="Enter generated revenue" required />
                  </fieldset>
                </div>
              </div>
            </div>

            <!-- Result Display -->
            <div class="field" style="margin-top: 1.5rem;">
              <div class="calc-result-box">
                <div style="font-size: 0.875rem; font-weight: 600; margin-bottom: 0.25rem; opacity: 0.9;">Amount</div>
                <div style="font-size: 1.75rem; font-weight: 700;">₹<span id="result">0.00</span></div>
              </div>
            </div>

            <!-- Actions -->
            <div class="form-actions calc-form-actions" style="margin-top: 1.5rem;">
              <button type="button" class="calc-cancel-btn btn" onclick="closeCalculatorModal()">Close</button>
              <button type="submit" class="calc-submit-btn eoi-primary-btn">Calculate</button>
            </div>
          </form>
        </div>
      </div>
    </div>
    <!-- calculator modal End -->

    <script>
      if (window.Capacitor && window.Capacitor.Plugins && window.Capacitor.Plugins.SplashScreen) {
        window.addEventListener('load', () => {
          window.Capacitor.Plugins.SplashScreen.hide();
        });
      }
    </script>

    <!-- Menu Group Toggle Function -->
    <script>
      // Menu Group Toggle Function - Enhanced for collapsed state
      function toggleMenuGroup(event, groupId) {
        event.preventDefault();
        event.stopPropagation();

        const menuGroup = event.currentTarget.closest('.menu-group');
        const submenu = document.getElementById('submenu-' + groupId);
        const sidebar = document.getElementById('sidebar');

        if (!menuGroup || !submenu) return;

        // Check if sidebar is collapsed
        const isCollapsed = sidebar && (sidebar.classList.contains('close') || document.body.classList.contains('sidebar-collapsed'));
        const isMobile = window.innerWidth <= 1024;

        // Desktop collapsed state - position submenu next to the menu item
        if (isCollapsed && !isMobile) {
          // Close all other open menus first
          document.querySelectorAll('.menu-group.open').forEach(function(otherGroup) {
            if (otherGroup !== menuGroup) {
              otherGroup.classList.remove('open');
            }
          });

          // Toggle current menu
          const isOpen = menuGroup.classList.contains('open');

          if (isOpen) {
            menuGroup.classList.remove('open');
          } else {
            // No manual positioning needed for absolute positioning
            menuGroup.classList.add('open');
          }
        } else {
          // Normal expanded state or mobile - just toggle
          const isOpen = menuGroup.classList.contains('open');

          if (isOpen) {
            menuGroup.classList.remove('open');
          } else {
            menuGroup.classList.add('open');
          }
        }
      }

      // Close submenu when clicking outside (for collapsed state)
      document.addEventListener('click', function(event) {
        const sidebar = document.getElementById('sidebar');
        const isCollapsed = sidebar && (sidebar.classList.contains('close') || document.body.classList.contains('sidebar-collapsed'));
        const isMobile = window.innerWidth <= 1024;

        if (isCollapsed && !isMobile) {
          const clickedInsideMenu = event.target.closest('.menu-group');

          if (!clickedInsideMenu) {
            // Close all open menus
            document.querySelectorAll('.menu-group.open').forEach(function(menuGroup) {
              menuGroup.classList.remove('open');
            });
          }
        }
      });

      // Submenus are now position: absolute, so they follow the parent naturally.
      // Repositioning on scroll is no longer needed.

      // Menu expansion and state management moved to main sidebar initializer below.

      // Note: Sidebar toggle logic integrated into the main listener below to avoid conflicts.
    </script>

    <!-- Dark Mode Toggle Function -->
    <script>
      function setDarkModeState(enabled) {
        document.body.classList.toggle('dark-mode', !!enabled);
        document.documentElement.setAttribute('data-theme', enabled ? 'dark' : 'light');
        localStorage.setItem('darkMode', enabled ? 'true' : 'false');

        const toggleCheck = document.getElementById('themeToggleSuperadmin');
        if (toggleCheck) toggleCheck.checked = !!enabled;

        const icon = document.getElementById('darkModeIcon');
        if (icon) {
          if (enabled) {
            icon.classList.remove('bi-moon-fill');
            icon.classList.add('bi-brightness-high-fill');
          } else {
            icon.classList.remove('bi-brightness-high-fill');
            icon.classList.add('bi-moon-fill');
          }
        }

        updateIframeDarkMode();
      }

      function toggleDarkMode(event) {
        if (event) {
          event.preventDefault();
          event.stopPropagation();
        }

        // Guard against accidental double-fire paths (click bubbling/touch duplication).
        const now = Date.now();
        if (window.__darkModeToggleAt && (now - window.__darkModeToggleAt) < 250) {
          return false;
        }
        window.__darkModeToggleAt = now;

        const nextState = !document.body.classList.contains('dark-mode');
        setDarkModeState(nextState);
        return false;
      }

      function updateIframeDarkMode() {
        const isDarkMode = document.body.classList.contains('dark-mode');

        // Update dashboard iframe (if exists)
        const dashboardIframe = document.getElementById('userlogin6-dashboard-iframe');
        if (dashboardIframe) {
          // Check if iframe is ready (loaded and has contentWindow)
          const isReady = dashboardIframe.dataset && dashboardIframe.dataset.ready === '1' && dashboardIframe.contentWindow;

          if (isReady) {
            try {
              // Send message to dashboard iframe
              dashboardIframe.contentWindow.postMessage({
                type: 'darkMode',
                enabled: isDarkMode
              }, '*');
            } catch (e) {
              console.log('Could not update dashboard iframe dark mode:', e);
            }
          }
        }

        // Update property bookings iframe (if exists)
        const bookingsIframe = document.getElementById('bookings-superadmin-iframe');
        if (bookingsIframe) {
          try {
            // Send postMessage to iframe
            bookingsIframe.contentWindow.postMessage({
              type: 'darkMode',
              enabled: isDarkMode
            }, '*');

            // Also directly update iframe document (belt and suspenders approach)
            const iframeDoc = bookingsIframe.contentDocument || bookingsIframe.contentWindow.document;
            if (iframeDoc) {
              if (iframeDoc.body) {
                if (isDarkMode) {
                  iframeDoc.body.classList.add('dark-mode');
                } else {
                  iframeDoc.body.classList.remove('dark-mode');
                }
              }
              if (iframeDoc.documentElement) {
                if (isDarkMode) {
                  iframeDoc.documentElement.setAttribute('data-theme', 'dark');
                } else {
                  iframeDoc.documentElement.removeAttribute('data-theme');
                }
              }
            }
          } catch (e) {
            console.log('Could not update property bookings iframe dark mode:', e);
          }
        }
      }

      // Check for saved dark mode preference on page load
      (function() {
        const savedDarkMode = localStorage.getItem('darkMode');
        if (savedDarkMode === 'true' || savedDarkMode === 'enabled') {
          document.body.classList.add('dark-mode');
        }
      })();

      // Also check on DOMContentLoaded as fallback
      document.addEventListener('DOMContentLoaded', function() {
        const savedDarkMode = localStorage.getItem('darkMode');
        if (savedDarkMode === 'true' || savedDarkMode === 'enabled') {
          setDarkModeState(true);
        } else {
          setDarkModeState(false);
        }
        // Update iframe after a short delay to ensure it's loaded
        setTimeout(updateIframeDarkMode, 500);
      });

      // Mobile sidebar toggle function (uses unified toggle system)
      function toggleMobileSidebar() {
        // Use the unified toggle function if available, otherwise use direct logic
        if (typeof window.toggleleftsidebar === 'function') {
          window.toggleleftsidebar();
        } else {
          // Fallback for when DOMContentLoaded hasn't run yet
          var sidebar = document.getElementById('sidebar');
          var body = document.body;

          if (sidebar && window.innerWidth <= 768) {
            var isClosed = sidebar.classList.contains('close');
            var isOpen = body.classList.contains('sidebar-open');

            if (isOpen || !isClosed) {
              // Close the sidebar
              body.classList.remove('sidebar-open');
              body.classList.remove('sidebar-overlay');
              sidebar.classList.add('close');
            } else {
              // Open the sidebar
              body.classList.add('sidebar-open');
              body.classList.add('sidebar-overlay');
              sidebar.classList.remove('close');
            }
          }
        }
      }

      // Close mobile sidebar (for close button)
      function closeMobileSidebar() {
        var body = document.body;
        var sidebar = document.getElementById('sidebar');

        if (sidebar && window.innerWidth <= 768) {
          body.classList.remove('sidebar-open');
          body.classList.remove('sidebar-overlay');
          sidebar.classList.remove('close');
        }
      }
    </script>

    <!-- This is for hide and show Right Side Bar -->
    <script>
      document.addEventListener("DOMContentLoaded", function() {
        var sidebar = document.getElementById('rightsidebar');
        if (!sidebar) {
          return;
        }

        function hideSidebar() {
          sidebar.style.display = 'none';
        }
        var sidebarItems = document.querySelectorAll('.pmd-sidebar-li');
        sidebarItems.forEach(function(item) {
          item.addEventListener('click', function() {
            hideSidebar();
          });
        });
      });
    </script>
    <!-- This is for hide and show Right Side Bar End -->

    <script>
      document.addEventListener('DOMContentLoaded', function() {
        var sidebar = document.getElementById('sidebar');
        if (!sidebar) {
          return;
        }

        var body = document.body;
        var toggleButtons = document.querySelectorAll('.sidebar-toggle-main, .mobile-menu-toggle');
        var overlay = document.querySelector('.mobile-overlay');
        var collapseQuery = window.matchMedia('(max-width: 1180px)');
        var mobileQuery = window.matchMedia('(max-width: 1024px)');
        var wasCollapseMatch = collapseQuery.matches;
        var desktopPreference = collapseQuery.matches ? true : false;
        var desktopPreferenceManual = false;
        var widePreference = false;
        var widePreferenceManual = false;

        // Robust localStorage restoration
        const sidebarStoreKey = 'superadmin_sidebarCollapsed';
        const legacyStoreKey = 'sidebarCollapsed';
        const savedState = localStorage.getItem(sidebarStoreKey) || localStorage.getItem(legacyStoreKey);

        if (savedState !== null) {
          const isCollapsed = (savedState === 'true');
          desktopPreference = isCollapsed;
          desktopPreferenceManual = true;
          widePreference = isCollapsed;
          widePreferenceManual = true;
        }

        if (!overlay) {
          overlay = document.createElement('div');
          overlay.className = 'mobile-overlay';
          body.appendChild(overlay);
        }

        function syncToggleIcon(closed) {
          toggleButtons.forEach(function(btn) {
            btn.classList.toggle('is-collapsed', closed);
            btn.setAttribute('aria-expanded', (!closed).toString());
            var label = closed ? 'Open sidebar navigation' : 'Collapse sidebar navigation';
            btn.setAttribute('aria-label', label);
            btn.setAttribute('title', label);
            var mobileIcon = btn.querySelector('.toggle-icon-mobile');
            if (mobileIcon) {
              mobileIcon.classList.toggle('bi-x-lg', !closed);
              mobileIcon.classList.toggle('bi-list', closed);
            }
          });
        }

        function applySidebarState(closed) {
          var isMobile = mobileQuery.matches;
          sidebar.classList.toggle('close', closed);
          body.classList.toggle('sidebar-collapsed', closed);
          body.classList.toggle('sidebar-open', !closed);
          body.classList.toggle('sidebar-overlay', !closed && isMobile);
          if (!isMobile) {
            body.classList.remove('sidebar-overlay');
          }
          syncToggleIcon(closed);
        }

        function toggleleftsidebar(forceState) {
          var currentlyClosed = sidebar.classList.contains('close');
          var nextState = typeof forceState === 'boolean' ? forceState : !currentlyClosed;

          applySidebarState(nextState);

          if (!mobileQuery.matches) {
            desktopPreference = nextState;
            desktopPreferenceManual = true;
            widePreference = nextState;
            widePreferenceManual = true;

            // Save state to localStorage explicitly as string
            localStorage.setItem('superadmin_sidebarCollapsed', nextState ? 'true' : 'false');
            localStorage.setItem('sidebarCollapsed', nextState ? 'true' : 'false'); // Sync with userlogin6 key too

            // Close all dropdowns when sidebar is collapsed on desktop
            if (nextState) {
              document.querySelectorAll('.menu-group.open').forEach(function(menuGroup) {
                menuGroup.classList.remove('open');
              });
            }
          }
          return nextState;
        }

        window.toggleleftsidebar = toggleleftsidebar;

        // Initialize state precisely once
        handleResponsive(true);

        // Auto-expand menu if a child is active and mark parent
        const activeItems = document.querySelectorAll('.submenu .sidemenuli[class*="sideactive"]');
        activeItems.forEach(function(item) {
          const parentGroup = item.closest('.menu-group');
          if (parentGroup) {
            parentGroup.classList.add('has-active-child');
            // Only auto-expand on expanded sidebar (not collapsed)
            const isCollapsed = sidebar.classList.contains('close') || document.body.classList.contains('sidebar-collapsed');
            if (!isCollapsed) {
              parentGroup.classList.add('open');
            }
          }
        });

        toggleButtons.forEach(function(btn) {
          if (btn.hasAttribute('onclick')) {
            btn.removeAttribute('onclick');
          }
          btn.addEventListener('click', function(event) {
            event.preventDefault();
            // On mobile, ensure sidebar opens when clicking mobile-menu-toggle
            if (btn.classList.contains('mobile-menu-toggle') && mobileQuery.matches) {
              var isCurrentlyClosed = sidebar.classList.contains('close');
              if (isCurrentlyClosed) {
                toggleleftsidebar(false); // Force open on mobile
              } else {
                toggleleftsidebar(); // Toggle normally
              }
            } else {
              toggleleftsidebar();
            }
          });
        });

        overlay.addEventListener('click', function() {
          var isMobile = mobileQuery.matches;
          if (isMobile) {
            closeMobileSidebar();
          } else {
            toggleleftsidebar(true);
          }
        });

        function handleResponsive(initialRun) {
          var isMobile = mobileQuery.matches;
          var shouldCollapse = collapseQuery.matches;

          if (isMobile) {
            applySidebarState(true);
            wasCollapseMatch = shouldCollapse;
            return;
          }

          if (!initialRun) {
            if (shouldCollapse && !wasCollapseMatch && !desktopPreferenceManual) {
              desktopPreference = true;
            } else if (!shouldCollapse && wasCollapseMatch && !widePreferenceManual) {
              widePreference = false;
            }
          } else if (shouldCollapse && !desktopPreferenceManual) {
            desktopPreference = true;
          }

          var targetState = shouldCollapse ? desktopPreference : widePreference;
          applySidebarState(targetState);
          wasCollapseMatch = shouldCollapse;
        }

        function bindMediaQuery(query, handler) {
          if (typeof query.addEventListener === 'function') {
            query.addEventListener('change', handler);
          } else if (typeof query.addListener === 'function') {
            query.addListener(handler);
          }
        }

        window.addEventListener('resize', function() {
          handleResponsive(false);
        });

        bindMediaQuery(collapseQuery, function() {
          handleResponsive(false);
        });

        bindMediaQuery(mobileQuery, function() {
          handleResponsive(false);
        });

        document.addEventListener('keyup', function(event) {
          if (event.key === 'Escape') {
            var isMobile = mobileQuery.matches;
            if (body.classList.contains('sidebar-overlay')) {
              if (isMobile) {
                closeMobileSidebar();
              } else {
                toggleleftsidebar(true);
              }
            }
          }
        });

        // Initialized above
      });
    </script>
    <script>
      // Loader Logic (Migrated from userlogin6)
      let loaderFailsafeTimeout;

      function showLeadsLoader() {
        const el = document.querySelector('.loader-container');
        if (el) el.style.display = 'flex';
        // Failsafe: always hide loader after 5 seconds
        if (loaderFailsafeTimeout) clearTimeout(loaderFailsafeTimeout);
        loaderFailsafeTimeout = setTimeout(() => {
          hideLeadsLoader();
        }, 5000);
      }

      function hideLeadsLoader() {
        const el = document.querySelector('.loader-container');
        if (el) el.style.display = 'none';
        if (loaderFailsafeTimeout) clearTimeout(loaderFailsafeTimeout);
      }

      // Initialize loader on page load
      document.addEventListener('DOMContentLoaded', () => {
        showLeadsLoader();
      });

      window.addEventListener('load', () => {
        hideLeadsLoader();
      });

      // Cleanup on unload to prevent stuck loader on back/forward
      window.addEventListener('unload', () => {
        hideLeadsLoader();
      });
    </script>
    <style>
      /* Header Notification Dropdown */
      .header-notification-popup {
        position: absolute;
        top: calc(100% + 10px);
        right: 10px;
        width: 340px;
        max-height: 400px;
        background: aliceblue;
        backdrop-filter: blur(20px);
        border: 1px solid var(--border-color, #e2e8f0);
        border-radius: 16px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        z-index: 9999;
        display: none;
        flex-direction: column;
        overflow: hidden;
        animation: .2s ease-out fadeIn;
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
    </style>
    <script>
      // Notification Logic for Superadmin
      document.addEventListener('DOMContentLoaded', () => {
        const notifBtn = document.getElementById('notificationBtn');
        const popup = document.getElementById('notifDropdownPopup');
        const badge = document.getElementById('notifBadge');

        if (!notifBtn || !popup) return;

        const notifApiBase = '<?php echo $superadminNotifBase; ?>';

        // ── Inject structure once ─────────────────────────────────────
        popup.innerHTML = `
          <div class="notification-header">
            <h3>Notifications</h3>
            <button class="mark-all-read" id="saMarkAllBtn">Mark all as read</button>
          </div>
          <div class="notification-search-wrapper" style="padding:0.6rem 1rem;border-bottom:1px solid var(--border-color,#e2e8f0);">
            <input type="text" id="saNotifSearch" placeholder="Search notifications…"
              style="width:100%;padding:0.45rem 0.75rem;border:1px solid var(--border-color,#ddd);border-radius:8px;font-size:0.88rem;outline:none;">
          </div>
          <div class="notification-list" id="saNotifList"></div>
        `;

        const listEl = document.getElementById('saNotifList');
        const searchInput = document.getElementById('saNotifSearch');
        const markAllBtn = document.getElementById('saMarkAllBtn');

        // ── State ─────────────────────────────────────────────────────
        let notifications = [];
        let offset = 0;
        const limit = 10;
        let searchQuery = '';
        let isLoading = false;
        let hasMore = true;

        // ── Toggle popup ──────────────────────────────────────────────
        notifBtn.addEventListener('click', e => {
          e.stopPropagation();
          const isVisible = popup.style.display === 'flex';
          // Close profile card if open
          const profileBox = document.getElementById('profile-content_box');
          if (profileBox) profileBox.style.display = 'none';

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

        // ── Reset & load from start ───────────────────────────────────
        async function resetAndLoad() {
          offset = 0;
          hasMore = true;
          notifications = [];
          listEl.innerHTML = '<div style="padding:1.5rem;text-align:center;color:#888;">Loading…</div>';
          await loadMore();
        }

        // ── Append next page ──────────────────────────────────────────
        async function loadMore() {
          if (isLoading || !hasMore) return;
          isLoading = true;
          try {
            let url = `${notifApiBase}/get-notifications.php?limit=${limit}&offset=${offset}`;
            if (searchQuery) url += `&q=${encodeURIComponent(searchQuery)}`;
            const res = await fetch(url, {
              credentials: 'same-origin'
            });
            const data = await res.json();

            if (data.status !== 'success') {
              isLoading = false;
              return;
            }

            const newItems = data.notifications || [];
            if (offset === 0) notifications = newItems;
            else notifications = notifications.concat(newItems);

            hasMore = data.has_more;
            offset += newItems.length;
            renderList(notifications, offset === newItems.length);
            updateBadge(data.unread_count);
          } catch (err) {
            console.error('Superadmin notif load error:', err);
          }
          isLoading = false;
        }

        // ── Render ────────────────────────────────────────────────────
        function renderList(items, replace = true) {
          if (replace) listEl.innerHTML = '';

          if (!items || items.length === 0) {
            listEl.innerHTML = '<div style="padding:2rem;text-align:center;color:#888;">No notifications found</div>';
            return;
          }

          const startIdx = replace ? 0 : listEl.querySelectorAll('.notification-item').length;
          const toRender = replace ? items : items.slice(startIdx);

          toRender.forEach(n => {
            const item = document.createElement('div');
            item.className = 'notification-item' + (n.is_read == 0 ? ' unread' : '');
            item.dataset.nid = n.notification_id || n.id;

            let iconClass = 'fa-bell';
            if (n.type === 'lead' || n.type === 'lead_update') iconClass = 'fa-user-plus';
            if (n.type === 'followup_reminder') iconClass = 'fa-clock';
            if (n.type === 'alert') iconClass = 'fa-exclamation-circle';
            if (n.type === 'skip_popup') iconClass = 'fa-forward';

            const bodyText = n.body || n.message || n.title || '';
            item.innerHTML = `
              <div class="notification-icon"><i class="fas ${iconClass}"></i></div>
              <div class="notification-content">
                <p>${bodyText}</p>
                <div class="notification-time">${formatTimeAgo(n.created_at)}</div>
              </div>`;

            item.addEventListener('click', async () => {
              const nid = Number(item.dataset.nid);
              if (!Number.isFinite(nid) || nid <= 0) return;

              // Click should only mark as read (no redirect)
              if (item.classList.contains('unread')) {
                const ok = await markAsRead([nid]);
                if (ok) item.classList.remove('unread');
              }
            });
            listEl.appendChild(item);
          });
        }

        // ── Infinite scroll ───────────────────────────────────────────
        listEl.addEventListener('scroll', () => {
          if (listEl.scrollTop + listEl.clientHeight >= listEl.scrollHeight - 60 && hasMore && !isLoading) {
            loadMore();
          }
        });

        // ── Search (debounced 400ms) ──────────────────────────────────
        let searchTimer;
        searchInput.addEventListener('input', () => {
          clearTimeout(searchTimer);
          searchTimer = setTimeout(() => {
            searchQuery = searchInput.value.trim();
            resetAndLoad();
          }, 400);
        });

        // ── Mark as read ──────────────────────────────────────────────
        async function markAsRead(ids) {
          if (!ids || !ids.length) return false;
          try {
            const res = await fetch(`${notifApiBase}/mark-read.php`, {
              method: 'POST',
              credentials: 'same-origin',
              headers: {
                'Content-Type': 'application/json'
              },
              body: JSON.stringify({
                notification_ids: ids
              })
            });
            if (!res.ok) return false;
            const data = await res.json();
            if (data.status === 'success') {
              let newlyRead = 0;
              notifications.forEach(n => {
                const nid = n.notification_id || n.id;
                if (ids.includes(nid)) {
                  if (n.is_read == 0) {
                    n.is_read = 1;
                    newlyRead++;
                  }
                }
              });
              const currentBadgeCount = parseInt(badge ? (badge.textContent || badge.innerText) : '0', 10) || 0;
              updateBadge(Math.max(0, currentBadgeCount - newlyRead));
              return true;
            }
          } catch (err) {
            console.error('Mark read error:', err);
          }
          return false;
        }

        async function markAllRead() {
          try {
            const res = await fetch(`${notifApiBase}/mark-all-read.php`, {
              method: 'POST',
              credentials: 'same-origin'
            });
            if (!res.ok) return false;
            const data = await res.json();
            if (data.status === 'success') {
              notifications.forEach(n => {
                n.is_read = 1;
              });
              listEl.querySelectorAll('.notification-item.unread').forEach(el => el.classList.remove('unread'));
              updateBadge(0);
              return true;
            }
          } catch (err) {
            console.error('Mark all read error:', err);
          }
          return false;
        }

        markAllBtn.addEventListener('click', async e => {
          e.stopPropagation();
          await markAllRead();
        });

        // ── Badge ─────────────────────────────────────────────────────
        function updateBadge(count) {
          if (!badge) return;
          if (count > 0) {
            badge.textContent = count;
            badge.style.display = 'flex';
          } else {
            badge.textContent = '';
            badge.style.display = 'none';
          }
          const dotBadge = document.querySelector('#notificationBtn .notification-badge');
          if (dotBadge) dotBadge.style.display = 'none';
        }

        function formatTimeAgo(dateString) {
          if (!dateString) return '';
          const date = new Date(dateString.replace(' ', 'T'));
          const s = Math.floor((Date.now() - date.getTime()) / 1000);
          if (s < 60) return 'Just now';
          if (s < 3600) return Math.floor(s / 60) + 'm ago';
          if (s < 86400) return Math.floor(s / 3600) + 'h ago';
          const d = Math.floor(s / 86400);
          return d === 1 ? 'Yesterday' : d + 'd ago';
        }

        // ── Initial badge count on page load ─────────────────────────
        fetch(`${notifApiBase}/get-notifications.php?limit=1&offset=0`, {
            credentials: 'same-origin'
          })
          .then(r => r.json())
          .then(data => {
            if (data.status === 'success') updateBadge(data.unread_count);
          })
          .catch(e => console.error(e));
      });
    </script>