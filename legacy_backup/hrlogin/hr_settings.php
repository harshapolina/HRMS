<?php
session_start();
$skip_superadmin_css = true;
require_once __DIR__ . '/htmlopen.php';
// Redirect to login if not logged in or not an admin
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'hradminuser') {
    header('Location: /');
    exit;
}
require_once __DIR__ . '/includes/db_mysqli.php';
try {
    $con = hr_mysqli_connect();
} catch (Throwable $e) {
    exit('Failed to connect to MySQL: ' . $e->getMessage());
}
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}
try {
    $check = $con->query("SHOW COLUMNS FROM leave_types LIKE 'is_paid'")->num_rows;
    if (!$check) {
        $con->query("ALTER TABLE leave_types ADD COLUMN is_paid TINYINT(1) DEFAULT 1");
    }
} catch (Exception $e) {}
$message = '';
// Handle Leave Type Operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_leave_type') {
        $name = $_POST['leave_name'];
        $limit = (int)$_POST['annual_limit'];
        $is_paid = isset($_POST['is_paid']) ? (int)$_POST['is_paid'] : 1;
        $stmt = $con->prepare('INSERT INTO leave_types (leave_name, annual_limit, is_paid) VALUES (?, ?, ?)');
        $stmt->bind_param('sii', $name, $limit, $is_paid);
        $stmt->execute();
        $stmt->close();
        $message = '<div class="alert alert-success">Leave type added successfully!</div>';
    } elseif ($_POST['action'] === 'delete_leave_type') {
        $id = (int)$_POST['id'];
        $con->query("DELETE FROM leave_types WHERE id = $id");
        $message = '<div class="alert alert-success">Leave type deleted successfully!</div>';
    } elseif ($_POST['action'] === 'update_attendance' || $_POST['action'] === 'update_settings') {
        foreach ($_POST as $key => $value) {
            if ($key === 'action') continue;
            $stmt = $con->prepare('INSERT INTO hr_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?');
            $stmt->bind_param('sss', $key, $value, $value);
            $stmt->execute();
            $stmt->close();
        }
        $message = '<div class="alert alert-success">Settings updated successfully!</div>';
    } elseif ($_POST['action'] === 'add_deduction') {
        $name = $_POST['deduction_name'];
        $type = $_POST['type'];
        $value = (float)$_POST['value'];
        $cid = $_SESSION['id'] ?? 0;
        $stmt = $con->prepare('INSERT INTO deductions (deduction_name, type, value, company_id) VALUES (?, ?, ?, ?)');
        $stmt->bind_param('ssdi', $name, $type, $value, $cid);
        $stmt->execute();
        $stmt->close();
        $message = '<div class="alert alert-success">Deduction added successfully!</div>';
    } elseif ($_POST['action'] === 'delete_deduction') {
        $id = (int)$_POST['id'];
        $con->query("DELETE FROM deductions WHERE id = $id");
        $message = '<div class="alert alert-success">Deduction removed successfully!</div>';
    } elseif ($_POST['action'] === 'toggle_deduction') {
        $id = (int)$_POST['id'];
        $status = (int)$_POST['status'];
        $con->query("UPDATE deductions SET status = $status WHERE id = $id");
        $message = '<div class="alert alert-success">Deduction status updated!</div>';
    }
}
// Fetch Deductions
$compID = $_SESSION['id'] ?? 0;
$deductions = [];
$res_ded = $con->query("SELECT * FROM deductions WHERE company_id = $compID ORDER BY created_at DESC");
if ($res_ded) {
    while($row = $res_ded->fetch_assoc()) {
        $deductions[] = $row;
    }
}
// Fetch Current Settings
$settings = [];
$res = $con->query("SELECT setting_key, setting_value FROM hr_settings");
while($row = $res->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
// Fetch Leave Types
$leave_types = [];
$res_leaves = $con->query("SELECT * FROM leave_types ORDER BY id ASC");
while($row = $res_leaves->fetch_assoc()) {
    $leave_types[] = $row;
}
// Defaults
$office_start = $settings['office_start_time'] ?? '09:00:00';
$office_end = $settings['office_end_time'] ?? '18:00:00';
$grace_period = $settings['grace_period_minutes'] ?? '15';
$break_time = $settings['break_time_minutes'] ?? '0';
$sunday_is_paid_day = $settings['sunday_is_paid_day'] ?? '1';
$saturday_rule = $settings['saturday_rule'] ?? 'always_paid';
?>
<link rel="stylesheet" href="./assets/css/style_dashboard.css?v=<?php echo time(); ?>" />
<link rel="stylesheet" href="./assets/css/Users.css?v=<?php echo time(); ?>" />
<link rel="stylesheet" href="./assets/css/unified_table_styles.css?v=<?php echo time(); ?>" />
<style>
  :root {
      --primary-teal: #2a8c90;
      --primary-teal-hover: #227477;
      --primary-teal-rgb: 42, 140, 144;
      --accent-teal: #2dd4bf;
      --accent-teal-rgb: 45, 212, 191;
      --glass-bg: rgba(255, 255, 255, 0.75);
      --glass-border: rgba(255, 255, 255, 0.4);
      --shadow-premium: 0 10px 30px -5px rgba(15, 23, 42, 0.04), 0 20px 40px -10px rgba(15, 23, 42, 0.03);
      --shadow-premium-hover: 0 20px 40px -5px rgba(15, 23, 42, 0.08), 0 30px 60px -10px rgba(15, 23, 42, 0.06);
      --transition-smooth: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
      --input-bg: rgba(255, 255, 255, 0.65);
      --text-main: #1e293b;
      --text-muted: #64748b;
      --card-inner-bg: rgba(42, 140, 144, 0.02);
      --input-border: rgba(42, 140, 144, 0.35);
  }

  body.dark-mode {
      --glass-bg: rgba(15, 23, 42, 0.7);
      --glass-border: rgba(255, 255, 255, 0.06);
      --input-bg: rgba(15, 23, 42, 0.55);
      --text-main: #f8fafc;
      --text-muted: #94a3b8;
      --primary-teal: #2a8c90;
      --primary-teal-hover: #227477;
      --primary-teal-rgb: 42, 140, 144;
      --accent-teal: #38bdf8;
      --accent-teal-rgb: 56, 189, 248;
      --card-inner-bg: rgba(42, 140, 144, 0.03);
      --input-border: rgba(255, 255, 255, 0.18);
  }

  body, html {
      transition: var(--transition-smooth);
  }

  .incentivemain, .content { background: transparent !important; border: none !important; box-shadow: none !important; }

  /* Animated floating backgrounds - only in light mode for cleaner look */
  body:not(.dark-mode)::before, body:not(.dark-mode)::after {
      content: ''; position: fixed; width: 200vw; height: 200vh; border-radius: 50%; z-index: -1; opacity: .3;
      animation: 15s ease-in-out infinite alternate float;
  }
  body:not(.dark-mode)::before { background: radial-gradient(circle, #d2b4ff 0, transparent 70%); top: -10vh; right: -50vw; }
  body:not(.dark-mode)::after { background: radial-gradient(circle, #f9eb9c 0, transparent 70%); bottom: -100vh; left: -50vw; animation-delay: 2.5s; }
  @keyframes float { 0% { transform: translateY(0) scale(1); } 100% { transform: translateY(-20px) scale(1.05); } }

  /* Premium Glassmorphic Card container */
  .hr-settings-page .card { 
      border-radius: 20px !important; 
      background: var(--glass-bg) !important; 
      backdrop-filter: blur(20px) !important;
      -webkit-backdrop-filter: blur(20px) !important;
      border: 1px solid var(--glass-border) !important;
      box-shadow: var(--shadow-premium) !important;
      color: var(--text-main) !important;
      transition: var(--transition-smooth) !important;
      padding: 15px !important;
      margin-top: 10px;
  }
  .hr-settings-page .card:hover {
      box-shadow: var(--shadow-premium-hover) !important;
  }

  .hr-settings-page .card-body {
      padding: 1rem !important;
  }

  /* Form Control & Input styling */
  .hr-settings-page .form-control, 
  .hr-settings-page .form-select {
      background-color: var(--input-bg) !important;
      color: var(--text-main) !important;
      border: 1.5px solid var(--input-border) !important;
      border-radius: 10px !important;
      padding: 10px 14px !important;
      font-size: 14px !important;
      font-weight: 500;
      transition: var(--transition-smooth) !important;
      box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.01) !important;
  }
  .hr-settings-page .form-control:focus, 
  .hr-settings-page .form-select:focus {
      background-color: var(--glass-bg) !important;
      border-color: var(--primary-teal) !important;
      box-shadow: 0 0 0 4px rgba(var(--primary-teal-rgb), 0.15) !important;
      transform: translateY(-1px);
  }
  
  .hr-settings-page .input-group-text {
      border-radius: 0 10px 10px 0 !important;
      background: var(--primary-teal) !important;
      color: white !important;
      border: none !important;
      font-weight: 600;
      padding: 0 18px !important;
      font-size: 14px !important;
      transition: var(--transition-smooth);
  }
  .hr-settings-page .input-group .form-control {
      border-radius: 10px 0 0 10px !important;
  }

  /* Form Label styling */
  .hr-settings-page .form-label {
      font-weight: 600 !important;
      color: var(--text-main) !important;
      font-size: 13px !important;
      margin-bottom: 6px !important;
      letter-spacing: 0.3px;
      display: flex;
      align-items: center;
      gap: 6px;
  }

  /* Modern Tab Navigation Pills */
  .hr-settings-page .nav-tabs { 
      border: none !important; 
      margin-bottom: 0; 
      flex-wrap: nowrap !important; 
      overflow-x: auto; 
      overflow-y: hidden; 
      gap: 6px;
      padding: 5px !important;
      background: rgba(var(--primary-teal-rgb), 0.05);
      border-radius: 14px;
      margin-bottom: 24px !important;
      scrollbar-width: none !important; /* Hide scrollbar for clean tab design */
  }
  .hr-settings-page .nav-tabs::-webkit-scrollbar {
      display: none !important; /* Hide scrollbar for Chrome/Safari/Edge */
  }
  
  .hr-settings-page .nav-link { 
      border: none !important; 
      color: var(--text-muted) !important; 
      font-weight: 600; 
      padding: 9px 18px !important;
      border-radius: 10px !important;
      font-size: 13.5px !important;
      transition: var(--transition-smooth) !important;
      white-space: nowrap;
      background: transparent !important;
      display: flex;
      align-items: center;
      gap: 6px;
  }
  .hr-settings-page .nav-link:hover {
      color: var(--primary-teal) !important;
      background: rgba(var(--primary-teal-rgb), 0.08) !important;
      transform: translateY(-1.5px);
  }
  .hr-settings-page .nav-link.active { 
      background: var(--primary-teal) !important; 
      color: white !important; 
      box-shadow: 0 6px 16px -6px rgba(var(--primary-teal-rgb), 0.4) !important;
  }

  /* Premium Buttons */
  .hr-settings-page .btn {
      transition: var(--transition-smooth) !important;
      font-weight: 600 !important;
      border-radius: 10px !important;
      padding: 9px 18px !important;
      font-size: 13.5px !important;
  }
  .hr-settings-page .btn-dark {
      background: var(--primary-teal) !important;
      border: none !important;
      color: #fff !important;
      box-shadow: 0 4px 12px rgba(var(--primary-teal-rgb), 0.15) !important;
  }
  .hr-settings-page .btn-dark:hover {
      background: var(--primary-teal-hover) !important;
      transform: translateY(-1.5px);
      box-shadow: 0 8px 18px rgba(var(--primary-teal-rgb), 0.25) !important;
  }
  .hr-settings-page .btn-outline-secondary {
      border: 1.5px solid var(--glass-border) !important;
      background: transparent !important;
      color: var(--text-main) !important;
  }
  .hr-settings-page .btn-outline-secondary:hover {
      background: rgba(var(--primary-teal-rgb), 0.08) !important;
      border-color: var(--primary-teal) !important;
      color: var(--primary-teal) !important;
      transform: translateY(-1px);
  }
  .hr-settings-page .btn-outline-danger {
      border: 1.5px solid rgba(239, 68, 68, 0.2) !important;
      color: #ef4444 !important;
      background: transparent !important;
  }
  .hr-settings-page .btn-outline-danger:hover {
      background: #ef4444 !important;
      color: white !important;
      border-color: #ef4444 !important;
      box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2) !important;
      transform: translateY(-1px);
  }
  
  /* Save/Primary action buttons styling */
  .hr-settings-page .text-end .btn, 
  .hr-settings-page .col-md-2.d-grid .btn {
      background: var(--primary-teal) !important;
      color: white !important;
      border: none !important;
      box-shadow: 0 6px 16px -6px rgba(var(--primary-teal-rgb), 0.35) !important;
      padding: 11px 30px !important;
      font-size: 13.5px !important;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      border-radius: 10px !important;
  }
  .hr-settings-page .text-end .btn:hover,
  .hr-settings-page .col-md-2.d-grid .btn:hover {
      background: var(--primary-teal-hover) !important;
      transform: translateY(-2px);
      box-shadow: 0 10px 20px -6px rgba(var(--primary-teal-rgb), 0.45) !important;
  }

  /* Table design */
  .hr-settings-page .table { 
      background: transparent !important; 
      color: var(--text-main) !important; 
      border-collapse: separate !important;
      border-spacing: 0 6px !important;
  }
  .hr-settings-page .table thead th {
      border: none !important;
      color: var(--text-muted);
      font-size: 11px !important;
      text-transform: uppercase;
      letter-spacing: 1.3px;
      padding: 10px 16px !important;
      font-weight: 700;
  }
  .hr-settings-page .table tr {
      background: transparent !important;
      transition: var(--transition-smooth);
  }
  .hr-settings-page .table td { 
      padding: 12px 16px !important; 
      vertical-align: middle; 
      background: var(--card-inner-bg) !important;
      border-top: 1px solid var(--glass-border) !important; 
      border-bottom: 1px solid var(--glass-border) !important;
      color: var(--text-main) !important; 
      font-size: 13.5px !important;
      transition: var(--transition-smooth);
  }
  .hr-settings-page .table td:first-child {
      border-left: 1px solid var(--glass-border) !important;
      border-radius: 10px 0 0 10px !important;
  }
  .hr-settings-page .table td:last-child {
      border-right: 1px solid var(--glass-border) !important;
      border-radius: 0 10px 10px 0 !important;
  }
  .hr-settings-page .table tr:hover td {
      background: rgba(var(--primary-teal-rgb), 0.05) !important;
      border-color: var(--primary-teal) !important;
  }

  /* Modern badging */
  .hr-settings-page .badge {
      font-weight: 700 !important;
      border-radius: 8px !important;
      padding: 6px 12px !important;
      letter-spacing: 0.3px;
      text-transform: uppercase;
      font-size: 11px !important;
  }
  .hr-settings-page .bg-success {
      background-color: rgba(16, 185, 129, 0.12) !important;
      color: #10b981 !important;
      border: 1.5px solid rgba(16, 185, 129, 0.18) !important;
  }
  .hr-settings-page .bg-danger {
      background-color: rgba(239, 68, 68, 0.12) !important;
      color: #ef4444 !important;
      border: 1.5px solid rgba(239, 68, 68, 0.18) !important;
  }
  .hr-settings-page .bg-teal-soft {
      background-color: rgba(var(--primary-teal-rgb), 0.08) !important;
      color: var(--primary-teal) !important;
      border: 1.5px solid rgba(var(--primary-teal-rgb), 0.18) !important;
  }

  .hr-settings-page .settings-badge {
      background: rgba(var(--primary-teal-rgb), 0.06) !important;
      color: var(--primary-teal) !important;
      padding: 8px 14px !important;
      border-radius: 10px !important;
      font-weight: 700 !important;
      font-size: 12.5px !important;
      border: 1.5px solid rgba(var(--primary-teal-rgb), 0.1) !important;
      display: inline-block;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.01) !important;
  }

  /* Settings Form Cards */
  .hr-settings-page .settings-form-card {
      background: var(--card-inner-bg) !important;
      border: 1.5px solid var(--glass-border) !important;
      border-radius: 16px !important;
      color: var(--text-main) !important;
      padding: 20px !important;
      transition: var(--transition-smooth);
      box-shadow: inset 0 2px 8px rgba(0, 0, 0, 0.01) !important;
  }
  .hr-settings-page .settings-form-card:hover {
      border-color: rgba(var(--primary-teal-rgb), 0.15) !important;
      background: rgba(var(--primary-teal-rgb), 0.03) !important;
  }

  /* Title Styles */
  .hr-settings-page h5, 
  .hr-settings-page h6 {
      font-weight: 800 !important;
      letter-spacing: -0.3px !important;
      display: flex;
      align-items: center;
      gap: 8px;
  }
  .hr-settings-page h5 {
      color: var(--primary-teal) !important;
      font-size: 1.15rem !important;
      margin-bottom: 18px !important;
  }
  .hr-settings-page h6 {
      color: var(--text-main) !important;
      font-size: 0.95rem !important;
  }

  /* Mobile scroll arrows and navigation wrapper */
  .hr-settings-page .tabs-scroll-arrow-right {
      position: absolute;
      right: 4px;
      top: 50%;
      transform: translateY(-50%);
      background: var(--glass-bg) !important;
      backdrop-filter: blur(10px) !important;
      -webkit-backdrop-filter: blur(10px) !important;
      border: 1px solid var(--glass-border) !important;
      border-radius: 50% !important;
      width: 38px !important;
      height: 38px !important;
      display: none !important;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08) !important;
      z-index: 10;
      color: var(--primary-teal) !important;
      padding: 0 !important;
      transition: var(--transition-smooth);
  }
  .hr-settings-page .tabs-scroll-arrow-right:hover {
      background: var(--primary-teal) !important;
      color: white !important;
      transform: translateY(-50%) scale(1.08) !important;
      box-shadow: 0 6px 16px rgba(var(--primary-teal-rgb), 0.25) !important;
  }

  /* Mobile settings lists */
  .hr-settings-page .settings-mobile-card {
      background: var(--glass-bg) !important;
      border: 1px solid var(--glass-border) !important;
      border-radius: 16px;
      padding: 16px;
      margin-bottom: 10px;
      box-shadow: var(--shadow-premium) !important;
      transition: var(--transition-smooth);
      display: flex;
      flex-direction: column;
      gap: 10px;
  }
  .hr-settings-page .settings-mobile-card:hover {
      transform: translateY(-1.5px);
      box-shadow: var(--shadow-premium-hover) !important;
      border-color: rgba(var(--primary-teal-rgb), 0.15) !important;
  }
  .hr-settings-page .settings-mobile-card-top {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      border-bottom: 1px solid var(--glass-border);
      padding-bottom: 10px;
  }
  .hr-settings-page .settings-mobile-card-title {
      font-weight: 800;
      font-size: 1.05rem;
      color: var(--text-main);
  }
  .hr-settings-page .settings-mobile-card-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px;
  }
  .hr-settings-page .settings-mobile-card-item {
      display: flex;
      flex-direction: column;
      gap: 2px;
  }
  .hr-settings-page .settings-mobile-card-item.full {
      grid-column: span 2;
  }
  .hr-settings-page .settings-mobile-label {
      font-size: 0.68rem;
      font-weight: 800;
      color: var(--text-muted);
      text-transform: uppercase;
      letter-spacing: 0.5px;
  }
  .hr-settings-page .settings-mobile-value {
      font-weight: 600;
      font-size: 13px;
      color: var(--text-main);
  }
  .hr-settings-page .settings-mobile-empty {
      border-radius: 16px;
      border: 2px dashed var(--glass-border);
      background: rgba(var(--primary-teal-rgb), 0.01);
      color: var(--text-muted);
      font-weight: 600;
      padding: 24px;
      text-align: center;
      font-size: 13.5px;
  }

  /* Password Toggle Button */
  .hr-settings-page #togglePassword {
      border-radius: 0 10px 10px 0 !important;
      border: 1.5px solid var(--glass-border) !important;
      border-left: none !important;
      color: var(--text-muted) !important;
      background: var(--input-bg) !important;
      transition: var(--transition-smooth);
      padding: 10px 14px !important;
  }
  .hr-settings-page #togglePassword:hover {
      color: var(--primary-teal) !important;
      background: rgba(var(--primary-teal-rgb), 0.08) !important;
  }

  /* Alert styles */
  .hr-settings-page .alert-success {
      background-color: rgba(16, 185, 129, 0.1) !important;
      border: 1.5px solid rgba(16, 185, 129, 0.15) !important;
      color: #10b981 !important;
      border-radius: 12px !important;
      font-weight: 600 !important;
      padding: 14px !important;
      margin-bottom: 20px !important;
      box-shadow: 0 4px 12px rgba(16, 185, 129, 0.05) !important;
      display: flex;
      align-items: center;
      gap: 10px;
      font-size: 13.5px;
  }
  .hr-settings-page .alert-success::before {
      content: "\F26E";
      font-family: "bootstrap-icons";
      font-size: 1.15rem;
  }

  /* Smooth Tab Pane transitions */
  .hr-settings-page .tab-pane {
      animation: fadeInTab 0.45s ease-out;
  }
  @keyframes fadeInTab {
      from { opacity: 0; transform: translateY(8px); }
      to { opacity: 1; transform: translateY(0); }
  }

  /* Section Card inside Tabs */
  .hr-settings-page .settings-section {
      background: var(--glass-bg);
      border: 1px solid var(--glass-border);
      border-radius: 16px;
      padding: 20px;
      margin-bottom: 20px;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02);
  }

  @media (min-width: 769px) {
      .hr-settings-page .nav-tabs .nav-item {
          flex: 1 !important;
      }
      .hr-settings-page .nav-tabs .nav-link {
          justify-content: center !important;
      }
  }

  @media (max-width: 768px) {
      .hr-settings-page.content { padding: 12px !important; }
      .hr-settings-page .nav-tabs { padding: 6px !important; border-radius: 14px; }
      .hr-settings-page .nav-link { padding: 10px 16px !important; font-size: 0.82rem; border-radius: 12px !important; }
      .hr-settings-page .tabs-scroll-arrow-right { display: flex !important; }
      .hr-settings-page .nav-tabs { padding-right: 52px !important; }
      .hr-settings-page .settings-form-card { padding: 20px !important; }
      .hr-settings-page .settings-section { padding: 16px; border-radius: 20px; }
      body.dark-mode .tabs-navigation-wrapper::after {
          background: linear-gradient(to right, transparent, rgba(28, 28, 30, 0.95) 80%);
      }
  }
</style>
<?php include('header.php'); ?>
<div class="content hr-settings-page">
    <div class="container-fluid">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <?php echo $message; ?>
                
                <!-- Modern Page Header -->
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
                    <div>
                        <h4 class="mb-1 fw-bold" style="color: var(--primary-teal); letter-spacing: -0.5px;">HR Control Panel</h4>
                        <p class="text-muted mb-0 small"><i class="bi bi-info-circle me-1"></i> Configure attendance rules, leave policies, location settings, SMTP, and company calendar.</p>
                    </div>
                    <span class="badge bg-teal-soft" style="font-size: 0.75rem;"><i class="bi bi-shield-lock-fill me-1"></i> Admin Privileges</span>
                </div>
                <hr class="my-3 opacity-10">

                <!-- Tabs Navigation -->
                <div class="tabs-navigation-wrapper" style="position: relative; display: flex; align-items: center; width: 100%; margin-bottom: 28px !important;">
                    <ul class="nav nav-tabs flex-nowrap" id="settingsTabs" role="tablist" style="width: 100%; margin-bottom: 0 !important;">
                        <li class="nav-item">
                            <button class="nav-link active" id="attendance-tab" data-bs-toggle="tab" data-bs-target="#attendance" type="button" role="tab">
                                <i class="bi bi-clock-history"></i> Attendance Rules
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" id="leaves-tab" data-bs-toggle="tab" data-bs-target="#leaves" type="button" role="tab">
                                <i class="bi bi-calendar2-check-fill"></i> Leave Policies
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" id="location-tab" data-bs-toggle="tab" data-bs-target="#location" type="button" role="tab">
                                <i class="bi bi-geo-alt-fill"></i> Location Settings
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" id="email-tab" data-bs-toggle="tab" data-bs-target="#email" type="button" role="tab">
                                <i class="bi bi-envelope-at-fill"></i> Email Settings
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" id="payroll-tab" data-bs-toggle="tab" data-bs-target="#payroll" type="button" role="tab">
                                <i class="bi bi-cash-coin"></i> Payroll Rules
                            </button>
                        </li>
                    </ul>
                    <button type="button" class="tabs-scroll-arrow-right" id="tabsScrollRight" aria-label="Scroll tabs right"><i class="bi bi-chevron-right" style="font-size: 14px; font-weight: bold; -webkit-text-stroke: 0.5px;"></i></button>
                </div>
                
                <div class="tab-content" id="settingsTabsContent">
                    <!-- Attendance Rules Tab -->
                    <div class="tab-pane fade show active" id="attendance" role="tabpanel">
                        <form method="POST" action="hr_settings.php">
                            <input type="hidden" name="action" value="update_attendance">
                            <div class="settings-section">
                                <h5 class="mb-4" style="color: var(--primary-teal); font-weight: 700;"><i class="bi bi-clock"></i> Office Timing Settings</h5>
                                <div class="row g-4 mb-4">
                                    <div class="col-md-6">
                                        <label class="form-label"><i class="bi bi-play-circle-fill" style="color: var(--primary-teal);"></i> Office Start Time</label>
                                        <input type="time" name="office_start_time" class="form-control" value="<?php echo $office_start; ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label"><i class="bi bi-stop-circle-fill" style="color: var(--primary-teal);"></i> Office End Time</label>
                                        <input type="time" name="office_end_time" class="form-control" value="<?php echo $office_end; ?>" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="settings-section">
                                <h5 class="mb-4" style="color: var(--primary-teal); font-weight: 700;"><i class="bi bi-cone-striped"></i> Delay &amp; Break Rules</h5>
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <label class="form-label"><i class="bi bi-hourglass-split" style="color: var(--primary-teal);"></i> Grace Period</label>
                                        <div class="input-group">
                                            <input type="number" name="grace_period_minutes" class="form-control" value="<?php echo $grace_period; ?>" required min="0" max="60">
                                            <span class="input-group-text">mins</span>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label"><i class="bi bi-cup-hot" style="color: var(--primary-teal);"></i> Break Duration</label>
                                        <div class="input-group">
                                            <input type="number" name="break_time_minutes" class="form-control" value="<?php echo $break_time; ?>" required min="0" max="120">
                                            <span class="input-group-text">mins</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="text-end mt-4">
                                <button type="submit" class="btn px-5 py-3">
                                    Save Rules
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Leave Policies Tab -->
                    <div class="tab-pane fade" id="leaves" role="tabpanel">
                        <div class="settings-section">
                            <h5 style="color: var(--primary-teal); font-weight: 700;" class="mb-4"><i class="bi bi-journal-bookmark-fill"></i> Manage Leave Types</h5>
                            
                            <div class="table-responsive mb-4 d-none d-md-block">
                                <table class="table unified-table align-middle">
                                    <thead>
                                        <tr>
                                            <th>Leave Name</th>
                                            <th>Type</th>
                                            <th>Annual Limit (Days)</th>
                                            <th class="text-center">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($leave_types as $type): ?>
                                        <tr>
                                            <td class="fw-bold"><?php echo htmlspecialchars($type['leave_name']); ?></td>
                                            <td>
                                                <span class="badge <?php echo ($type['is_paid'] ?? 1) ? 'bg-success' : 'bg-danger'; ?>">
                                                    <?php echo ($type['is_paid'] ?? 1) ? 'Paid' : 'Unpaid (LWP)'; ?>
                                                </span>
                                            </td>
                                            <td><span class="settings-badge"><?php echo $type['annual_limit']; ?> Days</span></td>
                                            <td class="text-center">
                                                <form method="POST" action="hr_settings.php" style="display:inline;" onsubmit="return confirm('Note: Deleting a leave type may affect existing leave balances. Continue?')">
                                                    <input type="hidden" name="action" value="delete_leave_type">
                                                    <input type="hidden" name="id" value="<?php echo $type['id']; ?>">
                                                    <button type="submit" class="btn btn-link text-danger p-0" style="padding:0 !important;"><i class="bi bi-trash-fill" style="font-size: 1.15rem;"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($leave_types)): ?>
                                        <tr><td colspan="4" class="text-center py-4 text-muted small">No leave types defined yet.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="settings-mobile-list d-md-none mb-4">
                                <?php if (empty($leave_types)): ?>
                                    <div class="settings-mobile-empty">No leave types defined yet.</div>
                                <?php else: ?>
                                    <?php foreach ($leave_types as $type): ?>
                                    <div class="settings-mobile-card">
                                        <div class="settings-mobile-card-top">
                                            <div class="settings-mobile-card-title"><?php echo htmlspecialchars($type['leave_name']); ?></div>
                                            <div class="settings-mobile-actions">
                                                <span class="badge <?php echo ($type['is_paid'] ?? 1) ? 'bg-success' : 'bg-danger'; ?>">
                                                    <?php echo ($type['is_paid'] ?? 1) ? 'Paid' : 'LWP'; ?>
                                                </span>
                                                <form method="POST" action="hr_settings.php" style="display:inline;" onsubmit="return confirm('Note: Deleting a leave type may affect existing leave balances. Continue?')">
                                                    <input type="hidden" name="action" value="delete_leave_type">
                                                    <input type="hidden" name="id" value="<?php echo $type['id']; ?>">
                                                    <button type="submit" class="btn btn-link text-danger p-0" style="padding:0 !important; margin-left:10px;"><i class="bi bi-trash-fill"></i></button>
                                                </form>
                                            </div>
                                        </div>
                                        <div class="settings-mobile-card-grid">
                                            <div class="settings-mobile-card-item">
                                                <span class="settings-mobile-label">Annual Limit</span>
                                                <span class="settings-mobile-value"><span class="settings-badge" style="padding: 4px 10px !important; font-size: 0.8rem;"><?php echo (int)$type['annual_limit']; ?> Days</span></span>
                                            </div>
                                            <div class="settings-mobile-card-item">
                                                <span class="settings-mobile-label">Type</span>
                                                <span class="settings-mobile-value"><?php echo ($type['is_paid'] ?? 1) ? 'Paid Leave' : 'Unpaid Leave'; ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="settings-form-card">
                            <h6 class="mb-4" style="font-weight: 700;"><i class="bi bi-plus-circle-fill text-primary-teal"></i> Add New Leave Type</h6>
                            <form method="POST" action="hr_settings.php" class="row g-3 align-items-center">
                                <input type="hidden" name="action" value="add_leave_type">
                                <div class="col-md-4">
                                    <input type="text" name="leave_name" class="form-control" placeholder="e.g. Vacation Leave" required>
                                </div>
                                <div class="col-md-3">
                                    <input type="number" name="annual_limit" class="form-control" placeholder="Days per Year" required>
                                </div>
                                <div class="col-md-3">
                                    <select name="is_paid" class="form-select">
                                        <option value="1">Paid Leave</option>
                                        <option value="0">Unpaid Leave (LWP)</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-dark w-100" style="padding: 10px 18px !important;">Create</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Location Settings Tab -->
                    <div class="tab-pane fade" id="location" role="tabpanel">
                        <form method="POST" action="hr_settings.php">
                            <input type="hidden" name="action" value="update_attendance">
                            <div class="settings-section">
                                <h5 style="color: var(--primary-teal); font-weight: 700;" class="mb-4"><i class="bi bi-pin-map-fill"></i> Geofencing Settings</h5>
                                <div class="row g-4 mb-4">
                                    <div class="col-md-6">
                                        <label class="form-label"><i class="bi bi-geo-alt" style="color: var(--primary-teal);"></i> Office Latitude</label>
                                        <input type="text" name="office_latitude" class="form-control" value="<?php echo $settings['office_latitude'] ?? ''; ?>" placeholder="e.g. 17.3850">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label"><i class="bi bi-geo-alt-fill" style="color: var(--primary-teal);"></i> Office Longitude</label>
                                        <input type="text" name="office_longitude" class="form-control" value="<?php echo $settings['office_longitude'] ?? ''; ?>" placeholder="e.g. 78.4867">
                                    </div>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label"><i class="bi bi-broadcast" style="color: var(--primary-teal);"></i> Allowed Radius</label>
                                    <div class="input-group">
                                        <input type="number" name="office_radius" class="form-control" value="<?php echo $settings['office_radius'] ?? '100'; ?>" required min="5">
                                        <span class="input-group-text">meters</span>
                                    </div>
                                </div>
                            </div>
                            <div class="text-end mt-4">
                                <button type="submit" class="btn px-5 py-3">
                                    Save Location Rules
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Email Settings Tab -->
                    <div class="tab-pane fade" id="email" role="tabpanel">
                        <form method="POST" action="hr_settings.php">
                            <input type="hidden" name="action" value="update_settings">
                            <div class="settings-section">
                                <h5 style="color: var(--primary-teal); font-weight: 700;" class="mb-4"><i class="bi bi-envelope-at-fill"></i> Gmail SMTP Configuration</h5>
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <label class="form-label"><i class="bi bi-envelope" style="color: var(--primary-teal);"></i> Gmail Address</label>
                                        <input type="email" name="gmail_username" class="form-control" value="<?php echo htmlspecialchars($settings['gmail_username'] ?? ''); ?>" placeholder="e.g. hr@company.com" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label"><i class="bi bi-shield-lock" style="color: var(--primary-teal);"></i> Google App Password</label>
                                        <div class="input-group">
                                            <input type="password" name="gmail_app_password" id="gmail_app_password" class="form-control" value="<?php echo htmlspecialchars($settings['gmail_app_password'] ?? ''); ?>" placeholder="16-digit App Password" required style="border-right: none;">
                                            <button class="btn" type="button" id="togglePassword" style="border-left: none; padding: 10px 14px; color: var(--text-muted); background: transparent;">
                                                <i class="bi bi-eye" id="eyeIcon"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="text-end mt-4">
                                <button type="submit" class="btn px-5 py-3">
                                    Save Email Settings
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Payroll Rules Tab -->
                    <div class="tab-pane fade" id="payroll" role="tabpanel">
                        <div class="settings-section">
                            <div class="d-flex flex-column flex-sm-row align-items-start align-items-sm-center justify-content-between mb-4 gap-3">
                                <h5 class="m-0" style="color: var(--primary-teal); font-weight: 700;"><i class="bi bi-calendar3-event"></i> Holiday Calendar</h5>
                                <div class="d-flex gap-2 align-items-center flex-wrap">
                                    <label class="small text-muted m-0 fw-bold">Select Year</label>
                                    <select id="holiday_year" class="form-select form-select-sm" style="width: 110px; padding: 6px 12px !important; border-radius:10px !important;">
                                        <?php
                                          $yNow = (int)date('Y');
                                          for ($yy = $yNow - 1; $yy <= $yNow + 2; $yy++) {
                                              echo '<option value="' . $yy . '">' . $yy . '</option>';
                                          }
                                        ?>
                                    </select>
                                    <button type="button" id="holiday_export_btn" class="btn btn-sm btn-outline-secondary" style="padding: 6px 16px !important; border-radius:10px !important;">
                                        <i class="bi bi-download me-1"></i> Export CSV
                                    </button>
                                </div>
                            </div>

                            <form id="holidayForm" class="row g-3 align-items-end mb-4">
                                <div class="col-md-3">
                                    <label class="form-label"><i class="bi bi-calendar-date"></i> Date</label>
                                    <input type="date" id="holiday_date" class="form-control" required>
                                </div>
                                <div class="col-md-7">
                                    <label class="form-label"><i class="bi bi-chat-left-text"></i> Reason / Holiday Name</label>
                                    <input type="text" id="holiday_reason" class="form-control" placeholder="e.g. Independence Day" required>
                                </div>
                                <div class="col-md-2 d-grid">
                                    <button type="submit" class="btn" style="padding: 10px 18px !important;">Save Holiday</button>
                                </div>
                            </form>

                            <div class="table-responsive mt-3 d-none d-md-block">
                                <table class="table table-sm align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th style="width: 180px;">Date</th>
                                            <th>Reason</th>
                                            <th style="width: 100px;" class="text-end">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="holiday_list">
                                        <tr><td colspan="3" class="text-muted small py-3 text-center">Loading…</td></tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="settings-mobile-list d-md-none mt-3" id="holidayMobileList">
                                <div class="settings-mobile-empty">Loading…</div>
                            </div>
                        </div>

                        <div class="settings-section">
                            <h5 style="color: var(--primary-teal); font-weight: 700;" class="mb-4"><i class="bi bi-calendar-week"></i> Weekend Policy Settings</h5>
                            <form method="POST" action="hr_settings.php">
                                <input type="hidden" name="action" value="update_settings">
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <label class="form-label"><i class="bi bi-calendar-check" style="color: var(--primary-teal);"></i> Are Sundays Paid?</label>
                                        <select name="sunday_is_paid_day" class="form-select">
                                            <option value="1" <?php echo $sunday_is_paid_day == '1' ? 'selected' : ''; ?>>Yes, Sundays are paid</option>
                                            <option value="0" <?php echo $sunday_is_paid_day == '0' ? 'selected' : ''; ?>>No, Sundays are not paid</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label"><i class="bi bi-calendar3" style="color: var(--primary-teal);"></i> Saturday Policy</label>
                                        <select name="saturday_rule" class="form-select">
                                            <option value="always_paid" <?php echo $saturday_rule === 'always_paid' ? 'selected' : ''; ?>>Every Saturday paid off</option>
                                            <option value="alternate_paid" <?php echo $saturday_rule === 'alternate_paid' ? 'selected' : ''; ?>>2nd &amp; 4th Saturday paid off</option>
                                            <option value="never_paid" <?php echo $saturday_rule === 'never_paid' ? 'selected' : ''; ?>>Saturday is a working day</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="text-end mt-4">
                                    <button type="submit" class="btn px-5 py-3">
                                        Save Payroll Rules
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<!-- Tab Fallback Script -->
<script>
$(document).ready(function(){
    // Tabs Mobile Scroll Arrow Logic
    $('#tabsScrollRight').click(function() {
        const container = $('#settingsTabs');
        container.animate({ scrollLeft: container.scrollLeft() + 150 }, 200);
    });

    $('#settingsTabs button').click(function (e) {
        e.preventDefault();
        $(this).tab('show');

        // Smoothly scroll tab bar to center the active tab in mobile view
        const tabEl = $(this);
        const container = $('#settingsTabs');
        const containerWidth = container.width();
        const scrollLeft = container.scrollLeft();
        const tabLeft = tabEl.position().left;
        const tabWidth = tabEl.outerWidth();
        
        const targetScroll = scrollLeft + tabLeft - (containerWidth / 2) + (tabWidth / 2);
        container.animate({ scrollLeft: targetScroll }, 300);
    });

    // Password Toggle Logic
    $('#togglePassword').click(function() {
        const passwordField = $('#gmail_app_password');
        const eyeIcon = $('#eyeIcon');
        const type = passwordField.attr('type') === 'password' ? 'text' : 'password';
        passwordField.attr('type', type);
        
        // Toggle icon class
        if (type === 'password') {
            eyeIcon.removeClass('bi-eye-slash').addClass('bi-eye');
        } else {
            eyeIcon.removeClass('bi-eye').addClass('bi-eye-slash');
        }
    });

    // Holiday calendar logic (HR Settings → Payroll Calculations)
    let holidayRowsCache = [];

    function csvEscapeHoliday(val) {
        const s = (val == null ? '' : String(val)).replace(/"/g, '""');
        return `"${s}"`;
    }

    async function exportHolidayCalendar() {
        const year = document.getElementById('holiday_year')?.value || new Date().getFullYear();
        let rows = holidayRowsCache;
        if (!rows.length) {
            try {
                const res = await fetch(`action.php?holidays_list=1&year=${encodeURIComponent(year)}`, { credentials: 'same-origin' });
                const json = await res.json();
                if (json.status === 'success') rows = json.data || [];
            } catch (e) {
                alert('Could not load holidays for export.');
                return;
            }
        }
        if (!rows.length) {
            alert(`No holidays to export for ${year}.`);
            return;
        }
        const lines = ['Date,Reason'];
        rows.forEach((r) => {
            lines.push(`${csvEscapeHoliday(r.holiday_date)},${csvEscapeHoliday(r.reason || '')}`);
        });
        const blob = new Blob(['\ufeff' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `company_holidays_${year}.csv`;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(link.href);
    }

    function renderHolidayMobile(rows, message) {
        const list = document.getElementById('holidayMobileList');
        if (!list) return;
        if (message) {
            list.innerHTML = `<div class="settings-mobile-empty">${message}</div>`;
            return;
        }
        if (!rows.length) {
            list.innerHTML = '<div class="settings-mobile-empty">No holidays saved.</div>';
            return;
        }
        list.innerHTML = rows.map(r => {
            const d = (r.holiday_date || '').toString();
            const reason = (r.reason || '').toString().replace(/</g,'&lt;').replace(/>/g,'&gt;');
            return `<div class="settings-mobile-card">
                <div class="settings-mobile-card-top">
                    <div>
                        <div class="settings-mobile-label">Date</div>
                        <div class="settings-mobile-card-title">${d}</div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-danger" data-hdate="${d}">Delete</button>
                </div>
                <div class="settings-mobile-card-item full">
                    <span class="settings-mobile-label">Reason</span>
                    <span class="settings-mobile-value">${reason}</span>
                </div>
            </div>`;
        }).join('');
    }

    async function loadHolidays() {
        const year = document.getElementById('holiday_year')?.value || new Date().getFullYear();
        const tbody = document.getElementById('holiday_list');
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan=\"3\" class=\"text-muted small py-3 text-center\">Loading…</td></tr>';
        renderHolidayMobile([], 'Loading…');
        try {
            const res = await fetch(`action.php?holidays_list=1&year=${encodeURIComponent(year)}`, { credentials: 'same-origin' });
            const json = await res.json();
            if (json.status !== 'success') throw new Error(json.message || 'Failed');
            const rows = json.data || [];
            holidayRowsCache = rows;
            if (!rows.length) {
                tbody.innerHTML = '<tr><td colspan=\"3\" class=\"text-muted small py-3 text-center\">No holidays saved.</td></tr>';
                renderHolidayMobile([]);
                return;
            }
            tbody.innerHTML = rows.map(r => {
                const d = (r.holiday_date || '').toString();
                const reason = (r.reason || '').toString().replace(/</g,'&lt;').replace(/>/g,'&gt;');
                return `<tr>
                    <td><span class=\"fw-semibold\">${d}</span></td>
                    <td>${reason}</td>
                    <td class=\"text-end\">
                        <button type=\"button\" class=\"btn btn-sm btn-outline-danger\" data-hdate=\"${d}\">Delete</button>
                    </td>
                </tr>`;
            }).join('');
            renderHolidayMobile(rows);
        } catch (e) {
            tbody.innerHTML = '<tr><td colspan=\"3\" class=\"text-danger small py-3 text-center\">Failed to load holidays.</td></tr>';
            renderHolidayMobile([], 'Failed to load holidays.');
        }
    }

    $(document).on('change', '#holiday_year', loadHolidays);
    $(document).on('click', '#holiday_export_btn', exportHolidayCalendar);

    $(document).on('submit', '#holidayForm', async function(e) {
        e.preventDefault();
        const date = document.getElementById('holiday_date')?.value;
        const reason = document.getElementById('holiday_reason')?.value;
        if (!date || !reason) return;
        const fd = new FormData();
        fd.append('save_holiday', '1');
        fd.append('date', date);
        fd.append('reason', reason);
        const btn = this.querySelector('button[type=\"submit\"]');
        if (btn) btn.disabled = true;
        try {
            const res = await fetch('action.php', { method: 'POST', body: fd, credentials: 'same-origin' });
            const json = await res.json();
            if (json.status !== 'success') throw new Error(json.message || 'Failed');
            document.getElementById('holiday_reason').value = '';
            await loadHolidays();
        } catch (err) {
            alert('Failed to save holiday');
        } finally {
            if (btn) btn.disabled = false;
        }
    });

    $(document).on('click', '#holiday_list button[data-hdate], #holidayMobileList button[data-hdate]', async function() {
        const date = this.getAttribute('data-hdate');
        if (!date) return;
        if (!confirm(`Delete holiday on ${date}?`)) return;
        const fd = new FormData();
        fd.append('delete_holiday', '1');
        fd.append('date', date);
        this.disabled = true;
        try {
            const res = await fetch('action.php', { method: 'POST', body: fd, credentials: 'same-origin' });
            const json = await res.json();
            if (json.status !== 'success') throw new Error(json.message || 'Failed');
            await loadHolidays();
        } catch (err) {
            alert('Failed to delete holiday');
            this.disabled = false;
        }
    });

    // Initial load
    if (document.getElementById('holiday_year')) {
        document.getElementById('holiday_year').value = new Date().getFullYear();
        loadHolidays();
    }
});
</script>
<?php include 'htmlclose.php'; ?>