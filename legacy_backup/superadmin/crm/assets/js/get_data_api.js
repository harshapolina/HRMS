document.addEventListener("DOMContentLoaded", () => {
    // Load user options in the select box
    fetchUsers();
    // fetchFilterValue();
});
// $("#isolatedFilterModal").on("shown.bs.modal", function () {
//   fetchFilterValue();
// });

// API base (absolute) — update this if your app is hosted at a different base
const API_BASE = '/incentiveapp_integration/userlogin1/superadmin/crm/';

// Cycle through multi-colored avatar icons (matches userlogin variants)
const ICON_PATHS = [
    '../../assets/dataimage/icon-1.png',
    '../../assets/dataimage/icon-2.png',
    '../../assets/dataimage/icon-3.png',
    '../../assets/dataimage/icon-4.png',
    '../../assets/dataimage/icon-5.png',
    '../../assets/dataimage/icon-6.png'
];
let iconCounter = 0;

const getNextIconPath = () => {
    if (!ICON_PATHS.length) return './assets/images/avatar.jpeg';
    iconCounter = (iconCounter % ICON_PATHS.length) + 1;
    const idx = iconCounter - 1;
    return ICON_PATHS[idx];
};

// Attach horizontal scroll buttons for badge rows
const initBadgeArrows = () => {
    // Function to update arrow visibility based on scroll position
    const updateArrowVisibility = (track, leftArrow, rightArrow) => {
        if (!track) return;

        const scrollLeft = track.scrollLeft;
        const maxScroll = track.scrollWidth - track.clientWidth;

        // Show/hide left arrow based on scroll position
        if (leftArrow) {
            if (scrollLeft > 0) {
                leftArrow.classList.add('visible');
            } else {
                leftArrow.classList.remove('visible');
            }
        }

        // Optional: hide right arrow when fully scrolled to the right
        if (rightArrow) {
            if (scrollLeft >= maxScroll - 1) {
                rightArrow.classList.add('hidden');
            } else {
                rightArrow.classList.remove('hidden');
            }
        }
    };

    // Group arrows by their target
    const arrowGroups = {};
    document.querySelectorAll('.badge-arrow').forEach(btn => {
        const targetSelector = btn.dataset.target;
        if (!targetSelector) return;

        if (!arrowGroups[targetSelector]) {
            arrowGroups[targetSelector] = { left: null, right: null, track: null };
        }

        arrowGroups[targetSelector].track = document.querySelector(targetSelector);

        if (btn.classList.contains('left')) {
            arrowGroups[targetSelector].left = btn;
        } else if (btn.classList.contains('right')) {
            arrowGroups[targetSelector].right = btn;
        }
    });

    // Attach click and scroll listeners for each group
    Object.keys(arrowGroups).forEach(targetSelector => {
        const { left, right, track } = arrowGroups[targetSelector];
        if (!track) return;

        // Click handlers
        if (left) {
            left.addEventListener('click', () => {
                track.scrollBy({ left: -220, behavior: 'smooth' });
            });
        }

        if (right) {
            right.addEventListener('click', () => {
                track.scrollBy({ left: 220, behavior: 'smooth' });
            });
        }

        // Scroll event listener to update arrow visibility
        track.addEventListener('scroll', () => {
            updateArrowVisibility(track, left, right);
        });

        // Initial visibility check
        updateArrowVisibility(track, left, right);

        // Re-check on window resize
        window.addEventListener('resize', () => {
            updateArrowVisibility(track, left, right);
        });
    });
};

// safeFetch: checks response.ok and content-type before parsing
async function safeFetch(url, opts) {
    const fullUrl = url.startsWith('http') || url.startsWith('/') ? url : (API_BASE + url);
    const resp = await fetch(fullUrl, opts);
    const ct = (resp.headers.get('content-type') || '').toLowerCase();
    const text = await resp.text();
    if (!resp.ok) {
        // Include response body for debugging (trimmed)
        const snippet = text ? text.slice(0, 1000) : '';
        throw new Error(`Server Error: ${resp.status} ${resp.statusText} — ${snippet}`);
    }
    if (ct.includes('application/json')) {
        if (!text) throw new Error('Empty JSON response');
        try { return JSON.parse(text); } catch (e) { throw new Error('Invalid JSON: ' + e.message + ' — ' + text.slice(0, 200)); }
    }
    return text;
}

// Fetch user options and populate select element
const fetchUsers = async () => {
    const userSelect = document.getElementById("user-select");
    const cronUserSelect = document.getElementById("cron-assigned-user");
    if (!userSelect && !cronUserSelect) return;

    const setSelectOptions = (selectEl, optionsHtml, includePlaceholder) => {
        if (!selectEl) return;
        const placeholder = includePlaceholder ? '<option value="">Select user</option>' : '';
        selectEl.innerHTML = placeholder + optionsHtml;
        if ($(selectEl).hasClass('select2-hidden-accessible')) {
            $(selectEl).trigger('change.select2');
        }
    };

    try {
        const optionsHtml = await safeFetch('upload.php?get_users=1', { method: 'GET' });
        if (typeof optionsHtml === 'string') {
            setSelectOptions(userSelect, optionsHtml, false);
            setSelectOptions(cronUserSelect, optionsHtml, false);
        }
    } catch (error) {
        console.error("Failed to fetch users:", error);
        if (userSelect) userSelect.innerHTML = '<option value="">(failed to load users)</option>';
        if (cronUserSelect) cronUserSelect.innerHTML = '<option value="">(failed to load users)</option>';
    }
};

const initializeCronUserSelect2 = () => {
    const cronSelect = document.getElementById('cron-assigned-user');
    const createCronModalEl = document.getElementById('createCronModal');
    if (!cronSelect || !createCronModalEl || typeof $ === 'undefined' || !$.fn || !$.fn.select2) return;

    const $cronSelect = $(cronSelect);
    if ($cronSelect.hasClass('select2-hidden-accessible')) {
        $cronSelect.select2('destroy');
    }

    $cronSelect.select2({
        placeholder: 'Select users',
        allowClear: true,
        multiple: true,
        width: '100%',
        closeOnSelect: false,
        dropdownParent: $(createCronModalEl)
    });
};

const initializeFilterSelect2 = () => {
    const selectIds = {
        name: "isolatedFilterCustumername",
        email: "isolatedFilterEmail",
        number: "isolatedFilterContactnumber",
        location: "isolatedFilterLocation",
        source_of_lead: "isolatedFilterSourceOfLead",
        project: "isolatedFilterAssignedProjectName",
        assign_to_user: "isolatedFilterAssignedUserName",
        status: "isolatedFilterStatus"
    };

    Object.keys(selectIds).forEach(column => {
        let selectElement = document.getElementById(selectIds[column]);

        if (selectElement) {
            $(`#${selectIds[column]}`).select2({
                placeholder: "Search & select",
                allowClear: true,
                multiple: true,
                width: '100%',
                tags: true,
                closeOnSelect: false,
                dropdownParent: $('#isolatedFilterModal'), // Fix: Ensure dropdown appears within modal
                ajax: {
                    url: API_BASE + "upload.php",
                    dataType: "json",
                    delay: 300, // Delay to reduce requests
                    data: function (params) {
                        return {
                            get_filter_value: 1, // Send filter request
                            column: column,      // Column to search
                            search: params.term || "" // Search term
                        };
                    },
                    processResults: function (data) {
                        if (data.status === "success") {
                            return {
                                results: data.filters.map(value => ({
                                    id: value,
                                    text: value
                                }))
                            };
                        } else {
                            console.error("Server error:", data.message);
                            return { results: [] };
                        }
                    },
                    cache: true
                }
            });
        } else {
            console.error(`Element #${selectIds[column]} not found.`);
        }
    });
};

// Ensure it runs **after DOM is fully loaded**
$(document).ready(function () {
    initializeCronUserSelect2();
    initializeFilterSelect2();

    // Add proper Bootstrap 5 modal event listener for cleanup
    const filterModal = document.getElementById('isolatedFilterModal');
    if (filterModal) {
        // Cleanup when modal is fully hidden
        filterModal.addEventListener('hidden.bs.modal', function (event) {
            console.log('Filter modal hidden event - cleaning up');
            
            // Remove any lingering backdrops
            setTimeout(() => {
                const backdrops = document.querySelectorAll('.modal-backdrop');
                backdrops.forEach(backdrop => backdrop.remove());
                
                // Ensure body classes are reset
                document.body.classList.remove('modal-open');
                document.body.style.paddingRight = '';
                document.body.style.overflow = '';
            }, 100);
        });

        // Ensure modal can be opened
        filterModal.addEventListener('show.bs.modal', function (event) {
            console.log('Filter modal show event - preparing');
            
            // Remove any existing backdrops before showing
            const existingBackdrops = document.querySelectorAll('.modal-backdrop');
            existingBackdrops.forEach(backdrop => backdrop.remove());
        });
    }

    // Use delegated event handlers for close buttons
    $(document).on('click', '#isolatedCloseFilter', function (e) {
        console.log('Close button X clicked');
        e.preventDefault();
        e.stopImmediatePropagation();

        // Use Bootstrap 5 Modal API
        const filterModalEl = document.getElementById('isolatedFilterModal');
        if (filterModalEl) {
            const modalInstance = bootstrap.Modal.getInstance(filterModalEl);
            if (modalInstance) {
                modalInstance.hide();
            }
        }
    });

    $(document).on('click', '#isolatedCancleFilter', function (e) {
        console.log('Close button clicked');
        e.preventDefault();
        e.stopImmediatePropagation();

        // Use Bootstrap 5 Modal API
        const filterModalEl = document.getElementById('isolatedFilterModal');
        if (filterModalEl) {
            const modalInstance = bootstrap.Modal.getInstance(filterModalEl);
            if (modalInstance) {
                modalInstance.hide();
            }
        }
    });
});

// these script is for delete select row or other operations
document.addEventListener('DOMContentLoaded', function () {

    initBadgeArrows();
    initNumberToggle();
    initFilterToastClose();

    // Handle Single Lead Insertion
    const addLeadForm = document.getElementById("addLeadForm");
    const responseMessage = document.getElementById("responseMessage");
    const submitLeadButton = document.getElementById("submitLead");

    if (!addLeadForm || !responseMessage || !submitLeadButton) {
        console.warn("Required elements not found");
        return;
    }

    console.log("All elements found. Attaching listener to #submitLead");

    submitLeadButton.addEventListener("click", async function () {
        console.log("#submitLead clicked");

        const formData = new FormData(addLeadForm);
        console.log("FormData prepared for lead insert");

        try {
            const response = await safeFetch('insert_lead.php', { method: 'POST', body: formData });
            // safeFetch returns parsed JSON for JSON responses
            const res = typeof response === 'string' ? JSON.parse(response) : response;
            console.log("Request completed to insert_lead.php", res);
            console.log("Response received:", res);

            // Show toast notification at the bottom
            Swal.fire({
                toast: true,
                position: 'bottom',
                icon: res.status === 'success' ? 'success' : 'error',
                title: res.message,
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
                customClass: {
                    popup: 'custom-toast-popup'
                }
            });

            let modalEl = document.getElementById("addLeadModal");
            let modal = bootstrap.Modal.getInstance(modalEl);
            if (!modal) {
                modal = new bootstrap.Modal(modalEl);
            }
            modal.hide();

            // Explicitly remove backdrop and modal-open class
            setTimeout(() => {
                $('.modal-backdrop').remove();
                $('body').removeClass('modal-open');
                $('body').css('padding-right', '');
            }, 200);

            console.log("Modal closed");

            addLeadForm.reset();

            if (res.status === "success") {
                console.log("Insert success. Refreshing data...");
                fetchData();
            }

        } catch (error) {
            console.error("Insert error:", error);

            // Show error toast notification at the bottom
            Swal.fire({
                toast: true,
                position: 'bottom',
                icon: 'error',
                title: 'An error occurred. Please try again later.',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true,
                customClass: {
                    popup: 'custom-toast-popup'
                }
            });

            const modalEl = document.getElementById("addLeadModal");
            let modal = bootstrap.Modal.getInstance(modalEl);
            if (!modal) {
                modal = new bootstrap.Modal(modalEl);
            }
            modal.hide();

            // Explicitly remove backdrop and modal-open class
            setTimeout(() => {
                $('.modal-backdrop').remove();
                $('body').removeClass('modal-open');
                $('body').css('padding-right', '');
            }, 200);

            addLeadForm.reset();
        }
    });
    // Handle file upload
    const uploadFormEl = document.getElementById("uploadForm");
    let uploadProgressToast = document.getElementById('upload-progress-toast');
    if (!uploadProgressToast) {
        const toastHtml =
            '<div id="upload-progress-toast" style="display:none;position:fixed;left:50%;bottom:24px;transform:translateX(-50%);z-index:12000;min-width:320px;max-width:92vw;background:#16a34a;color:#fff;border-radius:12px;box-shadow:0 12px 30px rgba(0,0,0,0.18);padding:12px 14px;">' +
            '  <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;font-size:13px;font-weight:600;">' +
            '    <span id="upload-progress-text">Processing upload...</span>' +
            '    <span id="upload-progress-percent">0%</span>' +
            '  </div>' +
            '  <div style="margin-top:8px;height:7px;background:rgba(255,255,255,0.28);border-radius:999px;overflow:hidden;">' +
            '    <div id="upload-progress-bar" style="width:0%;height:100%;background:#ffffff;transition:width .25s ease;"></div>' +
            '  </div>' +
            '</div>';
        document.body.insertAdjacentHTML('beforeend', toastHtml);
        uploadProgressToast = document.getElementById('upload-progress-toast');
    }

    const updateUploadProgressUi = (progress, statusText, mode = 'processing') => {
        if (!uploadProgressToast || !progress) return;
        const textEl = document.getElementById('upload-progress-text');
        const pctEl = document.getElementById('upload-progress-percent');
        const barEl = document.getElementById('upload-progress-bar');
        const pct = Math.max(0, Math.min(100, Number(progress.percent || 0)));
        const processed = Number(progress.processed_rows || 0);
        const total = Number(progress.total_rows || 0);

        uploadProgressToast.style.display = 'block';
        uploadProgressToast.style.background = mode === 'error' ? '#dc2626' : (mode === 'done' ? '#16a34a' : '#0f766e');
        if (textEl) {
            textEl.textContent = statusText || `Processing ${processed}/${total} rows...`;
        }
        if (pctEl) pctEl.textContent = `${pct}%`;
        if (barEl) barEl.style.width = `${pct}%`;
    };

    const sleep = (ms) => new Promise(resolve => setTimeout(resolve, ms));

    if (uploadFormEl) uploadFormEl.addEventListener("submit", async (event) => {
        event.preventDefault();
        const formData = new FormData(event.target);
        const submitBtn = uploadFormEl.querySelector('button[type="submit"]');
        const closeBtn = document.querySelector('#uploadExcelPopup .btn-secondary');
        const uploadModalEl = document.getElementById('uploadExcelPopup');

        if (submitBtn) submitBtn.disabled = true;
        updateUploadProgressUi({ percent: 0, processed_rows: 0, total_rows: 0 }, 'Uploading file and starting import...');

        // Close upload modal immediately after clicking Upload.
        try {
            const modalInstance = uploadModalEl ? bootstrap.Modal.getInstance(uploadModalEl) : null;
            if (modalInstance) {
                modalInstance.hide();
            } else if (closeBtn) {
                closeBtn.click();
            }
        } catch (e) {
            if (closeBtn) closeBtn.click();
        }

        setTimeout(() => {
            $('.modal-backdrop').remove();
            $('body').removeClass('modal-open');
            $('body').css('padding-right', '');
        }, 120);

        try {
            const startResult = await safeFetch('upload.php?action=start_upload', { method: 'POST', body: formData });
            if (!startResult || startResult.status !== 'success' || !startResult.progress || !startResult.progress.job_id) {
                throw new Error((startResult && startResult.message) ? startResult.message : 'Failed to start upload job.');
            }

            let current = startResult.progress;
            updateUploadProgressUi(current, `Processing ${current.processed_rows}/${current.total_rows} rows...`);

            while (!current.done) {
                await sleep(350);
                const poll = await safeFetch(`upload.php?action=process_upload&job_id=${encodeURIComponent(current.job_id)}`, { method: 'POST' });
                if (!poll || poll.status !== 'success' || !poll.progress) {
                    throw new Error((poll && poll.message) ? poll.message : 'Upload progress polling failed.');
                }
                current = poll.progress;
                updateUploadProgressUi(current, `Processing ${current.processed_rows}/${current.total_rows} rows...`);
            }

            updateUploadProgressUi(
                current,
                `Completed ${current.processed_rows}/${current.total_rows}. Inserted ${current.inserted}, Updated ${current.updated}, Skipped ${current.skipped}.`,
                'done'
            );

            fetchData(); // Refresh data table after successful upload
        } catch (error) {
            console.error("Error uploading file:", error);
            updateUploadProgressUi({ percent: 100, processed_rows: 0, total_rows: 0 }, (error && error.message) ? error.message : 'There was an error uploading the file.', 'error');
        } finally {
            if (submitBtn) submitBtn.disabled = false;

            // Keep status visible briefly, then hide.
            setTimeout(() => {
                if (uploadProgressToast) uploadProgressToast.style.display = 'none';
            }, 4500);
        }
    });

    // Initialize variables
    const rowSelector = document.getElementById('rowSelector');
    const prevButton = document.getElementById('prevButton');
    const nextButton = document.getElementById('nextButton');
    const pageNumbersContainer = document.getElementById('pageNumbers');
    const jumpToPageInput = document.getElementById('jumpToPageInput');
    const jumpButton = document.getElementById('jumpButton');
    const rowInfo = document.getElementById('rowInfo');
    const searchInput = document.getElementById('searchInput');
    const uploaddata = document.getElementById('uploaddata'); // Table body
    const selectAllCheckbox = document.getElementById('select-all');
    const deleteSelectedBtn = document.getElementById('delete-selected-btn');
    const createCronFab = document.getElementById('create-cron-fab');
    const createCronFabCount = document.getElementById('create-cron-fab-count');
    const cronSelectedCount = document.getElementById('cron-selected-count');
    const createCronModal = document.getElementById('createCronModal');
    const createCronForm = document.getElementById('create-cron-form');
    const statusFilterSelect = document.getElementById('statusFilterSelect');

    // Configuration for the API
    let totalRowCount = 0;

    // Function to update badge counts dynamically - SIMPLIFIED VERSION
    window.updateBadgeCounts = function (searchQuery = '', filters = {}, filterType = '', showDeleted = false) {
        const encodedQuery = searchQuery ? encodeURIComponent(searchQuery) : '';
        const encodedFilters = filters && Object.keys(filters).length > 0 ? encodeURIComponent(JSON.stringify(filters)) : '';
        const encodedFilterType = filterType ? encodeURIComponent(filterType) : '';
        const url = `./upload.php?get_filtered_counts=1&searchQuery=${encodedQuery}&multiFilters=${encodedFilters}&currentFilter=${encodedFilterType}&showDeletedOnly=${showDeleted ? 1 : 0}`;

        console.log(`[Badge Update] Calling get_filtered_counts with search="${searchQuery}", filters:`, filters);
        fetch(url)
            .then(response => response.json())
            .then(counts => {
                console.log('[Badge Update] Received counts:', counts);

                // Update each badge with proper null checks - NEW format with span.count
                const updateBadge = (id, count) => {
                    const btn = document.getElementById(id);
                    if (btn) {
                        const countSpan = btn.querySelector('.count');
                        if (countSpan) {
                            countSpan.textContent = count || 0;
                            console.log(`✓ Updated ${id}: ${count}`);
                        } else {
                            console.warn(`✗ Count span not found in button: ${id}`);
                        }
                    } else {
                        console.warn(`✗ Button not found: ${id}`);
                    }
                };

                // Update all badges EXCEPT Total (Total is updated by fetchData with filtered count)
                updateBadge('myLeads', counts.myLeads);
                updateBadge('eoicounterdata', counts.totaleoi);
                updateBadge('deletedLeads', counts.totaldelete);
                updateBadge('totalUnassigned', counts.unassigned);
                updateBadge('droppedLeads', counts.droppedLeads);
                updateBadge('bookedLeads', counts.bookedLeads);
                updateBadge('todayFollowUps', counts.today_collection);
                updateBadge('paidAds', counts.paidAds);
                updateBadge('shi_d', counts.shi_d);
                updateBadge('followLeads', counts.followLeads);
                updateBadge('overdueLeads', counts.overdueLeads);
                updateBadge('activeLeads', counts.activeLeads);
                updateBadge('freshLeads', counts.freshLeads);
                updateBadge('pendingLeads', counts.pendingLeads);

                console.log('[Badge Update] ✓ ALL BADGES UPDATED');
            })
            .catch(error => {
                console.error('[Badge Update] ✗ ERROR:', error);
            });
    };

    let currentPage = 1;
    let rowsPerPage = parseInt(rowSelector.value, 10);
    let totalPages;
    let currentFilter = ''; // lead-type badge filter (e.g., active, booked)
    let multifilters = {}; // multi-column filters, including status dropdown
    let showDeletedOnly = false;
    let statusFilterValue = ''; // status dropdown selection persists across badges
    const leadTypeButtons = document.querySelectorAll('.filter-btn[data-lead-type]');
    // Column visibility state (reset to defaults on each refresh)
    try { localStorage.removeItem('crmColumnVisibility'); } catch (e) { }
    let columnVisibilityState = {};

    // Helper: clear all filter inputs/selects (modal + search) and reset state
    function clearAllFiltersAndSearch() {
        const filterIds = [
            'isolatedFilterCustumername',
            'isolatedFilterEmail',
            'isolatedFilterContactnumber',
            'isolatedFilterLocation',
            'isolatedFilterSourceOfLead',
            'isolatedFilterAssignedProjectName',
            'isolatedFilterAssignedUserName',
            'isolatedFilterStatus',
            'isolatedFilterStartDate',
            'isolatedFilterEndDate'
        ];

        filterIds.forEach(id => {
            const element = document.getElementById(id);
            if (!element) return;
            if ($(element).hasClass('select2-hidden-accessible')) {
                $(element).val(null).trigger('change');
            } else {
                element.value = '';
            }
        });

        multifilters = {};
        if (searchInput) searchInput.value = '';
        hideFloatingClearButton();
    }

    // Function to trigger the close button programmatically
    function closeModal() {
        // Use Bootstrap 5 Modal API
        const filterModalEl = document.getElementById('isolatedFilterModal');
        if (filterModalEl) {
            const modalInstance = bootstrap.Modal.getInstance(filterModalEl);
            if (modalInstance) {
                modalInstance.hide();
            } else {
                // If no instance exists, create one and hide it
                const newModal = new bootstrap.Modal(filterModalEl);
                newModal.hide();
            }
        }
        
        // Additional cleanup (belt and suspenders approach)
        setTimeout(() => {
            const backdrops = document.querySelectorAll('.modal-backdrop');
            backdrops.forEach(backdrop => backdrop.remove());
            document.body.classList.remove('modal-open');
            document.body.style.paddingRight = '';
            document.body.style.overflow = '';
        }, 150);
    }



    // Function to handle multi-column filtering
    function applyMultiColumnFilter() {
        // Get values from modal inputs
        multifilters = {
            name: $('#isolatedFilterCustumername').val() || [],
            email: $('#isolatedFilterEmail').val() || [],
            number: $('#isolatedFilterContactnumber').val() || [],
            location: $('#isolatedFilterLocation').val() || [],
            source_of_lead: $('#isolatedFilterSourceOfLead').val() || [],
            project: $('#isolatedFilterAssignedProjectName').val() || [],
            assign_to_user: $('#isolatedFilterAssignedUserName').val() || [],
            status: $('#isolatedFilterStatus').val() || [],
            start_date: $('#isolatedFilterStartDate').val().trim() || null,  // Added Start Date
            end_date: $('#isolatedFilterEndDate').val().trim() || null
            // name: document.getElementById('isolatedFilterCustumername').value.trim(),
            // email: document.getElementById('isolatedFilterEmail').value.trim(),
            // number: document.getElementById('isolatedFilterContactnumber').value.trim(),
            // location: document.getElementById('isolatedFilterLocation').value.trim(),
            // source_of_lead: document.getElementById('isolatedFilterSourceOfLead').value.trim(),
            // project: document.getElementById('isolatedFilterAssignedProjectName').value.trim(),
            // assign_to_user: document.getElementById('isolatedFilterAssignedUserName').value.trim(),
            // status: document.getElementById('isolatedFilterStatus').value.trim(),
        };

        // Log filters for debugging
        console.log('[DEBUG] Applying Multi-Column Filters:', multifilters);

        currentPage = 1; // Reset to the first page
        fetchData(currentPage, rowsPerPage, searchInput.value.trim(), '', multifilters); // Fetch data with multi-column filters

        // Update badge counts once per filter application
        getUserLeadsCount();

        // Show floating clear filter button
        showFloatingClearButton();

        // Hide the modal
        closeModal();
    }

    // Function to create and show floating clear filter button
    let clearFilterBtn = null;
    function showFloatingClearButton() {
        // Create button if it doesn't exist
        if (!clearFilterBtn) {
            clearFilterBtn = document.createElement('button');
            clearFilterBtn.id = 'clearFilterFloatingBtn';
            clearFilterBtn.innerHTML = '<i class="bi bi-x-circle-fill"></i> Clear Filters';
            clearFilterBtn.style.cssText = `
                position: fixed;
                bottom: 30px;
                right: 30px;
                background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
                color: white;
                border: none;
                padding: 12px 24px;
                border-radius: 50px;
                font-size: 15px;
                font-weight: 600;
                cursor: pointer;
                box-shadow: 0 4px 15px rgba(220, 53, 69, 0.4);
                z-index: 9999;
                display: flex;
                align-items: center;
                gap: 8px;
                transition: all 0.3s ease;
            `;

            // Add hover effect
            clearFilterBtn.onmouseenter = function () {
                this.style.transform = 'translateY(-2px)';
                this.style.boxShadow = '0 6px 20px rgba(220, 53, 69, 0.5)';
            };
            clearFilterBtn.onmouseleave = function () {
                this.style.transform = 'translateY(0)';
                this.style.boxShadow = '0 4px 15px rgba(220, 53, 69, 0.4)';
            };

            // Click handler to clear filters
            clearFilterBtn.onclick = function () {
                // Clear all filters
                const filterIds = [
                    'isolatedFilterCustumername',
                    'isolatedFilterEmail',
                    'isolatedFilterContactnumber',
                    'isolatedFilterLocation',
                    'isolatedFilterSourceOfLead',
                    'isolatedFilterAssignedProjectName',
                    'isolatedFilterAssignedUserName',
                    'isolatedFilterStatus',
                    'isolatedFilterStartDate',
                    'isolatedFilterEndDate'
                ];

                filterIds.forEach(id => {
                    const element = document.getElementById(id);
                    if (element) {
                        if ($(element).hasClass("select2-hidden-accessible")) {
                            $(element).val(null).trigger("change");
                        } else {
                            element.value = '';
                        }
                    }
                });

                // Reset filters and fetch data
                multifilters = {};
                currentPage = 1;
                fetchData(currentPage, rowsPerPage, searchInput.value.trim());

                // Hide the button
                statusFilterValue = '';
                if (multifilters.status) delete multifilters.status;
                const statusFilterBtn = document.getElementById('filterStatus');
                if (statusFilterBtn) {
                    statusFilterBtn.innerHTML = '<i class="fa-solid fa-filter"></i> Status <i class="fa-solid fa-caret-down"></i>';
                }
                hideFloatingClearButton();
            };

            document.body.appendChild(clearFilterBtn);
        }

        // Show the button
        clearFilterBtn.style.display = 'flex';
    }

    function hideFloatingClearButton() {
        if (clearFilterBtn) {
            clearFilterBtn.style.display = 'none';
        }
    }

    function hasActiveFilters() {
        const hasSearch = searchInput && searchInput.value.trim() !== '';
        return hasSearch || Object.keys(multifilters).length > 0;
    }


    // Event listener for the "Apply Filters" button
    document.getElementById('isolatedApplyFiltersBtn').addEventListener('click', applyMultiColumnFilter);

    // Event listener for the "Reset All" button (was "Clear Filters")
    document.getElementById('isolatedCancleFilter').addEventListener('click', () => {
        // IDs of all filter inputs
        const filterIds = [
            'isolatedFilterCustumername',
            'isolatedFilterEmail',
            'isolatedFilterContactnumber',
            'isolatedFilterLocation',
            'isolatedFilterSourceOfLead',
            'isolatedFilterAssignedProjectName',
            'isolatedFilterAssignedUserName',
            'isolatedFilterStatus',
            'isolatedFilterStartDate',
            'isolatedFilterEndDate'
        ];

        // Loop through each filter input
        filterIds.forEach(id => {
            const element = document.getElementById(id);
            if (element) {
                // Check if it's a Select2 dropdown
                if ($(element).hasClass("select2-hidden-accessible")) {
                    $(element).val(null).trigger("change"); // Clear Select2 selections
                } else {
                    element.value = ''; // Clear normal input fields
                }
            }
        });

        // Reset multi-column filters and pagination
        multifilters = {};
        statusFilterValue = '';
        const statusFilterBtn = document.getElementById('filterStatus');
        if (statusFilterBtn) {
            statusFilterBtn.innerHTML = '<i class="fa-solid fa-filter"></i> Status <i class="fa-solid fa-caret-down"></i>';
        }
        currentPage = 1;

        // Fetch data without filters
        fetchData(currentPage, rowsPerPage, searchInput.value.trim());

        // Update badge counts after clearing filters
        getUserLeadsCount();

        // Hide floating clear button
        hideFloatingClearButton();

        // Close the modal
        closeModal();
    });
    leadTypeButtons.forEach(button => {
        button.addEventListener('click', function (e) {
            if (e) {
                e.preventDefault();
                e.stopImmediatePropagation(); // avoid duplicate listeners on the same badge
            }
            const leadType = this.dataset.leadType;
            const nextShowDeleted = leadType === 'deleted';
            const nextFilter = leadType === 'total' ? '' : leadType;

            // Avoid redundant re-renders/flicker if clicking the active badge again
            if (currentFilter === nextFilter && showDeletedOnly === nextShowDeleted) {
                return;
            }

            // Remove highlight from all other lead-type buttons
            leadTypeButtons.forEach(btn => btn.classList.remove('active-deleted'));
            this.classList.add('active-deleted');

            showDeletedOnly = nextShowDeleted;
            currentFilter = nextFilter;
            currentPage = 1;
            if (statusFilterSelect) {
                statusFilterSelect.value = '';
            }

            // Do NOT reset status dropdown; keep it applied across badge clicks

            // Keep existing search and multi-filters; just change the lead-type filter
            fetchData(currentPage, rowsPerPage, searchInput.value.trim(), currentFilter, multifilters, showDeletedOnly);
        });
    });
    // Download handling is now centralized in assets/js/get_downloads.js
    // to avoid duplicate requests and stuck spinners. The legacy handler was removed.
    
    // Format assigned users with ellipsis and expand functionality
    function formatAssignedUsers(assignedUsersStr) {
        if (!assignedUsersStr || assignedUsersStr === 'N/A' || assignedUsersStr.trim() === '') {
            return 'N/A';
        }
        
        const users = assignedUsersStr.split(',').map(u => u.trim()).filter(Boolean);
        
        if (users.length <= 2) {
            // Show all users if 2 or fewer as plain text
            return users.join(', ');
        } else {
            // Show first 2 users with expand option as plain text
            const visibleUsers = users.slice(0, 2).join(', ');
            const hiddenUsers = users.slice(2).map(user => `<span class="assigned-user-hidden" style="display:none;">, ${user}</span>`).join('');
            const moreBtn = `<span class="assigned-users-more" data-expanded="false" style="color: #007bff; cursor: pointer; margin-left: 4px;">+${users.length - 2} more</span>`;
            
            return `<span class="assigned-users-wrapper">${visibleUsers}${hiddenUsers}${moreBtn}</span>`;
        }
    }
    
    // Fetch data function
    async function fetchData(page = 1, rowsPerPage = 10, searchQuery = '', filterType = '', multiFilters = {}, showDeletedOnly = false) {
        const badgeMapping = {
            active: { id: 'activeLeads', text: 'Active', icon: 'fa-solid fa-circle-check' },
            pending: { id: 'pendingLeads', text: 'Untouched', icon: 'fa-solid fa-clock' },
            fresh: { id: 'freshLeads', text: 'New', icon: 'fa-solid fa-star' },
            dropped: { id: 'droppedLeads', text: 'Dropped', icon: 'fa-solid fa-ban' },
            booked: { id: 'bookedLeads', text: 'Booked', icon: 'fa-solid fa-book' },
            today: { id: 'todayFollowUps', text: "Today FollowUp's", icon: 'fa-solid fa-calendar-check' },
            ads: { id: 'paidAds', text: 'Ads', icon: 'fa-solid fa-bullhorn' },
            shi_d: { id: 'shi_d', text: 'SHI-D', icon: 'fa-solid fa-database' },
            follow: { id: 'followLeads', text: 'Follow Up', icon: 'fa-solid fa-arrow-up' },
            overdue: { id: 'overdueLeads', text: 'Overdue', icon: 'fa-solid fa-triangle-exclamation' },
            my: { id: 'myLeads', text: 'My Leads', icon: 'fa-solid fa-user' },
            unassigned: { id: 'totalUnassigned', text: 'Unassigned', icon: 'fa-solid fa-user-slash' },
            deleted: { id: 'deletedLeads', text: 'Deleted Leads', icon: 'fa-solid fa-trash', iconStyle: 'color:red' }
        };

        const hasActiveFilters = (filtersObj) => {
            if (!filtersObj || typeof filtersObj !== 'object') return false;
            return Object.values(filtersObj).some((val) => {
                if (val === null || val === undefined) return false;
                if (Array.isArray(val)) return val.length > 0;
                if (typeof val === 'object') return Object.keys(val).length > 0;
                return String(val).trim() !== '';
            });
        };
        try {
            const encodedQuery = searchQuery ? encodeURIComponent(searchQuery) : '';
            const encodedFilters = multiFilters ? encodeURIComponent(JSON.stringify(multiFilters)) : '';
            const startDate = multiFilters.start_date ? encodeURIComponent(multiFilters.start_date) : '';
            const endDate = multiFilters.end_date ? encodeURIComponent(multiFilters.end_date) : '';

            const url = `upload.php?page=${page}&rowsPerPage=${rowsPerPage}
              &searchQuery=${encodedQuery}
              &multiFilters=${encodedFilters}
              &startDate=${startDate}&endDate=${endDate}
              &showDeletedOnly=${showDeletedOnly ? 1 : 0}
              &currentFilter=${encodeURIComponent(filterType)}`;
            //   const url = `upload.php?page=${page}&rowsPerPage=${rowsPerPage}
            //   &searchQuery=${encodedQuery}&filter=${encodeURIComponent(filterType)}
            //   &multiFilters=${encodedFilters}&start_date=${startDate}&end_date=${endDate}`;

            // Use safeFetch which prefixes API_BASE and validates content-type
            const result = await safeFetch(url);
            let data = result;
            if (typeof result === 'string') {
                // Server returned HTML (likely an error page)
                console.error('Server returned HTML:', result.slice(0, 300));
                throw new Error('Invalid response from server (expected JSON)');
            }

            // console.log('Data fetched:', data);

            if (data.error) {
                throw new Error(data.error);
            }
            updateTable(data.data, showDeletedOnly);
            updatePagination(data.totalRows, data.currentPage, data.rowsPerPage);

            // Update Total badge only when viewing the base (all) list; keep it stable while switching badges
            const totalBtn = document.getElementById('totalLeads');
            if (!filterType && !showDeletedOnly && totalBtn && data.totalRows !== undefined) {
                const countSpan = totalBtn.querySelector('.count');
                if (countSpan) {
                    countSpan.textContent = data.totalRows;
                    console.log(`[Filtered Count] Updated Total badge: ${data.totalRows}`);
                }
            }

            // Refresh all badge counters only for the base view so counts stay static after filter apply
            if (!filterType && !showDeletedOnly && typeof window.updateBadgeCounts === 'function') {
                window.updateBadgeCounts(searchQuery, multiFilters, '', false);
            }

            // Update the badge for the currently selected filter to match visible row count
            if (filterType && data.totalRows !== undefined) {
                const badgeInfo = badgeMapping[filterType];
                if (badgeInfo) {
                    const badgeBtn = document.getElementById(badgeInfo.id);
                    if (badgeBtn) {
                        const style = badgeInfo.iconStyle ? ` style="${badgeInfo.iconStyle}"` : '';
                        badgeBtn.innerHTML = `<i class="${badgeInfo.icon}"${style}></i> ${badgeInfo.text} <span class="count">${data.totalRows}</span>`;
                    }
                }
            }

            // Show filter toast with contextual label
            const filtersActive = Boolean((searchQuery && searchQuery.trim()) || hasActiveFilters(multiFilters) || showDeletedOnly);
            const label = filterType && badgeMapping[filterType]
                ? `${badgeMapping[filterType].text} Leads`
                : (filtersActive ? 'Leads Filtered' : 'Total Leads');
            showFilterToast(data.totalRows ?? 0, label);

            // Call the user cell processing after the table is updated
            processUserCells();

            // Apply responsive behavior first
            if (typeof handleResponsiveBehavior === 'function') {
                handleResponsiveBehavior();
            }

            // Then populate column visibility dropdown
            PopulateCheckedRow();
        } catch (error) {
            console.error('[DEBUG] Error fetching data:', error);
            uploaddata.innerHTML = `<tr><td colspan="20" style="text-align: center; color: red;">Error loading data. Please try again.</td></tr>`;
        }
    }

    // <!-- column selector script for table start-->
    function PopulateCheckedRow() {
        const applyColumnVisibility = (columnIndex, isChecked) => {
            const cells = document.querySelectorAll(`#myTable tr th:nth-child(${columnIndex}), #myTable tr td:nth-child(${columnIndex})`);
            cells.forEach(cell => {
                if (isChecked) {
                    cell.classList.remove('default-hide', 'hide-column', 'force-visible');
                } else {
                    cell.classList.add('default-hide');
                    cell.classList.remove('force-visible');
                }
                cell.style.display = '';
            });
        };

        function populateDropdown() {
            const columnSelector = document.getElementById('columnSelector');
            if (!columnSelector) return;
            columnSelector.innerHTML = '';
            const headers = document.querySelectorAll('#myTable thead th');
            const isMobile = window.innerWidth < 1024;

            // Columns to hide by default on mobile (Budget=4, Assigned Lead=5, Lead Source=10, Status=11)
            const mobileHiddenColumns = [4, 5, 10, 11];

            headers.forEach((header, index) => {
                if (header.classList.contains('visibility-skip')) return; // omit from dropdown
                const columnIndex = index + 1;
                const label = document.createElement('label');

                // Check if column should be hidden by default on mobile
                const shouldHideOnMobile = isMobile && mobileHiddenColumns.includes(columnIndex);
                const isChecked = !shouldHideOnMobile && !header.classList.contains('default-hide') && !header.classList.contains('hide-column');

                label.innerHTML = `<input type="checkbox" value="${columnIndex}" ${isChecked ? 'checked' : ''}> ${header.innerText}`;
                columnSelector.appendChild(label);

                applyColumnVisibility(columnIndex, isChecked);
            });
        }

        function toggleColumnVisibility() {
            const selector = document.getElementById('columnSelector');
            if (!selector) return;
            selector.addEventListener('change', function (event) {
                const checkbox = event.target;
                if (checkbox.tagName === 'INPUT' && checkbox.type === 'checkbox') {
                    const column = checkbox.value;
                    const isChecked = checkbox.checked;
                    applyColumnVisibility(column, isChecked);
                }
            });
        }

        populateDropdown();
        toggleColumnVisibility();
    }
    // <!-- column selector script for table end-->
    // This Javascript is for GET The Count of leads Status 
    function getUserLeadsCount() {
        // Only update badges when filters/search explicitly change (not on every badge click)
        if (typeof window.updateBadgeCounts === 'function') {
            window.updateBadgeCounts(searchInput.value.trim(), multifilters, currentFilter, showDeletedOnly);
        }
    }
    // This Javascript is for GET The Count of leads Status End
    const statusBadgeMarkup = (statusText) => {
        const cleanStatus = (statusText || '').toString().trim();
        const slug = cleanStatus
            ? cleanStatus.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-+|-+$)/g, '')
            : 'pending';
        const safeLabel = cleanStatus || 'Pending';
        return `<span class="status-badge ${slug}">${safeLabel}</span>`;
    };

    const maskNumber = (num) => {
        if (!num) return '';
        const str = String(num);
        if (str.length <= 3) return str;
        const visible = str.slice(-3);
        const masked = str.slice(0, -3).replace(/\d/g, '*');
        return `${masked}${visible}`;
    };

    let filterToastTimer = null;

    function showFilterToast(count, label) {
        const toast = document.getElementById('filterToast');
        if (!toast) return;
        const countEl = toast.querySelector('.toast-count');
        const textEl = toast.querySelector('.toast-text');
        if (countEl) countEl.textContent = count ?? 0;
        if (textEl) textEl.textContent = label || 'Leads Filtered';
        toast.classList.add('visible');
        if (filterToastTimer) clearTimeout(filterToastTimer);
        filterToastTimer = setTimeout(() => {
            toast.classList.remove('visible');
        }, 3000);
    }

    function initFilterToastClose() {
        const toast = document.getElementById('filterToast');
        if (!toast) return;
        const closeBtn = toast.querySelector('.toast-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                toast.classList.remove('visible');
            });
        }
    }

    function initNumberToggle() {
        const tableBody = document.getElementById('uploaddata');
        if (!tableBody) return;
        tableBody.addEventListener('click', (e) => {
            const target = e.target.closest('.lead-number');
            if (!target || !tableBody.contains(target)) return;
            const full = target.dataset.fullNumber || '';
            if (!full) return;
            const masked = target.dataset.maskedNumber || maskNumber(full);
            const isFull = target.dataset.visible === 'full';
            target.textContent = isFull ? masked : full;
            target.dataset.visible = isFull ? 'masked' : 'full';
        });
    }

    const untouchedLabel = (createdAt) => {
        if (!createdAt) return '';
        const createdDate = new Date(createdAt);
        if (Number.isNaN(createdDate.getTime())) return '';
        const now = new Date();
        const diffDays = Math.max(0, Math.floor((now - createdDate) / (1000 * 60 * 60 * 24)));
        if (diffDays === 0) return 'New Today';
        return `Untouched From ${diffDays}D`;
    };

    const sourcePillMarkup = (source) => {
        if (!source || !source.trim()) {
            return `<img src="../assets/dataimage/mecntec-icon.png" alt="Not Set" class="source-logo" style="width: 24px; height: 24px; object-fit: contain;">`;
        }

        const sourceLower = source.toLowerCase().trim();
        let sourceIconPath = '';

        // Map source names to icon paths (using userlogin6 assets)
        if (sourceLower.includes('google')) {
            sourceIconPath = '../../userlogin6/assets/dataimage/google-logo.svg';
        } else if (sourceLower.includes('facebook')) {
            sourceIconPath = '../../userlogin6/assets/dataimage/facebook.svg';
        } else if (sourceLower.includes('99acres') || sourceLower.includes('99acre')) {
            sourceIconPath = '../../userlogin6/assets/dataimage/99acre.png';
        } else if (sourceLower.includes('magicbricks')) {
            sourceIconPath = '../../userlogin6/assets/dataimage/magicbricks.png';
        } else if (sourceLower.includes('housing')) {
            sourceIconPath = '../../userlogin6/assets/dataimage/housing.png';
        } else {
            sourceIconPath = '../../userlogin6/assets/dataimage/mecntec-icon.png';
        }

        return `<img src="${sourceIconPath}" alt="${source}" class="source-logo" style="width: 24px; height: 24px; object-fit: contain;">`;
    };

    // Update the table
    function updateTable(rows) {
        uploaddata.innerHTML = ''; // Clear the table body

        rows.forEach(row => {
            const tr = document.createElement('tr');
            const maskedNumber = maskNumber(row.number);
            const untouched = untouchedLabel(row.created_at);
            const untouchedNew = untouched && untouched.toLowerCase().includes('new');
            const numberCellStyle = tablename === 'subham323' ? 'style="display:none;"' : '';
            const statusBadge = statusBadgeMarkup(row.latest_status || row.status);
            const budgetRaw = row.latest_budget ?? row.budget;
            const budgetDisplay = (budgetRaw !== undefined && budgetRaw !== null && String(budgetRaw).trim() !== '') ? budgetRaw : 'NA';

            tr.innerHTML = `
                          <td><input type="checkbox" class="select-row" value="${row.id}"></td>
                          <td>
                            <div class="lead-profile">
                                <div class="left-lead">
                                    <div class="lead-bubble">
                                        <img src="${getNextIconPath()}" alt="${row.name || 'Lead'}" class="lead-avatar" onerror="this.src='./assets/images/avatar.jpeg'">
                            
                                    </div>
                                    <div class="lead-info">
                                        <h4>${row.name || 'Unknown Lead'}</h4>
                                        <div class="lead-meta">
                                            ${maskedNumber ? `<span class="lead-number" data-full-number="${row.number || ''}" data-masked-number="${maskedNumber}" data-visible="masked">${maskedNumber}</span>` : ''}
                                        </div>
                                        ${untouched ? `<div class="untouched-row${untouchedNew ? ' new-today' : ''}">${untouched}</div>` : ''}
                                    </div>
                                </div>
                            </div>
                          </td>
                          <td>
                            <div class="project-info">
                                <div class="project-name">${row.project || 'Not Assigned'}</div>
                                ${statusBadge}
                            </div>
                          </td>
                          <td class="budget-cell default-hide">${budgetDisplay}</td>
                          <td class="user-cell" data-full-users="${row.assign_to_user || ''}"></td>
                          <td class="default-hide">${row.email || ''}</td>
                          <td class="default-hide">${row.location || ''}</td>
                          <td class="default-hide">${row.created_at || ''}</td>
                          <td class="default-hide">${row.id || ''}</td>
                          <td style="text-align: center;">${sourcePillMarkup(row.source_of_lead)}</td>
                          <td>
                            <div class="status-actions">
                            ${showDeletedOnly
                    ? `
                                    <button type="button" class="recover-btn recover-lead" data-id="${row.original_id || row.id}" title="Recover Lead">
                                    <i class="bi bi-arrow-clockwise"></i> Recover
                                    </button>
                                    <button type="button" class="delete-btn permanently-delete-lead" data-id="${row.original_id || row.id}" title="Delete Permanently">
                                    <i class="bi bi-x-octagon"></i> Delete
                                    </button>
                                `
                    : `
                                    <button type="button" class="status-btn status-modal-cls-cmmn view-status" data-bs-toggle="modal" data-bs-target="#viewStatusModal" data-id="${row.id}" title="View Status">
                                    <i class="bi bi-eye"></i> Status
                                    </button>
                                `
                }
                            </div>
                         </td>
                          <td class="expand-btn-cell">
                            <button type="button" class="expand-row-btn" aria-label="Expand row" data-row-id="${row.id}">
                              <i class="bi bi-chevron-down down-arrow"></i>
                              <i class="bi bi-chevron-up up-arrow" style="display: none;"></i>
                            </button>
                          </td>
                          <td class="always-hide visibility-skip" ${numberCellStyle}>${row.number || ''}</td>
                          <td class="always-hide visibility-skip">${row.latest_status || row.status || ''}</td>
                      `;
            uploaddata.appendChild(tr);

            // Create detail row for mobile view
            const detailRow = document.createElement('tr');
            detailRow.className = 'details-row';
            detailRow.setAttribute('data-parent-id', row.id);
            detailRow.style.display = 'none';

            detailRow.innerHTML = `
                <td colspan="100%">
                    <div class="details-content">
                        <div class="details-block details-block-left">
                            <div class="mobile-details-section">
                                <h4>Lead Details</h4>
                                <div class="flexxx">
                                    <strong>Name: &nbsp;</strong>
                                    <div class="detail-row-text">${row.name || 'N/A'}</div>
                                </div>
                                <div class="flexxx">
                                    <strong>Email: &nbsp;</strong>
                                    <div class="detail-row-text">${row.email || 'N/A'}</div>
                                </div>
                                <div class="flexxx">
                                    <strong>Phone: &nbsp;</strong>
                                    <div class="detail-row-text">${row.number || 'N/A'}</div>
                                </div>
                                <div class="flexxx">
                                    <strong>Project: &nbsp;</strong>
                                    <div class="detail-row-text">${row.project || 'N/A'}</div>
                                </div>
                                <div class="flexxx">
                                    <strong>Assigned Lead: &nbsp;</strong>
                                    <div class="detail-row-text assigned-users-container" data-users="${row.assign_to_user || ''}">${formatAssignedUsers(row.assign_to_user)}</div>
                                </div>
                                <div class="flexxx">
                                    <strong>Location: &nbsp;</strong>
                                    <div class="detail-row-text">${row.location || 'N/A'}</div>
                                </div>
                                <div class="flexxx">
                                    <strong>Budget: &nbsp;</strong>
                                    <div class="detail-row-text">${budgetDisplay}</div>
                                </div>
                                <div class="flexxx">
                                    <strong>Status: &nbsp;</strong>
                                    <div class="detail-row-text">${row.latest_status || row.status || 'N/A'}</div>
                                </div>
                                <div class="flexxx">
                                    <strong>Created: &nbsp;</strong>
                                    <div class="detail-row-text">${row.created_at || 'N/A'}</div>
                                </div>
                                <div class="flexxx">
                                    <strong>Source: &nbsp;</strong>
                                    <div class="detail-row-text">${row.source_of_lead || 'N/A'}</div>
                                </div>
                                <div class="flexxx" style="margin-top: 12px;">
                                    ${showDeletedOnly ?
                    '<button type="button" class="recover-btn recover-lead" data-id="' + (row.original_id || row.id) + '" title="Recover Lead" style="margin-right: 8px; background: #e0f2fe; color: #0c4a6e; border-radius: 5px; border: none; padding: 6px 5px;"><i class="bi bi-arrow-clockwise"></i> Recover</button>' +
                    '<button type="button" class="delete-btn permanently-delete-lead" data-id="' + (row.original_id || row.id) + '" title="Delete Permanently"><i class="bi bi-x-octagon"></i> Delete</button>'
                    :
                    '<button type="button" class="status-btn status-modal-cls-cmmn view-status" data-bs-toggle="modal" data-bs-target="#viewStatusModal" data-id="' + row.id + '" title="View Status"><i class="bi bi-eye"></i> Status</button>'
                }
                                </div>
                            </div>
                        </div>
                    </div>
                </td>
            `;

            uploaddata.appendChild(detailRow);
        });

        // Apply responsive behavior after table is populated
        if (typeof handleResponsiveBehavior === 'function') {
            handleResponsiveBehavior();
        }

        // Add event delegation for assigned users expand/collapse
        document.querySelectorAll('.assigned-users-more').forEach(btn => {
            btn.addEventListener('click', function() {
                const wrapper = this.closest('.assigned-users-wrapper');
                const hiddenUsers = wrapper.querySelectorAll('.assigned-user-hidden');
                const isExpanded = this.getAttribute('data-expanded') === 'true';
                
                if (isExpanded) {
                    // Collapse: hide additional users
                    hiddenUsers.forEach(user => {
                        user.style.display = 'none';
                    });
                    const totalHidden = hiddenUsers.length;
                    this.textContent = `+${totalHidden} more`;
                    this.setAttribute('data-expanded', 'false');
                } else {
                    // Expand: show all users
                    hiddenUsers.forEach(user => {
                        user.style.display = 'inline';
                    });
                    this.textContent = 'Show less';
                    this.setAttribute('data-expanded', 'true');
                }
            });
        });

        if (showDeletedOnly) {
            // Recover Lead
            document.querySelectorAll('.recover-lead').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const id = btn.getAttribute('data-id');

                    const confirmResult = await Swal.fire({
                        title: 'Recover Lead?',
                        text: "Are you sure you want to recover this lead?",
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonText: 'Yes, recover it!',
                        cancelButtonText: 'Cancel'
                    });

                    if (confirmResult.isConfirmed) {
                        try {
                            const result = await safeFetch('bulk_delete.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: `recover_id=${encodeURIComponent(id)}`
                            });
                            if (result.status === 'success') {
                                Swal.fire('Recovered!', 'Lead successfully recovered.', 'success');
                                // Refresh the deleted leads table
                                fetchData(1, 10, '', '', {}, true);
                                // Update badge counts to reflect the recovery
                                if (typeof window.updateBadgeCounts === 'function') {
                                    window.updateBadgeCounts('', {}, '', false);
                                }
                            } else {
                                Swal.fire('Error!', result.message || 'Unknown error', 'error');
                            }
                        } catch (e) {
                            Swal.fire('Error!', 'Error recovering lead.', 'error');
                        }
                    }
                });
            });

            // Permanently Delete Lead
            document.querySelectorAll('.permanently-delete-lead').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const id = btn.getAttribute('data-id');

                    const confirmResult = await Swal.fire({
                        title: 'Delete Permanently?',
                        text: "This action cannot be undone. Are you sure?",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Yes, delete it!',
                        cancelButtonText: 'Cancel'
                    });

                    if (confirmResult.isConfirmed) {
                        try {
                            const result = await safeFetch('bulk_delete.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: `permanent_delete_id=${encodeURIComponent(id)}`
                            });
                            if (result.status === 'success') {
                                Swal.fire('Deleted!', 'Lead permanently deleted.', 'success');
                                // Refresh the deleted leads table
                                fetchData(1, 10, '', '', {}, true);
                                // Update badge counts to reflect the deletion
                                if (typeof window.updateBadgeCounts === 'function') {
                                    window.updateBadgeCounts('', {}, '', false);
                                }
                            } else {
                                Swal.fire('Error!', result.message || 'Unknown error', 'error');
                            }
                        } catch (e) {
                            Swal.fire('Error!', 'Error deleting lead.', 'error');
                        }
                    }
                });
            });
        }
    }

    // Update pagination
    function updatePagination(totalRows, currentPage, rowsPerPage) {
        totalPages = Math.ceil(totalRows / rowsPerPage); // Calculate total pages

        const start = (currentPage - 1) * rowsPerPage + 1;
        const end = Math.min(currentPage * rowsPerPage, totalRows);
        rowInfo.innerText = `Showing ${start} to ${end} of ${totalRows} entries`;

        prevButton.disabled = currentPage === 1;
        nextButton.disabled = currentPage === totalPages;

        pageNumbersContainer.innerHTML = '';

        const createPageButton = (pageNumber) => {
            const button = document.createElement('button');
            button.innerText = pageNumber;
            button.className = pageNumber === currentPage ? 'active' : '';
            button.addEventListener('click', function () {
                fetchData(pageNumber, rowsPerPage, searchInput.value.trim(), currentFilter, multifilters, showDeletedOnly);
            });
            return button;
        };

        const addEllipsis = () => {
            const ellipsis = document.createElement('span');
            ellipsis.innerText = '...';
            pageNumbersContainer.appendChild(ellipsis);
        };

        // Always show the first page only if currentPage is 1
        if (currentPage === 1) {
            pageNumbersContainer.appendChild(createPageButton(1));
            if (totalPages > 2) addEllipsis();
            if (totalPages > 1) pageNumbersContainer.appendChild(createPageButton(totalPages));
        }
        else {
            // Show the first page and add ellipsis
            pageNumbersContainer.appendChild(createPageButton(1));
            addEllipsis();

            // Show the current page and one neighbor on each side if possible
            if (currentPage > 2) {
                pageNumbersContainer.appendChild(createPageButton(currentPage - 1));
            }

            pageNumbersContainer.appendChild(createPageButton(currentPage));

            if (currentPage < totalPages - 1) {
                pageNumbersContainer.appendChild(createPageButton(currentPage + 1));
            }

            // Add ellipsis before the last page if currentPage is not near the end
            if (currentPage < totalPages - 2) {
                addEllipsis();
            }

            // Always show the last page
            pageNumbersContainer.appendChild(createPageButton(totalPages));
        }
    }

    // Event listeners for pagination
    rowSelector.addEventListener('change', function () {
        rowsPerPage = parseInt(this.value, 10);
        currentPage = 1;
        fetchData(currentPage, rowsPerPage, searchInput.value.trim(), currentFilter, multifilters, showDeletedOnly);
    });

    let badgeRefreshTimer = null;
    searchInput.addEventListener('keyup', function () {
        const searchQuery = this.value;
        currentPage = 1;
        fetchData(currentPage, rowsPerPage, searchQuery, currentFilter, multifilters, showDeletedOnly);

        // Debounce badge refresh so counts update only after user pauses typing
        if (badgeRefreshTimer) clearTimeout(badgeRefreshTimer);
        badgeRefreshTimer = setTimeout(() => {
            getUserLeadsCount();
        }, 300);
    });

    prevButton.addEventListener('click', function () {
        if (currentPage > 1) {
            currentPage--;
            fetchData(currentPage, rowsPerPage, searchInput.value.trim(), currentFilter, multifilters, showDeletedOnly);
        }
    });

    nextButton.addEventListener('click', function () {
        if (currentPage < totalPages) {
            currentPage++;
            fetchData(currentPage, rowsPerPage, searchInput.value.trim(), currentFilter, multifilters, showDeletedOnly);
        }
    });

    if (statusFilterSelect) {
        statusFilterSelect.addEventListener('change', function () {
            const statusValue = this.value;
            currentFilter = statusValue;
            showDeletedOnly = false;
            currentPage = 1;

            // Clear badge highlights when using status dropdown
            leadTypeButtons.forEach(btn => btn.classList.remove('active-deleted'));

            fetchData(currentPage, rowsPerPage, searchInput.value.trim(), statusValue, multifilters, showDeletedOnly);
        });
    }

    // NEW Status Filter Dropdown Handler
    const statusFilterBtn = document.getElementById('filterStatus');
    const statusFilterContainer = document.querySelector('.status-filter-container');
    const statusOptions = document.querySelectorAll('.status-option');
    const statusSearchInput = document.querySelector('.status-search-input');

    if (statusFilterBtn && statusFilterContainer) {
        // Toggle dropdown
        statusFilterBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            statusFilterContainer.classList.toggle('active');

            const dropdown = statusFilterContainer.querySelector('.status-filter-dropdown');
            if (dropdown && statusFilterContainer.classList.contains('active')) {
                const rect = statusFilterBtn.getBoundingClientRect();
                dropdown.style.position = 'fixed';
                dropdown.style.left = `${rect.left}px`;
                dropdown.style.top = `${rect.bottom + 6}px`;
                dropdown.style.minWidth = `${rect.width}px`;
                dropdown.style.zIndex = '3000';
            }
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function (e) {
            if (!statusFilterContainer.contains(e.target)) {
                statusFilterContainer.classList.remove('active');
            }
        });

        // Handle status selection
        statusOptions.forEach(option => {
            option.addEventListener('click', function () {
                const statusValue = this.dataset.status;
                const filterValue = statusValue === 'All' ? '' : statusValue;

                showDeletedOnly = false;
                currentPage = 1;

                // Clear badge highlights when using status dropdown
                leadTypeButtons.forEach(btn => btn.classList.remove('active'));

                // Persist status filter and treat it like a multi-filter
                statusFilterValue = filterValue;
                if (filterValue) {
                    multifilters = { ...multifilters, status: [filterValue] };
                    showFloatingClearButton();
                } else {
                    if (multifilters.status) delete multifilters.status;
                    if (!hasActiveFilters()) hideFloatingClearButton();
                }

                // Update button text
                const selectedText = this.querySelector('.status-badge').textContent;
                statusFilterBtn.innerHTML = `<i class="fa-solid fa-filter"></i> ${selectedText} <i class="fa-solid fa-caret-down"></i>`;

                // Keep currentFilter (badge) intact; just apply status as an additional filter
                fetchData(currentPage, rowsPerPage, searchInput.value.trim(), currentFilter, multifilters, showDeletedOnly);
                getUserLeadsCount();

                // Close dropdown
                statusFilterContainer.classList.remove('active');
            });
        });

        // Status search functionality
        if (statusSearchInput) {
            statusSearchInput.addEventListener('input', function () {
                const searchTerm = this.value.toLowerCase();
                statusOptions.forEach(option => {
                    const statusText = option.querySelector('.status-badge').textContent.toLowerCase();
                    if (statusText.includes(searchTerm)) {
                        option.style.display = 'block';
                    } else {
                        option.style.display = 'none';
                    }
                });
            });
        }
    }

    jumpButton.addEventListener('click', function () {
        const pageNumber = parseInt(jumpToPageInput.value, 10);
        if (pageNumber >= 1 && pageNumber <= totalPages) {
            currentPage = pageNumber;
            fetchData(pageNumber, rowsPerPage, searchInput.value.trim(), currentFilter, multifilters, showDeletedOnly);
        } else {
            alert('Please enter a valid page number.');
        }
    });

    document.getElementById('totalUnassigned').addEventListener('click', function () {
        currentPage = 1; // Reset to first page
        fetchData(currentPage, rowsPerPage, '', 'unassigned');
    });

    document.getElementById('myLeads').addEventListener('click', function () {
        currentPage = 1; // Reset to first page
        fetchData(currentPage, rowsPerPage, '', 'my');
    });

    document.getElementById('freshLeads').addEventListener('click', function () {
        currentPage = 1; // Reset to first page
        fetchData(currentPage, rowsPerPage, '', 'fresh');
    });

    document.getElementById('totalLeads').addEventListener('click', function () {
        currentPage = 1; // Reset to first page
        currentFilter = '';
        showDeletedOnly = false;
        fetchData(currentPage, rowsPerPage, searchInput.value.trim(), currentFilter, multifilters, showDeletedOnly);
    });

    // Fetch initial data on page load
    document.addEventListener('DOMContentLoaded', () => {
        currentPage = 1; // Reset to first page
        fetchData(currentPage, rowsPerPage, searchInput.value.trim(), '');
        // Update badge counts on page load once (base view only)
        setTimeout(() => {
            if (typeof window.updateBadgeCounts === 'function') {
                window.updateBadgeCounts(searchInput.value.trim(), multifilters, '', false);
            }
        }, 200);
    });

    // Handle row selection (individual and bulk)
    selectAllCheckbox.addEventListener('change', function () {
        const allRowCheckboxes = document.querySelectorAll('.select-row');
        selectedIds = []; // Reset selected IDs array

        allRowCheckboxes.forEach(checkbox => {
            checkbox.checked = this.checked; // Check/uncheck all rows
            if (this.checked) {
                selectedIds.push(checkbox.value); // Add all row IDs to selectedIds array
            }
        });

        selectedIdsInput.value = selectedIds.join(','); // Update hidden input
        toggleAssignButton(); // Recheck button state
    });

    uploaddata.addEventListener('change', function (event) {
        if (event.target.classList.contains('select-row')) {
            if (!event.target.checked) {
                selectAllCheckbox.checked = false; // Uncheck "Select All" if any row is unchecked
            } else {
                const allRowCheckboxes = document.querySelectorAll('.select-row');
                const allChecked = Array.from(allRowCheckboxes).every(checkbox => checkbox.checked);
                selectAllCheckbox.checked = allChecked;
            }
        }
    });

    //  this is javascript is for trim assigned users name 
    // Select all the cells with class 'user-cell'

    // Handle bulk deletion
    deleteSelectedBtn.addEventListener('click', function (event) {
        event.preventDefault();
        const selectedCheckboxes = document.querySelectorAll('.select-row:checked');
        const numSelected = selectedCheckboxes.length;

        if (numSelected > 0) {
            Swal.fire({
                title: 'Are you sure?',
                text: `You are about to delete ${numSelected} selected row(s). This action cannot be undone.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, delete them!',
                cancelButtonText: 'No, cancel!'
            }).then((result) => {
                if (result.isConfirmed) {
                    const selectedIds = Array.from(selectedCheckboxes).map(checkbox => checkbox.value);

                    // Perform the AJAX request for deletion
                    fetch('bulk_delete.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `row_ids=${encodeURIComponent(JSON.stringify(selectedIds))}` // Send the selected IDs
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.status === 'success') {
                                // Remove the deleted rows from the table
                                selectedCheckboxes.forEach(checkbox => {
                                    const row = checkbox.closest('tr');
                                    row.remove();
                                });

                                Swal.fire('Deleted!', 'Selected rows have been deleted.', 'success');

                                // Check if all rows on the current page have been deleted
                                const remainingRows = document.querySelectorAll('#uploaddata tr').length;

                                if (remainingRows === 0 && currentPage > 1) {
                                    // If no rows remain on the current page, go to the previous page
                                    currentPage--;
                                }
                                // Fetch updated data while staying on the current page
                                fetchData(currentPage, rowsPerPage, searchInput.value.trim(), currentFilter, multifilters, showDeletedOnly);
                            } else {
                                Swal.fire('Error!', 'There was a problem deleting the rows.', 'error');
                            }
                        })
                        .catch(error => {
                            console.error('Error deleting rows:', error);
                            Swal.fire('Error!', 'There was an error processing your request.', 'error');
                        });
                }
            });
        } else {
            Swal.fire('No rows selected', 'Please select rows to delete.', 'info');
        }
    });
    // Handle bulk deletion End

    // Handle assign modal open - populate current assigned users
    const assignModalElement = document.getElementById('assignModal');
    if (assignModalElement) {
        assignModalElement.addEventListener('show.bs.modal', function () {
            // Get all currently assigned users from selected rows
            const selectedCheckboxes = document.querySelectorAll('.select-row:checked');
            const currentAssignedUsers = new Set();

            selectedCheckboxes.forEach(checkbox => {
                const row = checkbox.closest('tr');
                if (row) {
                    const userCell = row.querySelector('.user-cell');
                    if (userCell) {
                        const fullUsers = userCell.getAttribute('data-full-users') || '';
                        if (fullUsers && fullUsers.trim() !== '') {
                            // Split by comma if multiple users
                            const users = fullUsers.split(',').map(u => u.trim()).filter(u => u);
                            users.forEach(user => currentAssignedUsers.add(user));
                        }
                    }
                }
            });

            // Display current assigned users in the container
            const container = document.getElementById('current-assigned-users');
            if (container && currentAssignedUsers.size > 0) {
                container.innerHTML = '<strong>Currently Assigned:</strong> ' + Array.from(currentAssignedUsers).join(', ');
                container.style.display = 'block';
            } else if (container) {
                container.innerHTML = '<strong>Currently Assigned:</strong> None';
                container.style.display = 'block';
            }
        });
    }

    // Variables for row selection and assign user
    const assignButton = document.getElementById('assign-button');
    const userSelect = document.getElementById('user-select'); // Updated to new unified select
    const hiddenUsersInput = document.getElementById('hidden-users');
    const selectedIdsInput = document.getElementById('selected-ids');
    const selectedCountElement = document.getElementById('selected-count'); // Element to show selected row count
    let selectedIds = [];
    let selectedUsers = [];

    // Initialize Select2 for unified user selection
    if (userSelect) {
        $(userSelect).select2({
            placeholder: 'Search and select users...',
            allowClear: true,
            closeOnSelect: false,
            dropdownParent: $('#assignModal'),
            width: '100%'
        });
    }

    // Function to enable/disable the assign button
    function toggleAssignButton() {
        // Enable the button if at least one row is selected
        assignButton.disabled = selectedIds.length === 0;

        // Update the selected row count in the modal
        selectedCountElement.textContent = selectedIds.length;

        if (createCronFab && createCronFabCount) {
            createCronFab.style.display = selectedIds.length > 0 ? 'inline-flex' : 'none';
            createCronFabCount.textContent = selectedIds.length;
        }
    }

    // Handle row selection (checkboxes)
    document.getElementById('uploaddata').addEventListener('change', function (event) {
        if (event.target.classList.contains('select-row')) {
            const rowId = event.target.value; // Get the row ID
            if (event.target.checked) {
                selectedIds.push(rowId); // Add to selected rows
            } else {
                selectedIds = selectedIds.filter(id => id !== rowId); // Remove from selected rows
                selectAllCheckbox.checked = false;
            }
            selectedIdsInput.value = selectedIds.join(','); // Update hidden input
            toggleAssignButton(); // Recheck button state
        }
    });

    // Handle user selection with Select2
    if (userSelect) {
        $(userSelect).on('change', function () {
            selectedUsers = $(this).val() || []; // Get selected values from Select2
            updateHiddenInput();
            toggleAssignButton(); // Recheck button state
        });
    }

    // Function to update hidden input for users
    function updateHiddenInput() {
        hiddenUsersInput.value = selectedUsers.join(','); // Update hidden input with selected user IDs
    }

    // Handle Assign User button click
    document.getElementById('modal-assign-button').addEventListener('click', function (event) {
        event.preventDefault();  // Prevent default form submission

        if (selectedIds.length === 0) {
            alert('Please select at least one rows to assign.');
            return;
        }

        const formData = new FormData();
        formData.append('selected_ids', selectedIds.join(','));
        formData.append('users', selectedUsers.join(',')); // Pass selected users if necessary
        formData.append('assignprojectname', document.getElementById('assignprojectname').value);

        // Make the fetch request to assign users
        fetch('assign_users.php', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())  // Parse the JSON response
            .then(data => {
                // Show toast notification at the bottom instead of top alert
                Swal.fire({
                    toast: true,
                    position: 'bottom',
                    icon: data.status === 'success' ? 'success' : 'error',
                    title: data.message,
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true,
                    customClass: {
                        popup: 'custom-toast-popup'
                    }
                });

                // Refresh table data (if needed)
                if (data.status === 'success') {
                    // Hide the modal after a successful assignment
                    // document.querySelector('#assignModal .close').click();  // Simulate the close button click
                    const assignUserModal = bootstrap.Modal.getInstance(document.getElementById('assignModal'));
                    assignUserModal.hide();

                    // Remove backdrop and restore body scroll
                    setTimeout(() => {
                        document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
                        document.body.classList.remove('modal-open');
                        document.body.style.overflow = '';
                        document.body.style.paddingRight = '';
                    }, 300);

                    // Clear the form fields
                    document.getElementById('assign-form').reset();  // Reset the form fields
                    $(userSelect).val(null).trigger('change');  // Clear Select2 selection
                    selectedIds = [];  // Clear selected IDs
                    selectedUsers = [];  // Clear selected users

                    // Reset hidden inputs
                    selectedIdsInput.value = '';
                    hiddenUsersInput.value = '';

                    // Disable the assign button
                    assignButton.disabled = true;
                    // Reset the selected row count in the modal
                    selectedCountElement.textContent = selectedIds.length;

                    // Check if all rows on the current page have been deleted
                    const remainingRows = document.querySelectorAll('#uploaddata tr').length;

                    if (remainingRows === 0 && currentPage > 1) {
                        // If no rows remain on the current page, go to the previous page
                        currentPage--;
                    }

                    // Fetch updated data while staying on the current page
                    fetchData(currentPage, rowsPerPage, searchInput.value.trim(), currentFilter, multifilters, showDeletedOnly);
                    getUserLeadsCount();
                }

                // Hide the message after 5 seconds
                setTimeout(() => {
                    uploadMessageassign.style.display = "none";
                }, 5000);
            })
            .catch(error => {
                console.error('Error assigning users:', error);
                alert('There was an error processing the request.');
            });
    });
    // Prevent form submission and reload
    const assignForm = document.getElementById('assign-form');
    assignForm.addEventListener('submit', function (event) {
        event.preventDefault();  // Prevent form submission
        assignButton.click();  // Trigger the assign button click
    });

    if (createCronModal) {
        createCronModal.addEventListener('show.bs.modal', function () {
            initializeCronUserSelect2();

            const checkedIds = Array.from(document.querySelectorAll('.select-row:checked')).map(cb => cb.value);
            if (checkedIds.length > 0) {
                selectedIds = checkedIds;
                selectedIdsInput.value = selectedIds.join(',');
                toggleAssignButton();
            }
            if (cronSelectedCount) {
                cronSelectedCount.textContent = selectedIds.length;
            }
        });
    }

    if (createCronForm) {
        createCronForm.addEventListener('submit', async function (event) {
            event.preventDefault();

            const rowIds = selectedIds.join(',');
            const projectName = (document.getElementById('cron-project-name')?.value || '').trim();
            const location = (document.getElementById('cron-location')?.value || '').trim();
            const intervalValue = parseInt(document.getElementById('cron-interval')?.value || '0', 10);
            const cronAssignedUserSelect = document.getElementById('cron-assigned-user');
            const assignedUsers = cronAssignedUserSelect
                ? Array.from(cronAssignedUserSelect.selectedOptions || [])
                    .map(option => (option.value || '').trim())
                    .filter(Boolean)
                : [];
            const assignedUsersCsv = assignedUsers.join(',');

            if (!rowIds) {
                Swal.fire({ icon: 'info', title: 'No leads selected', text: 'Select at least one lead.' });
                return;
            }
            if (!projectName || !location || assignedUsers.length === 0 || !intervalValue || intervalValue < 1) {
                Swal.fire({ icon: 'warning', title: 'Missing details', text: 'Please fill all fields and select at least one user. Interval must be at least 1 minute.' });
                return;
            }

            const payload = new FormData();
            payload.append('row_ids', rowIds);
            payload.append('project_name', projectName);
            payload.append('location', location);
            payload.append('interval_time', String(intervalValue));
            payload.append('assigned_user', assignedUsersCsv);

            try {
                const response = await safeFetch('create_cron_job.php', { method: 'POST', body: payload });
                const result = typeof response === 'string' ? JSON.parse(response) : response;

                Swal.fire({
                    toast: true,
                    position: 'bottom',
                    icon: result.status === 'success' ? 'success' : 'error',
                    title: result.message || 'Request completed',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true,
                    customClass: { popup: 'custom-toast-popup' }
                });

                if (result.status === 'success') {
                    const cronModalInstance = bootstrap.Modal.getInstance(createCronModal);
                    if (cronModalInstance) cronModalInstance.hide();
 
                    setTimeout(() => {
                        document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
                        document.body.classList.remove('modal-open');
                        document.body.style.overflow = '';
                        document.body.style.paddingRight = '';
                    }, 300);

                    createCronForm.reset();
                    selectedIds = [];
                    selectedIdsInput.value = '';
                    selectAllCheckbox.checked = false;
                    document.querySelectorAll('.select-row:checked').forEach(cb => {
                        cb.checked = false;
                    });
                    toggleAssignButton();
                }
            } catch (error) {
                console.error('Error creating cron job:', error);
                Swal.fire({
                    toast: true,
                    position: 'bottom',
                    icon: 'error',
                    title: 'Unable to create cron job',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true,
                    customClass: { popup: 'custom-toast-popup' }
                });
            }
        });
    }
    // Assign User modle script end here
    // Function to process user cells (run this after table is updated)
    function processUserCells() {
        const userCells = document.querySelectorAll('.user-cell');
        userCells.forEach(cell => {
            const fullUsers = (cell.dataset.fullUsers || cell.innerText || '')
                .split(',')
                .map(name => name.trim())
                .filter(Boolean);

            const displayLabel = fullUsers.length > 1 ? `${fullUsers[0]} ...more` : (fullUsers[0] || 'Unassigned');
            cell.innerHTML = `<span class="assigned-pill">${displayLabel}</span>`;

            cell.addEventListener('click', function () {
                document.getElementById('assignUserList').innerText = fullUsers.join(', ') || displayLabel;
                const assignUserModal = new bootstrap.Modal(document.getElementById('assignUserModal'));
                assignUserModal.show();
            });
        });
    }
    // Initial fetch of data
    fetchData(currentPage, rowsPerPage);
    // this javascript is for trim the assign user ENd
});
// this is loader javascript 
document.addEventListener("DOMContentLoaded", function () {
    var loader = document.getElementById('loader');
    if (!loader) return;
    // Show loader initially
    loader.style.opacity = '1';
    loader.style.top = '0';
    loader.style.zIndex = '1002'; // Set initial z-index to 999
    // Hide loader after a short delay; once hidden, remove from flow so minor scrolls cannot reveal it
    setTimeout(function () {
        loader.style.transition = 'opacity 1s ease, top 1s ease, z-index 1s'; // Add z-index transition
        loader.style.opacity = '0';
        loader.style.top = '-100px'; // Move loader smoothly upward
        loader.style.zIndex = '0'; // Set z-index to 0 when hiding loader
        setTimeout(function () {
            loader.style.display = 'none';
        }, 800);
    }, 2000);
});
// this is loader javascript End
// this script is for showing the status popup model
$(document).ready(function () {
    // Event delegation: Use a parent element that exists when the page loads
    $(document).on('click', '.view-status', function (event) {
        event.preventDefault(); // Prevent the default action (page refresh)
        var uploadDataId = $(this).data('id');
        fetchData(uploadDataId, 'status');
    });

    $(document).on('click', '.view-remarks', function (event) {
        event.preventDefault(); // Prevent the default action (page refresh)
        var uploadDataId = $(this).data('id');
        fetchData(uploadDataId, 'remarks');
    });

    // Function to fetch data for either status or remarks
    function fetchData(uploadDataId, type) {
        $.ajax({
            url: 'get_user_updates.php',
            method: 'GET',
            data: { upload_data_id: uploadDataId, type: type },
            success: function (response) {
                if (type === 'status') {
                    $('#statusModalData').html(response); // Inject data into the modal body
                    $('#viewStatusModal').modal('show'); // Show the Status modal
                } else if (type === 'remarks') {
                    $('#remarksModalData').html(response); // Inject data into the modal body
                    $('#viewRemarksModal').modal('show'); // Show the Remarks modal
                }
            },
            error: function (xhr, status, error) {
                alert('Error fetching data. Please try again.');
                console.error('AJAX Error: ', error); // Log any error
            }
        });
    }
});
// this script is for showing the status popup model End 
// Download Excle Sheet
document.getElementById('download-excel-ex').addEventListener('click', function () {
    window.location.href = '/incentiveapp_integration/userlogin1/superadmin/crm/example_format.xlsx?v=' + Date.now();
});
// Download Excle Sheet End
// This is script for sidebar toggel start
document.body.addEventListener("click", (event) => {
    const target = event.target.closest(".unique-toggle-btn, .call-counter");

    if (target) {
        const rowId = target.getAttribute("data-id");
        const userUniqueId = target.getAttribute("data-userid"); // Get user_unique_id
        // console.log("Row ID:", rowId, "User Unique ID:", userUniqueId);

        if (target.classList.contains("unique-toggle-btn")) {
            fetchHistory(rowId, userUniqueId);
            toggleSidebar("uniqueLeadHistorySidebar");
        } else if (target.classList.contains("call-counter")) {
            fetchCallHistory(rowId, userUniqueId);
            toggleSidebar("uniqueCallHistorySidebar");
        }
    }
});

// Toggle sidebar visibility
function toggleSidebar(sidebarId) {
    const sidebar = document.getElementById(sidebarId);
    if (sidebar) {
        const isActive = sidebar.classList.contains("active");

        if (isActive) {
            closeSidebar(sidebarId);
        } else {
            sidebar.style.display = "block";
            setTimeout(() => sidebar.classList.add("active"), 10);
        }
    } else {
        console.error(`Sidebar with ID ${sidebarId} not found.`);
    }
}

// Close sidebar function
function closeSidebar(sidebarId) {
    const sidebar = document.getElementById(sidebarId);
    if (sidebar) {
        sidebar.classList.remove("active");
        setTimeout(() => (sidebar.style.display = "none"), 300);
    }
}

// Initialize event listeners for close buttons
document.getElementById("uniqueCloseSidebar").addEventListener("click", () => closeSidebar("uniqueLeadHistorySidebar"));
document.getElementById("uniqueCloseCallSidebar").addEventListener("click", () => closeSidebar("uniqueCallHistorySidebar"));

// Initialize dropdown toggle logic
function initializeLeadHistoryClickListeners() {
    document.querySelectorAll(".unique-lead-history li").forEach((item) => {
        item.addEventListener("click", () => {
            const dropdown = item.querySelector(".unique-dropdown");
            const uparrow = item.querySelector(".unique-uparrow");
            const downarrow = item.querySelector(".unique-downarrow");

            const isDropdownVisible = dropdown.classList.contains("show");

            // Reset all dropdowns and arrows
            document.querySelectorAll(".unique-dropdown").forEach((dd) => dd.classList.remove("show"));
            document.querySelectorAll(".unique-uparrow").forEach((ua) => (ua.style.display = "none"));
            document.querySelectorAll(".unique-downarrow").forEach((da) => (da.style.display = "inline"));

            // Show or hide the current dropdown
            if (isDropdownVisible) {
                dropdown.classList.remove("show");
                uparrow.style.display = "none";
                downarrow.style.display = "inline";
            } else {
                dropdown.classList.add("show");
                uparrow.style.display = "inline";
                downarrow.style.display = "none";
            }
        });
    });
}

// Re-initialize the event listeners after dynamically rendering rows
function fetchHistory(rowId, userUniqueId) {
    // console.log('Fetching history for row ID:', rowId, 'User Unique ID:', userUniqueId);
    fetch('get_user_updates.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ fetchHistory: true, rowId, user_unique_id: userUniqueId }),
    })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                const followUpHistory = document.getElementById('followUpHistory');
                followUpHistory.innerHTML = ''; // Clear previous history

                if (data.history.length === 0) {
                    followUpHistory.innerHTML = '<li class="list-group-item">No follow-up history found.</li>';
                } else {
                    data.history.forEach(entry => {
                        // Determine the appropriate status class using the switch case
                        let statusClass = '';
                        switch (entry.status) {
                            case 'Pending':
                                statusClass = 'history-pending';
                                break;
                            case 'Fake':
                                statusClass = 'history-fake';
                                break;
                            case 'RNR':
                                statusClass = 'history-rnr';
                                break;
                            case 'Call Back':
                                statusClass = 'history-call-back';
                                break;
                            case 'Already Booked':
                                statusClass = 'history-booked';
                                break;
                            case 'Not Interested':
                                statusClass = 'history-not-interested';
                                break;
                            case 'Interested':
                                statusClass = 'history-interested';
                                break;
                            case 'Follow Up':
                                statusClass = 'history-follow-up';
                                break;
                            case 'Fix Site Visit':
                                statusClass = 'history-visit';
                                break;
                            case 'Site Visit Done':
                                statusClass = 'history-visit-done';
                                break;
                            case 'Converted':
                                statusClass = 'history-eoi-collected';
                                break;
                            case 'Re site visit':
                                statusClass = 'history-re-site-visit';
                                break;
                            case 'NQFTP':
                                statusClass = 'history-NQFTP';
                                break;
                            case 'Not Connected':
                                statusClass = 'history-not-connected';
                                break;
                            default:
                                statusClass = ''; // No class if the status doesn't match any case
                                break;
                        }

                        const li = document.createElement('li');
                        li.classList.add('unique-step', 'unique-active-timeline');
                        li.innerHTML = `
                            <div class="unique-dot"></div>
                            <div class="unique-content">
                                <div>
                                    <span class="unique-status-info ${statusClass}">${entry.status}</span>
                                    <span class="unique-date-time">${entry.timestamp}</span>
                                </div>
                                <span class="unique-arrow unique-downarrow">▼</span>
                                <span class="unique-arrow unique-uparrow">▲</span>
                            </div>
                            <div class="unique-dropdown">
                                <div class="unique-dropdown-insides">
                                    <div class="note-containers">
                                        <span><b>Updated By:</b> ${entry.update_by || 'No User Available'}</span>
                                        <span><b>Date & Time:</b> ${entry.followUpDate || 'N/A'} ${entry.followUpTime || 'N/A'}</span>
                                        <span><b>Notes:</b> ${entry.notes || 'No notes available'}</span>
                                    </div>
                                </div>
                            </div>
                        `;
                        followUpHistory.appendChild(li);
                    });

                    // Re-initialize click listeners for dynamically added rows
                    initializeLeadHistoryClickListeners();
                    // Populate assigned date and assigned by user
                    document.getElementById('assigned_date_leads').innerText =
                        data.assignedDate || 'N/A';
                    document.getElementById('assigned_by_user').innerText =
                        data.assignedBy || 'N/A';
                    document.getElementById('lead_user_name').innerText =
                        data.lead_user || 'N/A';
                    document.getElementById('lead_user_number').innerText =
                        data.lead_number || 'N/A';
                }
            } else {
                console.error('Failed to fetch history:', data.message);
            }
        })
        .catch(error => console.error('Error fetching history:', error));
}
function fetchCallHistory(rowId, userUniqueId) {
    // console.log('Fetching history for row ID:', rowId, 'User Unique ID:', userUniqueId);
    fetch('get_user_updates.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ fetchCallHistory: true, rowId, user_unique_id: userUniqueId }),
    })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                const followUpHistory = document.getElementById('followUpCallHistory');
                followUpHistory.innerHTML = ''; // Clear previous history

                if (data.history.length === 0) {
                    followUpHistory.innerHTML = '<li class="list-group-item">No follow-up history found.</li>';
                } else {
                    data.history.forEach(entry => {
                        // Styling CSS END FOR STATUS
                        const li = document.createElement('li');
                        li.classList.add('unique-step', 'unique-active-timeline');
                        li.innerHTML = `
                            <div class="unique-dot"></div>
                            <div class="unique-content">
                                <span class="unique-status-info">Call Attempted: ${entry.click_attempted}</span>
                                <span class="unique-arrow">→</span> 
                                <span class="unique-status-view"><a href="#.">Date</a></span> 
                                <span class="unique-arrow">→</span>
                                <span class="unique-date-time">${entry.timestamp}</span>
                            </div>
                        `;
                        followUpHistory.appendChild(li);
                    });

                    // Re-initialize click listeners for dynamically added rows
                    initializeLeadHistoryClickListeners();
                    // Populate assigned date and assigned by user
                    const assignedDateElement = document.getElementById('assigned_date_callleads');
                    if (assignedDateElement) {
                        assignedDateElement.innerText = data.assignedDate || 'N/A';
                    }

                    const assignedByElement = document.getElementById('assigned_by_calluserr');
                    if (assignedByElement) {
                        if (data.assignedBy) {
                            assignedByElement.innerText = data.assignedBy;
                        } else {
                            assignedByElement.innerText = 'N/A';  // ✅ Debugging: Check if it’s null
                            console.error('Assigned By is missing from the response:', data);
                        }
                    }

                    const leadUserNameElement = document.getElementById('lead_user_callname');
                    if (leadUserNameElement) {
                        leadUserNameElement.innerText = data.lead_user || 'N/A';
                    }

                    const leadUserNumberElement = document.getElementById('lead_user_callnumber');
                    if (leadUserNumberElement) {
                        leadUserNumberElement.innerText = data.lead_number || 'N/A';
                    }
                }
            } else {
                console.error('Failed to fetch history:', data.message);
            }
        })
        .catch(error => console.error('Error fetching history:', error));
}
// Logic is history model End

// Shared toggle for detail rows so both arrow clicks and row clicks work
function toggleDetailRow(row) {
    if (!row || row.classList.contains('details-row')) return;
    const detailRow = row.nextElementSibling;
    if (!detailRow || !detailRow.classList.contains('details-row')) return;

    const table = row.closest('table');

    // If opening this row, close any other expanded rows first
    const isHidden = (detailRow.style.display === 'none' || !detailRow.style.display);
    if (isHidden && table) {
        table.querySelectorAll('tr.expanded').forEach(expandedRow => {
            if (expandedRow === row) return;
            const dr = expandedRow.nextElementSibling;
            if (dr && dr.classList.contains('details-row')) {
                dr.style.display = 'none';
            }
            const expBtn = expandedRow.querySelector('.expand-row-btn');
            if (expBtn) {
                const dArrow = expBtn.querySelector('.down-arrow');
                const uArrow = expBtn.querySelector('.up-arrow');
                if (dArrow) dArrow.style.display = 'inline';
                if (uArrow) uArrow.style.display = 'none';
            }
            expandedRow.classList.remove('expanded');
        });
    }

    const btn = row.querySelector('.expand-row-btn');
    const downArrow = btn ? btn.querySelector('.down-arrow') : null;
    const upArrow = btn ? btn.querySelector('.up-arrow') : null;

    if (isHidden) {
        detailRow.style.display = 'table-row';
        if (downArrow) downArrow.style.display = 'none';
        if (upArrow) upArrow.style.display = 'inline';
        row.classList.add('expanded');
    } else {
        detailRow.style.display = 'none';
        if (downArrow) downArrow.style.display = 'inline';
        if (upArrow) upArrow.style.display = 'none';
        row.classList.remove('expanded');
    }
}

// Expand/Collapse button functionality (arrow click)
document.addEventListener('click', function (e) {
    if (e.target.closest('.expand-row-btn')) {
        e.preventDefault();
        e.stopPropagation();
        const btn = e.target.closest('.expand-row-btn');
        const row = btn.closest('tr');
        toggleDetailRow(row);
    }
});

// Also toggle when clicking anywhere on the data row (excluding interactive controls)
document.addEventListener('click', function (e) {
    // Ignore clicks on controls so their native behavior works
    if (e.target.closest('button, a, input, select, label, textarea, .expand-row-btn, .lead-number')) return;
    const row = e.target.closest('tr');
    if (!row || row.classList.contains('details-row')) return;
    // Prevent checkbox column from toggling when user just wants to select
    if (e.target.closest('.select-row')) return;
    toggleDetailRow(row);
});

// Handle responsive behavior on window resize
function handleResponsiveBehavior() {
    const table = document.getElementById('myTable');
    if (!table) return;

    // Clear inline display overrides and force-visible flags so CSS media queries control visibility
    table.querySelectorAll('th, td').forEach(cell => {
        cell.style.display = '';
        cell.classList.remove('force-visible');
    });

    // Keep details rows hidden on desktop unless expanded
    if (window.innerWidth >= 1025) {
        table.querySelectorAll('tbody tr.details-row').forEach(row => {
            if (!row.classList.contains('expanded')) {
                row.style.display = 'none';
            }
        });
    }
}

// Initialize responsive behavior after DOM is ready and table is loaded
document.addEventListener('DOMContentLoaded', function () {
    // Wait a bit for table to be rendered
    setTimeout(() => {
        handleResponsiveBehavior();
    }, 100);
});

// Also call it when table data is fetched
if (typeof window.addEventListener !== 'undefined') {
    window.addEventListener('load', function () {
        setTimeout(() => {
            handleResponsiveBehavior();
        }, 200);
    });
}

// Handle window resize with debouncing
let resizeTimer;
window.addEventListener('resize', function () {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(function () {
        handleResponsiveBehavior();
    }, 250);
});

