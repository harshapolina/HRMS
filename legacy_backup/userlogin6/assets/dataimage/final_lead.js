let iconCounter = 0;

function getNextIconPath() {
    iconCounter = (iconCounter % 6) + 1; // Cycle through 1-6
    return `assets/dataimage/icon-${iconCounter}.png`;
}

$(document).ready(function() {
    // Track currently expanded row and phone row
    var currentExpandedRow = null;
    var currentPhoneRow = null;
    var table;
    var currentActiveFilter = 'my'; // Track the currently active filter

    // Function to encrypt phone number (simple masking)
    function encryptPhone(phone) {
        if (!phone) return '';

        // Remove all non-digit characters
        const digitsOnly = phone.replace(/\D/g, '');

        // Handle very short numbers (show nothing)
        if (digitsOnly.length <= 2) return '***';

        // For Indian numbers (typically 10 digits) show last 4 digits
        if (digitsOnly.length === 10) {
            return '•••••' + digitsOnly.slice(-5);
        }

        // For international numbers with country code, show last 4 digits
        if (digitsOnly.length > 10) {
            return '•••-' + digitsOnly.slice(-4);
        }

        // Default case - show last 4 digits
        const lastFour = digitsOnly.slice(-4);
        return '•••••' + lastFour;
    }

    // Function to get last touch status (This logic might need adjustment based on actual data from update_status.php)
    function getLastTouchStatus(dateString) {
        if (!dateString) {
            return {
                text: "(Untouched)",
                class: "untouched"
            };
        }

        const today = new Date();
        const date = new Date(dateString);
        const diffTime = Math.abs(today - date);
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

        if (diffDays === 0) {
            return {
                text: "Touched today",
                class: "recent"
            };
        } else if (diffDays === 1) {
            return {
                text: "Touched yesterday",
                class: "recent"
            };
        } else if (diffDays <= 7) {
            return {
                text: "Touched this week",
                class: "this-week"
            };
        } else {
            return {
                text: "Touched over a week ago",
                class: "over-week"
            };
        }
    }

    // Function to update selection actions visibility
    function updateSelectionActions() {
        const $selectedRows = $('.row-checkbox:checked');
        const selectedCount = $selectedRows.length;
        const $mobileNav = $('.mobile-bottom-nav');

        if (selectedCount > 0) {
            $('.selection-actions').show();
            $mobileNav.addClass('has-selection');

            // Update selection count
            $('.assign-btn-mobile .selection-count, .delete-btn-mobile .selection-count')
                .text(selectedCount)
                .css('display', 'flex');

            $('.assign-btn-mobile, .delete-btn-mobile').show();
        } else {
            $('.selection-actions').hide();
            $mobileNav.removeClass('has-selection');
            $('.selection-count').css('display', 'none');
        }
    }

    // Mobile button click handlers
    $(document).on('click', '.assign-btn-mobile', function() {
        $('.assign-users-btn').click();
    });

    $(document).on('click', '.delete-btn-mobile', function() {
        $('.delete-selected-btn').click();
    });

    // Function to get selected lead IDs
    function getSelectedLeadIds() {
        const selectedIds = [];
        $('.row-checkbox:checked').each(function() {
            const $row = $(this).closest('tr');

            // First try to get ID from the hidden element in lead column
            let leadId = $row.find('.lead-id-hidden').text().trim();

            // If not found, try to get from the ID column (if visible)
            if (!leadId) {
                leadId = $row.find('.lead-id-section').text().replace('#', '').trim();
            }

            if (leadId) {
                selectedIds.push(leadId);
            }
        });
        return selectedIds;
    }

    // Handle responsive behavior
    function handleResponsiveBehavior() {
        // Add your responsive behavior logic here
        console.log('Handling responsive behavior');
    }

    // Handle column visibility
    function handleColumnVisibility() {
        // Add your column visibility logic here
        console.log('Handling column visibility');
    }

    // Destroy existing DataTable if it exists
    if ($.fn.DataTable.isDataTable('#leadsTable')) {
        $('#leadsTable').DataTable().clear().destroy();
    }

    // Initialize DataTable
    table = $('#leadsTable').DataTable({
        processing: true, // Show processing indicator
        serverSide: true, // Enable server-side processing
        ajax: {
            url: 'update_status.php', // Your PHP script for fetching data
            type: 'GET',
            data: function(d) {
                // DataTables sends page, length, search, order parameters by default
                // You can add custom filters here
                d.page = (d.start / d.length) + 1; // Calculate current page
                d.rowsPerPage = d.length; // Rows per page
                d.searchQuery = d.search.value; // Global search query

                // Add filter parameters based on currentActiveFilter
                if (currentActiveFilter && currentActiveFilter !== 'my') { // 'my' means no specific filter applied
                    d.filter = currentActiveFilter;
                } else {
                    d.filter = ''; // No filter
                }

                // Add multi-filters from the modal if applied
                const multiFilters = {};
                if ($('#filterModal').is(':visible')) { // Only apply if modal is open and values are present
                    if ($('#filterName').val().trim()) multiFilters.name = $('#filterName').val().trim();
                    if ($('#filterId').val().trim()) multiFilters.id = $('#filterId').val().trim();
                    if ($('#filterEmail').val().trim()) multiFilters.email = $('#filterEmail').val().trim();
                    if ($('#filterAssignedLead').val().trim()) multiFilters.user_unique_id = $('#filterAssignedLead').val().trim();

                    const statusFilters = $('#statusFilter').val();
                    if (statusFilters && statusFilters.length > 0) multiFilters.status = statusFilters.join(',');

                    const sourceFilters = $('#sourceFilter').val();
                    if (sourceFilters && sourceFilters.length > 0) multiFilters.source_of_lead = sourceFilters.join(',');

                    const projectFilters = $('#projectFilter').val();
                    if (projectFilters && projectFilters.length > 0) multiFilters.assign_project_name = projectFilters.join(',');

                    const locationFilters = $('#locationFilter').val();
                    if (locationFilters && locationFilters.length > 0) multiFilters.location = locationFilters.join(',');

                    if ($('#startDate').val()) multiFilters.start_date = $('#startDate').val();
                    if ($('#endDate').val()) multiFilters.end_date = $('#endDate').val();
                }
                d.multiFilters = JSON.stringify(multiFilters);

                // Remove default DataTables search and order parameters if you handle them manually in PHP
                delete d.search;
                delete d.order;
                delete d.columns;
            },
            dataSrc: function(json) {
                // Map the data from your PHP response to DataTables expected format
                // Your PHP script should return 'data' (array of rows) and 'recordsFiltered', 'recordsTotal'
                json.recordsTotal = json.totalRows;
                json.recordsFiltered = json.totalRows; // Assuming totalRows is the filtered count
                return json.data.map(row => {
                    // Map your PHP data structure to the expected DataTables column structure
                    // This is crucial for correct rendering
                    return {
                        id: row.id,
                        name: row.name,
                        lead_identity: row.lead_identity,
                        profilePic: getNextIconPath(), // Assign a dynamic profile pic
                        created: row.created_at, // Use created_at from PHP
                        lastTouch: row.user_status === 'Pending' ? 'Untouched' : row.created_at, // Adjust based on your logic
                        email: row.email,
                        phone: row.number,
                        assignedLead: row.user_unique_id, // Assuming user_unique_id is the assigned lead
                        assignedProject: row.assign_project_name,
                        status: row.user_status,
                        location: row.location,
                        source: row.source_of_lead // Use source_of_lead from PHP
                    };
                });
            }
        },
        responsive: true,
        ordering: false, // Disable client-side ordering as it's handled by server
        dom: '<"top-container"<"search-container"f><"button-container"B><"length-container"l>>rt<"bottom-container"ip>',
        buttons: [{
            text: '<i class="fas fa-filter"></i> Filter',
            className: 'dt-button filter-btn',
            action: function(e, dt, node, config) {
                $('#filterModal').show();
                $('body').css('overflow', 'hidden');

                // Initialize Select2 after modal is shown
                $('.select-input').select2({
                    placeholder: "Select options",
                    allowClear: true,
                    width: '100%',
                    dropdownParent: $('#filterModal') // Important for proper positioning
                });
            }
        }, {
            extend: 'colvis',
            text: '<i class="fas fa-columns"></i> Columns',
            className: 'dt-button columns-btn',
            titleAttr: 'Column Visibility',
            columns: ':not(.no-colvis)',
            columnText: function(dt, idx, title) {
                const columnNames = {
                    0: 'Select',
                    1: 'Lead Info',
                    2: 'ID',
                    3: 'Expand',
                    4: 'Email',
                    5: 'Project',
                    6: 'Location',
                    7: 'Lead Source',
                    8: 'Assigned Lead',
                    9: 'Actions'
                };
                return columnNames[idx] || title;
            }
        }],
        lengthMenu: [
            [5, 10, 25, 50, -1],
            [5, 10, 25, 50, "All"]
        ],
        pageLength: 10,

        columns: [{
            data: null,
            className: 'checkbox-cell no-colvis',
            orderable: false,
            render: function() {
                return '<input type="checkbox" class="row-checkbox">';
            }
        }, {
            data: null,
            className: 'no-colvis',
            render: function(data, type, row) {
                const encryptedPhone = encryptPhone(row.phone);
                const touchStatus = getLastTouchStatus(row.lastTouch);

                // Determine lead identity icon
                let leadIdentityIcon = '';
                switch (row.lead_identity) {
                    case 'Hot':
                        leadIdentityIcon = '<i class="fas fa-fire hot-icon"></i>';
                        break;
                    case 'Warm':
                        leadIdentityIcon = '<i class="fas fa-sun warm-icon"></i>';
                        break;
                    case 'Cold':
                        leadIdentityIcon = '<i class="fas fa-snowflake cold-icon"></i>';
                        break;
                    default:
                        leadIdentityIcon = '';
                }

                // Get the source icon path (assuming source_of_lead is like 'google ads', 'facebook ads')
                let sourceIconPath = 'assets/dataimage/default-source.svg';
                let sourceName = 'Unknown';
                if (row.source) {
                    if (row.source.includes('google')) {
                        sourceIconPath = 'assets/dataimage/google-logo.svg';
                        sourceName = 'Google Ads';
                    } else if (row.source.includes('facebook')) {
                        sourceIconPath = 'assets/dataimage/facebook.svg';
                        sourceName = 'Facebook Ads';
                    } else if (row.source.includes('instagram')) {
                        sourceIconPath = 'assets/dataimage/instagram.svg';
                        sourceName = 'Instagram Ads';
                    }else {
                          // Default path for all other sources
                            sourceIconPath = 'assets/dataimage/mecntec-icon.png';
                            sourceName = row.source;
                    }
                }


                return `
                    <div class="lead-profile">
                        <div class="left-lead">
                            <img src="${row.profilePic}" alt="${row.name}" class="lead-avatar">
                            <div class="notes-section">
                                <div class="lead-info">
                                    <div class="created-info"><b>Created at</b><br>${row.created}</div>
                                    <div class="mobile-project-info">
                                        <div class="lead-name">
                                            <h4>${row.name}</h4>
                                            ${leadIdentityIcon}
                                        </div>
                                        <div class="lead-id-hidden" style="display:none">${row.id}</div>
                                        <span class="mobile-project status-badge ${row.status.toLowerCase().replace(' ', '-')}">${row.status}</span>
                                    </div>
                                    <div class="last-touch-status touch-desktop ${touchStatus.class}">${touchStatus.text}</div>
                                    <div class="mobile-project-info">
                                        <span class="project-name-mobile">Project: ${row.assignedProject}</span>
                                    </div>
                                    <div class="phone-info encrypted" data-real-phone="${row.phone}">${encryptedPhone}</div>
                                </div>
                            </div>
                        </div>
                        <div class="right-lead">
                            <div class="mobile-source">
                                <img src="${sourceIconPath}" alt="${sourceName}" class="source-logo">
                            </div>
                            <div class="last-touch-status touch-mobile ${touchStatus.class}">${touchStatus.text}</div>
                        </div>
                    </div>
                    <div class="created-info-mobile" style="display:none">${row.created}</div>
                `;
            }
        }, {
            data: "id",
            className: 'id-column',
            visible: false,
            render: function(data) {
                return `<div class="lead-id-section">#${data}</div>`;
            }
        }, {
            data: null,
            className: 'expand-btn-cell no-colvis',
            orderable: false,
            render: function() {
                return '<button class="expand-row-btn" aria-label="Expand row"><i class="fas fa-chevron-down"></i></button>';
            }
        }, {
            data: "email",
            className: 'mobile-hide email-column',
            visible: false,
            render: function(data) {
                return `<div class="contact-info">${data}</div>`;
            }
        }, {
            data: null,
            className: 'mobile-hide project-column',
            render: function(data, type, row) {
                return `
                        <div class="project-info">
                            <div>${row.assignedProject}</div>
                            <span class="status-badge ${row.status.toLowerCase().replace(' ', '-')}">${row.status}</span>
                        </div>
                    `;
            }
        }, {
            data: "location",
            className: 'mobile-hide location-column',
            visible: false,
            render: function(data) {
                return `<div class="location-info">${data}</div> `;
            }
        }, {
            data: "source",
            className: 'mobile-hide source-column',
            visible: false,
            render: function(data) {
                // Use the source_of_lead from PHP
                let sourceIconPath = 'assets/dataimage/default-source.svg';
                if (data) {
                    if (data.includes('google')) {
                        sourceIconPath = 'assets/dataimage/google-logo.svg';
                    } else if (data.includes('facebook')) {
                        sourceIconPath = 'assets/dataimage/facebook.svg';
                    } else {
                        sourceIconPath = `assets/dataimage/dataimage/mecntec-icon.png`;
                    }
                }
                return `<img src="${sourceIconPath}" alt="${data.replace('.svg', '').replace('-', ' ')}"
                class="source-logo" style="height: 20px; width: auto;">`;
            }
        }, {
            data: "assignedLead",
            className: 'mobile-hide assigned-column',
            visible: false,
            render: function(data) {
                return `<div class="assigned-lead">${data}</div>`;
            }
        }, {
            data: null,
            className: 'mobile-hide actions-column',
            orderable: false,
            render: function(data, type, row) {
            return `
                <div class="action-buttons-leads">
                    <button class="action-btn phone tooltip" data-tooltip="Call"><i class="fas fa-phone"></i></button>
                    <button class="action-btn whatsapp tooltip" data-tooltip="WhatsApp"><i class="fab fa-whatsapp"></i></button>
                    <button class="action-btn status tooltip" data-tooltip="update status"><i class="fas fa-refresh"></i></button>
                    <button class="action-btn history tooltip unique-toggle-btn" data-tooltip="History" data-id="${row.id}"><i class="fas fa-history"></i></button>
                    <button class="action-btn reassign tooltip" data-tooltip="Reassign"><i class="fas fa-user-friends"></i></button>
                </div>
            `;
        }
        }],
        language: {
            search: "",
            searchPlaceholder: "Search leads...",
            lengthMenu: "Show _MENU_ leads",
            info: "Showing _START_ to _END_ of _TOTAL_ leads",
            infoEmpty: "No leads to show",
            paginate: {
                first: "First",
                last: "Last",
                next: "Next",
                previous: "Previous"
            }
        },

        initComplete: function() {
            // Move controls to container
            $('.top-container').detach().appendTo('#tableControlsContainer');

            // Add date range filter
            const dateRangeHTML = `
                <div class="date-range-filter">
                <button class="calendar-btn" id="openDateRangePicker">
                    <i class="fas fa-calendar-alt"></i>
                </button>
                <input type="date" id="dateFrom" class="date-input" placeholder="From Date" style="display: none;">
                <input type="date" id="dateTo" class="date-input" placeholder="To Date" style="display: none;">
            </div>
            `;

            // Style the top container
            $('.top-container').css({
                'background': 'white',
                'display': 'flex',
                'align-items': 'center',
                'justify-content': 'space-between',
                'flex-wrap': 'wrap',
                'margin-top': '4px',
                'width': '100%'
            });

            $('.search-container').css({
                'flex': '1',
                'min-width': '80px',
                'max-width': '100%',
                'order': '1'
            });
            $('.search-container').after(dateRangeHTML);

            $('.button-container').css({
                'display': 'flex',
                'align-items': 'center',
                'gap': '10px',
                'order': '3'
            });

            $('.length-container').css({
                'order': '4'
            });

            // Style search input
            $('.dataTables_filter input').css({
                'width': '100%',
                'padding': '8.5px 12px',
                'border': '1px solid white',
                'font-size': '14px',
                'transition': 'border-color 0.2s ease'
            }).attr('placeholder', 'Search leads...');

            // Remove default label text
            $('.dataTables_filter label').contents().filter(function() {
                return this.nodeType === 3;
            }).remove();

            // Style buttons
            $('.dt-button').css({
                'background': 'white',
                'border': '1px solid #dadce0',
                'border-radius': '4px',
                'padding': '8px 12px',
                'font-size': '14px',
                'color': '#3c4043',
                'cursor': 'pointer',
                'transition': 'all 0.2s ease',
                'display': 'inline-flex',
                'align-items': 'center',
                'gap': '6px',
                'margin': '0px'
            });

            $('.dt-button:hover').css({
                'background': '#e8eaed',
                'border-color': '#bdc1c6'
            });

            // Style length menu
            $('.dataTables_length label').css({
                'display': 'flex',
                'align-items': 'center',
                'gap': '8px',
                'margin': '0',
                'font-size': '14px'
            });

            $('.dataTables_length select').css({
                'border': '1px solid #dadce0',
                'border-radius': '4px',
                'padding': '8px 12px',
                'font-size': '14px'
            });

            handleResponsiveBehavior();

            // Select all checkbox functionality
            $('#selectAll').change(function() {
                $('.row-checkbox').prop('checked', $(this).prop('checked'));
                updateSelectionActions();
            });

            // Individual checkbox functionality
            $(document).on('change', '.row-checkbox', function() {
                if ($('.row-checkbox:checked').length === $('.row-checkbox').length) {
                    $('#selectAll').prop('checked', true);
                } else {
                    $('#selectAll').prop('checked', false);
                }
                updateSelectionActions();
            });

            // Initialize filter dropdown functionality
            initFilterDropdowns();

            // Handle responsive behavior for column visibility
            handleColumnVisibility();
        }
    });

    // Initialize filter dropdown functionality
    function initFilterDropdowns() {
        // Show/hide dropdowns when clicking header buttons
        $(document).on('click', '.filter-header-btn', function(e) {
            e.stopPropagation();
            e.preventDefault();

            const $header = $(this).closest('.filter-header');
            const $dropdown = $header.find('.filter-dropdown');

            // Hide all other dropdowns
            $('.filter-dropdown').not($dropdown).hide();

            // Toggle current dropdown
            $dropdown.toggle();
        });

        // Close dropdowns when clicking elsewhere
        $(document).on('click', function() {
            $('.filter-dropdown').hide();
        });

        // Prevent dropdown from closing when clicking inside it
        $(document).on('click', '.filter-dropdown', function(e) {
            e.stopPropagation();
        });

        // Handle search input in dropdowns
        $(document).on('keyup', '.filter-search-input', function() {
            const searchTerm = $(this).val().toLowerCase();
            const $dropdown = $(this).closest('.filter-dropdown');
            const columnIndex = parseInt($(this).data('column'));

            if (searchTerm === '') {
                $dropdown.find('.filter-options label').show();
            } else {
                $dropdown.find('.filter-options label').each(function() {
                    const labelText = $(this).text().toLowerCase();
                    $(this).toggle(labelText.includes(searchTerm));
                });
            }
        });

        // Handle filter option selection
        $(document).on('change', '.filter-option', function() {
            const columnIndex = parseInt($(this).data('column'));
            const filterValue = $(this).val();
            const isChecked = $(this).is(':checked');

            if (isChecked) {
                if (filterValue === '') {
                    // If "All" is checked, uncheck others
                    $(this).closest('.filter-options').find('.filter-option').not(this).prop('checked', false);
                    table.column(columnIndex).search('').draw();
                } else {
                    // If specific option is checked, uncheck "All"
                    $(this).closest('.filter-options').find('.filter-option[value=""]').prop('checked', false);

                    // Get all checked values for this column
                    const checkedValues = [];
                    $(this).closest('.filter-options').find('.filter-option:checked').each(function() {
                        if ($(this).val() !== '') {
                            checkedValues.push($(this).val());
                        }
                    });

                    if (checkedValues.length > 0) {
                        // Create regex for multiple values
                        const searchRegex = checkedValues.join('|');
                        table.column(columnIndex).search(searchRegex, true, false).draw();
                    } else {
                        // If no specific options are checked, show all
                        table.column(columnIndex).search('').draw();
                    }
                }
            } else {
                // If unchecking, check if no options are selected
                const anyChecked = $(this).closest('.filter-options').find('.filter-option:checked').length > 0;
                if (!anyChecked) {
                    // If nothing is checked, check "All" and show all
                    $(this).closest('.filter-options').find('.filter-option[value=""]').prop('checked', true);
                    table.column(columnIndex).search('').draw();
                } else {
                    // If specific options are still checked, update filter
                    const checkedValues = [];
                    $(this).closest('.filter-options').find('.filter-option:checked').each(function() {
                        if ($(this).val() !== '') {
                            checkedValues.push($(this).val());
                        }
                    });

                    if (checkedValues.length > 0) {
                        const searchRegex = checkedValues.join('|');
                        table.column(columnIndex).search(searchRegex, true, false).draw();
                    }
                }
            }
        });

        // Reassign Lead Modal Functionality
        function openReassignModal(leadId, leadName) {
            $('body').css('overflow', 'hidden');
            $('#reassignModal').addClass('active').show();

            // Populate the form with lead details
            $('#reassignLeadName').val(leadName);
            $('#reassignLeadId').val(leadId);

            // Initialize Select2
            $('#reassignTo').select2({
                placeholder: "Select Team Member",
                allowClear: false,
                width: '100%',
                dropdownParent: $('#reassignModal')
            });
        }

        function closeReassignModal() {
            $('body').css('overflow', 'auto');
            $('#reassignModal').removeClass('active').hide();
            $('#reassignForm')[0].reset();
        }


        // Replace the existing status modal functions with these corrected versions

        // Status Update Modal Functionality
        function openStatusModal(leadId, leadName, currentStatus) {
    // Close any existing modal first
    closeStatusModal();
    
    $('body').css('overflow', 'hidden');
    
    // Show the modal
    $('#statusModal').addClass('active').show();
    
    // Populate the form with lead details
    $('#statusLeadName').val(leadName);
    $('#statusLeadId').val(leadId);
    
    // Set current status if provided
    if (currentStatus) {
        $('#newStatus').val(currentStatus);
    }
    
    // Initialize Select2 after a short delay to ensure DOM is ready
    setTimeout(function() {
        if (!$('#newStatus').hasClass('select2-hidden-accessible')) {
            $('#newStatus').select2({
                placeholder: "Select Status",
                allowClear: false,
                width: '100%',
                dropdownParent: $('#statusModal')
            });
        }
    }, 100);
}
function closeStatusModal() {
    $('body').css('overflow', 'auto');
    $('#statusModal').removeClass('active').hide();
    
    // Destroy Select2 properly
    if ($('#newStatus').hasClass('select2-hidden-accessible')) {
        $('#newStatus').select2('destroy');
    }
    
    // Reset form
    $('#statusForm')[0].reset();
    
    // Clear any error states
    $('#statusForm .form-group').removeClass('error');
    $('#statusForm .error-message').text('');
}

// Add this to your existing JavaScript
$(document).on('click', '.identity-btn', function() {
    $('.identity-btn').removeClass('active');
    $(this).addClass('active');
    $('#leadIdentityValue').val($(this).data('value'));
});

// Update the status form submission to include new fields
$('#statusForm').off('submit').on('submit', function(e) {
    e.preventDefault();
    
    // Clear previous errors
    $('.form-group').removeClass('error');
    $('.error-message').text('');
    
    // Validate required fields
    if (!$('#newStatus').val()) {
        $('#newStatus').closest('.form-group').addClass('error');
        $('#newStatus').closest('.form-group').find('.error-message').text('Please select a status');
        return false;
    }
    
    const formData = {
        leadId: $('#statusLeadId').val(),
        leadName: $('#statusLeadName').val(),
        newStatus: $('#newStatus').val(),
        followUpDate: $('#followUpDate').val(),
        followUpTime: $('#followUpTime').val(),
        notes: $('#statusNotes').val(),
        leadIdentity: $('#leadIdentityValue').val(),
        changedBy: "Current User",
        changedAt: new Date().toISOString()
    };
    
    // Show loading state
    const $submitBtn = $('#statusForm .submit-btn');
    const originalText = $submitBtn.html();
    $submitBtn.html('<i class="fas fa-spinner fa-spin"></i> Updating...').prop('disabled', true);
    
    // Simulate API call
    setTimeout(function() {
        console.log('Lead status updated:', formData);
        
        // Update the table row
        const leadId = formData.leadId;
        $('tr').each(function() {
            if ($(this).find('.lead-id-section').text().includes(leadId)) {
                const $statusBadge = $(this).find('.status-badge');
                
                // Remove all status classes
                $statusBadge.removeClass(function(index, className) {
                    return (className.match(/(^|\s)\w*-?\w*$/g) || []).join(' ');
                });
                
                // Add new status class
                const statusClass = formData.newStatus.toLowerCase().replace(/\s+/g, '-');
                $statusBadge.addClass(statusClass);
                
                // Update text
                $statusBadge.text(formData.newStatus);
                
                // Update lead identity if changed
                if (formData.leadIdentity) {
                    // Remove existing identity icons
                    $(this).find('.hot-icon, .warm-icon, .cold-icon').remove();
                    
                    // Add new identity icon
                    let identityIcon = '';
                    switch(formData.leadIdentity) {
                        case 'Hot':
                            identityIcon = '<i class="fas fa-fire hot-icon"></i>';
                            break;
                        case 'Warm':
                            identityIcon = '<i class="fas fa-sun warm-icon"></i>';
                            break;
                        case 'Cold':
                            identityIcon = '<i class="fas fa-snowflake cold-icon"></i>';
                            break;
                    }
                    
                    if (identityIcon) {
                        $(this).find('.lead-name').append(identityIcon);
                    }
                }
            }
        });
        
        // Reset button state
        $submitBtn.html(originalText).prop('disabled', false);
        
        // Close modal
        closeStatusModal();
        
        // Show success notification
        showNotification(`Status updated to ${formData.newStatus} for lead ${leadId}`, 'success');
    }, 1500);
});

// Show/hide follow-up fields based on status selection
$('#newStatus').change(function() {
    const selectedStatus = $(this).val();
    
    // Hide all date/time fields first
    $('.status-date-field, .status-time-field').hide();
    
    // Always show notes field
    $('#statusNotes').closest('.form-group').show();
    
    // Show specific fields based on status
    if (['Pending', 'RNR', 'Call Back', 'Interested', 'Follow Up', 
         'Fix Site Visit', 'Site Visit Done', 'Converted', 'Re site visit', 'NQFTP', 'Not Connected'].includes(selectedStatus)) {
        $(`.status-date-field[data-status="${selectedStatus}"], 
           .status-time-field[data-status="${selectedStatus}"]`).show();
        // Show lead identity for these statuses
        $('#leadIdentityDiv').show();
    }
    else if (['Fake', 'Already Booked', 'Not Interested'].includes(selectedStatus)) {
        // Hide lead identity for these statuses
        $('#leadIdentityDiv').hide();
    }
});

// Update the form submission to handle cases without lead identity
$('#statusForm').off('submit').on('submit', function(e) {
    e.preventDefault();
    
    // Clear previous errors
    $('.form-group').removeClass('error');
    $('.error-message').text('');
    
    // Validate required fields
    if (!$('#newStatus').val()) {
        $('#newStatus').closest('.form-group').addClass('error');
        $('#newStatus').closest('.form-group').find('.error-message').text('Please select a status');
        return false;
    }
    
    const selectedStatus = $('#newStatus').val();
    const formData = {
        leadId: $('#statusLeadId').val(),
        leadName: $('#statusLeadName').val(),
        newStatus: selectedStatus,
        notes: $('#statusNotes').val(),
        changedBy: "Current User",
        changedAt: new Date().toISOString()
    };
    
    // Only include lead identity if not in excluded statuses
    if (!['Fake', 'Already Booked', 'Not Interested'].includes(selectedStatus)) {
        formData.leadIdentity = $('#leadIdentityValue').val();
    }
    
    // Add date/time fields based on status
    switch(selectedStatus) {
        case 'Pending':
            formData.pendingDate = $('#pendingDate').val();
            formData.pendingTime = $('#pendingTime').val();
            break;
        case 'RNR':
            formData.rnrDate = $('#rnrDate').val();
            formData.rnrTime = $('#rnrTime').val();
            break;
        case 'Call Back':
            formData.callbackDate = $('#callbackDate').val();
            formData.callbackTime = $('#callbackTime').val();
            break;
        case 'Interested':
            formData.interestedDate = $('#interestedDate').val();
            formData.interestedTime = $('#interestedTime').val();
            break;
        case 'Follow Up':
            formData.followupDate = $('#followupDate').val();
            formData.followupTime = $('#followupTime').val();
            break;
        case 'Fix Site Visit':
            formData.siteVisitDate = $('#siteVisitDate').val();
            formData.siteVisitTime = $('#siteVisitTime').val();
            break;
        case 'Site Visit Done':
            formData.visitDoneDate = $('#visitDoneDate').val();
            formData.visitDoneTime = $('#visitDoneTime').val();
            break;
        case 'Converted':
            formData.convertedDate = $('#convertedDate').val();
            formData.convertedTime = $('#convertedTime').val();
            break;
        case 'Re site visit':
            formData.reSiteVisitDate = $('#reSiteVisitDate').val();
            formData.reSiteVisitTime = $('#reSiteVisitTime').val();
            break;
        case 'NQFTP':
            formData.qualifiedDate = $('#qualifiedDate').val();
            formData.qualifiedTime = $('#qualifiedTime').val();
            break;
        case 'Not Connected':
            formData.notConnectedDate = $('#notConnectedDate').val();
            formData.notConnectedTime = $('#notConnectedTime').val();
            break;
        // No date/time for Fake, Already Booked, Not Interested
    }
    
    // Show loading state
    const $submitBtn = $('#statusForm .submit-btn');
    const originalText = $submitBtn.html();
    $submitBtn.html('<i class="fas fa-spinner fa-spin"></i> Updating...').prop('disabled', true);
    
    // Simulate API call
    setTimeout(function() {
        console.log('Lead status updated:', formData);
        
        // Update the table row
        const leadId = formData.leadId;
        $('tr').each(function() {
            if ($(this).find('.lead-id-section').text().includes(leadId)) {
                const $statusBadge = $(this).find('.status-badge');
                
                // Remove all status classes
                $statusBadge.removeClass(function(index, className) {
                    return (className.match(/(^|\s)\w*-?\w*$/g) || []).join(' ');
                });
                
                // Add new status class
                const statusClass = formData.newStatus.toLowerCase().replace(/\s+/g, '-');
                $statusBadge.addClass(statusClass);
                
                // Update text
                $statusBadge.text(formData.newStatus);
                
                // Only update lead identity if it was included in the form
                if (formData.leadIdentity !== undefined) {
                    // Remove existing identity icons
                    $(this).find('.hot-icon, .warm-icon, .cold-icon').remove();
                    
                    // Add new identity icon if not empty
                    if (formData.leadIdentity) {
                        let identityIcon = '';
                        switch(formData.leadIdentity) {
                            case 'Hot':
                                identityIcon = '<i class="fas fa-fire hot-icon"></i>';
                                break;
                            case 'Warm':
                                identityIcon = '<i class="fas fa-sun warm-icon"></i>';
                                break;
                            case 'Cold':
                                identityIcon = '<i class="fas fa-snowflake cold-icon"></i>';
                                break;
                        }
                        
                        if (identityIcon) {
                            $(this).find('.lead-name').append(identityIcon);
                        }
                    }
                }
            }
        });
        
        // Reset button state
        $submitBtn.html(originalText).prop('disabled', false);
        
        // Close modal
        closeStatusModal();
        
        // Show success notification
        showNotification(`Status updated to ${formData.newStatus} for lead ${leadId}`, 'success');
    }, 1500);
});

// Initialize - hide all date/time fields by default and show lead identity
$(document).ready(function() {
    $('.status-date-field, .status-time-field').hide();
    $('#leadIdentityDiv').show(); // Show by default
});
// Date Range Picker Modal
const datePickerModal = document.createElement('div');
datePickerModal.className = 'date-picker-modal';
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
            <button class="date-picker-btn cancel">Cancel</button>
            <button class="date-picker-btn apply">Apply</button>
        </div>
    </div>
`;
document.body.appendChild(datePickerModal);

// Open modal when calendar button is clicked
document.getElementById('openDateRangePicker').addEventListener('click', function() {
    // Set current values in modal
    document.getElementById('modalDateFrom').value = document.getElementById('dateFrom').value;
    document.getElementById('modalDateTo').value = document.getElementById('dateTo').value;
    
    datePickerModal.style.display = 'flex';
});

// Close modal
datePickerModal.querySelector('.date-picker-close').addEventListener('click', function() {
    datePickerModal.style.display = 'none';
});

datePickerModal.querySelector('.date-picker-btn.cancel').addEventListener('click', function() {
    datePickerModal.style.display = 'none';
});

// Apply date range
datePickerModal.querySelector('.date-picker-btn.apply').addEventListener('click', function() {
    const fromDate = document.getElementById('modalDateFrom').value;
    const toDate = document.getElementById('modalDateTo').value;
    
    // Update the hidden inputs
    document.getElementById('dateFrom').value = fromDate;
    document.getElementById('dateTo').value = toDate;
    
    // Trigger the table filtering
    table.draw();
    
    datePickerModal.style.display = 'none';
});

// Close modal when clicking outside
datePickerModal.addEventListener('click', function(e) {
    if (e.target === datePickerModal) {
        datePickerModal.style.display = 'none';
    }
});
// Make sure event handlers are properly bound
$(document).off('click', '.action-btn.status').on('click', '.action-btn.status', function(e) {
    e.stopPropagation();
    
    const $row = $(this).closest('tr');
    // Get ID from the hidden element in lead column
    const leadId = $row.find('.lead-id-hidden').text().trim();
    const leadName = $row.find('.lead-info h4').text().trim();
    const currentStatus = $row.find('.status-badge').text().trim();
    
    openStatusModal(leadId, leadName, currentStatus);
});

// Close modal handlers - use off/on to prevent duplicate bindings
$('#closeStatusModal, #cancelStatusUpdate').off('click').on('click', function(e) {
    e.preventDefault();
    closeStatusModal();
});

$('#statusModal').off('click').on('click', function(e) {
    if ($(e.target).hasClass('modal-overlay')) {
        closeStatusModal();
    }
});

// Prevent modal from closing when clicking inside
$('#statusModal .modal-container').off('click').on('click', function(e) {
    e.stopPropagation();
});

// Close modal with Escape key
$(document).off('keydown.statusModal').on('keydown.statusModal', function(e) {
    if (e.key === "Escape" && $('#statusModal').hasClass('active')) {
        closeStatusModal();
    }
});
        // Handle reassign button click in table rows
        $(document).off('click', '.action-btn.reassign').on('click', '.action-btn.reassign', function(e) {
            e.stopPropagation();

            const $row = $(this).closest('tr');
            // Always get ID from the hidden element in lead column
            const leadId = $row.find('.lead-id-hidden').text().trim();
            const leadName = $row.find('.lead-info h4').text().trim();

            openReassignModal(leadId, leadName);
        });
        // Close modal handlers
        $('#closeReassignModal, #cancelReassign').click(function(e) {
            e.preventDefault();
            closeReassignModal();
        });

        $('#reassignModal .modal-overlay').click(function(e) {
            if ($(e.target).hasClass('modal-overlay')) {
                closeReassignModal();
            }
        });

        // Prevent modal from closing when clicking inside
        $('#reassignModal .modal-container').click(function(e) {
            e.stopPropagation();
        });

        // Close modal with Escape key
        $(document).keydown(function(e) {
            if (e.key === "Escape" && $('#reassignModal').hasClass('active')) {
                closeReassignModal();
            }
        });

        // Form submission
        $('#reassignForm').submit(function(e) {
            e.preventDefault();

            // Validate required fields
            if (!$('#reassignTo').val()) {
                $('#reassignTo').closest('.form-group').addClass('error');
                $('#reassignTo').closest('.form-group').find('.error-message').text('Please select a team member');
                return false;
            }

            const formData = {
                leadId: $('#reassignLeadId').val(),
                leadName: $('#reassignLeadName').val(),
                assignedTo: $('#reassignTo').val(),
                notes: $('#reassignNotes').val(),
                reassignedBy: "Current User",
                reassignedAt: new Date().toISOString()
            };

            // Show loading state
            const $submitBtn = $('#reassignForm .submit-btn');
            $submitBtn.html('<i class="fas fa-spinner fa-spin"></i> Reassigning...').prop('disabled', true);

            // Simulate API call
            setTimeout(function() {
                console.log('Lead reassigned:', formData);

                // Update the table row using the hidden ID
                const leadId = formData.leadId;
                table.rows().every(function() {
                    const rowData = this.data();
                    if (rowData.id === leadId) {
                        rowData.assignedLead = formData.assignedTo;
                        this.data(rowData).draw(false);
                    }
                });

                // Reset form and close modal
                $submitBtn.html('Reassign Lead').prop('disabled', false);
                closeReassignModal();

                // Show success notification
                showNotification(`Lead ${leadId} reassigned to ${formData.assignedTo}`, 'success');
            }, 1500);
        });

        initDateRangeFilter();

        
        function initDateRangeFilter() {
        // Custom search function for date range filtering
        $.fn.dataTable.ext.search.push(
        function(settings, data, dataIndex) {
            const fromDate = $('#dateFrom').val();
            const toDate = $('#dateTo').val();

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
        }
    );

    // Event listeners for date inputs
    $('#dateFrom, #dateTo').on('change', function() {
        table.draw();
    });
}


        // Handle sort buttons
        $(document).on('click', '.sort-btn', function() {
            const sortDirection = $(this).data('sort');
            const columnIndex = parseInt($(this).data('column'));

            // Toggle sorting
            if (sortDirection === 'asc') {
                table.order([columnIndex, 'asc']).draw();
            } else {
                table.order([columnIndex, 'desc']).draw();
            }

            // Close the dropdown
            $(this).closest('.filter-dropdown').hide();
        });
    }

    $(document).on('click', '.filter-header', function(e) {
        // Only prevent sorting if clicking on the button or dropdown
        if ($(e.target).closest('.filter-header-btn, .filter-dropdown').length) {
            e.stopPropagation();
            e.preventDefault();
            return false;
        }
    });

     // Lead History Sidebar Functionality
document.body.addEventListener("click", (event) => {
    const target = event.target.closest(".unique-toggle-btn, .call-counter");
    
    if (target) {
        const rowId = target.getAttribute("data-id");
        const userUniqueId = target.getAttribute("data-userid");
        
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
document.getElementById("uniqueCloseSidebar")?.addEventListener("click", () => closeSidebar("uniqueLeadHistorySidebar"));
document.getElementById("uniqueCloseCallSidebar")?.addEventListener("click", () => closeSidebar("uniqueCallHistorySidebar"));

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


// You'll also need to add the fetchHistory function (this is a placeholder - implement according to your API)
function fetchHistory(leadId, userId) {
    // This should make an AJAX call to get the lead history data
    console.log("Fetching history for lead:", leadId, "user:", userId);
    
    // Example of how you might populate the sidebar:
    document.getElementById("lead_user_name").textContent = "John Doe"; // Replace with actual data
    document.getElementById("lead_user_number").textContent = "+1 234 567 890"; // Replace with actual data
    document.getElementById("assigned_date_leads").textContent = "June 15, 2023"; // Replace with actual data
    document.getElementById("assigned_by_user").textContent = "Admin User"; // Replace with actual data
    
    // Clear existing history
    const historyList = document.getElementById("followUpHistory");
    historyList.innerHTML = "";
    
    // Add sample history items (replace with actual data from your API)
    const sampleHistory = [
        { date: "June 20, 2023", time: "10:30 AM", status: "Contacted", notes: "Called customer, they showed interest in property" },
        { date: "June 18, 2023", time: "2:15 PM", status: "Follow Up", notes: "Scheduled a follow-up call for next week" },
        { date: "June 15, 2023", time: "9:00 AM", status: "New Lead", notes: "Lead created from website form" }
    ];
    
    sampleHistory.forEach(item => {
        const historyItem = document.createElement("li");
        historyItem.className = "unique-step";
        historyItem.innerHTML = `
            <div class="unique-dot"></div>
            <div class="unique-content">
                
                    <span class="unique-status-info">${item.status}</span>
                    <span class="unique-date-time">${item.date} at ${item.time}</span>
                
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
    
    // Initialize click listeners for the new history items
    initializeLeadHistoryClickListeners();
}

    function handleResponsiveBehavior() {
        const isMobile = window.innerWidth <= 900;

        // Show/hide columns based on screen size
        if (isMobile) {
            $('.mobile-hide').hide();
            $('.mobile-project').show();
        } else {
            $('.mobile-hide').show();
            $('.mobile-project').hide();
        }

        $('#leadsTable tbody tr').each(function() {
            const $row = $(this);
            const detailsContent = createDetailsContent($row);
            const leadId = $row.find('td:eq(1) .lead-id-section').text();
            let $detailsRow = $row.next('.details-row');

            $row.find('.expand-row-btn').show();

            if (detailsContent) {
                if ($detailsRow.length) {
                    $detailsRow.find('.details-content').html(detailsContent);
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
            } else if ($detailsRow.length) {
                $detailsRow.remove();
            }
        });
    }

    function createDetailsContent(row) {
        const hiddenColumns = [];
        const headers = $('#leadsTable thead th');
        const excludedColumns = [0, 1, 2, 3]; // Exclude checkbox, lead info, ID, and expand button columns

        // For mobile view, use DataTable row data directly
        if (window.innerWidth <= 900) {
            let rowData;
            if (typeof table !== 'undefined' && row instanceof jQuery && row.length) {
                rowData = table.row(row).data();
            }

            if (rowData) {
                const leadName = rowData.name || 'N/A';
                const email = rowData.email || 'N/A';
                const project = rowData.assignedProject || 'N/A';
                const source = rowData.source ?
                    rowData.source.replace('.svg', '').replace('-logo', '').replace(/-/g, ' ') :
                    'N/A';
                const location = rowData.location || 'N/A';
                const assignedLead = rowData.assignedLead || 'N/A';
                const created = rowData.created || 'N/A';
                return `
                <div class="details-block details-block-left">
                    <div class="mobile-details-section">
                        <h4>Lead Details</h4>
                        <div class="flexxx">
                            <strong>Name: &nbsp;</strong>
                            <div class="text-toggle" data-type="name">${leadName}</div>
                        </div>
                        <div class="flexxx">
                            <strong>Email: &nbsp;</strong>
                            <div class="text-toggle" data-type="email">${email}</div>
                        </div>
                        <div class="flexxx">
                            <strong>Project:&nbsp;</strong>
                            <div class="text-toggle" data-type="project">${project}</div>
                        </div>
                        <div class="flexxx">
                            <strong>Assigned Lead:&nbsp;</strong>
                            <div class="text-toggle" data-type="assigned lead">${assignedLead || 'N/A'}</div>
                        </div>
                        <div class="flexxx">
                            <strong>Location:&nbsp;</strong>
                            <div>${location || 'N/A'}</div>
                        </div>
                        <div class="flexxx">
                            <strong>Created At:&nbsp;</strong>
                            <div class="text-toggle" data-type="created">${created}</div>
                        </div>
                    </div>
                </div>
                <div class="details-block details-block-right">
                    <h4>Actions</h4>
                    <div class="action-buttons-leads mobile">
                        <button class="action-btn phone tooltip" data-tooltip="Call"><i class="fas fa-phone"></i></button>
                        <button class="action-btn whatsapp tooltip" data-tooltip="WhatsApp"><i class="fab fa-whatsapp"></i></button>
                        <button class="action-btn status tooltip" data-tooltip="Update Status"><i class="fas fa-refresh"></i></button>
                        <button class="action-btn history tooltip" data-tooltip="History"><i class="fas fa-history"></i></button>
                        <button class="action-btn reassign tooltip" data-tooltip="Reassign"><i class="fas fa-user-friends"></i></button>
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
            if (cell.css('display') === 'none') {
                const headerText = header.find('.filter-header-btn').text().trim() || header.text().trim();
                if (headerText) {
                    hiddenColumns.push({
                        header: headerText,
                        content: cell.html()
                    });
                }
            }
        }
        if (hiddenColumns.length > 0) {
            let detailsHTML = '';
            hiddenColumns.forEach(column => {
                detailsHTML += `
                <div class="details-block">
                    <strong>${column.header}:</strong>
                    <div>${column.content}</div>
                </div>
            `;
            });
            return detailsHTML;
        }
        return '';
    }

    $(document).on('click', '.text-toggle', function(e) {
        e.stopPropagation(); // Prevent the click from bubbling up

        const $this = $(this);
        const type = $this.data('type');

        // Toggle text visibility
        $this.toggleClass('full-text');
        // If it's the first time expanding, change the cursor style
        if (!$this.hasClass('full-text')) {
            $this.css('cursor', 'pointer');
        }
    });

    $(document).on('click', function(e) {
        // If the click is not on a text-toggle element and not inside a modal
        if (!$(e.target).closest('.text-toggle').length &&
            !$(e.target).closest('.modal-container').length) {

            // Hide all expanded text
            $('.text-toggle.full-text').removeClass('full-text');
        }
    });
    $(document).ready(function() {
        // Function to open modal
        function openAddLeadModal() {
            $('body').css('overflow', 'hidden');
            $('#addLeadModal').addClass('active').show();
        }

        // Function to close modal
        function closeAddLeadModal() {
            $('body').css('overflow', 'auto');
            $('#addLeadModal').removeClass('active').hide();
        }

        // Open modal when clicking any + button (floating, mobile, etc.)
        $(document).on('click', '.action-button:has(.fa-plus), .add-btn-mobile', function(e) {
            e.preventDefault();
            openAddLeadModal();
        });

        // Close modal when clicking close button, cancel button, or overlay
        $('#closeAddLeadModal, #cancelAddLead').click(function(e) {
            e.preventDefault();
            closeAddLeadModal();
        });

        $('.modal-overlay').click(function(e) {
            if ($(e.target).hasClass('modal-overlay')) {
                closeAddLeadModal();
            }
        });

        // Prevent modal from closing when clicking inside the modal container
        $('.modal-container').click(function(e) {
            e.stopPropagation();
        });

        // Close modal when pressing Escape key
        $(document).keydown(function(e) {
            if (e.key === "Escape" && $('#addLeadModal').hasClass('active')) {
                closeAddLeadModal();
            }
        });

        // Form submission handling
        $('#addLeadForm').submit(function(e) {
            e.preventDefault();

            // Reset error states
            $('.form-group').removeClass('error');
            $('.error-message').text('');

            let isValid = true;

            // Validate required fields
            $('#addLeadForm [required]').each(function() {
                if (!$(this).val().trim()) {
                    const $formGroup = $(this).closest('.form-group');
                    $formGroup.addClass('error');
                    $formGroup.find('.error-message').text('This field is required');
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
            const phone = $('#leadPhone').val().trim();
            if (!/^\d{10}$/.test(phone)) {
                $('#leadPhone').closest('.form-group').addClass('error');
                $('#leadPhone').closest('.form-group').find('.error-message').text('Please enter a valid 10-digit phone number');
                $('#leadPhone').focus();
                return false;
            }

            // Validate email if provided
            const email = $('#leadEmail').val().trim();
            if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                $('#leadEmail').closest('.form-group').addClass('error');
                $('#leadEmail').closest('.form-group').find('.error-message').text('Please enter a valid email address');
                $('#leadEmail').focus();
                return false;
            }

            // Form is valid - process submission
            const formData = {
                name: $('#leadName').val().trim(),
                phone: '+91' + phone,
                email: email || null,
                project: $('#leadProject').val(),
                source: $('#leadSource').val(),
                status: 'New',
                created: new Date().toISOString()
            };

            // Show loading state
            const $submitBtn = $('.submit-btn');
            $submitBtn.html('<i class="fas fa-spinner fa-spin"></i> Adding...').prop('disabled', true);

            // Simulate API call
            setTimeout(function() {
                // In a real app, you would make an AJAX call here
                console.log('Form submitted:', formData);

                // Add to DataTable (simulated)
                if (typeof table !== 'undefined') {
                    // Instead of adding directly, trigger a redraw to fetch new data from server
                    table.ajax.reload(null, false); // Reload data without resetting pagination
                }

                // Reset form and close modal
                $('#addLeadForm')[0].reset();
                $submitBtn.html('Add Lead').prop('disabled', false);
                closeAddLeadModal();

                // Show success notification
                showNotification('Lead added successfully!', 'success');

            }, 1500);
        });

        // Phone number input validation
        $('#leadPhone').on('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '').substring(0, 10);
        });

        // Helper function to show notifications
        function showNotification(message, type) {
            const $notification = $('<div class="notification ' + type + '">' + message + '</div>');
            $('body').append($notification);

            setTimeout(function() {
                $notification.addClass('show');
            }, 10);

            setTimeout(function() {
                $notification.removeClass('show');
                setTimeout(function() {
                    $notification.remove();
                }, 300);
            }, 3000);
        }
    });

    // Delete Leads Modal Functionality
    function openDeleteModal(selectedIds) {
        $('body').css('overflow', 'hidden');
        $('#deleteModal').addClass('active').show();

        // Update the message based on number of selected leads
        const count = selectedIds.length;
        let message = `Are you sure you want to delete ${count} selected lead${count > 1 ? 's' : ''}? This action cannot be undone.`;
        $('#deleteMessage').text(message);

        // Show list of selected leads if less than 10
        if (count > 0 && count <= 10) {
            $('#selectedLeadsList').show();
            const $list = $('#leadsToDelete').empty();

            // Find and list each selected lead
            selectedIds.forEach(id => {
                const leadName = $(`tr:contains(${id})`).find('.lead-info h4').text().trim();
                $list.append(`<li><i class="fas fa-user" style="margin-right: 8px;"></i> ${leadName} (${id})</li>`);
            });
        } else {
            $('#selectedLeadsList').hide();
        }
    }

    function closeDeleteModal() {
        $('body').css('overflow', 'auto');
        $('#deleteModal').removeClass('active').hide();
    }

    // Handle delete button clicks
    $(document).on('click', '.delete-selected-btn, .delete-btn-mobile', function(e) {
        e.preventDefault();

        const selectedIds = getSelectedLeadIds();
        if (selectedIds.length === 0) {
            showNotification('Please select at least one lead to delete', 'error');
            return;
        }

        openDeleteModal(selectedIds);
    });

    // Close modal handlers
    $('#closeDeleteModal, #cancelDelete').click(function(e) {
        e.preventDefault();
        closeDeleteModal();
    });

    $('#deleteModal .modal-overlay').click(function(e) {
        if ($(e.target).hasClass('modal-overlay')) {
            closeDeleteModal();
        }
    });

    // Prevent modal from closing when clicking inside
    $('#deleteModal .modal-container').click(function(e) {
        e.stopPropagation();
    });

    // Close modal with Escape key
    $(document).keydown(function(e) {
        if (e.key === "Escape" && $('#deleteModal').hasClass('active')) {
            closeDeleteModal();
        }
    });

    // Form submission
    $('#deleteForm').submit(function(e) {
        e.preventDefault();

        const selectedIds = getSelectedLeadIds();
        if (selectedIds.length === 0) {
            showNotification('No leads selected for deletion', 'error');
            closeDeleteModal();
            return;
        }

        // Show loading state
        const $submitBtn = $('#deleteForm .delete-btn');
        $submitBtn.html('<i class="fas fa-spinner fa-spin"></i> Deleting...').prop('disabled', true);

        // Simulate API call (replace with actual AJAX call to delete leads)
        setTimeout(function() {
            console.log('Deleting leads:', selectedIds);

            // In a real app, you would make an API call here and then:
            // 1. Remove the rows from the DataTable
            // 2. Update the table display

            // For this demo, we'll just remove the selected rows
            selectedIds.forEach(id => {
                // Find the row in DataTable by ID and remove it
                table.rows(function(idx, data, node) {
                    return data.id === id;
                }).remove();
            });
            table.draw();

            // Reset checkboxes and selection actions
            $('#selectAll').prop('checked', false);
            $('.row-checkbox').prop('checked', false);
            updateSelectionActions();

            // Close modal and show notification
            $submitBtn.html('Delete Leads').prop('disabled', false);
            closeDeleteModal();
            showNotification(`${selectedIds.length} lead${selectedIds.length > 1 ? 's' : ''} deleted successfully`, 'success');
        }, 1500);
    });

    // Updated expand button click handler
    $(document).on('click', '.expand-row-btn', function(e) {
        e.stopPropagation();
        e.preventDefault();

        const $btn = $(this);
        const $icon = $btn.find('i');
        const $row = $btn.closest('tr');
        const $detailsRow = $row.next('.details-row');

        // Close any previously expanded row
        if (currentExpandedRow && currentExpandedRow !== $row[0]) {
            const $prevRow = $(currentExpandedRow);
            const $prevDetails = $prevRow.next('.details-row');
            const $prevIcon = $prevRow.find('.expand-row-btn i');
            $prevDetails.hide();
            $prevIcon.removeClass('rotated');
        }

        // Toggle current row
        if ($detailsRow.is(':visible')) {
            $detailsRow.hide();
            $icon.removeClass('rotated');
        } else {
            $detailsRow.show();
            $icon.addClass('rotated');
        }

        // Update current expanded row reference
        currentExpandedRow = $detailsRow.is(':visible') ? $row[0] : null;
    });



    $(document).on('click', '.filter-header', function(e) {
        // Only prevent sorting if clicking on the button or dropdown
        if ($(e.target).closest('.filter-header-btn, .filter-dropdown').length) {
            e.stopPropagation();
            e.preventDefault();
            return false;
        }
    });
    // Updated row click handler with proper previous row handling
    $(document).on('click', '#leadsTable tbody tr', function(e) {
        // Skip if clicking on action buttons, dropdowns, checkboxes, or expand button
        if ($(e.target).closest('.action-btn, .filter-dropdown, .filter-header-btn, a, input[type="checkbox"], .expand-row-btn').length) {
            return;
        }

        // Skip if clicking on details rows
        if ($(this).hasClass('details-row')) {
            return;
        }

        const $currentRow = $(this);
        const $currentPhoneInfo = $currentRow.find('.phone-info');
        const $currentDetailsRow = $currentRow.next('.details-row');

        // Handle previous row cleanup
        if (currentPhoneRow && currentPhoneRow !== $currentRow[0]) {
            const $prevRow = $(currentPhoneRow);
            const $prevPhone = $prevRow.find('.phone-info');
            const $prevDetailsRow = $prevRow.next('.details-row');

            // Encrypt previous row's phone number if it's decrypted
            if ($prevPhone.length && !$prevPhone.hasClass('encrypted')) {
                const realPhone = $prevPhone.data('real-phone');
                const encryptedPhone = encryptPhone(realPhone);
                $prevPhone.text(encryptedPhone).addClass('encrypted');
            }

            // Close previous row's details if it's open
            if ($prevDetailsRow.length && $prevDetailsRow.is(':visible')) {
                $prevDetailsRow.hide();
                $prevRow.find('.expand-row-btn i').removeClass('fa-minus').addClass('fa-plus');
            }
        }

        // Handle current row phone number toggle
        if ($currentPhoneInfo.length) {
            if ($currentPhoneInfo.hasClass('encrypted')) {
                // Decrypt current row's phone
                const realPhone = $currentPhoneInfo.data('real-phone');
                $currentPhoneInfo.text(realPhone).removeClass('encrypted');
            } else {
                // Encrypt current row's phone
                const realPhone = $currentPhoneInfo.data('real-phone');
                const encryptedPhone = encryptPhone(realPhone);
                $currentPhoneInfo.text(encryptedPhone).addClass('encrypted');
            }
        }

        // In the row click handler, update the icon rotation handling:
        if ($currentDetailsRow.length) {
            const $expandBtn = $currentRow.find('.expand-row-btn');
            const $icon = $expandBtn.find('i');

            if ($currentDetailsRow.is(':visible')) {
                // Close current details
                $currentDetailsRow.hide();
                $icon.removeClass('rotated');
                currentExpandedRow = null;
            } else {
                // Open current details
                $currentDetailsRow.show();
                $icon.addClass('rotated');
                currentExpandedRow = $currentRow[0];
            }
        }

        // Update tracking variables
        currentPhoneRow = $currentRow[0];
    });

    // Handle table redraw events
    table.on('draw', function() {
        $('#leadsTable tbody tr').each(function() {
            const $row = $(this);
            const $detailsRow = $row.next('.details-row');
            const $icon = $row.find('.expand-row-btn i');

            // Reset expand button icons
            $icon.removeClass('rotated');
            if ($detailsRow.length && $detailsRow.is(':visible')) {
                $icon.addClass('rotated');
            }

            // Ensure phone numbers are encrypted after redraw
            $row.find('.phone-info').each(function() {
                const $phoneInfo = $(this);
                if (!$phoneInfo.hasClass('encrypted')) {
                    const realPhone = $phoneInfo.data('real-phone');
                    const encryptedPhone = encryptPhone(realPhone);
                    $phoneInfo.text(encryptedPhone).addClass('encrypted');
                }
            });

            // Handle mobile view for lead source
            if (window.innerWidth <= 900) {
                $row.find('.mobile-source').show();
            } else {
                $row.find('.mobile-source').hide();
            }
        });

        // Reset tracking variables
        currentExpandedRow = null;
        currentPhoneRow = null;
        handleResponsiveBehavior();
    });

    // Handle window resize
    let resizeTimer;
    $(window).on('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            handleResponsiveBehavior();
        }, 250);
    });

    // Track current active filter
    var currentActiveFilter = 'my';

    // Function to handle filter button clicks
    function handleFilterButtonClick(button) {
        const $button = $(button);
        const filterValue = $button.text().trim().split(' ')[0].toLowerCase(); // Get the first word as filter value

        // Map button text to filter values expected by update_status.php
        const filterMap = {
            'my': 'myLeads',
            'booked': 'bookedLeads',
            'today': 'today_collection',
            'dropped': 'droppedLeads',
            'ads': 'paidAds',
            'nfs-d': 'NFS_D',
            'active': 'activeLeads',
            'new': 'freshLeads',
            'pending': 'pendingLeads',
            'follow': 'followLeads',
            'untouched': 'pendingLeads' // Assuming untouched leads are 'Pending' status
        };

        const actualFilterValue = filterMap[filterValue] || ''; // Default to empty if not found

        // If clicking the already active button, deselect it and show all
        if ($button.hasClass('active')) {
            $button.removeClass('active');
            currentActiveFilter = ''; // Clear filter
        } else {
            // Remove active class from all buttons in both rows
            $('.tab-row .filter-btn, .filter-row .filter-btn').removeClass('active');
            // Add active class to clicked button
            $button.addClass('active');
            currentActiveFilter = actualFilterValue;
        }

        // Redraw the table to apply the new filter via AJAX
        table.draw();
    }

    // Click handler for filter buttons
    $(document).on('click', '.tab-row .filter-btn, .filter-row .filter-btn', function(e) {
        e.preventDefault();
        handleFilterButtonClick(this);
    });

    // Initialize with "My Leads" as default active
    function initializeFilters() {
        // Set "My Leads" as default active
        $('.tab-row .filter-btn').first().addClass('active');
        currentActiveFilter = 'myLeads'; // Set initial filter for server-side
        table.draw(); // Initial draw to fetch data
    }

    // Call initialization after table is ready
    initializeFilters();

    // Optional: Add visual feedback for button interactions
    $(document).on('mouseenter', '.filter-btn', function() {
        if (!$(this).hasClass('active')) {
            $(this).addClass('hover');
        }
    });

    $(document).on('mouseleave', '.filter-btn', function() {
        $(this).removeClass('hover');
    });

    // Handle mobile filter button
    $(document).on('click', '.filter-btn-mobile', function() {
        // Toggle mobile filter panel or show filter options
        $('.filter-row').toggleClass('mobile-visible');
    });
});
$(document).on('click', '.filter-btn-mobile, .dt-button.filter-btn', function() {
    $('#filterModal').show();
    $('body').css('overflow', 'hidden');

    // Initialize Select2 after modal is shown
    $('.select-input').select2({
        placeholder: "Select options",
        allowClear: true,
        width: '100%',
        dropdownParent: $('#filterModal') // Important for proper positioning
    });
});

$(document).ready(function() {

    // Initialize Select2 for multi-select dropdowns
    $('.select-input').select2({
        placeholder: "Select options",
        allowClear: true,
        width: '100%'
    });

    // Open filter modal
    $(document).on('click', '.filter-btn-mobile, .dt-button.filter-btn', function() {
        $('#filterModal').show();
        $('body').css('overflow', 'hidden');
    });



    // Close filter modal
    $('#closeFilterModal').click(function() {
        $('#filterModal').hide();
        $('body').css('overflow', 'auto');
    });

    // Click outside modal to close
    $('.modal-overlay').click(function(e) {
        if ($(e.target).hasClass('modal-overlay')) {
            $('#filterModal').hide();
            $('body').css('overflow', 'auto');
        }
    });

    // Toggle filter option buttons
    $(document).on('click', '.filter-option-btn', function() {
        $(this).toggleClass('active');
    });

    // Reset all filters
    $('#resetFilters').click(function() {
        $('.filter-option-btn').removeClass('active');
        $('.select-input').val(null).trigger('change');
        $('#startDate, #endDate').val('');

        // Clear all table filters and redraw
        table.search('').columns().search('').draw();
    });


    // Apply filters
    $('#filterForm').submit(function(e) {
        e.preventDefault();

        // Trigger a redraw of the DataTable, which will send the current filter values to the server
        table.draw();

        // Close modal
        $('#filterModal').hide();
        $('body').css('overflow', 'auto');
    });

    // Close modal with Escape key
    $(document).keydown(function(e) {
        if (e.key === "Escape" && $('#filterModal').is(':visible')) {
            $('#filterModal').hide();
            $('body').css('overflow', 'auto');
        }
    });
});


// Function to open assign modal
function openAssignModal() {
    const selectedIds = getSelectedLeadIds(); // Use the global function
    if (selectedIds.length === 0) {
        showNotification('Please select at least one lead to assign.', 'error');
        return;
    }

    // Update the modal with selected leads info
    $('#selectedLeadsCount').text(selectedIds.length);

    // Clear and populate the selected leads list
    const $selectedLeadsList = $('#selectedLeadsList');
    $selectedLeadsList.empty();

    // Get the names of selected leads
    $('.row-checkbox:checked').each(function() {
        const $row = $(this).closest('tr');
        const leadId = $row.find('.lead-id-hidden').text();
        const leadName = $row.find('.lead-info h4').text();

        $selectedLeadsList.append(`
            <div class="selected-lead-item">
                <span class="lead-id">${leadId}</span>
                <span class="lead-name">${leadName}</span>
            </div>
        `);
    });

    // Open the modal
    $('body').css('overflow', 'hidden');
    $('#assignModal').addClass('active').show();

    // Initialize Select2
    $('#assignTo').select2({
        placeholder: "Select Team Member",
        allowClear: false,
        width: '100%',
        dropdownParent: $('#assignModal')
    });
}
// Function to close assign modal
function closeAssignModal() {
    $('body').css('overflow', 'auto');
    $('#assignModal').removeClass('active').hide();
}

// Handle assign button click
$(document).on('click', '.assign-users-btn, .assign-btn-mobile', function(e) {
    e.preventDefault();
    openAssignModal();
});

// Close modal when clicking close button, cancel button, or overlay
$('#closeAssignModal, #cancelAssign').click(function(e) {
    e.preventDefault();
    closeAssignModal();
});

$('#assignModal').click(function(e) {
    if ($(e.target).hasClass('modal-overlay')) {
        closeAssignModal();
    }
});

// Prevent modal from closing when clicking inside the modal container
$('#assignModal .modal-container').click(function(e) {
    e.stopPropagation();
});

// Close modal when pressing Escape key
$(document).keydown(function(e) {
    if (e.key === "Escape" && $('#assignModal').hasClass('active')) {
        closeAssignModal();
    }
});

function getSelectedLeadIds() {
    const selectedIds = [];
    $('.row-checkbox:checked').each(function() {
        const $row = $(this).closest('tr');

        // First try to get ID from the hidden element in lead column
        let leadId = $row.find('.lead-id-hidden').text().trim();

        // If not found, try to get from the ID column (if visible)
        if (!leadId) {
            leadId = $row.find('.lead-id-section').text().replace('#', '').trim();
        }

        if (leadId) {
            selectedIds.push(leadId);
        }
    });
    return selectedIds;
}

// Form submission handling
$('#assignForm').submit(function(e) {
    e.preventDefault();

    // Reset error states
    $('.form-group').removeClass('error');
    $('.error-message').text('');

    // Validate required fields
    if (!$('#assignTo').val()) {
        $('#assignTo').closest('.form-group').addClass('error');
        $('#assignTo').closest('.form-group').find('.error-message').text('Please select a team member');
        $('#assignTo').focus();
        return false;
    }

    const selectedIds = getSelectedLeadIds();
    const assignUser = $('#assignTo').val(); // Renamed to match PHP
    const projectName = $('#assignProjectName').val(); // Assuming you add this input in your modal
    const assignNote = $('#assignNote').val(); // This can be sent as part of notes if needed

    // Show loading state
    const $submitBtn = $('.submit-btn');
    $submitBtn.html('<i class="fas fa-spinner fa-spin"></i> Assigning...').prop('disabled', true);

    // Send data to update_status.php for bulk assignment
    $.ajax({
        url: 'update_status.php',
        type: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            bulkAssign: true,
            rowIds: selectedIds,
            assignUser: assignUser,
            projectName: projectName,
            notes: assignNote // You might want to handle notes in PHP
        }),
        success: function(response) {
            if (response.status === 'success') {
                showNotification(`${selectedIds.length} leads assigned successfully!`, 'success');
                // Trigger a redraw of the DataTable to reflect changes from the server
                table.ajax.reload(null, false); // Reload data without resetting pagination
            } else {
                showNotification(`Error assigning leads: ${response.message}`, 'error');
            }
        },
        error: function(xhr, status, error) {
            console.error("AJAX Error:", status, error, xhr.responseText);
            showNotification('An error occurred during assignment. Please try again.', 'error');
        },
        complete: function() {
            // Reset form and close modal
            $('#assignForm')[0].reset();
            $submitBtn.html('Assign Leads').prop('disabled', false);
            closeAssignModal();

            // Uncheck all checkboxes
            $('.row-checkbox').prop('checked', false);
            $('#selectAll').prop('checked', false);
            updateSelectionActions();
        }
    });
});
