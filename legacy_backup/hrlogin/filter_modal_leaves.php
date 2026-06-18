<!-- Leave Filter Modal -->
<div id="filterModal" class="users-filter-modal">
    <div class="users-filter-dialog">
        <div class="users-filter-content">
            <div class="users-filter-header">
                <h4 class="mb-0"><i class="bi bi-funnel-fill me-2"></i>FILTER DATA</h4>
                <button type="button" class="users-filter-close" id="closeFilter">&times;</button>
            </div>
            
            <div class="users-filter-body py-4">
                <!-- Date Range Section -->
                <div class="mb-4">
                    <label class="d-block mb-3" style="font-weight: 700; color: #227477; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;">Date Range</label>
                    <div class="row g-2">
                        <div class="col-6">
                            <div class="users-filter-item">
                                <label>From</label>
                                <input type="date" id="filterFromDate" class="users-filter-input" value="<?php echo htmlspecialchars($from_date); ?>">
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="users-filter-item">
                                <label>To</label>
                                <input type="date" id="filterToDate" class="users-filter-input" value="<?php echo htmlspecialchars($to_date); ?>">
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

                <!-- Other Filters -->
                <div class="users-filter-grid">
                    <!-- Status Filter -->
                    <div class="users-filter-item">
                        <label>Status</label>
                        <select id="filterStatus" class="users-filter-input">
                            <option value="">All Status</option>
                            <option value="Pending" <?php echo $status_filter == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="Approved" <?php echo $status_filter == 'Approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="Rejected" <?php echo $status_filter == 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="users-filter-footer border-top pt-3 d-flex justify-content-center flex-wrap gap-2">
                <button type="button" class="btn btn-secondary btn-users-close flex-grow-1" id="closeFilterFooter" style="border-radius: 8px; min-width: 100px;">Close</button>
                <button type="button" class="btn btn-danger btn-users-clear flex-grow-1" id="clearFiltersBtn" style="border-radius: 8px; min-width: 100px;">Clear Filters</button>
                <button type="button" class="btn btn-primary btn-users-apply flex-grow-1" id="applyFiltersBtn" style="border-radius: 8px; min-width: 100px;">Apply Filters</button>
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
        const first = now.getDate() - now.getDay() + (now.getDay() === 0 ? -6 : 1);
        const last = first + 6;
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
