<?php
session_start(); // Start the session at the beginning

// If the user is not logged in redirect to the login page...
if (!isset($_SESSION['loggedin'])) {
	header('Location: /');
	exit;
}
// // Include database connection
// Check if the user's role is allowed to access this page
$allowed_roles = ['superuseradmin']; // Define allowed roles for this page
$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : ''; // Get user's role from session

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

// $userid = $_SESSION['tablename'];
$id = $_SESSION['id'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="shortcut icon" type="nobglogo.png" href="../assets/images/nobglogo.png" alt="text">
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/5.0.0-alpha2/css/bootstrap.min.css">
  <script src="../../assets/js/bootstrap_alpha2.js"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/css/bootstrap.min.css"/>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Lexend+Deca:wght@100..900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="./assets/css/crm_structure_style.css"/>
  <link rel="stylesheet" href="../assets/css/style1.css"/>
  <link rel="stylesheet" href="../../assets/css/loader.css"/>
  <link rel="stylesheet" href="./assets/css/crm_table_style.css"/>
  <link rel="stylesheet" href="./assets/css/style_dashboard.css"/>  
  
  <style>
    @import url(https://netdna.bootstrapcdn.com/font-awesome/4.0.3/css/font-awesome.css);.divide,.newsec h3{font-size:18px}.fold-content:first-child,.newsec{text-align:left}#emptotaldata,.newsec{width:100%;padding:5px 20px;background-color:#eaf6fd;box-shadow:0 4px 6px rgba(0,0,0,.1)}table.fold-table{background:#fff;color:#555}.table-wrap{position:relative}tbody tr td,tfoot tr td,thead tr th{text-align:center!important;white-space:nowrap}.scroll-left,.scroll-left i,.scroll-right,.scroll-right i{top:50%;transform:translate(-50%,-50%)}.table-container{overflow-y:auto;max-height:55vh;height:100%;width:100%;max-width:76vw;margin:0 auto}.newsec{border:1px solid #e0e0e0;border-radius:10px}.maintablewrap{overflow-y:scroll;width:100%;max-height:85vh;height:100%;border-radius:15px}.maintablewrap table{width:100%}table{border-collapse:collapse}.maintablewrap::-webkit-scrollbar,.table-container::-webkit-scrollbar{width:5px;height:5px}.maintablewrap::-webkit-scrollbar-track,.table-container::-webkit-scrollbar-track{background:#e3e3e3;border-radius:10px}.maintablewrap::-webkit-scrollbar-thumb,.table-container::-webkit-scrollbar-thumb{background-color:#1b6c9f;border-radius:20px}.table-container table tfoot tr td,.table-container table thead th{padding:5px 12px!important;border:1px solid rgba(0,0,0,.529);font-weight:500!important;color:#1b6c9f!important}.maintablewrap table tfoot tr td,.maintablewrap table thead th{padding:6px 0;border:1px solid rgba(0,0,0,.529);font-weight:600!important;color:#f6f5f3!important}.maintablewrap table tfoot,.maintablewrap table thead{position:sticky;background:#1b6c9f;z-index:99}.table-container table tfoot,.table-container table thead{position:sticky;background:#000;z-index:99}.maintablewrap table thead,.table-container thead{top:-2px}.maintablewrap table tfoot,.table-container tfoot{bottom:-2px}.fold-table tbody tr td{font-weight:500;font-size:15px!important;border:1px solid rgba(0,0,0,.1);border-bottom:1px solid rgba(0,0,0,.599);padding:12px 20px!important;color:#000;transform:scale(1)}.small-friendly tbody tr td{padding:7px 12px!important}.fold-table tbody tr:hover{transform:scale(1.001);transition:.3s ease-in-out}.scroll-left,.scroll-right{position:absolute;border-radius:50%;border:1px solid #000;width:35px;height:35px;z-index:99;background-color:#fff}.scroll-left i,.scroll-right i{font-size:23px;color:#1b6c9f;position:absolute;left:50%}.scroll-left{left:20px}.scroll-right{right:-10px}table.fold-table>tbody>tr.view{transition:.3s}.fold-content>table>tbody>tr,table.fold-table>tbody>tr.view td,table.fold-table>tbody>tr.view th{cursor:pointer}table.fold-table>tbody>tr.view td:first-child,table.fold-table>tbody>tr.view th:first-child{position:relative;padding-left:20px}table.fold-table>tbody>tr.view td:first-child:before,table.fold-table>tbody>tr.view th:first-child:before{position:absolute;top:50%;left:5px;width:50px;height:16px;margin-top:-8px;font:20px fontawesome;content:"\f0d7";transition:.3s;color:red}table.fold-table>tbody>tr.view:nth-child(4n-1){background:#f4f4f4}.fold-content>table>tbody>tr:hover,table.fold-table>tbody>tr.view:hover{background:#ddd}table.fold-table>tbody>tr.view.open{background:#e5e5e6;color:#000}table.fold-table>tbody>tr.view.open td:first-child:before,table.fold-table>tbody>tr.view.open th:first-child:before{transform:rotate(-180deg);color:#000}.visible-small,table.fold-table>tbody>tr.fold{display:none}table.fold-table>tbody>tr.fold.open{display:table-row}.fold-content h3{margin-top:0;font-size:16px}.fold-content>table{box-shadow:0 2px 8px 0 rgba(0,0,0,.2)}.fold-content>table>tbody>tr:nth-child(2n){background:#eee}.visible-big{display:block}.totalbook{display:flex;align-items:center;margin:3px 0}.totalbook .totalbookchild{margin-left:10px}.totalbook .totalbookchild h6{font-size:14px}.divide{margin:-9px 0 0 10px;font-weight:400;opacity:.6}.monthexp{color:red}.totalbook .totalbookchild:first-child{margin-left:0}.financialtrsticky{position:sticky;top:0;z-index:99}#emptotaldata{text-align:center;margin:0 auto;border:1px solid #e0e0e0;border-top-right-radius:10px;border-top-left-radius:10px;display:none}#emptotaldata .totalbook{justify-content:center}@media only screen and (max-width:1170px){.newsec,.table-container{width:100%;max-width:100%}}@media only screen and (max-width:768px){#emptotaldata .totalbookhead{font-size:13px}.scroll-right{right:-12px}.scroll-left{left:20px}.maintablewrap table tfoot tr td,.maintablewrap table thead th{padding:8px 12px;font-size:13px}.fold-table tbody tr td{padding:6px 12px!important}table.fold-table>tbody>tr.view td:first-child:before,table.fold-table>tbody>tr.view th:first-child:before{position:absolute;top:50%;left:0;width:20px;height:16px;margin-top:-8px;font:18px fontawesome;content:"\f0d7";transition:.3s;color:red}}@media only screen and (min-width:1600px){.newsec,.table-container{width:100%;max-width:1200px;margin:0 auto}}
    #responseformdata{
      background-color: lightcyan;
    }
     .side-menu li.sideactive45{
      background: var(--shicol);
    position: relative
  }
  .side-menu li.sideactive45 a{
      color: white
  }
  .addNewUserModal{display: none;}
  </style>
<?php include('header.php'); ?>
<!-- Main Content -->
<div class="content">
<!-- Add New EOI Modal Start -->
<div class="modal fade" tabindex="-1" id="addNewEOIModal">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Booking</h5>
                    <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="add-eoi-form" name="myform1" class="p-2" novalidate="">
                    <input type="hidden" class="btn-check Processing" name="cstatus" value="Processing" checked>
                        <div class="container">
                            <div class="row">

                                <div class="col-lg-6 mb-2">
                                    <div class="form-item">
                                        <label for="bdate">Booking date</label>
                                        <input type="date" name="bdate" class="form-control form-control-lg" required="">
                                        <div class="invalid-feedback">Date is required!</div>
                                    </div>
                                </div>
                                <div class="col-lg-6 mb-2">
                                    <div class="form-item">
                                        <label for="bmonth">Booking month</label>
                                        <input type="month" name="bmonth" class="form-control form-control-lg" required="">
                                        <div class="invalid-feedback">Month is required!</div>
                                    </div>
                                </div>
                                <div class="col-lg-6 mb-2">
                                    <div class="form-item">
                                        <label for="developer">Builder name</label>
                                        <input type="text" name="developer" class="form-control form-control-lg" required="">
                                        <div class="invalid-feedback">Builder name is required!</div>
                                    </div>
                                </div>
                                <div class="col-lg-6 mb-2">
                                    <div class="form-item">
                                        <label for="bproject">Project name</label>
                                        <input type="text" name="bproject" class="form-control form-control-lg" required="">
                                        <div class="invalid-feedback">Project name is required!</div>
                                    </div>
                                </div>
                                <div class="col-lg-6 mb-2">
                                    <div class="form-item">
                                        <label for="cname">Customer name</label>
                                        <input type="username" name="cname" class="form-control form-control-lg" required="">
                                        <div class="invalid-feedback">Customer name is required!</div>
                                    </div>
                                </div>
                                <div class="col-lg-6 mb-2">
                                    <div class="form-item">
                                        <label for="cnumber">Contact no.</label>
                                        <input type="number" name="cnumber" class="form-control form-control-lg" required="">
                                        <div class="invalid-feedback">Contact Number is required!</div>
                                    </div>
                                </div>
                                <div class="col-lg-6 mb-2">
                                    <div class="form-item">
                                        <label for="cemail">E-mail</label>
                                        <input type="email" name="cemail" class="form-control form-control-lg" required="">
                                        <div class="invalid-feedback">E-mail is required!</div>
                                    </div>
                                </div>
                                <div class="col-lg-6 mb-2">
                                    <div class="form-item">
                                        <label for="tproject">Project type</label>
                                        <input type="text" name="tproject" class="form-control form-control-lg" required="">
                                        <div class="invalid-feedback">Project Type is required!</div>
                                    </div>
                                </div>
                                <!-- <div class="row"> -->
                                    <div class="col-lg-6 text-center">
                                        <label for="addMoreFields">Converted</label>
                                        <br>
                                        <input type="checkbox" id="toggleFields" name="converted">
                                    </div>
                                    <div class="col-lg-6 text-center">
                                        <label for="cancel">Cancel</label>
                                        <br>
                                        <input type="checkbox" id="canceleoi" name="canceleoi">
                                    </div>
                                <!-- </div> -->
                                <div id="additional-fields" style="display: none;">
                                    <div class="row">
                                <div class="col-lg-6 mb-2">
                                    <div class="form-item">
                                        <label for="unitno">Unit no.</label>
                                        <input type="text" name="unitno" class="form-control form-control-lg">
                                        <div class="invalid-feedback">Unit Number is required!</div>
                                    </div>
                                </div>
                                <div class="col-lg-6 mb-2">
                                    <div class="form-item">
                                        <label for="psize">Project size</label>
                                        <input type="number" name="psize" class="form-control form-control-lg">
                                        <div class="invalid-feedback">Project Size is required!</div>
                                    </div>
                                </div>
                                <div class="col-lg-6 mb-2">
                                    <div class="form-item">
                                        <label for="cagreement">Agreement Value</label>
                                        <input type="number" name="cagreement" id="cagreement-1" class="form-control form-control-lg">
                                        <div class="invalid-feedback">Agreement Value is required!</div>
                                    </div>
                                </div>
                                <div class="col-lg-6 mb-2">
                                    <div class="form-item">
                                        <label for="ccashback">Commission %</label>
                                        <input type="text" name="ccashback" id="ccashback-1" class="form-control form-control-lg" onkeyup="addCalculate(1)">
                                        <div class="invalid-feedback">Commission % is required!</div>
                                    </div>
                                </div>
                                <div class="col-lg-6 mb-2">
                                    <div class="form-item">
                                        <label for="crevenue">Revenue Amount</label>
                                        <input type="number" name="crevenue" id="crevenue-1" class="form-control form-control-lg">
                                        <div class="invalid-feedback">Revenue Amount is required!</div>
                                    </div>
                                </div>
                                <div class="col-lg-6 mb-2">
                                    <div class="form-item">
                                        <label for="cccashback">Cashback %</label>
                                        <input type="text" name="cccashback" id="cccashback-1" class="form-control form-control-lg" onkeyup="addCalculate(1)">
                                        <div class="invalid-feedback">Cashback % is required!</div>
                                    </div>
                                </div>
                                <div class="col-lg-6 mb-2">
                                    <div class="form-item">
                                        <label for="ccrevenue">Revenue Amount</label>
                                        <input type="number" name="ccrevenue" id="ccrevenue-1" class="form-control form-control-lg">
                                        <div class="invalid-feedback">Actual Amount is required!</div>
                                    </div>
                                </div>
                            </div>
                            </div>
                                <div class="col-lg-12">
                                    <div class="Ubsubmitbtn">
                                        <input type="submit" value="Add EOI" class="btn btn-primary btn-block" id="add-eoi-btn">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
<!-- Add New EOI Modal End -->
<!-- Edit EOI Modal Start -->
<div class="modal fade" tabindex="-1" id="editEOIModal">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Booking</h5>
                <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="edit-eoi-form" class="p-2" name="myform2">
                  <input type="hidden" name="id">
                  <input type="hidden" class="btn-check Processing" name="cstatus" value="Processing" checked>
                    <div class="container">
                        <div class="row">
                            <!-- Existing details (Read-only) -->
                            <div class="col-lg-6 mb-2">
                                <label for="bdate">Booking date</label>
                                <input type="date" name="bdate" id="bdate" class="form-control form-control-lg" readonly>
                            </div>
                            <div class="col-lg-6 mb-2">
                                <label for="bmonth">Booking month</label>
                                <input type="month" name="bmonth" id="bmonth" class="form-control form-control-lg" readonly>
                            </div>
                            <div class="col-lg-6 mb-2">
                                <label for="developer">Builder name</label>
                                <input type="text" name="developer" id="developer" class="form-control form-control-lg" readonly>
                            </div>
                            <div class="col-lg-6 mb-2">
                                <label for="bproject">Project name</label>
                                <input type="text" name="bproject" id="bproject" class="form-control form-control-lg" readonly>
                            </div>
                            <div class="col-lg-6 mb-2">
                                <label for="cname">Customer name</label>
                                <input type="text" name="cname" id="cname" class="form-control form-control-lg" readonly>
                            </div>
                            <div class="col-lg-6 mb-2">
                                <label for="cnumber">Contact no.</label>
                                <input type="number" name="cnumber" id="cnumber" class="form-control form-control-lg" readonly>
                            </div>
                            <div class="col-lg-6 mb-2">
                                <label for="cemail">E-mail</label>
                                <input type="email" name="cemail" id="cemail" class="form-control form-control-lg" readonly>
                            </div>
                            <div class="col-lg-6 mb-2">
                                <label for="tproject">Project type</label>
                                <input type="text" name="tproject" id="tproject" class="form-control form-control-lg" readonly>
                            </div>

                            <!-- Checkbox controls -->
                            <div class="col-lg-6 text-center">
                                <label for="converted">Converted</label>
                                <br>
                                <input type="checkbox" id="toggleFields1" name="converted">
                            </div>
                            <div class="col-lg-6 text-center">
                                <label for="cancel">Cancel</label>
                                <br>
                                <input type="checkbox" id="canceleoi" name="canceleoi">
                            </div>

                            <!-- Additional fields for 'Converted' -->
                            <div id="additional-fields1" style="display: none;">
                                <div class="row">
                                    <div class="col-lg-6 mb-2">
                                        <label for="unitno">Unit no.</label>
                                        <input type="text" name="unitno" id="unitno" class="form-control form-control-lg">
                                    </div>
                                    <div class="col-lg-6 mb-2">
                                        <label for="psize">Project size</label>
                                        <input type="number" name="psize" id="psize" class="form-control form-control-lg">
                                    </div>
                                    <div class="col-lg-6 mb-2">
                                        <label for="cagreement">Agreement Value</label>
                                        <input type="number" name="cagreement" id="cagreement-2" class="form-control form-control-lg">
                                    </div>
                                    <div class="col-lg-6 mb-2">
                                        <label for="ccashback">Commission %</label>
                                        <input type="text" name="ccashback" id="ccashback-2" class="form-control form-control-lg" onkeyup="addCalculate(2)">
                                    </div>
                                    <div class="col-lg-6 mb-2">
                                        <label for="crevenue">Revenue Amount</label>
                                        <input type="number" name="crevenue" id="crevenue-2" class="form-control form-control-lg" readonly>
                                    </div>
                                    <div class="col-lg-6 mb-2">
                                      <label for="cccashback">Cashback %</label>
                                      <input type="text" name="cccashback" id="cccashback-2" class="form-control form-control-lg" onkeyup="addCalculate(2)">
                                    </div>
                                    <div class="col-lg-6 mb-2">
                                      <label for="ccrevenue">Revenue Amount</label>
                                      <input type="number" name="ccrevenue" id="ccrevenue-2" class="form-control form-control-lg" readonly>
                                    </div>
                                </div>
                            </div>

                            <!-- Submit button -->
                            <div class="col-lg-12">
                                <button type="submit" class="btn btn-primary btn-block" id="edit-eoi-btn">Update EOI</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<!-- Edit EOI Modal End -->
   <!-- Modal for updating status End -->
<div class="container">
        <div class="row">
            <div id="uploadMessage" class="alert" style="display: none;"></div>
                <div class="dt-layout-row-wrapper" style="padding: 0px 10px!important;">
                    <div class="col-lg-12">
                        <!--<div class="dt-layout-row-top-header">-->
                        <!--    <div class="dt-layout-header">-->
                        <!--        <button class="deactivebutton accessbtn" id="myLeads"><i class="bi bi-graph-up"></i> My Leads (0)</button>-->
                        <!--        <a href="http://localhost/incentiveapp_integration/userlogin/bookings">-->
                        <!--            <button class="assignbutton accessbtn" id="bookedLeads"><i class="bi bi-journal-richtext"></i> Booked (0)</button>-->
                        <!--        </a>-->
                        <!--        <button class="filterbutton accessbtn" id="droppedLeads"><i class="bi bi-droplet"></i>Dropped (0)</button>-->
                        <!--    </div>-->
                        <!--</div>-->
                        <div class="dt-layout-row-top">
                            <div class="dt-layout-row-top-il">
                                <button class="acitvebutton accessbtn" id="activeLeads"><i class="bi bi-activity"></i> Active (0)</button>
                                <button class="assignbutton accessbtn" id="freshLeads"><i class="bi bi-cloud-plus"></i> New (0)</button>
                                <button class="deactivebutton accessbtn" id="pendingLeads"><i class="bi bi-hourglass"></i> Pending (0)</button>
                                <!--<button class="salarybutton accessbtn"><i class="bi bi-card-checklist"></i> EOI (0)</button>-->
                                <!--<button class="uploadbutton accessbtn uploadExcelPopup" data-bs-toggle="modal" data-bs-target="#uploadExcelPopup"><i class="bi bi-cloud-arrow-up"></i>Upload Excel</button>-->
                                <!--<button class="assignmodalbutton accessbtn"  id="assign-button" data-bs-toggle="modal" data-bs-target="#assignModal" disabled><i class="bi bi-people"></i>Assign Users</button>-->
                                <!--<button class="deletebutton accessbtn" id="delete-selected-btn"><i class="bi bi-trash"></i>Delete Selected</button>-->
                                <!--<button class="excelbutton accessbtn" id="download-excel-ex"><i class="bi bi-file-earmark-spreadsheet"></i>Excel Example</button>-->
                                <!--<button class="filterbutton accessbtn"><i class="bi bi-filter"></i>Filter</button>-->
                            </div>
                            <div class="dt-layout-row-top-ir">
                                <div class="rowSelector_wrap">
                                    <span class="dt-layout-row-top-ir-label"><label for="rowSelector">Show Rows:</label></span>
                                    <select id="rowSelector">
                                        <option value="10">10</option>
                                        <option value="50">50</option>
                                        <option value="100">100</option>
                                        <option value="200">200</option>
                                        <option value="300">300</option>
                                        <!-- <option value="500">500</option> -->
                                    </select>
                                </div>
                                <div class="Visibility_dropdown">
                                    <button><i class="bi bi-layout-three-columns"></i><span class="dt-layout-row-top-ir-label">Column Visibility</span></button>
                                    <div class="Visibility_dropdown-content" id="columnSelector"></div>
                                </div>
                                <div class="search">
                                    <input type="text" id="searchInput" class="searchTerm" placeholder="Search...">
                                    <button type="submit" class="searchButton">
                                        <i class="bi bi-search"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-12">
                        <div class="dt-layout-row-mid">
                            <div class="scrollable-table">
                              <div id="responseformdata"></div>
                                <table id="myTable">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>User Eoi</th>
                                            <th>Booking Data</th>
                                            <th>Booking Month</th>
                                            <th>Builder Name</th>
                                            <th>Project Name</th>
                                            <th>Custumer Name</th>
                                            <th>Contact Number</th>
                                            <th>Email</th>
                                            <th>Project Type</th>
                                        </tr>
                                    </thead>
                                    <tbody id="eoiDataFetch">
                                          
                                    </tbody>
                                </table>
                                <div class="overlay"></div>
                            </div>
                        </div>
                    </div>  
                    <div class="col-lg-12 mt-2">
                        <div class="dt-layout-foot">
                            <!-- showing page nos  -->
                            <div id="rowInfo"></div>
                            <!-- pagination div  -->
                            <div class="pagination" id="pagination">
                                <button id="prevButton" disabled><i class="bi bi-arrow-left"></i></button>
                                <span id="pageNumbers"></span>
                                <button id="nextButton" disabled><i class="bi bi-arrow-right"></i></button>
                            </div>
                            <!-- jump on page no div  -->
                            <div id="jumpToPage" class="search">
                                <input type="number" id="jumpToPageInput" class="searchTerm" placeholder="Page No." min="1" />
                                <button id="jumpButton" class="searchButton">Jump</button>
                            </div>
                        </div>
                    </div>
                </div>    
        </div>
    </div>
<!-- Model For main Strucrture end -->
  </div>
  <!--End Main Content -->
  <!-- <script type="text/javascript" src="../assets/js/bootstrap_alpha2.js"></script> -->
  <script type="text/javascript" src="../assets/js/script.js"></script>
  <!-- <script src="main.js"></script> -->
  <script src="calc.js"></script>
  <script>
  function addCalculate(formId) {
    // Use the formId to access the correct input elements
    var agreementValue = parseFloat(document.getElementById("cagreement-" + formId).value) || 0;
    var commissionPercentage = parseFloat(document.getElementById("ccashback-" + formId).value) || 0;
    var cashbackPercentage = parseFloat(document.getElementById("cccashback-" + formId).value) || 0;

    // Calculate Revenue Amount based on Commission Percentage
    var revenueAmount = agreementValue * (commissionPercentage / 100);
    document.getElementById("crevenue-" + formId).value = revenueAmount.toFixed(2);

    // Calculate Revenue After Cashback
    var revenueAfterCashback = revenueAmount - (agreementValue * (cashbackPercentage / 100));
    document.getElementById("ccrevenue-" + formId).value = revenueAfterCashback.toFixed(2);
}
 </script>
  <script>
    $(document).ready(function() {
    // Toggle additional fields based on 'Converted' checkbox in add form
    $('#converted').change(function() {
        $('#additionalFields').toggle(this.checked);
    });

    // Submit form for adding new EOI data
    $('#add-eoi-form').submit(function(event) {
        event.preventDefault();
        $.ajax({
            url: 'eoiaction.php',
            type: 'POST',
            data: $(this).serialize(),
            success: function(response) {
                // Clear any existing timer and display the new response
                $('#responseformdata').stop().show().html(response); // Show and set the new response
                $('#add-eoi-form')[0].reset(); // Reset the form
                $('#additionalFields').hide(); // Hide additional fields if shown
                $('#addNewEOIModal').modal('hide'); // Hide the add modal
                fetchEOIData(); // Reload data table after submission

                // Hide the response message after 5 seconds
                setTimeout(function() {
                    $('#responseformdata').fadeOut('slow');
                }, 5000);
            },
            error: function(xhr, status, error) {
                console.error("Error: " + error);
                $('#responseformdata').stop().show().html("Error occurred while submitting data."); // Show error message
                setTimeout(function() {
                    $('#responseformdata').fadeOut('slow');
                }, 5000);
            }
        });
    });

    // Fetch and load EOI data
    function fetchEOIData() {
        $.ajax({
            url: 'fetch_eoi_data.php', // URL to fetch data rows
            success: function(data) {
                $('#eoiDataFetch').html(data);
            }
        });
    }

    fetchEOIData(); // Initial load of EOI data when the page loads

    // Event listener for dynamically loaded Complete buttons
    $(document).on('click', '.complete-btn', function() {
        const recordId = $(this).data('id');

        // Fetch and populate data for editing
        $.ajax({
            url: `get_record_data.php?id=${recordId}`,
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                // Populate modal with data
                $("input[id='bdate']").val(data.booking_date);
                $("input[id='bmonth']").val(data.booking_month);
                $("input[id='developer']").val(data.builder);
                $("input[id='bproject']").val(data.project);
                $("input[id='cname']").val(data.customer_name);
                $("input[id='cnumber']").val(data.contact_number);
                $("input[id='cemail']").val(data.email_id);
                $("input[id='tproject']").val(data.project_type);

                // Show the modal
                $('#editEOIModal').modal('show');
            }
        });
    });

    // Toggle additional fields based on 'Converted' checkbox in edit form
    $('#toggleFields').change(function() {
        $('#additional-fields').toggle(this.checked);
    });
    $('#toggleFields1').change(function() {
        $('#additional-fields1').toggle(this.checked);
    });

    // Submit form for editing EOI data
    $('#edit-eoi-form').submit(function(event) {
        event.preventDefault();

        const formData = new FormData(this);

        // AJAX request for updating data in the database
        $.ajax({
            url: 'eoiaction.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                // Clear any existing timer and display the new response
                $('#responseformdata').stop().show().html(response); // Show and set the new response
                $('#editEOIModal').modal('hide'); // Hide the edit modal
                fetchEOIData(); // Reload data table to reflect changes

                // Hide the response message after 5 seconds
                setTimeout(function() {
                    $('#responseformdata').fadeOut('slow');
                }, 5000);
            },
            error: function(xhr, status, error) {
                console.error("Error: " + error);
                $('#responseformdata').stop().show().html("Error occurred while updating data."); // Show error message
                setTimeout(function() {
                    $('#responseformdata').fadeOut('slow');
                }, 5000);
            }
        });
    });
});
  </script>
<!-- Addin Actual Revenu as per commission and cashback --> 
<!-- <script>
    function addCalculate(){if(isNaN(document.forms.myform.cagreement.value)||""==document.forms.myform.cagreement.value)var e=0;else var e=parseFloat(document.forms.myform.cagreement.value);if(isNaN(document.forms.myform.ccashback.value)||""==document.forms.myform.ccashback.value)var m=0;else var m=parseFloat(document.forms.myform.ccashback.value);if(document.forms.myform.crevenue.value=parseInt(e*(m/100)),isNaN(document.forms.myform.cccashback.value)||""==document.forms.myform.cccashback.value)var r=0;else var r=parseFloat(document.forms.myform.cccashback.value);if(isNaN(document.forms.myform.crevenue.value)||""==document.forms.myform.crevenue.value)var a=0;else var a=parseInt(document.forms.myform.crevenue.value);document.forms.myform.ccrevenue.value=parseInt(a-e*(r/100))}
</script> -->
<!-- Addin Actual Revenu as per commission and cashback End--> 
<!-- Updating Actual Revenu as per commission and cashback -->
<!-- <script>
 function updateCalculate(){if(isNaN(document.getElementById("cagreement").value)||""==document.getElementById("cagreement").value)var e=0;else var e=parseFloat(document.getElementById("cagreement").value);if(isNaN(document.getElementById("ccashback").value)||""==document.getElementById("ccashback").value)var a=0;else var a=parseFloat(document.getElementById("ccashback").value);if(document.getElementById("crevenue").value=e*(a/100),isNaN(document.getElementById("cccashback").value)||""==document.getElementById("cccashback").value)var l=0;else var l=parseFloat(document.getElementById("cccashback").value);if(isNaN(document.getElementById("crevenue").value)||""==document.getElementById("crevenue").value)var t=0;else var t=parseInt(document.getElementById("crevenue").value);document.getElementById("ccrevenue").value=t-e*(l/100)}
</script> -->
<!-- Updating Actual Revenu as per commission and cashback End-->
  <script>
    // this is loader script 
    document.addEventListener("DOMContentLoaded", function() {
    var loader = document.getElementById('loader');
    // Show loader initially
    loader.style.opacity = '1';
    loader.style.top = '0';
    loader.style.zIndex = '1002'; // Set initial z-index to 999
    setTimeout(function() {
      loader.style.transition = 'opacity 1s ease, top 1s ease, z-index 1s'; // Add z-index transition
      loader.style.opacity = '0';
      loader.style.top = '-100px'; // Move loader smoothly upward
      loader.style.zIndex = '0'; // Set z-index to 0 when hiding loader
      }, 5000);
    });
  // this is loader sciript End 
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
  <!-- Include necessary scripts -->
  <script>
    function debounce(e,t){let l;return function(...s){let n=this;clearTimeout(l),l=setTimeout(()=>e.apply(n,s),t)}}function searchTable(){let e=document.getElementById("searchInput").value.toLowerCase(),t=document.querySelectorAll("tbody");t.forEach(t=>{let l=t.querySelectorAll("tr");l.forEach(t=>{let l=t.innerText.toLowerCase();l.includes(e)?(t.classList.remove("tablehiddenrows"),t.style.display="",setTimeout(()=>{t.classList.remove("tablehiddenrows")},10)):(t.classList.add("tablehiddenrows"),setTimeout(()=>{t.style.display="none"},500))})})}document.getElementById("searchInput").addEventListener("input",debounce(searchTable,300));
  </script>
  <script>
        document.getElementById("toggleFields").addEventListener("change",function(){var e=document.getElementById("additional-fields"),t=e.querySelectorAll("input");this.checked?(e.style.display="block",t.forEach(function(e){"hidden"!==e.type&&e.setAttribute("required","required")})):(e.style.display="none",t.forEach(function(e){e.removeAttribute("required")}))});
        document.getElementById("toggleFields1").addEventListener("change",function(){var e=document.getElementById("additional-fields1"),t=e.querySelectorAll("input");this.checked?(e.style.display="block",t.forEach(function(e){"hidden"!==e.type&&e.setAttribute("required","required")})):(e.style.display="none",t.forEach(function(e){e.removeAttribute("required")}))});
  </script>
</body>
</html>