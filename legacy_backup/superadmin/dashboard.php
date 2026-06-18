<?php include('htmlopen.php'); ?>
<link rel="stylesheet" href="./assets/css/calender.css" />
<style>
  .side-menu li.sideactive{ 
    position: relative
  }
  .side-menu li.sideactive a{
        color: white;
      }
    
  /* Prevent horizontal overflow globally */
  html, body {
    overflow-x: hidden !important;
    max-width: 100vw !important;
  }
  
  .content {
    overflow-x: hidden !important;
    max-width: 100% !important;
  }
  
  .contentinside {
    overflow-x: hidden !important;
    max-width: 100% !important;
    width: 100% !important;
  }
  
  .togglerightsidebar,.addNewUserModal,.filterModal,.calculatorModal,.downloadCsvBtn{ 
    display: none;
  }
  
  /* Revenue Stats Cards Styling */
  .revenue-stats-card {
    padding: 16px 12px;
    text-align: center;
    border-radius: 10px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    color: white;
    border: none;
  }
  
  .revenue-stats-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 15px rgba(0, 0, 0, 0.2);
  }
  
  .revenue-card-header {
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 10px;
    opacity: 0.95;
  }
  
  .revenue-card-value {
    font-size: 24px;
    font-weight: 700;
    margin: 6px 0;
    text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
  }
  
  .revenue-card-subtitle {
    font-size: 11px;
    opacity: 0.85;
    font-weight: 400;
  }
  @media screen and (max-width: 768px) {
  .content,
    body.sidebar-collapsed .content,
    body.sidebar-overlay .content {
        padding: 12px 0px 0px;
        margin-left: 0 !important;
        filter: none;
        transition: filter 0.3s;
    }
}
  
  /* Different gradients for each revenue card - direct targeting */
  .col-lg-2 .revenue-stats-card:nth-child(1) {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  }
  
  .col-lg-2 .revenue-stats-card:nth-child(2) {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
  }
  
  .col-lg-2 .revenue-stats-card:nth-child(3) {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
  }
  
  /* Ensure revenue cards fill the height properly */
  .col-lg-2, .col-xl-2 {
    display: flex;
    flex-direction: column;
    gap: 8px;
  }
  
  /* Make revenue cards equal height */
  .revenue-stats-card {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: center;
    min-height: 105px;
  }
  
  /* Fix chart overflow */
  .dashboard-card {
    box-sizing: border-box;
    overflow: hidden;
  }
  
  #barchart_material svg,
  #piechart_3d svg {
    max-width: 100% !important;
    overflow: hidden;
  }
  
  /* Responsive adjustments for revenue cards */
  @media (max-width: 768px) {
    .revenue-stats-card {
      padding: 20px 15px;
    }
    
    .revenue-card-value {
      font-size: 26px;
    }
    
    .revenue-card-header {
      font-size: 14px;
    }
    
    .revenue-card-header i {
      font-size: 20px;
    }
  }
  
  @media (max-width: 480px) {
    .revenue-stats-card {
      padding: 18px 12px;
    }
    
    .revenue-card-value {
      font-size: 22px;
    }
    
    .revenue-card-header {
      font-size: 13px;
    }
    /* ApexCharts toolbar - reverse column for better mobile UX */
    .apexcharts-toolbar {
      flex-direction: column-reverse !important;
    }
  }
  
  /* ApexCharts toolbar responsive for screens less than 500px */
  @media (max-width: 500px) {
    .apexcharts-toolbar {
      flex-direction: column-reverse !important;
    }
  }

  @media (min-width: 1025px) and (max-width: 1250px) {
    .apexcharts-toolbar {
      flex-direction: column-reverse !important;
    }
    
  }
  
  /* Container for embedded userlogin6 dashboard */
  #userlogin6-dashboard-container {
    display: none;
    width: 100%;
    height: calc(100vh - 80px);
    max-height: 900px;
    margin-top: 10px;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: none !important;
    border: none !important;
  }
  /* Make iframe fill the container completely without leaving gaps.
     Use absolute positioning inside the relatively positioned container
     so we don't change any global margins that affect the sidebar. */
  /* When the embed is shown, position it fixed so it fills the area
     to the right of the sidebar and below the header. This avoids
     any right-side gap while keeping the left sidebar visible. */
  #userlogin6-dashboard-container {
    position: fixed;
    top: 80px; /* matches earlier calc(100vh - 80px) usage */
    left: var(--sidebar-width, 280px);
    right: 0;
    bottom: 0;
    width: auto;
    height: auto;
    max-height: none;
    padding: 0;
    margin: 0;
    background: transparent;
    border-radius: 0; /* flush to the right edge to eliminate visible gaps */
    z-index: 199;
  }
  
  /* For tablets and mobile: iframe should cover full screen when shown */
  @media (max-width: 1024px) {
    #userlogin6-dashboard-container {
      left: 0 !important;
      right: 0 !important;
      width: 100% !important;
      z-index: 199 !important;
    }
  }

  #userlogin6-dashboard-iframe {
    position: absolute;
    inset: 0; /* top:0; right:0; bottom:0; left:0 */
    width: 100%;
    height: 100%;
    border: none;
    margin: 0;
    padding: 0;
    display: block;
    background: rgba(255,255,255,0.0);
    overflow-y: auto;
    overflow-x: hidden;
  }
  
  /* Add padding inside iframe content to prevent left cutoff on tablets */
  @media (min-width: 721px) and (max-width: 1024px) {
    #userlogin6-dashboard-iframe {
      padding-left: 15px !important;
      padding-right: 15px !important;
    }
  }
  
  /* Dull overlay for top navbar when dashboard is active */
  #userlogin6-dashboard-container.active-navbar-dull ~ .header,
  #userlogin6-dashboard-container.active-navbar-dull ~ .topbar,
  #userlogin6-dashboard-container.active-navbar-dull ~ .navbar {
    background: rgba(255,255,255,0.7) !important;
    transition: background 0.3s;
    border-radius: inherit; /* follow container corners */
  }

  /* Fix dashboard grid layout for embedded userlogin6 dashboard */
  #userlogin6-dashboard-iframe .dashboard-row {
    display: grid !important;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)) !important;
    gap: 20px !important;
    width: 100% !important;
  }
  
  #userlogin6-dashboard-iframe .dashboard-card {
    width: 100% !important;
    max-width: none !important;
  }

  /* Fix leads stats out of viewport in specific ranges, shrinking profile card */
  @media (min-width: 798px) and (max-width: 910px), (min-width: 1028px) and (max-width: 1130px) {
    #userlogin6-dashboard-iframe .dashboard-row:first-of-type {
      grid-template-columns: minmax(0, 0.8fr) minmax(0, 1.2fr) !important; 
    }
  }

  /* Tablet sidebar overlay behavior - sidebar should be on top of dashboard */
  @media (max-width: 1024px) {
    /* Ensure sidebar has higher z-index than iframe on tablets */
    .sidebar {
      z-index: 250 !important;
    }
  }

  /* Responsive styles for charts */
  @media (max-width: 992px) {
    /* Stack columns on tablets and below */
    .col-lg-2, .col-lg-3, .col-lg-7, .col-lg-9,
    .col-xl-2, .col-xl-3, .col-xl-7, .col-xl-9 {
      width: 100% !important;
      max-width: 100% !important;
      flex: 0 0 100% !important;
      margin-bottom: 15px;
    }
    
    .col-lg-2, .col-xl-2 {
      gap: 10px;
    }
    
    .revenue-stats-card {
      margin-bottom: 0 !important;
      min-height: 90px;
    }
    
    #line_top_x, #piechart_3d, #barchart_material {
      min-height: 320px !important;
      height: 320px !important;
    }
    
    .dashboard-card {
      margin-bottom: 15px;
    }
  }
  
  @media (max-width: 768px) {
    html {
      overflow-x: hidden !important;
    }
    
    body {
      overflow-x: hidden !important;
      max-width: 100vw !important;
      width: 100% !important;
    }
    
    .content {
      overflow-x: hidden !important;
      max-width: 100vw !important;
      width: 100% !important;
    }
    
    .contentinside {
      overflow-x: hidden !important;
      max-width: 100vw !important;
      width: 100% !important;
      padding-left: 0 !important;
      padding-right: 0 !important;
    }
    
    .container-fluid {
      padding-left: 10px !important;
      padding-right: 10px !important;
      max-width: 100vw !important;
      width: 100% !important;
      overflow-x: hidden !important;
      box-sizing: border-box !important;
    }
    
    .row {
      margin-left: 0 !important;
      margin-right: 0 !important;
      max-width: 100% !important;
      width: 100% !important;
    }
    
    .row > [class*="col-"] {
      padding-left: 10px !important;
      padding-right: 10px !important;
      max-width: 100% !important;
      width: 100% !important;
      box-sizing: border-box !important;
      margin-bottom: 15px !important;
    }
    
    .chart-col-lg, .col-lg-2, .col-lg-3, .col-lg-7, .col-lg-9,
    .col-xl-2, .col-xl-3, .col-xl-7, .col-xl-9 {
      width: 100% !important;
      max-width: 100% !important;
      flex: 0 0 100% !important;
      margin-bottom: 15px !important;
      display: block !important;
    }
    
    .col-lg-2, .col-xl-2 {
      gap: 12px;
    }
    
    .dashboard-card {
      padding: 10px;
      width: 100% !important;
      max-width: 100% !important;
      box-sizing: border-box !important;
      overflow-x: hidden !important;
      margin-bottom: 10px;
    }
    
    .revenue-stats-card {
      padding: 16px 12px;
      margin-bottom: 10px !important;
      width: 100% !important;
      max-width: 100% !important;
      box-sizing: border-box !important;
      min-height: auto;
      height: auto;
    }
    
    .revenue-card-value {
      font-size: 26px;
    }
    
    .revenue-card-header {
      font-size: 14px;
    }
    
    #line_top_x {
      width: 100% !important;
      max-width: 100% !important;
      height: 300px !important;
      min-height: 300px !important;
      box-sizing: border-box !important;
    }
    
    .chartcmmnstyle {
      width: 100% !important;
      max-width: 100% !important;
      height: 300px !important;
      min-height: 300px !important;
      box-sizing: border-box !important;
    }
    
    #piechart_3d {
      width: 100% !important;
      max-width: 100% !important;
      height: 300px !important;
      box-sizing: border-box !important;
    }
    
    #barchart_material {
      width: 100% !important;
      max-width: 100% !important;
      height: 300px !important;
      box-sizing: border-box !important;
    }
  }

  /* Responsive styles for small mobile (480px and below) */
  @media (max-width: 480px) {
    .container, .container-fluid {
      margin-left: 0px !important;
      margin-right: 0px !important;
      padding-left: 0px !important;
      padding-right: 0px !important;
      width: 100% !important;
      max-width: 100vw !important;
    }
    
    .row {
      margin-left: 0 !important;
      margin-right: 0 !important;
      width: 100% !important;
    }
    
    .row > [class*="col-"] {
      padding-left: 8px !important;
      padding-right: 8px !important;
      width: 100% !important;
      max-width: 100% !important;
      flex: 0 0 100% !important;
      margin-bottom: 12px !important;
      box-sizing: border-box !important;
    }
    
    .dashboard-card {
      padding: 8px;
      margin-bottom: 10px;
      width: 100% !important;
      box-sizing: border-box !important;
    }
    
    #line_top_x {
      width: 100% !important;
      height: 280px !important;
      min-height: 280px !important;
    }
    
    .chartcmmnstyle {
      width: 100% !important;
      height: 280px !important;
      min-height: 280px !important;
    }
    
    #piechart_3d {
      width: 100% !important;
      height: 280px !important;
      min-height: 280px !important;
    }
    
    #barchart_material {
      width: 100% !important;
      height: 280px !important;
      min-height: 280px !important;
    }
    
    /* Revenue cards on small mobile */
    .revenue-stats-card {
      padding: 14px 10px;
      margin-bottom: 8px !important;
    }
    
    .revenue-card-value {
      font-size: 24px;
    }
    
    .revenue-card-header {
      font-size: 13px;
    }
  }

  /* Responsive styles for very small screens (375px and below) */
  @media (max-width: 375px) {
    .container, .container-fluid {
      padding-left: 6px !important;
      padding-right: 6px !important;
      width: 100% !important;
      max-width: 100% !important;
      overflow-x: hidden !important;
    }
    
    .row {
      margin-left: 0 !important;
      margin-right: 0 !important;
      width: 100% !important;
    }
    
    .row > [class*="col-"] {
      width: 100% !important;
      max-width: 100% !important;
      flex: 0 0 100% !important;
      padding-left: 6px !important;
      padding-right: 6px !important;
      margin-bottom: 10px;
    }
    
    .dashboard-card {
      padding: 8px;
      border-radius: 10px;
      width: 100% !important;
      box-sizing: border-box !important;
    }
    
    #line_top_x {
      width: 100% !important;
      height: 260px !important;
      min-height: 260px !important;
      max-width: 100% !important;
    }
    
    .chartcmmnstyle {
      width: 100% !important;
      height: 260px !important;
      min-height: 260px !important;
      max-width: 100% !important;
    }
    
    #piechart_3d {
      width: 100% !important;
      height: 260px !important;
      min-height: 260px !important;
      max-width: 100% !important;
    }
    
    #barchart_material {
      width: 100% !important;
      height: 260px !important;
      min-height: 260px !important;
      max-width: 100% !important;
    }
    
    /* Revenue cards on very small screens */
    .revenue-stats-card {
      padding: 10px 8px;
      margin-bottom: 8px !important;
    }
    
    .revenue-card-value {
      font-size: 20px;
    }
    
    .revenue-card-header {
      font-size: 12px;
      gap: 6px;
    }
    
    .revenue-card-header i {
      font-size: 16px;
    }
    
    .revenue-card-subtitle {
      font-size: 10px;
    }
  }

  /* Analytics Tabs Styling */
  .analytics-tabs {
    display: flex;
    border-bottom: 2px solid #e0e0e0;
    margin-bottom: 20px;
    background: white;
    border-radius: 8px 8px 0 0;
    overflow: hidden;
  }
  
  .analytics-tab {
    flex: 1;
    padding: 15px 20px;
    text-align: center;
    cursor: pointer;
    font-weight: 600;
    font-size: 14px;
    color: #666;
    background: #f8f9fa;
    border: none;
    transition: all 0.3s ease;
    position: relative;
  }
  
  .analytics-tab:hover {
    background: #e9ecef;
    color: #333;
  }
  
  .analytics-tab.active {
    background: white;
    color: #667eea;
    border-bottom: 3px solid #667eea;
  }
  
  .analytics-tab.active::after {
    content: '';
    position: absolute;
    bottom: -2px;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  }
  
  .analytics-content {
    display: none;
    opacity: 0;
    transition: opacity 0.2s ease-in-out;
  }
  
  .analytics-content.active {
    display: block;
    opacity: 1;
  }
  
  .analytics-charts-row {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
  }
  
  .analytics-chart-container {
    flex: 1;
    min-width: 300px;
    background: white;
    border-radius: 10px;
    padding: 15px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
  }
  
  @media (max-width: 768px) {
    .analytics-tab {
      padding: 12px 10px;
      font-size: 12px;
    }
    
    .analytics-charts-row {
      flex-direction: column;
      gap: 15px;
    }
    
    .analytics-chart-container {
      min-width: 100%;
      width: 100%;
      margin-bottom: 15px;
    }
    
    #booking_status_chart,
    #booking_trend_chart,
    #lead_source_chart,
    #lead_flow_chart {
      width: 100% !important;
      max-width: 100% !important;
      overflow: hidden !important;
    }
  }
  
  @media (max-width: 480px) {
    .analytics-tab {
      padding: 10px 8px;
      font-size: 12px;
    }
  }

  /* Specific styles for 320px screens */
  @media (max-width: 320px) {
    .container, .container-fluid {
      padding-left: 5px !important;
      padding-right: 5px !important;
      overflow-x: hidden !important;
    }
    
    .row {
      margin-left: 0 !important;
      margin-right: 0 !important;
    }
    
    .row > [class*="col-"] {
      width: 100% !important;
      padding-left: 5px !important;
      padding-right: 5px !important;
      margin-bottom: 8px;
    }
    
    .dashboard-card {
      padding: 8px;
    }
    
    #line_top_x {
      width: 100% !important;
      height: 240px !important;
      min-height: 240px !important;
    }
    
    .chartcmmnstyle {
      width: 100% !important;
      height: 240px !important;
      min-height: 240px !important;
    }
    
    #piechart_3d {
      width: 100% !important;
      height: 240px !important;
      min-height: 240px !important;
    }
    
    #barchart_material {
      width: 100% !important;
      height: 240px !important;
      min-height: 240px !important;
    }
    
    /* Revenue cards on 320px screens */
    .revenue-stats-card {
      padding: 10px 8px;
      margin-bottom: 8px !important;
    }
    
    .revenue-card-value {
      font-size: 18px;
    }
    
    .revenue-card-header {
      font-size: 11px;
      gap: 4px;
    }
    
    .revenue-card-header i {
      font-size: 14px;
    }
    
    .revenue-card-subtitle {
      font-size: 9px;
    }
  }

  /* ==================== NOTIFICATION & PROFILE DROPDOWN FIX ==================== */
  /* CRITICAL: Fix for notification and profile dropdowns not appearing on dashboard */
  
  /* Base styles - ensure dropdowns are properly configured */
  #notif-content_box,
  #profile-content_box,
  .notif-content,
  .profile-content {
    z-index: 99999 !important;
    position: fixed !important;
  }
  
  /* FORCE visibility when JavaScript sets display: block */
  #notif-content_box[style*="display: block"],
  #profile-content_box[style*="display: block"],
  .notif-content[style*="display: block"],
  .profile-content[style*="display: block"] {
    display: block !important;
    visibility: visible !important;
    opacity: 1 !important;
    pointer-events: auto !important;
  }

</style>
<?php include('header.php'); ?>
<!-- Sidebar Toggle Button removed for troubleshooting script.js error -->
<!-- Main Content -->
<div class="content">
  <div class="contentinside">

    <!-- Native superadmin dashboard content -->
    <div id="superadmin-dashboard-main">
      <!-- Year Dropdown -->
      <div class="container">
        <div class="row justify-content-center mb-3">
            <div class="col-lg-3">
                <div class="custom-year-dropdown" id="yearDropdownWrapper">
                    <div class="custom-dropdown-toggle" id="yearDropdownToggle">
                        <span class="custom-dropdown-selected" id="yearDropdownSelected">Select Year</span>
                        <div class="custom-dropdown-right">
                            <span class="custom-dropdown-label">Year</span>
                            <svg class="custom-dropdown-chevron" viewBox="0 0 24 24" width="16" height="16">
                                <path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                    </div>
                    <div class="custom-dropdown-menu" id="yearDropdownMenu">
                        <!-- Options populated by JS -->
                    </div>
                    <select class="form-select" id="year_select" style="display:none !important;">
                        <!-- Hidden select for chart.js compatibility -->
                    </select>
                </div>
            </div>
        </div>
      </div>
      <!-- Year Dropdown End -->
      
      <!-- Main Dashboard Grid Layout -->
      <div class="container-fluid px-3">
        <!-- Row 1: Revenue Stats (Left) + Profit/Loss Chart (Center) + Status Distribution (Right) -->
        <div class="row mb-3">
          <!-- Revenue Stats Column (Left) -->
          <div class="col-xl-2 col-lg-2 col-md-12 col-sm-12">
            <!-- Total Revenue Card -->
            <div class="revenue-stats-card mb-2">
              <div class="revenue-card-header">
                <span>Total Revenue</span>
              </div>
              <div class="revenue-card-value" id="total-revenue-value">₹0</div>
              <div class="revenue-card-subtitle">For Selected Year</div>
            </div>
            
            <!-- Actual Revenue Card -->
            <div class="revenue-stats-card mb-2">
              <div class="revenue-card-header">
                <span>Actual Revenue</span>
              </div>
              <div class="revenue-card-value" id="actual-revenue-value">₹0</div>
              <div class="revenue-card-subtitle">For Selected Year</div>
            </div>
            
            <!-- Received Amount Card -->
            <div class="revenue-stats-card">
              <div class="revenue-card-header">
                <span>Received Amount</span>
              </div>
              <div class="revenue-card-value" id="received-amount-value">₹0</div>
              <div class="revenue-card-subtitle">For Selected Year</div>
            </div>
          </div>
          
          <!-- Profit & Loss Summary Chart (Center) -->
          <div class="col-xl-7 col-lg-7 col-md-12 col-sm-12">
            <div class="dashboard-card" style="height: 100%;">
              <div id="line_top_x" style="width: 100%; height: 100%; min-height: 340px;"></div>
            </div>
          </div>
          
          <!-- Status Distribution Pie Chart (Right) -->
          <div class="col-xl-3 col-lg-3 col-md-12 col-sm-12">
            <div class="dashboard-card" style="height: 100%; overflow: hidden;">
              <div class="chartcmmnstyle" id="piechart_3d" style="height: 100%; min-height: 340px; width: 100%; overflow: hidden;"></div>
            </div>
          </div>
        </div>
        
        <!-- Row 2: Company Performance (Left) + Calendar (Right) -->
        <div class="row mb-3" style="align-items: stretch;">
          <!-- Company Performance Bar Chart (Left - 75%) -->
          <div class="col-xl-9 col-lg-9 col-md-12 col-sm-12">
            <div class="dashboard-card" style="max-height: 400px; overflow: hidden scroll;scrollbar-width: thin;">
              <div class="chartcmmnstyle" id="barchart_material" style="height: 100%; width: 100%; max-width: 100%; overflow: hidden;"></div>
            </div>
          </div>
          
          <!-- Calendar (Right - 25%) -->
          <div class="col-xl-3 col-lg-3 col-md-12 col-sm-12">
            <div class="dashboard-card" style="height: 400px; overflow: hidden; padding: 10px;">
              <div class="wrapper" style="height: 100%; overflow-y: auto; scrollbar-width: thin;">
                <div class="container-calendar" style="height: auto; max-height: none; padding: 5px 10px;">
                  <div id="right">
                    <h3 id="monthAndYear"></h3>
                    <div class="button-container-calendar">
                      <button id="previous" onclick="previous()">
                        ‹
                      </button>
                      <button id="next" onclick="next()">
                        ›
                      </button>
                    </div>
                    <table class="table-calendar" id="calendar" data-lang="en">
                      <thead id="thead-month"></thead>
                      <tbody id="calendar-body"></tbody>
                    </table>
                    <div class="footer-container-calendar">
                      <label for="month">Jump To: </label>
                      <select id="month" onchange="jump()">
                        <option value=0>Jan</option>
                        <option value=1>Feb</option>
                        <option value=2>Mar</option>
                        <option value=3>Apr</option>
                        <option value=4>May</option>
                        <option value=5>Jun</option>
                        <option value=6>Jul</option>
                        <option value=7>Aug</option>
                        <option value=8>Sep</option>
                        <option value=9>Oct</option>
                        <option value=10>Nov</option>
                        <option value=11>Dec</option>
                      </select>
                      <select id="year" onchange="jump()"></select>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Row 3: Analytics Section with Tabs -->
        <div class="row mt-3">
          <div class="col-lg-12">
            <div class="dashboard-card">
              <!-- Analytics Tabs -->
              <div class="analytics-tabs">
                <button class="analytics-tab active" onclick="switchAnalyticsTab('bookings')">
                  📊 Booking Analytics
                </button>
                <button class="analytics-tab" onclick="switchAnalyticsTab('leads')">
                  📈 Leads Analytics
                </button>
              </div>
              
              <!-- Booking Analytics Content -->
              <div id="bookings-analytics" class="analytics-content active">
                <div class="analytics-charts-row">
                  <div class="analytics-chart-container">
                    <div id="booking_status_chart" style="width: 100%; height: 350px;"></div>
                  </div>
                  <div class="analytics-chart-container">
                    <div id="booking_trend_chart" style="width: 100%; height: 350px;"></div>
                  </div>
                </div>
              </div>
              
              <!-- Leads Analytics Content -->
              <div id="leads-analytics" class="analytics-content">
                <div class="analytics-charts-row">
                  <div class="analytics-chart-container">
                    <div id="lead_source_chart" style="width: 100%; height: 350px;"></div>
                  </div>
                  <div class="analytics-chart-container">
                    <div id="lead_flow_chart" style="width: 100%; height: 350px;"></div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
        <!-- <div class="row  mt-3">
           <div class="col-lg-12 indexbooktable">
            <h4>Booking Status</h4>
            <a href="/user/superadmin/database.php">
              <div class="panel-body">
                <table class="table table-bordered table-hover text-center">
                  <thead> 
                  <tr class="filters">
                        <th>ID</th>
                        <th>Booking Date</th>
                        <th>Month</th>
                        <th>Builder</th>
                        <th>Project</th>
                        <th>Customer Name</th>
                        <th>Contact No.</th>
                        <th>Email Id</th>
                        <th>Type</th>
                        <th>Unit No.</th>
                        <th>Size</th>
                        <th>Agreement Value</th>
                        <th>Commission %</th>
                        <th>Total Revenue</th>
                        <th>CashBack %</th>
                        <th>Actual Revenue</th>
                        <th>Status</th>
                        <th>Received Amt.</th>
                        <th>Sales Person</th>
                    </tr>
                    </thead>
                  <tbody id="pagedata">
                    
                  </tbody>
                  <tfoot>
                    <th>ID</th>
                    <th>Booking Date</th>
                    <th>Month</th>
                    <th>Builder</th>
                    <th>Project</th>
                    <th>Customer Name.</th>
                    <th>Contact No.</th>
                    <th>Email Id</th>
                    <th>Type</th>
                    <th>Unit No.</th>
                    <th>Size</th>
                    <th>Agreement Value</th>
                    <th>Commission %</th>
                    <th>Total Revenue</th>
                    <th>CashBack %</th>
                    <th>Actual Revenue</th>
                    <th>Status</th>
                    <th>Received Amt.</th>
                    <th>Sales Person</th> 
                  </tfoot>
                </table>
              </div>
            </a>
           </div>
       </div> -->
      </div>
    </div> <!-- /#superadmin-dashboard-main -->

    <!-- Embedded userlogin6 dashboard for Koushik promoter (shown via header toggle) -->
    <div id="userlogin6-dashboard-container">
      <iframe
        id="userlogin6-dashboard-iframe"
        title="User Dashboard - Koushik (userlogin6)"
        loading="lazy"
        src=""
        onload="handleIframeLoad(this)">
      </iframe>
    </div>

  </div>
</div>


<!-- ApexCharts for line chart (better vanilla JS support) -->
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<!-- Google Charts for other charts -->
<!-- <script type="text/javascript" src="../assets/js/chartloader.js"></script> -->
<script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
<script src="./assets/js/chart.js"></script>
<script src="./assets/js/calender.js"></script>
<!-- <script src="main.js"></script> -->
<script src="calc.js"></script>
<script>
  // Compute and set the embed container position so it hugs the top nav
  // but does not cover it. This removes the top gap while preserving
  // the visible header and sidebar. Called when showing the embed and on resize.
  function adjustUserlogin6Container() {
    var container = document.getElementById('userlogin6-dashboard-container');
    if (!container) return;

    // header height (try common selectors) - fallback to 80px
    var header = document.querySelector('header, .header, .topbar, .navbar, .main-header');
    var headerHeight = header ? Math.ceil(header.getBoundingClientRect().height) : 80;

    // sidebar bounding rect if present - prefer measuring actual element
    var sidebar = document.querySelector('.sidebar');
    var leftPx = 0;
    if (sidebar) {
      try {
        var rect = sidebar.getBoundingClientRect();
        // rect.right gives the pixel position of the sidebar's right edge
        leftPx = Math.ceil(rect.right);
      } catch (e) {
        leftPx = 0;
      }
    } else {
      // fallback to CSS variable if sidebar element not found
      var sv = getComputedStyle(document.documentElement).getPropertyValue('--sidebar-width') || '280px';
      leftPx = sv.trim();
    }

    // Mobile / narrow screens: if viewport is small or sidebar is hidden/collapsed,
    // allow the embed to occupy full width (left = 0) so content isn't clipped.
    var useFullWidth = false;
    var vw = window.innerWidth || document.documentElement.clientWidth;
    // If sidebar element exists but is effectively hidden or collapsed
    if (sidebar) {
      var cs = getComputedStyle(sidebar);
      if (cs.display === 'none' || sidebar.offsetWidth < 48 || sidebar.offsetHeight === 0) {
        useFullWidth = true;
      }
    }
    // Also force full width for small viewports (mobile)
    if (vw <= 720) useFullWidth = true;

    container.style.top = headerHeight + 'px';
    // if using full width (mobile) set left to 0, otherwise use measured sidebar right edge
    if (useFullWidth) {
      container.style.left = '0';
    } else {
      container.style.left = (typeof leftPx === 'number') ? leftPx + 'px' : leftPx;
    }
    container.style.right = '0';
    container.style.bottom = '0';
    container.style.width = 'auto';
    container.style.height = 'auto';
    container.style.padding = '0';
  }

  // Called from header toggle (only present on dashboard page)
  function handleUserlogin6Toggle(checkbox) {
    var main = document.getElementById('superadmin-dashboard-main');
    var container = document.getElementById('userlogin6-dashboard-container');
    var iframe = document.getElementById('userlogin6-dashboard-iframe');
    if (!main || !container || !iframe) {
      return;
    }

    // Save toggle state to localStorage
    if (checkbox) {
      localStorage.setItem('userlogin6DashboardToggle', checkbox.checked ? 'true' : 'false');
    }

    if (checkbox && checkbox.checked) {
      // Lazy-load iframe only on first toggle
      if (!iframe.dataset.loaded) {
        // Get dark mode state and include in URL
        var isDarkMode = document.body.classList.contains('dark-mode');
        var darkModeParam = isDarkMode ? '&darkMode=1' : '';
        iframe.src = '/incentiveapp_integration/userlogin1/userlogin6/dashboard.php?impersonate=rahul00761&embed=1' + darkModeParam;
        iframe.dataset.loaded = '1';
        // Reset ready flag since iframe is loading
        iframe.dataset.ready = '0';
      } else {
        // If iframe is already loaded, update dark mode via postMessage
        // Use a small delay to ensure iframe is ready
        setTimeout(function() {
          updateIframeDarkMode();
        }, 50);
      }
      // adjust position based on current header/sidebar sizes
      adjustUserlogin6Container();
      main.style.display = 'none';
      container.style.display = 'block';
    } else {
      container.style.display = 'none';
      main.style.display = 'block';
      window.dispatchEvent(new Event('resize'));

  // ✅ FORCE CHART REDRAW AFTER DASHBOARD BECOMES VISIBLE
      setTimeout(function () {

    if (typeof redrawCharts === 'function') {
      redrawCharts();   // Google Charts + Revenue + Apex
    }

    if (typeof redrawActiveAnalyticsCharts === 'function') {
      redrawActiveAnalyticsCharts(); // Analytics charts
    }

    }, 120);
      }
  }

  // Handle iframe load event
  function handleIframeLoad(iframe) {
    // Mark iframe as ready
    iframe.dataset.ready = '1';
    
    // Inject comprehensive responsive CSS for dashboard grid layout inside iframe
    try {
      var iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
      var style = iframeDoc.createElement('style');
      style.textContent = `
        /* ============================================
           RESPONSIVE DASHBOARD GRID LAYOUT
           ============================================ */
        /* Make embedded user dashboard header scroll with content (no fixed/sticky pinning). */
        .header,
        .navbar,
        .main-header,
        .topbar,
        .leads-header {
          position: static !important;
          top: auto !important;
          left: auto !important;
          right: auto !important;
          z-index: auto !important;
        }
        
        .main-content,
        main.main-content {
          padding-top: 0 !important;
          margin-top: 0 !important;
        }
        
        /* Add top padding to dashboard content to prevent text cutoff */
        .dashboard-content {
          padding-left: 10px;
          padding-right: 10px;
          
          margin-top: 10px !important;
        }
          @media (max-width: 768px) {
            .dashboard-card.next-game-card {
               margin-top: 0px !important; 
            }

          @media (max-width: 480px) {
            .dashboard-card.next-game-card {
               margin-top: 20px !important; 
            }
        }
        
        /* Add left/right padding for tablets to prevent cutoff */
        @media (min-width: 768px) and (max-width: 1024px) {
          .dashboard-content {
            padding-left: 15px !important;
            padding-right: 15px !important;
          }
          
          main {
            padding-left: 15px !important;
            padding-right: 15px !important;
          }
          
          body {
            overflow-x: hidden !important;
          }
        }
        
        /* Desktop: 1400px and above - Full 2-column layout */
        @media (min-width: 1400px) {
          /* Row 1: Profile and Leads Stats side by side */
          .dashboard-content > .dashboard-row:first-child {
            display: grid !important;
            grid-template-columns: 1fr 1fr !important;
            gap: 24px !important;
          }
          
          /* Row 2: Table on left (60%), Metrics on right (40%) */
          .below-content {
            display: grid !important;
            grid-template-columns: 1.5fr 1fr !important;
            gap: 24px !important;
            align-items: start !important;
          }
          
          .below-content > .dashboard-row:first-child {
            grid-column: 1 / 2 !important;
            grid-row: 1 / 3 !important;
          }
          
          .below-content > div {
            grid-column: 2 / 3 !important;
            display: flex !important;
            flex-direction: column !important;
            gap: 24px !important;
          }
          
          .metrics-row {
            display: grid !important;
            grid-template-columns: repeat(2, 1fr) !important;
            gap: 20px !important;
            margin: 0 !important;
          }
        }
        
        /* Large Desktop: 1200px - 1399px */
        @media (min-width: 1200px) and (max-width: 1399px) {
          .dashboard-content > .dashboard-row:first-child {
            display: grid !important;
            grid-template-columns: 1fr 1fr !important;
            gap: 20px !important;
          }
          
          .below-content {
            display: grid !important;
            grid-template-columns: 1fr 1fr !important;
            gap: 20px !important;
            align-items: start !important;
          }
          
          .below-content > .dashboard-row:first-child {
            grid-column: 1 / 2 !important;
            grid-row: 1 / 3 !important;
          }
          
          .below-content > div {
            grid-column: 2 / 3 !important;
            display: flex !important;
            flex-direction: column !important;
            gap: 20px !important;
          }
          
          .metrics-row {
            display: grid !important;
            grid-template-columns: repeat(2, 1fr) !important;
            gap: 16px !important;
            margin: 0 !important;
          }
        }
        
        /* Tablet Landscape: 992px - 1199px */
        @media (min-width: 992px) and (max-width: 1199px) {
          .dashboard-content > .dashboard-row:first-child {
            display: grid !important;
            grid-template-columns: 1fr 1fr !important;
            gap: 16px !important;
          }
          
          /* Stack table and metrics vertically */
          .below-content {
            display: flex !important;
            flex-direction: column !important;
            gap: 16px !important;
          }
          
          .below-content > .dashboard-row:first-child {
            width: 100% !important;
          }
          
          .below-content > div {
            width: 100% !important;
            display: flex !important;
            flex-direction: column !important;
            gap: 16px !important;
          }
          
          .metrics-row {
            display: grid !important;
            grid-template-columns: repeat(2, 1fr) !important;
            gap: 16px !important;
            margin: 0 !important;
          }
        }
        
        /* Tablet Portrait: 768px - 991px */
        @media (min-width: 768px) and (max-width: 991px) {
          .dashboard-content > .dashboard-row:first-child {
            display: grid !important;
            gap: 16px !important;
          }
          
          .below-content {
            display: flex !important;
            flex-direction: column !important;
            gap: 16px !important;
          }
          
          .below-content > .dashboard-row:first-child {
            width: 100% !important;
          }
          
          .below-content > div {
            width: 100% !important;
            display: flex !important;
            flex-direction: column !important;
            gap: 16px !important;
          }
          
          .metrics-row {
            display: grid !important;
            grid-template-columns: repeat(2, 1fr) !important;
            gap: 14px !important;
            margin: 0 !important;
          }
        }
        
        /* Mobile Landscape: 576px - 767px */
        @media (min-width: 576px) and (max-width: 767px) {
          .dashboard-content > .dashboard-row:first-child {
            display: flex !important;
            flex-direction: column !important;
            gap: 14px !important;
          }
          
          .below-content {
            display: flex !important;
            flex-direction: column !important;
            gap: 14px !important;
          }
          
          .below-content > .dashboard-row:first-child {
            width: 100% !important;
          }
          
          .below-content > div {
            width: 100% !important;
            display: flex !important;
            flex-direction: column !important;
            gap: 14px !important;
          }
          
          .metrics-row {
            display: grid !important;
            grid-template-columns: repeat(2, 1fr) !important;
            gap: 12px !important;
            margin: 0 !important;
          }
        }
        
        /* Mobile Portrait: Below 576px */
        @media (max-width: 575px) {
          .dashboard-content > .dashboard-row:first-child {
            display: flex !important;
            flex-direction: column !important;
            gap: 12px !important;
          }
          
          .below-content {
            display: flex !important;
            flex-direction: column !important;
            gap: 12px !important;
          }
          
          .below-content > .dashboard-row:first-child {
            width: 100% !important;
          }
          
          .below-content > div {
            width: 100% !important;
            display: flex !important;
            flex-direction: column !important;
            gap: 12px !important;
          }
          
          .metrics-row {
            display: flex !important;
            flex-direction: column !important;
            gap: 12px !important;
            margin: 0 !important;
          }
        }
        
        /* Common styles for all breakpoints */
        .metric-card {
          width: 100% !important;
          max-width: none !important;
          margin: 0 !important;
          box-sizing: border-box !important;
        }
        
        .dashboard-card {
          width: 100% !important;
          max-width: none !important;
          box-sizing: border-box !important;
        }
        
        .dashboard-row {
          width: 100% !important;
          box-sizing: border-box !important;
        }
        
        /* Ensure proper overflow handling */
        .standings-table-container {
          overflow-x: auto !important;
          -webkit-overflow-scrolling: touch !important;
        }
        
        /* Responsive table */
        @media (max-width: 767px) {
          .standings-table {
            font-size: 12px !important;
          }
          
          .standings-table th,
          .standings-table td {
            padding: 8px 6px !important;
          }
        }
        
        /* Dark mode background styling for embedded userlogin6 dashboard */
        [data-theme=dark] body {
          background: linear-gradient(135deg, #2c2c2e00 0%, #1c1c1e00 50%, #2c2c2ea1 100%) !important;
        }

        @media (max-width: 479px) {
          .dashboard-content {
            gap: 0rem !important;
         }
          
         .below-content {
            margin-top: 0px !important;
          }
         }

        @media (min-width: 480px) and (max-width: 797px) {
          #borderColorChange.dashboard-card.next-game-card {
            margin-bottom: 20px !important;
          }

          .dashboard-content {
            gap: .5rem !important;
         }
          
         .below-content {
            margin-top: 0px !important;
          }
         }

        @media (min-width: 415px) and (max-width:500px) {
          .dashboard-card.next-game-card .perf-badge{
            padding: 5px 10px !important;
          }
        }


      `;
      iframeDoc.head.appendChild(style);
    } catch (e) {
      console.log('Could not inject CSS into iframe:', e);
    }
    
    // Send dark mode state immediately after iframe loads
    setTimeout(function() {
      updateIframeDarkMode();
    }, 100);
  }

  // Function to update iframe dark mode (shared with header.php)
  function updateIframeDarkMode() {
    var iframe = document.getElementById('userlogin6-dashboard-iframe');
    if (!iframe) return;
    
    // Check if iframe is ready (loaded and has contentWindow)
    var isReady = iframe.dataset.ready === '1' && iframe.contentWindow;
    
    if (isReady) {
      try {
        var isDarkMode = document.body.classList.contains('dark-mode');
        // Send message to iframe
        iframe.contentWindow.postMessage({
          type: 'darkMode',
          enabled: isDarkMode
        }, '*');
      } catch (e) {
        console.log('Could not update iframe dark mode:', e);
        // Retry after a short delay
        setTimeout(function() {
          updateIframeDarkMode();
        }, 200);
      }
    } else {
      // Iframe not ready yet, retry after a short delay
      setTimeout(function() {
        updateIframeDarkMode();
      }, 100);
    }
  }

  // Restore dashboard toggle state on page load
  (function() {
    var toggleCheckbox = document.getElementById('userlogin6-dashboard-toggle');
    if (toggleCheckbox) {
      var savedState = localStorage.getItem('userlogin6DashboardToggle');
      if (savedState === 'true') {
        toggleCheckbox.checked = true;
        // Trigger the toggle handler to show the iframe
        handleUserlogin6Toggle(toggleCheckbox);
      }
    }
    // Update iframe dark mode after a delay to ensure it's loaded
    setTimeout(function() {
      if (toggleCheckbox && toggleCheckbox.checked) {
        updateIframeDarkMode();
      }
    }, 1000);
  })();

  // Recalculate container bounds when window resizes or device orientation changes
  window.addEventListener('resize', function () {
    var container = document.getElementById('userlogin6-dashboard-container');
    if (container && container.style.display === 'block') {
      adjustUserlogin6Container();
    }
  });

  // Watch for sidebar size/class changes so we can recompute automatically.
  (function watchSidebarChanges() {
    var sidebar = document.querySelector('.sidebar');
    if (!sidebar) return;

    // ResizeObserver to detect width changes
    if (window.ResizeObserver) {
      try {
        var ro = new ResizeObserver(function () {
          var container = document.getElementById('userlogin6-dashboard-container');
          if (container && container.style.display === 'block') adjustUserlogin6Container();
        });
        ro.observe(sidebar);
      } catch (e) {
        // ignore
      }
    }

    // MutationObserver to watch for class changes on body (sidebar toggles often toggle body class)
    var moTarget = document.body;
    if (window.MutationObserver) {
      var mo = new MutationObserver(function (mutations) {
        mutations.forEach(function (m) {
          if (m.attributeName === 'class' || m.attributeName === 'style') {
            var container = document.getElementById('userlogin6-dashboard-container');
            if (container && container.style.display === 'block') adjustUserlogin6Container();
          }
        });
      });
      mo.observe(moTarget, { attributes: true, attributeFilter: ['class', 'style'] });
    }
  })();

  // On initial load, also ensure variables are correct (no visual change until toggled)
  document.addEventListener('DOMContentLoaded', function () {
    adjustUserlogin6Container();
    
    // Analytics charts will be initialized automatically after main charts load
    // No need to call initializeAnalyticsCharts() here to avoid duplicate initialization
  });
  
  // Store current active analytics tab
  var currentAnalyticsTab = 'bookings';
  // Expose to global scope for chart.js
  window.currentAnalyticsTab = currentAnalyticsTab;
  
  // Function to switch between analytics tabs
  function switchAnalyticsTab(tabName) {
    // Hide all content sections
    var contents = document.querySelectorAll('.analytics-content');
    contents.forEach(function(content) {
      content.classList.remove('active');
    });
    
    // Remove active class from all tabs
    var tabs = document.querySelectorAll('.analytics-tab');
    tabs.forEach(function(tab) {
      tab.classList.remove('active');
    });
    
    // Show selected content
    document.getElementById(tabName + '-analytics').classList.add('active');
    
    // Set active tab
    event.target.classList.add('active');
    
    // Store the active tab
    currentAnalyticsTab = tabName;
    window.currentAnalyticsTab = tabName;
    
    // Redraw charts for the active tab to ensure proper rendering after tab switch
    setTimeout(function() {
      if (typeof redrawActiveAnalyticsCharts === 'function') {
        redrawActiveAnalyticsCharts();
      }
    }, 50);
    
    console.log('Switched to ' + tabName + ' analytics tab');
  }
</script>

<?php include('htmlclose.php'); ?>

<script src="./assets/js/script.js"></script>