<?php
require_once dirname(__DIR__) . '/env_loader.php';
include('htmlopen.php');
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
    '/superadmin/payment-tracking',
    '/superadmin/companyassets',
    '/superadmin/accounts',
    '/superadmin/property-bookings',
    '/superadmin/expenses',
    '/superadmin/incentive-tracking',
    '/hrlogin/createuser/',
    '/superadmin/users'
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
$DATABASE_HOST = getenv('DB_HOST') ?: 'localhost';
$DATABASE_USER = getenv('DB_USER') ?: 'u797909128_demoproject';
$DATABASE_PASS = getenv('DB_PASS') ?: 'QK&0/aF@5';
$DATABASE_NAME = getenv('DB_NAME') ?: 'u797909128_demo';

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

<?php
    // Place near the top of users.php, before HTML output
    $allowedLimits = [10, 50, 100, 200, 300];
    $recordsPerPage = isset($_GET['limit']) && in_array((int)$_GET['limit'], $allowedLimits, true)
        ? (int)$_GET['limit']
        : 10;

    $currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $offset = ($currentPage - 1) * $recordsPerPage;
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Users Database</title>
  <link rel="shortcut icon" type="nobglogo.png" href="../assets/dataimage/nobglogo.png" alt="text">
  <link rel="stylesheet" href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Lexend+Deca:wght@100..900&display=swap" rel="stylesheet">
  <!-- User Page Custom CSS -->
  <link rel="stylesheet" href="./assets/css/Users.css?v=<?php echo time(); ?>"/>
  <style>
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
<!-- Users Filter Modal (Property Booking Style) -->
<div class="modal fade users-filter-modal" id="filterModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered users-filter-dialog">
        <div class="modal-content users-filter-content">

            <!-- Header -->
            <div class="users-filter-header">
                <h4>FILTER DATA</h4>
                <button type="button" class="users-filter-close" data-dismiss="modal">✕</button>
            </div>

            <!-- Body -->
            <div class="users-filter-body">

                <div class="users-filter-grid">

                    <?php
                    $fields = [
                        "ID"             => "filterID",
                        "Status"         => "status",
                        "Name"           => "username",
                        "Email"          => "email",
                        "Contact No"     => "Contactnumber",
                        "Password"       => "Password",
                        "Monthly CTC"    => "inhandsalary",
                        "Date of Joining"=> "DateOfJoining",
                        "Date of Birth"  => "DateOfBirth",
                        "Unique ID"      => "uniqueid",
                        "Employee ID"    => "EmployeeId",
                        "Assign User"    => "assignuser",
                        "Role Type"      => "roletype",
                        "Project Name"   => "Projectname"
                    ];

                    foreach ($fields as $label => $id):
                    ?>
                        <div class="users-filter-item">
                            <label><?= $label ?></label>
                            <div class="usr-custom-dropdown-wrapper" id="usrdd-<?= $id ?>">
                                <!-- FIXED BOX -->
    <div class="usr-input-shell">

        <div class="usr-chip-container" id="usrdd-<?= $id ?>-chips"></div>

        <input
            type="text"
            class="users-filter-input usr-custom-dropdown-input"
            id="<?= $id ?>"
            placeholder="search & select <?= strtolower($label) ?>..."
            data-usrdd="usrdd-<?= $id ?>"
            autocomplete="off"
        >

    </div>
    

    <!-- FLOATING DROPDOWN (OUTSIDE SHELL) -->
    <div class="usr-custom-dropdown-list" id="usrdd-<?= $id ?>-list"></div>

                            </div>
                        </div>
                    <?php endforeach; ?>

                </div>

            </div>

            <!-- Footer -->
            <div class="users-filter-footer">

                <button class="btn-users-close" data-dismiss="modal">
                    Close
                </button>

                <button class="btn-users-clear" id="clearFiltersBtn">
                    Clear Filters
                </button>

                <button class="btn-users-apply" id="applyFiltersBtn">
                    Apply Filters
                </button>

            </div>

        </div>
    </div>
</div>


<!-- Add/Edit User Modal -->
<div class="modal fade" id="addEditModal" tabindex="-1" aria-labelledby="addEditModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addEditModalLabel">Add New User</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="userForm">
          <input type="hidden" id="userId" name="userId">
          <div class="row">
            <div class="col-md-6 mb-3">
              <label for="userName" class="form-label">Name <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="userName" name="userName" required>
            </div>
            <div class="col-md-6 mb-3">
              <label for="userEmail" class="form-label">Email <span class="text-danger">*</span></label>
              <input type="email" class="form-control" id="userEmail" name="userEmail" required>
            </div>
            <div class="col-md-6 mb-3">
              <label for="userContact" class="form-label">Contact No. <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="userContact" name="userContact" required>
            </div>
            <div class="col-md-6 mb-3">
              <label for="userPassword" class="form-label">Password <span class="text-danger">*</span></label>
              <input type="password" class="form-control" id="userPassword" name="userPassword" required>
            </div>
            <div class="col-md-6 mb-3">
              <label for="userMonthlyCTC" class="form-label">Monthly CTC</label>
              <input type="text" class="form-control" id="userMonthlyCTC" name="userMonthlyCTC">
            </div>
            <div class="col-md-6 mb-3">
              <label for="userDOJ" class="form-label">Date Of Joining</label>
              <input type="date" class="form-control" id="userDOJ" name="userDOJ">
            </div>
            <div class="col-md-6 mb-3">
              <label for="userDOB" class="form-label">Date Of Birth</label>
              <input type="date" class="form-control" id="userDOB" name="userDOB">
            </div>
            <div class="col-md-6 mb-3">
              <label for="userUniqueID" class="form-label">Unique ID</label>
              <input type="text" class="form-control" id="userUniqueID" name="userUniqueID">
            </div>
            <div class="col-md-6 mb-3">
              <label for="userEmployeeID" class="form-label">Employee ID</label>
              <input type="text" class="form-control" id="userEmployeeID" name="userEmployeeID">
            </div>
            
            <div class="col-md-6 mb-3">
              <label for="assign_user" class="form-label">Assign User</label>
              <div class="custom-multiselect" onclick="toggleDropdown(event)">
                <input type="text" id="search_user_input" placeholder="Search and select users..." autocomplete="off" oninput="filterDropdown()">
                <div id="selected_tags"></div>
                <div id="dropdown" class="dropdown">
                  <?php foreach ($assignUsers as $user): ?>
                    <div class="dropdown-item" onclick="selectUser('<?= htmlspecialchars($user['tablename']) ?>', '<?= htmlspecialchars($user['username']) ?> (<?= htmlspecialchars($user['user_type']) ?>)')">
                      <?= htmlspecialchars($user['username']) ?> (<?= htmlspecialchars($user['user_type']) ?>)
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
              <input type="hidden" id="assign_user_hidden" name="assign_user">
            </div>

            <div class="col-md-6 mb-3">
              <label for="user1stAmount" class="form-label">1st Amount</label>
              <input type="text" class="form-control" id="user1stAmount" name="user1stAmount">
            </div>
            <div class="col-md-6 mb-3">
              <label for="user2ndAmount" class="form-label">2nd Amount</label>
              <input type="text" class="form-control" id="user2ndAmount" name="user2ndAmount">
            </div>
            <div class="col-md-6 mb-3">
              <label for="user3rdAmount" class="form-label">3rd Amount</label>
              <input type="text" class="form-control" id="user3rdAmount" name="user3rdAmount">
            </div>
            <div class="col-md-6 mb-3">
              <label for="user4thAmount" class="form-label">4th Amount</label>
              <input type="text" class="form-control" id="user4thAmount" name="user4thAmount">
            </div>
            <div class="col-md-6 mb-3">
              <label for="user5thAmount" class="form-label">5th Amount</label>
              <input type="text" class="form-control" id="user5thAmount" name="user5thAmount">
            </div>
            <div class="col-md-6 mb-3">
              <label for="user6thAmount" class="form-label">6th Amount</label>
              <input type="text" class="form-control" id="user6thAmount" name="user6thAmount">
            </div>
            <div class="col-md-6 mb-3">
              <label for="userProjectName" class="form-label">Project Name</label>
              <input type="text" class="form-control" id="userProjectName" name="userProjectName">
            </div>
            <div class="col-md-6 mb-3">
              <label for="userProjectType" class="form-label">Project Type</label>
              <input type="text" class="form-control" id="userProjectType" name="userProjectType">
            </div>
            <div class="col-md-6 mb-3">
              <label for="userRoleType" class="form-label">Role Type</label>
              <input type="text" class="form-control" id="userRoleType" name="userRoleType">
            </div>
            
            <div class="col-md-6 mb-3">
              <label for="userStatus" class="form-label">Status</label>
              <select class="form-select" id="userStatus" name="userStatus">
                <option value="1">Active</option>
                <option value="0">Inactive</option>
              </select>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary" id="saveUserBtn">Save User</button>
      </div>
    </div>
  </div>
</div>

<!-- Main Content Area -->
<div class="content">
  <div class="container-fluid">
    <div class="row">
      <div class="col-12">
        
        <!-- Summary Cards Section -->
        <div class="summary-wrapper">

    <button class="summary-arrow left" id="summaryLeft">
        ‹
    </button>

    <div class="summary-section" id="summaryScroll">

        <div class="summary-card">
            <span class="summary-text">Active Users : <span id="activeusers">0</span></span>
        </div>

        <div class="summary-card">
            <span class="summary-text">Inactive Users : <span id="deactiveusers">0</span></span>
        </div>

        <div class="summary-card">
            <span class="summary-text">Assign Users : <span id="assignednuser">0</span></span>
        </div>

        <div class="summary-card">
            <span class="summary-text">Total Salary : <span id="totalsalary">0</span></span>
        </div>

    </div>

    <button class="summary-arrow right" id="summaryRight">
        ›
    </button>

</div>


        <!-- Search and Filter Bar -->
        <div class="control-bar">
          <div class="control-left control-search-flex">
            <div class="search-box">
              <svg class="search-icon" width="18" height="18" viewBox="0 0 18 18" fill="none">
                <circle cx="8" cy="8" r="6" stroke="#999" stroke-width="1.5"/>
                <path d="M12.5 12.5L16 16" stroke="#999" stroke-width="1.5" stroke-linecap="round"/>
              </svg>
              <input type="text" class="search-input" id="searchInput" placeholder="Search assets...">
            </div>
          </div>
          <div class="control-right">
            <button class="btn-filter" id="openFilterBtn">
               <i class="bi bi-filter"></i>
              <span class="btn-text">Filters</span>
            </button>
            <div class="column-visibility-wrapper">

    <button class="btn-column-visibility" id="columnVisibilityBtn">
        <!-- <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
            <rect x="2" y="3" width="4" height="10" stroke="currentColor" stroke-width="1.5"/>
            <rect x="10" y="3" width="4" height="10" stroke="currentColor" stroke-width="1.5"/>
        </svg> -->
        <i class="bi bi-layout-three-columns"></i>
        <span class="btn-text">Column Visibility</span>
    </button>

    <!-- Dropdown -->
    <div class="column-dropdown" id="columnDropdown">

    <!-- NOTE: 0 is checkbox, so we start from 1 -->

    <label><input type="checkbox" data-col="1"> ID</label>
    <label><input type="checkbox" data-col="2"> Status</label>
    <label><input type="checkbox" data-col="3"> Name</label>
    <label><input type="checkbox" data-col="4"> Email</label>
    <label><input type="checkbox" data-col="5"> Contact</label>
    <label><input type="checkbox" data-col="6"> Password</label>
    <label><input type="checkbox" data-col="7"> Monthly CTC</label>
    <label><input type="checkbox" data-col="8"> DOJ</label>
    <label><input type="checkbox" data-col="9"> DOB</label>
    <label><input type="checkbox" data-col="10"> Unique ID</label>
    <label><input type="checkbox" data-col="11"> Employee ID</label>

    <label><input type="checkbox" data-col="12"> 1st Amount</label>
    <label><input type="checkbox" data-col="13"> 2nd Amount</label>
    <label><input type="checkbox" data-col="14"> 3rd Amount</label>
    <label><input type="checkbox" data-col="15"> 4th Amount</label>
    <label><input type="checkbox" data-col="16"> 5th Amount</label>
    <label><input type="checkbox" data-col="17"> 6th Amount</label>
    <label><input type="checkbox" data-col="18"> Project Name</label>
    <label><input type="checkbox" data-col="19"> Project Type</label>
    <label><input type="checkbox" data-col="20"> Role Type</label>
    <label><input type="checkbox" data-col="21"> Assign User</label>

    <label><input type="checkbox" data-col="22"> Created At</label>
    <label><input type="checkbox" data-col="23"> Inactive At</label>
    <label><input type="checkbox" data-col="24"> Action</label>

</div>


</div>

            <div class="page-size-selector">
    <select id="users-limit" class="users-limit-select">
        <?php foreach ($allowedLimits as $v): ?>
            <option value="<?php echo $v; ?>" <?php echo $recordsPerPage === $v ? 'selected' : ''; ?>>
                <?php echo $v; ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>

          </div>
        </div>

        <!-- Table Container with Horizontal Scroll -->
        <div class="user-table-container">
          <div class="user-table-scroll-wrapper">
            <table class="user-data-table">
              <thead>
                <tr>
                  <th class="checkbox-col">
                    <input type="checkbox" class="checkbox" id="selectAll">
                  </th>
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
                <!-- Data will be populated here via JavaScript -->
              </tbody>
            </table>
          </div>
        </div>

        <!-- Pagination Section -->
        <div class="pagination-section">
          <div class="pagination-info">
            <span>Showing <span id="showingStart">1</span> to <span id="showingEnd">1</span> of <span id="totalEntries">1</span> entries</span>
          </div>
          <div class="pagination-controls">
            <button class="page-btn prev-btn" id="prevPageBtn">←</button>
            <button class="page-btn page-number active" id="currentPageBtn">1</button>
            <button class="page-btn next-btn" id="nextPageBtn">→</button>
          </div>
          <div class="pagination-jump">
          <div class="jump-wrapper">
            <!-- <span class="jump-label">Page No.</span> -->

            <input  type="number"   id="pageJump"   class="jump-input"   placeholder="Page No." />


            <!-- <span class="jump-divider">|</span> -->

            <button class="jump-btn" id="jumpBtn">Jump</button>
          </div>
          </div>

        </div>

      </div>
    </div>
  </div>
</div>
<button id="floatingClearFilters" class="floating-clear-btn">
    <i class="bi bi-x-circle"></i>
    Clear Filters
</button>
<!--End Main Content -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="./assets/js/script.js"></script>
  <script src="hrmain.js"></script>
<!-- <script>
  function applyFilters(){var e=[{id:"filterID",columnIndex:0},{id:"status",columnIndex:1},{id:"username",columnIndex:2},{id:"email",columnIndex:3},{id:"Contactnumber",columnIndex:4},{id:"Password",columnIndex:5},{id:"inhandsalary",columnIndex:6},{id:"DateOfJoining",columnIndex:7},{id:"DateOfBirth",columnIndex:8},{id:"uniqueid",columnIndex:9},{id:"EmployeeId",columnIndex:10},{id:"firstamount",columnIndex:11},{id:"scndamount",columnIndex:12},{id:"thirdamount",columnIndex:13},{id:"fourthamount",columnIndex:14},{id:"fifthamount",columnIndex:15},{id:"sixthamount",columnIndex:16},{id:"Projectname",columnIndex:17},{id:"Projecttype",columnIndex:18},{id:"roletype",columnIndex:19},{id:"assignuser",columnIndex:20}],n=0,t=0;$("#incentiveuser tr").each(function(){var i=$(this),l=!0;e.forEach(function(e){var n=$("#"+e.id).val().toLowerCase();if(-1===i.find("td:eq("+e.columnIndex+")").text().toLowerCase().indexOf(n))return l=!1,!1}),l?(i.addClass("custom-filtered-row"),n+=parseFloat(i.find("td:eq(6)").text())||0,t++):i.removeClass("custom-filtered-row")}),$("#totalsalary").text(n),$("#assignednuser").text(t),$("#incentiveuser tr").hide(),applyCustomFilter()}function applyCustomFilter(){$(".custom-filtered-row").show()}$(document).ready(function(){$(".filterable .btn-filter1").click(function(){$("#filterModal").modal("show")}),$("#applyFiltersBtn").click(function(){$("#filterModal").modal("hide"),applyFilters()}),$("#filterModal").on("hidden.bs.modal",function(){$(".filterable .filters input").val(""),applyFilters()}),$("#closeFilter, #cancleFilter").click(function(){applyFilters(),$("#filterModal").modal("hide")}),$("#clearFiltersBtn").click(function(){$("#filterID, #status, #username, #email, #Contactnumber, #Password, #inhandsalary, #DateOfJoining, #DateOfBirth, #uniqueid, #EmployeeId, #firstamount, #scndamount, #thirdamount, #fourthamount, #fifthamount, #sixthamount, #Projectname, #Projecttype, #roletype, #assignuser").val(""),applyFilters(),$("#filterModal").modal("hide")})});
</script>   -->
  <script>
function debounce(e,t){
    let timer;
    return function(...args){
        clearTimeout(timer);
        timer = setTimeout(() => e.apply(this,args), t);
    }
}

function searchTable() {

    const searchValue =
        document.getElementById("searchInput").value.toLowerCase().trim();

    const rows =
        document.querySelectorAll("#incentiveuser tr.user-data-row");

    rows.forEach(row => {

        const cells = row.querySelectorAll("td");

        const searchableText = `
            ${cells[1]?.innerText ?? ""}
            ${cells[2]?.innerText ?? ""}
            ${cells[3]?.innerText ?? ""}
            ${cells[4]?.innerText ?? ""}
            ${cells[5]?.innerText ?? ""}
        `.toLowerCase();

        row.style.display =
            searchableText.includes(searchValue) ? "" : "none";

        const expandRow = row.nextElementSibling;

        if (expandRow?.classList.contains("user-expand-row")) {
            expandRow.style.display = "none";
        }
    });

    window.currentUsersPage = 1;
    updateUsersPagination();
    updateUsersSummary();
}

/* 🔥🔥🔥 THIS WAS MISSING */
document.getElementById("searchInput")
    ?.addEventListener("input", debounce(searchTable, 300));
</script>

  <script>
    let selectedUsers=[];
    function toggleDropdown(e){

    e.stopPropagation();   // 🔥 CRITICAL FIX

    const dropdown = document.getElementById("dropdown");

    if (!dropdown) return;

    dropdown.style.display =
        dropdown.style.display === "block" ? "none" : "block";
}

      function selectUser(e,t){selectedUsers.some(t=>t.value===e)||(selectedUsers.push({value:e,label:t}),updateSelectedTags(),updateHiddenInput())}
      function removeUser(e){selectedUsers=selectedUsers.filter(t=>t.value!==e),updateSelectedTags(),updateHiddenInput()}
       function updateSelectedTags(){let e=document.getElementById("selected_tags");e.innerHTML="",selectedUsers.forEach(t=>{let s=document.createElement("div");s.className="tag",s.innerHTML=`${t.label}<span class="remove" onclick="removeUser('${t.value}')">&times;</span>`,e.appendChild(s)})}
      function updateHiddenInput(){let e=selectedUsers.map(e=>e.value);document.getElementById("assign_user_hidden").value=e.join(",")}
      document.addEventListener("click",function(e){let t=document.getElementById("dropdown"),s=document.querySelector(".custom-multiselect");s.contains(e.target)||(t.style.display="none")});        
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

  <script>
document.addEventListener('DOMContentLoaded', function () {
    const select = document.getElementById('users-limit');
    if (!select) return;

    select.addEventListener('change', function () {
        const url = new URL(window.location.href);
        url.searchParams.set('limit', this.value);
        url.searchParams.set('page', '1'); // reset to first page
        window.location.href = url.toString();
        document.getElementById("users-limit")?.addEventListener("change", () => {

        window.currentUsersPage = 1;
        updateUsersPagination();
        });

    });
});
</script>

<script>
$(document).ready(function () {

    const modal = $('#filterModal');

    // Open
    $('#openFilterBtn').on('click', function () {

        modal.addClass('custom-show');
        $('body').addClass('modal-open');

        if (!$('.custom-backdrop').length) {
            $('body').append('<div class="custom-backdrop"></div>');
        }
    });

    // Close function
    function closeModal() {

        modal.removeClass('custom-show');
        $('body').removeClass('modal-open');
        $('.custom-backdrop').remove();
    }

    // Close buttons
    $('.users-filter-close, .btn-users-close').on('click', closeModal);

    // Outside click
    modal.on('click', function (e) {

        if ($(e.target).is('#filterModal')) {
            closeModal();
        }
    });

    // Apply
    $('#applyFiltersBtn').on('click', function () {

        applyFilters();
        closeModal();
    });

    // Clear
    $('#clearFiltersBtn').on('click', function () {

    $('#filterModal input').val('');
    applyFilters();
    closeModal();    // 🔥 CRITICAL FIX
});


});
</script>

<script>
document.addEventListener("DOMContentLoaded", function () {

    const dropdown = document.getElementById("columnDropdown");
    const table = document.querySelector(".user-data-table");
    const columnBtn = document.getElementById("columnVisibilityBtn");

    if (!dropdown || !table) return;

    const checkboxes = dropdown.querySelectorAll("input[type='checkbox']");

    /* 🔥 BUTTON FIX */
    if (columnBtn) {
        columnBtn.addEventListener("click", function (e) {
            e.stopPropagation();
            dropdown.classList.toggle("show");
        });
    }

    document.addEventListener("click", function () {
        dropdown.classList.remove("show");
    });

    dropdown.addEventListener("click", function (e) {
        e.stopPropagation();
    });

    function toggleColumn(index, show) {

        table.querySelectorAll(
            "thead tr th:nth-child(" + (index + 1) + "), tbody tr td:nth-child(" + (index + 1) + ")"
        ).forEach(cell => {
            cell.style.display = show ? "table-cell" : "none";
        });
    }

    checkboxes.forEach(cb => {
        cb.addEventListener("change", function () {
            const col = parseInt(this.dataset.col);
            toggleColumn(col, this.checked);
        });
    });

    window.applyDefaults = function () {

        const width = window.innerWidth;
        let defaultCols = [];

        if (width >= 1440) defaultCols = [1,2,3,4,5,6,7,9,24];
        else if (width > 768) defaultCols = [1,2,3,4,5,8,24];
        else if (width > 425) defaultCols = [1,2,3,4,5,24];
        else if (width >= 377) defaultCols = [1,2,3,24];
        else defaultCols = [2,3,24];

        checkboxes.forEach(cb => {

            const col = parseInt(cb.dataset.col);
            const shouldShow = defaultCols.includes(col);

            cb.checked = shouldShow;
            toggleColumn(col, shouldShow);
        });

        table.style.width = "max-content";
    }

    let resizeTimer;

  function debounceResize() {

    clearTimeout(resizeTimer);

    resizeTimer = setTimeout(() => {

        if (typeof window.applyDefaults === "function") {
            window.applyDefaults();
        }

        /* 🔥 FIX 1 — CLOSE COLUMN DROPDOWN */
        if (window.innerWidth >= 1025) {
            dropdown.classList.remove("show");
        }

        /* 🔥🔥🔥 FIX 2 — COLLAPSE EXPANDED USER ROWS */
        if (window.innerWidth >= 1025) {

            document.querySelectorAll(".user-expand-row")
                .forEach(row => row.style.display = "none");

            document.querySelectorAll(".user-expand-btn i")
                .forEach(icon => icon.className = "bi bi-chevron-right");

            document.querySelectorAll(".user-expand-btn")
                .forEach(btn => btn.classList.remove("active"));
        }

    }, 180);
}



    applyDefaults();

    window.addEventListener("resize", debounceResize);
});

</script>

<script>
document.addEventListener("DOMContentLoaded", function () {

    const container = document.getElementById("summaryScroll");
    const leftBtn = document.getElementById("summaryLeft");
    const rightBtn = document.getElementById("summaryRight");

    if (!container || !leftBtn || !rightBtn) return;

    function updateArrows() {

        const maxScroll =
            container.scrollWidth - container.clientWidth;

        // Show / hide left
        if (container.scrollLeft > 10) {
            leftBtn.style.display = "flex";
        } else {
            leftBtn.style.display = "none";
        }

        // Show / hide right
        if (container.scrollLeft < maxScroll - 10) {
            rightBtn.style.display = "flex";
        } else {
            rightBtn.style.display = "none";
        }
    }

    // Scroll buttons
    leftBtn.addEventListener("click", () => {
        container.scrollBy({
            left: -200,
            behavior: "smooth"
        });
    });

    rightBtn.addEventListener("click", () => {
        container.scrollBy({
            left: 200,
            behavior: "smooth"
        });
    });

    // Events
    container.addEventListener("scroll", updateArrows);
    window.addEventListener("resize", updateArrows);

    // Initial check (important)
    setTimeout(updateArrows, 500);

});
</script>




</body>
</html>