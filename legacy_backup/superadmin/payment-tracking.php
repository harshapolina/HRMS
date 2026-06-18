<?php include('htmlopen.php'); ?>
<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Lexend+Deca:wght@100..900&display=swap" rel="stylesheet">

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<link rel="stylesheet" href="./assets/css/unified_table_styles.css"/>
<style>
  /* Apply Lexend Deca font to all elements except FontAwesome icons */
  *:not(.fa):not(.fas):not(.far):not(.fab):not(.fal):not([class*="fa-"]) {
    font-family: "Lexend Deca", sans-serif !important;
    font-optical-sizing: auto !important;
  }
  
  body {
    font-family: "Lexend Deca", sans-serif !important;
    font-optical-sizing: auto !important;
  }
  
  /* Ensure FontAwesome icons use their proper font */
  .fa, .fas, .far, .fab, .fal, [class*="fa-"] {
    font-family: "Font Awesome 6 Free", "Font Awesome 6 Pro", "FontAwesome" !important;
    font-style: normal !important;
  }
  .side-menu li.sideactive4{
    background: var(--shicol);
    position: relative
  }
  .side-menu li.sideactive4 a{
    color: white
  }
  .addNewUserModal,.downloadCsvBtn{ 
    display: none;
  }
  
  /* Chevron Icon Styles */
  .chevron-column {
    width: 50px;
    text-align: center;
    padding: 8px !important;
  }
  
  .chevron-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 25px;
    height: 25px;
    background-color: #000;
    border-radius: 50%;
    cursor: pointer;
    transition: all 0.3s ease;
  }
  
  .chevron-icon:hover {
    background-color: #333;
    transform: scale(1.1);
  }
  
  .chevron-icon svg {
    width: 16px;
    height: 16px;
    fill: #fff;
    transition: transform 0.3s ease;
  }
  
  .chevron-icon.expanded svg {
    transform: rotate(90deg);
  }

  .unified-table thead th:last-child, .fold-table thead th:last-child, #myTable thead th:last-child, #example thead th:last-child {
    border-radius: 0 0px 0 0 !important;
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
    @media (max-width: 1024px) {
        .container, .container-lg, .container-md, .container-sm {
             max-width: 1000px !important;
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
  
  /* Hide Overall Build column on all screens */
  #example thead tr th:nth-child(3),
  #example tbody tr td:nth-child(3) {
    display: none;
  }
  
  /* Hide chevron icon column for screens greater than 1024px */
  @media (min-width: 1025px) {
    #example thead tr th:last-child,
    #example tbody tr td:last-child {
      display: none;
    }
    
    /* Fix border radius on last visible cell for large screens */
    #example tbody tr td:nth-child(8) {
      border-radius: 0 10px 10px 0 !important;
    }
  }
  
  /* Hide Remaining Payment column for screens less than 1024px */
  @media (max-width: 1024px) {
    #example thead tr th:nth-child(5),
    #example tbody tr td:nth-child(5) {
      display: none;
    }
    
    /* Hide Action column for screens less than 1024px */
    #example thead tr th:nth-child(8),
    #example tbody tr td:nth-child(8) {
      display: none;
    }
    
    /* Fix border radius on last visible cell for small screens (chevron column) */
    #example tbody tr.main-row td:last-child {
      border-radius: 0 10px 10px 0 !important;
    }
    
    /* Make rows clickable on mobile */
    #example tbody tr.main-row {
      cursor: pointer;
    }
  }
  
  /* Expandable detail row styles */
  .detail-row {
    display: none;
    background: #f8f9fa;
  }
  
  .detail-row.expanded {
    display: table-row;
  }
  
  .detail-row td {
    padding: 20px !important;
    border-radius: 0 0 10px 10px !important;
  }
  
  .detail-content {
    background: white;
    padding: 20px;
  }
  
  .detail-title {
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 15px;
    text-align: center;
    color: #333;
  }
  
  .detail-item {
    display: flex;
    padding: 5px 0;
    gap: 7px;
  }
  
  .detail-item:last-child {
    border-bottom: none;
  }
  
  .detail-label {
    display: flex;
    align-items: center;
    font-weight: 600;
    color: #6c757d;
    font-size: 12px;
    letter-spacing: 0.5px;
  }
  
  .detail-value {
    font-weight: 500;
    color: #333;
    text-align: right;
  }
  
  .detail-actions {
    max-width: max-content;
    padding-top: 5px;
  }
  
  .detail-actions .save-button {
    width: 100%;
    padding: 10px;
  }
  
  /* Hide detail rows on desktop */
  @media (min-width: 1025px) {
    .detail-row {
      display: none !important;
    }
  }
  
  /* Hide Advance Amount column for screens less than 768px */
  @media (max-width: 768px) {
    #example thead tr th:nth-child(4),
    #example tbody tr td:nth-child(4) {
      display: none;
    }
    
    /* Show advance amount field in detail row */
    .detail-advance-amount {
      display: flex;
    }
  }
  
  /* Hide advance amount in detail row on larger screens */
  @media (min-width: 769px) {
    .detail-advance-amount {
      display: none;
    }
  }
  
  /* Hide User Name and Booking Number columns for screens less than 425px */
  @media (max-width: 426px) {
    #example thead tr th:nth-child(6),
    #example tbody tr td:nth-child(6),
    #example thead tr th:nth-child(7),
    #example tbody tr td:nth-child(7) {
      display: none;
    }
    
    /* Show username and booking number in detail row */
    .detail-username,
    .detail-booking-number {
      display: flex;
    }
  }
  
  /* Hide Booking Number column for screens less than 575px */
  @media (min-width: 426px) and (max-width: 575px) {
    #example thead tr th:nth-child(7),
    #example tbody tr td:nth-child(7) {
      display: none;
    }
    
    /* Show booking number in detail row */
    .detail-booking-number {
      display: flex;
    }
  }
  
  /* Hide username and booking number in detail row on larger screens */
  @media (min-width: 576px) {
    .detail-username,
    .detail-booking-number {
      display: none;
    }
  }
  .table-container {
    max-height: 90vh !important;
    overflow-x: auto !important;
    overflow-y: auto !important;
    scrollbar-width: none !important;
  }
  
  .scrollable-table, .maintablewrap {
    max-height: 90vh !important;
    scrollbar-width: none !important;
  }
  .row {
    margin-right: 0px !important;
    margin-left: 0px !important;
  }
  @media screen and (max-width: 768px) {
      .content, body.sidebar-collapsed .content, body.sidebar-overlay .content {
          padding: 70px 0px 0px !important;
      }
  }

  /* Table cell padding with high specificity */
  .table-container .unified-table tbody td,
  .table-container .fold-table tbody tr td,
  .table-container #myTable tbody td,
  .table-container #example tbody td,
  .table-container table.stripe tbody td,
  .table-container table.display tbody td {
    padding: 10px !important;
  }
  .row>* {
    padding-right: 0px !important;
    padding-left: 0px !important; 
}
  
  /* Controls Row Styling */
  .leads-controls-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 5px;
    padding-bottom: 12px;
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
  
  /* Column visibility - use class-based hiding */
  #example th.column-hidden,
  #example td.column-hidden {
    display: none !important;
  }
  
  /* Force show column - overrides media queries */
  #example th.column-show,
  #example td.column-show {
    display: table-cell !important;
  }
  
  /* Pagination Styling */
  .dt-layout-foot {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 0;
    flex-wrap: wrap;
    gap: 15px;
  }
  
  .pagination {
    display: flex;
    align-items: center;
    gap: 5px;
  }
  
  .pagination button {
    border-radius: 6px;
    border: 1px solid #dee2e6;
    background: #fff;
    padding: 6px 14px;
    transition: all 0.2s ease;
    font-size: 14px;
    font-weight: 500;
    color: #495057;
    min-width: auto;
    cursor: pointer;
  }
  
  .pagination button:hover:not(:disabled) {
    background-color: #f8f9fa;
    border-color: #adb5bd;
  }
  
  .pagination button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
  }
  
  .pagination button.active {
    background-color: #007bff;
    border-color: #007bff;
    color: white;
  }
  
  /* Responsive controls row adjustments */
  @media (max-width: 768px) {
    .leads-controls-row {
      gap: 10px;
    }
    
    .leads-search-box {
      margin-right: 0;
    }
  }
  
  /* Pagination Footer Styling */
  .dt-layout-foot {
    background: transparent !important;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 15px 30px;
    margin: 0 -10px;
    backdrop-filter: none !important; 
    border-radius: none !important; 
    border: none !important;
  }
  
  .pagination {
    display: flex;
    align-items: center;
    gap: 5px;
    flex-wrap: wrap;
    justify-content: center;
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
  
  #paymentJumpButton {
    transition: background 0.2s ease !important;
  }
  
  #paymentJumpButton:hover {
    background: #0056b3 !important;
  }
  
  /* Mobile responsive pagination */
  @media (max-width: 768px) {
    .dt-layout-foot {
      flex-direction: column;
      gap: 10px;
      padding: 15px 5px;
    }
    
    #paymentRowInfo {
      text-align: center;
      order: 1;
      width: 100%;
    }
    
    .pagination {
      flex-direction: row !important;
      order: 2;
      justify-content: center;
      width: 100%;
    }
    
    #paymentJumpToPage {
      order: 3;
      justify-content: center;
      width: 100%;
      max-width: 200px;
    }
  }

  /* Fix header notification and profile dropdowns - position and z-index */
  /* .notif-content, 
  #notif-content_box {
    position: fixed !important;
    top: 60px !important;
    right: 150px !important;
    width: 320px !important;
    max-height: 450px !important;
    z-index: 1500 !important;
    background: white !important;
    border: 1px solid #dee2e6 !important;
    border-radius: 12px !important;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2) !important;
    overflow-y: auto !important;
  }
  
  .profile-content,
  #profile-content_box {
    position: fixed !important;
    top: 60px !important;
    right: 20px !important;
    width: 260px !important;
    min-height: 200px !important;
    z-index: 1500 !important;
    background: white !important;
    border: 1px solid #dee2e6 !important;
    border-radius: 12px !important;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2) !important;
    padding: 10px !important;
  } */
  
  .navbar-right-items {
    position: relative;
    z-index: 1000 !important;
  }
  
  #notificationBtn,
  #more_profile_icon {
    position: relative !important;
    z-index: 1000 !important;
    pointer-events: all !important;
    cursor: pointer !important;
  }
  body.dark-mode .detail-content{
    background: transparent !important;
  }
  body.dark-mode .rowSelector_wrap, .leadstatus_wrap{
    background: transparent !important;
  }
  body.dark-mode .rowSelector_wrap select, .leadstatus_wrap select{
    background: transparent !important;
    border: 1px solid #555 !important;
    color: #eee !important;
  }

  /* SweetAlert2 Toast Dark Mode Support */
  body.dark-mode .swal2-popup.swal2-toast {
    background-color: #1e293b !important;
    color: #e2e8f0 !important;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.5) !important;
  }

  body.dark-mode .swal2-popup.swal2-toast .swal2-title {
    color: #e2e8f0 !important;
  }

  body.dark-mode .swal2-popup.swal2-toast .swal2-icon.swal2-success {
    border-color: #22c55e !important;
  }

  body.dark-mode .swal2-popup.swal2-toast .swal2-icon.swal2-success [class^='swal2-success-line'] {
    background-color: #22c55e !important;
  }

  body.dark-mode .swal2-popup.swal2-toast .swal2-icon.swal2-success .swal2-success-ring {
    border-color: rgba(34, 197, 94, 0.3) !important;
  }

  body.dark-mode .swal2-timer-progress-bar {
    background: rgba(34, 197, 94, 0.8) !important;
  }

  /* Light mode toast styling */
  .swal2-popup.swal2-toast {
    background-color: #ffffff !important;
    color: #1e293b !important;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15) !important;
  }

</style>
<?php include('header.php'); ?>
<!-- Main Content -->
<div class="content">
<div class="contentinside">
  <div class="container">
    <div class="row">
      <div class="col-lg-12">
        <!-- Controls Row: Search, Column Visibility, and Rows Selector -->
        <div class="leads-controls-row">
          <div class="leads-search-box">
            <i class="bi bi-search"></i>
            <input type="text" id="paymentSearchInput" placeholder="Search payments...">
          </div>
          
          <div class="leads-toolbar-controls">
            <!-- Column Visibility Dropdown -->
            <div class="column-visibility-dropdown">
              <button class="column-visibility-btn" id="columnVisibilityBtn">
                <i class="bi bi-layout-three-columns"></i>
              </button>
              <div class="column-visibility-menu" id="columnVisibilityMenu">
                <div class="column-visibility-item">
                  <input type="checkbox" id="col-id" checked>
                  <label for="col-id">ID</label>
                </div>
                <div class="column-visibility-item">
                  <input type="checkbox" id="col-overall-earning" checked>
                  <label for="col-overall-earning">Overall Earning</label>
                </div>
                <div class="column-visibility-item">
                  <input type="checkbox" id="col-overall-build" checked>
                  <label for="col-overall-build">Overall Build</label>
                </div>
                <div class="column-visibility-item">
                  <input type="checkbox" id="col-advance-amount" checked>
                  <label for="col-advance-amount">Advance Amount</label>
                </div>
                <div class="column-visibility-item">
                  <input type="checkbox" id="col-remaining-payment" checked>
                  <label for="col-remaining-payment">Remaining Payment</label>
                </div>
                <div class="column-visibility-item">
                  <input type="checkbox" id="col-user-name" checked>
                  <label for="col-user-name">User Name</label>
                </div>
                <div class="column-visibility-item">
                  <input type="checkbox" id="col-booking-number" checked>
                  <label for="col-booking-number">Booking Number</label>
                </div>
                <div class="column-visibility-item">
                  <input type="checkbox" id="col-action" checked>
                  <label for="col-action">Action</label>
                </div>
              </div>
            </div>
            
            <div class="rowSelector_wrap">
              <select id="rowsPerPageSelector">
                <option value="10" selected>10</option>
                <option value="25">25</option>
                <option value="50">50</option>
                <option value="100">100</option>
                <option value="200">200</option>
              </select>
            </div>
          </div>
        </div>
        
        <div class="table-container">
          <table id="example" class="stripe row-border order-column display" cellspacing="0" style="width:100%">
            <thead>
              <tr>
                <th>ID</th>
                <th>Overall Earning</th>
                <th>Overall Build</th>
                <th>Advance Amount</th>
                <th>Remaining Payment</th>
                <th>User Name</th>
                <th>Booking Number</th>
                <th>Action</th>
                <th class="chevron-column"></th>
              </tr>
            </thead>
            <tbody id="paymenttable">
              <?php
              if (!empty($payment_data)) {
                foreach ($payment_data as $row) {
                  $rowId = $row['id'];
                  
                  // Main row
                  echo "<tr class='main-row' data-row-id='" . $rowId . "' onclick='toggleRowDetails(" . $rowId . ", event)'>";
                  
                  echo "<td>" . $row['id'] . "</td>";
                  echo "<td>" . $row['overall_earn'] . "</td>";
                  echo "<td>₹ " . $row['overall_paid'] . "</td>";

                  // Add an input field for editing the advance_pay
                  echo "<td><input type='text' id='edit_advance_pay_" . $rowId . "' value='" . $row['advance_pay'] . "' onclick='event.stopPropagation();'></td>";

                  echo "<td>₹ " . $row['remaning_payment'] . "</td>";
                  echo "<td>" . $row['user_name'] . "</td>";
                  echo "<td> " . $row['bookin_number'] . "</td>";

                  // Add an Edit button to submit changes
                  echo "<td onclick='event.stopPropagation();'><button class='save-button' onclick='event.stopPropagation(); editAdvancePay(" . $rowId . "); saveData();'>Save</button></td>";
                  
                  // Add chevron icon column at the end
                  echo "<td class='chevron-column'>";
                  echo "<div class='chevron-icon' id='chevron-" . $rowId . "'>";
                  echo "<svg viewBox='0 0 24 24' xmlns='http://www.w3.org/2000/svg'>";
                  echo "<path d='M9.29 6.71a.996.996 0 0 0 0 1.41L13.17 12l-3.88 3.88a.996.996 0 1 0 1.41 1.41l4.59-4.59a.996.996 0 0 0 0-1.41L10.7 6.7c-.38-.38-1.02-.38-1.41.01z'/>";
                  echo "</svg>";
                  echo "</div>";
                  echo "</td>";

                  echo "</tr>";
                  
                  // Detail row for mobile (expandable)
                  echo "<tr class='detail-row' id='detail-" . $rowId . "'>";
                  echo "<td colspan='9'>";
                  echo "<div class='detail-content'>";
                  echo "<div class='detail-title'>Payment Details</div>";
                  
                  // Show Advance Amount on screens < 768px
                  echo "<div class='detail-item detail-advance-amount'>";
                  echo "<span class='detail-label'>Advance Amount</span>";
                  echo "<span class='detail-value'><input type='text' id='edit_advance_pay_mobile_" . $rowId . "' value='" . $row['advance_pay'] . "' style='width: 100%; padding: 5px; border: 1px solid #ddd; border-radius: 4px;'></span>";
                  echo "</div>";
                  
                  // Show User Name on screens < 425px
                  echo "<div class='detail-item detail-username'>";
                  echo "<span class='detail-label'>User Name</span>";
                  echo "<span class='detail-value'>" . $row['user_name'] . "</span>";
                  echo "</div>";
                  
                  // Show Booking Number on screens < 425px
                  echo "<div class='detail-item detail-booking-number'>";
                  echo "<span class='detail-label'>Booking Number</span>";
                  echo "<span class='detail-value'>" . $row['bookin_number'] . "</span>";
                  echo "</div>";
                  
                  echo "<div class='detail-item'>";
                  echo "<span class='detail-label'>Overall Build</span>";
                  echo "<span class='detail-value'>₹ " . $row['overall_paid'] . "</span>";
                  echo "</div>";
                  
                  echo "<div class='detail-item'>";
                  echo "<span class='detail-label'>Remaining Payment</span>";
                  echo "<span class='detail-value'>₹ " . $row['remaning_payment'] . "</span>";
                  echo "</div>";
                  
                  echo "<div class='detail-actions'>";
                  echo "<button class='save-button' onclick='editAdvancePayMobile(" . $rowId . "); saveData();'>Save Changes</button>";
                  echo "</div>";
                  
                  echo "</div>";
                  echo "</td>";
                  echo "</tr>"; 
                }
              } else {
                echo "<tr><td colspan='9'>No data found</td></tr>";
              }
              ?>
            </tbody>
          </table>
        </div>
        
        <!-- Pagination Section -->
        <div class="row mt-3">
          <div class="col-lg-12">
            <div class="dt-layout-foot">
              <!-- showing entries info -->
              <div id="paymentRowInfo" style="font-size: 14px; font-weight: 500;">Showing 1 to 10 of 0 entries</div>
              
              <!-- pagination buttons -->
              <div class="pagination" id="paymentPagination">
                <button id="paymentPrevButton" disabled style="border-radius: 6px; border: 1px solid #dee2e6; background: #fff; padding: 6px 14px; transition: all 0.2s ease; font-size: 14px; font-weight: 500; color: #495057; min-width: auto;">←</button>
                <span id="paymentPageNumbers" style="display: flex; align-items: center; gap: 5px;"></span>
                <button id="paymentNextButton" disabled style="border-radius: 6px; border: 1px solid #dee2e6; background: #fff; padding: 6px 14px; transition: all 0.2s ease; font-size: 14px; font-weight: 500; color: #495057; min-width: auto;">→</button>
              </div>
              
              <!-- jump to page -->
              <div id="paymentJumpToPage" class="search" style="display: flex; align-items: center; gap: 5px; width: max-content;">
                <input type="number" id="paymentJumpInput" class="searchTerm" placeholder="Page No." min="1" style="width: 100px; padding: 6px 10px; border: none; border-radius: 6px 0 0 6px; font-size: 14px;" />
                <button id="paymentJumpButton" class="searchButton" style="padding: 6px 14px; background: #007bff; color: white; border: 1px solid #007bff; border-radius: 0 6px 6px 0; font-size: 14px; cursor: pointer; font-weight: 500;">Jump</button>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Notification handled by SweetAlert2 toast -->
      </div>
    </div>
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
                            <div class="container">
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
                                        <label for="Overallearn">Overall earn</label>
                                        <input type="text" class="form-control form-control-lg" id="Overallearn">
                                    </div>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <div class="form-item">
                                        <label for="Overallpaid">Overall paid</label>
                                        <input type="text" class="form-control form-control-lg" id="Overallpaid">
                                    </div>
                                </div>
                                    <div class="col-md-4 mb-2">
                                    <div class="form-item">
                                        <label for="Advancepay">Advance pay</label>
                                        <input type="text" class="form-control form-control-lg" id="Advancepay">
                                    </div>
                                </div>
                                    <div class="col-md-4 mb-2">
                                    <div class="form-item">
                                        <label for="Remaningpayment">Remaning payment</label>
                                        <input type="text" class="form-control form-control-lg" id="Remaningpay">
                                    </div>
                                </div>
                                    <div class="col-md-4 mb-2">
                                    <div class="form-item">
                                        <label for="Username">User name</label>
                                        <input type="text" class="form-control form-control-lg" id="Username">
                                    </div>
                                </div>
                                    <div class="col-md-12 mb-2">
                                    <div class="form-item">
                                        <label for="Bookingnumber">Booking number</label>
                                        <input type="text" class="form-control form-control-lg" id="Bookingno">
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
  </div>
</div>
</div>
<!--End Main Content -->
<script>
  function applyFilters() {
    var filterInputs = [
        { id: "filterID", columnIndex: 0 },
        { id: "Overallearn", columnIndex: 1 },
        { id: "Overallpaid", columnIndex: 2 },
        { id: "Advancepay", columnIndex: 3 },
        { id: "Remaningpay", columnIndex: 4 },
        { id: "Username", columnIndex: 5 },
        { id: "Bookingno", columnIndex: 6 }
    ];
    var activeFilters = [];
    $("#paymenttable tr").each(function() {
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
    $("#paymenttable tr").hide();
    applyCustomFilter();
}

function applyCustomFilter() {
    $(".custom-filtered-row").show();
}

$(document).ready(function() {
    $(".filterable .btn-filter1").click(function() {
        $("#filterModal").modal("show");
    });

    $("#applyFiltersBtn").click(function() {
        applyFilters();
        $("#closeFilter").click(); // Simulate a click on the close button
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

    $("#clearFiltersBtn").click(function() {
        $("#filterID,#Overallearn,#Overallpaid,#Advancepay,#Remaningpay,#Username,#Bookingno").val("");
        applyFilters();
        $("#closeFilter").click(); // Simulate a click on the close button
    });
});
</script>
<script>
  function editAdvancePay(id) {
    var newAdvancePay = document.getElementById('edit_advance_pay_' + id).value;

    // Assuming you have an AJAX function to send the updated value to the server
    // You need to implement the updateAdvancePay function in your action.php file
    updateAdvancePay(id, newAdvancePay);
  }
  
  function editAdvancePayMobile(id) {
    // Get value from mobile input field
    var mobileInput = document.getElementById('edit_advance_pay_mobile_' + id);
    var newAdvancePay = mobileInput ? mobileInput.value : document.getElementById('edit_advance_pay_' + id).value;
    
    // Sync with desktop input if it exists
    var desktopInput = document.getElementById('edit_advance_pay_' + id);
    if (desktopInput) {
      desktopInput.value = newAdvancePay;
    }
    
    // Update the database
    updateAdvancePay(id, newAdvancePay);
  }


  function updateAdvancePay(id, newAdvancePay) {
    // Use AJAX to send the updated value to the server
    // You can use jQuery.ajax or fetch API for this purpose
    // Send an AJAX request to update the "advance_pay" in the database
    // Example using jQuery.ajax:
    $.ajax({
      type: 'POST',
      url: 'action.php',
      data: {
        action: 'update_advance_pay',
        id: id,
        newAdvancePay: newAdvancePay
      },
      success: function(response) {
        // Handle the response from the server (e.g., show a success message)
        console.log(response);
      },
      error: function(error) {
        // Handle the error (e.g., show an error message)
        console.error(error);
      }
    });
  }
</script>
<script>
    function saveData() {
      // Detect dark mode
      const isDarkMode = document.body.classList.contains('dark-mode');
      
      // Show SweetAlert2 toast notification with dark mode support
      Swal.fire({
        toast: true,
        position: 'bottom',
        icon: 'success',
        title: 'Data saved successfully!',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        background: isDarkMode ? '#1e293b' : '#ffffff',
        color: isDarkMode ? '#e2e8f0' : '#1e293b',
        iconColor: '#22c55e',
        customClass: {
          popup: 'colored-toast'
        }
      });
    }
  </script>
  
  <script>
    // ===================
    // PAYMENT TRACKER CONTROLS
    // ===================
    $(document).ready(function() {
      let currentPage = 1;
      let rowsPerPage = 10;
      let allRows = [];
      let filteredRows = [];
      
      // ===================
      // PAGINATION FUNCTIONALITY
      // ===================
      function initPagination() {
        // Get all main-row elements (not detail-row)
        allRows = $('#paymenttable tr.main-row').toArray();
        filteredRows = allRows.slice(); // Copy of all rows
        updatePagination();
      }
      
      function updatePagination() {
        const totalRows = filteredRows.length;
        const totalPages = Math.ceil(totalRows / rowsPerPage);
        
        // Update row info text
        const startRow = totalRows === 0 ? 0 : (currentPage - 1) * rowsPerPage + 1;
        const endRow = Math.min(currentPage * rowsPerPage, totalRows);
        $('#paymentRowInfo').text(`Showing ${startRow} to ${endRow} of ${totalRows} entries`);
        
        // Show/hide rows based on current page
        allRows.forEach((row) => {
          $(row).hide();
          // Also hide any expanded details for hidden rows
          $(row).next('.detail-row').removeClass('expanded').hide();
          $(row).find('.chevron-icon').removeClass('expanded');
        });
        
        filteredRows.forEach((row, index) => {
          const rowPage = Math.floor(index / rowsPerPage) + 1;
          if (rowPage === currentPage) {
            $(row).show();
          }
        });
        
        // Update pagination buttons
        $('#paymentPrevButton').prop('disabled', currentPage === 1);
        $('#paymentNextButton').prop('disabled', currentPage === totalPages || totalPages === 0);
        
        // Generate page numbers
        generatePageNumbers(currentPage, totalPages);
      }
      
      function generatePageNumbers(current, total) {
        const $pageNumbers = $('#paymentPageNumbers');
        $pageNumbers.empty();
        
        if (total === 0) return;
        
        // Show max 5 page numbers with current page in the middle when possible
        let startPage = Math.max(1, current - 2);
        let endPage = Math.min(total, startPage + 4);
        
        // Adjust if we're near the end
        if (endPage - startPage < 4) {
          startPage = Math.max(1, endPage - 4);
        }
        
        for (let i = startPage; i <= endPage; i++) {
          const btn = $(`<button>${i}</button>`);
          if (i === current) {
            btn.addClass('active');
          }
          btn.on('click', function() {
            currentPage = i;
            updatePagination();
          });
          $pageNumbers.append(btn);
        }
      }
      
      // Previous button
      $('#paymentPrevButton').on('click', function() {
        if (currentPage > 1) {
          currentPage--;
          updatePagination();
        }
      });
      
      // Next button
      $('#paymentNextButton').on('click', function() {
        const totalPages = Math.ceil(filteredRows.length / rowsPerPage);
        if (currentPage < totalPages) {
          currentPage++;
          updatePagination();
        }
      });
      
      // Rows per page selector
      $('#rowsPerPageSelector').on('change', function() {
        rowsPerPage = parseInt($(this).val());
        currentPage = 1; // Reset to first page
        updatePagination();
      });
      
      // Jump to page functionality
      $('#paymentJumpButton').on('click', function() {
        const pageNumber = parseInt($('#paymentJumpInput').val());
        const totalPages = Math.ceil(filteredRows.length / rowsPerPage);
        
        if (pageNumber && pageNumber >= 1 && pageNumber <= totalPages) {
          currentPage = pageNumber;
          updatePagination();
          $('#paymentJumpInput').val(''); // Clear input after jump
        }
      });
      
      // Allow Enter key to trigger jump
      $('#paymentJumpInput').on('keypress', function(e) {
        if (e.which === 13) {
          $('#paymentJumpButton').click();
        }
      });
      
      // ===================
      // SEARCH FUNCTIONALITY
      // ===================
      $('#paymentSearchInput').on('keyup', function() {
        const searchTerm = $(this).val().toLowerCase();
        
        if (searchTerm === '') {
          // Show all rows if search is empty
          filteredRows = allRows.slice();
        } else {
          // Filter rows based on search term
          filteredRows = allRows.filter(function(row) {
            const $row = $(row);
            const rowText = $row.text().toLowerCase();
            return rowText.indexOf(searchTerm) !== -1;
          });
        }
        
        // Reset to first page after search
        currentPage = 1;
        updatePagination();
      });
      
      // ===================
      // COLUMN VISIBILITY
      // ===================
      const columnMapping = {
        'col-id': 1,
        'col-overall-earning': 2,
        'col-overall-build': 3,
        'col-advance-amount': 4,
        'col-remaining-payment': 5,
        'col-user-name': 6,
        'col-booking-number': 7,
        'col-action': 8
      };
      
      // Toggle column visibility dropdown
      $('#columnVisibilityBtn').on('click', function(e) {
        e.stopPropagation();
        $('#columnVisibilityMenu').toggleClass('show');
      });
      
      // Close dropdown when clicking outside
      $(document).on('click', function(e) {
        if (!$(e.target).closest('.column-visibility-dropdown').length) {
          $('#columnVisibilityMenu').removeClass('show');
        }
      });
      
      // Handle column visibility checkbox changes
      $('.column-visibility-item input[type="checkbox"]').on('change', function() {
        const checkboxId = $(this).attr('id');
        const columnIndex = columnMapping[checkboxId];
        const isChecked = $(this).is(':checked');
        
        if (columnIndex) {
          const thSelector = `#example thead tr th:nth-child(${columnIndex})`;
          const tdSelector = `#example tbody tr td:nth-child(${columnIndex})`;
          
          if (isChecked) {
            // Show column - add column-show class to override media queries
            $(thSelector).removeClass('column-hidden').addClass('column-show');
            $(tdSelector).removeClass('column-hidden').addClass('column-show');
          } else {
            // Hide column - add column-hidden class
            $(thSelector).removeClass('column-show').addClass('column-hidden');
            $(tdSelector).removeClass('column-show').addClass('column-hidden');
          }
        }

        // Keep Action column hidden in tablet range to avoid horizontal scroll.
        enforceActionColumnForTabletRange();
      });

      function enforceActionColumnForTabletRange() {
        const screenWidth = window.innerWidth;
        const isTabletRange = screenWidth >= 1024 && screenWidth <= 1255;
        const actionThSelector = '#example thead tr th:nth-child(8)';
        const actionTdSelector = '#example tbody tr td:nth-child(8)';

        if (isTabletRange) {
          $(actionThSelector).removeClass('column-show').addClass('column-hidden');
          $(actionTdSelector).removeClass('column-show').addClass('column-hidden');
          $('#col-action').prop('checked', false);
        }
      }
      
      // Function to sync checkboxes with actual column visibility
      function syncCheckboxesWithVisibility() {
        const screenWidth = window.innerWidth;
        
        // ID (column 1) - always visible
        $('#col-id').prop('checked', true);
        
        // Overall Earning (column 2) - always visible
        $('#col-overall-earning').prop('checked', true);
        
        // Overall Build (column 3) - always hidden by CSS (line 156-159)
        $('#col-overall-build').prop('checked', false);
        
        // Advance Amount (column 4) - hidden on screens < 768px
        if (screenWidth < 768) {
          $('#col-advance-amount').prop('checked', false);
        } else {
          $('#col-advance-amount').prop('checked', true);
        }
        
        // Remaining Payment (column 5) - hidden on screens < 1024px
        if (screenWidth < 1024) {
          $('#col-remaining-payment').prop('checked', false);
        } else {
          $('#col-remaining-payment').prop('checked', true);
        }
        
        // User Name (column 6) - hidden on screens < 426px
        if (screenWidth < 426) {
          $('#col-user-name').prop('checked', false);
        } else {
          $('#col-user-name').prop('checked', true);
        }
        
        // Booking Number (column 7) - hidden on screens < 575px
        if (screenWidth < 575) {
          $('#col-booking-number').prop('checked', false);
        } else {
          $('#col-booking-number').prop('checked', true);
        }
        
        // Action (column 8) - hidden on screens < 1024px and 1024px-1255px
        if (screenWidth <= 1255) {
          $('#col-action').prop('checked', false);
        } else {
          $('#col-action').prop('checked', true);
        }

        enforceActionColumnForTabletRange();
      }
      
      // Initialize checkboxes on page load
      syncCheckboxesWithVisibility();
      
      // Update checkboxes on window resize
      $(window).on('resize', function() {
        syncCheckboxesWithVisibility();
        enforceActionColumnForTabletRange();
      });
      
      // Initialize pagination on page load
      initPagination();
    });
  </script>
  
  <script>
    // Row expansion functionality for mobile screens
    function toggleRowDetails(rowId, event) {
      // Only activate on mobile screens (< 1024px)
      if (window.innerWidth >= 1025) {
        return;
      }
      
      // Prevent event bubbling
      if (event) {
        event.stopPropagation();
      }
      
      var detailRow = $('#detail-' + rowId);
      var chevron = $('#chevron-' + rowId);
      
      if (detailRow.length && chevron.length) {
        // Check if currently expanded
        if (detailRow.hasClass('expanded')) {
          // Collapse: remove class and hide
          detailRow.removeClass('expanded').hide();
          chevron.removeClass('expanded');
        } else {
          // Expand: add class and show
          detailRow.addClass('expanded').show();
          chevron.addClass('expanded');
        }
      }
    }
  </script>

<script>
  // Fix for notification and profile dropdowns
  $(document).ready(function() {
    const notifBtn = $('#notificationBtn');
    const notifContent = $('#notif-content_box');
    const profileIcon = $('#more_profile_icon');
    const profileContent = $('#profile-content_box');
    
    // Notification button click
    if (notifBtn.length && notifContent.length) {
      notifBtn.on('click', function(e) {
        e.stopPropagation();
        const isVisible = notifContent.css('display') === 'block';
        notifContent.css('display', isVisible ? 'none' : 'block');
        profileContent.css('display', 'none');
      });
    }
    
    // Profile icon click
    if (profileIcon.length && profileContent.length) {
      profileIcon.on('click', function(e) {
        e.stopPropagation();
        const isVisible = profileContent.css('display') === 'block';
        profileContent.css('display', isVisible ? 'none' : 'block');
        notifContent.css('display', 'none');
      });
    }
    
    // Close buttons
    $('.closebtn').on('click', function() {
      notifContent.css('display', 'none');
    });
    
    $('.closebtn1').on('click', function() {
      profileContent.css('display', 'none');
    });
    
    // Click outside to close
    $(document).on('click', function(e) {
      if (!$(e.target).closest('#notificationBtn, #notif-content_box').length) {
        notifContent.css('display', 'none');
      }
      if (!$(e.target).closest('#more_profile_icon, #profile-content_box').length) {
        profileContent.css('display', 'none');
      }
    });
  });
</script>
<?php include('htmlclose.php'); ?>
<?php
// Close the connection
$conn->close();
?>