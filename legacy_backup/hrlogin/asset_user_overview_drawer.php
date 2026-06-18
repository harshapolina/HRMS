<!-- Overview-only User 360 drawer (Company Assets) -->
<div class="profile-drawer-overlay" id="assetOverviewOverlay" aria-hidden="true" style="display:none;position:fixed;inset:0;z-index:100300;"></div>
<div class="profile-drawer asset-overview-drawer" id="assetOverviewDrawer" role="dialog" aria-modal="true" aria-labelledby="assetOverviewUserName" style="display:none;position:fixed;z-index:100310;">
    <div class="drawer-header">
        <div class="drawer-header-content">
            <div class="drawer-avatar">
                <i class="bi bi-person"></i>
            </div>
            <div class="drawer-title">
                <h3 id="assetOverviewUserName">Employee</h3>
                <div class="d-flex align-items-center mt-2">
                    <span class="me-2 fw-bold" style="font-size: 0.85rem;">Status:</span>
                    <span id="assetOverviewStatusText" class="fw-bold" style="font-size: 0.85rem;">---</span>
                </div>
            </div>
        </div>
        <button type="button" class="close-drawer" onclick="closeAssetEmployeeOverview()" aria-label="Close">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>

    <div class="drawer-body">
        <h5 class="mb-3" style="font-weight: 700;">Basic Information</h5>
        <div class="overview-card shadow-sm">
            <div class="overview-grid-3">
                <div class="overview-field">
                    <span class="overview-label">Email</span>
                    <p class="overview-value" id="assetOverviewEmail">---</p>
                </div>
                <div class="overview-field">
                    <span class="overview-label">Contact No</span>
                    <p class="overview-value" id="assetOverviewContact">---</p>
                </div>
                <div class="overview-field">
                    <span class="overview-label">Date of Birth</span>
                    <p class="overview-value" id="assetOverviewDob">---</p>
                </div>
                <div class="overview-field">
                    <span class="overview-label">Unique ID</span>
                    <p class="overview-value" id="assetOverviewUniqueId">---</p>
                </div>
                <div class="overview-field">
                    <span class="overview-label">Employee ID</span>
                    <p class="overview-value" id="assetOverviewEmployeeId">---</p>
                </div>
                <div class="overview-field">
                    <div class="overview-password-label-row">
                        <span class="overview-label">Password</span>
                        <button type="button" class="overview-password-toggle" id="assetOverviewPasswordToggle" aria-label="Show password">
                            <i class="bi bi-eye" id="assetOverviewPasswordIcon"></i>
                        </button>
                    </div>
                    <p class="overview-value" id="assetOverviewPassword">••••••••</p>
                </div>
                <div class="overview-field">
                    <span class="overview-label">Date of Joining</span>
                    <p class="overview-value" id="assetOverviewDoj">---</p>
                </div>
                <div class="overview-field">
                    <span class="overview-label">Monthly CTC</span>
                    <p class="overview-value" id="assetOverviewSalary">---</p>
                </div>
                <div class="overview-field" id="assetOverviewCreatedAtWrap">
                    <span class="overview-label">Created At</span>
                    <p class="overview-value" id="assetOverviewCreatedAt">---</p>
                </div>
                <div class="overview-field" id="assetOverviewInactiveAtWrap" style="display: none;">
                    <span class="overview-label">Inactive At</span>
                    <p class="overview-value" id="assetOverviewInactiveAt">---</p>
                </div>
            </div>
        </div>

        <h5 class="mb-3 mt-4" style="font-weight: 700;">Project Details</h5>
        <div class="overview-card shadow-sm">
            <div class="overview-grid-2">
                <div class="overview-field">
                    <span class="overview-label">Project Name</span>
                    <p class="overview-value" id="assetOverviewProject">---</p>
                </div>
                <div class="overview-field">
                    <span class="overview-label">Project Type</span>
                    <p class="overview-value" id="assetOverviewProjectType">---</p>
                </div>
                <div class="overview-field">
                    <span class="overview-label">Role Type</span>
                    <p class="overview-value" id="assetOverviewRole">---</p>
                </div>
                <div class="overview-field">
                    <span class="overview-label">Assigned Users</span>
                    <p class="overview-value" id="assetOverviewAssignedUsers">---</p>
                </div>
            </div>
        </div>

        <h5 class="mb-3 mt-4" style="font-weight: 700;"><i class="bi bi-wallet2 me-2"></i> Salary Structure (CTC)</h5>
        <div id="assetOverviewSalaryStructure" class="overview-card shadow-sm mb-2">
            <div class="text-center p-4 text-muted">Select an employee to view salary structure.</div>
        </div>
    </div>
</div>
