var filtersApplied = false;

document.addEventListener('DOMContentLoaded', function () {
    const menuBar = document.querySelector('.sidebarbutt');
    const sideBar = document.querySelector('.sidebar');

    function toggleleftsidebar() {
        if (sideBar) sideBar.classList.toggle('close');
    }

    if (menuBar) menuBar.addEventListener('click', toggleleftsidebar);

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

    function setupProfileNotifControls() {
        const notifcontent = document.querySelector("#notif-content_box");
        const morenotificon = document.querySelector("#more_notif_icon") || document.querySelector("#notificationBtn") || document.querySelector("#more_notif_btn");
        const moreprofileicon = document.querySelector("#more_profile_icon");
        const profilecontent = document.querySelector("#profile-content_box");
        const closebtn = document.querySelector(".closebtn");
        const closebtn1 = document.querySelector(".closebtn1");

        if (morenotificon && notifcontent && profilecontent) {
            morenotificon.addEventListener("click", () => {
                notifcontent.style.display = notifcontent.style.display === "block" ? "none" : "block";
                profilecontent.style.display = "none";
            });
        }

        if (closebtn && profilecontent && notifcontent) {
            closebtn.addEventListener("click", () => {
                profilecontent.style.display = "none";
                notifcontent.style.display = "none";
            });
        }

        if (closebtn1 && profilecontent) {
            closebtn1.addEventListener("click", function () {
                profilecontent.style.display = "none";
            });
        }
    }

    setupProfileNotifControls();
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
document.addEventListener("DOMContentLoaded", function () {
    const tableBody = document.getElementById("incentiveuser");
    if (!tableBody) return; 

    // Show loading state
    tableBody.innerHTML = `<tr><td colspan="25" style="text-align:center; padding: 20px;">Loading users...</td></tr>`;

    fetch("fetch_users.php?all=1")
        .then(res => {
            if (!res.ok) throw new Error("HTTP " + res.status);
            return res.json();
        })
        .then(data => {
            if (!Array.isArray(data)) {
                throw new Error("Invalid data format received from server.");
            }

            let rows = "";
            data.forEach(user => {
                rows += `
                <tr class="user-data-row">
                    <td class="checkbox-colu"><input type="checkbox"></td>
                    <td>${user.id ?? ""}</td>
                    <td>${user.is_active == 1 ? "Active" : "Inactive"}</td>
                    <td>${user.username ?? ""}</td>
                    <td>${truncateEmail(user.useremail)}</td>
                    <td>${user.phonenumber ?? ""}</td>
                    <td class="password-cell">
                        <span class="password-mask">••••••••</span>
                        <span class="password-text" style="display:none;">${user.epassword ?? ""}</span>
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
                    <td>${user.user_type ?? ""}</td>
                    <td>${user.assign_user ?? ""}</td>
                    <td>${user.created_at ?? ""}</td>
                    <td>${user.inactive_at ?? ""}</td>
                    <td class="user-action-cell">
                        <div class="action-icons">
                            <a href="#" id="${user.id}" class="editLink action-icon edit"><i class="bi bi-pencil-square"></i></a>
                            <a href="#" id="${user.id}" class="deleteLink action-icon delete"><i class="bi bi-trash"></i></a>
                        </div>
                        <button class="user-expand-btn" onclick="toggleUserRow(this)" title="Show details"><i class="bi bi-chevron-right"></i></button>
                    </td>
                </tr>
                <tr class="user-expand-row" style="display:none;">
                    <td colspan="25">
                        <div class="user-expand-content">
                            <div class="user-expand-grid">
                                <div class="user-expand-item"><span class="user-expand-label">Email</span><span class="user-expand-value">${truncateEmail(user.useremail)}</span></div>
                                <div class="user-expand-item"><span class="user-expand-label">Contact No</span><span class="user-expand-value">${user.phonenumber ?? ""}</span></div>
                                <div class="user-expand-item"><span class="user-expand-label">Password</span><span class="user-expand-value">${user.epassword ?? "—"}</span></div>
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

            tableBody.innerHTML = rows;

            document.querySelectorAll("#incentiveuser tr.user-data-row").forEach(row => {
                row.addEventListener("click", function (e) {
                    if (e.target.closest("button") || e.target.closest("input") || e.target.closest(".password-cell")) {
                        return;
                    }
                    if (window.innerWidth <= 1024) {
                        const expandBtn = row.querySelector(".user-expand-btn");
                        if (expandBtn) expandBtn.click();
                    }
                });
            });

            if (typeof applyDefaults === "function") applyDefaults();
            window.currentUsersPage = 1;
            updateUsersPagination();
            updateUsersSummary(); 
        })
        .catch(err => {
            console.error("Users Fetch Error:", err);
            // This is the error fallback - if you see this on your screen, fetch_users.php is broken or missing.
            tableBody.innerHTML = `<tr><td colspan="25" style="text-align:center; color:#d9534f; padding:20px; font-weight:bold;">
                Data Failed to Load. Please check 'fetch_users.php' for server errors. <br>
                <small style="color:#666; font-weight:normal;">(Press F12 and check the Console tab for exact details)</small>
            </td></tr>`;
        });
});

/* ================= USER ROW EXPAND TOGGLE ================= */
window.toggleUserRow = function (btn) {
    const dataRow = btn.closest("tr.user-data-row");
    const expandRow = dataRow.nextElementSibling;
    const icon = btn.querySelector("i");

    if (!expandRow || !expandRow.classList.contains("user-expand-row")) return;
    const isOpen = expandRow.style.display !== "none";

    document.querySelectorAll(".user-expand-row").forEach(row => { row.style.display = "none"; });
    document.querySelectorAll(".user-expand-btn i").forEach(i => { i.className = "bi bi-chevron-right"; });
    document.querySelectorAll(".user-expand-btn").forEach(b => { b.classList.remove("active"); });

    if (!isOpen) {
        expandRow.style.display = "";
        expandRow.style.animation = "accordionFade 0.25s ease";
        icon.className = "bi bi-chevron-down";
        btn.classList.add("active");
    }
};

/* ================= EDIT / DELETE USER HANDLERS ================= */
document.addEventListener("click", function (e) {
    const editBtn = e.target.closest(".editLink");
    if (editBtn) {
        e.preventDefault();
        openEditModal(editBtn.id);
        return;
    }

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
        }).then((result) => {
            if (result.isConfirmed) deleteUser(deleteBtn.id);
        });
    }
});

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
            document.getElementById("addEditModalLabel").innerText = "Edit User";
            const modal = new bootstrap.Modal(document.getElementById("addEditModal"));
            modal.show();
        }).catch(err => console.error("Edit Load Error:", err));
}

function deleteUser(userId) {
    fetch("delete_user.php?id=" + userId)
        .then(res => res.json())
        .then(data => {
            if (data.success) location.reload();
            else alert(data.message || "Delete failed");
        }).catch(err => console.error("Delete Error:", err));
}

document.getElementById("saveUserBtn")?.addEventListener("click", function () {
    const form = document.getElementById("userForm");
    if (!form) return;
    fetch("update_user.php", { method: "POST", body: new FormData(form) })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById("addEditModal")).hide();
                location.reload();
            } else alert(data.message || "Update failed");
        }).catch(err => console.error("Update Error:", err));
});

/* ================= PAGINATION & SUMMARY ================= */
function updateUsersPagination() {
    const allRows = document.querySelectorAll("#incentiveuser tr.user-data-row");
    const visibleRows = Array.from(allRows).filter(row => row.style.display !== "none");

    const limit = parseInt(document.getElementById("users-limit")?.value) || 10;
    const totalRows = visibleRows.length;
    const totalPages = Math.max(1, Math.ceil(totalRows / limit));

    let currentPage = window.currentUsersPage || 1;
    if (currentPage > totalPages) currentPage = totalPages;
    if (currentPage < 1) currentPage = 1;
    window.currentUsersPage = currentPage;

    const start = (currentPage - 1) * limit;
    const end = start + limit;

    allRows.forEach(row => {
        row.style.display = "none";
        const expandRow = row.nextElementSibling;
        if (expandRow && expandRow.classList.contains("user-expand-row")) expandRow.style.display = "none";
    });

    visibleRows.forEach((row, index) => {
        if (index >= start && index < end) row.style.display = "";
    });

    const elStart = document.getElementById("showingStart");
    if (elStart) elStart.innerText = totalRows === 0 ? 0 : start + 1;
    
    const elEnd = document.getElementById("showingEnd");
    if (elEnd) elEnd.innerText = Math.min(end, totalRows);
    
    const elTotal = document.getElementById("totalEntries");
    if (elTotal) elTotal.innerText = totalRows;
    
    const elCurrent = document.getElementById("currentPageBtn");
    if (elCurrent) elCurrent.innerText = currentPage;

    const prevBtn = document.getElementById("prevPageBtn");
    const nextBtn = document.getElementById("nextPageBtn");
    if(prevBtn) prevBtn.classList.toggle("disabled", currentPage === 1);
    if(nextBtn) nextBtn.classList.toggle("disabled", currentPage === totalPages);
}

function updateUsersSummary() {
    const rows = document.querySelectorAll("#incentiveuser tr.user-data-row");
    let activeCount = 0;
    let inactiveCount = 0;

    rows.forEach(row => {
        if (row.style.display === "none") return;
        const status = row.querySelectorAll("td")[2]?.innerText.toLowerCase();
        if (status === "active") activeCount++;
        else if (status === "inactive") inactiveCount++;
    });

    // FIXED ID MATCHING: Falls back to the old names if HTML wasn't updated
    const elActive = document.getElementById("activeCount") || document.getElementById("activeusers");
    if (elActive) elActive.innerText = activeCount;

    const elInactive = document.getElementById("inactiveCount") || document.getElementById("deactiveusers");
    if (elInactive) elInactive.innerText = inactiveCount;
}

document.getElementById("prevPageBtn")?.addEventListener("click", () => {
    window.currentUsersPage--;
    updateUsersPagination();
});

document.getElementById("nextPageBtn")?.addEventListener("click", () => {
    window.currentUsersPage++;
    updateUsersPagination();
});

document.addEventListener("change", function (e) {
    if (e.target && e.target.id === "selectAll") {
        document.querySelectorAll("#incentiveuser tr td:first-child input[type='checkbox']").forEach(cb => {
            cb.checked = e.target.checked;
        });
    }
});

function checkIfFiltersActive() {
    const floatingBtn = document.getElementById("floatingClearFilters");
    if (floatingBtn) floatingBtn.style.display = filtersApplied ? "flex" : "none";
}

function applyFilters() {
    const filters = {
        id: document.getElementById("filterID")?.value.toLowerCase() || "",
        status: document.getElementById("status")?.value.toLowerCase() || "",
        name: document.getElementById("username")?.value.toLowerCase() || "",
        email: document.getElementById("email")?.value.toLowerCase() || "",
        contact: document.getElementById("Contactnumber")?.value.toLowerCase() || "",
        role: document.getElementById("roletype")?.value.toLowerCase() || "",
        project: document.getElementById("Projectname")?.value.toLowerCase() || ""
    };

    const rows = document.querySelectorAll("#incentiveuser tr.user-data-row");
    let totalSalary = 0;
    let visibleCount = 0;

    rows.forEach(row => {
        const cells = row.querySelectorAll("td");
        const rowData = {
            id: cells[1]?.innerText.toLowerCase() || "",
            status: cells[2]?.innerText.toLowerCase() || "",
            name: cells[3]?.innerText.toLowerCase() || "",
            email: cells[4]?.innerText.toLowerCase() || "",
            contact: cells[5]?.innerText.toLowerCase() || "",
            salary: parseFloat(cells[7]?.innerText) || 0,
            role: cells[20]?.innerText.toLowerCase() || "",
            project: cells[18]?.innerText.toLowerCase() || ""
        };

        const match = rowData.id.includes(filters.id) && rowData.status.includes(filters.status) &&
            rowData.name.includes(filters.name) && rowData.email.includes(filters.email) &&
            rowData.contact.includes(filters.contact) && rowData.role.includes(filters.role) &&
            rowData.project.includes(filters.project);

        if (match) {
            row.style.display = "";
            totalSalary += rowData.salary;
            visibleCount++;
        } else {
            row.style.display = "none";
        }
    });

    const elTotalSal = document.getElementById("totalsalary");
    if(elTotalSal) elTotalSal.innerText = totalSalary;
    
    const elAssigned = document.getElementById("assignednuser");
    if(elAssigned) elAssigned.innerText = visibleCount;

    window.currentUsersPage = 1;
    updateUsersPagination();
    updateUsersSummary(); 
    checkIfFiltersActive();
}

document.getElementById("applyFiltersBtn")?.addEventListener("click", function () {
    applyFilters();
    filtersApplied = true;
    checkIfFiltersActive();
    document.getElementById("filterModal")?.classList.remove("custom-show");
});

document.getElementById("clearFiltersBtn")?.addEventListener("click", function () {
    document.querySelectorAll("#filterModal input").forEach(input => input.value = "");
    applyFilters();
    checkIfFiltersActive();
    document.getElementById("filterModal")?.classList.remove("custom-show");
});

document.getElementById("floatingClearFilters")?.addEventListener("click", function () {
    document.querySelectorAll("#filterModal input").forEach(input => input.value = "");
    if(typeof resetUsrddFilters === "function") resetUsrddFilters(); 
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

    if (text.style.display === "none") {
        text.style.display = "inline";
        mask.style.display = "none";
    } else {
        text.style.display = "none";
        mask.style.display = "inline";
    }
});