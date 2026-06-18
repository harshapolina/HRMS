/**
 * ============================================================================
 * LEADS PAGE - MAIN JAVASCRIPT FILE (SUPER OPTIMIZED v2.0)
 * ============================================================================
 * 
 * FUNCTION ORGANIZATION (BY FUNCTIONALITY):
 * 
 * SECTION 1: GLOBAL CONSTANTS & UTILITIES
 *    - DEBOUNCE_DELAY, closeModal
 *    - Helper functions: encryptPhone, getLastTouchStatus, escapeHtml
 * 
 * SECTION 2: TAG COUNT SYSTEM (SUPER OPTIMIZED - SINGLE API CALL) âš¡âš¡
 *    - getCurrentFilterState() - Cached filter state collection
 *    - fetchAllTagCounts() - SINGLE API call for ALL 11 counts
 *    - updateTagBadge() - Badge update (no flickering)
 *    - updateAllTagCounts() - Master function with debouncing
 *    - 300ms debounce + 500ms cache prevents duplicate calls
 * 
 * SECTION 3: DATATABLE INITIALIZATION & DATA FETCHING âš¡
 *    - initializeLeadsTable() - Table setup with optimized AJAX
 *    - DataTable configuration - Server-side processing
 *    - AJAX data function - Optimized query building
 * 
 * SECTION 4: FILTER SYSTEM âš¡
 *    - initFilterDropdowns() - Header dropdown filters
 *    - populateFilterDropdown() - Dynamic filter options
 *    - getUniqueColumnValuesAll() - Column value fetching
 *    - initMultiSelectFilters() - Multi-select filter handling
 *    - initClearFiltersButton() - Clear filters functionality
 * 
 * SECTION 5: MODAL MANAGEMENT
 *    - openStatusModal() / closeStatusModal() - Status updates
 *    - openAssignModal() / closeAssignModal() - Bulk assignment
 *    - openReassignModal() / closeReassignModal() - Single reassignment
 *    - openAddLeadModal() / closeAddLeadModal() - Add new lead
 *    - openDeleteModal() / closeDeleteModal() - Delete leads
 * 
 * SECTION 6: LEAD OPERATIONS âš¡
 *    - submitLeadForm() - Add new lead
 *    - updateTableRow() - Update lead status
 *    - validateStatusForm() - Form validation
 *    - loadUsers() - User dropdown population
 * 
 * SECTION 7: UI HELPERS
 *    - showLeadsLoader() / hideLeadsLoader() - Loading states
 *    - handleColumnVisibility() - Responsive columns
 *    - handleResponsiveBehavior() - Mobile responsiveness
 *    - updateFilterCounter() - Filter count display
 * 
 * SECTION 8: EVENT HANDLERS
 *    - Manager toggle handlers
 *    - Filter button clicks
 *    - Form submissions
 *    - Table draw events
 * 
 * ============================================================================
 * PERFORMANCE OPTIMIZATIONS (ULTRA FAST - REDUCED API CALLS):
 * âš¡âš¡ SINGLE API CALL - get_all_tag_counts returns ALL 11 counts at once
 * âš¡âš¡ Replaces 11 individual API calls with just 1 (90% reduction!)
 * âš¡ Request cancellation - Prevents stale updates
 * âš¡ 300ms debounce - Prevents rapid duplicate calls
 * âš¡ 500ms cache - Avoids redundant API calls
 * âš¡ No flickering - Preserves old values until new ones arrive
 * âš¡ Backend caching - User role & accessible users cached
 * âš¡ Optimized SQL - Single query with conditional aggregation
 * ============================================================================
 */

// ============================================================================
// SECTION 1: GLOBAL CONSTANTS & UTILITIES
// ============================================================================

// Define DEBOUNCE_DELAY constant to prevent undefined errors (make it globally accessible)
// Use var instead of const to ensure it's hoisted and available in all scopes
var DEBOUNCE_DELAY = 300;
window.DEBOUNCE_DELAY = DEBOUNCE_DELAY; // Make it globally accessible

// Define closeModal early to prevent ReferenceError
window.closeModal = window.closeModal || function () {
  console.warn('closeModal called but not yet initialized');
};

function closeColumnsModal() {
  const existing = document.getElementById('columnsModalOverlay');
  if (existing) existing.remove();
  if (window.__columnsModalEscapeHandler) {
    document.removeEventListener('keydown', window.__columnsModalEscapeHandler);
    window.__columnsModalEscapeHandler = null;
  }
  document.body.style.overflow = '';
}

function openColumnsModal(table, anchorNode) {
  closeColumnsModal();

  const overlay = document.createElement('div');
  overlay.className = 'modal-overlay';
  overlay.id = 'columnsModalOverlay';
  overlay.style.display = 'flex';

  const modal = document.createElement('div');
  modal.className = 'modal-container-eoi';
  modal.style.padding = '10px';
  modal.style.maxWidth = 'min(700px, 95vw)';
  modal.style.width = '95%';
  modal.style.margin = '6rem auto';
  modal.style.maxHeight = '72vh';
  modal.style.overflowY = 'auto';

  const header = document.createElement('div');
  header.className = 'modal-header';
  header.innerHTML = '<h3>COLUMN VISIBILITY</h3>';

  const closeBtn = document.createElement('button');
  closeBtn.type = 'button';
  closeBtn.className = 'modal-close-btn';
  closeBtn.innerHTML = '&times;';
  header.appendChild(closeBtn);

  const body = document.createElement('div');
  body.className = 'modal-body';
  body.style.padding = '18px 20px 10px';
  body.style.overflowY = 'auto';

  const grid = document.createElement('div');
  grid.style.display = 'grid';
  grid.style.gridTemplateColumns = 'repeat(2, minmax(0, 1fr))';
  grid.style.gap = '16px 18px';
  grid.style.width = '100%';

  const columnLabels = {
    0: 'Select Row',
    1: 'Lead Info',
    2: 'Project',
    3: 'ID',
    4: 'Created At',
    5: 'Email',
    6: 'Location',
    7: 'Budget',
    8: 'Remarks',
    9: 'Updated At',
    10: 'Assigned Lead',
    11: 'Lead Source',
    12: 'Expand',
    13: 'Actions'
  };

  table.columns().every(function () {
    const index = this.index();
    const headerNode = this.header();

    const label = document.createElement('label');
    label.style.display = 'flex';
    label.style.alignItems = 'center';
    label.style.justifyContent = 'space-between';
    label.style.gap = '12px';
    label.style.padding = '12px 14px';
    label.style.border = '1px solid rgba(0,0,0,0.08)';
    label.style.borderRadius = '10px';
    label.style.background = '#fff';
    label.style.cursor = 'pointer';

    const title = document.createElement('span');
    title.textContent = columnLabels[index] || (headerNode ? headerNode.innerText.replace(/\s+/g, ' ').trim() : `Column ${index + 1}`);
    title.style.fontWeight = '600';

    const checkbox = document.createElement('input');
    checkbox.type = 'checkbox';
    checkbox.checked = table.column(index).visible();
    checkbox.dataset.columnIndex = String(index);

    label.appendChild(title);
    label.appendChild(checkbox);
    grid.appendChild(label);
  });

  body.appendChild(grid);

  const footer = document.createElement('div');
  footer.className = 'modal-footer';
  footer.style.display = 'flex';
  footer.style.justifyContent = 'flex-end';
  footer.style.gap = '10px';
  footer.style.paddingTop = '14px';
  footer.innerHTML = `
    <button type="button" class="btn btn-secondary" id="closeColumnsModalBtn">Close</button>
    <button type="button" class="btn btn-primary" id="applyColumnsModalBtn">Apply</button>
  `;

  modal.appendChild(header);
  modal.appendChild(body);
  modal.appendChild(footer);
  overlay.appendChild(modal);
  document.body.appendChild(overlay);
  document.body.style.overflow = 'hidden';

  closeBtn.addEventListener('click', closeColumnsModal);
  overlay.addEventListener('click', function (e) {
    if (e.target === overlay) closeColumnsModal();
  });
  document.getElementById('closeColumnsModalBtn').addEventListener('click', closeColumnsModal);
  document.getElementById('applyColumnsModalBtn').addEventListener('click', function () {
    overlay.querySelectorAll('input[type="checkbox"][data-column-index]').forEach((checkbox) => {
      const columnIndex = parseInt(checkbox.dataset.columnIndex, 10);
      if (!Number.isNaN(columnIndex)) {
        table.column(columnIndex).visible(checkbox.checked, false);
      }
    });
    table.columns.adjust().draw(false);
    closeColumnsModal();
  });

  if (window.__columnsModalEscapeHandler) {
    document.removeEventListener('keydown', window.__columnsModalEscapeHandler);
  }
  window.__columnsModalEscapeHandler = function (event) {
    if (event.key === 'Escape') {
      closeColumnsModal();
    }
  };
  document.addEventListener('keydown', window.__columnsModalEscapeHandler);
}

// iOS-only dropdown portal helper: moves dropdowns to <body> while open so
// they are not clipped/hidden by transformed or overflowed ancestors.
window.__iosDropdownPortal = window.__iosDropdownPortal || (function () {
  const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) ||
    (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

  function applyFixedStyles(dropdownEl, anchorEl) {
    const rect = anchorEl && anchorEl.getBoundingClientRect ? anchorEl.getBoundingClientRect() : { bottom: 100 };
    const viewportWidth = window.innerWidth || document.documentElement.clientWidth || 0;
    const viewportHeight = window.innerHeight || document.documentElement.clientHeight || 0;
    const top = Math.max(10, Math.min(rect.bottom + 8, Math.max(20, viewportHeight - 320)));
    const panelWidth = 220;
    const left = Math.max(10, Math.min(rect.left || 10, Math.max(10, viewportWidth - panelWidth - 10)));

    dropdownEl.style.setProperty('position', 'fixed', 'important');
    dropdownEl.style.setProperty('left', `${left}px`, 'important');
    dropdownEl.style.setProperty('right', 'auto', 'important');
    dropdownEl.style.setProperty('top', `${top}px`, 'important');
    dropdownEl.style.setProperty('width', `${panelWidth}px`, 'important');
    dropdownEl.style.setProperty('max-width', `${panelWidth}px`, 'important');
    dropdownEl.style.setProperty('max-height', `calc(100vh - ${top + 24}px)`, 'important');
    dropdownEl.style.setProperty('overflow-y', 'auto', 'important');
    dropdownEl.style.setProperty('-webkit-overflow-scrolling', 'touch', 'important');
    dropdownEl.style.setProperty('z-index', '2147483000', 'important');
    dropdownEl.style.setProperty('margin-top', '0', 'important');
    dropdownEl.style.setProperty('display', 'block', 'important');
  }

  function clearFixedStyles(dropdownEl) {
    const props = [
      'position', 'left', 'right', 'top', 'width', 'max-width', 'max-height',
      'overflow-y', '-webkit-overflow-scrolling', 'z-index', 'margin-top', 'display'
    ];
    props.forEach((prop) => dropdownEl.style.removeProperty(prop));
  }

  function open(dropdownEl, anchorEl) {
    if (!isIOS || !dropdownEl) return;

    if (!dropdownEl.__iosPortalMarker) {
      const marker = document.createComment('ios-dropdown-marker');
      dropdownEl.parentNode && dropdownEl.parentNode.insertBefore(marker, dropdownEl);
      dropdownEl.__iosPortalMarker = marker;
      document.body.appendChild(dropdownEl);
    }

    applyFixedStyles(dropdownEl, anchorEl);
  }

  function close(dropdownEl) {
    if (!isIOS || !dropdownEl) return;

    clearFixedStyles(dropdownEl);

    const marker = dropdownEl.__iosPortalMarker;
    if (marker && marker.parentNode) {
      marker.parentNode.insertBefore(dropdownEl, marker);
      marker.parentNode.removeChild(marker);
      dropdownEl.__iosPortalMarker = null;
    }
  }

  return { isIOS, open, close };
})();

document.addEventListener("DOMContentLoaded", function () {
  // ============================================================================
  // URL PARAMETER HANDLING FOR DASHBOARD POPUP
  // Read URL params (from dashboard popup) and initialize filters
  // ============================================================================
  const urlParams = new URLSearchParams(window.location.search);
  const urlFilterUser = urlParams.get('filterUser') || '';
  const urlStatus = urlParams.get('status') || '';
  const urlActiveFilter = urlParams.get('filter') || '';
  const urlStartDate = urlParams.get('start_date') || '';
  const urlEndDate = urlParams.get('end_date') || '';
  const urlTeamView = urlParams.get('teamView') || '';
  const urlLeadName = urlParams.get('lead_name') || '';
  const urlProjectName = urlParams.get('project_name') || '';

  // Store filterUser globally for AJAX requests
  window.urlFilterUser = urlFilterUser;
  window.urlTeamView = urlTeamView;

  if (urlActiveFilter) {
    window.currentActiveFilter = urlActiveFilter;
  }

  // Initialize multiFilters from URL params if present
  if (urlStartDate || urlEndDate || urlStatus || urlFilterUser || urlLeadName || urlProjectName) {
    window.multiFilters = window.multiFilters || {};
    if (urlStartDate) window.multiFilters.start_date = urlStartDate;
    if (urlEndDate) window.multiFilters.end_date = urlEndDate;
    if (urlStatus) window.multiFilters.status = urlStatus;
    if (urlFilterUser) window.multiFilters.filterUser = urlFilterUser;
    if (urlLeadName) window.multiFilters.name = urlLeadName;
    if (urlProjectName) window.multiFilters.assign_project_name = urlProjectName;
    console.log('📋 Initialized multiFilters from URL params:', window.multiFilters);
  }

  //define variables for existing functions
  const submitReassignBtn = document.getElementById("submitReassign");
  const reassignRowIdInput = document.getElementById("reassignRowId");
  const assignUserInput = document.getElementById("assignUser");
  const projectNameInput = document.getElementById("projectName");
  const responseMessage = document.getElementById("responseMessage");
  const openDateRangePickerBtn = document.getElementById("openDateRangePicker");
  const modalDateFromInput = document.getElementById("modalDateFrom");
  const modalDateToInput = document.getElementById("modalDateTo");
  const dateFromInput = document.getElementById("dateFrom");
  const dateToInput = document.getElementById("dateTo");
  const datePickerModal = document.getElementById("datePickerModal");
  const closeLeadHistoryBtn = document.getElementById("uniqueCloseSidebar");
  const closeCallHistoryBtn = document.getElementById("uniqueCloseCallSidebar");
  const leadUserNameEl = document.getElementById("lead_user_name");
  const leadUserNumberEl = document.getElementById("lead_user_number");
  const assignedDateEl = document.getElementById("assigned_date_leads");
  const assignedByUserEl = document.getElementById("assigned_by_user");
  const historyList = document.getElementById("followUpHistory");
  const followUpCallHistory = document.getElementById("followUpCallHistory");
  const assignedDateCallLeads = document.getElementById("assigned_date_callleads");
  const assignedByCallUser = document.getElementById("assigned_by_calluserr");
  const leadUserCallName = document.getElementById("lead_user_callname");
  const leadUserCallNumber = document.getElementById("lead_user_callnumber");
  const reassignForm = document.getElementById("reassignForm");
  const followUpHistory = document.getElementById("followUpHistory");
  const assignedDateLeads = document.getElementById("assigned_date_leads");
  const assignedByUser = document.getElementById("assigned_by_user");
  const leadUserName = document.getElementById("lead_user_name");
  const leadUserNumber = document.getElementById("lead_user_number");
  const addLeadForm = document.getElementById("addLeadForm");
  const submitBtn = document.getElementById("submitLead");
  const leadLocation = document.getElementById("leadlocation");
  const leadPhone = document.getElementById("leadPhone");
  const leadEmail = document.getElementById("leadEmail");
  const assignModal = document.getElementById("assignModal");
  const assignForm = document.getElementById("assignForm");
  const assignTo = document.getElementById("assignTo");
  const bulkProjectName = document.getElementById("bulkProjectName");
  const selectedLeadsCount = document.getElementById("selectedLeadsCount");
  const selectedLeadsList = document.getElementById("selectedLeadsList");
  const cancelAssignBtn = document.getElementById("cancelAssign");
  const closeAssignModalBtn = document.getElementById("closeAssignModal");
  const managerToggle = document.getElementById("managerToggle");
  const mobileManagerToggleBtn = document.getElementById("mobileManagerToggleBtn");
  const mobileManagerToggleIconTeam = document.getElementById("mobileManagerToggleIconTeam");
  const mobileManagerToggleIconMy = document.getElementById("mobileManagerToggleIconMy");
  const mobileManagerToggleText = document.getElementById("mobileManagerToggleText");
  const currentRole = (
    document.querySelector(".leads-header")?.getAttribute("data-current-role") ||
    ""
  ).toLowerCase();
  const canManagerCopyPhone = currentRole === "manager";
  const statusModal = document.getElementById('statusModal');
  const statusForm = document.getElementById('statusForm');
  const newStatus = document.getElementById('newStatus');
  const followUpDate = document.getElementById('followUpDate');
  const followUpTime = document.getElementById('followUpTime');
  const statusNotes = document.getElementById('statusNotes');
  const statusSubmit = document.getElementById('statusSubmit');
  const statusField = document.getElementById('statusField');
  const dateField = document.getElementById('dateField');
  const timeField = document.getElementById('timeField');
  const notesField = document.getElementById('notesField');
  const statusError = document.getElementById('statusError');
  const dateError = document.getElementById('dateError');
  const timeError = document.getElementById('timeError');
  const notesError = document.getElementById('notesError');
  const historyDescription = document.getElementById('historyDescription');

  const HISTORY_CACHE_TTL_MS = 15000;
  const historyCache = new Map();
  const callHistoryCache = new Map();

  function buildLeadCacheKey(rowId, userUniqueId) {
    return `${String(rowId || '').trim()}::${String(userUniqueId || '').trim()}`;
  }

  function getCachedValue(cacheMap, key) {
    const entry = cacheMap.get(key);
    if (!entry) return null;
    if ((Date.now() - entry.ts) > HISTORY_CACHE_TTL_MS) {
      cacheMap.delete(key);
      return null;
    }
    return entry.value;
  }

  function setCachedValue(cacheMap, key, value) {
    cacheMap.set(key, { ts: Date.now(), value });
  }

  window.invalidateLeadCaches = function invalidateLeadCaches(rowId) {
    const prefix = `${String(rowId || '').trim()}::`;
    for (const key of historyCache.keys()) {
      if (key.startsWith(prefix)) historyCache.delete(key);
    }
    for (const key of callHistoryCache.keys()) {
      if (key.startsWith(prefix)) callHistoryCache.delete(key);
    }
  };


  //This is my quick variables
  let iconCounter = 0;
  let table;
  let ipFetched = false;
  let ipAddress = "";
  let isStatusSubmitting = false;
  let isRemarksModeActive = false;
  const remarksModeRestoredRows = new Set();

  function syncMobileManagerToggleUI() {
    if (!managerToggle || !mobileManagerToggleBtn || !mobileManagerToggleText) {
      return;
    }

    const isTeamView = !!managerToggle.checked;

    // ON means currently in Team View, so CTA becomes "My View" with single-user icon.
    mobileManagerToggleText.textContent = isTeamView ? "My View" : "Team View";

    // Use fixed icon elements so exactly one icon is visible in each state.
    if (mobileManagerToggleIconTeam && mobileManagerToggleIconMy) {
      mobileManagerToggleIconTeam.style.display = isTeamView ? "none" : "inline-flex";
      mobileManagerToggleIconMy.style.display = isTeamView ? "inline-flex" : "none";
    }

    mobileManagerToggleBtn.classList.toggle("is-team-view", !isTeamView);
    mobileManagerToggleBtn.classList.toggle("is-my-view", isTeamView);
    mobileManagerToggleBtn.disabled = !!managerToggle.disabled;
  }

  if (managerToggle && mobileManagerToggleBtn) {
    mobileManagerToggleBtn.addEventListener("click", function () {
      if (managerToggle.disabled) return;
      managerToggle.checked = !managerToggle.checked;
      managerToggle.dispatchEvent(new Event("change"));
      syncMobileManagerToggleUI();
    });

    managerToggle.addEventListener("change", syncMobileManagerToggleUI);
    syncMobileManagerToggleUI();
  }

  function getRowLeadId($row) {
    const fromHidden = ($row.find(".lead-id-hidden").text() || "").trim();
    if (fromHidden) return fromHidden;
    const fromIdCell = ($row.find(".lead-id-section").text() || "").replace("#", "").trim();
    return fromIdCell;
  }

  function rowHasVisibleRemarks($row) {
    const $remarksNodes = $row.find(
      ".mobile-remarks-view:visible, .mobile-remarks-text:visible, td.remarks-column .remarks-cell-scroll:visible, td.remarks-column .remarks-info:visible"
    );

    if (!$remarksNodes.length) return false;

    let hasMeaningfulRemark = false;
    $remarksNodes.each(function () {
      const text = (($(this).text() || "").replace(/^\s*remarks\s*:\s*/i, "").trim());
      if (text && text.toUpperCase() !== "N/A") {
        hasMeaningfulRemark = true;
        return false;
      }
    });

    return hasMeaningfulRemark;
  }

  function openHistoryForRow($row) {
    const $historyBtn = $row.find(".unique-toggle-btn").first();
    const rowId = (
      $historyBtn.data("id") ||
      getRowLeadId($row) ||
      ""
    ).toString().trim();
    const userUniqueId = (
      $historyBtn.data("userid") ||
      $row.find(".assigned-lead").text() ||
      ""
    ).toString().trim();

    if (!rowId || !userUniqueId || typeof window.fetchHistory !== "function") {
      return false;
    }

    window.fetchHistory(rowId, userUniqueId);

    const historySidebar = document.getElementById("uniqueLeadHistorySidebar");
    if (historySidebar) {
      historySidebar.style.display = "block";
      setTimeout(() => historySidebar.classList.add("active"), 10);
    }

    return true;
  }

  function buildWhatsAppHistoryButtonMarkup(row, isMobile) {
    const leadId = String(row.remark_id || row.id || '').trim();
    const uploadId = String(row.id || '').trim();
    const leadName = String(row.name || '').trim().replace(/\s+/g, ' ');
    const leadPhone = String(row.phone || '').replace(/\s+/g, '').trim();
    const assignToUser = String(row.assignedLead || '').replace(/\s+/g, ' ').trim();
    const unreadMsgCount = parseInt(row.unread_wa_count || 0, 10) || 0;
    const waInterest = String(row.wa_interest || '').trim();

    if (!leadId) return '';

    let borderStyle = 'border:none;';
    if (waInterest === 'interested') borderStyle = 'border: 2px solid #28a745 !important;';
    else if (waInterest === 'neutral') borderStyle = 'border: 2px solid #ffc107 !important;';
    else if (waInterest === 'not_interested') borderStyle = 'border: 2px solid #dc3545 !important;';

    let badgeHtml = '';
    if (unreadMsgCount > 0) {
      const dispCount = unreadMsgCount > 99 ? '99+' : unreadMsgCount;
      const badgeStyle = isMobile
        ? 'position:absolute; top:-4px; right:-4px; background-color:#ef4444; color:white; border-radius:50%; min-width:16px; height:16px; font-size:9px; font-weight:bold; display:flex; align-items:center; justify-content:center; box-shadow: 0 1px 2px rgba(0,0,0,0.2); z-index:2; border: 1.5px solid white;'
        : 'position:absolute; top:-6px; right:-6px; background-color:#ef4444; color:white; border-radius:50%; min-width:18px; height:18px; font-size:10px; font-weight:bold; display:flex; align-items:center; justify-content:center; box-shadow: 0 1px 2px rgba(0,0,0,0.2); z-index:2; border: 2px solid white;';
      badgeHtml = `<span class="wa-unread-badge" style="${badgeStyle}">${dispCount}</span>`;
    }

    const buttonClass = isMobile ? 'action-btn wa-history-icon-btn tooltip' : 'wa-history-icon-btn icon-action-btn';
    const titleText = isMobile ? 'Chat History' : 'WhatsApp History';
    const tooltipAttr = isMobile ? ' data-tooltip="Chat History"' : '';
    const svg = isMobile
      ? '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" width="16" height="16" fill="currentColor"><path d="M316.5 288.8C309.8 288.8 303.2 290.9 297.6 294.6C292 298.3 287.7 303.7 285.1 310C282.5 316.3 281.9 323.1 283.2 329.7C284.5 336.3 287.8 342.4 292.6 347.1C297.4 351.8 303.5 355.1 310.1 356.4C316.7 357.7 323.6 357 329.8 354.4C336 351.8 341.3 347.4 345.1 341.8C348.9 336.2 350.8 329.6 350.8 322.9C350.8 313.8 347.1 305.1 340.7 298.7C334.3 292.3 325.6 288.7 316.5 288.8zM206.1 288.8C199.4 288.8 192.8 290.9 187.2 294.6C181.6 298.3 177.3 303.7 174.7 310C172.1 316.3 171.5 323.1 172.8 329.7C174.1 336.3 177.4 342.4 182.2 347.1C187 351.8 193.1 355.1 199.7 356.4C206.3 357.7 213.2 357 219.4 354.4C225.6 351.8 230.9 347.4 234.7 341.8C238.5 336.2 240.4 329.6 240.4 322.9C240.4 313.8 236.7 305.1 230.3 298.7C223.9 292.3 215.2 288.7 206.1 288.7L206.1 288.7zM427 288.8C408.2 288.9 393 304.3 393.1 323.1C393.2 341.9 408.6 357.1 427.4 357C446.2 356.9 461.4 341.5 461.3 322.7C461.2 303.9 445.8 288.7 427 288.8zM580.8 233.5C565.3 209.3 543.5 187.9 516.1 169.9C463.2 135.1 393.7 115.9 320.4 115.9C296.2 115.9 272.1 118 248.4 122.3C233.5 108 216.9 95.7 198.9 85.7C132.1 52.4 73.3 64.8 43.6 75.5C41.3 76.3 39.3 77.6 37.7 79.4C36.1 81.2 35 83.3 34.4 85.6C33.8 87.9 33.9 90.3 34.5 92.7C35.1 95.1 36.3 97.1 38 98.8C59 120.5 93.6 163.3 85.1 202.3C52 236.2 34 277 34 319.6C34 363 52 403.8 85.1 437.7C93.6 476.7 59 519.6 38 541.2C36.3 543 35.2 545.1 34.5 547.4C33.8 549.7 33.8 552.1 34.4 554.4C35 556.7 36.1 558.9 37.7 560.6C39.3 562.3 41.3 563.7 43.6 564.5C73.3 575.2 132.1 587.6 198.9 554.3C216.9 544.3 233.6 532 248.4 517.7C272.2 522 296.3 524.1 320.4 524.1C393.7 524.1 463.2 504.9 516.1 470.1C543.5 452.1 565.2 430.7 580.8 406.5C598.1 379.6 606.9 350.6 606.9 320.4C606.9 289.4 598.1 260.4 580.8 233.5L580.8 233.5zM317.4 473.9C287.2 474 257.1 470.1 228 462.4L207.9 481.8C196.7 492.5 184.3 501.8 170.8 509.4C154.4 517.6 136.6 522.7 118.3 524.3C119.3 522.5 120.2 520.7 121.1 518.9C141.3 481.8 146.7 448.4 137.4 418.8C104.4 392.8 84.6 359.6 84.6 323.4C84.6 240.3 188.9 172.9 317.4 172.9C445.9 172.9 550.3 240.3 550.3 323.4C550.3 406.5 446 473.9 317.4 473.9z"/></svg>'
      : '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" width="18" height="18" fill="currentColor"><path d="M316.5 288.8C309.8 288.8 303.2 290.9 297.6 294.6C292 298.3 287.7 303.7 285.1 310C282.5 316.3 281.9 323.1 283.2 329.7C284.5 336.3 287.8 342.4 292.6 347.1C297.4 351.8 303.5 355.1 310.1 356.4C316.7 357.7 323.6 357 329.8 354.4C336 351.8 341.3 347.4 345.1 341.8C348.9 336.2 350.8 329.6 350.8 322.9C350.8 313.8 347.1 305.1 340.7 298.7C334.3 292.3 325.6 288.7 316.5 288.8zM206.1 288.8C199.4 288.8 192.8 290.9 187.2 294.6C181.6 298.3 177.3 303.7 174.7 310C172.1 316.3 171.5 323.1 172.8 329.7C174.1 336.3 177.4 342.4 182.2 347.1C187 351.8 193.1 355.1 199.7 356.4C206.3 357.7 213.2 357 219.4 354.4C225.6 351.8 230.9 347.4 234.7 341.8C238.5 336.2 240.4 329.6 240.4 322.9C240.4 313.8 236.7 305.1 230.3 298.7C223.9 292.3 215.2 288.7 206.1 288.7L206.1 288.7zM427 288.8C408.2 288.9 393 304.3 393.1 323.1C393.2 341.9 408.6 357.1 427.4 357C446.2 356.9 461.4 341.5 461.3 322.7C461.2 303.9 445.8 288.7 427 288.8zM580.8 233.5C565.3 209.3 543.5 187.9 516.1 169.9C463.2 135.1 393.7 115.9 320.4 115.9C296.2 115.9 272.1 118 248.4 122.3C233.5 108 216.9 95.7 198.9 85.7C132.1 52.4 73.3 64.8 43.6 75.5C41.3 76.3 39.3 77.6 37.7 79.4C36.1 81.2 35 83.3 34.4 85.6C33.8 87.9 33.9 90.3 34.5 92.7C35.1 95.1 36.3 97.1 38 98.8C59 120.5 93.6 163.3 85.1 202.3C52 236.2 34 277 34 319.6C34 363 52 403.8 85.1 437.7C93.6 476.7 59 519.6 38 541.2C36.3 543 35.2 545.1 34.5 547.4C33.8 549.7 33.8 552.1 34.4 554.4C35 556.7 36.1 558.9 37.7 560.6C39.3 562.3 41.3 563.7 43.6 564.5C73.3 575.2 132.1 587.6 198.9 554.3C216.9 544.3 233.6 532 248.4 517.7C272.2 522 296.3 524.1 320.4 524.1C393.7 524.1 463.2 504.9 516.1 470.1C543.5 452.1 565.2 430.7 580.8 406.5C598.1 379.6 606.9 350.6 606.9 320.4C606.9 289.4 598.1 260.4 580.8 233.5L580.8 233.5zM317.4 473.9C287.2 474 257.1 470.1 228 462.4L207.9 481.8C196.7 492.5 184.3 501.8 170.8 509.4C154.4 517.6 136.6 522.7 118.3 524.3C119.3 522.5 120.2 520.7 121.1 518.9C141.3 481.8 146.7 448.4 137.4 418.8C104.4 392.8 84.6 359.6 84.6 323.4C84.6 240.3 188.9 172.9 317.4 172.9C445.9 172.9 550.3 240.3 550.3 323.4C550.3 406.5 446 473.9 317.4 473.9z"/></svg>';

    return `
      <div style="position:relative; display:inline-block; margin-right:4px;">
        <button class="${buttonClass}"
          title="${titleText}"${tooltipAttr}
          data-lead-id="${leadId}"
          data-remark-id="${String(row.remark_id || '').trim()}"
          data-upload-id="${uploadId}"
          data-assign-user="${assignToUser}"
          data-lead-name="${leadName.replace(/"/g, "'")}" 
          data-lead-phone="${leadPhone}"
          style="display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:6px;cursor:pointer;background:rgba(37,211,102,0.12);color:#25D366;font-size:13px;vertical-align:middle;transition:background .2s;${borderStyle}">
          ${svg}
        </button>
        ${badgeHtml}
      </div>
    `;
  }

  function applyRemarksModeToVisibleRows() {
    const $rows = $("#leadsTable tbody tr").not(".details-row");

    $rows.each(function () {
      const $row = $(this);

      if (!isRemarksModeActive) {
        $row.removeClass("remarks-mode-active row-show-details");
        return;
      }

      const rowId = getRowLeadId($row);
      const isRestored = rowId && remarksModeRestoredRows.has(rowId);

      $row.addClass("remarks-mode-active");
      $row.toggleClass("row-show-details", !!isRestored);
    });

    $(".remarks-btn-mobile, .columns-btn-mobile").toggleClass("active", isRemarksModeActive);
  }

  // Manager-only phone interaction: click toggles masked/unmasked and copy is allowed only when unmasked.
  $(document).on("copy", ".phone-info", function (e) {
    const $phoneEl = $(this);
    if (!canManagerCopyPhone || $phoneEl.hasClass("encrypted")) {
      e.preventDefault();
    }
  });

  $(document).on("contextmenu", ".phone-info", function (e) {
    const $phoneEl = $(this);
    if (!canManagerCopyPhone || $phoneEl.hasClass("encrypted")) {
      e.preventDefault();
    }
  });

  $(document).on("click", ".phone-info", function (e) {
    if (!canManagerCopyPhone) return;

    e.stopPropagation();

    const $phoneEl = $(this);
    const rawPhone = String($phoneEl.data("real-phone") || "").trim();
    if (!rawPhone) return;

    if ($phoneEl.hasClass("encrypted")) {
      $phoneEl.text(rawPhone).removeClass("encrypted");
      $phoneEl.attr("title", "Click again to hide number");
    } else {
      $phoneEl.text(encryptPhone(rawPhone)).addClass("encrypted");
      $phoneEl.attr("title", "Click to show number");
    }
  });

  // Update the top-left badge label when switching between views
  function updateMyLeadsLabel(isTeamView) {
    const myLeadsBtn = document.getElementById("myLeads");
    if (!myLeadsBtn) return;

    const iconEl = myLeadsBtn.querySelector("i");
    const countEl = myLeadsBtn.querySelector(".count");
    const iconHtml = iconEl ? iconEl.outerHTML : "";
    const countHtml = countEl ? countEl.outerHTML : '<span class="count">0</span>';
    const label = isTeamView ? " All Leads " : " My Leads ";

    myLeadsBtn.innerHTML = `${iconHtml}${label}${countHtml}`;
  }

  $(document).on("click", "#statusModal .identity-btn", function () {
    console.log("❌… Lead Identity button clicked");

    // Remove 'active' from siblings
    $(this)
      .closest(".identity-buttons")
      .find(".identity-btn")
      .removeClass("active");

    // Add 'active' to clicked one
    $(this).addClass("active");

    // Update hidden input
    $("#leadIdentityValue").val($(this).data("value"));

    console.log("Updated to:", $(this).data("value"));
  });

  function getNextIconPath() {
    iconCounter = (iconCounter % 6) + 1; // Cycle through 1-6
    return `assets/dataimage/icon-${iconCounter}.png`;
  }
  $('#leadIdentitySelect').on('change', function () {
    const selectedValue = $(this).val();
    $('#leadIdentityValue').val(selectedValue || '');
    console.log('Lead identity changed to:', selectedValue);
  });


  $('#leadIdentitySelect').on('change', function () {
    const selectedValue = $(this).val();
    $('#leadIdentityValue').val(selectedValue);
    console.log('Lead identity changed to:', selectedValue);
  });

  // Update the status modal opening function
  function openStatusModal(leadId, leadName, currentStatus, currentIdentity = '', userUniqueId = '') {
    // Clear previous validation states
    clearValidation();

    // Always start from a clean form so mobile and desktop behave the same
    if ($('#statusForm')[0]) {
      $('#statusForm')[0].reset();
    }
    $('#newStatus').val('');
    $('#budget').val('');
    $('#leadIdentitySelect').val('');
    $('#leadIdentityValue').val('');
    $('#preferredLocation').val('');
    $('#statusNotes').val('');
    $('#followUpDate').val('');
    $('#followUpTime').val('');
    $('#displayFollowUpDate').text('dd-mm-yyyy');
    $('#displayFollowUpTime').text('--:--');
    $('#dateField').hide();
    $('#timeField').hide();
    $('#statusForm .form-group').removeClass('error success');
    $('#statusForm .error-message').text('').hide();

    // Set lead details
    $('#statusLeadName').text(leadName);
    $('#statusLeadId').text(leadId);
    $('#rowId').val(leadId);

    // Store userUniqueId in a hidden field or data attribute
    $('#statusModal').data('userUniqueId', userUniqueId);

    // Do NOT pre-select the current status — user should always pick a new one
    $('#newStatus').val('').trigger('change');

    // Reinitialize Select2 with proper configuration
    $('#newStatus').select2({
      placeholder: "Select Status",
      allowClear: false,
      width: '100%',
      dropdownParent: $('#statusModal'),
      minimumResultsForSearch: 0
    });

    // Set current lead identity
    if (currentIdentity) {
      $('#leadIdentitySelect').val(currentIdentity);
      $('#leadIdentityValue').val(currentIdentity);
    }

    // Always start with no date/time fields — they appear when the user picks a status
    toggleDateTimeFields('');


    // Reset all form fields to remove any error states
    $('#statusForm .form-group').removeClass('error success');
    $('#statusForm .error-message').text('').hide();

    // Show modal with higher z-index
    $('#statusModal').css('z-index', '10050').show();
    $('body').css('overflow', 'hidden');
  }

  // Also initialize Select2 for the lead identity dropdown
  $('#leadIdentitySelect').select2({
    placeholder: "Select Lead Identity",
    allowClear: true,
    width: '100%',
    dropdownParent: $('#statusModal'),
    minimumResultsForSearch: -1
  });

  async function updateDataAndUI() {
    await fetchData();
    // Don't call getAllCountData here - filtered counts will be set after table initialization
    // await getAllCountData();
  }

  let countRequestToken = 0;
  // Flag to prevent getAllCountData from overwriting filtered badge counts
  let filteredCountsSet = false;
  let filteredCountsLocked = false;

  async function getAllCountData(flag = false, skipBadgeUpdate = false) {
    const requestToken = ++countRequestToken;
    try {
      // Get the status of the toggle (enabled or disabled)
      const toggleEl = document.getElementById("managerToggle");
      const isToggleEnabled = toggleEl && toggleEl.dataset.selfLock === '1' ? false : toggleEl.checked;
      const urlParams = new URLSearchParams(window.location.search);
      const filterUserParam = urlParams.get("filterUser");
      const managerViewParam = urlParams.get("managerView");

      // Get current active filters to pass to count endpoint
      let dateFrom = $("#dateFrom").val() || '';
      let dateTo = $("#dateTo").val() || '';

      if (window.multiFilters) {
        const mf = window.multiFilters;
        if (mf.start_date || mf.startDate) {
          dateFrom = mf.start_date || mf.startDate;
        }
        if (mf.end_date || mf.endDate) {
          dateTo = mf.end_date || mf.endDate;
        }
      }
      const multiFiltersJson = JSON.stringify(window.multiFilters || {});
      const currentFilter = window.currentActiveFilter || '';

      // Get current DataTables search query
      let searchQuery = '';
      let columnsSearch = [];
      try {
        if (typeof table !== 'undefined' && table && $.fn.DataTable.isDataTable("#leadsTable")) {
          // Get global search
          const currentSearch = table.search();
          searchQuery = (currentSearch && typeof currentSearch === 'string') ? currentSearch : '';

          // Get column-specific searches
          if (table.columns && typeof table.columns === 'function') {
            table.columns().every(function () {
              const colIndex = this.index();
              const colSearch = this.search();
              if (colSearch && typeof colSearch === 'string') {
                columnsSearch.push({
                  index: colIndex,
                  value: colSearch
                });
              }
            });
          }
        }
      } catch (e) {
        console.warn('Could not get DataTables search query:', e);
      }

      // Build query string with all filters
      const queryParams = new URLSearchParams({
        get_data: '1',
        toggle_enabled: isToggleEnabled ? '1' : '0',
        dateFrom: dateFrom,
        dateTo: dateTo,
        multiFilters: multiFiltersJson,
        filter: currentFilter
      });

      // Add search query if exists
      if (searchQuery) {
        queryParams.set('searchQuery', searchQuery);
      }

      // Add column searches if exists
      if (columnsSearch.length > 0) {
        // Convert column searches to DataTables format for server
        columnsSearch.forEach(colSearch => {
          queryParams.append(`columns[${colSearch.index}][search][value]`, colSearch.value);
        });
      }

      if (filterUserParam) {
        queryParams.set('filterUser', filterUserParam);
      }
      if (managerViewParam) {
        queryParams.set('managerView', managerViewParam);
      }

      // Fetch the total count, unassigned count, and my leads count from the PHP script
      const response = await fetch(`update_status.php?${queryParams.toString()}`);
      const data = await response.json(); // Parse the response as JSON

      if (requestToken !== countRequestToken) {
        return; // Ignore stale responses
      }

      // NEVER update badges here - all badges must come from updateFilteredBadgeCounts()
      // which fetches ALL filtered rows from server for accurate counts
      // This prevents overwriting filtered counts with unfiltered server data
      // All badge updates are handled by updateFilteredBadgeCounts() which counts ALL filtered rows (not just current page)

      // NOTE: Status badges (Active, Fresh, Pending, Follow, Overdue) are ALWAYS updated by updateFilteredBadgeCounts()
      // based on filtered table rows, not from server data. This ensures they reflect current filters/search.
      // Do NOT update these here - they will be calculated from filtered rows:
      // - activeLeads
      // - freshLeads  
      // - pendingLeads
      // - followLeads
      // - overdueLeads
      // Also, once table is initialized, ALL badges (including My Leads, Booked, etc.) should come from filtered rows

      // Update the "Untouched" button with the new count
      //document.getElementById("untouchedLeads").innerHTML = `Untouche <span class="count">${data.untouchedLeads} </span>`;

      // Optionally apply filters if 'flag' is true
      if (flag) {
        applyFilters(); // Call any filter function if needed
      }
    } catch (error) {
      console.error("Error fetching data:", error);
      hideLeadsLoader();
    } finally {
      hideLeadsLoader();
    }
  }

  $(document).ready(function () {
    // Track currently expanded row and phone row
    var currentExpandedRow = null;
    var currentPhoneRow = null;
    var table;
    let parentNotifiedOnce = false;
    let createdAtSortDirection = null;

    function updateCreatedAtSortButton() {
      const $sortButton = $("#createdAtSortBtn");
      if (!$sortButton.length) return;

      const isAscending = createdAtSortDirection === "asc";
      const icon = isAscending ? "fa-sort-amount-up" : "fa-sort-amount-down-alt";

      $sortButton.html(`<i class="fas ${icon}"></i>`);
    }

    function toggleCreatedAtSort() {
      createdAtSortDirection = createdAtSortDirection === "asc" ? "desc" : "asc";
      updateCreatedAtSortButton();

      if (table && $.fn.DataTable.isDataTable("#leadsTable")) {
        table.ajax.reload(null, true);
      }
    }

    $(document).on("click", "#createdAtSortBtn", function (e) {
      e.preventDefault();
      e.stopPropagation();
      toggleCreatedAtSort();
    });

    function notifyParentLeadsReady(meta = {}) {
      try {
        if (window.parent && window.parent !== window) {
          window.parent.postMessage(
            {
              type: "leads-data-ready",
              source: "user_lead",
              ...meta,
            },
            "*"
          );
        }
      } catch (err) {
        console.warn("Unable to notify parent about leads readiness:", err);
      }
    }
    // Set default filter to "myLeads" BEFORE table initialization to avoid duplicate API calls
    // The table will load with the correct filter from the start
    window.currentActiveFilter = typeof window.currentActiveFilter === "string" && window.currentActiveFilter !== ""
      ? window.currentActiveFilter
      : "myLeads";

    const dateFrom = $("#dateFrom").val();
    const dateTo = $("#dateTo").val();
    const urlParams = new URLSearchParams(window.location.search);
    const managerViewParam = urlParams.get('managerView');
    const filterUserParam = urlParams.get('filterUser');
    const currentUser = typeof currentUserTableName !== 'undefined' ? currentUserTableName : '';
    const currentUserName = typeof window.currentUserName !== 'undefined' ? window.currentUserName : '';
    const currentUserDisplayName = typeof window.currentUserDisplayName !== 'undefined' ? window.currentUserDisplayName : '';

    // Apply manager view/user filter passed from dashboard
    const managerViewEnabled = (managerViewParam === 'true' || managerViewParam === '1');

    const normalizeUser = (val) => {
      if (!val) return '';
      const parts = String(val).trim().toLowerCase().split(/[\s(-]+/);
      return parts[0] || '';
    };
    const matchesUser = (val) => {
      if (!val) return false;
      const needle = normalizeUser(val);
      return (
        (currentUser && needle === normalizeUser(currentUser)) ||
        (currentUserName && needle === normalizeUser(currentUserName)) ||
        (currentUserDisplayName && needle === normalizeUser(currentUserDisplayName))
      );
    };

    const isSelfSelection = (filterUserParam && matchesUser(filterUserParam));

    if (managerToggle) {
      const shouldSelfLock = () => {
        const mfUser = window.multiFilters && window.multiFilters.user ? String(window.multiFilters.user).trim() : '';
        const urlParamsCurrent = new URLSearchParams(window.location.search);
        const urlFilterUser = urlParamsCurrent.get('filterUser');
        return matchesUser(filterUserParam) || matchesUser(mfUser) || matchesUser(urlFilterUser);
      };

      const applySelfLock = () => {
        managerToggle.checked = false;
        managerToggle.dataset.selfLock = '1';
        managerToggle.disabled = true;
        const container = document.querySelector('.manager-toggle-container');
        if (container) {
          container.classList.add('self-locked');
        }
      };

      const prevChecked = managerToggle.checked;

      if (shouldSelfLock()) {
        applySelfLock();
      } else {
        managerToggle.dataset.selfLock = '';
        managerToggle.checked = managerViewEnabled;
        managerToggle.disabled = false;
      }

      if (prevChecked !== managerToggle.checked) {
        managerToggle.dispatchEvent(new Event('change'));
      }

      const hardLockHandler = (e) => {
        if (shouldSelfLock()) {
          applySelfLock();
          e.stopImmediatePropagation();
          e.preventDefault();
        }
      };
      managerToggle.addEventListener('change', hardLockHandler, true);

      setTimeout(() => { if (shouldSelfLock()) applySelfLock(); }, 0);
      window.addEventListener('load', () => { if (shouldSelfLock()) applySelfLock(); });

      // Final hardening: repeatedly enforce for the first 2 seconds + observe attribute changes
      const enforceHardSelfLock = () => { if (shouldSelfLock()) applySelfLock(); };
      enforceHardSelfLock();
      setTimeout(enforceHardSelfLock, 50);
      setTimeout(enforceHardSelfLock, 300);
      setTimeout(enforceHardSelfLock, 800);
      setTimeout(enforceHardSelfLock, 1500);
      setTimeout(enforceHardSelfLock, 2000);

      const observer = new MutationObserver(() => { if (shouldSelfLock()) applySelfLock(); });
      observer.observe(managerToggle, { attributes: true, attributeFilter: ['checked'] });

      // Keep enforcing for a short window to defeat any async toggles
      let enforceCount = 0;
      const enforceInterval = setInterval(() => {
        if (shouldSelfLock()) {
          applySelfLock();
        }
        enforceCount += 1;
        if (enforceCount > 20) {
          clearInterval(enforceInterval);
        }
      }, 250);
    }

    // Also enforce toggle off when the assigned-user filter targets the logged-in user
    (function attachSelfLockWatcher() {
      const assignedUserInput = document.getElementById('isolatedFilterAssigneduserName');
      if (!assignedUserInput || !managerToggle) return;

      const syncToggleWithAssignedUser = () => {
        const target = (assignedUserInput.value || '').trim();
        const isSelf = matchesUser(target);
        const prevChecked = managerToggle.checked;

        if (isSelf) {
          managerToggle.checked = false;
          managerToggle.dataset.selfLock = '1';
          managerToggle.disabled = true;
          if (prevChecked !== managerToggle.checked) {
            managerToggle.dispatchEvent(new Event('change'));
          }
        } else if (managerToggle.dataset.selfLock === '1') {
          managerToggle.dataset.selfLock = '';
          managerToggle.disabled = false;
        }
      };

      assignedUserInput.addEventListener('change', syncToggleWithAssignedUser);
      assignedUserInput.addEventListener('input', syncToggleWithAssignedUser);
      syncToggleWithAssignedUser();
    })();

    // Helper: force toggle off if multiFilters.user equals self (covers apply button flows)
    function enforceSelfLockFromFilters() {
      if (!managerToggle) return;
      const mfUser = window.multiFilters && window.multiFilters.user ? String(window.multiFilters.user).trim() : '';
      const isSelf = matchesUser(mfUser);
      const prevChecked = managerToggle.checked;
      if (isSelf) {
        managerToggle.checked = false;
        managerToggle.dataset.selfLock = '1';
        managerToggle.disabled = true;
        if (prevChecked !== managerToggle.checked) {
          managerToggle.dispatchEvent(new Event('change'));
        }
      } else if (managerToggle.dataset.selfLock === '1') {
        managerToggle.dataset.selfLock = '';
        managerToggle.disabled = false;
      }
    }

    // Only push the URL user filter when provided and not empty
    if (filterUserParam && !isSelfSelection) {
      window.__filterUserFromUrl = filterUserParam;
      window.multiFilters = window.multiFilters || {};
      window.multiFilters.user = filterUserParam;
    } else {
      window.__filterUserFromUrl = null;
      if (window.multiFilters && window.multiFilters.user) {
        delete window.multiFilters.user;
      }
    }

    // Re-check after URL/multiFilters adjustments
    enforceSelfLockFromFilters();

    // Ensure badge label reflects the current view on load
    updateMyLeadsLabel(managerToggle && managerToggle.checked);

    if (dateFrom || dateTo) {
      $("#isolatedFilterStartDate").val(dateFrom);
      $("#isolatedFilterEndDate").val(dateTo);

      // Ensure multiFilters has these dates
      if (window.multiFilters) {
        window.multiFilters.start_date = dateFrom;
        window.multiFilters.end_date = dateTo;
      } else {
        window.multiFilters = {
          start_date: dateFrom,
          end_date: dateTo
        };
      }
    }


    // Function to encrypt phone number (simple masking)
    function encryptPhone(phone) {
      if (!phone) return "";

      // Convert to string in case it's a number or other type
      const phoneStr = String(phone);

      // Remove all non-digit characters
      const digitsOnly = phoneStr.replace(/\D/g, "");

      // Handle very short numbers (show nothing)
      if (digitsOnly.length <= 2) return "*";

      // For Indian numbers (typically 10 digits) show last 5 digits
      if (digitsOnly.length === 10) {
        return "*****" + digitsOnly.slice(-5);
      }

      // For international numbers with country code, show last 4 digits
      if (digitsOnly.length > 10) {
        return "***-" + digitsOnly.slice(-4);
      }

      // Default case - show last 4 digits
      const lastFour = digitsOnly.slice(-4);
      return "*****" + lastFour;
    }

    // Function to get last touch status (This logic might need adjustment based on actual data from update_status.php)
    function getLastTouchStatus(dateString, daysUntouched = null) {
      if (
        !dateString ||
        typeof dateString !== "string" ||
        dateString.trim() === "" ||
        dateString.trim().toLowerCase() === "untouched"
      ) {
        // If daysUntouched is provided and it's a pending lead, show duration
        if (daysUntouched !== null && daysUntouched !== undefined) {
          const days = parseInt(daysUntouched);
          if (days >= 30) {
            const months = Math.floor(days / 30);
            return {
              text: `Untouched from ${months}M`,
              class: "untouched",
            };
          } else {
            return {
              text: `Untouched from ${days}D`,
              class: "untouched",
            };
          }
        }

        return {
          text: "(Untouched)",
          class: "untouched",
        };
      }

      const date = new Date(dateString);

      if (isNaN(date.getTime())) {
        return {
          text: "(Invalid Date)",
          class: "untouched",
        };
      }

      const today = new Date();
      const diffTime = today - date;
      const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));

      if (diffDays === 0) {
        return {
          text: "Touched today",
          class: "recent",
        };
      } else if (diffDays === 1) {
        return {
          text: "Touched yesterday",
          class: "recent",
        };
      } else {
        return {
          text: `Untouched from ${diffDays} days`,
          class: "over-week",
        };
      }
    }

    // Function to update selection actions visibility
    /**
     * formatPhoneForCall(num)
     * Normalises any phone number to a diallable +91 tel: string.
     *   10 digits         → +91xxxxxxxxxx
     *   12 digits (91...) → +91xxxxxxxxxx  (already has country code)
     *   anything else     → +<digits>       (fallback — don't lose the number)
     */
    function formatPhoneForCall(num) {
      const digits = String(num || '').replace(/\D/g, '');
      if (!digits) return '';
      if (digits.length === 12 && digits.startsWith('91')) return '+' + digits;
      if (digits.length === 10) return '+91' + digits;
      return '+' + digits; // fallback
    }

    // Opens native phone dialer with a safe fallback for browsers that block
    // synthetic clicks after async confirmation dialogs.
    function openDialpad(phoneNumber) {
      const dialTarget = String(phoneNumber || '').trim();
      if (!dialTarget) return;

      const telHref = dialTarget.startsWith('tel:') ? dialTarget : `tel:${dialTarget}`;

      const fallbackTimer = window.setTimeout(() => {
        if (!document.hidden && document.visibilityState === 'visible') {
          window.location.href = telHref;
        }
      }, 250);

      const clearFallback = () => {
        window.clearTimeout(fallbackTimer);
      };

      window.addEventListener('pagehide', clearFallback, { once: true });
      window.addEventListener('blur', clearFallback, { once: true });

      const callAnchor = document.createElement('a');
      callAnchor.href = telHref;
      callAnchor.style.display = 'none';
      document.body.appendChild(callAnchor);
      callAnchor.click();
      window.setTimeout(() => callAnchor.remove(), 200);
    }

    function updateSelectionActions() {

      const $selectedRows = $(".row-checkbox:checked");
      const selectedCount = $selectedRows.length;
      const $mobileNav = $(".mobile-bottom-nav");
      const isMobile = window.innerWidth <= 768;
      const canDeleteLeads = window.canDeleteLeads === true;
      const $deleteButtons = $(".delete-selected-btn");

      // Always show .selection-count, default to 0
      $(
        ".assign-btn-mobile .selection-count, .delete-btn-mobile .selection-count"
      )
        .text(selectedCount)
        .css("display", "flex");

      if (selectedCount > 0) {
        if (isMobile) {
          $(".selection-actions").hide();
          $(".remarks-btn-mobile, .columns-btn-mobile").hide();
          $(".whatsapp-btn-mobile").show();
        } else {
          $(".selection-actions").show();
        }
        $mobileNav.addClass("has-selection");
        $(".assign-btn-mobile, .delete-btn-mobile").show();
      } else {
        if (isMobile) {
          $(".remarks-btn-mobile, .columns-btn-mobile").show();
          $(".whatsapp-btn-mobile").hide();
        }
        $(".selection-actions").hide();
        $mobileNav.removeClass("has-selection");
        // Do not hide selection count, just reset it to 0
        $(".assign-btn-mobile, .delete-btn-mobile").show();
      }

      if (!canDeleteLeads) {
        $deleteButtons.prop("disabled", true).addClass("disabled");
      } else {
        $deleteButtons
          .prop("disabled", selectedCount === 0)
          .toggleClass("disabled", selectedCount === 0);
      }
    }



    $(document).on("click", ".delete-btn-mobile", function () {
      $(".delete-selected-btn").click();
    });

    $(document).on("click", ".whatsapp-btn-mobile", function () {
      $("#whatsappBulkBtn").click();
    });

    $(document).on("click", ".remarks-btn-mobile, .columns-btn-mobile", function (e) {
      e.preventDefault();
      e.stopPropagation();

      isRemarksModeActive = !isRemarksModeActive;

      if (isRemarksModeActive) {
        remarksModeRestoredRows.clear();
      }

      applyRemarksModeToVisibleRows();
    });

    $(document).on("click", ".lead-avatar", function (e) {
      if (!isRemarksModeActive) return;

      e.stopPropagation();

      const $row = $(this).closest("tr");
      if (!$row.length || $row.hasClass("details-row")) return;

      const rowId = getRowLeadId($row);
      if (!rowId) return;

      if (remarksModeRestoredRows.has(rowId)) {
        remarksModeRestoredRows.delete(rowId);
      } else {
        remarksModeRestoredRows.add(rowId);
      }

      applyRemarksModeToVisibleRows();
    });

    $(document).on(
      "click",
      ".update-status-btn, .details-row .update-status-btn",
      function (e) {
        e.stopPropagation();
        e.preventDefault();

        const $btn = $(this);
        const $row = $btn.closest("tr").is(".details-row")
          ? $btn.closest("tr").prev("tr")
          : $btn.closest("tr");

        const leadId =
          $btn.data("id") || $row.find(".lead-id-hidden").text().trim();
        const leadName = $row.find(".lead-info h4").text().trim();
        const userUniqueId = ($btn.data("userid") || $row.find(".assigned-lead").text()).toString().trim();

        openStatusModal(leadId, leadName, '', '', userUniqueId);

      }
    );


    // Close when clicking outside modal
    $("#statusModal").on("click", function (e) {
      if ($(e.target).hasClass("modal-overlay")) {
        closeStatusModal();
      }
    });

    // Prevent modal from closing when clicking inside
    $("#statusModal .modal-container").on("click", function (e) {
      e.stopPropagation();
    });

    // Close modal with Escape key
    $(document).on("keydown", function (e) {
      if (e.key === "Escape" && $("#statusModal").hasClass("active")) {
        closeStatusModal();
      }
    });
    // Function to get selected lead IDs
    function getSelectedLeadIds() {
      const selectedIds = [];
      $(".row-checkbox:checked").each(function () {
        const $row = $(this).closest("tr");

        // First try to get ID from the hidden element in lead column
        let leadId = $row.find(".lead-id-hidden").text().trim();

        // If not found, try to get from the ID column (if visible)
        if (!leadId) {
          leadId = $row.find(".lead-id-section").text().replace("#", "").trim();
        }

        if (leadId) {
          selectedIds.push(leadId);
        }
      });
      return selectedIds;
    }
    async function loadUsersIntoDropdown() {
      try {
        const response = await fetch("update_status.php?get_users=1&all_users=1");
        if (response.ok) {
          const options = await response.text();

          const $assignUser = $("#assignUser");
          $assignUser.empty();
          $assignUser.append(
            '<option value="" disabled selected>Select user</option>'
          );
          $assignUser.append(options);

          // If you're using Select2
          $assignUser.trigger("change");
        } else {
          console.error("Failed to fetch users. Status:", response.status);
          hideLeadsLoader();
        }
      } catch (error) {
        console.error("Error fetching users:", error);
        hideLeadsLoader();
      } finally {
        hideLeadsLoader();
      }
    }

    $(document).on("click", "#leadIdentity button", function () {
      // Remove active class from all buttons
      $("#leadIdentity button").removeClass("active");

      // Add active class to clicked button
      $(this).addClass("active");

      // Set the hidden input value
      const value = $(this).data("value");
      $("#leadIdentityValue").val(value);
    });

    // Handle responsive behavior
    function handleResponsiveBehavior() {
      // Add your responsive behavior logic here
      console.log("Handling responsive behavior");
    }

    // Function to handle column visibility based on screen width
    function handleColumnVisibility() {
      if (!$.fn.DataTable.isDataTable('#leadsTable')) return;
      const table = $('#leadsTable').DataTable();

      const w = window.innerWidth;

      // DataTables handles column arrays as a single batch internally
      // Do NOT call draw() here — that causes a double-render of all rows
      if (w <= 532) {
        table.columns([2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 13]).visible(false, false);
      } else if (w <= 900) {
        table.columns([2, 3, 4, 5, 6, 7, 9, 10, 11, 13]).visible(false, false);
      } else if (w <= 1365) {
        table.columns([3, 4, 5, 6, 7, 8, 9]).visible(false, false);
      } else {
        table.columns([8, 9]).visible(false, false);
      }

      // Adjust column widths only — no full redraw
      table.columns.adjust();
    }

    // Call this function on page load and window resize
    $(document).ready(function () {
      handleColumnVisibility();

      // Debounced resize handler — was firing hundreds of times without this
      let _resizeTimer;
      $(window).on('resize', function () {
        clearTimeout(_resizeTimer);
        _resizeTimer = setTimeout(handleColumnVisibility, 250);
      });
    });

    // If you're using DataTables colvis button, you might need to update it too
    $(document).on('click', '.dt-button.columns-btn', function () {
      setTimeout(function () {
        const $collection = $('.dt-button-collection');
        if ($collection.length) {
          $collection[0].style.setProperty('top', 'auto', 'important');
          $collection[0].style.setProperty('bottom', 'auto', 'important');
          $collection[0].style.setProperty('transform', 'translateY(calc(-100% - 8px))', 'important');
          $collection[0].style.setProperty('margin-top', '0', 'important');
        }
        handleColumnVisibility();
      }, 100);
    });

    let loaderFailsafeTimeout = null;
    function showLeadsLoader() {
      const el = document.querySelector('.loader-container');
      if (el) el.style.display = 'flex';
      // Failsafe: always hide loader after 10 seconds
      if (loaderFailsafeTimeout) clearTimeout(loaderFailsafeTimeout);
      loaderFailsafeTimeout = setTimeout(() => {
        hideLeadsLoader();
      }, 5000);
    }

    function hideLeadsLoader() {
      const el = document.querySelector('.loader-container');
      if (el) el.style.display = 'none';
      if (loaderFailsafeTimeout) clearTimeout(loaderFailsafeTimeout);
    }
    // --- Hide loader on script load error (e.g., notification SDK) ---
    window.addEventListener('error', function (e) {
      if (e.target && e.target.tagName === 'SCRIPT') {
        hideLeadsLoader();
      }
    }, true);


    // --- Global AJAX error handler to always hide loader ---
    $(document).ajaxError(function (event, jqxhr, settings, thrownError) {
      hideLeadsLoader();
    });

    // DataTable AJAX error handler
    $(document).on('xhr.dt', function (e, settings, json, xhr) {
      if (xhr && xhr.status && xhr.status !== 200) {
        hideLeadsLoader();
      }
    });

    // Destroy existing DataTable if it exists
    if ($.fn.DataTable.isDataTable("#leadsTable")) {
      $("#leadsTable").DataTable().clear().destroy();
    }
    showLeadsLoader();

    // leadCounts.js

    // Don't call getAllCountData on page load - it will be called after table initialization
    // and filtered counts will be set by updateFilteredBadgeCounts()
    // getAllCountData();

    // Add an event listener for the toggle button to update the data when the toggle is changed
    // document.getElementById("managerToggle").addEventListener("change", () => {
    //     getAllCountData();
    // });

    $(document).on("click", "#untouchedLeads", function () {
      window.currentActiveFilter = "pendingLeads";
      // This will now use client-side filtering
      table.draw();

      scheduleCountRefresh();
    });



    // Initialize DataTable
    function initializeLeadsTable(dateFrom, dateTo) {
      cleanTableControls();
      const urlParams = new URLSearchParams(window.location.search);
      let startDate = urlParams.get('start_date');
      let endDate = urlParams.get('end_date');

      // If no URL params, check session storage
      if (!startDate || !endDate) {
        startDate = sessionStorage.getItem('lead_filter_start_date');
        endDate = sessionStorage.getItem('lead_filter_end_date');
      }

      // Store dates for persistence
      if (startDate && endDate) {
        sessionStorage.setItem('lead_filter_start_date', startDate);
        sessionStorage.setItem('lead_filter_end_date', endDate);

        // Set the date inputs
        $('#dateFrom').val(startDate);
        $('#dateTo').val(endDate);
        $('#isolatedFilterStartDate').val(startDate);
        $('#isolatedFilterEndDate').val(endDate);

        // CRITICAL FIX: Preserve ALL existing filters (status, user, etc.) when initializing dates
        const existingFilters = window.multiFilters ? { ...window.multiFilters } : {};
        window.multiFilters = {
          ...existingFilters,  // Preserve all existing filters FIRST
          start_date: startDate,  // Then set/override dates
          end_date: endDate
        };
        console.log('✅ InitializeLeadsTable - Preserved all filters:', window.multiFilters);
      }

      // Clear persisted filters when leaving the page so returning starts fresh
      const clearPersistedLeadFilters = () => {
        sessionStorage.removeItem('lead_filter_start_date');
        sessionStorage.removeItem('lead_filter_end_date');
        window.multiFilters = {};
        window.headerDropdownFilters = {};
      };
      window.addEventListener('pagehide', clearPersistedLeadFilters, { once: true });
      window.addEventListener('beforeunload', clearPersistedLeadFilters, { once: true });

      function renderCompactLeadsPagination(api) {
        if (!api || typeof api.page !== "function") return;

        const info = api.page.info();
        const totalPages = Number(info?.pages || 0);
        const currentPage = Number(info?.page || 0) + 1;
        const $paginate = $(api.table().container()).find(".dataTables_paginate");

        if (!$paginate.length) return;

        // Keep DataTables previous/next controls and rebuild only numeric section.
        $paginate
          .find(".paginate_button:not(.previous):not(.next), .ellipsis, .compact-page-ellipsis")
          .remove();

        if (totalPages <= 0) return;

        const $next = $paginate.find(".paginate_button.next").first();
        const insertBeforeTarget = $next.length ? $next : null;

        const addPageButton = (page) => {
          const isCurrent = page === currentPage;
          const $btn = $("<a>", {
            href: "#",
            class: `paginate_button compact-page-number${isCurrent ? " current" : ""}`,
            "aria-label": `Page ${page}`,
            "aria-current": isCurrent ? "page" : null,
            text: String(page),
          });

          if (!isCurrent) {
            $btn.on("click", function (e) {
              e.preventDefault();
              api.page(page - 1).draw("page");
            });
          }

          if (insertBeforeTarget) {
            $btn.insertBefore(insertBeforeTarget);
          } else {
            $paginate.append($btn);
          }
        };

        const addEllipsis = () => {
          const $ellipsis = $("<span>", {
            class: "compact-page-ellipsis",
            text: "..",
          });

          if (insertBeforeTarget) {
            $ellipsis.insertBefore(insertBeforeTarget);
          } else {
            $paginate.append($ellipsis);
          }
        };

        if (totalPages <= 3) {
          for (let page = 1; page <= totalPages; page += 1) addPageButton(page);
          return;
        }

        addPageButton(1);

        if (currentPage > 2) addEllipsis();
        if (currentPage !== 1 && currentPage !== totalPages) addPageButton(currentPage);
        if (currentPage < totalPages - 1) addEllipsis();

        addPageButton(totalPages);

        // ── Page Jump widget ──────────────────────────────────────────────
        // Remove any stale jump widget from a previous draw
        $paginate.find('.compact-page-jump').remove();

        if (totalPages > 1) {
          const $jump = $(`
            <span class="compact-page-jump" style="
              display: inline-flex;
              align-items: center;
              gap: 4px;
              margin-left: 8px;
              vertical-align: middle;
            ">
              <input
                type="number"
                class="compact-jump-input"
                placeholder="Page No."
                min="1"
                max="${totalPages}"
                style="
                  width: 72px;
                  padding: 4px 8px;
                  border: 1px solid #ccc;
                  border-radius: 6px;
                  font-size: 13px;
                  outline: none;
                  text-align: center;
                "
              >
              <button class="compact-jump-btn" style="
                padding: 4px 12px;
                background: #4f8ef7;
                color: #fff;
                border: none;
                border-radius: 6px;
                font-size: 13px;
                cursor: pointer;
                font-weight: 600;
              ">Jump</button>
            </span>
          `);

          const doJump = () => {
            const val = parseInt($jump.find('.compact-jump-input').val(), 10);
            if (isNaN(val) || val < 1 || val > totalPages) {
              $jump.find('.compact-jump-input').css('border-color', '#e53e3e');
              setTimeout(() => $jump.find('.compact-jump-input').css('border-color', '#ccc'), 1200);
              return;
            }
            api.page(val - 1).draw('page');
          };

          $jump.find('.compact-jump-btn').on('click', doJump);
          $jump.find('.compact-jump-input').on('keydown', function (e) {
            if (e.key === 'Enter') doJump();
          });

          // Append after the last paginate button (before or after Next)
          if (insertBeforeTarget) {
            $jump.insertAfter(insertBeforeTarget);
          } else {
            $paginate.append($jump);
          }
        }
      }


      if ($.fn.DataTable.isDataTable("#leadsTable")) {
        // Get the table instance
        const oldTable = $("#leadsTable").DataTable();

        // Destroy the table and remove all controls
        oldTable.destroy(true); // true = remove all associated elements

        // Manually clean up the controls container
        $("#tableControlsContainer").empty();
      }
      // Check if we're on mobile - disable responsive extension on mobile to prevent styling recalculation
      // Mobile styling will be handled by CSS media queries instead
      // Use a more robust check that persists across table rebuilds
      // ============================================================================
      // SECTION 3: DATATABLE INITIALIZATION & DATA FETCHING (OPTIMIZED)
      // ============================================================================

      const canDeleteLeads = window.canDeleteLeads === true;
      const tableButtons = [];

      if (canDeleteLeads) {
        tableButtons.push({
          text: '<i class="fas fa-trash"></i>',
          className: "dt-button delete-selected-btn delete-leads-btn",
          titleAttr: "Delete selected leads",
          action: function () {
            handleDeleteSelectedLeads();
          },
        });
      }

      tableButtons.push(
        {
          text: '<i class="fas fa-filter"></i>',
          className: "dt-button filter-btn",
          action: function (e, dt, node, config) {
            $("#filterModal").show();
            $("body").css("overflow", "hidden");

            $(".select-input").select2({
              placeholder: "Select options",
              allowClear: true,
              width: "100%",
              dropdownParent: $("#filterModal"),
            });
          },
        },
        {
          text: '<i class="fas fa-columns"></i>',
          className: "dt-button columns-btn",
          titleAttr: "Column Visibility",
          action: function (e, dt, node) {
            e.preventDefault();
            e.stopPropagation();
            openColumnsModal(dt, node);
          }
        }
      );

      table = $("#leadsTable").DataTable({
        processing: true, // Show processing indicator
        serverSide: true, // Enable server-side processing
        order: [[4, "desc"]],
        deferRender: true, // OPTIMIZED: Defer rendering for large datasets
        searchDelay: 200, // PERF: 200ms — fast enough to feel instant, avoids hammering server
        ajax: {
          url: "update_status.php", // Backend API endpoint
          type: "GET",
          cache: false, // OPTIMIZED: Prevent caching for fresh data
          error: function (xhr, error, thrown) {
            // OPTIMIZED: Handle Ajax errors gracefully - prevent DataTables error popup
            console.error('DataTables Ajax error:', error, thrown);
            hideLeadsLoader();

            // Log error details
            if (xhr.status === 0) {
              console.error('Network error - check connection');
            } else if (xhr.status === 404) {
              console.error('Endpoint not found - check URL');
            } else if (xhr.status === 500) {
              console.error('Server error:', xhr.responseText);
            } else {
              console.error('HTTP Error:', xhr.status, xhr.responseText);
            }

            // OPTIMIZED: Return valid empty response to prevent DataTables error popup
            // DataTables will handle empty data gracefully
          },
          data: function (d) {
            // OPTIMIZED: Get toggle state once and reuse
            const currentToggleState = (managerToggle && managerToggle.dataset.selfLock === '1')
              ? 0
              : (managerToggle && managerToggle.checked ? 1 : 0);

            // OPTIMIZED: Get date filters efficiently
            d.dateFrom = $("#dateFrom").val() || (window.multiFilters?.start_date || '');
            d.dateTo = $("#dateTo").val() || (window.multiFilters?.end_date || '');
            d.length = d.length;
            d.managerToggle = currentToggleState;

            // Only send filterUser when it's not self-selection
            const isSelfSelectionAjax = window.__filterUserFromUrl && matchesUser(window.__filterUserFromUrl);
            if (!isSelfSelectionAjax && window.__filterUserFromUrl) {
              d.filterUser = window.__filterUserFromUrl;
            } else {
              d.filterUser = null;
            }

            // Additionally, if the applied filters target self, force manager view off
            if (window.multiFilters && window.multiFilters.user) {
              const mfUser = String(window.multiFilters.user).trim();
              if (matchesUser(mfUser)) {
                d.managerToggle = 0;
                d.managerView = 'false';
                if (managerToggle) {
                  managerToggle.checked = false;
                  managerToggle.dataset.selfLock = '1';
                  managerToggle.disabled = true;
                }
              }
            }

            if (managerToggle && managerToggle.dataset.selfLock !== '1' && managerToggle.checked) {
              d.managerView = 'true';
            } else {
              d.managerView = 'false';
            }

            // If the dashboard selected the same user as the logged-in user, force individual view to avoid empty team results
            if (window.__filterUserFromUrl && ((currentUser && window.__filterUserFromUrl === currentUser) || (currentUserName && window.__filterUserFromUrl === currentUserName))) {
              d.managerToggle = 0;
              d.managerView = 'false';
              if (managerToggle) managerToggle.checked = false;
            }

            if (window.multiFilters && window.multiFilters.start_date) {
              d.dateFrom = window.multiFilters.start_date;
            }
            if (window.multiFilters && window.multiFilters.end_date) {
              d.dateTo = window.multiFilters.end_date;
            }

            if (d.length === -1) {
              // For "All" option, we need to set a very high number
              d.length = 1000000; // Adjust this number based on your expected maximum rows
            }

            // OPTIMIZED: Combine filters efficiently (single pass)
            const combinedFilters = {};
            if (window.multiFilters && Object.keys(window.multiFilters).length > 0) {
              Object.assign(combinedFilters, window.multiFilters);
            }
            if (window.headerDropdownFilters && Object.keys(window.headerDropdownFilters).length > 0) {
              Object.assign(combinedFilters, window.headerDropdownFilters);
            }
            d.multiFilters = Object.keys(combinedFilters).length > 0 ? JSON.stringify(combinedFilters) : "";

            // OPTIMIZED: Add column searches for ID (column 3) and Created At (column 4) from headerDropdownFilters
            // DataTables sends columns as array, but we need to ensure proper format for backend
            // Backend expects: columns[i][search][value] format
            if (window.headerDropdownFilters) {
              if (window.headerDropdownFilters.id && window.headerDropdownFilters.id.trim() !== '') {
                const idValues = window.headerDropdownFilters.id.split(',').map(v => v.trim()).filter(v => v);
                if (idValues.length > 0) {
                  // Initialize columns array if needed (DataTables format)
                  if (!Array.isArray(d.columns)) {
                    d.columns = [];
                  }
                  // Ensure column 3 exists with proper structure
                  if (!d.columns[3]) {
                    d.columns[3] = { search: { value: '' } };
                  }
                  d.columns[3].search.value = idValues.join('|'); // Pipe separator for multiple IDs
                }
              }
              if (window.headerDropdownFilters.created_at && window.headerDropdownFilters.created_at.trim() !== '') {
                const dateValues = window.headerDropdownFilters.created_at.split(',').map(v => v.trim()).filter(v => v);
                if (dateValues.length > 0) {
                  // Initialize columns array if needed (DataTables format)
                  if (!Array.isArray(d.columns)) {
                    d.columns = [];
                  }
                  // Ensure column 4 exists with proper structure
                  if (!d.columns[4]) {
                    d.columns[4] = { search: { value: '' } };
                  }
                  d.columns[4].search.value = dateValues.join('|'); // Pipe separator for multiple dates
                }
              }

              // If no values present, explicitly clear the column search payload to avoid stale filters
              if (!window.headerDropdownFilters.id || window.headerDropdownFilters.id.trim() === '') {
                if (!Array.isArray(d.columns)) d.columns = [];
                if (!d.columns[3]) d.columns[3] = { search: { value: '' } };
                d.columns[3].search.value = '';
              }
              if (!window.headerDropdownFilters.created_at || window.headerDropdownFilters.created_at.trim() === '') {
                if (!Array.isArray(d.columns)) d.columns = [];
                if (!d.columns[4]) d.columns[4] = { search: { value: '' } };
                d.columns[4].search.value = '';
              }
            } else {
              // No header filters at all; ensure both columns are cleared
              if (!Array.isArray(d.columns)) d.columns = [];
              if (!d.columns[3]) d.columns[3] = { search: { value: '' } };
              if (!d.columns[4]) d.columns[4] = { search: { value: '' } };
              d.columns[3].search.value = '';
              d.columns[4].search.value = '';
            }

            // ── Lazy-load: cap server request to 25 rows (ALL views) ────────
            // For any page-length > 25 (mobile AND desktop), cap the first
            // server request to 25 rows, then fetch & append subsequent
            // batches on scroll via IntersectionObserver.
            const _desiredLen = d.length;
            const _lazyActive = _desiredLen > 25;   // no device restriction

            if (_lazyActive) {
              // Store lazy state for scroll-load continuation
              window._dtLazy = {
                desiredLen: _desiredLen,
                loaded: 0,
                pageStart: d.start,
                params: null
              };
              d.length = 25;
              d.rowsPerPage = 25;
            } else {
              window._dtLazy = null;
            }

            d.page = Math.floor(d.start / (d.length)) + 1;
            d.rowsPerPage = d.length;
            d.searchQuery = d.search?.value || '';
            if (createdAtSortDirection) {
              d.order_dir = createdAtSortDirection;
            } else {
              delete d.order_dir;
            }
            d.filter = window.currentActiveFilter || "";

            window.__leadRequestPayload = {
              dateFrom: d.dateFrom,
              dateTo: d.dateTo,
              multiFilters: d.multiFilters,
              filter: d.filter,
              managerToggle: currentToggleState,
              filterUser: d.filterUser || null,
              managerView: d.managerView || null
            };

            // Snapshot ALL params for continuation fetches (lazy scroll)
            if (_lazyActive && window._dtLazy) {
              window._dtLazy.params = { ...d };
            }

            delete d.search;
            return d;
          },
          dataSrc: function (json) {
            // OPTIMIZED: Fast data processing for large datasets with error handling
            // Check if data exists - handle errors gracefully
            if (!json) {
              console.error("âŒ No server response");
              hideLeadsLoader();
              return [];
            }

            // Handle error responses
            if (json.error || json.status === 'error') {
              console.error("âŒ Server error:", json.error || json.message);
              hideLeadsLoader();
              return [];
            }

            // Ensure data array exists
            if (!json.data || !Array.isArray(json.data)) {
              console.error("âŒ Invalid server response - missing data array");
              hideLeadsLoader();
              return [];
            }

            // OPTIMIZED: Set records info efficiently
            const totalRows = json.totalRows || 0;
            json.recordsTotal = totalRows;
            json.recordsFiltered = totalRows;

            // OPTIMIZED: Notify parent only once
            if (!parentNotifiedOnce) {
              const hasDateRange = window.__leadRequestPayload?.dateFrom && window.__leadRequestPayload?.dateTo;
              if (hasDateRange) {
                notifyParentLeadsReady({
                  totalRows: totalRows,
                  filters: window.multiFilters || null,
                  activeFilter: window.currentActiveFilter || "",
                  request: window.__leadRequestPayload || null,
                });
                parentNotifiedOnce = true;
              }
            }

            // OPTIMIZED: Update active filter button count
            if (window.currentActiveFilter) {
              const activeButton = document.querySelector('.filter-btn.active');
              if (activeButton) {
                const countSpan = activeButton.querySelector('.count');
                if (countSpan) countSpan.textContent = totalRows;
              }
            }

            // OPTIMIZED: Don't update tag counts here - will be called once after table initialization
            // This prevents duplicate API calls on page load
            // updateAllTagCounts();

            // OPTIMIZED: Map data efficiently (single pass)
            return json.data.map((row) => ({
              id: row.id,
              remark_id: row.remark_id,
              name: row.name,
              lead_identity: row.lead_identity,
              profilePic: getNextIconPath(),
              created: row.created_at,
              lastTouch: row.user_status === "Pending" ? "Untouched" : row.created_at,
              email: row.email,
              phone: row.number,
              remarks: row.user_remarks || row.remarks || "",
              updatedAt: row.updated_at || row.user_remarks_updated_at || row.remarks_updated_at || "",
              assignedLead: row.user_unique_id,
              assignedProject: row.assign_project_name,
              status: row.user_status,
              location: row.location_status,
              source: row.source_of_lead,
              budget: row.budget,
              daysUntouched: row.days_untouched,
              isOverdue: row.is_overdue,
              overdueFromDays: row.overdue_from_days,
              recording_url: row.recording_url,
              unread_wa_count: row.unread_wa_count || 0,
              wa_interest: row.wa_interest || 'none',
            }));
          },
        },
        // Column visibility is handled manually via handleColumnVisibility()
        // The Responsive plugin recalculates ALL row layouts on every draw — very expensive at 250 rows
        responsive: false,
        // Build DOM nodes lazily instead of all 250 at once
        deferRender: true,
        ordering: false,
        dom: '<"top-container"<"search-container"f><"button-container"B><"length-container"l>>rt<"bottom-container"ip>',
        buttons: tableButtons,
        lengthMenu: [
          [5, 10, 25, 50, 100, 250, 300],
          [5, 10, 25, 50, 100, 250, 300]
        ],
        pageLength: 10,
        drawCallback: function (settings) {
          const api = this.api();
          renderCompactLeadsPagination(api);

          // Kill any existing lazy observer + abort any in-flight lazy fetch
          if (window._dtLazyObserver) {
            window._dtLazyObserver.disconnect();
            window._dtLazyObserver = null;
          }
          if (window._dtLazyAbortCtrl) {
            window._dtLazyAbortCtrl.abort();
            window._dtLazyAbortCtrl = null;
          }

          // ── Lazy infinite-scroll: fetch next batch of rows on scroll ─────
          // ajax.data already capped the server to 25 rows.
          // Here we watch the last rendered row and fetch the next batch
          // via a direct fetch() call (with AbortController so stale
          // requests from a previous filter are cancelled immediately).
          if (window._dtLazy) {
            const lazy = window._dtLazy;
            const BATCH = 25;  // PERF: 25-row batches → 12 requests for 300 rows (was 28)
            const $tbody = $(api.table().body());
            const tbodyNode = api.table().body();
            const totalWanted = lazy.desiredLen;
            lazy.loaded = $tbody.find('tr').not('.details-row').length;



            const fetchAndAppend = () => {
              if (lazy.loaded >= totalWanted) return;

              // Build query params for the next batch
              const nextStart = lazy.pageStart + lazy.loaded;
              const params = new URLSearchParams({
                ...lazy.params,
                start: nextStart,
                page: Math.floor(nextStart / BATCH) + 1,
                rowsPerPage: BATCH,
                length: BATCH
              });

              // Create a fresh AbortController for this fetch
              window._dtLazyAbortCtrl = new AbortController();
              fetch('update_status.php?' + params.toString(), { signal: window._dtLazyAbortCtrl.signal })
                .then(r => r.json())
                .then(json => {
                  if (!json.data || !json.data.length) return;

                  // Map server rows → row objects (same as dataSrc)
                  const newRows = json.data.map(row => ({
                    id: row.id, remark_id: row.remark_id,
                    name: row.name, lead_identity: row.lead_identity,
                    profilePic: getNextIconPath(),
                    created: row.created_at,
                    lastTouch: row.user_status === 'Pending' ? 'Untouched' : row.created_at,
                    email: row.email, phone: row.number,
                    remarks: row.user_remarks || row.remarks || '',
                    updatedAt: row.updated_at || '',
                    assignedLead: row.user_unique_id,
                    assignedProject: row.assign_project_name,
                    status: row.user_status, location: row.location_status,
                    source: row.source_of_lead, budget: row.budget,
                    daysUntouched: row.days_untouched,
                    isOverdue: row.is_overdue, overdueFromDays: row.overdue_from_days,
                    recording_url: row.recording_url,
                    unread_wa_count: row.unread_wa_count || 0,
                    wa_interest: row.wa_interest || 'none'
                  }));

                  // ── Register rows with DataTables (critical for click handlers) ──
                  // Previously we appended raw <tr> HTML which bypassed DataTables'
                  // internal registry. As a result, table.row(element).data() returned
                  // undefined for lazy rows, silently breaking ALL click handlers
                  // (row expand, remarks mode, history panel, etc.).
                  //
                  // Fix: use api.row.add() to put each row into aoData, then call
                  // DataTables' internal _fnCreateTr to build the proper <tr>/<td>
                  // nodes (with correct classes, visibility, cell refs). This makes
                  // api.row(element).data() work for every lazily-loaded row.
                  const dtSettings = api.settings()[0];
                  const _fnCreateTr = dtSettings.oApi && dtSettings.oApi._fnCreateTr;
                  const newlyAppendedNodes = []; // tracks only the rows added this batch

                  newRows.forEach(rowData => {
                    // 1. Register with DataTables internal data store (no draw/ajax)
                    const rowApi = api.row.add(rowData);
                    const rowIdx = rowApi.index();

                    // 2. Build the <tr> node via DataTables internals if available,
                    //    otherwise fall back to manual column rendering
                    if (_fnCreateTr) {
                      _fnCreateTr(dtSettings, rowIdx, null, null);
                    } else {
                      // Fallback: build cells manually (column renderers still fire)
                      const colDefs = dtSettings.aoColumns;
                      const cells = colDefs.map(col => {
                        const cellData = col.mData ? rowData[col.mData] : rowData;
                        const html = typeof col.mRender === 'function'
                          ? col.mRender(cellData, 'display', rowData)
                          : (cellData != null ? cellData : '');
                        const vis = col.bVisible !== false ? '' : ' style="display:none"';
                        return `<td${vis}>${html}</td>`;
                      });
                      const $tr = $(`<tr>${cells.join('')}</tr>`);
                      dtSettings.aoData[rowIdx].nTr = $tr[0];
                      dtSettings.aoData[rowIdx].anCells = $tr.find('td').toArray();
                    }

                    const rowNode = dtSettings.aoData[rowIdx] && dtSettings.aoData[rowIdx].nTr;
                    if (rowNode) {
                      dtSettings.aiDisplay.push(rowIdx);
                      dtSettings.aiDisplayMaster.push(rowIdx);
                      $tbody.append(rowNode);
                      newlyAppendedNodes.push(rowNode); // track new nodes only
                    }
                  });

                  lazy.loaded += newRows.length;

                  // ── Phase 2: Post-append deferred via requestAnimationFrame ──
                  // Browser paints new rows first, THEN we do details-row creation
                  // and remarks-mode toggling — only on the newly added rows,
                  // never re-scanning the entire tbody. Eliminates scroll stutter.
                  requestAnimationFrame(() => {
                    const $newRows = $(newlyAppendedNodes);

                    // Create .details-row only for new rows
                    $newRows.each(function () {
                      const $row = $(this);
                      if ($row.hasClass('details-row')) return;
                      const detailsContent = createDetailsContent($row);
                      if (!detailsContent) return;
                      const leadId = $row.find('td:eq(1) .lead-id-section').text();
                      const $dr = $(`<tr class="details-row" data-parent-id="${leadId}">
                        <td colspan="10"><div class="details-content">${detailsContent}</div></td>
                      </tr>`);
                      $row.after($dr);
                      $dr.hide();
                      const $cc = $dr.find('.call-counter');
                      if ($cc.length && typeof fetchCallCount === 'function') {
                        fetchCallCount($cc.data('id'), $dr[0]);
                      }
                    });

                    // Apply remarks mode only to new rows
                    if (isRemarksModeActive) {
                      $newRows.each(function () {
                        const $row = $(this);
                        if ($row.hasClass('details-row')) return;
                        const rowId = getRowLeadId($row);
                        const isRestored = rowId && remarksModeRestoredRows.has(rowId);
                        $row.addClass('remarks-mode-active');
                        $row.toggleClass('row-show-details', !!isRestored);
                      });
                    }

                    // Set up observer for the next batch, or clean up
                    if (lazy.loaded < totalWanted) {
                      observeLast();
                    } else {

                    }
                  });
                })
                .catch(err => {
                  // AbortError is expected when a new draw fires mid-fetch — ignore silently
                  if (err && err.name !== 'AbortError') console.warn('lazy fetch error', err);
                });
            };

            const observeLast = () => {
              // Only count real data rows — exclude .details-row (hidden panels)
              // inserted by handleResponsiveBehavior, otherwise the sentinel
              // lands on a hidden row that IntersectionObserver never fires for.
              const rows = $tbody.find('tr').not('.details-row');
              // Sentinel = row halfway through current visible rows
              const sidx = Math.max(0, rows.length - Math.ceil(BATCH / 2));
              const node = rows[sidx];
              if (!node) return;

              window._dtLazyObserver = new IntersectionObserver(entries => {
                entries.forEach(e => {
                  if (e.isIntersecting) {
                    window._dtLazyObserver.disconnect();
                    window._dtLazyObserver = null;
                    fetchAndAppend();
                  }
                });
                // PERF: 0.5 = prefetch when sentinel is 50% visible (was 0.1)
                // eliminates the visible gap between rendered rows and incoming batch
              }, { root: null, threshold: 0.5 });

              window._dtLazyObserver.observe(node);
            };

            observeLast();
          }

          hideLeadsLoader();
        },
        initComplete: function (settings, json) {
          // Fallback to ensure loader hides once fully initialized
          hideLeadsLoader();
        },
        columns: [
          {
            data: null,
            className: "checkbox-cell no-colvis",
            orderable: false,
            render: function () {
              return '<input type="checkbox" class="row-checkbox">';
            },
          },
          {
            data: null,
            className: "no-colvis",
            render: function (data, type, row) {
              const encryptedPhone = encryptPhone(row.phone);
              const touchStatus = getLastTouchStatus(row.lastTouch, row.daysUntouched);

              // Determine lead identity icon
              let leadIdentityIcon = "";
              switch (row.lead_identity) {
                case "Hot":
                  leadIdentityIcon = '<i class="fas fa-fire hot-icon"></i>';
                  break;
                case "Warm":
                  leadIdentityIcon = '<i class="fas fa-sun warm-icon"></i>';
                  break;
                case "Cold":
                  leadIdentityIcon =
                    '<i class="fas fa-snowflake cold-icon"></i>';
                  break;
                default:
                  leadIdentityIcon = "";
              }

              // Get the source icon path
              let sourceIconPath = "assets/dataimage/mecntec-icon.png";
              let sourceName = "Unknown";
              if (row.source) {
                const sourceLower = row.source.toLowerCase();
                if (sourceLower.includes("google ads lead")) {
                  sourceIconPath = "assets/dataimage/mecntec-icon.png";

                } else if (sourceLower.includes("google ads")) {
                  sourceIconPath = "assets/dataimage/google-logo.svg";
                  sourceName = "Google Ads";
                } else if (sourceLower.includes("facebook ads lead")) {
                  sourceIconPath = "assets/dataimage/mecntec-icon.png";


                } else if (sourceLower.includes("facebook ads")) {
                  sourceIconPath = "assets/dataimage/facebook.svg";
                  sourceName = "Facebook Ads";
                } else if (sourceLower.includes("99acres")) {
                  sourceIconPath = "assets/dataimage/99acre.png";
                  sourceName = "99acres ads";
                } else if (sourceLower.includes("magicbricks")) {
                  sourceIconPath = "assets/dataimage/magicbricks.png";
                  sourceName = "magicbricks ads";
                } else if (sourceLower.includes("housing.com")) {
                  sourceIconPath = "assets/dataimage/housing.png";
                  sourceName = "housing.com ads";
                } else {
                  sourceIconPath = "assets/dataimage/mecntec-icon.png";
                  sourceName = row.source; // fallback to original text
                }
              }

              const remarksTextRaw = row.remarks == null ? "" : String(row.remarks).trim();
              const remarksTextSafe = window.escapeHtml
                ? window.escapeHtml(remarksTextRaw || "N/A")
                : (remarksTextRaw || "N/A");

              return `
                    <div class="lead-profile">
                        <div class="left-lead">
                            <img src="${row.profilePic}" alt="${row.name
                }" class="lead-avatar">
                            <div class="notes-section">
                                <div class="lead-info">
                                    <div class="created-info" style="display:none"><b>Created at</b><br>${row.created
                }</div>
                                    <div class="mobile-project-info">
                                        <div class="lead-name">
                                            <h4>${row.name}</h4>
                                            <span class="status-badge mobile-only-badge ${row.status.toLowerCase().replace(/ /g, '-')}" style="font-size:9px;padding:2px 5px;white-space:nowrap;margin-left:4px;vertical-align:middle;">${row.status}</span>
                                            ${leadIdentityIcon}
                                            ${(row.source && row.source.toLowerCase().includes("ivr"))
                  ? `<i class="fas fa-pencil-alt edit-ivr-lead-btn" 
                                                      title="Edit IVR Lead" 
                                                      data-id="${row.id}" 
                                                      data-name="${window.escapeHtml ? window.escapeHtml(row.name) : row.name}" 
                                                      data-email="${window.escapeHtml ? window.escapeHtml(row.email || '') : (row.email || '')}" 
                                                      style="color: #6b7280; font-size: 11px; cursor: pointer; margin-left: 5px;"></i>`
                  : ''}
                                        </div>
                                        <div class="mobile-remarks-view" style="display:none;">
                                          <span class="mobile-remarks-label">Remarks:</span>
                                          <span class="mobile-remarks-text">${remarksTextSafe}</span>
                                        </div>
                                        <div class="lead-id-hidden" style="display:none">${row.id
                }</div>
                                        <div class="remark-id-hidden" style="display:none">${row.remark_id
                }</div>
                                        <div class="assigned-lead" style="display:none">${row.assignedLead
                }</div>
                                        <div class="unread-wa-hidden" style="display:none">${row.unread_wa_count}</div>
                                    </div>
                                    ${row.status === "Pending"
                  ? `<div class="last-touch-status touch-desktop ${touchStatus.class}">${touchStatus.text}</div>`
                  : ""
                }
                                    <div class="mobile-project-info">
                                        <span class="project-name-mobile">Project: ${row.assignedProject
                }</span>
                                    </div>
                                    <div class="phone-info encrypted" data-real-phone="${row.phone
                }">${encryptedPhone}</div>
                                </div>
                            </div>
                        </div>
                        <div class="right-lead">
                            <div class="mobile-source">
                                ${row.isOverdue && row.isOverdue == 1
                  ? `<div class="last-touch-status overdue-status" style="animation: blink 1.5s infinite; color: #ff4444; font-weight: bold; font-size: 11px;">Overdue from ${row.overdueFromDays || '1'}D</div>`
                  : `<img src="${sourceIconPath}" alt="${sourceName}" class="source-logo">`
                }
                            </div>
                            ${row.status === "Pending"
                  ? `<div class="last-touch-status touch-mobile ${touchStatus.class}">${touchStatus.text}</div>`
                  : ""
                }
                        </div>
                    </div>
                    <div class="created-info-mobile" style="display:none">${row.created
                }</div>
                `;
            },
          },
          {
            data: null,
            className: "  project-column",
            render: function (data, type, row) {
              return `
                        <div class="project-info">
                            <div class="project-name">${row.assignedProject
                }</div>
                            <span class="status-badge ${row.status
                  .toLowerCase()
                  .replace(" ", "-")}">${row.status}</span>
                        </div>
                    `;
            },
          },
          {
            data: "id",
            className: "id-column",
            visible: false,
            render: function (data) {
              return `<div class="lead-id-section">#${data}</div>`;
            },
          },
          {
            data: "created",
            className: "  created-column",
            visible: false,
            render: function (data, type, row) {
              const rawCreated = row.created || data;
              if (rawCreated && typeof rawCreated === "string" && rawCreated.includes(" ")) {
                const parts = rawCreated.split(" ");
                return `<div class="created-info">${parts[0]} <br>${parts[1]}</div>`;
              }
              return `<div class="created-info">${rawCreated || "N/A"}</div>`;
            },
          },

          {
            data: "email",
            className: "  email-column",
            visible: false,

            render: function (data) {
              return `<div class="contact-info">${data}</div>`;
            },
          },


          {
            data: "location",  // Change from "location" to "location_status"
            className: "  location-column",
            visible: false,
            render: function (data) {
              return `<div class="location-info">${data}</div> `;
            },
          },
          {
            data: "budget",
            className: "  budget-column",
            visible: false,
            render: function (data) {
              return data ? `<div class="budget-info">${data}</div>` : '<div class="budget-info">N/A</div>';
            }
          },
          {
            data: "remarks",
            className: "  remarks-column",
            visible: false,
            render: function (data, type) {
              const rawRemarks = data == null ? "" : String(data).trim();

              if (type !== "display") {
                return rawRemarks || "N/A";
              }

              if (!rawRemarks) {
                return `<div class="remarks-info">N/A</div>`;
              }

              const safeRemarks = window.escapeHtml
                ? window.escapeHtml(rawRemarks)
                : rawRemarks;

              return `<div class="remarks-cell-scroll" title="${safeRemarks}">${safeRemarks}</div>`;
            }
          },
          {
            data: "updatedAt",
            className: "updated-at-column",
            visible: false,
            render: function (data) {
              if (data && typeof data === "string" && data.includes(" ")) {
                const parts = data.split(" ");
                return `<div class="updated-at-info">${parts[0]} <br>${parts[1]}</div>`;
              }
              return `<div class="updated-at-info">${data || "N/A"}</div>`;
            },
          },
          {
            data: "assignedLead",
            className: "  assigned-column",

            render: function (data) {
              return `<div class="assigned-lead">${data}</div>`;
            },
          },

          {
            data: "source",
            className: "  source-column",

            render: function (data, type, row) {
              // Check if the lead is overdue
              if (row.isOverdue && row.isOverdue == 1) {
                return `
        <div class="overdue-status" 
             style="animation: blink 1.5s infinite; color: #ff4444; font-weight: bold; font-size: 11px; text-align: center;">
          Overdue from ${row.overdueFromDays || '1'}D
        </div>
      `;
              }

              // Otherwise show the source logo as normal
              let sourceIconPath = "assets/dataimage/mecntec-icon.png";

              if (data) {
                const sourceLower = data.toLowerCase(); // normalize for comparison
                if (sourceLower.includes("google ads lead")) {
                  sourceIconPath = "assets/dataimage/mecntec-icon.png";
                } else if (sourceLower.includes("facebook ads lead")) {
                  sourceIconPath = "assets/dataimage/mecntec-icon.png";
                }
                else if (sourceLower.includes("google")) {
                  sourceIconPath = "assets/dataimage/google-logo.svg";
                } else if (sourceLower.includes("facebook")) {
                  sourceIconPath = "assets/dataimage/facebook.svg";
                } else if (sourceLower.includes("99acres")) {
                  sourceIconPath = "assets/dataimage/99acre.png";
                } else if (sourceLower.includes("magicbricks")) {
                  sourceIconPath = "assets/dataimage/magicbricks.png";
                } else if (sourceLower.includes("housing")) {
                  sourceIconPath = "assets/dataimage/housing.png";
                }
              }

              return `
      <img src="${sourceIconPath}" 
           alt="${data || 'Source'}"
           class="source-logo" 
           style="height: 20px; width: auto;"
           onerror="this.onerror=null; this.src='assets/dataimage/mecntec-icon.png';">
    `;
            },
          },


          {
            data: null,
            className: "expand-btn-cell no-colvis",
            orderable: false,
            render: function () {
              return `
            <button class="expand-row-btn" aria-label="Expand row">
                <i class="fas fa-chevron-down down-arrow"></i>
                <i class="fas fa-chevron-up up-arrow" style="display: none;"></i>
            </button>
        `;
            },
          },
          {
            data: null,
            className: "  actions-column",
            orderable: false,
            render: function (data, type, row) {
              const hasRecording = row.recording_url && row.recording_url.trim() !== '';
              return `
                    <div class="action-buttons-leads">
                        <div class="different-wrapper">
                        <button class="action-btn status tooltip update-button different-buttons update-status-btn" data-tooltip="update status" data-userid="${row.assignedLead}" data-id="${row.id}" data-toggle="modal" data-target="#statusModal">
                            <i class="fas fa-refresh"></i>
                        </button>
                    </div>
                        <button class="action-btn reassign tooltip" data-tooltip="Reassign" data-id="${row.id}" data-toggle="modal" data-target="#reassignModal"><i class="fas fa-user-friends"></i></button>
                        <button class="action-btn history tooltip unique-toggle-btn" data-tooltip="History" data-id="${row.id}" data-userid="${row.assignedLead}"><i class="fas fa-history"></i></button>
                        <button class="action-btn whatsapp tooltip" data-tooltip="WhatsApp">
                            <i class="fab fa-whatsapp"></i>
                        </button>
                        <span class="wa-interest-hidden" style="display:none;">${row.wa_interest || ''}</span>
                        <button class="action-btn recording tooltip play-recording-btn
                              ${!hasRecording ? 'disabled-btn' : ''}"
                              data-tooltip="Recording"
                              data-url="${hasRecording ? row.recording_url : ''}"
                              ${!hasRecording ? 'disabled' : ''}>
                             <i class="fa fa-volume-up"></i>
                        </button>
                        <div class="phone-btn-wrapper different-wrapper cursor-new">
                            <a style="cursor: no-drop" href="tel:${formatPhoneForCall(row.phone)}" data-id="${row.id}">
                                <button class="call-button action-btn phone tooltip different-buttons" data-tooltip="Call">
                                    <i class="fas fa-phone"></i>
                                </button>
                            </a>
                        <div class="call-btn-counter different-btn-counter call-counter" data-id="${row.id}" data-userid="${row.assignedLead}">0</div>
                    </div>
                      ${buildWhatsAppHistoryButtonMarkup(row, false)}
                    </div>
                    </div>
                `;
            },
          },
        ],
        language: {
          search: "",
          searchPlaceholder: "Search leads...",
          lengthMenu: "_MENU_",
          info: "Showing _START_ to _END_ of _TOTAL_ leads",
          infoEmpty: "No leads to show",
          paginate: {
            first: "First",
            last: "Last",
            next: "Next",
            previous: "Previous",
          },
        },

        initComplete: function () {
          // Move controls to container
          $(".top-container").detach().appendTo("#tableControlsContainer");

          // Note: search-triggered badge updates are handled by updateAllTagCounts()
          // via the 'keyup.searchCountRefresh' handler in setupFilterCounterListeners().
          // A separate IIFE was removed to eliminate the flicker caused by two conflicting
          // badge update calls arriving at different times for the same search event.



          // Add date range filter - COMMENTED OUT: Date selection is available in filter popup
          // const dateRangeHTML = `
          //       <div class="date-range-filter">
          //           <button class="calendar-btn" id="openDateRangePicker">
          //               <i class="fas fa-calendar-alt"></i>
          //           </button>
          //           <div class="date-icon-group">
          //               <div class="date-icon-item">
          //                   <span class="date-label">from</span>
          //                   <i class="fas fa-calendar-alt date-icon" id="dateFromIcon"></i>
          //               </div>
          //               <div class="date-icon-item">
          //                   <span class="date-label">to</span>
          //                   <i class="fas fa-calendar-alt date-icon" id="dateToIcon"></i>
          //               </div>
          //           </div>
          //       <input type="text" id="dateFrom" class="date-input" placeholder="From Date" style="display: none;" onfocus="this.type='date';" onblur="if(!this.value) this.type='text';">
          //       <input type="text" id="dateTo" class="date-input" placeholder="To Date" style="display: none;" onfocus="this.type='date';" onblur="if(!this.value) this.type='text';">
          //       </div>
          //   `;

          // Style the top container
          $(".top-container").css({
            background: "white",
            display: "flex",
            "align-items": "center",
            "justify-content": "space-between",
            "flex-wrap": "wrap",
            width: "100%",
          });

          $(".search-container").css({
            flex: "1",
            "min-width": "80px",
            "max-width": "100%",
            order: "1",
          });
          // $(".search-container").after(dateRangeHTML); // COMMENTED OUT: Date selection is available in filter popup

          $(".button-container").css({
            display: "flex",
            "align-items": "center",
            gap: "12px",
            order: "3",
            padding: "0 8px",
            margin: "0 4px",
          });

          $(".length-container").css({
            order: "4",
            padding: "0 8px",
            margin: "0 4px",
          });

          // Style search input
          $(".dataTables_filter input")
            .css({
              width: "100%",
              padding: "8.5px 12px",
              border: "1px solid white",
              "font-size": "14px",
              transition: "border-color 0.2s ease",
            })
            .attr("placeholder", "Search leads")
            .on("focus", function () {
              $(this).css({
                "border": "none !important",
                "outline": "none",
                "box-shadow": "none"
              });
            })
            .on("blur", function () {
              $(this).css({
                "border": "1px solid white",
                "outline": "none",
                "box-shadow": "none"
              });
            });

          // Remove default label text
          $(".dataTables_filter label")
            .contents()
            .filter(function () {
              return this.nodeType === 3;
            })
            .remove();

          // Style buttons
          $(".dt-button").css({
            background: "white",
            border: "1px solid #dadce0",
            "border-radius": "10px",
            padding: "8px 12px",
            "font-size": "14px",
            color: "#3c4043",
            cursor: "pointer",
            transition: "all 0.2s ease",
            display: "inline-flex",
            "align-items": "center",
            gap: "6px",
            margin: "0px",
            height: "36px",
            marginTop: "0px",
          });

          $(".dt-button:hover").css({
            background: "#e8eaed",
            "border-color": "#bdc1c6",
          });

          // Style length menu
          $(".dataTables_length label").css({
            display: "flex",
            "align-items": "center",
            gap: "8px",
            margin: "0",
            "font-size": "14px",
          });

          $(".dataTables_length select").css({
            border: "1px solid #dadce0",
            "border-radius": "10px",
            padding: "8px 0px",
            "font-size": "14px",
            height: "36px",
            marginTop: "0px",
          });
          document.querySelector(".loader-container").style.display = "none";

          // Add click handlers for calendar icons - COMMENTED OUT: Date selection is available in filter popup
          // $(document).off("click", "#dateFromIcon, #dateToIcon").on("click", "#dateFromIcon, #dateToIcon", function(e) {
          //   e.preventDefault();
          //   e.stopPropagation();
          //   const openDateRangePickerBtn = document.getElementById("openDateRangePicker");
          //   const datePickerModal = document.getElementById("datePickerModal");
          //   const modalDateFromInput = document.getElementById("modalDateFrom");
          //   const modalDateToInput = document.getElementById("modalDateTo");
          //   
          //   if (datePickerModal && modalDateFromInput && modalDateToInput) {
          //     // Get current values from all sources
          //     const currentFrom = $("#dateFrom").val() || $("#isolatedFilterStartDate").val();
          //     const currentTo = $("#dateTo").val() || $("#isolatedFilterEndDate").val();
          //     
          //     // Set modal values
          //     modalDateFromInput.value = currentFrom;
          //     modalDateToInput.value = currentTo;
          //     
          //     // Open modal
          //     datePickerModal.style.display = "flex";
          //   } else if (openDateRangePickerBtn) {
          //     // Fallback to button click if modal elements not found
          //     openDateRangePickerBtn.click();
          //   }
          // });

          // Hide the original calendar button - COMMENTED OUT: Date selection is available in filter popup
          // $("#openDateRangePicker").css("display", "none");

          handleResponsiveBehavior();

          // Handle responsive behavior for column visibility
          handleColumnVisibility();

          // Keep top badge counts synced with current filtered/search results
          // OPTIMIZED: Use a flag to prevent duplicate calls on initial load
          let isInitialDraw = true;
          let skipNextDrawUpdate = false; // Flag to skip tag update on specific draws

          // Expose function to skip next draw update (for date picker, etc.)
          window.skipNextTagCountUpdate = function () {
            skipNextDrawUpdate = true;
          };

          table.on("draw.dt", function () {
            // Skip tag count update on initial draw - will be called once after initialization
            if (isInitialDraw) {
              isInitialDraw = false;
              return;
            }

            // Skip if flagged (e.g., during date picker operations)
            if (skipNextDrawUpdate) {
              skipNextDrawUpdate = false;
              console.log('⏭️ Skipping tag count update from draw.dt (manual update will follow)');
              return;
            }

            // PERF: 50ms is enough — rows are already in the DOM when draw.dt fires.
            // Was 600ms which made counts feel sluggish after every filter/draw.
            setTimeout(() => {
              clearFilterStateCache();
              updateAllTagCounts();
            }, 50);
          });
          // OPTIMIZED: Single initial update after table initialization (removed duplicate)
          // This will be called once at line 1875 after table initialization
          // OPTIMIZED: Don't load users on page load - load lazily when modal opens
          // loadUsersIntoDropdown(); // Moved to lazy loading when modal opens

          $(document).on("click", ".reassign", function (e) {
            e.preventDefault();
            e.stopPropagation();

            const $btn = $(this);
            const rowId = $btn.data("id") || $btn.attr("data-id");
            if (!rowId) return;

            // Read row details from DOM — works for both regular and lazy-loaded rows
            const $tr = $btn.closest("tr").hasClass("details-row")
              ? $btn.closest("tr").prev("tr")
              : $btn.closest("tr");

            const leadName = $tr.find("h4").first().text().trim() || ("Lead #" + rowId);
            const project = $tr.find(".project-name").first().text().trim()
              || $tr.find(".project-name-mobile").first().text().replace(/^Project:\s*/i, "").trim();

            // Populate modal fields (same as original)
            $("#reassignRowId").val(rowId);
            $("#projectName").val(project || "");
            $("#reassignLeadName").text(leadName);

            // Load users lazily then open modal with fadeIn — exactly as original
            loadUsersIntoDropdown();
            $("#reassignModal").fadeIn();
          });

          $("#cancelReassign, #closeReassignModal").on("click", function () {
            $("#reassignModal").fadeOut();
          });


          // Select all checkbox functionality
          $("#selectAll").change(function () {
            $(".row-checkbox").prop("checked", $(this).prop("checked"));
            updateSelectionActions();
          });

          this.api().on('length.dt', function (e, settings, len) {
            if (len === -1) {
              // For "All" option, set page length to total filtered records
              settings._iDisplayLength = settings.fnRecordsDisplay();
            }
          });

          updateLengthMenuVisibility();

          // Individual checkbox functionality
          $(document).on("change", ".row-checkbox", function () {
            if (
              $(".row-checkbox:checked").length === $(".row-checkbox").length
            ) {
              $("#selectAll").prop("checked", true);
            } else {
              $("#selectAll").prop("checked", false);
            }
            updateSelectionActions();
          });

          // Initialize filter dropdown functionality
          initFilterDropdowns();
          updateCreatedAtSortButton();

          // Build unique-values cache in the background after initial load
          invalidateUniqueValuesCache();
          setTimeout(buildUniqueValuesCache, 2000); // PERF: deferred — never competes with main data request

          // Handle responsive behavior for column visibility
          handleColumnVisibility();

          //   fetchHistory(rowId, userUniqueId);
        },
      });

      // OPTIMIZED: Single call to update tag counts after table initialization
      // This is the ONLY place tag counts are updated on initial page load
      // All other updateAllTagCounts() calls are for user interactions (filters, search, etc.)
      setTimeout(() => {
        updateAllTagCounts();
      }, 500); // Reduced delay from 800ms to 500ms for faster initial load
    }

    // ============================================================================
    // URL PARAMETER AUTO-APPLY LOGIC FOR DASHBOARD POPUP
    // ============================================================================
    const urlParamsPopup = new URLSearchParams(window.location.search);
    const urlStatus = urlParamsPopup.get('status');
    const urlTeamView = urlParamsPopup.get('teamView');
    const urlFilterUser = urlParamsPopup.get('filterUser');
    const urlStartDate = urlParamsPopup.get('start_date');
    const urlEndDate = urlParamsPopup.get('end_date');
    const urlLeadName = urlParamsPopup.get('lead_name');
    const urlProjectName = urlParamsPopup.get('project_name');

    console.log('🌐 FULL URL:', window.location.href);
    console.log('📊 URL Parameters detected:', {
      status: urlStatus,
      teamView: urlTeamView,
      filterUser: urlFilterUser,
      startDate: urlStartDate,
      endDate: urlEndDate,
      leadName: urlLeadName,
      projectName: urlProjectName
    });

    // Check if any popup parameters exist
    const hasPopupParams = urlStatus || urlTeamView || urlFilterUser || urlStartDate || urlEndDate || urlLeadName || urlProjectName;
    console.log('🎯 Has popup URL parameters?', hasPopupParams);

    // CRITICAL FIX: Pre-populate window.multiFilters with URL parameters
    // This ensures tag counts work correctly from the start
    if (!window.multiFilters) {
      window.multiFilters = {};
    }

    // Pre-populate multiFilters with URL parameters
    if (urlStatus) {
      window.multiFilters.status = urlStatus;
      console.log('✅ Pre-populated multiFilters.status:', urlStatus);
    }

    if (urlTeamView === 'on' && urlFilterUser) {
      window.multiFilters.user = urlFilterUser;
      console.log('✅ Pre-populated multiFilters.user:', urlFilterUser);
    }

    if (urlStartDate && urlEndDate) {
      window.multiFilters.start_date = urlStartDate;
      window.multiFilters.end_date = urlEndDate;
      console.log('✅ Pre-populated multiFilters dates:', urlStartDate, 'to', urlEndDate);
    }

    if (urlLeadName) {
      window.multiFilters.name = urlLeadName;
      console.log('✅ Pre-populated multiFilters.name:', urlLeadName);
    }

    if (urlProjectName) {
      window.multiFilters.assign_project_name = urlProjectName;
      console.log('✅ Pre-populated multiFilters.assign_project_name:', urlProjectName);
    }

    console.log('📦 multiFilters after pre-population:', JSON.stringify(window.multiFilters));

    // Apply team view setting from URL
    if (urlTeamView === 'on' || urlTeamView === 'off') {
      const shouldBeChecked = urlTeamView === 'on';
      if (managerToggle) {
        managerToggle.checked = shouldBeChecked;
        updateMyLeadsLabel(shouldBeChecked);
        console.log('✅ Team view set to:', shouldBeChecked ? 'ON' : 'OFF');
      }
    }

    console.log('📦 multiFilters before initializeLeadsTable:', JSON.stringify(window.multiFilters));

    // Apply filters to UI inputs after table initialization
    setTimeout(() => {
      let filtersApplied = false;

      // Apply status filter - using multi-select tag system
      if (urlStatus) {
        const statusInput = document.getElementById('isolatedFilterStatus');
        if (statusInput) {
          // For multi-select inputs, we need to add as a tag
          if (typeof window.addMultiSelectTag === 'function') {
            window.addMultiSelectTag('isolatedFilterStatus', urlStatus);
            console.log('✅ Status filter tag added:', urlStatus);
          } else {
            // Fallback: set value directly
            statusInput.value = urlStatus;
            console.log('✅ Status filter input set (fallback):', urlStatus);
          }
          filtersApplied = true;
        }
      }

      // Apply user filter - using multi-select tag system (only if team view is ON and user is provided)
      if (urlTeamView === 'on' && urlFilterUser) {
        const userInput = document.getElementById('isolatedFilterAssigneduserName');
        if (userInput) {
          // For multi-select inputs, we need to add as a tag
          if (typeof window.addMultiSelectTag === 'function') {
            window.addMultiSelectTag('isolatedFilterAssigneduserName', urlFilterUser);
            console.log('✅ User filter tag added:', urlFilterUser);
          } else {
            // Fallback: set value directly
            userInput.value = urlFilterUser;
            console.log('✅ User filter input set (fallback):', urlFilterUser);
          }
          filtersApplied = true;
        }
      }

      // Apply lead name filter
      if (urlLeadName) {
        const leadNameInput = document.getElementById('isolatedFilterCustumername');
        if (leadNameInput) {
          if (typeof window.addMultiSelectTag === 'function') {
            window.addMultiSelectTag('isolatedFilterCustumername', urlLeadName);
            console.log('✅ Lead name filter tag added:', urlLeadName);
          } else {
            leadNameInput.value = urlLeadName;
            console.log('✅ Lead name filter input set (fallback):', urlLeadName);
          }
          filtersApplied = true;
        }
      }

      // Apply project filter
      if (urlProjectName) {
        const projectInput = document.getElementById('isolatedFilterAssignedProjectName');
        if (projectInput) {
          if (typeof window.addMultiSelectTag === 'function') {
            window.addMultiSelectTag('isolatedFilterAssignedProjectName', urlProjectName);
            console.log('✅ Project filter tag added:', urlProjectName);
          } else {
            projectInput.value = urlProjectName;
            console.log('✅ Project filter input set (fallback):', urlProjectName);
          }
          filtersApplied = true;
        }
      }

      // Apply date filters to inputs (dates are regular inputs, not multi-select)
      if (urlStartDate && urlEndDate) {
        const startDateInput = document.getElementById('isolatedFilterStartDate');
        const endDateInput = document.getElementById('isolatedFilterEndDate');
        if (startDateInput && endDateInput) {
          startDateInput.value = urlStartDate;
          endDateInput.value = urlEndDate;
          console.log('✅ Date range inputs set to:', urlStartDate, 'to', urlEndDate);
          filtersApplied = true;
        }
      }

      // Apply all filters by clicking the apply button
      if (filtersApplied) {
        const applyBtn = document.getElementById('isolatedApplyFiltersBtn');
        if (applyBtn) {
          console.log('🔄 Clicking apply filters button...');
          console.log('📦 multiFilters RIGHT BEFORE apply button click:', JSON.stringify(window.multiFilters));
          // Clear filter cache before applying
          clearFilterStateCache();
          applyBtn.click();
          console.log('📦 multiFilters RIGHT AFTER apply button click:', JSON.stringify(window.multiFilters));
        }
      }
    }, 800); // Increased to 800ms to ensure multi-select system is ready

    console.log('📦 multiFilters after setTimeout registered (before initializeLeadsTable):', JSON.stringify(window.multiFilters));

    initializeLeadsTable(); // ðŸ"„ Call it here

    // ============================================================================
    // OPTIMIZED TOGGLE SWITCH HANDLER - INSTANT LOADING
    // ============================================================================
    managerToggle.addEventListener("change", function (e) {
      const normalizeUser = (v) => String(v || '').trim().toLowerCase();
      const currentSelfTokens = [
        typeof window.currentUserTableName !== 'undefined' ? window.currentUserTableName : '',
        typeof window.currentUserName !== 'undefined' ? window.currentUserName : '',
        typeof window.currentUserDisplayName !== 'undefined' ? window.currentUserDisplayName : ''
      ].map(normalizeUser).filter(Boolean);
      const urlParamsLocal = new URLSearchParams(window.location.search);
      const urlFilterUser = normalizeUser(urlParamsLocal.get('filterUser'));
      const urlManagerView = urlParamsLocal.get('managerView');
      const isSelfByUrl = urlFilterUser && currentSelfTokens.includes(urlFilterUser);
      const forceLock = managerToggle.dataset.selfLock === '1' || isSelfByUrl || (urlManagerView === 'true' && isSelfByUrl);
      if (forceLock) {
        managerToggle.checked = false;
        managerToggle.dataset.selfLock = '1';
        managerToggle.disabled = true;
        e.stopImmediatePropagation();
        e.preventDefault();
        return;
      }
      const isChecked = this.checked;

      // OPTIMIZED: Clear filter state cache immediately
      clearFilterStateCache();

      // OPTIMIZED: Update label instantly
      updateMyLeadsLabel(isChecked);

      console.log("Manager toggle changed to:", isChecked ? "ON" : "OFF");

      if (isChecked) {
        // Manager view ON â†’ clear filters
        window.multiFilters = {};
        window.headerDropdownFilters = {};
        $("#dateFrom, #dateTo, #isolatedFilterStartDate, #isolatedFilterEndDate").val("");
        $('.filter-option').prop('checked', false);
        $('.filter-option[value=""]').prop('checked', true);
        $('.filter-header').removeClass('active-filter');
        $(".filter-btn").removeClass("active");
        window.currentActiveFilter = "";
        console.log("ðŸ”„ Team view enabled - all filters cleared");
      } else {
        // Manager view OFF â†’ reset to "My Leads"
        window.multiFilters = {};
        window.headerDropdownFilters = {};
        $("#dateFrom, #dateTo, #isolatedFilterStartDate, #isolatedFilterEndDate").val("");
        $('.filter-option').prop('checked', false);
        $('.filter-option[value=""]').prop('checked', true);
        $('.filter-header').removeClass('active-filter');
        $(".filter-btn").removeClass("active");
        window.currentActiveFilter = "myLeads";
        $('.tab-row .filter-btn').first().addClass('active');
        console.log("ðŸ”„ Individual view enabled - My Leads filter applied");
      }

      // Clear session storage
      sessionStorage.removeItem('lead_filter_start_date');
      sessionStorage.removeItem('lead_filter_end_date');

      // OPTIMIZED: Invalidate cache immediately
      invalidateUniqueValuesCache();

      // OPTIMIZED: Just reload data instead of destroying/recreating table (MUCH FASTER)
      if ($.fn.DataTable.isDataTable("#leadsTable")) {
        showLeadsLoader();

        // OPTIMIZED: Reload data instantly without destroying table
        table.ajax.reload(function (json) {
          hideLeadsLoader();
          // OPTIMIZED: Update tag counts instantly after data loads
          updateAllTagCounts();

          // OPTIMIZED: Rebuild cache in background (non-blocking)
          setTimeout(buildUniqueValuesCache, 2000); // PERF: deferred after toggle change
        }, false); // false = don't reset pagination

        // OPTIMIZED: Re-populate dropdowns in background (non-blocking)
        setTimeout(() => {
          $(".filter-header").each(function () {
            populateFilterDropdown($(this)).catch(err => {
              console.error('Error populating filter dropdown:', err);
            });
          });
        }, 100);
      }
    });


    // Add this function to show/hide "All" option based on filters
    // Add this function to show/hide "All" option based on filters
    function updateLengthMenuVisibility() {
      const lengthMenu = $('.dataTables_length select');
      const allOption = lengthMenu.find('option[value="-1"]');

      // Check if any filters are active (excluding "My Leads" filter)
      const hasFilters = (
        window.multiFilters && Object.keys(window.multiFilters).length > 0 ||
        window.currentActiveFilter && window.currentActiveFilter !== "" && window.currentActiveFilter !== "myLeads" ||
        $("#dateFrom").val() || $("#dateTo").val() ||
        table.search() !== ""
      );

      if (hasFilters) {
        allOption.show();
      } else {
        allOption.hide();
        // If "All" was selected but no filters, revert to default
        if (table.page.len() === -1) {
          table.page.len(10).draw();
        }
      }
    }

    // Add these event listeners to update the menu when filters change
    $(document).on('draw.dt', function () {
      updateLengthMenuVisibility();
      // Ensure cache exists for current context
      if (!window.__leadsUniqueCache.loaded && !window.__leadsUniqueCache.loading) {
        setTimeout(buildUniqueValuesCache, 2000);
      }
      // Don't call getAllCountData here - it overwrites the filtered count set in dataSrc
      // The active button count is updated in dataSrc to match filtered results
    });



    $(".dataTables_filter input").on("keyup", function () {
      setTimeout(updateLengthMenuVisibility, 100);
    });


    function ensureFilterDropdownsWork() {
      // Remove existing handlers to prevent duplicates
      $(document).off('click', '.filter-header-btn');

      // Add fresh handlers for filter header buttons
      $(document).on('click', '.filter-header-btn', function (e) {
        e.stopPropagation();
        e.preventDefault();

        const $header = $(this).closest('.filter-header');
        const $dropdown = $header.find('.filter-dropdown');

        // Populate dropdown dynamically before showing (async)
        populateFilterDropdown($header).then(() => {
          // Hide all other dropdowns
          $('.filter-dropdown').not($dropdown).hide();

          // Toggle current dropdown after population
          $dropdown.toggle();
        }).catch(err => {
          console.error('Error populating dropdown:', err);
          // Still show dropdown even if population fails
          $('.filter-dropdown').not($dropdown).hide();
          $dropdown.toggle();
        });
      });

      // Close dropdowns when clicking elsewhere
      $(document).on('click', function (e) {
        if (!$(e.target).closest('.filter-header').length) {
          $('.filter-dropdown').hide();
          window.activeDropdownHeader = null; // Clear active dropdown tracking
        }
      });
    }



    let currentFilter = ""; // Tracks current active status filter
    let isStatusDropdownOpen = false;
    // === Global unique-values cache for header dropdowns ===
    // Caches unique values across the entire server-side dataset for the current filter context
    window.__leadsUniqueCache = {
      key: "", // identifies current filter context
      loading: false,
      loaded: false,
      values: {} // { [columnIndex: number]: string[] }
    };

    // Track active header dropdown selections to enable cascading dropdowns
    // Shape: { [filterKey: string]: 'val1,val2,...' }
    window.headerDropdownFilters = window.headerDropdownFilters || {};

    // Track which dropdown is currently being used by the user
    window.activeDropdownHeader = null;

    // Map table column index -> server field name for background fetch
    const serverFieldByColumnIndex = {
      1: "name", // Lead (name inside lead info)
      2: "assign_project_name",
      3: "id",
      4: "created_at",
      5: "email",
      6: "location_status",
      7: "budget",
      8: "user_remarks",
      9: "updated_at",
      10: "user_unique_id",
      11: "source_of_lead",
    };

    function getCurrentDatasetKey() {
      const dateFrom = $("#dateFrom").val() || "";
      const dateTo = $("#dateTo").val() || "";
      const toggle = managerToggle && managerToggle.checked ? "1" : "0";
      const mf = window.multiFilters ? JSON.stringify(window.multiFilters) : "";
      const active = (typeof window.currentActiveFilter !== "undefined" && window.currentActiveFilter)
        ? window.currentActiveFilter
        : "";
      const hfObj = window.headerDropdownFilters || {};
      // Stable stringify header filters (sort keys for stability)
      const hf = JSON.stringify(Object.keys(hfObj).sort().reduce((acc, k) => { acc[k] = hfObj[k]; return acc; }, {}));
      // Note: We intentionally ignore global search in the key, so dropdowns list all values for the current filtered dataset
      return `tgl:${toggle}|from:${dateFrom}|to:${dateTo}|filter:${active}|mf:${mf}|hf:${hf}`;
    }

    async function buildUniqueValuesCache() {
      const cacheKey = getCurrentDatasetKey();
      // If cache for this context is already built or in progress, skip
      if (window.__leadsUniqueCache.loading) return;
      if (window.__leadsUniqueCache.loaded && window.__leadsUniqueCache.key === cacheKey) return;

      window.__leadsUniqueCache.loading = true;
      window.__leadsUniqueCache.loaded = false;
      window.__leadsUniqueCache.key = cacheKey;
      window.__leadsUniqueCache.values = {};

      // Initialize sets for all tracked columns
      const sets = {};
      Object.keys(serverFieldByColumnIndex).forEach(k => { sets[k] = new Set(); });

      try {
        // We’ll page through the same endpoint the table uses
        const rowsPerPage = 500; // reasonable chunk
        let page = 1;
        let totalRows = Infinity;

        while ((page - 1) * rowsPerPage < totalRows) {
          const params = new URLSearchParams();
          // Replicate DataTables' custom params
          const df = $("#dateFrom").val();
          const dt = $("#dateTo").val();
          if (window.multiFilters && window.multiFilters.start_date) {
            params.set("dateFrom", window.multiFilters.start_date);
          } else if (df) {
            params.set("dateFrom", df);
          }
          if (window.multiFilters && window.multiFilters.end_date) {
            params.set("dateTo", window.multiFilters.end_date);
          } else if (dt) {
            params.set("dateTo", dt);
          }

          // Manager toggle
          params.set("managerToggle", managerToggle && managerToggle.checked ? "1" : "0");

          // Current active status filter (My Leads / Booked / etc.)
          if (typeof window.currentActiveFilter !== "undefined" && window.currentActiveFilter && window.currentActiveFilter !== "my") {
            params.set("filter", window.currentActiveFilter);
          } else {
            params.set("filter", "");
          }

          // Multi-filters + header dropdown filters (cascading)
          const combinedFilters = {};
          if (window.multiFilters && Object.keys(window.multiFilters).length > 0) {
            Object.assign(combinedFilters, window.multiFilters);
          }
          if (window.headerDropdownFilters && Object.keys(window.headerDropdownFilters).length > 0) {
            Object.assign(combinedFilters, window.headerDropdownFilters);
          }
          if (Object.keys(combinedFilters).length > 0) {
            params.set("multiFilters", JSON.stringify(combinedFilters));
          } else {
            params.set("multiFilters", "");
          }

          // Server-side pagination inputs expected by backend
          params.set("page", String(page));
          params.set("rowsPerPage", String(rowsPerPage));
          // Keep ordering consistent with table
          params.set("order_by", "created_at");
          params.set("order_dir", "desc");

          const url = `update_status.php?${params.toString()}`;
          // eslint-disable-next-line no-await-in-loop
          const resp = await fetch(url, { method: "GET" });
          if (!resp.ok) break;
          // eslint-disable-next-line no-await-in-loop
          const json = await resp.json();
          if (!json || !Array.isArray(json.data)) break;

          // totalRows from server determines when to stop
          if (typeof json.totalRows === "number") {
            totalRows = json.totalRows;
          } else {
            // Fallback: stop if we get fewer than asked rows
            totalRows = Math.max(totalRows, page * rowsPerPage);
          }

          // Accumulate values for every configured column
          for (const row of json.data) {
            for (const [colIdxStr, serverField] of Object.entries(serverFieldByColumnIndex)) {
              const colIdx = Number(colIdxStr);
              let val = row[serverField];
              if (val === undefined || val === null) continue;
              // Normalize to string and trim
              val = String(val).replace(/<[^>]*>/g, "").trim();
              if (!val) continue;
              sets[colIdx].add(val);
            }
          }

          // Stop if this page returned no rows
          if (json.data.length === 0) break;

          page += 1;
        }

        // Convert to sorted arrays
        const values = {};
        Object.keys(sets).forEach(k => {
          values[k] = Array.from(sets[k]).sort((a, b) => a.localeCompare(b, undefined, { sensitivity: "base" }));
        });
        window.__leadsUniqueCache.values = values;
        window.__leadsUniqueCache.loaded = true;
        // Notify listeners that cache is ready
        try { document.dispatchEvent(new CustomEvent('uniqueCacheLoaded')); } catch (_) { }
      } catch (e) {
        // In case of error, mark as not loaded so we fallback to current page values
        console.warn("Unique-values cache build failed:", e);
        window.__leadsUniqueCache.loaded = false;
      } finally {
        window.__leadsUniqueCache.loading = false;
      }
    }

    function invalidateUniqueValuesCache() {
      window.__leadsUniqueCache.key = "";
      window.__leadsUniqueCache.loaded = false;
      window.__leadsUniqueCache.loading = false;
      window.__leadsUniqueCache.values = {};
    }

    // Status dropdown counts cache
    let statusCountsCache = {
      key: "",
      timestamp: 0,
      counts: null,
    };

    function normalizeStatusKey(statusValue) {
      return String(statusValue || "").trim().toLowerCase();
    }

    function setStatusOptionCount(optionEl, count) {
      const $option = $(optionEl);
      const $badge = $option.find(".status-badge");
      if (!$badge.length) return;

      const existingBase = $badge.attr("data-base-label");
      const computedBase = existingBase || $badge.text().replace(/\s*\(\d+\)\s*$/, "").trim();
      $badge.attr("data-base-label", computedBase);
      $badge.text(`${computedBase} (${Number(count) || 0})`);
    }

    async function updateStatusDropdownCounts(forceRefresh = false) {
      if (!table || !$.fn.DataTable.isDataTable('#leadsTable')) return;

      const managerToggleEl = document.getElementById("managerToggle");
      const toggleState = managerToggleEl && managerToggleEl.dataset.selfLock === '1'
        ? 0
        : (managerToggleEl && managerToggleEl.checked ? 1 : 0);

      const urlParams = new URLSearchParams(window.location.search);
      const filterUserParam = urlParams.get("filterUser");

      // Build filters context but exclude status itself to show all status counts.
      const combinedFilters = {
        ...(window.multiFilters || {}),
        ...(window.headerDropdownFilters || {})
      };
      delete combinedFilters.status;

      const queryParams = new URLSearchParams({
        page: '1',
        rowsPerPage: '1000000',
        searchQuery: table.search() || '',
        managerToggle: toggleState ? '1' : '0',
        filter: window.currentActiveFilter || '',
        multiFilters: JSON.stringify(combinedFilters)
      });

      if (managerToggleEl && managerToggleEl.dataset.selfLock !== '1' && managerToggleEl.checked) {
        queryParams.set('managerView', 'true');
      } else {
        queryParams.set('managerView', 'false');
      }

      if (filterUserParam) queryParams.set('filterUser', filterUserParam);

      const cacheKey = queryParams.toString();
      const now = Date.now();
      const cacheValidForMs = 15000;

      let countsByStatus = null;

      if (!forceRefresh && statusCountsCache.counts && statusCountsCache.key === cacheKey && (now - statusCountsCache.timestamp) < cacheValidForMs) {
        countsByStatus = statusCountsCache.counts;
      } else {
        try {
          const response = await fetch(`update_status.php?${cacheKey}`);
          const data = await response.json();
          const rows = Array.isArray(data?.data) ? data.data : (Array.isArray(data) ? data : []);

          countsByStatus = { __all: rows.length };
          rows.forEach((row) => {
            const statusKey = normalizeStatusKey(row.user_status || row.status);
            if (!statusKey) return;
            countsByStatus[statusKey] = (countsByStatus[statusKey] || 0) + 1;
          });

          statusCountsCache = {
            key: cacheKey,
            timestamp: now,
            counts: countsByStatus,
          };
        } catch (error) {
          console.error("Error updating status dropdown counts:", error);
          return;
        }
      }

      $(".status-option").each(function () {
        const rawStatus = ($(this).data("status") || "").toString().trim();
        const isAll = rawStatus === "" || rawStatus.toLowerCase() === "all";
        const count = isAll
          ? (countsByStatus.__all || 0)
          : (countsByStatus[normalizeStatusKey(rawStatus)] || 0);
        setStatusOptionCount(this, count);
      });
    }

    function cleanTableControls() {
      const container = $("#tableControlsContainer");
      container.empty();

      // Also remove any datepicker modal if exists
      $(".date-picker-modal").remove();
    }

    // Open/close the dropdown when clicking "Status" button
    $("#filterStatus").on("click", function (e) {
      e.stopPropagation();
      const $statusDropdown = $(".status-filter-dropdown");

      // Keep only one top-filter dropdown open at a time
      $(".tag-filter-container").removeClass("active");
      $(".tag-filter-dropdown").hide();

      $statusDropdown.toggle();
      isStatusDropdownOpen = !isStatusDropdownOpen;

      if (isStatusDropdownOpen) {
        window.__iosDropdownPortal.open($statusDropdown[0], this);
        updateStatusDropdownCounts();
      } else {
        window.__iosDropdownPortal.close($statusDropdown[0]);
      }
    });

    // Close dropdown when clicking outside
    $(document).on("click", function () {
      if (isStatusDropdownOpen) {
        const $statusDropdown = $(".status-filter-dropdown");
        $statusDropdown.hide();
        window.__iosDropdownPortal.close($statusDropdown[0]);
        isStatusDropdownOpen = false;
      }
    });

    // Filter the status options when typing in search input
    $(".status-search-input").on("keyup", function () {
      const searchTerm = $(this).val().toLowerCase();
      $(".status-option").each(function () {
        const statusText = $(this).text().toLowerCase();
        $(this).toggle(statusText.includes(searchTerm));
      });
    });

    // When the unique cache loads (after any filter change), refresh any visible header dropdowns
    // But skip the dropdown that the user is currently interacting with
    document.addEventListener('uniqueCacheLoaded', function () {
      $('.filter-dropdown:visible').each(function () {
        const $hdr = $(this).closest('.filter-header');
        // Skip repopulating if this is the currently active dropdown or has checked items
        const isActiveDropdown = window.activeDropdownHeader && $hdr[0] === window.activeDropdownHeader;
        const hasCheckedItems = $hdr.find('.filter-option:checked[value!=""]').length > 0;
        if (!isActiveDropdown && !hasCheckedItems) {
          populateFilterDropdown($hdr).catch(err => {
            console.error('Error populating filter dropdown:', err);
          });
        }
      });
    });

    // Helper function to map column index to filter key
    function getFilterKeyByColumnIndex(columnIndex) {
      // Map DataTable column index to backend multiFilters key
      // Aligns with serverFieldByColumnIndex and fetchMultiFilteredLeads keys
      const columnMap = {
        1: "name",                    // LEAD
        2: "assign_project_name",      // ASSIGNED PROJECT
        3: "id",                       // ID - now fully supported
        4: "created_at",               // CREATED AT - now fully supported (exact match)
        5: "email",                    // EMAIL
        6: "location",                 // LOCATION (location_status)
        7: "budget",                   // BUDGET
        8: "remarks",                  // REMARKS
        9: "updated_at",               // UPDATED AT
        10: "user",                    // ASSIGNED LEAD (user_unique_id)
        11: "source_of_lead"           // LEAD SOURCE
      };
      return columnMap[columnIndex] || "";
    }


    // Handle status button click (inside dropdown)
    $(".status-option").on("click", function (e) {
      e.preventDefault();
      const rawStatus = ($(this).data("status") || "").toString().trim();
      const isAllOption = rawStatus === "" || rawStatus.toLowerCase() === "all";
      const selectedStatus = isAllOption ? "" : rawStatus;

      currentFilter = selectedStatus;

      if (!window.multiFilters) {
        window.multiFilters = {};
      }

      if (isAllOption) {
        delete window.multiFilters.status;
      } else {
        window.multiFilters.status = selectedStatus;
      }

      console.log("[DEBUG] Filter Status:", currentFilter);

      $(".status-option").removeClass("active");
      $(this).addClass("active");

      $(".status-filter-dropdown").hide(); // Close the dropdown
      window.__iosDropdownPortal.close($(".status-filter-dropdown")[0]);
      isStatusDropdownOpen = false;

      // Reload the DataTable with new status filter
      if (typeof table !== "undefined") {
        table.draw();

        scheduleCountRefresh();
        updateStatusDropdownCounts(true);

        setTimeout(updateFilterCounter, 200);

        // Status context changed: refresh cache
        invalidateUniqueValuesCache();
        setTimeout(buildUniqueValuesCache, 2000) // PERF: deferred;
      }
      setTimeout(() => {
        if (window.clearFiltersManager) {
          window.clearFiltersManager.updateVisibility();
        }
      }, 300);
    });

    // Keep dropdown counts fresh when table data changes by search/filter/page changes.
    $(document).on('draw.dt', '#leadsTable', function () {
      updateStatusDropdownCounts();
    });

    $("#isolatedApplyFiltersBtn").on("click", function () {
      console.log('🎯 isolatedApplyFiltersBtn clicked! multiFilters BEFORE reading inputs:', JSON.stringify(window.multiFilters));

      const filters = {};

      // Get values from multi-select (tags) or single input
      function getFilterValue(inputId, fieldKey) {
        const values = window.multiSelectValues && window.multiSelectValues[inputId] ? window.multiSelectValues[inputId] : [];
        const singleValue = $("#" + inputId).val().trim();

        // Preserve commas inside project names by returning arrays for that field
        if (values.length > 0) {
          return fieldKey === 'assign_project_name' ? [...values] : values.join(',');
        }

        if (singleValue) {
          return fieldKey === 'assign_project_name' ? [singleValue] : singleValue;
        }
        return null;
      }

      const nameValue = getFilterValue('isolatedFilterCustumername', 'name');
      if (nameValue) filters.name = nameValue;

      const emailValue = getFilterValue('isolatedFilterEmail', 'email');
      if (emailValue) filters.email = emailValue;

      const numberValue = getFilterValue('isolatedFilterContactnumber', 'number');
      if (numberValue) filters.number = numberValue;

      const locationValue = getFilterValue('isolatedFilterLocation', 'location');
      if (locationValue) filters.location = locationValue;

      const sourceValue = getFilterValue('isolatedFilterSourceOfLead', 'source_of_lead');
      if (sourceValue) filters.source_of_lead = sourceValue;

      const statusValue = getFilterValue('isolatedFilterStatus', 'status');
      console.log('📋 Status input value:', statusValue);
      console.log('📋 Existing multiFilters.status:', window.multiFilters?.status);
      if (statusValue) {
        filters.status = statusValue;
        console.log('✅ Using status from input:', statusValue);
      } else if (window.multiFilters && window.multiFilters.status) {
        // Preserve status from URL params if input is empty
        filters.status = window.multiFilters.status;
        console.log('✅ Preserved status from multiFilters:', filters.status);
      } else {
        console.log('❌ No status to apply - input empty and multiFilters.status empty');
      }

      const projectValue = getFilterValue('isolatedFilterAssignedProjectName', 'assign_project_name');
      if (projectValue) filters.assign_project_name = projectValue;

      const identityValue = getFilterValue('isolatedFilterAssignedIdentity', 'lead_identity');
      if (identityValue) filters.lead_identity = identityValue;

      const userValue = getFilterValue('isolatedFilterAssigneduserName', 'user');
      console.log('👤 User input value:', userValue);
      console.log('👤 Existing multiFilters.user:', window.multiFilters?.user);
      if (userValue) {
        filters.user = userValue;
        console.log('✅ Using user from input:', userValue);
      } else if (window.multiFilters && window.multiFilters.user) {
        // Preserve existing user filter from URL params if input is empty
        // This ensures the user filter set from dashboard is not lost
        filters.user = window.multiFilters.user;
        console.log('✅ Preserved user from multiFilters:', filters.user);
      } else {
        console.log('📝 No user filter to apply');
      }

      const budgetValue = getFilterValue('isolatedFilterBudget', 'budget');
      if (budgetValue) filters.budget = budgetValue;

      const startDate = $("#isolatedFilterStartDate").val().trim();
      const endDate = $("#isolatedFilterEndDate").val().trim();
      setTimeout(() => {
        if (window.clearFiltersManager) {
          window.clearFiltersManager.updateVisibility();
        }
      }, 300);

      if (startDate) {
        filters.start_date = startDate;
      } else if (window.multiFilters && window.multiFilters.start_date) {
        // Preserve start_date from URL params if input is empty
        filters.start_date = window.multiFilters.start_date;
      }

      if (endDate) {
        filters.end_date = endDate;
      } else if (window.multiFilters && window.multiFilters.end_date) {
        // Preserve end_date from URL params if input is empty
        filters.end_date = window.multiFilters.end_date;
      }

      console.log('🔄 Isolated Apply - Rebuilding filters');
      console.log('   multiFilters BEFORE replacement:', JSON.stringify(window.multiFilters));
      console.log('   NEW filters object built from inputs:', JSON.stringify(filters));

      // ❌… Store globally - THIS REPLACES THE ENTIRE OBJECT
      window.multiFilters = filters;
      console.log('   multiFilters AFTER replacement:', JSON.stringify(window.multiFilters));
      // If the user filter targets the logged-in user/username, force manager toggle off
      enforceSelfLockFromFilters();

      console.log("✅ Filters applied:", JSON.stringify(window.multiFilters));

      // Close modal
      $('#filterModal').hide();
      $('body').css('overflow', 'auto');

      // PERF: Clear stale filter-state cache so the count fetch immediately below
      // picks up the freshly-set window.multiFilters, then fire it BEFORE table.draw()
      // so both requests race in parallel rather than counts waiting for the full draw.
      clearFilterStateCache();
      updateAllTagCounts();

      // ❌… Refresh DataTable
      table.draw();

      // Filters applied: rebuild cache for new context
      invalidateUniqueValuesCache();
      setTimeout(buildUniqueValuesCache, 2000) // PERF: deferred;
    });

    // ❌… Once only: attach draw event listener - INSTANT UPDATE
    $(document).on("draw.dt", function () {
      clearFilterStateCache(); // Clear cache when table redraws
      updateFilterCounter();
      updateLengthMenuVisibility();
      console.log("❌… Filters re-applied after DataTable draw");
      // Update ALL tag counts instantly using new system
      updateAllTagCounts();
    });


    $("#isolatedClearFiltersBtn").on("click", function () {
      console.log("Resetting filters...");

      // Clear all modal input values
      $("#dateFrom").val("");
      $("#dateTo").val("");

      // Clear single inputs
      $("#isolatedFilterCustumername").val("");
      $("#isolatedFilterEmail").val("");
      $("#isolatedFilterContactnumber").val("");
      $("#isolatedFilterLocation").val("");
      $("#isolatedFilterSourceOfLead").val("");
      $("#isolatedFilterStatus").val("");
      $("#isolatedFilterAssignedProjectName").val("");
      $("#isolatedFilterAssigneduserName").val("");
      $("#isolatedFilterAssignedIdentity").val("");
      $("#isolatedFilterBudget").val("");
      $("#isolatedFilterStartDate").val("");
      $("#isolatedFilterEndDate").val("");

      // Clear all multi-select tags (including budget)
      if (window.multiSelectValues) {
        Object.keys(window.multiSelectValues).forEach(inputId => {
          window.multiSelectValues[inputId] = [];
          const $container = $(`#${inputId}`).closest('.multi-select-container');
          if ($container.length) {
            $container.find('.multi-select-tags').empty();
          }
        });
      }

      // Clear all multi-select tags
      if (window.multiSelectValues) {
        Object.keys(window.multiSelectValues).forEach(inputId => {
          window.multiSelectValues[inputId] = [];
          const $container = $(`#${inputId}`).closest('.multi-select-container');
          if ($container.length) {
            $container.find('.multi-select-tags').empty();
          }
        });
      }

      // Clear all checkbox filters and check "All" option
      $(".filter-option").prop("checked", false);
      $('.filter-option[value=""]').prop("checked", true);

      setTimeout(() => {
        if (window.clearFiltersManager) {
          window.clearFiltersManager.updateVisibility();
        }
      }, 300);

      // Clear the multiFilters object
      window.multiFilters = {};

      // Clear header dropdown filters (column filters)
      window.headerDropdownFilters = {};

      // Reset persistent per-column selection store
      window.__colSelections = {};

      // Reset all column dropdown checkboxes to "All"
      $('.filter-header').each(function () {
        const $header = $(this);
        const $dropdown = $header.find('.filter-dropdown, .filter-options');
        const $allOption = $dropdown.find('.filter-option[value=""]');
        if ($allOption.length) {
          $dropdown.find('.filter-option').prop('checked', false);
          $allOption.prop('checked', true);
        }
      });

      // Clear all column searches in DataTable
      if (table && $.fn.DataTable.isDataTable("#leadsTable")) {
        table.columns().search(''); // Clear all column searches
        table.draw(); // Redraw immediately to apply cleared filters
      }

      // Close the filter modal
      $("#filterModal").hide();
      $("body").css("overflow", "auto");
      scheduleCountRefresh();
      setTimeout(updateLengthMenuVisibility, 100);
      // Filters cleared: refresh cache
      invalidateUniqueValuesCache();
      setTimeout(buildUniqueValuesCache, 2000) // PERF: deferred;
    });

    $('#filterStatusSelect').on('change', function () {
      setTimeout(updateLengthMenuVisibility, 100);
    });

    // Column filter changes already handled below with cascading logic; keep length menu synced
    $(document).on("change", ".filter-option", function () {
      setTimeout(updateLengthMenuVisibility, 100);
    });


    let filterCounterTimeout; // keep track of auto-hide timeout
    let dateChangeTimeout;

    function showFilterCounter(count) {
      const popup = document.getElementById('filterCounterPopup');
      const countElement = document.getElementById('filteredLeadsCount');

      if (!popup || !countElement) {
        console.error('Filter counter elements not found');
        return;
      }

      countElement.textContent = count.toLocaleString();
      popup.style.display = 'flex';
      popup.classList.remove('hiding');

      // Clear any existing timeout so it doesn't hide too early
      if (filterCounterTimeout) {
        clearTimeout(filterCounterTimeout);
      }

      // Restart auto-hide timer
      filterCounterTimeout = setTimeout(() => {
        hideFilterCounter();
      }, 4000);
    }

    // Function to hide the filter counter
    function hideFilterCounter() {
      const popup = document.getElementById('filterCounterPopup');
      if (!popup) return;

      popup.classList.add('hiding');
      setTimeout(() => {
        popup.style.display = 'none';
      }, 300);
    }

    document.getElementById('closeFilterCounter').addEventListener('click', hideFilterCounter);

    function updateFilterCounter() {
      if (table && $.fn.DataTable.isDataTable("#leadsTable")) {
        const pageInfo = table.page.info();
        const filteredCount = pageInfo.recordsFiltered || pageInfo.recordsDisplay;
        showFilterCounter(filteredCount);
      }
    }

    let countRefreshPending = false;

    // ============================================================================
    // SECTION 2: TAG COUNT SYSTEM (OPTIMIZED FOR 1L+ RECORDS)
    // ============================================================================
    // This section handles all tag count updates with:
    // - Individual functions for each tag (11 total)
    // - Request cancellation to prevent stale updates
    // - Filter state caching for performance
    // - No flickering - preserves old values until new ones arrive
    // ============================================================================

    // Cache filter state to avoid recalculating on every request
    let cachedFilterState = null;
    let filterStateCacheTime = 0;
    const FILTER_STATE_CACHE_TTL = 50; // Cache for 50ms to batch rapid requests

    /**
     * Get current filter state for count requests - OPTIMIZED with caching
     */
    function getCurrentFilterState() {
      // Return cached state if still valid (for rapid successive calls)
      const now = Date.now();
      if (cachedFilterState && (now - filterStateCacheTime) < FILTER_STATE_CACHE_TTL) {
        return cachedFilterState;
      }
      const managerToggleEl = document.getElementById("managerToggle");
      const toggleState = (managerToggleEl && managerToggleEl.dataset.selfLock === '1') ? 0 : (managerToggleEl && managerToggleEl.checked ? 1 : 0);
      const urlParams = new URLSearchParams(window.location.search);
      const filterUserParam = urlParams.get("filterUser");
      const managerViewParam = urlParams.get("managerView");

      // Get global search
      let globalSearch = '';
      let columnsSearch = [];
      const hasTable = (typeof table !== 'undefined') && table && $.fn.DataTable.isDataTable("#leadsTable");

      if (hasTable && typeof table.search === 'function') {
        globalSearch = table.search().trim() || '';

        // Also read directly from DOM input - this is immediately current
        // the instant user types, before DataTable commits internal state.
        // Overrides table.search() if DOM has a value (handles timing gap).
        const _domInput = document.querySelector('.dataTables_filter input');
        if (_domInput && _domInput.value.trim()) {
          globalSearch = _domInput.value.trim();
        }

        // Get column-specific searches
        if (typeof table.columns === 'function') {
          try {
            table.columns().every(function () {
              const colSearch = this.search();
              if (colSearch && typeof colSearch === 'string' && colSearch.trim() !== '') {
                columnsSearch.push({ index: this.index(), value: colSearch });
              }
            });
          } catch (e) {
            console.warn('Column search read failed', e);
          }
        }
      }

      // Get date filters
      let dateFrom = $("#dateFrom").val() || '';
      let dateTo = $("#dateTo").val() || '';
      if (window.multiFilters) {
        const mf = window.multiFilters;
        if (mf.start_date || mf.startDate) dateFrom = mf.start_date || mf.startDate;
        if (mf.end_date || mf.endDate) dateTo = mf.end_date || mf.endDate;
      }

      // Combine multiFilters with headerDropdownFilters
      const combinedFilters = { ...(window.multiFilters || {}) };
      if (window.headerDropdownFilters && Object.keys(window.headerDropdownFilters).length > 0) {
        Object.assign(combinedFilters, window.headerDropdownFilters);
      }

      // Build query params — include the active filter button so badge counts
      // reflect only the filtered subset (e.g. Booked, New, Ads, NFS-D)
      const queryParams = new URLSearchParams({
        toggle_enabled: toggleState ? '1' : '0',
        dateFrom: dateFrom,
        dateTo: dateTo,
        multiFilters: JSON.stringify(combinedFilters),
        searchQuery: globalSearch || '',
        filter: (typeof window.currentActiveFilter !== 'undefined' && window.currentActiveFilter) ? window.currentActiveFilter : ''
      });

      if (managerToggleEl && managerToggleEl.dataset.selfLock !== '1' && managerToggleEl.checked) {
        queryParams.set('managerView', 'true');
      } else {
        queryParams.set('managerView', 'false');
      }

      if (filterUserParam) queryParams.set('filterUser', filterUserParam);

      // Add column searches
      columnsSearch.forEach(({ index, value }) => {
        queryParams.append(`columns[${index}][search][value]`, value);
      });

      const result = queryParams.toString();

      // Cache the result
      cachedFilterState = result;
      filterStateCacheTime = Date.now();

      return result;
    }

    /**
     * Clear filter state cache - call when filters change
     */
    function clearFilterStateCache() {
      cachedFilterState = null;
      filterStateCacheTime = 0;
    }

    // ============================================================================
    // SUPER OPTIMIZED TAG COUNT SYSTEM - SINGLE API CALL FOR ALL COUNTS
    // Replaces 11 individual API calls with just 1 for maximum performance
    // ============================================================================

    // Request cancellation/coalescing for the single API call
    let activeTagCountRequest = null;
    let activeTagCountPromise = null;
    let activeTagCountKey = '';

    // Debounce control - prevents duplicate calls
    let tagCountDebounceTimeout = null;
    let lastTagCountCallTime = 0;
    const TAG_COUNT_DEBOUNCE_MS = 100; // PERF: was 300ms — 100ms coalesces rapid clicks without visible lag

    // Cache for tag counts - prevents unnecessary API calls
    let cachedTagCounts = { key: '', data: null, time: 0 };
    const TAG_COUNTS_CACHE_TTL = 1500; // 1.5s cache for rapid UI interactions

    /**
     * Update a single tag count badge - NO FLICKERING
     * Only updates if count is valid (not null/undefined), preserves old value otherwise.
     * Triggers a brief CSS pop animation only when the number actually changes.
     */
    function updateTagBadge(tagId, count) {
      const btn = document.getElementById(tagId);
      if (!btn) return;

      // Don't update if count is invalid - preserve current value to prevent flickering
      if (count === null || count === undefined) {
        return;
      }

      const countStr = String(count);
      let span = btn.querySelector('.count');

      if (span) {
        // Skip DOM write + animation entirely when value hasn't changed
        if (span.textContent === countStr) return;

        span.textContent = countStr;

        // Trigger pop animation: remove first so rapid updates always restart it
        span.classList.remove('count-updated');
        // Reflow forces the browser to recognise the class removal before re-adding
        void span.offsetWidth;
        span.classList.add('count-updated');
        // Auto-remove after animation completes (300ms) to keep DOM tidy
        setTimeout(() => span.classList.remove('count-updated'), 300);
      } else {
        btn.innerHTML = `${btn.innerHTML} <span class="count">${countStr}</span>`;
      }
    }

    /**
     * Fetch ALL tag counts in a single API call - SUPER OPTIMIZED
     * Replaces 11 individual API calls with just 1
     */
    async function fetchAllTagCounts() {
      const filterParams = getCurrentFilterState();

      // Reuse in-flight request for identical filters
      if (activeTagCountPromise && activeTagCountKey === filterParams) {
        return activeTagCountPromise;
      }

      // Check cache first
      const now = Date.now();
      if (
        cachedTagCounts.data &&
        cachedTagCounts.key === filterParams &&
        (now - cachedTagCounts.time) < TAG_COUNTS_CACHE_TTL
      ) {
        return cachedTagCounts.data;
      }

      // Different query supersedes older one.
      if (activeTagCountRequest) {
        activeTagCountRequest.abort();
        activeTagCountRequest = null;
      }

      // Create new AbortController
      const abortController = new AbortController();
      activeTagCountRequest = abortController;
      activeTagCountKey = filterParams;

      activeTagCountPromise = (async () => {
        try {
          const response = await fetch(`update_status.php?get_all_tag_counts=1&${filterParams}`, {
            signal: abortController.signal,
            cache: 'no-cache'
          });

          if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
          }

          const data = await response.json();

          if (data.status === 'success' && data.counts) {
            // Cache the result
            cachedTagCounts = { key: filterParams, data: data.counts, time: Date.now() };
            return data.counts;
          }
          return null;
        } catch (error) {
          // Don't log abort errors (they're expected when cancelling)
          if (error.name !== 'AbortError') {
            console.error('Error fetching all tag counts:', error);
          }
          return null;
        } finally {
          activeTagCountRequest = null;
          activeTagCountPromise = null;
        }
      })();

      return activeTagCountPromise;
    }

    /**
     * Update all tag counts with a SINGLE API call - SUPER OPTIMIZED
     * This replaces 11 separate API calls with just 1
     */
    async function updateAllTagCounts() {
      // Debounce - prevent rapid duplicate calls
      const now = Date.now();
      if ((now - lastTagCountCallTime) < TAG_COUNT_DEBOUNCE_MS) {
        // Schedule for later if we're calling too fast
        if (tagCountDebounceTimeout) {
          clearTimeout(tagCountDebounceTimeout);
        }
        tagCountDebounceTimeout = setTimeout(() => {
          updateAllTagCounts();
        }, TAG_COUNT_DEBOUNCE_MS);
        return;
      }

      lastTagCountCallTime = now;

      try {
        const counts = await fetchAllTagCounts();

        if (counts) {
          // Update all badges at once from single response
          updateTagBadge('myLeads', counts.myLeads);
          updateTagBadge('bookedLeads', counts.bookedLeads);
          updateTagBadge('activeLeads', counts.activeLeads);
          updateTagBadge('freshLeads', counts.freshLeads);
          updateTagBadge('pendingLeads', counts.pendingLeads);
          updateTagBadge('droppedLeads', counts.droppedLeads);
          updateTagBadge('followLeads', counts.followLeads);
          updateTagBadge('overdueLeads', counts.overdueLeads);
          updateTagBadge('today_collection', counts.today_collection);
          updateTagBadge('paidAds', counts.paidAds);
          updateTagBadge('SHI_D', counts.shi_d ?? counts.nfs_d);
          updateTagBadge('NFS_D', counts.nfs_d ?? counts.shi_d);
        }
      } catch (error) {
        console.error('Error updating tag counts:', error);
      }
    }

    // Debounce wrapper for search input
    let searchDebounceTimeout;
    function debouncedUpdateAllTagCounts(delay = 300) {
      if (searchDebounceTimeout) {
        clearTimeout(searchDebounceTimeout);
      }
      searchDebounceTimeout = setTimeout(() => {
        updateAllTagCounts();
      }, delay);
    }

    function scheduleCountRefresh() {
      // Use debounced update to prevent rapid duplicate calls
      debouncedUpdateAllTagCounts();
    }

    // Update badge counts - uses the single optimized API call
    async function updateFilteredBadgeCounts() {
      await updateAllTagCounts();
    }

    // REMOVED: Dead code below - replaced by updateAllTagCounts() system for better performance
    /*
      const managerToggleEl = document.getElementById("managerToggle");
      const toggleState = (managerToggleEl && managerToggleEl.dataset.selfLock === '1') ? 0 : (managerToggleEl && managerToggleEl.checked ? 1 : 0);
      const urlParams = new URLSearchParams(window.location.search);
      const filterUserParam = urlParams.get("filterUser");
      const managerViewParam = urlParams.get("managerView");

      const hasTable = (typeof table !== 'undefined') && table && $.fn.DataTable.isDataTable("#leadsTable");
      const globalSearch = hasTable && typeof table.search === 'function' ? table.search().trim() : '';
      const hasGlobalSearch = !!globalSearch;

      // Detect header dropdown/column filters
      let columnsSearch = [];
      if (hasTable && typeof table.columns === 'function') {
        try {
          table.columns().every(function () {
            const colSearch = this.search();
            if (colSearch && typeof colSearch === 'string' && colSearch.trim() !== '') {
              columnsSearch.push({ index: this.index(), value: colSearch });
            }
          });
        } catch (e) {
          console.warn('Column search read failed for badge counts', e);
        }
      }

      const hasColumnSearch = columnsSearch.length > 0;
      const hasMultiFilters = window.multiFilters && Object.keys(window.multiFilters).length > 0;
      const hasHeaderFilters = window.headerDropdownFilters && Object.keys(window.headerDropdownFilters).length > 0;
      const hasAnyFilter = hasGlobalSearch || hasColumnSearch || hasMultiFilters || hasHeaderFilters;

      const setCount = (id, count) => {
        const btn = document.getElementById(id);
        if (!btn) return;
        const span = btn.querySelector('.count');
        if (span) span.textContent = count;
        else btn.innerHTML = `${btn.innerHTML} <span class="count">${count}</span>`;
      };

      // If searching/filtering, compute counts from filtered dataset; otherwise fetch totals
      if (hasAnyFilter) {
        // Build filtered request
        const combinedFilters = { ...(window.multiFilters || {}) };
        if (window.headerDropdownFilters && Object.keys(window.headerDropdownFilters).length > 0) {
          Object.assign(combinedFilters, window.headerDropdownFilters);
        }

        const queryParams = new URLSearchParams({
          page: '1',
          rowsPerPage: '1000000',
          searchQuery: globalSearch || '',
          managerToggle: toggleState ? '1' : '0',
          filter: '', // keep badges independent of status button selection
          multiFilters: JSON.stringify(combinedFilters)
        });

        if (managerToggleEl && managerToggleEl.dataset.selfLock !== '1' && managerToggleEl.checked) {
          queryParams.set('managerView', 'true');
        } else {
          queryParams.set('managerView', 'false');
        }

        if (filterUserParam) queryParams.set('filterUser', filterUserParam);

        columnsSearch.forEach(({ index, value }) => {
          queryParams.append(`columns[${index}][search][value]`, value);
        });

        try {
          const response = await fetch(`update_status.php?${queryParams.toString()}`);
          const data = await response.json();
          const rows = data.data || data || [];

          // Tally counts from filtered rows
          const counts = {
            active: 0,
            fresh: 0,
            pending: 0,
            follow: 0,
            overdue: 0,
            myLeads: 0,
            booked: 0,
            dropped: 0,
            today: 0,
            ads: 0,
            nfs_d: 0,
          };

          const mappedRows = rows.map((row) => ({
            status: (row.user_status || row.status || '').toLowerCase(),
            lastTouch: (row.user_status === 'Pending' ? 'untouched' : (row.created_at || row.created || '')).toLowerCase(),
            daysUntouched: Number(row.days_untouched || row.daysUntouched || 0),
            sourceOfLead: (row.source_of_lead || row.sourceOfLead || '').toLowerCase().trim(),
            created: row.created_at || row.created,
            isOverdue: row.is_overdue || row.isOverdue || 0,
            followUpDate: row.follow_up_date || row.followUpDate,
          }));

          const isActiveLead = (s) => {
            const v = (s || '').trim();
            return v !== '' && v !== 'not interested';
          };

          const today = new Date();
          today.setHours(0, 0, 0, 0);
          const tomorrow = new Date(today);
          tomorrow.setDate(tomorrow.getDate() + 1);

          mappedRows.forEach((row) => {
            const status = row.status;
            if (isActiveLead(status)) counts.active += 1;
            if (status === 'pending' || row.lastTouch.includes('untouched') || row.daysUntouched > 0) counts.pending += 1;
            if (status.includes('follow')) counts.follow += 1;
            if (row.isOverdue == 1 || String(row.isOverdue).toLowerCase() === 'true') counts.overdue += 1;

            if (row.created) {
              const createdDate = new Date(row.created);
              if (!isNaN(createdDate)) {
                const diffDays = (Date.now() - createdDate.getTime()) / 86400000;
                if (diffDays <= 5) counts.fresh += 1;
              }
            }

            counts.myLeads += 1;
            if (status === 'already booked') counts.booked += 1;
            if (status === 'dropped' || status === 'not interested' || status === 'fake') counts.dropped += 1;

            if (row.followUpDate) {
              const followDate = new Date(row.followUpDate);
              if (!isNaN(followDate)) {
                followDate.setHours(0, 0, 0, 0);
                if (followDate >= today && followDate < tomorrow && (status.includes('follow') || status === 'interested' || status === 'eoi' || status === 'call back' || status === 'rnr' || status === 'fix site visit')) {
                  counts.today += 1;
                }
              }
            }

            const adsSources = ['google ads', 'facebook ads', 'magicbricks ads', '99acres ads', 'housing.com ads'];
            if (adsSources.includes(row.sourceOfLead)) counts.ads += 1; else counts.nfs_d += 1;
          });

          setCount('activeLeads', counts.active);
          setCount('freshLeads', counts.fresh);
          setCount('pendingLeads', counts.pending);
          setCount('followLeads', counts.follow);
          setCount('overdueLeads', counts.overdue);
          setCount('myLeads', counts.myLeads);
          setCount('bookedLeads', counts.booked);
          setCount('droppedLeads', counts.dropped);
          setCount('today_collection', counts.today);
          setCount('paidAds', counts.ads);
          setCount('NFS_D', counts.nfs_d);
          return;
        } catch (err) {
          console.error('Error updating filtered badge counts:', err);
          // fall through to totals fetch as fallback
        }
      }

      // Totals path (no search/filters or fallback)
      const totalsParams = new URLSearchParams({
        get_data: '1',
        toggle_enabled: toggleState ? '1' : '0'
      });
      if (filterUserParam) totalsParams.set('filterUser', filterUserParam);
      if (managerViewParam) totalsParams.set('managerView', managerViewParam);

      try {
        const response = await fetch(`update_status.php?${totalsParams.toString()}`);
        const data = await response.json();

        setCount('myLeads', data.myLeads ?? 0);
        setCount('bookedLeads', data.bookedLeads ?? 0);
        setCount('droppedLeads', data.droppedLeads ?? 0);
        setCount('today_collection', data.today_collection ?? 0);
        setCount('paidAds', data.paidAds ?? 0);
        setCount('NFS_D', data.nfs_d ?? 0);
        setCount('activeLeads', data.activeLeads ?? 0);
        setCount('freshLeads', data.freshLeads ?? 0);
        setCount('pendingLeads', data.pendingLeads ?? 0);
        setCount('followLeads', data.followupLeads ?? 0);
        setCount('overdueLeads', data.overdueLeads ?? 0);
      } catch (err) {
        console.error('Error updating badge totals:', err);
      }
    */

    function setupFilterCounterListeners() {
      // Date filter changes - INSTANT UPDATE
      $("#dateFrom, #dateTo").on("change", () => {
        clearFilterStateCache(); // Clear cache when filters change
        updateFilterCounter();
        updateAllTagCounts();
      });

      // Search input changes - INSTANT UPDATE with debounce for typing
      $(".dataTables_filter input").off("keyup.searchCountRefresh").on("keyup.searchCountRefresh", function () {
        clearFilterStateCache(); // Clear cache when filters change
        updateFilterCounter();
        debouncedUpdateAllTagCounts(300); // Debounce for search typing
      });

      // Column filter changes (header dropdown) - INSTANT UPDATE
      $(document).on("change", ".filter-option", function () {
        clearFilterStateCache();
        updateFilterCounter();
        updateAllTagCounts();

        // --- Persistent per-column selection store ---
        // Keeps track of ALL checked values across DOM rebuilds (search changes)
        window.__colSelections = window.__colSelections || {};
        const $cb = $(this);
        const colIdx = String($cb.data('column') ?? '');
        if (!colIdx) return;
        const rawAttr = $cb.attr('data-raw');
        const val = rawAttr ? decodeURIComponent(rawAttr) : ($cb.val() || '');
        if (!window.__colSelections[colIdx]) window.__colSelections[colIdx] = new Set();
        if ($cb.is(':checked')) {
          if (val === '') {
            // "All" checked → clear specific selections for this column
            window.__colSelections[colIdx] = new Set(['']);
          } else {
            window.__colSelections[colIdx].delete(''); // unmark "All"
            window.__colSelections[colIdx].add(val);
          }
        } else {
          window.__colSelections[colIdx].delete(val);
          if (window.__colSelections[colIdx].size === 0) {
            window.__colSelections[colIdx].add(''); // fall back to "All"
          }
        }
      });

      // Header dropdown search input changes - INSTANT UPDATE
      $(document).on("keyup", ".filter-search-input", () => {
        clearFilterStateCache(); // Clear cache when filters change
        updateFilterCounter();
        debouncedUpdateAllTagCounts(300);
      });

      // Status filter changes - INSTANT UPDATE
      $('#filterStatusSelect').on('change', () => {
        clearFilterStateCache(); // Clear cache when filters change
        updateFilterCounter();
        updateAllTagCounts();
      });

      // Isolated filter changes - INSTANT UPDATE
      $("#isolatedFilterCustumername, #isolatedFilterEmail, #isolatedFilterContactnumber, " +
        "#isolatedFilterLocation, #isolatedFilterSourceOfLead, #isolatedFilterStatus, " +
        "#isolatedFilterAssignedProjectName, #isolatedFilterAssigneduserName, " +
        "#isolatedFilterAssignedIdentity, #isolatedFilterBudget, " +
        "#isolatedFilterStartDate, #isolatedFilterEndDate"
      ).on("change keyup", () => {
        clearFilterStateCache(); // Clear cache when filters change
        updateFilterCounter();
        debouncedUpdateAllTagCounts(300);
      });

      // Clear filters button - INSTANT UPDATE
      $("#isolatedClearFiltersBtn").on("click", () => {
        clearFilterStateCache(); // Clear cache when filters change
        updateFilterCounter();
        updateAllTagCounts();
      });

      // Apply filters button - INSTANT UPDATE
      $("#isolatedApplyFiltersBtn").on("click", () => {
        clearFilterStateCache(); // Clear cache when filters change
        updateFilterCounter();
        updateAllTagCounts();
      });

      // Manager toggle - INSTANT UPDATE
      if (managerToggle) {
        managerToggle.addEventListener("change", () => {
          clearFilterStateCache(); // Clear cache when filters change
          updateFilterCounter();
          updateAllTagCounts();
        });
      }

      // Filter buttons (my leads, booked leads, etc.) - INSTANT UPDATE
      $(document).on("click", ".tab-row .filter-btn, .filter-row .filter-btn", () => {
        clearFilterStateCache(); // Clear cache when filters change
        updateFilterCounter();
        updateAllTagCounts();
      });
    }

    setTimeout(setupFilterCounterListeners, 700);

    // Call the function to initialize the DataTable
    // initializeLeadsTable();
    // Function to populate filter dropdown with dynamic options (now async to fetch filtered values)
    async function populateFilterDropdown($header) {
      const columnIndex = parseInt($header.find(".filter-search-input").data("column")) || parseInt($header.find(".filter-option").first().data("column"));
      const filterKey = getFilterKeyByColumnIndex(columnIndex);
      const $dropdown = $header.find(".filter-options");

      // Collect current column searches so server respects active column filters
      const columnsSearch = [];
      if (table && $.fn.DataTable.isDataTable('#leadsTable') && typeof table.columns === 'function') {
        try {
          table.columns().every(function () {
            const colSearch = this.search();
            if (colSearch && typeof colSearch === 'string' && colSearch.trim() !== '') {
              columnsSearch.push({ index: this.index(), value: colSearch });
            }
          });
        } catch (e) {
          console.warn('Column search read failed for dropdown', e);
        }
      }

      // Check if there's an active filter for this column
      const hasActiveFilter = window.headerDropdownFilters && window.headerDropdownFilters[filterKey] && window.headerDropdownFilters[filterKey].trim() !== '';
      const activeFilterValues = hasActiveFilter ? window.headerDropdownFilters[filterKey].split(',').map(v => v.trim()).filter(v => v) : [];

      // Save current selections — prefer persistent store so searches don't lose prior picks
      window.__colSelections = window.__colSelections || {};
      const _storedPF = window.__colSelections[String(columnIndex)];
      const currentSelections = _storedPF
        ? Array.from(_storedPF)
        : (() => {
          const arr = [];
          $dropdown.find('.filter-option:checked').each(function () {
            const rawAttr = $(this).attr('data-raw');
            arr.push(rawAttr ? decodeURIComponent(rawAttr) : ($(this).val() || ''));
          });
          return arr;
        })();

      // Show initial options from current page data immediately (no loading state)
      $dropdown.empty();

      // Add "All" option
      $dropdown.append(`
        <label><input type="checkbox" class="filter-option" data-column="${columnIndex}" value="" ${!hasActiveFilter && currentSelections.includes("") ? 'checked' : ''}> All</label>
    `);

      // If there's an active filter, show only selected values
      if (hasActiveFilter && activeFilterValues.length > 0) {
        // Show only the selected filter values
        activeFilterValues.forEach(v => {
          const raw = encodeURIComponent(v);
          const decoded = decodeURIComponent(raw);
          if (!$dropdown.find(`.filter-option[data-column="${columnIndex}"][data-raw="${raw}"]`).length) {
            $dropdown.append(`
              <label><input type="checkbox" class="filter-option" data-column="${columnIndex}" data-raw="${raw}" value="${escapeHtml(decoded)}" checked> ${escapeHtml(decoded)}</label>
            `);
          }
        });
      } else {
        // Extract initial unique values from current page data immediately
        if (table && $.fn.DataTable.isDataTable('#leadsTable')) {
          const initialValues = new Set();
          const columnFieldMap = {
            1: 'name',
            2: 'assignedProject',
            3: 'id',
            5: 'email',
            6: 'location',       // was missing — Location column
            7: 'budget',         // was missing — Budget column
            8: 'remarks',
            9: 'updatedAt',
            10: 'assignedLead',
            11: 'source'
          };
          const fieldName = columnFieldMap[columnIndex];

          if (fieldName) {
            try {
              table.rows({ page: 'current' }).every(function () {
                const rowData = this.data();
                if (rowData && rowData[fieldName]) {
                  const value = String(rowData[fieldName]).trim();
                  if (value) initialValues.add(value);
                }
              });

              // Add initial values immediately
              initialValues.forEach(v => {
                const raw = encodeURIComponent(v);
                const isSelected = currentSelections.includes(v) || currentSelections.includes(decodeURIComponent(raw));
                if (!$dropdown.find(`.filter-option[data-column="${columnIndex}"][data-raw="${raw}"]`).length) {
                  $dropdown.append(`
                    <label><input type="checkbox" class="filter-option" data-column="${columnIndex}" data-raw="${raw}" value="${escapeHtml(v)}" ${isSelected ? 'checked' : ''}> ${escapeHtml(v)}</label>
                  `);
                }
              });
            } catch (e) {
              console.warn('Error extracting initial values:', e);
            }
          }
        }
      }

      // Append initial values from current page
      // Lazy loader for remaining values on scroll
      const loaderKey = `${columnIndex}:${getCurrentDatasetKey()}`;
      window.__uniqueValueLoaders = window.__uniqueValueLoaders || {};
      window.__uniqueValueLoaders[loaderKey] = {
        page: 1,
        loading: false,
        done: false,
        seenKeys: new Set(), // normalized dedupe
        hydratedAll: false,
        buffer: [],          // store extra unique values found in a fetch but not yet rendered
        initialLimit: 5,     // show only 5 options initially (plus "All")
        initialFilled: false // once initialLimit rendered, stop appending until scroll
      };

      // Keep chunks big enough to quickly yield many unique options (improves perceived speed)
      const rowsPerPage = 50;

      const fetchChunk = async () => {
        const state = window.__uniqueValueLoaders[loaderKey];
        if (!state || state.loading || state.done) return;
        state.loading = true;

        try {
          // Flush buffered values first (no network)
          if (Array.isArray(state.buffer) && state.buffer.length > 0) {
            let toAppend = state.buffer;
            // If initial render isn't filled yet, cap to initialLimit
            if (!state.initialFilled) {
              const already = $dropdown.find(`.filter-option[data-column="${columnIndex}"]`).length - 1; // excluding All
              const remaining = Math.max(0, (state.initialLimit || 5) - already);
              if (remaining <= 0) {
                state.initialFilled = true;
              } else {
                const take = toAppend.slice(0, remaining);
                const keep = toAppend.slice(remaining);
                state.buffer = keep;
                toAppend = take;
                if (keep.length === 0) state.initialFilled = true;
              }
            } else {
              // On scroll, append in manageable chunks to keep UI smooth
              const take = toAppend.slice(0, 50);
              const keep = toAppend.slice(50);
              state.buffer = keep;
              toAppend = take;
            }

            for (const v of toAppend) {
              const raw = encodeURIComponent(v);
              const isSelected = currentSelections.includes(v) || currentSelections.includes(decodeURIComponent(raw));
              if ($dropdown.find(`.filter-option[data-column="${columnIndex}"][data-raw="${raw}"]`).length === 0) {
                $dropdown.append(`
                    <label><input type="checkbox" class="filter-option" data-column="${columnIndex}" data-raw="${raw}" value="${escapeHtml(v)}" ${isSelected ? 'checked' : ''}> ${escapeHtml(v)}</label>
                  `);
              }
            }

            // If we appended something from buffer, we can return early
            if (toAppend.length > 0) return;
          }

          const currentSearch = table && table.search ? table.search() : '';
          const combinedFilters = { ...(window.multiFilters || {}) };
          if (window.headerDropdownFilters && Object.keys(window.headerDropdownFilters).length > 0) {
            Object.assign(combinedFilters, window.headerDropdownFilters);
          }

          // Do not self-filter this column so all choices remain visible
          if (filterKey && combinedFilters[filterKey]) {
            delete combinedFilters[filterKey];
          }

          // OPTIMIZED: Use fast get_unique_values endpoint (DISTINCT query - much faster than fetching all rows)
          const urlParams = new URLSearchParams(window.location.search);
          const filterUserParam = urlParams.get("filterUser");
          const managerViewParam = urlParams.get("managerView");

          const uniqueValuesParams = new URLSearchParams({
            get_unique_values: '1',
            columnIndex: columnIndex.toString(),
            page: String(state.page),
            perPage: String(rowsPerPage),
            searchQuery: currentSearch || '',
            dateFrom: $("#dateFrom").val() || '',
            dateTo: $("#dateTo").val() || '',
            managerToggle: managerToggle && managerToggle.dataset.selfLock !== '1' && managerToggle.checked ? '1' : '0',
            filter: window.currentActiveFilter || '',
            multiFilters: JSON.stringify(combinedFilters)
          });

          if (filterUserParam) uniqueValuesParams.set('filterUser', filterUserParam);
          if (managerViewParam) uniqueValuesParams.set('managerView', managerViewParam);

          columnsSearch.forEach(({ index, value }) => {
            uniqueValuesParams.append(`columns[${index}][search][value]`, value);
          });

          const url = `update_status.php?${uniqueValuesParams.toString()}`;
          const resp = await fetch(url, { method: 'GET', cache: 'no-cache' });
          if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
          const json = await resp.json();
          const uniqueValues = Array.isArray(json.values) ? json.values : [];

          if (uniqueValues.length === 0) {
            state.done = true;
          }

          // OPTIMIZED: Process unique values directly (no row conversion needed)
          const newlyFound = uniqueValues
            .filter(v => {
              if (!v || v === null || v === undefined) return false;
              const cleanV = String(v).replace(/<[^>]*>/g, '').trim();
              if (!cleanV) return false;
              const norm = cleanV.replace(/\s+/g, ' ').toLowerCase();
              if (!state.seenKeys.has(norm)) {
                state.seenKeys.add(norm);
                return true;
              }
              return false;
            })
            .map(v => String(v).replace(/<[^>]*>/g, '').trim());

          // OPTIMIZED: Append or buffer based on initial limit
          if (newlyFound.length > 0) {
            const already = $dropdown.find(`.filter-option[data-column="${columnIndex}"]`).length - 1; // excluding All
            if (!state.initialFilled) {
              const remaining = Math.max(0, (state.initialLimit || 5) - already);
              const toAppend = newlyFound.slice(0, remaining);
              const toBuffer = newlyFound.slice(remaining);
              if (toBuffer.length) state.buffer.push(...toBuffer);
              if (toAppend.length < remaining) {
                // still not full; will fetch more on next call
              } else {
                state.initialFilled = true;
              }
              for (const v of toAppend) {
                const raw = encodeURIComponent(v);
                if ($dropdown.find(`.filter-option[data-column="${columnIndex}"][data-raw="${raw}"]`).length === 0) {
                  const isSelected = currentSelections.includes(v) || currentSelections.includes(decodeURIComponent(raw));
                  $dropdown.append(`
                      <label><input type="checkbox" class="filter-option" data-column="${columnIndex}" data-raw="${raw}" value="${escapeHtml(v)}" ${isSelected ? 'checked' : ''}> ${escapeHtml(v)}</label>
                    `);
                }
              }
            } else {
              // After initial fill, buffer everything; scroll will flush
              state.buffer.push(...newlyFound);
            }
          }

          // OPTIMIZED: Check if more values available (use hasMore from endpoint)
          if (!json.hasMore || uniqueValues.length < rowsPerPage) {
            state.done = true;
          }

          state.page += 1;
        } catch (err) {
          console.error('Lazy load dropdown values failed:', err);
          state.done = true; // avoid spin
        } finally {
          state.loading = false;
        }
      };

      // OPTIMIZED: Initial chunk (await so first 5 server values appear immediately)
      // Use fast endpoint for initial load
      await fetchChunk();

      // NOTE: We intentionally do NOT prefill for header dropdowns because we only want 5 options initially.

      // Attach scroll listener for infinite loading
      $dropdown.off('scroll.lazyValues').on('scroll.lazyValues', function () {
        const el = this;
        if (el.scrollTop + el.clientHeight >= el.scrollHeight - 10) {
          fetchChunk();
        }
      });

      // Fallback: if key columns still have very few options, pull full unique set
      if (columnIndex === 2 || columnIndex === 6 || columnIndex === 7 || columnIndex === 8 || columnIndex === 9 || columnIndex === 10 || columnIndex === 11) {
        const ensureFullList = async () => {
          const existing = new Set();
          $dropdown.find('.filter-option[data-column="' + columnIndex + '"]').each(function () {
            const rawAttr = $(this).attr('data-raw');
            const rawVal = rawAttr ? decodeURIComponent(rawAttr) : $(this).val();
            if (rawVal) existing.add(rawVal.trim());
          });

          // If we have fewer than 5 distinct values (excluding All), fetch full unique list
          if (existing.size < 5) {
            try {
              const fullList = await getUniqueColumnValuesAll(columnIndex);
              fullList.forEach(v => {
                const norm = String(v).trim();
                if (!norm) return;
                if (existing.has(norm)) return;
                existing.add(norm);
                const raw = encodeURIComponent(norm);
                const isSelected = currentSelections.includes(norm) || currentSelections.includes(decodeURIComponent(raw));
                $dropdown.append(`
                  <label><input type="checkbox" class="filter-option" data-column="${columnIndex}" data-raw="${raw}" value="${escapeHtml(norm)}" ${isSelected ? 'checked' : ''}> ${escapeHtml(norm)}</label>
                `);
              });
            } catch (err) {
              console.error('Fallback unique fetch failed:', err);
            }
          }
        };

        // Run fallback without blocking main flow
        setTimeout(ensureFullList, 0);
      }

      // Check previously selected values if they exist (prefer headerDropdownFilters for UI state)
      if (currentSelections.length === 0) {
        const filterKey = getFilterKeyByColumnIndex(columnIndex);
        let selectedCsv = "";
        if (window.headerDropdownFilters && window.headerDropdownFilters[filterKey]) {
          selectedCsv = window.headerDropdownFilters[filterKey];
        } else if (window.multiFilters && window.multiFilters[filterKey]) {
          selectedCsv = window.multiFilters[filterKey];
        }
        if (selectedCsv) {
          const selectedSet = new Set(selectedCsv.split(","));
          $dropdown.find('.filter-option').each(function () {
            const rawAttr = $(this).attr('data-raw');
            const rawVal = rawAttr ? decodeURIComponent(rawAttr) : $(this).val();
            if (selectedSet.has(rawVal)) {
              $(this).prop('checked', true);
            }
          });
          $dropdown.find('.filter-option[value=""]').prop("checked", false);
        }

        const columnSearch = table && typeof table.column === "function"
          ? table.column(columnIndex).search()
          : "";
        const currentSearch = typeof columnSearch === "string"
          ? columnSearch
          : (columnSearch && typeof columnSearch.search === "string" ? columnSearch.search : "");
        if (currentSearch && typeof currentSearch === "string" && currentSearch.split) {
          const selectedValues = currentSearch.split('|');
          selectedValues.forEach(value => {
            $dropdown.find(`.filter-option[value="${value}"]`).prop("checked", true);
          });
          $dropdown.find('.filter-option[value=""]').prop("checked", false);
        } else if (currentSelections.length === 0) {
          $dropdown.find('.filter-option[value=""]').prop("checked", true);
        }
      }

      // Add Apply, Sort, and Clear buttons at the end of dropdown (remove old ones first to avoid duplicates)
      const $filterDropdown = $header.find(".filter-dropdown");
      $filterDropdown.find(".filter-apply-btn, .filter-clear-btn, #createdAtSortBtn").closest('div').remove();

      let sortBtnHtml = '';
      if (columnIndex === 4) {
        const isAscending = createdAtSortDirection === "asc";
        const icon = isAscending ? "fa-sort-amount-up" : "fa-sort-amount-down-alt";
        sortBtnHtml = `<button type="button" id="createdAtSortBtn" data-column="4" title="Sort Column" style="padding: 8px 12px; background: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px;"><i class="fas ${icon}"></i></button>`;
      }

      $filterDropdown.append(`
        <div style="border-top: 1px solid #eee; padding-top: 10px; margin-top: 10px; display: flex; gap: 8px;">
          <button class="filter-apply-btn" data-column="${columnIndex}" style="flex: 1; padding: 8px; background: #2a8c90; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 500;">Apply Filter</button>
          ${sortBtnHtml}
          <button class="filter-clear-btn" data-column="${columnIndex}" style="padding: 8px 12px; background: #ef4444; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: 500; font-size: 16px; line-height: 1;">&times;</button>
        </div>
      `);
    }

    // Function to get unique values from ALL filtered rows (not just current page)
    // OPTIMIZED: Fast unique column values fetching using dedicated endpoint
    async function getUniqueColumnValuesAll(columnIndex) {
      // Prefer cached values if available for the current filter context
      const cacheReady = window.__leadsUniqueCache && window.__leadsUniqueCache.loaded && (window.__leadsUniqueCache.key === getCurrentDatasetKey());
      if (cacheReady) {
        const arr = window.__leadsUniqueCache.values?.[columnIndex];
        if (Array.isArray(arr) && arr.length > 0) return arr;
      }

      if (!table || !$.fn.DataTable.isDataTable('#leadsTable')) {
        return [];
      }

      try {
        // OPTIMIZED: Use dedicated fast endpoint instead of fetching 1M rows
        const currentSearch = table.search() || '';
        const dateFrom = $("#dateFrom").val() || '';
        const dateTo = $("#dateTo").val() || '';
        const managerToggleEl = document.getElementById("managerToggle");
        const currentToggleState = (managerToggleEl && managerToggleEl.dataset.selfLock === '1')
          ? 0
          : (managerToggleEl && managerToggleEl.checked ? 1 : 0);

        const urlParams = new URLSearchParams(window.location.search);
        const filterUserParam = urlParams.get("filterUser");

        // OPTIMIZED: Use get_unique_values endpoint (much faster - uses DISTINCT query)
        const queryParams = new URLSearchParams({
          get_unique_values: '1',
          columnIndex: columnIndex.toString(),
          page: '1',
          perPage: '200', // OPTIMIZED: Get 200 at a time (enough for dropdown)
          searchQuery: currentSearch,
          dateFrom: dateFrom,
          dateTo: dateTo,
          managerToggle: currentToggleState.toString(),
          filter: window.currentActiveFilter || '',
          multiFilters: JSON.stringify(window.multiFilters || {})
        });

        if (managerToggleEl && managerToggleEl.dataset.selfLock !== '1' && managerToggleEl.checked) {
          queryParams.set('managerView', 'true');
        } else {
          queryParams.set('managerView', 'false');
        }

        if (filterUserParam) queryParams.set('filterUser', filterUserParam);

        // OPTIMIZED: Fetch from fast endpoint
        const response = await fetch(`update_status.php?${queryParams.toString()}`);
        if (!response.ok) throw new Error(`HTTP ${response.status}`);

        const data = await response.json();
        const values = Array.isArray(data.values) ? data.values : [];

        return values.sort((a, b) => a.localeCompare(b, undefined, { sensitivity: 'base' }));
      } catch (error) {
        console.error('Error fetching unique values:', error);
        // Fallback: gather from currently loaded rows only (current page)
        const values = new Set();
        if (table && $.fn.DataTable.isDataTable('#leadsTable')) {
          table.rows({ page: 'current' }).every(function () {
            const data = this.data();
            let value;
            switch (columnIndex) {
              case 1: value = data.name; break;
              case 2: value = data.assignedProject; break;
              case 3: value = data.id; break;
              case 4: value = data.created; break;
              case 5: value = data.email; break;
              case 6: value = data.location; break;
              case 7: value = data.budget; break;
              case 8: value = data.remarks; break;
              case 9: value = data.updatedAt; break;
              case 10: value = data.assignedLead; break;
              case 11: value = data.source_of_lead || data.source || data.lead_source; break;
              default: value = "";
            }
            if (value !== undefined && value !== null && value !== "") {
              const cleanValue = typeof value === 'string' ? value.replace(/<[^>]*>/g, '').trim() : String(value).trim();
              if (cleanValue) values.add(cleanValue);
            }
          });
        }
        return Array.from(values).sort((a, b) => a.localeCompare(b, undefined, { sensitivity: 'base' }));
      }
    }


    // Helper function to escape HTML
    function escapeHtml(unsafe) {
      return unsafe
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
    }


    // Initialize filter dropdown functionality
    function initFilterDropdowns() {
      // Show/hide dropdowns when clicking header buttons
      $(document).on("click", ".filter-header-btn", function (e) {
        e.stopPropagation();
        e.preventDefault();

        const $header = $(this).closest(".filter-header");
        const $dropdown = $header.find(".filter-dropdown");

        const wasVisible = $dropdown.is(":visible");
        // Hide all other dropdowns first
        $(".filter-dropdown").not($dropdown).hide();

        // Toggle close if already open
        if (wasVisible) {
          $dropdown.hide();
          window.activeDropdownHeader = null;
          return;
        }

        // Track which dropdown is currently active
        window.activeDropdownHeader = $header[0];

        // Show dropdown immediately with cached/initial options
        $dropdown.show();

        // Populate dropdown dynamically in background (async)
        populateFilterDropdown($header).catch(err => {
          console.error('Error populating dropdown:', err);
        });

        // Clear active dropdown when hiding
        if (!$dropdown.is(':visible')) {
          window.activeDropdownHeader = null;
        }
      });

      // Prevent dropdown from closing when clicking inside it (except close button)
      $(document).on("click", ".filter-dropdown", function (e) {
        // Allow close button clicks to propagate
        if ($(e.target).hasClass('filter-close-btn') || $(e.target).closest('.filter-close-btn').length) {
          return;
        }
        e.stopPropagation();
      });

      // Handle search input in dropdowns
      // Remote search for dropdown options so search works beyond lazily loaded items
      const __dropdownSearchState = window.__dropdownSearchState || (window.__dropdownSearchState = {});

      async function remoteSearchDropdownOptions($header, term) {
        const columnIndex =
          parseInt($header.find(".filter-search-input").data("column")) ||
          parseInt($header.find(".filter-option").first().data("column"));
        const $options = $header.find(".filter-options");
        if (!columnIndex || !$options.length) return;

        const filterKey = getFilterKeyByColumnIndex(columnIndex);

        // Preserve current selections — use persistent store so prior selections survive DOM rebuild
        window.__colSelections = window.__colSelections || {};
        const _stored = window.__colSelections[String(columnIndex)];
        const currentSelections = _stored
          ? Array.from(_stored)
          : (() => {
            const arr = [];
            $options.find('.filter-option:checked').each(function () {
              const rawAttr = $(this).attr('data-raw');
              arr.push(rawAttr ? decodeURIComponent(rawAttr) : ($(this).val() || ''));
            });
            return arr;
          })();

        // Build combined filters (same as populateFilterDropdown)
        const currentSearch = table && table.search ? table.search() : "";
        const combinedFilters = { ...(window.multiFilters || {}) };
        if (window.headerDropdownFilters && Object.keys(window.headerDropdownFilters).length > 0) {
          Object.assign(combinedFilters, window.headerDropdownFilters);
        }
        // Do not self-filter this column
        if (filterKey && combinedFilters[filterKey]) {
          delete combinedFilters[filterKey];
        }

        // Column-specific searches
        const columnsSearch = [];
        if (table && $.fn.DataTable.isDataTable("#leadsTable") && typeof table.columns === "function") {
          try {
            table.columns().every(function () {
              const colSearch = this.search();
              if (colSearch && typeof colSearch === "string" && colSearch.trim() !== "") {
                columnsSearch.push({ index: this.index(), value: colSearch });
              }
            });
          } catch (e) {
            console.warn("Column search read failed for dropdown search", e);
          }
        }

        const keyBase = `${columnIndex}:${getCurrentDatasetKey()}:${term}`;
        __dropdownSearchState[keyBase] = __dropdownSearchState[keyBase] || {
          page: 1,
          loading: false,
          done: false,
          seen: new Set(),
          requestId: 0,
          controller: null,
          pageCache: new Map() // page -> { values, hasMore }
        };
        const state = __dropdownSearchState[keyBase];
        const myRequestId = ++state.requestId;

        // Abort any in-flight request for this term
        try { state.controller?.abort?.(); } catch (_) { }
        state.controller = new AbortController();

        // Reset UI for new term
        state.page = 1;
        state.done = false;
        state.seen = new Set();
        $options.off("scroll.remoteSearch");
        $options.empty();
        $options.append(
          `<label><input type="checkbox" class="filter-option" data-column="${columnIndex}" value="" ${currentSelections.includes("") ? "checked" : ""
          }> All</label>`
        );

        // Re-inject previously selected items at the top so they stay visible & checked
        // even when they don't appear in the current search term's results
        const _prevSelected = currentSelections.filter(v => v !== '');
        _prevSelected.forEach(v => {
          const raw = encodeURIComponent(v);
          const norm = v.replace(/\s+/g, ' ').toLowerCase();
          if (!$options.find(`.filter-option[data-column="${columnIndex}"][data-raw="${raw}"]`).length) {
            $options.append(
              `<label><input type="checkbox" class="filter-option" data-column="${columnIndex}" data-raw="${raw}" value="${escapeHtml(v)}" checked> ${escapeHtml(v)}</label>`
            );
            state.seen.add(norm); // prevent duplicate when server returns it
          }
        });

        const fetchPage = async () => {
          if (state.loading || state.done) return;
          state.loading = true;
          try {
            // Cache hit
            if (state.pageCache.has(state.page)) {
              const cached = state.pageCache.get(state.page);
              const values = Array.isArray(cached.values) ? cached.values : [];
              for (const vRaw of values) {
                const v = String(vRaw).replace(/<[^>]*>/g, "").trim();
                if (!v) continue;
                const norm = v.replace(/\s+/g, " ").toLowerCase();
                if (state.seen.has(norm)) continue;
                state.seen.add(norm);
                const raw = encodeURIComponent(v);
                const isSelected = currentSelections.includes(v) || currentSelections.includes(decodeURIComponent(raw));
                if ($options.find(`.filter-option[data-column="${columnIndex}"][data-raw="${raw}"]`).length === 0) {
                  $options.append(
                    `<label><input type="checkbox" class="filter-option" data-column="${columnIndex}" data-raw="${raw}" value="${escapeHtml(
                      v
                    )}" ${isSelected ? "checked" : ""}> ${escapeHtml(v)}</label>`
                  );
                }
              }
              if (!cached.hasMore || values.length === 0) state.done = true;
              else state.page += 1;
              return;
            }

            const queryParams = new URLSearchParams({
              get_unique_values: "1",
              columnIndex: String(columnIndex),
              q: term,
              page: String(state.page),
              perPage: "100",
              searchQuery: currentSearch || "",
              managerToggle:
                managerToggle && managerToggle.dataset.selfLock !== "1" && managerToggle.checked ? "1" : "0",
              filter: "",
              order_by: "created_at",
              order_dir: "desc"
            });

            if (filterUserParam) queryParams.set("filterUser", filterUserParam);
            if (managerViewParam) queryParams.set("managerView", managerViewParam);
            if (Object.keys(combinedFilters).length > 0) {
              queryParams.set("multiFilters", JSON.stringify(combinedFilters));
            }
            columnsSearch.forEach(({ index, value }) => {
              queryParams.append(`columns[${index}][search][value]`, value);
            });

            const url = `update_status.php?${queryParams.toString()}`;
            const resp = await fetch(url, { method: "GET", signal: state.controller.signal });
            if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
            const json = await resp.json();

            // Ignore out-of-date responses
            if (state.requestId !== myRequestId) return;

            const values = Array.isArray(json.values) ? json.values : [];
            state.pageCache.set(state.page, { values, hasMore: !!json.hasMore });
            for (const vRaw of values) {
              const v = String(vRaw).replace(/<[^>]*>/g, "").trim();
              if (!v) continue;
              const norm = v.replace(/\s+/g, " ").toLowerCase();
              if (state.seen.has(norm)) continue;
              state.seen.add(norm);

              const raw = encodeURIComponent(v);
              const isSelected = currentSelections.includes(v) || currentSelections.includes(decodeURIComponent(raw));
              if ($options.find(`.filter-option[data-column="${columnIndex}"][data-raw="${raw}"]`).length === 0) {
                $options.append(
                  `<label><input type="checkbox" class="filter-option" data-column="${columnIndex}" data-raw="${raw}" value="${escapeHtml(
                    v
                  )}" ${isSelected ? "checked" : ""}> ${escapeHtml(v)}</label>`
                );
              }
            }

            if (!json.hasMore || values.length === 0) {
              state.done = true;
            } else {
              state.page += 1;
            }
          } catch (err) {
            if (err && (err.name === "AbortError")) return;
            console.error("Remote dropdown search failed:", err);
            state.done = true;
          } finally {
            state.loading = false;
          }
        };

        await fetchPage();

        // Paginate within search results on scroll
        $options.off("scroll.remoteSearch").on("scroll.remoteSearch", function () {
          const el = this;
          if (el.scrollTop + el.clientHeight >= el.scrollHeight - 10) {
            fetchPage();
          }
        });
      }

      $(document).on("input", ".filter-search-input", function () {
        const $input = $(this);
        const $header = $input.closest(".filter-header");
        const term = ($input.val() || "").toString().trim();

        // Mark this dropdown as active when user types in search
        window.activeDropdownHeader = $header[0];

        clearTimeout($input.data("remoteSearchTimer"));
        $input.data(
          "remoteSearchTimer",
          setTimeout(() => {
            if (!term) {
              // Restore default (lazy-loaded) list
              populateFilterDropdown($header).catch((err) => console.error("Error repopulating dropdown:", err));
              return;
            }
            remoteSearchDropdownOptions($header, term);
          }, 150)
        );
      });

      // ==========================================================
      // Filter Modal: Typeahead suggestions for all filter inputs
      // ==========================================================
      function initLeadFilterSuggestions() {
        const $inputs = $(".lead-filter-suggest");
        if (!$inputs.length) return;

        const dropdown = document.createElement("div");
        dropdown.className = "lead-suggest-dropdown";
        dropdown.innerHTML = `
          <div class="lead-suggest-header" style="display: flex; justify-content: space-between; align-items: center; padding: 8px 12px; border-bottom: 1px solid #eee; background: #f9fafb; display: none;">
            <span style="font-size: 12px; color: #666;">Select options</span>
            <button class="lead-suggest-close" style="background: none; border: none; font-size: 18px; cursor: pointer; color: #999; padding: 0; width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; line-height: 1; transition: color 0.2s;" title="Close">&times;</button>
          </div>
          <div class="lead-suggest-search-wrapper" style="padding: 8px; border-bottom: 1px solid #e5e7eb;">
            <input type="search" class="lead-suggest-search-input" placeholder="Search..." style="width: 100%; border: 1px solid #cbd5e1; border-radius: 4px; padding: 6px 12px; outline: none; font-size: 14px; box-sizing: border-box;" autocomplete="off" />
          </div>
          <div class="lead-suggest-list"></div>
          <div class="lead-suggest-footer" style="display:none;">Loading...</div>
        `;
        document.body.appendChild(dropdown);
        dropdown.style.display = "none";
        const listEl = dropdown.querySelector(".lead-suggest-list");
        const footerEl = dropdown.querySelector(".lead-suggest-footer");
        const closeBtn = dropdown.querySelector(".lead-suggest-close");
        const searchInput = dropdown.querySelector(".lead-suggest-search-input");

        // Close button handler
        if (closeBtn) {
          closeBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            hideDropdown();
          });
          closeBtn.addEventListener('mouseenter', function () {
            this.style.color = '#333';
          });
          closeBtn.addEventListener('mouseleave', function () {
            this.style.color = '#999';
          });
        }

        const state = {
          openFor: null,
          fieldKey: "",
          term: "",
          page: 1,
          loading: false,
          done: false,
          activeIndex: -1,
          seen: new Set(),
          controller: null,
          cache: new Map() // `${datasetKey}|${fieldKey}|${term}|${page}` -> { values, hasMore }
        };

        // Pre-warm: fetch first 5 suggestions for each field once the filter modal opens,
        // so focus shows results instantly (no network wait).
        const prewarmFirstPage = async (fieldKey) => {
          const datasetKey = getCurrentDatasetKey ? getCurrentDatasetKey() : "default";
          const cacheKey = `${datasetKey}|${fieldKey}||1`; // empty term, page 1
          if (state.cache.has(cacheKey)) return;

          try {
            const currentSearch = table && table.search ? table.search() : "";
            const combinedFilters = { ...(window.multiFilters || {}) };
            if (fieldKey && combinedFilters[fieldKey]) delete combinedFilters[fieldKey];

            const queryParams = new URLSearchParams({
              get_unique_values: "1",
              fieldKey,
              q: "",
              page: "1",
              // Prewarm a scrollable list for empty input, or all for budget (only 12 options)
              perPage: fieldKey === 'budget' ? "200" : "20",
              searchQuery: currentSearch || "",
              managerToggle:
                managerToggle && managerToggle.dataset.selfLock !== "1" && managerToggle.checked ? "1" : "0",
              filter: ""
            });
            if (filterUserParam) queryParams.set("filterUser", filterUserParam);
            if (managerViewParam) queryParams.set("managerView", managerViewParam);
            if (Object.keys(combinedFilters).length > 0) {
              queryParams.set("multiFilters", JSON.stringify(combinedFilters));
            }

            const url = `update_status.php?${queryParams.toString()}`;
            const resp = await fetch(url, { method: "GET" });
            if (!resp.ok) return;
            const json = await resp.json();
            const values = Array.isArray(json.values) ? json.values : [];
            state.cache.set(cacheKey, { values, hasMore: !!json.hasMore });
          } catch (_) {
            // ignore prewarm errors
          }
        };

        const prewarmAll = () => {
          const seenKeys = new Set();
          $inputs.each(function () {
            const key = ($(this).data("fieldKey") || "").toString().trim();
            if (!key || seenKeys.has(key)) return;
            seenKeys.add(key);
            // Fire and forget
            prewarmFirstPage(key);
          });
        };

        const positionDropdown = (input) => {
          const rect = input.getBoundingClientRect();
          const width = Math.max(260, rect.width);
          dropdown.style.width = `${width}px`;
          dropdown.style.left = `${rect.left + window.scrollX}px`;

          const spaceBelow = window.innerHeight - rect.bottom;
          const spaceAbove = rect.top;
          const estimatedHeight = 310;

          if (spaceBelow < estimatedHeight && spaceAbove > spaceBelow) {
            dropdown.style.top = `${rect.top + window.scrollY - 6}px`;
            dropdown.style.transform = `translateY(-100%)`;
          } else {
            dropdown.style.top = `${rect.bottom + window.scrollY + 6}px`;
            dropdown.style.transform = `none`;
          }
        };

        const hideDropdown = () => {
          dropdown.style.display = "none";
          state.openFor = null;
          state.activeIndex = -1;
          state.seen = new Set();
          try { state.controller?.abort?.(); } catch (_) { }
          state.controller = null;
        };

        const setFooter = (text, show) => {
          if (!footerEl) return;
          footerEl.textContent = text || "";
          footerEl.style.display = show ? "block" : "none";
        };

        const clearList = () => {
          listEl.innerHTML = "";
          state.activeIndex = -1;
          state.seen = new Set();
        };

        const renderItems = (values) => {
          for (const vRaw of values) {
            const v = String(vRaw || "").replace(/<[^>]*>/g, "").trim();
            if (!v) continue;
            const norm = v.replace(/\s+/g, " ").toLowerCase();
            if (state.seen.has(norm)) continue;
            state.seen.add(norm);

            const item = document.createElement("div");
            item.className = "lead-suggest-item";

            // Check if this value is already selected for multi-select inputs
            let isItemSelected = false;
            if (state.openFor && $(state.openFor).hasClass('multi-select-input')) {
              const inputId = $(state.openFor).attr('id');
              if (window.multiSelectValues &&
                window.multiSelectValues[inputId] &&
                window.multiSelectValues[inputId].includes(v)) {
                isItemSelected = true;
                item.classList.add('selected');
              }
            }

            if (state.openFor && $(state.openFor).hasClass('multi-select-input')) {
              item.innerHTML = `<div style="display:flex; justify-content:space-between; align-items:center; width:100%;">
                    <span>${v}</span>
                    <i class="fas fa-check check-icon" style="color:#0ea5e9; font-size:14px; display:${isItemSelected ? 'inline-block' : 'none'};"></i>
                </div>`;
            } else {
              item.textContent = v;
            }

            item.addEventListener("mousedown", (e) => {
              e.preventDefault(); // keep focus
              e.stopPropagation(); // prevent global close handler from firing
              if (state.openFor) {
                const $input = $(state.openFor);
                const inputId = $input.attr('id');

                // Check if this is a multi-select input
                if ($input.hasClass('multi-select-input')) {
                  if (!window.multiSelectValues) window.multiSelectValues = {};
                  if (!window.multiSelectValues[inputId]) {
                    window.multiSelectValues[inputId] = [];
                  }

                  // Check if already selected - if so, remove it
                  const isSelected = window.multiSelectValues[inputId].includes(v);
                  if (isSelected) {
                    // Remove the selected value
                    const index = window.multiSelectValues[inputId].indexOf(v);
                    window.removeMultiSelectTag(inputId, index);
                    // Update the item appearance
                    item.classList.remove('selected');
                    const icon = item.querySelector('.check-icon');
                    if (icon) icon.style.display = 'none';
                  } else {
                    // Add as tag
                    window.addMultiSelectTag(inputId, v);
                    // Update the item appearance
                    item.classList.add('selected');
                    const icon = item.querySelector('.check-icon');
                    if (icon) icon.style.display = 'inline-block';
                  }

                  // Keep dropdown open and keep focus on searchInput
                  const searchInput = dropdown.querySelector('.lead-suggest-search-input');
                  if (searchInput) searchInput.focus();
                  return; // Exit early to keep dropdown open
                } else {
                  // Original behavior for non-multi-select inputs
                  state.openFor.value = v;
                  $(state.openFor).trigger("change");
                  hideDropdown();
                }
              } else {
                // If no input is focused, close dropdown
                hideDropdown();
              }
            });
            listEl.appendChild(item);
          }
        };

        const fetchSuggestions = async (reset) => {
          if (!state.openFor) return;
          if (state.loading || state.done) return;
          state.loading = true;
          setFooter("Loading...", true);

          const datasetKey = getCurrentDatasetKey ? getCurrentDatasetKey() : "default";
          const cacheKey = `${datasetKey}|${state.fieldKey}|${state.term}|${state.page}`;
          try {
            if (reset) {
              clearList();
              state.page = 1;
              state.done = false;
            }

            // Cache hit
            if (state.cache.has(cacheKey)) {
              const cached = state.cache.get(cacheKey);
              renderItems(cached.values || []);
              state.done = !cached.hasMore;
              if (!state.done) state.page += 1;
              setFooter(state.done ? "End of results" : "", false);
              state.loading = false;
              return;
            }

            try { state.controller?.abort?.(); } catch (_) { }
            state.controller = new AbortController();

            const currentSearch = table && table.search ? table.search() : "";
            const combinedFilters = { ...(window.multiFilters || {}) };
            // Do not self-filter the same field while suggesting values
            if (state.fieldKey && combinedFilters[state.fieldKey]) {
              delete combinedFilters[state.fieldKey];
            }

            const queryParams = new URLSearchParams({
              get_unique_values: "1",
              fieldKey: state.fieldKey,
              q: state.term,
              page: String(state.page),
              // If input is empty, show a bigger initial list (scrollable).
              // If user types, keep it small for snappy search.
              // For budget field, load all options (only 12 options)
              perPage: state.fieldKey === 'budget' ? "200" : (state.term ? "5" : "20"),
              searchQuery: currentSearch || "",
              managerToggle:
                managerToggle && managerToggle.dataset.selfLock !== "1" && managerToggle.checked ? "1" : "0",
              filter: ""
            });

            if (filterUserParam) queryParams.set("filterUser", filterUserParam);
            if (managerViewParam) queryParams.set("managerView", managerViewParam);
            if (Object.keys(combinedFilters).length > 0) {
              queryParams.set("multiFilters", JSON.stringify(combinedFilters));
            }

            const url = `update_status.php?${queryParams.toString()}`;
            const resp = await fetch(url, { method: "GET", signal: state.controller.signal });
            if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
            const json = await resp.json();
            const values = Array.isArray(json.values) ? json.values : [];
            const hasMore = !!json.hasMore;
            state.cache.set(cacheKey, { values, hasMore });

            renderItems(values);
            state.done = !hasMore || values.length === 0;
            if (!state.done) state.page += 1;
            setFooter(state.done ? "End of results" : "", false);
          } catch (err) {
            if (err && err.name === "AbortError") {
              // ignore
            } else {
              console.error("Suggestion fetch failed:", err);
            }
            setFooter("", false);
          } finally {
            state.loading = false;
          }
        };

        // Scroll pagination
        listEl.addEventListener("scroll", () => {
          if (state.loading || state.done) return;
          if (listEl.scrollTop + listEl.clientHeight >= listEl.scrollHeight - 10) {
            fetchSuggestions(false);
          }
        });

        // Global close handlers
        document.addEventListener("mousedown", (e) => {
          // Don't close if clicking inside dropdown (including close button)
          if (dropdown.contains(e.target)) {
            return;
          }
          // Don't close if clicking on the input field or its container
          if (state.openFor) {
            const $container = $(state.openFor).closest(".multi-select-container");
            if (e.target === state.openFor || ($container.length && $container[0].contains(e.target))) {
              return;
            }
          }
          // Close dropdown for clicks outside
          hideDropdown();
        });
        window.addEventListener("scroll", () => { if (state.openFor) positionDropdown(state.openFor); }, true);
        window.addEventListener("resize", () => { if (state.openFor) positionDropdown(state.openFor); });

        const highlight = (idx) => {
          const items = Array.from(listEl.querySelectorAll(".lead-suggest-item"));
          items.forEach((el, i) => el.classList.toggle("active", i === idx));
          state.activeIndex = idx;
          if (idx >= 0 && items[idx]) {
            const el = items[idx];
            const top = el.offsetTop;
            const bottom = top + el.offsetHeight;
            if (top < listEl.scrollTop) listEl.scrollTop = top;
            else if (bottom > listEl.scrollTop + listEl.clientHeight) listEl.scrollTop = bottom - listEl.clientHeight;
          }
        };

        // Fix: searchInput events should be attached once, outside of the individual input loop
        searchInput.addEventListener("input", () => {
          if (!state.openFor) return;
          if (!dropdown.style.display || dropdown.style.display === "none") {
            dropdown.style.display = "flex";
          }
          state.term = searchInput.value.trim();
          state.page = 1;
          state.done = false;
          clearList();
          positionDropdown(state.openFor);
          const $input = $(state.openFor);
          clearTimeout($input.data("suggestTimer"));
          $input.data("suggestTimer", setTimeout(() => fetchSuggestions(true), 120));
        });

        searchInput.addEventListener("keydown", (e) => {
          if (!state.openFor) return;
          if (dropdown.style.display === "none") return;
          const items = Array.from(listEl.querySelectorAll(".lead-suggest-item"));
          if (e.key === "Escape") {
            hideDropdown();
            return;
          }
          if (e.key === "ArrowDown") {
            e.preventDefault();
            if (!items.length) return;
            highlight(Math.min(items.length - 1, state.activeIndex + 1));
            return;
          }
          if (e.key === "ArrowUp") {
            e.preventDefault();
            if (!items.length) return;
            highlight(Math.max(0, state.activeIndex - 1));
            return;
          }
          if (e.key === "Enter") {
            // Prevent form submission if enter is pressed inside search
            e.preventDefault();
            if (state.activeIndex >= 0 && items[state.activeIndex]) {
              items[state.activeIndex].dispatchEvent(new MouseEvent("mousedown", { bubbles: true }));
            }
          }
        });

        $inputs.each(function () {
          const input = this;
          const $input = $(input);
          const fieldKey = ($input.data("fieldKey") || "").toString().trim();
          if (!fieldKey) return;

          const schedule = (reset) => {
            clearTimeout($input.data("suggestTimer"));
            $input.data("suggestTimer", setTimeout(() => fetchSuggestions(reset), 120));
          };

          const $container = $input.closest(".multi-select-container");
          const $clickTarget = $container.length ? $container : $input;
          $clickTarget.on("click", (e) => {
            if ($(e.target).closest(".remove-tag").length) return;
            if (state.openFor === input && dropdown.style.display !== "none") {
              hideDropdown();
              return;
            }
            if (state.openFor && state.openFor !== input) hideDropdown();
            state.openFor = input;
            state.fieldKey = fieldKey;
            // The search should be empty when first opened, showing original options
            searchInput.value = "";
            state.term = "";
            state.page = 1;
            state.done = false;
            state.loading = false;
            clearList();
            dropdown.style.display = "flex";
            positionDropdown(state.openFor);
            // Focus the search input inside the dropdown
            setTimeout(() => searchInput.focus(), 10);

            // Instant paint: use prewarmed first page if available (empty term)
            if (!state.term) {
              const datasetKey = getCurrentDatasetKey ? getCurrentDatasetKey() : "default";
              const ck = `${datasetKey}|${fieldKey}||1`;
              if (state.cache.has(ck)) {
                const cached = state.cache.get(ck);
                renderItems(cached.values || []);
                state.done = !cached.hasMore;
                state.page = state.done ? 1 : 2;
                setFooter(state.done ? "End of results" : "", false);
                // Don't block UI; still refresh silently in background
                schedule(false);
                return;
              }
            }
            schedule(true);
          });
        });

        // Prewarm as soon as filter modal opens
        $(document).on("click", ".filter-btn-mobile, .dt-button.filter-btn", function () {
          setTimeout(prewarmAll, 0);
        });
      }

      // init once
      setTimeout(initLeadFilterSuggestions, 0);

      // ==========================================================
      // Multi-select functionality for filter modal inputs
      // ==========================================================
      function initMultiSelectFilters() {
        // Store selected values for each field
        window.multiSelectValues = window.multiSelectValues || {};

        // Initialize multi-select for all filter inputs (including budget)
        $('.multi-select-input').each(function () {
          const $input = $(this);
          const inputId = $input.attr('id');
          const fieldKey = $input.data('field-key');
          const $container = $input.closest('.multi-select-container');
          const $tagsContainer = $container.find('.multi-select-tags');

          // Initialize empty array if not exists
          if (!window.multiSelectValues[inputId]) {
            window.multiSelectValues[inputId] = [];
          }

          // Load existing values from multiFilters if available
          if (window.multiFilters && window.multiFilters[fieldKey]) {
            const existingValue = window.multiFilters[fieldKey];

            if (Array.isArray(existingValue)) {
              window.multiSelectValues[inputId] = existingValue
                .map(v => (v !== undefined && v !== null ? v.toString().trim() : ''))
                .filter(v => v);
            } else if (typeof existingValue === 'string') {
              let parsedArray = null;
              try {
                const parsed = JSON.parse(existingValue);
                if (Array.isArray(parsed)) parsedArray = parsed;
              } catch (_) {
                // Ignore parse errors and fall back to string handling
              }

              if (parsedArray) {
                window.multiSelectValues[inputId] = parsedArray
                  .map(v => (v !== undefined && v !== null ? v.toString().trim() : ''))
                  .filter(v => v);
              } else if (fieldKey !== 'assign_project_name' && existingValue.includes(',')) {
                window.multiSelectValues[inputId] = existingValue.split(',').map(v => v.trim()).filter(v => v);
              } else if (existingValue.trim()) {
                window.multiSelectValues[inputId] = [existingValue.trim()];
              }
            }
          }

          // Render existing tags
          renderTags(inputId, $tagsContainer);

          // Handle Enter key to add current input value as tag
          $input.on('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
              e.preventDefault();
              const value = $(this).val().trim();
              if (value && !window.multiSelectValues[inputId].includes(value)) {
                addTag(inputId, value, $tagsContainer);
                $(this).val('');
              }
            }
            // Backspace on empty input - removed as per user request
            // No longer removes tags when input is empty
          });


        });
      }

      // Make functions globally accessible
      window.addMultiSelectTag = function (inputId, value) {
        if (!window.multiSelectValues) window.multiSelectValues = {};
        if (!window.multiSelectValues[inputId]) {
          window.multiSelectValues[inputId] = [];
        }
        if (!window.multiSelectValues[inputId].includes(value)) {
          window.multiSelectValues[inputId].push(value);
          const $input = $('#' + inputId);
          const $container = $input.closest('.multi-select-container');
          const $tagsContainer = $container.find('.multi-select-tags');
          window.renderMultiSelectTags(inputId, $tagsContainer);
        }
      };

      window.removeMultiSelectTag = function (inputId, index) {
        if (window.multiSelectValues && window.multiSelectValues[inputId] && window.multiSelectValues[inputId][index]) {
          const removedValue = window.multiSelectValues[inputId][index];
          window.multiSelectValues[inputId].splice(index, 1);
          const $input = $('#' + inputId);
          const $container = $input.closest('.multi-select-container');
          const $tagsContainer = $container.find('.multi-select-tags');
          window.renderMultiSelectTags(inputId, $tagsContainer);

          // Update dropdown items if dropdown is open for this input
          const dropdown = document.querySelector('.lead-suggest-dropdown');
          if (dropdown && dropdown.style.display !== 'none') {
            const items = dropdown.querySelectorAll('.lead-suggest-item');
            items.forEach(item => {
              if (item.textContent.trim() === removedValue) {
                item.classList.remove('selected');
              }
            });
          }
        }
      };

      window.renderMultiSelectTags = function (inputId, $tagsContainer) {
        if (!$tagsContainer || !$tagsContainer.length) {
          const $input = $('#' + inputId);
          $tagsContainer = $input.closest('.multi-select-container').find('.multi-select-tags');
        }
        $tagsContainer.hide();
        const $input = $('#' + inputId);
        $input.closest('.multi-select-container').css('position', 'relative');
        $input.css({
          'cursor': 'pointer',
          'white-space': 'nowrap',
          'overflow': 'hidden',
          'text-overflow': 'ellipsis',
          'padding-right': '30px'
        });
        $input.prop('readonly', true);

        // Add a dropdown arrow icon
        let $arrow = $input.closest('.multi-select-container').find('.dropdown-arrow-icon');
        if ($arrow.length === 0) {
          $arrow = $('<i class="fas fa-caret-down dropdown-arrow-icon" style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); color: #6b7280; pointer-events: none;"></i>');
          $input.after($arrow);
        }

        const values = (window.multiSelectValues && window.multiSelectValues[inputId]) || [];

        let displayValues = [];
        values.forEach((value) => {
          let displayValue = value;
          if (inputId === 'isolatedFilterBudget') {
            const budgetMap = {
              '4000000-5000000': '40,00,000 - 50,00,000',
              '5000000-6000000': '50,00,000 - 60,00,000',
              '6000000-7000000': '60,00,000 - 70,00,000',
              '7000000-8000000': '70,00,000 - 80,00,000',
              '8000000-9000000': '80,00,000 - 90,00,000',
              '9000000-10000000': '90,00,000 - 1,00,00,000',
              '10000000-20000000': '1,00,00,000 - 2,00,00,000',
              '20000000-30000000': '2,00,00,000 - 3,00,00,000',
              '30000000-40000000': '3,00,00,000 - 4,00,00,000',
              '40000000-50000000': '4,00,00,000 - 5,00,00,000',
              '50000000-60000000': '5,00,00,000 - 6,00,00,000',
              '60000000-70000000': '6,00,00,000 - 7,00,00,000'
            };
            displayValue = budgetMap[value] || value;
          }
          displayValues.push(displayValue);
        });

        if (displayValues.length > 0) {
          $input.val(displayValues.join(', '));
          $input.attr('title', displayValues.join(', '));
        } else {
          $input.val('');
          $input.attr('title', '');
          $input.attr('placeholder', 'Search & Select');
        }
      };

      function addTag(inputId, value, $tagsContainer) {
        window.addMultiSelectTag(inputId, value);
      }

      function removeTag(inputId, index, $tagsContainer) {
        window.removeMultiSelectTag(inputId, index);
      }

      function renderTags(inputId, $tagsContainer) {
        window.renderMultiSelectTags(inputId, $tagsContainer);
      }

      // Initialize multi-select when modal opens
      function initializeMultiSelectOnModalOpen() {
        if ($('#filterModal').is(':visible')) {
          setTimeout(() => {
            initMultiSelectFilters();
          }, 150);
        }
      }

      $(document).on('click', '.filter-btn-mobile, .dt-button.filter-btn', function () {
        initializeMultiSelectOnModalOpen();
      });

      // Watch for modal visibility changes
      const modalObserver = new MutationObserver(function (mutations) {
        mutations.forEach(function (mutation) {
          if (mutation.type === 'attributes' && mutation.attributeName === 'style') {
            const modal = $('#filterModal');
            if (modal.is(':visible')) {
              initializeMultiSelectOnModalOpen();
            }
          }
        });
      });

      // Observe the filter modal
      const filterModal = document.getElementById('filterModal');
      if (filterModal) {
        modalObserver.observe(filterModal, {
          attributes: true,
          attributeFilter: ['style']
        });
      }

      // Also initialize on page load if modal is already visible
      if ($('#filterModal').is(':visible')) {
        setTimeout(initMultiSelectFilters, 100);
      }

      // Handle filter option selection - only update UI, don't apply filter yet
      $(document).on("change", ".filter-option", function () {
        const columnIndex = parseInt($(this).data("column"));
        const $filterOptions = $(this).closest(".filter-options");
        const $header = $(this).closest('.filter-header');

        // If "All" is checked, uncheck others
        if ($(this).val() === "") {
          if ($(this).is(":checked")) {
            $filterOptions.find(".filter-option").not(this).prop("checked", false);
          }
        } else {
          // If any specific option is checked, uncheck "All"
          $filterOptions.find('.filter-option[value=""]').prop("checked", false);
        }
      });

      // Handle Apply button click - apply filters only when Apply is clicked
      $(document).on("click", ".filter-apply-btn", function () {
        const columnIndex = parseInt($(this).data("column"));
        const $header = $(this).closest('.filter-header');
        const $filterOptions = $header.find(".filter-options");
        const filterKey = getFilterKeyByColumnIndex(columnIndex);

        // Get all checked values except "All"
        const checkedValues = [];
        const rawCheckedValues = [];
        $filterOptions.find(".filter-option:checked").each(function () {
          if ($(this).val() !== "") {
            checkedValues.push($(this).val());
            const rawAttr = $(this).attr('data-raw');
            const rawVal = rawAttr ? decodeURIComponent(rawAttr) : $(this).val();
            rawCheckedValues.push(rawVal);
          }
        });

        // OPTIMIZED: For ID (column 3) and Created At (column 4), use column search directly (faster and more reliable)
        // For other columns, use headerDropdownFilters -> multiFilters approach
        if (checkedValues.length > 0) {
          if (columnIndex === 3 || columnIndex === 4) {
            // ID and Created At: Use column search directly for instant filtering
            const searchValue = rawCheckedValues.join('|'); // Pipe separator for multiple values
            if (table && $.fn.DataTable.isDataTable("#leadsTable")) {
              // Set column search BEFORE updating headerDropdownFilters
              table.column(columnIndex).search(searchValue, false, false);
            }
            // Also store in headerDropdownFilters for tag count calculations (fallback)
            if (filterKey) {
              // Preserve commas inside project names by keeping an array instead of CSV
              window.headerDropdownFilters[filterKey] = (filterKey === 'assign_project_name')
                ? [...rawCheckedValues]
                : rawCheckedValues.join(',');
            }
          } else {
            // Other columns: Use headerDropdownFilters -> multiFilters approach
            if (filterKey) {
              window.headerDropdownFilters[filterKey] = (filterKey === 'assign_project_name')
                ? [...rawCheckedValues]
                : rawCheckedValues.join(',');
            }
          }
        } else {
          // Clear filters
          if (columnIndex === 3 || columnIndex === 4) {
            // ID and Created At: Clear column search directly
            if (table && $.fn.DataTable.isDataTable("#leadsTable")) {
              table.column(columnIndex).search('', false, false);
            }
            if (filterKey) delete window.headerDropdownFilters[filterKey];
          } else {
            if (filterKey) delete window.headerDropdownFilters[filterKey];
          }
          $filterOptions.find('.filter-option[value=""]').prop("checked", true);
        }

        // OPTIMIZED: Clear filter cache FIRST, then update counts INSTANTLY, then draw table
        // This ensures tag counts update instantly and table filters correctly
        clearFilterStateCache();

        // OPTIMIZED: For ID and Created At, update counts AFTER column search is set but BEFORE draw
        // This prevents flickering and ensures counts reflect the new filter state
        if (columnIndex === 3 || columnIndex === 4) {
          // Update counts immediately (column search is already set)
          updateAllTagCounts();
          // Then draw table (will use the column search we just set)
          if (table && $.fn.DataTable.isDataTable("#leadsTable")) {
            table.draw(false); // false = don't reset pagination
          }
        } else {
          // For other columns, update counts and draw normally
          updateAllTagCounts();
          if (table && $.fn.DataTable.isDataTable("#leadsTable")) {
            table.draw(false); // false = don't reset pagination
          }
        }

        // Invalidate and rebuild unique cache for new narrowed context
        invalidateUniqueValuesCache();
        // Debounce slightly to avoid multiple parallel builds
        clearTimeout(window.__rebuildUniqueCacheTimer);
        window.__rebuildUniqueCacheTimer = setTimeout(() => {
          buildUniqueValuesCache();
        }, 150);

        // Repopulate other header dropdowns to reflect cascading options
        // But avoid repopulating dropdowns that have active selections to prevent hiding user's choices
        $(".filter-header").each(function () {
          const $hdr = $(this);
          // Skip the header we just changed
          if ($hdr[0] !== $header[0]) {
            // Don't repopulate if this is the currently active dropdown
            const isActiveDropdown = window.activeDropdownHeader && $hdr[0] === window.activeDropdownHeader;
            // Only repopulate if this dropdown doesn't have any active selections
            // or if it's not currently visible (not being used by user)
            const hasActiveSelections = $hdr.find('.filter-option:checked[value!=""]').length > 0;
            const isVisible = $hdr.find('.filter-dropdown').is(':visible');

            // If dropdown is active, has selections, or is visible, preserve current state
            if (!isActiveDropdown && !(hasActiveSelections && isVisible)) {
              populateFilterDropdown($hdr).catch(err => {
                console.error('Error populating filter dropdown:', err);
              });
            }
          }
        });

        // Close dropdown after applying
        $header.find('.filter-dropdown').hide();
        window.activeDropdownHeader = null;

        // Update clear filters visibility
        setTimeout(() => {
          if (window.clearFiltersManager) {
            window.clearFiltersManager.updateVisibility();
          }
        }, 300);
      });

      // Handle Clear button click - clear filter for this column
      $(document).on("click", ".filter-clear-btn", function () {
        const columnIndex = parseInt($(this).data("column"));
        const $header = $(this).closest('.filter-header');
        const $filterOptions = $header.find(".filter-options");
        const filterKey = getFilterKeyByColumnIndex(columnIndex);

        // Clear all selections and check "All"
        $filterOptions.find(".filter-option").prop("checked", false);
        $filterOptions.find('.filter-option[value=""]').prop("checked", true);

        // Remove from headerDropdownFilters
        if (filterKey && window.headerDropdownFilters) {
          delete window.headerDropdownFilters[filterKey];
        }

        // OPTIMIZED: For ID (column 3) and Created At (column 4), clear DataTable column search
        if (table && $.fn.DataTable.isDataTable("#leadsTable")) {
          if (columnIndex === 3 || columnIndex === 4) {
            table.column(columnIndex).search('', false, false);
          }
        }

        // OPTIMIZED: Clear filter cache and update counts instantly
        clearFilterStateCache();
        updateAllTagCounts();

        // Trigger table redraw to remove filter
        if (table && $.fn.DataTable.isDataTable("#leadsTable")) {
          table.draw(false); // false = don't reset pagination
        }

        // Invalidate and rebuild unique cache
        invalidateUniqueValuesCache();
        clearTimeout(window.__rebuildUniqueCacheTimer);
        window.__rebuildUniqueCacheTimer = setTimeout(() => {
          buildUniqueValuesCache();
        }, 150);

        // Repopulate other header dropdowns to reflect cascading options
        $(".filter-header").each(function () {
          const $hdr = $(this);
          if ($hdr[0] !== $header[0]) {
            const isActiveDropdown = window.activeDropdownHeader && $hdr[0] === window.activeDropdownHeader;
            const hasActiveSelections = $hdr.find('.filter-option:checked[value!=""]').length > 0;
            const isVisible = $hdr.find('.filter-dropdown').is(':visible');

            if (!isActiveDropdown && !(hasActiveSelections && isVisible)) {
              populateFilterDropdown($hdr).catch(err => {
                console.error('Error populating filter dropdown:', err);
              });
            }
          }
        });

        // Close dropdown after clearing
        $header.find('.filter-dropdown').hide();
        window.activeDropdownHeader = null;

        // Update clear filters visibility
        setTimeout(() => {
          if (window.clearFiltersManager) {
            window.clearFiltersManager.updateVisibility();
          }
        }, 300);
      });


      async function fetchUsersForReassign() {
        try {
          const response = await fetch("update_status.php?get_users=1&all_users=1");
          if (!response.ok) throw new Error("Failed to fetch users");

          const options = await response.text();

          const $select = $("#reassignTo");
          $select.html(`
            <option value="" disabled selected>Select User</option>
            ${options}
        `);
        } catch (error) {
          console.error("Error fetching users:", error);
        }
      }

      function initializeAssignUserSelect2() {
        var posColors = {
          'P': { bg: '#7c3aed', color: '#fff' },
          'BH': { bg: '#dc2626', color: '#fff' },
          'M': { bg: '#16a34a', color: '#fff' },
          'TL': { bg: '#0d9488', color: '#fff' },
          'U': { bg: '#6b7280', color: '#fff' }
        };
        function makeBadge(option, isSelection) {
          if (!option.id) return option.text;
          var pos = $(option.element).data('position') || '';
          var col = posColors[pos];
          var name = $('<span>').text(option.text.replace(/\s*-\s*\S+$/, '').trim()).html();
          if (!col || !pos) return $('<span>' + name + '</span>');
          var sz = isSelection ? '26px;height:18px;padding:0 5px' : '28px;height:20px;padding:0 6px';
          var gap = isSelection ? '6px' : '8px';
          return $(
            '<span style="display:flex;align-items:center;gap:' + gap + ';">' +
            '<span style="display:inline-flex;align-items:center;justify-content:center;' +
            'min-width:' + sz + ';border-radius:4px;font-size:10px;font-weight:700;' +
            'letter-spacing:.5px;background:' + col.bg + ';color:' + col.color + ';flex-shrink:0;">' +
            pos +
            '</span>' +
            '<span>' + name + '</span>' +
            '</span>'
          );
        }
        $('#assignUser').select2({
          placeholder: "Select User",
          allowClear: false,
          width: '100%',
          dropdownParent: $('#reassignModal'),
          minimumResultsForSearch: 0,
          templateResult: function (o) { return makeBadge(o, false); },
          templateSelection: function (o) { return makeBadge(o, true); }
        });
      }

      // Initialize Select2 with badge templates at page load so both the
      // row-click path and openReassignModal path always show colored badges.
      initializeAssignUserSelect2();

      // Function to properly destroy assign user Select2 instance
      function destroyAssignUserSelect2() {
        if ($('#assignUser').hasClass('select2-hidden-accessible')) {
          $('#assignUser').select2('destroy');
        }
        $('#assignUser').val('');
      }

      // Function to open reassign modal
      function openReassignModal(leadId, leadName, currentAssignedUser = '', currentProject = '') {
        // Set lead details
        $("#reassignLeadName").text(leadName);
        $("#reassignLeadId").text(leadId);
        $("#reassignRowId").val(leadId);

        // Destroy any existing Select2 instances
        destroyAssignUserSelect2();

        // Load users and initialize Select2
        loadUsersIntoDropdown().then(() => {
          // Set current values if provided
          if (currentAssignedUser) {
            $("#assignUser").val(currentAssignedUser);
          }

          if (currentProject) {
            $("#projectName").val(currentProject);
          }

          // Initialize Select2
          initializeAssignUserSelect2();

          // Show modal
          $("body").css("overflow", "hidden");
          $("#reassignModal").addClass("active").show();
        });
      }


      function closeReassignModal() {
        // Destroy Select2 instances
        destroyAssignUserSelect2();

        // Hide modal
        $("body").css("overflow", "auto");
        $("#reassignModal").removeClass("active").hide();

        // Reset form
        $("#reassignForm")[0].reset();
        $("#responseMessage").hide();
      }

      function updateTableRow(rowId, status, notes, lead_identity) {
        // Ensure DataTable is initialized
        if (!table || !$.fn.DataTable.isDataTable("#leadsTable")) {
          console.error("DataTable not initialized");
          return;
        }

        console.log('Updating row with lead_identity:', lead_identity);

        // Helper: patch all visual elements on a <tr> jQuery object
        function patchRowDom($row) {
          if (!$row || !$row.length) return;

          // 1. Status badge
          const $badge = $row.find(".status-badge");
          if ($badge.length) {
            $badge.text(status).removeClass()
              .addClass("status-badge " + status.toLowerCase().replace(/\s+/g, "-"));
          }

          // 2. Hot/Warm/Cold identity icon
          const $leadName = $row.find(".lead-name, .lead-info");
          if ($leadName.length) {
            $leadName.find(".hot-icon, .warm-icon, .cold-icon").remove();
            if (lead_identity && lead_identity.trim() !== '') {
              const icons = {
                Hot: '<i class="fas fa-fire hot-icon" style="color:#ff4444;margin-left:5px;"></i>',
                Warm: '<i class="fas fa-sun warm-icon" style="color:#ffaa00;margin-left:5px;"></i>',
                Cold: '<i class="fas fa-snowflake cold-icon" style="color:#0088ff;margin-left:5px;"></i>'
              };
              if (icons[lead_identity]) $leadName.append(icons[lead_identity]);
            }
          }

          // 3. Last-touch status
          const $lastTouch = $row.find(".last-touch-status");
          if ($lastTouch.length) {
            $lastTouch.text("Touched today").removeClass("untouched over-week").addClass("recent");
          }
        }

        let foundViaApi = false;

        // Pass 1: DataTables API path (works for normally-loaded rows).
        // Lazily-appended rows can have aoData entries where _aData is not yet
        // initialised; DataTables throws internally before we can inspect the
        // value, so guard every this.data() call with try-catch.
        table.rows().every(function () {
          let rowData;
          try { rowData = this.data(); } catch (e) { return; } // skip broken lazy entries
          if (!rowData || rowData.id != rowId) return;

          rowData.status = status;
          if (lead_identity !== undefined && lead_identity !== null) {
            rowData.lead_identity = lead_identity;
          }
          try { this.data(rowData).invalidate(); } catch (e) { /* lazy row – DOM patch handles it */ }

          const rowNode = this.node();
          if (rowNode) patchRowDom($(rowNode));

          foundViaApi = true;
          return false; // stop iteration
        });

        // Pass 2: Direct DOM fallback for lazily-loaded rows missed by the API.
        if (!foundViaApi) {
          const $lazyRow = $("#leadsTable")
            .find('[data-id="' + rowId + '"]').first().closest("tr");
          patchRowDom($lazyRow);
        }

        // Skip draw() in lazy-scroll mode: server-side draw(false) fires a new
        // AJAX request that wipes all lazily-appended rows beyond the first batch.
        // The DOM is already patched above so no redraw is needed.
        if (!window._dtLazy) {
          table.draw(false);
        }
      }


      $(document).on("click", ".action-btn.status, .details-row .action-btn.status", function (e) {
        e.stopPropagation();
        e.preventDefault();

        const $btn = $(this);
        const $row = $btn.closest("tr").is(".details-row")
          ? $btn.closest("tr").prev("tr")
          : $btn.closest("tr");

        const leadId = $btn.data("id") || $row.find(".lead-id-hidden").text().trim();
        const leadName = $row.find(".lead-info h4").text().trim();
        const userUniqueId = $btn.data("userid") || $row.find(".assigned-lead").text().trim();

        // Always open with blank status so user picks a fresh value
        openStatusModal(leadId, leadName, '', '', userUniqueId);
      });

      // Function to open status modal with current status
      // Update the status modal opening function
      function openStatusModal(leadId, leadName, currentStatus, currentIdentity = '', userUniqueId = '') {
        // Clear previous validation states
        clearValidation();

        // IMPORTANT: Reset ALL form fields first before setting new values
        $('#statusForm')[0].reset();

        // Clear all Select2 dropdowns
        $('#newStatus').val('').change();
        $('#budget').val('').change();
        $('#leadIdentitySelect').val('').change();

        // Clear text inputs
        $('#preferredLocation').val('');
        $('#statusNotes').val('');

        // Clear date/time inputs
        $('#followUpDate').val('');
        $('#followUpTime').val('');

        // Set lead details
        $('#statusLeadName').text(leadName);
        $('#statusLeadId').text(leadId);
        $('#rowId').val(leadId);

        // Store userUniqueId in a hidden field or data attribute
        $('#statusModal').data('userUniqueId', userUniqueId);

        // Do NOT pre-select the current status — user should always pick a new one
        $('#newStatus').val('').change();

        // Reinitialize Select2 with proper configuration
        $('#newStatus').select2({
          placeholder: "Select Status",
          allowClear: false,
          width: '100%',
          dropdownParent: $('#statusModal'),
          minimumResultsForSearch: 0
        });

        $('#budget').select2({
          placeholder: "Select Budget Range",
          allowClear: true,
          width: '100%',
          dropdownParent: $('#statusModal'),
          minimumResultsForSearch: 0
        });

        // Initialize lead identity dropdown with same configuration


        $('#leadIdentitySelect').select2({
          placeholder: "Select Lead Identity",
          allowClear: true,  // Changed to true to allow clearing
          width: '100%',
          dropdownParent: $('#statusModal'),
          minimumResultsForSearch: -1
        });


        // Set current lead identity
        if (currentIdentity) {
          $('#leadIdentitySelect').val(currentIdentity);
          $('#leadIdentityValue').val(currentIdentity);
        }

        // Always start with no date/time fields — they appear when user picks a status
        toggleDateTimeFields('');

        // Reset all form fields to remove any error states
        $('#statusForm .form-group').removeClass('error success');
        $('#statusForm .error-message').text('').hide();

        // Show modal with higher z-index
        $('#statusModal').css('z-index', '10050').show();
        $('body').css('overflow', 'hidden');
      }



      $('#price_range').select2({
        placeholder: "Select Price Range",
        allowClear: false,
        width: '100%',
        dropdownParent: $('#statusModal'),
        minimumResultsForSearch: 0
      });



      // Function to close status modal
      function closeStatusModal() {
        console.log('🚪 closeStatusModal called');

        // Completely reset the form
        $('#statusForm')[0].reset();

        // Clear Select2 values
        $('#newStatus').val('').change();
        $('#budget').val('').change();
        $('#leadIdentitySelect').val('').change();

        // Clear text inputs
        $('#preferredLocation').val('');
        $('#statusNotes').val('');

        // Clear date/time inputs
        $('#followUpDate').val('');
        $('#followUpTime').val('');

        // Hide date/time fields if not applicable to new status
        $('#dateField').hide();
        $('#timeField').hide();

        $('#statusModal').hide();
        clearValidation();

        // Reset the submit button text
        const $submitBtn = $("#statusForm .submit-btn");
        $submitBtn.html('Update Status').prop('disabled', false);

        // Destroy Select2 to avoid conflicts
        if ($('#newStatus').hasClass('select2-hidden-accessible')) {
          $('#newStatus').select2('destroy');
        }
        if ($('#budget').hasClass('select2-hidden-accessible')) {
          $('#budget').select2('destroy');
        }

        let restored = false;

        // Check if we have an exact-time follow-up waiting - restore it first (higher priority)
        if (window.ExactTimeFollowupSystem && typeof window.ExactTimeFollowupSystem.restorePopup === 'function') {
          console.log('   Checking if exact-time popup should be restored...');
          restored = window.ExactTimeFollowupSystem.restorePopup() || false;
        }

        // If exact-time didn't restore, check if we have an overdue lead waiting
        if (!restored && window.OverdueLeadSystem && typeof window.OverdueLeadSystem.restorePopup === 'function') {
          console.log('   Checking if overdue popup should be restored...');
          restored = window.OverdueLeadSystem.restorePopup() || false;
        }

        // No popups were restored, restore body scrolling
        if (!restored) {
          console.log('   No popups restored, unlocking body scroll');
          document.body.style.removeProperty('overflow');
          document.body.style.removeProperty('position');
          document.body.style.removeProperty('width');
          document.body.style.removeProperty('top');
        }
      }


      function clearValidation() {
        // Remove all error/success classes from form groups
        $('.form-group').removeClass('error success');

        // Clear all error messages
        $('.error-message').text('').hide();

        // Remove any error styling from Select2 and regular inputs
        $('.select2-container').removeClass('error-state success');
        $('#statusForm input, #statusForm select, #statusForm textarea').css('border-color', '');
      }

      const fixStyle = `
.select2-container.error-state .select2-selection {
    border-color: #dc3545 !important;
}
.form-group.success .select2-container .select2-selection {
    border-color: #28a745 !important;
}
`;
      $('head').append(`<style>${fixStyle}</style>`);


      // Function to toggle date/time fields based on status
      function toggleDateTimeFields(status) {
        const statusRequiringDateTime = [
          'Pending', 'RNR', 'Call Back', 'Interested', 'EOI', 'Follow Up',
          'Fix Site Visit', 'Site Visit Done', 'VC Done', 'Converted', 'Re site visit', 'NQFTP', 'Not Connected'
        ];

        if (statusRequiringDateTime.includes(status)) {
          $('#dateField').show();
          $('#timeField').show();
        } else {
          $('#dateField').hide();
          $('#timeField').hide();
        }
      }

      // Initialize when document is ready
      $(document).ready(function () {
        // Initialize Select2 for status dropdown
        setupAddLeadValidation();

        // Disable the submit button initially
        $("#submitLead").prop("disabled", true);


        // Status change handler
        $('#newStatus').on('change', function () {
          const selectedStatus = $(this).val();
          toggleDateTimeFields(selectedStatus);
        });

        // Close modal handlers
        $('#closeStatusModal, #cancelStatusUpdate').on('click', closeStatusModal);

        // Close when clicking outside
        $('#statusModal').on('click', function (e) {
          if ($(e.target).hasClass('modal-overlay')) {
            closeStatusModal();
          }
        });

        // Close with Escape key
        $(document).on('keydown', function (e) {
          if (e.key === 'Escape' && $('#statusModal').is(':visible')) {
            closeStatusModal();
          }
        });

        // Status button click handler
        $(document).on("click", ".action-btn.status, .details-row .action-btn.status", function (e) {
          e.stopPropagation();
          e.preventDefault();

          const $btn = $(this);
          const $row = $btn.closest("tr").is(".details-row")
            ? $btn.closest("tr").prev("tr")
            : $btn.closest("tr");

          const leadId = $btn.data("id") || $row.find(".lead-id-hidden").text().trim();
          const leadName = $row.find(".lead-info h4").text().trim();
          const userUniqueId = $btn.data("userid") || $row.find(".assigned-lead").text().trim();

          // Always open with blank status so user picks a fresh value
          openStatusModal(leadId, leadName, '', '', userUniqueId);
        });

      });
      function validateStatusForm() {
        const selectedStatus = $("#newStatus").val();
        let isValid = true;

        // Clear previous errors
        $(".form-group").removeClass("error");
        $(".error-message").text("").hide();

        // Status validation (using Select2)
        if (!$("#newStatus").val()) {
          $("#newStatus").next('.select2-container').addClass('error-state');
          $("#statusError").text("Please select a status").show();
          isValid = false;
        } else {
          $("#newStatus").next('.select2-container').removeClass('error-state').addClass('success');
        }

        // Notes validation
        if (!$("#statusNotes").val().trim()) {
          $("#statusNotes").closest(".form-group").addClass("error");
          $("#statusNotes").closest(".form-group").find(".error-message")
            .text("Please enter notes").show();
          isValid = false;
        } else {
          $("#statusNotes").closest(".form-group").removeClass("error").addClass("success");
        }

        // Additional validation based on status
        const statusRequiringDateTime = [
          "Pending", "RNR", "Call Back", "Interested", "EOI", "Follow Up",
          "Fix Site Visit", "Site Visit Done", "VC Done", "Converted", "Re site visit", "NQFTP", "Not Connected"
        ];

        if (statusRequiringDateTime.includes(selectedStatus)) {
          // Date validation
          if (!$("#followUpDate").val()) {
            $("#followUpDate").closest(".form-group").addClass("error");
            $("#followUpDate").closest(".form-group").find(".error-message")
              .text("Please select a date").show();
            isValid = false;
          } else {
            $("#followUpDate").closest(".form-group").removeClass("error").addClass("success");
          }

          // Time validation
          if (!$("#followUpTime").val()) {
            $("#followUpTime").closest(".form-group").addClass("error");
            $("#followUpTime").closest(".form-group").find(".error-message")
              .text("Please select a time").show();
            isValid = false;
          } else {
            $("#followUpTime").closest(".form-group").removeClass("error").addClass("success");
          }
        }

        return isValid;
      }

      // Add individual field validation handlers
      $(document).on('change', '#followUpDate', function () {
        const selectedStatus = $("#newStatus").val();
        const statusRequiringDateTime = [
          "Pending", "RNR", "Call Back", "Interested", "EOI", "Follow Up",
          "Fix Site Visit", "Site Visit Done", "VC Done", "Converted", "Re site visit", "NQFTP", "Not Connected"
        ];

        if (statusRequiringDateTime.includes(selectedStatus)) {
          if (this.value) {
            $(this).closest(".form-group").removeClass("error").addClass("success");
            $(this).closest(".form-group").find(".error-message").text("");
          } else {
            $(this).closest(".form-group").addClass("error");
            $(this).closest(".form-group").find(".error-message")
              .text("Please select a date").show();
          }
        }
      });

      $(document).on('change', '#followUpTime', function () {
        const selectedStatus = $("#newStatus").val();
        const statusRequiringDateTime = [
          "Pending", "RNR", "Call Back", "Interested", "EOI", "Follow Up",
          "Fix Site Visit", "Site Visit Done", "VC Done", "Converted", "Re site visit", "NQFTP", "Not Connected"
        ];

        if (statusRequiringDateTime.includes(selectedStatus)) {
          if (this.value) {
            $(this).closest(".form-group").removeClass("error").addClass("success");
            $(this).closest(".form-group").find(".error-message").text("");
          } else {
            $(this).closest(".form-group").addClass("error");
            $(this).closest(".form-group").find(".error-message")
              .text("Please select a time").show();
          }
        }
      });

      $(document).on('input', '#statusNotes', function () {
        if (this.value.trim()) {
          $(this).closest(".form-group").removeClass("error").addClass("success");
          $(this).closest(".form-group").find(".error-message").text("");
        } else {
          $(this).closest(".form-group").addClass("error");
          $(this).closest(".form-group").find(".error-message")
            .text("Please enter notes").show();
        }
      });



      // Update the status form submission to include new fields
      $("#statusForm").on("submit", function (e) {
        e.preventDefault();

        // Prevent multiple submissions
        if (isStatusSubmitting) {
          return false;
        }

        isStatusSubmitting = true;

        // Validate the form
        if (!validateStatusForm()) {
          isStatusSubmitting = false;
          return false;
        }

        // Get the userUniqueId from the modal data
        const userUniqueId = $('#statusModal').data('userUniqueId');

        // Get the lead identity value
        const leadIdentityValue = $("#leadIdentitySelect").val() || $("#leadIdentityValue").val() || '';

        const formData = {
          update: true,
          rowId: $("#statusLeadId").text(),
          status: $("#newStatus").val(),
          notes: $("#statusNotes").val(),
          followUpDate: $("#followUpDate").val(),
          followUpTime: $("#followUpTime").val(),
          lead_identity: leadIdentityValue,
          budget: $("#budget").val(),
          location_status: $("#preferredLocation").val(),
          user_unique_id: userUniqueId // Add this to the form data
        };

        // Show loading state
        const $submitBtn = $("#statusForm .submit-btn");
        const originalText = $submitBtn.html();
        $submitBtn
          .html('<i class="fas fa-spinner fa-spin"></i> Updating...')
          .prop("disabled", true);

        // Send data to server
        fetch("update_status.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(formData),
        })
          .then(async (response) => {
            const status = response.status;
            const text = await response.text();              // always read text first
            console.log("update_status response status:", status);
            console.log("Raw server response:", text);       // <-- inspect this in console (paste here if you want)
            // try to parse JSON
            try {
              const data = JSON.parse(text);
              return { ok: response.ok, data, raw: text };
            } catch (err) {
              // Not JSON â€” throw a descriptive error containing the raw body
              const e = new Error("Server returned non-JSON response");
              e.raw = text;
              e.status = status;
              throw e;
            }
          })
          .then(({ ok, data }) => {
            if (data.status === "success") {
              updateTableRow(formData.rowId, formData.status, formData.notes, formData.lead_identity);
              showNotification("Status updated!", "success");
              closeStatusModal();
              if (typeof window.invalidateLeadCaches === "function") {
                window.invalidateLeadCaches(formData.rowId);
              }
              fetchHistory(formData.rowId, formData.user_unique_id, { forceRefresh: true });

              // Dispatch custom event for Today's Followup Popup integration
              console.log('📤 Preparing to dispatch leadStatusUpdated event...');
              console.log('   Lead ID:', formData.rowId);
              console.log('   New Status:', formData.status);

              const leadStatusEvent = new CustomEvent('leadStatusUpdated', {
                detail: {
                  leadId: formData.rowId,
                  newStatus: formData.status,
                  timestamp: new Date().toISOString()
                }
              });
              document.dispatchEvent(leadStatusEvent);
              console.log('✅ leadStatusUpdated event dispatched successfully');
            } else {
              showNotification(data.message || "Error updating status", "error");
              throw new Error(data.message || "Error updating status");
            }
          })
          .catch((error) => {
            console.error("Error (update_status):", error);
            // If server returned raw HTML, show it in console (helpful)
            if (error.raw) {
              console.error("Server returned (raw):", error.raw);
            }
            showNotification("Error updating status. Please check console or try again.", "error");
          })
          .finally(() => {
            isStatusSubmitting = false;
            // Use same selector used for initial button; your code used both "#statusForm .submit-btn" and "#statusSubmit"
            $("#statusForm .submit-btn").html('Update Status').prop('disabled', false);
          });
      });
      $(document).on('change', '#newStatus', function () {
        const selectedStatus = $(this).val();

        // Clear error state when a status is selected
        if (selectedStatus) {
          $(this).next('.select2-container').removeClass('error-state').addClass('success');
          $("#statusError").text("");

          // Show/hide date/time fields based on selected status
          toggleDateTimeFields(selectedStatus);
        }
      });

      $(document).on('change', '#leadIdentitySelect', function () {
        const selectedIdentity = $(this).val();
        $('#leadIdentityValue').val(selectedIdentity);
      });




      function loadUsers() {
        fetch("get_users.php")
          .then((res) => res.json())
          .then((users) => {
            $("#assignUser").empty();
            $("#assignUser").append('<option value="">Select user</option>');
            users.forEach((user) => {
              $("#assignUser").append(
                `<option value="${user.id}">${user.name}</option>`
              );
            });
          });
      }
      //   loadUsers();

      //ths is the reassign submit handler
      submitReassignBtn.addEventListener("click", function () {
        // Validate form first
        if (!validateReassignForm()) {
          return;
        }

        // Rest of your existing code for reassign...
        const rowId = reassignRowIdInput.value;
        const assignUser = assignUserInput.value;
        const projectName = projectNameInput.value;
        const includeHistory = document.getElementById('includeHistoryToggle').checked;

        if (!assignUser || !projectName) {
          responseMessage.innerHTML = `<div class="alert alert-danger">Please select a user and a project.</div>`;
          return;
        }

        // Show loading state
        const originalButtonText = submitReassignBtn.innerHTML;
        submitReassignBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        submitReassignBtn.disabled = true;

        const data = {
          reassign: true,
          reassignRowId: rowId,
          assignUser: assignUser,
          projectName: projectName,
          includeHistory: includeHistory // Add history preference to payload
        };

        fetch("update_status.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(data),
        })
          .then((response) => response.json())
          .then((data) => {
            if (data.status === "success") {
              // Show success message with history info
              const historyMessage = includeHistory ?
                ' with complete history.' :
                ' as a fresh lead.';
              responseMessage.innerHTML = `<div class="alert alert-success">
                ${data.message} Lead reassigned${historyMessage}
            </div>`;

              // Close modal and reset form
              $("#reassignModal").fadeOut();
              $("#reassignForm")[0].reset();
              table.ajax.reload(null, false); // Refresh table without resetting page

              // Show notification
              showNotification(`Lead reassigned successfully${historyMessage}`, 'success');

              if (typeof window.invalidateLeadCaches === "function") {
                window.invalidateLeadCaches(rowId);
              }
            } else {
              responseMessage.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
              showNotification(data.message, 'error');
            }

            // Close modal and reset
            $("#reassignModal").fadeOut();
            reassignForm.reset();
            reassignRowIdInput.value = "";
          })
          .catch((error) => {
            console.error("Error:", error);
            responseMessage.innerHTML = `<div class="alert alert-danger">Unexpected error. Please try again.</div>`;
            showNotification('Failed to reassign lead. Please try again.', 'error');
          })
          .finally(() => {
            // Reset button state
            submitReassignBtn.innerHTML = originalButtonText;
            submitReassignBtn.disabled = false;

            // Update UI data
            updateDataAndUI();
          });
      });

      // Show/hide follow-up fields based on status selection
      $("#newStatus").change(function () {
        const selectedStatus = $(this).val();

        // Get references to the field containers
        const $dateField = $("#dateField");
        const $timeField = $("#timeField");
        const $notesField = $("#notesField");
        const $identityDiv = $("#leadIdentityDiv");
        const $budgetField = $("#budgetField");
        const $locationField = $("#locationField");

        // Hide all date/time fields first
        $dateField.hide();
        $timeField.hide();

        // Always show notes field
        $notesField.show();

        // Update labels and show/hide fields based on status
        switch (selectedStatus) {
          case "Pending":
            $budgetField.show();
            $locationField.show();
            $dateField.find(".field-legend").text("Pending Date *");
            $timeField.find(".field-legend").text("Pending Time *");
            $dateField.show();
            $timeField.show();
            $identityDiv.show();
            break;

          case "Call Back":
            $budgetField.show();
            $locationField.show();
            $dateField.find(".field-legend").text("Call Back Date *");
            $timeField.find(".field-legend").text("Call Back Time *");
            $dateField.show();
            $timeField.show();
            $identityDiv.show();
            break;

          case "RNR":
            $dateField.find(".field-legend").text("RNR Date *");
            $timeField.find(".field-legend").text("RNR Time *");
            $dateField.show();
            $timeField.show();
            $identityDiv.show();
            $budgetField.hide();
            $locationField.hide();
            break;

          case "Interested":
            $budgetField.show();
            $locationField.show();
            $dateField.find(".field-legend").text("Interested Date *");
            $timeField.find(".field-legend").text("Interested Time *");
            $dateField.show();
            $timeField.show();
            $identityDiv.show();
            break;

          case "EOI":
            $budgetField.show();
            $locationField.show();
            $dateField.find(".field-legend").text("EOI Date *");
            $timeField.find(".field-legend").text("EOI Time *");
            $dateField.show();
            $timeField.show();
            $identityDiv.show();
            break;

          case "Follow Up":
            $budgetField.show();
            $locationField.show();
            $dateField.find(".field-legend").text("Follow Up Date *");
            $timeField.find(".field-legend").text("Follow Up Time *");
            $dateField.show();
            $timeField.show();
            $identityDiv.show();
            break;

          case "Fix Site Visit":
            $budgetField.show();
            $locationField.show();
            $dateField.find(".field-legend").text("Fix Site Visit Date *");
            $timeField.find(".field-legend").text("Fix Site Visit Time *");
            $dateField.show();
            $timeField.show();
            $identityDiv.show();
            break;

          case "Site Visit Done":
            $budgetField.show();
            $locationField.show();
            $dateField.find(".field-legend").text("Site Visit Date *");
            $timeField.find(".field-legend").text("Site Visit Time *");
            $dateField.show();
            $timeField.show();
            $identityDiv.show();
            break;

          case "VC Done":
            $budgetField.show();
            $locationField.show();
            $dateField.find(".field-legend").text("VC Date *");
            $timeField.find(".field-legend").text("VC Time *");
            $dateField.show();
            $timeField.show();
            $identityDiv.show();
            break;

          case "Converted":
            $budgetField.show();
            $locationField.show();
            $dateField.find(".field-legend").text("Converted Date *");
            $timeField.find(".field-legend").text("Converted Time *");
            $dateField.show();
            $timeField.show();
            $identityDiv.show();
            break;

          case "Re site visit":
            $budgetField.show();
            $locationField.show();
            $dateField.find(".field-legend").text("Re site visit Date *");
            $timeField.find(".field-legend").text("Re site visit Time *");
            $dateField.show();
            $timeField.show();
            $identityDiv.show();
            break;

          case "NQFTP":
            $budgetField.show();
            $locationField.show();
            $dateField.find(".field-legend").text("NQFTP Date *");
            $timeField.find(".field-legend").text("NQFTP Time *");
            $dateField.show();
            $timeField.show();
            $identityDiv.show();
            break;

          case "Not Connected":
            $dateField.find(".field-legend").text("Not Connected Date *");
            $timeField.find(".field-legend").text("Not Connected Time *");
            $dateField.show();
            $timeField.show();
            $identityDiv.show();
            $budgetField.hide();
            $locationField.hide();
            break;

          // Cases where only notes should be shown


          case "Already Booked":

            $dateField.hide();
            $timeField.hide();
            $identityDiv.hide();
            break;


          case "Fake":
          case "Not Interested":
            $dateField.hide();
            $timeField.hide();
            $identityDiv.hide();
            $budgetField.hide();
            $locationField.hide();
            break;

          default:
            $budgetField.show();
            $locationField.show();
            $dateField.hide();
            $timeField.hide();
            $identityDiv.show();
        }
      });



      // Initialize - hide all date/time fields by default and show lead identity
      $(document).ready(function () {
        $(".status-date-field, .status-time-field").hide();
        $("#leadIdentityDiv").show(); // Show by default
      });
      // Date Range Picker Modal
      const datePickerModal = document.createElement("div");
      datePickerModal.className = "date-picker-modal";
      datePickerModal.innerHTML = `
    <div class="date-picker-container">
        <div class="date-picker-header">
            <div class="date-picker-title">Select Date Range</div>
            <button class="date-picker-close">&times;</button>
        </div>
        <div class="date-picker-fields">
            <div class="date-picker-field">
                <label for="modalDateFrom">From Date</label>
                <input type="date" id="modalDateFrom" class="date-input">
            </div>
            <div class="date-picker-field">
                <label for="modalDateTo">To Date</label>
                <input type="date" id="modalDateTo" class="date-input">
            </div>
        </div>
        <div class="date-picker-actions">
        <button class="date-picker-btn reset">Reset</button>
            <button class="date-picker-btn cancel">Cancel</button>
            <button class="date-picker-btn apply">Apply</button>
        </div>
    </div>
`;
      document.body.appendChild(datePickerModal);

      const dateFromInput = document.getElementById("dateFrom");
      const dateToInput = document.getElementById("dateTo");
      const openDateRangePickerBtn = document.getElementById(
        "openDateRangePicker"
      );
      const modalDateFromInput = document.getElementById("modalDateFrom");
      const modalDateToInput = document.getElementById("modalDateTo");

      // Open modal when calendar button is clicked - COMMENTED OUT: Date selection is available in filter popup
      // openDateRangePickerBtn.addEventListener("click", function () {
      //   // Get current values from all sources
      //   const currentFrom = $("#dateFrom").val() || $("#isolatedFilterStartDate").val();
      //   const currentTo = $("#dateTo").val() || $("#isolatedFilterEndDate").val();
      //   
      //   // Set modal values
      //   modalDateFromInput.value = currentFrom;
      //   modalDateToInput.value = currentTo;

      //   datePickerModal.style.display = "flex";
      // });
      let dateChangeTimeout;
      function updateDateRangeFilterBackground() {
        const dateFrom = $("#dateFrom").val();
        const dateTo = $("#dateTo").val();
        const dateRangeFilter = $(".date-range-filter");

        // Keep background color unchanged - don't change to red
        // if (dateFrom || dateTo) {
        //     dateRangeFilter.css("background-color", "#df344dff"); // Red highlight
        // } else {
        //     dateRangeFilter.css("background-color", ""); // Reset
        // }
      }

      datePickerModal.querySelector('.date-picker-btn.apply').addEventListener('click', function () {
        const fromDate = modalDateFromInput.value;
        const toDate = modalDateToInput.value;

        // Update ALL date inputs
        $("#dateFrom").val(fromDate);
        $("#dateTo").val(toDate);
        $("#isolatedFilterStartDate").val(fromDate);
        $("#isolatedFilterEndDate").val(toDate);

        // CRITICAL FIX: Preserve existing filters when updating dates
        if (!window.multiFilters) {
          window.multiFilters = {};
        }

        // Only update date properties, preserve all other filters (status, user, etc.)
        if (fromDate && toDate) {
          window.multiFilters.start_date = fromDate;
          window.multiFilters.end_date = toDate;
        } else {
          // Clear date filters if empty
          delete window.multiFilters.start_date;
          delete window.multiFilters.end_date;
        }

        // Clear filter state cache before table draw
        if (typeof clearFilterStateCache === 'function') {
          clearFilterStateCache();
        }

        console.log('📅 Date picker applying filters. multiFilters:', window.multiFilters);

        // CRITICAL FIX: Skip the automatic tag count update from draw.dt handler
        // We'll do a manual update after proper delay to ensure multiFilters is stable
        if (typeof window.skipNextTagCountUpdate === 'function') {
          window.skipNextTagCountUpdate();
        }

        // Close modal and refresh table
        datePickerModal.style.display = 'none';
        table.draw();

        // CRITICAL FIX: Update tag counts AFTER table draw completes with proper delay
        // This ensures window.multiFilters is stable and not overwritten by other handlers
        setTimeout(() => {
          console.log('🔄 Updating tag counts after date filter. multiFilters:', window.multiFilters);
          clearFilterStateCache();
          updateAllTagCounts();
        }, 200);

        // Update UI highlights
        setTimeout(() => {
          updateFilterCounter();
          updateDateRangeFilterBackground(); // ðŸ”´ Add this line
        }, 200);
        // Invalidate and rebuild cache for new date context
        invalidateUniqueValuesCache();
        setTimeout(buildUniqueValuesCache, 2000) // PERF: deferred;
      });




      function syncDateFilters(fromDate, toDate) {
        // Update all date inputs
        $("#dateFrom").val(fromDate);
        $("#dateTo").val(toDate);
        $("#isolatedFilterStartDate").val(fromDate);
        $("#isolatedFilterEndDate").val(toDate);

        // Update multiFilters - THIS IS FINE, it only updates dates, doesn't replace the object
        if (window.multiFilters) {
          window.multiFilters.start_date = fromDate;
          window.multiFilters.end_date = toDate;
        } else {
          window.multiFilters = {
            start_date: fromDate,
            end_date: toDate
          };
        }
      }

      // Helper function to clear date filters
      function clearDateFilters() {
        syncDateFilters("", "");
      }


      $.fn.dataTable.ext.search.push(
        function (settings, data, dataIndex) {
          const fromDate = $('#dateFrom').val();
          const toDate = $('#dateTo').val();

          if (!fromDate && !toDate) {
            return true; // No date filter applied
          }

          // Get the date from the booking date column
          const rowData = settings.aoData[dataIndex]._aData;
          const bookingDate = rowData.bookingDate;

          const rowDate = new Date(bookingDate);
          const from = fromDate ? new Date(fromDate) : null;
          const to = toDate ? new Date(toDate) : null;

          // Set time to start/end of day for proper comparison
          if (from) from.setHours(0, 0, 0, 0);
          if (to) to.setHours(23, 59, 59, 999);
          rowDate.setHours(0, 0, 0, 0);

          if (from && to) {
            return rowDate >= from && rowDate <= to;
          } else if (from) {
            return rowDate >= from;
          } else if (to) {
            return rowDate <= to;
          }

          return true;
        }
      );



      datePickerModal.querySelector('.date-picker-btn.reset').addEventListener('click', function () {
        // Clear all date inputs
        $("#dateFrom").val('');
        $("#dateTo").val('');
        $("#isolatedFilterStartDate").val('');
        $("#isolatedFilterEndDate").val('');
        modalDateFromInput.value = '';
        modalDateToInput.value = '';

        // Clear from multiFilters (preserve other filters)
        if (window.multiFilters) {
          delete window.multiFilters.start_date;
          delete window.multiFilters.end_date;
        }

        console.log('🗑️ Date picker reset. multiFilters:', window.multiFilters);

        // Skip the automatic tag count update from draw.dt handler
        if (typeof window.skipNextTagCountUpdate === 'function') {
          window.skipNextTagCountUpdate();
        }

        // Refresh table to show all data
        table.draw();

        // Update tag counts after delay
        setTimeout(() => {
          console.log('🔄 Updating tag counts after date reset. multiFilters:', window.multiFilters);
          clearFilterStateCache();
          updateAllTagCounts();
        }, 200);
        // Date cleared -> refresh cache
        invalidateUniqueValuesCache();
        setTimeout(buildUniqueValuesCache, 2000) // PERF: deferred;
      });


      // Close modal
      datePickerModal
        .querySelector(".date-picker-close")
        .addEventListener("click", function () {
          datePickerModal.style.display = "none";
        });

      datePickerModal
        .querySelector(".date-picker-btn.cancel")
        .addEventListener("click", function () {
          datePickerModal.style.display = "none";
        });

      // REMOVED DUPLICATE: Second apply button handler removed to prevent double-firing
      // The first handler (line 5677) now correctly handles everything including:
      // - Preserving existing filters (status, user, etc.)
      // - Updating date filters
      // - Clearing filter cache
      // - Triggering table refresh and tag count updates

      // Close modal when clicking outside
      datePickerModal.addEventListener("click", function (e) {
        if (e.target === datePickerModal) {
          datePickerModal.style.display = "none";
        }
      });

      // Custom search function for date range filtering
      $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
        const fromDate = dateFromInput.value;
        const toDate = dateToInput.value;

        if (!fromDate && !toDate) {
          return true; // No date filter applied
        }

        // Get the date from the row data
        const rowData = settings.aoData[dataIndex]._aData;
        const rowDateStr = rowData.created; // Assuming 'created' is your date field

        // Create Date object from row date string
        const rowDate = new Date(rowDateStr);
        if (isNaN(rowDate.getTime())) {
          return false; // Invalid date, exclude row
        }

        // Create Date objects for filter range
        const from = fromDate ? new Date(fromDate) : null;
        const to = toDate ? new Date(toDate) : null;

        // Set time to start/end of day for proper comparison
        if (from) from.setHours(0, 0, 0, 0);
        if (to) to.setHours(23, 59, 59, 999);
        rowDate.setHours(0, 0, 0, 0);

        if (from && to) {
          return rowDate >= from && rowDate <= to;
        } else if (from) {
          return rowDate >= from;
        } else if (to) {
          return rowDate <= to;
        }

        return true;
      });

      // Event listeners for date inputs
      dateFromInput.addEventListener("change", function () {
        table.draw();

        scheduleCountRefresh();
      });

      dateToInput.addEventListener("change", function () {
        table.draw();

        scheduleCountRefresh();
      });

      // Make sure event handlers are properly bound
      $(document).on("click", ".action-btn.status, .details-row .action-btn.status", function (e) {
        e.stopPropagation();
        e.preventDefault();

        const $btn = $(this);
        const $row = $btn.closest("tr").is(".details-row")
          ? $btn.closest("tr").prev("tr")
          : $btn.closest("tr");

        const leadId = $btn.data("id") || $row.find(".lead-id-hidden").text().trim();
        const leadName = $row.find(".lead-info h4").text().trim();
        const currentStatus = $row.find(".status-badge").text().trim();
        const userUniqueId = $btn.data("userid") || $row.find(".assigned-lead").text().trim(); // Get user ID

        // Also get current identity if available
        let currentIdentity = '';


        openStatusModal(leadId, leadName, '', currentIdentity, userUniqueId); // always open with blank status
      });


      // Close modal handlers - use off/on to prevent duplicate bindings
      $("#closeStatusModal, #cancelStatusUpdate")
        .off("click")
        .on("click", function (e) {
          e.preventDefault();
          closeStatusModal();
        });

      $("#statusModal")
        .off("click")
        .on("click", function (e) {
          if ($(e.target).hasClass("modal-overlay")) {
            closeStatusModal();
          }
        });

      // Prevent modal from closing when clicking inside
      $("#statusModal .modal-container")
        .off("click")
        .on("click", function (e) {
          e.stopPropagation();
        });

      // Close modal with Escape key
      $(document)
        .off("keydown.statusModal")
        .on("keydown.statusModal", function (e) {
          if (e.key === "Escape" && $("#statusModal").hasClass("active")) {
            closeStatusModal();
          }
        });
      // Handle reassign button click in table rows
      $(document).on(
        "click",
        ".action-btn.reassign, .details-row .action-btn.reassign",
        function (e) {
          e.stopPropagation(); // Prevent event from bubbling up
          e.preventDefault(); // Prevent any default behavior

          const $btn = $(this);
          const $row = $btn.closest("tr").is(".details-row")
            ? $btn.closest("tr").prev("tr")
            : $btn.closest("tr");

          const leadId = $row.find(".lead-id-hidden").text().trim();
          const leadName = $row.find(".lead-info h4").text().trim();

          openReassignModal(leadId, leadName);
        }
      );
      // Close modal handlers
      $("#closeReassignModal, #cancelReassign").click(function (e) {
        e.preventDefault();
        closeReassignModal();
      });

      $("#reassignModal .modal-overlay").click(function (e) {
        if ($(e.target).hasClass("modal-overlay")) {
          closeReassignModal();
        }
      });

      // Prevent modal from closing when clicking inside
      $("#reassignModal .modal-container").click(function (e) {
        e.stopPropagation();
      });

      // Close modal with Escape key
      $(document).keydown(function (e) {
        if (e.key === "Escape" && $("#reassignModal").hasClass("active")) {
          closeReassignModal();
        }
      });

      // Form submission
      $("#reassignForm").submit(function (e) {
        e.preventDefault();

        // Validate required fields
        if (!$("#reassignTo").val()) {
          $("#reassignTo").closest(".form-group").addClass("error");
          $("#reassignTo")
            .closest(".form-group")
            .find(".error-message")
            .text("Please select a team member");
          return false;
        }

        const formData = {
          leadId: $("#reassignLeadId").val(),
          leadName: $("#reassignLeadName").val(),
          assignedTo: $("#reassignTo").val(),
          notes: $("#reassignNotes").val(),
          reassignedBy: "Current User",
          reassignedAt: new Date().toISOString(),
        };

        // Show loading state
        const $submitBtn = $("#submitReassign");
        $submitBtn
          .html('<i class="fas fa-spinner fa-spin"></i> Reassigning...')
          .prop("disabled", true);

        // Simulate API call
        setTimeout(function () {
          console.log("Lead reassigned:", formData);

          // Update the table row using the hidden ID
          const leadId = formData.leadId;
          table.rows().every(function () {
            const rowData = this.data();
            if (rowData.id === leadId) {
              rowData.assignedLead = formData.assignedTo;
              this.data(rowData).draw(false);
            }
          });

          // Reset form and close modal
          $submitBtn.html("Reassign Lead").prop("disabled", false);
          closeReassignModal();

          // Show success notification
          showNotification(
            `Lead ${leadId} reassigned to ${formData.assignedTo}`,
            "success"
          );
        }, 1500);
        updateDataAndUI()
      });

      // ...existing code...

      // $(document).on("change", ".filter-dropdown .filter-option", function() {
      //   const columnIndex = parseInt($(this).data("column"));
      //   const filterValue = $(this).val();
      //   const isChecked = $(this).is(":checked");
      //   const $filterOptions = $(this).closest(".filter-options");

      //   if (isChecked) {
      //     if (filterValue === "") {
      //       // If "All" is checked, uncheck others
      //       $filterOptions.find(".filter-option").not(this).prop("checked", false);
      //       table.column(columnIndex).search("").draw();
      //     } else {
      //       // If specific option is checked, uncheck "All"
      //       $filterOptions.find('.filter-option[value=""]').prop("checked", false);

      //       // Get all checked values for this column
      //       const checkedValues = [];
      //       $filterOptions.find(".filter-option:checked").each(function () {
      //         if ($(this).val() !== "") {
      //           checkedValues.push($(this).val());
      //         }
      //       });

      //       if (checkedValues.length > 0) {
      //         // Create regex for multiple values
      //         const searchRegex = checkedValues.join("|");
      //         table.column(columnIndex).search(searchRegex, true, false).draw();
      //       } else {
      //         // If no specific options are checked, show all
      //         table.column(columnIndex).search("").draw();
      //       }
      //     }
      //   } else {
      //     // If unchecking, check if no options are selected
      //     const anyChecked = $filterOptions.find(".filter-option:checked").length > 0;
      //     if (!anyChecked) {
      //       // If nothing is checked, check "All" and show all
      //       $filterOptions.find('.filter-option[value=""]').prop("checked", true);
      //       table.column(columnIndex).search("").draw();
      //     } else {
      //       // If specific options are still checked, update filter
      //       const checkedValues = [];
      //       $filterOptions.find(".filter-option:checked").each(function () {
      //         if ($(this).val() !== "") {
      //           checkedValues.push($(this).val());
      //         }
      //       });

      //       if (checkedValues.length > 0) {
      //         const searchRegex = checkedValues.join("|");
      //         table.column(columnIndex).search(searchRegex, true, false).draw();
      //       }
      //     }
      //   }
      // });

      // ...existing code...

      function initDateRangeFilter() {
        // Custom search function for date range filtering
        $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
          const fromDate = $("#dateFrom").val();
          const toDate = $("#dateTo").val();

          if (!fromDate && !toDate) {
            return true; // No date filter applied
          }

          // Get the raw 'created' data from the DataTable's internal data
          // This 'data' array corresponds to the columns defined in your DataTable.
          // Assuming 'created' is the 'created_at' from your PHP, which is mapped to 'created' in dataSrc.
          // Based on your column definition, 'created' is part of the first column's data object.
          // So, we need to access it from the rowData object.
          const rowData = settings.aoData[dataIndex]._aData;
          const createdDateStr = rowData.created; // This should be the 'created_at' value from PHP

          // Attempt to create a Date object directly from the string.
          // Ensure your PHP 'created_at' format is something JavaScript's Date constructor can parse reliably (e.g., "YYYY-MM-DD HH:MM:SS" or ISO 8601).
          const rowDate = new Date(createdDateStr);

          // Check if the date parsing was successful
          if (isNaN(rowDate.getTime())) {
            console.warn("Invalid date format for filtering:", createdDateStr);
            return true; // If date is invalid, include the row (or exclude, depending on desired behavior)
          }

          const from = fromDate ? new Date(fromDate) : null;
          const to = toDate ? new Date(toDate) : null;

          // Set time to start/end of day for proper comparison
          if (from) from.setHours(0, 0, 0, 0);
          if (to) to.setHours(23, 59, 59, 999);
          rowDate.setHours(0, 0, 0, 0); // Normalize row date to start of day for comparison

          if (from && to) {
            return rowDate >= from && rowDate <= to;
          } else if (from) {
            return rowDate >= from;
          } else if (to) {
            return rowDate <= to;
          }

          return true;
        });
        let dateChangeTimeout;




        // Event listeners for date inputs
        $("#dateFrom, #dateTo").on("change", function () {
          clearTimeout(dateChangeTimeout);

          const fromDate = $("#dateFrom").val();
          const toDate = $("#dateTo").val();

          // Ensure multiFilters exists
          if (!window.multiFilters) {
            window.multiFilters = {};
          }

          window.multiFilters.start_date = fromDate;
          window.multiFilters.end_date = toDate;

          // Keep isolated filters in sync
          $("#isolatedFilterStartDate").val(fromDate);
          $("#isolatedFilterEndDate").val(toDate);

          // Debounce to avoid flooding table redraw
          dateChangeTimeout = setTimeout(function () {
            // ❌… Trigger table redraw
            table.draw(false);

            // ❌… Run updates after redraw finishes
            table.one("draw", function () {
              updateLengthMenuVisibility();
              updateDateRangeFilterBackground();
              updateFilterCounter();

            });
          }, 500);
        });

      }
      initDateRangeFilter(table);

      // Handle sort buttons
      $(document).on("click", ".sort-btn", function () {
        const sortDirection = $(this).data("sort");
        const columnIndex = parseInt($(this).data("column"));

        // Toggle sorting
        if (sortDirection === "asc") {
          table.order([columnIndex, "asc"]).draw();
        } else {
          table.order([columnIndex, "desc"]).draw();
        }

        // Close the dropdown
        $(this).closest(".filter-dropdown").hide();
      });
    }

    $(document).on("click", ".filter-header", function (e) {
      // Only prevent sorting if clicking on the button or dropdown
      if ($(e.target).closest(".filter-header-btn, .filter-dropdown").length) {
        e.stopPropagation();
        e.preventDefault();
        return false;
      }
    });

    // Lead History Sidebar Functionality

    // Also update your lead history click handler to ignore clicks on reassign buttons:
    document.body.addEventListener("click", (event) => {
      // For history buttons - skip if click came from a reassign button
      const historyBtn = event.target.closest(".unique-toggle-btn");
      const callHistoryBtn = event.target.closest(".call-counter");
      const reassignBtn = event.target.closest(
        ".action-btn.reassign, .details-row .action-btn.reassign"
      );

      if (reassignBtn) {
        return; // Skip if this is a reassign button click
      }

      if (historyBtn) {
        event.stopPropagation();
        const rowId = historyBtn.getAttribute("data-id");
        const userUniqueId = historyBtn.getAttribute("data-userid");
        const $historyRow = $(historyBtn).closest("tr").hasClass("details-row")
          ? $(historyBtn).closest("tr").prev("tr")
          : $(historyBtn).closest("tr");
        const historySidebar = document.getElementById("uniqueLeadHistorySidebar");
        const wasActive = !!(historySidebar && historySidebar.classList.contains("active"));

        // One-time row-click behavior:
        // - Icon click opening sidebar => next row click should open details (set flag).
        // - Icon click closing sidebar => next row click should open history (clear flag).
        $historyRow.data("historyOpenedFromIcon", wasActive ? "0" : "1");
        fetchHistory(rowId, userUniqueId);
        toggleSidebar("uniqueLeadHistorySidebar");
        return;
      }

      if (callHistoryBtn) {
        event.stopPropagation();
        const rowId = callHistoryBtn.getAttribute("data-id");
        const userUniqueId = callHistoryBtn.getAttribute("data-userid");
        fetchCallHistory(rowId, userUniqueId);
        toggleSidebar("uniqueCallHistorySidebar");
        return;
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
          // Remove overdue popup styling when opened from normal table
          sidebar.classList.remove("opened-from-overdue");
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
        sidebar.classList.remove("opened-from-overdue"); // Remove overdue popup styling
        setTimeout(() => (sidebar.style.display = "none"), 300);
      }
    }

    // Initialize event listeners for close buttons
    closeLeadHistoryBtn?.addEventListener("click", () =>
      closeSidebar("uniqueLeadHistorySidebar")
    );
    closeCallHistoryBtn?.addEventListener("click", () =>
      closeSidebar("uniqueCallHistorySidebar")
    );

    // Initialize dropdown toggle logic
    function initializeLeadHistoryClickListeners() {
      document.querySelectorAll(".unique-lead-history li.unique-active-timeline").forEach((item) => {
        // Prevent adding multiple listeners if called multiple times
        if (item.hasAttribute('data-listener-attached')) return;
        item.setAttribute('data-listener-attached', 'true');

        item.addEventListener("click", () => {
          const dropdown = item.querySelector(".unique-dropdown");
          const uparrow = item.querySelector(".unique-uparrow");
          const downarrow = item.querySelector(".unique-downarrow");

          const isDropdownVisible = dropdown.classList.contains("show");

          // Show or hide the current dropdown individually (removed accordion logic)
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

    // Global History Expand Toggle Logic
    const historyExpandToggle = document.getElementById("globalHistoryExpandToggle");
    if (historyExpandToggle) {
      // Restore from localStorage
      const isHidden = localStorage.getItem("leadHistoryDetailsHidden") !== "false";
      historyExpandToggle.checked = isHidden;

      historyExpandToggle.addEventListener("change", (e) => {
        const hideDetails = e.target.checked;
        localStorage.setItem("leadHistoryDetailsHidden", hideDetails ? "true" : "false");

        // Immediately apply to current list
        document.querySelectorAll(".unique-lead-history li.unique-active-timeline").forEach((item) => {
          const dropdown = item.querySelector(".unique-dropdown");
          const uparrow = item.querySelector(".unique-uparrow");
          const downarrow = item.querySelector(".unique-downarrow");

          if (hideDetails) {
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

    $(document).on("click", " .details-row ", function (e) {
      e.stopPropagation();

      // Locate correct row (main row or previous of details row)
      const $row = $(this).closest("tr").is(".details-row")
        ? $(this).closest("tr").prev("tr")
        : $(this).closest("tr");

      // Extract data
      const leadId = $row.find(".lead-id-hidden").text().trim();
      const leadName = $row.find(".lead-info h4").text().trim();

      // Set data in sidebar using global variables
      leadUserNameEl.textContent = leadName;
      leadUserNumberEl.textContent =
        $row.find(".phone-info").data("real-phone") || "N/A";
      assignedDateLeadsEl.textContent =
        $row.find(".created-info").text().trim() || "N/A";
      assignedByUserEl.textContent =
        $row.find(".assigned-lead").text().trim() || "N/A";

      // Clear existing history
      historyList.innerHTML = "";

      // Fetch lead history
      $.ajax({
        url: "update_status.php",
        method: "POST",
        data: { leadId: leadId },
        dataType: "json",
        success: function (data) {
          if (data && Array.isArray(data)) {
            data.forEach((item) => {
              const statusClass = item.status
                .toLowerCase()
                .replace(/\s+/g, "-");

              const historyItem = document.createElement("li");
              historyItem.className = "unique-step";
              historyItem.innerHTML = `
                        <div class="unique-dot"></div>
                        <div class="unique-content">:
                            <div>
                                <span class="unique-status-info ${statusClass}">${item.status}</span>
                                <span class="unique-date-time">${item.date} at ${item.time}</span>
                            </div>
                            <span class="unique-arrow unique-downarrow">▼</span>
                            <span class="unique-arrow unique-uparrow">▲</span>
                        </div>
                        <div class="unique-dropdown">
                            <div class="unique-dropdown-insides">
                                <span><b>Notes:</b> ${item.notes}</span>
                            </div>
                        </div>
                    `;
              historyList.appendChild(historyItem);
            });
          } else {
            historyList.innerHTML = "<li>No history available.</li>";
          }
        },
        error: function (xhr, status, error) {
          document.querySelector(".loader-container").style.display = "none";

          console.error("Error fetching history data:", error);
          historyList.innerHTML = "<li>Error fetching history data.</li>";
        },
      });

      // Manage sidebars
      closeSidebar("uniqueCallHistorySidebar");
      toggleSidebar("uniqueLeadHistorySidebar");
    });

    function initiateCall(phoneNumber, leadId, $counter, $row) {
      // Ensure phoneNumber is a string and not null/undefined
      if (!phoneNumber) {
        showNotification("Phone number not found for this lead", "error");
        return;
      }

      // Convert to string in case it's a number
      const phoneStr = String(phoneNumber);

      // Format phone number with +91 country code
      const formattedPhone = formatPhoneForCall(phoneStr);

      if (!formattedPhone) {
        showNotification("Invalid phone number format", "error");
        return;
      }

      // Update the call counter
      const currentCount = parseInt($counter.text()) || 0;
      $counter.text(currentCount + 1);

      // In a real app, you would make an API call here to log the call
      console.log(`Calling ${formattedPhone} (Lead ID: ${leadId})`);

      // For demo purposes, we'll just show a success message
      Swal.fire({
        title: "Call Initiated",
        text: `Calling ${formattedPhone}`,
        icon: "success",
        timer: 2000,
        showConfirmButton: false,
      }).then(() => {
        // After the alert closes, actually initiate the call
        openDialpad(formattedPhone);
      });

      // Update last touch time
      const $lastTouch = $row.find(".last-touch-status");
      const now = new Date();
      const formattedTime = now.toLocaleString("en-US", {
        month: "short",
        day: "numeric",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
      });

      // Update the touch status in the UI
      $lastTouch
        .text("Touched today")
        .removeClass("untouched over-week")
        .addClass("recent");
    }

    // GET CALL HISTORY BUTTON
    // Fetch the current call count from the backend
    async function fetchCallCount(rowId, tr) {
      try {
        const response = await fetch("update_status.php", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
          },
          body: JSON.stringify({
            rowId: rowId,
            history_call: false, // Just fetch the count
          }),
        });
        const result = await response.json();

        if (result.status === "success") {
          const callCounterSpan = tr.querySelector(".call-counter");
          callCounterSpan.textContent = result.total_clicks || 0; // Update the call count
        } else {
          console.error("Error fetching call count:", result.message);
        }
      } catch (error) {
        console.error("Error fetching call count:", error);
      }
    }


    // Add click event listener for call button
    document.addEventListener("click", async (event) => {
      const button = event.target.closest(".call-button");

      if (button) {
        const wrapper = button.closest(".different-wrapper");
        const anchor = wrapper.querySelector("a");
        const rowId = anchor?.dataset.id;
        const callCounterSpan = wrapper.querySelector(".call-btn-counter");

        if (!rowId || !callCounterSpan) {
          console.error("Required elements are missing");
          return;
        }

        event.preventDefault(); // Prevent call for now

        // ❌… SweetAlert2 confirmation
        const { isConfirmed } = await Swal.fire({
          title: "Are you sure?",
          text: "Do you really want to make this call?",
          icon: "question",
          showCancelButton: true,
          confirmButtonColor: "#3085d6",
          cancelButtonColor: "#d33",
          confirmButtonText: "Yes, call now!",
          cancelButtonText: "Cancel",
        });

        if (!isConfirmed) return;

        button.disabled = true;
        button.classList.add("loading");

        try {
          const response = await fetch("update_status.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
              history_call: true,
              rowId: rowId,
            }),
          });

          const result = await response.json();

          if (result.status === "success") {
            callCounterSpan.textContent = result.total_clicks;
            console.log("Call logged successfully:", result.call_history);

            // ❌… Trigger the phone call after successful log
            window.location.href = anchor.href;
          } else {
            console.error("Error logging call:", result.message);
            Swal.fire("Oops!", result.message, "error");
          }
        } catch (error) {
          console.error("Fetch error:", error);
          Swal.fire("Error", "Something went wrong!", "error");
        } finally {
          button.disabled = false;
          button.classList.remove("loading");
        }
      }
    });



    $(document).on("click", ".call-button", async function (e) {
      e.stopPropagation(); // Prevent event from bubbling up
      e.preventDefault(); // Prevent default anchor behavior

      const $button = $(this);
      const $row = $button.closest("tr");
      const $counter = $button
        .closest(".phone-btn-wrapper")
        .find(".call-btn-counter");

      // Get lead info
      const leadName = $row.find(".lead-info h4").text().trim();
      const phoneNumber = $row.find(".phone-info").data("real-phone");
      const leadId = $row.find(".lead-id-hidden").text().trim();

      if (!phoneNumber) {
        Swal.fire({
          title: "Error",
          text: "Phone number not available for this lead",
          icon: "error",
        });
        return;
      }

      // Show SweetAlert confirmation
      const { isConfirmed } = await Swal.fire({
        title: "Confirm Call",
        html: `Call <strong>${leadName}</strong> at <strong>${phoneNumber}</strong>?`,
        icon: "question",
        showCancelButton: true,
        confirmButtonText: "Call Now",
        cancelButtonText: "Cancel",
      });

      if (isConfirmed) {
        // Only proceed with call if confirmed
        initiateCall(phoneNumber, leadId, $counter, $row);
      }
    });

    // Update the click handler for call buttons in your table
    // $(document).on('click', '.action-btn.phone', function(e) {
    //     e.preventDefault();
    //     e.stopPropagation();

    //     const $button = $(this);
    //     const $row = $button.closest('tr');
    //     const $counter = $button.closest('.phone-btn-wrapper').find('.call-btn-counter');

    //     // Get lead info - handle both main row and details row
    //     const leadName = $row.find('.lead-info h4').text().trim();
    //     const phoneNumber = $row.find('.phone-info').data('real-phone');
    //     const leadId = $row.find('.lead-id-hidden').text().trim();

    //     if (!phoneNumber) {
    //         Swal.fire({
    //             title: 'Error',
    //             text: 'Phone number not available for this lead',
    //             icon: 'error'
    //         });
    //         return;
    //     }

    //     // Format phone for display
    //     const formattedPhone = formatPhoneNumber(phoneNumber);

    //     // Show SweetAlert confirmation
    //     Swal.fire({
    //         title: 'Confirm Call',
    //         html: `Call <strong>${leadName}</strong> at <strong>${formattedPhone}</strong>?`,
    //         icon: 'question',
    //         showCancelButton: true,
    //         confirmButtonText: 'Call Now',
    //         cancelButtonText: 'Cancel',
    //         customClass: {
    //             confirmButton: 'btn btn-primary',
    //             cancelButton: 'btn btn-outline-secondary'
    //         },
    //         buttonsStyling: false
    //     }).then((result) => {
    //         if (result.isConfirmed) {
    //             initiateCall(phoneNumber, leadId, $counter, $row);
    //         }
    //     });
    // });
    // // Helper function to format phone numbers
    // function formatPhoneNumber(phone) {
    //     // Remove all non-digit characters
    //     const cleaned = ('' + phone).replace(/\D/g, '');

    //     // Format as (XXX) XXX-XXXX for US numbers
    //     const match = cleaned.match(/^(\d{3})(\d{3})(\d{4})$/);
    //     if (match) {
    //         return '(' + match[1] + ') ' + match[2] + '-' + match[3];
    //     }
    //     return phone; // Return original if formatting fails
    // }


    function handleResponsiveBehavior() {
      const isMobile = window.innerWidth <= 900;

      // Show/hide columns based on screen size
      if (isMobile) {
        $(".mobile-hide").hide();
        $(".mobile-project").show();
      } else {
        $(".mobile-hide").show();
        $(".mobile-project").hide();
      }

      $("#leadsTable tbody tr").each(function () {
        const $row = $(this);
        const detailsContent = createDetailsContent($row);
        const leadId = $row.find("td:eq(1) .lead-id-section").text();
        let $detailsRow = $row.next(".details-row");

        $row.find(".expand-row-btn").show();

        if (detailsContent) {
          if ($detailsRow.length) {
            $detailsRow.find(".details-content").html(detailsContent);
          } else {
            $detailsRow = $(`
            <tr class="details-row" data-parent-id="${leadId}">
                <td colspan="10">
                    <div class="details-content">
                        ${detailsContent}
                    </div>
                </td>
            </tr>
        `);
            $row.after($detailsRow);
          }

          $detailsRow.hide();
          const callCounter = $detailsRow.find(".call-counter");
          if (callCounter.length) {
            const rowId = callCounter.data("id");
            fetchCallCount(rowId, $detailsRow[0]);
          }
        } else if ($detailsRow.length) {
          $detailsRow.remove();
        }
      });
    }

    function createDetailsContent(row) {
      const hiddenColumns = [];
      const headers = $("#leadsTable thead th");
      const excludedColumns = [0, 1, 4]; // Exclude checkbox, lead info, ID, and expand button columns

      // For mobile view, use DataTable row data directly
      if (window.innerWidth <= 900) {
        let rowData;
        if (
          typeof table !== "undefined" &&
          row instanceof jQuery &&
          row.length
        ) {
          rowData = table.row(row).data();
        }

        // ── Fallback for lazy-loaded rows (not in DataTables registry) ──────
        // For rows appended via the infinite-scroll fetch, table.row().data()
        // returns undefined. We scrape the same values from the rendered DOM.
        if (!rowData && row instanceof jQuery && row.length) {
          const $r = row;
          // Lead ID from hidden span or the ID cell
          const scrapedId = ($r.find('.lead-id-hidden').text() ||
            $r.find('.lead-id-section').text().replace('#', '')).trim();
          // Name from the lead name heading
          const scrapedName = $r.find('.lead-name h4, .lead-name').first().clone().children().remove().end().text().trim() ||
            $r.find('h4').first().text().trim();
          // Phone from phone-info data attribute
          const $phone = $r.find('.phone-info').first();
          const scrapedPhone = ($phone.data('real-phone') || $phone.text()).toString().trim();
          // Status badge text
          const scrapedStatus = $r.find('.status-badge').first().text().trim();
          // Project from mobile project element
          const scrapedProject = $r.find('.mobile-project-name, .project-name').first().text().trim();
          // Assigned lead from assignedlead element
          const scrapedAssignedLead = $r.find('.assigned-lead, [data-userid]').first().text().trim() ||
            $r.find('[data-userid]').first().data('userid') || '';
          // Recording URL from recording button data-url
          const scrapedRecording = $r.find('.play-recording-btn').first().data('url') || '';
          // WA interest from hidden span
          const scrapedWaInterest = $r.find('.wa-interest-hidden').text().trim() || 'none';

          if (scrapedId) {
            rowData = {
              id: scrapedId,
              name: scrapedName || 'N/A',
              email: $r.find('[data-type="email"], .email-info').first().text().trim() || 'N/A',
              phone: scrapedPhone,
              assignedProject: scrapedProject || 'N/A',
              assignedLead: scrapedAssignedLead || 'N/A',
              status: scrapedStatus,
              location: $r.find('.location-info').first().text().trim() || 'N/A',
              budget: $r.find('.budget-info').first().text().trim() || 'N/A',
              created: $r.find('.created-info').first().text().trim() || 'N/A',
              updatedAt: $r.find('.updated-info').first().text().trim() || 'N/A',
              source: $r.find('.source-info').first().text().trim() || '',
              recording_url: scrapedRecording,
              wa_interest: scrapedWaInterest,
            };
          }
        }
        // ── End fallback ─────────────────────────────────────────────────────

        if (rowData) {
          const leadName = rowData.name || "N/A";
          const email = rowData.email || "N/A";
          const project = rowData.assignedProject || "N/A";
          const source = rowData.source
            ? rowData.source
              .replace(".svg", "")
              .replace("-logo", "")
              .replace(/-/g, " ")
            : "N/A";
          const location = rowData.location || "N/A";
          const assignedLead = rowData.assignedLead || "N/A";
          const created = rowData.created || "N/A";
          const updatedAt = rowData.updatedAt || "N/A";
          const hasRecording =
            rowData.recording_url && rowData.recording_url.trim() !== '';
          return `
            <div class="details-block details-block-left">
                <div class="mobile-details-section">
                    <h4>Lead Details</h4>
                    <div class="flexxx">
                        <strong>Name: &nbsp;</strong>
                        <div class="text-toggle detail-row-text" data-type="name">${leadName}</div>
                    </div>
                    <div class="flexxx">
                        <strong>Email: &nbsp;</strong>
                        <div class="text-toggle detail-row-text" data-type="email">${email}</div>
                    </div>
                    <div class="flexxx">
                       <strong>Project:&nbsp;</strong>
                        <div class="text-toggle detail-row-text" data-type="project">${project}</div>
                    </div>
                    <div class="flexxx">
                        <strong>Assigned Lead:&nbsp;</strong>
                        <div class="text-toggle detail-row-text" data-type="assigned lead">${assignedLead || "N/A"
            }</div>
                    </div>
                    <div class="flexxx">
                        <strong>Location:&nbsp;</strong>
                        <div class="text-toggle detail-row-text" >${location || "N/A"
            }</div>
                    </div>
                    <div class="flexxx">
                        <strong>Budget:&nbsp;</strong>
                        <div class="text-toggle detail-row-text" data-type="budget">${rowData.budget || "N/A"}</div>
                    </div>
                    <div class="flexxx">
                        <strong>Created:&nbsp;</strong>
                        <div class="text-toggle detail-row-text" data-type="created">${created}</div>
                    </div>
                    <div class="flexxx">
                      <strong>Updated:&nbsp;</strong>
                      <div class="text-toggle detail-row-text" data-type="updated">${updatedAt}</div>
                    </div>
                </div>
            </div>
             <div class="details-block details-block-right">
                <h4>Actions</h4>
                <div class="action-buttons-leads mobile">
                    <button class="action-btn whatsapp tooltip" data-tooltip="WhatsApp">
                        <i class="fab fa-whatsapp"></i>
                    </button>
                    <span class="wa-interest-hidden" style="display:none;">${rowData.wa_interest || ''}</span>
                    <button class="action-btn status tooltip update-status-btn different-buttons" 
                            data-tooltip="Update Status" 
                            data-id="${rowData.id}" 
                            data-userid="${rowData.assignedLead}">
                        <i class="fas fa-refresh"></i>
                    </button>
                    <button class="action-btn history tooltip unique-toggle-btn"
                            data-tooltip="History" 
                            data-id="${rowData.id}" 
                            data-userid="${rowData.assignedLead}">
                        <i class="fas fa-history"></i>
                    </button>
                    
                    <button class="action-btn reassign tooltip" 
                            data-tooltip="Reassign" 
                            data-id="${rowData.id}">
                        <i class="fas fa-user-friends"></i>
                    </button>
                    
                    <button class="action-btn recording tooltip play-recording-btn
                            ${!hasRecording ? 'disabled-btn' : ''}"
                            data-tooltip="Recording"
                            data-url="${hasRecording ? rowData.recording_url : ''}"
                            ${!hasRecording ? 'disabled' : ''}>
                        <i class="fa fa-volume-up"></i>
                    </button>
                    <div class="phone-btn-wrapper different-wrapper" style="flex: 1;">
                        <a href="tel:${formatPhoneForCall(rowData.phone)}" data-id="${rowData.id
            }" style="width: 20%;">
                            <button class=" call-button action-btn phone tooltip mobile-phone" data-tooltip="Call" data-id="${rowData.id
            }" data-userid="${rowData.assignedLead}">
                                <i class="fas fa-phone"></i>
                            </button>
                        </a>
                        <div class="call-btn-counter different-btn-counter call-counter" data-id="${rowData.id
            }" data-userid="${rowData.assignedLead}">0</div>
                    </div>
                    ${buildWhatsAppHistoryButtonMarkup(rowData, true)}
                </div>
            </div>
            `;
        }
      }
      // ...existing desktop logic...
      for (let i = 0; i < headers.length; i++) {
        if (excludedColumns.includes(i)) continue;
        const header = $(headers[i]);
        const cell = row.find(`td:eq(${i})`);
        if (cell.css("display") === "none") {
          const headerText =
            header.find(".filter-header-btn").text().trim() ||
            header.text().trim();
          if (headerText) {
            hiddenColumns.push({
              header: headerText,
              content: cell.html(),
            });
          }
        }
      }
      if (hiddenColumns.length > 0) {
        let detailsHTML = "";
        hiddenColumns.forEach((column) => {
          detailsHTML += `
                <div class="details-block">
                    <strong>${column.header}:</strong>
                    <div>${column.content}</div>
                </div>
            `;
        });
        return detailsHTML;
      }
      return "";
    }

    $(document).on("click", ".text-toggle", function (e) {
      e.stopPropagation(); // Prevent the click from bubbling up

      const $this = $(this);
      const type = $this.data("type");

      // Toggle text visibility
      $this.toggleClass("full-text");
      // If it's the first time expanding, change the cursor style
      if (!$this.hasClass("full-text")) {
        $this.css("cursor", "pointer");
      }
    });

    $(document).on("click", function (e) {
      // If the click is not on a text-toggle element and not inside a modal
      if (
        !$(e.target).closest(".text-toggle").length &&
        !$(e.target).closest(".modal-container").length
      ) {
        // Hide all expanded text
        $(".text-toggle.full-text").removeClass("full-text");
      }
    });

    function initializeLeadSourceSelect2() {
      $('#leadsource').select2({
        placeholder: "Select Source",
        allowClear: false,
        width: '100%',
        dropdownParent: $('#addLeadModal'),
        minimumResultsForSearch: -1
      });
    }


    function destroyLeadSourceSelect2() {
      if ($('#leadsource').hasClass('select2-hidden-accessible')) {
        $('#leadsource').select2('destroy');
      }
      $('#leadsource').val('');
    }

    function ensureAddLeadBottomSheetStyles() {
      if (document.getElementById('addLeadBottomSheetStyles')) {
        return;
      }

      const styleEl = document.createElement('style');
      styleEl.id = 'addLeadBottomSheetStyles';
      styleEl.textContent = `
        #addLeadModal.add-lead-bottom-sheet {
          display: flex;
          align-items: center;
          justify-content: center;
          padding: 0;
        }

        #addLeadModal.add-lead-bottom-sheet .modal-container {
          width: min(620px, 100vw);
          max-height: 88vh;
          overflow-y: auto;
          margin: 0;
          border-radius: 18px 18px 0 0;
          transform: translateY(100%);
          transition: transform 0.28s ease;
          box-shadow: 0 -18px 45px rgba(15, 23, 42, 0.25);
        }

        #addLeadModal.add-lead-bottom-sheet.active .modal-container {
          transform: translateY(0);
        }

        #addLeadModal.add-lead-bottom-sheet.is-closing .modal-container {
          transform: translateY(100%);
        }

        #addLeadModal.add-lead-bottom-sheet .modal-overlay {
          align-items: flex-end;
        }
      `;

      document.head.appendChild(styleEl);
    }


    $(document).ready(function () {
      // Function to open modal
      function openAddLeadModal() {

        destroyLeadSourceSelect2();

        // Initialize Select2 for lead source
        initializeLeadSourceSelect2();

        // Ensure bottom-sheet styling is available
        ensureAddLeadBottomSheetStyles();

        // Fetch IP address lazily when modal opens (reduces page load network calls)
        if (typeof fetchIPAddressLazy === 'function') {
          fetchIPAddressLazy();
        }

        // Show modal
        const $modal = $("#addLeadModal");
        $("body").css("overflow", "hidden");
        $modal
          .addClass("add-lead-bottom-sheet")
          .removeClass("is-closing")
          .show();

        requestAnimationFrame(() => {
          $modal.addClass("active");
        });
      }

      // Function to close modal
      function closeAddLeadModal() {
        const $modal = $("#addLeadModal");
        $("body").css("overflow", "auto");

        if (!$modal.hasClass("add-lead-bottom-sheet")) {
          $modal.removeClass("active").hide();
          return;
        }

        $modal.addClass("is-closing").removeClass("active");

        setTimeout(() => {
          $modal.hide().removeClass("is-closing");
        }, 280);
      }

      window.openAddLeadModal = openAddLeadModal;
      window.closeAddLeadModal = closeAddLeadModal;

      // Open modal when clicking any + button (floating, mobile, etc.)
      $(document).on(
        "click",
        ".action-button:has(.fa-plus), .add-btn-mobile",
        function (e) {
          e.preventDefault();
          openAddLeadModal();
        }
      );

      // Close modal when clicking close button, cancel button, or overlay
      $("#closeAddLeadModal, #cancelAddLead").click(function (e) {
        e.preventDefault();
        closeAddLeadModal();
      });

      $(".modal-overlay").click(function (e) {
        if ($(e.target).hasClass("modal-overlay")) {
          closeAddLeadModal();
        }
      });

      // Prevent modal from closing when clicking inside the modal container
      $(".modal-container").click(function (e) {
        e.stopPropagation();
      });

      // Close modal when pressing Escape key
      $(document).keydown(function (e) {
        if (e.key === "Escape" && $("#addLeadModal").hasClass("active")) {
          closeAddLeadModal();
        }
      });



      $("#leadPhone").on("input", function (e) {
        // Get current value
        let value = this.value.replace(/\D/g, "");

        // If first digit is not between 6-9, clear the input
        if (value.length > 0 && !/^[6-9]/.test(value)) {
          this.value = "";
          $(this).closest(".form-group").addClass("error");
          $(this)
            .closest(".form-group")
            .find(".error-message")
            .text("Indian mobile numbers must start with 6, 7, 8, or 9");
          return;
        }

        // Limit to 10 digits
        this.value = value.substring(0, 10);

        // Clear error if valid
        if (/^[6-9]/.test(this.value)) {
          $(this).closest(".form-group").removeClass("error");
          $(this).closest(".form-group").find(".error-message").text("");
        }
      });

      // Form submission handling
      $("#addLeadForm").submit(function (e) {
        e.preventDefault();

        // Reset error states
        $(".form-group").removeClass("error");
        $(".error-message").text("");

        if (!validateAddLeadForm()) {
          showNotification("Please fill all required fields correctly", "error");
          return false;
        }
        let isValid = true;

        // Validate required fields
        $("#addLeadForm [required]").each(function () {
          if (!$(this).val().trim()) {
            const $formGroup = $(this).closest(".form-group");
            $formGroup.addClass("error");
            $formGroup.find(".error-message").text("This field is required");
            isValid = false;

            // Focus on first invalid field
            if (isValid === false) {
              $(this).focus();
              isValid = null; // Prevents changing back to true
            }
          }
        });

        if (!isValid) return false;

        // Validate phone number
        const phone = $("#leadPhone").val().trim();
        if (!/^\d{10}$/.test(phone)) {
          $("#leadPhone").closest(".form-group").addClass("error");
          $("#leadPhone")
            .closest(".form-group")
            .find(".error-message")
            .text("Please enter a valid 10-digit phone number");
          $("#leadPhone").focus();
          return false;
        }

        // Validate email if provided
        const email = $("#leadEmail").val().trim();
        if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
          $("#leadEmail").closest(".form-group").addClass("error");
          $("#leadEmail")
            .closest(".form-group")
            .find(".error-message")
            .text("Please enter a valid email address");
          $("#leadEmail").focus();
          return false;
        }

        // Form is valid - process submission
        const formData = {
          name: $("#leadName").val().trim(),
          phone: "+91" + phone,
          email: email || null,
          project: $("#leadProject").val(),
          source: $("#leadSource").val(),
          status: "New",
          created: new Date().toISOString(),
        };

        // Show loading state
        const $submitBtn = $("#submitLead");
        $submitBtn
          .html('<i class="fas fa-spinner fa-spin"></i> Adding...')
          .prop("disabled", true);

        // Simulate API call
        setTimeout(function () {
          // In a real app, you would make an AJAX call here
          console.log("Form submitted:", formData);

          // Add to DataTable (simulated)
          if (typeof table !== "undefined") {
            // Instead of adding directly, trigger a redraw to fetch new data from server
            table.ajax.reload(null, false); // Reload data without resetting pagination
          }

          // Reset form and close modal
          $("#addLeadForm")[0].reset();
          $submitBtn.html("Add Lead").prop("disabled", false);

          closeAddLeadModal();

          // Show success notification
          showNotification("Lead added successfully!", "success");
          // Refresh data
          updateDataAndUI()
          fetchHistory(rowId, userUniqueId);
        }, 0);
      });

      // Phone number input validation
      $("#leadPhone").on("input", function () {
        this.value = this.value.replace(/[^0-9]/g, "").substring(0, 10);
      });

      // Helper function to show notifications
      function showNotification(message, type) {
        const palette = {
          success: "rgba(16, 185, 129, 0.96)",
          error: "rgba(239, 68, 68, 0.96)",
          warning: "rgba(245, 158, 11, 0.96)",
          info: "rgba(59, 130, 246, 0.96)",
        };

        const hiddenTransform = "translate(-50%, 16px)";
        const visibleTransform = "translate(-50%, 0)";

        $(".notification, .lead-toast").remove();

        const $notification = $(
          '<div class="lead-toast ' + type + '" role="status" aria-live="polite">' +
          message +
          "</div>"
        ).css({
          position: "fixed",
          top: "auto",
          bottom: "24px",
          left: "50%",
          transform: hiddenTransform,
          opacity: 0,
          transition: "transform 0.28s ease, opacity 0.28s ease",
          zIndex: 100000,
          maxWidth: "min(90vw, 540px)",
          width: "auto",
          padding: "14px 24px",
          borderRadius: "14px",
          background: palette[type] || palette.info,
          backgroundColor: palette[type] || palette.info,
          color: "#ffffff",
          fontWeight: 600,
          fontSize: message.length > 120 ? "14px" : "15px",
          letterSpacing: "0.2px",
          boxShadow: "0 10px 25px rgba(0, 0, 0, 0.2)",
          pointerEvents: "none",
          textAlign: "center",
          display: "inline-flex",
          alignItems: "center",
          justifyContent: "center",
          lineHeight: message.length > 120 ? "1.4" : "1.2",
          whiteSpace: "pre-line",
          height: "auto",
          minHeight: "auto",
        });

        $notification[0].style.setProperty("background-color", palette[type] || palette.info, "important");
        $notification[0].style.setProperty("color", "#ffffff", "important");
        $notification[0].style.setProperty("background", palette[type] || palette.info, "important");

        $("body").append($notification);

        requestAnimationFrame(function () {
          $notification.css({
            transform: visibleTransform,
            opacity: 1,
          });
        });

        setTimeout(function () {
          $notification.css({
            transform: hiddenTransform,
            opacity: 0,
          });
          setTimeout(function () {
            $notification.remove();
          }, 280);
        }, 3200);
      }
    });

    async function deleteSelectedLeads(selectedIds) {
      const response = await fetch("update_status.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          bulkDelete: true,
          rowIds: selectedIds,
        }),
      });

      const payload = await response.json().catch(() => ({}));
      if (!response.ok || payload.status !== "success") {
        const message = payload.message || "Delete failed. Please try again.";
        throw new Error(message);
      }

      return payload;
    }

    function handleDeleteSelectedLeads() {
      if (window.canDeleteLeads !== true) {
        showNotification("You are not allowed to delete leads.", "error");
        return;
      }

      const selectedIds = getSelectedLeadIds();
      if (selectedIds.length === 0) {
        showNotification("Please select at least one lead to delete", "error");
        return;
      }

      const leadCount = selectedIds.length;
      const message = `Do you really want to delete ${leadCount} lead${leadCount > 1 ? "s" : ""}? This action cannot be undone.`;

      if (!window.Swal) {
        if (window.confirm(message)) {
          deleteSelectedLeads(selectedIds)
            .then(() => {
              if ($.fn.DataTable.isDataTable("#leadsTable")) {
                $("#leadsTable").DataTable().ajax.reload(null, false);
              }
              if (typeof scheduleCountRefresh === "function") {
                scheduleCountRefresh();
              }
              $("#selectAll").prop("checked", false);
              $(".row-checkbox").prop("checked", false);
              updateSelectionActions();
              showNotification("Leads deleted successfully", "success");
            })
            .catch((error) => {
              showNotification(error.message || "Delete failed", "error");
            });
        }
        return;
      }

      Swal.fire({
        title: "Are you sure?",
        text: message,
        icon: "question",
        showCancelButton: true,
        confirmButtonText: "Yes, delete now!",
        cancelButtonText: "Cancel",
        confirmButtonColor: "#22c55e",
        cancelButtonColor: "#ef4444",
      }).then((result) => {
        if (!result.isConfirmed) return;

        Swal.fire({
          title: "Deleting...",
          allowOutsideClick: false,
          showConfirmButton: false,
          didOpen: () => {
            Swal.showLoading();
          },
        });

        deleteSelectedLeads(selectedIds)
          .then((payload) => {
            Swal.close();

            if ($.fn.DataTable.isDataTable("#leadsTable")) {
              $("#leadsTable").DataTable().ajax.reload(null, false);
            }
            if (typeof scheduleCountRefresh === "function") {
              scheduleCountRefresh();
            }

            $("#selectAll").prop("checked", false);
            $(".row-checkbox").prop("checked", false);
            updateSelectionActions();

            const deletedCount = payload.deletedCount || selectedIds.length;
            Swal.fire({
              toast: true,
              position: "bottom",
              icon: "success",
              title: `${deletedCount} lead${deletedCount > 1 ? "s" : ""} deleted successfully`,
              showConfirmButton: false,
              timer: 3000,
              timerProgressBar: true,
              customClass: {
                popup: "custom-toast-popup",
              },
            });
          })
          .catch((error) => {
            Swal.close();
            Swal.fire({
              toast: true,
              position: "bottom",
              icon: "error",
              title: error.message || "Delete failed",
              showConfirmButton: false,
              timer: 3000,
              timerProgressBar: true,
              customClass: {
                popup: "custom-toast-popup",
              },
            });
          });
      });
    }

    $(document).on(
      "click",
      ".delete-selected-btn, .delete-btn-mobile",
      function (e) {
        e.preventDefault();
        handleDeleteSelectedLeads();
      }
    );

    //  existing expand button click handler with this:
    $(document).on("click", ".expand-row-btn", function (e) {
      e.stopPropagation();
      e.preventDefault();

      const $btn = $(this);
      const $row = $btn.closest("tr");
      if (isRemarksModeActive && !$row.hasClass("row-show-details")) {
        // In remarks mode, expand-btn click should open history sidebar
        openHistoryForRow($row);
        return;
      }

      const $downArrow = $btn.find(".down-arrow");
      const $upArrow = $btn.find(".up-arrow");
      const $detailsRow = $row.next(".details-row");
      const $phoneInfo = $row.find(".phone-info");

      // Close any previously expanded row and remove highlight
      if (currentExpandedRow && currentExpandedRow !== $row[0]) {
        const $prevRow = $(currentExpandedRow);
        const $prevDetails = $prevRow.next(".details-row");
        const $prevBtn = $prevRow.find(".expand-row-btn");
        $prevDetails.hide();
        $prevBtn.find(".down-arrow").show();
        $prevBtn.find(".up-arrow").hide();
        $prevRow.removeClass("highlight-row");

        // Encrypt phone if visible
        const $prevPhone = $prevRow.find(".phone-info");
        if ($prevPhone.length && !$prevPhone.hasClass("encrypted")) {
          const realPhone = $prevPhone.data("real-phone");
          const encryptedPhone = encryptPhone(realPhone);
          $prevPhone.text(encryptedPhone).addClass("encrypted");
        }
      }

      // Toggle current row
      if ($detailsRow.is(":visible")) {
        $detailsRow.hide();
        $downArrow.show();
        $upArrow.hide();
        $row.removeClass("highlight-row");

        // Encrypt phone if visible
        if ($phoneInfo.length && !$phoneInfo.hasClass("encrypted")) {
          const realPhone = $phoneInfo.data("real-phone");
          const encryptedPhone = encryptPhone(realPhone);
          $phoneInfo.text(encryptedPhone).addClass("encrypted");
        }
      } else {
        $detailsRow.show();
        $downArrow.hide();
        $upArrow.show();
        $row.addClass("highlight-row");

        // Show phone number if encrypted
        if ($phoneInfo.length && $phoneInfo.hasClass("encrypted")) {
          const realPhone = $phoneInfo.data("real-phone");
          $phoneInfo.text(realPhone).removeClass("encrypted");
        }
      }

      // Update current expanded row reference
      currentExpandedRow = $detailsRow.is(":visible") ? $row[0] : null;
      currentPhoneRow = $detailsRow.is(":visible") ? $row[0] : null;
    });

    $(document).on("click", ".filter-header", function (e) {
      // Only prevent sorting if clicking on the button or dropdown
      if ($(e.target).closest(".filter-header-btn, .filter-dropdown").length) {
        e.stopPropagation();
        e.preventDefault();
        return false;
      }
    });

    function fetchCallHistory(rowId, userUniqueId, options = {}) {
      const cacheKey = buildLeadCacheKey(rowId, userUniqueId);
      const cached = !options.forceRefresh ? getCachedValue(callHistoryCache, cacheKey) : null;

      if (cached && cached.status === "success") {
        followUpCallHistory.innerHTML = "";
        const historyEntries = Array.isArray(cached.history) ? cached.history : [];

        if (historyEntries.length === 0) {
          followUpCallHistory.innerHTML =
            '<li class="list-group-item">No follow-up history found.</li>';
        } else {
          historyEntries.forEach((entry) => {
            const callType = (entry.call_type || 'outgoing').toLowerCase();
            const callIcon = callType === 'missed' ? '📵' :
              callType === 'incoming' ? '📲' : '📞';
            const callClass = callType === 'missed' ? 'status-fake' :
              callType === 'incoming' ? 'status-interested' : 'status-callback';
            const durationSec = parseInt(entry.duration_seconds || 0);
            const durLabel = durationSec > 0
              ? (durationSec >= 60
                ? Math.floor(durationSec / 60) + 'm ' + (durationSec % 60) + 's'
                : durationSec + 's')
              : 'Not connected';
            const attempted = entry.click_attempted || '—';

            const li = document.createElement("li");
            li.classList.add("unique-step", "unique-active-timeline");
            li.innerHTML = `
                        <div class="unique-dot"></div>
                        <div class="unique-content">
                            <span class="unique-status-info ${callClass}">${callIcon} Call Attempted: ${attempted}</span>
                            <span class="unique-arrow">→</span>
                            <span class="unique-status-view"><a href="#.">${durLabel}</a></span>
                            <span class="unique-arrow">→</span>
                            <span class="unique-date-time">${entry.timestamp}</span>
                            <span class="unique-arrow unique-downarrow">▼</span>
                            <span class="unique-arrow unique-uparrow" style="display:none">▲</span>
                        </div>
                        <div class="unique-dropdown">
                            <div class="unique-dropdown-insides">
                                <span><b>Call Type:</b> ${callType.charAt(0).toUpperCase() + callType.slice(1)}</span>
                                <span><b>Duration:</b> ${durLabel}</span>
                                <span><b>Phone:</b> ${entry.phone_number || '—'}</span>
                                <span><b>Date &amp; Time:</b> ${entry.timestamp}</span>
                            </div>
                        </div>
                    `;
            followUpCallHistory.appendChild(li);
          });
        }

        assignedDateCallLeads.textContent = cached.assignedDate || "N/A";
        assignedByCallUser.textContent = cached.assignedBy || "N/A";
        leadUserCallName.textContent = cached.lead_user || "N/A";
        leadUserCallNumber.textContent = cached.lead_number || "N/A";
        return;
      }

      fetch("update_status.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          fetchCallHistory: true,
          rowId,
          user_unique_id: userUniqueId,
        }),
      })
        .then((response) => response.json())
        .then((data) => {
          setCachedValue(callHistoryCache, cacheKey, data);
          if (data.status === "success") {
            followUpCallHistory.innerHTML = ""; // Clear previous history

            if (data.history.length === 0) {
              followUpCallHistory.innerHTML =
                '<li class="list-group-item">No follow-up history found.</li>';
            } else {
              data.history.forEach((entry) => {
                const callType = (entry.call_type || 'outgoing').toLowerCase();
                const callIcon = callType === 'missed' ? '📵' :
                  callType === 'incoming' ? '📲' : '📞';
                const callClass = callType === 'missed' ? 'status-fake' :
                  callType === 'incoming' ? 'status-interested' : 'status-callback';
                const durationSec = parseInt(entry.duration_seconds || 0);
                const durLabel = durationSec > 0
                  ? (durationSec >= 60
                    ? Math.floor(durationSec / 60) + 'm ' + (durationSec % 60) + 's'
                    : durationSec + 's')
                  : 'Not connected';
                const attempted = entry.click_attempted || '—';

                const li = document.createElement("li");
                li.classList.add("unique-step", "unique-active-timeline");
                li.innerHTML = `
                        <div class="unique-dot"></div>
                        <div class="unique-content">
                            <span class="unique-status-info ${callClass}">${callIcon} Call Attempted: ${attempted}</span>
                            <span class="unique-arrow">→</span>
                            <span class="unique-status-view"><a href="#.">${durLabel}</a></span>
                            <span class="unique-arrow">→</span>
                            <span class="unique-date-time">${entry.timestamp}</span>
                            <span class="unique-arrow unique-downarrow">▼</span>
                            <span class="unique-arrow unique-uparrow" style="display:none">▲</span>
                        </div>
                        <div class="unique-dropdown">
                            <div class="unique-dropdown-insides">
                                <span><b>Call Type:</b> ${callType.charAt(0).toUpperCase() + callType.slice(1)}</span>
                                <span><b>Duration:</b> ${durLabel}</span>
                                <span><b>Phone:</b> ${entry.phone_number || '—'}</span>
                                <span><b>Date &amp; Time:</b> ${entry.timestamp}</span>
                            </div>
                        </div>
                    `;
                followUpCallHistory.appendChild(li);
              });
            }

            // Populate assigned details and lead info using globals
            assignedDateCallLeads.textContent = data.assignedDate || "N/A";
            assignedByCallUser.textContent = data.assignedBy || "N/A";
            leadUserCallName.textContent = data.lead_user || "N/A";
            leadUserCallNumber.textContent = data.lead_number || "N/A";
          } else {
            console.error("Failed to fetch history:", data.message);
          }
        })
        .catch((error) => console.error("Error fetching history:", error));
    }

    // Updated row click handler with highlighting
    // Replace your existing row click handler with this:
    $(document).on("click", "#leadsTable tbody tr", function (e) {
      // Skip if clicking on action buttons, dropdowns, checkboxes, or expand button
      if (
        $(e.target).closest(
          '.action-btn, .filter-dropdown, .filter-header-btn, a, input[type="checkbox"], .expand-row-btn'
        ).length
      ) {
        return;
      }

      // Skip if clicking on details rows
      if ($(this).hasClass("details-row")) {
        return;
      }

      const $currentRow = $(this);

      // When remarks are visible, the full row should behave like the history button.
      const historyOpenedFromIcon = $currentRow.data("historyOpenedFromIcon") === "1";

      // If user just opened history from the profile/history icon for this row,
      // consume the one-time flag and let row click toggle details instead.
      if (historyOpenedFromIcon) {
        $currentRow.data("historyOpenedFromIcon", "0");
      } else if (rowHasVisibleRemarks($currentRow)) {
        e.stopPropagation();
        if (openHistoryForRow($currentRow)) {
          return;
        }
      }

      if (isRemarksModeActive && !$currentRow.hasClass("row-show-details")) {
        // In remarks mode, row click should open the history sidebar
        // even for rows with N/A remarks (rowHasVisibleRemarks was false).
        openHistoryForRow($currentRow);
        return;
      }

      const $currentPhoneInfo = $currentRow.find(".phone-info");
      const $currentDetailsRow = $currentRow.next(".details-row");
      const $expandBtn = $currentRow.find(".expand-row-btn");
      const $downArrow = $expandBtn.find(".down-arrow");
      const $upArrow = $expandBtn.find(".up-arrow");

      // Handle previous row cleanup
      if (currentPhoneRow && currentPhoneRow !== $currentRow[0]) {
        const $prevRow = $(currentPhoneRow);
        const $prevPhone = $prevRow.find(".phone-info");
        const $prevDetailsRow = $prevRow.next(".details-row");
        const $prevExpandBtn = $prevRow.find(".expand-row-btn");
        const $prevDownArrow = $prevExpandBtn.find(".down-arrow");
        const $prevUpArrow = $prevExpandBtn.find(".up-arrow");

        // Encrypt previous row's phone number if it's decrypted
        if ($prevPhone.length && !$prevPhone.hasClass("encrypted")) {
          const realPhone = $prevPhone.data("real-phone");
          const encryptedPhone = encryptPhone(realPhone);
          $prevPhone.text(encryptedPhone).addClass("encrypted");
        }

        // Close previous row's details if it's open
        if ($prevDetailsRow.length && $prevDetailsRow.is(":visible")) {
          $prevDetailsRow.hide();
          $prevDownArrow.show();
          $prevUpArrow.hide();
          $prevRow.removeClass("highlight-row");
        }
      }

      // Handle current row phone number toggle
      if ($currentPhoneInfo.length) {
        if ($currentPhoneInfo.hasClass("encrypted")) {
          // Decrypt current row's phone
          const realPhone = $currentPhoneInfo.data("real-phone");
          $currentPhoneInfo.text(realPhone).removeClass("encrypted");

          // Show up arrow when phone number is visible
          $downArrow.hide();
          $upArrow.show();

          // Show details row if not already visible
          if ($currentDetailsRow.length && !$currentDetailsRow.is(":visible")) {
            $currentDetailsRow.show();
            $currentRow.addClass("highlight-row");
          }
        } else {
          // Encrypt current row's phone
          const realPhone = $currentPhoneInfo.data("real-phone");
          const encryptedPhone = encryptPhone(realPhone);
          $currentPhoneInfo.text(encryptedPhone).addClass("encrypted");

          // Show down arrow when phone number is hidden
          $downArrow.show();
          $upArrow.hide();

          // Hide details row if visible
          if ($currentDetailsRow.length && $currentDetailsRow.is(":visible")) {
            $currentDetailsRow.hide();
            $currentRow.removeClass("highlight-row");
          }
        }
      }

      // Update tracking variables
      currentPhoneRow = $currentRow[0];
      currentExpandedRow = $currentDetailsRow.is(":visible")
        ? $currentRow[0]
        : null;
    });

    // Handle table redraw events — defer ALL per-row work via rAF
    // so the browser paints the new rows FIRST, then we do the DOM
    // cleanup (arrow resets, phone encryption, details-row creation).
    // Without rAF, 300 rows × synchronous DOM queries = visible stutter.
    table.on("draw", function () {
      requestAnimationFrame(() => {
        $("#leadsTable tbody tr").each(function () {
          const $row = $(this);
          const $detailsRow = $row.next(".details-row");
          const $btn = $row.find(".expand-row-btn");
          const $downArrow = $btn.find(".down-arrow");
          const $upArrow = $btn.find(".up-arrow");
          const $phoneInfo = $row.find(".phone-info");

          // Reset expand button icons and remove highlight
          $downArrow.show();
          $upArrow.hide();
          $row.removeClass("highlight-row");

          if ($detailsRow.length && $detailsRow.is(":visible")) {
            $downArrow.hide();
            $upArrow.show();
            $row.addClass("highlight-row");

            // Ensure phone number is visible when expanded
            if ($phoneInfo.length && $phoneInfo.hasClass("encrypted")) {
              const realPhone = $phoneInfo.data("real-phone");
              $phoneInfo.text(realPhone).removeClass("encrypted");
            }
          } else {
            // Ensure phone number is hidden when collapsed
            if ($phoneInfo.length && !$phoneInfo.hasClass("encrypted")) {
              const realPhone = $phoneInfo.data("real-phone");
              const encryptedPhone = encryptPhone(realPhone);
              $phoneInfo.text(encryptedPhone).addClass("encrypted");
            }
          }

          // Handle mobile view for lead source
          if (window.innerWidth <= 900) {
            $row.find(".mobile-source").show();
          } else {
            $row.find(".mobile-source").hide();
          }
        });

        // Reset tracking variables
        currentExpandedRow = null;
        currentPhoneRow = null;
        handleResponsiveBehavior();
        applyRemarksModeToVisibleRows();
      });
    });


    // Handle window resize
    let resizeTimer;
    $(window).on("resize", function () {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(function () {
        handleResponsiveBehavior();
      }, 250);
    });

    // Track current active filter (using global window.currentActiveFilter)

    // Function to handle filter button clicks
    function handleFilterButtonClick(button) {
      const $button = $(button);
      // Use ID directly as it matches the filter keys expected by server
      const actualFilterValue = $button.attr('id') || "";
      const isTeamView = managerToggle && managerToggle.checked === true;
      const isMyLeadsButton = actualFilterValue === "myLeads";
      const effectiveFilterValue = (isTeamView && isMyLeadsButton) ? "" : actualFilterValue;

      console.log("🔖 Filter clicked:", actualFilterValue, "| Effective filter:", effectiveFilterValue || "(all)", "| Team view active:", managerToggle?.checked);

      // If clicking the already active button, deselect it and show all
      if ($button.hasClass("active")) {
        $button.removeClass("active");
        window.currentActiveFilter = ""; // Clear filter
      } else {
        // Remove active class from all buttons in both rows
        $(".tab-row .filter-btn, .filter-row .filter-btn").removeClass(
          "active"
        );
        // Add active class to clicked button
        $button.addClass("active");
        window.currentActiveFilter = effectiveFilterValue;
        console.log("🎯 Filter button applied:", effectiveFilterValue || "(all)");
      }

      // PERF: Kick off count refresh IMMEDIATELY — clears the cached filter-state
      // params so getCurrentFilterState() picks up the new filter, then fires the
      // count API in parallel with the table AJAX. Without this the count update
      // waits for: table AJAX round-trip + draw.dt fires + 50ms timer = very slow.
      clearFilterStateCache();
      updateAllTagCounts();

      // Redraw the table to apply the new filter via AJAX
      table.draw();

      scheduleCountRefresh();
    }

    // Click handler for filter buttons
    $(document).on(
      "click",
      ".tab-row .filter-btn, .filter-row .filter-btn:not(.status-filter-btn):not(.tag-filter-btn)",
      function (e) {
        e.preventDefault();
        handleFilterButtonClick(this);
      }
    );

    // Initialize with "My Leads" as default active
    function initializeFilters() {
      // Default to the active filter from the URL or fall back to the first tab button.
      const firstTabButton = document.querySelector('.tab-row .filter-btn');
      const activeButton = window.currentActiveFilter
        ? document.getElementById(window.currentActiveFilter)
        : null;
      $(".tab-row .filter-btn, .filter-row .filter-btn").removeClass("active");

      if (activeButton) {
        $(activeButton).addClass("active");
      } else if (firstTabButton) {
        $(firstTabButton).addClass("active");
        if (!window.currentActiveFilter) {
          window.currentActiveFilter = firstTabButton.id || "myLeads";
        }
      }
      // REMOVED: table.draw() - the table already drew with correct filter on init
      // This was causing duplicate API calls (draw=1 without filter, draw=2 with filter)
      scheduleCountRefresh();
    }

    // Call initialization after table is ready
    initializeFilters();

    // Optional: Add visual feedback for button interactions
    $(document).on("mouseenter", ".filter-btn", function () {
      if (!$(this).hasClass("active")) {
        $(this).addClass("hover");
      }
    });

    $(document).on("mouseleave", ".filter-btn", function () {
      $(this).removeClass("hover");
    });

    // Handle mobile filter button
    $(document).on("click", ".filter-btn-mobile", function () {
      // Toggle mobile filter panel or show filter options
      $(".filter-row").toggleClass("mobile-visible");
    });
  });
  $(document).on(
    "click",
    ".filter-btn-mobile, .dt-button.filter-btn",
    function () {
      $("#filterModal").show();
      $("body").css("overflow", "hidden");

      // Initialize Select2 after modal is shown
      $(".select-input").select2({
        placeholder: "Select options",
        allowClear: true,
        width: "100%",
        dropdownParent: $("#filterModal"), // Important for proper positioning
      });
    }
  );





  // Update the status modal opening function
  function openStatusModal(leadId, leadName, currentStatus, currentIdentity = '', userUniqueId = '') {
    // Clear previous validation states
    clearValidation();

    // Reset the form completely before populating new lead data
    if ($('#statusForm')[0]) {
      $('#statusForm')[0].reset();
    }
    $('#newStatus').val('');
    $('#budget').val('');
    $('#leadIdentitySelect').val('');
    $('#leadIdentityValue').val('');
    $('#preferredLocation').val('');
    $('#statusNotes').val('');
    $('#followUpDate').val('');
    $('#followUpTime').val('');
    $('#displayFollowUpDate').text('dd-mm-yyyy');
    $('#displayFollowUpTime').text('--:--');
    $('#dateField').hide();
    $('#timeField').hide();
    $('#statusForm .form-group').removeClass('error success');
    $('#statusForm .error-message').text('').hide();

    // Set lead details
    $('#statusLeadName').text(leadName);
    $('#statusLeadId').text(leadId);
    $('#rowId').val(leadId);

    // Store userUniqueId in a hidden field or data attribute
    $('#statusModal').data('userUniqueId', userUniqueId);


    // Destroy any existing Select2 instances first
    if ($('#newStatus').hasClass('select2-hidden-accessible')) {
      $('#newStatus').select2('destroy');
    }

    // Do NOT pre-select the current status — user should always pick a new one
    $('#newStatus').val('');

    // Reinitialize Select2 with proper configuration
    $('#newStatus').select2({
      placeholder: "Select Status",
      allowClear: false,
      width: '100%',
      dropdownParent: $('#statusModal'),
      minimumResultsForSearch: 0
    });

    if ($('#budget').hasClass('select2-hidden-accessible')) {
      $('#budget').select2('destroy');
    }

    $('#budget').select2({
      placeholder: "Select Budget Range",
      allowClear: true,
      width: '100%',
      dropdownParent: $('#statusModal'),
      minimumResultsForSearch: 0
    });

    // Set current lead identity
    if (currentIdentity) {
      $('#leadIdentitySelect').val(currentIdentity);
      $('#leadIdentityValue').val(currentIdentity);
    }

    // Always start with no date/time fields — they appear when user picks a status
    toggleDateTimeFields('');

    // Reset all form fields to remove any error states
    $('#statusForm .form-group').removeClass('error success');
    $('#statusForm .error-message').text('').hide();

    // Show modal with higher z-index
    $('#statusModal').css('z-index', '10050').show();
    $('body').css('overflow', 'hidden');
  }

  function updateDateRangeFilterBackground() {
    const dateFrom = $("#dateFrom").val();
    const dateTo = $("#dateTo").val();
    const dateRangeFilter = $(".date-range-filter");

    // Keep background color unchanged - don't change to red
    // if (dateFrom || dateTo) {
    //     dateRangeFilter.css("background-color", "#df344dff"); // Light red color
    // } else {
    //     dateRangeFilter.css("background-color", ""); // Reset to default
    // }
  }
  function validateReassignForm() {
    let isValid = true;

    // Clear previous errors
    $(".error-message").text("").hide();
    $(".form-group").removeClass("error");

    // Validate assign user
    const assignUser = $("#assignUser").val();
    if (!assignUser) {
      $("#assignUser").next('.select2-container').addClass('error-state');
      $("#assignUserError").text("Please select a user").show();
      isValid = false;
    } else {
      $("#assignUser").next('.select2-container').removeClass('error-state');
    }

    // Validate project name
    const projectName = $("#projectName").val().trim();
    if (!projectName) {
      $("#projectName").closest(".form-group").addClass("error");
      $("#projectNameError").text("Project name is required").show();
      isValid = false;
    } else {
      $("#projectName").closest(".form-group").removeClass("error");
    }

    return isValid;
  }




  // Also call it initially to set the correct state on page load
  updateDateRangeFilterBackground();

  function validateAssignForm() {
    let isValid = true;

    // Clear previous errors
    $(".error-message").text("").hide();
    $(".form-group").removeClass("error");

    // Validate assign to
    const assignTo = $("#assignTo").val();
    if (!assignTo) {
      $("#assignTo").next('.select2-container').addClass('error-state');
      $("#assignToError").text("Please select a user").show();
      isValid = false;
    } else {
      $("#assignTo").next('.select2-container').removeClass('error-state');
    }

    // Validate project name
    const projectName = $("#assignProjectName").val().trim();
    if (!projectName) {
      $("#assignProjectName").closest(".form-group").addClass("error");
      $("#assignProjectNameError").text("Project name is required").show();
      isValid = false;
    } else {
      $("#assignProjectName").closest(".form-group").removeClass("error");
    }

    return isValid;
  }


  $('#price_range').select2({
    placeholder: "Select Price Range",
    allowClear: false,
    width: '100%',
    dropdownParent: $('#statusModal'),
    minimumResultsForSearch: 0
  });

  // Function to clear all validation states
  function clearValidation() {
    // Remove all error/success classes
    const formGroups = [statusField, dateField, timeField, notesField];
    formGroups.forEach(group => {
      group.classList.remove('error', 'success');
    });

    // Clear error messages
    statusError.textContent = '';
    dateError.textContent = '';
    timeError.textContent = '';
    notesError.textContent = '';
  }

  // Function to validate the form
  function handleStatusChange() {
    const selectedStatus = $("#newStatus").val();

    // Clear error state when a status is selected
    if (selectedStatus) {
      $("#statusField").removeClass("error").addClass("success");
      $("#statusError").text("");

      // Show/hide date/time fields based on selected status
      toggleDateTimeFields(selectedStatus);
    } else {
      $("#statusField").removeClass("success").addClass("error");
      $("#statusError").text("Please select a status");
    }
  }



  // Update your status change event listener
  $("#newStatus").on("change", function () {
    const selectedStatus = $(this).val();
    handleStatusChange();
    toggleDateTimeFields(selectedStatus);
  });

  // Also add validation for other fields when they change
  $("#followUpDate").on("change", function () {
    if (this.value) {
      $(this).closest(".form-group").removeClass("error").addClass("success");
      $(this).closest(".form-group").find(".error-message").text("");
    }
  });

  $("#followUpTime").on("change", function () {
    if (this.value) {
      $(this).closest(".form-group").removeClass("error").addClass("success");
      $(this).closest(".form-group").find(".error-message").text("");
    }
  });

  $("#statusNotes").on("input", function () {
    if (this.value.trim()) {
      $(this).closest(".form-group").removeClass("error").addClass("success");
      $(this).closest(".form-group").find(".error-message").text("");
    }
  });

  // Update your validateStatusForm function to use consistent validation
  function validateStatusForm() {
    const selectedStatus = $("#newStatus").val();
    let isValid = true;

    // Clear previous errors
    $(".form-group").removeClass("error");
    $(".error-message").text("").hide();

    // Status validation (using Select2)
    if (!$("#newStatus").val()) {
      $("#newStatus").next('.select2-container').addClass('error-state');
      $("#statusError").text("Please select a status").show();
      isValid = false;
    } else {
      $("#newStatus").next('.select2-container').removeClass('error-state').addClass('success');
    }

    // Notes validation
    if (!$("#statusNotes").val().trim()) {
      $("#statusNotes").closest(".form-group").addClass("error");
      $("#statusNotes").closest(".form-group").find(".error-message")
        .text("Please enter notes").show();
      isValid = false;
    } else {
      $("#statusNotes").closest(".form-group").removeClass("error").addClass("success");
    }

    // Additional validation based on status
    const statusRequiringDateTime = [
      "Pending", "RNR", "Call Back", "Interested", "EOI", "Follow Up",
      "Fix Site Visit", "Site Visit Done", "VC Done", "Converted", "Re site visit", "NQFTP", "Not Connected"
    ];

    if (statusRequiringDateTime.includes(selectedStatus)) {
      // Date validation
      if (!$("#followUpDate").val()) {
        $(".status-date-field").addClass("error");
        $(".status-date-field").find(".error-message")
          .text("Please select a date").show();
        isValid = false;
      } else {
        $(".status-date-field").removeClass("error").addClass("success");
      }

      // Time validation
      if (!$("#followUpTime").val()) {
        $(".status-time-field").addClass("error");
        $(".status-time-field").find(".error-message")
          .text("Please select a time").show();
        isValid = false;
      } else {
        $(".status-time-field").removeClass("error").addClass("success");
      }
    }

    return isValid;
  }

  // Function to toggle date/time fields based on status
  function toggleDateTimeFields(status) {
    const statusRequiringDateTime = [
      'Pending', 'RNR', 'Call Back', 'Interested', 'EOI', 'Follow Up',
      'Fix Site Visit', 'Site Visit Done', 'VC Done', 'Converted', 'Re site visit', 'NQFTP', 'Not Connected'
    ];

    if (statusRequiringDateTime.includes(status)) {
      dateField.style.display = 'block';
      timeField.style.display = 'block';
    } else {
      dateField.style.display = 'none';
      timeField.style.display = 'none';
    }
  }

  // Event listener for status change
  newStatus.addEventListener('change', function () {
    toggleDateTimeFields(this.value);
    validateField(this, statusField, statusError, 'Please select a status');
  });

  // Event listener for date change
  followUpDate.addEventListener('change', function () {
    validateField(this, dateField, dateError, 'Please select a date');
  });

  // Event listener for time change
  followUpTime.addEventListener('change', function () {
    validateField(this, timeField, timeError, 'Please select a time');
  });

  // Event listener for notes input
  statusNotes.addEventListener('input', function () {
    validateField(this, notesField, notesError, 'Please enter notes');
  });

  // Function to validate individual field
  function validateField(field, fieldContainer, errorElement, errorMessage) {
    if (field.hasAttribute('required') && !field.value) {
      fieldContainer.classList.remove('success');
      fieldContainer.classList.add('error');
      errorElement.textContent = errorMessage;
    } else {
      fieldContainer.classList.remove('error');
      fieldContainer.classList.add('success');
      errorElement.textContent = '';
    }
  }


  // Close modal events
  document.getElementById('closeStatusModal').addEventListener('click', closeStatusModal);
  document.getElementById('cancelStatusUpdate').addEventListener('click', closeStatusModal);

  // Close modal when clicking outside
  statusModal.addEventListener('click', function (e) {
    if (e.target === statusModal) {
      closeStatusModal();
    }
  });

  // Close modal with Escape key
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && statusModal.style.display === 'flex') {
      closeStatusModal();
    }
  });

  // Helper function to show notifications
  function showNotification(message, type) {
    // You can implement a notification system here
    alert(`${type.toUpperCase()}: ${message}`);
  }

  // Make openStatusModal function available globally
  window.openStatusModal = openStatusModal;


  $(document).ready(function () {
    // Initialize Select2 for multi-select dropdowns


    $(".select-input").select2({
      placeholder: "Select options",
      allowClear: true,
      width: "100%",
    });

    // Open filter modal
    $(document).on(
      "click",
      ".filter-btn-mobile, .dt-button.filter-btn",
      function () {
        $("#filterModal").show();
        $("body").css("overflow", "hidden");
      }
    );

    // Close filter modal
    $("#closeFilterModal").click(function () {
      $("#filterModal").hide();
      $("body").css("overflow", "auto");
    });

    // Click outside modal to close
    $(".modal-overlay").click(function (e) {
      if ($(e.target).hasClass("modal-overlay")) {
        $("#filterModal").hide();
        $("body").css("overflow", "auto");
      }
    });

    // Toggle filter option buttons
    $(document).on("click", ".filter-option-btn", function () {
      $(this).toggleClass("active");
    });

    // Reset all filters
    $("#resetFilters").click(function () {
      // Clear all input fields in the filter modal
      $('#filterModal input[type="text"]').val("");
      $('#filterModal input[type="date"]').val("");

      // Clear all select2 dropdowns
      $(".select-input").val(null).trigger("change");

      // Clear all table filters
      table.search("").columns().search("").draw();

      // Optional: Close the modal after reset
      // $('#filterModal').hide();
      // $('body').css('overflow', 'auto');
    });

    // Apply filters
    $("#filterForm").submit(function (e) {
      e.preventDefault();

      // Trigger a redraw of the DataTable, which will send the current filter values to the server
      //table.draw();

      // Close modal
      $("#filterModal").hide();
      $("body").css("overflow", "auto");
    });

    // Close modal with Escape key
    $(document).keydown(function (e) {
      if (e.key === "Escape" && $("#filterModal").is(":visible")) {
        $("#filterModal").hide();
        $("body").css("overflow", "auto");
      }
    });
  });



  // ── Position badge colours (shared by both Assign dropdowns) ──────────────
  var _posColors = {
    'P': { bg: '#7c3aed', color: '#fff' },
    'BH': { bg: '#dc2626', color: '#fff' },
    'M': { bg: '#16a34a', color: '#fff' },
    'TL': { bg: '#0d9488', color: '#fff' },
    'U': { bg: '#6b7280', color: '#fff' }
  };

  function _positionTemplate(option) {
    if (!option.id) return option.text;
    var pos = $(option.element).data('position') || '';
    var col = _posColors[pos];
    var name = $('<span>').text(option.text.replace(/\s*-\s*\S+$/, '').trim()).html();
    if (!col) return $('<span>' + name + '</span>');
    return $(
      '<span style="display:flex;align-items:center;gap:8px;">' +
      '<span style="' +
      'display:inline-flex;align-items:center;justify-content:center;' +
      'min-width:28px;height:20px;padding:0 6px;border-radius:4px;' +
      'font-size:10px;font-weight:700;letter-spacing:.5px;' +
      'background:' + col.bg + ';color:' + col.color + ';flex-shrink:0;">' +
      pos +
      '</span>' +
      '<span>' + name + '</span>' +
      '</span>'
    );
  }

  function _positionSelectionTemplate(option) {
    if (!option.id) return option.text;
    var pos = $(option.element).data('position') || '';
    var name = $('<span>').text(option.text.replace(/\s*-\s*\S+$/, '').trim()).html();
    var col = _posColors[pos];
    if (!col) return $('<span>' + name + '</span>');
    return $(
      '<span style="display:flex;align-items:center;gap:6px;">' +
      '<span style="' +
      'display:inline-flex;align-items:center;justify-content:center;' +
      'min-width:26px;height:18px;padding:0 5px;border-radius:4px;' +
      'font-size:10px;font-weight:700;letter-spacing:.5px;' +
      'background:' + col.bg + ';color:' + col.color + ';flex-shrink:0;">' +
      pos +
      '</span>' +
      '<span>' + name + '</span>' +
      '</span>'
    );
  }
  // ────────────────────────────────────────────────────────────────────────────

  // Initialize Select2 for assign to dropdown (Bulk Assign modal)
  function initializeAssignToSelect2() {
    $('#assignTo').select2({
      placeholder: "Select User",
      allowClear: false,
      width: '100%',
      dropdownParent: $('#assignModal'),
      minimumResultsForSearch: 0,
      templateResult: _positionTemplate,
      templateSelection: _positionSelectionTemplate
    });
  }

  // Function to properly destroy assign to Select2 instance
  function destroyAssignToSelect2() {
    if ($('#assignTo').hasClass('select2-hidden-accessible')) {
      $('#assignTo').select2('destroy');
    }
    $('#assignTo').val('');
  }

  // Function to open assign modal
  function openAssignModal() {
    const selectedIds = getSelectedLeadIds();
    if (selectedIds.length === 0) {
      showNotification("Please select at least one lead to assign.", "error");
      return;
    }

    // Update the modal with selected leads info
    $("#selectedLeadsCount").text(selectedIds.length);
    const $selectedLeadsList = $("#selectedLeadsList");
    $selectedLeadsList.empty();

    // Get names of selected leads
    $(".row-checkbox:checked").each(function () {
      const $row = $(this).closest("tr");
      const leadId = $row.find(".lead-id-hidden").text();
      const leadName = $row.find(".lead-info h4").text();
      $selectedLeadsList.append(`
            <div class="selected-lead-item">
                <span class="lead-id">${leadId}</span>
                <span class="lead-name">${leadName}</span>
            </div>
        `);
    });

    // Destroy any existing Select2 instances
    destroyAssignToSelect2();

    // Load users and initialize Select2

    initializeAssignToSelect2();


  }

  // Function to close assign modal
  function closeAssignModal() {
    // Destroy Select2 instances
    destroyAssignToSelect2();

    // Hide modal
    $("body").css("overflow", "auto");
    $("#assignModal").removeClass("active").hide();

    // Reset form
    $("#assignForm")[0].reset();
    $("#selectedLeadsCount").text("0");
    $("#selectedLeadsList").empty();
    $("#assignResponseMessage").empty();
  }

  const userType = "<?php echo $user_type; ?>";
  console.log(userType);

  // Handle assign button clicks with permission check
  $(document).on("click", ".assign-m", function (e) {
    e.preventDefault();
    openAssignModal();
  });

  $(document).on("click", ".assign-u", function (e) {
    e.preventDefault();
    Swal.fire({
      title: "Permission Denied",
      text: "You do not have permission to assign leads. Only managers and CEOs can use this feature.",
      icon: "error",
      confirmButtonText: "OK",
      customClass: {
        confirmButton: "btn btn-primary",
      },
    });
  });

  // Close modal when clicking close button, cancel button, or overlay
  $("#closeAssignModal, #cancelAssign").click(function (e) {
    e.preventDefault();
    closeAssignModal();
  });

  $("#assignModal").click(function (e) {
    if ($(e.target).hasClass("modal-overlay")) {
      closeAssignModal();
    }
  });

  // Prevent modal from closing when clicking inside the modal container
  $("#assignModal .modal-container").click(function (e) {
    e.stopPropagation();
  });

  // Close modal when pressing Escape key
  $(document).keydown(function (e) {
    if (e.key === "Escape" && $("#assignModal").hasClass("active")) {
      closeAssignModal();
    }
  });


  const timeInput = document.getElementById("followUpTime");

  if (timeField && timeInput) {
    timeField.addEventListener("click", function () {
      // Focus and open the native time picker
      timeInput.showPicker?.(); // modern browsers
      timeInput.focus();        // fallback
    });
  }



  function getSelectedLeadIds() {
    const selectedIds = [];
    $(".row-checkbox:checked").each(function () {
      const $row = $(this).closest("tr");

      // First try to get ID from the hidden element in lead column
      let leadId = $row.find(".lead-id-hidden").text().trim();

      // If not found, try to get from the ID column (if visible)
      if (!leadId) {
        leadId = $row.find(".lead-id-section").text().replace("#", "").trim();
      }

      if (leadId) {
        selectedIds.push(leadId);
      }
    });
    return selectedIds;
  }

  // Form submission handling
  // $("#assignForm").submit(function (e) {
  //   e.preventDefault();

  //   // Reset error states
  //   $(".form-group").removeClass("error");
  //   $(".error-message").text("");

  //   // Validate required fields
  //   // if (!$("#assignTo").val()) {
  //   //   $("#assignTo").closest(".form-group").addClass("error");
  //   //   $("#assignTo")
  //   //     .closest(".form-group")
  //   //     .find(".error-message")
  //   //     .text("Please select a team member");
  //   //   $("#assignTo").focus();
  //   //   return false;
  //   // }

  //   const selectedIds = getSelectedLeadIds();
  //   const assignUser = $("#assignTo").val(); // Renamed to match PHP
  //   const projectName = $("#assignProjectName").val(); // Assuming you add this input in your modal
  //   const assignNote = $("#assignNote").val(); // This can be sent as part of notes if needed

  //   // Show loading state
  //   const $submitBtn = $(".submit-btn");
  //   $submitBtn
  //     .html('<i class="fas fa-spinner fa-spin"></i> Assigning...')
  //     .prop("disabled", true);

  //   // Send data to update_status.php for bulk assignment
  //   $.ajax({
  //     url: "update_status.php",
  //     type: "POST",
  //     contentType: "application/json",
  //     data: JSON.stringify({
  //       bulkAssign: true,
  //       rowIds: selectedIds,
  //       assignUser: assignUser,
  //       projectName: projectName,
  //       notes: assignNote, // You might want to handle notes in PHP
  //     }),
  //     success: function (response) {
  //       if (response.status === "success") {
  //         showNotification(
  //           `${selectedIds.length} leads assigned successfully!`,
  //           "success"
  //         );
  //         // Trigger a redraw of the DataTable to reflect changes from the server
  //         table.ajax.reload(null, false); // Reload data without resetting pagination
  //       } else {
  //         showNotification(
  //           `Error assigning leads: ${response.message}`,
  //           "error"
  //         );
  //       }
  //     },
  //     error: function (xhr, status, error) {
  //       console.error("AJAX Error:", status, error, xhr.responseText);
  //       showNotification(
  //         "An error occurred during assignment. Please try again.",
  //         "error"
  //       );
  //     },
  //     complete: function () {
  //       // Reset form and close modal
  //       $("#assignForm")[0].reset();
  //       $submitBtn.html("Assign Leads").prop("disabled", false);
  //       closeAssignModal();

  //       // Uncheck all checkboxes
  //       $(".row-checkbox").prop("checked", false);
  //       $("#selectAll").prop("checked", false);
  //       updateSelectionActions();
  //     },
  //   });

  //   //   this javascirpt is for get the counts of leads status End
  // });



  // WhatsApp Modal Functionality
  function openWhatsappModal(leadName, phoneNumber) {
    $("body").css("overflow", "hidden");
    $("#whatsappModal").addClass("active").show();

    // Populate recipient info
    $("#whatsappRecipientName").text(leadName);
    $("#whatsappRecipientPhone").text(phoneNumber);

    // Store the phone number in the modal for later use
    $("#whatsappModal").data("phone", phoneNumber);
    $("#whatsappModal").data("name", leadName);

    // Clear previous selections and messages
    $(".message-option").removeClass("selected");
    $("#customMessage").val("");
  }

  function closeWhatsappModal() {
    $("body").css("overflow", "auto");
    $("#whatsappModal").removeClass("active").hide();
  }

  function showNotification(message, type) {
    const $notification = $(
      '<div class="notification ' + type + '">' + message + "</div>"
    );
    $("body").append($notification);

    setTimeout(() => $notification.addClass("show"), 10);
    setTimeout(() => {
      $notification.removeClass("show");
      setTimeout(() => $notification.remove(), 300);
    }, 3000);
  }

  function fetchData() {
    const table = $("#leadsTable").DataTable(); // Gets existing DataTable instance
    if (table) {
      table.ajax.reload(null, false);
    } else {
      console.error("DataTable not initialized");
    }
  }

  console.log(
    "Manager toggle element:",
    document.getElementById("managerToggle")
  );

  document.addEventListener("DOMContentLoaded", function () {
    const toggle = document.getElementById("managerToggle");

    if (!toggle) {
      console.error("âš ï¸ Manager toggle not found!");
      return;
    }

    console.log("❌… Toggle found, initial state:", toggle.checked);

    toggle.addEventListener("change", function () {
      console.log("ðŸ”„ Toggle changed! New state:", this.checked);
      // Your actual functionality here
      updateDataAndUI()
    });
  });
  if (managerToggle) {
    // Initial check
    console.log(
      "Initial toggle state:",
      managerToggle.checked ? "ON (Manager View)" : "OFF (Normal View)"
    );

    // Add change event listener
    managerToggle.addEventListener("change", function () {


      // Here you can call your functions that should run when toggle changes
      fetchDataToggle();
    });
  } else {
    console.warn("Manager toggle checkbox not found");
  }

  async function fetchDataToggle(
    page = 1,
    rowsPerPage = 10,
    searchQuery = "",
    filterType = "",
    multiFilters = {}
  ) {
    try {
      const encodedQuery = searchQuery ? encodeURIComponent(searchQuery) : "";
      const encodedFilters = multiFilters
        ? encodeURIComponent(JSON.stringify(multiFilters))
        : "";
      const toggleState = managerToggle.checked ? 1 : 0;

      const url = `update_status.php?page=${page}&rowsPerPage=${rowsPerPage}&searchQuery=${encodedQuery}&filter=${encodeURIComponent(
        filterType
      )}&multiFilters=${encodedFilters}&managerToggle=${toggleState}`;

      const response = await fetch(url);
      // ... rest of your fetch logic
      if (!response.ok) {
        throw new Error(
          `Server Error: ${response.status} ${response.statusText}`
        );
      }
      const text = await response.text();
      let data;
      try {
        data = JSON.parse(text);
      } catch (jsonError) {
        console.error("Invalid JSON response:", text);
        throw new Error("Invalid JSON received from server");
      }

      // console.log('Data fetched:', data);

      // Update hierarchy information if available
      if (data.hierarchyInfo) {
        updateHierarchyUI(data.hierarchyInfo);
      }

      updateDataAndUI()
    } catch (error) {
      console.error("Fetch error:", error);
    }
  }

  // Update getUserLeadsCount to include toggle state
  // function getUserLeadsCount() {
  //     const getAllCountData = async (flag = false) => {
  //         try {
  //             const toggleState = managerToggle ? managerToggle.checked : false;
  //             const response = await fetch(`update_status.php?get_data=1&toggle_enabled=${toggleState ? 1 : 0}`);
  //             const data = await response.json();

  //             // Update all your count elements here
  //             document.getElementById("myLeads").innerHTML = `<i class="bi bi-graph-up"></i> My Leads (${data.myLeads})`;
  //             document.getElementById("bookedLeads").innerHTML = `<i class="bi bi-journal-richtext"></i> Booked (${data.bookedLeads})`;
  //             // Update other counts...

  //         } catch (error) {
  //             console.error("Error fetching data:", error);
  //         }
  //     };
  //     getAllCountData();
  // }

  // Handle WhatsApp button clicks in both main and details rows
  $(document).on(
    "click",
    ".action-btn.whatsapp, .details-row .action-btn.whatsapp",
    function (e) {
      e.stopPropagation();

      // Find the parent row (works for both main row and details row clicks)
      const $row = $(this).closest("tr").is(".details-row")
        ? $(this).closest("tr").prev("tr")
        : $(this).closest("tr");

      // Get lead info
      const leadName = $row.find(".lead-info h4").text().trim();
      const $phoneEl = $row.find(".phone-info");
      // .data() may be unpopulated for lazy-created DOM nodes; .attr() reads
      // directly from the HTML attribute and always works as a fallback.
      const phoneNumber = $phoneEl.data("real-phone")
        || $phoneEl.attr("data-real-phone");

      if (!phoneNumber) {
        showNotification("Phone number not available for this lead", "error");
        return;
      }

      openWhatsappModal(leadName, phoneNumber);
    }
  );

  // Close modal handlers
  $("#closeWhatsappModal, #cancelWhatsapp").click(function (e) {
    e.preventDefault();
    closeWhatsappModal();
  });

  $("#whatsappModal").click(function (e) {
    if ($(e.target).hasClass("modal-overlay")) {
      closeWhatsappModal();
    }
  });

  // Prevent modal from closing when clicking inside
  $("#whatsappModal .modal-container").click(function (e) {
    e.stopPropagation();
  });

  // Close modal with Escape key
  $(document).keydown(function (e) {
    if (e.key === "Escape" && $("#whatsappModal").hasClass("active")) {
      closeWhatsappModal();
    }
  });

  $(document).ready(function () {
    // Store the current lead's phone number and name
    var currentPhone = "";
    var currentName = "";

    // When opening the modal, set the phone and name
    $(document).on(
      "click",
      ".action-btn.whatsapp, .details-row .action-btn.whatsapp",
      function (e) {
        e.stopPropagation();

        // Find the parent row
        const $row = $(this).closest("tr").is(".details-row")
          ? $(this).closest("tr").prev("tr")
          : $(this).closest("tr");

        // Get lead info - ensure we get a string value
        currentName = $row.find(".lead-info h4").text().trim() || "";

        // Safely get phone number — use .attr() as fallback for lazy-created DOM
        // nodes where jQuery's .data() cache may not be populated yet.
        const $phoneEl = $row.find(".phone-info");
        const phoneData = $phoneEl.data("real-phone") || $phoneEl.attr("data-real-phone");
        currentPhone =
          typeof phoneData === "number"
            ? phoneData.toString()
            : (phoneData || "").toString().trim();

        if (!currentPhone) {
          showNotification("Phone number not available for this lead", "error");
          return;
        }

        // Populate modal
        $("#whatsappRecipientName").text(currentName);
        $("#whatsappRecipientPhone").text(currentPhone);

        // Clear previous message
        $("#customMessage").val("");

        // Open modal
        $("#whatsappModal").show();
        $("body").css("overflow", "hidden");
      }
    );


    // Handle quick message selection
    $(".message-option").click(function (e) {
      e.preventDefault();
      let message = $(this).data("message") || "";
      message = message
        .replace("[Name]", currentName)
        .replace("[Your Name]", "Sales Agent")
        .replace("[Company]", "Your Company")
        .replace("[Project]", "Project Name");
      $("#customMessage").val(message);
      updateWhatsAppLink(message);
    });

    // Update message in real-time
    $("#customMessage").on("input", function () {
      updateWhatsAppLink($(this).val());
    });

    // Update WhatsApp link - with proper error handling
    function updateWhatsAppLink(message) {
      try {
        if (!currentPhone || typeof currentPhone.replace !== "function") {
          console.error("Invalid phone number:", currentPhone);
          return;
        }

        // Ensure phone is a string and clean it
        const phoneStr = currentPhone.toString();
        const formattedPhone = phoneStr.replace(/\D/g, "");

        if (!formattedPhone) {
          console.error("Could not format phone number:", currentPhone);
          return;
        }

        const encodedMessage = encodeURIComponent(message || "");
        const whatsappUrl = `https://api.whatsapp.com/send?phone=${formattedPhone}&text=${encodedMessage}`;

        // Update the send button
        const $sendBtn = $("#sendWhatsappLink");
        $sendBtn.attr("href", whatsappUrl);

        // For debugging
        console.log("Generated WhatsApp URL:", whatsappUrl);
      } catch (error) {
        console.error("Error generating WhatsApp link:", error);
      }
    }

    // Handle send button click
    $(document).on("click", "#sendWhatsappLink", function (e) {
      const message = $("#customMessage").val().trim();

      if (!message) {
        e.preventDefault();
        showNotification("Please enter a message", "error");
        return false;
      }

      // Verify the link is properly set
      const whatsappUrl = $(this).attr("href");
      if (!whatsappUrl || whatsappUrl === "#") {
        e.preventDefault();
        console.error("Invalid WhatsApp URL");
        showNotification("Could not generate WhatsApp link", "error");
        return false;
      }

      // Link is valid, allow default behavior
      return true;
    });

    // Close modal handlers
    $("#closeWhatsappModal, #cancelWhatsapp").click(function (e) {
      e.preventDefault();
      closeWhatsappModal();
    });

    function closeWhatsappModal() {
      $("#whatsappModal").hide();
      $("body").css("overflow", "auto");
    }

    // Close when clicking outside
    $("#whatsappModal").click(function (e) {
      if ($(e.target).hasClass("modal-overlay")) {
        closeWhatsappModal();
      }
    });

    // Prevent modal from closing when clicking inside
    $("#whatsappModal .modal-container").click(function (e) {
      e.stopPropagation();
    });

    // Close with Escape key
    $(document).keydown(function (e) {
      if (e.key === "Escape" && $("#whatsappModal").is(":visible")) {
        closeWhatsappModal();
      }
    });

    function showNotification(message, type) {
      const $notification = $(
        '<div class="notification ' + type + '">' + message + "</div>"
      );
      $("body").append($notification);

      setTimeout(() => $notification.addClass("show"), 10);
      setTimeout(() => {
        $notification.removeClass("show");
        setTimeout(() => $notification.remove(), 300);
      }, 3000);
    }
  });

  // Replace the existing status filter initialization with this:

  function getLeadsDataTableInstance() {
    if (!$.fn.DataTable.isDataTable('#leadsTable')) return null;
    try {
      return $('#leadsTable').DataTable();
    } catch (err) {
      console.warn('Could not fetch DataTable instance:', err);
      return null;
    }
  }

  // Initialize Select2 for status dropdown
  const $filterStatusSelect = $('#filterStatusSelect');
  if ($filterStatusSelect.length) {
    $filterStatusSelect.select2({
      placeholder: "Select Status",
      allowClear: true,
      width: '100%',
      dropdownAutoWidth: true,
      dropdownParent: $(document.body),
      minimumResultsForSearch: -1
    });
  }

  // Update the status filter button to work with Select2
  $(document).on("click", ".status-filter-btn", function (e) {
    e.stopPropagation();

    // Custom Status dropdown exists on this page; avoid conflicting Select2 open.
    if ($('.status-filter-dropdown').length) {
      return;
    }

    $('#filterStatusSelect').select2('open');
    // Close tag filter dropdown when opening status dropdown
    $(".tag-filter-container").removeClass("active");
    $(".tag-filter-dropdown").slideUp(200);
  });

  // Handle status selection
  $('#filterStatusSelect').on('change', function () {
    const rawValue = $(this).val();
    const selectedStatus = (rawValue || '').toString().trim();
    const isAllOption = selectedStatus === '' || selectedStatus.toLowerCase() === 'all';
    currentFilter = isAllOption ? '' : selectedStatus;

    // Ensure we have a multiFilters bag to persist status filter for the backend
    if (!window.multiFilters) {
      window.multiFilters = {};
    }

    if (isAllOption) {
      delete window.multiFilters.status;
    } else {
      window.multiFilters.status = selectedStatus;
    }

    console.log("[DEBUG] Filter Status:", currentFilter);

    const dt = getLeadsDataTableInstance();
    if (dt) {
      dt.draw(false);
      scheduleCountRefresh();
      setTimeout(updateFilterCounter, 200);
      invalidateUniqueValuesCache();
      setTimeout(buildUniqueValuesCache, 2000) // PERF: deferred;
    }

    setTimeout(() => {
      if (window.clearFiltersManager) {
        window.clearFiltersManager.updateVisibility();
      }
    }, 300);
  });

  // Close dropdown when clicking outside
  $(document).on('click', function (e) {
    if (!$(e.target).closest('.select2-container').length &&
      !$(e.target).closest('.status-filter-btn').length) {
      $('#filterStatusSelect').select2('close');
    }

    // Auto-close Tag Filter
    if (!$(e.target).closest('.tag-filter-container').length) {
      $('.tag-filter-container').removeClass('active');
      const $tagDropdown = $('.tag-filter-dropdown');
      $tagDropdown.slideUp(200);
      window.__iosDropdownPortal.close($tagDropdown[0]);
    }
  });

  // ----------------------------------------------------
  // Tag Filter Logic (Hot, Warm, Cold)
  // ----------------------------------------------------
  $(document).on("click", ".tag-filter-btn", function (e) {
    e.stopPropagation();
    const $container = $(this).closest(".tag-filter-container");
    const $tagDropdown = $(".tag-filter-dropdown");
    const wasActive = $container.hasClass("active");

    // Close other dropdowns if needed
    $(".status-filter-container").removeClass("active");
    $(".status-filter-dropdown").hide();
    if ($filterStatusSelect.length) {
      $filterStatusSelect.select2('close');
    }

    if (wasActive) {
      $container.removeClass("active");
      $tagDropdown.slideUp(200);
      window.__iosDropdownPortal.close($tagDropdown[0]);
      $(".mobile-dropdown-backdrop").remove();
    } else {
      $container.addClass("active");
      $tagDropdown.slideDown(200);
      window.__iosDropdownPortal.open($tagDropdown[0], this);
    }
  });

  $(document).on("click", ".tag-option", function (e) {
    e.stopPropagation();
    const selectedTag = $(this).data("tag");

    // Update active class
    $(".tag-option").removeClass("active");
    $(this).addClass("active");

    // Store in filter state bags FIRST, then close and draw
    if (!window.multiFilters) {
      window.multiFilters = {};
    }
    if (!window.headerDropdownFilters) {
      window.headerDropdownFilters = {};
    }

    if (!selectedTag || selectedTag === "All") {
      delete window.multiFilters.lead_identity;
      delete window.headerDropdownFilters.lead_identity;
    } else {
      window.multiFilters.lead_identity = selectedTag;
      window.headerDropdownFilters.lead_identity = selectedTag;
    }

    console.log("[DEBUG] Filter Tag:", selectedTag);

    // Redraw table IMMEDIATELY (before closing dropdown)
    const dt = getLeadsDataTableInstance();
    if (dt) {
      dt.draw(false);
      if (typeof scheduleCountRefresh === "function") {
        scheduleCountRefresh();
      }
      if (typeof updateFilterCounter === "function") {
        setTimeout(updateFilterCounter, 200);
      }
      setTimeout(function () {
        if (window.clearFiltersManager) {
          window.clearFiltersManager.updateVisibility();
        }
      }, 300);
    }

    // Close dropdown AFTER filter is fired
    $(".tag-filter-container").removeClass("active");
    $(".tag-filter-dropdown").slideUp(150);
    window.__iosDropdownPortal.close($(".tag-filter-dropdown")[0]);
    $(".mobile-dropdown-backdrop").remove();
  });

  // (outside-click for tag dropdown is handled by the merged document handler above)


  const historyToggle = document.getElementById('includeHistoryToggle');


  // Update description text based on toggle state
  function updateHistoryDescription() {
    const historyToggle = document.getElementById('includeHistoryToggle');
    const historyDescription = document.getElementById('historyDescription');

    if (historyToggle.checked) {
      historyDescription.innerHTML = 'All previous interactions, notes, and status updates will be transferred to the new assignee.';
    } else {
      historyDescription.innerHTML = 'Lead will be reassigned as a fresh lead. Previous history will not be visible to the new assignee.';
    }
  }

  // Initialize description
  updateHistoryDescription();

  // Handle toggle change
  historyToggle.addEventListener('change', function () {
    updateHistoryDescription();

    // Add visual feedback
    const container = document.querySelector('.history-toggle-container');
    container.style.transform = 'scale(0.98)';
    setTimeout(() => {
      container.style.transform = 'scale(1)';
    }, 150);
  });

  // Handle form submission with history option
  document.getElementById('submitReassign').addEventListener('click', function () {
    const includeHistory = historyToggle.checked;
    const formData = new FormData(document.getElementById('reassignForm'));

    // Add history option to form data
    formData.append('includeHistory', includeHistory ? '1' : '0');

    console.log('Reassigning with history:', includeHistory);

    // Your existing form submission logic here
    // Example:
    // submitReassignForm(formData);
  });


  function monitorDropdownFilterChanges() {
    // Monitor filter option changes in dropdowns
    $(document).on("change", ".filter-option", function () {
      setTimeout(() => {
        if (window.clearFiltersManager) {
          window.clearFiltersManager.updateVisibility();
        }
      }, 100);
    });

    // Monitor when dropdown filters are applied (when table redraws)
    $(document).on('draw.dt', function () {
      setTimeout(() => {
        if (window.clearFiltersManager) {
          window.clearFiltersManager.updateVisibility();
        }
      }, 200);
    });

    // Monitor search input in dropdowns
    $(document).on("keyup", ".filter-search-input", function () {
      // Debounce the visibility check
      clearTimeout(window.dropdownSearchTimeout);
      window.dropdownSearchTimeout = setTimeout(() => {
        if (window.clearFiltersManager) {
          window.clearFiltersManager.updateVisibility();
        }
      }, 300);
    });

    // Monitor when dropdowns are closed (filters applied)
    $(document).on('click', '.filter-close-btn, .dt-close-btn', function () {
      setTimeout(() => {
        if (window.clearFiltersManager) {
          window.clearFiltersManager.updateVisibility();
        }
      }, 150);
    });
  }



  // Clear All Filters Functionality
  function initClearFiltersButton() {
    // Create and inject the button if it doesn't exist
    if (!$('#clearAllFiltersBtn').length) {
      $('body').append(`
            <button class="clear-filters-btn" id="clearAllFiltersBtn">
                <i class="fas fa-times-circle"></i>
                Clear All Filters
            </button>
        `);

      monitorDropdownFilterChanges();
    }

    const $clearBtn = $('#clearAllFiltersBtn');

    // Function to check if any filters are active
    function areFiltersActive() {
      // Check multiFilters
      if (window.multiFilters && Object.keys(window.multiFilters).length > 0) {
        const hasActiveMultiFilters = Object.values(window.multiFilters).some(value =>
          value !== undefined && value !== null && value !== ''
        );
        if (hasActiveMultiFilters) return true;
      }

      // Check date filters
      if ($('#dateFrom').val() || $('#dateTo').val()) return true;

      // Check isolated filter inputs and multi-select values
      const isolatedFilters = [
        'isolatedFilterCustumername',
        'isolatedFilterEmail',
        'isolatedFilterContactnumber',
        'isolatedFilterLocation',
        'isolatedFilterSourceOfLead',
        'isolatedFilterStatus',
        'isolatedFilterAssignedProjectName',
        'isolatedFilterAssigneduserName',
        'isolatedFilterAssignedIdentity',
        'isolatedFilterBudget'
      ];

      // Check for multi-select values (tags)
      if (window.multiSelectValues && Object.keys(window.multiSelectValues).length > 0) {
        for (const inputId of isolatedFilters) {
          if (window.multiSelectValues[inputId] &&
            Array.isArray(window.multiSelectValues[inputId]) &&
            window.multiSelectValues[inputId].length > 0) {
            return true;
          }
        }
      }

      // Check for regular input values
      for (const filter of isolatedFilters) {
        if ($(`#${filter}`).val().trim()) return true;
      }

      // Check header dropdown filters - IMPROVED DETECTION
      if (window.headerDropdownFilters && Object.keys(window.headerDropdownFilters).length > 0) {
        const hasHeaderFilters = Object.values(window.headerDropdownFilters).some(value =>
          value !== undefined && value !== null && value !== '' && value !== 'All'
        );
        if (hasHeaderFilters) return true;
      }

      // Check column search filters
      if (table && table.search()) return true;

      // Check individual column filters - IMPROVED DETECTION
      if (table) {
        const columns = table.columns();
        for (let i = 0; i < columns.count(); i++) {
          const search = columns[i].search();
          if (search && search !== "" && search !== "All") {
            return true;
          }
        }
      }

      // Check status filter; fall back to window scope to avoid ReferenceError when function is used globally
      const activeFilterValue = (typeof window !== "undefined" && typeof window.currentActiveFilter !== "undefined")
        ? window.currentActiveFilter
        : "";

      if (activeFilterValue && activeFilterValue !== "" && activeFilterValue !== "myLeads") {
        return true;
      }

      // Check filter dropdown checkboxes (excluding "All" options) - IMPROVED
      const activeOptions = $('.filter-option:checked[value!=""][value!="All"]');
      if (activeOptions.length > 0) {
        // Verify that at least one non-"All" option is actually checked
        let hasRealSelection = false;
        activeOptions.each(function () {
          if ($(this).val() && $(this).val() !== "All") {
            hasRealSelection = true;
            return false; // break the loop
          }
        });
        if (hasRealSelection) return true;
      }

      return false;
    }

    // Function to update button visibility
    function updateClearButtonVisibility() {
      if (areFiltersActive()) {
        $clearBtn.addClass('show').fadeIn(300);
      } else {
        $clearBtn.removeClass('show').fadeOut(300);
      }
    }

    // Function to clear all filters
    function clearAllFilters() {
      console.log('Clearing all filters...');

      // 1. Clear multiFilters object completely
      window.multiFilters = {};

      // 2. Clear header dropdown filters (column filters)
      window.headerDropdownFilters = {};

      // 3. Clear date filters
      $('#dateFrom').val('');
      $('#dateTo').val('');
      $('#isolatedFilterStartDate').val('');
      $('#isolatedFilterEndDate').val('');

      // 4. Clear isolated filter inputs and multi-select values
      const multiSelectInputs = [
        'isolatedFilterCustumername',
        'isolatedFilterEmail',
        'isolatedFilterContactnumber',
        'isolatedFilterLocation',
        'isolatedFilterSourceOfLead',
        'isolatedFilterStatus',
        'isolatedFilterAssignedProjectName',
        'isolatedFilterAssigneduserName',
        'isolatedFilterAssignedIdentity',
        'isolatedFilterBudget'
      ];

      // Clear multi-select values object
      if (window.multiSelectValues) {
        window.multiSelectValues = {};
      }

      // Clear all multi-select tags from DOM
      $('.multi-select-tags').empty();

      // Clear input values
      multiSelectInputs.forEach(inputId => {
        $(`#${inputId}`).val('');
      });



      // 5. Reset all column dropdown checkboxes to "All"
      $('.filter-header').each(function () {
        const $header = $(this);
        const $dropdown = $header.find('.filter-dropdown, .filter-options');
        const $allOption = $dropdown.find('.filter-option[value=""]');
        if ($allOption.length) {
          $dropdown.find('.filter-option').prop('checked', false);
          $allOption.prop('checked', true);
        }
      });

      // 6. Clear column searches in DataTable - OPTIMIZED for ID and Created At
      if (table && $.fn.DataTable.isDataTable("#leadsTable")) {
        table.search(''); // Clear global search
        // OPTIMIZED: Clear all column searches, with special handling for ID and Created At
        table.columns().every(function () {
          const colIdx = this.index();
          this.search('', false, false);
        });
      }

      // 7. Reset active filter buttons and set to default
      $('.filter-btn').removeClass('active');

      // Set default filter based on current view mode
      if (managerToggle && managerToggle.checked) {
        // In team view - don't set "My Leads" filter (causes SQL error)
        window.currentActiveFilter = '';
      } else {
        // In individual view - set "My Leads" as default
        window.currentActiveFilter = 'myLeads';
        // Set "My Leads" button as active
        $('.tab-row .filter-btn').first().addClass('active');
      }

      // 8. Clear status filter
      $('#filterStatusSelect').val(null).trigger('change');
      $('.status-option').removeClass('active');
      $('.status-option[data-status="All"]').addClass('active');

      // 9. Clear session storage
      sessionStorage.removeItem('lead_filter_start_date');
      sessionStorage.removeItem('lead_filter_end_date');

      // 9b. Clear any filters that were derived from URL params
      window.__filterUserFromUrl = null;
      window.urlFilterUser = '';
      window.urlTeamView = '';

      // 9c. Reset URL back to clean base path (no query params)
      try {
        const cleanUrl = `${window.location.origin}${window.location.pathname}`;
        window.history.replaceState({}, '', cleanUrl);
      } catch (e) {
        console.warn('Unable to clean URL on clear filters:', e);
      }

      // 10. Do NOT reset manager toggle - keep current view mode (individual or team)
      // Users may want to clear filters while staying in team view

      // 11. IMPORTANT: Trigger the isolated apply filters functionality
      // This will apply the cleared filters and refresh the table
      $("#isolatedApplyFiltersBtn").trigger('click');

      // 12. OPTIMIZED: Clear filter cache, update counts instantly, then redraw table
      clearFilterStateCache();
      updateAllTagCounts();

      // Trigger table redraw to reflect the cleared filters
      if (table && $.fn.DataTable.isDataTable("#leadsTable")) {
        table.draw(false); // false = don't reset pagination
      }

      // 13. Update UI
      updateClearButtonVisibility();
      updateDateRangeFilterBackground();
      resetFilterDropdowns();


      // 14. Show confirmation
      showNotification('All filters have been cleared', 'success');
    }

    function resetFilterDropdowns() {
      $('.filter-header').each(function () {
        const $header = $(this);
        // Try both .filter-dropdown and .filter-options selectors to find the dropdown
        const $dropdown = $header.find('.filter-dropdown, .filter-options');
        const $allOption = $dropdown.find('.filter-option[value=""]');

        if ($allOption.length) {
          // Uncheck all other options, check "All"
          $dropdown.find('.filter-option').prop('checked', false);
          $allOption.prop('checked', true);
        }
      });

      // Also clear headerDropdownFilters to ensure column filters are reset
      window.headerDropdownFilters = {};

      // OPTIMIZED: Clear all column searches in DataTable (including ID and Created At)
      if (table && $.fn.DataTable.isDataTable("#leadsTable")) {
        table.columns().every(function () {
          const colIdx = this.index();
          this.search('', false, false);
        });
      }
    }


    // Event listener for clear button
    $clearBtn.on('click', clearAllFilters);

    // Clear filters when page is closed/switched (beforeunload, visibilitychange, pagehide)
    function clearFiltersOnPageLeave() {
      // Clear header dropdown filters (column filters)
      if (window.headerDropdownFilters) {
        window.headerDropdownFilters = {};
      }
      // Clear column searches in DataTable if table exists
      if (table && $.fn.DataTable.isDataTable("#leadsTable")) {
        table.columns().search('');
      }
    }

    // Listen for page unload/visibility change
    window.addEventListener('beforeunload', clearFiltersOnPageLeave);
    window.addEventListener('pagehide', clearFiltersOnPageLeave);
    document.addEventListener('visibilitychange', function () {
      if (document.hidden) {
        clearFiltersOnPageLeave();
      }
    });

    // Monitor filter changes to update button visibility
    const eventsToMonitor = [
      // Date filters
      'change', '#dateFrom, #dateTo',

      // Isolated filters
      'input change',
      '#isolatedFilterCustumername, #isolatedFilterEmail, #isolatedFilterContactnumber, ' +
      '#isolatedFilterLocation, #isolatedFilterSourceOfLead, #isolatedFilterStatus, ' +
      '#isolatedFilterAssignedProjectName, #isolatedFilterAssigneduserName, ' +
      '#isolatedFilterAssignedIdentity, #isolatedFilterBudget',

      // Column filters
      'change', '.filter-option',

      // Search
      'keyup', '.dataTables_filter input',

      // Status filter
      'click', '.status-option',

      // Manager toggle
      'change', '#managerToggle'
    ];

    // Add event listeners for all filter changes
    for (let i = 0; i < eventsToMonitor.length; i += 2) {
      $(document).on(eventsToMonitor[i], eventsToMonitor[i + 1], function () {
        setTimeout(updateClearButtonVisibility, 100);
      });
    }

    // Also monitor DataTable events
    $(document).on('draw.dt', function () {
      setTimeout(updateClearButtonVisibility, 100);
    });

    // Monitor URL parameters
    function checkUrlParams() {
      const urlParams = new URLSearchParams(window.location.search);
      if (urlParams.has('managerView') || urlParams.has('filterUser') ||
        urlParams.has('start_date') || urlParams.has('end_date')) {
        updateClearButtonVisibility();
      }
    }

    // Initial check
    setTimeout(updateClearButtonVisibility, 1000);

    // Check URL params on load
    setTimeout(checkUrlParams, 500);

    return {
      updateVisibility: updateClearButtonVisibility,
      clearAll: clearAllFilters
    };
  }

  // Initialize the clear filters button when DOM is ready
  $(document).ready(function () {
    // Wait a bit for the table to initialize
    setTimeout(() => {
      window.clearFiltersManager = initClearFiltersButton();
      monitorDropdownFilterChanges();
    }, 2000);
  });

  // Also add this to your existing manager toggle change handler
  if (managerToggle) {
    managerToggle.addEventListener("change", function (e) {
      if (managerToggle.dataset.selfLock === '1') {
        managerToggle.checked = false;
        return;
      }
      setTimeout(() => {
        if (window.clearFiltersManager) {
          window.clearFiltersManager.updateVisibility();
        }
      }, 300);
    });
  }



  function validateAddLeadForm() {
    const name = $("#leadName").val().trim();
    const phone = $("#leadPhone").val().trim();
    const email = $("#leadEmail").val().trim();
    const project = $("#leadProject").val().trim();
    const source = $("#leadsource").val();

    // Reset all error states first
    $(".form-group").removeClass("error");
    $(".error-message").text("").hide();

    let isValid = true;

    // Validate name
    if (name === "") {
      showFieldError($("#leadName")[0], "Name is required");
      isValid = false;
    }

    // Validate phone
    if (phone === "") {
      showFieldError($("#leadPhone")[0], "Phone number is required");
      isValid = false;
    } else if (!/^[6-9]\d{9}$/.test(phone)) {
      showFieldError($("#leadPhone")[0], "Please enter a valid 10-digit Indian mobile number");
      isValid = false;
    }

    // Validate email (required field)
    if (email === "") {
      showFieldError($("#leadEmail")[0], "Email is required");
      isValid = false;
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      showFieldError($("#leadEmail")[0], "Please enter a valid email address");
      isValid = false;
    }

    // Validate project
    if (project === "") {
      showFieldError($("#leadProject")[0], "Project is required");
      isValid = false;
    }

    // Validate source
    if (!source) {
      showFieldError($("#leadsource")[0], "Lead source is required");
      isValid = false;
    }

    // Enable/disable the submit button
    $("#submitLead").prop("disabled", !isValid);

    return isValid;
  }



  // Add this function to handle real-time validation
  function setupAddLeadValidation() {
    // Get all required input fields - ADD EMAIL to required fields
    const requiredFields = ["#leadName", "#leadPhone", "#leadEmail", "#leadProject", "#leadsource"];

    // Add event listeners to all required fields
    requiredFields.forEach(field => {
      $(field).on("input change", function () {
        validateAddLeadForm();

        // Additional field-specific validation
        if (field === "#leadPhone") {
          const phone = $(this).val().trim();
          if (phone.length > 0 && !/^[6-9]/.test(phone)) {
            $(this).closest(".form-group").addClass("error");
            $(this).closest(".form-group").find(".error-message")
              .text("Indian mobile numbers must start with 6, 7, 8, or 9");
          } else {
            $(this).closest(".form-group").removeClass("error");
            $(this).closest(".form-group").find(".error-message").text("");
          }
        }

        // Email validation
        if (field === "#leadEmail") {
          const email = $(this).val().trim();
          if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            $(this).closest(".form-group").addClass("error");
            $(this).closest(".form-group").find(".error-message")
              .text("Please enter a valid email address");
          } else if (!email) {
            $(this).closest(".form-group").addClass("error");
            $(this).closest(".form-group").find(".error-message")
              .text("Email is required");
          } else {
            $(this).closest(".form-group").removeClass("error");
            $(this).closest(".form-group").find(".error-message").text("");
          }
        }
      });
    });
  }



  function updateRow(id) {
    console.log("this (updateRow) fcuntion is calling");
    const status = document.querySelector("#status").value; // Get new status from modal
    const notes = document.querySelector("#notes").value; // Get new notes from modal
    const followUpDate = document.querySelector("#followUpDate").value; // Get follow-up date from modal
    const followUpTime = document.querySelector("#followUpTime").value; // Get follow-up time from modal
    const leadIdentityInput = document.querySelector("#leadIdentityValue");
    const leadIdentity = leadIdentityInput ? leadIdentityInput.value : null;

    // console.log(`Lead Identity in updateRow: ${leadIdentity}`);

    if (leadIdentity === null) {
      console.log("No lead identity selected.");
    }

    // Send data to server
    fetch("update_status.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify({
        update: true,
        rowId: id,
        status: status,
        notes: notes,
        followUpDate: followUpDate,
        followUpTime: followUpTime,
        lead_identity: leadIdentity, // Include lead_identity
      }),
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.status === "success") {
          // Update the table row directly without refreshing
          updateTableRow(id, status, notes, leadIdentity);

          // Update the follow-up history
          if (data.history) {
            populateFollowUpHistory(data.history); // Function to update the history in the modal
          }

          // Notify the user if the Google Calendar event was created
          // if (data.google_calendar_event) {
          //     alert(`Google Calendar Event Created Successfully! \nView it here: ${data.google_calendar_event}`);
          // } else if (data.google_calendar_error) {
          //     console.warn(data.google_calendar_error);
          //     alert('The update was successful, but there was an issue with the Google Calendar integration.');
          // }

          // Close the modal after a successful update
          const updateModal = bootstrap.Modal.getInstance(
            document.querySelector("#statusModal")
          );
          updateModal.hide();
          // Reset the form
          // const form = document.querySelector('#updateStatusForm');
          // form.reset();

          // Clear the modal input fields
          document.querySelector("#status").value = "";
          document.querySelector("#notes").value = "";
          document.querySelector("#followUpDate").value = "";
          document.querySelector("#followUpTime").value = "";
          document.querySelector("#leadIdentity").value = "";
          const leadIdentityButtons = document.querySelectorAll(
            "#leadIdentity button"
          );
          leadIdentityButtons.forEach((btn) => btn.classList.remove("active"));
          // Optional: reset <select> to a blank option (if needed)
          document.querySelector("#status").selectedIndex = 0;
          // Set the "None" button as active by default
          const noneButton = document.querySelector(
            '#leadIdentity button[data-value=""]'
          );
          if (noneButton) {
            noneButton.classList.add("active");
            document.querySelector("#leadIdentityValue").value = ""; // Optional: reinforce that it's cleared
          }
        } else {
          alert(data.message || "Error updating row. Please try again.");
        }
      })
      .catch((error) => {
        console.error("Error:", error);
        alert(
          `Error fetching data. Please try again. Details: ${error.message}`
        );
      });
  }

  function updateTableRow(rowId, status, notes, lead_identity) {
    const row = document.querySelector(`tr[data-id="${rowId}"]`);

    if (row) {
      const statusButton = row.querySelector("button"); // Assuming status is updated in a button
      const viewRemarksButton = row.querySelector(".viewremarks-btn"); // Button to view remarks
      const leadIdentityWrapper = row.querySelector(".user-name-wrapper");

      // Update status text
      if (statusButton) {
        statusButton.textContent = status;

        // Remove old status classes
        statusButton.classList.remove(
          "status-pending",
          "status-fake",
          "status-rnr",
          "status-callback",
          "status-booked",
          "status-not-interested",
          "status-interested",
          "status-follow-up",
          "status-visit",
          "status-visit-done",
          "status-eoi-collected"
        );

        // Add the new class based on the updated status
        let statusClass = "";
        switch (status) {
          case "Pending":
            statusClass = "status-pending";
            break;
          case "Fake":
            statusClass = "status-fake";
            break;
          case "RNR":
            statusClass = "status-rnr";
            break;
          case "Call Back":
            statusClass = "status-callback";
            break;
          case "Already Booked":
            statusClass = "status-booked";
            break;
          case "Not Interested":
            statusClass = "status-not-interested";
            break;
          case "Interested":
            statusClass = "status-interested";
            break;
          case "Follow Up":
            statusClass = "status-follow-up";
            break;
          case "Fix Site Visit":
            statusClass = "status-visit";
            break;
          case "Site Visit Done":
            statusClass = "status-visit-done";
            break;
          case "VC Done":
            statusClass = "status-vc-done";
            break;
          case "Converted":
            statusClass = "status-eoi-collected";
            break;
          case "Re site visit":
            statusClass = "status-re-site-visit";
            break;
          case "NQFTP":
            statusClass = "status-NQFTP";
            break;
          case "Not Connected":
            statusClass = "not-connected";
            break;
          default:
            statusClass = ""; // No class if the status doesn't match any case
            break;
        }

        // Add the new class to the button
        if (statusClass) {
          statusButton.classList.add(statusClass);
        }
      } else {
        console.error("Status button not found in the row.");
      }

      const remarksCell = row.querySelector("td.remarks-column .remarks-cell-scroll, td.remarks-column .remarks-info");
      if (remarksCell) {
        const rawNotes = notes == null ? "" : String(notes).trim();
        if (rawNotes) {
          remarksCell.classList.remove("remarks-info");
          remarksCell.classList.add("remarks-cell-scroll");
          remarksCell.textContent = rawNotes;
          remarksCell.title = rawNotes;
        } else {
          remarksCell.classList.remove("remarks-cell-scroll");
          remarksCell.classList.add("remarks-info");
          remarksCell.textContent = "N/A";
          remarksCell.removeAttribute("title");
        }
      }
      if (leadIdentityWrapper) {
        let leadIdentityIcon = "";

        switch (lead_identity) {
          case "Hot":
            leadIdentityIcon =
              '<sub><i class="bi bi-fire hot-icon" style="font-size: 1.5em; font-weight: bold;"></i></sub>';
            break;
          case "Warm":
            leadIdentityIcon =
              '<sub><i class="bi bi-sun warm-icon" style="font-size: 1.5em; font-weight: bold;"></i></sub>';
            break;
          case "Cold":
            leadIdentityIcon =
              '<sub><i class="bi bi-snow cold-icon" style="font-size: 1.5em; font-weight: bold;"></i></sub>';
            break;
          default:
            leadIdentityIcon = ""; // No icon if the lead_identity doesn't match any case
            break;
        }

        // Update the lead identity icon
        leadIdentityWrapper.innerHTML =
          leadIdentityWrapper.querySelector("span").outerHTML +
          leadIdentityIcon;
      } else {
        console.error("Lead Identity wrapper not found in the row.");
      }

      $("#updateStatusForm")[0].reset(); // Reset the form
    } else {
      console.error(`Row with ID ${rowId} not found.`);
    }
    // this javascript is for refresh the count after changes in any row
    getUserLeadsCount();
  }

  function populateFollowUpHistory(history) {
    const historyList = document.querySelector("#followUpHistory");
    historyList.innerHTML = ""; // Clear existing history once

    history.forEach((entry) => {
      const listItem = document.createElement("li");
      listItem.classList.add("list-group-item");
      listItem.textContent = `Status: ${entry.status}, Notes: ${entry.notes}, 
                                    Follow-Up Date: ${entry.followUpDate}, 
                                    Follow-Up Time: ${entry.followUpTime}, 
                                    Updated On: ${entry.timestamp}`;
      historyList.appendChild(listItem);
    });
  }



  // Close dropdown when clicking outside on mobile
  $(document).on("click", function (e) {
    // If clicking outside the status filter container
    if (!$(e.target).closest(".status-filter-container").length) {
      $(".status-filter-container").removeClass("active");
      $(".mobile-dropdown-backdrop").remove();
    }
  });

  // Prevent dropdown from closing when clicking inside it
  $(".status-filter-dropdown").on("click", function (e) {
    e.stopPropagation();
  });

  function closeSidebar1(sidebarId) {
    const sidebar = document.getElementById(sidebarId);
    if (sidebar) {
      sidebar.classList.remove("active");
      sidebar.classList.remove("opened-from-overdue"); // Remove overdue popup styling
      setTimeout(() => (sidebar.style.display = "none"), 300);
    }
  }
  closeLeadHistoryBtn?.addEventListener("click", () =>
    closeSidebar1("uniqueLeadHistorySidebar")
  );
  closeCallHistoryBtn?.addEventListener("click", () =>
    closeSidebar1("uniqueCallHistorySidebar")
  );



  // Add special handling for modal opening buttons
  $(document).on('click', '.salarybutton, .add-btn-mobile, .filter-btn-mobile, .remarks-btn-mobile, .columns-btn-mobile, .filter-btn, .dt-button.filter-btn', function (e) {
    e.stopPropagation();
    // Let the original click handlers do their work
  });

  // Fix for DataTables filter button
  $(document).on('click', '.dt-button.filter-btn', function (e) {
    e.stopPropagation();
    // The original handler will show the modal
  });


  // Initialize dropdown toggle logic
  function initializeLeadHistoryClickListeners() {
    document.querySelectorAll(".unique-lead-history li").forEach((item) => {
      item.addEventListener("click", () => {
        const dropdown = item.querySelector(".unique-dropdown");
        const uparrow = item.querySelector(".unique-uparrow");
        const downarrow = item.querySelector(".unique-downarrow");

        const isDropdownVisible = dropdown.classList.contains("show");

        // Reset all dropdowns and arrows
        document
          .querySelectorAll(".unique-dropdown")
          .forEach((dd) => dd.classList.remove("show"));
        document
          .querySelectorAll(".unique-uparrow")
          .forEach((ua) => (ua.style.display = "none"));
        document
          .querySelectorAll(".unique-downarrow")
          .forEach((da) => (da.style.display = "inline"));

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



  // Add New Lead Logic start here

  // Fetch IP address - LAZY LOAD: Only fetch when add lead modal is opened
  // This reduces unnecessary network calls on page load
  function fetchIPAddressLazy() {
    if (ipFetched) return; // Already fetched

    fetch("https://ipapi.co/json/")
      .then((response) => response.json())
      .then((data) => {
        ipAddress = data.ip;
        if (leadLocation) leadLocation.value = ipAddress;
        ipFetched = true;
      })
      .catch((error) => {
        console.error("Error fetching IP:", error);
        if (leadLocation) leadLocation.value = "unknown";
        ipFetched = true;
      });
  }

  // Add input event listeners for real-time validation
  const leadName = document.getElementById('leadName');

  const leadProject = document.getElementById('leadProject');
  const leadsource = document.getElementById('leadsource');

  if (leadName) {
    leadName.addEventListener('blur', function () {
      if (!this.value.trim()) {
        showFieldError(this, 'Name is required');
      } else {
        clearFieldError(this);
      }
    });
  }

  if (leadPhone) {
    leadPhone.addEventListener('blur', function () {
      const phone = this.value.trim();
      if (!phone) {
        showFieldError(this, 'Phone number is required');
      } else if (!/^[6-9]\d{9}$/.test(phone)) {
        showFieldError(this, 'Please enter a valid 10-digit Indian mobile number');
      } else {
        clearFieldError(this);
      }
    });
  }

  if (leadEmail) {
    leadEmail.addEventListener('blur', function () {
      const email = this.value.trim();
      if (email === "") {
        showFieldError(this, 'Email is required');
      } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        showFieldError(this, 'Please enter a valid email address');
      } else {
        clearFieldError(this);
      }
    });
  }


  if (leadProject) {
    leadProject.addEventListener('blur', function () {
      if (!this.value.trim()) {
        showFieldError(this, 'Project is required');
      } else {
        clearFieldError(this);
      }
    });
  }

  if (leadsource) {
    leadsource.addEventListener('change', function () {
      if (!this.value) {
        showFieldError(this, 'Lead source is required');
      } else {
        clearFieldError(this);
      }
    });
  }


  function clearFieldError(element) {
    const formGroup = element.closest('.form-group');
    if (formGroup) {
      formGroup.classList.remove('error');
      const errorElement = formGroup.querySelector('.error-message');
      if (errorElement) {
        errorElement.textContent = '';
      }
    }
  }

  // Update the form submission handler to use the enhanced validation
  addLeadForm.addEventListener("submit", function (e) {
    e.preventDefault();

    // Validate form first
    if (!validateAddLeadForm()) {
      // Show a general error message
      const generalError = document.getElementById('generalFormError');
      if (generalError) {
        generalError.textContent = 'Please fill all required fields correctly';
        generalError.style.display = 'block';

        setTimeout(() => {
          generalError.style.display = 'none';
        }, 5000);
      }

      // Scroll to first error
      const firstError = document.querySelector('.form-group.error');
      if (firstError) {
        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }

      return false;
    }

    // If form is valid, proceed with submission
    const generalError = document.getElementById('generalFormError');
    if (generalError) {
      generalError.style.display = 'none';
    }

    submitLeadForm();
  });

  // Add this to your existing JavaScript code
  $(document).ready(function () {
    // Make date inputs open calendar on click
    function enhanceDateInputs() {
      // Select all date inputs in your application
      const dateInputs = document.querySelectorAll('input[type="date"]');

      dateInputs.forEach(input => {
        // Ensure the input shows the calendar on click
        input.addEventListener('click', function () {
          this.showPicker();
        });

        // Also ensure it works on focus for keyboard users
        input.addEventListener('focus', function () {
          this.showPicker();
        });
      });
    }

    // Initialize the enhancement
    enhanceDateInputs();

    // Re-initialize when modals are opened (in case they contain date inputs)
    $(document).on('shown.bs.modal', function () {
      enhanceDateInputs();
    });
  });

  // Handle X button in header dropdown - just close the dropdown without clearing filters
  $(document).on('click', '.filter-close-btn', function (e) {
    e.stopPropagation(); // Prevent the click from bubbling up
    e.preventDefault(); // Prevent default button behavior

    const $header = $(this).closest('.filter-header');
    const $dropdown = $(this).closest('.filter-dropdown');

    // Hide the dropdown
    $dropdown.hide();

    // Remove active class from header
    $header.removeClass('active');

    // Clear active dropdown tracking
    if (window.activeDropdownHeader === $header[0]) {
      window.activeDropdownHeader = null;
    }
  });

  // Also add this to handle closing status filter dropdown
  $(document).on('click', '.status-filter-close-btn', function (e) {
    e.stopPropagation();
    $('.status-filter-dropdown').hide();
    isStatusDropdownOpen = false;
  });



  function validateLeadForm() {
    let isValid = true;
    let firstInvalid = null;

    // Clear previous errors
    document.querySelectorAll('.error-message').forEach(el => el.textContent = '');
    document.querySelectorAll('.form-group').forEach(el => el.classList.remove('error'));

    // Validate required fields - ADD EMAIL to required fields
    const requiredFields = [
      { id: 'leadName', name: 'Name' },
      { id: 'leadPhone', name: 'Phone Number' },
      { id: 'leadEmail', name: 'Email' }, // Email is now required
      { id: 'leadProject', name: 'Project' },
      { id: 'leadsource', name: 'Lead Source' }
    ];

    requiredFields.forEach(field => {
      const element = document.getElementById(field.id);
      if (!element.value.trim()) {
        showFieldError(element, `${field.name} is required`);
        if (!firstInvalid) firstInvalid = element;
        isValid = false;
      }
    });

    // Validate phone number format (only if it has value)
    const phone = leadPhone.value.trim();
    if (phone && !/^[6-9]\d{9}$/.test(phone)) {
      showFieldError(leadPhone, 'Please enter a valid 10-digit Indian mobile number');
      if (!firstInvalid) firstInvalid = leadPhone;
      isValid = false;
    } else if (!phone) {
      // Phone is empty (already handled above, but we'll make sure)
      isValid = false;
    }

    // Validate email format (email is now required)
    const email = leadEmail.value.trim();
    if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      showFieldError(leadEmail, 'Please enter a valid email address');
      if (!firstInvalid) firstInvalid = leadEmail;
      isValid = false;
    } else if (!email) {
      // Email is empty (already handled above)
      isValid = false;
    }

    if (firstInvalid) {
      firstInvalid.focus();

      // Add a shake animation to draw attention to the error
      firstInvalid.closest('.form-group').classList.add('shake');
      setTimeout(() => {
        firstInvalid.closest('.form-group').classList.remove('shake');
      }, 500);
    }

    return isValid;
  }

  function showFieldError(element, message) {
    const formGroup = element.closest('.form-group');
    if (formGroup) {
      formGroup.classList.add('error');
      const errorElement = formGroup.querySelector('.error-message');
      if (errorElement) {
        errorElement.textContent = message;
        errorElement.style.display = 'block';
      }
    }
  }

  function clearFieldError(element) {
    const formGroup = element.closest('.form-group');
    if (formGroup) {
      formGroup.classList.remove('error');
      const errorElement = formGroup.querySelector('.error-message');
      if (errorElement) {
        errorElement.textContent = '';
        errorElement.style.display = 'none';
      }
    }
  }


  // Submit lead add handler
  // addLeadForm.addEventListener("submit", function (e) {
  //   e.preventDefault();

  //   // Validate form first
  //   if (!validateLeadForm()) {
  //     return;
  //   }

  //   // Check if IP is fetched (wait max 2 seconds if not)
  //   const startTime = Date.now();
  //   const checkIP = () => {
  //     if (ipFetched || Date.now() - startTime > 2000) {
  //       submitLeadForm();
  //     } else {
  //       setTimeout(checkIP, 100);
  //     }
  //   };

  //   checkIP();
  // });


  // Add this code to handle closing dropdowns when clicking outside
  $(document).on('click', function (e) {
    // Close all filter dropdowns if click is outside
    $('.filter-dropdown').each(function () {
      const $dropdown = $(this);
      // Check if click is outside this dropdown and its header button
      if (!$dropdown.is(e.target) &&
        $dropdown.has(e.target).length === 0 &&
        !$(e.target).closest('.filter-header-btn').length) {
        $dropdown.hide();
      }
    });

    // Close status filter dropdown if click is outside
    const $statusDropdown = $('.status-filter-dropdown');
    if ($statusDropdown.is(':visible') &&
      !$statusDropdown.is(e.target) &&
      $statusDropdown.has(e.target).length === 0 &&
      !$(e.target).closest('.status-filter-btn').length) {
      $statusDropdown.hide();
      isStatusDropdownOpen = false;
    }
  });

  // Prevent dropdown from closing when clicking inside it (except close button)
  $(document).on('click', '.filter-dropdown, .status-filter-dropdown', function (e) {
    // Allow close button clicks to propagate
    if ($(e.target).hasClass('filter-close-btn') || $(e.target).hasClass('status-filter-close-btn') ||
      $(e.target).closest('.filter-close-btn').length || $(e.target).closest('.status-filter-close-btn').length) {
      return;
    }
    e.stopPropagation();
  });


  function submitLeadForm() {
    const formData = new FormData(addLeadForm);

    // Show loading state
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';

    fetch("insert_lead.php", {
      method: "POST",
      body: formData,
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error("Network response was not ok");
        }
        return response.json();
      })
      .then((res) => {
        if (res.status === "success") {
          if (typeof window.closeAddLeadModal === "function") {
            window.closeAddLeadModal();
          }

          // Refresh data table
          if (typeof table !== "undefined") {
            table.ajax.reload(null, false);
          }

          // Reset form
          addLeadForm.reset();
        } else {
          throw new Error(res.message || "Unknown error occurred");
        }
      })
      .catch((error) => {
        console.error("Error:", error);
        showNotification(
          error.message || "An error occurred. Please try again later.",
          "error"
        );
      })
      .finally(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
        // Show notification after closing
        // showNotification("Lead Added Successfully");
        fetchHistory(rowId, userUniqueId);
      });
  }


  const style = document.createElement('style');
  style.textContent = `
    @media (min-width: 901px) {
        .call-button {
            pointer-events: none !important;
            opacity: 0.5 !important;
            cursor: not-allowed !important;
        }
        
        .call-button:hover {
            background-color: transparent !important;
        }
    }
    
    @media (max-width: 900px) {
        .call-button {
            pointer-events: auto !important;
            opacity: 1 !important;
            cursor: pointer !important;
        }
    }
`;
  document.head.appendChild(style);

  // Modify the call button click handler to prevent default behavior on desktop
  $(document).on("click", ".call-button", async function (e) {
    // Prevent call on desktop
    if (window.innerWidth > 900) {
      e.preventDefault();
      e.stopPropagation();

      // Show message that calls are only available on mobile
      Swal.fire({
        title: "Mobile Only Feature",
        text: "Call functionality is only available on mobile devices. Please use your mobile device to make calls.",
        icon: "info",
        timer: 3000,
        showConfirmButton: false
      });
      return;
    }

    // Original mobile functionality
    e.stopPropagation();
    e.preventDefault();

    const $button = $(this);
    const $row = $button.closest("tr");
    const $counter = $button
      .closest(".phone-btn-wrapper")
      .find(".call-btn-counter");

    // Get lead info
    const leadName = $row.find(".lead-info h4").text().trim();
    const phoneNumber = $row.find(".phone-info").data("real-phone");
    const leadId = $row.find(".lead-id-hidden").text().trim();

    if (!phoneNumber) {
      Swal.fire({
        title: "Error",
        text: "Phone number not available for this lead",
        icon: "error",
      });
      return;
    }

    // Show SweetAlert confirmation
    const { isConfirmed } = await Swal.fire({
      title: "Confirm Call",
      html: `Call <strong>${leadName}</strong> at <strong>${phoneNumber}</strong>?`,
      icon: "question",
      showCancelButton: true,
      confirmButtonText: "Call Now",
      cancelButtonText: "Cancel",
    });

    if (isConfirmed) {
      // Only proceed with call if confirmed
      initiateCall(phoneNumber, leadId, $counter, $row);
    }
  });
  const additionalStyle = document.createElement('style');
  additionalStyle.textContent = `
    @media (min-width: 901px) {
        .mobile-call-only {
            opacity: 0.7;
        }
        
        .mobile-call-only .call-button:after {
            content: " (Mobile only)";
            font-size: 0.7em;
            opacity: 0.7;
        }
    }
    
    @keyframes blink {
        0%, 50% { opacity: 1; }
        51%, 100% { opacity: 0.3; }
    }
    
    .overdue-status {
        animation: blink 1.5s infinite;
    }
`;
  document.head.appendChild(additionalStyle);



  // Also prevent the anchor tag from working on desktop
  $(document).on("click", ".phone-btn-wrapper a", function (e) {
    if (window.innerWidth > 900) {
      e.preventDefault();
      e.stopPropagation();
      return false;
    }
  });


  // Add New Lead Logic end here

  // Fetch users for dropdown

  // Load users when modal opens
  async function loadUsers() {
    try {
      const res = await fetch("update_status.php?get_users=1");
      if (!res.ok) throw new Error("Failed to fetch users");

      const html = await res.text();
      assignTo.innerHTML = `
                <option value="" disabled selected>Select User</option>
                ${html}
            `;
    } catch (error) {
      console.error("User load error:", error);
      showNotification("Unable to load users", "error");
    }
  }

  // Use event delegation for assign buttons to handle dynamic elements
  document.addEventListener("click", async function (e) {
    // Check if the clicked element is an assign button
    if (e.target.closest(".assign-btn-mobile") || e.target.closest(".assign-users-btn")) {
      e.preventDefault();
      e.stopPropagation();

      const checkboxes = document.querySelectorAll(".row-checkbox:checked");
      const selectedIds = Array.from(checkboxes).map((cb) => {
        const row = cb.closest("tr");
        return row.querySelector(".lead-id-hidden").textContent.trim();
      });

      if (selectedIds.length === 0) {
        showNotification("Please select at least one lead", "error");
        return;
      }

      selectedLeadsCount.textContent = selectedIds.length;
      selectedLeadsList.innerHTML = selectedIds
        .map((id) => `<span class="lead-id-tag">#${id}</span>`)
        .join("");
      assignForm.setAttribute("data-selected-ids", selectedIds.join(","));

      await loadUsers();

      assignModal.style.display = "flex";
      document.body.classList.add("modal-open");
    }
  });

  // Close modal - removed premature call

  cancelAssignBtn.addEventListener("click", closeModal1);
  closeAssignModalBtn.addEventListener("click", closeModal1);
  assignModal.addEventListener("click", (e) => {
    if (e.target === assignModal) closeModal1();
  });

  // #assignTo is a Select2 dropdown — must use jQuery .on() for change events
  // (Select2 dispatches through jQuery, not the native DOM)
  $(assignTo).on("change", async function () {
    if (!document.getElementById("assignDupSection")) return; // section not visible, nothing to update

    const newUser = $(assignTo).val();
    if (!newUser) { removeDupSection(); return; }

    const rowIds = (assignForm.getAttribute("data-selected-ids") || "").split(",").filter(Boolean);
    const projectName = (document.getElementById("assignProjectName") || {}).value?.trim() || '';
    const includeHistory = document.getElementById('bulkIncludeHistoryToggle').checked;

    if (rowIds.length === 0) { removeDupSection(); return; }

    try {
      const checkResp = await fetch("update_status.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ checkBulkDuplicates: true, rowIds, assignUser: newUser })
      });
      const checkResult = await checkResp.json();

      if (checkResult.commonCount > 0) {
        injectDupSection(checkResult, newUser, projectName, includeHistory, rowIds);
      } else {
        removeDupSection();
      }
    } catch (err) {
      removeDupSection();
    }
  });

  // ── helper: perform the actual bulk-assign POST ─────────────────────────
  async function performBulkAssign(idsArray, targetUser, projectName, includeHistory, selectedIdsForLog) {
    const responseMsg = document.getElementById("assignResponseMessage");
    if (responseMsg) responseMsg.innerHTML = "";

    const submitBtn = document.querySelector("#assignForm .submit-btn");
    const originalText = submitBtn ? submitBtn.innerHTML : 'Assign Leads';
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Assigning...';
    }
    document.querySelectorAll('.assign-dup-btn').forEach(b => b.disabled = true);

    const data = {
      bulkAssign: true,
      rowIds: idsArray,
      assignUser: targetUser,
      projectName: projectName,
      includeHistory: includeHistory ? 1 : 0
    };

    try {
      const response = await fetch("update_status.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data),
      });

      if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
      const responseText = await response.text();

      let result;
      try {
        result = JSON.parse(responseText);
      } catch (parseError) {
        throw new Error("Invalid response from server");
      }

      if (result.status === "success") {
        const historyMessage = includeHistory ? ' with complete history.' : ' as fresh leads.';
        showNotification(`Leads assigned successfully${historyMessage}`, 'success');

        closeModal1();

        $(".row-checkbox").prop("checked", false);
        $("#selectAll").prop("checked", false);
        $(".selection-actions").hide();
        $(".mobile-bottom-nav").removeClass("has-selection");
        $(".assign-btn-mobile .selection-count, .delete-btn-mobile .selection-count").text(0);

        setTimeout(async () => {
          try {
            if ($.fn.DataTable.isDataTable("#leadsTable")) {
              const currentTable = $("#leadsTable").DataTable();
              currentTable.ajax.reload(function (json) {
                if (json && json.data) {
                  const stillPresent = json.data.filter(row => (selectedIdsForLog || []).includes(row.id.toString()));
                  if (stillPresent.length > 0) {
                    console.warn("Assigned leads still visible:", stillPresent.map(r => r.id));
                  }
                }
              }, false);
            }
            setTimeout(() => {
              if (table && $.fn.DataTable.isDataTable("#leadsTable")) {
                clearFilterStateCache();
                updateAllTagCounts();
              }
            }, 600);
          } catch (updateError) {
            try {
              if ($.fn.DataTable.isDataTable("#leadsTable")) {
                $("#leadsTable").DataTable().ajax.reload(null, false);
              }
            } catch (reloadError) {
              console.error("Fallback reload failed:", reloadError);
            }
          }
        }, 800);
      } else {
        const errorMsg = result.message || result.error || "Failed to assign leads. Please try again.";
        showNotification(errorMsg, 'error');
        if (responseMsg) responseMsg.innerHTML = `<div class="error-message">${errorMsg}</div>`;
      }
    } catch (err) {
      console.error("âŒ Error during assignment:", err);
      showNotification("An error occurred while assigning leads. Please try again.", 'error');
      if (responseMsg) responseMsg.innerHTML = `<div class="error-message">Network error. Please check your connection and try again.</div>`;
    } finally {
      if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = originalText; }
      document.querySelectorAll('.assign-dup-btn').forEach(b => b.disabled = false);
    }
  }

  // ── helper: inject duplicate stats inside the existing assign modal ───────
  function injectDupSection(checkResult, targetUser, projectName, includeHistory, allIds) {
    removeDupSection();
    const { commonCount, uniqueCount, total, uniqueIds } = checkResult;

    const section = document.createElement('div');
    section.id = 'assignDupSection';
    section.className = 'assign-dup-section';
    section.innerHTML = `
      <div class="assign-dup-label">⚠️ Duplicate Leads Detected</div>
      <div class="assign-dup-stats">
        <div class="bulk-dup-stat bulk-dup-stat--warn">
          <span class="bulk-dup-stat-num">${commonCount}</span>
          <span class="bulk-dup-stat-label">Already assigned<br>to <strong>${targetUser}</strong></span>
        </div>
        <div class="bulk-dup-stat bulk-dup-stat--ok">
          <span class="bulk-dup-stat-num">${uniqueCount}</span>
          <span class="bulk-dup-stat-label">New leads<br>(not yet assigned)</span>
        </div>
        <div class="bulk-dup-stat bulk-dup-stat--total">
          <span class="bulk-dup-stat-num">${total}</span>
          <span class="bulk-dup-stat-label">Total<br>selected</span>
        </div>
      </div>
      <p class="bulk-dup-hint">
        <strong>${commonCount}</strong> of your <strong>${total}</strong> selected lead${total !== 1 ? 's' : ''}
        already exist in <strong>${targetUser}</strong>'s account. Choose how to proceed:
      </p>`;

    const formActions = assignForm.querySelector('.form-actions');
    if (formActions) {
      assignForm.insertBefore(section, formActions);
    } else {
      assignForm.appendChild(section);
    }

    const originalSubmitBtn = formActions ? formActions.querySelector('.submit-btn') : null;
    if (originalSubmitBtn) originalSubmitBtn.style.display = 'none';

    const dupActions = document.createElement('div');
    dupActions.id = 'assignDupActions';
    dupActions.className = 'assign-dup-actions';
    dupActions.innerHTML = `
      ${uniqueCount > 0
        ? `<button type="button" class="assign-dup-btn bulk-dup-btn--unique" id="assignDupUniqueBtn">
             Assign Unique <span class="bulk-dup-badge">${uniqueCount}</span>
           </button>`
        : ''}
      <button type="button" class="assign-dup-btn bulk-dup-btn--all" id="assignDupAllBtn">
        Assign All <span class="bulk-dup-badge bulk-dup-badge--all">${total}</span>
      </button>`;

    if (formActions) formActions.appendChild(dupActions);

    if (uniqueCount > 0) {
      document.getElementById('assignDupUniqueBtn').onclick = async () => {
        await performBulkAssign(uniqueIds.map(String), targetUser, projectName, includeHistory, allIds);
      };
    }
    document.getElementById('assignDupAllBtn').onclick = async () => {
      await performBulkAssign(allIds, targetUser, projectName, includeHistory, allIds);
    };
  }

  // ── helper: restore modal to original single-button state ─────────────────
  function removeDupSection() {
    document.getElementById('assignDupSection')?.remove();
    document.getElementById('assignDupActions')?.remove();
    const sub = assignForm ? assignForm.querySelector('.submit-btn') : null;
    if (sub) sub.style.display = '';
  }

  // Update the assign form submission handler
  assignForm.addEventListener("submit", async function (e) {
    e.preventDefault();

    if (!validateAssignForm()) return false;

    const rowIds = assignForm.getAttribute("data-selected-ids");
    const selectedIds = rowIds ? rowIds.split(",") : [];
    const targetUser = assignTo.value;
    const projectName = (document.getElementById("assignProjectName") || {}).value?.trim() || '';
    const includeHistory = document.getElementById('bulkIncludeHistoryToggle').checked;
    const allIds = selectedIds.filter(Boolean);

    const responseMsg = document.getElementById("assignResponseMessage");
    if (responseMsg) responseMsg.innerHTML = "";

    const submitBtn = document.querySelector("#assignForm .submit-btn");
    const originalText = submitBtn ? submitBtn.innerHTML : 'Assign Leads';
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking...';
    }

    try {
      const checkResp = await fetch("update_status.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ checkBulkDuplicates: true, rowIds: allIds, assignUser: targetUser })
      });
      const checkResult = await checkResp.json();

      if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = originalText; }

      if (checkResult.commonCount > 0) {
        // Duplicates found — transform modal in place, no extra popup
        injectDupSection(checkResult, targetUser, projectName, includeHistory, allIds);
      } else {
        // No duplicates — assign all directly
        await performBulkAssign(allIds, targetUser, projectName, includeHistory, selectedIds);
      }
    } catch (err) {
      console.error("Duplicate check failed, proceeding with all:", err);
      if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = originalText; }
      await performBulkAssign(allIds, targetUser, projectName, includeHistory, selectedIds);
    }
  });
  const historyToggle2 = document.getElementById('bulkIncludeHistoryToggle');
  const historyDescription2 = document.getElementById('historyDescription2');

  function updateBulkHistoryDescription() {
    if (historyToggle2.checked) {
      historyDescription2.textContent = 'All previous interactions, notes, and status updates will be transferred to the new assignee.';
    } else {
      historyDescription2.textContent = 'Leads will be assigned as fresh leads without any previous history.';
    }
  }

  // Initialize description
  updateBulkHistoryDescription();

  // Handle toggle change
  historyToggle2.addEventListener('change', function () {
    updateBulkHistoryDescription();

    // Add visual feedback
    const section = document.querySelector('.history-section');
    section.style.transform = 'scale(0.99)';
    setTimeout(() => {
      section.style.transform = 'scale(1)';
    }, 150);
  });



  function closeModal1() {
    removeDupSection(); // clean up any duplicate section before closing
    assignModal.style.display = "none";
    document.body.classList.remove("modal-open");
    assignForm.reset();
    selectedLeadsCount.textContent = "0";
    selectedLeadsList.innerHTML = "";
    responseMessage.innerHTML = "";
  }

  // Create global closeModal alias to prevent undefined errors
  window.closeModal = closeModal1;

  // Also define closeModal at module level to prevent ReferenceError
  // Override the early definition with the actual function
  window.closeModal = closeModal1;

  // Notification helper
  function showNotification(message, type = "info", legacyType = null) {
    // Support both signatures:
    // 1) showNotification(message, type)
    // 2) showNotification(title, message, type)
    if (legacyType !== null) {
      message = [message, type].filter(Boolean).join("\n\n");
      type = legacyType;
    }

    const palette = {
      success: "rgba(16, 185, 129, 0.96)",
      error: "rgba(239, 68, 68, 0.96)",
      warning: "rgba(245, 158, 11, 0.96)",
      info: "rgba(59, 130, 246, 0.96)",
    };

    if (!palette[type]) {
      type = "info";
    }

    const hiddenTransform = "translate(-50%, 16px)";
    const visibleTransform = "translate(-50%, 0)";

    document
      .querySelectorAll(".notification, .lead-toast")
      .forEach((node) => node.remove());

    const notification = document.createElement("div");
    notification.className = `lead-toast ${type}`;
    notification.textContent = message;
    notification.setAttribute("role", "status");
    notification.setAttribute("aria-live", "polite");

    Object.assign(notification.style, {
      position: "fixed",
      top: "auto",
      bottom: "24px",
      left: "50%",
      transform: hiddenTransform,
      opacity: 0,
      transition: "transform 0.28s ease, opacity 0.28s ease",
      zIndex: "100000",
      maxWidth: "min(90vw, 540px)",
      width: "auto",
      padding: "14px 24px",
      borderRadius: "14px",
      background: palette[type] || palette.info,
      color: "#ffffff",
      fontWeight: "600",
      fontSize: message.length > 120 ? "14px" : "15px",
      letterSpacing: "0.2px",
      boxShadow: "0 10px 25px rgba(0, 0, 0, 0.2)",
      textAlign: "center",
      display: "inline-flex",
      alignItems: "center",
      justifyContent: "center",
      lineHeight: message.length > 120 ? "1.4" : "1.2",
      whiteSpace: "pre-line",
      height: "auto",
      minHeight: "auto",
    });

    notification.style.setProperty("background-color", palette[type] || palette.info, "important");
    notification.style.setProperty("color", "#ffffff", "important");
    notification.style.setProperty("background", palette[type] || palette.info, "important");

    document.body.appendChild(notification);

    requestAnimationFrame(() => {
      notification.style.transform = visibleTransform;
      notification.style.opacity = "1";
    });

    setTimeout(() => {
      notification.style.transform = hiddenTransform;
      notification.style.opacity = "0";
      setTimeout(() => notification.remove(), 280);
    }, 3200);
  }

  // Make fetchHistory globally accessible
  window.fetchHistory = function fetchHistory(rowId, userUniqueId, options = {}) {
    console.log('🔍 fetchHistory called with:', { rowId, userUniqueId });

    const cacheKey = buildLeadCacheKey(rowId, userUniqueId);
    const cached = !options.forceRefresh ? getCachedValue(historyCache, cacheKey) : null;

    // Reset sidebar metadata immediately to avoid showing stale values from previous lead.
    if (assignedDateLeads) assignedDateLeads.innerText = "N/A";
    if (assignedByUser) assignedByUser.innerText = "N/A";
    if (leadUserName) leadUserName.innerText = "N/A";
    if (leadUserNumber) leadUserNumber.innerText = "N/A";

    if (followUpHistory) {
      followUpHistory.innerHTML = "";
    }

    const historyPromise = cached
      ? Promise.resolve(cached)
      : fetch("update_status.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          fetchHistory: true,
          rowId,
          user_unique_id: userUniqueId,
        }),
      })
        .then((response) => {
          console.log('📡 Fetch response received:', response.status);
          return response.json();
        });

    historyPromise
      .then((data) => {
        console.log('📡 History data received:', data);
        if (data && data.status === "success") {
          setCachedValue(historyCache, cacheKey, data);
        }

        if (data.status === "success") {
          const historyEntries = Array.isArray(data.history) ? data.history : [];
          console.log('✅ History fetch successful, history entries:', historyEntries.length);

          if (!followUpHistory) {
            console.error('❌ followUpHistory element not found!');
            return;
          }

          followUpHistory.innerHTML = ""; // Clear previous history

          // Always apply current lead metadata, even if there is no follow-up history.
          if (assignedDateLeads) assignedDateLeads.innerText = data.assignedDate || "N/A";
          if (assignedByUser) assignedByUser.innerText = data.assignedBy || "N/A";
          if (leadUserName) leadUserName.innerText = data.lead_user || "N/A";
          if (leadUserNumber) leadUserNumber.innerText = data.lead_number || "N/A";

          if (historyEntries.length === 0) {
            followUpHistory.innerHTML =
              '<li class="list-group-item">No follow-up history found.</li>';
          } else {
          [...historyEntries].reverse().forEach((entry) => {
              // Determine the appropriate status class
              let statusClass = "";
              switch (entry.status) {
                case "Pending":
                  statusClass = "history-pending";
                  break;
                case "Fake":
                  statusClass = "history-fake";
                  break;
                case "RNR":
                  statusClass = "history-rnr";
                  break;
                case "Call Back":
                  statusClass = "history-call-back";
                  break;
                case "Already Booked":
                  statusClass = "history-booked";
                  break;
                case "Not Interested":
                  statusClass = "history-not-interested";
                  break;
                case "Interested":
                  statusClass = "history-interested";
                  break;
                case "Follow Up":
                  statusClass = "history-follow-up";
                  break;
                case "Fix Site Visit":
                  statusClass = "history-visit";
                  break;
                case "Site Visit Done":
                  statusClass = "history-visit-done";
                  break;
                case "VC Done":
                  statusClass = "history-vc-done";
                  break;
                case "Converted":
                  statusClass = "history-eoi-collected";
                  break;
                case "EOI":
                  statusClass = "history-eoi";
                  break;
                case "Re site visit":
                  statusClass = "history-re-site-visit";
                  break;
                case "NQFTP":
                  statusClass = "history-NQFTP";
                  break;
                case "Not Connected":
                  statusClass = "history-not-connected";
                  break;
                default:
                  statusClass = "";
                  break;
              }

              const li = document.createElement("li");
              li.classList.add("unique-step", "unique-active-timeline");
              li.innerHTML = `
                        <div class="unique-dot"></div>
                        <div class="unique-content">
                            <div>
                                <span class="unique-status-info ${statusClass}">${entry.status
                }</span>
                                <span class="unique-date-time">${entry.timestamp
                }</span>
                            </div>
                            <span class="unique-arrow unique-downarrow">▼</span>
                            <span class="unique-arrow unique-uparrow">▲</span>
                        </div>
                        <div class="unique-dropdown">
                            <div class="unique-dropdown-insides">
                                <div class="note-containers">
                                  <span><b>Updated By:</b> ${entry.update_by || "No User Available"
                }</span>
                                  <span><b>Date & Time:</b> ${entry.followUpDate || "N/A"
                } ${entry.followUpTime || "N/A"}</span>
                                  <span><b>Notes:</b> ${entry.notes || "No notes available"
                }</span>
                                </div>
                            </div>
                        </div>
                    `;
              followUpHistory.appendChild(li);
            });

            // Attach toggle logic to new history items
            initializeLeadHistoryClickListeners();

            // Auto-apply the global expand/collapse state
            const isHidden = localStorage.getItem("leadHistoryDetailsHidden") !== "false";
            document.querySelectorAll(".unique-lead-history li.unique-active-timeline").forEach((item) => {
              const dropdown = item.querySelector(".unique-dropdown");
              const uparrow = item.querySelector(".unique-uparrow");
              const downarrow = item.querySelector(".unique-downarrow");
              if (!isHidden) {
                dropdown.classList.add("show");
                uparrow.style.display = "inline";
                downarrow.style.display = "none";
              }
            });

            console.log('✅ History sidebar content populated successfully');
          }
        } else {
          console.error("Failed to fetch history:", data.message);
          if (followUpHistory) {
            followUpHistory.innerHTML =
              '<li class="list-group-item">Unable to load follow-up history.</li>';
          }
        }
      })
      .catch((error) => {
        console.error("Error fetching history:", error);
        if (followUpHistory) {
          followUpHistory.innerHTML =
            '<li class="list-group-item">Error fetching follow-up history.</li>';
        }
      });
  }

  //document.addEventListener('click', function (e) {
  //if (e.target.closest('.unique-toggle-btn')) {
  //const button = e.target.closest('.unique-toggle-btn');
  //const rowId = button.getAttribute('data-id');
  //const userUniqueId = button.getAttribute('data-userid');

  //console.log('Clicked History for:', rowId, userUniqueId);
  //fetchHistory(rowId, userUniqueId);
  // }
  //});

  // Safely handle $header if it exists (may not be defined in all contexts)
  try {
    if (typeof $header !== 'undefined' && $header && $header.length) {
      $header.find(".filter-toggle").on("click", function () {
        const $dropdown = $header.find('.filter-dropdown');
        if ($dropdown.length) {
          $dropdown.empty(); // Clear old items
          populateFilterDropdown($header).then(() => {
            $dropdown.toggle(); // Show after population
          });
        }
      });
    }
  } catch (e) {
    // Silently ignore if $header is not defined
  }

  // Helper function to escape HTML
  function escapeHtml(unsafe) {
    return unsafe
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function escapeRegex(string) {
    return string.replace(/[-\/\\^$*+?.()|[\]{}]/g, "\\$&");
  }
});



async function fetchMultiFilteredLeads() {
  multiFilters = {
    name: document.getElementById("isolatedFilterCustumername").value.trim(),
    email: document.getElementById("isolatedFilterEmail").value.trim(),
    number: document.getElementById("isolatedFilterContactnumber").value.trim(),
    location: document.getElementById("isolatedFilterLocation").value.trim(),
    source_of_lead: document
      .getElementById("isolatedFilterSourceOfLead")
      .value.trim(),
    status: document.getElementById("isolatedFilterStatus").value.trim(),
    assign_project_name: (() => {
      const input = document.getElementById("isolatedFilterAssignedProjectName");
      const tagValues = window.multiSelectValues && window.multiSelectValues["isolatedFilterAssignedProjectName"];
      if (Array.isArray(tagValues) && tagValues.length > 0) {
        return [...tagValues];
      }
      return input ? input.value.trim() : "";
    })(),
    user: document
      .getElementById("isolatedFilterAssigneduserName")
      .value.trim(),
    lead_identity: document
      .getElementById("isolatedFilterAssignedIdentity")
      .value.trim(),
    budget: document.getElementById("isolatedFilterBudget").value.trim(),
    start_date: document.getElementById("isolatedFilterStartDate").value.trim(),
    end_date: document.getElementById("isolatedFilterEndDate").value.trim(),

  };

  try {
    const encodedFilters = multiFilters
      ? encodeURIComponent(JSON.stringify(multiFilters))
      : "";
    const response = await fetch(
      `update_status.php?page=1&multiFilters=${encodedFilters}`,
      {
        method: "GET",
        headers: { Accept: "application/json" },
      }
    );
    const data = await response.json();
    console.log("Filtered Data from backend:", data);
    return data;
  } catch (error) {
    console.error("Error fetching multi-filtered leads:", error);
    return { data: [], totalRows: 0 };
  }
}

// Function to update hierarchy information in UI
function updateHierarchyUI(hierarchyInfo) {
  // Handle manager toggle visibility and functionality
  const managerToggle = document.getElementById('managerToggle');
  const managerToggleContainer = document.querySelector('.manager-toggle-container');

  if (hierarchyInfo.can_use_manager_toggle === false) {
    // Hide manager toggle for regular users
    if (managerToggleContainer) {
      managerToggleContainer.style.display = 'none';
    }
    return; // Don't proceed with other UI updates for regular users
  }

  // Show manager toggle for management roles
  if (managerToggleContainer) {
    managerToggleContainer.style.display = 'flex';
  }

  // Update manager toggle description - removed team member count display
  const toggleLabel = managerToggleContainer ? managerToggleContainer.querySelector('.toggle-label') : null;
  const toggleHelper = toggleLabel ? toggleLabel.nextElementSibling : null;
  if (toggleHelper && hierarchyInfo.accessible_user_count) {
    const count = hierarchyInfo.accessible_user_count;
    if (count > 1) {
      // Hide or clear the helper text instead of showing team count
      if (toggleHelper) toggleHelper.textContent = '';
      if (toggleHelper) toggleHelper.style.display = 'none';
    } else {
      if (toggleHelper) toggleHelper.textContent = 'No team members assigned';
      if (managerToggle) {
        managerToggle.disabled = true;
        managerToggle.checked = false;
      }
    }
  }

  // Update role indicator
  const userLevelIndicator = document.getElementById('userLevelIndicator');
  if (userLevelIndicator && hierarchyInfo.current_user_level) {
    const levelNames = {
      1: 'CEO/Promoter',
      2: 'Business Head',
      3: 'Manager',
      4: 'Team Lead',
      5: 'Sales Executive'
    };
    const levelName = levelNames[hierarchyInfo.current_user_level] || 'User';
    userLevelIndicator.innerHTML = `<i class="fas fa-id-badge"></i> Role: ${levelName}`;
    userLevelIndicator.className = `hierarchy-level level-${hierarchyInfo.current_user_level}`;
  }

  // Remove team count badge - not displaying team member count
  const existingCountBadge = managerToggleContainer ? managerToggleContainer.querySelector('.team-count-badge') : null;
  if (existingCountBadge) {
    existingCountBadge.remove();
  }
}

// ============================================================================
// OVERDUE LEADS POPUP SYSTEM - SIMPLE IN-MEMORY QUEUE
// ============================================================================

(function () {
  'use strict';

  console.log('🚀 Overdue Lead System IIFE starting (Simple Queue Mode)...');

  // Simple in-memory queue
  let overdueLeadsQueue = [];
  let currentIndex = 0;
  let isPopupActive = false;
  let totalOverdueCount = 0; // Store total count from database
  let isStatusModalOpenFromOverdue = false; // Track if status modal was opened from overdue popup

  // Local helper for this IIFE scope (main table formatter is in a different scope).
  function formatPhoneForPopupCall(num) {
    const digits = String(num || '').replace(/\D/g, '');
    if (!digits) return '';
    if (digits.length === 12 && digits.startsWith('91')) return '+' + digits;
    if (digits.length === 10) return '+91' + digits;
    return '+' + digits;
  }

  function openPopupDialpad(phoneNumber) {
    const dialTarget = String(phoneNumber || '').trim();
    if (!dialTarget) return;

    const telHref = dialTarget.startsWith('tel:') ? dialTarget : `tel:${dialTarget}`;

    const a = document.createElement('a');
    a.href = telHref;
    a.style.display = 'none';
    document.body.appendChild(a);
    a.click();
    setTimeout(() => a.remove(), 150);

    setTimeout(() => {
      if (!document.hidden && document.visibilityState === 'visible') {
        window.location.assign(telHref);
      }
    }, 250);
  }

  // Note: We no longer use localStorage for tracking dismissed leads.
  // All tracking is done in the database (overdue_leads_tracking table)
  // so it syncs across all devices for the same user.

  // Clear overdue leads queue (called when toggle is disabled)
  window.clearOverdueLeadsQueue = function () {
    console.log('🗑️ Clearing overdue leads queue...');
    overdueLeadsQueue = [];
    currentIndex = 0;
    totalOverdueCount = 0;

    // Close any open popup
    const modal = document.getElementById('overdueNotificationModal');
    if (modal && modal.style.display !== 'none') {
      closeOverduePopup();
      console.log('✅ Closed open overdue popup');
    }

    console.log('✅ Overdue leads queue cleared');
  };

  // Clean up old localStorage data (from previous version)
  function cleanupOldLocalStorage() {
    try {
      const oldKey = 'dismissedOverdueLeads';
      if (localStorage.getItem(oldKey)) {
        localStorage.removeItem(oldKey);
        console.log('🧹 Cleaned up old localStorage data - now using database tracking');
      }
    } catch (e) {
      // Ignore errors
    }
  }

  // Initialize: Fetch all overdue leads ONCE
  function initOverdueLeadSystem() {
    console.log('🔔 Initializing Overdue Lead System...');

    // Clean up old localStorage
    cleanupOldLocalStorage();

    // Wait for page load, then check toggle state before fetching leads
    setTimeout(() => {
      console.log('⏰ Checking toggle state before fetching leads...');

      // CRITICAL: Check if toggle is disabled in the UI
      const toggle = document.getElementById('overdue-popup-toggle');
      if (!toggle) {
        console.warn('⚠️ Toggle element not found. Waiting for DOM...');
        // Try again after a short delay
        setTimeout(initOverdueLeadSystem, 1000);
        return;
      }

      if (!toggle.checked) {
        console.log('🚫 ⛔ Toggle is DISABLED on page load. NOT fetching overdue leads.');
        console.log('🚫 Toggle checked state:', toggle.checked);
        return;
      }

      console.log('✅ Toggle is ENABLED on page load. Fetching overdue leads...');
      console.log('✅ Toggle checked state:', toggle.checked);
      fetchAllOverdueLeads();
    }, 4000); // 4 second delay to ensure page is fully loaded
  }

  // Fetch ALL overdue leads at once
  async function fetchAllOverdueLeads() {
    try {
      console.log('📡 Fetching all overdue leads...');
      const response = await fetch('update_status.php?get_all_overdue_leads=1', {
        method: 'GET',
        headers: { 'Accept': 'application/json' }
      });

      if (!response.ok) {
        console.error('❌ HTTP error! status:', response.status);
        return;
      }

      const data = await response.json();
      console.log('📡 API Response:', data);
      console.log('📡 popup_disabled value:', data.popup_disabled);
      console.log('📡 Number of leads:', data.leads?.length || 0);

      // CRITICAL CHECK: If popup is disabled, stop immediately
      if (data.popup_disabled === true || data.popup_disabled === 'true' || data.popup_disabled === 1) {
        console.log('🚫 ⛔ OVERDUE POPUPS ARE DISABLED. API returned popup_disabled = true. Not showing any popups.');
        overdueLeadsQueue = [];
        return;
      }

      if (data.status === 'success' && data.leads && data.leads.length > 0) {
        // Backend already filters out processed leads for today via overdue_leads_tracking table
        overdueLeadsQueue = data.leads;
        totalOverdueCount = data.total_count || data.leads.length;
        currentIndex = 0;
        console.log(`✅ Loaded ${overdueLeadsQueue.length} overdue leads into queue (Total in DB: ${totalOverdueCount})`);
        showNextLead();
      } else {
        console.log('✅ No overdue leads to show (all processed today or none exist)');
      }
    } catch (error) {
      console.error('❌ Error fetching overdue leads:', error);
    }
  }

  // Show next lead from queue
  function showNextLead() {
    if (currentIndex >= overdueLeadsQueue.length) {
      console.log('✅ All overdue leads processed');
      return;
    }

    const lead = overdueLeadsQueue[currentIndex];
    if (lead) {
      console.log(`🔔 Showing overdue lead ${currentIndex + 1}/${overdueLeadsQueue.length}:`, lead.name);
      showOverdueLeadPopup(lead);
    }
  }

  // Show the overdue lead popup with lead data
  function showOverdueLeadPopup(lead) {
    // CRITICAL: Double-check toggle state before showing ANY popup
    const toggle = document.getElementById('overdue-popup-toggle');
    if (toggle && !toggle.checked) {
      console.log('🚫 Toggle was disabled. Skipping popup display.');
      closeOverduePopup();
      return;
    }

    const modal = document.getElementById('overdueNotificationModal');
    if (!modal) {
      console.error('❌ Overdue Notification Modal not found in DOM');
      return;
    }

    if (!lead) {
      console.warn('❌ No lead data to display');
      return;
    }

    console.log(`✅ Toggle is enabled. Displaying popup for: ${lead.name}`);

    // Set as active
    isPopupActive = true;

    // Update lead information in the popup
    document.getElementById('overdueLeadName').textContent = lead.name || 'Unknown';

    // Setup phone number with click-to-reveal functionality
    const phoneElement = document.getElementById('overdueLeadPhone');
    const fullPhone = lead.number || '-';
    const maskedPhone = maskPhone(fullPhone);
    phoneElement.textContent = maskedPhone;
    phoneElement.setAttribute('data-full-phone', fullPhone);
    phoneElement.setAttribute('data-is-masked', 'true');
    phoneElement.style.cursor = 'pointer';
    phoneElement.style.userSelect = 'none';
    phoneElement.title = 'Click to reveal full number';

    // Remove any existing click handlers and add new one
    phoneElement.replaceWith(phoneElement.cloneNode(true));
    const newPhoneElement = document.getElementById('overdueLeadPhone');
    newPhoneElement.addEventListener('click', function () {
      const isMasked = this.getAttribute('data-is-masked') === 'true';
      const fullNumber = this.getAttribute('data-full-phone');

      if (isMasked) {
        this.textContent = fullNumber;
        this.setAttribute('data-is-masked', 'false');
        this.title = 'Click to mask number';
      } else {
        this.textContent = maskPhone(fullNumber);
        this.setAttribute('data-is-masked', 'true');
        this.title = 'Click to reveal full number';
      }
    });

    document.getElementById('overdueLeadStatus').textContent = lead.status || '-';
    document.getElementById('overdueLeadProject').textContent = lead.assign_project_name || 'N/A';
    document.getElementById('overdueLeadDate').textContent = formatDate(lead.follow_up_date) || '-';
    document.getElementById('overdueLeadTime').textContent = formatTime(lead.follow_up_time) || '-';

    // Show remaining count based on total database count
    const remaining = totalOverdueCount - currentIndex;
    document.getElementById('overdueRemainingCount').textContent = remaining;

    // Ensure modal is properly positioned and visible
    modal.style.setProperty('position', 'fixed', 'important');
    modal.style.setProperty('top', '0', 'important');
    modal.style.setProperty('left', '0', 'important');
    modal.style.setProperty('width', '100%', 'important');
    modal.style.setProperty('height', '100%', 'important');
    modal.style.setProperty('z-index', '100000', 'important');
    modal.style.setProperty('overflow-y', 'auto', 'important');
    modal.style.display = 'flex';

    // Lock body scrolling completely
    document.body.style.setProperty('overflow', 'hidden', 'important');
    document.body.style.setProperty('position', 'fixed', 'important');
    document.body.style.setProperty('width', '100%', 'important');
    document.body.style.setProperty('top', '0', 'important');

    console.log(`✅ Overdue lead popup displayed: ${lead.name}`);
  }

  // Close the popup
  function closeOverduePopup() {
    const modal = document.getElementById('overdueNotificationModal');
    if (modal) {
      modal.style.display = 'none';

      // Restore body scrolling
      document.body.style.removeProperty('overflow');
      document.body.style.removeProperty('position');
      document.body.style.removeProperty('width');
      document.body.style.removeProperty('top');

      isPopupActive = false;
      isStatusModalOpenFromOverdue = false; // Reset flag when closing popup
      console.log('✅ Overdue popup closed');
    }
  }

  // Handle Update Status button click - just hide popup temporarily
  function handleUpdateStatusClick() {
    const currentLead = overdueLeadsQueue[currentIndex];
    if (!currentLead) {
      console.log('⚠️ No current lead in queue');
      return;
    }

    console.log('📝 Opening status modal for lead:', currentLead.id);

    // Reuse the same clean reset as the normal open path
    if (typeof openStatusModal === 'function') {
      openStatusModal(currentLead.id, currentLead.name, '', '', currentLead.user_unique_id);
    }

    // Set values in the existing status modal
    $('#statusLeadName').text(currentLead.name);
    $('#statusLeadId').text(currentLead.id);
    $('#rowId').val(currentLead.id);
    $('#statusModal').data('userUniqueId', currentLead.user_unique_id);
    // NOTE: do NOT re-set newStatus here — openStatusModal already cleared it to blank.
    // Setting currentLead.status here was the reason the pre-filled status appeared.

    // Temporarily hide overdue modal while status modal is open
    const overdueModal = document.getElementById('overdueNotificationModal');
    if (overdueModal) {
      overdueModal.style.display = 'none';
      isStatusModalOpenFromOverdue = true; // Mark that we hid it for status modal
      console.log('   Set isStatusModalOpenFromOverdue = true');
    }

    // Show status modal
    const statusModal = document.getElementById('statusModal');
    if (statusModal) {
      statusModal.style.setProperty('position', 'fixed', 'important');
      statusModal.style.setProperty('top', '0', 'important');
      statusModal.style.setProperty('left', '0', 'important');
      statusModal.style.setProperty('width', '100%', 'important');
      statusModal.style.setProperty('height', '100%', 'important');
      statusModal.style.setProperty('z-index', '100050', 'important');
      statusModal.style.setProperty('overflow-y', 'auto', 'important');
      statusModal.style.display = 'flex';
    }

    // Keep body locked during status modal
    document.body.style.setProperty('overflow', 'hidden', 'important');
    document.body.style.setProperty('position', 'fixed', 'important');
    document.body.style.setProperty('width', '100%', 'important');
    document.body.style.setProperty('top', '0', 'important');
  }

  // Handle Skip button click - mark as skipped in database
  function handleSkipClick() {
    const currentLead = overdueLeadsQueue[currentIndex];
    if (!currentLead) return;

    console.log(`⏭️ Skipping lead ${currentIndex + 1}/${overdueLeadsQueue.length}`);

    // ✅ Close popup and advance IMMEDIATELY — no waiting for API
    closeOverduePopup();
    currentIndex++;
    setTimeout(() => {
      showNextLead();
    }, 300);

    // Mark as skipped in database in the background
    const formData = new FormData();
    formData.append('lead_id', currentLead.id);
    formData.append('action_status', 'skipped');
    fetch('update_status.php?mark_overdue_lead=1', {
      method: 'POST',
      body: formData
    })
      .then(() => console.log('✅ Lead marked as skipped in database (synced to all devices)'))
      .catch(error => console.error('❌ Error marking lead as skipped:', error));
  }

  // Handle History button click - open history sidebar
  function handleHistoryClick() {
    const currentLead = overdueLeadsQueue[currentIndex];
    if (!currentLead) {
      console.log('⚠️ No current lead in queue');
      return;
    }

    console.log('📖 Opening history sidebar for lead:', currentLead);
    console.log('📖 Lead ID:', currentLead.id, 'User ID:', currentLead.user_unique_id);

    const historySidebar = document.getElementById('uniqueLeadHistorySidebar');
    if (historySidebar) {
      historySidebar.style.display = 'block';
      historySidebar.classList.add('opened-from-overdue');
      setTimeout(() => historySidebar.classList.add('active'), 10);
    }

    if (followUpHistory) {
      followUpHistory.innerHTML = '<li class="list-group-item">Loading history...</li>';
    }

    // Fetch and display history using global function
    if (typeof window.fetchHistory === 'function') {
      console.log('📖 Calling fetchHistory...');
      window.fetchHistory(currentLead.id, currentLead.user_unique_id, { forceRefresh: true });
    } else {
      console.error('❌ fetchHistory function not found on window object');
    }

    console.log('✅ History sidebar opened from overdue popup');
  }

  // Handle Skip All button click - mark all in database
  async function handleSkipAllClick() {
    const { isConfirmed } = await Swal.fire({
      title: 'Skip All Remaining Leads?',
      text: 'Are you sure you want to skip all remaining overdue leads?',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes, Skip All',
      cancelButtonText: 'Cancel',
      confirmButtonColor: '#ef4444',
      cancelButtonColor: '#6b7280',
      customClass: {
        container: 'swal-high-zindex'
      }
    });

    if (!isConfirmed) {
      return;
    }

    console.log('⏭️ Skipping all remaining overdue leads');

    // ✅ Close popup IMMEDIATELY after confirmation — don't wait for the API
    currentIndex = overdueLeadsQueue.length;
    closeOverduePopup();

    // Use the bulk skip endpoint in the background
    try {
      const response = await fetch('update_status.php?skip_all_overdue_leads=1', {
        method: 'GET'
      });

      const result = await response.json();

      if (result.status === 'success') {
        console.log(`✅ Skipped ${result.count} overdue leads in database (synced to all devices)`);
      } else {
        console.error('❌ Error skipping leads:', result.message);
      }
    } catch (error) {
      console.error('❌ Error in Skip All:', error);
    }
  }

  // Listen for status update completion - mark as updated and move to next lead
  function listenForStatusUpdate() {
    console.log('🎧 Setting up leadStatusUpdated event listener...');

    document.addEventListener('leadStatusUpdated', async function (e) {
      console.log('📨 leadStatusUpdated event received:', e.detail);

      const currentLead = overdueLeadsQueue[currentIndex];
      if (!currentLead) {
        console.log('⚠️ No current lead in queue');
        return;
      }

      // Check if this event is for the current lead
      const eventLeadId = String(e.detail.leadId);
      const currentLeadId = String(currentLead.id);

      if (eventLeadId === currentLeadId) {
        console.log(`✅ Status updated for lead ${currentIndex + 1}/${overdueLeadsQueue.length}`);

        // Reset flag since we're successfully updating (not just closing modal)
        isStatusModalOpenFromOverdue = false;
        console.log('   Reset isStatusModalOpenFromOverdue = false (successful update)');

        // Mark as updated in database
        try {
          const formData = new FormData();
          formData.append('lead_id', currentLead.id);
          formData.append('action_status', 'updated');

          await fetch('update_status.php?mark_overdue_lead=1', {
            method: 'POST',
            body: formData
          });

          console.log('✅ Lead marked as updated in database');
        } catch (error) {
          console.error('❌ Error marking lead as updated:', error);
        }

        // Move to next lead
        currentIndex++;

        // Show next lead after short delay
        setTimeout(() => {
          showNextLead();
        }, 500);
      }
    });

    console.log('✅ leadStatusUpdated event listener registered');
  }

  // Setup event listeners
  function setupEventListeners() {
    // Update Status button
    document.addEventListener('click', function (e) {
      if (!isPopupActive) return;

      const statusModal = document.getElementById('statusModal');
      if (statusModal && statusModal.style.display === 'flex') {
        return;
      }

      if (e.target.closest('#updateOverdueLeadBtn')) {
        e.preventDefault();
        e.stopPropagation();
        handleUpdateStatusClick();
      }
    });

    // Phone icon click - Make call with confirmation
    document.addEventListener('click', async function (e) {
      if (!isPopupActive) return;

      const statusModal = document.getElementById('statusModal');
      if (statusModal && statusModal.style.display === 'flex') {
        return;
      }

      // Only fire when the phone icon (<i class="fas fa-phone">) is clicked
      const isPhoneIcon = e.target.classList.contains('fa-phone') ||
        e.target.closest('[class*="fa-phone"]');
      const isInsidePhoneContainer = e.target.closest('.overdue-lead-phone');

      if (isPhoneIcon && isInsidePhoneContainer) {
        e.preventDefault();
        e.stopPropagation();

        console.log('📞 Phone icon clicked in overdue popup');

        const currentLead = overdueLeadsQueue[currentIndex];
        if (!currentLead || !currentLead.number) {
          await Swal.fire({
            title: 'Error',
            text: 'Phone number not available for this lead',
            icon: 'error',
            confirmButtonText: 'OK',
            customClass: {
              container: 'swal-high-zindex',
              popup: 'swal-high-zindex'
            }
          });
          return;
        }

        // Validate once before opening the confirmation
        const phoneStr = formatPhoneForPopupCall(currentLead.number);

        if (!phoneStr) {
          await Swal.fire({
            title: 'Error',
            text: 'Invalid phone number format',
            icon: 'error',
            confirmButtonText: 'OK',
            customClass: {
              container: 'swal-high-zindex',
              popup: 'swal-high-zindex'
            }
          });
          return;
        }

        console.log('📞 Showing call confirmation dialog');

        // Launch dialer directly from confirm button click path
        // (more reliable on mobile browsers/webviews).
        const { isConfirmed } = await Swal.fire({
          title: 'Are you sure?',
          text: 'Do You Really Want To Make This Call?',
          icon: 'question',
          showCancelButton: true,
          confirmButtonColor: '#22c55e',
          cancelButtonColor: '#dc3545',
          confirmButtonText: 'Yes, call now!',
          cancelButtonText: 'Cancel',
          preConfirm: () => {
            console.log('📞 Initiating call to:', phoneStr);
            openPopupDialpad(phoneStr);
            return true;
          },
          customClass: {
            container: 'swal-high-zindex',
            popup: 'swal-high-zindex'
          }
        });

        if (!isConfirmed) {
          console.log('📞 Call cancelled by user');
          return;
        }
      }
    });

    // Skip button
    document.addEventListener('click', function (e) {
      if (!isPopupActive) return;

      const statusModal = document.getElementById('statusModal');
      if (statusModal && statusModal.style.display === 'flex') {
        return;
      }

      if (e.target.closest('#skipOverdueLeadBtn')) {
        e.preventDefault();
        e.stopPropagation();
        handleSkipClick();
      }
    });

    // History button
    document.addEventListener('click', function (e) {
      if (!isPopupActive) return;

      const statusModal = document.getElementById('statusModal');
      if (statusModal && statusModal.style.display === 'flex') {
        return;
      }

      if (e.target.closest('#overdueHistoryBtn')) {
        e.preventDefault();
        e.stopPropagation();
        handleHistoryClick();
      }
    });

    // Skip All button
    document.addEventListener('click', function (e) {
      if (!isPopupActive) return;

      const statusModal = document.getElementById('statusModal');
      if (statusModal && statusModal.style.display === 'flex') {
        return;
      }

      if (e.target.closest('#skipAllOverdueLeadsBtn')) {
        e.preventDefault();
        e.stopPropagation();
        handleSkipAllClick();
      }
    });
  }

  // Helper: Mask phone number
  function maskPhone(phone) {
    if (!phone) return '';
    const phoneStr = String(phone).replace(/\D/g, '');
    if (phoneStr.length <= 5) return phoneStr;
    return '*****' + phoneStr.slice(-5);
  }

  // Helper: Format date
  function formatDate(dateStr) {
    if (!dateStr) return '';
    try {
      const date = new Date(dateStr);
      return date.toLocaleDateString('en-IN', {
        day: '2-digit',
        month: 'short',
        year: 'numeric'
      });
    } catch (e) {
      return dateStr;
    }
  }

  // Helper: Format time
  function formatTime(timeStr) {
    if (!timeStr) return '';
    try {
      const [hours, minutes] = timeStr.split(':');
      const hour = parseInt(hours);
      const ampm = hour >= 12 ? 'PM' : 'AM';
      const displayHour = hour % 12 || 12;
      return `${displayHour}:${minutes} ${ampm}`;
    } catch (e) {
      return timeStr;
    }
  }

  // Restore overdue popup if it was temporarily hidden for status modal
  function restoreOverduePopup() {
    console.log('🔄 restoreOverduePopup called');
    console.log('   isPopupActive:', isPopupActive);
    console.log('   isStatusModalOpenFromOverdue:', isStatusModalOpenFromOverdue);
    console.log('   currentIndex:', currentIndex);
    console.log('   queue length:', overdueLeadsQueue.length);

    const currentLead = overdueLeadsQueue[currentIndex];
    const overdueModal = document.getElementById('overdueNotificationModal');

    // Check if we have a valid lead and the modal exists
    if (!currentLead) {
      console.log('   No current lead in queue - nothing to restore');
      isStatusModalOpenFromOverdue = false;
      return false; // Return false to indicate we didn't restore
    }

    if (!overdueModal) {
      console.log('   Overdue modal not found in DOM');
      isStatusModalOpenFromOverdue = false;
      return false;
    }

    // Check if modal is hidden AND either:
    // 1. Status modal was opened from overdue popup, OR
    // 2. Popup is still active (was showing before)
    const shouldRestore = (isStatusModalOpenFromOverdue || isPopupActive) &&
      overdueModal.style.display === 'none';

    console.log('   Should restore?', shouldRestore);
    console.log('   Modal display:', overdueModal.style.display);

    if (shouldRestore) {
      console.log('   ✅ Restoring overdue popup for lead:', currentLead.id);
      showOverdueLeadPopup(currentLead);
      isStatusModalOpenFromOverdue = false; // Reset flag after successful restore
      return true; // Return true to indicate we restored
    } else {
      console.log('   ❌ Conditions not met for restore');
      isStatusModalOpenFromOverdue = false;
      return false;
    }
  }

  // Debug function to check system state
  function debugOverdueSystem() {
    console.log('=== OVERDUE SYSTEM DEBUG INFO ===');
    console.log('Queue length:', overdueLeadsQueue.length);
    console.log('Current index:', currentIndex);
    console.log('Is popup active:', isPopupActive);
    console.log('Total overdue count:', totalOverdueCount);
    console.log('Modal element exists:', !!document.getElementById('overdueNotificationModal'));
    console.log('Document ready state:', document.readyState);
    console.log('Note: Tracking is now database-based (overdue_leads_tracking table)');
    console.log('      Dismissed leads sync across all devices automatically');
    console.log('================================');
    return {
      queueLength: overdueLeadsQueue.length,
      currentIndex: currentIndex,
      isActive: isPopupActive,
      totalCount: totalOverdueCount,
      modalExists: !!document.getElementById('overdueNotificationModal'),
      trackingMode: 'database'
    };
  }

  // Expose functions globally
  window.OverdueLeadSystem = {
    init: initOverdueLeadSystem,
    refresh: fetchAllOverdueLeads,
    isActive: function () { return isPopupActive; },
    close: closeOverduePopup,
    restorePopup: restoreOverduePopup,
    debug: debugOverdueSystem
  };

  console.log('✅ OverdueLeadSystem exposed to window');

  // Initialize when page is loaded
  if (document.readyState === 'complete') {
    console.log('📄 Page already loaded, initializing...');
    setupEventListeners();
    listenForStatusUpdate();
    initOverdueLeadSystem();
  } else {
    console.log('📄 Waiting for page load...');
    window.addEventListener('load', function () {
      console.log('📄 Page loaded, initializing overdue system...');
      setupEventListeners();
      listenForStatusUpdate();
      initOverdueLeadSystem();
    });
  }

  console.log('🏁 Overdue Lead System IIFE completed');

})();
// ============================================================================
// FOLLOW-UP REMINDER SYSTEM
// ============================================================================
(function () {
  'use strict';

  console.log('⏰ Initializing Follow-up Reminder System...');

  // Check interval: every 2 minutes
  const CHECK_INTERVAL = 2 * 60 * 1000; // 2 minutes in milliseconds

  // Store interval ID
  let checkIntervalId = null;

  // Check for upcoming follow-ups
  async function checkUpcomingFollowups() {
    try {
      console.log('🔍 Checking for upcoming follow-ups...');

      const response = await fetch('update_status.php?check_upcoming_followups=1');
      const data = await response.json();

      if (data.status === 'success' && data.count > 0) {
        console.log(`📋 Found ${data.count} upcoming follow-up(s)`);

        // Process each lead individually to check if notification already exists
        for (const lead of data.leads) {
          await storeAndNotifyFollowup(lead);
        }
      } else {
        console.log('✓ No upcoming follow-ups in next 10 minutes');
      }
    } catch (error) {
      console.error('❌ Error checking upcoming follow-ups:', error);
    }
  }

  // Store and notify for a single follow-up (prevents duplicates)
  async function storeAndNotifyFollowup(lead) {
    const time = formatFollowupTime(lead.followup_datetime);
    const minutes = lead.minutes_until;

    const title = '⏰ Follow-up Reminder';
    let message = `Follow-up scheduled for ${time}\n\n`;
    message += `Lead: ${lead.lead_name}\n`;
    message += `Status: ${lead.status}`;
    if (lead.project_name) {
      message += `\nProject: ${lead.project_name}`;
    }

    // Store in database FIRST to check if notification already exists
    try {
      const formData = new FormData();
      formData.append('store_followup_notification', '1');
      formData.append('title', title);
      formData.append('body', message);
      formData.append('leads', JSON.stringify([{
        lead_id: lead.lead_id,
        lead_name: lead.lead_name,
        status: lead.status,
        followup_time: lead.followup_datetime
      }]));

      const response = await fetch('update_status.php', {
        method: 'POST',
        body: formData
      });

      const result = await response.json();

      if (result.status === 'success' && !result.duplicate) {
        console.log('✅ New follow-up reminder stored - showing notification');

        // Only show notification if it's NEW (not duplicate)
        showNotification(title, message, 'info');
        showBrowserNotification(title, `${lead.lead_name} - ${lead.status} @ ${time}`);
        playNotificationSound();

        // Refresh notification badge
        if (typeof window.refreshNotificationBadge === 'function') {
          window.refreshNotificationBadge();
        }
      } else if (result.status === 'exists' || result.duplicate) {
        console.log('✓ Notification already exists for this lead - not showing again');
      }
    } catch (error) {
      console.error('❌ Error storing follow-up reminder:', error);
    }
  }

  // Format follow-up datetime to readable time
  function formatFollowupTime(datetime) {
    try {
      const date = new Date(datetime);
      return date.toLocaleTimeString('en-US', {
        hour: 'numeric',
        minute: '2-digit',
        hour12: true
      });
    } catch (e) {
      return datetime;
    }
  }

  // Show browser notification (if permission granted)
  function showBrowserNotification(title, body) {
    if (!('Notification' in window)) {
      return;
    }

    if (Notification.permission === 'granted') {
      new Notification(title, {
        body: body,
        icon: '/assets/images/notification-icon.png',
        tag: 'followup-reminder',
        requireInteraction: true
      });
    } else if (Notification.permission !== 'denied') {
      Notification.requestPermission().then(permission => {
        if (permission === 'granted') {
          new Notification(title, {
            body: body,
            icon: '/assets/images/notification-icon.png',
            tag: 'followup-reminder',
            requireInteraction: true
          });
        }
      });
    }
  }

  // Play notification sound
  function playNotificationSound() {
    try {
      const audio = new Audio('/assets/sounds/notification.mp3');
      audio.volume = 0.5;
      audio.play().catch(e => console.log('Could not play notification sound'));
    } catch (e) {
      // Sound file not available, ignore
    }
  }

  // Start the periodic check
  function startFollowupReminders() {
    // Check immediately on start
    setTimeout(() => checkUpcomingFollowups(), 5000); // Wait 5 seconds after page load

    // Then check every 2 minutes
    checkIntervalId = setInterval(checkUpcomingFollowups, CHECK_INTERVAL);

    console.log('✅ Follow-up reminder checks started (every 2 minutes)');
  }

  // Stop the periodic check
  function stopFollowupReminders() {
    if (checkIntervalId) {
      clearInterval(checkIntervalId);
      checkIntervalId = null;
      console.log('⏸ Follow-up reminder checks stopped');
    }
  }

  // Expose control functions globally
  window.FollowupReminderSystem = {
    start: startFollowupReminders,
    stop: stopFollowupReminders,
    checkNow: checkUpcomingFollowups
  };

  // Auto-start when page is ready
  if (document.readyState === 'complete') {
    startFollowupReminders();
  } else {
    window.addEventListener('load', startFollowupReminders);
  }

  console.log('✅ Follow-up Reminder System initialized');

})();

// ============================================================================
// EXACT-TIME FOLLOW-UP POPUP SYSTEM
// ============================================================================
(function () {
  'use strict';

  console.log('⏰ Initializing Exact-Time Follow-up Popup System...');

  // Queue and state management
  let exactTimeLeadsQueue = [];
  let currentIndex = 0;
  let totalExactTimeCount = 0;
  let isPopupActive = false;

  // Check interval: every 30 seconds for exact time (more responsive)
  const CHECK_INTERVAL = 30 * 1000; // 30 seconds in milliseconds
  let checkIntervalId = null;

  // Local helper for this IIFE scope (main table formatter is in a different scope).
  function formatPhoneForPopupCall(num) {
    const digits = String(num || '').replace(/\D/g, '');
    if (!digits) return '';
    if (digits.length === 12 && digits.startsWith('91')) return '+' + digits;
    if (digits.length === 10) return '+91' + digits;
    return '+' + digits;
  }

  function openPopupDialpad(phoneNumber) {
    const dialTarget = String(phoneNumber || '').trim();
    if (!dialTarget) return;

    const telHref = dialTarget.startsWith('tel:') ? dialTarget : `tel:${dialTarget}`;

    const a = document.createElement('a');
    a.href = telHref;
    a.style.display = 'none';
    document.body.appendChild(a);
    a.click();
    setTimeout(() => a.remove(), 150);

    setTimeout(() => {
      if (!document.hidden && document.visibilityState === 'visible') {
        window.location.assign(telHref);
      }
    }, 250);
  }

  // LocalStorage key for tracking dismissed leads
  const DISMISSED_LEADS_KEY = 'dismissedExactTimeLeads';
  const DISMISS_DURATION = 25 * 60 * 60 * 1000; // 25 hours in milliseconds (covers 24h reschedule + buffer)

  // Create unique key for lead + datetime combination
  function createDismissKey(leadId, followupDate, followupTime) {
    // Combine lead_id with the scheduled date and time to make it unique per scheduled time
    return `${leadId}_${followupDate}_${followupTime}`;
  }

  // Get dismissed leads from localStorage
  function getDismissedLeads() {
    try {
      const stored = localStorage.getItem(DISMISSED_LEADS_KEY);
      if (!stored) return {};

      const dismissed = JSON.parse(stored);
      const now = Date.now();

      // Clean up old entries (older than DISMISS_DURATION)
      const cleaned = {};
      for (const [key, timestamp] of Object.entries(dismissed)) {
        if (now - timestamp < DISMISS_DURATION) {
          cleaned[key] = timestamp;
        }
      }

      // Save cleaned version
      localStorage.setItem(DISMISSED_LEADS_KEY, JSON.stringify(cleaned));
      return cleaned;
    } catch (e) {
      console.error('Error reading dismissed leads:', e);
      return {};
    }
  }

  // Mark a lead with specific datetime as dismissed
  function markLeadDismissed(leadId, followupDate, followupTime) {
    try {
      const dismissed = getDismissedLeads();
      const key = createDismissKey(leadId, followupDate, followupTime);
      dismissed[key] = Date.now();
      localStorage.setItem(DISMISSED_LEADS_KEY, JSON.stringify(dismissed));
      console.log(`✅ Lead ${leadId} (${followupDate} ${followupTime}) marked as dismissed`);
    } catch (e) {
      console.error('Error marking lead as dismissed:', e);
    }
  }

  // Check if a lead with specific datetime was recently dismissed
  function isLeadRecentlyDismissed(leadId, followupDate, followupTime) {
    const dismissed = getDismissedLeads();
    const key = createDismissKey(leadId, followupDate, followupTime);
    return dismissed.hasOwnProperty(key);
  }

  // Helper: Mask phone number
  function maskPhone(phone) {
    if (!phone) return '';
    const phoneStr = String(phone).replace(/\D/g, '');
    if (phoneStr.length <= 5) return phoneStr;
    return '*****' + phoneStr.slice(-5);
  }

  // Helper: Format date
  function formatDate(dateStr) {
    if (!dateStr) return '';
    try {
      const date = new Date(dateStr);
      return date.toLocaleDateString('en-IN', {
        day: '2-digit',
        month: 'short',
        year: 'numeric'
      });
    } catch (e) {
      return dateStr;
    }
  }

  // Helper: Format time
  function formatTime(timeStr) {
    if (!timeStr) return '';
    try {
      const [hours, minutes] = timeStr.split(':');
      const hour = parseInt(hours);
      const ampm = hour >= 12 ? 'PM' : 'AM';
      const hour12 = hour % 12 || 12;
      return `${hour12}:${minutes} ${ampm}`;
    } catch (e) {
      return timeStr;
    }
  }

  // Check for exact-time follow-ups
  async function checkExactTimeFollowups() {
    try {
    // Don't check if popup is already showing
    if (isPopupActive) {
      console.log('⏸ Exact-time popup already active, skipping check');
      return;
    }

      console.log('🔍 Checking for exact-time follow-ups...');
      console.log('   Current time:', new Date().toLocaleString());

      const response = await fetch('update_status.php?check_exact_followups=1');
      const data = await response.json();

      console.log('   Server response:', {
        count: data.count,
        server_time: data.check_time,
        window: data.window_start + ' to ' + data.window_end
      });

      if (data.status === 'success' && data.count > 0) {
        console.log(`📋 Found ${data.count} exact-time follow-up(s):`, data.leads.map(l => ({
          name: l.lead_name,
          scheduled: l.followup_datetime,
          minutes_passed: l.minutes_passed
        })));

        // Filter out recently dismissed leads (checks lead_id + datetime combination)
        const dismissedLeads = getDismissedLeads();
        console.log('   Currently dismissed leads:', Object.keys(dismissedLeads).length);

        const filteredLeads = data.leads.filter(lead => {
          if (isLeadRecentlyDismissed(lead.lead_id, lead.follow_up_date, lead.follow_up_time)) {
            console.log(`⏭️ Skipping lead ${lead.lead_id} (${lead.follow_up_date} ${lead.follow_up_time}) - recently dismissed/rescheduled`);
            return false;
          }
          return true;
        });

        if (filteredLeads.length > 0) {
          console.log(`✅ ${filteredLeads.length} lead(s) to show after filtering`);
          exactTimeLeadsQueue = filteredLeads;
          totalExactTimeCount = filteredLeads.length;
          currentIndex = 0;
          showNextLead();
        } else {
          console.log('✓ All exact-time leads were recently dismissed');
        }
      } else {
        console.log('✓ No exact-time follow-ups right now (within ±2 min window)');
      }
    } catch (error) {
      console.error('❌ Error checking exact-time follow-ups:', error);
    }
  }

  // Show the next lead in queue
  function showNextLead() {
    if (currentIndex >= exactTimeLeadsQueue.length) {
      console.log('✅ All exact-time follow-ups processed');
      closeExactTimePopup();
      return;
    }

    const lead = exactTimeLeadsQueue[currentIndex];
    if (lead) {
      showExactTimeLeadPopup(lead);
    }
  }

  // Show the exact-time follow-up popup with lead data
  function showExactTimeLeadPopup(lead) {
    const modal = document.getElementById('exactTimeFollowupModal');
    if (!modal) {
      console.error('❌ Exact Time Follow-up Modal not found in DOM');
      return;
    }

    if (!lead) {
      console.warn('❌ No lead data to display');
      return;
    }

    // Set as active
    isPopupActive = true;

    // Update lead information in the popup
    document.getElementById('exactTimeLeadName').textContent = lead.lead_name || 'Unknown';

    // Setup phone number with click-to-reveal functionality
    const phoneElement = document.getElementById('exactTimeLeadPhone');
    const fullPhone = lead.lead_phone || '-';
    phoneElement.textContent = maskPhone(fullPhone);
    phoneElement.setAttribute('data-full-phone', fullPhone);
    phoneElement.setAttribute('data-is-masked', 'true');
    phoneElement.style.cursor = 'pointer';
    phoneElement.style.userSelect = 'none';
    phoneElement.title = 'Click to reveal full number';

    // Remove any existing click handlers and add new one
    phoneElement.replaceWith(phoneElement.cloneNode(true));
    const newPhoneElement = document.getElementById('exactTimeLeadPhone');
    newPhoneElement.addEventListener('click', function () {
      const isMasked = this.getAttribute('data-is-masked') === 'true';
      const fullNumber = this.getAttribute('data-full-phone');

      if (isMasked) {
        this.textContent = fullNumber;
        this.setAttribute('data-is-masked', 'false');
        this.title = 'Click to mask number';
      } else {
        this.textContent = maskPhone(fullNumber);
        this.setAttribute('data-is-masked', 'true');
        this.title = 'Click to reveal full number';
      }
    });

    // Setup phone icon with click-to-call functionality
    const phoneIcon = document.querySelector('#exactTimeFollowupModal .fa-phone');
    if (phoneIcon) {
      phoneIcon.style.cursor = 'pointer';
      phoneIcon.replaceWith(phoneIcon.cloneNode(true));
      const newPhoneIcon = document.querySelector('#exactTimeFollowupModal .fa-phone');

      newPhoneIcon.addEventListener('click', async function (e) {
        e.preventDefault();
        e.stopPropagation();

        console.log('📞 Phone icon clicked in exact time popup');

        if (!fullPhone || fullPhone === '-') {
          await Swal.fire({
            title: 'Error',
            text: 'Phone number not available for this lead',
            icon: 'error',
            confirmButtonText: 'OK',
            customClass: { container: 'swal-high-zindex', popup: 'swal-high-zindex' }
          });
          return;
        }

        const phoneStr = formatPhoneForPopupCall(fullPhone);
        if (!phoneStr) {
          await Swal.fire({
            title: 'Error',
            text: 'Invalid phone number format',
            icon: 'error',
            confirmButtonText: 'OK',
            customClass: { container: 'swal-high-zindex', popup: 'swal-high-zindex' }
          });
          return;
        }

        const { isConfirmed } = await Swal.fire({
          title: 'Are you sure?',
          text: 'Do You Really Want To Make This Call?',
          icon: 'question',
          showCancelButton: true,
          confirmButtonColor: '#22c55e',
          cancelButtonColor: '#dc3545',
          confirmButtonText: 'Yes, call now!',
          cancelButtonText: 'Cancel',
          preConfirm: () => {
            console.log('📞 Initiating call to:', phoneStr);
            openPopupDialpad(phoneStr);
            return true;
          },
          customClass: { container: 'swal-high-zindex', popup: 'swal-high-zindex' }
        });

        if (!isConfirmed) return;
      });
    }
    document.getElementById('exactTimeLeadStatus').textContent = lead.status || '-';
    document.getElementById('exactTimeLeadProject').textContent = lead.project_name || 'N/A';
    document.getElementById('exactTimeLeadDate').textContent = formatDate(lead.follow_up_date) || '-';
    document.getElementById('exactTimeLeadTime').textContent = formatTime(lead.follow_up_time) || '-';

    // Show remarks if available
    const remarksSection = document.getElementById('exactTimeRemarksSection');
    const remarksText = document.getElementById('exactTimeLeadRemarks');
    if (lead.remarks && lead.remarks.trim()) {
      remarksText.textContent = lead.remarks;
      remarksSection.style.display = 'block';
    } else {
      remarksSection.style.display = 'none';
    }

    // Show remaining count
    const remaining = totalExactTimeCount - currentIndex;
    document.getElementById('exactTimeRemainingCount').textContent = remaining;

    // Ensure modal is properly positioned and visible
    modal.style.setProperty('position', 'fixed', 'important');
    modal.style.setProperty('top', '0', 'important');
    modal.style.setProperty('left', '0', 'important');
    modal.style.setProperty('width', '100%', 'important');
    modal.style.setProperty('height', '100%', 'important');
    modal.style.setProperty('z-index', '100000', 'important');
    modal.style.setProperty('overflow-y', 'auto', 'important');
    modal.style.display = 'flex';

    // Lock body scrolling completely
    document.body.style.setProperty('overflow', 'hidden', 'important');
    document.body.style.setProperty('position', 'fixed', 'important');
    document.body.style.setProperty('width', '100%', 'important');
    document.body.style.setProperty('top', '0', 'important');

    console.log(`✅ Exact-time follow-up popup displayed: ${lead.lead_name}`);
  }

  // Close the popup
  function closeExactTimePopup() {
    const modal = document.getElementById('exactTimeFollowupModal');
    if (modal) {
      modal.style.display = 'none';

      // Restore body scrolling
      document.body.style.removeProperty('overflow');
      document.body.style.removeProperty('position');
      document.body.style.removeProperty('width');
      document.body.style.removeProperty('top');

      isPopupActive = false;
      console.log('✅ Exact-time popup closed');
    }
  }

  // Handle Update Status button click
  function handleUpdateExactTimeClick() {
    const currentLead = exactTimeLeadsQueue[currentIndex];
    if (!currentLead) {
      console.log('⚠️ No current lead in queue');
      return;
    }

    console.log('📝 Opening status modal for exact-time lead:', currentLead.lead_id);

    // Reuse the same clean reset as the normal open path
    if (typeof openStatusModal === 'function') {
      openStatusModal(currentLead.lead_id, currentLead.lead_name, '', '', currentLead.user_unique_id);
    }

    // Set values in the existing status modal
    $('#statusLeadName').text(currentLead.lead_name);
    $('#statusLeadId').text(currentLead.lead_id);
    $('#rowId').val(currentLead.lead_id);
    $('#statusModal').data('userUniqueId', currentLead.user_unique_id);
    // NOTE: do NOT re-set newStatus here — openStatusModal already cleared it to blank.
    // Setting currentLead.status here was the reason the pre-filled status appeared.

    // Temporarily hide exact-time modal while status modal is open
    const exactTimeModal = document.getElementById('exactTimeFollowupModal');
    if (exactTimeModal) {
      exactTimeModal.style.display = 'none';
    }

    // Show status modal
    const statusModal = document.getElementById('statusModal');
    if (statusModal) {
      statusModal.style.setProperty('position', 'fixed', 'important');
      statusModal.style.setProperty('top', '0', 'important');
      statusModal.style.setProperty('left', '0', 'important');
      statusModal.style.setProperty('width', '100%', 'important');
      statusModal.style.setProperty('height', '100%', 'important');
      statusModal.style.setProperty('z-index', '100050', 'important');
      statusModal.style.setProperty('overflow-y', 'auto', 'important');
      statusModal.style.display = 'flex';
    }

    // Keep body locked during status modal
    document.body.style.setProperty('overflow', 'hidden', 'important');
    document.body.style.setProperty('position', 'fixed', 'important');
    document.body.style.setProperty('width', '100%', 'important');
    document.body.style.setProperty('top', '0', 'important');
  }

  // Handle Skip button click - reschedule for 24 hours
  function handleSkipExactTimeClick() {
    const currentLead = exactTimeLeadsQueue[currentIndex];
    if (!currentLead) return;

    console.log(`⏭️ Skipping exact-time lead ${currentIndex + 1}/${exactTimeLeadsQueue.length} - Rescheduling for 24h`);

    // ✅ Mark dismissed in localStorage immediately so it won't reappear on next check
    markLeadDismissed(currentLead.lead_id, currentLead.follow_up_date, currentLead.follow_up_time);

    // ✅ Close popup and advance IMMEDIATELY — no waiting for API
    closeExactTimePopup();
    currentIndex++;
    setTimeout(() => {
      showNextLead();
    }, 300);

    // Reschedule in the database in the background
    const formData = new FormData();
    formData.append('reschedule_followup', '1');
    formData.append('lead_id', currentLead.lead_id);
    formData.append('skip_mode', 'single');
    fetch('update_status.php', {
      method: 'POST',
      body: formData
    })
      .then(r => r.json())
      .then(result => {
        if (result.status === 'success') {
          console.log('✅ Lead rescheduled for 24 hours later:', result.new_datetime);
          showNotification(`Follow-up Rescheduled: ${currentLead.lead_name} rescheduled for 24 hours later`, 'info');
        }
      })
      .catch(error => console.error('❌ Error rescheduling lead:', error));
  }

  // Handle Skip All button click
  async function handleSkipAllExactTimeClick() {
    const { isConfirmed } = await Swal.fire({
      title: 'Skip All Remaining Follow-ups?',
      text: 'Are you sure you want to skip all remaining follow-ups? They will be rescheduled for 24 hours later.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes, Reschedule All',
      cancelButtonText: 'Cancel',
      confirmButtonColor: '#ef4444',
      cancelButtonColor: '#6b7280',
      customClass: {
        container: 'swal-high-zindex'
      }
    });

    if (!isConfirmed) {
      return;
    }

    console.log('⏭️ Skipping all remaining exact-time leads - Rescheduling for 24h');

    const remainingLeads = exactTimeLeadsQueue.slice(currentIndex);

    // ✅ Close popup IMMEDIATELY after confirmation — don't wait for API calls
    // Also mark ALL leads as dismissed in localStorage right now to prevent re-showing
    for (const lead of remainingLeads) {
      markLeadDismissed(lead.lead_id, lead.follow_up_date, lead.follow_up_time);
    }
    currentIndex = exactTimeLeadsQueue.length;
    closeExactTimePopup();

    showNotification(`All Follow-ups Rescheduled: ${remainingLeads.length} follow-up(s) rescheduled for 24 hours later`, 'info');

    // Reschedule all remaining leads in the background
    for (const lead of remainingLeads) {
      try {
        const formData = new FormData();
        formData.append('reschedule_followup', '1');
        formData.append('lead_id', lead.lead_id);
        formData.append('skip_mode', 'bulk');

        const response = await fetch('update_status.php', {
          method: 'POST',
          body: formData
        });

        const result = await response.json();
        if (result.status === 'success') {
          console.log(`✅ Lead ${lead.lead_id} rescheduled for 24h`);
        } else {
          console.warn(`⚠️ Lead ${lead.lead_id} reschedule API returned non-success, but already dismissed locally`);
        }
      } catch (error) {
        console.error(`❌ Error rescheduling lead ${lead.lead_id}:`, error);
      }
    }
  }

  // Listen for status update completion - move to next lead
  function listenForExactTimeStatusUpdate() {
    console.log('🎧 Setting up leadStatusUpdated event listener for exact-time...');

    document.addEventListener('leadStatusUpdated', async function (e) {
      console.log('📨 leadStatusUpdated event received for exact-time:', e.detail);

      const currentLead = exactTimeLeadsQueue[currentIndex];
      if (!currentLead) {
        console.log('⚠️ No current lead in exact-time queue');
        return;
      }

      // Check if this event is for the current lead
      const eventLeadId = String(e.detail.leadId);
      const currentLeadId = String(currentLead.lead_id);

      if (eventLeadId === currentLeadId) {
        console.log(`✅ Status updated for exact-time lead ${currentIndex + 1}/${exactTimeLeadsQueue.length}`);

        // Move to next lead
        currentIndex++;

        // Show next lead after short delay
        setTimeout(() => {
          showNextLead();
        }, 500);
      }
    });

    console.log('✅ leadStatusUpdated event listener registered for exact-time');
  }

  // Restore exact-time popup if it was temporarily hidden for status modal
  function restoreExactTimePopup() {
    console.log('🔄 restoreExactTimePopup called');

    const currentLead = exactTimeLeadsQueue[currentIndex];
    if (currentLead) {
      console.log('   Restoring exact-time popup for lead:', currentLead.lead_id);
      const exactTimeModal = document.getElementById('exactTimeFollowupModal');
      if (exactTimeModal && exactTimeModal.style.display === 'none') {
        console.log('   Showing exact-time modal again...');
        showExactTimeLeadPopup(currentLead);
        return true; // Return true to indicate we restored
      }
    } else {
      console.log('   No current lead in exact-time queue');
    }
    return false; // Return false if we didn't restore
  }

  // Handle History button click for exact-time follow-up
  function handleExactTimeHistoryClick() {
    const currentLead = exactTimeLeadsQueue[currentIndex];
    if (!currentLead) {
      console.log('⚠️ No current lead in exact-time queue');
      return;
    }

    console.log('📖 Opening history sidebar for exact-time lead:', currentLead.lead_id);

    const historySidebar = document.getElementById('uniqueLeadHistorySidebar');
    if (historySidebar) {
      historySidebar.style.display = 'block';
      historySidebar.classList.add('opened-from-overdue');
      setTimeout(() => historySidebar.classList.add('active'), 10);
    }

    if (followUpHistory) {
      followUpHistory.innerHTML = '<li class="list-group-item">Loading history...</li>';
    }

    // Fetch and display history using global function
    if (typeof window.fetchHistory === 'function') {
      window.fetchHistory(currentLead.lead_id, currentLead.user_unique_id, { forceRefresh: true });
    } else {
      console.error('❌ fetchHistory function not found on window object');
    }

    console.log('✅ History sidebar opened from exact-time popup');
  }

  // Setup event listeners
  function setupExactTimeEventListeners() {
    const updateBtn = document.getElementById('updateExactTimeLeadBtn');
    const skipBtn = document.getElementById('skipExactTimeLeadBtn');
    const skipAllBtn = document.getElementById('skipAllExactTimeLeadsBtn');
    const historyBtn = document.getElementById('exactTimeHistoryBtn');

    if (updateBtn) {
      updateBtn.addEventListener('click', handleUpdateExactTimeClick);
      console.log('✅ Update button listener attached');
    }

    if (historyBtn) {
      historyBtn.addEventListener('click', handleExactTimeHistoryClick);
      console.log('✅ History button listener attached');
    }

    if (skipBtn) {
      skipBtn.addEventListener('click', handleSkipExactTimeClick);
      console.log('✅ Skip button listener attached');
    }

    if (skipAllBtn) {
      skipAllBtn.addEventListener('click', handleSkipAllExactTimeClick);
      console.log('✅ Skip All button listener attached');
    }
  }

  // Start the periodic check
  function startExactTimeChecks() {
    // Check immediately after 3 seconds (like overdue system)
    setTimeout(() => checkExactTimeFollowups(), 3000);

    // Then check every 30 seconds
    checkIntervalId = setInterval(checkExactTimeFollowups, CHECK_INTERVAL);

    console.log('✅ Exact-time follow-up checks started (every 30 seconds)');
  }

  // Stop the periodic check
  function stopExactTimeChecks() {
    if (checkIntervalId) {
      clearInterval(checkIntervalId);
      checkIntervalId = null;
      console.log('⏸ Exact-time follow-up checks stopped');
    }
  }

  // Clear dismissed leads (for debugging)
  function clearDismissedLeads() {
    try {
      localStorage.removeItem(DISMISSED_LEADS_KEY);
      console.log('🗑️ Cleared all dismissed exact-time leads from localStorage');
      return true;
    } catch (e) {
      console.error('Error clearing dismissed leads:', e);
      return false;
    }
  }

  // Expose functions globally
  window.ExactTimeFollowupSystem = {
    start: startExactTimeChecks,
    stop: stopExactTimeChecks,
    checkNow: checkExactTimeFollowups,
    close: closeExactTimePopup,
    restorePopup: restoreExactTimePopup,
    clearDismissed: clearDismissedLeads,
    getDismissed: getDismissedLeads
  };

  // Initialize when page is loaded
  if (document.readyState === 'complete') {
    console.log('📄 Page already loaded, initializing exact-time system...');
    setupExactTimeEventListeners();
    listenForExactTimeStatusUpdate();
    startExactTimeChecks();
  } else {
    console.log('📄 Waiting for page load...');
    window.addEventListener('load', function () {
      console.log('📄 Page loaded, initializing exact-time system...');
      setupExactTimeEventListeners();
      listenForExactTimeStatusUpdate();
      startExactTimeChecks();
    });
  }

  console.log('✅ Exact-Time Follow-up Popup System initialized');

})();

$(document).ready(function () {
  // Handle click event on status options
  $('.status-option').on('click', function () {
    // Get the selected status from the data attribute
    var selectedStatus = $(this).data('status');
    // Make an AJAX request to update_status.php
    $.ajax({
      url: 'update_status.php',
      type: 'POST',
      data: { status: selectedStatus },
      success: function (response) {
        // Handle the response from the server
        console.log(response); // You can process the response as needed
        // Optionally, update the UI based on the response
      },
      error: function (xhr, status, error) {
        console.error('Error fetching data:', error);
      }
    });
  });
});

document.addEventListener('DOMContentLoaded', function () {
  // Close Filter Modal from Footer Button
  const closeFilterBtn = document.getElementById('closeFilterBtn');
  const filterModal = document.getElementById('filterModal');

  if (closeFilterBtn && filterModal) {
    closeFilterBtn.addEventListener('click', function () {
      filterModal.style.display = 'none';
    });
  }
});

(function () {
  function initAudioPlayer() {
    if (typeof jQuery === 'undefined') {
      console.log("⏳ Waiting for jQuery...");
      setTimeout(initAudioPlayer, 100);
      return;
    }

    console.log("🚀 Audio Player Initialized inside user_lead.php");

    const audio = document.getElementById("popupAudio");
    const container = document.getElementById("audioPopupPlayer");
    const playPause = document.getElementById("popupPlayPause");
    const progress = document.getElementById("popupProgress");
    const current = document.getElementById("popupCurrent");
    const duration = document.getElementById("popupDuration");
    const closeBtn = document.getElementById("popupClose");

    if (!container || !audio) {
      console.error("❌ Audio player components missing in DOM");
      return;
    }

    function formatTime(seconds) {
      if (!seconds || isNaN(seconds)) return "0:00";
      const min = Math.floor(seconds / 60);
      const sec = Math.floor(seconds % 60);
      return min + ":" + (sec < 10 ? "0" : "") + sec;
    }

    /* ===============================
       CLICK HANDLER (DYNAMIC BUTTON)
    ================================ */
    $(document).on("click", ".play-recording-btn", function (e) {
      e.preventDefault();
      e.stopPropagation();

      const url = $(this).attr("data-url");
      console.log("🎵 Recording button clicked. URL:", url);

      if (!url || url === '' || url === 'null' || url === 'undefined') {
        console.log("⚠️ No recording URL available");
        if (typeof Swal !== 'undefined') {
          Swal.fire({
            icon: 'warning',
            title: 'No Recording',
            text: 'No recording available for this lead.',
            customClass: {
              container: 'my-swal'
            }
          });
        } else {
          alert("No recording available for this lead.");
        }
        return;
      }

      // Show popup
      container.style.display = "block";

      // Load audio
      audio.pause();
      audio.currentTime = 0;
      audio.src = url;
      audio.load();

      audio.play().then(() => {
        playPause.textContent = "⏸";
      }).catch(err => {
        console.error("Audio play error:", err);
        if (err.name === 'NotAllowedError') {
          console.warn("Autoplay blocked. User interaction required.");
        }
      });
    });

    /* ===============================
       PLAY/PAUSE TOGGLE
    ================================ */
    playPause.addEventListener("click", function () {
      if (audio.paused) {
        audio.play();
        playPause.textContent = "⏸";
      } else {
        audio.pause();
        playPause.textContent = "▶";
      }
    });

    /* ===============================
       CLOSE
    ================================ */
    closeBtn.addEventListener("click", function () {
      audio.pause();
      container.style.display = "none";
    });

    /* ===============================
       AUDIO EVENTS
    ================================ */
    audio.addEventListener("loadedmetadata", function () {
      duration.textContent = formatTime(audio.duration);
      progress.max = Math.floor(audio.duration);
    });

    audio.addEventListener("timeupdate", function () {
      progress.value = Math.floor(audio.currentTime);
      current.textContent = formatTime(audio.currentTime);
    });

    progress.addEventListener("input", function () {
      audio.currentTime = progress.value;
    });

    // Close on outside click
    window.addEventListener("click", function (event) {
      if (event.target === container) {
        audio.pause();
        container.style.display = "none";
      }
    });
  }

  // Start initialization
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAudioPlayer);
  } else {
    initAudioPlayer();
  }
})();

console.log('🔍 DEBUG: Checking Overdue Lead System...');

// Check if OverdueLeadSystem exists
setTimeout(() => {
  if (typeof window.OverdueLeadSystem !== 'undefined') {
    console.log('✅ OverdueLeadSystem object found!');
    console.log('Active?', window.OverdueLeadSystem.isActive());

    // Manually trigger to test
    console.log('🔁 Manually triggering refresh...');
    window.OverdueLeadSystem.refresh();
  } else {
    console.error('❌ OverdueLeadSystem object NOT FOUND! JavaScript not loaded properly.');
  }

  // Test API directly
  console.log('📡 Testing API endpoint directly...');
  fetch('update_status.php?get_next_overdue_lead=1')
    .then(r => {
      console.log('Response status:', r.status);
      return r.json();
    })
    .then(data => {
      console.log('API Response:', data);
      if (data.lead) {
        console.log('✅ Lead data received:', data.lead);
      } else {
        console.warn('⚠️ No lead data in response');
      }
    })
    .catch(err => {
      console.error('❌ API Error:', err);
    });
}, 2000);

// ----------------------------------------------------------------------------
// IVR LEAD EDIT LOGIC
// ----------------------------------------------------------------------------

$(document).ready(function () {
  // Escape HTML Helper
  window.escapeHtml = function (unsafe) {
    if (!unsafe) return '';
    return unsafe
      .toString()
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  };

  // Open Modal
  $(document).on('click', '.edit-ivr-lead-btn', function (e) {
    e.stopPropagation();
    e.preventDefault();

    const leadId = $(this).data('id');
    const leadName = $(this).data('name');
    const leadEmail = $(this).data('email');

    $('#editIvrRowId').val(leadId);
    $('#editIvrName').val(leadName);
    $('#editIvrEmail').val(leadEmail);

    $('#ivrNameError, #ivrEmailError').hide();
    $('#editIvrLeadModal').css('display', 'flex');
    $('body').css('overflow', 'hidden');
  });

  // Close Modal
  $('#closeEditIvrModal, #cancelEditIvrModal').on('click', function (e) {
    e.preventDefault();
    $('#editIvrLeadModal').hide();
    $('body').css('overflow', '');
  });

  // Handle form submit
  $('#editIvrLeadForm').on('submit', function (e) {
    e.preventDefault();

    const leadId = $('#editIvrRowId').val();
    const newName = $('#editIvrName').val().trim();
    const newEmail = $('#editIvrEmail').val().trim();

    if (!newName) {
      $('#ivrNameError').text('Name is required').show();
      return;
    }

    const formData = new FormData();
    formData.append('edit_ivr_lead', '1');
    formData.append('lead_id', leadId);
    formData.append('new_name', newName);
    formData.append('new_email', newEmail);

    const $btn = $('#submitEditIvrBtn');
    const originalText = $btn.text();
    $btn.prop('disabled', true).text('Updating...');

    fetch('action.php', {
      method: 'POST',
      body: formData
    })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          $('#editIvrLeadModal').hide();
          $('body').css('overflow', '');

          if (typeof showNotification === 'function') {
            showNotification('Lead updated successfully!', 'success');
          }

          // Refresh DataTable without losing paging
          if ($.fn.DataTable.isDataTable("#leadsTable")) {
            $("#leadsTable").DataTable().ajax.reload(null, false);
          }
        } else {
          if (typeof showNotification === 'function') {
            showNotification(data.message || 'Failed to update lead', 'error');
          } else {
            alert(data.message || 'Failed to update lead');
          }
        }
      })
      .catch(err => {
        console.error('Error updating lead:', err);
        if (typeof showNotification === 'function') {
          showNotification('An error occurred during update.', 'error');
        } else {
          alert('An error occurred during update.');
        }
      })
      .finally(() => {
        $btn.prop('disabled', false).text(originalText);
      });
  });
});

