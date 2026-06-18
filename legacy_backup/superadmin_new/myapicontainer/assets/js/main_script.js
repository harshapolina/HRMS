/////////////////////////// PAGENATION SCRIPT (enhanced + multi-select)
document.addEventListener('DOMContentLoaded', function () {
    const rowSelector = document.getElementById('rowSelector');
    const prevButton = document.getElementById('prevButton');
    const nextButton = document.getElementById('nextButton');
    const pageNumbersContainer = document.getElementById('pageNumbers');
    const jumpToPageInput = document.getElementById('jumpToPageInput');
    const jumpButton = document.getElementById('jumpButton');
    const rowInfo = document.getElementById('rowInfo');
    const searchInput = document.getElementById('searchInput');
    const uploaddata = document.getElementById('uploaddata'); // Ensure this is defined in your HTML
    const createUserSelect = document.getElementById("create_users");
    const editUserSelect = document.getElementById("edit_users");
    const assignedUsersContainer = document.getElementById("assignedUsersContainer");
    const assignUserInput = document.getElementById("assign_user");
    const createApiModal = document.getElementById("apicreationModal");

    // Optional header select-all checkbox (if you add it to the thead)
    const selectAllCheckbox = document.getElementById('selectAll');

    let currentPage = 1;
    let rowsPerPage = parseInt(rowSelector.value, 10);
    let totalPages;

    // Initialize Select2 with bootstrap-5 theme
    function initSelect2(selectElement) {
        $(selectElement).select2({
            theme: 'bootstrap-5',
            placeholder: "Search and select users",
            allowClear: true,
            width: '100%'
        });
    }

    // Fetch and populate users into the Select2 dropdown
    async function fetchUsers(selectElement) {
        try {
            const response = await fetch("action_api.php?get_users=1");
            if (!response.ok) throw new Error(`Status: ${response.status}`);

            const optionsHTML = await response.text();

            // Destroy existing Select2 before updating
            if ($(selectElement).hasClass("select2-hidden-accessible")) {
                $(selectElement).select2('destroy');
            }

            // Populate options
            selectElement.innerHTML = optionsHTML;

            // Reinitialize Select2
            initSelect2(selectElement);

        } catch (error) {
            console.error("Error fetching users:", error);
        }
    }

    // Fetch data function
    function fetchData(page = 1, rowsPerPageParam = 10, searchQuery = '', filter = '') {
        // keep the external state consistent
        currentPage = page;
        rowsPerPage = rowsPerPageParam;

        const url = `action_api.php?page=${page}&rowsPerPage=${rowsPerPageParam}&searchQuery=${encodeURIComponent(searchQuery)}&filter=${encodeURIComponent(filter)}`;
        fetch(url)
            .then(response => response.json())
            .then(data => {
                updateTable(data.data || []);
                updatePagination(data.totalRows || 0, data.currentPage || page, data.rowsPerPage || rowsPerPageParam);
                getCellModel(); // Apply cell model logic after updating the table
            })
            .catch(error => console.error('Error fetching data:', error));
    }

    // Update the table — now includes a checkbox as the FIRST column for multi-select
    function updateTable(rows) {
        uploaddata.innerHTML = ''; // Clear the table body

        rows.forEach(row => {
            const tr = document.createElement('tr');
            tr.setAttribute('data-id', row.id);

            // Note: we add checkbox as the first td, then keep rest of columns same as before
            tr.innerHTML = `
                <td><input type="checkbox" class="rowCheckbox" value="${row.id}"></td>
                <td>${row.project_name}</td>
                <td>${row.api_key}</td>
                <td class="user-cell">${row.assign_user}</td>
                <td>${row.lead_source}</td>
                <td>${row.fb_form_leads}</td>
                <td>
                    <div class="d-flex align-items-center justify-content-around">
                        <div class="item-icon edit-item mx-1 rounded-pill editApiBtn" 
                             data-bs-toggle="modal" 
                             data-bs-target="#editApiModal" 
                             data-id="${row.id}" 
                             data-assign-user="${row.assign_user}">
                            <i class="bi bi-pencil-square"></i> Edit
                        </div>
                        <div class="item-icon delete-item mx-1 rounded-pill deleteApiBtn" data-id="${row.id}">
                            <i class="bi bi-trash-fill"></i> Delete
                        </div>
                        <button class="invoice-button">
                            <i class="bi bi-cloud-arrow-down"></i> 
                            <a href="../scripts/generate_curl_excel.php?id=${row.id}" style="color:white">cURL</a>
                        </button>
                        <button class="invoice-button" onclick="downloadReport(this)" data-lead-source="${row.lead_source}" data-project-name="${row.project_name}" style="background-color: lightseagreen;">
                            <i class="bi bi-cloud-arrow-down"></i> Report
                        </button>
                    </div>
                </td>
                <td>${row.created_at}</td>
            `;
            uploaddata.appendChild(tr);
        });

        // If header selectAll exists, uncheck it after re-render
        if (selectAllCheckbox) selectAllCheckbox.checked = false;
    }

    // ---------- Enhanced pagination renderer (keeps prev/next external buttons working) ----------
    function createPageButton(label, isActive, onClick, ariaLabel = null) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'page-btn' + (isActive ? ' active' : '');
        btn.textContent = label;
        if (ariaLabel) btn.setAttribute('aria-label', ariaLabel);
        btn.addEventListener('click', onClick);
        if (isActive) btn.setAttribute('aria-current','page');
        return btn;
    }

    function updatePagination(totalRows, page, perPage) {
        totalPages = Math.max(1, Math.ceil(totalRows / perPage));
        const current = Math.min(Math.max(1, page), totalPages);

        // show text like "Showing X to Y of Z entries"
        const start = (current - 1) * perPage + 1;
        const end = Math.min(current * perPage, totalRows);
        rowInfo.innerText = `Showing ${totalRows === 0 ? 0 : start} to ${end} of ${totalRows} entries`;

        // keep your external prev/next disabled state same
        prevButton.disabled = current === 1;
        nextButton.disabled = current === totalPages;

        // render compact pager with ellipses
        pageNumbersContainer.innerHTML = '';

        // Prev small
        const prev = createPageButton('‹', false, () => { if (current > 1) fetchData(current - 1, perPage, searchInput.value); }, 'Previous');
        prev.classList.add('nav-btn');
        prev.disabled = (current === 1);
        pageNumbersContainer.appendChild(prev);

        // First page always
        pageNumbersContainer.appendChild(createPageButton('1', current === 1, () => fetchData(1, perPage, searchInput.value), 'Page 1'));

        // windowed pages
        const maxButtons = 5; // tune as needed
        let left = Math.max(2, current - Math.floor(maxButtons / 2));
        let right = Math.min(totalPages - 1, left + maxButtons - 1);
        left = Math.max(2, Math.min(left, Math.max(2, totalPages - maxButtons)));

        if (left > 2) {
            const span = document.createElement('span'); span.className = 'page-ellipsis'; span.textContent = '…';
            pageNumbersContainer.appendChild(span);
        }

        for (let i = left; i <= right; i++) {
            pageNumbersContainer.appendChild(createPageButton(i.toString(), i === current, () => fetchData(i, perPage, searchInput.value), `Page ${i}`));
        }

        if (right < totalPages - 1) {
            const span = document.createElement('span'); span.className = 'page-ellipsis'; span.textContent = '…';
            pageNumbersContainer.appendChild(span);
        }

        if (totalPages > 1) {
            pageNumbersContainer.appendChild(createPageButton(totalPages.toString(), current === totalPages, () => fetchData(totalPages, perPage, searchInput.value), `Page ${totalPages}`));
        }

        // Next small
        const next = createPageButton('›', false, () => { if (current < totalPages) fetchData(current + 1, perPage, searchInput.value); }, 'Next');
        next.classList.add('nav-btn');
        next.disabled = (current === totalPages);
        pageNumbersContainer.appendChild(next);
    }

    // Event listeners
    rowSelector.addEventListener('change', () => {
        rowsPerPage = parseInt(rowSelector.value, 10);
        fetchData(currentPage, rowsPerPage);
    });

    searchInput.addEventListener('keyup', () => {
        const searchQuery = searchInput.value;
        fetchData(currentPage, rowsPerPage, searchQuery);
    });

    prevButton.addEventListener('click', () => {
        if (currentPage > 1) fetchData(--currentPage, rowsPerPage);
    });

    nextButton.addEventListener('click', () => {
        if (currentPage < totalPages) fetchData(++currentPage, rowsPerPage);
    });

    jumpButton.addEventListener('click', () => {
        const pageNumber = parseInt(jumpToPageInput.value, 10);
        if (pageNumber >= 1 && pageNumber <= totalPages) fetchData(pageNumber, rowsPerPage);
        else alert('Please enter a valid page number.');
    });

    // Initial fetch
    fetchData(currentPage, rowsPerPage);

    // Show user-cell preview and modal on click (unchanged)
    function getCellModel() {
        const userCells = document.querySelectorAll('.user-cell'); // Dynamically fetch user cells
        userCells.forEach(cell => {
            const userList = cell.innerText.trim().split(',');
            if (userList.length > 1) {
                cell.innerHTML = `${userList[0]} ...more`;
            }
            cell.addEventListener('click', function () {
                document.getElementById('assignUserList').innerText = userList.join(', ');
                const assignUserModal = new bootstrap.Modal(document.getElementById('assignUserModal'));
                assignUserModal.show();
            });
        });
    }

    // ------------------- keep create/delete/edit flows EXACTLY as before -------------------
    // Fetch and populate users when the "Create API" modal opens
    document.querySelector("#apicreationModal").addEventListener("show.bs.modal", () => {
        fetchUsers(createUserSelect);
    });
     $("#createApiForm").on("submit", function (e) {
        e.preventDefault(); // Prevent form's default submission behavior
        const formData = $(this).serialize(); // Serialize form data
    
        $.ajax({
            url: "create_api.php", // Adjust the directory to match the PHP file location
            type: "POST",
            data: formData,
            dataType: "json",
            success: function (response) {
                const messageDiv = $("#responseMessage"); // Div to show success or error message
                if (response.status === "success") {
                    // Show success message
                    messageDiv
                        .removeClass("error")
                        .addClass("success")
                        .text(response.message)
                        .fadeIn();
    
                    // Call fetchData to update the table
                    fetchData(currentPage, rowsPerPage);
    
                    // Reset the form
                    $("#createApiForm")[0].reset();
    
                    // Hide the modal using Bootstrap's modal hide method
                    $("#apicreationModal").modal("hide");
    
                    // Hide the success message after 5 seconds
                    setTimeout(() => {
                        messageDiv.fadeOut();
                    }, 5000);
                } else {
                    // Show error message
                    messageDiv
                        .removeClass("success")
                        .addClass("error")
                        .text(response.message)
                        .fadeIn();
    
                    // Hide the message after 5 seconds
                    setTimeout(() => {
                        messageDiv.fadeOut();
                    }, 5000);
                }
            },
            error: function () {
                // Handle any errors during the AJAX call
                $("#responseMessage")
                    .removeClass("success")
                    .addClass("error")
                    .text("An error occurred. Please try again.")
                    .fadeIn();
    
                // Hide the message after 5 seconds
                setTimeout(() => {
                    $("#responseMessage").fadeOut();
                }, 5000);
            },
        });
    });

    // Delete API button (unchanged)
    $(document).on("click", ".deleteApiBtn", function () {
        const apiId = $(this).data("id");
        console.log(apiId);

        Swal.fire({
            title: "Are you sure?",
            text: "You won't be able to revert this!",
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#3085d6",
            cancelButtonColor: "#d33",
            confirmButtonText: "Yes, delete it!",
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: "delete-api.php",
                    type: "POST",
                    data: { id: apiId },
                    dataType: "json",
                    success: function (response) {
                        if (response.status === "success") {
                            Swal.fire({
                                icon: "success",
                                title: "Deleted!",
                                text: response.message,
                                timer: 2000,
                                showConfirmButton: false,
                            });
                            fetchData(currentPage, rowsPerPage); // Refresh table data
                        } else {
                            Swal.fire({
                                icon: "error",
                                title: "Error",
                                text: response.message,
                            });
                        }
                    },
                    error: function () {
                        Swal.fire({
                            icon: "error",
                            title: "Error",
                            text: "An error occurred. Please try again.",
                        });
                    },
                });
            }
        });
    });
    // this is for create and delete API script End

    // This Script is for UPDATE THE API USERS START (unchanged, minor improvement: use .user-cell when updating)
    // Add a selected user badge
    function populateAssignedUsers(userId) {
        const exists = [...document.querySelectorAll("#assignedUsersContainer span")]
            .some(span => span.textContent === userId);

        if (!exists) {
            const userDiv = document.createElement("div");
            userDiv.className = "assigned-user me-2 mb-2"; // Removed badge/bg-primary for custom style

            userDiv.innerHTML = `
                <span>${userId}</span>
                <button type="button" class="removeUserBtn" data-user-id="${userId}">&times;</button>
            `;

            document.getElementById("assignedUsersContainer").appendChild(userDiv);

            // Remove user on click
            userDiv.querySelector(".removeUserBtn").addEventListener("click", () => {
                userDiv.remove();
            });
        }
    }
 
    // Handle selecting users from dropdown
    $('#edit_users').on('select2:select', function (e) {
        populateAssignedUsers(e.params.data.id);
        $(this).val(null).trigger('change'); // clear dropdown
    });

    // Open modal and load data
    document.addEventListener("click", async (event) => {
        const button = event.target.closest(".editApiBtn");
        if (!button) return;

        const apiId = button.dataset.id;
        const currentAssignUser = button.dataset.assignUser || "";

        document.getElementById("editApiId").value = apiId;

        // Fetch and populate dropdown
        await fetchUsers(document.getElementById("edit_users"));

        // Clear current assigned users
        document.getElementById("assignedUsersContainer").innerHTML = "";

        // Populate already assigned users
        currentAssignUser.split(",").forEach(userId => {
            if (userId.trim()) populateAssignedUsers(userId.trim());
        });
    });

    function populateCreateAssignedUsers(userId) {
        const container = document.getElementById("createAssignedUsersContainer");
        const exists = [...container.querySelectorAll("span")]
            .some(span => span.textContent === userId);

        if (!exists) {
            const userDiv = document.createElement("div");
            userDiv.className = "assigned-user me-2 mb-2 d-flex align-items-center";
            userDiv.style.background = "#e9ecef";
            userDiv.style.padding = "5px 10px";
            userDiv.style.borderRadius = "20px";

            userDiv.innerHTML = `
                <span>${userId}</span>
                <button type="button" class="btn-close ms-2" style="font-size: 0.7rem;" aria-label="Remove"></button>
            `;

            container.appendChild(userDiv);

            // Update hidden input
            updateCreateAssignedUsersInput();

            // Remove user on click
            userDiv.querySelector(".btn-close").addEventListener("click", () => {
                userDiv.remove();
                updateCreateAssignedUsersInput();
            });
        }
    }

    $('#create_users').on('select2:select', function (e) {
        const userId = e.params.data.id;
        populateCreateAssignedUsers(userId);

        // Remove the selected item from select2 so it doesn't show as a blue tag
        let selected = $(this).val() || [];
        selected = selected.filter(id => id !== userId);
        $(this).val(selected).trigger('change');
    });

    function updateCreateAssignedUsersInput() {
        const selectedUsers = [...document.querySelectorAll("#createAssignedUsersContainer span")]
            .map(span => span.textContent.trim());
        document.getElementById("assign_user").value = selectedUsers.join(", ");
    }

    // Submit the edit form
    document.getElementById("editApiForm").addEventListener("submit", async (event) => {
        event.preventDefault();

        const apiId = document.getElementById("editApiId").value;

        // Prepare the data to be sent to the server
        const updatedUsers = Array.from(assignedUsersContainer.querySelectorAll(".assigned-user span"))
            .map((span) => span.textContent.trim())
            .join(",");

        try {
            const response = await fetch("edit_api.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: new URLSearchParams({ api_id: apiId, assign_user: updatedUsers }),
            });

            const result = await response.json();
            const responseMessage = document.getElementById("responseMessage");
            if (result.status === "success") {
                // Update the table with combined users (use .user-cell to be robust with checkbox column)
                const row = document.querySelector(`.editApiBtn[data-id="${apiId}"]`).closest("tr");
                const userCell = row.querySelector('.user-cell');
                if (userCell) userCell.innerText = updatedUsers;

                // Show success message
                responseMessage.innerHTML = `<div class="alert alert-success">${result.message}</div>`;

                // Clear the modal form
                document.getElementById("editApiForm").reset();
                document.querySelector("#editApiModal .btn-close").click();
            } else {
                // Show error message
                responseMessage.innerHTML = `<div class="alert alert-danger">${result.message}</div>`;
            }
        } catch (error) {
            console.error("Error updating API:", error);
            document.getElementById("responseMessage").innerHTML =
                `<div class="alert alert-danger">An error occurred. Please try again later.</div>`;
        }

        // Hide the message after a few seconds
        setTimeout(() => {
            document.getElementById("responseMessage").innerHTML = "";
        }, 3000);
    });

    // Add event listener for the modal's "show.bs.modal" event
    createApiModal.addEventListener("show.bs.modal", function () {
        // Clear the assign_user field
        document.getElementById("assign_user").value = "";

        // Reset the dropdown for new selections
        const createUsersDropdown = document.getElementById("create_users");
        if (createUsersDropdown) {
            createUsersDropdown.selectedIndex = -1; // Clear previous selection
        }

        // Clear any created assigned user UI
        const createAssigned = document.getElementById("createAssignedUsersContainer");
        if (createAssigned) createAssigned.innerHTML = "";
    });

    document.getElementById("create_users").addEventListener("change", function () {
        const selectedOptions = Array.from(this.selectedOptions).map(option => option.value);
        document.getElementById("assign_user").value = selectedOptions.join(", ");
    });

    // ----------------- Multi-select helpers -----------------
    // returns array of selected API ids (strings)
    function getSelectedApiIds() {
        return Array.from(document.querySelectorAll('.rowCheckbox:checked')).map(cb => cb.value);
    }
    // expose globally
    window.getSelectedApiIds = getSelectedApiIds;

    // selectAll header checkbox behavior (if present)
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function () {
            const all = document.querySelectorAll('.rowCheckbox');
            all.forEach(cb => cb.checked = this.checked);
        });
    }
    // delegate change on individual checkboxes (keeps selectAll state in sync)
    document.addEventListener('change', function (e) {
        if (!e.target) return;
        if (e.target.classList && e.target.classList.contains('rowCheckbox')) {
            if (selectAllCheckbox) {
                const all = document.querySelectorAll('.rowCheckbox');
                selectAllCheckbox.checked = Array.from(all).length > 0 && Array.from(all).every(cb => cb.checked);
            }
        }
    });

    // REPORT SCRIPT START
    function downloadReport(button) {
        const leadSource = encodeURIComponent(button.getAttribute('data-lead-source'));
        const projectName = encodeURIComponent(button.getAttribute('data-project-name'));

        const url = `../scripts/generate_api_report.php?lead_source=${leadSource}&project_name=${projectName}`;
        window.location.href = url;
    }
    // REPORT SCRIPT END 
});
