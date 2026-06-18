<?php include('htmlopen.php'); ?>
<!-- Include CSS -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />
<link href="https://cdnjs.cloudflare.com/ajax/libs/select2-bootstrap-5-theme/1.3.0/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

<!-- Google Fonts - Lexend Deca -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Lexend+Deca:wght@100..900&display=swap" rel="stylesheet">

<!-- Include JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>

<link rel="stylesheet" href="./assets/css/unified_table_styles.css"/>
<style>
  /* Apply Lexend Deca font to entire incentive tracker module */
  .content,
  .content *:not([class*="fa-"]):not(i):not(.fas):not(.far):not(.fab):not(.fal):not(.bi),
  #example,
  #example *:not([class*="fa-"]):not(i):not(.fas):not(.far):not(.fab):not(.fal):not(.bi),
  .modal,
  .modal *:not([class*="fa-"]):not(i):not(.fas):not(.far):not(.fab):not(.fal):not(.bi),
  input,
  button,
  label,
  th,
  td {
    font-family: "Lexend Deca", sans-serif !important;
    font-optical-sizing: auto !important;
  }
  
  /* Ensure font-style normal doesn't affect icons */
  .content *:not([class*="fa-"]):not(i):not(.fas):not(.far):not(.fab):not(.fal):not(.bi),
  #example *:not([class*="fa-"]):not(i):not(.fas):not(.far):not(.fab):not(.fal):not(.bi),
  .modal *:not([class*="fa-"]):not(i):not(.fas):not(.far):not(.fab):not(.fal):not(.bi) {
    font-style: normal !important;
  }
  
  /* Fix header notification and profile dropdowns - position and z-index only */
  
  /* .profile-content,
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
  
  
  
  .side-menu li.sideactive3{
    background: var(--shicol);
    position: relative
  }
  .side-menu li.sideactive3 a{
    color: white
  }
  .addNewUserModal,.downloadCsvBtn{ 
    display: none;
  }
  
  /* Add vertical scroll to table container */
  .table-container {
    scrollbar-width: none;
    max-height: calc(100vh - 200px);
    overflow-y: auto;
    overflow-x: auto;
    position: relative;
    z-index: 1;
  }
  .container{
    padding-left: 0px !important;
    padding-right: 0px !important;
  }
  
  /* Equal column widths */
  #example {
    width: 100%;
  }
  
  /* Set specific column widths for equal spacing */
  #example thead th:nth-child(1),
  #example tbody td:nth-child(1) {
    width: 40px;
  }
  
  /* #example thead th:nth-child(2),
  #example tbody td:nth-child(2) {
    width: auto;
  }
  
  #example thead th:nth-child(3),
  #example tbody td:nth-child(3) {
    width: 22%;
  }
  
  #example thead th:nth-child(4),
  #example tbody td:nth-child(4) {
    width: 150px;
  }
  
  #example thead th:nth-child(5),
  #example tbody td:nth-child(5) {
    width: 22%;
  }
  
  #example thead th:nth-child(6),
  #example tbody td:nth-child(6) {
    width: 150px;
  }
  
  #example thead th:nth-child(7),
  #example tbody td:nth-child(7) {
    width: 22%;
  }
  
  #example thead th:nth-child(8),
  #example tbody td:nth-child(8) {
    width: 140px;
  } */
  
  #example thead th:nth-child(9),
  #example tbody td:nth-child(9) {
    width: 50px;
  }
  
  /* Sticky header */
  .table-container table thead {
    position: sticky;
    top: 0;
    z-index: 5;
    background-color: #fff;
  }
  
  /* Smooth scrolling */
  .table-container::-webkit-scrollbar {
    width: 8px;
    height: 8px;
  }
  
  .table-container::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
  }
  
  .table-container::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 4px;
  }
  
  .table-container::-webkit-scrollbar-thumb:hover {
    background: #555;
  }
  
  /* Table cell padding with high specificity */
  #example.unified-table tbody td, 
  table#example tbody td, 
  .dt-layout-row-mid #example tbody td {
    padding: 14px !important;
  }
  
  /* Chevron column styling */
  .chevron-cell {
    text-align: center;
    vertical-align: middle;
    cursor: pointer;
    padding: 8px !important;
    width: 60px;
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
  
  /* Rotate chevron when row is expanded */
  tr.expanded .chevron-icon i {
    transform: rotate(90deg);
  }
  
  /* Make table rows clickable */
  #example tbody tr {
    cursor: pointer;
  }
  
  /* Details row styling */
  tr.details-row {
    background-color: #f8f9fa !important;
    cursor: default !important;
  }
  
  tr.details-row td {
    padding: 0 !important;
    border: none !important;
  }
  
  .details-container {
    padding: 8px;
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
  
  /* Header for chevron column */
  #example thead th:last-child {
    border-radius: 0px !important;
    width: 60px;
    text-align: center;
  }
  tbody, td, tfoot, th, thead, tr {
    max-width: 50px !Important;
}
  
  /* Hide Remaining Amount column on screens less than 1024px */
  /* @media (max-width: 1024px) {
    #example thead th:nth-child(5),
    #example tbody td:nth-child(5) {
      display: none;
    }
  } */
  
  /* Hide Generated Revenue and User Name columns on screens less than 768px */
  @media (max-width: 768px) {
    #example thead th:nth-child(5),
    #example tbody td:nth-child(5) {
      display: none;
    }
    /* Hide Generated Revenue column (3rd column) */
    #example thead th:nth-child(3),
    #example tbody td:nth-child(3) {
      display: none;
    }
  }
  @media (max-width: 490px){
    /* Hide User Name column (7th column) */
    #example thead th:nth-child(7),
    #example tbody td:nth-child(7) {
      display: none;
    }
  }
  
  /* Hide specific columns on screens larger than 768px */
  @media (max-width: 2560px) {
    /* Hide Booking Number column (8th column) */
    #example thead th:nth-child(8),
    #example tbody td:nth-child(8) {
      display: none;
    }
    
    /* Hide Recent Payment column (4th column) */
    #example thead th:nth-child(4),
    #example tbody td:nth-child(4) {
      display: none;
    }
    
    /* Hide Build Amount column (6th column) */
    #example thead th:nth-child(6),
    #example tbody td:nth-child(6) {
      display: none;
    }
    .container, .container-md, .container-sm {
        max-width: 100% !important;
    }
  }
  
  /* Reduce row gap on mobile devices for incentive tracking table only */
  @media (max-width: 768px) {
    #example {
      border-spacing: 0 4px !important;
    }
    
    /* Override the unified table styles margin-bottom with stronger specificity */
    #example tbody tr,
    table#example tbody tr,
    #example.stripe tbody tr,
    #example.display tbody tr {
      margin-bottom: 4px !important;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06) !important;
    }
    
    /* Adjust column widths for mobile to move USER NAME to the left */
    #example thead th:nth-child(1),
    #example tbody td:nth-child(1) {
      width: 100px;
      min-width: 100px !important;
    }
    
    /* #example thead th:nth-child(2),
    #example tbody td:nth-child(2) {
      width: 150px !important;
      min-width: 150px !important;
    }
    
    #example thead th:nth-child(7),
    #example tbody td:nth-child(7) {
      width: 150px !important;
      max-width: none !important;
    } */
    
    #example thead th:nth-child(9),
    #example tbody td:nth-child(9) {
      width: 50px !important;
    }
  }
  
  /* Pagination Footer Styling */
  .dt-layout-foot {
    background: transparent !important;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 5px;
    padding: 10px 0;
  }
  
  .pagination {
    display: flex;
    align-items: center;
    gap: 8px;
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
  
  #incentiveJumpButton:hover {
    background: #0056b3 !important;
  }
  
  /* Mobile responsive pagination */
  @media (max-width: 768px) {
    .dt-layout-foot {
      flex-direction: column;
      gap: 10px;
    }
    
    #incentiveRowInfo {
      text-align: center;
      order: 1;
    }
    
    .pagination {
      order: 2;
      justify-content: center;
    }
    
    #incentiveJumpToPage {
      order: 3;
      justify-content: center;
    }
  }
  
  /* Controls Toolbar Styling */
  .toolbar-icon-btn:hover {
    background: #f8f9fa !important;
    border-color: #adb5bd !important;
  }
  
  #incentiveSearchInput:focus {
    outline: none;
    border-color: #007bff;
    box-shadow: 0 0 0 0.2rem rgba(0,123,255,0.25);
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
  
  @media (max-width: 768px) {
    
    .incentive-search-box {
      max-width: 100% !important;
    }
    
    .incentive-toolbar-controls {
      justify-content: center;
    }
  }
</style>
<?php include('header.php'); ?>
<!-- Main Content -->
<div class="content">
<div class="contentinside">
  <div class="container">
    <div class="row">
      <div class="col-lg-12">
        <!-- Controls Row -->
        <div class="incentive-controls-row" style="display: flex; align-items: center; gap: 5px; margin-bottom: 2px; flex-wrap: nowrap;">
          <!-- Search Box -->
          <div class="incentive-search-box" style="flex: 1; min-width: 200px; position: relative;">
            <i class="bi bi-search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #6c757d; font-size: 16px;"></i>
            <input type="text" id="incentiveSearchInput" placeholder="Search incentives..." style="width: 100%; padding: 10px 12px 10px 38px; border: 1px solid #dee2e6; border-radius: 8px; font-size: 14px; transition: all 0.2s ease;">
          </div>
          
          <!-- Toolbar Controls -->
          <div class="incentive-toolbar-controls" style="display: flex; align-items: center; gap: 10px;">
            <!-- Rows Per Page Selector -->
            <div class="rowSelector_wrap" style="position: relative;">
              <select id="rowsPerPageSelector" style="padding: 10px 32px 10px 12px; border: 1px solid #dee2e6; border-radius: 8px; background: #fff; font-size: 14px; font-weight: 500; color: #495057; cursor: pointer; appearance: none;">
                <option value="10">10</option>
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
                <th>Month</th>
                <th>Generated Revenue</th>
                <th>Recent Payment</th>
                <th>Remaining Amount</th>
                <th>Build Amount</th>
                <th>User Name</th>
                <th>Booking Number</th>
                <th></th>
              </tr>
            </thead>
            <tbody id="trackings">
            <?php
            // Check if there is any data in the tracking table
            if (!empty($tracking_data)) {
                foreach ($tracking_data as $row) {
                    echo "<tr class='data-row' 
                            data-id='" . htmlspecialchars($row['id']) . "' 
                            data-month='" . htmlspecialchars($row['month']) . "' 
                            data-gen-revenue='" . htmlspecialchars($row['gen_revenue']) . "' 
                            data-recent-pay='" . htmlspecialchars($row['recent_pay']) . "' 
                            data-remaining-amt='" . htmlspecialchars($row['remaning_amt']) . "' 
                            data-build-amt='" . htmlspecialchars($row['send_amt']) . "' 
                            data-user-name='" . htmlspecialchars($row['user_name']) . "' 
                            data-booking-number='" . htmlspecialchars($row['bookin_number']) . "'>";
                    echo "<td>" . $row['id'] . "</td>";
                    echo "<td>" . $row['month'] . "</td>";
                    echo "<td>₹ " . $row['gen_revenue'] . "</td>";
                    echo "<td>₹ " . $row['recent_pay'] . "</td>";
                    echo "<td>₹ " . $row['remaning_amt'] . "</td>";
                    echo "<td>₹ " . $row['send_amt'] . "</td>";
                    echo "<td>" . $row['user_name'] . "</td>";
                    echo "<td>" . $row['bookin_number'] . "</td>";
                    echo "<td class='chevron-cell'><div class='chevron-icon'><i class='fas fa-chevron-right'></i></div></td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='8'>No data found</td></tr>";
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
              <div id="incentiveRowInfo" style="font-size: 14px; font-weight: 500;">Showing 1 to 10 of 0 entries</div>
              
              <!-- pagination buttons -->
              <div class="pagination" id="incentivePagination">
                <button id="incentivePrevButton" disabled style="border-radius: 6px; border: 1px solid #dee2e6; background: #fff; padding: 6px 14px; transition: all 0.2s ease; font-size: 14px; font-weight: 500; color: #495057; min-width: auto;">←</button>
                <span id="incentivePageNumbers" style="display: flex; align-items: center; gap: 5px;"></span>
                <button id="incentiveNextButton" disabled style="border-radius: 6px; border: 1px solid #dee2e6; background: #fff; padding: 6px 14px; transition: all 0.2s ease; font-size: 14px; font-weight: 500; color: #495057; min-width: auto;">→</button>
              </div>
              
              <!-- jump to page -->
              <div id="incentiveJumpToPage" class="search" style="display: flex; align-items: center; gap: 5px; width: max-content;">
                <input type="number" id="incentiveJumpInput" class="searchTerm" placeholder="Page No." min="1" style="width: 100px; padding: 6px 10px; border: none; border-radius: 6px 0 0 6px; font-size: 14px;" />
                <button id="incentiveJumpButton" class="searchButton" style="padding: 6px 14px; background: #007bff; color: white; border: 1px solid #007bff; border-radius: 0 6px 6px 0; font-size: 14px; cursor: pointer; font-weight: 500;">Jump</button>
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
<script>
  // Handle row click to expand/collapse details
  $(document).ready(function() {
    // Toggle details when clicking on data rows
    $(document).on('click', '.data-row', function(e) {
      const $clickedRow = $(this);
      const $existingDetails = $clickedRow.next('.details-row');
      
      // Check if this row is already expanded
      if ($existingDetails.length > 0) {
        // Collapse this row
        $clickedRow.removeClass('expanded');
        $existingDetails.remove();
      } else {
        // Close any other open rows
        $('.data-row.expanded').removeClass('expanded');
        $('.details-row').remove();
        
        // Expand this row
        $clickedRow.addClass('expanded');
        
        // Get data from row attributes
        const rowData = {
          id: $clickedRow.data('id'),
          month: $clickedRow.data('month'),
          genRevenue: $clickedRow.data('gen-revenue'),
          recentPay: $clickedRow.data('recent-pay'),
          remainingAmt: $clickedRow.data('remaining-amt'),
          buildAmt: $clickedRow.data('build-amt'),
          userName: $clickedRow.data('user-name'),
          bookingNumber: $clickedRow.data('booking-number')
        };
        
        // Create details HTML
        const detailsHTML = `
          <tr class="details-row">
            <td colspan="9">
              <div class="details-container">
                <div class="details-title">Incentive Tracking Details</div>
                <div class="detail-item">
                  <span class="detail-label">ID:</span>
                  <span class="detail-value">${rowData.id}</span>
                </div>
                <div class="detail-item">
                  <span class="detail-label">Month:</span>
                  <span class="detail-value">${rowData.month}</span>
                </div>
                <div class="detail-item">
                  <span class="detail-label">Generated Revenue:</span>
                  <span class="detail-value">₹ ${rowData.genRevenue}</span>
                </div>
                <div class="detail-item">
                  <span class="detail-label">Recent Payment:</span>
                  <span class="detail-value">₹ ${rowData.recentPay}</span>
                </div>
                <div class="detail-item">
                  <span class="detail-label">Remaining Amount:</span>
                  <span class="detail-value">₹ ${rowData.remainingAmt}</span>
                </div>
                <div class="detail-item">
                  <span class="detail-label">Build Amount:</span>
                  <span class="detail-value">₹ ${rowData.buildAmt}</span>
                </div>
                <div class="detail-item">
                  <span class="detail-label">User Name:</span>
                  <span class="detail-value">${rowData.userName}</span>
                </div>
                <div class="detail-item">
                  <span class="detail-label">Booking Number:</span>
                  <span class="detail-value">${rowData.bookingNumber}</span>
                </div>
              </div>
            </td>
          </tr>
        `;
        
        // Insert details row after the clicked row
        $clickedRow.after(detailsHTML);
      }
    });
  });
</script>

<script>
  // Pagination script for incentive tracking
  $(document).ready(function() {
    let currentPage = 1;
    let rowsPerPage = 10;
    let allRows = [];
    
    function initPagination() {
      // Get all data rows (excluding details rows)
      allRows = $('#trackings tr.data-row').toArray();
      
      // Initialize pagination
      updatePagination();
    }
    
    function updatePagination() {
      const totalRows = allRows.length;
      const totalPages = Math.ceil(totalRows / rowsPerPage);
      
      // Update row info
      const startRow = totalRows === 0 ? 0 : (currentPage - 1) * rowsPerPage + 1;
      const endRow = Math.min(currentPage * rowsPerPage, totalRows);
      $('#incentiveRowInfo').text(`Showing ${startRow} to ${endRow} of ${totalRows} entries`);
      
      // Show/hide rows
      allRows.forEach((row, index) => {
        const rowPage = Math.floor(index / rowsPerPage) + 1;
        if (rowPage === currentPage) {
          $(row).show();
        } else {
          $(row).hide();
          // Also hide any expanded details for hidden rows
          $(row).next('.details-row').remove();
          $(row).removeClass('expanded');
        }
      });
      
      // Update pagination buttons
      $('#incentivePrevButton').prop('disabled', currentPage === 1);
      $('#incentiveNextButton').prop('disabled', currentPage === totalPages || totalPages === 0);
      
      // Generate page numbers
      generatePageNumbers(currentPage, totalPages);
    }
    
    function generatePageNumbers(current, total) {
      const $pageNumbers = $('#incentivePageNumbers');
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
    $('#incentivePrevButton').on('click', function() {
      if (currentPage > 1) {
        currentPage--;
        updatePagination();
      }
    });
    
    // Next button
    $('#incentiveNextButton').on('click', function() {
      const totalPages = Math.ceil(allRows.length / rowsPerPage);
      if (currentPage < totalPages) {
        currentPage++;
        updatePagination();
      }
    });
    
    // Jump to page
    $('#incentiveJumpButton').on('click', function() {
      const pageNum = parseInt($('#incentiveJumpInput').val());
      const totalPages = Math.ceil(allRows.length / rowsPerPage);
      
      if (pageNum >= 1 && pageNum <= totalPages) {
        currentPage = pageNum;
        updatePagination();
        $('#incentiveJumpInput').val('');
      } else {
        alert(`Please enter a page number between 1 and ${totalPages}`);
      }
    });
    
    // Enter key on jump input
    $('#incentiveJumpInput').on('keypress', function(e) {
      if (e.which === 13) {
        $('#incentiveJumpButton').click();
      }
    });
    
    // Initialize pagination on page load
    initPagination();
    
    // ===================
    // SEARCH FUNCTIONALITY
    // ===================
    $('#incentiveSearchInput').on('keyup', function() {
      const searchTerm = $(this).val().toLowerCase();
      
      $('#trackings tr.data-row').each(function() {
        const $row = $(this);
        const rowText = $row.text().toLowerCase();
        
        if (rowText.includes(searchTerm)) {
          $row.show();
        } else {
          $row.hide();
          // Hide expanded details if row is hidden
          $row.next('.details-row').remove();
          $row.removeClass('expanded');
        }
      });
      
      // Update pagination with visible rows
      allRows = $('#trackings tr.data-row:visible').toArray();
      currentPage = 1;
      updatePagination();
    });
    

    // ==========================
    // ROWS PER PAGE SELECTOR
    // ==========================
    $('#rowsPerPageSelector').on('change', function() {
      rowsPerPage = parseInt($(this).val());
      currentPage = 1;
      updatePagination();
    });
  });
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