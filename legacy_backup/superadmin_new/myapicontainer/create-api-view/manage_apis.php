<?php include('../htmlopen.php'); ?>
<!-- Include CSS -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />
<link href="https://cdnjs.cloudflare.com/ajax/libs/select2-bootstrap-5-theme/1.3.0/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<!-- Include JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
<style>
     #responseMessage {
            padding: 10px;
            margin-bottom: 10px;
            border: 1px solid transparent;
            border-radius: 5px;
            display: none;
        }
        #responseMessage.success {
            background-color: #d4edda;
            color: #155724;
            border-color: #c3e6cb;
        }
        #responseMessage.error {
            background-color: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }
        /* Assigned user badge */
        .assigned-user {
            display: inline-flex;
            align-items: center;
            background: #eef2ff;
            border: 1px solid #c7d2fe;
            border-radius: 16px;
            padding: 4px 10px;
            font-size: 13px;
            font-weight: 500;
            color: #1e3a8a;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }

        .assigned-user:hover {
            background: #f1f5f9 !important;
            transform: translateY(-1px) !important;
        }

        /* Optional avatar before username */
        .assigned-user .avatar {
            width: 20px !important;
            height: 20px !important;
            border-radius: 50% !important;
            background-color: #e5e7eb !important;
            margin-right: 6px !important;
            display: inline-block !important;
        }

        /* Remove button */
        .removeUserBtn {
            background: transparent;
            border: none;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 14px;
            color: #1e3a8a;
            margin-left: 6px;
            cursor: pointer;
            line-height: 1;
        }

        .removeUserBtn:hover {
            background: #fecaca;
            color: #b91c1c;
        }

        /* Form labels */
        #editApiForm label {
            color: #0d6efd !important;
            font-weight: 600 !important;
            font-size: 14px !important;
        }

        /* Select2 dropdown */
        .select2-container--bootstrap-5 .select2-selection {
            min-height: 42px !important;
            border-radius: 8px !important;
            font-size: 14px !important;
            padding: 6px !important;
        }

        .select2-selection__choice {
            background-color: #0d6efd !important;
            color: white !important;
            border-radius: 4px !important;
            padding: 2px 8px !important;
        }

        .select2-results__options {
            max-height: 200px !important;
            overflow-y: auto !important;
        }
        /* Shared */
.loginBtn {
  box-sizing: border-box;
  position: relative;
  /* width: 13em;  - apply for fixed size */
  margin: 0.2em;
  padding: 0 15px 0 46px;
  border: none;
  text-align: left;
  line-height: 34px;
  white-space: nowrap;
  border-radius: 0.2em;
  font-size: 16px;
  color: #FFF;
}
.loginBtn:before {
  content: "";
  box-sizing: border-box;
  position: absolute;
  top: 0;
  left: 0;
  width: 34px;
  height: 100%;
}
.loginBtn:focus {
  outline: none;
}
.loginBtn:active {
  box-shadow: inset 0 0 0 32px rgba(0,0,0,0.1);
}
/* Facebook */
.loginBtn--facebook {
  background-color: #4C69BA;
  background-image: linear-gradient(#4C69BA, #3B55A0);
  /*font-family: "Helvetica neue", Helvetica Neue, Helvetica, Arial, sans-serif;*/
  text-shadow: 0 -1px 0 #354C8C;
}
.loginBtn--facebook:before {
  border-right: #364e92 1px solid;
  background: url('https://s3-us-west-2.amazonaws.com/s.cdpn.io/14082/icon_facebook.png') 6px 6px no-repeat;
}
.loginBtn--facebook:hover,
.loginBtn--facebook:focus {
  background-color: #5B7BD5;
  background-image: linear-gradient(#5B7BD5, #4864B1);
}
.side-menu li.sideactive70 {
    background: var(--shicol);
    position: relative;
}
.side-menu li.sideactive70 a {
    color: white;
}
/* Base button styles */
button.activebutton.accessbtn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background-color: #4CAF50;
    color: white;
    border: none;
    border-radius: 8px;
    padding: 0px 7px;
    font-size: 12px;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    height: 34px;
}

/* Icon spacing */
button.activebutton.accessbtn i {
    margin-right: 8px; /* Space between icon and text */
    font-size: 18px; /* Icon size */
}

/* Hover effects */
button.activebutton.accessbtn:hover {
    background-color: #45a049; /* Darker green on hover */
    box-shadow: 0 6px 8px rgba(0, 0, 0, 0.15); /* Enhanced shadow */
}

/* Active effects */
button.activebutton.accessbtn:active {
    transform: scale(0.98); /* Slightly shrink on click */
    background-color: #3e8e41; /* Even darker green */
}

/* Focus styles */
button.activebutton.accessbtn:focus {
    outline: 2px solid #80C1FF; /* Light blue outline */
    outline-offset: 2px; /* Space between button and outline */
}
/* Deactive button styles */
button.deactivebutton.accessbtn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background-color: #f44336;
    color: white;
    border: none;
    border-radius: 8px;
    padding: 0px 7px;
    font-size: 11px;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    height: 34px;
}

/* Hover effects */
button.deactivebutton.accessbtn:hover {
    background-color: #e53935; /* Darker red on hover */
    box-shadow: 0 6px 8px rgba(0, 0, 0, 0.15); /* Enhanced shadow */
}

/* Active effects */
button.deactivebutton.accessbtn:active {
    transform: scale(0.98); /* Slightly shrink on click */
    background-color: #d32f2f; /* Even darker red */
}

/* Focus styles */
button.deactivebutton.accessbtn:focus {
    outline: 2px solid #FFCDD2; /* Light red outline */
    outline-offset: 2px; /* Space between button and outline */
}
button.deactivebutton.accessbtn i {
    margin-right: 8px; /* Space between icon and text */
    font-size: 18px; /* Icon size */
    color:white;
}
/*group manager CSS START*/
#groupManagerModal{--gm-bg:#fbfdff;--gm-surface:#ffffff;--gm-muted:#74808a;--gm-primary:#1767b2;--gm-accent:#ffb400;--gm-success:#1e8e3e;--gm-danger:#d23f3f;font-family:Inter,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;color:#15303b}#groupManagerModal .gm--card{border-radius:12px;overflow:visible;box-shadow:0 12px 40px rgba(20,40,60,.08);border:none}#groupManagerModal .gm--header{background:linear-gradient(180deg,#fff,#fbfcff);padding:18px 22px;border-bottom:1px solid rgba(18,40,60,.06);display:flex;align-items:center;justify-content:space-between}#groupManagerModal .gm--title{margin:0;font-weight:700;font-size:1.15rem;color:#0f2a3a;letter-spacing:-.2px}#groupManagerModal .gm--close{background:0 0;border:none;color:#56707b;font-size:18px;opacity:.9}#groupManagerModal .gm--body{background:var(--gm-bg);padding:18px}#groupManagerModal .gm--grid{display:grid;grid-template-columns:360px 1fr;gap:20px;align-items:start}#groupManagerModal .gm--pane{background:var(--gm-surface);border-radius:10px;padding:14px;border:1px solid rgba(16,38,56,.04);box-shadow:0 6px 18px rgba(16,38,56,.02)}#groupManagerModal .gm--left{display:flex;flex-direction:column;gap:12px;min-height:420px}#groupManagerModal .gm--right{display:flex;flex-direction:column;gap:10px;min-height:420px}#groupManagerModal .gm--search-row,#groupManagerModal .gm--search-rows{display:flex;gap:8px;align-items:center}#groupManagerModal .gm--search-input{flex:1;padding:10px 12px;border-radius:10px;border:1px solid rgba(16,38,56,.06);background:linear-gradient(180deg,#fff,#fbfdff);font-size:.95rem;color:#0d2a36;outline:0;transition:box-shadow .12s,border-color .12s,transform .05s}#groupManagerModal .gm--search-input:focus{border-color:rgba(23,103,178,.18);box-shadow:0 6px 22px rgba(23,103,178,.06);transform:translateY(-1px)}#groupManagerModal .gm--icon-btn{min-width:40px;min-height:40px;display:inline-grid;place-items:center;border-radius:10px;border:1px solid rgba(16,38,56,.04);background:#fff;cursor:pointer;color:#3c5563;font-weight:700}#groupManagerModal .gm--icon-btn:hover{background:#f3f8ff;transform:translateY(-1px)}#groupManagerModal .gm--list-wrap{flex:1;overflow:auto;border-radius:10px;border:1px solid rgba(16,38,56,.03);background:linear-gradient(180deg,#fff,#fbfdff);padding:8px}#groupManagerModal .gm--groups-list{width:100%;height:100%;border:none;padding:6px;font-size:.96rem;color:#173240;background:0 0;outline:0;-webkit-appearance:none}#groupManagerModal .gm--groups-list option{padding:8px;font-weight:600}#groupManagerModal .gm--controls{display:flex;gap:10px;align-items:center;justify-content:space-between}#groupManagerModal .gm--controls .btn{border-radius:8px;padding:8px 12px;font-weight:600}#groupManagerModal .gm--details-head{margin-bottom:4px}#groupManagerModal .gm--group-title{margin:0;font-size:1.05rem;font-weight:700;color:#0c2e3a}#groupManagerModal .gm--small-meta{color:var(--gm-muted);font-size:.88rem}#groupManagerModal .gm--assign-row{display:flex;gap:10px;align-items:center}#groupManagerModal .gm--assign-row .select2-container--bootstrap-5 .select2-selection--multiple{border-radius:8px;min-height:48px;padding:6px 8px;border:1px solid rgba(16,38,56,.06);background:#fff;box-shadow:none}#groupManagerModal .gm--assign-row .select2-container--bootstrap-5 .select2-selection__choice{background:#1976d2;border:none;color:#fff;font-weight:600;margin:4px 6px 4px 0;border-radius:6px}#groupManagerModal .gm--hint{color:var(--gm-muted);font-size:.86rem}#groupManagerModal .gm--rows-container{max-height:360px;overflow:auto;padding:12px;border-radius:10px;border:1px solid rgba(16,38,56,.03);background:linear-gradient(180deg,#fff,#fbfdff);box-shadow:inset 0 1px 0 rgba(255,255,255,.6)}#groupManagerModal .gm--rows-container .form-check{display:flex;gap:12px;align-items:flex-start;padding:10px;border-radius:8px;transition:background .08s,transform .06s;margin-bottom:8px;border:1px solid rgba(16,38,56,.02)}#groupManagerModal .gm--rows-container .form-check:hover{background:rgba(14,56,112,.03);transform:translateY(-2px)}#groupManagerModal .gm--rows-container .form-check input[type=checkbox]{margin-top:6px;width:18px;height:18px}#groupManagerModal .gm--rows-container label{font-weight:600;color:#132b34;font-size:.95rem}#groupManagerModal .gm--rows-actions{display:flex;gap:12px;margin-top:10px}#groupManagerModal .gm--rows-actions .btn{padding:10px 14px;border-radius:8px;font-weight:700;box-shadow:0 6px 18px rgba(22,103,178,.06)}#groupManagerModal .gm--rows-actions .btn-warning{background:var(--gm-accent);color:#15303b;border:none}#groupManagerModal .gm--footer-actions{display:flex;gap:12px;justify-content:flex-end;margin-top:14px;align-items:center}#groupManagerModal .modal-dialog{max-width:1200px;margin:30px auto}#groupManagerModal .modal-content{overflow:visible}#groupManagerModal .gm--list-wrap::-webkit-scrollbar,#groupManagerModal .gm--rows-container::-webkit-scrollbar{height:10px;width:10px}#groupManagerModal .gm--list-wrap::-webkit-scrollbar-thumb,#groupManagerModal .gm--rows-container::-webkit-scrollbar-thumb{background:rgba(22,103,178,.12);border-radius:8px}#groupManagerModal .gm--list-wrap::-webkit-scrollbar-track,#groupManagerModal .gm--rows-container::-webkit-scrollbar-track{background:0 0}#groupManagerModal .gm--alert{padding:10px 12px;border-radius:8px;background:#fff6e6;border:1px solid rgba(235,150,25,.12);color:#6b3f00;font-weight:600}#groupManagerModal .gm--empty{padding:24px;border-radius:8px;color:var(--gm-muted);font-size:.95rem;text-align:center;border:1px dashed rgba(16,38,56,.04);background:linear-gradient(180deg,#fff,#fbfdff)}@media (max-width:1100px){#groupManagerModal .modal-dialog{max-width:980px}#groupManagerModal .gm--grid{grid-template-columns:320px 1fr}}@media (max-width:820px){#groupManagerModal .modal-dialog{max-width:760px}#groupManagerModal .gm--grid{grid-template-columns:1fr;gap:12px}#groupManagerModal .gm--left{order:2}#groupManagerModal .gm--right{order:1}#groupManagerModal .gm--rows-container{max-height:260px}}#groupManagerModal .text-muted{color:var(--gm-muted)!important;font-size:.88rem}
/*group manager CSS End*/
</style>
<?php include('../../header.php'); ?>
<!-- Main Content -->
<div class="content">
    <!-- this is main table structure start -->
<div class="container">
     <!-- API Creation Popup start-->
            <div class="modal fade" id="apicreationModal" tabindex="-1" role="dialog" aria-labelledby="apicreationModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content">
                        <form id="createApiForm" method="POST">

                            <div class="modal-header">
                                <h6 class="modal-title">API/Group</h6>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            
                            <div class="modal-body">
                                <div class="container">
                                    <div class="row"> 
                                    
                                        <div class="col-md-12 mb-2"> 
                                            <div class="form-item">
                                                <label for="source_name">Lead Source</label>
                                                <select name="source_name" id="source_name" required="" class="form-control form-control-lg custom-input">
                                                    <option value="">Select Source</option>
                                                    <option value="google ads">Google</option>
                                                    <option value="magicbricks ads">Magicbricks</option>
                                                    <option value="99acres ads">99acres</option>
                                                    <option value="housing.com ads">housing.com</option>
                                                    <option value="WhatsApp Bot">WhatsApp Bot</option>
                                                    <option value="group">Group</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="col-md-12 mb-2">
                                            <div class="form-item">
                                                <label for="projectname">Project Name:</label>
                                                <input type="text" id="project_name" required name="project_name" class="form-control form-control-lg custom-input">
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-12 mb-2" id="groupNameContainer" style="display:none;">
                                            <div class="form-item">
                                                <label for="group_name">Group Name:</label>
                                                <input type="text" id="group_name" name="group_name" class="form-control form-control-lg custom-input">
                                            </div>
                                        </div>

                                        <div class="col-md-12 mb-2"> 
                                        <div class="form-item">
                                            <label for="users">Select Users</label>
                                            <select id="create_users" name="users[]" multiple="" class="form-control form-control-lg custom-input searchable-dropdown">
                                                
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-md-12 mb-2" style="overflow-y: scroll;height: 100px;"> 
                                        <div class="form-item">
                                            <!-- <label for="assign_user">Assign Users (comma-separated IDs)</label> -->
                                                <input type="hidden" id="assign_user" name="assign_user">
                                                <div id="createAssignedUsersContainer" class="d-flex flex-wrap mt-2"></div>
                                        </div>
                                    </div>

                                    </div>
                                </div>
                            </div>

                            <div class="modal-footer">
                                <div class="col-lg-12 text-center">
                                    <button type="submit" name="submit" id="createBtn" class="btn btn-sm btn-primary">Create API</button>
                                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                                </div>
                            </div>

                        </form>
                    </div>
                </div>
            </div>
        <!-- API creation PopUp model End -->
        <!-- API EDIT POPUP MODEL START -->
            <div class="modal fade" id="editApiModal" tabindex="-1" aria-labelledby="editApiModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <form id="editApiForm">
                            <div class="modal-header">
                                <h6 class="modal-title">Edit API</h6>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
        
                            <div class="modal-body">
                                <input type="hidden" id="editApiId" name="api_id">
        
                                <label class="fw-bold text-primary">Assigned Users</label>
                                <div id="assignedUsersContainer" class="mb-3"></div>
        
                                <div class="form-item mt-3">
                                    <label for="edit_users" class="form-label fw-bold text-primary">Select New Users</label>
                                    <select id="edit_users" class="form-control" multiple style="width:100%">
                                        <!-- Dynamic options -->
                                    </select>
                                </div>
                            </div>
        
                            <div class="modal-footer">
                                <button type="submit" class="btn btn-sm btn-primary">Save Changes</button>
                                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <!-- API EDIT POPUP MODEL END -->               
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
        <!--this is group model assign start-->
        <!-- Modal -->
        <div class="modal fade" id="assignGroupModal" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog">
            <form id="assignGroupForm">
              <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Assign selected APIs to Group</h5></div>
                <div class="modal-body">
                  <div class="mb-2">
                    <label for="targetGroup">Select Group</label>
                    <select id="targetGroup" name="group_id" class="form-control">
                      <!-- options will be populated via AJAX -->
                    </select>
                  </div>
                  <div id="assignFeedback" style="display:none;"></div>
                </div>
                <div class="modal-footer">
                  <button type="submit" class="btn btn-primary">Assign</button>
                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
              </div>
            </form>
          </div>
        </div>
        <!--this is group model assign End-->
        <!--group manage Start-->
        <!-- Button to open Group Manager -->
        <!-- Group Manager Modal -->
        <div class="modal fade" id="groupManagerModal" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-xl">
            <div class="modal-content gm--card">
              <div class="modal-header gm--header">
                <h5 class="modal-title gm--title">Group Manager</h5>
                <button type="button" class="btn-close gm--close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
        
              <div class="modal-body gm--body">
                <div class="gm--grid">
        
                  <!-- LEFT: groups list + search -->
                  <aside class="gm--pane gm--left">
                    <div class="gm--search-row">
                      <input id="gm_groupSearch" class="gm--search-input" placeholder="Search groups (name or project)...">
                      <button id="gm_clearGroupSearch" class="gm--icon-btn" title="Clear search">✕</button>
                    </div>
        
                    <div class="gm--list-wrap">
                      <select id="gm_groupsList" class="gm--groups-list" size="12" aria-label="Groups list"></select>
                    </div>
        
                    <div class="gm--controls">
                      <button id="gm_createGroupBtn" class="btn btn-success btn-sm">Create Group</button>
                      <button id="gm_deleteGroupBtn" class="btn btn-danger btn-sm">Delete Group</button>
                      <button id="gm_refreshBtn" class="btn btn-outline-secondary btn-sm">Refresh</button>
                    </div>
                  </aside>
        
                  <!-- RIGHT: group details -->
                  <section class="gm--pane gm--right">
                    <div id="gm_alert" class="gm--alert" style="display:none;"></div>
        
                    <div id="gm_details" class="gm--details" style="display:none;">
                      <div class="gm--details-head">
                        <h6 id="gm_groupTitle" class="gm--group-title">Group — <span class="gm--group-id">id</span></h6>
        
                        <div class="gm--small-meta">Edit members and associated API rows below</div>
                      </div>
        
                      <!-- Assign users (Select2) -->
                      <label class="form-label gm--label">Assign Users</label>
                      <div class="gm--assign-row">
                        <select id="gm_assignUsers" multiple="multiple" style="width:100%"></select>
                        <button id="gm_clearAssignUsersBtn" class="gm--icon-btn" title="Clear users">✕</button>
                      </div>
                      <small class="text-muted d-block gm--hint">Search or type to add users. Select2 search is active inside the control.</small>
        
                      <!-- Search for API rows -->
                      <div class="gm--search-rows">
                        <input id="gm_rowSearch" class="gm--search-input" placeholder="Search API rows (project name / api_key / lead source)...">
                        <button id="gm_clearRowSearch" class="gm--icon-btn" title="Clear row search">✕</button>
                      </div>
        
                      <!-- Associated API Rows -->
                      <label class="form-label gm--label mt-2">Associated API Rows</label>
                      <div id="gm_rowsContainer" class="gm--rows-container" aria-live="polite">
                        <!-- JS will append rows (checkbox items) here -->
                      </div>
        
                      <div class="gm--rows-actions">
                        <button id="gm_addSelectedRowsBtn" class="btn btn-primary btn-sm">Add selected API rows to group</button>
                        <button id="gm_removeSelectedRowsBtn" class="btn btn-warning btn-sm">Remove selected API rows from group</button>
                      </div>
        
                      <div class="gm--footer-actions">
                        <button id="gm_saveBtn" class="btn btn-primary">Save changes</button>
                        <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                      </div>
                    </div>
        
                    <div id="gm_empty" class="gm--empty text-muted">Select a group to see details.</div>
                  </section>
                </div>
              </div>
            </div>
          </div>
        </div>
        <!--group manage End-->
        <div class="row">
            <div id="responseMessage"></div>
            <div id="uploadMessage" class="alert" style="display: none;"></div>
                <div class="dt-layout-row-wrapper" style="padding: 0px 10px!important;">
                    <div class="col-lg-12">
                        <div class="dt-layout-row-top-header">
                            <div class="dt-layout-header">
                                <a href="https://www.facebook.com/v15.0/dialog/oauth?client_id=1322640825707670&redirect_uri=https://www.searchhomesindia.in/superadmin_new/myapicontainer/facebook/login&scope=pages_manage_ads,pages_read_engagement,leads_retrieval">
                                    <button class="loginBtn loginBtn--facebook">
                                      Login with Facebook
                                    </button>
                                </a>
                                <button class="activebutton accessbtn" onclick="fetchLeads()"><i class="bi bi-arrow-repeat"></i> Sync Leads</button>
                                <button class="deactivebutton accessbtn" onclick="refreshTokens()"><i class="bi bi-arrow-clockwise"></i> Refresh Tokens</button>
                                <button id="openGroupManagerBtn" class="btn btn-secondary me-2">Manage Groups</button>
                            </div>
                        </div>
                        <div class="dt-layout-row-top mt-2">
                            <div class="dt-layout-row-top-il">
                                <!-- <button class="acitvebutton accessbtn" id="activeLeads"><i class="bi bi-activity"></i> Active (0)</button>
                                <button class="assignbutton accessbtn" id="freshLeads"><i class="bi bi-cloud-plus"></i> New (0)</button>
                                <button class="deactivebutton accessbtn" id="pendingLeads"><i class="bi bi-hourglass"></i> Pending (0)</button>
                                <a href="http://localhost/incentiveapp_integration/superadmin_new/crm/user-eoi">
                                    <button class="salarybutton accessbtn" id="eoicounterdata"><i class="bi bi-card-checklist"></i> EOI (0)</button>
                                </a>
                                <button class="excelbutton accessbtn" id="download-excel-ex"><i class="bi bi-file-earmark-spreadsheet"></i>Excel Example</button>
                                <button class="uploadbutton accessbtn uploadExcelPopup" data-bs-toggle="modal" data-bs-target="#uploadExcelPopup"><i class="bi bi-cloud-arrow-up"></i>Upload Excel</button>-->
                                <button class="acitvebutton accessbtn" data-bs-toggle="modal" data-bs-target="#apicreationModal"><i class="bi bi-braces"></i> API Creation</button>
                                <button class="assignmodalbutton accessbtn"  id="assign-button" data-bs-toggle="modal" data-bs-target="#assignModal" disabled><i class="bi bi-people"></i>Assign Users</button> 
                                <button class="deletebutton accessbtn" id="delete-selected-btn"><i class="bi bi-trash"></i>Delete Selected</button>
                                <button class="filterbutton accessbtn"><i class="bi bi-filter"></i>Filter</button>
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
                        <button id="assignToGroupBtn" class="btn btn-warning" style="display:none;">Assign to Group</button>
                        <div class="dt-layout-row-mid">
                            <div class="scrollable-table">
                                <table id="myTable">
                                    <thead>
                                        <tr>
                                            <th><input type="checkbox" id="selectAll" /></th>
                                            <th>Project Name</th>
                                            <th>API Key</th>
                                            <th>Assigned Users</th>
                                            <th>API Source</th>
                                            <th>Leads Count</th>
                                            <th>Actions</th>
                                            <th>Create At</th>
                                        </tr>
                                    </thead>
                                    <tbody id="uploaddata">
                                        
                                    </tbody>
                                </table>
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
</div>
<!-- Toast container (put near end of body) -->
<div aria-live="polite" aria-atomic="true" style="position: fixed; top: 1rem; right: 1rem; z-index: 1080;">
  <div id="globalToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="4000" style="min-width: 260px;">
    <div class="toast-header">
      <strong class="me-auto" id="globalToastTitle">Notification</strong>
      <small class="text-muted" id="globalToastTime"></small>
      <button type="button" class="btn-close ms-2 mb-1" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
    <div class="toast-body" id="globalToastBody"></div>
  </div>
</div>


<script>
    document.getElementById('source_name').addEventListener('change', function () {
    const fbFormField = document.getElementById('fb_form_id_container');
    if (this.value === 'facebook ads') {
        fbFormField.style.display = 'block'; // Show Facebook Form ID field
    } else {
        fbFormField.style.display = 'none'; // Hide Facebook Form ID field
    }
});
</script>
<script>
    function fetchLeads() {
    fetch('https://www.searchhomesindia.in/superadmin_new/myapicontainer/facebook/fetch_leads')
        .then(response => response.text()) // Or .json() if it's JSON
        .then(data => {
            alert('Leads Synced Successfully: ' + data);
            console.log('Response:', data);
        })
        .catch(error => {
            console.error('Error syncing leads:', error);
            alert('Failed to sync leads. Check the console for details.');
        });
}

function refreshTokens() {
    fetch('https://www.searchhomesindia.in/superadmin_new/myapicontainer/facebook/refresh_token')
        .then(response => response.text()) // Or .json() if it's JSON
        .then(data => {
            alert('Tokens Refreshed Successfully: ' + data);
            console.log('Response:', data);
        })
        .catch(error => {
            console.error('Error refreshing tokens:', error);
            alert('Failed to refresh tokens. Check the console for details.');
        });
}

</script>
<script>
document.addEventListener('DOMContentLoaded', function () {

    const sourceSelect = document.getElementById('source_name');
    const projectInput = document.getElementById('project_name');
    const projectField = projectInput.closest('.col-md-12');

    const groupInput = document.getElementById('group_name');
    const groupField = document.getElementById('groupNameContainer');

    const assignInput = document.getElementById('assign_user');
    const userSelect = document.getElementById('create_users');
    const createBtn = document.getElementById('createBtn');

    const formFields = [
        assignInput.closest('.col-md-12'),
        userSelect.closest('.col-md-12')
    ];

    sourceSelect.addEventListener('change', function () {
        const value = this.value;

        /** 🔹 If GROUP is selected **/
        if (value === 'group') {

            // Button text change
            createBtn.textContent = "Create Group";

            // Show group name
            groupField.style.display = 'block';
            groupInput.setAttribute('required', true);

            // Hide project name
            projectField.style.display = 'none';
            projectInput.removeAttribute('required');
            projectInput.value = '';

            // Show user fields
            formFields.forEach(field => field.style.display = 'block');

            return; 
        }

        /** 🔹 If PORTAL lead source **/
        const portalSources = ['magicbricks ads', '99acres ads', 'housing.com ads'];
        const isPortal = portalSources.includes(value);

        if (isPortal) {
            createBtn.textContent = "Create API"; // reset button name

            projectInput.value = 'Portal API';
            assignInput.value = 'Vipul0001';
            userSelect.selectedIndex = -1;

            projectField.style.display = 'block';
            groupField.style.display = 'none';

            formFields.forEach(field => field.style.display = 'none');

            return;
        }

        /** 🔹 Normal API sources (Google / WhatsApp Bot) **/
        createBtn.textContent = "Create API"; // reset button name

        projectField.style.display = 'block';
        projectInput.setAttribute('required', true);

        groupField.style.display = 'none';
        groupInput.value = '';

        formFields.forEach(field => field.style.display = 'block');
    });

});
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  const selectAll = document.getElementById('selectAll');
  const assignBtn = document.getElementById('assignToGroupBtn');
  const assignGroupForm = document.getElementById('assignGroupForm');

  // If assign button or form is missing, just return gracefully (no errors)
  if (!assignBtn || !assignGroupForm) {
    // still wire selectAll checkbox functionality below if present
    if (!selectAll) return;
  }

  function getSelectedIds() {
    return Array.from(document.querySelectorAll('.rowCheckbox:checked')).map(ch => ch.value);
  }

  function updateAssignBtnVisibility() {
    const any = getSelectedIds().length > 0;
    if (assignBtn) assignBtn.style.display = any ? 'inline-block' : 'none';
  }

  // When select all clicked
  if (selectAll) {
    selectAll.addEventListener('change', function() {
      document.querySelectorAll('.rowCheckbox').forEach(cb => cb.checked = this.checked);
      updateAssignBtnVisibility();
    });
  }

  // When single row checkbox toggled (use event delegation if rows are dynamic)
  document.addEventListener('change', function(e) {
    if (e.target && e.target.classList && e.target.classList.contains('rowCheckbox')) {
      updateAssignBtnVisibility();
      // ensure selectAll reflects state
      const all = document.querySelectorAll('.rowCheckbox');
      if (all.length && selectAll) {
        selectAll.checked = Array.from(all).every(cb => cb.checked);
      }
    }
  });

  // When assign button clicked -> open modal and populate group dropdown
  if (assignBtn) {
    assignBtn.addEventListener('click', function() {
      // Use correct backend filename (action_api.php)
      fetch('action_api.php?get_groups=1')
        .then(async (r) => {
          if (!r.ok) throw new Error('Server returned ' + r.status);
          const text = await r.text();
          try {
            return JSON.parse(text);
          } catch (err) {
            // helpful debug message
            throw new Error('Invalid JSON returned for groups: ' + text.slice(0, 300));
          }
        })
        .then(groups => {
          const select = document.getElementById('targetGroup');
          if (!select) {
            alert('Group select element missing in DOM.');
            return;
          }
          select.innerHTML = '<option value="">Select Group</option>';
          groups.forEach(g => {
            const opt = document.createElement('option');
            opt.value = g.id;            // group row id
            opt.textContent = (g.group_name || '') + (g.project_name ? (' (' + g.project_name + ')') : '');
            select.appendChild(opt);
          });
          // open modal (Bootstrap)
          const modalEl = document.getElementById('assignGroupModal');
          if (modalEl) {
            const modal = new bootstrap.Modal(modalEl);
            modal.show();
          } else {
            alert('Assign modal element missing.');
          }
        })
        .catch(err => {
          console.error('Failed to load groups:', err);
          alert('Failed to load groups: ' + (err.message || 'Unknown error'));
        });
    });
  }

  // Submit assignment form
   if (assignGroupForm) {
    assignGroupForm.addEventListener('submit', async function(e) {
      e.preventDefault();

      // collect selection & group
      const selectedIds = Array.from(document.querySelectorAll('.rowCheckbox:checked')).map(cb => cb.value);
      const groupSelect = document.getElementById('targetGroup');
      const groupId = groupSelect ? groupSelect.value : null;
      if (!groupId) { showToast('Please select a group', 'Error'); return; }
      if (selectedIds.length === 0) { showToast('Please select at least one API', 'Error'); return; }

      try {
        const res = await fetch('action_api.php?assign_to_group=1', {
          method: 'POST',
          headers: {'Content-Type':'application/json'},
          body: JSON.stringify({ selected_ids: selectedIds, group_id: groupId })
        });

        const text = await res.text();
        let json;
        try { json = JSON.parse(text); } catch (err) {
          console.error('Invalid JSON response:', text);
          showToast('Invalid server response. Check console.', 'Error');
          return;
        }

        if (json.status === 'success') {
          // hide modal
          const modalEl = document.getElementById('assignGroupModal');
          if (modalEl) {
            const inst = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
            inst.hide();
          }

          // Clear selections UI
          document.querySelectorAll('.rowCheckbox').forEach(cb => cb.checked = false);
          const selAll = document.getElementById('selectAll');
          if (selAll) selAll.checked = false;
          const assignBtnEl = document.getElementById('assignToGroupBtn');
          if (assignBtnEl) assignBtnEl.style.display = 'none';

          // Refresh table data if fetchData exists (preserves page & search)
          if (typeof fetchData === 'function') {
            try {
              const page = (typeof currentPage !== 'undefined') ? currentPage : 1;
              const rows = (typeof rowsPerPage !== 'undefined') ? rowsPerPage : 10;
              const search = (typeof searchInput !== 'undefined' && searchInput && searchInput.value !== undefined) ? searchInput.value : '';
              fetchData(page, rows, search);
            } catch (e) {
              console.warn('fetchData failed, skipping refresh', e);
            }
          }

          // show professional toast success
          showToast(json.message || 'Assigned to group successfully', 'Success');

        } else {
          // server returned error
          showToast(json.message || 'Failed to assign', 'Error');
        }
      } catch (err) {
        console.error('Network error:', err);
        showToast('Network error. Try again.', 'Error');
      }
    });
  }

  // Toast helper using the #globalToast element from earlier
  function showToast(message, title = 'Notification') {
    const toastEl = document.getElementById('globalToast');
    if (!toastEl) {
      // Fallback to alert only if toast markup missing
      alert((title ? title + ': ' : '') + message);
      return;
    }
    const titleEl = document.getElementById('globalToastTitle');
    const bodyEl = document.getElementById('globalToastBody');
    const timeEl = document.getElementById('globalToastTime');

    if (titleEl) titleEl.textContent = title;
    if (bodyEl) bodyEl.innerHTML = message;
    if (timeEl) timeEl.textContent = new Date().toLocaleTimeString();

    const toast = new bootstrap.Toast(toastEl);
    toast.show();
  }
});
</script>
<!--group Manager JS Start-->
<script>
    !function(){let e=document.getElementById("gm_groupSearch"),t=document.getElementById("gm_clearGroupSearch"),n=document.getElementById("gm_groupsList"),o=document.getElementById("gm_rowSearch"),l=document.getElementById("gm_clearRowSearch"),i=document.getElementById("gm_rowsContainer"),r=document.getElementById("gm_clearAssignUsersBtn"),s=$("#gm_assignUsers");function a(e,t){return!!e&&!!t&&-1!==e.toString().toLowerCase().indexOf(t.toLowerCase())}function c(){let t=(e.value||"").trim();for(let o=0;o<n.options.length;o++){let l=n.options[o],i=""===t||a(l.text,t)||a(l.value,t);l.style.display=i?"":"none"}let r=Array.from(n.options).some(e=>"none"!==e.style.display);if(r)n._noResultOption&&(n.remove(n._noResultOption.index),n._noResultOption=null);else if(!n._noResultOption){let s=document.createElement("option");s.text="No groups match your search",s.disabled=!0,s.style.color="#888",n.add(s),n._noResultOption=s}}function u(){let e=(o.value||"").trim();if(!i)return;let t=i.querySelectorAll(".form-check"),n=!1;if(t.forEach(t=>{let o=t.innerText||t.textContent||"",l=""===e||a(o,e);t.style.display=l?"":"none",l&&(n=!0)}),n)i._noRes&&(i._noRes.remove(),i._noRes=null);else if(!i._noRes){let l=document.createElement("div");l.className="text-muted",l.style.padding="8px",l.textContent="No API rows match your search.",i.appendChild(l),i._noRes=l}}e.addEventListener("input",c),t.addEventListener("click",function(){e.value="",c()}),o.addEventListener("input",u),l.addEventListener("click",function(){o.value="",u()}),r&&r.addEventListener("click",function(){try{s.val(null).trigger("change")}catch(e){console.warn(e)}}),window.gm_applyClientFilters=function(){c(),u()}}();
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  // Elements
  const openBtn = document.getElementById('openGroupManagerBtn');
  const modalEl = document.getElementById('groupManagerModal');
  const gm_groupsList = document.getElementById('gm_groupsList');
  const gm_assignUsers = $('#gm_assignUsers'); // use Select2
  const gm_rowsContainer = document.getElementById('gm_rowsContainer');
  const gm_details = document.getElementById('gm_details');
  const gm_empty = document.getElementById('gm_empty');
  const gm_alert = document.getElementById('gm_alert');

  // Buttons
  const gm_refreshBtn = document.getElementById('gm_refreshBtn');
  const gm_createGroupBtn = document.getElementById('gm_createGroupBtn');
  const gm_deleteGroupBtn = document.getElementById('gm_deleteGroupBtn');
  const gm_saveBtn = document.getElementById('gm_saveBtn');
  const gm_addSelectedRowsBtn = document.getElementById('gm_addSelectedRowsBtn');
  const gm_removeSelectedRowsBtn = document.getElementById('gm_removeSelectedRowsBtn');

  // init select2 for assign users (will be filled)
  function initAssignUsersSelect() {
    gm_assignUsers.select2({
      theme: 'bootstrap-5',
      placeholder: 'Search & select users',
      allowClear: true,
      width: '100%'
    });
  }
  initAssignUsersSelect();

  // helper show alerts (bootstrap alert markup)
  function showGMAlert(type, message) {
    gm_alert.innerHTML = `<div class="alert alert-${type} alert-dismissible fade show" role="alert">
      ${message}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>`;
    gm_alert.style.display = 'block';
  }
  function hideGMAlert() { gm_alert.style.display = 'none'; gm_alert.innerHTML = ''; }

  // open modal
  openBtn.addEventListener('click', async () => {
    hideGMAlert();
    const modal = new bootstrap.Modal(modalEl);
    modal.show();
    await loadGroupsList();
  });

  // refresh groups list
  gm_refreshBtn.addEventListener('click', loadGroupsList);

  // load list of groups (brief info)
  async function loadGroupsList() {
    gm_groupsList.innerHTML = '';
    gm_details.style.display = 'none';
    gm_empty.style.display = 'block';
    try {
      const res = await fetch('action_api.php?get_groups=1');
      if (!res.ok) throw new Error('Failed to fetch groups');
      const groups = await res.json();
      if (!Array.isArray(groups) || groups.length === 0) {
        gm_groupsList.innerHTML = '<option disabled>No groups found</option>';
        return;
      }
      groups.forEach(g => {
        const opt = document.createElement('option');
        opt.value = g.id;
        opt.textContent = (g.group_name || 'Group') + (g.project_name ? ' — ' + g.project_name : '');
        gm_groupsList.appendChild(opt);
      });
      // auto-select first
      gm_groupsList.selectedIndex = 0;
      loadSelectedGroup();
    } catch (err) {
      showGMAlert('danger', 'Could not load groups: ' + err.message);
    }
  }

  // when group selected
  gm_groupsList.addEventListener('change', loadSelectedGroup);

  // load group details (users + associated rows)
  async function loadSelectedGroup() {
    hideGMAlert();
    gm_rowsContainer.innerHTML = '';
    gm_assignUsers.empty(); // clear select2
    const gid = gm_groupsList.value;
    if (!gid) { gm_details.style.display = 'none'; gm_empty.style.display = 'block'; return; }
    try {
      const res = await fetch(`action_api.php?get_group_details=1&id=${encodeURIComponent(gid)}`);
      if (!res.ok) throw new Error('Failed to fetch group details');
      const data = await res.json();
      // group row
      document.getElementById('gm_groupTitle').innerText = `${data.group.group_name || 'Group'} (id: ${data.group.id})`;
      // populate assign_user select2 (we need to fetch full users list for options)
      const usersRes = await fetch('action_api.php?get_users=1'); // returns option html in your existing endpoint
      const optionsHtml = await usersRes.text();
      // populate and reinit select2
      $('#gm_assignUsers').html(optionsHtml);
      initAssignUsersSelect();
      // set selected values (data.group.assign_user string CSV)
      const assigned = (data.group.assign_user || '').split(',').map(s => s.trim()).filter(Boolean);
      // set values for select2 by matching option values
      $('#gm_assignUsers').val(assigned).trigger('change');

      // populate associated API rows list (checkboxes + details)
      // also show other API rows that are not in group (for 'add' selection)
      const rows = data.rows || []; // rows currently associated with this group (their project_apis rows)
      const allCandidateRows = data.candidates || []; // other non-group rows we can add
      // show associated rows first with checked boxes
      const createRowCheckbox = r => {
        const div = document.createElement('div');
        div.className = 'form-check';
        div.innerHTML = `<input class="form-check-input gm_rowCheckbox" type="checkbox" value="${r.id}" id="gm_row_${r.id}" ${r.in_group ? 'checked' : ''}>
                         <label class="form-check-label" for="gm_row_${r.id}">
                           <strong>${r.project_name}</strong> — ${r.api_key} — ${r.lead_source} <small class="text-muted">(${r.created_at})</small>
                         </label>`;
        return div;
      };
      // combine: first in-group rows, then candidates
      const seen = new Set();
      rows.forEach(r => { r.in_group = true; gm_rowsContainer.appendChild(createRowCheckbox(r)); seen.add(String(r.id)); });
      allCandidateRows.forEach(r => {
        if (seen.has(String(r.id))) return;
        r.in_group = false;
        gm_rowsContainer.appendChild(createRowCheckbox(r));
      });

      gm_details.style.display = 'block';
      gm_empty.style.display = 'none';
    } catch (err) {
      showGMAlert('danger', 'Failed to load group details: ' + err.message);
    }
  }

  // Save changes: update group assign_user and optionally update group_name (if you want to expose that)
  gm_saveBtn.addEventListener('click', async () => {
    hideGMAlert();
    const gid = gm_groupsList.value;
    if (!gid) return showGMAlert('warning', 'No group selected');
    // gather selected users
    const selectedUsers = $('#gm_assignUsers').val() || [];
    // gather rows that are checked (these should be associated with the group)
    const checkedRows = Array.from(document.querySelectorAll('.gm_rowCheckbox:checked')).map(cb => cb.value);
    const payload = { group_id: gid, assign_users: selectedUsers, rows: checkedRows };

    try {
      const res = await fetch('action_api.php?action=update_group', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const json = await res.json();
      if (json.status === 'success') {
        showGMAlert('success', json.message || 'Group updated');
        // refresh groups list & keep same selected group after short delay
        await loadGroupsList();
        // reselect same group
        gm_groupsList.value = gid;
        await loadSelectedGroup();
      } else {
        showGMAlert('danger', json.message || 'Failed to update group');
      }
    } catch (err) {
      showGMAlert('danger', 'Save failed: ' + err.message);
    }
  });

  // Add selected API rows to group (only toggles group_id on those rows)
  gm_addSelectedRowsBtn.addEventListener('click', async () => {
    hideGMAlert();
    const gid = gm_groupsList.value;
    if (!gid) return showGMAlert('warning', 'No group selected');
    // candidate rows that are currently unchecked -> add those checked checkboxes
    const selected = Array.from(document.querySelectorAll('.gm_rowCheckbox:checked'))
      .map(cb => cb.value);
    if (selected.length === 0) return showGMAlert('warning', 'Select at least one row to add');
    try {
      const res = await fetch('action_api.php?action=add_rows_to_group', {
        method: 'POST',
        headers: { 'Content-Type':'application/json' },
        body: JSON.stringify({ group_id: gid, ids: selected })
      });
      const json = await res.json();
      if (json.status === 'success') {
        showGMAlert('success', json.message || 'Rows added to group');
        await loadSelectedGroup();
      } else {
        showGMAlert('danger', json.message || 'Failed to add rows');
      }
    } catch (err) {
      showGMAlert('danger', err.message);
    }
  });

  // Remove selected API rows from group
  gm_removeSelectedRowsBtn.addEventListener('click', async () => {
    hideGMAlert();
    const gid = gm_groupsList.value;
    if (!gid) return showGMAlert('warning', 'No group selected');
    const selected = Array.from(document.querySelectorAll('.gm_rowCheckbox:checked'))
      .map(cb => cb.value);
    if (selected.length === 0) return showGMAlert('warning', 'Select at least one row to remove');
    try {
      const res = await fetch('action_api.php?action=remove_rows_from_group', {
        method: 'POST',
        headers: { 'Content-Type':'application/json' },
        body: JSON.stringify({ group_id: gid, ids: selected })
      });
      const json = await res.json();
      if (json.status === 'success') {
        showGMAlert('success', json.message || 'Rows removed from group');
        await loadSelectedGroup();
      } else {
        showGMAlert('danger', json.message || 'Failed to remove rows');
      }
    } catch (err) {
      showGMAlert('danger', err.message);
    }
  });

  // Create group prompt
  gm_createGroupBtn.addEventListener('click', async () => {
    const name = prompt('Enter group name:');
    if (!name) return;
    try {
      const res = await fetch('action_api.php?action=create_group', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ group_name: name })
      });
      const json = await res.json();
      if (json.status === 'success') {
        showGMAlert('success', 'Group created');
        await loadGroupsList();
      } else showGMAlert('danger', json.message || 'Create failed');
    } catch (err) { showGMAlert('danger', err.message); }
  });

  // Delete group
  gm_deleteGroupBtn.addEventListener('click', async () => {
    const gid = gm_groupsList.value;
    if (!gid) return showGMAlert('warning', 'No group selected');
    if (!confirm('Delete this group? This will remove group assignment from associated API rows.')) return;
    try {
      const res = await fetch('action_api.php?action=delete_group', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ group_id: gid })
      });
      const json = await res.json();
      if (json.status === 'success') {
        showGMAlert('success', json.message || 'Deleted');
        await loadGroupsList();
      } else showGMAlert('danger', json.message || 'Delete failed');
    } catch (err) { showGMAlert('danger', err.message); }
  });

});
</script>
<!--group Manager JS End-->
<?php include('../htmlclose.php'); ?>