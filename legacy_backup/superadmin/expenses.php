<?php
include('htmlopen.php');
// We need to use sessions, so you should always start sessions using the below code.
session_start();
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
// Check if the logged-in user is a superadmin and set the session variable accordingly
if ($nameuser === $nameuser) { // Replace 'your_superadmin_username' with the actual superadmin's username
  $_SESSION['is_superadmin'] = true;
}
// Fetch managers and team leads from the account table
$DATABASE_HOST = 'localhost';
$DATABASE_USER = 'u797909128_demoproject';
$DATABASE_PASS = 'QK&0/aF@5';
$DATABASE_NAME = 'u797909128_demo';

// Try and connect using the info above.
$conn = mysqli_connect($DATABASE_HOST, $DATABASE_USER, $DATABASE_PASS, $DATABASE_NAME);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$sql = "SELECT  username, tablename FROM accounts WHERE user_type IN ('manager', 'teamlead', 'ceo')";
$result = $conn->query($sql);

// Initialize an array to store the fetched users
$assignUsers = [];
if ($result) {
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $assignUsers[] = $row;
        }
    }
} else {
    echo "Error: " . $sql . "<br>" . $conn->error;
}

// Close the connection
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Company Expenses</title>
  <!-- <link rel="shortcut icon" type="nobglogo.png" href="../assets/images/nobglogo.png" alt="text">
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/5.0.0-alpha2/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" />
  <link rel="stylesheet" href="../assets/css/style.css" />
  <link rel="stylesheet" href="./assets/css/style1.css" />
  <link rel="stylesheet" href="../assets/css/loader.css"/> -->
  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Lexend+Deca:wght@100..900&display=swap" rel="stylesheet">


 <link rel="stylesheet" href="./assets/css/unified_table_styles.css"/>
 <!-- Select2 CSS -->
 <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />
 <link href="https://cdnjs.cloudflare.com/ajax/libs/select2-bootstrap-5-theme/1.3.0/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
 <style>
    body {
      font-family: "Lexend Deca", sans-serif !important;
      font-optical-sizing: auto !important;
    }
    .addBooking{display:none;}
    .side-menu li.sideactive2{
      background: var(--shicol);
      position: relative
    }
    .side-menu li.sideactive2 a{
      color: white
    }
    /* Page-specific overrides */
    #emptotaldata {
      width: 100%;
      padding: 1rem 1.5rem;
      background: linear-gradient(135deg, #dbeafe, #bfdbfe);
      box-shadow: 0 4px 6px rgba(0,0,0,.1);
      text-align: center;
      margin: 0 auto 1rem;
      border: 1px solid #e0e0e0;
      border-radius: 0.75rem;
      display: none;
    }
    .unified-table thead th:last-child, .fold-table thead th:last-child, #myTable thead th:last-child, #example thead th:last-child {
      border-radius: 0 0px 0 0 !important;
    }
    .unified-table tbody td:last-child, .fold-table tbody tr td:last-child, #myTable tbody td:last-child, #example tbody td:last-child {
    /* border-right: none !important; */
        border-radius: 0 12px 12px 0 !important;
    }
    
    /* On large screens (>1024px), apply border-radius to 8th column since chevron is hidden */
    @media (min-width: 1025px) {
      .fold-table tbody tr td:nth-child(8) {
        border-radius: 0 12px 12px 0 !important;
      }
    }
    @media (max-width: 4000px) {
        .container, .container-lg, .container-md, .container-sm {
             max-width: 100% !important;
        }
    }
    @media (max-width: 1800px) {
        .container, .container-lg, .container-md, .container-sm {
             max-width: 1720px !important;
        }
    }
    @media (max-width: 1600px) {
        .container, .container-lg, .container-md, .container-sm {
             max-width: 1520px !important;
        }
    }
    @media (max-width: 1400px) {
        .container, .container-lg, .container-md, .container-sm {
             max-width: 1320px !important;
        }
    }
    @media (max-width: 1200px) {
        .container, .container-lg, .container-md, .container-sm {
             max-width: 1140px !important;
        }
    }
    @media (max-width: 992px) {
        .container, .container-lg, .container-md, .container-sm {
             max-width: 960px !important;
        }
    }
    @media (max-width: 768px) {
        .container, .container-md, .container-sm {
            max-width: 720px !important;
        }
    }
    @media (max-width: 576px) {
        .container, .container-sm {
            max-width: 540px !important;
        }
    }
    @media (max-width: 480px) {
        .container, .container-sm {
            max-width: 460px !important;
        }
    }
    @media (max-width: 426px) {
        .container, .container-sm {
            max-width: 405px !important;
        }
    }
    @media (max-width: 400px) {
        .container, .container-sm {
            max-width: 380px !important;
        }
    }
    @media (max-width: 380px) {
        .container, .container-sm {
            max-width: 360px !important;
        }
    }
    @media (max-width: 360px) {
        .container, .container-sm {
            max-width: 340px !important;
        }
    }
    @media (max-width: 350px) {
        .container, .container-sm {
            max-width: 330px !important;
        }
    }
    @media (max-width: 320px) {
        .container, .container-sm {
            max-width: 300px !important;
        }
    }
    .container, .container-fluid, .container-lg, .container-md, .container-sm, .container-xl, .container-xxl {
      padding-right: 0rem !important;
      padding-left: 0rem !important;
    }
    .row>* {
      margin-right: 0px !important;
      margin-left: 0px !important;
      padding-right: 10px !important;
      padding-left:10px !important;
    }
    @media screen and (max-width: 768px) {
        .content, body.sidebar-collapsed .content, body.sidebar-overlay .content {
            padding: 70px 0px 0px !important;
        }
    }
    .row{
      margin-right: 0px !important;
      margin-left: 0px !important;
    }


    #emptotaldata .totalbook {
      justify-content: center;
    }
    .totalbook {
      display: flex;
      align-items: center;
      margin: 5px 0;
      gap: 1.5rem;
      flex-wrap: wrap;
    }
    .totalbook .totalbookchild h6 {
      font-size: 14px;
      font-weight: 600;
      color: #1e40af;
    }
    .newsec {
      width: 100%;
      padding: 1rem 1.5rem;
      background: linear-gradient(135deg, #f8fafc, #f1f5f9);
      box-shadow: 0 4px 6px rgba(0,0,0,.1);
      border: 1px solid #e2e8f0;
      border-radius: 0.75rem;
      text-align: left;
    }
    
    /* Controls Row Styling */
    .leads-controls-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding-top: 5px;
    }
    
    /* Search Box */
    .leads-search-box {
      display: flex;
      align-items: center;
      gap: 10px;
      background-color: #ffffff;
      border: 1px solid #dee2e6;
      border-radius: 6px;
      padding: 8px 12px;
      flex: 1;
      margin-right: 6px;
    }
    
    .leads-search-box i {
      color: #6c757d;
      font-size: 16px;
    }
    
    .leads-search-box input {
      border: none;
      outline: none;
      width: 100%;
      font-size: 14px;
      color: #495057;
    }
    
    .leads-search-box input::placeholder {
      color: #adb5bd;
    }
    
    /* Toolbar Controls */
    .leads-toolbar-controls {
      width: auto;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    
    /* Row Selector */
    .rowSelector_wrap {
      position: relative;
      display: inline-flex;
      align-items: center;
    }
    
    .rowSelector_wrap select {
      padding: 10px 28px 10px 15px;
      border: 1px solid #dee2e6;
      border-radius: 6px;
      background-color: #ffffff;
      font-size: 14px;
      cursor: pointer;
      appearance: none;
      background-image: none;
    }
    
    .rowSelector_wrap select:hover {
      border-color: #adb5bd;
    }
    
    .rowSelector_wrap::after {
      content: '\25BE';
      position: absolute;
      right: 3px;
      top: 50%;
      transform: translateY(-50%);
      color: #495057;
      font-size: 24px;
      pointer-events: none;
    }
    
    /* Column Visibility Dropdown */
    .column-visibility-dropdown {
      position: relative;
      display: inline-block;
    }
    
    .column-visibility-btn {
      padding: 10px;
      border: 1px solid #dee2e6;
      border-radius: 6px;
      background-color: #ffffff;
      font-size: 18px;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: all 0.2s ease;
      min-width: 42px;
      height: 42px;
    }
    
    .column-visibility-btn:hover {
      border-color: #adb5bd;
      background-color: #f8f9fa;
    }
    
    .column-visibility-btn i {
      font-size: 18px;
      color: #495057;
    }
    
    .column-visibility-menu {
      display: none;
      position: absolute;
      top: 100%;
      right: 0;
      margin-top: 5px;
      background: white;
      border: 1px solid #dee2e6;
      border-radius: 8px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
      min-width: 200px;
      z-index: 1000;
      padding: 8px 0;
    }
    
    .column-visibility-menu.show {
      display: block;
    }
    
    .column-visibility-item {
      padding: 8px 16px;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 10px;
      transition: background-color 0.2s ease;
    }
    
    .column-visibility-item:hover {
      background-color: #f8f9fa;
    }
    body.dark-mode .column-visibility-item:hover {
      background-color: #f8f9fa1c;
    }
    
    .column-visibility-item input[type="checkbox"] {
      cursor: pointer;
      width: 16px;
      height: 16px;
    }
    
    .column-visibility-item label {
      cursor: pointer;
      margin: 0;
      font-size: 14px;
      color: #495057;
      user-select: none;
    }
    
    /* Table wrapper with horizontal scroll */
    .maintablewrap {
      overflow-x: auto;
      overflow-y: visible;
      width: 100%;
      -webkit-overflow-scrolling: touch;
      margin: 0;
    }
    
    /* Ensure table has minimum width to trigger scroll */
    .fold-table {
      min-width: 800px;
      width: 100%;
    }
    
    /* Table cell padding with high specificity */
    .fold-table tbody td,
    table.fold-table tbody td,
    .maintablewrap .fold-table tbody td {
      padding: 14px !important;
    }
    
    .maintablewrap::-webkit-scrollbar {
      height: 8px;
    }
    
    .maintablewrap::-webkit-scrollbar-track {
      background: #f1f1f1;
      border-radius: 4px;
    }
    
    .maintablewrap::-webkit-scrollbar-thumb {
      background: #888;
      border-radius: 4px;
    }
    
    .maintablewrap::-webkit-scrollbar-thumb:hover {
      background: #555;
    }
    
    /* Column visibility - use class-based hiding with !important to override media queries */
    .fold-table th.column-hidden,
    .fold-table td.column-hidden {
      display: none !important;
    }
    
    /* Force show column - overrides everything including media queries */
    .fold-table th.column-show,
    .fold-table td.column-show {
      display: table-cell !important;
    }
    
    /* Responsive adjustments */
    @media (max-width: 768px) {
      .leads-controls-row {
        gap: 10px;
      }
      
      .leads-search-box {
        margin-right: 0;
      }
      
      /* Hide Facebook Exp. column (2nd column) - but not if manually shown */
      .fold-table thead th:nth-child(2):not(.column-show),
      .fold-table tbody td:nth-child(2):not(.column-show) {
        display: none !important;
      }
      
      /* Hide HR Exp. column (4th column) - but not if manually shown */
      .fold-table thead th:nth-child(4):not(.column-show),
      .fold-table tbody td:nth-child(4):not(.column-show) {
        display: none !important;
      }
    }
    @media (max-width: 426px) {
      /* Hide Google Exp. column (3rd column) - but not if manually shown */
      .fold-table thead th:nth-child(3):not(.column-show),
      .fold-table tbody td:nth-child(3):not(.column-show) {
        display: none !important;
      }
    }
    
    /* Hide specific columns on smaller screens (less than 1024px) */
    @media (max-width: 1023px) {
      /* Hide IT Exp. column (5th column) - but not if manually shown */
      .fold-table thead th:nth-child(5):not(.column-show),
      .fold-table tbody td:nth-child(5):not(.column-show) {
        display: none !important;
      }
      
      /* Hide SHI Exp. column (6th column) - but not if manually shown */
      .fold-table thead th:nth-child(6):not(.column-show),
      .fold-table tbody td:nth-child(6):not(.column-show) {
        display: none !important;
      }
      
      /* Hide Accounts Exp. column (7th column) - but not if manually shown */
      .fold-table thead th:nth-child(7):not(.column-show),
      .fold-table tbody td:nth-child(7):not(.column-show) {
        display: none !important;
      }
    }
    
    /* Chevron column - hidden on large screens (>1024px), visible on smaller screens */
    .fold-table thead th.chevron-col,
    .fold-table tbody td.chevron-col {
      display: none !important;
      width: 50px;
      text-align: center;
      padding: 12px 8px;
    }
    
    /* Show chevron column only on screens 1024px and below */
    @media (max-width: 1024px) {
      .fold-table thead th.chevron-col,
      .fold-table tbody td.chevron-col {
        display: table-cell !important;
      }
    }
    
    /* Chevron icon styling */
    .chevron-icon {
      width: 25px;
      height: 25px;
      background-color: #000;
      border-radius: 50%;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: transform 0.3s ease;
    }
    
    .chevron-icon i {
      color: #fff;
      font-size: 16px;
    }
    
    .fold-table tr.view.open .chevron-icon {
      transform: rotate(90deg);
    }
    
    /* Make table rows clickable */
    .fold-table tbody tr.data-row {
      cursor: pointer;
    }
    
    /* Rotate chevron when row is expanded */
    tr.expanded .chevron-icon i {
      transform: rotate(90deg);
    }
    
    /* Details row styling */
    tr.details-row {
      background-color: #fff !important;
      cursor: default !important;
    }
    
    tr.details-row td {
      padding: 0 !important;
      border: none !important;
    }
    
    .details-container {
      padding: 8px;
      background: white;
    }
    
    .details-title {
      font-size: 18px;
      font-weight: 600;
      text-align: center;
      margin-bottom: 5px;
      color: #333;
    }
    
    .detail-item {
      margin-bottom: 4px;
      display: flex;
      align-items: baseline;
    }
    
    .detail-label {
      font-weight: 600;
      color: #6c757d;
      text-transform: uppercase;
      font-size: 12px;
      min-width: 180px;
      margin-right: 10px;
    }
    
    .detail-value {
      color: #333;
      font-size: 14px;
      font-weight: 400;
    }
    
    
    /* @media (max-width: 545px) {
      .container, .container-fluid, .container-lg, .container-md, .container-sm, .container-xl, .container-xxl {
          padding-left: 0.1rem;
          padding-right: 0.1rem;
      }
    } */
    
    /* Pagination Footer Styling */
    .dt-layout-foot {
      background: transparent !important;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 5px;
      padding: 10px 5px;
      border: none;
    }
    
    .pagination {
      display: flex;
      align-items: center;
      gap: 8px;
    }
    @media (max-width: 670px) {
      .pagination {
          flex-direction: row !Important;
      }
  }

    .pagination button {
      border-radius: 6px !important;
      border: 1px solid #dee2e6 !important;
      background: #fff !important;
      padding: 6px 14px !important;
      transition: all 0.2s ease !important;
      font-size: 14px !important;
      font-weight: 500 !important;
      color: #495057 !important;
      min-width: auto !important;
      cursor: pointer;
    }
    
    .pagination button:hover:not(:disabled) {
      background: #e9ecef !important;
      border-color: #adb5bd !important;
    }
    
    .pagination button.active {
      background: #007bff !important;
      color: #fff !important;
      border-color: #007bff !important;
    }
    
    .pagination button:disabled {
      opacity: 0.5 !important;
      cursor: not-allowed !important;
    }
    
    #expenseJumpButton:hover {
      background: #0056b3 !important;
    }
    
    /* Mobile responsive pagination */
    @media (max-width: 768px) {
      .dt-layout-foot {
        flex-direction: column;
        gap: 10px;
      }
      
      #expenseRowInfo {
        text-align: center;
        order: 1;
      }
      
      .pagination {
        order: 2;
        justify-content: center;
      }
      
      #expenseJumpToPage {
        order: 3;
        justify-content: center;
      }
    }
    body.dark-mode tr.details-row{
      background: var(--table-bg) !important;
    }
    body.dark-mode .details-container{
      background: transparent !important;
}

 </style>
  <?php include('header.php'); ?>
  <!-- Main Content -->
  <div class="content">
  <div class="contentinside">
    <div class="container">
      <div class="row">
        <!-- Operation Alert -->
        <div class="col-lg-12">
            <div id="showAlert"></div>
        </div>
        <!-- Operation Alert End-->
        <div class="col-lg-12">
          <!-- Controls Row: Search and Rows Selector -->
          <div class="leads-controls-row">
            <div class="leads-search-box">
              <i class="bi bi-search"></i>
              <input type="text" id="searchInput" placeholder="Search expenses...">
            </div>
            
            <div class="leads-toolbar-controls">
              <!-- Column Visibility Dropdown -->
              <div class="column-visibility-dropdown">
                <button class="column-visibility-btn" id="columnVisibilityBtn">
                  <i class="bi bi-layout-three-columns"></i>
                </button>
                <div class="column-visibility-menu" id="columnVisibilityMenu">
                  <div class="column-visibility-item">
                    <input type="checkbox" id="col-financial-year">
                    <label for="col-financial-year">Financial Year</label>
                  </div>
                  <div class="column-visibility-item">
                    <input type="checkbox" id="col-facebook">
                    <label for="col-facebook">Facebook Exp.</label>
                  </div>
                  <div class="column-visibility-item">
                    <input type="checkbox" id="col-google">
                    <label for="col-google">Google Exp.</label>
                  </div>
                  <div class="column-visibility-item">
                    <input type="checkbox" id="col-hr">
                    <label for="col-hr">HR Exp.</label>
                  </div>
                  <div class="column-visibility-item">
                    <input type="checkbox" id="col-it">
                    <label for="col-it">IT Exp.</label>
                  </div>
                  <div class="column-visibility-item">
                    <input type="checkbox" id="col-shi">
                    <label for="col-shi">SHI Exp.</label>
                  </div>
                  <div class="column-visibility-item">
                    <input type="checkbox" id="col-accounts">
                    <label for="col-accounts">Accounts Exp.</label>
                  </div>
                  <div class="column-visibility-item">
                    <input type="checkbox" id="col-others">
                    <label for="col-others">Others Exp.</label>
                  </div>
                </div>
              </div>
              
              <div class="rowSelector_wrap">
                <select id="rowSelector">
                  <option value="10" selected>10</option>
                  <option value="50">50</option>
                  <option value="100">100</option>
                  <option value="200">200</option>
                  <option value="300">300</option>
                </select>
              </div>
            </div>
          </div>
          
          <div class="maintablewrap">
            <table class="fold-table" cellspacing="0">
              <thead> 
                <tr>
                  <th>Financial Year</th>
                  <th>Facebook Exp.</th>
                  <th>Google Exp.</th>
                  <th>HR Exp.</th>
                  <th>IT Exp.</th>
                  <th>SHI Exp.</th>
                  <th>Accounts Exp.</th>
                  <th>Others Exp.</th>
                  <th class="chevron-col"></th>
                </tr>
              </thead>
              <tbody id="expensesdata">

              </tbody>
            </table>
          </div>
          
          <!-- Pagination Section -->
          <div class="row mt-3">
            <div class="col-lg-12">
              <div class="dt-layout-foot">
                <!-- showing entries info -->
                <div id="expenseRowInfo" style="font-size: 14px; font-weight: 500;">Showing 1 to 10 of 0 entries</div>
                
                <!-- pagination buttons -->
                <div class="pagination" id="expensePagination">
                  <button id="expensePrevButton" disabled style="border-radius: 6px; border: 1px solid #dee2e6; background: #fff; padding: 6px 14px; transition: all 0.2s ease; font-size: 14px; font-weight: 500; color: #495057; min-width: auto;">←</button>
                  <span id="expensePageNumbers" style="display: flex; align-items: center; gap: 5px;"></span>
                  <button id="expenseNextButton" disabled style="border-radius: 6px; border: 1px solid #dee2e6; background: #fff; padding: 6px 14px; transition: all 0.2s ease; font-size: 14px; font-weight: 500; color: #495057; min-width: auto;">→</button>
                </div>
                
                <!-- jump to page -->
                <div id="expenseJumpToPage" class="search" style="display: flex; align-items: center; gap: 5px; width: max-content;">
                  <input type="number" id="expenseJumpInput" class="searchTerm" placeholder="Page No." min="1" style="width: 100px; padding: 6px 10px; border: none; border-radius: 6px 0 0 6px; font-size: 14px;" />
                  <button id="expenseJumpButton" class="searchButton" style="padding: 6px 14px; background: #007bff; color: white; border: 1px solid #007bff; border-radius: 0 6px 6px 0; font-size: 14px; cursor: pointer; font-weight: 500;">Jump</button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  </div>
  <!--End Main Content -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script type="text/javascript" src="./assets/js/script.js"></script>
<!-- Add New Expenses Modal Start -->
<div class="modal fade" tabindex="-1" id="addNewUserModal">
              <div class="modal-dialog modal-lg modal-dialog-centered">
                  <div class="modal-content">
                      <div class="modal-header">
                          <h5 class="modal-title">Add New Expenses</h5>
                          <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close"></button>
                      </div>
                      <div class="modal-body">
                          <form id="add-user-form" name="myform" class="p-2" method="POST" enctype="multipart/form-data">
                          <div class="container">
                              <div class="row">
                                  <div class="col-md-4 mb-2">
                                    <div class="form-item">
                                      <label for="bdate">Expenses date</label>
                                      <input type="date" name="bdate" id="dateid" class="form-control form-control-lg"
                                          required>
                                      <div class="invalid-feedback">Date is required!</div>
                                  </div>
                              </div>
                                  <div class="col-md-4 mb-2">
                                  <div class="form-item">
                                      <label for="bmonth">Expenses month</label>
                                      <input type="month" name="bmonth" id="monthid" class="form-control form-control-lg"
                                          required>
                                      <div class="invalid-feedback">Month is required!</div>
                                  </div>
                              </div>
                                  <div class="col-md-4 mb-2">
                                  <div class="form-item">
                                      <label for="developer">Employee name</label>
                                      <input type="text" name="developer" class="form-control form-control-lg" required>
                                      <div class="invalid-feedback">Employee name is required!</div>
                                  </div>
                              </div>
                                  <div class="col-md-4 mb-2">
                                  <div class="form-item">
                                      <label for="ExpensesValue">Expenses value</label>
                                      <input type="number" name="cagreement" class="form-control form-control-lg" required>
                                      <div class="invalid-feedback">Agreement Value is required!</div>
                                  </div>
                              </div>
                                  <div class="col-md-4 mb-2">
                                  <div class="form-item">
                                      <label for="Project Name">Project name</label>
                                      <input type="text" name="tproject" class="form-control form-control-lg" required>
                                      <div class="invalid-feedback">Source Project is required!</div>
                                  </div>
                              </div>
                                  <div class="col-md-4 mb-2">
                                  <div class="form-item">
                                      <label for="DeveloperName">Developer Name</label>
                                      <input type="text" name="unitno" class="form-control form-control-lg" required>
                                      <div class="invalid-feedback">Source Project Name is required!</div>
                                  </div>
                              </div>
                              
                              <div class="col-md-4 mb-2">
                                  <div class="form-item">
                                    <label for="cname">Expenses name</label>
                                    <select name="cname" class="form-control form-control-lg">
                                        <option value="">Choose expense type</option>
                                        <option value="Facebook">Facebook</option>
                                        <option value="Google">Google</option>
                                        <option value="IT">Developer</option>
                                        <option value="SHI">SearchHomesIndia</option>
                                        <option value="Accounts">Accounts</option>
                                        <option value="HR">HR</option>
                                        <option value="Others">Others</option>
                                    </select>
                                    <div class="invalid-feedback">Expenses Source is required!</div>
                                </div>
                            </div>
                                  <div class="col-lg-6 mb-2">
                                  <div class="form-item">
                                      <label for="Assign User">Assign User</label>
                                      <select name="assign" id="assign" class="form-control form-control-lg">
                                          <option value="">Assign User</option>
                                          <?php foreach ($assignUsers as $user): ?>
                                              <option value="<?= $user['tablename']; ?>"><?= $user['username']; ?></option>
                                          <?php endforeach; ?>
                                      </select>
                                      <div class="invalid-feedback">Assign User is required!</div>
                                  </div>
                              </div>
                              <div class="col-md-6 mb-2">
                                  <!-- <div class="form-item"> -->
                                    <label for="Upload Invoice">Upload Invoice</label>
                                    <input type="file" name="bproject" class="form-control form-control-lg" required>
                                    <div class="invalid-feedback">Upload Invoice is required!</div>
                                <!-- </div> -->
                            </div>
                                  <div class="col-lg-12">
                                      <div class="Ubsubmitbtn">
                                          <input type="submit" name="add" value="Add Expenses"
                                              class="btn btn-primary btn-block" id="add-user-btn">
                                      </div>
                                  </div>
                              </div>
                          </div>
                          </form>
                      </div>
                  </div>
              </div>
            </div>
<!-- Add New User Modal End -->
<!-- Edit Expenses Modal Start -->
  <div class="modal fade" tabindex="-1" id="editUserModal">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Edit This Expenses</h5>
          <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <form id="edit-user-form" name="myform" class="p-2" novalidate>
            <input type="hidden" name="id" id="id">
            <div class="row mb-3 gx-3">
              <div class="col">
                <input type="date" name="bdate" id="bdate" class="form-control form-control-lg" required>
                <div class="invalid-feedback">Date is required!</div>
              </div>

              <div class="col">
                <input type="month" name="bmonth" id="bmonth" class="form-control form-control-lg" placeholder="Enter Last Name" required>
                <div class="invalid-feedback">Month is required!</div>
              </div>
            </div>

            <div class="row mb-3 gx-3">
              <div class="col">
                <input type="text" name="developer" id="developer" class="form-control form-control-lg" placeholder="Enter Employee Name" required>
                <div class="invalid-feedback">Employee name is required!</div>
              </div>

              <div class="col">
                <input type="number" name="cagreement" id="cagreement" class="form-control form-control-lg" placeholder="Enter Expenses Value" required>
                <div class="invalid-feedback">Agreement Value is required!</div>
              </div>
            </div>

            <div class="row mb-3 gx-3">
              <div class="col">
                <select name="cname" id="cname" class="form-control form-control-lg">
                  <option value="Facebook">Facebook</option>
                  <option value="Google">Google</option>
                  <option value="Developer">Developer</option>
                  <option value="SearchHomesIndia">SearchHomesIndia</option>
                  <option value="Accounts">Accounts</option>
                  <option value="HR">HR</option>
                  <option value="Others">Others</option>
                </select>
                <div class="invalid-feedback">Expenses Source is required!</div>
              </div>

              <div class="col">
                <input type="text" name="tproject" id="tproject" class="form-control form-control-lg" placeholder="Enter Source Project Name" required>
                <div class="invalid-feedback">Source Project is required!</div>
              </div>
            </div>

            <div class="row mb-3 gx-3">
              <div class="col">
                <input type="text" name="unitno" id="unitno" class="form-control form-control-lg" placeholder="Source Project Name" required>
                <div class="invalid-feedback">Source Project Name is required!</div>
              </div>

              <div class="col">
                <input type="file" name="bproject" id="bproject" class="form-control form-control-lg" placeholder="Upload Invoice" required>
                <div class="invalid-feedback">Upload Invoice is required!</div>
              </div>
            </div>

            <div class="mb-3">
              <input type="submit" value="Update Booking" class="btn btn-success btn-block btn-lg" id="edit-user-btn">
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
<!-- Edit User Modal End -->
<!-- Filter Rows Modal Start -->
<div class="modal fade" tabindex="-1" id="filterModal">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Filter Data</h5>
                        <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close"
                            id="closeFilter"></button>
                    </div>
                    <div class="modal-body">
                        <div class="container p-0">
                            <div class="row">
                                <!-- Filter inputs -->
                                <div class="col-md-4 mb-2">
                                    <div class="form-item">
                                    <label for="ID">ID</label>
                                    <input type="text" class="form-control form-control-lg" id="filterID">
                                </div>
                            </div>
                                <div class="col-md-4 mb-2">
                                    <div class="form-item">
                                    <label for="Expenses Date">Expenses Date</label>
                                    <input type="text" class="form-control form-control-lg " id="ExpensesDate">
                                </div>
                            </div>
                                <div class="col-md-4 mb-2">
                                    <div class="form-item">
                                    <label for="Expenses Month">Expenses Month</label>
                                    <input type="text" class="form-control form-control-lg " id="ExpensesMonth">
                                </div>
                            </div>
                                <div class="col-md-4 mb-2">
                                    <div class="form-item">
                                    <label for="UserName">User Name</label>
                                    <input type="text" class="form-control form-control-lg " id="UserName">
                                </div>
                            </div>
                                <div class="col-md-4 mb-2">
                                    <div class="form-item">
                                    <label for="ExpensesAmount">Expenses Amount</label>
                                    <input type="text" class="form-control form-control-lg " id="ExpensesAmount">
                                </div>
                            </div>
                                <div class="col-md-4 mb-2">
                                    <div class="form-item">
                                    <label for="Expenses Source">Expenses Source</label>
                                    <input type="text" class="form-control form-control-lg " id="ExpensesSource">
                                </div>
                            </div>
                                <div class="col-md-4 mb-2">
                                    <div class="form-item">
                                    <label for="Source Project">Source Project</label>
                                    <input type="text" class="form-control form-control-lg " id="SourceProject">
                                </div>
                            </div>
                                <div class="col-md-4 mb-2">
                                    <div class="form-item">
                                    <label for="Project Name">Project Name</label>
                                    <input type="text" class="form-control form-control-lg " id="ProjectName">
                                </div>
                            </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer" style="margin: 0 auto;">
                        <!-- Close Modal button -->
                        <button type="button" class="btn btn-secondary" data-dismiss="modal"
                            id="cancleFilter">Close</button>
                        <!-- Clear Filters button -->
                        <button type="button" class="btn btn-danger" id="clearFiltersBtn">Clear Filters</button>
                        <!-- Apply Filters button -->
                        <button type="button" class="btn btn-primary" id="applyFiltersBtn">Apply Filters</button>
                    </div>
                </div>
            </div>
          </div>
<!-- filter rows Modal End -->
  <script src="expensesmain.js"></script>
  <script>
    function applyFilters() {
      var filterInputs = [{
          id: "filterID",
          columnIndex: 0
        },
        {
          id: "ExpensesDate",
          columnIndex: 1
        },
        {
          id: "ExpensesMonth",
          columnIndex: 2
        },
        {
          id: "UserName",
          columnIndex: 3
        },
        {
          id: "ExpensesAmount",
          columnIndex: 4
        },
        {
          id: "ExpensesSource",
          columnIndex: 5
        },
        {
          id: "SourceProject",
          columnIndex: 6
        },
        {
          id: "ProjectName",
          columnIndex: 6
        },
      ];
      activeFilters = [];
      $("#expenses tr").each(function() {
        var row = $(this);
        var showRow = true;
        filterInputs.forEach(function(inputInfo) {
          var input = $("#" + inputInfo.id);
          var filterValue = input.val().toLowerCase();
          var cellValue = row.find("td:eq(" + inputInfo.columnIndex + ")").text().toLowerCase();
          if (cellValue.indexOf(filterValue) === -1) {
            showRow = false;
            return false;
          }
          if (filterValue.trim() !== "") {
            activeFilters.push(filterValue);
          }
        });
        if (showRow) {
          row.addClass("custom-filtered-row");
        } else {
          row.removeClass("custom-filtered-row");
        }
      });
      $("#expenses tr").hide();
      applyCustomFilter();
    };
    applyCustomFilter();

    function applyCustomFilter() {
      $(".custom-filtered-row").show();
    }
    $(".filterable .btn-filter1").click(function() {
      $("#filterModal").modal("show");
    });
    $("#applyFiltersBtn").click(function() {
      $("#filterModal").modal("hide");
      applyFilters();
    });
    $("#filterModal").on("hidden.bs.modal", function() {
      $(".filterable .filters input").val("");
      applyFilters();
    });
    $("#closeFilter").click(function() {
      applyFilters();
      $("#filterModal").modal("hide");
    });
    $("#cancleFilter").click(function() {
      applyFilters();
      $("#filterModal").modal("hide");
    });
    $(document).ready(function() {
      $("#clearFiltersBtn").click(function() {
        $("#filterID,#ExpensesDate,#ExpensesMonth,#UserName,#ExpensesAmount,#ExpensesSource,#SourceProject,#ProjectName").val("");
      });
    });
    $("#clearFiltersBtn").click(function() {
      applyFilters();
      $("#filterModal").modal("hide");
    });
  </script>
  <script>
    $(document).ready(function() {
      $('.scroll-left').on('click', function() {
        $('.table-container').animate({
          scrollLeft: '-=300'
        }, 'ease-in-out');
        $('.maintablewrap').animate({
          scrollLeft: '-=300'
        }, 'ease-in-out');
      });
      $('.scroll-right').on('click', function() {
        $('.table-container').animate({
          scrollLeft: '+=300'
        }, 'ease-in-out');
        $('.maintablewrap').animate({
          scrollLeft: '+=300'
        }, 'ease-in-out');
      });
    });
  </script>
  <script>
    document.addEventListener("DOMContentLoaded", function() {
    var loader = document.getElementById('loader');
    if (loader) {
      // Show loader initially
      loader.style.opacity = '1';
      loader.style.top = '0';
      loader.style.zIndex = '999'; // Set initial z-index to 999
      // Hide loader after 5 seconds with smooth transition
      setTimeout(function() {
        loader.style.transition = 'opacity 1s ease, top 1s ease, z-index 1s'; // Add z-index transition
        loader.style.opacity = '0';
        loader.style.top = '-100px'; // Move loader smoothly upward
        loader.style.zIndex = '0'; // Set z-index to 0 when hiding loader
      }, 3000);
    }
  });
  </script>
  <script>
    function debounce(e,t){let l;return function(...s){let n=this;clearTimeout(l),l=setTimeout(()=>e.apply(n,s),t)}}
    function searchTable(){
        var inputEl = document.getElementById("searchInput");
        var q = inputEl ? inputEl.value.toLowerCase() : "";
        var t=document.querySelectorAll("tbody");
        t.forEach(t=>{let l=t.querySelectorAll("tr");l.forEach(t=>{let l=t.innerText.toLowerCase();l.includes(q)?(t.classList.remove("tablehiddenrows"),t.style.display="",setTimeout(()=>{t.classList.remove("tablehiddenrows")},10)):(t.classList.add("tablehiddenrows"),setTimeout(()=>{t.style.display="none"},500))})})
    }
    var _searchInputEl = document.getElementById("searchInput");
    if (_searchInputEl) {
        _searchInputEl.addEventListener("input",debounce(searchTable,300));
    }
  </script>
  <script>
    // Global error handlers to surface JS/network errors into the page for debugging
    window.addEventListener('error', function (e) {
      try { document.getElementById('showAlert').innerText = 'JS error: ' + e.message; } catch (err) {}
      console.error('Global error:', e.error || e.message, e);
    });
    window.addEventListener('unhandledrejection', function (e) {
      try { document.getElementById('showAlert').innerText = 'Unhandled promise rejection: ' + (e.reason && e.reason.message ? e.reason.message : e.reason); } catch (err) {}
      console.error('Unhandled rejection:', e.reason);
    });

    document.addEventListener('DOMContentLoaded', function() {
      // If the expenses table hasn't been filled after scripts run, show a hint
      // Increased timeout to 3000ms to allow more time for data loading
      setTimeout(function() {
        var tbody = document.getElementById('expensesdata');
        var showAlert = document.getElementById('showAlert');
        // Only show error if tbody is empty AND no alert is already showing
        if (tbody && tbody.children.length === 0 && showAlert && !showAlert.innerText.trim()) {
          showAlert.innerHTML = '<div class="alert alert-warning">Loading expenses data is taking longer than expected. Please check your network connection or refresh the page.</div>';
        }
      }, 3000);
    });
  </script>
  <script>
    // Column Visibility Functionality
    $(document).ready(function() {
      const columnVisibilityBtn = document.getElementById('columnVisibilityBtn');
      const columnVisibilityMenu = document.getElementById('columnVisibilityMenu');
      
      // Toggle dropdown menu
      columnVisibilityBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        columnVisibilityMenu.classList.toggle('show');
      });
      
      // Close dropdown when clicking outside
      document.addEventListener('click', function(e) {
        if (!columnVisibilityMenu.contains(e.target) && !columnVisibilityBtn.contains(e.target)) {
          columnVisibilityMenu.classList.remove('show');
        }
      });
      
      // Prevent dropdown from closing when clicking inside menu
      columnVisibilityMenu.addEventListener('click', function(e) {
        e.stopPropagation();
      });
      
      // Column visibility configuration
      const columnConfig = [
        { id: 'col-financial-year', index: 1 },
        { id: 'col-facebook', index: 2 },
        { id: 'col-google', index: 3 },
        { id: 'col-hr', index: 4 },
        { id: 'col-it', index: 5 },
        { id: 'col-shi', index: 6 },
        { id: 'col-accounts', index: 7 },
        { id: 'col-others', index: 8 }
      ];
      
      // Function to toggle column visibility using classes
      function toggleColumn(columnIndex, isVisible) {
        const table = document.querySelector('.fold-table');
        const headerCells = table.querySelectorAll('thead th');
        const rows = table.querySelectorAll('tbody tr');
        
        if (isVisible) {
          // Show column - add column-show class with !important to override media queries
          if (headerCells[columnIndex - 1]) {
            headerCells[columnIndex - 1].classList.add('column-show');
            headerCells[columnIndex - 1].classList.remove('column-hidden');
          }
          rows.forEach(row => {
            const cells = row.querySelectorAll('td');
            if (cells[columnIndex - 1]) {
              cells[columnIndex - 1].classList.add('column-show');
              cells[columnIndex - 1].classList.remove('column-hidden');
            }
          });
        } else {
          // Hide column
          if (headerCells[columnIndex - 1]) {
            headerCells[columnIndex - 1].classList.remove('column-show');
            headerCells[columnIndex - 1].classList.add('column-hidden');
          }
          rows.forEach(row => {
            const cells = row.querySelectorAll('td');
            if (cells[columnIndex - 1]) {
              cells[columnIndex - 1].classList.remove('column-show');
              cells[columnIndex - 1].classList.add('column-hidden');
            }
          });
        }
      }
      
      // Add event listeners to checkboxes
      columnConfig.forEach(config => {
        const checkbox = document.getElementById(config.id);
        if (checkbox) {
          checkbox.addEventListener('change', function() {
            toggleColumn(config.index, this.checked);
          });
        }
      });
      
      // Function to sync checkboxes with actual column visibility
      function syncCheckboxesWithVisibility() {
        columnConfig.forEach(config => {
          const checkbox = document.getElementById(config.id);
          if (checkbox) {
            const table = document.querySelector('.fold-table');
            const headerCells = table.querySelectorAll('thead th');
            const headerCell = headerCells[config.index - 1];
            
            if (headerCell) {
              // Check if column is currently visible
              const computedStyle = window.getComputedStyle(headerCell);
              const isVisible = computedStyle.display !== 'none';
              // Set checkbox to match current visibility
              checkbox.checked = isVisible;
            }
          }
        });
      }
      
      // Initialize column visibility on page load with delay to ensure CSS is applied
      setTimeout(syncCheckboxesWithVisibility, 100);
      
      // Also sync on window resize to handle responsive changes
      let resizeTimeout;
      window.addEventListener('resize', function() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(syncCheckboxesWithVisibility, 100);
      });
    });
  </script>
  </body>
</html>