const addForm = document.getElementById("add-user-form");
const showAlert = document.getElementById("showAlert");
const uploadWrapper = document.getElementById('uploadWrapper');
// const fileInput = document.getElementById('document');
const filePreview = document.getElementById('filePreview');
const isMobileView = window.innerWidth <= 768;
const uploadProgress = document.getElementById('uploadProgress');
const progressFill = document.getElementById('progressFill');
const progressText = document.getElementById('progressText');
const errorMessage = document.getElementById('errorMessage');

// State management for filters
let expandedRows = [];
let expandedDetails = [];
let nestedSearchTerms = {};
let nestedPerPageValues = {};
let isFilterApplied = false;
let activeFilters = {};
let originalData = {}; // Store original row data for restoration
let nestedCurrentPages = {};
let mainCurrentPage = 1;
let mainPerPage = 10;
let mainTotalPages = 1;
let mainFilteredData = [];

// Global last formatted month (fallback to avoid ReferenceError across handlers)
var lastFormattedMonth = null;

// Refresh bookings UI: fetch the current page HTML, extract updated stats/table and JSON data,
// then replace the DOM fragments and reinitialize pagination/listeners so new booking shows up
async function refreshBookingsUI() {
    try {
        const resp = await fetch(window.location.href, { cache: 'no-store' });
        if (!resp.ok) {
            console.warn('refreshBookingsUI: server returned non-OK', resp.status);
            return Promise.reject(new Error('Failed to fetch updated page'));
        }
        const text = await resp.text();

        // Parse HTML to extract parts
        const parser = new DOMParser();
        const doc = parser.parseFromString(text, 'text/html');

        // Replace stats grid
        const newStats = doc.querySelector('.stats-grid');
        const oldStats = document.querySelector('.stats-grid');
        console.debug('refreshBookingsUI: found newStats=', !!newStats, 'oldStats=', !!oldStats);
        if (newStats && oldStats) oldStats.innerHTML = newStats.innerHTML;

        // Replace main table container
        const newTableContainer = doc.querySelector('.table-container');
        const oldTableContainer = document.querySelector('.table-container');
        console.debug('refreshBookingsUI: found newTableContainer=', !!newTableContainer, 'oldTableContainer=', !!oldTableContainer);
        if (newTableContainer && oldTableContainer) oldTableContainer.innerHTML = newTableContainer.innerHTML;

        // Replace pagination text
        const newPaginationText = doc.querySelector('.pagination-text');
        const oldPaginationText = document.querySelector('.pagination-text');
        console.debug('refreshBookingsUI: found newPaginationText=', !!newPaginationText, 'oldPaginationText=', !!oldPaginationText);
        if (newPaginationText && oldPaginationText) oldPaginationText.textContent = newPaginationText.textContent;

        // Extract groupedRows and monthlyData JSON embedded in inline script
        let groupedParsed = false, monthlyParsed = false;
        const groupedMatch = text.match(/const groupedRows = (\[|\{)[\s\S]*?;\s*/);
        const monthlyMatch = text.match(/const monthlyData = (\[|\{)[\s\S]*?;\s*/);

        if (groupedMatch) {
            try {
                const m = groupedMatch[0].replace(/const groupedRows =/, '').replace(/;\s*$/, '');
                window.groupedRows = JSON.parse(m);
                groupedParsed = true;
            } catch (e) {
                console.warn('Could not parse groupedRows from server response (regex)', e);
            }
        }
        if (monthlyMatch) {
            try {
                const m = monthlyMatch[0].replace(/const monthlyData =/, '').replace(/;\s*$/, '');
                window.monthlyData = JSON.parse(m);
                monthlyParsed = true;
            } catch (e) {
                console.warn('Could not parse monthlyData from server response (regex)', e);
            }
        }

        console.debug('refreshBookingsUI: groupedParsed=', groupedParsed, 'monthlyParsed=', monthlyParsed);

        // Fallback: scan script tags for the variable assignments and extract JSON
        if (!groupedParsed || !monthlyParsed) {
            const scripts = doc.querySelectorAll('script');
            scripts.forEach(s => {
                const t = s.textContent || '';
                if (!groupedParsed) {
                    const gm = t.match(/const groupedRows = (\[|\{)[\s\S]*?;\s*/);
                    if (gm) {
                        try {
                            const m = gm[0].replace(/const groupedRows =/, '').replace(/;\s*$/, '');
                            window.groupedRows = JSON.parse(m);
                            groupedParsed = true;
                        } catch (e) { }
                    }
                }
                if (!monthlyParsed) {
                    const mm = t.match(/const monthlyData = (\[|\{)[\s\S]*?;\s*/);
                    if (mm) {
                        try {
                            const m = mm[0].replace(/const monthlyData =/, '').replace(/;\s*$/, '');
                            window.monthlyData = JSON.parse(m);
                            monthlyParsed = true;
                        } catch (e) { }
                    }
                }
            });
        }

        // Reinitialize pagination, per-page and event listeners
        mainCurrentPage = 1;
        initializeMainPagination();
        handleMainPerPageChange();
        setupMainPaginationButtons();
        setupEventListeners();

        console.debug('refreshBookingsUI: window.monthlyData length=', window.monthlyData ? window.monthlyData.length : 0, 'window.groupedRows keys=', window.groupedRows ? Object.keys(window.groupedRows).length : 0);

        // Initialize nested pagination state for months
        if (window.monthlyData) {
            window.monthlyData.forEach(m => {
                nestedCurrentPages[m.month] = 1;
                nestedPerPageValues[m.month] = 5;
                updateNestedPagination(m.month);
            });
        }

        // Reapply remaining column visibility after DOM replacements
        if (typeof window.applyRemainingVisibility === 'function') {
            window.applyRemainingVisibility();
        }

        console.log('Bookings UI refreshed');
    } catch (err) {
        console.error('refreshBookingsUI failed', err);
    }
}
// expose for debugging and calling from other modules
window.refreshBookingsUI = refreshBookingsUI;

// ===== SIMPLIFIED HIERARCHY FOR BOOKINGS =====
// All bookings are shown by default based on user hierarchy
// No additional UI controls needed



// Simple Search Debug and Fix
// Add this script to debug and fix the search issue

console.log('=== SEARCH DEBUG SCRIPT LOADED ===');

// Initialize main table pagination
function initializeMainPagination() {
    // Derive entries from DOM to avoid relying on external globals
    const allRows = Array.from(document.querySelectorAll('#mainTableBody .table-row[onclick]'));
    const totalEntries = allRows.length;
    mainTotalPages = Math.ceil(totalEntries / mainPerPage) || 1;

    // Update pagination controls
    updateMainPaginationControls();

    // Apply pagination to the main table
    applyMainPagination();
}

// Update pagination controls
function updateMainPaginationControls() {
    const pagination = document.querySelector('.pagination-controls');
    if (!pagination) return;

    // Rebuild the pagination bar from scratch to avoid leftover buttons
    pagination.innerHTML = '';

    const createButton = (label, className, disabled, onClick) => {
        const btn = document.createElement('button');
        btn.className = className;
        btn.textContent = label;
        btn.disabled = disabled;
        if (onClick) btn.onclick = onClick;
        pagination.appendChild(btn);
        return btn;
    };

    createButton('Previous', 'btn btn-outline btn-sm', mainCurrentPage === 1, () => {
        if (mainCurrentPage > 1) goToMainPage(mainCurrentPage - 1);
    });

    if (mainTotalPages > 0) {
        const addPageBtn = (page) => {
            createButton(
                page,
                `btn btn-sm ${page === mainCurrentPage ? 'btn-primary' : 'btn-outline'}`,
                false,
                () => goToMainPage(page)
            );
        };

        const addEllipsis = () => {
            const ellipsis = document.createElement('span');
            ellipsis.className = 'pagination-ellipsis';
            ellipsis.textContent = '..';
            ellipsis.style.padding = '0 8px';
            ellipsis.style.color = '#666';
            ellipsis.style.display = 'inline-flex';
            ellipsis.style.alignItems = 'center';
            pagination.appendChild(ellipsis);
        };

        if (mainTotalPages <= 3) {
            for (let i = 1; i <= mainTotalPages; i++) addPageBtn(i);
        } else {
            addPageBtn(1);
            if (mainCurrentPage > 2) addEllipsis();
            if (mainCurrentPage !== 1 && mainCurrentPage !== mainTotalPages) addPageBtn(mainCurrentPage);
            if (mainCurrentPage < mainTotalPages - 1) addEllipsis();
            addPageBtn(mainTotalPages);
        }
    }

    createButton('Next', 'btn btn-outline btn-sm', mainCurrentPage === mainTotalPages || mainTotalPages === 0, () => {
        if (mainCurrentPage < mainTotalPages) goToMainPage(mainCurrentPage + 1);
    });
}



// Form submission handling
// Form submission handling
document.addEventListener('DOMContentLoaded', function () {
    const addForm = document.getElementById("add-booking-form");
    const showAlert = document.getElementById("showAlert");

    if (addForm) {
        addForm.addEventListener("submit", async (e) => {
            e.preventDefault();

            const formData = new FormData(addForm);
            formData.append("add", 1);

            // Format the booking month before sending and store to global lastFormattedMonth
            const dateInput = formData.get('bdate');
            if (dateInput) {
                const date = new Date(dateInput);
                // assign to the outer-scoped var so other code can reference it
                lastFormattedMonth = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
                formData.set('bmonth', lastFormattedMonth);
            }

            // Ensure calculated fields are populated before validation
            if (typeof addCalculate === 'function') {
                addCalculate();
            }

            if (addForm.checkValidity() === false) {
                e.preventDefault();
                e.stopPropagation();
                addForm.classList.add("was-validated");
                return false;
            } else {
                const submitBtn = document.getElementById("saveBtn");
                const originalText = submitBtn.textContent;

                submitBtn.textContent = "Please Wait...";
                submitBtn.disabled = true;

                try {
                    const data = await fetch("action.php", {
                        method: "POST",
                        body: formData,
                    });
                    const response = await data.text();

                    // Debug: Log the actual response to console
                    console.log('Server response:', response);

                    // More specific duplicate detection - check for exact duplicate messages
                    const responseText = response.toLowerCase().trim();
                    const isDuplicate = responseText.includes('duplicate booking') ||
                        responseText.includes('booking already exists') ||
                        responseText.includes('duplicate entry') ||
                        responseText.includes('this booking already exists') ||
                        (responseText.includes('duplicate') && responseText.includes('booking'));

                    // Check for success indicators - be more specific
                    const isSuccess = responseText.includes('booking added successfully') ||
                        responseText.includes('booking inserted successfully') ||
                        responseText.includes('booking saved successfully') ||
                        responseText.includes('successfully added') ||
                        responseText.includes('inserted successfully') ||
                        responseText.includes('booking created successfully') ||
                        (responseText.includes('success') && responseText.includes('booking'));

                    // Show response message in the old alert div
                    showAlert.innerHTML = response;
                    showAlert.style.display = "block";
                    setTimeout(() => { showAlert.style.display = "none"; }, 3000);

                    if (isDuplicate) {
                        // Show duplicate notification with red background and cross icon
                        showEnhancedNotification("Duplicate booking data! This booking already exists.", "duplicate");
                        console.log('Detected as duplicate');
                        addForm.reset();
                        addForm.classList.remove("was-validated");
                        closeAddBookingModal();
                    } else if (isSuccess) {
                        // Reset form only on success
                        addForm.reset();
                        addForm.classList.remove("was-validated");
                        closeAddBookingModal();
                        showNotification("Booking Added Successfully!", "success");
                        console.log('Detected as success');

                        // Update the table without refreshing by calling the centralized refresher
                        try {
                            // Ask the server-rendered fragments to be re-fetched and DOM updated.
                            await refreshBookingsUI();
                        } catch (e) {
                            console.warn('refreshBookingsUI failed after add, falling back to local update', e);

                            // Fallback: attempt minimal local update (increment month count if present)
                            const monthRow = document.querySelector(`tr[onclick="toggleRow('${lastFormattedMonth}')"]`);
                            if (monthRow) {
                                const monthCell = monthRow.querySelector('.month-cell');
                                if (monthCell) {
                                    const countMatch = monthCell.textContent.match(/\((\d+)\)/);
                                    if (countMatch) {
                                        const newCount = parseInt(countMatch[1]) + 1;
                                        monthCell.textContent = monthCell.textContent.replace(/\(\d+\)/, `(${newCount})`);
                                    }
                                }
                            } else {
                                console.log('New month row needed (fallback):', lastFormattedMonth);
                            }
                        }
                    } else if (responseText.includes('error') || responseText.includes('failed') || responseText.includes('invalid') || responseText.includes('SQLSTATE')) {
                        // Extract plain text from HTML response to show exact error 
                        let cleanError = responseText.replace(/<[^>]*>?/gm, '').trim();
                        // Show error notification with actual error message from server
                        showEnhancedNotification(cleanError || "Error: Unable to add booking. Please check details.", "error");
                        console.log('Detected as error:', responseText);
                        // Don't close modal or reset form on error - let user see and fix the issue
                    } else {
                        // If no clear indication, assume success (fallback for cases where response doesn't contain expected keywords)
                        addForm.reset();
                        addForm.classList.remove("was-validated");
                        closeAddBookingModal();
                        showNotification("Booking processed successfully!", "success");
                        console.log('Fallback to success - response:', responseText);

                        // Update the table
                        try {
                            await refreshBookingsUI();
                        } catch (e) {
                            console.warn('refreshBookingsUI failed after add, falling back to local update', e);
                        }
                    }

                } catch (error) {
                    console.error('Error submitting form:', error);
                    showEnhancedNotification("Network error occurred. Please try again.", "error");
                } finally {
                    submitBtn.textContent = originalText;
                    submitBtn.disabled = false;

                }
            }
        });
    }
});


// Function to ensure unit number maintains the prefix
function prefixCheck(input) {
    // Get the expected prefix from the initial value or a data attribute
    const unitInput = document.getElementById('unitNo');
    if (!unitInput) return;

    // Extract prefix from the initial value (e.g., "Ma-" from "Ma-")
    const currentValue = input.value;
    const initialValue = unitInput.defaultValue || '';

    // If the prefix is removed, restore it
    if (initialValue && !currentValue.startsWith(initialValue)) {
        // Check if user is typing after the prefix
        if (currentValue.length < initialValue.length) {
            input.value = initialValue;
        } else if (!currentValue.includes(initialValue)) {
            // User might have deleted the prefix, restore it
            input.value = initialValue + currentValue.replace(initialValue, '');
        }
    }
}

// Add this function to handle unit prefix
// Initialize unit field with prefix
document.addEventListener('DOMContentLoaded', function () {
    const unitInput = document.getElementById('unitNo');
    if (unitInput && !unitInput.value) {
        unitInput.value = "<?php echo $unit_prefix ?? 'Un-'; ?>";
    }

    // Add event listeners for automatic calculation
    const agreementInput = document.getElementById('agreementValue');
    const commissionInput = document.getElementById('commissionPct');
    const cashbackInput = document.getElementById('cashbackPct');

    if (agreementInput) {
        agreementInput.addEventListener('input', function () {
            if (typeof addCalculate === 'function') addCalculate();
        });
    }

    if (commissionInput) {
        commissionInput.addEventListener('input', function () {
            if (typeof addCalculate === 'function') addCalculate();
        });
    }

    if (cashbackInput) {
        cashbackInput.addEventListener('input', function () {
            if (typeof addCalculate === 'function') addCalculate();
        });
    }
});

// Revenue and Cashback Calculation Functions
// Extracted from bookings.php for reusability

/**
 * Calculate revenue and actual revenue based on agreement, commission and cashback
 * Uses form elements with names: cagreement, ccashback, crevenue, cccashback, ccrevenue
 */
function addCalculate() {
    // Get agreement value
    let agreementValue = 0;
    if (!isNaN(document.forms.myform.cagreement.value) && document.forms.myform.cagreement.value !== "") {
        agreementValue = parseFloat(document.forms.myform.cagreement.value);
    }

    // Get commission percentage
    let commissionPct = 0;
    if (!isNaN(document.forms.myform.ccashback.value) && document.forms.myform.ccashback.value !== "") {
        commissionPct = parseFloat(document.forms.myform.ccashback.value);
    }

    // Calculate revenue: agreement * (commission%)
    const revenue = parseInt(agreementValue * (commissionPct / 100));
    document.forms.myform.crevenue.value = revenue;

    // Get cashback percentage
    let cashbackPct = 0;
    if (!isNaN(document.forms.myform.cccashback.value) && document.forms.myform.cccashback.value !== "") {
        cashbackPct = parseFloat(document.forms.myform.cccashback.value);
    }

    // Get revenue value (fallback to 0 if invalid)
    let revenueValue = 0;
    if (!isNaN(document.forms.myform.crevenue.value) && document.forms.myform.crevenue.value !== "") {
        revenueValue = parseInt(document.forms.myform.crevenue.value);
    }

    // Calculate actual revenue: revenue - (agreement * cashback%)
    const actualRevenue = parseInt(revenueValue - (agreementValue * (cashbackPct / 100)));
    document.forms.myform.ccrevenue.value = actualRevenue;
}

/**
 * Update calculation using getElementById instead of form elements
 * Uses elements with IDs: cagreement, ccashback, crevenue, cccashback, ccrevenue
 */
function updateCalculate() {
    // Get agreement value
    let agreementValue = 0;
    if (!isNaN(document.getElementById("cagreement").value) && document.getElementById("cagreement").value !== "") {
        agreementValue = parseFloat(document.getElementById("cagreement").value);
    }

    // Get commission percentage
    let commissionPct = 0;
    if (!isNaN(document.getElementById("ccashback").value) && document.getElementById("ccashback").value !== "") {
        commissionPct = parseFloat(document.getElementById("ccashback").value);
    }

    // Calculate revenue: agreement * (commission%)
    const revenue = agreementValue * (commissionPct / 100);
    document.getElementById("crevenue").value = revenue;

    // Get cashback percentage
    let cashbackPct = 0;
    if (!isNaN(document.getElementById("cccashback").value) && document.getElementById("cccashback").value !== "") {
        cashbackPct = parseFloat(document.getElementById("cccashback").value);
    }

    // Get revenue value (fallback to 0 if invalid)
    let revenueValue = 0;
    if (!isNaN(document.getElementById("crevenue").value) && document.getElementById("crevenue").value !== "") {
        revenueValue = parseInt(document.getElementById("crevenue").value);
    }

    // Calculate actual revenue: revenue - (agreement * cashback%)
    const actualRevenue = revenueValue - (agreementValue * (cashbackPct / 100));
    document.getElementById("ccrevenue").value = actualRevenue;
}

/**
 * Calculate cashback revenue with tiered percentage reduction
 * Uses elements: input[name="cagreement"], input[name="cccashback"], input[name="user_agreement"]
 */
function calculateCashbackRevenue() {
    const agreementInput = document.querySelector('input[name="cagreement"]');
    const cashbackInput = document.querySelector('input[name="cccashback"]');
    const revenueInput = document.querySelector('input[name="user_agreement"]');

    if (!agreementInput || !cashbackInput || !revenueInput) {
        console.warn('Required input elements not found for calculateCashbackRevenue');
        return;
    }

    let agreementValue = parseFloat(agreementInput.value);
    let cashback = parseFloat(cashbackInput.value);

    if (isNaN(agreementValue) || isNaN(cashback)) {
        revenueInput.value = '';
        return;
    }

    let percentageToReduce = 0;

    // Tiered cashback reduction system
    if (cashback >= 0.1 && cashback < 0.5) {
        percentageToReduce = 25;
    } else if (cashback >= 0.5 && cashback < 1) {
        percentageToReduce = 50;
    } else if (cashback >= 1 && cashback <= 1.5) {
        percentageToReduce = 75;
    } else if (cashback > 1.5) {
        percentageToReduce = 100;
    }

    let reducedValue = agreementValue * (percentageToReduce / 100);
    let actualRevenue = agreementValue - reducedValue;

    // Set the calculated value (rounded to integer)
    revenueInput.value = Math.round(actualRevenue);
}

/**
 * Generic calculation function that works with flexible element selectors
 * Can be used for various form configurations
 */
function calculateRevenue(selectors = {}) {
    const defaultSelectors = {
        agreement: 'input[name="cagreement"], #cagreement, #agreementValue',
        commission: 'input[name="ccashback"], #commissionPct',
        cashback: 'input[name="cccashback"], #cashbackPct',
        revenue: 'input[name="crevenue"], #crevenue, #revenueAmount',
        actualRevenue: 'input[name="ccrevenue"], #ccrevenue, #actualAmount'
    };

    // Merge with provided selectors
    const config = { ...defaultSelectors, ...selectors };

    // Get elements
    const agreementEl = document.querySelector(config.agreement);
    const commissionEl = document.querySelector(config.commission);
    const cashbackEl = document.querySelector(config.cashback);
    const revenueEl = document.querySelector(config.revenue);
    const actualEl = document.querySelector(config.actualRevenue);

    if (!agreementEl || !commissionEl) {
        console.warn('Required elements not found for calculation');
        return;
    }

    // Get values
    const agreement = parseFloat(agreementEl.value) || 0;
    const commission = parseFloat(commissionEl.value) || 0;
    const cashback = parseFloat(cashbackEl?.value) || 0;

    // Calculate revenue = agreement * (commission%)
    const revenue = agreement * (commission / 100);

    // Calculate cashback amount based on revenue
    const cashbackAmount = revenue * (cashback / 100);

    // Calculate actual revenue = revenue - cashback amount
    const actualRevenue = revenue - cashbackAmount;

    // Update form fields
    if (revenueEl) {
        revenueEl.value = Number.isFinite(revenue) ? revenue.toFixed(2) : '0.00';
    }

    if (actualEl) {
        actualEl.value = Number.isFinite(actualRevenue) ? actualRevenue.toFixed(2) : '0.00';
    }

    return {
        agreement,
        commission,
        cashback,
        revenue,
        actualRevenue
    };
}

// Add option functionality
function addOption(type) {
    document.getElementById('optionType').value = type;

    // Set modal title and label based on type
    const titles = {
        'builder': 'Add New Builder',
        'project': 'Add New Project',
        'ptype': 'Add New Project Type'
    };

    const labels = {
        'builder': 'Builder Name',
        'project': 'Project Name',
        'ptype': 'Project Type'
    };

    document.getElementById('addOptionTitle').textContent = titles[type] || 'Add New Option';
    document.getElementById('addOptionLabel').textContent = labels[type] || 'Option Value';
    document.getElementById('newOptionValue').value = '';

    openAddOptionModal();
}

function openAddOptionModal() {
    document.getElementById('addOptionModalOverlay').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeAddOptionModal() {
    document.getElementById('addOptionModalOverlay').style.display = 'none';
    document.body.style.overflow = '';
}

function submitOption() {
    const type = document.getElementById('optionType').value;
    const value = document.getElementById('newOptionValue').value.trim();

    if (!value) {
        alert('Please enter a value');
        return;
    }

    // Add to the appropriate datalist
    const datalistId = type + 'List';
    const datalist = document.getElementById(datalistId);

    if (datalist) {
        const option = document.createElement('option');
        option.value = value;
        datalist.appendChild(option);
    }

    // Set the value in the corresponding input field
    const inputId = type === 'ptype' ? 'projectType' : type + 'Name';
    const inputEl = document.getElementById(inputId);
    if (inputEl) {
        const currentValue = inputEl.value.trim();
        if (currentValue) {
            inputEl.value = currentValue + ', ' + value;
        } else {
            inputEl.value = value;
        }
    }

    closeAddOptionModal();
}

function submitContact() {
    const fieldName = document.getElementById('contactField').value;
    const value = document.getElementById('newContactValue').value.trim();

    if (!value) {
        alert('Please enter a value');
        return;
    }

    // Validate based on field type
    if (fieldName === 'cemail') {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(value)) {
            alert('Please enter a valid email address');
            return;
        }
    } else if (fieldName === 'cnumber') {
        const phoneRegex = /^[0-9]{10}$/;
        if (!phoneRegex.test(value.replace(/\D/g, ''))) {
            alert('Please enter a valid 10-digit phone number');
            return;
        }
    }

    // Get the target input field
    const inputEl = document.querySelector('[name="' + fieldName + '"]');
    if (inputEl) {
        const currentValue = inputEl.value.trim();
        if (currentValue) {
            inputEl.value = currentValue + ', ' + value;
        } else {
            inputEl.value = value;
        }
    }

    closeAddContactModal();
}

// Update pagination info text
function updateMainPaginationInfo() {
    const allRows = Array.from(document.querySelectorAll('#mainTableBody .table-row[onclick]'));
    const searchTerm = window.currentSearchTerm || '';

    // Calculate visible entries based on current search/filter state
    let visibleRows = allRows;

    if (searchTerm) {
        // If there's a search term, count only rows that match the search
        visibleRows = allRows.filter(row => {
            return row.dataset.searchMatch === 'true';
        });
    } else if (isFilterApplied) {
        // If no search term but filters are applied, use the filter logic
        visibleRows = allRows.filter(row => {
            const onclickAttr = row.getAttribute('onclick');
            if (!onclickAttr) return false;

            const match = onclickAttr.match(/'([^']+)'/);
            if (!match) return false;

            const month = match[1];
            return checkMonthHasMatchingData(month);
        });
    }

    const totalEntries = visibleRows.length;
    let startEntry = 0;
    let endEntry = 0;
    if (totalEntries > 0) {
        startEntry = (mainCurrentPage - 1) * mainPerPage + 1;
        endEntry = Math.min(mainCurrentPage * mainPerPage, totalEntries);
    }
    const infoText = `Showing ${startEntry} to ${endEntry} of ${totalEntries} entries`;
    const infoTargets = document.querySelectorAll('.pagination-text, .pagination-text-top');
    infoTargets.forEach(el => {
        el.textContent = infoText;
    });
}

// Go to specific page
function goToMainPage(page) {
    mainCurrentPage = page;
    applyMainPagination();
    updateMainPaginationControls();
}

document.addEventListener('DOMContentLoaded', function () {
    console.log('Initializing pagination system...');

    // Initialize main pagination
    initializeMainPagination();

    // Setup per page change handlers
    handleMainPerPageChange();

    // Setup pagination buttons
    setupMainPaginationButtons();

    // Sync both desktop and mobile pagination selects
    const allPerPageSelects = document.querySelectorAll('.pagination-select');
    allPerPageSelects.forEach(select => {
        select.addEventListener('change', function (event) {
            // Sync all pagination selects to the same value
            const newValue = event.target.value;
            allPerPageSelects.forEach(otherSelect => {
                if (otherSelect !== event.target) {
                    otherSelect.value = newValue;
                }
            });

            // Handle the pagination change
            handlePerPageChangeEvent(event);
        });
    });

    console.log('Pagination system initialized');
});


// Handle per page change
function handleMainPerPageChange() {
    const perPageSelect = document.querySelector('.pagination-select');
    if (perPageSelect) {
        // Remove existing listener to prevent duplicates
        perPageSelect.removeEventListener('change', handlePerPageChangeEvent);
        perPageSelect.addEventListener('change', handlePerPageChangeEvent);
        console.log('Per page change listener attached to:', perPageSelect);

        // Also handle mobile pagination select if it exists
        const mobilePerPageSelect = document.querySelector('.mobile-pagination-info .pagination-select');
        if (mobilePerPageSelect && mobilePerPageSelect !== perPageSelect) {
            mobilePerPageSelect.removeEventListener('change', handlePerPageChangeEvent);
            mobilePerPageSelect.addEventListener('change', handlePerPageChangeEvent);
            console.log('Mobile per page change listener attached');
        }
    } else {
        console.error('Pagination select element not found');
    }
}


function handlePerPageChangeEvent(event) {
    const newPerPage = parseInt(event.target.value);
    console.log('Per page changed to:', newPerPage);

    mainPerPage = newPerPage;
    mainCurrentPage = 1; // Reset to first page when changing per page

    // Get all main table rows
    const allRows = Array.from(document.querySelectorAll('#mainTableBody .table-row[onclick]'));
    const searchTerm = window.currentSearchTerm || '';

    let visibleRows = allRows;

    // Apply current filters to determine which rows should be counted
    if (searchTerm) {
        // If there's a search term, only count rows that match the search
        visibleRows = allRows.filter(row => row.dataset.searchMatch === 'true');
    } else if (isFilterApplied) {
        // If filters are applied but no search, count rows that pass the filters
        visibleRows = allRows.filter(row => {
            const onclickAttr = row.getAttribute('onclick');
            if (!onclickAttr) return false;

            const match = onclickAttr.match(/'([^']+)'/);
            if (!match) return false;

            const month = match[1];
            return checkMonthHasMatchingData(month);
        });
    }
    // If no search and no filters, all rows are visible

    // Recalculate total pages based on visible rows
    mainTotalPages = Math.ceil(visibleRows.length / mainPerPage) || 1;

    console.log(`Visible rows: ${visibleRows.length}, Per page: ${mainPerPage}, Total pages: ${mainTotalPages}`);

    // Apply pagination and update controls
    applyMainPagination();
    updateMainPaginationControls();
}




// Handle previous/next buttons
function setupMainPaginationButtons() {
    const prevBtn = document.querySelector('.pagination-controls button:first-child');
    const nextBtn = document.querySelector('.pagination-controls button:last-child');

    if (prevBtn) {
        prevBtn.onclick = () => {
            if (mainCurrentPage > 1) {
                goToMainPage(mainCurrentPage - 1);
            }
        };
    }

    if (nextBtn) {
        nextBtn.onclick = () => {
            if (mainCurrentPage < mainTotalPages) {
                goToMainPage(mainCurrentPage + 1);
            }
        };
    }
}

// Safe applyMainPagination function - only changes page when necessary
function applyMainPagination() {
    const allRows = Array.from(document.querySelectorAll('#mainTableBody .table-row[onclick]'));
    const searchTerm = window.currentSearchTerm || '';

    console.log(`Applying pagination - Total rows: ${allRows.length}, Search term: "${searchTerm}", Filters applied: ${isFilterApplied}`);

    // First filter by search term if it exists
    let visibleBeforePaginate = allRows;

    if (searchTerm) {
        visibleBeforePaginate = allRows.filter(row => {
            return row.dataset.searchMatch === 'true';
        });
    } else if (isFilterApplied) {
        // If no search term but filters are applied, use the filter logic
        visibleBeforePaginate = allRows.filter(row => {
            const onclickAttr = row.getAttribute('onclick');
            if (!onclickAttr) return false;

            const match = onclickAttr.match(/'([^']+)'/);
            if (!match) return false;

            const month = match[1];
            return checkMonthHasMatchingData(month);
        });
    }

    console.log(`Rows before pagination: ${visibleBeforePaginate.length}`);

    // Compute pages based on currently visible rows after filters/search
    const totalEntries = visibleBeforePaginate.length;
    mainTotalPages = Math.ceil(totalEntries / mainPerPage) || 1;

    // Ensure current page is valid - only change if necessary
    if (mainCurrentPage > mainTotalPages) {
        mainCurrentPage = mainTotalPages;
    }
    if (mainCurrentPage < 1) {
        mainCurrentPage = 1;
    }

    const startIndex = (mainCurrentPage - 1) * mainPerPage;
    const endIndex = startIndex + mainPerPage;

    console.log(`Page ${mainCurrentPage} of ${mainTotalPages}: showing items ${startIndex + 1} to ${Math.min(endIndex, totalEntries)}`);

    // Hide all rows first
    allRows.forEach(row => {
        row.style.display = 'none';

        // Also hide nested sections for hidden months
        const onclickAttr = row.getAttribute('onclick');
        if (onclickAttr) {
            const match = onclickAttr.match(/'([^']+)'/);
            if (match) {
                const month = match[1];
                const nestedSection = document.getElementById(`nested-${month}`);
                if (nestedSection) {
                    nestedSection.style.display = 'none';
                }
            }
        }
    });

    // Show only the rows for the current page
    visibleBeforePaginate.forEach((row, idx) => {
        if (idx >= startIndex && idx < endIndex) {
            row.style.display = '';

            // If this row is expanded, show its nested section too
            const onclickAttr = row.getAttribute('onclick');
            if (onclickAttr) {
                const match = onclickAttr.match(/'([^']+)'/);
                if (match) {
                    const month = match[1];
                    if (expandedRows.includes(month)) {
                        const nestedSection = document.getElementById(`nested-${month}`);
                        if (nestedSection) {
                            nestedSection.style.display = 'table-row';
                            // Apply search to nested rows if needed
                            if (searchTerm || isFilterApplied) {
                                nestedSearchTerms[month] = searchTerm;
                                updateNestedPagination(month);
                            }
                        }
                    }
                }
            }
        }
    });

    updateMainPaginationInfo();

    // Show no results message if needed
    if (totalEntries === 0) {
        showNoResultsMessage();
    }
}




// Wait for DOM to be ready
document.addEventListener('DOMContentLoaded', function () {
    window.currentSearchTerm = '';
    console.log('DOM Ready - Starting search debug...');

    // Step 1: Check if search input exists
    const searchInput = document.getElementById('mainSearch');
    console.log('Search input found:', searchInput);

    if (!searchInput) {
        console.error('❌ Search input with ID "mainSearch" not found!');

        // Try to find it by other means
        const searchByClass = document.querySelector('.search-input');
        const searchByName = document.querySelector('input[placeholder*="Search"]');

        console.log('Search by class (.search-input):', searchByClass);
        console.log('Search by placeholder:', searchByName);
        return;
    }

    console.log('✅ Search input found successfully');

    // Step 2: Check table rows
    const tableRows = document.querySelectorAll('#mainTableBody .table-row');
    console.log('Table rows found:', tableRows.length);

    if (tableRows.length === 0) {
        console.error('❌ No table rows found!');
        // Try alternative selectors
        const altRows = document.querySelectorAll('#mainTableBody tr');
        console.log('Alternative rows found:', altRows.length);
    }

    // (removed duplicate simpleSearch/handleNoResults block)

    // Step 4: Simple search function (robust: searches month/main row and nested rows)
    function simpleSearch(searchValue) {
        const term = String(searchValue || '').toLowerCase().trim();
        window.currentSearchTerm = term;

        const rows = Array.from(document.querySelectorAll('#mainTableBody .table-row[onclick]'));
        let anyMatch = false;

        rows.forEach(row => {
            const onclick = row.getAttribute('onclick') || '';
            const monthMatch = onclick.match(/toggleRow\('([^']+)'\)/) || onclick.match(/'([^']+)'/);
            const month = monthMatch ? monthMatch[1] : null;

            const monthCell = row.querySelector('.month-cell');
            const monthText = monthCell ? monthCell.textContent.toLowerCase() : '';

            // Check nested data via groupedRows if available (more reliable)
            let nestedMatches = false;
            if (month && window.groupedRows && window.groupedRows[month]) {
                const list = window.groupedRows[month];
                const t = term;
                nestedMatches = list.some(r => {
                    const hay = Object.keys(r).map(k => (r[k] == null ? '' : String(r[k]))).join(' ').toLowerCase();
                    return t === '' || hay.includes(t);
                });
            } else if (month) {
                const nestedEl = document.getElementById(`nested-${month}`);
                nestedMatches = nestedEl ? nestedEl.textContent.toLowerCase().includes(term) : false;
            }

            // Check the main row text as well
            const mainRowText = row.textContent.toLowerCase();
            const mainMatches = term === ''
                ? true
                : (mainRowText.includes(term) || monthText.includes(term) || nestedMatches);

            // But if no nested row matched, the parent should be hidden even if month text matched
            if (term !== '' && !nestedMatches && !mainRowText.includes(term)) {
                row.dataset.searchMatch = 'false';
                row.style.display = 'none';
                const nestedSection = document.getElementById(`nested-${month}`);
                if (nestedSection) nestedSection.style.display = 'none';
                return; // skip further checks
            }

            // Also ensure it passes any active filters
            const passesFilters = !isFilterApplied || (month ? checkMonthHasMatchingData(month) : true);

            const isMatch = mainMatches && passesFilters;

            row.dataset.searchMatch = isMatch ? 'true' : 'false';

            if (isMatch) {
                row.style.display = '';
                anyMatch = true;
                // Record the main search term for this month so nested pagination/search
                // will filter nested rows when the month is expanded. Always set it so
                // nested filtering is consistent whether the month is expanded now or later.
                if (month) {
                    nestedSearchTerms[month] = term;
                    try { updateNestedPagination(month); } catch (err) { /* ignore */ }
                    // If month is currently expanded, ensure the nested section is visible
                    const nestedSection = document.getElementById(`nested-${month}`);
                    if (nestedSection && expandedRows.includes(month)) {
                        nestedSection.style.display = 'table-row';
                    }
                }
            } else {
                row.style.display = 'none';
                if (month) {
                    const nestedSection = document.getElementById(`nested-${month}`);
                    if (nestedSection) nestedSection.style.display = 'none';
                }
            }
        });

        // Reset to first page and re-run pagination so visible rows are paginated
        mainCurrentPage = 1;
        initializeMainPagination();
        updateMainPaginationControls();
        applyMainPagination();

        // Update top stats
        updateTopStatsFromVisibleRows();

        // Show no-results if nothing matched
        if (!anyMatch && term !== '') {
            showNoResultsMessage();
        } else {
            // remove any existing no-results rows
            document.querySelectorAll('.no-results-row').forEach(el => el.remove());
        }

        // If search is empty, clear the nestedSearchTerms set by the main search so
        // nested tables return to their independent search/pagination state
        if (term === '') {
            Object.keys(nestedSearchTerms).forEach(m => {
                delete nestedSearchTerms[m];
                try { updateNestedPagination(m); } catch (err) { /* ignore */ }
            });
        }
    }

    // Update top stat cards dynamically when search filters rows
    // Update top stat cards dynamically when search filters rows
    function updateTopStatsFromVisibleRows() {
        const rows = Array.from(document.querySelectorAll('#mainTableBody .table-row'));
        let bookings = 0, agreement = 0, deduct_agreement = 0, revenue = 0, invoice = 0, cancelled_amt = 0, cancelled_count = 0;
        
        const formatCompact = (num) => {
            if (num >= 1000000000) return '₹' + (num / 1000000000).toFixed(2) + 'B';
            if (num >= 1000000) return '₹' + (num / 1000000).toFixed(2) + 'M';
            if (num >= 1000) return '₹' + (num / 1000).toFixed(2) + 'K';
            return '₹' + Math.round(num).toLocaleString('en-IN');
        };
        
        const formatFull = (num) => '₹' + Math.round(num).toLocaleString('en-IN');
        
        // The current global search term
        const term = window.currentSearchTerm || '';
        
        // Only consider months that matched the main search
        const matchingRows = rows.filter(row => row.dataset.searchMatch !== 'false');
        
        matchingRows.forEach(row => {
            const month = row.dataset.month;
            
            // If we have detailed booking data, filter it exactly like simpleSearch does
            if (month && window.groupedRows && window.groupedRows[month]) {
                const list = window.groupedRows[month];
                
                let monthBookings = 0;
                let monthAgreement = 0;
                let monthDeduct = 0;
                let monthRevenue = 0;
                let monthInvoice = 0;
                let monthCancelledAmt = 0;
                let monthCancelledCount = 0;

                list.forEach(r => {
                    // Check if this specific booking matches the search
                    const hay = Object.keys(r).map(k => (r[k] == null ? '' : String(r[k]))).join(' ').toLowerCase();
                    const isMatch = (term === '' || hay.includes(term));
                    
                    if (isMatch) {
                        const status = String(r.astatus || '').toLowerCase().trim();
                        if (status === 'canceled' || status === 'cancled') {
                            // Only count this towards the cancelled bucket
                            monthCancelledAmt += parseFloat(r.agreement_value || 0);
                            monthCancelledCount++;
                        } else {
                            // Count this booking for active overalls
                            monthBookings++;
                            monthAgreement += parseFloat(r.agreement_value || 0);
                            monthDeduct += parseFloat(r.deduct_agreement || 0);
                            monthRevenue += parseFloat(r.crevenue || 0);
                        }
                    }
                });

                // Update month row text to show filtered details (Bookings count, Agreement, Revenue)
                const monthCellSpan = row.querySelector('.month-cell');
                if (monthCellSpan) {
                    // preserve the icon HTML, overwrite text
                    const iconHtml = monthCellSpan.querySelector('.icon') ? monthCellSpan.querySelector('.icon').outerHTML : '';
                    monthCellSpan.innerHTML = iconHtml + ' ' + month + ' (' + monthBookings + ')';
                }

                const agreementCell = row.querySelector('.revenue-cell');
                if (agreementCell) {
                    const iconHtml = '<i class="fas fa-wallet"></i> ';
                    agreementCell.innerHTML = iconHtml + formatCompact(monthAgreement);
                }

                const actualRevCell = row.querySelector('.expenses-cell');
                if (actualRevCell) {
                    const iconHtml = '<i class="fas fa-file-invoice-dollar"></i> ';
                    actualRevCell.innerHTML = iconHtml + formatCompact(monthRevenue);
                }

                // Add to overall totals
                bookings += monthBookings;
                agreement += monthAgreement;
                deduct_agreement += monthDeduct;
                revenue += monthRevenue;
                invoice += monthInvoice;
                cancelled_amt += monthCancelledAmt;
                cancelled_count += monthCancelledCount;
            } else {
                // Fallback if structured data missing: use dataset totals for this month
                bookings += parseInt(row.dataset.bookings) || 0;
                agreement += parseFloat(row.dataset.agreement) || 0;
                deduct_agreement += parseFloat(row.dataset.deductAgreement) || 0;
                revenue += parseFloat(row.dataset.revenue) || 0;
                invoice += parseFloat(row.dataset.invoiceRaise) || 0;
                cancelled_amt += parseFloat(row.dataset.cancelledAgreement) || 0;
                cancelled_count += parseInt(row.dataset.cancelledCount) || 0;
            }
        });
        
        const final_remaining = Math.max(0, revenue - invoice);
        
        if (document.getElementById('statOverallBookings')) document.getElementById('statOverallBookings').textContent = bookings;
        if (document.getElementById('cardOverallBookings')) document.getElementById('cardOverallBookings').setAttribute('data-tooltip', bookings + ' Bookings');

        if (document.getElementById('statOverallAgreement')) document.getElementById('statOverallAgreement').textContent = formatCompact(agreement);
        if (document.getElementById('cardOverallAgreement')) document.getElementById('cardOverallAgreement').setAttribute('data-tooltip', formatFull(agreement));

        if (document.getElementById('statActualAgreement')) document.getElementById('statActualAgreement').textContent = formatCompact(deduct_agreement);
        if (document.getElementById('cardActualAgreement')) document.getElementById('cardActualAgreement').setAttribute('data-tooltip', formatFull(deduct_agreement));

        if (document.getElementById('statOverallRevenue')) document.getElementById('statOverallRevenue').textContent = formatCompact(revenue);
        if (document.getElementById('cardOverallRevenue')) document.getElementById('cardOverallRevenue').setAttribute('data-tooltip', formatFull(revenue));

        if (document.getElementById('statCancelledAgreement')) {
            document.getElementById('statCancelledAgreement').innerHTML = `${formatCompact(cancelled_amt)}\n                <span style="font-size: 13px; opacity: 0.8;">(${cancelled_count})</span>`;
        }
        if (document.getElementById('cardCancelledAgreement')) document.getElementById('cardCancelledAgreement').setAttribute('data-tooltip', `${formatFull(cancelled_amt)} (${cancelled_count})`);

        if (document.getElementById('statFinalRemaining')) document.getElementById('statFinalRemaining').textContent = formatCompact(final_remaining);
        if (document.getElementById('cardFinalRemaining')) document.getElementById('cardFinalRemaining').setAttribute('data-tooltip', formatFull(final_remaining));
    }

    // Debounce helper
    function debounce(fn, wait) {
        let t = null;
        return function (...args) {
            clearTimeout(t);
            t = setTimeout(() => fn.apply(this, args), wait);
        };
    }

    // Step 4: Attach event listener to main search input
    function attachSearchListener() {
        const input = document.getElementById('mainSearch') || document.querySelector('.search-input input, .search-input');
        if (!input) return;

        const realInput = input.tagName === 'INPUT' ? input : (input.querySelector ? input.querySelector('input') : input);

        const handler = debounce(function (e) {
            const val = e && e.target ? e.target.value : '';
            simpleSearch(val);
        }, 200);

        realInput.removeEventListener('input', handler);
        realInput.addEventListener('input', handler);

        // Support Enter key to perform immediate search
        realInput.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter') {
                ev.preventDefault();
                simpleSearch(realInput.value || '');
            }
        });
    }

    // Attach the listener
    attachSearchListener();

    // Step 5: Test the search function immediately
    console.log('🧪 Testing search function...');

    // Initial test disabled to avoid interfering with filtered view
    // simpleSearch('');

    // Make search function globally available for manual testing
    window.testSearch = simpleSearch;
    window.debugSearchInfo = function () {
        return {
            searchInput: searchInput,
            tableRows: document.querySelectorAll('#mainTableBody .table-row, #mainTableBody tr[onclick]'),
            mainTableBody: document.getElementById('mainTableBody')
        };
    };

    console.log('✅ Debug setup complete');
    console.log('💡 You can now test with: testSearch("your search term")');
    console.log('💡 Get debug info with: debugSearchInfo()');
});



// Alternative initialization block disabled to prevent duplicate DOMContentLoaded dispatch
// (Handled by native event only)

// Manual search function you can call from console
window.manualSearch = function (term) {
    const searchInput = document.getElementById('mainSearch') || document.querySelector('.search-input');
    if (searchInput) {
        searchInput.value = term;
        searchInput.dispatchEvent(new Event('input'));
    } else {
        console.error('Search input not found for manual search');
    }
};

console.log('=== SEARCH DEBUG SCRIPT READY ===');
console.log('💡 Try typing in the search box now');
console.log('💡 Or test manually with: manualSearch("2024-11")');

// Function to read URL parameters and apply date filters for bookings
function applyUrlDateFiltersToBookings() {
    const urlParams = new URLSearchParams(window.location.search);
    const startDate = urlParams.get('start_date');
    const endDate = urlParams.get('end_date');
    const filterUser = urlParams.get('filterUser');
    const managerView = urlParams.get('managerView');

    console.log('🔍 BOOKINGS URL PARAMETERS DEBUG:');
    console.log('- Current URL:', window.location.href);
    console.log('- startDate:', startDate);
    console.log('- endDate:', endDate);
    console.log('- filterUser:', filterUser);
    console.log('- managerView:', managerView);

    // Handle user filtering first if specified
    if (filterUser && managerView === 'true') {
        console.log('Applying user filter from URL to bookings:', filterUser);
        console.log('Manager view enabled:', managerView);
        // Show notification that user filtering is applied
        setTimeout(() => {
            if (typeof showNotification === 'function') {
                showNotification('📊 Viewing bookings for: ' + filterUser, 'info');
            } else {
                console.log('showNotification function not available');
                // Fallback notification
                alert('Viewing bookings for: ' + filterUser);
            }
        }, 500);
    } else {
        console.log('No user filter applied - filterUser:', filterUser, 'managerView:', managerView);
    }

    if (startDate || endDate) {
        console.log('Applying URL date filters to bookings:', { startDate, endDate });

        // Apply the date range filter to the bookings
        setTimeout(() => {
            applyBookingDateFilters(startDate, endDate);
        }, 1000);
    }

    // Show clear filter button if any filters are applied from dashboard
    if ((startDate || endDate) || (filterUser && managerView === 'true')) {
        showDashboardClearFilterButtonForBookings();
    }
}

// Function to apply date filters to booking data
function applyBookingDateFilters(startDate, endDate) {
    console.log('Applying date filters to booking table:', { startDate, endDate });

    if (!startDate && !endDate) return;

    const fromDate = startDate ? new Date(startDate) : null;
    const toDate = endDate ? new Date(endDate) : null;

    // Filter monthly data based on date range
    const monthlyRows = document.querySelectorAll('#mainTableBody .table-row[onclick]');
    let hasVisibleMonths = false;

    monthlyRows.forEach(row => {
        const onclick = row.getAttribute('onclick');
        const monthMatch = onclick ? onclick.match(/'([^']+)'/) : null;
        if (!monthMatch) return;

        const month = monthMatch[1];
        const monthData = window.groupedRows ? window.groupedRows[month] : null;

        if (monthData && monthData.length > 0) {
            // Check if any booking in this month falls within the date range
            const hasMatchingBookings = monthData.some(booking => {
                if (!booking.booking_date) return false;
                const bookingDate = new Date(booking.booking_date);

                if (fromDate && bookingDate < fromDate) return false;
                if (toDate && bookingDate > toDate) return false;

                return true;
            });

            if (hasMatchingBookings) {
                row.style.display = '';
                hasVisibleMonths = true;
            } else {
                row.style.display = 'none';
            }
        } else {
            row.style.display = 'none';
        }
    });

    // Show message if no months match the filter
    if (!hasVisibleMonths) {
        showNoResultsMessage();
    }

    // Show notification that filters were applied
    if (typeof showNotification === 'function') {
        showNotification('Date filters applied from dashboard', 'success');
    }
}

document.addEventListener('DOMContentLoaded', function () {
    console.log('DOM Content Loaded - Initializing search');
    const loaderContainer = document.querySelector(".loader-container");
    if (loaderContainer) loaderContainer.style.display = "flex";

    // Apply URL date filters first
    applyUrlDateFiltersToBookings();

    // Store original data for restoration
    initializeOriginalData();

    // Setup all event listeners
    setupEventListeners();

    // Initialize per page values for each month
    initializePerPageValues();

    // const loaderContainer = document.querySelector(".loader-container");
    if (loaderContainer) loaderContainer.style.display = "none";

    console.log('Search initialization complete');
});

// Store original visibility state
function initializeOriginalData() {
    document.querySelectorAll('#mainTableBody .table-row').forEach(row => {
        const onclickAttr = row.getAttribute('onclick');
        if (onclickAttr) {
            const match = onclickAttr.match(/'([^']+)'/);
            if (match) {
                const month = match[1];
                originalData[month] = {
                    visible: true,
                    nestedData: Array.from((function () { const c = document.getElementById('nested-data-' + month); return c ? c.querySelectorAll('.compact-row') : []; })()).map(nestedRow => ({
                        element: nestedRow,
                        visible: true
                    }))
                };
            }
        }
    });
}

// Initialize per page values
function initializePerPageValues() {
    // Get months from PHP data or from DOM
    const monthRows = document.querySelectorAll('#mainTableBody .table-row[onclick]');
    monthRows.forEach(row => {
        const onclickAttr = row.getAttribute('onclick');
        if (onclickAttr) {
            const match = onclickAttr.match(/'([^']+)'/);
            if (match) {
                const month = match[1];
                nestedPerPageValues[month] = 5;
            }
        }
    });
}

// Initialize original data storage
document.addEventListener('DOMContentLoaded', function () {
    // Store original visibility state
    document.querySelectorAll('#mainTableBody .table-row').forEach(row => {
        const month = row.getAttribute('onclick').match(/'([^']+)'/)[1];
        originalData[month] = {
            visible: true,
            nestedData: Array.from((function () { const c = document.getElementById('nested-data-' + month); return c ? c.querySelectorAll('.compact-row') : []; })()).map(nestedRow => ({
                element: nestedRow,
                visible: true
            }))
        };
    });

    setupEventListeners();

    // Initialize per page values for each month
    Object.keys(groupedRows).forEach(month => {
        nestedPerPageValues[month] = 5;
    });
});

// Open filter modal
function openFilterModal() {
    document.getElementById('filterModalOverlay').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

// Close filter modal
function closeFilterModal() {
    document.getElementById('filterModalOverlay').style.display = 'none';
    document.body.style.overflow = '';
}

// Clear all filters// Clear all filters (specific version)
// Clear all filters (fixed version)
// Fixed clearFilters function that properly resets nested pagination
function clearFilters() {
    // List of all filter field IDs to clear
    const filterIds = [
        'filterBookingDateStart', 'filterBookingDateEnd', 'filterMonth',
        'filterBuilder', 'filterProject', 'filterType', 'filterUnit',
        'filterSize', 'filterCustumername', 'filterContactnumber',
        'filterEmail', 'filterStatus', 'filterSalesperson'
    ];

    // Clear each field
    filterIds.forEach(id => {
        const field = document.getElementById(id);
        if (field) {
            field.value = '';
        }
    });

    // Clear main search input
    const mainSearchInput = document.getElementById('mainSearch');
    if (mainSearchInput) {
        mainSearchInput.value = '';
        window.currentSearchTerm = '';
    }

    // Clear active filters object
    activeFilters = {};
    window.activeFilters = {}; // sync for inline download function

    // Reset filter applied flag
    isFilterApplied = false;

    // Remove no results message
    document.querySelectorAll('.no-results-row').forEach(el => el.remove());
    document.querySelectorAll('.no-nested-results').forEach(el => el.remove());

    // Reset all nested table states
    Object.keys(nestedCurrentPages).forEach(month => {
        // Reset pagination to first page
        nestedCurrentPages[month] = 1;
        // Reset items per page to default
        nestedPerPageValues[month] = 5;
        // Clear nested search terms
        delete nestedSearchTerms[month];

        // Reset the per-page selector for this month
        const perPageSelect = document.querySelector(`select[onchange*="${month}"]`);
        if (perPageSelect) {
            perPageSelect.value = '5';
        }

        // Clear nested search input for this month
        const nestedSearchInput = document.querySelector(`input[oninput*="${month}"]`);
        if (nestedSearchInput) {
            nestedSearchInput.value = '';
        }

        // Show all nested rows for this month
        const nestedContainer = document.getElementById(`nested-data-${month}`);
        if (nestedContainer) {
            const nestedRows = nestedContainer.querySelectorAll('.compact-row');
            nestedRows.forEach(row => {
                row.style.display = '';
                row.dataset.filterMatch = 'true';
                row.dataset.searchMatch = 'true';
            });
        }

        // Update nested pagination for this month
        updateNestedPagination(month);
    });

    // Show all main table rows and reset their search match status
    document.querySelectorAll('#mainTableBody .table-row').forEach(row => {
        row.style.display = '';
        row.dataset.searchMatch = 'true';
        row.dataset.filterMatch = 'true';
    });

    // Reset main pagination to first page
    mainCurrentPage = 1;

    // Reinitialize the pagination system
    initializeMainPagination();
    updateMainPaginationControls();
    applyMainPagination();

    // Update stats to show all data (no filters)
    updateStatsFromFilteredData();

    // Close the filter modal
    closeFilterModal();

    // Toggle clear filters button visibility
    const clearBtn = document.getElementById('clearAllFiltersBtn');
    if (clearBtn) {
        clearBtn.classList.remove('show');
    }
}



// Fixed restore original view - Keep nested search independent
function restoreOriginalView() {
    // Reset main search term
    window.currentSearchTerm = '';

    // Show all month rows
    document.querySelectorAll('#mainTableBody .table-row').forEach(row => {
        row.style.display = '';
        row.dataset.searchMatch = 'true';
    });

    // Show all nested rows (but respect their individual search terms)
    Object.keys(groupedRows).forEach(month => {
        const nestedContainer = document.getElementById('nested-data-' + month);
        const nestedRows = nestedContainer ? nestedContainer.querySelectorAll('.compact-row') : [];
        nestedRows.forEach(nestedRow => {
            nestedRow.style.display = '';
        });

        // If this month is expanded, refresh its pagination (respecting its own search term)
        if (expandedRows.includes(month)) {
            updateNestedPagination(month);
        }
    });

    // Reset pagination to first page
    mainCurrentPage = 1;
    applyMainPagination();
    updateMainPaginationControls();

    // Update stats to show all data (no filters)
    updateStatsFromFilteredData();

    // Hide filter-dependent download buttons
    document.querySelectorAll('.filter-dependent-download').forEach(btn => {
        btn.style.display = 'none';
        btn.disabled = true;
        btn.classList.remove('filter-active');
    });
}

// Helper: get first existing element by possible IDs
function getByIds(ids) {
    for (const id of ids) {
        const el = document.getElementById(id);
        if (el) return el;
    }
    return null;
}

// Helper: read input value safely (lowercased + trimmed for text fields)
function getVal(ids, toLower = true) {
    const el = getByIds(Array.isArray(ids) ? ids : [ids]);
    if (!el) return '';

    // Custom booking filter dropdown inputs keep a structured selected-values payload.
    // Prefer this over comma-splitting the visible text (values can themselves contain commas).
    if (el.classList && el.classList.contains('filter-dropdown-input')) {
        const rawSelected = el.getAttribute('data-selected-value');
        if (rawSelected) {
            let selectedValues = [];
            try {
                selectedValues = JSON.parse(rawSelected);
            } catch (_) {
                selectedValues = rawSelected.split(',').map(v => v.trim()).filter(Boolean);
            }

            if (Array.isArray(selectedValues) && selectedValues.length > 0) {
                return toLower
                    ? selectedValues.map(v => String(v).toLowerCase().trim())
                    : selectedValues.map(v => String(v).trim());
            }
        }
    }

    // Check if element is a Select2 multi-select or native multi-select
    if (el.multiple || (window.$ && window.$(el).hasClass('select2-hidden-accessible'))) {
        // For Select2 multi-select, get selected values as array
        if (window.$ && window.$(el).hasClass('select2-hidden-accessible')) {
            const selectedValues = window.$(el).val();
            if (Array.isArray(selectedValues) && selectedValues.length > 0) {
                return toLower ? selectedValues.map(v => String(v).toLowerCase().trim()) : selectedValues.map(v => String(v).trim());
            }
            return [];
        }
        // For native multi-select
        const selectedOptions = Array.from(el.selectedOptions || []);
        if (selectedOptions.length > 0) {
            const values = selectedOptions.map(opt => opt.value);
            return toLower ? values.map(v => String(v).toLowerCase().trim()) : values.map(v => String(v).trim());
        }
        return [];
    }

    // For single value inputs
    const v = (el.value ?? '').toString().trim();
    return toLower ? v.toLowerCase() : v;
}

// Helper function to format numbers in compact format (K, M, B)
function formatCompactNumber(number) {
    if (number >= 1000000000) {
        return '₹' + (number / 1000000000).toFixed(1) + 'B';
    } else if (number >= 1000000) {
        return '₹' + (number / 1000000).toFixed(1) + 'M';
    } else if (number >= 1000) {
        return '₹' + (number / 1000).toFixed(1) + 'K';
    } else {
        return '₹' + number.toLocaleString();
    }
}

// Comprehensive function to update ALL stats based on filtered data
function updateStatsFromFilteredData() {
    // Overall totals
    let totalBookings = 0;
    let totalRevenue = 0;
    let actualRevenue = 0;
    let receivedAmount = 0;
    
    // Top banner specific stats
    let topBookings = 0;
    let topOverallAgreement = 0;
    let topActualAgreement = 0;
    let topOverallRevenue = 0;
    let topInvoiceRaised = 0;
    let topCancelledAgreement = 0;
    let topCancelledCount = 0;

    // Per-year stats
    const yearlyStats = {};

    // Get all months/years from groupedRows
    const data = window.groupedRows || {};

    // Iterate through all grouped data (months or financial years)
    Object.keys(data).forEach(yearKey => {
        // Obey month/year filter if one is applied
        if (isFilterApplied && activeFilters.month) {
            try {
                if (typeof monthMatches === 'function' && !monthMatches(yearKey, activeFilters.month)) {
                    return; // Skip this entire group
                }
            } catch (e) { /* fallback if monthMatches is absent */ }
        }

        const rows = data[yearKey] || [];

        // Initialize year stats
        yearlyStats[yearKey] = {
            bookings: 0,
            totalRevenue: 0,
            actualRevenue: 0,
            receivedAmount: 0,
            invoiceRaised: 0,
            processingCount: 0,
            canceledCount: 0,
            receivedCount: 0
        };

        rows.forEach(row => {
            // Check if this row matches the current filters
            if (isFilterApplied) {
                if (!matchesFilters(row)) {
                    return; // Skip rows that don't match filters
                }
            }

            // Aggregate overall stats
            totalBookings++;
            totalRevenue += parseFloat(row.revenue) || 0;
            actualRevenue += parseFloat(row.crevenue) || 0;
            receivedAmount += parseFloat(row.recived_amt) || 0;
            
            // Collect accurate stats matching PHP top banners
            const status = (row.astatus || "").toLowerCase().trim();
            const agValue = parseFloat(row.agreement_value) || 0;
            const deductAgValue = parseFloat(row.deduct_agreement) || 0;
            const cRevValue = parseFloat(row.crevenue) || 0;
            const invRaiseValue = parseFloat(row.invoice_raise) || 0;

            if (status === "canceled" || status === "cancelled") {
                topCancelledAgreement += agValue;
                topCancelledCount++;
            } else {
                topBookings++;
                topOverallAgreement += agValue;
                topActualAgreement += deductAgValue;
                topOverallRevenue += cRevValue;
                topInvoiceRaised += invRaiseValue;
            }

            // Aggregate per-year stats
            yearlyStats[yearKey].bookings++;
            yearlyStats[yearKey].totalRevenue += parseFloat(row.revenue) || 0;
            yearlyStats[yearKey].actualRevenue += parseFloat(row.crevenue) || 0;
            yearlyStats[yearKey].receivedAmount += parseFloat(row.recived_amt) || 0;
            yearlyStats[yearKey].invoiceRaised += parseFloat(row.invoice_raise) || 0;

            // Count by status
            if (status === "processing") {
                yearlyStats[yearKey].processingCount++;
            } else if (status === "canceled" || status === "cancelled") {
                yearlyStats[yearKey].canceledCount++;
            } else if (status === "received") {
                yearlyStats[yearKey].receivedCount++;
            }
        });
    });

    // ========== UPDATE TOP STATS CARDS ==========
    const statOverallBookings = document.getElementById("statOverallBookings");
    const statOverallAgreement = document.getElementById("statOverallAgreement");
    const statActualAgreement = document.getElementById("statActualAgreement");
    const statOverallRevenue = document.getElementById("statOverallRevenue");
    const statCancelledAgreement = document.getElementById("statCancelledAgreement");
    const statFinalRemaining = document.getElementById("statFinalRemaining");

    if (statOverallBookings) statOverallBookings.textContent = topBookings;
    if (statOverallAgreement) statOverallAgreement.textContent = formatCompactNumber(topOverallAgreement);
    if (statActualAgreement) statActualAgreement.textContent = formatCompactNumber(topActualAgreement);
    if (statOverallRevenue) statOverallRevenue.textContent = formatCompactNumber(topOverallRevenue);
    if (statFinalRemaining) statFinalRemaining.textContent = formatCompactNumber(Math.max(0, topOverallRevenue - topInvoiceRaised));
    
    // Use innerHTML for cancelled agreement to include count with style
    if (statCancelledAgreement) {
        statCancelledAgreement.innerHTML = formatCompactNumber(topCancelledAgreement) + 
            ` <span style="opacity: 0.8;">(${topCancelledCount})</span>`;
    }

    // Update tooltips if cards are present
    const cardOverallBookings = document.getElementById("cardOverallBookings");
    if (cardOverallBookings) cardOverallBookings.dataset.tooltip = topBookings + " Bookings";
    
    if (document.getElementById("cardOverallAgreement") && window.getFullRupeeValue) 
        document.getElementById("cardOverallAgreement").dataset.tooltip = window.getFullRupeeValue(topOverallAgreement);

    if (document.getElementById("cardActualAgreement") && window.getFullRupeeValue) 
        document.getElementById("cardActualAgreement").dataset.tooltip = window.getFullRupeeValue(topActualAgreement);

    if (document.getElementById("cardOverallRevenue") && window.getFullRupeeValue) 
        document.getElementById("cardOverallRevenue").dataset.tooltip = window.getFullRupeeValue(topOverallRevenue);

    if (document.getElementById("cardCancelledAgreement") && window.getFullRupeeValue) 
        document.getElementById("cardCancelledAgreement").dataset.tooltip = window.getFullRupeeValue(topCancelledAgreement);

    if (document.getElementById("cardFinalRemaining") && window.getFullRupeeValue) 
        document.getElementById("cardFinalRemaining").dataset.tooltip = window.getFullRupeeValue(Math.max(0, topOverallRevenue - topInvoiceRaised));

    // ========== UPDATE FILTER SUMMARY BANNER ==========
    const filterStatBookings = document.getElementById('filterStatBookings');
    const filterStatTotalRevenue = document.getElementById('filterStatTotalRevenue');
    const filterStatActualRevenue = document.getElementById('filterStatActualRevenue');

    if (filterStatBookings) filterStatBookings.textContent = totalBookings;
    if (filterStatTotalRevenue) filterStatTotalRevenue.textContent = formatCompactNumber(totalRevenue);
    if (filterStatActualRevenue) filterStatActualRevenue.textContent = formatCompactNumber(actualRevenue);

    const filterSummaryBanner = document.getElementById('filterSummaryBanner');
    if (filterSummaryBanner) {
        filterSummaryBanner.style.display = isFilterApplied ? 'block' : 'none';
    }

    // ========== UPDATE EACH YEAR ROW STATS ==========
    Object.keys(yearlyStats).forEach(yearKey => {
        const stats = yearlyStats[yearKey];
        const remaining = stats.actualRevenue - stats.invoiceRaised;

        // Find the main table row for this year
        const yearRow = document.querySelector(`tr[onclick*="'${yearKey}'"]`);
        if (yearRow) {
            // Update the month-cell to show filtered count
            const monthCell = yearRow.querySelector('.month-cell');
            if (monthCell) {
                // Get original year data for total_raised
                const originalYearData = window.yearlyData?.find(y => y.year === yearKey);
                const totalRaised = originalYearData?.total_raised || 0;

                // Update the text, preserving the icon
                const icon = monthCell.querySelector('.icon');
                const iconHtml = icon ? icon.outerHTML : '';
                monthCell.innerHTML = iconHtml + ` ${yearKey} (${stats.bookings}/${totalRaised})`;
            }

            // Update actual revenue cell
            const actualRevenueCell = yearRow.querySelector('.actual-revenue-cell');
            if (actualRevenueCell) {
                actualRevenueCell.innerHTML = `<i class="fas fa-wallet"></i> ${formatCompactNumber(stats.actualRevenue)}`;
                actualRevenueCell.className = `actual-revenue-cell amount ${stats.actualRevenue > 0 ? 'positive' : 'zero'}`;
            }

            // Update remaining cell
            const remainingCell = yearRow.querySelector('.remaining-amount-cell, .remaining-col:not(th)');
            if (remainingCell && remainingCell.tagName === 'TD') {
                remainingCell.innerHTML = `<i class="fas fa-coins"></i> ${formatCompactNumber(remaining)}`;
            }
        }

        // ========== UPDATE NESTED SECTION STATS ==========
        const nestedSection = document.getElementById(`nested-${yearKey}`);
        if (nestedSection) {
            // Update mobile-year-stats chips
            const statChips = nestedSection.querySelectorAll('.stat-chip');
            statChips.forEach(chip => {
                const label = chip.querySelector('span')?.textContent?.toLowerCase() || '';
                const valueEl = chip.querySelector('strong');
                if (!valueEl) return;

                if (label.includes('revenue') && !label.includes('actual')) {
                    valueEl.textContent = formatCompactNumber(stats.totalRevenue);
                } else if (label.includes('remaining')) {
                    valueEl.textContent = formatCompactNumber(remaining);
                } else if (label.includes('recent') || label.includes('build')) {
                    valueEl.textContent = formatCompactNumber(stats.receivedAmount);
                }
            });

            // Update status counts
            const statusCounts = nestedSection.querySelectorAll('.status-count');
            statusCounts.forEach(countEl => {
                const text = countEl.textContent?.toLowerCase() || '';
                if (text.includes('processing')) {
                    countEl.textContent = `Total Processing :- ${stats.processingCount}`;
                } else if (text.includes('cancel')) {
                    countEl.textContent = `Total Cancelled :- ${stats.canceledCount}`;
                } else if (text.includes('received')) {
                    countEl.textContent = `Total Received :- ${stats.receivedCount}`;
                }
            });

            // Update financial year title
            const fyTitle = nestedSection.querySelector('.financial-year-title');
            if (fyTitle) {
                fyTitle.textContent = `Financial Year - ${yearKey}`;
            }
        }
    });

    console.log('All stats updated:', { totalBookings, totalRevenue, actualRevenue, receivedAmount, yearlyStats });
}

// Make function globally available
window.updateStatsFromFilteredData = updateStatsFromFilteredData;

// Apply filters to the data
function applyFilters() {
    // Collect filter values
    activeFilters = {
        id: getVal('filterID'),
        bookingDateStart: getVal('filterBookingDateStart', false),
        bookingDateEnd: getVal('filterBookingDateEnd', false),
        month: getVal('filterMonth', false),
        builder: getVal('filterBuilder'),
        project: getVal('filterProject'),
        city: getVal('filterCity'),
        // support multiple possible IDs for customer name to avoid typos
        customerName: getVal(['filterCustumername', 'filterCustomername', 'filterCustomerName']),
        contactNumber: getVal(['filterContactnumber', 'filterContactNumber']),
        email: getVal('filterEmail'),
        type: getVal('filterType'),
        unit: getVal('filterUnit'),
        size: getVal('filterSize'),
        agreement: getVal('filterAgreement'),
        commission: getVal('filterCommission') || '',
        revenue: getVal(['filterTrevenue', 'filterRevenue']) || '',
        cashback: getVal(['filterCashBack', 'filterCashback']),
        actualRevenue: getVal('filterActualRevenue') || '',
        status: getVal('filterStatus'),
        received: getVal('filterReceived') || '',
        salesperson: getVal('filterSalesperson')
    };

    // Remove empty filters and normalize values
    Object.keys(activeFilters).forEach(key => {
        let v = activeFilters[key];
        if (typeof v === 'string') v = v.trim();
        if (v === '' || v == null) {
            delete activeFilters[key];
            return;
        }

        // Fields that support multi-select (comma-separated values)
        const multiSelectFields = ['builder', 'project', 'city', 'customerName', 'contactNumber', 'email', 'type', 'unit', 'status', 'salesperson'];

        if (typeof v === 'string' && multiSelectFields.includes(key)) {
            // Check if value contains commas (multiple selections)
            if (v.includes(',')) {
                // Split by comma, trim and lowercase each value
                activeFilters[key] = v.split(',')
                    .map(val => val.trim().toLowerCase())
                    .filter(val => val !== ''); // Remove empty strings
            } else {
                // Single value - normalize to lowercase
                activeFilters[key] = v.toLowerCase();
            }
        } else if (typeof v === 'string') {
            // Other text filters - normalize to lowercase
            activeFilters[key] = v.toLowerCase();
        }
    });

    console.log('Active Filters:', activeFilters); // Debug log

    // Set filter applied flag BEFORE applying filters
    isFilterApplied = Object.keys(activeFilters).length > 0;
    window.activeFilters = activeFilters; // sync for inline download function

    // Apply filters
    if (Object.keys(activeFilters).length === 0) {
        restoreOriginalView();
    } else {
        // If month filter was provided, try to narrow directly to that month
        if (activeFilters.month) {
            // show only months that match the provided month filter (supports many formats)
            const target = activeFilters.month;
            document.querySelectorAll('#mainTableBody .table-row[onclick]').forEach(row => {
                const onclick = row.getAttribute('onclick') || '';
                const m = onclick.match(/'([^']+)'/);
                const month = m ? m[1] : null;
                if (month && monthMatches(month, target)) {
                    row.style.display = '';
                    const nested = document.getElementById('nested-' + month);
                    if (nested) filterNestedData(month);
                } else {
                    row.style.display = 'none';
                    const nested = document.getElementById('nested-' + month);
                    if (nested) nested.style.display = 'none';
                }
            });
        } else {
            filterMonthlyData();
        }
    }

    closeFilterModal();

    mainCurrentPage = 1;
    applyMainPagination();
    updateMainPaginationControls();

    // Update stats based on filtered data
    updateStatsFromFilteredData();

    // Toggle clear filters button visibility
    const clearBtn = document.getElementById('clearAllFiltersBtn');
    if (clearBtn) {
        if (Object.keys(activeFilters).length > 0) {
            clearBtn.classList.add('show');
        } else {
            clearBtn.classList.remove('show');
        }
    }

    // Toggle filter-dependent download buttons
    document.querySelectorAll('.filter-dependent-download').forEach(btn => {
        if (isFilterApplied) {
            btn.style.display = 'inline-flex';
            btn.disabled = false;
            btn.removeAttribute('disabled');
            btn.classList.add('filter-active');
        } else {
            btn.style.display = 'none';
            btn.disabled = true;
            btn.classList.remove('filter-active');
        }
    });
}

// Apply filters coming from an embedded parent
function applyExternalFilters(incomingFilters = {}) {
    // Normalize and trim values just like the local applyFilters
    const normalized = {};
    const lowerKeys = ['id', 'builder', 'project', 'city', 'customerName', 'contactNumber', 'email', 'type', 'unit', 'size', 'agreement', 'commission', 'revenue', 'cashback', 'actualRevenue', 'status', 'received'];
    const multiSelectFields = ['builder', 'project', 'city', 'customerName', 'contactNumber', 'email', 'type', 'unit', 'status'];

    Object.entries(incomingFilters || {}).forEach(([key, value]) => {
        if (value === undefined || value === null) return;
        if (typeof value === 'string') {
            const trimmed = value.trim();
            if (!trimmed) return;

            // Handle multi-select fields with comma-separated values
            if (multiSelectFields.includes(key) && trimmed.includes(',')) {
                normalized[key] = trimmed.split(',')
                    .map(val => val.trim().toLowerCase())
                    .filter(val => val !== '');
            } else {
                normalized[key] = lowerKeys.includes(key) ? trimmed.toLowerCase() : trimmed;
            }
        } else {
            normalized[key] = value;
        }
    });

    activeFilters = normalized;
    isFilterApplied = Object.keys(activeFilters).length > 0;
    window.activeFilters = activeFilters; // sync for inline download function

    if (!isFilterApplied) {
        clearFilters();
        return;
    }

    removeNoResultsMessage();

    if (activeFilters.month) {
        document.querySelectorAll('#mainTableBody .table-row[onclick]').forEach(row => {
            const onclick = row.getAttribute('onclick') || '';
            const m = onclick.match(/'([^']+)'/);
            const month = m ? m[1] : null;

            if (month && monthMatches(month, activeFilters.month)) {
                row.style.display = '';
                const nested = document.getElementById('nested-' + month);
                if (nested) {
                    nested.style.display = expandedRows.includes(month) ? 'table-row' : 'none';
                    filterNestedData(month);
                }
            } else {
                row.style.display = 'none';
                const nested = document.getElementById('nested-' + month);
                if (nested) nested.style.display = 'none';
            }
        });
    } else {
        filterMonthlyData();
    }

    mainCurrentPage = 1;
    applyMainPagination();
    updateMainPaginationControls();

    // Update stats based on filtered data
    updateStatsFromFilteredData();

    // Only show the dashboard clear button when running in the top-level app (not embedded in superadmin wrapper)
    if (window.self === window.top) {
        showDashboardClearFilterButtonForBookings();
    }

    // Toggle filter-dependent download buttons
    // Toggle filter-dependent download buttons (external filter path)
    document.querySelectorAll('.filter-dependent-download').forEach(btn => {
        if (isFilterApplied) {
            btn.style.display = 'inline-flex';
            btn.disabled = false;
            btn.removeAttribute('disabled');
            btn.classList.add('filter-active');
        } else {
            btn.style.display = 'none';
            btn.disabled = true;
            btn.classList.remove('filter-active');
        }
    });
}
window.applyExternalFilters = applyExternalFilters;

// Listen for filter events from parent iframes
window.addEventListener('message', function (event) {
    const data = event.data || {};

    if (data.type === 'applyFilters') {
        applyExternalFilters(data.filters || {});
    }

    if (data.type === 'clearFilters') {
        if (typeof clearFilters === 'function') {
            clearFilters();
        }
    }
});

// document.addEventListener('DOMContentLoaded', function() {
//     initializeMainPagination();
//     handleMainPerPageChange();
//     setupMainPaginationButtons();
// });


// Filter monthly data
function filterMonthlyData() {
    const monthlyRows = document.querySelectorAll('#mainTableBody .table-row[onclick]');
    let hasVisibleMonths = false;
    // Remove existing no results message
    document.querySelectorAll('.no-results-row').forEach(el => el.remove());

    monthlyRows.forEach(row => {
        const onclick = row.getAttribute('onclick');
        const m = onclick ? onclick.match(/'([^']+)'/) : null;
        if (!m) { return; }
        const month = m[1];
        let shouldShowMonth = false;
        // Check if month has any matching nested data
        const hasMatchingData = checkMonthHasMatchingData(month);
        if (hasMatchingData) {
            shouldShowMonth = true;
        }
        if (shouldShowMonth) {
            row.style.display = '';
            hasVisibleMonths = true;
            // Apply filters to nested data
            const nestedSection = document.getElementById(`nested-${month}`);
            if (nestedSection) {
                // If month is expanded, show filtered nested rows; otherwise keep hidden until expanded
                nestedSection.style.display = expandedRows.includes(month) ? 'table-row' : 'none';
                // Always apply filters to nested data, regardless of whether it's currently visible
                filterNestedData(month);
            }
        } else {
            row.style.display = 'none';
            // Hide nested section too
            const nestedSection = document.getElementById(`nested-${month}`);
            if (nestedSection) {
                nestedSection.style.display = 'none';
            }
            // clear any nested search term for hidden months
            delete nestedSearchTerms[month];
        }
    });
    // Show message if no months match filters
    if (!hasVisibleMonths) {
        showNoResultsMessage();
    }

    // After filtering, reset main pagination to page 1 and apply only on visible rows
    mainCurrentPage = 1;
    applyMainPagination();
    updateMainPaginationControls();
}

// Check if a month has matching data
function checkMonthHasMatchingData(month) {
    // If a month filter is active, quickly reject months that don't match the filter
    if (activeFilters && activeFilters.month) {
        try {
            if (!monthMatches(month, activeFilters.month)) return false;
        } catch (e) { /* fallthrough to DOM scan */ }
    }

    // Prefer server-provided structured data
    if (window.groupedRows && window.groupedRows[month]) {
        const monthBookings = window.groupedRows[month];
        const hasMatch = monthBookings.some(row => matchesFilters(row));
        return hasMatch;
    }

    // Fallback: scan DOM nested rows and extract their data
    const nestedContainer = document.getElementById('nested-data-' + month);
    if (!nestedContainer) return false;
    const rows = nestedContainer.querySelectorAll('.compact-row');
    for (let i = 0; i < rows.length; i++) {
        const domRow = rows[i];
        const rowData = extractRowData(domRow, month);
        if (matchesFilters(rowData)) return true;
    }
    return false;
}

// Helper: tolerant month matching
// monthKey is the canonical month identifier used in the page (e.g. '2024-11' or 'November 2024')
// target is the user-entered filter value (may be '2024-11', 'nov 2024', 'nov', '11/2024', etc.)
function monthMatches(monthKey, target) {
    if (!monthKey || !target) return false;
    const a = String(monthKey).trim().toLowerCase();
    const b = String(target).trim().toLowerCase();

    // direct include/equality check first
    if (a === b) return true;
    if (a.includes(b) || b.includes(a)) return true;

    // Try parse yyyy-mm from either side
    const rxYMdash = /^(\d{4})-(\d{2})$/;
    const rxSlash = /^(\d{1,2})[\/-](\d{4})$/; // mm/yyyy or m/yyyy

    const pa = rxYMdash.exec(a);
    const pb = rxYMdash.exec(b);
    if (pa && pb) {
        return pa[1] === pb[1] && pa[2] === pb[2];
    }

    // If one side is yyyy-mm and the other is mm/yyyy or month name, normalize both to {year,month}
    function toYearMonth(s) {
        if (!s) return null;
        const t = s.trim();
        const m1 = rxYMdash.exec(t);
        if (m1) return { year: m1[1], month: m1[2].padStart(2, '0') };
        const m2 = rxSlash.exec(t);
        if (m2) return { year: m2[2], month: m2[1].padStart(2, '0') };
        // Try to parse textual month like 'nov 2024' or 'november 2024'
        const words = t.split(/\s+/);
        // find a 4-digit year token
        const yearToken = words.find(w => /^\d{4}$/.test(w));
        const year = yearToken || null;
        // try to match month name or number
        const monthNames = ['january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december'];
        const monthToken = words.find(w => monthNames.indexOf(w) !== -1 || /^\d{1,2}$/.test(w));
        if (year && monthToken) {
            let m = monthNames.indexOf(monthToken) + 1;
            if (m === 0) m = parseInt(monthToken, 10);
            if (!isNaN(m) && m >= 1 && m <= 12) {
                return { year: year, month: String(m).padStart(2, '0') };
            }
        }
        return null;
    }

    const ya = toYearMonth(a);
    const yb = toYearMonth(b);
    if (ya && yb) {
        return ya.year === yb.year && ya.month === yb.month;
    }

    // fallback: substring match
    return a.includes(b) || b.includes(a);
}

// Check if a row matches all active filters
function matchesFilters(row) {
    // Skip month filter as it's handled at month level
    const filtersToCheck = { ...activeFilters };
    delete filtersToCheck.month;

    // If no filters other than month, return true
    if (Object.keys(filtersToCheck).length === 0) {
        return true;
    }

    // Check each filter
    if (filtersToCheck.id && !String(row.id).toLowerCase().includes(filtersToCheck.id)) {
        return false;
    }

    if (filtersToCheck.bookingDateStart && row.booking_date < filtersToCheck.bookingDateStart) {
        return false;
    }

    if (filtersToCheck.bookingDateEnd && row.booking_date > filtersToCheck.bookingDateEnd) {
        return false;
    }

    if (filtersToCheck.builder) {
        const builderVal = String(row.builder || '').toLowerCase().trim();
        if (Array.isArray(filtersToCheck.builder)) {
            const hasMatch = filtersToCheck.builder.some(val => builderVal === String(val).toLowerCase().trim());
            if (filtersToCheck.builder.length > 0 && !hasMatch) return false;
        } else if (builderVal !== filtersToCheck.builder) {
            return false;
        }
    }

    if (filtersToCheck.project) {
        const projectVal = String(row.project || '').toLowerCase().trim();
        if (Array.isArray(filtersToCheck.project)) {
            const hasMatch = filtersToCheck.project.some(val => projectVal === String(val).toLowerCase().trim());
            if (filtersToCheck.project.length > 0 && !hasMatch) return false;
        } else if (projectVal !== filtersToCheck.project) {
            return false;
        }
    }

    if (filtersToCheck.city) {
        const cityVal = String(row.city || '').toLowerCase().trim();
        if (Array.isArray(filtersToCheck.city)) {
            const hasMatch = filtersToCheck.city.some(val => cityVal === String(val).toLowerCase().trim());
            if (filtersToCheck.city.length > 0 && !hasMatch) return false;
        } else if (cityVal !== filtersToCheck.city) {
            return false;
        }
    }

    if (filtersToCheck.customerName) {
        const customerVal = String(row.customer_name || '').toLowerCase().trim();
        if (Array.isArray(filtersToCheck.customerName)) {
            const hasMatch = filtersToCheck.customerName.some(val => customerVal === String(val).toLowerCase().trim());
            if (filtersToCheck.customerName.length > 0 && !hasMatch) return false;
        } else if (customerVal !== filtersToCheck.customerName) {
            return false;
        }
    }

    if (filtersToCheck.contactNumber) {
        const contactVal = String(row.contact_number || '').toLowerCase().trim();
        if (Array.isArray(filtersToCheck.contactNumber)) {
            const hasMatch = filtersToCheck.contactNumber.some(val => contactVal === String(val).toLowerCase().trim());
            if (filtersToCheck.contactNumber.length > 0 && !hasMatch) return false;
        } else if (contactVal !== filtersToCheck.contactNumber) {
            return false;
        }
    }

    if (filtersToCheck.email) {
        const emailVal = String(row.email_id || '').toLowerCase().trim();
        if (Array.isArray(filtersToCheck.email)) {
            const hasMatch = filtersToCheck.email.some(val => emailVal === String(val).toLowerCase().trim());
            if (filtersToCheck.email.length > 0 && !hasMatch) return false;
        } else if (emailVal !== filtersToCheck.email) {
            return false;
        }
    }

    if (filtersToCheck.type) {
        const typeVal = String(row.project_type || '').toLowerCase().trim();
        if (Array.isArray(filtersToCheck.type)) {
            const hasMatch = filtersToCheck.type.some(val => typeVal === String(val).toLowerCase().trim());
            if (filtersToCheck.type.length > 0 && !hasMatch) return false;
        } else if (typeVal !== filtersToCheck.type) {
            return false;
        }
    }

    if (filtersToCheck.unit) {
        const unitVal = String(row.unit_no || '').toLowerCase().trim();
        if (Array.isArray(filtersToCheck.unit)) {
            const hasMatch = filtersToCheck.unit.some(val => unitVal === String(val).toLowerCase().trim());
            if (filtersToCheck.unit.length > 0 && !hasMatch) return false;
        } else if (unitVal !== filtersToCheck.unit) {
            return false;
        }
    }

    if (filtersToCheck.size && !String(row.size).includes(filtersToCheck.size)) {
        return false;
    }

    if (filtersToCheck.agreement && !String(row.agreement_value).includes(filtersToCheck.agreement.replace(/[^0-9]/g, ''))) {
        return false;
    }

    if (filtersToCheck.commission && row.commission_percent && !String(row.commission_percent).includes(filtersToCheck.commission.replace(/[^0-9.]/g, ''))) {
        return false;
    }

    if (filtersToCheck.revenue && row.revenue && !String(row.revenue).includes(filtersToCheck.revenue.replace(/[^0-9]/g, ''))) {
        return false;
    }

    if (filtersToCheck.cashback && row.cashback_percent && !String(row.cashback_percent).includes(filtersToCheck.cashback.replace(/[^0-9.]/g, ''))) {
        return false;
    }

    if (filtersToCheck.actualRevenue && row.crevenue && !String(row.crevenue).includes(filtersToCheck.actualRevenue.replace(/[^0-9]/g, ''))) {
        return false;
    }

    if (filtersToCheck.status) {
        const statusVal = String(row.astatus || '').toLowerCase().trim();
        if (Array.isArray(filtersToCheck.status)) {
            const hasMatch = filtersToCheck.status.some(val => statusVal === String(val).toLowerCase().trim());
            if (filtersToCheck.status.length > 0 && !hasMatch) return false;
        } else if (statusVal !== filtersToCheck.status.toLowerCase()) {
            return false;
        }
    }

    if (filtersToCheck.received && row.recived_amt && !String(row.recived_amt).includes(filtersToCheck.received.replace(/[^0-9]/g, ''))) {
        return false;
    }

    if (filtersToCheck.salesperson) {
        const spVal = String(row.source_table || '').toLowerCase().trim();
        if (Array.isArray(filtersToCheck.salesperson)) {
            const hasMatch = filtersToCheck.salesperson.some(val => spVal === String(val).toLowerCase().trim());
            if (filtersToCheck.salesperson.length > 0 && !hasMatch) return false;
        } else if (spVal !== filtersToCheck.salesperson) {
            return false;
        }
    }

    return true;
}

// Filter nested data for a specific month - REMOVED DUPLICATE


// Fixed toggleRow function - Remove the problematic applyMainPagination call
function toggleRow(month) {
    const row = document.getElementById(`nested-${month}`);
    const icon = document.getElementById(`expand-${month}`);

    if (!row || !icon) return;

    const isHidden = window.getComputedStyle(row).display === 'none';

    if (isHidden) {
        // Close all other expanded rows
        expandedRows.forEach(expandedMonth => {
            if (expandedMonth !== month) {
                const otherRow = document.getElementById(`nested-${expandedMonth}`);
                const otherIcon = document.getElementById(`expand-${expandedMonth}`);
                if (otherRow && otherIcon) {
                    otherRow.style.display = 'none';
                    otherIcon.classList.remove('rotated');
                }
            }
        });

        // Update expanded rows array
        expandedRows = [month];

        // Open the requested row
        row.style.display = 'table-row';
        icon.classList.add('rotated');

        // Apply any active filters to the newly opened nested table
        if (isFilterApplied) {
            filterNestedData(month);
        }

        // Apply current search term to nested table if exists
        const searchEl = document.getElementById('mainSearch')
            || document.querySelector('.search-input')
            || document.querySelector('input[placeholder*="Search" i]');
        const currentSearch = (searchEl?.value || '').toLowerCase().trim();
        if (currentSearch) {
            nestedSearchTerms[month] = currentSearch;
        }

        // Update nested pagination
        updateNestedPagination(month);

    } else {
        // Close the requested row
        row.style.display = 'none';
        icon.classList.remove('rotated');
        expandedDetails = expandedDetails.filter(id => !id.includes(month));
        expandedRows = expandedRows.filter(m => m !== month);
    }
}
// Remove no results message
function removeNoResultsMessage() {
    const existingMessage = document.querySelector('.no-results-row');
    if (existingMessage) {
        existingMessage.remove();
    }
}


// Fixed handleMainSearch function - Don't apply main search to nested tables
function handleMainSearch(e) {
    // Unified entrypoint for main search input — delegate to simpleSearch which handles
    // nested-row matching (via groupedRows), filters and pagination consistently.
    try {
        const val = e && e.target ? e.target.value : String(e || '');
        simpleSearch(val);
    } catch (err) {
        console.error('handleMainSearch delegation failed', err);
    }
}


// Setup event listeners
function setupEventListeners() {
    // Robustly locate the main search input
    const mainSearch = document.getElementById('mainSearch')
        || document.querySelector('.search-input')
        || document.querySelector('input[placeholder*="Search" i]');
    if (mainSearch) {
        console.log('Setting up main search listener');
        try { mainSearch.removeEventListener('input', handleMainSearch); } catch (_) { }
        mainSearch.addEventListener('input', handleMainSearch);
        console.log('Main search listener attached to:', mainSearch);
    } else {
        console.error('Main search input not found!');
    }

    // NOTE: downloadFilteredBtn onclick is set via HTML attribute (onclick="downloadFilteredBookings()")
    // pointing to window.downloadFilteredBookings (the isolated IIFE version in user_booking.php).
    // Do NOT override dlBtn.onclick here — it would call the wrong local function.
}

// downloadFilteredBookings is defined as an IIFE in user_booking.php
// (isolated script tag) so it is always accessible from onclick and
// cannot be overridden by a hoisted function declaration in this file.


// Helper: within a month, check if any booking that passes active filters also matches the search term
function filteredMonthMatchesSearch(month, searchTerm) {
    if (!searchTerm) return true; // already filtered by isFilterApplied path
    const rows = (window.groupedRows && window.groupedRows[month]) || [];
    const term = searchTerm.toLowerCase();
    return rows.some(r => {
        if (!matchesFilters(r)) return false;
        const haystack = [
            r.id,
            r.builder,
            r.project,
            r.customer_name,
            r.contact_number,
            r.email_id,
            r.project_type,
            r.unit_no,
            r.size,
            r.agreement_value,
            r.commission_percent,
            r.revenue,
            r.cashback_percent,
            r.crevenue,
            r.astatus,
            r.recived_amt
        ].map(v => (v == null ? '' : String(v))).join(' ').toLowerCase();
        return haystack.includes(term);
    });
}

// Helper: when no filters are applied, search month by nested bookings too
function monthNestedMatchesSearch(month, searchTerm) {
    if (!searchTerm) return true;
    const rows = (window.groupedRows && window.groupedRows[month]) || [];
    const term = searchTerm.toLowerCase();
    return rows.some(r => {
        const haystack = [
            r.id,
            r.builder,
            r.project,
            r.customer_name,
            r.contact_number,
            r.email_id,
            r.project_type,
            r.unit_no,
            r.size,
            r.agreement_value,
            r.commission_percent,
            r.revenue,
            r.cashback_percent,
            r.crevenue,
            r.astatus,
            r.recived_amt
        ].map(v => (v == null ? '' : String(v))).join(' ').toLowerCase();
        return haystack.includes(term);
    });
}

// Centralized no-results message insertion below the header row
function showNoResultsMessage() {
    // remove any existing message first
    document.querySelectorAll('.no-results-row').forEach(el => el.remove());
    const tbody = document.getElementById('mainTableBody');
    if (!tbody) return;
    const headerCount = document.querySelectorAll('.table-head th').length || 6;
    const noResultsRow = document.createElement('tr');
    noResultsRow.className = 'no-results-row';
    noResultsRow.innerHTML = `<td colspan="${headerCount}" style="text-align: center; padding: 2rem; color: #64748b; font-style: italic;">No bookings match your current filters. Try adjusting your search criteria.</td>`;
    tbody.appendChild(noResultsRow);
}


// Handle per page change
function handlePerPageChange(month, perPage) {
    nestedPerPageValues[month] = parseInt(perPage);
    handleNestedSearch(month, nestedSearchTerms[month] || '');
}

// Debug function to check filter state
function debugFilters() {
    console.log('Active Filters:', activeFilters);
    console.log('Is Filter Applied:', isFilterApplied);
    console.log('Grouped Rows:', groupedRows);
    console.log('Monthly Data:', monthlyData);
}

// Debug function to check nested row filtering
function debugNestedFiltering(month) {
    console.log(`=== DEBUG NESTED FILTERING FOR ${month} ===`);
    console.log('Active Filters:', activeFilters);
    console.log('Is Filter Applied:', isFilterApplied);

    const nestedContainer = document.getElementById('nested-data-' + month);
    const nestedRows = nestedContainer ? nestedContainer.querySelectorAll('.compact-row') : [];

    nestedRows.forEach((row, index) => {
        let rowData;
        if (window.groupedRows && window.groupedRows[month] && window.groupedRows[month][index]) {
            rowData = window.groupedRows[month][index];
        } else {
            rowData = extractRowData(row, month);
        }

        const matches = matchesFilters(rowData);

        // Detailed status debugging
        let statusMatch = true;
        if (activeFilters.status) {
            statusMatch = String(rowData.astatus).toLowerCase().includes(activeFilters.status.toLowerCase());
        }

        console.log(`Row ${index}:`, {
            rowData: rowData,
            matches: matches,
            status: rowData.astatus,
            filterStatus: activeFilters.status,
            statusMatch: statusMatch,
            rowElement: row
        });
    });
}

// Enhanced debug function to test status extraction
function debugStatusExtraction(month) {
    console.log(`=== DEBUG STATUS EXTRACTION FOR ${month} ===`);
    const nestedContainer = document.getElementById('nested-data-' + month);
    const nestedRows = nestedContainer ? nestedContainer.querySelectorAll('.compact-row') : [];

    nestedRows.forEach((row, index) => {
        // Try all possible status selectors
        const statusSelectors = [
            'td:nth-child(5) .badge',
            '.status-cell .badge',
            '.badge.status',
            '.badge[class*="status"]',
            'td .badge:last-child',
            '.badge'
        ];

        const statusResults = {};
        statusSelectors.forEach(selector => {
            const element = row.querySelector(selector);
            statusResults[selector] = element ? element.textContent.trim() : 'NOT FOUND';
        });

        console.log(`Row ${index} status extraction:`, {
            rowElement: row,
            innerHTML: row.innerHTML.substring(0, 200) + '...',
            statusResults: statusResults,
            extractedData: extractRowData(row, month)
        });
    });
}

// Helper function to manually test status filtering
function testStatusFilter(month, statusValue) {
    console.log(`=== TEST STATUS FILTER: ${statusValue} FOR ${month} ===`);

    // Temporarily set the filter
    const originalFilters = { ...activeFilters };
    const originalIsFilterApplied = isFilterApplied;

    activeFilters.status = statusValue.toLowerCase();
    isFilterApplied = true;

    // Test the filtering
    debugNestedFiltering(month);

    // Apply the filter
    filterNestedData(month);

    console.log('Filter applied. Check the nested rows visibility.');

    // Restore original state if needed
    // activeFilters = originalFilters;
    // isFilterApplied = originalIsFilterApplied;
}

// Make debug functions available globally for testing
window.debugFilters = debugFilters;
window.debugNestedFiltering = debugNestedFiltering;
window.debugStatusExtraction = debugStatusExtraction;
window.testStatusFilter = testStatusFilter;


// Replace your current filterNestedData function with this complete version
// Fixed filterNestedData function with proper pagination handling
function filterNestedData(month) {
    const nestedContainer = document.getElementById('nested-data-' + month);
    const nestedRows = nestedContainer ? nestedContainer.querySelectorAll('.compact-row') : [];
    let hasVisibleRows = false;
    let totalMatches = 0;

    // Remove any existing no results message
    const nestedContainerEl = document.getElementById('nested-data-' + month);
    const existingNoResults = nestedContainerEl ? nestedContainerEl.querySelector('.no-nested-results') : null;
    if (existingNoResults) existingNoResults.remove();

    // First pass: count total matches and set visibility
    nestedRows.forEach((row, index) => {
        // Extract row data from the HTML or use original data if available
        let rowData;
        if (window.groupedRows && window.groupedRows[month] && window.groupedRows[month][index]) {
            rowData = window.groupedRows[month][index];
        } else {
            rowData = extractRowData(row, month);
        }

        const matches = matchesFilters(rowData);

        if (matches) {
            totalMatches++;
            row.dataset.filterMatch = 'true'; // Mark as matching filter
        } else {
            row.dataset.filterMatch = 'false'; // Mark as not matching filter
        }
    });

    // Reset to first page when filtering changes
    nestedCurrentPages[month] = 1;

    // Update pagination with the new filtered count
    updateNestedPagination(month);

    // Show a message if no rows are visible
    if (totalMatches === 0) {
        const tbody = document.getElementById('nested-data-' + month);
        if (tbody) {
            const noResultsRow = document.createElement('tr');
            noResultsRow.className = 'no-nested-results';
            noResultsRow.innerHTML = '<td colspan="6" style="text-align: center; padding: 1rem;">No bookings match your filters</td>';
            tbody.appendChild(noResultsRow);
        }
    }
}

// Add this function to extract row data from HTML
function extractRowData(row, month) {
    const id = row.querySelector('td:first-child .badge')?.textContent.trim() || '';
    const unit = row.querySelector('.unit-cell .badge')?.textContent.trim() || '';
    const type = row.querySelector('.type-cell .badge')?.textContent.trim() || '';
    const customerName = row.querySelector('.customer-name')?.textContent.trim() || '';
    const contactNumber = row.querySelector('.customer-contact')?.textContent.trim() || '';

    // Multiple possible selectors for status badge - try them in order
    let status = '';
    const statusSelectors = [
        'td:nth-child(5) .badge',
        '.status-cell .badge',
        '.badge.status',
        '.badge[class*="status"]',
        'td .badge:last-child',
        '.badge'
    ];

    for (const selector of statusSelectors) {
        const statusBadge = row.querySelector(selector);
        if (statusBadge) {
            const text = statusBadge.textContent.trim();
            // Check if this looks like a status (common status values)
            if (text && (
                text.toLowerCase().includes('received') ||
                text.toLowerCase().includes('pending') ||
                text.toLowerCase().includes('cancelled') ||
                text.toLowerCase().includes('confirmed') ||
                text.toLowerCase().includes('processing') ||
                /^[A-Za-z\s]+$/.test(text) // Only contains letters and spaces
            )) {
                status = text;
                break;
            }
        }
    }

    // Fallback: look for any badge in the 5th column
    if (!status) {
        const fifthColumnBadge = row.querySelector('td:nth-child(5)');
        if (fifthColumnBadge) {
            const allBadges = fifthColumnBadge.querySelectorAll('.badge');
            if (allBadges.length > 0) {
                status = allBadges[allBadges.length - 1].textContent.trim();
            }
        }
    }

    // Collect hidden details
    const detailValues = row.querySelectorAll('.customer-info .detail-value');
    const project = detailValues[0]?.textContent.trim() || '';
    const builder = detailValues[1]?.textContent.trim() || '';
    const projectType = detailValues[2]?.textContent.trim() || '';
    const bookingDate = detailValues[3]?.textContent.trim() || '';
    const size = detailValues[4]?.textContent.trim() || '';
    const sourceTable = detailValues[6]?.textContent.trim() || '';

    // Email could be in multiple locations
    const emailElement = row.querySelector('.customer-info .detail-text') ||
        row.querySelector('.email-cell') ||
        row.querySelector('[data-email]');
    const emailId = emailElement?.textContent.trim() || emailElement?.getAttribute('data-email') || '';

    // Try to extract additional financial information if present
    const agreementValue = row.querySelector('[data-agreement]')?.getAttribute('data-agreement') ||
        row.querySelector('.agreement-value')?.textContent.replace(/[^0-9]/g, '') || '';
    const commissionPercent = row.querySelector('[data-commission]')?.getAttribute('data-commission') || '';
    const revenue = row.querySelector('[data-revenue]')?.getAttribute('data-revenue') || '';
    const cashbackPercent = row.querySelector('[data-cashback]')?.getAttribute('data-cashback') || '';
    const crevenue = row.querySelector('[data-crevenue]')?.getAttribute('data-crevenue') || '';
    const receivedAmt = row.querySelector('[data-received]')?.getAttribute('data-received') || '';

    // Try to find the original data from groupedRows first
    let originalData = null;
    if (window.groupedRows && window.groupedRows[month]) {
        originalData = window.groupedRows[month].find(item =>
            String(item.id) === id &&
            (item.unit_no === unit || String(item.unit_no) === unit) &&
            (item.customer_name === customerName || String(item.customer_name) === customerName)
        );
    }

    // Return original data if found, otherwise construct from DOM
    return originalData || {
        id,
        unit_no: unit,
        project_type: type,
        customer_name: customerName,
        contact_number: contactNumber,
        astatus: status, // This is the key field for status filtering
        project,
        builder,
        project_type_hidden: projectType, // distinguish from "type"
        booking_date: bookingDate,
        size,
        email_id: emailId,
        source_table: sourceTable,
        agreement_value: agreementValue,
        commission_percent: commissionPercent,
        revenue: revenue,
        cashback_percent: cashbackPercent,
        crevenue: crevenue,
        recived_amt: receivedAmt
    };
}


// Helper to fix -0.00 issue
function formatValue(num) {
    if (Object.is(num, -0)) num = 0; // remove -0
    return num.toFixed(2);
}

// Helper function to format numbers in compact format (K, M, B) - client-side version with 2 decimal places
function formatCompactNumber(number) {
    if (number >= 1000000000) {
        return '₹' + (number / 1000000000).toFixed(2) + 'B';
    } else if (number >= 1000000) {
        return '₹' + (number / 1000000).toFixed(2) + 'M';
    } else if (number >= 1000) {
        return '₹' + (number / 1000).toFixed(2) + 'K';
    } else {
        return '₹' + number.toLocaleString();
    }
}

// Helper function to get full rupee value without abbreviation (for tooltips)
function getFullRupeeValue(number) {
    return '₹' + number.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
window.getFullRupeeValue = getFullRupeeValue;

// Make formatCompactNumber available globally for use in other scripts
window.formatCompactNumber = formatCompactNumber;

// Function to show dashboard clear filter button for bookings
function showDashboardClearFilterButtonForBookings() {
    // Skip creating this button when embedded (superadmin wrapper handles its own clear button)
    if (window.self !== window.top) {
        return;
    }

    // Check if button already exists
    if (document.getElementById('dashboardClearFilterBtnBookings')) {
        return;
    }

    // Create the floating clear filter button
    const clearBtn = document.createElement('div');
    clearBtn.id = 'dashboardClearFilterBtnBookings';
    clearBtn.className = 'dashboard-clear-filter-btn-bookings';
    clearBtn.innerHTML = `
        <button class="clear-filter-btn" title="Clear Dashboard Filters">
            <i class="fas fa-times"></i>
            <span>Clear Filters</span>
        </button>
    `;

    // Add click handler
    clearBtn.addEventListener('click', clearDashboardFiltersForBookings);

    // Add to body
    document.body.appendChild(clearBtn);

    // Add CSS styles
    addClearFilterButtonStylesForBookings();
}

// Function to clear dashboard filters for bookings
function clearDashboardFiltersForBookings() {
    // Clear any applied date filters by resetting the view
    // Reset any filters that might be applied
    if (typeof clearFilters === 'function') {
        clearFilters();
    }

    // Show all months again
    const monthlyRows = document.querySelectorAll('#mainTableBody .table-row[onclick]');
    monthlyRows.forEach(row => {
        row.style.display = 'table-row';
    });

    // Reset main pagination
    mainCurrentPage = 1;
    if (typeof applyMainPagination === 'function') {
        applyMainPagination();
    }

    // Remove the clear filter button
    const clearBtn = document.getElementById('dashboardClearFilterBtnBookings');
    if (clearBtn) {
        clearBtn.remove();
    }

    // Check if we need to reload the page to clear server-side user filters
    const urlParams = new URLSearchParams(window.location.search);
    const hasUserFilter = urlParams.get('filterUser') && urlParams.get('managerView');

    if (hasUserFilter) {
        // If user filter was applied, reload the page without parameters to reset server-side filtering
        console.log('Reloading page to clear server-side user filters');
        window.location.href = window.location.pathname;
    } else {
        // Just update URL to remove filter parameters
        const url = new URL(window.location);
        url.searchParams.delete('start_date');
        url.searchParams.delete('end_date');
        url.searchParams.delete('filterUser');
        url.searchParams.delete('managerView');
        window.history.replaceState({}, document.title, url.pathname);

        // Show success notification
        if (typeof showNotification === 'function') {
            showNotification('All dashboard filters cleared', 'success');
        }
    }
}

// Function to add CSS styles for the clear filter button for bookings
function addClearFilterButtonStylesForBookings() {
    if (document.getElementById('dashboardClearFilterStylesBookings')) {
        return;
    }

    const styles = document.createElement('style');
    styles.id = 'dashboardClearFilterStylesBookings';
    styles.textContent = `
        .dashboard-clear-filter-btn-bookings {
            position: fixed;
            bottom: 100px;
            right: 20px;
            z-index: 1000;
            animation: slideInRight 0.3s ease-out;
        }

        .dashboard-clear-filter-btn-bookings .clear-filter-btn {
            background: #ef4444;
            color: white;
            border: none;
            border-radius: 25px;
            padding: 8px 16px;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }

        .dashboard-clear-filter-btn-bookings .clear-filter-btn:hover {
            background: #dc2626;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
            transform: translateY(-1px);
        }

        .dashboard-clear-filter-btn-bookings .clear-filter-btn:active {
            transform: translateY(0);
        }

        .dashboard-clear-filter-btn-bookings .clear-filter-btn i {
            font-size: 10px;
        }

        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @media (max-width: 768px) {
            .dashboard-clear-filter-btn-bookings {
                bottom: 80px;
                right: 15px;
            }
            
            .dashboard-clear-filter-btn-bookings .clear-filter-btn {
                padding: 6px 12px;
                font-size: 11px;
            }
            
            .dashboard-clear-filter-btn-bookings .clear-filter-btn span {
                display: none;
            }
            
            .dashboard-clear-filter-btn-bookings .clear-filter-btn i {
                font-size: 12px;
            }
        }
    `;

    document.head.appendChild(styles);
}

document.addEventListener("DOMContentLoaded", function () {
    // Target date & month inputs
    const dateInputs = [
        "filterBookingDateStart",
        "filterBookingDateEnd",
        "filterMonth"
    ];

    dateInputs.forEach(id => {
        const input = document.getElementById(id);
        if (input) {
            const fieldset = input.closest(".fieldset-label");
            if (fieldset) {
                fieldset.addEventListener("click", () => {
                    input.showPicker ? input.showPicker() : input.focus();
                });
            }
        }
    });
});


// Toggle sidebar for mobile
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.querySelector('.overlay');
    const menuBtn = document.querySelector('.mobile-menu-btn');
    sidebar.classList.toggle('open');
    overlay.classList.toggle('active');
    menuBtn.classList.toggle('hidden', sidebar.classList.contains('open'));
}

function closeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.querySelector('.overlay');
    const menuBtn = document.querySelector('.mobile-menu-btn');
    sidebar.classList.remove('open');
    overlay.classList.remove('active');
    menuBtn.classList.remove('hidden');
}


// Toggle detail row - with auto-close functionality
function toggleDetail(detailId) {
    const detailRow = document.getElementById(detailId);
    const button = detailRow.previousElementSibling.querySelector('button');

    // Extract month and row ID from detailId
    const [_, month, rowId] = detailId.match(/detail-(.+)-(\d+)/);

    if (detailRow.style.display === 'none') {
        // Close all other detail rows in the same month section
        const allDetailRows = document.querySelectorAll(`[id^="detail-${month}-"]`);
        allDetailRows.forEach(row => {
            if (row.id !== detailId && row.style.display !== 'none') {
                row.style.display = 'none';
                const otherButton = row.previousElementSibling.querySelector('button');
                if (otherButton) otherButton.textContent = 'Show More';

                // Remove from expanded details
                expandedDetails = expandedDetails.filter(id => id !== row.id);
            }
        });

        // Open the requested detail row
        detailRow.style.display = 'table-row';
        button.textContent = 'Show Less';
        expandedDetails.push(detailId);
    } else {
        // Close the requested detail row
        detailRow.style.display = 'none';
        button.textContent = 'Show More';
        expandedDetails = expandedDetails.filter(id => id !== detailId);
    }
}

// Handle nested search
function handleNestedSearch(month, searchTerm) {
    nestedSearchTerms[month] = searchTerm.toLowerCase().trim();
    nestedCurrentPages[month] = 1; // Reset to first page when searching
    updateNestedPagination(month);
}

// Handle per page change
function handlePerPageChange(month, perPage) {
    nestedPerPageValues[month] = parseInt(perPage);
    nestedCurrentPages[month] = 1; // Reset to first page when changing items per page
    updateNestedPagination(month);
}

// Enhanced updateNestedPagination function
function updateNestedPagination(month) {
    if (!window.groupedRows || !window.groupedRows[month]) return;

    const nestedContainer = document.getElementById('nested-data-' + month);
    const rows = nestedContainer ? nestedContainer.querySelectorAll('.compact-row') : [];
    const searchTerm = nestedSearchTerms[month] || '';
    const perPage = nestedPerPageValues[month] || 5;
    const currentPage = nestedCurrentPages[month] || 1;

    let visibleCount = 0;
    let totalMatches = 0;
    const startIndex = (currentPage - 1) * perPage;

    // Remove any existing no results message
    removeNestedNoResultsMessage(month);

    // First pass: count total matches considering BOTH search and filters
    rows.forEach((row, index) => {
        let rowData;

        // Use groupedRows data if available, otherwise extract from DOM
        if (window.groupedRows && window.groupedRows[month] && window.groupedRows[month][index]) {
            rowData = window.groupedRows[month][index];
        } else {
            // Extract data from the DOM element
            rowData = extractRowData(row, month);
        }

        // Check if row matches search term
        let matchesSearch = true;
        if (searchTerm) {
            const visibleText = row.textContent.toLowerCase();
            const dataText = Object.values(rowData).join(' ').toLowerCase();
            matchesSearch = visibleText.includes(searchTerm) || dataText.includes(searchTerm);
        }

        // Check if row matches global filters - when filters are cleared, this should be true
        const matchesGlobalFilters = !isFilterApplied || matchesFilters(rowData);

        if (matchesSearch && matchesGlobalFilters) {
            totalMatches++;
        }
    });

    // If no search term and no filters applied, show all rows
    if (!searchTerm && !isFilterApplied) {
        totalMatches = rows.length;
    }

    // Second pass: apply pagination to visible rows
    let visibleRowIndex = 0;
    rows.forEach((row, index) => {
        let rowData;

        // Use groupedRows data if available, otherwise extract from DOM
        if (window.groupedRows && window.groupedRows[month] && window.groupedRows[month][index]) {
            rowData = window.groupedRows[month][index];
        } else {
            // Extract data from the DOM element
            rowData = extractRowData(row, month);
        }

        // Check if row matches search term
        let matchesSearch = true;
        if (searchTerm) {
            const visibleText = row.textContent.toLowerCase();
            const dataText = Object.values(rowData).join(' ').toLowerCase();
            matchesSearch = visibleText.includes(searchTerm) || dataText.includes(searchTerm);
        }

        // Check if row matches global filters
        const matchesGlobalFilters = !isFilterApplied || matchesFilters(rowData);

        const shouldShow = matchesSearch && matchesGlobalFilters;

        if (shouldShow) {
            // Check if this row should be visible on current page
            if (visibleRowIndex >= startIndex && visibleRowIndex < startIndex + perPage) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
            visibleRowIndex++;
        } else {
            row.style.display = 'none';
        }
    });

    // Update pagination controls
    updatePaginationControls(month, totalMatches, currentPage, perPage);

    // Update showing info
    updateShowingInfo(month, totalMatches, currentPage, perPage);

    // Show no results message if needed (but not when filters are cleared)
    if (totalMatches === 0 && (searchTerm || isFilterApplied)) {
        showNestedNoResultsMessage(month);
    }
}

// Helper function to show toast notification
function showToast(message) {
    const existingToast = document.getElementById('toast');
    if (existingToast) {
        existingToast.textContent = message;
        existingToast.style.display = 'block';

        setTimeout(() => {
            existingToast.style.display = 'none';
        }, 2000);
    } else {
        // Create toast if it doesn't exist
        const toast = document.createElement('div');
        toast.id = 'toast';
        toast.textContent = message;
        toast.style.cssText = 'position:fixed;right:16px;bottom:16px;background:#10b981;color:#fff;padding:10px 14px;border-radius:8px;box-shadow:0 8px 24px rgba(2,6,23,.18);z-index:120';
        document.body.appendChild(toast);

        setTimeout(() => {
            toast.style.display = 'none';
        }, 2000);
    }
}

Object.keys(nestedCurrentPages).forEach(month => {
    nestedCurrentPages[month] = 1; // Reset to first page
    updateNestedPagination(month); // Update pagination
});



// Update pagination controls
function updatePaginationControls(month, totalMatches, currentPage, perPage) {
    const totalPages = Math.ceil(totalMatches / perPage);
    const pageNumbers = document.getElementById(`page-numbers-${month}`);
    const prevBtn = document.getElementById(`prev-btn-${month}`);
    const nextBtn = document.getElementById(`next-btn-${month}`);

    if (!pageNumbers || !prevBtn || !nextBtn) return;

    // Update previous/next buttons
    prevBtn.disabled = currentPage === 1;
    nextBtn.disabled = currentPage === totalPages || totalPages === 0;

    // Rebuild page numbers to guarantee a compact set
    pageNumbers.innerHTML = '';

    if (totalPages === 0) {
        pageNumbers.innerHTML = '<span class="no-pages">No pages</span>';
        return;
    }

    const addPageBtn = (page) => {
        const pageBtn = document.createElement('button');
        pageBtn.className = `btn btn-sm ${page === currentPage ? 'btn-primary active-page' : 'btn-outline'}`;
        pageBtn.textContent = page;
        pageBtn.onclick = () => goToNestedPage(month, page);
        pageNumbers.appendChild(pageBtn);
    };

    const addEllipsis = () => {
        const ellipsis = document.createElement('span');
        ellipsis.className = 'pagination-ellipsis';
        ellipsis.textContent = '..';
        ellipsis.style.padding = '0 8px';
        ellipsis.style.color = '#666';
        ellipsis.style.display = 'inline-flex';
        ellipsis.style.alignItems = 'center';
        pageNumbers.appendChild(ellipsis);
    };

    if (totalPages <= 3) {
        for (let i = 1; i <= totalPages; i++) addPageBtn(i);
    } else {
        addPageBtn(1);
        if (currentPage > 2) addEllipsis();
        if (currentPage !== 1 && currentPage !== totalPages) addPageBtn(currentPage);
        if (currentPage < totalPages - 1) addEllipsis();
        addPageBtn(totalPages);
    }
}

function showNestedNoResultsMessage(month) {
    removeNestedNoResultsMessage(month); // Remove existing first

    const tbody = document.getElementById('nested-data-' + month);
    if (tbody) {
        const noResultsRow = document.createElement('tr');
        noResultsRow.className = 'no-nested-results';
        noResultsRow.innerHTML = '<td colspan="6" style="text-align: center; padding: 1rem; color: #64748b; font-style: italic;">No bookings match your search criteria.</td>';
        tbody.appendChild(noResultsRow);
    }
}

function removeNestedNoResultsMessage(month) {
    const existingMessage = (function () { const c = document.getElementById('nested-data-' + month); return c ? c.querySelector('.no-nested-results') : null; })();
    if (existingMessage) {
        existingMessage.remove();
    }
}


// Update showing information
function updateShowingInfo(month, totalMatches, currentPage, perPage) {
    const startElement = document.getElementById(`showing-start-${month}`);
    const endElement = document.getElementById(`showing-end-${month}`);
    const totalElement = document.getElementById(`total-records-${month}`);

    if (!startElement || !endElement || !totalElement) return;

    const start = totalMatches === 0 ? 0 : (currentPage - 1) * perPage + 1;
    const end = Math.min(currentPage * perPage, totalMatches);

    startElement.textContent = start;
    endElement.textContent = end;
    totalElement.textContent = totalMatches;
}

// Go to specific page
function goToNestedPage(month, page) {
    nestedCurrentPages[month] = page;
    updateNestedPagination(month);
}

// Handle nested pagination (previous/next)
function handleNestedPagination(month, direction) {
    const currentPage = nestedCurrentPages[month] || 1;
    const perPage = nestedPerPageValues[month] || 5;
    const container = document.getElementById('nested-data-' + month);
    const rows = container ? container.querySelectorAll('.compact-row') : [];

    // Count visible rows for this month
    let totalMatches = 0;
    rows.forEach((row, index) => {
        if (window.groupedRows && window.groupedRows[month] && window.groupedRows[month][index]) {
            const rowData = window.groupedRows[month][index];
            const text = row.textContent.toLowerCase();
            const searchTerm = nestedSearchTerms[month] || '';

            const matchesSearch = !searchTerm || text.includes(searchTerm);
            const matchesGlobalFilters = !isFilterApplied || matchesFilters(rowData);

            if (matchesSearch && matchesGlobalFilters) {
                totalMatches++;
            }
        }
    });

    const totalPages = Math.ceil(totalMatches / perPage);

    if (direction === 'prev' && currentPage > 1) {
        nestedCurrentPages[month] = currentPage - 1;
    } else if (direction === 'next' && currentPage < totalPages) {
        nestedCurrentPages[month] = currentPage + 1;
    }

    updateNestedPagination(month);
}

// Fixed toggleRow function - Remove the problematic applyMainPagination call
function toggleRow(month) {
    const row = document.getElementById(`nested-${month}`);
    const icon = document.getElementById(`expand-${month}`);

    if (!row || !icon) return;

    const isHidden = window.getComputedStyle(row).display === 'none';

    if (isHidden) {
        // Close all other expanded rows
        expandedRows.forEach(expandedMonth => {
            if (expandedMonth !== month) {
                const otherRow = document.getElementById(`nested-${expandedMonth}`);
                const otherIcon = document.getElementById(`expand-${expandedMonth}`);
                if (otherRow && otherIcon) {
                    otherRow.style.display = 'none';
                    otherIcon.classList.remove('rotated');
                }
            }
        });

        // Update expanded rows array
        expandedRows = [month];

        // Open the requested row
        row.style.display = 'table-row';
        icon.classList.add('rotated');

        // // Apply the current main search term to nested rows
        // const searchEl = document.getElementById('mainSearch')
        //     || document.querySelector('.search-input')
        //     || document.querySelector('input[placeholder*="Search" i]');
        // const currentSearch = (searchEl?.value || '').toLowerCase().trim();
        // nestedSearchTerms[month] = currentSearch;

        // // Apply pagination to the nested table only - DO NOT call applyMainPagination
        // updateNestedPagination(month);

        // // If there's an active search, apply it to the newly opened nested table
        // if (currentSearch && typeof handleMainSearch === 'function') {
        //     // Apply search to nested table without affecting main pagination
        //     handleNestedSearch(month, currentSearch);
        // }
    } else {
        // Close the requested row
        row.style.display = 'none';
        icon.classList.remove('rotated');
        expandedDetails = expandedDetails.filter(id => !id.includes(month));
        expandedRows = expandedRows.filter(m => m !== month);
    }
}
// Filter nested table
function filterNestedTable(month, searchTerm) {
    const container = document.getElementById('nested-data-' + month);
    const rows = container ? container.querySelectorAll('.compact-row') : [];
    const perPage = nestedPerPageValues[month] || 5;
    let visibleCount = 0;

    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        if (text.includes(searchTerm.toLowerCase()) && visibleCount < perPage) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
}

// Mobile detection
function isMobile() {
    return window.innerWidth <= 1023;
}

// Mobile detail modal functions
function showMobileDetailModal(detailId) {
    const detailRow = document.getElementById(detailId);
    if (!detailRow) return;

    const expandedDetails = detailRow.querySelector('.expanded-details');

    if (expandedDetails) {
        // Clone the expanded details content
        const modalBody = document.getElementById('mobileModalBody');
        modalBody.innerHTML = expandedDetails.innerHTML;

        // Show the modal
        const modal = document.getElementById('mobileDetailModal');
        modal.classList.add('active');

        // Prevent body scroll
        document.body.style.overflow = 'hidden';
    }
}

function closeMobileModal() {
    const modal = document.getElementById('mobileDetailModal');
    modal.classList.remove('active');

    // Restore body scroll
    document.body.style.overflow = '';
}

// Close modal on outside click
{
    const el = document.getElementById('mobileDetailModal');
    if (el) {
        el.addEventListener('click', function (e) {
            if (e.target === this) {
                closeMobileModal();
            }
        });
    }
}

// Close modals with Escape key
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        closeFilterModal();
        closeAddBookingModal();
    }
});

// Handle window resize
window.addEventListener('resize', function () {
    if (!isMobile()) {
        closeMobileModal();
    }
});

// Modified toggleDetail function to handle mobile view
// BUT: Don't define this function if we're in embedded mode (it's defined in bookings_superadmin.php)
if (!window.location.search.includes('embed=1')) {
    function toggleDetail(detailId) {
        if (isMobile()) {
            // On mobile, show the modal instead of expanding the row
            showMobileDetailModal(detailId);
            return;
        }

        // Original desktop functionality
        const detailRow = document.getElementById(detailId);
        const button = detailRow.previousElementSibling.querySelector('button');

        // Extract month and row ID from detailId
        const [_, month, rowId] = detailId.match(/detail-(.+)-(\d+)/);

        if (detailRow.style.display === 'none') {
            // Close all other detail rows in the same month section
            const allDetailRows = document.querySelectorAll(`[id^="detail-${month}-"]`);
            allDetailRows.forEach(row => {
                if (row.id !== detailId && row.style.display !== 'none') {
                    row.style.display = 'none';
                    const otherButton = row.previousElementSibling.querySelector('button');
                    if (otherButton) otherButton.textContent = 'Show More';

                    // Remove from expanded details
                    expandedDetails = expandedDetails.filter(id => id !== row.id);
                }
            });

            // Open the requested detail row
            detailRow.style.display = 'table-row';
            button.textContent = 'Show Less';
            expandedDetails.push(detailId);
        } else {
            // Close the requested detail row
            detailRow.style.display = 'none';
            button.textContent = 'Show More';
            expandedDetails = expandedDetails.filter(id => id !== detailId);
        }
    }
}


// Update the button click handler to use the appropriate function
// BUT: Don't override onclick if we're in embedded mode (superadmin iframe)
document.addEventListener('DOMContentLoaded', function () {
    // Check if we're in embedded mode (iframe) - if so, skip this
    const isEmbedded = window.location.search.includes('embed=1');

    if (!isEmbedded) {
        // Update all "Show More" buttons to use the appropriate function
        document.querySelectorAll('.btn-toggle').forEach(button => {
            // Only override if button has an onclick attribute
            const onclickAttr = button.getAttribute('onclick');
            if (onclickAttr) {
                const match = onclickAttr.match(/toggleDetail\('([^']+)'/);
                if (match && match[1]) {
                    button.onclick = function (e) {
                        e.stopPropagation();
                        toggleDetail(match[1]);
                    };
                }
            }
        });
    }
});

document.addEventListener("DOMContentLoaded", function () {
    const bookingDate = document.getElementById("bookingDate");
    if (bookingDate) {
        bookingDate.addEventListener("click", function () {
            if (this.showPicker) {
                this.showPicker();
            }
        });
    }
});


function updateBookingMonth() {
    const dateInput = document.getElementById('bdateo');
    const monthInput = document.getElementById('bmontho');

    if (dateInput && monthInput && dateInput.value) {
        const date = new Date(dateInput.value);
        const year = date.getFullYear();
        const month = (date.getMonth() + 1).toString().padStart(2, '0');
        monthInput.value = `${year}-${month}`;
    }
}

// Enhanced file handling
function handleFileSelect(files) {
    const filePreview = document.getElementById('filePreview');
    if (!filePreview || !files || files.length === 0) return;

    // Clear previous preview
    filePreview.innerHTML = '';

    Array.from(files).forEach((file, index) => {
        const fileItem = document.createElement('div');
        fileItem.className = 'file-item';
        fileItem.innerHTML = `
            <div class="file-info">
                <span class="file-name">${file.name}</span>
                <span class="file-size">${formatFileSize(file.size)}</span>
            </div>
            <button type="button" class="remove-file" onclick="removeFile(${index})">×</button>
        `;
        filePreview.appendChild(fileItem);
    });
}

// Format file size
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// Remove file (placeholder - you'll need to implement actual file removal logic)
function removeFile(index) {
    console.log('Remove file at index:', index);
    // Implement file removal logic here
}

// Add event listeners when DOM is loaded
document.addEventListener('DOMContentLoaded', function () {
    // Date input change listener
    const dateInput = document.getElementById('bdateo');
    if (dateInput) {
        dateInput.addEventListener('change', updateBookingMonth);
    }

    // Close modal on overlay click
    const overlay = document.getElementById('addBookingOverlay');
    if (overlay) {
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) {
                closeAddBookingModal();
            }
        });
    }

    // Escape key to close modal
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            const overlay = document.getElementById('addBookingOverlay');
            if (overlay && overlay.style.display !== 'none') {
                closeAddBookingModal();
            }
        }
    });
});


// Close modals when clicking outside
{
    const el = document.getElementById('filterModalOverlay');
    if (el) {
        el.addEventListener('click', function (e) {
            if (e.target === this) {
                closeFilterModal();
            }
        });
    }
}
document.querySelectorAll('.addNewUserModal').forEach(button => {
    button.addEventListener('click', openAddBookingModal);
});



{
    const el = document.getElementById('addNewUserModal');
    if (el) {
        el.addEventListener('click', function (e) {
            if (e.target === this) {
                closeAddBookingModal();
            }
        });
    }
}
function showNotification(message, type = "info") {
    const notification = document.createElement("div");
    notification.className = `notification ${type}`;
    notification.textContent = message;
    document.body.appendChild(notification);

    setTimeout(() => {
        notification.classList.add("show");
        setTimeout(() => {
            notification.classList.remove("show");
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }, 10);
}

// Enhanced notification function with icons and different styles
function showEnhancedNotification(message, type = "info") {
    const notification = document.createElement("div");
    notification.className = `enhanced-notification ${type}`;

    // Add icon based on type
    let icon = '';
    switch (type) {
        case 'success':
            icon = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20,6 9,17 4,12"></polyline></svg>';
            break;
        case 'error':
        case 'duplicate':
            icon = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
            break;
        case 'warning':
            icon = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="m12 17 .01 0"/></svg>';
            break;
        default:
            icon = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="m9,9 6,6"/><path d="m15,9-6,6"/></svg>';
    }

    notification.innerHTML = `
        <div class="notification-content">
            <div class="notification-icon">${icon}</div>
            <span class="notification-message">${message}</span>
        </div>
    `;

    // Add styles if not already added
    if (!document.getElementById('enhanced-notification-styles')) {
        const styles = document.createElement('style');
        styles.id = 'enhanced-notification-styles';
        styles.textContent = `
            .enhanced-notification {
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 12px 16px;
                border-radius: 8px;
                box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
                z-index: 10000;
                transform: translateX(100%);
                transition: transform 0.3s ease, opacity 0.3s ease;
                opacity: 0;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                font-size: 14px;
                min-width: 300px;
                max-width: 400px;
            }
            
            .enhanced-notification.show {
                transform: translateX(0);
                opacity: 1;
            }
            
            .enhanced-notification.success {
                background: #10b981;
                color: white;
                border-left: 4px solid #059669;
            }
            
            .enhanced-notification.error,
            .enhanced-notification.duplicate {
                background: #ef4444;
                color: white;
                border-left: 4px solid #dc2626;
            }
            
            .enhanced-notification.warning {
                background: #f59e0b;
                color: white;
                border-left: 4px solid #d97706;
            }
            
            .enhanced-notification.info {
                background: #3b82f6;
                color: white;
                border-left: 4px solid #2563eb;
            }
            
            .notification-content {
                display: flex;
                align-items: center;
                gap: 10px;
            }
            
            .notification-icon {
                flex-shrink: 0;
            }
            
            .notification-message {
                flex: 1;
                font-weight: 500;
            }
            
            @media (max-width: 480px) {
                .enhanced-notification {
                    right: 10px;
                    left: 10px;
                    min-width: unset;
                    max-width: unset;
                }
            }
        `;
        document.head.appendChild(styles);
    }

    document.body.appendChild(notification);

    setTimeout(() => {
        notification.classList.add("show");
        setTimeout(() => {
            notification.classList.remove("show");
            setTimeout(() => notification.remove(), 300);
        }, 4000);
    }, 10);
}

// Date handling
{
    const el = document.getElementById('bdateo');
    if (el) {
        el.addEventListener('change', function () {
            let selectedDate = this.value;
            if (selectedDate) {
                let dateObj = new Date(selectedDate);
                let month = (dateObj.getMonth() + 1).toString().padStart(2, '0');
                let year = dateObj.getFullYear();
                const target = document.getElementById('bmontho');
                if (target) target.value = `${year}-${month}`;
            }
        });
    }
}

// Add multiple inputs functions
function addNumber() {
    document.getElementById('contactField').value = 'cnumber';
    document.getElementById('addContactTitle').textContent = 'Add Contact Number';
    document.getElementById('addContactLabel').textContent = 'Phone Number';
    document.getElementById('newContactValue').value = '';
    openAddContactModal();
}

function addEmail() {
    document.getElementById('contactField').value = 'cemail';
    document.getElementById('addContactTitle').textContent = 'Add Email Address';
    document.getElementById('addContactLabel').textContent = 'Email Address';
    document.getElementById('newContactValue').value = '';
    openAddContactModal();
}

function addName() {
    document.getElementById('contactField').value = 'cname';
    document.getElementById('addContactTitle').textContent = 'Add Customer Name';
    document.getElementById('addContactLabel').textContent = 'Customer Name';
    document.getElementById('newContactValue').value = '';
    openAddContactModal();
}

// Modal control functions
function openAddOptionModal() {
    document.getElementById('addOptionModalOverlay').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeAddOptionModal() {
    document.getElementById('addOptionModalOverlay').style.display = 'none';
    document.body.style.overflow = '';
    document.getElementById('addOptionForm').classList.remove("was-validated");
}

function openAddContactModal() {
    document.getElementById('addContactModalOverlay').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeAddContactModal() {
    document.getElementById('addContactModalOverlay').style.display = 'none';
    document.body.style.overflow = '';
    document.getElementById('addContactForm').classList.remove("was-validated");
}

// Add this function to populate datalists from your HTML options
function initializeDatalists() {
    console.log('Initializing datalists...');

    // Builder names datalist
    const builderOptions = [
        "Prestige Group", "Brigade Group", "Sobha Limited", "Godrej Properties",
        "Puravankara Limited", "Shriram Properties", "Sattva Group", "Salarpuria Sattva",
        "Assetz Property Group", "Embassy Group", "L&T Realty", "Mahaveer Group",
        "Adarsh Developers", "Mahindra Lifespaces", "Neeladri Properties", "Ranav Group",
        "Amber Meadows", "Ramky Group", "Arvind Smart Spaces", "Goyal & Co."
    ];

    // Project names datalist  
    const projectOptions = [
        "Prestige Lakeside Habitat", "Brigade Utopia", "Sobha Dream Acres", "Godrej Air Nxt",
        "Purva Zenium", "Shriram Blue", "Salarpuria Sattva Divinity", "Assetz 63° East",
        "Embassy Lake Terraces", "L&T Raintree Boulevard", "Mahaveer Ranches", "Adarsh Palm Retreat",
        "Mahindra Eden", "Neeladri Sarovaram", "Ramky One Karnival", "Arvind Bel Air",
        "Goyal Orchid Whitefield"
    ];

    // Project types datalist
    const typeOptions = [
        "1-BHK", "2-BHK", "3-BHK", "4-BHK", "5-BHK", "Villa", "Plot"
    ];

    // Populate the datalists
    populateDatalist('developera-list', builderOptions);
    populateDatalist('bprojecta-list', projectOptions);
    populateDatalist('tprojecta-list', typeOptions);

    console.log('Datalists initialized successfully');
}

// Helper function to populate a datalist
function populateDatalist(datalistId, options) {
    const datalist = document.getElementById(datalistId);
    if (!datalist) {
        // Silently skip if datalist doesn't exist (expected on some pages)
        return;
    }

    // Clear existing options
    datalist.innerHTML = '';

    // Add new options
    options.forEach(option => {
        const optElement = document.createElement('option');
        optElement.value = option;
        datalist.appendChild(optElement);
    });

    console.log(`Populated ${datalistId} with ${options.length} options`);
}

// Add this to your DOMContentLoaded event listener
document.addEventListener('DOMContentLoaded', function () {
    console.log('DOM Content Loaded - Initializing application');
    const loaderContainer = document.querySelector(".loader-container");
    if (loaderContainer) loaderContainer.style.display = "flex";
    // Initialize datalists
    initializeDatalists();

    // Your existing initialization code
    initializeOriginalData();
    setupEventListeners();
    initializePerPageValues();
    // const loaderContainer = document.querySelector(".loader-container");
    if (loaderContainer) loaderContainer.style.display = "none";
    console.log('Application initialization complete');
});

// Add option functionality
function addOption(type) {
    document.getElementById('optionType').value = type;

    // Set modal title and label based on type
    const titles = {
        'developera': 'Add New Builder',
        'bprojecta': 'Add New Project',
        'tprojecta': 'Add New Project Type'
    };

    const labels = {
        'developera': 'Builder Name',
        'bprojecta': 'Project Name',
        'tprojecta': 'Project Type'
    };

    document.getElementById('addOptionTitle').textContent = titles[type] || 'Add New Option';
    document.getElementById('addOptionLabel').textContent = labels[type] || 'Option Value';
    document.getElementById('newOptionValue').value = '';

    openAddOptionModal();
}


// Load datalist options (if you have existing data)
function loadDatalist(type) {
    // This would typically fetch from your server
    // For now, adding some sample options
    const sampleData = {
        'developera': ['Builder 1', 'Builder 2', 'Builder 3'],
        'bprojecta': ['Project A', 'Project B', 'Project C'],
        'tprojecta': ['2BHK', '3BHK', '4BHK', 'Villa']
    };

    const list = document.getElementById(`${type}-list`);
    if (list && sampleData[type]) {
        list.innerHTML = '';
        sampleData[type].forEach(val => {
            const opt = document.createElement('option');
            opt.value = val;
            list.appendChild(opt);
        });
    }
}

// Initialize datalists on page load
document.addEventListener('DOMContentLoaded', function () {
    ['developera', 'bprojecta', 'tprojecta'].forEach(loadDatalist);

    // Note: Calculation event listeners are now handled in the main calculation section
    // This prevents duplicate listeners and conflicts
});





// Helpers
const $ = (s, r = document) => r.querySelector(s); const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));

// Modal with focus trap
const addBookingOverlay = $('#addBookingModalOverlay');
let lastFocused = null;

function openAddBookingModal() {
    if (!addBookingOverlay) return;
    lastFocused = document.activeElement;
    addBookingOverlay.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    setTimeout(() => $('#add-booking-form')?.focus(), 0);
}

function closeAddBookingModal() {
    if (!addBookingOverlay) return;
    addBookingOverlay.style.display = 'none';
    document.body.style.overflow = '';
    lastFocused?.focus();
    const form = $('#add-booking-form');
    if (form) form.classList.remove("was-validated");
    // Reset file uploader fully
    try { if (Array.isArray(filesState)) filesState.length = 0; } catch(e) {}
    if (typeof renderList === 'function') renderList();
    const fi = document.getElementById('fileInput');
    if (fi) fi.value = '';
}

// Close modal when clicking outside
if (addBookingOverlay) {
    addBookingOverlay.addEventListener('click', e => {
        if (e.target === addBookingOverlay) closeAddBookingModal();
    });
}

// Close modal with Escape key
document.addEventListener('keydown', e => {
    if (addBookingOverlay && addBookingOverlay.style.display === 'flex' && e.key === 'Escape') closeAddBookingModal();
});

// Booking Month auto-fill
const bookingDate = $('#bookingDate'), bookingMonth = $('#bookingMonth');
bookingDate.addEventListener('change', () => {
    if (!bookingDate.value) { bookingMonth.value = ''; return; }
    const d = new Date(bookingDate.value + 'T00:00:00');
    bookingMonth.value = d.toLocaleString(undefined, { month: 'long', year: 'numeric' });
});

// Datalist +Add persistence
const storeKey = 'booking-datalists';
const store = JSON.parse(localStorage.getItem(storeKey) || '{}');

function hydrateList(id) {
    const list = store[id];
    if (!list) return;
    const dl = document.getElementById(id);
    const ex = new Set($$(`#${id} option`).map(o => o.value || o.textContent));
    list.forEach(v => {
        if (!ex.has(v)) {
            const o = document.createElement('option');
            o.value = v;
            dl.appendChild(o);
        }
    });
}

['builderList', 'projectList', 'ptypeList'].forEach(hydrateList);

function persistList(id) {
    const options = $$(`#${id} option`).map(o => o.value || o.textContent);
    store[id] = Array.from(new Set([...(store[id] || []), ...options]));
    localStorage.setItem(storeKey, JSON.stringify(store));
}

$$('.add-btn').forEach(btn => {
    btn.addEventListener('click', () => openMiniAdd(btn.dataset.add));
});

// Mini popup functionality
const miniOverlay = $('#miniOverlay'), miniTitle = $('#miniTitle'),
    miniLabel = $('#miniLabel'), miniInput = $('#miniInput'),
    optionType = $('#optionType');
let miniTarget = null;

const addMap = {
    builder: { input: '#builderName', list: 'builderList', label: 'New builder name' },
    project: { input: '#projectName', list: 'projectList', label: 'New project name' },
    ptype: { input: '#projectType', list: 'ptypeList', label: 'New project type' },
    customer: { input: '#customerName', list: null, label: 'New customer name' },
    contact: { input: '#contactNo', list: null, label: 'New contact' },
    email: { input: '#email', list: null, label: 'Enter email address' }
};

function openMiniAdd(key) {
    const cfg = addMap[key];
    if (!cfg) return;
    miniTarget = cfg;
    miniTitle.textContent = cfg.label;
    miniLabel.textContent = cfg.label;
    miniInput.value = '';
    miniOverlay.style.display = 'flex';
    setTimeout(() => miniInput.focus(), 20);
}

function closeMini() {
    miniOverlay.style.display = 'none';
    miniTarget = null;
}

function submitOption() {
    if (!miniTarget) return;
    const val = miniInput.value.trim();
    if (!val) return miniInput.focus();

    if (miniTarget.list) {
        const dl = document.getElementById(miniTarget.list);
        const opt = document.createElement('option');
        opt.value = val;
        dl.appendChild(opt);
        persistList(miniTarget.list);
    }

    const inputEl = $(miniTarget.input);
    if (inputEl) {
        const currentValue = inputEl.value.trim();
        if (currentValue) {
            inputEl.value = currentValue + ', ' + val;
        } else {
            inputEl.value = val;
        }
    }
    closeMini();
}

// Custom Lead Source dropdown logic
(function () {
    const leadSelectWrap = $('#leadSelect'), leadBtn = $('#leadBtn'),
        leadOptions = $('#leadOptions'), leadValue = $('#leadValue'),
        hiddenSelect = $('#leadSource');

    if (!leadSelectWrap) return;

    function openLead() {
        leadSelectWrap.classList.add('open');
        leadSelectWrap.setAttribute('aria-expanded', 'true');
        leadOptions.focus();
    }

    function closeLead() {
        leadSelectWrap.classList.remove('open');
        leadSelectWrap.setAttribute('aria-expanded', 'false');
    }

    leadBtn.addEventListener('click', () => {
        if (leadSelectWrap.classList.contains('open')) closeLead();
        else openLead();
    });

    document.addEventListener('click', e => {
        if (!leadSelectWrap.contains(e.target)) closeLead();
    });

    // option click
    $$('.lead-option', leadOptions).forEach(li => {
        li.addEventListener('click', () => {
            const v = li.dataset.value;
            leadValue.textContent = li.textContent;
            hiddenSelect.value = v;
            $$('.lead-option', leadOptions).forEach(x => x.removeAttribute('aria-selected'));
            li.setAttribute('aria-selected', 'true');
            closeLead();
        });
    });

    // keyboard navigation
    leadBtn.addEventListener('keydown', e => {
        if (e.key === 'ArrowDown') {
            openLead();
            const first = $('.lead-option', leadOptions);
            first?.focus();
        }
    });

    leadOptions.addEventListener('keydown', e => {
        const focused = document.activeElement;
        if (!focused.classList.contains('lead-option')) return;

        if (e.key === 'ArrowDown') {
            const next = focused.nextElementSibling;
            next?.focus();
            e.preventDefault();
        }

        if (e.key === 'ArrowUp') {
            const prev = focused.previousElementSibling;
            prev?.focus();
            e.preventDefault();
        }

        if (e.key === 'Enter') {
            focused.click();
        }

        if (e.key === 'Escape') {
            closeLead();
            leadBtn.focus();
        }
    });
})();

// Uploader + global drop
const uploader = $('#uploader'), fileInput = $('#fileInput'),
    browseBtn = $('#browseBtn'), fileList = $('#fileList'),
    globalDrop = $('#globalDrop');
const filesState = [];
const MAX_FILES = 1;

function formatSize(bytes) {
    const u = ['B', 'KB', 'MB', 'GB'];
    let i = 0, v = bytes;
    while (v >= 1024 && i < u.length - 1) {
        v /= 1024;
        i++;
    }
    return v.toFixed(v < 10 && i > 0 ? 1 : 0) + ' ' + u[i];
}

if (fileInput) {
    fileInput.accept = '.pdf,application/pdf';
}


function renderList() {
    const prompt = $('#uploadPrompt');
    if (filesState.length > 0) {
        uploader.classList.add('has-files');
        if (prompt) prompt.style.display = 'none';
        fileList.style.marginTop = '0';
        fileList.style.width = '100%';
        fileList.style.boxSizing = 'border-box';
    } else {
        uploader.classList.remove('has-files');
        if (prompt) prompt.style.display = 'flex';
        fileList.style.marginTop = '8px';
        fileList.style.width = '';
        fileList.style.boxSizing = '';
    }
    fileList.innerHTML = '';
    filesState.forEach(item => {
        const row = document.createElement('div');
        row.className = 'file-item';
        row.dataset.id = item.id;
        row.style.width = '100%';
        row.style.boxSizing = 'border-box';
        row.style.overflow = 'hidden';

        const thumb = document.createElement('div');
        thumb.className = 'file-thumb pdf-thumb'; // Add pdf-thumb class

        // Show PDF icon for PDF files
        thumb.innerHTML = '<svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="1"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><path d="M10 13.5V12h4v4h-1.5"/><path d="M10 12h1.5V16H10z"/></svg>';

        const meta = document.createElement('div');
        meta.className = 'file-meta';

        const name = document.createElement('div');
        name.className = 'name';
        name.textContent = item.file.name;

        const sub = document.createElement('div');
        sub.className = 'sub';
        sub.textContent = formatSize(item.file.size);

        const prog = document.createElement('div');
        prog.className = 'progress';
        const bar = document.createElement('span');
        bar.style.width = item.progress + '%';
        prog.appendChild(bar);

        meta.appendChild(name);
        meta.appendChild(sub);
        meta.appendChild(prog);

        const rm = document.createElement('button');
        rm.className = 'remove';
        rm.setAttribute('aria-label', 'Remove file');
        rm.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
        rm.title = 'Remove file';

        rm.addEventListener('click', () => {
            const idx = filesState.findIndex(f => f.id === item.id);
            if (idx > -1) {
                filesState.splice(idx, 1);
                fileInput.value = ''; // Clear actual input so same file can be selected again
                renderList();
            }
        });

        row.appendChild(thumb);
        row.appendChild(meta);
        row.appendChild(rm);
        fileList.appendChild(row);
    });
}


function simulateUpload(item) {
    const timer = setInterval(() => {
        item.progress = Math.min(100, item.progress + 10 + Math.random() * 10);
        renderList();
        if (item.progress >= 100) clearInterval(timer);
    }, 120);
}

function acceptFile(file) {
    // Check if file is PDF
    if (file.type !== 'application/pdf') {
        showEnhancedNotification("Only PDF files are allowed", "error");
        return false;
    }

    if (filesState.length >= MAX_FILES) return false;

    const item = {
        id: Math.random().toString(36).slice(2),
        file,
        progress: 0,
        url: undefined
    };

    // For PDFs, we can't generate a preview image, so show a PDF icon instead
    filesState.push(item);
    renderList();
    simulateUpload(item);
    return true;
}

function handleFiles(files) {
    Array.from(files).forEach(acceptFile);
}

// Component drag
['dragenter', 'dragover'].forEach(evt => uploader.addEventListener(evt, e => {
    e.preventDefault();
    uploader.classList.add('dragover');
}));

['dragleave', 'drop'].forEach(evt => uploader.addEventListener(evt, e => {
    e.preventDefault();
    uploader.classList.remove('dragover');
}));

uploader.addEventListener('drop', e => handleFiles(e.dataTransfer.files));

// Browse
browseBtn.addEventListener('click', () => fileInput.click());
fileInput.addEventListener('change', e => handleFiles(e.target.files));

// Global drop-anywhere
let dragCounter = 0;
document.addEventListener('dragenter', () => {
    dragCounter++;
    globalDrop.classList.add('show');
});

document.addEventListener('dragover', e => {
    e.preventDefault();
});

document.addEventListener('dragleave', () => {
    dragCounter = Math.max(0, dragCounter - 1);
    if (dragCounter === 0) globalDrop.classList.remove('show');
});

document.addEventListener('drop', e => {
    e.preventDefault();
    dragCounter = 0;
    globalDrop.classList.remove('show');

    if (e.dataTransfer?.files?.length) {
        if (addBookingOverlay.style.display !== 'flex') openAddBookingModal();
        setTimeout(() => uploader.scrollIntoView({ behavior: 'smooth', block: 'center' }), 50);
        handleFiles(e.dataTransfer.files);
    }
});

// Validation module
(function () {
    // Fields to validate: selector, friendly name, validator function (returns {ok,msg})
    const touched = {};
    const neverWarn = new Set(['remarks']);

    const rules = [
        { key: 'bookingDate', el: '#bookingDate', name: 'Booking date', req: true, validate: v => ({ ok: !!v, msg: 'Please select a booking date.' }) },
        { key: 'builderName', el: '#builderName', name: 'Builder name', req: true, validate: v => ({ ok: !!v && v.trim().length > 1, msg: 'Please provide builder name.' }) },
        { key: 'projectName', el: '#projectName', name: 'Project name', req: true, validate: v => ({ ok: !!v && v.trim().length > 1, msg: 'Please provide project name.' }) },
        { key: 'projectType', el: '#projectType', name: 'Project type', req: true, validate: v => ({ ok: !!v && v.trim().length > 0, msg: 'Please provide project type.' }) },
        { key: 'projectSize', el: '#projectSize', name: 'Project size', req: true, validate: v => ({ ok: !!v && v.trim().length > 0, msg: 'Please provide project size.' }) },
        { key: 'customerName', el: '#customerName', name: 'Customer name', req: true, validate: v => ({ ok: !!v && v.trim().length > 2, msg: 'Please provide customer name.' }) },
        { key: 'contactNo', el: '#contactNo', name: 'Contact number', req: true, validate: v => ({ ok: /^\d{10}$/.test((v || '').replace(/\D/g, '')), msg: 'Please enter exactly 10 digits.' }) },
        {
            key: 'email', el: '#email', name: 'Email', req: true, validate: v => {
                if (!v || !v.trim()) return { ok: false, msg: 'Email is required.' };
                // Support comma-separated emails
                const emails = v.split(',').map(e => e.trim()).filter(e => e);
                const allValid = emails.every(email => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email));
                return { ok: allValid, msg: allValid ? '' : 'Enter valid email address(es).' };
            }
        },
        { key: 'unitNo', el: '#unitNo', name: 'Unit no', req: true, validate: v => ({ ok: !!v && v.trim().length > 0, msg: 'Please provide unit number.' }) },
        { key: 'cityName', el: '#cityName', name: 'City', req: true, validate: v => ({ ok: !!v && v.trim().length > 0, msg: 'Please select a city.' }) },
        { key: 'agreementValue', el: '#agreementValue', name: 'Agreement value', req: true, validate: v => ({ ok: parseFloat(v) > 0, msg: 'Agreement value should be greater than 0.' }) },
        {
            key: 'commissionPct', el: '#commissionPct', name: 'Commission %', req: true, validate: v => {
                const n = parseFloat(v);
                return { ok: !isNaN(n) && n >= 0 && n <= 100, msg: 'Enter commission between 0 and 100.' };
            }
        },
        {
            key: 'cashbackPct', el: '#cashbackPct', name: 'Cashback %', req: true, validate: v => {
                const n = parseFloat(v);
                return { ok: !isNaN(n) && n >= 0 && n <= 100, msg: 'Enter cashback between 0 and 100.' };
            }
        },
        { key: 'leadSelect', el: '#leadSelect', name: 'Lead source', req: true, validate: el => ({ ok: $('#leadSource').value !== '', msg: 'Select a lead source.' }) },
        { key: 'remarks', el: '#remarks', name: 'Remarks', req: false, validate: v => ({ ok: true, msg: '' }) }
    ];

    const saveButton = $('#saveBtn');

    // Reset the form fields and UI to pristine state
    function resetForm() {
        const container = $('#add-booking-form');
        if (!container) return;

        // clear all inputs/selects/textareas inside form
        const els = container.querySelectorAll('input,textarea,select');
        els.forEach(el => {
            if (el.tagName === 'INPUT') {
                const t = el.type;
                if (t === 'checkbox' || t === 'radio') el.checked = false;
                else if (t === 'file') el.value = '';
                else el.value = '';
            } else if (el.tagName === 'TEXTAREA') el.value = '';
            else if (el.tagName === 'SELECT') el.value = '';
        });

        // reset custom lead select display
        const hiddenLead = $('#leadSource');
        if (hiddenLead) hiddenLead.value = '';
        const leadValue = $('#leadValue');
        if (leadValue) leadValue.textContent = 'Select Source';

        // clear any selected aria attributes in lead options
        document.querySelectorAll('.lead-option[aria-selected="true"]').forEach(n => n.removeAttribute('aria-selected'));

        // clear uploader UI and internal state
        try { if (Array.isArray(filesState)) filesState.length = 0; } catch (e) { }
        if (typeof renderList === 'function') renderList(); // restores prompt + removes has-files class

        // clear validation visuals and touched state
        document.querySelectorAll('.field.invalid,.field.valid').forEach(n => n.classList.remove('invalid', 'valid', 'pop'));
        document.querySelectorAll('.status-icon').forEach(n => n.remove());
        document.querySelectorAll('.validation-msg').forEach(n => n.textContent = '');
        try { for (const k in touched) delete touched[k]; } catch (e) { }

        // specific derived fields
        if (bookingMonth) bookingMonth.value = '';
    }

    function getFieldWrapper(el) {
        // find closest .field container
        return el.closest('.field') || el.parentElement;
    }

    function setState(el, ok, msg, options = { show: false }) {
        const wrap = getFieldWrapper(el);
        if (!wrap) return;

        const ariaEl = (el.tagName === 'DIV' && el.classList.contains('lead-select')) ? $('#leadBtn') : el;

        // decide where to place the icon: prefer .input-wrap, else field root
        const targetForIcon = wrap.querySelector('.input-wrap') || wrap.querySelector('.fieldset-label') || wrap;

        // remove only non-hint icons so neutral hint persists until replaced
        const prevIcons = targetForIcon.querySelectorAll('.status-icon');
        prevIcons.forEach(ic => { if (!ic.classList.contains('hint')) ic.remove(); });

        wrap.classList.remove('invalid', 'valid', 'pop');

        if (ok) {
            if (options.show) { // show green only when explicitly told (value present or touched)
                wrap.classList.add('valid');
                ariaEl?.setAttribute('aria-invalid', 'false');
                const ic = document.createElement('span');
                ic.className = 'status-icon ok';
                ic.innerHTML = '✓';
                targetForIcon.appendChild(ic);
            }
        } else {
            ariaEl?.setAttribute('aria-invalid', 'true');

            // only show icon/message if this field is allowed to warn and show flag given
            const id = el.id || '';
            if (options.show && !neverWarn.has(id)) {
                wrap.classList.add('invalid', 'pop');
                const ic = document.createElement('span');
                ic.className = 'status-icon err';
                ic.innerHTML = '!';
                targetForIcon.appendChild(ic);

                let msgEl = wrap.querySelector('.validation-msg');
                if (!msgEl) {
                    msgEl = document.createElement('div');
                    msgEl.className = 'validation-msg';
                    wrap.appendChild(msgEl);
                }
                msgEl.textContent = msg || 'Required';
            } else {
                const msgEl = wrap.querySelector('.validation-msg');
                if (msgEl) msgEl.textContent = '';
            }
        }
    }

    function validateOne(rule, opts = { fromUser: false, showImmediately: false }) {
        const node = $(rule.el);
        if (!node) return true;

        const val = (rule.el === '#leadSelect') ? $('#leadSource').value : (node.value || '').toString();
        const res = rule.validate(val, node);

        // Decide whether to display the error: show when field touched OR when a subsequent field triggered validation
        const wasTouched = !!touched[rule.key];
        const hasValue = (val !== null && val !== undefined && String(val).trim() !== '');

        // For OK state, show green icon only if the field has a value (user filled it)
        let shouldShow;
        if (res.ok) { shouldShow = hasValue; }
        else { shouldShow = wasTouched || opts.showImmediately; }

        setState(node, !!res.ok, res.msg, { show: shouldShow });
        return !!res.ok;
    }

    function validateAll(triggeringKey) {
        let all = true;
        rules.forEach((r, idx) => {
            // if this rule is earlier than triggeringKey and triggeringKey exists, show errors for earlier empty fields
            const showImmediately = triggeringKey ? (rules.findIndex(x => x.key === triggeringKey) > idx) : false;
            const ok = validateOne(r, { showImmediately });
            if (r.req && !ok) all = false;
        });

        if (saveButton) {
            saveButton.disabled = !all;
            saveButton.classList.toggle('primary', all);
        }

        return all;
    }

    // Attach listeners
    rules.forEach(r => {
        const node = $(r.el);
        if (!node) return;

        const inputEl = (r.el === '#leadSelect') ? $('#leadBtn') : node;

        // mark touched on first user interaction
        const markTouched = () => {
            if (!touched[r.key]) {
                touched[r.key] = true;
                validateOne(r, { fromUser: true });
            }
        };

        inputEl.addEventListener('focus', () => { });
        inputEl.addEventListener('blur', () => { markTouched(); validateAll(r.key); });
        inputEl.addEventListener('input', () => { markTouched(); validateOne(r); validateAll(r.key); });

        // special handling for lead-select custom component
        if (r.el === '#leadSelect') {
            const opts = $$('.lead-option');
            opts.forEach(li => li.addEventListener('click', () => {
                touched[r.key] = true;
                setTimeout(() => validateAll(r.key), 10);
            }));
        }
    });

    // when modal opens reset touched so warnings are hidden until user interacts
    document.querySelectorAll('[onclick="openAddBookingModal()"]').forEach(btn => {
        btn.addEventListener('click', () => {
            Object.keys(touched).forEach(k => delete touched[k]);

            // clear any previous states visually
            document.querySelectorAll('.field.invalid,.field.valid').forEach(el => el.classList.remove('invalid', 'valid', 'pop'));
            document.querySelectorAll('.status-icon').forEach(n => n.remove());
            document.querySelectorAll('.validation-msg').forEach(n => n.textContent = '');

            setTimeout(() => validateAll(), 120);
        });
    });

    // prevent save if invalid and show focus on first invalid
    if (saveButton) {
        saveButton.addEventListener('click', (e) => {
            if (!validateAll()) {
                e.preventDefault();
                const firstInvalid = $('.field.invalid');
                if (firstInvalid) {
                    const focusable = firstInvalid.querySelector('input,textarea,button,select');
                    focusable?.focus();
                }
                return;
            }
        });
    }

    // re-run global validation when agreement/commission/cashback change to reflect derived fields
    ['#agreementValue', '#commissionPct', '#cashbackPct'].forEach(s => {
        const n = $(s);
        if (n) n.addEventListener('input', validateAll);
    });

})();



// Add calendar button to booking date field
document.addEventListener('DOMContentLoaded', () => {
    const bookingWrap = $('#bookingDate')?.closest('.fieldset-label') || $('#bookingDate')?.closest('.field');
    const bookingInput = $('#bookingDate');

    if (!bookingInput || !bookingWrap) return;

    // create button if not present
    if (!bookingWrap.querySelector('.date-picker-btn')) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'date-picker-btn';
        btn.setAttribute('aria-label', 'Open calendar');
        btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 11h10M7 15h10M7 7h10M5 3v2M19 3v2M3 7h18v14H3z" stroke="#334155" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>';

        // place it inside the field wrapper so absolute positioning works
        bookingWrap.style.position = bookingWrap.style.position || 'relative';
        bookingWrap.appendChild(btn);

        // clicking should open the native picker where supported
        btn.addEventListener('click', () => {
            // prefer the modern API
            if (typeof bookingInput.showPicker === 'function') {
                bookingInput.showPicker();
            } else {
                // fallback: focus, then dispatch a click so the browser shows the picker where possible
                bookingInput.focus();
                bookingInput.click();
            }
        });
    }
});

// Auto-attach calculation event listeners when DOM is ready
document.addEventListener('DOMContentLoaded', function () {
    // Store previous values to prevent unnecessary recalculations
    const previousValues = {};

    // Flag to prevent recursive calls
    let isCalculating = false;

    // Helper function to check if value actually changed
    function hasValueChanged(element, key) {
        const currentValue = element.value;
        const hasChanged = previousValues[key] !== currentValue;
        previousValues[key] = currentValue;
        return hasChanged;
    }

    // Safe calculation wrapper
    function safeCalculate(calculationFunction, functionName) {
        if (isCalculating) return; // Prevent recursive calls

        try {
            isCalculating = true;
            calculationFunction();
        } catch (error) {
            console.warn(`${functionName} error:`, error);
        } finally {
            isCalculating = false;
        }
    }

    // Helper function to safely attach calculation listeners
    function attachCalculationListener(element, calculationFunction, key, functionName = 'calculation') {
        if (!element) return;

        // Store initial value
        previousValues[key] = element.value || '';

        // Remove any existing listeners to prevent duplicates
        const newElement = element.cloneNode(true);
        element.parentNode.replaceChild(newElement, element);

        // Only use 'input' event for real-time calculation as user types
        newElement.addEventListener('input', function (e) {
            // Only proceed if the event originated from this specific element
            if (e.target !== newElement) return;

            // Prevent event bubbling
            e.stopPropagation();

            // Only calculate if value actually changed
            if (hasValueChanged(newElement, key)) {
                safeCalculate(calculationFunction, functionName);
            }
        }, { passive: true });

        // Use 'blur' event when user leaves the field (more reliable than 'change')
        newElement.addEventListener('blur', function (e) {
            // Only proceed if the event originated from this specific element
            if (e.target !== newElement) return;

            // Prevent event bubbling
            e.stopPropagation();

            // Always recalculate on blur to ensure accuracy
            safeCalculate(calculationFunction, functionName);
        }, { passive: true });

        return newElement;
    }

    // Clear any existing calculation listeners first
    document.querySelectorAll('input[data-calc-listener]').forEach(el => {
        el.removeAttribute('data-calc-listener');
    });

    // Attach listeners for addCalculate() - uses form.myform elements
    if (document.forms.myform) {
        const formAgreement = attachCalculationListener(
            document.forms.myform.cagreement,
            addCalculate,
            'form_agreement',
            'addCalculate'
        );
        const formCommission = attachCalculationListener(
            document.forms.myform.ccashback,
            addCalculate,
            'form_commission',
            'addCalculate'
        );
        const formCashback = attachCalculationListener(
            document.forms.myform.cccashback,
            addCalculate,
            'form_cashback',
            'addCalculate'
        );

        // Mark elements to prevent duplicate listeners
        if (formAgreement) formAgreement.setAttribute('data-calc-listener', 'addCalculate');
        if (formCommission) formCommission.setAttribute('data-calc-listener', 'addCalculate');
        if (formCashback) formCashback.setAttribute('data-calc-listener', 'addCalculate');
    }

    // Attach listeners for updateCalculate() - uses getElementById elements
    const idAgreement = attachCalculationListener(
        document.getElementById('cagreement'),
        updateCalculate,
        'id_agreement',
        'updateCalculate'
    );
    const idCommission = attachCalculationListener(
        document.getElementById('ccashback'),
        updateCalculate,
        'id_commission',
        'updateCalculate'
    );
    const idCashback = attachCalculationListener(
        document.getElementById('cccashback'),
        updateCalculate,
        'id_cashback',
        'updateCalculate'
    );

    // Mark elements to prevent duplicate listeners
    if (idAgreement) idAgreement.setAttribute('data-calc-listener', 'updateCalculate');
    if (idCommission) idCommission.setAttribute('data-calc-listener', 'updateCalculate');
    if (idCashback) idCashback.setAttribute('data-calc-listener', 'updateCalculate');

    // Attach listeners for calculateCashbackRevenue() - uses querySelector elements
    const cbAgreement = attachCalculationListener(
        document.querySelector('input[name="cagreement"]:not([data-calc-listener])'),
        calculateCashbackRevenue,
        'cashback_agreement',
        'calculateCashbackRevenue'
    );
    const cbCashback = attachCalculationListener(
        document.querySelector('input[name="cccashback"]:not([data-calc-listener])'),
        calculateCashbackRevenue,
        'cashback_cashback',
        'calculateCashbackRevenue'
    );

    // Mark elements to prevent duplicate listeners
    if (cbAgreement) cbAgreement.setAttribute('data-calc-listener', 'calculateCashbackRevenue');
    if (cbCashback) cbCashback.setAttribute('data-calc-listener', 'calculateCashbackRevenue');

    // Attach listeners for the generic calculateRevenue() function
    const genAgreement = attachCalculationListener(
        document.querySelector('#agreementValue:not([data-calc-listener])'),
        () => calculateRevenue(),
        'generic_agreement',
        'calculateRevenue'
    );
    const genCommission = attachCalculationListener(
        document.querySelector('#commissionPct:not([data-calc-listener])'),
        () => calculateRevenue(),
        'generic_commission',
        'calculateRevenue'
    );
    const genCashback = attachCalculationListener(
        document.querySelector('#cashbackPct:not([data-calc-listener])'),
        () => calculateRevenue(),
        'generic_cashback',
        'calculateRevenue'
    );

    // Mark elements to prevent duplicate listeners
    if (genAgreement) genAgreement.setAttribute('data-calc-listener', 'calculateRevenue');
    if (genCommission) genCommission.setAttribute('data-calc-listener', 'calculateRevenue');
    if (genCashback) genCashback.setAttribute('data-calc-listener', 'calculateRevenue');

    console.log('Calculation event listeners attached successfully');
});
// ===========================================
// Filter Dropdown Functionality — Server-Side Paginated
// Uses the EXISTING HTML structure (.filter-dropdown-list / .filter-dropdown-options)
// Only the data source changed: fetched from server instead of window.allBookingsData
// ===========================================

(function () {
    // ── Per-wrapper state ─────────────────────────────────────────────────────
    const wrapperState = new WeakMap();
    // Shared pre-warm cache: `${fieldKey}||${page}` -> {values, hasMore}
    const cache = new Map();

    function getState(wrapper) {
        if (!wrapperState.has(wrapper)) {
            wrapperState.set(wrapper, {
                page   : 1,
                term   : '',
                loading: false,
                done   : false,
                seen   : new Set(),
                ctrl   : null,
            });
        }
        return wrapperState.get(wrapper);
    }

    // ── Populate .filter-dropdown-options with items (original style) ─────────
    function renderItems(wrapper, values) {
        const optionsContainer = wrapper.querySelector('.filter-dropdown-options');
        if (!optionsContainer) return;
        const st = getState(wrapper);
        const input = wrapper.querySelector('.filter-dropdown-input');
        let selectedValues = [];
        if (input) {
            const rawSelected = input.getAttribute('data-selected-value') || '';
            if (rawSelected) {
                try {
                    selectedValues = JSON.parse(rawSelected);
                } catch (_) {
                    selectedValues = rawSelected.split(',').map(v => v.trim()).filter(Boolean);
                }
            } else if (input.value) {
                selectedValues = input.value.split(',').map(v => v.trim()).filter(Boolean);
            }
        }

        for (const vRaw of values) {
            const v = String(vRaw || '').trim();
            if (!v) continue;
            const norm = v.toLowerCase();
            if (st.seen.has(norm)) continue;
            st.seen.add(norm);

            const optionDiv = document.createElement('div');
            optionDiv.className = 'filter-dropdown-option' + (selectedValues.includes(v) ? ' selected' : '');
            optionDiv.textContent = v;
            optionDiv.setAttribute('data-value', v);

            optionDiv.addEventListener('click', (e) => {
                e.stopPropagation();
                toggleOption(wrapper, v, optionDiv);
            });

            optionsContainer.appendChild(optionDiv);
        }
    }

    function toggleOption(wrapper, value, optionDiv) {
        const input = wrapper.querySelector('.filter-dropdown-input');
        if (!input) return;
        let current = [];
        const rawSelected = input.getAttribute('data-selected-value') || '';
        if (rawSelected) {
            try {
                current = JSON.parse(rawSelected);
            } catch (_) {
                current = rawSelected.split(',').map(v => v.trim()).filter(Boolean);
            }
        } else if (input.value) {
            current = input.value.split(',').map(v => v.trim()).filter(Boolean);
        }

        const idx = current.indexOf(value);
        if (idx > -1) {
            current.splice(idx, 1);
            optionDiv.classList.remove('selected');
        } else {
            current.push(value);
            optionDiv.classList.add('selected');
        }
        input.value = current.join(', ');
        input.setAttribute('data-selected-value', JSON.stringify(current));
    }

    function showNoResults(wrapper, msg) {
        const optionsContainer = wrapper.querySelector('.filter-dropdown-options');
        if (!optionsContainer) return;
        const el = document.createElement('div');
        el.className = 'filter-dropdown-no-results';
        el.textContent = msg || 'No options available';
        optionsContainer.appendChild(el);
    }

    // ── Fetch one page from server ────────────────────────────────────────────
    async function fetchPage(wrapper, reset) {
        const st = getState(wrapper);
        if (st.loading || st.done) return;
        const fieldKey = wrapper.getAttribute('data-filter-field');
        if (!fieldKey) return;

        if (reset) {
            const optionsContainer = wrapper.querySelector('.filter-dropdown-options');
            if (optionsContainer) optionsContainer.innerHTML = '';
            st.page  = 1;
            st.done  = false;
            st.seen  = new Set();
        }

        st.loading = true;
        const cacheKey = `${fieldKey}|${st.term}|${st.page}`;

        try {
            if (cache.has(cacheKey)) {
                const cached = cache.get(cacheKey);
                renderItems(wrapper, cached.values || []);
                if (cached.values.length === 0 && st.page === 1) showNoResults(wrapper);
                st.done = !cached.hasMore || cached.values.length === 0;
                if (!st.done) st.page++;
                st.loading = false;
                return;
            }

            try { st.ctrl && st.ctrl.abort(); } catch (_) {}
            st.ctrl = new AbortController();

            const perPage = fieldKey === 'status' ? 200 : (st.term ? 5 : 20);
            const params  = new URLSearchParams({
                get_booking_unique_values: '1',
                fieldKey,
                q      : st.term,
                page   : String(st.page),
                perPage: String(perPage),
            });
            const urlFilterUser = new URLSearchParams(window.location.search).get('filterUser');
            if (urlFilterUser) params.set('filterUser', urlFilterUser);

            const resp = await fetch(`update_status.php?${params}`, { signal: st.ctrl.signal });
            if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
            const json = await resp.json();

            const values  = Array.isArray(json.values) ? json.values : [];
            const hasMore = !!json.hasMore;
            cache.set(cacheKey, { values, hasMore });

            renderItems(wrapper, values);
            if (values.length === 0 && st.page === 1) showNoResults(wrapper);
            st.done = !hasMore || values.length === 0;
            if (!st.done) st.page++;
        } catch (err) {
            if (err && err.name === 'AbortError') return;
            console.error('Booking filter fetch error:', err);
        } finally {
            st.loading = false;
        }
    }

    // ── Pre-warm: background-fetch page 1 for all fields ─────────────────────
    async function prewarmField(fieldKey) {
        const cacheKey = `${fieldKey}||1`;
        if (cache.has(cacheKey)) return;
        try {
            const perPage = fieldKey === 'status' ? 200 : 20;
            const params  = new URLSearchParams({
                get_booking_unique_values: '1',
                fieldKey, q: '', page: '1', perPage: String(perPage),
            });
            const urlFilterUser = new URLSearchParams(window.location.search).get('filterUser');
            if (urlFilterUser) params.set('filterUser', urlFilterUser);
            const resp   = await fetch(`update_status.php?${params}`);
            if (!resp.ok) return;
            const json   = await resp.json();
            const values = Array.isArray(json.values) ? json.values : [];
            cache.set(cacheKey, { values, hasMore: !!json.hasMore });
        } catch (_) {}
    }

    function prewarmAll() {
        ['builder','project','unit','customer','contact','email','status','city','salesperson']
            .forEach(f => prewarmField(f));
    }

    // ── Open / close helpers (same logic as original) ─────────────────────────
    function openDropdown(wrapper) {
        closeAllDropdowns();
        const dropdownList = wrapper.querySelector('.filter-dropdown-list');
        const searchBox    = wrapper.querySelector('.filter-search-box');
        if (!dropdownList) return;

        // Break out of overflow bounds
        dropdownList.style.display = 'flex';
        dropdownList.style.position = 'fixed';
        dropdownList.style.zIndex = '999999';
        
        // Calculate dynamic position based on wrapper
        const rect = wrapper.getBoundingClientRect();
        dropdownList.style.top = (rect.bottom + 4) + 'px';
        dropdownList.style.left = rect.left + 'px';
        dropdownList.style.width = rect.width + 'px';
        
        wrapper.classList.add('active');

        // Fix for webkit backdrop-filter rendering bug
        const modalContainer = wrapper.closest('.modal-container-eoi');
        const modalOverlay = wrapper.closest('.modal-overlay');
        if (modalContainer) {
            modalContainer.style.setProperty('backdrop-filter', 'none', 'important');
            modalContainer.style.setProperty('-webkit-backdrop-filter', 'none', 'important');
            modalContainer.style.setProperty('background-color', 'rgb(20 20 20)', 'important');
        }
        if (modalOverlay) {
            modalOverlay.style.setProperty('backdrop-filter', 'blur(9px)', 'important');
            modalOverlay.style.setProperty('-webkit-backdrop-filter', 'none', 'important');
        }

        const st       = getState(wrapper);
        const fieldKey = wrapper.getAttribute('data-filter-field');
        const ck       = `${fieldKey}||1`;

        // Reset term/page
        st.term   = '';
        st.page   = 1;
        st.done   = false;
        st.loading= false;
        st.seen   = new Set();
        if (searchBox) searchBox.value = '';
        const optionsContainer = wrapper.querySelector('.filter-dropdown-options');
        if (optionsContainer) optionsContainer.innerHTML = '';

        if (searchBox) setTimeout(() => searchBox.focus(), 50);

        // Paint from pre-warm cache immediately, or fetch
        if (cache.has(ck)) {
            const cached = cache.get(ck);
            renderItems(wrapper, cached.values || []);
            if (cached.values.length === 0) showNoResults(wrapper);
            st.done = !cached.hasMore || cached.values.length === 0;
            if (!st.done) st.page = 2;
        } else {
            fetchPage(wrapper, false);
        }
    }

    function closeDropdown(wrapper) {
        const dropdownList = wrapper.querySelector('.filter-dropdown-list');
        const searchBox    = wrapper.querySelector('.filter-search-box');
        if (dropdownList) {
            dropdownList.style.display = 'none';
            // Reset styles
            dropdownList.style.position = '';
            dropdownList.style.top = '';
            dropdownList.style.left = '';
            dropdownList.style.width = '';
            dropdownList.style.zIndex = '';
            wrapper.classList.remove('active');
            
            // Restore webkit backdrop-filter
            const modalContainer = wrapper.closest('.modal-container-eoi');
            const modalOverlay = wrapper.closest('.modal-overlay');
            if (modalContainer) {
                modalContainer.style.removeProperty('backdrop-filter');
                modalContainer.style.removeProperty('-webkit-backdrop-filter');
                modalContainer.style.removeProperty('background-color');
            }
            if (modalOverlay) {
                modalOverlay.style.removeProperty('backdrop-filter');
                modalOverlay.style.removeProperty('-webkit-backdrop-filter');
            }
        }
        if (searchBox) searchBox.value = '';
    }

    function closeAllDropdowns() {
        document.querySelectorAll('.filter-dropdown-wrapper').forEach(w => {
            const dl = w.querySelector('.filter-dropdown-list');
            if (dl && dl.style.display !== 'none') closeDropdown(w);
        });
    }

    // ── Init: bind events to every wrapper ───────────────────────────────────
    function initFilterDropdowns() {
        document.querySelectorAll('.filter-dropdown-wrapper').forEach(wrapper => {
            if (wrapper.dataset.bkFilterBound) return;
            wrapper.dataset.bkFilterBound = '1';

            const input        = wrapper.querySelector('.filter-dropdown-input');
            const searchBox    = wrapper.querySelector('.filter-search-box');
            const optionsCont  = wrapper.querySelector('.filter-dropdown-options');

            if (!input) return;

            // Click on input → open dropdown
            input.addEventListener('click', (e) => {
                e.stopPropagation();
                const dl = wrapper.querySelector('.filter-dropdown-list');
                if (dl && dl.style.display !== 'none') {
                    closeDropdown(wrapper);
                } else {
                    openDropdown(wrapper);
                }
            });

            // Search box: debounced server search
            if (searchBox) {
                let timer = null;
                searchBox.addEventListener('input', () => {
                    clearTimeout(timer);
                    timer = setTimeout(() => {
                        const st  = getState(wrapper);
                        st.term   = searchBox.value.trim();
                        st.page   = 1;
                        st.done   = false;
                        st.loading= false;
                        fetchPage(wrapper, true);
                    }, 150);
                });
                searchBox.addEventListener('click', (e) => e.stopPropagation());
            }

            // Scroll to load more
            if (optionsCont) {
                optionsCont.addEventListener('scroll', () => {
                    const st = getState(wrapper);
                    if (st.loading || st.done) return;
                    if (optionsCont.scrollTop + optionsCont.clientHeight >= optionsCont.scrollHeight - 10) {
                        fetchPage(wrapper, false);
                    }
                });
            }
        });

        // Close on outside click
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.filter-dropdown-wrapper')) {
                closeAllDropdowns();
            }
        });

        // Close dropdowns when scrolling the modal body to prevent detached floating
        const modalBody = document.querySelector('#filterModalOverlay .modal-body');
        if (modalBody) {
            modalBody.addEventListener('scroll', () => {
                closeAllDropdowns();
            }, { passive: true });
        }
        
        // Handle window resize
        window.addEventListener('resize', () => {
             closeAllDropdowns();
        }, { passive: true });
    }

    // ── openFilterModal override ──────────────────────────────────────────────
    window.openFilterModal = function () {
        const modal = document.getElementById('filterModalOverlay');
        if (modal) {
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
        initFilterDropdowns();
        prewarmAll();
    };

    // ── clearFilters override ─────────────────────────────────────────────────
    window.clearFilters = (function (_orig) {
        return function () {
            document.querySelectorAll('.filter-dropdown-wrapper').forEach(wrapper => {
                const input = wrapper.querySelector('.filter-dropdown-input');
                if (input) { input.value = ''; input.removeAttribute('data-selected-value'); }
                const optCont = wrapper.querySelector('.filter-dropdown-options');
                if (optCont) optCont.querySelectorAll('.filter-dropdown-option.selected')
                    .forEach(o => o.classList.remove('selected'));
            });
            closeAllDropdowns();
            if (typeof _orig === 'function') _orig();
        };
    })(window.clearFilters);

    // ── MutationObserver: init when modal becomes visible ────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        const filterModal = document.getElementById('filterModalOverlay');
        if (!filterModal) return;
        new MutationObserver(function () {
            const d = window.getComputedStyle(filterModal).display;
            if (d === 'flex' || d === 'block') {
                initFilterDropdowns();
                prewarmAll();
            }
        }).observe(filterModal, { attributes: true, attributeFilter: ['style'] });
    });

})();




function calculateDeductValue() {
        let agreementValue = parseFloat(document.getElementById("agreementValue").value) || 0;
        let cashback = parseFloat(document.getElementById("cashbackPct").value) || 0;

        // Convert cashback % to actual percentage (e.g., 1.5% = 1.5)
        let cashbackPct = cashback;

        let result = 0;

        if (cashbackPct >= 0.1 && cashbackPct <= 0.50) {
            result1 = agreementValue * 0.25;
            result = agreementValue - result1;
        }
        else if (cashbackPct > 0.50 && cashbackPct <= 1.00) {
            result1 = agreementValue * 0.50;
            result = agreementValue - result1;
        }
        else if (cashbackPct > 1.00 && cashbackPct <= 1.50) {
            result1 = agreementValue * 0.75;
            result = agreementValue - result1;
        }
        else if (cashbackPct > 1.50) {
            result1 = agreementValue * 1.00;
            result = agreementValue - result1;
        }
        else {
            result = agreementValue;
        }

        // Set calculated value into hidden field
        document.getElementById("deduct_agreementValue").value = result.toFixed(2);
    }


