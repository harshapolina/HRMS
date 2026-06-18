<!-- attendance_filter_modal.php -->
<div id="filterModal" class="users-filter-modal">
    <div class="users-filter-dialog">
        <div class="users-filter-content">
            <div class="users-filter-header">
                <h4 class="mb-0"><i class="bi bi-funnel-fill me-2"></i>FILTER DATA</h4>
                <button type="button" class="users-filter-close" id="closeFilter">&times;</button>
            </div>
            <div class="users-filter-body">
                <!-- Date Range Section (Moved from main bar) -->
                <div class="mb-4">
                    <label class="d-block mb-2 fw-bold small text-muted">DATE RANGE</label>
                    <div class="row g-2">
                        <div class="col-6">
                            <div class="users-filter-item">
                                <label>From</label>
                                <input type="date" name="from" id="filterFromDate" class="users-filter-input" value="<?php echo $from_date; ?>">
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="users-filter-item">
                                <label>To</label>
                                <input type="date" name="to" id="filterToDate" class="users-filter-input" value="<?php echo $to_date; ?>">
                            </div>
                        </div>
                    </div>
                    <div class="mt-2 d-flex gap-2">
                         <button type="button" class="btn btn-sm btn-outline-secondary px-3" style="border-radius: 8px;" onclick="setFilterRange('today')">Today</button>
                         <button type="button" class="btn btn-sm btn-outline-secondary px-3" style="border-radius: 8px;" onclick="setFilterRange('week')">Week</button>
                         <button type="button" class="btn btn-sm btn-outline-secondary px-3" style="border-radius: 8px;" onclick="setFilterRange('month')">Month</button>
                    </div>
                </div>
                <hr class="my-4 opacity-25">
                <!-- Advanced Selection Grid -->
                <div class="users-filter-grid">
                    <!-- Employee ID -->
                    <div class="users-filter-item">
                        <label>Employee ID</label>
                        <div class="usr-custom-dropdown-wrapper" id="usrdd-filterID">
                             <div class="usr-chip-container" id="usrdd-filterID-chips"></div>
                             <input type="text" class="usr-custom-dropdown-input" id="filterID" placeholder="Search ID...">
                             <div class="usr-custom-dropdown-list" id="usrdd-filterID-list"></div>
                        </div>
                    </div>
                    <!-- Employee Name -->
                    <div class="users-filter-item">
                        <label>Employee Name</label>
                        <div class="usr-custom-dropdown-wrapper" id="usrdd-username">
                             <div class="usr-chip-container" id="usrdd-username-chips"></div>
                             <input type="text" class="usr-custom-dropdown-input" id="username" placeholder="Search Name...">
                             <div class="usr-custom-dropdown-list" id="usrdd-username-list"></div>
                        </div>
                    </div>

                    <!-- Work Hours -->
                    <div class="users-filter-item">
                        <label>Work Hours</label>
                        <div class="usr-custom-dropdown-wrapper" id="usrdd-workhours">
                             <div class="usr-chip-container" id="usrdd-workhours-chips"></div>
                             <input type="text" class="usr-custom-dropdown-input" id="workhours" placeholder="Search Hours...">
                             <div class="usr-custom-dropdown-list" id="usrdd-workhours-list"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="users-filter-footer border-top pt-3">
                <a href="attendance_report.php?export=csv&from=<?php echo $from_date; ?>&to=<?php echo $to_date; ?>&search=<?php echo urlencode($search_query); ?>&status=<?php echo urlencode($status_filter); ?>" 
                   class="btn btn-outline-success me-auto" style="border-radius: 8px; font-weight: 500;">
                    <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export CSV
                </a>
                <button type="button" class="btn btn-secondary btn-users-close" id="closeFilterFooter" style="border-radius: 8px;">Close</button>
                <button type="button" class="btn btn-danger btn-users-clear" id="clearFiltersBtn" style="border-radius: 8px;">Clear Filters</button>
                <button type="button" class="btn btn-primary btn-users-apply" id="applyFiltersBtn" style="border-radius: 8px;">Apply Filters</button>
            </div>
        </div>
    </div>
</div>
<script>
function setFilterRange(range) {
    const fromInput = document.getElementById('filterFromDate');
    const toInput = document.getElementById('filterToDate');
    const now = new Date();
    let from, to;

    if (range === 'today') {
        from = to = now.toISOString().split('T')[0];
    } else if (range === 'week') {
        const first = now.getDate() - now.getDay() + (now.getDay() === 0 ? -6 : 1); // Monday
        const last = first + 6; // Sunday
        from = new Date(now.setDate(first)).toISOString().split('T')[0];
        to = new Date(now.setDate(last)).toISOString().split('T')[0];
    } else if (range === 'month') {
        from = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().split('T')[0];
        to = new Date(now.getFullYear(), now.getMonth() + 1, 0).toISOString().split('T')[0];
    }

    if (fromInput && toInput) {
        fromInput.value = from;
        toInput.value = to;
    }
}
</script>
