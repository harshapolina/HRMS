<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

$pageTitle = "Superadmin Bookings";
$isBookingPage = true;
$hideCityFilterInHeader = true; // Hide city filter from header since it's in main content

// Check if embedded mode
$isEmbed = isset($_GET['embed']) && $_GET['embed'] === '1';

// Session is already started in htmlopen.php, so we can safely access session variables
// Get session variables (use defaults for superadmin view)
$nameuser = $_SESSION['username'] ?? 'Superadmin';
$userid = $_SESSION['tablename'] ?? '';
$Project_type = $_SESSION['project_type'] ?? '';
$user_type = $_SESSION['user_type'] ?? 'superuseradmin';
$id = $_SESSION['id'] ?? '';
// Include superadmin database connection and utility classes
// Path relative to userlogin6 directory
require_once __DIR__ . '/../superadmin/db.php';
require_once __DIR__ . '/../superadmin/util.php';

$db = new Database;
$util = new Util;

// Fetch ALL bookings data from superadmin database (no user filtering)
$allBookings = $db->read(); // This gets all data from admintable + updaterowtable

// Debug: Log total bookings fetched
error_log("Total bookings fetched from database: " . count($allBookings));

// Handle city filtering from GET parameter
$cityOptions = [];
foreach ($allBookings as $booking) {
    $city = trim($booking['city'] ?? '');
    if (!empty($city)) {
        $cityOptions[$city] = true;
    }
}
$cityOptions = array_keys($cityOptions);
sort($cityOptions);

$selectedCity = '';
$requestedCity = $_GET['city'] ?? '';
if (!empty($requestedCity) && in_array($requestedCity, $cityOptions, true)) {
    $selectedCity = $requestedCity;
}

// Handle financial year filtering from GET parameter
$selectedYear = $_GET['year'] ?? 'all';

// Set globals for city (year options will be set after grouping)
$GLOBALS['selectedCity'] = $selectedCity;
$GLOBALS['cityOptions'] = $cityOptions;
$GLOBALS['selectedYear'] = $selectedYear;

// Group ALL bookings by financial year (unfiltered - for downloads)
$allGroupedRows = []; // Array to store ALL grouped rows by financial year
$allGroupedRows['ungrouped'] = []; // For bookings without booking_month

foreach ($allBookings as $row) {
    $monthYear = $row['booking_month'] ?? '';
    
    // If no booking_month, add to ungrouped
    if (empty($monthYear)) {
        $allGroupedRows['ungrouped'][] = $row;
        continue;
    }
    
    $year = date('Y', strtotime($monthYear)); // Extract the year from the booking_month
    $month = date('n', strtotime($monthYear)); // Extract the month (1-12) from the booking_month

    // Adjust the year if the month is before April (financial year starts in April)
    if ($month < 4) {
        $year--;
    }

    $groupKey = $year . '-' . ($year + 1); // Generate the group key (e.g., 2022-2023)
    if (!isset($allGroupedRows[$groupKey])) {
        $allGroupedRows[$groupKey] = [];
    }
    $allGroupedRows[$groupKey][] = $row;
}

// Remove ungrouped if empty
if (empty($allGroupedRows['ungrouped'])) {
    unset($allGroupedRows['ungrouped']);
}

// Debug: Count total bookings in allGroupedRows
$totalInAllGroupedRows = 0;
foreach ($allGroupedRows as $year => $bookings) {
    $count = count($bookings);
    $totalInAllGroupedRows += $count;
    error_log("Year $year has $count bookings");
}
error_log("Total bookings in allGroupedRows: $totalInAllGroupedRows");
error_log("Total bookings fetched (allBookings): " . count($allBookings));

// Extract year options from allGroupedRows (unfiltered list of all years)
$yearOptions = array_keys($allGroupedRows);
// Filter out 'ungrouped' from year options
$yearOptions = array_filter($yearOptions, function($year) {
    return $year !== 'ungrouped';
});
// Sort year options in descending order (newest first)
usort($yearOptions, function($a, $b) {
    return strcmp($b, $a);
});
$GLOBALS['yearOptions'] = $yearOptions;

// Group bookings by financial year (with city filter for display)
$groupedRows = []; // Array to store grouped rows by financial year
foreach ($allBookings as $row) {
    $monthYear = $row['booking_month'] ?? '';
    if (empty($monthYear)) continue;
    
    // Apply city filter before grouping
    if (!empty($selectedCity)) {
        $rowCity = trim($row['city'] ?? '');
        if ($rowCity !== $selectedCity) {
            continue; // Skip this row if city doesn't match
        }
    }
    
    $year = date('Y', strtotime($monthYear)); // Extract the year from the booking_month
    $month = date('n', strtotime($monthYear)); // Extract the month (1-12) from the booking_month

    // Adjust the year if the month is before April (financial year starts in April)
    if ($month < 4) {
        $year--;
    }

    $groupKey = $year . '-' . ($year + 1); // Generate the group key (e.g., 2022-2023)
    if (!isset($groupedRows[$groupKey])) {
        $groupedRows[$groupKey] = [];
    }
    $groupedRows[$groupKey][] = $row;
}

// Helper function to format numbers in compact format (K, M, B)
function formatCompactNumber($number) {
    if ($number >= 1000000000) {
        return '₹' . round($number / 1000000000, 1) . 'B';
    } elseif ($number >= 1000000) {
        return '₹' . round($number / 1000000, 1) . 'M';
    } elseif ($number >= 1000) {
        return '₹' . round($number / 1000, 1) . 'K';
    } else {
        return '₹' . number_format($number);
    }
}

// Compute aggregate stats for a given set of rows (used for filters)
function computeOverallStats(array $rows) {
    $booking = count($rows);
    $totalRevenue = 0;
    $actualRevenue = 0;
    $recivedRevenue = 0;
    $overallAgreement = 0;
    $invoice_raised = 0;

    foreach ($rows as $row) {
        $totalRevenue += (float)($row['revenue'] ?? 0);
        $actualRevenue += (float)($row['crevenue'] ?? 0);
        $recivedRevenue += (float)($row['recived_amt'] ?? 0);
        $overallAgreement += (float)($row['agreement_value'] ?? 0);
        $invoice_raised += (float)($row['invoice_raise'] ?? 0);
    }

    return [
        'booking' => $booking,
        'totalRevenue' => $totalRevenue,
        'actualRevenue' => $actualRevenue,
        'recivedRevenue' => $recivedRevenue,
        'agreement' => $overallAgreement,
        'invoice_raised' => $invoice_raised,
        'finalRemaining' => max(0, $totalRevenue - $invoice_raised)
    ];
}

// Calculate yearly stats (like superadmin/action.php)
$yearlyData = [];
foreach ($groupedRows as $yearKey => $rows) {
    $rowcount = count($rows);
    $totalRevenue = 0;
    $actualRevenue = 0;
    $recivedRevenue = 0;
    $invoice_raised = 0;
    $total_raised = 0;
    $overallAgreement = 0;
    
    // Get status counter for this financial year
    $status_counter = $db->status_counter($yearKey);
    $received_count = $status_counter['received_count'];
    $canceled_count = $status_counter['canceled_count'];
    $processing_count = $status_counter['processing_count'];
    
    foreach ($rows as $row) {
        $totalRevenue += (float)($row['revenue'] ?? 0);
        $actualRevenue += (float)($row['crevenue'] ?? 0);
        $recivedRevenue += (float)($row['recived_amt'] ?? 0);
        $invoice_raised += (float)($row['invoice_raise'] ?? 0);
        $total_raised += (float)($row['update_in_invoice_table'] ?? 0);
        $overallAgreement += (float)($row['agreement_value'] ?? 0);
    }
    
    // Get totals for this financial year
    $totalPaid = $db->totalGivenAmt($yearKey);
    $totalPaidmanager = $db->totalGivenAmtManager($yearKey);
    $totalPaidsalary = $db->totalGivenAmtSalary($yearKey);
    $totalAmtPay = $db->calculate_total_getamount($yearKey);
    $totalExpensesAmt = $db->totalExpensesForFinancialYear($yearKey);
    
    $yearlyData[] = [
        'year' => $yearKey,
        'bookings' => $rowcount,
        'totalRevenue' => $totalRevenue,
        'actualRevenue' => $actualRevenue,
        'recivedRevenue' => $recivedRevenue,
        'invoice_raised' => $invoice_raised,
        'total_raised' => $total_raised,
        'received_count' => $received_count,
        'canceled_count' => $canceled_count,
        'processing_count' => $processing_count,
        'totalPaid' => $totalPaid,
        'totalPaidmanager' => $totalPaidmanager,
        'totalPaidsalary' => $totalPaidsalary,
        'totalAmtPay' => $totalAmtPay,
        'totalExpensesAmt' => $totalExpensesAmt,
        'agreement' => $overallAgreement
    ];
}

// Calculate overall stats
$overall_stats = [
    'booking' => array_sum(array_column($yearlyData, 'bookings')),
    'totalRevenue' => array_sum(array_column($yearlyData, 'totalRevenue')),
    'actualRevenue' => array_sum(array_column($yearlyData, 'actualRevenue')),
    'recivedRevenue' => array_sum(array_column($yearlyData, 'recivedRevenue')),
    'invoice_raised' => array_sum(array_column($yearlyData, 'invoice_raised')),
    'agreement' => array_sum(array_column($yearlyData, 'agreement'))
];
$overall_stats['finalRemaining'] = max(0, $overall_stats['totalRevenue'] - $overall_stats['invoice_raised']);

// Sort yearly data by year descending (newest first)
usort($yearlyData, function($a, $b) {
    return strcmp($b['year'], $a['year']);
});

// Filter yearlyData by selected year if specified (for display and stats)
$displayYearlyData = $yearlyData;
if (!empty($selectedYear) && $selectedYear !== 'all') {
    $displayYearlyData = array_filter($yearlyData, function($yearData) use ($selectedYear) {
        return $yearData['year'] === $selectedYear;
    });
    
    // Recalculate overall stats based on filtered year
    $overall_stats = [
        'booking' => array_sum(array_column($displayYearlyData, 'bookings')),
        'totalRevenue' => array_sum(array_column($displayYearlyData, 'totalRevenue')),
        'actualRevenue' => array_sum(array_column($displayYearlyData, 'actualRevenue')),
        'recivedRevenue' => array_sum(array_column($displayYearlyData, 'recivedRevenue')),
        'invoice_raised' => array_sum(array_column($displayYearlyData, 'invoice_raised')),
        'agreement' => array_sum(array_column($displayYearlyData, 'agreement'))
    ];
    $overall_stats['finalRemaining'] = max(0, $overall_stats['totalRevenue'] - $overall_stats['invoice_raised']);
}

include 'htmlopen.php';
include 'header.php';
?>

<script>
// Apply dark mode immediately on page load to prevent flash when iframe reloads
(function() {
    function applyDarkModeState(enabled) {
        if (enabled) {
            document.body.classList.add('dark-mode');
            document.documentElement.setAttribute('data-theme', 'dark');
        } else {
            document.body.classList.remove('dark-mode');
            document.documentElement.removeAttribute('data-theme');
        }
    }

  try {
        // In iframe mode, prefer parent darkMode first (iframe localStorage can be stale)
        let savedDarkMode = null;
        try {
            if (window.parent && window.parent !== window) {
                savedDarkMode = window.parent.localStorage.getItem('darkMode');
            }
        } catch (e) {
            // Ignore parent access issues and fall back to local storage
        }

        if (savedDarkMode === null) {
            savedDarkMode = localStorage.getItem('darkMode');
        }

        applyDarkModeState(savedDarkMode === 'true' || savedDarkMode === 'enabled');
  } catch (e) {
    // Ignore localStorage errors in cross-origin scenarios
    console.log('Could not check dark mode preference:', e);
  }
})();

// Listen for dark mode changes from parent window
window.addEventListener('message', function(event) {
  if (event.data && event.data.type === 'darkMode') {
        const enabled = !!event.data.enabled;
        if (enabled) {
            document.body.classList.add('dark-mode');
            document.documentElement.setAttribute('data-theme', 'dark');
        } else {
            document.body.classList.remove('dark-mode');
            document.documentElement.removeAttribute('data-theme');
        }

        // Keep iframe local preference in sync with parent toggles
        try {
            localStorage.setItem('darkMode', enabled ? 'true' : 'false');
        } catch (e) {
            // Ignore storage errors
        }
  }
});

// Define editUser function early so it's available for inline onclick handlers
window.editUser = async function(id) {
  if (!id) {
    Swal.fire({
      toast: true,
      position: 'bottom',
      icon: 'error',
      title: 'No booking ID provided',
      showConfirmButton: false,
      timer: 3000,
      timerProgressBar: true
    });
    return;
  }
  
  try {
    const url = `/incentiveapp_integration/userlogin1/superadmin/action.php?edit=1&id=${id}`;
    
    const data = await fetch(url, {
      method: "GET",
    });
    
    if (!data.ok) {
      throw new Error(`HTTP error! status: ${data.status}`);
    }
    
    const response = await data.json();
    
    const modal = document.getElementById('editUserModal');
    
    if (!modal) {
      Swal.fire({
        toast: true,
        position: 'bottom',
        icon: 'error',
        title: 'Edit modal not found. Please refresh the page.',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true
      });
      return;
    }
    
    // Populate form fields
    if (document.getElementById("id")) document.getElementById("id").value = response.id || '';
    if (document.getElementById("bdate")) document.getElementById("bdate").value = response.booking_date || '';
    if (document.getElementById("bmonth")) document.getElementById("bmonth").value = response.booking_month || '';
    if (document.getElementById("developer")) document.getElementById("developer").value = response.builder || '';
    if (document.getElementById("bproject")) document.getElementById("bproject").value = response.project || '';
    if (document.getElementById("cname")) document.getElementById("cname").value = response.customer_name || '';
    if (document.getElementById("cnumber")) document.getElementById("cnumber").value = response.contact_number || '';
    if (document.getElementById("cemail")) document.getElementById("cemail").value = response.email_id || '';
    if (document.getElementById("tproject")) document.getElementById("tproject").value = response.project_type || '';
    if (document.getElementById("unitno")) document.getElementById("unitno").value = response.unit_no || '';
    if (document.getElementById("psize")) document.getElementById("psize").value = response.size || '';
    if (document.getElementById("cagreement")) document.getElementById("cagreement").value = response.agreement_value || '';
    if (document.getElementById("ccashback")) document.getElementById("ccashback").value = response.cashback || '';
    if (document.getElementById("crevenue")) document.getElementById("crevenue").value = response.revenue || '';
    if (document.getElementById("cccashback")) document.getElementById("cccashback").value = response.ccashback || '';
    if (document.getElementById("ccrevenue")) document.getElementById("ccrevenue").value = response.crevenue || '';
    if (document.getElementById("brecived")) document.getElementById("brecived").value = response.recived_amt || '';
    if (document.getElementById("invoice_raised")) document.getElementById("invoice_raised").value = response.invoice_raise || '';
    if (document.getElementById("source_table")) document.getElementById("source_table").value = response.source_table || '';
    if (document.getElementById("selected_user_label")) {
      document.getElementById("selected_user_label").innerHTML = response.source_table || '';
    }
    if (document.getElementById("unique_searchInput")) {
      document.getElementById("unique_searchInput").value = response.source_table || '';
    }
    
    // Set status
    const status = response.astatus || 'Processing';
    if (status === 'Processing') {
      if (document.getElementById('btn-check-3-outlined')) document.getElementById('btn-check-3-outlined').checked = true;
      if (document.getElementById('2success-outlined')) document.getElementById('2success-outlined').checked = false;
      if (document.getElementById('2danger-outlined')) document.getElementById('2danger-outlined').checked = false;
    } else if (status === 'Received') {
      if (document.getElementById('btn-check-3-outlined')) document.getElementById('btn-check-3-outlined').checked = false;
      if (document.getElementById('2success-outlined')) document.getElementById('2success-outlined').checked = true;
      if (document.getElementById('2danger-outlined')) document.getElementById('2danger-outlined').checked = false;
    } else if (status === 'Canceled') {
      if (document.getElementById('btn-check-3-outlined')) document.getElementById('btn-check-3-outlined').checked = false;
      if (document.getElementById('2success-outlined')) document.getElementById('2success-outlined').checked = false;
      if (document.getElementById('2danger-outlined')) document.getElementById('2danger-outlined').checked = true;
    }
    
    // Set checkboxes
    if (document.getElementById("update_invoice_checkbox")) {
      document.getElementById("update_invoice_checkbox").checked = response.update_in_invoice_table == 1;
    }
    if (document.getElementById("update_user_checkbox")) {
      document.getElementById("update_user_checkbox").checked = response.update_in_user_table == 1;
    }
    if (document.getElementById("cashbackverify")) {
      document.getElementById("cashbackverify").checked = response.cashbackverify == 1;
    }
    
    // Show current file if exists
    if (response.document_path && response.document_path.trim() !== '') {
      const currentFileDisplay = document.getElementById('currentFileDisplay');
      const currentFileName = document.getElementById('currentFileName');
      const currentFileDownload = document.getElementById('currentFileDownload');
      
      if (currentFileDisplay && currentFileName && currentFileDownload) {
        // Extract filename from path
        const fileName = response.document_path.split('/').pop();
        currentFileName.textContent = fileName;
        // Use absolute URL path
        currentFileDownload.href = '/incentiveapp_integration/userlogin1/superadmin/' + response.document_path;
        currentFileDisplay.style.display = 'block';
      }
    } else {
      const currentFileDisplay = document.getElementById('currentFileDisplay');
      if (currentFileDisplay) {
        currentFileDisplay.style.display = 'none';
      }
    }
    
    // Check if we're in an iframe - if so, move modal to parent window
    const isInIframe = window.self !== window.top;
    
    if (isInIframe) {
      try {
        // Clone the modal to parent window
        const parentDoc = window.parent.document;
        const parentBody = parentDoc.body;
        
        // Remove existing modal from parent if it exists
        const existingParentModal = parentDoc.getElementById('editUserModal');
        if (existingParentModal) {
          existingParentModal.remove();
        }
        
        // Clone the modal
        const modalClone = modal.cloneNode(true);
        modalClone.id = 'editUserModal';
        
        // Update close button onclick to work in parent
        const closeBtn = modalClone.querySelector('.btn-close');
        if (closeBtn) {
          closeBtn.setAttribute('onclick', "document.getElementById('editUserModal').style.display='none';");
        }
        
        // Inject dropdown CSS into parent document if not already present
        if (!parentDoc.getElementById('dropdown-styles-injected')) {
            const styleEl = parentDoc.createElement('style');
            styleEl.id = 'dropdown-styles-injected';
            styleEl.textContent = `
                .dropdown-container { position: relative; }
                .dropdown-options {
                    display: none;
                    border: 1px solid #ccc;
                    list-style-type: none;
                    margin: 0;
                    padding: 0;
                    max-height: 200px;
                    overflow-y: auto;
                    position: absolute;
                    width: 100%;
                    background-color: #fff;
                    z-index: 200000;
                    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                    border-radius: 0.375rem;
                }
                .dropdown-options li, .unique-option {
                    padding: 10px;
                    cursor: pointer;
                    transition: background-color 0.2s ease;
                }
                .dropdown-options li:hover, .unique-option:hover {
                    background-color: #f1f5f9;
                }
                #unique_searchInput {
                    width: 100%;
                    padding: 0.5rem;
                    border: 1px solid #d1d5db;
                    border-radius: 0.375rem;
                    font-size: 14px;
                }
                #unique_searchInput:focus {
                    outline: none;
                    border-color: #3b82f6;
                    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
                }
                [data-theme="dark"] .dropdown-options {
                    background-color: #1e293b;
                    border-color: #475569;
                }
                [data-theme="dark"] .dropdown-options li,
                [data-theme="dark"] .unique-option {
                    color: #e2e8f0;
                }
                [data-theme="dark"] .dropdown-options li:hover,
                [data-theme="dark"] .unique-option:hover {
                    background-color: #334155;
                }
                [data-theme="dark"] #unique_searchInput {
                    background-color: #1e293b;
                    border-color: #475569;
                    color: #e2e8f0;
                }
            `;
            parentDoc.head.appendChild(styleEl);
        }

        // Append to parent body
        parentBody.appendChild(modalClone);
        
        // Show in parent
        modalClone.style.display = 'flex';
        modalClone.style.visibility = 'visible';
        modalClone.style.opacity = '1';
        modalClone.style.pointerEvents = 'auto';
        modalClone.style.position = 'fixed';
        modalClone.style.top = '0';
        modalClone.style.left = '0';
        modalClone.style.width = '100%';
        modalClone.style.height = '100%';
        modalClone.style.zIndex = '99999';
        modalClone.style.background = 'rgba(0,0,0,0.5)';
        modalClone.style.alignItems = 'center';
        modalClone.style.justifyContent = 'center';
        modalClone.style.flexDirection = 'column';
        modalClone.style.overflow = 'auto';
        
        // Update form submission to work in parent context
        const parentForm = parentDoc.getElementById('edit-user-form');
        if (parentForm) {
          // Remove old listener if exists
          const newForm = parentForm.cloneNode(true);
          parentForm.parentNode.replaceChild(newForm, parentForm);
          
          // Re-initialize dropdown AFTER the form re-clone so listeners survive
          if (typeof initAssignedUserDropdown === 'function') {
              initAssignedUserDropdown(parentDoc);
          }

          // Re-attach submit handler in parent context
          newForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(newForm);
            formData.append('update', 1);
            
            const updateBtn = parentDoc.getElementById('edit-user-btn');
            const originalText = updateBtn.value;
            updateBtn.value = 'Please Wait...';
            updateBtn.disabled = true;
            
            try {
              const response = await fetch('/incentiveapp_integration/userlogin1/superadmin/action.php', {
                method: 'POST',
                body: formData,
              });
              const result = await response.text();
              
              if (response.ok) {
                parentDoc.getElementById('editUserModal').style.display = 'none';
                // Use parent window's Swal
                if (window.parent.Swal) {
                  window.parent.Swal.fire({
                    toast: true,
                    position: 'bottom',
                    icon: 'success',
                    title: 'Booking updated successfully!',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true
                  });
                }
                window.location.reload(); // Reload iframe
              } else {
                // Use parent window's  Swal
                if (window.parent.Swal) {
                  window.parent.Swal.fire({
                    toast: true,
                    position: 'bottom',
                    icon: 'error',
                    title: 'Error updating booking: ' + result,
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true
                  });
                }
              }
            } catch (error) {
              // Use parent window's Swal
              if (window.parent.Swal) {
                window.parent.Swal.fire({
                  toast: true,
                  position: 'bottom',
                  icon: 'error',
                  title: 'Error updating booking. Please try again.',
                  showConfirmButton: false,
                  timer: 3000,
                  timerProgressBar: true
                });
              }
            } finally {
              updateBtn.value = originalText;
              updateBtn.disabled = false;
            }
          });
        }
        
        // Close modal when clicking outside (in parent)
        modalClone.addEventListener('click', function(e) {
          if (e.target === modalClone) {
            modalClone.style.display = 'none';
          }
        });
        
        // Add status button handlers for the cloned modal in parent window
        // When "Received" label is clicked
        const receivedLabel = modalClone.querySelector('label[for="2success-outlined"]');
        if (receivedLabel) {
          receivedLabel.addEventListener('click', function() {
            setTimeout(function() {
              const actualRevenue = parentDoc.getElementById('ccrevenue');
              const receivedAmt = parentDoc.getElementById('brecived');
              if (actualRevenue && receivedAmt) {
                receivedAmt.removeAttribute('readonly');
                receivedAmt.value = actualRevenue.value;
                receivedAmt.setAttribute('readonly', true);
                console.log('Received clicked (parent) - set brecived to:', actualRevenue.value);
              }
            }, 10);
          });
        }
        
        // When "Canceled" label is clicked
        const canceledLabel = modalClone.querySelector('label[for="2danger-outlined"]');
        if (canceledLabel) {
          canceledLabel.addEventListener('click', function() {
            setTimeout(function() {
              const ccashback = parentDoc.getElementById('ccashback');
              const crevenue = parentDoc.getElementById('crevenue');
              const ccrevenue = parentDoc.getElementById('ccrevenue');
              const brecived = parentDoc.getElementById('brecived');
              
              if (ccashback) ccashback.value = 0;
              if (crevenue) crevenue.value = 0;
              if (ccrevenue) ccrevenue.value = 0;
              if (brecived) {
                brecived.removeAttribute('readonly');
                brecived.value = 0;
                brecived.setAttribute('readonly', true);
              }
              console.log('Canceled clicked (parent) - set all values to 0');
            }, 10);
          });
        }
        
        // When "Raised Invoice" checkbox is clicked
        const invoiceCheckbox = modalClone.querySelector('#update_invoice_checkbox');
        if (invoiceCheckbox) {
          invoiceCheckbox.addEventListener('change', function() {
            if (this.checked) {
              const actualRevenue = parentDoc.getElementById('ccrevenue');
              const invoiceRaised = parentDoc.getElementById('invoice_raised');
              if (actualRevenue && invoiceRaised) {
                invoiceRaised.value = actualRevenue.value;
                console.log('Invoice checkbox clicked (parent) - set invoice_raised to:', actualRevenue.value);
              }
            }
          });
        }
        
        return; // Exit early since we're using parent modal
      } catch (error) {
        // Fall through to show in iframe if parent access fails
      }
    }
    
    // Show modal in current window (if not in iframe or if parent access failed)
    modal.style.display = 'flex';
    modal.style.visibility = 'visible';
    modal.style.opacity = '1';
    modal.style.pointerEvents = 'auto';
    modal.setAttribute('style', modal.getAttribute('style') + '; display: flex !important; visibility: visible !important; opacity: 1 !important; z-index: 99999 !important;');
    
    // Force check after a short delay
    setTimeout(() => {
      // Force show again if still hidden
      if (window.getComputedStyle(modal).display === 'none') {
        modal.style.setProperty('display', 'flex', 'important');
        modal.style.setProperty('visibility', 'visible', 'important');
        modal.style.setProperty('opacity', '1', 'important');
        modal.style.setProperty('z-index', '99999', 'important');
      }
    }, 100);
    
  } catch (error) {
    Swal.fire({
      toast: true,
      position: 'bottom',
      icon: 'error',
      title: 'Error loading booking data: ' + error.message,
      showConfirmButton: false,
      timer: 3000,
      timerProgressBar: true
    });
  }
};
</script>

<style>
/* Hide leads header when page is embedded in superadmin property-bookings iframe */
.embedded-main .header.leads-header,
header.header.leads-header {
  display: none !important;
  visibility: hidden !important;
  opacity: 0 !important;
  height: 0 !important;
  overflow: hidden !important;
  margin: 0 !important;
  padding: 0 !important;
}

/* Contact Details - Truncate text with ellipsis */
.detail-item {
  display: flex;
  align-items: baseline;
  gap: 0.75rem;
  margin-bottom: 0.75rem;
  width: 100%;
  position: relative;
}

.detail-icon-wrapper {
  flex-shrink: 0;
  width: 32px;
  height: 32px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
}

.detail-icon-wrapper.green {
  background: #d1fae5;
  color: black;
}

.detail-icon-wrapper.blue {
  background: #dbeafe;
  color: black;
}

.detail-text {
  flex: 1;
  font-size: 14px;
  color: #1e293b;
  font-weight: 500;
  line-height: 1.5;
  word-break: break-word;
  position: relative;
  z-index: 10;
  pointer-events: auto;
}

.detail-text.truncated {
  display: block;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  cursor: pointer;
  transition: all 0.2s;
  max-width: 100%;
}

.detail-text.truncated:hover,
.detail-value.truncated:hover {
  color: #2563eb;
  text-decoration: underline;
}

.detail-text.truncated,
.detail-value.truncated {
  cursor: pointer !important;
  user-select: none;
}

.detail-text.expanded,
.detail-value.expanded {
  cursor: pointer !important;
  user-select: none;
}

.detail-text.expanded,
.detail-value.expanded {
  white-space: normal !important;
  word-wrap: break-word !important;
  word-break: break-word !important;
  cursor: pointer;
  line-height: 1.6;
  display: block;
  max-width: none !important;
  overflow: visible !important;
  text-overflow: clip !important;
  z-index: 10;
  position: relative;
  pointer-events: auto;
}

.detail-value.truncated {
  display: block;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  cursor: pointer;
  z-index: 10;
  position: relative;
  pointer-events: auto;
}

body.dark-mode .detail-text,
body.dark-mode .detail-value {
  color: #e2e8f0;
}

@media (max-width: 768px) {
  .detail-item {
    gap: 0.5rem;
  }
  
  .detail-icon-wrapper {
    width: 28px;
    height: 28px;
  }
  
  .detail-icon-wrapper svg {
    width: 14px;
    height: 14px;
  }
  
  .detail-text {
    font-size: 13px;
  }
}

/* Dropdown Container Styles - for Assigned User Dropdown */
.dropdown-container {
  position: relative;
}

.dropdown-options {
  display: none;
  border: 1px solid #ccc;
  list-style-type: none;
  margin: 0;
  padding: 0;
  max-height: 200px;
  overflow-y: auto;
  position: absolute;
  width: 100%;
  background-color: #fff;
  z-index: 200000;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  border-radius: 0.375rem;
}

.dropdown-options li,
.unique-option {
  padding: 10px;
  cursor: pointer;
  transition: background-color 0.2s ease;
}

.dropdown-options li:hover,
.unique-option:hover {
  background-color: #f1f5f9;
}

#unique_searchInput {
  width: 100%;
  padding: 0.5rem;
  border: 1px solid #d1d5db;
  border-radius: 0.375rem;
  font-size: 14px;
}

#unique_searchInput:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

[data-theme="dark"] .dropdown-options {
  background-color: #1e293b;
  border-color: #475569;
}

[data-theme="dark"] .dropdown-options li,
[data-theme="dark"] .unique-option {
  color: #e2e8f0;
}

[data-theme="dark"] .dropdown-options li:hover,
[data-theme="dark"] .unique-option:hover {
  background-color: #334155;
}

[data-theme="dark"] #unique_searchInput {
  background-color: #1e293b;
  border-color: #475569;
  color: #e2e8f0;
}
</style>

<script>
// City Filter Change Handler
function handleCityFilterChange(selectedCity) {
  const currentUrl = new URL(window.location.href);
  
  // If "All Cities" is selected (empty value), remove the city parameter
  if (!selectedCity || selectedCity === '') {
    currentUrl.searchParams.delete('city');
  } else {
    // Set the selected city as a parameter
    currentUrl.searchParams.set('city', selectedCity);
  }
  
  // Reload the page with the new URL
  window.location.href = currentUrl.toString();
}

function handleYearFilterChange(selectedYear) {
  const currentUrl = new URL(window.location.href);
  
  // If "All Years" is selected, remove the year parameter
  if (!selectedYear || selectedYear === '' || selectedYear === 'all') {
    currentUrl.searchParams.delete('year');
  } else {
    // Set the selected year as a parameter
    currentUrl.searchParams.set('year', selectedYear);
  }
  
  // Reload the page with the new URL
  window.location.href = currentUrl.toString();
}

// Global function for expanding/collapsing detail text (higher priority than addEventListener)
window.toggleDetailText = function(element) {
  console.log('[TOGGLE] Function called on element');
  
  if (element.classList.contains('truncated')) {
    console.log('[TOGGLE] Expanding');
    const originalText = element.getAttribute('data-original-text');
    if (originalText.includes(',')) {
      const items = originalText.split(',').map(item => item.trim()).filter(item => item);
      element.innerHTML = items.join('<br>');
    } else {
      element.textContent = originalText;
    }
    element.classList.remove('truncated');
    element.classList.add('expanded');
    element.setAttribute('title', 'Click to collapse');
  } else if (element.classList.contains('expanded')) {
    console.log('[TOGGLE] Collapsing');
    const originalText = element.getAttribute('data-original-text');
    element.textContent = originalText;
    element.classList.remove('expanded');
    element.classList.add('truncated');
    element.setAttribute('title', 'Click to expand');
  }
  
  return false; // Prevent event propagation
};

// Contact Details & Value Text Truncation and Expansion
function initContactTextTruncation() {
  // Find all detail-text and detail-value elements that haven't been initialized yet
  const targets = document.querySelectorAll('.detail-text:not([data-initialized]), .detail-value:not([data-initialized])');
  
  console.log('[TRUNCATION] Found', targets.length, 'uninitialized targets');
  
  if (targets.length === 0) {
    console.warn('[TRUNCATION] No targets found! All elements:', document.querySelectorAll('.detail-text, .detail-value').length);
  }
  
  targets.forEach((textElem, index) => {
    const text = textElem.textContent.trim();
    
    // Lower thresholds specifically for smaller screens
    const isMobile = window.innerWidth <= 1024;
    const threshold = isMobile ? 15 : 30;

    console.log('[TRUNCATION] Element', index, ':', text.substring(0, 30), 'length:', text.length, 'will truncate:', (text.includes(',') || text.length > threshold));

    if (text.includes(',') || text.length > threshold) {
      // Store original text
      textElem.setAttribute('data-original-text', text);
      
      // Mark as initialized to prevent duplicate processing
      textElem.setAttribute('data-initialized', 'true');
      
      // Add truncated class
      textElem.classList.add('truncated');
      
      console.log('[TRUNCATION] Element', index, 'initialized with classes:', textElem.className);
      
      // Use onclick attribute for highest priority (runs before addEventListener)
      textElem.setAttribute('onclick', 'return window.toggleDetailText(this);');
      textElem.setAttribute('title', 'Click to expand');
      
      console.log('[TRUNCATION] onclick attribute attached to element', index);
    }
  });
  
  console.log('[TRUNCATION] Initialization complete');
}

// Run on page load
document.addEventListener('DOMContentLoaded', initContactTextTruncation);

// Also run when new rows are expanded (for dynamically loaded content)
const observer = new MutationObserver(function(mutations) {
  mutations.forEach(function(mutation) {
    if (mutation.type === 'attributes' && mutation.attributeName === 'style') {
      const target = mutation.target;
      // Check if a detail row was just made visible
      if (target.id && target.id.startsWith('detail-') && target.style.display !== 'none') {
        // Re-initialize truncation for this newly visible content
        setTimeout(initContactTextTruncation, 100);
      }
    }
  });
});

// Observe all detail rows
document.addEventListener('DOMContentLoaded', function() {
  const detailRows = document.querySelectorAll('[id^="detail-"]');
  detailRows.forEach(row => {
    observer.observe(row, { attributes: true });
  });
});

</script>

<div id="showAlert"></div>

<!-- Main Content -->
<div class="content <?php echo $isEmbed ? 'embedded-main' : ''; ?>">
    <!-- Filters - Top Left -->
    <div style="margin-bottom: 0.3rem; display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
        <!-- City Filter -->
        <select id="cityFilterSelect" class="city-filter-select" onchange="handleCityFilterChange(this.value)" style="padding: 8px 12px; border-radius: 8px; background: #dcfce7; border: 1px solid #86efac; font-size: 14px; cursor: pointer; min-width: 150px;">
            <option value="" <?php echo empty($selectedCity) ? 'selected' : ''; ?>>All Cities</option>
            <?php foreach ($cityOptions as $cityOption): ?>
                <option value="<?php echo htmlspecialchars($cityOption); ?>" <?php echo ($cityOption === $selectedCity) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($cityOption); ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <!-- Financial Year Filter -->
        <select id="yearFilterSelect" class="city-filter-select" onchange="handleYearFilterChange(this.value)" style="padding: 8px 12px; border-radius: 8px; background: #dcfce7; border: 1px solid #86efac; font-size: 14px; cursor: pointer; min-width: 150px;">
            <option value="all" <?php echo ($selectedYear === 'all') ? 'selected' : ''; ?>>All Years</option>
            <?php foreach ($yearOptions as $yearOption): ?>
                <option value="<?php echo htmlspecialchars($yearOption); ?>" <?php echo ($yearOption === $selectedYear) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($yearOption); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card green" id="cardOverallBookings" data-tooltip="<?php echo number_format($overall_stats['booking'] ?? 0); ?>">
            <div class="stat-title">Overall Bookings</div>
            <div class="stat-value" id="statOverallBookings"><?php echo $overall_stats['booking'] ?? 0; ?></div>
        </div>
        <div class="stat-card green" id="cardOverallAgreement" data-tooltip="₹<?php echo number_format($overall_stats['agreement'] ?? 0); ?>">
            <div class="stat-title">Overall Agreement</div>
            <div class="stat-value" id="statOverallAgreement"><?php echo formatCompactNumber($overall_stats['agreement'] ?? 0); ?></div>
        </div>
        <div class="stat-card purple" id="cardTotalRevenue" data-tooltip="₹<?php echo number_format($overall_stats['totalRevenue'] ?? 0); ?>">
            <div class="stat-title">Total Revenue</div>
            <div class="stat-value" id="statTotalRevenue"><?php echo formatCompactNumber($overall_stats['totalRevenue'] ?? 0); ?></div>
        </div>
        <div class="stat-card teal" id="cardActualRevenue" data-tooltip="₹<?php echo number_format($overall_stats['actualRevenue'] ?? 0); ?>">
            <div class="stat-title">Actual Revenue</div>
            <div class="stat-value" id="statActualRevenue"><?php echo formatCompactNumber($overall_stats['actualRevenue'] ?? 0); ?></div>
        </div>
        <div class="stat-card orange" id="cardReceivedAmount" data-tooltip="₹<?php echo number_format($overall_stats['recivedRevenue'] ?? 0); ?>">
            <div class="stat-title">Received Amount</div>
            <div class="stat-value" id="statReceivedAmount"><?php echo formatCompactNumber($overall_stats['recivedRevenue'] ?? 0); ?></div>
        </div>
        <div class="stat-card orange" id="cardFinalRemaining" data-tooltip="₹<?php echo number_format($overall_stats['finalRemaining'] ?? 0); ?>">
            <div class="stat-title">Final Remaining</div>
            <div class="stat-value" id="statFinalRemaining"><?php echo formatCompactNumber($overall_stats['finalRemaining'] ?? 0); ?></div>
        </div>
    </div>

    <!-- Search Section -->
    <div class="search-section">
        <div class="search-container">
            <div class="search-filters">
                <div class="search-input-wrapper">
                    <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"></circle>
                        <path d="m21 21-4.35-4.35"></path>
                    </svg>
                    <input type="text" class="search-input" placeholder="Search bookings..." id="mainSearch" autocomplete="off">
                </div>
                
                <button class="filter-button quick-action-button" id="calculatorButton" title="Calculator" aria-label="Open Calculator">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="4" y="2" width="16" height="20" rx="2"></rect>
                        <line x1="8" y1="6" x2="16" y2="6"></line>
                        <line x1="8" y1="11" x2="8" y2="11"></line>
                        <line x1="12" y1="11" x2="12" y2="11"></line>
                        <line x1="16" y1="11" x2="16" y2="11"></line>
                        <line x1="8" y1="15" x2="8" y2="15"></line>
                        <line x1="12" y1="15" x2="12" y2="15"></line>
                        <line x1="16" y1="15" x2="16" y2="15"></line>
                    </svg>
                </button>
                <button class="filter-button quick-action-button" id="downloadCsvButton" title="Download CSV" aria-label="Download CSV">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 3v12"></path>
                        <path d="m7 10 5 5 5-5"></path>
                        <path d="M5 21h14"></path>
                    </svg>
                </button>
                <button class="filter-button" id="filterButton">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
                    </svg>
                    <span>Filters</span>
                </button>
                <button class="add-booking-button" id="addBookingButton">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right: 4px;">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    <span>Add Booking</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Filter Summary Banner -->
    <div class="filter-summary-banner" id="filterSummaryBanner" style="display: none;">
        <div class="filter-summary-content">
            <h2 class="filter-summary-title" id="filterSummaryTitle">Financial Year - All Years</h2>
            <div class="filter-summary-stats">
                <div class="filter-stat-item">
                    <span class="filter-stat-label">Total Bookings :-</span>
                    <span class="filter-stat-value" id="filterStatBookings">0</span>
                </div>
                <div class="filter-stat-item">
                    <span class="filter-stat-label">Total Revenue :-</span>
                    <span class="filter-stat-value" id="filterStatTotalRevenue">₹0</span>
                </div>
                <div class="filter-stat-item">
                    <span class="filter-stat-label">Actual Revenue :-</span>
                    <span class="filter-stat-value" id="filterStatActualRevenue">₹0</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Table -->
    <div class="table-card">
        <div class="table-container">
            <table class="table main-bookings-table">
                <thead class="table-head">
                    <tr>
                        <th>Financial Year/Bookings</th>
                        <th class="revenue-col">Actual Revenue</th>
                        <th class="remaining-col">Remaining</th>
                        <th class="build-incentive-col">Build Incentive</th>
                        <th class="build-incentive-col">Total Paid</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="mainTableBody">
                    <?php 
                    // Use the pre-filtered $displayYearlyData from earlier
                    foreach ($displayYearlyData as $index => $yearData): 
                    ?>
                        <tr class="table-row" onclick="toggleRow('<?php echo htmlspecialchars($yearData['year']); ?>')">
                            <!-- Financial Year + Calendar Icon -->
                            <td class="month-cell">
                                <span class="icon calendar">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512" class="calendar-icon" width="18" height="18" fill="currentColor">
                                        <path d="M0 464c0 26.5 21.5 48 48 48h352c26.5 0 48-21.5 48-48V192H0v272zm320-196c0-6.6 5.4-12 12-12h40c6.6 0 12 5.4 12 12v40c0 6.6-5.4 12-12 12h-40c-6.6 0-12-5.4-12-12v-40zm0 128c0-6.6 5.4-12 12-12h40c6.6 0 12 5.4 12 12v40c0 6.6-5.4 12-12 12h-40c-6.6 0-12-5.4-12-12v-40zM192 268c0-6.6 5.4-12 12-12h40c6.6 0 12 5.4 12 12v40c0 6.6-5.4 12-12 12h-40c-6.6 0-12-5.4-12-12v-40zm0 128c0-6.6 5.4-12 12-12h40c6.6 0 12 5.4 12 12v40c0 6.6-5.4 12-12 12h-40c-6.6 0-12-5.4-12-12v-40zM64 268c0-6.6 5.4-12 12-12h40c6.6 0 12 5.4 12 12v40c0 6.6-5.4 12-12 12H76c-6.6 0-12-5.4-12-12v-40zm0 128c0-6.6 5.4-12 12-12h40c6.6 0 12 5.4 12 12v40c0 6.6-5.4 12-12 12H76c-6.6 0-12-5.4-12-12v-40zM400 64h-48V16c0-8.8-7.2-16-16-16h-32c-8.8 0-16 7.2-16 16v48H160V16c0-8.8-7.2-16-16-16h-32c-8.8 0-16 7.2-16 16v48H48C21.5 64 0 85.5 0 112v48h448v-48c0-26.5-21.5-48-48-48z"/>
                                    </svg>
                                </span>
                                <?php echo htmlspecialchars($yearData['year']) . ' (' . $yearData['bookings'] . '/' . $yearData['total_raised'] . ')'; ?>
                            </td>

                            <!-- Actual Revenue (kept in main table) -->
                            <td class="actual-revenue-cell amount <?php echo $yearData['actualRevenue'] > 0 ? 'positive' : 'zero'; ?>">
                                <i class="fas fa-wallet"></i> <?php echo formatCompactNumber($yearData['actualRevenue']); ?>
                            </td>

                            <!-- Remaining (shown on desktop ≥1441px) -->
                            <td class="remaining-col remaining-amount-cell amount zero">
                                <i class="fas fa-coins"></i> <?php echo formatCompactNumber($yearData['actualRevenue'] - $yearData['invoice_raised']); ?>
                            </td>

                            <!-- Build Incentive -->
                            <td class="build-incentive-cell amount zero">
                                <i class="fas fa-coins"></i> <?php echo formatCompactNumber($yearData['totalAmtPay'] + $yearData['totalPaidmanager']); ?>
                            </td>

                            <!-- Total Paid -->
                            <td class="build-incentive-cell amount zero">
                                <i class="fas fa-gift"></i> <?php echo formatCompactNumber($yearData['totalPaid']); ?>
                            </td>

                            <td>
                                <svg id="expand-<?php echo htmlspecialchars($yearData['year']); ?>" class="expand-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" onclick="event.stopPropagation(); toggleRow('<?php echo htmlspecialchars($yearData['year']); ?>')">
                                    <polyline points="9,18 15,12 9,6"></polyline>
                                </svg>
                            </td>
                        </tr>

                        <!-- Nested Row for Year Details -->
                        <tr id="nested-<?php echo htmlspecialchars($yearData['year']); ?>" style="display: none;">
                            <td colspan="6">
                                <div class="nested-section">
                                    <!-- Financial Year Header -->
                                    <div class="financial-year-header">
                                        <h3 class="financial-year-title">Financial Year - <?php echo htmlspecialchars($yearData['year']); ?></h3>
                                        <div class="status-counts">
                                            <span class="status-count processing">Total Processing :- <?php echo $yearData['processing_count']; ?></span>
                                            <span class="status-count cancelled">Total Cancelled :- <?php echo $yearData['canceled_count']; ?></span>
                                            <span class="status-count received">Total Received :- <?php echo $yearData['received_count']; ?></span>
                                        </div>
                                    </div>
                                    <!-- Mobile summary chips for hidden main columns -->
                                    <div class="mobile-year-stats">
                                        <div class="stat-chip"><span>Total Rev</span><strong id="year-summary-revenue-<?php echo htmlspecialchars($yearData['year']); ?>"><?php echo formatCompactNumber($yearData['totalRevenue']); ?></strong></div>
                                        <div class="stat-chip"><span>Remaining Rev</span><strong id="year-summary-remaining-<?php echo htmlspecialchars($yearData['year']); ?>"><?php echo formatCompactNumber($yearData['actualRevenue'] - $yearData['invoice_raised']); ?></strong></div>
                                        <div class="stat-chip"><span>Received Amt</span><strong id="year-summary-recent-<?php echo htmlspecialchars($yearData['year']); ?>"><?php echo formatCompactNumber($yearData['recivedRevenue']); ?></strong></div>
                                        <div class="stat-chip"><span>Paid Salary</span><strong id="year-summary-salary-<?php echo htmlspecialchars($yearData['year']); ?>"><?php echo formatCompactNumber($yearData['totalPaidsalary']); ?></strong></div>
                                        <div class="stat-chip"><span>Expenses</span><strong id="year-summary-other-<?php echo htmlspecialchars($yearData['year']); ?>"><?php echo formatCompactNumber($yearData['totalExpensesAmt']); ?></strong></div>
                                        <div class="stat-chip"><span>Build Incentive</span><strong id="year-summary-build-<?php echo htmlspecialchars($yearData['year']); ?>"><?php echo formatCompactNumber($yearData['totalAmtPay'] + $yearData['totalPaidmanager']); ?></strong></div>
                                        <div class="stat-chip"><span>Total Paid Amt</span><strong id="year-summary-paid-<?php echo htmlspecialchars($yearData['year']); ?>"><?php echo formatCompactNumber($yearData['totalPaid']); ?></strong></div>
                                    </div>
                                
                                    <div class="nested-controls">
                                        <div class="nested-search-wrapper">
                                            <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <circle cx="11" cy="11" r="8"></circle>
                                                <path d="m21 21-4.35-4.35"></path>
                                            </svg>
                                            <input type="text" class="nested-search" placeholder="Search details..." oninput="handleNestedSearch('<?php echo htmlspecialchars($yearData['year']); ?>', this.value)" data-year="<?php echo htmlspecialchars($yearData['year']); ?>">
                                        </div>
                                        <div class="per-page-selector">
                                            <span>Show</span>
                                            <select class="per-page-select" onchange="handlePerPageChange('<?php echo htmlspecialchars($yearData['year']); ?>', this.value)">
                                                <option value="5" selected>5</option>
                                                <option value="10">10</option>
                                                <option value="25">25</option>
                                                <option value="50">50</option>
                                            </select>
                                            <span>per page</span>
                                        </div>
                                    </div>

                                    <div class="compact-table">
                                        <table style="width: 100%; border-collapse: collapse;">
                                            <thead class="compact-table-head">
                                                <tr>
                                                    <th>S.No</th>
                                                    <th class="unit-col">Unit</th>
                                                    <th class="type-col">Type</th>
                                                    <th>Customer</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody id="nested-data-<?php echo htmlspecialchars($yearData['year']); ?>">
                                                <?php
                                                $counter = 1;
                                                if (isset($groupedRows[$yearData['year']])) {
                                                    foreach ($groupedRows[$yearData['year']] as $row):
                                                        // City filtering: Skip rows that don't match selected city
                                                        if (!empty($GLOBALS['selectedCity'])) {
                                                            $rowCity = trim($row['city'] ?? '');
                                                            if ($rowCity !== $GLOBALS['selectedCity']) {
                                                                continue; // Skip this row if city doesn't match
                                                            }
                                                        }
                                                        
                                                        $row_id = $row['id'];
                                                        $checkbox_value = $db->getCheckboxValue($row_id);
                                                        $checkbox_cashBack = $db->getCheckboxCashBack($row_id);
                                                        
                                                        // Hide rows beyond the first 5 by default (first page)
                                                        $rowStyle = $counter > 5 ? 'style="display: none;"' : '';
                                                        
                                                        // Determine status badge class
                                                        $statusClass = '';
                                                        if ($row['astatus'] === 'Processing') {
                                                            $statusClass = 'badge-orange';
                                                        } elseif ($row['astatus'] === 'Received') {
                                                            $statusClass = 'badge-emerald';
                                                        } elseif ($row['astatus'] === 'Canceled') {
                                                            $statusClass = 'badge-red';
                                                        } elseif ($row['astatus'] === 'Completed') {
                                                            $statusClass = 'badge-emerald';
                                                        } elseif ($row['astatus'] === 'Active') {
                                                            $statusClass = 'badge-blue';
                                                        } elseif ($row['astatus'] === 'Pending') {
                                                            $statusClass = 'badge-amber';
                                                        } else {
                                                            $statusClass = 'badge-outline';
                                                        }

                                                        // Determine type badge class
                                                        $projectTypeValue = strtolower($row['project_type'] ?? '');
                                                        if (strpos($projectTypeValue, '3bhk') !== false) {
                                                            $typeClass = 'badge-blue';
                                                        } elseif (strpos($projectTypeValue, '2bhk') !== false) {
                                                            $typeClass = 'badge-purple';
                                                        } elseif (strpos($projectTypeValue, '4bhk') !== false) {
                                                            $typeClass = 'badge-orange';
                                                        } else {
                                                            $typeClass = 'badge-outline';
                                                        }
                                                        $unitSafe = $row['unit_no'] ?? 'N/A';
                                                        $projectTypeSafe = $row['project_type'] ?? 'N/A';
                                                        $customerNameSafe = $row['customer_name'] ?? 'N/A';
                                                        $customerContactSafe = $row['contact_number'] ?? 'N/A';
                                                        $statusSafe = $row['astatus'] ?? 'N/A';
                                                        ?>
                                                        <tr
                                                            <?php echo $rowStyle; ?>
                                                            class="compact-row"
                                                            data-id="<?php echo htmlspecialchars($row['id']); ?>"
                                                            data-booking-date="<?php echo htmlspecialchars($row['booking_date'] ?? ''); ?>"
                                                            data-booking-month="<?php echo htmlspecialchars($row['booking_month'] ?? ''); ?>"
                                                            data-builder="<?php echo htmlspecialchars($row['builder'] ?? ''); ?>"
                                                            data-project="<?php echo htmlspecialchars($row['project'] ?? ''); ?>"
                                                            data-customer-name="<?php echo htmlspecialchars($customerNameSafe); ?>"
                                                            data-contact-number="<?php echo htmlspecialchars($customerContactSafe); ?>"
                                                            data-email="<?php echo htmlspecialchars($row['email_id'] ?? ''); ?>"
                                                            data-project-type="<?php echo htmlspecialchars($projectTypeSafe); ?>"
                                                            data-unit="<?php echo htmlspecialchars($unitSafe); ?>"
                                                            data-size="<?php echo htmlspecialchars($row['size'] ?? ''); ?>"
                                                            data-city="<?php echo htmlspecialchars($row['city'] ?? ''); ?>"
                                                            data-agreement="<?php echo htmlspecialchars($row['agreement_value'] ?? ''); ?>"
                                                            data-commission="<?php echo htmlspecialchars($row['cashback'] ?? $row['commission_percent'] ?? ''); ?>"
                                                            data-revenue="<?php echo htmlspecialchars($row['revenue'] ?? ''); ?>"
                                                            data-cashback="<?php echo htmlspecialchars($row['cashback'] ?? ''); ?>"
                                                            data-actual-revenue="<?php echo htmlspecialchars($row['crevenue'] ?? ''); ?>"
                                                            data-status="<?php echo htmlspecialchars($statusSafe); ?>"
                                                            data-received="<?php echo htmlspecialchars($row['recived_amt'] ?? ''); ?>"
                                                        >
                                                            <td>
                                                                <span class="badge badge-outline"><?php echo htmlspecialchars($row['id']); ?></span>
                                                            </td>
                                                            <td class="unit-cell">
                                                                <span class="badge badge-emerald"><?php echo htmlspecialchars($unitSafe); ?></span>
                                                            </td>
                                                            <td class="type-cell">
                                                                <span class="badge <?php echo $typeClass; ?>"><?php echo strtoupper(htmlspecialchars($projectTypeSafe)); ?></span>
                                                            </td>
                                                            <td>
                                                                <div class="customer-info">
                                                                    <div class="customer-avatar">
                                                                        <?php echo strtoupper(substr($customerNameSafe ?: 'U', 0, 1)); ?>
                                                                    </div>
                                                                    <div class="customer-details">
                                                                        <div class="customer-name"><?php echo htmlspecialchars($customerNameSafe); ?></div>
                                                                        <div class="customer-contact"><?php echo htmlspecialchars($customerContactSafe); ?></div>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <span class="badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($statusSafe); ?></span>
                                                            </td>
                                                            <td>
                                                                <div style="display: flex; gap: 5px; align-items: center; justify-content: start;">
                                                                    <button class="btn btn-toggle btn-sm" onclick="handleShowMore('detail-<?php echo htmlspecialchars($yearData['year']); ?>-<?php echo $row['id']; ?>', event)">Show More</button>
                                                                </div>
                                                                <?php
                                                                // Store Edit/Download buttons data for use in expanded section
                                                                $editButtonHtml = '<a href="#" id="' . $row['id'] . '" class="btn btn-sm editLink" onclick="event.preventDefault(); event.stopPropagation(); editUser(\'' . $row['id'] . '\'); return false;" style="padding: 6px 12px; background: white; border: 1px solid #ddd; border-radius: 4px; text-decoration: none; color: #1a73e8; display: inline-flex; align-items: center; justify-content: center;"><i class="fa fa-edit" style="font-size: 14px;"></i></a>';
                                                                
                                                                $downloadButtonHtml = '';
                                                                $filePath = $row['document_path'] ?? '';
                                                                // Adjust path to check file existence from userlogin6 directory
                                                                $fileCheckPath = $filePath;
                                                                if (!empty($filePath)) {
                                                                    // If path starts with 'uploads_form/', prepend '../superadmin/'
                                                                    if (strpos($filePath, 'uploads_form/') === 0) {
                                                                        $fileCheckPath = '../superadmin/' . $filePath;
                                                                    }
                                                                }
                                                                if (!empty($filePath) && file_exists($fileCheckPath)) {
                                                                    // Use relative path from current location
                                                                    $downloadPath = (strpos($filePath, 'uploads_form/') === 0) ? '../superadmin/' . htmlspecialchars($filePath) : htmlspecialchars($filePath);
                                                                    $downloadButtonHtml = '<a href="' . $downloadPath . '" target="_blank" class="btn" download style="padding: 6px 12px; background: white; border: 1px solid #ddd; border-radius: 4px; text-decoration: none; color: #1a73e8; display: inline-flex; align-items: center; justify-content: center;"><i class="fa fa-download" style="font-size: 14px;"></i></a>';
                                                                }
                                                                ?>
                                                            </td>
                                                        </tr>
                                                        <tr id="detail-<?php echo htmlspecialchars($yearData['year']); ?>-<?php echo $row['id']; ?>" style="display: none;">
                                                            <td colspan="6">
                                                                <div class="expanded-details">
                                                                    <div class="details-grid">
                                                                        <div>
                                                                            <h6 class="section-title">
                                                                                <span class="icon-wrapper icon-blue">
                                                                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                                        <circle cx="12" cy="7" r="4"></circle>
                                                                                        <path d="M5.5 21a7.5 7.5 0 0 1 13 0"></path>
                                                                                    </svg>
                                                                                </span>
                                                                                Contact Details
                                                                            </h6>
                                                                            <div class="detail-section">
                                                                                <div class="property-det contact-info">
                                                                                    <div class="detail-item">
                                                                                        <div class="detail-icon-wrapper green">
                                                                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                                                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                                                                                            </svg>
                                                                                        </div>
                                                                                        <span class="detail-text"><?php echo htmlspecialchars($row['contact_number'] ?? ''); ?></span>
                                                                                    </div>
                                                                                    <div class="detail-item">
                                                                                        <div class="detail-icon-wrapper blue">
                                                                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                                                                                <polyline points="22,6 12,13 2,6"></polyline>
                                                                                            </svg>
                                                                                        </div>
                                                                                        <span class="detail-text"><?php echo htmlspecialchars($row['email_id'] ?? ''); ?></span>
                                                                                    </div>
                                                                                </div>
                                                                                <div style="padding-top: 0.75rem; border-top: 1px solid #f1f5f9; margin-top: 0.75rem;">
                                                                                    <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 16px;">
                                                                                        <!-- Sales Person Section -->
                                                                                        <div style="flex: 1;">
                                                                                            <div class="detail-label" style="margin: 0 0 6px 0; color: #666; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700;">SALES PERSON</div>
                                                                                            <div style="display: flex; align-items: center; gap: 10px;">
                                                                                                <div class="detail-value" style="font-size: 13px; color: #333; font-weight: 700;"><?php echo htmlspecialchars($row['source_table'] ?? ''); ?></div>
                                                                                                <?php 
                                                                                                // Status indicator based on booking status
                                                                                                $status = $row['astatus'] ?? '';
                                                                                                if ($status === 'Received' || $status === 'Completed') {
                                                                                                    echo '<div style="width: 28px; height: 28px; background: #22c55e; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                                                                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                                                                                            <polyline points="20 6 9 17 4 12"></polyline>
                                                                                                        </svg>
                                                                                                    </div>';
                                                                                                } elseif ($status === 'Processing' || $status === 'Pending') {
                                                                                                    echo '<div style="width: 28px; height: 28px; background: #ef4444; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                                                                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                                                                                            <line x1="18" y1="6" x2="6" y2="18"></line>
                                                                                                            <line x1="6" y1="6" x2="18" y2="18"></line>
                                                                                                        </svg>
                                                                                                    </div>';
                                                                                                } elseif ($status === 'Canceled') {
                                                                                                    echo '<div style="width: 28px; height: 28px; background: #ef4444; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                                                                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                                                                                            <line x1="18" y1="6" x2="6" y2="18"></line>
                                                                                                            <line x1="6" y1="6" x2="18" y2="18"></line>
                                                                                                        </svg>
                                                                                                    </div>';
                                                                                                }
                                                                                                ?>
                                                                                            </div>
                                                                                        </div>
                                                                                        
                                                                                        <!-- Actions Section -->
                                                                                        <div style="flex-shrink: 0;">
                                                                                            <div class="detail-label" style="font-size: 11px;">ACTIONS</div>
                                                                                            <div style="display: flex; align-items: center; gap: 8px;">
                                                                                                <?php 
                                                                                                // Edit button - icon only
                                                                                                $smallEditButtonHtml = '<a href="#" id="' . $row['id'] . '" class="btn btn-sm editLink" onclick="event.preventDefault(); event.stopPropagation(); editUser(\'' . $row['id'] . '\'); return false;" style="padding: 8px 10px; background: white; border: 1px solid #ddd; border-radius: 4px; text-decoration: none; color: #1a73e8; display: inline-flex; align-items: center; justify-content: center;"><i class="fa fa-edit" style="font-size: 14px;"></i></a>';
                                                                                                echo $smallEditButtonHtml;
                                                                                                
                                                                                                // Download button - icon only, disabled if no file exists
                                                                                                // Adjust path to check file existence from userlogin6 directory
                                                                                                $fileCheckPath = $filePath;
                                                                                                if (!empty($filePath)) {
                                                                                                    // If path starts with 'uploads_form/', prepend '../superadmin/'
                                                                                                    if (strpos($filePath, 'uploads_form/') === 0) {
                                                                                                        $fileCheckPath = '../superadmin/' . $filePath;
                                                                                                    }
                                                                                                }
                                                                                                $hasFile = !empty($filePath) && file_exists($fileCheckPath);
                                                                                                if ($hasFile) {
                                                                                                    // Use relative path from current location
                                                                                                    $downloadPath = (strpos($filePath, 'uploads_form/') === 0) ? '../superadmin/' . htmlspecialchars($filePath) : htmlspecialchars($filePath);
                                                                                                    $smallDownloadButtonHtml = '<a href="' . $downloadPath . '" target="_blank" class="btn" download style="padding: 8px 10px; background: white; border: 1px solid #ddd; border-radius: 4px; text-decoration: none; color: #1a73e8; display: inline-flex; align-items: center; justify-content: center;"><i class="fa fa-download" style="font-size: 14px;"></i></a>';
                                                                                                } else {
                                                                                                    $smallDownloadButtonHtml = '<button disabled class="btn" style="padding: 8px 10px; background: #f3f4f6; border: 1px solid #ddd; border-radius: 4px; color: #9ca3af; display: inline-flex; align-items: center; justify-content: center; cursor: not-allowed;"><i class="fa fa-download" style="font-size: 14px;"></i></button>';
                                                                                                }
                                                                                                echo $smallDownloadButtonHtml;
                                                                                                ?>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div>
                                                                            <h6 class="section-title">
                                                                                <span class="icon-wrapper icon-green">
                                                                                    <svg xmlns="http://www.w3.org/2000/svg" x="0px" y="0px" width="24" height="24" viewBox="0,0,256,256">
                                                                                        <g fill="#ffffff" fill-rule="nonzero" stroke="none" stroke-width="1" stroke-linecap="butt" stroke-linejoin="miter" stroke-miterlimit="10" stroke-dasharray="" stroke-dashoffset="0" font-family="none" font-weight="none" font-size="none" text-anchor="none" style="mix-blend-mode: normal">
                                                                                            <g transform="scale(10.66667,10.66667)">
                                                                                                <path d="M12,2.09961l-11,9.90039h3v9h7v-6h2v6h7v-9h3zM12,4.79102l6,5.40039v0.80859v8h-3v-6h-6v6h-3v-8.80859z"></path>
                                                                                            </g>
                                                                                        </g>
                                                                                    </svg>
                                                                                </span>
                                                                                Property Details
                                                                            </h6>
                                                                            <div class="detail-section">
                                                                                <div class="property-det">
                                                                                    <div>
                                                                                        <div class="detail-label">Unit No</div>
                                                                                        <div class="detail-value"><?php echo htmlspecialchars($row['unit_no'] ?? ''); ?></div>
                                                                                    </div>
                                                                                    <div>
                                                                                        <div class="detail-label">Type</div>
                                                                                        <div class="detail-value"><?php echo strtoupper(htmlspecialchars($row['project_type'] ?? '')); ?></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div class="property-det">
                                                                                    <div>
                                                                                        <div class="detail-label">Builder</div>
                                                                                        <div class="detail-value"><?php echo htmlspecialchars($row['builder'] ?? ''); ?></div>
                                                                                    </div>
                                                                                    <div>
                                                                                        <div class="detail-label">Project</div>
                                                                                        <div class="detail-value"><?php echo htmlspecialchars($row['project'] ?? ''); ?></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; padding-top: 0.5rem; border-top: 1px solid #f1f5f9; margin-top: 0.75rem;">
                                                                                    <div>
                                                                                        <div class="detail-label">Size</div>
                                                                                        <div class="detail-value"><?php echo htmlspecialchars($row['size'] ?? ''); ?></div>
                                                                                    </div>
                                                                                    <div>
                                                                                        <div class="detail-label">Date</div>
                                                                                        <div class="detail-value"><?php echo htmlspecialchars($row['booking_date'] ?? ''); ?></div>
                                                                                    </div>
                                                                                </div>
                                                                                <div style="display: grid; grid-template-columns: 1fr; gap: 0.75rem; padding-top: 0.5rem; border-top: 1px solid #f1f5f9; margin-top: 0.75rem;">
                                                                                    <div>
                                                                                        <div class="detail-label">City</div>
                                                                                        <div class="detail-value"><?php echo !empty(trim($row['city'] ?? '')) ? htmlspecialchars($row['city']) : 'N/A'; ?></div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div>
                                                                            <h6 class="section-title">
                                                                                <span class="icon-wrapper icon-orange">
                                                                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                                        <line x1="12" y1="1" x2="12" y2="23"></line>
                                                                                        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                                                                                    </svg>
                                                                                </span>
                                                                                Financial Details
                                                                            </h6>
                                                                            <div class="detail-section">
                                                                                <div class="financial-item">
                                                                                    <span class="financial-label">Agreement Value</span>
                                                                                    <span class="financial-value slate">₹<?php echo number_format((float)($row['agreement_value'] ?? 0)); ?></span>
                                                                                </div>
                                                                                <div class="financial-item">
                                                                                    <span class="financial-label">
                                                                                        Total Revenue
                                                                                        <span class="commission-percent">(<?php echo number_format((float)($row['cashback'] ?? 0), 2); ?>% commission)</span>
                                                                                    </span>
                                                                                    <span class="financial-value emerald">₹<?php echo number_format((float)($row['revenue'] ?? 0)); ?></span>
                                                                                </div>
                                                                                <div class="financial-item">
                                                                                    <span class="financial-label">
                                                                                        Actual Revenue
                                                                                        <span class="cashback-percent">(<?php echo number_format((float)($row['ccashback'] ?? 0), 2); ?>% cashback)</span>
                                                                                    </span>
                                                                                    <span class="financial-value emerald">₹<?php echo number_format((float)($row['crevenue'] ?? 0)); ?></span>
                                                                                </div>
                                                                                <div class="financial-item">
                                                                                    <span class="financial-label">Received Amount</span>
                                                                                    <span class="financial-value purple">₹<?php echo number_format((float)($row['recived_amt'] ?? 0)); ?></span>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                        <?php
                                                        $counter++;
                                                    endforeach;
                                                } else {
                                                    echo '<tr><td colspan="6" style="text-align: center; padding: 1rem;">No bookings found for this financial year</td></tr>';
                                                }
                                                ?>
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="nested-pagination">
                                        <div class="nested-pagination-info">
                                            <span>Showing</span>
                                            <span id="showing-start-<?php echo htmlspecialchars($yearData['year']); ?>">0</span>
                                            <span>to</span>
                                            <span id="showing-end-<?php echo htmlspecialchars($yearData['year']); ?>">0</span>
                                            <span>of</span>
                                            <span id="showing-total-<?php echo htmlspecialchars($yearData['year']); ?>">0</span>
                                            <span>entries</span>
                                        </div>
                                        <div class="nested-pagination-controls">
                                            <button class="btn btn-outline pagination-btn btn-sm" id="prev-btn-<?php echo htmlspecialchars($yearData['year']); ?>" onclick="handleNestedPagination('<?php echo htmlspecialchars($yearData['year']); ?>', 'prev')" disabled>Previous</button>
                                            <div class="nested-page-numbers" id="page-numbers-<?php echo htmlspecialchars($yearData['year']); ?>">
                                                <button class="btn btn-primary btn-sm active-page" onclick="goToNestedPage('<?php echo htmlspecialchars($yearData['year']); ?>', 1)">1</button>
                                            </div>
                                            <button class="btn btn-outline pagination-btn btn-sm" id="next-btn-<?php echo htmlspecialchars($yearData['year']); ?>" onclick="handleNestedPagination('<?php echo htmlspecialchars($yearData['year']); ?>', 'next')">Next</button>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        </div>
    </div>

    <div class="pagination-top-row" style="text-align: center;">
        <div class="pagination-text-top pagination-text" style="display: inline-block;">Showing 0 To 0 Of 0 Entries</div>
    </div>

    <div class="pagination">
        <div class="pagination-info">
            <span>Show</span>
            <select class="pagination-select">
                <option>10</option>
                <option>25</option>
                <option>50</option>
            </select>
            <span>Entries</span>
        </div>
        <div class="pagination-controls">
            <button class="btn btn-outline btn-sm" disabled>Previous</button>
            <button class="btn btn-primary btn-sm">1</button>
            <button class="btn btn-outline btn-sm">Next</button>
        </div>
    </div>
</div>

<script>
    // State management - Make available globally for search functionality
    // CRITICAL: Set on window first, then create const reference
    // This ensures both window.groupedRows and direct groupedRows access work
    window.groupedRows = <?php echo json_encode($groupedRows); ?>;
    window.allGroupedRows = <?php echo json_encode($allGroupedRows); ?>; // Unfiltered data for downloads
    window.yearlyData = <?php echo json_encode($displayYearlyData); ?>; // Filtered yearly data
    window.allYearlyData = <?php echo json_encode($yearlyData); ?>; // All yearly data
    window.initialOverallStats = <?php echo json_encode($overall_stats); ?>;
    window.selectedYear = <?php echo json_encode($selectedYear); ?>; // Currently selected year
    window.selectedCity = <?php echo json_encode($selectedCity); ?>; // Currently selected city
    
    // Debug: Log counts to verify data is loaded
    console.log('[DATA LOADED] groupedRows years:', Object.keys(window.groupedRows));
    console.log('[DATA LOADED] allGroupedRows years:', Object.keys(window.allGroupedRows));
    console.log('[DATA LOADED] Selected year:', window.selectedYear);
    console.log('[DATA LOADED] Selected city:', window.selectedCity);
    
    let totalInGrouped = 0;
    for (const year in window.groupedRows) {
        totalInGrouped += window.groupedRows[year].length;
    }
    
    let totalInAllGrouped = 0;
    for (const year in window.allGroupedRows) {
        totalInAllGrouped += window.allGroupedRows[year].length;
    }
    
    console.log('[DATA LOADED] Total bookings in groupedRows:', totalInGrouped);
    console.log('[DATA LOADED] Total bookings in allGroupedRows:', totalInAllGrouped);
    
    // Create const references (these are block-scoped to this script, but window.groupedRows is global)
    const groupedRows = window.groupedRows;
    const yearlyData = window.yearlyData;
    
    // Also make it available as a global variable (not const) for functions that expect it directly
    // Some functions in bookings_script.js might access groupedRows directly
    
    // Pagination state for nested rows (by year)
    // Initialize on window object - bookings_script.js will declare these as let variables
    if (!window.nestedPerPageValues) window.nestedPerPageValues = {};
    if (!window.nestedCurrentPages) window.nestedCurrentPages = {};
    if (!window.nestedSearchTerms) window.nestedSearchTerms = {};
    
    // Initialize pagination state for all years
    Object.keys(groupedRows).forEach(year => {
        window.nestedPerPageValues[year] = 5; // Default to 5 per page for initial compact view
        window.nestedCurrentPages[year] = 1;
        window.nestedSearchTerms[year] = '';
        
        // IMMEDIATELY set the pagination text on page load (before any functions are called)
        // This ensures users see correct counts right away
        const totalBookings = groupedRows[year] ? groupedRows[year].length : 0;
        const perPage = 5;
        const endCount = Math.min(perPage, totalBookings);
        
        // Set the values directly on the DOM elements
        const showingStart = document.getElementById(`showing-start-${year}`);
        const showingEnd = document.getElementById(`showing-end-${year}`);
        const showingTotal = document.getElementById(`showing-total-${year}`);
        
        if (showingStart && showingEnd && showingTotal) {
            showingStart.textContent = totalBookings > 0 ? '1' : '0';
            showingEnd.textContent = endCount;
            showingTotal.textContent = totalBookings;
            console.log(`[INITIAL] Set pagination for year ${year}: 1 to ${endCount} of ${totalBookings}`);
        } else {
            console.warn(`[INITIAL] Pagination elements not found for year ${year}`);
        }
    });
    window.expandedDetails = window.expandedDetails || [];
    
    // Initialize main pagination variables (required by bookings_script.js)
    window.mainCurrentPage = 1;
    window.mainPerPage = 10;
    window.mainTotalPages = 1;
    window.isFilterApplied = false;
    window.currentSearchTerm = '';
    window.expandedRows = [];
    
    // ============================================
    // MOBILE FUNCTIONS - Must be defined before toggleDetail
    // ============================================
    // Mobile detection function
    function isMobile() {
        return window.innerWidth <= 1023;
    }
    
    // Show mobile detail modal
    function showMobileDetailModal(detailId) {
        console.log('showMobileDetailModal called with detailId:', detailId);
        
        const detailRow = document.getElementById(detailId);
        console.log('detailRow found:', detailRow);
        
        if (!detailRow) {
            console.warn('showMobileDetailModal: Detail row not found:', detailId);
            return;
        }
        
        const expandedDetails = detailRow.querySelector('.expanded-details');
        console.log('expandedDetails found:', expandedDetails);
        
        if (expandedDetails) {
            const modalBody = document.getElementById('mobileModalBody');
            console.log('modalBody found:', modalBody);
            
            if (modalBody) {
                // Copy internal HTML
                modalBody.innerHTML = expandedDetails.innerHTML;
                console.log('[MODAL] Set modalBody innerHTML');
                
                // CRITICAL: Remove ALL attributes and classes from cloned content so it can be re-processed
                const clonedTexts = modalBody.querySelectorAll('.detail-text, .detail-value');
                console.log('[MODAL] Found', clonedTexts.length, 'detail elements to reset');
                
                clonedTexts.forEach((el, idx) => {
                    el.removeAttribute('data-initialized');
                    el.removeAttribute('data-original-text');
                    el.removeAttribute('title');
                    el.classList.remove('truncated', 'expanded');
                    
                    // Store original text as data attribute for inline handler
                    const originalText = el.textContent.trim();
                    el.setAttribute('data-original', originalText);
                    el.setAttribute('data-idx', idx);
                    
                    console.log('[MODAL] Reset element', idx, ':', originalText.substring(0, 30));
                });
                
                console.log('[MODAL] About to re-initialize truncation...');
                // Initialize truncation/expansion for newly added modal content immediately
                initContactTextTruncation();
                console.log('[MODAL] Truncation initialization called');
                
                const modal = document.getElementById('mobileDetailModal');
                console.log('modal element found:', modal);
                
                if (modal) {
                    console.log('Adding active class to modal');
                    modal.classList.add('active');
                    document.body.style.overflow = 'hidden';
                    console.log('Modal should now be visible!');
                } else {
                    console.error('mobileDetailModal element NOT FOUND in DOM!');
                }
            } else {
                console.error('mobileModalBody element NOT FOUND in DOM!');
            }
        } else {
            console.warn('No .expanded-details found in detail row');
        }
    }
    
    // Close mobile modal
    function closeMobileModal() {
        const modal = document.getElementById('mobileDetailModal');
        if (modal) {
            modal.classList.remove('active');
        }
        document.body.style.overflow = '';
    }
    // ============================================
    
    // NEW: Direct handler for Show More button that checks window size immediately
    function handleShowMore(detailId, evt) {
        if (evt) {
            evt.preventDefault();
            evt.stopPropagation();
        }
        
        const windowWidth = window.innerWidth;
        console.log('=== HANDLE SHOW MORE ===');
        console.log('Window width:', windowWidth);
        console.log('Detail ID:', detailId);
        
        // Direct check: if window is smaller than 1024px, show modal
        if (windowWidth <= 1023) {
            console.log('Small screen detected - showing modal');
            showMobileDetailModal(detailId);
        } else {
            console.log('Large screen detected - calling toggleDetail');
            toggleDetail(detailId, evt);
        }
    }
    
    // Toggle detail row with stopPropagation to work across breakpoints
    function toggleDetail(detailId, evt) {
        if (evt && typeof evt.stopPropagation === 'function') {
            evt.preventDefault();
            evt.stopPropagation();
        }
        
        // Check if mobile - show modal instead
        const mobile = isMobile();
        const width = window.innerWidth;
        
        console.log('=== TOGGLE DETAIL DEBUG ===');
        console.log('Window width:', width);
        console.log('isMobile() result:', mobile);
        console.log('detailId:', detailId);
        
        if (mobile) {
            console.log('Mobile detected - showing modal');
            showMobileDetailModal(detailId);
            return; // IMPORTANT: Return here to prevent inline expansion
        }
        
        console.log('Desktop detected - expanding inline');
        const detailRow = document.getElementById(detailId);
        if (!detailRow) {
            console.warn('toggleDetail: Detail row not found with ID:', detailId);
            return;
        }
        
        // Specifically target the .btn-toggle button (Show More/Less button)
        const button = detailRow.previousElementSibling?.querySelector('button.btn-toggle');
        if (!button) {
            console.warn('toggleDetail: Show More button not found for detail row:', detailId);
            return;
        }

        // Extract year and row ID from detailId
        const match = detailId.match(/detail-(.+)-(\d+)/);
        if (!match) return;
        
        const [_, year] = match;
        const computedDisplay = window.getComputedStyle(detailRow).display;
        const isOpen = detailRow.style.display === 'table-row' || computedDisplay === 'table-row' || computedDisplay === 'block';

        // Close any other open detail rows for this year
        document.querySelectorAll(`[id^="detail-${year}-"]`).forEach(row => {
            if (row.id !== detailId) {
                row.style.display = 'none';
                const otherBtn = row.previousElementSibling?.querySelector('button.btn-toggle');
                if (otherBtn) otherBtn.textContent = 'Show More';
                window.expandedDetails = (window.expandedDetails || []).filter(id => id !== row.id);
            }
        });

        if (isOpen) {
            detailRow.style.display = 'none';
            button.textContent = 'Show More';
            window.expandedDetails = (window.expandedDetails || []).filter(id => id !== detailId);
        } else {
            detailRow.style.display = 'table-row';
            detailRow.style.visibility = 'visible';
            detailRow.style.opacity = '1';
            detailRow.style.height = 'auto';
            button.textContent = 'Show Less';
            if (!window.expandedDetails.includes(detailId)) {
                window.expandedDetails.push(detailId);
            }
        }
    }
    
    // Initialize nested counters for all years
    function initializeNestedCounters() {
        console.log('Initializing nested counters...');
        
        // Wait for all helper functions to be defined
        // Check if critical functions exist before proceeding
        if (typeof extractRowData !== 'function' || typeof matchesFiltersYearWise !== 'function') {
            console.warn('[initializeNestedCounters] Helper functions not yet defined, delaying initialization...');
            setTimeout(initializeNestedCounters, 100);
            return;
        }
        
        console.log('[initializeNestedCounters] Helper functions found, proceeding with initialization');
        
        // Loop through all years in groupedRows
        for (const year in window.groupedRows) {
            // Use updateNestedPagination instead of directly setting values
            // This ensures filter state is respected even on initial load
            if (nestedCurrentPages[year] === undefined) {
                nestedCurrentPages[year] = 1;
            }
            if (nestedPerPageValues[year] === undefined) {
                nestedPerPageValues[year] = 5;
            }
            
            // Call updateNestedPagination to properly calculate filtered counts
            updateNestedPagination(year);
            console.log(`Initialized pagination for year ${year}`);
        }
    }
    
    // Call initialization when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeNestedCounters);
    } else {
        // DOM already loaded
        initializeNestedCounters();
    }
    
    // Toggle row (for expanding/collapsing year)
    function toggleRow(year) {
        const nestedRow = document.getElementById('nested-' + year);
        const expandIcon = document.getElementById('expand-' + year);
        
        if (!nestedRow || !expandIcon) return;
        
        if (nestedRow.style.display === 'none') {
            nestedRow.style.display = 'table-row';
            expandIcon.style.transform = 'rotate(90deg)';
            
            // Initialize pagination state if not exists
            if (!nestedCurrentPages[year]) {
                nestedCurrentPages[year] = 1;
            }
            if (!nestedPerPageValues[year]) {
                nestedPerPageValues[year] = 5;
            }
            
            // IMMEDIATELY apply pagination to show only first page of results
            updateNestedPagination(year);
        } else {
            nestedRow.style.display = 'none';
            expandIcon.style.transform = 'rotate(0deg)';
        }
    }
    
    // Handle nested search
    function handleNestedSearch(year, searchTerm) {
        nestedSearchTerms[year] = searchTerm;
        updateNestedPagination(year);
    }
    
    // Handle per page change
    function handlePerPageChange(year, perPage) {
        nestedPerPageValues[year] = parseInt(perPage);
        nestedCurrentPages[year] = 1; // Reset to first page when changing items per page
        updateNestedPagination(year);
    }
    
    // Update nested pagination - accepts optional filteredCount and filteredData for accuracy
    function updateNestedPagination(year, passedFilteredCount, passedFilteredData) {
        console.log(`[updateNestedPagination] Called for year ${year}, passedFilteredCount=${passedFilteredCount}, passedFilteredData=${passedFilteredData ? passedFilteredData.length : 'null'}`);
        
        if (!groupedRows[year]) {
            console.warn(`[updateNestedPagination] No groupedRows data for year ${year}`);
            return;
        }
        
        const nestedContainer = document.getElementById(`nested-data-${year}`);
        const rows = nestedContainer ? nestedContainer.querySelectorAll('.compact-row') : [];
        const searchTerm = (nestedSearchTerms[year] || '').toLowerCase();
        const perPage = nestedPerPageValues[year] || 5;
        const currentPage = nestedCurrentPages[year] || 1;
        const activeFilters = window.activeFilters || {};
        const hasActiveFilters = window.isFilterApplied && Object.keys(activeFilters).length > 0;
        
        console.log(`[updateNestedPagination] Year=${year}, DOM rows=${rows.length}, hasActiveFilters=${hasActiveFilters}`);
        
        // Use passed filtered data/count if available (most accurate), otherwise compute
        let filteredData;
        let usePassedCount = false;
        
        if (Array.isArray(passedFilteredData) && passedFilteredData.length >= 0) {
            // Use passed filtered data directly - most reliable
            filteredData = passedFilteredData;
            usePassedCount = true;
            console.log(`[updateNestedPagination] Using passed filtered data: ${filteredData.length} rows`);
        } else if (typeof passedFilteredCount === 'number' && passedFilteredCount >= 0) {
            // If only count was passed, try to get data
            usePassedCount = true;
            console.log(`[updateNestedPagination] Using passed filtered count: ${passedFilteredCount}`);
            
            const getFilteredFn = typeof getFilteredRowsForYear === 'function' ? getFilteredRowsForYear : 
                                  (typeof window.getFilteredRowsForYear === 'function' ? window.getFilteredRowsForYear : null);
            if (hasActiveFilters && getFilteredFn) {
                filteredData = getFilteredFn(year, activeFilters);
            } else {
                filteredData = groupedRows[year] || [];
            }
        } else {
            // No passed count, compute it
            const getFilteredFn = typeof getFilteredRowsForYear === 'function' ? getFilteredRowsForYear : 
                                  (typeof window.getFilteredRowsForYear === 'function' ? window.getFilteredRowsForYear : null);
            
            if (hasActiveFilters && getFilteredFn) {
                filteredData = getFilteredFn(year, activeFilters);
                console.log(`[updateNestedPagination] Got ${filteredData.length} filtered rows from getFilteredRowsForYear`);
            } else {
                filteredData = groupedRows[year] || [];
                console.log(`[updateNestedPagination] Using all ${filteredData.length} rows (no filter function or no filters)`);
            }
        }
        
        // Apply search term if present
        let totalMatches = 0;
        const matchingIndices = [];
        
        if (searchTerm) {
            // Filter by search term
            filteredData.forEach((rowData, idx) => {
                const searchableText = JSON.stringify(rowData).toLowerCase();
                if (searchableText.includes(searchTerm)) {
                    totalMatches++;
                    matchingIndices.push(idx);
                }
            });
        } else {
            // When passedFilteredData is provided, use its length directly (most accurate)
            // Otherwise fall back to passedFilteredCount if available, or computed filteredData.length
            if (Array.isArray(passedFilteredData)) {
                totalMatches = passedFilteredData.length;
            } else if (typeof passedFilteredCount === 'number' && passedFilteredCount >= 0) {
                totalMatches = passedFilteredCount;
            } else {
                totalMatches = filteredData.length;
            }
            filteredData.forEach((_, idx) => matchingIndices.push(idx));
        }
        
        console.log(`[updateNestedPagination] Year=${year}, usePassedCount=${usePassedCount}, passedCount=${passedFilteredCount}, filteredData.length=${filteredData.length}, totalMatches=${totalMatches}`);
        
        const startIndex = (currentPage - 1) * perPage;
        
        // Apply pagination to DOM rows
        if (rows.length > 0) {
            // Build a set of matching row IDs for quick lookup
            const matchingRowIds = new Set();
            filteredData.forEach(row => {
                if (row.id) matchingRowIds.add(String(row.id));
            });
            
            let visibleRowIndex = 0;
            rows.forEach((row) => {
                // Get row ID from data attribute or first cell
                const rowId = row.dataset.rowId || row.querySelector('[data-row-id]')?.dataset.rowId || 
                              row.querySelector('td:first-child')?.textContent?.trim();
                
                // Check if this row matches filters (by ID or by index)
                let rowMatches = false;
                if (hasActiveFilters) {
                    rowMatches = matchingRowIds.has(String(rowId));
                    // Fallback: check by original groupedRows
                    if (!rowMatches && window.groupedRows && window.groupedRows[year]) {
                        const originalIndex = Array.from(rows).indexOf(row);
                        const originalRowData = window.groupedRows[year][originalIndex];
                        if (originalRowData) {
                            rowMatches = filteredData.some(fd => fd.id === originalRowData.id);
                        }
                    }
                } else {
                    rowMatches = true; // No filters, all rows match
                }
                
                // Apply search filter
                if (rowMatches && searchTerm) {
                    const text = row.textContent.toLowerCase();
                    rowMatches = text.includes(searchTerm);
                }
                
                if (rowMatches) {
                    // Check if this row should be visible on current page
                    if (visibleRowIndex >= startIndex && visibleRowIndex < startIndex + perPage) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                    visibleRowIndex++;
                } else {
                    row.style.display = 'none';
                }
            });
        }
        
        console.log(`[updateNestedPagination] Final totalMatches=${totalMatches}`);
        
        // Update pagination controls
        updatePaginationControls(year, totalMatches, currentPage, perPage);
        
        // Update showing info
        updateShowingInfo(year, totalMatches, currentPage, perPage);
    }
    
    // Update pagination controls
    function updatePaginationControls(year, totalMatches, currentPage, perPage) {
        const totalPages = Math.ceil(totalMatches / perPage);
        const pageNumbers = document.getElementById(`page-numbers-${year}`);
        const prevBtn = document.getElementById(`prev-btn-${year}`);
        const nextBtn = document.getElementById(`next-btn-${year}`);
        
        if (!pageNumbers || !prevBtn || !nextBtn) return;
        
        // Update previous button
        prevBtn.disabled = currentPage === 1;
        
        // Update next button
        nextBtn.disabled = currentPage === totalPages || totalPages === 0;
        
        // Update page numbers
        pageNumbers.innerHTML = '';
        
        if (totalPages === 0) {
            pageNumbers.innerHTML = '<span class="no-pages">No pages</span>';
            return;
        }
        
        // Show page numbers (simplified - show up to 5 pages)
        const maxVisiblePages = 5;
        let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages / 2));
        let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);
        
        if (endPage - startPage < maxVisiblePages - 1) {
            startPage = Math.max(1, endPage - maxVisiblePages + 1);
        }
        
        for (let i = startPage; i <= endPage; i++) {
            const pageBtn = document.createElement('button');
            pageBtn.className = `btn btn-sm ${i === currentPage ? 'btn-primary active-page' : 'btn-outline'}`;
            pageBtn.textContent = i;
            pageBtn.onclick = () => goToNestedPage(year, i);
            pageNumbers.appendChild(pageBtn);
        }
    }
    
    // Update showing info
    function updateShowingInfo(year, totalMatches, currentPage, perPage) {
        const startIndex = (currentPage - 1) * perPage + 1;
        const endIndex = Math.min(currentPage * perPage, totalMatches);
        
        console.log(`[updateShowingInfo] Year=${year}, totalMatches=${totalMatches}, currentPage=${currentPage}, perPage=${perPage}, startIndex=${startIndex}, endIndex=${endIndex}`);
        
        const showingStart = document.getElementById(`showing-start-${year}`);
        const showingEnd = document.getElementById(`showing-end-${year}`);
        const showingTotal = document.getElementById(`showing-total-${year}`);
        
        console.log(`[updateShowingInfo] Elements found: start=${!!showingStart}, end=${!!showingEnd}, total=${!!showingTotal}`);
        
        if (showingStart) {
            showingStart.textContent = totalMatches > 0 ? startIndex : 0;
            console.log(`[updateShowingInfo] Set showing-start-${year} to ${showingStart.textContent}`);
        }
        if (showingEnd) {
            showingEnd.textContent = endIndex;
            console.log(`[updateShowingInfo] Set showing-end-${year} to ${showingEnd.textContent}`);
        }
        if (showingTotal) {
            showingTotal.textContent = totalMatches;
            console.log(`[updateShowingInfo] Set showing-total-${year} to ${showingTotal.textContent}`);
        }
    }
    
    // Handle nested pagination - uses same counting as updateNestedPagination
    function handleNestedPagination(year, direction) {
        const currentPage = nestedCurrentPages[year] || 1;
        const perPage = nestedPerPageValues[year] || 5;
        const searchTerm = (nestedSearchTerms[year] || '').toLowerCase();
        const activeFilters = window.activeFilters || {};
        const hasActiveFilters = window.isFilterApplied && Object.keys(activeFilters).length > 0;
        
        // Get filtered data using same method as updateNestedPagination
        const getFilteredFn = typeof getFilteredRowsForYear === 'function' ? getFilteredRowsForYear : 
                              (typeof window.getFilteredRowsForYear === 'function' ? window.getFilteredRowsForYear : null);
        
        let filteredData;
        if (hasActiveFilters && getFilteredFn) {
            filteredData = getFilteredFn(year, activeFilters);
        } else {
            filteredData = groupedRows[year] || [];
        }
        
        // Apply search term
        let totalMatches = filteredData.length;
        if (searchTerm) {
            totalMatches = filteredData.filter(rowData => {
                return JSON.stringify(rowData).toLowerCase().includes(searchTerm);
            }).length;
        }
        
        const totalPages = Math.ceil(totalMatches / perPage);
        
        if (direction === 'prev' && currentPage > 1) {
            nestedCurrentPages[year] = currentPage - 1;
        } else if (direction === 'next' && currentPage < totalPages) {
            nestedCurrentPages[year] = currentPage + 1;
        }
        
        updateNestedPagination(year);
    }
    
    // Go to nested page
    function goToNestedPage(year, page) {
        nestedCurrentPages[year] = page;
        updateNestedPagination(year);
    }
    
    // Initialize pagination when row is expanded
    function initializeNestedPagination(year) {
        if (!nestedPerPageValues[year]) {
            nestedPerPageValues[year] = 5;
        }
        if (!nestedCurrentPages[year]) {
            nestedCurrentPages[year] = 1;
        }
        updateNestedPagination(year);
    }

    // Preserve year-wise nested handlers before any other JS (e.g. assets/js/bookings_script.js)
    // can overwrite globals with month-wise implementations.
    window.__superadminBookingsYearFns = window.__superadminBookingsYearFns || {};
    window.__superadminBookingsYearFns.handleNestedSearch = handleNestedSearch;
    window.__superadminBookingsYearFns.handlePerPageChange = handlePerPageChange;
    window.__superadminBookingsYearFns.updateNestedPagination = updateNestedPagination;
    window.__superadminBookingsYearFns.handleNestedPagination = handleNestedPagination;
    window.__superadminBookingsYearFns.goToNestedPage = goToNestedPage;
</script>

<style>
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
    width: 1em !important;
    height: 1em !important;
    padding: 0.5em !important;
    margin: 0 !important;
  }
  .btn-close:hover {
    opacity: 0.75 !important;
  }
  .modal-header {
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    padding: 1rem !important;
    border-bottom: 1px solid #dee2e6 !important;
    position: relative !important;
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
  }
  body.modal-open {
    overflow: hidden !important;
  }
  body.modal-open .sidebar,
  body.modal-open .navbar {
    pointer-events: none !important;
  }
  /* Ensure editUserModal has highest z-index */
  #editUserModal {
    z-index: 99999 !important;
    position: fixed !important;
  }
  #editUserModal .modal-dialog {
    z-index: 100000 !important;
    position: relative !important;
  }
  #editUserModal .modal-content {
    z-index: 100001 !important;
    position: relative !important;
  }
</style>

<?php if ($isEmbed): ?>
<style>
  /* Embedded mode - transparent backgrounds for containers, keep content backgrounds */
  /* These styles must be at the end to override all other CSS */
  html,
  body {
    background: transparent;
    scrollbar-width:none;
  }
  
  /* Main content and embedded classes must be transparent - highest specificity */
  main.main-content.embedded-main,
  main#mainContent.main-content.embedded-main,
  main.main-content.embedded-main#mainContent,
  .main-content.embedded-main,
  main.embedded-main,
  main.main-content,
  #mainContent.main-content,
  #mainContent.embedded-main,
  main#mainContent,
  .content.embedded-main,
  .embedded-main {
    scrollbar-width: none;
    background: transparent !important;
    background-color: transparent !important;
    padding: 0 !important;
    overflow-y: auto !important;
  }
  
  .content.embedded-main > * {
    background: transparent !important;
  }
  
  /* Keep original backgrounds for cards and tables */
  .table-card {
    background: #fff !important;
  }
  .table-container {
    background: transparent !important;
  }
  .stats-grid {
    background: transparent !important;
  }
  .stat-card {
    /* Keep original stat card backgrounds - they have their own gradients */
  }
  .table-row,
  .table-head {
    background: #fff !important;
  }
  .nested-section {
    background: transparent !important;
  }
  
  /* Make sure all wrapper divs are transparent */
  .contentinside {
    background: transparent !important;
  }
  
  /* Make sure direct children of content are transparent unless they're cards */
  .content.embedded-main > div:not(.table-card):not(.stat-card) {
    background: transparent !important;
  }
  
  /* Override any media query rules */
  @media (max-width: 1024px) {
    main.main-content.embedded-main,
    .main-content.embedded-main,
    main.embedded-main {
      background: transparent !important;
      background-color: transparent !important;
    }
  }
  
  @media (max-width: 900px) {
    main.main-content.embedded-main,
    .main-content.embedded-main,
    main.embedded-main {
      background: transparent !important;
      background-color: transparent !important;
    }
  }
  
  /* Search Section Styles - Full Width */
  .search-section {
    background: transparent !important;
    padding: 0 !important;
    margin-top: 1rem ;
    margin-bottom: 0.1rem !important;
    box-shadow: none !important;
    border: 0 !important;
    width: 100% !important;
  }
  
  .search-container {
    display: flex !important;
    width: 100% !important;
    align-items: center !important;
    justify-content: space-between !important;
  }
  
  .search-filters {
    backdrop-filter: blur(10px);
    border: 0 !important;
    align-items: center !important;
    flex: 1 1 auto !important;
    width: 100% !important;
    max-width: 100% !important;
    box-sizing: border-box !important;
    display: flex !important;
    gap: 1rem !important;
        flex-wrap: nowrap !important;
  }
  
  .search-input-wrapper {
    position: relative !important;
        flex: 1 1 auto !important;
        width: auto !important;
        max-width: none !important;
        min-width: 0 !important;
  }
  
  /* Filter Button Styles */
  .filter-button {
    display: inline-flex !important;
    align-items: center !important;
    gap: 0.5rem !important;
    padding: 1rem 1.25rem !important;
    background: white !important;
    border: 1px solid #e2e8f0 !important;
    border-radius: 1rem !important;
    font-size: 0.95rem !important;
    font-weight: 500 !important;
    color: #475569 !important;
    cursor: pointer !important;
    transition: all 0.2s ease !important;
    white-space: nowrap !important;
  }
  
  .filter-button:hover {
    background: #f8fafc !important;
    border-color: #cbd5e1 !important;
    color: #1e293b !important;
  }
  
  .filter-button svg {
    flex-shrink: 0 !important;
  }
  
  body.dark-mode .filter-button {
    background: rgba(255, 255, 255, 0.1) !important;
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
  }

  body.dark-mode .filter-button svg {
    color: #ffffff !important;
  }
  /* Add Booking Button Styles */
  .add-booking-button {
    display: inline-flex !important;
    align-items: center !important;
    gap: 0.25rem !important;
    padding: 1rem 1.5rem !important;
    background: #3b82f6 !important;
    border: none !important;
    border-radius: 1rem !important;
    font-size: 0.95rem !important;
    font-weight: 600 !important;
    color: white !important;
    cursor: pointer !important;
    transition: all 0.2s ease !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1) !important;
    white-space: nowrap !important;
  }
  
  .add-booking-button:hover {
    background: #2563eb !important;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1) !important;
    transform: translateY(-1px) !important;
  }
  
  .add-booking-button svg {
    flex-shrink: 0 !important;
  }

    .quick-action-button {
        min-width: 48px !important;
        width: 48px !important;
        height: 48px !important;
        padding: 0.75rem !important;
        justify-content: center !important;
        flex: 0 0 auto !important;
    }
  
  /* Responsive adjustments for buttons */
  @media (min-width: 769px) {
    .search-filters {
      flex-wrap: nowrap !important;
    }
  }
  
  @media (max-width: 768px) {
    .search-filters {
      gap: 0.5rem !important;
            align-items: center !important;
            flex-wrap: nowrap !important;
    }
    
    .filter-button,
    .add-booking-button,
    .quick-action-button {
            flex: 0 0 auto !important;
            width: 46px !important;
            height: 46px !important;
      padding: 0.75rem !important;
      font-size: 0.85rem !important;
            justify-content: center !important;
    }
    
    .filter-button span,
    .add-booking-button span {
      display: none !important;
    }
    
    .filter-button svg,
        .add-booking-button svg,
        .quick-action-button svg {
      margin-right: 0 !important;
    }

        .search-input-wrapper {
            flex: 1 1 auto !important;
            min-width: 0 !important;
        }
  }
  
  .search-icon {
    position: absolute !important;
    left: 1rem !important;
    top: 50% !important;
    transform: translateY(-50%) !important;
    color: #94a3b8 !important;
    width: 1.25rem !important;
    height: 1.25rem !important;
    pointer-events: none !important;
  }
  
  .search-input {
    width: 100% !important;
    padding: 1rem 1rem 1rem 3rem !important;
    border: 1px solid #e2e8f0 !important;
    border-radius: 1rem !important;
    font-size: 1rem !important;
    transition: 0.2s !important;
    background: #fff !important;
    box-sizing: border-box !important;
  }
  
  .search-input:focus {
    outline: 0 !important;
    border-color: #3b82f6 !important;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1) !important;
  }
  
  .search-input::placeholder {
    color: #94a3b8 !important;
  }
  
  @media (max-width: 768px) {
    .search-input {
      font-size: 0.75rem !important;
      padding: 0.7rem 0.5rem 0.7rem 1.5rem !important;
    }
    
    .search-icon {
      width: 1rem !important;
      height: 1rem !important;
      left: 0.4rem !important;
    }
  }

    /* Embedded mode: stabilize main table layout to avoid header wrapping */
    .table-card .table-container {
        overflow-x: auto !important;
    }

    .main-bookings-table {
        width: 100% !important;
        min-width: 300px !important;
        table-layout: auto !important;
    }

    .main-bookings-table .table-head th,
    .main-bookings-table tbody td {
        white-space: nowrap !important;
    }

    .main-bookings-table .table-head th:first-child,
    .main-bookings-table tbody td:first-child {
        
    }

    /* Icon Colors for Financial Columns */
    /* Actual Revenue - Red */
    .actual-revenue-cell .fa-wallet,
    .actual-revenue-cell i.fas {
        color: #ef4444 !important; /* Red for Actual Revenue */
    }

    /* Remaining - Yellow/Orange */
    .remaining-col .fa-coins,
    .remaining-amount-cell .fa-coins,
    .remaining-col i.fas,
    .remaining-amount-cell i.fas {
        color: #f59e0b !important; /* Yellow/Orange for Remaining */
    }

    /* Build Incentive - Yellow/Orange */
    .build-incentive-cell .fa-coins,
    td.build-incentive-cell:nth-of-type(4) i.fas {
        color: #f59e0b !important; /* Yellow/Orange for Build Incentive */
    }

    /* Total Paid - Green (5th column with fa-gift icon) */
    .build-incentive-cell .fa-gift,
    td.build-incentive-cell:nth-of-type(5) i.fas,
    td:nth-of-type(5) .fa-gift {
        color: #10b981 !important; /* Green for Total Paid */
    }

    /* Dark mode icon colors - maintain readability */
    body.dark-mode .actual-revenue-cell .fa-wallet,
    body.dark-mode .actual-revenue-cell i.fas {
        color: #f87171 !important; /* Lighter red for dark mode */
    }

    body.dark-mode .remaining-col .fa-coins,
    body.dark-mode .remaining-amount-cell .fa-coins,
    body.dark-mode .remaining-col i.fas,
    body.dark-mode .remaining-amount-cell i.fas,
    body.dark-mode .build-incentive-cell .fa-coins,
    body.dark-mode td.build-incentive-cell:nth-of-type(4) i.fas {
        color: #fbbf24 !important; /* Lighter yellow for dark mode */
    }

    body.dark-mode .build-incentive-cell .fa-gift,
    body.dark-mode td.build-incentive-cell:nth-of-type(5) i.fas,
    body.dark-mode td:nth-of-type(5) .fa-gift {
        color: #34d399 !important; /* Lighter green for dark mode */
    }

    /* ============================================
       MOBILE MODAL STYLES
       ============================================ */
    .mobile-detail-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        backdrop-filter: blur(1px);
        background: rgb(0 0 0 / 0%);
        z-index: 99999;
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
    }

    .mobile-detail-modal.active {
        display: flex;
        align-items: flex-start;
        justify-content: center;
        padding: 0.1rem;
    }

    .mobile-modal-content {
        background: white;
        border-radius: 1rem;
        width: 100%;
        max-width: 500px;
        margin: auto;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        animation: slideUp 0.3s ease-out;
    }

    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .mobile-modal-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1rem 1.25rem;
        border-bottom: 1px solid #e5e7eb;
        position: sticky;
        top: 0;
        background: white;
        border-radius: 1rem 1rem 0 0;
        z-index: 10;
    }

    .mobile-modal-title {
        margin: 0;
        font-size: 1.125rem;
        font-weight: 600;
        color: #1e293b;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .mobile-modal-title svg {
        color: #3b82f6;
    }

    .mobile-close-btn {
        background: #f1f5f9;
        border: none;
        border-radius: 0.5rem;
        width: 2rem;
        height: 2rem;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: background 0.2s;
    }

    .mobile-close-btn:hover {
        background: #e2e8f0;
    }

    .mobile-close-btn svg {
        color: #64748b;
    }

    .mobile-modal-body {
        padding: 1.25rem;
        max-height: calc(100vh - 8rem);
        overflow-y: auto;
    }

    [data-theme="dark"] .mobile-modal-body{
        background: rgba(30, 30, 30, 0.95) !important;
    }

    [data-theme="dark"] .select2-results__option{
        background: rgba(30, 30, 30, 0.95) !important;
    }

    [data-theme="dark"] .mobile-year-stats .stat-chip{
        border: 1px solid #ffffff3d !important;
    }
    /* Mobile responsive styles for nested controls */
    @media (max-width: 768px) {
        .nested-controls {
            gap: 0.75rem !important;
            padding: 0.3rem !important;
        }

        .nested-search-wrapper {
            width: 100% !important;
            max-width: 100% !important;
        }

        .nested-search {
            width: 100% !important;
            font-size: 0.875rem !important;
            padding: 0.5rem 1.75rem !important;
        }

        .per-page-selector {
            width: max-content !important;
            justify-content: flex-end !important;
        }

        .per-page-selector label {
            font-size: 0.75rem !important;
        }

        .per-page-select {
            font-size: 0.875rem !important;
            padding: 0.375rem 0.5rem !important;
            min-width: 70px !important;
        }

        .pagination-wrapper {
            width: 100% !important;
            flex-direction: column !important;
            gap: 0.5rem !important;
        }

        .showing-info {
            order: -1;
            text-align: center !important;
            font-size: 0.75rem !important;
            padding: 0.25rem 0 !important;
        }

        .pagination-controls {
            width: 100% !important;
            justify-content: center !important;
        }

        .pagination-controls button {
            padding: 0.375rem 0.75rem !important;
            font-size: 0.75rem !important;
        }

        .page-numbers {
            gap: 0.25rem !important;
            flex-wrap: wrap !important;
            justify-content: center !important;
        }

        .page-numbers button {
            min-width: 2rem !important;
            height: 2rem !important;
            padding: 0.25rem !important;
            font-size: 0.75rem !important;
        }
    }

    @media (max-width: 480px) {
        .mobile-modal-content {
            width: calc(100% - 1rem);
            margin: 0.5rem;
        }

        .mobile-modal-header {
            padding: 0.75rem 1rem;
        }

        .mobile-modal-title {
            font-size: 1rem;
        }

        .mobile-modal-body {
            margin-top: 10px;
            padding: 0.4rem;
        }

        .nested-controls {
            padding: 0.3rem !important;
        }

        .per-page-selector label,
        .showing-info {
            font-size: 0.7rem !important;
        }

        .pagination-controls button {
            padding: 0.25rem 0.5rem !important;
            font-size: 0.7rem !important;
        }
    }
    @media (max-width: 768px) {
        .main-content, .pagination, body {
            flex-direction: column !important;
        }      
        .pagination-info {
            display: flex !important;
            justify-content: center;
            order: 2;
        }
    }

    [data-theme="dark"] .mobile-year-stats .stat-chip,
    [data-theme="dark"] .financial-year-header{
        background: transparent !important;
    }

    [data-theme="dark"] .mobile-year-stats .stat-chip span,
    [data-theme="dark"] .mobile-year-stats .stat-chip strong{
        color: white !important;
    }

    [data-theme="dark"] .filter-summary-title,
    [data-theme="dark"] .filter-stat-label,
    [data-theme="dark"] .filter-stat-value{
        color: white !important;
    }
    .city-Filter-Select{
        font-weight: 600; 
        font-size: 14px; 
        color: #475569;
    }
    [data-theme="dark"] .city-Filter-Select{
        color: #fff;
    }   

</style>
<script>
  // Force apply transparent backgrounds via JavaScript as fallback
  (function() {
    function applyTransparentBackgrounds() {
      const mainElements = document.querySelectorAll('main.main-content.embedded-main, main.embedded-main, .main-content.embedded-main, #mainContent.embedded-main, main#mainContent');
      mainElements.forEach(function(el) {
        el.style.setProperty('background', 'transparent', 'important');
        el.style.setProperty('background-color', 'transparent', 'important');
      });
      
      const contentElements = document.querySelectorAll('.content.embedded-main, .embedded-main');
      contentElements.forEach(function(el) {
        el.style.setProperty('background', 'transparent', 'important');
        el.style.setProperty('background-color', 'transparent', 'important');
      });
    }
    
    // Apply immediately
    applyTransparentBackgrounds();
    
    // Apply after DOM is ready
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', applyTransparentBackgrounds);
    }
    
    // Apply after a short delay to override any late-loading styles
    setTimeout(applyTransparentBackgrounds, 100);
    setTimeout(applyTransparentBackgrounds, 500);
  })();
</script>
<?php endif; ?>

<!-- Edit User Modal Start -->
    <div id="editUserModal" style="display: none; position: fixed !important; top: 0 !important; left: 0 !important; width: 100% !important; height: 100% !important; z-index: 99999 !important; backdrop-filter: blur(6px) saturate(1.2) !important;background: rgb(9 9 9 / 41%); align-items: center !important; justify-content: center !important; flex-direction: column !important; overflow: auto !important;">
        <div class="modal-dialog modal-dialog-centered" style="position: relative !important; margin: auto !important; max-width: 600px !important; width: 90% !important; max-height: 90vh !important; overflow: hidden !important; z-index: 100000 !important;">
            <div class="modal-content" style="max-height: 90vh !important; display: flex !important; flex-direction: column !important;border-radius:18px; overflow: hidden !important; z-index: 100001 !important; position: relative !important;">
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
                                        <label for="unique_source_table" class="form-label">Assigned User <b id="selected_user_label"></b></label>
                                        <input type="text" id="unique_searchInput" class="form-control" placeholder="Search...">
                                        <ul id="unique_source_table" class="dropdown-options">
                                            <?php
                                                // Fetch users from the database using existing $db connection
                                                // $db is already created at the top of the file and extends Config
                                                // Use reflection to access the protected $conn property
                                                try {
                                                    $reflection = new ReflectionClass($db);
                                                    $connProperty = $reflection->getProperty('conn');
                                                    $connProperty->setAccessible(true);
                                                    $conn = $connProperty->getValue($db);
                                                    
                                                    if ($conn) {
                                                        $userQuery = "SELECT tablename FROM accounts ORDER BY tablename";
                                                        $userStmt = $conn->prepare($userQuery);
                                                        $userStmt->execute();
                                                        $users = $userStmt->fetchAll(PDO::FETCH_ASSOC);
                                                        
                                                        if (!empty($users)) {
                                                            foreach ($users as $user) {
                                                                $tablename = htmlspecialchars($user['tablename'] ?? '');
                                                                if (!empty($tablename)) {
                                                                    echo "<li class='unique-option' data-value='{$tablename}'>{$tablename}</li>";
                                                                }
                                                            }
                                                        }
                                                    }
                                                } catch (Exception $e) {
                                                    // Silently fail - users dropdown will be empty
                                                    error_log("Error fetching users for dropdown: " . $e->getMessage());
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

                                <!-- raised invoice -->
                                <div class="col-lg-12">
                                    <div class="btnwraps btncheckboxs">
                                        <input type="checkbox" class="form-check-input" name="update_invoice_checkbox"
                                            id="update_invoice_checkbox">
                                        <label class="form-check-label" for="update-invoice-checkbox">Raised
                                            Invoice</label>
                                        <!-- Checkbox for updating user table -->

                                        <input type="checkbox" class="form-check-input" name="update_user_checkbox"
                                            id="update_user_checkbox">
                                        <label class="form-check-label" for="update-user-checkbox">Update
                                            User</label>
                                        <!-- xtra -->

                                        <input type="checkbox" class="form-check-input" name="cashbackverify"
                                            id="cashbackverify">
                                        <label class="form-check-label" for="update-user-checkbox">Cashback
                                            Paid</label>
                                    </div>
                                </div>

                                <div class="col-lg-12">
                                    <div class="btnwraps">
                                        <input type="radio" class="btn-check Processing" name="cstatus"
                                            id="btn-check-3-outlined" value="Processing" required>
                                        <label class="btn btn-outline-primary bttm-btn" for="btn-check-3-outlined"
                                            style="margin-left: 0px;">Processing</label>

                                        <input type="radio" class="btn-check Received" name="cstatus"
                                            id="2success-outlined" value="Received" required>
                                        <label class="btn btn-outline-success bttm-btn"
                                            for="2success-outlined">Received</label>

                                        <input type="radio" class="btn-check Canceled" name="cstatus"
                                            id="2danger-outlined" value="Canceled" required>
                                        <label class="btn btn-outline-danger bttm-btn"
                                            for="2danger-outlined">Cancelled</label>
                                        <div class="invalid-feedback">Please Select the staus of booking!</div>
                                    </div>
                                </div>

                                <div class="col-lg-12">
                                    <div class="Ubsubmitbtn">
                                        <input type="submit" value="Update Booking" class="btn btn-success btn-block"
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

<script>
   document.addEventListener('DOMContentLoaded', function() {
        initAssignedUserDropdown(document);
    });

    // Extracted so it can be re-invoked on a cloned modal in the parent window
    function initAssignedUserDropdown(root) {
        const searchInput = root.getElementById ? root.getElementById('unique_searchInput') : root.querySelector('#unique_searchInput');
        const dropdown = root.getElementById ? root.getElementById('unique_source_table') : root.querySelector('#unique_source_table');
        if (!searchInput || !dropdown) return;

        const hiddenInput = root.getElementById ? root.getElementById('source_table') : root.querySelector('#source_table');
        const labelElement = root.getElementById ? root.getElementById('selected_user_label') : root.querySelector('#selected_user_label');

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
                opt.style.display = opt.innerText.toLowerCase().includes(filter) ? '' : 'none';
            });
            if (dropdown.style.display === 'block') showDropdown();
        });

        dropdown.addEventListener('click', function(event) {
            if (event.target && event.target.tagName === 'LI') {
                searchInput.value = event.target.innerText;
                if (hiddenInput) hiddenInput.value = event.target.dataset.value;
                if (labelElement) labelElement.innerHTML = event.target.innerText;
                dropdown.style.display = 'none';
            }
        });

        (root.ownerDocument || root).addEventListener('click', (event) => {
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
    }
    // Expose globally so the iframe→parent clone flow can re-attach listeners
    window.initAssignedUserDropdown = initAssignedUserDropdown;
</script>
<script>
    // Use event delegation on labels for status buttons (radio buttons are hidden)
    document.addEventListener('DOMContentLoaded', function() {
        // When "Received" label is clicked, set received amount to actual revenue
        document.addEventListener('click', function(e) {
            if (e.target.matches('label[for="2success-outlined"]') || e.target.closest('label[for="2success-outlined"]')) {
                setTimeout(function() {
                    var actualRevenue = document.getElementById('ccrevenue');
                    var receivedAmt = document.getElementById('brecived');
                    if (actualRevenue && receivedAmt) {
                        receivedAmt.removeAttribute('readonly');
                        receivedAmt.value = actualRevenue.value;
                        receivedAmt.setAttribute('readonly', true);
                        console.log('Received clicked - set brecived to:', actualRevenue.value);
                    }
                }, 10);
            }
        });
        
        // When "Canceled" label is clicked, set commission, total revenue, actual revenue, and received amount to 0
        document.addEventListener('click', function(e) {
            if (e.target.matches('label[for="2danger-outlined"]') || e.target.closest('label[for="2danger-outlined"]')) {
                setTimeout(function() {
                    var ccashback = document.getElementById('ccashback');
                    var crevenue = document.getElementById('crevenue');
                    var ccrevenue = document.getElementById('ccrevenue');
                    var brecived = document.getElementById('brecived');
                    
                    if (ccashback) ccashback.value = 0;
                    if (crevenue) crevenue.value = 0;
                    if (ccrevenue) ccrevenue.value = 0;
                    if (brecived) {
                        brecived.removeAttribute('readonly');
                        brecived.value = 0;
                        brecived.setAttribute('readonly', true);
                    }
                    console.log('Canceled clicked - set all values to 0');
                }, 10);
            }
        });
        
        // When "Raised Invoice" checkbox is clicked
        document.addEventListener('change', function(e) {
            if (e.target.matches('#update_invoice_checkbox')) {
                if (e.target.checked) {
                    var actualRevenue = document.getElementById('ccrevenue');
                    var invoiceRaised = document.getElementById('invoice_raised');
                    if (actualRevenue && invoiceRaised) {
                        invoiceRaised.value = actualRevenue.value;
                        console.log('Invoice checkbox clicked - set invoice_raised to:', actualRevenue.value);
                    }
                }
            }
        });
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
        const radios = document.querySelectorAll('.btn-check.Received, .btn-check.Canceled');
        if (processingChecked) {
            radios.forEach(radio => radio.removeAttribute('required'));
        } else {
            checkboxes.forEach(checkbox => checkbox.removeAttribute('required'));
        }

        return true;
    }
</script>
<script>
// Edit button functionality - use both event delegation and direct handlers

// Method 1: Event delegation on document
document.addEventListener('click', function(e) {
    const editLink = e.target.closest('a.editLink');
    
    // Also check if the clicked element itself is an editLink
    const isEditLink = e.target.classList.contains('editLink') || e.target.closest('a.editLink');
    
    if (editLink || isEditLink) {
        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();
        
        const linkElement = editLink || e.target.closest('a.editLink') || e.target;
        const id = linkElement.getAttribute('id') || linkElement.id;
        
        if (id) {
            editUser(id);
        }
        return false;
    }
}, true);

// Method 2: Direct event handlers on all edit links (for iframe context)
document.addEventListener('DOMContentLoaded', function() {
    // Find all edit links and attach direct handlers
    const editLinks = document.querySelectorAll('a.editLink');
    
    editLinks.forEach((link) => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();
            
            const id = this.getAttribute('id') || this.id;
            
            if (id) {
                editUser(id);
            }
            return false;
        }, true);
    });
});

// Close modal when clicking outside
document.addEventListener('DOMContentLoaded', function() {
    const editModal = document.getElementById('editUserModal');
    if (editModal) {
        editModal.addEventListener('click', (e) => {
            if (e.target === editModal) editModal.style.display = 'none';
        });
    }
});

// Update Calculate function
function updateCalculate() {   
    if(isNaN(document.getElementById("cagreement").value) || document.getElementById("cagreement").value=="") {   
        var text1 = 0;   
    } else {   
        var text1 = parseFloat(document.getElementById("cagreement").value);   
    }   
    
    if(isNaN(document.getElementById("ccashback").value) || document.getElementById("ccashback").value=="") {   
        var text2 = 0;   
    } else {   
        var text2 = parseFloat(document.getElementById("ccashback").value); 
    } 
    //print Value Input
    document.getElementById("crevenue").value = ((text1)*(text2/100));
    
    if(isNaN(document.getElementById("cccashback").value) || document.getElementById("cccashback").value=="") {   
        var text3 = 0;   
    } else {   
        var text3 = parseFloat(document.getElementById("cccashback").value); 
    }
    
    if(isNaN(document.getElementById("crevenue").value) || document.getElementById("crevenue").value=="") {   
        var text4 = 0;   
    } else {   
        var text4 = parseInt(document.getElementById("crevenue").value); 
    }
    document.getElementById("ccrevenue").value = ((text4)-((text1)*(text3/100)));
}

// Update form submission handler
const updateForm = document.getElementById("edit-user-form");
if (updateForm) {
    updateForm.addEventListener("submit", async (e) => {
        e.preventDefault();

        const formData = new FormData(updateForm);
        formData.append("update", 1);

        if (updateForm.checkValidity() === false) {
            e.preventDefault();
            e.stopPropagation();
            updateForm.classList.add("was-validated");
            return false;
        } else {
            const updateBtn = document.getElementById("edit-user-btn");
            const originalText = updateBtn.value;
            updateBtn.value = "Please Wait...";
            updateBtn.disabled = true;

            try {
                const data = await fetch("/incentiveapp_integration/userlogin1/superadmin/action.php", {
                    method: "POST",
                    body: formData,
                });
                const response = await data.text();

                const showAlert = document.getElementById("showAlert");
                if (showAlert) {
                    showAlert.innerHTML = response;
                }

                updateBtn.value = originalText;
                updateBtn.disabled = false;
                
                const modal = document.getElementById('editUserModal');
                if (modal) {
                    modal.style.display = 'none';
                }
                
                updateForm.reset();
                updateForm.classList.remove("was-validated");
                
                // Save current filters to sessionStorage before reload
                if (window.activeFilters && Object.keys(window.activeFilters).length > 0) {
                    sessionStorage.setItem('bookingFilters', JSON.stringify(window.activeFilters));
                    sessionStorage.setItem('isFilterApplied', 'true');
                }
                
                // Reload page to show updated data
                window.location.reload();
            } catch (error) {
                alert('Error updating booking. Please try again.');
                updateBtn.value = originalText;
                updateBtn.disabled = false;
            }
        }
    });
}
</script>


<script>
// Ensure search works exactly like userlogin6
// The issue: simpleSearch is defined inside a DOMContentLoaded handler in bookings_script.js
// but handleMainSearch (global) tries to call it. We need to ensure it works.

// Create a wrapper search function that will work with our data structure
function createSearchHandler() {
    return function(e) {
        const term = (e.target.value || '').toLowerCase().trim();
        window.currentSearchTerm = term;
        
        if (!window.groupedRows) {
            return;
        }
        
        const rows = Array.from(document.querySelectorAll('#mainTableBody .table-row[onclick]'));
        let anyMatch = false;
        
        rows.forEach(row => {
            const onclick = row.getAttribute('onclick') || '';
            const yearMatch = onclick.match(/toggleRow\('([^']+)'\)/) || onclick.match(/'([^']+)'/);
            const year = yearMatch ? yearMatch[1] : null;
            
            if (!year) {
                return;
            }
            
            // Search in the main row text
            const mainRowText = row.textContent.toLowerCase();
            
            // Search in nested data
            let nestedMatches = false;
            if (window.groupedRows[year]) {
                const list = window.groupedRows[year];
                nestedMatches = list.some(r => {
                    const searchableFields = [
                        r.booking_id,
                        r.builder_name,
                        r.project_name,
                        r.customer_name,
                        r.contact_number,
                        r.email,
                        r.unit_type,
                        r.unit_number,
                        r.status,
                        r.salesperson
                    ];
                    const hay = searchableFields
                        .map(v => (v == null ? '' : String(v)))
                        .join(' ')
                        .toLowerCase();
                    return term === '' || hay.includes(term);
                });
            }
            
            // Show/hide row based on match
            const isMatch = term === '' || mainRowText.includes(term) || nestedMatches;
            
            if (isMatch) {
                row.style.display = '';
                row.dataset.searchMatch = 'true';
                anyMatch = true;
                
                // If nested data matches, update nested search and show nested section
                if (nestedMatches && year) {
                    window.nestedSearchTerms = window.nestedSearchTerms || {};
                    window.nestedSearchTerms[year] = term;
                    
                    // Trigger nested search update
                    if (typeof handleNestedSearch === 'function') {
                        handleNestedSearch(year, term);
                    }
                }
            } else {
                row.style.display = 'none';
                row.dataset.searchMatch = 'false';
                
                // Hide nested section
                const nestedSection = document.getElementById(`nested-${year}`);
                if (nestedSection) {
                    nestedSection.style.display = 'none';
                }
            }
        });
        
        // Show no results message if needed
        if (!anyMatch && term !== '') {
            document.querySelectorAll('.no-results-row').forEach(el => el.remove());
            
            const noResultsRow = document.createElement('tr');
            noResultsRow.className = 'no-results-row';
            noResultsRow.innerHTML = '<td colspan="10" style="text-align: center; padding: 2rem; color: #64748b;">No bookings found matching "' + term + '"</td>';
            document.getElementById('mainTableBody').appendChild(noResultsRow);
        } else {
            document.querySelectorAll('.no-results-row').forEach(el => el.remove());
        }
    };
}

// Initialize search when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    function attachSearch() {
        const searchInput = document.getElementById('mainSearch');
        if (!searchInput) {
            return;
        }
        
        // Check if bookings_script.js has already attached a listener
        // If not, attach our own
        const hasExistingListener = searchInput.getAttribute('data-search-attached') === 'true';
        
        if (!hasExistingListener) {
            const searchHandler = createSearchHandler();
            searchInput.addEventListener('input', searchHandler);
            searchInput.setAttribute('data-search-attached', 'true');
        }
    }
    
    // Try immediately
    attachSearch();
    
    // Also try after a delay to ensure everything is loaded
    setTimeout(attachSearch, 500);
    setTimeout(attachSearch, 1000);
});

// ============================================
// FILTERING FUNCTIONS (Year-wise version)
// Based on userlogin6/assets/js/bookings_script.js but adapted for financial year grouping
// Overrides applyFilters from bookings_script.js after it loads
// ============================================

console.log('[IFRAME] Filtering functions script loaded');

// Listen for filter messages from parent window
window.addEventListener('message', function(event) {
    
    // Note: In production we avoid blocking UI with alert() calls.
    // Security: In production, verify event.origin
    if (event.data && event.data.type === 'darkMode') {
        // Handled by dedicated dark mode listener above; avoid noisy misclassification logs.
        return;
    } else if (event.data && event.data.type === 'applyFilters') {
        console.log('[IFRAME] Filter message detected!');
        console.log('[IFRAME] Received filters from parent:', event.data.filters);
        
        // Apply the filters received from parent
        if (typeof window.applyFilters === 'function') {
            console.log('[IFRAME] Calling applyFilters function...');
            window.applyFilters(event.data.filters);
        } else {
            console.warn('[IFRAME] applyFilters function not yet defined, queuing...');
            // Queue it to run after applyFilters is defined
            setTimeout(() => {
                if (typeof window.applyFilters === 'function') {
                    console.log('[IFRAME] Calling applyFilters function (delayed)...');
                    window.applyFilters(event.data.filters);
                } else {
                    console.error('[IFRAME] applyFilters function still not defined!');
                }
            }, 100);
        }
    } else if (event.data && event.data.type === 'clearFilters') {
        console.log('[IFRAME] Clear filters message received');
        if (typeof window.clearFilters === 'function') {
            window.clearFilters();
        }
    } else if (event.data && event.data.type === 'downloadCsv') {
        console.log('[IFRAME] Download CSV message received');
        if (typeof window.downloadFilteredBookings === 'function') {
            window.downloadFilteredBookings();
        } else {
            console.warn('[IFRAME] downloadFilteredBookings function not yet defined');
        }
    }
});

// Wait for bookings_script.js to load, then override applyFilters
(function() {
    'use strict';
    
    // Helper function - same as userlogin6
    function getVal(ids, toLower = true) {
        const idArray = Array.isArray(ids) ? ids : [ids];
        let element = null;
        for (const id of idArray) {
            element = document.getElementById(id);
            if (element) break;
        }
        if (!element) return '';
        
        // Check if element is a Select2 multi-select or native multi-select
        if (element.multiple || (window.$ && window.$(element).hasClass('select2-hidden-accessible'))) {
            // For Select2 multi-select, get selected values as array
            if (window.$ && window.$(element).hasClass('select2-hidden-accessible')) {
                const selectedValues = window.$(element).val();
                console.log(`[getVal] Select2 multi-select for ${idArray[0]}: `, selectedValues);
                if (Array.isArray(selectedValues) && selectedValues.length > 0) {
                    const result = toLower ? selectedValues.map(v => String(v).toLowerCase().trim()) : selectedValues.map(v => String(v).trim());
                    return result;
                }
                return [];
            }
            // For native multi-select
            const selectedOptions = Array.from(element.selectedOptions || []);
            if (selectedOptions.length > 0) {
                const values = selectedOptions.map(opt => opt.value);
                const result = toLower ? values.map(v => String(v).toLowerCase().trim()) : values.map(v => String(v).trim());
                return result;
            }
            return [];
        }
        
        // For single value inputs
        const v = (element.value ?? '').toString().trim();
        return toLower ? v.toLowerCase() : v;
    }
    
    function restoreYearWiseGlobals() {
        const fns = window.__superadminBookingsYearFns;
        if (!fns) return;

        // Nested handlers
        if (typeof fns.handleNestedSearch === 'function') window.handleNestedSearch = fns.handleNestedSearch;
        if (typeof fns.handlePerPageChange === 'function') window.handlePerPageChange = fns.handlePerPageChange;
        if (typeof fns.updateNestedPagination === 'function') window.updateNestedPagination = fns.updateNestedPagination;
        if (typeof fns.handleNestedPagination === 'function') window.handleNestedPagination = fns.handleNestedPagination;
        if (typeof fns.goToNestedPage === 'function') window.goToNestedPage = fns.goToNestedPage;

        // Filter logic
        if (typeof fns.filterNestedData === 'function') window.filterNestedData = fns.filterNestedData;
        if (typeof fns.filterYearlyData === 'function') window.filterYearlyData = fns.filterYearlyData;
        if (typeof fns.restoreOriginalView === 'function') window.restoreOriginalView = fns.restoreOriginalView;
    }

    function applyYearWiseNestedFilteringToAllYears() {
        const fns = window.__superadminBookingsYearFns || {};
        const doUpdate = fns.updateNestedPagination || window.updateNestedPagination;
        if (typeof doUpdate !== 'function') return;

        document.querySelectorAll('[id^="nested-data-"]').forEach(tbody => {
            const year = tbody.id.replace('nested-data-', '');
            try {
                doUpdate(year);
            } catch (e) {
                // Error filtering for year
            }

            // If a detail row is open for a now-hidden compact row, force-hide it
            tbody.querySelectorAll(`tr[id^="detail-${year}-"]`).forEach(detailRow => {
                const prev = detailRow.previousElementSibling;
                if (prev && prev.classList && prev.classList.contains('compact-row')) {
                    if (prev.style.display === 'none') {
                        detailRow.style.display = 'none';
                    }
                }
            });
        });
    }

    // Override applyFilters after bookings_script.js loads
    function overrideApplyFilters() {

        // Re-assert year-wise functions in case external scripts overwrote them.
        restoreYearWiseGlobals();

        window.applyFilters = function(filtersFromParent) {

            // Ensure we are using the year-wise nested filtering functions
            restoreYearWiseGlobals();

            // Collect filters - ALWAYS prefer DOM values if the elements exist (ensures multi-select works)
            let activeFilters = {};
            
            // First, try to get values from DOM (this properly handles Select2 multi-select)
            const domFilters = {
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
            
            // Check if we got any values from DOM (at least one filter element exists)
            const hasDomFilters = Object.values(domFilters).some(v => {
                if (Array.isArray(v)) return v.length > 0;
                return v !== '' && v != null;
            });
            
            if (hasDomFilters) {
                activeFilters = domFilters;
            } else if (filtersFromParent && typeof filtersFromParent === 'object') {
                activeFilters = { ...filtersFromParent };
            } else {
                activeFilters = {};
            }


            // Remove empty filters and normalize strings/arrays
            Object.keys(activeFilters).forEach(key => {
                let v = activeFilters[key];
                
                // Handle arrays (from multi-select)
                if (Array.isArray(v)) {
                    // Remove empty values from array
                    v = v.filter(item => item !== '' && item != null);
                    if (v.length === 0) {
                        delete activeFilters[key];
                        return;
                    }
                    // Lowercase each array element
                    activeFilters[key] = v.map(item => String(item).toLowerCase());
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



            // Store in window for other functions
            window.activeFilters = activeFilters;
            window.isFilterApplied = Object.keys(activeFilters).length > 0;
            if (typeof isFilterApplied !== 'undefined') isFilterApplied = window.isFilterApplied;

            // Compute filtered rows upfront to drive banner visibility immediately
            const filteredRowsForBanner = getFilteredRows(activeFilters);
            updateOverallStatCards(filteredRowsForBanner, window.isFilterApplied);

            // Apply filters using year-wise logic (do NOT call the month-wise pagination from bookings_script.js)
            if (Object.keys(activeFilters).length === 0) {
                if (typeof window.restoreOriginalView === 'function') {
                    window.restoreOriginalView();
                } else {
                    restoreOriginalView();
                }
            } else {
                if (typeof window.filterYearlyData === 'function') {
                    window.filterYearlyData();
                } else {
                    filterYearlyData();
                }
            }

            // Update clear filter button visibility
            if (typeof window.updateClearFilterButtonVisibility === 'function') {
                setTimeout(() => window.updateClearFilterButtonVisibility(), 100);
            }

            // Close modal if called from within iframe
            if (typeof closeFilterModal === 'function' && window.parent === window) {
                closeFilterModal();
            }

            // Recompute visible year stats after filters
            recomputeAllYearStatsAfterFilter();

            // Force nested table visibility to match filters (prevents stale rows being shown)
            applyYearWiseNestedFilteringToAllYears();
        };
        
        // Also override clearFilters - COMPLETELY REWRITTEN (OPTIMIZED FOR SPEED)
        window.clearFilters = function() {
            
            // Ensure we are using the year-wise nested filtering functions
            restoreYearWiseGlobals();

            // STEP 1: Clear all filter state IMMEDIATELY
            window.activeFilters = {};
            window.isFilterApplied = false;
            if (typeof isFilterApplied !== 'undefined') isFilterApplied = false;
            
            // STEP 2: Clear all filter input fields (both in iframe and parent if accessible)
            const filterIds = [
                'filterID', 'filterBookingDateStart', 'filterBookingDateEnd', 'filterMonth',
                'filterBuilder', 'filterProject', 'filterType', 'filterUnit',
                'filterSize', 'filterCustumername', 'filterCustomername', 'filterCustomerName',
                'filterContactnumber', 'filterContactNumber', 'filterEmail', 'filterStatus',
                'filterAgreement', 'filterCommission', 'filterTrevenue', 'filterRevenue',
                'filterCashBack', 'filterCashback', 'filterActualRevenue', 'filterReceived'
            ];
            
            filterIds.forEach(id => {
                const element = document.getElementById(id);
                if (element) element.value = '';
            });
            
            // Also try to clear in parent window if in iframe
            try {
                if (window.parent && window.parent !== window) {
                    filterIds.forEach(id => {
                        const parentElement = window.parent.document.getElementById(id);
                        if (parentElement) parentElement.value = '';
                    });
                }
            } catch (e) { /* Could not access parent window */ }
            
            // STEP 3: Show ALL year rows and restore their stats
            const yearlyRows = document.querySelectorAll('#mainTableBody .table-row[onclick]');
            const yearsToUpdate = [];
            
            yearlyRows.forEach(row => {
                row.style.display = '';
                const onclick = row.getAttribute('onclick') || '';
                const m = onclick.match(/toggleRow\('([^']+)'\)/);
                const year = m ? m[1] : null;
                
                if (year && window.yearlyData) {
                    const yearData = window.yearlyData.find(yd => yd.year === year);
                    if (yearData) {
                        updateYearRowStatsFromData(year, row, yearData);
                        yearsToUpdate.push(year);
                    }
                }
            });
            
            // STEP 4: Show ALL nested rows for ALL years (remove all filters) - BATCHED
            const nestedSections = document.querySelectorAll('[id^="nested-"]');
            
            nestedSections.forEach(nested => {
                const year = nested.id.replace('nested-', '');
                
                // Show ALL nested rows without any filtering
                nested.querySelectorAll('.compact-row').forEach(row => {
                    row.style.display = '';
                });
                
                // Reset nested pagination state
                if (window.nestedCurrentPages) window.nestedCurrentPages[year] = 1;
                if (window.nestedPerPageValues) window.nestedPerPageValues[year] = 5;
                if (window.nestedSearchTerms) window.nestedSearchTerms[year] = '';
            });
            
            // STEP 5: Update all nested pagination in one batch (faster)
            const fns = window.__superadminBookingsYearFns || {};
            const doUpdate = fns.updateNestedPagination || window.updateNestedPagination;
            if (typeof doUpdate === 'function') {
                yearsToUpdate.forEach(year => {
                    doUpdate(year);
                });
            }
            
            // Remove no results messages
            document.querySelectorAll('.no-results-row').forEach(el => el.remove());
            
            // Update clear filter button visibility (no delay needed)
            if (typeof window.updateClearFilterButtonVisibility === 'function') {
                window.updateClearFilterButtonVisibility();
            }

            // Restore top stat cards to initial overall totals
            updateOverallStatCards();
            
            // Update main pagination text to show all entries
            const allRows = getAllRows();
            updateMainPaginationText(allRows.length);
            

        };

        // Override applyExternalFilters to keep stat cards and counts in sync for superadmin filters
        window.applyExternalFilters = function(incomingFilters = {}) {
            
            // Normalize and trim values
            const lowerKeys = ['id', 'builder', 'project', 'customerName', 'contactNumber', 'email', 'type', 'unit', 'size', 'agreement', 'commission', 'revenue', 'cashback', 'actualRevenue', 'status', 'received'];
            const normalized = {};
            Object.entries(incomingFilters || {}).forEach(([key, value]) => {
                if (value === undefined || value === null) return;
                
                // Handle arrays (from multi-select inputs like builder, project, etc.)
                if (Array.isArray(value)) {
                    const processedArray = value
                        .map(item => String(item).trim())
                        .filter(item => item !== '')
                        .map(item => lowerKeys.includes(key) ? item.toLowerCase() : item);
                    
                    if (processedArray.length > 0) {
                        normalized[key] = processedArray;
                    }
                    return;
                }
                
                // Handle single string values
                if (typeof value === 'string') {
                    const trimmed = value.trim();
                    if (!trimmed) return;
                    normalized[key] = lowerKeys.includes(key) ? trimmed.toLowerCase() : trimmed;
                } else {
                    // Handle other types (numbers, booleans, etc.)
                    normalized[key] = value;
                }
            });
            

            
            // Ensure we are using the year-wise nested filtering functions
            restoreYearWiseGlobals();

            // Apply and broadcast state
            window.activeFilters = normalized;
            window.isFilterApplied = Object.keys(normalized).length > 0;
            if (typeof isFilterApplied !== 'undefined') isFilterApplied = window.isFilterApplied;
            
            
            // Update top cards immediately
            const filteredRowsForBanner = getFilteredRows(normalized);
            
            updateOverallStatCards(filteredRowsForBanner, window.isFilterApplied);
            
            if (!window.isFilterApplied) {
                if (typeof window.restoreOriginalView === 'function') {
                    window.restoreOriginalView();
                } else {
                    restoreOriginalView();
                }
            } else {
                if (typeof window.filterYearlyData === 'function') {
                    window.filterYearlyData();
                } else {
                    filterYearlyData();
                }
            }
            
            // Clear-filter button visibility (in iframe context only)
            if (typeof window.updateClearFilterButtonVisibility === 'function') {
                setTimeout(() => window.updateClearFilterButtonVisibility(), 100);
            }

            // Recompute visible year stats after filters
            recomputeAllYearStatsAfterFilter();

            // Force nested table visibility to match filters
            applyYearWiseNestedFilteringToAllYears();
            
            // Update main pagination text with filtered count
            updateMainPaginationText(filteredRowsForBanner.length, filteredRowsForBanner.length);
        };
    }
    
    // Call override immediately
    overrideApplyFilters();
    
    // AGGRESSIVEretry to ensure it happens after bookings_script.js loads
    let retryCount = 0;
    const retryInterval = setInterval(() => {
        retryCount++;
        overrideApplyFilters();
        if (retryCount >= 20) {
            clearInterval(retryInterval);
        }
    }, 100); // Every 100ms for 2 seconds
    
    window.addEventListener('load', () => {
        setTimeout(overrideApplyFilters, 100);
        setTimeout(overrideApplyFilters, 500);
        setTimeout(overrideApplyFilters, 1000);
        
        // Initialize main pagination text on page load
        setTimeout(() => {
            const allRows = getAllRows();
            updateMainPaginationText(allRows.length);
        }, 500);
        
        // Restore filters from sessionStorage if they exist (after edit form submission)
        setTimeout(() => {
            const savedFilters = sessionStorage.getItem('bookingFilters');
            const wasFilterApplied = sessionStorage.getItem('isFilterApplied');
            
            if (savedFilters && wasFilterApplied === 'true') {
                try {
                    const filters = JSON.parse(savedFilters);
                    
                    // Clear sessionStorage after reading
                    sessionStorage.removeItem('bookingFilters');
                    sessionStorage.removeItem('isFilterApplied');
                    
                    // Apply the restored filters
                    if (Object.keys(filters).length > 0) {
                        if (typeof window.applyExternalFilters === 'function') {
                            window.applyExternalFilters(filters);
                        } else if (typeof window.applyFilters === 'function') {
                            window.activeFilters = filters;
                            window.isFilterApplied = true;
                            window.applyFilters(filters);
                        }
                    }
                } catch (e) {
                    sessionStorage.removeItem('bookingFilters');
                    sessionStorage.removeItem('isFilterApplied');
                }
            }
        }, 800);
    });
}());

// Helper functions for year-wise filtering (based on userlogin6 but adapted for years)
// These will use the matchesFilters from bookings_script.js if available, or define our own

// Recompute stats for all visible year rows after filters
function recomputeAllYearStatsAfterFilter() {
    const rows = document.querySelectorAll('#mainTableBody .table-row[onclick]');
    rows.forEach(row => {
        // Don't skip any rows - we want to update all year stats
        const onclick = row.getAttribute('onclick') || '';
        const m = onclick.match(/toggleRow\('([^']+)'\)/);
        const year = m ? m[1] : null;
        if (!year) return;
        try {
            updateYearRowStats(year, row);
        } catch (err) {
            // Error updating year stats
        }
    });
}

// Flatten grouped rows into a single array (used for overall stat cards)
function getAllRows() {
    const rows = [];
    if (window.groupedRows) {
        Object.keys(window.groupedRows).forEach(year => {
            if (Array.isArray(window.groupedRows[year])) {
                rows.push(...window.groupedRows[year]);
            }
        });
    }
    return rows;
}

// Compact number formatter for client-side updates
function formatCompactNumberJS(number) {
    const n = Number(number) || 0;
    if (n >= 1000000000) return '₹' + (n / 1000000000).toFixed(1) + 'B';
    if (n >= 1000000) return '₹' + (n / 1000000).toFixed(1) + 'M';
    if (n >= 1000) return '₹' + (n / 1000).toFixed(1) + 'K';
    return '₹' + n.toLocaleString();
}

// Month filter matcher (expects target in YYYY-MM format). Accepts booking_date or booking_month fields.
function matchesMonthFilter(row, target) {
    if (!target || !row) return true;
    const match = String(target).match(/^(\d{4})-(\d{2})$/);
    if (!match) return true;
    const targetYear = parseInt(match[1], 10);
    const targetMonth = parseInt(match[2], 10);

    const raw = row.booking_date || row.booking_month || row.bmonth || '';
    if (!raw) return false;

    // If already YYYY-MM
    const dash = /^\d{4}-\d{2}/.exec(raw);
    if (dash) {
        const [y, m] = dash[0].split('-').map(Number);
        return y === targetYear && m === targetMonth;
    }

    const d = new Date(raw);
    if (Number.isNaN(d.getTime())) return false;
    return d.getFullYear() === targetYear && (d.getMonth() + 1) === targetMonth;
}

// Compute overall stats from a set of rows
function computeOverallFromRows(rows) {
    const stats = { booking: 0, totalRevenue: 0, actualRevenue: 0, recivedRevenue: 0, agreement: 0, invoiceRaised: 0, finalRemaining: 0 };
    (rows || []).forEach(r => {
        stats.booking += 1;
        stats.totalRevenue += parseFloat(r.revenue || 0);
        stats.actualRevenue += parseFloat(r.crevenue || 0);
        stats.recivedRevenue += parseFloat(r.recived_amt || 0);
        stats.agreement += parseFloat(r.agreement_value || 0);
        stats.invoiceRaised += parseFloat(r.invoice_raise || 0);
    });
    stats.finalRemaining = Math.max(0, stats.totalRevenue - stats.invoiceRaised);
    return stats;
}

// Flatten and filter rows according to active filters (including month)
function getFilteredRows(activeFilters = {}) {
    const rows = [];
    if (!window.groupedRows) return rows;

    Object.keys(window.groupedRows).forEach(year => {
        rows.push(...getFilteredRowsForYear(year, activeFilters));
    });
    return rows;
}

function getFilteredRowsForYear(year, activeFilters = {}) {
    const rows = [];
    const filters = activeFilters || window.activeFilters || {};
    if (!window.groupedRows || !window.groupedRows[year]) return rows;

    const filtersWithoutMonth = { ...filters };
    delete filtersWithoutMonth.month;



    (window.groupedRows[year] || []).forEach(r => {
        const matchesMonth = !filters.month || matchesMonthFilter(r, filters.month);
        const matchesOthers = Object.keys(filtersWithoutMonth).length === 0 || matchesFiltersYearWise(r, filtersWithoutMonth);
        
        if (matchesMonth && matchesOthers) rows.push(r);
    });
    
    return rows;
}

// Update top stat cards (overall, total/actual revenue, received) after filtering
function updateOverallStatCards(filteredRows, forceFiltered = false) {
    // console.log('[UPDATE STATS] Called with:', { 
    //     filteredRowsCount: Array.isArray(filteredRows) ? filteredRows.length : 'not array',
    //     forceFiltered,
    //     isFilterApplied: window.isFilterApplied,
    //     activeFiltersCount: window.activeFilters ? Object.keys(window.activeFilters).length : 0
    // });
    
    const isFiltered = forceFiltered || (window.isFilterApplied && window.activeFilters && Object.keys(window.activeFilters).length > 0);
    const rowsToUse = Array.isArray(filteredRows) ? filteredRows : getAllRows();

    // console.log('[UPDATE STATS] Using rows:', rowsToUse.length, 'isFiltered:', isFiltered);

    let stats;
    if (rowsToUse.length > 0) {
        stats = computeOverallFromRows(rowsToUse);
    } else if (!isFiltered && window.initialOverallStats) {
        stats = window.initialOverallStats;
    } else {
        stats = { booking: 0, totalRevenue: 0, actualRevenue: 0, recivedRevenue: 0 };
    }

    // console.log('[UPDATE STATS] Computed stats:', stats);

    const setText = (id, text) => {
        const el = document.getElementById(id);
        if (el) {
            // console.log(`[UPDATE STATS] Setting ${id} to: ${text}`);
            el.textContent = text;
            // Force update with style change to ensure re-render
            el.style.opacity = '0.99';
            setTimeout(() => { el.style.opacity = '1'; }, 10);
        } else {
            // Element not found
        }
    };

    const setStatDisplay = (valueId, cardId, text, fullText) => {
        const valueEl = document.getElementById(valueId);
        const cardEl = document.getElementById(cardId);
        if (valueEl) valueEl.textContent = text;
        if (cardEl && fullText) cardEl.setAttribute('data-tooltip', fullText);
    };

    const formatFullCurrency = val => '₹' + new Intl.NumberFormat('en-IN').format(val || 0);

    setStatDisplay('statOverallBookings', 'cardOverallBookings', Number(stats.booking || 0).toLocaleString(), Number(stats.booking || 0).toLocaleString());
    setStatDisplay('statOverallAgreement', 'cardOverallAgreement', formatCompactNumberJS(stats.agreement || 0), formatFullCurrency(stats.agreement));
    setStatDisplay('statTotalRevenue', 'cardTotalRevenue', formatCompactNumberJS(stats.totalRevenue || 0), formatFullCurrency(stats.totalRevenue));
    setStatDisplay('statActualRevenue', 'cardActualRevenue', formatCompactNumberJS(stats.actualRevenue || 0), formatFullCurrency(stats.actualRevenue));
    setStatDisplay('statReceivedAmount', 'cardReceivedAmount', formatCompactNumberJS(stats.recivedRevenue || 0), formatFullCurrency(stats.recivedRevenue));
    setStatDisplay('statFinalRemaining', 'cardFinalRemaining', formatCompactNumberJS(stats.finalRemaining || 0), formatFullCurrency(stats.finalRemaining));

    // console.log('[UPDATE STATS] Stats cards updated successfully');

    // Update filter summary banner
    updateFilterSummaryBanner(stats, isFiltered);
}

// Update filter summary banner with current stats
function updateFilterSummaryBanner(stats, isFiltered) {
    const banner = document.getElementById('filterSummaryBanner');
    const title = document.getElementById('filterSummaryTitle');
    const bookings = document.getElementById('filterStatBookings');
    const totalRevenue = document.getElementById('filterStatTotalRevenue');
    const actualRevenue = document.getElementById('filterStatActualRevenue');



    if (!banner) {
        return;
    }

    // Treat search or any active filters as a filtered state, even if the caller passed false
    const hasFilters =
        isFiltered ||
        (window.isFilterApplied && window.activeFilters && Object.keys(window.activeFilters).length > 0) ||
        (window.currentSearchTerm && window.currentSearchTerm.trim().length > 0);

    if (!hasFilters) {
        banner.style.setProperty('display', 'none', 'important');
        return;
    }

    banner.style.setProperty('display', 'block', 'important');

    // Determine the title based on active filters
    const activeFilters = window.activeFilters || {};
    let titleText = 'Financial Year - ';
    
    // Check if a specific year is selected from the year dropdown
    const selectedYear = window.selectedYear;
    if (selectedYear && selectedYear !== 'all') {
        titleText += selectedYear;
    } else if (activeFilters.month) {
        // Extract year-month from filter
        const monthMatch = activeFilters.month.match(/^(\d{4})-(\d{2})$/);
        if (monthMatch) {
            const year = parseInt(monthMatch[1]);
            const month = parseInt(monthMatch[2]);
            const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            titleText += `${monthNames[month - 1]} ${year}`;
        } else {
            titleText += 'Filtered Results';
        }
    } else {
        // Determine which years are visible AND have filtered data
        const visibleYears = [];
        document.querySelectorAll('#mainTableBody .table-row[onclick]').forEach(row => {
            if (row.style.display !== 'none') {
                const onclick = row.getAttribute('onclick') || '';
                const match = onclick.match(/toggleRow\('([^']+)'\)/);
                if (match) {
                    const year = match[1];
                    // Check if this year has any filtered rows
                    const filteredRows = getFilteredRowsForYear(year, activeFilters);
                    if (filteredRows.length > 0) {
                        visibleYears.push(year);
                    }
                }
            }
        });

        if (visibleYears.length === 1) {
            titleText += visibleYears[0];
        } else if (visibleYears.length > 1) {
            // Extract start and end years from the range format (e.g., "2023-2024", "2024-2025")
            const yearParts = visibleYears.map(y => {
                const match = y.match(/^(\d{4})-(\d{4})$/);
                return match ? [parseInt(match[1]), parseInt(match[2])] : null;
            }).filter(Boolean);
            
            if (yearParts.length > 0) {
                // Find the minimum start year and maximum end year from visible years only
                const minYear = Math.min(...yearParts.map(p => p[0]));
                const maxYear = Math.max(...yearParts.map(p => p[1]));
                titleText += `${minYear}-${maxYear}`;
            } else {
                titleText += 'Multiple Years';
            }
        } else {
            titleText += 'Filtered Results';
        }
    }

    if (title) title.textContent = titleText;
    if (bookings) bookings.textContent = Number(stats.booking || 0).toLocaleString();
    if (totalRevenue) totalRevenue.textContent = formatCompactNumberJS(stats.totalRevenue || 0);
    if (actualRevenue) actualRevenue.textContent = formatCompactNumberJS(stats.actualRevenue || 0);
}

// Update main pagination text at the bottom of the table
function updateMainPaginationText(totalEntries, forceFilteredCount) {
    const paginationTextElements = document.querySelectorAll('.pagination-text, .pagination-text-top');
    const activeFilters = window.activeFilters || {};
    const hasFilters = window.isFilterApplied && Object.keys(activeFilters).length > 0;
    
    // Count visible year rows to determine "showing" count
    const visibleYearRows = document.querySelectorAll('#mainTableBody .table-row[onclick]');
    let visibleCount = 0;
    let filteredBookingsCount = 0;
    
    visibleYearRows.forEach(row => {
        if (row.style.display !== 'none') {
            visibleCount++;
            // If filters are active, count filtered rows for this year
            if (hasFilters) {
                const onclick = row.getAttribute('onclick') || '';
                const match = onclick.match(/toggleRow\('([^']+)'\)/);
                if (match) {
                    const year = match[1];
                    const filteredRows = getFilteredRowsForYear(year, activeFilters);
                    filteredBookingsCount += filteredRows.length;
                }
            }
        }
    });
    
    // Use filtered count if filters are active, otherwise use totalEntries
    const displayCount = hasFilters ? filteredBookingsCount : (forceFilteredCount !== undefined ? forceFilteredCount : totalEntries);
    
    // Update the text to show year count and total bookings count
    const text = `Showing ${visibleCount} Year${visibleCount !== 1 ? 's' : ''} With ${displayCount} Total Booking${displayCount !== 1 ? 's' : ''}`;
    
    paginationTextElements.forEach(el => {
        if (el) {
            el.textContent = text;
        }
    });
    
}

// Make function globally available
window.updateFilterSummaryBanner = updateFilterSummaryBanner;
window.updateMainPaginationText = updateMainPaginationText;

// Safely fetch the first non-empty field value by trying multiple key names
function getField(row, keys) {
    if (!row || !keys) return '';
    const keyList = Array.isArray(keys) ? keys : [keys];
    for (const key of keyList) {
        if (row[key] !== undefined && row[key] !== null) {
            return String(row[key]);
        }
    }
    return '';
}

// Filter yearly data (similar to filterMonthlyData but for years)
function filterYearlyData() {
    const activeFilters = window.activeFilters || {};
    const yearlyRows = document.querySelectorAll('#mainTableBody .table-row[onclick]');
    const filteredGlobalRows = [];
    let hasVisibleYears = false;
    const hasFilters = Object.keys(activeFilters).length > 0;
    const hasSearch = (window.currentSearchTerm || '').trim().length > 0;
    const forceFiltered = hasFilters || hasSearch;
    
    document.querySelectorAll('.no-results-row').forEach(el => el.remove());
    
    yearlyRows.forEach(row => {
        const onclick = row.getAttribute('onclick');
        const m = onclick ? onclick.match(/toggleRow\('([^']+)'\)/) : null;
        if (!m) return;
        const year = m[1];
        
        // Check if year has matching data
        const hasMatchingData = checkYearHasMatchingData(year);
        
        if (hasMatchingData) {
            row.style.display = '';
            hasVisibleYears = true;
            const nestedSection = document.getElementById(`nested-${year}`);
            if (nestedSection) {
                const wasExpanded = nestedSection.style.display === 'table-row' || 
                                   window.getComputedStyle(nestedSection).display === 'table-row';
                nestedSection.style.display = wasExpanded ? 'table-row' : 'none';
                
                filterNestedData(year);
                updateYearRowStats(year, row);
                
                // Update pagination counts to reflect filtered data (for both expanded and collapsed)
                // This ensures counts are correct when user expands the section
                updateNestedPagination(year);
            }

            // Collect filtered rows for overall stat recalculation
            if (window.groupedRows && window.groupedRows[year]) {
                const filteredRowsForYear = window.groupedRows[year].filter(booking => {
                    const matchesMonth = !activeFilters.month || matchesMonthFilter(booking, activeFilters.month);
                    const filtersWithoutMonth = { ...activeFilters };
                    delete filtersWithoutMonth.month;
                    const matchesOthers = Object.keys(filtersWithoutMonth).length === 0 || matchesFiltersYearWise(booking);
                    return matchesMonth && matchesOthers;
                });
                filteredGlobalRows.push(...filteredRowsForYear);
            }
        } else {
            row.style.display = 'none';
            const nestedSection = document.getElementById(`nested-${year}`);
            if (nestedSection) nestedSection.style.display = 'none';
        }
    });
    
    if (!hasVisibleYears) {
        showNoResultsMessage();
        updateOverallStatCards([], forceFiltered);
        updateMainPaginationText(0);
    } else {
        updateOverallStatCards(filteredGlobalRows, forceFiltered);
        updateMainPaginationText(filteredGlobalRows.length);
    }
}

// Check if a year has matching data
function checkYearHasMatchingData(year) {
    const activeFilters = window.activeFilters || {};
    
    // Prefer server-provided structured data
    if (window.groupedRows && window.groupedRows[year]) {
        return window.groupedRows[year].some(row => {
            const matchesMonth = !activeFilters.month || matchesMonthFilter(row, activeFilters.month);
            return matchesMonth && matchesFiltersYearWise(row);
        });
    }
    
    // Fallback: scan DOM
    const nestedContainer = document.getElementById('nested-data-' + year);
    if (!nestedContainer) return false;
    const rows = nestedContainer.querySelectorAll('.compact-row');
    for (let i = 0; i < rows.length; i++) {
        const domRow = rows[i];
        const rowData = extractRowData(domRow, year);
        if (rowData) {
            if (typeof window.matchesFilters === 'function') {
                if (window.matchesFilters(rowData)) return true;
            } else {
                if (matchesFiltersYearWise(rowData)) return true;
            }
        }
    }
    return false;
}

// Extract row data from DOM element with dataset fallbacks to keep filters and Show More stable on all widths
function extractRowData(rowElement, year) {
    if (!rowElement) return null;
    const cells = rowElement.querySelectorAll('td');
    const ds = rowElement.dataset || {};

    const getText = (selector, index) => {
        if (selector) {
            const node = rowElement.querySelector(selector);
            if (node && node.textContent) return node.textContent.trim();
        }
        if (typeof index === 'number' && cells[index] && cells[index].textContent) {
            return cells[index].textContent.trim();
        }
        return '';
    };

    return {
        id: ds.id || getText(null, 0),
        booking_date: ds.bookingDate || ds.bookingMonth || '',
        builder: ds.builder || '',
        project: ds.project || '',
        customer_name: ds.customerName || getText('.customer-name', 3),
        contact_number: ds.contactNumber || getText('.customer-contact'),
        email_id: ds.email || '',
        project_type: ds.projectType || getText('.type-cell .badge', 2),
        unit_no: ds.unit || getText('.unit-cell .badge', 1),
        size: ds.size || '',
        agreement_value: ds.agreement || '',
        commission_percent: ds.commission || '',
        revenue: ds.revenue || '',
        cashback_percent: ds.cashback || '',
        crevenue: ds.actualRevenue || '',
        astatus: ds.status || getText('td:nth-child(5) .badge', 4),
        recived_amt: ds.received || ''
    };
}

// Check if a row matches all active filters (year-wise version)
function matchesFiltersYearWise(row, explicitFilters) {
    const activeFilters = explicitFilters || window.activeFilters || {};
    const filtersToCheck = {...activeFilters};
    delete filtersToCheck.month;
    
    if (Object.keys(filtersToCheck).length === 0) {
        return true;
    }
    
    if (filtersToCheck.id) {
        const idVal = String(row.id || '').toLowerCase().trim();
        if (Array.isArray(filtersToCheck.id)) {
            const hasMatch = filtersToCheck.id.some(val => idVal === String(val).toLowerCase().trim());
            if (filtersToCheck.id.length > 0 && !hasMatch) return false;
        } else if (!idVal.includes(filtersToCheck.id)) {
            return false;
        }
    }
    
    // Date filtering - proper date comparison (same as userlogin6)
    if (filtersToCheck.bookingDateStart && row.booking_date) {
        const rowDateStr = String(row.booking_date).trim();
        const filterDateStr = String(filtersToCheck.bookingDateStart).trim();
        // If dates are in YYYY-MM-DD format, compare as strings (faster and more reliable)
        if (rowDateStr.match(/^\d{4}-\d{2}-\d{2}/) && filterDateStr.match(/^\d{4}-\d{2}-\d{2}/)) {
            if (rowDateStr < filterDateStr) return false;
        } else {
            // Fallback to Date object comparison
            const rowDate = new Date(rowDateStr);
            const filterDate = new Date(filterDateStr);
            if (!isNaN(rowDate.getTime()) && !isNaN(filterDate.getTime()) && rowDate < filterDate) return false;
        }
    }
    
    if (filtersToCheck.bookingDateEnd && row.booking_date) {
        const rowDateStr = String(row.booking_date).trim();
        const filterDateStr = String(filtersToCheck.bookingDateEnd).trim();
        // If dates are in YYYY-MM-DD format, compare as strings
        if (rowDateStr.match(/^\d{4}-\d{2}-\d{2}/) && filterDateStr.match(/^\d{4}-\d{2}-\d{2}/)) {
            if (rowDateStr > filterDateStr) return false;
        } else {
            // Fallback to Date object comparison
            const rowDate = new Date(rowDateStr);
            const filterDate = new Date(filterDateStr);
            if (!isNaN(rowDate.getTime()) && !isNaN(filterDate.getTime()) && rowDate > filterDate) return false;
        }
    }
    const builderVal = getField(row, ['builder', 'builder_name']).toLowerCase().trim();
    if (filtersToCheck.builder) {
        // Handle array (multi-select) or single value
        if (Array.isArray(filtersToCheck.builder)) {
            // For multi-select, check if builder value EXACTLY matches ANY of the selected values
            const hasMatch = filtersToCheck.builder.some(val => {
                const filterVal = String(val).toLowerCase().trim();
                const matches = builderVal === filterVal;  // EXACT match only
                return matches;
            });
            if (filtersToCheck.builder.length > 0 && !hasMatch) return false;
        } else {
            // For single value, use exact match
            if (builderVal !== filtersToCheck.builder) return false;
        }
    }
    const projectVal = getField(row, ['project', 'project_name']).toLowerCase().trim();
    if (filtersToCheck.project) {
        if (Array.isArray(filtersToCheck.project)) {
            const hasMatch = filtersToCheck.project.some(val => {
                const filterVal = String(val).toLowerCase().trim();
                return projectVal === filterVal;  // EXACT match only
            });
            if (filtersToCheck.project.length > 0 && !hasMatch) return false;
        } else {
            // For single value, use exact match
            if (projectVal !== filtersToCheck.project) return false;
        }
    }
    if (filtersToCheck.customerName) {
        const customerField = getField(row, ['customer_name', 'customer', 'client']).toLowerCase().trim();
        if (Array.isArray(filtersToCheck.customerName)) {
            const hasMatch = filtersToCheck.customerName.some(val => customerField === String(val).toLowerCase().trim());
            if (filtersToCheck.customerName.length > 0 && !hasMatch) return false;
        } else if (customerField !== filtersToCheck.customerName) {
            return false;
        }
    }
    const contactVal = getField(row, ['contact_number', 'mobile', 'phone']).toLowerCase().trim();
    if (filtersToCheck.contactNumber) {
        if (Array.isArray(filtersToCheck.contactNumber)) {
            const hasMatch = filtersToCheck.contactNumber.some(val => contactVal === String(val).toLowerCase().trim());
            if (filtersToCheck.contactNumber.length > 0 && !hasMatch) return false;
        } else if (contactVal !== filtersToCheck.contactNumber) {
            return false;
        }
    }
    const emailVal = getField(row, ['email_id', 'email']).toLowerCase().trim();
    if (filtersToCheck.email) {
        if (Array.isArray(filtersToCheck.email)) {
            const hasMatch = filtersToCheck.email.some(val => emailVal === String(val).toLowerCase().trim());
            if (filtersToCheck.email.length > 0 && !hasMatch) return false;
        } else if (emailVal !== filtersToCheck.email) {
            return false;
        }
    }
    const typeVal = getField(row, ['project_type', 'unit_type', 'type']).toLowerCase().trim();
    if (filtersToCheck.type) {
        if (Array.isArray(filtersToCheck.type)) {
            const hasMatch = filtersToCheck.type.some(val => typeVal === String(val).toLowerCase().trim());
            if (filtersToCheck.type.length > 0 && !hasMatch) return false;
        } else if (typeVal !== filtersToCheck.type) {
            return false;
        }
    }
    const unitVal = getField(row, ['unit_no', 'unit_number']).toLowerCase().trim();
    if (filtersToCheck.unit) {
        if (Array.isArray(filtersToCheck.unit)) {
            const hasMatch = filtersToCheck.unit.some(val => unitVal === String(val).toLowerCase().trim());
            if (filtersToCheck.unit.length > 0 && !hasMatch) return false;
        } else if (unitVal !== filtersToCheck.unit) {
            return false;
        }
    }
    const sizeVal = getField(row, ['size', 'unit_size']);
    if (filtersToCheck.size && !sizeVal.includes(filtersToCheck.size)) return false;
    const agreementVal = getField(row, ['agreement_value', 'agreement']).replace(/[^0-9.]/g, '');
    if (filtersToCheck.agreement && !agreementVal.includes(filtersToCheck.agreement.replace(/[^0-9]/g, ''))) return false;
    const commissionVal = getField(row, ['commission_percent', 'commission']).replace(/[^0-9.]/g, '');
    if (filtersToCheck.commission && commissionVal && !commissionVal.includes(filtersToCheck.commission.replace(/[^0-9.]/g, ''))) return false;
    const revenueVal = getField(row, ['revenue', 'total_revenue']).replace(/[^0-9.]/g, '');
    if (filtersToCheck.revenue && revenueVal && !revenueVal.includes(filtersToCheck.revenue.replace(/[^0-9]/g, ''))) return false;
    const cashbackVal = getField(row, ['cashback_percent', 'cashback']).replace(/[^0-9.]/g, '');
    if (filtersToCheck.cashback && cashbackVal && !cashbackVal.includes(filtersToCheck.cashback.replace(/[^0-9.]/g, ''))) return false;
    const actualRevenueVal = getField(row, ['crevenue', 'actual_revenue']).replace(/[^0-9.]/g, '');
    if (filtersToCheck.actualRevenue && actualRevenueVal && !actualRevenueVal.includes(filtersToCheck.actualRevenue.replace(/[^0-9]/g, ''))) return false;
    const statusVal = getField(row, ['astatus', 'status']).toLowerCase().trim();
    if (filtersToCheck.status) {
        if (Array.isArray(filtersToCheck.status)) {
            // For multi-select, check if status value EXACTLY matches ANY of the selected values
            const hasMatch = filtersToCheck.status.some(val => statusVal === String(val).toLowerCase().trim());
            if (filtersToCheck.status.length > 0 && !hasMatch) return false;
        } else if (statusVal !== filtersToCheck.status.toLowerCase()) {
            return false;
        }
    }
    const receivedVal = getField(row, ['recived_amt', 'received_amount']).replace(/[^0-9.]/g, '');
    if (filtersToCheck.received && receivedVal && !receivedVal.includes(filtersToCheck.received.replace(/[^0-9]/g, ''))) return false;
    
    return true;
}

// Filter nested data for a specific year
function filterNestedData(year) {
    const activeFilters = window.activeFilters || {};
    const nestedContainer = document.getElementById('nested-data-' + year);
    if (!nestedContainer) return 0;
    
    const rows = nestedContainer.querySelectorAll('.compact-row');
    let visibleCount = 0;
    
    rows.forEach((row, index) => {
        let rowData;
        if (window.groupedRows && window.groupedRows[year] && window.groupedRows[year][index]) {
            rowData = window.groupedRows[year][index];
        } else {
            rowData = extractRowData(row, year);
        }
        
        if (rowData) {
            // Check month filter separately if it exists
            let matchesMonth = true;
            if (activeFilters.month && rowData.booking_date) {
                const target = activeFilters.month;
                let targetYear = null;
                let targetMonth = null;
                
                if (target.match(/^\d{4}-\d{2}$/)) {
                    [targetYear, targetMonth] = target.split('-').map(Number);
                }
                
                if (targetYear && targetMonth) {
                    const bookingDate = new Date(rowData.booking_date);
                    if (!isNaN(bookingDate.getTime())) {
                        const bookingYear = bookingDate.getFullYear();
                        const bookingMonth = bookingDate.getMonth() + 1;
                        matchesMonth = (bookingYear === targetYear && bookingMonth === targetMonth);
                    } else {
                        matchesMonth = false;
                    }
                }
            }
            
            // Check other filters (excluding month as it's handled above)
            const filtersWithoutMonth = {...activeFilters};
            delete filtersWithoutMonth.month;
            const matchesOtherFilters = Object.keys(filtersWithoutMonth).length === 0 || matchesFiltersYearWise(rowData);
            
            const matches = matchesMonth && matchesOtherFilters;
            
            if (matches) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        } else {
            row.style.display = 'none';
        }
    });
    
    if (typeof updateNestedPagination === 'function') {
        updateNestedPagination(year);
    }
    
    return visibleCount;
}

// Update per-year summary cards
function updateYearSummaryGrid(year, overrideData = {}) {
    const yearData = (window.yearlyData || []).find(yd => yd.year === year) || {};
    const merged = {
        totalRevenue: overrideData.totalRevenue ?? yearData.totalRevenue ?? 0,
        actualRevenue: overrideData.actualRevenue ?? yearData.actualRevenue ?? 0,
        invoice_raised: overrideData.invoice_raised ?? yearData.invoice_raised ?? 0,
        recivedRevenue: overrideData.recivedRevenue ?? yearData.recivedRevenue ?? 0,
        totalPaidsalary: overrideData.totalPaidsalary ?? yearData.totalPaidsalary ?? 0,
        totalExpensesAmt: overrideData.totalExpensesAmt ?? yearData.totalExpensesAmt ?? 0,
        buildIncentive: overrideData.buildIncentive ?? ((yearData.totalAmtPay ?? 0) + (yearData.totalPaidmanager ?? 0)),
        totalPaid: overrideData.totalPaid ?? yearData.totalPaid ?? 0
    };

    function formatCompactNumber(number) {
        const n = Number(number) || 0;
        if (n >= 1000000000) return '₹' + (n / 1000000000).toFixed(1) + 'B';
        if (n >= 1000000) return '₹' + (n / 1000000).toFixed(1) + 'M';
        if (n >= 1000) return '₹' + (n / 1000).toFixed(1) + 'K';
        return '₹' + n.toFixed(0);
    }

    const summaryValues = {
        revenue: merged.totalRevenue,
        remaining: merged.actualRevenue - merged.invoice_raised,
        recent: merged.recivedRevenue,
        salary: merged.totalPaidsalary,
        other: merged.totalExpensesAmt,
        build: merged.buildIncentive,
        paid: merged.totalPaid
    };

    Object.entries(summaryValues).forEach(([key, value]) => {
        const el = document.getElementById(`year-summary-${key}-${year}`);
        if (el) {
            el.textContent = formatCompactNumber(value);
        }
    });
}

// Ensure remaining column visibility aligns with viewport width
function applyRemainingVisibility() {
    const w = window.innerWidth;
    const hide = w <= 1024;
    document.querySelectorAll('.remaining-col').forEach(el => {
        el.style.display = hide ? 'none' : 'table-cell';
    });
}

// Update year row statistics based on filtered data
function updateYearRowStats(year, rowElement, preFilteredRows) {
    if (!window.groupedRows || !window.groupedRows[year]) return;
    
    const activeFilters = window.activeFilters || {};
    const hasActiveFilters = Object.keys(activeFilters).length > 0;
    const filteredRows = Array.isArray(preFilteredRows) ? preFilteredRows : getFilteredRowsForYear(year, activeFilters);
    
    // Get original year data
    const yearData = (window.yearlyData || []).find(yd => yd.year === year) || {};
    
    let rowcount = filteredRows.length;
    let totalRevenue = 0;
    let actualRevenue = 0;
    let recivedRevenue = 0;
    let invoice_raised = 0;
    let total_raised = 0;
    let processingCount = 0;
    let canceledCount = 0;
    let receivedCount = 0;
    
    // When filters are active, calculate ALL values from filtered rows only
    // When no filters, we'll use yearData for year-level metrics
    let totalPaidsalary = 0;
    let totalExpensesAmt = 0;
    let buildIncentive = 0;
    let totalPaid = 0;
    
    filteredRows.forEach(row => {
        totalRevenue += parseFloat(row.revenue || 0);
        actualRevenue += parseFloat(row.crevenue || 0);
        recivedRevenue += parseFloat(row.recived_amt || 0);
        invoice_raised += parseFloat(row.invoice_raise || 0);
        total_raised += parseFloat(row.update_in_invoice_table || 0);

        const statusVal = String(row.astatus || row.status || '').toLowerCase();
        if (statusVal.includes('cancel')) {
            canceledCount++;
        } else if (statusVal.includes('receive') || statusVal.includes('completed')) {
            receivedCount++;
        } else if (statusVal) {
            processingCount++;
        }
    });
    
    // Year-level expenses/incentives:
    // - Salary/Other expenses come from other tables; keep year totals regardless of booking-level filters.
    // - Build incentive / Total paid: if the booking rows have fields, compute from the filtered rows.
    totalPaidsalary = yearData.totalPaidsalary || 0;
    totalExpensesAmt = yearData.totalExpensesAmt || 0;

    if (hasActiveFilters) {
        // Try to compute these from filtered booking rows.
        buildIncentive = 0;
        totalPaid = 0;
        filteredRows.forEach(r => {
            buildIncentive += parseFloat(r.getamount || r.get_amount || r.build_incentive || 0);
            totalPaid += parseFloat(r.send_amt || r.paid_amt || 0);
        });
    } else {
        buildIncentive = (yearData.totalAmtPay || 0) + (yearData.totalPaidmanager || 0);
        totalPaid = yearData.totalPaid || 0;
    }
    
    function formatCompactNumber(number) {
        if (number >= 1000000000) return '₹' + (number / 1000000000).toFixed(1) + 'B';
        if (number >= 1000000) return '₹' + (number / 1000000).toFixed(1) + 'M';
        if (number >= 1000) return '₹' + (number / 1000).toFixed(1) + 'K';
        return '₹' + number.toFixed(0);
    }
    
    const monthCell = rowElement.querySelector('.month-cell');
    if (monthCell) {
        const icon = monthCell.querySelector('.icon.calendar');
        const iconHTML = icon ? icon.outerHTML : '';
        monthCell.innerHTML = iconHTML + ' ' + `${year} (${rowcount}/${total_raised})`;
    }
    
    const actualCell = rowElement.querySelector('.actual-revenue-cell');
    if (actualCell) {
        actualCell.innerHTML = '<i class="fas fa-wallet"></i> ' + formatCompactNumber(actualRevenue);
        actualCell.className = 'actual-revenue-cell amount ' + (actualRevenue > 0 ? 'positive' : 'zero');
    }

    const remainingCell = rowElement.querySelector('.remaining-amount-cell');
    if (remainingCell) {
        const remaining = actualRevenue - invoice_raised;
        remainingCell.innerHTML = '<i class="fas fa-coins"></i> ' + formatCompactNumber(remaining);
        remainingCell.className = 'remaining-col remaining-amount-cell amount ' + (remaining > 0 ? 'positive' : 'zero');
    }

    // Update year summary grid with calculated values
    updateYearSummaryGrid(year, { 
        totalRevenue,
        actualRevenue,
        invoice_raised,
        recivedRevenue,
        totalPaidsalary,
        totalExpensesAmt,
        buildIncentive,
        totalPaid
    });

    // Update status counters in header
    const nestedRow = rowElement?.nextElementSibling;
    const statusContainer = nestedRow?.querySelector('.status-counts');
    if (statusContainer) {
        const procEl = statusContainer.querySelector('.processing');
        const cancEl = statusContainer.querySelector('.cancelled');
        const recvEl = statusContainer.querySelector('.received');
        if (procEl) procEl.textContent = `Total Processing :- ${processingCount}`;
        if (cancEl) cancEl.textContent = `Total Cancelled :- ${canceledCount}`;
        if (recvEl) recvEl.textContent = `Total Received :- ${receivedCount}`;
    } else {
        const nestedById = document.getElementById(`nested-${year}`);
        const statusContainerAlt = nestedById?.querySelector('.status-counts');
        if (statusContainerAlt) {
            const procEl = statusContainerAlt.querySelector('.processing');
            const cancEl = statusContainerAlt.querySelector('.cancelled');
            const recvEl = statusContainerAlt.querySelector('.received');
            if (procEl) procEl.textContent = `Total Processing :- ${processingCount}`;
            if (cancEl) cancEl.textContent = `Total Cancelled :- ${canceledCount}`;
            if (recvEl) recvEl.textContent = `Total Received :- ${receivedCount}`;
        }
    }

    // Update main row revenue/paid columns
    const mainCells = rowElement.querySelectorAll('.build-incentive-cell');
    if (mainCells && mainCells.length >= 2) {
        const [buildCell, paidCell] = mainCells;
        buildCell.innerHTML = '<i class="fas fa-coins"></i> ' + formatCompactNumber(buildIncentive);
        paidCell.innerHTML = '<i class="fas fa-gift"></i> ' + formatCompactNumber(totalPaid);
    }

    return { rowcount, totalRevenue, actualRevenue, recivedRevenue, invoice_raised, total_raised };
}

// Filter yearly data based on active filters - EXPOSE TO WINDOW IMMEDIATELY
window.filterYearlyData = function() {
    const activeFilters = window.activeFilters || {};
    const yearlyRows = document.querySelectorAll('#mainTableBody .table-row[onclick]');
    
    yearlyRows.forEach(row => {
        try {
            const onclick = row.getAttribute('onclick') || '';
            const m = onclick.match(/toggleRow\('([^']+)'\)/);
            const year = m ? m[1] : null;
            
            if (!year) {
                if (row && row.style) row.style.display = 'none';
                return;
            }

            const filteredRows = getFilteredRowsForYear(year, activeFilters);
            const hasMatchingBookings = filteredRows.length > 0;
            
            // Show/hide year row based on whether it has matching bookings
            if (hasMatchingBookings) {
                if (row && row.style) row.style.display = '';
                
                // Apply filters to nested data for this year (only if nested row exists)
                const nested = document.getElementById('nested-' + year);
                if (nested) {
                    try {
                        const fns = window.__superadminBookingsYearFns || {};
                        const doFilterNested = fns.filterNestedData || window.filterNestedData;
                        const doUpdateNested = fns.updateNestedPagination || window.updateNestedPagination;

                        if (typeof doFilterNested === 'function') {
                            doFilterNested(year);
                        }

                        updateYearRowStats(year, row, filteredRows);

                        // Always enforce final row visibility/pagination after applying filters
                        // Pass the filtered count AND data directly to avoid re-computation issues
                        if (typeof doUpdateNested === 'function') {
                            doUpdateNested(year, filteredRows.length, filteredRows);
                        }
                    } catch (err) {
                        // Error filtering nested data
                    }
                } else {
                    updateYearRowStats(year, row, filteredRows);
                }
            } else {
                // Hide year row if no bookings match
                if (row && row.style) row.style.display = 'none';
                const nested = document.getElementById('nested-' + year);
                if (nested && nested.style) nested.style.display = 'none';
            }
        } catch (error) {
            // Error in filterYearlyData
        }
    });
};

// Restore original view (OPTIMIZED FOR SPEED)
function restoreOriginalView() {
    
    // Clear filters FIRST
    window.activeFilters = {};
    window.isFilterApplied = false;
    if (typeof isFilterApplied !== 'undefined') isFilterApplied = false;
    
    const yearlyRows = document.querySelectorAll('#mainTableBody .table-row[onclick]');
    const yearsToUpdate = [];
    
    yearlyRows.forEach(row => {
        row.style.display = '';
        const onclick = row.getAttribute('onclick') || '';
        const m = onclick.match(/toggleRow\('([^']+)'\)/);
        const year = m ? m[1] : null;
        if (year && window.yearlyData) {
            const yearData = window.yearlyData.find(yd => yd.year === year);
            if (yearData) {
                updateYearRowStatsFromData(year, row, yearData);
                yearsToUpdate.push(year);
            }
        }
    });
    
    // Update clear filter button visibility (no delay)
    if (typeof window.updateClearFilterButtonVisibility === 'function') {
        window.updateClearFilterButtonVisibility();
    }
    
    const nestedSections = document.querySelectorAll('[id^="nested-"]');
    nestedSections.forEach(nested => {
        const year = nested.id.replace('nested-', '');
        const mainRow = document.querySelector(`[onclick*="toggleRow('${year}')"]`);
        
        // Check if nested section was previously expanded
        const expandIcon = document.getElementById(`expand-${year}`);
        const wasExpanded = expandIcon && expandIcon.style.transform === 'rotate(90deg)';
        
        // Show all nested rows (remove filters)
        nested.querySelectorAll('.compact-row').forEach(row => {
            row.style.display = '';
        });
        
        // Reset pagination state
        if (window.nestedCurrentPages) window.nestedCurrentPages[year] = 1;
        if (window.nestedPerPageValues) window.nestedPerPageValues[year] = 5;
        if (window.nestedSearchTerms) window.nestedSearchTerms[year] = '';
        
        // Restore nested section visibility based on previous state
        if (mainRow && wasExpanded) {
            nested.style.display = 'table-row';
        } else {
            nested.style.display = 'none';
        }
    });
    
    // Update all nested pagination in one batch (faster - no setTimeout)
    const fns = window.__superadminBookingsYearFns || {};
    const doUpdate = fns.updateNestedPagination || window.updateNestedPagination;
    if (typeof doUpdate === 'function') {
        yearsToUpdate.forEach(year => {
            doUpdate(year);
        });
    }
    
    document.querySelectorAll('.no-results-row').forEach(el => el.remove());

    // Reset top stat cards back to initial overall numbers
    updateOverallStatCards(undefined, false);
    
    // Update main pagination text to show all entries
    const allRows = getAllRows();
    updateMainPaginationText(allRows.length);
    
}

// Update year row stats from yearlyData (for restoring original view)
function updateYearRowStatsFromData(year, rowElement, yearData) {
    function formatCompactNumber(number) {
        if (number >= 1000000000) return '₹' + (number / 1000000000).toFixed(1) + 'B';
        if (number >= 1000000) return '₹' + (number / 1000000).toFixed(1) + 'M';
        if (number >= 1000) return '₹' + (number / 1000).toFixed(1) + 'K';
        return '₹' + number.toFixed(0);
    }
    
    const monthCell = rowElement.querySelector('.month-cell');
    if (monthCell) {
        const icon = monthCell.querySelector('.icon.calendar');
        const iconHTML = icon ? icon.outerHTML : '';
        monthCell.innerHTML = iconHTML + ' ' + `${year} (${yearData.bookings}/${yearData.total_raised})`;
    }
    
    const actualCell = rowElement.querySelector('.actual-revenue-cell');
    if (actualCell) {
        actualCell.innerHTML = '<i class="fas fa-wallet"></i> ' + formatCompactNumber(yearData.actualRevenue);
        actualCell.className = 'actual-revenue-cell amount ' + (yearData.actualRevenue > 0 ? 'positive' : 'zero');
    }

    const remainingCell = rowElement.querySelector('.remaining-amount-cell');
    if (remainingCell) {
        const remaining = yearData.actualRevenue - yearData.invoice_raised;
        remainingCell.innerHTML = '<i class="fas fa-coins"></i> ' + formatCompactNumber(remaining);
        remainingCell.className = 'remaining-col remaining-amount-cell amount ' + (remaining > 0 ? 'positive' : 'zero');
    }

    // IMPORTANT: Also restore status counts in nested section
    const nestedSection = document.getElementById(`nested-${year}`);
    if (nestedSection) {
        const statusContainer = nestedSection.querySelector('.status-counts');
        if (statusContainer) {
            const procEl = statusContainer.querySelector('.processing');
            const cancEl = statusContainer.querySelector('.cancelled');
            const recvEl = statusContainer.querySelector('.received');
            if (procEl) procEl.textContent = `Total Processing :- ${yearData.processing_count || 0}`;
            if (cancEl) cancEl.textContent = `Total Cancelled :- ${yearData.canceled_count || 0}`;
            if (recvEl) recvEl.textContent = `Total Received :- ${yearData.received_count || 0}`;
        }
    }

    updateYearSummaryGrid(year, {
        totalRevenue: yearData.totalRevenue,
        actualRevenue: yearData.actualRevenue,
        invoice_raised: yearData.invoice_raised,
        recivedRevenue: yearData.recivedRevenue
    });
}

// Show no results message
function showNoResultsMessage() {
    document.querySelectorAll('.no-results-row').forEach(el => el.remove());
    const tbody = document.getElementById('mainTableBody');
    if (!tbody) return;
    const headerCount = document.querySelectorAll('.table-head th').length || 5;
    const noResultsRow = document.createElement('tr');
    noResultsRow.className = 'no-results-row';
    noResultsRow.innerHTML = `<td colspan="${headerCount}" style="text-align: center; padding: 2rem; color: #64748b; font-style: italic;">No bookings match your current filters. Try adjusting your search criteria.</td>`;
    tbody.appendChild(noResultsRow);
}

// Make functions globally available
window.filterYearlyData = filterYearlyData;
window.checkYearHasMatchingData = checkYearHasMatchingData;
window.extractRowData = extractRowData;
window.matchesFiltersYearWise = matchesFiltersYearWise;
window.filterNestedData = filterNestedData;
window.updateYearRowStats = updateYearRowStats;
window.restoreOriginalView = restoreOriginalView;
window.updateYearRowStatsFromData = updateYearRowStatsFromData;
window.showNoResultsMessage = showNoResultsMessage;
window.getFilteredRowsForYear = getFilteredRowsForYear;
window.getFilteredRows = getFilteredRows;
window.getAllRows = getAllRows;
window.matchesMonthFilter = matchesMonthFilter;

// Preserve year-wise filter functions too (external JS can overwrite globals)
window.__superadminBookingsYearFns = window.__superadminBookingsYearFns || {};
window.__superadminBookingsYearFns.filterYearlyData = filterYearlyData;
window.__superadminBookingsYearFns.restoreOriginalView = restoreOriginalView;
window.__superadminBookingsYearFns.filterNestedData = filterNestedData;
window.__superadminBookingsYearFns.matchesFiltersYearWise = matchesFiltersYearWise;
window.__superadminBookingsYearFns.extractRowData = extractRowData;
window.__superadminBookingsYearFns.updateYearRowStats = updateYearRowStats;
window.__superadminBookingsYearFns.updateYearRowStatsFromData = updateYearRowStatsFromData;
window.__superadminBookingsYearFns.getFilteredRowsForYear = getFilteredRowsForYear;
window.__superadminBookingsYearFns.getFilteredRows = getFilteredRows;
    
    // Apply remaining column visibility on load and resize
    applyRemainingVisibility();
    window.addEventListener('resize', applyRemainingVisibility);

// Clear Filter Floating Button
(function() {
    let clearFilterBtn = null;

    // When embedded in the superadmin wrapper, let the parent handle the floating clear button
    const isEmbedded = window.self !== window.top;
    if (isEmbedded) {
        return;
    }
    
    // Function to create the button
    function createClearFilterButton() {
        if (clearFilterBtn) return; // Already created
        
        // Wait for body to be available
        if (!document.body) {
            setTimeout(createClearFilterButton, 100);
            return;
        }
        
        // Create the floating clear filter button
        clearFilterBtn = document.createElement('button');
        clearFilterBtn.id = 'clearFilterFloatingBtn';
        clearFilterBtn.className = 'clear-filter-floating-btn';
        clearFilterBtn.innerHTML = '<i class="fas fa-times-circle"></i> Clear Filters';
        clearFilterBtn.onclick = function() {
            if (typeof window.clearFilters === 'function') {
                window.clearFilters();
            } else if (typeof clearFilters === 'function') {
                clearFilters();
            }
            updateClearFilterButtonVisibility();
        };
        document.body.appendChild(clearFilterBtn);
    }
    
    // Function to update button visibility
    function updateClearFilterButtonVisibility() {
        if (!clearFilterBtn) {
            createClearFilterButton();
            setTimeout(updateClearFilterButtonVisibility, 200);
            return;
        }
        
        const hasFilters = window.isFilterApplied && window.activeFilters && Object.keys(window.activeFilters).length > 0;
        
        if (hasFilters) {
            clearFilterBtn.style.setProperty('display', 'flex', 'important');
            clearFilterBtn.style.setProperty('visibility', 'visible', 'important');
            clearFilterBtn.style.setProperty('opacity', '1', 'important');
            clearFilterBtn.style.setProperty('pointer-events', 'auto', 'important');
            clearFilterBtn.style.setProperty('z-index', '9999999', 'important');
        } else {
            clearFilterBtn.style.setProperty('display', 'none', 'important');
        }
    }
    
    // Create button when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', createClearFilterButton);
    } else {
        createClearFilterButton();
    }
    
    // Also try creating immediately
    createClearFilterButton();
    
    // Update visibility when filters change - wrap applyFilters
    const originalApplyFilters = window.applyFilters;
    if (originalApplyFilters) {
        window.applyFilters = function(...args) {
            const result = originalApplyFilters.apply(this, args);
            setTimeout(() => {
                updateClearFilterButtonVisibility();
            }, 300);
            return result;
        };
    }
    
    // Watch for filter state changes more frequently
    let checkCount = 0;
    const checkFilterState = setInterval(() => {
        updateClearFilterButtonVisibility();
        checkCount++;
        // Stop checking after 30 seconds to avoid infinite loop
        if (checkCount > 30) {
            clearInterval(checkFilterState);
        }
    }, 1000);
    
    // Initial checks with multiple delays
    setTimeout(updateClearFilterButtonVisibility, 500);
    setTimeout(updateClearFilterButtonVisibility, 1000);
    setTimeout(updateClearFilterButtonVisibility, 2000);
    setTimeout(updateClearFilterButtonVisibility, 3000);
    
    // Also listen for window load
    window.addEventListener('load', function() {
        setTimeout(updateClearFilterButtonVisibility, 500);
        
        // Initialize banner state on load
        setTimeout(() => {
            const isFiltered = window.isFilterApplied && window.activeFilters && Object.keys(window.activeFilters).length > 0;
            console.log('[INIT] Page loaded - isFiltered:', isFiltered);
            if (typeof updateFilterSummaryBanner === 'function') {
                const rows = isFiltered ? getFilteredRows(window.activeFilters || {}) : getAllRows();
                const stats = computeOverallFromRows(rows);
                updateFilterSummaryBanner(stats, isFiltered);
            }
        }, 500);
    });
    
    // Expose function globally
    window.updateClearFilterButtonVisibility = updateClearFilterButtonVisibility;
    
    // Add test function to show banner manually (for debugging)
    window.testShowFilterBanner = function() {
        const banner = document.getElementById('filterSummaryBanner');
        if (banner) {
            banner.style.display = 'block';
            console.log('[TEST] Banner manually shown');
        } else {
            console.error('[TEST] Banner element not found');
        }
    };
    
    // Also expose a manual trigger for testing
    window.showClearFilterButton = function() {
        if (clearFilterBtn) {
            clearFilterBtn.style.display = 'flex';
        } else {
            createClearFilterButton();
        }
    };
})();
</script>

<style>
/* Filter Summary Banner Styles */
.filter-summary-banner {
    background: linear-gradient(135deg, #e0e7ff 0%, #e7e5ff 100%);
    padding: 1.5rem 2rem;
    box-shadow: 0 10px 25px -5px rgba(99, 102, 241, 0.15);
    animation: slideDown 0.3s ease-out;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.filter-summary-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1rem;
}

.filter-summary-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0;
    letter-spacing: -0.02em;
}

.filter-summary-stats {
    display: flex;
    flex-wrap: wrap;
    gap: 2rem;
    align-items: center;
}

.filter-stat-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.filter-stat-label {
    font-size: 0.95rem;
    font-weight: 600;
    color: #475569;
}

.filter-stat-value {
    font-size: 1rem;
    font-weight: 700;
    color: #1e293b;
}

@media (max-width: 768px) {
    .filter-summary-banner {
        padding: 1.25rem 1.5rem;
        margin-bottom: 1rem;
    }

    .filter-summary-title {
        font-size: 1.25rem;
    }

    .filter-summary-stats {
        gap: 1rem;
    }

    .filter-stat-label {
        font-size: 0.875rem;
    }

    .filter-stat-value {
        font-size: 0.95rem;
    }
}

@media (max-width: 480px) {
    .filter-summary-banner {
        padding: 1rem 1.25rem;
    }

    .filter-summary-title {
        font-size: 1.1rem;
        margin-bottom: 0.5rem;
    }

    .filter-summary-stats {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.75rem;
    }

    .filter-stat-item {
        width: 100%;
        justify-content: space-between;
    }

    .filter-stat-label {
        font-size: 0.85rem;
    }

    .filter-stat-value {
        font-size: 0.9rem;
    }
}

/* Clear Filter Floating Button */
#clearFilterFloatingBtn,
.clear-filter-floating-btn {
    position: fixed !important;
    bottom: 2rem !important;
    right: 2rem !important;
    z-index: 9999999 !important; /* Extremely high z-index to ensure it's on top of everything */
    display: none !important; /* Hidden by default, shown when filters are active */
    align-items: center !important;
    gap: 0.5rem !important;
    padding: 0.875rem 1.5rem !important;
    background: linear-gradient(135deg, #ef4444, #dc2626) !important;
    color: #fff !important;
    border: none !important;
    border-radius: 2rem !important;
    font-size: 0.875rem !important;
    font-weight: 600 !important;
    cursor: pointer !important;
    box-shadow: 0 10px 25px -5px rgba(239, 68, 68, 0.4), 0 4px 6px -2px rgba(0, 0, 0, 0.1) !important;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif !important;
    pointer-events: auto !important;
    visibility: visible !important;
    opacity: 1 !important;
    margin: 0 !important;
    max-width: none !important;
    min-width: auto !important;
}

.clear-filter-floating-btn:hover {
    transform: translateY(-2px) scale(1.05) !important;
    box-shadow: 0 15px 35px -5px rgba(239, 68, 68, 0.5), 0 6px 10px -2px rgba(0, 0, 0, 0.15) !important;
    background: linear-gradient(135deg, #f87171, #ef4444) !important;
}

.clear-filter-floating-btn:active {
    transform: translateY(0) scale(0.98) !important;
}

.clear-filter-floating-btn i {
    font-size: 1.125rem !important;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .clear-filter-floating-btn {
        bottom: 1.5rem !important;
        right: 1.5rem !important;
        padding: 0.75rem 1.25rem !important;
        font-size: 0.8125rem !important;
    }
    
    .clear-filter-floating-btn i {
        font-size: 1rem !important;
    }
}

/* Animation for button appearance */
@keyframes slideInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.clear-filter-floating-btn[style*="flex"] {
    animation: slideInUp 0.3s ease-out !important;
}

/* Nested toolbar layout and per-page dropdown (match userlogin6 visual) */
.nested-controls {
    display: flex;
    flex-wrap: nowrap;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 0.3rem;
    padding:0.3rem;
}

.nested-search-wrapper {
    flex: 1 1;
    min-width: 220px;
}

.per-page-selector {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 0.4rem 0.75rem;
}

.per-page-selector span {
    font-weight: 600;
    font-size: 0.9rem;
    color: #475569;
}

.per-page-select {
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    padding: 0.35rem 1.9rem 0.35rem 0.75rem;
    background: #fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E") no-repeat right 10px center;
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    min-width: 68px;
    font-weight: 600;
    color: #0f172a;
    cursor: pointer;
}

.per-page-select:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
}

/* Dark mode styles for per-page select */
body.dark-mode .per-page-select {
    background-position: right 4px center !important;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23ffffff' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E") !important;
    background-repeat: no-repeat !important;
    border-color: #475569;
    color: #e2e8f0;
}

body.dark-mode .per-page-select:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.25);
}

body.dark-mode .per-page-selector span {
    color: #cbd5e1;
}

@media (max-width: 768px) {
    .nested-controls {
        align-items: stretch;
    }
    .nested-search-wrapper {
        min-width: auto;
    }
    .per-page-selector {
        width: 100%;
        justify-content: flex-end;
        padding: 0.35rem 0.6rem;
    }
}

@media (max-width: 375px){
    .mobile-year-stats {
        gap: 8px;
        margin-bottom: 0.75rem;
    }
}

@media (max-width: 425px){
    .mobile-year-stats {
        gap: 8px;
        margin-bottom: 0.75rem;
    }
    .mobile-year-stats .stat-chip span {
        font-size: 0.6rem;
        font-weight: 600;
        color: #475569;
        text-transform: uppercase;
        letter-spacing: 0.02em;
    }
}

/* Mobile summary chips for hidden main-table columns */
.mobile-year-stats {
    display: flex !important;
    flex-wrap: nowrap !important;
    width: 100vw !important;
    overflow-x: auto !important;
    scrollbar-width: none !important;
    gap: 8px;
    margin-bottom: 0.3rem;
}

.mobile-year-stats .stat-chip {
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding: 0.65rem 0.75rem;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    background: linear-gradient(135deg, #f8fafc, #eef2ff);
    box-shadow: 0 6px 12px -8px rgba(15, 23, 42, 0.2);
    width: -webkit-fill-available;
    min-width: 150px;
}

/* Financial Year Header Styles */
.financial-year-header {
    background: radial-gradient(circle at 20% 20%, #42A1A7 3%, transparent 25%), radial-gradient(circle at 70% 90%, #42A1A7 1%, transparent 34%), var(--primary-teal);
    padding: 0.8rem 1.5rem;
    border-top-right-radius: 10px;
    border-top-left-radius: 10px;
    margin-bottom: 0.3rem;
}

.financial-year-title {
    font-size: 1.5rem;
    font-weight: 700;
    color: #ffffff;
    margin: 0 0 0.75rem 0;
    letter-spacing: 0.02em;
}

.status-counts {
    display: flex;
    flex-wrap: wrap;
    gap: 1.25rem;
    align-items: center;
}

.status-count {
    font-size: 0.95rem;
    font-weight: 600;
    color: #ffffff;
    display: inline-flex;
    align-items: center;
    padding: 0.35rem 0.75rem;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    backdrop-filter: blur(10px);
}

.status-count.processing {
    background: rgb(251 228 36 / 38%);
}

.status-count.cancelled {
    background: rgb(239 68 68 / 53%);
}

.status-count.received {
    background: rgb(34 197 94 / 41%);
}

@media (max-width: 768px) {
    .financial-year-header {
        padding: 1rem 1.25rem;
    }

    .financial-year-title {
        font-size: 1.25rem;
        margin-bottom: 0.5rem;
    }

    .status-counts {
        gap: 0.75rem;
    }

    .status-count {
        font-size: 0.85rem;
        padding: 0.3rem 0.6rem;
    }
}

@media (max-width: 480px) {
    .financial-year-header {
        padding: 0.875rem 1rem;
    }

    .financial-year-title {
        font-size: 1.1rem;
    }

    .status-counts {
        align-items: flex-start;
        gap: 0.5rem;
    }

    .status-count {
        font-size: 0.8rem;
    }
}

.summary-label {
    font-size: 0.8rem;
    font-weight: 600;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.summary-value {
    font-size: 1rem;
    font-weight: 700;
    color: #0f172a;
}

/* Always show remaining column */
.remaining-col {
    display: table-cell;
    min-width: 140px;
}

.mobile-year-stats .stat-chip span {
    font-size: 0.8rem;
    font-weight: 600;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 0.02em;
}

.mobile-year-stats .stat-chip strong {
    font-size: 0.95rem;
    color: #0f172a;
}

@media (max-width: 1200px) {
    .mobile-year-stats {
        display: flex;
    }
}

/* Responsive main table columns for smaller viewports */
@media (max-width: 1200px) {
    .table-card .table-container {
        overflow-x: auto;
    }
    .main-bookings-table .table-head th,
    .main-bookings-table tbody td {
        white-space: nowrap;
    }
}

/* Action buttons styling for Edit and Download in Sales Person section */
.editLink i,
.btn-download i,
.btn[download] i {
    transition: all 0.2s ease;
}

.editLink:hover,
.btn[download]:hover {
    background: #f5f5f5 !important;
    border-color: #1a73e8 !important;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1) !important;
    text-decoration: none !important;
}

.editLink:hover i,
.btn[download]:hover i {
    color: #0d5bb5 !important;
}

/* Show More/Less button styling in Actions column */
.btn-toggle {
    padding: 8px 16px;
    border: 1px solid #ddd;
    background: white;
    border-radius: 8px;
    cursor: pointer;
    font-size: 13px;
    transition: all 0.2s ease;
}

.btn-toggle:hover {
    background: #f8f9fa;
    border-color: #1a73e8;
    color: #1a73e8;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
/* Contact Details & Property Values Truncate with Ellipsis for smaller screens */
/* Only apply when the .truncated class is explicitly added by JavaScript */
/* In the mobile modal, allow both truncated and expanded states */
.mobile-detail-modal .detail-text.truncated,
.mobile-detail-modal .detail-value.truncated {
    max-width: 100px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    cursor: pointer !important;
    display: inline-block;
    pointer-events: auto !important;
}

.mobile-detail-modal .detail-text.expanded,
.mobile-detail-modal .detail-value.expanded {
    max-width: none !important;
    width: 100% !important;
    display: block !important;
    white-space: normal !important;
    overflow: visible !important;
    text-overflow: clip !important;
    word-break: break-word !important;
    cursor: pointer !important;
    pointer-events: auto !important;
}

@media (max-width: 1024px) {
    .contact-info .detail-item .detail-text.truncated,
    .detail-value.truncated {
        max-width: 200px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        cursor: pointer;
        transition: all 0.3s ease;
        word-break: break-all;
        display: inline-block;
    }
    
    .contact-info .detail-item .detail-text.expanded,
    .detail-value.expanded {
        max-width: none !important;
        white-space: normal !important;
        overflow: visible !important;
        overflow-wrap: break-word !important;
        word-wrap: break-word !important;
        word-break: break-word !important;
        display: block !important;
        height: auto !important;
    }
    
    .contact-info .detail-item .detail-text:hover,
    .detail-value:hover {
        color: #2563eb;
    }
}

@media (max-width: 768px) {
    /* Keep truncation narrow only when the element is still collapsed */
    .contact-info .detail-item .detail-text.truncated {
        max-width: 150px;
    }
    .detail-value.truncated {
        max-width: 120px;
    }

    /* When expanded, allow full width and natural wrapping */
    .contact-info .detail-item .detail-text.expanded,
    .detail-value.expanded {
        max-width: none !important;
        width: 100% !important;
        display: block !important;
    }
}

@media (max-width: 480px) {
    /* Keep truncation narrow only when the element is still collapsed */
    .contact-info .detail-item .detail-text.truncated {
        max-width: 180px;
    }
    .detail-value.truncated {
        max-width: 100px;
    }

    /* When expanded, allow full width and natural wrapping */
    .contact-info .detail-item .detail-text.expanded,
    .detail-value.expanded {
        max-width: none !important;
        width: 100% !important;
        display: block !important;
    }
}
    </style>

    <!-- Bridge embedded buttons to parent modals (superadmin wrapper) -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const isEmbedded = window.self !== window.top;
        const addBtn = document.getElementById('addBookingButton');
        const filterBtn = document.getElementById('filterButton');
        const calcBtn = document.getElementById('calculatorButton');
        const downloadBtn = document.getElementById('downloadCsvButton');

        function openModal(fnName, fallback) {
            // Prefer calling the parent page modal when embedded
            if (isEmbedded && window.parent && typeof window.parent[fnName] === 'function') {
                window.parent[fnName]();
                return true;
            }
            // Fall back to local handler if available (non-embedded use)
            if (typeof fallback === 'function') {
                fallback();
                return true;
            }
            return false;
        }

        if (addBtn) {
            addBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const handled = openModal('openAddBookingModal', window.openAddBookingModal);
                if (!handled) {
                    console.warn('Add booking modal not available in this view.');
                }
            });
        }

        if (filterBtn) {
            filterBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const handled = openModal('openFilterModal', window.openFilterModal);
                if (!handled) {
                    console.warn('Filter modal not available in this view.');
                }
            });
        }

        if (calcBtn) {
            calcBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const handled = openModal('openCalculatorModal', window.openCalculatorModal);
                if (!handled) {
                    console.warn('Calculator modal not available in this view.');
                }
            });
        }

        if (downloadBtn) {
            downloadBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                if (typeof window.downloadFilteredBookings === 'function') {
                    window.downloadFilteredBookings();
                } else {
                    console.warn('Download handler not available in this view.');
                }
            });
        }
    });
    </script>

<!-- Mobile Detail Modal -->
<div id="mobileDetailModal" class="mobile-detail-modal">
    <div class="mobile-modal-content">
        <div class="mobile-modal-header">
            <h3 class="mobile-modal-title">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 11H5a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7a2 2 0 0 0-2-2h-4m-4-11V9a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2V3a2 2 0 0 00-2-2h-2a2 2 0 0 0-2 2z"></path>
                </svg>
                Booking Details
            </h3>
            <button class="mobile-close-btn" onclick="closeMobileModal()">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
        <div class="mobile-modal-body" id="mobileModalBody">
            <!-- Content will be populated dynamically -->
        </div>
    </div>
</div>

<script>
    // Event listeners for mobile modal (functions are defined earlier in the page)
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('mobileDetailModal');
        if (modal) {
            // Close modal on outside click
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeMobileModal();
                }
            });
        }
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeMobileModal();
            }
        });
        
        // Handle window resize - close modal if switching to desktop
        window.addEventListener('resize', function() {
            if (!isMobile()) {
                closeMobileModal();
            }
        });
    });
</script>

<script>
    // SUPERADMIN-ONLY: Override pagination to use arrows instead of text
    // This runs after bookings_script.js loads and overrides the updateMainPaginationControls function
    window.addEventListener('DOMContentLoaded', function() {
        // Wait for bookings_script.js to load and define the original function
        setTimeout(function() {
            // Store original function reference
            const originalUpdateMainPaginationControls = window.updateMainPaginationControls;
            
            // Override with arrow version for superadmin
            window.updateMainPaginationControls = function() {
                const pagination = document.querySelector('.pagination-controls');
                if (!pagination) return;

                // Rebuild the pagination bar from scratch
                pagination.innerHTML = '';

                const createButton = (label, className, disabled, onClick) => {
                    const btn = document.createElement('button');
                    btn.className = className;
                    btn.textContent = label;
                    btn.disabled = disabled;
                    if (onClick) btn.onclick = onClick;
                    pagination.appendChild(btn);
                    return btn;
                };

                // Use arrows instead of text
                createButton('←', 'btn btn-outline btn-sm', window.mainCurrentPage === 1, () => {
                    if (window.mainCurrentPage > 1) window.goToMainPage(window.mainCurrentPage - 1);
                });

                if (window.mainTotalPages > 0) {
                    const addPageBtn = (page) => {
                        createButton(
                            page,
                            `btn btn-sm ${page === window.mainCurrentPage ? 'btn-primary' : 'btn-outline'}`,
                            false,
                            () => window.goToMainPage(page)
                        );
                    };

                    const addEllipsis = () => {
                        const ellipsis = document.createElement('span');
                        ellipsis.className = 'pagination-ellipsis';
                        ellipsis.textContent = '..';
                        ellipsis.style.padding = '0 8px';
                        ellipsis.style.color = '#666';
                        ellipsis.style.display = 'inline-flex';
                        ellipsis.style.alignItems = 'center';
                        pagination.appendChild(ellipsis);
                    };

                    if (window.mainTotalPages <= 3) {
                        for (let i = 1; i <= window.mainTotalPages; i++) addPageBtn(i);
                    } else {
                        addPageBtn(1);
                        if (window.mainCurrentPage > 2) addEllipsis();
                        if (window.mainCurrentPage !== 1 && window.mainCurrentPage !== window.mainTotalPages) addPageBtn(window.mainCurrentPage);
                        if (window.mainCurrentPage < window.mainTotalPages - 1) addEllipsis();
                        addPageBtn(window.mainTotalPages);
                    }
                }

                createButton('→', 'btn btn-outline btn-sm', window.mainCurrentPage === window.mainTotalPages || window.mainTotalPages === 0, () => {
                    if (window.mainCurrentPage < window.mainTotalPages) window.goToMainPage(window.mainCurrentPage + 1);
                });
            };
            
            // Call it once to apply the arrow style immediately
            if (typeof window.updateMainPaginationControls === 'function') {
                window.updateMainPaginationControls();
            }
        }, 100);
    });

// Download Filtered Bookings as CSV
window.downloadFilteredBookings = function() {
    console.log('[DOWNLOAD] Starting CSV download...');
    
    const csvData = [];
    
    // Check if filters are applied
    const hasFilters = window.activeFilters && Object.keys(window.activeFilters).length > 0;
    console.log(`[DOWNLOAD] Filters applied: ${hasFilters}`);
    console.log('[DOWNLOAD] Active filters:', window.activeFilters);
    
    // Use allGroupedRows (unfiltered by city) for downloads to get ALL bookings
    const dataSource = window.allGroupedRows || window.groupedRows;
    
    if (!dataSource) {
        console.error('[DOWNLOAD] No booking data available');
        alert('Unable to download: booking data not loaded');
        return;
    }
    
    console.log('[DOWNLOAD] Data source keys:', Object.keys(dataSource));
    let totalBookings = 0;
    let exportCount = 0;
    let skippedBookings = 0;
    
    // Get selected year filter (if any)
    const selectedYear = window.selectedYear;
    console.log('[DOWNLOAD] Selected year filter:', selectedYear);
    
    // Iterate through all years and all bookings
    for (const yearKey in dataSource) {
        // Skip years that don't match the selected year filter
        if (selectedYear && selectedYear !== 'all' && yearKey !== selectedYear) {
            console.log(`[DOWNLOAD] Skipping year ${yearKey} (doesn't match filter ${selectedYear})`);
            continue;
        }
        
        const yearBookings = dataSource[yearKey];
        
        if (!Array.isArray(yearBookings)) {
            console.warn(`[DOWNLOAD] Year ${yearKey} is not an array:`, yearBookings);
            continue;
        }
        
        console.log(`[DOWNLOAD] Processing year ${yearKey} with ${yearBookings.length} bookings`);
        
        yearBookings.forEach((booking, index) => {
            totalBookings++;
            let shouldExport = false;
            
            if (hasFilters) {
                // When filters are applied: check both month filter and other filters
                // This matches the logic in filterYearlyData function
                if (typeof window.matchesFiltersYearWise === 'function') {
                    // Check month filter separately (if present)
                    let matchesMonth = true;
                    if (window.activeFilters.month) {
                        if (typeof window.matchesMonthFilter === 'function') {
                            matchesMonth = window.matchesMonthFilter(booking, window.activeFilters.month);
                        }
                    }
                    
                    // Check other filters (excluding month)
                    const filtersWithoutMonth = { ...window.activeFilters };
                    delete filtersWithoutMonth.month;
                    const matchesOthers = Object.keys(filtersWithoutMonth).length === 0 || 
                                         window.matchesFiltersYearWise(booking, window.activeFilters);
                    
                    shouldExport = matchesMonth && matchesOthers;
                    
                    if (exportCount < 5) { // Only log first 5 for debugging
                        console.log(`[DOWNLOAD] Row ${booking.id}: matchesMonth=${matchesMonth}, matchesOthers=${matchesOthers}, shouldExport=${shouldExport}`);
                    }
                } else {
                    console.warn('[DOWNLOAD] matchesFiltersYearWise function not available');
                    shouldExport = true; // Export all if filter function unavailable
                }
            } else {
                // When no filters: export ALL bookings (entire dataset)
                shouldExport = true;
            }
            
            if (shouldExport) {
                exportCount++;
                const rowData = [];
                
                // Extract data in same order as CSV header
                rowData.push(booking.id || '');
                rowData.push(booking.booking_date || '');
                rowData.push(booking.booking_month || '');
                rowData.push(booking.builder || '');
                rowData.push(booking.project || '');
                rowData.push(booking.customer_name || '');
                rowData.push(booking.contact_number || '');
                rowData.push(booking.email_id || '');
                rowData.push(booking.project_type || '');
                rowData.push(booking.unit_no || '');
                rowData.push(booking.size || '');
                rowData.push(booking.agreement_value || '');
                rowData.push(booking.cashback || booking.commission_percent || '');
                rowData.push(booking.revenue || '');
                rowData.push(booking.cashback || '');
                rowData.push(booking.crevenue || '');
                rowData.push(booking.astatus || '');
                rowData.push(booking.recived_amt || '');
                
                csvData.push(rowData);
            } else {
                skippedBookings++;
            }
        });
    }
    
    console.log(`[DOWNLOAD] Total bookings processed: ${totalBookings}`);
    console.log(`[DOWNLOAD] Bookings to export: ${exportCount}`);
    console.log(`[DOWNLOAD] Bookings skipped: ${skippedBookings}`);
    
    console.log(`[DOWNLOAD] Rows to export: ${exportCount}`);
    
    if (csvData.length === 0) {
        console.warn('[DOWNLOAD] No rows to export');
        console.warn('[DOWNLOAD] No data to export');
        return;
    }
    
    // Generate filename with timestamp
    const now = new Date();
    const timestamp = now.getFullYear() + '-' + 
                     String(now.getMonth() + 1).padStart(2, '0') + '-' + 
                     String(now.getDate()).padStart(2, '0') + '_' +
                     String(now.getHours()).padStart(2, '0') + '-' +
                     String(now.getMinutes()).padStart(2, '0');
    const filename = hasFilters ? 
        `bookings_filtered_${timestamp}.csv` : 
        `bookings_export_${timestamp}.csv`;
    
    console.log(`[DOWNLOAD] Downloading ${csvData.length} rows as ${filename}`);
    
    downloadCsv(csvData, filename);
};

// CSV Download Utility Function
function downloadCsv(data, filename) {
    console.log(`[downloadCsv] Starting CSV generation for ${data.length} rows`);
    
    const fixedColumnNames = [
        "ID",
        "Booking Date",
        "Month",
        "Builder",
        "Project",
        "Customer Name",
        "Contact No.",
        "Email Id",
        "Type",
        "Unit No.",
        "Size",
        "Agreement Value",
        "Commission %",
        "Total Revenue",
        "CashBack %",
        "Actual Revenue",
        "Status",
        "Received Amt."
    ];

    // Escape CSV values properly
    const escapeCsvValue = (value) => {
        if (value == null) return '';
        const strValue = String(value);
        // If value contains comma, newline, or quotes, wrap in quotes and escape internal quotes
        if (strValue.includes(',') || strValue.includes('\n') || strValue.includes('"')) {
            return '"' + strValue.replace(/"/g, '""') + '"';
        }
        return strValue;
    };

    // Build CSV content using array (more efficient for large datasets)
    const csvRows = [];
    
    // Add header row
    csvRows.push(fixedColumnNames.map(escapeCsvValue).join(","));
    
    // Add data rows
    data.forEach(function(row, index) {
        csvRows.push(row.map(escapeCsvValue).join(","));
        if (index % 100 === 0 && index > 0) {
            console.log(`[downloadCsv] Processed ${index} rows...`);
        }
    });
    
    console.log(`[downloadCsv] Total CSV rows created: ${csvRows.length}`);
    
    // Join all rows with newlines
    const csvContent = csvRows.join("\n");
    
    console.log(`[downloadCsv] CSV content length: ${csvContent.length} characters`);

    // Create Blob instead of data URI (no size limit issues)
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    
    console.log(`[downloadCsv] Blob size: ${blob.size} bytes`);
    
    // Create download link using Blob URL (supports large files)
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.setAttribute("href", url);
    link.setAttribute("download", filename);
    document.body.appendChild(link);
    link.click();
    
    // Clean up
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
    
    console.log('[downloadCsv] CSV download initiated successfully');
}


</script>

<?php
$customJs = [
    "assets/js/bookings_script.js?v=20250930"
];
include "htmlclose.php";
?>