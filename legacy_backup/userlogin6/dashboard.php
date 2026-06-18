<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Ensure we have a session before handling embed/impersonation logic
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// When this dashboard is loaded inside the superadmin iframe, bootstrap a minimal
// session so downstream API calls (dashboard_data.php, etc.) recognize the user.
$isEmbedRequest    = isset($_GET['embed']) && $_GET['embed'] === '1';
$impersonatedTable = isset($_GET['impersonate']) ? trim((string)$_GET['impersonate']) : '';

if ($isEmbedRequest && $impersonatedTable !== '') {
  // Do not clobber an existing login; only seed what is missing so the
  // embedded view behaves like the impersonated user.
  if (!isset($_SESSION['loggedin'])) {
    $_SESSION['loggedin'] = true;
  }
  if (empty($_SESSION['role'])) {
    $_SESSION['role'] = 'superuseradmin';
  }
  if (empty($_SESSION['user_type'])) {
    $_SESSION['user_type'] = 'promoter';
  }

  $_SESSION['tablename'] = $impersonatedTable;
}

include 'config.php';
$pageTitle = "Dashboard";
include 'htmlopen.php';
include 'header.php';

// Database connection
require_once 'config.php';
$config = new Config();
$conn = $config->getConnection();

// Determine which user's data this dashboard should show.
$effectiveTablename = $_SESSION['tablename'] ?? null;
if (!empty($impersonatedTable) && ($isEmbedRequest || (isset($_SESSION['role']) && $_SESSION['role'] === 'superuseradmin'))) {
  $effectiveTablename = $impersonatedTable;
}

// Get current (or impersonated) user's username and meta
$stmt = $conn->prepare("SELECT username, useremail, doj, assign_user FROM accounts WHERE tablename = :tablename");
$stmt->bindParam(':tablename', $effectiveTablename);
$stmt->execute();
$userRow = $stmt->fetch(PDO::FETCH_ASSOC);
$currentUsername = $userRow['username'] ?? 'User';
$currentEmail = $userRow['useremail'] ?? 'user@company.com';
$doj = $userRow['doj'] ?? 'Unknown';

// Resolve assigned manager name (single assignment)
$managerName = 'No Manager';
$assignRaw = $userRow['assign_user'] ?? '';
if (!empty($assignRaw)) {
  $leaderId = trim((string)$assignRaw);
  $stmtMgr = $conn->prepare("SELECT username FROM accounts WHERE tablename = :tid AND is_active = 1");
  $stmtMgr->bindParam(':tid', $leaderId);
  if ($stmtMgr->execute()) {
    $mgrRow = $stmtMgr->fetch(PDO::FETCH_ASSOC);
    if ($mgrRow && !empty($mgrRow['username'])) {
      $managerName = $mgrRow['username'];
    }
  }
}
?>

<!-- Modal CSS (isolated) -->
<link rel="stylesheet" href="assets/css/user_perf_modal.css">

<!-- User Performance Modal (hidden) -->
<div id="upm-modal" class="upm-modal" aria-hidden="true">
  <div class="upm-modal__dialog" role="dialog" aria-modal="true">
    <button id="upm-close" class="upm-close" aria-label="Close">&times;</button>

    <header class="upm-header">
      <div class="upm-title-wrap">
        <h2 class="upm-title" id="upm-title">User Performance</h2>
        <p class="upm-sub" id="upm-subtitle">Daily activity, status distribution & counts</p>
      </div>

      <div class="upm-controls">
        <div class="upm-select-group">
          <select id="upm-user-select" class="upm-select upm-select-native" aria-label="Select user">
            <option value="">Loading users...</option>
          </select>
          <div class="upm-combobox" id="upm-user-combobox" tabindex="0" role="combobox" aria-haspopup="listbox" aria-expanded="false" aria-owns="upm-user-options">
            <span class="upm-combobox-value" id="upm-user-display">Select User</span>
            <span class="upm-combobox-caret"></span>
          </div>
          <div class="upm-dropdown" id="upm-user-dropdown">
            <input id="upm-user-search" class="upm-dropdown-search" type="search" placeholder="Search user..." aria-label="Search user" autocomplete="off">
            <div class="upm-option-list" id="upm-user-options" role="listbox"></div>
          </div>
        </div>

        <select id="upm-month-select" class="upm-select" aria-label="Select month">
          <option value="">Month</option>
          <option value="1">Jan</option><option value="2">Feb</option><option value="3">Mar</option>
          <option value="4">Apr</option><option value="5">May</option><option value="6">Jun</option>
          <option value="7">Jul</option><option value="8">Aug</option><option value="9">Sep</option>
          <option value="10">Oct</option><option value="11">Nov</option><option value="12">Dec</option>
          <option value="custom">Custom Range</option>
        </select>

        <input id="upm-start" class="upm-date" type="date" style="display:none" />
        <input id="upm-end"   class="upm-date" type="date" style="display:none" />

         
      </div>
    </header>

    <main class="upm-body">
      <section class="upm-cards" id="upm-cards">
        <!-- status cards injected here -->
      </section>

      <section class="upm-charts">
        <div class="upm-chart-panel">
          <canvas id="upm-daily-chart"></canvas>
        </div>

        <div class="upm-chart-panel upm-chart-panel--right">
          <canvas id="upm-status-chart"></canvas>
        </div>
      </section>

      <section class="upm-footer">
        <div class="upm-footer-left">
          <small class="upm-note">Data based on user_remarks & timestamp</small>
        </div>
        <div class="upm-footer-right">
          <button id="upm-close-2" class="upm-btn">Close</button>
        </div>
      </section>
    </main>
  </div>
</div>

<!-- Dashboard Content -->
<div class="dashboard-content">
  <div class="dashboard-row">
    <div class="dashboard-card next-game-card" id="borderColorChange">
      <div class="card-header1">

        <?php if (!$isLeadsPage && !$isEOIPage && !$isBookingPage && (empty($isEmbed) || !$isEmbed)): ?>
          <div class="welcome-text welcome-2">Hello, <?php echo htmlspecialchars($currentUsername); ?>👋</div>
        <?php endif; ?>

        <?php if (!$isLeadsPage && !$isEOIPage && !$isBookingPage && $currentRoleNormalized !== 'user'): ?>
          <!-- Searchable Names Dropdown (Mobile/Tablet) -->
          <div class="names-dropdown mobile-only-1249">
            <div class="searchable-select" id="searchableNameSelectMobile">
              <div class="searchable-select-container">
                <input type="text"
                       id="nameSearchInputMobile"
                       class="searchable-select-input"
                       placeholder="Search and select a name..."
                       readonly>
                <div class="searchable-select-arrow">
                  <i class="fas fa-chevron-down"></i>
                </div>
              </div>
              <div class="searchable-select-dropdown" id="nameDropdownListMobile">
                <div class="searchable-select-loading">
                  <i class="fas fa-spinner fa-spin"></i> Loading users...
                </div>
              </div>
            </div>
          </div>
        <?php endif; ?>

      </div>

      <div class="card-header profile-card-header">
        <a href="#" id="upm-open-btn" class="view-link upm-btn">User Performance</a>
        <div class="profile-center-wrap">
          
          <h3 class="card-title">Profile</h3>
          <!-- New User Performance button -->
          
        </div>
        <?php $roleNormalized = isset($currentRole) ? strtolower(trim($currentRole)) : 'user'; ?>
        <?php if ($roleNormalized !== 'user'): ?>
          <a class="view-link profile-top-link" onclick="openDashboard()">Overall Report</a>
        <?php else: ?>
          <a class="view-link profile-top-link" onclick="openHierarchyPopup()">View Hierarchy</a>
        <?php endif; ?>
      </div>

      <div class="match-info">
        <div class="detail">
          <div class="league-info">
            <span>Joining Date</span>
          </div>
          <div class="match-time">
            <?php 
              $formattedDoj = !empty($doj) ? date("d M Y", strtotime($doj)) : '';
              echo htmlspecialchars($formattedDoj);
            ?>
          </div>
        </div>
        <div class="match-teams">
          <div class="team team-left"> 
            <span class="team-name"><?php echo htmlspecialchars($currentUsername); ?></span>
            <img src="assets/dataimage/user.png" alt="User" class="team-logo">
          </div>
          <div class="vs-indicator">Assign</div>
          <div class="team team-right">
            <img src="assets/dataimage/ceo.png" alt="Ceo" class="team-logo">
            <span class="team-name"><?php echo htmlspecialchars($managerName); ?></span>
          </div>
        </div>
      </div>

      <div class="profile-card-footer">
        <?php $roleNormalized = isset($currentRole) ? strtolower(trim($currentRole)) : 'user'; ?>
        <?php if ($roleNormalized !== 'user'): ?>
          <a class="view-link profile-bottom-link" onclick="openDashboard()">Overall Report</a>
        <?php else: ?>
          <a class="view-link profile-bottom-link" onclick="openHierarchyPopup()">View Hierarchy</a>
        <?php endif; ?>
        <a href="#" class="view-link upm-btn mobile-upm-btn profile-bottom-link" style="margin-left: 10px;" onclick="document.getElementById('upm-open-btn').click(); return false;">User Performance</a>
      </div>
    </div>

    <div id="backToMyDashboard" class="floating-dashboard-btn hidden">
      <button class="floating-btn">
        <i class="fas fa-arrow-left"></i>
        <span>Back to My Dashboard</span>
      </button>
    </div>

    <!-- Games Statistic Card -->
    <div class="dashboard-card next-game-card">
      <div class="card-header">
        <h3 class="card-title">Leads Stats</h3>
        <a href="#" class="view-link" onclick="openPopup()">View Report</a>
      </div>
      <div class="games-stats">
        <div class="progress-bar" id="source-progress">
          <div class="progress-segment status-pending" data-count="Pending: 0" style="width: 0%"></div>
          <div class="progress-segment status-followup" data-count="Follow Up: 0" style="width: 0%"></div>
          <div class="progress-segment status-fix-site-visit" data-count="Fix Site Visit: 0" style="width: 0%"></div>
          <div class="progress-segment status-site-visited" data-count="Site Visited: 0" style="width: 0%"></div>
        </div>
        <div class="stats-grid" id="status-stats">
          <div class="stat-item" data-status="Pending">
            <div class="stat-label">Pending</div>
            <div class="stat-value" id="pending-count">0</div>
          </div>
          <div class="stat-item" data-status="Follow Up">
            <div class="stat-label">Follow Up</div>
            <div class="stat-value" id="followup-count">0</div>
          </div>
          <div class="stat-item" data-status="Fix Site Visit">
            <div class="stat-label">Fix Site Visit</div>
            <div class="stat-value" id="fix-site-visit-count">0</div>
          </div>
          <div class="stat-item" data-status="Site Visit Done">
            <div class="stat-label">Site Visit Done</div>
            <div class="stat-value" id="site-visited-count">0</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="below-content">
    <!-- Second Row - Standings -->
    <div class="dashboard-row">
      <div class="dashboard-card standings-card">
        <div class="card-header">
          <h3 class="card-title">📊 Top 10 New Leads</h3>
          <a href="https://mnts.in/incentiveapp_integration/userlogin1/userlogin6/user_lead" class="view-link">View all</a>
        </div>
        <div class="standings-table-container">
          <table class="standings-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Source</th>
                <th>Status</th>
                <th>Project Name</th>
              </tr>
            </thead>
            <tbody id="standings-body">
              <!-- Data will be populated by JavaScript -->
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div>
      <!-- Third Row - Metrics -->
      <div class="dashboard-row metrics-row">
        <div class="metric-card possession" onclick="openLeadsPopupWithFilter(null)" style="cursor: pointer;">
          <div class="metric-icon">
            <i class="fas fa-user-plus"></i>
          </div>
          <div class="metric-info">
            <div class="metric-label" data-original="Monthly LEADS">Monthly LEADS</div>
            <div class="metric-value" id="my-leads-value">0</div>
          </div>
        </div>

        <div class="metric-card overall-price" onclick="redirectToEOIsWithFilter()">
          <div class="metric-icon">
            <i class="fas fa-file-signature"></i>
          </div>
          <div class="metric-info">
            <div class="metric-label" data-original="Monthly EOIs">Monthly EOIs</div>
            <div class="metric-value" id="total-eoi-value">0</div>
          </div>
        </div>

        <div class="metric-card transfer-budget" onclick="openBookingPopup()">
          <div class="metric-icon">
            <i class="fas fa-handshake"></i>
          </div>
          <div class="metric-info">
            <div class="metric-label" data-original="Monthly Bookings">Monthly Bookings</div>
            <div class="metric-value" id="overall-bookings-value">0</div>
          </div>
        </div>

        <div class="metric-card average-score">
          <div class="metric-icon">
            <i class="fa fa-inr"></i>
          </div>
          <div class="metric-info average-score">
            <div class="metric-label" data-original="Monthly Agreement">Monthly Agreement</div>
            <div class="metric-value" id="overall-revenue-value">0</div>
          </div>
        </div>
      </div>

      <!-- Fourth Row - Action Card -->
      <div class="dashboard-row">
        <div class="action-card-container">
          <div class="action-card">
            <div class="action-content">
              <div class="action-text">
                <div class="action-label">DON'T FORGET</div>
                <div class="action-title">Setup training for next week</div>
              </div>
              <button class="action-button">Go to training center</button>
            </div>
            <div class="action-illustration">
              <div class="illustration-elements">
                <div class="element ball"></div>
                <div class="element cone"></div>
                <div class="element whistle"></div>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>
</main>

<div class="popup-overlay hidden" id="dashboardPopup">
  <div class="dashboard-popup">
    <div class="popup-header">
      <h1>Manager Dashboard</h1>
      <button class="close-btn" onclick="closeDashboard()">&times;</button>
    </div>
    <div class="popup-filters filtersss">
      <!-- Desktop View (Date filters + User filter will be added here) -->
    </div>
    <div class="dashboard-content dashboard-content2">
      <!-- Overall Stats, Charts, and User Performance sections remain here -->
    </div>
  </div>
</div>

<div id="popup" class="popup-overlay2 hidden">
  <div class="popup-container">
    <div class="popup-header">
      <h2 class="popup-title">Lead Analytics Dashboard</h2>
      <div class="theme-controls">
        <button class="close-btn" onclick="closePopup()">&times;</button>
      </div>
    </div>
    <div class="popup-content">
      <div class="stats-grid1">
        <div class="chart-card">
          <h3 class="chart-title">Lead Status Distribution</h3>
          <div class="chart-container">
            <canvas id="statusChart"></canvas>
          </div>
        </div>
        <div class="chart-card">
          <h3 class="chart-title">Lead Source Analytics</h3>
          <div class="chart-container">
            <canvas id="sourceChart"></canvas>
          </div>
        </div>
      </div>

      <div class="summary-cards">
        <div class="summary-card">
          <div class="summary-number">1,247</div>
          <div class="summary-label">Total Leads</div>
        </div>
        <div class="summary-card">
          <div class="summary-number">23%</div>
          <div class="summary-label">Conversion Rate</div>
        </div>
        <div class="summary-card">
          <div class="summary-number">156</div>
          <div class="summary-label">Today's Follow Up</div>
        </div>
        <div class="summary-card">
          <div class="summary-number">89</div>
          <div class="summary-label">Site Visits Pending</div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Hierarchy Popup Modal -->
<div id="hierarchyPopup" class="popup-overlay2 hidden">
  <div class="popup-container" style="max-width: 900px;">
    <div class="popup-header">
      <h2 class="popup-title">Organization Hierarchy</h2>
      <div class="theme-controls">
        <button class="close-btn" onclick="closeHierarchyPopup()">&times;</button>
      </div>
    </div>
    <div class="popup-content">
      <div class="hierarchy-dynamic" id="hierarchyDynamic"></div>
    </div>
  </div>
</div>

<!-- Booking Details Popup Modal -->
<div id="bookingPopup" class="popup-overlay2 hidden">
  <div class="popup-container" style="max-width: 1200px;">
    <div class="popup-header">
      <h2 class="popup-title">Monthly Bookings</h2>
      <div class="theme-controls">
        <button class="close-btn" onclick="closeBookingPopup()">&times;</button>
      </div>
    </div>
    <div class="popup-content">
      <!-- Search Bar -->
      <div class="booking-search-container" style="margin-bottom: 15px;">
        <input 
          type="text" 
          id="bookingSearchInput" 
          class="booking-search-input" 
          placeholder="Search ..."
          style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px;"
        >
      </div>
      
      <div class="standings-table-container">
        <table class="standings-table">
          <thead>
            <tr>
              <th>Unit</th>
              <th>Type</th>
              <th>Customer Name</th>
              <th>Builder</th>
              <th>Project</th>
              <th>Agreement Value</th>
              <th>Total Revenue</th>
              <th>Actual Revenue</th>
              <th>Cashback</th>
              <th>Commission</th>
              
            </tr>
          </thead>
          <tbody id="booking-table-body">
            <!-- Data will be populated by JavaScript -->
          </tbody>
        </table>
      </div>
      
      <!-- Pagination Controls -->
      <div class="booking-pagination" style="display: flex; justify-content: space-between; align-items: center; margin-top: 15px; padding: 10px 0;">
        <div class="pagination-info" id="bookingPaginationInfo">
          Showing 0 of 0 bookings
        </div>
        <div class="pagination-controls" style="display: flex; gap: 5px; align-items: center;">
          <button 
            id="bookingFirstPage" 
            class="pagination-btn" 
            onclick="goToBookingPage('first')"
          >First</button>
          <button 
            id="bookingPrevPage" 
            class="pagination-btn" 
            onclick="goToBookingPage('prev')"
          >Previous</button>
          <span id="bookingPageInfo">Page 1 of 1</span>
          <button 
            id="bookingNextPage" 
            class="pagination-btn" 
            onclick="goToBookingPage('next')"
          >Next</button>
          <button 
            id="bookingLastPage" 
            class="pagination-btn" 
            onclick="goToBookingPage('last')"
          >Last</button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  // Set the current user tablename for dashboard JS (supports impersonation)
  currentUserTableName = "<?php echo htmlspecialchars($effectiveTablename ?? ($_SESSION['tablename'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>";
  console.log('Dashboard currentUserTableName:', currentUserTableName);
  window.DASHBOARD_ASSIGNED_USERS_API = 'dashboard_data.php?total=true';
  // Also expose username/display name for self-comparison in JS
  window.currentUserName = "<?php echo htmlspecialchars($currentUsername ?? '', ENT_QUOTES, 'UTF-8'); ?>";
  window.currentUserDisplayName = window.currentUserName;
  window.currentUserEmail = "<?php echo htmlspecialchars($currentEmail ?? '', ENT_QUOTES, 'UTF-8'); ?>";

  // Also expose original session tablename if needed by user_perf_modal.js
  window.CURRENT_TABLENAME = "<?php echo isset($_SESSION['tablename']) ? addslashes($_SESSION['tablename']) : ''; ?>";
</script>

<!-- SheetJS library for Excel export -->
<script src="https://cdn.sheetjs.com/xlsx-0.20.1/package/dist/xlsx.full.min.js"></script>

<!-- User Performance Modal JS -->
<script src="assets/js/user_perf_modal.js"></script>

<?php include 'htmlclose.php'; ?>