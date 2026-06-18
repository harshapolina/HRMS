
// Listen for dark mode messages from parent window (when embedded in iframe)
// Set this up EARLY before other code runs - use a named function so we can call it later
function handleDarkModeMessage(event) {
  // Accept messages from any origin (you may want to restrict this in production)
  if (event.data && event.data.type === 'darkMode') {
    // Always update localStorage immediately
    localStorage.setItem('darkMode', event.data.enabled ? 'true' : 'false');

    // Update state if it exists
    if (typeof state !== 'undefined') {
      state.darkMode = event.data.enabled;
    }

    // Apply theme if function exists
    if (typeof applyTheme === 'function') {
      applyTheme();
    } else {
      // If applyTheme doesn't exist yet, directly update the theme attribute
      if (event.data.enabled) {
        document.documentElement.setAttribute('data-theme', 'dark');
      } else {
        document.documentElement.removeAttribute('data-theme');
      }
      // Retry applying full theme after a short delay
      setTimeout(function () {
        if (typeof applyTheme === 'function') {
          applyTheme();
        }
      }, 100);
    }
  }
}

// Set up the listener immediately
window.addEventListener('message', handleDarkModeMessage);

// State management
// Check for darkMode from URL parameter first (for embedded mode), then localStorage
const urlParams = new URLSearchParams(window.location.search);
const darkModeFromUrl = urlParams.get('darkMode') === '1';
const darkModeFromStorage = localStorage.getItem('darkMode') === 'true';
const state = {
  sidebarCollapsed: false,
  darkMode: darkModeFromUrl || darkModeFromStorage, // URL param takes precedence for embedded mode
  currentPage: 'dashboard'
};

// DOM elements
const sidebar = document.getElementById('sidebar');
const mainContent = document.getElementById('mainContent');
const themeToggle = document.getElementById('themeToggle');
const themeIcon = document.getElementById('themeIcon');
const mobileOverlay = document.getElementById('mobileOverlay');
const mobileMenuBtn = document.getElementById('mobileMenuBtn');
const searchBtn = document.getElementById('searchBtn');
const notificationBtn = document.getElementById('notificationBtn');
let notificationPositionHandlerBound = false;
let notificationClickBound = false;

let userInfoPositionHandlerBound = false;
let userInfoClickBound = false;
let userInfoAnchorEl = null;



// User info popup functions
function ensureUserPopupPortaled() {
  const popup = document.getElementById('userInfoPopup');
  if (!popup) return null;

  let portal = document.getElementById('ui-portal');
  if (!portal) {
    portal = document.createElement('div');
    portal.id = 'ui-portal';
    document.body.appendChild(portal);
  }

  if (popup.parentElement !== portal) {
    portal.appendChild(popup);
    popup.setAttribute('data-ported', 'true');
  }
  return popup;
}

function positionUserInfoPopup() {
  const popup = document.getElementById('userInfoPopup');
  const userProfile = userInfoAnchorEl || document.querySelector('.user-profile-sidebar, #sidebarUserProfileBtn');
  if (!popup || !userProfile || !popup.classList.contains('active')) return;

  const rect = userProfile.getBoundingClientRect();
  const gutter = 8;
  const isMobile = window.innerWidth <= 768;
  let width;
  let left;
  let top;

  if (isMobile) {
    popup.style.position = '';
    popup.style.top = '';
    popup.style.left = '';
    popup.style.width = '';
    popup.style.zIndex = '';
    return;
  } else {
    width = 320;
    left = Math.max(8, Math.min(rect.right - width, window.innerWidth - width - 8));
    top = rect.bottom + gutter;

    popup.style.position = 'fixed';
    popup.style.top = `${top}px`;
    popup.style.left = `${left}px`;
    popup.style.width = `${width}px`;
    popup.style.zIndex = '2147483647';
  }
}

function handleUserInfo(e) {
  e && e.stopPropagation();
  const popup = document.getElementById('userInfoPopup');
  const userProfile = (e && e.currentTarget) || userInfoAnchorEl || document.querySelector('.user-profile-sidebar, #sidebarUserProfileBtn');
  if (!popup || !userProfile) return;

  userInfoAnchorEl = userProfile;

  const isActive = popup.classList.contains('active');
  if (isActive) {
    popup.classList.remove('active');
    userProfile.setAttribute('aria-expanded', 'false');
    popup.style.position = '';
    popup.style.top = '';
    popup.style.left = '';
    popup.style.width = '';
    popup.style.zIndex = '';
  } else {
    const portaled = ensureUserPopupPortaled() || popup;
    portaled.classList.add('active');
    userProfile.setAttribute('aria-expanded', 'true');
    positionUserInfoPopup();

    if (!userInfoPositionHandlerBound) {
      const reposition = debounce(() => positionUserInfoPopup(), 50);
      window.addEventListener('resize', reposition);
      window.addEventListener('scroll', reposition, { passive: true, capture: true });
      userInfoPositionHandlerBound = true;
    }
  }
}

// Update the existing click outside handler
document.addEventListener('click', function (e) {


  // User info popup click outside
  const userInfoPopup = document.getElementById('userInfoPopup');
  const userProfileEls = document.querySelectorAll('.user-profile-sidebar, #sidebarUserProfileBtn');
  if (userInfoPopup && userProfileEls.length) {
    if (userInfoPopup.classList.contains('active') &&
      !userInfoPopup.contains(e.target) &&
      !Array.from(userProfileEls).some((el) => el.contains(e.target))) {
      userInfoPopup.classList.remove('active');
      userProfileEls.forEach((el) => el.setAttribute('aria-expanded', 'false'));
      userInfoPopup.style.position = '';
      userInfoPopup.style.top = '';
      userInfoPopup.style.left = '';
      userInfoPopup.style.width = '';
      userInfoPopup.style.zIndex = '';
    }
  }
});

document.addEventListener('DOMContentLoaded', function () {
  // Notification close button
  const notificationCloseBtn = document.getElementById('notificationCloseBtn');
  if (notificationCloseBtn) {
    notificationCloseBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      const popup = document.getElementById('notificationPopup');
      if (popup) {
        popup.classList.remove('active');
        // Reset styles
        popup.style.position = '';
        popup.style.top = '';
        popup.style.left = '';
        popup.style.width = '';
        popup.style.zIndex = '';
      }
    });
  }

  // User info close button
  const userInfoCloseBtn = document.getElementById('userInfoCloseBtn');
  if (userInfoCloseBtn) {
    userInfoCloseBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      const popup = document.getElementById('userInfoPopup');
      if (popup) {
        popup.classList.remove('active');
        // Reset styles
        popup.style.position = '';
        popup.style.top = '';
        popup.style.left = '';
        popup.style.width = '';
        popup.style.zIndex = '';
      }
    });
  }
});


// Update the existing escape key handler
document.addEventListener('keydown', function (e) {
  if (e.key === 'Escape') {
    // Existing notification popup code...
    const notificationPopup = document.getElementById('notificationPopup');
    if (notificationPopup && notificationPopup.classList.contains('active')) {
      notificationPopup.classList.remove('active');
      // ... reset styles
    }

    // User info popup escape
    const userInfoPopup = document.getElementById('userInfoPopup');
    if (userInfoPopup && userInfoPopup.classList.contains('active')) {
      userInfoPopup.classList.remove('active');
      userInfoPopup.style.position = '';
      userInfoPopup.style.top = '';
      userInfoPopup.style.left = '';
      userInfoPopup.style.width = '';
      userInfoPopup.style.zIndex = '';
    }
  }
});

// Initialize the application
function init() {
  setupEventListeners();
  applyTheme();
  setupResponsiveDesign();
  animateStats();
  setupTooltips();
}

// Event listeners
function setupEventListeners() {

  const userProfileSidebar = document.querySelectorAll('.user-profile-sidebar, #sidebarUserProfileBtn');
  if (userProfileSidebar.length && !userInfoClickBound) {
    userProfileSidebar.forEach((el) => el.addEventListener('click', handleUserInfo));
    userInfoClickBound = true;
  }
  // Theme toggle
  if (themeToggle) {
    themeToggle.addEventListener('change', toggleTheme);
  }

  // Mobile menu
  if (mobileMenuBtn) {
    mobileMenuBtn.addEventListener('click', toggleMobileSidebar);
  }
  if (mobileOverlay) {
    mobileOverlay.addEventListener('click', closeMobileSidebar);
  }

  // Header buttons
  if (searchBtn) {
    searchBtn.addEventListener('click', handleSearch);
  }
  if (notificationBtn && !notificationClickBound) {
    notificationBtn.addEventListener('click', handleNotifications);
    notificationClickBound = true;
  }

  // Window resize
  window.addEventListener('resize', handleResize);

  // Keyboard shortcuts
  document.addEventListener('keydown', handleKeyboardShortcuts);
}

// Mobile sidebar functionality
function toggleMobileSidebar() {
  if (sidebar) {
    sidebar.classList.toggle('mobile-open');
  }
  if (mobileOverlay) {
    mobileOverlay.classList.toggle('active');
  }
  document.body.style.overflow = sidebar && sidebar.classList.contains('mobile-open') ? 'hidden' : '';
  updateMobileMenuIcon();
}

function closeMobileSidebar() {
  if (sidebar) {
    sidebar.classList.remove('mobile-open');
  }
  if (mobileOverlay) {
    mobileOverlay.classList.remove('active');
  }
  document.body.style.overflow = '';
  updateMobileMenuIcon();
}

function updateMobileMenuIcon() {
  if (!mobileMenuBtn) return;
  const isOpen = !!(sidebar && sidebar.classList.contains('mobile-open'));
  mobileMenuBtn.innerHTML = isOpen
    ? '<i class="fas fa-times"></i>'
    : '<i class="fas fa-bars"></i>';
  mobileMenuBtn.classList.toggle('is-open', isOpen);
}





// Initialize the application
function init() {
  setupEventListeners();
  applyTheme(); // Apply theme on page load
  setupResponsiveDesign();
  // animateStats(); // Disabled: stats now display instantly without animation
  setupTooltips();
}

// Theme functionality
function toggleTheme() {
  state.darkMode = themeToggle.checked;
  localStorage.setItem('darkMode', state.darkMode); // Save to localStorage
  applyTheme();
}

function applyTheme() {
  if (state.darkMode) {
    document.documentElement.setAttribute('data-theme', 'dark');
    if (themeIcon) {
      themeIcon.className = 'fas fa-sun';
    }
    if (themeToggle) {
      themeToggle.checked = true;
    }
  } else {
    document.documentElement.removeAttribute('data-theme');
    if (themeIcon) {
      themeIcon.className = 'fas fa-moon';
    }
    if (themeToggle) {
      themeToggle.checked = false;
    }
  }

  // Update charts if the function is available
  if (typeof window.updateChartsForTheme === 'function') {
    window.updateChartsForTheme();
  }
}

// Add event listener for the theme toggle link/button
const themeToggleLink = document.getElementById('themeToggleLink');
if (themeToggleLink) {
  themeToggleLink.addEventListener('click', function (e) {
    e.preventDefault();
    if (themeToggle) {
      themeToggle.checked = !themeToggle.checked;
      toggleTheme();
    }
  });
}

// Initialize when DOM is loaded
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => {
    init();
  });
} else {
  init();
}

// Header functionality
function handleSearch() {
  const searchQuery = prompt('Enter search query:');
  if (searchQuery) {
    console.log('Searching for:', searchQuery);
  }
}

// Notification popup handling
function ensurePopupPortaled() {
  const popup = document.getElementById('notificationPopup');
  if (!popup) return null;

  // Create a top-level portal once
  let portal = document.getElementById('ui-portal');
  if (!portal) {
    portal = document.createElement('div');
    portal.id = 'ui-portal';

    document.body.appendChild(portal);
  }

  // Move popup into the portal to avoid clipping/stacking issues
  if (popup.parentElement !== portal) {
    portal.appendChild(popup);
    popup.setAttribute('data-ported', 'true');
  }
  return popup;
}

function positionNotificationPopup() {
  const popup = document.getElementById('notificationPopup');
  const button = document.getElementById('notificationBtn');
  if (!popup || !button || !popup.classList.contains('active')) return;

  const rect = button.getBoundingClientRect();
  const gutter = 8;
  const isMobile = window.innerWidth <= 768;
  let width;
  let left;
  let top;

  if (isMobile) {
    // For mobile screens (≤768px), remove explicit positioning
    popup.style.position = '';
    popup.style.top = '';
    popup.style.left = '';
    popup.style.width = '';
    popup.style.zIndex = '';
    return; // Exit early as we don't need to position it
  } else {
    // For larger screens, maintain the original positioning logic
    width = Math.min(360, window.innerWidth - 24);
    left = Math.max(8, Math.min(rect.right - width, window.innerWidth - width - 8));
    top = rect.bottom + gutter;

    popup.style.position = 'fixed';
    popup.style.top = `${top}px`;
    popup.style.left = `${left}px`;
    popup.style.width = `${width}px`;
    popup.style.zIndex = '2147483647';
  }
}

function handleNotifications(e) {
  e && e.stopPropagation();
  const popup = document.getElementById('notificationPopup');
  const button = document.getElementById('notificationBtn');
  if (!popup || !button) return;

  const isActive = popup.classList.contains('active');
  if (isActive) {
    popup.classList.remove('active');
    button.setAttribute('aria-expanded', 'false');
    // Clear inline positioning so CSS can reset on next open
    popup.style.position = '';
    popup.style.top = '';
    popup.style.left = '';
    popup.style.width = '';
    popup.style.zIndex = '';
  } else {
    const portaled = ensurePopupPortaled() || popup;
    portaled.classList.add('active');
    button.setAttribute('aria-expanded', 'true');
    positionNotificationPopup();

    // Bind resize/scroll handlers once (use capture to catch inner scrollers)
    if (!notificationPositionHandlerBound) {
      const reposition = debounce(() => positionNotificationPopup(), 50);
      window.addEventListener('resize', reposition);
      window.addEventListener('scroll', reposition, { passive: true, capture: true });
      notificationPositionHandlerBound = true;
    }
  }
}

// Responsive design
function setupResponsiveDesign() {
  handleResize();

  // Keep icon state synced even if sidebar class changes from other handlers.
  if (sidebar && mobileMenuBtn && typeof MutationObserver !== 'undefined') {
    const observer = new MutationObserver(() => updateMobileMenuIcon());
    observer.observe(sidebar, { attributes: true, attributeFilter: ['class'] });
  }
}

function handleResize() {
  const isMobile = window.innerWidth <= 1024;

  if (mobileMenuBtn) {
    if (isMobile) {
      mobileMenuBtn.style.display = 'flex';
      updateMobileMenuIcon();
    } else {
      mobileMenuBtn.style.display = 'none';
      closeMobileSidebar();
    }
  }
}

// Keyboard shortcuts
function handleKeyboardShortcuts(e) {
  // Ctrl/Cmd + K: Search
  if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
    e.preventDefault();
    handleSearch();
  }

  // Ctrl/Cmd + D: Toggle dark mode
  if ((e.ctrlKey || e.metaKey) && e.key === 'd') {
    e.preventDefault();
    if (themeToggle) {
      themeToggle.click();
    }
  }

  // Escape: Close mobile sidebar
  if (e.key === 'Escape') {
    closeMobileSidebar();
  }
}



document.addEventListener('DOMContentLoaded', function () {
  const sidebar = document.getElementById('sidebar');
  const mainContent = document.getElementById('mainContent');
  const sidebarToggle = document.getElementById('sidebarToggle');
  const sidebarCloseBtn = document.getElementById('sidebarCloseBtn');
  if (!sidebarToggle) return; // Exit if sidebar toggle doesn't exist
  const toggleIcon = sidebarToggle.querySelector('i');

  // Helper function to update arrow icon
  function updateIcon() {
    if (!sidebar || !toggleIcon) return;
    if (sidebar.classList.contains('collapsed')) {
      toggleIcon.className = 'fas fa-chevron-right'; // collapsed → right arrow
    } else {
      toggleIcon.className = 'fas fa-chevron-left'; // expanded → left arrow
    }
  }

  // Only apply collapse functionality on desktop
  if (sidebar && mainContent && window.innerWidth > 1024) {
    // Restore state from localStorage
    const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';

    if (isCollapsed) {
      sidebar.classList.add('collapsed');
      mainContent.classList.add('expanded');
    } else {
      sidebar.classList.remove('collapsed');
      mainContent.classList.remove('expanded');
    }

    updateIcon(); // ✅ ensure correct icon on load

    // Toggle sidebar collapse
    sidebarToggle.addEventListener('click', function () {
      if (!sidebar || !mainContent) return;
      sidebar.classList.toggle('collapsed');
      mainContent.classList.toggle('expanded');

      // Save state
      localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
      updateIcon(); // ✅ update icon on toggle
    });
  }

  // Close sidebar on mobile
  if (sidebarCloseBtn && sidebar) {
    sidebarCloseBtn.addEventListener('click', function () {
      sidebar.classList.remove('mobile-open');
      document.body.style.overflow = '';
    });
  }

  // Handle window resize
  window.addEventListener('resize', function () {
    if (window.innerWidth <= 1024) {
      sidebar.classList.remove('collapsed');
      mainContent.classList.remove('expanded');
      toggleIcon.className = 'fas fa-chevron-left'; // reset to expanded state
    }
  });
});


// No-op: binding handled in setupEventListeners()

// Close notification when clicking outside
document.addEventListener('click', function (e) {
  const popup = document.getElementById('notificationPopup');
  const button = document.getElementById('notificationBtn');
  if (!popup || !button) return;

  if (!popup.contains(e.target) && !button.contains(e.target)) {
    if (popup.classList.contains('active') || popup.style.display === 'flex' || popup.style.display === 'block') {
      popup.style.display = 'none';
      popup.classList.remove('active');
      button.setAttribute('aria-expanded', 'false');
      popup.style.position = '';
      popup.style.top = '';
      popup.style.left = '';
      popup.style.width = '';
      popup.style.zIndex = '';
    }
  }
});

// Close notification on escape key
document.addEventListener('keydown', function (e) {
  if (e.key === 'Escape') {
    const popup = document.getElementById('notificationPopup');
    if (popup && (popup.classList.contains('active') || popup.style.display === 'flex' || popup.style.display === 'block')) {
      popup.style.display = 'none';
      popup.classList.remove('active');
      popup.style.position = '';
      popup.style.top = '';
      popup.style.left = '';
      popup.style.width = '';
      popup.style.zIndex = '';
    }
  }
});

// Mark all as read functionality
const markAllReadBtn = document.querySelector('.mark-all-read');
if (markAllReadBtn) {
  markAllReadBtn.addEventListener('click', function () {
    document.querySelectorAll('.notification-item.unread').forEach(item => {
      item.classList.remove('unread');
    });

    // Hide notification badge
    const badge = document.querySelector('.notification-badge');
    if (badge) badge.style.display = 'none';
  });
}


// Animations (Disabled to make stats display instantly)
// function animateStats() {
//   const statValues = document.querySelectorAll('.stat-value');
//   
//   statValues.forEach((stat, index) => {
//     const finalValue = stat.textContent;
//     const numericValue = parseInt(finalValue.replace(/[^0-9]/g, ''));
//     
//     if (numericValue) {
//       stat.textContent = '0';
//       
//       setTimeout(() => {
//         animateNumber(stat, 0, numericValue, finalValue, 1000);
//       }, index * 200);
//     }
//   });
// }
// 
// function animateNumber(element, start, end, finalText, duration) {
//   const startTime = performance.now();
//   const isPrice = finalText.includes('$');
//   const suffix = finalText.replace(/[0-9,]/g, '');
//   
//   function update(currentTime) {
//     const elapsed = currentTime - startTime;
//     const progress = Math.min(elapsed / duration, 1);
//     const current = Math.floor(start + (end - start) * easeOutCubic(progress));
//     
//     if (isPrice) {
//       element.textContent = '$' + current.toLocaleString();
//     } else {
//       element.textContent = current.toLocaleString() + suffix.replace(',', '');
//     }
//     
//     if (progress < 1) {
//       requestAnimationFrame(update);
//     } else {
//       element.textContent = finalText;
//     }
//   }
//   
//   requestAnimationFrame(update);
// }
// 
// function easeOutCubic(t) {
//   return 1 - Math.pow(1 - t, 3);
// }

// Tooltips
function setupTooltips() {
  // Tooltips are handled by CSS
}

// Utility functions
function debounce(func, wait) {
  let timeout;
  return function executedFunction(...args) {
    const later = () => {
      clearTimeout(timeout);
      func(...args);
    };
    clearTimeout(timeout);
    timeout = setTimeout(later, wait);
  };
}

// Initialize when DOM is loaded
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => {
    init();
  });
} else {
  init();
}

const sidebarCloseBtn = document.getElementById('sidebarCloseBtn');
if (sidebarCloseBtn) {
  sidebarCloseBtn.addEventListener('click', closeMobileSidebar);
}



function applyFilters() {
  var filterInputs = [{
    id: "filterID",
    columnIndex: 0
  },
  {
    id: "filterBookingDate",
    columnIndex: 1
  },
  {
    id: "filterMonth",
    columnIndex: 2
  },
  {
    id: "filterBuilder",
    columnIndex: 3
  },
  {
    id: "filterProject",
    columnIndex: 4
  },
  {
    id: "filterCustumername",
    columnIndex: 5
  },
  {
    id: "filterContactnumber",
    columnIndex: 6
  },
  {
    id: "filterEmail",
    columnIndex: 7
  },
  {
    id: "filterType",
    columnIndex: 8
  },
  {
    id: "filterUnit",
    columnIndex: 9
  },
  {
    id: "filterSize",
    columnIndex: 10
  },
  {
    id: "filterAgreement",
    columnIndex: 11
  },
  {
    id: "filterCommission",
    columnIndex: 12
  },
  {
    id: "filterTrevenue",
    columnIndex: 13
  },
  {
    id: "filterCashBack",
    columnIndex: 14
  },
  {
    id: "filterActualRevenue",
    columnIndex: 15
  },
  {
    id: "filterStatus",
    columnIndex: 16
  },
  {
    id: "filterReceived",
    columnIndex: 17
  },
  {
    id: "filterSales",
    columnIndex: 18
  },
  ];
  activeFilters = [];
  $("#pagedataaas tr").each(function () {
    var row = $(this);
    var showRow = true;
    filterInputs.forEach(function (inputInfo) {
      var input = $("#" + inputInfo.id);
      var filterValue = input.val().toLowerCase();
      var cellValue = row.find("td:eq(" + inputInfo.columnIndex + ")").text().toLowerCase();
      if (cellValue.indexOf(filterValue) === -1) {
        showRow = false;
        return false;
      }
      if (filterValue.trim() !== "") {
        activeFilters.push(filterValue);
      }
    });
    if (showRow) {
      row.addClass("custom-filtered-row");
    } else {
      row.removeClass("custom-filtered-row");
    }
  });
  var totalTotalRevenue = 0;
  var totalActualRevenue = 0;
  var counterRow = 0;
  $(".custom-filtered-row").each(function () {
    var totalRevenue = parseFloat($(this).find("td:eq(13)").text());
    var actualRevenue = parseFloat($(this).find("td:eq(15)").text());
    if (!isNaN(totalRevenue)) {
      totalTotalRevenue += totalRevenue;
      counterRow += 1;
    }
    if (!isNaN(actualRevenue)) {
      totalActualRevenue += actualRevenue;
    }
  });

  $("#counter").text(counterRow);
  $("#totalTotalRevenue").text(totalTotalRevenue.toLocaleString());
  $("#totalActualRevenue").text(totalActualRevenue.toLocaleString());
  $("#pagedataaas tr").hide();
  applyCustomFilter();
}
applyCustomFilter();

function applyCustomFilter() {
  $(".custom-filtered-row").show();
}
$(".filterable .btn-filter1").click(function () {
  $("#filterModal").modal("show");
});
$("#applyFiltersBtn").click(function () {
  $("#filterModal").modal("hide");
  applyFilters();
});
$("#filterModal").on("hidden.bs.modal", function () {
  $(".filterable .filters input").val("");
  applyFilters();
});
$("#closeFilter").click(function () {
  applyFilters();
  $("#filterModal").modal("hide");
});
$("#cancleFilter").click(function () {
  applyFilters();
  $("#filterModal").modal("hide");
});
$(document).ready(function () {
  const clearFiltersBtn = $("#clearFiltersBtn");
  if (clearFiltersBtn.length) {
    clearFiltersBtn.click(function () {
      $("#filterID, #filterBookingDate, #filterMonth, #filterBuilder, #filterProject, #filterContactnumber, #filterCustumername, #filterEmail, #filterType, #filterUnit, #filterSize, #filterAgreement, #filterCommission, #filterTrevenue, #filterCashBack, #filterActualRevenue, #filterStatus, #filterReceived, #filterSales").val("");
    });
  }
});
$("#clearFiltersBtn").click(function () {
  applyFilters();
  $("#filterModal").modal("hide");
});

document.addEventListener("DOMContentLoaded", function () {
  var sidebar = document.getElementById('rightsidebar');
  function hideSidebar() {
    sidebar.style.display = 'none';
  }
  var sidebarItems = document.querySelectorAll('.pmd-sidebar-li');
  sidebarItems.forEach(function (item) {
    item.addEventListener('click', function () {
      hideSidebar();
    });
  });
});

function combineUnitNumber() {
  const prefix = document.getElementById('unit-prefix').innerText;
  const suffix = document.getElementById('unitno-suffix').value;
  document.getElementById('unitno').value = prefix + suffix;
}

function addNumber() {
  let newNumber = prompt("Enter another contact number:");
  if (newNumber && newNumber.trim() !== '') {
    let input = document.querySelector('[name="cnumber"]');
    input.value = input.value ? input.value + ', ' + newNumber.trim() : newNumber.trim();
  }
}

function addEmail() {
  let newEmail = prompt("Enter another email:");
  if (newEmail && newEmail.trim() !== '') {
    let input = document.querySelector('[name="cemail"]');
    input.value = input.value ? input.value + ', ' + newEmail.trim() : newEmail.trim();
  }
}

function addName() {
  let newName = prompt("Enter another name:");
  if (newName && newName.trim() !== '') {
    let input = document.querySelector('[name="cname"]');
    input.value = input.value ? input.value + ', ' + newName.trim() : newName.trim();
  }
}




// Add resize debouncing
window.addEventListener('resize', debounce(handleResize, 250));



// ===== SEARCHABLE NAME SELECT FUNCTIONALITY =====

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
  return roleMap[rawType] || { role: 'user', badge: 'U', color: '#059669' };
}

class SearchableNameSelect {
  constructor(containerId = 'searchableNameSelect', inputId = 'nameSearchInput', dropdownId = 'nameDropdownList') {
    this.container = document.getElementById(containerId);
    this.input = document.getElementById(inputId);
    this.dropdown = document.getElementById(dropdownId);
    this.arrow = this.container?.querySelector('.searchable-select-arrow');
    this.isOpen = false;
    this.users = [];
    this.selectedValue = '';
    this.selectedText = '';
    this.filteredUsers = [];
    this.displayLimit = 15;
    this.currentCount = 15;

    console.log('SearchableNameSelect initialized:', {
      container: !!this.container,
      input: !!this.input,
      dropdown: !!this.dropdown
    });

    if (this.container && this.input && this.dropdown) {
      this.init();
    } else {
      console.error('SearchableNameSelect: Missing required elements');
    }
  }

  init() {
    // Event listeners
    this.input.addEventListener('click', (e) => this.toggle(e));
    this.input.addEventListener('input', (e) => this.handleSearch(e));
    this.input.addEventListener('keydown', (e) => this.handleKeydown(e));

    // Add scroll listener for lazy loading
    this.dropdown.addEventListener('scroll', (e) => this.handleScroll(e));

    // Close dropdown when clicking outside
    document.addEventListener('click', (e) => {
      if (!this.container.contains(e.target) && !this.dropdown.contains(e.target)) {
        this.close();
      }
    });

    // Reposition dropdown on window resize
    window.addEventListener('resize', () => {
      if (this.isOpen) {
        this.positionDropdown();
      }
    });

    // Load users data
    this.loadUsers();
  }

  async loadUsers() {
    try {
      this.showLoading();

      // Fetch users from the same endpoint that dashboard uses
      const response = await fetch('dashboard_data.php');
      const data = await response.json();

      if (data.status === 'success' && data.assigned_users) {
        this.users = data.assigned_users.map(user => ({
          value: user.tablename || user.user_id,
          text: user.username || user.name,
          type: user.user_type || 'user'
        }));

        this.filteredUsers = [...this.users];
        console.log('Users loaded:', this.users.length);
        this.renderOptions();
      } else {
        console.error('Failed to load users:', data);
        this.showError('Failed to load users');
      }
    } catch (error) {
      console.error('Error loading users:', error);
      this.showError('Error loading users');
    }
  }

  showLoading() {
    this.dropdown.innerHTML = `
            <div class="searchable-select-loading">
                <i class="fas fa-spinner fa-spin"></i> Loading users...
            </div>
        `;
  }

  showError(message) {
    this.dropdown.innerHTML = `
            <div class="searchable-select-no-results">
                ${message}
            </div>
        `;
  }

  handleScroll(e) {
    const { scrollTop, scrollHeight, clientHeight } = e.target;
    // Check if scrolled near the bottom (within 20 pixels)
    if (scrollTop + clientHeight >= scrollHeight - 20) {
      if (this.currentCount < this.filteredUsers.length) {
        const nextCount = Math.min(this.currentCount + this.displayLimit, this.filteredUsers.length);
        this.renderOptionsAppend(this.currentCount, nextCount);
        this.currentCount = nextCount;
      }
    }
  }

  renderOptions() {
    this.currentCount = this.displayLimit;
    if (this.filteredUsers.length === 0) {
      this.dropdown.innerHTML = `
                <div class="searchable-select-no-results">
                    No users found
                </div>
            `;
      return;
    }

    const optionsToRender = this.filteredUsers.slice(0, Math.min(this.currentCount, this.filteredUsers.length));
    this.dropdown.innerHTML = this.generateOptionsHTML(optionsToRender);
    this.attachOptionListeners(this.dropdown);
    this.dropdown.scrollTop = 0; // Reset scroll on new search/render
  }

  renderOptionsAppend(start, end) {
    const optionsToRender = this.filteredUsers.slice(start, end);
    if (optionsToRender.length === 0) return;

    this.dropdown.insertAdjacentHTML('beforeend', this.generateOptionsHTML(optionsToRender));
    this.attachOptionListeners(this.dropdown);
  }

  generateOptionsHTML(usersArray) {
    return usersArray.map(user => {
      // Use unified role badge function
      const roleBadge = getRoleBadge(user.type);

      return `
                <div class="searchable-select-option ${user.value === this.selectedValue ? 'selected' : ''}" 
                     data-value="${user.value}">
                    <span>${user.text}</span>
                    <span class="user-badge" style="
                        background: ${roleBadge.color};
                        color: white;
                        padding: 2px 6px;
                        border-radius: 4px;
                        font-size: 10px;
                        font-weight: bold;
                    " title="${roleBadge.role.toUpperCase()}">${roleBadge.badge}</span>
                </div>
            `;
    }).join('');
  }

  attachOptionListeners(container) {
    // Add click listeners ONLY to options without the 'listener-attached' class
    container.querySelectorAll('.searchable-select-option:not(.listener-attached)').forEach(option => {
      option.classList.add('listener-attached');
      option.addEventListener('click', (e) => {
        e.stopPropagation();
        this.selectOption(option.dataset.value);
      });
    });
  }

  handleSearch(e) {
    const searchTerm = e.target.value.toLowerCase();

    if (searchTerm === '') {
      this.filteredUsers = [...this.users];
    } else {
      this.filteredUsers = this.users.filter(user =>
        user.text.toLowerCase().includes(searchTerm) ||
        user.type.toLowerCase().includes(searchTerm)
      );
    }

    this.renderOptions();

    if (!this.isOpen) {
      this.open();
    }
  }

  handleKeydown(e) {
    if (e.key === 'Enter') {
      e.preventDefault();
      if (this.filteredUsers.length === 1) {
        this.selectOption(this.filteredUsers[0].value);
      }
    } else if (e.key === 'Escape') {
      this.close();
      this.input.blur();
    } else if (e.key === 'ArrowDown') {
      e.preventDefault();
      if (!this.isOpen) {
        this.open();
      }
      // Focus first option (could be enhanced with keyboard navigation)
    }
  }

  selectOption(value) {
    const user = this.users.find(u => u.value === value);
    if (user) {
      this.selectedValue = value;
      this.selectedText = user.text;
      this.input.value = user.text;
      this.input.setAttribute('data-selected-value', value);

      // Trigger change event for dashboard integration
      const changeEvent = new CustomEvent('nameSelectChange', {
        detail: { value: value, text: user.text, user: user }
      });
      this.container.dispatchEvent(changeEvent);

      // Also trigger legacy events for compatibility
      if (typeof handleNamesSelectChange === 'function') {
        // Set a temporary select element for compatibility
        const tempSelect = document.createElement('select');
        tempSelect.value = value;
        tempSelect.id = 'namesSelect';
        document.body.appendChild(tempSelect);

        handleNamesSelectChange();

        document.body.removeChild(tempSelect);
      }
    }

    this.close();
  }

  toggle(e) {
    e.stopPropagation();
    console.log('Dropdown toggle called, isOpen:', this.isOpen);
    if (this.isOpen) {
      this.close();
    } else {
      this.open();
    }
  }

  open() {
    console.log('Opening dropdown...');
    this.isOpen = true;
    this.container.classList.add('open');
    this.input.removeAttribute('readonly');
    this.input.focus();

    // Ensure dropdown is visible
    this.dropdown.style.display = 'block';
    console.log('Dropdown display set to block');

    // Position dropdown
    this.positionDropdown();
  }

  close() {
    console.log('Closing dropdown...');
    this.isOpen = false;
    this.container.classList.remove('open');
    this.input.setAttribute('readonly', 'true');

    // Hide dropdown
    this.dropdown.style.display = 'none';
    this.dropdown.style.visibility = 'hidden';

    // Reset positioning
    if (this.dropdown.getAttribute('data-ported') === 'true') {
      this.dropdown.style.position = '';
      this.dropdown.style.top = '';
      this.dropdown.style.left = '';
      this.dropdown.style.width = '';
      this.dropdown.style.zIndex = '';
    }

    // Reset input if no valid selection
    if (!this.selectedValue && this.input.value) {
      this.input.value = this.selectedText || '';
    }
  }

  positionDropdown() {
    // Use portal approach to avoid z-index issues
    this.ensureDropdownPortaled();

    // Position dropdown relative to the input
    const rect = this.container.getBoundingClientRect();
    const dropdownHeight = 200; // max-height from CSS
    const spaceBelow = window.innerHeight - rect.bottom;

    // Ensure dropdown is visible and styled
    this.dropdown.style.display = 'block';
    this.dropdown.style.visibility = 'visible';

    if (spaceBelow < dropdownHeight && rect.top > dropdownHeight) {
      // Show dropdown above if there's more space
      this.dropdown.style.position = 'fixed';
      this.dropdown.style.top = `${rect.top - Math.min(dropdownHeight, rect.top - 8)}px`;
      this.dropdown.style.left = `${rect.left}px`;
      this.dropdown.style.width = `${rect.width}px`;
      this.dropdown.style.borderRadius = '12px 12px 0 0';
    } else {
      // Show dropdown below (default)
      this.dropdown.style.position = 'fixed';
      this.dropdown.style.top = `${rect.bottom}px`;
      this.dropdown.style.left = `${rect.left}px`;
      this.dropdown.style.width = `${rect.width}px`;
      this.dropdown.style.borderRadius = '0 0 12px 12px';
    }

    this.dropdown.style.zIndex = '2147483647'; // Highest possible z-index
    console.log('Dropdown positioned:', {
      display: this.dropdown.style.display,
      position: this.dropdown.style.position,
      top: this.dropdown.style.top,
      left: this.dropdown.style.left,
      width: this.dropdown.style.width,
      zIndex: this.dropdown.style.zIndex
    });
  }

  ensureDropdownPortaled() {
    if (!this.dropdown || this.dropdown.getAttribute('data-ported') === 'true') return;

    // Create a top-level portal
    let portal = document.getElementById('ui-portal');
    if (!portal) {
      portal = document.createElement('div');
      portal.id = 'ui-portal';
      document.body.appendChild(portal);
    }

    // Move dropdown to portal to avoid z-index issues
    portal.appendChild(this.dropdown);
    this.dropdown.setAttribute('data-ported', 'true');
    console.log('Dropdown portaled successfully');
  }

  // Public methods for external integration
  getValue() {
    return this.selectedValue;
  }

  getSelectedUser() {
    return this.users.find(u => u.value === this.selectedValue);
  }

  setValue(value) {
    const user = this.users.find(u => u.value === value);
    if (user) {
      this.selectOption(value);
    }
  }

  clear() {
    this.selectedValue = '';
    this.selectedText = '';
    this.input.value = '';
    this.input.removeAttribute('data-selected-value');
    this.renderOptions();
  }

  refresh() {
    this.loadUsers();
  }
}

// Initialize searchable name select
let searchableNameSelect = null;

// Initialize when DOM is ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initSearchableNameSelect);
} else {
  initSearchableNameSelect();
}

function initSearchableNameSelect() {
  // Initialize header searchable select (desktop only)
  const headerContainer = document.getElementById('searchableNameSelect');
  // Initialize mobile searchable select (mobile only)
  const mobileContainer = document.getElementById('searchableNameSelectMobile');

  let headerSelect = null;
  let mobileSelect = null;

  // Initialize header searchable select
  if (headerContainer) {
    headerSelect = new SearchableNameSelect('searchableNameSelect', 'nameSearchInput', 'nameDropdownList');
    console.log('Header searchable select initialized');
  }

  // Initialize mobile searchable select
  if (mobileContainer) {
    mobileSelect = new SearchableNameSelect('searchableNameSelectMobile', 'nameSearchInputMobile', 'nameDropdownListMobile');
    console.log('Mobile searchable select initialized');
  }

  // Set the active instance based on screen width
  const updateActiveInstance = () => {
    if (window.innerWidth > 1249) {
      searchableNameSelect = headerSelect;
    } else {
      searchableNameSelect = mobileSelect;
    }
  };

  // Set initial active instance
  updateActiveInstance();

  // Update on resize
  window.addEventListener('resize', updateActiveInstance);

  // Make it globally available for dashboard integration
  window.searchableNameSelect = searchableNameSelect;
  window.headerSelect = headerSelect;
  window.mobileSelect = mobileSelect;

  // Create compatibility layer for existing dashboard code
  window.getSelectedUserValue = () => {
    const activeSelect = window.innerWidth > 1249 ? headerSelect : mobileSelect;
    return activeSelect?.getValue() || '';
  };

  window.getSelectedUserData = () => {
    const activeSelect = window.innerWidth > 1249 ? headerSelect : mobileSelect;
    return activeSelect?.getSelectedUser() || null;
  };
}

// Custom Month/Year Dropdown Functionality with Portal
document.addEventListener('DOMContentLoaded', function () {
  const monthDropdown = document.getElementById('monthDropdown');
  const monthSelected = document.getElementById('monthSelected');
  const monthOptions = document.getElementById('monthOptions');
  const monthHiddenInput = document.getElementById('monthSelect');

  const yearDropdown = document.getElementById('yearDropdown');
  const yearSelected = document.getElementById('yearSelected');
  const yearOptions = document.getElementById('yearOptions');
  const yearHiddenInput = document.getElementById('yearSelect');

  // Portal the dropdown options to body to avoid z-index issues
  function ensureDropdownPortaled(optionsElement) {
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

  // Position dropdown relative to the button
  function positionDropdown(buttonElement, optionsElement) {
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

  if (monthDropdown && monthSelected && monthOptions) {
    // Portal the options
    ensureDropdownPortaled(monthOptions);

    // Toggle month dropdown
    monthSelected.addEventListener('click', function (e) {
      e.stopPropagation();
      const wasOpen = monthDropdown.classList.contains('open');

      // Close year dropdown
      if (yearDropdown) {
        yearDropdown.classList.remove('open');
        if (yearOptions) yearOptions.style.display = 'none';
      }

      // Toggle month dropdown
      if (wasOpen) {
        monthDropdown.classList.remove('open');
        monthOptions.style.display = 'none';
      } else {
        monthDropdown.classList.add('open');
        monthOptions.style.display = 'block';
        positionDropdown(monthSelected, monthOptions);
      }
    });

    // Handle month option click
    monthOptions.querySelectorAll('.custom-dropdown-option').forEach(option => {
      option.addEventListener('click', function () {
        const value = this.getAttribute('data-value');
        const text = this.textContent.trim();

        monthSelected.innerHTML = text + ' <i class="fas fa-chevron-down"></i>';
        monthHiddenInput.value = value;

        monthOptions.querySelectorAll('.custom-dropdown-option').forEach(opt => {
          opt.classList.remove('selected');
        });
        this.classList.add('selected');

        monthDropdown.classList.remove('open');
        monthOptions.style.display = 'none';
        monthHiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
      });
    });
  }

  if (yearDropdown && yearSelected && yearOptions) {
    // Portal the options
    ensureDropdownPortaled(yearOptions);

    // Toggle year dropdown
    yearSelected.addEventListener('click', function (e) {
      e.stopPropagation();
      const wasOpen = yearDropdown.classList.contains('open');

      // Close month dropdown
      if (monthDropdown) {
        monthDropdown.classList.remove('open');
        if (monthOptions) monthOptions.style.display = 'none';
      }

      // Toggle year dropdown
      if (wasOpen) {
        yearDropdown.classList.remove('open');
        yearOptions.style.display = 'none';
      } else {
        yearDropdown.classList.add('open');
        yearOptions.style.display = 'block';
        positionDropdown(yearSelected, yearOptions);
      }
    });

    // Handle year option click
    yearOptions.querySelectorAll('.custom-dropdown-option').forEach(option => {
      option.addEventListener('click', function () {
        const value = this.getAttribute('data-value');
        const text = this.textContent.trim();

        yearSelected.innerHTML = text + ' <i class="fas fa-chevron-down"></i>';
        yearHiddenInput.value = value;

        yearOptions.querySelectorAll('.custom-dropdown-option').forEach(opt => {
          opt.classList.remove('selected');
        });
        this.classList.add('selected');

        yearDropdown.classList.remove('open');
        yearOptions.style.display = 'none';
        yearHiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
      });
    });
  }

  // Close dropdowns when clicking outside
  document.addEventListener('click', function (e) {
    if (monthDropdown && monthOptions && !monthDropdown.contains(e.target) && !monthOptions.contains(e.target)) {
      monthDropdown.classList.remove('open');
      monthOptions.style.display = 'none';
    }
    if (yearDropdown && yearOptions && !yearDropdown.contains(e.target) && !yearOptions.contains(e.target)) {
      yearDropdown.classList.remove('open');
      yearOptions.style.display = 'none';
    }
  });

  // Reposition on scroll and resize
  const repositionDropdowns = () => {
    if (monthDropdown?.classList.contains('open')) {
      positionDropdown(monthSelected, monthOptions);
    }
    if (yearDropdown?.classList.contains('open')) {
      positionDropdown(yearSelected, yearOptions);
    }
  };

  window.addEventListener('scroll', repositionDropdowns, true);
  window.addEventListener('resize', repositionDropdowns);
});

// UPM Month Dropdown Functionality
document.addEventListener('DOMContentLoaded', function () {
  const upmMonthDropdown = document.getElementById('upmMonthDropdown');
  const upmMonthSelected = document.getElementById('upmMonthSelected');
  const upmMonthOptions = document.getElementById('upmMonthOptions');
  const upmMonthHiddenInput = document.getElementById('upm-month-select');

  if (!upmMonthDropdown || !upmMonthSelected || !upmMonthOptions || !upmMonthHiddenInput) return;

  // Portal function
  function ensureUPMDropdownPortaled(optionsElement) {
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
  function positionUPMDropdown(buttonElement, optionsElement) {
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
  ensureUPMDropdownPortaled(upmMonthOptions);

  // Toggle dropdown
  upmMonthSelected.addEventListener('click', function (e) {
    e.stopPropagation();
    const wasOpen = upmMonthDropdown.classList.contains('open');

    if (wasOpen) {
      upmMonthDropdown.classList.remove('open');
      upmMonthOptions.style.display = 'none';
    } else {
      upmMonthDropdown.classList.add('open');
      upmMonthOptions.style.display = 'block';
      positionUPMDropdown(upmMonthSelected, upmMonthOptions);
    }
  });

  // Handle option click
  upmMonthOptions.querySelectorAll('.custom-dropdown-option').forEach(option => {
    option.addEventListener('click', function () {
      const value = this.getAttribute('data-value');
      const text = this.textContent.trim();

      upmMonthSelected.innerHTML = text + ' <i class="fas fa-chevron-down"></i>';
      upmMonthHiddenInput.value = value;

      upmMonthOptions.querySelectorAll('.custom-dropdown-option').forEach(opt => {
        opt.classList.remove('selected');
      });
      this.classList.add('selected');

      upmMonthDropdown.classList.remove('open');
      upmMonthOptions.style.display = 'none';
      upmMonthHiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
    });
  });

  // Close dropdown when clicking outside
  document.addEventListener('click', function (e) {
    if (upmMonthDropdown && upmMonthOptions &&
      !upmMonthDropdown.contains(e.target) && !upmMonthOptions.contains(e.target)) {
      upmMonthDropdown.classList.remove('open');
      upmMonthOptions.style.display = 'none';
    }
  });

  // Reposition on scroll and resize
  const repositionUPMDropdown = () => {
    if (upmMonthDropdown?.classList.contains('open')) {
      positionUPMDropdown(upmMonthSelected, upmMonthOptions);
    }
  };

  window.addEventListener('scroll', repositionUPMDropdown, true);
  window.addEventListener('resize', repositionUPMDropdown);
});
