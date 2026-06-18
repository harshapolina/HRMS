<?php
include('htmlopen.php');
// We need to use sessions, so you should always start sessions using the below code.
// session_start();
require_once 'action.php';
$counter = $db->printTableRowsCount();
// If the user is not logged in redirect to the login page...
if (!isset($_SESSION['loggedin'])) {
	header('Location: /');
	exit;
}
// Check if the user's role is allowed to access this page
$allowed_roles = ['superuseradmin']; // Define allowed roles for this page
$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : ''; // Get user's role from session
$allow_access = isset($_SESSION['allow_access']) ? $_SESSION['allow_access'] : ''; // Get user's access level

// Define restricted directories for "few access" users
$restricted_paths = [
    '/superadmin/payment-tracking',
    '/superadmin/companyassets',
    '/superadmin/accounts',
    '/superadmin/property-bookings',
    '/superadmin/expenses',
    '/superadmin/incentive-tracking',
    '/hrlogin/createuser/',
    '/superadmin/users'
];
// Get the current URL path without query parameters
$current_path = strtok($_SERVER['REQUEST_URI'], '?');

// Restrict access based on user role and access permissions
if ($allow_access !== 'full access' && in_array($current_path, $restricted_paths)) {
    // Log the unauthorized access attempt for debugging (optional)
    error_log("Unauthorized access attempt to $current_path by user role: $user_role");
    
    // Redirect to access denied page
    header('Location: access_denied.html');
    exit;
}

if (!in_array($user_role, $allowed_roles)) {
    // User's role is not allowed, redirect to an error page or homepage
    header('Location: access_denied.html'); // Redirect to an error page
    exit;
}
// fitch name from  account table to show to the user
$nameuser = $_SESSION['username'];
$tablename = $_SESSION['tablename'];

// Check if the logged-in user is a superadmin and set the session variable accordingly
if ($nameuser === $nameuser) { // Replace 'your_superadmin_username' with the actual superadmin's username
  $_SESSION['is_superadmin'] = true;
}
$config = new Config();
$conn = $config->getConnection();

// =====================================
// City Filtering
// =====================================

// Get all bookings for processing
$allBookings = $db->read();

// Build available city options from booking data
$cityOptionsSet = [];
foreach ($allBookings as $booking) {
    $city = trim($booking['city'] ?? '');
    if (!empty($city)) {
        $cityOptionsSet[$city] = true;
    }
}

// Add default cities if none exist in bookings
if (empty($cityOptionsSet)) {
    $defaultCities = ['Mumbai', 'Delhi', 'Bangalore', 'Hyderabad', 'Chennai', 'Kolkata', 'Pune', 'Ahmedabad'];
    foreach ($defaultCities as $city) {
        $cityOptionsSet[$city] = true;
    }
}

$cityOptions = array_keys($cityOptionsSet);
sort($cityOptions);

// Handle city filtering
$requestedCity = $_GET['city'] ?? '';
$selectedCity = '';

// Only set selectedCity if a valid city is requested
if (!empty($requestedCity) && in_array($requestedCity, $cityOptions, true)) {
    $selectedCity = $requestedCity;
}

// Export these variables for use in header.php
$GLOBALS['cityOptions'] = $cityOptions;
$GLOBALS['selectedCity'] = $selectedCity;
?>
<!-- Page-specific styles for property-bookings -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<!-- Google Fonts - Lexend Deca -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Lexend+Deca:wght@100..900&display=swap" rel="stylesheet">
<!-- Select2 CSS for autocomplete -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<!-- SweetAlert2 for toast notifications -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="../userlogin6/assets/css/booking_styles.css">
<style>
      @import url('https://netdna.bootstrapcdn.com/font-awesome/4.0.3/css/font-awesome.css');
        /* Remove underline from links (prevents underlines under icon-only links) */
        a, a:visited, a:hover, a:active { text-decoration: none !important; }
    :root {
      /* Sidebar widths - same as other superadmin pages */
      --sidebar-width: 280px;
      --sidebar-collapsed-width: 90px;
      --navbar-height: clamp(50px, 5.5vh, 70px); /* Scales between 50px-70px based on viewport height */
      --page-padding: clamp(1rem, 2vw, 2rem); /* Responsive padding */
      --page-bg: #e8edf7;
      --content-max-width: clamp(95%, 95vw, 2000px); /* Increased to match userlogin6 layout: 95% on small screens, max 2000px */
    }
    html,
    body {
      scrollbar-width: none;
      height: 100%;
      margin: 0;
      padding: 0;
      background: radial-gradient(circle at 90% 50%, rgba(180, 140, 240, 0.5) 0%, transparent 40%), 
                  radial-gradient(circle at 25% 15%, #cce2c9 0%, transparent 30%), 
                  linear-gradient(135deg, #D1E5E6 0%, #caf2f5 0%);
      background-attachment: fixed;
      background-size: cover;
      overflow-x: hidden;
    }
    body {
      font-family: "Lexend Deca", sans-serif;
      font-optical-sizing: auto;
      font-style: normal;
      color: #0f172a;
      background: radial-gradient(circle at 90% 50%, rgba(180, 140, 240, 0.5) 0%, transparent 40%), 
                  radial-gradient(circle at 25% 15%, #cce2c9 0%, transparent 30%), 
                  linear-gradient(135deg, #D1E5E6 0%, #caf2f5 0%);
      background-attachment: fixed;
      background-size: cover;
    }
    
    /* Floating background circles */
    body::before,
    body::after {
      content: '';
      position: fixed;
      width: 200vw;
      height: 200vh;
      border-radius: 50%;
      z-index: -1;
      opacity: 0.3;
      pointer-events: none;
      animation: float 15s infinite alternate ease-in-out;
    }
    
    body::before {
      background: radial-gradient(circle, #d2b4ff 0, transparent 70%);
      top: -10vh;
      right: -50vw;
      animation-delay: 0s;
    }
    
    body::after {
      background: radial-gradient(circle, #f9eb9c 0, transparent 70%);
      bottom: -100vh;
      left: -50vw;
      animation-delay: 2.5s;
    }
    
    @keyframes float {
      0% {
        transform: translate(0, 0);
      }
      100% {
        transform: translate(10px, 10px);
      }
    }
    /* ============================================
       SIDEBAR UNCOLLAPSED (OPEN) STATE
       ============================================ */
    /* Responsive layout like dashboard - centered with max-width when sidebar is open */
    .content {
      scrollbar-width: none;
      position: relative;
      width: calc(100% - var(--sidebar-width));
      left: var(--sidebar-width);
      max-width: none;
      margin-left: 0;
      margin-right: 0;
      min-height: 100vh;
      max-height: 100vh;
      overflow-y: auto;
      transition: left 0.3s ease, width 0.3s ease, padding 0.3s ease;
      box-sizing: border-box;
      display: flex;
      flex-direction: column;
      align-items: stretch;
      gap: clamp(0.5rem, 1vw, 1rem);
    }
    body.sidebar-collapsed .navbar {
      width: 100% !important;
    }
    
    /* Center the contentinside wrapper like incentivemain in dashboard */
    .contentinside {
      max-width: var(--content-max-width);
      margin: 0 auto;
      position: relative;
      width: 100%;
    }
    
    /* ============================================
       SIDEBAR COLLAPSED STATE
       ============================================ */
    /* When sidebar is collapsed, content adjusts to collapsed sidebar width */
    /* We'll style this separately after uncollapsed is done */
    .sidebar.close ~ .content,
    .sidebar.collapsed ~ .content,
    .sidebar.sidebar-collapsed ~ .content,
    .sidebar[class*="close"] ~ .content,
    body.sidebar-collapsed .content {
      /* Will be styled separately */
    }
    .content .table-card {
      margin: 0;
      width: 100%;
      padding: 0 clamp(1rem, 2.5vw, 2.5rem);
      background: #fff;
      box-shadow: 0 clamp(0.25rem, 0.375vw, 0.375rem) clamp(0.375rem, 0.5vw, 0.5rem) -1px rgba(0, 0, 0, 0.1), 0 clamp(0.125rem, 0.25vw, 0.25rem) clamp(0.25rem, 0.375vw, 0.375rem) -1px rgba(0, 0, 0, 0.06);
      border-radius: clamp(0.75rem, 1vw, 1rem);
    }
    .sidebar.close ~ .content .table-card,
    .sidebar.collapsed ~ .content .table-card,
    .sidebar.sidebar-collapsed ~ .content .table-card,
    .sidebar[class*="close"] ~ .content .table-card,
    body.sidebar-collapsed .table-card {
      width: 100%;
      padding: 0 clamp(0.75rem, 1.5vw, 1rem);
      background: #fff;
      box-shadow: 0 clamp(0.25rem, 0.375vw, 0.375rem) clamp(0.375rem, 0.5vw, 0.5rem) -1px rgba(0, 0, 0, 0.1), 0 clamp(0.125rem, 0.25vw, 0.25rem) clamp(0.25rem, 0.375vw, 0.375rem) -1px rgba(0, 0, 0, 0.06);
    }
    @media (max-width: 992px) {
      .content,
      .sidebar.close ~ .content,
      .sidebar.collapsed ~ .content,
      .sidebar.sidebar-collapsed ~ .content,
      .sidebar[class*="close"] ~ .content,
      body.sidebar-collapsed .content {
        width: 100%;
        max-width: 100%;
        margin: 0 auto;
        padding: calc(var(--navbar-height) + 0.25rem) 1rem 0.5rem;
      }
      .content .table-card {
        padding: 0;
      }
    }
    .contentinside {
      width: 100%;
      max-width: 100%;
      padding: 0;
      flex: 1 1 auto;
      display: flex;
    }
    
    /* Iframe styling - increased height to match userlogin6 layout */
    #bookings-superadmin-iframe {
      width: 100% !important;
      min-height: calc(100vh - clamp(4rem, 6vh, 4.5rem)) !important;
      border: none !important;
      margin: 0 !important;
      padding: 0 !important;
      display: block !important;
      background: transparent !important;
      overflow: hidden !important;
    }
    
    #emptotaldata, .newsec {
      width: 100%;
      margin: 0 auto;
      padding: 5px 20px;
      background-color: #fff;
      box-shadow: 0 2px 8px 0 rgba(0,0,0,.05);
    }
    table.fold-table {
      background: white;
      color: #555;
    }
    .table-wrap {
      position: relative;
    }
    tbody tr td, tfoot tr td, thead tr th {
      text-align: center !important;
      white-space: nowrap;
    }
    .scroll-left, .scroll-left i, .scroll-right, .scroll-right i {
      top: 50%;
      transform: translate(-50%, -50%);
    }
    .table-container {
      overflow-y: auto;
      overflow-x: auto;
      flex: 1 1 auto;
      height: auto;
      max-height: none;
      width: 100%;
      margin: 0 auto;
      background-color: transparent;
      -webkit-overflow-scrolling: touch;
      min-height: 0;
    }
    .newsec {
      width: 100%;
      border: 1px solid rgba(26, 43, 71, 0.08);
      border-radius: 16px;
      background: #fff;
      box-shadow: 0 2px 8px 0 rgba(0,0,0,.05);
    }
    .maintablewrap {
      overflow-y: auto;
      overflow-x: auto;
      width: 100%;
      height: auto;
      max-height: none;
      background-color: #fff;
      -webkit-overflow-scrolling: touch;
      flex: 1 1 auto;
      min-height: 0;
    }
    .maintablewrap table {
      width: 100%;
      min-width: 100%;
    }
    table {
      border-collapse: collapse;
    }
    .maintablewrap::-webkit-scrollbar, .table-container::-webkit-scrollbar {
      width: 5px;
      height: 5px;
    }
    .maintablewrap::-webkit-scrollbar-track, .table-container::-webkit-scrollbar-track {
      background: #e3e3e3;
      border-radius: 10px;
    }
    .maintablewrap::-webkit-scrollbar-thumb, .table-container::-webkit-scrollbar-thumb {
      background-color: #1b6c9f;
      border-radius: 20px;
    }
    .table-container table tfoot tr td, .table-container table thead th {
      padding: 5px 12px !important;
      border: 1px solid rgba(0,0,0,.529);
      font-weight: 500 !important;
      color: #1b6c9f !important;
    }
    .maintablewrap table tfoot tr td, .maintablewrap table thead th {
      padding: 6px 0;
      border: 1px solid rgba(0,0,0,.529);
      font-weight: 600 !important;
      color: #f6f5f3 !important;
    }
    .maintablewrap table tfoot, .maintablewrap table thead {
      position: sticky;
      background: #1b6c9f;
      z-index: 1;
    }
    .table-container table tfoot, .table-container table thead {
      position: sticky;
      background: #000;
      z-index: 99;
    }
    .maintablewrap table thead, .table-container thead {
      top: -2px;
    }
    .maintablewrap table tfoot, .table-container tfoot {
      bottom: -2px;
    }
    .fold-table tbody tr td {
      font-weight: 500;
      font-size: 15px !important;
      border: 1px solid rgba(0,0,0,.1);
      border-bottom: 1px solid rgba(0,0,0,.599);
      padding: 12px 20px !important;
      color: #000;
      transform: scale(1);
    }
    .small-friendly tbody tr td {
      padding: 10px 12px !important;
    }
    .fold-table tbody tr:hover {
      transform: scale(1.001);
      transition: .3s ease-in-out;
    }
    .scroll-left, .scroll-right {
      position: absolute;
      border-radius: 50%;
      border: 1px solid #000;
      width: 35px;
      height: 35px;
      z-index: 555;
      background-color: #fff;
    }
    .scroll-left i, .scroll-right i {
      font-size: 23px;
      color: #1b6c9f;
      position: absolute;
      left: 50%;
    }
    .scroll-left {
      left: 20px;
    }
    .scroll-right {
      right: -10px;
    }
    table.fold-table > tbody > tr.view {
      transition: .3s;
    }
    .fold-content > table > tbody > tr, table.fold-table > tbody > tr.view td, table.fold-table > tbody > tr.view th {
      cursor: pointer;
    }
    table.fold-table > tbody > tr.view td:first-child, table.fold-table > tbody > tr.view th:first-child {
      position: relative;
      padding-left: 20px;
    }
    table.fold-table > tbody > tr.view td:first-child:before, table.fold-table > tbody > tr.view th:first-child:before {
      position: absolute;
      top: 50%;
      left: 5px;
      width: 50px;
      height: 16px;
      margin-top: -8px;
      font: 20px fontawesome;
      content: "\f0d7";
      transition: .3s;
      color: red;
    }
    table.fold-table > tbody > tr.view:nth-child(4n-1) {
      background: #f4f4f4;
    }
    .fold-content > table > tbody > tr:hover, table.fold-table > tbody > tr.view:hover {
      background: #ddd;
    }
    table.fold-table > tbody > tr.view.open {
      background: #e5e5e6;
      color: #000;
    }
    table.fold-table > tbody > tr.view.open td:first-child:before, table.fold-table > tbody > tr.view.open th:first-child:before {
      transform: rotate(-180deg);
      color: #000;
    }
    .visible-small, table.fold-table > tbody > tr.fold {
      display: none;
    }
    table.fold-table > tbody > tr.fold.open {
      display: table-row;
    }
    .fold-content h3 {
      margin-top: 0;
    }
    .fold-content > table {
      box-shadow: 0 2px 8px 0 rgba(0,0,0,.2);
    }
    .fold-content > table > tbody > tr:nth-child(2n) {
      background: #eee;
    }
    .visible-big {
      display: block;
    }
    .fold-content:first-child {
      text-align: left;
    }
    .totalbook {
      display: flex;
      align-items: center;
      margin: 3px 0;
    }
    .totalbook .totalbookchild {
      margin-left: 40px;
    }
    .totalbook .totalbookchild:first-child {
      margin-left: 0;
    }
    .financialtrsticky {
      position: sticky;
      top: 0;
      z-index: 150;
    }
    #emptotaldata {
      text-align: center;
      border: 1px solid #e0e0e0;
      border-top-right-radius: 10px;
      border-top-left-radius: 10px;
      display: none;
    }
    #emptotaldata .totalbook {
      justify-content: center;
    }
    @media only screen and (max-width: 768px) {
      #emptotaldata .totalbookhead {
        font-size: 13px;
      }
      .scroll-right {
        right: -20px;
      }
      .scroll-left {
        left: 20px;
      }
      .newsec, .table-container {
        width: 100%;
        max-width: 100%;
      }
      .maintablewrap table tfoot tr td, .maintablewrap table thead th {
        padding: 6px 12px;
        font-size: 13px;
      }
      .fold-table tbody tr td {
        padding: 10px 12px !important;
      }
    }
    .btn-download {
      background-color: #4CAF50;
      color: white;
      border: none;
      padding: 3px 2px;
      border-radius: 20px;
      cursor: pointer;
      transition: background-color 0.3s ease, transform 0.2s ease;
      box-shadow: 0 4px 6px rgba(0, 0, 0, );
    }
    /* Assigned User Dropdown Styles */
    .dropdown-container { position: relative; width: 100%; }
    #unique_source_table {
        display: none;
        border: 1px solid #ddd;
        border-radius: 8px;
        list-style-type: none;
        margin: 4px 0 0 0;
        padding: 0;
        max-height: 250px;
        overflow-y: auto;
        position: absolute;
        width: 100%;
        background-color: #fff;
        z-index: 100005;
        box-shadow: 0 10px 25px rgba(0,0,0,0.15);
    }
    .unique-option {
        padding: 12px 16px;
        cursor: pointer;
        transition: background-color 0.2s, color 0.2s;
        border-bottom: 1px solid #f0f0f0;
        font-size: 14px;
        color: #333;
    }
    .unique-option:last-child { border-bottom: none; }
    .unique-option:hover { background-color: #f8f9fa; color: #007bff; }
    
    /* Dark Mode support for dropdown in parent */
    body.dark-mode #unique_source_table { background-color: #1e1e1e; border-color: #333; box-shadow: 0 10px 25px rgba(0,0,0,0.5); }
    body.dark-mode .unique-option { color: #e0e0e0; border-bottom-color: #2a2a2a; }
    body.dark-mode .unique-option:hover { background-color: #2a2a2a; color: #4da3ff; }
    /* Assigned User Dropdown Styles End */
     .side-menu li.sideactive1{
      background: var(--shicol);
    position: relative
  }
  .side-menu li.sideactive1 a{
      color: white
  }

  #remarks{
    font-size: 14px !important;
    color: #0000009e !important;
    padding: 8px 12px !important;
  }
  body.dark-mode #remarks{
    color: white !important;
  }

  body.dark-mode .form-control::placeholder{
    color: #454545;
  }
  
  /* SweetAlert2 z-index - Must be higher than all modals */
  .swal2-container {
    z-index: 2999999 !important;
  }
  
  .swal2-popup {
    z-index: 3000000 !important;
  }
  
  /* SweetAlert2 Dark Mode Overrides */
  body.dark-mode .swal2-popup {
    background: rgba(30, 30, 30, 0.95) !important;
    backdrop-filter: blur(10px) !important;
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
  }

  body.dark-mode .swal2-title,
  body.dark-mode .swal2-content,
  body.dark-mode .swal2-html-container {
    color: #ffffff !important;
  }

  body.dark-mode .swal2-confirm.swal2-styled {
    background: linear-gradient(135deg, #3c83f6 0%, #1c5adf 100%) !important;
    box-shadow: 0 4px 12px rgba(60, 131, 246, 0.3) !important;
  }

  body.dark-mode .swal2-cancel.swal2-styled {
    background: rgba(255, 255, 255, 0.1) !important;
    color: #ffffff !important;
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
  }

  body.dark-mode .swal2-cancel.swal2-styled:hover {
    background: rgba(255, 255, 255, 0.2) !important;
  }

  body.dark-mode .swal2-timer-progress-bar {
    background: rgba(255, 255, 255, 0.3) !important;
  }
  /* Lead Source Dropdown Dark Mode */
  body.dark-mode #leadSource {
    background-color: rgba(30,30,30,0.95);
    border-color: rgba(255,255,255,0.1);
    color: #e2e8f0;
  }

  body.dark-mode #leadSource option {
    background-color: rgba(30,30,30,0.95);
    color: #e2e8f0;
  }

  /* ============================================
     DESKTOP VIEW - SAME AS OTHER PAGES
     ============================================ */
  @media (min-width: 1025px) {
    /* Desktop uses same sidebar widths as other pages - no override needed */
    
    /* When sidebar is open (uncollapsed) - same as dashboard */
    .content {
      position: relative;
      width: calc(100% - var(--sidebar-width));
      left: var(--sidebar-width);
      max-width: none;
      margin-left: 0;
      margin-right: 0;
    }
    
    /* Center the contentinside wrapper like incentivemain */
    .contentinside {
      max-width: var(--content-max-width);
      margin: 0 auto;
    }
    
    /* Collapsed state will be styled separately */
    .sidebar.close ~ .content,
    .sidebar.collapsed ~ .content,
    body.sidebar-collapsed .content {
      /* Will be styled separately */
    }
  }
  @media (max-width: 1024px) {
    .content,
    .sidebar.close ~ .content,
    .sidebar.collapsed ~ .content,
    body.sidebar-collapsed .content {
      width: 100%;
      max-width: 100%;
      margin-left: 0;
      margin-right: 0;
    }
    .table-container,
    .newsec {
      width: 100%;
      max-width: 100%;
    }
  }
  .addExpenses{ 
  display: none;
  }.mt-custom {margin-top: 0.3rem !important;}
  /* Nested table styling to match userlogin6 */
  .nested-section {
    background: transparent !important;
    border-top: 1px solid #e2e8f0;
    padding: 1rem 0;
    position: relative;
    overflow: visible;
  }
  .nested-table-container {
    max-height: 400px;
    overflow-y: auto;
    position: relative;
    border: 1px solid #e2e8f0;
    border-radius: 0.75rem;
    background: #fff;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,.1);
  }
  .compact-table {
    width: 100%;
    border-collapse: collapse;
    background: #fff;
  }
  /* Override any conflicting styles for nested table header */
  .nested-table-container .compact-table-head,
  .nested-section .compact-table-head,
  .compact-table .compact-table-head {
    background: linear-gradient(135deg, #f1f5f9, #f8fafc) !important;
    border-bottom: 1px solid #e2e8f0 !important;
    position: sticky !important;
    top: 0 !important;
    z-index: 300 !important;
    box-shadow: 0 2px 4px rgba(0,0,0,.05) !important;
  }
  .nested-table-container .compact-table-head th,
  .nested-section .compact-table-head th,
  .compact-table .compact-table-head th,
  .compact-table-head th {
    position: sticky !important;
    top: 0 !important;
    z-index: 310 !important;
    padding: 0.6rem 1rem !important;
    text-align: left !important;
    font-weight: 600 !important;
    color: #374151 !important;
    font-size: 0.75rem !important;
    background: linear-gradient(135deg, #f1f5f9, #f8fafc) !important;
    text-transform: uppercase !important;
    letter-spacing: 0.05em !important;
    border: none !important;
  }
  .nested-table-container .compact-table thead,
  .nested-section .compact-table thead,
  .compact-table thead {
    background: linear-gradient(135deg, #f1f5f9, #f8fafc) !important;
  }
  .nested-table-container .compact-table thead th,
  .nested-section .compact-table thead th,
  .compact-table thead th {
    background: linear-gradient(135deg, #f1f5f9, #f8fafc) !important;
    color: #374151 !important;
    text-align: center !important;
  }
  .compact-row {
    border-bottom: 1px solid #f1f5f9;
    transition: 0.2s;
    background: #fff;
    position: relative !important;
    z-index: 0 !important;
  }
  .compact-row:last-child {
    border-bottom: none;
  }
  .compact-row:hover {
    background: #f8fafc;
  }
  .compact-row td {
    padding: 1rem;
    vertical-align: middle;
  }
  .compact-row td:last-child {
    display: flex;
    justify-content: center;
    align-items: center;
    text-align: center;
  }
  /* Action buttons side by side */
  .compact-row td:last-child > div {
    display: flex;
    gap: 5px;
    align-items: center;
    flex-wrap: wrap;
    justify-content: center;
  }
  .btn-toggle, .editLink, .deleteLink, .btn-download {
    white-space: nowrap;
  }
  /* Make Show More and Edit buttons have same styling except color */
  .btn-toggle {
    padding: 0.375rem 0.75rem !important;
    font-size: 0.875rem !important;
    font-weight: 500 !important;
    border-radius: 50rem !important;
    border: 1px solid transparent !important;
    line-height: 1.5 !important;
    text-align: center !important;
    vertical-align: middle !important;
    cursor: pointer !important;
    transition: all 0.15s ease-in-out !important;
    background-color: #0d6efd !important;
    color: #fff !important;
  }
  .btn-toggle:hover {
    background-color: #0b5ed7 !important;
    border-color: #0a58ca !important;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1) !important;
  }
  .btn-toggle:active {
    transform: translateY(0);
    box-shadow: 0 1px 2px rgba(0,0,0,0.1) !important;
  }
  .editLink {
    padding: 0.375rem 0.75rem !important;
    font-size: 0.875rem !important;
    font-weight: 500 !important;
    border-radius: 50rem !important;
    border: 1px solid transparent !important;
    line-height: 1.5 !important;
    text-align: center !important;
    vertical-align: middle !important;
    cursor: pointer !important;
    transition: all 0.15s ease-in-out !important;
    text-decoration: none !important;
    display: inline-block !important;
    background-color: #198754 !important;
    color: #fff !important;
  }
  .editLink:hover {
    background-color: #157347 !important;
    border-color: #146c43 !important;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1) !important;
    color: #fff !important;
  }
  .editLink:active {
    transform: translateY(0);
    box-shadow: 0 1px 2px rgba(0,0,0,0.1) !important;
  }
  /* Icons styling for outer table */
  .group-header.view td i {
    margin-right: 5px;
    font-size: 14px;
    vertical-align: middle;
  }
  /* Match userlogin6 table styling exactly */
  .table-card {
    background: transparent !important;
    box-shadow: none !important;
    border: 0;
    width: 100%;
    display: flex;
    flex-direction: column;
    flex: 1 1 auto;
    min-height: 0;
    backdrop-filter: blur(4px);
    box-sizing: border-box;
  }
  .table-container {
    overflow-x: auto;
    overflow-y: auto;
    position: relative;
    background: transparent !important;
    -webkit-overflow-scrolling: touch;
    width: 100%;
    flex: 1 1 auto;
    min-height: 0;
    border-radius: 1rem;
    padding: 0.5rem;
    box-shadow: 0 8px 20px rgba(15, 23, 42, 0.08);
  }
  
  /* Floating Clear Filters Button */
  .floating-clear-filters-btn {
    position: fixed;
    bottom: 30px;
    right: 30px;
    background-color: #dc3545; /* Red background */
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 50px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(220, 53, 69, 0.4);
    z-index: 9999;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 8px;
  }
  @media (max-width: 768px) {
    .floating-clear-filters-btn {
      padding: 5px 14px;
      font-size: 12px;
    }
  }
  
  .floating-clear-filters-btn:hover {
    background-color: #c82333;
    box-shadow: 0 6px 16px rgba(220, 53, 69, 0.6);
    transform: translateY(-2px);
  }
  
  .floating-clear-filters-btn:active {
    transform: translateY(0);
  }
  
  .floating-clear-filters-btn i {
    font-size: 18px;
  }
  
  /* Apply Filters Button Styling */
  .filter-submit {
    background-color: #28a745 !important; /* Green background */
    color: white !important;
    border: none !important;
    font-weight: 600 !important;
    transition: all 0.3s ease !important;
    border-radius: 13px;
    padding: 14px;
  }
  
  .filter-submit:hover {
    background-color: #218838 !important;
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(40, 167, 69, 0.3) !important;
  }
  
  .filter-submit:active {
    transform: translateY(0);
  }
  
  /* Clear Filters Button in Modal Styling - Higher specificity to override */
  .form-actions .clear-filters-modal-btn,
  .modal-container-eoi .clear-filters-modal-btn,
  button.clear-filters-modal-btn {
    background-color: #dc3545 !important; /* Red background */
    color: white !important;
    border: none !important;
    font-weight: 600 !important;
    transition: all 0.3s ease !important;
    border-radius: 13px !important;
    
  }
  
  .form-actions .clear-filters-modal-btn:hover,
  .modal-container-eoi .clear-filters-modal-btn:hover,
  button.clear-filters-modal-btn:hover {
    background-color: #c82333 !important;
    transform: translateY(-1px) !important;
    box-shadow: 0 4px 8px rgba(220, 53, 69, 0.3) !important;
  }
  
  .form-actions .clear-filters-modal-btn:active,
  .modal-container-eoi .clear-filters-modal-btn:active,
  button.clear-filters-modal-btn:active {
    transform: translateY(0) !important;
  }

  /* Responsive styling for filter buttons on mobile */
  @media (max-width: 768px) {
    .form-actions {
      display: flex !important;
      flex-direction: row !important;
      gap: 10px !important;
      flex-wrap: nowrap !important;
      justify-content: space-between !important;
      align-items: center !important;
      width: 100% !important;
    }

    .form-actions .clear-filters-modal-btn,
    .form-actions .filter-submit,
    button.clear-filters-modal-btn,
    button.filter-submit {
      flex: 1 1 50% !important;
      width: 48% !important;
      max-width: 50% !important;
      min-width: 0 !important;
      padding: 12px 8px !important;
      font-size: 14px !important;
      white-space: nowrap !important;
      overflow: hidden !important;
      text-overflow: ellipsis !important;
      border-radius: 10px !important;
      text-align: center !important;
      display: flex !important;
      justify-content: center !important;
      align-items: center !important;
    }
  }

  @media (max-width: 480px) {
    .form-actions .clear-filters-modal-btn,
    .form-actions .filter-submit,
    button.clear-filters-modal-btn,
    button.filter-submit {
      padding: 10px 6px !important;
      font-size: 13px !important;
    }
  }

  @media (max-width: 375px) {
    .form-actions .clear-filters-modal-btn,
    .form-actions .filter-submit,
    button.clear-filters-modal-btn,
    button.filter-submit {
      padding: 8px 4px !important;
      font-size: 12px !important;
    }
  }


  /* ==================================================
     FORM-ITEM STYLING - FROM SUPERADMIN_NEW REFERENCE
     ================================================== */
  
  .form-item {
    margin-bottom: 0.3rem;
    position: relative !important;
  }
  
  .form-item input,
  .form-item select {
    display: block !important;
    background: transparent !important;
    border: 1px solid #000 !important;
    transition: .3s !important;
    padding: 0 15px !important;
    border-radius: 8px !important;
    width: 100% !important;
    font-size: 14px !important;
    font-weight: 500 !important;
    color: #0000009e !important;
  }
  
  /* Special padding for file input to prevent label overlap */
  .form-item input[type="file"] {
    padding-top: 10px !important;
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
  
  /* Modal content styling matching reference */
  .custom-modal-content {
    overflow: visible !important;
  }

  @media (max-width: 480px) {
    #editUserModal .btnwraps{
      display: flex !important;
      flex-direction: row !important;
      gap: 5px !important;
      flex-wrap: nowrap !important;
      margin-bottom: 10px !important;
    }
    #editUserModal .bttm-btn{
      padding: 4px 8px !important;
      font-size: 13px !important;
    }
  }

  /* Force status buttons to be horizontal in edit modal */
  #editUserModal .btnwraps {
    display: flex !important;
    flex-direction: row !important;
    gap: 15px ;
    flex-wrap: wrap ;
    padding: 1px 10px 15px 20px;
  }

  #editUserModal .bttm-btn {
    display: inline-flex !important;
    margin: 0 !important;
    flex: 0 0 auto !important;
    border-radius: 20px !important;
    padding: 8px 24px ;
    font-weight: 500 !important;
    font-size: 14px ;
    border-width: 1px !important;
  }
  
  /* Specific colors for each button type */
  #editUserModal .btn-outline-primary.bttm-btn {
    border-color: #0d6efd !important;
    color: #0d6efd !important;
  }
  
  #editUserModal .btn-outline-success.bttm-btn {
    border-color: #198754 !important;
    color: #198754 !important;
  }
  
  #editUserModal .btn-outline-danger.bttm-btn {
    border-color: #dc3545 !important;
    color: #dc3545 !important;
  }
  
  /* Thicker border when button is checked/selected */
  #editUserModal .btn-check:checked + .bttm-btn {
    border-width: 2px !important;
  }
  
  /* Keep filled colors when selected */
  #editUserModal .btn-check:checked + .btn-outline-primary.bttm-btn {
    background-color: #0d6efd !important;
    color: white !important;
  }
  
  #editUserModal .btn-check:checked + .btn-outline-success.bttm-btn {
    background-color: #198754 !important;
    color: white !important;
  }
  
  #editUserModal .btn-check:checked + .btn-outline-danger.bttm-btn {
    background-color: #dc3545 !important;
    color: white !important;
  }

  /* Select2 Dropdown Fixes for Filter Modal */
  .select2-container {
    z-index: 1050 !important;
  }
  
  .select2-container--open {
    z-index: 1055 !important;
  }
  .select2-container--default .select2-selection--multiple{
    border: 1px solid #000 !important;
  }

  .select2-container--default .select2-results>.select2-results__options {
    max-height: 200px;
    overflow-y: auto;
    scrollbar-width: none;
  }
  
  .select2-dropdown {
    z-index: 1055 !important;
    border: 1px solid #000 !important;
    background-color: white !important;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1) !important;
    display: block !important;
    position: absolute !important;
  }
  
  .select2-container--bootstrap-5 .select2-dropdown {
    z-index: 1055 !important;
    border: 1px solid #000 !important;
    background-color: white !important;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1) !important;
    display: block !important;
    position: absolute !important;
  }
  
  .select2-search--dropdown {
    background-color: white !important;
    padding: 8px !important;  
    z-index: 1055 !important;
  }
  
  .select2-search__field {
    width: 100% !important;
  }
  
  .select2-results {
    background-color: white !important;
    max-height: 300px !important;
    overflow-y: auto !important;
  }
  
  .select2-results__options {
    background-color: white !important;
  }
  
  .select2-container--bootstrap-5 .select2-results__option {
    padding: 8px 12px !important;
    background-color: white !important;
    color: #000 !important;
    font-size: 14px !important;
  }
  
  .select2-container--bootstrap-5 .select2-results__option--highlighted {
    background-color: #6ea8fe !important;
    color: white !important;
  }
  
  .select2-container--bootstrap-5 .select2-results__option--selected {
    background-color: #0d6efd !important;
    color: white !important;
  }
  
  .select2-container--bootstrap-5 .select2-selection {
    border: 1px solid #000 !important;
    border-radius: 5px !important;
    min-height: 44px !important;
    padding: 8px 12px !important;
    background: white !important;
  }
  
  /* Fixed height for multi-select with scroll */
  .select2-selection--multiple {
    padding: 0 !important;
  }
  .select2-container--default .select2-selection--multiple {
    background-color: transparent;
    border: 1px solid #aaa;
    border-radius: 8px;
    cursor: text;
    padding-bottom: 5px;
    padding-right: 5px;
  }
  .select2-container--default.select2-container--open.select2-container--below .select2-selection--single, .select2-container--default.select2-container--open.select2-container--below .select2-selection--multiple {
    overflow-y: auto;
    max-height: 40px;
    border-bottom-left-radius: 0;
    border-bottom-right-radius: 0;
  }

  .select2-container--default .select2-selection--multiple {
    position: static;
  }
  .select2-container .select2-selection--multiple {
    box-sizing: border-box;
    cursor: pointer;
    display: block;
    min-height:38px;
    max-height: 40px;
    overflow-y: auto;
    scrollbar-width: none;
  }

  
  .select2-selection--multiple .select2-selection__rendered {
    display: inline !important;
    flex-wrap: wrap !important;
    align-items: center !important;
    gap: 4px !important;
    padding: 2px 2px !important;
    overflow-x: hidden !important;
  }
  
  .select2-selection--multiple .select2-selection__rendered::-webkit-scrollbar {
    width: 5px !important;
    height: 5px !important;
  }
  
  .select2-selection--multiple .select2-selection__rendered::-webkit-scrollbar-thumb {
    background: #cbd5e1 !important;
    border-radius: 10px !important;
  }
  
  .select2-selection--multiple .select2-selection__rendered::-webkit-scrollbar-track {
    background: transparent !important; /* Invisible track */
  }
  
  .select2-selection--multiple .select2-selection__rendered::-webkit-scrollbar-button {
    display: none !important; /* Remove arrows */
  }
  
  .select2-selection--multiple .select2-selection__choice {
    margin: 4px !important;
    padding: 3px 8px !important;
    font-size: 13px !important;
    flex-shrink: 0 !important;
    background-color: #e3f2fd !important;
    border: 1px solid #90caf9 !important;
    border-radius: 4px !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 4px !important;
    height: 26px !important; /* Fixed tag height */
  }
  
  .select2-selection--multiple .select2-selection__choice__remove {
    color: #1976d2 !important;
    font-weight: bold !important;
    margin-right: 0 !important;
  }
  
  .select2-selection--multiple .select2-selection__choice__remove:hover {
    color: #d32f2f !important;
  }
  
  .select2-selection--multiple .select2-search--inline {
    margin-top: 4px;
    float: left;
    flex: 0 1 auto !important; /* Allow shrinking, limited growing */
    min-width: 40px !important; /* Small minimum */
    width: auto !important; /* Natural width */
    max-width: 100% !important;
    align-items: center !important;
  }
  
  .select2-selection--multiple .select2-search--inline .select2-search__field {
    margin-left: 12px;
    padding: 0 !important; /* Remove all padding */
    width: 100% !important;
    border: none !important;
    outline: none !important;
    background: transparent !important;
    font-size: 14px !important;
    vertical-align: top !important; /* Align to top */
    display: block !important;
    /* Force textarea to behave like input */
    resize: none !important;
    white-space: nowrap !important;
    box-sizing: border-box !important;
  }
  
  .select2-selection--multiple .select2-search--inline .select2-search__field::placeholder {
    color: #9ca3af !important;
    opacity: 0.7 !important;
    line-height: 26px !important; /* Match field line-height */
    vertical-align: top !important;
  }
  
  /* Modal and backdrop z-index fix - from leads page */
  .modal { 
    overflow: visible !important; 
  } 
  
  .modal-dialog { 
    overflow-y: initial !important; 
    pointer-events: auto !important; 
  } 
  
  .modal-content { 
    pointer-events: auto !important; 
  }
  
  .modal-backdrop { 
    z-index: 1040 !important; 
  }
  
  .modal-backdrop.show { 
    opacity: 0.5 !important; 
  }
  
  /* Specific fix for filterModalOverlay */
  #filterModalOverlay {
    z-index: 10500 !important;
    background-color: rgb(9 9 9 / 41%) !important; /* Dark backdrop like leads page */
    backdrop-filter: blur(6px) saturate(1.2) !important; /* Optional: adds blur effect */
  }
  
  #filterModalOverlay.show {
    background-color: rgba(0, 0, 0, 0.5) !important;
  }
  
  #filterModalOverlay .modal-dialog {
    z-index: 1051 !important;
  }
  
  #filterModalOverlay .modal-content {
    z-index: 1052 !important;
  }
  
  /* Ensure Select2 dropdown appears above everything */
  body > .select2-container {
    z-index: 99999 !important;
  }
  
  body > .select2-container--open {
    z-index: 99999 !important;
  }
  
  body > .select2-dropdown {
    z-index: 99999 !important;
  }
  
  .select2-container--bootstrap-5 .select2-search__field {
    border: 1px solid #dee2e6 !important;
    padding: 6px 12px !important;
    background: white !important;
    color: #000 !important;
  }
  
  .select2-container--open .select2-dropdown {
    display: block !important;
    visibility: visible !important;
  }
  
  /* Ensure modal doesn't hide dropdown */
  .modal-overlay {
    overflow: visible !important;
  }
  
  .modal-container-eoi {
    overflow: visible !important;
  }
  
  /* Mobile Responsive Media Queries for Filter Modal */
  @media (max-width: 768px) {
    /* Modal adjustments for tablet/mobile */
    #filterModalOverlay .modal-dialog {
      margin: 1rem !important;
      max-width: calc(100% - 2rem) !important;
    }
    
    #filterModalOverlay .modal-content {
      padding: 1.5rem 1rem !important;
    }
    
    #filterModalOverlay .modal-header h5 {
      font-size: 1.25rem !important;
    }
    
    /* Form field adjustments */
    .form-item label {
      font-size: 0.813rem !important;
      margin-bottom: 0.4rem !important;
    }
    
    .form-control {
      font-size: 0.875rem !important;
      padding: 0.5rem 0.75rem !important;
    }
    
    /* Select2 adjustments */
    .select2-container--default .select2-selection--multiple {
      min-height: 40px !important;
      max-height: 40px !important;
      font-size: 0.875rem !important;
    }
    
    .select2-container--default .select2-selection--multiple .select2-selection__choice {
      font-size: 0.75rem !important;
      padding: 3px 6px !important;
    }
    
    /* Button adjustments */
    .modal-footer .btn {
      padding: 0.625rem 1.25rem !important;
      font-size: 0.875rem !important;
    }
    
    /* Grid adjustments - stack on mobile */
    .row .col-md-6 {
      margin-bottom: 1rem !important;
    }
  }
  
  @media (max-width: 480px) {
    /* Smaller mobile adjustments */
    #filterModalOverlay .modal-dialog {
      margin: 0.5rem !important;
      max-width: calc(100% - 1rem) !important;
    }
    
    #filterModalOverlay .modal-content {
      padding: 1rem 0.75rem !important;
    }
    
    #filterModalOverlay .modal-header h5 {
      font-size: 1.125rem !important;
    }
    
    /* Smaller fonts */
    .form-item label {
      font-size: 0.75rem !important;
      margin-bottom: 0.3rem !important;
    }
    
    .form-control {
      font-size: 0.813rem !important;
      padding: 0.45rem 0.65rem !important;
      min-height: 38px !important;
    }
    
    /* Select2 smaller */
    .select2-container--default .select2-selection--multiple {
      min-height: 38px !important;
      max-height: 38px !important;
      font-size: 0.813rem !important;
      padding: 3px 6px !important;
    }
    
    .select2-container--default .select2-selection--multiple .select2-selection__choice {
      font-size: 0.688rem !important;
      padding: 2px 5px !important;
    }
    
    /* Footer buttons stack vertically */
    .modal-footer {
      gap: 0.5rem !important;
    }
    
    .modal-footer .btn {
      padding: 0.55rem 0.7rem !important;
      font-size: 0.775rem !important;
    }
    
    .row .col-md-6 {
      margin-bottom: 0.75rem !important;
    }
  }

  .form-control {
    border: 1px solid black !important;
  }
  
  @media (max-width: 375px) {
    /* Extra small mobile */
    #filterModalOverlay .modal-content {
      padding: 0.875rem 0.625rem !important;
    }
    
    #filterModalOverlay .modal-header h5 {
      font-size: 1rem !important;
    }
    
    .form-item label {
      font-size: 0.688rem !important;
    }
    
    .form-control {
      font-size: 0.75rem !important;
      padding: 0.4rem 0.6rem !important;
      min-height: 36px !important;
    }
    
    .select2-container--default .select2-selection--multiple {
      min-height: 36px !important;
      max-height: 36px !important;
      font-size: 0.75rem !important;
    }
    
    .modal-footer .btn {
      padding: 0.425rem 0.575rem !important;
      font-size: 0.753rem !important;
    }
  }


  .custom-select {
    position: relative;
  }

  .lead-select {
    position: relative;
    cursor: pointer;
  }

  .lead-btn {
    width: 100%;
    padding: 12px 16px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    background: white;
    text-align: left;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 14px;
    transition: all 0.2s;
  }

  .lead-btn:hover {
    border-color: #9ca3af;
  }

  .lead-btn::after {
    content: '';
    width: 0;
    height: 0;
    border-left: 5px solid transparent;
    border-right: 5px solid transparent;
    border-top: 6px solid #6b7280;
    transition: transform 0.2s;
    margin-left: 8px;
  }

  .lead-select.open .lead-btn::after {
    transform: rotate(180deg);
  }

  .lead-value {
    color: #374151;
  }

  .lead-options {
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    right: 0;
    background: white;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    max-height: 240px;
    overflow-y: auto;
    z-index: 1000;
    display: none;
    list-style: none;
    padding: 4px;
    margin: 0;
  }

  .lead-select.open .lead-options {
    display: block;
  }

  .lead-option {
    padding: 10px 12px;
    cursor: pointer;
    border-radius: 6px;
    transition: background-color 0.15s;
  }

  .lead-option:hover {
    background-color: #f3f4f6;
  }

  .lead-option[aria-selected="true"] {
    background-color: #e5e7eb;
    font-weight: 500;
  }

  .lead-option:focus {
    outline: none;
    background-color: #dbeafe;
  }

  @media (max-width: 768px) {
    .table-container {
      padding: 0.25rem;
      border-radius: 0.75rem;
    }
  }
  .table {
    width: 100%;
    min-width: 1150px;
    border-collapse: separate;
    border-spacing: 0 0.5rem;
    background: transparent !important;
    table-layout: auto;
    margin: 0;
  }
  .table-head {
    position: sticky !important;
    top: 0 !important;
    z-index: 200 !important;
    background: #fff !important;
    box-shadow: 0 4px 10px rgba(15, 23, 42, 0.08) !important;
    border-top: none !important;
  }
  .table-head, .table-header {
    border: none;
    box-shadow: none;
  }
  .table-head th {
    position: sticky !important;
    top: 0 !important;
    z-index: 210 !important;
    padding: 1rem 2rem !important;
    text-align: center !important;
    font-weight: 700 !important;
    color: #6b7280 !important;
    font-size: 0.85rem !important;
    text-transform: uppercase !important;
    letter-spacing: 0.08em !important;
    background: #fff !important;
    border: none !important;
    box-shadow: none !important;
    border-radius: 0 !important;
  }
  .table-head th:first-child,
  .table-head th:last-child {
    border-radius: 0;
    border-left: none;
    border-right: none;
  }
  .table-head th {
    border-left: none !important;
    border-right: none !important;
    border-top: none !important;
    border-bottom: none !important;
  }
  .table-row {
    border-radius: 1rem !important;
    transition: 0.2s;
    cursor: pointer;
    background: transparent !important;
    box-shadow: 0 8px 20px rgba(15, 23, 42, 0.08);
    border: 1px solid rgba(15, 23, 42, 0.06);
    position: relative;
    z-index: 0 !important;
  }
  .table-row:nth-child(2n) {
    background: #fff !important;
  }
  .table-row:hover {
    background: transparent !important;
    box-shadow: 0 6px 10px -1px rgba(0,0,0,.15);
  }
  .table-row td {
    padding: 1.5rem 2rem;
    font-weight: 500;
    color: #1e293b;
    border-top: 1px solid #f1f5f9;
    border-bottom: 1px solid #f1f5f9;
    pointer-events: auto;
    position: relative;
  }
  .table-row td:first-child {
    font-weight: 600;
    color: #1e293b;
    white-space: nowrap !important;
    word-break: normal;
    line-height: 1.4;
    min-width: 120px;
  }
  .table-row td:last-child {
    border-right: 1px solid transparent;
    border-radius: 0 1rem 1rem 0;
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 0.75rem;
  }
  .table-row td {
    pointer-events: auto !important;
  }
  .table-row {
    pointer-events: auto !important;
  }
  .customer-info {
    display: flex;
    align-items: center;
    gap: 0 !important;
  }
  .customer-avatar {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    width: 2.25rem;
    height: 2.25rem;
    border-radius: 0.75rem;
    color: #fff;
    font-weight: 600;
    width: 2rem;
    height: 2rem;
  }
  .customer-name {
    font-weight: 600;
    letter-spacing: 0.03em;
    margin: 0;
  }
  .customer-contact {
    display: block;
    margin: 0;
    font-size: 0.85rem;
    color: #475569;
  }
  .expand-icon {
    width: 1.5rem;
    height: 1.5rem;
    padding: 6px;
    border-radius: 50%;
    transition: 0.3s;
    cursor: pointer;
    background: #000;
    color: #fff;
  }
  .expand-icon.rotated {
    transform: rotate(90deg) !important;
  }
  .expand-icon:hover:not(.rotated) {
    transform: rotate(0deg) !important;
    box-shadow: 0 4px 10px rgba(126,34,206,.35);
  }
  .expand-icon.rotated:hover {
    box-shadow: 0 4px 10px rgba(147,51,234,.45);
  }
  .month-cell {
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }
  .calendar-icon {
    flex-shrink: 0;
  }
  .amount.zero {
    color: #64748b;
  }
  .amount.positive {
    color: #059669;
  }
  /* Sidebar - must be clickable and above content */
  .sidebar {
    z-index: 10000 !important;
    position: fixed !important;
    pointer-events: auto !important;
  }
  .sidebar * {
    pointer-events: auto !important;
  }
  .side-menu,
  .side-menu li,
  .side-menu li a {
    pointer-events: auto !important;
    z-index: inherit !important;
  }
  /* Navbar styles - match other pages */
  .navbar {
    height: var(--navbar-height, 55px);
    padding: 0 72px 0 20px;
    display: flex;
    align-items: center;
    position: fixed;
    width: 100%;
    top: 0;
    left: 0;
    z-index: 260;
    gap: 16px;
    background: transparent;
    box-shadow: none;
    border-bottom: none;
    backdrop-filter: blur(50px);
    -webkit-backdrop-filter: none;
    pointer-events: auto !important;
  }
  @media screen and (max-width: 1024px) {
    .navbar {
      padding: 0 22px 0px 20px;
    }
  }
  @media screen and (max-width: 728px) {
    .navbar {
      padding: 0 12px 0px 20px;
    }
  }
  .navbar * {
    pointer-events: auto !important;
  }
  /* Modal - override external CSS, properly centered and clickable */
  .modal {
    z-index: 10050 !important;
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    width: 100% !important;
    height: 100% !important;
    display: none !important;
    overflow: hidden !important;
    pointer-events: auto !important;
    padding: 0 !important;
  }
  .modal.show,
  .modal.fade.show {
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    pointer-events: auto !important;
    padding: 1rem !important;
  }
  /* CRITICAL: Ensure modal-dialog is centered - override ALL external CSS */
  body .modal.show .modal-dialog,
  body .modal.fade.show .modal-dialog {
    margin: 0 !important;
    margin-left: auto !important;
    margin-right: auto !important;
    margin-top: 0 !important;
    margin-bottom: 0 !important;
  }
    /* Keep Bootstrap defaults for generic modals; specific overrides live below */
  .modal.show .modal-dialog-centered,
  .modal.fade.show .modal-dialog-centered {
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    min-height: 0 !important;
    max-height: none !important;
    pointer-events: auto !important;
    margin: 0 !important;
    left: auto !important;
    right: auto !important;
    top: auto !important;
    bottom: auto !important;
    transform: none !important;
    height: auto !important;
    width: auto !important;
  }
  /* Force centering for editUserModal specifically - highest specificity */
  #editUserModal.modal.show,
  #editUserModal.modal.fade.show {
      display: flex !important;
      align-items: center !important;
      justify-content: center !important;
      padding: 0 !important;
  }
  #editUserModal.modal.show .modal-dialog,
  #editUserModal.modal.fade.show .modal-dialog,
  #editUserModal.modal.show .modal-dialog-centered,
  #editUserModal.modal.fade.show .modal-dialog-centered {
      position: fixed !important;
      inset: 0 !important;
      margin: auto !important;
      width: min(640px, calc(100vw - 2rem)) !important;
      max-width: 100% !important;
      max-height: calc(100vh - 2rem) !important;
      display: flex !important;
      align-items: stretch !important;
      pointer-events: auto !important;
      transform: none !important;
  }
  .modal-content {
    position: relative !important;
    pointer-events: auto !important;
    z-index: 10051 !important;
    margin: 0 !important;
    max-height: 90vh !important;
    display: flex !important;
    flex-direction: column !important;
    overflow: hidden !important;
  }
  .modal-content * {
    pointer-events: auto !important;
  }
  .modal-body {
    overflow-y: auto !important;
    overflow-x: hidden !important;
    max-height: calc(90vh - 120px) !important;
    flex: 1 1 auto !important;
  }
  /* Close button visibility and styling */
  .btn-close {
    opacity: 1 !important;
    visibility: visible !important;
    display: block !important;
    position: relative !important;
    z-index: 10052 !important;
    pointer-events: auto !important;
    cursor: pointer !important;
    background: transparent url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23000'%3e%3cpath d='M.293.293a1 1 0 011.414 0L8 6.586 14.293.293a1 1 0 111.414 1.414L9.414 8l6.293 6.293a1 1 0 01-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 01-1.414-1.414L6.586 8 .293 1.707a1 1 0 010-1.414z'/%3e%3c/svg%3e") center/1em auto no-repeat !important;
    border: 0 !important;
    border-radius: 0.375rem !important;
    width: 0.8em !important;
    height: 0.8em !important;
    padding: 0.9em !important;
    margin: 0 !important;
    font-size: medium;
  }
  .btn-close:hover {
    opacity: 0.75 !important;
  }
  .modal-header {
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    padding: 1rem !important;
    border-bottom: none !important;
    position: relative !important;
  }
  body.dark-mode .modal-content {
    background:  rgba(30, 30, 30, 0.95) !important;
  }
  body.dark-mode .modal-body {
    background:rgba(30, 30, 30, 0.95) !important;
  }
  body.dark-mode .modal-header {
    background: rgba(30, 30, 30, 0.95) !important;
  }
  body.dark-mode .form-item label {
    background: rgba(30, 30, 30, 0.95) !important;
    color: white !important;
  }
  body.dark-mode .form-item input{
    border: 1px solid #aaaaaa7d !important;
    color: white !important;
  }
  body.dark-mode input[type="date"]{
    color-scheme: dark;
    background: transparent !important;
  }
  body.dark-mode .form-control{
    background: transparent !important;
    color: white !important;
    border: 1px solid #ffffff7d !important;
  }
  body.dark-mode .modal-header .btn-close{
    filter: invert(1) !important;
  }
  body.dark-mode .modal-close-btn{
    color: white !important;
  }
  body.dark-mode .btn .btn-sm .btn-editLink{
    background: transparent !important;
  }
  body.dark-mode .select2-container--default .select2-selection--multiple {
    border: 1px solid #ffffff7d !important;
  }
  body.dark-mode .alert-warning{
    background: transparent !important;
    color: white !important;
    border: none !important;
  }
  body.dark-mode .filter-summary-title,
  body.dark-mode .filter-stat-label,
  body.dark-mode .filter-stat-value{
    color: white !important;
  }

  body.dark-mode .select2-results__option{
    background: rgba(30, 30, 30, 0.95) !important;
  }
  body.dark-mode .select2-selection__choice__display{
    color: black !important;
    margin-left: 6px !important;
  }
  body.dark-mode .select2-selection__choice__remove{
    filter: invert(1);
  }

  .modal-header .btn-close {
    margin-left: auto !important;
    margin-right: 0 !important;
  }
  .modal-backdrop {
    z-index: 10049 !important;
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    width: 100% !important;
    height: 100% !important;
    background: rgba(0, 0, 0, 0.55) !important; /* restore dim overlay instead of bright mask */
  }
  body.modal-open {
    overflow: hidden !important;
  }
  body.modal-open .sidebar,
  body.modal-open .navbar {
    pointer-events: none !important;
  }
  
  /* Modal Overlay - Exact styling from userlogin6 with highest z-index */
  .modal-overlay {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    right: 0 !important;
    bottom: 0 !important;
    backdrop-filter: blur(12px) saturate(1.2) !important;
    display: none !important;
    align-items: center !important;
    justify-content: center !important;
    transition: .3s ease-in-out !important;
    z-index: 999999 !important; /* Highest z-index to appear on top of everything */
    background: rgb(9 9 9 / 40%) !important;
  }
  
  .modal-overlay.active,
  .modal-overlay[style*="flex"] {
    display: flex !important;
    animation: .3s ease-out overlayFadeIn !important;
  }
  
  @keyframes overlayFadeIn {
    from {
      opacity: 0;
      backdrop-filter: blur(0px);
    }
    to {
      opacity: 1;
      backdrop-filter: blur(12px) saturate(1.2);
    }
  }
  
  /* Modal Container - Exact styling from userlogin6 */
  .modal-container-eoi {
    background: white;
    border-radius: 24px !important;
    box-shadow: 0 25px 50px -12px rgba(42, 140, 144, .25), 0 0 0 1px rgba(255, 255, 255, .5), inset 0 1px 0 rgba(255, 255, 255, .7) !important;
    width: 100% !important;
    max-width: 500px !important;
    overflow: auto !important;
    animation: .4s cubic-bezier(.34, 1.56, .64, 1) modalSlideIn !important;
    margin: auto !important;
    position: relative !important;
    backdrop-filter: blur(20px) !important;
    z-index: 1000000 !important; /* Even higher for the container */
  }
  /* Status Buttons Dark Mode Fix - Make inactive buttons transparent */
  body.dark-mode .status-btn:not(.status-btn-active) {
    background: transparent !important;
    color: white !important;
    border: 1px solid rgba(255, 255, 255, 0.5) !important;
  }
  /* Fix for Filter Dropdown Search Input Text Color in Dark Mode */
  body.dark-mode .select2-search__field {
    color: white !important;
    background: transparent !important;
  }

  body.dark-mode .modal-container-eoi{
    box-shadow: none !important;
    background: none !important;
  }
  
  .add-booking-modal {
    max-width: 600px !important;
  }
  
  @keyframes modalSlideIn {
    from {
      opacity: 0;
      transform: translateY(30px) scale(.95);
    }
    to {
      opacity: 1;
      transform: translateY(0) scale(1);
    }
  }
  
  /* Modal Header - Exact styling from userlogin6 */
  .modal-header {
    display: flex !important;
    justify-content: space-between !important;
    align-items: center !important;
    position: sticky !important;
    top: 0 !important;
    z-index: 10000 !important;
    border-radius: 24px 24px 0 0 !important;
    backdrop-filter: blur(10px) !important;
    padding: 0.2rem 0.9rem !important;
    background: white;
  }
  
  .modal-header h3 {
    font-size: 1.0rem !important;
    font-weight: 700 !important;
    color: #1e293b !important;
    margin: 0 !important;
  }
  
  /* Modal Close Button - Exact styling from userlogin6 */
  .modal-close-btn {
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    background: transparent !important;
    border: none !important;
    font-size: 2rem !important;
    color: #64748b !important;
    cursor: pointer !important;
    padding: 0.5rem !important;
    border-radius: 0.5rem !important;
    transition: all 0.2s !important;
    width: 2.5rem !important;
    height: 2.5rem !important;
    line-height: 1 !important;
  }
  
  .modal-close-btn:hover {
    background: rgba(100, 116, 139, 0.1) !important;
    color: #374151 !important;
    transform: scale(1.1) !important;
  }
  
  .modal-close-btn:active {
    transform: scale(0.95) !important;
  }
  
  /* Modal Body - Exact styling from userlogin6 */
  .modal-body {
    padding: 0.7rem !important;
    scrollbar-color: transparent transparent !important;
    background: white;
    border-radius: 0 0 24px 24px !important;
    scrollbar-width: thin;
  }
  
  .modal-container-eoi::-webkit-scrollbar {
    display: none !important;
  }
  
  /* Custom Input Modal Specific Styles - Higher z-index to appear above Add Booking modal */
  #customInputModalOverlay {
    z-index: 1999999 !important; /* Much higher than Add Booking modal */
  }
  
  #customInputModalOverlay .modal-container-eoi {
    max-width: 450px !important;
    z-index: 2000000 !important; /* Even higher for the container */
    max-height: 80vh !important;
  }
  
  /* Mobile responsive - reduce height on small screens */
  @media (max-width: 768px) {
    #customInputModalOverlay .modal-container-eoi {
      max-width: 65% !important;
      max-height: fit-content !important;
      margin: 1rem !important;
    }
    
    #customInputModalOverlay .modal-header {
      padding: 1rem 1.25rem !important;
    }
    
    #customInputModalOverlay .modal-header h3 {
      font-size: 1.25rem !important;
    }
    
    #customInputModalOverlay .modal-body {
      padding: 1rem 1.25rem !important;
    }
    
    #customInputModalOverlay .modal-footer {
      padding: 0.875rem 1.25rem !important;
      flex-direction: row !important;
    }
    
    #customInputModalOverlay .btn {
      padding: 8px 20px !important;
      font-size: 14px !important;
      flex: 1 !important;
    }
  }
  
  @media (max-width: 480px) {
    #customInputModalOverlay .modal-container-eoi {
      max-height: fit-content !important;
      max-width: 95% !important;
      margin: 0.5rem !important;
    }
    
    #customInputModalOverlay .modal-header h3 {
      font-size: 1.125rem !important;
    }
    
    #customInputModalOverlay .btn {
      padding: 8px 16px !important;
      font-size: 13px !important;
    }
  }
  
  #customInputModalField {
    width: 100%;
    padding: 12px 16px;
    border: 1.5px solid #d1d5db;
    border-radius: 8px;
    font-size: 15px;
    transition: all 0.2s ease;
  }
  
  #customInputModalField:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
  }
  
  body.dark-mode #customInputModalField {
    background: rgba(45, 55, 72, 0.5);
    border-color: #4a5568;
    color: #ffffff;
  }
  
  body.dark-mode #customInputModalField:focus {
    border-color: #60a5fa;
    box-shadow: 0 0 0 3px rgba(96, 165, 250, 0.2);
  }
  
  #customInputModalOverlay .modal-footer {
    display: flex;
    gap: 1rem;
    justify-content: center;
    padding: 1rem 1.5rem;
    border-top: 1px solid #e5e7eb;
  }
  
  body.dark-mode #customInputModalOverlay .modal-footer {
    border-top-color: #374151;
  }
  
  #customInputModalOverlay .btn {
    padding: 10px 30px;
    border-radius: 8px;
    font-weight: 500;
    font-size: 15px;
    border: none;
    cursor: pointer;
    transition: all 0.2s ease;
  }
  
  #customInputModalOverlay .btn-secondary {
    background: #6b7280;
    color: white;
  }
  
  #customInputModalOverlay .btn-secondary:hover {
    background: #4b5563;
  }
  
  #customInputModalOverlay .btn-primary {
    background: #3b82f6;
    color: white;
  }
  
  #customInputModalOverlay .btn-primary:hover {
    background: #2563eb;
  }
  
  body.dark-mode #customInputModalOverlay .btn-secondary {
    background: #4b5563;
  }
  
  body.dark-mode #customInputModalOverlay .btn-secondary:hover {
    background: #374151;
  }
  
  body.dark-mode #customInputModalOverlay .btn-primary {
    background: #2563eb;
  }
  
  body.dark-mode #customInputModalOverlay .btn-primary:hover {
    background: #1d4ed8;
  }
  
  /* Section styling */
  .section {
    margin-top: 1rem !important;
  }
  
  .section h3 {
    font-size: 1rem !important;
    font-weight: 700 !important;
    margin: 0.5rem 0 !important;
    color: #1e293b !important;
  }
  
  /* Grid layout */
  .grid {
    display: grid !important;
    grid-template-columns: 1fr !important;
    gap: 0.75rem !important;
  }
  
  @media (min-width: 600px) {
    .grid {
      grid-template-columns: 1fr 1fr !important;
      gap: 1rem !important;
    }
  }
  
  /* Field styling */
  .field {
    display: flex !important;
    flex-direction: column !important;
    gap: 0.5rem !important;
  }
  
  .field.full-row {
    grid-column: 1 / -1 !important;
  }
  
  /* Form actions */
  .form-actions {
    display: flex !important;
    gap: 0.75rem !important;
    justify-content: flex-end !important;
    margin-top: 1.5rem !important;
    padding-top: 1rem !important;
    border-top: 1px solid rgba(42, 140, 144, 0.2) !important;
  }
  
  .cancel-btn,
  .submit-btn {
    padding: 0.75rem 1.5rem !important;
    border-radius: 0.5rem !important;
    font-weight: 600 !important;
    cursor: pointer !important;
    transition: all 0.2s !important;
    border: none !important;
    font-size: 0.875rem !important;
  }
  
  .cancel-btn {
    background: #f1f5f9 !important;
    color: #64748b !important;
  }
  
  .cancel-btn:hover {
    background: #e2e8f0 !important;
    color: #475569 !important;
  }
  
  .submit-btn {
    background: linear-gradient(135deg, #2a8c90, #1f6b6e) !important;
    color: #fff !important;
    box-shadow: 0 4px 12px rgba(42, 140, 144, .3) !important;
  }
  
  .submit-btn:hover {
    transform: translateY(-2px) !important;
    box-shadow: 0 6px 16px rgba(42, 140, 144, .4) !important;
  }
  
  /* Note styling */
  .note {
    border-radius: 10px !important;
    background: #e6f6ff !important;
    color: #0b4a6f !important;
    padding: 0.75rem 1rem !important;
    font-size: 0.875rem !important;
    margin-bottom: 0.1rem !important;
    border: 1px solid #caeaff !important;
  }
  
  /* Date and Month Input Styling - Responsive */
  input[type="date"],
  input[type="month"] {
    width: 100% !important;
    border-radius: 0.5rem !important;
    font-size: 0.875rem !important;
    background: #fff !important;
    color: #1e293b !important;
    transition: all 0.2s !important;
    box-sizing: border-box !important;
    min-height: 2.5rem !important;
  }
  
  input[type="date"]:focus,
  input[type="month"]:focus {
    outline: none !important;
    border-color: #2a8c90 !important;
    box-shadow: 0 0 0 3px rgba(42, 140, 144, 0.1) !important;
  }
  
  input[type="date"]:hover,
  input[type="month"]:hover {
    border-color: #2a8c90 !important;
  }
  
  /* Calendar picker styling - ensure it's visible and properly positioned */
  input[type="date"]::-webkit-calendar-picker-indicator,
  input[type="month"]::-webkit-calendar-picker-indicator {
    cursor: pointer !important;
    opacity: 1 !important;
    padding: 0.25rem !important;
    margin-left: 0.25rem !important;
  }
  
  /* Fieldset label styling for date inputs */
  .fieldset-label {
    position: relative !important;
  }
  
  .fieldset-label input[type="date"],
  .fieldset-label input[type="month"] {
    margin-top: 0.25rem !important;
  }
  @media(max-width:670px){
    .modal-container-eoi .calc-form-actions .calc-submit-btn{
      font-size: 0.6rem;
    }
  }
  
  /* Responsive */
  @media (max-width: 768px) {
    .modal-container-eoi {
      height: 80%;
      max-width: 95% !important;
      border-radius: 16px !important;
    }
    
    .modal-header {
      padding: 0.75rem 1rem !important;
      border-radius: 16px 16px 0 0 !important;
    }
    
    .modal-header h3 {
      font-size: 1.25rem !important;
    }
    
    .modal-body {
      padding: 1rem !important;
      border-radius: 0 0 16px 16px !important;
    }
    
    input[type="date"],
    input[type="month"] {
      font-size: 1rem !important; /* Larger on mobile for better touch targets */
    }
    
    .grid {
      grid-template-columns: 1fr !important;
      gap: 0.75rem !important;
    }
    
    .form-actions {
      flex-direction: row !important;
    }
    .submit-btn{
      padding: 0.90rem 1rem !important;
    }
        
        
    
    .cancel-btn,
    .submit-btn {
      width: 100% !important;
    }
  }
  /* CRITICAL: Table containers must have proper z-index to allow nested sections */
  .table-container {
    z-index: 1 !important;
    position: relative !important;
  }
  .table-card {
    z-index: 1 !important;
    position: relative !important;
  }
  .table-row {
    z-index: 1 !important;
    position: relative !important;
  }
  .maintablewrap {
    z-index: 1 !important;
    position: relative !important;
  }
  .newsec {
    z-index: 1 !important;
    position: relative !important;
  }
  .content {
    z-index: 1 !important;
    position: relative !important;
  }
  /* CRITICAL: Nested section must break out of table stacking context */
  tr[id^="nested-"] {
    pointer-events: auto !important;
    z-index: 5000 !important;
    position: relative !important;
  }
  tr[id^="nested-"] td {
    pointer-events: auto !important;
    z-index: 5000 !important;
    position: relative !important;
  }
  tr[id^="nested-"] * {
    pointer-events: auto !important;
  }
  /* Nested search styling - MUST be below sidebar but above table */
  .nested-section {
    pointer-events: auto !important;
    position: relative !important;
    z-index: 5000 !important;
    isolation: isolate !important; /* Create new stacking context */
  }
  .nested-controls {
    pointer-events: auto !important;
    z-index: 5001 !important;
    position: relative !important;
    isolation: isolate !important;
  }
  .nested-search-wrapper {
    position: relative !important;
    pointer-events: auto !important;
    z-index: 5002 !important;
    isolation: isolate !important;
  }
  /* CRITICAL: Search input - MUST be clickable - override everything */
  .nested-search,
  input.nested-search,
  input[class*="nested-search"] {
    pointer-events: auto !important;
    z-index: 9999 !important;
    position: relative !important;
    cursor: text !important;
    background: white !important;
    border: 1px solid #c9ccd1ff !important;
    opacity: 1 !important;
    visibility: visible !important;
    display: block !important;
    width: 100% !important;
    height: auto !important;
    transform: translateZ(0) !important;
    -webkit-user-select: text !important;
    user-select: text !important;
    -webkit-tap-highlight-color: rgba(0,0,0,0.1) !important;
  }
  /* Ensure nothing overlays the search input */
  .nested-search-wrapper {
    position: relative !important;
    pointer-events: auto !important;
    z-index: 9999 !important;
    isolation: isolate !important;
  }
  .nested-search-wrapper * {
    pointer-events: none !important;
  }
  .nested-search-wrapper .nested-search,
  .nested-search-wrapper input.nested-search {
    pointer-events: auto !important;
  }

  /* ============================================
     RESPONSIVE STYLES FOR ALL SCREEN SIZES
     ============================================ */
  
  /* Ensure body allows horizontal scroll on mobile */
  @media (max-width: 768px) {
    body {
      overflow-x: auto;
      width: 100%;
    }
    html {
      overflow-x: auto;
      width: 100%;
    }
  }
  
  /* Large Tablets and Small Desktops (1024px - 1440px) */
  @media (min-width: 1025px) and (max-width: 1440px) {
    /* When sidebar is open (uncollapsed) - same as dashboard */
    .content {
      position: relative;
      width: calc(100% - var(--sidebar-width));
      left: var(--sidebar-width);
      max-width: none;
      margin-left: 0;
      margin-right: 0;
      padding-top: clamp(1rem, 1vh, 2.5rem);
      padding-left: clamp(0.75rem, 1.5vw, 1.5rem);
      padding-right: clamp(0.75rem, 1.5vw, 1.5rem);
      padding-bottom: clamp(0.25rem, 0.5vh, 0.5rem);
    }
    
    /* Center the contentinside wrapper like incentivemain */
    .contentinside {
      max-width: var(--content-max-width);
      margin: 0 auto;
    }
    
    /* Collapsed state will be styled separately */
    .sidebar.close ~ .content,
    .sidebar.collapsed ~ .content,
    body.sidebar-collapsed .content {
      /* Will be styled separately */
    }
    .content .table-card {
      padding: 0 1.5rem;
    }
    .table-container {
      width: 100%;
    }
    .table-head th,
    .table-row td {
      padding: 1rem 1.5rem;
      font-size: 0.875rem;
    }
  }

  /* Tablets (768px - 1024px) */
  @media (max-width: 1024px) {
    .content {
      width: 100%;
      max-width: 100%;
      margin-left: 0;
      margin-right: 0;
      padding: 50px 0 0.5rem;
    }
    .content .table-card {
      padding: 0 0.75rem;
    }
    .table-container {
      width: 100%;
      max-width: 100%;
      max-height: 65vh;
      overflow-x: auto;
      overflow-y: auto;
      -webkit-overflow-scrolling: touch;
    }
    .newsec {
      width: 100%;
      max-width: 100%;
    }
    .maintablewrap {
      width: 100%;
      overflow-x: auto;
      overflow-y: auto;
      -webkit-overflow-scrolling: touch;
    }
    .table-head th {
      padding: 0.75rem 1rem;
      text-align: center;
      font-weight: 700;
      color: #6b7280 !important;
      padding: 1rem 1.25rem;
      font-size: 0.85rem;
    }
    .table-row td:first-child {
      font-size: 0.9rem;
    }
    .expand-icon {
      white-space: normal !important;
      word-break: break-word;
      line-height: 1.3;
      width: 1.25rem;
      height: 1.25rem;
      padding: 4px;
    }
    .nested-table-container {
      max-height: 350px;
    }
    .compact-table-head th,
    .compact-row td {
      padding: 0.75rem 0.75rem;
      font-size: 0.7rem;
    }
    .modal-dialog {
      max-width: 90% !important;
      width: 90% !important;
    }
  }

  /* Small Tablets and Large Phones (600px - 768px) */
  @media (max-width: 768px) {
    .content {
      margin-left: 0;
      padding: 50px 0 0.5rem;
      width: 100%;
    }
    .content .table-card {
      padding: 0 0.5rem;
    }
    .table-container {
      max-height: 60vh;
      overflow-x: auto;
      overflow-y: auto;
      width: 100%;
      -webkit-overflow-scrolling: touch;
      touch-action: pan-x pan-y;
    }
    .newsec {
      width: 100%;
      overflow-x: auto;
    }
    .maintablewrap {
      width: 100%;
      overflow-x: auto;
      overflow-y: auto;
      -webkit-overflow-scrolling: touch;
      touch-action: pan-x pan-y;
    }
    .table-card {
      width: 100%;
      overflow-x: auto;
    }
    .table {
      min-width: 800px;
    }
    .table-head th {
      padding: 0.6rem 0.75rem;
      font-size: 0.75rem;
      white-space: nowrap;
    }
    .table-row td {
      padding: 0.75rem 0.75rem;
      font-size: 0.8rem;
    }
    .table-row td:first-child {
      font-size: 0.85rem;
      min-width: 120px;
    }
    .month-cell {
      flex-direction: column;
      gap: 0.25rem;
      align-items: flex-start;
    }
    .calendar-icon {
      width: 18px;
      height: 18px;
    }
    .expand-icon {
      width: 1.1rem;
      height: 1.1rem;
    }
    .nested-section {
      padding: 0.75rem 0;
    }
    .nested-controls {
      flex-direction: column;
      gap: 0.75rem;
      padding: 0.75rem;
    }
    .nested-search-wrapper {
      width: 100%;
      max-width: 100%;
    }
    .nested-table-container {
      max-height: 300px;
      border-radius: 0.5rem;
    }
    .compact-table-head th {
      padding: 0.5rem 0.5rem;
      font-size: 0.65rem;
    }
    .compact-row td {
      padding: 0.6rem 0.5rem;
      font-size: 0.65rem;
    }
    .compact-row td:last-child > div {
      flex-direction: column;
      gap: 3px;
    }
    .btn-toggle,
    .editLink,
    .deleteLink,
    .btn-download {
      font-size: 0.7rem;
      padding: 0.25rem 0.5rem;
    }
    .modal-dialog {
      max-width: 95% !important;
      width: 95% !important;
      margin: 0.5rem auto !important;
    }
    .modal-content {
      max-height: 85vh !important;
    }
    .modal-body {
      max-height: calc(85vh - 100px) !important;
      padding: 0.75rem !important;
    }
    .table-row {
      margin-bottom: 0.5rem;
    }
  }

  /* Mobile Phones (480px - 600px) */
  @media (max-width: 600px) {
    .content {
      margin-left: 0;
      padding: 50px 0 0.25rem;
      width: 100%;
    }
    .content .table-card {
      padding: 0 0.5rem;
    }
    .table-container {
      max-height: 55vh;
      overflow-x: auto;
      overflow-y: auto;
      width: 100%;
      -webkit-overflow-scrolling: touch;
      touch-action: pan-x pan-y;
    }
    .newsec {
      width: 100%;
      overflow-x: auto;
    }
    .maintablewrap {
      width: 100%;
      overflow-x: auto;
      overflow-y: auto;
      -webkit-overflow-scrolling: touch;
      touch-action: pan-x pan-y;
    }
    .table-card {
      width: 100%;
      overflow-x: auto;
    }
    .table {
      min-width: 700px;
    }
    .table-head th {
      padding: 0.5rem 0.5rem;
      font-size: 0.7rem;
    }
    .table-row td {
      padding: 0.6rem 0.5rem;
      font-size: 0.75rem;
    }
    .table-row td:first-child {
      font-size: 0.8rem;
      min-width: 100px;
    }
    .amount {
      font-size: 0.7rem;
    }
    .nested-controls {
      padding: 0.5rem;
      gap: 0.5rem;
    }
    .nested-search {
      padding: 0.4rem 0.5rem 0.4rem 2rem !important;
      font-size: 0.75rem !important;
    }
    .per-page-selector {
      font-size: 0.75rem;
    }
    .per-page-select {
      padding: 0.4rem;
      font-size: 0.75rem;
    }
    .nested-table-container {
      max-height: 250px;
    }
    .compact-table-head th {
      padding: 0.4rem 0.3rem;
      font-size: 0.6rem;
    }
    .compact-row td {
      padding: 0.5rem 0.3rem;
      font-size: 0.6rem;
    }
    .customer-info {
      flex-direction: column;
      align-items: flex-start;
      gap: 0.25rem;
    }
    .customer-avatar {
      width: 1.5rem;
      height: 1.5rem;
      font-size: 0.6rem;
    }
    .customer-name,
    .customer-contact {
      font-size: 0.65rem;
    }
    .badge {
      font-size: 0.6rem;
      padding: 0.15rem 0.3rem;
    }
    .nested-pagination {
      flex-direction: column;
      gap: 0.5rem;
      padding: 0.75rem;
    }
    .nested-pagination-controls {
      width: 100%;
      justify-content: center;
    }
    .nested-page-numbers .btn {
      min-width: 1.5rem;
      height: 1.5rem;
      font-size: 0.7rem;
      padding: 0.2rem;
    }
    .modal-dialog {
      max-width: 98% !important;
      width: 98% !important;
      margin: 0.25rem auto !important;
    }
    .modal-header {
      padding: 0.75rem !important;
    }
    .modal-title {
      font-size: 1rem !important;
    }
  }

  /* Small Mobile Phones (320px - 480px) */
  @media (max-width: 480px) {
    .content {
      margin-left: 0;
      padding: 50px 0 0.25rem;
      width: 100%;
    }
    .content .table-card {
      padding: 0 0.35rem;
    }
    .table-container {
      max-height: 50vh;
      overflow-x: auto;
      overflow-y: auto;
      width: 100%;
      -webkit-overflow-scrolling: touch;
      touch-action: pan-x pan-y;
    }
    .newsec {
      width: 100%;
      overflow-x: auto;
    }
    .maintablewrap {
      width: 100%;
      overflow-x: auto;
      overflow-y: auto;
      -webkit-overflow-scrolling: touch;
      touch-action: pan-x pan-y;
    }
    .table-card {
      width: 100%;
      overflow-x: auto;
    }
    .table {
      min-width: 600px;
    }
    .table-head th {
      padding: 0.4rem 0.3rem;
      font-size: 0.65rem;
    }
    .table-row td {
      padding: 0.5rem 0.3rem;
      font-size: 0.7rem;
    }
    .table-row td:first-child {
      font-size: 0.75rem;
      min-width: 80px;
    }
    .month-cell {
      font-size: 0.7rem;
    }
    .calendar-icon {
      width: 16px;
      height: 16px;
    }
    .expand-icon {
      width: 1rem;
      height: 1rem;
      padding: 3px;
    }
    .nested-section {
      padding: 0.5rem 0;
    }
    .nested-controls {
      padding: 0.4rem;
      gap: 0.4rem;
    }
    .nested-search {
      padding: 0.35rem 0.4rem 0.35rem 1.75rem !important;
      font-size: 0.7rem !important;
    }
    .nested-table-container {
      max-height: 200px;
      border-radius: 0.375rem;
    }
    .compact-table-head th {
      padding: 0.3rem 0.2rem;
      font-size: 0.55rem;
    }
    .compact-row td {
      padding: 0.4rem 0.2rem;
      font-size: 0.55rem;
    }
    .btn-toggle,
    .editLink,
    .deleteLink,
    .btn-download {
      font-size: 0.65rem;
      padding: 0.2rem 0.4rem;
    }
    .modal-dialog {
      max-width: 100% !important;
      width: 100% !important;
      margin: 0 !important;
      border-radius: 0 !important;
    }
    .modal-content {
      border-radius: 13px !important;
      max-height: 75vh !important;
    }
    .modal-body {
      max-height: calc(100vh - 80px) !important;
      padding: 0.5rem !important;
    }
    .form-control-lg {
      font-size: 0.9rem !important;
      padding: 0.5rem !important;
    }
    .col-md-6,
    .col-md-12,
    .col-12 {
      padding: 0.25rem;
    }
  }

  /* Extra Small Devices (max-width: 320px) */
  @media (max-width: 320px) {
    .content {
      margin-left: 0;
      padding: 45px 0 0.15rem;
      width: 100%;
    }
    .content .table-card {
      padding: 0 0.25rem;
    }
    .table-container {
      overflow-x: auto;
      overflow-y: auto;
      width: 100%;
      -webkit-overflow-scrolling: touch;
      touch-action: pan-x pan-y;
    }
    .maintablewrap {
      overflow-x: auto;
      overflow-y: auto;
      width: 100%;
      -webkit-overflow-scrolling: touch;
      touch-action: pan-x pan-y;
    }
    .table {
      min-width: 500px;
    }
    .table-head th,
    .table-row td {
      padding: 0.3rem 0.2rem;
      font-size: 0.6rem;
    }
    .compact-table-head th,
    .compact-row td {
      padding: 0.25rem 0.15rem;
      font-size: 0.5rem;
    }
    .btn-toggle,
    .editLink,
    .deleteLink {
      font-size: 0.6rem;
      padding: 0.15rem 0.3rem;
    }
  }

  /* Landscape Orientation Adjustments */
  @media (max-height: 600px) and (orientation: landscape) {
    .content {
      padding: 50px 0 0.25rem;
    }
    .content .table-card {
      padding: 0 0.5rem;
    }
    .table-container {
      max-height: 45vh;
      overflow-x: auto;
      overflow-y: auto;
      -webkit-overflow-scrolling: touch;
    }
    .maintablewrap {
      overflow-x: auto;
      overflow-y: auto;
      -webkit-overflow-scrolling: touch;
    }
    .nested-table-container {
      max-height: 180px;
    }
    .modal-content {
      max-height: 90vh !important;
    }
    .modal-body {
      max-height: calc(90vh - 80px) !important;
    }
  }

  /* Print Styles */
  @media print {
    .sidebar,
    .navbar,
    .nested-controls,
    .nested-pagination,
    .btn,
    .expand-icon {
      display: none !important;
    }
    .table-container {
      max-height: none;
      overflow: visible;
    }
    .nested-section {
      display: block !important;
    }
  }
  </style>
  
  <style>
    #rightSidebarToggleHeader {
      display: none !important;
    }

    body.dark-mode .mobile-year-stats .stat-chip{
    background: transparent !important;
    color: white !important;
    border: 1px solid #ffffff3d !important;
    }

    body.dark-mode .nested-pagination-info{
      color:#ffffffe0 !important;
    }
  </style>
  
  <?php include('header.php'); ?>

  <!-- Right Sidebar Additional Script -->
  <script>
    document.addEventListener('DOMContentLoaded', function() {
    });
    
    // Functions to open modals - Updated to match userlogin6
    function openFilterModal() {
      // Close sidebar first
      const sidebar = document.getElementById('rightSidebar');
      const overlay = document.getElementById('rightSidebarOverlay');
      const headerToggleBtn = document.getElementById('rightSidebarToggleHeader');
      if (sidebar) sidebar.classList.remove('open');
      if (overlay) overlay.classList.remove('active');
      if (headerToggleBtn) headerToggleBtn.classList.remove('active');
      
      const modal = document.getElementById('filterModalOverlay');
      if (modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
      }
    }
    
    function openAddBookingModal() {
      // Close sidebar first
      const sidebar = document.getElementById('rightSidebar');
      const overlay = document.getElementById('rightSidebarOverlay');
      const headerToggleBtn = document.getElementById('rightSidebarToggleHeader');
      if (sidebar) sidebar.classList.remove('open');
      if (overlay) overlay.classList.remove('active');
      if (headerToggleBtn) headerToggleBtn.classList.remove('active');
      
      const modal = document.getElementById('addBookingModalOverlay');
      if (modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
      }
    }
    
    // Open Calculator Modal
    function openCalculatorModal() {
      // Close sidebar first
      const sidebar = document.getElementById('rightSidebar');
      const overlay = document.getElementById('rightSidebarOverlay');
      const headerToggleBtn = document.getElementById('rightSidebarToggleHeader');
      if (sidebar) sidebar.classList.remove('open');
      if (overlay) overlay.classList.remove('active');
      if (headerToggleBtn) headerToggleBtn.classList.remove('active');
      
      const modal = document.getElementById('calculatorModalOverlay');
      if (modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
      }
    }
    
    // Close modal functions - Make globally available (matching userlogin6)
    window.closeFilterModal = function() {
      const modal = document.getElementById('filterModalOverlay');
      if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = '';
      }
    };
    
    window.closeAddBookingModal = function() {
      const modal = document.getElementById('addBookingModalOverlay');
      if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = '';
      }
    };
    
    window.closeCalculatorModal = function() {
      const modal = document.getElementById('calculatorModalOverlay');
      if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = '';
      }
    };
    
    // Make functions globally available
    window.openFilterModal = openFilterModal;
    window.openAddBookingModal = openAddBookingModal;
    window.openCalculatorModal = openCalculatorModal;
    
    // Helper function to get iframe window
    function getIframeWindow() {
      const iframe = document.querySelector('iframe[src*="bookings_superadmin"]');
      return iframe ? iframe.contentWindow : null;
    }
    
    // Helper function to get iframe document
    function getIframeDocument() {
      const iframe = document.querySelector('iframe[src*="bookings_superadmin"]');
      return iframe ? iframe.contentDocument : null;
    }
    
    // Helper: get first existing element by possible IDs (from parent document - modal is in parent)
    function getByIds(ids) {
      const idArray = Array.isArray(ids) ? ids : [ids];
      for (const id of idArray) {
        const el = document.getElementById(id);
        if (el) return el;
      }
      return null;
    }
    
    // Helper: read input value safely (from parent document - modal is in parent)
    function getVal(ids, toLower = true) {
      const el = getByIds(ids);
      if (!el) return '';
      
      // Check if this is a Select2 multi-select or native multi-select
      if (el.multiple || (window.$ && window.$(el).hasClass('select2-hidden-accessible'))) {
        // For Select2 multi-select, get selected values as array
        if (window.$ && window.$(el).hasClass('select2-hidden-accessible')) {
          const selectedValues = window.$(el).val();
          if (Array.isArray(selectedValues) && selectedValues.length > 0) {
            return toLower 
              ? selectedValues.map(v => String(v).toLowerCase().trim()) 
              : selectedValues.map(v => String(v).trim());
          }
          return [];
        }
        // For native multi-select
        const selectedOptions = Array.from(el.selectedOptions || []);
        if (selectedOptions.length > 0) {
          const values = selectedOptions.map(opt => opt.value);
          return toLower 
            ? values.map(v => String(v).toLowerCase().trim()) 
            : values.map(v => String(v).trim());
        }
        return [];
      }
      
      // For single value inputs
      const v = (el.value ?? '').toString().trim();
      return toLower ? v.toLowerCase() : v;
    }

    function escapeSelectorText(value = '') {
      return value.replace(/([!"#$%&'()*+,./:;<=>?@[\\\]^`{|}~])/g, '\\$1');
    }

    function getMainTableBody(doc) {
      if (!doc) return null;
      return doc.getElementById('mainTableBody') ||
        doc.querySelector('#mainTableBody') ||
        doc.querySelector('.main-table-body') ||
        doc.querySelector('.table-body') ||
        doc.querySelector('tbody');
    }

    function getMonthlyRows(doc) {
      if (!doc) return [];
      let rows = Array.from(doc.querySelectorAll('#mainTableBody .table-row[onclick]'));
      if (!rows.length) rows = Array.from(doc.querySelectorAll('.table-row[onclick]'));
      if (!rows.length) rows = Array.from(doc.querySelectorAll('#mainTableBody .table-row'));
      if (!rows.length) rows = Array.from(doc.querySelectorAll('.table-row'));
      return rows;
    }

    function getNestedRow(doc, key) {
      if (!doc || !key) return null;
      const direct = doc.getElementById(`nested-${key}`);
      if (direct) return direct;
      const escaped = escapeSelectorText(key);
      return doc.querySelector(`#nested-${escaped}`) ||
        doc.querySelector(`[data-month="${escaped}"]`) ||
        doc.querySelector(`[data-financial-year="${escaped}"]`) ||
        doc.querySelector(`[data-fy="${escaped}"]`);
    }

    function toggleNoResultsMessage(doc, show, message) {
      const tableBody = getMainTableBody(doc);
      if (!tableBody) return;
      let placeholder = doc.getElementById('parent-filter-no-results');
      if (show) {
        if (!placeholder) {
          placeholder = doc.createElement('tr');
          placeholder.id = 'parent-filter-no-results';
          placeholder.className = 'no-results-row';
          const cell = doc.createElement('td');
          cell.colSpan = 50;
          cell.style.textAlign = 'center';
          cell.style.padding = '1rem';
          cell.textContent = message || 'No bookings match the selected filters.';
          placeholder.appendChild(cell);
          tableBody.appendChild(placeholder);
        } else {
          placeholder.style.display = '';
          if (message) {
            const placeholderCell = placeholder.querySelector('td') || placeholder;
            placeholderCell.textContent = message;
          }
        }
      } else if (placeholder) {
        placeholder.remove();
      }
    }

    const MONTH_NAME_LOOKUP = ['jan','feb','mar','apr','may','jun','jul','aug','sep','oct','nov','dec'];

    function buildMonthVariants(monthValue = '') {
      if (!monthValue) return [];
      const normalized = monthValue.toLowerCase();
      const parts = normalized.split('-');
      const [year, month] = parts.length === 2 ? parts : [null, null];
      const variants = new Set();
      variants.add(normalized);
      variants.add(normalized.replace('-', '/'));
      variants.add(normalized.replace('-', ' '));
      if (month && year) {
        const numericMonth = month.padStart(2, '0');
        variants.add(`${numericMonth}-${year}`);
        variants.add(`${numericMonth}/${year}`);
        variants.add(`${numericMonth} ${year}`);
        const monthIndex = parseInt(month, 10) - 1;
        if (!Number.isNaN(monthIndex) && MONTH_NAME_LOOKUP[monthIndex]) {
          const shortName = MONTH_NAME_LOOKUP[monthIndex];
          variants.add(`${shortName} ${year}`);
          variants.add(`${shortName.toUpperCase()} ${year}`);
          variants.add(`${year} ${shortName}`);
        }
      }
      return Array.from(variants).filter(Boolean);
    }

    function parseDateValue(value) {
      if (!value) return null;
      const parsed = new Date(value);
      if (!Number.isNaN(parsed.getTime())) return parsed;
      const isoMatch = value.match(/(\d{4})[-/](\d{1,2})[-/](\d{1,2})/);
      if (isoMatch) {
        const normalized = `${isoMatch[1]}-${isoMatch[2].padStart(2, '0')}-${isoMatch[3].padStart(2, '0')}`;
        const iso = new Date(normalized);
        if (!Number.isNaN(iso.getTime())) return iso;
      }
      return null;
    }

    function extractDateFromText(text = '') {
      if (!text) return '';
      const isoMatch = text.match(/(\d{4}[-/]\d{1,2}[-/]\d{1,2})/);
      if (isoMatch) return isoMatch[1];
      const altMatch = text.match(/(\d{1,2}\s+[A-Za-z]{3,9}\s+\d{4})/);
      return altMatch ? altMatch[1] : '';
    }
    
    // Helper: filter nested rows by specific month
    function filterNestedByMonth(nestedContainer, targetMonth) {
      if (!nestedContainer || !targetMonth) return 0;
      const nestedDataContainer = nestedContainer.querySelector('[id^="nested-data-"]') ||
        nestedContainer.querySelector('.nested-table-container') ||
        nestedContainer;
      if (!nestedDataContainer) {
        return 0;
      }
      const rows = nestedDataContainer.querySelectorAll('.compact-row');
      const variants = buildMonthVariants(targetMonth);
      const altTarget = targetMonth.replace('/', '-');
      buildMonthVariants(altTarget).forEach(v => variants.push(v));
      const uniqueVariants = Array.from(new Set(variants.map(v => v.toLowerCase())));
      let matchCount = 0;
      rows.forEach(row => {
        const rowText = (row.textContent || '').toLowerCase();
        const dataSet = row.dataset || {};
        const rowMonthAttr = (dataSet.bookingMonth || dataSet.month || dataSet.monthValue || '').toLowerCase();
        const rowDateAttr = (dataSet.bookingDate || dataSet.date || '').toLowerCase();
        const extractedDate = extractDateFromText(row.textContent || '').toLowerCase();
        const matches = uniqueVariants.some(variant =>
          rowText.includes(variant) ||
          rowMonthAttr.includes(variant) ||
          rowDateAttr.includes(variant) ||
          extractedDate.includes(variant)
        );
        if (matches) {
          row.style.display = '';
          row.dataset.filterMatch = 'true';
          matchCount++;
        } else {
          row.style.display = 'none';
          row.dataset.filterMatch = 'false';
        }
      });
      const matchState = matchCount > 0 ? 'true' : 'false';
      nestedContainer.dataset.filterMatch = matchState;
      nestedDataContainer.dataset.filterMatch = matchState;
      if (matchCount === 0) {
        nestedContainer.style.display = 'none';
      }
      return matchCount;
    }
    
    // Helper: filter nested rows by multiple filters (builder, project, customer, etc.)
    function filterNestedByFilters(nestedContainer, filters) {
      const nestedDataContainer = nestedContainer.querySelector('[id^="nested-data-"]') ||
        nestedContainer.querySelector('.nested-table-container') ||
        nestedContainer;
      if (!nestedDataContainer) {
        return 0;
      }
      
      const rows = nestedDataContainer.querySelectorAll('.compact-row');
      const normalizedFilters = Object.entries(filters || {}).filter(([key, value]) => {
        if (!value || typeof value !== 'string' || value.trim() === '') return false;
        return key !== 'bookingDateStart' && key !== 'bookingDateEnd' && key !== 'month';
      }).map(([key, value]) => [key, value.toLowerCase()]);
      const startDate = filters && filters.bookingDateStart ? parseDateValue(filters.bookingDateStart) : null;
      const endDate = filters && filters.bookingDateEnd ? parseDateValue(filters.bookingDateEnd) : null;
      let matchCount = 0;
      rows.forEach(row => {
        const rowText = (row.textContent || '').toLowerCase();
        const originalText = row.textContent || '';
        const dataSet = row.dataset || {};
        const rowDateCandidate = dataSet.bookingDate || dataSet.date || extractDateFromText(originalText);
        const rowDate = parseDateValue(rowDateCandidate);
        let matches = true;
        if (startDate && (!rowDate || rowDate < startDate)) {
          matches = false;
        }
        if (matches && endDate && (!rowDate || rowDate > endDate)) {
          matches = false;
        }
        if (matches) {
          for (const [, value] of normalizedFilters) {
            if (!rowText.includes(value)) {
              matches = false;
              break;
            }
          }
        }
        if (matches) {
          row.style.display = '';
          row.dataset.filterMatch = 'true';
          matchCount++;
        } else {
          row.style.display = 'none';
          row.dataset.filterMatch = 'false';
        }
      });
      const matchState = matchCount > 0 ? 'true' : 'false';
      nestedContainer.dataset.filterMatch = matchState;
      nestedDataContainer.dataset.filterMatch = matchState;
      if (matchCount === 0) {
        nestedContainer.style.display = 'none';
      }
      return matchCount;
    }
    
    // Helper: apply filters to nested tables directly
    function applyFiltersToNestedTables(iframeDoc, filters) {
      const nestedContainers = iframeDoc.querySelectorAll('[id^="nested-data-"]');
      
      let totalMatches = 0;
      nestedContainers.forEach(container => {
        const rows = container.querySelectorAll('.compact-row');
        
        rows.forEach(row => {
          const rowText = row.textContent.toLowerCase();
          let matches = true;
          
          // Check each filter
          Object.entries(filters).forEach(([key, value]) => {
            if (value && !rowText.includes(value.toLowerCase())) {
              matches = false;
            }
          });
          
          if (matches) totalMatches++;
          row.style.display = matches ? '' : 'none';
          row.dataset.filterMatch = matches ? 'true' : 'false';
        });
      });
    }
    
    // Apply Filters function - collect filters from parent modal and apply to iframe
    // Named applyPropertyFilters to avoid conflict with main.js applyFilters
    window.applyPropertyFilters = function() {
      const iframeWindow = getIframeWindow();
      const iframeDoc = getIframeDocument();
      
      if (!iframeWindow || !iframeDoc) {
        alert('Error: Could not find bookings data. Please refresh the page.');
        return;
      }
      
      // Don't call iframe's applyFilters directly - it reads from iframe document
      // Instead, collect filters from parent document (modal) and set them in iframe
      try {
        // Collect filter values from the modal in parent
        const activeFilters = {
          id: getVal('filterID'),
          bookingDateStart: getVal('filterBookingDateStart', false),
          bookingDateEnd: getVal('filterBookingDateEnd', false),
          month: getVal('filterMonth', false),
          builder: getVal('filterBuilder'),
          project: getVal('filterProject'),
          customerName: getVal(['filterCustumername','filterCustomername','filterCustomerName']),
          contactNumber: getVal(['filterContactnumber','filterContactNumber']),
          email: getVal('filterEmail'),
          type: getVal('filterType'),
          unit: getVal('filterUnit'),
          size: getVal('filterSize'),
          agreement: getVal('filterAgreement'),
          commission: getVal('filterCommission') || '',
          revenue: getVal(['filterTrevenue','filterRevenue']) || '',
          cashback: getVal(['filterCashBack','filterCashback']),
          actualRevenue: getVal('filterActualRevenue') || '',
          status: getVal('filterStatus'),
          received: getVal('filterReceived') || ''
        };
        
        // Remove empty filters and normalize
        Object.keys(activeFilters).forEach(key => {
          let v = activeFilters[key];
          
          // Handle arrays (from multi-select like builder, project, customerName, type, status)
          if (Array.isArray(v)) {
            // Remove empty values from array
            v = v.filter(item => item !== '' && item != null);
            if (v.length === 0) {
              delete activeFilters[key];
              return;
            }
            // Array elements are already lowercased by getVal if needed
            activeFilters[key] = v;
            return;
          }
          
          // Handle single string values
          if (typeof v === 'string') v = v.trim();
          if (v === '' || v == null) {
            delete activeFilters[key];
            return;
          }
          if (typeof v === 'string') activeFilters[key] = v.toLowerCase();
        });
        
        // Use the iframe's applyExternalFilters function which accepts filter parameters
        if (typeof iframeWindow.applyExternalFilters === 'function') {
          try {
            iframeWindow.applyExternalFilters(activeFilters);
          } catch (error) {
            alert('Error applying filters: ' + error.message);
          }
        } else if (typeof iframeWindow.applyFilters === 'function') {
          // Fallback to applyFilters (without parameters)
          try {
            iframeWindow.applyFilters();
          } catch (error) {
            alert('Error applying filters: ' + error.message);
          }
        } else {
          alert('Error: Filter functionality not available. Please refresh the page.');
        }
        
        closeFilterModal();
      } catch (error) {
        alert('Error applying filters: ' + error.message);
      }
    };
    
    // Clear Filters function - calls iframe's clearFilters
    // Named clearPropertyFilters to avoid conflict with main.js
    window.clearPropertyFilters = function() {
      const iframeWindow = getIframeWindow();
      const iframeDoc = getIframeDocument();
      
      if (!iframeWindow || !iframeDoc) {
        return;
      }
      
      // Clear the filter form fields in parent modal first
      const filterIds = [
        'filterBookingDateStart', 'filterBookingDateEnd', 'filterMonth',
        'filterBuilder', 'filterProject', 'filterType', 'filterUnit',
        'filterSize', 'filterCustumername', 'filterContactnumber',
        'filterEmail', 'filterStatus', 'filterID', 'filterAgreement',
        'filterCommission', 'filterTrevenue', 'filterRevenue', 'filterCashBack',
        'filterCashback', 'filterActualRevenue', 'filterReceived'
      ];
      filterIds.forEach(id => {
        const field = document.getElementById(id);
        if (field) field.value = '';
      });
      
      // Call the iframe's clearFilters function
      if (typeof iframeWindow.clearFilters === 'function') {
        iframeWindow.clearFilters();
      } else {
        // Fallback: manually restore view
        if (typeof iframeWindow.restoreOriginalView === 'function') {
          iframeWindow.restoreOriginalView();
        }
      }
    };
    
    // Close modals when clicking outside (matching userlogin6)
    document.addEventListener('DOMContentLoaded', function() {
      // Filter modal
      const filterModal = document.getElementById('filterModalOverlay');
      if (filterModal) {
        filterModal.addEventListener('click', function(e) {
          if (e.target === filterModal) {
            closeFilterModal();
          }
        });
      }
      
      // Add booking modal
      const addBookingModal = document.getElementById('addBookingModalOverlay');
      if (addBookingModal) {
        addBookingModal.addEventListener('click', function(e) {
          if (e.target === addBookingModal) {
            closeAddBookingModal();
          }
        });
      }
      
      // Calculator modal
      const calculatorModal = document.getElementById('calculatorModalOverlay');
      if (calculatorModal) {
        calculatorModal.addEventListener('click', function(e) {
          if (e.target === calculatorModal) {
            closeCalculatorModal();
          }
        });
      }
      
      // Close on escape key
      document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
          if (filterModal && filterModal.style.display !== 'none') {
            closeFilterModal();
          }
          if (addBookingModal && addBookingModal.style.display !== 'none') {
            closeAddBookingModal();
          }
          if (calculatorModal && calculatorModal.style.display !== 'none') {
            closeCalculatorModal();
          }
        }
      });
    });
  </script>
  
<!-- Filter Modal (from userlogin6) -->
<div class="modal-overlay" id="filterModalOverlay" style="display: none;">
    <div class="modal-container-eoi">
        <div class="modal-header">
            <h3>FILTER DATA</h3>
            <button type="button" class="modal-close-btn" onclick="closeFilterModal()">&times;</button>
        </div>

        <div class="modal-body">
            <div class="container">
                <div class="row">
                    <!-- Filter inputs with form-item structure -->
                    <div class="col-md-6 mb-2">
                        <div class="form-item">
                            <label for="filterBookingDateStart">Booking Date Start</label>
                            <input type="date" class="form-control form-control-lg" id="filterBookingDateStart">
                        </div>
                    </div>
                    <div class="col-md-6 mb-2">
                        <div class="form-item">
                            <label for="filterBookingDateEnd">Booking Date End</label>
                            <input type="date" class="form-control form-control-lg" id="filterBookingDateEnd">
                        </div>
                    </div>
                    <div class="col-md-6 mb-2">
                        <div class="form-item">
                            <label for="filterID">ID</label>
                            <select id="filterID" class="form-select filter-select" multiple>
                                <?php
                                try {
                                    $config_filter = new Config();
                                    $stmt = $config_filter->getConnection()->prepare("SELECT DISTINCT id FROM admintable WHERE id IS NOT NULL ORDER BY id DESC LIMIT 200");
                                    $stmt->execute();
                                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        echo '<option value="' . htmlspecialchars($row['id']) . '">' . htmlspecialchars($row['id']) . '</option>';
                                    }
                                } catch (Exception $e) {}
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6 mb-2">
                        <div class="form-item">
                            <label for="filterMonth">Month</label>
                            <input type="text" class="form-control form-control-lg" id="filterMonth">
                        </div>
                    </div>
                    <div class="col-md-6 mb-2">
                        <div class="form-item">
                            <label for="filterBuilder">Builder Name</label>
                            <select id="filterBuilder" class="form-select filter-select" multiple>
                                <?php
                                try {
                                    $config_filter = new Config();
                                    $stmt = $config_filter->getConnection()->prepare("SELECT DISTINCT builder FROM admintable WHERE builder IS NOT NULL AND builder != '' ORDER BY builder ASC LIMIT 200");
                                    $stmt->execute();
                                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        echo '<option value="' . htmlspecialchars($row['builder']) . '">' . htmlspecialchars($row['builder']) . '</option>';
                                    }
                                } catch (Exception $e) {}
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6 mb-2">
                        <div class="form-item">
                            <label for="filterProject">Project Name</label>
                            <select id="filterProject" class="form-select filter-select" multiple>
                                <?php
                                try {
                                    $config_filter = new Config();
                                    $stmt = $config_filter->getConnection()->prepare("SELECT DISTINCT project FROM admintable WHERE project IS NOT NULL AND project != '' ORDER BY project ASC LIMIT 200");
                                    $stmt->execute();
                                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        echo '<option value="' . htmlspecialchars($row['project']) . '">' . htmlspecialchars($row['project']) . '</option>';
                                    }
                                } catch (Exception $e) {}
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6 mb-2">
                        <div class="form-item">
                            <label for="filterCustumername">Customer Name</label>
                            <select id="filterCustumername" class="form-select filter-select" multiple>
                                <?php
                                try {
                                    $config_filter = new Config();
                                    $stmt = $config_filter->getConnection()->prepare("SELECT DISTINCT customer_name FROM admintable WHERE customer_name IS NOT NULL AND customer_name != '' ORDER BY customer_name ASC LIMIT 200");
                                    $stmt->execute();
                                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        echo '<option value="' . htmlspecialchars($row['customer_name']) . '">' . htmlspecialchars($row['customer_name']) . '</option>';
                                    }
                                } catch (Exception $e) {}
                                ?>
                            </select>
                        </div>
                    </div>
                   <div class="col-md-6 mb-2">
                        <div class="form-item">
                            <label for="filterContactnumber">Contact No.</label>
                            <select id="filterContactnumber" class="form-select filter-select" multiple>
                                <?php
                                try {
                                    $config_filter = new Config();
                                    $stmt = $config_filter->getConnection()->prepare("SELECT DISTINCT contact_number FROM admintable WHERE contact_number IS NOT NULL AND contact_number != '' ORDER BY contact_number ASC LIMIT 200");
                                    $stmt->execute();
                                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        echo '<option value="' . htmlspecialchars($row['contact_number']) . '">' . htmlspecialchars($row['contact_number']) . '</option>';
                                    }
                                } catch (Exception $e) {}
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6 mb-2">
                        <div class="form-item">
                            <label for="filterEmail">Email Id</label>
                            <select id="filterEmail" class="form-select filter-select" multiple>
                                <?php
                                try {
                                    $config_filter = new Config();
                                    $stmt = $config_filter->getConnection()->prepare("SELECT DISTINCT email_id FROM admintable WHERE email_id IS NOT NULL AND email_id != '' ORDER BY email_id ASC LIMIT 200");
                                    $stmt->execute();
                                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        echo '<option value="' . htmlspecialchars($row['email_id']) . '">' . htmlspecialchars($row['email_id']) . '</option>';
                                    }
                                } catch (Exception $e) {}
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6 mb-2">
                        <div class="form-item">
                            <label for="filterType">Unit Type</label>
                            <select id="filterType" class="form-select filter-select" multiple>
                                <?php
                                try {
                                    $config_filter = new Config();
                                    $stmt = $config_filter->getConnection()->prepare("SELECT DISTINCT project_type FROM admintable WHERE project_type IS NOT NULL AND project_type != '' ORDER BY project_type ASC LIMIT 200");
                                    $stmt->execute();
                                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        echo '<option value="' . htmlspecialchars($row['project_type']) . '">' . htmlspecialchars($row['project_type']) . '</option>';
                                    }
                                } catch (Exception $e) {}
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6 mb-2">
                        <div class="form-item">
                            <label for="filterUnit">Unit No.</label>
                            <select id="filterUnit" class="form-select filter-select" multiple>
                                <?php
                                try {
                                    $config_filter = new Config();
                                    $stmt = $config_filter->getConnection()->prepare("SELECT DISTINCT unit_no FROM admintable WHERE unit_no IS NOT NULL AND unit_no != '' ORDER BY unit_no ASC LIMIT 200");
                                    $stmt->execute();
                                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        echo '<option value="' . htmlspecialchars($row['unit_no']) . '">' . htmlspecialchars($row['unit_no']) . '</option>';
                                    }
                                } catch (Exception $e) {}
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6 mb-2">
                        <div class="form-item">
                            <label for="filterStatus">Status</label>
                            <select id="filterStatus" class="form-select filter-select" multiple>
                                <?php
                                try {
                                    $config_filter = new Config();
                                    $stmt = $config_filter->getConnection()->prepare("SELECT DISTINCT astatus FROM admintable WHERE astatus IS NOT NULL AND astatus != '' ORDER BY astatus ASC LIMIT 200");
                                    $stmt->execute();
                                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        echo '<option value="' . htmlspecialchars($row['astatus']) . '">' . htmlspecialchars($row['astatus']) . '</option>';
                                    }
                                } catch (Exception $e) {}
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6 mb-2">
                        <div class="form-item">
                            <label for="filterSales">Sales person</label>
                            <select id="filterSales" class="form-select filter-select" multiple>
                                <?php
                                try {
                                    $config_filter = new Config();
                                    $stmt = $config_filter->getConnection()->prepare("SELECT DISTINCT source_table FROM admintable WHERE source_table IS NOT NULL AND source_table != '' ORDER BY source_table ASC LIMIT 200");
                                    $stmt->execute();
                                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        echo '<option value="' . htmlspecialchars($row['source_table']) . '">' . htmlspecialchars($row['source_table']) . '</option>';
                                    }
                                } catch (Exception $e) {}
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6 mb-2">
                        <div class="form-item">
                            <label for="filterSize">Unit Size</label>
                            <input type="text" class="form-control form-control-lg" id="filterSize">
                        </div>
                    </div>
                    <div class="col-md-6 mb-2">
                        <div class="form-item">
                            <label for="filterAgreement">Agreement Value</label>
                            <input type="text" class="form-control form-control-lg" id="filterAgreement">
                        </div>
                    </div>
                    <div class="col-md-6 mb-2">
                        <div class="form-item">
                            <label for="filterCommission">Commission %</label>
                            <input type="text" class="form-control form-control-lg" id="filterCommission">
                        </div>
                    </div>
                    <div class="col-md-6 mb-2">
                        <div class="form-item">
                            <label for="filterTrevenue">Total Revenue</label>
                            <input type="text" class="form-control form-control-lg" id="filterTrevenue">
                        </div>
                    </div>
                    <div class="col-md-6 mb-2">
                        <div class="form-item">
                            <label for="filterCashBack">CashBack %</label>
                            <input type="text" class="form-control form-control-lg" id="filterCashBack">
                        </div>
                    </div>
                    <div class="col-md-6 mb-2">
                        <div class="form-item">
                            <label for="filterActualRevenue">Actual Revenue</label>
                            <input type="text" class="form-control form-control-lg" id="filterActualRevenue">
                        </div>
                    </div>
                    <div class="col-md-6 mb-2">
                        <div class="form-item">
                            <label for="filterReceived">Received Amt.</label>
                            <input type="text" class="form-control form-control-lg" id="filterReceived">
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer" style="margin: 0 auto; gap: 1rem; justify-content: center;">
                <!-- Close Modal button -->
                <button type="button" class="btn btn-secondary" onclick="closeFilterModal()" id="cancleFilter">Close</button>
                <!-- Clear Filters button -->
                <button type="button" class="btn btn-danger" onclick="clearAllFilters()" id="clearFiltersBtn">Clear Filters</button>
                <!-- Apply Filters button -->
                <button type="button" class="btn btn-primary" onclick="applyFiltersToIframe()" id="applyFiltersBtn">Apply Filters</button>
            </div>
        </div>
    </div>
</div>

<!-- Floating Clear Filters Button -->
<button id="floatingClearFiltersBtn" class="floating-clear-filters-btn" onclick="clearAllFilters()" style="display: none;">
  <i class="bi bi-x-circle"></i> Clear Filters
</button>

<!-- Custom Input Modal for Add Fields -->
<div class="modal-overlay" id="customInputModalOverlay" style="display: none;">
    <div class="modal-container-eoi" style="max-width: 450px;">
        <div class="modal-header">
            <h3 id="customInputModalTitle">Enter Value</h3>
            <button type="button" class="modal-close-btn" onclick="closeCustomInputModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-item">
                <label for="customInputModalField" id="customInputModalLabel">Enter new value:</label>
                <input class="form-control form-control-lg" id="customInputModalField" type="text" placeholder="Start typing..." autocomplete="off">
            </div>
        </div>
        <div class="modal-footer" style="margin: 0 auto; gap: 1rem; justify-content: center; border-top: 1px solid #e5e7eb; padding-top: 1rem;">
            <button type="button" class="btn btn-secondary" onclick="closeCustomInputModal()" style="padding: 10px 30px; border-radius: 8px; font-weight: 500;">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="submitCustomInputModal()" style="padding: 10px 30px; border-radius: 8px; font-weight: 500; background: #3b82f6;">OK</button>
        </div>
    </div>
</div>

<!-- Add Booking Modal (from userlogin6) -->
<div class="modal-overlay" id="addBookingModalOverlay" style="display: none;">
    <div class="modal-container-eoi add-booking-modal">
        <div class="modal-header">
            <h3>Add New Booking</h3>
            <button type="button" class="modal-close-btn" onclick="closeAddBookingModal()">&times;</button>
        </div>
        <div class="modal-body">

            <form id="add-booking-form" name="myform" novalidate>
                <div class="container">
                    <div class="row">
                        <!-- Row 1: Booking Date and Booking Month -->
                        <div class="col-6 col-md-6 col-lg-4 mb-2">
                            <div class="form-item">
                                <label for="bookingDate">Booking Date</label>
                                <input type="date" class="form-control form-control-lg" id="bookingDate" name="bdate" required>
                            </div>
                        </div>
                        <div class="col-6 col-md-6 col-lg-4 mb-2">
                            <div class="form-item">
                                <label for="bookingMonth">Booking Month</label>
                                <input type="month" class="form-control form-control-lg" id="bookingMonth" name="bmonth" placeholder="Auto-filled from date" readonly>
                            </div>
                        </div>

                        <!-- Row 2: Builder Name, Project Name, Project Type -->
                        <div class="col-6 col-md-6 col-lg-4 mb-2">
                            <div class="form-item">
                                <button class="add-btn" data-add="builder" type="button" style="position: absolute; right: 10px; top: -10px; z-index: 3; background: dimgrey; color: white; border: none; border-radius: 4px; font-size: 11px; cursor: pointer;">+</button>
                                <label for="builderName">Builder Name</label>
                                <input list="builderList" class="form-control form-control-lg" id="builderName" name="developer" placeholder="Start typing..." required>
                                <datalist id="builderList">
                                    <option value="Prestige Group">
                                    <option value="Brigade Group">
                                    <option value="Sobha Limited">
                                    <option value="Godrej Properties">
                                    <option value="Puravankara Limited">
                                    <option value="Shriram Properties">
                                    <option value="Sattva Group">
                                    <option value="Salarpuria Sattva">
                                    <option value="Assetz Property Group">
                                    <option value="Embassy Group">
                                    <option value="L&T Realty">
                                    <option value="Mahaveer Group">
                                    <option value="Adarsh Developers">
                                    <option value="Mahindra Lifespaces">
                                    <option value="Neeladri Properties">
                                    <option value="Ranav Group">
                                    <option value="Amber Meadows">
                                    <option value="Ramky Group">
                                    <option value="Arvind Smart Spaces">
                                    <option value="Goyal & Co.">
                                </datalist>
                            </div>
                        </div>
                        <div class="col-6 col-md-6 col-lg-4 mb-2">
                            <div class="form-item">
                                <button class="add-btn" data-add="project" type="button" style="position: absolute; right: 10px; top: -10px; z-index: 3; background: dimgrey; color: white; border: none; border-radius: 4px; font-size: 11px; cursor: pointer;">+</button>
                                <label for="projectName">Project Name</label>
                                <input list="projectList" class="form-control form-control-lg" id="projectName" name="bproject" placeholder="Start typing..." required>
                                <datalist id="projectList">
                                    <option value="Prestige Lakeside Habitat">
                                    <option value="Brigade Utopia">
                                    <option value="Sobha Dream Acres">
                                    <option value="Godrej Air Nxt">
                                    <option value="Purva Zenium">
                                    <option value="Shriram Blue">
                                    <option value="Salarpuria Sattva Divinity">
                                    <option value="Assetz 63° East">
                                    <option value="Embassy Lake Terraces">
                                    <option value="L&T Raintree Boulevard">
                                    <option value="Mahaveer Ranches">
                                    <option value="Adarsh Palm Retreat">
                                    <option value="Mahindra Eden">
                                    <option value="Neeladri Sarovaram">
                                    <option value="Ramky One Karnival">
                                    <option value="Arvind Bel Air">
                                    <option value="Goyal Orchid Whitefield">
                                </datalist>
                            </div>
                        </div>
                        <div class="col-6 col-md-6 col-lg-4 mb-2">
                            <div class="form-item">
                                <button class="add-btn" data-add="ptype" type="button" style="position: absolute; right: 10px; top: -10px; z-index: 3; background: dimgrey; color: white; border: none; border-radius: 4px; font-size: 11px; cursor: pointer;">+</button>
                                <label for="projectType">Project Type</label>
                                <input list="ptypeList" class="form-control form-control-lg" id="projectType" name="tproject" placeholder="Select or type" required>
                                <datalist id="ptypeList">
                                    <option value="1-BHK">
                                    <option value="2-BHK">
                                    <option value="3-BHK">
                                    <option value="4-BHK">
                                    <option value="5-BHK">
                                    <option value="Villa">
                                    <option value="Plot">
                                </datalist>
                            </div>
                        </div>
                        
                        <!-- Row 3: Project Size, Customer Name, Email -->
                        <div class="col-6 col-md-6 col-lg-4 mb-2">
                            <div class="form-item">
                                <label for="projectSize">Project Size</label>
                                <input class="form-control form-control-lg" id="projectSize" name="psize" type="text" placeholder="e.g. 1200 sq.ft" inputmode="decimal" required>
                            </div>
                        </div>

                        <div class="col-6 col-md-6 col-lg-4 mb-2">
                            <div class="form-item">
                                <button class="add-btn" data-add="customer" type="button" style="position: absolute; right: 10px; top: -10px; z-index: 3000; background: dimgrey; color: white; border: none; border-radius: 4px; font-size: 11px; cursor: pointer;">+</button>
                                <label for="customerName">Customer Name</label>
                                <input class="form-control form-control-lg" id="customerName" name="cname" placeholder="Full name" required>
                            </div>
                        </div>
                        <div class="col-6 col-md-6 col-lg-4 mb-2">
                            <div class="form-item">
                                <button class="add-btn" data-add="email" type="button" style="position: absolute; right: 10px; top: -10px; z-index: 3; background: dimgrey; color: white; border: none; border-radius: 4px; font-size: 11px; cursor: pointer;">+</button>
                                <label for="email">Email</label>
                                <input class="form-control form-control-lg" id="email" name="cemail" type="email" placeholder="name@example.com" required>
                            </div>
                        </div>
                        
                        <!-- Row 4: Contact No, Unit No, Agreement Value -->
                        <div class="col-6 col-md-6 col-lg-4 mb-2">
                            <div class="form-item">
                                <button class="add-btn" data-add="contact" type="button" style="position: absolute; right: 10px; top: -10px; z-index: 3; background: dimgrey; color: white; border: none; border-radius: 4px; font-size: 11px; cursor: pointer;">+</button>
                                <label for="contactNo">Contact No.</label>
                                <input class="form-control form-control-lg" id="contactNo" name="cnumber" type="tel" placeholder="+91 XXXXX XXXXX" maxlength="10" pattern="[0-9]{10}" title="Please enter exactly 10 digits" required>
                            </div>
                        </div>
                        <?php
                        // Get first 2 characters of nameuser (capitalize first letter of each word if needed)
                        $unit_prefix = substr($nameuser, 0, 2);
                        $unit_prefix = ucfirst(strtolower($unit_prefix)) . '-';
                        ?>
                        <div class="col-6 col-md-6 col-lg-4 mb-2">
                            <div class="form-item">
                                <label for="unitNo">Unit No.</label>
                                <input class="form-control form-control-lg" id="unitNo" name="unitno" placeholder="e.g. A-1204" required value="<?php echo $unit_prefix ?? 'Un-'; ?>" oninput="prefixCheck(this)">
                            </div>
                        </div>
                        
                        <!-- Row 5: Agreement Value, Commission %, Revenue Amount -->

                        <div class="col-6 col-md-6 col-lg-4 mb-2">
                            <div class="form-item">
                                <label for="agreementValue">Agreement Value</label>
                                <input class="form-control form-control-lg" id="agreementValue" name="cagreement" type="number" min="0" step="0.01" placeholder="₹ 0.00" required>
                            </div>
                        </div>
                        <div class="col-6 col-md-6 col-lg-4 mb-2">
                            <div class="form-item">
                                <label for="commissionPct">Commission %</label>
                                <input class="form-control form-control-lg" id="commissionPct" name="ccashback" type="number" min="0" max="100" step="0.01" placeholder="% 0" required onkeyup="addCalculate(this.value)">
                            </div>
                        </div>
                        <div class="col-6 col-md-6 col-lg-4 mb-2">
                            <div class="form-item">
                                <label for="revenueAmount">Revenue Amount</label>
                                <input class="form-control form-control-lg" id="revenueAmount" name="crevenue" type="number" placeholder="₹ Auto" readonly required>
                            </div>
                        </div>
                        
                        <!-- Row 6: Cashback %, Actual Amount, Lead Source -->
                        <div class="col-6 col-md-6 col-lg-4 mb-2">
                            <div class="form-item">
                                <label for="cashbackPct">Cashback %</label>
                                <input class="form-control form-control-lg" id="cashbackPct" name="cccashback" type="number" min="0" max="100" step="0.01" placeholder="% 0" required onkeyup="addCalculate(this.value); calculateCashbackRevenue();">
                            </div>
                        </div>
                        <div class="col-6 col-md-6 col-lg-4 mb-2">
                            <div class="form-item">
                                <label for="actualAmount">Actual Amount</label>
                                <input class="form-control form-control-lg" id="actualAmount" name="ccrevenue" type="number" placeholder="₹ Auto" readonly required>
                            </div>
                        </div>
                        <div class="col-6 col-md-6 col-lg-4 mb-2">
                            <div class="form-item">
                                <label for="leadSource">Lead Source</label>
                                <select class="form-control form-control-lg" id="leadSource" name="leadsource" required>
                                    <option value="">Select Source</option>
                                    <option value="Google">Google</option>
                                    <option value="Facebook">Facebook</option>
                                    <option value="Direct">Direct</option>
                                    <option value="Referral">Referral</option>
                                    <option value="Portal">Portal</option>
                                    <option value="WhatsApp">WhatsApp</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-12 col-md-12 col-lg-8 mb-2">
                            <div class="form-item">
                                <label for="remarks">Remarks</label>
                                <textarea class="form-control form-control-lg" id="remarks" name="bremarks" placeholder="Additional notes..." rows="2" style="height: 38px; min-height: 38px; resize: vertical;"></textarea>
                            </div>
                        </div>
                        <div class="col-12 mb-2">
                            <div class="form-item">
                                <label for="fileInput">Attachments</label>
                                <input type="file" class="form-control form-control-lg" id="fileInput" name="document" accept=".png,.jpg,.jpeg,.heic,.webp,.pdf,.doc,.docx" style="padding: 8px 12px !important;">
                                <small style="display: block; margin-top: 4px; color: #6b7280; font-size: 11px;">PDF, JPG, PNG. Max 1 file</small>
                            </div>
                        </div>
                        
                        <!-- Status (full width) -->
                        <div class="col-12 mb-2">
                            <div class="form-item">
                                <label for="bookingStatus" style="position: relative; background: transparent; padding: 0; top: 0; left: 0; color: #1e293b; font-size: 14px; font-weight: 600; display: block; margin-bottom: 8px;">Status</label>
                                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                                    <button type="button" class="status-btn status-btn-active" data-status="Processing" onclick="selectStatus(this, 'Processing')" style="padding: 8px 20px; border-radius: 20px; font-size: 14px; font-weight: 500; cursor: pointer; background: #1b6c9f; color: white; border: 1px solid #1b6c9f; transition: all 0.3s;">Processing</button>
                                    <button type="button" class="status-btn" data-status="Received" onclick="selectStatus(this, 'Received')" style="padding: 8px 20px; border-radius: 20px; font-size: 14px; font-weight: 500; cursor: pointer; background: white; color: #6b7280; border: 1px solid #d1d5db; transition: all 0.3s;">Received</button>
                                    <button type="button" class="status-btn" data-status="Canceled" onclick="selectStatus(this, 'Canceled')" style="padding: 8px 20px; border-radius: 20px; font-size: 14px; font-weight: 500; cursor: pointer; background: white; color: #dc2626; border: 1px solid #dc2626; transition: all 0.3s;">Canceled</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <input type="hidden" name="cstatus" value="Processing" />
                <input type="hidden" name="brecived" value="0" />

                <div class="alert alert-warning py-1 px-2 small mb-2" role="alert" style="font-size: 13px;">
                    <i class="bi bi-info-circle-fill me-1">ℹ</i> Please ensure all details are accurate before
                    submitting. Incorrect data may affect incentives.
                </div>

                <div class="modal-footer" style="margin: 0 auto; gap: 1rem; border-top: 1px solid #e5e7eb; padding-top: 1rem;">
                    <button type="button" class="btn btn-secondary" onclick="closeAddBookingModal()">
                        Close
                    </button>
                    <button type="submit" class="btn btn-primary" id="saveBtn">
                        Add Booking
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- Edit User Modal Start -->
    <div id="editUserModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 10050; align-items: center; justify-content: center; flex-direction: column; overflow: auto; backdrop-filter: blur(6px) saturate(1.2) !important;background: rgb(9 9 9 / 41%);">
        <div class="modal-dialog modal-dialog-centered" style="position: relative; margin: auto; max-width: 600px; width: 90%; height: 80%; overflow: hidden;">
            <div class="modal-content" style="max-height: 80%; display: flex; flex-direction: column; overflow: hidden;">
                <div class="modal-header" style="display: flex; align-items: center; justify-content: space-between; padding: 1rem; border-bottom: 1px solid #dee2e6;">
                    <h5 class="modal-title">Edit This Booking</h5>
                    <button type="button" class="btn-close" aria-label="Close" onclick="document.getElementById('editUserModal').style.display='none';" style="opacity: 1; visibility: visible; display: block; cursor: pointer; background: transparent url('data:image/svg+xml,%3csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 16 16\' fill=\'%23000\'%3e%3cpath d=\'M.293.293a1 1 0 011.414 0L8 6.586 14.293.293a1 1 0 111.414 1.414L9.414 8l6.293 6.293a1 1 0 01-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 01-1.414-1.414L6.586 8 .293 1.707a1 1 0 010-1.414z\'/%3e%3c/svg%3e') center/1em auto no-repeat; border: 0; border-radius: 0.375rem; width: 1em; height: 1em; padding: 0.5em; margin: 0;"></button>
                </div>
                <div class="modal-body" style="overflow-y: auto; overflow-x: hidden; max-height: calc(90vh - 120px); flex: 1 1 auto; padding: 1rem;">
                    <form id="edit-user-form" name="myform" class="p-2" novalidate="">
                        <input type="hidden" name="id" id="id">
                        <div class="container">
                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <div class="form-group dropdown-container">
                                        <label for="unique_searchInput" class="form-label">Assigned User <b id="selected_user_label"></b></label>
                                        <input type="text" id="unique_searchInput" class="form-control" placeholder="Search..." autocomplete="off">
                                        <ul id="unique_source_table" class="dropdown-options">
                                            <?php
                                                // Fetch users from the database
                                                $userQuery = "SELECT tablename FROM accounts"; // Assuming a users table
                                                $userStmt = $conn->prepare($userQuery);
                                                $userStmt->execute();
                                                $users = $userStmt->fetchAll(PDO::FETCH_ASSOC);
                                                
                                                foreach ($users as $user) {
                                                    echo "<li class='unique-option' data-value='{$user['tablename']}'>{$user['tablename']}</li>";
                                                }
                                            ?>
                                        </ul>
                                        <input type="hidden" id="source_table" name="source_table">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-2">
                                    <div class="form-item">
                                        <label for="bdate">Booking date</label>
                                        <input type="date" name="bdate" id="bdate" class="form-control form-control-lg"
                                            required>
                                        <div class="invalid-feedback">Date is required!</div>
                                    </div>
                                </div>

                                <div class="col-md-6 mb-2">
                                    <div class="form-item">
                                        <label for="bmonth">Booking month</label>
                                        <input type="month" name="bmonth" id="bmonth"
                                            class="form-control form-control-lg" required>
                                        <div class="invalid-feedback">Month is required!</div>
                                    </div>
                                </div>

                                <div class="col-md-6 mb-2">
                                    <div class="form-item">
                                        <input type="text" name="developer" id="developer"
                                            class="form-control form-control-lg" required>
                                        <label for="developer">Builder Name</label>
                                        <div class="invalid-feedback">Builder name is required!</div>
                                    </div>
                                </div>

                                <div class="col-md-6 mb-2">
                                    <div class="form-item">
                                        <input type="text" name="bproject" id="bproject"
                                            class="form-control form-control-lg" required>
                                        <label for="bproject">Project name</label>
                                        <div class="invalid-feedback">Project name is required!</div>
                                    </div>
                                </div>

                                <div class="col-md-6 mb-2">
                                    <div class="form-item">
                                        <input type="username" name="cname" id="cname"
                                            class="form-control form-control-lg" required>
                                        <label for="cname">Customer name</label>
                                        <div class="invalid-feedback">Customer name is required!</div>
                                    </div>
                                </div>

                                <div class="col-md-6 mb-2">
                                    <div class="form-item">
                                        <input type="text" name="cnumber" id="cnumber"
                                            class="form-control form-control-lg" required>
                                        <label for="cnumber">Customer no.</label>
                                        <div class="invalid-feedback">Contact Number is required!</div>
                                    </div>
                                </div>

                                <div class="col-md-6 mb-2">
                                    <div class="form-item">
                                        <input type="email" name="cemail" id="cemail"
                                            class="form-control form-control-lg" required>
                                        <label for="cemail">E-mail</label>
                                        <div class="invalid-feedback">E-mail is required!</div>
                                    </div>
                                </div>

                                <div class="col-md-6 mb-2">
                                    <div class="form-item">
                                        <input type="text" name="tproject" id="tproject"
                                            class="form-control form-control-lg" required>
                                        <label for="tproject">Project type</label>
                                        <div class="invalid-feedback">Project Type is required!</div>
                                    </div>
                                </div>

                                <div class="col-md-6 mb-2">
                                    <div class="form-item">
                                        <input type="text" name="unitno" id="unitno"
                                            class="form-control form-control-lg" required>
                                        <label for="unitno">Unit no</label>
                                        <div class="invalid-feedback">Unit Number is required!</div>
                                    </div>
                                </div>

                                <div class="col-md-6 mb-2">
                                    <div class="form-item">
                                        <input type="number" name="psize" id="psize"
                                            class="form-control form-control-lg" required>
                                        <label for="psize">Project size</label>
                                        <div class="invalid-feedback">Project Size is required!</div>
                                    </div>
                                </div>

                                <div class="col-md-6 mb-2">
                                    <div class="form-item">
                                        <input type="number" name="cagreement" id="cagreement"
                                            class="form-control form-control-lg" required>
                                        <label for="cagreement">Agreement value</label>
                                        <div class="invalid-feedback">Agreement Value is required!</div>
                                    </div>
                                </div>

                                <div class="col-md-6 mb-2">
                                    <div class="form-item">
                                        <input type="text" name="ccashback" id="ccashback"
                                            class="form-control form-control-lg" onkeyup="updateCalculate(this.value)"
                                            required>
                                        <label for="ccashback">Commission %</label>
                                        <div class="invalid-feedback">Commission % is required!</div>
                                    </div>
                                </div>

                                <div class="col-md-6 mb-2">
                                    <div class="form-item">
                                        <input type="number" name="crevenue" id="crevenue"
                                            class="form-control form-control-lg" required>
                                        <label for="crevenue">Total revenue</label>
                                        <div class="invalid-feedback">Total Revenue Amount is required!</div>
                                    </div>
                                </div>

                                <div class="col-md-6 mb-2">
                                    <div class="form-item">
                                        <input type="text" name="cccashback" id="cccashback"
                                            class="form-control form-control-lg" onkeyup="updateCalculate(this.value)"
                                            required>
                                        <label for="cccashback">CashBack %</label>
                                        <div class="invalid-feedback">CashBack % is required!</div>
                                    </div>
                                </div>

                                <div class="col-md-12 mb-2">
                                    <div class="form-item">
                                        <input type="number" name="ccrevenue" id="ccrevenue"
                                            class="form-control form-control-lg" required>
                                        <label for="ccrevenue">Actual Revenue</label>
                                        <div class="invalid-feedback">Actual Revenue Amount is required!</div>
                                    </div>
                                </div>

                                <div class="col-md-6 mb-2">
                                    <div class="form-item">
                                        <input type="number" name="brecived" id="brecived"
                                            class="form-control form-control-lg disable" readonly>
                                        <label for="brecived">Received Amount</label>
                                        <div class="invalid-feedback">Received Amt. is required!</div>
                                    </div>
                                </div>

                                <div class="col-md-6 mb-2">
                                    <div class="form-item">
                                        <input type="number" name="invoice_raised" id="invoice_raised"
                                            class="form-control form-control-lg">
                                        <label for="invoice_raised">Invoice Received Amt.</label>
                                    </div>
                                </div>

                                <!-- File Upload Section -->
                                <div class="col-md-12 mb-3">
                                    <label for="editFileInput" style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: #374151;">Attachments</label>
                                    <div id="currentFileDisplay" style="margin-bottom: 0.75rem; padding: 0.75rem; background: #f3f4f6; border-radius: 8px; display: none;">
                                        <div style="display: flex; align-items: center; justify-content: space-between;">
                                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                                <i class="fa fa-file" style="color: #6b7280;"></i>
                                                <span id="currentFileName" style="color: #374151; font-size: 14px;"></span>
                                            </div>
                                            <a href="#" id="currentFileDownload" target="_blank" class="btn btn-sm" download style="padding: 4px 8px; background: white; border: 1px solid #ddd; border-radius: 4px; text-decoration: none; color: #1a73e8; font-size: 12px;">
                                                <i class="fa fa-download" style="font-size: 12px;"></i> Download
                                            </a>
                                        </div>
                                    </div>
                                    <input type="file" class="form-control form-control-lg" id="editFileInput" name="document" accept=".png,.jpg,.jpeg,.heic,.webp,.pdf,.doc,.docx" style="padding: 8px 12px !important;">
                                    <small style="display: block; margin-top: 4px; color: #6b7280; font-size: 11px;">PDF, JPG, PNG. Max 1 file. Leave empty to keep existing file.</small>
                                </div>

                                <!-- Checkboxes Section - Horizontal Layout -->
                                <div class="col-lg-12 mb-3">
                                    <div style="display: flex; gap: 20px; flex-wrap: wrap; align-items: center;">
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <input type="checkbox" class="form-check-input" name="update_invoice_checkbox"
                                                id="update_invoice_checkbox" style="width: 18px; height: 18px; cursor: pointer;">
                                            <label class="form-check-label" for="update_invoice_checkbox" style="cursor: pointer; font-weight: 500; margin: 0;">Raised
                                                Invoice</label>
                                        </div>

                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <input type="checkbox" class="form-check-input" name="update_user_checkbox"
                                                id="update_user_checkbox" style="width: 18px; height: 18px; cursor: pointer;">
                                            <label class="form-check-label" for="update_user_checkbox" style="cursor: pointer; font-weight: 500; margin: 0;">Update
                                                User</label>
                                        </div>

                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <input type="checkbox" class="form-check-input" name="cashbackverify"
                                                id="cashbackverify" style="width: 18px; height: 18px; cursor: pointer;">
                                            <label class="form-check-label" for="cashbackverify" style="cursor: pointer; font-weight: 500; margin: 0;">Cashback
                                                Paid</label>
                                        </div>
                                    </div>
                                </div>

                                <!-- Status Buttons - Horizontal Pill Style -->
                                <div class="col-lg-12 mb-3">
                                    <div class="btnwraps" style="display: flex !important; gap: 15px; flex-wrap: wrap; flex-direction: row !important;">
                                        <input type="radio" class="btn-check" name="cstatus"
                                            id="btn-check-processing" value="Processing" required style="display: none;">
                                        <label class="btn btn-outline-primary bttm-btn" for="btn-check-processing"
                                            style="display: inline-flex !important; padding: 8px 24px; border-radius: 20px; font-weight: 500; border-width: 2px; margin: 0 !important;">Processing</label>

                                        <input type="radio" class="btn-check" name="cstatus"
                                            id="btn-check-received" value="Received" required style="display: none;">
                                        <label class="btn btn-outline-success bttm-btn" for="btn-check-received"
                                            style="display: inline-flex !important; padding: 8px 24px; border-radius: 20px; font-weight: 500; border-width: 2px; margin: 0 !important;">Received</label>

                                        <input type="radio" class="btn-check" name="cstatus"
                                            id="btn-check-canceled" value="Cancled" required style="display: none;">
                                        <label class="btn btn-outline-danger bttm-btn" for="btn-check-canceled"
                                            style="display: inline-flex !important; padding: 8px 24px; border-radius: 20px; font-weight: 500; border-width: 2px; margin: 0 !important;">Cancled</label>
                                        <div class="invalid-feedback">Please Select the status of booking!</div>
                                    </div>
                                </div>

                                <div class="col-lg-12">
                                    <div class="Ubsubmitbtn" style="display: flex; justify-content: end;">
                                        <input type="submit" value="Update Booking" class="btn btn-success btn-block" style="width:100%;"
                                            id="edit-user-btn" onclick="validateForm()">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
<!-- Edit User Modal End -->
<!-- Add Name Model Start -->
    <div class="modal fade" id="addOptionModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form id="optionForm">
          <div class="modal-header">
            <h5 class="modal-title">Add New Option</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" id="optionType" name="type">
            <div class="mb-3">
              <label for="newOptionValue" class="form-label">Enter new value</label>
              <input type="text" id="newOptionValue" name="value" class="form-control" required>
            </div>
          </div>
          <div class="modal-footer">
            <button type="submit" class="btn btn-primary">Save</button>
          </div>
        </form>
      </div>
    </div>
  </div>
<!-- Add Name Model End -->
<!-- Main Content -->
  <div class="content">
  <div class="contentinside">
    <!-- Embedded userlogin6 bookings_superadmin page -->
    <iframe
      id="bookings-superadmin-iframe"
      title="Superadmin Bookings"
      src="/incentiveapp_integration/userlogin1/userlogin6/bookings_superadmin.php?embed=1"
      style="width: 100%; height: 100vh; border: none; margin: 0; padding: 0; display: block;"
      loading="lazy">
    </iframe>
  </div>
  </div>
  <!--End Main Content -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script type="text/javascript" src="./assets/js/script.js"></script>
    <script type="text/javascript" src="./main.js"></script>
    <script src="./calc.js"></script>
  <script>
   document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('unique_searchInput');
        const dropdown = document.getElementById('unique_source_table');
        if (!searchInput || !dropdown) return;
        
        const hiddenInput = document.getElementById('source_table');
        const labelElement = document.getElementById('selected_user_label');

        // Use position: fixed so the dropdown is not clipped by overflow on modal ancestors
        function showDropdown() {
            const rect = searchInput.getBoundingClientRect();
            dropdown.style.position = 'fixed';
            dropdown.style.top = rect.bottom + 'px';
            dropdown.style.left = rect.left + 'px';
            dropdown.style.width = rect.width + 'px';
            dropdown.style.display = 'block';
        }

        searchInput.addEventListener('focus', (e) => {
            e.stopPropagation();
            showDropdown();
        });
        
        searchInput.addEventListener('click', (e) => {
            e.stopPropagation();
            showDropdown();
        });

        searchInput.addEventListener('keyup', function() {
            const filter = this.value.toLowerCase();
            const options = dropdown.getElementsByTagName('li');
            Array.from(options).forEach(opt => {
                const text = opt.innerText.toLowerCase();
                opt.style.display = text.includes(filter) ? '' : 'none';
            });
            // Keep dropdown positioned correctly while typing
            if (dropdown.style.display === 'block') showDropdown();
        });

        dropdown.addEventListener('click', function(event) {
            const li = event.target.closest('li');
            if (li) {
                event.stopPropagation();
                const selectedText = li.innerText;
                const selectedValue = li.dataset.value;
                
                searchInput.value = selectedText;
                if (hiddenInput) hiddenInput.value = selectedValue;
                if (labelElement) labelElement.textContent = selectedText;
                dropdown.style.display = 'none';
            }
        });

        document.addEventListener('click', (event) => {
            if (!dropdown.contains(event.target) && event.target !== searchInput) {
                dropdown.style.display = 'none';
            }
        });

        // Reposition dropdown on scroll within the modal body
        const modalBody = searchInput.closest('.modal-body');
        if (modalBody) {
            modalBody.addEventListener('scroll', () => {
                if (dropdown.style.display === 'block') showDropdown();
            });
        }
    });
    </script>
  <script>
    $(document).ready(function() {
      $('.scroll-left').on('click', function() {
        $('.table-container, .maintablewrap').animate({ scrollLeft: '-=300' }, 'ease-in-out');
      });
      $('.scroll-right').on('click', function() {
        $('.table-container, .maintablewrap').animate({ scrollLeft: '+=300' }, 'ease-in-out');
      });
    });
  </script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
        const ccrevenueInput = document.getElementById('ccrevenue');
        const brecivedInput = document.getElementById('brecived');
        const invoiceRaisedInput = document.getElementById('invoice_raised');
        const receivedRadio = document.getElementById('2success-outlined');
        const invoiceRadio = document.getElementById('update_invoice_checkbox');
        
        if (receivedRadio && brecivedInput && ccrevenueInput) {
            receivedRadio.addEventListener('click', () => {
                brecivedInput.value = ccrevenueInput.value;
            });
        }
        if (invoiceRadio && invoiceRaisedInput && ccrevenueInput) {
            invoiceRadio.addEventListener('click', () => {
                invoiceRaisedInput.value = ccrevenueInput.value;
            });
        }

        const loader = document.getElementById('loader');
        if (loader) {
            loader.style.opacity = '1';
            loader.style.top = '0';
            loader.style.zIndex = '999';
            setTimeout(() => {
                loader.style.transition = 'opacity 1s ease, top 1s ease, z-index 1s';
                loader.style.opacity = '0';
                loader.style.top = '-100px';
                loader.style.zIndex = '0';
            }, 3000);
        }
    });

    function validateForm() {
        const processingChecked = document.getElementById('btn-check-3-outlined')?.checked;
        const receivedChecked = document.getElementById('2success-outlined')?.checked;
        const canceledChecked = document.getElementById('2danger-outlined')?.checked;
        const invalidFeedback = document.querySelector('.invalid-feedback');

        if (!(processingChecked || receivedChecked || canceledChecked)) {
            if (invalidFeedback) invalidFeedback.style.display = 'block';
            return false;
        }
        
        if (invalidFeedback) invalidFeedback.style.display = 'none';
        const checkboxes = document.querySelectorAll('.btn-check.Processing');
        const radios = document.querySelectorAll('.btn-check.Received, .btn-check.Cancled');

        if (processingChecked) {
            radios.forEach(radio => radio.removeAttribute('required'));
        } else {
            checkboxes.forEach(checkbox => checkbox.removeAttribute('required'));
        }
        return true;
    }
  </script>
    <script>
        function debounce(e,t){let l;return function(...s){let n=this;clearTimeout(l),l=setTimeout(()=>e.apply(n,s),t)}}
        function searchTable(){
            let inputEl = document.getElementById("searchInput");
            let e = inputEl ? inputEl.value.toLowerCase() : "";
            let t = document.querySelectorAll("tbody");
            t.forEach(t=>{let l=t.querySelectorAll("tr");l.forEach(t=>{let l=t.innerText.toLowerCase();l.includes(e)?(t.classList.remove("tablehiddenrows"),t.style.display="",setTimeout(()=>{t.classList.remove("tablehiddenrows")},10)):(t.classList.add("tablehiddenrows"),setTimeout(()=>{t.style.display="none"},500))})})
        }
        var _searchInputEl = document.getElementById("searchInput");
        if(_searchInputEl){
            _searchInputEl.addEventListener("input",debounce(searchTable,300));
        }
    </script>
  <script>
        function addOption(type) {
            document.getElementById('optionType').value = type;
            document.getElementById('newOptionValue').value = '';
            const modal = new bootstrap.Modal(document.getElementById('addOptionModal'));
            modal.show();
        }

        const optionForm = document.getElementById('optionForm');
        if (optionForm) {
          optionForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const type = document.getElementById('optionType').value;
            const value = document.getElementById('newOptionValue').value.trim();

            if (value !== '') {
                fetch('options_handler.php?action=save', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `type=${encodeURIComponent(type)}&value=${encodeURIComponent(value)}`
                })
                .then(response => response.text())
                .then(() => {
                loadDatalist(type); // reload the datalist
                const addOptionModal = document.getElementById('addOptionModal');
                if (addOptionModal && bootstrap && bootstrap.Modal) {
                  bootstrap.Modal.getInstance(addOptionModal)?.hide();
                }
                });
            }
          });
        }

        function loadDatalist(type) {
        fetch(`options_handler.php?action=get&type=${type}`)
            .then(res => res.json())
            .then(options => {
            const list = document.getElementById(`${type}-list`);
            if (list) {
              list.innerHTML = '';
              options.forEach(val => {
                  const opt = document.createElement('option');
                  opt.value = val;
                  list.appendChild(opt);
              });
            }
            });
        }

        // Load all datalists initially
        ['developera', 'bprojecta', 'tprojecta'].forEach(loadDatalist);
    </script>
    <script>
        const bdateoInput = document.getElementById('bdateo');
        if (bdateoInput) {
          bdateoInput.addEventListener('change', function() {
              let selectedDate = this.value; // Get the selected date in YYYY-MM-DD format
              if (selectedDate) {
                  let dateObj = new Date(selectedDate);
                  let month = (dateObj.getMonth() + 1).toString().padStart(2, '0'); // Two-digit month
                  let year = dateObj.getFullYear();
                  const bmonthoInput = document.getElementById('bmontho');
                  if (bmonthoInput) bmonthoInput.value = `${year}-${month}`; // Set MM-YYYY format
              }
          });
        }
    </script>
    <script>
    function addNumber() {
        let newNumber = prompt("Enter another contact number:");
        if (newNumber && newNumber.trim() !== '') {
        let input = document.querySelector('[name="cnumber"]');
        input.value = input.value ? input.value + ', ' + newNumber.trim() : newNumber.trim();
        }
    }

    function addEmail() {
        let newEmail = prompt("Enter another email:");
        if (newEmail && newEmail.trim() !== '') {
            if (validateEmail(newEmail.trim())) {
                let input = document.querySelector('[name="cemail"]');
                input.value = input.value ? input.value + ', ' + newEmail.trim() : newEmail.trim();
            } else {
                alert("Invalid email format!");
            }
        }
    }

    function validateEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }

    // Custom Input Modal Functions
    let customInputModalCallback = null;
    let customInputModalValidation = null;

    function showCustomInputModal(title, label, placeholder, validationFn) {
        return new Promise((resolve, reject) => {
            const overlay = document.getElementById('customInputModalOverlay');
            const titleElem = document.getElementById('customInputModalTitle');
            const labelElem = document.getElementById('customInputModalLabel');
            const inputElem = document.getElementById('customInputModalField');
            
            // Set modal content
            titleElem.textContent = title || 'Enter Value';
            labelElem.textContent = label || 'Enter new value:';
            inputElem.placeholder = placeholder || 'Start typing...';
            inputElem.value = '';
            
            // Store callback and validation
            customInputModalCallback = resolve;
            customInputModalValidation = validationFn;
            
            // Show modal
            overlay.style.display = 'flex';
            setTimeout(() => inputElem.focus(), 100);
            
            // Handle Enter key
            inputElem.onkeypress = function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    submitCustomInputModal();
                }
            };
            
            // Handle Escape key
            inputElem.onkeydown = function(e) {
                if (e.key === 'Escape') {
                    e.preventDefault();
                    closeCustomInputModal();
                }
            };
        });
    }

    function closeCustomInputModal() {
        const overlay = document.getElementById('customInputModalOverlay');
        const inputElem = document.getElementById('customInputModalField');
        
        overlay.style.display = 'none';
        inputElem.value = '';
        
        if (customInputModalCallback) {
            customInputModalCallback(null);
            customInputModalCallback = null;
        }
    }

    function submitCustomInputModal() {
        const inputElem = document.getElementById('customInputModalField');
        const value = inputElem.value.trim();
        
        if (!value) {
            inputElem.style.borderColor = '#ef4444';
            setTimeout(() => {
                inputElem.style.borderColor = '';
            }, 1000);
            return;
        }
        
        // Run validation if provided
        if (customInputModalValidation) {
            const validationResult = customInputModalValidation(value);
            if (!validationResult.valid) {
                Swal.fire({
                    toast: true,
                    position: 'bottom',
                    icon: 'error',
                    title: validationResult.message || 'Invalid input',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true
                });
                inputElem.style.borderColor = '#ef4444';
                setTimeout(() => {
                    inputElem.style.borderColor = '';
                }, 1000);
                return;
            }
        }
        
        const overlay = document.getElementById('customInputModalOverlay');
        overlay.style.display = 'none';
        
        if (customInputModalCallback) {
            customInputModalCallback(value);
            customInputModalCallback = null;
        }
        
        inputElem.value = '';
    }

    // Close modal when clicking outside
    document.addEventListener('click', function(e) {
        const overlay = document.getElementById('customInputModalOverlay');
        if (e.target === overlay) {
            closeCustomInputModal();
        }
    });
    </script>
        <!-- Old booking data loading script removed - now using iframe -->
        <script>
        // Nested table functionality - Global scope
        let nestedCurrentPages = {};
        let nestedPerPageValues = {};
        let nestedSearchTerms = {}; // Store search terms for each month
        
        // Table row toggle functionality
        $(document).on("click", ".table-container .table-row, .table-container .table-row td", function(e) {
            if ($(e.target).closest('.nested-section, .nested-controls, .nested-search-wrapper, .nested-search, .compact-table, .per-page-selector, .per-page-select, .nested-table-container, .expand-icon, .btn, button, a.editLink, a.deleteLink, .btn-download, .btn-toggle, a').length > 0) {
                return;
            }
            
            const $row = $(this).closest('.table-row');
            if ($row.length === 0 || $(e.target).is('a, button, .btn')) return;
            
            const onclick = $row.attr('onclick');
            if (!onclick) return;
            
            const match = onclick.match(/toggleRow\('([^']+)'\)/);
            if (!match) return;
            
            const month = match[1];
            const $nestedRow = $('#nested-' + month);
            const $icon = $('#expand-' + month);
            
            if ($nestedRow.length === 0) return;
            
            if ($row.hasClass("open")) {
                $row.removeClass("open").removeClass("financialtrsticky");
                $nestedRow.hide();
                $icon.removeClass('rotated');
            } else {
                $(".table-row.open").each(function() {
                    const $otherRow = $(this);
                    const otherOnclick = $otherRow.attr("onclick");
                    if (otherOnclick) {
                        const otherMatch = otherOnclick.match(/toggleRow\('([^']+)'\)/);
                        if (otherMatch) {
                            const otherMonth = otherMatch[1];
                            $('#nested-' + otherMonth).hide();
                            $('#expand-' + otherMonth).removeClass('rotated');
                        }
                    }
                    $otherRow.removeClass("open").removeClass("financialtrsticky");
                });
                
                $row.addClass("open").addClass("financialtrsticky");
                $nestedRow.show();
                $icon.addClass('rotated');
                
                if (typeof updateNestedPagination === 'function') {
                    updateNestedPagination(month);
                }
            }
        });
        
        // Expand icon clicks
        $(document).on("click", ".expand-icon", function(e) {
            e.stopPropagation();
            const $icon = $(this);
            const iconId = $icon.attr('id');
            if (!iconId) return;
            
            const match = iconId.match(/expand-(.+)/);
            if (!match) return;
            
            const month = match[1];
            const $row = $('.table-row[onclick*="' + month + '"]');
            const $nestedRow = $('#nested-' + month);
            
            if ($nestedRow.length === 0) return;
            
            if ($row.hasClass("open")) {
                $row.removeClass("open").removeClass("financialtrsticky");
                $nestedRow.hide();
                $icon.removeClass('rotated');
            } else {
                $(".table-row.open").each(function() {
                    const $otherRow = $(this);
                    const otherOnclick = $otherRow.attr("onclick");
                    if (otherOnclick) {
                        const otherMatch = otherOnclick.match(/toggleRow\('([^']+)'\)/);
                        if (otherMatch) {
                            const otherMonth = otherMatch[1];
                            $('#nested-' + otherMonth).hide();
                            $('#expand-' + otherMonth).removeClass('rotated');
                        }
                    }
                    $otherRow.removeClass("open").removeClass("financialtrsticky");
                });
                
                $row.addClass("open").addClass("financialtrsticky");
                $nestedRow.show();
                $icon.addClass('rotated');
                
                if (typeof updateNestedPagination === 'function') {
                    updateNestedPagination(month);
                }
            }
        });
        
        // Keep toggleRow function for backward compatibility (if called directly from onclick)
        function toggleRow(month) {
            const $row = $('.table-row[onclick*="' + month + '"]');
            const $nestedRow = $('#nested-' + month);
            const $icon = $('#expand-' + month);
            
            if ($nestedRow.length === 0) return;
            
            if ($row.hasClass("open")) {
                $row.removeClass("open").removeClass("financialtrsticky");
                $nestedRow.hide();
                $icon.removeClass('rotated');
            } else {
                $(".table-row.open").each(function() {
                    const $otherRow = $(this);
                    const otherOnclick = $otherRow.attr("onclick");
                    if (otherOnclick) {
                        const otherMatch = otherOnclick.match(/toggleRow\('([^']+)'\)/);
                        if (otherMatch) {
                            const otherMonth = otherMatch[1];
                            $('#nested-' + otherMonth).hide();
                            $('#expand-' + otherMonth).removeClass('rotated');
                        }
                    }
                    $otherRow.removeClass("open").removeClass("financialtrsticky");
                });
                
                $row.addClass("open").addClass("financialtrsticky");
                $nestedRow.show();
                $icon.addClass('rotated');
                
                if (typeof updateNestedPagination === 'function') {
                    updateNestedPagination(month);
                }
            }
        }

        
        function toggleDetail(detailId) {
            const detailRow = document.getElementById(detailId);
            if (detailRow) {
                // Check if row is currently visible (check both inline style and computed style)
                const currentDisplay = detailRow.style.display || window.getComputedStyle(detailRow).display;
                const isCurrentlyHidden = currentDisplay === 'none' || currentDisplay === '';
                
                // Toggle visibility
                detailRow.style.display = isCurrentlyHidden ? 'table-row' : 'none';
                
                // Find the button that triggered this and toggle its text
                const buttons = document.querySelectorAll('button.btn-toggle');
                buttons.forEach(button => {
                    const onclick = button.getAttribute('onclick');
                    if (onclick && onclick.includes(detailId)) {
                        // If we're showing the row (was hidden), change to "Show Less"
                        // If we're hiding the row (was visible), change to "Show More"
                        button.textContent = isCurrentlyHidden ? 'Show Less' : 'Show More';
                    }
                });
            }
        }

        // Nested search functionality
        window.handleNestedSearch = function(month, searchTerm) {
            if (!month) return;
            nestedSearchTerms[month] = (searchTerm || '').toLowerCase().trim();
            nestedCurrentPages[month] = 1;
            if (typeof updateNestedPagination === 'function') {
                updateNestedPagination(month);
            }
        };
        
        function handleNestedSearch(month, searchTerm) {
            window.handleNestedSearch(month, searchTerm);
        }
        
        // Prevent table-row clicks from interfering with nested section
        document.addEventListener('click', function(e) {
            const searchInput = e.target.closest('.nested-search');
            if (searchInput) {
                e.stopPropagation();
                e.stopImmediatePropagation();
            }
        }, true);

        function handlePerPageChange(month, perPage) {
            nestedPerPageValues[month] = parseInt(perPage) || 10;
            nestedCurrentPages[month] = 1;
            updateNestedPagination(month);
        }

        function updateNestedPagination(month) {
            const container = document.getElementById('nested-data-' + month);
            if (!container) return;
            
            const perPage = nestedPerPageValues[month] || 10;
            const currentPage = nestedCurrentPages[month] || 1;
            const searchTerm = nestedSearchTerms[month] || '';
            
            // Get all rows
            const allRows = Array.from(container.querySelectorAll('.compact-row'));
            
            // First, filter rows by search term if there is one
            let filteredRows = allRows;
            if (searchTerm) {
                filteredRows = allRows.filter(row => {
                    const text = row.textContent.toLowerCase();
                    return text.includes(searchTerm);
                });
            }
            
            const totalRows = filteredRows.length;
            const totalPages = Math.ceil(totalRows / perPage);
            
            // Hide all rows first
            allRows.forEach(row => {
                row.style.display = 'none';
            });
            
            // Show only the rows that match search and are on the current page
            filteredRows.forEach((row, index) => {
                const pageNum = Math.floor(index / perPage) + 1;
                if (pageNum === currentPage) {
                    row.style.display = '';
                }
            });
            
            // Update showing info
            const start = totalRows > 0 ? (currentPage - 1) * perPage + 1 : 0;
            const end = totalRows > 0 ? Math.min(currentPage * perPage, totalRows) : 0;
            const startEl = document.getElementById('showing-start-' + month);
            const endEl = document.getElementById('showing-end-' + month);
            const totalEl = document.getElementById('showing-total-' + month);
            if (startEl) startEl.textContent = start;
            if (endEl) endEl.textContent = end;
            if (totalEl) totalEl.textContent = totalRows;
            
            // Update pagination buttons
            const prevBtn = document.getElementById('prev-btn-' + month);
            const nextBtn = document.getElementById('next-btn-' + month);
            if (prevBtn) prevBtn.disabled = currentPage === 1;
            if (nextBtn) nextBtn.disabled = currentPage >= totalPages || totalPages === 0;
            
            // Update page numbers
            const pageNumbersEl = document.getElementById('page-numbers-' + month);
            if (pageNumbersEl) {
                let html = '';
                if (totalPages > 0) {
                    for (let i = 1; i <= totalPages; i++) {
                        html += `<button class="btn ${i === currentPage ? 'btn-primary active-page' : 'btn-outline'} btn-sm" onclick="goToNestedPage('${month}', ${i})">${i}</button>`;
                    }
                } else {
                    html = '<span class="text-muted">No results</span>';
                }
                pageNumbersEl.innerHTML = html;
            }
        }

        function handleNestedPagination(month, direction) {
          const container = document.getElementById('nested-data-' + month);
          if (!container) return;

          const perPage = nestedPerPageValues[month] || 10;
          const searchTerm = (nestedSearchTerms[month] || '').toLowerCase();
          const currentPage = nestedCurrentPages[month] || 1;

          const allRows = Array.from(container.querySelectorAll('.compact-row'));
          const filteredRows = searchTerm
            ? allRows.filter(row => row.textContent.toLowerCase().includes(searchTerm))
            : allRows;

          const totalPages = Math.max(1, Math.ceil(filteredRows.length / perPage));

          if (direction === 'prev' && currentPage > 1) {
            nestedCurrentPages[month] = currentPage - 1;
          } else if (direction === 'next' && currentPage < totalPages) {
            nestedCurrentPages[month] = currentPage + 1;
          }

          updateNestedPagination(month);
        }

        function goToNestedPage(month, page) {
            nestedCurrentPages[month] = page;
            updateNestedPagination(month);
        }

        // Initialize nested pagination when data is loaded
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(() => {
                document.querySelectorAll('[id^="nested-data-"]').forEach(container => {
                    const month = container.id.replace('nested-data-', '');
                    if (!nestedPerPageValues[month]) nestedPerPageValues[month] = 10;
                    if (!nestedCurrentPages[month]) nestedCurrentPages[month] = 1;
                    updateNestedPagination(month);
                });
            }, 500);
            
            // Close modal when clicking outside
            const editModal = document.getElementById('editUserModal');
            if (editModal) {
                editModal.addEventListener('click', (e) => {
                    if (e.target === editModal) editModal.style.display = 'none';
                });
            }
        });

        // Edit button functionality
        document.addEventListener('click', function(e) {
            const editLink = e.target.closest('a.editLink');
            if (editLink) {
                e.preventDefault();
                e.stopPropagation();
                const id = editLink.getAttribute('id');
                if (id && typeof editUser === 'function') {
                    editUser(id);
                }
            }
        }, true);

        // Note: updateForm handler is already in main.js, so we don't duplicate it here
        // The main.js handler will call fetchAllUsers which reloads the table
        
        // Add Booking Form Submit Handler
        document.addEventListener('DOMContentLoaded', function() {
          const addBookingForm = document.getElementById('add-booking-form');
          if (addBookingForm) {
            addBookingForm.addEventListener('submit', function(e) {
              e.preventDefault();
              
              const formData = new FormData(addBookingForm);
              formData.append('add', '1'); // Signal to action.php that this is an add request
              
              // Show loading state
              const saveBtn = document.getElementById('saveBtn');
              const originalBtnText = saveBtn ? saveBtn.innerHTML : 'Add Booking';
              if (saveBtn) {
                saveBtn.disabled = true;
                saveBtn.innerHTML = 'Adding...';
              }
              
              fetch('action.php', {
                method: 'POST',
                body: formData
              })
              .then(response => response.text())
              .then(data => {
                // Restore button state
                if (saveBtn) {
                  saveBtn.disabled = false;
                  saveBtn.innerHTML = originalBtnText;
                }
                
                // Check if success or error
                if (data.includes('success') || data.includes('successfully')) {
                  Swal.fire({
                    toast: true,
                    position: 'bottom',
                    icon: 'success',
                    title: 'Booking added successfully!',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true
                  });
                  addBookingForm.reset();
                  closeAddBookingModal();
                  
                  // Refresh the iframe to show new booking
                  const iframe = document.querySelector('iframe[src*="bookings_superadmin"]');
                  if (iframe) {
                    iframe.contentWindow.location.reload();
                  }
                } else if (data.includes('duplicate')) {
                  Swal.fire({
                    toast: true,
                    position: 'bottom',
                    icon: 'warning',
                    title: 'Duplicate booking found. Data not inserted.',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true
                  });
                } else {
                  Swal.fire({
                    toast: true,
                    position: 'bottom',
                    icon: 'error',
                    title: data || 'Something went wrong!',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true
                  });
                }
              })
              .catch(error => {
                Swal.fire({
                  toast: true,
                  position: 'bottom',
                  icon: 'error',
                  title: 'Error adding booking. Please try again.',
                  showConfirmButton: false,
                  timer: 3000,
                  timerProgressBar: true
                });
                if (saveBtn) {
                  saveBtn.disabled = false;
                  saveBtn.innerHTML = originalBtnText;
                }
              });
            });
          }
          
          // Auto-fill booking month from booking date
          const bookingDateInput = document.getElementById('bookingDate');
          const bookingMonthInput = document.getElementById('bookingMonth');
          if (bookingDateInput && bookingMonthInput) {
            bookingDateInput.addEventListener('change', function() {
              const selectedDate = this.value;
              if (selectedDate) {
                const dateObj = new Date(selectedDate);
                const month = (dateObj.getMonth() + 1).toString().padStart(2, '0');
                const year = dateObj.getFullYear();
                const monthValue = `${year}-${month}`;
                bookingMonthInput.value = monthValue;
              }
            });
          }
          
          // Calculate revenue from agreement value and commission %
          window.addCalculate = function(val) {
            const agreementValue = parseFloat(document.getElementById('agreementValue')?.value) || 0;
            const commissionPct = parseFloat(document.getElementById('commissionPct')?.value) || 0;
            const cashbackPct = parseFloat(document.getElementById('cashbackPct')?.value) || 0;
            
            const revenue = (agreementValue * commissionPct) / 100;
            const revenueInput = document.getElementById('revenueAmount');
            if (revenueInput) {
              revenueInput.value = revenue.toFixed(2);
            }
            
            // Calculate actual amount (revenue - cashback)
            calculateCashbackRevenue();
          };
          
          window.calculateCashbackRevenue = function() {
            const agreementValue = parseFloat(document.getElementById('agreementValue')?.value) || 0;
            const revenue = parseFloat(document.getElementById('revenueAmount')?.value) || 0;
            const cashbackPct = parseFloat(document.getElementById('cashbackPct')?.value) || 0;
            
            // Cashback is calculated as a percentage of Agreement Value (not revenue)
            const cashbackAmount = (agreementValue * cashbackPct) / 100;
            const actualAmount = revenue - cashbackAmount;
            
            const actualAmountInput = document.getElementById('actualAmount');
            if (actualAmountInput) {
              actualAmountInput.value = actualAmount.toFixed(2);
            }
          };
          
          // Update calculation for edit modal (called from edit form)
          window.updateCalculate = function() {
            const agreement = parseFloat(document.getElementById('cagreement')?.value) || 0;
            const commission = parseFloat(document.getElementById('ccashback')?.value) || 0;
            const cashbackPct = parseFloat(document.getElementById('cccashback')?.value) || 0;
            
            // Calculate revenue: Agreement × Commission%
            const revenue = (agreement * commission) / 100;
            const revenueInput = document.getElementById('crevenue');
            if (revenueInput) {
              revenueInput.value = revenue.toFixed(2);
            }
            
            // Calculate actual amount: Revenue - (Agreement × Cashback%)
            const cashbackAmount = (agreement * cashbackPct) / 100;
            const actualAmount = revenue - cashbackAmount;
            
            const actualAmountInput = document.getElementById('ccrevenue');
            if (actualAmountInput) {
              actualAmountInput.value = actualAmount.toFixed(2);
            }
          };
          
          // Unit number prefix check
          window.prefixCheck = function(input) {
            const prefix = input.value.substring(0, 3);
            const expectedPrefix = input.defaultValue.substring(0, 3);
            if (!input.value.startsWith(expectedPrefix)) {
              input.value = input.defaultValue;
            }
          };
          
          // Lead source custom select functionality
          const leadSelect = document.getElementById('leadSelect');
          const leadBtn = document.getElementById('leadBtn');
          const leadOptions = document.getElementById('leadOptions');
          const leadValue = document.getElementById('leadValue');
          const leadSourceInput = document.getElementById('leadSource');
          
          if (leadBtn && leadOptions) {
            leadBtn.addEventListener('click', function(e) {
              e.preventDefault();
              e.stopPropagation();
              const isOpen = leadOptions.classList.contains('open');
              leadOptions.classList.toggle('open', !isOpen);
              if (leadSelect) leadSelect.setAttribute('aria-expanded', !isOpen);
            });
            
            document.querySelectorAll('.lead-option').forEach(option => {
              option.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const value = this.getAttribute('data-value');
                if (leadValue) leadValue.textContent = value;
                if (leadSourceInput) leadSourceInput.value = value;
                leadOptions.classList.remove('open');
                if (leadSelect) leadSelect.setAttribute('aria-expanded', 'false');
              });
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
              if (leadSelect && leadOptions && !leadSelect.contains(e.target)) {
                leadOptions.classList.remove('open');
                if (leadSelect) leadSelect.setAttribute('aria-expanded', 'false');
              }
            });
          }
        });
        </script>
        <script>
        // Keep nested compact tables visible inside the embedded bookings iframe on small screens
        document.addEventListener('DOMContentLoaded', function() {
          const iframe = document.getElementById('bookings-superadmin-iframe');
          if (!iframe) return;

          const injectIframeStyles = () => {
            const doc = iframe.contentDocument || iframe.contentWindow?.document;
            if (!doc) return;

            if (doc.getElementById('superadmin-iframe-compact-override')) return;

            const style = doc.createElement('style');
            style.id = 'superadmin-iframe-compact-override';
            style.textContent = `
              @media (max-width: 1200px) {
                /* Keep compact tables structured with horizontal scroll if needed */
                .compact-table {
                  display: block !important;
                  width: 100% !important;
                  overflow-x: auto !important;
                }
                .compact-table table {
                  display: table !important;
                  width: 100% !important;
                  table-layout: auto !important;
                }
                .compact-table-head,
                .compact-table-head tr,
                .compact-table tbody tr.compact-row {
                  position: sticky;
                  // display: table-row !important;
                }
                .compact-table-head th,
                .compact-table tbody td {
                  display: table-cell !important;
                  white-space: normal !important;
                  text-align: left !important;
                  vertical-align: middle !important;
                }
              }
            `;

            (doc.head || doc.body).appendChild(style);
          };

          // Function to apply dark mode to iframe immediately
          const applyDarkModeToIframe = function() {
            const isDarkMode = document.body.classList.contains('dark-mode');
            if (!iframe) return;
            
            try {
              const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
              if (iframeDoc && iframeDoc.body) {
                if (isDarkMode) {
                  iframeDoc.body.classList.add('dark-mode');
                  iframeDoc.documentElement.setAttribute('data-theme', 'dark');
                } else {
                  iframeDoc.body.classList.remove('dark-mode');
                  iframeDoc.documentElement.removeAttribute('data-theme');
                }
              }
            } catch (e) {
              console.log('Could not apply dark mode to iframe:', e);
            }
          };

          iframe.addEventListener('load', function() {
            injectIframeStyles();
            // Apply dark mode immediately on iframe load to prevent flash
            applyDarkModeToIframe();
          });

          // If the iframe is already loaded (browser cache), inject immediately
          if (iframe.contentDocument?.readyState === 'complete') {
            injectIframeStyles();
            applyDarkModeToIframe();
          }
        });
        
        // Function to apply filters to the iframe (delegate to iframe's own logic)
        window.applyFiltersToIframe = function() {
          console.log('applyFiltersToIframe called');

          // Get all filter values using getVal helper (handles Select2 multi-select properly)
          const filters = {
            id: getVal('filterID'),
            bookingDateStart: getVal('filterBookingDateStart', false),
            bookingDateEnd: getVal('filterBookingDateEnd', false),
            month: getVal('filterMonth', false),
            builder: getVal('filterBuilder'),
            project: getVal('filterProject'),
            customerName: getVal(['filterCustumername', 'filterCustomername', 'filterCustomerName']),
            contactNumber: getVal(['filterContactnumber', 'filterContactNumber']),
            email: getVal('filterEmail'),
            type: getVal('filterType'),
            unit: getVal('filterUnit'),
            size: getVal('filterSize'),
            agreement: getVal('filterAgreement'),
            commission: getVal('filterCommission') || '',
            revenue: getVal(['filterTrevenue', 'filterRevenue']) || '',
            cashback: getVal(['filterCashBack', 'filterCashback']),
            actualRevenue: getVal('filterActualRevenue') || '',
            status: getVal('filterStatus'),
            received: getVal('filterReceived') || ''
          };

          // Try multiple selectors to find the iframe
          let iframe = document.querySelector('iframe[src*="bookings_superadmin"]');
          if (!iframe) {
            iframe = document.querySelector('iframe[src*="userlogin6"]');
          }
          if (!iframe) {
            iframe = document.querySelector('iframe');
          }

          if (iframe && iframe.contentWindow) {
            // Remove empty filters (handle both strings and arrays)
            const cleanFilters = {};
            Object.keys(filters).forEach(key => {
              const val = filters[key];
              // Keep arrays if they have elements, keep non-empty strings
              if (Array.isArray(val)) {
                if (val.length > 0) cleanFilters[key] = val;
              } else if (val) {
                cleanFilters[key] = val;
              }
            });

            const hasFilters = Object.keys(cleanFilters).length > 0;

            try {
              if (hasFilters) {
                if (typeof iframe.contentWindow.applyExternalFilters === 'function') {
                  iframe.contentWindow.applyExternalFilters(cleanFilters);
                } else {
                  iframe.contentWindow.postMessage({ type: 'applyFilters', filters: cleanFilters }, '*');
                }

                const floatingBtn = document.getElementById('floatingClearFiltersBtn');
                if (floatingBtn) {
                  floatingBtn.style.display = 'flex';
                }
              } else {
                // No filters provided; clear any existing filters in the iframe
                if (typeof iframe.contentWindow.clearFilters === 'function') {
                  iframe.contentWindow.clearFilters();
                } else {
                  iframe.contentWindow.postMessage({ type: 'clearFilters' }, '*');
                }

                const floatingBtn = document.getElementById('floatingClearFiltersBtn');
                if (floatingBtn) {
                  floatingBtn.style.display = 'none';
                }
              }

              if (typeof closeFilterModal === 'function') {
                closeFilterModal();
              }
            } catch (error) {
              console.error('Error applying filters:', error);
              alert('Error applying filters: ' + error.message);
            }
          } else {
            console.error('Iframe not found or not accessible');
            console.error('All iframes on page:', document.querySelectorAll('iframe'));
            alert('Error: Unable to apply filters. Iframe not found. Please reload the page.');
          }
        };
        
        // Function to clear all filter inputs in the modal AND clear iframe filters
        window.clearAllFilters = function() {
          console.log('Clearing all filters and form inputs...');
          
          // Clear all filter input fields in the modal
          const filterInputs = [
            'filterID', 'filterBookingDateStart', 'filterBookingDateEnd', 'filterMonth',
            'filterBuilder', 'filterProject', 'filterCustumername', 'filterContactnumber',
            'filterEmail', 'filterType', 'filterUnit', 'filterSize', 'filterAgreement',
            'filterCommission', 'filterRevenue', 'filterCashback', 'filterActualRevenue',
            'filterStatus', 'filterReceived'
          ];
          
          filterInputs.forEach(id => {
            const input = document.getElementById(id);
            if (input) {
              input.value = '';
            }
          });
          
          // Clear filters in iframe
          if (typeof window.clearFiltersInIframe === 'function') {
            window.clearFiltersInIframe();
          }
          
          // Hide floating Clear Filters button
          const floatingBtn = document.getElementById('floatingClearFiltersBtn');
          if (floatingBtn) {
            floatingBtn.style.display = 'none';
          }
          
          console.log('Filters cleared - modal remains open');
        };
        
        // Function to clear filters in the iframe
        window.clearFiltersInIframe = function() {
          const iframe = document.querySelector('iframe[src*="bookings_superadmin"]') ||
                        document.querySelector('iframe');
          if (iframe && iframe.contentWindow) {
            try {
              if (typeof iframe.contentWindow.clearFilters === 'function') {
                iframe.contentWindow.clearFilters();
              } else {
                iframe.contentWindow.postMessage({ type: 'clearFilters' }, '*');
              }

              const floatingBtn = document.getElementById('floatingClearFiltersBtn');
              if (floatingBtn) {
                floatingBtn.style.display = 'none';
              }
            } catch (error) {
              console.error('Error clearing filters:', error);
            }
          }
        };

        // On iframe load, ask it to remove its own dashboard clear button to avoid duplicates
        // Also apply dark mode immediately to prevent flash
        document.addEventListener('DOMContentLoaded', () => {
          const iframe = document.querySelector('iframe[src*="bookings_superadmin"]') || document.querySelector('iframe');
          if (!iframe) return;
          
          const applyDarkModeToIframe = function() {
            const isDarkMode = document.body.classList.contains('dark-mode');
            try {
              const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
              if (iframeDoc && iframeDoc.body) {
                if (isDarkMode) {
                  iframeDoc.body.classList.add('dark-mode');
                  iframeDoc.documentElement.setAttribute('data-theme', 'dark');
                } else {
                  iframeDoc.body.classList.remove('dark-mode');
                  iframeDoc.documentElement.removeAttribute('data-theme');
                }
              }
            } catch (e) {
              console.log('Could not apply dark mode to iframe:', e);
            }
          };
          
          iframe.addEventListener('load', () => {
            try {
              iframe.contentWindow.postMessage({ type: 'removeDashboardClearFilterBtn' }, '*');
              // Apply dark mode immediately on every iframe load
              applyDarkModeToIframe();
            } catch (e) {
              console.warn('Could not signal iframe to remove dashboard clear filter button', e);
            }
          });
        });
        
        // Lead Source Dropdown JavaScript
        (function() {
          const leadSelectWrap = document.getElementById('leadSelect');
          const leadBtn = document.getElementById('leadBtn');
          const leadOptions = document.getElementById('leadOptions');
          const leadValue = document.getElementById('leadValue');
          const hiddenSelect = document.getElementById('leadSource');
          
          if (!leadSelectWrap || !leadBtn || !leadOptions) {
            console.log('Lead source elements not found');
            return;
          }
          
          function openLead() {
            leadSelectWrap.classList.add('open');
            leadSelectWrap.setAttribute('aria-expanded', 'true');
          }
          
          function closeLead() {
            leadSelectWrap.classList.remove('open');
            leadSelectWrap.setAttribute('aria-expanded', 'false');
          }
          
          // Toggle dropdown on button click
          leadBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (leadSelectWrap.classList.contains('open')) {
              closeLead();
            } else {
              openLead();
            }
          });
          
          // Close dropdown when clicking outside
          document.addEventListener('click', function(e) {
            if (!leadSelectWrap.contains(e.target)) {
              closeLead();
            }
          });
          
          // Handle option selection
          const optionElements = leadOptions.querySelectorAll('.lead-option');
          optionElements.forEach(function(li) {
            li.addEventListener('click', function(e) {
              e.stopPropagation();
              const value = li.getAttribute('data-value');
              leadValue.textContent = li.textContent;
              hiddenSelect.value = value;
              
              // Update aria-selected
              optionElements.forEach(function(opt) {
                opt.removeAttribute('aria-selected');
              });
              li.setAttribute('aria-selected', 'true');
              
              closeLead();
            });
          });
          
          console.log('Lead Source dropdown initialized');
        })();
        
        // Status Selection Function for Add Booking Modal
        function selectStatus(button, status) {
          // Remove active class from all status buttons
          const allStatusBtns = document.querySelectorAll('.status-btn');
          allStatusBtns.forEach(btn => {
            btn.classList.remove('status-btn-active');
            const statusType = btn.getAttribute('data-status');
            
            // Reset to default styles
            if (statusType === 'Processing') {
              btn.style.background = 'white';
              btn.style.color = '#1b6c9f';
              btn.style.border = '1px solid #1b6c9f';
            } else if (statusType === 'Received') {
              btn.style.background = 'white';
              btn.style.color = '#6b7280';
              btn.style.border = '1px solid #d1d5db';
            } else if (statusType === 'Canceled') {
              btn.style.background = 'white';
              btn.style.color = '#dc2626';
              btn.style.border = '1px solid #dc2626';
            }
          });
          
          // Add active class to clicked button
          button.classList.add('status-btn-active');
          
          // Set active styles based on status
          if (status === 'Processing') {
            button.style.background = '#1b6c9f';
            button.style.color = 'white';
            button.style.border = '1px solid #1b6c9f';
          } else if (status === 'Received') {
            button.style.background = '#6b7280';
            button.style.color = 'white';
            button.style.border = '1px solid #6b7280';
          } else if (status === 'Canceled') {
            button.style.background = '#dc2626';
            button.style.color = 'white';
            button.style.border = '1px solid #dc2626';
          }
          
          // Update hidden input
          const hiddenStatusInput = document.querySelector('input[name="cstatus"]');
          if (hiddenStatusInput) {
            hiddenStatusInput.value = status;
          }
        }
        
        // +Add Button Functionality for Add Booking Modal
        document.addEventListener('DOMContentLoaded', function() {
          const addButtons = document.querySelectorAll('.add-btn');
          
          addButtons.forEach(button => {
            button.addEventListener('click', async function(e) {
              e.preventDefault();
              e.stopPropagation();
              
              const addType = this.getAttribute('data-add');
              let inputId, datalistId, promptMessage, modalTitle, modalLabel, placeholder, validationFn;
              
              // Map button types to their respective inputs and datalists
              switch(addType) {
                case 'builder':
                  inputId = 'builderName';
                  datalistId = 'builderList';
                  modalTitle = 'Add Builder Name';
                  modalLabel = 'Enter new Builder Name:';
                  placeholder = 'e.g., ABC Builders';
                  break;
                case 'project':
                  inputId = 'projectName';
                  datalistId = 'projectList';
                  modalTitle = 'Add Project Name';
                  modalLabel = 'Enter new Project Name:';
                  placeholder = 'e.g., Green Valley Apartments';
                  break;
                case 'ptype':
                  inputId = 'projectType';
                  datalistId = 'ptypeList';
                  modalTitle = 'Add Project Type';
                  modalLabel = 'Enter new Project Type:';
                  placeholder = 'e.g., Residential';
                  break;
                case 'customer':
                  inputId = 'customerName';
                  modalTitle = 'Add Customer Name';
                  modalLabel = 'Enter new Customer Name:';
                  placeholder = 'Full name';
                  break;
                case 'email':
                  inputId = 'email';
                  modalTitle = 'Add Email Address';
                  modalLabel = 'Enter new Email Address:';
                  placeholder = 'name@example.com';
                  validationFn = (value) => {
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    return emailRegex.test(value) 
                      ? { valid: true } 
                      : { valid: false, message: 'Please enter a valid email address' };
                  };
                  break;
                case 'contact':
                  inputId = 'contactNo';
                  modalTitle = 'Add Contact Number';
                  modalLabel = 'Enter new Contact Number:';
                  placeholder = '+91 XXXXX XXXXX';
                  validationFn = (value) => {
                    const phoneRegex = /^[0-9]{10}$/;
                    return phoneRegex.test(value) 
                      ? { valid: true } 
                      : { valid: false, message: 'Please enter a valid 10-digit phone number' };
                  };
                  break;
                default:
                  return;
              }
              
              // Show custom modal to get new value
              const newValue = await showCustomInputModal(modalTitle, modalLabel, placeholder, validationFn);
              
              if (newValue && newValue.trim() !== '') {
                const trimmedValue = newValue.trim();
                
                // If there's a datalist, add option to it (avoid duplicates)
                if (datalistId) {
                  const datalist = document.getElementById(datalistId);
                  if (datalist) {
                    const exists = Array.from(datalist.options).some(opt => opt.value === trimmedValue);
                    if (!exists) {
                      const newOption = document.createElement('option');
                      newOption.value = trimmedValue;
                      datalist.appendChild(newOption);
                    }
                  }
                }
                
                // Append to the input field (comma-separated, no duplicates)
                const inputField = document.getElementById(inputId);
                if (inputField) {
                  const parts = inputField.value
                    ? inputField.value.split(',').map(v => v.trim()).filter(v => v.length)
                    : [];
                  if (!parts.includes(trimmedValue)) {
                    parts.push(trimmedValue);
                  }
                  inputField.value = parts.join(', ');
                  inputField.focus();
                  
                  // Show success feedback
                  const originalBg = this.style.background;
                  this.style.background = '#10b981';
                  setTimeout(() => {
                    this.style.background = originalBg;
                  }, 500);
                }
              }
            });
          });
          
          console.log('+Add buttons initialized:', addButtons.length);
        });
        </script>
        
        <!-- jQuery and Select2 JS for autocomplete -->
        <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
        
        <!-- Select2 Custom Matcher Fix - Inline for proper search filtering -->
        <script>
        /**
         * Universal Select2 Custom Matcher Fix
         * This script adds a custom matcher function to all Select2 dropdowns on the page
         * to enable proper filtering when users type in the search box.
         */
        (function () {
            'use strict';

            /**
             * Custom matcher function for Select2
             * Performs case-insensitive substring search on option text
             */
            function customSelect2Matcher(params, data) {
                // If there are no search terms, return all data
                if ($.trim(params.term) === '') {
                    return data;
                }

                // Do not display the item if there is no 'text' property
                if (typeof data.text === 'undefined') {
                    return null;
                }

                // Check if the text contains the search term (case-insensitive)
                if (data.text.toLowerCase().indexOf(params.term.toLowerCase()) > -1) {
                    return data;
                }

                // Check if option has children (optgroup)
                if (data.children && data.children.length > 0) {
                    // Clone the data object to avoid modifying the original
                    var match = $.extend(true, {}, data);

                    // Filter children
                    for (var c = data.children.length - 1; c >= 0; c--) {
                        var child = data.children[c];
                        var matches = customSelect2Matcher(params, child);

                        // Remove child if it doesn't match
                        if (matches == null) {
                            match.children.splice(c, 1);
                        }
                    }

                    // Return match only if it has some matching children
                    if (match.children.length > 0) {
                        return match;
                    }
                }

                // Return null to hide the option
                return null;
            }

            /**
             * Initialize Select2 with custom matcher on target elements
             * Supports AJAX loading for filter elements with data-field attribute
             */
            function initSelect2WithMatcher(selector) {
                $(selector).each(function () {
                    var $element = $(this);

                    // Check if Select2 is already initialized
                    if ($element.hasClass('select2-hidden-accessible')) {
                        // Destroy existing Select2 instance
                        $element.select2('destroy');
                    }

                    // Get existing options or use defaults
                    var existingOptions = $element.data('select2-options') || {};
                    
                    // Check if element has data-field attribute for AJAX loading
                    var fieldName = $element.attr('data-field');
                    
                    var select2Options = {
                        multiple: true,
                        closeOnSelect: false,
                        width: '100%',
                        placeholder: $element.attr('placeholder') || 'Search & select',
                        allowClear: true,
                        dropdownParent: $('body')
                    };
                    
                    // If field name is provided, use AJAX for loading options
                    if (fieldName) {
                        console.log('Initializing AJAX Select2 for field:', fieldName);
                        select2Options.ajax = {
                            url: 'fetch_filter_options.php',
                            dataType: 'json',
                            delay: 250,
                            data: function (params) {
                                var requestData = {
                                    field: fieldName,
                                    search: params.term || '',
                                    page: params.page || 1
                                };
                                console.log('Select2 AJAX request for', fieldName, ':', requestData);
                                return requestData;
                            },
                            processResults: function (data, params) {
                                params.page = params.page || 1;
                                console.log('Select2 AJAX response for', fieldName, ':', data);
                                
                                // Ensure data structure is correct
                                if (data && data.results) {
                                    return {
                                        results: data.results,
                                        pagination: {
                                            more: (data.pagination && data.pagination.more) || false
                                        }
                                    };
                                } else {
                                    // Fallback for unexpected response format
                                    console.warn('Unexpected response format for', fieldName, ':', data);
                                    return {
                                        results: [],
                                        pagination: { more: false }
                                    };
                                }
                            },
                            error: function(xhr, status, error) {
                                // Ignore abort errors (normal when typing quickly)
                                if (status !== 'abort') {
                                    console.error('Select2 AJAX error for', fieldName, ':', {
                                        status: status,
                                        error: error,
                                        responseText: xhr.responseText
                                    });
                                }
                            },
                            cache: true
                        };
                        select2Options.minimumInputLength = 0; // Allow loading without input
                    } else {
                        // For non-AJAX fields, use custom matcher
                        select2Options.matcher = customSelect2Matcher;
                    }

                    // Initialize Select2 with configured options
                    $element.select2($.extend({}, existingOptions, select2Options));
                    console.log('Select2 initialized for element:', $element.attr('id'));
                });
            }

            /**
             * Apply the custom matcher to all filter dropdowns
             */
            function applyCustomMatcher() {
                // List of common filter dropdown selectors
                var filterSelectors = [
                    '#filterBuilderName',
                    '#filterProjectName',
                    '#filterCustomerName',
                    '#filterStatus',
                    '#filterMonth',
                    '#filterYear',
                    '.filter-dropdown',
                    '.select2-dropdown',
                    'select[id^="filter"]',
                    'select[name^="filter"]'
                ];

                // Apply to each selector that exists
                filterSelectors.forEach(function (selector) {
                    if ($(selector).length > 0) {
                        console.log('Applying custom Select2 matcher to:', selector);
                        initSelect2WithMatcher(selector);
                    }
                });
            }

            // Apply when DOM is ready
            $(document).ready(function () {
                console.log('Select2 matcher fix loaded');

                // Wait a bit for other scripts to initialize Select2 first
                setTimeout(applyCustomMatcher, 500);

                // Re-apply when modals are opened (in case Select2 is re-initialized)
                $(document).on('shown.bs.modal', function () {
                    setTimeout(applyCustomMatcher, 100);
                });

                // Listen for custom event to manually trigger re-initialization
                $(document).on('reinitSelect2Matcher', function () {
                    applyCustomMatcher();
                });
            });

            // Also try to apply immediately if jQuery and Select2 are already loaded
            if (typeof jQuery !== 'undefined' && jQuery.fn.select2) {
                setTimeout(applyCustomMatcher, 1000);
                setTimeout(applyCustomMatcher, 2000);
            }

            // Expose function globally for manual calls if needed
            window.applySelect2CustomMatcher = applyCustomMatcher;

            /**
             * Global function to clear all Select2 filter selections
             * Call this when "Clear Filters" button is clicked
             */
            window.clearAllFilterSelections = function() {
                console.log('Clearing all Select2 filter selections...');
                
                try {
                    // List of all filter selectors
                    var filterSelectors = [
                        '#filterBuilderName',
                        '#filterProjectName',
                        '#filterCustomerName',
                        '#filterStatus',
                        '#filterMonth',
                        '#filterYear',
                        '.filter-dropdown',
                        'select[id^="filter"]',
                        'select[name^="filter"]',
                        '.filter-select'
                    ];
                    
                    var clearedCount = 0;
                    
                    // Clear each Select2 dropdown
                    filterSelectors.forEach(function(selector) {
                        try {
                            var $elements = $(selector);
                            if ($elements.length > 0) {
                                $elements.each(function() {
                                    try {
                                        var $el = $(this);
                                        // Clear the selection and trigger change event
                                        if ($el.hasClass('select2-hidden-accessible')) {
                                            $el.val(null).trigger('change');
                                            clearedCount++;
                                        }
                                    } catch (e) {
                                        console.warn('Error clearing individual element:', e);
                                    }
                                });
                            }
                        } catch (e) {
                            console.warn('Error with selector', selector, ':', e);
                        }
                    });
                    
                    console.log('✓ Cleared', clearedCount, 'Select2 filters');
                    return true;
                } catch (e) {
                    console.error('Error in clearAllFilterSelections:', e);
                    return false;
                }
            };

            /**
             * Safe wrapper to clear all filters including Select2 and regular inputs
             * This prevents the existing clearFilters chain from causing errors
             */
            window.safeClearAllFilters = function() {
                console.log('=== Safe Clear All Filters ===');
                
                try {
                    // First, clear all Select2 dropdowns
                    window.clearAllFilterSelections();
                    
                    // Then clear any regular text inputs with filter IDs
                    var textInputSelectors = [
                        'input[id^="filter"]',
                        'input[name^="filter"]'
                    ];
                    
                    textInputSelectors.forEach(function(selector) {
                        try {
                            $(selector).val('');
                        } catch (e) {
                            console.warn('Error clearing text input:', e);
                        }
                    });
                    
                    console.log('✓ All filters cleared successfully');

                                        
                    // Hide floating Clear Filters button
                    try {
                        var floatingBtn = document.getElementById('floatingClearFiltersBtn');
                        if (floatingBtn) {
                            floatingBtn.style.display = 'none';
                            console.log('✓ Floating clear button hidden');
                        }
                    } catch (e) {
                        console.warn('Error hiding floating button:', e);
                    }

                    
                    // Try to call the iframe's clearFilters if it exists (but catch errors)
                    try {
                        var iframe = document.querySelector('iframe[src*="bookings_superadmin"]') || 
                                    document.querySelector('iframe[src*="userlogin6"]') ||
                                    document.querySelector('iframe');
                        
                        if (iframe && iframe.contentWindow) {
                            if (typeof iframe.contentWindow.clearFilters === 'function') {
                                // Try to clear filters in iframe, but wrap in try-catch
                                try {
                                    iframe.contentWindow.clearFilters();
                                } catch (iframeError) {
                                    console.warn('Iframe clearFilters had an error (ignoring):', iframeError.message);
                                }
                            }
                        }
                    } catch (e) {
                        console.warn('Error accessing iframe (ignoring):', e.message);
                    }
                    
                    return true;
                } catch (e) {
                    console.error('Error in safeClearAllFilters:', e);
                    return false;
                }
            };

            /**
             * Override the existing broken clearAllFilters function
             * This ensures the Clear Filters button works without modification
             */
            window.clearAllFilters = function() {
                console.log('clearAllFilters called - redirecting to safe version');
                return window.safeClearAllFilters();
            };

            /**
             * Override the existing broken clearFiltersInIframe function
             * This prevents the TypeError from breaking the filter clearing
             */
            window.clearFiltersInIframe = function() {
                console.log('clearFiltersInIframe called - using safe implementation');
                // Just call the safe version and ignore the broken original
                return window.clearAllFilterSelections();
            };

            console.log('✓ Select2 Custom Matcher Script Loaded');
            console.log('✓ Clear filter functions overridden with safe versions');
        })();
        </script>
        
        <script>
        // File input handler for edit modal - show selected file name
        document.addEventListener('DOMContentLoaded', function() {
            const editFileInput = document.getElementById('editFileInput');
            if (editFileInput) {
                editFileInput.addEventListener('change', function(e) {
                    if (this.files && this.files[0]) {
                        const fileName = this.files[0].name;
                        const fileSize = (this.files[0].size / 1024).toFixed(2); // in KB
                        
                        // Create or update new file indicator
                        let newFileIndicator = this.parentElement.querySelector('.new-file-indicator');
                        if (!newFileIndicator) {
                            newFileIndicator = document.createElement('div');
                            newFileIndicator.className = 'new-file-indicator';
                            newFileIndicator.style.marginTop = '0.5rem';
                            newFileIndicator.style.padding = '0.75rem';
                            newFileIndicator.style.background = '#dbeafe';
                            newFileIndicator.style.border = '1px solid #3b82f6';
                            newFileIndicator.style.borderRadius = '8px';
                            newFileIndicator.style.fontSize = '14px';
                            newFileIndicator.style.color = '#1e40af';
                            this.parentElement.appendChild(newFileIndicator);
                        }
                        
                        newFileIndicator.innerHTML = `
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <i class="fa fa-file" style="color: #3b82f6;"></i>
                                <div style="flex: 1;">
                                    <div style="font-weight: 500;">${fileName}</div>
                                    <div style="font-size: 12px; color: #6b7280;">${fileSize} KB - Ready to upload</div>
                                </div>
                                <i class="fa fa-check-circle" style="color: #10b981;"></i>
                            </div>
                        `;
                    }
                });
            }
        });
        </script>     
  </body>
</html>