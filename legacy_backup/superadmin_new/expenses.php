<?php
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
// Check if the logged-in user is a superadmin and set the session variable accordingly
if ($nameuser === $nameuser) { // Replace 'your_superadmin_username' with the actual superadmin's username
  $_SESSION['is_superadmin'] = true;
}
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

$sql = "SELECT  username, tablename FROM accounts WHERE user_type IN ('manager', 'teamlead', 'ceo')";
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
  <title>Company Expenses</title>
  <link rel="shortcut icon" type="nobglogo.png" href="../assets/images/nobglogo.png" alt="text">
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/5.0.0-alpha2/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" />
  <link rel="stylesheet" href="../assets/css/style.css" />
  <link rel="stylesheet" href="./assets/css/style1.css" />
  <link rel="stylesheet" href="../assets/css/loader.css"/>
 <style>
    @import url('https://netdna.bootstrapcdn.com/font-awesome/4.0.3/css/font-awesome.css');.fold-content:first-child,.newsec{text-align:left}#emptotaldata,.newsec{width:100%;padding:5px 20px;background-color:#eaf6fd;box-shadow:0 4px 6px rgba(0,0,0,.1)}table.fold-table{background:#fff;color:#555}.table-wrap{position:relative}tbody tr td,tfoot tr td,thead tr th{text-align:center!important;white-space:nowrap}.scroll-left,.scroll-left i,.scroll-right,.scroll-right i{top:50%;transform:translate(-50%,-50%)}.table-container{overflow-y:auto;max-height:55vh;height:100%;width:100%;max-width:76vw}.newsec{border:1px solid #e0e0e0;border-radius:10px}.maintablewrap{overflow-y:scroll;width:100%;max-height:85vh;height:100%;position:relative}.maintablewrap table{width:100%}table{border-collapse:collapse}.maintablewrap::-webkit-scrollbar,.table-container::-webkit-scrollbar{width:5px;height:5px}.maintablewrap::-webkit-scrollbar-track,.table-container::-webkit-scrollbar-track{background:#e3e3e3;border-radius:10px}.maintablewrap::-webkit-scrollbar-thumb,.table-container::-webkit-scrollbar-thumb{background-color:#1b6c9f;border-radius:20px}.table-container table tfoot tr td,.table-container table thead th{padding:5px 12px!important;border:1px solid rgba(0,0,0,.529);font-weight:500!important;color:#1b6c9f!important}.maintablewrap table tfoot tr td,.maintablewrap table thead th{padding:6px 0!important;border:1px solid rgba(0,0,0,.529);font-weight:600!important;color:#f6f5f3!important}.maintablewrap table tfoot,.maintablewrap table thead{position:sticky;background:#1b6c9f;z-index:99}.table-container table tfoot,.table-container table thead{position:sticky;background:#000;z-index:99}.maintablewrap table thead,.table-container thead{top:-2px}.maintablewrap table tfoot,.table-container tfoot{bottom:-2px}.fold-table tbody tr td{font-weight:500;font-size:15px!important;border:1px solid rgba(0,0,0,.1);border-bottom:1px solid rgba(0,0,0,.599);padding:12px 20px!important;color:#000;transform:scale(1)}.small-friendly tbody tr td{padding:7px 12px!important}.fold-table tbody tr:hover{transform:scale(1.001);transition:.3s ease-in-out}.scroll-left,.scroll-right{position:absolute;border-radius:50%;border:1px solid #000;width:35px;height:35px;z-index:99;background-color:#fff}.scroll-left i,.scroll-right i{font-size:23px;color:#1b6c9f;position:absolute;left:50%}.scroll-left{left:20px}.scroll-right{right:-10px}table.fold-table1>tbody>tr.view1,table.fold-table>tbody>tr.view{transition:.3s}.fold-content>table>tbody>tr,table.fold-table1>tbody>tr.view1 th,table.fold-table>tbody>tr.view td,table.fold-table>tbody>tr.view th,table.fold1-table>tbody>tr.view1 td{cursor:pointer}table.fold-table1>tbody>tr.view1 td:first-child,table.fold-table1>tbody>tr.view1 th:first-child,table.fold-table>tbody>tr.view td:first-child,table.fold-table>tbody>tr.view th:first-child{position:relative;padding-left:20px}table.fold-table1>tbody>tr.view1 td:first-child:before,table.fold-table1>tbody>tr.view1 th:first-child:before,table.fold-table>tbody>tr.view td:first-child:before,table.fold-table>tbody>tr.view th:first-child:before{position:absolute;top:50%;left:-15px;width:50px;height:16px;margin-top:-8px;font:20px fontawesome;content:"\f0d7";transition:.3s;color:red}table.fold-table1>tbody>tr.view1:nth-child(4n-1),table.fold-table>tbody>tr.view:nth-child(4n-1){background:#f4f4f4}.fold-content>table>tbody>tr:hover,table.fold-table>tbody>tr.view:hover{background:#ddd}table.fold-table>tbody>tr.view.open{background:#e5e5e6;color:#000}table.fold-table1>tbody>tr.view1.open1 td:first-child:before,table.fold-table1>tbody>tr.view1.open1 th:first-child:before,table.fold-table>tbody>tr.view.open td:first-child:before,table.fold-table>tbody>tr.view.open th:first-child:before{transform:rotate(-180deg);color:#000}.visible-small,table.fold-table1>tbody>tr.fold1,table.fold-table>tbody>tr.fold{display:none}table.fold-table1>tbody>tr.fold1.open1,table.fold-table>tbody>tr.fold.open{display:table-row}.fold-content h3{margin-top:0;font-size:16px}.fold-content>table{box-shadow:0 2px 8px 0 rgba(0,0,0,.2)}.fold-content>table>tbody>tr:nth-child(2n){background:#eee}.visible-big{display:block}.totalbook{display:flex;align-items:center;margin:3px 0}.totalbook .totalbookchild{margin-left:10px}.totalbook .totalbookchild h6{font-size:14px}.divide{margin:-9px 0 0 10px;font-weight:400;font-size:18px;opacity:.6}.monthexp{color:red}.totalbook .totalbookchild:first-child{margin-left:0}.side-menu li.sideactive2{background:var(--shicol);position:relative}.side-menu li.sideactive2 a{color:var(--shicol)}.financialtrsticky{position:sticky;top:0;z-index:99}#emptotaldata{text-align:center;margin:0 auto;border:1px solid #e0e0e0;border-top-right-radius:10px;border-top-left-radius:10px;display:none}#emptotaldata .totalbook{justify-content:center}@media only screen and (max-width:1170px){.newsec,.table-container{width:100%;max-width:100%}}@media only screen and (max-width:768px){#emptotaldata .totalbookhead{font-size:13px}.scroll-right{right:-12px}.scroll-left{left:20px}.maintablewrap table tfoot tr td,.maintablewrap table thead th{padding:8px 12px;font-size:13px}.fold-table tbody tr td{padding:6px 12px!important}table.fold-table>tbody>tr.view td:first-child:before,table.fold-table>tbody>tr.view th:first-child:before{position:absolute;top:50%;left:0;width:20px;height:16px;margin-top:-8px;font:18px fontawesome;content:"\f0d7";transition:.3s;color:red}}@media only screen and (min-width:1600px){.newsec,.table-container{width:100%;max-width:1200px;margin:0 auto}}
    .addBooking{display:none;}
    .side-menu li.sideactive2{
      background: var(--shicol);
    position: relative
  }
  .side-menu li.sideactive2 a{
      color: white
  }
 </style>
  <?php include('header.php'); ?>
  <!-- Main Content -->
  <div class="content">
  <div class="contentinside">
    <div class="container">
      <div class="row">
        <!-- Operation Alert -->
        <div class="col-lg-12">
            <div id="showAlert"></div>
        </div>
        <!-- Operation Alert End-->
        <div class="col-lg-12">
          <div class="maintablewrap">
            <table class="fold-table" cellspacing="0">
              <thead> 
                <tr>
                  <th>Financial Year</th>
                  <th>Facebook Exp.</th>
                  <th>Google Exp.</th>
                  <th>HR Exp.</th>
                  <th>IT Exp.</th>
                  <th>SHI Exp.</th>
                  <th>Accounts Exp.</th>
                  <th>Others Exp.</th>
                </tr>
              </thead>
              <tbody id="expensesdata">

              </tbody>
              <tfoot>
                <tr>
                  <td>Financial Year</td>
                  <td>Facebook Exp.</td>
                  <td>Google Exp.</td>
                  <td>HR Exp.</td>
                  <td>IT Exp.</td>
                  <td>SHI Exp.</td>
                  <td>Accounts Exp.</td>
                  <td>Others Exp.</td>
                </tr>
              </tfoot>
            </table>
            <button class="scroll-left">
              <i class="bx bx-left-arrow-alt"></i>
            </button>
            <button class="scroll-right">
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
  <script type="text/javascript" src="../assets/js/bootstrap_alpha2.js"></script>
  <script type="text/javascript" src="./assets/js/script.js"></script>
<!-- Add New Expenses Modal Start -->
<div class="modal fade" tabindex="-1" id="addNewUserModal">
              <div class="modal-dialog modal-lg modal-dialog-centered">
                  <div class="modal-content">
                      <div class="modal-header">
                          <h5 class="modal-title">Add New Expenses</h5>
                          <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close"></button>
                      </div>
                      <div class="modal-body">
                          <form id="add-user-form" name="myform" class="p-2" method="POST" enctype="multipart/form-data">
                          <div class="container">
                              <div class="row">
                                  <div class="col-md-4 mb-2">
                                    <div class="form-item">
                                      <label for="bdate">Expenses date</label>
                                      <input type="date" name="bdate" id="dateid" class="form-control form-control-lg"
                                          required>
                                      <div class="invalid-feedback">Date is required!</div>
                                  </div>
                              </div>
                                  <div class="col-md-4 mb-2">
                                  <div class="form-item">
                                      <label for="bmonth">Expenses month</label>
                                      <input type="month" name="bmonth" id="monthid" class="form-control form-control-lg"
                                          required>
                                      <div class="invalid-feedback">Month is required!</div>
                                  </div>
                              </div>
                                  <div class="col-md-4 mb-2">
                                  <div class="form-item">
                                      <label for="developer">Employee name</label>
                                      <input type="text" name="developer" class="form-control form-control-lg" required>
                                      <div class="invalid-feedback">Employee name is required!</div>
                                  </div>
                              </div>
                                  <div class="col-md-4 mb-2">
                                  <div class="form-item">
                                      <label for="ExpensesValue">Expenses value</label>
                                      <input type="number" name="cagreement" class="form-control form-control-lg" required>
                                      <div class="invalid-feedback">Agreement Value is required!</div>
                                  </div>
                              </div>
                                  <div class="col-md-4 mb-2">
                                  <div class="form-item">
                                      <label for="Project Name">Project name</label>
                                      <input type="text" name="tproject" class="form-control form-control-lg" required>
                                      <div class="invalid-feedback">Source Project is required!</div>
                                  </div>
                              </div>
                                  <div class="col-md-4 mb-2">
                                  <div class="form-item">
                                      <label for="DeveloperName">Developer Name</label>
                                      <input type="text" name="unitno" class="form-control form-control-lg" required>
                                      <div class="invalid-feedback">Source Project Name is required!</div>
                                  </div>
                              </div>
                              
                              <div class="col-md-4 mb-2">
                                  <div class="form-item">
                                    <label for="cname">Expenses name</label>
                                    <select name="cname" class="form-control form-control-lg">
                                        <option value="">Choose expense type</option>
                                        <option value="Facebook">Facebook</option>
                                        <option value="Google">Google</option>
                                        <option value="IT">Developer</option>
                                        <option value="SHI">SearchHomesIndia</option>
                                        <option value="Accounts">Accounts</option>
                                        <option value="HR">HR</option>
                                        <option value="Others">Others</option>
                                    </select>
                                    <div class="invalid-feedback">Expenses Source is required!</div>
                                </div>
                            </div>
                                  <div class="col-lg-6 mb-2">
                                  <div class="form-item">
                                      <label for="Assign User">Assign User</label>
                                      <select name="assign" id="assign" class="form-control form-control-lg">
                                          <option value="">Assign User</option>
                                          <?php foreach ($assignUsers as $user): ?>
                                              <option value="<?= $user['tablename']; ?>"><?= $user['username']; ?></option>
                                          <?php endforeach; ?>
                                      </select>
                                      <div class="invalid-feedback">Assign User is required!</div>
                                  </div>
                              </div>
                              <div class="col-md-6 mb-2">
                                  <!-- <div class="form-item"> -->
                                    <label for="Upload Invoice">Upload Invoice</label>
                                    <input type="file" name="bproject" class="form-control form-control-lg" required>
                                    <div class="invalid-feedback">Upload Invoice is required!</div>
                                <!-- </div> -->
                            </div>
                                  <div class="col-lg-12">
                                      <div class="Ubsubmitbtn">
                                          <input type="submit" name="add" value="Add Expenses"
                                              class="btn btn-primary btn-block" id="add-user-btn">
                                      </div>
                                  </div>
                              </div>
                          </div>
                          </form>
                      </div>
                  </div>
              </div>
            </div>
<!-- Add New User Modal End -->
<!-- Edit Expenses Modal Start -->
  <div class="modal fade" tabindex="-1" id="editUserModal">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Edit This Expenses</h5>
          <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <form id="edit-user-form" name="myform" class="p-2" novalidate>
            <input type="hidden" name="id" id="id">
            <div class="row mb-3 gx-3">
              <div class="col">
                <input type="date" name="bdate" id="bdate" class="form-control form-control-lg" required>
                <div class="invalid-feedback">Date is required!</div>
              </div>

              <div class="col">
                <input type="month" name="bmonth" id="bmonth" class="form-control form-control-lg" placeholder="Enter Last Name" required>
                <div class="invalid-feedback">Month is required!</div>
              </div>
            </div>

            <div class="row mb-3 gx-3">
              <div class="col">
                <input type="text" name="developer" id="developer" class="form-control form-control-lg" placeholder="Enter Employee Name" required>
                <div class="invalid-feedback">Employee name is required!</div>
              </div>

              <div class="col">
                <input type="number" name="cagreement" id="cagreement" class="form-control form-control-lg" placeholder="Enter Expenses Value" required>
                <div class="invalid-feedback">Agreement Value is required!</div>
              </div>
            </div>

            <div class="row mb-3 gx-3">
              <div class="col">
                <select name="cname" id="cname" class="form-control form-control-lg">
                  <option value="Facebook">Facebook</option>
                  <option value="Google">Google</option>
                  <option value="Developer">Developer</option>
                  <option value="SearchHomesIndia">SearchHomesIndia</option>
                  <option value="Accounts">Accounts</option>
                  <option value="HR">HR</option>
                  <option value="Others">Others</option>
                </select>
                <div class="invalid-feedback">Expenses Source is required!</div>
              </div>

              <div class="col">
                <input type="text" name="tproject" id="tproject" class="form-control form-control-lg" placeholder="Enter Source Project Name" required>
                <div class="invalid-feedback">Source Project is required!</div>
              </div>
            </div>

            <div class="row mb-3 gx-3">
              <div class="col">
                <input type="text" name="unitno" id="unitno" class="form-control form-control-lg" placeholder="Source Project Name" required>
                <div class="invalid-feedback">Source Project Name is required!</div>
              </div>

              <div class="col">
                <input type="file" name="bproject" id="bproject" class="form-control form-control-lg" placeholder="Upload Invoice" required>
                <div class="invalid-feedback">Upload Invoice is required!</div>
              </div>
            </div>

            <div class="mb-3">
              <input type="submit" value="Update Booking" class="btn btn-success btn-block btn-lg" id="edit-user-btn">
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
<!-- Edit User Modal End -->
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
                        <div class="container p-0">
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
                                    <label for="Expenses Date">Expenses Date</label>
                                    <input type="text" class="form-control form-control-lg " id="ExpensesDate">
                                </div>
                            </div>
                                <div class="col-md-4 mb-2">
                                    <div class="form-item">
                                    <label for="Expenses Month">Expenses Month</label>
                                    <input type="text" class="form-control form-control-lg " id="ExpensesMonth">
                                </div>
                            </div>
                                <div class="col-md-4 mb-2">
                                    <div class="form-item">
                                    <label for="UserName">User Name</label>
                                    <input type="text" class="form-control form-control-lg " id="UserName">
                                </div>
                            </div>
                                <div class="col-md-4 mb-2">
                                    <div class="form-item">
                                    <label for="ExpensesAmount">Expenses Amount</label>
                                    <input type="text" class="form-control form-control-lg " id="ExpensesAmount">
                                </div>
                            </div>
                                <div class="col-md-4 mb-2">
                                    <div class="form-item">
                                    <label for="Expenses Source">Expenses Source</label>
                                    <input type="text" class="form-control form-control-lg " id="ExpensesSource">
                                </div>
                            </div>
                                <div class="col-md-4 mb-2">
                                    <div class="form-item">
                                    <label for="Source Project">Source Project</label>
                                    <input type="text" class="form-control form-control-lg " id="SourceProject">
                                </div>
                            </div>
                                <div class="col-md-4 mb-2">
                                    <div class="form-item">
                                    <label for="Project Name">Project Name</label>
                                    <input type="text" class="form-control form-control-lg " id="ProjectName">
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
  <script src="expensesmain.js"></script>
  <script>
    function applyFilters() {
      var filterInputs = [{
          id: "filterID",
          columnIndex: 0
        },
        {
          id: "ExpensesDate",
          columnIndex: 1
        },
        {
          id: "ExpensesMonth",
          columnIndex: 2
        },
        {
          id: "UserName",
          columnIndex: 3
        },
        {
          id: "ExpensesAmount",
          columnIndex: 4
        },
        {
          id: "ExpensesSource",
          columnIndex: 5
        },
        {
          id: "SourceProject",
          columnIndex: 6
        },
        {
          id: "ProjectName",
          columnIndex: 6
        },
      ];
      activeFilters = [];
      $("#expenses tr").each(function() {
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
      $("#expenses tr").hide();
      applyCustomFilter();
    };
    applyCustomFilter();

    function applyCustomFilter() {
      $(".custom-filtered-row").show();
    }
    $(".filterable .btn-filter1").click(function() {
      $("#filterModal").modal("show");
    });
    $("#applyFiltersBtn").click(function() {
      $("#filterModal").modal("hide");
      applyFilters();
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
    $(document).ready(function() {
      $("#clearFiltersBtn").click(function() {
        $("#filterID,#ExpensesDate,#ExpensesMonth,#UserName,#ExpensesAmount,#ExpensesSource,#SourceProject,#ProjectName").val("");
      });
    });
    $("#clearFiltersBtn").click(function() {
      applyFilters();
      $("#filterModal").modal("hide");
    });
  </script>
  <script>
    $(document).ready(function() {
      $('.scroll-left').on('click', function() {
        $('.table-container').animate({
          scrollLeft: '-=300'
        }, 'ease-in-out');
        $('.maintablewrap').animate({
          scrollLeft: '-=300'
        }, 'ease-in-out');
      });
      $('.scroll-right').on('click', function() {
        $('.table-container').animate({
          scrollLeft: '+=300'
        }, 'ease-in-out');
        $('.maintablewrap').animate({
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
    loader.style.zIndex = '999'; // Set initial z-index to 999
    // Hide loader after 5 seconds with smooth transition
    setTimeout(function() {
      loader.style.transition = 'opacity 1s ease, top 1s ease, z-index 1s'; // Add z-index transition
      loader.style.opacity = '0';
      loader.style.top = '-100px'; // Move loader smoothly upward
      loader.style.zIndex = '0'; // Set z-index to 0 when hiding loader
    }, 3000);
  });
  </script>
  <script>
   function debounce(e,t){let l;return function(...s){let n=this;clearTimeout(l),l=setTimeout(()=>e.apply(n,s),t)}}function searchTable(){let e=document.getElementById("searchInput").value.toLowerCase(),t=document.querySelectorAll("tbody");t.forEach(t=>{let l=t.querySelectorAll("tr");l.forEach(t=>{let l=t.innerText.toLowerCase();l.includes(e)?(t.classList.remove("tablehiddenrows"),t.style.display="",setTimeout(()=>{t.classList.remove("tablehiddenrows")},10)):(t.classList.add("tablehiddenrows"),setTimeout(()=>{t.style.display="none"},500))})})}document.getElementById("searchInput").addEventListener("input",debounce(searchTable,300));
  </script>
  </body>
</html>