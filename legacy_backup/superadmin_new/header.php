<style>
  .tablehiddenrows {transition: opacity 0.5s ease-out, max-height 0.5s ease-out;display: none;}
  body {font-family: "Lexend Deca", sans-serif;font-optical-sizing: auto;font-style: normal;}
</style>
</head>

<body>
  <!-- Loader html -->
  <div class="loading bodyloader" id="loader">
    <div class="loading__container">
      <div class="loading__ring loading__ring--orange"></div>
      <div class="loading__ring loading__ring--green"></div>
      <div class="loading__ring loading__ring--blue"></div>
    </div>
  </div>
  <!-- Loader html End -->
  <!-- incentive main start -->
  <div class="incentivemain">

    <!-- Navbar -->
    <nav class="navbar">

      <i class="bi iconclassforall bi-sliders2 dashopen" onclick="toggleleftsidebar()"></i>

      <div class="incentivelogo">
        <a href="#" class="logo">
          <img class="shilogo" src="../assets/images/shilogo.png" alt="applogo" />
        </a>
      </div>

       <form action="#" id="lapserach" onsubmit="return false;">
        <div class="inputwrap d-block position-relative">
          <div class="form-input d-flex">
            <input type="text" id="searchInput" placeholder="Search..." aria-label="Search"/>
            <button class="search-btn" type="submit" aria-label="Search button"><i class="bi bi-search iconclassforall"></i></button>
          </div>
        </div>
      </form>

      <div id="mob_search_icon" onclick="openSearchPopup()">
        <i class="bx bx-search"></i>
      </div>

      <div id="search-popup" class="search-popup">
        <form>
          <input type="text" placeholder="Type your search...">
          <div class="mob-pop-flex">
            <button type="submit">Search</button>
            <button onclick="closeSearchPopup()">Close</button>
          </div>
        </form>
      </div>

      <a href="#" class="notif" id="more_notif_icon">
        <i class="bi bi-bell iconclassforall"></i>
        <span class="count">0</span>
      </a>

      <div class="notif-content" id="notif-content_box">
        <ul>
          <div class="closebtn"><i class="bi iconclassforall bi-x-circle-fill"></i></div>
          <li>
            <span>
              <h6>You have 3 notification</h6>
            </span>
          </li>
          <li>
            <span>
              <i class="bx bx-envelope"></i>
              Lorem ipsum, dolor sit amet consectetur adipisicing.
            </span>
            <p>today</p>
          </li>
          <li>
            <span>
              <i class="bx bx-envelope"></i>
              Lorem ipsum, dolor sit amet consectetur adipisicing.
            </span>
            <p>yesterday</p>
          </li>
          <li>
            <span>
              <i class="bx bx-envelope"></i>
              Lorem ipsum, dolor sit amet consectetur adipisicing.
            </span>
            <p>11:00 Am</p>
          </li>
          <li>
            <span>
              <i class="bx bx-envelope"></i>
              Lorem ipsum, dolor sit amet consectetur adipisicing.
            </span>
            <p>11:00 Am</p>
          </li>
          <li>
            <span>
              <i class="bx bx-envelope"></i>
              Lorem ipsum, dolor sit amet consectetur adipisicing.
            </span>
            <p>11:00 Am</p>
          </li>
          <li>
            <span>
              <i class="bx bx-envelope"></i>
              Lorem ipsum, dolor sit amet consectetur adipisicing.
            </span>
            <p>11:00 Am</p>
          </li>
          <li>
            <span>
              <i class="bx bx-envelope"></i>
              Lorem ipsum, dolor sit amet consectetur adipisicing.
            </span>
            <p>11:00 Am</p>
          </li>
          <li>
            <span>
              <i class="bx bx-envelope"></i>
              Lorem ipsum, dolor sit amet consectetur adipisicing.
            </span>
            <p>11:00 Am</p>
          </li>
          <li>
            <span>
              <i class="bx bx-envelope"></i>
              Lorem ipsum, dolor sit amet consectetur adipisicing.
            </span>
            <p>11:00 Am</p>
          </li>
        </ul>
      </div>

      <a href="#" class="profile" id="more_profile_icon">
        <i class="bi iconclassforall bi-person-fill"></i>
      </a>

      <div class="profile-content" id="profile-content_box">
        <ul>
          <div class="closebtn1"><i class="bi iconclassforall bi-x-circle-fill"></i></div>
          <li>
            <a href="#.">
              <span>
                <i class="bi iconclassforall bi-person-fill"></i>
                Welcome <?php echo $nameuser ?>
              </span>
            </a>
          </li>
          <li>
            <a href="#."><span>
                <i class="bi iconclassforall bi-lock-fill"></i>
                Forget Password
              </span>
            </a>
          </li>
          <li>
            <a href="/userlogin" target="_blank">
            <button class="profile-logout" style="color:black;"><span>
                <i class="bi iconclassforall bi-box-arrow-right"></i>
                User login
              </span></button>
            </a>
          </li>
          <li>
            <!-- <a href="/hrlogin" target="_blank"> -->
            <button class="profile-logout" style="color:black;" onclick="submitLoginForm()"><span>
                <i class="bi iconclassforall bi-box-arrow-right"></i>
                Hr login
              </span></button>
            <!-- </a> -->
          </li>

          <li>
            <a href="../logout"><button class="profile-logout">
              <i class="bx bx-log-out-circle"></i>
              Logout
              </button>
            </a>
          </li>
        </ul>
      </div>

      <a href="#" class="setting togglerightsidebar" id="togglerightsidebar">
        <i class="bi iconclassforall bi-sliders"></i>
      </a>

      <!-- Right Sidebar -->
      <aside id="rightsidebar" class="pmd-sidebar">
        <i class="bi iconclassforall bi-x-circle-fill close-btn" id="close-btn"></i>
        <ul class="pmd-sidebar-ul">

          <li class="pmd-sidebar-li addNewUserModal" type="button" data-toggle="modal" data-target="#addNewUserModal">
            <i class="bi iconclassforall bi-person-fill-add addBooking"></i>
            <span class="addBooking">Add Booking</span>
            <i class="bi iconclassforall bi-cash-coin addExpenses"></i>
            <span class="addExpenses">Add Expenses</span>
          </li> 

          <li class="pmd-sidebar-li btn-filter1 filterModal" data-toggle="modal" data-target="#filterModal">
            <i class="bi iconclassforall bi-funnel-fill"></i>
            <span>Filter</span>
          </li>

          <li class="pmd-sidebar-li calculatorModal" data-toggle="modal" data-target="#calculatorModal">
            <i class="bi iconclassforall bi-calculator"></i>
            <span>Calculator</span>
          </li>

          <li class="pmd-sidebar-li downloadCsvBtn addBooking" type="button" id="downloadCsvBtn">
            <i class="bi iconclassforall bi-cloud-arrow-down-fill"></i>
            <span>Download</span>
          </li>
        </ul>

      </aside>

    </nav>
    <!-- End of Navbar -->

    <!--Left Sidebar -->
    <div class="sidebar" id="sidebar">
      <ul class="side-menu" id="side-menu">
        <li class="sidemenuli">
          <a href="#." class="separationheading"><i class="bi bi-menu-up iconclassforall"></i><span>MAIN MENU</span></a>
        </li>

        <li class="sidemenuli sideactive">
            <a href="/superadmin_new/dashboard"><i class="bi iconclassforall bi-back"></i>Dashboard</a>
        </li>

        <?php if ($allow_access === 'full access'): ?>
        <li class="sidemenuli sideactive1">
          <a href="/superadmin_new/property-bookings"><i class="bi iconclassforall bi-journal-richtext"></i>Property Bookings</a>
        </li>

        <li class="sidemenuli sideactive2">
          <a href="/superadmin_new/expenses"><i class="bi iconclassforall bi-wallet2"></i>Company Expenses</a>
        </li>

        <li class="sidemenuli sideactive3">
          <a href="/superadmin_new/incentive-tracking"><i class="bi iconclassforall bi-cash-coin"></i>Incentive Tracker</a>
        </li>

        <hr>
        <?php endif; ?>
        <li class="sidemenuli">
          <a href="#." class="separationheading"><i class="bi bi-shield-lock iconclassforall"></i><span>CRM</span></a>
        </li>

        <li class="sidemenuli sideactive8">
          <a href="/superadmin_new/crm/leads_data"><i class="bi iconclassforall bi-cloud-upload"></i>Leads Data</a>
        </li>
        
        <?php if ($tablename === 'subham323' || $tablename === 'NoUser323'): ?>
                <span style="display:none;"></span>
        <?php else: ?>
            <li class="sidemenuli sideactive7">
              <a href="/superadmin_new/superadmin_create_alert"><i class="bi iconclassforall bi-exclamation-octagon" style="color:red;"></i>Create Notice</a>
            </li>
            
            <li class="sidemenuli sideactive70">
              <a href="/superadmin_new/myapicontainer/create-api-view/manage_apis"><i class="bi iconclassforall bi-people"></i>Global Configuration</a>
            </li>
        <?php endif; ?>

        <hr>
        <?php if ($allow_access === 'full access'): ?>
        <li class="sidemenuli">
          <a href="#." class="separationheading"><i class="bi bi-shield-lock iconclassforall"></i><span>DEPARTMENT</span></a>
        </li>
        
        <li class="sidemenuli sideactive4">
          <a href="/superadmin_new/payment-tracking"><i class="bi iconclassforall bi-credit-card-2-back"></i>Payment Tracker</a>
        </li>

        <li class="sidemenuli sideactive5">
          <a href="/superadmin_new/companyassets"><i class='bi bi-wallet2 iconclassforall'></i>Company Assets</a>
        </li>
        <hr>
        <li class="sidemenuli">
          <a href="#." class="separationheading"><i class="bi bi-currency-exchange iconclassforall"></i><span>Accounts</span></a>
        </li>

        <li class="sidemenuli">
          <a href="/hrlogin/createuser/" target="_blank">
            <i class="bi iconclassforall bi-person-plus"></i>Create User
          </a>
        </li>

        <li class="sidemenuli sideactive6">
          <a href="/superadmin_new/users"><i class="bi iconclassforall bi-people"></i>Users</a>
        </li>
        <hr>
        <?php endif; ?>
        <!-- <li class="sidemenuli">
          <div class="dropdown statusdrop">
            <a href="#"><i class="bx bxs-bookmarks"></i>Check Status</a>
            <div class="dropdown-content statusdropcont" style="left: 10px">
              <ul id="statuscont">
                <li class="statuscontli statusact">
                  <a href="#"><i class="bx bx-wallet"></i>Paid</a>
                </li>
                <li class="statuscontli">
                  <a href="#"><i class="bx bx-loader-circle"></i>Processing</a>
                </li>
                <li class="statuscontli">
                  <a href="#"><i class="bx bx-error-alt"></i>Cancelled</a>
                </li>
              </ul>
            </div>
          </div>
        </li> -->

        <!-- <li class="sidemenuli">
          <a href="#"><i class="bi iconclassforall bi-lightbulb"></i>
            <input type="checkbox" id="theme-toggle" hidden />
            <label for="theme-toggle" class="theme-toggle"></label>
          </a>
        </li> -->
        <li class="sidemenuli">
        <a href="./logout" class="logout"><i class="bi iconclassforall bi-box-arrow-right"></i>Logout</a>
        </li>

      </ul>
    </div>
    <!-- End of Left Sidebar -->
  
<!-- Calculator Modal -->
<div class="modal fade" id="calculatorModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="exampleModalLabel">Incentive Calculator</h5>
                        <button type="button" class="btn-close close" data-dismiss="modal" aria-label="Close"
                            id="closeFilter"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <form id="calculator-form">
                                <div class="col-lg-12 mb-4">
                                    <div class="form-item">
                                    <label for="d1">Current Salary :</label>
                                    <input type="number" class="form-control form-control-lg" id="d1" required>
                                </div>
                                </div>
                                <div class="col-lg-12">
                                    <div class="form-item">
                                    <label for="d2">Generated Revenue :</label>
                                    <input type="number" class="form-control form-control-lg" id="d2" required>
                                </div>
                            </div>
                            </form>
                            <div class="col-lg-12">
                                <div class="Ubsubmitbtn">
                                <div class="border p-2 m-1 bg-success text-white amountbtn">
                                    Amount: ₹<span id="result">0.00</span></div>
                                </div>
                            </div>
                            </div>
                    </div>
                    <div class="modal-footer" style="margin: 0 auto;">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="submit" form="calculator-form" class="btn btn-primary">Calculate</button>
                    </div>
                </div>
            </div>
          </div>
<!-- calculator modal End -->
<script>
  if (window.Capacitor && window.Capacitor.Plugins && window.Capacitor.Plugins.SplashScreen) {
    window.addEventListener('load', () => {
      window.Capacitor.Plugins.SplashScreen.hide();
    });
  }
</script>  
<!-- This is for hide and show Right Side Bar -->
<script>
  document.addEventListener("DOMContentLoaded", function() {
      var sidebar = document.getElementById('rightsidebar');
      function hideSidebar() {
          sidebar.style.display = 'none';
      }
      var sidebarItems = document.querySelectorAll('.pmd-sidebar-li');
      sidebarItems.forEach(function(item) {
          item.addEventListener('click', function() {
              hideSidebar();
          });
      });
  });
</script>
<!-- This is for hide and show Right Side Bar End -->