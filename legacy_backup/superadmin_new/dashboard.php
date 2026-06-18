<?php include('htmlopen.php'); ?>
<link rel="stylesheet" href="./assets/css/calender.css" />
<style>
  .side-menu li.sideactive{ 
    background: var(--shicol);
    position: relative
  }
  .side-menu li.sideactive a{
      color: white
  }
  .togglerightsidebar,.addNewUserModal,.filterModal,.calculatorModal,.downloadCsvBtn{ 
  display: none;
  }
</style>
  <?php include('header.php'); ?>
    <!-- Main Content -->
    <div class="content">
      <div class="contentinside">
      <!-- Year Dropdown -->
      <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-3">
                <div class="input-group">
                    <select class="form-select" id="year_select">
                        <!-- Dropdown options -->
                    </select>
                    <label class="input-group-text" for="year_select">Year</label>
                </div>
            </div>
        </div>
      </div>
      <!-- Year Dropdown End -->
      <div class="container">
        <div class="row">
          <!-- this is drawing Pie Chart -->
          <div class="col-lg-6 chart-col-lg">
                <canvas id="line_top_x"></canvas>
          </div>
          <!-- this is drawing Pie Chart End -->
          <!-- this is drawing Bar Chart -->
          <div class="col-lg-6 chart-col-lg">
            <div class="chartcmmnstyle" id="piechart_3d"></div>
          </div>
          <!-- this is drawing Bar Chart End-->
        </div>
        <div class="row  mt-3">
          <div class="col-lg-6 chart-col-lg">
            <div class="chartcmmnstyle" id="barchart_material"></div>
          </div>
          <div class="col-lg-6 chart-col-lg">
            <div class="wrapper">
              <div class="container-calendar">
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
        <!-- <div class="row  mt-3">
           <div class="col-lg-12 indexbooktable">
            <h4>Booking Status</h4>
            <a href="/user/superadmin_new/database.php">
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
    </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script type="text/javascript" src="../assets/js/chartloader.js"></script>
  <script src="./assets/js/chart.js"></script>
  <script src="./assets/js/calender.js"></script>
  <!-- <script src="main.js"></script> -->
  <script src="calc.js"></script>
  <?php include('htmlclose.php'); ?>