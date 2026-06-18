var filtersApplied = false;

document.addEventListener('DOMContentLoaded', function () {
    const menuBar = document.querySelector('.sidebarbutt');
    const sideBar = document.querySelector('.sidebar');

    function toggleleftsidebar() {
        if (sideBar) sideBar.classList.toggle('close');
    }

    // Use optional chaining to prevent any TypeError if menuBar is null
    menuBar?.addEventListener('click', toggleleftsidebar);

    const rightsidebar = document.querySelector('#rightsidebar');
    const togglerightsidebar = document.querySelector('#togglerightsidebar');
    const closebtnbar = document.querySelector('#close-btn');

    // Right sidebar controls -------------------------------------------------
    // Guarded setup: only query and attach listeners after DOM is ready,
    // and only if the elements exist. If you prefer to avoid loading the
    // right-sidebar markup on pages that don't need it, include the
    // #rightsidebar / #togglerightsidebar / #close-btn elements only on
    // those pages — this script will safely do nothing otherwise.
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

    // Profile / notification controls --------------------------------------
    function setupProfileNotifControls() {
        const morenotif = document.querySelector("#more_notif_btn");
        const notifcontent = document.querySelector("#notif-content_box");
        // Support both legacy ids and the current bell button id
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

        if (moreprofileicon && profilecontent) {
            moreprofileicon.addEventListener("click", () => {
                profilecontent.style.display =
                    profilecontent.style.display === "block" ? "none" : "block";
                // Also close the new notification popup if open
                const notifPopup = document.querySelector("#notifDropdownPopup");
                if (notifPopup) notifPopup.style.display = "none";
                if (notifcontent) notifcontent.style.display = "none";
            });
        }
    }

    setupProfileNotifControls();

    // Other UI handlers that depend on DOM can be safely attached here
});

// Search popup functions removed - searchbar functionality removed

// var toggler = document.getElementById("theme-toggle");

// toggler.addEventListener("change", function () {
//   if (this.checked) {
//     document.body.classList.add("dark");
//     document.body.style.color = "white";
//     document.querySelector(".content").add("dark");

//   } else {
//     document.body.classList.remove("dark");
//     document.body.style.color = "black";
//   }
// });

var morenotif = document.querySelector("#more_notif_btn");
var notifcontent = document.querySelector("#notif-content_box");
var morenotificon = document.querySelector("#more_notif_icon");
var moreprofileicon = document.querySelector("#more_profile_icon");
var profilecontent = document.querySelector("#profile-content_box");
var closebtn = document.querySelector(".closebtn");
var closebtn1 = document.querySelector(".closebtn1");
// (moved into DOMContentLoaded setupProfileNotifControls)

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
// function downloadCsv(data, filename) {
//   var csvContent = "data:text/csv;charset=utf-8,";
//   data.forEach(function (row) {
//     csvContent += row.join(",") + "\n";
//   });

//   var encodedUri = encodeURI(csvContent);
//   var link = document.createElement("a");
//   link.setAttribute("href", encodedUri);
//   link.setAttribute("download", filename);
//   document.body.appendChild(link);
//   link.click();
// }

// $("#downloadCsvBtn").click(function () {
//   var csvData = [];

//   // Loop through table rows to collect data
//   $("#pagedata tr.custom-filtered-row").each(function () {
//     var rowData = [];
//     var isExcludedHeaderRow = false;

//     // Loop through table cells
//     $(this)
//       .find("td")
//       .each(function (index) {
//         var cellText = $(this).text().trim();

//         if (
//           cellText === "Financial Year/Bookings:" ||
//           cellText === "Total Revenue:" ||
//           cellText === "Actual Revenue:" ||
//           cellText === "Recived Amount:" ||
//           cellText === "Amount To be Pay:" ||
//           cellText === "Total Paid Amt:"
//         ) {
//           isExcludedHeaderRow = true;
//           return false; // Stop checking cells once an excluded header is found
//         }

//         if (
//           // index === 11 ||  // Column 11 (filterAgreement)
//           // index === 12 ||  // Column 12 (filterCommission)
//           // index === 13 ||  // Column 13 (filterTrevenue)
//           // index === 14 ||  // Column 14 (filterCashBack)
//           // index === 15 ||  // Column 15 (filterActualRevenue)
//           // index === 16 ||  // Column 16 (filterStatus)
//           // index === 17 ||  // Column 17 (filterReceived)
//           index === 18     // Column 18 (filterSales)
//         ) {
//           // Replace specific columns with asterisks
//           rowData.push('Search Homes India');
//         } else {
//           rowData.push(cellText);
//         }
//       });

//     if (!isExcludedHeaderRow) {
//       // Exclude the row if it matches the specific content
//       var rowText = rowData.join(',');
//       if (
//         rowText !== "*,*,*,*,*,*,*,*,*,*,*,*,*,*,*,*,*,*"
//       ) {
//         csvData.push(rowData);
//       }
//     }
//   });

//   downloadCsv(csvData, "filtered_data.csv");
// });
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

    if (!tableBody) return; // Only run on Users page

    fetch("fetch_users.php")   // 🔥 CREATE THIS FILE
        .then(res => res.json())
        .then(data => {
            console.log(data);
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
              <!-- DESKTOP ICONS -->
    <div class="action-icons">
        <a href="#" id="${user.id}" class="editLink action-icon edit">
            <i class="bi bi-pencil-square"></i>
        </a>

        <a href="#" id="${user.id}" class="deleteLink action-icon delete">
            <i class="bi bi-trash"></i>
        </a>
    </div>
            <button class="user-expand-btn" onclick="toggleUserRow(this)" title="Show details">
                <i class="bi bi-chevron-right"></i>
            </button>
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
    <button id="${user.id}" class="editLink expand-action-btn edit">
        Edit
    </button>

    <button id="${user.id}" class="deleteLink expand-action-btn delete">
        Delete
    </button>
</div>

                </div>
            </div>
        </td>
    </tr>
`;
            });

            tableBody.innerHTML = rows;
            /* =========================================
   MOBILE ROW CLICK EXPANSION FIX
========================================= */

            document.querySelectorAll("#incentiveuser tr.user-data-row")
                .forEach(row => {

                    row.addEventListener("click", function (e) {

                        /* Ignore clicks on buttons / inputs */
                        if (
                            e.target.closest("button") ||
                            e.target.closest("input") ||
                            e.target.closest(".password-cell")
                        ) {
                            return;
                        }

                        /* Only enable on tablets / mobile */
                        if (window.innerWidth <= 1024) {

                            const expandBtn = row.querySelector(".user-expand-btn");

                            if (expandBtn) {
                                expandBtn.click();   // 🔥 Reuse existing logic
                            }
                        }
                    });
                });

            /* 🔥 CRITICAL FIX */
            if (typeof applyDefaults === "function") {
                applyDefaults();
            }
            window.currentUsersPage = 1;
            updateUsersPagination();
            updateUsersSummary();   // ✅ IMPORTANT



        })
        .catch(err => console.error("Users Fetch Error:", err));
});

/* ================= USER ROW EXPAND TOGGLE ================= */
/* ================= USER ROW EXPAND TOGGLE (ACCORDION FIX) ================= */

window.toggleUserRow = function (btn) {

    const dataRow = btn.closest("tr.user-data-row");
    const expandRow = dataRow.nextElementSibling;
    const icon = btn.querySelector("i");

    if (!expandRow || !expandRow.classList.contains("user-expand-row")) return;

    const isOpen = expandRow.style.display !== "none";

    /* 🔥🔥🔥 ACCORDION LOGIC — CLOSE ALL FIRST */
    document.querySelectorAll(".user-expand-row").forEach(row => {
        row.style.display = "none";
    });

    document.querySelectorAll(".user-expand-btn i").forEach(i => {
        i.className = "bi bi-chevron-right";
    });

    document.querySelectorAll(".user-expand-btn").forEach(b => {
        b.classList.remove("active");
    });

    /* 🔥 TOGGLE CURRENT */
    if (!isOpen) {

        expandRow.style.display = "";
        expandRow.style.animation = "accordionFade 0.25s ease";
        icon.className = "bi bi-chevron-down";
        btn.classList.add("active");

    }
};


/* ================= EDIT USER HANDLER ================= */

document.addEventListener("click", function (e) {

    const editBtn = e.target.closest(".editLink");

    if (!editBtn) return;

    e.preventDefault();

    const userId = editBtn.id;

    if (!userId) return;

    openEditModal(userId);
});

function openEditModal(userId) {

    console.log("Editing User:", userId);

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
        })
        .catch(err => console.error("Edit Load Error:", err));
}

/* ================= DELETE USER HANDLER ================= */

document.addEventListener("click", function (e) {

    const deleteBtn = e.target.closest(".deleteLink");

    if (!deleteBtn) return;

    e.preventDefault();

    const userId = deleteBtn.id;

    if (!userId) return;

    Swal.fire({
        title: 'Are you sure?',
        text: "This user will be permanently deleted.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#6366f1',   // Same Leads style color
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete them!'
    }).then((result) => {

        if (result.isConfirmed) {
            deleteUser(userId);
        }

    });
});

function deleteUser(userId) {

    console.log("Deleting User:", userId);

    fetch("delete_user.php?id=" + userId)
        .then(res => res.text())
        .then(text => {

            console.log("DELETE RESPONSE:", text);

            const data = JSON.parse(text);

            if (data.success) {

                location.reload();

            } else {

                alert(data.message || "Delete failed");
            }
        })
        .catch(err => {

            console.error("Delete Error:", err);
            alert("Server error while deleting");
        });
}


/* ================= SAVE USER (CRITICAL FIX) ================= */

document.getElementById("saveUserBtn")?.addEventListener("click", function () {

    const form = document.getElementById("userForm");

    if (!form) return;

    const formData = new FormData(form);

    fetch("update_user.php", {
        method: "POST",
        body: formData
    })
        .then(res => res.text())
        .then(text => {
            console.log("SERVER RESPONSE:", text);

            const data = JSON.parse(text);

            if (data.success) {

                /* ✅ CLOSE MODAL */
                const modalEl = document.getElementById("addEditModal");
                const modal = bootstrap.Modal.getInstance(modalEl);
                modal.hide();

                /* ✅ RELOAD TABLE */
                location.reload();

            } else {
                alert(data.message || "Update failed");
            }
        })
        .catch(err => {
            console.error("Update Error:", err);
            alert("Server error while updating");
        });

});


function updateUsersPagination() {

    /* Only count DATA rows, not expand rows */
    const allRows = document.querySelectorAll("#incentiveuser tr.user-data-row");
    const visibleRows = Array.from(allRows).filter(row =>
        row.style.display !== "none"
    );

    const limit = parseInt(document.getElementById("users-limit")?.value) || 10;
    const totalRows = visibleRows.length;
    const totalPages = Math.max(1, Math.ceil(totalRows / limit));

    let currentPage = window.currentUsersPage || 1;
    if (currentPage > totalPages) currentPage = totalPages;
    if (currentPage < 1) currentPage = 1;
    window.currentUsersPage = currentPage;

    const start = (currentPage - 1) * limit;
    const end = start + limit;

    /* Hide ALL data rows and their paired expand rows */
    allRows.forEach(row => {
        row.style.display = "none";
        const expandRow = row.nextElementSibling;
        if (expandRow && expandRow.classList.contains("user-expand-row")) {
            expandRow.style.display = "none";
        }
    });

    /* Show only current page data rows (collapse their expand row on page turn) */
    visibleRows.forEach((row, index) => {
        if (index >= start && index < end) {
            row.style.display = "";
            /* expand row stays hidden — user must re-click arrow */
        }
    });

    document.getElementById("showingStart").innerText = totalRows === 0 ? 0 : start + 1;
    document.getElementById("showingEnd").innerText = Math.min(end, totalRows);
    document.getElementById("totalEntries").innerText = totalRows;
    document.getElementById("currentPageBtn").innerText = currentPage;

    const prevBtn = document.getElementById("prevPageBtn");
    const nextBtn = document.getElementById("nextPageBtn");
    prevBtn?.classList.toggle("disabled", currentPage === 1);
    nextBtn?.classList.toggle("disabled", currentPage === totalPages);
}

function updateUsersSummary() {

    const rows = document.querySelectorAll("#incentiveuser tr.user-data-row");

    let activeCount = 0;
    let inactiveCount = 0;

    rows.forEach(row => {

        if (row.style.display === "none") return; // ignore hidden rows

        const status = row.querySelectorAll("td")[2]?.innerText.toLowerCase();

        if (status === "active") activeCount++;
        else if (status === "inactive") inactiveCount++;
    });

    document.getElementById("activeusers").innerText = activeCount;
    document.getElementById("deactiveusers").innerText = inactiveCount;
}

/* ===============================
   FILTER STATE DETECTION
=============================== */

function checkIfFiltersActive() {

    const floatingBtn = document.getElementById("floatingClearFilters");
    if (!floatingBtn) return;

    /* ✅ ONLY SHOW AFTER APPLY */
    floatingBtn.style.display = filtersApplied ? "flex" : "none";
}



document.getElementById("prevPageBtn")?.addEventListener("click", () => {
    window.currentUsersPage--;
    updateUsersPagination();
});

document.getElementById("nextPageBtn")?.addEventListener("click", () => {
    window.currentUsersPage++;
    updateUsersPagination();
});

/* ================= SELECT ALL FIX ================= */

document.addEventListener("change", function (e) {

    if (e.target && e.target.id === "selectAll") {

        const isChecked = e.target.checked;

        document
            .querySelectorAll("#incentiveuser tr td:first-child input[type='checkbox']")
            .forEach(cb => {
                cb.checked = isChecked;
            });
    }
});

/* ================= USERS FILTER ENGINE ================= */

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

        /* ✅ REAL COLUMN MAPPING (VERY IMPORTANT) */

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

        const match =
            rowData.id.includes(filters.id) &&
            rowData.status.includes(filters.status) &&
            rowData.name.includes(filters.name) &&
            rowData.email.includes(filters.email) &&
            rowData.contact.includes(filters.contact) &&
            rowData.role.includes(filters.role) &&
            rowData.project.includes(filters.project);

        if (match) {

            row.style.display = "";
            totalSalary += rowData.salary;
            visibleCount++;

        } else {

            row.style.display = "none";
        }
    });

    /* ✅ SUMMARY UPDATE */

    document.getElementById("totalsalary").innerText = totalSalary;
    document.getElementById("assignednuser").innerText = visibleCount;

    /* ✅ PAGINATION RESET */

    window.currentUsersPage = 1;
    updateUsersPagination();
    updateUsersSummary();   // ✅ ADD THIS LINE
    checkIfFiltersActive();
}

function clearFilters() {

    document.querySelectorAll("#filterModal input").forEach(input => {
        input.value = "";
    });

    applyFilters();
}

document.getElementById("applyFiltersBtn")?.addEventListener("click", function () {

    applyFilters();
filtersApplied = true;    // 🔥 ADD THIS
checkIfFiltersActive();

    const modal = document.getElementById("filterModal");
    modal?.classList.remove("custom-show");

});


document.getElementById("clearFiltersBtn")?.addEventListener("click", function () {

    document.querySelectorAll("#filterModal input")
        .forEach(input => input.value = "");

    applyFilters();

    checkIfFiltersActive();   // 🔥 ADD THIS

    const modal = document.getElementById("filterModal");
    modal?.classList.remove("custom-show");

});

/* ===============================
   FLOATING CLEAR FILTER BUTTON
=============================== */

document.getElementById("floatingClearFilters")
    ?.addEventListener("click", function () {

        document.querySelectorAll("#filterModal input")
            .forEach(input => input.value = "");

        resetUsrddFilters();   // 🔥 CRITICAL
        applyFilters();

        filtersApplied = false;   // 🔥 IMPORTANT
        checkIfFiltersActive();

        /* 🔥 REMOVE BLUR IF EXISTS */
        document.querySelector(".custom-backdrop")?.remove();
        document.body.classList.remove("modal-open");

        checkIfFiltersActive();
    });



/* ===============================================================
   USRDD — USERS PAGE MULTISELECT DROPDOWN ENGINE
   Chips appear INSIDE the input wrapper (scroll-enabled)
   Fully scoped — no global pollution — no conflict with existing JS
================================================================ */

(function () {

    /* ---- internal state ---- */
    var usrSelections = {};   // { fieldId: [ "val1", "val2" ] }

    /* ---- field → possible values come from live table data ---- */
    function usrGetOptionsForField(fieldId) {

        var colMap = {
            filterID:       1,
            status:         2,
            username:       3,
            email:          4,
            Contactnumber:  5,
            Password:       6,
            inhandsalary:   7,
            DateOfJoining:  8,
            DateOfBirth:    9,
            uniqueid:       10,
            EmployeeId:     11,
            assignuser:     21,
            roletype:       20,
            Projectname:    18
        };

        var colIndex = colMap[fieldId];
        if (colIndex === undefined) return [];

        var seen  = {};
        var opts  = [];

        document.querySelectorAll("#incentiveuser tr.user-data-row").forEach(function (row) {
            var cells = row.querySelectorAll("td");
            var cell  = cells[colIndex];
            if (!cell) return;

            /* For password cell get the hidden text span */
            var val = "";
            var passText = cell.querySelector(".password-text");
            if (passText) {
                val = passText.textContent.trim();
            } else {
                val = cell.innerText.trim();
            }

            if (val && !seen[val]) {
                seen[val] = true;
                opts.push(val);
            }
        });

        return opts.sort();
    }

    /* ---- render chip container ---- */
    function usrRenderChips(fieldId) {

        var container = document.getElementById("usrdd-" + fieldId + "-chips");
        if (!container) return;

        var selected = usrSelections[fieldId] || [];

        container.innerHTML = selected.map(function (v) {
            return '<span class="usr-chip">'
                + v
                + '<span class="usr-chip-remove" data-field="' + fieldId + '" data-val="' + v.replace(/"/g, '&quot;') + '">×</span>'
                + '</span>';
        }).join('');

        /* show/hide placeholder */
        var input = document.getElementById(fieldId);
        if (input) {
            input.placeholder = selected.length > 0 ? "" : input.getAttribute("data-placeholder") || input.closest(".usr-custom-dropdown-wrapper")?.previousElementSibling?.textContent.trim() || "";
        }
    }

    /* ---- build dropdown list ---- */
    function usrBuildList(fieldId, searchVal) {

        var list = document.getElementById("usrdd-" + fieldId + "-list");
        if (!list) return;

        var allOpts = usrGetOptionsForField(fieldId);
        var selected = usrSelections[fieldId] || [];
        var q = (searchVal || "").toLowerCase();

        var filtered = allOpts.filter(function (o) {
            return !q || o.toLowerCase().includes(q);
        });

        if (filtered.length === 0) {
            list.innerHTML = '<div class="usr-dd-empty">No matches</div>';
            return;
        }

        list.innerHTML = filtered.map(function (o) {
            var isSel = selected.includes(o);
            return '<div class="usr-dd-item' + (isSel ? ' selected' : '') + '" data-field="' + fieldId + '" data-val="' + o.replace(/"/g, '&quot;') + '">'
                + (isSel ? '<span class="usr-dd-check">✓</span> ' : '')
                + o
                + '</div>';
        }).join('');
    }

    /* ---- open dropdown ---- */
    function usrOpenDropdown(fieldId) {

        /* close all other usrdd dropdowns first */
        document.querySelectorAll(".usr-custom-dropdown-list.open").forEach(function (el) {
            if (el.id !== "usrdd-" + fieldId + "-list") {
                el.classList.remove("open");
            }
        });

        var input = document.getElementById(fieldId);
        var list  = document.getElementById("usrdd-" + fieldId + "-list");
        if (!list) return;

        usrBuildList(fieldId, input ? input.value : "");
        list.classList.add("open");
    }

    /* ---- close all dropdowns ---- */
    function usrCloseAll() {
        document.querySelectorAll(".usr-custom-dropdown-list.open").forEach(function (el) {
            el.classList.remove("open");
        });
    }

    /* ---- toggle item selection ---- */
    function usrToggleItem(fieldId, val) {

        if (!usrSelections[fieldId]) usrSelections[fieldId] = [];

        var idx = usrSelections[fieldId].indexOf(val);
        if (idx === -1) {
            usrSelections[fieldId].push(val);
        } else {
            usrSelections[fieldId].splice(idx, 1);
        }

        /* sync hidden input value for filter engine */
        var input = document.getElementById(fieldId);
        if (input) {
            input.value = usrSelections[fieldId].join(", ");
        }

        usrRenderChips(fieldId);
        usrBuildList(fieldId, "");  /* rebuild with new checked state */
    }

    /* ---- deselect one chip ---- */
    function usrRemoveChip(fieldId, val) {

        if (!usrSelections[fieldId]) return;
        usrSelections[fieldId] = usrSelections[fieldId].filter(function (v) { return v !== val; });

        var input = document.getElementById(fieldId);
        if (input) {
            input.value = usrSelections[fieldId].join(", ");
        }

        usrRenderChips(fieldId);
    }

    /* ---- attach event listeners once DOM ready ---- */
    document.addEventListener("DOMContentLoaded", function () {

        var filterIds = [
            "filterID", "status", "username", "email", "Contactnumber",
            "Password", "inhandsalary", "DateOfJoining", "DateOfBirth",
            "uniqueid", "EmployeeId", "assignuser", "roletype", "Projectname"
        ];

        filterIds.forEach(function (fieldId) {

            var input = document.getElementById(fieldId);
            if (!input) return;

            /* focus / click opens dropdown */
            input.addEventListener("focus", function () {
                usrOpenDropdown(fieldId);
            });

            input.addEventListener("click", function (e) {
                e.stopPropagation();
                usrOpenDropdown(fieldId);
            });

            /* live search filters list */
            input.addEventListener("input", function () {
                usrBuildList(fieldId, this.value);
                var list = document.getElementById("usrdd-" + fieldId + "-list");
                if (list && !list.classList.contains("open")) list.classList.add("open");
            });
        });

        /* global click to close */
        document.addEventListener("click", function (e) {
            if (!e.target.closest(".usr-custom-dropdown-wrapper")) {
                usrCloseAll();
            }
        });

        /* item click — delegation on document */
        document.addEventListener("click", function (e) {

            /* chip remove */
            /* chip remove */
var removeBtn = e.target.closest(".usr-chip-remove");
if (removeBtn) {
    e.stopPropagation();
    usrRemoveChip(removeBtn.dataset.field, removeBtn.dataset.val);
    checkIfFiltersActive();   // ✅ ONLY UI UPDATE
    return;
}

/* dropdown item */
var item = e.target.closest(".usr-dd-item");
if (item) {
    e.stopPropagation();
    usrToggleItem(item.dataset.field, item.dataset.val);
    checkIfFiltersActive();   // ✅ ONLY UI UPDATE
    return;
}
        });

        /* ---- patch clearFilters to also clear usrdd state ---- */
        var origClearBtn = document.getElementById("clearFiltersBtn");
        if (origClearBtn) {
            origClearBtn.addEventListener("click", function () {
                filterIds.forEach(function (fieldId) {
                    usrSelections[fieldId] = [];
                    usrRenderChips(fieldId);
                });
            });
        }

        var floatClearBtn = document.getElementById("floatingClearFilters");
        if (floatClearBtn) {
            floatClearBtn.addEventListener("click", function () {
                filterIds.forEach(function (fieldId) {
                    usrSelections[fieldId] = [];
                    usrRenderChips(fieldId);
                });
            });
        }
    });

    /* ---- extend applyFilters to support multi-value OR logic ---- */
    var _origApplyFilters = window.applyFilters;

    window.applyFilters = function () {

        /* build multi-value filter map from usrSelections */
        var multiFilters = {};

        var colMap = {
            filterID:       1,
            status:         2,
            username:       3,
            email:          4,
            Contactnumber:  5,
            Password:       6,
            inhandsalary:   7,
            DateOfJoining:  8,
            DateOfBirth:    9,
            uniqueid:       10,
            EmployeeId:     11,
            assignuser:     21,
            roletype:       20,
            Projectname:    18
        };

        Object.keys(colMap).forEach(function (fieldId) {
            var sel = usrSelections[fieldId];
            if (sel && sel.length > 0) {
                multiFilters[fieldId] = { colIndex: colMap[fieldId], values: sel };
            }
        });

        var rows = document.querySelectorAll("#incentiveuser tr.user-data-row");

        var totalSalary  = 0;
        var visibleCount = 0;

        rows.forEach(function (row) {

            var cells = row.querySelectorAll("td");
            var show  = true;

            /* check each active multi-filter */
            Object.keys(multiFilters).forEach(function (fieldId) {

                if (!show) return;

                var mf  = multiFilters[fieldId];
                var cell = cells[mf.colIndex];

                var cellText = "";
                if (cell) {
                    var passText = cell.querySelector(".password-text");
                    cellText = (passText ? passText.textContent : cell.innerText).toLowerCase().trim();
                }

                /* OR logic — if ANY selected value matches, pass */
                var matched = mf.values.some(function (v) {
                    return cellText === v.toLowerCase();
                });

                if (!matched) show = false;
            });

            /* also apply plain-text single filters (existing engine inputs) */
            var simpleFilters = {
                filterID:      document.getElementById("filterID")?.value.trim().toLowerCase(),
                status:        document.getElementById("status")?.value.trim().toLowerCase(),
                username:      document.getElementById("username")?.value.trim().toLowerCase(),
                email:         document.getElementById("email")?.value.trim().toLowerCase(),
                Contactnumber: document.getElementById("Contactnumber")?.value.trim().toLowerCase(),
                roletype:      document.getElementById("roletype")?.value.trim().toLowerCase(),
                Projectname:   document.getElementById("Projectname")?.value.trim().toLowerCase()
            };

            /* Only use plain text filter if no usrdd selection for that field */
            Object.keys(simpleFilters).forEach(function (fieldId) {

                if (!show) return;
                if (usrSelections[fieldId] && usrSelections[fieldId].length > 0) return;  /* handled by multiFilters above */

                var val = simpleFilters[fieldId];
                if (!val) return;

                var colIndex = colMap[fieldId];
                if (colIndex === undefined) return;

                var cell = cells[colIndex];
                if (!cell) { show = false; return; }

                var cellText = cell.innerText.toLowerCase().trim();
                if (!cellText.includes(val)) show = false;
            });

            if (show) {
                row.style.display = "";
                var salaryCell = cells[7];
                totalSalary += parseFloat(salaryCell ? salaryCell.innerText : 0) || 0;
                visibleCount++;
            } else {
                row.style.display = "none";
            }
        });

        document.getElementById("totalsalary").innerText = totalSalary;
        document.getElementById("assignednuser").innerText = visibleCount;

        window.currentUsersPage = 1;
        updateUsersPagination();
        if (typeof updateUsersSummary === "function") updateUsersSummary();
        if (typeof checkIfFiltersActive === "function") checkIfFiltersActive();
    };

    window.resetUsrddFilters = function () {

    var filterIds = [
        "filterID", "status", "username", "email", "Contactnumber",
        "Password", "inhandsalary", "DateOfJoining", "DateOfBirth",
        "uniqueid", "EmployeeId", "assignuser", "roletype", "Projectname"
    ];

    filterIds.forEach(function (fieldId) {
        usrSelections[fieldId] = [];
        var input = document.getElementById(fieldId);
        if (input) input.value = "";
        usrRenderChips(fieldId);
    });
};

})();

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