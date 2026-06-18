<!-- Offer Letter Filter Modal -->
<div id="offerFilterModal" class="users-filter-modal">
    <div class="users-filter-dialog">
        <div class="users-filter-content">
            <div class="users-filter-header">
                <h4 class="mb-0"><i class="bi bi-funnel-fill me-2"></i>FILTER DATA</h4>
                <button type="button" class="users-filter-close" id="closeOfferFilter">&times;</button>
            </div>

            <div class="users-filter-body py-4">
                <div class="mb-4">
                    <label class="d-block mb-3" style="font-weight: 700; color: #227477; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;">Date Range (Created At)</label>
                    <div class="row g-2">
                        <div class="col-6">
                            <div class="users-filter-item">
                                <label>From</label>
                                <input type="date" id="offerFilterFromDate" class="users-filter-input">
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="users-filter-item">
                                <label>To</label>
                                <input type="date" id="offerFilterToDate" class="users-filter-input">
                            </div>
                        </div>
                    </div>
                    <div class="mt-2 d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary px-3" style="border-radius: 8px;" onclick="setOfferFilterRange('today')">Today</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary px-3" style="border-radius: 8px;" onclick="setOfferFilterRange('week')">Week</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary px-3" style="border-radius: 8px;" onclick="setOfferFilterRange('month')">Month</button>
                    </div>
                </div>

                <hr class="my-4 opacity-25">

                <div class="users-filter-grid">
                    <div class="users-filter-item">
                        <label>Status</label>
                        <select id="offerFilterStatus" class="users-filter-input">
                            <option value="">All Status</option>
                            <option value="Draft">Draft</option>
                            <option value="Sent">Sent</option>
                            <option value="Accepted">Accepted</option>
                            <option value="Rejected">Rejected</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="users-filter-footer border-top pt-3 d-flex justify-content-center flex-wrap gap-2">
                <button type="button" class="btn btn-secondary btn-users-close flex-grow-1" id="closeOfferFilterFooter" style="border-radius: 8px; min-width: 100px;">Close</button>
                <button type="button" class="btn btn-danger btn-users-clear flex-grow-1" id="clearOfferFiltersBtn" style="border-radius: 8px; min-width: 100px;">Clear Filters</button>
                <button type="button" class="btn btn-primary btn-users-apply flex-grow-1" id="applyOfferFiltersBtn" style="border-radius: 8px; min-width: 100px;">Apply Filters</button>
            </div>
        </div>
    </div>
</div>
