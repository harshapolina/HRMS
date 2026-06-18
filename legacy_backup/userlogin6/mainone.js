document.addEventListener('DOMContentLoaded', function () {
    // Initialize variables
    const rowSelector = document.getElementById('rowSelector');
    const prevButton = document.getElementById('prevButton');
    const nextButton = document.getElementById('nextButton');
    const pageNumbersContainer = document.getElementById('pageNumbers');
    const jumpToPageInput = document.getElementById('jumpToPageInput');
    const jumpButton = document.getElementById('jumpButton');
    const rowInfo = document.getElementById('rowInfo');
    const searchInput = document.getElementById('searchInput');
    const uploaddata = document.getElementById('table-body'); // Table body
    const updateForm = document.getElementById('updateStatusForm');
    // Include both header filter buttons (accessbtn) and status-row filters
    const filterButtons = document.querySelectorAll('.accessbtn, .filter-row .filter-btn.server-true');
    const filterDropdown = document.getElementById('filterStatus'); // Dropdown
    const leadIdentityButtons = document.querySelectorAll('#leadIdentity button');
    const leadIdentityInput = document.querySelector('#leadIdentityValue');
    const managerToggle = document.getElementById('managerToggle');
    const addLeadForm = document.getElementById("addLeadForm");
    const submitLeadBtn = document.getElementById("submitLead");
    const responseMessage = document.getElementById("responseMessage");
    let columnVisibility = {};
    // const updateModal = new bootstrap.Modal(document.getElementById('statusModal')); // Initialize Bootstrap modal
    // const tableBody = document.getElementById("table-body");
    // let activeFilter = ''; // Declare this globally

    let currentPage = 1;
    let rowsPerPage = parseInt(rowSelector.value, 10);
    let totalPages;
    let currentFilter = ''; // To track the active filter
    let isDropdownUpdating = false; // Prevent unintended button state changes
    let multifilters = {}; // To store multi-column filters 

    // Debugging utility to log current filter state
    function logFilterState(action) {
        console.log(`[DEBUG] ${action} - Current Filter:`, currentFilter);
    }

    // Function to update button states
    function updateButtonStates(filter) {
        filterButtons.forEach(button => {
            button.classList.remove('active');
            if (button.id === filter) {
                button.classList.add('active');
            }
        });
    }

    filterButtons.forEach(button => {
        button.addEventListener('click', function () {
            if (isDropdownUpdating) return; // Prevent interference from dropdown updates
            const filterType = this.id; // Use the button's ID as the filter type
            if (currentFilter === filterType) {
                currentFilter = ''; // Toggle filter off
            } else {
                currentFilter = filterType; // Apply new filter
            }
            // Clear any multi-column filters when applying a single status filter to avoid conflicts
            multifilters = {};
            logFilterState('Button Click'); // Debug log
            currentPage = 1; // Reset to the first page
            updateButtonStates(currentFilter); // Update button styles
            // Reset the dropdown to its default state
            if (filterDropdown.value !== '') {
                filterDropdown.value = ''; // Reset dropdown value
                filterDropdown.selectedIndex = 0; // Set to default option (first one)
            }
            fetchData(currentPage, rowsPerPage, searchInput.value.trim(), currentFilter, multifilters); // Fetch data with filter
        });
    });

    // Event listener for dropdown
    filterDropdown.addEventListener('change', function () {
        const selectedValue = this.value; // Get selected value from dropdown

        // Ensure valid filter selection and prevent duplicate updates
        if (selectedValue && selectedValue !== currentFilter) {
            isDropdownUpdating = true; // Block button event during dropdown update
            currentFilter = selectedValue; // Apply dropdown filter
            logFilterState('Dropdown Change'); // Debug log
            currentPage = 1; // Reset to the first page
            updateButtonStates(currentFilter); // Update button styles
            fetchData(currentPage, rowsPerPage, searchInput.value.trim(), currentFilter, multifilters); // Fetch data with filter
            setTimeout(() => (isDropdownUpdating = false), 100); // Allow button events after dropdown update
        }
    });

    // Function to handle multi-column filtering
    function applyMultiColumnFilter() {
        // Get values from modal inputs
        multifilters = {
            name: document.getElementById('isolatedFilterCustumername').value.trim(),
            email: document.getElementById('isolatedFilterEmail').value.trim(),
            number: document.getElementById('isolatedFilterContactnumber').value.trim(),
            location: document.getElementById('isolatedFilterLocation').value.trim(),
            source_of_lead: document.getElementById('isolatedFilterSourceOfLead').value.trim(),
            status: document.getElementById('isolatedFilterStatus').value.trim(),
            assign_project_name: document.getElementById('isolatedFilterAssignedProjectName').value.trim(),
            user_unique_id: document.getElementById('isolatedFilterAssigneduserName').value.trim(),
            lead_identity: document.getElementById('isolatedFilterAssignedIdentity').value.trim(),
            start_date: document.getElementById('isolatedFilterStartDate').value.trim(),
            end_date: document.getElementById('isolatedFilterEndDate').value.trim(),
        };

        // Log filters for debugging
        console.log('[DEBUG] Applying Multi-Column Filters:', multifilters);

        currentPage = 1; // Reset to the first page
        fetchData(currentPage, rowsPerPage, searchInput.value.trim(), '', multifilters); // Fetch data with multi-column filters

        // Close the modal
        const filterModal = document.getElementById('isolatedFilterModal'); // Reference the modal
        const bootstrapModalInstance = bootstrap.Modal.getInstance(filterModal); // Get Bootstrap modal instance
        bootstrapModalInstance.hide(); // Hide the modal
    }

    // Event listener for the "Apply Filters" button
    document.getElementById('isolatedApplyFiltersBtn').addEventListener('click', applyMultiColumnFilter);

    // Event listener for the "Clear Filters" button
    document.getElementById('isolatedClearFiltersBtn').addEventListener('click', () => {
        // Clear all modal input values
        document.getElementById('isolatedFilterCustumername').value = '';
        document.getElementById('isolatedFilterEmail').value = '';
        document.getElementById('isolatedFilterContactnumber').value = '';
        document.getElementById('isolatedFilterLocation').value = '';
        document.getElementById('isolatedFilterSourceOfLead').value = '';
        document.getElementById('isolatedFilterStatus').value = '';
        document.getElementById('isolatedFilterAssignedProjectName').value = '';
        document.getElementById('isolatedFilterAssigneduserName').value = '';
        document.getElementById('isolatedFilterAssignedIdentity').value = '';
        document.getElementById('isolatedFilterStartDate').value = '';
        document.getElementById('isolatedFilterEndDate').value = '';

        // console.log('[DEBUG] Multi-Column Filters Cleared');

        multifilters = {}; // Clear multi-column filters
        currentPage = 1; // Reset to the first page
        fetchData(currentPage, rowsPerPage, searchInput.value.trim()); // Fetch data without any filters

        // Close the modal
        const filterModal = document.getElementById('isolatedFilterModal'); // Reference the modal
        const bootstrapModalInstance = bootstrap.Modal.getInstance(filterModal); // Get Bootstrap modal instance
        bootstrapModalInstance.hide(); // Hide the modal
    });
    // Multi column filter functionality end here
    // Fetch data function
    async function fetchData(page = 1, rowsPerPage = 10, searchQuery = '', filterType = '', multiFilters = {}) {
        try {
            const encodedQuery = searchQuery ? encodeURIComponent(searchQuery) : '';
            const encodedFilters = multiFilters ? encodeURIComponent(JSON.stringify(multiFilters)) : '';
            const toggleState = managerToggle.checked ? 1 : 0; // Get toggle state (1 = checked, 0 = unchecked)
            // const url = `update_status.php?page=${page}&rowsPerPage=${rowsPerPage}&searchQuery=${encodedQuery}&filter=${encodeURIComponent(filterType)}`;
            const url = `update_status.php?page=${page}&rowsPerPage=${rowsPerPage}&searchQuery=${encodedQuery}&filter=${encodeURIComponent(filterType)}&multiFilters=${encodedFilters}&managerToggle=${toggleState}`;

            console.log(`Fetching data from: ${url}`); // Debug URL

            const response = await fetch(url);

            if (!response.ok) {
                throw new Error(`Server Error: ${response.status} ${response.statusText}`);
            }

            const text = await response.text();
            let data;
            try {
                data = JSON.parse(text);
            } catch (jsonError) {
                console.error('Invalid JSON response:', text);
                throw new Error('Invalid JSON received from server');
            }

            // console.log('Data fetched:', data);

            if (data.error) {
                throw new Error(data.error);
            }

            // Process data
            updateTable(data.data);
            updatePagination(data.totalRows, data.currentPage, data.rowsPerPage);
            processUserCells();
            hideAllNumbers();
            PopulateCheckedRow();
            reapplyColumnVisibility();
            getUserLeadsCount();
        } catch (error) {
            console.error('Fetch error:', error);
            alert(`Error fetching data: ${error.message}`);
        }
    }
    // Event listener for toggle switch to refetch data when toggled
    managerToggle.addEventListener('change', () => {
        fetchData(); // Refetch data when toggle state changes
    });
    // Function to reapply column visibility
    function reapplyColumnVisibility() {
        Object.keys(columnVisibility).forEach(column => {
            const isChecked = columnVisibility[column];
            const cells = document.querySelectorAll(`#myTable tr th:nth-child(${column}), #myTable tr td:nth-child(${column})`);
            cells.forEach(cell => {
                cell.style.display = isChecked ? '' : 'none';
            });
        });
    }
    // Universal Search with Pagination
    searchInput.addEventListener("input", function () {
        const searchQuery = searchInput.value.trim().toLowerCase();
        currentPage = 1; // Reset to the first page on a new search
        fetchData(currentPage, rowsPerPage, searchQuery);
    });
    // This javascript is for universal search bar End

    // <!-- column selector script for table start-->
    function PopulateCheckedRow() {
        function populateDropdown() {
            const columnSelector = document.getElementById('columnSelector');
            columnSelector.innerHTML = ''; // Clear previous checkboxes
            const headers = document.querySelectorAll('#myTable thead th');

            headers.forEach((header, index) => {
                const columnIndex = index + 1;
                // const isChecked = columnVisibility[columnIndex] !== false; // Default to visible if not set
                const label = document.createElement('label');
                const isChecked = !header.classList.contains('hide-column');
                label.innerHTML = `<input type="checkbox" value="${columnIndex}" ${isChecked ? 'checked' : ''}> ${header.innerText}`;
                columnSelector.appendChild(label);
                // Hide/show the column based on visibility state
                const cells = document.querySelectorAll(`#myTable tr th:nth-child(${columnIndex}), #myTable tr td:nth-child(${columnIndex})`);
                cells.forEach(cell => {
                    cell.style.display = isChecked ? '' : 'none';
                });
            });
        }

        function toggleColumnVisibility() {
            document.getElementById('columnSelector').addEventListener('change', function (event) {
                const checkbox = event.target;
                if (checkbox.tagName === 'INPUT' && checkbox.type === 'checkbox') {
                    const column = checkbox.value;
                    const isChecked = checkbox.checked;
                    columnVisibility[column] = isChecked; // Update visibility state
                    const cells = document.querySelectorAll(`#myTable tr th:nth-child(${column}), #myTable tr td:nth-child(${column})`);
                    cells.forEach(cell => {
                        cell.style.display = isChecked ? '' : 'none';
                    });
                }
            });
        }
        populateDropdown();
        toggleColumnVisibility();
    }
    // <!-- column selector script for table end-->
    //   this javascirpt is for get the counts of leads status
    function getUserLeadsCount() {
        const getAllCountData = async (flag = false) => {
            try {
                // Get the status of the toggle (enabled or disabled)
                const isToggleEnabled = document.getElementById("managerToggle").checked;
                // Fetch the total count, unassigned count, and my leads count from the PHP script
                const response = await fetch(`update_status.php?get_data=1&toggle_enabled=${isToggleEnabled ? 1 : 0}`);
                const data = await response.json(); // Parse the response as JSON

                // Update the "My Leads" button with the count of leads assigned to the current user
                document.getElementById("myLeads").innerHTML = `<i class="fa-solid fa-user"></i> My Leads (${data.myLeads})`;

                // Update the "My Leads" button with the count of leads assigned to the current user
                document.getElementById("bookedLeads").innerHTML = `<i class="bi bi-journal-richtext"></i> Booked (${data.bookedLeads})`;

                // Update the "Dropped Leads" button with the count of dropped leads
                document.getElementById("droppedLeads").innerHTML = `<i class="bi bi-droplet"></i> Dropped (${data.droppedLeads})`;

                // Update the "Active Leads" button with the count of active leads
                document.getElementById("activeLeads").innerHTML = `<i class="bi bi-activity"></i> Active (${data.activeLeads})`;

                // Update the "Fresh Leads" button with the count of leads from the last 5 days
                document.getElementById("freshLeads").innerHTML = `<i class="bi bi-cloud-plus"></i> New (${data.freshLeads})`;

                // Update the "Pending Leads" button with the count of pending leads
                document.getElementById("pendingLeads").innerHTML = `<i class="bi bi-hourglass"></i> Pending (${data.pendingLeads})`;

                // Update the "Follow Up Leads" button with the count of pending leads
                document.getElementById("followLeads").innerHTML = `<i class="bi bi-hourglass"></i> Follow Up (${data.followupLeads})`;

                // Update the "Today's Update" button with the count of pending leads
                document.getElementById("today_collection").innerHTML = `<i class="bi bi-calendar3"></i> Today FollowUp's (${data.today_collection})`;

                // Update the "Ads Leads" button with the count of pending leads
                document.getElementById("paidAds").innerHTML = `<i class="bi bi-google" style="color: red;"></i><i class="bi bi-facebook" style="color: blue;"></i>Ads (${data.paidAds})`;

                // Update the "SHI-D" button with the new count
                document.getElementById("SHI_D").innerHTML = `<i class="bi bi-database-fill"></i> SHI-D (${data.shi_d})`;

                // Optionally apply filters if 'flag' is true
                if (flag) {
                    applyFilters(); // Call any filter function if needed
                }
            } catch (error) {
                console.error("Error fetching data:", error);
            }
        };
        // Call the function when needed, for example, on page load
        getAllCountData();
        // Add an event listener for the toggle button to update the data when the toggle is changed
        document.getElementById("managerToggle").addEventListener("change", () => {
            getAllCountData();
        });
    }
    //   this javascirpt is for get the counts of leads status End

    // Update the table
    function updateTable(rows) {
        uploaddata.innerHTML = ''; // Clear the table body
        if (!rows.length) {
            uploaddata.innerHTML = '<tr><td colspan="10">No results found.</td></tr>';
            return;
        }
        rows.forEach(row => {
            const tr = document.createElement('tr');
            tr.setAttribute('data-id', row.id);
            tr.classList.add('reveal-number-row');
            tr.style.cursor = 'pointer';

            // Switch Case Start Here
            let statusClass = '';
            switch (row.user_status) {
                case 'Pending':
                    statusClass = 'status-pending';
                    break;
                case 'Fake':
                    statusClass = 'status-fake';
                    break;
                case 'RNR':
                    statusClass = 'status-rnr';
                    break;
                case 'Call Back':
                    statusClass = 'status-callback';
                    break;
                case 'Already Booked':
                    statusClass = 'status-booked';
                    break;
                case 'Not Interested':
                    statusClass = 'status-not-interested';
                    break;
                case 'Interested':
                    statusClass = 'status-interested';
                    break;
                case 'EOI':
                    statusClass = 'status-eoi';
                    break;
                case 'Follow Up':
                    statusClass = 'status-follow-up';
                    break;
                case 'Fix Site Visit':
                    statusClass = 'status-visit';
                    break;
                case 'Site Visit Done':
                    statusClass = 'status-visit-done';
                    break;
                case 'Converted':
                    statusClass = 'status-eoi-collected';
                    break;
                case 'Not Connected':
                    statusClass = 'not-connected';
                    break;
                default:
                    statusClass = ''; // No class if the status doesn't match any case
                    break;
            }
            // Switch Case Close Here

            // Add HTML for each column
            tr.innerHTML = `
                <td><input type="checkbox" class="select-row" value="${row.id}"></td>
                <td>${row.id}</td>
                <td>${row.user_unique_id}</td>
                <td>
                    <span><span style="font-size: 10px;color: black;">Created At:</span> <span style="font-size: 10px;color: green;">${row.created_at}</span></span><br>
                    <div class="user-name-wrapper">
                        <div class="user-name-lead-div">
                            <span class="username-subtitle">${row.name}</span>
                            ${row.lead_identity === null
                    ? `<sub></sub>`
                    : row.lead_identity === 'Hot'
                        ? `<sub><i class="bi bi-fire hot-icon" style="font-size: 1.5em; font-weight: bold;"></i></sub>`
                        : row.lead_identity === 'Warm'
                            ? `<sub><i class="bi bi-sun warm-icon" style="font-size: 1.5em; font-weight: bold;"></i></sub>`
                            : row.lead_identity === 'Cold'
                                ? `<sub><i class="bi bi-snow cold-icon" style="font-size: 1.5em; font-weight: bold;"></i></sub>`
                                : '' // Default empty if there's no valid lead_identity
                }
                        </div>
                        ${row.user_status === 'Pending'
                    ? `<p class="untouched-lead">(Untouched)</p>`
                    : `<span></span>`
                }
                        </div>
                        <p><span class="masked-number" data-number="${row.number}">XXXXXXXXXX</span></p>
                </td>
                <td>${row.email}</td>
                <td>
                    <div class="user-name-wrapper">
                        <div class="user-name-lead-div">
                            <span class="username-subtitle">${row.assign_project_name}</span>
                        </div>
                        <button class="${statusClass}" style="border:none;background-color:transparent">${row.user_status}</button>
                    </div>
                </td>
                <!--<td>
                    <span class="masked-number" data-number="${row.number}">XXXXXXXXXX</span>
                    <a href="tel:${row.number}" style="color: black;"> <i class="bi bi-telephone-forward"></i></a>
                </td>-->
                <td>${row.location}</td>
                <!--<td>${row.assign_to_user}</td>-->
                <!--<td><button class="${statusClass}" style="border:none;background-color:transparent">${row.user_status}</button></td>-->
                <td>
                    <button type="button" class="viewremarks-btn view-remarks" data-id="${row.id}" data-name="${row.name}" data-remarks="${row.user_remarks}" data-toggle="modal" data-target="#usermssgModal">
                        <i class="bi bi-card-text"></i> View Remark
                    </button>
                </td>
                <td>${row.source_of_lead}</td>
                <td>
                <div class="update-btn-div">
                    <!-- Update Button -->
                    <div class="different-wrapper">
                        <button class="update-button different-buttons update-status-btn" data-id="${row.id}" data-userid="${row.user_unique_id}" data-toggle="modal" data-target="#statusModal">
                            <i class="bi bi-arrow-repeat"></i>
                        </button>
                        <!-- <div class="update-btn-counter different-btn-counter">10</div> -->
                    </div>
                    <!-- Assign User Button -->
                    <div class="different-wrapper">
                        <button class="assigndiff-button different-buttons reassign-btn update-assign-btn" data-id="${row.id}" data-toggle="modal" data-target="#reassignModal">
                            <i class="bi bi-person-plus"></i>
                        </button>
                        <!-- <div class="assignuser-btn-counter different-btn-counter">5</div> -->
                    </div>
                    <!-- History Button -->
                    <div class="different-wrapper unique-toggle-btn" data-id="${row.id}" data-userid="${row.user_unique_id}">
                        <button class="history-button different-buttons">
                            <i class="bi bi-clock-history"></i>
                        </button>
                        <!-- <div class="history-btn-counter different-btn-counter">13</div> -->
                    </div>
                    <!-- Whats Button -->
                    <div class="different-wrapper">
                        <a href="https://api.whatsapp.com/send?phone=${row.number}&text=👋 Hi!%0A%0A✨ *Thank you for your interest in ${row.assign_project_name}* ✨%0A%0A🔎 Could you please share more details about your requirements?%0A%0A📌 *Project Name:* ${row.assign_project_name}%0A📞 *Contact Number:* ${row.number}%0A%0AWe look forward to assisting you! 😊%0A%0A📝 *Feel free to share any specific details or questions you have!" target="_blank">
                            <button class="whats-button different-buttons">
                                <i class="bi bi-whatsapp"></i>
                            </button>
                        </a>
                        <!-- <div class="whats-btn-counter different-btn-counter">1</div> -->
                    </div>
                    <!-- Call Button -->
                    <div class="different-wrapper cursor-new">
                        <a href="tel:${row.number}" data-id="${row.id}">
                            <button class="call-button different-buttons">
                                <i class="bi bi-telephone"></i>
                            </button>
                        </a>
                        <div class="call-btn-counter different-btn-counter call-counter" data-id="${row.id}" data-userid="${row.user_unique_id}">0</div>
                    </div>
                </div>
            </td>
                `;
            uploaddata.appendChild(tr);
            // ✅ Attach click listener to this row’s update button
            tr.querySelector('.update-status-btn').addEventListener('click', function () {
                selectedRowId = this.getAttribute('data-id');
                selectedUserUniqueId = this.getAttribute('data-userid');
                document.querySelector('#rowId').value = selectedRowId; // set hidden input if needed
            });

            // Attach listener for view remark button if it exists in your HTML
            const viewBtn = tr.querySelector('.viewremarks-btn');
            if (viewBtn) {
                viewBtn.addEventListener('click', function () {
                    const userName = this.getAttribute('data-name');
                    const userRemarks = this.getAttribute('data-remarks');
                    const history = JSON.parse(this.getAttribute('data-history')) || [];

                    document.getElementById('usermssgModalLabel').textContent = `Message from ${userName}`;
                    document.getElementById('usermssgModalBody').innerHTML = `<p>${userRemarks}</p>`;
                });
            }

            fetchCallCount(row.id, tr);
            // Add event listener to the 'View Remark' button
            // tr.querySelector('.viewremarks-btn').addEventListener('click', function() {
            //     const userName = this.getAttribute('data-name');
            //     const userRemarks = this.getAttribute('data-remarks');
            //     const history = JSON.parse(this.getAttribute('data-history')) || [];

            //     // Populate modal with user name and remarks
            //     document.getElementById('usermssgModalLabel').textContent = `Message from ${userName}`;
            //     document.getElementById('usermssgModalBody').innerHTML = `<p>${userRemarks}</p>`;
            // });
        });

        // Add event listeners to "Update Status" buttons
        document.querySelectorAll('.update-status-btn').forEach(button => {
            button.addEventListener('click', function () {
                const rowId = this.dataset.id;
                document.querySelector('#rowId').value = rowId; // Set hidden input with row ID
            });
        });
        // Add event listener for Re-assign buttons
        document.querySelectorAll('.update-assign-btn').forEach(button => {
            button.addEventListener('click', function () {
                const rowId = this.dataset.id;
                document.querySelector('#reassignRowId').value = rowId; // Set hidden input for row ID
            });
        });
        // Add event listener for Bulk-Re-assign buttons
        document.getElementById("bulkAssign").addEventListener("click", function () {
            const selectedRows = [...document.querySelectorAll(".select-row:checked")].map(input => input.value);
            // const responseMessage = document.getElementById("responseMessage");
            // Clear previous messages
            responseMessage.innerHTML = "";

            if (selectedRows.length === 0) {
                responseMessage.innerHTML = `<div class="alert alert-warning">Please select at least one row to assign.</div>`;
                setTimeout(() => {
                    responseMessage.innerHTML = "";
                }, 3000); // Hide after 3 seconds
                return;
            }
            document.getElementById("bulkAssignRowIds").value = selectedRows.join(",");
            // Open the modal
            let bulkAssignModal = new bootstrap.Modal(document.getElementById("bulkAssignModal"));
            bulkAssignModal.show();
        });
        // Select All functionality
        document.getElementById("select-all").addEventListener("change", function () {
            const isChecked = this.checked;
            document.querySelectorAll(".select-row").forEach(checkbox => {
                checkbox.checked = isChecked;
            });
        });

        // Uncheck "Select All" if any row is manually unchecked
        document.addEventListener("change", function (event) {
            if (event.target.classList.contains("select-row")) {
                const allRows = document.querySelectorAll(".select-row");
                const checkedRows = document.querySelectorAll(".select-row:checked");

                document.getElementById("select-all").checked = allRows.length === checkedRows.length;
            }
        });
    }
    //Handle Bulk Assign For Submission
    document.getElementById("submitBulkAssign").addEventListener("click", function () {
        const rowIds = document.getElementById("bulkAssignRowIds").value;
        const assignUser = document.getElementById("bulkAssignUser").value;
        const projectName = document.getElementById("bulkProjectName").value;
        // const responseMessage = document.getElementById("responseMessage");
        responseMessage.innerHTML = ""; // Clear previous messages

        if (!assignUser || !projectName) {
            responseMessage.innerHTML = `<div class="alert alert-warning">Please select a user and enter a project name.</div>`;

            setTimeout(() => {
                responseMessage.innerHTML = "";
            }, 3000); // Hide after 3 seconds
            return;
        }

        const data = {
            bulkAssign: true,
            rowIds: rowIds.split(","), // Convert to array
            assignUser: assignUser,
            projectName: projectName
        };

        fetch("update_status.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(data)
        })
            .then(response => response.json())
            .then(data => {
                if (data.status === "success") {
                    responseMessage.innerHTML = `<div class="alert alert-success">${data.message}</div>`;

                    // Get modal element
                    let modalElement = document.getElementById("bulkAssignModal");
                    let modalInstance = bootstrap.Modal.getInstance(modalElement);

                    // Force close modal
                    if (modalInstance) {
                        modalInstance.hide();
                    }

                    // **Force remove modal backdrop to fix the black shadow issue**
                    setTimeout(() => {
                        modalElement.classList.remove("show");
                        modalElement.style.display = "none";
                        document.body.classList.remove("modal-open");

                        // Remove ALL modal backdrops manually
                        document.querySelectorAll(".modal-backdrop").forEach(el => el.remove());

                    }, 300); // Small delay to ensure Bootstrap handles it first

                    // Reset the form fields
                    document.getElementById("bulkAssignForm").reset();
                    document.getElementById("bulkAssignRowIds").value = "";

                    // Hide the success message after 3 seconds
                    setTimeout(() => {
                        responseMessage.innerHTML = "";
                    }, 3000);

                    // Update table data without refreshing
                    fetchData();

                } else {
                    responseMessage.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
                    // Hide error message after 3 seconds
                    setTimeout(() => {
                        responseMessage.innerHTML = "";
                    }, 3000);
                }
            })
            .catch(error => {
                console.error("Error:", error);
                responseMessage.innerHTML = `<div class="alert alert-danger">An unexpected error occurred. Please try again later.</div>`;
                setTimeout(() => {
                    responseMessage.innerHTML = "";
                }, 3000);
            });
    });
    // Handle Reassign Form Submission
    document.getElementById("submitReassign").addEventListener("click", function () {
        const rowId = document.getElementById("reassignRowId").value;
        const assignUser = document.getElementById("assignUser").value;
        const projectName = document.getElementById("projectName").value;
        // const responseMessage = document.getElementById("responseMessage"); // Message container

        if (!assignUser || !projectName) {
            responseMessage.innerHTML = `<div class="alert alert-danger">Please select a user and a project.</div>`;
            return;
        }

        const data = {
            reassign: true,
            reassignRowId: rowId,
            assignUser: assignUser,
            projectName: projectName
        };

        fetch("update_status.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(data)
        })
            .then(response => response.json())
            .then(data => {
                if (data.status === "success") {
                    responseMessage.innerHTML = `<div class="alert alert-success">${data.message}</div>`;

                    // Update the table row
                    const row = document.querySelector(`tr[data-id="${rowId}"]`);
                    if (row) {
                        row.querySelector('td:nth-child(8)').textContent = assignUser; // Update assigned user
                        row.querySelector('td:nth-child(7)').textContent = projectName; // Update project name
                    }

                    // Hide the modal first
                    let modalElement = document.getElementById("reassignModal");
                    let modal = bootstrap.Modal.getInstance(modalElement);
                    if (modal) modal.hide();

                    // Reset the form immediately
                    document.getElementById("reassignForm").reset();
                    document.getElementById("reassignRowId").value = "";

                    // Ensure modal can be reopened next time
                    modalElement.addEventListener("hidden.bs.modal", function () {
                        let newModal = new bootstrap.Modal(modalElement);
                        newModal.dispose();
                        modalElement.removeEventListener("hidden.bs.modal", arguments.callee);
                    });

                    // Now, after hiding, update the response message
                    setTimeout(() => {
                        document.getElementById("responseMessage").innerHTML = `<div class="alert alert-success">Reassigned successfully!</div>`;

                        // Hide the success message after 5 seconds
                        setTimeout(() => {
                            document.getElementById("responseMessage").innerHTML = "";
                        }, 500);

                    }, 500); // Small delay to ensure smooth UX 
                    // Update table data without refreshing
                    fetchData(); // 🔄 This will refresh the table dynamically            

                } else {
                    responseMessage.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
                }
            })
            .catch(error => {
                console.error("Error:", error);
                responseMessage.innerHTML = `<div class="alert alert-danger">An unexpected error occurred. Please try again later.</div>`;
            });
    });

    // Update row without refreshing the page
    function updateTableRow(rowId, status, notes, lead_identity) {
        const row = document.querySelector(`tr[data-id="${rowId}"]`);

        if (row) {
            const statusButton = row.querySelector('button'); // Assuming status is updated in a button
            const viewRemarksButton = row.querySelector('.viewremarks-btn'); // Button to view remarks
            const leadIdentityWrapper = row.querySelector('.user-name-wrapper');

            // Update status text
            if (statusButton) {
                statusButton.textContent = status;

                // Remove old status classes
                statusButton.classList.remove(
                    'status-pending',
                    'status-fake',
                    'status-rnr',
                    'status-callback',
                    'status-booked',
                    'status-not-interested',
                    'status-interested',
                    'status-eoi',
                    'status-follow-up',
                    'status-visit',
                    'status-visit-done',
                    'status-eoi-collected'
                );

                // Add the new class based on the updated status
                let statusClass = '';
                switch (status) {
                    case 'Pending':
                        statusClass = 'status-pending';
                        break;
                    case 'Fake':
                        statusClass = 'status-fake';
                        break;
                    case 'RNR':
                        statusClass = 'status-rnr';
                        break;
                    case 'Call Back':
                        statusClass = 'status-callback';
                        break;
                    case 'Already Booked':
                        statusClass = 'status-booked';
                        break;
                    case 'Not Interested':
                        statusClass = 'status-not-interested';
                        break;
                    case 'Interested':
                        statusClass = 'status-interested';
                        break;
                    case 'EOI':
                        statusClass = 'status-eoi';
                        break;
                    case 'Follow Up':
                        statusClass = 'status-follow-up';
                        break;
                    case 'Fix Site Visit':
                        statusClass = 'status-visit';
                        break;
                    case 'Site Visit Done':
                        statusClass = 'status-visit-done';
                        break;
                    case 'Converted':
                        statusClass = 'status-eoi-collected';
                        break;
                    case 'Not Connected':
                        statusClass = 'not-connected';
                        break;
                    default:
                        statusClass = ''; // No class if the status doesn't match any case
                        break;
                }

                // Add the new class to the button
                if (statusClass) {
                    statusButton.classList.add(statusClass);
                }
            } else {
                console.error('Status button not found in the row.');
            }

            // Update remarks in the button's data-remarks attribute
            if (viewRemarksButton) {
                viewRemarksButton.setAttribute('data-remarks', notes); // Update the remarks
            } else {
                console.error('View Remarks button not found in the row.');
            }
            if (leadIdentityWrapper) {
                let leadIdentityIcon = '';

                switch (lead_identity) {
                    case 'Hot':
                        leadIdentityIcon = '<sub><i class="bi bi-fire hot-icon" style="font-size: 1.5em; font-weight: bold;"></i></sub>';
                        break;
                    case 'Warm':
                        leadIdentityIcon = '<sub><i class="bi bi-sun warm-icon" style="font-size: 1.5em; font-weight: bold;"></i></sub>';
                        break;
                    case 'Cold':
                        leadIdentityIcon = '<sub><i class="bi bi-snow cold-icon" style="font-size: 1.5em; font-weight: bold;"></i></sub>';
                        break;
                    default:
                        leadIdentityIcon = ''; // No icon if the lead_identity doesn't match any case
                        break;
                }

                // Update the lead identity icon
                leadIdentityWrapper.innerHTML = leadIdentityWrapper.querySelector('span').outerHTML + leadIdentityIcon;
            } else {
                console.error('Lead Identity wrapper not found in the row.');
            }
            // this javascript is for refresh the count after changes in any row
            getUserLeadsCount()
            $('#updateStatusForm')[0].reset(); // Reset the form
        } else {
            console.error(`Row with ID ${rowId} not found.`);
        }
    }
    // document.body.addEventListener('click', (event) => {
    //     const target = event.target.closest('.unique-toggle-btn');
    //     if (target) {
    //         console.log('Button clicked:', target); // Check button click
    //         const rowId = target.getAttribute('data-id');
    //         console.log('Row ID:', rowId); // Check row ID
    //         fetchHistory(rowId); // Fetch history data for the row
    //         openSidebar(); // Open the sidebar
    //     }
    // });

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
        fetch('update_status.php', {
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
                        [...data.history].reverse().forEach(entry => {
                            // Determine the appropriate status class using the switch case
                            let statusClass = '';
                            switch (entry.status) {
                                case 'Pending':
                                    statusClass = 'status-pending';
                                    break;
                                case 'Fake':
                                    statusClass = 'status-fake';
                                    break;
                                case 'RNR':
                                    statusClass = 'status-rnr';
                                    break;
                                case 'Call Back':
                                    statusClass = 'status-callback';
                                    break;
                                case 'Already Booked':
                                    statusClass = 'status-booked';
                                    break;
                                case 'Not Interested':
                                    statusClass = 'status-not-interested';
                                    break;
                                case 'Interested':
                                    statusClass = 'status-interested';
                                    break;
                                case 'Follow Up':
                                    statusClass = 'status-follow-up';
                                    break;
                                case 'Fix Site Visit':
                                    statusClass = 'status-visit';
                                    break;
                                case 'Site Visit Done':
                                    statusClass = 'status-visit-done';
                                    break;
                                case 'Converted':
                                    statusClass = 'status-eoi-collected';
                                    break;
                                case 'EOI':
                                    statusClass = 'eoi';
                                    break;
                                case 'Not Connected':
                                    statusClass = 'not-connected';
                                    break;
                                default:
                                    statusClass = ''; // No class if the status doesn't match any case
                                    break;
                            }
                            // Styling CSS END FOR STATUS
                            const li = document.createElement('li');
                            li.classList.add('unique-step', 'unique-active-timeline');
                            li.innerHTML = `
                                <div class="unique-dot"></div>
                                <div class="unique-content">
                                    <span class="unique-status-info ${statusClass}">${entry.status}</span>
                                    <span class="unique-arrow">→</span> 
                                    <span class="unique-status-view"><a href="#.">Notes</a></span> 
                                    <span class="unique-arrow">→</span>
                                    <span class="unique-date-time">${entry.timestamp}</span>
                                    <span class="unique-arrow unique-downarrow">▼</span>
                                    <span class="unique-arrow unique-uparrow">▲</span>
                                </div>
                                <div class="unique-dropdown">
                                    <div class="unique-dropdown-insides">
                                        <span><b>Updated By:</b> ${entry.update_by || 'No User available'}</span>
                                        <span><b>Date & Time</b>: ${entry.followUpDate || 'N/A'
                                } ${entry.followUpTime || 'N/A'}</span>
                                        <span><b>Notes</b>: ${entry.notes || 'No notes available'}</span>
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
        fetch('update_status.php', {
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
                        [...data.history].reverse().forEach(entry => {
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

                            const li = document.createElement('li');
                            li.classList.add('unique-step', 'unique-active-timeline');
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

    // Event delegation for dynamically added buttons
    document.addEventListener('click', function (event) {
        let rowId, userUniqueId;
        if (event.target.classList.contains('update-status-btn') || event.target.classList.contains('call-counter')) {
            rowId = event.target.dataset.id;
            userUniqueId = event.target.dataset.userid; // Fetch user_unique_id
            // console.log('Button clicked for rowId:', rowId, 'User Unique ID:', userUniqueId);

            // Fetch history based on the button clicked
            if (event.target.classList.contains('update-status-btn')) {
                fetchHistory(rowId, userUniqueId);
            } else if (event.target.classList.contains('call-counter')) {
                fetchCallHistory(rowId, userUniqueId);
            }

            // Populate the hidden input for updating status
            const rowIdInput = document.querySelector('#rowId');
            if (rowIdInput) {
                rowIdInput.value = rowId;
            } else {
                console.error('Hidden input #rowId not found in the DOM.');
            }
        }
    });
    // Loop through the buttons and attach click event listeners
    leadIdentityButtons.forEach(button => {
        button.addEventListener('click', () => {
            // Remove the 'active' class from all buttons
            leadIdentityButtons.forEach(btn => btn.classList.remove('active'));
            // Add the 'active' class to the clicked button
            button.classList.add('active');

            // Set the hidden input value to the selected button's data-value
            const selectedValue = button.getAttribute('data-value');
            leadIdentityInput.value = selectedValue;

            // Log for debugging
            // console.log(`Selected Value: ${selectedValue}`);
            // console.log(`Hidden Input Value: ${leadIdentityInput.value}`);
        });
    });
    function updateRow(id) {
        const status = document.querySelector('#status').value; // Get new status from modal
        const notes = document.querySelector('#notes').value; // Get new notes from modal
        const followUpDate = document.querySelector('#followUpDate').value; // Get follow-up date from modal
        const followUpTime = document.querySelector('#followUpTime').value; // Get follow-up time from modal
        const leadIdentityInput = document.querySelector('#leadIdentityValue');
        const leadIdentity = leadIdentityInput ? leadIdentityInput.value : null;

        // console.log(`Lead Identity in updateRow: ${leadIdentity}`);

        if (leadIdentity === null) {
            console.log("No lead identity selected.");
        }

        // Send data to server
        fetch('update_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                update: true,
                rowId: selectedRowId,
                status: status,
                notes: notes,
                followUpDate: followUpDate,
                followUpTime: followUpTime,
                lead_identity: leadIdentity,
                user_unique_id: selectedUserUniqueId
            })
        })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    // Update the table row directly without refreshing
                    updateTableRow(id, status, notes, leadIdentity);

                    // Update the follow-up history
                    if (data.history) {
                        populateFollowUpHistory(data.history); // Function to update the history in the modal
                    }

                    // Notify the user if the Google Calendar event was created
                    if (data.google_calendar_event) {
                        alert(`Google Calendar Event Created Successfully! \nView it here: ${data.google_calendar_event}`);
                    } else if (data.google_calendar_error) {
                        console.warn(data.google_calendar_error);
                        alert('The update was successful, but there was an issue with the Google Calendar integration.');
                    }

                    // Close the modal after a successful update
                    const updateModal = bootstrap.Modal.getInstance(document.querySelector('#statusModal'));
                    updateModal.hide();

                    // Clear the modal input fields
                    document.querySelector('#status').value = '';
                    document.querySelector('#notes').value = '';
                    document.querySelector('#followUpDate').value = '';
                    document.querySelector('#followUpTime').value = '';
                    document.querySelector('#leadIdentity').value = '';
                    const leadIdentityButtons = document.querySelectorAll('#leadIdentity button');
                    leadIdentityButtons.forEach(btn => btn.classList.remove('active'));
                } else {
                    alert(data.message || 'Error updating row. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert(`Error fetching data. Please try again. Details: ${error.message}`);
            });
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
                PopulateCheckedRow()
                fetchData(pageNumber, rowsPerPage, searchInput.value.trim(), currentFilter, multifilters);
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
        PopulateCheckedRow()
        fetchData(currentPage, rowsPerPage, searchInput.value.trim(), currentFilter, multifilters);
    });

    searchInput.addEventListener('keyup', function () {
        const searchQuery = this.value;
        currentPage = 1;
        PopulateCheckedRow()
        fetchData(currentPage, rowsPerPage, searchQuery, currentFilter, multifilters);
    });


    prevButton.addEventListener('click', function () {
        if (currentPage > 1) {
            currentPage--;
            PopulateCheckedRow()
            fetchData(currentPage, rowsPerPage, searchInput.value.trim(), currentFilter, multifilters);
        }
    });

    nextButton.addEventListener('click', function () {
        if (currentPage < totalPages) {
            currentPage++;
            PopulateCheckedRow()
            fetchData(currentPage, rowsPerPage, searchInput.value.trim(), currentFilter, multifilters);
        }
    });

    jumpButton.addEventListener('click', function () {
        const pageNumber = parseInt(jumpToPageInput.value, 10);
        if (pageNumber >= 1 && pageNumber <= totalPages) {
            currentPage = pageNumber;
            PopulateCheckedRow()
            fetchData(currentPage, rowsPerPage, searchInput.value.trim(), currentFilter, multifilters);
        } else {
            alert('Please enter a valid page number.');
        }
    });

    // Function to hide all numbers
    function hideAllNumbers() {
        document.querySelectorAll('.masked-number').forEach(span => {
            span.textContent = 'XXXXXXXXXX'; // Mask all numbers
        });
    }

    // Event Delegation for hiding and showing phone numbers
    uploaddata.addEventListener('click', function (e) {
        const clickedRow = e.target.closest('.reveal-number-row');

        // Only proceed if a row with class `reveal-number-row` was clicked
        if (clickedRow) {
            // Hide all numbers first
            hideAllNumbers();

            // Find the masked-number span in the clicked row and reveal the number
            const numberSpan = clickedRow.querySelector('.masked-number');
            if (numberSpan) {
                numberSpan.textContent = numberSpan.getAttribute('data-number');
            }
        }
    });

    // Optionally, hide the number again if clicked outside any row
    document.addEventListener('click', function (event) {
        // Check if the click was outside any of the reveal-number-row rows
        if (!event.target.closest('.reveal-number-row')) {
            hideAllNumbers();  // Hide all numbers
        }
    });

    // Function to handle user cells
    function processUserCells() {
        const userCells = document.querySelectorAll('.user-cell');
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

    // Handle modal submit
    updateForm.addEventListener('submit', function (event) {
        event.preventDefault();
        const id = document.querySelector('#rowId').value; // Get rowId from hidden input
        updateRow(id); // Call updateRow function
    });

    function populateFollowUpHistory(history) {
        const historyList = document.querySelector('#followUpHistory');
        historyList.innerHTML = ''; // Clear existing history once

        [...history].reverse().forEach(entry => {
            const listItem = document.createElement('li');
            listItem.classList.add('list-group-item');
            listItem.textContent = `Status: ${entry.status}, Notes: ${entry.notes}, 
                                    Follow-Up Date: ${entry.followUpDate}, 
                                    Follow-Up Time: ${entry.followUpTime}, 
                                    Updated On: ${entry.timestamp}`;
            historyList.appendChild(listItem);
        });
    }

    // Submit lead add handler
    submitLeadBtn.addEventListener("click", function (e) {
        e.preventDefault();

        const formData = new FormData(addLeadForm);

        fetch("insert_lead.php", {
            method: "POST",
            body: formData
        })
            .then(response => response.json())
            .then(res => {
                // Show appropriate message
                if (res.status === "success") {
                    responseMessage.innerHTML = `<div class="alert alert-success">${res.message}</div>`;
                    fetchData(1, 10, '', '', {}, '', ''); // Refresh data only on success
                } else {
                    responseMessage.innerHTML = `<div class="alert alert-danger">${res.message}</div>`;
                }

                // Hide modal and reset form in both cases (success or error)
                const modal = bootstrap.Modal.getInstance(document.getElementById("addLeadModal"));
                if (modal) modal.hide();
                addLeadForm.reset();

                // Clear message after 3 seconds
                setTimeout(() => {
                    responseMessage.innerHTML = "";
                }, 3000);
            })
            .catch(error => {
                console.error("Error:", error);
                responseMessage.innerHTML = `<div class="alert alert-danger">An error occurred. Please try again later.</div>`;

                // Hide modal and reset form even if a fetch error occurs
                const modal = bootstrap.Modal.getInstance(document.getElementById("addLeadModal"));
                if (modal) modal.hide();
                addLeadForm.reset();

                setTimeout(() => {
                    responseMessage.innerHTML = "";
                }, 3000);
            });
    });
    // Submit lead add handler End

    // Initial data fetch
    fetchData(currentPage, rowsPerPage);
});
// This script is for keep ristrict user to don't copy don't inspect
// Disable right-click
document.addEventListener('contextmenu', function (e) {
    e.preventDefault();
});

// Disable certain keyboard shortcuts
document.addEventListener('keydown', function (e) {
    if (e.ctrlKey && (e.key === 'c' || e.key === 'u' || e.key === 'a' || e.key === 's')) {
        e.preventDefault();
    }
});
// This script is for keep ristrict user to don't copy don't inspect End
document.addEventListener("DOMContentLoaded", () => {
    const assignUserDropdown = document.getElementById("assignUser");
    fetchUsers(assignUserDropdown); // Populate dropdown on page load
});

const fetchUsers = async () => {
    try {
        const response = await fetch("update_status.php?get_users=1");
        if (response.ok) {
            const options = await response.text();

            // Populate all select elements for user assignment
            document.querySelectorAll(".user-select").forEach(select => {
                select.innerHTML = `
                    <option value="" disabled selected>Select User</option>
                    ${options}
                `;
            });
        } else {
            console.error("Failed to fetch users. Status:", response.status);
        }
    } catch (error) {
        console.error("Error fetching users:", error);
    }
};

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
            const callCounterSpan = tr.querySelector('.call-counter');
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

        // ✅ SweetAlert2 confirmation
        const { isConfirmed } = await Swal.fire({
            title: "Are you sure?",
            text: "Do you really want to make this call?",
            icon: "question",
            showCancelButton: true,
            confirmButtonColor: "#3085d6",
            cancelButtonColor: "#d33",
            confirmButtonText: "Yes, call now!",
            cancelButtonText: "Cancel"
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

                // ✅ Trigger the phone call after successful log
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
// GET CALL HISTORY BUTTON END