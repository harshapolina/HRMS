<?php
session_start(); // Start the session at the beginning

// If the user is not logged in redirect to the login page...
if (!isset($_SESSION['loggedin'])) {
	header('Location: /');
	exit;
}
// // Include database connection
// Check if the user's role is allowed to access this page
$allowed_roles = ['regularuser']; // Define allowed roles for this page
$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : ''; // Get user's role from session

if (!in_array($user_role, $allowed_roles)) {
    // User's role is not allowed, redirect to an error page or homepage
    header('Location: access_denied.html'); // Redirect to an error page
    exit;
}

$nameuser = $_SESSION['username'];
$userid = $_SESSION['tablename'];
$Project_type = $_SESSION['project_type'];
$user_type = $_SESSION['user_type'];
$id = $_SESSION['id'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Your Bookings<?php $nameuser ?></title>
  <link rel="shortcut icon" type="nobglogo.png" href="../assets/images/nobglogo.png" alt="text">
  <link rel="stylesheet" href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" />
  <link rel="stylesheet" href="../assets/css/bootstrap5.3.2.css"/>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <link rel="stylesheet" href="../assets/css/style.css"/>
  <link rel="stylesheet" href="../assets/css/loader.css"/>
  <link rel="stylesheet" href="./assets/css/style1.css"/>
  <link rel="stylesheet" href="./assets/css/crm_structure_style.css"/>
  <link rel="stylesheet" href="./assets/css/crm_table_style.css"/>
  <link rel="stylesheet" href="./assets/css/style_dashboard.css"/> 
  
  <style>
    @import url('https://netdna.bootstrapcdn.com/font-awesome/4.0.3/css/font-awesome.css');
    #emptotaldata,.newsec{width:100%;margin:0 auto;padding:5px 20px;background-color:#eaf6fd;box-shadow:0 4px 6px rgba(0,0,0,.1)}table.fold-table{background:#fff;color:#555}.table-wrap{position:relative}tbody tr td,tfoot tr td,thead tr th{text-align:center!important;white-space:nowrap}.scroll-left,.scroll-left i,.scroll-right,.scroll-right i{top:50%;transform:translate(-50%,-50%)}.table-container{overflow-y:auto;max-height:55vh;height:100%;width:100%;max-width:76vw;margin:0 auto}.newsec{max-width:76vw;border:1px solid #e0e0e0;border-radius:10px}.maintablewrap{overflow-y:scroll;width:100%;max-height:85vh;height:100%}.maintablewrap table{width:100%}table{border-collapse:collapse}.maintablewrap::-webkit-scrollbar,.table-container::-webkit-scrollbar{width:5px;height:5px}.maintablewrap::-webkit-scrollbar-track,.table-container::-webkit-scrollbar-track{background:#e3e3e3;border-radius:10px}.maintablewrap::-webkit-scrollbar-thumb,.table-container::-webkit-scrollbar-thumb{background-color:#1b6c9f;border-radius:20px}.table-container table tfoot tr td,.table-container table thead th{padding:5px 12px!important;border:1px solid rgba(0,0,0,.529);font-weight:500!important;color:#1b6c9f!important}.maintablewrap table tfoot tr td,.maintablewrap table thead th{padding:6px 0;border:1px solid rgba(0,0,0,.529);font-weight:600!important;color:#f6f5f3!important}.maintablewrap table tfoot,.maintablewrap table thead{position:sticky;background:#1b6c9f;z-index:1}.table-container table tfoot,.table-container table thead{position:sticky;background:#000;z-index:99}.maintablewrap table thead,.table-container thead{top:-2px}.maintablewrap table tfoot,.table-container tfoot{bottom:-2px}.fold-table tbody tr td{font-weight:500;font-size:15px!important;border:1px solid rgba(0,0,0,.1);border-bottom:1px solid rgba(0,0,0,.599);padding:12px 20px!important;color:#000;transform:scale(1)}.small-friendly tbody tr td{padding:10px 12px!important}.fold-table tbody tr:hover{transform:scale(1.001);transition:.3s ease-in-out}.scroll-left,.scroll-right{position:absolute;border-radius:50%;border:1px solid #000;width:35px;height:35px;z-index:555;background-color:#fff}.scroll-left i,.scroll-right i{font-size:23px;color:#1b6c9f;position:absolute;left:50%}.scroll-left{left:20px}.scroll-right{right:-10px}table.fold-table>tbody>tr.view{transition:.3s}.fold-content>table>tbody>tr,table.fold-table>tbody>tr.view td,table.fold-table>tbody>tr.view th{cursor:pointer}table.fold-table>tbody>tr.view td:first-child,table.fold-table>tbody>tr.view th:first-child{position:relative;padding-left:20px}table.fold-table>tbody>tr.view td:first-child:before,table.fold-table>tbody>tr.view th:first-child:before{position:absolute;top:50%;left:5px;width:50px;height:16px;margin-top:-8px;font:20px fontawesome;content:"\f0d7";transition:.3s;color:red}table.fold-table>tbody>tr.view:nth-child(4n-1){background:#f4f4f4}.fold-content>table>tbody>tr:hover,table.fold-table>tbody>tr.view:hover{background:#ddd}table.fold-table>tbody>tr.view.open{background:#e5e5e6;color:#000}table.fold-table>tbody>tr.view.open td:first-child:before,table.fold-table>tbody>tr.view.open th:first-child:before{transform:rotate(-180deg);color:#000}.visible-small,table.fold-table>tbody>tr.fold{display:none}table.fold-table>tbody>tr.fold.open{display:table-row}.fold-content h3{margin-top:0}.fold-content>table{box-shadow:0 2px 8px 0 rgba(0,0,0,.2)}.fold-content>table>tbody>tr:nth-child(2n){background:#eee}.visible-big{display:block}.fold-content:first-child{text-align:left}.totalbook{display:flex;align-items:center;margin:3px 0}.totalbook .totalbookchild{margin-left:40px}.totalbook .totalbookchild:first-child{margin-left:0}.financialtrsticky{position:sticky;top:0;z-index:150}#emptotaldata{text-align:center;border:1px solid #e0e0e0;border-top-right-radius:10px;border-top-left-radius:10px;display:none}#emptotaldata .totalbook{justify-content:center}@media only screen and (max-width:768px){#emptotaldata .totalbookhead{font-size:13px}.scroll-right{right:-20px}.scroll-left{left:20px}.newsec,.table-container{width:100%;max-width:100%}.maintablewrap table tfoot tr td,.maintablewrap table thead th{padding:6px 12px;font-size:13px}.fold-table tbody tr td{padding:10px 12px!important}}
     .side-menu li.sideactive1{
      background: var(--shicol);
    position: relative
  }
  .side-menu li.sideactive1 a{
      color: white
  }
  .addNewEOIModal{display:none}
  </style>
<?php include('header.php'); ?>
<!-- Main Content -->
<div class="content">
<!-- Filter Start -->
  <div class="modal fade" tabindex="-1" id="filterModal">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Filter Data</h5>
          <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close" id="closeFilter"></button>
        </div>
        <div class="modal-body">
          <div class="container p-0">
            <div class="row">
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
              <!-- Filter inputs -->
              <div class="col-lg-6 mb-2">
                <div class="form-item">
                  <label for="ID">ID</label>
                  <input type="text" class="form-control form-control-lg" id="filterID">
                </div>
              </div>

                <div class="col-lg-6 mb-2">
                <div class="form-item">
                <label for="Month">Month</label>
                  <input type="text" class="form-control form-control-lg" id="filterMonth">
                </div>
              </div>

                <div class="col-lg-6 mb-2">
                <div class="form-item">
                <label for="Builder">Builder</label>
                  <input type="text" class="form-control form-control-lg" id="filterBuilder">
                </div>
              </div>

              <div class="col-lg-6 mb-2">
                <div class="form-item">
                    <label for="Project">Project</label>
                    <input type="text" class="form-control form-control-lg" id="filterProject">
                </div>
              </div>

              <div class="col-lg-6 mb-2">
                <div class="form-item">
                  <label for="Custumername">Custumer name</label>
                  <input type="text" class="form-control form-control-lg" id="filterCustumername">
                </div>
              </div>
              <div class="col-lg-6 mb-2">
                <div class="form-item">
                  <label for="Contactnumber">Contact no.</label>
                  <input type="text" class="form-control form-control-lg" id="filterContactnumber">
                  </div>
              </div>

              <div class="col-lg-6 mb-2">
                <div class="form-item">
                  <label for="Email">Email</label>
                  <input type="text" class="form-control form-control-lg" id="filterEmail" >
                  </div>
              </div>

              <div class="col-lg-6 mb-2">
                <div class="form-item">
                  <label for="Type">Type</label>
                  <input type="text" class="form-control form-control-lg" id="filterType">
                  </div>
              </div>

              <div class="col-lg-6 mb-2">
                <div class="form-item">
                  <label for="Unit">Unit</label>
                  <input type="text" class="form-control form-control-lg" id="filterUnit">
                  </div>
              </div>

              <div class="col-lg-6 mb-2">
                <div class="form-item">
                  <label for="Size">Size</label>
                  <input type="text" class="form-control form-control-lg" id="filterSize">
                  </div>
              </div>

              <div class="col-lg-6 mb-2">
                <div class="form-item">
                    <label for="Agreement">Agreement</label>
                    <input type="text" class="form-control form-control-lg" id="filterAgreement">
                  </div>
              </div>
              <?php if ($Project_type === 'mandate'): ?>
                <!-- <td>Commission %</td> -->
              <?php else: ?>
              <div class="col-lg-6 mb-2">
                <div class="form-item">
                    <label for="Commission">Commission</label>
                    <input type="text" class="form-control form-control-lg" id="filterCommission">
                    </div>
              </div>
              <div class="col-lg-6 mb-2">
                <div class="form-item">
                    <label for="revenue">Revenue</label>
                    <input type="text" class="form-control form-control-lg" id="filterTrevenue">
                </div>
              </div>
              <?php endif; ?>
              <div class="col-lg-6 mb-2">
                <div class="form-item">
                    <label for="CashBack">CashBack</label>
                    <input type="text" class="form-control form-control-lg" id="filterCashBack" >
                    <?php if ($Project_type === 'mandate'): ?>
                      <!-- <td>Commission %</td> -->
                    <?php else: ?>
                      <label for="ActualRevenue">ActualRevenue</label>
                      <input type="text" class="form-control form-control-lg" id="filterActualRevenue" >
                    <?php endif; ?>
                </div>
              </div>
              <?php if ($Project_type === 'mandate'): ?>
                  <!-- <td>Commission %</td> -->
              <?php else: ?>
              <div class="col-lg-6 mb-2">
                <div class="form-item">
                    <label for="Received">Received</label>
                    <input type="text" class="form-control form-control-lg" id="filterReceived">
                  </div>
              </div>
              <?php endif; ?>
              <div class="col-lg-6 mb-2">
                <div class="form-item">
                  <label for="Status">Status</label>
                  <input type="text" class="form-control form-control-lg" id="filterStatus">
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
<!-- Filter end -->
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
              <table class="fold-table" cellspacing="0">
                <thead>
                  <tr>
                      <th>Month/Bookings</th>
                    <?php if ($Project_type === 'mandate' && $user_type === 'user'): ?>
                      <!-- <th>Revenue</th> -->
                    <?php elseif ($user_type === 'manager'): ?>
                      <th>Revenue</th>
                      <th>Expenses</th>
                    <?php else: ?>
                      <th>Revenue</th>
                    <?php endif; ?>
                    <?php if ($Project_type === 'mandate' && $user_type === 'user'): ?>
                      <th>Remaining Amount</th>
                    <?php elseif ($user_type === 'manager'): ?>
                      <!-- <th>Remaining Amount</th> -->
                    <?php else: ?>
                      <th>Remaining Amount</th>
                    <?php endif; ?>
                    <?php if ($Project_type === 'mandate' && $user_type === 'user'): ?>
                      <!-- <th>Recent Build</th> -->
                    <?php elseif ($user_type === 'manager'): ?>
                      <!-- <th>Recent Build</th> -->
                    <?php else: ?>
                      <th>Recent Build</th>
                    <?php endif; ?>
                      <th>Build Incentive</th>
                  </tr>
                </thead>
                <tbody id="pagedataaas">

                </tbody>
                <tfoot>
                  <tr>
                      <td>Month/Bookings</td>
                    <?php if ($Project_type === 'mandate' && $user_type === 'user'): ?>
                      <!-- <td>Revenue</td> -->
                    <?php elseif ($user_type === 'manager'): ?>
                      <td>Revenue</td>
                      <td>Expenses</td>
                    <?php else: ?>
                      <td>Revenue</td>
                    <?php endif; ?>
                    <?php if ($Project_type === 'mandate' && $user_type === 'user'): ?>
                      <td>Remaining Amount</td>
                    <?php elseif ($user_type === 'manager'): ?>
                      <!-- <td>Remaining Amount</td> -->
                    <?php else: ?>
                      <td>Remaining Amount</td>
                    <?php endif; ?>
                    <?php if ($Project_type === 'mandate' && $user_type === 'user'): ?>
                      <!-- <td>Recent Build</td> -->
                    <?php elseif ($user_type === 'manager'): ?>
                      <!-- <td>Recent Build</td> -->
                    <?php else: ?>
                      <td>Recent Build</td>
                    <?php endif; ?>
                      <td>Build Incentive</td>
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
  <script type="text/javascript" src="../assets/js/bootstrap_alpha2.js"></script>
  <script type="text/javascript" src="./assets/js/script.js"></script>
  <script src="main.js"></script>
  <script src="calc.js"></script>
<!-- Addin Actual Revenu as per commission and cashback --> 
<script>
    function addCalculate(){if(isNaN(document.forms.myform.cagreement.value)||""==document.forms.myform.cagreement.value)var e=0;else var e=parseFloat(document.forms.myform.cagreement.value);if(isNaN(document.forms.myform.ccashback.value)||""==document.forms.myform.ccashback.value)var m=0;else var m=parseFloat(document.forms.myform.ccashback.value);if(document.forms.myform.crevenue.value=parseInt(e*(m/100)),isNaN(document.forms.myform.cccashback.value)||""==document.forms.myform.cccashback.value)var r=0;else var r=parseFloat(document.forms.myform.cccashback.value);if(isNaN(document.forms.myform.crevenue.value)||""==document.forms.myform.crevenue.value)var a=0;else var a=parseInt(document.forms.myform.crevenue.value);document.forms.myform.ccrevenue.value=parseInt(a-e*(r/100))}
</script>
<!-- Addin Actual Revenu as per commission and cashback End--> 
<!-- Updating Actual Revenu as per commission and cashback -->
<script>
 function updateCalculate(){if(isNaN(document.getElementById("cagreement").value)||""==document.getElementById("cagreement").value)var e=0;else var e=parseFloat(document.getElementById("cagreement").value);if(isNaN(document.getElementById("ccashback").value)||""==document.getElementById("ccashback").value)var a=0;else var a=parseFloat(document.getElementById("ccashback").value);if(document.getElementById("crevenue").value=e*(a/100),isNaN(document.getElementById("cccashback").value)||""==document.getElementById("cccashback").value)var l=0;else var l=parseFloat(document.getElementById("cccashback").value);if(isNaN(document.getElementById("crevenue").value)||""==document.getElementById("crevenue").value)var t=0;else var t=parseInt(document.getElementById("crevenue").value);document.getElementById("ccrevenue").value=t-e*(l/100)}
</script>
<!-- Updating Actual Revenu as per commission and cashback End-->
<!-- Filter data script -->
<?php if ($Project_type === 'mandate'): ?>
  <script>
    var isFilterApplied=!1,activeFilters=[];function applyFilters(){var e=[{id:"filterID",columnIndex:0},{id:"sourceTable",columnIndex:1},{id:"filterBookingDateStart",columnIndex:2},{id:"filterBookingDateEnd",columnIndex:2},{id:"filterMonth",columnIndex:3},{id:"filterBuilder",columnIndex:4},{id:"filterProject",columnIndex:5},{id:"filterCustumername",columnIndex:6},{id:"filterContactnumber",columnIndex:7},{id:"filterEmail",columnIndex:8},{id:"filterType",columnIndex:9},{id:"filterUnit",columnIndex:10},{id:"filterSize",columnIndex:11},{id:"filterAgreement",columnIndex:12},{id:"filterCashBack",columnIndex:13},{id:"filterStatus",columnIndex:14},];activeFilters=[],$("#filterdata tr").each(function(){var t=$(this),i=!0;e.forEach(function(e){var l=$("#"+e.id).val().toLowerCase(),r=t.find("td:eq("+e.columnIndex+")").text().toLowerCase();if("filterBookingDateStart"===e.id||"filterBookingDateEnd"===e.id){var n=new Date($("#filterBookingDateStart").val()),o=new Date($("#filterBookingDateEnd").val()),a=new Date(r);if(!isNaN(n)&&!isNaN(o)&&(a<n||a>o))return i=!1,!1}else if(-1===r.indexOf(l))return i=!1,!1;""!==l.trim()&&activeFilters.push(l)}),i?t.addClass("custom-filtered-row"):t.removeClass("custom-filtered-row")});var t=0,i=0,l=0;$(".custom-filtered-row").each(function(){var e=parseFloat($(this).find("td:eq(13)").text()),r=parseFloat($(this).find("td:eq(15)").text());isNaN(e)||(t+=e,l+=1),isNaN(r)||(i+=r)}),$("#counter").text(l),$("#totalTotalRevenue").text(t.toLocaleString()),$("#totalActualRevenue").text(i.toLocaleString()),applyCustomFilter()}function applyCustomFilter(){$("#filterdata tr").hide(),$(".custom-filtered-row").show(),isFilterApplied=!0}applyCustomFilter(),$(".filterable .btn-filter1").click(function(){$("#filterModal").modal("show")}),$("#applyFiltersBtn").click(function(){console.log("Apply Filters button clicked"),applyFilters(),$("#emptotaldata").css("display","block"),filterModal.hide()}),$("#filterModal").on("hidden.bs.modal",function(){$(".filterable .filters input").val(""),isFilterApplied||$("#filterdata tr").show(),applyFilters()}),$("#closeFilter").click(function(){applyFilters(),$("#filterModal").modal("hide")}),$("#cancleFilter").click(function(){applyFilters(),$("#filterModal").modal("hide")}),$(document).ready(function(){$("#clearFiltersBtn").click(function(){$("#filterID, #sourceTable, #filterBookingDateStart, #filterBookingDateEnd, #filterMonth, #filterBuilder, #filterProject, #filterCustumername, #filterContactnumber, #filterEmail, #filterType, #filterUnit, #filterSize, #filterAgreement, #filterCashBack, #filterStatus").val(""),$("#emptotaldata").css("display","none")})}),$("#clearFiltersBtn").click(function(){applyFilters(),$("#filterModal").modal("hide")});
  </script>
 <?php else: ?>
  <script>
    var isFilterApplied=!1,activeFilters=[];function applyFilters(){var e=[{id:"filterID",columnIndex:0},{id:"sourceTable",columnIndex:1},{id:"filterBookingDateStart",columnIndex:2},{id:"filterBookingDateEnd",columnIndex:2},{id:"filterMonth",columnIndex:3},{id:"filterBuilder",columnIndex:4},{id:"filterProject",columnIndex:5},{id:"filterCustumername",columnIndex:6},{id:"filterContactnumber",columnIndex:7},{id:"filterEmail",columnIndex:8},{id:"filterType",columnIndex:9},{id:"filterUnit",columnIndex:10},{id:"filterSize",columnIndex:11},{id:"filterAgreement",columnIndex:12},{id:"filterCommission",columnIndex:13},{id:"filterTrevenue",columnIndex:14},{id:"filterCashBack",columnIndex:15},{id:"filterActualRevenue",columnIndex:16},{id:"filterStatus",columnIndex:17},{id:"filterReceived",columnIndex:18},];activeFilters=[],$("#filterdata tr").each(function(){var t=$(this),i=!0;e.forEach(function(e){var l=$("#"+e.id).val().toLowerCase(),r=t.find("td:eq("+e.columnIndex+")").text().toLowerCase();if("filterBookingDateStart"===e.id||"filterBookingDateEnd"===e.id){var n=new Date($("#filterBookingDateStart").val()),o=new Date($("#filterBookingDateEnd").val()),a=new Date(r);if(!isNaN(n)&&!isNaN(o)&&(a<n||a>o))return i=!1,!1}else if(-1===r.indexOf(l))return i=!1,!1;""!==l.trim()&&activeFilters.push(l)}),i?t.addClass("custom-filtered-row"):t.removeClass("custom-filtered-row")});var t=0,i=0,l=0;$(".custom-filtered-row").each(function(){var e=parseFloat($(this).find("td:eq(13)").text()),r=parseFloat($(this).find("td:eq(15)").text());isNaN(e)||(t+=e,l+=1),isNaN(r)||(i+=r)}),$("#counter").text(l),$("#totalTotalRevenue").text(t.toLocaleString()),$("#totalActualRevenue").text(i.toLocaleString()),applyCustomFilter()}function applyCustomFilter(){$("#filterdata tr").hide(),$(".custom-filtered-row").show(),isFilterApplied=!0}applyCustomFilter(),$(".filterable .btn-filter1").click(function(){$("#filterModal").modal("show")}),$("#applyFiltersBtn").click(function(){console.log("Apply Filters button clicked"),applyFilters(),$("#emptotaldata").css("display","block"),filterModal.hide()}),$("#filterModal").on("hidden.bs.modal",function(){$(".filterable .filters input").val(""),isFilterApplied||$("#filterdata tr").show(),applyFilters()}),$("#closeFilter").click(function(){applyFilters(),$("#filterModal").modal("hide")}),$("#cancleFilter").click(function(){applyFilters(),$("#filterModal").modal("hide")}),$(document).ready(function(){$("#clearFiltersBtn").click(function(){$("#filterID, #sourceTable, #filterBookingDateStart, #filterBookingDateEnd, #filterMonth, #filterBuilder, #filterProject, #filterCustumername, #filterContactnumber, #filterEmail, #filterType, #filterUnit, #filterSize, #filterAgreement, #filterCommission, #filterTrevenue, #filterCashBack, #filterActualRevenue, #filterStatus, #filterReceived").val(""),$("#emptotaldata").css("display","none")})}),$("#clearFiltersBtn").click(function(){applyFilters(),$("#filterModal").modal("hide")});
  </script>
<?php endif; ?>
<!-- Filter data script End -->
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
    function calculateCashbackRevenue() {
        const agreementInput = document.querySelector('input[name="cagreement"]');
        const cashbackInput = document.querySelector('input[name="cccashback"]');
        const revenueInput = document.querySelector('input[name="user_agreement"]');

        let agreementValue = parseFloat(agreementInput.value);
        let cashback = parseFloat(cashbackInput.value);

        if (isNaN(agreementValue) || isNaN(cashback)) {
            revenueInput.value = '';
            return;
        }

        let percentageToReduce = 0;

        if (cashback >= 0.1 && cashback < 0.5) {
            percentageToReduce = 25;
        } else if (cashback >= 0.5 && cashback < 1) {
            percentageToReduce = 50;
        } else if (cashback >= 1 && cashback <= 1.5) {
            percentageToReduce = 75;
        } else if (cashback > 1.5) {
            percentageToReduce = 100;
        }

        let reducedValue = agreementValue * (percentageToReduce / 100);
        let actualRevenue = agreementValue - reducedValue;

        // revenueInput.value = actualRevenue.toFixed(2);
        revenueInput.value = Math.round(actualRevenue);

    }
  </script>
  </body>
  </html>