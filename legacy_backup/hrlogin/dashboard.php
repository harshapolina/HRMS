<?php 
session_start();
require_once 'db.php';
// Redirect to login if not logged in or not an admin
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'hradminuser') {
    header('Location: /');
    exit;
}
$skip_superadmin_css = true;
include __DIR__ . '/htmlopen.php'; 
include __DIR__ . '/header.php'; 

$db = new Database();
$conn = $db->getConnection();

// 1. Active Employees
$stmt = $conn->query("SELECT COUNT(*) FROM accounts WHERE is_active = 1");
$active_employees = $stmt->fetchColumn() ?: 0;

// 2. Today Present
$today = date('Y-m-d');
$stmt = $conn->prepare("SELECT COUNT(*) FROM user_attendance WHERE today_status IS NOT NULL AND today_status != 'Not Logged' AND today_date = ?");
$stmt->execute([$today]);
$today_present = $stmt->fetchColumn() ?: 0;

// 3. Absent Today
$absent_today = $active_employees - $today_present;

// 4. Total Salary
$stmt = $conn->query("SELECT SUM(salary) FROM accounts WHERE is_active = 1");
$total_salary = $stmt->fetchColumn() ?: 0;
$formatted_salary = "₹ " . number_format($total_salary);
?>
<link rel="stylesheet" href="../superadmin/assets/css/calender.css" />
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
    .premium-dashboard { font-family: 'Outfit', sans-serif; padding: 20px; }
    .premium-title { font-size: 1.8rem; font-weight: 700; color: #1e293b; margin-bottom: 25px; }
    
    @media (min-width: 769px) {
        .stats-row-mobile, .chart-row-mobile {
            margin-left: -12px !important;
            margin-right: -12px !important;
        }
        .stats-col-mobile, .chart-col-mobile {
            padding-left: 12px !important;
            padding-right: 12px !important;
        }
    }
    
    .stat-card {
        background: white;
        border-radius: 20px;
        padding: 24px;
        display: flex;
        align-items: center;
        box-shadow: 0 10px 25px rgba(0,0,0,0.05);
        border: 1px solid rgba(226, 232, 240, 0.8);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        width: 100% !important;
        min-width: 0 !important;
        flex: none !important;
    }
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 20px 30px rgba(0,0,0,0.08);
    }
    
    .stat-icon {
        width: 60px;
        height: 60px;
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        margin-right: 20px;
        flex-shrink: 0;
    }
    .icon-blue { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color: white; box-shadow: 0 8px 16px rgba(59, 130, 246, 0.3); }
    .icon-green { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; box-shadow: 0 8px 16px rgba(16, 185, 129, 0.3); }
    .icon-red { background: linear-gradient(135deg, #f43f5e 0%, #e11d48 100%); color: white; box-shadow: 0 8px 16px rgba(244, 63, 94, 0.3); }
    .icon-purple { background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); color: white; box-shadow: 0 8px 16px rgba(139, 92, 246, 0.3); }
    
    .stat-content { display: flex; flex-direction: column; }
    .stat-label { font-size: 0.9rem; color: #64748b; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 5px; }
    .stat-value { font-size: 1.8rem; font-weight: 700; color: #0f172a; line-height: 1; }

    /* Dark Mode Support for Dashboard */
    body.dark-mode .premium-title { color: #f8fafc; }
    body.dark-mode .stat-card { background: #1e293b; border-color: #334155; box-shadow: 0 10px 25px rgba(0,0,0,0.2); }
    body.dark-mode .stat-label { color: #94a3b8; }
    body.dark-mode .stat-value { color: #f8fafc; }
    body.dark-mode .premium-chart-container { background: #1e293b; border-color: #334155; box-shadow: 0 10px 25px rgba(0,0,0,0.2); }
    body.dark-mode .premium-chart-title { color: #f8fafc; }
    body.dark-mode .premium-calendar { background: #1e293b; border-color: #334155; box-shadow: 0 10px 25px rgba(0,0,0,0.2); }
    body.dark-mode .premium-select { background-color: #334155; color: #f8fafc; border-color: #475569; }
</style>

<div class="content">
  <div class="container-fluid premium-dashboard">
    <h2 class="premium-title">Overview Dashboard</h2>
    
    <div class="row stats-row-mobile mb-3">
        <div class="col-lg-3 col-md-6 mb-4 stats-col-mobile">
            <div class="stat-card h-100">
                <div class="stat-icon icon-blue">
                    <i class="bi bi-people-fill"></i>
                </div>
                <div class="stat-content">
                    <span class="stat-label">Active Employees</span>
                    <span class="stat-value"><?= $active_employees ?></span>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-4 stats-col-mobile">
            <div class="stat-card h-100">
                <div class="stat-icon icon-green">
                    <i class="bi bi-check-circle-fill"></i>
                </div>
                <div class="stat-content">
                    <span class="stat-label">Today Present</span>
                    <span class="stat-value"><?= $today_present ?></span>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-4 stats-col-mobile">
            <div class="stat-card h-100">
                <div class="stat-icon icon-red">
                    <i class="bi bi-x-circle-fill"></i>
                </div>
                <div class="stat-content">
                    <span class="stat-label">Absent Today</span>
                    <span class="stat-value"><?= $absent_today ?></span>
                </div>
            </div>
        </div>
        
        <div class="col-lg-3 col-md-6 mb-4 stats-col-mobile">
            <div class="stat-card h-100">
                <div class="stat-icon icon-purple">
                    <i class="bi bi-cash-stack"></i>
                </div>
                <div class="stat-content">
                    <span class="stat-label">Total Mo. Salary</span>
                    <span class="stat-value"><?= $formatted_salary ?></span>
                </div>
            </div>
        </div>
    </div>
    
    
    
  <div class="modal fade" tabindex="-1" id="addNewUserModal">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Add New Employee</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <form id="add-user-form" name="myform" class="p-2" novalidate>
            <div class="row mb-3 gx-3">
              <div class="col">
                <label for="date of joining">DOJ</label>
                <input type="date" name="doj" class="form-control form-control-lg" required>
                <div class="invalid-feedback">Date of Joning is required!</div>
              </div>
              <div class="col">
              <label for="date of Birth">DOB</label>
                <input type="date" name="dob" class="form-control form-control-lg" required>
                <div class="invalid-feedback">Date of Birth required!</div>
              </div>
            </div>
            <div class="row mb-3 gx-3">
              <div class="col">
                <input type="text" name="ename" class="form-control form-control-lg" placeholder="Enter Employee Name" required>
                <div class="invalid-feedback">Employee name is required!</div>
              </div>
              <div class="col">
                <input type="text" name="eemail" class="form-control form-control-lg" placeholder="Enter Employee Email" required>
                <div class="invalid-feedback">Employee Email is required!</div>
              </div>
            </div>
            <div class="row mb-3 gx-3">
              <div class="col">
                <input type="tel" name="enumber" class="form-control form-control-lg" placeholder="Enter Employee Number" required>
                <div class="invalid-feedback">Employee number is required!</div>
              </div>
              <div class="col">
                <input type="text" name="epass" class="form-control form-control-lg" placeholder="Enter Employee Password" required>
                <div class="invalid-feedback">Employee Password is required!</div>
              </div>
            </div>
            <div class="mb-3">
              <input type="number" name="esalary" class="form-control form-control-lg" placeholder="Enter Employee Salary" required>
              <div class="invalid-feedback">Employee Salary is required!</div>
            </div>
            <div class="row mb-3 gx-3">
              <div class="col">
                <input type="text" name="etable" class="form-control form-control-lg" placeholder="Enter Employee Table Name" required>
                <div class="invalid-feedback">Table name is required!</div>
              </div>
              <div class="col">
                <input type="text" name="emid" class="form-control form-control-lg" placeholder="Enter Employee ID" required>
                <div class="invalid-feedback">Employee ID is required!</div>
              </div>
            </div>
            
            <div class="row mb-3 gx-3">
              <div class="col">
                <input type="number" name="amountO" class="form-control form-control-lg" placeholder="Enter 1st Amount" required>
                <div class="invalid-feedback">1st Amount is required!</div>
              </div>
              <div class="col">
                <input type="number" name="amountT" class="form-control form-control-lg" placeholder="Enter 2nd Amount" required>
                <div class="invalid-feedback">2nd Amount is required!</div>
              </div>
            </div>
            <div class="row mb-3 gx-3">
              <div class="col">
                <input type="number" name="amountTh" class="form-control form-control-lg" placeholder="Enter 3rd Amount" required>
                <div class="invalid-feedback">3rd Amount is required!</div>
              </div>
              <div class="col">
                <input type="number" name="amountF" class="form-control form-control-lg" placeholder="Enter 4th Amount" required>
                <div class="invalid-feedback">4th Amount is required!</div>
              </div>
            </div>
            <div class="row mb-3 gx-3">
              <div class="col">
                <input type="number" name="amountFf" class="form-control form-control-lg" placeholder="Enter 5th Amount" required>
                <div class="invalid-feedback">5th Amount is required!</div>
              </div>
              <div class="col">
                <input type="number" name="amountS" class="form-control form-control-lg" placeholder="Enter 6th Amount" required>
                <div class="invalid-feedback">6th Amount is required!</div>
              </div>
            </div>
            <div class="row mb-3 gx-3">
              <div class="col">
                <input type="text" name="project_name" class="form-control form-control-lg" placeholder="Enter Project Name" required>
                <div class="invalid-feedback">Project name is required!</div>
              </div>
              <div class="col">
              <select name="D_project" class="selection">
                    <option value="">Select Project Type</option>
                    <option value="mandate">Mandate</option>
                    <option value="retail">Retail</option>
                </select>
                <div class="invalid-feedback">Employee PJT is required!</div>
              </div>
            </div>
            <div class="mb-3">
              <input type="text" name="ecode" class="form-control form-control-lg" placeholder="Enter Employee Code" required>
              <div class="invalid-feedback">Employee code is required!</div>
            </div>
            <div class="mb-3">
              <input type="submit" value="Add Employee" class="btn btn-primary btn-block btn-lg" id="add-user-btn">
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
  <!-- Add New User Modal End -->
  <!-- Edit User Modal Start -->
  <div class="modal fade" tabindex="-1" id="editUserModal">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Edit Employee Details</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <form id="edit-user-form" name="myform" class="p-2" novalidate>
            <input type="hidden" name="id" id="id">
            <div class="row mb-3 gx-3">
              <div class="col">
                <label for="date of joining">DOJ</label>
                <input type="date" name="doj" id="doj" class="form-control form-control-lg" required>
                <div class="invalid-feedback">Date of Joning is required!</div>
              </div>
              <div class="col">
                <label for="date of joining">DOB</label>
                <input type="date" name="dob" id="dob" class="form-control form-control-lg" placeholder="Enter Last Name" required>
                <div class="invalid-feedback">Date of Birth is required!</div>
              </div>
            </div>
            <div class="row mb-3 gx-3">
              <div class="col">
              <input type="text" name="ename" id="ename" class="form-control form-control-lg" placeholder="Enter Employee Name" required>
                <div class="invalid-feedback">Employee name is required!</div>
              </div>
              <div class="col">
                <input type="text" name="eemail" id="eemail" class="form-control form-control-lg" placeholder="Enter Employee Email" required>
                <div class="invalid-feedback">Employee Email is required!</div>
              </div>
            </div>
            <div class="row mb-3 gx-3">
              <div class="col">
              <input type="tel" name="enumber" id="enumber" class="form-control form-control-lg" placeholder="Enter Employee Number" required>
                <div class="invalid-feedback">Employee Number is required!</div>
              </div>
              <div class="col">
                <input type="text" name="epass" id="epass" class="form-control form-control-lg" placeholder="Enter Employee Password" required>
                <div class="invalid-feedback">Employee Password is required!</div>
              </div>
            </div>
            <div class="mb-3">
              <input type="number" name="esalary" id="esalary" class="form-control form-control-lg" placeholder="Enter Employee Salary" required>
              <div class="invalid-feedback">Employee Salary is required!</div>
            </div>
            <div class="row mb-3 gx-3">
              <div class="col">
              <input type="text" name="etable" id="etable" class="form-control form-control-lg" placeholder="Enter Employee Table" required>
                <div class="invalid-feedback">Table Name is required!</div>
              </div>
              <div class="col">
                <input type="text" name="emid" id="emid" class="form-control form-control-lg" placeholder="Enter Employee ID" required>
                <div class="invalid-feedback">Employee Id is required!</div>
              </div>
            </div>
            
            <div class="row mb-3 gx-3">
              <div class="col">
                <input type="number" name="amountO" id="amountO" class="form-control form-control-lg" placeholder="Enter 1st Amount" required>
                <div class="invalid-feedback">1st Amount is required!</div>
              </div>
              <div class="col">
                <input type="number" name="amountT" id="amountT" class="form-control form-control-lg" placeholder="Enter 2nd Amount" required>
                <div class="invalid-feedback">2nd Amount is required!</div>
              </div>
            </div>
            <div class="row mb-3 gx-3">
              <div class="col">
                <input type="number" name="amountTh" id="amountTh" class="form-control form-control-lg" placeholder="Enter 3rd Amount" required>
                <div class="invalid-feedback">3rd Amount is required!</div>
              </div>
              <div class="col">
                <input type="number" name="amountF" id="amountF" class="form-control form-control-lg" placeholder="Enter 4th Amount" required>
                <div class="invalid-feedback">4th Amount is required!</div>
              </div>
            </div>
            <div class="row mb-3 gx-3">
              <div class="col">
                <input type="number" name="amountFf" id="amountFf" class="form-control form-control-lg" placeholder="Enter 5th Amount" required>
                <div class="invalid-feedback">5th Amount is required!</div>
              </div>
              <div class="col">
                <input type="number" name="amountS" id="amountS" class="form-control form-control-lg" placeholder="Enter 6th Amount" required>
                <div class="invalid-feedback">6th Amount is required!</div>
              </div>
            </div>
            <div class="row mb-3 gx-3">
              <div class="col">
                <input type="text" name="project_name" id="project_name" class="form-control form-control-lg" placeholder="Enter Project Name" required>
                <div class="invalid-feedback">Project name is required!</div>
              </div>
              <div class="col">
              <select name="D_project" id="D_project" class="selection">
                    <option value="">Select Project Type</option>
                    <option value="mandate">Mandate</option>
                    <option value="retail">Retail</option>
                </select>
                <div class="invalid-feedback">Employee PJT is required!</div>
              </div>
            </div>
           
            <div class="mb-3">
              <input type="text" name="ecode" id="ecode" class="form-control form-control-lg" placeholder="Enter Code" required>
              <div class="invalid-feedback">Employee Code is required!</div>
            </div>
            <div class="mb-3">
              <input type="submit" value="Update" class="btn btn-success btn-block btn-lg" id="edit-user-btn">
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
  <!-- Edit User Modal End -->
<!-- Main Content -->
    <style>
        .premium-chart-container {
            background: white;
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
            border: 1px solid rgba(226, 232, 240, 0.8);
            margin-bottom: 20px;
        }
        .premium-chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .premium-chart-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: #1e293b;
            margin: 0;
        }
        .premium-select {
            border-radius: 10px;
            border: 1px solid #cbd5e1;
            background-color: #f8fafc;
            color: #475569;
            font-weight: 500;
            cursor: pointer;
            box-shadow: none !important;
        }
        .premium-calendar {
            background: white;
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
            border: 1px solid rgba(226, 232, 240, 0.8);
        }

        /* Universal Calendar Header Alignment (Mobile + Desktop) */
        .premium-calendar #right {
            position: relative !important;
        }
        .premium-calendar #monthAndYear {
            text-align: center !important;
            margin: 0 60px 20px 60px !important;
            padding: 0 !important;
            height: 38px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            font-size: 1.35rem !important;
            font-weight: 700 !important;
            color: #1e293b !important;
            line-height: 1 !important;
        }
        .premium-calendar .button-container-calendar {
            position: static !important;
            margin: 0 !important;
            padding: 0 !important;
        }
        .premium-calendar #previous,
        .premium-calendar #next {
            position: absolute !important;
            top: 0 !important;
            margin: 0 !important;
            height: 38px !important;
            width: 38px !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            border-radius: 8px !important;
            font-size: 1.5rem !important;
            font-weight: 600 !important;
            line-height: 1 !important;
            padding: 0 0 3px 0 !important; /* Optically center arrow vertically */
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05) !important;
            transition: all 0.2s ease !important;
        }
        .premium-calendar #previous {
            left: 0 !important;
        }
        .premium-calendar #next {
            right: 0 !important;
        }
        .premium-calendar #previous:hover,
        .premium-calendar #next:hover {
            transform: scale(1.08) !important;
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1) !important;
        }

        .chart-wrapper {
            position: relative;
            height: 300px;
            width: 100%;
        }

        /* Equal card heights in desktop view */
        @media (min-width: 992px) {
            .chart-row-mobile {
                display: flex;
            }
            .chart-row-mobile > div {
                display: flex;
                flex-direction: column;
            }
            .premium-chart-container, .premium-calendar {
                flex: 1;
                height: 100%;
                margin-bottom: 0 !important;
            }

            /* Premium Full-Height Calendar Layout for Desktop */
            .premium-calendar {
                display: flex !important;
                flex-direction: column !important;
            }
            .premium-calendar > .wrapper {
                flex: 1 !important;
                display: flex !important;
                flex-direction: column !important;
                height: 100% !important;
            }
            .premium-calendar .container-calendar {
                flex: 1 !important;
                display: flex !important;
                flex-direction: column !important;
                height: 100% !important;
                max-width: 100% !important;
                padding: 0 !important;
                background: transparent !important;
            }
            .premium-calendar #right {
                flex: 1 !important;
                display: flex !important;
                flex-direction: column !important;
                height: 100% !important;
            }
            .premium-calendar .table-calendar {
                flex: 1 !important;
                width: 100% !important;
                height: 100% !important;
                margin-bottom: 0 !important;
                border-collapse: collapse !important;
            }
            .premium-calendar .table-calendar tbody {
                height: 100% !important;
            }
            .premium-calendar .table-calendar tr {
                height: auto !important;
            }
            .premium-calendar .table-calendar td,
            .premium-calendar .table-calendar th {
                height: auto !important;
                padding: 12px 8px !important;
                vertical-align: middle !important;
                text-align: center !important;
                transition: background-color 0.2s ease, transform 0.2s ease;
            }
            .premium-calendar .table-calendar td:hover {
                background-color: rgba(59, 130, 246, 0.08) !important;
                cursor: pointer;
            }
            body.dark-mode .premium-calendar .table-calendar td:hover {
                background-color: rgba(248, 250, 252, 0.1) !important;
            }
        }

        /* Mobile Responsiveness */
        @media (max-width: 768px) {
            .premium-dashboard { padding: 15px; }
            .chart-row-mobile, .stats-row-mobile {
                margin-left: 0 !important;
                margin-right: 0 !important;
            }
            .chart-col-mobile, .stats-col-mobile {
                padding-left: 0 !important;
                padding-right: 0 !important;
            }
            .stat-card {
                padding: 20px;
            }
            .stat-icon {
                width: 50px;
                height: 50px;
                font-size: 20px;
                margin-right: 15px;
            }
            .stat-value {
                font-size: 1.5rem;
            }
            .premium-chart-container, .premium-calendar {
                padding: 15px;
                margin-bottom: 15px;
            }
            .premium-chart-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }
            .premium-select {
                width: 100% !important;
            }
            .chart-wrapper {
                height: 250px;
            }
            .premium-calendar {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
        }
    </style>

    <div class="row chart-row-mobile">
        <div class="col-lg-8 mb-4 chart-col-mobile">
            <div class="premium-chart-container">
                <div class="premium-chart-header">
                    <h3 class="premium-chart-title">Attendance Overview</h3>
                    <select class="form-select w-auto premium-select" id="attendanceFilter">
                        <option value="This Week">This Week</option>
                        <option value="Last Week">Last Week</option>
                    </select>
                </div>
                <div class="chart-wrapper">
                    <canvas id="attendanceChart"></canvas>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4 mb-4 chart-col-mobile">
            <div class="premium-chart-container">
                <div class="premium-chart-header">
                    <h3 class="premium-chart-title">Employee Status</h3>
                </div>
                <div class="chart-wrapper">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row chart-row-mobile">
        <div class="col-lg-6 mb-4 chart-col-mobile">
            <div class="premium-chart-container">
                <div class="premium-chart-header">
                    <h3 class="premium-chart-title">Salary Expenses</h3>
                    <select class="premium-select" id="salaryFilter">
                        <option value="2026">2026</option>
                        <option value="2025">2025</option>
                    </select>
                </div>
                <div class="chart-wrapper">
                    <canvas id="salaryChart"></canvas>
                </div>
            </div>
        </div>
        
        <div class="col-lg-6 mb-4 chart-col-mobile">
            <div class="premium-calendar">
              <div class="wrapper" style="box-shadow: none; border: none; padding: 0;">
                <div class="container-calendar" style="background: transparent;">
                  <div id="right">
                    <h3 id="monthAndYear" style="color: #1e293b; font-weight: 700; font-family: 'Outfit';"></h3>
                    <div class="button-container-calendar">
                      <button id="previous" onclick="previous()">‹</button>
                      <button id="next" onclick="next()">›</button>
                    </div>
                    <table class="table-calendar" id="calendar" data-lang="en">
                      <thead id="thead-month"></thead>
                      <tbody id="calendar-body"></tbody>
                    </table>
                    <div class="footer-container-calendar" style="display:none;">
                      <label for="month">Jump To: </label>
                      <select id="month" onchange="jump()"><option value=0>Jan</option><option value=1>Feb</option><option value=2>Mar</option><option value=3>Apr</option><option value=4>May</option><option value=5>Jun</option><option value=6>Jul</option><option value=7>Aug</option><option value=8>Sep</option><option value=9>Oct</option><option value=10>Nov</option><option value=11>Dec</option></select>
                      <select id="year" onchange="jump()"></select>
                    </div>
                  </div>
                </div>
              </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        Chart.defaults.font.family = "'Outfit', sans-serif";
        Chart.defaults.color = "#64748b";

        // Attendance Line Chart
        var attendanceChart = new Chart(document.getElementById('attendanceChart'), {
            type: 'line',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [{
                    label: 'Present Employees',
                    data: [12, 19, 15, 17, 14, 8, 5],
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: '#3b82f6',
                    pointBorderWidth: 2,
                    pointRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { borderDash: [5, 5], color: '#f1f5f9' }, border: { display: false } },
                    x: { grid: { display: false }, border: { display: false } }
                }
            }
        });

        // Status Doughnut Chart
        var statusChart = new Chart(document.getElementById('statusChart'), {
            type: 'doughnut',
            data: {
                labels: ['Active', 'On Leave', 'Inactive'],
                datasets: [{
                    data: [<?= $active_employees ?>, <?= $absent_today ?>, 2],
                    backgroundColor: ['#10b981', '#f59e0b', '#ef4444'],
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: { position: 'bottom', labels: { usePointStyle: true, padding: 20 } }
                }
            }
        });

        // Salary Bar Chart
        var salaryChart = new Chart(document.getElementById('salaryChart'), {
            type: 'bar',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [{
                    label: 'Payroll (₹)',
                    data: [280000, 310000, 290000, 320000, 340000, 340000],
                    backgroundColor: '#8b5cf6',
                    borderRadius: 6,
                    borderSkipped: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { borderDash: [5, 5], color: '#f1f5f9' }, border: { display: false } },
                    x: { grid: { display: false }, border: { display: false } }
                }
            }
        });

        // Add Interactivity to Filters
        document.getElementById('attendanceFilter').addEventListener('change', function(e) {
            if(e.target.value === 'This Week') {
                attendanceChart.data.datasets[0].data = [12, 19, 15, 17, 14, 8, 5];
            } else {
                attendanceChart.data.datasets[0].data = [15, 16, 18, 14, 19, 11, 7];
            }
            attendanceChart.update();
        });

        document.getElementById('salaryFilter').addEventListener('change', function(e) {
            if(e.target.value === '2026') {
                salaryChart.data.datasets[0].data = [280000, 310000, 290000, 320000, 340000, 340000];
            } else {
                salaryChart.data.datasets[0].data = [240000, 250000, 245000, 260000, 270000, 275000];
            }
            salaryChart.update();
        });
    </script>
    <!-- <div class="row  mt-3">
      <div class="col-lg-12 indexbooktable">
        <h4>Users Database</h4>
        <a href="/incentiveapp_integration/hrlogin/incentiveuser.php">
          <div class="panel-body">
          <table id="example" class="stripe row-border order-column display" cellspacing="0" style="width:100%">
            <thead>
              <tr>
                <th>ID</th>
                <th>Date Of Joining</th>
                <th>Date Of Birth</th>
                <th>Name</th>
                <th>Email</th>
                <th>Contact No.</th>
                <th>Password</th>
                <th>In Hand Salary</th>
                <th>Designation</th>
                <th>Employee Id</th>
                <th>1st Amount</th>
                <th>2nd Amount</th>
                <th>3rd Amount</th>
                <th>4th Amount</th>
                <th>5th Amount</th>
                <th>6th Amount</th>
                <th>Project Name</th>
                <th>Project Type</th>
                <th>Role Type</th>
              </tr>
            </thead>
            <tbody id="incentiveuser">
            </tbody>
            <tfoot>
              <td>ID</td>
              <td>Date Of Joining</td>
              <td>Date Of Birth</td>
              <td>Name</td>
              <td>Email</td>
              <td>Contact No.</td>
              <td>Password</td>
              <td>In Hand Salary</td>
              <td>Designation</td>
              <td>Employee Id</td>
              <td>1st Amount</td>
              <td>2nd Amount</td>
              <td>3rd Amount</td>
              <td>4th Amount</td>
              <td>5th Amount</td>
              <td>6th Amount</td>
              <td>Project Name</td>
              <td>Project Type</td>
              <td>Role Type</td>
            </tfoot>
          </table>
          </div>
        </a>
      </div>
    </div> -->
<!--End Main Content -->
<!-- Filter Rows Modal Start -->
<!-- <div class="modal fade" tabindex="-1" id="filterModal">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title">Filter Data</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" id="closeFilter"></button>
            </div>
            <div class="modal-body">
              <div class="container p-0">
                <div class="row">
                  <div class="col-md-6">
                    <input type="text" class="form-control mb-2" id="filterID" placeholder="ID">
                    <input type="text" class="form-control mb-2" id="EmployeeId" placeholder="Employee Id">
                  </div>
                  <div class="col-md-6">
                  <input type="text" class="form-control mb-2" id="DateOfJoining" placeholder="Date Of Joining">
                    <input type="text" class="form-control mb-2" id="DateOfBirth" placeholder="Date Of Birth">
                  </div>
                  <div class="col-md-6">
                    <input type="text" class="form-control mb-2" id="username" placeholder="User name">
                    <input type="text" class="form-control mb-2" id="email" placeholder="Email Id">
                  </div>
                  <div class="col-md-6">
                    <input type="text" class="form-control mb-2" id="Contactnumber" placeholder="Contact No.">
                    <input type="text" class="form-control mb-2" id="Password" placeholder="Password">
                  </div>
                  <div class="col-md-6">
                    <input type="text" class="form-control mb-2" id="inhandsalary" placeholder="In hand salary">
                    <input type="text" class="form-control mb-2" id="designation" placeholder="Designation">
                  </div>
                  <div class="col-md-6">
                    <input type="text" class="form-control mb-2" id="firstamount" placeholder="1st amount">
                    <input type="text" class="form-control mb-2" id="scndamount" placeholder="2nd amount">
                  </div>
                  <div class="col-md-6">
                    <input type="text" class="form-control mb-2" id="thirdamount" placeholder="3rd amount">
                    <input type="text" class="form-control mb-2" id="fourthamount" placeholder="4th amount">
                  </div>
                  <div class="col-md-6">
                    <input type="text" class="form-control mb-2" id="fifthamount" placeholder="5th amount">
                    <input type="text" class="form-control mb-2" id="sixthamount" placeholder="6th amount">
                  </div>
                  <div class="col-md-6">
                    <input type="text" class="form-control mb-2" id="Projectname" placeholder="Project Name">
                  </div>
                  <div class="col-md-6">
                    <input type="text" class="form-control mb-2" id="Projecttype" placeholder="Project Type">
                  </div>
                  <div class="col-md-6">
                    <input type="text" class="form-control mb-2" id="Code" placeholder="Code">
                  </div>
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="cancleFilter">Close</button>
              <button type="button" class="btn btn-danger" id="clearFiltersBtn">Clear Filters</button>
              <button type="button" class="btn btn-primary" id="applyFiltersBtn">Apply Filters</button>
            </div>
          </div>
        </div>
      </div> -      <!-- Filter INput  -->
      <!-- filter rows Modal End -->
      <!-- <script src="main.js"></script>   -->
      <script src="../superadmin/assets/js/calender.js"></script>
      <script>
        function applyFilters(){var n=[{id:"filterID",columnIndex:0},{id:"EmployeeId",columnIndex:1},{id:"DateOfJoining",columnIndex:2},{id:"DateOfBirth",columnIndex:3},{id:"username",columnIndex:4},{id:"email",columnIndex:5},{id:"Contactnumber",columnIndex:6},{id:"Password",columnIndex:7},{id:"inhandsalary",columnIndex:8},{id:"designation",columnIndex:9},{id:"firstamount",columnIndex:10},{id:"scndamount",columnIndex:11},{id:"thirdamount",columnIndex:12},{id:"fourthamount",columnIndex:13},{id:"fifthamount",columnIndex:14},{id:"sixthamount",columnIndex:15},{id:"Projectname",columnIndex:16},{id:"Projecttype",columnIndex:17},{id:"Code",columnIndex:18},];activeFilters=[],$("#incentiveuser tr").each(function(){var e=$(this),i=!0;n.forEach(function(n){var t=$("#"+n.id).val().toLowerCase();if(-1===e.find("td:eq("+n.columnIndex+")").text().toLowerCase().indexOf(t))return i=!1,!1;""!==t.trim()&&activeFilters.push(t)}),i?e.addClass("custom-filtered-row"):e.removeClass("custom-filtered-row")}),$("#incentiveuser tr").hide(),applyCustomFilter()}function applyCustomFilter(){$(".custom-filtered-row").show()}applyCustomFilter(),$(".filterable .btn-filter1").click(function(){$("#filterModal").modal("show")}),$("#applyFiltersBtn").click(function(){$("#filterModal").modal("hide"),applyFilters()}),$("#filterModal").on("hidden.bs.modal",function(){$(".filterable .filters input").val(""),applyFilters()}),$("#closeFilter").click(function(){applyFilters(),$("#filterModal").modal("hide")}),$("#cancleFilter").click(function(){applyFilters(),$("#filterModal").modal("hide")}),$(document).ready(function(){$("#clearFiltersBtn").click(function(){$("#filterID, #EmployeeId, #DateOfJoining, #DateOfBirth, #username, #email, #Contactnumber, #Password, #inhandsalary, #designation, #firstamount, #scndamount, #thirdamount, #fourthamount, #fifthamount, #sixthamount, #Projectname, #Projecttype, #Code").val("")})}),$("#clearFiltersBtn").click(function(){applyFilters(),$("#filterModal").modal("hide")});
      </script>
    </div> <!-- .container-fluid -->
  </div> <!-- .content wrap -->
<?php include __DIR__ . '/htmlclose.php'; ?>
; ?>