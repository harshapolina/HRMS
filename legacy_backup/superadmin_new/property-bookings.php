<?php
// We need to use sessions, so you should always start sessions using the below code.
// session_start();
require_once 'action.php';
$counter = $db->printTableRowsCount();
// If the user is not logged in redirect to the login page...
if (!isset($_SESSION['loggedin'])) {
	header('Location: /');
	exit;
}
// Check if the user's role is allowed to access this page
$allowed_roles = ['superuseradmin', 'hradminuser']; // Define allowed roles for this page
$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : ''; // Get user's role from session
$allow_access = isset($_SESSION['allow_access']) ? $_SESSION['allow_access'] : ''; // Get user's access level

// Define restricted directories for "few access" users
$restricted_paths = [
    '/superadmin_new/payment-tracking',
    '/superadmin_new/companyassets',
    '/superadmin_new/accounts',
    // '/superadmin_new/property-bookings',
    '/superadmin_new/expenses',
    '/superadmin_new/incentive-tracking',
    '/hrlogin/createuser/',
    '/superadmin_new/users'
];

// Get the current URL path without query parameters
$current_path = strtok($_SERVER['REQUEST_URI'], '?');

// Allow HR admin ONLY for property-bookings inside superadmin
if ($user_role === 'hradminuser') {

    if ($current_path !== '/superadmin_new/property-bookings') {
        header('Location: access_denied.html');
        exit;
    }

}

// Restrict access based on user role and access permissions
if (
    $user_role !== 'hradminuser' &&
    $allow_access !== 'full access' &&
    in_array($current_path, $restricted_paths)
) {
    error_log("Unauthorized access attempt to $current_path by user role: $user_role");
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
$tablename = $_SESSION['tablename'];

// Check if the logged-in user is a superadmin and set the session variable accordingly
if ($nameuser === $nameuser) { // Replace 'your_superadmin_username' with the actual superadmin's username
  $_SESSION['is_superadmin'] = true;
}
$config = new Config();
$conn = $config->getConnection();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Superadmin database</title>
  <link rel="shortcut icon" type="nobglogo.png" href="../assets/images/nobglogo.png" alt="text">
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/5.0.0-alpha2/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" />
  <link rel="stylesheet" href="../assets/css/style.css" />
  <link rel="stylesheet" href="../assets/css/loader.css"/>
  <link rel="stylesheet" href="./assets/css/style1.css" />
  <style>
      @import url('https://netdna.bootstrapcdn.com/font-awesome/4.0.3/css/font-awesome.css');
    #emptotaldata,.newsec{width:100%;margin:0 auto;padding:5px 20px;background-color:#eaf6fd;box-shadow:0 4px 6px rgba(0,0,0,.1)}table.fold-table{background:#fff;color:#555}.table-wrap{position:relative}tbody tr td,tfoot tr td,thead tr th{text-align:center!important;white-space:nowrap}.scroll-left,.scroll-left i,.scroll-right,.scroll-right i{top:50%;transform:translate(-50%,-50%)}.table-container{overflow-y:auto;max-height:55vh;height:100%;width:100%;max-width:76vw;margin:0 auto}.newsec{max-width:76vw;border:1px solid #e0e0e0;border-radius:10px}.maintablewrap{overflow-y:scroll;width:100%;max-height:85vh;height:100%}.maintablewrap table{width:100%}table{border-collapse:collapse}.maintablewrap::-webkit-scrollbar,.table-container::-webkit-scrollbar{width:5px;height:5px}.maintablewrap::-webkit-scrollbar-track,.table-container::-webkit-scrollbar-track{background:#e3e3e3;border-radius:10px}.maintablewrap::-webkit-scrollbar-thumb,.table-container::-webkit-scrollbar-thumb{background-color:#1b6c9f;border-radius:20px}.table-container table tfoot tr td,.table-container table thead th{padding:5px 12px!important;border:1px solid rgba(0,0,0,.529);font-weight:500!important;color:#1b6c9f!important}.maintablewrap table tfoot tr td,.maintablewrap table thead th{padding:6px 0;border:1px solid rgba(0,0,0,.529);font-weight:600!important;color:#f6f5f3!important}.maintablewrap table tfoot,.maintablewrap table thead{position:sticky;background:#1b6c9f;z-index:99}.table-container table tfoot,.table-container table thead{position:sticky;background:#000;z-index:99}.maintablewrap table thead,.table-container thead{top:-2px}.maintablewrap table tfoot,.table-container tfoot{bottom:-2px}.fold-table tbody tr td{font-weight:500;font-size:15px!important;border:1px solid rgba(0,0,0,.1);border-bottom:1px solid rgba(0,0,0,.599);padding:12px 20px!important;color:#000;transform:scale(1)}.small-friendly tbody tr td{padding:10px 12px!important}.fold-table tbody tr:hover{transform:scale(1.001);transition:.3s ease-in-out}.scroll-left,.scroll-right{position:absolute;border-radius:50%;border:1px solid #000;width:35px;height:35px;z-index:555;background-color:#fff}.scroll-left i,.scroll-right i{font-size:23px;color:#1b6c9f;position:absolute;left:50%}.scroll-left{left:20px}.scroll-right{right:-10px}table.fold-table>tbody>tr.view{transition:.3s}.fold-content>table>tbody>tr,table.fold-table>tbody>tr.view td,table.fold-table>tbody>tr.view th{cursor:pointer}table.fold-table>tbody>tr.view td:first-child,table.fold-table>tbody>tr.view th:first-child{position:relative;padding-left:20px}table.fold-table>tbody>tr.view td:first-child:before,table.fold-table>tbody>tr.view th:first-child:before{position:absolute;top:50%;left:5px;width:50px;height:16px;margin-top:-8px;font:20px fontawesome;content:"\f0d7";transition:.3s;color:red}table.fold-table>tbody>tr.view:nth-child(4n-1){background:#f4f4f4}.fold-content>table>tbody>tr:hover,table.fold-table>tbody>tr.view:hover{background:#ddd}table.fold-table>tbody>tr.view.open{background:#e5e5e6;color:#000}table.fold-table>tbody>tr.view.open td:first-child:before,table.fold-table>tbody>tr.view.open th:first-child:before{transform:rotate(-180deg);color:#000}.visible-small,table.fold-table>tbody>tr.fold{display:none}table.fold-table>tbody>tr.fold.open{display:table-row}.fold-content h3{margin-top:0}.fold-content>table{box-shadow:0 2px 8px 0 rgba(0,0,0,.2)}.fold-content>table>tbody>tr:nth-child(2n){background:#eee}.visible-big{display:block}.fold-content:first-child{text-align:left}.totalbook{display:flex;align-items:center;margin:3px 0}.totalbook .totalbookchild{margin-left:40px}.totalbook .totalbookchild:first-child{margin-left:0}.financialtrsticky{position:sticky;top:0;z-index:150}#emptotaldata{text-align:center;border:1px solid #e0e0e0;border-top-right-radius:10px;border-top-left-radius:10px;display:none}#emptotaldata .totalbook{justify-content:center}@media only screen and (max-width:768px){#emptotaldata .totalbookhead{font-size:13px}.scroll-right{right:-20px}.scroll-left{left:20px}.newsec,.table-container{width:100%;max-width:100%}.maintablewrap table tfoot tr td,.maintablewrap table thead th{padding:6px 12px;font-size:13px}.fold-table tbody tr td{padding:10px 12px!important}}.btn-download {background-color: #4CAF50;color: white;border: none;padding: 3px 2px;border-radius: 20px;cursor: pointer;transition: background-color 0.3s ease, transform 0.2s ease;box-shadow: 0 4px 6px rgba(0, 0, 0, );}
    /* dropDown CSS */
    .dropdown-container{position:relative}#unique_source_table{display:none;border:1px solid #ccc;list-style-type:none;margin:0;padding:0;max-height:200px;overflow-y:auto;position:absolute;width:100%;background-color:#fff;z-index:1000}#unique_source_table li{padding:10px;cursor:pointer}#unique_source_table li:hover{background-color:#f1f1f1}
    /* dropDown CSS End */
     .side-menu li.sideactive1{
      background: var(--shicol);
    position: relative
  }
  .side-menu li.sideactive1 a{
      color: white
  }
  @media only screen and (max-width:1170px) {
    .table-container,
    .newsec {
      width: 100%;
      max-width: 100%;
    }
  }
  .addExpenses{ 
  display: none;
  }.mt-custom {margin-top: 0.3rem !important;}
</style>
  <?php include('header.php'); ?>
<!-- Filter Rows Modal Start -->
    <div class="modal fade" tabindex="-1" id="filterModal">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content custom-modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">FILTER DATA</h5>
                    <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close"
                        id="closeFilter"></button>
                </div>
                <div class="modal-body">
                    <div class="container">
                        <div class="row">
                            <!-- Filter inputs -->
                            <div class="col-md-6 mb-2">
                                <div class="form-item">
                                    <label for="filterBookingDateStart">Booking Date Start</label>
                                    <input type="date" class="form-control form-control-lg custom-input"
                                        id="filterBookingDateStart">
                                </div>
                            </div>
                            <div class="col-md-6 mb-2">
                                <div class="form-item">
                                    <label for="filterBookingDateEnd">Booking Date End</label>
                                    <input type="date" class="form-control form-control-lg custom-input"
                                        id="filterBookingDateEnd">
                                </div>
                            </div>
                            <div class="col-md-6 mb-2">
                                <div class="form-item">
                                    <label for="filterID">ID</label>
                                    <input type="text" class="form-control form-control-lg custom-input" id="filterID">
                                </div>
                            </div>
                            <div class="col-md-6 mb-2">
                                <div class="form-item">
                                    <label for="filterMonth">Month</label>
                                    <input type="text" class="form-control form-control-lg custom-input"
                                        id="filterMonth">
                                </div>
                            </div>
                            <div class="col-md-6 mb-2">
                                <div class="form-item">
                                    <label for="filterBuilder">Builder Name</label>
                                    <input type="text" class="form-control form-control-lg custom-input"
                                        id="filterBuilder">
                                </div>
                            </div>
                            <div class="col-md-6 mb-2">
                                <div class="form-item">
                                    <label for="filterProject">Project Name</label>
                                    <input type="text" class="form-control form-control-lg custom-input"
                                        id="filterProject">
                                </div>
                            </div>
                            <div class="col-md-6 mb-2">
                                <div class="form-item">
                                    <label for="filterCustumername">Customer Name</label>
                                    <input type="text" class="form-control form-control-lg custom-input"
                                        id="filterCustumername">
                                </div>
                            </div>
                            <div class="col-md-6 mb-2">
                                <div class="form-item">
                                    <label for="filterContactnumber">Contact No.</label>
                                    <input type="text" class="form-control form-control-lg custom-input"
                                        id="filterContactnumber">
                                </div>
                            </div>
                            <div class="col-md-6 mb-2">
                                <div class="form-item">
                                    <label for="filterEmail">Email Id</label>
                                    <input type="text" class="form-control form-control-lg custom-input"
                                        id="filterEmail">
                                </div>
                            </div>
                            <div class="col-md-6 mb-2">
                                <div class="form-item">
                                    <label for="filterType">Unit Type</label>
                                    <input type="text" class="form-control form-control-lg custom-input"
                                        id="filterType">
                                </div>
                            </div>
                            <div class="col-md-6 mb-2">
                                <div class="form-item">
                                    <label for="filterUnit">Unit No.</label>
                                    <input type="text" class="form-control form-control-lg custom-input"
                                        id="filterUnit">
                                </div>
                            </div>
                            <div class="col-md-6 mb-2">
                                <div class="form-item">
                                    <label for="filterSize">Unit Size</label>
                                    <input type="text" class="form-control form-control-lg custom-input"
                                        id="filterSize">
                                </div>
                            </div>
                            <div class="col-md-6 mb-2">
                                <div class="form-item">
                                    <label for="filterAgreement">Agreement Value</label>
                                    <input type="text" class="form-control form-control-lg custom-input"
                                        id="filterAgreement">
                                </div>
                            </div>
                            <div class="col-md-6 mb-2">
                                <div class="form-item">
                                    <label for="filterCommission">Commission %</label>
                                    <input type="text" class="form-control form-control-lg custom-input"
                                        id="filterCommission">
                                </div>
                            </div>
                            <div class="col-md-6 mb-2">
                                <div class="form-item">
                                    <label for="filterTrevenue">Total Revenue</label>
                                    <input type="text" class="form-control form-control-lg custom-input"
                                        id="filterTrevenue">
                                </div>
                            </div>
                            <div class="col-md-6 mb-2">
                                <div class="form-item">
                                    <label for="filterCashBack">CashBack %</label>
                                    <input type="text" class="form-control form-control-lg custom-input"
                                        id="filterCashBack">
                                </div>
                            </div>
                            <div class="col-md-6 mb-2">
                                <div class="form-item">
                                    <label for="filterActualRevenue">Actual Revenue</label>
                                    <input type="text" class="form-control form-control-lg custom-input"
                                        id="filterActualRevenue">
                                </div>
                            </div>
                            <div class="col-md-6 mb-2">
                                <div class="form-item">
                                    <label for="filterStatus">Status</label>
                                    <input type="text" class="form-control form-control-lg custom-input"
                                        id="filterStatus">
                                </div>
                            </div>
                            <div class="col-md-6 mb-2">
                                <div class="form-item">
                                    <label for="filterReceived">Received Amt.</label>
                                    <input type="text" class="form-control form-control-lg custom-input"
                                        id="filterReceived">
                                </div>
                            </div>
                            <div class="col-md-6 mb-2">
                                <div class="form-item">
                                    <label for="filterSales">Sales person</label>
                                    <input type="text" class="form-control form-control-lg custom-input"
                                        id="filterSales">
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
<!-- Add New User Modal Start -->
    <div class="modal fade" tabindex="-1" id="addNewUserModal" novalidate enctype="multipart/form-data">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Booking</h5>
                    <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="add-user-form" name="myform" class="p-2" novalidate>
                        <div class="container">
                            <div class="row">

                                <div class="col-lg-6 mb-2">
                                    <div class="form-item">
                                        <label for="bdate">Booking date</label>
                                        <input type="date" name="bdate" id="bdateo" class="form-control form-control-lg" required onclick="this.showPicker()">
                                        <div class="invalid-feedback">Date is required!</div>
                                    </div>
                                </div>
                                <div class="col-lg-6 mb-2">
                                    <div class="form-item">
                                        <label for="bmonth">Booking month</label>
                                        <input type="month" name="bmonth" id="bmontho" class="form-control form-control-lg" required readonly style="cursor: no-drop;">
                                        <div class="invalid-feedback">Month is required!</div>
                                    </div>
                                </div>
                                <div class="col-lg-6 mb-2">
                                  <div class="form-item">
                                      <label for="developer">Builder name</label>
                                      <input list="developera-list" name="developer" id="developera" class="form-control form-control-lg" required>
                                      <datalist id="developera-list"></datalist>
                                      <button type="button" class="btn btn-sm btn-secondary position-absolute end-0 top-0 mt-custom me-2" onclick="addOption('developera')" style="margin-left: 85%;">+</button>
                                      <div class="invalid-feedback">Builder name is required!</div>
                                  </div>
                              </div>
                                <div class="col-lg-6 mb-2">
                                  <div class="form-item">
                                      <label for="bproject">Project name</label>
                                      <input list="bprojecta-list" name="bproject" id="bprojecta" class="form-control form-control-lg" required>
                                      <datalist id="bprojecta-list"></datalist>
                                      <button type="button" class="btn btn-sm btn-secondary position-absolute end-0 top-0 mt-custom me-2" onclick="addOption('bprojecta')" style="margin-left: 85%;">+</button>
                                      <div class="invalid-feedback">Project name is required!</div>
                                  </div>
                              </div>
                                <div class="col-lg-6 mb-2">
                                    <div class="form-item">
                                        <label for="cname">Customer name</label>
                                        <input type="username" name="cname" class="form-control form-control-lg"
                                            required>
                                        <div class="invalid-feedback">Customer name is required!</div>
                                    </div>
                                </div>
                                <!-- Contact Number Field -->
                                <div class="col-lg-6 mb-2">
                                    <div class="form-item">
                                        <label for="cnumber">Contact no.</label>
                                        <div class="input-group">
                                        <input type="text" name="cnumber" class="form-control form-control-lg" required>
                                        <div class="invalid-feedback">Contact Number is required!</div>
                                        <button class="btn btn-outline-primary" type="button" onclick="addNumber()">
                                            <i class="bi bi-plus"></i>
                                        </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Email Field -->
                                <div class="col-lg-6 mb-2">
                                    <div class="form-item">
                                        <label for="cemail">E-mail</label>
                                        <div class="input-group">
                                        <input type="text" name="cemail" class="form-control form-control-lg" required>
                                        <div class="invalid-feedback">E-mail is required!</div>
                                        <button class="btn btn-outline-primary" type="button" onclick="addEmail()">
                                            <i class="bi bi-plus"></i>
                                        </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-6 mb-2">
                                    <div class="form-item">
                                        <label for="tproject">Project type</label>
                                        <input list="tprojecta-list" name="tproject" id="tprojecta" class="form-control form-control-lg" required>
                                        <datalist id="tprojecta-list"></datalist>
                                        <button type="button" class="btn btn-sm btn-secondary position-absolute end-0 top-0 mt-custom me-2" onclick="addOption('tprojecta')" style="margin-left: 85%;">+</button>
                                        <div class="invalid-feedback">Project Type is required!</div>
                                    </div>
                                </div>
                                <div class="col-lg-6 mb-2">
                                    <div class="form-item">
                                        <label for="unitno">Unit no.</label>
                                        <input type="text" name="unitno" class="form-control form-control-lg" required>
                                        <div class="invalid-feedback">Unit Number is required!</div>
                                    </div>
                                </div>
                                <div class="col-lg-6 mb-2">
                                    <div class="form-item">
                                        <label for="psize">Project size</label>
                                        <input type="number" name="psize" class="form-control form-control-lg" required>
                                        <div class="invalid-feedback">Project Size is required!</div>
                                    </div>
                                </div>
                                <div class="col-lg-6 mb-2">
                                    <div class="form-item">
                                        <label for="cagreement">Agreement Value</label>
                                        <input type="number" name="cagreement" class="form-control form-control-lg"
                                            required>
                                        <div class="invalid-feedback">Agreement Value is required!</div>
                                    </div>
                                </div>
                                <div class="col-lg-6 mb-2">
                                    <div class="form-item">
                                        <label for="ccashback">Commission %</label>
                                        <input type="text" name="ccashback" class="form-control form-control-lg"
                                            onkeyup="addCalculate(this.value)" required>
                                        <div class="invalid-feedback">Commission % is required!</div>
                                    </div>
                                </div>
                                <div class="col-lg-6 mb-2">
                                    <div class="form-item">
                                        <label for="crevenue">Revenue Amount</label>
                                        <input type="number" name="crevenue" class="form-control form-control-lg"
                                            required>
                                        <div class="invalid-feedback">Revenue Amount is required!</div>
                                    </div>
                                </div>
                                <div class="col-lg-6 mb-2">
                                    <div class="form-item">
                                        <label for="cccashback">Cashback %</label>
                                        <input type="text" name="cccashback" class="form-control form-control-lg"
                                            onkeyup="addCalculate(this.value)" required>
                                        <div class="invalid-feedback">Cashback % is required!</div>
                                    </div>
                                </div>
                                <div class="col-lg-6 mb-2">
                                    <div class="form-item">
                                        <label for="ccrevenue">Revenue Amount</label>
                                        <input type="number" name="ccrevenue" class="form-control form-control-lg"
                                            required>
                                        <div class="invalid-feedback">Actual Amount is required!</div>
                                    </div>
                                </div>
                                <div class="col-lg-6 mb-2">
                                    <div class="form-item">
                                        <label for="brecived">Received Amt.</label>
                                        <input type="number" name="brecived" class="form-control form-control-lg">
                                        <div class="invalid-feedback">Enter Received Amt. is required!</div>
                                    </div>
                                </div>
                                <div class="col-lg-12">
                                    <div class="btnwraps">
                                        <input type="checkbox" class="btn-check Processing" name="cstatus"
                                            id="btn-check-2-outlined" value="Processing" checked>
                                        <label class="btn btn-outline-primary bttm-btn"
                                            for="btn-check-2-outlined">Processing</label>

                                        <input type="radio" class="btn-check Received" name="cstatus"
                                            id="success-outlined" value="Received">
                                        <label class="btn btn-outline-success bttm-btn"
                                            for="success-outlined">Received</label>

                                        <input type="radio" class="btn-check Cancled" name="cstatus"
                                            id="danger-outlined" value="Cancled">
                                        <label class="btn btn-outline-danger bttm-btn"
                                            for="danger-outlined">Cancled</label>
                                        <div class="invalid-feedback">Status is required!</div>
                                    </div>
                                </div>
                                <div class="col-lg-12 mb-2">
                                  <div class="form-item">
                                      <label for="SourceProject">Lead Source</label>
                                      <select name="leadsource" id="leadsource" class="form-control form-control-lg" required>
                                          <option value="">Select Source</option>
                                          <option value="Google Ads">Google</option>
                                          <option value="Facebook Ads">Facebook</option>
                                          <option value="Direct">Direct</option>
                                          <option value="Referral">Referral</option>
                                          <option value="Portal">Portal</option>
                                          <option value="WhatsApp">WhatsApp</option>
                                      </select>
                                      <div class="invalid-feedback">Lead Source is required!</div>
                                  </div>
                                </div>
                                <!-- New File Upload Field -->
                                <div class="col-lg-12 mb-2">
                                    <div class="form-item">
                                        <label for="document">Upload B-Form</label>
                                        <input type="file" name="document" class="form-control form-control-lg" style="padding: 10px;">
                                        <div class="invalid-feedback">Please upload a document!</div>
                                    </div>
                                </div>
                                <div class="col-lg-12 mb-2">
                                    <div class="form-item">
                                        <label for="bremarks">Remarks (Optional)</label>
                                        <input type="text" name="bremarks" id="bremarks" class="form-control form-control-lg">
                                    </div>
                                </div>
                                <div class="col-lg-12">
                                    <div class="Ubsubmitbtn">
                                        <input type="submit" value="Add Booking" class="btn btn-primary btn-block"
                                            id="add-user-btn">
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
<!-- Edit User Modal Start -->
    <div class="modal fade" tabindex="-1" id="editUserModal">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit This Booking</h5>
                    <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
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
                                                // Fetch users from the database
                                                $userQuery = "SELECT tablename FROM accounts"; // Assuming a users table
                                                $userStmt = $conn->prepare($userQuery);
                                                $userStmt->execute();
                                                $users = $userStmt->fetchAll(PDO::FETCH_ASSOC);
                                                
                                                foreach ($users as $user) {
                                                    echo "<li class='unique-option' data-value='{$user['tablename']}'>{$user['tablename']}</li>";
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
                                        <input type="number" name="cnumber" id="cnumber"
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
                                        <input type="checkbox" class="btn-check Processing" name="cstatus"
                                            id="btn-check-3-outlined" value="Processing" required>
                                        <label class="btn btn-outline-primary bttm-btn" for="btn-check-3-outlined"
                                            style="margin-left: 0px;">Processing</label>

                                        <input type="radio" class="btn-check Received" name="cstatus"
                                            id="2success-outlined" value="Received" required>
                                        <label class="btn btn-outline-success bttm-btn"
                                            for="2success-outlined">Received</label>

                                        <input type="radio" class="btn-check Cancled" name="cstatus"
                                            id="2danger-outlined" value="Cancled" required>
                                        <label class="btn btn-outline-danger bttm-btn"
                                            for="2danger-outlined">Cancled</label>
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
<!-- Add Name Model Start -->
    <div class="modal fade" id="addOptionModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form id="optionForm">
          <div class="modal-header">
            <h5 class="modal-title">Add New Option</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" id="optionType" name="type">
            <div class="mb-3">
              <label for="newOptionValue" class="form-label">Enter new value</label>
              <input type="text" id="newOptionValue" name="value" class="form-control" required>
            </div>
          </div>
          <div class="modal-footer">
            <button type="submit" class="btn btn-primary">Save</button>
          </div>
        </form>
      </div>
    </div>
  </div>
<!-- Add Name Model End -->
<!-- Main Content -->
  <div class="content">
  <div class="contentinside">
    <div class="container">
      <div class="row">
        <!-- This is for filter data show and hide -->
        <div class="col-lg-12">
          <div id="emptotaldata">
            <h4>Financial Year - 2023/2024</h4>
            <div class="totalbook">
              <div class="totalbookchild">
                <h6>Total Bookings :- <span id="counter">0.00</span></h6>
              </div>
              <div class="totalbookchild">
                <h6>Total Revenue :- ₹<span id="totalTotalRevenue">0.00</span></h6>
              </div>
              <div class="totalbookchild">
                <h6>Actual Revenue :- ₹<span id="totalActualRevenue">0.00</span></h6>
              </div>
            </div>
          </div>
        </div>
        <!-- This is for filter data show and hide End -->
        <!-- Operation Alert -->
        <div class="col-lg-12">
          <div id="showAlert"></div>
        </div>
        <!-- Operation Alert End-->
        <div class="col-lg-12">
          <div class="maintablewrap">
            <table class="fold-table" cellspacing="0" style="width: 100%">
              <thead>
                <tr>
                    <th>Year/Bookings/Invoice</th>
                    <th>Total Revenue</th>
                    <th>Actual Revenue</th>
                    <th>Remaning Revenue</th>
                    <th>Received Amount</th>
                    <th>Paid Salary</th>
                    <th>Expenses</th>
                    <th>Amount To be Pay</th>
                    <th>Total Paid Amt</th>
                  </tr>
                </thead>
                <tbody id="pagedata">
                  <!-- Here Year Row will be populate dynamically -->
                  <!-- Here Year Row will be populate dynamically with new table -->
                </tbody>
                <tfoot>
                  <tr>
                    <td>Year/Bookings/Invoice</td>
                    <td>Total Revenue</td>
                    <td>Actual Revenue</td>
                    <td>Remaning Revenue</td>
                    <td>Received Amount</td>
                    <td>Paid Salary</td>
                    <td>Expenses</td>
                    <td>Amount To be Pay</td>
                    <td>Total Paid Amt</td>
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
  <!-- <script src="https://stackpath.bootstrapcdn.com/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script> -->
  <script type="text/javascript" src="./assets/js/script.js"></script>
  <script type="text/javascript" src="main.js"></script>
  <script src="calc.js"></script>
  <script>
   document.addEventListener('DOMContentLoaded', function() {
        var searchInput = document.getElementById('unique_searchInput');
        var dropdown = document.getElementById('unique_source_table');
        var options = dropdown.getElementsByTagName('li');
        var hiddenInput = document.getElementById('source_table');
        var labelElement = document.getElementById('selected_user_label'); // Element where selected user will be displayed

        // Show dropdown when the input field is clicked
        searchInput.addEventListener('focus', function() {
            dropdown.style.display = 'block'; // Show dropdown when input is focused
        });

        // Filter functionality for search input
        searchInput.addEventListener('keyup', function() {
            var filter = searchInput.value.toLowerCase();
            for (var i = 0; i < options.length; i++) {
                var optionText = options[i].innerText.toLowerCase();
                if (optionText.indexOf(filter) > -1) {
                    options[i].style.display = ""; // Show matching options
                } else {
                    options[i].style.display = "none"; // Hide non-matching options
                }
            }
        });

        // Option selection logic
        dropdown.addEventListener('click', function(event) {
            if (event.target && event.target.tagName === 'LI') {
                searchInput.value = event.target.innerText; // Update input with selected option
                hiddenInput.value = event.target.dataset.value; // Update hidden input with selected value
                labelElement.innerHTML = event.target.innerText; // Update the label beside "Assigned User"
                dropdown.style.display = "none"; // Hide dropdown after selection
            }
        });

        // Prevent the dropdown from closing immediately on click
        searchInput.addEventListener('click', function(e) {
            e.stopPropagation(); // Prevent the event from propagating to the document
            dropdown.style.display = 'block'; // Keep the dropdown open
        });

        // Close dropdown if clicking outside of it
        document.addEventListener('click', function(event) {
            if (!dropdown.contains(event.target) && event.target !== searchInput) {
                dropdown.style.display = 'none'; // Hide dropdown if clicking outside
            }
        });
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
    // Get references to the input elements
    const ccrevenueInput = document.getElementById('ccrevenue');
    const brecivedInput = document.getElementById('brecived');
    const invoiceRaisedInput = document.getElementById('invoice_raised');
    const receivedRadio = document.getElementById('2success-outlined');
    const invoiceRadio = document.getElementById('update_invoice_checkbox');
    // Add event listeners to the radio buttons
    receivedRadio.addEventListener('click', function() {
        brecivedInput.value = ccrevenueInput.value;
    });
    invoiceRadio.addEventListener('click', function() {
        invoice_raised.value = ccrevenueInput.value;
    });
</script>

<script>
    function validateForm() {
        var processingChecked = document.getElementById('btn-check-3-outlined').checked;
        var receivedChecked = document.getElementById('2success-outlined').checked;
        var canceledChecked = document.getElementById('2danger-outlined').checked;

        if (!(processingChecked || receivedChecked || canceledChecked)) {
            document.querySelector('.invalid-feedback').style.display = 'block';
        } else {
            document.querySelector('.invalid-feedback').style.display = 'none';

            // Remove "required" attribute from the remaining buttons
            var checkboxes = document.querySelectorAll('.btn-check.Processing');
            var radios = document.querySelectorAll('.btn-check.Received, .btn-check.Cancled');

            if (processingChecked) {
                // If Processing is selected, remove "required" from radios
                radios.forEach(function (radio) {
                    radio.removeAttribute('required');
                });
            } else {
                // If Processing is not selected, remove "required" from checkboxes
                checkboxes.forEach(function (checkbox) {
                    checkbox.removeAttribute('required');
                });
            }
        }
    }
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
  <script>
        function addOption(type) {
            document.getElementById('optionType').value = type;
            document.getElementById('newOptionValue').value = '';
            const modal = new bootstrap.Modal(document.getElementById('addOptionModal'));
            modal.show();
        }

        document.getElementById('optionForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const type = document.getElementById('optionType').value;
        const value = document.getElementById('newOptionValue').value.trim();

        if (value !== '') {
            fetch('options_handler.php?action=save', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `type=${encodeURIComponent(type)}&value=${encodeURIComponent(value)}`
            })
            .then(response => response.text())
            .then(() => {
            loadDatalist(type); // reload the datalist
            bootstrap.Modal.getInstance(document.getElementById('addOptionModal')).hide();
            });
        }
        });

        function loadDatalist(type) {
        fetch(`options_handler.php?action=get&type=${type}`)
            .then(res => res.json())
            .then(options => {
            const list = document.getElementById(`${type}-list`);
            list.innerHTML = '';
            options.forEach(val => {
                const opt = document.createElement('option');
                opt.value = val;
                list.appendChild(opt);
            });
            });
        }

        // Load all datalists initially
        ['developera', 'bprojecta', 'tprojecta'].forEach(loadDatalist);
    </script>
    <script>
        document.getElementById('bdateo').addEventListener('change', function() {
            let selectedDate = this.value; // Get the selected date in YYYY-MM-DD format
            if (selectedDate) {
                let dateObj = new Date(selectedDate);
                let month = (dateObj.getMonth() + 1).toString().padStart(2, '0'); // Two-digit month
                let year = dateObj.getFullYear();
                document.getElementById('bmontho').value = `${year}-${month}`; // Set MM-YYYY format
            }
        });
    </script>
    <script>
    function addNumber() {
        let newNumber = prompt("Enter another contact number:");
        if (newNumber && newNumber.trim() !== '') {
        let input = document.querySelector('[name="cnumber"]');
        input.value = input.value ? input.value + ', ' + newNumber.trim() : newNumber.trim();
        }
    }

    function addEmail() {
        let newEmail = prompt("Enter another email:");
        if (newEmail && newEmail.trim() !== '') {
        let input = document.querySelector('[name="cemail"]');
        input.value = input.value ? input.value + ', ' + newEmail.trim() : newEmail.trim();
        }
    }
    </script>
    <script>
        function addEmail() {
            let newEmail = prompt("Enter another email:");
            if (newEmail && newEmail.trim() !== '') {
            if (validateEmail(newEmail.trim())) {
                let input = document.querySelector('[name="cemail"]');
                input.value = input.value ? input.value + ', ' + newEmail.trim() : newEmail.trim();
            } else {
                alert("Invalid email format!");
            }
            }
        }

        function validateEmail(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        }
    </script>
  </body>
</html>