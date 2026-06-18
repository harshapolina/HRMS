<?php include('./htmlopen.php'); ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<!-- Select2 CSS -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
    /* this is for dropdown CSS */
    .dropdown-container{position:relative;width:100%}.search-box{margin-bottom:10px;padding:8px;border:1px solid #ced4da;border-radius:5px;width:100%}#users{width:100%;height:150px;padding:10px;border-radius:5px;border:1px solid #ced4da;background-color:#fff;font-size:16px}#users option{padding:8px;border-bottom:1px solid #ddd}#users option:hover{background-color:#f0f0f0}.modal-body{padding:20px;background-color:#f9f9f9;border-radius:10px}.modal-footer{border-top:1px solid #e9ecef;padding:15px}
    /* this is for dropdown CSS End */
    /* this is for Filter CSS */
    .custom-modal-content{overflow:visible!important}.dropdown-container{position:relative}.dropdown-content{display:none;position:absolute;top:100%;left:0;width:100%;background-color:#fff;box-shadow:0 4px 8px rgba(0,0,0,.1);z-index:1000}.dropdown-menu{width:100%;overflow:auto}.dropdown-content ul{list-style-type:none;padding:0;margin:0;max-height:200px;overflow-y:auto}.dropdown-content li{padding:8px;cursor:pointer}.dropdown-content li:hover{background-color:#f1f1f1}.dropdown-search{width:100%;padding:8px;box-sizing:border-box}.dropdown-container input[readonly]{cursor:pointer}.assignedusertd{width:100px;overflow:auto}.assignedusertd::-webkit-scrollbar{height:5px}.assignedusertd::-webkit-scrollbar-track{-webkit-box-shadow:inset 0 0 6px rgba(0,0,0,.3);border-radius:10px}.assignedusertd::-webkit-scrollbar-thumb{border-radius:10px;-webkit-box-shadow:inset 0 0 6px rgba(0,0,0,.5)}
    /* this is for Filter CSS End */
    /* styling for Assigned Users */
    .assigned-users-list{display:flex;flex-wrap:wrap;padding:10px;border:1px solid #ddd;border-radius:4px;max-height:150px;overflow-y:auto}.assigned-user{display:flex;align-items:center;margin:5px;padding:5px 10px;background-color:#f8f9fa;border-radius:15px;font-size:14px}.assigned-user span{margin-right:10px}.remove-btn{background:#dc3545;border:none;color:#fff;font-size:12px;border-radius:50%;width:20px;height:20px;display:flex;align-items:center;justify-content:center;cursor:pointer}.remarks_project{border-radius:3px;background-color:#cdffd9;font-size:9px;font-weight:700;color:#00008b}
    /* styling for Assigned Users End */
    /* styling for dashobard buttons */
    .side-menu li.sideactive8{background: var(--shicol);position: relative}.side-menu li.sideactive8 a{color: white}.addNewUserModal{display: none;}
    /* styling for dashobard buttons End */
    /* style for filter dropdown  */
    .select2-container { width: 100% !important; } .select2-selection__choice { display: flex; align-items: center; background-color: #007bff; color: white; padding: 5px 10px; border-radius: 5px; margin: 2px; } .select2-selection__choice__remove { margin-left: 10px; color: white; cursor: pointer; font-weight: bold; } .select2-container { z-index: 9999 !important; /* Ensures it's on top */ } .modal { overflow: visible !important; /* Prevents clipping */ } .modal-dialog { overflow-y: initial !important; } .select2-dropdown { z-index: 1050 !important; /* Ensures dropdown is above modal */ } .form-item label { z-index: 999999; padding: 0 0px; }
    /* style for filter dropdown End */
    /* this is history Side bar CSS START */
    .unique-sidebar-title{font-size:11px;color:#333}.unique-status-sidebar{font-family:Arial,sans-serif;height:100vh;position:fixed;top:0;background-color:#f4f4f4;transition:right .3s;padding:0 20px;display:none;opacity:0}.unique-top-sect{height:5vh;display:flex;align-items:center;justify-content:center;border-bottom:1px solid #ccc}.unique-mid-sect{height:85vh;overflow-y:auto}.unique-btm-sect{height:10vh}.unique-status-sidebar.active{right:0;display:block;opacity:1}.unique-close-btn{border:none;font-size:1.5em;cursor:pointer;position:absolute;top:12px;color:#000;width:30px;right:6px;height:30px;border-radius:50%;z-index:999;background:#eae9e9}.unique-close-btn:hover,.unique-uparrow{color:red}.unique-lead-history{list-style-type:none;padding:0;position:relative}.unique-lead-history::before{content:'';position:absolute;left:14px;top:0;bottom:0;width:2px;background:#ccc}.unique-lead-history li{position:relative;padding:15px 0 15px 20px;cursor:pointer;border-radius:20px}.unique-lead-history .unique-step:hover{background-color:#dfdfdf;transition:.2s}.unique-dot{width:10px;height:10px;background-color:#555;border-radius:50%;position:absolute;left:10px;top:50%;transform:translateY(-50%)}.unique-content{margin-left:20px;display:flex;align-items:center;justify-content:space-between}.unique-content span{padding:0 3px}.unique-date-time{display:block;font-size:10px;color:#555}.unique-dropdown{display:none;font-size:.8em;color:#555;background:#f9f9f9;padding:10px;border:1px solid #ddd;border-radius:5px;box-shadow:0 2px 5px rgba(0,0,0,.1);transition:max-height .3s,opacity .3s;max-height:0;overflow:hidden;opacity:0;margin-left:22px!important;margin-right:20px!important}.unique-dropdown-insides span{display:block}.unique-dropdown.show{display:block;max-height:200px;opacity:1;margin-top:10px;max-width:245px;overflow-y:auto;text-align:justify;word-wrap:break-word}.unique-arrow{cursor:pointer;font-size:12px}.unique-arrow.unique-uparrow,.unique-dropdown.show+.unique-arrow.unique-downarrow{display:none}.unique-dropdown.show+.unique-arrow.unique-uparrow{display:inline}.unique-active-timeline .unique-dot{background-color:green}.unique-status-info{font-size:12px;font-weight:600}.unique-status-view a{font-size:11px;font-weight:600}.unique-bottom-boxes,.unique-bottom-static{width:100%;display:flex;justify-content:space-between;padding:10px 0}.unique-bottom-boxes{border-bottom:1px solid #ccc}.unique-bottom-static{border-top:1px solid #ccc}.unique-left-box,.unique-left-static,.unique-right-box,.unique-right-static{width:50%;text-align:center;font-weight:500;font-size:14px}.unique-left-box h4,.unique-left-box h6,.unique-left-static h4,.unique-left-static h6,.unique-right-box h4,.unique-right-box h6,.unique-right-static h4,.unique-right-static h6{margin:0;padding:0}.unique-left-box h4,.unique-left-static h4,.unique-right-box h4,.unique-right-static h4{font-size:14px;color:teal;font-weight:300}.unique-left-box h6,.unique-left-static h6,.unique-right-box h6,.unique-right-static h6{font-size:12px;color:#000;margin-top:5px;font-weight:700}.unique-status-view a{text-decoration:none;color:red}.unique-downarrow{color:green}.unique-dropdown.show::-webkit-scrollbar-track,.unique-mid-sect::-webkit-scrollbar-track{background-color:#f5f5f5}.unique-dropdown.show::-webkit-scrollbar,.unique-mid-sect::-webkit-scrollbar{width:2px;background-color:#f5f5f5}.unique-dropdown.show::-webkit-scrollbar-thumb,.unique-mid-sect::-webkit-scrollbar-thumb{background-color:#ccc}.unique-dropdown-insides{padding:10px}.date-time{margin-top:5px}
    /* this is history Side bar CSS End */
    .different-wrapper { display: flex; justify-content: center; align-items: center; } .history-button { background: linear-gradient(135deg, #4CAF50, #2E7D32); /* Green gradient */ border: none; border-radius: 50%; /* Circle button */ width: 20px; height: 20px; cursor: pointer; box-shadow: 0 4px 8px rgba(0,0,0,0.2); transition: all 0.3s ease; display: flex; justify-content: center; align-items: center; color: white; font-size: 18px; } .history-button:hover { background: linear-gradient(135deg, #66BB6A, #388E3C); transform: scale(1.1); box-shadow: 0 6px 12px rgba(0,0,0,0.3); } .history-button i { font-size: 20px; }
    /* this is for status model table CSS Start */
    .custom-status-table{width:100%;border-collapse:collapse;font-family:Poppins,sans-serif;background-color:#fff;box-shadow:0 4px 12px rgba(0,0,0,.1);border-radius:12px;overflow:hidden}.custom-status-table td,.custom-status-table th{padding:14px 20px;text-align:left;border-bottom:1px solid #eee}.custom-status-table thead{background-color:#f5f7fa}.custom-status-table th{font-size:16px;color:#333;font-weight:600}.custom-status-table td{font-size:15px;color:#555}.custom-status-table tbody tr:hover{background-color:#f0f4f8}.custom-status-table .status{display:flex;align-items:center;gap:8px;font-weight:500}.custom-status-table .status-rnr{color:#f1c40f}.custom-status-table .status-pending{color:#f39c12}.custom-status-table .history-icon{display:flex;justify-content:center;align-items:center}.custom-status-table .history-icon img{width:30px;height:30px;border-radius:50%}@media (max-width:768px){.custom-status-table td,.custom-status-table th{padding:10px 12px;font-size:14px}}@media (max-width:480px){.custom-status-table thead{display:none}.custom-status-table tr{display:block;margin-bottom:15px;border:1px solid #ddd;border-radius:8px;padding:10px}.custom-status-table td{display:flex;justify-content:space-between;padding:8px 10px}.custom-status-table td::before{content:attr(data-label);font-weight:700;color:#777}}
    /* this is for status model table CSS End */
    /* counter CSS */
    .user-info{display:flex;align-items:center;gap:8px;font-family:'Segoe UI',sans-serif}.avatar-wrapper{position:relative;width:32px;height:32px}.avatar-wrapper img{width:100%;height:100%;border-radius:50%;object-fit:cover}.lead-counter{position:absolute;top:-4px;right:-4px;background-color:#ff4d4f;color:#fff;font-size:10px;font-weight:700;padding:0 5px;border-radius:50%;height:16px;min-width:16px;display:flex;align-items:center;justify-content:center;line-height:1;box-shadow:0 0 2px rgba(0,0,0,.2)}.user-name{font-size:14px;font-weight:500;color:#333;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:140px}
    /* counter CSS End */
    span.select2-selection.select2-selection--multiple {overflow-y: scroll !important;height: 30px !important;}
    /*Assign User model*/
    #assignModal .modal-dialog{max-width:700px;margin:1.75rem auto}#assignModal .modal-content{max-height:85vh;display:flex;flex-direction:column}#assignModal .modal-body{overflow:auto;padding:1rem;max-height:calc(85vh - 120px)}#assignModal #assigned-users-container{max-height:180px;overflow-y:auto;border:1px solid #e9ecef;border-radius:4px;padding:8px;margin-bottom:12px;background:#fff}#assignModal .assigned-badge{display:inline-block;margin:4px 6px 4px 0;padding:6px 10px;border-radius:16px;background:#f1f3f5;font-size:.9rem}#assignModal #users{min-height:120px;max-height:220px;overflow-y:auto}#assignModal .modal-footer{margin:0;padding:12px 16px;border-top:1px solid #e9ecef;background:#fff;position:sticky;bottom:0;z-index:10}@media (max-width:576px){#assignModal .modal-dialog{max-width:95%}#assignModal #assigned-users-container{max-height:140px}#assignModal #users{min-height:100px}}
    /*Assign users model close*/
</style>
<?php include('./header.php'); ?>
<div class="content">
<!-- Isolated Filter Modal -->
    <div class="modal fade" tabindex="-1" id="isolatedFilterModal" >
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content custom-modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Filter Data</h5>
                    <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close" id="isolatedCloseFilter"></button>
                </div>
                <div class="modal-body">
                    <div class="container">
                        <div class="row">
                            <!-- Date Range Filter -->
                            <div class="col-md-6 mb-2">
                                <div class="form-item">
                                    <label for="isolatedFilterStartDate">Start Date</label>
                                    <input type="date" class="form-control form-control-lg custom-input" id="isolatedFilterStartDate">
                                </div>
                            </div>

                            <div class="col-md-6 mb-2">
                                <div class="form-item">
                                    <label for="isolatedFilterEndDate">End Date</label>
                                    <input type="date" class="form-control form-control-lg custom-input" id="isolatedFilterEndDate">
                                </div>
                            </div>

                            <!-- Customer Name -->
                            <div class="col-md-6 mb-2">
                                <div class="form-item" style="max-height: 40px;">
                                    <label for="isolatedFilterCustumername">Customer Name</label>
                                    <!-- <input type="text" class="form-control form-control-lg custom-input" id="isolatedFilterCustumername"> -->
                                    <select id="isolatedFilterCustumername" class="filter-select" multiple></select>
                                </div>
                            </div>
                            <!-- Email -->
                            <div class="col-md-6 mb-2">
                                <div class="form-item" style="max-height: 40px;">
                                    <label for="isolatedFilterEmail">Email</label>
                                    <!-- <input type="text" class="form-control form-control-lg custom-input" id="isolatedFilterEmail"> -->
                                    <select id="isolatedFilterEmail" class="filter-select" multiple></select>
                                </div>
                            </div>
                            <!-- Contact Number -->
                            <div class="col-md-6 mb-2">
                                <div class="form-item" style="max-height: 40px;">
                                    <label for="isolatedFilterContactnumber">Contact No.</label>
                                    <!-- <input type="text" class="form-control form-control-lg custom-input" id="isolatedFilterContactnumber"> -->
                                    <select id="isolatedFilterContactnumber" class="filter-select" multiple></select>
                                </div>
                            </div>
                            <!-- Location -->
                            <div class="col-md-6 mb-2">
                                <div class="form-item" style="max-height: 40px;">
                                    <label for="isolatedFilterLocation">Location</label>
                                    <!-- <input type="text" class="form-control form-control-lg custom-input" id="isolatedFilterLocation"> -->
                                    <select id="isolatedFilterLocation" class="filter-select" multiple></select>
                                </div>
                            </div>
                            <!-- Source of Lead -->
                            <div class="col-md-6 mb-2">
                                <div class="form-item" style="max-height: 40px;">
                                    <label for="isolatedFilterSourceOfLead">Source of Lead</label>
                                    <!-- <input type="text" class="form-control form-control-lg custom-input" id="isolatedFilterSourceOfLead"> -->
                                    <select id="isolatedFilterSourceOfLead" class="filter-select" multiple></select>
                                </div>
                            </div>
                            <!-- Status -->
                            <div class="col-md-6 mb-2">
                                <div class="form-item">
                                    <label for="isolatedFilterStatus">Status</label>
                                    <!-- <input type="text" class="form-control form-control-lg custom-input" id="isolatedFilterStatus"> -->
                                    <select id="isolatedFilterStatus" class="filter-select" multiple></select>
                                </div>
                            </div>
                            <!-- Assigned Project Name -->
                            <div class="col-md-6 mb-2">
                                <div class="form-item" style="max-height: 40px;">
                                    <label for="isolatedFilterAssignedProjectName">Assigned Project Name</label>
                                    <!-- <input type="text" class="form-control form-control-lg custom-input" id="isolatedFilterAssignedProjectName"> -->
                                    <select id="isolatedFilterAssignedProjectName" class="filter-select" multiple></select>
                                </div>
                            </div>
                            <!-- Assigned User Name -->
                            <div class="col-md-6 mb-2">
                                <div class="form-item" style="max-height: 40px;">
                                    <label for="isolatedFilterAssignedUserName">Assigned User Name</label>
                                    <!-- <input type="text" class="form-control form-control-lg custom-input" id="isolatedFilterAssignedUserName"> -->
                                    <select id="isolatedFilterAssignedUserName" class="filter-select" multiple></select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="margin: 0 auto;z-index: 999999999;">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal" id="isolatedCancleFilter">Close</button>
                    <button type="button" class="btn btn-danger" id="isolatedClearFiltersBtn">Clear Filters</button>
                    <button type="button" class="btn btn-primary" id="isolatedApplyFiltersBtn">Apply Filters</button>
                </div>
            </div>
        </div>
    </div>
<!-- Isolated Filter Modal End -->
<!-- upload excel popup -->
    <div class="modal fade" id="uploadExcelPopup" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title">Upload Files</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="close"></button>
                </div>
                <form id="uploadForm" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="container">
                            <div class="card p-5" >
                                <div class="drop_box">
                                    <h6 style="text-transform:uppercase">Files Supported: xlsx, xls, csv</h6>
                                    <div class="upload-wrap">
                                        <input type="file" name="file" accept=".xlsx, .xls, .csv" required class="upload-input">
                                    </div>
                                </div>
                            </div>
                            <a class="excelbutton accessbtn" id="download-excel-ex" style="cursor:pointer;"><i class="bi bi-file-earmark-spreadsheet"></i>Download Sample Example</a>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <div class="col-lg-12 text-center">
                            <button type="submit" name="submit" class="btn btn-sm btn-primary">Upload</button>
                            <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
<!-- THis upload Form Ends -->
<!-- Status Modal -->
    <div class="modal fade" id="viewStatusModal" tabindex="-1" role="dialog" aria-labelledby="viewStatusModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title" id="viewStatusModalLabel"><i class="bi bi-eye"></i> View Status</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <table class="table-bordered">
                        <thead>
                            <tr>
                                <th>User Unique ID</th>
                                <th>Project name</th>
                                <th>Status</th>
                                <th>History</th>
                            </tr>
                        </thead>
                        <tbody id="statusModalData">
                           
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
<!-- Status Modal End-->
<!-- Remarks Modal -->
    <div class="modal fade" id="viewRemarksModal" tabindex="-1" role="dialog" aria-labelledby="viewRemarksModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title" id="viewRemarksModalLabel">View Remarks</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <table class="table-bordered">
                        <thead>
                            <tr>
                                <th>User Unique ID</th>
                                <th>Project name</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody id="remarksModalData">

                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
<!-- Remarks Modal End -->
<!-- Assign User Popup -->
    <div class="modal fade" id="assignModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title">Assign Users</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                    </button>
                </div>
                <form id="assign-form" >
                    <div class="modal-body">
                        <p><b>Total Leads</b>: <span id="selected-count"><b> 0</b></span></p>
                        <input type="hidden" id="selected-ids" name="selected_ids">
                        <!-- Hidden input to store selected users -->
                        <input type="hidden" name="users" id="hidden-users">

                        <div class="dropdown-container" class="assigned-users-list">
                            <label for="assignprojectname">Project Name:</label>
                            <input type="text" id="assignprojectname" name="assignprojectname" class="form-control search-box" placeholder="Enter Project Name...">
                        </div>

                        <label for="selected-users">Assigned Users:</label>
                        <div id="assigned-users-container" class="assigned-users-list"></div>

                        <!-- Search for Users Dropdown -->
                        <label for="users">Select User(s):</label>
                        <div class="dropdown-container">
                            <input type="text" id="user-search" class="form-control search-box" placeholder="Search users...">
                            <select id="users" multiple class="form-select">
                                <!-- Options populated dynamically from PHP -->
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <div class="col-lg-12 text-center">
                            <button type="button" id="modal-assign-button" class="btn btn-sm btn-primary">Assign</button>
                            <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
<!-- Assign User PopUp model End -->
<!-- Modal Structure for Assigned Users Start -->
    <div class="modal fade" id="assignUserModal" tabindex="-1" role="dialog" aria-labelledby="assignUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title" id="assignUserModalLabel">Assigned Users</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" >
                <p id="assignUserList"></p>
                </div>
            </div>
        </div>
    </div>
<!-- Modal Structure for Assigned Users End -->
<!-- History Model for leads Start -->
    <div class="unique-status-sidebar" id="uniqueLeadHistorySidebar" style="z-index: 99999999;">
        <div class="unique-top-sect">
            <h1 class="unique-sidebar-title" ><b>Lead History</b></h1>
            <button class="unique-close-btn" id="uniqueCloseSidebar">&times;</button>
        </div>
        <div class="unique-mid-sect">
            <div class="unique-bottom-boxes">
                <div class="unique-left-box" style="border-right:1px solid #ccc">
                    <h4>Cus. Name</h4>
                    <h6 id="lead_user_name"></h6>
                </div>
                <div class="unique-right-box">
                    <h4>Cus. Number</span>
                    <h6 id="lead_user_number"></h6>
                </div>
            </div>
            <ul class="unique-lead-history" id="followUpHistory">
                
            </ul>
        </div>
        <div class="unique-btm-sect">
            <div class="unique-bottom-static d-flex">
                <div class="unique-left-static" style="border-right:1px solid #ccc">
                    <h4>Lead assigned on</h4>
                    <h6 id="assigned_date_leads"></h6>
                </div>
                <div class="unique-right-static">
                    <h4>Lead assigned by </span>
                    <h6 id="assigned_by_user"></h6>
                </div>
            </div>
        </div>
    </div>
<!-- History Model for leads End -->
<!-- History Call Model for leads Start -->
    <div class="unique-status-sidebar" id="uniqueCallHistorySidebar" style="z-index: 9999999;">
        <div class="unique-top-sect">
            <h1 class="unique-sidebar-title" ><b>Call History</b></h1>
            <button class="unique-close-btn" id="uniqueCloseCallSidebar">&times;</button>
        </div>
        <div class="unique-mid-sect">
            <div class="unique-bottom-boxes">
                <div class="unique-left-box" style="border-right:1px solid #ccc">
                    <h4>Cus. Name</h4>
                    <h6 id="lead_user_callname"></h6>
                </div>
                <div class="unique-right-box">
                    <h4>Cus. Number</span>
                    <h6 id="lead_user_callnumber"></h6>
                </div>
            </div>
            <ul class="unique-lead-history" id="followUpCallHistory">
                
            </ul>
        </div>
        <div class="unique-btm-sect">
            <div class="unique-bottom-static d-flex">
                <div class="unique-left-static" style="border-right:1px solid #ccc">
                    <h4>Lead assigned on</h4>
                    <h6 id="assigned_date_callleads"></h6>
                </div>
                <div class="unique-right-static">
                    <h4>Lead assigned by </span>
                    <h6 id="assigned_by_calluserr"></h6>
                </div>
            </div>
        </div>
    </div>
<!-- History CAll Model for leads End -->
<!-- Add Lead Modal -->
    <div class="modal fade" id="addLeadModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form id="addLeadForm" method="POST">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New Lead</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="leadName">Name</label>
                            <input type="text" class="form-control" id="leadName" name="name" required>
                        </div>
                        <div class="form-group">
                            <label for="leadNumber">Number</label>
                            <input type="text" class="form-control" id="leadNumber" name="number" required>
                        </div>
                        <div class="form-group">
                            <label for="leadEmail">Email</label>
                            <input type="email" class="form-control" id="leadEmail" name="email" required>
                        </div>
                        <div class="form-group">
                            <label for="leadProject">Project</label>
                            <input type="text" class="form-control" id="leadProject" name="project" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-primary" id="submitLead">Add Lead</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
<!-- Add Lead Modal End -->
<!-- this is main table structure start -->
<div class="container">
        <div class="row">
            <div id="uploadMessage" class="alert" style="display: none;"></div>
                <div class="dt-layout-row-wrapper" style="padding: 0px 10px!important;">
                    <div class="col-lg-12">
                        <div class="dt-layout-row-top-header">
                            <div class="dt-layout-header">
                                <?php if ($tablename === 'subham323' || $tablename === 'NoUser323'): ?>
                                        <span id="totalLeads" style="display:none;"></span>
                                        <span id="myLeads" style="display:none;"></span>
                                        <span id="bookedLeads" style="display:none;"></span>
                                        <span id="eoicounterdata" style="display:none;"></span>
                                        <span id="deletedLeads" style="display:none;"></span>
                                        <span id="totalUnassigned" style="display:none;"></span>
                                        <span id="droppedLeads" style="display:none;"></span>
                                <?php else: ?>
                                    <button class="activebutton accessbtn" id="totalLeads" data-lead-type="total"><i class="bi bi-activity"></i> Total (0) </button>
                                    <button class="deactivebutton accessbtn" id="myLeads" data-lead-type="my"><i class="bi bi-graph-up"></i> My Leads (0)</button>
                                    <a href="/superadmin_new/crm/user-eoi">
                                        <button class="salarybutton accessbtn" id="eoicounterdata"><i class="bi bi-card-checklist"></i> EOI (0)</button>
                                    </a>
                                    <span id="bookedLeads" style="display:none;"></span>
                                    <button class="filterbutton accessbtn" id="deletedLeads" data-lead-type="deleted"><i class="bi bi-trash" style="color:red"></i>Deleted Leads (0)</button>
                                    <button class="salarybutton accessbtn" id="totalUnassigned" data-lead-type="unassigned"><i class="bi bi-bell-slash"></i> Unassigned (0)</button>
                                    <button class="filterbutton accessbtn" id="droppedLeads" data-lead-type="dropped"><i class="bi bi-droplet"></i>Dropped (0)</button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="dt-layout-row-top">
                            <div class="dt-layout-row-top-il">
                                <?php if ($tablename === 'subham323' || $tablename === 'NoUser323'): ?>
                                        <span style="display:none;"></span>
                                        <span id="activeLeads" style="display:none;"></span>
                                        <span id="freshLeads" style="display:none;"></span>
                                        <span id="pendingLeads" style="display:none;"></span>
                                <?php else: ?>
                                    <button type="button" class="salarybutton accessbtn" data-bs-toggle="modal" data-bs-target="#addLeadModal">
                                        <i class="bi bi-person-add"></i> Add Lead
                                    </button>
                                    <button class="acitvebutton accessbtn" id="activeLeads" data-lead-type="active"><i class="bi bi-activity"></i> Active (0)</button>
                                    <button class="assignbutton accessbtn" id="freshLeads" data-lead-type="fresh"><i class="bi bi-cloud-plus"></i> New (0)</button>
                                    <button class="deactivebutton accessbtn" id="pendingLeads" data-lead-type="pending"><i class="bi bi-hourglass"></i> Pending (0)</button>
                                <?php endif; ?>
                                <?php if ($tablename === 'subham323' || $tablename === 'NoUser323'): ?>
                                        <span style="display:none;"></span>
                                        <!--<span id="assign-button" style="display:none;"></span>-->
                                        <span id="delete-selected-btn" style="display:none;"></span>
                                        <span id="downloadCsv" style="display:none;"></span>
                                <?php else: ?>
                                <button class="assignmodalbutton accessbtn"  id="assign-button" data-bs-toggle="modal" data-bs-target="#assignModal" disabled><i class="bi bi-people"></i>Assign Users</button>
                                    <button class="deletebutton accessbtn" id="delete-selected-btn"><i class="bi bi-trash"></i>Delete Selected</button>
                                    <button id="downloadCsv" class="downloadbutton accessbtn">
                                      <i class="bi bi-download"></i> Download Leads
                                    </button>
                                    <!--<span id="exportStatus" class="ms-2"></span>-->
                                <button class="uploadbutton accessbtn uploadExcelPopup" data-bs-toggle="modal" data-bs-target="#uploadExcelPopup"><i class="bi bi-cloud-arrow-up"></i>Upload Excel</button>
                                <?php endif; ?>
                                <button class="filterbutton accessbtn" id="multicolumFilter" data-toggle="modal" data-target="#isolatedFilterModal"><i class="bi bi-filter"></i>Filter</button>
                                <!--<button class="excelbutton accessbtn" id="download-excel-ex"><i class="bi bi-file-earmark-spreadsheet"></i>Excel Example</button>-->
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
                                         <option value="500">500</option> 
                                         <option value="1000">1000</option> 
                                         <option value="1500">1500</option> 
                                         <option value="2000">2000</option> 
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
                                <div id="responseMessage" style="display:none;"></div>
                                <form id="bulkDeleteForm" method="POST">
                                <table id="myTable">
                                    <thead>
                                        <tr>
                                            <th><input type="checkbox" id="select-all"></th>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <?php if ($tablename === 'subham323'): ?>
                                                    <span style="display:none;"></span>
                                            <?php else: ?>
                                                <th>Number</th>
                                            <?php endif; ?>
                                            <th>Location</th>
                                            <th>Project</th>
                                            <th>Lead Source</th>
                                            <th>Assigned User</th>
                                            <th>Status</th>
                                            <!--<th>Remarks</th>-->
                                            <th class="hide-column">Created At</th>
                                        </tr>
                                    </thead>
                                    <tbody id="uploaddata">
                                          
                                    </tbody>
                                </table>
                            </form>
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
<!-- this is main table structure start End -->
</div>
<!-- incentive main close -->
<script>
    const tablename = "<?php echo $_SESSION['tablename']; ?>";
</script>
<!-- this is my scripts for working project  -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.3/js/bootstrap.min.js"></script>
  <!-- Select2 JS -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
  <script src="https://www.searchhomesindia.in/assets/js/pullToRefresh.js"></script>
  <script type="text/javascript" src="./assets/js/get_data_api.js"></script>
  <script type="text/javascript" src="./assets/js/get_table_js.js"></script>
  <script type="text/javascript" src="./assets/js/get_downloads.js"></script>
  <script type="text/javascript" src="../assets/js/script.js"></script>
<!-- javascript for get the search in dropdown search  -->
    <script> 
        $(document).ready(function() {
        // Function to populate dropdowns based on the table data
        function populateDropdowns() {
            var locations = new Set();
            var projects = new Set();
            var source_of_lead = new Set();
            var assignedUsers = new Set();
            var statuses = new Set();
            var createdAtDates = new Set();

            $("#uploaddata tr").each(function() {
                var location = $(this).find("td:eq(4)").text().trim();
                var project = $(this).find("td:eq(5)").text().trim();
                var source_of_lead = $(this).find("td:eq(6)").text().trim();
                var assignedUser = $(this).find("td:eq(7)").text().trim();
                var status = $(this).find("td:eq(8)").text().trim();
                var createdAt = $(this).find("td:eq(9)").text().trim();

                if (location) locations.add(location);
                if (project) projects.add(project);
                if (source_of_lead) source_of_lead.add(source_of_lead);
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
        const CURRENT_USER = "<?php echo $tablename; ?>";
    </script>
<!-- javascript for get the search in dropdown search End  -->
<!-- this is my scripts for working project  End -->
</body>
</html>