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
  
  /* Hide overall earn columns for specific needs or keeping parity */
/*  #example thead tr th:nth-child(2),
  #example tbody tr td:nth-child(2) {
    display: none;
  }
*/
  
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
  
  /* Responsive visibilities */
  @media (max-width: 1024px) {
    #example thead tr th:nth-child(6),
    #example tbody tr td:nth-child(6) {
      display: none;
    }
    
    #example thead tr th:nth-child(7),
    #example tbody tr td:nth-child(7) {
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
  
</style>
<?php include('header.php'); ?>
<!-- Main Content -->
<div class="content">
<div class="contentinside">
  <div class="container">
    
    <?php
    $totalOverallEarn = 0;
    $totalOverallPaid = 0;
    $totalAdvancePay = 0;
    $totalRemainingPayment = 0;
    
    // Convert mysqli result to array for multiple traversals if needed
    $payment_rows = [];
    if($result_pay) {
        while($row = $result_pay->fetch_assoc()) {
            $payment_rows[] = $row;
            $totalOverallEarn += $row['overall_earn'];
            $totalOverallPaid += $row['overall_paid'];
            $totalAdvancePay += $row['advance_pay'];
            $totalRemainingPayment += $row['remaning_payment'];
        }
    }
    ?>

    <!-- Stats Cards Grid -->
    <div class="stats-grid mb-4">
        <div class="stat-card">
            <div class="stat-card-title">Overall Earning</div>
            <div class="stat-card-value">₹<?php echo number_format($totalOverallEarn, 2); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-card-title">Overall Paid</div>
            <div class="stat-card-value">₹<?php echo number_format($totalOverallPaid, 2); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-card-title">Advance Pay</div>
            <div class="stat-card-value">₹<?php echo number_format($totalAdvancePay, 2); ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-card-title">Remaining Payment</div>
            <div class="stat-card-value">₹<?php echo number_format($totalRemainingPayment, 2); ?></div>
        </div>
    </div>

    <div class="row">
      <div class="col-lg-12">
        <!-- Controls Row -->
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
                  <input type="checkbox" id="col-overall-paid" checked>
                  <label for="col-overall-paid">Overall Paid</label>
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
                <th>Overall Paid</th>
                <th>Advance Amount</th>
                <th>Remaining Payment</th>
                <th>User Name</th>
                <th>Booking Number</th>
                <th>Action</th>
                <th class="chevron-column"></th>
              </tr>
            </thead>
            <tbody id="paymenttable">
              <?php foreach($payment_rows as $row): ?>
              <tr class="main-row" data-id="<?php echo $row['id']; ?>">
                <td><?php echo $row['id']; ?></td>
                <td><?php echo $row['overall_earn']; ?></td>
                <td><?php echo $row['overall_paid']; ?></td>
                <td>
                  <input type="text" class="advance-pay-input" value="<?php echo $row['advance_pay']; ?>" id="advancePayInput-<?php echo $row['id']; ?>" oninput="editAdvancePay(<?php echo $row['id']; ?>)">
                </td>
                <td><?php echo $row['remaning_payment']; ?></td>
                <td><?php echo $row['user_name']; ?></td>
                <td><?php echo $row['bookin_number']; ?></td>
                <td>
                  <button class="save-btn" id="saveBtn-<?php echo $row['id']; ?>" disabled onclick="saveData(<?php echo $row['id']; ?>)">Save</button>
                </td>
                <td class="chevron-column">
                  <div class="chevron-icon" onclick="toggleRowDetails(this, event)">
                    <svg viewBox="0 0 24 24">
                      <path d="M8.59,16.59L13.17,12L8.59,7.41L10,6L16,12L10,18L8.59,16.59Z" />
                    </svg>
                  </div>
                </td>
              </tr>
              <!-- Detail row for mobile/expand -->
              <tr class="detail-row" id="detail-<?php echo $row['id']; ?>" style="display: none;">
                <td colspan="9">
                  <div class="detail-content">
                    <div class="detail-grid">
                      <div class="detail-item">
                        <span class="detail-label">User Name:</span>
                        <span class="detail-value"><?php echo $row['user_name']; ?></span>
                      </div>
                      <div class="detail-item">
                        <span class="detail-label">Booking Number:</span>
                        <span class="detail-value"><?php echo $row['bookin_number']; ?></span>
                      </div>
                      <div class="detail-item">
                        <span class="detail-label">Overall Paid:</span>
                        <span class="detail-value"><?php echo $row['overall_paid']; ?></span>
                      </div>
                      <div class="detail-item detail-full">
                        <span class="detail-label">Action:</span>
                        <div class="detail-value">
                            <button class="save-btn mobile-save-btn" id="mobile-saveBtn-<?php echo $row['id']; ?>" disabled onclick="saveData(<?php echo $row['id']; ?>)">Save Changes</button>
                        </div>
                      </div>
                    </div>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        
        <!-- Pagination UI -->
        <div class="dt-layout-foot">
          <div class="dt-layout-info">
            Showing <span id="showingStart">0</span> to <span id="showingEnd">0</span> of <span id="totalEntries">0</span> entries
          </div>
          <div class="dt-layout-paging">
            <div class="dt-paging-button disabled" id="prevPage">Previous</div>
            <div id="pageNumbers" style="display: flex; gap: 5px;"></div>
            <div class="dt-paging-button" id="nextPage">Next</div>
          </div>
          <div class="jump-to-page">
            <span>Jump to page:</span>
            <input type="number" id="jumpToPageInput" min="1">
            <button id="jumpToPageBtn">Go</button>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>
</div>

<script>
let currentPage = 1;
let rowsPerPage = 10;
let filteredRows = [];
let allRows = [];

document.addEventListener('DOMContentLoaded', function() {
    // Collect all table rows
    const tbody = document.getElementById('paymenttable');
    const mainRows = Array.from(tbody.querySelectorAll('tr.main-row'));
    
    allRows = mainRows.map(row => {
        const id = row.getAttribute('data-id');
        const detailRow = document.getElementById('detail-' + id);
        return {
            id: id,
            mainElement: row,
            detailElement: detailRow,
            searchText: row.innerText.toLowerCase()
        };
    });
    
    filteredRows = [...allRows];
    
    // Initial pagination
    updatePagination();
    
    // Rows per page listener
    document.getElementById('rowsPerPageSelector').addEventListener('change', function() {
        rowsPerPage = parseInt(this.value);
        currentPage = 1;
        updatePagination();
    });
    
    // Search listener
    document.getElementById('paymentSearchInput').addEventListener('input', function() {
        const query = this.value.toLowerCase();
        filteredRows = allRows.filter(row => row.searchText.includes(query));
        currentPage = 1;
        updatePagination();
    });
    
    // Pagination controls
    document.getElementById('prevPage').addEventListener('click', function() {
        if (currentPage > 1) {
            currentPage--;
            updatePagination();
        }
    });
    
    document.getElementById('nextPage').addEventListener('click', function() {
        const totalPages = Math.ceil(filteredRows.length / rowsPerPage);
        if (currentPage < totalPages) {
            currentPage++;
            updatePagination();
        }
    });

    // Jump to page
    document.getElementById('jumpToPageBtn').addEventListener('click', function() {
        const page = parseInt(document.getElementById('jumpToPageInput').value);
        const totalPages = Math.ceil(filteredRows.length / rowsPerPage);
        if (page >= 1 && page <= totalPages) {
            currentPage = page;
            updatePagination();
        }
    });

    // Column Visibility Logic
    const visibilityBtn = document.getElementById('columnVisibilityBtn');
    const visibilityMenu = document.getElementById('columnVisibilityMenu');
    
    visibilityBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        visibilityMenu.classList.toggle('active');
    });
    
    document.addEventListener('click', () => visibilityMenu.classList.remove('active'));
    visibilityMenu.addEventListener('click', (e) => e.stopPropagation());
    
    const columnCheckboxes = {
        'col-id': 1,
        'col-overall-earning': 2,
        'col-overall-paid': 3,
        'col-advance-amount': 4,
        'col-remaining-payment': 5,
        'col-user-name': 6,
        'col-booking-number': 7,
        'col-action': 8
    };
    
    Object.keys(columnCheckboxes).forEach(id => {
        const checkbox = document.getElementById(id);
        const colIdx = columnCheckboxes[id];
        
        checkbox.addEventListener('change', function() {
            const table = document.getElementById('example');
            const display = this.checked ? '' : 'none';
            
            // Header
            table.querySelector(`thead tr th:nth-child(${colIdx})`).style.display = display;
            
            // Body
            allRows.forEach(row => {
                row.mainElement.querySelector(`td:nth-child(${colIdx})`).style.display = display;
            });
        });
    });
});

function updatePagination() {
    const totalPages = Math.ceil(filteredRows.length / rowsPerPage);
    if (currentPage > totalPages && totalPages > 0) currentPage = totalPages;
    if (currentPage < 1) currentPage = 1;
    
    const start = (currentPage - 1) * rowsPerPage;
    const end = start + rowsPerPage;
    
    // Hide all rows
    allRows.forEach(row => {
        row.mainElement.style.display = 'none';
        row.detailElement.style.display = 'none';
        row.mainElement.querySelector('.chevron-icon').classList.remove('expanded');
    });
    
    // Show current page rows
    const visibleRows = filteredRows.slice(start, end);
    visibleRows.forEach(row => {
        row.mainElement.style.display = '';
    });
    
    // Update labels
    document.getElementById('showingStart').textContent = filteredRows.length > 0 ? start + 1 : 0;
    document.getElementById('showingEnd').textContent = Math.min(end, filteredRows.length);
    document.getElementById('totalEntries').textContent = filteredRows.length;
    
    // Update buttons
    document.getElementById('prevPage').classList.toggle('disabled', currentPage === 1);
    document.getElementById('nextPage').classList.toggle('disabled', currentPage === totalPages || totalPages === 0);
    
    // Draw page numbers
    const pageNumbersContainer = document.getElementById('pageNumbers');
    pageNumbersContainer.innerHTML = '';
    
    for (let i = 1; i <= totalPages; i++) {
        if (i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
            const btn = document.createElement('div');
            btn.className = 'dt-paging-button' + (i === currentPage ? ' current' : '');
            btn.textContent = i;
            btn.onclick = () => { currentPage = i; updatePagination(); };
            pageNumbersContainer.appendChild(btn);
        } else if (i === currentPage - 2 || i === currentPage + 2) {
            const dots = document.createElement('span');
            dots.textContent = '...';
            dots.style.alignSelf = 'center';
            pageNumbersContainer.appendChild(dots);
        }
    }
}

function toggleRowDetails(icon, event) {
    if (event) event.stopPropagation();
    const mainRow = icon.closest('tr');
    const id = mainRow.getAttribute('data-id');
    const detailRow = document.getElementById('detail-' + id);
    const isExpanded = icon.classList.contains('expanded');
    
    if (isExpanded) {
        icon.classList.remove('expanded');
        detailRow.style.display = 'none';
    } else {
        icon.classList.add('expanded');
        detailRow.style.display = '';
    }
}

// Mobile row click auto-expand
document.getElementById('paymenttable').addEventListener('click', function(e) {
    if (window.innerWidth <= 1024) {
        const mainRow = e.target.closest('tr.main-row');
        if (mainRow && !e.target.closest('.advance-pay-input') && !e.target.closest('.save-btn')) {
            const icon = mainRow.querySelector('.chevron-icon');
            toggleRowDetails(icon);
        }
    }
});

function editAdvancePay(id) {
    const saveBtn = document.getElementById('saveBtn-' + id);
    const mobileSaveBtn = document.getElementById('mobile-saveBtn-' + id);
    if(saveBtn) saveBtn.disabled = false;
    if(mobileSaveBtn) mobileSaveBtn.disabled = false;
}

function saveData(id) {
    const newAdvancePay = document.getElementById('advancePayInput-' + id).value;
    
    $.ajax({
        url: 'action.php',
        method: 'POST',
        data: {
            action: 'update_advance_pay',
            id: id,
            newAdvancePay: newAdvancePay
        },
        success: function(response) {
            const Toast = Swal.mixin({
                toast: true,
                position: "top-end",
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
                didOpen: (toast) => {
                    toast.onmouseenter = Swal.stopTimer;
                    toast.onmouseleave = Swal.resumeTimer;
                }
            });
            Toast.fire({
                icon: "success",
                title: "Advance Pay updated successfully!"
            });
            
            const saveBtn = document.getElementById('saveBtn-' + id);
            const mobileSaveBtn = document.getElementById('mobile-saveBtn-' + id);
            if(saveBtn) saveBtn.disabled = true;
            if(mobileSaveBtn) mobileSaveBtn.disabled = true;
        },
        error: function() {
            Swal.fire({
                icon: 'error',
                title: 'Oops...',
                text: 'Something went wrong!',
            });
        }
    });
}
</script>

<?php include('htmlclose.php'); ?>