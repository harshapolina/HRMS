<!-- Users Filter Modal (Superadmin Parity) -->
<div class="modal fade users-filter-modal" id="filterModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered users-filter-dialog">
        <div class="modal-content users-filter-content">
            <!-- Header -->
            <div class="users-filter-header">
                <h4 class="mb-0"><i class="bi bi-funnel-fill me-2"></i>FILTER DATA</h4>
                <button type="button" class="users-filter-close" data-bs-dismiss="modal" id="closeFilter">✕</button>
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
                        "Unique ID"      => "uniqueid", // Standardized to match script
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
                                <div class="usr-custom-dropdown-list" id="usrdd-<?= $id ?>-list"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <!-- Footer -->
            <div class="users-filter-footer">
                <button class="btn-users-close" type="button" id="cancleFilter">
                    Close
                </button>
                <button class="btn-users-clear" type="button" id="clearFiltersBtn">
                    Clear Filters
                </button>
                <button class="btn-users-apply" type="button" id="applyFiltersBtn">
                    Apply Filters
                </button>
            </div>
        </div>
    </div>
</div>
