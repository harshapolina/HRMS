<!-- Add/Edit User Modal -->
<div class="modal fade" id="addEditModal" tabindex="-1" aria-labelledby="addEditModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content application-modal">
      <div class="modal-header application-header">
        <div class="d-flex align-items-center gap-3">
            <div class="application-icon">
                <i class="bi bi-person-plus-fill"></i>
            </div>
            <div>
                <h5 class="modal-title fw-bold mb-0" id="addEditModalLabel">New User Application</h5>
            </div>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0">
        <form id="userForm">
          <input type="hidden" id="userId" name="userId">
          
          <div class="application-form-container">
            <!-- Section 1: Basic Information -->
            <div class="form-section">
                <div class="section-header">
                    <i class="bi bi-person-vcard"></i>
                    <span>Basic Information</span>
                </div>
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="form-floating-custom">
                            <input type="text" class="form-control" id="userName" name="userName" required>
                            <label for="userName">Full Name <span class="text-danger">*</span></label>
                            <i class="bi bi-person input-icon"></i>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-floating-custom">
                            <input type="email" class="form-control" id="userEmail" name="userEmail" required>
                            <label for="userEmail">Email Address <span class="text-danger">*</span></label>
                            <i class="bi bi-envelope input-icon"></i>
                        </div>
                        <div id="userEmailFeedback" class="field-feedback"></div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-floating-custom">
                            <input type="text" class="form-control" id="userContact" name="userContact" required>
                            <label for="userContact">Contact No. <span class="text-danger">*</span></label>
                            <i class="bi bi-telephone input-icon"></i>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-floating-custom">
                            <input type="password" class="form-control" id="userPassword" name="userPassword" required>
                            <label for="userPassword">Password <span class="text-danger">*</span></label>
                            <i class="bi bi-shield-lock input-icon"></i>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-floating-custom">
                            <input type="date" class="form-control" id="userDOB" name="userDOB">
                            <label for="userDOB">Date Of Birth</label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 2: Employment Details -->
            <div class="form-section">
                <div class="section-header">
                    <i class="bi bi-briefcase"></i>
                    <span>Employment & Project Details</span>
                </div>
                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="form-floating-custom">
                            <input type="text" class="form-control" id="userMonthlyCTC" name="userMonthlyCTC" oninput="window.calculateModalSalaryStructure('salary')">
                            <label for="userMonthlyCTC">Monthly CTC (₹)</label>
                            <i class="bi bi-currency-rupee input-icon"></i>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-floating-custom">
                            <input type="date" class="form-control" id="userDOJ" name="userDOJ">
                            <label for="userDOJ">Date Of Joining</label>
                        </div>
                    </div>
                    <div class="col-md-4" id="statusFieldGroup">
                        <div class="form-floating-custom">
                            <select class="form-select" id="userStatus" name="userStatus">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                            <label for="userStatus">Account Status</label>
                            <i class="bi bi-toggle-on input-icon"></i>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-floating-custom position-relative">
                            <input type="text" class="form-control" id="userRoleType" name="user_type" autocomplete="off" onfocus="showRoleDropdown()" oninput="filterRoleDropdown()">
                            <label for="userRoleType">Role Type</label>
                            <i class="bi bi-person-badge input-icon"></i>
                            <div id="roleDropdown" class="dropdown shadow-lg border-0 rounded-4" style="display: none; position: absolute; top: 100%; left: 0; right: 0; background: var(--table-bg, #fff); border: 1px solid var(--table-border, #eee); z-index: 999; max-height: 150px; overflow-y: auto;">
                                <?php 
                                if (isset($existingRoles)) {
                                    foreach ($existingRoles as $role): ?>
                                        <div class="dropdown-item py-2 px-3" onclick="selectRoleType('<?= htmlspecialchars($role, ENT_QUOTES) ?>')">
                                            <?= htmlspecialchars($role) ?>
                                        </div>
                                    <?php endforeach;
                                } ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-floating-custom">
                            <input type="text" class="form-control" id="userProjectName" name="project_name">
                            <label for="userProjectName">Project Name / Dept</label>
                            <i class="bi bi-folder input-icon"></i>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-floating-custom">
                            <input type="text" class="form-control" id="userProjectType" name="D_project">
                            <label for="userProjectType">Project Type</label>
                            <i class="bi bi-gear input-icon"></i>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-floating-custom">
                            <input type="text" class="form-control" id="userCity" name="city" list="userCityList" autocomplete="off" placeholder=" ">
                            <datalist id="userCityList">
                                <option value="Bangalore"></option>
                                <option value="Hyderabad"></option>
                                <option value="Pune"></option>
                                <option value="Chennai"></option>
                                <option value="Mumbai"></option>
                                <option value="Delhi"></option>
                                <option value="Gujarat"></option>
                            </datalist>
                            <label for="userCity">City</label>
                            <i class="bi bi-geo-alt input-icon"></i>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-floating-custom">
                            <input type="text" class="form-control" id="userUniqueID" name="userUniqueID" required>
                            <label for="userUniqueID">Unique ID <span class="text-danger">*</span></label>
                            <i class="bi bi-fingerprint input-icon"></i>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-floating-custom">
                            <input type="text" class="form-control" id="userEmployeeID" name="userEmployeeID">
                            <label for="userEmployeeID">Internal Employee ID</label>
                            <i class="bi bi-hash input-icon"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 3: Salary Breakdown -->
            <div class="form-section">
                <div class="section-header">
                    <i class="bi bi-cash-coin"></i>
                    <span>Salary Breakdown (Auto-Calculated)</span>
                </div>
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="form-floating-custom">
                            <input type="number" class="form-control" id="userBasic" name="amountO" oninput="window.calculateModalSalaryStructure('basic')">
                            <label for="userBasic">Basic Salary (₹)</label>
                            <i class="bi bi-cash input-icon"></i>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-floating-custom">
                            <input type="number" class="form-control" id="userHRA" name="amountT">
                            <label for="userHRA">HRA (₹)</label>
                            <i class="bi bi-house-door input-icon"></i>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-floating-custom">
                            <input type="number" class="form-control" id="userConveyance" name="amountTh">
                            <label for="userConveyance">Conveyance Allowance (₹)</label>
                            <i class="bi bi-car-front input-icon"></i>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-floating-custom">
                            <input type="number" class="form-control" id="userSpecial" name="amountF">
                            <label for="userSpecial">Special Allowance (₹)</label>
                            <i class="bi bi-gift input-icon"></i>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-floating-custom">
                            <input type="number" class="form-control" id="userPFEmployer" name="amountFf" oninput="window.calculateModalSalaryStructure('pf_employer')">
                            <label for="userPFEmployer">PF (Employer Part) (₹)</label>
                            <i class="bi bi-safe input-icon"></i>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-floating-custom">
                            <input type="number" class="form-control" id="userDeductions" name="amountS">
                            <label for="userDeductions">Standard Deductions (₹)</label>
                            <i class="bi bi-dash-circle input-icon"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 3: Assign Users -->
            <div class="form-section mb-0">
                <div class="section-header">
                    <i class="bi bi-person-check"></i>
                    <span>Assign Users</span>
                </div>
                <div class="row">
                    <div class="col-12">
                        <div class="custom-multiselect form-control application-multiselect" onclick="toggleDropdown(event)">
                            <div id="selected_tags"></div>
                            <input type="text" id="search_user_input" placeholder="Search and select users..." autocomplete="off" oninput="filterDropdown()">
                            <div id="dropdown" class="dropdown shadow-lg border-0 rounded-4">
                            <?php 
                            if (isset($assignUsers)) {
                                foreach ($assignUsers as $user): ?>
                                    <div class="dropdown-item assign-user-option py-2 px-3"
                                         data-value="<?= htmlspecialchars($user['tablename'], ENT_QUOTES) ?>"
                                         data-label="<?= htmlspecialchars($user['username'] . ' (' . $user['user_type'] . ')', ENT_QUOTES) ?>"
                                         onclick="event.stopPropagation(); selectUser(this.dataset.value, this.dataset.label)">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="avatar-circle-sm"><?= substr($user['username'], 0, 1) ?></div>
                                            <div>
                                                <div class="fw-bold small"><?= htmlspecialchars($user['username']) ?></div>
                                                <div class="text-muted" style="font-size: 11px;"><?= htmlspecialchars($user['user_type']) ?></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach;
                            } ?>
                            </div>
                        </div>
                        <input type="hidden" id="assign_user_hidden" name="assign_user">
                    </div>
                </div>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer border-0 p-4">
        <button type="button" class="btn btn-light px-4 py-2 rounded-3 fw-bold" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary px-5 py-2 rounded-3 fw-bold" id="saveUserBtn" style="background: linear-gradient(135deg, #1e6063 0%, #2a8c90 100%); border: none; box-shadow: 0 4px 12px rgba(42, 140, 144, 0.3);">
            <i class="bi bi-cloud-check-fill me-2"></i> Register User
        </button>
      </div>
    </div>
  </div>
</div>

<style>
.application-modal {
    border-radius: 24px;
    border: none;
    overflow: hidden;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
}
.application-header {
    background: linear-gradient(135deg, #1e6063 0%, #2a8c90 100%);
    color: white;
    padding: 24px 32px;
    border: none;
}
.application-icon {
    width: 48px;
    height: 48px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}
.application-form-container {
    padding: 32px;
    padding-top: 40px;
    background: #f8fafc;
}
.form-section {
    background: white;
    border-radius: 20px;
    padding: 24px;
    margin-bottom: 24px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 2px 4px rgba(0,0,0,0.02);
}
.section-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 24px;
    color: #1e6063;
    font-weight: 700;
    font-size: 1.1rem;
}
.section-header i {
    font-size: 1.25rem;
    opacity: 0.8;
}
.field-feedback {
    margin-top: 6px;
    font-size: 0.75rem;
    font-weight: 600;
    color: #64748b;
}
.field-feedback.is-invalid {
    color: #b91c1c;
}
body.dark-mode #addEditModal .field-feedback {
    color: #aaaaaa !important;
}
body.dark-mode #addEditModal .field-feedback.is-invalid {
    color: #fca5a5 !important;
}
.user-form-swal-popup {
    border-radius: 20px !important;
    padding: 1.5rem 1.25rem 1.25rem !important;
    box-shadow: 0 20px 45px rgba(15, 23, 42, 0.18) !important;
}
.user-form-swal-title {
    font-size: 1.15rem !important;
    font-weight: 700 !important;
    color: #1e293b !important;
}
.user-form-swal-body {
    font-size: 0.95rem !important;
    line-height: 1.55 !important;
    color: #475569 !important;
    text-align: left !important;
    margin-top: 0.35rem !important;
}
.user-form-alert-message {
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: 12px;
    padding: 12px 14px;
    color: #991b1b;
    font-weight: 600;
}
.user-form-alert-list {
    margin: 10px 0 0;
    padding-left: 18px;
    color: #7f1d1d;
}
.user-form-alert-list li {
    margin-bottom: 4px;
}
.user-form-swal-btn {
    border-radius: 10px !important;
    font-weight: 700 !important;
    padding: 0.55rem 1.4rem !important;
}
body.dark-mode .user-form-swal-title {
    color: #f8fafc !important;
}
body.dark-mode .user-form-swal-body {
    color: #cbd5e1 !important;
}
body.dark-mode .user-form-alert-message {
    background: #2a1515 !important;
    border-color: #7f1d1d !important;
    color: #fecaca !important;
}
body.dark-mode .user-form-alert-list {
    color: #fca5a5 !important;
}

/* Custom Floating Labels */
.form-floating-custom {
    position: relative;
}
.form-floating-custom label {
    position: absolute;
    top: -10px;
    left: 12px;
    background: white;
    padding: 0 8px;
    font-size: 12px;
    font-weight: 700;
    color: #64748b;
    z-index: 2;
    transition: all 0.2s;
    white-space: nowrap;
    max-width: calc(100% - 24px);
    overflow: hidden;
    text-overflow: ellipsis;
}
.form-floating-custom .form-control,
.form-floating-custom .form-select {
    padding: 14px 16px 14px 44px !important;
    border-radius: 12px;
    border: 1.5px solid #e2e8f0;
    font-size: 15px;
    color: #1e293b;
    height: 54px;
    transition: all 0.2s;
}

/* Date fields do not use left icons; keep full dd-mm-yyyy visible */
.form-floating-custom input[type="date"].form-control {
    padding: 14px 10px !important;
    font-size: 13px !important;
    letter-spacing: 0 !important;
    text-overflow: clip !important;
}

.form-floating-custom input[type="date"].form-control::-webkit-datetime-edit {
    padding: 0 !important;
}

.form-floating-custom input[type="date"].form-control::-webkit-calendar-picker-indicator {
    margin: 0 !important;
}
.form-floating-custom .form-control:focus,
.form-floating-custom .form-select:focus {
    border-color: #2a8c90;
    box-shadow: 0 0 0 4px rgba(42, 140, 144, 0.1);
    outline: none;
}
.form-floating-custom .input-icon {
    position: absolute;
    left: 16px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    font-size: 18px;
}

/* Multiselect Styling */
.application-multiselect {
    padding: 8px 12px !important;
    border-radius: 12px !important;
    border: 1.5px solid #e2e8f0 !important;
    background: white !important;
    min-height: 54px !important;
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    align-items: center;
    cursor: text;
}
.application-multiselect input {
    border: none !important;
    flex: 1;
    min-width: 150px;
    outline: none !important;
    font-size: 14px;
    padding: 4px 0;
}
.tag {
    background: #eef2ff;
    color: #4f46e5;
    padding: 4px 10px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 6px;
    border: 1px solid #e0e7ff;
}
.tag span:hover {
    color: #ef4444;
}
.avatar-circle-sm {
    width: 28px;
    height: 28px;
    background: #eef2ff;
    color: #4f46e5;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
}
.dropdown-item:hover {
    background-color: #f1f5f9;
    cursor: pointer;
}

/* Dark Mode Support (Neutral Monochrome) - High Specificity overrides */
body.dark-mode #addEditModal .modal-content {
    background: #121212 !important;
    border: 1px solid #2d2d2d !important;
    color: #ffffff !important;
}
body.dark-mode #addEditModal .application-header {
    background: #121212 !important;
    border-bottom: 1px solid #2d2d2d !important;
}
body.dark-mode #addEditModal .application-icon {
    background: #1a1a1a !important;
    border: 1px solid #2d2d2d !important;
    color: #ffffff !important;
}
body.dark-mode #addEditModal .application-form-container {
    background: #000000 !important;
}
body.dark-mode #addEditModal .form-section {
    background: #121212 !important;
    border-color: #2d2d2d !important;
    box-shadow: none !important;
}
body.dark-mode #addEditModal .section-header {
    color: #ffffff !important;
}
body.dark-mode #addEditModal .form-floating-custom label {
    background: #121212 !important;
    color: #aaaaaa !important;
}
body.dark-mode #addEditModal .form-floating-custom .form-control,
body.dark-mode #addEditModal .form-floating-custom .form-select {
    background-color: #000000 !important;
    border-color: #2d2d2d !important;
    color: #ffffff !important;
}
body.dark-mode #addEditModal .form-floating-custom .form-control:focus,
body.dark-mode #addEditModal .form-floating-custom .form-select:focus {
    border-color: #ffffff !important;
    box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.15) !important;
}
body.dark-mode #addEditModal .form-floating-custom .form-select option {
    background-color: #121212 !important;
    color: #ffffff !important;
}
body.dark-mode #addEditModal .form-floating-custom .input-icon {
    color: #888888 !important;
}
body.dark-mode #addEditModal .tag {
    background: #1a1a1a !important;
    color: #ffffff !important;
    border-color: #3d3d3d !important;
}
body.dark-mode #addEditModal .avatar-circle-sm {
    background: #3d3d3d !important;
    color: #ffffff !important;
}
body.dark-mode #addEditModal .application-multiselect {
    background: #000000 !important;
    border-color: #2d2d2d !important;
}
body.dark-mode #addEditModal .application-multiselect input {
    color: #ffffff !important;
    background: transparent !important;
}
body.dark-mode #addEditModal #roleDropdown,
body.dark-mode #addEditModal #dropdown,
body.dark-mode #addEditModal .dropdown {
    background: #121212 !important;
    border: 1px solid #2d2d2d !important;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.5) !important;
}
body.dark-mode #addEditModal .dropdown-item {
    color: #ffffff !important;
}
body.dark-mode #addEditModal .dropdown-item:hover {
    background: #222222 !important;
}
body.dark-mode #addEditModal .modal-footer {
    background: #121212 !important;
    border-top: 1px solid #2d2d2d !important;
}
body.dark-mode #addEditModal .modal-footer .btn-light {
    background: #1a1a1a !important;
    border: 1px solid #3d3d3d !important;
    color: #ffffff !important;
}
body.dark-mode #addEditModal .modal-footer .btn-light:hover {
    background: #2d2d2d !important;
}
body.dark-mode #addEditModal #saveUserBtn {
    background: #ffffff !important;
    color: #000000 !important;
    border: none !important;
    box-shadow: none !important;
}
body.dark-mode #addEditModal #saveUserBtn:hover {
    background: #dddddd !important;
    color: #000000 !important;
}
body.dark-mode #addEditModal .btn-close {
    filter: none !important;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23fff'%3e%3cpath d='M.293.293a1 1 0 0 1 1.414 0L8 6.586 14.293.293a1 1 0 1 1 1.414 1.414L9.414 8l6.293 6.293a1 1 0 0 1-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 0 1-1.414-1.414L6.586 8 .293 1.707a1 1 0 0 1 0-1.414z'/%3e%3c/svg%3e") !important;
    opacity: 0.8 !important;
}
body.dark-mode #addEditModal .btn-close:hover {
    opacity: 1 !important;
}

/* Layout Spreading, Gap Adjustments & Breathing Room for Form Fields */
#addEditModal .row {
    margin-left: -12px !important;
    margin-right: -12px !important;
    row-gap: 24px !important; /* Generous, premium vertical spacing between input rows */
}

#addEditModal .row > [class^="col-"],
#addEditModal .row > [class*=" col-"] {
    padding-left: 12px !important;
    padding-right: 12px !important;
}

/* Expand internal bounds of cards & containers for luxurious airiness */
#addEditModal .form-section {
    padding: 32px !important; /* Expanded from 24px for balanced spacing */
    margin-bottom: 28px !important;
    border-radius: 20px !important;
    border: 1px solid rgba(226, 232, 240, 0.8) !important;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02) !important;
}

#addEditModal .application-form-container {
    padding: 40px !important; /* Expanded outer boundaries from 32px */
}

#addEditModal .form-floating-custom .form-control,
#addEditModal .form-floating-custom .form-select {
    height: 56px !important; /* Premium height for easier tap/click interface */
}
</style>

<script>
window.showRoleDropdown = function () {
    const dropdown = document.getElementById('roleDropdown');
    if (dropdown) {
        dropdown.style.display = 'block';
        // Reset list displays to show all initially
        const items = dropdown.querySelectorAll('.dropdown-item');
        items.forEach(item => item.style.display = 'block');
    }
};

window.filterRoleDropdown = function () {
    const input = document.getElementById('userRoleType');
    const filter = input.value.toLowerCase();
    const dropdown = document.getElementById('roleDropdown');
    if (!dropdown) return;
    const items = dropdown.querySelectorAll('.dropdown-item');
    items.forEach(item => {
        item.style.display = item.innerText.toLowerCase().includes(filter) ? 'block' : 'none';
    });
};

window.selectRoleType = function (role) {
    const input = document.getElementById('userRoleType');
    if (input) {
        input.value = role;
        input.dispatchEvent(new Event('input')); // In case there are listeners
    }
    const dropdown = document.getElementById('roleDropdown');
    if (dropdown) {
        dropdown.style.display = 'none';
    }
};

// Document click listener to close dropdown when clicking outside
document.addEventListener('click', function (event) {
    const roleDropdown = document.getElementById('roleDropdown');
    const roleInput = document.getElementById('userRoleType');
    if (roleDropdown && roleInput && !roleInput.contains(event.target) && !roleDropdown.contains(event.target)) {
        roleDropdown.style.display = 'none';
    }
});
</script>

