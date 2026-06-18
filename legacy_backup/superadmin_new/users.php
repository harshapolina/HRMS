<?php
require_once dirname(__DIR__) . '/env_loader.php';
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
    '/superadmin_new/payment-tracking',
    '/superadmin_new/companyassets',
    '/superadmin_new/accounts',
    '/superadmin_new/property-bookings',
    '/superadmin_new/expenses',
    '/superadmin_new/incentive-tracking',
    '/hrlogin/createuser/',
    '/superadmin_new/users'
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

$sql = "SELECT  username, tablename, user_type FROM accounts WHERE user_type IN ('promoter', 'business head', 'manager', 'team lead') AND is_active = 1";
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
  <title>Users Database</title>
  <link rel="shortcut icon" type="nobglogo.png" href="../assets/images/nobglogo.png" alt="text">
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/5.0.0-alpha2/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" />
  <link rel="stylesheet" href="../assets/css/style.css" />
  <link rel="stylesheet" href="../assets/css/style1.css" />
  <link rel="stylesheet" href="../assets/css/loader.css"/>
  <style>
    .table-container,.table-wrap{position:relative}tbody tr td,tfoot tr td,thead tr th{text-align:center!important;white-space:nowrap}#scroll-left,#scroll-left i,#scroll-right,#scroll-right i{top:50%;transform:translate(-50%,-50%)}html::-webkit-scrollbar{behavior:smooth}.table-container{overflow-y:auto;max-height:100vh;height:100%;width:100%}table{border-collapse:collapse}.table-container::-webkit-scrollbar{width:5px;height:5px}.table-container::-webkit-scrollbar-track{background:#e3e3e3;border-radius:10px}.table-container::-webkit-scrollbar-thumb{background-color:#1b6c9f;border-radius:20px}.table-container thead{position:sticky;top:-1px;background:#fff}.table-container tfoot td,.table-container thead th{padding:10px 12px}.table-container tfoot{position:sticky;bottom:-1px;background:#fff}tbody tr td{font-weight:500;font-size:15px;border:1px solid rgba(0,0,0,.1);border-bottom:1px solid rgba(0,0,0,.599);padding:12px 20px}tfoot tr td,thead tr th{border:1px solid rgba(0,0,0,.529);color:#1b6c9f;font-weight:700}#scroll-left,#scroll-right{position:absolute;border-radius:50%;border:1px solid #000;width:35px;height:35px;background-color:#fff}#scroll-left i,#scroll-right i{font-size:23px;color:#1b6c9f;position:absolute;left:50%}#scroll-left{left:0;z-index:777}#scroll-right{right:0;z-index:777}@media only screen and (max-width:768px){#scroll-right{right:-20px}#scroll-left{left:20px}}
    .activeuser,.dactiveuser{width:100%;height:30px;font-weight:700;box-shadow:0 0 2px #000}.activeuser{border-radius:30px;background-color:#90ee90;border:1px solid #000}.dactiveuser{border-radius:30px;background-color:grey;border:1px solid #000;color:#fff}.row.mb-3.justify-content-center{display:flex;justify-content:center;align-items:center}.form-select{border-radius:10px;padding:10px;font-size:16px;color:#495057;width:50%;text-align:center}.form-select.active{background-color:#90ee90}.form-select.inactive{background-color:#d3d3d3}    .counterxy{text-align:center;padding:20px;border-radius:10px;color:#fff;margin-bottom:20px}.active-counter{background-color:#28a745}.inactive-counter{background-color:#dc3545}.counterxy { text-align: center; padding: 5px; border-radius: 10px; color: #fff; margin-bottom: 0px!important;}.counterxy h4{ font-size: 15px; } .counterxy h2{ font-size: 17px; } .table-container { max-height: 75vh!important; }
    .side-menu li.sideactive6{
      background: var(--shicol);
      position: relative
    }
    .side-menu li.sideactive6 a{
        color: white
    }
    .addNewUserModal,.downloadCsvBtn{ 
  display: none;
  }
  .custom-multiselect{position:relative;border:1px solid #ced4da;border-radius:8px;padding:5px 10px;cursor:pointer;min-height:42px;background-color:#fff}.custom-multiselect input[type=text]{border:none;outline:0;width:100%;background-color:transparent;cursor:pointer}#selected_tags{display:flex;flex-wrap:wrap;gap:5px;margin-top:5px}.tag{background-color:#0d6efd;color:#fff;padding:2px 8px;border-radius:6px;font-size:13px;display:flex;align-items:center}.tag .remove{margin-left:5px;cursor:pointer;font-weight:700}.dropdown{display:none;position:absolute;top:100%;left:0;right:0;border:1px solid #ddd;max-height:150px;overflow-y:auto;background:#fff;z-index:999;border-radius:5px;box-shadow:0 2px 8px rgba(0,0,0,.1)}.dropdown-item{padding:8px 12px;cursor:pointer}.dropdown-item:hover{background-color:#f1f1f1}
  </style>
<?php include('header.php'); ?>
<!-- Filter Rows Modal Start -->
<div class="modal fade" id="filterModal">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content custom-modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">FILTER DATA</h5>
                    <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close" id="closeFilter"></button>
                </div>
                <div class="modal-body">
                    <div class="container">
                        <div class="row">
                            <!-- Filter inputs -->
                            <div class="col-md-4 mb-2">
                                <div class="form-item">
                                    <label for="filterID">ID</label>
                                    <input type="text" class="form-control form-control-lg custom-input" id="filterID">
                                </div>
                            </div>
                            <div class="col-md-4 mb-2">
                                <div class="form-item">
                                    <label for="Status">Status</label>
                                    <input type="text" class="form-control form-control-lg custom-input" id="status">
                                </div>
                            </div>
                            <div class="col-md-4 mb-2">
                                <div class="form-item">
                                    <label for="Username">Username</label>
                                    <input type="text" class="form-control form-control-lg custom-input" id="username">
                                </div>
                            </div>
                            <div class="col-md-4 mb-2">
                                <div class="form-item">
                                    <label for="Email">Email</label>
                                    <input type="text" class="form-control form-control-lg custom-input" id="email">
                                </div>
                            </div>
                            <div class="col-md-4 mb-2">
                                <div class="form-item">
                                    <label for="Contactnumber">Contact Number</label>
                                    <input type="text" class="form-control form-control-lg custom-input" id="Contactnumber">
                                </div>
                            </div>
                            <div class="col-md-4 mb-2">
                                <div class="form-item">
                                    <label for="Password">Password</label>
                                    <input type="text" class="form-control form-control-lg custom-input" id="Password">
                                </div>
                            </div>
                            <div class="col-md-4 mb-2">
                                <div class="form-item">
                                    <label for="inhandsalary">Inhand Salary</label>
                                    <input type="text" class="form-control form-control-lg custom-input" id="inhandsalary">
                                </div>
                            </div>
                            <div class="col-md-4 mb-2">
                                <div class="form-item">
                                    <label for="DateOfJoining">Date Of Joining</label>
                                    <input type="text" class="form-control form-control-lg custom-input" id="DateOfJoining">
                                </div>
                            </div>
                            <div class="col-md-4 mb-2">
                                <div class="form-item">
                                    <label for="DateOfBirth">Date Of Birth</label>
                                    <input type="text" class="form-control form-control-lg custom-input" id="DateOfBirth">
                                </div>
                            </div>
                            <div class="col-md-4 mb-2">
                                <div class="form-item">
                                    <label for="uniqueid">Unique ID</label>
                                    <input type="text" class="form-control form-control-lg custom-input" id="uniqueid">
                                </div>
                            </div>
                            <div class="col-md-4 mb-2">
                                <div class="form-item">
                                    <label for="EmployeeId">Employee ID</label>
                                    <input type="text" class="form-control form-control-lg custom-input" id="EmployeeId">
                                </div>
                            </div>
                            <div class="col-md-4 mb-2">
                                <div class="form-item">
                                    <label for="firstamount">First Amount</label>
                                    <input type="text" class="form-control form-control-lg custom-input" id="firstamount">
                                </div>
                            </div>
                            <div class="col-md-4 mb-2">
                                <div class="form-item">
                                    <label for="scndamount">Second Amount</label>
                                    <input type="text" class="form-control form-control-lg custom-input" id="scndamount">
                                </div>
                            </div>
                            <div class="col-md-4 mb-2">
                                <div class="form-item">
                                    <label for="thirdamount">Third Amount</label>
                                    <input type="text" class="form-control form-control-lg custom-input" id="thirdamount">
                                </div>
                            </div>
                            <div class="col-md-4 mb-2">
                                <div class="form-item">
                                    <label for="fourthamount">Fourth Amount</label>
                                    <input type="text" class="form-control form-control-lg custom-input" id="fourthamount">
                                </div>
                            </div>
                            <div class="col-md-4 mb-2">
                                <div class="form-item">
                                    <label for="fifthamount">Fifth Amount</label>
                                    <input type="text" class="form-control form-control-lg custom-input" id="fifthamount">
                                </div>
                            </div>
                            <div class="col-md-4 mb-2">
                                <div class="form-item">
                                    <label for="sixthamount">Sixth Amount</label>
                                    <input type="text" class="form-control form-control-lg custom-input" id="sixthamount">
                                </div>
                            </div>
                            <div class="col-md-4 mb-2">
                                <div class="form-item">
                                    <label for="Projectname">Project Name</label>
                                    <input type="text" class="form-control form-control-lg custom-input" id="Projectname">
                                </div>
                            </div>
                            <div class="col-md-4 mb-2">
                                <div class="form-item">
                                    <label for="Projecttype">Project Type</label>
                                    <input type="text" class="form-control form-control-lg custom-input" id="Projecttype">
                                </div>
                            </div>
                            <div class="col-md-4 mb-2">
                                <div class="form-item">
                                    <label for="roletype">Role Type</label>
                                    <input type="text" class="form-control form-control-lg custom-input" id="roletype">
                                </div>
                            </div>
                            <div class="col-md-4 mb-2">
                                <div class="form-item">
                                    <label for="assignuser">Assign User</label>
                                    <input type="text" class="form-control form-control-lg custom-input" id="assignuser">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="margin: 0 auto;">
                    <!-- Close Modal button -->
                    <button type="button" class="btn btn-secondary" data-dismiss="modal" id="cancleFilter">Close</button>
                    <!-- Clear Filters button -->
                    <button type="button" class="btn btn-danger" id="clearFiltersBtn">Clear Filters</button>
                    <!-- Apply Filters button -->
                    <button type="button" class="btn btn-primary" id="applyFiltersBtn">Apply Filters</button>
                </div>
            </div>
        </div>
    </div>
<!-- filter rows Modal End -->
<!-- Add New User Modal Start -->
<div class="modal fade" tabindex="-1" id="addNewUserModal">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Add New Employee</h5>
          <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close"></button>
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
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Employee Details</h5>
                    <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="edit-user-form" name="myform" class="p-2" novalidate="">
                        <div class="container">
                            <div class="row">
                                <input type="hidden" name="id" id="id">
                                <div class="col-md-4 mb-2">
                                    <div class="form-item">
                                        <label for="ID">ID</label>
                                        <select name="is_active" id="is_active" class="form-control form-control-lg selection">
                                            <option value="">Select Status</option>
                                            <option value="1">Active</option>
                                            <option value="0">Inactive</option>
                                        </select>
                                        <div class="invalid-feedback">Employee Status is required!</div>
                                    </div>
                                </div>
                                
                                <div class="col-md-4 mb-2">
                                    <div class="form-item">
                                        <label for="assign_user_input" class="form-label fw-bold text-primary">Assign User</label>
                                        <div class="custom-multiselect" onclick="toggleDropdown()">
                                        <input type="text" id="assign_user_input" readonly placeholder="Select Assign Users" />
                                        <input type="hidden" name="assign_user" id="assign_user_hidden" />
                                        <div id="selected_tags"></div>
                                        
                                        <!-- Dropdown container -->
                                        <div class="dropdown" id="dropdown">
                                            <!-- 🔍 Search bar inside dropdown -->
                                            <input type="text" id="search_user_input" placeholder="Search..." onkeyup="filterDropdown()" class="form-control mb-1">

                                            <!-- Dropdown items -->
                                            <?php foreach ($assignUsers as $user): ?>
                                            <div class="dropdown-item" onclick="selectUser('<?= $user['tablename']; ?>', '<?= $user['username']; ?>')">
                                            <?= $user['username']; ?><span style="font-size: 11px;font-weight: 600;color: green;">(<?= $user['user_type']; ?>)</span>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                        </div>
                                        <div class="invalid-feedback">Employee Role Type is required!</div>
                                    </div>
                                </div>
                                
                                <div class="col-md-4 mb-2">
                                    <div class="form-item">
                                        <label for="date of joining">DOJ</label>
                                        <input type="date" name="doj" id="doj" class="form-control form-control-lg" required="">
                                        <div class="invalid-feedback">Date of Joning is required!</div>
                                    </div>
                                </div>

                                <div class="col-md-4 mb-2">
                                    <div class="form-item">
                                        <label for="date of joining">DOB</label>
                                        <input type="date" name="dob" id="dob" class="form-control form-control-lg" placeholder="Enter Last Name" required="">
                                        <div class="invalid-feedback">Date of Birth is required!</div>
                                    </div>
                                </div>

                                <div class="col-md-4 mb-2">
                                    <div class="form-item">
                                        <label for="Enter Employee Name">Enter Employee Name</label>
                                        <input type="text" name="ename" id="ename" class="form-control form-control-lg" required="">
                                        <div class="invalid-feedback">Employee name is required!</div>
                                    </div>
                                </div>

                                <div class="col-md-4 mb-2">
                                    <div class="form-item">
                                        <label for="Enter Employee Email">Enter Employee Email</label>
                                        <input type="text" name="eemail" id="eemail" class="form-control form-control-lg" required="">
                                        <div class="invalid-feedback">Employee Email is required!</div>
                                    </div>
                                </div>

                                <div class="col-md-4 mb-2">
                                    <div class="form-item">
                                        <label for="Enter Employee Number">Enter Employee Number</label>
                                        <input type="tel" name="enumber" id="enumber" class="form-control form-control-lg" required="">
                                        <div class="invalid-feedback">Employee Number is required!</div>
                                    </div>
                                </div>

                                <div class="col-md-4 mb-2">
                                    <div class="form-item">
                                        <label for="Enter Employee Password">Enter Employee Password</label>
                                        <input type="text" name="epass" id="epass" class="form-control form-control-lg" required="">
                                        <div class="invalid-feedback">Employee Password is required!</div>
                                    </div>
                                </div>

                                <div class="col-md-4 mb-2">
                                    <div class="form-item">
                                        <label for="Enter Employee Salary">Enter Employee Salary</label>
                                        <input type="number" name="esalary" id="esalary" class="form-control form-control-lg" required="">
                                        <div class="invalid-feedback">Employee Salary is required!</div>
                                    </div>
                                </div>

                                <div class="col-md-4 mb-2">
                                    <div class="form-item">
                                        <label for="Enter Employee Table">Enter Employee Table</label>
                                        <input type="text" name="etable" id="etable" class="form-control form-control-lg" required="">
                                        <div class="invalid-feedback">Table Name is required!</div>
                                    </div>
                                </div>

                                <div class="col-md-4 mb-2">
                                    <div class="form-item">
                                        <label for="Enter Employee ID">Enter Employee ID</label>
                                        <input type="text" name="emid" id="emid" class="form-control form-control-lg" required="">
                                        <div class="invalid-feedback">Employee Id is required!</div>
                                    </div>
                                </div>
                                
                                <div class="col-md-4 mb-2">
                                    <div class="form-item">
                                        <label for="Enter 1st Amoun">Enter 1st Amoun</label>
                                        <input type="number" name="amountO" id="amountO" class="form-control form-control-lg" required="">
                                        <div class="invalid-feedback">1st Amount is required!</div>
                                    </div>
                                </div>

                                <div class="col-md-4 mb-2">
                                    <div class="form-item">
                                        <label for="Enter 2nd Amount">Enter 2nd Amount</label>
                                        <input type="number" name="amountT" id="amountT" class="form-control form-control-lg" required="">
                                        <div class="invalid-feedback">2nd Amount is required!</div>
                                    </div>
                                </div>

                                <div class="col-md-4 mb-2">
                                    <div class="form-item">
                                        <label for="Enter 3rd Amount">Enter 3rd Amount</label>
                                        <input type="number" name="amountTh" id="amountTh" class="form-control form-control-lg" required="">
                                        <div class="invalid-feedback">3rd Amount is required!</div>
                                    </div>
                                </div>

                                <div class="col-md-4 mb-2">
                                    <div class="form-item">
                                        <label for="Enter 4th Amount">Enter 4th Amount</label>
                                        <input type="number" name="amountF" id="amountF" class="form-control form-control-lg" required="">
                                        <div class="invalid-feedback">4th Amount is required!</div>
                                    </div>
                                </div>

                                <div class="col-md-4 mb-2">
                                    <div class="form-item">
                                        <label for="Enter 5th Amount">Enter 5th Amount</label>
                                        <input type="number" name="amountFf" id="amountFf" class="form-control form-control-lg" required="">
                                        <div class="invalid-feedback">5th Amount is required!</div>
                                    </div>
                                </div>

                                <div class="col-md-4 mb-2">
                                    <div class="form-item">
                                        <label for="Enter 6th Amount">Enter 6th Amount</label>
                                        <input type="number" name="amountS" id="amountS" class="form-control form-control-lg" required="">
                                        <div class="invalid-feedback">6th Amount is required!</div>
                                    </div>
                                </div>

                                <div class="col-md-4 mb-2">
                                    <div class="form-item">
                                        <label for="Enter Project Name">Enter Project Name</label>
                                        <input type="text" name="project_name" id="project_name" class="form-control form-control-lg" required="">
                                        <div class="invalid-feedback">Project name is required!</div>
                                    </div>
                                </div>

                                <div class="col-md-4 mb-2">
                                    <div class="form-item">
                                        <label for="Detail project">Detail project</label>
                                        <select name="D_project" id="D_project" class="form-control form-control-lg selection">
                                            <option value="">Select Project Type</option>
                                            <option value="mandate">Mandate</option>
                                            <option value="retail">Retail</option>
                                        </select>
                                        <div class="invalid-feedback">Employee PJT is required!</div>
                                    </div>
                                </div> 
                            
                                <div class="col-md-4 mb-2">
                                    <div class="form-item">
                                        <label for="user_type">User Type</label>
                                        <select name="user_type" id="user_type" class="form-control form-control-lg selection">
                                            <option value="">Select Role Type</option>
                                            <option value="promoter">Promoter</option>
                                            <option value="business head">Business Head</option>
                                            <option value="manager">Manager</option>
                                            <option value="team lead">Team Lead</option>
                                            <option value="user">User</option>
                                        </select>
                                        <div class="invalid-feedback">Employee Role Type is required!</div>
                                    </div>
                                </div>

                                <div class="modal-footer" style="margin: 0 auto;">
                                    <input type="submit" value="Update" class="btn btn-success col-lg-12" id="edit-user-btn">
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
        </div>
    </div>
</div>
<!-- Edit User Modal End -->
<!-- Main Content -->
<div class="content">
<div class="contentinside">
  <div class="container">
    <div class="row">
        <div class="col-lg-12" style="margin-bottom:30px">
        <button type="button" id="activeCounter" class="btn btn-sm btn-primary">Active Users :<span id="activeCount">0</span></button>
        <button type="button" id="inactiveCounter" class="btn btn-sm btn-primary">Inactive Users : <span id="inactiveCount">0</span></button>
        <button type="button" class="btn btn-sm btn-primary">Assign Users : <span id="assignednuser">0</span></button>
        <button type="button" class="btn btn-sm btn-primary">Total Salary : <span id="totalsalary">0</span></button>
      </div>
      <div class="col-lg-12">
        <!-- Operation Alert -->
        <div class="col-lg-12">
          <div id="showAlert"></div>
        </div>
        <!-- Operation Alert End-->
        <div class="table-wrap">
          <div class="table-container">
            
            <table id="example" class="stripe row-border order-column display" cellspacing="0" style="width: 100%">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Status</th>
                  <th>Name</th>
                  <th>Email</th>
                  <th>Contact No.</th>
                  <th>Password</th>
                  <th>Monthly CTC</th>
                  <th>Date Of Joining</th>
                  <th>Date Of Birth</th>
                  <th>Unique ID</th>
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
                  <th>Assign User</th>
                  <th>Created At</th>
                  <th>Inactive At</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody id="incentiveuser">
                
              </tbody>
              <tfoot>
                <tr>
                  <td>ID</td>
                  <td>Status</td>
                  <td>Name</td>
                  <td>Email</td>
                  <td>Contact No.</td>
                  <td>Password</td>
                  <td>Monthly CTC</td>
                  <td>Date Of Joining</td>
                  <td>Date Of Birtd</td>
                  <td>Unique ID</td>
                  <td>Employee Id</td>
                  <td>1st Amount</td>
                  <td>2nd Amount</td>
                  <td>3rd Amount</td>
                  <td>4td Amount</td>
                  <td>5td Amount</td>
                  <td>6td Amount</td>
                  <td>Project Name</td>
                  <td>Project Type</td>
                  <td>Role Type</td>
                  <td>Assign User</td>
                  <td>Created At</td>
                  <td>Inactive At</td>
                  <td>Action</td>
                </tr>
              </tfoot>
            </table>
          </div>
          <button id="scroll-left">
            <i class="bx bx-left-arrow-alt"></i>
          </button>
          <button id="scroll-right">
            <i class="bx bx-right-arrow-alt"></i>
          </button>
        </div>
      </div>
    </div>
  </div>
</div>
</div>
<!--End Main Content -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="../assets/js/bootstrap_alpha2.js"></script>
  <script type="text/javascript" src="./assets/js/script.js"></script>
  <script src="hrmain.js"></script>
<script>
  function applyFilters(){var e=[{id:"filterID",columnIndex:0},{id:"status",columnIndex:1},{id:"username",columnIndex:2},{id:"email",columnIndex:3},{id:"Contactnumber",columnIndex:4},{id:"Password",columnIndex:5},{id:"inhandsalary",columnIndex:6},{id:"DateOfJoining",columnIndex:7},{id:"DateOfBirth",columnIndex:8},{id:"uniqueid",columnIndex:9},{id:"EmployeeId",columnIndex:10},{id:"firstamount",columnIndex:11},{id:"scndamount",columnIndex:12},{id:"thirdamount",columnIndex:13},{id:"fourthamount",columnIndex:14},{id:"fifthamount",columnIndex:15},{id:"sixthamount",columnIndex:16},{id:"Projectname",columnIndex:17},{id:"Projecttype",columnIndex:18},{id:"roletype",columnIndex:19},{id:"assignuser",columnIndex:20}],n=0,t=0;$("#incentiveuser tr").each(function(){var i=$(this),l=!0;e.forEach(function(e){var n=$("#"+e.id).val().toLowerCase();if(-1===i.find("td:eq("+e.columnIndex+")").text().toLowerCase().indexOf(n))return l=!1,!1}),l?(i.addClass("custom-filtered-row"),n+=parseFloat(i.find("td:eq(6)").text())||0,t++):i.removeClass("custom-filtered-row")}),$("#totalsalary").text(n),$("#assignednuser").text(t),$("#incentiveuser tr").hide(),applyCustomFilter()}function applyCustomFilter(){$(".custom-filtered-row").show()}$(document).ready(function(){$(".filterable .btn-filter1").click(function(){$("#filterModal").modal("show")}),$("#applyFiltersBtn").click(function(){$("#filterModal").modal("hide"),applyFilters()}),$("#filterModal").on("hidden.bs.modal",function(){$(".filterable .filters input").val(""),applyFilters()}),$("#closeFilter, #cancleFilter").click(function(){applyFilters(),$("#filterModal").modal("hide")}),$("#clearFiltersBtn").click(function(){$("#filterID, #status, #username, #email, #Contactnumber, #Password, #inhandsalary, #DateOfJoining, #DateOfBirth, #uniqueid, #EmployeeId, #firstamount, #scndamount, #thirdamount, #fourthamount, #fifthamount, #sixthamount, #Projectname, #Projecttype, #roletype, #assignuser").val(""),applyFilters(),$("#filterModal").modal("hide")})});
</script>  
  <script>
    $(document).ready(function () {
      $('#scroll-left').on('click', function () {
        $('.table-container').animate({
          scrollLeft: '-=300'
        }, 'ease-in-out');
      });

      $('#scroll-right').on('click', function () {
        $('.table-container').animate({
          scrollLeft: '+=300'
        }, 'ease-in-out');
      });
    });
  </script>
  <script>
    document.addEventListener("DOMContentLoaded", function() {
    var loader = document.getElementById('loader');
    // Show loader initially
    loader.style.opacity = '1';
    loader.style.top = '0';
    loader.style.zIndex = '1002'; // Set initial z-index to 999
    // Hide loader after 5 seconds with smooth transition
    setTimeout(function() {
      loader.style.transition = 'opacity 1s ease, top 1s ease, z-index 1s'; // Add z-index transition
      loader.style.opacity = '0';
      loader.style.top = '-100px'; // Move loader smoothly upward
      loader.style.zIndex = '0'; // Set z-index to 0 when hiding loader
    }, 2000);
  });
  </script>
  <script>
   function debounce(e,t){let l;return function(...s){let n=this;clearTimeout(l),l=setTimeout(()=>e.apply(n,s),t)}}function searchTable(){let e=document.getElementById("searchInput").value.toLowerCase(),t=document.querySelectorAll("tbody");t.forEach(t=>{let l=t.querySelectorAll("tr");l.forEach(t=>{let l=t.innerText.toLowerCase();l.includes(e)?(t.classList.remove("tablehiddenrows"),t.style.display="",setTimeout(()=>{t.classList.remove("tablehiddenrows")},10)):(t.classList.add("tablehiddenrows"),setTimeout(()=>{t.style.display="none"},500))})})}document.getElementById("searchInput").addEventListener("input",debounce(searchTable,300));
  </script>
  <script>
    let selectedUsers=[];function toggleDropdown(){document.getElementById("dropdown").style.display="block"}function selectUser(e,t){selectedUsers.some(t=>t.value===e)||(selectedUsers.push({value:e,label:t}),updateSelectedTags(),updateHiddenInput())}function removeUser(e){selectedUsers=selectedUsers.filter(t=>t.value!==e),updateSelectedTags(),updateHiddenInput()}function updateSelectedTags(){let e=document.getElementById("selected_tags");e.innerHTML="",selectedUsers.forEach(t=>{let s=document.createElement("div");s.className="tag",s.innerHTML=`${t.label}<span class="remove" onclick="removeUser('${t.value}')">&times;</span>`,e.appendChild(s)})}function updateHiddenInput(){let e=selectedUsers.map(e=>e.value);document.getElementById("assign_user_hidden").value=e.join(",")}document.addEventListener("click",function(e){let t=document.getElementById("dropdown"),s=document.querySelector(".custom-multiselect");s.contains(e.target)||(t.style.display="none")});        
  </script>
  <script>
      function filterDropdown() {
            const input = document.getElementById("search_user_input");
            const filter = input.value.toLowerCase();
            const items = document.querySelectorAll("#dropdown .dropdown-item");

            items.forEach(item => {
                const text = item.textContent || item.innerText;
                if (text.toLowerCase().includes(filter)) {
                item.style.display = "";
                } else {
                item.style.display = "none";
                }
            });
        }
  </script>
</body>
</html>