/* SweetAlert must stack above letter editor (100100) and signature (100110) modals */
(function ensureSwalAboveLetterModals() {
    if (document.getElementById('hr-swal-z-fix')) return;
    const style = document.createElement('style');
    style.id = 'hr-swal-z-fix';
    style.textContent = '.swal2-container{z-index:100200!important;}';
    (document.head || document.documentElement).appendChild(style);
})();

// Disable Bootstrap Modal Focus Trap globally to allow typing in nested modals and editors
if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
    if (bootstrap.Modal.prototype._initializeFocusTrap) {
        bootstrap.Modal.prototype._initializeFocusTrap = function () {
            return { activate: function () { }, deactivate: function () { } };
        };
    }
    if (bootstrap.Modal.prototype._enforceFocus) {
        bootstrap.Modal.prototype._enforceFocus = function () { };
    }
}

var filtersApplied = false;
var activeCardFilter = null;

function toggleCardFilter(filterValue, cardElement) {
    const cardActive = document.querySelector('.summary-section .stat-card-headcount');
    const cardInactive = document.querySelector('.summary-section .stat-card-absent');
    const cardAssigned = document.querySelector('.summary-section .stat-card-present');
    
    // Clear existing active class
    [cardActive, cardInactive, cardAssigned].forEach(c => c?.classList.remove('active-filter'));
    
    if (filterValue === null) {
        // If clicking "Assigned", we set to null (show all)
        activeCardFilter = null;
    } else {
        // If clicking Active/Inactive
        if (activeCardFilter === filterValue) {
            // Already active, so toggle off -> show all
            activeCardFilter = null;
        } else {
            activeCardFilter = filterValue;
            cardElement.classList.add('active-filter');
        }
    }
    applyFilters();
}

function resetCardFiltersUI() {
    activeCardFilter = null;
    const cardActive = document.querySelector('.summary-section .stat-card-headcount');
    const cardInactive = document.querySelector('.summary-section .stat-card-absent');
    const cardAssigned = document.querySelector('.summary-section .stat-card-present');
    [cardActive, cardInactive, cardAssigned].forEach(c => c?.classList.remove('active-filter'));
}

document.addEventListener('DOMContentLoaded', function () {
    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        if (bootstrap.Modal.prototype._initializeFocusTrap) {
            bootstrap.Modal.prototype._initializeFocusTrap = function () {
                return { activate: function () { }, deactivate: function () { } };
            };
        }
        if (bootstrap.Modal.prototype._enforceFocus) {
            bootstrap.Modal.prototype._enforceFocus = function () { };
        }
    }

    const menuBar = document.querySelector('.sidebarbutt');
    const sideBar = document.querySelector('.sidebar');
    function toggleleftsidebar() {
        if (sideBar) sideBar.classList.toggle('close');
    }
    menuBar?.addEventListener('click', toggleleftsidebar);
    function setupRightSidebarControls() {
        const rightsidebar = document.querySelector('#rightsidebar');
        const togglerightsidebar = document.querySelector('#togglerightsidebar');
        const closebtnbar = document.querySelector('#close-btn');
        if (togglerightsidebar) {
            togglerightsidebar.addEventListener('click', function () {
                if (rightsidebar) rightsidebar.style.display = 'block';
            });
        }
        if (closebtnbar) {
            closebtnbar.addEventListener('click', function () {
                if (rightsidebar) rightsidebar.style.display = 'none';
            });
        }
    }
    setupRightSidebarControls();

    // Setup summary card filters listeners
    const cardActive = document.querySelector('.summary-section .stat-card-headcount');
    const cardInactive = document.querySelector('.summary-section .stat-card-absent');
    const cardAssigned = document.querySelector('.summary-section .stat-card-present');
    
    if (cardActive) {
        cardActive.addEventListener('click', function () {
            toggleCardFilter('active', cardActive);
        });
    }
    if (cardInactive) {
        cardInactive.addEventListener('click', function () {
            toggleCardFilter('inactive', cardInactive);
        });
    }
    if (cardAssigned) {
        cardAssigned.addEventListener('click', function () {
            toggleCardFilter(null, cardAssigned);
        });
    }
});
var originalDisplay = {};
function filterOptions() {
    var input, filter, ul, li, a, i, txtValue;
    input = document.getElementById('searchInput');
    if (!input) return;
    filter = input.value.toUpperCase();
    ul = document.getElementById('optionul');
    if (!ul) return;
    li = ul.getElementsByClassName('optionli');
    for (i = 0; i < li.length; i++) {
        a = li[i].getElementsByTagName('a')[0];
        txtValue = a.textContent || a.innerText;
        if (txtValue.toUpperCase().indexOf(filter) > -1) {
            li[i].style.display = 'block';
        } else {
            li[i].style.display = 'none';
        }
    }
    if (filter === '') {
        for (i = 0; i < li.length; i++) {
            li[i].style.display = 'none';
        }
    }
}
$(document).ready(function () {
    $('#scroll-left').on('click', function () {
        $('#example_wrapper .dt-scroll-body').scrollLeft($('#example_wrapper .dt-scroll-body').scrollLeft() - 200);
    });
    $('#scroll-right').on('click', function () {
        $('#example_wrapper .dt-scroll-body').scrollLeft($('#example_wrapper .dt-scroll-body').scrollLeft() + 200);
    });
});
/* ================= EMAIL TRUNCATION ================= */
function truncateEmail(email) {
    if (!email) return "";
    const maxLength = window.innerWidth <= 768 ? 12 : 18;
    if (email.length <= maxLength) return email;
    return email.substring(0, maxLength) + "...";
}
/* ================= USERS TABLE DATA LOADER ================= */
window.usersListMeta = { total: 0, page: 1, limit: 10, total_pages: 1, summary: {} };
window.currentUsersPage = 1;

function buildUsersFetchParams(pageOverride) {
    const params = new URLSearchParams();
    const page = pageOverride || window.currentUsersPage || 1;
    const limit = parseInt(document.getElementById("users-limit")?.value, 10) || 10;
    params.set("page", String(page));
    params.set("limit", String(limit));

    const searchValue = document.getElementById("tableSearchInput")?.value.trim() || "";
    if (searchValue) {
        params.set("search", searchValue);
    }
    if (activeCardFilter) {
        params.set("card_filter", activeCardFilter);
    }

    Object.entries(usrdd_selected || {}).forEach(([field, values]) => {
        if (Array.isArray(values) && values.length > 0) {
            params.set("f_" + field, values.join(","));
        }
    });

    document.querySelectorAll("#filterModal input.usr-custom-dropdown-input").forEach((input) => {
        const value = input.value.trim();
        if (value) {
            params.set(input.id, value);
        }
    });

    return params;
}

function fetchUsersList(page, attempt = 1) {
    const maxAttempts = 3;
    const retryDelayMs = 400 * attempt;
    const params = buildUsersFetchParams(page);
    return fetch("fetch_users.php?" + params.toString(), { credentials: "same-origin", cache: "no-store" })
        .then(async (res) => {
            const text = await res.text();
            let data = null;
            try {
                data = text ? JSON.parse(text) : null;
            } catch (_) {
                data = null;
            }
            if (!res.ok) {
                const serverMsg = data && data.error ? data.error : null;
                const err = new Error(serverMsg || ("HTTP " + res.status));
                if (attempt < maxAttempts && [500, 502, 503, 504].includes(res.status)) {
                    await new Promise((r) => setTimeout(r, retryDelayMs));
                    return fetchUsersList(page, attempt + 1);
                }
                throw err;
            }
            if (data && data.error) {
                throw new Error(data.error);
            }
            return data;
        });
}

function buildUserTableRows(users) {
    let rows = "";
    users.forEach((user) => {
        rows += `
                <tr class="user-data-row">
                    <td class="checkbox-colu"><input type="checkbox"></td>
                    <td>${user.id ?? ""}</td>
                    <td>${user.is_active == 1 ? "Active" : "Inactive"}</td>
                    <td><a href="javascript:void(0)" onclick="openUserProfileDrawer(${user.id})" style="text-decoration: none; color: inherit; font-weight: 600;">${user.username ?? ""}</a></td>
                    <td>${truncateEmail(user.useremail)}</td>
                    <td>${user.phonenumber ?? ""}</td>
                    <td class="password-cell">
                        <span class="password-mask">••••••••</span>
                    </td>
                    <td>${user.salary ?? ""}</td>
                    <td>${user.doj ?? ""}</td>
                    <td>${user.dob ?? ""}</td>
                    <td>${user.uniqueid ?? ""}</td>
                    <td>${user.employee_id ?? ""}</td>
                    <td>${user.first_amount ?? ""}</td>
                    <td>${user.second_amount ?? ""}</td>
                    <td>${user.third_amount ?? ""}</td>
                    <td>${user.fourth_amount ?? ""}</td>
                    <td>${user.fifth_amount ?? ""}</td>
                    <td>${user.sixth_amount ?? ""}</td>
                    <td>${user.project_name ?? ""}</td>
                    <td>${user.project_type ?? ""}</td>
                    <td>${user.city ?? ""}</td>
                    <td>${user.user_type ?? ""}</td>
                    <td>${user.assign_user ?? ""}</td>
                    <td>${user.created_at ?? ""}</td>
                    <td>${user.inactive_at ?? ""}</td>
                    <td class="user-action-cell">
                        <div class="action-icons">
                            <a href="#" id="${user.id}" class="editLink action-icon edit"><i class="bi bi-pencil-square"></i></a>
                            <a href="#" id="${user.id}" class="deleteLink action-icon delete"><i class="bi bi-trash"></i></a>
                            ${user.is_active == 0 ? `<a href="#" onclick="openFnfModal(${user.id})" class="action-icon fnf" title="Full & Final Settlement"><i class="bi bi-file-earmark-check"></i></a>` : ""}
                        </div>
                        <button class="user-expand-btn" onclick="toggleUserExpansion(this)" title="Show Details"><i class="bi bi-chevron-right"></i></button>
                    </td>
                </tr>
                <tr class="user-expand-row" style="display:none;">
                    <td colspan="26">
                        <div class="user-expand-content">
                            <div class="user-expand-grid">
                                <div class="user-expand-item"><span class="user-expand-label">Email</span><span class="user-expand-value">${truncateEmail(user.useremail)}</span></div>
                                <div class="user-expand-item"><span class="user-expand-label">Contact No</span><span class="user-expand-value">${user.phonenumber ?? ""}</span></div>
                                <div class="user-expand-item"><span class="user-expand-label">Password</span><span class="user-expand-value">Open profile to view</span></div>
                                <div class="user-expand-item"><span class="user-expand-label">Monthly CTC</span><span class="user-expand-value">${user.salary ?? "—"}</span></div>
                                <div class="user-expand-item"><span class="user-expand-label">Date of Joining</span><span class="user-expand-value">${user.doj ?? ""}</span></div>
                                <div class="user-expand-item"><span class="user-expand-label">Date of Birth</span><span class="user-expand-value">${user.dob ?? "—"}</span></div>
                                <div class="user-expand-item"><span class="user-expand-label">Unique ID</span><span class="user-expand-value">${user.uniqueid ?? "—"}</span></div>
                                <div class="user-expand-item"><span class="user-expand-label">Employee ID</span><span class="user-expand-value">${user.employee_id ?? "—"}</span></div>
                                <div class="user-expand-item"><span class="user-expand-label">1st Amount</span><span class="user-expand-value">${user.first_amount ?? "—"}</span></div>
                                <div class="user-expand-item"><span class="user-expand-label">2nd Amount</span><span class="user-expand-value">${user.second_amount ?? "—"}</span></div>
                                <div class="user-expand-item"><span class="user-expand-label">3rd Amount</span><span class="user-expand-value">${user.third_amount ?? "—"}</span></div>
                                <div class="user-expand-item"><span class="user-expand-label">4th Amount</span><span class="user-expand-value">${user.fourth_amount ?? "—"}</span></div>
                                <div class="user-expand-item"><span class="user-expand-label">5th Amount</span><span class="user-expand-value">${user.fifth_amount ?? "—"}</span></div>
                                <div class="user-expand-item"><span class="user-expand-label">6th Amount</span><span class="user-expand-value">${user.sixth_amount ?? "—"}</span></div>
                                <div class="user-expand-item"><span class="user-expand-label">Project Name</span><span class="user-expand-value">${user.project_name ?? "—"}</span></div>
                                <div class="user-expand-item"><span class="user-expand-label">Project Type</span><span class="user-expand-value">${user.project_type ?? "—"}</span></div>
                                <div class="user-expand-item"><span class="user-expand-label">City</span><span class="user-expand-value">${user.city ?? "—"}</span></div>
                                <div class="user-expand-item"><span class="user-expand-label">Role Type</span><span class="user-expand-value">${user.user_type ?? "—"}</span></div>
                                <div class="user-expand-item"><span class="user-expand-label">Assign User</span><span class="user-expand-value">${user.assign_user ?? "—"}</span></div>
                                <div class="user-expand-item"><span class="user-expand-label">Created At</span><span class="user-expand-value">${user.created_at ?? "—"}</span></div>
                                <div class="user-expand-item"><span class="user-expand-label">Inactive At</span><span class="user-expand-value">${user.inactive_at ?? "—"}</span></div>
                                <div class="user-expand-actions">
                                     <button id="${user.id}" class="editLink expand-action-btn edit">Edit</button>
                                     ${user.is_active == 0 ? `<button onclick="openFnfModal(${user.id})" class="expand-action-btn fnf">FNF Settlement</button>` : ""}
                                     <button id="${user.id}" class="deleteLink expand-action-btn delete">Delete</button>
                                </div>
                            </div>
                        </div>
                    </td>
                </tr>`;
    });
    return rows;
}

function attachUserRowListeners() {
    document.querySelectorAll("#incentiveuser tr.user-data-row").forEach((row) => {
        row.addEventListener("click", function (e) {
            if (e.target.closest("button") || e.target.closest("input") || e.target.closest(".password-cell") || e.target.closest("a")) {
                return;
            }
            if (window.innerWidth <= 1024) {
                const expandBtn = row.querySelector(".user-expand-btn");
                if (expandBtn) expandBtn.click();
            } else {
                const userId = row.cells[1]?.innerText.trim();
                if (userId && typeof openUserProfileDrawer === "function") {
                    openUserProfileDrawer(userId);
                }
            }
        });
    });
}

function renderUsersTable(users) {
    const tableBody = document.getElementById("incentiveuser");
    if (!tableBody) return;
    if (!users.length) {
        tableBody.innerHTML = `<tr><td colspan="26" style="text-align:center; padding: 20px;">No users found.</td></tr>`;
        return;
    }
    tableBody.innerHTML = buildUserTableRows(users);
    attachUserRowListeners();
    if (typeof applyDefaults === "function") applyDefaults();
}

function updateUsersSummaryFromMeta() {
    const summary = window.usersListMeta.summary || {};
    const elActive = document.getElementById("activeusers");
    if (elActive) elActive.innerText = summary.active ?? 0;
    const elInactive = document.getElementById("deactiveusers");
    if (elInactive) elInactive.innerText = summary.inactive ?? 0;
    const elAssigned = document.getElementById("assignednuser");
    if (elAssigned) elAssigned.innerText = summary.assigned ?? 0;
    const elTotalSal = document.getElementById("totalsalary");
    if (elTotalSal) elTotalSal.innerText = Number(summary.total_salary || 0).toLocaleString();
}

window.loadUsers = function loadUsers(page) {
    const tableBody = document.getElementById("incentiveuser");
    if (!tableBody) return Promise.resolve();

    if (page !== undefined) {
        window.currentUsersPage = page;
    }

    tableBody.innerHTML = `<tr><td colspan="26" style="text-align:center; padding: 20px;">Loading users...</td></tr>`;

    return fetchUsersList(window.currentUsersPage)
        .then((payload) => {
            if (!payload || !Array.isArray(payload.data)) {
                throw new Error("Invalid data format received from server.");
            }

            window.usersListMeta = {
                total: payload.total || 0,
                page: payload.page || 1,
                limit: payload.limit || 10,
                total_pages: payload.total_pages || 1,
                summary: payload.summary || {},
            };
            window.currentUsersPage = window.usersListMeta.page;

            renderUsersTable(payload.data);
            updateUsersPagination();
            updateUsersSummaryFromMeta();
            checkIfFiltersActive();
        })
        .catch((err) => {
            console.error("Users Fetch Error:", err);
            const detail = err && err.message ? err.message : "Unknown error";
            tableBody.innerHTML = `<tr><td colspan="26" style="text-align:center; color:#d9534f; padding:20px; font-weight:bold;">
                Data Failed to Load. ${detail}<br>
                <small style="color:#666; font-weight:normal;">Try refreshing the page. If this keeps happening, check fetch_users.php on the server (F12 → Console).</small>
            </td></tr>`;
        });
};

document.addEventListener("DOMContentLoaded", function () {
    const tableBody = document.getElementById("incentiveuser");
    if (!tableBody) return;
    loadUsers(1);
});
/* ================= USER ROW EXPANSION ================= */
window.toggleUserExpansion = function (btn) {
    const row = btn.closest("tr");
    const nextRow = row.nextElementSibling;
    if (nextRow && nextRow.classList.contains("user-expand-row")) {
        const isVisible = nextRow.style.display !== "none";
        nextRow.style.display = isVisible ? "none" : "";
        const icon = btn.querySelector("i");
        if (icon) {
            icon.className = isVisible ? "bi bi-chevron-right" : "bi bi-chevron-down";
        }
    }
};
/* ================= USER 360 PROFILE DRAWER ================= */
window.openUserProfileDrawer = function (userId) {
    const overlay = document.getElementById("profileDrawerOverlay");
    const drawer = document.getElementById("profileDrawer");
    if (overlay) overlay.classList.add("active");
    if (drawer) drawer.classList.add("active");
    document.body.style.overflow = "hidden";
    switchDrawerTab("overview");
    document.getElementById("drawer_user_name").innerText = "Loading...";
    document.getElementById("drawer_status_text").innerText = "...";
    const editBtn = document.getElementById("drawerEditBtn");
    if (editBtn) editBtn.setAttribute("data-userid", userId);
    fetch(`fetch_users.php?id=${userId}`)
        .then(res => res.json())
        .then(user => {
            if (user && !user.error) {
                window.currentDrawerUser = user;
                document.getElementById("drawer_user_name").innerText = user.username || "Unknown User";
                const activationToggle = document.getElementById("userActivationToggle");
                const statusText = document.getElementById("drawer_status_text");
                const createdAtEl = document.getElementById("drawer_created_at");
                const inactiveAtEl = document.getElementById("drawer_inactive_at");
                const createdAtContainer = document.getElementById("drawer_created_at_container");
                const inactiveAtContainer = document.getElementById("drawer_inactive_at_container");

                if (createdAtEl) createdAtEl.innerText = user.created_at || "---";
                if (inactiveAtEl) inactiveAtEl.innerText = user.inactive_at || "---";

                if (user.is_active == 1) {
                    activationToggle.checked = true;
                    statusText.innerText = "Active";
                    statusText.className = "ms-2 fw-bold text-success";
                    if (createdAtContainer) createdAtContainer.style.display = "block";
                    if (inactiveAtContainer) inactiveAtContainer.style.display = "none";
                } else {
                    activationToggle.checked = false;
                    statusText.innerText = "Inactive";
                    statusText.className = "ms-2 fw-bold text-danger";
                    if (createdAtContainer) createdAtContainer.style.display = "none";
                    if (inactiveAtContainer) inactiveAtContainer.style.display = "block";
                }
                document.getElementById("drawer_email").innerText = user.useremail || "---";
                document.getElementById("drawer_contact").innerText = user.phonenumber || "---";
                document.getElementById("drawer_dob").innerText = user.dob || "---";
                document.getElementById("drawer_unique_id").innerText = user.uniqueid || "---";
                document.getElementById("drawer_employee_id").innerText = user.employee_id || "---";
                document.getElementById("drawer_password").innerText = "••••••••";
                const pwdIcon = document.getElementById("drawer_password_icon");
                if (pwdIcon) {
                    pwdIcon.className = "bi bi-eye";
                }
                document.getElementById("drawer_doj").innerText = user.doj || "---";
                document.getElementById("drawer_salary").innerText = user.salary ? `₹${user.salary}` : "---";
                document.getElementById("drawer_project").innerText = user.project_name || "Unassigned";
                document.getElementById("drawer_project_type").innerText = user.project_type || "---";
                const drawerCity = document.getElementById("drawer_city");
                if (drawerCity) drawerCity.innerText = user.city || "---";
                document.getElementById("drawer_role").innerText = user.user_type || "---";
                document.getElementById("drawer_assigned_users").innerText = user.assign_user || "---";

                // Render CTC Salary Table
                if (typeof renderSalaryStructure === 'function') {
                    renderSalaryStructure(user);
                }
            }
        })
        .catch(err => console.error("Error fetching user details for drawer:", err));
    // Check status for both letters
    ['appointment_letter', 'offer_letter'].forEach(type => {
        fetch(`get_appointment_letter.php?user_id=${userId}&document_type=${type}`)
            .then(res => res.json())
            .then(res => {
                const statusMap = {
                    'appointment_letter': 'appointment_letter_status',
                    'offer_letter': 'offer_letter_status'
                };
                const btnMap = {
                    'appointment_letter': 'btn_print_appointment_letter',
                    'offer_letter': 'btn_print_offer_letter'
                };
                const btnPreviewMap = {
                    'appointment_letter': 'btn_preview_appointment_letter',
                    'offer_letter': 'btn_preview_offer_letter'
                };
                const btnMailMap = {
                    'appointment_letter': 'btn_mail_appointment_letter',
                    'offer_letter': 'btn_mail_offer_letter'
                };

                const statusId = statusMap[type];
                const btnId = btnMap[type];
                const btnPreviewId = btnPreviewMap[type];
                const btnMailId = btnMailMap[type];

                const statusEl = document.getElementById(statusId);
                const printBtn = document.getElementById(btnId);
                const previewBtn = document.getElementById(btnPreviewId);
                const mailBtn = document.getElementById(btnMailId);

                if (res.status === 'success' && res.data) {
                    if (statusEl) {
                        statusEl.innerHTML = '<i class="bi bi-check-circle-fill text-success"></i> Generated';
                        statusEl.className = 'info-value text-success';
                    }
                    if (printBtn) printBtn.style.display = 'inline-block';
                    if (previewBtn) previewBtn.style.display = 'inline-block';
                    if (mailBtn) mailBtn.style.display = 'inline-block';
                } else {
                    if (statusEl) {
                        statusEl.innerHTML = 'Not Generated';
                        statusEl.className = 'info-value text-muted';
                    }
                    if (printBtn) printBtn.style.display = 'none';
                    if (previewBtn) previewBtn.style.display = 'none';
                    if (mailBtn) mailBtn.style.display = 'none';
                }
            })
            .catch(err => console.error(`Error checking ${type} status:`, err));
    });
    loadPayslipHistory(userId);

    // Reset calendar to current month when opening drawer
    const now = new Date();
    currentCalendarMonth = now.getMonth();
    currentCalendarYear = now.getFullYear();

    fetch(`fetch_attendance_history.php?user_id=${userId}&month=${String(now.getMonth() + 1).padStart(2, '0')}&year=${now.getFullYear()}`)
        .then(res => res.json())
        .then(logs => {
            if (logs && !logs.error) {
                renderAttendanceCalendar(logs);
            } else {
                renderAttendanceCalendar({});
            }
        })
        .catch(err => console.error("Error fetching attendance for drawer:", err));

    // Load Assets
    if (typeof fetchUserAssets === "function") {
        fetchUserAssets(userId);
    }
};

window.toggleDrawerPasswordVisibility = function () {
    const pwdSpan = document.getElementById("drawer_password");
    const pwdIcon = document.getElementById("drawer_password_icon");
    if (!pwdSpan || !pwdIcon || !window.currentDrawerUser) return;
    
    if (pwdSpan.innerText === "••••••••") {
        pwdSpan.innerText = window.currentDrawerUser.epassword || "---";
        pwdIcon.classList.remove("bi-eye");
        pwdIcon.classList.add("bi-eye-slash");
    } else {
        pwdSpan.innerText = "••••••••";
        pwdIcon.classList.remove("bi-eye-slash");
        pwdIcon.classList.add("bi-eye");
    }
};

let currentCalendarMonth = new Date().getMonth();
let currentCalendarYear = new Date().getFullYear();

window.changeCalendarMonth = function (delta) {
    if (!window.currentDrawerUser) return;
    currentCalendarMonth += delta;
    if (currentCalendarMonth < 0) { currentCalendarMonth = 11; currentCalendarYear--; }
    else if (currentCalendarMonth > 11) { currentCalendarMonth = 0; currentCalendarYear++; }

    const userId = window.currentDrawerUser.id;
    fetch(`fetch_attendance_history.php?user_id=${userId}&month=${String(currentCalendarMonth + 1).padStart(2, '0')}&year=${currentCalendarYear}`)
        .then(res => res.json())
        .then(logs => renderAttendanceCalendar(logs))
        .catch(err => console.error("Error fetching attendance for navigation:", err));
};

function renderAttendanceCalendar(logs) {
    const calendarEl = document.getElementById("attendanceCalendar");
    const titleEl = document.getElementById("calendarMonthTitle");
    if (!calendarEl) return;
    calendarEl.innerHTML = "";

    const months = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
    if (titleEl) titleEl.innerText = `${months[currentCalendarMonth]} ${currentCalendarYear}`;

    // Update Summary Header Title
    const summaryTitleEl = document.querySelector("#drawer_tab_attendance h5");
    if (summaryTitleEl) summaryTitleEl.innerText = `${months[currentCalendarMonth]} Attendance Summary`;

    const firstDay = new Date(currentCalendarYear, currentCalendarMonth, 1).getDay();
    const daysInMonth = new Date(currentCalendarYear, currentCalendarMonth + 1, 0).getDate();

    const days = ["SUN", "MON", "TUE", "WED", "THU", "FRI", "SAT"];
    days.forEach(d => {
        const dayLabel = document.createElement("div");
        dayLabel.className = "calendar-day-label";
        dayLabel.innerText = d;
        calendarEl.appendChild(dayLabel);
    });

    for (let i = 0; i < firstDay; i++) {
        const emptyDay = document.createElement("div");
        emptyDay.className = "calendar-date empty";
        calendarEl.appendChild(emptyDay);
    }

    const todayObj = new Date();
    todayObj.setHours(0, 0, 0, 0); // Normalize today for comparison

    let counts = { present: 0, absent: 0, late: 0, leave: 0 };

    for (let day = 1; day <= daysInMonth; day++) {
        const dateStr = `${currentCalendarYear}-${String(currentCalendarMonth + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        const dayDate = new Date(currentCalendarYear, currentCalendarMonth, day);
        const dayEl = document.createElement("div");
        dayEl.className = "calendar-date";

        const dayNum = document.createElement("span");
        dayNum.innerText = day;
        dayEl.appendChild(dayNum);

        if (day === todayObj.getDate() && currentCalendarMonth === todayObj.getMonth() && currentCalendarYear === todayObj.getFullYear()) {
            dayEl.classList.add("today");
        }

        const isSunday = dayDate.getDay() === 0;
        const isPast = dayDate < todayObj;
        let log = logs[dateStr];

        // Default Sunday only when no log (company holidays come from API)
        if (isSunday && !log) {
            log = { status: 'Holiday', reason: 'Sunday' };
        } else if (!log && isPast) {
            log = { status: 'Absent', reason: 'No punch record' };
        }

        if (log) {
            const rawStatus = (typeof log === 'object' ? log.status : log) || "";
            const reason = typeof log === 'object' ? log.reason : '';
            const statusLower = rawStatus.toLowerCase();

            let statusClass = 'absent';

            // Tally logic aligned with attendance_report.php (case-insensitive for robustness)
            if (statusLower === "present") {
                statusClass = 'present';
                counts.present++;
            } else if (statusLower === "late") {
                statusClass = 'late';
                counts.present++;
                counts.late++;
            } else if (statusLower === "absent" || statusLower === "late-absent") {
                statusClass = 'absent';
                counts.absent++;
            } else if (log.is_company_holiday) {
                statusClass = 'company-holiday';
                counts.leave++;
            } else {
                // Sunday weekly off, approved leave types, etc.
                statusClass = statusLower === 'holiday' ? 'holiday' : 'leave';
                counts.leave++;
            }

            dayEl.classList.add(statusClass);

            if (reason) {
                dayEl.setAttribute("title", reason);
                const reasonEl = document.createElement("div");
                reasonEl.className = "calendar-reason";
                reasonEl.innerText = reason;
                dayEl.appendChild(reasonEl);
            }

            // Always add the status dot
            const dot = document.createElement("div");
            dot.className = "status-dot";
            dayEl.appendChild(dot);
        }
        calendarEl.appendChild(dayEl);
    }

    // Update Summary Cards
    document.getElementById("drawer_att_present").innerText = counts.present;
    document.getElementById("drawer_att_absent").innerText = counts.absent;
    document.getElementById("drawer_att_late").innerText = counts.late;
    document.getElementById("drawer_att_leave").innerText = counts.leave;
}
window.closeUserProfileDrawer = function () {
    const overlay = document.getElementById("profileDrawerOverlay");
    const drawer = document.getElementById("profileDrawer");
    if (overlay) overlay.classList.remove("active");
    if (drawer) drawer.classList.remove("active");
    document.body.style.overflow = "";
    window.currentDrawerUser = null;
};
window.switchDrawerTab = function (tabName) {
    document.querySelectorAll(".drawer-tab").forEach(t => t.classList.remove("active"));
    const tabs = document.querySelectorAll(".drawer-tab");
    tabs.forEach(t => { if (t.innerText.toLowerCase().includes(tabName.toLowerCase())) t.classList.add("active"); });
    document.querySelectorAll(".drawer-section").forEach(s => s.classList.remove("active"));
    const selectedSection = document.getElementById(`drawer_tab_${tabName}`);
    if (selectedSection) selectedSection.classList.add("active");
};
window.openEditModalFromDrawer = function () {
    const userId = document.getElementById("drawerEditBtn").getAttribute("data-userid");
    if (userId) openEditModal(userId);
};

/* ================= SALARY STRUCTURE LOGIC ================= */
window.renderSalaryStructure = function (user) {
    const container = document.getElementById('salary_structure_container');
    const overviewContainer = document.getElementById('overview_salary_structure_container');
    if (!container && !overviewContainer) return;

    const monthlyCTC = parseFloat(user.salary) || 0;
    const yearlyCTC = monthlyCTC * 12;

    // Check if any manual component is set to a non-zero value to determine if we should use manual structure or defaults
    const hasManual = (
        (user.first_amount && parseFloat(user.first_amount) > 0) ||
        (user.second_amount && parseFloat(user.second_amount) > 0) ||
        (user.third_amount && parseFloat(user.third_amount) > 0) ||
        (user.fourth_amount && parseFloat(user.fourth_amount) > 0) ||
        (user.fifth_amount && parseFloat(user.fifth_amount) > 0) ||
        (user.sixth_amount && parseFloat(user.sixth_amount) > 0)
    );

    const basic = hasManual ? (parseFloat(user.first_amount) || 0) : Math.round(monthlyCTC * 0.5);
    const hra = hasManual ? (parseFloat(user.second_amount) || 0) : Math.round(monthlyCTC * 0.2);
    const conveyance = hasManual ? (parseFloat(user.third_amount) || 0) : Math.round(monthlyCTC * 0.07);
    const pfEmployer = hasManual ? (parseFloat(user.fifth_amount) || 0) : Math.min(1800, Math.round(basic * 0.12));

    const monthlyGross = monthlyCTC - pfEmployer;
    // Special Allowance is the remainder if not explicitly set
    const specialAllowance = hasManual ? (parseFloat(user.fourth_amount) || 0) : (monthlyGross - (basic + hra + conveyance));

    // Standard Deductions (PF Employee + PT + Medical)
    const totalDeds = hasManual ? (parseFloat(user.sixth_amount) || 0) : (pfEmployer + 200 + 817);

    const netPay = monthlyGross - totalDeds;

    const tableHtml = `
        <table class="salary-table">
            <thead>
                <tr>
                    <th style="text-align: left;">Earnings & Benefits</th>
                    <th style="text-align: right;">Monthly</th>
                    <th style="text-align: right;">Yearly</th>
                </tr>
            </thead>
            <tbody>
                <tr class="total-row">
                    <td class="row-label">Total CTC (Cost to Company)</td>
                    <td class="row-value">₹${monthlyCTC.toLocaleString()}</td>
                    <td class="row-yearly">₹${yearlyCTC.toLocaleString()}</td>
                </tr>
                <tr>
                    <td class="row-label">Basic Salary</td>
                    <td class="row-value">₹${basic.toLocaleString()}</td>
                    <td class="row-yearly">₹${(basic * 12).toLocaleString()}</td>
                </tr>
                <tr>
                    <td class="row-label">HRA (House Rent Allowance)</td>
                    <td class="row-value">₹${hra.toLocaleString()}</td>
                    <td class="row-yearly">₹${(hra * 12).toLocaleString()}</td>
                </tr>
                <tr>
                    <td class="row-label">Conveyance Allowance</td>
                    <td class="row-value">₹${conveyance.toLocaleString()}</td>
                    <td class="row-yearly">₹${(conveyance * 12).toLocaleString()}</td>
                </tr>
                <tr>
                    <td class="row-label">Special Allowance</td>
                    <td class="row-value">₹${specialAllowance.toLocaleString()}</td>
                    <td class="row-yearly">₹${(specialAllowance * 12).toLocaleString()}</td>
                </tr>
                <tr class="highlight-row">
                    <td class="row-label">PF (Employer Part)</td>
                    <td class="row-value">₹${pfEmployer.toLocaleString()}</td>
                    <td class="row-yearly">₹${(pfEmployer * 12).toLocaleString()}</td>
                </tr>
                <tr class="fw-bold">
                    <td class="row-label">Monthly Gross</td>
                    <td class="row-value">₹${monthlyGross.toLocaleString()}</td>
                    <td class="row-yearly">₹${(monthlyGross * 12).toLocaleString()}</td>
                </tr>
                <tr class="deduction-row">
                    <td class="row-label">Standard Deductions (PF, PT, Med)</td>
                    <td class="row-value">-₹${totalDeds.toLocaleString()}</td>
                    <td class="row-yearly">-₹${(totalDeds * 12).toLocaleString()}</td>
                </tr>
                <tr class="net-row">
                    <td class="row-label">Net Take Home Pay</td>
                    <td class="row-value">₹${netPay.toLocaleString()}</td>
                    <td class="row-yearly">₹${(netPay * 12).toLocaleString()}</td>
                </tr>
            </tbody>
        </table>
    `;

    const gridHtml = `
        <div class="overview-grid-3">
            <!-- Total CTC -->
            <div class="overview-field">
                <span class="overview-label">Total CTC (Cost to Company)</span>
                <p class="overview-value text-primary">₹${monthlyCTC.toLocaleString()} <span class="text-muted" style="font-size: 11px; font-weight: normal;">(Yearly: ₹${yearlyCTC.toLocaleString()})</span></p>
            </div>
            <!-- Basic Salary -->
            <div class="overview-field">
                <span class="overview-label">Basic Salary</span>
                <p class="overview-value">₹${basic.toLocaleString()}</p>
            </div>
            <!-- HRA -->
            <div class="overview-field">
                <span class="overview-label">HRA (House Rent Allowance)</span>
                <p class="overview-value">₹${hra.toLocaleString()}</p>
            </div>
            <!-- Conveyance Allowance -->
            <div class="overview-field">
                <span class="overview-label">Conveyance Allowance</span>
                <p class="overview-value">₹${conveyance.toLocaleString()}</p>
            </div>
            <!-- Special Allowance -->
            <div class="overview-field">
                <span class="overview-label">Special Allowance</span>
                <p class="overview-value">₹${specialAllowance.toLocaleString()}</p>
            </div>
            <!-- PF (Employer Part) -->
            <div class="overview-field">
                <span class="overview-label">PF (Employer Part)</span>
                <p class="overview-value">₹${pfEmployer.toLocaleString()}</p>
            </div>
            <!-- Monthly Gross -->
            <div class="overview-field">
                <span class="overview-label">Monthly Gross</span>
                <p class="overview-value" style="font-weight: 700;">₹${monthlyGross.toLocaleString()}</p>
            </div>
            <!-- Standard Deductions -->
            <div class="overview-field">
                <span class="overview-label">Standard Deductions (PF, PT, Med)</span>
                <p class="overview-value" style="color: #ef4444;">-₹${totalDeds.toLocaleString()}</p>
            </div>
            <!-- Net Take Home Pay -->
            <div class="overview-field">
                <span class="overview-label text-success" style="color: #10b981; font-weight: 700;">Net Take Home Pay</span>
                <p class="overview-value" style="color: #10b981; font-weight: 700; font-size: 16px;">₹${netPay.toLocaleString()}</p>
            </div>
        </div>
    `;

    if (container) container.innerHTML = tableHtml;
    if (overviewContainer) overviewContainer.innerHTML = gridHtml;
};

window.calculateSwalSalaryStructure = function (changedField) {
    const ctcInput = document.getElementById('swal_salary');
    const basicInput = document.getElementById('swal_basic');
    const hraInput = document.getElementById('swal_hra');
    const conveyanceInput = document.getElementById('swal_conveyance');
    const specialInput = document.getElementById('swal_special');
    const pfEmployerInput = document.getElementById('swal_pf_employer');
    const deductionsInput = document.getElementById('swal_deductions');

    if (!ctcInput) return;

    const ctc = parseFloat(ctcInput.value) || 0;
    let basic = parseFloat(basicInput.value) || 0;
    let hra = parseFloat(hraInput.value) || 0;
    let conveyance = parseFloat(conveyanceInput.value) || 0;
    let pfEmployer = parseFloat(pfEmployerInput.value) || 0;
    let deductions = parseFloat(deductionsInput.value) || 0;

    if (changedField === 'salary') {
        basic = Math.round(ctc * 0.5);
        hra = Math.round(ctc * 0.2);
        conveyance = Math.round(ctc * 0.07);
        pfEmployer = Math.min(1800, Math.round(basic * 0.12));
        deductions = pfEmployer + 200 + 817;

        basicInput.value = basic;
        hraInput.value = hra;
        conveyanceInput.value = conveyance;
        pfEmployerInput.value = pfEmployer;
        deductionsInput.value = deductions;
    } else if (changedField === 'basic') {
        hra = Math.round(basic * 0.4);
        pfEmployer = Math.min(1800, Math.round(basic * 0.12));
        deductions = pfEmployer + 200 + 817;

        hraInput.value = hra;
        pfEmployerInput.value = pfEmployer;
        deductionsInput.value = deductions;
    } else if (changedField === 'pf_employer') {
        deductions = pfEmployer + 200 + 817;
        deductionsInput.value = deductions;
    }

    const monthlyGross = ctc - pfEmployer;
    const special = monthlyGross - (basic + hra + conveyance);
    specialInput.value = special;
};

window.toggleSalaryEdit = function () {
    if (!window.currentDrawerUser) return;

    // Check if manual structure exists for pre-filling
    const u = window.currentDrawerUser;
    const hasManual = (
        (u.first_amount && parseFloat(u.first_amount) > 0) ||
        (u.second_amount && parseFloat(u.second_amount) > 0) ||
        (u.third_amount && parseFloat(u.third_amount) > 0) ||
        (u.fourth_amount && parseFloat(u.fourth_amount) > 0) ||
        (u.fifth_amount && parseFloat(u.fifth_amount) > 0) ||
        (u.sixth_amount && parseFloat(u.sixth_amount) > 0)
    );

    // Calculate defaults for pre-filling if no manual structure exists
    const mCTC = parseFloat(u.salary) || 0;
    const def_basic = Math.round(mCTC * 0.5);
    const def_hra = Math.round(mCTC * 0.2);
    const def_conv = Math.round(mCTC * 0.07);
    const def_pfEmp = Math.min(1800, Math.round(def_basic * 0.12));
    const def_gross = mCTC - def_pfEmp;
    const def_spec = def_gross - (def_basic + def_hra + def_conv);
    const def_deds = def_pfEmp + 200 + 817;

    Swal.fire({
        title: 'Update Salary Structure',
        width: '600px',
        html: `
            <div class="row g-3 text-start mt-2">
                <div class="col-md-12">
                    <label class="form-label fw-bold">Total Monthly CTC (₹)</label>
                    <input type="number" id="swal_salary" class="form-control" value="${u.salary}" oninput="window.calculateSwalSalaryStructure('salary')">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold small text-muted">Basic Salary</label>
                    <input type="number" id="swal_basic" class="form-control" value="${hasManual ? (u.first_amount || 0) : def_basic}" oninput="window.calculateSwalSalaryStructure('basic')">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold small text-muted">HRA</label>
                    <input type="number" id="swal_hra" class="form-control" value="${hasManual ? (u.second_amount || 0) : def_hra}" oninput="window.calculateSwalSalaryStructure('hra')">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold small text-muted">Conveyance Allowance</label>
                    <input type="number" id="swal_conveyance" class="form-control" value="${hasManual ? (u.third_amount || 0) : def_conv}" oninput="window.calculateSwalSalaryStructure('conveyance')">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold small text-muted">Special Allowance</label>
                    <input type="number" id="swal_special" class="form-control" value="${hasManual ? (u.fourth_amount || 0) : def_spec}" oninput="window.calculateSwalSalaryStructure('special')">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold small text-muted">PF (Employer Part)</label>
                    <input type="number" id="swal_pf_employer" class="form-control" value="${hasManual ? (u.fifth_amount || 0) : def_pfEmp}" oninput="window.calculateSwalSalaryStructure('pf_employer')">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold small text-muted">Standard Deductions</label>
                    <input type="number" id="swal_deductions" class="form-control" value="${hasManual ? (u.sixth_amount || 0) : def_deds}" oninput="window.calculateSwalSalaryStructure('deductions')">
                </div>
                <div class="col-12 mt-3">
                    <div class="alert alert-info py-2 small mb-0">
                        <i class="bi bi-info-circle"></i> Updating these values will manually override the default auto-calculated structure for this user.
                    </div>
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Update Structure',
        confirmButtonColor: '#4f46e5',
        preConfirm: () => {
            return {
                salary: document.getElementById('swal_salary').value,
                basic: document.getElementById('swal_basic').value,
                hra: document.getElementById('swal_hra').value,
                conveyance: document.getElementById('swal_conveyance').value,
                special: document.getElementById('swal_special').value,
                pf_employer: document.getElementById('swal_pf_employer').value,
                deductions: document.getElementById('swal_deductions').value
            };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const data = result.value;
            const formData = new FormData();
            formData.append('action', 'update_user_salary');
            formData.append('id', window.currentDrawerUser.id);
            formData.append('salary', data.salary);
            formData.append('basic', data.basic);
            formData.append('hra', data.hra);
            formData.append('conveyance', data.conveyance);
            formData.append('special', data.special);
            formData.append('pf_employer', data.pf_employer);
            formData.append('deductions', data.deductions);

            fetch('action.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(res => {
                    if (res.status === 'success') {
                        window.currentDrawerUser.salary = data.salary;
                        window.currentDrawerUser.first_amount = data.basic;
                        window.currentDrawerUser.second_amount = data.hra;
                        window.currentDrawerUser.third_amount = data.conveyance;
                        window.currentDrawerUser.fourth_amount = data.special;
                        window.currentDrawerUser.fifth_amount = data.pf_employer;
                        window.currentDrawerUser.sixth_amount = data.deductions;

                        document.getElementById("drawer_salary").innerText = `₹${data.salary}`;
                        renderSalaryStructure(window.currentDrawerUser);
                        Swal.fire({
                            title: 'Structure Updated!',
                            text: 'The manual salary breakdown has been applied.',
                            icon: 'success',
                            timer: 2000,
                            showConfirmButton: false
                        });
                        if (typeof loadUsers === 'function') loadUsers();
                    } else {
                        Swal.fire('Error', res.message, 'error');
                    }
                })
                .catch(err => {
                    console.error(err);
                    Swal.fire('Error', 'Server communication failed.', 'error');
                });
        }
    });
};
/* ================= EDIT / DELETE USER HANDLERS ================= */
document.addEventListener("click", function (e) {
    const editBtn = e.target.closest(".editLink");
    if (editBtn) { e.preventDefault(); openEditModal(editBtn.id); return; }
    const deleteBtn = e.target.closest(".deleteLink");
    if (deleteBtn) {
        e.preventDefault();
        Swal.fire({
            title: 'Are you sure?',
            text: "This user will be permanently deleted.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#6366f1',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, delete them!'
        }).then((result) => { if (result.isConfirmed) deleteUser(deleteBtn.id); });
    }
});
window.calculateModalSalaryStructure = function (changedField) {
    const ctcInput = document.getElementById('userMonthlyCTC');
    const basicInput = document.getElementById('userBasic');
    const hraInput = document.getElementById('userHRA');
    const conveyanceInput = document.getElementById('userConveyance');
    const specialInput = document.getElementById('userSpecial');
    const pfEmployerInput = document.getElementById('userPFEmployer');
    const deductionsInput = document.getElementById('userDeductions');

    if (!ctcInput) return;

    const ctc = parseFloat(ctcInput.value) || 0;
    let basic = parseFloat(basicInput.value) || 0;
    let hra = parseFloat(hraInput.value) || 0;
    let conveyance = parseFloat(conveyanceInput.value) || 0;
    let pfEmployer = parseFloat(pfEmployerInput.value) || 0;
    let deductions = parseFloat(deductionsInput.value) || 0;

    if (changedField === 'salary') {
        basic = Math.round(ctc * 0.5);
        hra = Math.round(ctc * 0.2);
        conveyance = Math.round(ctc * 0.07);
        pfEmployer = Math.min(1800, Math.round(basic * 0.12));
        deductions = pfEmployer + 200 + 817;

        basicInput.value = basic;
        hraInput.value = hra;
        conveyanceInput.value = conveyance;
        pfEmployerInput.value = pfEmployer;
        deductionsInput.value = deductions;
    } else if (changedField === 'basic') {
        hra = Math.round(basic * 0.4);
        pfEmployer = Math.min(1800, Math.round(basic * 0.12));
        deductions = pfEmployer + 200 + 817;

        hraInput.value = hra;
        pfEmployerInput.value = pfEmployer;
        deductionsInput.value = deductions;
    } else if (changedField === 'pf_employer') {
        deductions = pfEmployer + 200 + 817;
        deductionsInput.value = deductions;
    }

    const monthlyGross = ctc - pfEmployer;
    const special = monthlyGross - (basic + hra + conveyance);
    specialInput.value = special;
};

function openEditModal(userId) {
    fetch("fetch_users.php?id=" + userId)
        .then(res => res.json())
        .then(user => {
            if (!user || user.error) return;
            document.getElementById("userId").value = user.id;
            document.getElementById("userName").value = user.username ?? "";
            document.getElementById("userEmail").value = user.useremail ?? "";
            document.getElementById("userContact").value = user.phonenumber ?? "";
            document.getElementById("userPassword").value = user.epassword ?? "";
            document.getElementById("userMonthlyCTC").value = user.salary ?? "";
            document.getElementById("userDOJ").value = user.doj ?? "";
            document.getElementById("userDOB").value = user.dob ?? "";
            document.getElementById("userUniqueID").value = user.uniqueid ?? "";
            document.getElementById("userEmployeeID").value = user.employee_id ?? "";
            if (document.getElementById("userStatus")) document.getElementById("userStatus").value = user.is_active;

            // Populate newly added fields
            if (document.getElementById("userRoleType")) document.getElementById("userRoleType").value = user.user_type ?? "";
            if (document.getElementById("userProjectName")) document.getElementById("userProjectName").value = user.project_name ?? "";
            if (document.getElementById("userProjectType")) document.getElementById("userProjectType").value = user.project_type ?? "";
            if (document.getElementById("userCity")) document.getElementById("userCity").value = user.city ?? "";
            if (document.getElementById("userBasic")) document.getElementById("userBasic").value = user.first_amount ?? "";
            if (document.getElementById("userHRA")) document.getElementById("userHRA").value = user.second_amount ?? "";
            if (document.getElementById("userConveyance")) document.getElementById("userConveyance").value = user.third_amount ?? "";
            if (document.getElementById("userSpecial")) document.getElementById("userSpecial").value = user.fourth_amount ?? "";
            if (document.getElementById("userPFEmployer")) document.getElementById("userPFEmployer").value = user.fifth_amount ?? "";
            if (document.getElementById("userDeductions")) document.getElementById("userDeductions").value = user.sixth_amount ?? "";

            const assignTablenames = user.assign_user ? user.assign_user.split(',').map(s => s.trim()).filter(s => s !== "") : [];
            window.selectedUsers = resolveAssignUserLabels(assignTablenames);
            updateTags();
            document.getElementById("addEditModalLabel").innerText = "Edit User";
            const passwordField = document.getElementById("userPassword");
            if (passwordField) passwordField.removeAttribute("required");
            const statusField = document.getElementById("statusFieldGroup");
            if (statusField) statusField.style.display = "block";
            const modalEl = document.getElementById("addEditModal");
            const modal = new bootstrap.Modal(modalEl);
            modal.show();
        }).catch(err => console.error("Edit Load Error:", err));
}
window.clearUserForm = function () {
    document.getElementById("userForm").reset();
    document.getElementById("userId").value = "";
    document.getElementById("addEditModalLabel").innerText = "Add New User";
    const passwordField = document.getElementById("userPassword");
    if (passwordField) passwordField.setAttribute("required", "");
    const statusField = document.getElementById("statusFieldGroup");
    if (statusField) statusField.style.display = "block";

    // Clear newly added fields
    if (document.getElementById("userRoleType")) document.getElementById("userRoleType").value = "";
    if (document.getElementById("userProjectName")) document.getElementById("userProjectName").value = "";
    if (document.getElementById("userProjectType")) document.getElementById("userProjectType").value = "";
    if (document.getElementById("userCity")) document.getElementById("userCity").value = "";
    if (document.getElementById("userBasic")) document.getElementById("userBasic").value = "";
    if (document.getElementById("userHRA")) document.getElementById("userHRA").value = "";
    if (document.getElementById("userConveyance")) document.getElementById("userConveyance").value = "";
    if (document.getElementById("userSpecial")) document.getElementById("userSpecial").value = "";
    if (document.getElementById("userPFEmployer")) document.getElementById("userPFEmployer").value = "";
    if (document.getElementById("userDeductions")) document.getElementById("userDeductions").value = "";

    const emailFeedback = document.getElementById("userEmailFeedback");
    if (emailFeedback) {
        emailFeedback.textContent = "";
        emailFeedback.classList.remove("is-invalid");
    }
    const emailField = document.getElementById("userEmail");
    if (emailField) {
        emailField.setCustomValidity("");
        emailField.dataset.duplicateEmail = "false";
    }

    window.selectedUsers = [];
    updateTags();
};
function deleteUser(userId) {
    fetch("action.php?delete=1&id=" + userId)
        .then(res => res.text())
        .then(data => { 
            if (data.includes('success')) {
                location.reload(); 
            } else {
                alert("Delete failed. Server response: " + data);
            }
        })
        .catch(err => console.error("Delete Error:", err));
}
const userFormFieldLabels = {
    userName: "Full Name",
    userEmail: "Email Address",
    userContact: "Contact No.",
    userPassword: "Password",
    userUniqueID: "Unique ID"
};

let userFormAlertOpen = false;
let userFormSaveLocked = false;

async function showUserFormAlert(title, message, icon = "warning") {
    if (userFormAlertOpen) return;
    userFormAlertOpen = true;

    const isDark = document.body.classList.contains("dark-mode");
    if (typeof Swal !== "undefined") {
        await Swal.fire({
            icon,
            title,
            html: `<div class="user-form-alert-message">${message}</div>`,
            confirmButtonText: "Got it",
            confirmButtonColor: "#1e6063",
            background: isDark ? "#121212" : "#ffffff",
            color: isDark ? "#f1f5f9" : "#334155",
            returnFocus: false,
            heightAuto: false,
            customClass: {
                popup: "user-form-swal-popup",
                title: "user-form-swal-title",
                htmlContainer: "user-form-swal-body",
                confirmButton: "user-form-swal-btn"
            }
        });
        await new Promise(resolve => setTimeout(resolve, 250));
        userFormAlertOpen = false;
        return;
    }

    const plain = message.replace(/<[^>]+>/g, " ").replace(/\s+/g, " ").trim();
    alert(`${title}\n\n${plain}`);
    userFormAlertOpen = false;
}

function getUserFormValidationMessage(form) {
    const invalidFields = Array.from(form.querySelectorAll(":invalid"));
    if (!invalidFields.length) {
        return "Please check the form for missing or invalid values.";
    }
    const missing = invalidFields.map(el => userFormFieldLabels[el.id] || el.name || "Required field");
    return `Please complete the following required field${missing.length > 1 ? "s" : ""}:<ul class="user-form-alert-list">${missing.map(label => `<li>${label}</li>`).join("")}</ul>`;
}

async function checkUserEmailAvailable(email, excludeUserId = "") {
    const params = new URLSearchParams({
        check_email: "1",
        email: email.trim()
    });
    if (excludeUserId) params.append("exclude_id", excludeUserId);
    const res = await fetch(`action.php?${params.toString()}`);
    const data = await res.json();
    return !data.exists;
}

window.validateUserEmailField = async function (showMessage = true) {
    const emailEl = document.getElementById("userEmail");
    const feedbackEl = document.getElementById("userEmailFeedback");
    if (!emailEl) return true;

    const email = emailEl.value.trim();
    emailEl.setCustomValidity("");

    if (!email) {
        emailEl.dataset.duplicateEmail = "false";
        if (feedbackEl) {
            feedbackEl.textContent = "";
            feedbackEl.classList.remove("is-invalid");
        }
        return true;
    }

    if (!emailEl.checkValidity()) {
        if (showMessage) {
            await showUserFormAlert("Invalid Email", "Please enter a valid email address.", "warning");
        }
        return false;
    }

    try {
        const userId = document.getElementById("userId")?.value || "";
        const available = await checkUserEmailAvailable(email, userId);
        if (!available) {
            const message = "This email is already registered to another employee. Each user must have a unique email address.";
            emailEl.dataset.duplicateEmail = "true";
            if (feedbackEl) {
                feedbackEl.textContent = "";
                feedbackEl.classList.remove("is-invalid");
            }
            if (showMessage) {
                await showUserFormAlert("Email Already Exists", message, "warning");
            }
            return false;
        }
        emailEl.dataset.duplicateEmail = "false";
        if (feedbackEl) {
            feedbackEl.textContent = "";
            feedbackEl.classList.remove("is-invalid");
        }
        return true;
    } catch (err) {
        console.error("Email availability check failed:", err);
        return true;
    }
};

document.getElementById("userEmail")?.addEventListener("input", function () {
    this.setCustomValidity("");
    this.dataset.duplicateEmail = "false";
    const feedbackEl = document.getElementById("userEmailFeedback");
    if (feedbackEl) {
        feedbackEl.textContent = "";
        feedbackEl.classList.remove("is-invalid");
    }
});

document.getElementById("saveUserBtn")?.addEventListener("click", async function (e) {
    e.preventDefault();
    e.stopPropagation();
    if (userFormSaveLocked || userFormAlertOpen) return;

    const form = document.getElementById("userForm");
    if (!form) return;

    const saveBtn = this;
    userFormSaveLocked = true;
    saveBtn.disabled = true;

    const unlockSave = () => {
        saveBtn.disabled = false;
        setTimeout(() => { userFormSaveLocked = false; }, 350);
    };

    try {
    const userId = document.getElementById("userId").value;
    const isNewUser = !userId;

    if (isNewUser) {
        const basicFields = [
            { id: "userName", message: "Full Name is required." },
            { id: "userEmail", message: "Email Address is required." },
            { id: "userContact", message: "Contact No. is required." },
            { id: "userPassword", message: "Password is required." }
        ];
        for (const field of basicFields) {
            const el = document.getElementById(field.id);
            if (!el) continue;
            el.setCustomValidity(el.value.trim() ? "" : field.message);
        }
    } else {
        ["userName", "userEmail", "userContact", "userPassword"].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.setCustomValidity("");
        });
    }

    if (!form.checkValidity()) {
        await showUserFormAlert("Missing Information", getUserFormValidationMessage(form), "warning");
        const firstInvalid = form.querySelector(":invalid");
        if (firstInvalid) {
            firstInvalid.scrollIntoView({ behavior: "smooth", block: "center" });
        }
        return;
    }

    const emailOk = await validateUserEmailField(true);
    if (!emailOk) return;

    const formData = new FormData();
    formData.append('id', userId);
    formData.append('ename', document.getElementById("userName").value);
    formData.append('eemail', document.getElementById("userEmail").value);
    formData.append('enumber', document.getElementById("userContact").value);
    formData.append('epass', document.getElementById("userPassword").value);
    formData.append('esalary', document.getElementById("userMonthlyCTC").value);
    formData.append('doj', document.getElementById("userDOJ").value);
    formData.append('dob', document.getElementById("userDOB").value);
    formData.append('etable', document.getElementById("userUniqueID").value);
    formData.append('emid', document.getElementById("userEmployeeID").value);
    formData.append('assign_user', document.getElementById("assign_user_hidden").value);
    formData.append('is_active', document.getElementById("userStatus").value);

    // Append newly added fields for Designation, Project, and Salary Breakdown
    formData.append('user_type', document.getElementById("userRoleType") ? document.getElementById("userRoleType").value : "");
    formData.append('project_name', document.getElementById("userProjectName") ? document.getElementById("userProjectName").value : "");
    formData.append('D_project', document.getElementById("userProjectType") ? document.getElementById("userProjectType").value : "");
    formData.append('city', document.getElementById("userCity") ? document.getElementById("userCity").value : "");
    formData.append('amountO', document.getElementById("userBasic") ? document.getElementById("userBasic").value : "0");
    formData.append('amountT', document.getElementById("userHRA") ? document.getElementById("userHRA").value : "0");
    formData.append('amountTh', document.getElementById("userConveyance") ? document.getElementById("userConveyance").value : "0");
    formData.append('amountF', document.getElementById("userSpecial") ? document.getElementById("userSpecial").value : "0");
    formData.append('amountFf', document.getElementById("userPFEmployer") ? document.getElementById("userPFEmployer").value : "0");
    formData.append('amountS', document.getElementById("userDeductions") ? document.getElementById("userDeductions").value : "0");

    if (userId) formData.append('update', '1'); else formData.append('add', '1');
    const res = await fetch("action.php", { method: "POST", body: formData });
    const data = await res.text();
    if (data.includes('success')) {
        const modalEl = document.getElementById("addEditModal");
        const modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) modal.hide();
        location.reload();
        return;
    }

    console.error("Server Response:", data);
    const temp = document.createElement("div");
    temp.innerHTML = data;
    const strongEl = temp.querySelector("strong");
    const message = (strongEl?.textContent || temp.innerText || data || "Could not save user. Please try again.")
        .trim()
        .replace(/\s*close\s*$/i, "");
    const isEmailError = /email.*already registered/i.test(message);
    const isBasicInfoError = /basic information/i.test(message);
    const title = isEmailError ? "Email Already Exists" : isBasicInfoError ? "Missing Information" : "Unable to Save";
    const icon = isEmailError || isBasicInfoError ? "warning" : "error";
    await showUserFormAlert(title, message, icon);
    } catch (err) {
        console.error("Save Error:", err);
    } finally {
        unlockSave();
    }
});
/* ================= ADD/EDIT USER MULTISELECT LOGIC ================= */
window.selectedUsers = [];

function resolveAssignUserLabels(tablenameList) {
    const lookup = {};
    document.querySelectorAll('#dropdown .assign-user-option').forEach(el => {
        if (el.dataset.value) lookup[el.dataset.value] = el.dataset.label || el.dataset.value;
    });
    return tablenameList.map(val => ({ value: val, label: lookup[val] || val }));
}

window.toggleDropdown = function (event) {
    event.stopPropagation();
    const dropdown = document.getElementById('dropdown');
    dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
};
window.filterDropdown = function () {
    const input = document.getElementById('search_user_input');
    const filter = input ? input.value.toLowerCase() : '';
    const items = document.querySelectorAll('#dropdown .assign-user-option');
    items.forEach(item => { item.style.display = item.innerText.toLowerCase().includes(filter) ? 'block' : 'none'; });
};
window.selectUser = function (tablename, display) {
    if (!window.selectedUsers.some(u => u.value === tablename)) {
        window.selectedUsers.push({ value: tablename, label: display || tablename });
        updateTags();
    }
    const dropdown = document.getElementById('dropdown');
    if (dropdown) dropdown.style.display = 'none';
    const searchInput = document.getElementById('search_user_input');
    if (searchInput) searchInput.value = '';
};
window.removeUser = function (tablename) {
    window.selectedUsers = window.selectedUsers.filter(u => u.value !== tablename);
    updateTags();
};
function updateTags() {
    const container = document.getElementById('selected_tags');
    const hiddenInput = document.getElementById('assign_user_hidden');
    if (!container) return;
    container.innerHTML = '';
    window.selectedUsers.forEach(u => {
        const tag = document.createElement('div');
        tag.className = 'tag';
        tag.appendChild(document.createTextNode(u.label));
        const removeBtn = document.createElement('span');
        removeBtn.textContent = '\u00d7';
        removeBtn.style.cssText = 'margin-left:5px; cursor:pointer;';
        removeBtn.onclick = () => removeUser(u.value);
        tag.appendChild(removeBtn);
        container.appendChild(tag);
    });
    if (hiddenInput) hiddenInput.value = window.selectedUsers.map(u => u.value).join(',');
}
document.addEventListener('click', function (e) {
    const dropdown = document.getElementById('dropdown');
    const multiselect = document.querySelector('.custom-multiselect');
    if (dropdown && multiselect && !multiselect.contains(e.target)) {
        dropdown.style.display = 'none';
    }
});
/* ================= USRDD MULTISELECT DROPDOWN LOGIC ================= */
let usrdd_selected = {};
let usrddInputTimer = null;
function initUsrdd() {
    document.querySelectorAll('.usr-custom-dropdown-input').forEach(input => {
        const field = input.id;
        usrdd_selected[field] = [];
        input.addEventListener('focus', () => showUsrddList(field));
        input.addEventListener('input', () => {
            clearTimeout(usrddInputTimer);
            usrddInputTimer = setTimeout(() => showUsrddList(field), 250);
        });
        document.addEventListener('click', (e) => { if (!e.target.closest(`#usrdd-${field}`)) hideUsrddList(field); });
    });
}
function showUsrddList(field) {
    const list = document.getElementById(`usrdd-${field}-list`);
    if (!list) return;
    const input = document.getElementById(field);
    const q = input ? input.value.trim() : "";
    list.innerHTML = '<div class="usr-dd-empty">Loading...</div>';
    list.classList.add('open');

    const params = new URLSearchParams({ distinct: field, q });
    fetch("fetch_users.php?" + params.toString(), { credentials: "same-origin", cache: "no-store" })
        .then((res) => res.json())
        .then((payload) => {
            const values = Array.isArray(payload.values) ? payload.values : [];
            let itemsHtml = "";
            values.forEach((val) => {
                const safeVal = String(val).replace(/\\/g, "\\\\").replace(/'/g, "\\'");
                const isSelected = usrdd_selected[field].includes(val);
                itemsHtml += `<div class="usr-dd-item ${isSelected ? 'selected' : ''}" onclick="toggleUsrddSelection('${field}', '${safeVal}')">${isSelected ? '<span class="usr-dd-check">✓</span>' : ''}${val}</div>`;
            });
            list.innerHTML = itemsHtml || '<div class="usr-dd-empty">No values found</div>';
        })
        .catch(() => {
            list.innerHTML = '<div class="usr-dd-empty">Failed to load values</div>';
        });
}
window.toggleUsrddSelection = function (field, value) {
    const idx = usrdd_selected[field].indexOf(value);
    if (idx > -1) usrdd_selected[field].splice(idx, 1); else usrdd_selected[field].push(value);
    renderUsrddChips(field);
    showUsrddList(field);
};
function renderUsrddChips(field) {
    const container = document.getElementById(`usrdd-${field}-chips`);
    if (!container) return;
    container.innerHTML = usrdd_selected[field].map(val => `<div class="usr-chip">${val}<span class="usr-chip-remove" onclick="toggleUsrddSelection('${field}', '${val}')">×</span></div>`).join('');
}
function filterUsrddList(field, query) {
    const list = document.getElementById(`usrdd-${field}-list`);
    const items = list.querySelectorAll('.usr-dd-item');
    query = query.toLowerCase();
    items.forEach(item => { item.style.display = item.innerText.toLowerCase().includes(query) ? 'flex' : 'none'; });
}
function hideUsrddList(field) { document.getElementById(`usrdd-${field}-list`)?.classList.remove('open'); }
function resetUsrddFilters() { usrdd_selected = {}; document.querySelectorAll('.usr-custom-dropdown-input').forEach(input => { usrdd_selected[input.id] = []; renderUsrddChips(input.id); }); }
/* ================= MODAL TRIGGERS ================= */
$(document).ready(function () {
    const modal = $('#filterModal');
    $('#openFilterBtn').on('click', function () {
        modal.addClass('custom-show');
        $('body').addClass('modal-open');
        if (!$('.custom-backdrop').length) $('body').append('<div class="custom-backdrop"></div>');
    });
    function closeModal() { modal.removeClass('custom-show'); $('body').removeClass('modal-open'); $('.custom-backdrop').remove(); }
    $('.users-filter-close, .btn-users-close, #closeFilter, #cancleFilter').on('click', closeModal);
    modal.on('click', function (e) { if ($(e.target).is('#filterModal')) closeModal(); });
    $('#applyFiltersBtn').on('click', function () { applyFilters(); filtersApplied = true; checkIfFiltersActive(); closeModal(); });
    $('#clearFiltersBtn').on('click', function () { 
        resetUsrddFilters(); 
        document.querySelectorAll("#filterModal input").forEach(i => i.value = ""); 
        resetCardFiltersUI();
        applyFilters(); 
        filtersApplied = false; 
        checkIfFiltersActive(); 
        closeModal(); 
    });
    initUsrdd();
});
/* ================= PAGINATION & SUMMARY ================= */
function updateUsersPagination() {
    const meta = window.usersListMeta || {};
    const totalRows = meta.total || 0;
    const limit = meta.limit || parseInt(document.getElementById("users-limit")?.value, 10) || 10;
    const currentPage = meta.page || window.currentUsersPage || 1;
    const totalPages = meta.total_pages || Math.max(1, Math.ceil(totalRows / limit));
    const start = totalRows === 0 ? 0 : (currentPage - 1) * limit;
    const end = totalRows === 0 ? 0 : Math.min(start + limit, totalRows);

    const elStart = document.getElementById("showingStart"); if (elStart) elStart.innerText = totalRows === 0 ? 0 : start + 1;
    const elEnd = document.getElementById("showingEnd"); if (elEnd) elEnd.innerText = end;
    const elTotal = document.getElementById("totalEntries"); if (elTotal) elTotal.innerText = totalRows;
    const elCurrent = document.getElementById("currentPageBtn"); if (elCurrent) elCurrent.innerText = currentPage;
    const prevBtn = document.getElementById("prevPageBtn"); const nextBtn = document.getElementById("nextPageBtn");
    if (prevBtn) prevBtn.classList.toggle("disabled", currentPage <= 1);
    if (nextBtn) nextBtn.classList.toggle("disabled", currentPage >= totalPages);
}
function updateUsersSummary() {
    updateUsersSummaryFromMeta();
}
document.getElementById("prevPageBtn")?.addEventListener("click", () => {
    if (window.currentUsersPage <= 1) return;
    loadUsers(window.currentUsersPage - 1);
});
document.getElementById("nextPageBtn")?.addEventListener("click", () => {
    const totalPages = window.usersListMeta?.total_pages || 1;
    if (window.currentUsersPage >= totalPages) return;
    loadUsers(window.currentUsersPage + 1);
});
document.getElementById("users-limit")?.addEventListener("change", () => {
    loadUsers(1);
});
function checkIfFiltersActive() { 
    const floatingBtn = document.getElementById("floatingClearFilters"); 
    const searchInputVal = document.getElementById("tableSearchInput")?.value.trim() || "";
    if (floatingBtn) {
        floatingBtn.style.display = (filtersApplied || searchInputVal !== "") ? "flex" : "none";
    }
}
function applyFilters() {
    loadUsers(1);
}
$(document).on("click", "#floatingClearFilters", function () { 
    resetUsrddFilters(); 
    document.querySelectorAll("#filterModal input").forEach(input => input.value = ""); 
    resetCardFiltersUI();
    const searchInput = document.getElementById("tableSearchInput");
    if (searchInput) searchInput.value = "";
    applyFilters(); 
    filtersApplied = false; 
    checkIfFiltersActive(); 
    document.querySelector(".custom-backdrop")?.remove(); 
    document.body.classList.remove("modal-open"); 
});
/* ================= PASSWORD SHOW/HIDE ================= */
document.addEventListener("click", function (e) {
    const cell = e.target.closest(".password-cell");
    if (!cell) return;
    const mask = cell.querySelector(".password-mask");
    const text = cell.querySelector(".password-text");
    if (!mask || !text) return;
    if (text.style.display === "none") { text.style.display = "inline"; mask.style.display = "none"; } else { text.style.display = "none"; mask.style.display = "inline"; }
});
// --- FNF Logic ---
function openFnfModal(userId) {
    $.post('fnf_action.php', { action: 'get_fnf_details', user_id: userId }, function (res) {
        if (res.status === 'success') {
            const user = res.user; const settlement = res.settlement || {};
            window.currentDrawerUser = user; // Ensure openLetterModal has the user context
            $('#fnf_user_id').val(userId); $('#fnf_employee_name').text(user.username);
            $('#fnf_last_working_day').val(settlement.last_working_day || user.deactivated_at || new Date().toISOString().split('T')[0]);
            $('#fnf_status').val(settlement.status || 'Pending');
            $('#fnf_assets_returned').val(res.pending_assets > 0 ? 0 : 1);
            
            // Render Payslips
            const payslipsBody = $('#fnf_payslips_body');
            if (res.payslips && res.payslips.length > 0) {
                payslipsBody.empty();
                res.payslips.forEach(ps => {
                    payslipsBody.append(`
                        <tr>
                            <td class="ps-3 fw-semibold">${ps.month_year}</td>
                            <td>₹ ${parseFloat(ps.net_salary).toLocaleString('en-IN')}</td>
                            <td class="text-center">
                                <button type="button" onclick="previewPayslip('${ps.id}', '${ps.month_year}', 'payroll')" class="btn btn-xs btn-outline-primary py-0 px-2 me-1" style="font-size:10px;" title="Preview">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <button type="button" onclick="printPayslip('${ps.id}', '${ps.month_year}', 'payroll')" class="btn btn-xs btn-outline-secondary py-0 px-2" style="font-size:10px;" title="Print">
                                    <i class="bi bi-printer"></i><span class="d-none d-md-inline"> Print</span>
                                </button>
                            </td>
                        </tr>
                    `);
                });
            } else {
                payslipsBody.html('<tr><td colspan="3" class="text-center py-3 text-muted small">No past payslips found.</td></tr>');
            }
            ['no_dues_certificate', 'relieving_letter'].forEach(type => {
                fetch(`get_appointment_letter.php?user_id=${userId}&document_type=${type}`)
                    .then(res => res.json())
                    .then(res => {
                        const statusId = type === 'no_dues_certificate' ? 'fnf_no_dues_status' : 'fnf_relieving_status';
                        const btnMailId = type === 'no_dues_certificate' ? 'btn_fnf_mail_no_dues' : 'btn_fnf_mail_relieving';
                        const btnPrintId = type === 'no_dues_certificate' ? 'btn_fnf_print_no_dues' : 'btn_fnf_print_relieving';
                        const btnPreviewId = type === 'no_dues_certificate' ? 'btn_fnf_preview_no_dues' : 'btn_fnf_preview_relieving';
                        
                        const statusEl = document.getElementById(statusId);
                        const mailBtn = document.getElementById(btnMailId);
                        const printBtn = document.getElementById(btnPrintId);
                        const previewBtn = document.getElementById(btnPreviewId);

                        if (res.status === 'success' && res.data) {
                            statusEl.innerHTML = '<i class="bi bi-check-circle-fill text-success"></i> Generated';
                            statusEl.className = "info-value small text-success";
                            if (mailBtn) mailBtn.style.display = 'inline-block';
                            if (printBtn) printBtn.style.display = 'inline-block';
                            if (previewBtn) previewBtn.style.display = 'inline-block';
                        } else {
                            statusEl.innerHTML = 'Not Generated';
                            statusEl.className = "info-value small text-muted";
                            if (mailBtn) mailBtn.style.display = 'none';
                            if (printBtn) printBtn.style.display = 'none';
                            if (previewBtn) previewBtn.style.display = 'none';
                        }
                    });
            });

            const modal = new bootstrap.Modal(document.getElementById('fnfModal')); modal.show();
        } else { Swal.fire('Error', res.message, 'error'); }
    });
}
function saveFnf() {
    const formData = { action: 'save_fnf', user_id: $('#fnf_user_id').val(), last_working_day: $('#fnf_last_working_day').val(), unpaid_salary: 0, leave_encashment: 0, bonus_incentives: 0, deductions: 0, net_settlement: 0, status: $('#fnf_status').val(), assets_returned: $('#fnf_assets_returned').val() };
    $.post('fnf_action.php', formData, function (res) {
        if (res.status === 'success') { Swal.fire('Saved', 'FNF details updated successfully.', 'success'); }
        else { Swal.fire('Error', res.message || 'Failed to save FNF details.', 'error'); }
    }, 'json');
}

function sendFinalFnfMail() {
    const userId = $('#fnf_user_id').val();
    if (!userId) { Swal.fire('Error', 'User ID not found.', 'error'); return; }

    Swal.fire({
        title: 'Sending Final Mail',
        text: 'Generating documents and sending mail... Please wait.',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });

    $.post('mail_fnf_settlement.php', { user_id: userId }, function (res) {
        if (res.status === 'success') {
            Swal.fire('Success', res.message, 'success');
        } else {
            Swal.fire('Error', res.message || 'Failed to send mail.', 'error');
        }
    }, 'json').fail(function() {
        Swal.fire('Error', 'Server communication error.', 'error');
    });
}
/* ================= APPOINTMENT LETTER LOGIC ================= */
// Fix for Editor focus in Bootstrap Modal (prevents Bootstrap from trapping focus)
$(document).on('focusin', function (e) {
    if ($(e.target).closest(".tox-tinymce, .tox-tinymce-aux, .moxman-window, .tam-assetmanager-root, .note-editor, .note-editable").length) {
        e.stopImmediatePropagation();
    }
});

function upgradeLegacySignatures(htmlString) {
    if (!htmlString) return htmlString;
    try {
        const parser = new DOMParser();
        const doc = parser.parseFromString(htmlString, 'text/html');
        const imgs = doc.querySelectorAll('.signature-stamp');
        let modified = false;
        imgs.forEach(img => {
            const parent = img.parentElement;
            if (!parent || !parent.classList.contains('signature-container')) {
                const wrapper = doc.createElement('span');
                wrapper.className = 'signature-container';
                wrapper.style.position = 'relative';
                wrapper.style.display = 'inline-block';
                wrapper.style.width = '0';
                wrapper.style.height = '0';
                wrapper.style.verticalAlign = 'bottom';
                wrapper.style.overflow = 'visible';

                img.style.position = 'absolute';
                if (!img.style.left) img.style.left = '0px';
                if (!img.style.top && !img.style.bottom) img.style.bottom = '0px';

                img.parentNode.insertBefore(wrapper, img);
                wrapper.appendChild(img);
                modified = true;
            } else {
                parent.style.position = 'relative';
                parent.style.display = 'inline-block';
                parent.style.width = '0';
                parent.style.height = '0';
                parent.style.verticalAlign = 'bottom';
                parent.style.overflow = 'visible';

                img.style.position = 'absolute';
            }
        });
        if (!modified) {
            return htmlString;
        }
        /* DOMParser moves <style> into <head>; preserve it when re-serializing body */
        const styleTags = Array.from(doc.querySelectorAll('style'))
            .map((el) => el.outerHTML)
            .join('');
        return styleTags + doc.body.innerHTML;
    } catch (e) {
        console.error("Error upgrading signatures:", e);
    }
    return htmlString;
}

/** Sync Summernote model from the editable DOM without focus/insertNode side effects */
function syncLetterEditorFromEditable(editor) {
    const editable = editor.next('.note-editor').find('.note-editable')[0];
    if (!editable) return;
    $(editable).trigger('input');
    try {
        editor.summernote('saveRange');
    } catch (e) { /* ignore */ }
}

function stripSigPlaceholdersForOutput(html) {
    if (!html) return html;
    let out = html;
    const patterns = [
        /<div[^>]*\bsig-placeholder\b[^>]*>[\s\S]*?<\/div>/gi,
        /<span[^>]*\bsig-placeholder\b[^>]*>[\s\S]*?<\/span>/gi,
        /<p[^>]*>\s*\[CLICK\s*HE\s*<\/p>/gi,
        /<p[^>]*>\s*RE\s*TO\s*INSERT\s*SIGNATURE\]\s*<\/p>/gi,
        /\[?\s*CLICK\s*HERE\s*TO\s*INSERT\s*SIGNATURE\s*\]?/gi,
        /CLICK\s*HERE\s*TO\s*INSERT\s*SIGNATURE/gi,
        /\[CLICK\s*HE\s*RE\s*TO\s*INSERT\s*SIGNATURE\]/gi,
        /\[CLICK\s*HE\s*/gi,
        /RE\s*TO\s*INSERT\s*SIGNATURE\]\s*/gi,
        /\{\{\s*\w*_?signature\s*\}\}/gi,
        /\[\s*HR['’]?s?\s+Signature\s*\]/gi,
        /\[\s*HR\s+Partner\s+Signature\s*\]/gi,
        /\[\s*Manager['’]?s?\s+Signature\s*\]/gi,
        /\[\s*Manager\/Supervisor\s+Signature\s*\]/gi,
        /\[\s*Supervisor['’]?s?\s+Signature\s*\]/gi,
        /\[\s*Candidate['’]?s?\s+Signature\s*\]/gi,
        /\[\s*Recipient['’]?s?\s+Signature\s*\]/gi,
        /\[\s*Employee\/Candidate\s+Signature\s*\]/gi,
        /\[\s*Employee['’]?s?\s+Signature\s*\]/gi
    ];
    patterns.forEach((p) => { out = out.replace(p, ''); });
    return out;
}

/* Guard flag: when true, onBlur must NOT overwrite the saved range */
window._sigRangeLocked = false;

function persistLetterEditorRange() {
    /* If the range was explicitly locked (by clicking Add Signature), skip */
    if (window._sigRangeLocked) return;

    const editor = $('#appointmentLetterEditor');
    if (!editor.length || typeof editor.summernote !== 'function') return;
    try {
        editor.summernote('saveRange');
    } catch (e) {
        console.warn('saveRange failed', e);
    }
    const editable = editor.next('.note-editor').find('.note-editable')[0];
    if (!editable) return;
    const sel = window.getSelection();
    if (!sel || sel.rangeCount === 0) return;
    const anchor = sel.anchorNode;
    if (anchor && (editable === anchor || editable.contains(anchor))) {
        window.letterEditorSavedNativeRange = sel.getRangeAt(0).cloneRange();
    }
}

/** Call on mousedown before Add Signature — keeps cursor position before the button steals focus */
window.saveLetterEditorRange = function (e) {
    if (e && e.preventDefault) {
        e.preventDefault();
    }
    /* Save the current selection while the editor still has focus */
    window._sigRangeLocked = false;          // temporarily unlock so we can capture
    persistLetterEditorRange();
    window._sigRangeLocked = true;           // LOCK — prevents onBlur from overwriting
};

function insertSignatureHtmlAtCursor(editor, signatureHtml, role = 'cursor') {
    if (!editor.length || typeof editor.summernote !== 'function') return false;

    const editable = editor.next('.note-editor').find('.note-editable')[0];
    if (!editable) return false;

    // Parse the signature HTML to inject the unique ID and ensure styling
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = signatureHtml.trim();
    const imgNode = tempDiv.querySelector('img') || tempDiv.firstChild;

    let sigId = '';
    if (role === 'hr') sigId = 'sig-hr';
    else if (role === 'manager') sigId = 'sig-manager';
    else if (role === 'candidate') sigId = 'sig-candidate';
    else sigId = `sig-custom-${Date.now()}`;

    // 1. In-place Update: If role-based signature already exists, update its src
    if (role !== 'cursor') {
        const existingSig = editable.querySelector(`#${sigId}`);
        if (existingSig) {
            const newSrc = imgNode ? imgNode.getAttribute('src') : '';
            if (newSrc) {
                existingSig.setAttribute('src', newSrc);
                existingSig.setAttribute('alt', `${role.charAt(0).toUpperCase() + role.slice(1)} Signature`);
            }
            
            // Trigger change event so Summernote registers it without disturbing the template
            $(editable).trigger('input');
            return true;
        }
    }

    // Create wrapper container for zero-height absolute positioning
    const wrapper = document.createElement('span');
    wrapper.className = 'signature-container';
    wrapper.style.position = 'relative';
    wrapper.style.display = 'inline-block';
    wrapper.style.width = '0';
    wrapper.style.height = '0';
    wrapper.style.verticalAlign = 'bottom';
    wrapper.style.overflow = 'visible';

    if (imgNode && imgNode.nodeType === 1) {
        imgNode.setAttribute('id', sigId);
        imgNode.classList.add('signature-stamp');
        imgNode.style.position = 'absolute';
        imgNode.style.left = '0px';
        imgNode.style.bottom = '0px';
        imgNode.style.maxWidth = '220px';
        imgNode.style.height = 'auto';
        imgNode.style.zIndex = '10';
        imgNode.style.cursor = 'pointer';
        imgNode.setAttribute('alt', `${role.charAt(0).toUpperCase() + role.slice(1)} Signature`);
        
        wrapper.appendChild(imgNode);
    }

    // Helper to find a text node matching a regex
    const findTextNodeContainingRegex = (root, regex) => {
        const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, null, false);
        let node;
        while (node = walker.nextNode()) {
            if (regex.test(node.nodeValue)) {
                return node;
            }
        }
        return null;
    };

    // 2. Role-based Placement: Search for placeholders or fallbacks
    let insertionTarget = null;

    if (role !== 'cursor') {
        const roleTargets = {
            hr: {
                placeholders: [
                    /\{\{\s*hr_signature\s*\}\}/i,
                    /\[\s*HR['’]?s?\s+Signature\s*\]/i,
                    /\[\s*HR\s+Partner\s+Signature\s*\]/i
                ],
                fallbacks: ["Shivali V Rai", "HR Signature", "HR's Signature", "HR’s Signature", "Signature of HR"]
            },
            manager: {
                placeholders: [
                    /\{\{\s*manager_signature\s*\}\}/i,
                    /\[\s*Manager['’]?s?\s+Signature\s*\]/i,
                    /\[\s*Manager\/Supervisor\s+Signature\s*\]/i,
                    /\[\s*Supervisor['’]?s?\s+Signature\s*\]/i
                ],
                fallbacks: ["Supervisor Signature", "Supervisor's Signature", "Supervisor’s Signature", "Manager Signature", "Manager's Signature", "Manager’s Signature", "Reporting Manager", "Supervisor"]
            },
            candidate: {
                placeholders: [
                    /\{\{\s*candidate_signature\s*\}\}/i,
                    /\{\{\s*recipient_signature\s*\}\}/i,
                    /\[\s*Candidate['’]?s?\s+Signature\s*\]/i,
                    /\[\s*Recipient['’]?s?\s+Signature\s*\]/i,
                    /\[\s*Employee\/Candidate\s+Signature\s*\]/i,
                    /\[\s*Employee['’]?s?\s+Signature\s*\]/i
                ],
                fallbacks: [
                    "Employee Signature", "Employee's Signature", "Employee’s Signature",
                    "Candidate Signature", "Candidate's Signature", "Candidate’s Signature",
                    "Recipient Signature", "Recipient's Signature", "Recipient’s Signature",
                    "Signature of Candidate", "Signature of Employee", "Signature of Recipient"
                ]
            }
        };

        const targets = roleTargets[role];
        if (targets) {
            // A. Search for placeholder
            let foundPlaceholderNode = null;
            let matchedRegex = null;
            for (const regex of targets.placeholders) {
                foundPlaceholderNode = findTextNodeContainingRegex(editable, regex);
                if (foundPlaceholderNode) {
                    matchedRegex = regex;
                    break;
                }
            }

            if (foundPlaceholderNode && matchedRegex) {
                insertionTarget = {
                    type: 'placeholder',
                    node: foundPlaceholderNode,
                    regex: matchedRegex
                };
            }

            // B. If no placeholder, search for fallback keywords
            if (!insertionTarget) {
                let targetEl = null;
                for (let keyword of targets.fallbacks) {
                    const elements = $(editable).find(`*:contains("${keyword}")`);
                    if (elements.length > 0) {
                        let deepest = null;
                        elements.each(function() {
                            if (this.innerText && this.innerText.includes(keyword)) {
                                if (!deepest || this.contains(deepest)) {
                                    deepest = this;
                                }
                            }
                        });
                        if (deepest) {
                            targetEl = deepest;
                            break;
                        }
                    }
                }
                if (targetEl) {
                    insertionTarget = {
                        type: 'fallback',
                        node: targetEl
                    };
                }
            }
        }
    }

    // If role is cursor, or we couldn't find any placeholder/fallback, use the cursor
    if (!insertionTarget) {
        if (window.letterEditorSavedNativeRange) {
            insertionTarget = {
                type: 'cursor',
                range: window.letterEditorSavedNativeRange
            };
        } else {
            insertionTarget = {
                type: 'end'
            };
        }
    }

    /* DOM-only insertion — avoid summernote focus/insertNode which rewrites the letter layout */
    try {
        const sel = window.getSelection();

        if (insertionTarget.type === 'cursor') {
            editable.focus();
            sel.removeAllRanges();
            sel.addRange(insertionTarget.range);

            const cursorRange = insertionTarget.range;
            cursorRange.deleteContents();
            cursorRange.insertNode(wrapper);

            const afterRange = document.createRange();
            afterRange.setStartAfter(wrapper);
            afterRange.collapse(true);
            sel.removeAllRanges();
            sel.addRange(afterRange);

            window.letterEditorSavedNativeRange = null;
        } else if (insertionTarget.type === 'placeholder') {
            const textNode = insertionTarget.node;
            const regex = insertionTarget.regex;
            const val = textNode.nodeValue;
            const match = val.match(regex);
            if (!match) return false;
            const index = match.index;
            const matchedText = match[0];
            const afterNode = textNode.splitText(index);
            afterNode.splitText(matchedText.length);
            afterNode.parentNode.replaceChild(wrapper, afterNode);
        } else if (insertionTarget.type === 'fallback') {
            const targetEl = insertionTarget.node;
            if (targetEl && targetEl.parentNode) {
                targetEl.parentNode.insertBefore(wrapper, targetEl);
            } else {
                editable.appendChild(wrapper);
            }
        } else {
            editable.appendChild(wrapper);
        }

        syncLetterEditorFromEditable(editor);
        return true;
    } catch (err) {
        console.error("Signature insertion failed:", err);
        return false;
    }
}

function removeSigPlaceholdersFromEditor() {
    const editor = $('#appointmentLetterEditor');
    if (!editor.length || typeof editor.summernote !== 'function') return;
    const html = editor.summernote('code');
    const cleaned = stripSigPlaceholdersForOutput(html);
    if (cleaned !== html) {
        editor.summernote('code', cleaned);
    }
}

function convertNumberToIndianWords(num) {
    const single = ["", "One", "Two", "Three", "Four", "Five", "Six", "Seven", "Eight", "Nine", "Ten", "Eleven", "Twelve", "Thirteen", "Fourteen", "Fifteen", "Sixteen", "Seventeen", "Eighteen", "Nineteen"];
    const double = ["", "", "Twenty", "Thirty", "Forty", "Fifty", "Sixty", "Seventy", "Eighty", "Ninety"];
    
    function translate(n) {
        let str = "";
        if (n >= 100) {
            str += single[Math.floor(n / 100)] + " Hundred ";
            n = n % 100;
        }
        if (n >= 20) {
            str += double[Math.floor(n / 10)] + " ";
            n = n % 10;
        }
        if (n > 0) {
            str += single[n] + " ";
        }
        return str.trim();
    }
    
    let n = Math.round(num);
    if (n === 0) return "Zero";
    
    let words = "";
    
    const crores = Math.floor(n / 10000000);
    n = n % 10000000;
    if (crores > 0) {
        words += translate(crores) + " Crore ";
    }
    
    const lakhs = Math.floor(n / 100000);
    n = n % 100000;
    if (lakhs > 0) {
        words += translate(lakhs) + " Lakh ";
    }
    
    const thousands = Math.floor(n / 1000);
    n = n % 1000;
    if (thousands > 0) {
        words += translate(thousands) + " Thousand ";
    }
    
    if (n > 0) {
        words += translate(n);
    }
    
    return words.trim();
}

function getAppointmentLetterTemplate(user) {
    const today = new Date().toLocaleDateString('en-GB');
    const name = user.username || 'Employee Name';
    const address = "Address Placeholder";
    const doj = user.doj || 'Date of Joining';

    const monthlyCTC = parseFloat(user.salary) || 0;
    const yearlyCTC = monthlyCTC * 12;
    const yearlyInWords = convertNumberToIndianWords(yearlyCTC);

    // Check if manual structure exists
    const hasManual = (
        (user.first_amount && parseFloat(user.first_amount) > 0) ||
        (user.second_amount && parseFloat(user.second_amount) > 0) ||
        (user.third_amount && parseFloat(user.third_amount) > 0) ||
        (user.fourth_amount && parseFloat(user.fourth_amount) > 0) ||
        (user.fifth_amount && parseFloat(user.fifth_amount) > 0) ||
        (user.sixth_amount && parseFloat(user.sixth_amount) > 0)
    );

    const basic = hasManual ? (parseFloat(user.first_amount) || 0) : Math.round(monthlyCTC * 0.5);
    const pfEmployer = hasManual ? (parseFloat(user.fifth_amount) || 0) : Math.min(1800, Math.round(basic * 0.12));
    const hra = hasManual ? (parseFloat(user.second_amount) || 0) : Math.round(monthlyCTC * 0.2);
    const conveyance = hasManual ? (parseFloat(user.third_amount) || 0) : Math.round(monthlyCTC * 0.07);

    const monthlyGross = monthlyCTC - pfEmployer;
    const specialAllowance = hasManual ? (parseFloat(user.fourth_amount) || 0) : (monthlyGross - (basic + hra + conveyance));

    // For standard deductions split in the letter
    let pfEmployee = pfEmployer, pt = 200, medical = 817;
    let customDeds = 0;
    if (hasManual) {
        const totalD = parseFloat(user.sixth_amount) || 0;
        customDeds = totalD - (pfEmployee + pt + medical);
        if (customDeds < 0) customDeds = 0;
    }
    const totalDeduction = pfEmployee + pt + medical + customDeds;
    const netPay = monthlyGross - totalDeduction;

    return `
    <div style="font-family: Arial, sans-serif; line-height: 1.6; color: #000; padding: 20px; position: relative;">
        <!-- PAGE 1 -->
        <h2 style="text-align: center; font-weight: bold; text-decoration: underline;">Appointment Letter</h2>
        <p style="text-align: right;"><strong>Date:</strong> ${today}</p>
        
        <p><strong>To,</strong><br>${name}<br>${address}</p>
        <p><strong>Subject: Offer of Employment</strong></p>
        <p>Dear ${name},</p>
        <p>On behalf of Search Homes India Pvt Ltd (the Company) I am pleased to offer you the position of <strong>${user.user_type || 'Manager- Sales'}</strong> starting <strong>${doj}</strong>. We extend this offer, and the opportunity it represents, with great confidence in your abilities.</p>
        <p>The Firm reserves the right, to make reasonable changes to any of your terms of employment, which will be communicated to you in writing.</p>
        <p>Notwithstanding anything contained in this Employment Letter or otherwise, during the term of your employment, at the Employer’s sole discretion, you may be transferred to any other separate legal entity or associate company or a subsidiary or a wholly owned subsidiary that the Employer may deem fit and proper.</p>
        
        <h4>1. Location</h4>
        <p>Your initial place of posting will be Bangalore, Karnataka. However, the Company may require you to work at other company locations and at customer’s sites. The company reserves the right to transfer you on a temporary or permanent basis to other job functions, departments or locations within the Company when necessary.</p>
        
        <h4>2. Compensation</h4>
        <p>You will be entitled to a base salary of <strong>INR ${yearlyCTC.toLocaleString()}/- (Indian Rupees ${yearlyInWords} only)</strong> per annum and You will be subject to statutory and other deductions as per Company policies and practices. Break-up of the salary structure is provided in Annexure-A.</p>
        <p>You will be responsible for payment of your personal Income tax as per all applicable Indian tax laws.</p>
        <p>Please understand your salary is a strictly confidential agreement between yourself and the Employer. You are welcome to contact your business representative / HR manager for any clarifications / explanations. However, this should not be discussed with any of your colleagues. Any breach of this clause will be construed as a professional misconduct.</p>
        
        <hr style="border-top: 1px dashed #ccc; margin: 40px 0;">

        <!-- PAGE 2 -->
        <p>The Employer's policy on remuneration reviews is that they are conducted annually and are Discretionary. You will be paid monthly on or around the last working day of each month or as Determined, for the period covering the salary cycle.</p>

        <h4>3. Probation and Confirmation</h4>
        <p>You will be initially on probation for a period of 90 days, during which you may be removed from your appointed post without any notice or reason if your performance is not found satisfactory as per the reviews. During the first 60 days of probation, you are expected to achieve a minimum of 2 closures/bookings. Upon successful completion of the probation period and satisfactory performance, you will be issued an appointment-cum-confirmation letter.</p>

        <h4>4. Notice period</h4>
        <p>There will be 15 days of advance notice period in case you opt to depart from the organization. A notice of 15 days is required during your employment with the company by either party to terminate his contract. Notice period is considered to start from the point of resignation letter is received by the manager. Also, if situations warrant, as in the case of breach of policies, poor performance or inappropriate behavior the company may decide to terminate the contract with immediate effect. In case of failing to serve the notice period or termination, the company reserves all rights to withhold any form of remuneration indefinitely. Leave Policy: No Leaves will be provided during training and probation for 90 days from Date of Joining.</p>

        <h4>5. Full and Final Settlement (FNF)</h4>
        <p>Any employee wishing to resign must communicate his intent in writing for acceptance by Management. On acceptance of resignation, And after serving notice period by employee. FNE (includes the employee's unpaid salary only), shall be credited to the respective Employees Bank account within 30 to 45 days after relieving.</p>

        <h4>6. Hours of Work</h4>
        <p>Your normal hours of work will be approximately 9 hours. Due to work exigencies of work. you may be required to work beyond normal hours for which you will not be paid any overtime.</p>

        <h4>7. Annual Leave and Holidays</h4>
        <p>You will be entitled for accrual of One Casual leave every month you work. Additionally, you will be entitled for 6 Privilege Leaves & 6 Sick Leaves for the calendar year to be accrued on a pro-rata basis. You are encouraged to refer to Leave Policy for more details.</p>
        <p>You will be entitled to holidays as declared by the firm every year and the same will be displayed in Qandle.</p>

        <h4>8. Termination of Employment</h4>
        <p>This appointment is subject to Two Months notice in writing by either party subject to the following additional obligations where termination takes place in the following:</p>

        <hr style="border-top: 1px dashed #ccc; margin: 40px 0;">

        <!-- PAGE 3 -->
        <h5>8.1 Termination of your employment by the firm</h5>
        <p>8.1 Upon confirmation of your employment, either party may terminate the employment by providing a written notice for the duration decided at the time of confirmation. In the event of termination initiated by the Company, the notice period determined by the Company shall be final and binding. However, if the employment is terminated by the Company during the probation period, no notice period will be applicable. Notwithstanding the foregoing, the Company reserves the right to terminate employment without notice or compensation for any act of misconduct, breach of policy, or non-performance, if you</p>
        <p style="padding-left: 20px;">(i) Engage in misconduct related to the Company or your employment, including but not limited to any breach of the terms of this offer letter; or (ii) are convicted for any criminal offense during the tenure of your service with the Company by a court of law, the Company may terminate your employment immediately, without any prior notice and without payment of any additional amounts. The termination will not affect the rights and remedies that the Company may have under any laws, rules and regulations for the time being in force.</p>
        <p>For the purpose of this Agreement, the term "Misconduct" shall include bit shall not be limited to the following:</p>
        <p style="padding-left: 20px;">(a) willful default by the Employee in the performance of his duties;<br>
        (b) bankruptcy of the Employee; the commission by the Employee of an act of fraud relating to the Company and its business;<br>
        (c) any crime by the Employee that has a material adverse effect on the Company and its business, including, in the course of his employment, or misappropriation of the assets of the Company, and/or<br>
        (d) the Employee's willful violation of the policies of the Company, as in effect from time-to-time;</p>

        <h5>8.2 Upon termination</h5>
        <p>Upon termination, you will immediately return to the Company any and all documents, manuals, data, records, confidential information, intellectual property, material, equipment and other property belonging to the Company that may be entrusted to and/or placed in your possession or control by virtue of and/or during the course of your employment with the Company, without making any copies thereof and/or extracts there from. You will also deliver to the Company immediately all notes, analysis, summaries and working papers relating thereto. The Company will settle your dues, if any, and issue a relieving letter to you only upon your compliance with the terms of this Clause</p>

        <h5>8.3 Salary during probation termination</h5>
        <p>If an employee resigns or is terminated during the probation period, salary for that period will not be paid.</p>

        <h4>9. Obligations of Employee</h4>
        <p>9.1 You will abide by all Company's rules, regulations, policies and procedures framed by the Company from time to time and applicable to your position, which rules, regulations, policies and procedures shall be deemed to be a part of this offer letter as if they are specifically incorporated in</p>

        <hr style="border-top: 1px dashed #ccc; margin: 40px 0;">

        <!-- PAGE 4 -->
        <p>this offer letter, Such rules, regulations may include without limitation matters of attendance, conduct, behavior, discipline, working hours, leave, holidays and other applicable benefits. You will take steps to be aware of the Company's rules, regulations, policies and procedures and ignorance of any of them shall not excuse any contravention of the terms of this offer letter.</p>
        <p>9.2 During the period of your employment with the Company, you will exclusively serve the Company.</p>
        <p>9.3 You will not engage or become interested, directly or indirectly, without prior written consent of the Company in that behalf, with or without remuneration, in any trade, business, occupation, employment, service or calling whatsoever nor will undertake any activities which are or will be contrary to or conflict with interests of the Company and/or your duties and obligations hereunder; and shall perform your duties and responsibilities with diligence and devotion and shall direct your best efforts to promote the interests of the Company and its operations and all the activities to the extent permitted by law.</p>
        <p>9.4 During the term of your employment with the Company and thereafter, you shall not (a) solicit for a competitor of the Company or attempt to gain the business of the Company for a competitor of the Company, or for yourself or any other purpose or reason, any customer of the Company that you solicited or served or about which you learned confidential information during your employment with the Company, or (b) solicit or encourage, or cause others to solicit or encourage, any employees or consultants, or collaborators of the Company to terminate their employment or engagement with the Company.</p>
        <p>9.5 This employment is offered to you upon the understanding and is conditional upon (i) the credentials; testimonials and particulars submitted by you with or in your application for employment being true, correct and accurate, and (ii) satisfactory verification of your background by the Company in a manner as it deem fits. If at any time it should emerge that the particulars furnished by you are false/incorrect or if any material or relevant information has been suppressed or concealed or the result of the background investigation and verification of documents/information is not satisfactory in the sole opinion of the Company, then notwithstanding your acceptance of this offer letter, this offer will be considered ineffective and irregular and would be liable to be terminated by the Company forthwith without notice and without payment of any compensation, whatsoever. This termination will not affect the rights and remedies that the Company may have under any laws, rules and regulations for the time being in force.</p>

        <hr style="border-top: 1px dashed #ccc; margin: 40px 0;">

        <!-- PAGE 5 -->
        <h4>10. Restrictions after termination</h4>
        <h5>10.1 Confidentiality</h5>
        <p>You shall not, during the course of your employment or at any time thereafter, disclose or use for your own benefit any confidential or proprietary information of the Company, including but not limited to client lists, project details, developer agreements, pricing structures, marketing strategies, and any other business information obtained during your employment, except as may be required in the proper performance of your duties or as required by law.</p>
        
        <h5>10.2 Non-Compete</h5>
        <p>For a period of six (6) months following the termination of your employment (for any reason), you shall not, directly or indirectly, engage, associate, or be concerned in any capacity (whether as an employee, consultant, agent, partner, or otherwise) with any real estate brokerage, channel partner, or marketing firm that competes with the Company in the marketing or sale of real estate projects within the Bangalore where you were last employed or assigned.</p>

        <h5>10.3 Non-Solicitation</h5>
        <p>For a period of twelve (12) months after the termination of your employment, you shall not, directly or indirectly:<br>
        a) solicit, approach, or deal with any developer, builder, or client (individual or corporate) of the Company with whom you had business interactions during your employment; or<br>
        b) induce or attempt to induce any employee, consultant, or agent of the Company to terminate their engagement with the Company or to join any competing business.</p>

        <h5>10.4 Return of Company Property</h5>
        <p>Upon termination of your employment, you shall immediately return all Company property, including marketing materials, databases, client contacts, documents, equipment, access cards, and any other materials or information belonging to the Company, whether in physical or electronic form.</p>

        <h5>10.5 Survival and Enforcement</h5>
        <p>The obligations set forth in this clause shall survive the termination of your employment for the periods mentioned herein. The Company reserves the right to seek appropriate legal or equitable remedies, including injunctions, to prevent or remedy any breach of these obligations.</p>

        <h4>11. Firm Property</h4>
        <p>In order to perform your duties on behalf of Search Homes India Pvt. Ltd., you may be supplied with property and information, which belongs to the Firm. On termination of your employment, you will immediately return all property and information properly belonging to the Firm, which was supplied to you.</p>
        <p>Your appointment is subject to the enclosed undertaking regarding confidential information and occupations in conflict with the Firm's interest.</p>

        <h4>Firm Policies</h4>
        <p>It is an essential condition of your employment that you must comply with all existing, reviewed and new Firm policies and procedures. Any breach of Firm policies or procedures may lead to disciplinary action.</p>

        <h4>IT Policy</h4>
        <p>The Firm has an IT Policy, which covers the acceptable use of these systems, which you may be required to access at some stage in the course of your employment with Search Homes India Pvt. Ltd.</p>

        <hr style="border-top: 1px dashed #ccc; margin: 40px 0;">

        <!-- PAGE 6 -->
        <h4 style="font-weight: bold;">Sexual Harassment</h4>
        <p>It is Search Homes India Pvt. Ltd. Policy to prohibit in our workplace any conduct, which constitutes sexual harassment. It guarantees to deal with allegations of harassment seriously, promptly and in confidence and undertakes to protect from victimization of those employees who complain about sexualharassment.</p>
        
        <h4 style="font-weight: bold;">Key Result Area:</h4>
        <p>• Ensure Business productivity of minimum 10X/month Including RM’s CTC.</p>
        <p>• Ensure Collection from Builder on time basis to manage operation.</p>
        <p>• Ensure recruitment on time basis as per budgeted manpower across cities with help of HR team</p>
        <p>• Maintain Good relationship with builder for smooth operation</p>
        <p>• Yearly achievement net business of YTD revenue after cancellation.</p>
        <p>• Performance appraisal will happen every 12 month once starting from joining month completion of 12 month based on achievement of KRA.</p>
        <p>• Incentive payouts shall be processed annually, subject to the applicable policies and the discretion of the management.</p>
        <p><strong>Note:</strong> The details of the incentive scheme and applicable performance targets will be communicated to you separately via email by the Business Head or HR Department. Incentive payouts shall be made based on the achievement of the targets as specified in the said email. The scheme and targets communicated therein shall be applicable to you as per company policy.</p>
        
        <h4 style="font-weight: bold;">Severance</h4>
        <p>If any provision of this contract of employment is declared or determined to be illegal or invalid by final determination of any court or tribunal of competent jurisdiction, the validity of the remaining</p>
        <p>Parts, terms or provisions of this contract shall not be affected, and the illegal or invalid part, term or provision shall be deemed not to be part of this contract.</p>
        <p>General you will be required to apply yourself wholly to the Firmness business and no work is to be undertaken in a private capacity which conflicts with that of the Firms.</p>
        <p>In the event of any disagreement over the interpretation of the above, the decision of the Directors will be final.</p>

        <hr style="border-top: 1px dashed #ccc; margin: 40px 0;">

        <!-- PAGE 7 -->
        <h4 style="font-weight: bold; text-align: center;">Background Verification</h4>
        <p>Validity of this offer is subject to positive clearance of the Background Verification Process carried out by Search Homes India Pvt. Ltd.</p>
        <p>If the terms and conditions in this contract are acceptable to you, please sign and return this contract to us. On behalf of Search Homes India Pvt. Ltd., Congratulations on your new role.</p>
        
        <br>
        <p>Sincerely,</p>
        <p><strong>For Search Homes India Pvt Ltd</strong></p>
        <br><br>
        <p><strong>Shivali V Rai</strong><br>Senior HR Executive</p>
        <hr style="border-top: 1px dashed #ccc; margin: 40px 0;">

        <!-- PAGE 8 -->
        <h3 style="text-align: center; font-weight: bold; text-decoration: underline;">Acceptance</h3>
        <p>I, ${name}, hereby accept the terms and conditions of this employment offer. The following documents have been attached for your records or shall be provided to the Company on the date of joining.</p>
        <p>Copy of PAN card</p>
        <p>Copy of Educational Certificates</p>
        <br><br>
        <p>______________________</p>
        <p>Please sign and date your acceptance</p>

        <hr style="border-top: 1px dashed #ccc; margin: 40px 0;">
        
        <!-- PAGE 9 (ANNEXURE-A) -->
        <h3 style="text-align: center;">ANNEXURE- A</h3>
        <p style="text-align: center;"><strong>${name}</strong></p>
        <table border="1" cellpadding="8" cellspacing="0" style="width: 100%; border-collapse: collapse;">
            <tbody>
                <tr style="background-color: #f2f2f2; font-weight: bold;"><td>CTC</td><td>Monthly (₹)</td><td>Yearly (₹)</td></tr>
                <tr><td><strong>Gross CTC</strong></td><td>${monthlyCTC.toLocaleString()}</td><td>${yearlyCTC.toLocaleString()}</td></tr>
                <tr style="background-color: #f9f9f9;"><td colspan="3"><strong>Earnings</strong></td></tr>
                <tr><td>Basic</td><td>${basic.toLocaleString()}</td><td>${(basic * 12).toLocaleString()}</td></tr>
                <tr><td>HRA</td><td>${hra.toLocaleString()}</td><td>${(hra * 12).toLocaleString()}</td></tr>
                <tr><td>Conveyance Allowance</td><td>${conveyance.toLocaleString()}</td><td>${(conveyance * 12).toLocaleString()}</td></tr>
                <tr><td>Special Allowance</td><td>${specialAllowance.toLocaleString()}</td><td>${(specialAllowance * 12).toLocaleString()}</td></tr>
                <tr style="background-color: #f9f9f9;"><td colspan="3"><strong>Statutory Benefit (Employer)</strong></td></tr>
                <tr><td>PF (Employer Part)</td><td>${pfEmployer.toLocaleString()}</td><td>${(pfEmployer * 12).toLocaleString()}</td></tr>
                <tr style="background-color: #e6f7ff; font-weight: bold;"><td>Monthly Gross</td><td>${monthlyGross.toLocaleString()}</td><td>${(monthlyGross * 12).toLocaleString()}</td></tr>
                <tr style="background-color: #f9f9f9;"><td colspan="3"><strong>Deductions (Employee)</strong></td></tr>
                <tr><td>PF (Employee Part)</td><td>${pfEmployee.toLocaleString()}</td><td>${(pfEmployee * 12).toLocaleString()}</td></tr>
                <tr><td>PT</td><td>${pt.toLocaleString()}</td><td>${(pt * 12).toLocaleString()}</td></tr>
                <tr><td>Medical Benefit</td><td>${medical.toLocaleString()}</td><td>${(medical * 12).toLocaleString()}</td></tr>
                <tr style="background-color: #d4edda; font-weight: bold;"><td>Net Pay</td><td>${netPay.toLocaleString()}</td><td>${(netPay * 12).toLocaleString()}</td></tr>
            </tbody>
        </table>
        <br>
        <p><strong>Note: 1) Income Tax will be deducted as per the provision of Income Tax act 1961</strong></p>

        <hr style="border-top: 1px dashed #ccc; margin: 40px 0;">

        <!-- PAGE 10 -->
        <h3 style="text-align: center; font-weight: bold; text-decoration: underline;">Search Homes India Pvt Ltd Code of Conduct</h3>
        <br>
        <p><strong>1. Integrity and Honesty:</strong></p>
        <ul>
            <li>All employees must act with integrity and honesty in all their dealings, both within the company and with external stakeholders.</li>
            <li>Misrepresentation, dishonesty, fraud, or any form of unethical behavior will not be tolerated. Employee will be Direct Terminated</li>
            <li>Never using their authority for personal gain for themselves or their immediate family or friends.</li>
        </ul>

        <p><strong>2. Respect and Diversity:</strong></p>
        <ul>
            <li>We value diversity and treat all individuals with respect, regardless of their race, ethnicity, gender, religion, sexual orientation, age, disability, or any other characteristic.</li>
            <li>Discrimination, harassment, or any form of disrespectful behavior is strictly prohibited.</li>
        </ul>

        <p><strong>3. Confidentiality:</strong></p>
        <ul>
            <li>Employees must respect the confidentiality of company information, as well as any sensitive information entrusted to them by clients, partners, or colleagues.</li>
            <li>Disclosure of confidential information without proper authorization is strictly prohibited.</li>
        </ul>

        <p><strong>4. Compliance with Laws and Regulations:</strong></p>
        <ul>
            <li>All employees must comply with applicable laws, regulations, and company policies in the conduct of their duties.</li>
            <li>Any illegal activities or violations of regulations will not be tolerated.</li>
        </ul>

        <p><strong>5. Conflict of Interest:</strong></p>
        <ul>
            <li>Employees must avoid situations where their personal interests conflict with the interests of the company.</li>
            <li>Any actual or potential conflicts of interest must be disclosed promptly and managed appropriately.</li>
            <li>Employees must avoid situations in which their private interests conflict or might reasonably be thought to conflict with their Company duties. Any personal interest that may affect or be seen by others to affect their impartiality should be declared to</li>
            <li>Their immediate supervisor and Head of the Department who will direct that employee not to perform that duty.</li>
        </ul>

        <hr style="border-top: 1px dashed #ccc; margin: 40px 0;">

        <!-- PAGE 11 -->
        <ul>
            <li>Employees must not solicit or accept from any person any remuneration, benefit, advantage or promise of further advantage whether for themselves, their immediate family, or any business concern or trust with which they are associated.</li>
        </ul>

        <p><strong>6. Professionalism:</strong></p>
        <ul>
            <li>Employees are expected to conduct themselves in a professional manner at all times, both within the workplace and when representing the company externally.</li>
            <li>This includes maintaining a positive attitude, being punctual, and communicating effectively.</li>
        </ul>

        <p><strong>7. Environmental and Social Responsibility:</strong></p>
        <ul>
            <li>We are committed to minimizing our environmental impact and contributing positively to the communities in which we operate.</li>
            <li>Employees are encouraged to participate in corporate social responsibility initiatives and to act in an environmentally responsible manner.</li>
        </ul>

        <p><strong>8. Safety and Well-being:</strong></p>
        <ul>
            <li>The safety and well-being of our employees, clients, and partners are of paramount importance.</li>
            <li>Employees must adhere to all safety protocols and procedures, and report any unsafe conditions or incidents promptly.</li>
        </ul>

        <p><strong>9. Reporting Violations:</strong></p>
        <ul>
            <li>Employees who become aware of any violations of this code of conduct are encouraged to report them promptly to their supervisor, human resources, or the appropriate authority.</li>
            <li>Retaliation against individuals who report violations in good faith is strictly prohibited.</li>
        </ul>

        <p><strong>10. Enforcement:</strong></p>
        <ul>
            <li>Violations of this code of conduct may result in disciplinary action, up to and including termination of employment.</li>
            <li>The company reserves the right to amend or update this code of conduct as necessary, and employees are expected to familiarize themselves with any changes.</li>
            <li>Employees are expected to work to exceed the following code of ethics and principles. They should seek the commitment of their supervisor/manager in implementing the code and should seek to achieve widespread acceptance of the code amongst fellow employees. Employees should raise any matter of concern of an ethical nature with their immediate supervisor/manager or another senior colleague, irrespective of whether it is explicitly mentioned in the code.</li>
        </ul>

        <hr style="border-top: 1px dashed #ccc; margin: 40px 0;">

        <!-- PAGE 12 -->
        <h3 style="text-align: center; font-weight: bold; text-decoration: underline;">Acknowledgment:</h3>
        <br>
        <p>I acknowledge that I have received, read, and understand the Search Homes India Pvt Ltd Code of Conduct. I agree to comply with its provisions and understand that violations may result in disciplinary action, up to and including termination of employment.</p>
        <br><br>
        <p>Employee Signature: ______________________ &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Date: _________________</p>
        <br><br>
        <p>Supervisor Signature (for acknowledgment): _________________________</p>
        <br>
        <p>Date: ______________</p>

        <hr style="border-top: 1px dashed #ccc; margin: 40px 0;">

        <!-- PAGE 13 -->
        <h3 style="text-align: center; font-weight: bold;">Search Homes India Pvt Ltd</h3>
        <h4 style="text-align: center; font-weight: bold;">(CONFIDENTIALITY AND NON-DISCLOSURE)</h4>
        <br>
        <p style="text-align: center;"><strong>Search Homes India Pvt Ltd DND agreement with ${name}</strong></p>
        <br>
        <p><strong>1. Purpose:</strong> The parties are discussing a potential business relationship concerning certain information that the Disclosing Party considers confidential.</p>
        
        <p><strong>2. Definition of Confidential Information:</strong> "Confidential Information" refers to any data or information, oral or written, disclosed by the Disclosing Party to the Recipient, including, but not limited to, information relating to the Disclosing Party’s products, processes, trade secrets, know-how, designs, pricing, customers, business plans, marketing strategies, financial information, Business Leads and any other Discussion information related to Company marked as "<strong>confidential</strong>" or which should reasonably be considered confidential.</p>
        
        <p><strong>3. Acknowledgement</strong> that, in your capacity as <strong>${user.user_type || 'Analyst - Digital Marketing'}</strong> and an employee of the Company, you will have access to Confidential Information. You undertake to hold such Confidential Information in a fiduciary capacity for the benefit of the Company. Further, you undertake to observe the strictest secrecy in all matters pertaining to the Company, its clients, customers, suppliers, vendors, associated companies, and not to divulge or disclose at any time Confidential Information received as an employee of the Company to any unauthorized person during or after your employment. <strong>The Company prohibits the use of Confidential Information for your own benefit or for the benefit of any other person, firm or entity. This includes not divulging Confidential Information unless you are sure of their right to receive it. To clarify, you shall not disclose it to, or permit its use by, any other party including any other 3rd party</strong></p>
        
        <p><strong>4. Return of Confidential Information:</strong> When so requested by the Company and in any case upon the termination of your employment, you will immediately return to the Company or at the Company’s request, destroy all Confidential Information in your possession or control, together with all copies, summaries and analyses, regardless of the format in which the information exists or is stored. In case of destruction, you will immediately send a written certification to the Company confirming that destruction has been accomplished to the Disclosing Party.</p>
        
        <p><strong>5. Data Protection & Security:</strong> Your appointment is being made on the basis of the information and details given by you. We generally rely on personal data provided by you. In order to ensure that your personal data is current, complete and accurate, please update us if there are changes to your personal data by informing our HR Department in writing or via email within seven working days. If, at any time any information or detail given by you is found to be incorrect or inaccurate or false, the Company may terminate your services without any notice, salary in lieu of notice or compensation.</p>

        <hr style="border-top: 1px dashed #ccc; margin: 40px 0;">

        <!-- PAGE 14 -->
        <p>Any breach of the obligation as set out in this clause may, in particular, lead to the immediate termination of your employment, without notice or payment in lieu thereof.</p>
        <br>
        <p><strong>Search Homes India Pvt Ltd</strong>, No 280 3rd Floors,<br>5th Main Rd, 6th Sector, HSR Layout,<br>Bangalore, Karnataka 560102</p>
        <br>
        <p>Name: <strong>Shivali V Rai</strong></p>
        <p>Title: <strong>Senior HR Executive</strong></p>
        <p>Date: ______________________</p>
        <br><br>
        <p><strong>[Recipient’s Name]</strong><br><strong>[Recipient’s Signature]</strong></p>
        <br>
        <p>Name: ______________________</p>
        <p>Title: ______________________</p>
        <p>Date: ______________________</p>
    </div>
    `;
}

function getOfferLetterLogoSrc() {
    if (typeof window.HR_COMPANY_LOGO_URL === 'string' && window.HR_COMPANY_LOGO_URL) {
        return window.HR_COMPANY_LOGO_URL;
    }
    return '../superadmin/assets/dataimage/hlogo.png';
}

function getOfferLetterLogoImg(watermark) {
    const src = getOfferLetterLogoSrc();
    if (watermark) {
        return `<img src="${src}" alt="" class="offer-letter-logo offer-letter-logo--watermark">`;
    }
    return `<img src="${src}" alt="Search Homes India" class="offer-letter-logo">`;
}

/** Restore branded offer letter shell when a saved copy lost header/footer after signature edits */
function ensureOfferLetterEditorContent(content, template) {
    if (!content || !template) return content || template;
    if (!content.includes('offer-letter-doc')) {
        return stripSigPlaceholdersForOutput(template);
    }
    try {
        const parser = new DOMParser();
        const contentDoc = parser.parseFromString(content, 'text/html');
        const templateDoc = parser.parseFromString(template, 'text/html');
        const contentRoot = contentDoc.querySelector('.offer-letter-doc');
        const templateRoot = templateDoc.querySelector('.offer-letter-doc');
        if (!contentRoot || !templateRoot) {
            return stripSigPlaceholdersForOutput(content);
        }
        const hasHeader = !!contentRoot.querySelector('.header-fixed');
        const sigs = contentDoc.querySelectorAll('.signature-stamp');
        if (hasHeader || sigs.length === 0) {
            return stripSigPlaceholdersForOutput(content);
        }
        sigs.forEach((sig) => {
            const id = sig.getAttribute('id');
            const src = sig.getAttribute('src');
            if (!src || !id) return;
            const target = templateRoot.querySelector('#' + id);
            if (target) {
                target.setAttribute('src', src);
                target.classList.add('signature-stamp');
            }
        });
        const sigContainers = contentRoot.querySelectorAll('.signature-container');
        if (sigContainers.length > 0) {
            const warmRegards = Array.from(templateRoot.querySelectorAll('p')).find((p) =>
                /warm regards/i.test(p.textContent || '')
            );
            const anchor = warmRegards || templateRoot.querySelector('.page-content');
            if (anchor && anchor.parentNode) {
                sigContainers.forEach((container) => {
                    if (!templateRoot.contains(container)) {
                        anchor.parentNode.insertBefore(container.cloneNode(true), anchor.nextSibling);
                    }
                });
            }
        }
        const styleTags = Array.from(contentDoc.querySelectorAll('style'))
            .map((el) => el.outerHTML)
            .join('');
        return stripSigPlaceholdersForOutput(styleTags + templateRoot.outerHTML);
    } catch (e) {
        console.warn('ensureOfferLetterEditorContent failed', e);
        return stripSigPlaceholdersForOutput(content);
    }
}

function getOfferLetterLayoutStyles() {
    return `
    <style>
        .offer-letter-doc { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.55; color: #333; position: relative; padding-top: 130px; padding-bottom: 150px; }
        .offer-letter-doc .header-fixed { position: absolute; top: 0; left: 0; width: 100%; z-index: 10; background: #fff; }
        .offer-letter-doc .footer-fixed { position: absolute; bottom: 0; left: 0; width: 100%; z-index: 10; background: #fff; height: 130px; }
        .offer-letter-doc .offer-letter-logo { max-height: 58px; width: auto; display: block; }
        .offer-letter-doc .offer-letter-logo--watermark { max-width: 420px; max-height: none; width: 70%; margin: 0 auto; opacity: 0.08; }
        .offer-letter-doc .company-info { text-align: right; font-size: 11px; color: #333; line-height: 1.7; font-weight: 600; }
        .offer-letter-doc .header-border { height: 2px; background: linear-gradient(to right, #115b82 0%, #115b82 75%, #f5a623 75%, #f5a623 88%, #e63946 88%, #e63946 100%); margin-top: 8px; }
        .offer-letter-doc .letter-title { text-align: center; font-size: 17px; font-weight: 800; text-decoration: underline; margin: 18px 0 22px; color: #115b82; }
        .offer-letter-doc .content-body { font-size: 13px; text-align: justify; }
        .offer-letter-doc .content-body p { margin-bottom: 12px; }
        .offer-letter-doc .salary-table { width: 100%; border-collapse: collapse; font-size: 12px; margin-top: 12px; }
        .offer-letter-doc .salary-table th, .offer-letter-doc .salary-table td { border: 1px solid #ddd; padding: 8px 10px; text-align: center; }
        .offer-letter-doc .salary-table td:first-child { text-align: left; font-weight: 600; }
        .offer-letter-doc .salary-table .category-row, .offer-letter-doc .salary-table .total-row { background: #004d80; color: #fff; font-weight: 700; }
        .offer-letter-doc .salary-table .category-row td { text-align: left; }
        .offer-letter-doc .footer-title { margin: 0; font-size: 20px; color: #222; font-weight: 800; }
        .offer-letter-doc .footer-address { margin: 2px 0 8px; font-size: 12px; color: #444; font-weight: 600; }
        .offer-letter-doc .footer-bottom-bar { background: #115b82; color: #fff; font-size: 10px; font-weight: 700; text-align: center; padding: 8px 12px; }
        .offer-letter-doc .letter-layout-table { width: 100%; border-collapse: collapse; }
        .offer-letter-doc .letter-layout-table td { border: none; vertical-align: top; }
        @media print {
            .offer-letter-doc { padding-top: 0; padding-bottom: 0; }
            .offer-letter-doc .header-fixed { position: fixed; top: 0; left: 0; width: 100%; background: #fff; z-index: 1000; padding: 0 40px; box-sizing: border-box; }
            .offer-letter-doc .footer-fixed { position: fixed; bottom: 0; left: 0; width: 100%; background: #fff; z-index: 1000; height: 130px; }
            .offer-letter-doc .letter-watermark { position: fixed; top: 38%; left: 0; width: 100%; text-align: center; z-index: -1; opacity: 0.08; }
            .offer-letter-doc thead { display: table-header-group; }
            .offer-letter-doc tfoot { display: table-footer-group; }
            .offer-letter-doc .header-space { height: 130px; }
            .offer-letter-doc .footer-space { height: 140px; }
        }
    </style>`;
}

function getOfferLetterHeaderHtml() {
    return `
    <div class="header-fixed">
        <table style="width:100%; border-collapse:collapse; margin-top:12px;">
            <tr>
                <td style="vertical-align:bottom; border:none;">
                    ${getOfferLetterLogoImg(false)}
                </td>
                <td class="company-info" style="vertical-align:bottom; border:none;">
                    <span style="color:#115b82">&#9742;</span> +91 63600 16650<br>
                    <span style="color:#115b82">&#9993;</span> contact@searchhomesindia.com<br>
                    <span style="color:#115b82">&#127760;</span> www.searchhomesindia.com
                </td>
            </tr>
        </table>
        <div class="header-border"></div>
    </div>`;
}

function getOfferLetterFooterHtml() {
    return `
    <div class="footer-fixed">
        <svg width="100%" height="90" viewBox="0 0 1000 90" preserveAspectRatio="none" style="position:absolute; bottom:35px; left:0;">
            <path d="M0,90 L0,40 Q80,60 150,90 Z" fill="#115b82"/>
            <path d="M0,90 L0,70 Q60,85 110,90 Z" fill="#20a163"/>
            <path d="M1000,90 L1000,20 Q880,60 750,90 Z" fill="#20a163"/>
            <path d="M1000,90 L1000,50 Q900,80 820,90 Z" fill="#115b82"/>
            <path d="M1000,90 L1000,85 Q940,100 890,90 Z" fill="#e63946"/>
            <path d="M1000,90 L1000,105 Q965,115 930,90 Z" fill="#f5a623"/>
        </svg>
        <div style="text-align:center; position:relative; z-index:2; padding-bottom:42px;">
            <h2 class="footer-title">Search Homes India Pvt. Ltd.</h2>
            <p class="footer-address">No 280, 3rd Floor, 5th Main Rd, 6th Sector, HSR Layout Bengaluru, Karnataka 560102</p>
        </div>
        <div class="footer-bottom-bar">
            &bull; CIN: U70109KA2015PTC084843 &nbsp;&nbsp;&nbsp; &bull; GSTIN: 29AAWCS6824M1Z9
        </div>
    </div>`;
}

function getOfferLetterTemplate(user) {
    const today = new Date().toLocaleDateString('en-GB');
    const name = user.username || 'Employee Name';
    const position = user.user_type || 'General Manager- Sales';

    const monthlyCTC = parseFloat(user.salary) || 0;
    const yearlyCTC = monthlyCTC * 12;

    // Check if manual structure exists
    const hasManual = (
        (user.first_amount && parseFloat(user.first_amount) > 0) ||
        (user.second_amount && parseFloat(user.second_amount) > 0) ||
        (user.third_amount && parseFloat(user.third_amount) > 0) ||
        (user.fourth_amount && parseFloat(user.fourth_amount) > 0) ||
        (user.fifth_amount && parseFloat(user.fifth_amount) > 0) ||
        (user.sixth_amount && parseFloat(user.sixth_amount) > 0)
    );

    const basic = hasManual ? (parseFloat(user.first_amount) || 0) : Math.round(monthlyCTC * 0.5);
    const pfEmployer = hasManual ? (parseFloat(user.fifth_amount) || 0) : Math.min(1800, Math.round(basic * 0.12));
    const hra = hasManual ? (parseFloat(user.second_amount) || 0) : Math.round(monthlyCTC * 0.2);
    const conveyance = hasManual ? (parseFloat(user.third_amount) || 0) : Math.round(monthlyCTC * 0.07);

    const monthlyGross = monthlyCTC - pfEmployer;
    const specialAllowance = hasManual ? (parseFloat(user.fourth_amount) || 0) : (monthlyGross - (basic + hra + conveyance));

    // For standard deductions split
    let pfEmployee = pfEmployer, pt = 200, medical = 817;
    let customDeds = 0;
    if (hasManual) {
        const totalD = parseFloat(user.sixth_amount) || 0;
        customDeds = totalD - (pfEmployee + pt + medical);
        if (customDeds < 0) customDeds = 0;
    }
    const netPay = monthlyGross - (pfEmployee + pt + medical + customDeds);

    const header = getOfferLetterHeaderHtml();
    const footer = getOfferLetterFooterHtml();

    return `
    ${getOfferLetterLayoutStyles()}
    <div class="offer-letter-doc">
        <div class="letter-watermark">
            ${getOfferLetterLogoImg(true)}
        </div>
        ${header}
        ${footer}

        <table class="letter-layout-table">
            <thead><tr><td><div class="header-space">&nbsp;</div></td></tr></thead>
            <tfoot><tr><td><div class="footer-space">&nbsp;</div></td></tr></tfoot>
            <tbody>
                <tr>
                    <td>
                        <div class="page-content">
                            <h2 class="letter-title">Offer Letter</h2>
                            <p><strong>Date:</strong> ${today}</p>
                            <p><strong>To,</strong><br>${name}</p>
                            <div class="content-body">
                                <p>We are pleased to offer you employment at <strong>Search Homes India Pvt Ltd</strong>. We believe your skills and background will be valuable assets to our team and contribute significantly to our success.</p>
                                <p>As per our discussion, your position will be <strong>${position}</strong> with a fixed Annual Cost to Company (CTC) of <strong>INR ${yearlyCTC.toLocaleString()}/- LPA</strong>. Enclosed with this letter, you'll find our employee handbook, which outlines additional benefits, including Provident Fund (PF) and Insurance.</p>
                                <p><strong>Probation Period</strong></p>
                                <p>You will be on a <strong>90 days</strong> probationary period, during which the company reserves the right to terminate employment without notice or remuneration if your performance is not deemed satisfactory/ or you abscond / or as part of your employment you are expected to meet specific benchmark of Minimum 2 confirmed bookings within 60 days. Additionally, please note that no leave will be granted during the probationary period, and any absence will be considered as Loss of Pay (LOP).</p>
                                <p><strong>Dress Code Guidelines</strong></p>
                                <ol>
                                    <li><strong>Business Casual:</strong> Acceptable for most office days. This includes collared shirts, blouses, trousers, skirts, and dresses.</li>
                                    <li><strong>Formal Attire:</strong> On days when you have client meetings or special events, formal business attire is required. This includes suits, ties, blazers, formal skirts, and dresses.</li>
                                    <li><strong>Inappropriate Attire:</strong> Please avoid casual wear like T-shirts, shorts, flip-flops, and any clothing with logos, slogans, or Graphics not aligned with our company's image.</li>
                                </ol>
                                <p><strong>Notice Period</strong></p>
                                <p>During your employment, a 15-day notice period is required by either party to terminate this contract. The notice period starts from the date your resignation letter is received by your manager. However, in cases of a breach of company policy, the company may terminate the contract with immediate effect.</p>
                                <p><strong>Full &amp; Final Settlement</strong></p>
                                <p>Any employee wishing to resign must communicate his intent in writing for acceptance by management. On acceptance of resignation and after serving notice period by employee. FNF and deductions settlements process is initiated after post last working day and final amount ( includes the employees unpaid salary only), shall be credited to the respective Employees Bank Account within 30 to 45 after relieving</p>
                                <p>If you choose to accept this offer, please sign and return the enclosed copy of this letter in the provided self- addressed, stamped envelope. We are excited to welcome you to the Search Homes India family.</p>
                            </div>
                            <p style="font-weight:bold; margin-top:30px;">Warm regards,</p>
                            <p><strong>Shivali V Rai</strong><br>HR Manager<br>Search Homes India Pvt Ltd</p>
                            <table style="width:100%; margin-top:50px; border-collapse:collapse;">
                                <tr>
                                    <td style="border:none; width:50%;"><strong>Employee Signature:</strong> ____________________</td>
                                    <td style="border:none; width:50%; text-align:right;"><strong>Date:</strong> ____________________</td>
                                </tr>
                            </table>
                        </div>
                    </td>
                </tr>
                <tr style="page-break-before: always;">
                    <td>
                        <div class="page-content">
                            <h3 style="text-align:center; text-decoration:underline; font-weight:800; color:#115b82; margin-bottom:8px;">ANNEXURE - A</h3>
                            <p style="text-align:center; font-weight:bold; font-size:15px; margin-bottom:20px;">${name}</p>
                            <table class="salary-table">
                                <thead>
                                    <tr>
                                        <th style="background:#fff; color:#115b82;">CTC</th>
                                        <th style="background:#004d80; color:#fff;">Monthly CTC</th>
                                        <th style="background:#004d80; color:#fff;">Yearly CTC</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="total-row">
                                        <td>CTC</td>
                                        <td>${monthlyCTC.toLocaleString()}</td>
                                        <td>${yearlyCTC.toLocaleString()}</td>
                                    </tr>
                                    <tr class="category-row"><td colspan="3">Earning</td></tr>
                                    <tr><td>Basic</td><td>${basic.toLocaleString()}</td><td>${(basic * 12).toLocaleString()}</td></tr>
                                    <tr><td>HRA</td><td>${hra.toLocaleString()}</td><td>${(hra * 12).toLocaleString()}</td></tr>
                                    <tr><td>Conveyance Allowance</td><td>${conveyance.toLocaleString()}</td><td>${(conveyance * 12).toLocaleString()}</td></tr>
                                    <tr><td>Special Allowance</td><td>${specialAllowance.toLocaleString()}</td><td>${(specialAllowance * 12).toLocaleString()}</td></tr>
                                    <tr class="category-row"><td colspan="3">Statutory Benefit</td></tr>
                                    <tr><td>PF (Employer Part)</td><td>${pfEmployer.toLocaleString()}</td><td>${(pfEmployer * 12).toLocaleString()}</td></tr>
                                    <tr class="total-row"><td>Monthly Gross</td><td>${monthlyGross.toLocaleString()}</td><td>${(monthlyGross * 12).toLocaleString()}</td></tr>
                                    <tr><td>PF (Employee Part)</td><td>${pfEmployee.toLocaleString()}</td><td>${(pfEmployee * 12).toLocaleString()}</td></tr>
                                    <tr><td>PT</td><td>${pt.toLocaleString()}</td><td>${(pt * 12).toLocaleString()}</td></tr>
                                    <tr><td>Medical Benefit</td><td>${medical.toLocaleString()}</td><td>${(medical * 12).toLocaleString()}</td></tr>
                                    <tr class="total-row"><td>Net Pay</td><td>${netPay.toLocaleString()}</td><td>${(netPay * 12).toLocaleString()}</td></tr>
                                </tbody>
                            </table>
                            <p style="font-size:11px; font-weight:800; color:#444; margin-top:15px;">Note: 1) Income Tax will be deducted as per the provision of Income Tax act 1961</p>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    `;
}

function getNoDuesCertificateTemplate(user) {
    const today = new Date().toLocaleDateString('en-GB');
    const name = user.username || 'Muhammed Asif V K';
    const empId = user.employee_id || '2073';
    const role = user.user_type || 'Senior Manager';
    const project = user.project_name || 'Sales';
    const lastWorkingDay = user.inactive_at ? new Date(user.inactive_at).toLocaleDateString('en-GB') : today;

    return `
    <div style="font-family: Arial, sans-serif; line-height: 1.8; color: #000; padding: 40px; max-width: 800px; margin: auto; border: 1px solid #eee; position: relative;">
        <div style="text-align: center; margin-bottom: 50px;">
            <h2 style="text-transform: uppercase; font-weight: bold; text-decoration: underline; letter-spacing: 2px;">No Dues Certificate</h2>
        </div>

        <p style="margin-bottom: 30px; text-align: justify;">
            This is to certify that <strong>${name}</strong> [Employee ID: <strong>${empId}</strong>] Who was working as <strong>${role}</strong> in the <strong>${project}</strong> at <strong>Search Homes India Pvt Ltd</strong>, has cleared all dues with respect to the company as of their last working day <strong>${lastWorkingDay}</strong>.
        </p>

        <p style="margin-bottom: 30px; text-align: justify;">
            There are no pending financial or material obligations from the employee towards the company, and all company assets have been returned in proper condition. All Pending incentives and salaries have also been cleared by the company.
        </p>

        <p style="margin-bottom: 50px; text-align: justify;">
            This certificate is being issued upon the employee's request for future reference.
        </p>

        <div style="margin-top: 60px;">
            <p><strong>Date:</strong> ${lastWorkingDay}</p>
            <p><strong>Place:</strong> Bangalore</p>
        </div>

        <table style="width: 100%; border-collapse: collapse; border: none; margin-top: 80px;">
            <tr>
                <td style="width: 50%; text-align: left; vertical-align: bottom; border: none; padding: 0;">
                    <p style="margin: 0; font-weight: bold;">Authorized Signatory</p>
                    <p style="margin: 0;"><strong>Shivali V Rai</strong></p>
                    <p style="margin: 0;">HR Manager</p>
                </td>
                <td style="width: 50%; text-align: right; vertical-align: bottom; border: none; padding: 0;">
                    <p style="margin-bottom: 40px;">Employee Signature: ____________________</p>
                </td>
            </tr>
        </table>
    </div>
    `;
}

function getRelievingLetterTemplate(user) {
    const today = new Date().toLocaleDateString('en-GB');
    const name = user.username || 'Abhishek Vishwakarma';
    const empId = user.employee_id || '2130';
    const role = user.user_type || 'Senior Sales Manager';
    const doj = user.doj || '03/03/2026';
    const lastWorkingDay = user.inactive_at ? new Date(user.inactive_at).toLocaleDateString('en-GB') : '31/03/2026';

    return `
    <div style="font-family: Arial, sans-serif; line-height: 1.6; color: #000; padding: 50px; max-width: 850px; margin: auto; position: relative;">
        <p style="text-align: left; margin-bottom: 40px;">Date: ${today}</p>
        
        <div style="text-align: center; margin-bottom: 60px;">
            <h2 style="font-weight: bold; text-transform: uppercase; letter-spacing: 1px;">TO WHOM SO EVER IT MAY CONCERN</h2>
        </div>

        <p style="margin-bottom: 25px; text-align: justify;">
            This is to certify that <strong>${name} [Employee ID: ${empId}]</strong> was employed with us as a full-time employee from <strong>${doj} to ${lastWorkingDay}</strong> and has been relieved from his duties as of closing hours on <strong>${lastWorkingDay}</strong>.
        </p>

        <p style="margin-bottom: 25px; text-align: justify;">
            He was designated as <strong>${role}</strong> at the time of his leaving the organization.
        </p>

        <p style="margin-bottom: 60px; text-align: justify;">
            We wish him all the best in his future endeavors.
        </p>

        <div style="margin-top: 80px;">
            <p style="margin-bottom: 10px;">For Search Homes India Pvt Ltd</p>
            <p style="margin: 0;"><strong>Shivali V Rai</strong></p>
            <p style="margin: 0;">Sr. HR Executive</p>
        </div>
        
        <!-- Watermark/Logo Placeholder -->
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); opacity: 0.05; z-index: -1;">
            <i class="bi bi-house-door" style="font-size: 300px;"></i>
        </div>
    </div>
    `;
}

window.openLetterModal = function (type, userIdOptional) {
    const launchEditor = () => {
        if (!window.currentDrawerUser || !window.currentDrawerUser.id) {
            Swal.fire('Error', 'Please select a user first.', 'error');
            return;
        }
        openLetterModalForUser(type, window.currentDrawerUser.id);
    };

    if (userIdOptional) {
        const uid = parseInt(userIdOptional, 10);
        if (!uid) {
            Swal.fire('Error', 'Invalid employee link for this offer.', 'error');
            return;
        }
        fetch(`fetch_users.php?id=${encodeURIComponent(uid)}`)
            .then(res => {
                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                return res.json();
            })
            .then(user => {
                if (user && !user.error && user.id) {
                    window.currentDrawerUser = user;
                    openLetterModalForUser(type, uid);
                } else {
                    Swal.fire('Error', user?.error || 'Could not load employee details.', 'error');
                }
            })
            .catch((err) => {
                console.error('fetch_users failed:', err);
                Swal.fire('Error', 'Could not load employee details.', 'error');
            });
        return;
    }

    launchEditor();
};

function isLetterEditorMobile() {
    return window.matchMedia('(max-width: 768px)').matches;
}

/** Give remaining viewport height to the letter body on mobile */
function getLetterEditorHeight() {
    if (!isLetterEditorMobile()) return 500;
    const vh = window.visualViewport?.height ?? window.innerHeight;
    const modal = document.getElementById('appointmentLetterModal');
    let chrome = 200;
    if (modal) {
        const header = modal.querySelector('.modal-header');
        const formatBar = modal.querySelector('.letter-mobile-format-bar');
        const footer = modal.querySelector('.letter-editor-footer');
        chrome = (header?.offsetHeight || 0) + (formatBar?.offsetHeight || 0) + (footer?.offsetHeight || 0) + 12;
    }
    return Math.max(360, Math.floor(vh - chrome));
}

function resizeLetterEditorForMobile() {
    if (!isLetterEditorMobile()) return;
    const h = getLetterEditorHeight();
    const $frame = $('#appointmentLetterEditor').next('.note-editor');
    if (!$frame.length) return;
    $frame.find('.note-editing-area').css({ height: h + 'px', minHeight: h + 'px' });
    $frame.find('.note-editable').css({ minHeight: h + 'px' });
}

function getLetterSummernoteToolbar() {
    if (isLetterEditorMobile()) {
        return [];
    }
    return [
        ['style', ['style']],
        ['font', ['bold', 'underline', 'clear']],
        ['color', ['color']],
        ['para', ['ul', 'ol', 'paragraph']],
        ['table', ['table']],
        ['insert', ['link', 'image', 'hr']],
        ['view', ['fullscreen', 'codeview', 'help']]
    ];
}

window.runLetterSummernoteCommand = function (cmd, arg) {
    const $ed = $('#appointmentLetterEditor');
    if (!$ed.length || typeof $ed.summernote !== 'function') return;
    try {
        $ed.summernote('focus');
        if (arg !== undefined && arg !== null && arg !== '') {
            $ed.summernote(cmd, arg);
        } else {
            $ed.summernote(cmd);
        }
    } catch (err) {
        console.warn('Summernote command failed:', cmd, err);
    }
};

function initLetterMobileFormatBar() {
    const bar = document.getElementById('letterMobileFormatBar');
    if (!bar || bar.dataset.bound === '1') return;
    bar.dataset.bound = '1';
    bar.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-sn-cmd]');
        if (!btn) return;
        e.preventDefault();
        runLetterSummernoteCommand(
            btn.getAttribute('data-sn-cmd'),
            btn.getAttribute('data-sn-arg')
        );
    });
}

function openLetterModalForUser(type, userId) {
    if (!window.currentDrawerUser || !window.currentDrawerUser.id) {
        Swal.fire('Error', 'Employee details are not loaded yet. Please try again.', 'error');
        return;
    }

    const modalEl = document.getElementById('appointmentLetterModal');
    if (!modalEl) {
        Swal.fire('Error', 'Letter editor is not available on this page. Please refresh and try again.', 'error');
        return;
    }

    if (modalEl.parentElement !== document.body) {
        document.body.appendChild(modalEl);
    }

    if (typeof bootstrap === 'undefined' || !bootstrap.Modal) {
        Swal.fire('Error', 'Page scripts did not load correctly. Please refresh the page.', 'error');
        return;
    }

    // Reset range tracking to avoid cursor focus locking from previous sessions
    window.letterEditorSavedNativeRange = null;
    window._sigRangeLocked = false;

    const letterUrl = `get_appointment_letter.php?user_id=${encodeURIComponent(userId)}&document_type=${encodeURIComponent(type)}`;

    fetch(letterUrl)
        .then(res => {
            if (!res.ok) {
                throw new Error(`HTTP ${res.status}`);
            }
            return res.json();
        })
        .then(res => {
        let template = '';
        if (type === 'appointment_letter') template = getAppointmentLetterTemplate(window.currentDrawerUser);
        else if (type === 'offer_letter') template = getOfferLetterTemplate(window.currentDrawerUser);
        else if (type === 'no_dues_certificate') template = getNoDuesCertificateTemplate(window.currentDrawerUser);
        else if (type === 'relieving_letter') template = getRelievingLetterTemplate(window.currentDrawerUser);

        let content = (res && res.status === 'success' && res.data) ? res.data : template;
        if (type === 'offer_letter') {
            content = ensureOfferLetterEditorContent(content, template);
        }
        content = upgradeLegacySignatures(content);

        const titleMap = {
            'appointment_letter': 'Appointment Letter',
            'offer_letter': 'Offer Letter',
            'no_dues_certificate': 'No Dues Letter',
            'relieving_letter': 'Relieving Letter'
        };
        const title = titleMap[type] || 'Document Editor';

        const titleEl = modalEl.querySelector('.modal-title');
        if (titleEl) {
            titleEl.innerHTML = `<i class="bi bi-envelope-paper"></i> ${title}`;
        }
        const docTypeInput = document.getElementById('editing_document_type');
        if (docTypeInput) docTypeInput.value = type;

        let modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) {
            modal.dispose();
        }
        modal = new bootstrap.Modal(modalEl, {
            focus: false
        });

        // Reset range tracking when the modal is closed to clean up resources
        modalEl.addEventListener('hidden.bs.modal', function _onClose() {
            window.letterEditorSavedNativeRange = null;
            window._sigRangeLocked = false;
            document.body.classList.remove('letter-editor-open');
            window.removeEventListener('resize', resizeLetterEditorForMobile);
            if (window.visualViewport) {
                window.visualViewport.removeEventListener('resize', resizeLetterEditorForMobile);
            }
            modalEl.removeEventListener('hidden.bs.modal', _onClose);
        });

        modalEl.addEventListener('shown.bs.modal', function onLetterModalShown() {
            document.body.classList.add('letter-editor-open');
            window.addEventListener('resize', resizeLetterEditorForMobile);
            if (window.visualViewport) {
                window.visualViewport.addEventListener('resize', resizeLetterEditorForMobile);
            }
            modalEl.removeEventListener('shown.bs.modal', onLetterModalShown);
            if (typeof $.fn.summernote !== 'undefined') {
                // Destroy previous instance only if Summernote was already initialized
                try {
                    if ($('#appointmentLetterEditor').next('.note-editor').length) {
                        $('#appointmentLetterEditor').summernote('destroy');
                    }
                } catch (destroyErr) {
                    console.warn('Summernote destroy skipped:', destroyErr);
                }

                initLetterMobileFormatBar();

                $('#appointmentLetterEditor').summernote({
                    placeholder: 'Write your document here...',
                    tabsize: 2,
                    height: getLetterEditorHeight(),
                    focus: true,
                    disableDragAndDrop: true,
                    dialogsInBody: true,
                    toolbar: getLetterSummernoteToolbar(),
                    popover: {
                        image: [
                            ['image', ['resizeFull', 'resizeHalf', 'resizeQuarter', 'resizeNone']],
                            ['float', ['floatLeft', 'floatRight', 'floatNone']],
                            ['custom', ['toggleDrag']],
                            ['remove', ['removeMedia']]
                        ]
                    },
                    callbacks: {
                        onPaste: function (e) {
                            var bufferText = ((e.originalEvent || e).clipboardData || window.clipboardData).getData('Text');
                            e.preventDefault();
                            // Paste as plain text to avoid weird backgrounds and formatting
                            document.execCommand('insertText', false, bufferText);
                        },
                        onKeyup: function () {
                            persistLetterEditorRange();
                        },
                        onMouseup: function () {
                            persistLetterEditorRange();
                        },
                        onBlur: function () {
                            persistLetterEditorRange();
                        },
                        onFocus: function () {
                            if (window.letterEditorSavedNativeRange) {
                                try {
                                    // Ensure the range contains nodes that are still attached to the DOM
                                    if (!document.contains(window.letterEditorSavedNativeRange.startContainer)) {
                                        window.letterEditorSavedNativeRange = null;
                                        return;
                                    }
                                    const sel = window.getSelection();
                                    const editable = $('#appointmentLetterEditor').next('.note-editor').find('.note-editable')[0];
                                    if (editable && (!sel.anchorNode || !editable.contains(sel.anchorNode))) {
                                        sel.removeAllRanges();
                                        sel.addRange(window.letterEditorSavedNativeRange);
                                        $('#appointmentLetterEditor').summernote('saveRange');
                                    }
                                } catch (e) { /* ignore */ }
                            }
                        }
                    },
                    buttons: {
                        toggleDrag: function (context) {
                            var ui = $.summernote.ui;
                            var button = ui.button({
                                contents: '<i class="bi bi-hand-index-thumb"></i> Drag',
                                tooltip: 'Enable Drag Mode',
                                click: function () {
                                    var $img = $(context.invoke('restoreTarget')) || $(context.layoutInfo.editable).data('target');
                                    if (!$img || !$img.length) { $img = $('.note-control-selection').prev('img'); }

                                    if ($img && $img.length) {
                                        $img.css({
                                            'outline': '2px dashed #4f46e5',
                                            'outline-offset': '2px',
                                            'cursor': 'move',
                                            'position': 'absolute',
                                            'z-index': '9999'
                                        });

                                        // --- MANUAL MOUSE TRACKING ---
                                        let isDragging = false;
                                        let startX, startY, initialLeft, initialTop;

                                        $img.on('mousedown.manualDrag', function (e) {
                                            isDragging = true;
                                            startX = e.clientX;
                                            startY = e.clientY;

                                            // Convert bottom: 0px or auto-top to explicit top style so relative dragging updates top correctly
                                            if ($img.css('bottom') !== 'auto' || !$img.css('top') || $img.css('top') === 'auto') {
                                                const currentTop = $img[0].offsetTop;
                                                $img.css({
                                                    'top': currentTop + 'px',
                                                    'bottom': 'auto'
                                                });
                                            }

                                            // Get current relative offsets
                                            initialLeft = parseFloat($img.css('left')) || 0;
                                            initialTop = parseFloat($img.css('top')) || 0;
                                            e.preventDefault();
                                        });

                                        $(document).on('mousemove.manualDrag', function (e) {
                                            if (!isDragging) return;
                                            let dx = e.clientX - startX;
                                            let dy = e.clientY - startY;
                                            $img.css({
                                                'left': (initialLeft + dx) + 'px',
                                                'top': (initialTop + dy) + 'px'
                                            });
                                        });

                                        $(document).on('mouseup.manualDrag', function () {
                                            if (isDragging) {
                                                isDragging = false;
                                                $(document).off('.manualDrag');
                                                $img.off('.manualDrag');
                                                $img.css({ 'outline': 'none', 'cursor': 'pointer' });

                                                // Sync back to Summernote
                                                const editor = $('#appointmentLetterEditor');
                                                editor.summernote('code', editor.next('.note-editor').find('.note-editable').html());

                                                const Toast = Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 1000 });
                                                Toast.fire({ icon: 'success', title: 'Position Anchored' });
                                            }
                                        });

                                        const Toast = Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 2000 });
                                        Toast.fire({ icon: 'info', title: 'Click and HOLD to move signature relative to its text position!' });
                                    }
                                }
                            });
                            return button.render();
                        }
                    }
                });
                // Set content after initialization for better reliability
                $('#appointmentLetterEditor').summernote('code', stripSigPlaceholdersForOutput(content));
                resizeLetterEditorForMobile();
                requestAnimationFrame(resizeLetterEditorForMobile);
                setTimeout(resizeLetterEditorForMobile, 80);
            } else {
                document.getElementById('appointmentLetterEditor').value = content;
            }
            setTimeout(resizeLetterEditorForMobile, 80);
        }, { once: true });

        const resetBtn = document.getElementById('resetLetterBtn');
        const saveBtn = document.getElementById('saveLetterBtn');
        if (!resetBtn || !saveBtn) {
            throw new Error('Letter editor controls are missing from the page.');
        }

        // Update Reset button
        resetBtn.onclick = function () {
            Swal.fire({
                title: 'Are you sure?',
                text: "This will overwrite your current edits with the fresh template.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#f59e0b',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes, reset it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    let freshContent = '';
                    if (type === 'appointment_letter') freshContent = getAppointmentLetterTemplate(window.currentDrawerUser);
                    else if (type === 'offer_letter') freshContent = getOfferLetterTemplate(window.currentDrawerUser);
                    else if (type === 'no_dues_certificate') freshContent = getNoDuesCertificateTemplate(window.currentDrawerUser);
                    else if (type === 'relieving_letter') freshContent = getRelievingLetterTemplate(window.currentDrawerUser);

                    if (typeof $.fn.summernote !== 'undefined') {
                        $('#appointmentLetterEditor').summernote('code', stripSigPlaceholdersForOutput(freshContent));
                    } else {
                        document.getElementById('appointmentLetterEditor').value = freshContent;
                    }
                }
            });
        };

        // Update Save button
        saveBtn.onclick = function () {
            saveLetter(type);
        };

        modal.show();
    }).catch(err => {
        console.error("Error loading letter editor:", err);
        const detail = err && err.message ? err.message : 'Unknown error';
        Swal.fire('Error', `Could not load letter data. (${detail})`, 'error');
    });
}

function saveLetter(type) {
    if (!window.currentDrawerUser || !window.currentDrawerUser.id) return;
    const userId = window.currentDrawerUser.id;
    let content = '';
    if (typeof $.fn.summernote !== 'undefined') {
        content = $('#appointmentLetterEditor').summernote('code');
    } else {
        content = document.getElementById('appointmentLetterEditor').value;
    }

    const formData = new FormData();
    formData.append('user_id', userId);
    formData.append('document_type', type);
    formData.append('content', content);

    fetch('save_appointment_letter.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success') {
                Swal.fire('Saved!', 'Letter has been saved.', 'success');
                const modalEl = document.getElementById('appointmentLetterModal');
                const modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();

                // Update UI status
                const statusMap = {
                    'appointment_letter': 'appointment_letter_status',
                    'offer_letter': 'offer_letter_status'
                };
                const btnMap = {
                    'appointment_letter': 'btn_print_appointment_letter',
                    'offer_letter': 'btn_print_offer_letter'
                };
                const btnPreviewMap = {
                    'appointment_letter': 'btn_preview_appointment_letter',
                    'offer_letter': 'btn_preview_offer_letter'
                };
                const btnMailMap = {
                    'appointment_letter': 'btn_mail_appointment_letter',
                    'offer_letter': 'btn_mail_offer_letter'
                };

                const statusId = statusMap[type];
                const btnId = btnMap[type];
                const btnPreviewId = btnPreviewMap[type];
                const btnMailId = btnMailMap[type];

                // Update Drawer Elements
                const drawerStatus = document.getElementById(statusId);
                if (drawerStatus) {
                    drawerStatus.innerHTML = '<i class="bi bi-check-circle-fill text-success"></i> Generated';
                    drawerStatus.className = "info-value text-success";
                }
                const drawerBtn = document.getElementById(btnId);
                if (drawerBtn) drawerBtn.style.display = 'inline-block';
                const drawerPreviewBtn = document.getElementById(btnPreviewId);
                if (drawerPreviewBtn) drawerPreviewBtn.style.display = 'inline-block';
                const drawerMailBtn = document.getElementById(btnMailId);
                if (drawerMailBtn) drawerMailBtn.style.display = 'inline-block';

                // Update FNF Modal Elements
                const fnfStatusId = type === 'no_dues_certificate' ? 'fnf_no_dues_status' : 'fnf_relieving_status';
                const fnfBtnMailId = type === 'no_dues_certificate' ? 'btn_fnf_mail_no_dues' : 'btn_fnf_mail_relieving';
                const fnfBtnPrintId = type === 'no_dues_certificate' ? 'btn_fnf_print_no_dues' : 'btn_fnf_print_relieving';
                const fnfBtnPreviewId = type === 'no_dues_certificate' ? 'btn_fnf_preview_no_dues' : 'btn_fnf_preview_relieving';

                const fnfStatus = document.getElementById(fnfStatusId);
                if (fnfStatus) {
                    fnfStatus.innerHTML = '<i class="bi bi-check-circle-fill text-success"></i> Generated';
                    fnfStatus.className = "info-value small text-success";
                }
                const fnfBtnMail = document.getElementById(fnfBtnMailId);
                if (fnfBtnMail) fnfBtnMail.style.display = 'inline-block';
                const fnfBtnPrint = document.getElementById(fnfBtnPrintId);
                if (fnfBtnPrint) fnfBtnPrint.style.display = 'inline-block';
                const fnfBtnPreview = document.getElementById(fnfBtnPreviewId);
                if (fnfBtnPreview) fnfBtnPreview.style.display = 'inline-block';
            } else { Swal.fire('Error', res.message || 'Failed to save letter.', 'error'); }
        })
        .catch(err => { console.error("Error saving letter:", err); Swal.fire('Error', 'Failed to communicate with server.', 'error'); });
}

// --- Asset Management in User 360 ---
window.fetchUserAssets = function (userId) {
    const body = document.getElementById("drawer_assets_body");
    const noMsg = document.getElementById("no_assets_message");
    if (!body) return;

    body.innerHTML = '<tr><td colspan="3" class="text-center py-3">Loading...</td></tr>';
    noMsg.style.display = "none";

    fetch(`action.php?fetch_user_assets=1&user_id=${userId}`)
        .then(res => res.json())
        .then(data => {
            body.innerHTML = "";
            if (!data || data.length === 0) {
                noMsg.style.display = "block";
                return;
            }
            data.forEach(asset => {
                const row = document.createElement("tr");
                row.className = "user-data-row";
                row.innerHTML = `
                    <td>
                        <div class="fw-bold">${asset.asset_name}</div>
                        <div class="small text-muted">${asset.asset_type}</div>
                    </td>
                    <td><span class="drawer-asset-serial">${asset.serial_number || '—'}</span></td>
                    <td class="text-center">
                        <button onclick="returnAssetFromDrawer(${asset.id})" class="btn btn-link btn-sm text-danger p-0" title="Return Asset">
                            <i class="bi bi-arrow-return-left"></i> Return
                        </button>
                    </td>
                `;
                body.appendChild(row);
            });
        })
        .catch(err => {
            console.error("Error fetching assets:", err);
            body.innerHTML = '<tr><td colspan="3" class="text-center text-danger">Failed to load.</td></tr>';
        });
}

window.openAssignAssetFromDrawer = function () {
    const userId = window.currentDrawerUser ? window.currentDrawerUser.id : null;
    if (!userId) {
        Swal.fire("Error", "User details not fully loaded. Please wait a moment.", "error");
        return;
    }

    const modalEl = document.getElementById('assignAssetFromDrawerModal');
    if (!modalEl) {
        console.error("Modal element 'assignAssetFromDrawerModal' not found.");
        return;
    }

    /* Escape drawer stacking context; sit above mobile navbar (z-index 99999) */
    if (modalEl.parentElement !== document.body) {
        document.body.appendChild(modalEl);
    }

    document.getElementById("assign_asset_user_id").value = userId;

    const select = document.getElementById("drawerAssetSelect");
    if (select) {
        select.innerHTML = '<option value="">Loading inventory...</option>';
        select.disabled = true;
        fetch("action.php?fetch_assets_list=1", { credentials: "same-origin", cache: "no-store" })
            .then(async (res) => {
                const text = await res.text();
                let assets;
                try {
                    assets = JSON.parse(text);
                } catch (_) {
                    throw new Error("Invalid response from server");
                }
                if (!res.ok) {
                    throw new Error(assets?.message || ("HTTP " + res.status));
                }
                return assets;
            })
            .then(assets => {
                select.innerHTML = '<option value="">-- Choose Available Asset --</option>';
                if (Array.isArray(assets)) {
                    const available = assets.filter(a =>
                        String(a.status || "").trim().toLowerCase() === "available"
                    );
                    if (available.length === 0) {
                        select.innerHTML = '<option value="">No assets available in inventory</option>';
                    } else {
                        available.forEach(a => {
                            const opt = document.createElement("option");
                            opt.value = a.id;
                            opt.textContent = `${a.asset_name} (${a.serial_number})`;
                            select.appendChild(opt);
                        });
                    }
                }
                select.disabled = false;
            })
            .catch(err => {
                console.error("Error fetching assets:", err);
                select.innerHTML = '<option value="">Error loading assets</option>';
                select.disabled = false;
            });
    }

    let modal = bootstrap.Modal.getInstance(modalEl);
    if (modal) modal.dispose();
    modal = new bootstrap.Modal(modalEl, { backdrop: true, focus: true });

    modalEl.addEventListener('shown.bs.modal', function onShown() {
        document.body.classList.add('assign-asset-modal-open');
        modalEl.removeEventListener('shown.bs.modal', onShown);
    }, { once: true });

    modalEl.addEventListener('hidden.bs.modal', function onHidden() {
        document.body.classList.remove('assign-asset-modal-open');
        modalEl.removeEventListener('hidden.bs.modal', onHidden);
    }, { once: true });

    modal.show();
}

window.returnAssetFromDrawer = function (assignmentId) {
    Swal.fire({
        title: 'Return Asset?',
        text: "Mark this asset as returned to inventory?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#0d9488',
        confirmButtonText: 'Yes, Return'
    }).then((result) => {
        if (result.isConfirmed) {
            const today = new Date().toISOString().split('T')[0];
            $.ajax({
                url: 'action.php',
                method: 'POST',
                data: { return_asset_action: 1, assignment_id: assignmentId, returned_date: today },
                success: function (res) {
                    Swal.fire('Returned!', 'Asset status updated.', 'success');
                    if (window.currentDrawerUser) fetchUserAssets(window.currentDrawerUser.id);
                }
            });
        }
    });
}

// Form handler for Asset Assignment from Drawer
$(document).ready(function () {
    $(document).on('submit', '#assignAssetFromDrawerForm', function (e) {
        e.preventDefault();
        const assetId = $(this).find('[name="asset_id"]').val();
        if (!assetId) {
            Swal.fire('Select an asset', 'Choose an available asset from the list first.', 'warning');
            return;
        }
        $.ajax({
            url: 'action.php',
            method: 'POST',
            data: $(this).serialize(),
            success: function (res) {
                if (typeof res === 'string' && res.indexOf('success') === -1) {
                    Swal.fire('Error', 'Could not assign asset. Please try again.', 'error');
                    return;
                }
                const modalEl = document.getElementById('assignAssetFromDrawerModal');
                const modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();

                Swal.fire('Assigned!', 'Asset linked successfully.', 'success');
                if (window.currentDrawerUser) fetchUserAssets(window.currentDrawerUser.id);
            },
            error: function () {
                Swal.fire('Error', 'Server error while assigning asset.', 'error');
            }
        });
    });
});

const LETTER_TYPE_TITLES = {
    appointment_letter: 'Appointment Letter',
    offer_letter: 'Offer Letter',
    no_dues_certificate: 'No Dues Certificate',
    relieving_letter: 'Relieving Letter'
};

function getLetterTypeTitle(type) {
    return LETTER_TYPE_TITLES[type] || 'Letter';
}

function getLetterOutputStyles(type) {
    const signatureCss = ` .signature-container { position: relative !important; display: inline-block !important; width: 0 !important; height: 0 !important; vertical-align: bottom !important; overflow: visible !important; } .signature-stamp, img.signature-stamp { position: absolute !important; max-width: 220px !important; height: auto !important; z-index: 10; border: none !important; outline: none !important; }`;
    const placeholderCss = ' .sig-placeholder,[data-sig-placeholder]{display:none!important;}';

    let outputCss = '';
    if (type === 'offer_letter') {
        outputCss = `@page { margin: 12mm; } 
            body { margin: 0; padding: 8px 12px; background: #eef2f3; }
            @media print { body { padding: 0 !important; background: #fff !important; } }
            .offer-letter-doc { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.55; color: #333; position: relative; background: #fff; box-shadow: 0 4px 20px rgba(0,0,0,0.08); padding: 48px; box-sizing: border-box; }
            @media print { .offer-letter-doc { box-shadow: none !important; padding: 0 !important; } }
            .offer-letter-doc .header-fixed { position: fixed; top: 0; left: 0; width: 100%; background: #fff; z-index: 1000; padding: 0 24px; box-sizing: border-box; }
            .offer-letter-doc .footer-fixed { position: fixed; bottom: 0; left: 0; width: 100%; background: #fff; z-index: 1000; height: 130px; }
            .offer-letter-doc .letter-watermark { position: fixed; top: 38%; left: 0; width: 100%; text-align: center; z-index: -1; opacity: 0.08; }
            .offer-letter-doc thead { display: table-header-group; }
            .offer-letter-doc tfoot { display: table-footer-group; }
            .offer-letter-doc .header-space { height: 120px; }
            .offer-letter-doc .footer-space { height: 130px; }
            .offer-letter-logo { max-height: 58px !important; width: auto !important; display: block !important; }
            .offer-letter-logo.offer-letter-logo--watermark { max-width: 420px !important; max-height: none !important; width: 70% !important; margin: 0 auto !important; opacity: 0.08 !important; }
            .offer-letter-doc .company-info { text-align: right; font-size: 11px; line-height: 1.7; font-weight: 600; }
            .offer-letter-doc .header-border { height: 2px; background: linear-gradient(to right, #115b82 0%, #115b82 75%, #f5a623 75%, #f5a623 88%, #e63946 88%, #e63946 100%); margin-top: 8px; }
            .offer-letter-doc .letter-title { text-align: center; font-size: 17px; font-weight: 800; text-decoration: underline; color: #115b82; }
            .offer-letter-doc .content-body { font-size: 13px; text-align: justify; }
            .offer-letter-doc .salary-table { width: 100%; border-collapse: collapse; font-size: 12px; }
            .offer-letter-doc .salary-table th, .offer-letter-doc .salary-table td { border: 1px solid #ddd; padding: 8px 10px; text-align: center; }
            .offer-letter-doc .salary-table td:first-child { text-align: left; font-weight: 600; }
            .offer-letter-doc .salary-table .category-row, .offer-letter-doc .salary-table .total-row { background: #004d80; color: #fff; font-weight: 700; }
            .offer-letter-doc .letter-layout-table { width: 100%; border-collapse: collapse; }
            .offer-letter-doc .letter-layout-table td { border: none; vertical-align: top; }`
            + signatureCss + placeholderCss;
    } else {
        outputCss = `@page { margin: 20mm; } body { font-family: Arial, sans-serif; padding: 8px 12px; line-height: 1.6; background: #eef2f3; }
            table { width: 100%; border-collapse: collapse; }
            td, th { padding: 8px; border: 1px solid #ddd; }
            hr { border-top: 1px dashed #ccc; margin: 30px 0; }`
            + signatureCss + placeholderCss;
    }

    const isDarkMode =
        (document.body && document.body.classList.contains('dark-mode')) ||
        (document.documentElement && document.documentElement.classList.contains('dark-mode'));
    if (isDarkMode) {
        outputCss += `
        @media screen {
            body { color: #e8e8e8 !important; background-color: #1a1a1a !important; }
            body * { color: #e8e8e8 !important; }
            body strong, body h1, body h2, body h3, body h4, body h5, body h6 { color: #f5f5f5 !important; }
            body table, body td, body th { border-color: #3d3d3d !important; }
            body hr { border-top-color: #3d3d3d !important; }
            .offer-letter-doc { background-color: #242424 !important; box-shadow: 0 4px 20px rgba(0,0,0,0.35) !important; color: #e8e8e8 !important; }
            .offer-letter-doc .header-fixed, .offer-letter-doc .footer-fixed { background-color: #242424 !important; }
            .offer-letter-logo { filter: brightness(0.92) contrast(1.05) grayscale(0.1) !important; }
            .offer-letter-logo.offer-letter-logo--watermark { opacity: 0.04 !important; }
            .offer-letter-doc .company-info { color: #b0b0b0 !important; }
            .offer-letter-doc .letter-title { color: #f5f5f5 !important; }
            .offer-letter-doc .salary-table .category-row, .offer-letter-doc .salary-table .total-row { background: #333333 !important; color: #f5f5f5 !important; }
        }`;
    }

    return outputCss;
}

function prepareLetterHtmlForOutput(type, letterHtml) {
    let html = letterHtml || '';
    if (type === 'offer_letter' && window.currentDrawerUser) {
        html = ensureOfferLetterEditorContent(
            html,
            getOfferLetterTemplate(window.currentDrawerUser)
        );
    }
    return upgradeLegacySignatures(stripSigPlaceholdersForOutput(html));
}

function buildLetterPreviewDocument(type, letterHtml) {
    const bodyHtml = prepareLetterHtmlForOutput(type, letterHtml);
    const styles = getLetterOutputStyles(type);
    return `<!DOCTYPE html><html><head><meta charset="UTF-8"><title>${getLetterTypeTitle(type)}</title><style>${styles}</style></head><body>${bodyHtml}</body></html>`;
}

function slugForPreviewFilename(title) {
    return (title || 'document')
        .replace(/[<>:"/\\|?*]/g, '')
        .replace(/\s+/g, '_')
        .replace(/_+/g, '_')
        .substring(0, 100) || 'document';
}

function buildPreviewExportElement(html) {
    const source = html || window._letterPreviewHtml;
    if (!source) return null;
    const parsed = new DOMParser().parseFromString(source, 'text/html');
    const box = document.createElement('div');
    box.style.background = '#ffffff';
    box.style.color = '#000000';
    const styleEl = document.createElement('style');
    styleEl.textContent = Array.from(parsed.querySelectorAll('style'))
        .map((s) => s.textContent)
        .join('\n');
    box.appendChild(styleEl);
    const content = document.createElement('div');
    content.innerHTML = parsed.body ? parsed.body.innerHTML : source;
    box.appendChild(content);
    return box;
}

function ensureHtml2PdfLoaded() {
    if (typeof window.html2pdf !== 'undefined') {
        return Promise.resolve();
    }
    return new Promise((resolve, reject) => {
        const existing = document.querySelector('script[data-html2pdf-loader]');
        if (existing) {
            existing.addEventListener('load', () => resolve());
            existing.addEventListener('error', () => reject(new Error('Failed to load PDF library')));
            return;
        }
        const s = document.createElement('script');
        s.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js';
        s.dataset.html2pdfLoader = '1';
        s.onload = () => resolve();
        s.onerror = () => reject(new Error('Failed to load PDF library'));
        document.head.appendChild(s);
    });
}

function openDocumentPreviewModal(title, fullHtmlDocument) {
    const modalEl = document.getElementById('letterPreviewModal');
    const iframe = document.getElementById('letterPreviewFrame');
    const loadingEl = document.getElementById('letterPreviewLoading');
    if (!modalEl || !iframe) {
        Swal.fire('Error', 'Preview is not available on this page.', 'error');
        return;
    }

    window._letterPreviewHtml = fullHtmlDocument;
    window._letterPreviewDownloadFilename = `${slugForPreviewFilename(title)}.pdf`;

    document.getElementById('letterPreviewTitle').textContent = title;
    if (loadingEl) loadingEl.style.display = 'flex';
    iframe.style.visibility = 'hidden';

    const onPreviewReady = () => {
        if (loadingEl) loadingEl.style.display = 'none';
        iframe.style.visibility = 'visible';
        iframe.removeEventListener('load', onPreviewReady);
    };
    iframe.addEventListener('load', onPreviewReady);

    const isDarkThemeActive = document.body.classList.contains('dark-mode') || document.documentElement.classList.contains('dark-mode');
    const isPayslipPreview = /^Payslip\b/i.test(String(title || '').trim());
    let previewHtml = fullHtmlDocument;
    if (isDarkThemeActive && isPayslipPreview) {
        previewHtml = applyDarkThemeToPayslipPreview(fullHtmlDocument);
    }
    iframe.srcdoc = previewHtml;

    if (modalEl.parentElement !== document.body) {
        document.body.appendChild(modalEl);
    }

    document.body.classList.add('letter-preview-open');

    let modal = bootstrap.Modal.getInstance(modalEl);
    if (modal) {
        modal.dispose();
    }
    modal = new bootstrap.Modal(modalEl, { focus: false, backdrop: true });

    const onPreviewHidden = () => {
        document.body.classList.remove('letter-preview-open');
        modalEl.removeEventListener('hidden.bs.modal', onPreviewHidden);
    };
    modalEl.addEventListener('hidden.bs.modal', onPreviewHidden, { once: true });

    modal.show();

    requestAnimationFrame(() => {
        try {
            if (iframe.contentDocument && iframe.contentDocument.readyState === 'complete') {
                onPreviewReady();
            }
        } catch (e) { /* ignore */ }
    });
}

function applyDarkThemeToPayslipPreview(htmlDoc) {
    if (!htmlDoc) return htmlDoc;
    const darkCss = `
        body { background: #1a1a1a !important; color: #e8e8e8 !important; }
        .header h1 { color: #f5f5f5 !important; }
        .header p, .section-title, .amount-words, .footer-note { color: #9a9a9a !important; }
        .details-table strong { color: #d4d4d4 !important; }
        .hr-line { border-top-color: #3d3d3d !important; }
        .details-table td, .inner-table td, .totals-row td { border-color: #3d3d3d !important; color: #e8e8e8 !important; background: #242424 !important; }
        .details-table td, .inner-table td, .totals-row, .totals-row td { background: #242424 !important; }
        .salary-container, .net-pay-box { border-color: #3d3d3d !important; background: #242424 !important; box-shadow: none !important; }
        .net-pay-box span { color: #f5f5f5 !important; }
        .salary-table td { background: #242424 !important; border-color: #3d3d3d !important; }
    `;

    if (/<\/head>/i.test(htmlDoc)) {
        return htmlDoc.replace(/<\/head>/i, `<style>${darkCss}</style></head>`);
    }
    return `<style>${darkCss}</style>${htmlDoc}`;
}

function openLetterPreviewModal(type, letterHtml, titleSuffix) {
    const userName = window.currentDrawerUser?.username || 'Employee';
    const title = titleSuffix
        ? `${getLetterTypeTitle(type)} — ${titleSuffix}`
        : `${getLetterTypeTitle(type)} — ${userName}`;
    openDocumentPreviewModal(title, buildLetterPreviewDocument(type, letterHtml));
}

window.printLetterPreview = function () {
    const iframe = document.getElementById('letterPreviewFrame');
    if (iframe && iframe.contentWindow) {
        iframe.contentWindow.focus();
        iframe.contentWindow.print();
    }
};

window.downloadLetterPreview = async function () {
    const exportEl = buildPreviewExportElement();
    if (!exportEl) {
        Swal.fire('Error', 'Nothing to download. Open a preview first.', 'error');
        return;
    }

    const filename = window._letterPreviewDownloadFilename || 'document.pdf';

    Swal.fire({
        title: 'Generating PDF…',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });

    const wrapper = document.createElement('div');
    wrapper.style.cssText = 'position:fixed;left:-9999px;top:0;z-index:-1;width:210mm;background:#fff';
    wrapper.appendChild(exportEl);
    document.body.appendChild(wrapper);

    const opt = {
        margin: 10,
        filename,
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: {
            scale: 2,
            useCORS: true,
            backgroundColor: '#ffffff',
            logging: false,
            scrollY: 0,
            scrollX: 0
        },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
    };

    try {
        await ensureHtml2PdfLoaded();
        await html2pdf().set(opt).from(exportEl).save();
        Swal.close();
    } catch (err) {
        console.error('Preview PDF download failed:', err);
        Swal.fire('Error', 'Could not generate PDF. Try Print and save as PDF.', 'error');
    } finally {
        if (wrapper.parentNode) {
            document.body.removeChild(wrapper);
        }
    }
};

window.previewLetterFromEditor = function () {
    const type = document.getElementById('editing_document_type')?.value;
    if (!type) return;

    let content = '';
    if (typeof $.fn.summernote !== 'undefined' && $('#appointmentLetterEditor').length) {
        content = $('#appointmentLetterEditor').summernote('code');
    } else {
        content = document.getElementById('appointmentLetterEditor')?.value || '';
    }

    if (!content || !content.replace(/<[^>]+>/g, '').trim()) {
        Swal.fire('Nothing to preview', 'Add some content to the letter first.', 'info');
        return;
    }

    openLetterPreviewModal(type, content, 'Editor preview (unsaved)');
};

window.previewLetter = function (type) {
    if (!window.currentDrawerUser || !window.currentDrawerUser.id) return;
    const userId = window.currentDrawerUser.id;

    Swal.fire({
        title: 'Loading preview…',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });

    fetch(`get_appointment_letter.php?user_id=${userId}&document_type=${type}`)
        .then(res => res.json())
        .then(res => {
            Swal.close();
            if (res.status === 'success' && res.data) {
                openLetterPreviewModal(type, res.data, 'Final output');
            } else {
                Swal.fire('Not Found', 'Please generate and save the letter first.', 'info');
            }
        })
        .catch(err => {
            Swal.close();
            console.error('Error loading letter preview:', err);
            Swal.fire('Error', 'Could not load letter for preview.', 'error');
        });
};

window.printLetter = function (type) {
    if (!window.currentDrawerUser || !window.currentDrawerUser.id) return;
    const userId = window.currentDrawerUser.id;
    fetch(`get_appointment_letter.php?user_id=${userId}&document_type=${type}`)
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success' && res.data) {
                const printWindow = window.open('', '_blank');
                const printHtml = prepareLetterHtmlForOutput(type, res.data);
                const printStyles = getLetterOutputStyles(type);
                printWindow.document.write(`<html><head><title>${getLetterTypeTitle(type)} - ${window.currentDrawerUser.username}</title><style>${printStyles}</style></head><body>${printHtml}<script>window.onload = function() { window.print(); window.close(); }</script></body></html>`);
                printWindow.document.close();
            } else { Swal.fire('Not Found', 'Please generate and save the letter first.', 'info'); }
        })
        .catch(err => { console.error("Error printing letter:", err); });
};

window.mailLetter = function (type) {
    if (!window.currentDrawerUser || !window.currentDrawerUser.id) return;
    const userId = window.currentDrawerUser.id;
    
    Swal.fire({
        title: 'Sending Mail...',
        text: 'Please wait while the letter is being sent.',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    const formData = new FormData();
    formData.append('user_id', userId);
    formData.append('document_type', type);

    fetch('mail_letter.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success') {
                Swal.fire('Sent!', res.message, 'success');
            } else {
                Swal.fire('Error', res.message || 'Failed to send mail.', 'error');
            }
        })
        .catch(err => {
            console.error("Error mailing letter:", err);
            Swal.fire('Error', 'Failed to communicate with server.', 'error');
        });
};

window.loadPayslipHistory = function (userId) {
    const tbody = document.getElementById('drawer_payslip_history');
    const mobileContainer = document.getElementById('drawer_payslip_history_mobile');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">Loading...</td></tr>';
    if (mobileContainer) mobileContainer.innerHTML = '<div class="text-center text-muted p-3">Loading...</div>';

    fetch(`payslips_api.php?action=history&user_id=${userId}`)
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success' && res.data.length > 0) {
                let html = '';
                let mobileHtml = '';
                const months = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
                res.data.forEach(p => {
                    const date = new Date(p.created_at).toLocaleDateString('en-GB');
                    const src = (p.source || 'user_payslips').replace(/'/g, "\\'");
                    
                    // Desktop table row
                    html += `
                <tr>
                    <td><strong>${months[p.month - 1]} ${p.year}</strong></td>
                    <td class="text-success fw-bold">₹${parseFloat(p.net_pay).toLocaleString()}</td>
                    <td class="text-muted small">${date}</td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-info py-0 me-1" onclick="mailPayslip(${p.id}, ${userId})" title="Mail"><i class="bi bi-envelope"></i></button>
                        <button class="btn btn-sm btn-outline-primary py-0 me-1" onclick="previewPayslip(${p.id}, '${p.month} ${p.year}', '${src}')" title="Preview"><i class="bi bi-eye"></i></button>
                        <button class="btn btn-sm btn-outline-secondary py-0" onclick="printPayslip(${p.id}, '${p.month} ${p.year}', '${src}')" title="Print"><i class="bi bi-printer"></i></button>
                    </td>
                </tr>`;

                    // Mobile card
                    mobileHtml += `
                <div class="payslip-mobile-card mb-3 p-3">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <div class="payslip-mobile-month" style="font-size: 1.1rem; font-weight: 700;">
                                ${months[p.month - 1]} ${p.year}
                            </div>
                            <div class="payslip-mobile-date text-muted small mt-1">
                                Generated: ${date}
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="payslip-mobile-amount text-success fw-bold" style="font-size: 1.1rem;">
                                ₹${parseFloat(p.net_pay).toLocaleString()}
                            </div>
                        </div>
                    </div>
                    <div class="d-flex gap-2 mt-3 pt-2 border-top-dashed">
                        <button class="btn btn-outline-info flex-grow-1 py-2 d-flex align-items-center justify-content-center gap-2" style="font-size: 0.85rem; font-weight: 600;" onclick="mailPayslip(${p.id}, ${userId})">
                            <i class="bi bi-envelope"></i> Mail
                        </button>
                        <button class="btn btn-outline-primary flex-grow-1 py-2 d-flex align-items-center justify-content-center gap-2" style="font-size: 0.85rem; font-weight: 600;" onclick="previewPayslip(${p.id}, '${p.month} ${p.year}', '${src}')">
                            <i class="bi bi-eye"></i> Preview
                        </button>
                        <button class="btn btn-outline-secondary flex-grow-1 py-2 d-flex align-items-center justify-content-center gap-2" style="font-size: 0.85rem; font-weight: 600;" onclick="printPayslip(${p.id}, '${p.month} ${p.year}', '${src}')">
                            <i class="bi bi-printer"></i> Print
                        </button>
                    </div>
                </div>`;
                });
                tbody.innerHTML = html;
                if (mobileContainer) mobileContainer.innerHTML = mobileHtml;
            } else {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No payslips generated yet.</td></tr>';
                if (mobileContainer) mobileContainer.innerHTML = '<div class="text-center text-muted p-4">No payslips generated yet.</div>';
            }
        })
        .catch(err => {
            console.error("Payslip fetch error:", err);
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-danger">Failed to load</td></tr>';
        });
};

window.mailPayslip = function (payslipId, userId = null) {
    if (!userId && window.currentDrawerUser) userId = window.currentDrawerUser.id;
    if (!userId) {
        Swal.fire('Error', 'User ID not found.', 'error');
        return;
    }

    Swal.fire({
        title: 'Sending Payslip...',
        text: 'Please wait while the payslip is being mailed.',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    const formData = new FormData();
    formData.append('user_id', userId);
    formData.append('payslip_id', payslipId);

    fetch('mail_payslip.php', { method: 'POST', body: formData })
        .then(async response => {
            const text = await response.text();
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error("Server response was not JSON:", text);
                throw new Error(text.substring(0, 100).trim() || "Empty server response (possible crash)");
            }
        })
        .then(res => {
            if (res.status === 'success') {
                Swal.fire('Mailed!', res.message, 'success');
            } else {
                Swal.fire('Error', res.message || 'Failed to send payslip.', 'error');
            }
        })
        .catch(err => {
            console.error("Error mailing payslip:", err);
            Swal.fire('Error', 'Communication error: ' + err.message, 'error');
        });
};

window.openPayslipGeneratorModal = function () {
    if (!window.currentDrawerUser || !window.currentDrawerUser.id) {
        Swal.fire('Error', 'Please select a user first.', 'error');
        return;
    }

    // Reset form
    document.getElementById('payslipGeneratorForm').reset();

    // Set current month/year
    const now = new Date();
    document.getElementById('payslip_month').value = now.getMonth() + 1; // 1-12
    document.getElementById('payslip_year').value = now.getFullYear();

    // Check for manual overrides
    const hasManual = (
        (window.currentDrawerUser.first_amount && parseFloat(window.currentDrawerUser.first_amount) > 0) ||
        (window.currentDrawerUser.second_amount && parseFloat(window.currentDrawerUser.second_amount) > 0) ||
        (window.currentDrawerUser.third_amount && parseFloat(window.currentDrawerUser.third_amount) > 0) ||
        (window.currentDrawerUser.fourth_amount && parseFloat(window.currentDrawerUser.fourth_amount) > 0) ||
        (window.currentDrawerUser.fifth_amount && parseFloat(window.currentDrawerUser.fifth_amount) > 0) ||
        (window.currentDrawerUser.sixth_amount && parseFloat(window.currentDrawerUser.sixth_amount) > 0)
    );

    // Base salary components
    const monthlyCTC = parseFloat(window.currentDrawerUser.salary) || 0;
    const basic = hasManual ? (parseFloat(window.currentDrawerUser.first_amount) || 0) : Math.round(monthlyCTC * 0.5);
    const hra = hasManual ? (parseFloat(window.currentDrawerUser.second_amount) || 0) : Math.round(monthlyCTC * 0.2);
    const conveyance = hasManual ? (parseFloat(window.currentDrawerUser.third_amount) || 0) : Math.round(monthlyCTC * 0.07);
    const pfEmployer = hasManual ? (parseFloat(window.currentDrawerUser.fifth_amount) || 0) : Math.min(1800, Math.round(basic * 0.12));
    const monthlyGross = monthlyCTC - pfEmployer;
    const specialAllowance = hasManual ? (parseFloat(window.currentDrawerUser.fourth_amount) || 0) : (monthlyGross - (basic + hra + conveyance));

    document.getElementById('payslip_basic').dataset.base = basic;
    document.getElementById('payslip_hra').dataset.base = hra;
    document.getElementById('payslip_conveyance').dataset.base = conveyance;
    document.getElementById('payslip_special').dataset.base = specialAllowance;

    document.getElementById('payslip_basic').value = basic;
    document.getElementById('payslip_hra').value = hra;
    document.getElementById('payslip_conveyance').value = conveyance;
    document.getElementById('payslip_special').value = specialAllowance;

    // Set standard deductions from manual if exists, or use defaults
    const totalDeds = hasManual ? (parseFloat(window.currentDrawerUser.sixth_amount) || 0) : (pfEmployer + 200 + 817);

    document.getElementById('payslip_pf').value = pfEmployer;
    document.getElementById('payslip_pt').value = 200;
    document.getElementById('payslip_medical').value = 817;
    const baseSplit = pfEmployer + 200 + 817;
    const customDed = totalDeds - baseSplit;
    document.getElementById('payslip_custom_deduction').value = customDed > 0 ? customDed : 0;

    window.payslipPayrollMeta = { payDenominator: 30, sundaysArePaid: true, calendarDays: 30, sundayCount: 0, workingDays: 30 };
    const calEl = document.getElementById('payslip_calendar_days');
    const sunEl = document.getElementById('payslip_sunday_count');
    const workEl = document.getElementById('payslip_working_days');
    if (calEl) calEl.value = 30;
    if (sunEl) sunEl.value = 0;
    if (workEl) workEl.value = 30;
    document.getElementById('payslip_total_days').value = 30;
    document.getElementById('payslip_lops').value = 0;

    calculatePayslip();
    fetchAttendanceForPayslip();

    const modal = new bootstrap.Modal(document.getElementById('payslipGeneratorModal'));
    modal.show();
};

window.fetchAttendanceForPayslip = function () {
    const userId = window.currentDrawerUser.id;
    const month = document.getElementById('payslip_month').value;
    const year = document.getElementById('payslip_year').value;

    fetch(`payslips_api.php?action=attendance_summary&user_id=${userId}&month=${month}&year=${year}&_=${Date.now()}`)
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success' && res.data) {
                const d = res.data;
                const calendarDays = d.days_in_month || 30;
                const sundayCount = d.sunday_count ?? 0;
                const workingDays = d.working_days ?? (calendarDays - sundayCount);
                const payDenominator = d.pay_denominator ?? (d.sundays_are_paid ? calendarDays : workingDays);
                const paidDays = d.paid_days !== undefined ? d.paid_days : 0;
                const lops = d.lops !== undefined ? d.lops : Math.max(0, payDenominator - paidDays);

                window.payslipPayrollMeta = {
                    payDenominator,
                    sundaysArePaid: !!d.sundays_are_paid,
                    calendarDays,
                    sundayCount,
                    workingDays
                };

                const calEl = document.getElementById('payslip_calendar_days');
                const sunEl = document.getElementById('payslip_sunday_count');
                const workEl = document.getElementById('payslip_working_days');
                if (calEl) calEl.value = calendarDays;
                if (sunEl) sunEl.value = sundayCount;
                if (workEl) workEl.value = workingDays;

                document.getElementById('payslip_total_days').value = payDenominator;
                const sundaysPaidEl = document.getElementById('payslip_sundays_are_paid');
                if (sundaysPaidEl) sundaysPaidEl.value = d.sundays_are_paid ? '1' : '0';

                document.getElementById('payslip_lops').value = Math.round(lops * 100) / 100;
                const paidDaysInput = document.getElementById('payslip_paid_days');
                if (paidDaysInput) paidDaysInput.value = Math.round(paidDays * 100) / 100;

                calculatePayslip();
            }
        })
        .catch(err => console.error("Error fetching attendance for payslip:", err));
};

function getPayslipPayDenominator() {
    const meta = window.payslipPayrollMeta;
    if (meta && meta.payDenominator > 0) return meta.payDenominator;
    const work = parseFloat(document.getElementById('payslip_working_days')?.value);
    if (work > 0) return work;
    return parseFloat(document.getElementById('payslip_total_days')?.value) || 30;
}

window.calculatePayslip = function () {
    const payBase = getPayslipPayDenominator();
    const month = document.getElementById('payslip_month').value;
    const year = document.getElementById('payslip_year').value;
    const monthNames = ["", "January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];

    const periodEl = document.getElementById('preview_period');
    if (periodEl) periodEl.innerText = `${monthNames[month]} ${year}`;

    const paidDaysInput = document.getElementById('payslip_paid_days');
    const lopsInput = parseFloat(document.getElementById('payslip_lops').value) || 0;
    let paidDays;
    if (paidDaysInput && document.activeElement !== paidDaysInput && paidDaysInput.value !== '') {
        paidDays = parseFloat(paidDaysInput.value) || 0;
    } else {
        paidDays = Math.max(0, payBase - lopsInput);
    }
    const lops = Math.max(0, payBase - paidDays);

    // Keep paid days input in sync (only when not being manually edited)
    if (paidDaysInput && document.activeElement !== paidDaysInput) {
        paidDaysInput.value = Math.round(paidDays * 100) / 100;
    }
    // Keep lops in sync
    if (document.activeElement !== document.getElementById('payslip_lops')) {
        document.getElementById('payslip_lops').value = Math.round(lops * 100) / 100;
    }

    // Base values (respect manual overrides in the fields)
    const basic = parseFloat(document.getElementById('payslip_basic').value) || 0;
    const hra = parseFloat(document.getElementById('payslip_hra').value) || 0;
    const conv = parseFloat(document.getElementById('payslip_conveyance').value) || 0;
    const spec = parseFloat(document.getElementById('payslip_special').value) || 0;

    // Custom values
    const bonus = parseFloat(document.getElementById('payslip_bonus').value) || 0;
    const pf = parseFloat(document.getElementById('payslip_pf').value) || 0;
    const pt = parseFloat(document.getElementById('payslip_pt').value) || 0;
    const medical = parseFloat(document.getElementById('payslip_medical').value) || 0;
    const customDed = parseFloat(document.getElementById('payslip_custom_deduction').value) || 0;

    // LOP deduction calculation
    const grossBase = basic + hra + conv + spec;
    const lopAmount = payBase > 0 ? Math.round(grossBase * (lops / payBase)) : 0;

    const totalEarnings = grossBase + bonus;
    const totalDeductions = pf + pt + medical + customDed + lopAmount;
    const netPay = totalEarnings - totalDeductions;

    // Update Preview Elements
    if (document.getElementById('preview_name')) {
        document.getElementById('preview_name').innerText = window.currentDrawerUser ? window.currentDrawerUser.username : '---';
    }

    if (document.getElementById('preview_basic')) document.getElementById('preview_basic').innerText = `₹${basic.toLocaleString()}`;
    if (document.getElementById('preview_hra')) document.getElementById('preview_hra').innerText = `₹${hra.toLocaleString()}`;
    if (document.getElementById('preview_conveyance')) document.getElementById('preview_conveyance').innerText = `₹${conv.toLocaleString()}`;
    if (document.getElementById('preview_special')) document.getElementById('preview_special').innerText = `₹${spec.toLocaleString()}`;

    if (document.getElementById('preview_pf')) document.getElementById('preview_pf').innerText = `₹${pf.toLocaleString()}`;
    if (document.getElementById('preview_pt')) document.getElementById('preview_pt').innerText = `₹${pt.toLocaleString()}`;
    if (document.getElementById('preview_medical')) document.getElementById('preview_medical').innerText = `₹${medical.toLocaleString()}`;
    if (document.getElementById('preview_custom_ded')) document.getElementById('preview_custom_ded').innerText = `₹${customDed.toLocaleString()}`;

    if (document.getElementById('preview_lop_row')) {
        const lopRow = document.getElementById('preview_lop_row');
        if (lopAmount > 0) {
            lopRow.style.display = 'table-row';
            document.getElementById('preview_lop_amount').innerText = `(₹${lopAmount.toLocaleString()})`;
        } else {
            lopRow.style.display = 'none';
        }
    }

    const netPayPreview = document.getElementById('preview_net_pay');
    if (netPayPreview) {
        netPayPreview.innerText = `Net Pay: ₹${Math.round(netPay).toLocaleString()}`;
        netPayPreview.dataset.raw = netPay;
    }

    const lopAmtEl = document.getElementById('preview_lop_amount');
    if (lopAmtEl) {
        lopAmtEl.dataset.raw = lopAmount;
    }

    // Update words
    const previewWords = document.getElementById('preview_words');
    if (previewWords) {
        previewWords.innerText = netPay > 0 ? (numberToWords(Math.round(netPay)) + " Rupees Only") : "Zero Rupees Only";
    }
};

window.syncLops = function() {
    const payBase = getPayslipPayDenominator();
    const paidDays = parseFloat(document.getElementById('payslip_paid_days').value) || 0;
    const lops = Math.max(0, payBase - paidDays);
    document.getElementById('payslip_lops').value = lops;
    
    calculatePayslip();
};

// Helper function for Number to Words (Indian Format)
function numberToWords(num) {
    const ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    const tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

    if (num === 0) return 'Zero';

    function convert(n) {
        if (n < 20) return ones[n];
        if (n < 100) return tens[Math.floor(n / 10)] + (n % 10 !== 0 ? ' ' + ones[n % 10] : '');
        if (n < 1000) return ones[Math.floor(n / 100)] + ' Hundred' + (n % 100 !== 0 ? ' and ' + convert(n % 100) : '');
        return '';
    }

    let result = '';
    if (num >= 10000000) {
        result += convert(Math.floor(num / 10000000)) + ' Crore ';
        num %= 10000000;
    }
    if (num >= 100000) {
        result += convert(Math.floor(num / 100000)) + ' Lakh ';
        num %= 100000;
    }
    if (num >= 1000) {
        result += convert(Math.floor(num / 1000)) + ' Thousand ';
        num %= 1000;
    }
    result += convert(num);
    return result.trim();
}

window.savePayslip = function () {
    const userId = window.currentDrawerUser.id;
    const month = document.getElementById('payslip_month').value;
    const year = document.getElementById('payslip_year').value;
    const netPay = parseFloat(document.getElementById('preview_net_pay').dataset.raw) || 0;

    // Build JSON payload
    const paidDaysEl = document.getElementById('payslip_paid_days');
    const paidDays = paidDaysEl ? (parseFloat(paidDaysEl.value) || 0) : 0;
    const meta = window.payslipPayrollMeta || {};
    const payDenominator = getPayslipPayDenominator();

    const payslipData = {
        month: month,
        year: year,
        calendar_days: meta.calendarDays || document.getElementById('payslip_calendar_days')?.value,
        sunday_count: meta.sundayCount ?? document.getElementById('payslip_sunday_count')?.value,
        working_days: meta.workingDays || document.getElementById('payslip_working_days')?.value,
        sundays_are_paid: meta.sundaysArePaid !== false,
        pay_denominator: payDenominator,
        total_days: payDenominator,
        paid_days: paidDays,
        lops: document.getElementById('payslip_lops').value,
        lop_amount: parseFloat(document.getElementById('preview_lop_amount').dataset.raw) || 0,
        earnings: {
            basic: document.getElementById('payslip_basic').value,
            hra: document.getElementById('payslip_hra').value,
            conveyance: document.getElementById('payslip_conveyance').value,
            special: document.getElementById('payslip_special').value,
            bonus: document.getElementById('payslip_bonus').value
        },
        deductions: {
            pf: document.getElementById('payslip_pf').value,
            pt: document.getElementById('payslip_pt').value,
            medical: document.getElementById('payslip_medical').value,
            custom: document.getElementById('payslip_custom_deduction').value
        },
        net_pay: netPay
    };

    const formData = new FormData();
    formData.append('action', 'save');
    formData.append('user_id', userId);
    formData.append('month', month);
    formData.append('year', year);
    formData.append('net_pay', netPay);
    formData.append('payslip_data', JSON.stringify(payslipData));

    fetch('payslips_api.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success') {
                Swal.fire('Saved!', 'Payslip generated successfully.', 'success');
                bootstrap.Modal.getInstance(document.getElementById('payslipGeneratorModal')).hide();
                loadPayslipHistory(userId);
            } else {
                Swal.fire('Error', res.message, 'error');
            }
        })
        .catch(err => {
            console.error(err);
            Swal.fire('Error', 'Failed to save payslip.', 'error');
        });
};

function parsePayslipMonthYear(monthYear) {
    let month = 0;
    let year = 0;
    if (!monthYear) return { month, year };

    const parts = String(monthYear).trim().split(/\s+/);
    const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    if (parts.length >= 2) {
        const mPart = parts[0];
        const yPart = parseInt(parts[parts.length - 1], 10);
        const nameIdx = monthNames.indexOf(mPart.substring(0, 3));
        if (nameIdx >= 0) {
            month = nameIdx + 1;
        } else {
            const numericMonth = parseInt(mPart, 10);
            if (numericMonth >= 1 && numericMonth <= 12) month = numericMonth;
        }
        if (!isNaN(yPart)) year = yPart;
    }
    return { month, year };
}

function fetchJsonOrThrow(response) {
    return response.text().then((text) => {
        const trimmed = (text || '').trim();
        if (!trimmed) {
            throw new Error('Empty response from server.');
        }
        if (trimmed.charAt(0) === '<') {
            console.error('Non-JSON payslip response:', trimmed.substring(0, 400));
            throw new Error('Could not load payslip data. Please refresh and try again.');
        }
        try {
            return JSON.parse(trimmed);
        } catch (e) {
            console.error('Invalid JSON payslip response:', trimmed.substring(0, 400));
            throw new Error('Could not read payslip data from server.');
        }
    });
}

function fetchPayslipData(id, monthYear, source = '') {
    if (!window.currentDrawerUser) {
        return Promise.reject(new Error('No user selected'));
    }
    const userId = window.currentDrawerUser.id;
    const { month, year } = parsePayslipMonthYear(monthYear);
    const src = source || '';

    let fetchUrl = `payslips_api.php?action=get_payslip&user_id=${userId}`;
    if (month > 0 && year > 0) {
        fetchUrl += `&month=${month}&year=${year}`;
        if (src === 'payroll') {
            fetchUrl += `&source=payroll`;
        }
        if (id) {
            fetchUrl += `&id=${id}`;
        }
    } else if (id) {
        fetchUrl += `&id=${id}`;
        if (src === 'payroll') {
            fetchUrl += `&source=payroll`;
        }
    } else {
        return Promise.reject(new Error('Missing payslip period information.'));
    }

    return fetch(fetchUrl)
        .then(fetchJsonOrThrow)
        .then(res => {
            if (res.status === 'success' && res.data) {
                return res.data;
            }
            const msg = res.message || 'Could not find payslip record.';
            return Promise.reject(new Error(msg));
        });
}

window.previewPayslip = function (id, monthYear, source = '') {
    if (!window.currentDrawerUser) return;
    const userName = window.currentDrawerUser.username;
    const userRole = window.currentDrawerUser.user_type || 'Employee';
    const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    const { month, year } = parsePayslipMonthYear(monthYear);
    const periodLabel = (month > 0 && year > 0) ? `${months[month - 1]} ${year}` : (monthYear || 'Payslip');

    Swal.fire({
        title: 'Loading preview…',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });

    fetchPayslipData(id, monthYear, source)
        .then(data => {
            Swal.close();
            openDocumentPreviewModal(
                `Payslip — ${periodLabel} — ${userName}`,
                buildPayslipPreviewDocument(data, userName, userRole)
            );
        })
        .catch(err => {
            Swal.close();
            console.error('Payslip preview error:', err);
            Swal.fire('Error', err.message || 'Failed to load payslip for preview.', 'error');
        });
};

window.previewPayslipFromGenerator = function () {
    if (!window.currentDrawerUser) return;
    calculatePayslip();

    const userName = window.currentDrawerUser.username;
    const userRole = window.currentDrawerUser.user_type || 'Employee';
    const month = parseInt(document.getElementById('payslip_month').value, 10);
    const year = parseInt(document.getElementById('payslip_year').value, 10);
    const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

    const basic = parseFloat(document.getElementById('payslip_basic').value) || 0;
    const hra = parseFloat(document.getElementById('payslip_hra').value) || 0;
    const conv = parseFloat(document.getElementById('payslip_conveyance').value) || 0;
    const spec = parseFloat(document.getElementById('payslip_special').value) || 0;
    const bonus = parseFloat(document.getElementById('payslip_bonus').value) || 0;
    const pf = parseFloat(document.getElementById('payslip_pf').value) || 0;
    const pt = parseFloat(document.getElementById('payslip_pt').value) || 0;
    const medical = parseFloat(document.getElementById('payslip_medical').value) || 0;
    const customDed = parseFloat(document.getElementById('payslip_custom_deduction').value) || 0;
    const totalDays = parseFloat(document.getElementById('payslip_total_days').value) || 30;
    const lops = parseFloat(document.getElementById('payslip_lops').value) || 0;
    const lopAmount = parseFloat(document.getElementById('preview_lop_amount')?.dataset?.raw) || 0;
    const netPay = parseFloat(document.getElementById('preview_net_pay')?.dataset?.raw) || 0;

    const data = {
        month,
        year,
        month_year: `${months[month - 1]} ${year}`,
        total_days: totalDays,
        lops,
        lop_amount: lopAmount,
        earnings: { basic, hra, conveyance: conv, special: spec, bonus },
        deductions: { pf, pt, medical, custom: customDed },
        net_pay: netPay
    };

    openDocumentPreviewModal(
        `Payslip — ${months[month - 1]} ${year} — Editor preview (unsaved)`,
        buildPayslipPreviewDocument(data, userName, userRole)
    );
};

window.printPayslip = function (id, monthYear, source = '') {
    if (!window.currentDrawerUser) return;
    const userName = window.currentDrawerUser.username;
    const userRole = window.currentDrawerUser.user_type || 'Employee';

    fetchPayslipData(id, monthYear, source)
        .then(data => renderAndPrintPayslip(data, userName, userRole))
        .catch(err => {
            console.error('Print error:', err);
            Swal.fire('Error', err.message || 'Failed to load payslip data.', 'error');
        });
};

function getPayslipOutputStyles() {
    return `
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding: 50px; color: #333; line-height: 1.5; background: #fff; }
        .header { text-align: center; margin-bottom: 40px; }
        .header h1 { margin: 0; color: #005691; font-size: 32px; font-weight: 800; }
        .header p { margin: 10px 0; color: #666; font-size: 18px; }
        .hr-line { border-top: 2px solid #005691; margin: 20px 0; }
        .details-table { width: 100%; margin-bottom: 30px; border-collapse: collapse; }
        .details-table td { padding: 12px 15px; border: 1px solid #e2e8f0; font-size: 15px; }
        .details-table strong { color: #444; font-weight: 700; }
        .section-row { display: flex; gap: 20px; margin-bottom: 5px; }
        .section-title { flex: 1; font-size: 14px; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-bottom: 10px; }
        .salary-container { border: 1px solid #cbd5e1; border-radius: 4px; overflow: hidden; margin-bottom: 30px; }
        .salary-table { width: 100%; border-collapse: collapse; }
        .inner-table { width: 100%; border-collapse: collapse; }
        .inner-table td { padding: 10px 15px; border: 1px solid #e2e8f0; font-size: 14px; }
        .totals-row { background: #fff; font-weight: 700; font-size: 15px; }
        .totals-row td { padding: 15px; border: 1px solid #cbd5e1; }
        .net-pay-box { margin-top: 40px; padding: 25px; border: 1px solid #e2e8f0; border-radius: 12px; background: #fff; text-align: right; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
        .net-pay-box span { font-size: 24px; font-weight: 800; color: #1e293b; }
        .amount-words { text-align: right; color: #64748b; font-size: 13px; font-style: italic; margin-top: 8px; }
        .footer-note { text-align: center; color: #777; margin-top: 50px; font-size: 12px; }
    `;
}

function buildPayslipBodyHtml(data, userName, userRole = 'Employee') {
    const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    let monthLabel = data.month_year || '';
    if (!monthLabel && data.month) monthLabel = `${months[data.month - 1]} ${data.year}`;

    const e = data.earnings || { basic: 0, hra: 0, conveyance: 0, special: 0, bonus: 0 };
    const d = data.deductions || { pf: 0, pt: 0, medical: 0, custom: 0 };

    const totalE = parseFloat(e.basic || 0) + parseFloat(e.hra || 0) + parseFloat(e.conveyance || 0) + parseFloat(e.special || 0) + parseFloat(e.bonus || 0);
    const lop_amount = parseFloat(data.lop_amount || 0);
    const totalD = parseFloat(d.pf || 0) + parseFloat(d.pt || 0) + parseFloat(d.medical || 0) + parseFloat(d.custom || 0) + lop_amount;

    const lops = data.lops || 0;
    const total_days = data.total_days || 30;
    const netPay = data.net_pay ?? (totalE - totalD);
    const amountWords = netPay > 0 ? (numberToWords(Math.round(netPay)) + ' Rupees Only') : 'Zero Rupees Only';

    return `
    <div class="header">
        <h1>Search Homes India Pvt Ltd</h1>
        <p>Payslip for the period of ${monthLabel}</p>
    </div>
    <div class="hr-line"></div>
    <table class="details-table">
        <tr>
            <td style="width: 50%;"><strong>Employee Name:</strong> ${userName}</td>
            <td style="width: 50%;"><strong>Designation:</strong> ${userRole}</td>
        </tr>
        <tr>
            <td><strong>${data.sundays_are_paid === false ? 'Working Days' : 'Total Days'}:</strong> ${data.pay_denominator || data.working_days || total_days}</td>
            <td><strong>Loss of Pay (Days):</strong> ${lops}</td>
        </tr>
        ${data.sundays_are_paid === false && data.sunday_count != null ? `<tr><td colspan="2"><strong>Calendar:</strong> ${data.calendar_days || total_days} days &nbsp;|&nbsp; <strong>Sundays (excluded):</strong> ${data.sunday_count}</td></tr>` : ''}
    </table>
    <div class="section-row">
        <div class="section-title">Earnings</div>
        <div class="section-title">Deductions</div>
    </div>
    <div class="salary-container">
        <table class="salary-table">
            <tr>
                <td style="width: 50%; vertical-align: top; padding: 10px;">
                    <table class="inner-table">
                        <tr><td style="width: 70%;">Basic</td><td style="text-align: right;">₹${parseFloat(e.basic || 0).toLocaleString()}</td></tr>
                        <tr><td>HRA</td><td style="text-align: right;">₹${parseFloat(e.hra || 0).toLocaleString()}</td></tr>
                        <tr><td>Conveyance</td><td style="text-align: right;">₹${parseFloat(e.conveyance || 0).toLocaleString()}</td></tr>
                        <tr><td>Special Allowance</td><td style="text-align: right;">₹${parseFloat(e.special || 0).toLocaleString()}</td></tr>
                        ${e.bonus > 0 ? `<tr><td>Bonus/Incentive</td><td style="text-align: right;">₹${parseFloat(e.bonus).toLocaleString()}</td></tr>` : '<tr><td style="border:none;">&nbsp;</td><td style="border:none;">&nbsp;</td></tr>'}
                    </table>
                </td>
                <td style="width: 50%; vertical-align: top; padding: 10px;">
                    <table class="inner-table">
                        <tr><td style="width: 70%;">PF</td><td style="text-align: right;">₹${parseFloat(d.pf || 0).toLocaleString()}</td></tr>
                        <tr><td>Professional Tax</td><td style="text-align: right;">₹${parseFloat(d.pt || 0).toLocaleString()}</td></tr>
                        <tr><td>Medical Benefit</td><td style="text-align: right;">₹${parseFloat(d.medical || 0).toLocaleString()}</td></tr>
                        ${d.custom > 0 ? `<tr><td>Other Deductions</td><td style="text-align: right;">₹${parseFloat(d.custom).toLocaleString()}</td></tr>` : '<tr><td style="border:none;">&nbsp;</td><td style="border:none;">&nbsp;</td></tr>'}
                        ${lop_amount > 0 ? `<tr><td>LOP Deduction</td><td style="text-align: right;">₹${parseFloat(lop_amount).toLocaleString()}</td></tr>` : ''}
                    </table>
                </td>
            </tr>
            <tr class="totals-row">
                <td style="border-right: 1px solid #cbd5e1;">
                    <div style="display: flex; justify-content: space-between; padding: 5px 10px;">
                        <span>Total Earnings:</span>
                        <span>₹${totalE.toLocaleString()}</span>
                    </div>
                </td>
                <td>
                    <div style="display: flex; justify-content: space-between; padding: 5px 10px;">
                        <span>Total Deductions:</span>
                        <span>₹${totalD.toLocaleString()}</span>
                    </div>
                </td>
            </tr>
        </table>
    </div>
    <div class="net-pay-box">
        <span>Net Pay: ₹${Math.round(netPay).toLocaleString()}</span>
        <div class="amount-words">${amountWords}</div>
    </div>
    <p class="footer-note">This is a computer-generated document and does not require a signature.</p>`;
}

function buildPayslipPreviewDocument(data, userName, userRole = 'Employee') {
    const bodyHtml = buildPayslipBodyHtml(data, userName, userRole);
    return `<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Payslip - ${userName}</title><style>${getPayslipOutputStyles()}</style></head><body>${bodyHtml}</body></html>`;
}

function renderAndPrintPayslip(data, userName, userRole = 'Employee') {
    const html = buildPayslipPreviewDocument(data, userName, userRole)
        + '<script>window.onload = function() { window.print(); window.close(); }<\/script>';

    const printWindow = window.open('', '_blank');
    if (printWindow) {
        printWindow.document.write(html);
        printWindow.document.close();
    } else {
        Swal.fire('Error', 'Popup blocked. Please allow popups for this site.', 'error');
    }
};

window.openAddNewUserModal = function () {
    if (typeof clearUserForm === 'function') clearUserForm();
    else {
        const form = document.getElementById('userForm');
        if (form) form.reset();
        const userIdField = document.getElementById('userId');
        if (userIdField) userIdField.value = '';
    }

    // Hide status field for new application
    const statusField = document.getElementById('statusFieldGroup');
    if (statusField) statusField.style.display = 'none';

    // Set modal title
    const modalTitle = document.getElementById('addEditModalLabel');
    if (modalTitle) modalTitle.innerText = 'New User Application';

    const passwordField = document.getElementById('userPassword');
    if (passwordField) passwordField.setAttribute('required', '');

    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('addEditModal'));
    modal.show();
};

window.toggleUserActivation = function () {
    if (!window.currentDrawerUser || !window.currentDrawerUser.id) return;

    const toggle = document.getElementById('userActivationToggle');
    const isActivating = toggle.checked;
    const userId = window.currentDrawerUser.id;

    // Internal helper to handle the actual fetch call
    const performStatusUpdate = (status) => {
        const isActivatingStatus = (status === 1);
        const formData = new FormData();
        formData.append('action', 'update_user_status');
        formData.append('id', userId);
        formData.append('status', status);

        fetch('action.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(res => {
                if (res.status === 'success') {
                    const statusText = document.getElementById('drawer_status_text');
                    if (statusText) {
                        statusText.innerText = isActivatingStatus ? 'Active' : 'Inactive';
                        statusText.className = isActivatingStatus ? 'ms-2 fw-bold text-success' : 'ms-2 fw-bold text-danger';
                    }

                    const createdAtContainer = document.getElementById("drawer_created_at_container");
                    const inactiveAtContainer = document.getElementById("drawer_inactive_at_container");

                    if (isActivatingStatus) {
                        if (createdAtContainer) createdAtContainer.style.display = "block";
                        if (inactiveAtContainer) inactiveAtContainer.style.display = "none";
                    } else {
                        if (createdAtContainer) createdAtContainer.style.display = "none";
                        if (inactiveAtContainer) inactiveAtContainer.style.display = "block";

                        // Set inactive_at to current time in the UI
                        const inactiveAtEl = document.getElementById("drawer_inactive_at");
                        if (inactiveAtEl) {
                            const now = new Date();
                            const yyyy = now.getFullYear();
                            const mm = String(now.getMonth() + 1).padStart(2, '0');
                            const dd = String(now.getDate()).padStart(2, '0');
                            const hh = String(now.getHours()).padStart(2, '0');
                            const min = String(now.getMinutes()).padStart(2, '0');
                            const ss = String(now.getSeconds()).padStart(2, '0');
                            inactiveAtEl.innerText = `${yyyy}-${mm}-${dd} ${hh}:${min}:${ss}`;
                        }
                    }

                    Swal.fire({
                        title: 'Status Updated',
                        text: `User is now ${isActivatingStatus ? 'Active' : 'Inactive'}.`,
                        icon: 'success',
                        timer: 1500,
                        showConfirmButton: false
                    });
                    // Refresh table
                    if (typeof loadUsers === 'function') loadUsers();
                    else if (typeof fetchUsersData === 'function') fetchUsersData();
                    else location.reload();
                } else {
                    Swal.fire('Error', res.message, 'error');
                    toggle.checked = !isActivatingStatus; // Revert
                }
            })
            .catch(err => {
                console.error(err);
                Swal.fire('Error', 'Failed to update status.', 'error');
                toggle.checked = !isActivatingStatus; // Revert
            });
    };

    if (isActivating) {
        // Check if documents are generated
        const appStatus = document.getElementById('appointment_letter_status').innerText.includes('Generated');
        const offerStatus = document.getElementById('offer_letter_status').innerText.includes('Generated');

        if (!appStatus || !offerStatus) {
            Swal.fire({
                title: 'Activation Blocked',
                text: 'You must generate both the Appointment Letter and Offer Letter before activating this user.',
                icon: 'warning',
                confirmButtonColor: '#4f46e5'
            });
            toggle.checked = false; // Revert toggle
            return;
        }
        performStatusUpdate(1);
    } else {
        // Inactivation check: Must return all assets first
        fetch(`action.php?fetch_user_assets=1&user_id=${userId}`)
            .then(res => res.json())
            .then(data => {
                if (data && data.length > 0) {
                    Swal.fire({
                        title: 'Inactivation Blocked',
                        text: `This user has ${data.length} assigned asset(s). All company assets must be returned or cleared before inactivating the user.`,
                        icon: 'warning',
                        confirmButtonColor: '#4f46e5'
                    });
                    toggle.checked = true; // Revert toggle to Active
                } else {
                    // No assets, proceed with inactivation
                    performStatusUpdate(0);
                }
            })
            .catch(err => {
                console.error("Error checking assets:", err);
                Swal.fire('Error', 'Failed to verify user assets. Please try again.', 'error');
                toggle.checked = true; // Revert
            });
    }
};

/* ================= SIGNATURE CREATOR LOGIC ================= */
(function() {
    let canvas, ctx;
    let isDrawing = false;
    let lastX = 0, lastY = 0;
    let points = [];
    let strokeColor = '#000000';
    let canvasHasDrawing = false;
    let selectedFont = 'Mrs Saint Delafield';

    // Type signature details
    let typeCanvas, typeCtx;

    // Initialize all components
    function initSignatureSuite() {
        // Draw Canvas
        canvas = document.getElementById('signature-pad');
        if (canvas) {
            ctx = canvas.getContext('2d');
            
            // Set up event listeners for drawing
            canvas.removeEventListener('mousedown', startDrawing);
            canvas.removeEventListener('mousemove', draw);
            canvas.removeEventListener('mouseup', stopDrawing);
            canvas.removeEventListener('mouseleave', stopDrawing);
            canvas.addEventListener('mousedown', startDrawing);
            canvas.addEventListener('mousemove', draw);
            canvas.addEventListener('mouseup', stopDrawing);
            canvas.addEventListener('mouseleave', stopDrawing);

            canvas.removeEventListener('touchstart', startDrawing);
            canvas.removeEventListener('touchmove', draw);
            canvas.removeEventListener('touchend', stopDrawing);
            canvas.addEventListener('touchstart', startDrawing, { passive: false });
            canvas.addEventListener('touchmove', draw, { passive: false });
            canvas.addEventListener('touchend', stopDrawing);
        }

        // Color Swatches
        document.querySelectorAll('#signatureModal .btn-color-swatch').forEach(btn => {
            btn.replaceWith(btn.cloneNode(true)); // remove old listeners
        });
        document.querySelectorAll('#signatureModal .btn-color-swatch').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('#signatureModal .btn-color-swatch').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                strokeColor = this.getAttribute('data-color');
            });
        });

        // Clear Button
        const clearBtn = document.getElementById('clear-draw-btn');
        if (clearBtn) {
            clearBtn.replaceWith(clearBtn.cloneNode(true));
            document.getElementById('clear-draw-btn').addEventListener('click', clearDrawCanvas);
        }

        // Type Input
        const typeInput = document.getElementById('sign_name_input');
        if (typeInput) {
            typeInput.replaceWith(typeInput.cloneNode(true));
            document.getElementById('sign_name_input').addEventListener('input', renderTypeSignature);
        }

        // Type Font Selector Pills
        document.querySelectorAll('#type-font-selectors .font-pill').forEach(btn => {
            btn.replaceWith(btn.cloneNode(true));
        });
        document.querySelectorAll('#type-font-selectors .font-pill').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('#type-font-selectors .font-pill').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                selectedFont = this.getAttribute('data-font');
                renderTypeSignature();
            });
        });

        // Type Canvas
        typeCanvas = document.getElementById('type-signature-canvas');
        if (typeCanvas) {
            typeCtx = typeCanvas.getContext('2d');
        }

        // Drag & Drop Zone
        const uploadZone = document.getElementById('sign-upload-zone');
        const fileInput = document.getElementById('sign_file_input');
        if (uploadZone && fileInput) {
            const newUploadZone = uploadZone.cloneNode(true);
            uploadZone.parentNode.replaceChild(newUploadZone, uploadZone);
            
            const newFileInput = fileInput.cloneNode(true);
            fileInput.parentNode.replaceChild(newFileInput, fileInput);

            const activeUploadZone = document.getElementById('sign-upload-zone');
            const activeFileInput = document.getElementById('sign_file_input');

            activeUploadZone.addEventListener('click', () => activeFileInput.click());
            
            ['dragenter', 'dragover'].forEach(eventName => {
                activeUploadZone.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    activeUploadZone.classList.add('drag-over');
                }, false);
            });

            ['dragleave', 'drop'].forEach(eventName => {
                activeUploadZone.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    activeUploadZone.classList.remove('drag-over');
                }, false);
            });

            activeUploadZone.addEventListener('drop', (e) => {
                const dt = e.dataTransfer;
                const files = dt.files;
                if (files.length) {
                    activeFileInput.files = files;
                    handleUpload(files[0]);
                }
            });

            activeFileInput.addEventListener('change', function() {
                if (this.files.length) {
                    handleUpload(this.files[0]);
                }
            });
        }

        // Remove Uploaded Image
        const removeUploadBtn = document.getElementById('remove-upload-btn');
        if (removeUploadBtn) {
            removeUploadBtn.replaceWith(removeUploadBtn.cloneNode(true));
            document.getElementById('remove-upload-btn').addEventListener('click', function() {
                document.getElementById('uploadPreviewImg').src = '';
                document.getElementById('uploadPreviewContainer').style.display = 'none';
                document.getElementById('sign-upload-zone').style.display = 'block';
                const fInput = document.getElementById('sign_file_input');
                if (fInput) fInput.value = '';
            });
        }

        // Unified Use Button
        const useBtn = document.getElementById('useSignatureBtn');
        if (useBtn) {
            useBtn.replaceWith(useBtn.cloneNode(true));
            document.getElementById('useSignatureBtn').addEventListener('click', handleUseSignature);
        }
    }

    function getCoords(e) {
        if (!canvas) return { x: 0, y: 0 };
        const rect = canvas.getBoundingClientRect();
        const clientX = e.touches ? e.touches[0].clientX : e.clientX;
        const clientY = e.touches ? e.touches[0].clientY : e.clientY;
        return {
            x: (clientX - rect.left) * (canvas.width / rect.width),
            y: (clientY - rect.top) * (canvas.height / rect.height)
        };
    }

    function startDrawing(e) {
        if (e.cancelable) e.preventDefault();
        isDrawing = true;
        const coords = getCoords(e);
        points = [coords];
        lastX = coords.x;
        lastY = coords.y;
        
        ctx.beginPath();
        ctx.arc(coords.x, coords.y, 1.25, 0, Math.PI * 2, true);
        ctx.fillStyle = strokeColor;
        ctx.fill();
        canvasHasDrawing = true;
    }

    function draw(e) {
        if (!isDrawing) return;
        if (e.cancelable) e.preventDefault();
        const coords = getCoords(e);
        points.push(coords);

        ctx.lineWidth = 2.5;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.strokeStyle = strokeColor;

        if (points.length > 2) {
            const xc = (points[points.length - 2].x + points[points.length - 1].x) / 2;
            const yc = (points[points.length - 2].y + points[points.length - 1].y) / 2;
            ctx.beginPath();
            ctx.moveTo(lastX, lastY);
            ctx.quadraticCurveTo(points[points.length - 2].x, points[points.length - 2].y, xc, yc);
            ctx.stroke();
            lastX = xc;
            lastY = yc;
        } else if (points.length === 2) {
            ctx.beginPath();
            ctx.moveTo(points[0].x, points[0].y);
            ctx.lineTo(points[1].x, points[1].y);
            ctx.stroke();
            lastX = (points[0].x + points[1].x) / 2;
            lastY = (points[0].y + points[1].y) / 2;
        }
        canvasHasDrawing = true;
    }

    function stopDrawing() {
        isDrawing = false;
        points = [];
    }

    function clearDrawCanvas() {
        if (ctx && canvas) {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            canvasHasDrawing = false;
        }
    }

    function renderTypeSignature() {
        if (!typeCtx || !typeCanvas) return;
        const nameInput = document.getElementById('sign_name_input');
        const name = nameInput ? nameInput.value.trim() : '';
        
        typeCtx.clearRect(0, 0, typeCanvas.width, typeCanvas.height);
        if (!name) return;

        typeCtx.fillStyle = '#000000';
        typeCtx.textAlign = 'center';
        typeCtx.textBaseline = 'middle';

        let fontSize = 72;
        typeCtx.font = `${fontSize}px "${selectedFont}", cursive`;
        let textWidth = typeCtx.measureText(name).width;
        // Dynamically shrink text size to fit canvas width
        while (textWidth > typeCanvas.width - 40 && fontSize > 24) {
            fontSize -= 4;
            typeCtx.font = `${fontSize}px "${selectedFont}", cursive`;
            textWidth = typeCtx.measureText(name).width;
        }

        typeCtx.fillText(name, typeCanvas.width / 2, typeCanvas.height / 2);
    }

    function handleUpload(file) {
        if (!file) return;
        if (!file.type.match('image.*')) {
            alert('Please select an image file.');
            return;
        }
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('uploadPreviewImg').src = e.target.result;
            document.getElementById('uploadPreviewContainer').style.display = 'block';
            document.getElementById('sign-upload-zone').style.display = 'none';
        };
        reader.readAsDataURL(file);
    }

    function handleUseSignature() {
        const activeTab = document.querySelector('#signatureTab .nav-link.active');
        const activeTabId = activeTab ? activeTab.getAttribute('data-bs-target') : '#sign-draw';
        let signatureDataUrl = '';

        if (activeTabId === '#sign-draw') {
            if (!canvasHasDrawing) {
                Swal.fire('Empty signature', 'Please draw your signature first.', 'warning');
                return;
            }
            signatureDataUrl = canvas.toDataURL('image/png');
        } else if (activeTabId === '#sign-type') {
            const name = document.getElementById('sign_name_input').value.trim();
            if (!name) {
                Swal.fire('Name empty', 'Please type your name first.', 'warning');
                return;
            }
            signatureDataUrl = typeCanvas.toDataURL('image/png');
        } else if (activeTabId === '#sign-upload') {
            const imgEl = document.getElementById('uploadPreviewImg');
            signatureDataUrl = imgEl ? imgEl.src : '';
            if (!signatureDataUrl || signatureDataUrl.length < 20) {
                Swal.fire('No image', 'Please upload or drop a signature image first.', 'warning');
                return;
            }
        }

        const editor = $('#appointmentLetterEditor');
        if (!editor.length || typeof editor.summernote !== 'function') {
            alert('Editor not found. Please refresh the page.');
            return;
        }

        const role = $('#signature_role_select').val() || 'cursor';
        const signatureHtml = `<img class="signature-stamp" src="${signatureDataUrl}" alt="Signature">`;

        const inserted = insertSignatureHtmlAtCursor(editor, signatureHtml, role);
        /* Unlock the range guard so normal cursor tracking resumes */
        window._sigRangeLocked = false;

        if (inserted) {
            // Close modal
            const modalEl = document.getElementById('signatureModal');
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();

            Swal.fire({
                title: 'Applied!',
                text: 'Signature added to document.',
                icon: 'success',
                timer: 1500,
                showConfirmButton: false
            });
        } else {
            Swal.fire({
                icon: 'info',
                title: 'Place your cursor first',
                text: 'Click in the letter where you want the signature, then click Add Signature.',
                confirmButtonColor: '#2a8c90'
            });
        }
    }

    // Export window functions
    window.openSignatureModal = function() {
        const modalEl = document.getElementById('signatureModal');
        if (!modalEl) return;

        /* Keep signature above the letter editor modal (same z-index class would otherwise stack by DOM order) */
        if (modalEl.parentElement !== document.body) {
            document.body.appendChild(modalEl);
        }

        let modal = bootstrap.Modal.getInstance(modalEl);
        if (modal) {
            modal.dispose();
        }
        modal = new bootstrap.Modal(modalEl, { focus: false });
        modal.show();

        /* When the modal closes for ANY reason, unlock the range guard */
        modalEl.addEventListener('hidden.bs.modal', function _unlock() {
            window._sigRangeLocked = false;
            modalEl.removeEventListener('hidden.bs.modal', _unlock);
        });

        // Reset state on modal open
        setTimeout(() => {
            // Re-initialize elements to make sure DOM is loaded
            initSignatureSuite();

            // Clear Draw Tab
            clearDrawCanvas();
            strokeColor = '#000000';
            const defaultSwatch = document.querySelector('#signatureModal .btn-color-swatch[data-color="#000000"]');
            if (defaultSwatch) {
                document.querySelectorAll('#signatureModal .btn-color-swatch').forEach(b => b.classList.remove('active'));
                defaultSwatch.classList.add('active');
            }

            // Clear Type Tab
            const nameInput = document.getElementById('sign_name_input');
            if (nameInput) nameInput.value = '';
            selectedFont = 'Mrs Saint Delafield';
            const defaultFontBtn = document.querySelector('#type-font-selectors .font-pill[data-font="Mrs Saint Delafield"]');
            if (defaultFontBtn) {
                document.querySelectorAll('#type-font-selectors .font-pill').forEach(b => b.classList.remove('active'));
                defaultFontBtn.classList.add('active');
            }
            renderTypeSignature();

            // Clear Upload Tab
            document.getElementById('uploadPreviewImg').src = '';
            document.getElementById('uploadPreviewContainer').style.display = 'none';
            document.getElementById('sign-upload-zone').style.display = 'block';
            const fileInput = document.getElementById('sign_file_input');
            if (fileInput) fileInput.value = '';

            // Switch to Draw Tab default
            const drawTabBtn = document.getElementById('tab-draw-btn');
            if (drawTabBtn) {
                bootstrap.Tab.getOrCreateInstance(drawTabBtn).show();
            }
        }, 150);
    };

})();

// --- Signature Drag-Drop cleanup ---
document.addEventListener('dragend', function (e) {
    if (e.target && e.target.classList && e.target.classList.contains('signature-stamp')) {
        e.target.draggable = false;
        e.target.style.outline = 'none';
        e.target.style.cursor = 'pointer';
    }
});

// --- Fix for the 'Copy instead of Move' drag bug ---
document.addEventListener('dragstart', function (e) {
    if (e.target && e.target.classList && e.target.classList.contains('signature-stamp')) {
        // This tells the browser we want to MOVE the element, not copy it
        if (e.dataTransfer) {
            e.dataTransfer.effectAllowed = 'move';
        }
    }
});

document.addEventListener('drop', function (e) {
    // Small delay to let Summernote handle the drop, then ensure focus
    if (e.target && e.target.classList && e.target.classList.contains('signature-stamp')) {
        setTimeout(() => {
            if (typeof $.fn.summernote !== 'undefined') {
                $('#appointmentLetterEditor').summernote('focus');
            }
        }, 50);
    }
});

// --- Fix for Bootstrap 5 focus trap interfering with nested modals and editors ---
const bypassElements = ['#signatureModal', '.note-editor', '.note-modal', '.note-dialog'];
function checkBypass(e) {
    if (e.target && typeof e.target.closest === 'function') {
        for (let sel of bypassElements) {
            if (e.target.closest(sel)) {
                e.stopImmediatePropagation();
                return;
            }
        }
    }
}
document.addEventListener('focusin', checkBypass, true);
document.addEventListener('focus', checkBypass, true);
window.addEventListener('focusin', checkBypass, true);

document.addEventListener('DOMContentLoaded', function () {
    initLetterMobileFormatBar();
    const params = new URLSearchParams(window.location.search);
    const userId = params.get('open_user_id');
    const documentType = params.get('document_type');
    const onUsersPage = /users\.php$/i.test(window.location.pathname || '');
    if (!onUsersPage || !userId || typeof openUserProfileDrawer !== 'function') return;

    openUserProfileDrawer(userId);
    if (documentType === 'offer_letter') {
        setTimeout(function () {
            if (typeof switchDrawerTab === 'function') {
                switchDrawerTab('assets');
            }
            setTimeout(function () {
                if (typeof openLetterModal === 'function') {
                    openLetterModal('offer_letter');
                }
            }, 700);
        }, 700);
    }
});
window.addEventListener('focus', checkBypass, true);

