<?php include('htmlopen.php'); ?>
<link rel="stylesheet" href="../assets/css/dataTable2.0.4.css" />
<link rel="stylesheet" href="../assets/css/button_dataTable3.0.2.css" />
<link rel="stylesheet" href="../assets/css/fixed_dataTable5.0.0.css"/>
<link rel="stylesheet" href="../assets/css/jquery_dataTable.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
    /* this is for Hide Bar of table  */
    div.dt-container .dt-paging .dt-paging-button.current, div.dt-container .dt-paging .dt-paging-button.current:hover{color: white!important;}.dt-search{display: none!important;}
    /* this is for Hide Bar of table  End*/
    /* this is for status color CSS */
    .status-pending{background-color:#40E0D0;color:#000;width:100%;height:30px;border-radius:30px;box-shadow:0 0 1px indigo;border:none;font-weight:600}.status-booked,.status-fake,.status-not-interested,.status-rnr{color:#fff;width:100%;height:30px;border-radius:30px;box-shadow:0 0 1px indigo;border:none;font-weight:600}.status-callback,.status-interested{color:#000;border-radius:30px;width:100%;height:30px;box-shadow:0 0 1px indigo;font-weight:600}.status-fake{background-color:red}.status-rnr{background-color:orange}.status-callback{background-color:#add8e6;border:none}.status-booked{background-color:green}.status-not-interested{background-color:grey}.status-interested{background-color:#90ee90;border:none}.status-follow-up{background-color:purple;color:#fff;width:100%;height:30px;border-radius:30px;box-shadow:0 0 1px indigo;border:none;font-weight:600}
    /* this is for status color CSS ENd */
    /* This is For Button CSS */
    #delete-selected-btn{background-color:red;color:#fff}.btn{border:none!important}#assign-button{background-color:#00f;color:#fff}.uploadExcelPopup{background-color:#109910;color:#fff}#download-excel-ex{color:#fff;background-color:grey}.card{border-radius:10px;box-shadow:0 5px 5px 0 rgba(0,0,0,.3);background-color:#fff;padding:10px 30px 40px}.card h3{font-size:22px;font-weight:600}.drop_box{margin:10px 0;padding:10px;display:flex;align-items:center;justify-content:center;flex-direction:column;border:3px dotted #1b6c9f;border-radius:5px}.drop_box h4{font-size:16px;font-weight:400;color:#2e2e2e}.drop_box p{margin-top:10px;margin-bottom:20px;font-size:12px;color:#a3a3a3}.upload-wrap button{border:1px solid #000}.upload-input{border-radius:5px;height:50px;line-height:normal;color:#282828;display:block;width:100%;box-sizing:border-box;user-select:auto;font-size:16px;padding:0 6px 0 12px}.upload-input:focus{border:3px solid #1b6c9f}
    /* This is For Button CSS End */
    /* this is for dropdown CSS */
    .dropdown-container{position:relative;width:100%}.search-box{margin-bottom:10px;padding:8px;border:1px solid #ced4da;border-radius:5px;width:100%}#users{width:100%;height:150px;padding:10px;border-radius:5px;border:1px solid #ced4da;background-color:#fff;font-size:16px}#users option{padding:8px;border-bottom:1px solid #ddd}#users option:hover{background-color:#f0f0f0}.modal-body{padding:20px;background-color:#f9f9f9;border-radius:10px}.modal-footer{border-top:1px solid #e9ecef;padding:15px}
    /* this is for dropdown CSS End */
    /* This is for Filter CSS */
    .custom-modal-content{overflow:visible!important}.dropdown-container{position:relative}.dropdown-content{display:none;position:absolute;top:100%;left:0;width:100%;background-color:#fff;box-shadow:0 4px 8px rgba(0,0,0,.1);z-index:1000}.dropdown-menu{width:100%;overflow:auto}.dropdown-content ul{list-style-type:none;padding:0;margin:0;max-height:200px;overflow-y:auto}.dropdown-content li{padding:8px;cursor:pointer}.dropdown-content li:hover{background-color:#f1f1f1}.dropdown-search{width:100%;padding:8px;box-sizing:border-box}.dropdown-container input[readonly]{cursor:pointer}.assignedusertd{width:100px;overflow:auto}.assignedusertd::-webkit-scrollbar{height:5px}.assignedusertd::-webkit-scrollbar-track{-webkit-box-shadow:inset 0 0 6px rgba(0,0,0,.3);border-radius:10px}.assignedusertd::-webkit-scrollbar-thumb{border-radius:10px;-webkit-box-shadow:inset 0 0 6px rgba(0,0,0,.5)}
    /* This is for Filter CSS End */
    .side-menu li.sideactive8{
      background: var(--shicol);
    position: relative
  }
  .side-menu li.sideactive8 a{
      color: white
  }
  .addNewUserModal{display: none;}
</style>
<?php include('header.php'); ?>
<div class="content">
<!-- This is filter model  -->
<div class="modal fade" tabindex="-1" id="filterModal">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content custom-modal-content">
            <div class="modal-header">
                <h5 class="modal-title">FILTER DATA</h5>
                <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close" id="closeFilter"></button>
            </div>
            <div class="modal-body">
                <div class="container">
                    <div class="row">
                        <div class="col-md-6 mb-2">
                            <div class="form-item">
                                <label for="filterCustumername">Customer Name</label>
                                <input type="text" class="form-control form-control-lg custom-input" id="filterCustumername">
                            </div>
                        </div>
                        <div class="col-md-6 mb-2">
                            <div class="form-item">
                                <label for="filterEmail">Email Id</label>
                                <input type="text" class="form-control form-control-lg custom-input" id="filterEmail">
                            </div>
                        </div>
                        <div class="col-md-6 mb-2">
                            <div class="form-item">
                                <label for="filterContactnumber">Contact No.</label>
                                <input type="text" class="form-control form-control-lg custom-input" id="filterContactnumber">
                            </div>
                        </div>

                        <div class="col-md-6 mb-2">
                            <div class="form-item">
                                <label for="LocationInput">Location</label>
                                <input type="text" id="LocationInput" class="form-control form-control-lg custom-input" placeholder="Select Location">
                                <div id="LocationDropdown" class="dropdown-menu" style="display: none;">
                                    <!-- Options will be dynamically added here -->
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-2">
                            <div class="form-item">
                                <label for="ProjectInput">Project</label>
                                <input type="text" id="ProjectInput" class="form-control form-control-lg custom-input" placeholder="Select Project">
                                <div id="ProjectDropdown" class="dropdown-menu" style="display: none;">
                                    <!-- Options will be dynamically added here -->
                                </div>
                            </div>
                        </div>

                            <div class="col-md-6 mb-2">
                                <div class="form-item">
                                    <label for="AssigneduserInput">Assigned User</label>
                                    <input type="text" id="AssigneduserInput" class="form-control form-control-lg custom-input" placeholder="Select Assigned User">
                                    <div id="AssigneduserDropdown" class="dropdown-menu" style="display: none;">
                                        <!-- Options will be dynamically added here -->
                                    </div>
                                </div>
                            </div>

                        <div class="col-md-6 mb-2">
                            <div class="form-item">
                                <label for="StatusInput">Status</label>
                                <input type="text" id="StatusInput" class="form-control form-control-lg custom-input" placeholder="Select Status">
                                <div id="StatusDropdown" class="dropdown-menu" style="display: none;">
                                    <!-- Options will be dynamically added here -->
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 mb-2">
                            <div class="form-item">
                                <label for="CreatedatInput">Created At</label>
                                <input type="text" id="CreatedatInput" class="form-control form-control-lg custom-input" placeholder="Select Created At">
                                <div id="CreatedatDropdown" class="dropdown-menu" style="display: none;">
                                    <!-- Options will be dynamically added here -->
                                </div>
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
<!-- This is filter model End -->
    <div class="container">
        <div class="row">
        <!-- /////////////////////buttons///////////////////////////// -->
            <div class="col-lg-12" style="margin-bottom:30px">
                <button type="button" class="btn btn-sm uploadExcelPopup"  data-toggle="modal" data-target="#uploadExcelPopup">Upload Excel</button> 
                <button type="button" id="assign-button" class="btn btn-sm" disabled data-toggle="modal" data-target="#assignModal">Assign Users</button>
                <button type="button" id="delete-selected-btn" class="btn btn-sm">Delete Selected</button> 
                <button type="button" id="download-excel-ex" class="btn btn-sm">Excel Example</button> 
            </div> 
    <!-- <h2>Upload Excel File</h2>
    <form action="upload.php" method="POST" enctype="multipart/form-data">
    <input type="file" name="file" accept=".xlsx, .xls, .csv" required>
    <button type="submit" name="submit">Upload</button>
    </form> -->
     <!-- upload excel popup -->
     <div class="modal fade" id="uploadExcelPopup" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Upload Excel Files</h5>
                        </div>
                        <form action="upload.php" method="POST" enctype="multipart/form-data">
                            <div class="modal-body">
                                <div class="container">
                                    <div class="card">
                                        <h3>Upload Excel Files</h3>
                                        <div class="drop_box">
                                        <!-- <header>
                                            <h4>Select File here</h4>
                                        </header> -->
                                        <p style="text-transform:uppercase">Files Supported: xlsx, xls, csv</p>
                                            <div class="upload-wrap">
                                                    <input type="file" name="file" accept=".xlsx, .xls, .csv" required class="upload-input">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <div class="col-lg-12 text-center">
                                    <button type="submit" name="submit" class="btn btn-sm btn-primary">Upload</button>
                                    <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Close</button>
                                </div>
                            </div>
                        </form>
                        </div>
                    </div>
                </div>

    <h3>Uploaded Data</h3>
    <?php
        // Check if the status message is passed in the URL
        if (isset($_GET['status'])) {
            $statusMessage = htmlspecialchars($_GET['status']); 
            echo "<div id='status-message' style='color: green; background-color: lightyellow; padding: 10px; margin-bottom: 20px;'>
                    $statusMessage
                </div>";
        }
        ?>
        <script>
        // Hide the status message after 20 seconds
        setTimeout(function() {
            var statusMessageDiv = document.getElementById('status-message');
            if (statusMessageDiv) {
                statusMessageDiv.style.display = 'none';
            }
        }, 20000); // 20 seconds
        </script>
      <div class="col-lg-12">
        <div class="table-container">
    <form id="bulkDeleteForm" method="POST" action="bulk_delete.php">
    <table id="example" class="stripe row-border order-column display" cellspacing="0" style="width:100%">
        <thead>
            <tr>
                <th><input type="checkbox" id="select-all"></th>
                <th>Name</th>
                <th>Email</th>
                <th>Number</th>
                <th>Location</th>
                <th>Project</th>
                <th>Assigned User</th>
                <th>Status</th>
                <th>Message</th>
                <th>Created At</th>
            </tr>
        </thead>
        <tbody id="uploaddata">
        <?php

            $sql = "SELECT * FROM shi_upload_data ORDER BY id DESC";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($result) > 0) {
                foreach ($result as $row) {
                    // Determine the CSS class for the status
                    $statusClass = '';
                    switch ($row['status']) {
                        case 'Pending':
                            $statusClass = 'status-pending';
                            break;
                        case 'Fake':
                            $statusClass = 'status-fake';
                            break;
                        case 'RNR':
                            $statusClass = 'status-rnr';
                            break;
                        case 'Call Back':
                            $statusClass = 'status-callback';
                            break;
                        case 'Already Booked':
                            $statusClass = 'status-booked';
                            break;
                        case 'Not Interested':
                            $statusClass = 'status-not-interested';
                            break;
                        case 'Interested':
                            $statusClass = 'status-interested';
                            break;
                        case 'Follow Up':
                            $statusClass = 'status-follow-up';
                            break;
                        default:
                            $statusClass = '';
                    }
                    echo "<tr>
                            <td><input type='checkbox' class='select-row' name='row_ids[]' value='{$row['id']}'></td>
                            <td>{$row['name']}</td>
                            <td>{$row['email']}</td>
                            <td>{$row['number']}</td>
                            <td>{$row['location']}</td>
                            <td>{$row['project']}</td>
                            <td>{$row['assign_to_user']}</td>
                            <td><button class='{$statusClass}'>{$row['status']}</button></td>
                            <td>{$row['message']}</td>
                            <td>{$row['created_at']}</td>
                          </tr>";
                }
            } else {
                echo "<tr><td colspan='9'>No data found</td></tr>";
            }
        ?>
        </tbody>
    </table>
</form>
</div>
</div>
   <!-- assign user popup -->
   <div class="modal fade " id="assignModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Assign Users</h5>
                        </div>
                        <form id="assign-form" method="POST" action="assign_users.php">
                            <div class="modal-body">
                                <p> <b>Total Leads</b>: <span id="selected-count"><b> 0</b></span></p>

                                <input type="hidden" id="selected-ids" name="selected_ids">
                                <div class="dropdown-container">
                                    <input type="text" id="assignprojectname" name="assignprojectname" class="form-control search-box" placeholder="Enter Project Name...">
                                </div>
                                <label for="users">Select User(s):</label>
                                <div class="dropdown-container">
                                    <input type="text" id="user-search" class="form-control search-box" placeholder="Search users...">
                                    <select id="users" name="users[]" multiple class="form-select">
                                        <option value="">No User</option>
                                        <?php
                                            // Fetch users from the database
                                            $userQuery = "SELECT tablename FROM accounts WHERE is_active = 1"; 
                                            $userStmt = $conn->prepare($userQuery);
                                            $userStmt->execute();
                                            $users = $userStmt->fetchAll(PDO::FETCH_ASSOC);
                                            
                                            foreach ($users as $user) {
                                                echo "<option value='{$user['tablename']}'>{$user['tablename']}</option>";
                                            }
                                        ?>
                                    </select>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <div class="col-lg-12 text-center">
                                    <button type="submit" name="submit" class="btn btn-sm btn-primary">Assign</button>
                                    <button type="button" class="btn btn-sm btn-secondary" data-dismiss="modal">Close</button>
                                </div>
                            </div>
                        </form>
                        </div>
                    </div>
                </div>
</div>
</div>
  <script type="text/javascript" src="../assets/js/jquery_dataTable.js"></script>
  <script type="text/javascript" src="../assets/js/dataTable2.0.4.js"></script>
<script>
    document.getElementById('delete-selected-btn').addEventListener('click', function(event) {
    event.preventDefault();
    
    const selectedCheckboxes = document.querySelectorAll('.select-row:checked');
    const numSelected = selectedCheckboxes.length;
    
    if (numSelected > 0) {
        // Show confirmation dialog (use SweetAlert or your own modal)
        Swal.fire({
            title: 'Are you sure?',
            text: `You are about to delete ${numSelected} selected row(s). This action cannot be undone.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete them!',
            cancelButtonText: 'No, cancel!',
        }).then((result) => {
            if (result.isConfirmed) {
                // If confirmed, submit the form
                document.getElementById('bulkDeleteForm').submit();
            }
        });
    } else {
        Swal.fire('No rows selected', 'Please select at least one row to delete.', 'info');
    }
});
</script>
<script>
// Download Excle Sheet
document.getElementById('download-excel-ex').addEventListener('click', function() {
                                    window.location.href = 'example_format.xlsx';
                    });
// Download Excle Sheet End
// This is for DropDown Script
                        document.addEventListener('DOMContentLoaded', function() {
                        var searchInput = document.getElementById('user-search');
                        var userDropdown = document.getElementById('users');
                        
                        // Search functionality for the dropdown
                        searchInput.addEventListener('keyup', function() {
                            var filter = searchInput.value.toLowerCase();
                            var options = userDropdown.options;
                            
                            for (var i = 0; i < options.length; i++) {
                                var optionText = options[i].text.toLowerCase();
                                if (optionText.indexOf(filter) > -1) {
                                    options[i].style.display = "";
                                } else {
                                    options[i].style.display = "none";
                                }
                            }
                        });
                    });
                    // This is for DropDown Script End
                    document.addEventListener('DOMContentLoaded', function() {
                        $('#example').DataTable({
                                            ordering: false, // Disable sorting for the entire table
                                            scrollX: true,
                                            lengthMenu: [[10, 25, 50,100,200,300, -1], [10, 25, 50,100,200,300, "All"]],
                                        });
    // Initialize DataTable
    const table = $('#example').DataTable(); 

    const selectAll = document.getElementById('select-all');
    const countDisplay = document.getElementById('selected-count');
    const assignButton = document.getElementById('assign-button');

    function updateCount() {
        // Get the number of checked rows that are visible
        const selectedCount = table
            .rows({ page: 'current' })
            .nodes()
            .to$()
            .find('.select-row:checked')
            .length;
        countDisplay.textContent = selectedCount;
    }

    function toggleAssignButton() {
        const selectedVisibleRows = table
            .rows({ page: 'current' })
            .nodes()
            .to$()
            .find('.select-row:checked').length;

        // Enable or disable the "Assign" button based on selected visible rows
        assignButton.disabled = selectedVisibleRows === 0;
    }

    // Handle the Select All checkbox functionality
    selectAll.addEventListener('change', function() {
        const isChecked = this.checked;

        // Only check/uncheck the rows visible on the current page
        table
            .rows({ page: 'current' })
            .nodes()
            .to$()
            .find('.select-row')
            .each(function() {
                this.checked = isChecked;
            });

        updateCount();
        toggleAssignButton(); // Enable/disable the assign button based on selection
    });

    // Attach the change event to each checkbox in the visible rows
    $('#example tbody').on('change', '.select-row', function() {
        // If any checkbox is unchecked, uncheck the 'select-all' checkbox
        if (!this.checked) {
            selectAll.checked = false;
        }

        // Check if all visible rows are selected and update the 'select-all' checkbox
        if (table
            .rows({ page: 'current' })
            .nodes()
            .to$()
            .find('.select-row:checked')
            .length === table
            .rows({ page: 'current' })
            .nodes()
            .to$()
            .find('.select-row')
            .length) {
            selectAll.checked = true;
        }

        updateCount();
        toggleAssignButton(); // Enable/disable the assign button based on selection
    });

    // Show modal popup when "Assign Users" is clicked
    assignButton.addEventListener('click', function() {
        // Get the IDs of selected visible rows
        const selectedIds = table
            .rows({ page: 'current' })
            .nodes()
            .to$()
            .find('.select-row:checked')
            .map(function() {
                return this.value;
            })
            .get();

        if (selectedIds.length > 0) {
            // Open the modal and pass selected IDs
            openAssignModal(selectedIds);
        }
    });

    function openAssignModal(selectedIds) {
        // You can use a library like Bootstrap for modals
        const modal = document.getElementById('assignModal');
        modal.style.display = 'block';

        // Store the selected IDs in a hidden field inside the modal
        document.getElementById('selected-ids').value = selectedIds.join(',');
    }
});
</script>
</div>
  <!-- incentive main close -->
  <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
  <script type="text/javascript" src="../assets/js/jquery_dataTable.js"></script>
  <script type="text/javascript" src="../assets/js/dataTable2.0.4.js"></script>
  <script type="text/javascript" src="../assets/js/dataTable_fixed.js"></script>
  <script type="text/javascript" src="../assets/js/fixed_dataTable.js"></script>
  <script type="text/javascript" src="../assets/js/dataTable_button.js"></script>
  <script type="text/javascript" src="../assets/js/button_dataTable.js"></script>
  <script type="text/javascript" src="./assets/js/script.js"></script>
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
      }, 5000);
    });
  </script>
   <script> 
    $(document).ready(function() {
    // Function to populate dropdowns based on the table data
    function populateDropdowns() {
        var locations = new Set();
        var projects = new Set();
        var assignedUsers = new Set();
        var statuses = new Set();
        var createdAtDates = new Set();

        $("#uploaddata tr").each(function() {
            var location = $(this).find("td:eq(4)").text().trim();
            var project = $(this).find("td:eq(5)").text().trim();
            var assignedUser = $(this).find("td:eq(6)").text().trim();
            var status = $(this).find("td:eq(7)").text().trim();
            var createdAt = $(this).find("td:eq(9)").text().trim();

            if (location) locations.add(location);
            if (project) projects.add(project);
            if (assignedUser) assignedUsers.add(assignedUser);
            if (status) statuses.add(status);
            if (createdAt) createdAtDates.add(createdAt);
        });

        // Populate Location dropdown
        $("#LocationDropdown").empty().append('<a class="dropdown-item" data-value="">Select Location</a>');
        locations.forEach(function(location) {
            $("#LocationDropdown").append('<a class="dropdown-item" data-value="' + location + '">' + location + '</a>');
            
        });
        
        // Populate Project dropdown
        $("#ProjectDropdown").empty().append('<a class="dropdown-item" data-value="">Select Project</a>');
        projects.forEach(function(project) {
            $("#ProjectDropdown").append('<a class="dropdown-item" data-value="' + project + '">' + project + '</a>');
            
        });

        // Populate Assigneduser dropdown
        $("#AssigneduserDropdown").empty().append('<a class="dropdown-item" data-value="">Select Assigned User</a>');
        assignedUsers.forEach(function(user) {
            $("#AssigneduserDropdown").append('<a class="dropdown-item" data-value="' + user + '">' + user + '</a>');
        });

        // Populate Status dropdown
        $("#StatusDropdown").empty().append('<a class="dropdown-item" data-value="">Select Status</a>');
        statuses.forEach(function(status) {
            $("#StatusDropdown").append('<a class="dropdown-item" data-value="' + status + '">' + status + '</a>');
        });

        // Populate Created At dropdown
        $("#CreatedatDropdown").empty().append('<a class="dropdown-item" data-value="">Select Created At</a>');
        createdAtDates.forEach(function(date) {
            $("#CreatedatDropdown").append('<a class="dropdown-item" data-value="' + date + '">' + date + '</a>');
        });
    }

    // Call the function to populate the dropdowns when the page loads
    populateDropdowns();

    // Searchable input field
    function setUpSearchableDropdown(inputId, dropdownId) {
        var $input = $("#" + inputId);
        var $dropdown = $("#" + dropdownId);
        
        $input.on("focus", function() {
            $dropdown.show();
        });

        $input.on("input", function() {
            var query = $(this).val().toLowerCase();
            $dropdown.find(".dropdown-item").each(function() {
                var text = $(this).text().toLowerCase();
                $(this).toggle(text.indexOf(query) !== -1);
            });
        });

        $(document).on("click", function(event) {
            if (!$(event.target).closest("#" + inputId + ", #" + dropdownId).length) {
                $dropdown.hide();
            }
        });

        $dropdown.on("click", ".dropdown-item", function() {
            var value = $(this).data("value");
            $input.val($(this).text());
            $dropdown.hide();
        });
    }

    // Set up searchable dropdowns
    setUpSearchableDropdown("LocationInput", "LocationDropdown");
    setUpSearchableDropdown("ProjectInput", "ProjectDropdown");
    setUpSearchableDropdown("AssigneduserInput", "AssigneduserDropdown");
    setUpSearchableDropdown("StatusInput", "StatusDropdown");
    setUpSearchableDropdown("CreatedatInput", "CreatedatDropdown");
});
</script>

<script>
  function applyFilters() {
    var filterInputs = [{
        id: "filterCustumername",
        columnIndex: 1
      },
      {
        id: "filterEmail",
        columnIndex: 2
      },
      {
        id: "filterContactnumber",
        columnIndex: 3
      },
      {
        id: "LocationInput",
        columnIndex: 4
      },
      {
        id: "AssigneduserInput",
        columnIndex: 5
      },
      {
        id: "StatusInput",
        columnIndex: 6
      },
      {
        id: "CreatedatInput",
        columnIndex: 8
      },
    ];
    activeFilters = [];
    $("#uploaddata tr").each(function() {
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

    $("#uploaddata tr").hide();
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
      $("#filterCustumername,#filterEmail,#filterContactnumber,#LocationInput,#AssigneduserInput,#StatusInput,#CreatedatInput").val("");
    });
  });
  $("#clearFiltersBtn").click(function() {
    applyFilters();
    $("#filterModal").modal("hide");
  });
</script>
<script>
    $(document).ready(function(){var e;let t=$("#example").DataTable(),a;document.getElementById("searchInput").addEventListener("input",(e=function e(){let a=document.getElementById("searchInput").value.toLowerCase();t.search(a).draw()},function(...t){let n=this;clearTimeout(a),a=setTimeout(()=>e.apply(n,t),300)}))});
</script>
</body>
</html>