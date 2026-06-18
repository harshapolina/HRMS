<?php
$skip_superadmin_css = true;
include('htmlopen.php');
?>
<link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />
<link href="https://cdnjs.cloudflare.com/ajax/libs/select2-bootstrap-5-theme/1.3.0/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Lexend+Deca:wght@100..900&display=swap" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
<link rel="stylesheet" href="./assets/css/Users.css?v=<?php echo time(); ?>"/>
<link rel="stylesheet" href="./assets/css/style_dashboard.css?v=<?php echo time(); ?>"/>
<link rel="stylesheet" href="./assets/css/unified_table_styles.css?v=<?php echo time(); ?>"/>
<script>
    // Inject data-theme to support superadmin CSS variables
    document.documentElement.setAttribute('data-theme', 'light');
</script>
<style>
/* =========================================
   FORCE BACKGROUND GRADIENT
========================================= */
body, html {
    background: radial-gradient(circle at 90% 50%, rgba(180, 140, 240, 0.5) 0%, transparent 40%), radial-gradient(circle at 25% 15%, #cce2c9 0%, transparent 30%), linear-gradient(135deg, #D1E5E6 0%, #caf2f5 0%) !important;
    background-attachment: fixed !important;
    background-size: cover !important;
}
body::before {
    content: ''; position: fixed; width: 200vw; height: 200vh; border-radius: 50%; z-index: -1; opacity: .3; animation: 15s ease-in-out infinite alternate float;
    background: radial-gradient(circle, #d2b4ff 0, transparent 70%); top: -10vh; right: -50vw; animation-delay: 0s;
}
body::after {
    content: ''; position: fixed; width: 200vw; height: 200vh; border-radius: 50%; z-index: -1; opacity: .3; animation: 15s ease-in-out infinite alternate float;
    background: radial-gradient(circle, #f9eb9c 0, transparent 70%); bottom: -100vh; left: -50vw; animation-delay: 2.5s;
}
@keyframes float {
    0% { transform: translateY(0) scale(1); }
    100% { transform: translateY(-20px) scale(1.05); }
}
/* =========================================
   MODERN SEARCH BOX & CONTROLS UI
========================================= */
.control-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    margin-top: 5px !important;
    margin-bottom: 10px !important;
    background: transparent !important;
    padding: 0 !important;
    box-shadow: none !important;
    backdrop-filter: none !important;
}
.control-left {
    flex: 1;
}
.search-box {
    position: relative;
    width: 100%;
}
.search-input {
    width: 100% !important;
    padding: 14px 20px 14px 48px !important;
    border: 1px solid rgba(0,0,0,0.1) !important;
    border-radius: 12px !important;
    font-size: 15px !important;
    background: #ffffff !important;
    box-shadow: 0 2px 4px rgba(0,0,0,0.02) !important;
    color: #333 !important;
}
.search-icon {
    position: absolute;
    left: 18px !important;
    top: 50% !important;
    transform: translateY(-50%) !important;
    font-size: 18px !important;
    color: #94a3b8 !important;
    z-index: 2;
}
.page-size-selector {
    padding: 12px 18px !important;
    border-radius: 12px !important;
    border: 1px solid rgba(0,0,0,0.1) !important;
    background: #ffffff !important;
    min-width: 80px !important;
}
/* =========================================
   INCENTIVE TRACKER UI FIXES
========================================= */
table.user-data-table {
    border-collapse: separate !important;
    border-spacing: 0px 14px !important;
    background: transparent !important;
}
.user-table-container {
    margin-top: 5px !important;
    margin-bottom: 0px !important;
    border: none !important;
    box-shadow: none !important;
    background: transparent !important;
}
.user-table-scroll-wrapper {
    max-height: calc(100vh - 160px);
    overflow-y: auto !important;
    overflow-x: auto !important;
    scrollbar-width: none !important; /* Hide scrollbar Firefox */
}
.user-table-scroll-wrapper::-webkit-scrollbar {
    width: 0px !important;
    height: 0px !important;
    display: none !important; /* Hide scrollbar Webkit */
}
/* Pagination Gap Fix */
.pagination-section {
    padding-top: 10px !important;
    padding-bottom: 15px !important;
    margin-top: 0 !important;
}
/* Sticky Header */
table.user-data-table thead th {
    position: sticky !important;
    top: 0 !important;
    z-index: 10 !important;
    background: #f4f7f8 !important; /* Slight off-white to block table rows under it */
    border-bottom: 2px solid #e2e8f0 !important;
    color: #64748b !important;
    font-size: 12px !important;
    padding: 16px !important;
    font-weight: 600 !important;
    text-transform: uppercase;
    box-shadow: 0 2px 4px rgba(0,0,0,0.02) !important;
}
table.user-data-table tbody tr {
    background: #ffffff !important;
    border-radius: 16px !important;
    box-shadow: 0 4px 12px rgba(0,0,0,0.04) !important;
    transition: all 0.3s ease !important;
}
table.user-data-table tbody tr:hover {
    box-shadow: 0 6px 16px rgba(0,0,0,0.08) !important;
    transform: translateY(-1px) !important;
}
table.user-data-table tbody td {
    border: none !important;
    background: transparent !important;
    padding: 18px 16px !important;
    font-size: 14px !important;
    color: #334155 !important;
    font-weight: 500 !important;
}
/* Ensure rounded corners apply directly to cells */
table.user-data-table tbody td:first-child { border-radius: 16px 0 0 16px !important; }
table.user-data-table tbody td:last-child { border-radius: 0 16px 16px 0 !important; }
/* Push pagination to actual bottom */
.contentinside {
    padding-bottom: 0 !important;
    margin-bottom: 0 !important;
}
/* =========================================
   PAGINATION UI MATCH
========================================= */
.pagination-info {
    font-weight: 700 !important;
    color: #1e293b !important;
    font-size: 14px !important;
}
.page-btn {
    padding: 10px 16px !important;
    font-size: 14px !important;
    font-weight: 600 !important;
    color: #334155 !important;
    border-radius: 6px !important;
    border: 1px solid #e2e8f0 !important;
    background: #ffffff !important;
    box-shadow: 0 1px 2px rgba(0,0,0,0.02) !important;
}
.page-btn.active {
    background: #007bff !important;
    color: #ffffff !important;
    border-color: #007bff !important;
}
.jump-wrapper {
    padding: 4px 4px 4px 16px !important; 
    border-radius: 8px !important;
    border: 1px solid #e2e8f0 !important;
    background: #ffffff !important;
    box-shadow: 0 1px 2px rgba(0,0,0,0.02) !important;
}
.jump-wrapper .jump-label {
    font-weight: 500 !important;
    color: #64748b !important;
    font-size: 14px !important;
}
.jump-wrapper .jump-input {
    width: 60px !important; 
}
.jump-wrapper .jump-btn {
    padding: 10px 20px !important;
    font-size: 14px !important;
    font-weight: 600 !important;
    border-radius: 6px !important; 
    margin-left: 8px !important;
}
/* Action button (Chevron) */
.user-expand-btn {
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
    background: #1e293b !important;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    color: white !important;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease !important;
}
.user-expand-btn:hover {
    box-shadow: 0 4px 12px rgba(30, 41, 59, 0.3) !important;
}
.user-expand-btn i {
    display: flex !important;
    font-size: 14px;
    line-height: 1;
}
/* Hide broken icon tag but allow the exact class to render chevron if it uses FontAwesome */
tr.expanded .user-expand-btn i {
    transform: rotate(90deg) !important;
}
/* Hide Columns */
table.user-data-table th:nth-child(4), table.user-data-table td:nth-child(4),
table.user-data-table th:nth-child(6), table.user-data-table td:nth-child(6),
table.user-data-table th:nth-child(8), table.user-data-table td:nth-child(8) {
    display: none !important;
}
/* Top Navbar search hide and gap fixes */
#lapserach, #mob_search_icon { display: none !important; }
/* Force navbar right-side icons to stick together */
.navbar { 
    justify-content: flex-start !important; 
    gap: 24px !important; 
}
.navbar .incentivelogo { 
    margin-right: auto !important; 
}
/* Font */
.content, input, button, select, th, td {
    font-family: "Lexend Deca", sans-serif !important;
}
/* Details row */
tr.details-row { background-color: transparent !important; cursor: default !important; }
tr.details-row td { 
    padding: 0 !important; 
    border: none !important; 
    background: #f8fafc !important; 
    border-radius: 16px !important;
    box-shadow: inset 0 2px 4px rgba(0,0,0,0.02) !important;
}
.details-container { padding: 24px; }
.details-title { font-size: 18px; font-weight: 600; text-align: center; margin-bottom: 20px; color: #1e293b; }
.detail-item { margin-bottom: 8px; display: flex; align-items: center; }
.detail-label { font-weight: 600; color: #64748b; text-transform: uppercase; font-size: 12px; min-width: 180px; }
.detail-value { color: #334155; font-size: 14px; font-weight: 500; }
</style>
<?php include('header.php'); ?>
<div class="content">
<div class="contentinside">
  <div class="container-fluid" style="padding: 0 10px;">
    <div class="row">
      <div class="col-lg-12">
        
        <div class="control-bar">
          <div class="control-left">
            <div class="search-box">
              <i class="bi bi-search search-icon"></i>
              <input type="text" class="search-input" id="incentiveSearchInput" placeholder="Search incentives...">
            </div>
          </div>
          
          <div class="control-right">
            <div class="page-size-selector">
              <select id="rowsPerPageSelector" class="users-limit-select">
                <option value="10">10</option>
                <option value="25">25</option>
                <option value="50">50</option>
                <option value="100">100</option>
                <option value="200">200</option>
              </select>
            </div>
          </div>
        </div>
        
        <div class="user-table-container">
          <div class="user-table-scroll-wrapper">
            <table id="example" class="user-data-table unified-table" cellspacing="0" style="width:100%">
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
              if ($result && $result->num_rows > 0) {
                  while ($row = $result->fetch_assoc()) {
                      echo "<tr class='data-row user-data-row' 
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
                      // Chevron icon to match user expand button
                      echo "<td class='user-action-cell'><button class='user-expand-btn'><i class='bi bi-chevron-right' style='-webkit-text-stroke: 1px currentColor;'></i></button></td>";
                      echo "</tr>";
                  }
              } else {
                  echo "<tr><td colspan='9' style='text-align:center;'>No data found</td></tr>";
              }
              ?>
              </tbody>
            </table>
          </div>
        </div>
        
        <div class="pagination-section">
          <div class="pagination-info" id="incentiveRowInfo">Showing 1 to 10 of 0 entries</div>
          
          <div class="pagination-controls" id="incentivePagination">
            <button class="page-btn" id="incentivePrevButton" disabled>←</button>
            <span id="incentivePageNumbers" style="display: flex; gap: 5px;"></span>
            <button class="page-btn" id="incentiveNextButton" disabled>→</button>
          </div>
          
          <div class="pagination-jump">
            <div class="jump-wrapper">
              <span class="jump-label">Page No.</span>
              <input type="number" id="incentiveJumpInput" class="jump-input" placeholder="" min="1" />
              <button id="incentiveJumpButton" class="jump-btn">Jump</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
</div>
<script>
  // Handle row click to expand/collapse details
  $(document).ready(function() {
    $(document).on('click', '.data-row', function(e) {
      const $clickedRow = $(this);
      const $existingDetails = $clickedRow.next('.details-row');
      
      if ($existingDetails.length > 0) {
        $clickedRow.removeClass('expanded');
        $existingDetails.remove();
      } else {
        $('.data-row.expanded').removeClass('expanded');
        $('.details-row').remove();
        
        $clickedRow.addClass('expanded');
        
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
      allRows = $('#trackings tr.data-row').toArray();
      updatePagination();
    }
    
    function updatePagination() {
      const totalRows = allRows.length;
      const totalPages = Math.ceil(totalRows / rowsPerPage);
      
      const startRow = totalRows === 0 ? 0 : (currentPage - 1) * rowsPerPage + 1;
      const endRow = Math.min(currentPage * rowsPerPage, totalRows);
      $('#incentiveRowInfo').text(`Showing ${startRow} to ${endRow} of ${totalRows} entries`);
      
      allRows.forEach((row, index) => {
        const rowPage = Math.floor(index / rowsPerPage) + 1;
        if (rowPage === currentPage) {
          $(row).show();
        } else {
          $(row).hide();
          $(row).next('.details-row').remove();
          $(row).removeClass('expanded');
        }
      });
      
      $('#incentivePrevButton').prop('disabled', currentPage === 1);
      $('#incentiveNextButton').prop('disabled', currentPage === totalPages || totalPages === 0);
      
      generatePageNumbers(currentPage, totalPages);
    }
    
    function generatePageNumbers(current, total) {
      const $pageNumbers = $('#incentivePageNumbers');
      $pageNumbers.empty();
      
      if (total === 0) return;
      
      let startPage = Math.max(1, current - 2);
      let endPage = Math.min(total, startPage + 4);
      
      if (endPage - startPage < 4) {
        startPage = Math.max(1, endPage - 4);
      }
      
      for (let i = startPage; i <= endPage; i++) {
        // Appended the missing 'page-btn' class here for perfect UI matching
        const btn = $(`<button class="page-btn">${i}</button>`);
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
    
    $('#incentivePrevButton').on('click', function() {
      if (currentPage > 1) {
        currentPage--;
        updatePagination();
      }
    });
    $('#incentiveNextButton').on('click', function() {
      const totalPages = Math.ceil(allRows.length / rowsPerPage);
      if (currentPage < totalPages) {
        currentPage++;
        updatePagination();
      }
    });
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
    $('#incentiveJumpInput').on('keypress', function(e) {
      if (e.which === 13) {
        $('#incentiveJumpButton').click();
      }
    });
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
          $row.next('.details-row').remove();
          $row.removeClass('expanded');
        }
      });
      
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
    
    if (notifBtn.length && notifContent.length) {
      notifBtn.on('click', function(e) {
        e.stopPropagation();
        const isVisible = notifContent.css('display') === 'block';
        notifContent.css('display', isVisible ? 'none' : 'block');
        profileContent.css('display', 'none');
      });
    }
    
    if (profileIcon.length && profileContent.length) {
      profileIcon.on('click', function(e) {
        e.stopPropagation();
        const isVisible = profileContent.css('display') === 'block';
        profileContent.css('display', isVisible ? 'none' : 'block');
        notifContent.css('display', 'none');
      });
    }
    
    $('.closebtn').on('click', function() {
      notifContent.css('display', 'none');
    });
    $('.closebtn1').on('click', function() {
      profileContent.css('display', 'none');
    });
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