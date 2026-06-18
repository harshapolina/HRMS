/////////////////////////// PAGENATION SCRIPT
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
    // const selectAllCheckbox = document.getElementById('select-all');
    // const deleteSelectedBtn = document.getElementById('delete-selected-btn');

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
    function fetchData(page = 1, rowsPerPage = 10, searchQuery = '', filter = '') {
        const url = `action_api.php?page=${page}&rowsPerPage=${rowsPerPage}&searchQuery=${encodeURIComponent(searchQuery)}&filter=${encodeURIComponent(filter)}`;
        fetch(url)
            .then(response => response.json())
            .then(data => {
                updateTable(data.data);
                updatePagination(data.totalRows, data.currentPage, data.rowsPerPage);
                getCellModel(); // Apply cell model logic after updating the table
            })
            .catch(error => console.error('Error fetching data:', error));
    }

    // Helper function to get platform icon/logo
    function getPlatformIcon(source, type) {
        // Check type first for groups
        if (type === 'group') {
            return '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1" width="20" height="20" viewBox="0 0 256 256" xml:space="preserve"><g style="stroke: none; stroke-width: 0; stroke-dasharray: none; stroke-linecap: butt; stroke-linejoin: miter; stroke-miterlimit: 10; fill: none; fill-rule: nonzero; opacity: 1;" transform="translate(1.4065934065934016 1.4065934065934016) scale(2.81 2.81)"><path d="M 45 49.519 L 45 49.519 c -7.68 0 -13.964 -6.284 -13.964 -13.964 v -5.008 c 0 -7.68 6.284 -13.964 13.964 -13.964 h 0 c 7.68 0 13.964 6.284 13.964 13.964 v 5.008 C 58.964 43.236 52.68 49.519 45 49.519 z" style="stroke: none; stroke-width: 1; stroke-dasharray: none; stroke-linecap: butt; stroke-linejoin: miter; stroke-miterlimit: 10; fill: rgb(110,177,225); fill-rule: nonzero; opacity: 1;" transform=" matrix(1 0 0 1 0 0) " stroke-linecap="round"/><path d="M 52.863 51.438 c -2.362 1.223 -5.032 1.927 -7.863 1.927 s -5.501 -0.704 -7.863 -1.927 C 26.58 53.014 18.414 62.175 18.414 73.152 v 14.444 c 0 1.322 1.082 2.403 2.403 2.403 h 48.364 c 1.322 0 2.403 -1.082 2.403 -2.403 V 73.152 C 71.586 62.175 63.42 53.014 52.863 51.438 z" style="stroke: none; stroke-width: 1; stroke-dasharray: none; stroke-linecap: butt; stroke-linejoin: miter; stroke-miterlimit: 10; fill: rgb(110,177,225); fill-rule: nonzero; opacity: 1;" transform=" matrix(1 0 0 1 0 0) " stroke-linecap="round"/><path d="M 71.277 34.854 c -2.362 1.223 -5.032 1.927 -7.863 1.927 c -0.004 0 -0.007 0 -0.011 0 c -0.294 4.412 -2.134 8.401 -4.995 11.43 c 10.355 3.681 17.678 13.649 17.678 24.941 v 0.263 h 11.511 c 1.322 0 2.404 -1.082 2.404 -2.404 V 56.568 C 90 45.59 81.834 36.429 71.277 34.854 z" style="stroke: none; stroke-width: 1; stroke-dasharray: none; stroke-linecap: butt; stroke-linejoin: miter; stroke-miterlimit: 10; fill: rgb(113,212,86); fill-rule: nonzero; opacity: 1;" transform=" matrix(1 0 0 1 0 0) " stroke-linecap="round"/><path d="M 63.414 0 c -7.242 0 -13.237 5.589 -13.898 12.667 c 8 2.023 13.947 9.261 13.947 17.881 v 2.385 c 7.657 -0.027 13.914 -6.298 13.914 -13.961 v -5.008 C 77.378 6.284 71.094 0 63.414 0 z" style="stroke: none; stroke-width: 1; stroke-dasharray: none; stroke-linecap: butt; stroke-linejoin: miter; stroke-miterlimit: 10; fill: rgb(113,212,86); fill-rule: nonzero; opacity: 1;" transform=" matrix(1 0 0 1 0 0) " stroke-linecap="round"/><path d="M 13.915 73.152 c 0 -11.292 7.322 -21.261 17.677 -24.941 c -2.861 -3.029 -4.702 -7.019 -4.995 -11.43 c -0.004 0 -0.007 0 -0.011 0 c -2.831 0 -5.5 -0.704 -7.863 -1.927 C 8.166 36.429 0 45.59 0 56.568 v 14.444 c 0 1.322 1.082 2.404 2.404 2.404 h 11.511 V 73.152 z" style="stroke: none; stroke-width: 1; stroke-dasharray: none; stroke-linecap: butt; stroke-linejoin: miter; stroke-miterlimit: 10; fill: rgb(240,88,47); fill-rule: nonzero; opacity: 1;" transform=" matrix(1 0 0 1 0 0) " stroke-linecap="round"/><path d="M 26.536 32.932 v -2.385 c 0 -8.62 5.946 -15.858 13.947 -17.881 C 39.823 5.589 33.828 0 26.586 0 c -7.68 0 -13.964 6.284 -13.964 13.964 v 5.008 C 12.622 26.635 18.879 32.905 26.536 32.932 z" style="stroke: none; stroke-width: 1; stroke-dasharray: none; stroke-linecap: butt; stroke-linejoin: miter; stroke-miterlimit: 10; fill: rgb(240,88,47); fill-rule: nonzero; opacity: 1;" transform=" matrix(1 0 0 1 0 0) " stroke-linecap="round"/></g></svg>';
        }
        
        const lowerSource = (source || '').toLowerCase();
        
        if (lowerSource.includes('group')) {
            return '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1" width="20" height="20" viewBox="0 0 256 256" xml:space="preserve"><g style="stroke: none; stroke-width: 0; stroke-dasharray: none; stroke-linecap: butt; stroke-linejoin: miter; stroke-miterlimit: 10; fill: none; fill-rule: nonzero; opacity: 1;" transform="translate(1.4065934065934016 1.4065934065934016) scale(2.81 2.81)"><path d="M 45 49.519 L 45 49.519 c -7.68 0 -13.964 -6.284 -13.964 -13.964 v -5.008 c 0 -7.68 6.284 -13.964 13.964 -13.964 h 0 c 7.68 0 13.964 6.284 13.964 13.964 v 5.008 C 58.964 43.236 52.68 49.519 45 49.519 z" style="stroke: none; stroke-width: 1; stroke-dasharray: none; stroke-linecap: butt; stroke-linejoin: miter; stroke-miterlimit: 10; fill: rgb(110,177,225); fill-rule: nonzero; opacity: 1;" transform=" matrix(1 0 0 1 0 0) " stroke-linecap="round"/><path d="M 52.863 51.438 c -2.362 1.223 -5.032 1.927 -7.863 1.927 s -5.501 -0.704 -7.863 -1.927 C 26.58 53.014 18.414 62.175 18.414 73.152 v 14.444 c 0 1.322 1.082 2.403 2.403 2.403 h 48.364 c 1.322 0 2.403 -1.082 2.403 -2.403 V 73.152 C 71.586 62.175 63.42 53.014 52.863 51.438 z" style="stroke: none; stroke-width: 1; stroke-dasharray: none; stroke-linecap: butt; stroke-linejoin: miter; stroke-miterlimit: 10; fill: rgb(110,177,225); fill-rule: nonzero; opacity: 1;" transform=" matrix(1 0 0 1 0 0) " stroke-linecap="round"/><path d="M 71.277 34.854 c -2.362 1.223 -5.032 1.927 -7.863 1.927 c -0.004 0 -0.007 0 -0.011 0 c -0.294 4.412 -2.134 8.401 -4.995 11.43 c 10.355 3.681 17.678 13.649 17.678 24.941 v 0.263 h 11.511 c 1.322 0 2.404 -1.082 2.404 -2.404 V 56.568 C 90 45.59 81.834 36.429 71.277 34.854 z" style="stroke: none; stroke-width: 1; stroke-dasharray: none; stroke-linecap: butt; stroke-linejoin: miter; stroke-miterlimit: 10; fill: rgb(113,212,86); fill-rule: nonzero; opacity: 1;" transform=" matrix(1 0 0 1 0 0) " stroke-linecap="round"/><path d="M 63.414 0 c -7.242 0 -13.237 5.589 -13.898 12.667 c 8 2.023 13.947 9.261 13.947 17.881 v 2.385 c 7.657 -0.027 13.914 -6.298 13.914 -13.961 v -5.008 C 77.378 6.284 71.094 0 63.414 0 z" style="stroke: none; stroke-width: 1; stroke-dasharray: none; stroke-linecap: butt; stroke-linejoin: miter; stroke-miterlimit: 10; fill: rgb(113,212,86); fill-rule: nonzero; opacity: 1;" transform=" matrix(1 0 0 1 0 0) " stroke-linecap="round"/><path d="M 13.915 73.152 c 0 -11.292 7.322 -21.261 17.677 -24.941 c -2.861 -3.029 -4.702 -7.019 -4.995 -11.43 c -0.004 0 -0.007 0 -0.011 0 c -2.831 0 -5.5 -0.704 -7.863 -1.927 C 8.166 36.429 0 45.59 0 56.568 v 14.444 c 0 1.322 1.082 2.404 2.404 2.404 h 11.511 V 73.152 z" style="stroke: none; stroke-width: 1; stroke-dasharray: none; stroke-linecap: butt; stroke-linejoin: miter; stroke-miterlimit: 10; fill: rgb(240,88,47); fill-rule: nonzero; opacity: 1;" transform=" matrix(1 0 0 1 0 0) " stroke-linecap="round"/><path d="M 26.536 32.932 v -2.385 c 0 -8.62 5.946 -15.858 13.947 -17.881 C 39.823 5.589 33.828 0 26.586 0 c -7.68 0 -13.964 6.284 -13.964 13.964 v 5.008 C 12.622 26.635 18.879 32.905 26.536 32.932 z" style="stroke: none; stroke-width: 1; stroke-dasharray: none; stroke-linecap: butt; stroke-linejoin: miter; stroke-miterlimit: 10; fill: rgb(240,88,47); fill-rule: nonzero; opacity: 1;" transform=" matrix(1 0 0 1 0 0) " stroke-linecap="round"/></g></svg>';
        } else if (lowerSource.includes('google')) {
            return '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 48 48"><path fill="#FFC107" d="M43.611 20.083H42V20H24v8h11.303c-1.649 4.657-6.08 8-11.303 8c-6.627 0-12-5.373-12-12s5.373-12 12-12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.268 4 24 4C12.955 4 4 12.955 4 24s8.955 20 20 20s20-8.955 20-20c0-1.341-.138-2.65-.389-3.917z"/><path fill="#FF3D00" d="m6.306 14.691l6.571 4.819C14.655 15.108 18.961 12 24 12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.268 4 24 4C16.318 4 9.656 8.337 6.306 14.691z"/><path fill="#4CAF50" d="M24 44c5.166 0 9.86-1.977 13.409-5.192l-6.19-5.238A11.91 11.91 0 0 1 24 36c-5.202 0-9.619-3.317-11.283-7.946l-6.522 5.025C9.505 39.556 16.227 44 24 44z"/><path fill="#1976D2" d="M43.611 20.083H42V20H24v8h11.303a12.04 12.04 0 0 1-4.087 5.571l.003-.002l6.19 5.238C36.971 39.205 44 34 44 24c0-1.341-.138-2.65-.389-3.917z"/></svg>';
        } else if (lowerSource.includes('facebook')) {
            return '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 48 48"><path fill="#039be5" d="M24 5A19 19 0 1 0 24 43A19 19 0 1 0 24 5Z"/><path fill="#fff" d="M26.572,29.036h4.917l0.772-4.995h-5.69v-2.73c0-2.075,0.678-3.915,2.619-3.915h3.119v-4.359c-0.548-0.074-1.707-0.236-3.897-0.236c-4.573,0-7.254,2.415-7.254,7.917v3.323h-4.701v4.995h4.701v13.729C22.089,42.905,23.032,43,24,43c0.875,0,1.729-0.08,2.572-0.194V29.036z"/></svg>';
        } else if (lowerSource.includes('magicbricks')) {
            return '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="#e74c3c" d="M12 2L2 7v10c0 5.55 3.84 10.74 9 12c5.16-1.26 9-6.45 9-12V7l-10-5z"/><text x="12" y="16" font-size="10" fill="white" text-anchor="middle" font-weight="bold">MB</text></svg>';
        } else if (lowerSource.includes('99acres')) {
            return '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="#27ae60" d="M12 2L2 7v10c0 5.55 3.84 10.74 9 12c5.16-1.26 9-6.45 9-12V7l-10-5z"/><text x="12" y="16" font-size="9" fill="white" text-anchor="middle" font-weight="bold">99</text></svg>';
        } else if (lowerSource.includes('housing')) {
            return '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><path fill="#3498db" d="M12 2L2 7v10c0 5.55 3.84 10.74 9 12c5.16-1.26 9-6.45 9-12V7l-10-5z"/><text x="12" y="16" font-size="10" fill="white" text-anchor="middle" font-weight="bold">H</text></svg>';
        } else if (lowerSource.includes('whatsapp')) {
            return '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 48 48"><path fill="#25D366" d="M24 4C12.95 4 4 12.95 4 24c0 3.53.92 6.84 2.52 9.71L4 44l10.48-2.48A19.9 19.9 0 0 0 24 44c11.05 0 20-8.95 20-20S35.05 4 24 4z"/><path fill="#FFF" d="M35.2 12.8c-3-3-7-4.7-11.2-4.7-8.7 0-15.8 7.1-15.8 15.8 0 2.8.7 5.5 2.1 7.9L8 40l8.3-2.2c2.3 1.3 4.9 1.9 7.7 1.9h.1c8.7 0 15.8-7.1 15.8-15.8 0-4.2-1.6-8.2-4.7-11.1zM24 36.6c-2.4 0-4.7-.6-6.7-1.8l-.5-.3-4.8 1.3L13.3 31l-.3-.5c-1.3-2.1-2-4.5-2-6.9 0-7.2 5.9-13.1 13.1-13.1 3.5 0 6.8 1.4 9.3 3.9 2.5 2.5 3.9 5.8 3.9 9.3-.1 7.2-6 13.2-13.3 13.2zm7.2-9.8c-.4-.2-2.3-1.1-2.7-1.3-.3-.1-.6-.2-.8.2-.2.4-1 1.3-1.2 1.5-.2.2-.4.3-.8.1-.4-.2-1.6-.6-3-1.9-1.1-1-1.9-2.2-2.1-2.6-.2-.4 0-.6.2-.8.2-.2.4-.4.6-.7.2-.2.3-.4.4-.6.1-.2.1-.5 0-.7-.1-.2-.8-2-1.1-2.7-.3-.7-.6-.6-.8-.6h-.7c-.2 0-.6.1-.9.5-.3.4-1.2 1.2-1.2 2.9s1.2 3.4 1.4 3.6c.2.2 2.8 4.3 6.8 6 4 1.7 4 1.1 4.7 1.1.7 0 2.3-.9 2.6-1.8.3-.9.3-1.6.2-1.8-.1-.2-.3-.3-.7-.5z"/></svg>';
        }
        return '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="#95a5a6"/></svg>';
    }

    // Helper function to format assigned users
    function formatAssignedUsers(users) {
        if (!users || users.trim() === '') return '<span class="user-badge">Not Assigned</span>';

        const userArray = users.split(',').map(u => u.trim()).filter(u => u);
        if (userArray.length === 0) return '<span class="user-badge">Not Assigned</span>';

        if (userArray.length === 1) {
            return `<span class="user-badge">${userArray[0]}</span>`;
        }

        return `<span class="user-badge clickable">${userArray[0]} ...more</span>`;
    }

    // Update the table
    function updateTable(rows) {
        uploaddata.innerHTML = ''; // Clear the table body

        rows.forEach(row => {
            const tr = document.createElement('tr');
            tr.dataset.apiId = row.id; // Store row ID for later use

            // Check current visibility of columns from headers
            const headers = document.querySelectorAll('#myTable thead th');
            const apiKeyVisible = headers[2] && headers[2].style.display !== 'none';
            const actionsVisible = headers[6] && headers[6].style.display !== 'none';
            const createdAtVisible = headers[7] && headers[7].style.display !== 'none';

            tr.innerHTML = `
                <td style="width: 40px;">
                    <input type="checkbox" class="row-checkbox" data-id="${row.id}">
                </td>
                <td>${row.project_name}</td>
                <td style="display: ${apiKeyVisible ? 'table-cell' : 'none'};">${row.api_key}</td>
                <td class="user-cell" data-users="${row.assign_user}">${formatAssignedUsers(row.assign_user)}</td>
                <td class="api-source-cell">
                    <div class="d-flex align-items-center justify-content-center">
                        ${getPlatformIcon(row.lead_source, row.type)}
                    </div>
                </td>
                <td>${row.fb_form_leads}</td>
                <td style="display: ${actionsVisible ? 'table-cell' : 'none'};">
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
                <td style="display: ${createdAtVisible ? 'table-cell' : 'none'};">${row.created_at}</td>
                <td class="chevron-cell">
                    <div class="chevron-icon">
                        <i class="bi bi-chevron-right"></i>
                    </div>
                </td>
            `;

            // Store the complete row data on the tr element for expansion
            tr.rowData = row;

            uploaddata.appendChild(tr);
        });

        // Re-attach checkbox listeners after updating table
        attachCheckboxListeners();

        // Attach chevron click handlers
        attachChevronHandlers();
    }

    // Create expanded row content
    function createExpandedRowContent(row) {
        return `
            <tr class="expanded-row">
                <td colspan="9">
                    <div class="expanded-content">
                        <div class="expanded-header">API Details</div>
                        <div class="expanded-body">
                            <div class="detail-grid">
                                <div class="detail-item">
                                    <span class="detail-label">Project Name:</span>
                                    <span class="detail-value">${row.project_name}</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">API Key:</span>
                                    <span class="detail-value">${row.api_key}</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Assigned Users:</span>
                                    <span class="detail-value">${row.assign_user || 'Not Assigned'}</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">API Source:</span>
                                    <span class="detail-value">${row.lead_source}</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Leads Count:</span>
                                    <span class="detail-value">${row.fb_form_leads}</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Created:</span>
                                    <span class="detail-value">${row.created_at}</span>
                                </div>
                            </div>
                            <div class="expanded-actions">
                                <div class="action-header">Actions</div>
                                <div class="action-buttons">
                                    <button class="action-btn edit-btn editApiBtn" data-bs-toggle="modal" data-bs-target="#editApiModal" data-id="${row.id}" data-assign-user="${row.assign_user}">
                                        <i class="bi bi-pencil-square"></i> Edit
                                    </button>
                                    <button class="action-btn delete-btn deleteApiBtn" data-id="${row.id}">
                                        <i class="bi bi-trash-fill"></i> Delete
                                    </button>
                                    <button class="action-btn curl-btn" onclick="window.location.href='../scripts/generate_curl_excel.php?id=${row.id}'">
                                        <i class="bi bi-cloud-arrow-down"></i> cURL
                                    </button>
                                    <button class="action-btn report-btn" onclick="downloadReport(this)" data-lead-source="${row.lead_source}" data-project-name="${row.project_name}">
                                        <i class="bi bi-cloud-arrow-down"></i> Report
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </td>
            </tr>
        `;
    }

    // Attach chevron click handlers
    function attachChevronHandlers() {
        const rows = document.querySelectorAll('#uploaddata tr:not(.expanded-row)');
        rows.forEach(tr => {
            tr.style.cursor = 'pointer';
            tr.addEventListener('click', function (e) {
                // Don't expand if clicking on checkbox or user badge
                if (e.target.closest('.row-checkbox') ||
                    e.target.closest('.user-badge') ||
                    e.target.closest('.editApiBtn') ||
                    e.target.closest('.deleteApiBtn')) {
                    return;
                }

                e.stopPropagation();
                const chevronIcon = this.querySelector('.chevron-icon i');
                const nextRow = this.nextElementSibling;

                // Close all other expanded rows first
                const allExpandedRows = document.querySelectorAll('.expanded-row');
                const allExpandedTrs = document.querySelectorAll('.row-expanded');

                allExpandedRows.forEach(expandedRow => {
                    if (expandedRow !== nextRow) {
                        expandedRow.remove();
                    }
                });

                allExpandedTrs.forEach(expandedTr => {
                    if (expandedTr !== this) {
                        const otherChevron = expandedTr.querySelector('.chevron-icon i');
                        if (otherChevron) {
                            otherChevron.classList.remove('bi-chevron-down');
                            otherChevron.classList.add('bi-chevron-right');
                        }
                        expandedTr.classList.remove('row-expanded');
                    }
                });

                // Check if current row is already expanded
                if (nextRow && nextRow.classList.contains('expanded-row')) {
                    // Collapse current row
                    nextRow.remove();
                    if (chevronIcon) {
                        chevronIcon.classList.remove('bi-chevron-down');
                        chevronIcon.classList.add('bi-chevron-right');
                    }
                    this.classList.remove('row-expanded');
                } else {
                    // Expand current row
                    const expandedContent = createExpandedRowContent(this.rowData);
                    this.insertAdjacentHTML('afterend', expandedContent);
                    if (chevronIcon) {
                        chevronIcon.classList.remove('bi-chevron-right');
                        chevronIcon.classList.add('bi-chevron-down');
                    }
                    this.classList.add('row-expanded');

                    // Attach click handlers to detail values for expansion
                    attachDetailValueHandlers();
                }
            });
        });
    }

    // Attach click handlers to detail values for mobile expansion
    function attachDetailValueHandlers() {
        const detailValues = document.querySelectorAll('.detail-value');
        detailValues.forEach(value => {
            value.addEventListener('click', function (e) {
                e.stopPropagation(); // Prevent row collapse
                this.classList.toggle('expanded');
            });
        });
    }

    // Make functions globally accessible
    window.updateTable = updateTable;

    // Checkbox handling functions
    function attachCheckboxListeners() {
        const selectAllCheckbox = document.getElementById('select-all');
        const rowCheckboxes = document.querySelectorAll('.row-checkbox:not(#select-all)');
        const deleteSelectedBtn = document.getElementById('delete-selected-btn');

        // Select All functionality
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function () {
                rowCheckboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
                updateDeleteButtonState();
            });
        }

        // Individual checkbox change
        rowCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function () {
                updateSelectAllState();
                updateDeleteButtonState();
            });
        });

        updateDeleteButtonState();
    }

    // Update select-all checkbox state based on individual checkboxes
    function updateSelectAllState() {
        const selectAllCheckbox = document.getElementById('select-all');
        const rowCheckboxes = document.querySelectorAll('.row-checkbox:not(#select-all)');
        const checkedCount = document.querySelectorAll('.row-checkbox:not(#select-all):checked').length;

        if (selectAllCheckbox) {
            selectAllCheckbox.checked = checkedCount === rowCheckboxes.length && rowCheckboxes.length > 0;
            selectAllCheckbox.indeterminate = checkedCount > 0 && checkedCount < rowCheckboxes.length;
        }
    }

    // Update delete and assign button states based on selection
    function updateDeleteButtonState() {
        const deleteSelectedBtn = document.getElementById('delete-selected-btn');
        const assignButton = document.getElementById('assign-button');
        const checkedCount = document.querySelectorAll('.row-checkbox:not(#select-all):checked').length;

        if (deleteSelectedBtn) {
            deleteSelectedBtn.disabled = checkedCount === 0;
        }

        if (assignButton) {
            assignButton.disabled = checkedCount === 0;
        }
    }

    // Get selected row IDs
    function getSelectedRowIds() {
        const checkedBoxes = document.querySelectorAll('.row-checkbox:not(#select-all):checked');
        return Array.from(checkedBoxes).map(checkbox => checkbox.dataset.id);
    }

    // Make getSelectedRowIds globally accessible for delete functionality
    window.getSelectedRowIds = getSelectedRowIds;

    // Update pagination
    function updatePagination(totalRows, currentPage, rowsPerPage) {
        totalPages = Math.ceil(totalRows / rowsPerPage);
        const start = (currentPage - 1) * rowsPerPage + 1;
        const end = Math.min(currentPage * rowsPerPage, totalRows);
        rowInfo.innerText = `Showing ${start} to ${end} of ${totalRows} entries`;

        prevButton.disabled = currentPage === 1;
        nextButton.disabled = currentPage === totalPages;

        pageNumbersContainer.innerHTML = '';
        for (let i = 1; i <= totalPages; i++) {
            const button = document.createElement('button');
            button.innerText = i;
            button.className = i === currentPage ? 'active' : '';
            button.addEventListener('click', () => fetchData(i, rowsPerPage));
            pageNumbersContainer.appendChild(button);
        }
    }

    // Make updatePagination globally accessible
    window.updatePagination = updatePagination;

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

    function getCellModel() {
        const userCells = document.querySelectorAll('.user-cell'); // Dynamically fetch user cells
        userCells.forEach(cell => {
            const usersData = cell.dataset.users || '';
            const userList = usersData.trim().split(',').map(u => u.trim()).filter(u => u);

            // Only add click handler if there are multiple users
            if (userList.length > 1) {
                const badge = cell.querySelector('.user-badge');
                if (badge) {
                    badge.style.cursor = 'pointer';
                    // Add click listener to the badge only, not the entire cell
                    badge.addEventListener('click', function (e) {
                        e.stopPropagation(); // Prevent event bubbling
                        document.getElementById('assignUserList').innerText = userList.join(', ');
                        const assignUserModal = new bootstrap.Modal(document.getElementById('assignUserModal'));
                        assignUserModal.show();
                    });
                }
            }
        });
    }

    // Make getCellModel globally accessible
    window.getCellModel = getCellModel;

    // this is for create and delete API script Start
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
                if (response.status === "success") {
                    // Show success toast notification at the bottom
                    Swal.fire({
                        toast: true,
                        position: 'bottom',
                        icon: 'success',
                        title: response.message,
                        showConfirmButton: false,
                        timer: 3000,
                        timerProgressBar: true,
                        customClass: {
                            popup: 'custom-toast-popup'
                        }
                    });

                    // Call fetchData to update the table
                    fetchData(currentPage, rowsPerPage);

                    // Reset the form
                    $("#createApiForm")[0].reset();

                    // Hide the modal using Bootstrap's modal hide method
                    $("#apicreationModal").modal("hide");
                } else {
                    // Show error toast notification
                    Swal.fire({
                        toast: true,
                        position: 'bottom',
                        icon: 'error',
                        title: response.message,
                        showConfirmButton: false,
                        timer: 3000,
                        timerProgressBar: true,
                        customClass: {
                            popup: 'custom-toast-popup'
                        }
                    });
                }
            },
            error: function () {
                // Show error toast notification
                Swal.fire({
                    toast: true,
                    position: 'bottom',
                    icon: 'error',
                    title: 'An error occurred. Please try again.',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true,
                    customClass: {
                        popup: 'custom-toast-popup'
                    }
                });
            },
        });
    });

    // Delete API button
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

    // This Script is for UPDATE THE API USERS START
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
            if (result.status === "success") {
                // Refresh the entire table from the server to show updated data
                fetchData(currentPage, rowsPerPage);

                // Show success toast notification
                Swal.fire({
                    toast: true,
                    position: 'bottom',
                    icon: 'success',
                    title: result.message,
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true,
                    customClass: {
                        popup: 'custom-toast-popup'
                    }
                });

                // Clear the modal form
                document.getElementById("editApiForm").reset();
                document.querySelector("#editApiModal .btn-close").click();
            } else {
                // Show error toast notification
                Swal.fire({
                    toast: true,
                    position: 'bottom',
                    icon: 'error',
                    title: result.message,
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true,
                    customClass: {
                        popup: 'custom-toast-popup'
                    }
                });
            }
        } catch (error) {
            console.error("Error updating API:", error);
            // Show error toast notification
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
        }
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
    });
    document.getElementById("create_users").addEventListener("change", function () {
        const selectedOptions = Array.from(this.selectedOptions).map(option => option.value);
        document.getElementById("assign_user").value = selectedOptions.join(", ");
    });
    // This Script is for UPDATE THE API USERS END

    fetchData(currentPage, rowsPerPage);
});
// REPORT SCRIPT START
function downloadReport(button) {
    const leadSource = encodeURIComponent(button.getAttribute('data-lead-source'));
    const projectName = encodeURIComponent(button.getAttribute('data-project-name'));

    const url = `../scripts/generate_api_report.php?lead_source=${leadSource}&project_name=${projectName}`;
    window.location.href = url;
}
// REPORT SCRIPT END 