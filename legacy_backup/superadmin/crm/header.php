<style>
  .tablehiddenrows {
    transition: opacity 0.5s ease-out, max-height 0.5s ease-out;
    display: none;
  }

  body {
    font-family: "Lexend Deca", sans-serif;
    font-optical-sizing: auto;
    font-style: normal;
  }
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

      <!-- Right side items wrapper -->
      <div class="navbar-right-items">
        <button class="header-btn tooltip" id="notificationBtn" data-tooltip="Notifications">
          <span id="notifBadge" style="display:none"></span>
          <i class="fas fa-bell" aria-hidden="true"></i>
          <div class="notification-badge"></div>
        </button>

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

        <div class="user-profile-sidebar" id="more_profile_icon">
          <img class="user-avatar-small" src="../../userlogin6/assets/dataimage/ayu.jpg" alt="">
          <div class="user-info">
            <div class="user-name-small">
              <!-- User Name -->
            </div>
          </div>
        </div>
      </div>
      <!-- End navbar-right-items -->

      <button type="button" class="mobile-menu-toggle" aria-label="Toggle menu" onclick="toggleMobileSidebar()">
        <i class="bi bi-list"></i>
      </button>

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
        <div class="user-info-header">
          <div class="closebtn1"><i class="bi iconclassforall bi-x-circle-fill"></i></div>
          <div class="user-avatar-large">
            <img src="/incentiveapp_integration/userlogin6/assets/dataimage/ayu.jpg" alt="User Avatar">
          </div>
          <div class="user-details">
            <h3><?php echo $nameuser ?></h3>
          </div>
        </div>
        <div class="user-info-actions">
          <a href="#." class="user-action-link">
            <i class="bi iconclassforall bi-lock-fill"></i> Forget Password
          </a>
          <a href="/userlogin" target="_blank" class="user-action-link">
            <i class="bi iconclassforall bi-box-arrow-right"></i> User login
          </a>
          <button class="user-action-link" onclick="submitLoginForm()">
            <i class="bi iconclassforall bi-box-arrow-right"></i> Hr login
          </button>
          <a href="../logout" class="user-action-link logout">
            <i class="bx bx-log-out-circle"></i> Logout
          </a>
        </div>
      </div>

    </nav>
    <!-- End of Navbar -->

    <!--Left Sidebar -->
    <div class="sidebar" id="sidebar">
      <div class="incentivelogo">
        <a href="#" class="logo" aria-label="SearchHomes India">
          <img class="shilogo logo-expanded" src="assets/dataimage/hlogo.png" alt="SearchHomes India" />
        </a>
      </div>
      <div class="sidebar-divider" aria-hidden="true"></div>
      <ul class="side-menu" id="side-menu">
        <li class="sidemenuli">
          <a href="#." class="separationheading"><i class="bi bi-menu-up iconclassforall"></i><span>Main Menu</span
              /home/koushik /></a>
        </li>

        <li class="sidemenuli sideactive">
          <a href="/incentiveapp_integration/userlogin1/superadmin/dashboard" class="active"><i
              class="bi iconclassforall bi-back"></i>Dashboard</a>
        </li>

        <?php if ($allow_access === 'full access'): ?>
          <li class="sidemenuli sideactive1">
            <a href="/incentiveapp_integration/userlogin1/superadmin/property-bookings"><i
                class="bi iconclassforall bi-journal-richtext"></i>Property Bookings</a>
          </li>

          <li class="sidemenuli sideactive2">
            <a href="/incentiveapp_integration/userlogin1/superadmin/expenses"><i
                class="bi iconclassforall bi-wallet2"></i>Company Expenses</a>
          </li>

          <li class="sidemenuli sideactive3">
            <a href="/incentiveapp_integration/userlogin1/superadmin/incentive-tracking"><i
                class="bi iconclassforall bi-cash-coin"></i>Incentive Tracker</a>
          </li>

          <hr>
        <?php endif; ?>
        <li class="sidemenuli">
          <a href="#." class="separationheading"><i class="bi bi-shield-lock iconclassforall"></i><span>CRM</span></a>
        </li>

        <li class="sidemenuli sideactive8">
          <a href="/incentiveapp_integration/userlogin1/superadmin/crm/leads_data"><i
              class="bi iconclassforall bi-cloud-upload"></i>Leads Data</a>
        </li>

        <?php if ($tablename === 'subham323'): ?>
          <span style="display:none;"></span>
        <?php else: ?>
          <li class="sidemenuli sideactive7">
            <a href="/incentiveapp_integration/userlogin1/superadmin/superadmin_create_alert"><i
                class="bi iconclassforall bi-exclamation-octagon" style="color:red;"></i>Create Notice</a>
          </li>

          <li class="sidemenuli sideactive70">
            <a href="/incentiveapp_integration/userlogin1/superadmin/myapicontainer/create-api-view/manage_apis"><i
                class="bi iconclassforall bi-people"></i>Global Configuration</a>
          </li>
        <?php endif; ?>

        <hr>
        <?php if ($allow_access === 'full access'): ?>
          <li class="sidemenuli">
            <a href="#." class="separationheading"><i
                class="bi bi-shield-lock iconclassforall"></i><span>DEPARTMENT</span></a>
          </li>

          <li class="sidemenuli sideactive4">
            <a href="/incentiveapp_integration/userlogin1/superadmin/payment-tracking"><i
                class="bi iconclassforall bi-credit-card-2-back"></i>Payment Tracker</a>
          </li>

          <li class="sidemenuli sideactive5">
            <a href="/incentiveapp_integration/userlogin1/superadmin/companyassets"><i
                class='bi bi-wallet2 iconclassforall'></i>Company Assets</a>
          </li>
          <hr>
          <li class="sidemenuli">
            <a href="#." class="separationheading"><i
                class="bi bi-currency-exchange iconclassforall"></i><span>Accounts</span></a>
          </li>

          <li class="sidemenuli">
            <a href="/hrlogin/createuser/" target="_blank">
              <i class="bi iconclassforall bi-person-plus"></i>Create User
            </a>
          </li>

          <li class="sidemenuli sideactive6">
            <a href="/incentiveapp_integration/userlogin1/superadmin/users"><i
                class="bi iconclassforall bi-people"></i>Users</a>
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
          <a href="#" class="dark-mode-toggle" onclick="toggleDarkMode()"><i
              class="bi iconclassforall bi-moon-fill"></i>Dark Mode</a>
        </li>
        <li class="sidemenuli">
          <a href="./logout" class="logout"><i class="bi iconclassforall bi-box-arrow-right"></i>Logout</a>
        </li>

      </ul>

      <button type="button" class="sidebar-toggle-main" aria-label="Toggle sidebar" aria-controls="sidebar"
        aria-expanded="true" onclick="toggleleftsidebar()">
        <i class="bi bi-chevron-left toggle-icon-desktop" aria-hidden="true"></i>
      </button>
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
    <!-- Dark Mode Toggle Function -->
    <script>
      function toggleDarkMode() {
        document.body.classList.toggle('dark-mode');
        // Save preference to localStorage (use 'true'/'false' to match userlogin6)
        const isDarkMode = document.body.classList.contains('dark-mode');
        localStorage.setItem('darkMode', isDarkMode ? 'true' : 'false');

        // Update iframe if it exists
        updateIframeDarkMode();
      }

      function updateIframeDarkMode() {
        const iframe = document.getElementById('userlogin6-dashboard-iframe');
        if (!iframe) return;

        // Check if iframe is ready (loaded and has contentWindow)
        const isReady = iframe.dataset && iframe.dataset.ready === '1' && iframe.contentWindow;

        if (isReady) {
          try {
            const isDarkMode = document.body.classList.contains('dark-mode');
            // Send message to iframe
            iframe.contentWindow.postMessage({
              type: 'darkMode',
              enabled: isDarkMode
            }, '*');
          } catch (e) {
            console.log('Could not update iframe dark mode:', e);
            // Retry after a short delay
            setTimeout(function () {
              updateIframeDarkMode();
            }, 200);
          }
        } else {
          // Iframe not ready yet, retry after a short delay
          setTimeout(function () {
            updateIframeDarkMode();
          }, 100);
        }
      }

      // Check for saved dark mode preference on page load
      (function () {
        const savedDarkMode = localStorage.getItem('darkMode');
        if (savedDarkMode === 'true' || savedDarkMode === 'enabled') {
          document.body.classList.add('dark-mode');
        }
      })();

      // Also check on DOMContentLoaded as fallback
      document.addEventListener('DOMContentLoaded', function () {
        const savedDarkMode = localStorage.getItem('darkMode');
        if (savedDarkMode === 'true' || savedDarkMode === 'enabled') {
          document.body.classList.add('dark-mode');
        }
        // Update iframe after a short delay to ensure it's loaded
        setTimeout(updateIframeDarkMode, 500);
      });

      // Mobile sidebar toggle function (separate from desktop toggle)
      function toggleMobileSidebar() {
        var sidebar = document.getElementById('sidebar');
        var body = document.body;

        if (sidebar && window.innerWidth <= 768) {
          var isOpen = body.classList.contains('sidebar-open');

          if (isOpen) {
            body.classList.remove('sidebar-open');
            body.classList.remove('sidebar-overlay');
            sidebar.classList.remove('close');
          } else {
            body.classList.add('sidebar-open');
            body.classList.add('sidebar-overlay');
            sidebar.classList.remove('close');
          }
        }
      }

      // Close mobile sidebar (for close button)
      function closeMobileSidebar() {
        var body = document.body;
        var sidebar = document.getElementById('sidebar');

        if (sidebar && window.innerWidth <= 768) {
          body.classList.remove('sidebar-open');
          body.classList.remove('sidebar-overlay');
          sidebar.classList.remove('close');
        }
      }
    </script>
    <!-- This is for hide and show Right Side Bar -->
    <script>
      document.addEventListener("DOMContentLoaded", function () {
        var sidebar = document.getElementById('rightsidebar');
        if (!sidebar) {
          return;
        }
        function hideSidebar() {
          sidebar.style.display = 'none';
        }
        var sidebarItems = document.querySelectorAll('.pmd-sidebar-li');
        sidebarItems.forEach(function (item) {
          item.addEventListener('click', function () {
            hideSidebar();
          });
        });
      });
    </script>
    <!-- This is for hide and show Right Side Bar End -->
    <script>
      document.addEventListener('DOMContentLoaded', function () {
        var sidebar = document.getElementById('sidebar');
        if (!sidebar) {
          return;
        }

        var body = document.body;
        var toggleButtons = document.querySelectorAll('.sidebar-toggle-main');
        var overlay = document.querySelector('.mobile-overlay');
        var collapseQuery = window.matchMedia('(max-width: 1180px)');
        var mobileQuery = window.matchMedia('(max-width: 768px)');
        var wasCollapseMatch = collapseQuery.matches;
        var desktopPreference = collapseQuery.matches ? true : false;
        var desktopPreferenceManual = false;
        var widePreference = false;
        var widePreferenceManual = false;

        if (!overlay) {
          overlay = document.createElement('div');
          overlay.className = 'mobile-overlay';
          body.appendChild(overlay);
        }

        function syncToggleIcon(closed) {
          toggleButtons.forEach(function (btn) {
            btn.classList.toggle('is-collapsed', closed);
            btn.setAttribute('aria-expanded', (!closed).toString());
            var label = closed ? 'Open sidebar navigation' : 'Collapse sidebar navigation';
            btn.setAttribute('aria-label', label);
            btn.setAttribute('title', label);
            var mobileIcon = btn.querySelector('.toggle-icon-mobile');
            if (mobileIcon) {
              mobileIcon.classList.toggle('bi-x-lg', !closed);
              mobileIcon.classList.toggle('bi-list', closed);
            }
          });
        }

        function applySidebarState(closed) {
          var isMobile = mobileQuery.matches;
          sidebar.classList.toggle('close', closed);
          body.classList.toggle('sidebar-collapsed', closed);
          body.classList.toggle('sidebar-open', !closed);
          body.classList.toggle('sidebar-overlay', !closed && isMobile);
          if (!isMobile) {
            body.classList.remove('sidebar-overlay');
          }
          syncToggleIcon(closed);
        }

        function toggleleftsidebar(forceState) {
          var currentlyClosed = sidebar.classList.contains('close');
          var nextState = typeof forceState === 'boolean' ? forceState : !currentlyClosed;
          applySidebarState(nextState);
          if (!mobileQuery.matches) {
            desktopPreference = nextState;
            desktopPreferenceManual = true;
            widePreference = nextState;
            widePreferenceManual = true;
          }
          return nextState;
        }

        window.toggleleftsidebar = toggleleftsidebar;

        toggleButtons.forEach(function (btn) {
          if (btn.hasAttribute('onclick')) {
            btn.removeAttribute('onclick');
          }
          btn.addEventListener('click', function (event) {
            event.preventDefault();
            toggleleftsidebar();
          });
        });

        overlay.addEventListener('click', function () {
          var isMobile = mobileQuery.matches;
          if (isMobile) {
            closeMobileSidebar();
          } else {
            toggleleftsidebar(true);
          }
        });

        function handleResponsive(initialRun) {
          var isMobile = mobileQuery.matches;
          var shouldCollapse = collapseQuery.matches;

          if (isMobile) {
            applySidebarState(true);
            wasCollapseMatch = shouldCollapse;
            return;
          }

          if (!initialRun) {
            if (shouldCollapse && !wasCollapseMatch && !desktopPreferenceManual) {
              desktopPreference = true;
            } else if (!shouldCollapse && wasCollapseMatch && !widePreferenceManual) {
              widePreference = false;
            }
          } else if (shouldCollapse && !desktopPreferenceManual) {
            desktopPreference = true;
          }

          var targetState = shouldCollapse ? desktopPreference : widePreference;
          applySidebarState(targetState);
          wasCollapseMatch = shouldCollapse;
        }

        function bindMediaQuery(query, handler) {
          if (typeof query.addEventListener === 'function') {
            query.addEventListener('change', handler);
          } else if (typeof query.addListener === 'function') {
            query.addListener(handler);
          }
        }

        window.addEventListener('resize', function () {
          handleResponsive(false);
        });

        bindMediaQuery(collapseQuery, function () {
          handleResponsive(false);
        });

        bindMediaQuery(mobileQuery, function () {
          handleResponsive(false);
        });

        document.addEventListener('keyup', function (event) {
          if (event.key === 'Escape') {
            var isMobile = mobileQuery.matches;
            if (body.classList.contains('sidebar-overlay')) {
              if (isMobile) {
                closeMobileSidebar();
              } else {
                toggleleftsidebar(true);
              }
            }
          }
        });

        handleResponsive(true);
      });
    </script>