// Disable excessive console logging in production to clean up the browser console
if (typeof window !== 'undefined') {
    window.console.log = function () { };
    window.console.info = function () { };
    window.console.debug = function () { };
    // Preserving console.error and console.warn for critical debugging
}

// Resolve the most reliable scrollable container for dashboard popup card lazy loading.
function getDashboardScrollRoot(usersGrid) {
    const popupContent = document.querySelector('#dashboardPopup .dashboard-content2');
    const popupShell = document.querySelector('#dashboardPopup .dashboard-popup');
    const candidates = [
        popupContent,
        popupShell,
        usersGrid?.closest('.dashboard-content2'),
        usersGrid?.closest('.dashboard-popup')
    ].filter(Boolean);

    for (const el of candidates) {
        if ((el.scrollHeight - el.clientHeight) > 8) {
            return el;
        }
    }

    return candidates[0] || null;
}
// Smooth scroll down the dashboard popup
function smoothScrollDownDashboard(root, distance = 420) {
    if (!root) {
        window.scrollBy({ top: distance, behavior: 'smooth' });
        return;
    }
    const maxScrollTop = Math.max(0, root.scrollHeight - root.clientHeight);
    if (maxScrollTop <= 0) return;

    const startTop = root.scrollTop;
    const targetTop = Math.min(startTop + distance, maxScrollTop);

    // Apply smooth CSS if not already present
    root.style.scrollBehavior = 'smooth';

    // Natively scroll smoothly
    root.scrollBy({ top: distance, behavior: 'smooth' });
}

// Enhanced Dashboard JavaScript with Aggregated Analytics Support

let currentUserTableName = ""; // Will be set by PHP in the HTML page
let currentlySelectedUser = null; // Track currently selected user from dropdown
let originalUserData = null;
let originalUserName = null;
let statusChart, sourceChart, overallLeadStatusChart, leadSourceChart;
let leadStatusData = null;
let leadSourceData = null;
let analyticsData = null;
let aggregatedAnalyticsData = null;
let currentFilteredAnalyticsData = null; // Stores filtered analytics data when filters are applied
let currentFilteredUserTablenames = null; // Stores list of filtered user tablenames for Excel export
let hierarchyData = null;
let dateRangeTimeout = null;
let lastStartDate = null;
let lastEndDate = null;
let selectedUsers = new Set(); // Store selected user values
let dropdownOpen = false;
let isRepopulatingDropdown = false; // Flag to prevent closing during repopulation
let currentSelectedMonth = new Date().getMonth() + 1;
let currentSelectedYear = new Date().getFullYear();
let preservedSelectedUsers = new Set();

// Lightweight in-memory cache for popup dashboard payloads to avoid refetching identical filters
const popupDataCache = new Map();
const POPUP_CACHE_TTL_MS = 120000; // 2 minutes

function buildPopupCacheKey(filters) {
    const ordered = {};
    Object.keys(filters).sort().forEach(k => {
        ordered[k] = filters[k];
    });
    return JSON.stringify(ordered);
}

function getCachedPopupData(key) {
    const entry = popupDataCache.get(key);
    if (!entry) return null;
    if (Date.now() - entry.timestamp > POPUP_CACHE_TTL_MS) {
        popupDataCache.delete(key);
        return null;
    }
    return entry.data;
}

function setCachedPopupData(key, data) {
    popupDataCache.set(key, { data, timestamp: Date.now() });
}

// Track which date column drives all filtered queries (created_at by default)
let dateFilterColumn = (() => {
    try {
        const stored = window.localStorage.getItem('dashboardDateColumn');
        return stored === 'updated_at' ? 'updated_at' : 'created_at';
    } catch (error) {
        console.warn('Unable to access localStorage for date column preference:', error);
        return 'created_at';
    }
})();


// Global user data for dashboard popup
const userData = [];
const leadSources = {};
const colors = [
    '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF',
    '#FF9F40', '#FF6384', '#C9CBCF', '#4BC0C0', '#FF6384'
];

// Utility functions
function formatRevenue(amount, full = false) {
    if (amount === null || amount === undefined) {
        return full ? '₹0.00' : '₹0';
    }

    const num = parseFloat(amount);
    if (isNaN(num)) {
        return full ? '₹0.00' : '₹0';
    }

    if (full) {
        return '₹' + num.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    // Format in international numbering system (K, M, B, T)
    if (num >= 1000000000000) {
        return '₹' + (num / 1000000000000).toFixed(2) + 'T';
    } else if (num >= 1000000000) {
        return '₹' + (num / 1000000000).toFixed(2) + 'B';
    } else if (num >= 1000000) {
        return '₹' + (num / 1000000).toFixed(2) + 'M';
    } else if (num >= 1000) {
        return '₹' + (num / 1000).toFixed(2) + 'K';
    }
    return '₹' + num.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// Alias for backward compatibility
const formatRevenueFull = (amount) => formatRevenue(amount, true);

function formatPercentage(numerator, denominator, decimals = 2) {
    if (denominator === 0 || denominator === null || denominator === undefined) {
        return '0%';
    }

    const num = parseFloat(numerator);
    const den = parseFloat(denominator);

    if (isNaN(num) || isNaN(den) || den === 0) {
        return '0%';
    }

    const percentage = (num / den) * 100;
    return percentage.toFixed(decimals) + '%';
}

function getStatusColors() {
    return {
        'Converted': '#10b981',
        'Interested': '#06b6d4',
        'Site Visit Done': '#8b5cf6',
        'VC Done': '#8d6e63',
        'Fix Site Visit': '#6366f1',
        'Call Back': '#f59e0b',
        'Follow Up': '#3b82f6',
        'Today\'s Follow Up': '#14b8a6',
        'Not Interested': '#ef4444',
        'Already Booked': '#f97316',
        'Not Connected': '#84cc16',
        'Dropped': '#6b7280',
        'RNR': '#9333ea',
        'Fake': '#dc2626',
        'Pending': '#fbbf24',
        'Re site visit': '#9c27b0',
        'Qualified for this project': '#00bcd4'
    };
}

// Get color for a specific status
function getStatusColor(status) {
    const statusColors = getStatusColors();
    return statusColors[status] || '#9ca3af'; // default gray color for unknown statuses
}

// Data management functions
function storeOriginalUserData(data, userName) {
    originalUserData = data;
    originalUserName = userName;
}

function toggleBackToDashboardButton(show) {
    const backButtonContainer = document.getElementById('backToMyDashboard');
    if (backButtonContainer) {
        backButtonContainer.classList.toggle('hidden', !show);
    }
}

function goBackToMyDashboard() {
    // Reset state variables
    currentlySelectedUser = null;
    currentViewMode = 'normal';
    currentHierarchyUser = null;

    // Clear multi-select filters
    selectedUsers.clear();
    preservedSelectedUsers.clear();

    // Clear project filters if they exist
    if (typeof selectedProjectNames !== 'undefined' && selectedProjectNames instanceof Set) {
        selectedProjectNames.clear();
    }

    // Reset searchable select if available
    if (window.searchableNameSelect) {
        if (typeof window.searchableNameSelect.reset === 'function') {
            window.searchableNameSelect.reset();
        } else if (typeof window.searchableNameSelect.setValue === 'function') {
            window.searchableNameSelect.setValue('');
        }
    }

    // Reset both desktop and mobile searchable select inputs
    const searchableSelects = [
        { id: 'searchableNameSelect', inputId: 'nameSearchInput' },
        { id: 'searchableNameSelectMobile', inputId: 'nameSearchInputMobile' }
    ];

    searchableSelects.forEach(({ id, inputId }) => {
        const container = document.getElementById(id);
        if (container) {
            const input = document.getElementById(inputId) || container.querySelector('input[type="text"]');
            if (input) {
                input.value = '';
                input.setAttribute('data-selected-value', '');
                input.placeholder = 'Search and select a name...';
            }
        }
    });

    // Reset traditional select
    const namesSelect = document.getElementById('namesSelect') || document.getElementById('name-select');
    if (namesSelect) {
        const originalHandler = namesSelect.onchange;
        namesSelect.onchange = null;
        namesSelect.selectedIndex = 0;
        namesSelect.value = '';
        setTimeout(() => namesSelect.onchange = originalHandler, 100);
    }

    // Clear search input in team member dropdown
    const teamSearchInput = document.querySelector('.multi-select-dropdown .search-input');
    if (teamSearchInput) {
        teamSearchInput.value = '';
    }

    // Update UI elements
    if (typeof updateDropdownSelections === 'function') updateDropdownSelections();
    if (typeof updateSelectedTags === 'function') updateSelectedTags();
    if (typeof updateDropdownPlaceholder === 'function') updateDropdownPlaceholder();
    if (typeof updateProjectPlaceholder === 'function') updateProjectPlaceholder();
    if (typeof clearSearch === 'function') clearSearch({ stopPropagation: () => { } });

    // Restore dashboard to current user
    if (originalUserData && originalUserName) {
        updateWelcomeText(`Hello, ${originalUserName} 👋`);
        updateDashboardWithData(originalUserData);
        loadUserTotalData();
    } else {
        const monthSelect = document.getElementById("monthSelect");
        const yearSelect = document.getElementById("yearSelect");
        const month = monthSelect ? monthSelect.value : new Date().getMonth() + 1;
        const year = yearSelect ? yearSelect.value : new Date().getFullYear();

        const filters = {
            month: month,
            year: year,
            date_column: getActiveDateColumn()
        };

        const url = buildDashboardUrl('dashboard_data.php', filters);

        fetch(url)
            .then(res => res.json())
            .then(data => {
                if (data.status === "success") {
                    const currentUserName = data.hierarchy?.current_user?.username || "User";
                    updateWelcomeText(`Hello, ${currentUserName} 👋`);
                    storeOriginalUserData(data, currentUserName);
                    updateDashboardWithData(data);
                    loadUserTotalData();
                }
            })
            .catch(err => console.error("Error fetching current user data:", err));
    }

    toggleBackToDashboardButton(false);
}
function validateDateRange(startDate, endDate) {
    if (!startDate || !endDate) {
        return { isValid: false, message: "Please select both start and end dates" };
    }

    const start = new Date(startDate);
    const end = new Date(endDate);

    if (isNaN(start.getTime()) || isNaN(end.getTime())) {
        return { isValid: false, message: "Invalid date format" };
    }

    if (start > end) {
        return {
            isValid: false,
            message: "Start date cannot be after end date. Please select a valid date range."
        };
    }

    return { isValid: true };
}


function updateWelcomeText(text) {
    const welcomeElement2 = document.querySelector(".welcome-2");
    if (welcomeElement2) welcomeElement2.textContent = text;
    const welcomeElement = document.querySelector(".welcome-1");
    if (welcomeElement) welcomeElement.textContent = text;
}

function updateUserInfo(data) {
    const userEmail = document.querySelector(".user-email");

    if (userEmail && data.hierarchy?.current_user) {
        userEmail.textContent = data.hierarchy.current_user.useremail;
    }

    const totalLeadsElement = document.querySelector(".user-info-stats .user-stat:nth-child(1) .stat-value");
    const bookingsElement = document.querySelector(".user-info-stats .user-stat:nth-child(2) .stat-value");
    const revenueElement = document.querySelector(".user-info-stats .user-stat:nth-child(3) .stat-value");

    const bookingsForPeriod = data.total_bookings ?? data.total_bookings_modal ?? 0;
    const revenueForPeriod = data.total_revenue ?? data.total_revenue_modal ?? 0;

    if (totalLeadsElement) totalLeadsElement.textContent = data.myLeads || 0;
    if (bookingsElement) bookingsElement.textContent = bookingsForPeriod;
    if (revenueElement) revenueElement.textContent = formatRevenue(revenueForPeriod);
}



function updateCurrentUserPopupStats(data) {
    const userTotalLeadsElement = document.getElementById("user-total-leads");
    const userTotalBookingsElement = document.getElementById("user-total-bookings");
    const userTotalRevenueElement = document.getElementById("user-total-revenue");

    // Use the same filtered data as main dashboard cards instead of modal (financial year) data
    const bookingsForPeriod = data.total_bookings ?? data.total_bookings_modal ?? 0;
    const revenueForPeriod = data.total_revenue ?? data.total_revenue_modal ?? 0;

    if (userTotalLeadsElement) userTotalLeadsElement.textContent = data.myLeads || 0;
    if (userTotalBookingsElement) userTotalBookingsElement.textContent = bookingsForPeriod;
    if (userTotalRevenueElement) userTotalRevenueElement.textContent = formatRevenue(revenueForPeriod);
}

// Dashboard update functions
function updateDashboardWithData(data) {
    updateCEOInfo(data);
    updateStandingsTable(data);
    updateKPIs(data);
    updateStatusCounts(data);
    updateProgressBar(data);
}

function updateCEOInfo(data) {
    if (data.ceo) {
        const ceoElement = document.querySelector(".team-right .team-name");
        if (ceoElement) ceoElement.textContent = data.ceo;
    }
}

function updateStandingsTable(data) {
    const tbody = document.getElementById("standings-body");
    if (!tbody) return;

    if (data.standings && data.standings.length > 0) {
        tbody.innerHTML = "";
        data.standings.forEach(item => {
            const row = document.createElement("tr");
            let sourceLogo = "assets/dataimage/mecntec-icon.svg";
            if (item.source_of_lead) {
                const source = item.source_of_lead.toLowerCase();
                if (source.includes("google ads lead")) sourceLogo = "assets/dataimage/mecntec-icon.svg";
                else if (source.includes("facebook ads lead")) sourceLogo = "assets/dataimage/mecntec-icon.svg";
                else if (source.includes("facebook")) sourceLogo = "assets/dataimage/facebook.svg";
                else if (source.includes("google")) sourceLogo = "assets/dataimage/google-logo.svg";
            }

            row.innerHTML = `
                <td>${item.id}</td>
                <td>${item.name}</td>
                <td class="lead-source" style="text-align:center;">
                    <img src="${sourceLogo}" alt="${item.source_of_lead || ''}" title="${item.source_of_lead || ''}" 
                             style="width:20px; height:20px; vertical-align:middle;">
                </td>
                <td>${item.status || ''}</td>
                <td>${item.assign_project_name || ''}</td>
            `;
            tbody.appendChild(row);
        });
    } else {
        tbody.innerHTML = `<tr><td colspan="5" style="text-align: center;">No leads found for selected period</td></tr>`;
    }
}

function updateKPIs(data) {
    if (data.total_eoi !== undefined) {
        const eoiElement = document.getElementById("total-eoi-value");
        if (eoiElement) {
            eoiElement.textContent = data.total_eoi;
        }
    }

    if (data.myLeads !== undefined) {
        const myLeadsElement = document.getElementById("my-leads-value");
        if (myLeadsElement) {
            myLeadsElement.textContent = data.myLeads;
        }
    }

    const bookingsForPeriod = data.total_bookings ?? data.total_bookings_modal;
    if (bookingsForPeriod !== undefined) {
        const bookingsEl = document.getElementById("overall-bookings-value");
        if (bookingsEl) {
            bookingsEl.textContent = bookingsForPeriod ?? 0;
        }
    }

    const revenueForPeriod = data.total_revenue ?? data.total_revenue_modal;
    if (revenueForPeriod !== undefined) {
        const revenueEl = document.getElementById("overall-revenue-value");
        if (revenueEl) {
            revenueEl.textContent = formatRevenue(revenueForPeriod ?? 0);
            revenueEl.setAttribute("title", formatRevenueFull(revenueForPeriod ?? 0));
        }
    }
}
function updateStatusCounts(data) {
    if (data.status_counts) {
        const pendingEl = document.getElementById("pending-count");
        const sitevisitEl = document.getElementById("site-visited-count");
        const followupEl = document.getElementById("followup-count");
        const fixsitevisitEl = document.getElementById("fix-site-visit-count");

        if (pendingEl) pendingEl.textContent = data.status_counts.pending_count || 0;
        if (sitevisitEl) sitevisitEl.textContent = data.status_counts.site_visit_done_count || 0;
        if (followupEl) followupEl.textContent = data.status_counts.followup_count || 0;
        if (fixsitevisitEl) fixsitevisitEl.textContent = data.status_counts.fix_site_visit_count || 0;
    }
}

function updateProgressBar(data) {
    if (!data.status_counts) return;

    const pending = parseInt(data.status_counts.pending_count) || 0;
    const followup = parseInt(data.status_counts.followup_count) || 0;
    const fixVisit = parseInt(data.status_counts.fix_site_visit_count) || 0;
    const siteVisited = parseInt(data.status_counts.site_visit_done_count || data.status_counts.site_visited_count) || 0;
    const totalStatus = pending + followup + fixVisit + siteVisited;

    const pendingSeg = document.querySelector('.progress-segment.status-pending');
    const followupSeg = document.querySelector('.progress-segment.status-followup');
    const fixSeg = document.querySelector('.progress-segment.status-fix-site-visit');
    const visitedSeg = document.querySelector('.progress-segment.status-site-visited');

    if (!pendingSeg || !followupSeg || !fixSeg || !visitedSeg) return;

    if (totalStatus > 0) {
        pendingSeg.style.width = `${(pending / totalStatus) * 100}%`;
        followupSeg.style.width = `${(followup / totalStatus) * 100}%`;
        fixSeg.style.width = `${(fixVisit / totalStatus) * 100}%`;
        visitedSeg.style.width = `${(siteVisited / totalStatus) * 100}%`;
    } else {
        pendingSeg.style.width = '0%';
        followupSeg.style.width = '0%';
        fixSeg.style.width = '0%';
        visitedSeg.style.width = '0%';
    }

    pendingSeg.setAttribute('data-count', `Pending: ${pending}`);
    followupSeg.setAttribute('data-count', `Follow Up: ${followup}`);
    fixSeg.setAttribute('data-count', `Fix Site Visit: ${fixVisit}`);
    visitedSeg.setAttribute('data-count', `Site Visited: ${siteVisited}`);
}

// Track latest dashboard request so older ones can be cancelled
let activeDashboardFetchController = null;

function cancelActiveDashboardRequest() {
    if (activeDashboardFetchController) {
        try {
            activeDashboardFetchController.abort('superseded');
        } catch (err) {
            console.warn('Unable to abort previous dashboard request', err);
        }
        activeDashboardFetchController = null;
    }
}

// Data fetching functions with abort + timeout support
function fetchData(url, options = {}) {
    const { controller, timeoutMs = 60000 } = options;
    const abortController = controller || new AbortController();
    let timeoutId = null;

    // Fail fast on extremely slow responses instead of leaving the loader spinning
    if (timeoutMs > 0) {
        timeoutId = setTimeout(() => abortController.abort('timeout'), timeoutMs);
    }

    return fetch(url, { signal: abortController.signal, cache: 'no-store' })
        .then(res => res.json())
        .catch(err => {
            if (err.name === 'AbortError') {
                console.warn('Fetch aborted:', url, err.message || '');
                return { status: "aborted", message: "Request cancelled" };
            }
            console.error("Fetch error:", err);
            return { status: "error", message: "Network error loading data" };
        })
        .finally(() => {
            if (timeoutId) {
                clearTimeout(timeoutId);
            }
        });
}

// Build a consistent query string for dashboard requests
function buildDashboardUrl(base, params = {}) {
    const searchParams = new URLSearchParams();

    Object.entries(params).forEach(([key, value]) => {
        if (value === undefined || value === null) return;

        if (Array.isArray(value)) {
            const sanitized = value
                .map(item => (typeof item === 'string' ? item.trim() : String(item)))
                .filter(item => item !== '');
            if (sanitized.length) searchParams.set(key, sanitized.join(','));
            return;
        }

        if (typeof value === 'object') {
            // Allow nested objects that already represent key/value pairs
            Object.entries(value).forEach(([nestedKey, nestedValue]) => {
                if (nestedValue === undefined || nestedValue === null) return;
                const formatted = typeof nestedValue === 'string' ? nestedValue.trim() : String(nestedValue);
                if (formatted !== '') searchParams.set(nestedKey, formatted);
            });
            return;
        }

        const formatted = typeof value === 'string' ? value.trim() : String(value);
        if (formatted !== '') searchParams.set(key, formatted);
    });

    const query = searchParams.toString();
    return query ? `${base}?${query}` : base;
}

function getActiveDateColumn() {
    return dateFilterColumn === 'updated_at' ? 'updated_at' : 'created_at';
}

function setActiveDateColumn(column) {
    const normalized = column === 'updated_at' ? 'updated_at' : 'created_at';
    if (dateFilterColumn === normalized) {
        updateDateToggleUI();
        return;
    }

    dateFilterColumn = normalized;

    try {
        window.localStorage.setItem('dashboardDateColumn', normalized);
    } catch (error) {
        console.warn('Unable to persist date column preference:', error);
    }

    updateDateToggleUI();
    refreshDashboardForDatePreference();
}

function isUsingUpdatedAtColumn() {
    return getActiveDateColumn() === 'updated_at';
}

function updateDateToggleUI() {
    const toggle = document.getElementById('dateColumnToggle');
    const label = document.getElementById('dateColumnToggleLabel');
    const container = document.getElementById('dateColumnToggleContainer');

    const useUpdated = isUsingUpdatedAtColumn();

    if (toggle) {
        toggle.checked = useUpdated;
    }

    if (label) {
        label.textContent = useUpdated ? 'Updated At' : 'Created At';
        label.dataset.state = useUpdated ? 'updated' : 'created';
    }

    if (container) {
        container.dataset.state = useUpdated ? 'updated' : 'created';
    }
}

function handleDateColumnToggleChange(event) {
    // NOTE: Just update the state, don't apply filters automatically
    // User must click Apply button to apply the filter
    const useUpdated = event?.target?.checked;
    const normalized = useUpdated ? 'updated_at' : 'created_at';
    dateFilterColumn = normalized;
    updateDateToggleUI();

    try {
        window.localStorage.setItem('dashboardDateColumn', normalized);
    } catch (error) {
        console.warn('Unable to persist date column preference:', error);
    }
}

function refreshDashboardForDatePreference(preserveSelections = true) {
    const monthSelect = document.getElementById('monthSelect');
    const yearSelect = document.getElementById('yearSelect');
    const startDateInput = document.getElementById('startDate');
    const endDateInput = document.getElementById('endDate');

    const customRangeActive = Boolean(
        (lastStartDate && lastEndDate) ||
        (monthSelect && monthSelect.value === 'custom' && startDateInput?.value && endDateInput?.value)
    );

    if (customRangeActive) {
        handleCustomRangeApply(true, preserveSelections);
        return;
    }

    const dateSelection = getCurrentPopupDateSelection();

    if (dateSelection.start_date && dateSelection.end_date) {
        loadPopupDashboardDataByDateRange(dateSelection.start_date, dateSelection.end_date, preserveSelections);
    } else {
        const month = parseInt(dateSelection.month, 10) || (new Date().getMonth() + 1);
        const year = parseInt(dateSelection.year, 10) || new Date().getFullYear();
        loadPopupDashboardData(month, year, preserveSelections);
    }

    const parsedMonth = monthSelect ? parseInt(monthSelect.value, 10) : NaN;
    const parsedYear = yearSelect ? parseInt(yearSelect.value, 10) : NaN;

    const monthToLoad = !Number.isNaN(parsedMonth) ? parsedMonth : (currentSelectedMonth || (new Date().getMonth() + 1));
    const yearToLoad = !Number.isNaN(parsedYear) ? parsedYear : (currentSelectedYear || new Date().getFullYear());

    loadDashboardData(monthToLoad, yearToLoad);

    if (selectedUsers.size > 0 || selectedProjectNames.size > 0) {
        setTimeout(() => applyAllFilters(), 500);
    } else if (currentViewMode === 'hierarchy' && currentHierarchyUser) {
        setTimeout(() => showTeamView(currentHierarchyUser), 500);
    }
}

// Determine active popup date filters (month/year or explicit range)
function getCurrentPopupDateSelection() {
    const popupDateRangeFilter = document.getElementById('popupDateRangeFilter');
    const popupMonthSelect = document.getElementById('popupMonthSelect');
    const popupYearSelect = document.getElementById('popupYearSelect');
    const popupStartDate = document.getElementById('popupStartDate');
    const popupEndDate = document.getElementById('popupEndDate');

    const startDate = popupStartDate?.value;
    const endDate = popupEndDate?.value;
    const monthValue = popupMonthSelect?.value;
    const yearValue = popupYearSelect?.value;

    const rangeVisible = popupDateRangeFilter && popupDateRangeFilter.style.display !== 'none';
    const monthRequiresRange = monthValue === 'custom';

    if ((rangeVisible || monthRequiresRange) && startDate && endDate) {
        return { start_date: startDate, end_date: endDate };
    }

    if (lastStartDate && lastEndDate) {
        return { start_date: lastStartDate, end_date: lastEndDate };
    }

    // Fallback to current month/year when explicit range not selected or incomplete
    const now = new Date();
    const month = (monthValue && monthValue !== 'custom') ? monthValue : (now.getMonth() + 1);
    const year = yearValue || now.getFullYear();

    return { month: month, year: year };
}

// Collect dashboard filters from current UI state with configurable inclusions
function collectDashboardFilters(options = {}) {
    const {
        includeSelectedUser = false,
        selectedUserOverride = null,
        includeFilteredUsers = false,
        filteredUsersOverride = null,
        includeProjects = true,
        projectNamesOverride = null,
        includeDates = true
    } = options || {};

    const filters = {};

    if (includeDates) {
        const dateSelection = getCurrentPopupDateSelection();
        if (dateSelection.start_date && dateSelection.end_date) {
            filters.start_date = dateSelection.start_date;
            filters.end_date = dateSelection.end_date;
        } else {
            filters.month = dateSelection.month;
            filters.year = dateSelection.year;
        }
    }

    if (includeProjects) {
        const projectsSource = Array.isArray(projectNamesOverride)
            ? projectNamesOverride
            : getSelectedProjectNames();
        const projects = (projectsSource || []).map(name => (typeof name === 'string' ? name.trim() : String(name))).filter(Boolean);
        if (projects.length) {
            filters.project_filter = projects.join(',');
        }
    }

    if (includeFilteredUsers) {
        let filteredUsers = [];
        if (Array.isArray(filteredUsersOverride)) {
            filteredUsers = filteredUsersOverride;
        } else if (filteredUsersOverride && typeof filteredUsersOverride === 'string') {
            filteredUsers = filteredUsersOverride.split(',');
        } else if (selectedUsers instanceof Set) {
            filteredUsers = Array.from(selectedUsers);
        }

        filteredUsers = filteredUsers
            .map(user => (typeof user === 'string' ? user.trim() : String(user)))
            .filter(Boolean);

        if (filteredUsers.length) {
            filters.filtered_users = filteredUsers.join(',');
        }
    }

    if (includeSelectedUser) {
        const selectedUser = selectedUserOverride ?? getSelectedUserValue();
        if (selectedUser) {
            filters.user_id = selectedUser;
        }
    }

    filters.date_column = getActiveDateColumn();

    return filters;
}

function loadUserTotalData(selectedUser = '') {
    const targetUser = selectedUser || currentlySelectedUser || getSelectedUserValue() || currentUserTableName || '';

    const financialYearDates = getCurrentFinancialYear();
    const filters = {
        start_date: financialYearDates.start,
        end_date: financialYearDates.end,
        date_column: getActiveDateColumn()
    };

    if (targetUser) {
        filters.user_id = targetUser;
    }

    const url = buildDashboardUrl('dashboard_data.php', filters);

    fetchData(url).then(data => {
        if (data.status === "success") {
            updateCurrentUserPopupStats(data);
        } else {
            console.error("Error loading financial year user data:", data.message);
        }
    });
}

function loadDashboardData(month, year) {
    // Sync user selection state and get selected user
    const selectedUser = syncUserSelectionState();

    const filters = {
        month: month,
        year: year,
        date_column: getActiveDateColumn()
    };

    if (selectedUser) {
        filters.user_id = selectedUser;
    }

    const url = buildDashboardUrl('dashboard_data.php', filters);

    fetchData(url).then(data => {
        if (data.status === "success") {
            updateDashboardWithData(data);

            // Ensure user totals are loaded with the correct context
            loadUserTotalData(selectedUser);
        }
    });
}

function fetchAssignedUsers() {
    return fetchData("dashboard_data.php").then(data => {
        if (data.status === "success" && data.assigned_users) {
            // Preserve existing user types to prevent badge overwriting
            const existingUserTypes = {};
            if (allUsersData && allUsersData.length > 0) {
                allUsersData.forEach(user => {
                    if (user.tablename && user.user_type) {
                        existingUserTypes[user.tablename] = user.user_type;
                    }
                });
            }

            // FIXED: Smart merge — do NOT overwrite perf data that loadPopupDashboardData already set.
            // Build a lookup of existing rich data keyed by tablename.
            const existingPerf = {};
            if (allUsersData && allUsersData.length > 0) {
                allUsersData.forEach(user => {
                    if (user.tablename) {
                        existingPerf[user.tablename] = user;
                        if (user.user_type && user.user_type !== 'user') {
                            existingUserTypes[user.tablename] = user.user_type;
                        }
                    }
                });
            }

            // Store user data with types for badge display, preserving existing performance metrics
            allUsersData = data.assigned_users.map(user => {
                const preservedType = existingUserTypes[user.tablename];
                // Prefer API data for user/team_lead roles, use preserved data only if API is empty
                let finalType;
                if (user.user_type && user.user_type !== 'undefined' && user.user_type !== 'null') {
                    // Trust API data if it exists and is valid
                    finalType = user.user_type;
                } else {
                    // Use preserved data only as fallback
                    finalType = preservedType || 'user';
                }

                // Store user type persistently for future sessions
                if (user.tablename && finalType) {
                    persistentUserTypes[user.tablename] = finalType;
                }

                // If we already have full performance data for this user (set by loadPopupDashboardData),
                // merge it in so we do NOT lose leads/charts on hard refresh.
                const existing = existingPerf[user.tablename];
                const hasFullData = existing && (existing.leads > 0 || (Array.isArray(existing.leadStatus) && existing.leadStatus.length > 0));

                if (hasFullData) {
                    return {
                        ...existing,           // keep all existing perf data (leads, leadStatus, etc.)
                        ...user,               // overlay fresh basic user fields (name, email, etc.)
                        user_type: finalType,  // always use resolved type
                        // Restore performance fields that may have been overwritten by ...user spread
                        leads: existing.leads,
                        bookings: existing.bookings,
                        eoi: existing.eoi,
                        cancelled_bookings: existing.cancelled_bookings,
                        quality_range: existing.quality_range,
                        fsv_count: existing.fsv_count,
                        svd_count: existing.svd_count,
                        leadStatus: existing.leadStatus,
                        detailed_status_counts: existing.detailed_status_counts,
                        analytics: existing.analytics
                    };
                }

                return {
                    ...user,
                    user_type: finalType
                };
            });

            // CRITICAL FIX: Ensure the master dropdown memory ALWAYS gets populated when the full 
            // set of assigned users is requested directly from the backend, so it's not permanently empty.
            if (!originalUsersData || originalUsersData.length === 0 || originalUsersData.length < allUsersData.length) {
                originalUsersData = [...allUsersData];
                console.log('Restored originalUsersData from fetchAssignedUsers with full subordinate list:', originalUsersData.length);
            }

            // Refresh dropdown to use the updated persistent types
            if (typeof populateDropdownOptions === 'function') {
                setTimeout(() => {
                    populateDropdownOptions();
                }, 100);
            }

            assignedUsersReady = true;
            return allUsersData;
        }
        return [];
    });
}

// New function to fetch project names from user_remarks table
function fetchProjectNames() {
    return fetchData("dashboard_data.php?fetch_project_names=true").then(data => {
        if (data.status === "success") {
            const projectNames = data.project_names || [];
            return projectNames;
        } else {
            console.error('Error fetching project names:', data.message);
            return [];
        }
    }).catch(error => {
        console.error("Error fetching project names:", error);
        return [];
    });
}

// New function to fetch users who have data for specific projects
function fetchUsersByProjects(projects) {
    if (!projects || projects.length === 0) {
        return Promise.resolve([]);
    }

    const projectsParam = projects.join(',');
    const url = `dashboard_data.php?fetch_users_by_projects=true&projects=${encodeURIComponent(projectsParam)}`;

    return fetchData(url).then(data => {
        if (data.status === "success") {
            return data.users || [];
        } else {
            console.error('Error response from fetchUsersByProjects:', data.message);
            return [];
        }
    }).catch(error => {
        console.error("Error fetching users by projects:", error);
        return [];
    });
}

// Helper function to get selected user from either searchable select or traditional select
function getSelectedUserValue() {
    // Try searchable select first
    if (window.searchableNameSelect && window.searchableNameSelect.getValue) {
        const value = window.searchableNameSelect.getValue();
        if (value) return value;
    }

    // Fallback to traditional select
    const namesSelect = document.getElementById('namesSelect') || document.getElementById('name-select');
    return namesSelect ? namesSelect.value : '';
}

// Helper function to set selected user in either searchable select or traditional select
function setSelectedUserValue(value) {
    // Try searchable select first
    if (window.searchableNameSelect && window.searchableNameSelect.setValue) {
        window.searchableNameSelect.setValue(value);
        return true;
    }

    // Fallback to traditional select
    const namesSelect = document.getElementById('namesSelect') || document.getElementById('name-select');
    if (namesSelect) {
        namesSelect.value = value;
        return true;
    }

    return false;
}

// Helper function to sync user selection state
function syncUserSelectionState() {
    const selectedUser = getSelectedUserValue();
    if (selectedUser !== currentlySelectedUser) {
        currentlySelectedUser = selectedUser;
    }
    return selectedUser;
}

// Helper function to get selected user name
function getSelectedUserName() {
    // Try searchable select first
    if (window.searchableNameSelect && window.searchableNameSelect.getSelectedUser) {
        const user = window.searchableNameSelect.getSelectedUser();
        if (user) return user.text;
    }

    // Fallback to traditional select
    const namesSelect = document.getElementById('namesSelect') || document.getElementById('name-select');
    if (namesSelect && namesSelect.selectedIndex > 0) {
        return namesSelect.options[namesSelect.selectedIndex].textContent;
    }

    return '';
}

function fetchAnalyticsData() {
    const selectedUser = getSelectedUserValue();

    // Get current financial year dates
    const financialYearDates = getCurrentFinancialYear();

    const filters = {
        start_date: financialYearDates.start,
        end_date: financialYearDates.end,
        date_column: getActiveDateColumn()
    };

    if (selectedUser) {
        filters.user_id = selectedUser;
    }

    const url = buildDashboardUrl('dashboard_data.php', filters);

    return fetchData(url).then(data => {
        if (data.status === 'success') {
            analyticsData = data;
            processChartData(data);
            return data;
        } else {
            console.error('Failed to fetch analytics data:', data.message || 'Unknown error');
            return null;
        }
    });
}

// Helper function to get current financial year dates
function getCurrentFinancialYear() {
    const now = new Date();
    const currentYear = now.getFullYear();
    const currentMonth = now.getMonth() + 1; // 1-12

    let startYear, endYear;

    if (currentMonth >= 4) {
        // April or later - current financial year
        startYear = currentYear;
        endYear = currentYear + 1;
    } else {
        // January-March - previous financial year
        startYear = currentYear - 1;
        endYear = currentYear;
    }

    return {
        start: `${startYear}-04-01`,
        end: `${endYear}-03-31`
    };
}


function fetchAggregatedAnalyticsData() {
    const selectedUser = getSelectedUserValue();

    // Use centralized filter helpers for consistent param building
    const filters = collectDashboardFilters({
        includeSelectedUser: !!selectedUser,
        selectedUserOverride: selectedUser,
        includeProjects: true,
        includeDates: true
    });

    filters.aggregated_analytics = true;
    filters.include_detailed_data = true;

    const url = buildDashboardUrl('dashboard_data.php', filters);

    return fetchData(url).then(data => {
        if (data.status === 'success' && data.aggregated_analytics) {
            aggregatedAnalyticsData = data.aggregated_analytics;

            // CRITICAL: Ensure we have the detailed performance data for all users
            if (data.aggregated_analytics.user_wise_data) {
                // Store the detailed user data for use in normal view
                allUsersData = data.aggregated_analytics.user_wise_data.map(user => ({
                    ...user,
                    // Ensure we have all the performance metrics
                    leads: user.leads || 0,
                    bookings: user.bookings || 0,
                    eoi: user.eoi || 0,
                    cancelled_bookings: user.cancelled_bookings || 0,
                    // Preserve user_type from original data
                    user_type: user.user_type || 'user'
                }));
            }

            processAggregatedData(data.aggregated_analytics);
            return data;
        } else {
            console.error('Failed to fetch aggregated analytics data');
            return null;
        }
    });
}
// New function to fetch analytics data for specific filtered users
function fetchFilteredAnalyticsData(userTablenames) {
    if (!userTablenames || userTablenames.length === 0) {
        return Promise.resolve(null);
    }

    const uniqueUsers = Array.from(new Set(
        userTablenames
            .map(tablename => (typeof tablename === 'string' ? tablename.trim() : String(tablename || '').trim()))
            .filter(Boolean)
    ));

    if (uniqueUsers.length === 0) {
        return Promise.resolve(null);
    }

    // Use one aggregated backend request instead of N per-user requests.
    const filters = collectDashboardFilters({
        includeProjects: true,
        includeDates: true
    });

    filters.aggregated_analytics = true;
    filters.include_detailed_data = true;
    filters.filtered_users = uniqueUsers.join(',');

    const selectedMainUser = currentlySelectedUser || getSelectedUserValue();
    if (selectedMainUser) {
        filters.user_id = selectedMainUser;
    }

    const cacheKey = buildPopupCacheKey({
        scope: 'filtered_analytics',
        ...filters
    });

    const cached = getCachedPopupData(cacheKey);
    if (cached) {
        return Promise.resolve({
            status: 'success',
            aggregated_analytics: cached
        });
    }

    const url = buildDashboardUrl('dashboard_data.php', filters);

    return fetchData(url, { timeoutMs: 20000 }).then(data => {
        if (data && data.status === 'success' && data.aggregated_analytics) {
            setCachedPopupData(cacheKey, data.aggregated_analytics);
            return {
                status: 'success',
                aggregated_analytics: data.aggregated_analytics
            };
        }

        console.error('Failed to fetch filtered aggregated analytics:', data?.message || data);
        return null;
    }).catch(error => {
        console.error('Error fetching filtered aggregated analytics:', error);
        return null;
    });
}

// New function to aggregate analytics data from multiple users
function aggregateUserAnalytics(userResults) {
    const aggregated = {
        detailed_status_counts: [],
        detailed_source_counts: [],
        total_leads: 0,
        total_bookings: 0,
        total_eoi: 0,
        total_users: userResults.length
    };

    const statusCountsMap = {};
    const sourceCountsMap = {};

    userResults.forEach(userResult => {
        // Aggregate totals
        aggregated.total_leads += parseInt(userResult.leads) || 0;
        aggregated.total_bookings += parseInt(userResult.bookings) || 0;
        aggregated.total_eoi += parseInt(userResult.eoi) || 0;

        // Aggregate status counts
        if (userResult.analytics && userResult.analytics.detailed_status_counts) {
            userResult.analytics.detailed_status_counts.forEach(statusItem => {
                const status = statusItem.status;
                const count = parseInt(statusItem.count) || 0;
                statusCountsMap[status] = (statusCountsMap[status] || 0) + count;
            });
        }

        // Aggregate source counts
        if (userResult.analytics && userResult.analytics.detailed_source_counts) {
            userResult.analytics.detailed_source_counts.forEach(sourceItem => {
                const source = sourceItem.source_of_lead;
                const count = parseInt(sourceItem.count) || 0;
                sourceCountsMap[source] = (sourceCountsMap[source] || 0) + count;
            });
        }
    });

    // Convert maps to arrays
    aggregated.detailed_status_counts = Object.entries(statusCountsMap).map(([status, count]) => ({
        status,
        count
    }));

    aggregated.detailed_source_counts = Object.entries(sourceCountsMap).map(([source, count]) => ({
        source_of_lead: source,
        count
    }));

    return aggregated;
}

function processChartData(data) {
    if (!data) return;

    // Use centralized status colors for consistency
    const statusColors = getStatusColors();

    // Use detailed status counts from analytics data for complete status information
    let statusCounts = [];

    if (data.analytics && data.analytics.detailed_status_counts && data.analytics.detailed_status_counts.length > 0) {
        // Use detailed status counts from analytics (includes all statuses like fake, converted, etc.)
        statusCounts = data.analytics.detailed_status_counts.filter(item => item.count > 0);
    } else if (data.status_counts) {
        // Fallback to basic status counts if detailed counts not available
        statusCounts = [
            { status: 'Pending', count: data.status_counts.pending_count || 0 },
            { status: 'Site Visit Done', count: data.status_counts.site_visit_done_count || 0 },
            { status: 'Follow Up', count: data.status_counts.followup_count || 0 },
            { status: 'Fix Site Visit', count: data.status_counts.fix_site_visit_count || 0 }
        ].filter(item => item.count > 0);
    }

    leadStatusData = {
        labels: statusCounts.map(item => item.status || 'Unknown'),
        datasets: [{
            data: statusCounts.map(item => parseInt(item.count) || 0),
            backgroundColor: statusCounts.map(item => statusColors[item.status] || '#9ca3af'),
            borderWidth: 2
        }]
    };




    const sourceColors = {
        'Google': '#4285f4', 'Facebook': '#1877f2', 'Direct': '#6366f1',
        'Portal': '#8b5cf6', 'Referral': '#10b981', 'WhatsApp': '#25d366',
        'Instagram': '#e1306c', 'LinkedIn': '#0077b5', 'Twitter': '#1da1f2',
        'Website': '#f59e0b', 'Other': '#9ca3af', 'Phone Call': '#84cc16',
        'Email': '#ef4444', 'Advertisement': '#f97316', 'SMS': '#14b8a6'
    };

    // Process detailed source data for individual lead sources
    let sourceCounts = [];

    if (data.analytics && data.analytics.detailed_source_counts && data.analytics.detailed_source_counts.length > 0) {
        // Use detailed source counts from analytics
        sourceCounts = data.analytics.detailed_source_counts.filter(item => item.count > 0);
    } else if (data.source_stats) {
        // Fallback to grouped source stats if detailed counts not available
        const sourceStats = data.source_stats;
        sourceCounts = [
            { source_of_lead: 'Google', count: sourceStats.google_count || 0 },
            { source_of_lead: 'Facebook', count: sourceStats.facebook_count || 0 },
            { source_of_lead: 'Other', count: sourceStats.other_count || 0 }
        ].filter(item => item.count > 0);
    }

    // Consolidate sources into 6 main categories
    const consolidatedSources = {
        'Google': 0,
        'Facebook': 0,
        'Direct': 0,
        'Referral': 0,
        'Portal': 0,
        'WhatsApp': 0
    };

    sourceCounts.forEach(item => {
        const source = (item.source_of_lead || '').toLowerCase();
        const count = parseInt(item.count) || 0;

        if (source.includes('google')) {
            consolidatedSources['Google'] += count;
        } else if (source.includes('facebook') || source.includes('fb')) {
            consolidatedSources['Facebook'] += count;
        } else if (source.includes('direct')) {
            consolidatedSources['Direct'] += count;
        } else if (source.includes('referral') || source.includes('refer')) {
            consolidatedSources['Referral'] += count;
        } else if (source.includes('portal') || source.includes('99acres') || source.includes('magicbricks') || source.includes('housing')) {
            consolidatedSources['Portal'] += count;
        } else if (source.includes('whatsapp') || source.includes('wa')) {
            consolidatedSources['WhatsApp'] += count;
        } else {
            // If doesn't match any category, add to Direct as default
            consolidatedSources['Direct'] += count;
        }
    });

    // Filter out categories with 0 count and add counts to labels
    const finalSourceLabels = [];
    const finalSourceData = [];
    const finalSourceColors = [];

    Object.entries(consolidatedSources).forEach(([label, count]) => {
        if (count > 0) {
            finalSourceLabels.push(`${label} (${count})`);
            finalSourceData.push(count);
            finalSourceColors.push(sourceColors[label] || '#9ca3af');
        }
    });

    leadSourceData = {
        labels: finalSourceLabels,
        datasets: [{
            label: 'Number of Leads',
            data: finalSourceData,
            backgroundColor: finalSourceColors,
            borderWidth: 2,
            borderRadius: 8
        }]
    };
}


function processAggregatedData(aggregatedData) {
    // Clear existing userData array
    userData.length = 0;

    // Process user-wise data while preserving user_type from allUsersData
    if (aggregatedData.user_wise_data) {
        aggregatedData.user_wise_data.forEach(user => {
            const leadStatusObj = {};
            user.leadStatus.forEach(status => {
                leadStatusObj[status.status] = parseInt(status.count);
            });

            // CRITICAL FIX: Find the original user data to preserve user_type
            const originalUserData = allUsersData.find(original =>
                original.tablename === user.tablename ||
                original.name === user.name ||
                original.email === user.email
            );

            userData.push({
                name: user.name,
                tablename: user.tablename,
                email: user.email,
                leads: user.leads,
                eoi: user.eoi,
                bookings: user.bookings,
                cancelled_bookings: user.cancelled_bookings || 0,
                conversion_rate: user.conversion_rate,
                leadStatus: leadStatusObj,
                // Preserve the original user_type
                user_type: originalUserData ? originalUserData.user_type : 'user',
                // Add quality range and status counts for Excel export
                quality_range: user.quality_range || user.leads || 0,
                fsv_count: user.fsv_count || 0,
                svd_count: user.svd_count || 0
            });
        });
    }

    // Process aggregated lead sources
    Object.keys(leadSources).forEach(key => delete leadSources[key]);
    if (aggregatedData.detailed_source_counts) {
        aggregatedData.detailed_source_counts.forEach(source => {
            leadSources[source.source_of_lead] = parseInt(source.count);
        });
    }
}
let originalUsersData = []; // Store the original user data with correct types


function fetchHierarchyData() {
    const selectedUser = getSelectedUserValue();

    let url = 'dashboard_data.php';
    if (selectedUser) url += `?user_id=${encodeURIComponent(selectedUser)}`;

    return fetchData(url).then(data => {
        if (data.status === 'success' && data.hierarchy) {
            hierarchyData = data.hierarchy;
            window.hierarchyData = data.hierarchy; // Make it globally accessible
            hierarchyReady = true;
            return data;
        } else {
            console.error('Failed to fetch hierarchy data');
            return null;
        }
    });
}

// Chart functions
function initCharts() {

    if (!leadStatusData || !leadSourceData) {
        return;
    }

    /* ✅ CRITICAL FIX — DESTROY OLD CHARTS */
    if (statusChart) {
        statusChart.destroy();
        statusChart = null;
    }

    if (sourceChart) {
        sourceChart.destroy();
        sourceChart = null;
    }

    const colors = getChartColors();

    // Show loaders before creating charts
    showChartLoader('statusChart');
    showChartLoader('sourceChart');
    setTimeout(initChartScrolling, 500);

    // Status Chart (Doughnut)
    const statusCtx = document.getElementById('statusChart');
    if (statusCtx) {
        setTimeout(() => {
            try {
                statusChart = new Chart(statusCtx, {
                    type: 'doughnut',
                    data: { ...leadStatusData, datasets: [{ ...leadStatusData.datasets[0], borderColor: colors.borderColor }] },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    color: colors.textColor,
                                    fontColor: colors.textColor, // Chart.js v2 fallback
                                    padding: window.innerWidth <= 768 ? 8 : 15,
                                    usePointStyle: true,
                                    font: {
                                        size: window.innerWidth <= 480 ? 9 : window.innerWidth <= 768 ? 10 : 11
                                    },
                                    boxWidth: window.innerWidth <= 480 ? 8 : 12,
                                    generateLabels: function (chart) {
                                        const data = chart.data;
                                        const currentColors = getChartColors();
                                        if (data.labels.length && data.datasets.length) {
                                            return data.labels.map((label, i) => {
                                                const value = data.datasets[0].data[i];
                                                let displayLabel = label;

                                                // Add the count value after the label
                                                displayLabel = `${label} (${value})`;

                                                if (window.innerWidth <= 480 && displayLabel.length > 12) {
                                                    displayLabel = label.substring(0, 8) + '...' + ` (${value})`;
                                                } else if (window.innerWidth <= 768 && displayLabel.length > 18) {
                                                    displayLabel = label.substring(0, 12) + '...' + ` (${value})`;
                                                }

                                                return {
                                                    text: displayLabel,
                                                    fillStyle: data.datasets[0].backgroundColor[i],
                                                    strokeStyle: data.datasets[0].borderColor || '#fff',
                                                    fontColor: currentColors.textColor,
                                                    lineWidth: 1,
                                                    hidden: false,
                                                    index: i
                                                };
                                            });
                                        }
                                        return [];
                                    }
                                }
                            },
                            tooltip: {
                                backgroundColor: colors.tooltipBg,
                                titleColor: colors.textColor,
                                bodyColor: colors.textColor,
                                bodyFontColor: colors.textColor, // v2 fallback
                                titleFontColor: colors.textColor, // v2 fallback
                                borderColor: colors.tooltipBorder,
                                borderWidth: 1,
                                titleFont: {
                                    size: window.innerWidth <= 768 ? 12 : 14
                                },
                                bodyFont: {
                                    size: window.innerWidth <= 768 ? 11 : 13
                                }
                            }
                        },
                        cutout: window.innerWidth <= 480 ? '50%' : '60%',
                        layout: {
                            padding: {
                                bottom: window.innerWidth <= 768 ? 5 : 10
                            }
                        }
                    }
                });
            } catch (error) {
                console.error("Error creating status chart:", error);
            } finally {
                // Hide loader after chart is created
                hideChartLoader('statusChart');
            }
        }, 100);
    }



    // Source Chart (Bar)
    const sourceCtx = document.getElementById('sourceChart');
    if (sourceCtx) {
        setTimeout(() => {
            try {
                sourceChart = new Chart(sourceCtx, {
                    type: 'bar',
                    data: { ...leadSourceData, datasets: [{ ...leadSourceData.datasets[0], borderColor: colors.borderColor }] },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                backgroundColor: colors.tooltipBg,
                                titleColor: colors.textColor,
                                bodyColor: colors.textColor,
                                borderColor: colors.tooltipBorder,
                                borderWidth: 1,
                                callbacks: {
                                    title: function (context) {
                                        // Show full label in tooltip
                                        return context[0].label || '';
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    color: colors.textColor,
                                    font: {
                                        size: window.innerWidth <= 768 ? 10 : 12
                                    }
                                },
                                grid: { color: colors.gridColor }
                            },
                            x: {
                                ticks: {
                                    color: colors.textColor,
                                    // Force rotation for desktop view
                                    maxRotation: window.innerWidth > 1024 ? 45 : (leadSourceData.labels.length > 5 || window.innerWidth <= 768 ? 90 : 45),
                                    minRotation: window.innerWidth > 1024 ? 45 : (leadSourceData.labels.length > 5 || window.innerWidth <= 768 ? 90 : 45),
                                    font: {
                                        size: window.innerWidth <= 480 ? 8 : window.innerWidth <= 768 ? 9 : 11
                                    },
                                    // Enhanced callback for better mobile visibility
                                    callback: function (value, index, ticks) {
                                        const label = this.getLabelForValue(value);

                                        if (window.innerWidth <= 480) {
                                            // Very small screens: show abbreviated label
                                            if (label.length > 15) {
                                                return label.substring(0, 10) + '...';
                                            }
                                            return label;
                                        } else if (window.innerWidth <= 768) {
                                            // Medium screens: show abbreviated label
                                            if (label.length > 18) {
                                                return label.substring(0, 15) + '...';
                                            }
                                            return label;
                                        } else {
                                            // Desktop: show full label
                                            return label;
                                        }
                                    },
                                    // Don't skip labels to show all sources
                                    autoSkip: false,
                                    maxTicksLimit: Math.max(leadSourceData.labels.length, 15)
                                },
                                grid: { color: colors.gridColor }
                            }
                        },
                        layout: {
                            padding: {
                                // Increase bottom padding based on number of sources and rotation
                                bottom: leadSourceData.labels.length > 5 ?
                                    (window.innerWidth <= 480 ? 35 : window.innerWidth <= 768 ? 30 : 25) :
                                    (window.innerWidth <= 480 ? 25 : window.innerWidth <= 768 ? 20 : 15),
                                left: window.innerWidth <= 480 ? 5 : 10,
                                right: window.innerWidth <= 480 ? 5 : 10,
                                top: window.innerWidth <= 480 ? 5 : 10
                            }
                        }
                    }
                });
            } catch (error) {
                console.error("Error creating source chart:", error);
            } finally {
                hideChartLoader('sourceChart');
            }
        }, 200);
    }


    updateSummaryCards();
    /* ✅ GLOBAL LOADER FAILSAFE */
    setTimeout(() => {

        hideChartLoader('statusChart');
        hideChartLoader('sourceChart');

    }, 2000);

}

const mobileCSSFix = `
<style>
.user-filter-section {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 20px;
    flex-wrap: wrap;
}

.user-filter-section .filter-controls-container {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    width: 100%;
    gap: 20px;
}

.user-filter-section .multi-select-container {
    flex: 1;
    min-width: 300px;
}
@media(min-width: 768px) and (max-width: 961px) {
.user-filter-section .month-filter-select {
    margin-left: unset !important;
}
    .filter-controls-container{
        justify-content: center !important;
    }
}

.user-filter-section .month-filter-select {
    display: flex;
    gap: 8px;
    align-items: center;
    
}

.user-filter-section .month-filter-select input[type="date"] {
    padding: 8px 12px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    background: white;
    font-size: 14px;
    min-width: 120px;
}

.user-filter-section .month-filter-select select{
    padding: 11px 12px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    background: white;
    font-size: 14px;
    min-width: 120px;
}

.user-filter-section .month-filter-select button {
    padding: 8px 12px;
    background: white;
    border-radius: 7px;
    cursor: pointer;
    font-size: 12px;
    white-space: nowrap;
}

/* Project filter styles */
.project-filter-container {
    flex: 1;
    min-width: 200px;
    max-width: 250px;
}

.project-multi-select {
    position: relative;
    width: 100%;
}

.project-dropdown-container {
    position: relative;
    width: 100%;
}

.project-dropdown-input {
    width: 100% !important;
    box-sizing: border-box;
}

.project-dropdown-options {
    background: white;
    width: 100% !important;
    box-sizing: border-box;
}

.project-selected-tag {
    display: inline-flex;
    align-items: center;
    background: #3b82f6;
    color: white;
    padding: 4px 8px;
    border-radius: 40px;
    font-size: 12px;
    gap: 4px;
    margin: 4px;
    min-width: fit-content;
}

.project-selected-tag .remove-project {
    cursor: pointer;
    font-weight: bold;
    margin-left: 4px;
    padding: 2px 4px;
    border-radius: 2px;
}

.project-selected-tag .remove-project:hover {
    background: rgba(255, 255, 255, 0.2);
}

.project-option {
    padding: 8px 12px;
    cursor: pointer;
    border-bottom: 1px solid #f3f4f6;
    font-size: 13px;
    display: flex;
    align-items: center;
    gap: 8px;
    user-select: none;
}

.project-option:hover {
    background: #f3f4f6;
}

.project-option.selected {
    background: #eff6ff;
    color: #2563eb;
}

.project-option input[type="checkbox"] {
    margin: 0;
    pointer-events: auto;
}

.project-option label {
    cursor: pointer;
    flex: 1;
    pointer-events: auto;
}

/* Ensure proper filter layout */
.filter-controls-container {
    display: flex !important;
    flex-wrap: wrap !important;
    gap: 15px !important;
    align-items: flex-start !important;
    width: 100% !important;
    max-width: 100% !important;
    box-sizing: border-box !important;
}

/* Desktop layout optimization */
@media (min-width: 769px) {
    .filter-controls-container {
        align-items: flex-start !important;
    }
    
    .multi-select-container {
        flex: 2 !important;
        min-width: 300px !important;
    }
    
    .project-filter-container {
        flex: 1 !important;
        min-width: 200px !important;
        max-width: 250px !important;
    }
    
    .month-filter-select {
        display: flex;
        flex: 0 0 auto !important;
        min-width: 180px !important;
    }
}

/* Large desktop layout */
@media (min-width: 1200px) {
    .multi-select-container {
        flex: 2.5 !important;
        max-width: 400px !important;
    }
    
    .project-filter-container {
        flex: 1.5 !important;
        max-width: 300px !important;
    }
}

/* Mobile view */
@media (max-width: 768px) {
    .user-filter-section .filter-controls-container {
        flex-direction: column !important;
        gap: 15px !important;
    }
    
    .user-filter-section .month-filter-select select{
        padding: 8px 12px;
    }
    
    .user-filter-section .month-filter-select {
        margin-left: 0;
        width: 100%;
        justify-content: space-between;
    }
    
    .user-filter-section .month-filter-select select,
    .user-filter-section .month-filter-select input[type="date"] {
        flex: 1;
        min-width: 0;
    }
    
    .user-filter-section .multi-select-container {
        min-width: 100% !important;
        max-width: 100% !important;
        flex: none !important;
    }
    
    .project-filter-container {
        min-width: 100% !important;
        max-width: 100% !important;
        flex: none !important;
    }
}

/* Small mobile view for project filter */
@media (max-width: 480px) {
    .user-filter-section .filter-controls-container {
        flex-direction: column;
        gap: 10px;
    }
    
    .project-filter-container {
        order: 2; /* Place project filter after users but before date filters */
    }
    
    .month-filter-select {
        order: 3;
    }
}

/* Small mobile view */
@media (max-width: 480px) {
    .user-filter-section .month-filter-select {
        flex-direction: column;
        gap: 10px;
    }
    
    .user-filter-section .month-filter-select select,
    .user-filter-section .month-filter-select input[type="date"],
    .user-filter-section .month-filter-select button {
        width: 100%;
    }
}

/* Keep popup custom range controls in a single row on mobile */
#popupDateRangeFilter {
    align-items: center;
    justify-content: flex-start !important;
    gap: 6px;
    flex-wrap: nowrap !important;
    overflow-x: auto;
}

#popupDateRangeFilter input[type="date"] {
    width: auto !important;
    min-width: 120px;
    flex: 1 1 0;
}

#popupDateRangeFilter button {
    width: auto !important;
    flex: 0 0 auto;
}

/* Enable scrolling for all chart containers across all screen sizes */
.chart-container {
    overflow-x: auto;
    overflow-y: auto;
    max-width: 100%;
    max-height: 400px; /* Set a reasonable max height */
    scrollbar-width: thin;
    scrollbar-color: #888 #f1f1f1;
}

/* Webkit scrollbar styling for better appearance */
.chart-container::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}

.chart-container::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
}

.chart-container::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 4px;
}

.chart-container::-webkit-scrollbar-thumb:hover {
    background: #555;
}

.chart-wrapper {
    min-width: 280px;
    min-height: 200px;
    position: relative;
}

/* Ensure chart canvas maintains minimum dimensions for readability */
.chart-container canvas {
    min-width: 280px !important;
    min-height: 200px !important;
}

@media (max-width: 768px) {
    .chart-container {
        overflow-x: auto;
        overflow-y: auto;
        max-height: 300px; /* Smaller for mobile */
    }
    
    .chart-wrapper {
        min-width: 250px;
        min-height: 180px;
        position: relative;
    }
    
    /* Ensure chart canvas doesn't get too compressed */
    .chart-container canvas {
        min-width: 250px !important;
        min-height: 180px !important;
    }
    
    /* Better mobile spacing for charts */
    .chart-card {
        padding: 15px 10px;
        margin-bottom: 20px;
    }
    
    .chart-title {
        font-size: 14px;
        margin-bottom: 15px;
    }
}

@media (max-width: 480px) {
    .chart-container {
        max-height: 250px;
    }
    
    .chart-wrapper {
        min-width: 220px;
        min-height: 160px;
    }
    
    .chart-container canvas {
        min-width: 220px !important;
        min-height: 160px !important;
    }
}

@media (max-width: 556px) {
    .chart-container {
        max-height: 200px;
        overflow-x: auto;
        overflow-y: auto;
        min-width: 0;
    }
    
    .chart-wrapper {
        width: 100%;
        height: 100%;
        min-width: 0;
        min-height: 0;
        max-width: 100%;
    }
    
    .chart-container canvas {
        width: 100% !important;
        height: auto !important;
        min-width: 0 !important;
        min-height: 0 !important;
        max-width: 100% !important;
    }
    
    .chart-overall {
        padding: 6px !important;
        min-width: 0;
        width: 100%;
        max-width: 100%;
    }
    
    /* Specifically target Overall Lead Status Distribution */
    #overallLeadStatusChart-wrapper {
        width: 100% !important;
        height: 180px !important;
        min-width: 0 !important;
        max-width: 100% !important;
    }
    
    #overallLeadStatusChart {
        width: 100% !important;
        height: 100% !important;
        min-width: 0 !important;
        max-width: 100% !important;
        padding-right: 15px;
    }
    
    /* Fix charts-section grid layout for very small screens */
    .charts-section {
        display: grid;
        grid-template-columns: 1fr !important;
        gap: 15px;
        margin-bottom: 20px;
    }
    
    .chart-overall {
        padding: 6px !important;
        min-height: 180px !important;
        width: 100%;
        max-width: 100%;
        overflow: hidden;
    }
}

@media (max-width: 480px) {
    .chart-container {
        max-height: 180px;
        overflow-x: auto;
        overflow-y: auto;
        min-width: 0;
        width: 100%;
    }
    
    .chart-wrapper {
        width: 100%;
        height: 100%;
        min-width: 0;
        min-height: 0;
        max-width: 100%;
    }
    
    .chart-container canvas {
        width: 100% !important;
        height: auto !important;
        min-width: 0 !important;
        min-height: 0 !important;
        max-width: 100% !important;
    }
    
    .chart-overall {
        padding: 4px !important;
        min-height: 160px !important;
        width: 100%;
        max-width: 100%;
        overflow: hidden;
    }
    
    /* Specifically target Overall Lead Status Distribution for 480px */
    #overallLeadStatusChart-wrapper {
        width: 100% !important;
        height: 160px !important;
        min-width: 0 !important;
        max-width: 100% !important;
    }
    
    #overallLeadStatusChart {
        width: 100% !important;
        height: 100% !important;
        min-width: 0 !important;
        max-width: 100% !important;
    }
    
    /* Ensure charts section is single column and full width */
    .charts-section {
        display: grid;
        grid-template-columns: 1fr !important;
        gap: 10px;
        margin-bottom: 15px;
    }
    
    .chart-overall h3 {
        font-size: 14px;
        margin-bottom: 10px;
    }
}
    
    .chart-card {
        padding: 10px 8px;
    }
    
    .chart-title {
        font-size: 13px;
        margin-bottom: 12px;
    }
}

/* Additional styles for popup dashboard charts */
.dashboard-popup .chart-container {
    overflow-x: auto;
    overflow-y: auto;
    max-width: 100%;
    max-height: 400px;
}

.dashboard-popup .chart-wrapper {
    min-width: 320px;
    min-height: 240px;
}

.dashboard-popup .chart-container canvas {
    min-width: 320px !important;
    min-height: 240px !important;
}

@media (max-width: 768px) {
    .dashboard-popup .chart-container {
        max-height: 350px;
    }
    
    .dashboard-popup .chart-wrapper {
        min-width: 320px;
        min-height: 250px;
    }
    
    .dashboard-popup .chart-container canvas {
        min-width: 320px !important;
        min-height: 250px !important;
    }
}

/* Charts section layout */
.charts-section {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.chart-overall {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    padding: 20px;
    min-height: 420px;
}

/* User card chart containers */
.user-card .user-chart {
    position: relative;
    height: 335px;
    overflow-x: auto;
    overflow-y: auto;
    border-radius: 8px;
    background: #f9fafb;
    padding: 10px;
}

.user-card .user-chart canvas {
    min-width: 250px !important;
    min-height: 180px !important;
}

.user-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    padding: 20px;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.user-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.chart-overall h3 {
    margin-bottom: 20px;
    text-align: center;
    color: #374151;
    font-size: 18px;
}

/* Ensure all chart containers in popup have scrolling */
.dashboard-popup .chart-container.chart-overall {
    overflow-x: auto;
    overflow-y: auto;
    max-height: 500px;
}

.dashboard-popup .chart-container.chart-overall .chart-wrapper {
    min-width: 400px;
    min-height: 350px;
    position: relative;
}

.dashboard-popup .chart-container.chart-overall canvas {
    min-width: 400px !important;
    min-height: 350px !important;
}

@media (max-width: 1024px) {
    .charts-section {
        grid-template-columns: 1fr;
        gap: 20px;
    }
}

@media (max-width: 768px) {
    .charts-section {
        gap: 15px;
    }
    
    .dashboard-popup .chart-container.chart-overall {
        height: 400px;
        padding: 15px;
    }
    
    .dashboard-popup .chart-container.chart-overall .chart-wrapper {
        min-width: 320px;
        min-height: 280px;
    }
    
    .dashboard-popup .chart-container.chart-overall canvas {
        min-width: 320px !important;
        min-height: 280px !important;
    }
    
    .chart-overall h3 {
        font-size: 16px;
        margin-bottom: 15px;
    }
}

/* Additional scrolling support for all chart types */
.chart-card .chart-container {
    overflow-x: auto;
    overflow-y: auto;
    max-height: 400px;
}

.chart-card .chart-wrapper {
    min-width: 300px;
    min-height: 250px;
    position: relative;
}

.chart-card canvas {
    min-width: 300px !important;
    min-height: 250px !important;
}

/* Filtered and hierarchy chart containers */
.filtered-chart-container,
.hierarchy-chart-container {
    overflow-x: auto;
    overflow-y: auto;
    max-height: 250px;
    border-radius: 8px;
    background: #f9fafb;
    padding: 10px;
}

.filtered-chart-container canvas,
.hierarchy-chart-container canvas {
    min-width: 280px !important;
    min-height: 200px !important;
}

/* Ensure all dynamically created chart containers have scrolling */
div[id*="Chart"] {
    overflow-x: auto;
    overflow-y: auto;
}

canvas[id*="Chart"] {
    min-width: 250px !important;
    min-height: 200px !important;
}

/* Team member chart containers */
canvas[id^="teamUserChart"] {
    min-width: 280px !important;
    min-height: 200px !important;
}

/* Universal chart container scrolling - catch all */
div:has(canvas) {
    overflow-x: auto;
    overflow-y: auto;
}

/* Ensure chart containers have proper styling */
.chart-container,
[class*="chart-container"],
[class*="chart-wrapper"] {
    scrollbar-width: thin;
    scrollbar-color: #888 #f1f1f1;
}

.chart-container::-webkit-scrollbar,
[class*="chart-container"]::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}

.chart-container::-webkit-scrollbar-track,
[class*="chart-container"]::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 3px;
}

.chart-container::-webkit-scrollbar-thumb,
[class*="chart-container"]::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 3px;
}

.chart-container::-webkit-scrollbar-thumb:hover,
[class*="chart-container"]::-webkit-scrollbar-thumb:hover {
    background: #555;
}

/* Make sure chart responsiveness works with scrolling */
.chart-wrapper {
    position: relative;
    width: 100%;
    height: 100%;
}

/* Ensure all chart canvases maintain minimum sizes for readability */
canvas {
    display: block;
    box-sizing: border-box;
}

/* Responsive adjustments for smaller screens */
@media (max-width: 480px) {
    .chart-card .chart-container,
    .user-card .user-chart,
    .filtered-chart-container,
    .hierarchy-chart-container {
        max-height: 300px;
    }
    
    .chart-card canvas,
    .user-card .user-chart canvas,
    .filtered-chart-container canvas,
    .hierarchy-chart-container canvas,
    canvas[id*="Chart"] {
        min-width: 250px !important;
        min-height: 180px !important;
       
    }
}

@media (max-width: 400px) {
 .chart-card canvas,
    .user-card .user-chart canvas,
    .filtered-chart-container canvas,
    .hierarchy-chart-container canvas,
    canvas[id*="Chart"]{
 padding-right: 35px;
 padding-left: 5px;
}
}
</style>
`;

// Add the mobile CSS to the document head if not already present
if (!document.getElementById('mobileChartCSS')) {
    const styleElement = document.createElement('style');
    styleElement.id = 'mobileChartCSS';
    styleElement.textContent = mobileCSSFix.replace(/<\/?style>/g, ''); // Remove style tags and use just the CSS
    document.head.appendChild(styleElement);
}


function updateSummaryCards() {
    if (!analyticsData) {
        console.warn('updateSummaryCards: analyticsData is not available');
        return;
    }

    const summaryCards = document.querySelectorAll('.summary-card');
    if (summaryCards.length >= 4) {
        const totalLeadsNumber = summaryCards[0].querySelector('.summary-number');
        const conversionRateNumber = summaryCards[1].querySelector('.summary-number');
        const siteVisitsDoneNumber = summaryCards[2].querySelector('.summary-number');
        const convertedLeadsNumber = summaryCards[3].querySelector('.summary-number');

        // Get total leads from financial year data
        const totalLeads = parseInt(analyticsData.myLeads) || 0;

        // Calculate converted leads from detailed status counts
        let convertedLeads = 0;

        // First, check if we have detailed_status_counts in the direct response
        if (analyticsData.detailed_status_counts && Array.isArray(analyticsData.detailed_status_counts)) {
            const convertedStatus = analyticsData.detailed_status_counts.find(
                item => item.status && item.status.toLowerCase() === 'converted'
            );
            convertedLeads = convertedStatus ? parseInt(convertedStatus.count) || 0 : 0;
        }
        // Then check analytics.detailed_status_counts
        else if (analyticsData.analytics && analyticsData.analytics.detailed_status_counts) {
            const convertedStatus = analyticsData.analytics.detailed_status_counts.find(
                item => item.status && item.status.toLowerCase() === 'converted'
            );
            convertedLeads = convertedStatus ? parseInt(convertedStatus.count) || 0 : 0;
        }
        // Fallback to analytics.converted_leads if available
        else if (analyticsData.analytics?.converted_leads) {
            convertedLeads = parseInt(analyticsData.analytics.converted_leads) || 0;
        }
        // Last fallback to total_bookings
        else if (analyticsData.total_bookings) {
            convertedLeads = parseInt(analyticsData.total_bookings) || 0;
        }

        // Get site visits done count
        let siteVisitsDone = 0;

        // First check direct detailed_status_counts
        if (analyticsData.detailed_status_counts && Array.isArray(analyticsData.detailed_status_counts)) {
            const siteVisitStatus = analyticsData.detailed_status_counts.find(
                item => item.status && item.status.toLowerCase() === 'site visit done'
            );
            siteVisitsDone = siteVisitStatus ? parseInt(siteVisitStatus.count) || 0 : 0;
        }
        // Then check status_counts
        else if (analyticsData.status_counts?.site_visit_done_count) {
            siteVisitsDone = parseInt(analyticsData.status_counts.site_visit_done_count) || 0;
        }
        // Then check analytics.detailed_status_counts
        else if (analyticsData.analytics && analyticsData.analytics.detailed_status_counts) {
            const siteVisitStatus = analyticsData.analytics.detailed_status_counts.find(
                item => item.status && item.status.toLowerCase() === 'site visit done'
            );
            siteVisitsDone = siteVisitStatus ? parseInt(siteVisitStatus.count) || 0 : 0;
        }

        // Update Total Leads
        if (totalLeadsNumber) {
            totalLeadsNumber.textContent = totalLeads;
        }

        // Update Conversion Rate
        if (conversionRateNumber) {
            const percentage = formatPercentage(convertedLeads, totalLeads, 2);
            conversionRateNumber.textContent = percentage;
            conversionRateNumber.setAttribute('title', `${convertedLeads}/${totalLeads}`);
        }

        // Update Site Visits Done
        if (siteVisitsDoneNumber) {
            siteVisitsDoneNumber.textContent = siteVisitsDone;
            const label = siteVisitsDoneNumber.parentElement.querySelector('.summary-label');
            if (label) label.textContent = 'Site Visits Done';
        }

        // Update Converted Leads
        if (convertedLeadsNumber) {
            convertedLeadsNumber.textContent = convertedLeads;
            const label = convertedLeadsNumber.parentElement.querySelector('.summary-label');
            if (label) label.textContent = 'Converted Leads';
        }
    }

    // Update popup title to show financial year
    updatePopupTitle();
}

function updatePopupTitle() {
    const popupTitle = document.querySelector('.popup-title');
    if (popupTitle) {
        const financialYear = getCurrentFinancialYearText();
        popupTitle.textContent = `Lead Analytics Dashboard - FY ${financialYear}`;
    }
}

function getCurrentFinancialYearText() {
    const dates = getCurrentFinancialYear();
    const startYear = dates.start.split('-')[0];
    const endYear = dates.end.split('-')[0];
    return `${startYear}-${endYear}`;
}


function updateAggregatedSummaryCards() {
    if (!aggregatedAnalyticsData) return;

    const summaryCards = document.querySelectorAll('.summary-card');
    if (summaryCards.length >= 4) {
        const totalLeadsNumber = summaryCards[0].querySelector('.summary-number');
        const conversionRateNumber = summaryCards[1].querySelector('.summary-number');
        const todayFollowUpNumber = summaryCards[2].querySelector('.summary-number');
        const siteVisitsPendingNumber = summaryCards[3].querySelector('.summary-number');

        const totals = extractAggregatedTotals(aggregatedAnalyticsData);
        if (totalLeadsNumber) totalLeadsNumber.textContent = totals.totalLeads || 0;
        if (conversionRateNumber) {
            // Prefer raw totals if available to compute exact percentage
            const totalLeads = aggregatedAnalyticsData.total_leads ?? aggregatedAnalyticsData.totalLeads ?? 0;
            const converted = aggregatedAnalyticsData.converted_leads ?? aggregatedAnalyticsData.total_bookings ?? 0;
            conversionRateNumber.textContent = formatPercentage(converted, totalLeads, 2);
            conversionRateNumber.setAttribute('title', `${converted}/${totalLeads}`);
        }
        if (todayFollowUpNumber) todayFollowUpNumber.textContent = aggregatedAnalyticsData.todays_followup || 0;
        if (siteVisitsPendingNumber) siteVisitsPendingNumber.textContent = aggregatedAnalyticsData.site_visits_pending || 0;
    }
}

function updateChartTheme() {
    const colors = getChartColors();
    const isDesktop = window.innerWidth > 768;

    if (statusChart) {
        statusChart.data.datasets[0].borderColor = colors.borderColor;
        statusChart.options.plugins.legend.labels.color = colors.textColor;
        statusChart.options.plugins.legend.labels.fontColor = colors.textColor; // v2 fallback
        statusChart.options.plugins.legend.labels.font.size = isDesktop ? 11 : (window.innerWidth <= 480 ? 9 : 10);
        statusChart.options.plugins.legend.labels.padding = isDesktop ? 10 : (window.innerWidth <= 768 ? 8 : 10);
        statusChart.options.plugins.tooltip.backgroundColor = colors.tooltipBg;
        statusChart.options.plugins.tooltip.titleColor = colors.textColor;
        statusChart.options.plugins.tooltip.bodyColor = colors.textColor;
        statusChart.options.plugins.tooltip.bodyFontColor = colors.textColor; // v2 fallback
        statusChart.options.plugins.tooltip.titleFontColor = colors.textColor; // v2 fallback
        statusChart.options.plugins.tooltip.borderColor = colors.tooltipBorder;
        statusChart.options.cutout = isDesktop ? '55%' : (window.innerWidth <= 480 ? '40%' : '50%');
        statusChart.update();
    }

    if (sourceChart) {
        sourceChart.data.datasets[0].borderColor = colors.borderColor;
        sourceChart.options.plugins.tooltip.backgroundColor = colors.tooltipBg;
        sourceChart.options.plugins.tooltip.titleColor = colors.textColor;
        sourceChart.options.plugins.tooltip.bodyColor = colors.textColor;
        sourceChart.options.plugins.tooltip.borderColor = colors.tooltipBorder;
        sourceChart.options.scales.y.ticks.color = colors.textColor;
        sourceChart.options.scales.y.ticks.font.size = isDesktop ? 11 : (window.innerWidth <= 768 ? 10 : 11);
        sourceChart.options.scales.y.grid.color = colors.gridColor;
        sourceChart.options.scales.x.ticks.color = colors.textColor;
        sourceChart.options.scales.x.ticks.font.size = isDesktop ? 10 : (window.innerWidth <= 768 ? 9 : 10);

        sourceChart.options.scales.x.grid.color = colors.gridColor;
        sourceChart.options.layout.padding.bottom = isDesktop ? 15 : (window.innerWidth <= 768 ? 15 : 20);
        sourceChart.update();
    }

    // Update dashboard popup charts if they exist
    if (overallLeadStatusChart) {
        overallLeadStatusChart.options.plugins.legend.labels.color = colors.textColor;
        overallLeadStatusChart.options.plugins.legend.labels.fontColor = colors.textColor; // v2 fallback
        overallLeadStatusChart.options.plugins.legend.labels.font.size = isDesktop ? 11 : (window.innerWidth <= 768 ? 10 : 11);
        overallLeadStatusChart.options.plugins.legend.labels.padding = isDesktop ? 10 : 15;
        overallLeadStatusChart.options.plugins.tooltip.backgroundColor = colors.tooltipBg;
        overallLeadStatusChart.options.plugins.tooltip.titleColor = colors.textColor;
        overallLeadStatusChart.options.plugins.tooltip.bodyColor = colors.textColor;
        overallLeadStatusChart.options.plugins.tooltip.bodyFontColor = colors.textColor; // v2 fallback
        overallLeadStatusChart.options.plugins.tooltip.titleFontColor = colors.textColor; // v2 fallback
        overallLeadStatusChart.options.plugins.tooltip.borderColor = colors.tooltipBorder;
        overallLeadStatusChart.options.cutout = isDesktop ? '55%' : '50%';
        overallLeadStatusChart.update();
    }

    if (leadSourceChart) {
        if (leadSourceChart.data && leadSourceChart.data.datasets && leadSourceChart.data.datasets[0]) {
            leadSourceChart.data.datasets[0].backgroundColor = getPaletteForLength(colors.palette, leadSourceChart.data.datasets[0].data.length);
            leadSourceChart.data.datasets[0].borderColor = colors.borderColor;
        }
        leadSourceChart.options.plugins.legend.labels.color = colors.textColor;
        leadSourceChart.options.plugins.legend.labels.fontColor = colors.textColor; // v2 fallback
        leadSourceChart.options.plugins.legend.labels.font.size = isDesktop ? 11 : (window.innerWidth <= 768 ? 10 : 11);
        leadSourceChart.options.plugins.legend.labels.padding = isDesktop ? 8 : 15;
        leadSourceChart.options.plugins.tooltip.backgroundColor = colors.tooltipBg;
        leadSourceChart.options.plugins.tooltip.titleColor = colors.textColor;
        leadSourceChart.options.plugins.tooltip.bodyColor = colors.textColor;
        leadSourceChart.options.plugins.tooltip.bodyFontColor = colors.textColor; // v2 fallback
        leadSourceChart.options.plugins.tooltip.titleFontColor = colors.textColor; // v2 fallback
        leadSourceChart.options.plugins.tooltip.borderColor = colors.tooltipBorder;
        leadSourceChart.options.cutout = isDesktop ? '55%' : '50%';
        leadSourceChart.update();
    }
}


// Popup functions
function openPopup() {
    document.getElementById('popup').classList.remove('hidden');

    const popupContent = document.querySelector('.popup-content');
    if (popupContent) {
        popupContent.innerHTML = '<div style="text-align: center; padding: 2rem;"><div style="font-size: 1.2rem; margin-bottom: 1rem;">Loading Analytics...</div><div style="color: #6b7280;">Please wait while we fetch your data</div></div>';
    }

    // Ensure we're fetching data for the currently selected user
    fetchAnalyticsData().then(data => {
        if (data && leadStatusData && leadSourceData) {
            restorePopupContent();
            setTimeout(initCharts, 100);
        } else if (popupContent) {
            popupContent.innerHTML = '<div style="text-align: center; padding: 2rem; color: #ef4444;"><div style="font-size: 1.2rem; margin-bottom: 1rem;">Error Loading Data</div><div>Unable to fetch analytics data. Please try again.</div></div>';
        }
    });
}

function restorePopupContent() {
    const popupContent = document.querySelector('.popup-content');
    if (popupContent) {
        const financialYear = getCurrentFinancialYearText();

        popupContent.innerHTML = `
            <div class="stats-grid1">
                <div class="chart-card">
                    <h3 class="chart-title">Lead Status Distribution (FY ${financialYear})</h3>
                    <div class="chart-container">
                        <div class="chart-wrapper">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="chart-card">
                    <h3 class="chart-title">Lead Source Analytics (FY ${financialYear})</h3>
                    <div class="chart-container">
                        <div class="chart-wrapper">
                            <canvas id="sourceChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="summary-cards">
                <div class="summary-card">
                    <div class="summary-number">0</div>
                    <div class="summary-label">Total Leads (FY ${financialYear})</div>
                </div>
                <div class="summary-card">
                    <div class="summary-number">0%</div>
                    <div class="summary-label">Conversion Rate</div>
                </div>
                <div class="summary-card">
                    <div class="summary-number">0</div>
                    <div class="summary-label">Site Visits Done</div>
                </div>
                <div class="summary-card">
                    <div class="summary-number">0</div>
                    <div class="summary-label">Converted Leads</div>
                </div>
            </div>
        `;
    }
}




function closePopup() {
    document.getElementById('popup').classList.add('hidden');
    if (statusChart) { statusChart.destroy(); statusChart = null; }
    if (sourceChart) { sourceChart.destroy(); sourceChart = null; }
}

// Enhanced Dashboard Popup Functions
function openDashboard() {
    // Keep popup masked until all data/charts render
    showDashboardPopupLoader('Loading team dashboard...');
    document.getElementById('dashboardPopup').classList.add('active');

    // Clear any previous inline loader markup; overlay loader will handle the wait state
    const dashboardContent = document.querySelector('#dashboardPopup .dashboard-content2');
    if (dashboardContent) {
        dashboardContent.innerHTML = '';
    }

    // Initialize popup filters with current month/year
    const now = new Date();
    const currentMonth = now.getMonth() + 1;
    const currentYear = now.getFullYear();

    currentSelectedMonth = parseInt(currentMonth);
    currentSelectedYear = parseInt(currentYear);

    // Set default values for popup selectors (they're now in the user-filter-section)
    const popupMonthSelect = document.getElementById('popupMonthSelect');
    const popupYearSelect = document.getElementById('popupYearSelect');

    if (popupMonthSelect) popupMonthSelect.value = currentMonth;
    if (popupYearSelect) popupYearSelect.value = currentYear;

    // Ensure date range filter is hidden initially
    const popupDateRangeFilter = document.getElementById('popupDateRangeFilter');
    const popupMonthYearSelectors = document.getElementById('popupMonthYearSelectors');

    if (popupDateRangeFilter) popupDateRangeFilter.style.display = 'none';
    if (popupMonthYearSelectors) popupMonthYearSelectors.style.display = 'flex';

    // Add user filter section to popup-filters (this now includes the month filter)
    addUserFilterToPopupFilters();

    // Set up popup filter event listeners
    setupPopupFilterListeners();

    // Load dashboard data immediately; fetch user/hierarchy in parallel to avoid blocking UI
    loadPopupDashboardData(currentMonth, currentYear);

    const assignedPromise = assignedUsersReady ? Promise.resolve(allUsersData) : fetchAssignedUsers();
    const hierarchyPromise = hierarchyReady ? Promise.resolve(hierarchyData) : fetchHierarchyData();

    Promise.allSettled([assignedPromise, hierarchyPromise]).then(() => {
        if (typeof populateDropdownOptions === 'function') {
            populateDropdownOptions();
        }
    }).catch(error => {
        console.error('Non-blocking user/hierarchy fetch error:', error);
    });
}


function addUserFilterToPopupFilters() {
    const popupFilters = document.querySelector('.popup-filters');
    if (!popupFilters) return;

    // FIX: Ensure popup filters stack above the dashboard content (especially for dark mode backdrop-filters)
    popupFilters.style.position = 'relative';
    popupFilters.style.zIndex = '20';


    // Check if filter already exists
    if (popupFilters.querySelector('.user-filter-section')) {
        return;
    }

    // Create and add the user filter section with integrated month filter
    const userFilterSection = document.createElement('div');
    userFilterSection.className = 'user-filter-section';
    userFilterSection.style.cssText = `
        background: #f8fafc;
        padding: 0px;
        border-radius: 12px;
        margin: 0px;
        align-items: center;
        border: 1px solid #e2e8f0;
        position: relative;
        z-index: 20;
    `;

    // Close mobile filter panel when clicking outside of it
    document.addEventListener('click', function (e) {
        const clickedInsidePortaledDropdown = !!e.target.closest('#ui-portal .custom-dropdown-options');
        if (window.innerWidth <= 768 && !userFilterSection.contains(e.target) && !clickedInsidePortaledDropdown) {
            const content = userFilterSection.querySelector('.filter-section-content');
            const icon = userFilterSection.querySelector('.filter-toggle-icon');
            const header = userFilterSection.querySelector('.filter-section-header');

            if (content && content.classList.contains('expanded')) {
                content.style.maxHeight = '0px';
                content.style.opacity = '0';
                content.style.padding = '0 16px';
                if (icon) icon.style.transform = 'rotate(0deg)';
                if (header) header.style.borderRadius = '12px';
                content.classList.remove('expanded');

                setTimeout(() => {
                    if (!content.classList.contains('expanded')) {
                        content.style.display = 'none';
                    }
                }, 400);
            }
        }
    });


    userFilterSection.innerHTML = `
    <!-- Collapsible Header -->
    <div class="filter-section-header" onclick="toggleFilterSection()" style="
         display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 14px;
        gap: 6px;
        cursor: pointer;
        border-radius: 12px;
        border-bottom: 1px solid #e2e8f0;
        transition: background 0.3s ease;
    ">
        <div style="display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-filter" style="color: #64748b; font-size: 14px;"></i>
            <span style="font-weight: 600; color: #334155; font-size: 14px;">Filters</span>
        </div>
        <i class="fas fa-chevron-down filter-toggle-icon" style="
            color: #64748b; 
            font-size: 12px;
            transition: transform 0.3s ease;
            display: none;
        "></i>
    </div>

    <!-- Collapsible Content - Initially collapsed on mobile -->
    <div class="filter-section-content" style="
        max-height: 0px;
        max-width: 100%;
        transition: max-height 0.4s ease, opacity 0.3s ease;
        opacity: 0;
        padding: 0 16px;
    ">
        <!-- Selected tags at the top - BOTH user and project tags together -->
        <div class="selected-tags"></div>
        <div class="project-selected-tags" id="projectSelectedTags" style="
                display: flex;
    flex-wrap: nowrap;
    gap: 6px;
    max-height: 60px;
    overflow-x: auto;

        "></div>
        
        <div class="filter-controls-container" style="
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: flex-start;
            width: 100%;
        ">
            <!-- Multi-select dropdown -->
            <div class="multi-select-container" style="flex: 1; min-width: 250px; max-width: 300px !important;">
                <div style="margin-bottom: 8px; color: #6b7280; font-size: 14px;">
                    Select users to view their individual or team performance:
                </div>
                <!-- Multi-select content will be inserted here by initializeMultiSelect() -->
            </div>
            
            <!-- Project Name Filter -->
            <div class="project-filter-container" style="flex: 1; min-width: 200px; max-width: 250px;">
                <div style=" color: #6b7280; font-size: 14px;">
                </div>
                <div class="project-multi-select">
                    <div class="project-dropdown-container" style="position: relative;">
                        <div class="project-dropdown-input" id="projectDropdownInput" style="
                            display: flex;
                            align-items: center;
                            border-radius: 6px;
                            padding: 8px 12px;
                            cursor: pointer;
                            font-size: 14px;
                            min-height: 42px;
                            width: 100%;
                            box-sizing: border-box;
                            background: white;
                        ">
                            <span class="project-placeholder" style="color: #6b7280; flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">Select project names...</span>
                            <i class="fas fa-chevron-down" style="margin-left: 8px; font-size: 12px; color: #6b7280; flex-shrink: 0;"></i>
                        </div>
                        
                        <div class="project-dropdown-options" id="projectDropdownOptions" style="
                            display: none;
                            
                            top: 100%;
                            left: 0;
                            right: 0;
                            border: 1px solid #d1d5db;
                            border-top: none;
                            border-radius: 0 0 6px 6px;
                            max-height: 200px;
                            overflow-y: auto;
                            z-index: 1000;
                            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
                        ">
                            <div class="project-search-container" style="padding: 8px; border-bottom: 1px solid #e5e7eb;">
                                <input type="text" class="project-search-input" placeholder="Search projects..." style="
                                    width: 100%;
                                    padding: 6px 8px;
                                    border: 1px solid #d1d5db;
                                    border-radius: 4px;
                                    font-size: 13px;
                                    outline: none;
                                    box-sizing: border-box;
                                ">
                            </div>
                            <div class="project-options-list" id="projectOptionsList">
                                <!-- Project options will be populated here -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Month/Year filter with custom dropdowns -->
            <div class="month-filter-select" id="popupMonthYearSelectors">
                <!-- Custom Month Dropdown -->
                <div class="custom-dropdown overall-report-date-dropdown" id="popupMonthDropdown">
                    <div class="custom-dropdown-selected" id="popupMonthSelected">
                        ${getMonthName(new Date().getMonth() + 1)}
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="custom-dropdown-options" id="popupMonthOptionsDiv">
                        ${generateMonthOptionsCustom()}
                    </div>
                </div>
                <input type="hidden" id="popupMonthSelect" value="${new Date().getMonth() + 1}">
                
                <!-- Custom Year Dropdown -->
                <div class="custom-dropdown overall-report-date-dropdown" id="popupYearDropdown">
                    <div class="custom-dropdown-selected" id="popupYearSelected">
                        ${new Date().getFullYear()}
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="custom-dropdown-options" id="popupYearOptionsDiv">
                        ${generateYearOptionsCustom()}
                    </div>
                </div>
                <input type="hidden" id="popupYearSelect" value="${new Date().getFullYear()}">
                
                <!-- Download Excel Button -->
                <button id="downloadExcelBtn" class="download-excel-btn" onclick="downloadDashboardExcel()" title="Download Excel Report">
                    <i class="fas fa-download"></i> Download
                </button>
            </div>

            <!-- Date Range Picker (Hidden Initially) -->
            <div class="month-filter-select" id="popupDateRangeFilter" style="display: none;">
                <input type="date" id="popupStartDate"> 
                <span style="margin: 0 5px; text-align: center;">to</span>
                <input type="date" id="popupEndDate">
                <button id="popupApplyRangeBtn" title="Apply date range" style="padding: 4px 8px; background: #16a34a; color: white; border: none; border-radius: 4px; cursor: pointer;">✓</button>
                <button id="popupCancelRangeBtn" style=" padding: 4px 8px; background: #ef4444; color: white; border: none; border-radius: 4px; cursor: pointer;">X</button>
            </div>
        </div>
    </div>
`;

    popupFilters.appendChild(userFilterSection);

    // Initialize the multi-select dropdown after ensuring user data is loaded
    setTimeout(async () => {
        // Ensure user data is loaded before initializing multi-select
        if (!allUsersData || allUsersData.length === 0) {
            await fetchAssignedUsers();
        }

        initializeMultiSelect();
        setupPopupFilterListeners();
        initializePopupCustomDropdowns(); // Initialize custom dropdowns

        // Initialize project filter
        initializeProjectFilter();

        // Set initial state based on screen size
        handleFilterSectionResize();
    }, 100);
}

function toggleFilterSection() {
    if (window.innerWidth > 768) return;

    const content = document.querySelector('.filter-section-content');
    const icon = document.querySelector('.filter-toggle-icon');
    const header = document.querySelector('.filter-section-header');

    if (!content || !icon) return;

    // Use a class to track state instead of checking styles
    const isExpanded = content.classList.contains('expanded');

    if (isExpanded) {
        // Collapse
        content.style.maxHeight = '0px';
        content.style.opacity = '0';
        content.style.padding = '0 16px';
        icon.style.transform = 'rotate(0deg)';
        header.style.borderRadius = '12px';
        content.classList.remove('expanded');

        setTimeout(() => {
            if (!content.classList.contains('expanded')) {
                content.style.display = 'none';
            }
        }, 400);
    } else {
        // Expand
        content.style.display = 'block';
        // Force reflow
        content.offsetHeight;
        content.style.maxHeight = '500px';
        content.style.opacity = '1';
        content.style.padding = '16px';
        icon.style.transform = 'rotate(180deg)';
        header.style.borderRadius = '12px 12px 0 0';
        content.classList.add('expanded');
    }
}
function clearFilterSelections() {
    // Clear selected users
    selectedUsers.clear();
    preservedSelectedUsers.clear();

    // Clear any remembered date range
    lastStartDate = null;
    lastEndDate = null;

    // Clear selected projects
    selectedProjectNames.clear();

    // Reset view mode
    currentViewMode = 'normal';
    currentHierarchyUser = null;

    // Reset currently selected user
    currentlySelectedUser = null;

    // Reset the name select dropdown to default
    const namesSelect = document.getElementById('namesSelect') || document.getElementById('name-select');
    if (namesSelect) {
        const originalHandler = namesSelect.onchange;
        namesSelect.onchange = null;  // Temporarily disable handler
        namesSelect.selectedIndex = 0;  // Reset to first option (usually "My Dashboard")
        setTimeout(() => namesSelect.onchange = originalHandler, 100);  // Re-enable handler
    }

    // Update dropdown checkboxes
    const checkboxes = document.querySelectorAll('.option-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = false;
        checkbox.closest('.dropdown-option')?.classList.remove('selected');
    });

    // Reset all project checkboxes
    const projectCheckboxes = document.querySelectorAll('[id^="project-"]');
    projectCheckboxes.forEach(checkbox => checkbox.checked = false);

    // Reset month/year filters to current month/year
    resetMonthYearFilters();

    // Update UI elements
    updateSelectedTags();
    updateDropdownPlaceholder();
    updateProjectPlaceholder();
    updateProjectSelectedTags();
    updateFilterModeIndicator();

    // Clear selected tags display
    const selectedTags = document.querySelector('.selected-tags');
    if (selectedTags) {
        selectedTags.innerHTML = '';
    }

    // Reset search input if exists
    const searchInput = document.querySelector('.search-input');
    if (searchInput) {
        searchInput.value = '';
    }

    // Hide floating clear filter button
    const floatingButton = document.querySelector('.floating-clear-filter');
    if (floatingButton) {
        floatingButton.remove();
    }

    // Hide filter summary panel
    const summaryPanel = document.querySelector('.filter-summary-panel');
    if (summaryPanel) {
        summaryPanel.remove();
    }

    // Remove team summary if exists
    const teamSummary = document.querySelector('.team-summary');
    if (teamSummary) {
        teamSummary.remove();
    }

    // Clear hierarchical summaries
    clearHierarchicalSummary();
    clearTeamSummary();

    // Reset grid display to default
    const usersGrid = document.getElementById('usersGrid');
    if (usersGrid) {
        usersGrid.style.display = '';
        usersGrid.style.gridTemplateColumns = '';
        usersGrid.style.gap = '';
    }

    // Force complete reset by showing all users in normal grid
    showAllUsersNormalGrid();

    // Reset charts to original data
    resetChartsToOriginalData();

    // Force update the visible user count to show all users
    if (allUsersData && allUsersData.length > 0) {
        updateVisibleUserCount(allUsersData.length, allUsersData.length);
    }

    // Remove any filter mode indicators or badges
    const filterBadges = document.querySelectorAll('.filter-badge, .team-mode-badge, .hierarchy-badge');
    filterBadges.forEach(badge => badge.remove());

    // Ensure dropdown is closed
    const dropdown = document.querySelector('.multi-select-dropdown');
    if (dropdown) {
        dropdown.classList.remove('open');
    }
}

// New function to reset month/year filters to current
function resetMonthYearFilters() {
    const now = new Date();
    const currentMonth = now.getMonth() + 1;
    const currentYear = now.getFullYear();

    // Reset popup month/year selectors
    const popupMonthSelect = document.getElementById('popupMonthSelect');
    const popupYearSelect = document.getElementById('popupYearSelect');
    const popupDateRangeFilter = document.getElementById('popupDateRangeFilter');
    const popupMonthYearSelectors = document.getElementById('popupMonthYearSelectors');

    if (popupMonthSelect) popupMonthSelect.value = currentMonth;
    if (popupYearSelect) popupYearSelect.value = currentYear;

    // Ensure we're in month/year mode (not custom date range)
    if (popupDateRangeFilter) popupDateRangeFilter.style.display = 'none';
    if (popupMonthYearSelectors) popupMonthYearSelectors.style.display = 'flex';

    // Also reset main dashboard filters
    const mainMonthSelect = document.getElementById('monthSelect');
    const mainYearSelect = document.getElementById('yearSelect');
    const mainDateRangeFilter = document.getElementById('dateRangeFilter');
    const mainMonthYearSelectors = document.getElementById('monthYearSelectors');

    if (mainMonthSelect) mainMonthSelect.value = currentMonth;
    if (mainYearSelect) mainYearSelect.value = currentYear;

    if (mainDateRangeFilter) mainDateRangeFilter.style.display = 'none';
    if (mainMonthYearSelectors) mainMonthYearSelectors.style.display = 'flex';

    // Update global variables
    currentSelectedMonth = currentMonth;
    currentSelectedYear = currentYear;

    // Reload data with current month/year
    loadPopupDashboardData(currentMonth, currentYear);
}


// Update the handleFilterSectionResize function
let lastWindowWidth = window.innerWidth;
function handleFilterSectionResize() {
    const content = document.querySelector('.filter-section-content');
    const icon = document.querySelector('.filter-toggle-icon');
    const header = document.querySelector('.filter-section-header');

    if (!content || !icon) return;

    // IMPORTANT: Only forcefully reset layout if the screen WIDTH changes (e.g. rotation or desktop resize).
    // If only height changed, that is just the mobile soft-keyboard opening! Do NOT auto-collapse.
    if (window.innerWidth === lastWindowWidth && document.readyState === 'complete') {
        return;
    }
    lastWindowWidth = window.innerWidth;

    if (window.innerWidth > 768) {
        // Desktop - always expanded
        content.style.display = 'block';
        content.style.maxHeight = 'none';
        content.style.opacity = '1';
        content.style.padding = '16px';
        icon.style.display = 'none';
        icon.style.transform = 'rotate(0deg)';
        header.style.borderRadius = '12px 12px 0 0';
        header.style.cursor = 'default';
        header.style.pointerEvents = 'none';
        content.classList.add('expanded');
    } else {
        // Mobile - keep state if already toggled! Do not force it closed if they have it open.
        if (!content.classList.contains('expanded')) {
            icon.style.display = 'block';
            content.style.display = 'none';
            content.style.maxHeight = '0px';
            content.style.opacity = '0';
            content.style.padding = '0 16px';
            icon.style.transform = 'rotate(0deg)';
            header.style.borderRadius = '12px';
            header.style.cursor = 'pointer';
            header.style.pointerEvents = 'auto';
        }
    }
}

function enableSmartScrolling() {
    const chartContainers = document.querySelectorAll('.chart-container');

    chartContainers.forEach(container => {
        const wrapper = container.querySelector('.chart-wrapper');
        const canvas = container.querySelector('canvas');

        if (wrapper && canvas) {
            // Check if content is larger than container
            const needsScrolling =
                wrapper.scrollWidth > container.clientWidth ||
                wrapper.scrollHeight > container.clientHeight;

            if (needsScrolling) {
                container.classList.add('scrollable');
            } else {
                container.classList.remove('scrollable');
            }
        }
    });
}

// Call this after charts are rendered
function initChartScrolling() {
    // Initial check
    setTimeout(enableSmartScrolling, 1000);

    // Check on window resize
    window.addEventListener('resize', enableSmartScrolling);

    // Check when charts update
    if (typeof Chart !== 'undefined' && Chart.defaults && Chart.defaults.plugins) {
        if (Chart.defaults.plugins.tooltip) {
            Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(0, 0, 0, 0.7)';
            if (Chart.defaults.plugins.tooltip.titleFont) {
                Chart.defaults.plugins.tooltip.titleFont.size = 12;
            }
            if (Chart.defaults.plugins.tooltip.bodyFont) {
                Chart.defaults.plugins.tooltip.bodyFont.size = 11;
            }
        }
        if (Chart.defaults.plugins.legend && Chart.defaults.plugins.legend.labels && Chart.defaults.plugins.legend.labels.font) {
            Chart.defaults.plugins.legend.labels.font.size = 11;
        }

        // Set chart text color based on theme for dark mode
        const isDarkMode = document.documentElement.getAttribute('data-theme') === 'dark';
        const textColor = isDarkMode ? '#ffffff' : '#666666';

        // Update legend label colors
        if (Chart.defaults.plugins.legend && Chart.defaults.plugins.legend.labels) {
            Chart.defaults.plugins.legend.labels.color = textColor;
        }

        // Update scale (axis) label colors
        if (Chart.defaults.scale) {
            Chart.defaults.scale.ticks = Chart.defaults.scale.ticks || {};
            Chart.defaults.scale.ticks.color = textColor;
        }

        // Update color for x and y axes
        if (Chart.defaults.scales) {
            if (Chart.defaults.scales.x) {
                Chart.defaults.scales.x.ticks = Chart.defaults.scales.x.ticks || {};
                Chart.defaults.scales.x.ticks.color = textColor;
            }
            if (Chart.defaults.scales.y) {
                Chart.defaults.scales.y.ticks = Chart.defaults.scales.y.ticks || {};
                Chart.defaults.scales.y.ticks.color = textColor;
            }
        }
    }
}


// Update chart colors when theme changes
function updateChartColors() {
    console.log('=== updateChartColors() called ===');

    if (typeof Chart === 'undefined') {
        console.warn('Chart.js not loaded yet');
        return;
    }

    const isDarkMode = isDarkTheme();
    const colorConfig = (typeof getChartColors === 'function') ? getChartColors() : {
        palette: [],
        textColor: isDarkMode ? '#ffffff' : '#666666',
        tooltipBg: isDarkMode ? '#111827' : '#ffffff',
        tooltipBorder: isDarkMode ? '#1f2937' : '#e5e7eb',
        gridColor: isDarkMode ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.06)',
        borderColor: isDarkMode ? '#0f172a' : '#ffffff'
    };
    const textColor = colorConfig.textColor;

    console.log('Dark mode:', isDarkMode, 'Text color:', textColor);

    // Update Chart.js global defaults for future charts
    if (Chart.defaults) {
        // Update legend defaults
        if (!Chart.defaults.plugins) Chart.defaults.plugins = {};
        if (!Chart.defaults.plugins.legend) Chart.defaults.plugins.legend = {};
        if (!Chart.defaults.plugins.legend.labels) Chart.defaults.plugins.legend.labels = {};
        Chart.defaults.plugins.legend.labels.color = textColor;
        Chart.defaults.plugins.legend.labels.fontColor = textColor; // Chart.js v2 compatibility

        if (!Chart.defaults.plugins.tooltip) Chart.defaults.plugins.tooltip = {};
        Chart.defaults.plugins.tooltip.backgroundColor = colorConfig.tooltipBg;
        Chart.defaults.plugins.tooltip.titleColor = textColor;
        Chart.defaults.plugins.tooltip.bodyColor = textColor;
        Chart.defaults.plugins.tooltip.borderColor = colorConfig.tooltipBorder;

        // Set base text color for all Chart.js elements
        Chart.defaults.color = textColor;
        if (!Chart.defaults.font) Chart.defaults.font = {};
        Chart.defaults.font.color = textColor;
        if (Chart.defaults.global) {
            Chart.defaults.global.defaultFontColor = textColor; // Chart.js v2 fallback
        }

        console.log('Updated Chart.defaults.plugins.legend.labels.color to:', textColor);
    }

    // Find all chart instances - multiple strategies
    let allCharts = [];

    // Strategy 1: Chart.instances (v3.x)
    if (Chart.instances && Array.isArray(Chart.instances)) {
        allCharts = allCharts.concat(Chart.instances);
        console.log('Found', Chart.instances.length, 'charts via Chart.instances');
    }

    // Strategy 2: Search window object for chart variables
    const chartVariableNames = ['statusChart', 'sourceChart', 'overallLeadStatusChart', 'leadSourceChart'];
    chartVariableNames.forEach(name => {
        if (window[name]) {
            allCharts.push(window[name]);
            console.log('Found chart via window.' + name);
        }
    });

    // Strategy 3: Use Chart.getChart() for all canvas elements
    document.querySelectorAll('canvas').forEach(canvas => {
        try {
            const chart = Chart.getChart(canvas);
            if (chart && !allCharts.includes(chart)) {
                allCharts.push(chart);
                console.log('Found chart via Chart.getChart() for canvas:', canvas.id || 'unnamed');
            }
        } catch (e) {
            // Ignore errors
        }
    });

    console.log('Total charts found:', allCharts.length);

    /* ✅ CRITICAL FIX — REMOVE DUPLICATES & STALE REFERENCES */
    allCharts = allCharts.filter((chart, index, self) => {

        if (!chart || !chart.canvas) return false;

        /* Remove destroyed charts */
        if (chart._destroyed) return false;

        /* Remove duplicates */
        return self.indexOf(chart) === index;
    });

    console.log('Charts after cleanup:', allCharts.length);

    // Update each chart
    allCharts.forEach((chart, index) => {
        if (!chart || !chart.options || !chart.canvas || !chart.canvas.isConnected) {
            console.warn('Chart', index, 'is invalid');
            return;
        }


        try {
            console.log('Updating chart', index);

            // Ensure options.plugins exists
            if (!chart.options.plugins) chart.options.plugins = {};
            if (!chart.options.plugins.legend) chart.options.plugins.legend = {};
            if (!chart.options.plugins.legend.labels) chart.options.plugins.legend.labels = {};

            // Update legend label color
            chart.options.plugins.legend.labels.color = textColor;
            chart.options.plugins.legend.labels.fontColor = textColor; // Chart.js v2 compatibility

            // Update tooltip styling for better contrast
            if (!chart.options.plugins.tooltip) chart.options.plugins.tooltip = {};
            chart.options.plugins.tooltip.backgroundColor = colorConfig.tooltipBg;
            chart.options.plugins.tooltip.titleColor = textColor;
            chart.options.plugins.tooltip.bodyColor = textColor;
            chart.options.plugins.tooltip.borderColor = colorConfig.tooltipBorder;

            // Also update the legend plugin directly if it exists
            if (chart.legend && chart.legend.options && chart.legend.options.labels) {
                chart.legend.options.labels.color = textColor;
                chart.legend.options.labels.fontColor = textColor; // Chart.js v2 compatibility
            }

            // Update scale colors if they exist
            if (chart.options.scales) {
                Object.keys(chart.options.scales).forEach(scaleKey => {
                    if (!chart.options.scales[scaleKey].ticks) {
                        chart.options.scales[scaleKey].ticks = {};
                    }
                    chart.options.scales[scaleKey].ticks.color = textColor;
                    if (!chart.options.scales[scaleKey].grid) {
                        chart.options.scales[scaleKey].grid = {};
                    }
                    chart.options.scales[scaleKey].grid.color = colorConfig.gridColor;
                });
            }

            // Refresh palette-driven charts when theme changes
            if (chart.canvas && chart.canvas.id === 'leadSourceChart' && chart.data && chart.data.datasets && chart.data.datasets[0]) {
                chart.data.datasets[0].backgroundColor = getPaletteForLength(colorConfig.palette, chart.data.datasets[0].data.length);
                chart.data.datasets[0].borderColor = colorConfig.borderColor;
            }

            // Force update the chart with immediate mode
            chart.update('none');
            console.log('Chart', index, 'updated successfully');
        } catch (error) {
            console.error('Error updating chart', index, ':', error);
        }
    });

    console.log('=== updateChartColors() complete ===');
}

// Make it globally available so theme toggle can call it
window.updateChartColors = updateChartColors;

// Apply initial chart theme once Chart.js has loaded and DOM is ready
if (document.readyState === 'complete' || document.readyState === 'interactive') {
    setTimeout(() => {
        if (typeof updateChartColors === 'function') {
            updateChartColors();
        }
    }, 150);
} else {
    document.addEventListener('DOMContentLoaded', () => {
        setTimeout(() => {
            if (typeof updateChartColors === 'function') {
                updateChartColors();
            }
        }, 150);
    });
}


// function handleFilterSectionResize() {
//     const content = document.querySelector('.filter-section-content');
//     const icon = document.querySelector('.filter-toggle-icon');
//     const header = document.querySelector('.filter-section-header');

//     if (!content || !icon) return;

//     if (window.innerWidth > 768) {
//         // Desktop - always expanded and not collapsible
//         content.style.maxHeight = '500px';
//         content.style.opacity = '1';
//         content.style.padding = '16px';
//         icon.style.transform = 'rotate(0deg)';
//         header.style.borderRadius = '12px 12px 0 0';
//         header.style.cursor = 'default';
//         header.style.pointerEvents = 'none'; // Disable clicking on desktop
//     } else {
//         // Mobile - initially collapsed and collapsible
//         content.style.maxHeight = '0px';
//         content.style.opacity = '0';
//         content.style.padding = '0 16px';
//         icon.style.transform = 'rotate(0deg)';
//         header.style.borderRadius = '12px';
//         header.style.cursor = 'pointer';
//         header.style.pointerEvents = 'auto'; // Enable clicking on mobile
//     }
// }

// Add resize listener to handle screen size changes
window.addEventListener('resize', handleFilterSectionResize);

// Make function globally available
window.toggleFilterSection = toggleFilterSection;
window.handleFilterSectionResize = handleFilterSectionResize;

// Add resize listener to handle screen size changes
/* Duplicate mobile collapse resize listener removed */


// Make function globally available
window.toggleFilterSection = toggleFilterSection;

// Helper functions to generate month and year options
function generateMonthOptions() {
    const months = [
        'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December'
    ];
    const currentMonth = new Date().getMonth() + 1;

    return months.map((month, index) =>
        `<option value="${index + 1}" ${index + 1 === currentMonth ? 'selected' : ''}>${month}</option>`
    ).join('') + '<option value="custom">Custom Range</option>';
}

function generateYearOptions() {
    const currentYear = new Date().getFullYear();
    let options = '';

    for (let y = currentYear; y >= currentYear - 3; y--) {
        const selected = y === currentYear ? 'selected' : '';
        options += `<option value="${y}" ${selected}>${y}</option>`;
    }

    return options;
}

// Helper functions for custom dropdowns
function generateMonthOptionsCustom() {
    const months = [
        'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December'
    ];
    const currentMonth = new Date().getMonth() + 1;

    return months.map((month, index) => {
        const value = index + 1;
        const isSelected = value === currentMonth ? 'selected' : '';
        return `<div class="custom-dropdown-option ${isSelected}" data-value="${value}">${month}</div>`;
    }).join('') + '<div class="custom-dropdown-option" data-value="custom">Custom Range</div>';
}

function generateYearOptionsCustom() {
    const currentYear = new Date().getFullYear();
    let options = '';

    for (let y = currentYear; y >= currentYear - 3; y--) {
        const isSelected = y === currentYear ? 'selected' : '';
        options += `<div class="custom-dropdown-option ${isSelected}" data-value="${y}">${y}</div>`;
    }

    return options;
}

function getMonthName(monthNum) {
    const months = [
        'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December'
    ];
    return months[monthNum - 1] || 'January';
}

// Initialize popup custom dropdowns
function initializePopupCustomDropdowns() {
    const popupMonthDropdown = document.getElementById('popupMonthDropdown');
    const popupMonthSelected = document.getElementById('popupMonthSelected');
    const popupMonthOptions = document.getElementById('popupMonthOptionsDiv');
    const popupMonthHiddenInput = document.getElementById('popupMonthSelect');

    const popupYearDropdown = document.getElementById('popupYearDropdown');
    const popupYearSelected = document.getElementById('popupYearSelected');
    const popupYearOptions = document.getElementById('popupYearOptionsDiv');
    const popupYearHiddenInput = document.getElementById('popupYearSelect');

    if (!popupMonthDropdown || !popupYearDropdown) return;

    // Portal function
    function ensurePopupDropdownPortaled(optionsElement) {
        if (!optionsElement || optionsElement.getAttribute('data-ported') === 'true') return optionsElement;

        let portal = document.getElementById('ui-portal');
        if (!portal) {
            portal = document.createElement('div');
            portal.id = 'ui-portal';
            document.body.appendChild(portal);
        }

        portal.appendChild(optionsElement);
        optionsElement.setAttribute('data-ported', 'true');
        return optionsElement;
    }

    // Position dropdown
    function positionPopupDropdown(buttonElement, optionsElement) {
        if (!buttonElement || !optionsElement) return;

        const rect = buttonElement.getBoundingClientRect();
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        const scrollLeft = window.pageXOffset || document.documentElement.scrollLeft;

        optionsElement.style.position = 'absolute';
        optionsElement.style.top = `${rect.bottom + scrollTop}px`;
        optionsElement.style.left = `${rect.left + scrollLeft}px`;
        optionsElement.style.width = `${rect.width}px`;
        optionsElement.style.zIndex = '2147483647';
    }

    // Portal the options
    ensurePopupDropdownPortaled(popupMonthOptions);
    ensurePopupDropdownPortaled(popupYearOptions);

    // Month dropdown toggle
    popupMonthSelected.addEventListener('click', function (e) {
        e.stopPropagation();
        const wasOpen = popupMonthDropdown.classList.contains('open');

        // Close year dropdown
        if (popupYearDropdown) {
            popupYearDropdown.classList.remove('open');
            if (popupYearOptions) popupYearOptions.style.display = 'none';
        }

        if (wasOpen) {
            popupMonthDropdown.classList.remove('open');
            popupMonthOptions.style.display = 'none';
        } else {
            popupMonthDropdown.classList.add('open');
            popupMonthOptions.style.display = 'block';
            positionPopupDropdown(popupMonthSelected, popupMonthOptions);
        }
    });

    // Year dropdown toggle
    popupYearSelected.addEventListener('click', function (e) {
        e.stopPropagation();
        const wasOpen = popupYearDropdown.classList.contains('open');

        // Close month dropdown
        if (popupMonthDropdown) {
            popupMonthDropdown.classList.remove('open');
            if (popupMonthOptions) popupMonthOptions.style.display = 'none';
        }

        if (wasOpen) {
            popupYearDropdown.classList.remove('open');
            popupYearOptions.style.display = 'none';
        } else {
            popupYearDropdown.classList.add('open');
            popupYearOptions.style.display = 'block';
            positionPopupDropdown(popupYearSelected, popupYearOptions);
        }
    });

    // Month option click
    popupMonthOptions.querySelectorAll('.custom-dropdown-option').forEach(option => {
        option.addEventListener('click', function (e) {
            e.stopPropagation();
            const value = this.getAttribute('data-value');
            const text = this.textContent.trim();

            popupMonthSelected.innerHTML = text + ' <i class="fas fa-chevron-down"></i>';
            popupMonthHiddenInput.value = value;

            popupMonthOptions.querySelectorAll('.custom-dropdown-option').forEach(opt => {
                opt.classList.remove('selected');
            });
            this.classList.add('selected');

            popupMonthDropdown.classList.remove('open');
            popupMonthOptions.style.display = 'none';

            // Trigger the existing change event handler
            popupMonthHiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
        });
    });

    // Year option click
    popupYearOptions.querySelectorAll('.custom-dropdown-option').forEach(option => {
        option.addEventListener('click', function (e) {
            e.stopPropagation();
            const value = this.getAttribute('data-value');
            const text = this.textContent.trim();

            popupYearSelected.innerHTML = text + ' <i class="fas fa-chevron-down"></i>';
            popupYearHiddenInput.value = value;

            popupYearOptions.querySelectorAll('.custom-dropdown-option').forEach(opt => {
                opt.classList.remove('selected');
            });
            this.classList.add('selected');

            popupYearDropdown.classList.remove('open');
            popupYearOptions.style.display = 'none';

            // Trigger the existing change event handler
            popupYearHiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
        });
    });

    // Close dropdowns when clicking outside
    document.addEventListener('click', function (e) {
        if (popupMonthDropdown && popupMonthOptions &&
            !popupMonthDropdown.contains(e.target) && !popupMonthOptions.contains(e.target)) {
            popupMonthDropdown.classList.remove('open');
            popupMonthOptions.style.display = 'none';
        }
        if (popupYearDropdown && popupYearOptions &&
            !popupYearDropdown.contains(e.target) && !popupYearOptions.contains(e.target)) {
            popupYearDropdown.classList.remove('open');
            popupYearOptions.style.display = 'none';
        }
    });

    // Reposition on scroll and resize
    const repositionPopupDropdowns = () => {
        if (popupMonthDropdown?.classList.contains('open')) {
            positionPopupDropdown(popupMonthSelected, popupMonthOptions);
        }
        if (popupYearDropdown?.classList.contains('open')) {
            positionPopupDropdown(popupYearSelected, popupYearOptions);
        }
    };

    window.addEventListener('scroll', repositionPopupDropdowns, true);
    window.addEventListener('resize', repositionPopupDropdowns);
}

function setupDateRangeControls() {
    const cancelRangeBtn = document.getElementById("cancelRangeBtn");
    if (cancelRangeBtn) {
        cancelRangeBtn.addEventListener("click", handleCustomRangeCancel);
    }

    const applyRangeBtn = document.getElementById("applyRangeBtn");
    if (applyRangeBtn) {
        applyRangeBtn.removeEventListener("click", handleApplyRangeButtonClick);
        applyRangeBtn.addEventListener("click", handleApplyRangeButtonClick);
    }
}

function handleApplyRangeButtonClick() {
    handleCustomRangeApply(true);
}


// Fixed functions for popup date range functionality

function setupPopupFilterListeners() {
    // Month/Year selectors
    const popupMonthSelect = document.getElementById('popupMonthSelect');
    const popupYearSelect = document.getElementById('popupYearSelect');
    const popupDateRangeFilter = document.getElementById('popupDateRangeFilter');
    const popupMonthYearSelectors = document.getElementById('popupMonthYearSelectors');
    const popupCancelRangeBtn = document.getElementById('popupCancelRangeBtn');
    const popupApplyRangeBtn = document.getElementById('popupApplyRangeBtn');
    const popupStartDate = document.getElementById('popupStartDate');
    const popupEndDate = document.getElementById('popupEndDate');

    // Remove existing listeners to prevent duplicates
    if (popupMonthSelect) {
        popupMonthSelect.removeEventListener('change', handlePopupMonthYearChange);
        popupMonthSelect.addEventListener('change', handlePopupMonthYearChange);
    }

    if (popupYearSelect) {
        popupYearSelect.removeEventListener('change', handlePopupMonthYearChange);
        popupYearSelect.addEventListener('change', handlePopupMonthYearChange);
    }

    if (popupCancelRangeBtn) {
        popupCancelRangeBtn.removeEventListener('click', handlePopupCustomRangeCancel);
        popupCancelRangeBtn.addEventListener('click', handlePopupCustomRangeCancel);
    }

    if (popupApplyRangeBtn) {
        popupApplyRangeBtn.removeEventListener('click', handlePopupCustomRangeApplyClick);
        popupApplyRangeBtn.addEventListener('click', handlePopupCustomRangeApplyClick);
    }

    // Date range inputs with debounced auto-apply
    if (popupStartDate && popupEndDate) {
        popupStartDate.removeEventListener('change', handlePopupDateRangeChange);
        popupEndDate.removeEventListener('change', handlePopupDateRangeChange);

        popupStartDate.addEventListener('change', handlePopupDateRangeChange);
        popupEndDate.addEventListener('change', handlePopupDateRangeChange);
    }
}

function handlePopupMonthYearChange() {
    const popupMonthSelect = document.getElementById('popupMonthSelect');
    const popupYearSelect = document.getElementById('popupYearSelect');
    const popupDateRangeFilter = document.getElementById('popupDateRangeFilter');
    const popupMonthYearSelectors = document.getElementById('popupMonthYearSelectors');

    if (popupMonthSelect.value === 'custom') {
        // Show date range picker
        popupMonthYearSelectors.style.display = 'none';
        popupDateRangeFilter.style.display = 'flex';

        // Do not auto-fill or auto-apply for custom range.
        // Keep prior values if present, otherwise leave blank until user chooses dates and clicks tick.
        const popupStartDate = document.getElementById('popupStartDate');
        const popupEndDate = document.getElementById('popupEndDate');
        if (popupStartDate && !popupStartDate.value && lastStartDate) popupStartDate.value = lastStartDate;
        if (popupEndDate && !popupEndDate.value && lastEndDate) popupEndDate.value = lastEndDate;
    } else {
        // Apply all filters with new month/year
        applyAllFilters();
    }
}

function handlePopupCustomRangeApplyClick(event) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }

    const popupStartDate = document.getElementById('popupStartDate');
    const popupEndDate = document.getElementById('popupEndDate');
    const startDate = popupStartDate?.value;
    const endDate = popupEndDate?.value;

    if (!startDate || !endDate) {
        showNotification('Please select both start and end dates', 'warning');
        return;
    }

    const validation = validateDateRange(startDate, endDate);
    if (!validation.isValid) {
        showNotification(validation.message, 'error');
        return;
    }

    // Persist selected range and apply all active filters only on explicit tick click.
    lastStartDate = startDate;
    lastEndDate = endDate;
    applyAllFilters();
}



function handlePopupCustomRangeCancel() {
    const popupDateRangeFilter = document.getElementById('popupDateRangeFilter');
    const popupMonthYearSelectors = document.getElementById('popupMonthYearSelectors');
    const popupMonthSelect = document.getElementById('popupMonthSelect');
    const popupYearSelect = document.getElementById('popupYearSelect');

    // Hide date range picker and show month/year selectors
    popupDateRangeFilter.style.display = 'none';
    popupMonthYearSelectors.style.display = 'flex';

    // Reset to current month/year
    const now = new Date();
    popupMonthSelect.value = now.getMonth() + 1;
    popupYearSelect.value = now.getFullYear();

    // Update global variables
    currentSelectedMonth = now.getMonth() + 1;
    currentSelectedYear = now.getFullYear();

    // Load data for current month/year
    loadPopupDashboardData(now.getMonth() + 1, now.getFullYear());
}


let popupDateRangeTimeout = null;

function handlePopupDateRangeChange() {
    const popupStartDate = document.getElementById('popupStartDate');
    const popupEndDate = document.getElementById('popupEndDate');

    if (!popupStartDate.value || !popupEndDate.value) {
        return;
    }

    // Validate date range for popup
    const validation = validateDateRange(popupStartDate.value, popupEndDate.value);
    if (!validation.isValid) {
        showNotification(validation.message, "error");

        // Clear previous timeout to prevent API call
        if (popupDateRangeTimeout) {
            clearTimeout(popupDateRangeTimeout);
            popupDateRangeTimeout = null;
        }
        return;
    }

    // Do not auto-apply on date changes.
    // Filtering runs only when user clicks the green tick button.
}




function loadPopupDashboardData(month, year, preserveSelections = false) {
    // Clear stale aggregated data to avoid flashing old content
    aggregatedAnalyticsData = null;
    currentFilteredAnalyticsData = null;
    currentFilteredUserTablenames = null;

    // Show loading state
    showDashboardPopupLoader('Loading team dashboard...');
    showPopupLoadingState();

    // Preserve current selections before loading new data
    if (preserveSelections && selectedUsers.size > 0) {
        preservedSelectedUsers = new Set(selectedUsers);
    }

    // Use centralized filter helpers for consistent param building
    const filters = {
        aggregated_analytics: true,
        include_detailed_data: true,
        month: month,
        year: year,
        date_column: getActiveDateColumn()
    };

    // Prefer explicit current selection, then fallback to dropdown
    const selectedMainUser = currentlySelectedUser || getSelectedUserValue();
    if (selectedMainUser) {
        filters.user_id = selectedMainUser;
    }

    // Add project filter if selected
    const selectedProjects = getSelectedProjectNames();
    if (selectedProjects && selectedProjects.length > 0) {
        filters.project_filter = selectedProjects.join(',');
    }

    const url = buildDashboardUrl('dashboard_data.php', filters);

    // Fast-path: return cached payload when filters are identical within TTL
    const cacheKey = buildPopupCacheKey(filters);
    const cached = getCachedPopupData(cacheKey);
    if (cached) {
        aggregatedAnalyticsData = cached;
        currentFilteredAnalyticsData = null;
        currentFilteredUserTablenames = null;
        processAggregatedData(cached);
        storeAllUsersData({ aggregated_analytics: cached });
        Promise.resolve(updatePopupDashboard(cached))
            .then(() => refreshDropdownBadges())
            .finally(() => hideDashboardPopupLoader());
        return;
    }

    cancelActiveDashboardRequest();
    const controller = new AbortController();
    activeDashboardFetchController = controller;
    const requestStartedAt = performance.now ? performance.now() : Date.now();

    fetchData(url, { controller, timeoutMs: 60000 }).then(data => {
        if (activeDashboardFetchController !== controller) return;
        if (data.status === "aborted") {
            hideDashboardPopupLoader();
            return;
        }

        if (data && data.status === 'success' && data.aggregated_analytics) {
            aggregatedAnalyticsData = data.aggregated_analytics;
            // Excel export should use this fresh data
            currentFilteredAnalyticsData = null;
            currentFilteredUserTablenames = null;
            setCachedPopupData(cacheKey, data.aggregated_analytics);
            processAggregatedData(data.aggregated_analytics);

            // CRITICAL: Store ALL user data with detailed performance metrics
            // Preserve original user types from allUsersData and persistent storage to prevent badge overwriting
            const existingUserTypes = {};
            let hasExistingData = false;

            // First, check persistent storage for user types
            if (Object.keys(persistentUserTypes).length > 0) {
                Object.assign(existingUserTypes, persistentUserTypes);
                hasExistingData = true;
            }

            // Then overlay any current allUsersData types (may be more up to date)
            if (allUsersData && allUsersData.length > 0) {
                allUsersData.forEach(user => {
                    if (user.user_type && user.user_type !== 'user') {
                        existingUserTypes[user.tablename] = user.user_type;
                        hasExistingData = true;
                    }
                });
            }

            if (data.aggregated_analytics.user_wise_data) {

                allUsersData = data.aggregated_analytics.user_wise_data.map(user => {
                    const preservedType = existingUserTypes[user.tablename];

                    // Prefer API data for user/team_lead roles, use preserved data only if API is empty
                    let finalType;
                    if (user.user_type && user.user_type !== 'undefined' && user.user_type !== 'null') {
                        // Trust API data if it exists and is valid
                        finalType = user.user_type;
                    } else {
                        // Use preserved data only as fallback
                        finalType = preservedType || 'user';
                    }

                    // Store final type persistently for future sessions
                    if (user.tablename && finalType) {
                        persistentUserTypes[user.tablename] = finalType;
                    }

                    return {
                        tablename: user.tablename,
                        name: user.name || user.username,
                        email: user.email || user.useremail,
                        // IMPORTANT: Use preserved user_type or fallback to current data
                        user_type: finalType,
                        // Performance metrics - use the same data as team view
                        leads: user.leads || 0,
                        bookings: user.bookings || 0,
                        eoi: user.eoi || 0,
                        cancelled_bookings: user.cancelled_bookings || 0,
                        conversion_rate: user.conversion_rate || 0,
                        // FIXED: Include lead status array for charts (from PHP user_wise_data)
                        leadStatus: Array.isArray(user.leadStatus) ? user.leadStatus : [],
                        // FIXED: Include per-user detailed status counts for charts
                        detailed_status_counts: Array.isArray(user.leadStatus) ? user.leadStatus : [],
                        // FIXED: Preserve QR / FSV / SVD from server so initial render is correct
                        quality_range: user.quality_range || 0,
                        fsv_count: user.fsv_count || 0,
                        svd_count: user.svd_count || 0,
                        // Include detailed analytics if available
                        analytics: user.analytics || {}
                    };
                });
            }

            storeAllUsersData(data); // Store all user data

            // Refresh dropdown after popup data loading
            if (typeof populateDropdownOptions === 'function') {
                setTimeout(() => {
                    populateDropdownOptions();
                }, 150);
            }

            const waitForNextFrame = () => new Promise(res => requestAnimationFrame(() => requestAnimationFrame(res)));
            const waitForDashboardStable = async () => {
                const snapshot = () => {
                    const numbers = Array.from(document.querySelectorAll('.stats-overview .number')).map(n => (n.textContent || '').trim());
                    const numbersReady = numbers.length >= 4 && numbers.every(v => v !== '' && !/loading/i.test(v));
                    const usersGrid = document.querySelector('#usersGrid');
                    const userCount = usersGrid ? usersGrid.children.length : 0;
                    const chartsReady = !!document.querySelector('#overallLeadStatusChart') && !!document.querySelector('#leadSourceChart');
                    return {
                        key: `${numbers.join('|')}::${userCount}::${chartsReady}`,
                        // For perceived performance we only require that some cards exist,
                        // not that every possible user card has been rendered.
                        ready: numbersReady && chartsReady && (userCount > 0)
                    };
                };

                let lastKey = '';
                let stableCount = 0;
                for (let i = 0; i < 10; i++) {
                    const { key, ready } = snapshot();
                    if (ready && key === lastKey) {
                        stableCount += 1;
                    } else {
                        stableCount = ready ? 1 : 0;
                    }
                    lastKey = key;
                    if (ready && stableCount >= 2) return;
                    await new Promise(res => setTimeout(res, 120));
                }
            };

            const runPostRenderTasks = async () => {
                if (preserveSelections && preservedSelectedUsers.size > 0) {
                    await new Promise(res => setTimeout(res, 350));
                    restoreSelectedUsers();
                    refreshDropdownBadges();
                } else {
                    await new Promise(res => setTimeout(res, 250));
                    refreshDropdownBadges();
                }
                await waitForNextFrame();
                await waitForNextFrame();
                await waitForDashboardStable();
            };

            Promise.resolve(updatePopupDashboard(data.aggregated_analytics))
                .then(() => runPostRenderTasks())
                .finally(() => hideDashboardPopupLoader());
        } else {
            console.error('Failed to fetch popup dashboard data:', data);
            showPopupErrorState('Failed to load dashboard data');
            hideDashboardPopupLoader();
        }
    }).catch(error => {
        console.error('Error fetching popup dashboard data:', error);
        showPopupErrorState('Network error loading dashboard data');
        hideDashboardPopupLoader();
    }).finally(() => {
        if (activeDashboardFetchController === controller) {
            activeDashboardFetchController = null;
        }
        const end = performance.now ? performance.now() : Date.now();
        console.info('loadPopupDashboardData duration (ms):', Math.round(end - requestStartedAt));
    });
}



function loadPopupDashboardDataByDateRange(startDate, endDate, preserveSelections = false) {
    // Clear stale aggregated data to avoid flashing old content
    aggregatedAnalyticsData = null;
    currentFilteredAnalyticsData = null;
    currentFilteredUserTablenames = null;

    // Show loading state
    showDashboardPopupLoader('Loading dashboard for date range...');
    showPopupLoadingState();

    // Preserve current selections before loading new data
    if (preserveSelections && selectedUsers.size > 0) {
        preservedSelectedUsers = new Set(selectedUsers);
    }

    // Use centralized filter helpers for consistent param building
    const filters = {
        aggregated_analytics: true,
        include_detailed_data: true,
        start_date: startDate,
        end_date: endDate,
        date_column: getActiveDateColumn()
    };

    // Prefer explicit current selection, then fallback to dropdown
    const selectedMainUser = currentlySelectedUser || getSelectedUserValue();
    if (selectedMainUser) {
        filters.user_id = selectedMainUser;
    }

    // Add project filter if selected
    const selectedProjects = getSelectedProjectNames();
    if (selectedProjects && selectedProjects.length > 0) {
        filters.project_filter = selectedProjects.join(',');
    }

    const url = buildDashboardUrl('dashboard_data.php', filters);

    // Fast-path: return cached payload when filters are identical within TTL
    const cacheKey = buildPopupCacheKey(filters);
    const cached = getCachedPopupData(cacheKey);
    if (cached) {
        aggregatedAnalyticsData = cached;
        currentFilteredAnalyticsData = null;
        currentFilteredUserTablenames = null;
        processAggregatedData(cached);
        storeAllUsersData({ aggregated_analytics: cached });
        Promise.resolve(updatePopupDashboard(cached))
            .then(() => refreshDropdownBadges())
            .finally(() => hideDashboardPopupLoader());
        return;
    }

    cancelActiveDashboardRequest();
    const controller = new AbortController();
    activeDashboardFetchController = controller;
    const requestStartedAt = performance.now ? performance.now() : Date.now();

    fetchData(url, { controller, timeoutMs: 60000 }).then(data => {
        if (activeDashboardFetchController !== controller) return;
        if (data.status === "aborted") {
            hideDashboardPopupLoader();
            return;
        }
        if (data && data.status === 'success' && data.aggregated_analytics) {
            aggregatedAnalyticsData = data.aggregated_analytics;
            // Excel export should use this fresh data
            currentFilteredAnalyticsData = null;
            currentFilteredUserTablenames = null;
            setCachedPopupData(cacheKey, data.aggregated_analytics);
            processAggregatedData(data.aggregated_analytics);
            storeAllUsersData(data); // Store all user data

            const waitForNextFrame = () => new Promise(res => requestAnimationFrame(() => requestAnimationFrame(res)));
            const waitForDashboardStable = async () => {
                const snapshot = () => {
                    const numbers = Array.from(document.querySelectorAll('.stats-overview .number')).map(n => (n.textContent || '').trim());
                    const numbersReady = numbers.length >= 4 && numbers.every(v => v !== '' && !/loading/i.test(v));
                    const usersGrid = document.querySelector('#usersGrid');
                    const userCount = usersGrid ? usersGrid.children.length : 0;
                    const chartsReady = !!document.querySelector('#overallLeadStatusChart') && !!document.querySelector('#leadSourceChart');
                    return {
                        key: `${numbers.join('|')}::${userCount}::${chartsReady}`,
                        ready: numbersReady && chartsReady && (userCount > 0)
                    };
                };

                let lastKey = '';
                let stableCount = 0;
                for (let i = 0; i < 10; i++) {
                    const { key, ready } = snapshot();
                    if (ready && key === lastKey) {
                        stableCount += 1;
                    } else {
                        stableCount = ready ? 1 : 0;
                    }
                    lastKey = key;
                    if (ready && stableCount >= 2) return;
                    await new Promise(res => setTimeout(res, 120));
                }
            };

            const runPostRenderTasks = async () => {
                if (preserveSelections && preservedSelectedUsers.size > 0) {
                    await new Promise(res => setTimeout(res, 350));
                    restoreSelectedUsers();
                    refreshDropdownBadges();
                } else {
                    await new Promise(res => setTimeout(res, 250));
                    refreshDropdownBadges();
                }
                await waitForNextFrame();
                await waitForNextFrame();
                await waitForDashboardStable();
            };

            Promise.resolve(updatePopupDashboard(data.aggregated_analytics))
                .then(() => runPostRenderTasks())
                .finally(() => hideDashboardPopupLoader());
        } else {
            showPopupErrorState('Failed to load dashboard data for selected date range');
            console.error('Failed to fetch popup dashboard data for date range');
            hideDashboardPopupLoader();
        }
    }).catch(error => {
        showPopupErrorState('Network error loading dashboard data');
        console.error('Error fetching popup dashboard data for date range:', error);
        hideDashboardPopupLoader();
    }).finally(() => {
        if (activeDashboardFetchController === controller) {
            activeDashboardFetchController = null;
        }
        const end = performance.now ? performance.now() : Date.now();
        console.info('loadPopupDashboardDataByDateRange duration (ms):', Math.round(end - requestStartedAt));
    });
}

function restoreSelectedUsers() {
    if (preservedSelectedUsers.size === 0) {
        return;
    }

    // Clear current selections
    selectedUsers.clear();

    // Restore preserved selections
    preservedSelectedUsers.forEach(userId => {
        selectedUsers.add(userId);
    });

    // Update the dropdown UI to reflect restored selections
    updateDropdownSelections();

    // Update the filtered view
    filterUserCards();
}

// NEW FUNCTION: Update dropdown checkboxes to match selected users
function updateDropdownSelections() {
    const options = document.querySelectorAll('.dropdown-option');
    let restoredCount = 0;

    options.forEach(option => {
        const checkbox = option.querySelector('.option-checkbox');
        if (checkbox) {
            const value = checkbox.value;
            const isSelected = selectedUsers.has(value);

            checkbox.checked = isSelected;
            if (isSelected) {
                option.classList.add('selected');
                restoredCount++;
            } else {
                option.classList.remove('selected');
            }
        }
    });

    // Update UI elements
    updateSelectedTags();
    updateDropdownPlaceholder();
    updateFilterModeIndicator();
}



function showPopupLoadingState() {
    // If overlay loader is already visible, skip injecting secondary loader markup to avoid double loaders
    const overlayVisible = document.querySelector('#dashboardPopup .dashboard-popup-loader')?.style.display === 'flex';
    if (overlayVisible) return;

    const dashboardContent = document.querySelector('#dashboardPopup .dashboard-content2');
    if (dashboardContent) {
        dashboardContent.innerHTML = `
            <div style="text-align: center; padding: 3rem;">
                <div style="font-size: 1.5rem; margin-bottom: 1rem;">Loading Dashboard...</div>
                <div style="color: #6b7280;">Please wait while we fetch the data</div>
                <div class="loading-spinner" style="margin: 20px auto; width: 40px; height: 40px; border: 3px solid #f3f3f3; border-top: 3px solid #3498db; border-radius: 50%; animation: spin 1s linear infinite;"></div>
            </div>
            <style>
                @keyframes spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
            </style>
        `;
    }
}

// Lightweight overlay loader dedicated to the dashboard popup so the UI does not reveal half-rendered charts/cards.
function ensureDashboardPopupLoader(message = 'Loading dashboard...') {
    const popup = document.getElementById('dashboardPopup');
    if (!popup) return null;

    let loader = popup.querySelector('.dashboard-popup-loader');
    if (!loader) {
        loader = document.createElement('div');
        loader.className = 'dashboard-popup-loader';
        loader.innerHTML = `
            <div class="dashboard-popup-loader__backdrop"></div>
            <div class="dashboard-popup-loader__content">
                <div class="dashboard-popup-loader__spinner"></div>
                <div class="dashboard-popup-loader__text"></div>
            </div>
        `;
        popup.appendChild(loader);

        // Inline styles to avoid stylesheet hops
        Object.assign(loader.style, {
            position: 'fixed',
            inset: '0',
            display: 'none',
            alignItems: 'center',
            justifyContent: 'center',
            zIndex: '99999'
        });

        const backdrop = loader.querySelector('.dashboard-popup-loader__backdrop');
        Object.assign(backdrop.style, {
            position: 'absolute',
            inset: '0',
            backgroundColor: 'rgba(255,255,255,0.75)'
        });

        const content = loader.querySelector('.dashboard-popup-loader__content');
        Object.assign(content.style, {
            position: 'relative',
            display: 'flex',
            flexDirection: 'column',
            alignItems: 'center',
            gap: '12px',
            padding: '18px 20px',
            background: '#ffffff',
            borderRadius: '12px',
            boxShadow: '0 10px 30px rgba(0,0,0,0.12)'
        });

        const spinner = loader.querySelector('.dashboard-popup-loader__spinner');
        Object.assign(spinner.style, {
            width: '42px',
            height: '42px',
            borderRadius: '50%',
            border: '4px solid rgba(0,0,0,0.08)',
            borderTopColor: '#3498db',
            animation: 'spin 0.9s linear infinite'
        });

        const text = loader.querySelector('.dashboard-popup-loader__text');
        Object.assign(text.style, {
            fontSize: '14px',
            color: '#374151',
            fontWeight: '600'
        });
    }

    const textNode = loader.querySelector('.dashboard-popup-loader__text');
    if (textNode) textNode.textContent = message;
    return loader;
}

function showDashboardPopupLoader(message = 'Loading dashboard...') {
    const loader = ensureDashboardPopupLoader(message);
    if (loader) loader.style.display = 'flex';
    // IMPORTANT: Do not auto-hide the loader.
    // It must only be removed when charts + user cards finish rendering,
    // otherwise large user counts will show a half-rendered popup.
}

function hideDashboardPopupLoader(force = false) {
    const popup = document.getElementById('dashboardPopup');
    if (!popup) return;
    const loader = popup.querySelector('.dashboard-popup-loader');
    if (loader) loader.style.display = 'none';
    if (window.dashboardLoaderFailsafe) {
        clearTimeout(window.dashboardLoaderFailsafe);
        window.dashboardLoaderFailsafe = null;
    }
}

function showPopupErrorState(message) {
    const dashboardContent = document.querySelector('#dashboardPopup .dashboard-content2');
    if (dashboardContent) {
        dashboardContent.innerHTML = `
            <div style="text-align: center; padding: 3rem; color: #ef4444;">
                <div style="font-size: 1.5rem; margin-bottom: 1rem;">Error Loading Dashboard</div>
                <div>${message}</div>
                <button onclick="retryPopupDashboard()" style="margin-top: 20px; padding: 10px 20px; background: #3b82f6; color: white; border: none; border-radius: 5px; cursor: pointer;">
                    Retry
                </button>
            </div>
        `;
    }
}

function retryPopupDashboard() {
    const popupMonthSelect = document.getElementById('popupMonthSelect');
    const popupYearSelect = document.getElementById('popupYearSelect');
    const popupDateRangeFilter = document.getElementById('popupDateRangeFilter');

    if (popupDateRangeFilter && popupDateRangeFilter.style.display !== 'none') {
        // Currently in date range mode
        const popupStartDate = document.getElementById('popupStartDate');
        const popupEndDate = document.getElementById('popupEndDate');
        if (popupStartDate.value && popupEndDate.value) {
            loadPopupDashboardDataByDateRange(popupStartDate.value, popupEndDate.value);
        } else {
            // If no dates are set, switch back to month/year mode
            handlePopupCustomRangeCancel();
        }
    } else {
        // Currently in month/year mode
        const month = popupMonthSelect ? popupMonthSelect.value : new Date().getMonth() + 1;
        const year = popupYearSelect ? popupYearSelect.value : new Date().getFullYear();
        loadPopupDashboardData(month, year);
    }
}


let allUsersData = []; // Store all user data for filtering
let persistentUserTypes = {}; // Store correct user types persistently across sessions
let assignedUsersReady = false; // Indicates assigned users have been loaded at least once
let hierarchyReady = false; // Indicates hierarchy data has been loaded at least once

function initializeMultiSelect() {
    // Find the multi-select container in the popup-filters section
    const multiSelectContainer = document.querySelector('.popup-filters .multi-select-container');
    if (!multiSelectContainer) {
        // Silently skip if multi-select container doesn't exist (expected on some pages)
        return;
    }

    addBadgeStyles();
    addRoleFilterStyles();

    // Add enhanced CSS styles for search functionality
    if (!document.getElementById('enhancedMultiSelectStyles')) {
        const style = document.createElement('style');
        style.id = 'enhancedMultiSelectStyles';
        style.textContent = `
            .custom-multi-select {
                position: relative;
                width: 100%;
                margin-bottom: 0;
            }

            /* Fix z-index context for filters */
            .popup-filters, .user-filter-section {
                position: relative;
                z-index: 20;
            }
            
            .multi-select-dropdown {
                position: relative;
                border: 1px solid #e2e8f0;
                border-radius: 8px;
                background: white;
                cursor: pointer;
                transition: border-color 0.3s ease;
                min-height: 44px;
            }
            
            .multi-select-dropdown:hover {
                border-color: #cbd5e1;
            }
            
            .multi-select-dropdown.open {
                border-color: #3b82f6;
                box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            }
            
            .dropdown-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 0px 16px;
                min-height: 42px;
                cursor: pointer;
                user-select: none;
                -webkit-user-select: none;
                -moz-user-select: none;
                -ms-user-select: none;
                touch-action: manipulation;
                -webkit-tap-highlight-color: rgba(0,0,0,0.1);
            }
            
            .dropdown-placeholder {
                color: #6b7280;
                font-size: 14px;
                flex: 1;
            }
            
            .dropdown-arrow {
                width: 20px;
                height: 20px;
                transition: transform 0.3s ease;
                color: #6b7280;
            }
            
            .dropdown-arrow.open {
                transform: rotate(180deg);
            }
            
            .dropdown-options {
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: white;
                border: 2px solid #3b82f6;
                border-top: none;
                border-radius: 0 0 8px 8px;
                z-index: 1000;
                box-shadow: 0 10px 25px rgba(0,0,0,0.1);
                display: none;
                min-height: 250px;
            }
            
            .dropdown-options.show {
                display: block;
                animation: slideDown 0.3s ease;
            }
            
            @keyframes slideDown {
                from {
                    opacity: 0;
                    transform: translateY(-10px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            
            /* Search Input Styles */
            .dropdown-search {
                padding: 12px 16px;
                border-bottom: 1px solid #f1f5f9;
                background: #fafbfc;
            }
            
            .search-input {
                width: 100%;
                padding: 8px 12px;
                border: 1px solid #d1d5db;
                border-radius: 6px;
                font-size: 14px;
                outline: none;
                transition: all 0.2s ease;
                background: white;
            }
            
            .search-input:focus {
                border-color: #3b82f6;
                box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
            }
            
            .search-input::placeholder {
                color: #9ca3af;
            }
            
            .search-clear {
                position: absolute;
                right: 8px;
                top: 50%;
                transform: translateY(-50%);
                background: none;
                border: none;
                color: #6b7280;
                cursor: pointer;
                font-size: 16px;
                padding: 2px;
                border-radius: 3px;
                display: none;
                transition: color 0.2s ease;
            }
            
            .search-clear:hover {
                color: #374151;
                background: #f3f4f6;
            }
            
            .search-wrapper {
                position: relative;
                display: flex;
                align-items: center;
            }
            
            .search-no-results {
                padding: 20px;
                text-align: center;
                color: #6b7280;
                font-style: italic;
                background: #f9fafb;
            }
            
            .dropdown-controls {
                padding: 8px 12px;
                border-bottom: 1px solid #f1f5f9;
                display: flex;
                gap: 8px;
                align-items: center;
                flex-wrap: wrap;
            }
            
            .control-buttons {
                display: flex;
                gap: 8px;
                flex-wrap: nowrap;
                align-items: center;
            }

            .date-toggle-container {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 4px 8px;
                user-select: none;
                flex-shrink: 0;
                background: #f8fafc;
                border-radius: 6px;
                border: 1px solid #e2e8f0;
            }

            .date-toggle-label {
                font-size: 10px;
                font-weight: 600;
                color: #64748b;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                white-space: nowrap;
                order: 2;
            }

            .date-toggle-switch {
                position: relative;
                display: inline-block;
                width: 40px;
                height: 20px;
                flex-shrink: 0;
                order: 1;
            }

            .date-toggle-switch input {
                opacity: 0;
                width: 0;
                height: 0;
            }

            .date-toggle-slider {
                position: absolute;
                cursor: pointer;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-color: #d1d5db;
                transition: 0.3s;
                border-radius: 34px;
            }

            .date-toggle-slider:before {
                position: absolute;
                content: "";
                height: 16px;
                width: 16px;
                left: 2px;
                bottom: 2px;
                background-color: white;
                transition: 0.3s;
                border-radius: 50%;
                box-shadow: 0 1px 3px rgba(0,0,0,0.2);
            }

            .date-toggle-switch input:checked + .date-toggle-slider {
                background-color: #2563eb;
            }

            .date-toggle-switch input:checked + .date-toggle-slider:before {
                transform: translateX(20px);
            }

            .date-toggle-container[data-state="updated"] .date-toggle-label {
                color: #2563eb;
            }

            @media (max-width: 768px) {
                .dropdown-controls {
                    gap: 8px;
                }

                .date-toggle-label {
                    font-size: 9px;
                }
            }

            @media (max-width: 600px) {
                .control-buttons {
                    gap: 6px;
                }

                .control-btn {
                    font-size: 11px;
                    padding: 6px 10px;
                }

                .date-toggle-container {
                    padding: 4px 6px;
                    gap: 6px;
                }

                .date-toggle-switch {
                    width: 36px;
                    height: 18px;
                }

                .date-toggle-slider:before {
                    height: 14px;
                    width: 14px;
                }

                .date-toggle-switch input:checked + .date-toggle-slider:before {
                    transform: translateX(18px);
                }

                .date-toggle-label {
                    font-size: 8px;
                }
            }

            @media (max-width: 480px) {
                .dropdown-controls {
                    gap: 6px;
                }

                .control-btn {
                    font-size: 10px;
                    padding: 5px 8px;
                }
            }

            .filter-mode-indicator {
                position: absolute;
                top: 10px;
                right: 15px;
                padding: 4px 12px;
                border-radius: 12px;
                font-size: 12px;
                font-weight: 600;
                transition: all 0.3s ease;
                display: none;
            }

            @media (max-width: 768px) {
                .filter-mode-indicator {
                    padding: 2px 8px;
                    font-size: 10px;
                    border-radius: 8px;
                    top: 5px;
                    right: 10px;
                }
            }
            
            .results-count {
                font-size: 11px;
                color: #6b7280;
                font-weight: 500;
            }
            
            .control-btn {
                padding: 9px 8px;
                border: 1px solid #d1d5db;
                border-radius: 4px;
                background: white;
                font-size: 11px;
                cursor: pointer;
                transition: all 0.2s ease;
            }
            
            .control-btn:hover {
                background: #f3f4f6;
            }
            
            .select-all-btn {
                color: #059669;
                border-color: #059669;
            }
            
            .clear-all-btn {
                color: #dc2626;
                border-color: #dc2626;
            }
            
            .dropdown-option {
                display: flex;
                align-items: center;
                padding: 12px 16px;
                cursor: pointer;
                transition: background-color 0.2s ease;
            }
            
            .dropdown-option:last-child {
                border-bottom: none;
            }
            
            .dropdown-option:hover {
                background-color: #f8fafc;
            }
            
            .dropdown-option.selected {
                background-color: #eff6ff;
                color: #1d4ed8;
                font-weight: 500;
            }
            
            .dropdown-option.hidden {
                display: none;
            }
            
            .option-checkbox {
                width: 16px;
                height: 16px;
                margin-right: 12px;
                accent-color: #3b82f6;
            }
            
            .option-text {
                flex: 1;
                font-size: 14px;
            }
            .selected-tags::-webkit-scrollbar {
                display: none;
            }
           .selected-tags {
    display: flex;
        overflow: auto;
    gap: 6px;
    
    width: 100%;
   
}
    /* Ensure both user tags and project tags display nicely together */
.selected-tags,
.project-selected-tags {
    display: flex;
    
    gap: 6px;
    max-height: 60px;
    overflow-y: auto;
    width: 100%;
}

/* Make sure project tags have distinct styling */
.project-selected-tag {
    background: linear-gradient(135deg, #8b5cf6, #7c3aed) !important;
}

/* When both user and project tags are present, add some separation */
.selected-tags:not(:empty) + .project-selected-tags:not(:empty) {
    
    border-top: 1px solid #e5e7eb;
}

.user-filter-section {
    display: flex;
    flex-direction: column;
    gap: 0px;
    max-width: 100%;
}

.user-filter-section .filter-controls-container {
    display: flex;
    justify-content: center;
    align-items: baseline;
    width: 100%;
    gap: 20px;
}

@media (min-width: 768px) {
.filter-section-content {
    display: block !important;    
;
}
    }
/* Mobile view */
@media (max-width: 768px) {
    .user-filter-section .filter-controls-container {
        flex-direction: column;
        gap: 15px;
    }
    
    
}

            
            .user-tag {
                background: linear-gradient(135deg, #3b82f6, #1d4ed8);
                color: white;
                padding: 4px 8px;
                border-radius: 16px;
                font-size: 12px;
                display: flex;
                align-items: center;
                gap: 6px;
                animation: tagFadeIn 0.3s ease;
                max-width: 180px;
            }
            
            .user-tag .tag-text {
                overflow: hidden;
                white-space: nowrap;
                text-overflow: ellipsis;
            }
            
            .user-tag .remove-btn {
                border: none;
                color: white;
                border-radius: 50%;
                width: 16px;
                height: 16px;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                font-size: 12px;
                font-weight: bold;
                transition: background-color 0.2s ease;
                line-height: 1;
            }
            
            .user-tag .remove-btn:hover {
                background: rgba(255,255,255,0.5);
            }
            
            @keyframes tagFadeIn {
                from {
                    opacity: 0;
                    transform: scale(0.8);
                }
                to {
                    opacity: 1;
                    transform: scale(1);
                }
            }
            
            .options-list {
                max-height: 200px;
                overflow-y: auto;
            }
            
            /* Scrollbar styling */
            .options-list::-webkit-scrollbar {
                width: 6px;
            }
            
            .options-list::-webkit-scrollbar-track {
                background: #f1f1f1;
            }
            
            .options-list::-webkit-scrollbar-thumb {
                background: #c1c1c1;
                border-radius: 3px;
            }
            
            .options-list::-webkit-scrollbar-thumb:hover {
                background: #a8a8a8;
            }
            
            /* Dark theme support */
            [data-theme="dark"] .multi-select-dropdown {
                background: #1f2937;
                border-color: #374151;
                color: #f9fafb;
            }
            
            [data-theme="dark"] .dropdown-options {
                background: #1f2937;
                border-color: #374151;
            }
            
            [data-theme="dark"] .dropdown-option:hover {
                background-color: #374151;
            }
            
            [data-theme="dark"] .dropdown-option.selected {
                background-color: transparent;
                color: #bfdbfe;
            }
            
            [data-theme="dark"] .search-input {
                background: #374151;
                border-color: #4b5563;
                color: #f9fafb;
            }
            
            [data-theme="dark"] .search-input::placeholder {
                color: #9ca3af;
            }
            
            [data-theme="dark"] .dropdown-search {
                background: transparent;
                border-bottom: 1px solid #f1f5f970;
            }
            
            [data-theme="dark"] .control-btn {
                background: #454545;
                border-color: #4b5563;
                color: #f9fafb;
            }

            [data-theme='dark'] .role-option{
                background: rgba(255, 255, 255, 0.1) !important;
            }

            [data-theme='dark'] .role-option:hover{
                background: rgba(255, 255, 255, 0.2) !important;
            }

            [data-theme="dark"] .date-toggle-label {
                color: #e5e7eb;
            }

            [data-theme="dark"] .date-toggle-slider {
                background-color: #4b5563;
            }

            [data-theme="dark"] .date-toggle-switch input:checked + .date-toggle-slider {
                background-color: #2563eb;
            }
            [data-theme="dark"]  .date-toggle-container{
                background: #f8fafc21;
                border: 1px solid #e2e8f052;
            }  
            [data-theme="dark"] .dropdown-controls {
                border-bottom: 1px solid #f1f5f96e;
            }
            [data-theme="dark"] .project-dropdown-container {
                background: #1f2937;
            }
            [data-theme="dark"] .project-option {
                background: transparent;
                border-bottom: 1px solid #f3f4f61f;

            }
            [data-theme="dark"] .project-dropdown-options {
                background: #1f2937;
            }
            .project-apply-footer {
                background: white;
            }
            [data-theme="dark"] .project-apply-footer {
                background: #1f2937;
            }

            .apply-selection-btn {
                background: #2563eb;
                color: #fff;
                border: none;
                font-weight: 600;
                padding: 6px 12px;
                border-radius: 6px;
                font-size: 12px;
                line-height: 1;
                cursor: pointer;
                transition: all 0.2s ease;
                white-space: nowrap;
            }

            .apply-selection-btn:hover {
                background: #1d4ed8;
            }

            [data-theme="dark"] .apply-selection-btn {
                background: #1e40af;
            }

            [data-theme="dark"] .apply-selection-btn:hover {
                background: #1e3a8a;
            }

        `;
        document.head.appendChild(style);
    }

    // Clear existing content and create enhanced custom dropdown
    multiSelectContainer.innerHTML = `
    <div class="custom-multi-select">
        <!-- REMOVED: <div class="selected-tags"></div> -->
        <div class="multi-select-dropdown">
            <div class="dropdown-header">
                <span class="dropdown-placeholder">select team member...</span>
                <svg class="dropdown-arrow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </div>
            <div class="dropdown-options">
                <div class="dropdown-search">
                    <div class="search-wrapper">
                        <input 
                            type="text" 
                            class="search-input" 
                            placeholder="Search team members..."
                            oninput="handleSearchInput(event)"
                            onclick="event.stopPropagation()"
                            ontouchstart="event.stopPropagation()"
                            ontouchend="event.stopPropagation()"
                            onfocus="event.stopPropagation()"
                        />
                        <button class="search-clear" onclick="clearSearch(event)" ontouchend="clearSearch(event)">&times;</button>
                    </div>
                </div>
                <div class="dropdown-controls">
                    <div class="control-buttons">
                        <button class="control-btn select-all-btn" onclick="selectAllUsers(event)" ontouchend="selectAllUsers(event)">Select All</button>
                        <button class="control-btn clear-all-btn" onclick="clearAllUsers(event)" ontouchend="clearAllUsers(event)">Clear All</button>
                        ${createRoleFilterDropdown()}
                    </div>
                    <div class="date-toggle-container" id="dateColumnToggleContainer">
                        <span class="date-toggle-label" id="dateColumnToggleLabel">Created At</span>
                        <label class="date-toggle-switch">
                            <input type="checkbox" id="dateColumnToggle">
                            <span class="date-toggle-slider"></span>
                        </label>
                    </div>
                    <button class="apply-selection-btn" onclick="window.applyFiltersFromDropdown(event)" ontouchend="window.applyFiltersFromDropdown(event)">Apply</button>
                    <div class="results-count" id="resultsCount"></div>
                </div>
                <div class="options-list" id="optionsList"></div>
                <div class="search-no-results" id="noResultsMessage" style="display: none;">
                    No users found matching your search
                </div>
            </div>
        </div>
    </div>
`;
    // Attach event-aware dropdown toggle
    const dropdown = multiSelectContainer.querySelector('.multi-select-dropdown');
    if (dropdown) {
        // Handle both click and touch events for better mobile support
        dropdown.addEventListener('click', function (e) {
            // Only toggle if clicking header, not inside options
            if (e.target.closest('.dropdown-header')) {
                e.stopPropagation();
                toggleDropdown(e);
            }
        });

        // Add touch event specifically for mobile devices
        dropdown.addEventListener('touchend', function (e) {
            // Only toggle if touching header, not inside options
            if (e.target.closest('.dropdown-header')) {
                e.preventDefault();
                e.stopPropagation();
                toggleDropdown(e);
            }
        }, { passive: false });

        // Prevent closing when clicking inside dropdown
        dropdown.addEventListener('click', function (e) {
            e.stopPropagation();
        });

        // Prevent closing when touching inside dropdown
        dropdown.addEventListener('touchstart', function (e) {
            e.stopPropagation();
        });
    }
    // Prevent closing when clicking inside options list
    const optionsList = multiSelectContainer.querySelector('#optionsList');
    if (optionsList) {
        optionsList.addEventListener('click', function (e) {
            e.stopPropagation();
        });

        // Prevent closing when touching inside options list (mobile)
        optionsList.addEventListener('touchstart', function (e) {
            e.stopPropagation();
        });
    }

    const dateToggle = multiSelectContainer.querySelector('#dateColumnToggle');
    if (dateToggle) {
        dateToggle.addEventListener('change', handleDateColumnToggleChange);
        dateToggle.addEventListener('click', function (e) {
            e.stopPropagation();
        });
        dateToggle.addEventListener('touchstart', function (e) {
            e.stopPropagation();
        });
    }

    const dateToggleContainer = multiSelectContainer.querySelector('#dateColumnToggleContainer');
    if (dateToggleContainer) {
        dateToggleContainer.addEventListener('click', function (e) {
            e.stopPropagation();
        });
        dateToggleContainer.addEventListener('touchstart', function (e) {
            e.stopPropagation();
        });
    }

    updateDateToggleUI();

    // Prevent closing when interacting with search input (critical for mobile)
    const searchInput = multiSelectContainer.querySelector('.search-input');
    if (searchInput) {
        searchInput.addEventListener('click', function (e) {
            e.stopPropagation();
        });

        searchInput.addEventListener('touchstart', function (e) {
            e.stopPropagation();
            window._keyboardFocusLock = true;
            setTimeout(() => { window._keyboardFocusLock = false; }, 800);
        });

        searchInput.addEventListener('touchend', function (e) {
            e.stopPropagation();
        });

        searchInput.addEventListener('focus', function (e) {
            e.stopPropagation();
            window._keyboardFocusLock = true;
            setTimeout(() => { window._keyboardFocusLock = false; }, 800);
        });
    }

    // Prevent closing when interacting with search wrapper
    const searchWrapper = multiSelectContainer.querySelector('.search-wrapper');
    if (searchWrapper) {
        searchWrapper.addEventListener('click', function (e) {
            e.stopPropagation();
        });

        searchWrapper.addEventListener('touchstart', function (e) {
            e.stopPropagation();
        });

        searchWrapper.addEventListener('touchend', function (e) {
            e.stopPropagation();
        });
    }

    // Prevent closing when interacting with dropdown search container
    const dropdownSearch = multiSelectContainer.querySelector('.dropdown-search');
    if (dropdownSearch) {
        dropdownSearch.addEventListener('click', function (e) {
            e.stopPropagation();
        });

        dropdownSearch.addEventListener('touchstart', function (e) {
            e.stopPropagation();
        });

        dropdownSearch.addEventListener('touchend', function (e) {
            e.stopPropagation();
        });
    }

    // Prevent closing when interacting with control buttons
    const controlButtons = multiSelectContainer.querySelector('.dropdown-controls');
    if (controlButtons) {
        controlButtons.addEventListener('click', function (e) {
            e.stopPropagation();
        });

        controlButtons.addEventListener('touchstart', function (e) {
            e.stopPropagation();
        });

        controlButtons.addEventListener('touchend', function (e) {
            e.stopPropagation();
        });
    }

    // Prevent closing when interacting with dropdown options container
    const dropdownOptions = multiSelectContainer.querySelector('.dropdown-options');
    if (dropdownOptions) {
        dropdownOptions.addEventListener('click', function (e) {
            e.stopPropagation();
        });

        dropdownOptions.addEventListener('touchstart', function (e) {
            e.stopPropagation();
        });

        dropdownOptions.addEventListener('touchend', function (e) {
            e.stopPropagation();
        });
    }


    // Populate options
    populateDropdownOptions();
    updateResultsCount();
    addBadgeStyles();

    // Force refresh badges after initialization to prevent overwriting
    setTimeout(() => {
        refreshDropdownBadges();
    }, 150);

    // Close dropdown when clicking outside
    document.addEventListener('click', handleOutsideClick);

    // Add touch event support for mobile devices
    // Use touchend instead of touchstart to avoid conflicts with scroll gestures
    document.addEventListener('touchend', handleOutsideClick);

    // Start badge integrity monitoring
    startBadgeMonitoring();
}



// Enhanced search functionality
function handleSearchInput(event) {
    event.stopPropagation();
    const searchTerm = event.target.value.toLowerCase().trim();
    const clearButton = document.querySelector('.search-clear');
    const optionsList = document.getElementById('optionsList');
    const noResultsMessage = document.getElementById('noResultsMessage');
    const options = optionsList.querySelectorAll('.dropdown-option');

    // Show/hide clear button
    clearButton.style.display = searchTerm ? 'block' : 'none';

    let visibleCount = 0;

    options.forEach(option => {
        const optionText = option.querySelector('.option-text').textContent.toLowerCase();
        const matches = optionText.includes(searchTerm);

        if (matches || searchTerm === '') {
            option.classList.remove('hidden');
            option.style.display = 'flex';
            visibleCount++;
        } else {
            option.classList.add('hidden');
            option.style.display = 'none';
        }
    });

    // Show/hide no results message
    if (visibleCount === 0 && searchTerm !== '') {
        noResultsMessage.style.display = 'block';
        optionsList.style.display = 'none';
    } else {
        noResultsMessage.style.display = 'none';
        optionsList.style.display = 'block';
    }

    // Update results count
    updateResultsCount(visibleCount, searchTerm);
}


function clearSearch(event) {
    event.stopPropagation();
    const searchInput = document.querySelector('.search-input');
    const clearButton = document.querySelector('.search-clear');

    // Guard against missing elements (e.g., when called from different page context)
    if (!searchInput) {
        console.warn('clearSearch: .search-input not found - skipping');
        return;
    }

    searchInput.value = '';
    if (clearButton) {
        clearButton.style.display = 'none';
    }

    // Show all options
    const optionsList = document.getElementById('optionsList');
    const noResultsMessage = document.getElementById('noResultsMessage');

    if (optionsList) {
        const options = optionsList.querySelectorAll('.dropdown-option');

        options.forEach(option => {
            option.classList.remove('hidden');
            option.style.display = 'flex';
        });

        if (noResultsMessage) {
            noResultsMessage.style.display = 'none';
        }
        optionsList.style.display = 'block';
    }

    updateResultsCount();
    // Removed focus after clearing search to prevent mobile keyboard jumping
}

function updateResultsCount(visibleCount = null, searchTerm = '') {
    const resultsCountElement = document.getElementById('resultsCount');
    if (!resultsCountElement) return;

    const totalCount = getUsersData().length;

    if (visibleCount === null) {
        const options = document.querySelectorAll('.dropdown-option:not(.hidden)');
        visibleCount = options.length;
    }

    if (searchTerm) {
        resultsCountElement.textContent = `${visibleCount} of ${totalCount} users`;
        resultsCountElement.style.color = visibleCount === 0 ? '#dc2626' : '#059669';
    } else {
        resultsCountElement.textContent = ``;
        resultsCountElement.style.color = '#6b7280';
    }
}

// Unified function to get role badge information
function getRoleBadge(userType) {
    // Convert to string, trim, and lowercase for consistent comparison
    const rawType = String(userType || '').trim().toLowerCase();

    // Define role mappings with their badges and colors
    const roleMap = {
        // Promoter variations
        'promoter': { role: 'promoter', badge: 'P', color: '#7c2d12' },
        'ceo': { role: 'promoter', badge: 'P', color: '#7c2d12' },
        'c': { role: 'promoter', badge: 'P', color: '#7c2d12' },

        // Business Head variations
        'business head': { role: 'business head', badge: 'BH', color: '#dc2626' },
        'business_head': { role: 'business head', badge: 'BH', color: '#dc2626' },
        'businesshead': { role: 'business head', badge: 'BH', color: '#dc2626' },
        'bh': { role: 'business head', badge: 'BH', color: '#dc2626' },

        // Manager variations
        'manager': { role: 'manager', badge: 'M', color: '#2563eb' },
        'm': { role: 'manager', badge: 'M', color: '#2563eb' },

        // Team Lead variations
        'team lead': { role: 'team lead', badge: 'TL', color: '#7c3aed' },
        'team_lead': { role: 'team lead', badge: 'TL', color: '#7c3aed' },
        'teamlead': { role: 'team lead', badge: 'TL', color: '#7c3aed' },
        'tl': { role: 'team lead', badge: 'TL', color: '#7c3aed' },

        // User/default variations
        'user': { role: 'user', badge: 'U', color: '#059669' },
        'u': { role: 'user', badge: 'U', color: '#059669' }
    };

    // Return mapped role or default to user
    const result = roleMap[rawType] || { role: 'user', badge: 'U', color: '#059669' };
    return result;
}

// Function to force refresh dropdown badges after data loading
function refreshDropdownBadges() {
    const optionsList = document.getElementById('optionsList');
    if (!optionsList) {
        return;
    }

    const options = optionsList.querySelectorAll('.dropdown-option');

    // Get fresh user data 
    const users = getUsersData();

    options.forEach((option) => {
        const checkbox = option.querySelector('.option-checkbox');
        const badgeElement = option.querySelector('.user-type-badge');
        const textElement = option.querySelector('.option-text');

        if (checkbox && badgeElement && textElement) {
            const userValue = checkbox.value;

            // Find user by value (tablename) instead of array index
            let userData = users.find(u => u.value === userValue);

            // Fallback: check persistent storage directly if user not found in array
            if (!userData || !userData.user_type) {
                const persistentType = persistentUserTypes[userValue];
                if (persistentType) {
                    userData = { user_type: persistentType, text: textElement.textContent };
                }
            }

            if (userData && userData.user_type) {
                const roleBadge = getRoleBadge(userData.user_type);

                // Update badge appearance
                badgeElement.style.background = roleBadge.color;
                badgeElement.textContent = roleBadge.badge;
                badgeElement.title = roleBadge.role.toUpperCase();
            } else {
                console.warn(`No user data found for refresh: ${textElement.textContent} (value: ${userValue})`);
            }
        }
    });
}

// Enhanced populateDropdownOptions function
function populateDropdownOptions() {
    const optionsList = document.getElementById('optionsList');
    if (!optionsList) return;

    // Save dropdown open state before repopulating
    const dropdown = document.querySelector('.multi-select-dropdown');
    const wasOpen = dropdown?.classList.contains('open');
    const savedDropdownOpenState = dropdownOpen; // Save the global variable state

    // Set flag to prevent handleOutsideClick from closing during repopulation
    isRepopulatingDropdown = true;

    optionsList.innerHTML = '';

    let users = [];

    // Try to get correct user types from hierarchy data as backup
    let hierarchyUserTypes = {};
    if (window.hierarchyData && window.hierarchyData.length > 0) {
        window.hierarchyData.forEach(user => {
            if (user.tablename && user.user_type) {
                hierarchyUserTypes[user.tablename] = user.user_type;
            }
        });
    }

    // CRITICAL: Use originalUsersData for dropdown to show ALL users, not just filtered ones
    // allUsersData may be filtered based on selections, but dropdown should always show everyone
    const userDataSource = (originalUsersData && originalUsersData.length > 0) ? originalUsersData : allUsersData;

    // Get users from the appropriate data source
    if (userDataSource && userDataSource.length) {
        users = userDataSource.map(user => {
            const tablename = user.tablename || user.value;

            // Check multiple sources for correct user type with priority
            let correctUserType = user.user_type || 'user';

            // First priority: persistent storage (most reliable)
            if (persistentUserTypes[tablename]) {
                correctUserType = persistentUserTypes[tablename];
            }
            // Second priority: hierarchy data (backup)
            else if (hierarchyUserTypes[tablename]) {
                correctUserType = hierarchyUserTypes[tablename];
            }

            return {
                value: tablename,
                text: user.name || user.username || user.text,
                user_type: correctUserType
            };
        });
    } else {
        // Try to fetch user data if data sources are empty
        fetchAssignedUsers().then(() => {
            // Retry populating dropdown after data is loaded
            if (allUsersData && allUsersData.length > 0) {
                populateDropdownOptions();
                return;
            }
        }).catch(error => {
            console.error('Error fetching assigned users:', error);
        });

        // Fallback to traditional dropdown data
        const namesSelect = document.getElementById('namesSelect') || document.getElementById('name-select');
        if (namesSelect && namesSelect.options) {
            for (let i = 1; i < namesSelect.options.length; i++) {
                const option = namesSelect.options[i];
                if (option.value) {
                    const userType = option.getAttribute('data-user-type') || 'user';

                    // Check persistent storage first, then hierarchy, then original
                    let correctUserType = userType;
                    if (persistentUserTypes[option.value]) {
                        correctUserType = persistentUserTypes[option.value];
                    } else if (hierarchyUserTypes[option.value]) {
                        correctUserType = hierarchyUserTypes[option.value];
                    }

                    users.push({
                        value: option.value,
                        text: option.textContent,
                        user_type: correctUserType
                    });
                }
            }
        } else {
            console.error('No namesSelect found for fallback');
        }
    }

    // Sort users alphabetically for better UX
    users.sort((a, b) => a.text.localeCompare(b.text));

    // Create option elements with user type badges
    users.forEach(user => {
        const optionDiv = document.createElement('div');
        optionDiv.className = 'dropdown-option';
        if (selectedUsers.has(user.value)) {
            optionDiv.classList.add('selected');
        }

        // Use unified role badge function
        const roleBadge = getRoleBadge(user.user_type);

        optionDiv.innerHTML = `
            <input type="checkbox" class="option-checkbox" value="${user.value}" ${selectedUsers.has(user.value) ? 'checked' : ''}>
            <div class="user-type-badge" style="
                background: ${roleBadge.color};
                color: white;
                width: 24px;
                height: 20px;
                border-radius: 4px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 9px;
                font-weight: bold;
                margin-right: 10px;
                flex-shrink: 0;
            " title="${roleBadge.role.toUpperCase()}">${roleBadge.badge}</div>
            <span class="option-text">${user.text}</span>
        `;

        optionDiv.addEventListener('click', (e) => handleOptionClick(e, user.value, user.text));
        optionsList.appendChild(optionDiv);
    });

    // Restore dropdown open state if it was open before repopulating
    if (wasOpen || savedDropdownOpenState) {
        const options = document.querySelector('.dropdown-options');
        const arrow = document.querySelector('.dropdown-arrow');

        // Restore CSS classes
        if (dropdown) dropdown.classList.add('open');
        if (options) options.classList.add('show');
        if (arrow) arrow.classList.add('open');

        // CRITICAL: Restore the global dropdownOpen variable
        dropdownOpen = true;
    }

    // Force refresh badges immediately AND after a short delay to ensure they don't get overwritten
    refreshDropdownBadges();
    setTimeout(() => {
        refreshDropdownBadges();

        // Clear the repopulation flag after everything is done
        isRepopulatingDropdown = false;
    }, 100);
}


// Handle option click - this maintains multiple selections


// Toggle dropdown visibility
function toggleDropdown(event) {
    // Stop propagation to prevent handleOutsideClick from immediately closing the dropdown
    if (event) {
        event.stopPropagation();
    }

    const dropdown = document.querySelector('.multi-select-dropdown');
    const options = document.querySelector('.dropdown-options');
    const arrow = document.querySelector('.dropdown-arrow');
    const searchInput = document.querySelector('.search-input');

    if (!dropdown || !options || !arrow) return;

    dropdownOpen = !dropdownOpen;

    if (dropdownOpen) {
        dropdown.classList.add('open');
        options.classList.add('show');
        arrow.classList.add('open');

        // Note: Auto-focus is disabled entirely to prevent mobile keyboards triggering layout collapse
    } else {
        dropdown.classList.remove('open');
        options.classList.remove('show');
        arrow.classList.remove('open');

        // Clear search when closing
        if (searchInput) {
            searchInput.value = '';
            clearSearch(new Event('click'));
        }
    }
}

// Handle clicks outside dropdown to close it
function handleOutsideClick(event) {
    if (window._keyboardFocusLock) return; // Prevent closing during keyboard slide-up
    const multiSelect = document.querySelector('.custom-multi-select');
    const dropdown = document.querySelector('.multi-select-dropdown');

    // Additional checks to prevent closing when clicking on dropdown elements
    const searchInput = document.querySelector('.search-input');
    const searchWrapper = document.querySelector('.search-wrapper');
    const dropdownSearch = document.querySelector('.dropdown-search');
    const dropdownOptions = document.querySelector('.dropdown-options');
    const controlButtons = document.querySelector('.dropdown-controls');

    // Check if click is on any interactive element inside dropdown
    const isInteractiveElement =
        (searchInput && searchInput.contains(event.target)) ||
        (searchWrapper && searchWrapper.contains(event.target)) ||
        (dropdownSearch && dropdownSearch.contains(event.target)) ||
        (dropdownOptions && dropdownOptions.contains(event.target)) ||
        (controlButtons && controlButtons.contains(event.target));

    // Only close if click is truly outside and not on any interactive element
    // Also don't close if we're currently repopulating the dropdown
    if (multiSelect && !multiSelect.contains(event.target) && !isInteractiveElement && dropdownOpen && !isRepopulatingDropdown) {
        // Extra check to ensure we're not closing immediately after opening
        // This helps prevent mobile touch event issues
        setTimeout(() => {
            if (dropdownOpen && !isRepopulatingDropdown) {
                toggleDropdown();
            }
        }, 50);
    }
}


function redirectToEOIsWithFilter() {
    // Prefer explicit selection; otherwise default to the logged-in user so EOIs are scoped
    const namesSelect = document.getElementById('namesSelect') || document.getElementById('name-select');
    const explicitSelection = currentlySelectedUser || (namesSelect ? namesSelect.value : '');
    const fallbackUser = (typeof currentUserTableName !== 'undefined' && currentUserTableName)
        || (typeof window !== 'undefined' && window.CURRENT_TABLENAME)
        || (typeof window !== 'undefined' && window.currentUserName)
        || (typeof window !== 'undefined' && window.currentUserDisplayName)
        || '';
    const finalUser = String(explicitSelection || fallbackUser || '').trim();

    // Use the same date logic as other functions - check for active custom date range first
    let startDate, endDate;

    if (lastStartDate && lastEndDate) {
        // Use active custom date range
        startDate = lastStartDate;
        endDate = lastEndDate;
    } else {
        // Fallback to month/year selection
        const monthSelect = document.getElementById("monthSelect");
        const yearSelect = document.getElementById("yearSelect");

        let month = monthSelect ? monthSelect.value : currentSelectedMonth || new Date().getMonth() + 1;
        let year = yearSelect ? yearSelect.value : currentSelectedYear || new Date().getFullYear();

        // Calculate start and end dates for the selected month/year
        startDate = `${year}-${String(month).padStart(2, '0')}-01`;
        const lastDay = new Date(year, month, 0).getDate();
        endDate = `${year}-${String(month).padStart(2, '0')}-${String(lastDay).padStart(2, '0')}`;
    }

    // Build the redirect URL for EOI page
    let redirectUrl = `/incentiveapp_integration/userlogin1/userlogin6/user_eoi?start_date=${encodeURIComponent(startDate)}&end_date=${encodeURIComponent(endDate)}`;

    // Always scope to the resolved user (even when it's the current user)
    if (finalUser) {
        redirectUrl += `&managerView=true&filterUser=${encodeURIComponent(finalUser)}`;
    }

    window.location.href = redirectUrl;
}

// Function to redirect to Bookings page with date filters
function redirectToBookingsWithFilter() {
    const normalizeUser = (v) => String(v || '').trim().toLowerCase().split(/[\s(-]+/)[0];
    const matchesSelf = (val) => {
        if (!val) return false;
        const needle = normalizeUser(val);
        const tn = normalizeUser(typeof currentUserTableName !== 'undefined' ? currentUserTableName : '');
        const un = normalizeUser(typeof window.currentUserName !== 'undefined' ? window.currentUserName : '');
        const dn = normalizeUser(typeof window.currentUserDisplayName !== 'undefined' ? window.currentUserDisplayName : '');
        return needle && (needle === tn || needle === un || needle === dn);
    };
    // Check if a user is selected - use stored value first, then dropdown
    let selectedUser = currentlySelectedUser;
    if (!selectedUser) {
        const namesSelect = document.getElementById('namesSelect') || document.getElementById('name-select');
        selectedUser = namesSelect ? namesSelect.value : '';
    }

    // Use the same date logic as other functions - check for active custom date range first
    let startDate, endDate;

    if (lastStartDate && lastEndDate) {
        // Use active custom date range
        startDate = lastStartDate;
        endDate = lastEndDate;
    } else {
        // Fallback to month/year selection
        const monthSelect = document.getElementById("monthSelect");
        const yearSelect = document.getElementById("yearSelect");

        let month = monthSelect ? monthSelect.value : currentSelectedMonth || new Date().getMonth() + 1;
        let year = yearSelect ? yearSelect.value : currentSelectedYear || new Date().getFullYear();

        // Calculate start and end dates for the selected month/year
        startDate = `${year}-${String(month).padStart(2, '0')}-01`;
        const lastDayBooking = new Date(year, month, 0).getDate();
        endDate = `${year}-${String(month).padStart(2, '0')}-${String(lastDayBooking).padStart(2, '0')}`;
    }

    // Build the redirect URL for Bookings page
    let redirectUrl = `/incentiveapp_integration/userlogin1/userlogin6/user_booking?start_date=${encodeURIComponent(startDate)}&end_date=${encodeURIComponent(endDate)}`;

    // Add user filter if a different user is selected
    if (selectedUser && selectedUser.trim() !== '' && !matchesSelf(selectedUser)) {
        redirectUrl += `&managerView=true&filterUser=${encodeURIComponent(selectedUser)}`;
    }

    window.location.href = redirectUrl;
}

// Update dropdown placeholder text
function updateDropdownPlaceholder() {
    const placeholder = document.querySelector('.dropdown-placeholder');
    if (!placeholder) return;

    const count = selectedUsers.size;
    if (count === 0) {
        placeholder.textContent = 'Click to select users...';
        placeholder.style.color = '#6b7280';
    } else {
        placeholder.textContent = `${count} user${count > 1 ? 's' : ''} selected`;
        placeholder.style.color = '#374151';
    }
}



// Select all users


// Clear all selections


// Get users data from available sources
function getUsersData() {
    // Use originalUsersData if available (preserves correct user types)
    if (originalUsersData && originalUsersData.length) {
        return originalUsersData.map(user => ({
            value: user.tablename,
            text: user.username || user.name,
            user_type: user.user_type
        }));
    }

    // Fallback to allUsersData with persistent storage priority
    if (allUsersData && allUsersData.length) {
        return allUsersData.map(user => {
            // Use persistent storage if available, otherwise use current data
            const persistentType = persistentUserTypes[user.tablename];
            const finalType = persistentType || user.user_type || 'user';

            return {
                value: user.tablename,
                text: user.username || user.name,
                user_type: finalType
            };
        });
    }

    // Last resort: get from dropdown options
    const users = [];
    const namesSelect = document.getElementById('namesSelect') || document.getElementById('name-select');
    if (namesSelect && namesSelect.options) {
        for (let i = 1; i < namesSelect.options.length; i++) {
            const option = namesSelect.options[i];
            if (option.value) {
                const userType = option.getAttribute('data-user-type') || 'user';
                users.push({
                    value: option.value,
                    text: option.textContent,
                    user_type: userType
                });
            }
        }
    }

    return users;
}



function updateStatsDisplay(stats, isFiltered = false) {
    // Priority guard: prevents lower-priority updates from overwriting higher-priority ones.
    // Callers may pass a third argument as options object: { priority: number, source: string }
    // Extract options (if provided) to determine incoming priority
    const callerArgs = arguments;
    let opts = {};
    if (callerArgs && callerArgs.length >= 3) {
        const third = callerArgs[2];
        if (typeof third === 'object') opts = third;
        else if (typeof third === 'number') opts.priority = third;
    }
    const incomingPriority = (opts && opts.priority) ? opts.priority : 1; // default low priority

    try {
        if (typeof window !== 'undefined') {
            if (!window.__lastStatsUpdate) window.__lastStatsUpdate = { priority: 0, timestamp: 0 };
        } else {
            if (typeof __lastStatsUpdate === 'undefined') var __lastStatsUpdate = { priority: 0, timestamp: 0 };
        }

        const last = (typeof window !== 'undefined') ? window.__lastStatsUpdate : __lastStatsUpdate;
        if (last && last.priority > incomingPriority) {
            console.info('updateStatsDisplay: skipped lower-priority update', { incomingPriority, last, source: opts.source });
            return;
        }

        // Record this update as last-applied
        const now = Date.now();
        if (typeof window !== 'undefined') {
            window.__lastStatsUpdate = { priority: incomingPriority, timestamp: now };
        } else {
            __lastStatsUpdate = { priority: incomingPriority, timestamp: now };
        }
    } catch (e) {
        // Fail-safe: continue if anything goes wrong with guard
        console.warn('updateStatsDisplay: priority guard error', e);
    }

    const statCards = document.querySelectorAll('.stats-overview .stat-card');
    if (!statCards || statCards.length < 4) return;

    const prefix = isFiltered ? 'Filtered ' : '';

    // Update the header title to show filtered total users count
    if (isFiltered) {
        const headerH1 = document.querySelector('#dashboardPopup .popup-header h1');
        if (headerH1) {
            headerH1.textContent = `Total Users: ${stats.totalUsers || 0}`;
        }
    }

    if (statCards[0]) {
        const h3 = statCards[0].querySelector('h3');
        const number = statCards[0].querySelector('.number');
        if (h3) h3.textContent = `${prefix}Total Leads`;
        if (number) number.textContent = stats.totalLeads || 0;
    }

    if (statCards[1]) {
        const h3 = statCards[1].querySelector('h3');
        const number = statCards[1].querySelector('.number');
        if (h3) h3.textContent = `${prefix}Total Bookings`;
        if (number) number.textContent = stats.totalBookings || 0;
    }

    if (statCards[2]) {
        const h3 = statCards[2].querySelector('h3');
        const number = statCards[2].querySelector('.number');
        if (h3) h3.textContent = `${prefix}Total EOI`;
        if (number) number.textContent = stats.totalEOI || 0;
    }

    if (statCards[3]) {
        const h3 = statCards[3].querySelector('h3');
        const number = statCards[3].querySelector('.number');
        if (h3) h3.textContent = `${prefix}Cancelled Bookings`;
        if (number) number.textContent = stats.totalCancelledBookings || 0;
    }

    // Debug: log applied update for easier troubleshooting in the browser console
    try {
        console.info('updateStatsDisplay: applied', { stats: stats, isFiltered: !!isFiltered, incomingPriority: incomingPriority, source: opts.source });
    } catch (e) { /* ignore logging errors */ }
}

// Helper: Normalize aggregated analytics payload to a canonical totals object
function extractAggregatedTotals(aggregated) {
    if (!aggregated) return {
        totalUsers: 0,
        totalLeads: 0,
        totalBookings: 0,
        totalEOI: 0,
        totalCancelledBookings: 0
    };

    // Accept snake_case or camelCase variants from server
    const totalUsers = aggregated.total_users ?? aggregated.totalUsers ?? 0;
    const totalLeads = aggregated.total_leads ?? aggregated.totalLeads ?? aggregated.totalLeadsCount ?? 0;
    const totalBookings = aggregated.total_bookings ?? aggregated.totalBookings ?? aggregated.bookings_total ?? 0;
    const totalEOI = aggregated.total_eoi ?? aggregated.totalEOI ?? aggregated.totalEoi ?? 0;
    const totalCanceled = aggregated.total_canceled_bookings ?? aggregated.total_canceled_bookings ?? aggregated.totalCanceledBookings ?? aggregated.total_canceled ?? 0;

    return {
        totalUsers: parseInt(totalUsers, 10) || 0,
        totalLeads: parseInt(totalLeads, 10) || 0,
        totalBookings: parseInt(totalBookings, 10) || 0,
        totalEOI: parseInt(totalEOI, 10) || 0,
        totalCancelledBookings: parseInt(totalCanceled, 10) || 0
    };
}

// Badge integrity monitoring system
function startBadgeMonitoring() {
    // Monitor dropdown for incorrect badges every 2 seconds
    const badgeMonitor = setInterval(() => {
        const optionsList = document.getElementById('optionsList');
        if (!optionsList) return;

        const options = optionsList.querySelectorAll('.dropdown-option');
        let foundIncorrect = false;

        options.forEach((option, index) => {
            const badgeElement = option.querySelector('.user-type-badge');
            const users = getUsersData();

            if (badgeElement && users[index]) {
                const user = users[index];
                const correctBadge = getRoleBadge(user.user_type);
                const currentBadge = badgeElement.textContent.trim();

                // Check if badge is incorrect (showing 'U' for non-user roles)
                if (currentBadge === 'U' && correctBadge.badge !== 'U') {
                    foundIncorrect = true;
                }
            }
        });

        // If incorrect badges found, refresh immediately
        if (foundIncorrect) {
            refreshDropdownBadges();
        }
    }, 10000);

    // Store monitor ID for cleanup
    window.badgeMonitorInterval = badgeMonitor;

    // Also add mutation observer for DOM changes
    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            if (mutation.type === 'childList' && mutation.target.id === 'optionsList') {
                setTimeout(() => refreshDropdownBadges(), 50);
            }
        });
    });

    const optionsList = document.getElementById('optionsList');
    if (optionsList) {
        observer.observe(optionsList, { childList: true, subtree: true });
        window.badgeObserver = observer;
    }
}

// Stop badge monitoring when dashboard is closed
function stopBadgeMonitoring() {
    if (window.badgeMonitorInterval) {
        clearInterval(window.badgeMonitorInterval);
        window.badgeMonitorInterval = null;
    }

    if (window.badgeObserver) {
        window.badgeObserver.disconnect();
        window.badgeObserver = null;
    }
}

// Project filter variables and functions
let selectedProjectNames = new Set(); // Store selected project names
let allProjectNames = []; // Store all available project names
let projectDropdownOpen = false;

// View mode tracking
let currentViewMode = 'normal'; // 'normal' or 'hierarchy'
let currentHierarchyUser = null; // Store the user for whom hierarchy is shown

// Helper function to get selected project names
function getSelectedProjectNames() {
    return Array.from(selectedProjectNames);
}

// Initialize project filter
async function initializeProjectFilter() {
    try {
        // Fetch project names from database
        allProjectNames = await fetchProjectNames();

        if (allProjectNames.length === 0) {
            console.warn('No project names found in user_remarks.assign_project_name');
        }

        // Populate project dropdown
        populateProjectDropdown();

        // Setup event listeners
        setupProjectFilterListeners();

    } catch (error) {
        console.error('Error initializing project filter:', error);
    }
}

// Populate project dropdown options
function populateProjectDropdown() {
    const projectOptionsList = document.getElementById('projectOptionsList');
    const dropdownOptions = document.getElementById('projectDropdownOptions');
    if (!projectOptionsList) return;

    projectOptionsList.innerHTML = '';

    if (allProjectNames.length === 0) {
        projectOptionsList.innerHTML = '<div style="padding: 12px; color: #6b7280; font-style: italic;">No projects found</div>';
        return;
    }

    allProjectNames.forEach((projectName, index) => {
        if (projectName && projectName.trim()) {
            // Use index-based ID to avoid issues with special characters in project names
            const safeId = `project-${index}`;

            const option = document.createElement('div');
            option.className = 'project-option';
            option.setAttribute('data-project-name', projectName);
            option.innerHTML = `
                <input type="checkbox" id="${safeId}" ${selectedProjectNames.has(projectName) ? 'checked' : ''}>
                <label for="${safeId}" style="cursor: pointer; flex: 1;">${projectName}</label>
            `;

            // Add click event to the entire option div for better UX
            option.addEventListener('click', (e) => {
                // Only handle clicks if not directly on checkbox or label
                if (e.target.type !== 'checkbox' && e.target.tagName !== 'LABEL') {
                    e.preventDefault();
                    e.stopPropagation();
                    handleProjectOptionClick(projectName);
                }
            });

            // Let checkbox work naturally
            const checkbox = option.querySelector(`#${safeId}`);
            if (checkbox) {
                checkbox.addEventListener('change', (e) => {
                    handleProjectOptionClick(projectName);
                });
            }

            // Make label clickable too
            const label = option.querySelector('label');
            if (label) {
                label.addEventListener('click', (e) => {
                    // Prevent default to avoid double-clicking the checkbox
                    e.preventDefault();
                    e.stopPropagation();

                    // Toggle checkbox state manually
                    checkbox.checked = !checkbox.checked;

                    handleProjectOptionClick(projectName);
                });
            }

            projectOptionsList.appendChild(option);
        }
    });

    // Add sticky apply/clear buttons inside dropdown so data loads only when requested
    if (dropdownOptions && !dropdownOptions.querySelector('.project-apply-footer')) {
        const footer = document.createElement('div');
        footer.className = 'project-apply-footer';
        footer.style.cssText = 'position: sticky; bottom: 0; padding: 10px 12px; border-top: 1px solid #e5e7eb; box-shadow: 0 -2px 6px rgba(0,0,0,0.04); display: flex; gap: 8px; background: #f9fafb;';

        const clearBtn = document.createElement('button');
        clearBtn.textContent = 'Clear';
        clearBtn.style.cssText = 'flex: 1; padding: 10px 12px; background: #ffffff; color: #374151; border: 1px solid #d1d5db; border-radius: 6px; font-weight: 500; cursor: pointer;';
        clearBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            clearAllProjects();
        });

        const applyBtn = document.createElement('button');
        applyBtn.textContent = 'Apply';
        applyBtn.style.cssText = 'flex: 1; padding: 10px 12px; background: #2563eb; color: #fff; border: none; border-radius: 6px; font-weight: 600; cursor: pointer;';
        applyBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            applyProjectFilters();
        });

        footer.appendChild(clearBtn);
        footer.appendChild(applyBtn);
        dropdownOptions.appendChild(footer);
    }
}

// Handle project option click
function handleProjectOptionClick(projectName) {
    // Find checkbox by project name using data attribute
    const option = document.querySelector(`[data-project-name="${projectName}"]`);
    const checkbox = option?.querySelector('input[type="checkbox"]');

    if (!checkbox) {
        console.error('Checkbox not found for project:', projectName);
        return;
    }

    // Update selection based on current checkbox state
    if (checkbox.checked) {
        selectedProjectNames.add(projectName);
    } else {
        selectedProjectNames.delete(projectName);
    }

    updateProjectSelectedTags();
    updateProjectPlaceholder();

    // Apply filtering
    handleProjectFilterChange();
}

// Update selected project tags display
function updateProjectSelectedTags() {
    const tagsContainer = document.getElementById('projectSelectedTags');
    if (!tagsContainer) return;

    if (selectedProjectNames.size === 0) {
        tagsContainer.style.display = 'none';
        tagsContainer.innerHTML = '';
        return;
    }

    tagsContainer.style.display = 'flex';
    tagsContainer.innerHTML = '';

    selectedProjectNames.forEach(projectName => {
        const tag = document.createElement('div');
        tag.className = 'project-selected-tag';
        tag.innerHTML = `
            <span>${projectName}</span>
            <span class="remove-project" onclick="removeProject('${projectName}')">&times;</span>
        `;
        tagsContainer.appendChild(tag);
    });
    addFloatingClearFilterButton();
}

// Update project placeholder text
function updateProjectPlaceholder() {
    const placeholder = document.querySelector('.project-placeholder');
    if (!placeholder) return;

    if (selectedProjectNames.size === 0) {
        placeholder.textContent = 'Select project names...';
    } else if (selectedProjectNames.size === 1) {
        placeholder.textContent = `${selectedProjectNames.size} project selected`;
    } else {
        placeholder.textContent = `${selectedProjectNames.size} projects selected`;
    }
}

// Remove project from selection
function removeProject(projectName) {
    selectedProjectNames.delete(projectName);

    // Update checkbox using data attribute
    const option = document.querySelector(`[data-project-name="${projectName}"]`);
    const checkbox = option?.querySelector('input[type="checkbox"]');
    if (checkbox) checkbox.checked = false;

    updateProjectSelectedTags();
    updateProjectPlaceholder();

    // Removing a selected project chip is an explicit filter action,
    // so refresh cards/charts/stats immediately.
    applyAllFilters();
}

// Setup project filter event listeners
function setupProjectFilterListeners() {
    console.log("Setting up project filter listeners...");

    const dropdownInput = document.getElementById("projectDropdownInput");
    const searchInput = document.querySelector(".project-search-input");

    if (dropdownInput) {
        // MOBILE FIX: Intercept touchstart to completely kill the simulated mobile double-click sequence
        dropdownInput.ontouchstart = function (e) {
            e.preventDefault(); // Prevents the browser from simulating a ghost "click" later
            e.stopPropagation();
            toggleProjectDropdown();
        };

        // DESKTOP FIX: Standard click handler
        dropdownInput.onclick = function (e) {
            e.preventDefault();
            e.stopPropagation();
            toggleProjectDropdown();
        };
    }

    if (searchInput) {
        searchInput.oninput = handleProjectSearch;

        searchInput.ontouchstart = function (e) {
            e.stopPropagation(); // Keep dropdown open when user taps the search bar
            window._keyboardFocusLock = true;
            setTimeout(() => { window._keyboardFocusLock = false; }, 800);
        };

        searchInput.onclick = function (e) {
            e.stopPropagation();
        };

        searchInput.addEventListener('focus', function (e) {
            window._keyboardFocusLock = true;
            setTimeout(() => { window._keyboardFocusLock = false; }, 800);
        });
    }

    // Global document listeners for closing the dropdown
    if (!window._projectDropdownListenerAdded) {
        const closeDropdownIfOutside = (e) => {
            if (window._keyboardFocusLock) return; // Prevent closing during keyboard slide-up
            if (!e.target.closest(".project-dropdown-container")) {
                closeProjectDropdown();
            }
        };

        document.addEventListener("click", closeDropdownIfOutside);
        document.addEventListener("touchstart", closeDropdownIfOutside, { passive: true });
        window._projectDropdownListenerAdded = true;
    }
}

// Toggle project dropdown visibility
let lastToggleTime = 0;
function toggleProjectDropdown() {
    let now = Date.now();
    // Increase to 500ms to comfortably capture Android/iOS longest ghost click delays
    if (now - lastToggleTime < 500) return;
    lastToggleTime = now;

    const dropdownOptions = document.getElementById("projectDropdownOptions");
    if (!dropdownOptions) return;

    projectDropdownOpen = !projectDropdownOpen;
    dropdownOptions.style.display = projectDropdownOpen ? "block" : "none";

    // Auto-focus removed entirely to prevent mobile keyboard layout shifting and crashing the dropdown
}

function closeProjectDropdown() {
    let now = Date.now();
    // Do not close if we JUST toggled it (prevents immediate close on mobile bug)
    if (typeof lastToggleTime !== "undefined" && now - lastToggleTime < 300) return;

    const dropdownOptions = document.getElementById("projectDropdownOptions");
    if (dropdownOptions) {
        dropdownOptions.style.display = "none";
        projectDropdownOpen = false;
    }
}

// Handle project search
function handleProjectSearch(event) {
    const searchTerm = event.target.value.toLowerCase();
    const projectOptions = document.querySelectorAll('.project-option');

    projectOptions.forEach(option => {
        const label = option.querySelector('label');
        if (label) {
            const projectName = label.textContent.toLowerCase();
            option.style.display = projectName.includes(searchTerm) ? 'flex' : 'none';
        }
    });
}

// Handle project filter change
function handleProjectFilterChange() {
    console.log('Project filter changed:', Array.from(selectedProjectNames));

    // Do not auto-apply; wait for user to click Apply in the dropdown
}

// Apply project filters on demand
function applyProjectFilters() {
    console.log('Manually applying project filters:', Array.from(selectedProjectNames));
    applyAllFilters();
    closeProjectDropdown();
}

// Apply all filters (users, projects, dates) together
// Wrapper function for Apply button - handles event and calls applyAllFilters
window.applyFiltersFromDropdown = function (event) {
    if (event) {
        event.stopPropagation();
        if (event.preventDefault) {
            event.preventDefault();
        }
    }
    // Call the main filter application function
    applyAllFilters();
};

function applyAllFilters() {
    // Keep floating clear button state in sync for every manual apply action.
    addFloatingClearFilterButton();

    // Clear filtered analytics data so Excel export uses fresh data
    currentFilteredAnalyticsData = null;
    currentFilteredUserTablenames = null;

    // Check if main dropdown has a user selected
    const selectedMainUser = getSelectedUserValue();

    // Use centralized filter helpers to build consistent query params
    const filters = collectDashboardFilters({
        includeSelectedUser: !!selectedMainUser,
        selectedUserOverride: selectedMainUser,
        includeFilteredUsers: selectedUsers.size > 0,
        includeProjects: true,
        includeDates: true
    });

    filters.aggregated_analytics = true;
    filters.include_detailed_data = true;

    if (selectedMainUser) {
        console.log('Including main dropdown user in filter request:', selectedMainUser);
    }

    const selectedProjects = getSelectedProjectNames();
    if (selectedProjects.length > 0) {
        filters.projectfilter = selectedProjects.join(",");
    }
    if (selectedUsers.size > 0) {
        filters.userfilter = Array.from(selectedUsers).join(",");
    }

    const url = buildDashboardUrl('dashboard_data.php', filters);

    // Cancel any slow/stale request before firing a new one
    cancelActiveDashboardRequest();
    const controller = new AbortController();
    activeDashboardFetchController = controller;
    const requestStartedAt = performance.now ? performance.now() : Date.now();

    // Show loading state
    showDashboardPopupLoader('Applying filters...');
    showPopupLoadingState();

    // Fetch filtered data
    fetchData(url, { controller, timeoutMs: 20000 }).then(data => {
        // Ignore if this request was superseded
        if (activeDashboardFetchController !== controller) return;

        if (data.status === "aborted") {
            hideDashboardPopupLoader();
            return;
        }
        if (data.status === "success") {
            // Store the data for filtering
            storeAllUsersData(data);

            // Check current view mode and preserve it
            if (currentViewMode === 'hierarchy' && currentHierarchyUser) {
                // IMPORTANT: Must restore dashboard structure first before showing hierarchy
                Promise.resolve(updatePopupDashboard(data.aggregated_analytics || data))
                    .then(() => {
                        if (selectedUsers && selectedUsers.size > 0) {
                            // Recompute filtered stats using the freshly-fetched user data
                            updateFilteredStats(Array.from(selectedUsers));
                        }
                    })
                    .finally(() => hideDashboardPopupLoader());

                // The updatePopupDashboard will handle preserving hierarchy view automatically
                // via its internal setTimeout that calls showTeamView(currentHierarchyUser)
            } else {
                // Update popup dashboard with filtered data (normal mode)
                Promise.resolve(updatePopupDashboard(data.aggregated_analytics || data))
                    .then(() => {
                        if (selectedUsers && selectedUsers.size > 0) {
                            // Recompute filtered stats using the freshly-fetched user data
                            updateFilteredStats(Array.from(selectedUsers));
                        }
                    })
                    .finally(() => hideDashboardPopupLoader());

                // NOTE: Don't call initializePopupCharts here!
                // It's already called inside updatePopupDashboard
                // Calling it twice causes the charts to reinitialize with wrong/stale data
            }
        } else {
            console.error('Error fetching filtered data:', data.message || 'Unknown error');
            showPopupErrorState('Failed to load filtered data. Please try again.');
            hideDashboardPopupLoader();
        }
    }).catch(error => {
        console.error('Error applying filters:', error);
        showPopupErrorState('Failed to load filtered data. Please try again.');
        hideDashboardPopupLoader();
    }).finally(() => {
        if (activeDashboardFetchController === controller) {
            activeDashboardFetchController = null;
        }
        const end = performance.now ? performance.now() : Date.now();
        console.info('applyAllFilters duration (ms):', Math.round(end - requestStartedAt));
    });
}
// Select all projects
function selectAllProjects() {
    allProjectNames.forEach(projectName => {
        if (projectName && projectName.trim()) {
            selectedProjectNames.add(projectName);
        }
    });

    // Update all checkboxes using data attributes
    allProjectNames.forEach(projectName => {
        const option = document.querySelector(`[data-project-name="${projectName}"]`);
        const checkbox = option?.querySelector('input[type="checkbox"]');
        if (checkbox) checkbox.checked = true;
    });

    updateProjectSelectedTags();
    updateProjectPlaceholder();
    handleProjectFilterChange();
}

// Clear all project selections
function clearAllProjects() {
    selectedProjectNames.clear();

    // Update all checkboxes using data attributes
    const options = document.querySelectorAll('[data-project-name]');
    options.forEach(option => {
        const checkbox = option.querySelector('input[type="checkbox"]');
        if (checkbox) checkbox.checked = false;
    });

    updateProjectSelectedTags();
    updateProjectPlaceholder();
    handleProjectFilterChange();
}

// Make functions globally available
window.handleSearchInput = handleSearchInput;
window.clearSearch = clearSearch;
window.toggleDropdown = toggleDropdown;
window.selectAllUsers = selectAllUsers;
window.clearAllUsers = clearAllUsers;
window.removeUser = removeUser;
window.refreshDropdownBadges = refreshDropdownBadges;
window.startBadgeMonitoring = startBadgeMonitoring;
window.stopBadgeMonitoring = stopBadgeMonitoring;
window.removeProject = removeProject;
window.selectAllProjects = selectAllProjects;
window.clearAllProjects = clearAllProjects;
window.toggleProjectDropdown = toggleProjectDropdown;


function handleMultiSelectChange() {
    const multiSelect = document.getElementById('userMultiSelect');
    if (!multiSelect) return;

    const selectedOptions = Array.from(multiSelect.selectedOptions);
    const selectedValues = selectedOptions.map(option => option.value);

    console.log('Multi-select changed. Selected:', selectedValues);

    // Sync global selections so downstream filters use the same set
    selectedUsers.clear();
    preservedSelectedUsers.clear();
    selectedValues.forEach(v => {
        if (v) {
            selectedUsers.add(v);
            preservedSelectedUsers.add(v);
        }
    });

    // Update selected users display
    updateSelectedUsersDisplay(selectedOptions);

    // Filter user cards based on selection
    filterUserCards(selectedValues);

    // Update stats based on filtered users
    updateFilteredStats(selectedValues);
    applyAllFilters();
}

// Enhanced function to update selected users display
function updateSelectedUsersDisplay(selectedOptions) {
    const selectedUsersContainer = document.querySelector('.selected-users');
    if (!selectedUsersContainer) {
        console.warn('Selected users container not found');
        return;
    }

    selectedUsersContainer.innerHTML = '';

    if (selectedOptions.length === 0) {
        selectedUsersContainer.innerHTML = '<div style="color: #6b7280; font-style: italic; padding: 8px;">No users selected - showing all users</div>';
        return;
    }

    selectedOptions.forEach(option => {
        if (option.value) {
            const userTag = document.createElement('div');
            userTag.className = 'user-tag';
            userTag.innerHTML = `
                <span>${option.textContent}</span>
                <button class="remove-btn" data-value="${option.value}" title="Remove ${option.textContent}">&times;</button>
            `;
            selectedUsersContainer.appendChild(userTag);
        }
    });

    // Add event listeners to remove buttons
    document.querySelectorAll('.selected-users .remove-btn').forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            const value = this.getAttribute('data-value');
            const multiSelect = document.getElementById('userMultiSelect');
            const option = multiSelect.querySelector(`option[value="${value}"]`);

            if (option) {
                option.selected = false;
                handleMultiSelectChange();
            }
        });
    });
}


// Enhanced function to preserve selections during data refresh
function preserveAndRestoreSelections(callback) {
    // Store current selections
    const currentSelections = new Set(selectedUsers);

    // Execute the callback (data loading)
    if (callback && typeof callback === 'function') {
        callback();
    }

    // Restore selections after a short delay to ensure DOM is updated
    setTimeout(() => {
        if (currentSelections.size > 0) {
            console.log('Restoring preserved selections:', Array.from(currentSelections));

            // Clear current selections
            selectedUsers.clear();

            // Restore preserved selections
            currentSelections.forEach(userId => {
                selectedUsers.add(userId);
            });

            // Update UI to reflect restored selections
            updateDropdownSelections();
            updateSelectedTags();
            updateDropdownPlaceholder();

            // Apply the filter to show only selected users
            filterUserCards();
        }
    }, 500); // Increased delay to ensure data is fully loaded
}

// Store all user data when loading the dashboard
function storeAllUsersData(data) {
    if (data && data.aggregated_analytics && data.aggregated_analytics.user_wise_data) {
        console.log('storeAllUsersData: Processing user data with persistent storage');

        // Check if we have filters active (if so, this is filtered data, not the full list)
        // CRITICAL BUGFIX: Must include the dashboard-level user selector, otherwise filtering by 
        // single-user on dashboard will cause the API response to destructively overwrite `originalUsersData` 
        // with just that one user, permanently erasing the rest of the popup's multiselect dropdown!
        const dashboardLevelUser = getSelectedUserValue();
        const hasActiveFilters = (selectedUsers && selectedUsers.size > 0) ||
            (selectedProjectNames && selectedProjectNames.size > 0) ||
            (window.searchQuery && window.searchQuery.length > 0) ||
            (dashboardLevelUser && dashboardLevelUser.trim().length > 0);

        console.log('storeAllUsersData context:', {
            hasActiveFilters,
            dashboardLevelUser,
            selectedUsersCount: selectedUsers?.size || 0,
            selectedProjectsCount: selectedProjectNames?.size || 0,
            incomingUserCount: data.aggregated_analytics.user_wise_data.length,
            originalUserCount: originalUsersData?.length || 0
        });

        // Merge existing user types from multiple sources
        const existingUserTypes = {};

        // First, use persistent storage
        if (Object.keys(persistentUserTypes).length > 0) {
            Object.assign(existingUserTypes, persistentUserTypes);
            console.log('Using persistent user types from storage:', persistentUserTypes);
        }

        // Then overlay original user data if available
        if (originalUsersData && originalUsersData.length > 0) {
            originalUsersData.forEach(user => {
                if (user.user_type && user.user_type !== 'user') {
                    existingUserTypes[user.tablename] = user.user_type;
                }
            });
            console.log('Overlay user types from originalUsersData');
        }

        // Merge the new data with preserved user types
        const processedUsers = data.aggregated_analytics.user_wise_data.map(newUser => {
            const preservedType = existingUserTypes[newUser.tablename];

            // Prefer API data for user/team_lead roles, use preserved data only if API is empty
            let finalType;
            if (newUser.user_type && newUser.user_type !== 'undefined' && newUser.user_type !== 'null') {
                // Trust API data if it exists and is valid
                finalType = newUser.user_type;
            } else {
                // Use preserved data only as fallback
                finalType = preservedType || 'user';
            }

            console.warn(`STORE CORRECTED - User ${newUser.name || newUser.username}: API="${newUser.user_type}", preserved="${preservedType}", final="${finalType}"`);

            // Store final type persistently
            if (newUser.tablename && finalType) {
                persistentUserTypes[newUser.tablename] = finalType;
            }

            return {
                ...newUser,
                user_type: finalType
            };
        });

        // Deduplicate users by tablename to avoid duplicate cards when API returns repeats
        const uniqueUsersMap = new Map();
        processedUsers.forEach(user => {
            if (user && user.tablename && !uniqueUsersMap.has(user.tablename)) {
                uniqueUsersMap.set(user.tablename, user);
            }
        });
        const uniqueUsers = Array.from(uniqueUsersMap.values());
        if (uniqueUsers.length !== processedUsers.length) {
            console.warn('storeAllUsersData: Removed duplicate user records', {
                before: processedUsers.length,
                after: uniqueUsers.length
            });
        }

        // Always update allUsersData (this can be filtered data for cards/charts)
        allUsersData = uniqueUsers;

        // Only update originalUsersData if we DON'T have active filters
        // This preserves the full user list for the dropdown
        if (!hasActiveFilters) {
            originalUsersData = [...uniqueUsers];
        }

        // Refresh dropdown after storing user data
        if (typeof populateDropdownOptions === 'function') {
            setTimeout(() => {
                populateDropdownOptions();
            }, 100);
        }
    }
}


// New function to update stats display

function updatePopupDashboard(aggregatedData) {
    return new Promise((resolve) => {
        const dashboardContent = document.querySelector('#dashboardPopup .dashboard-content2');
        const finalizePopupRender = () => {
            resolve();
        };

        if (!dashboardContent) {
            finalizePopupRender();
            return;
        }

        // Reset stats priority guard so new renders can update numbers after showing placeholders
        try {
            if (typeof window !== 'undefined') {
                window.__lastStatsUpdate = { priority: 0, timestamp: Date.now() };
            }
        } catch (e) {
            console.warn('Failed to reset stats priority guard', e);
        }

        // Update the popup main heading to show Total Users count
        const headerH1 = document.querySelector('#dashboardPopup .popup-header h1');
        if (headerH1) {
            const totals = extractAggregatedTotals(aggregatedData);
            headerH1.textContent = `Total Users: ${totals.totalUsers}`;
        }

        dashboardContent.innerHTML = `
            <!-- Overall Stats -->
            <div class="stats-overview" style="display: flex; overflow: auto; justify-content: space-evenly; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 5px;">
                <div class="stat-card" style="     margin: 4px 0;   background: transparent !important; padding: 20px; border-radius: 12px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.1);      min-width: 243px;">
                    <h3 class="stat-card-header">Total Leads</h3>
                    <div class="number stat-card-number " style="font-size: 28px; font-weight: bold;">Loading...</div>
                </div>
                <div class="stat-card" style="       margin: 4px 0; background: transparent !important; padding: 20px; border-radius: 12px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.1);    min-width: 243px;">
                    <h3 class="stat-card-header" >Total Bookings</h3>
                    <div class="number stat-card-number " style="font-size: 28px; font-weight: bold;">Loading...</div>
                </div>
                <div class="stat-card" style="      margin: 4px 0;  background: transparent !important; padding: 20px; border-radius: 12px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.1);    min-width: 243px;">
                    <h3 class="stat-card-header" >Total EOI</h3>
                    <div class="number stat-card-number " style="font-size: 28px; font-weight: bold;">Loading...</div>
                </div>
                <div class="stat-card" style="      margin: 4px 0;  background: transparent !important; padding: 20px; border-radius: 12px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.1); min-width: 243px;">
                    <h3 class="stat-card-header" >Cancelled Bookings</h3>
                    <div class="number stat-card-number" style="font-size: 28px; font-weight: bold;">Loading...</div>
                </div>
            </div>

            <!-- Main Charts -->
            <div class="charts-section">
                <div class="chart-container chart-overall" style="background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); padding: 20px;">
                    <h3 style="margin-bottom: 20px; text-align: center; color: #374151;">Overall Lead Status Distribution</h3>
                    <div class="chart-wrapper" id="overallLeadStatusChart-wrapper">
                        <canvas id="overallLeadStatusChart"></canvas>
                    </div>
                </div>
                
                <div class="chart-container chart-overall" style="background: white; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); padding: 20px;">
                    <h3 style="margin-bottom: 20px; text-align: center; color: #374151;">Lead Sources Distribution</h3>
                    <div class="chart-wrapper"  id="leadSourceChart-wrapper">
                        <canvas id="leadSourceChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Individual Users -->
            <div class="users-section">
                <h2 class="users-section-header" style="margin-bottom: 25px; text-align: center; font-size: 24px;">User Performance</h2>
                <div class="users-grid" id="usersGrid" style="grid-template-columns: repeat(auto-fill, minmax(270px, 1fr)); gap: 20px; display: grid;">
                    <!-- User cards will be generated by JavaScript -->
                </div>
            </div>
        `;

        const chartDataPromise = aggregateChartDataFromUsers(aggregatedData);

        chartDataPromise.then(chartData => {
            const fallbackTotals = extractAggregatedTotals(aggregatedData);
            const aggregatedTotals = chartData?.aggregated_totals || {};
            const coerceTotal = (value, fallback = 0) => {
                const numeric = Number(value);
                return Number.isFinite(numeric) ? numeric : fallback;
            };

            // If no filters are active, trust backend aggregated totals (includes hierarchy). Otherwise use chart totals with fallback.
            let totals;
            const hasUserFilter = selectedUsers && selectedUsers.size > 0;
            const hasProjectFilter = selectedProjectNames && selectedProjectNames.size > 0;
            const primarySelection = getSelectedUserValue();
            const isFiltered = hasUserFilter || hasProjectFilter || !!primarySelection;

            if (!isFiltered) {
                totals = {
                    totalUsers: fallbackTotals.totalUsers,
                    totalLeads: fallbackTotals.totalLeads,
                    totalBookings: fallbackTotals.totalBookings,
                    totalEOI: fallbackTotals.totalEOI,
                    totalCancelledBookings: fallbackTotals.totalCancelledBookings
                };
            } else {
                totals = {
                    totalUsers: coerceTotal(aggregatedTotals.totalUsers, fallbackTotals.totalUsers),
                    totalLeads: coerceTotal(aggregatedTotals.totalLeads, fallbackTotals.totalLeads),
                    totalBookings: coerceTotal(aggregatedTotals.totalBookings, fallbackTotals.totalBookings),
                    totalEOI: coerceTotal(aggregatedTotals.totalEOI, fallbackTotals.totalEOI),
                    totalCancelledBookings: coerceTotal(aggregatedTotals.totalCancelledBookings, fallbackTotals.totalCancelledBookings)
                };
            }

            // hasUserFilter, hasProjectFilter, primarySelection, isFiltered computed above

            if (headerH1) {
                headerH1.textContent = `Total Users: ${totals.totalUsers || 0}`;
            }

            console.log('updatePopupDashboard stats update (aggregated):', {
                totals,
                isFiltered,
                hasUserFilter,
                hasProjectFilter,
                selectedUsersCount: selectedUsers?.size || 0,
                selectedProjectsCount: selectedProjectNames?.size || 0,
                primarySelection
            });

            updateStatsDisplay(totals, isFiltered, { priority: 2, source: 'aggregated_analytics_hierarchy' });
        }).catch(error => {
            console.error('Failed to aggregate chart data for stats:', error);

            const fallbackTotals = extractAggregatedTotals(aggregatedData);
            const hasUserFilter = selectedUsers && selectedUsers.size > 0;
            const hasProjectFilter = selectedProjectNames && selectedProjectNames.size > 0;
            const primarySelection = getSelectedUserValue();
            const isFiltered = hasUserFilter || hasProjectFilter || !!primarySelection;

            if (headerH1) {
                headerH1.textContent = `Total Users: ${fallbackTotals.totalUsers || 0}`;
            }

            updateStatsDisplay(fallbackTotals, isFiltered, { priority: 2, source: 'aggregated_analytics_fallback' });
        });

        // Initialize everything in the correct order
        setTimeout(() => {
            const safeChartInit = () => chartDataPromise
                .then(chartData => {
                    const hasChartPayload = Array.isArray(chartData?.detailed_status_counts) || Array.isArray(chartData?.detailed_source_counts);
                    const safeChartData = hasChartPayload ? chartData : {
                        detailed_status_counts: aggregatedData?.detailed_status_counts || [],
                        detailed_source_counts: aggregatedData?.detailed_source_counts || []
                    };
                    return initializePopupCharts(safeChartData);
                })
                .catch(error => {
                    console.error('Falling back to server chart data due to aggregation error:', error);
                    return initializePopupCharts({
                        detailed_status_counts: aggregatedData?.detailed_status_counts || [],
                        detailed_source_counts: aggregatedData?.detailed_source_counts || []
                    });
                });

            const renderView = async () => {
                const isSingleUserSelected = selectedUsers && selectedUsers.size === 1;
                const isMultiUserSelected = selectedUsers && selectedUsers.size > 1;

                if (isMultiUserSelected) {
                    currentViewMode = 'normal';
                    currentHierarchyUser = null;
                    console.log(' Multiple users selected - forcing normal view');
                    await generateUserCards();
                    return;
                }

                if (isSingleUserSelected) {
                    const selectedUserId = Array.from(selectedUsers)[0];
                    const selectedUser = allUsersData.find(u => u.tablename === selectedUserId);

                    if (selectedUser) {
                        console.log(`Single user selected: ${selectedUser.name || selectedUser.username} - showing hierarchy view with subordinates`);
                        await showTeamView(selectedUser);
                        return;
                    }
                }

                if (currentViewMode === 'hierarchy' && currentHierarchyUser) {
                    console.log(` Preserving HIERARCHY view for user: ${currentHierarchyUser.name}`);
                    console.log(` Dashboard structure restored, now showing hierarchy for: ${currentHierarchyUser.name}`);
                    await showTeamView(currentHierarchyUser);
                } else {
                    console.log(` Showing NORMAL view`);
                    await generateUserCards();
                }
            };

            const renderPromise = renderView();

            Promise.all([safeChartInit(), renderPromise])
                .finally(() => {
                    initializeMultiSelect();
                    finalizePopupRender();
                });
        }, 100);
    });
}


// Make functions available globally
window.selectAllUsers = selectAllUsers;
window.clearAllUsers = clearAllUsers;

// Helper function to aggregate chart data from all user_wise_data
async function aggregateChartDataFromUsers(aggregatedData) {
    console.log('00--regating chart data from user_wise_data');

    const toNumber = (value, fallback = 0) => {
        const numeric = Number(value);
        return Number.isFinite(numeric) ? numeric : fallback;
    };

    const sanitizeCountArray = (items) => Array.isArray(items)
        ? items.filter(item => item && typeof item === 'object')
        : [];

    const gatherStatusCounts = (perfData, user) => {
        const candidates = [
            perfData?.detailed_status_counts,
            perfData?.analytics?.detailed_status_counts,
            user?.detailed_status_counts,
            user?.leadStatus,
            user?.analytics?.detailed_status_counts
        ];
        for (const candidate of candidates) {
            const sanitized = sanitizeCountArray(candidate);
            if (sanitized.length) {
                return sanitized;
            }
        }
        return [];
    };

    const gatherSourceCounts = (perfData, user) => {
        const candidates = [
            perfData?.detailed_source_counts,
            perfData?.analytics?.detailed_source_counts,
            user?.detailed_source_counts,
            user?.leadSources,
            user?.analytics?.detailed_source_counts
        ];
        for (const candidate of candidates) {
            const sanitized = sanitizeCountArray(candidate);
            if (sanitized.length) {
                return sanitized;
            }
        }
        return [];
    };

    const baseUserWiseData = Array.isArray(aggregatedData?.user_wise_data)
        ? [...aggregatedData.user_wise_data]
        : [];

    const hasMultiSelectFilter = selectedUsers instanceof Set && selectedUsers.size > 0;
    const selectedMultiUsers = hasMultiSelectFilter ? Array.from(selectedUsers) : [];
    const selectedMainUser = getSelectedUserValue();

    // If no specific selection, trust backend aggregated totals (includes full hierarchy) to avoid under-counts
    if (!hasMultiSelectFilter && !selectedMainUser) {
        return {
            detailed_status_counts: sanitizeCountArray(aggregatedData?.detailed_status_counts),
            detailed_source_counts: sanitizeCountArray(aggregatedData?.detailed_source_counts),
            aggregated_totals: extractAggregatedTotals(aggregatedData)
        };
    }

    const findUserRecord = (tableName) => {
        if (!tableName) return null;
        return baseUserWiseData.find(u => u.tablename === tableName)
            || (Array.isArray(aggregatedData?.user_wise_data)
                ? aggregatedData.user_wise_data.find(u => u.tablename === tableName)
                : null)
            || (Array.isArray(allUsersData)
                ? allUsersData.find(u => u.tablename === tableName)
                : null);
    };

    async function buildHierarchyDataset(baseUser) {
        if (!baseUser || !baseUser.tablename) {
            return baseUser ? [baseUser] : [];
        }

        const baseClone = { ...baseUser };

        try {
            const hierarchy = await buildCompleteHierarchy(baseClone);
            const subordinates = Array.isArray(hierarchy?.downward) ? hierarchy.downward.filter(Boolean) : [];
            const uniqueUsers = new Map();
            const allUsersToFetch = [baseClone, ...subordinates];

            console.log(` Aggregating hierarchy for ${baseClone.tablename} with ${subordinates.length} subordinates`);

            const performanceDataMap = await fetchBatchUserPerformanceData(allUsersToFetch);

            allUsersToFetch.forEach(user => {
                const perfData = performanceDataMap.get(user.tablename);

                const detailedStatusFromPerf = gatherStatusCounts(perfData, user);
                const detailedSourceFromPerf = gatherSourceCounts(perfData, user);

                const enriched = {
                    ...user,
                    leads: toNumber(perfData?.myLeads ?? user.leads ?? user.total_leads ?? user.myLeads),
                    bookings: toNumber(perfData?.total_bookings ?? user.bookings ?? user.total_bookings),
                    eoi: toNumber(perfData?.total_eoi ?? user.eoi ?? user.total_eoi),
                    cancelled_bookings: toNumber(
                        perfData?.cancelled_bookings
                        ?? user.cancelled_bookings
                        ?? user.total_cancelled_bookings
                        ?? user.total_canceled_bookings
                    ),
                    detailed_status_counts: detailedStatusFromPerf,
                    detailed_source_counts: detailedSourceFromPerf
                };

                const key = enriched.tablename || enriched.user_unique_id || enriched.userId || enriched.id;
                if (key) {
                    uniqueUsers.set(key, enriched);
                }
            });

            if (uniqueUsers.size > 0) {
                return Array.from(uniqueUsers.values());
            }
        } catch (error) {
            console.error(' Error building hierarchy dataset:', error);
        }

        return [baseClone];
    }

    let workingData = baseUserWiseData;

    if (hasMultiSelectFilter) {
        console.log(' Multi-select filter active, filtering to selected users:', selectedMultiUsers);
        workingData = workingData.filter(u => selectedUsers.has(u.tablename));
        console.log(` Filtered to ${workingData.length} selected users`);

        if (selectedMultiUsers.length === 1) {
            const baseUser = findUserRecord(selectedMultiUsers[0]);
            if (baseUser) {
                workingData = await buildHierarchyDataset(baseUser);
            }
        }
    } else if (selectedMainUser) {
        const baseUser = findUserRecord(selectedMainUser);
        if (baseUser) {
            workingData = await buildHierarchyDataset(baseUser);
        }
    }

    if (!Array.isArray(workingData) || workingData.length === 0) {
        console.log(' No user_wise_data available for chart aggregation');
        return {
            detailed_status_counts: sanitizeCountArray(aggregatedData?.detailed_status_counts),
            detailed_source_counts: sanitizeCountArray(aggregatedData?.detailed_source_counts),
            aggregated_totals: extractAggregatedTotals(aggregatedData)
        };
    }

    const statusCountsMap = {};
    const sourceCountsMap = {};
    const uniqueUserIds = new Set();
    const totalsAccumulator = {
        totalLeads: 0,
        totalBookings: 0,
        totalEOI: 0,
        totalCancelledBookings: 0
    };

    workingData.forEach(user => {
        const displayName = user.name || user.username || user.tablename;
        console.log(`  Aggregating data for: ${displayName}`);

        const userId = user.tablename || user.user_unique_id || user.userId || user.id;
        if (userId) {
            uniqueUserIds.add(userId);
        }

        totalsAccumulator.totalLeads += toNumber(user.leads ?? user.total_leads ?? user.myLeads);
        totalsAccumulator.totalBookings += toNumber(user.bookings ?? user.total_bookings);
        totalsAccumulator.totalEOI += toNumber(user.eoi ?? user.total_eoi);
        totalsAccumulator.totalCancelledBookings += toNumber(
            user.cancelled_bookings
            ?? user.cancelledBookings
            ?? user.total_cancelled_bookings
            ?? user.total_canceled_bookings
        );

        const userStatusCounts = gatherStatusCounts(null, user);
        userStatusCounts.forEach(item => {
            const status = item.status || 'Unknown';
            const count = toNumber(item.count, 0);
            statusCountsMap[status] = (statusCountsMap[status] || 0) + count;
        });

        const userSourceCounts = gatherSourceCounts(null, user);
        userSourceCounts.forEach(item => {
            const source = item.source_of_lead || 'Unknown';
            const count = toNumber(item.count, 0);
            sourceCountsMap[source] = (sourceCountsMap[source] || 0) + count;
        });
    });

    const aggregatedStatusCounts = Object.entries(statusCountsMap)
        .map(([status, count]) => ({ status, count }))
        .filter(item => item.count > 0);

    const aggregatedSourceCounts = Object.entries(sourceCountsMap)
        .map(([source_of_lead, count]) => ({ source_of_lead, count }))
        .filter(item => item.count > 0);

    const aggregatedTotals = {
        totalUsers: uniqueUserIds.size || workingData.length,
        totalLeads: Math.round(totalsAccumulator.totalLeads),
        totalBookings: Math.round(totalsAccumulator.totalBookings),
        totalEOI: Math.round(totalsAccumulator.totalEOI),
        totalCancelledBookings: Math.round(totalsAccumulator.totalCancelledBookings)
    };

    console.log(' Aggregated status counts (including subordinates):', aggregatedStatusCounts);
    console.log(' Aggregated source counts (including subordinates):', aggregatedSourceCounts);
    console.log(' Aggregated totals (including subordinates):', aggregatedTotals);

    return {
        detailed_status_counts: aggregatedStatusCounts,
        detailed_source_counts: aggregatedSourceCounts,
        aggregated_totals: aggregatedTotals
    };
}

async function initializePopupCharts(aggregatedData) {
    console.log(' initializePopupCharts called');
    console.log(' aggregatedData:', aggregatedData);

    // Aggregate chart data from user_wise_data to ensure sync with stats (including subordinates)
    const chartData = await aggregateChartDataFromUsers(aggregatedData);
    console.log(' Using aggregated chart data from users:', chartData);

    const colors = getChartColors();

    // Show loaders for charts
    showChartLoader('overallLeadStatusChart');
    showChartLoader('leadSourceChart');

    // Overall Lead Status Chart - Smaller size for desktop
    const overallStatusCtx = document.getElementById('overallLeadStatusChart');
    const overallWrapper = document.getElementById('overallLeadStatusChart-wrapper');

    // Remove any previous fallback message
    if (overallWrapper) {
        const prevFallback = overallWrapper.querySelector('.chart-fallback');
        if (prevFallback) prevFallback.remove();
    }

    // Check if we have meaningful data (array with length > 0 and at least one count > 0)
    const hasStatusData = Array.isArray(chartData?.detailed_status_counts)
        && chartData.detailed_status_counts.length > 0
        && chartData.detailed_status_counts.some(item => parseInt(item.count) > 0);

    console.log(' hasStatusData:', hasStatusData, 'Count:', chartData?.detailed_status_counts?.length);

    if (overallStatusCtx && hasStatusData) {
        const statusCounts = chartData.detailed_status_counts;

        setTimeout(() => {
            // Destroy existing chart if it exists
            if (overallLeadStatusChart) {
                overallLeadStatusChart.destroy();
            }

            overallLeadStatusChart = new Chart(overallStatusCtx, {

                type: 'doughnut',
                data: {
                    labels: statusCounts.map(item => item.status),
                    datasets: [{
                        data: statusCounts.map(item => parseInt(item.count)),
                        backgroundColor: statusCounts.map(item => getStatusColor(item.status)),
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 6, // More compact
                                usePointStyle: true,
                                color: colors.textColor,
                                fontColor: colors.textColor, // Chart.js v2 fallback for dark mode
                                font: {
                                    size: window.innerWidth <= 768 ? 9 : 10 // Smaller font
                                },
                                boxWidth: window.innerWidth <= 768 ? 8 : 10, // Smaller boxes
                                generateLabels: function (chart) {
                                    const data = chart.data;
                                    const currentColors = getChartColors();
                                    if (data.labels.length && data.datasets.length) {
                                        return data.labels.map((label, i) => {
                                            const value = data.datasets[0].data[i];
                                            let displayLabel = label;
                                            if (window.innerWidth > 768 && displayLabel.length > 12) {
                                                displayLabel = label.substring(0, 10) + '...';
                                            }
                                            return {
                                                text: `${displayLabel} (${value})`,
                                                fillStyle: data.datasets[0].backgroundColor[i],
                                                strokeStyle: data.datasets[0].borderColor || '#fff',
                                                fontColor: currentColors.textColor,
                                                lineWidth: 1,
                                                hidden: false,
                                                index: i
                                            };
                                        });
                                    }
                                    return [];
                                }
                            }
                        },
                        tooltip: {
                            backgroundColor: colors.tooltipBg,
                            titleColor: colors.textColor,
                            bodyColor: colors.textColor,
                            bodyFontColor: colors.textColor, // v2 fallback
                            titleFontColor: colors.textColor, // v2 fallback
                            borderColor: colors.tooltipBorder,
                            borderWidth: 1,
                            titleFont: {
                                size: window.innerWidth <= 768 ? 10 : 11
                            },
                            bodyFont: {
                                size: window.innerWidth <= 768 ? 9 : 10
                            }
                        }
                    },
                    cutout: '65%', // Increased cutout for smaller appearance
                    layout: {
                        padding: {
                            top: 3,
                            bottom: 3,
                            left: 3,
                            right: 3
                        }
                    }
                }
            });

            hideChartLoader('overallLeadStatusChart');
        }, 150);
    } else {
        // No data or all counts are zero: show fallback message
        if (overallLeadStatusChart) {
            try {
                overallLeadStatusChart.destroy();
            } catch (e) {
                console.log('Chart destroy error (ignored):', e);
            }
            overallLeadStatusChart = null;
        }

        hideChartLoader('overallLeadStatusChart');

        if (overallWrapper) {
            overallWrapper.innerHTML = '<div class="chart-fallback" style="display:flex;align-items:center;justify-content:center;min-height:200px;color:#9ca3af;font-size:16px;padding:40px;">No leads available</div>';
        }
    }

    // Lead Sources Chart - Smaller size for desktop
    const leadSourceCtx = document.getElementById('leadSourceChart');
    const leadSourceWrapper = document.getElementById('leadSourceChart-wrapper');

    // Remove any previous fallback message
    if (leadSourceWrapper) {
        const prevFallback = leadSourceWrapper.querySelector('.chart-fallback');
        if (prevFallback) prevFallback.remove();
    }

    // Check if we have meaningful data (array with length > 0 and at least one count > 0)
    const hasSourceData = Array.isArray(chartData?.detailed_source_counts)
        && chartData.detailed_source_counts.length > 0
        && chartData.detailed_source_counts.some(item => parseInt(item.count) > 0);

    console.log(' hasSourceData:', hasSourceData, 'Count:', chartData?.detailed_source_counts?.length);

    if (leadSourceCtx && hasSourceData) {
        const sourceCounts = chartData.detailed_source_counts;

        setTimeout(() => {
            // Destroy existing chart if it exists
            if (leadSourceChart) {
                leadSourceChart.destroy();
            }

            leadSourceChart = new Chart(leadSourceCtx, {
                type: 'doughnut',
                data: {
                    labels: sourceCounts.map(item => item.source_of_lead),
                    datasets: [{
                        data: sourceCounts.map(item => parseInt(item.count)),
                        backgroundColor: getPaletteForLength(colors.palette, sourceCounts.length),
                        borderWidth: 2,
                        borderColor: colors.borderColor
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                padding: 8, // Reduced padding
                                usePointStyle: true,
                                color: colors.textColor,
                                fontColor: colors.textColor, // Chart.js v2 fallback
                                font: {
                                    size: window.innerWidth <= 768 ? 10 : 11 // Smaller font
                                },
                                boxWidth: window.innerWidth <= 768 ? 10 : 12, // Smaller boxes
                                generateLabels: function (chart) {
                                    const data = chart.data;
                                    const currentColors = getChartColors();
                                    if (data.labels.length && data.datasets.length) {
                                        return data.labels.map((label, i) => {
                                            const value = data.datasets[0].data[i];
                                            // Compact label display
                                            let displayLabel = label;
                                            if (window.innerWidth > 768 && displayLabel.length > 20) {
                                                displayLabel = label.substring(0, 17) + '...';
                                            } else if (displayLabel.length > 25) {
                                                displayLabel = label.substring(0, 22) + '...';
                                            }
                                            return {
                                                text: `${displayLabel} (${value})`,
                                                fillStyle: data.datasets[0].backgroundColor[i],
                                                strokeStyle: data.datasets[0].borderColor || '#fff',
                                                fontColor: currentColors.textColor,
                                                lineWidth: 1,
                                                hidden: false,
                                                index: i
                                            };
                                        });
                                    }
                                    return [];
                                }
                            }
                        },
                        tooltip: {
                            backgroundColor: colors.tooltipBg,
                            titleColor: colors.textColor,
                            bodyColor: colors.textColor,
                            bodyFontColor: colors.textColor, // v2 fallback
                            titleFontColor: colors.textColor, // v2 fallback
                            borderColor: colors.tooltipBorder,
                            borderWidth: 1,
                            callbacks: {
                                label: function (context) {
                                    return `${context.label}: ${context.parsed}`;
                                }
                            }
                        }
                    },
                    // Smaller cutout for desktop
                    cutout: window.innerWidth <= 768 ? '50%' : '55%',
                    // Compact layout
                    layout: {
                        padding: {
                            left: 8,
                            right: 8,
                            top: 8,
                            bottom: 8
                        }
                    }
                }
            });

            hideChartLoader('leadSourceChart');
        }, 250);
    } else {
        // No data or all counts are zero: show fallback message
        if (leadSourceChart) {
            try {
                leadSourceChart.destroy();
            } catch (e) {
                console.log('Chart destroy error (ignored):', e);
            }
            leadSourceChart = null;
        }

        hideChartLoader('leadSourceChart');

        if (leadSourceWrapper) {
            leadSourceWrapper.innerHTML = '<div class="chart-fallback" style="display:flex;align-items:center;justify-content:center;min-height:200px;color:#9ca3af;font-size:16px;padding:40px;">No lead sources available</div>';
        }
    }
}


// Update the existing closeDashboard function to clean up popup-specific resources
function closeDashboard() {
    console.log('Closing dashboard and clearing filters...');

    // Clear all filter selections
    clearFilterSelections();

    // Reset date column filter to default 'created_at' when closing popup
    dateFilterColumn = 'created_at';
    try {
        window.localStorage.setItem('dashboardDateColumn', 'created_at');
    } catch (error) { }
    if (typeof updateDateToggleUI === 'function') updateDateToggleUI();

    // Re-apply default (no filter) state so next open starts clean
    refreshDashboardForDatePreference(false);

    // Close the modal
    document.getElementById('dashboardPopup').classList.remove('active');

    // Clear popup-specific timeouts
    if (popupDateRangeTimeout) {
        clearTimeout(popupDateRangeTimeout);
        popupDateRangeTimeout = null;
    }

    // Remove floating clear filter button (redundant but safe)
    const floatingButton = document.querySelector('.floating-clear-filter');
    if (floatingButton) {
        floatingButton.remove();
    }

    // Reset popup filters to default state
    const popupDateRangeFilter = document.getElementById('popupDateRangeFilter');
    const popupMonthYearSelectors = document.getElementById('popupMonthYearSelectors');
    const popupMonthSelect = document.getElementById('popupMonthSelect');
    const popupYearSelect = document.getElementById('popupYearSelect');
    const popupMonthSelected = document.getElementById('popupMonthSelected');
    const popupYearSelected = document.getElementById('popupYearSelected');
    const popupMonthOptions = document.getElementById('popupMonthOptionsDiv');
    const popupYearOptions = document.getElementById('popupYearOptionsDiv');
    const mainDateRangeFilter = document.getElementById('dateRangeFilter');
    const mainMonthYearSelectors = document.getElementById('monthYearSelectors');
    const mainMonthSelect = document.getElementById('monthSelect');
    const mainYearSelect = document.getElementById('yearSelect');
    const mainMonthSelected = document.getElementById('monthSelected');
    const mainYearSelected = document.getElementById('yearSelected');
    const mainMonthOptions = document.getElementById('monthOptions');
    const mainYearOptions = document.getElementById('yearOptions');

    if (popupDateRangeFilter) popupDateRangeFilter.style.display = 'none';
    if (popupMonthYearSelectors) popupMonthYearSelectors.style.display = 'flex';

    // Reset main selectors to month/year mode
    if (mainDateRangeFilter) mainDateRangeFilter.style.display = 'none';
    if (mainMonthYearSelectors) mainMonthYearSelectors.style.display = 'flex';

    // Reset to current month/year
    const now = new Date();
    if (popupMonthSelect) popupMonthSelect.value = now.getMonth() + 1;
    if (popupYearSelect) popupYearSelect.value = now.getFullYear();
    if (mainMonthSelect) mainMonthSelect.value = now.getMonth() + 1;
    if (mainYearSelect) mainYearSelect.value = now.getFullYear();

    // Reset visible dropdown labels and selected state
    const currentMonthName = getMonthName(now.getMonth() + 1);
    if (popupMonthSelected) {
        popupMonthSelected.innerHTML = `${currentMonthName} <i class="fas fa-chevron-down"></i>`;
    }
    if (popupYearSelected) {
        popupYearSelected.innerHTML = `${now.getFullYear()} <i class="fas fa-chevron-down"></i>`;
    }
    if (popupMonthOptions) {
        popupMonthOptions.querySelectorAll('.custom-dropdown-option').forEach(opt => {
            opt.classList.toggle('selected', opt.getAttribute('data-value') === String(now.getMonth() + 1));
        });
    }
    if (popupYearOptions) {
        popupYearOptions.querySelectorAll('.custom-dropdown-option').forEach(opt => {
            opt.classList.toggle('selected', opt.getAttribute('data-value') === String(now.getFullYear()));
        });
    }

    // Reset main visible dropdown labels and selected state
    if (mainMonthSelected) {
        mainMonthSelected.innerHTML = `${currentMonthName} <i class="fas fa-chevron-down"></i>`;
    }
    if (mainYearSelected) {
        mainYearSelected.innerHTML = `${now.getFullYear()} <i class="fas fa-chevron-down"></i>`;
    }
    if (mainMonthOptions) {
        mainMonthOptions.querySelectorAll('.custom-dropdown-option').forEach(opt => {
            opt.classList.toggle('selected', opt.getAttribute('data-value') === String(now.getMonth() + 1));
        });
    }
    if (mainYearOptions) {
        mainYearOptions.querySelectorAll('.custom-dropdown-option').forEach(opt => {
            opt.classList.toggle('selected', opt.getAttribute('data-value') === String(now.getFullYear()));
        });
    }

    // Destroy charts to prevent memory leaks
    if (overallLeadStatusChart) {
        overallLeadStatusChart.destroy();
        overallLeadStatusChart = null;
    }
    if (leadSourceChart) {
        leadSourceChart.destroy();
        leadSourceChart = null;
    }

    // Destroy individual user charts
    if (userData && userData.length) {
        userData.forEach((_, index) => {
            const canvas = document.getElementById(`userChart${index}`);
            if (canvas && canvas.chart) {
                canvas.chart.destroy();
            }
        });
    }

    // Stop badge monitoring
    stopBadgeMonitoring();

    console.log('Dashboard closed and filters cleared successfully');
}



// Add these functions to your existing dashboard JavaScript

// Create chart loader HTML
function createChartLoader() {
    return `
        <div class="chart-loader" style="
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            background: var(--bg-color, #fff);
            z-index: 10;
            border-radius: 8px;
        ">
            <div class="loader-spinner" style="
                width: 40px;
                height: 40px;
                border: 3px solid #f3f3f3;
                border-top: 3px solid #3498db;
                border-radius: 50%;
                animation: spin 1s linear infinite;
                margin-bottom: 12px;
            "></div>
            <div style="
                color: #666;
                font-size: 14px;
                text-align: center;
            ">Loading Chart...</div>
        </div>
        <style>
            @keyframes spin {

        // Ensure loader overlay never hangs: reset state and start a fallback timer immediately
        resetLeadsOverlayState();
        startLeadsOverlayFallback();

        // Keep the overlay visible until the iframe signals readiness
        const overlay = document.getElementById('leads-loading-overlay');
        if (overlay) {
            overlay.style.display = 'flex';
            overlay.style.opacity = '1';
        }
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        </style>
    `;
}

// Show loader for chart container
function showChartLoader(containerId) {
    const container = document.getElementById(containerId);
    if (container) {
        const parentContainer = container.closest('.chart-container') || container.parentElement;
        if (parentContainer) {
            parentContainer.style.position = 'relative';
            const existingLoader = parentContainer.querySelector('.chart-loader');
            if (!existingLoader) {
                parentContainer.insertAdjacentHTML('beforeend', createChartLoader());
            }
        }
    }
}

// Hide loader for chart container
function hideChartLoader(containerId) {
    const container = document.getElementById(containerId);
    if (container) {
        const parentContainer = container.closest('.chart-container') || container.parentElement;
        if (parentContainer) {
            const loader = parentContainer.querySelector('.chart-loader');
            if (loader) {
                loader.remove();
            }
        }
    }
}

// function closeDashboard() {
//     document.getElementById('dashboardPopup').classList.remove('active');

//     // Destroy charts to prevent memory leaks
//     if (overallLeadStatusChart) {
//         overallLeadStatusChart.destroy();
//         overallLeadStatusChart = null;
//     }
//     if (leadSourceChart) {
//         leadSourceChart.destroy();
//         leadSourceChart = null;
//     }

//     // Destroy individual user charts
//     userData.forEach((_, index) => {
//         const canvas = document.getElementById(`userChart${index}`);
//         if (canvas && canvas.chart) {
//             canvas.chart.destroy();
//         }
//     });
// }

function isDarkTheme() {
    try {
        // Check explicit theme attributes first (most reliable)
        if (document.documentElement.getAttribute('data-theme') === 'dark') return true;
        if (document.documentElement.getAttribute('data-theme') === 'light') return false;

        // Check CSS classes
        if (document.documentElement.classList.contains('dark-mode')) return true;
        if (document.documentElement.classList.contains('dark')) return true;
        if (document.documentElement.classList.contains('light-mode')) return false;
        if (document.documentElement.classList.contains('light')) return false;

        if (document.body.classList.contains('dark-mode')) return true;
        if (document.body.classList.contains('dark')) return true;
        if (document.body.classList.contains('light-mode')) return false;
        if (document.body.classList.contains('light')) return false;

        // Check global state
        if (window.state && window.state.darkMode === true) return true;
        if (window.state && window.state.darkMode === false) return false;

        // Check localStorage
        const storedTheme = localStorage.getItem('darkMode');
        if (storedTheme === 'true') return true;
        if (storedTheme === 'false') return false;

        // Check for theme stored differently
        const themePreference = localStorage.getItem('theme');
        if (themePreference === 'dark') return true;
        if (themePreference === 'light') return false;

    } catch (e) {
        // ignore
    }
    // Default to light mode if no explicit theme is set
    return false;
}

function getChartColors() {
    const isDark = isDarkTheme();

    const lightPalette = [
        "#2563eb", // Blue
        "#22c55e", // Green
        "#f59e0b", // Amber
        "#ef4444", // Red
        "#a855f7", // Purple
        "#06b6d4", // Cyan
        "#f97316", // Orange
        "#10b981"  // Emerald
    ];

    const darkPalette = [
        "#60a5fa", // Soft blue
        "#34d399", // Mint
        "#fbbf24", // Warm amber
        "#fb7185", // Coral
        "#c084fc", // Violet
        "#22d3ee", // Bright cyan
        "#f97316", // Orange
        "#4ade80"  // Lime
    ];

    return {
        palette: isDark ? darkPalette : lightPalette,
        textColor: isDark ? "#e5e7eb" : "#1f2937",
        tooltipBg: isDark ? "#111827" : "#ffffff",
        tooltipBorder: isDark ? "#1f2937" : "#e5e7eb",
        gridColor: isDark ? "rgba(255,255,255,0.08)" : "rgba(0,0,0,0.06)",
        borderColor: isDark ? "#0f172a" : "#ffffff"
    };
}

function getPaletteForLength(palette, length) {
    if (!Array.isArray(palette) || palette.length === 0 || !Number.isFinite(length)) {
        return [];
    }

    return Array.from({ length }, (_, idx) => palette[idx % palette.length]);
}






// Hierarchy functions
function updateHierarchyUI(state) {
    const elements = {
        'ceo-name': document.getElementById('ceo-name'),
        'ceo-email': document.getElementById('ceo-email'),
        'ceo-emp-id': document.getElementById('ceo-emp-id'),
        'ceo-doj': document.getElementById('ceo-doj'),
        'manager-name': document.getElementById('manager-name'),
        'manager-email': document.getElementById('manager-email'),
        'manager-emp-id': document.getElementById('manager-emp-id'),
        'manager-doj': document.getElementById('manager-doj'),
        'user-name': document.getElementById('user-name'),
        'user-email': document.getElementById('user-email'),
        'user-emp-id': document.getElementById('user-emp-id'),
        'user-doj': document.getElementById('user-doj')
    };

    if (state === 'loading') {
        Object.values(elements).forEach(el => {
            if (el) {
                if (el.id.includes('name')) el.textContent = 'Loading...';
                else if (el.id.includes('email')) el.textContent = 'Loading...';
                else el.textContent = '-';
            }
        });
    } else if (state === 'loaded' && hierarchyData) {
        // Update CEO info
        const ceo = hierarchyData.ceo;
        if (ceo) {
            if (elements['ceo-name']) elements['ceo-name'].textContent = ceo.username || 'N/A';
            if (elements['ceo-email']) elements['ceo-email'].textContent = ceo.useremail || 'N/A';
            if (elements['ceo-emp-id']) elements['ceo-emp-id'].textContent = `EID: ${ceo.employee_id || 'N/A'}`;
            if (elements['ceo-doj']) elements['ceo-doj'].textContent = `DOJ: ${ceo.doj || 'N/A'}`;
        } else {
            if (elements['ceo-name']) elements['ceo-name'].textContent = 'No CEO Found';
            if (elements['ceo-email']) elements['ceo-email'].textContent = 'N/A';
            if (elements['ceo-emp-id']) elements['ceo-emp-id'].textContent = '-';
            if (elements['ceo-doj']) elements['ceo-doj'].textContent = '-';
        }

        // Update Manager info
        const manager = hierarchyData.manager;
        if (manager) {
            if (elements['manager-name']) elements['manager-name'].textContent = manager.username || 'N/A';
            if (elements['manager-email']) elements['manager-email'].textContent = manager.useremail || 'N/A';
            if (elements['manager-emp-id']) elements['manager-emp-id'].textContent = `EID: ${manager.employee_id || 'N/A'}`;
            if (elements['manager-doj']) elements['manager-doj'].textContent = `DOJ: ${manager.doj || 'N/A'}`;
        } else {
            if (elements['manager-name']) elements['manager-name'].textContent = 'No Manager Assigned';
            if (elements['manager-email']) elements['manager-email'].textContent = 'N/A';
            if (elements['manager-emp-id']) elements['manager-emp-id'].textContent = '-';
            if (elements['manager-doj']) elements['manager-doj'].textContent = '-';
        }

        // Update Current User info
        const currentUser = hierarchyData.current_user;
        if (currentUser) {
            if (elements['user-name']) elements['user-name'].textContent = currentUser.username || 'N/A';
            if (elements['user-email']) elements['user-email'].textContent = currentUser.useremail || 'N/A';
            if (elements['user-emp-id']) elements['user-emp-id'].textContent = `EID: ${currentUser.employee_id || 'N/A'}`;
            if (elements['user-doj']) elements['user-doj'].textContent = `DOJ: ${currentUser.doj || 'N/A'}`;
        }
    } else if (state === 'error') {
        const errorText = 'Error loading data';
        if (elements['ceo-name']) elements['ceo-name'].textContent = errorText;
        if (elements['manager-name']) elements['manager-name'].textContent = errorText;
        if (elements['user-name']) elements['user-name'].textContent = errorText;

        if (elements['ceo-email']) elements['ceo-email'].textContent = 'Please try again';
        if (elements['manager-email']) elements['manager-email'].textContent = 'Please try again';
        if (elements['user-email']) elements['user-email'].textContent = 'Please try again';
    }
}

function openHierarchyPopup() {
    const overlay = document.getElementById('hierarchyPopup');
    if (!overlay) return;
    overlay.classList.remove('hidden');
    const container = document.getElementById('hierarchyDynamic');
    if (container) {
        container.innerHTML = '<div style="width:100%;text-align:center;padding:18px;color:#6c757d;">Loading hierarchy…</div>';
    }
    renderHierarchyDynamic();
}

function closeHierarchyPopup() {
    const overlay = document.getElementById('hierarchyPopup');
    if (!overlay) return;
    overlay.classList.add('hidden');
}

// Enhanced vertical hierarchy renderer with arrow connectors
async function renderHierarchyDynamic() {
    const container = document.getElementById('hierarchyDynamic');
    if (!container) return;

    // Inject scoped CSS for the new hierarchy design
    injectHierarchyStyles();

    // Get current level from data attribute if available
    let currentLevel = 5; // Updated to new max level
    const headerEl = document.querySelector('[data-current-level]') || document.querySelector('header.header');
    if (headerEl && headerEl.dataset.currentLevel) {
        const parsed = parseInt(headerEl.dataset.currentLevel, 10);
        if (!isNaN(parsed)) currentLevel = parsed;
    }

    // Get the currently selected user from the dropdown
    const namesSelect = document.getElementById('namesSelect') || document.getElementById('name-select');
    const selectedUser = namesSelect ? namesSelect.value : '';

    // Fetch hierarchy payload for the selected user
    let payload;
    try {
        const filters = {
            aggregated_analytics: false,
            _: Date.now(),
            date_column: getActiveDateColumn()
        };

        if (selectedUser) {
            filters.user_id = selectedUser;
        }

        const url = buildDashboardUrl('dashboard_data.php', filters);
        const res = await fetch(url);
        payload = await res.json();
    } catch (e) {
        container.innerHTML = '<div class="hierarchy-error">Failed to load hierarchy data</div>';
        return;
    }
    if (!payload || payload.status !== 'success') {
        container.innerHTML = '<div class="hierarchy-error">No hierarchy data available</div>';
        console.log('Hierarchy error: Invalid payload or failed status', payload);
        return;
    }

    console.log('Hierarchy data received for user:', selectedUser, payload.hierarchy);

    // If backend provides an explicit upward chain, render vertical hierarchy
    if (payload.hierarchy && Array.isArray(payload.hierarchy.chain) && payload.hierarchy.chain.length) {
        console.log('Rendering hierarchy chain:', payload.hierarchy.chain);
        renderVerticalHierarchyChain(container, payload.hierarchy.chain);
        return;
    }

    // Fallback to assigned_users grouping when chain not available
    console.log('No hierarchy chain found, falling back to assigned_users');
    const users = Array.isArray(payload.assigned_users) ? payload.assigned_users : [];
    console.log('Assigned users for fallback:', users);

    if (users.length === 0) {
        container.innerHTML = '<div class="hierarchy-error">No hierarchy data available - no assigned users found</div>';
        return;
    }

    renderVerticalHierarchyFromUsers(container, users, currentLevel);
}

// Function to inject scoped CSS styles for hierarchy
function injectHierarchyStyles() {
    if (document.getElementById('orgHierarchyStyles')) return;

    const style = document.createElement('style');
    style.id = 'orgHierarchyStyles';
    style.textContent = `
        /* Enhanced Vertical Hierarchy Styles */
        #hierarchyPopup .hierarchy-dynamic {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0;
            padding: 20px;
            max-width: 600px;
            margin: 0 auto;
        }
        
        #hierarchyPopup .hierarchy-level {
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
        }
        
        #hierarchyPopup .hierarchy-role-header {
            background: linear-gradient(135deg, var(--role-color, #6c757d), var(--role-color-dark, #5a6268));
            color: white;
            padding: 12px 24px;
            border-radius: 25px;
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
            z-index: 2;
            position: relative;
        }
        
        #hierarchyPopup .hierarchy-role-icon {
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        #hierarchyPopup .hierarchy-role-count {
            background: rgba(255,255,255,0.2);
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            margin-left: 6px;
        }
        
        #hierarchyPopup .hierarchy-members {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 16px;
            margin-bottom: 20px;
        }
        
        #hierarchyPopup .hierarchy-person-card {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 16px;
            padding: 16px;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-width: 160px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        #hierarchyPopup .hierarchy-person-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        }
        
        #hierarchyPopup .hierarchy-person-card.current {
            border-color: var(--role-color, #007bff);
            background: linear-gradient(135deg, rgba(0,123,255,0.05), white);
            box-shadow: 0 4px 16px rgba(0,123,255,0.2);
        }
        
        #hierarchyPopup .person-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--role-color, #6c757d);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 18px;
            margin-bottom: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        #hierarchyPopup .person-name {
            font-weight: 600;
            color: #2c3e50;
            text-align: center;
            margin-bottom: 4px;
            font-size: 14px;
        }
        
        #hierarchyPopup .person-email {
            color: #6c757d;
            font-size: 11px;
            text-align: center;
            word-break: break-word;
        }
        
        #hierarchyPopup .hierarchy-arrow {
            width: 0;
            height: 0;
            border-left: 12px solid transparent;
            border-right: 12px solid transparent;
            border-top: 16px solid var(--role-color, #6c757d);
            margin: 8px 0 16px;
            opacity: 0.7;
        }
        
        #hierarchyPopup .hierarchy-connector {
            width: 2px;
            height: 30px;
            background: linear-gradient(to bottom, var(--role-color, #6c757d), transparent);
            margin: -8px 0 -8px;
            opacity: 0.5;
        }
        
        #hierarchyPopup .hierarchy-error {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
            font-style: italic;
            background: #f8f9fa;
            border-radius: 12px;
            border: 2px dashed #dee2e6;
        }
        
        /* Role-specific colors */
        #hierarchyPopup .hierarchy-level[data-role="promoter"] { --role-color: #C58A00; --role-color-dark: #A67300; }
        #hierarchyPopup .hierarchy-level[data-role="business_head"] { --role-color: #7A3EF7; --role-color-dark: #6A35D6; }
        #hierarchyPopup .hierarchy-level[data-role="manager"] { --role-color: #20C997; --role-color-dark: #1BA97A; }
        #hierarchyPopup .hierarchy-level[data-role="team_lead"] { --role-color: #FF6B6B; --role-color-dark: #E55555; }
        #hierarchyPopup .hierarchy-level[data-role="user"] { --role-color: #6C757D; --role-color-dark: #5A6268; }
        
        /* Responsive design */
        @media (max-width: 768px) {
            #hierarchyPopup .hierarchy-dynamic {
                padding: 15px 10px;
            }
            
            #hierarchyPopup .hierarchy-person-card {
                min-width: 140px;
                padding: 12px;
            }
            
            #hierarchyPopup .person-avatar {
                width: 50px;
                height: 50px;
                font-size: 16px;
            }
            
            #hierarchyPopup .hierarchy-members {
                gap: 12px;
            }
        }
        
        @media (max-width: 480px) {
            #hierarchyPopup .hierarchy-members {
                flex-direction: column;
                align-items: center;
            }
            
            #hierarchyPopup .hierarchy-person-card {
                width: 100%;
                max-width: 200px;
            }
        }
        
        /* Dark theme support */
        [data-theme="dark"] #hierarchyPopup .hierarchy-person-card {
            background: #2d3748;
            border-color: #4a5568;
            color: #e2e8f0;
        }
        
        [data-theme="dark"] #hierarchyPopup .person-name {
            color: #e2e8f0;
        }
        
        [data-theme="dark"] #hierarchyPopup .hierarchy-error {
            background: #1a202c;
            border-color: #4a5568;
            color: #a0aec0;
        }
        
    `;
    document.head.appendChild(style);
}

// Function to render vertical hierarchy from chain data
function renderVerticalHierarchyChain(container, chain) {
    console.log('renderVerticalHierarchyChain called with chain:', chain);

    if (!chain || chain.length === 0) {
        container.innerHTML = '<div class="hierarchy-error">No hierarchy chain data available</div>';
        return;
    }

    const roleMeta = {
        promoter: { label: 'Promoter', icon: 'fas fa-crown' },
        business_head: { label: 'Business Head', icon: 'fas fa-briefcase' },
        manager: { label: 'Manager', icon: 'fas fa-users-cog' },
        team_lead: { label: 'Team Lead', icon: 'fas fa-user-friends' },
        user: { label: 'User', icon: 'fas fa-user' }
    };

    const getInitials = (name = '') => {
        const parts = String(name).trim().split(/\s+/).filter(Boolean);
        if (!parts.length) return '?';
        const initials = (parts[0][0] || '') + (parts.length > 1 ? (parts[1][0] || '') : '');
        return initials.toUpperCase();
    };

    // Group chain by role for vertical display
    const roleGroups = new Map();
    chain.forEach((person, idx) => {
        const roleKey = String(person.user_type || 'user').toLowerCase().replace(/\s+/g, '_');
        const normalizedRole = roleMeta[roleKey] ? roleKey : 'user';
        console.log(`Person ${idx}: ${person.username}, role: ${person.user_type} -> normalized: ${normalizedRole}`);

        if (!roleGroups.has(normalizedRole)) {
            roleGroups.set(normalizedRole, []);
        }
        roleGroups.get(normalizedRole).push({ ...person, isCurrent: idx === 0 });
    });

    // Define hierarchy order (top to bottom)
    const hierarchyOrder = ['promoter', 'business_head', 'manager', 'team_lead', 'user'];

    container.innerHTML = '';
    const fragment = document.createDocumentFragment();

    let isFirst = true;
    hierarchyOrder.forEach(roleKey => {
        if (!roleGroups.has(roleKey)) return;

        const members = roleGroups.get(roleKey);
        const meta = roleMeta[roleKey];

        // Add connector arrow (except for first level)
        if (!isFirst) {
            const arrow = document.createElement('div');
            arrow.className = 'hierarchy-arrow';
            fragment.appendChild(arrow);
        }

        // Create level container
        const levelDiv = document.createElement('div');
        levelDiv.className = 'hierarchy-level';
        levelDiv.setAttribute('data-role', roleKey);

        // Role header
        const header = document.createElement('div');
        header.className = 'hierarchy-role-header';
        header.innerHTML = `
            <i class="${meta.icon} hierarchy-role-icon"></i>
            <span>${meta.label}</span>
           
        `;

        // Members container
        const membersDiv = document.createElement('div');
        membersDiv.className = 'hierarchy-members';

        members.forEach(person => {
            const card = document.createElement('div');
            card.className = 'hierarchy-person-card' + (person.isCurrent ? ' current' : '');

            card.innerHTML = `
                <div class="person-avatar">${getInitials(person.username || person.tablename)}</div>
                <div class="person-name">${person.username || person.tablename || 'Unknown'}</div>
                ${person.useremail ? `<div class="person-email">${person.useremail}</div>` : ''}
            `;

            membersDiv.appendChild(card);
        });

        levelDiv.appendChild(header);
        levelDiv.appendChild(membersDiv);
        fragment.appendChild(levelDiv);

        isFirst = false;
    });

    container.appendChild(fragment);
}

// Function to render vertical hierarchy from users data (fallback)
function renderVerticalHierarchyFromUsers(container, users, currentLevel) {
    const roleMeta = {
        1: { key: 'promoter', label: 'Promoter', icon: 'fas fa-crown' },
        2: { key: 'business_head', label: 'Business Head', icon: 'fas fa-briefcase' },
        3: { key: 'manager', label: 'Manager', icon: 'fas fa-users-cog' },
        4: { key: 'team_lead', label: 'Team Lead', icon: 'fas fa-user-friends' },
        5: { key: 'user', label: 'User', icon: 'fas fa-user' }
    };

    const levelOf = (role) => {
        const r = String(role || '').toLowerCase();
        if (r === 'promoter' || r === 'ceo') return 1;
        if (r === 'business_head' || r === 'business head' || r === 'bh') return 2;
        if (r === 'manager' || r === 'm') return 3;
        if (r === 'team_lead' || r === 'team lead' || r === 'tl') return 4;
        return 5;
    };

    const getInitials = (name = '') => {
        const parts = String(name).trim().split(/\s+/).filter(Boolean);
        if (!parts.length) return '?';
        const initials = (parts[0][0] || '') + (parts.length > 1 ? (parts[1][0] || '') : '');
        return initials.toUpperCase();
    };

    // Group by role level
    const myId = typeof currentUserTableName !== 'undefined' ? currentUserTableName : null;
    const byLevel = new Map();

    for (const u of users) {
        const lvl = (typeof u.role_level === 'number' && !isNaN(u.role_level)) ? u.role_level : levelOf(u.user_type);
        if (lvl < currentLevel) continue;
        if (!byLevel.has(lvl)) byLevel.set(lvl, []);
        byLevel.get(lvl).push(u);
    }

    // Sort each group: current user first, then by name
    for (const [lvl, arr] of byLevel) {
        arr.sort((a, b) => {
            if (myId && (a.tablename === myId) !== (b.tablename === myId)) {
                return a.tablename === myId ? -1 : 1;
            }
            return String(a.username || '').localeCompare(String(b.username || ''));
        });
    }

    container.innerHTML = '';
    const fragment = document.createDocumentFragment();

    let isFirst = true;
    for (let lvl = 1; lvl <= 5; lvl++) {
        const group = byLevel.get(lvl) || [];
        const meta = roleMeta[lvl];
        if (!meta || group.length === 0) continue;

        // Add connector arrow (except for first level)
        if (!isFirst) {
            const arrow = document.createElement('div');
            arrow.className = 'hierarchy-arrow';
            fragment.appendChild(arrow);
        }

        // Create level container
        const levelDiv = document.createElement('div');
        levelDiv.className = 'hierarchy-level';
        levelDiv.setAttribute('data-role', meta.key);

        // Role header
        const header = document.createElement('div');
        header.className = 'hierarchy-role-header';
        header.innerHTML = `
            <i class="${meta.icon} hierarchy-role-icon"></i>
            <span>${meta.label}</span>
            
        `;

        // Members container
        const membersDiv = document.createElement('div');
        membersDiv.className = 'hierarchy-members';

        group.forEach(person => {
            const card = document.createElement('div');
            card.className = 'hierarchy-person-card' + (myId && person.tablename === myId ? ' current' : '');

            card.innerHTML = `
                <div class="person-avatar">${getInitials(person.username)}</div>
                <div class="person-name">${person.username || person.tablename || 'Unknown'}</div>
                ${person.useremail ? `<div class="person-email">${person.useremail}</div>` : ''}
            `;

            membersDiv.appendChild(card);
        });

        levelDiv.appendChild(header);
        levelDiv.appendChild(membersDiv);
        fragment.appendChild(levelDiv);

        isFirst = false;
    }

    if (fragment.childNodes.length) {
        container.appendChild(fragment);
    } else {
        container.innerHTML = '<div class="hierarchy-error">No team members found for your role level</div>';
    }
}

// Event handlers
function handleNamesSelectChange() {
    // Sync user selection state and get selected user
    const selectedUser = syncUserSelectionState();
    console.log('User selection changed:', selectedUser);
    console.log('Stored currentlySelectedUser:', currentlySelectedUser);
    const monthSelect = document.getElementById("monthSelect");
    const yearSelect = document.getElementById("yearSelect");

    const currentMonth = monthSelect ? monthSelect.value : currentSelectedMonth;
    const currentYear = yearSelect ? yearSelect.value : currentSelectedYear;

    toggleBackToDashboardButton(selectedUser && selectedUser !== currentUserTableName);

    if (selectedUser) {
        if (selectedUser === currentUserTableName && originalUserData) {
            console.log("Switching back to current user using stored data");
            updateWelcomeText(`Hello, ${originalUserName} 👋`);
            updateDashboardWithData(originalUserData);
            loadUserTotalData();

            // Refresh perf badge for current user
            if (typeof refreshUserPerfCard === 'function') {
                refreshUserPerfCard(); // No parameter = uses session user
            }

            // If booking popup is open, refresh its data for current user
            const bookingPopup = document.getElementById('bookingPopup');
            if (bookingPopup && !bookingPopup.classList.contains('hidden')) {
                console.log('Booking popup is open, refreshing data for current user');
                fetchBookingData();
            }
            return;
        }

        // Check if we have an active custom date range, otherwise use month/year
        const filters = {
            user_id: selectedUser,
            date_column: getActiveDateColumn()
        };

        if (lastStartDate && lastEndDate) {
            filters.start_date = lastStartDate;
            filters.end_date = lastEndDate;
            console.log('Loading selected user data with custom date range:', lastStartDate, 'to', lastEndDate);
        } else {
            filters.month = currentMonth;
            filters.year = currentYear;
            console.log('Loading selected user data with month/year:', currentMonth, '/', currentYear);
        }

        const url = buildDashboardUrl('dashboard_data.php', filters);

        fetchData(url).then(data => {
            if (data.status === "success") {
                console.log("Selected user data:", data);

                // Get selected username using helper function
                const selectedUsername = getSelectedUserName() || selectedUser;

                updateWelcomeText(`Viewing: ${selectedUsername} 👀`);

                updateDashboardWithData(data);

                // Refresh perf badge for selected user
                if (typeof refreshUserPerfCard === 'function') {
                    refreshUserPerfCard(selectedUser);
                }

                // Reset popup view state and filters when switching primary user
                currentViewMode = 'normal';
                currentHierarchyUser = null;

                if (selectedUsers.size > 0 || preservedSelectedUsers.size > 0) {
                    selectedUsers.clear();
                    preservedSelectedUsers.clear();

                    if (typeof updateDropdownSelections === 'function') updateDropdownSelections();
                    if (typeof updateSelectedTags === 'function') updateSelectedTags();
                    if (typeof updateDropdownPlaceholder === 'function') updateDropdownPlaceholder();
                    if (typeof refreshDropdownBadges === 'function') refreshDropdownBadges();
                    if (typeof updateFilterModeIndicator === 'function') updateFilterModeIndicator();
                }

                // Only refresh popup analytics if the dashboard popup is actually visible
                const dashboardPopup = document.getElementById('dashboardPopup');
                const isDashboardPopupVisible = dashboardPopup && dashboardPopup.classList.contains('active');

                if (isDashboardPopupVisible) {
                    console.log('Dashboard popup is visible, refreshing popup data for selected user:', selectedUser);
                    if (lastStartDate && lastEndDate) {
                        loadPopupDashboardDataByDateRange(lastStartDate, lastEndDate, false);
                    } else {
                        const monthToLoad = parseInt(currentMonth, 10) || currentSelectedMonth;
                        const yearToLoad = parseInt(currentYear, 10) || currentSelectedYear;
                        loadPopupDashboardData(monthToLoad, yearToLoad, false);
                    }
                } else {
                    console.log('Dashboard popup is not visible, skipping popup data refresh');
                }

                // If booking popup is open, refresh its data for the new user
                const bookingPopup = document.getElementById('bookingPopup');
                if (bookingPopup && !bookingPopup.classList.contains('hidden')) {
                    console.log('Booking popup is open, refreshing data for selected user:', selectedUser);
                    fetchBookingData();
                }

                // If hierarchy popup is open, refresh the hierarchy for the new user
                const hierarchyPopup = document.getElementById('hierarchyPopup');
                if (hierarchyPopup && !hierarchyPopup.classList.contains('hidden')) {
                    console.log('Hierarchy popup is open, refreshing hierarchy for selected user:', selectedUser);
                    renderHierarchyDynamic();
                }
            }
        });
    } else if (originalUserData && originalUserName) {
        updateWelcomeText(`Hello, ${originalUserName} 👋`);
        updateDashboardWithData(originalUserData);
        loadUserTotalData();
    } else {
        // Check if we have custom date range, otherwise use month/year
        if (lastStartDate && lastEndDate) {
            const filters = {
                start_date: lastStartDate,
                end_date: lastEndDate,
                date_column: getActiveDateColumn()
            };
            const url = buildDashboardUrl('dashboard_data.php', filters);
            fetchData(url).then(data => {
                if (data.status === "success") {
                    updateDashboardWithData(data);
                }
            });
        } else {
            loadDashboardData(currentMonth, currentYear);
        }
        loadUserTotalData();
    }
}


function handleCustomRangeApply(forceReload = false, preserveSelections = true) {
    const startInput = document.getElementById("startDate");
    const endInput = document.getElementById("endDate");

    if (!startInput || !endInput) {
        console.warn("Custom range inputs not found; skipping apply.");
        return;
    }

    let startDate = startInput.value;
    let endDate = endInput.value;

    if (forceReload && (!startDate || !endDate) && lastStartDate && lastEndDate) {
        startDate = lastStartDate;
        endDate = lastEndDate;
        if (!startInput.value) startInput.value = startDate;
        if (!endInput.value) endInput.value = endDate;
    }

    if (!startDate || !endDate) {
        showNotification("Please select both start and end dates", "warning");
        return;
    }

    // Validate date range
    const validation = validateDateRange(startDate, endDate);
    if (!validation.isValid) {
        showNotification(validation.message, "error");
        return;
    }

    if (!forceReload && startDate === lastStartDate && endDate === lastEndDate) {
        return;
    }

    lastStartDate = startDate;
    lastEndDate = endDate;

    const popupStartDateInput = document.getElementById('popupStartDate');
    const popupEndDateInput = document.getElementById('popupEndDate');
    if (popupStartDateInput) popupStartDateInput.value = startDate;
    if (popupEndDateInput) popupEndDateInput.value = endDate;

    console.log("Loading data from", startDate, "to", endDate);

    // For custom date ranges, show "Selected Period" instead of specific month/year
    updateMetricLabelsForCustomRange(startDate, endDate);

    // Sync user selection state and get selected user
    const selectedUser = syncUserSelectionState();

    console.log("Custom date range applied - maintaining selected user:", currentlySelectedUser);

    const filters = {
        start_date: startDate,
        end_date: endDate,
        date_column: getActiveDateColumn()
    };

    if (selectedUser) {
        filters.user_id = selectedUser;
        console.log("Including selected user in custom date request:", selectedUser);
    }

    const url = buildDashboardUrl('dashboard_data.php', filters);

    fetchData(url)
        .then(data => {
            if (data.status === "success") {
                console.log("Custom date range data received for user:", selectedUser || "all users");
                updateDashboardWithData(data);
                loadUserTotalData(selectedUser); // Pass selected user to maintain consistency

                loadPopupDashboardDataByDateRange(startDate, endDate, preserveSelections);

                if (selectedUsers.size > 0 || selectedProjectNames.size > 0) {
                    setTimeout(() => applyAllFilters(), 250);
                } else if (currentViewMode === 'hierarchy' && currentHierarchyUser) {
                    setTimeout(() => showTeamView(currentHierarchyUser), 250);
                }

                // If booking popup is open, refresh its data for the current context
                const bookingPopup = document.getElementById('bookingPopup');
                if (bookingPopup && !bookingPopup.classList.contains('hidden')) {
                    console.log('Booking popup is open, refreshing data for custom date range');
                    fetchBookingData();
                }

            } else {
                console.error("Error loading date range data:", data.message);
                showNotification("Error loading data: " + data.message, "error");
            }
        })
        .catch(err => {
            console.error("Error fetching custom range data:", err);
            showNotification("Network error loading data", "error");
        });
}

function updateMetricLabelsForCustomRange(startDate, endDate) {
    const start = new Date(startDate);
    const end = new Date(endDate);

    const formatDate = (date) => {
        return date.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric'
        });
    };

    const periodText = `${formatDate(start)} - ${formatDate(end)}`;

    const metricLabels = document.querySelectorAll('.metric-label');
    metricLabels.forEach(label => {
        const originalText = label.getAttribute('data-original') || label.textContent;
        label.setAttribute('data-original', originalText);

        // Replace "Monthly" with the custom period
        if (originalText.includes('Monthly')) {
            label.textContent = originalText.replace('Monthly', periodText);
        } else {
            label.textContent = `${periodText} ${originalText}`;
        }
    });
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

function setupDateRangeAutoApply() {
    const startDateInput = document.getElementById("startDate");
    const endDateInput = document.getElementById("endDate");

    if (startDateInput && endDateInput) {
        // Remove any existing listeners first
        startDateInput.onchange = null;
        endDateInput.onchange = null;

        const autoApplyHandler = function () {
            console.log("Date changed:", {
                start: startDateInput.value,
                end: endDateInput.value
            });

            if (!startDateInput.value || !endDateInput.value) {
                console.log("Waiting for both dates to be selected...");
                return;
            }

            const validation = validateDateRange(startDateInput.value, endDateInput.value);
            if (!validation.isValid) {
                showNotification(validation.message, "error");
                return;
            }

            console.log("Both dates selected, applying filter...");

            if (dateRangeTimeout) {
                clearTimeout(dateRangeTimeout);
            }

            dateRangeTimeout = setTimeout(() => {
                console.log("Executing handleCustomRangeApply...");
                handleCustomRangeApply(true);
            }, 300);
        };

        startDateInput.addEventListener('change', autoApplyHandler);
        endDateInput.addEventListener('change', autoApplyHandler);

        console.log("Auto-apply handlers attached to date inputs");
    } else {
        // Silently skip if date inputs don't exist (expected on some pages)
        // Date inputs not found - this is normal on pages that don't have date range filters
    }
}

function handleCustomRangeCancel() {
    const monthYearSelectors = document.getElementById("monthYearSelectors");
    const dateRangeFilter = document.getElementById("dateRangeFilter");
    const monthSelect = document.getElementById("monthSelect");
    const yearSelect = document.getElementById("yearSelect");

    if (dateRangeFilter) dateRangeFilter.style.display = "none";
    if (monthYearSelectors) monthYearSelectors.style.display = "flex";

    // Reset to current month/year
    const now = new Date();
    if (monthSelect) monthSelect.value = now.getMonth() + 1;
    if (yearSelect) yearSelect.value = now.getFullYear();

    // Update current selection
    currentSelectedMonth = now.getMonth() + 1;
    currentSelectedYear = now.getFullYear();

    // Update metric labels
    updateMetricLabels(currentSelectedMonth, currentSelectedYear);

    // Reset custom range tracking
    lastStartDate = null;
    lastEndDate = null;

    // Load data for current month/year
    loadDashboardData(currentSelectedMonth, currentSelectedYear);
}

// Initialize controls when document loads
document.addEventListener("DOMContentLoaded", function () {
    setupDateRangeControls();
});
function updateMetricLabels(month, year) {
    const monthNames = [
        "Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"
    ];

    const monthName = monthNames[month - 1];
    const periodText = `${monthName} ${year}`;

    // Update all metric labels
    const metricLabels = document.querySelectorAll('.metric-label');
    metricLabels.forEach(label => {
        // Ensure data-original attribute exists
        if (!label.hasAttribute('data-original')) {
            const originalText = label.textContent;
            label.setAttribute('data-original', originalText);
        }

        const originalText = label.getAttribute('data-original');

        // Replace "Monthly" with the actual period
        if (originalText.includes('Monthly')) {
            label.textContent = originalText.replace('Monthly', periodText);
        } else {
            // If it doesn't contain "Monthly", prepend the period
            label.textContent = `${periodText} ${originalText}`;
        }
    });
}


// Apply or clear Financial Year mode based on month dropdown selection.
function setFinancialYearMode(isEnabled) {
    const monthSelect = document.getElementById("monthSelect");
    const yearSelect = document.getElementById("yearSelect");
    const monthYearSelectors = document.getElementById("monthYearSelectors");
    const dateRangeFilter = document.getElementById("dateRangeFilter");
    const startDateInput = document.getElementById("startDate");
    const endDateInput = document.getElementById("endDate");

    if (isEnabled) {
        // Switch to Financial Year mode
        const fy = getCurrentFinancialYear();
        
        if (startDateInput) startDateInput.value = fy.start;
        if (endDateInput) endDateInput.value = fy.end;

        // Keep FY as selected value in month dropdown.
        if (monthSelect) {
            monthSelect.value = "fy";
        }
        if (yearSelect) yearSelect.disabled = true;

        // Show date range picker and hide month/year selectors (same UX as custom mode).
        if (dateRangeFilter) dateRangeFilter.style.display = "flex";
        if (monthYearSelectors) monthYearSelectors.style.display = "none";

        // Update tracking variables
        lastStartDate = fy.start;
        lastEndDate = fy.end;

        // Load data
        handleCustomRangeApply(true);
    } else {
        // Revert FY-specific locks and let regular month/year/custom flow continue.
        if (yearSelect) yearSelect.disabled = false;

        if (monthSelect && monthSelect.value === "fy") {
            const now = new Date();
            monthSelect.value = now.getMonth() + 1;
        }

        if (monthYearSelectors) monthYearSelectors.style.display = "flex";
    }
}


// Update the handleMonthYearChange function
function handleMonthYearChange() {
    const monthSelect = document.getElementById("monthSelect");
    const yearSelect = document.getElementById("yearSelect");
    const monthYearSelectors = document.getElementById("monthYearSelectors");
    const dateRangeFilter = document.getElementById("dateRangeFilter");

    if (monthSelect.value === "fy") {
        setFinancialYearMode(true);
        return;
    }

    setFinancialYearMode(false);

    if (monthSelect.value === "custom") {
        // Show date range picker and hide month/year selectors
        if (dateRangeFilter) dateRangeFilter.style.display = "flex";
        if (monthYearSelectors) monthYearSelectors.style.display = "none";

        // DON'T pre-fill any dates - leave them empty
        const startDate = document.getElementById("startDate");
        const endDate = document.getElementById("endDate");

        if (startDate) startDate.value = '';
        if (endDate) endDate.value = '';

        // Clear any previous date range tracking but maintain user selection
        lastStartDate = null;
        lastEndDate = null;

        // Setup auto-apply for date range inputs
        setupDateRangeAutoApply();

        // Log the current user context when switching to custom date mode
        const selectedUser = getSelectedUserValue();
        console.log("Custom date range selected - current user context:", selectedUser || "all users");

    } else {
        // Regular month/year selection
        currentSelectedMonth = parseInt(monthSelect.value);
        currentSelectedYear = parseInt(yearSelect.value);

        // Hide date range filter if shown
        if (dateRangeFilter) dateRangeFilter.style.display = "none";
        if (monthYearSelectors) monthYearSelectors.style.display = "flex";

        // Clear any date range tracking when switching back to month/year
        lastStartDate = null;
        lastEndDate = null;

        // Update metric labels first
        updateMetricLabels(currentSelectedMonth, currentSelectedYear);

        // Then load the data (this will maintain user context)
        loadDashboardData(currentSelectedMonth, currentSelectedYear);
    }
}




function handleMonthPickerChange() {
    const [year, month] = this.value.split("-");
    document.getElementById("monthSelect").value = parseInt(month);
    document.getElementById("yearSelect").value = parseInt(year);
    handleMonthYearChange();
}

document.addEventListener("DOMContentLoaded", function () {
    // Skip on leads page - it has its own optimized system
    if (window.location.pathname.includes('user_lead')) {
        return;
    }

    // Check if searchable name select exists and add event listener
    const searchableSelect = document.getElementById('searchableNameSelect');
    if (searchableSelect) {
        searchableSelect.addEventListener('nameSelectChange', function (e) {
            const { value, text, user } = e.detail;
            console.log('Name selected:', { value, text, user });

            // Update current user selection
            currentlySelectedUser = value;

            // Trigger dashboard update
            if (typeof handleNamesSelectChange === 'function') {
                handleNamesSelectChange();
            }
        });
    }

    // In embedded mode, ensure the current user is selected and data loads immediately.
    try {
        const mainEl = document.getElementById('mainContent');
        const isEmbed = mainEl && mainEl.classList.contains('embedded-main');
        if (isEmbed && typeof currentUserTableName !== 'undefined' && currentUserTableName) {
            console.log('Embedded mode detected, currentUserTableName:', currentUserTableName);

            // Function to select user and load data
            function selectUserAndLoadData() {
                let attempts = 0;
                const maxAttempts = 50; // 5 seconds max (50 * 100ms)

                const checkInterval = setInterval(function () {
                    attempts++;

                    // Try to get the active select instance
                    let activeSelect = null;
                    if (window.innerWidth > 1249) {
                        activeSelect = window.headerSelect || window.searchableNameSelect;
                    } else {
                        activeSelect = window.mobileSelect || window.searchableNameSelect;
                    }

                    console.log('Attempt', attempts, 'activeSelect:', activeSelect, 'has users:', activeSelect && activeSelect.users ? activeSelect.users.length : 0);

                    // Check if searchable select exists and has users loaded
                    if (activeSelect && activeSelect.users && activeSelect.users.length > 0) {
                        clearInterval(checkInterval);

                        // Find the user with matching tablename
                        const user = activeSelect.users.find(u => u.value === currentUserTableName);
                        if (user) {
                            console.log('Found user, selecting:', currentUserTableName, user.text);
                            // Select the user - this will trigger the change event
                            activeSelect.selectOption(currentUserTableName);
                            console.log('User selected, data should load now');
                        } else {
                            console.warn('User not found in list:', currentUserTableName, 'Available users:', activeSelect.users.map(u => u.value));
                            // Fallback: try to trigger data load anyway
                            loadDataForUser(currentUserTableName);
                        }
                    } else if (attempts >= maxAttempts) {
                        clearInterval(checkInterval);
                        console.warn('Timeout waiting for users to load, attempting fallback');
                        // Fallback: try to load data anyway
                        loadDataForUser(currentUserTableName);
                    }
                }, 100);
            }

            // Helper function to load data for a user
            function loadDataForUser(userTablename) {
                console.log('Loading data for user (tablename):', userTablename);
                currentlySelectedUser = userTablename;

                // Set the input value manually
                const input = document.getElementById('nameSearchInput');
                if (input) {
                    // Try to get the username from the users list if available
                    const activeSelect = window.innerWidth > 1249
                        ? (window.headerSelect || window.searchableNameSelect)
                        : (window.mobileSelect || window.searchableNameSelect);
                    const user = activeSelect && activeSelect.users ? activeSelect.users.find(u => u.value === userTablename) : null;
                    input.value = user ? user.text : userTablename;
                    input.setAttribute('data-selected-value', userTablename);
                }

                // Directly load dashboard data with the user_id parameter
                const initialMonth = currentSelectedMonth || (new Date().getMonth() + 1);
                const initialYear = currentSelectedYear || new Date().getFullYear();

                console.log('Loading dashboard data directly for user:', userTablename, 'month:', initialMonth, 'year:', initialYear);

                // Build URL with user_id parameter
                const filters = {
                    month: initialMonth,
                    year: initialYear,
                    user_id: userTablename,
                    date_column: getActiveDateColumn ? getActiveDateColumn() : 'created_at'
                };

                const url = buildDashboardUrl('dashboard_data.php', filters);
                console.log('Fetching dashboard data from:', url);

                // Use fetchData if available, otherwise use fetch
                if (typeof fetchData === 'function') {
                    fetchData(url).then(data => {
                        if (data.status === "success") {
                            console.log('Dashboard data loaded successfully');
                            updateDashboardWithData(data);
                            if (typeof loadUserTotalData === 'function') {
                                loadUserTotalData(userTablename);
                            }
                        } else {
                            console.error('Failed to load dashboard data:', data);
                        }
                    }).catch(error => {
                        console.error('Error loading dashboard data:', error);
                    });
                } else if (typeof handleNamesSelectChange === 'function') {
                    console.log('Using handleNamesSelectChange as fallback');
                    handleNamesSelectChange();
                } else if (typeof loadDashboardData === 'function') {
                    console.log('Using loadDashboardData as fallback');
                    loadDashboardData(initialMonth, initialYear);
                } else {
                    console.error('Neither fetchData, handleNamesSelectChange nor loadDashboardData is available');
                }
            }

            // Function to select user and load data
            function selectUserAndLoadData() {
                let attempts = 0;
                const maxAttempts = 50; // 5 seconds max (50 * 100ms)

                const checkInterval = setInterval(function () {
                    attempts++;

                    // Try to get the active select instance
                    let activeSelect = null;
                    if (window.innerWidth > 1249) {
                        activeSelect = window.headerSelect || window.searchableNameSelect;
                    } else {
                        activeSelect = window.mobileSelect || window.searchableNameSelect;
                    }

                    console.log('Attempt', attempts, 'activeSelect:', activeSelect, 'has users:', activeSelect && activeSelect.users ? activeSelect.users.length : 0);

                    // Check if searchable select exists and has users loaded
                    if (activeSelect && activeSelect.users && activeSelect.users.length > 0) {
                        clearInterval(checkInterval);

                        // Find the user with matching tablename
                        const user = activeSelect.users.find(u => u.value === currentUserTableName);
                        if (user) {
                            console.log('Found user, selecting:', currentUserTableName, user.text);
                            // Select the user - this will trigger the change event
                            activeSelect.selectOption(currentUserTableName);
                            console.log('User selected, data should load now');
                        } else {
                            console.warn('User not found in list:', currentUserTableName, 'Available users:', activeSelect.users.map(u => u.value));
                            // Fallback: try to trigger data load anyway
                            loadDataForUser(currentUserTableName);
                        }
                    } else if (attempts >= maxAttempts) {
                        clearInterval(checkInterval);
                        console.warn('Timeout waiting for users to load, attempting fallback');
                        // Fallback: try to load data anyway
                        currentlySelectedUser = currentUserTableName;
                        const input = document.getElementById('nameSearchInput');
                        if (input) {
                            input.value = currentUserTableName;
                            input.setAttribute('data-selected-value', currentUserTableName);
                        }
                        if (typeof handleNamesSelectChange === 'function') {
                            handleNamesSelectChange();
                        } else if (typeof loadDashboardData === 'function') {
                            const initialMonth = currentSelectedMonth || (new Date().getMonth() + 1);
                            const initialYear = currentSelectedYear || new Date().getFullYear();
                            loadDashboardData(initialMonth, initialYear);
                        }
                    }
                }, 100);
            }

            // In embedded mode, load data immediately since we have the tablename
            // Don't wait for the select to be ready - just load the data directly
            console.log('Embedded mode: Loading data immediately for tablename:', currentUserTableName);

            // Load data directly first (this is the most reliable way)
            setTimeout(function () {
                loadDataForUser(currentUserTableName);
            }, 500);

            // Also try to update the select when it's ready (for UI consistency)
            setTimeout(selectUserAndLoadData, 1000);

            // Also try immediately if searchable select is already ready
            setTimeout(function () {
                const activeSelect = window.innerWidth > 1249
                    ? (window.headerSelect || window.searchableNameSelect)
                    : (window.mobileSelect || window.searchableNameSelect);

                if (activeSelect && activeSelect.users && activeSelect.users.length > 0) {
                    const user = activeSelect.users.find(u => u.value === currentUserTableName);
                    if (user) {
                        console.log('Early selection - user already loaded, updating UI');
                        activeSelect.selectOption(currentUserTableName);
                    }
                }
            }, 2000);
        }
    } catch (e) {
        console.warn('Embedded initialization error:', e);
    }

    // Legacy support - keep the old select creation for backward compatibility
    // but only if searchable select doesn't exist
    if (!searchableSelect && window.innerWidth >= 1250) {
        const container = document.createElement("div");
        container.className = "names-dropdown";
        container.innerHTML = `
            <select id="namesSelect" class="names-select">
                <option value="">Select a Name</option>
            </select>
        `;
        const headerCenter = document.querySelector(".header-name-select");
        if (headerCenter) {
            headerCenter.prepend(container);
        }
    }
});

// Compact chart options for status chart
const compactChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            position: 'bottom',
            labels: {
                color: colors.textColor,
                padding: window.innerWidth <= 400 ? 2 : window.innerWidth <= 556 ? 3 : window.innerWidth <= 768 ? 6 : window.innerWidth <= 1200 ? 8 : 10, // More responsive padding for very small screens
                usePointStyle: true,
                font: {
                    size: window.innerWidth <= 400 ? 6 : window.innerWidth <= 556 ? 7 : window.innerWidth <= 768 ? 8 : window.innerWidth <= 1200 ? 10 : 11 // Progressive font sizes for very small screens
                },
                boxWidth: window.innerWidth <= 400 ? 4 : window.innerWidth <= 556 ? 5 : window.innerWidth <= 768 ? 7 : window.innerWidth <= 1200 ? 10 : 12, // Progressive box sizes for very small screens
                generateLabels: function (chart) {
                    const data = chart.data;
                    const currentColors = getChartColors();
                    if (data.labels.length && data.datasets.length) {
                        return data.labels.map((label, i) => {
                            const value = data.datasets[0].data[i];
                            let displayLabel = label;

                            // More aggressive truncation for small screens
                            if (window.innerWidth <= 480 && displayLabel.length > 8) {
                                displayLabel = label.substring(0, 6) + '..';
                            } else if (window.innerWidth <= 768 && displayLabel.length > 12) {
                                displayLabel = label.substring(0, 10) + '..';
                            }

                            return {
                                text: `${displayLabel} (${value})`,
                                fillStyle: data.datasets[0].backgroundColor[i],
                                strokeStyle: data.datasets[0].borderColor || '#fff',
                                fontColor: currentColors.textColor,
                                lineWidth: 1,
                                hidden: false,
                                index: i
                            };
                        });
                    }
                    return [];
                }
            }
        },
        tooltip: {
            backgroundColor: colors.tooltipBg,
            titleColor: colors.textColor,
            bodyColor: colors.textColor,
            borderColor: colors.tooltipBorder,
            borderWidth: 1,
            titleFont: {
                size: window.innerWidth <= 480 ? 9 : window.innerWidth <= 768 ? 10 : 12
            },
            bodyFont: {
                size: window.innerWidth <= 480 ? 8 : window.innerWidth <= 768 ? 9 : 11
            }
        }
    },
    cutout: window.innerWidth <= 400 ? '15%' : window.innerWidth <= 556 ? '20%' : window.innerWidth <= 768 ? '30%' : window.innerWidth <= 1200 ? '40%' : '45%', // Progressive cutout for very small screens
    layout: {
        padding: {
            top: window.innerWidth <= 400 ? 1 : window.innerWidth <= 556 ? 2 : window.innerWidth <= 768 ? 3 : window.innerWidth <= 1200 ? 5 : 8,
            bottom: window.innerWidth <= 400 ? 1 : window.innerWidth <= 556 ? 2 : window.innerWidth <= 768 ? 3 : window.innerWidth <= 1200 ? 5 : 8,
            left: window.innerWidth <= 400 ? 1 : window.innerWidth <= 556 ? 2 : window.innerWidth <= 768 ? 3 : window.innerWidth <= 1200 ? 5 : 8,
            right: window.innerWidth <= 400 ? 1 : window.innerWidth <= 556 ? 2 : window.innerWidth <= 768 ? 3 : window.innerWidth <= 1200 ? 5 : 8
        }
    }
};

function isMobile() {
    return window.innerWidth <= 768;
}

function isSmallMobile() {
    return window.innerWidth <= 480;
}

// Get optimized chart options for mobile
function getMobileChartOptions() {
    const mobile = isMobile();
    const smallMobile = isSmallMobile();
    const desktop = !mobile;

    return {
        responsive: true,
        maintainAspectRatio: false,
        // Adjust cutout based on screen size - smaller for better fit
        cutout: smallMobile ? '30%' : mobile ? '35%' : '40%',
        plugins: {
            legend: {
                position: 'bottom', // Always bottom for better mobile experience
                labels: {
                    padding: smallMobile ? 4 : mobile ? 6 : 8,
                    usePointStyle: true,
                    font: {
                        size: smallMobile ? 8 : mobile ? 9 : 10
                    },
                    boxWidth: smallMobile ? 6 : mobile ? 8 : 10,
                    maxColumns: smallMobile ? 2 : mobile ? 3 : 4 // Limit columns to prevent overflow
                }
            },
            tooltip: {
                titleFont: {
                    size: mobile ? 10 : 11
                },
                bodyFont: {
                    size: mobile ? 9 : 10
                }
            }
        },
        layout: {
            padding: {
                left: smallMobile ? 2 : mobile ? 4 : 5,
                right: smallMobile ? 2 : mobile ? 4 : 5,
                top: smallMobile ? 2 : mobile ? 4 : 5,
                bottom: smallMobile ? 2 : mobile ? 4 : 5
            }
        }
    };
}

const compactChartCSS = `
<style>
/* Compact chart containers for desktop */
@media (min-width: 769px) {
    .chart-overall {
        padding: 15px !important; /* Reduced padding */
    }
    
    .chart-overall h3 {
        font-size: 16px !important; /* Smaller title */
        margin-bottom: 15px !important;
    }
    
    .chart-container.chart-overall {
        max-height: 400px !important; /* Smaller max height */
    }
    
    .chart-container.chart-overall .chart-wrapper {
        min-width: 300px !important; /* Smaller min width */
        min-height: 220px !important; /* Smaller min height */
    }
    
    .chart-container.chart-overall canvas {
        min-width: 300px !important;
        min-height: 220px !important;
    }
    
    /* Compact stats overview */
    .stats-overview .stat-card {
        padding: 15px !important;
        min-width: 200px !important; /* Smaller cards */
    }
    
    .stats-overview .stat-card h3 {
        font-size: 13px !important;
    }
    
    .stats-overview .stat-card .number {
        font-size: 24px !important;
    }
}

/* Enhanced layout for larger desktop screens */
@media (min-width: 1200px) {
    .charts-section {
        grid-template-columns: repeat(auto-fit, minmax(500px, 1fr)) !important; /* Larger columns for better display */
        gap: 25px !important;
    }
    
    .chart-overall {
        padding: 25px !important;
        min-height: 420px !important; /* Reduced to match both charts */
    }
    
    .chart-container.chart-overall .chart-wrapper {
        width: 100% !important;
        height: 100% !important;
        min-width: 0 !important;
        min-height: 0 !important;
    }
    
    .chart-container.chart-overall canvas {
        width: 100% !important;
        height: 100% !important;
        min-width: 0 !important;
        min-height: 0 !important;
    }
}

/* Mobile adjustments remain the same */
@media (max-width: 768px) {
    .chart-container.chart-overall {
        max-height: 350px;
    }
    
    .chart-container.chart-overall .chart-wrapper {
        min-width: 300px;
        min-height: 220px;
    }
    
    .chart-container.chart-overall canvas {
        min-width: 300px !important;
        min-height: 220px !important;
    }
}

@media (max-width: 480px) {
    .chart-container.chart-overall {
        max-height: 300px;
    }
    
    .chart-container.chart-overall .chart-wrapper {
        min-width: 280px;
        min-height: 200px;
    }
    
    .chart-container.chart-overall canvas {
        min-width: 280px !important;
        min-height: 200px !important;
    }
}

@media (max-width: 550px) {
    .chart-container.chart-overall {
        max-height: 250px;
        overflow-x: auto;
        overflow-y: auto;
    }
    
    .chart-container.chart-overall .chart-wrapper {
        width: 100%;
        height: 100%;
        min-width: 0;
        min-height: 0;
    }
    
    .chart-container.chart-overall canvas {
        width: 100% !important;
        height: auto !important;
        min-width: 0 !important;
        min-height: 0 !important;
        max-width: 100% !important;
    }
    
    .chart-overall {
        padding: 8px !important;
        min-height: 220px !important;
    }
    
    .chart-overall h3 {
        font-size: 13px !important;
        margin-bottom: 8px !important;
    }
}
</style>
`;

// Add the compact CSS to the document
if (!document.getElementById('compactChartCSS')) {
    const styleElement = document.createElement('style');
    styleElement.id = 'compactChartCSS';
    styleElement.textContent = compactChartCSS.replace(/<\/?style>/g, ''); // Remove style tags and use just the CSS
    document.head.appendChild(styleElement);
}


// Make chart containers scrollable on mobile
function makeChartScrollable(canvasId) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;

    const container = canvas.closest('.chart-container') ||
        canvas.closest('.user-chart') ||
        canvas.closest('.hierarchy-chart-container') ||
        canvas.closest('.filtered-chart-container');

    if (!container || !isMobile()) return;

    const smallMobile = isSmallMobile();

    // Set container styles for scrolling
    container.style.overflowX = 'auto';
    container.style.overflowY = 'visible';
    container.style.maxWidth = '100%';
    container.style.webkitOverflowScrolling = 'touch';

    // Set minimum dimensions for scrolling
    const minWidth = smallMobile ? '340px' : '390px';
    const minHeight = smallMobile ? '200px' : '240px';

    canvas.style.minWidth = minWidth;
    canvas.style.minHeight = minHeight;
    canvas.style.width = minWidth;
    canvas.style.height = minHeight;

    // Add wrapper if not exists
    let wrapper = container.querySelector('.chart-wrapper');
    if (!wrapper) {
        wrapper = document.createElement('div');
        wrapper.className = 'chart-wrapper';
        wrapper.style.minWidth = minWidth;
        wrapper.style.width = '100%';
        wrapper.style.position = 'relative';

        // Wrap canvas
        const parent = canvas.parentNode;
        parent.insertBefore(wrapper, canvas);
        wrapper.appendChild(canvas);
    } else {
        wrapper.style.minWidth = minWidth;
    }

    // Add scroll indicator if not exists


    // Style scrollbar
    const scrollbarStyles = `
        ::-webkit-scrollbar {
            height: 6px;
            width: 6px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 3px;
        }
        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 3px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
    `;

    if (!document.getElementById('chart-scrollbar-styles')) {
        const style = document.createElement('style');
        style.id = 'chart-scrollbar-styles';
        style.textContent = scrollbarStyles;
        document.head.appendChild(style);
    }
}
function applyMobileChartOptimizations() {
    if (!isMobile()) return;

    // Find all chart canvases
    const chartCanvases = document.querySelectorAll('canvas[id*="Chart"]');

    chartCanvases.forEach(canvas => {
        makeChartScrollable(canvas.id);
    });

    // Adjust chart card styling
    const chartCards = document.querySelectorAll('.chart-card');
    chartCards.forEach(card => {
        card.style.padding = isSmallMobile() ? '10px 6px' : '12px 8px';
    });

    // Adjust chart titles
    const chartTitles = document.querySelectorAll('.chart-title, .chart-overall h3');
    chartTitles.forEach(title => {
        title.style.fontSize = isSmallMobile() ? '12px' : '13px';
        title.style.marginBottom = '10px';
    });
}

const originalInitCharts = initCharts;
initCharts = function () {
    if (!leadStatusData || !leadSourceData) {
        console.log('Waiting for analytics data to load...');
        return;
    }

    const colors = getChartColors();
    const mobileOptions = getMobileChartOptions();

    showChartLoader('statusChart');
    showChartLoader('sourceChart');
    setTimeout(initChartScrolling, 500);

    // Status Chart with mobile optimizations
    const statusCtx = document.getElementById('statusChart');
    if (statusCtx) {
        setTimeout(() => {
            makeChartScrollable('statusChart');

            statusChart = new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    ...leadStatusData,
                    datasets: [{
                        ...leadStatusData.datasets[0],
                        borderColor: colors.borderColor
                    }]
                },
                options: {
                    ...mobileOptions,
                    plugins: {
                        ...mobileOptions.plugins,
                        legend: {
                            ...mobileOptions.plugins.legend,
                            labels: {
                                ...mobileOptions.plugins.legend.labels,
                                color: colors.textColor,
                                generateLabels: function (chart) {
                                    const data = chart.data;
                                    const currentColors = getChartColors();
                                    if (data.labels.length && data.datasets.length) {
                                        return data.labels.map((label, i) => {
                                            const value = data.datasets[0].data[i];
                                            let displayLabel = label;

                                            if (isSmallMobile() && displayLabel.length > 10) {
                                                displayLabel = label.substring(0, 8) + '...';
                                            } else if (isMobile() && displayLabel.length > 15) {
                                                displayLabel = label.substring(0, 12) + '...';
                                            }

                                            return {
                                                text: `${displayLabel} (${value})`,
                                                fillStyle: data.datasets[0].backgroundColor[i],
                                                strokeStyle: data.datasets[0].borderColor || '#fff',
                                                fontColor: currentColors.textColor,
                                                lineWidth: 1,
                                                hidden: false,
                                                index: i
                                            };
                                        });
                                    }
                                    return [];
                                }
                            }
                        },
                        tooltip: {
                            ...mobileOptions.plugins.tooltip,
                            backgroundColor: colors.tooltipBg,
                            titleColor: colors.textColor,
                            bodyColor: colors.textColor,
                            borderColor: colors.tooltipBorder,
                            borderWidth: 1
                        }
                    }
                }
            });

            hideChartLoader('statusChart');
        }, 100);
    }

    // Source Chart with mobile optimizations
    const sourceCtx = document.getElementById('sourceChart');
    if (sourceCtx) {
        setTimeout(() => {
            makeChartScrollable('sourceChart');

            sourceChart = new Chart(sourceCtx, {
                type: 'bar',
                data: {
                    ...leadSourceData,
                    datasets: [{
                        ...leadSourceData.datasets[0],
                        borderColor: colors.borderColor
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: colors.tooltipBg,
                            titleColor: colors.textColor,
                            bodyColor: colors.textColor,
                            borderColor: colors.tooltipBorder,
                            borderWidth: 1,
                            titleFont: {
                                size: isMobile() ? 11 : 13
                            },
                            bodyFont: {
                                size: isMobile() ? 10 : 12
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                color: colors.textColor,
                                font: {
                                    size: isSmallMobile() ? 9 : isMobile() ? 10 : 12
                                }
                            },
                            grid: { color: colors.gridColor }
                        },
                        x: {
                            ticks: {
                                color: colors.textColor,
                                maxRotation: 45,
                                minRotation: 45,
                                font: {
                                    size: isSmallMobile() ? 8 : isMobile() ? 9 : 11
                                },
                                callback: function (value, index, ticks) {
                                    const label = this.getLabelForValue(value);

                                    if (isSmallMobile() && label.length > 8) {
                                        return label.substring(0, 6) + '...';
                                    } else if (isMobile() && label.length > 10) {
                                        return label.substring(0, 8) + '...';
                                    }
                                    return label;
                                },
                                autoSkip: false
                            },
                            grid: { color: colors.gridColor }
                        }
                    },
                    layout: {
                        padding: {
                            bottom: isSmallMobile() ? 25 : isMobile() ? 20 : 15,
                            left: isSmallMobile() ? 5 : isMobile() ? 8 : 10,
                            right: isSmallMobile() ? 5 : isMobile() ? 8 : 10,
                            top: isSmallMobile() ? 5 : isMobile() ? 8 : 10
                        }
                    }
                }
            });

            hideChartLoader('sourceChart');
        }, 200);
    }

    updateSummaryCards();
};
/* Safe original reference (won't throw if undefined) */
const originalInitializePopupCharts = (typeof initializePopupCharts !== 'undefined') ? initializePopupCharts : null;

initializePopupCharts = function (aggregatedData) {
    const STATUS_COLOR_MAP = {
        'Fake': '#B00020',
        'RNR': '#FFB300',
        'Call Back': '#FB8C00',
        'Already Booked': '#2E7D32',
        'Not Interested': '#9E9E9E',
        'Interested': '#00897B',
        'Follow Up': '#1976D2',
        'Fix Site Visit': '#6A1B9A',
        'Site Visit Done': '#388E3C',
        'VC Done': '#8D6E63',
        'Converted': '#F9A825',
        'Re site visit': '#9C27B0',
        'Qualified for this project': '#00BCD4',
        'Not Connected': '#6D4C41'
    };

    const DEFAULT_PALETTE = [
        '#1976D2', '#00897B', '#F9A825', '#FB8C00',
        '#6A1B9A', '#2E7D32', '#B00020', '#e39330ff'
    ];

    /* Helper: return color by status */
    function getStatusColor(status) {
        if (!status && status !== 0) return '#CCCCCC';
        const s = String(status).trim();
        const key = Object.keys(STATUS_COLOR_MAP).find(k => k.toLowerCase() === s.toLowerCase());
        if (key) return STATUS_COLOR_MAP[key];
        return DEFAULT_PALETTE[Math.abs(hashCode(s)) % DEFAULT_PALETTE.length];
    }

    function hashCode(str) {
        let h = 0;
        for (let i = 0; i < str.length; i++) {
            h = ((h << 5) - h) + str.charCodeAt(i);
            h |= 0;
        }
        return h;
    }

    /* Chart theme fallback */
    function _getChartColorsFallback() {
        const isDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        return { palette: DEFAULT_PALETTE, textColor: isDark ? '#EEE' : '#333' };
    }

    /* Responsive options generator */
    function _getResponsiveChartOptions() {
        const colors = (typeof getChartColors === 'function') ? getChartColors() : _getChartColorsFallback();
        const isMobile = window.matchMedia && window.matchMedia('(max-width:600px)').matches;
        return {
            responsive: true,
            maintainAspectRatio: false,
            cutout: isMobile ? '60%' : '55%',
            plugins: {
                legend: {
                    display: true,
                    position: isMobile ? 'bottom' : 'right',
                    labels: {
                        color: colors.textColor,
                        usePointStyle: true,
                        padding: 12,
                        generateLabels: function (chart) {
                            const data = chart.data;
                            if (!data || !data.labels || !data.datasets || !data.datasets.length) return [];
                            const dataset = data.datasets[0];
                            const currentColors = (typeof getChartColors === 'function') ? getChartColors() : _getChartColorsFallback();
                            return data.labels.map((label, i) => {
                                const value = dataset.data[i] || 0;
                                return {
                                    text: `${label} (${value})`,
                                    fillStyle: Array.isArray(dataset.backgroundColor) ? dataset.backgroundColor[i] : dataset.backgroundColor,
                                    strokeStyle: '#fff',
                                    fontColor: currentColors.textColor,
                                    lineWidth: 1,
                                    hidden: false,
                                    index: i
                                };
                            });
                        }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function (context) {
                            const dataset = context.dataset || {};
                            const value = dataset.data ? (dataset.data[context.dataIndex] || 0) : 0;
                            const total = (dataset.data || []).reduce((s, v) => s + Number(v || 0), 0);
                            const pct = total ? ((value / total) * 100).toFixed(1) : '0.0';
                            return `${context.label}: ${value} (${pct}%)`;
                        }
                    }
                }
            }
        };
    }

    /* makeChartScrollable: ensures wrapper scrollable on mobile */
    function makeChartScrollable(chartId) {
        const wrapper = document.getElementById(chartId + '-wrapper') || document.getElementById(chartId)?.parentElement;
        if (!wrapper) return;
        wrapper.style.overflowX = 'auto';
        wrapper.style.webkitOverflowScrolling = 'touch';
        if (!wrapper.style.height) {
            wrapper.style.height = window.matchMedia('(max-width:600px)').matches ? '300px' : '380px';
        }
    }

    /* showChartLoader / hideChartLoader fallback */
    if (typeof showChartLoader !== 'function') window.showChartLoader = function () { };
    if (typeof hideChartLoader !== 'function') window.hideChartLoader = function () { };

    try {
        // Ensure Chart.js is available
        if (typeof Chart === 'undefined') {
            console.error('Chart.js not found. Include Chart.js before this script.');
            hideChartLoader('overallLeadStatusChart');
            hideChartLoader('leadSourceChart');
            return;
        }

        const colors = (typeof getChartColors === 'function') ? getChartColors() : _getChartColorsFallback();
        const mobileOptions = (typeof getMobileChartOptions === 'function') ? getMobileChartOptions() : _getResponsiveChartOptions();

        showChartLoader('overallLeadStatusChart');
        showChartLoader('leadSourceChart');

        /* ---------- Overall Lead Status Chart ---------- */
        const overallCanvasEl = document.getElementById('overallLeadStatusChart');
        const overallWrapper = document.getElementById('overallLeadStatusChart-wrapper');

        // Remove any previous fallback message
        if (overallWrapper) {
            const prevFallback = overallWrapper.querySelector('.chart-fallback');
            if (prevFallback) prevFallback.remove();
        }

        // Check if we have meaningful data (array with length > 0 and at least one count > 0)
        const hasStatusData = Array.isArray(aggregatedData?.detailed_status_counts)
            && aggregatedData.detailed_status_counts.length > 0
            && aggregatedData.detailed_status_counts.some(item => parseInt(item.count) > 0);

        if (overallCanvasEl && hasStatusData) {
            const statusCounts = aggregatedData.detailed_status_counts;
            setTimeout(() => {
                makeChartScrollable('overallLeadStatusChart');

                const ctx = overallCanvasEl.getContext ? overallCanvasEl.getContext('2d') : null;
                if (!ctx) {
                    console.error('overallLeadStatusChart canvas context not available');
                    hideChartLoader('overallLeadStatusChart');
                    return;
                }

                if (window.overallLeadStatusChart && typeof window.overallLeadStatusChart.destroy === 'function') {
                    window.overallLeadStatusChart.destroy();
                }

                const labels = statusCounts.map(item => item.status || 'Unknown');
                const dataVals = statusCounts.map(item => parseInt(item.count, 10) || 0);
                const bgColors = statusCounts.map(item => getStatusColor(item.status));

                window.overallLeadStatusChart = new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: labels,
                        datasets: [{
                            data: dataVals,
                            backgroundColor: bgColors,
                            borderColor: '#ffffff',
                            borderWidth: 2
                        }]
                    },
                    options: {
                        ...mobileOptions,
                        plugins: {
                            ...mobileOptions.plugins,
                            legend: {
                                ...mobileOptions.plugins.legend,
                                labels: {
                                    ...((mobileOptions.plugins && mobileOptions.plugins.legend && mobileOptions.plugins.legend.labels) || {}),
                                    color: colors.textColor,
                                    generateLabels: function (chart) {
                                        const data = chart.data;
                                        if (!data || !data.labels || !data.datasets || !data.datasets.length) return [];
                                        const dataset = data.datasets[0];
                                        const currentColors = (typeof getChartColors === 'function') ? getChartColors() : { textColor: '#333' };
                                        return data.labels.map((label, i) => {
                                            const value = dataset.data[i] || 0;
                                            return {
                                                text: `${label} (${value})`,
                                                fillStyle: Array.isArray(dataset.backgroundColor) ? dataset.backgroundColor[i] : dataset.backgroundColor,
                                                strokeStyle: '#fff',
                                                fontColor: currentColors.textColor,
                                                lineWidth: 1,
                                                hidden: false,
                                                index: i
                                            };
                                        });
                                    }
                                }
                            }
                        }
                    }
                });

                hideChartLoader('overallLeadStatusChart');
            }, 120);
        } else {
            // No data or all counts are zero: show fallback message
            if (window.overallLeadStatusChart && typeof window.overallLeadStatusChart.destroy === 'function') {
                try {
                    window.overallLeadStatusChart.destroy();
                } catch (e) {
                    console.log('Chart destroy error (ignored):', e);
                }
                window.overallLeadStatusChart = null;
            }

            hideChartLoader('overallLeadStatusChart');

            if (overallWrapper) {
                overallWrapper.innerHTML = '<div class="chart-fallback" style="display:flex;align-items:center;justify-content:center;min-height:200px;color:#9ca3af;font-size:16px;padding:40px;">No leads available</div>';
            }
        }

        /* ---------- Lead Source Chart ---------- */
        const leadCanvasEl = document.getElementById('leadSourceChart');
        const leadSourceWrapper = document.getElementById('leadSourceChart-wrapper');

        // Remove any previous fallback message
        if (leadSourceWrapper) {
            const prevFallback = leadSourceWrapper.querySelector('.chart-fallback');
            if (prevFallback) prevFallback.remove();
        }

        // Check if we have meaningful data (array with length > 0 and at least one count > 0)
        const hasSourceData = Array.isArray(aggregatedData?.detailed_source_counts)
            && aggregatedData.detailed_source_counts.length > 0
            && aggregatedData.detailed_source_counts.some(item => parseInt(item.count) > 0);

        if (leadCanvasEl && hasSourceData) {
            const sourceCounts = aggregatedData.detailed_source_counts;
            setTimeout(() => {
                makeChartScrollable('leadSourceChart');

                const ctx2 = leadCanvasEl.getContext ? leadCanvasEl.getContext('2d') : null;
                if (!ctx2) {
                    console.error('leadSourceChart canvas context not available');
                    hideChartLoader('leadSourceChart');
                    return;
                }

                if (window.leadSourceChart && typeof window.leadSourceChart.destroy === 'function') {
                    window.leadSourceChart.destroy();
                }

                const labels2 = sourceCounts.map(item => item.source_of_lead || 'Unknown');
                const dataVals2 = sourceCounts.map(item => parseInt(item.count, 10) || 0);
                const bg2 = labels2.map((_, i) => DEFAULT_PALETTE[i % DEFAULT_PALETTE.length]);

                window.leadSourceChart = new Chart(ctx2, {
                    type: 'doughnut',
                    data: {
                        labels: labels2,
                        datasets: [{
                            data: dataVals2,
                            backgroundColor: bg2,
                            borderColor: '#ffffff',
                            borderWidth: 2
                        }]
                    },
                    options: {
                        ...mobileOptions,
                        plugins: {
                            ...mobileOptions.plugins,
                            legend: {
                                ...mobileOptions.plugins.legend,
                                labels: {
                                    ...((mobileOptions.plugins && mobileOptions.plugins.legend && mobileOptions.plugins.legend.labels) || {}),
                                    color: colors.textColor,
                                    generateLabels: function (chart) {
                                        const data = chart.data;
                                        if (!data || !data.labels || !data.datasets || !data.datasets.length) return [];
                                        const dataset = data.datasets[0];
                                        const currentColors = (typeof getChartColors === 'function') ? getChartColors() : { textColor: '#333' };
                                        return data.labels.map((label, i) => {
                                            const value = dataset.data[i] || 0;
                                            return {
                                                text: `${label} (${value})`,
                                                fillStyle: Array.isArray(dataset.backgroundColor) ? dataset.backgroundColor[i] : dataset.backgroundColor,
                                                strokeStyle: '#fff',
                                                fontColor: currentColors.textColor,
                                                lineWidth: 1,
                                                hidden: false,
                                                index: i
                                            };
                                        });
                                    }
                                }
                            }
                        }
                    }
                });

                hideChartLoader('leadSourceChart');
            }, 200);
        } else {
            // No data or all counts are zero: show fallback message
            if (window.leadSourceChart && typeof window.leadSourceChart.destroy === 'function') {
                try {
                    window.leadSourceChart.destroy();
                } catch (e) {
                    console.log('Chart destroy error (ignored):', e);
                }
                window.leadSourceChart = null;
            }

            hideChartLoader('leadSourceChart');

            if (leadSourceWrapper) {
                leadSourceWrapper.innerHTML = '<div class="chart-fallback" style="display:flex;align-items:center;justify-content:center;min-height:200px;color:#9ca3af;font-size:16px;padding:40px;">No lead sources available</div>';
            }
        }

    } catch (err) {
        console.error('initializePopupCharts error:', err);
        hideChartLoader('overallLeadStatusChart');
        hideChartLoader('leadSourceChart');
    }
};


// Apply optimizations on window resize
let resizeTimer;
window.addEventListener('resize', function () {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(function () {
        applyMobileChartOptimizations();

        // Reinitialize charts if switching between mobile/desktop
        if (statusChart) statusChart.resize();
        if (sourceChart) sourceChart.resize();
        if (overallLeadStatusChart) overallLeadStatusChart.resize();
        if (leadSourceChart) leadSourceChart.resize();
    }, 250);
});

// Apply on load
document.addEventListener('DOMContentLoaded', function () {
    setTimeout(applyMobileChartOptimizations, 1000);
});


// Initialize the application
function initDashboard() {
    // Initialize back button
    const backButtonContainer = document.getElementById('backToMyDashboard');
    if (backButtonContainer) {
        // Try to find the actual button element inside the container
        const backButton = backButtonContainer.querySelector('.floating-btn') ||
            backButtonContainer.querySelector('button') ||
            backButtonContainer.querySelector('a') ||
            backButtonContainer; // Fallback to the container itself

        if (backButton) {
            // Remove any existing listeners first (using a wrapper to ensure it works)
            const clickHandler = function (e) {
                e.preventDefault();
                e.stopPropagation();
                goBackToMyDashboard();
            };

            // Store the handler reference
            backButton._clickHandler = clickHandler;

            // Add the event listener
            backButton.addEventListener('click', clickHandler, true);
            backButtonContainer.setAttribute('data-initialized', 'true');
        }
    }

    // Fetch assigned users for dropdown
    const namesSelect = document.getElementById('namesSelect') || document.getElementById('name-select');

    // Silently handle missing select element (may not exist on all pages)
    // Only proceed with namesSelect-dependent code if element exists
    if (namesSelect) {

        function populateDropdown(users) {
            console.log('=== DEBUGGING USER TYPES ===');
            console.log('populateDropdown called with users:', users);

            // Store original user data with correct types BEFORE any processing
            originalUsersData = users.map(user => ({
                ...user,
                user_type: user.user_type ? user.user_type.toString().toLowerCase().trim() : 'user'
            }));

            // Also store in allUsersData for backward compatibility
            allUsersData = [...originalUsersData];

            // Update searchable select if it exists
            if (window.searchableNameSelect && window.searchableNameSelect.refresh) {
                window.searchableNameSelect.refresh();
            }

            // Legacy support - update traditional select if it exists
            const namesSelect = document.getElementById('namesSelect') || document.getElementById('name-select');
            if (namesSelect && users) {
                const currentValue = getSelectedUserValue();

                namesSelect.innerHTML = '<option value="">Select a Name</option>';
                users.forEach(user => {
                    const opt = document.createElement("option");
                    opt.value = user.tablename;
                    opt.textContent = user.username;

                    // Clean and normalize the user_type
                    let userType = 'user';
                    if (user.user_type) {
                        userType = user.user_type.toString().toLowerCase().trim();
                        if (userType === 'm' || userType === 'manager') userType = 'manager';
                        else if (userType === 'c' || userType === 'ceo') userType = 'ceo';
                        else if (userType === 'u' || userType === 'user') userType = 'user';
                    }

                    opt.setAttribute('data-user-type', userType);
                    namesSelect.appendChild(opt);
                });

                if (currentValue) {
                    namesSelect.value = currentValue;
                }
            }

            // Update searchable select with current value if it exists
            if (currentValue && window.searchableNameSelect) {
                window.searchableNameSelect.setValue(currentValue);

                // Add change event listener for backward compatibility
                if (!namesSelect.hasAttribute('data-listener-added')) {
                    namesSelect.addEventListener('change', function () {
                        // Sync with currentlySelectedUser when traditional select changes
                        currentlySelectedUser = this.value;
                        if (typeof handleNamesSelectChange === 'function') {
                            handleNamesSelectChange();
                        }
                    });
                    namesSelect.setAttribute('data-listener-added', 'true');
                }
            }
        }




        if (namesSelect) {
            console.log('Fetching data from dashboard_data.php...');
            fetchData("dashboard_data.php").then(data => {
                console.log('API Response:', data);
                console.log('Status:', data.status);
                console.log('Assigned users:', data.assigned_users);

                if (data.status === "success" && data.assigned_users) {
                    console.log('Calling populateDropdown with', data.assigned_users.length, 'users');
                    populateDropdown(data.assigned_users);
                } else {
                    console.log('API response failed or no assigned_users:', {
                        status: data.status,
                        hasAssignedUsers: !!data.assigned_users
                    });
                }
            }).catch(error => {
                console.error('Error fetching data:', error);
            });

            if (namesSelect) {
                namesSelect.addEventListener('change', handleNamesSelectChange);
            }
        }
    } // End of if (namesSelect) block - silently skip if element doesn't exist

    const monthSelect = document.getElementById("monthSelect");
    const yearSelect = document.getElementById("yearSelect");
    const monthPicker = document.getElementById("monthPicker");

    if (monthSelect && yearSelect) {
        monthSelect.addEventListener("change", handleMonthYearChange);
        yearSelect.addEventListener("change", handleMonthYearChange);
    }

    if (monthPicker) {
        monthPicker.addEventListener("change", handleMonthPickerChange);
    }

    // Set up popup event listeners
    const popup = document.getElementById('popup');
    const hierarchyPopup = document.getElementById('hierarchyPopup');
    const dashboardPopup = document.getElementById('dashboardPopup');

    if (popup) {
        popup.addEventListener('click', function (e) {
            if (e.target === this) closePopup();
        });
    }

    if (hierarchyPopup) {
        hierarchyPopup.addEventListener('click', function (e) {
            if (e.target === this) closeHierarchyPopup();
        });
    }

    if (dashboardPopup) {
        dashboardPopup.addEventListener('click', function (e) {
            if (e.target === this) closeDashboard();
        });
    }

    // Close popups with Escape key
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closePopup();
            closeHierarchyPopup();
            closeDashboard();
        }
    });

    // Event listeners for buttons
    const cancelRangeBtn = document.getElementById("cancelRangeBtn");
    if (cancelRangeBtn) {
        cancelRangeBtn.addEventListener("click", handleCustomRangeCancel);
    }

    // Initial data load
    const now = new Date();
    const defaultMonth = (monthSelect && monthSelect.value) ? monthSelect.value : (now.getMonth() + 1);
    const defaultYear = (yearSelect && yearSelect.value) ? yearSelect.value : now.getFullYear();


    setTimeout(() => {
        initializeMetricLabels();
        currentSelectedMonth = defaultMonth;
        currentSelectedYear = defaultYear;
        updateMetricLabels(currentSelectedMonth, currentSelectedYear);
    }, 100);

    loadDashboardData(defaultMonth, defaultYear);

    // Add window resize event listener for chart responsiveness
    let resizeTimeout;
    window.addEventListener('resize', function () {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(function () {
            if (statusChart || sourceChart || overallLeadStatusChart || leadSourceChart) {
                updateChartTheme();
            }
        }, 250);
    });

    // Load total user data for user info popup
    loadUserTotalData();
    setupDateRangeAutoApply();
}

// ── Dynamic status-count polling ──────────────────────────────────────────────
// Polls dashboard_data.php every 30 seconds and updates only the 4 status count
// elements (Pending / Follow Up / Fix Site Visit / Site Visit Done) plus the
// progress bar, without triggering a full dashboard re-render.

let _statusPollInterval = null;
const STATUS_POLL_MS = 30000; // 30 seconds

function _buildStatusPollUrl() {
    const monthSelect = document.getElementById('monthSelect');
    const yearSelect  = document.getElementById('yearSelect');
    const startDateEl = document.getElementById('startDate');
    const endDateEl   = document.getElementById('endDate');

    const params = { date_column: getActiveDateColumn() };

    // Honour custom date range when active
    if (lastStartDate && lastEndDate) {
        params.start_date = lastStartDate;
        params.end_date   = lastEndDate;
    } else if (monthSelect && monthSelect.value === 'custom' && startDateEl?.value && endDateEl?.value) {
        params.start_date = startDateEl.value;
        params.end_date   = endDateEl.value;
    } else {
        params.month = (monthSelect && monthSelect.value) ? monthSelect.value : (new Date().getMonth() + 1);
        params.year  = (yearSelect  && yearSelect.value)  ? yearSelect.value  : new Date().getFullYear();
    }

    // Carry through the currently selected user (if any)
    const selectedUser = (typeof syncUserSelectionState === 'function')
        ? syncUserSelectionState()
        : (currentlySelectedUser || (typeof getSelectedUserValue === 'function' ? getSelectedUserValue() : ''));
    if (selectedUser) params.user_id = selectedUser;

    return buildDashboardUrl('dashboard_data.php', params);
}

function _refreshStatusCounts() {
    // Do not poll if the tab is hidden to save resources
    if (document.visibilityState === 'hidden') return;

    const url = _buildStatusPollUrl();

    fetch(url, { cache: 'no-store' })
        .then(res => res.json())
        .then(data => {
            if (data && data.status === 'success') {
                if (typeof updateStatusCounts === 'function') updateStatusCounts(data);
                if (typeof updateProgressBar  === 'function') updateProgressBar(data);
            }
        })
        .catch(err => console.warn('[StatusPoll] fetch error:', err));
}

function startStatusPolling() {
    if (_statusPollInterval) return; // already running
    _statusPollInterval = setInterval(_refreshStatusCounts, STATUS_POLL_MS);
}

function stopStatusPolling() {
    if (_statusPollInterval) {
        clearInterval(_statusPollInterval);
        _statusPollInterval = null;
    }
}

// Pause polling while tab is hidden, resume when visible again
document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'visible') {
        // Immediately refresh on return, then resume the interval
        _refreshStatusCounts();
        startStatusPolling();
    } else {
        stopStatusPolling();
    }
});

document.addEventListener("DOMContentLoaded", function () {
    // Skip dashboard initialization on leads page - it has its own optimized API calls
    const pathname = window.location.pathname;
    if (pathname.includes('user_lead')) {
        console.log('Skipping dashboard_data.js initialization on leads page');
        return;
    }

    try {
        initializeMetricLabels();
        initDashboard();
        setupDateRangeAutoApply();
        startStatusPolling(); // begin dynamic status-count updates
    } catch (error) {
        // Silently handle any initialization errors, especially related to missing elements
        const errorMsg = (error.message || '').toLowerCase();
        if (!errorMsg.includes('names select element not found') &&
            !errorMsg.includes('name-select') &&
            !errorMsg.includes('namesselect')) {
            // Only log non-expected errors
            console.error('Dashboard initialization error:', error);
        }
    }

    // Initialize smart scrolling
    initChartScrolling();

    // Additional initialization for back button (ensure it works)
    setTimeout(() => {
        const backButtonContainer = document.getElementById('backToMyDashboard');
        if (backButtonContainer && !backButtonContainer.hasAttribute('data-initialized')) {
            const backButton = backButtonContainer.querySelector('.floating-btn') ||
                backButtonContainer.querySelector('button') ||
                backButtonContainer.querySelector('a') ||
                backButtonContainer;

            if (backButton) {
                // Create click handler
                const clickHandler = function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    goBackToMyDashboard();
                };

                // Store the handler reference
                backButton._clickHandler = clickHandler;

                // Add the event listener with capture phase
                backButton.addEventListener('click', clickHandler, true);
                backButtonContainer.setAttribute('data-initialized', 'true');
            }
        }
    }, 500);
});


function initializeMetricLabels() {
    const metricLabels = document.querySelectorAll('.metric-label');
    metricLabels.forEach(label => {
        // Only set data-original if it's not already set
        if (!label.hasAttribute('data-original')) {
            const originalText = label.textContent;
            label.setAttribute('data-original', originalText);
        }
    });
}



// Enhanced filterUserCards function
function filterUserCards() {
    const usersGrid = document.getElementById('usersGrid');
    if (!usersGrid) return;

    const selectedValues = Array.from(selectedUsers || []);
    const showAll = selectedValues.length === 0;
    const isSingleSelection = selectedValues.length === 1;
    const isMultipleSelection = selectedValues.length > 1;

    console.log('Filtering users - Selected:', selectedValues, 'Show All:', showAll);

    // Reset grid display
    usersGrid.style.display = '';
    usersGrid.style.gridTemplateColumns = '';
    usersGrid.style.gap = '';

    // Apply grid display for ALL cases EXCEPT single selection
    if (showAll || isMultipleSelection) {
        usersGrid.style.display = 'grid';
        usersGrid.style.gridTemplateColumns = 'repeat(auto-fill, minmax(270px, 1fr))';
        usersGrid.style.gap = '20px';
    }

    // Update floating button and filter summary
    if (hasAnyActiveDashboardFilters()) {
        addFloatingClearFilterButton();
        createFilterSummaryPanel();
    } else {
        const floatingButton = document.querySelector('.floating-clear-filter');
        if (floatingButton) floatingButton.remove();

        const summaryPanel = document.querySelector('.filter-summary-panel');
        if (summaryPanel) summaryPanel.remove();
    }

    // Handle different selection scenarios
    if (showAll) {
        showAllUsersNormalGrid();
        resetChartsToOriginalData();
        return;
    }

    if (isSingleSelection) {
        const userTablename = selectedValues[0];
        const selectedUser = allUsersData.find(u => String(u.tablename) === String(userTablename));

        if (selectedUser) {
            console.log(`Single user selected: ${selectedUser.username || selectedUser.name}`);
            showTeamView(selectedUser);
            return;
        }
    }

    // Multiple selections - ADD CHART UPDATES HERE
    if (isMultipleSelection) {
        console.log('Multiple users selected, updating charts...');
        showNormalFilteredView(selectedValues);
    }
}



function createRoleFilterDropdown() {
    return `
        <div class="role-filter-container" style="position: relative;">
            <button class="control-btn role-filter-btn" onclick="toggleRoleFilter(event)">
                Filter by Role ▾
            </button>
            <div class="role-filter-options" style="
                position: absolute;
                top: 100%;
                left: 0;
                background: white;
                border: 1px solid #d1d5db;
                border-radius: 6px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.1);
                z-index: 1001;
                display: none;
                min-width: 140px;
                overflow-y: scroll;
                max-height: 150px;
            ">
                <div class="role-option" onclick="selectRole('promoter')" style="padding: 8px 12px; cursor: pointer; border-bottom: 1px solid #f1f5f9;">
                    <span style="color: #7c2d12;">●</span> Promoter
                </div>
                <div class="role-option" onclick="selectRole('business head')" style="padding: 8px 12px; cursor: pointer; border-bottom: 1px solid #f1f5f9;">
                    <span style="color: #dc2626;">●</span> Business Head
                </div>
                <div class="role-option" onclick="selectRole('manager')" style="padding: 8px 12px; cursor: pointer; border-bottom: 1px solid #f1f5f9;">
                    <span style="color: #2563eb;">●</span> Manager
                </div>
                <div class="role-option" onclick="selectRole('team lead')" style="padding: 8px 12px; cursor: pointer; border-bottom: 1px solid #f1f5f9;">
                    <span style="color: #7c3aed;">●</span> Team Lead
                </div>
                <div class="role-option" onclick="selectRole('user')" style="padding: 8px 12px; cursor: pointer;">
                    <span style="color: #059669;">●</span> User
                </div>
            </div>
        </div>
    `;
}

// Add this CSS for the role filter
function addRoleFilterStyles() {
    if (!document.getElementById('roleFilterStyles')) {
        const style = document.createElement('style');
        style.id = 'roleFilterStyles';
        style.textContent = `
            .role-filter-container {
                position: relative;
                display: inline-block;
            }
            
            .role-filter-btn {
                background: white;
                border: 1px solid #d1d5db;
                border-radius: 4px;
                padding: 4px 8px;
                font-size: 11px;
                cursor: pointer;
                transition: all 0.2s ease;
                color: #6b7280;
            }
            
            .role-filter-btn:hover {
                background: #f3f4f6;
                border-color: #9ca3af;
            }
            
            .role-filter-options {
                position: absolute;
                top: 100%;
                left: 0;
                background: white;
                border: 1px solid #d1d5db;
                border-radius: 6px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.1);
                z-index: 1001;
                min-width: 120px;
            }
            
            .role-option {
                padding: 8px 12px;
                cursor: pointer;
                border-bottom: 1px solid #f1f5f9;
                transition: background-color 0.2s ease;
                display: flex;
                align-items: center;
                gap: 6px;
            }
            
            .role-option:hover {
                background-color: #f8fafc;
            }
            
            .role-option:last-child {
                border-bottom: none;
            }
            
            .role-filter-options.show {
                display: block;
                animation: fadeIn 0.2s ease;
            }
            @media (max-width: 425px){
                .role-filter-options.show {
                    left: -60px !important;
                }
            }
            
            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(-5px); }
                to { opacity: 1; transform: translateY(0); }
            }
            
            /* Dark theme support */
            [data-theme="dark"] .role-filter-btn {
                background: #374151;
                border-color: #4b5563;
                color: #f9fafb;
            }
            
            [data-theme="dark"] .role-filter-options {
                background: #454545 !important;
                border-color: #374151;
            }
            
            [data-theme="dark"] .role-option:hover {
                background-color: rgba(255,255,255,0.2) !important;
            }
        `;
        document.head.appendChild(style);
    }
}

// Toggle role filter dropdown
function toggleRoleFilter(event) {
    event.stopPropagation();
    const options = document.querySelector('.role-filter-options');
    if (options) {
        const isShowing = options.style.display === 'block';
        options.style.display = isShowing ? 'none' : 'block';
        options.classList.toggle('show', !isShowing);
    }
}

// Select users by role
function selectRole(role) {
    console.log(`Selecting all ${role}s`);

    const users = getUsersData();

    // Normalize role for comparison
    function normalizeRole(userRole) {
        const normalized = String(userRole || 'user').toLowerCase().trim();
        // Handle different variations of role names
        if (normalized === 'ceo' || normalized === 'c') return 'promoter';
        if (normalized === 'business_head' || normalized === 'businesshead') return 'business head';
        if (normalized === 'team_lead' || normalized === 'teamlead') return 'team lead';
        return normalized;
    }

    // First, remove all users of other roles from selection
    users.forEach(user => {
        const normalizedUserType = normalizeRole(user.user_type);
        if (normalizedUserType !== role) {
            selectedUsers.delete(user.value);
        }
    });

    // Now add all users of the selected role
    const roleUsers = users.filter(user => {
        const normalizedUserType = normalizeRole(user.user_type);
        return normalizedUserType === role;
    });

    if (roleUsers.length === 0) {
        console.log(`No ${role}s found`);
        console.log(`Available user types:`, users.map(u => normalizeRole(u.user_type)));
        return;
    }

    // Add all users of this role to selection
    roleUsers.forEach(user => {
        selectedUsers.add(user.value);
    });

    // Update checkboxes in dropdown
    updateRoleCheckboxes(role);

    // Update UI
    updateSelectedTags();
    updateDropdownPlaceholder();

    // NOTE: Do NOT call filterUserCards() - let user click Apply to apply the filter
    // This was automatically applying filters which is not desired behavior

    // Close role filter dropdown
    const options = document.querySelector('.role-filter-options');
    if (options) {
        options.style.display = 'none';
        options.classList.remove('show');
    }

    console.log(`Selected ${roleUsers.length} ${role}s (cleared previous role selections) - Click Apply to apply filter`);
}

// Update checkboxes for selected role - UPDATED VERSION
function updateRoleCheckboxes(selectedRole) {
    const options = document.querySelectorAll('.dropdown-option');
    options.forEach(option => {
        const checkbox = option.querySelector('.option-checkbox');
        const value = checkbox.value;
        const user = getUsersData().find(u => u.value === value);

        if (user) {
            const userType = String(user.user_type || 'user').toLowerCase().trim();
            const shouldBeSelected = userType === selectedRole;

            checkbox.checked = shouldBeSelected;
            if (shouldBeSelected) {
                option.classList.add('selected');
            } else {
                option.classList.remove('selected');
            }
        }
    });
}

// Close role filter when clicking outside
document.addEventListener('click', function (event) {
    const roleFilter = document.querySelector('.role-filter-container');
    if (roleFilter && !roleFilter.contains(event.target)) {
        const options = document.querySelector('.role-filter-options');
        if (options) {
            options.style.display = 'none';
            options.classList.remove('show');
        }
    }
});



// Helper function to show all users in normal grid
function showAllUsersNormalGrid() {
    const usersGrid = document.getElementById('usersGrid');
    if (!usersGrid) return;

    // Set view mode to normal
    currentViewMode = 'normal';
    currentHierarchyUser = null;
    console.log(`🔄 View mode set to NORMAL (show all)`);

    console.log('Showing all users in grid layout');

    // APPLY GRID DISPLAY FOR ALL USERS
    usersGrid.style.display = 'grid';
    usersGrid.style.gridTemplateColumns = 'repeat(auto-fill, minmax(270px, 1fr))';
    usersGrid.style.gap = '20px';

    // Clear team summary if exists
    clearTeamSummary();

    // Regenerate normal user cards
    generateUserCards();

    // Reset charts to show all data
    resetChartsToOriginalData();

    // Update stats after user cards are rendered
    // Only update if no filters are active (this function is called when showing all users)
    const hasUserFilter = selectedUsers && selectedUsers.size > 0;
    const hasProjectFilter = selectedProjectNames && selectedProjectNames.size > 0;

    if (!hasUserFilter && !hasProjectFilter) {
        console.log('No filters - updating stats from visible cards');
        setTimeout(() => {
            updateStatsFromVisibleCards();
        }, Math.min((allUsersData?.length || 0) * 50, 800));
    } else {
        console.log(' Filters still active - skipping stats update in showAllUsersNormalGrid');
    }

    updateVisibleUserCount(allUsersData.length, allUsersData.length);
}

function recalculateTotalsFromVisibleCards() {
    const visibleCards = document.querySelectorAll('.user-card:not([style*="display: none"])');

    let totalLeads = 0;
    let totalBookings = 0;
    let totalEOI = 0;
    let totalCancelled = 0;

    visibleCards.forEach(card => {
        // Extract numeric values from the card
        const leadsElement = card.querySelector('.leads-count');
        const bookingsElement = card.querySelector('.bookings-count');
        const eoiElement = card.querySelector('.eoi-count');
        const cancelledElement = card.querySelector('.cancelled-count');

        if (leadsElement) totalLeads += parseInt(leadsElement.textContent) || 0;
        if (bookingsElement) totalBookings += parseInt(bookingsElement.textContent) || 0;
        if (eoiElement) totalEOI += parseInt(eoiElement.textContent) || 0;
        if (cancelledElement) totalCancelled += parseInt(cancelledElement.textContent) || 0;
    });

    return {
        totalUsers: visibleCards.length,
        totalLeads,
        totalBookings,
        totalEOI,
        totalCancelledBookings: totalCancelled
    };
}
// === HIERARCHY MANAGEMENT FUNCTIONS ===

// Build complete hierarchy (upward and downward) for a selected user
async function buildCompleteHierarchy(selectedUser) {
    try {
        // Get all users data to build hierarchy
        const usersResponse = await fetch('dashboard_data.php');
        const usersData = await usersResponse.json();

        if (usersData.status !== 'success' || !usersData.assigned_users) {
            console.error('Failed to fetch users data for hierarchy');
            return { upward: [], downward: [], all: [] };
        }

        const allUsers = usersData.assigned_users;
        const userMap = new Map();

        // Create a map for quick user lookup
        allUsers.forEach(user => {
            userMap.set(user.tablename, user);
        });

        console.log(`%c=== USER MAP CREATED ===`, 'color: purple; font-weight: bold');
        console.log(`Total users in map: ${userMap.size}`);
        console.log(`User map keys:`, Array.from(userMap.keys()));

        // Get the complete user data from database (has assign_user field)
        const selectedUserFromDB = userMap.get(selectedUser.tablename);
        console.log(`Selected user in map:`, selectedUserFromDB ? 'YES' : 'NO');

        if (!selectedUserFromDB) {
            console.log(`%cSelected user not found in database map!`, 'color: red; font-weight: bold');
            return { upward: [], downward: [], all: [] };
        }

        console.log(`%c=== USER DATA COMPARISON ===`, 'color: orange; font-weight: bold');
        console.log(`Filtered user data (missing hierarchy):`, selectedUser);
        console.log(`Database user data (complete):`, selectedUserFromDB);

        console.log(`Building hierarchy for user - Using database data:`, selectedUserFromDB);

        console.log(`Total users in system: ${allUsers.length}`);
        console.log(`Sample user data - First few users with all fields:`, allUsers.slice(0, 3));

        // Check what fields are commonly available in the users data
        const commonFields = new Set();
        allUsers.slice(0, 10).forEach(user => {
            Object.keys(user).forEach(key => commonFields.add(key));
        });
        console.log(`Common fields in user data:`, Array.from(commonFields).sort());

        // Build upward hierarchy using complete database user data
        const upwardHierarchy = buildUpwardHierarchy(selectedUserFromDB, userMap, allUsers);

        // Build downward hierarchy (subordinates) using complete database user data
        const downwardHierarchy = buildDownwardHierarchy(selectedUserFromDB, userMap, allUsers);

        // Combine all for complete team view
        const completeHierarchy = [...upwardHierarchy, ...downwardHierarchy];

        // Remove duplicates while preserving order
        const uniqueHierarchy = [];
        const seenIds = new Set();

        completeHierarchy.forEach(user => {
            if (!seenIds.has(user.tablename)) {
                seenIds.add(user.tablename);
                uniqueHierarchy.push(user);
            }
        });

        console.log(`Complete hierarchy for ${selectedUserFromDB.username || selectedUserFromDB.name}:`, {
            upward: upwardHierarchy.length,
            downward: downwardHierarchy.length,
            total: uniqueHierarchy.length
        });

        // Temporary on-page debugging display
        const debugInfo = document.getElementById('debug-info');
        if (debugInfo) {
            debugInfo.style.display = 'block';
            debugInfo.innerHTML = `
                <strong>Hierarchy Debug for ${selectedUserFromDB.username || selectedUserFromDB.name}:</strong><br>
                Upward: ${upwardHierarchy.length} users: ${upwardHierarchy.map(u => u.username || u.name).join(', ')}<br>
                Downward: ${downwardHierarchy.length} users: ${downwardHierarchy.map(u => u.username || u.name).join(', ')}<br>
                Selected User assign_user: ${selectedUserFromDB.assign_user || 'NULL'}<br>
                Database vs Filtered User: DB has assign_user=${selectedUserFromDB.assign_user}, Filtered has assign_user=${selectedUser.assign_user}<br>
                Total hierarchy: ${uniqueHierarchy.length}
            `;
        }

        return {
            upward: upwardHierarchy,
            downward: downwardHierarchy,
            all: uniqueHierarchy
        };

    } catch (error) {
        console.error('Error building complete hierarchy:', error);
        return { upward: [], downward: [], all: [] };
    }
}

// Build upward hierarchy (managers, business heads, promoters)
function buildUpwardHierarchy(selectedUser, userMap, allUsers) {
    const upwardChain = [];
    const visited = new Set();
    let currentUser = selectedUser;
    let depth = 0;
    const maxDepth = 10; // Prevent infinite loops

    console.log(`%c=== BUILDING UPWARD HIERARCHY ===`, 'color: blue; font-weight: bold');
    console.log(`Target user: ${selectedUser.name || selectedUser.username} (${selectedUser.tablename})`);
    console.log(`Selected user assign_user field:`, selectedUser.assign_user);
    console.log(`User map has ${userMap.size} users`);

    // Log a few user map entries to see the structure
    let sampleUsers = [];
    let count = 0;
    for (let [key, user] of userMap) {
        sampleUsers.push({
            tablename: key,
            name: user.name || user.username,
            assign_user: user.assign_user,
            user_type: user.user_type
        });
        if (++count >= 3) break;
    }
    console.log(`Sample users from map:`, sampleUsers);

    while (currentUser && depth < maxDepth) {
        console.log(`%c--- Depth ${depth} ---`, 'color: green');
        console.log(`Current user: ${currentUser.name || currentUser.username} (${currentUser.tablename})`);

        if (visited.has(currentUser.tablename)) {
            console.log(`%cBreaking loop - already visited ${currentUser.tablename}`, 'color: red');
            break;
        }
        visited.add(currentUser.tablename);

        // Add current user to chain (except the selected user itself)
        if (depth > 0) {
            console.log(`%cAdding to upward chain: ${currentUser.name || currentUser.username} (${currentUser.tablename})`, 'color: green');
            upwardChain.push(currentUser);
        }

        // Find the manager/superior of current user - check multiple possible fields
        const assignedTo = currentUser.assign_user || currentUser.assigned_to || currentUser.manager || currentUser.parent_id;
        console.log(`Checking manager fields:`, {
            assign_user: currentUser.assign_user,
            assigned_to: currentUser.assigned_to,
            manager: currentUser.manager,
            parent_id: currentUser.parent_id,
            final_assignedTo: assignedTo
        });

        if (!assignedTo) {
            console.log(`%cNo assigned user found - reached top of hierarchy`, 'color: orange');
            break;
        }

        // Handle comma-separated assign_user field (multiple managers)
        const managerIds = assignedTo.includes(',') ?
            assignedTo.split(',').map(id => id.trim()) :
            [assignedTo.trim()];

        console.log(`Split manager IDs:`, managerIds);

        // Find the first available manager (or use the highest-level one)
        let nextUser = null;
        for (const managerId of managerIds) {
            const candidateManager = userMap.get(managerId);
            if (candidateManager) {
                console.log(`Found manager: ${candidateManager.name || candidateManager.username} (${candidateManager.tablename})`);
                nextUser = candidateManager;
                break; // Use the first found manager for upward hierarchy
            }
        }

        if (!nextUser) {
            console.log(`%cNo managers found in user map for IDs: ${managerIds.join(', ')}`, 'color: red');
            console.log(`Available user IDs in map:`, Array.from(userMap.keys()).slice(0, 10));
            break;
        }

        currentUser = nextUser;
        depth++;
    }

    console.log(`Upward hierarchy built: ${upwardChain.length} levels`, upwardChain.map(u => u.name || u.username));
    return upwardChain;
}

// Build downward hierarchy (subordinates at all levels)
function buildDownwardHierarchy(selectedUser, userMap, allUsers) {
    const downwardChain = [];
    const visited = new Set();

    console.log(`=== BUILDING DOWNWARD HIERARCHY ===`);
    console.log(`Target manager: ${selectedUser.name || selectedUser.username} (${selectedUser.tablename})`);
    console.log(`Total users to check: ${allUsers.length}`);

    // Debug: Show all users and their assign_user values
    console.log(`All users and their assignments:`);
    allUsers.forEach((user, idx) => {
        if (idx < 10) { // Show first 10 users
            console.log(`  ${user.tablename}: ${user.username || user.name} (${user.user_type}) -> assign_user: ${user.assign_user}`);
        }
    });

    // Find direct reports - check if selectedUser's tablename is in assign_user field
    const directReports = allUsers.filter(user => {
        if (user.tablename === selectedUser.tablename) return false; // Skip self

        const assignUsers = user.assign_user ?
            user.assign_user.split(',').map(id => id.trim()) :
            [];
        const isDirectReport = assignUsers.includes(selectedUser.tablename);

        if (isDirectReport) {
            console.log(`Found direct report: ${user.name || user.username} (${user.tablename}) - assign_user: ${user.assign_user}`);
        }

        return isDirectReport;
    });

    console.log(`Direct reports found: ${directReports.length}`);

    // Recursively find all subordinates
    function findAllSubordinates(manager, level = 0) {
        if (level > 10 || visited.has(manager.tablename)) return; // Prevent infinite recursion
        visited.add(manager.tablename);

        const subordinates = allUsers.filter(user => {
            if (user.tablename === manager.tablename || visited.has(user.tablename)) return false;

            const assignUsers = user.assign_user ?
                user.assign_user.split(',').map(id => id.trim()) :
                [];
            return assignUsers.includes(manager.tablename);
        });

        subordinates.forEach(subordinate => {
            downwardChain.push({
                ...subordinate,
                hierarchyLevel: level + 1,
                directManager: manager.tablename
            });

            // Recursively find subordinates of this subordinate
            findAllSubordinates(subordinate, level + 1);
        });
    }

    // Start the recursive search
    findAllSubordinates(selectedUser);

    console.log(`Downward hierarchy built: ${downwardChain.length} subordinates`);
    downwardChain.forEach((sub, idx) => {
        console.log(`  ${idx + 1}. ${sub.name || sub.username} (${sub.user_type}) - Level ${sub.hierarchyLevel}`);
    });

    return downwardChain;
}

// Get role hierarchy level for sorting
function getRoleHierarchyLevel(userType) {
    const hierarchyLevels = {
        'promoter': 1,
        'business head': 2,
        'business_head': 2,  // Handle underscore version
        'businesshead': 2,   // Handle cases without space
        'manager': 3,
        'team lead': 4,
        'team_lead': 4,      // Handle underscore version
        'teamlead': 4,       // Handle cases without space
        'team leader': 4,    // Alternative naming
        'user': 5
    };

    const normalizedType = (userType || 'user').toLowerCase().trim().replace(/\s+/g, ' ');
    let level = hierarchyLevels[normalizedType];

    // If not found, try without spaces
    if (!level) {
        const noSpaceType = normalizedType.replace(/\s+/g, '');
        level = hierarchyLevels[noSpaceType];
    }

    // Default to user level if still not found
    level = level || 5;

    console.log(`Role hierarchy level for "${userType}" -> normalized: "${normalizedType}" -> level: ${level}`);

    return level;
}

// Sort users by hierarchy and role
function sortUsersByHierarchy(users) {
    console.log('Sorting users by hierarchy. Input users:', users.map(u => ({
        name: u.username || u.name,
        type: u.user_type,
        level: getRoleHierarchyLevel(u.user_type),
        hierarchyLevel: u.hierarchyLevel
    })));

    const sorted = users.sort((a, b) => {
        // First sort by hierarchy level (1=promoter, 2=business head, 3=manager, etc.)
        const levelA = getRoleHierarchyLevel(a.user_type);
        const levelB = getRoleHierarchyLevel(b.user_type);

        console.log(`Comparing ${a.username || a.name} (${a.user_type}, level ${levelA}) vs ${b.username || b.name} (${b.user_type}, level ${levelB})`);

        if (levelA !== levelB) {
            return levelA - levelB;
        }

        // Then by hierarchyLevel if available (for subordinates)
        const hierLevelA = a.hierarchyLevel || 0;
        const hierLevelB = b.hierarchyLevel || 0;

        if (hierLevelA !== hierLevelB) {
            return hierLevelA - hierLevelB;
        }

        // Finally by name
        return (a.username || a.name || '').localeCompare(b.username || b.name || '');
    });

    console.log('Sorted hierarchy result:', sorted.map(u => ({
        name: u.username || u.name,
        type: u.user_type,
        level: getRoleHierarchyLevel(u.user_type)
    })));

    return sorted;
}

// UPDATED FUNCTION: Show team view for CEO/manager with proper data fetching
async function showTeamView(leader) {
    console.log(`Showing hierarchical team view for ${leader.name} (${leader.user_type})`);

    // Set view mode to hierarchy
    currentViewMode = 'hierarchy';
    currentHierarchyUser = leader;
    console.log(` View mode set to HIERARCHY for user: ${leader.name}`);

    // Check if project filters are applied
    const selectedProjects = getSelectedProjectNames();
    const hasProjectFilters = selectedProjects && selectedProjects.length > 0;
    console.log(`Project filters applied: ${hasProjectFilters ? 'YES' : 'NO'}`, hasProjectFilters ? Array.from(selectedProjects) : []);

    const usersGrid = document.getElementById('usersGrid');
    if (!usersGrid) {
        console.error('❌ usersGrid element not found! Cannot show hierarchy view.');
        console.log('📝 Available elements with ID:', Array.from(document.querySelectorAll('[id]')).map(el => el.id));
        return;
    }
    console.log('✅ usersGrid element found, proceeding with hierarchy view generation');

    // ADD FLOATING BUTTON FOR TEAM VIEW
    addFloatingClearFilterButton();

    try {

        // Build complete hierarchy for the selected leader
        const hierarchy = await buildCompleteHierarchy(leader);

        // For all roles, show downward hierarchy (subordinates)
        // Promoters will show their business heads, managers, team leads, and users
        // Other roles will show their direct subordinates
        let hierarchyToShow = hierarchy.downward;
        console.log(`${leader.user_type || 'User'} selected - showing downward hierarchy: ${hierarchyToShow.length} members`);

        if (hierarchyToShow.length === 0) {
            console.log('No subordinates found, but still showing hierarchy view with just the selected user');
            // Instead of falling back to normal view, show hierarchy view with just the selected user
            hierarchyToShow = []; // Empty subordinates, but we'll still show the selected user in hierarchy format
        }

        // Sort users by hierarchy - only showing downward hierarchy
        const sortedHierarchy = sortUsersByHierarchy(hierarchyToShow);

        console.log(`Downward hierarchy (subordinates) for ${leader.name}:`, {
            subordinates: hierarchy.downward.length,
            showing: sortedHierarchy.length,
            hasProjectFilters: hasProjectFilters,
            willShowHierarchyView: true
        });

        // Fetch performance data for ALL users in batch (including the leader)
        const allUsersForPerformance = [leader, ...sortedHierarchy];

        // NEW: Lazy load performance data locally inside the individual rendering loops!
        const performanceDataMap = new Map();

        // Clear existing cards and generate hierarchical team cards with performance data
        const usersGrid = document.getElementById('usersGrid');
        if (usersGrid) {
            usersGrid.innerHTML = '';
            console.log(`Generating hierarchical team cards for leader + ${sortedHierarchy.length} subordinates`);
            await generateHierarchicalTeamCards(sortedHierarchy, leader, hierarchy, performanceDataMap);
        }

        // Update team summary with hierarchical context
        updateHierarchicalTeamSummary(leader, hierarchy);

        const allUsersForCharts = [leader, ...sortedHierarchy];

        // Calculate team stats (computed fallback) but DO NOT apply to DOM yet.
        const teamStats = calculateHierarchicalTeamStats(sortedHierarchy, performanceDataMap, leader);

        // Fetch server-side aggregated analytics for the team and update charts ONLY (not stats).
        // Stats are already correct from updatePopupDashboard() - updating them again causes double rendering
        fetchTeamAnalyticsData(allUsersForCharts)
            .then(teamAnalytics => {
                if (teamAnalytics && teamAnalytics.aggregated_analytics) {
                    // Use server-provided aggregated analytics as authoritative source
                    // Pass skipStatsUpdate=true because stats are already correct from updatePopupDashboard()
                    console.log('✅ Updating charts with team analytics (stats already set by updatePopupDashboard)');
                    updateChartsWithAggregatedData(teamAnalytics.aggregated_analytics, true);
                } else {
                    // No aggregated payload: fallback to client-calculated charts only
                    console.log('⚠️ No team analytics, using filtered chart data (stats already correct)');
                    updateChartsWithFilteredData(allUsersForCharts);
                    // DO NOT call updateStatsDisplay - stats are already correct from updatePopupDashboard
                }
            })
            .catch(err => {
                console.error('Error fetching team analytics:', err);
                // On error, fallback to client-calculated charts only
                console.log('⚠️ Error fetching team analytics, using filtered chart data (stats already correct)');
                updateChartsWithFilteredData(allUsersForCharts);
                // DO NOT call updateStatsDisplay - stats are already correct from updatePopupDashboard
            });

        updateVisibleUserCount(sortedHierarchy.length, allUsersData.length);

    } catch (err) {
        console.error('Error showing hierarchical team view:', err);
        // Fallback: show normal view with the leader only
        await showNormalFilteredView([leader.tablename]);
    }
}
async function fetchTeamAnalyticsData(teamMembers) {
    if (!teamMembers || teamMembers.length === 0) {
        return null;
    }

    console.log('Fetching team analytics data for:', teamMembers.length, 'users');

    // Use centralized filter helpers to build consistent query params
    const baseFilters = collectDashboardFilters({
        includeProjects: true,
        includeDates: true
    });

    const promises = [];
    const batchSize = 50;

    // Fetch data in concurrent chunks of 50 to the batch endpoint
    for (let i = 0; i < teamMembers.length; i += batchSize) {
        const batch = teamMembers.slice(i, i + batchSize);
        const userIds = batch.map(u => u.tablename).join(',');

        const filters = { ...baseFilters, user_ids: userIds, analytics: true };
        const url = buildDashboardUrl('batch_dashboard_data.php', filters);

        const promise = fetchData(url).then(result => {
            if (result && result.status === 'success' && result.data) {
                return Object.entries(result.data).map(([userId, data]) => {
                    if (data.status === 'success') {
                        return {
                            tablename: userId,
                            analytics: data.analytics,
                            leads: data.myLeads || 0,
                            bookings: data.total_bookings || 0,
                            eoi: data.total_eoi || 0,
                            revenue: data.total_revenue || 0,
                            cancelled_bookings: data.cancelled_bookings || 0,
                            status_counts: data.status_counts || {},
                            detailed_status_counts: data.analytics?.detailed_status_counts || [],
                            detailed_source_counts: data.analytics?.detailed_source_counts || []
                        };
                    }
                    return null;
                });
            }
            return [];
        }).catch(err => {
            console.error(`Error fetching analytics batch:`, err);
            return [];
        });

        promises.push(promise);
    }

    return Promise.all(promises).then(batchResults => {
        // Flatten array of arrays and filter nulls
        const validResults = batchResults.flat().filter(result => result !== null);

        if (validResults.length === 0) {
            console.log('No valid analytics data received for any team members');
            return null;
        }

        // Aggregate the results
        const aggregatedAnalytics = aggregateTeamAnalytics(validResults);

        return {
            status: 'success',
            aggregated_analytics: aggregatedAnalytics
        };
    });
}

function aggregateTeamAnalytics(teamResults) {
    const aggregated = {
        detailed_status_counts: [],
        detailed_source_counts: [],
        total_leads: 0,
        total_bookings: 0,
        total_eoi: 0,
        total_revenue: 0,
        total_canceled_bookings: 0,
        total_users: teamResults.length
    };

    const statusCountsMap = {};
    const sourceCountsMap = {};

    teamResults.forEach(teamResult => {
        // Aggregate totals
        aggregated.total_leads += parseInt(teamResult.leads) || 0;
        aggregated.total_bookings += parseInt(teamResult.bookings) || 0;
        aggregated.total_eoi += parseInt(teamResult.eoi) || 0;
        aggregated.total_revenue += parseFloat(teamResult.revenue) || 0;
        aggregated.total_canceled_bookings += parseInt(teamResult.cancelled_bookings) || 0;

        // Aggregate status counts
        if (teamResult.detailed_status_counts) {
            teamResult.detailed_status_counts.forEach(statusItem => {
                const status = statusItem.status;
                const count = parseInt(statusItem.count) || 0;
                statusCountsMap[status] = (statusCountsMap[status] || 0) + count;
            });
        }

        // Aggregate source counts
        if (teamResult.detailed_source_counts) {
            teamResult.detailed_source_counts.forEach(sourceItem => {
                const source = sourceItem.source_of_lead;
                const count = parseInt(sourceItem.count) || 0;
                sourceCountsMap[source] = (sourceCountsMap[source] || 0) + count;
            });
        }
    });

    // Convert maps to arrays
    aggregated.detailed_status_counts = Object.entries(statusCountsMap).map(([status, count]) => ({
        status,
        count
    }));

    aggregated.detailed_source_counts = Object.entries(sourceCountsMap).map(([source, count]) => ({
        source_of_lead: source,
        count
    }));

    console.log('Aggregated team analytics:', aggregated);

    return aggregated;
}



// Helper function to show normal filtered view
async function showNormalFilteredView(selectedValues) {
    console.log('Showing normal filtered view for:', selectedValues);

    // Set view mode to normal
    currentViewMode = 'normal';
    currentHierarchyUser = null;
    console.log(`🔄 View mode set to NORMAL`);

    const usersGrid = document.getElementById('usersGrid');
    if (!usersGrid) return;

    // Clear team summary and hierarchical summary
    clearTeamSummary();
    clearHierarchicalSummary();

    // ADD FLOATING BUTTON FOR MULTIPLE SELECTIONS
    if (selectedValues.length > 0) {
        addFloatingClearFilterButton();
    }

    // Get filtered users based on selections
    const filteredUsers = allUsersData.filter(user =>
        selectedValues.includes(String(user.tablename))
    );

    console.log('Filtered users found:', filteredUsers.length, 'out of', selectedValues.length, 'selected');

    if (filteredUsers.length === 0) {
        usersGrid.innerHTML = '<div class="no-users-message" style="text-align: center; padding: 40px; color: #6b7280; grid-column: 1 / -1;">No users found for the selected filters</div>';

        // Update charts to show no data
        updateChartsWithFilteredData([]);
        return;
    }

    // Calculate stats from performance data FIRST
    console.log('🔢 Calculating filtered stats with project filters...');
    const filteredStats = await calculateFilteredStats(filteredUsers);
    console.log('✅ Filtered stats calculated:', filteredStats);

    // Update stats display IMMEDIATELY with correct data
    updateStatsDisplay(filteredStats, true);
    updateVisibleUserCount(filteredUsers.length, allUsersData.length);

    // Always regenerate cards to ensure fresh performance data
    console.log('Regenerating cards for filtered users with performance data');
    usersGrid.innerHTML = '';
    await generateFilteredUserCards(filteredUsers);

    // Update charts for filtered data - THIS IS CRITICAL
    updateChartsWithFilteredData(filteredUsers);

    console.log('✅ Normal filtered view completed with chart updates');
}

// Helper function to calculate stats for filtered users using performance data
async function calculateFilteredStats(filteredUsers) {
    const performanceDataMap = await fetchBatchUserPerformanceData(filteredUsers);

    let totalLeads = 0;
    let totalBookings = 0;
    let totalEOI = 0;
    let totalCancelledBookings = 0;

    filteredUsers.forEach(user => {
        const performanceData = performanceDataMap.get(user.tablename) || {};
        totalLeads += parseInt(performanceData.myLeads || 0);
        totalBookings += parseInt(performanceData.total_bookings || 0);
        totalEOI += parseInt(performanceData.total_eoi || 0);
        totalCancelledBookings += parseInt(performanceData.cancelled_bookings || 0);
    });

    return {
        totalUsers: filteredUsers.length,
        totalLeads: totalLeads,
        totalBookings: totalBookings,
        totalEOI: totalEOI,
        totalCancelledBookings: totalCancelledBookings
    };
}

// Helper function to calculate team stats
function calculateTeamStats(teamMembers, performanceData = {}) {
    let totalLeads = 0;
    let totalBookings = 0;
    let totalEOI = 0;
    let totalCancelledBookings = 0;

    teamMembers.forEach(member => {
        const memberPerformance = performanceData[member.tablename] || {};
        totalLeads += memberPerformance.leads || 0;
        totalBookings += memberPerformance.bookings || 0;
        totalEOI += memberPerformance.eoi || 0;
        totalCancelledBookings += memberPerformance.cancelled_bookings || 0;
    });

    return {
        totalUsers: teamMembers.length,
        totalLeads: totalLeads,
        totalBookings: totalBookings,
        totalEOI: totalEOI,
        totalCancelledBookings: totalCancelledBookings
    };
}


// Enhanced function to generate cards for filtered users with proper performance data
async function generateFilteredUserCards(filteredUsers) {
    const usersGrid = document.getElementById('usersGrid');
    if (!usersGrid) return;

    console.log('Generating cards for', filteredUsers.length, 'filtered users');

    // Fetch performance data for ALL filtered users in batch
    const performanceDataMap = await fetchBatchUserPerformanceData(filteredUsers);

    const toSafeInt = (value) => {
        const n = Number(value);
        return Number.isFinite(n) ? Math.max(0, Math.floor(n)) : 0;
    };

    usersGrid.innerHTML = '';

    const buildFilteredCard = (user, index) => {
        const existingCard = usersGrid.querySelector(`[data-user-id="${user.tablename}"]`);
        if (existingCard) return null;

        // Get performance data for this user
        const performanceData = performanceDataMap.get(user.tablename) || {};

        // Use performance data OR fallback to user's own data
        const leadsCount = toSafeInt(performanceData.myLeads ?? user.leads);
        const bookingsCount = toSafeInt(performanceData.total_bookings ?? user.bookings);
        const eoiCount = toSafeInt(performanceData.total_eoi ?? user.eoi);
        const cancelledCount = toSafeInt(performanceData.cancelled_bookings ?? user.cancelled_bookings);

        // Get QR, FSV, SVD from performance data
        const rawQualityRange = toSafeInt(performanceData.quality_range);
        const qualityRange = Math.min(rawQualityRange, leadsCount);
        const fsvCount = toSafeInt(performanceData.fsv_count);
        const svdCount = toSafeInt(performanceData.svd_count);

        // Get chart data from performance data
        const chartData = performanceData.detailed_status_counts || performanceData.analytics?.detailed_status_counts || [];

        // Extract converted leads from detailed status counts
        const convertedLeads = chartData.find(item => item.status && item.status.toLowerCase() === 'converted')?.count || 0;

        // Calculate conversion rate using converted leads (not bookings)
        const conversionRate = leadsCount > 0 ? ((convertedLeads / leadsCount) * 100).toFixed(1) : '0.0';

        // Use unified role badge function
        const roleBadge = getRoleBadge(user.user_type);
        const badgeColor = roleBadge.color;
        const badgeText = roleBadge.badge;
        const userType = roleBadge.role;

        const userCard = document.createElement('div');
        userCard.className = 'user-card';
        userCard.setAttribute('data-user-id', user.tablename);

        userCard.innerHTML = `
            <div class="user-header">
                <div class="user-name-container" style="display: flex; align-items: center; gap: 8px; margin-bottom: 5px;">
                    <div class="user-type-badge" style="
                        background: ${badgeColor};
                        color: white;
                        width: 24px;
                        height: 20px;
                        border-radius: 4px;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        font-size: 10px;
                        font-weight: bold;
                    " title="${userType.toUpperCase()}">${badgeText}</div>
                    <div class="user-name user-card-title" style="font-weight: 600;">${user.name || user.username}</div>
                </div>
                <div class="user-email text-muted" style="font-size: 14px;">${user.email || user.useremail}</div>
                <div class="user-stats" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-top: 15px;">
                    <div class="stat-box" style="text-align: center; padding: 10px; border-radius: 8px;">
                        <div class="stat-label">Leads</div>
                        <div class="stat-value">${leadsCount}</div>
                    </div>
                    <div class="stat-box" style="text-align: center; padding: 10px; border-radius: 8px;">
                        <div class="stat-label">QR</div>
                        <div class="stat-value">${qualityRange}</div>
                    </div>
                    <div class="stat-box" style="text-align: center; padding: 10px; border-radius: 8px;">
                        <div class="stat-label">FSV</div>
                        <div class="stat-value text-warning">${fsvCount}</div>
                    </div>
                    <div class="stat-box" style="text-align: center; padding: 10px; border-radius: 8px;">
                        <div class="stat-label">SVD</div>
                        <div class="stat-value text-violet">${svdCount}</div>
                    </div>
                    <div class="stat-box" style="text-align: center; padding: 10px; border-radius: 8px;">
                        <div class="stat-label">EOI</div>
                        <div class="stat-value text-primary">${eoiCount}</div>
                    </div>
                    <div class="stat-box" style="text-align: center; padding: 10px; border-radius: 8px;">
                        <div class="stat-label">Bookings</div>
                        <div class="stat-value text-success">${bookingsCount}</div>
                    </div>
                    <div class="stat-box" style="text-align: center; padding: 10px; border-radius: 8px;">
                        <div class="stat-label">Cancelled</div>
                        <div class="stat-value text-danger">${cancelledCount}</div>
                    </div>
                    <div class="stat-box" style="text-align: center; padding: 10px; border-radius: 8px;">
                        <div class="stat-label">CR</div>
                        <div class="stat-value text-purple" title="Converted: ${convertedLeads}/${leadsCount}">${conversionRate}%</div>
                    </div>
                </div>
            </div>
            <div class="user-chart filtered-chart-container" style="position: relative; margin-top: 15px;">
                <div class="chart-wrapper">
                    <canvas id="filteredUserChart${index}"></canvas>
                </div>
                <div class="chart-placeholder">Loading chart...</div>
            </div>
        `;

        const userWithData = {
            ...user,
            leads: leadsCount,
            bookings: bookingsCount,
            eoi: eoiCount,
            cancelled_bookings: cancelledCount,
            leadStatus: chartData
        };

        return { card: userCard, userWithData, index };
    };

    const INITIAL_BATCH = 2;
    const SCROLL_BATCH = 2;
    let renderedCount = 0;

    const renderBatch = async (batchSize) => {
        const start = renderedCount;
        if (start >= filteredUsers.length) return;

        const end = Math.min(start + batchSize, filteredUsers.length);
        const fragment = document.createDocumentFragment();
        const chartItems = [];

        for (let i = start; i < end; i++) {
            const built = buildFilteredCard(filteredUsers[i], i);
            if (!built) continue;
            fragment.appendChild(built.card);
            chartItems.push(built);
        }

        usersGrid.appendChild(fragment);
        renderedCount = end;

        // Ensure canvases are in layout before chart init.
        await new Promise(res => requestAnimationFrame(() => requestAnimationFrame(res)));

        chartItems.forEach((item, idx) => {
            setTimeout(() => {
                loadUserChartData(item.userWithData, item.index, 'filtered');
            }, idx * 80);
        });

        // Keep visible-card based stats in sync with lazy rendering.
        if (typeof updateStatsFromVisibleCards === 'function') {
            setTimeout(() => updateStatsFromVisibleCards(), 0);
        }
    };

    // Fast first paint (same behavior as normal unfiltered flow)
    await renderBatch(Math.min(INITIAL_BATCH, filteredUsers.length));

    // Cleanup prior observer/sentinel if any.
    const existingSentinel = document.getElementById('userCardsSentinel');
    if (existingSentinel && existingSentinel.parentElement) {
        existingSentinel.parentElement.removeChild(existingSentinel);
    }

    if (window.__userCardsObserver) {
        window.__userCardsObserver.disconnect();
    }

    const sentinel = document.createElement('div');
    sentinel.id = 'userCardsSentinel';
    sentinel.style.height = '1px';
    sentinel.style.width = '100%';
    usersGrid.parentElement.appendChild(sentinel);

    const scrollRoot = getDashboardScrollRoot(usersGrid);
    let isLoadingBatch = false;

    const observer = new IntersectionObserver(async (entries) => {
        const visible = entries.some(entry => entry.isIntersecting);
        if (!visible || isLoadingBatch) return;

        isLoadingBatch = true;
        await renderBatch(SCROLL_BATCH);

        if (renderedCount >= filteredUsers.length) {
            observer.disconnect();
            const indicator = document.getElementById('scrollDownIndicator');
            if (indicator) indicator.style.display = 'none';
        }
        isLoadingBatch = false;
    }, {
        root: scrollRoot,
        rootMargin: '0px 0px 400px 0px',
        threshold: 0.01
    });

    window.__userCardsObserver = observer;
    observer.observe(sentinel);

    if (window.__userCardsNearBottomListener && window.__userCardsNearBottomTarget) {
        window.__userCardsNearBottomTarget.removeEventListener('scroll', window.__userCardsNearBottomListener);
    }
    if (scrollRoot) {
        const nearBottomLoader = async () => {
            const maxScrollTop = Math.max(0, scrollRoot.scrollHeight - scrollRoot.clientHeight);
            const remaining = maxScrollTop - scrollRoot.scrollTop;
            if (remaining < 260 && !isLoadingBatch && (renderedCount < filteredUsers.length)) {
                isLoadingBatch = true;
                await renderBatch(SCROLL_BATCH);
                if (renderedCount >= filteredUsers.length) {
                    observer.disconnect();
                    const indicator = document.getElementById('scrollDownIndicator');
                    if (indicator) indicator.style.display = 'none';
                }
                isLoadingBatch = false;
            }
        };
        scrollRoot.addEventListener('scroll', nearBottomLoader, { passive: true });
        window.__userCardsNearBottomListener = nearBottomLoader;
        window.__userCardsNearBottomTarget = scrollRoot;
    }

    // Recreate scroll indicator only when there are more items than first batch.
    const oldIndicator = document.getElementById('scrollDownIndicator');
    if (oldIndicator) oldIndicator.remove();

    if (filteredUsers.length > INITIAL_BATCH) {
        const indicatorContainer = document.createElement('div');
        indicatorContainer.id = 'scrollDownIndicator';
        indicatorContainer.style.cssText = `
            position: sticky;
            bottom: 30px;
            margin-left: auto;
            margin-right: 30px;
            width: 45px;
            height: 45px;
            z-index: 9999;
            pointer-events: none;
            transform: translateY(-30px);
        `;

        indicatorContainer.innerHTML = `
            <div id="scrollDownBtn" style="
                background: #10b981;
                color: white;
                width: 45px;
                height: 45px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                pointer-events: auto;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.2), 0 2px 4px -1px rgba(0, 0, 0, 0.1);
                transition: opacity 0.3s ease, transform 0.3s ease;
                animation: bounce 2s infinite;
            ">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path>
                </svg>
            </div>
        `;

        if (scrollRoot) {
            scrollRoot.appendChild(indicatorContainer);
        } else {
            document.body.appendChild(indicatorContainer);
        }

        const btn = indicatorContainer.querySelector('#scrollDownBtn');
        btn.addEventListener('click', () => {
            smoothScrollDownDashboard(scrollRoot, 440);
        });
    }

    console.log(`Generated filtered user cards in lazy batches. Total users: ${filteredUsers.length}, initially rendered: ${Math.min(INITIAL_BATCH, filteredUsers.length)}`);
}


async function loadUserChartData(user, index, prefix = '') {
    try {
        const chartCanvas = document.getElementById(`${prefix}UserChart${index}`);
        const placeholder = chartCanvas?.closest('.chart-container')?.querySelector('.chart-placeholder') ||
            chartCanvas?.closest('.user-chart')?.querySelector('.chart-placeholder');

        if (!chartCanvas) {
            console.warn(`Chart canvas not found: ${prefix}UserChart${index}`);
            return;
        }

        console.log(`Loading chart for ${user.username || user.name}, prefix: ${prefix}, index: ${index}`);
        console.log('User chart data:', user.leadStatus);

        // Show placeholder while loading
        if (placeholder) {
            placeholder.style.display = 'flex';
            placeholder.textContent = 'Loading chart...';
        }

        let statusDistribution = [];

        // Handle different data structures for lead status
        if (user.leadStatus) {
            if (Array.isArray(user.leadStatus)) {
                statusDistribution = user.leadStatus;
            } else if (typeof user.leadStatus === 'object' && !Array.isArray(user.leadStatus)) {
                // Convert object to array format
                statusDistribution = Object.entries(user.leadStatus).map(([status, count]) => ({
                    status: status,
                    count: parseInt(count) || 0
                }));
            }
        }

        console.log('Processed status distribution:', statusDistribution);

        // Filter out zero counts and validate data
        statusDistribution = statusDistribution.filter(item =>
            item && item.count > 0 && item.status
        );

        console.log('Filtered status distribution:', statusDistribution);

        if (statusDistribution.length === 0) {
            console.log(`No status distribution data found for ${user.username || user.name}, showing no data message`);
            if (placeholder) {
                placeholder.textContent = 'No lead data available';
                placeholder.style.display = 'flex';
                placeholder.style.color = '#9ca3af';
                placeholder.style.fontSize = '12px';
            }
            return;
        }

        // Hide placeholder before creating chart
        if (placeholder) {
            placeholder.style.display = 'none';
        }

        // Create chart with data
        const labels = statusDistribution.map(item => item.status);
        const counts = statusDistribution.map(item => parseInt(item.count) || 0);

        // Status color mapping - use centralized colors for consistency
        const statusColors = getStatusColors();

        const backgroundColor = labels.map(label => statusColors[label] || '#9ca3af');

        // Destroy existing chart if it exists
        if (chartCanvas.chart) {
            chartCanvas.chart.destroy();
        }

        const chart = new Chart(chartCanvas, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: counts,
                    backgroundColor: backgroundColor,
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: {
                    onComplete: function () {
                        // Ensure placeholder is hidden after chart animation completes
                        if (placeholder) {
                            placeholder.style.display = 'none';
                            console.log(`Chart animation complete - placeholder hidden for ${user.username || user.name}`);
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            padding: 8,
                            usePointStyle: true,
                            font: { size: 10 },
                            generateLabels: function (chart) {
                                const data = chart.data;
                                const currentColors = getChartColors();
                                if (data.labels.length && data.datasets.length) {
                                    return data.labels.map((label, i) => {
                                        const value = data.datasets[0].data[i];
                                        return {
                                            text: `${label} (${value})`,
                                            fillStyle: data.datasets[0].backgroundColor[i],
                                            strokeStyle: '#fff',
                                            fontColor: currentColors.textColor,
                                            lineWidth: 1,
                                            hidden: false,
                                            index: i
                                        };
                                    });
                                }
                                return [];
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = total > 0 ? ((context.parsed / total) * 100).toFixed(1) : 0;
                                return `${context.label}: ${context.parsed} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });

        // Store chart reference for cleanup
        chartCanvas.chart = chart;

        console.log(`Chart created successfully for ${user.username || user.name}`);

        // Additional fallback to hide placeholder immediately after chart creation
        setTimeout(() => {
            if (placeholder && placeholder.style.display !== 'none') {
                placeholder.style.display = 'none';
                console.log('Fallback: hiding placeholder after timeout');
            }
        }, 100);

    } catch (error) {
        console.error(`Error loading chart data for user ${user.username || user.name}:`, error);
        const chartCanvas = document.getElementById(`${prefix}UserChart${index}`);
        const placeholder = chartCanvas?.closest('.chart-container')?.querySelector('.chart-placeholder') ||
            chartCanvas?.closest('.user-chart')?.querySelector('.chart-placeholder');
        if (placeholder) {
            placeholder.textContent = 'Error loading chart data';
            placeholder.style.display = 'flex';
            placeholder.style.color = '#ef4444';
        }
    }
}

// Add CSS animation for card transitions
if (!document.getElementById('cardTransitionStyles')) {
    const style = document.createElement('style');
    style.id = 'cardTransitionStyles';
    style.textContent = `
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .user-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
    `;
    document.head.appendChild(style);
}


// Generate hierarchical team cards with proper grouping and labels
async function generateHierarchicalTeamCards(hierarchyMembers, selectedUser, hierarchy, performanceDataMap) {
    const usersGrid = document.getElementById('usersGrid');
    if (!usersGrid) return;

    const isPromoter = selectedUser.user_type && selectedUser.user_type.toLowerCase().includes('promoter');
    const selectedProjects = getSelectedProjectNames();
    const hasProjectFilters = selectedProjects && selectedProjects.length > 0;

    console.log('🎯 GENERATING HIERARCHICAL TEAM CARDS (LAZY LOAD):', {
        selectedUser: selectedUser.name || selectedUser.username,
        isPromoter: isPromoter,
        hierarchyMembersCount: hierarchyMembers.length,
        hierarchyType: isPromoter ? 'upward (management)' : 'downward (subordinates)',
        performanceDataSize: performanceDataMap.size,
        hasProjectFilters: hasProjectFilters,
        selectedProjects: hasProjectFilters ? Array.from(selectedProjects) : []
    });

    // Reset hierarchical render counts
    window.__hierarchicalMembers = hierarchyMembers;
    window.__hierarchicalRenderedCount = 0;
    window.__hierarchicalPerformanceDataMap = performanceDataMap;
    let cardIndex = 0;

    // Add selected user section (always immediate)
    const selectedSection = document.createElement('div');
    selectedSection.className = 'hierarchy-section selected-user';
    selectedSection.innerHTML = `
        <div class="hierarchy-section-header">
            <h3 style="color: #059669; margin: 0 0 15px 0; font-size: 16px; display: flex; align-items: center;">
                <i class="fas fa-user-circle" style="margin-right: 8px;"></i>
                Selected ${selectedUser.user_type || 'User'}
                ${hasProjectFilters ? '<span style="color: #f59e0b; font-size: 12px; margin-left: 8px;">[Project Filtered Data]</span>' : ''}
            </h3>
        </div>
        <div class="hierarchy-cards-container"></div>
    `;

    const selectedContainer = selectedSection.querySelector('.hierarchy-cards-container');

    // NEW: Fetch explicitly the leader's data
    const leaderDataMap = await fetchBatchUserPerformanceData([selectedUser]);
    leaderDataMap.forEach((v, k) => performanceDataMap.set(k, v));

    const selectedPerformanceData = performanceDataMap.get(selectedUser.tablename) || {};
    const selectedCardHtml = await generateHierarchicalUserCard(selectedUser, cardIndex++, 'selected', selectedPerformanceData);
    selectedContainer.insertAdjacentHTML('beforeend', selectedCardHtml);
    usersGrid.appendChild(selectedSection);

    // Hierarchy section with batching
    const hierarchySection = document.createElement('div');
    hierarchySection.className = 'hierarchy-section downward-hierarchy';

    if (hierarchyMembers.length > 0) {
        hierarchySection.innerHTML = `
            <div class="hierarchy-section-header">
                <h3 style="color: ${isPromoter ? '#7c3aed' : '#dc2626'}; margin: 0 0 15px 0; font-size: 16px; display: flex; align-items: center;">
                    <i class="fas ${isPromoter ? 'fa-crown' : 'fa-users'}" style="margin-right: 8px;"></i>
                    ${isPromoter ? `Complete Team Hierarchy (${hierarchyMembers.length})` : `Team Subordinates (${hierarchyMembers.length})`}
                    ${hasProjectFilters ? '<span style="color: #059669; font-size: 12px; margin-left: 8px;">[Project Filtered]</span>' : ''}
                </h3>
            </div>
            <div class="hierarchy-cards-container" id="hierarchicalCardsContainer"></div>
        `;
        usersGrid.appendChild(hierarchySection);

        const hierarchyContainer = document.getElementById('hierarchicalCardsContainer');
        const INITIAL_BATCH = 2;
        const SCROLL_BATCH = 2;

        // Function to render a batch of hierarchical members
        const renderHierarchicalBatch = async (batchSize) => {
            const start = window.__hierarchicalRenderedCount || 0;
            if (start >= hierarchyMembers.length) return;

            const end = Math.min(start + batchSize, hierarchyMembers.length);
            const batchMembers = hierarchyMembers.slice(start, end);

            // === LAZY NETWORK FETCH ONLY THIS RENDER BATCH ===
            const batchDataMap = await fetchBatchUserPerformanceData(batchMembers);
            batchDataMap.forEach((v, k) => performanceDataMap.set(k, v));

            const frag = document.createDocumentFragment();

            for (const user of batchMembers) {
                // Read from local batch map directly
                const performanceData = performanceDataMap.get(user.tablename) || {};
                const div = document.createElement('div');
                div.innerHTML = await generateHierarchicalUserCard(user, cardIndex++, 'downward', performanceData);
                frag.appendChild(div.firstElementChild);
            }

            hierarchyContainer.appendChild(frag);
            window.__hierarchicalRenderedCount = end;

            // Load charts only for this batch
            await loadHierarchicalChartData(batchMembers, performanceDataMap);
        };

        // Render first batch
        await renderHierarchicalBatch(INITIAL_BATCH);

        // Setup IntersectionObserver for subsequent batches
        const existingSentinel = document.getElementById('hierarchicalSentinel');
        if (existingSentinel) existingSentinel.remove();

        const sentinel = document.createElement('div');
        sentinel.id = 'hierarchicalSentinel';
        sentinel.style.height = '1px';
        hierarchySection.appendChild(sentinel);

        if (window.__hierarchicalObserver) window.__hierarchicalObserver.disconnect();

        const scrollRoot = getDashboardScrollRoot(usersGrid);
        let isLoadingBatch = false;

        const observer = new IntersectionObserver(async (entries) => {
            const visible = entries.some(entry => entry.isIntersecting);
            if (!visible || isLoadingBatch || window.__hierarchicalRenderedCount >= hierarchyMembers.length) return;

            isLoadingBatch = true;
            console.log('Hierarchical sentinel visible, loading more members...');
            await renderHierarchicalBatch(SCROLL_BATCH);
            isLoadingBatch = false;

            if (window.__hierarchicalRenderedCount >= hierarchyMembers.length) {
                observer.disconnect();
                const indicator = document.getElementById('scrollDownIndicator');
                if (indicator) indicator.style.display = 'none';
            }
        }, {
            root: scrollRoot,
            rootMargin: '0px 0px 400px 0px',
            threshold: 0.01
        });

        window.__hierarchicalObserver = observer;
        observer.observe(sentinel);

        if (window.__hierarchicalNearBottomListener && window.__hierarchicalNearBottomTarget) {
            window.__hierarchicalNearBottomTarget.removeEventListener('scroll', window.__hierarchicalNearBottomListener);
        }
        if (scrollRoot) {
            const nearBottomLoader = async () => {
                const maxScrollTop = Math.max(0, scrollRoot.scrollHeight - scrollRoot.clientHeight);
                const remaining = maxScrollTop - scrollRoot.scrollTop;
                if (remaining < 260 && !isLoadingBatch && (window.__hierarchicalRenderedCount < hierarchyMembers.length)) {
                    isLoadingBatch = true;
                    await renderHierarchicalBatch(SCROLL_BATCH);
                    if (window.__hierarchicalRenderedCount >= hierarchyMembers.length) {
                        observer.disconnect();
                        const indicator = document.getElementById('scrollDownIndicator');
                        if (indicator) indicator.style.display = 'none';
                    }
                    isLoadingBatch = false;
                }
            };
            scrollRoot.addEventListener('scroll', nearBottomLoader, { passive: true });
            window.__hierarchicalNearBottomListener = nearBottomLoader;
            window.__hierarchicalNearBottomTarget = scrollRoot;
        }

        // --- SCROLL DOWN INDICATOR ---
        // Recreate scroll indicator only when there are more items than first batch
        const oldIndicator = document.getElementById('scrollDownIndicator');
        if (oldIndicator) oldIndicator.remove();

        if (hierarchyMembers.length > INITIAL_BATCH) {
            const indicatorContainer = document.createElement('div');
            indicatorContainer.id = 'scrollDownIndicator';
            indicatorContainer.style.cssText = `
                position: sticky;
                bottom: 30px;
                margin-left: auto;
                margin-right: 30px;
                width: 45px;
                height: 45px;
                z-index: 9999;
                pointer-events: none;
                transform: translateY(-30px);
            `;

            indicatorContainer.innerHTML = `
                <div id="scrollDownBtn" style="
                    background: #10b981;
                    color: white;
                    width: 45px;
                    height: 45px;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    cursor: pointer;
                    pointer-events: auto;
                    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.2), 0 2px 4px -1px rgba(0, 0, 0, 0.1);
                    transition: opacity 0.3s ease, transform 0.3s ease;
                    animation: bounce 2s infinite;
                ">
                    <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path>
                    </svg>
                </div>
            `;

            if (scrollRoot) {
                scrollRoot.appendChild(indicatorContainer);
            } else {
                document.body.appendChild(indicatorContainer);
            }

            const btn = indicatorContainer.querySelector('#scrollDownBtn');
            btn.addEventListener('click', () => {
                smoothScrollDownDashboard(scrollRoot, 440);
            });
        }

    } else {
        hierarchySection.innerHTML = `
            <div class="hierarchy-section-header">
                <h3 style="color: #6b7280; margin: 0 0 15px 0; font-size: 16px; display: flex; align-items: center;">
                    <i class="fas fa-info-circle" style="margin-right: 8px;"></i>
                    No Team Subordinates Found
                </h3>
            </div>
            <div class="hierarchy-cards-container">
                <p style="color: #6b7280; font-style: italic; padding: 20px; text-align: center;">
                    This user has no subordinates in their hierarchy${hasProjectFilters ? ' for the selected projects' : ''}.
                </p>
            </div>
        `;
        usersGrid.appendChild(hierarchySection);
    }

    injectHierarchyCardStyles();

    // Still load charts for the selected user (the leader) immediately
    await loadHierarchicalChartData([selectedUser], performanceDataMap);
}


// Generate individual hierarchical user card with proper performance data
async function generateHierarchicalUserCard(user, index, hierarchyType, performanceData = {}) {
    const userDisplayName = user.username || user.name || 'Unknown User';
    const userEmail = user.useremail || user.email || 'No email';
    const userRole = (user.user_type || 'user').toLowerCase();
    const roleDisplayName = userRole.charAt(0).toUpperCase() + userRole.slice(1);

    // Use provided performance data OR fallback to user's own data
    const toSafeInt = (value) => {
        const n = Number(value);
        return Number.isFinite(n) ? Math.max(0, Math.floor(n)) : 0;
    };

    const leadsCount = toSafeInt(performanceData.myLeads ?? user.leads);
    const bookingsCount = toSafeInt(performanceData.total_bookings ?? user.bookings);
    const eoiCount = toSafeInt(performanceData.total_eoi ?? user.eoi);
    const cancelledCount = toSafeInt(performanceData.cancelled_bookings ?? user.cancelled_bookings);

    // NEW: Get QR, FSV, SVD from performance data
    const rawQualityRange = toSafeInt(performanceData.quality_range);
    const qualityRange = Math.min(rawQualityRange, leadsCount);
    const fsvCount = toSafeInt(performanceData.fsv_count);
    const svdCount = toSafeInt(performanceData.svd_count);

    // Get chart data from performance data
    const chartData = performanceData.detailed_status_counts || performanceData.analytics?.detailed_status_counts || [];

    // Extract converted leads from detailed status counts
    const convertedLeads = chartData.find(item => item.status && item.status.toLowerCase() === 'converted')?.count || 0;

    // Calculate conversion rate using converted leads (not bookings)
    const conversionRate = leadsCount > 0 ?
        ((convertedLeads / leadsCount) * 100).toFixed(1) : '0';

    // Get border color based on hierarchy type
    const borderColors = {
        'upward': '#2563eb',
        'selected': '#059669',
        'downward': '#3b82f6'
    };
    const borderColor = borderColors[hierarchyType] || '#6b7280';

    // Get role badge color
    const roleColors = {
        'promoter': '#7c3aed',
        'business head': '#2563eb',
        'manager': '#059669',
        'team lead': '#d97706',
        'user': '#6b7280'
    };
    const roleColor = roleColors[userRole] || '#22c3cfff';

    return `
        <div class="user-card hierarchical-card" data-user-type="${hierarchyType}" data-user-id="${user.tablename}" style="
            border: 2px solid ${borderColor};
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        ">
            <div class="user-header" style="margin-bottom: 15px;">
                <h3 class="user-card-title">${userDisplayName}</h3>
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                    <span class="role-badge" style="
                        background: ${roleColor};
                        color: white;
                        padding: 4px 12px;
                        border-radius: 20px;
                        font-size: 12px;
                        font-weight: bold;
                        text-transform: uppercase;
                    ">${roleDisplayName}</span>
                    
                </div>
                <p style="margin: 0; font-size: 14px;" class="text-muted">${userEmail}</p>
            </div>
             
            <div class="user-stats" style="display: flex; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-top: 10px;">
                <!-- Row 1: Basic Stats -->
                <div class="stat-box" style="text-align: center; padding: 10px; border-radius: 8px;">
                    <div class="stat-value">${leadsCount}</div>
                    <div class="stat-label">Leads</div>
                </div>
                <div class="stat-box" style="text-align: center; padding: 10px; border-radius: 8px;">
                    <div class="stat-value">${qualityRange}</div>
                    <div class="stat-label">QR</div>
                </div>
                <div class="stat-box" style="text-align: center; padding: 10px; border-radius: 8px;">
                    <div class="stat-value text-warning">${fsvCount}</div>
                    <div class="stat-label">FSV</div>
                </div>
                <div class="stat-box" style="text-align: center; padding: 10px; border-radius: 8px;">
                    <div class="stat-value text-violet">${svdCount}</div>
                    <div class="stat-label">SVD</div>
                </div>
                <div class="stat-box" style="text-align: center; padding: 10px; border-radius: 8px;">
                    <div class="stat-value text-primary">${eoiCount}</div>
                    <div class="stat-label">EOI</div>
                </div>
                
                <!-- Row 2: Additional Stats -->
                <div class="stat-box" style="text-align: center; padding: 10px; border-radius: 8px;">
                    <div class="stat-value text-success">${bookingsCount}</div>
                    <div class="stat-label">Bookings</div>
                </div>
                <div class="stat-box" style="text-align: center; padding: 10px; border-radius: 8px;">
                    <div class="stat-value text-danger">${cancelledCount}</div>
                    <div class="stat-label">Cancelled</div>
                </div>
                <div class="stat-box" style="text-align: center; padding: 10px; border-radius: 8px;">
                    <div class="stat-value text-purple" title="${convertedLeads}/${leadsCount}">${conversionRate}%</div>
                    <div class="stat-label">CR</div>
                </div>
            
            </div>
            
            <div class="chart-container hierarchy-chart-container" style="position: relative;">
                <div class="chart-wrapper">
                    <canvas id="hierarchy_UserChart${index}"></canvas>
                </div>
                <div class="chart-placeholder">Loading chart...</div>
            </div>
        </div>
    `;
}


// Fixed function to calculate hierarchical team stats including the selected manager
function calculateHierarchicalTeamStats(hierarchyMembers, performanceDataMap, selectedUser = null) {
    let totalLeads = 0;
    let totalBookings = 0;
    let totalEOI = 0;
    let totalRevenue = 0;
    let totalCancelledBookings = 0;
    let totalUsers = hierarchyMembers.length;

    // CRITICAL FIX: Include selected user's data in calculations
    if (selectedUser) {
        const selectedUserPerformance = performanceDataMap.get(selectedUser.tablename) || {};
        totalLeads += parseInt(selectedUserPerformance.myLeads || 0);
        totalBookings += parseInt(selectedUserPerformance.total_bookings || 0);
        totalEOI += parseInt(selectedUserPerformance.total_eoi || 0);
        totalRevenue += parseFloat(selectedUserPerformance.total_revenue || 0);
        totalCancelledBookings += parseInt(selectedUserPerformance.cancelled_bookings || 0);
        totalUsers += 1; // Count the selected user

        console.log(`Added selected user ${selectedUser.username || selectedUser.name} data to team stats:`, {
            leads: selectedUserPerformance.myLeads,
            bookings: selectedUserPerformance.total_bookings,
            eoi: selectedUserPerformance.total_eoi
        });
    }

    // Calculate aggregate stats from performance data map for team members
    hierarchyMembers.forEach(member => {
        const performanceData = performanceDataMap.get(member.tablename) || {};
        totalLeads += parseInt(performanceData.myLeads || 0);
        totalBookings += parseInt(performanceData.total_bookings || 0);
        totalEOI += parseInt(performanceData.total_eoi || 0);
        totalRevenue += parseFloat(performanceData.total_revenue || 0);
        totalCancelledBookings += parseInt(performanceData.cancelled_bookings || 0);
    });

    console.log('Team stats calculated (including selected user):', {
        totalUsers,
        totalLeads,
        totalBookings,
        totalEOI,
        totalCancelledBookings
    });

    return {
        totalUsers,
        totalLeads,
        totalBookings,
        totalEOI,
        totalRevenue,
        totalCancelledBookings
    };
}

// Update team summary with hierarchical context
function updateHierarchicalTeamSummary(selectedUser, hierarchy) {
    const usersSection = document.querySelector('.users-section');
    if (!usersSection) return;

    // Remove existing summary
    const existingSummary = document.querySelector('.team-summary');
    if (existingSummary) {
        existingSummary.remove();
    }

    const selectedUserName = selectedUser.username || selectedUser.name || 'Unknown';
    const selectedUserRole = (selectedUser.user_type || 'user').toLowerCase();
    const isPromoter = selectedUserRole.includes('promoter');

    // Get filtered user count
    // totalShownCount includes the manager/promoter themselves (+1)
    const totalShownCount = hierarchy.downward.length + 1;
    // displayCount depends on the role: Promoters show total members (including self), others show only subordinates
    const displayCount = isPromoter ? totalShownCount : hierarchy.downward.length;

    const totalUsers = allUsersData.length;
    const isFiltered = selectedUsers.size > 0;

    // Create merged summary panel with team summary styling
    const summaryHtml = `
        <div class="team-summary" style="
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            text-align: center;
            position: relative;
        ">
            <h3 style="margin: 0 0 10px 0; font-size: 20px;">
                ${isPromoter ? '👑 Promoter Hierarchy View' : '🏢 Team Subordinates View'}
            </h3>
            <p style="margin: 0 0 15px 0; opacity: 0.9;">
                ${isPromoter ?
            `Showing complete team under <strong>${selectedUserName}</strong> (${selectedUserRole.toUpperCase()}) - ${displayCount} total members` :
            `Showing subordinates under <strong>${selectedUserName}</strong> (${selectedUserRole.toUpperCase()})`
        }
            </p>
            <div style="display: flex; justify-content: center; gap: 30px; flex-wrap: wrap;">
                <div style="text-align: center;">
                    <div style="font-size: 24px; font-weight: bold;">${selectedUserName}</div>
                    <div style="font-size: 14px; opacity: 0.8;">${isPromoter ? 'Selected Promoter' : 'Selected Manager'}</div>
                </div>
                <div style="text-align: center;">
                    <div style="font-size: 24px; font-weight: bold;">${displayCount}</div>
                    <div style="font-size: 14px; opacity: 0.8;">${isPromoter ? 'Total Team Members' : 'Subordinates'}</div>
                </div>
                <div style="text-align: center;">
                    <div style="font-size: 24px; font-weight: bold;">${totalShownCount}/${totalUsers}</div>
                    <div style="font-size: 14px; opacity: 0.8;">Users ${isFiltered ? 'Selected' : 'Total'}</div>
                </div>
                <div style="text-align: center;">
                    <div style="font-size: 24px; font-weight: bold;">${isFiltered ? 'FILTERED' : 'ALL DATA'}</div>
                    <div style="font-size: 14px; opacity: 0.8;">View Mode</div>
                </div>
            </div>
        </div>
    `;

    usersSection.insertAdjacentHTML('afterbegin', summaryHtml);

    // Add floating clear filter button to the popup (not in the summary panel)
    addFloatingClearFilterButton();
}


function getActiveDashboardFilterState() {
    const userFilterCount = selectedUsers instanceof Set ? selectedUsers.size : 0;
    const projectFilterCount = (typeof selectedProjectNames !== 'undefined' && selectedProjectNames instanceof Set)
        ? selectedProjectNames.size
        : 0;

    const selectedMainUser = (typeof getSelectedUserValue === 'function' ? getSelectedUserValue() : null) || currentlySelectedUser || null;
    const hasMainUserFilter = !!selectedMainUser &&
        (typeof currentUserTableName === 'undefined' || String(selectedMainUser) !== String(currentUserTableName));
    const mainUserFilterCount = hasMainUserFilter ? 1 : 0;

    const hasCustomDateRange = !!(lastStartDate && lastEndDate);

    let hasMonthYearFilter = false;
    const popupMonthSelect = document.getElementById('popupMonthSelect');
    const popupYearSelect = document.getElementById('popupYearSelect');
    if (popupMonthSelect && popupYearSelect && popupMonthSelect.value && popupMonthSelect.value !== 'custom') {
        const now = new Date();
        const currentMonth = String(now.getMonth() + 1);
        const currentYear = String(now.getFullYear());
        hasMonthYearFilter = String(popupMonthSelect.value) !== currentMonth || String(popupYearSelect.value) !== currentYear;
    }

    const dateFilterCount = (hasCustomDateRange || hasMonthYearFilter) ? 1 : 0;
    const totalFilters = userFilterCount + projectFilterCount + mainUserFilterCount + dateFilterCount;

    return {
        userFilterCount,
        projectFilterCount,
        mainUserFilterCount,
        dateFilterCount,
        totalFilters,
        hasAnyFilters: totalFilters > 0
    };
}

function hasAnyActiveDashboardFilters() {
    return getActiveDashboardFilterState().hasAnyFilters;
}

function addFloatingClearFilterButton() {
    // Remove existing floating button if it exists
    const existingButton = document.querySelector('.floating-clear-filter');
    if (existingButton) {
        existingButton.remove();
    }

    // Calculate total active filters from all filter sources in the top filter row.
    const { totalFilters, hasAnyFilters } = getActiveDashboardFilterState();

    // Only add the button if filters are active
    if (!hasAnyFilters) return;

    const dashboardPopup = document.querySelector('#dashboardPopup');
    if (!dashboardPopup) return;

    const floatingButton = document.createElement('button');
    floatingButton.className = 'floating-clear-filter';
    floatingButton.innerHTML = `
        <i class="fas fa-times"></i>
        Clear All Filters (${totalFilters})
    `;
    floatingButton.style.cssText = `
        position: fixed !important;
        bottom: 1px;
        right: 1px;
        background: #ef4444;
        color: white;
        border: none;
        padding: 12px 20px;
        border-radius: 25px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        display: flex;
        align-items: center;
        gap: 8px;
        z-index: 10000;
        transition: all 0.3s ease;
    `;

    floatingButton.addEventListener('mouseenter', function () {
        this.style.transform = 'translateY(-2px)';
        this.style.boxShadow = '0 6px 16px rgba(239, 68, 68, 0.4)';
        this.style.background = '#dc2626';
    });

    floatingButton.addEventListener('mouseleave', function () {
        this.style.transform = 'translateY(0)';
        this.style.boxShadow = '0 4px 12px rgba(239, 68, 68, 0.3)';
        this.style.background = '#ef4444';
    });

    floatingButton.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        console.log('Clearing ALL filters via floating button');

        // Clear ALL filters (users, projects, dates)
        clearFilterSelections();

        // Reset to show all data
        showAllUsersNormalGrid();
        resetChartsToOriginalData();

        // Remove the floating button
        this.remove();

        // Remove filter summary panel if exists
        const summaryPanel = document.querySelector('.filter-summary-panel');
        if (summaryPanel) {
            summaryPanel.remove();
        }
    });

    // Append to the popup itself, not the scrollable content
    dashboardPopup.appendChild(floatingButton);

    console.log('Floating clear filter button added with', totalFilters, 'active filters');
}



// Update the fetchUserPerformanceData function to include the new metrics
async function fetchUserPerformanceData(userTablename) {
    try {
        // Get current date filter parameters for consistency
        const popupMonthSelect = document.getElementById('popupMonthSelect');
        const popupYearSelect = document.getElementById('popupYearSelect');
        const popupDateRangeFilter = document.getElementById('popupDateRangeFilter');
        const popupStartDate = document.getElementById('popupStartDate');
        const popupEndDate = document.getElementById('popupEndDate');

        const filters = {
            user_id: userTablename,
            date_column: getActiveDateColumn(),
            analytics: true
        };

        // Add date filter parameters to get consistent data
        if (popupDateRangeFilter && popupDateRangeFilter.style.display !== 'none') {
            if (popupStartDate && popupEndDate && popupStartDate.value && popupEndDate.value) {
                filters.start_date = popupStartDate.value;
                filters.end_date = popupEndDate.value;
            }
        } else if (popupMonthSelect && popupYearSelect) {
            if (popupMonthSelect.value !== 'custom') {
                filters.month = popupMonthSelect.value;
                filters.year = popupYearSelect.value;
            }
        }

        const selectedProjects = getSelectedProjectNames();
        if (selectedProjects && selectedProjects.length > 0) {
            filters.project_filter = selectedProjects.join(',');
        }

        const url = buildDashboardUrl('dashboard_data.php', filters);
        console.log(`Fetching performance data for ${userTablename}:`, url);

        const response = await fetch(url);
        const data = await response.json();

        if (data.status === 'success') {
            console.log(`Performance data fetched for ${userTablename}:`, data);
            return {
                myLeads: data.myLeads || 0,
                total_eoi: data.total_eoi || 0,
                total_bookings: data.total_bookings || 0, // This is the key field for bookings
                total_revenue: data.total_revenue || 0,
                cancelled_bookings: data.cancelled_bookings || 0,
                status_counts: data.status_counts || {},
                analytics: data.analytics || {},
                detailed_status_counts: data.analytics?.detailed_status_counts || [],
                // NEW: Include QR data from the main response
                quality_range: data.quality_range || 0,
                // Calculate FSV and SVD from status_counts
                fsv_count: data.status_counts?.fix_site_visit_count || 0,
                svd_count: data.status_counts?.site_visit_done_count || 0
            };
        } else {
            console.warn(`Failed to fetch performance data for ${userTablename}:`, data.message);
            return getFallbackPerformanceData();
        }
    } catch (error) {
        console.error(`Error fetching performance data for ${userTablename}:`, error);
        return getFallbackPerformanceData();
    }
}

// Update the fallback performance data function
function getFallbackPerformanceData() {
    return {
        myLeads: 0,
        total_eoi: 0,
        total_bookings: 0,
        total_revenue: 0,
        cancelled_bookings: 0,
        status_counts: {},
        analytics: {},
        detailed_status_counts: [],
        quality_range: 0,
        fsv_count: 0,
        svd_count: 0
    };
}


// Fetch performance data for multiple users in batch using the new batch API
async function fetchBatchUserPerformanceData(users) {
    const performanceDataMap = new Map();

    if (!users || users.length === 0) return performanceDataMap;

    // Use centralized filter helpers to build consistent query params
    const baseFilters = {
        date_column: getActiveDateColumn(),
        analytics: true
    };

    // Add date filter parameters to get consistent data
    const popupMonthSelect = document.getElementById('popupMonthSelect');
    const popupYearSelect = document.getElementById('popupYearSelect');
    const popupDateRangeFilter = document.getElementById('popupDateRangeFilter');
    const popupStartDate = document.getElementById('popupStartDate');
    const popupEndDate = document.getElementById('popupEndDate');

    if (popupDateRangeFilter && popupDateRangeFilter.style.display !== 'none') {
        if (popupStartDate && popupEndDate && popupStartDate.value && popupEndDate.value) {
            baseFilters.start_date = popupStartDate.value;
            baseFilters.end_date = popupEndDate.value;
        }
    } else if (popupMonthSelect && popupYearSelect) {
        if (popupMonthSelect.value !== 'custom') {
            baseFilters.month = popupMonthSelect.value;
            baseFilters.year = popupYearSelect.value;
        }
    }

    const selectedProjects = getSelectedProjectNames();
    if (selectedProjects && selectedProjects.length > 0) {
        baseFilters.project_filter = selectedProjects.join(',');
    }

    // Limit chunk sizes to 50 for the batch API
    const batchSize = 50;
    for (let i = 0; i < users.length; i += batchSize) {
        const batch = users.slice(i, i + batchSize);
        const userIds = batch.map(u => u.tablename).join(',');

        const filters = { ...baseFilters, user_ids: userIds };
        const url = buildDashboardUrl('batch_dashboard_data.php', filters);

        try {
            const response = await fetch(url);
            const result = await response.json();

            if (result.status === 'success' && result.data) {
                // Populate the map with returned data
                Object.entries(result.data).forEach(([userId, userData]) => {
                    if (userData.status === 'success') {
                        performanceDataMap.set(userId, {
                            myLeads: userData.myLeads || 0,
                            total_eoi: userData.total_eoi || 0,
                            total_bookings: userData.total_bookings || 0,
                            total_revenue: userData.total_revenue || 0,
                            cancelled_bookings: userData.cancelled_bookings || 0,
                            status_counts: userData.status_counts || {},
                            analytics: userData.analytics || {},
                            detailed_status_counts: userData.analytics?.detailed_status_counts || [],
                            quality_range: userData.quality_range || 0,
                            fsv_count: userData.fsv_count || 0,
                            svd_count: userData.svd_count || 0
                        });
                    }
                });
            }
        } catch (error) {
            console.error('Error fetching batch dashboard data:', error);
        }

        // Add fallback data for failed users
        batch.forEach(user => {
            if (!performanceDataMap.has(user.tablename)) {
                performanceDataMap.set(user.tablename, {
                    myLeads: 0,
                    total_eoi: 0,
                    total_bookings: 0,
                    total_revenue: 0,
                    cancelled_bookings: 0,
                    status_counts: {},
                    analytics: {},
                    detailed_status_counts: [],
                    quality_range: 0,
                    fsv_count: 0,
                    svd_count: 0
                });
            }
        });
    }

    console.log(`Fetched performance data efficiently for ${performanceDataMap.size} users`);
    return performanceDataMap;
}

// Load chart data for hierarchical cards with performance data
async function loadHierarchicalChartData(allUsersForChartLoading, performanceDataMap) {
    if (!allUsersForChartLoading || allUsersForChartLoading.length === 0) return;

    console.log('Loading hierarchical chart data for', allUsersForChartLoading.length, 'users');

    // Create a set of user IDs to load for efficiency
    const userIdsToLoad = new Set(allUsersForChartLoading.map(u => u.tablename));

    // Find all hierarchy chart canvases in the DOM
    const allChartCanvases = document.querySelectorAll('canvas[id^="hierarchy_UserChart"]');

    for (const canvas of allChartCanvases) {
        try {
            const canvasId = canvas.id;
            const cardIndex = parseInt(canvasId.replace('hierarchy_UserChart', ''));

            // Find the corresponding user card
            const userCard = canvas.closest('.hierarchical-card');
            if (!userCard) continue;

            const userId = userCard.getAttribute('data-user-id');
            if (!userId || !userIdsToLoad.has(userId)) continue;

            // Get performance data for this user
            const performanceData = performanceDataMap.get(userId) || {};

            // Extract chart data - check multiple possible locations
            let chartData = [];
            if (performanceData.detailed_status_counts && Array.isArray(performanceData.detailed_status_counts)) {
                chartData = performanceData.detailed_status_counts;
            } else if (performanceData.analytics?.detailed_status_counts && Array.isArray(performanceData.analytics.detailed_status_counts)) {
                chartData = performanceData.analytics.detailed_status_counts;
            } else if (performanceData.analytics && Array.isArray(performanceData.analytics)) {
                chartData = performanceData.analytics;
            }

            // Find user data in allUsersForChartLoading
            let user = allUsersForChartLoading.find(u => u.tablename === userId);

            // If not found, try to get the user data from the card itself
            if (!user) {
                const userName = userCard.querySelector('h3')?.textContent?.trim();
                const userEmail = userCard.querySelector('p')?.textContent?.trim();
                user = {
                    tablename: userId,
                    username: userName,
                    useremail: userEmail,
                    name: userName
                };
                console.log(`User not found in provided list, created from card data:`, user);
            }

            // Create enhanced user data object with chart data
            const userWithData = {
                ...user,
                leads: performanceData.myLeads || user.leads || 0,
                bookings: performanceData.total_bookings || user.bookings || 0,
                eoi: performanceData.total_eoi || user.eoi || 0,
                cancelled_bookings: performanceData.cancelled_bookings || user.cancelled_bookings || 0,
                // Add leadStatus from performance data
                leadStatus: chartData
            };

            console.log(`User ${user.username || user.name} chart data (${chartData.length} items):`, userWithData.leadStatus);

            // Load the chart with the enhanced data using the correct index
            await loadUserChartData(userWithData, cardIndex, 'hierarchy_');

            // Add a small delay to prevent chart loading conflicts
            await new Promise(resolve => setTimeout(resolve, 100));

        } catch (error) {
            console.error(`Error loading chart data for canvas ${canvas.id}:`, error);

            // Show error message in placeholder
            const placeholder = canvas.closest('.chart-container')?.querySelector('.chart-placeholder');
            if (placeholder) {
                placeholder.textContent = 'Error loading chart data';
                placeholder.style.display = 'flex';
                placeholder.style.color = '#ef4444';
            }
        }
    }
}

// Inject CSS styles for hierarchy cards
function injectHierarchyCardStyles() {
    if (document.getElementById('hierarchyCardStyles')) return;

    const style = document.createElement('style');
    style.id = 'hierarchyCardStyles';
    style.textContent = `
        .hierarchy-section {
            margin-bottom: 30px;
        }
        
        .hierarchy-section-header {
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        
        .hierarchy-cards-container {
            display: grid;
            gap: 20px;
        }
        
        /* REMOVED THE #usersGrid FORCED GRID STYLES */
        
        .hierarchical-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important;
        }
        
        .upward-hierarchy .hierarchical-card {
            border-left: 4px solid #2563eb !important;
        }
        
        .selected-user .hierarchical-card {
            border-left: 4px solid #059669 !important;
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%) !important;
        }
        
        .downward-hierarchy .hierarchical-card {
            border-left: 4px solid #3b82f6 !important;
        }
        
        @media (max-width: 768px) {
            .hierarchy-cards-container {
                grid-template-columns: 1fr;
            }
        }
    `;

    document.head.appendChild(style);
}

async function generateTeamCards(teamMembers, leader = null, performanceData = {}) {
    const usersGrid = document.getElementById('usersGrid');
    if (!usersGrid) return;

    usersGrid.innerHTML = '';

    if (!teamMembers || teamMembers.length === 0) {
        usersGrid.innerHTML = '<div class="no-team-message" style="text-align: center; padding: 40px; color: #6b7280; grid-column: 1 / -1;">No team members found</div>';
        return;
    }

    // Track processed users to identify duplicates
    const processedUsers = new Set();
    let visibleCount = 0;

    // Process each team member and create their card (including duplicates)
    teamMembers.forEach((member, index) => {
        const identifier = member.tablename || member.email || member.username;
        const isDuplicate = processedUsers.has(identifier);

        if (identifier && !isDuplicate) {
            processedUsers.add(identifier);
        }

        console.log(`Processing ${member.username || member.name}: ${isDuplicate ? 'DUPLICATE (will be hidden)' : 'UNIQUE (will be visible)'}`);

        // Determine visibility - hide duplicates but keep them in DOM
        const cardVisibility = isDuplicate ? 'display: none;' : '';
        if (!isDuplicate) visibleCount++;

        const isLeader = leader && (
            (member.tablename === leader.tablename) ||
            (member.username === leader.username) ||
            (member.email === leader.email)
        );

        // Get performance data for this member
        const memberPerformance = performanceData[member.tablename] || {};
        const leadsCount = memberPerformance.leads || 0;
        const bookingsCount = memberPerformance.bookings || 0;
        const eoiCount = memberPerformance.eoi || 0;
        const statusDistribution = memberPerformance.status_distribution || [];

        // Extract converted leads from status distribution  
        const convertedLeads = statusDistribution.find(item => item.status && item.status.toLowerCase() === 'converted')?.count || 0;

        // Calculate conversion rate using converted leads (not bookings)
        const conversionRate = leadsCount > 0 ?
            Math.round(((convertedLeads || 0) / leadsCount) * 100) : 0;

        const userCard = document.createElement('div');
        userCard.className = 'user-card';

        // Determine card styling based on role
        let cardStyle = '';
        if (isLeader) {
            if ((member.user_type || '').toLowerCase() === 'ceo') {
                cardStyle = 'border: 3px solid #dc2626; box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);';
            } else {
                cardStyle = 'border: 3px solid #2563eb; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);';
            }
        } else {
            cardStyle = 'border: 2px solid #10b981; box-shadow: 0 2px 8px rgba(16, 185, 129, 0.2);';
        }

        // Combine card styling with visibility
        userCard.style.cssText = `${cardStyle} ${cardVisibility}`;

        userCard.innerHTML = `
            <div class="user-header">
                <div class="user-name">
                    ${member.username || member.name}
                    ${isLeader ? `<span class="leader-badge" style="
                        background: ${(member.user_type || '').toLowerCase() === 'ceo' ? '#dc2626' : '#2563eb'};
                        color: white;
                        padding: 2px 8px;
                        border-radius: 12px;
                        font-size: 10px;
                        margin-left: 8px;
                        text-transform: uppercase;
                    ">${(member.user_type || '').toLowerCase()}</span>` : ''}
                </div>
                <div class="user-email">${member.useremail || member.email || 'No email'}</div>
                <div class="user-stats">
                    <div class="user-stat">
                        <div class="label">Leads</div>
                        <div class="value">${leadsCount}</div>
                    </div>
                    <div class="user-stat">
                        <div class="label">EOI</div>
                        <div class="value">${eoiCount}</div>
                    </div>
                    <div class="user-stat">
                        <div class="label">Bookings</div>
                        <div class="value">${bookingsCount}</div>
                    </div>
                    <div class="user-stat">
                        <div class="label">Cancelled</div>
                        <div class="value">${memberPerformance.cancelled_bookings || 0}</div>
                    </div>
                    <div class="user-stat">
                        <div class="label">Conv Rate</div>
                        <div class="value" title="${convertedLeads}/${leadsCount}">${conversionRate}%</div>
                    </div>
                </div>
            </div>
            <div class="user-chart" style="position: relative;">
                <div class="chart-wrapper">
                    <canvas id="teamUserChart${index}"></canvas>
                </div>
            </div>
        `;

        usersGrid.appendChild(userCard);

        // Load chart data for this team member (only if there's data and not duplicate)
        if (!isDuplicate && leadsCount > 0 && statusDistribution.length > 0) {
            loadTeamMemberChartData(member, index, statusDistribution);
        }
    });

    console.log(`Generated ${teamMembers.length} team member cards (${visibleCount} visible, ${teamMembers.length - visibleCount} hidden duplicates)`);
}


// NEW FUNCTION: Load chart data for individual team members
async function loadTeamMemberChartData(member, index, statusDistribution) {
    try {
        const chartCanvas = document.getElementById(`teamUserChart${index}`);
        const placeholder = chartCanvas?.closest('.user-chart')?.querySelector('.chart-placeholder');

        if (chartCanvas && statusDistribution && statusDistribution.length > 0) {
            if (placeholder) placeholder.style.display = 'none';

            const labels = statusDistribution.map(item => item.status);
            const counts = statusDistribution.map(item => parseInt(item.count) || 0);

            if (counts.some(count => count > 0)) {
                setTimeout(() => {
                    new Chart(chartCanvas, {
                        type: 'doughnut',
                        data: {
                            labels: labels,
                            datasets: [{
                                data: counts,
                                backgroundColor: [
                                    '#4CAF50', '#2196F3', '#FFC107', '#F44336',
                                    '#9C27B0', '#00BCD4', '#FF9800', '#795548',
                                    '#607D8B', '#9E9E9E', '#795548', '#FF5722'
                                ],
                                borderWidth: 1,
                                borderColor: '#fff'
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'right',
                                    labels: {
                                        padding: 10,
                                        usePointStyle: true,
                                        font: { size: 10 },
                                        generateLabels: function (chart) {
                                            const data = chart.data;
                                            const currentColors = getChartColors();
                                            if (data.labels.length && data.datasets.length) {
                                                return data.labels.map((label, i) => {
                                                    const value = data.datasets[0].data[i];
                                                    return {
                                                        text: `${label} (${value})`,
                                                        fillStyle: data.datasets[0].backgroundColor[i],
                                                        strokeStyle: data.datasets[0].borderColor || '#fff',
                                                        fontColor: currentColors.textColor,
                                                        lineWidth: 1,
                                                        hidden: false,
                                                        index: i
                                                    };
                                                });
                                            }
                                            return [];
                                        }
                                    }
                                }
                            }
                        }
                    });
                }, index * 200);
            } else {
                if (placeholder) {
                    placeholder.textContent = 'No data available for selected period';
                    placeholder.style.display = 'flex';
                }
            }
        }
    } catch (error) {
        console.error(`Error loading chart data for ${member.username}:`, error);
        const placeholder = document.querySelector(`#teamUserChart${index}`)?.closest('.user-chart')?.querySelector('.chart-placeholder');
        if (placeholder) {
            placeholder.textContent = 'Error loading data';
            placeholder.style.display = 'flex';
        }
    }
}




function updateTeamSummary(leaderName, leaderType, teamCount) {
    let summary = document.querySelector('.team-summary');

    if (!summary) {
        summary = document.createElement('div');
        summary.className = 'team-summary';
        // Use the role badge color for styling consistency
        const isPromoter = roleBadge.role === 'promoter';
        summary.style.cssText = `
            background: linear-gradient(135deg, ${isPromoter ? '#fef2f2, #fee2e2' : '#eff6ff, #dbeafe'});
            border: 2px solid ${badgeColor};
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 600;
            color: ${isPromoter ? '#991b1b' : '#1e40af'};
            animation: slideDown 0.3s ease;
        `;

        const usersSection = document.querySelector('.users-section');
        const usersGrid = document.getElementById('usersGrid');
        if (usersSection && usersGrid) {
            usersSection.insertBefore(summary, usersGrid);
        }
    }

    // Use unified role badge function
    const roleBadge = getRoleBadge(leaderType);
    const badgeColor = roleBadge.color;
    const badgeText = roleBadge.badge;

    summary.innerHTML = `
        <div style="display: flex; align-items: center; justify-content: center; gap: 10px;">
            <div style="background: ${badgeColor}; color: white; width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: bold;">${badgeText}</div>
            <span>Showing <strong>${leaderName}</strong> (${roleBadge.role.toUpperCase()}) and their team (${teamCount} members)</span>
        </div>
    `;
}

// Helper function to clear team summary
function clearTeamSummary() {
    const summary = document.querySelector('.team-summary');
    if (summary) {
        summary.remove();
    }
}

// Helper function to clear hierarchical summary
function clearHierarchicalSummary() {
    const teamSummary = document.querySelector('.team-summary');
    if (teamSummary) {
        teamSummary.remove();
    }
}

// Update charts to show only filtered users' data
function updateChartsWithFilteredData(filteredUsers) {
    if (!filteredUsers || filteredUsers.length === 0) {
        console.log('No filtered users data available - resetting to original data');
        resetChartsToOriginalData();
        return;
    }

    console.log('Updating charts with filtered users:', filteredUsers.map(u => `${u.username || u.name} (${u.user_type})`));

    // Get the filtered user tablenames
    const filteredUserTablenames = filteredUsers.map(user => user.tablename).filter(id => id);

    if (filteredUserTablenames.length === 0) {
        console.log('No valid tablenames found for filtered users');
        resetChartsToOriginalData();
        return;
    }

    // Store filtered tablenames for Excel export
    currentFilteredUserTablenames = filteredUserTablenames;

    // Show loading state for charts
    if (overallLeadStatusChart && leadSourceChart) {
        showChartLoader('overallLeadStatusChart');
        showChartLoader('leadSourceChart');
    }

    // Fetch fresh aggregated data for the filtered users
    fetchFilteredAnalyticsData(filteredUserTablenames)
        .then(data => {
            // Hide loaders
            hideChartLoader('overallLeadStatusChart');
            hideChartLoader('leadSourceChart');

            if (data && data.aggregated_analytics) {
                // Store filtered data for Excel export
                currentFilteredAnalyticsData = data.aggregated_analytics;
                updateChartsWithAggregatedData(data.aggregated_analytics);
            } else {
                console.log('No aggregated analytics data received for filtered users');
                // Clear filtered data
                currentFilteredAnalyticsData = null;
                // Fallback: calculate from visible cards
                const stats = recalculateTotalsFromVisibleCards();
                updateStatsDisplay(stats, true);
                resetChartsToOriginalData();
            }
        })
        .catch(error => {
            // Hide loaders
            hideChartLoader('overallLeadStatusChart');
            hideChartLoader('leadSourceChart');

            console.error('Error fetching filtered analytics data:', error);
            // Fallback: calculate from visible cards
            const stats = recalculateTotalsFromVisibleCards();
            updateStatsDisplay(stats, true);
            resetChartsToOriginalData();
        });
}
// Update the overall lead status chart with filtered data


// Update both status and source charts with aggregated analytics data
function updateChartsWithAggregatedData(aggregatedData, skipStatsUpdate = false) {
    if (!aggregatedData) {
        console.log('No aggregated data provided for chart updates');
        return;
    }

    console.log('Updating charts with aggregated data:', aggregatedData, 'skipStatsUpdate:', skipStatsUpdate);

    // Update status chart
    if (aggregatedData.detailed_status_counts) {
        const statusCounts = {};
        aggregatedData.detailed_status_counts.forEach(item => {
            if (item.status && item.count !== undefined) {
                statusCounts[item.status] = parseInt(item.count, 10) || 0;
            }
        });

        if (overallLeadStatusChart) {
            updateOverallStatusChart(statusCounts);
        }
    }

    // Update source chart
    if (aggregatedData.detailed_source_counts) {
        if (leadSourceChart) {
            updateSourceChartWithData(aggregatedData.detailed_source_counts);
        }
    }

    // CRITICAL: Only update stats if not skipped
    // When called from showTeamView(), stats are already correct from updatePopupDashboard()
    // Updating them again causes double rendering
    if (!skipStatsUpdate) {
        console.log('✅ Updating stats from aggregated data');
        const totals = extractAggregatedTotals(aggregatedData);
        // Only update if there's something meaningful (avoid unnecessary low-value updates)
        if (totals && (totals.totalLeads > 0 || totals.totalBookings > 0 || totals.totalEOI > 0 || totals.totalUsers > 0)) {
            // Aggregated analytics is authoritative
            updateStatsDisplay(totals, true, { priority: 2, source: 'aggregated_analytics' }); // Pass true to indicate filtered data
        } else {
            // Even if zeros, prefer to apply aggregated payload (keeps UI consistent)
            updateStatsDisplay(totals, true, { priority: 2, source: 'aggregated_analytics' });
        }
    } else {
        console.log('⚠️ Skipping stats update (already updated by updatePopupDashboard)');
    }
}


// New function to update the source chart with filtered data
function updateSourceChartWithData(sourceCounts) {
    console.log('updateSourceChartWithData called with:', sourceCounts);
    console.log('leadSourceChart exists:', !!leadSourceChart);

    if (!leadSourceChart || !sourceCounts || sourceCounts.length === 0) {
        console.log('Cannot update source chart - missing chart or data');
        return;
    }

    const sourceColors = {
        'Google': '#4285f4', 'Facebook': '#1877f2', 'Direct': '#6366f1',
        'Portal': '#8b5cf6', 'Referral': '#10b981', 'WhatsApp': '#25d366',
        'Instagram': '#e1306c', 'LinkedIn': '#0077b5', 'Twitter': '#1da1f2',
        'Website': '#f59e0b', 'Other': '#9ca3af', 'Phone Call': '#84cc16',
        'Email': '#ef4444', 'Advertisement': '#f97316', 'SMS': '#14b8a6'
    };

    const labels = sourceCounts.map(item => item.source_of_lead || 'Unknown');
    const data = sourceCounts.map(item => parseInt(item.count, 10) || 0);
    const backgroundColors = labels.map((source, index) => {
        return sourceColors[source] || colors[index % colors.length] || '#9ca3af';
    });

    // Update chart data
    leadSourceChart.data.labels = labels;
    leadSourceChart.data.datasets[0].data = data;
    leadSourceChart.data.datasets[0].backgroundColor = backgroundColors;

    // Update legend labels to include counts
    leadSourceChart.options.plugins.legend.labels.generateLabels = function (chart) {
        const chartData = chart.data;
        if (chartData.labels.length && chartData.datasets.length) {
            return chartData.labels.map((label, i) => {
                const value = chartData.datasets[0].data[i];
                return {
                    text: `${label} (${value})`,
                    fillStyle: chartData.datasets[0].backgroundColor[i],
                    strokeStyle: chartData.datasets[0].borderColor || '#fff',
                    lineWidth: 1,
                    hidden: false,
                    index: i
                };
            });
        }
        return [];
    };

    // Update chart with animation
    leadSourceChart.update('active');

    console.log('Updated source chart with filtered data:', sourceCounts);
}

// Enhanced update filtered stats function
async function updateFilteredStats(selectedValues = null) {
    const activeSelected = Array.isArray(selectedValues) ? selectedValues.filter(Boolean) : Array.from(selectedUsers);
    const toNumber = (value) => Number(value) || 0;

    const aggregateUserList = (users, perfMap = null) => ({
        totalUsers: users.length,
        totalLeads: users.reduce((sum, user) => {
            const perf = perfMap?.get(user.tablename) || {};
            return sum + toNumber(perf.myLeads ?? user.leads ?? user.total_leads ?? user.myLeads);
        }, 0),
        totalBookings: users.reduce((sum, user) => {
            const perf = perfMap?.get(user.tablename) || {};
            return sum + toNumber(perf.total_bookings ?? user.bookings ?? user.total_bookings);
        }, 0),
        totalEOI: users.reduce((sum, user) => {
            const perf = perfMap?.get(user.tablename) || {};
            return sum + toNumber(perf.total_eoi ?? user.eoi ?? user.total_eoi);
        }, 0),
        totalCancelledBookings: users.reduce((sum, user) => {
            const perf = perfMap?.get(user.tablename) || {};
            return sum + toNumber(
                perf.cancelled_bookings ?? user.cancelled_bookings ?? user.total_cancelled_bookings ?? user.total_canceled_bookings
            );
        }, 0)
    });

    async function buildHierarchyTotals(baseUser) {
        if (!baseUser) return null;
        try {
            const hierarchy = await buildCompleteHierarchy(baseUser);
            const downward = Array.isArray(hierarchy?.downward) ? hierarchy.downward.filter(Boolean) : [];
            const users = [baseUser, ...downward];
            const perfMap = await fetchBatchUserPerformanceData(users);

            const enriched = users.map(u => {
                const perf = perfMap.get(u.tablename) || perfMap.get(u.user_unique_id) || {};
                return {
                    leads: toNumber(perf.myLeads ?? u.leads ?? u.total_leads ?? u.myLeads),
                    bookings: toNumber(perf.total_bookings ?? u.bookings ?? u.total_bookings),
                    eoi: toNumber(perf.total_eoi ?? u.eoi ?? u.total_eoi),
                    cancelled_bookings: toNumber(
                        perf.cancelled_bookings ?? u.cancelled_bookings ?? u.total_cancelled_bookings ?? u.total_canceled_bookings
                    )
                };
            });

            return aggregateUserList(enriched);
        } catch (error) {
            console.error('updateFilteredStats hierarchy aggregation failed', error);
            return null;
        }
    }

    if (!allUsersData || activeSelected.length === 0) {
        // Show original stats when no filter applied
        if (aggregatedAnalyticsData) {
            // Use canonical totals helper and treat as authoritative
            const totals = extractAggregatedTotals(aggregatedAnalyticsData);
            // Use higher priority so it overrides any previous filtered update
            updateStatsDisplay(totals, false, { priority: 4, source: 'aggregated_analytics_reset' });
        }

        // Reset charts to original data when no filter
        resetChartsToOriginalData();
        return;
    }

    const selectedSet = new Set(activeSelected);
    const filteredUsers = allUsersData.filter(user => selectedSet.has(user.tablename));

    if (filteredUsers.length === 0) return;

    let filteredStats;

    if (activeSelected.length === 1) {
        // Single user: include hierarchy (user + subordinates)
        const baseUser = filteredUsers[0] || allUsersData.find(u => u.tablename === activeSelected[0]);
        filteredStats = await buildHierarchyTotals(baseUser);

        // Fallback to direct user-only totals if hierarchy fetch fails
        if (!filteredStats) {
            filteredStats = aggregateUserList(filteredUsers);
        }
    } else {
        // Multiple users: do NOT include hierarchy, just sum selected users
        const perfMap = await fetchBatchUserPerformanceData(filteredUsers).catch(err => {
            console.error('fetchBatchUserPerformanceData failed, using raw user data', err);
            return null;
        });
        filteredStats = aggregateUserList(filteredUsers, perfMap || undefined);
    }

    // Use a higher priority so filtered totals override any lower-priority aggregated updates
    updateStatsDisplay(filteredStats, true, { priority: 3, source: 'filtered_users' });
}



// Enhanced handleOptionClick function with better chart updates
function handleOptionClick(event, value, text) {
    event.stopPropagation();

    const checkbox = event.currentTarget.querySelector('.option-checkbox');
    const isCurrentlySelected = selectedUsers.has(value);

    // Toggle selection
    if (isCurrentlySelected) {
        selectedUsers.delete(value);
        preservedSelectedUsers.delete(value); // Also remove from preserved
        checkbox.checked = false;
        event.currentTarget.classList.remove('selected');
    } else {
        selectedUsers.add(value);
        preservedSelectedUsers.add(value); // Also add to preserved
        checkbox.checked = true;
        event.currentTarget.classList.add('selected');
    }

    // Update UI
    updateSelectedTags();
    updateDropdownPlaceholder();

    // NOTE: Filters are now applied only when user clicks Apply button
    // Removed automatic applyAllFilters() call to allow user to make multiple selections before applying

    console.log('User selection changed. Current selections:', Array.from(selectedUsers));
}




// Enhanced selectAllUsers function
function selectAllUsers(event) {
    event.stopPropagation();

    const visibleOptions = document.querySelectorAll('.dropdown-option:not(.hidden)');

    visibleOptions.forEach(option => {
        const checkbox = option.querySelector('.option-checkbox');
        const value = checkbox.value;

        if (!selectedUsers.has(value)) {
            selectedUsers.add(value);
            checkbox.checked = true;
            option.classList.add('selected');
        }
    });

    updateSelectedTags();
    updateDropdownPlaceholder();

    // NOTE: Filters are now applied only when user clicks Apply button
    // Removed automatic applyAllFilters() call to allow user to make multiple selections before applying

    console.log('Selected all visible users');

}

// Enhanced clearAllUsers function - NOW APPLIES THE CLEARED STATE
function clearAllUsers(event) {
    if (event) event.stopPropagation();

    console.log('Clearing all user selections via clearAllUsers');

    // Clear user selections
    selectedUsers.clear();

    // Update checkboxes
    const checkboxes = document.querySelectorAll('.option-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = false;
        checkbox.closest('.dropdown-option')?.classList.remove('selected');
    });

    // Update UI
    updateSelectedTags();
    updateDropdownPlaceholder();
    updateFilterModeIndicator();

    // When Clear is clicked, we need to immediately reset the view to show all data
    // This is different from just clearing checkboxes - we're actually applying the "no filter" state
    showAllUsersNormalGrid();
    resetChartsToOriginalData();

    // Remove any floating filter buttons and summary panels
    const floatingButton = document.querySelector('.floating-clear-filter');
    if (floatingButton) floatingButton.remove();

    const summaryPanel = document.querySelector('.filter-summary-panel');
    if (summaryPanel) summaryPanel.remove();

    console.log('All filters cleared and view reset to show all users.');
}

// Enhanced removeUser function
function removeUser(value) {
    selectedUsers.delete(value);

    // Update checkbox in dropdown
    const checkbox = document.querySelector(`.option-checkbox[value="${value}"]`);
    if (checkbox) {
        checkbox.checked = false;
        checkbox.closest('.dropdown-option').classList.remove('selected');
    }

    updateSelectedTags();
    updateDropdownPlaceholder();

    // Removing a selected user chip is an explicit filter action,
    // so refresh cards/charts/stats immediately.
    applyAllFilters();

    console.log('User removed:', value, 'Remaining selections:', Array.from(selectedUsers));
}

// Add visual feedback for filtered vs unfiltered state
function updateFilterModeIndicator() {
    const filterSection = document.querySelector('.user-filter-section');
    if (!filterSection) return;

    let indicator = filterSection.querySelector('.filter-mode-indicator');

    if (!indicator) {
        indicator = document.createElement('div');
        indicator.className = 'filter-mode-indicator';

        filterSection.style.position = 'relative';
        filterSection.appendChild(indicator);
    }

    if (selectedUsers.size === 0) {
        indicator.textContent = 'SHOWING ALL';
        indicator.style.background = '#e5e7eb';
        indicator.style.color = '#6b7280';
    } else {
        indicator.textContent = `FILTERED (${selectedUsers.size})`;
        indicator.style.background = '#3b82f6';
        indicator.style.color = 'white';
    }
}

// Update the existing updateSelectedTags function to include the indicator
function updateSelectedTags() {
    const tagsContainer = document.querySelector('.selected-tags');
    if (!tagsContainer) return;

    tagsContainer.innerHTML = '';

    // Get user names for selected values
    const users = getUsersData();

    selectedUsers.forEach(value => {
        const user = users.find(u => u.value === value);
        if (user) {
            // Use unified role badge function
            const roleBadge = getRoleBadge(user.user_type);
            const userType = roleBadge.role;
            const badgeColor = roleBadge.color;
            const badgeText = roleBadge.badge;

            const tag = document.createElement('div');
            tag.className = 'user-tag';
            tag.innerHTML = `
                <div class="user-type-badge" style="
                    background: ${badgeColor};
                    color: white;
                    width: 16px;
                    height: 16px;
                    border-radius: 3px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 9px;
                    font-weight: bold;
                    margin-right: 6px;
                    flex-shrink: 0;
                " title="${userType.toUpperCase()}">${badgeText}</div>
                <span class="tag-text" title="${user.text}">${user.text}</span>
                <button class="remove-btn" onclick="removeUser('${value}')" title="Remove ${user.text}">&times;</button>
            `;
            tagsContainer.appendChild(tag);
        }
    });

    // Update filter mode indicator
    updateFilterModeIndicator();
}



function addBadgeStyles() {
    if (!document.getElementById('userTypeBadgeStyles')) {
        const style = document.createElement('style');
        style.id = 'userTypeBadgeStyles';
        style.textContent = `
            .user-type-badge {
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: bold;
                color: white;
                flex-shrink: 0;
            }
            
            /* Badge color variations */
            .badge-promoter { background: #7c3aed; }
            .badge-business-head { background: #dc2626; }
            .badge-manager { background: #2563eb; }
            .badge-team-lead { background: #d97706; }
            .badge-user { background: #059669; }
            
            .user-tag .user-type-badge {
                width: 16px;
                height: 16px;
                border-radius: 3px;
                font-size: 9px;
                margin-right: 6px;
            }
            
            .user-card .user-type-badge {
                width: 24px;
                height: 20px;
                border-radius: 4px;
                font-size: 10px;
            }
            
            /* Dark theme support */
            [data-theme="dark"] .badge-other { background: #9ca3af; }
        `;
        document.head.appendChild(style);
    }
}




// Enhanced chart color management for filtered data
function getFilteredChartColors() {
    const isDark = document.documentElement.getAttribute("data-theme") === "dark";
    const isFiltered = selectedUsers.size > 0;

    return {
        palette: [
            isFiltered ? "#22c55e" : "#4CAF50", // Green - brighter when filtered
            isFiltered ? "#3b82f6" : "#2196F3", // Blue
            isFiltered ? "#f59e0b" : "#FFC107", // Amber
            isFiltered ? "#ef4444" : "#F44336", // Red
            isFiltered ? "#a855f7" : "#9C27B0", // Purple
            isFiltered ? "#06b6d4" : "#00BCD4", // Cyan
            isFiltered ? "#f97316" : "#FF9800", // Orange
            isFiltered ? "#8b5cf6" : "#795548"  // Brown
        ],
        textColor: isDark ? "#eee" : "#333",
        tooltipBg: isDark ? "#333" : "#fff",
        tooltipBorder: isDark ? "#555" : "#ccc",
        isFiltered: isFiltered
    };
}

// Enhanced createFilterSummaryPanel function
function createFilterSummaryPanel() {
    const usersSection = document.querySelector('.users-section');
    if (!usersSection) return;

    let summaryPanel = document.querySelector('.filter-summary-panel');

    const totalUsers = allUsersData ? allUsersData.length : 0;
    const filteredCount = selectedUsers.size || totalUsers;
    const isFiltered = selectedUsers.size > 0;

    // Hide panel when 0 users are selected
    const shouldShowPanel = selectedUsers.size >= 1; // Changed from 2 to 1

    if (!shouldShowPanel) {
        // Remove panel if it exists and we shouldn't show it
        if (summaryPanel) {
            summaryPanel.remove();
        }
        return;
    }

    if (!summaryPanel) {
        summaryPanel = document.createElement('div');
        summaryPanel.className = 'filter-summary-panel';
        // Inline styles moved to CSS class .filter-summary-panel

        usersSection.insertBefore(summaryPanel, usersSection.firstChild);
    }

    summaryPanel.innerHTML = `
        <div class="summary-item">
            <div class="summary-count text-primary">
                ${filteredCount}/${totalUsers}
            </div>
            <div class="summary-label">
                Users Selected
            </div>
        </div>
        <div class="summary-item">
            <div class="summary-count text-success">
                ${isFiltered ? 'FILTERED VIEW' : 'ALL USERS VIEW'}
            </div>
            <div class="summary-label">
                ${isFiltered ? 'Multiple Users' : 'Showing All Users'}
            </div>
        </div>
        ${isFiltered ? `
        
        ` : ''}
    `;

    // Ensure the panel is visible
    summaryPanel.style.display = 'grid'; // Keep display grid as it might be toggled
}


// Update the updateVisibleUserCount function to include the summary panel
function updateVisibleUserCount(visible, total) {
    let countDisplay = document.querySelector('.user-count-display');
    if (!countDisplay) {
        countDisplay = document.createElement('div');
        countDisplay.className = 'user-count-display';

        const usersSection = document.querySelector('.users-section');
        const usersGrid = document.getElementById('usersGrid');
        if (usersSection && usersGrid) {
            usersSection.insertBefore(countDisplay, usersGrid);
        }
    }

    if (selectedUsers.size === 0) {
        countDisplay.textContent = `Showing all ${total} users`;
        countDisplay.className = 'user-count-display text-muted'; // Use class instead of style
        countDisplay.style.fontWeight = 'normal';
        countDisplay.style.color = ''; // Clear inline color
    } else if (selectedUsers.size === 1) {
        // Single user selected - show minimal info
        const selectedUser = Array.from(selectedUsers)[0];
        const users = getUsersData();
        const user = users.find(u => u.value === selectedUser);
        const userName = user ? user.text : 'Selected User';

        countDisplay.textContent = `Showing: ${userName}`;
        countDisplay.className = 'user-count-display text-primary';
        countDisplay.style.fontWeight = '600';
        countDisplay.style.color = ''; // Clear inline color
    } else {
        // Multiple users selected
        countDisplay.textContent = `Showing ${visible} of ${total} users (${selectedUsers.size} selected)`;
        countDisplay.className = 'user-count-display text-primary';
        countDisplay.style.fontWeight = '600';
        countDisplay.style.color = ''; // Clear inline color
    }

    // Update summary panel (will handle its own visibility)
    createFilterSummaryPanel();
}






// Update the initializePopupCharts function to include counts in legend labels
function initializePopupCharts(aggregatedData) {
    return new Promise((resolve) => {
        let pendingCharts = 2;
        const markChartFinished = () => {
            pendingCharts -= 1;
            if (pendingCharts <= 0) {
                resolve();
            }
        };
        const colors = getChartColors();

        // Show loaders for charts
        showChartLoader('overallLeadStatusChart');
        showChartLoader('leadSourceChart');

        // Overall Lead Status Chart
        const overallStatusCtx = document.getElementById('overallLeadStatusChart');
        const overallWrapper = document.getElementById('overallLeadStatusChart-wrapper');

        // Remove any previous fallback message
        if (overallWrapper) {
            const prevFallback = overallWrapper.querySelector('.chart-fallback');
            if (prevFallback) prevFallback.remove();
        }

        // Check if we have meaningful data (array with length > 0 and at least one count > 0)
        const hasStatusData = Array.isArray(aggregatedData?.detailed_status_counts)
            && aggregatedData.detailed_status_counts.length > 0
            && aggregatedData.detailed_status_counts.some(item => parseInt(item.count) > 0);

        if (overallStatusCtx && hasStatusData) {
            const statusCounts = aggregatedData.detailed_status_counts;

            setTimeout(() => {
                // Destroy existing chart if it exists
                if (overallLeadStatusChart) {
                    overallLeadStatusChart.destroy();
                }

                overallLeadStatusChart = new Chart(overallStatusCtx, {
                    type: 'doughnut',
                    data: {
                        labels: statusCounts.map(item => item.status),
                        datasets: [{
                            data: statusCounts.map(item => parseInt(item.count)),
                            backgroundColor: statusCounts.map(item => getStatusColor(item.status)),
                            borderWidth: 2,
                            borderColor: '#fff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    padding: 15,
                                    usePointStyle: true,
                                    color: colors.textColor,
                                    generateLabels: function (chart) {
                                        const data = chart.data;
                                        const currentColors = getChartColors();
                                        if (data.labels.length && data.datasets.length) {
                                            return data.labels.map((label, i) => {
                                                const value = data.datasets[0].data[i];
                                                return {
                                                    text: `${label} (${value})`,
                                                    fillStyle: data.datasets[0].backgroundColor[i],
                                                    strokeStyle: data.datasets[0].borderColor || '#fff',
                                                    fontColor: currentColors.textColor,
                                                    lineWidth: 1,
                                                    hidden: false,
                                                    index: i
                                                };
                                            });
                                        }
                                        return [];
                                    }
                                }
                            },
                            tooltip: {
                                backgroundColor: colors.tooltipBg,
                                titleColor: colors.textColor,
                                bodyColor: colors.textColor,
                                borderColor: colors.tooltipBorder,
                                borderWidth: 1
                            }
                        }
                    }
                });

                hideChartLoader('overallLeadStatusChart');
                markChartFinished();
            }, 150);
        } else {
            // No data or all counts are zero: show fallback message
            if (overallLeadStatusChart) {
                try {
                    overallLeadStatusChart.destroy();
                } catch (e) {
                    console.log('Chart destroy error (ignored):', e);
                }
                overallLeadStatusChart = null;
            }

            hideChartLoader('overallLeadStatusChart');
            markChartFinished();

            if (overallWrapper) {
                overallWrapper.innerHTML = '<div class="chart-fallback" style="display:flex;align-items:center;justify-content:center;min-height:200px;color:#9ca3af;font-size:16px;padding:40px;">No leads available</div>';
            }
        }

        // Lead Sources Chart
        const leadSourceCtx = document.getElementById('leadSourceChart');
        const leadSourceWrapper = document.getElementById('leadSourceChart-wrapper');

        // Remove any previous fallback message
        if (leadSourceWrapper) {
            const prevFallback = leadSourceWrapper.querySelector('.chart-fallback');
            if (prevFallback) prevFallback.remove();
        }

        // Check if we have meaningful data (array with length > 0 and at least one count > 0)
        const hasSourceData = Array.isArray(aggregatedData?.detailed_source_counts)
            && aggregatedData.detailed_source_counts.length > 0
            && aggregatedData.detailed_source_counts.some(item => parseInt(item.count) > 0);

        if (leadSourceCtx && hasSourceData) {
            const sourceCounts = aggregatedData.detailed_source_counts;

            setTimeout(() => {
                // Destroy existing chart if it exists
                if (leadSourceChart) {
                    leadSourceChart.destroy();
                }

                leadSourceChart = new Chart(leadSourceCtx, {
                    type: 'doughnut',
                    data: {
                        labels: sourceCounts.map(item => item.source_of_lead),
                        datasets: [{
                            data: sourceCounts.map(item => parseInt(item.count)),
                            backgroundColor: getPaletteForLength(colors.palette, sourceCounts.length),
                            borderWidth: 2,
                            borderColor: colors.borderColor
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'right',
                                labels: {
                                    padding: 15,
                                    usePointStyle: true,
                                    color: colors.textColor,
                                    font: {
                                        size: 11 // Smaller font for more items
                                    },
                                    generateLabels: function (chart) {
                                        const data = chart.data;
                                        const currentColors = getChartColors();
                                        if (data.labels.length && data.datasets.length) {
                                            return data.labels.map((label, i) => {
                                                const value = data.datasets[0].data[i];
                                                // Truncate long labels
                                                let displayLabel = label;
                                                if (label.length > 20) {
                                                    displayLabel = label.substring(0, 17) + '...';
                                                }
                                                return {
                                                    text: `${displayLabel} (${value})`,
                                                    fillStyle: data.datasets[0].backgroundColor[i],
                                                    strokeStyle: data.datasets[0].borderColor || '#fff',
                                                    fontColor: currentColors.textColor,
                                                    lineWidth: 1,
                                                    hidden: false,
                                                    index: i
                                                };
                                            });
                                        }
                                        return [];
                                    }
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function (context) {
                                        // Show full label in tooltip
                                        return `${context.label}: ${context.parsed}`;
                                    }
                                }
                            }
                        },
                        // Adjust layout to accommodate legend
                        layout: {
                            padding: {
                                left: 10,
                                right: 10,
                                top: 10,
                                bottom: 10
                            }
                        }
                    }
                });


                hideChartLoader('leadSourceChart');
                markChartFinished();
            }, 250);
        } else {
            // No data or all counts are zero: show fallback message
            if (leadSourceChart) {
                try {
                    leadSourceChart.destroy();
                } catch (e) {
                    console.log('Chart destroy error (ignored):', e);
                }
                leadSourceChart = null;
            }

            hideChartLoader('leadSourceChart');
            markChartFinished();

            if (leadSourceWrapper) {
                leadSourceWrapper.innerHTML = '<div class="chart-fallback" style="display:flex;align-items:center;justify-content:center;min-height:200px;color:#9ca3af;font-size:16px;padding:40px;">No lead sources available</div>';
            }
        }
    });
}

// Update the initializeDashboardCharts function to include counts in legend labels
function initializeDashboardCharts() {
    if (!aggregatedAnalyticsData) {
        console.log('No aggregated data available for dashboard charts');
        return;
    }

    const colors = getChartColors();

    // Restore popup content
    const popupContent = document.querySelector('#dashboardPopup .popup-content');
    if (popupContent) {
        popupContent.innerHTML = `
            <div class="stats-overview">
                <div class="overview-charts">
                    <div class="chart-section">
                        <h3>Overall Lead Status Distribution</h3>
                        <div class="chart-container" id="overallLeadStatusChart-wrapper" style="position: relative; height: 300px;">
                            <canvas id="overallLeadStatusChart"></canvas>
                        </div>
                    </div>
                    <div class="chart-section">
                        <h3>Lead Sources Overview</h3>
                        <div class="chart-container" id="leadSourceChart-wrapper" style="position: relative; height: 300px;">
                            <canvas id="leadSourceChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="users-section">
                <h3>Team Performance</h3>
                <div class="users-grid" id="usersGrid"></div>
            </div>
        `;
    }

    // Show loaders for dashboard charts
    showChartLoader('overallLeadStatusChart');
    showChartLoader('leadSourceChart');

    // Wait for DOM update
    setTimeout(() => {
        // Overall Lead Status Chart
        const overallStatusCtx = document.getElementById('overallLeadStatusChart');
        const overallWrapper = document.getElementById('overallLeadStatusChart-wrapper');

        // Remove any previous fallback message
        if (overallWrapper) {
            const prevFallback = overallWrapper.querySelector('.chart-fallback');
            if (prevFallback) prevFallback.remove();
        }

        // Check if we have meaningful data (array with length > 0 and at least one count > 0)
        const hasStatusData = Array.isArray(aggregatedAnalyticsData?.detailed_status_counts)
            && aggregatedAnalyticsData.detailed_status_counts.length > 0
            && aggregatedAnalyticsData.detailed_status_counts.some(item => parseInt(item.count) > 0);

        if (overallStatusCtx && hasStatusData) {
            const statusCounts = aggregatedAnalyticsData.detailed_status_counts;

            setTimeout(() => {
                // Destroy existing chart if it exists
                if (overallLeadStatusChart) {
                    overallLeadStatusChart.destroy();
                }

                overallLeadStatusChart = new Chart(overallStatusCtx, {
                    type: 'doughnut',
                    data: {
                        labels: statusCounts.map(item => item.status),
                        datasets: [{
                            data: statusCounts.map(item => parseInt(item.count)),
                            backgroundColor: statusCounts.map(item => getStatusColor(item.status)),
                            borderWidth: 2,
                            borderColor: '#fff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    padding: 15,
                                    usePointStyle: true,
                                    color: colors.textColor,
                                    generateLabels: function (chart) {
                                        const data = chart.data;
                                        const currentColors = getChartColors();
                                        if (data.labels.length && data.datasets.length) {
                                            return data.labels.map((label, i) => {
                                                const value = data.datasets[0].data[i];
                                                return {
                                                    text: `${label} (${value})`,
                                                    fillStyle: data.datasets[0].backgroundColor[i],
                                                    strokeStyle: data.datasets[0].borderColor || '#fff',
                                                    fontColor: currentColors.textColor,
                                                    lineWidth: 1,
                                                    hidden: false,
                                                    index: i
                                                };
                                            });
                                        }
                                        return [];
                                    }
                                }
                            },
                            tooltip: {
                                backgroundColor: colors.tooltipBg,
                                titleColor: colors.textColor,
                                bodyColor: colors.textColor,
                                borderColor: colors.tooltipBorder,
                                borderWidth: 1
                            }
                        }
                    }
                });

                hideChartLoader('overallLeadStatusChart');
            }, 150);
        } else {
            // No data or all counts are zero: show fallback message
            if (overallLeadStatusChart) {
                try {
                    overallLeadStatusChart.destroy();
                } catch (e) {
                    console.log('Chart destroy error (ignored):', e);
                }
                overallLeadStatusChart = null;
            }

            hideChartLoader('overallLeadStatusChart');

            if (overallWrapper) {
                overallWrapper.innerHTML = '<div class="chart-fallback" style="display:flex;align-items:center;justify-content:center;min-height:200px;color:#9ca3af;font-size:16px;padding:40px;">No leads available</div>';
            }
        }

        // Lead Sources Chart
        const leadSourceCtx = document.getElementById('leadSourceChart');
        const leadSourceWrapper = document.getElementById('leadSourceChart-wrapper');

        // Remove any previous fallback message
        if (leadSourceWrapper) {
            const prevFallback = leadSourceWrapper.querySelector('.chart-fallback');
            if (prevFallback) prevFallback.remove();
        }

        // Check if we have meaningful data (array with length > 0 and at least one count > 0)
        const hasSourceData = Array.isArray(aggregatedAnalyticsData?.detailed_source_counts)
            && aggregatedAnalyticsData.detailed_source_counts.length > 0
            && aggregatedAnalyticsData.detailed_source_counts.some(item => parseInt(item.count) > 0);

        if (leadSourceCtx && hasSourceData) {
            const sourceCounts = aggregatedAnalyticsData.detailed_source_counts;

            setTimeout(() => {
                // Destroy existing chart if it exists
                if (leadSourceChart) {
                    leadSourceChart.destroy();
                }

                leadSourceChart = new Chart(leadSourceCtx, {
                    type: 'doughnut',
                    data: {
                        labels: sourceCounts.map(item => item.source_of_lead),
                        datasets: [{
                            data: sourceCounts.map(item => parseInt(item.count)),
                            backgroundColor: getPaletteForLength(colors.palette, sourceCounts.length),
                            borderWidth: 2,
                            borderColor: colors.borderColor
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    padding: 15,
                                    usePointStyle: true,
                                    color: colors.textColor,
                                    generateLabels: function (chart) {
                                        const data = chart.data;
                                        const currentColors = getChartColors();
                                        if (data.labels.length && data.datasets.length) {
                                            return data.labels.map((label, i) => {
                                                const value = data.datasets[0].data[i];
                                                return {
                                                    text: `${label} (${value})`,
                                                    fillStyle: data.datasets[0].backgroundColor[i],
                                                    strokeStyle: data.datasets[0].borderColor || '#fff',
                                                    fontColor: currentColors.textColor,
                                                    lineWidth: 1,
                                                    hidden: false,
                                                    index: i
                                                };
                                            });
                                        }
                                        return [];
                                    }
                                }
                            },
                            tooltip: {
                                backgroundColor: colors.tooltipBg,
                                titleColor: colors.textColor,
                                bodyColor: colors.textColor,
                                borderColor: colors.tooltipBorder,
                                borderWidth: 1
                            }
                        }
                    }
                });

                hideChartLoader('leadSourceChart');
            }, 250);
        } else {
            // No data or all counts are zero: show fallback message
            if (leadSourceChart) {
                try {
                    leadSourceChart.destroy();
                } catch (e) {
                    console.log('Chart destroy error (ignored):', e);
                }
                leadSourceChart = null;
            }

            hideChartLoader('leadSourceChart');

            if (leadSourceWrapper) {
                leadSourceWrapper.innerHTML = '<div class="chart-fallback" style="display:flex;align-items:center;justify-content:center;min-height:200px;color:#9ca3af;font-size:16px;padding:40px;">No lead sources available</div>';
            }
        }
    }, 50);
}

function createScrollableLegend(chartId, chartInstance) {
    const chartElement = document.getElementById(chartId);
    if (!chartElement) {
        console.warn(`Chart element with id '${chartId}' not found`);
        return;
    }

    const chartContainer = chartElement.closest('.chart-container');
    if (!chartContainer) {
        console.warn(`Chart container not found for chart '${chartId}'`);
        return;
    }

    const existingLegend = chartContainer.querySelector('.custom-legend');

    if (existingLegend) {
        existingLegend.remove();
    }

    const legendContainer = document.createElement('div');
    legendContainer.className = 'custom-legend';
    legendContainer.style.cssText = `
    max-height: 200px;
    overflow-y: auto;
    overflow-x: hidden;
    margin-top: 15px;
    padding: 10px;
    background: var(--bg-color, #f8f9fa);
    border-radius: 8px;
    border: 1px solid var(--border-color, #dee2e6);
  `;

    const data = chartInstance.data;
    if (data.labels.length && data.datasets.length) {
        data.labels.forEach((label, i) => {
            const value = data.datasets[0].data[i];
            const color = data.datasets[0].backgroundColor[i];

            const legendItem = document.createElement('div');
            legendItem.style.cssText = `
        display: flex;
        align-items: center;
        padding: 5px 0;
        font-size: 12px;
      `;

            legendItem.innerHTML = `
        <span style="
          width: 12px;
          height: 12px;
          background: ${color};
          border-radius: 2px;
          margin-right: 8px;
          display: inline-block;
        "></span>
        <span style="flex: 1; min-width: 0;">
          <span title="${label}" style="
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: block;
          ">${label}</span>
        </span>
        <span style="margin-left: 8px; font-weight: bold;">${value}</span>
      `;

            legendContainer.appendChild(legendItem);
        });
    }

    chartContainer.appendChild(legendContainer);

    // Hide the default chart.js legend
    chartInstance.options.plugins.legend.display = false;
    chartInstance.update();
}

// Usage - call this after creating your chart:
// createScrollableLegend('leadSourceChart', leadSourceChart);

function groupSmallSources(sourceCounts, threshold = 5) {
    const mainSources = [];
    const otherSources = [];
    let otherCount = 0;

    sourceCounts.forEach(source => {
        if (source.count >= threshold) {
            mainSources.push(source);
        } else {
            otherSources.push(source.source_of_lead);
            otherCount += source.count;
        }
    });

    if (otherCount > 0) {
        mainSources.push({
            source_of_lead: 'Other',
            count: otherCount,
            sources: otherSources // Keep track of what's included
        });
    }

    return mainSources;
}

// Usage example (commented out):
// const groupedSources = groupSmallSources(sourceCounts, 3); // Group sources with less than 3 counts
// leadSourceData = {
//   labels: groupedSources.map(item => item.source_of_lead),
//   datasets: [{
//     data: groupedSources.map(item => parseInt(item.count)),
//     backgroundColor: groupedSources.map((item, index) => 
//       item.source_of_lead === 'Other' ? '#9ca3af' : colors.palette[index % colors.palette.length]
//     )
//   }]
// };

function calculateQualityRange(statusHistory) {
    if (!statusHistory || !Array.isArray(statusHistory) || statusHistory.length === 0) {
        return 0;
    }

    // Sort history by timestamp to ensure chronological order
    const sortedHistory = [...statusHistory].sort((a, b) =>
        new Date(a.timestamp) - new Date(b.timestamp)
    );

    // Status categories
    const zeroQualityStatuses = ['Pending', 'Already Booked', 'Not Interested'];
    const nonQualityStatuses = ['RNR', 'Not Connected', ...zeroQualityStatuses];

    // Check first status
    const firstStatus = sortedHistory[0].status;

    // If first status is in zero quality statuses, return 0
    if (zeroQualityStatuses.includes(firstStatus)) {
        return 0;
    }

    // If first status is RNR or Not Connected, check second status
    if (['RNR', 'Not Connected'].includes(firstStatus)) {
        if (sortedHistory.length > 1) {
            const secondStatus = sortedHistory[1].status;
            // If second status is NOT in non-quality statuses, return 1
            if (!nonQualityStatuses.includes(secondStatus)) {
                return 1;
            }
        }
        return 0;
    }

    // If first status is other than the above (good status), return 1
    return 1;
}

// Function to fetch and calculate quality range for a user
async function fetchUserQualityRange(userTablename, startDate = null, endDate = null, month = null, year = null) {
    try {
        let url = `get_user_quality_range.php?user_id=${encodeURIComponent(userTablename)}&date_column=${encodeURIComponent(getActiveDateColumn())}`;

        // Add date parameters
        if (startDate && endDate) {
            url += `&start_date=${encodeURIComponent(startDate)}&end_date=${encodeURIComponent(endDate)}`;
        } else if (month && year) {
            url += `&month=${encodeURIComponent(month)}&year=${encodeURIComponent(year)}`;
        }

        const response = await fetch(url);
        const data = await response.json();

        if (data.status === 'success') {
            return data.quality_range || 0;
        } else {
            console.error('Error fetching quality range:', data.message);
            return 0;
        }
    } catch (error) {
        console.error('Error fetching quality range:', error);
        return 0;
    }
}

// Function to fetch quality range for multiple users in batch using the new chunked API
async function fetchBatchQualityRange(users, startDate = null, endDate = null, month = null, year = null) {
    const qualityRangeMap = new Map();

    if (!users || users.length === 0) return qualityRangeMap;

    let baseFilters = {
        date_column: getActiveDateColumn()
    };

    if (startDate && endDate) {
        baseFilters.start_date = startDate;
        baseFilters.end_date = endDate;
    } else if (month && year) {
        baseFilters.month = month;
        baseFilters.year = year;
    }

    // Process in batches of 50 to drastically reduce HTTP overhead
    const batchSize = 50;
    for (let i = 0; i < users.length; i += batchSize) {
        const batch = users.slice(i, i + batchSize);
        const userIds = batch.map(u => u.tablename).join(',');

        const filters = { ...baseFilters, user_ids: userIds };
        // Assuming buildDashboardUrl returns a proper string appending to the script name
        const urlParams = new URLSearchParams(filters);
        const url = `batch_quality_range.php?${urlParams.toString()}`;

        try {
            const response = await fetch(url);
            const data = await response.json();

            if (data.status === 'success' && data.data) {
                // Populate the map
                Object.entries(data.data).forEach(([userId, qualityRange]) => {
                    qualityRangeMap.set(userId, parseFloat(qualityRange) || 0);
                });
            }
        } catch (error) {
            console.error('Error fetching batch quality range:', error);
        }

        // Add fallback data for any failed users
        batch.forEach(user => {
            if (!qualityRangeMap.has(user.tablename)) {
                qualityRangeMap.set(user.tablename, 0);
            }
        });
    }

    return qualityRangeMap;
}



// Update the generateUserCards function to include counts in individual user chart legends
async function generateUserCards() {
    console.log('🔄 generateUserCards() called - Project filter:', Array.from(selectedProjectNames));

    const usersGrid = document.getElementById('usersGrid');
    if (!usersGrid) {
        console.log('Users grid not found');
        return;
    }

    // Clear existing cards first to prevent duplicates
    usersGrid.innerHTML = '';

    // Use allUsersData which now contains the same detailed performance data
    let usersToDisplay = allUsersData && allUsersData.length > 0 ? allUsersData : userData;

    if (!usersToDisplay || usersToDisplay.length === 0) {
        console.log('No user data available');
        usersGrid.innerHTML = '<div class="no-users-message">No user data available</div>';
        return;
    }

    // Apply project filters to data but keep all users visible
    if (typeof selectedProjectNames !== 'undefined' && selectedProjectNames.size > 0) {
        console.log('Project filters applied - users will show with filtered data:', Array.from(selectedProjectNames));
        console.log('All users will be displayed with project-filtered counts');
        // Note: The backend data fetching already applies project filters to the counts
        // so all users will show up with their filtered data (including 0 counts)
    } else {
        console.log('No project filter applied, showing all users with full data');
    }

    if (usersToDisplay.length === 0) {
        console.log('No users found in hierarchy');
        usersGrid.innerHTML = '<div class="no-users-message">No users found in your hierarchy</div>';
        return;
    }

    console.log('Generating cards for', usersToDisplay.length, 'users in normal view (lazy scroll)');
    usersGrid.innerHTML = '';

    // Store data globally so scroll handler can load more batches
    window.__allUserCardsData = usersToDisplay;
    window.__userCardsRenderedCount = 0;

    // Get current date filters for quality range calculation
    const popupMonthSelect = document.getElementById('popupMonthSelect');
    const popupYearSelect = document.getElementById('popupYearSelect');
    const popupDateRangeFilter = document.getElementById('popupDateRangeFilter');
    const popupStartDate = document.getElementById('popupStartDate');
    const popupEndDate = document.getElementById('popupEndDate');

    let startDate = null, endDate = null, month = null, year = null;

    if (popupDateRangeFilter && popupDateRangeFilter.style.display !== 'none') {
        startDate = popupStartDate?.value;
        endDate = popupEndDate?.value;
    } else if (popupMonthSelect && popupYearSelect) {
        month = popupMonthSelect.value;
        year = popupYearSelect.value;
    }

    const toSafeInt = (value) => {
        const n = Number(value);
        return Number.isFinite(n) ? Math.max(0, Math.floor(n)) : 0;
    };

    // Speed: render cards immediately using server-provided user_wise_data payload,
    // then enhance QR/FSV/SVD + per-user charts asynchronously ON DEMAND per scroll batch.
    
    const buildUserCard = (user, index) => {
        const existingCard = usersGrid.querySelector(`[data-user-id="${user.tablename}"]`);
        if (existingCard) return null;

        // Use server-provided user_wise_data values for fast initial paint
        const leadsCount = toSafeInt(user.leads);
        const bookingsCount = toSafeInt(user.bookings);
        const eoiCount = toSafeInt(user.eoi);
        const cancelledCount = toSafeInt(user.cancelled_bookings);

        // FIXED: Use server-provided QR/FSV/SVD directly so initial render shows correct values
        const qualityRange = toSafeInt(user.quality_range);
        const fsvCount = toSafeInt(user.fsv_count);
        const svdCount = toSafeInt(user.svd_count);

        // FIXED: Resolve chart data from multiple possible locations (array format required)
        const chartData = (() => {
            const candidates = [
                user.detailed_status_counts,
                user.leadStatus,
                user.analytics?.detailed_status_counts
            ];
            for (const c of candidates) {
                if (Array.isArray(c) && c.length > 0) return c;
            }
            return [];
        })();
        const convertedLeads = chartData.find(item => item.status && item.status.toLowerCase() === 'converted')?.count || 0;
        const conversionRate = leadsCount > 0 ? ((convertedLeads / leadsCount) * 100).toFixed(1) : '0.0';

        const roleBadge = getRoleBadge(user.user_type);
        const badgeColor = roleBadge.color;
        const badgeText = roleBadge.badge;
        const userType = roleBadge.role;

        const userCard = document.createElement('div');
        userCard.className = 'user-card';
        userCard.setAttribute('data-user-id', user.tablename);

        userCard.innerHTML = `
            <div class="user-header">
                <div class="user-name-container" style="display: flex; align-items: center; gap: 8px; margin-bottom: 5px;">
                    <div class="user-type-badge" style="
                        background: ${badgeColor};
                        color: white;
                        width: 24px;
                        height: 20px;
                        border-radius: 4px;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        font-size: 10px;
                        font-weight: bold;
                    " title="${userType.toUpperCase()}">${badgeText}</div>
                    <div class="user-name" style="font-weight: 600;">${user.name || user.username}</div>
                </div>
                <div class="user-email" >${user.email || user.useremail}</div>
                <div class="user-stats" style=" grid-template-columns: repeat(4, 1fr); gap: 8px; margin-top: 15px;">
                    <div class="user-stat" style="text-align: center;">
                        <div class="label" style="font-size: 11px; color: #6b7280; margin-bottom: 4px;">Leads</div>
                        <div class="value" data-metric="leads" style="font-size: 16px; font-weight: bold; color: #1f2937;">${leadsCount}</div>
                    </div>
                    <div class="user-stat" style="text-align: center;">
                        <div class="label" style="font-size: 11px; color: #6b7280; margin-bottom: 4px;">QR</div>
                        <div class="value" data-metric="qr" style="font-size: 16px; font-weight: bold; color: #1f2937;">${qualityRange}</div>
                    </div>
                    <div class="user-stat" style="text-align: center;">
                        <div class="label" style="font-size: 11px; color: #6b7280; margin-bottom: 4px;">FSV</div>
                        <div class="value" data-metric="fsv" style="font-size: 16px; font-weight: bold; color: #f59e0b;">${fsvCount}</div>
                    </div>
                    <div class="user-stat" style="text-align: center;">
                        <div class="label" style="font-size: 11px; color: #6b7280; margin-bottom: 4px;">SVD</div>
                        <div class="value" data-metric="svd" style="font-size: 16px; font-weight: bold; color: #8b5cf6;">${svdCount}</div>
                    </div>
                    <div class="user-stat" style="text-align: center;">
                        <div class="label" style="font-size: 11px; color: #6b7280; margin-bottom: 4px;">EOI</div>
                        <div class="value" data-metric="eoi" style="font-size: 16px; font-weight: bold; color: #2563eb;">${eoiCount}</div>
                    </div>
                    <div class="user-stat" style="text-align: center;">
                        <div class="label" style="font-size: 11px; color: #6b7280; margin-bottom: 4px;">Bookings</div>
                        <div class="value" data-metric="bookings" style="font-size: 16px; font-weight: bold; color: #059669;">${bookingsCount}</div>
                    </div>
                    <div class="user-stat" style="text-align: center;">
                        <div class="label" style="font-size: 11px; color: #6b7280; margin-bottom: 4px;">Cancelled</div>
                        <div class="value" data-metric="cancelled" style="font-size: 16px; font-weight: bold; color: #dc2626;">${cancelledCount}</div>
                    </div>
                    <div class="user-stat" style="text-align: center;">
                        <div class="label" style="font-size: 11px; color: #6b7280; margin-bottom: 4px;">CR</div>
                        <div class="value" data-metric="cr" style="font-size: 16px; font-weight: bold; color: #7c3aed;" title="Converted: ${convertedLeads}/${leadsCount}">${conversionRate}%</div>
                    </div>
                </div>
            </div>
            <div class="user-chart filtered-chart-container" style="position: relative; margin-top: 15px;">
                <div class="chart-wrapper">
                    <canvas id="normalUserChart${index}"></canvas>
                </div>
                <div class="chart-placeholder" style="display:flex;align-items:center;justify-content:center;color:#9ca3af;font-size:12px;">Loading chart...</div>
            </div>
        `;

        const userWithData = {
            ...user,
            leads: leadsCount,
            bookings: bookingsCount,
            eoi: eoiCount,
            cancelled_bookings: cancelledCount,
            leadStatus: chartData
        };

        return { card: userCard, userWithData };
    };

    const INITIAL_BATCH = 2;
    const SCROLL_BATCH = 2;
    let chartQueue = [];

    const renderBatch = async (batchSize) => {
        const start = window.__userCardsRenderedCount || 0;
        if (start >= usersToDisplay.length) return;

        const end = Math.min(start + batchSize, usersToDisplay.length);
        const frag = document.createDocumentFragment();
        const batchChartItems = [];
        const batchUsersToDisplay = [];

        for (let i = start; i < end; i++) {
            const built = buildUserCard(usersToDisplay[i], i);
            if (!built) continue;
            frag.appendChild(built.card);
            batchChartItems.push({ user: built.userWithData, index: i });
            batchUsersToDisplay.push(usersToDisplay[i]);
        }

        usersGrid.appendChild(frag);
        window.__userCardsRenderedCount = end;

        // Wait for the browser to compute layout so canvases have dimensions
        // before Chart.js tries to render into them.
        await new Promise(res => requestAnimationFrame(() => requestAnimationFrame(res)));

        // Trigger the async enhancement specifically for only these N cards
        const batchQualityRangePromise = fetchBatchQualityRange(batchUsersToDisplay, startDate, endDate, month, year).catch(() => new Map());
        const batchPerformancePromise = fetchBatchUserPerformanceData(batchUsersToDisplay).catch(() => new Map());
        
        Promise.all([batchQualityRangePromise, batchPerformancePromise]).then(([qualityRangeMap, performanceDataMap]) => {
            try {
                batchUsersToDisplay.forEach((user, localIdx) => {
                    const idx = start + localIdx;
                    const card = usersGrid.querySelector(`[data-user-id="${user.tablename}"]`);
                    if (!card) return;

                    const perf = performanceDataMap.get(user.tablename) || {};
                    const leads = toSafeInt(perf.myLeads ?? user.leads);
                    const qrRaw = toSafeInt(qualityRangeMap.get(user.tablename));
                    const qr = Math.min(qrRaw, leads);
                    const fsv = toSafeInt(perf.fsv_count ?? user.fsv_count);
                    const svd = toSafeInt(perf.svd_count ?? user.svd_count);

                    const setMetric = (key, value) => {
                        const el = card.querySelector(`[data-metric="${key}"]`);
                        if (el) el.textContent = String(value);
                    };

                    setMetric('leads', leads);
                    setMetric('bookings', toSafeInt(perf.total_bookings ?? user.bookings));
                    setMetric('eoi', toSafeInt(perf.total_eoi ?? user.eoi));
                    setMetric('cancelled', toSafeInt(perf.cancelled_bookings ?? user.cancelled_bookings));
                    setMetric('qr', qr);
                    setMetric('fsv', fsv);
                    setMetric('svd', svd);

                    const freshChartData = (() => {
                        const candidates = [
                            perf.detailed_status_counts,
                            perf.analytics?.detailed_status_counts,
                            user.detailed_status_counts,
                            user.leadStatus
                        ];
                        for (const c of candidates) {
                            if (Array.isArray(c) && c.length > 0) return c;
                        }
                        return null;
                    })();

                    if (freshChartData) {
                        const convertedLeads = freshChartData.find(item => item.status && item.status.toLowerCase() === 'converted')?.count || 0;
                        const crEl = card.querySelector('[data-metric="cr"]') || card.querySelector('.value[title]');
                        if (crEl && leads > 0) {
                            const cr = ((convertedLeads / leads) * 100).toFixed(1);
                            crEl.textContent = cr + '%';
                            crEl.title = `Converted: ${convertedLeads}/${leads}`;
                        }

                        const userWithFreshData = {
                            ...user,
                            leads,
                            leadStatus: freshChartData
                        };
                        // load chart safely now that DOM has fresh counts
                        setTimeout(() => loadUserChartData(userWithFreshData, idx, 'normal'), localIdx * 50);
                    } else {
                        // Load chart conventionally from existing data
                        setTimeout(() => loadUserChartData(batchChartItems[localIdx].user, idx, 'normal'), localIdx * 50);
                    }
                });
            } catch (e) {
                console.warn('Batch async enhancement pass failed:', e);
            }
        });

        // Initialize standard charts fallbacks
        batchChartItems.forEach((item, idx) => {
            // Already handled in the async enhancement block, but kept in queue if needed later
            chartQueue.push(item);
        });

        // Keep "Total Users" and other stats in sync
        try {
            if (typeof updateStatsFromVisibleCards === 'function') {
                setTimeout(() => {
                    updateStatsFromVisibleCards();
                }, 0);
            }
        } catch (e) {
            console.warn('Failed to update stats after rendering user batch:', e);
        }
    };

    // Initial batch for fast first-paint
    await renderBatch(Math.min(INITIAL_BATCH, usersToDisplay.length));

    // Use an IntersectionObserver with a sentinel so that
    // "load more" works as the user scrolls inside the popup.
    const existingSentinel = document.getElementById('userCardsSentinel');
    if (existingSentinel && existingSentinel.parentElement) {
        existingSentinel.parentElement.removeChild(existingSentinel);
    }

    const sentinel = document.createElement('div');
    sentinel.id = 'userCardsSentinel';
    sentinel.style.height = '1px';
    sentinel.style.width = '100%';
    usersGrid.parentElement.appendChild(sentinel);

    if (window.__userCardsObserver) {
        window.__userCardsObserver.disconnect();
    }

    // Use the dashboard popup as the scroll root so the observer
    // fires correctly when the user scrolls inside the popup overlay.
    const scrollRoot = getDashboardScrollRoot(usersGrid);
    let isLoadingBatch = false;
    const observer = new IntersectionObserver(async (entries) => {
        const visible = entries.some(entry => entry.isIntersecting);
        if (!visible || isLoadingBatch) return;

        isLoadingBatch = true;
        console.log('Sentinel visible, loading more user cards...');

        await renderBatch(SCROLL_BATCH);

        // Stop observing when all users rendered
        if (window.__userCardsRenderedCount >= usersToDisplay.length) {
            observer.disconnect();
            const indicator = document.getElementById('scrollDownIndicator');
            if (indicator) indicator.style.display = 'none';
        }
        isLoadingBatch = false;
    }, {
        root: scrollRoot,
        rootMargin: '0px 0px 400px 0px',
        threshold: 0.01
    });

    window.__userCardsObserver = observer;
    observer.observe(sentinel);

    if (window.__userCardsNearBottomListener && window.__userCardsNearBottomTarget) {
        window.__userCardsNearBottomTarget.removeEventListener('scroll', window.__userCardsNearBottomListener);
    }
    if (scrollRoot) {
        const nearBottomLoader = async () => {
            const maxScrollTop = Math.max(0, scrollRoot.scrollHeight - scrollRoot.clientHeight);
            const remaining = maxScrollTop - scrollRoot.scrollTop;
            if (remaining < 260 && !isLoadingBatch && (window.__userCardsRenderedCount < usersToDisplay.length)) {
                isLoadingBatch = true;
                await renderBatch(SCROLL_BATCH);
                if (window.__userCardsRenderedCount >= usersToDisplay.length) {
                    observer.disconnect();
                    const indicator = document.getElementById('scrollDownIndicator');
                    if (indicator) indicator.style.display = 'none';
                }
                isLoadingBatch = false;
            }
        };
        scrollRoot.addEventListener('scroll', nearBottomLoader, { passive: true });
        window.__userCardsNearBottomListener = nearBottomLoader;
        window.__userCardsNearBottomTarget = scrollRoot;
    }

    // --- SCROLL DOWN INDICATOR ---
    // Remove old indicator if it exists
    let oldIndicator = document.getElementById('scrollDownIndicator');
    if (oldIndicator) oldIndicator.remove();

    // Only add indicator if there are more users than what's initially rendered
    if (usersToDisplay.length > INITIAL_BATCH) {
        // We attach to the scrollRoot directly using position: sticky. 
        // This guarantees it stays fixed to the bottom-right of the scroll viewport.
        const indicatorContainer = document.createElement('div');
        indicatorContainer.id = 'scrollDownIndicator';
        indicatorContainer.style.cssText = `
            position: sticky;
            bottom: 30px;
            margin-left: auto;
            margin-right: 30px;
            width: 45px;
            height: 45px;
            z-index: 9999;
            pointer-events: none;
            transform: translateY(-30px); /* Adjust vertical alignment if needed */
        `;

        indicatorContainer.innerHTML = `
            <div id="scrollDownBtn" style="
                background: #10b981;
                color: white;
                width: 45px;
                height: 45px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                pointer-events: auto; /* Re-enable clicks on the button itself */
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.2), 0 2px 4px -1px rgba(0, 0, 0, 0.1);
                transition: opacity 0.3s ease, transform 0.3s ease;
                animation: bounce 2s infinite;
            ">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path>
                </svg>
            </div>
            <style>
                @keyframes bounce {
                    0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
                    40% { transform: translateY(-10px); }
                    60% { transform: translateY(-5px); }
                }
            </style>
        `;

        // Attach to the scrolling container so sticky positioning works relative to its scroll viewport
        if (scrollRoot) {
            scrollRoot.appendChild(indicatorContainer);
        } else {
            document.body.appendChild(indicatorContainer);
        }
        const btn = indicatorContainer.querySelector('#scrollDownBtn');

        // Click to scroll down
        btn.addEventListener('click', () => {
            smoothScrollDownDashboard(scrollRoot, 440);
        });

        // Hide indicator when scrolled to absolute bottom
        const handleScroll = () => {
            const target = scrollRoot || window;
            const scrollHeight = target.scrollHeight || document.documentElement.scrollHeight;
            const scrollTop = target.scrollTop || document.documentElement.scrollTop;
            const clientHeight = target.clientHeight || document.documentElement.clientHeight;

            // Allow a small buffer (50px) for bottom detection
            const isAtBottom = scrollHeight - scrollTop - clientHeight < 50;
            const allRendered = window.__userCardsRenderedCount >= usersToDisplay.length;

            if (isAtBottom && allRendered) {
                btn.style.opacity = '0';
                btn.style.pointerEvents = 'none';
            } else {
                btn.style.opacity = '1';
                btn.style.pointerEvents = 'auto';
            }
        };

        if (scrollRoot) {
            scrollRoot.addEventListener('scroll', handleScroll);
        } else {
            window.addEventListener('scroll', handleScroll);
        }

        // Clean up scroll listener on re-render
        if (window.__indicatorScrollListener) {
            if (scrollRoot) scrollRoot.removeEventListener('scroll', window.__indicatorScrollListener);
            else window.removeEventListener('scroll', window.__indicatorScrollListener);
        }
        window.__indicatorScrollListener = handleScroll;
    }

    // Note: The async enhancement pass (QR/FSV/SVD + chart redraw) is now 
    // lazily evaluated exclusively inside renderBatch() per-scroll to prevent hitting
    // the backend with thousands of queries on initial page load.

    console.log(`✅ Generated ${usersToDisplay.length} user cards in normal view with performance data`);
    console.log('🔄 Project filtering status:', selectedProjectNames.size > 0 ? `Active (${Array.from(selectedProjectNames).join(', ')})` : 'Inactive');

    // CRITICAL FIX: Only call updateStatsFromVisibleCards if NO user filters are active
    // If user filters are active, stats have already been correctly updated by updatePopupDashboard with priority 2
    // Calling updateStatsFromVisibleCards here would overwrite correct stats with wrong data
    const hasUserFilter = selectedUsers && selectedUsers.size > 0;
    const hasProjectFilter = selectedProjectNames && selectedProjectNames.size > 0;

    if (!hasUserFilter && !hasProjectFilter) {
        console.log('✅ No filters active - will update stats from visible cards');
        setTimeout(() => {
            updateStatsFromVisibleCards();
        }, Math.min(usersToDisplay.length * 50, 1000));
    } else {
        console.log('⚠️ Filters active - SKIPPING updateStatsFromVisibleCards (stats already correct from server)');
        console.log('   User filters:', hasUserFilter ? `${selectedUsers.size} users` : 'none');
        console.log('   Project filters:', hasProjectFilter ? `${selectedProjectNames.size} projects` : 'none');
    }
}


// Update the updateOverallStatusChart function to include counts in filtered chart legends
function updateOverallStatusChart(statusCounts) {
    console.log('updateOverallStatusChart called with:', statusCounts);
    console.log('overallLeadStatusChart exists:', !!overallLeadStatusChart);

    if (!overallLeadStatusChart || !statusCounts || Object.keys(statusCounts).length === 0) {
        console.log('Cannot update status chart - missing chart or empty data');
        return;
    }

    const colors = getChartColors();
    // Use centralized status colors for consistency
    const statusColors = getStatusColors();

    const labels = Object.keys(statusCounts);
    // Coerce to numeric and avoid accidental object concatenation
    const data = labels.map(k => {
        const v = statusCounts[k];
        if (typeof v === 'number') return v;
        if (v && typeof v === 'object') {
            const n = parseInt(v.count ?? v.total ?? 0, 10);
            return Number.isFinite(n) ? n : 0;
        }
        const n = parseInt(v, 10);
        return Number.isFinite(n) ? n : 0;
    });
    const backgroundColors = labels.map(status => statusColors[status] || '#9ca3af');

    // Update chart data
    overallLeadStatusChart.data.labels = labels;
    overallLeadStatusChart.data.datasets[0].data = data;
    overallLeadStatusChart.data.datasets[0].backgroundColor = backgroundColors;

    // Update legend labels to include counts
    overallLeadStatusChart.options.plugins.legend.labels.generateLabels = function (chart) {
        const chartData = chart.data;
        const currentColors = getChartColors();
        if (chartData.labels.length && chartData.datasets.length) {
            return chartData.labels.map((label, i) => {
                const value = chartData.datasets[0].data[i];
                return {
                    text: `${label} (${value})`,
                    fillStyle: chartData.datasets[0].backgroundColor[i],
                    strokeStyle: chartData.datasets[0].borderColor || '#fff',
                    fontColor: currentColors.textColor,
                    lineWidth: 1,
                    hidden: false,
                    index: i
                };
            });
        }
        return [];
    };

    // Update chart with animation
    overallLeadStatusChart.update('active');

    console.log('Updated overall status chart with filtered data:', statusCounts);
}

// Update the resetChartsToOriginalData function to maintain counts in legends
function resetChartsToOriginalData() {
    if (!aggregatedAnalyticsData) {
        console.log('No original aggregated data available for reset');
        return;
    }

    console.log('Resetting charts to original aggregated data');

    // Clear filtered data so Excel export uses original data
    currentFilteredAnalyticsData = null;
    currentFilteredUserTablenames = null;

    // Use the common function to update both charts with original data
    updateChartsWithAggregatedData(aggregatedAnalyticsData);
}

// Make sure all functions are globally available
window.handleOptionClick = handleOptionClick;
window.selectAllUsers = selectAllUsers;
window.clearAllUsers = clearAllUsers;
window.removeUser = removeUser;
window.updateChartsWithFilteredData = updateChartsWithFilteredData;
window.toggleRoleFilter = toggleRoleFilter;
window.selectRole = selectRole;

// Booking popup functions
function openBookingPopup() {
    console.log('Opening booking popup...');
    proceedWithPopupOpen();
}

function proceedWithPopupOpen() {
    // Show the popup
    const popup = document.getElementById('bookingPopup');
    if (popup) {
        popup.classList.remove('hidden');

        // Update the popup title to show which period we're viewing
        const popupTitle = popup.querySelector('.popup-title');
        if (popupTitle) {
            const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
                'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            const startDateInput = document.getElementById('popupStartDate');
            const endDateInput = document.getElementById('popupEndDate');

            if (startDateInput && endDateInput && startDateInput.value && endDateInput.value) {
                popupTitle.textContent = `Bookings (${startDateInput.value} to ${endDateInput.value})`;
            } else if (lastStartDate && lastEndDate) {
                popupTitle.textContent = `Bookings (${lastStartDate} to ${lastEndDate})`;
            } else {
                const monthName = monthNames[currentSelectedMonth - 1];
                popupTitle.textContent = `Monthly Bookings (${monthName} ${currentSelectedYear})`;
            }
        }

        // Show loading state
        const tableBody = document.getElementById('booking-table-body');
        if (tableBody) {
            tableBody.innerHTML = '<tr><td colspan="10" style="text-align: center;">Loading booking data...</td></tr>';
        }

        // Fetch and display booking data
        fetchBookingData();
    }
}

function showUserContextError() {
    const popup = document.getElementById('bookingPopup');
    if (popup) {
        popup.classList.remove('hidden');
        const tableBody = document.getElementById('booking-table-body');
        if (tableBody) {
            tableBody.innerHTML = '<tr><td colspan="10" style="text-align: center; color: red;">Error: Unable to determine user context. Please refresh the page and try again.</td></tr>';
        }
    }
}


function closeBookingPopup() {
    const popup = document.getElementById('bookingPopup');
    if (popup) {
        popup.classList.add('hidden');
    }
}

// Add this function to aggregate data from visible user cards
function aggregateVisibleCardsData() {
    const visibleCards = document.querySelectorAll('.user-card:not([style*="display: none"])');

    let totalLeads = 0;
    let totalBookings = 0;
    let totalEOI = 0;
    let totalCancelled = 0;
    let totalQR = 0;
    let totalFSV = 0;
    let totalSVD = 0;

    console.log('Aggregating data from', visibleCards.length, 'visible cards');

    // Get the actual user data for visible cards by parsing the DOM values
    // This ensures we get the exact same values displayed in the user cards
    const visibleUserIds = Array.from(visibleCards).map(card => {
        return card.getAttribute('data-user-id');
    }).filter(id => id);

    console.log('Visible user IDs:', visibleUserIds);

    // Parse the actual values from the DOM to ensure accuracy
    visibleCards.forEach(card => {
        const userId = card.getAttribute('data-user-id');
        console.log(`Processing card for user: ${userId}`);

        // Find user stats within the card using more specific selectors
        const userStats = card.querySelectorAll('.user-stat');

        userStats.forEach(stat => {
            const label = stat.querySelector('.label')?.textContent?.trim();
            const value = parseInt(stat.querySelector('.value')?.textContent) || 0;

            switch (label) {
                case 'Leads':
                    totalLeads += value;
                    console.log(`Added ${value} leads from user ${userId}`);
                    break;
                case 'Bookings':
                    totalBookings += value;
                    console.log(`Added ${value} bookings from user ${userId}`);
                    break;
                case 'EOI':
                    totalEOI += value;
                    console.log(`Added ${value} EOI from user ${userId}`);
                    break;
                case 'Cancelled':
                    totalCancelled += value;
                    console.log(`Added ${value} cancelled from user ${userId}`);
                    break;
                case 'QR':
                    totalQR += value;
                    break;
                case 'FSV':
                    totalFSV += value;
                    break;
                case 'SVD':
                    totalSVD += value;
                    break;
            }
        });
    });

    const result = {
        totalLeads,
        totalBookings,
        totalEOI,
        totalCancelledBookings: totalCancelled,
        totalQR,
        totalFSV,
        totalSVD,
        totalUsers: visibleCards.length
    };

    console.log('Final aggregated data:', result);
    return result;
}

// Enhanced function to update stats from visible cards
function updateStatsFromVisibleCards() {
    console.log('=== updateStatsFromVisibleCards CALLED ===');

    // CRITICAL FIX: If we're in hierarchy mode with specific user selected,
    // the stats have already been calculated correctly from performance data
    // We should NOT recalculate from DOM as it may cause incorrect values
    const isHierarchyMode = currentViewMode === 'hierarchy';
    const hasUserFilter = selectedUsers && selectedUsers.size > 0;

    if (isHierarchyMode && hasUserFilter) {
        console.log('SKIPPING updateStatsFromVisibleCards - stats already set correctly for hierarchy mode');
        console.log('Reason:', {
            isHierarchyMode: isHierarchyMode,
            hasUserFilter: hasUserFilter,
            message: 'Stats were calculated from performance data in showTeamView, not from DOM'
        });
        return; // Don't overwrite correctly calculated stats
    }

    const aggregatedData = aggregateVisibleCardsData();

    console.log('Aggregated data from visible cards:', {
        totalUsers: aggregatedData.totalUsers,
        totalLeads: aggregatedData.totalLeads,
        totalBookings: aggregatedData.totalBookings,
        totalEOI: aggregatedData.totalEOI,
        totalCancelledBookings: aggregatedData.totalCancelledBookings
    });

    // Check if project filters are active
    const selectedProjects = getSelectedProjectNames();
    const hasProjectFilters = selectedProjects && selectedProjects.length > 0;

    // Determine if we should show "Filtered" or "Total" based on context
    const totalAvailableUsers = allUsersData ? allUsersData.length : 0;
    const isUserFiltered = aggregatedData.totalUsers < totalAvailableUsers;
    const isActuallyFiltered = isUserFiltered || hasProjectFilters;

    console.log('Filtering check:', {
        visibleUsers: aggregatedData.totalUsers,
        totalUsers: totalAvailableUsers,
        isUserFiltered: isUserFiltered,
        hasProjectFilters: hasProjectFilters,
        isActuallyFiltered: isActuallyFiltered,
        willShowAsFiltered: isActuallyFiltered ? 'Yes' : 'No'
    });

    // CRITICAL: If applyAllFilters() is handling updates, don't interfere with DOM-based calculations
    // DOM-based stats calculation should have LOWER priority than server aggregated data
    // Only update if we're showing all users with no filters (fallback scenario)
    if (!isActuallyFiltered && !hasUserFilter) {
        console.log('Updating stats from visible cards (no filters active, fallback calculation)');
        updateStatsDisplay({
            totalUsers: aggregatedData.totalUsers,
            totalLeads: aggregatedData.totalLeads,
            totalBookings: aggregatedData.totalBookings,
            totalEOI: aggregatedData.totalEOI,
            totalCancelledBookings: aggregatedData.totalCancelledBookings
        }, isActuallyFiltered, { priority: 1, source: 'visible_cards_fallback' });
    } else {
        console.log('SKIPPING updateStatsDisplay - filters active, applyAllFilters() will handle with server data');
    }

    // Also update the header
    const headerH1 = document.querySelector('#dashboardPopup .popup-header h1');
    if (headerH1) {
        headerH1.textContent = `Total Users: ${aggregatedData.totalUsers}`;
    }

    console.log('=== Stats update completed ===');
}

function fetchBookingData() {
    // Get current user context from multiple sources
    let selectedUser = null;

    // Check if a user is selected from the names dropdown
    const namesSelect = document.getElementById('namesSelect') || document.getElementById('name-select');
    if (namesSelect && namesSelect.value) {
        selectedUser = namesSelect.value;
    }

    // If no dropdown selection, use current user variables
    if (!selectedUser) {
        selectedUser = currentlySelectedUser || currentUserTableName;
    }

    // Last resort: try to get current user from other sources
    if (!selectedUser) {
        // Try to find session variables that might be set in the HTML
        if (typeof phpSessionUserTableName !== 'undefined') {
            selectedUser = phpSessionUserTableName;
        }
        // Try to get from a simple fetch request for current user
        // This will be handled asynchronously if needed
    }

    // Debug: Check if we have a valid user
    // Names select element checked silently
    console.log('Current user tablename:', currentUserTableName);
    console.log('Currently selected user:', currentlySelectedUser);
    console.log('Final selected user for query:', selectedUser);
    console.log('Current selected month:', currentSelectedMonth);
    console.log('Current selected year:', currentSelectedYear);

    // Get current date filters - check if we're in date range mode or monthly mode
    const filters = {
        get_bookings: true,
        date_column: getActiveDateColumn()
    };

    if (selectedUser) {
        filters.user = selectedUser;
        console.log('Using selected user:', selectedUser);
    } else {
        console.log('No specific user selected, PHP will use session user');
    }

    const startDateInput = document.getElementById('popupStartDate');
    const endDateInput = document.getElementById('popupEndDate');

    if (startDateInput && endDateInput && startDateInput.value && endDateInput.value) {
        filters.start_date = startDateInput.value;
        filters.end_date = endDateInput.value;
        console.log('Using manager dashboard date range:', startDateInput.value, 'to', endDateInput.value);
    } else if (lastStartDate && lastEndDate) {
        filters.start_date = lastStartDate;
        filters.end_date = lastEndDate;
        console.log('Using main dashboard custom date range:', lastStartDate, 'to', lastEndDate);
    } else {
        const month = currentSelectedMonth || new Date().getMonth() + 1;
        const year = currentSelectedYear || new Date().getFullYear();
        filters.month = month;
        filters.year = year;
        console.log('Using month/year:', month, '/', year);
    }

    const url = buildDashboardUrl('dashboard_data.php', filters);

    console.log('Fetching booking data with URL:', url); // Debug log

    // Show loading state
    const tableBody = document.getElementById('booking-table-body');
    if (tableBody) {
        tableBody.innerHTML = '<tr><td colspan="10" style="text-align: center;">Loading...</td></tr>';
    }

    // Fetch data
    fetch(url)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Booking data response:', data); // Debug log
            if (data.status === 'success' || data.success) {
                const bookings = data.bookings || data.data || [];
                populateBookingTable(bookings);
            } else {
                console.error('Error fetching booking data:', data.message || data.error);
                console.error('Debug info:', data.debug);
                if (tableBody) {
                    const errorMsg = data.message || data.error || 'Error loading data';
                    tableBody.innerHTML = `<tr><td colspan="10" style="text-align: center; color: red;">${errorMsg}</td></tr>`;
                }
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            if (tableBody) {
                tableBody.innerHTML = `<tr><td colspan="10" style="text-align: center; color: red;">Network error: ${error.message}</td></tr>`;
            }
        });
}

// Booking pagination variables
let allBookingsData = [];
let filteredBookingsData = [];
let currentBookingPage = 1;
const bookingsPerPage = 10;

function populateBookingTable(bookings) {
    console.log('Populating table with bookings:', bookings); // Debug log

    // Store all bookings data
    allBookingsData = bookings || [];
    filteredBookingsData = [...allBookingsData];
    currentBookingPage = 1;

    // Set up search functionality
    initializeBookingSearch();

    // Display first page
    displayBookingPage();
}

function initializeBookingSearch() {
    const searchInput = document.getElementById('bookingSearchInput');
    if (searchInput) {
        // Remove any existing event listener
        searchInput.removeEventListener('input', handleBookingSearch);
        // Add new event listener
        searchInput.addEventListener('input', handleBookingSearch);
        // Clear search input when popup opens
        searchInput.value = '';
    }
}

function handleBookingSearch(event) {
    const searchTerm = event.target.value.toLowerCase().trim();

    if (!searchTerm) {
        // If search is empty, show all bookings
        filteredBookingsData = [...allBookingsData];
    } else {
        // Filter bookings based on search term
        filteredBookingsData = allBookingsData.filter(booking => {
            const unit = (booking.unit_no || '').toLowerCase();
            const customer = (booking.customer_name || '').toLowerCase();
            const builder = (booking.builder || '').toLowerCase();
            const project = (booking.project || '').toLowerCase();
            const type = (booking.project_type || '').toLowerCase();

            return unit.includes(searchTerm) ||
                customer.includes(searchTerm) ||
                builder.includes(searchTerm) ||
                project.includes(searchTerm) ||
                type.includes(searchTerm);
        });
    }

    // Reset to first page after search
    currentBookingPage = 1;
    displayBookingPage();
}

function displayBookingPage() {
    const tableBody = document.getElementById('booking-table-body');
    if (!tableBody) {
        console.error('Booking table body not found');
        return;
    }

    if (!filteredBookingsData || filteredBookingsData.length === 0) {
        tableBody.innerHTML = '<tr><td colspan="10" style="text-align: center; padding: 20px; color: #666;">No bookings found</td></tr>';
        updateBookingPaginationInfo(0, 0, 0);
        updateBookingPaginationButtons(1, 1);
        return;
    }

    const totalBookings = filteredBookingsData.length;
    const totalPages = Math.ceil(totalBookings / bookingsPerPage);

    // Ensure current page is within valid range
    if (currentBookingPage > totalPages) {
        currentBookingPage = totalPages;
    }
    if (currentBookingPage < 1) {
        currentBookingPage = 1;
    }

    const startIndex = (currentBookingPage - 1) * bookingsPerPage;
    const endIndex = Math.min(startIndex + bookingsPerPage, totalBookings);
    const pageBookings = filteredBookingsData.slice(startIndex, endIndex);

    let tableHTML = '';
    pageBookings.forEach((booking, index) => {
        tableHTML += `
            <tr>
                <td data-label="Unit">${booking.unit_no || 'N/A'}</td>
                <td data-label="Type">${booking.project_type || 'N/A'}</td>
                <td data-label="Customer Name">${booking.customer_name || 'N/A'}</td>
                <td data-label="Builder">${booking.builder || 'N/A'}</td>
                <td data-label="Project">${booking.project || 'N/A'}</td>
                <td data-label="Agreement Value">₹${booking.agreement_value || '0.00'}</td>
                <td data-label="Total Revenue">₹${booking.revenue || '0.00'}</td>
                <td data-label="Actual Revenue">₹${booking.actual_revenue || '0.00'}</td>
                <td data-label="Cashback">${booking.cashback || '0.00'}%</td>
                <td data-label="Commission">${booking.commission || '0.00'}%</td>
            </tr>
        `;
    });

    tableBody.innerHTML = tableHTML;
    updateBookingPaginationInfo(startIndex + 1, endIndex, totalBookings);
    updateBookingPaginationButtons(currentBookingPage, totalPages);

    console.log(`Displayed page ${currentBookingPage} of ${totalPages} (${pageBookings.length} bookings)`);
}

function updateBookingPaginationInfo(start, end, total) {
    const paginationInfo = document.getElementById('bookingPaginationInfo');
    if (paginationInfo) {
        if (total === 0) {
            paginationInfo.textContent = 'Showing 0 of 0 bookings';
        } else {
            paginationInfo.textContent = `Showing ${start}-${end} of ${total} booking${total !== 1 ? 's' : ''}`;
        }
    }
}

function updateBookingPaginationButtons(currentPage, totalPages) {
    const pageInfo = document.getElementById('bookingPageInfo');
    const firstBtn = document.getElementById('bookingFirstPage');
    const prevBtn = document.getElementById('bookingPrevPage');
    const nextBtn = document.getElementById('bookingNextPage');
    const lastBtn = document.getElementById('bookingLastPage');

    if (pageInfo) {
        pageInfo.textContent = `Page ${currentPage} of ${totalPages}`;
    }

    // Disable/enable buttons based on current page
    if (firstBtn) firstBtn.disabled = currentPage === 1;
    if (prevBtn) prevBtn.disabled = currentPage === 1;
    if (nextBtn) nextBtn.disabled = currentPage === totalPages;
    if (lastBtn) lastBtn.disabled = currentPage === totalPages;

    // Update button styles
    [firstBtn, prevBtn, nextBtn, lastBtn].forEach(btn => {
        if (btn) {
            if (btn.disabled) {
                btn.style.opacity = '0.5';
                btn.style.cursor = 'not-allowed';
            } else {
                btn.style.opacity = '1';
                btn.style.cursor = 'pointer';
            }
        }
    });
}

function goToBookingPage(action) {
    const totalPages = Math.ceil(filteredBookingsData.length / bookingsPerPage);

    switch (action) {
        case 'first':
            currentBookingPage = 1;
            break;
        case 'prev':
            if (currentBookingPage > 1) {
                currentBookingPage--;
            }
            break;
        case 'next':
            if (currentBookingPage < totalPages) {
                currentBookingPage++;
            }
            break;
        case 'last':
            currentBookingPage = totalPages;
            break;
    }

    displayBookingPage();
}

// Make functions globally available
window.openBookingPopup = openBookingPopup;
window.closeBookingPopup = closeBookingPopup;
window.goToBookingPage = goToBookingPage;
window.clearFilterSelections = clearFilterSelections;
window.closeDashboard = closeDashboard;
window.goBackToMyDashboard = goBackToMyDashboard;

// ============================================================================
// LEADS POPUP FUNCTIONALITY
// ============================================================================

// Add click listeners to lead status stat items
document.addEventListener('DOMContentLoaded', function () {
    const pendingStat = document.getElementById('pending-count');
    const followupStat = document.getElementById('followup-count');
    const fixSiteVisitStat = document.getElementById('fix-site-visit-count');
    const siteVisitedStat = document.getElementById('site-visited-count');

    // Add click event listeners
    if (pendingStat) {
        pendingStat.closest('.stat-item').addEventListener('click', function () {
            openLeadsPopupWithFilter('Pending');
        });
        // Add pointer cursor
        pendingStat.closest('.stat-item').style.cursor = 'pointer';
    }

    if (followupStat) {
        followupStat.closest('.stat-item').addEventListener('click', function () {
            openLeadsPopupWithFilter('Follow Up');
        });
        followupStat.closest('.stat-item').style.cursor = 'pointer';
    }

    if (fixSiteVisitStat) {
        fixSiteVisitStat.closest('.stat-item').addEventListener('click', function () {
            openLeadsPopupWithFilter('Fix Site Visit');
        });
        fixSiteVisitStat.closest('.stat-item').style.cursor = 'pointer';
    }

    if (siteVisitedStat) {
        siteVisitedStat.closest('.stat-item').addEventListener('click', function () {
            openLeadsPopupWithFilter('Site Visit Done');
        });
        siteVisitedStat.closest('.stat-item').style.cursor = 'pointer';
    }
});

// Function to open leads popup with status filter
function openLeadsPopupWithFilter(status) {
    console.log('📊 Opening leads popup with status:', status);

    // Get selected user
    const selectedUser = getSelectedUserValue();
    const isLoggedInUser = !selectedUser || selectedUser === currentUserTableName;

    // Build filter data
    const filterData = {
        teamView: isLoggedInUser ? 'off' : 'on'
    };

    // Add status filter only if status is provided (null means all leads)
    if (status !== null && status !== undefined) {
        filterData.status = status;
    }

    // Add user filter only if subordinate is selected
    if (!isLoggedInUser && selectedUser) {
        filterData.filterUser = selectedUser;
    }

    // Get date range - check for custom date range first
    if (lastStartDate && lastEndDate) {
        filterData.start_date = lastStartDate;
        filterData.end_date = lastEndDate;
        console.log('Using custom date range:', lastStartDate, 'to', lastEndDate);
    } else {
        // Use month/year selection
        const monthSelect = document.getElementById("monthSelect");
        const yearSelect = document.getElementById("yearSelect");

        if (monthSelect && yearSelect) {
            const month = monthSelect.value || currentSelectedMonth;
            const year = yearSelect.value || currentSelectedYear;

            if (month && year) {
                // Calculate first and last day of the month
                const startYYYY = year;
                const startMM = String(month).padStart(2, '0');
                const lastDayPopup = new Date(year, month, 0).getDate();
                const endDD = String(lastDayPopup).padStart(2, '0');

                filterData.start_date = `${startYYYY}-${startMM}-01`;
                filterData.end_date = `${startYYYY}-${startMM}-${endDD}`;

                console.log('Using month/year range:', filterData.start_date, 'to', filterData.end_date);
            }
        }
    }

    console.log('Filter data for leads popup:', filterData);

    // Load leads page in popup
    loadLeadsPageInPopup(filterData);
}

// Function to load leads page in popup overlay
function loadLeadsPageInPopup(filterData) {
    try {
        console.log('📱 Creating leads popup overlay');

        // Create container if it doesn't exist
        let leadsContainer = document.getElementById('leads-page-container');
        if (!leadsContainer) {
            leadsContainer = document.createElement('div');
            leadsContainer.id = 'leads-page-container';
            leadsContainer.className = 'leads-page-overlay';
            document.body.appendChild(leadsContainer);
        }

        // Build URL with query parameters
        const baseUrl = 'user_lead';
        const params = new URLSearchParams();

        Object.keys(filterData).forEach(key => {
            if (filterData[key] !== undefined && filterData[key] !== null && filterData[key] !== '') {
                params.append(key, filterData[key]);
            }
        });

        const fullUrl = `${baseUrl}?${params.toString()}`;

        // Show the container with iframe and loader
        leadsContainer.style.display = 'block';
        leadsContainer.innerHTML = `
            <div class="leads-page-wrapper">
                <div class="leads-page-header">
                    <div class="leads-page-title">
                        <h2>Leads</h2>
                        <div class="leads-page-subtitle">
                            ${filterData.status ? `Status: ${filterData.status}` : 'All Leads'}${filterData.filterUser ? ` | User: ${filterData.filterUser}` : ''}${filterData.start_date && filterData.end_date ? ` | ${filterData.start_date} to ${filterData.end_date}` : ''}
                        </div>
                    </div>
                    <div class="leads-page-actions">
                        <button class="open-new-tab-btn" onclick="openLeadsInNewTab('${fullUrl}')" title="Open in New Tab">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M14,3V5H17.59L7.76,14.83L9.17,16.24L19,6.41V10H21V3M19,19H5V5H12V3H5C3.89,3 3,3.9 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19V12H19V19Z" />
                            </svg>
                            New Tab
                        </button>
                        <button class="close-leads-page" onclick="closeLeadsPage()" title="Close">
                            &times;
                        </button>
                    </div>
                </div>
                <div class="leads-iframe-container">
                    <!-- Loading overlay -->
                    <div class="leads-loading-overlay" id="leads-loading-overlay">
                        <div class="loader-container" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.7); display: flex; justify-content: center; align-items: center; z-index: 9999;">
                            <div class="loader-circle" style="border: 4px solid rgba(255, 255, 255, 0.3); border-top: 4px solid #3498db; border-radius: 50%; width: 60px; height: 60px; animation: spin 1s linear infinite; position: relative;">
                                <img src="assets/dataimage/mecntec-icon.png" alt="Mecntec Logo" class="loader-logo" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 30px; height: 30px; border-radius: 50%;">
                            </div>
                        </div>
                    </div>
                    <iframe 
                        src="${fullUrl}" 
                        frameborder="0" 
                        class="leads-iframe"
                        onload="handleLeadsIframeLoad(this)"
                        onerror="handleLeadsIframeError(this)"
                        style="opacity: 0; transition: opacity 0.3s ease;">
                    </iframe>
                </div>
            </div>
            <style>
                .leads-page-overlay {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.5);
                    z-index: 10000;
                    display: none;
                    overflow: hidden;
                }
                .leads-page-wrapper {
                    background: white;
                    margin: 10px;
                    border-radius: 10px;
                    height: calc(100vh - 20px);
                    display: flex;
                    flex-direction: column;
                    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
                }
                .leads-page-header {
                    padding: 15px 20px;
                    border-bottom: 1px solid #eee;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    background: #ffffff;
                    border-radius: 10px 10px 0 0;
                    flex-shrink: 0;
                }
                .leads-page-title h2 {
                    margin: 0;
                    color: black;
                    font-size: 18px;
                }
                .leads-page-subtitle {
                    font-size: 12px;
                    color: black;
                    margin-top: 2px;
                }
                .leads-page-actions {
                    display: flex;
                    gap: 10px;
                    align-items: center;
                }
                .open-new-tab-btn {
                    background: #007bff;
                    color: white;
                    border: none;
                    padding: 8px 12px;
                    border-radius: 6px;
                    cursor: pointer;
                    font-size: 12px;
                    display: flex;
                    align-items: center;
                    gap: 5px;
                    transition: all 0.2s;
                }
                .open-new-tab-btn:hover {
                    background: #0056b3;
                }
                .close-leads-page {
                    background: none;
                    border: none;
                    font-size: 24px;
                    cursor: pointer;
                    color: #666;
                    padding: 5px 10px;
                    border-radius: 4px;
                    line-height: 1;
                }
                .close-leads-page:hover {
                    background: #e9ecef;
                    color: #000;
                }
                .leads-iframe-container {
                    flex: 1;
                    overflow: hidden;
                    background: white;
                    position: relative;
                }
                .leads-iframe {
                    width: 100%;
                    height: 100%;
                    border: none;
                    display: block;
                }
                
                /* Loading overlay styles */
                .leads-loading-overlay {
                    position: absolute;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background-color: rgba(0, 0, 0, 0.7);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    z-index: 1000;
                    border-radius: 0 0 10px 10px;
                }
                
                .loader-container {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background-color: rgba(0, 0, 0, 0.7);
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    z-index: 9999;
                }

                .loader-circle {
                    border: 4px solid rgba(255, 255, 255, 0.3);
                    border-top: 4px solid #3498db;
                    border-radius: 50%;
                    width: 60px;
                    height: 60px;
                    animation: spin 1s linear infinite;
                    position: relative;
                }

                .loader-logo {
                    position: absolute;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    width: 30px;
                    height: 30px;
                    border-radius: 50%;
                }

                @keyframes spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
                
                /* Mobile responsive */
                @media (max-width: 768px) {
                    .leads-page-wrapper {
                        margin: 5px;
                        height: calc(100vh - 10px);
                        border-radius: 8px;
                    }
                    .leads-page-header {
                        padding: 10px 15px;
                        flex-direction: column;
                        gap: 10px;
                        align-items: stretch;
                    }
                    .leads-page-actions {
                        justify-content: space-between;
                    }
                    .leads-page-title h2 {
                        font-size: 16px;
                    }
                    .leads-page-subtitle {
                        font-size: 11px;
                    }
                }

                /* Dark mode overrides for popup overlay */
                .leads-page-overlay.dark-mode {
                    background: rgba(5, 7, 12, 0.8);
                }
                .leads-page-overlay.dark-mode .leads-page-wrapper {
                    background: #0b1220;
                    color: #e5e7eb;
                    box-shadow: 0 8px 30px rgba(0,0,0,0.6);
                }
                .leads-page-overlay.dark-mode .leads-page-header {
                    background: #454545;
                    border-color: #1f2937;
                }
                .leads-page-overlay.dark-mode .leads-page-title h2,
                .leads-page-overlay.dark-mode .leads-page-subtitle {
                    color: #e5e7eb;
                }
                .leads-page-overlay.dark-mode .leads-iframe-container {
                    background: #0f172a;
                }
                .leads-page-overlay.dark-mode .open-new-tab-btn {
                    background: #2563eb;
                }
                .leads-page-overlay.dark-mode .open-new-tab-btn:hover {
                    background: #1d4ed8;
                }
                .leads-page-overlay.dark-mode .close-leads-page {
                    color: #e5e7eb;
                }
                .leads-page-overlay.dark-mode .close-leads-page:hover {
                    background: #1f2937;
                    color: #fff;
                }
            </style>
        `;

        // Apply current theme to the overlay and iframe
        applyLeadsOverlayTheme();

        // Store the URL for the new tab functionality
        window.currentLeadsUrl = fullUrl;

        console.log('✅ Leads page overlay created successfully');

    } catch (error) {
        console.error('❌ Error loading leads page:', error);
        alert('Failed to load leads page');
    }
}

// Sync the popup overlay and iframe with the current theme
function applyLeadsOverlayTheme() {
    const overlay = document.getElementById('leads-page-container');
    if (!overlay) return;

    const isDark = isDarkTheme();
    overlay.classList.toggle('dark-mode', isDark);
    overlay.classList.toggle('light-mode', !isDark);

    // Notify the iframe so it can switch themes internally
    const iframe = overlay.querySelector('.leads-iframe');
    if (iframe && iframe.contentWindow) {
        iframe.contentWindow.postMessage({ type: 'darkMode', enabled: isDark }, '*');
    }
}

// Function to close leads popup
function closeLeadsPage() {
    const leadsContainer = document.getElementById('leads-page-container');
    if (leadsContainer) {
        leadsContainer.style.display = 'none';
        leadsContainer.innerHTML = '';
    }
}

// ========================================
// CHART COLOR INITIALIZATION FOR DARK MODE
// ========================================
// Initialize chart colors when page loads
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
        console.log('Initializing chart colors on DOMContentLoaded');
        // Wait a bit for charts to be created
        setTimeout(function () {
            if (typeof updateChartColors === 'function') {
                updateChartColors();
                console.log('Chart colors updated on page load');
            }
        }, 1000);
    });
} else {
    // DOM already loaded
    console.log('DOM already loaded, initializing chart colors');
    setTimeout(function () {
        if (typeof updateChartColors === 'function') {
            updateChartColors();
            console.log('Chart colors updated immediately');
        }
    }, 1000);
}

// Watch for theme changes
const observer = new MutationObserver(function (mutations) {
    mutations.forEach(function (mutation) {
        if (mutation.type === 'attributes' && mutation.attributeName === 'data-theme') {
            console.log('Theme changed, updating chart colors');
            setTimeout(function () {
                if (typeof updateChartColors === 'function') {
                    updateChartColors();
                    console.log('Chart colors updated after theme change');
                }
                applyLeadsOverlayTheme();
            }, 100);
        }
    });
});

// Start observing theme changes
observer.observe(document.documentElement, {
    attributes: true,
    attributeFilter: ['data-theme']
});

// Also call update colors periodically to catch dynamically created charts
setInterval(function () {
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    if (typeof updateChartColors === 'function' && document.hasFocus()) {
        updateChartColors();
    }
    applyLeadsOverlayTheme();
}, 3000);

console.log('Chart color initialization complete');


// Function to open leads in new tab
function openLeadsInNewTab(url) {
    window.open(url, '_blank');
}

// Handle iframe load event
function handleLeadsIframeLoad(iframe) {
    console.log('✅ Leads iframe loaded successfully');

    // Hide loading overlay
    const loadingOverlay = document.getElementById('leads-loading-overlay');
    if (loadingOverlay) {
        setTimeout(() => {
            loadingOverlay.style.display = 'none';
            iframe.style.opacity = '1';
            // Ensure iframe picks up the current theme
            applyLeadsOverlayTheme();
        }, 500);
    }
}

// Handle iframe error event
function handleLeadsIframeError(iframe) {
    console.error('❌ Leads iframe failed to load');

    const loadingOverlay = document.getElementById('leads-loading-overlay');
    if (loadingOverlay) {
        loadingOverlay.innerHTML = `
            <div style="text-align: center; color: white;">
                <h3>Failed to load leads page</h3>
                <p>Please try again or contact support if the problem persists.</p>
                <button onclick="closeLeadsPage()" style="padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; margin-top: 10px;">
                    Close
                </button>
            </div>
        `;
    }
}

// Make functions globally available
window.openLeadsPopupWithFilter = openLeadsPopupWithFilter;
window.loadLeadsPageInPopup = loadLeadsPageInPopup;
window.closeLeadsPage = closeLeadsPage;
window.openLeadsInNewTab = openLeadsInNewTab;
window.handleLeadsIframeLoad = handleLeadsIframeLoad;
window.handleLeadsIframeError = handleLeadsIframeError;



// ==========================================
// THEME OBSERVER FOR LIVE CHART UPDATES
// ==========================================
(function () {
    // Debounce function to prevent rapid re-renders
    let timeout;
    function debounceChartUpdate() {
        clearTimeout(timeout);
        timeout = setTimeout(() => {
            // Only update if the summary panel charts exist and we have data
            if (typeof aggregatedAnalyticsData !== 'undefined' &&
                document.getElementById('overallLeadStatusChart')) {
                // Re-initialize charts to pick up new theme colors
                // initializeDashboardCharts();
                updateChartColors();
            }
            applyLeadsOverlayTheme();
        }, 200);
    }

    // specific observer for theme changes
    const observer = new MutationObserver(function (mutations) {
        mutations.forEach(function (mutation) {
            if (mutation.type === 'attributes' && mutation.attributeName === 'data-theme') {
                debounceChartUpdate();
            }
        });
    });

    // Start observing the html element
    observer.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['data-theme']
    });
})();

// Download Dashboard Data as Excel
function downloadDashboardExcel() {
    try {
        // Check if XLSX library is loaded
        if (typeof XLSX === 'undefined') {
            alert('Excel library not loaded. Please refresh the page and try again.');
            return;
        }

        // ----------------------------------------------------------------
        // Collect current active filters from popup UI
        // ----------------------------------------------------------------
        const dateSelection = getCurrentPopupDateSelection();

        const filters = {
            aggregated_analytics: true,
            include_detailed_data: true,
            date_column: getActiveDateColumn()
        };

        // Date range or month/year
        if (dateSelection.start_date && dateSelection.end_date) {
            filters.start_date = dateSelection.start_date;
            filters.end_date = dateSelection.end_date;
        } else {
            filters.month = dateSelection.month;
            filters.year = dateSelection.year;
        }

        // Selected main user (e.g. from name-select dropdown)
        const selectedMainUser = currentlySelectedUser || getSelectedUserValue();
        if (selectedMainUser) {
            filters.user_id = selectedMainUser;
        }

        // Multi-selected team members
        if (selectedUsers && selectedUsers.size > 0) {
            filters.filtered_users = Array.from(selectedUsers).join(',');
        }

        // Project filter
        const selectedProjects = getSelectedProjectNames();
        if (selectedProjects && selectedProjects.length > 0) {
            filters.project_filter = selectedProjects.join(',');
        }

        // ----------------------------------------------------------------
        // Build a human-readable label for the filename
        // ----------------------------------------------------------------
        const monthSelect = document.getElementById('popupMonthSelect');
        const yearSelect = document.getElementById('popupYearSelect');
        let monthLabel = '';
        let yearLabel = new Date().getFullYear();

        if (dateSelection.start_date && dateSelection.end_date) {
            monthLabel = `${dateSelection.start_date}_to_${dateSelection.end_date}`;
            yearLabel = '';
        } else {
            monthLabel = monthSelect ? getMonthName(parseInt(monthSelect.value)) : '';
            yearLabel = yearSelect ? yearSelect.value : new Date().getFullYear();
        }

        // ----------------------------------------------------------------
        // Show a brief loading indicator on the download button
        // ----------------------------------------------------------------
        const downloadBtn = document.querySelector('[onclick="downloadDashboardExcel()"]');
        const originalText = downloadBtn ? downloadBtn.innerHTML : '';
        if (downloadBtn) {
            downloadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Downloading...';
            downloadBtn.disabled = true;
        }

        const url = buildDashboardUrl('dashboard_data.php', filters);
        console.log('[Excel Download] Fetching data with filters:', filters);

        fetchData(url, { timeoutMs: 30000 })
            .then(data => {
                if (!data || data.status !== 'success' || !data.aggregated_analytics) {
                    console.error('[Excel Download] Failed to fetch filtered analytics data:', data);
                    alert('Failed to fetch data for the selected filters. Please try again.');
                    return;
                }

                const dataToExport = data.aggregated_analytics;
                console.log('[Excel Download] Data received:', dataToExport);

                generateAndDownloadExcel(dataToExport, monthLabel, yearLabel);
            })
            .catch(err => {
                console.error('[Excel Download] Error:', err);
                alert('Error fetching data for download. Please try again.');
            })
            .finally(() => {
                if (downloadBtn) {
                    downloadBtn.innerHTML = originalText;
                    downloadBtn.disabled = false;
                }
            });

    } catch (error) {
        console.error('Error in downloadDashboardExcel:', error);
        alert('Error generating Excel file. Please check console and try again.');
    }
}

// Core Excel generation — receives already-filtered aggregated analytics data
function generateAndDownloadExcel(dataToExport, monthLabel, yearLabel) {
    try {
        // Create a new workbook
        const wb = XLSX.utils.book_new();

        // ===== SHEET 1: Lead Status Distribution =====
        const statusData = [];
        statusData.push(['Lead Status Distribution']);
        statusData.push([]);
        statusData.push(['Status', 'Count']);

        let statusCounts = [];
        if (dataToExport && dataToExport.detailed_status_counts) {
            statusCounts = dataToExport.detailed_status_counts;
        } else if (overallLeadStatusChart && overallLeadStatusChart.data) {
            const labels = overallLeadStatusChart.data.labels || [];
            const dataValues = overallLeadStatusChart.data.datasets && overallLeadStatusChart.data.datasets[0]
                ? overallLeadStatusChart.data.datasets[0].data : [];
            statusCounts = labels.map((label, index) => ({ status: label, count: dataValues[index] || 0 }));
        }

        if (statusCounts.length > 0) {
            statusCounts.forEach(item => {
                statusData.push([item.status || 'Unknown', parseInt(item.count) || 0]);
            });
            const total = statusCounts.reduce((sum, item) => sum + (parseInt(item.count) || 0), 0);
            statusData.push([]);
            statusData.push(['Total', total]);
        } else {
            statusData.push(['No data available', '']);
        }

        // ===== SHEET 2: Lead Source Analytics =====
        const sourceData = [];
        sourceData.push(['Lead Source Analytics']);
        sourceData.push([]);
        sourceData.push(['Source', 'Count']);

        let sourceCounts = [];
        if (dataToExport && dataToExport.detailed_source_counts) {
            sourceCounts = dataToExport.detailed_source_counts;
        } else if (leadSourceChart && leadSourceChart.data) {
            const labels = leadSourceChart.data.labels || [];
            const dataValues = leadSourceChart.data.datasets && leadSourceChart.data.datasets[0]
                ? leadSourceChart.data.datasets[0].data : [];
            sourceCounts = labels.map((label, index) => ({ source_of_lead: label, count: dataValues[index] || 0 }));
        }

        if (sourceCounts.length > 0) {
            sourceCounts.forEach(item => {
                const cleanLabel = (item.source_of_lead || 'Unknown').replace(/\s*\(\d+\)\s*$/, '');
                sourceData.push([cleanLabel, parseInt(item.count) || 0]);
            });
            const total = sourceCounts.reduce((sum, item) => sum + (parseInt(item.count) || 0), 0);
            sourceData.push([]);
            sourceData.push(['Total', total]);
        } else {
            sourceData.push(['No data available', '']);
        }

        // ===== SHEET 3: User Performance Data =====
        const userPerformanceData = [];
        userPerformanceData.push(['User Performance Report']);
        userPerformanceData.push([]);
        userPerformanceData.push(['Name', 'Email', 'Leads', 'QR', 'FSV', 'SVD', 'EOIs', 'Bookings', 'Cancelled', 'CR']);

        let userDataToExport = [];
        if (dataToExport && dataToExport.user_wise_data) {
            userDataToExport = dataToExport.user_wise_data;
        } else if (userData && userData.length > 0) {
            if (currentFilteredUserTablenames && currentFilteredUserTablenames.length > 0) {
                userDataToExport = userData.filter(user => currentFilteredUserTablenames.includes(user.tablename));
            } else {
                userDataToExport = userData;
            }
        }

        if (userDataToExport && userDataToExport.length > 0) {
            userDataToExport.forEach(user => {
                const leadsCount = parseInt(user.leads) || 0;
                const qrRaw = user.quality_range;
                const qualityRange = (qrRaw !== undefined && qrRaw !== null && qrRaw !== '')
                    ? Math.min(parseInt(qrRaw) || 0, leadsCount)
                    : 0;

                let fsvCount = parseInt(user.fsv_count) || 0;
                let svdCount = parseInt(user.svd_count) || 0;

                if ((fsvCount === 0 || svdCount === 0) && user.leadStatus) {
                    if (Array.isArray(user.leadStatus)) {
                        user.leadStatus.forEach(statusItem => {
                            const status = (statusItem.status || '').toLowerCase();
                            const count = parseInt(statusItem.count) || 0;
                            if (status.includes('fix site visit') || status === 'fix_site_visit') fsvCount = count;
                            else if (status.includes('site visit done') || status.includes('site visited') || status === 'site_visit_done') svdCount = count;
                        });
                    } else if (typeof user.leadStatus === 'object') {
                        Object.entries(user.leadStatus).forEach(([status, count]) => {
                            const sl = status.toLowerCase();
                            if (sl.includes('fix site visit') || sl === 'fix_site_visit') fsvCount = parseInt(count) || 0;
                            else if (sl.includes('site visit done') || sl.includes('site visited') || sl === 'site_visit_done') svdCount = parseInt(count) || 0;
                        });
                    }
                }

                userPerformanceData.push([
                    user.name || user.username || '',
                    user.email || user.useremail || '',
                    leadsCount,
                    qualityRange,
                    fsvCount,
                    svdCount,
                    parseInt(user.eoi) || parseInt(user.total_eoi) || 0,
                    parseInt(user.bookings) || parseInt(user.total_bookings) || 0,
                    parseInt(user.cancelled_bookings) || 0,
                    user.conversion_rate ? (typeof user.conversion_rate === 'number' ? user.conversion_rate.toFixed(1) + '%' : user.conversion_rate) : '0%'
                ]);
            });

            const totals = userDataToExport.reduce((acc, user) => {
                const leadsCount = parseInt(user.leads) || 0;
                const qrRaw2 = user.quality_range;
                const qualityRange = (qrRaw2 !== undefined && qrRaw2 !== null && qrRaw2 !== '')
                    ? Math.min(parseInt(qrRaw2) || 0, leadsCount)
                    : 0;
                let fsvCount = parseInt(user.fsv_count) || 0;
                let svdCount = parseInt(user.svd_count) || 0;

                if ((fsvCount === 0 || svdCount === 0) && user.leadStatus) {
                    if (Array.isArray(user.leadStatus)) {
                        user.leadStatus.forEach(statusItem => {
                            const status = (statusItem.status || '').toLowerCase();
                            const count = parseInt(statusItem.count) || 0;
                            if (status.includes('fix site visit') || status === 'fix_site_visit') fsvCount = count;
                            else if (status.includes('site visit done') || status.includes('site visited') || status === 'site_visit_done') svdCount = count;
                        });
                    } else if (typeof user.leadStatus === 'object') {
                        Object.entries(user.leadStatus).forEach(([status, count]) => {
                            const sl = status.toLowerCase();
                            if (sl.includes('fix site visit') || sl === 'fix_site_visit') fsvCount = parseInt(count) || 0;
                            else if (sl.includes('site visit done') || sl.includes('site visited') || sl === 'site_visit_done') svdCount = parseInt(count) || 0;
                        });
                    }
                }

                acc.leads += leadsCount;
                acc.qr += qualityRange;
                acc.fsv += fsvCount;
                acc.svd += svdCount;
                acc.eoi += parseInt(user.eoi) || parseInt(user.total_eoi) || 0;
                acc.bookings += parseInt(user.bookings) || parseInt(user.total_bookings) || 0;
                acc.cancelled += parseInt(user.cancelled_bookings) || 0;
                return acc;
            }, { leads: 0, qr: 0, fsv: 0, svd: 0, eoi: 0, bookings: 0, cancelled: 0 });

            userPerformanceData.push([]);
            userPerformanceData.push(['TOTAL', '', totals.leads, totals.qr, totals.fsv, totals.svd, totals.eoi, totals.bookings, totals.cancelled, '']);
        } else {
            userPerformanceData.push(['No user data available', '', '', '', '', '', '', '', '', '']);
        }

        // ===== SHEET 4: User Status Breakdown =====
        const userStatusData = [];
        userStatusData.push(['User Status Breakdown']);
        userStatusData.push([]);

        const allStatuses = new Set();
        if (userDataToExport && userDataToExport.length > 0) {
            userDataToExport.forEach(user => {
                if (user.leadStatus) {
                    if (Array.isArray(user.leadStatus)) {
                        user.leadStatus.forEach(item => {
                            if (item.status) allStatuses.add(item.status);
                        });
                    } else if (typeof user.leadStatus === 'object') {
                        Object.keys(user.leadStatus).forEach(status => allStatuses.add(status));
                    }
                }
            });
        }

        const statusColumns = Array.from(allStatuses);
        userStatusData.push(['Name', 'Email', ...statusColumns]);

        if (userDataToExport && userDataToExport.length > 0) {
            userDataToExport.forEach(user => {
                const row = [user.name || user.username || '', user.email || user.useremail || ''];
                statusColumns.forEach(statusName => {
                    let count = 0;
                    if (user.leadStatus) {
                        if (Array.isArray(user.leadStatus)) {
                            const found = user.leadStatus.find(item => item.status === statusName);
                            if (found) count = parseInt(found.count) || 0;
                        } else if (typeof user.leadStatus === 'object') {
                            count = parseInt(user.leadStatus[statusName]) || 0;
                        }
                    }
                    row.push(isNaN(count) ? 0 : count);
                });
                userStatusData.push(row);
            });
        } else {
            userStatusData.push(['No user data available', ...Array(statusColumns.length).fill('')]);
        }

        // ===== SHEET 5: Summary Statistics =====
        const summaryData = [];
        summaryData.push(['Summary Statistics']);
        summaryData.push([]);
        summaryData.push(['Metric', 'Value']);

        if (dataToExport && dataToExport.aggregated_totals) {
            const totals = dataToExport.aggregated_totals;
            summaryData.push(['Total Leads', parseInt(totals.totalLeads) || 0]);
            summaryData.push(['Total Bookings', parseInt(totals.totalBookings) || 0]);
            summaryData.push(['Total EOIs', parseInt(totals.totalEOI) || 0]);
            summaryData.push(['Total Cancelled Bookings', parseInt(totals.totalCancelledBookings) || 0]);
        } else if (userDataToExport && userDataToExport.length > 0) {
            const totals = userDataToExport.reduce((acc, user) => {
                acc.leads += parseInt(user.leads) || 0;
                acc.eoi += parseInt(user.eoi) || 0;
                acc.bookings += parseInt(user.bookings) || 0;
                acc.cancelled += parseInt(user.cancelled_bookings) || 0;
                return acc;
            }, { leads: 0, eoi: 0, bookings: 0, cancelled: 0 });

            summaryData.push(['Total Leads', totals.leads]);
            summaryData.push(['Total Bookings', totals.bookings]);
            summaryData.push(['Total EOIs', totals.eoi]);
            summaryData.push(['Total Cancelled Bookings', totals.cancelled]);
        } else if (dataToExport) {
            summaryData.push(['Total Leads', parseInt(dataToExport.total_leads) || 0]);
            summaryData.push(['Total Bookings', parseInt(dataToExport.total_bookings) || 0]);
            summaryData.push(['Total EOIs', parseInt(dataToExport.total_eoi) || 0]);
        } else {
            summaryData.push(['No data available', '']);
        }

        // Create worksheets
        const ws1 = XLSX.utils.aoa_to_sheet(statusData);
        const ws2 = XLSX.utils.aoa_to_sheet(sourceData);
        const ws3 = XLSX.utils.aoa_to_sheet(userPerformanceData);
        const ws4 = XLSX.utils.aoa_to_sheet(userStatusData);
        const ws5 = XLSX.utils.aoa_to_sheet(summaryData);

        ws1['!cols'] = [{ wch: 30 }, { wch: 15 }];
        ws2['!cols'] = [{ wch: 30 }, { wch: 15 }];
        ws3['!cols'] = [{ wch: 25 }, { wch: 30 }, { wch: 10 }, { wch: 10 }, { wch: 10 }, { wch: 10 }, { wch: 10 }, { wch: 12 }, { wch: 12 }, { wch: 12 }];
        ws4['!cols'] = [{ wch: 25 }, { wch: 30 }, ...statusColumns.map(() => ({ wch: 15 }))];
        ws5['!cols'] = [{ wch: 30 }, { wch: 20 }];

        // Append worksheets to workbook
        XLSX.utils.book_append_sheet(wb, ws1, 'Status Distribution');
        XLSX.utils.book_append_sheet(wb, ws2, 'Source Analytics');
        XLSX.utils.book_append_sheet(wb, ws3, 'User Performance');
        XLSX.utils.book_append_sheet(wb, ws4, 'User Status Breakdown');
        XLSX.utils.book_append_sheet(wb, ws5, 'Summary');

        // Generate filename
        const filenameParts = ['Dashboard_Report'];
        if (monthLabel) filenameParts.push(String(monthLabel));
        if (yearLabel) filenameParts.push(String(yearLabel));
        const dt = new Date();
        filenameParts.push(`${dt.getFullYear()}-${String(dt.getMonth() + 1).padStart(2, '0')}-${String(dt.getDate()).padStart(2, '0')}`);
        const filename = filenameParts.join('_') + '.xlsx';

        // Write the file
        XLSX.writeFile(wb, filename);
        console.log('[Excel Download] File downloaded successfully:', filename);

    } catch (error) {
        console.error('Error generating Excel file:', error);
        alert('Error generating Excel file. Please check console and try again.');
    }
}

