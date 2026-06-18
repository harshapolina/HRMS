document.addEventListener("DOMContentLoaded", function () {
  const convertedCheckbox = document.getElementById("toggleFields1");
  const additionalFields = document.getElementById("additional-fields1");
  const updateButton = document.getElementById("edit-eoi-btn");
  const requiredInputs = additionalFields.querySelectorAll("input[required]");
  let currentExpandedRow = null;
  let eoiTable;
  let allUniqueValues = {};
  let currentSelectedUser = "all"; // Default to aggregated EOI view
  let hierarchyData = []; // Store hierarchy data

  // Normalize any date string to YYYY-MM; returns empty string on invalid input
  const toYearMonth = (dateStr) => {
    if (!dateStr) return "";
    // Accept pre-formatted YYYY-MM without touching it
    const trimmed = String(dateStr).trim();
    if (/^\d{4}-\d{2}$/.test(trimmed)) return trimmed;

    const parsed = new Date(trimmed);
    if (isNaN(parsed)) return "";
    const year = parsed.getFullYear();
    const month = String(parsed.getMonth() + 1).padStart(2, "0");
    return `${year}-${month}`;
  };

  const syncBookingMonth = (dateSelector, monthSelector) => {
    const $date = $(dateSelector);
    const $month = $(monthSelector);
    if (!$date.length || !$month.length) return;
    const normalized = toYearMonth($date.val());
    if (normalized) {
      $month.val(normalized);
    }
  };

  // Expose for inline oninput handler on add form booking date
  window.updateBookingMonth = function () {
    syncBookingMonth("#bdateo", "#bmontho");
  };

  // Function to read URL parameters and apply date filters
  function applyUrlDateFilters() {
    const urlParams = new URLSearchParams(window.location.search);
    const startDate = urlParams.get('start_date');
    const endDate = urlParams.get('end_date');
    const filterUser = urlParams.get('filterUser');
    const managerView = urlParams.get('managerView');

    // Handle user filtering first if specified
    if (filterUser && managerView === 'true') {
      console.log('Applying user filter from URL:', filterUser);
      // Set the selected user and switch to them
      switchToUserFromUrl(filterUser);
    }

    if (startDate || endDate) {
      console.log('Applying URL date filters:', { startDate, endDate });

      // Set the date filter inputs
      if (startDate) {
        const startDateInput = document.getElementById('isolatedFilterStartDate1');
        if (startDateInput) {
          startDateInput.value = startDate;
        }
      }

      if (endDate) {
        const endDateInput = document.getElementById('isolatedFilterEndDate1');
        if (endDateInput) {
          endDateInput.value = endDate;
        }
      }

      // Apply the filters once the table is loaded
      setTimeout(() => {
        if (typeof eoiTable !== 'undefined' && eoiTable) {
          applyDateFiltersToTable(startDate, endDate);
        }
      }, 1000);
    }

    // Show clear filter button if any filters are applied from dashboard
    if ((startDate || endDate) || (filterUser && managerView === 'true')) {
      showDashboardClearFilterButton();
    }
  }

  // Function to apply date filters to the DataTable
  function applyDateFiltersToTable(startDate, endDate) {
    console.log('Applying date filters to table:', { startDate, endDate });

    // Clear any existing filters first
    if ($.fn.dataTable.ext.search.length > 0) {
      $.fn.dataTable.ext.search.pop();
    }

    // Create a custom filter function for dates
    $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
      const rowData = eoiTable.row(dataIndex).data();

      // Date range filter
      if (startDate || endDate) {
        const rowDate = new Date(rowData.bookingDate);
        const fromDate = startDate ? new Date(startDate) : null;
        const toDate = endDate ? new Date(endDate) : null;

        if (fromDate && rowDate < fromDate) return false;
        if (toDate && rowDate > toDate) return false;
      }

      return true;
    });

    // Apply the filters
    eoiTable.draw();

    // Show a notification that filters were applied
    showNotification('Date filters applied from dashboard', 'success');
  }

  // Apply URL filters when page loads
  applyUrlDateFilters();

  function getEoiDataUrl() {
    const userParam = currentSelectedUser ? `?selected_user=${encodeURIComponent(currentSelectedUser)}` : '';
    return `fetch_eoi_data.php${userParam}`;
  }

  // User Hierarchy Functions
  function loadUserHierarchy() {
    const loadingElement = document.getElementById("headerHierarchyLoading");
    const containerElement = document.getElementById("eoiHierarchyContainer");

    if (loadingElement) loadingElement.style.display = "flex";

    fetch("fetch_eoi_data.php?get_hierarchy=1")
      .then(response => response.json())
      .then(data => {
        if (data.error) {
          console.error("Error loading hierarchy:", data.error);
          return;
        }

        hierarchyData = data;

        if (!currentSelectedUser) {
          const allEntry = hierarchyData.find(user => user.user === "all");
          const currentUser = hierarchyData.find(user => user.is_current_user);
          currentSelectedUser = allEntry ? "all" : (currentUser ? currentUser.user : null);
        }

        renderHierarchyButtons();
      })
      .catch(error => {
        console.error("Error loading user hierarchy:", error);
        showNotification("Error loading user hierarchy", "error");
      })
      .finally(() => {
        if (loadingElement) loadingElement.style.display = "none";
      });
  }

  function renderHierarchyButtons() {
    const container = document.getElementById("eoiHierarchyContainer");
    if (!container) return;

    // Clear existing buttons except loading indicator
    const loadingElement = container.querySelector("#headerHierarchyLoading");
    container.innerHTML = "";
    if (loadingElement) {
      container.appendChild(loadingElement);
    }

    hierarchyData.forEach(user => {
      const button = document.createElement("button");
      button.className = "filter-btn accessbtn hierarchy-user-btn";
      button.dataset.user = user.user;

      const shouldBeActive = currentSelectedUser ? currentSelectedUser === user.user : user.is_current_user;
      if (shouldBeActive) {
        button.classList.add("active");
      }

      const iconClass = user.user === "all" ? "fa-layer-group" : (user.is_current_user ? 'fa-user' : 'fa-users');
      button.innerHTML = `
        <i class="fas ${iconClass}"></i>
        ${user.name}
        <span class="count">${user.count}</span>
      `;

      button.addEventListener("click", function (event) {
        event.preventDefault();
        event.stopPropagation();
        switchToUser(user.user, this);
      });

      container.appendChild(button);
    });
  }

  // Function to switch to user from URL parameter
  function switchToUserFromUrl(userId) {
    // Wait for hierarchy data to be loaded
    const checkHierarchy = () => {
      if (hierarchyData && hierarchyData.length > 0) {
        // Find the user in hierarchy data
        const user = hierarchyData.find(u => u.user === userId);
        if (user) {
          // Find the corresponding button
          const button = document.querySelector(`#eoiHierarchyContainer button[data-user="${userId}"]`);
          if (button) {
            // Use existing switchToUser function
            switchToUser(userId, button);
          } else {
            // If button not found, directly reload table data
            currentSelectedUser = userId;
            if (eoiTable) {
              eoiTable.ajax.url("fetch_eoi_data.php?selected_user=" + encodeURIComponent(userId));
              eoiTable.ajax.reload(() => {
                showNotification("Loaded EOI data for " + user.name, "success");
              });
            }
          }
        } else {
          console.warn("User not found in hierarchy:", userId);
        }
      } else {
        // Retry after a short delay if hierarchy not loaded yet
        setTimeout(checkHierarchy, 100);
      }
    };

    checkHierarchy();
  }

  function switchToUser(userId, buttonElement) {
    // Update active button - look for buttons in the header container
    document.querySelectorAll("#eoiHierarchyContainer .hierarchy-user-btn").forEach(btn => {
      btn.classList.remove("active");
    });
    buttonElement.classList.add("active");

    // Update current selected user
    currentSelectedUser = userId;

    // Show loading indicator
    document.querySelector(".loader-container").style.display = "flex";

    // Reload table data for selected user
    if (eoiTable) {
      eoiTable.ajax.url("fetch_eoi_data.php?selected_user=" + encodeURIComponent(userId));
      eoiTable.ajax.reload(function () {
        document.querySelector(".loader-container").style.display = "none";
        showNotification("Switched to " + (hierarchyData.find(u => u.user === userId)?.name || "user"), "success");
      });
    }
  }

  function refreshHierarchyCounts() {
    // Refresh the hierarchy data to update counts
    fetch("fetch_eoi_data.php?get_hierarchy=1")
      .then(response => response.json())
      .then(data => {
        if (!data.error) {
          hierarchyData = data;
          // Update count displays without changing active state
          hierarchyData.forEach(user => {
            const button = document.querySelector(`#eoiHierarchyContainer .hierarchy-user-btn[data-user="${user.user}"]`);
            if (button) {
              const countSpan = button.querySelector('.count');
              if (countSpan) {
                countSpan.textContent = user.count;
              }
            }
          });
        }
      })
      .catch(error => console.error("Error refreshing hierarchy:", error));
  }

  function showNotification(message, type) {
    const $notification = $(
      '<div class="notification ' + type + '">' + message + "</div>"
    );
    $("body").append($notification);

    setTimeout(function () {
      $notification.addClass("show");
    }, 10);

    setTimeout(function () {
      $notification.removeClass("show");
      setTimeout(function () {
        $notification.remove();
      }, 300);
    }, 3000);
  }

  // Function to show dashboard clear filter button
  function showDashboardClearFilterButton() {
    // Check if button already exists
    if (document.getElementById('dashboardClearFilterBtn')) {
      return;
    }

    // Create the floating clear filter button
    const clearBtn = document.createElement('div');
    clearBtn.id = 'dashboardClearFilterBtn';
    clearBtn.className = 'dashboard-clear-filter-btn';
    clearBtn.innerHTML = `
      <button class="clear-filter-btn" title="Clear Dashboard Filters">
        <i class="fas fa-times"></i>
        <span>Clear Filters</span>
      </button>
    `;

    // Add click handler
    clearBtn.addEventListener('click', clearDashboardFilters);

    // Add to body
    document.body.appendChild(clearBtn);

    // Add CSS styles
    addClearFilterButtonStyles();
  }

  // Function to clear dashboard filters
  function clearDashboardFilters() {
    // Clear date filter inputs
    const startDateInput = document.getElementById('isolatedFilterStartDate1');
    const endDateInput = document.getElementById('isolatedFilterEndDate1');

    if (startDateInput) startDateInput.value = '';
    if (endDateInput) endDateInput.value = '';

    // Clear DataTable filters
    if ($.fn.dataTable.ext.search.length > 0) {
      $.fn.dataTable.ext.search.splice(0, $.fn.dataTable.ext.search.length);
    }

    // Switch back to All EOI by default after clearing filters
    let switched = false;
    if (hierarchyData.length > 0) {
      const allBtn = document.querySelector('#eoiHierarchyContainer button[data-user="all"]');
      if (allBtn) {
        switched = true;
        switchToUser('all', allBtn);
      } else {
        const currentUser = hierarchyData.find(u => u.is_current_user);
        if (currentUser) {
          const currentUserBtn = document.querySelector(`#eoiHierarchyContainer button[data-user="${currentUser.user}"]`);
          if (currentUserBtn) {
            switched = true;
            switchToUser(currentUser.user, currentUserBtn);
          }
        }
      }
    }

    // Reload table data without filters if we did not already switch and reload
    if (eoiTable && !switched) {
      eoiTable.ajax.url(getEoiDataUrl());
      eoiTable.ajax.reload();
    }

    showNotification('All dashboard filters cleared', 'success');

    // Remove the clear filter button
    const clearBtn = document.getElementById('dashboardClearFilterBtn');
    if (clearBtn) {
      clearBtn.remove();
    }

    // Update URL to remove filter parameters
    const url = new URL(window.location);
    url.searchParams.delete('start_date');
    url.searchParams.delete('end_date');
    url.searchParams.delete('filterUser');
    url.searchParams.delete('managerView');
    window.history.replaceState({}, document.title, url.pathname);
  }

  // Function to add CSS styles for the clear filter button
  function addClearFilterButtonStyles() {
    if (document.getElementById('dashboardClearFilterStyles')) {
      return;
    }

    const styles = document.createElement('style');
    styles.id = 'dashboardClearFilterStyles';
    styles.textContent = `
      .dashboard-clear-filter-btn {
        position: fixed;
        bottom: 100px;
        right: 20px;
        z-index: 1000;
        animation: slideInRight 0.3s ease-out;
      }

      .clear-filter-btn {
        background: #ef4444;
        color: white;
        border: none;
        border-radius: 25px;
        padding: 8px 16px;
        font-size: 12px;
        font-weight: 500;
        cursor: pointer;
        box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        gap: 6px;
        white-space: nowrap;
      }

      .clear-filter-btn:hover {
        background: #dc2626;
        box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
        transform: translateY(-1px);
      }

      .clear-filter-btn:active {
        transform: translateY(0);
      }

      .clear-filter-btn i {
        font-size: 10px;
      }

      @keyframes slideInRight {
        from {
          transform: translateX(100%);
          opacity: 0;
        }
        to {
          transform: translateX(0);
          opacity: 1;
        }
      }

      @media (max-width: 768px) {
        .dashboard-clear-filter-btn {
          bottom: 80px;
          right: 15px;
        }
        
        .clear-filter-btn {
          padding: 6px 12px;
          font-size: 11px;
        }
        
        .clear-filter-btn span {
          display: none;
        }
        
        .clear-filter-btn i {
          font-size: 12px;
        }
      }
    `;

    document.head.appendChild(styles);
  }

  function checkAndToggleFields() {
    const convertedChecked = convertedCheckbox.checked;
    const cancelChecked = document.getElementById("canceleoi").checked;

    if (convertedChecked) {
      additionalFields.style.display = "block";
      requiredInputs.forEach((input) => {
        input.required = true;
      });
      validateConvertedFields();
    } else {
      additionalFields.style.display = "none";
      requiredInputs.forEach((input) => {
        input.required = false;
      });
      updateButton.disabled = false;
    }
  }


  document.getElementById("canceleoi").addEventListener("change", function () {
    checkAndToggleFields();
  });

  function validateConvertedFields() {
    // Only validate if converted is checked AND cancel is NOT checked
    if (convertedCheckbox.checked && !document.getElementById("canceleoi").checked) {
      let allFilled = true;

      requiredInputs.forEach((input) => {
        if (!input.value.trim()) {
          allFilled = false;
        }
      });

      updateButton.disabled = !allFilled;
    } else {
      updateButton.disabled = false;
    }
  }


  // Monitor Converted checkbox
  convertedCheckbox.addEventListener("change", function () {
    checkAndToggleFields();
  });

  // Monitor input fields in additional fields section
  requiredInputs.forEach((input) => {
    input.addEventListener("input", validateConvertedFields);
  });

  // Initial state check (in case of form pre-fill)
  checkAndToggleFields();

  function getNextIconPath() {
    iconCounter = (iconCounter % 6) + 1; // Cycle through 1-6
    return `assets/dataimage/icon-${iconCounter}.png`;
  }

  $(document).ready(function () {
    document.querySelector(".loader-container").style.display = "flex";

    // Load user hierarchy first
    loadUserHierarchy();

    // Apply URL date filters after a short delay to ensure everything is loaded
    setTimeout(() => {
      applyUrlDateFilters();
    }, 500);

    // Initialize DataTable for EOI with AJAX
    eoiTable = $("#eoiTable").DataTable({
      ajax: {
        url: getEoiDataUrl(),
        type: "GET",
        dataSrc: function (json) {
          iconCounter = 0;
          return json.map((row) => {
            return {
              ...row, // Spread all existing properties
              profilePic: getNextIconPath(), // Add profilePic with cycling icons
            };
          });
        }, // Use '' if your PHP returns a top-level array, or 'data' if wrapped like {data: [...]}
      },
      processing: true, // Shows processing indicator
      serverSide: false,
      responsive: true,
      ordering: false,
      drawCallback: function (settings) {
        initExpandButtons();
        handleResponsiveBehavior();
      },

      dom: '<"top-container"<"search-container"f><"button-container"B><"length-container"l>>rt<"bottom-container"ip>',
      buttons: [
        {
          text: '<i class="fas fa-filter" aria-hidden="true"></i>',
          className: "dt-button filter-btn",
          action: function (e, dt, node, config) {
            $(".filter-panel").toggleClass("open");
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
        },
      ]
      ,
      lengthMenu: [
        [5, 10, 25, 50, 100, 250, 500],
        [5, 10, 25, 50, 100, 250, 500],
      ],
      pageLength: 10,
      columns: [
        {
          data: "id",
          className: "sticky-column sticky-header",
          render: function (data) {
            return `<div class="eoi-id-section">${data}</div>`;
          },
        },
        {
          data: "customerName",
          render: function (data, type, row) {
            if (type === "filter") return data;
            const touchStatus = {
              class: "neutral",
              text: "No recent activity",
            };
            const sourceIconPath = "default-source-icon.png";
            const sourceName = "Source";

            return `
      <div class="customer-profile lead-profile" data-filter="${data}">
            <div class="left-customer left-lead">
                <img src="${row.profilePic}" alt="${row.name
              }" class="lead-avatar">
                <div class="notes-section">
                    <div class="customer-info">
                        <div class="created-info" style="display:none"><b>Created at</b><br>${row.created || "N/A"
              }</div>
                        <div class="mobile-project-info">
                            <div class="customer-name lead-name">
                                <h4 class="truncate-name">${data}</h4>
                            </div>
                            <div class="customer-id-hidden" style="display:none">${row.id
              }</div>
                        </div>
                        
                        <div class="mobile-project-info">
                            <span class="project-name-mobile truncate-project">Project: ${row.projectName || "N/A"
              }</span>
                        </div>
                        <div class="phone-info encrypted" data-real-phone="${row.contactNumber || ""
              }">
                            ${row.contactNumber}
                        </div>
                    </div>
                </div>
            </div>
            <div class="right-customer">
                <div class="date-info date-customer">${formatDate(
                row.bookingDate
              )}</div>
            </div>
        </div>
        <div class="created-info-mobile" style="display:none">${row.created || "N/A"
              }</div>
    `;
          },
        },

        {
          data: "builderName",
          className: "  tab-hidde",
          render: function (data, type, row) {
            if (type === "filter") return data;
            return `<div class="builder-info">${data}</div>`;
          },
        },
        {
          data: "projectName",
          className: "  mobile-hidde",
          render: function (data, type, row) {
            if (type === "filter") return data;
            return `<div class="project-info">${data}</div>`;
          },
        },

        {
          data: "contactNumber",
          className: "  mobile-hidde",
          visible: false,
          render: function (data) {
            const encryptedPhone = data;
            return `<div class="contact-info encrypted" data-real-phone="${data}">${encryptedPhone}</div>`;
          },
        },
        {
          data: "email",
          className: "  mobile-hidde",
          visible: false,
          render: function (data, type, row) {
            if (type === "filter") return data;
            return `<div class="email-info">${data}</div>`;
          },
        },
        {
          data: "projectType",
          className: "  tab-hidde",
          render: function (data, type, row) {
            if (type === "filter") return data;
            return `<div class="type-info"><span class="type-badge ${data.toLowerCase()}">${data}</span></div>`;
          },
        },
        {
          data: "bookingDate",
          className: "  mobile-hidde",
          render: function (data, type, row) {
            if (type === "filter") return data;
            return `<div class="date-info">${formatDate(data)}</div>`;
          },
        },
        {
          data: "bookingMonth",
          className: "month-eoi ",
          visible: false,
          render: function (data, type, row) {
            if (type === "filter") return data;
            // Convert "2025-08" format to "August 2025"
            if (data && data.match(/^\d{4}-\d{2}$/)) {
              // Check if format is YYYY-MM
              const [year, month] = data.split("-");
              const date = new Date(year, month - 1, 1); // Create date from year and month
              if (!isNaN(date)) {
                // Check if valid date
                const monthName = date.toLocaleString("default", {
                  month: "long",
                });
                return `<div class="month-info">${monthName} ${year}</div>`;
              }
            }
            return `<div class="month-info">${data || "N/A"}</div>`;
          },
        },

        {
          data: null,
          className: "expand-btn-cell",
          orderable: false,
          render: function () {
            return `
            <button class="expand-row-btn expand-eoi" aria-label="Expand row">
                <i class="fas fa-chevron-down down-arrow"></i>
                <i class="fas fa-chevron-up up-arrow" style="display: none;"></i>
            </button>
        `;
          },
        },
        {
          data: null,
          className: "  mobile-hidde",
          orderable: false,
          render: function (data, type, row) {
            return `
            <div class="action-buttons-eoi">
                <button class="action-btn-eoi complete-btn " 
                        
                        data-row='${JSON.stringify(
              row
            )}'>Complete <i class="fas fa-check"></i></button>
            </div>
        `;
          },
        },
      ],
      language: {
        search: "",
        searchPlaceholder: "Search EOI...",
        lengthMenu: "_MENU_ ",
        info: "Showing _START_ to _END_ of _TOTAL_ EOI",
        infoEmpty: "No EOI to show",
        paginate: {
          first: "First",
          last: "Last",
          next: "Next",
          previous: "Previous",
        },
      },
      initComplete: function () {
        document.querySelector(".loader-container").style.display = "none";
        // Move controls to container
        $(".top-container").detach().appendTo("#eoiTableControlsContainer");

        // Add date range filter
        const dateRangeHTML = `
                <div class="date-range-filter">
                <button class="calendar-btn" id="openDateRangePicker">
                    <i class="fas fa-calendar-alt"></i>
                </button>
            <input type="text" id="dateFrom" class="date-input" placeholder="From Date" onfocus="(this.type='date')" onblur="if(!this.value)this.type='text';">
            <input type="text" id="dateTo" class="date-input" placeholder="To Date" onfocus="(this.type='date')" onblur="if(!this.value)this.type='text';">
            </div>
            `;

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
        $(".search-container").after(dateRangeHTML);

        $(".button-container").css({
          display: "flex",
          "align-items": "center",
          gap: "10px",
          order: "3",
        });

        $(".length-container").css({
          order: "4",
        });

        // Style search input
        $(".dataTables_filter input")
          .css({
            width: "100%",
            padding: "8.5px 12px",
            border: "1px solid white",
            "font-size": "14px",
            transition: "border-color 0.2s ease",
            margin: "-4px 0 -5px 0",
          })
          .attr("placeholder", "Search EOI...");

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
          "border-radius": "4px",
          padding: "8px 12px",
          "font-size": "14px",
          color: "#3c4043",
          cursor: "pointer",
          transition: "all 0.2s ease",
          display: "inline-flex",
          "align-items": "center",
          gap: "6px",
          margin: "0px",
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
          "margin-left": "10px",
        });

        $(".dataTables_length select").css({
          border: "1px solid #dadce0",
          "border-radius": "4px",
          padding: "8px 12px",
          "font-size": "14px",
        });

        // Initialize filter dropdown functionality
        initFilterDropdowns();

        // Handle responsive behavior
        handleResponsiveBehavior();

        // Initialize mobile buttons
        initMobileButtons();

        // Initialize date range filter
        initDateRangeFilter();

        // NEW: Monitor column visibility changes
        monitorColumnVisibility();

        // Check for active filters on initialization
        if (window.eoiClearFiltersManager) {
          window.eoiClearFiltersManager.updateVisibility();
        }
      },
    });

    // Then populate the filters
    populateFilterDropdowns();

    // Also repopulate when table data changes
    eoiTable.on("draw", function () {
      populateFilterDropdowns();
      if (window.eoiClearFiltersManager) {
        window.eoiClearFiltersManager.updateVisibility();
      }
    });

    $(window).on("resize", function () {
      if (typeof eoiTable !== "undefined") {
        eoiTable.columns.adjust();
        handleResponsiveBehavior();
      }
    });

    initExpandButtons();
  });

  // Function to format date
  function formatDate(dateString) {
    const options = { year: "numeric", month: "short", day: "numeric" };
    return new Date(dateString).toLocaleDateString("en-US", options);
  }

  function handleFilterSelection(
    $checkbox,
    isChecked,
    filterValue,
    columnIndex,
    $filterOptions
  ) {
    const checkedCount = $filterOptions.find(".filter-option:checked").length;

    // Prevent unchecking the last option
    if (!isChecked && checkedCount <= 1) {
      $checkbox.prop("checked", true);
      return;
    }

    if (isChecked) {
      if (filterValue === "") {
        // If "All" is checked, uncheck all individual options
        $filterOptions.find('.filter-option[value!=""]').prop("checked", false);
        $checkbox.prop("checked", true);
        eoiTable.column(columnIndex).search("").draw();
      } else {
        // If specific option is checked, uncheck "All" option
        $filterOptions.find('.filter-option[value=""]').prop("checked", false);
        $checkbox.prop("checked", true);

        // Get all checked values
        const checkedValues = [];
        $filterOptions
          .find('.filter-option[value!=""]:checked')
          .each(function () {
            checkedValues.push($(this).val());
          });

        if (checkedValues.length > 0) {
          // Create regex for multiple values (exact match)
          const searchRegex =
            "^(" +
            checkedValues
              .map((value) => value.replace(/[.*+?^${}()|[\]\\]/g, "\\$&"))
              .join("|") +
            ")$";
          eoiTable.column(columnIndex).search(searchRegex, true, false).draw();
        } else {
          // If no options are selected, show all
          eoiTable.column(columnIndex).search("").draw();
        }
      }
    } else {
      // If unchecking an option (not "All")
      if (filterValue === "") {
        // If unchecking "All", do nothing special
        return;
      }

      // Check if any options are still selected
      const anyChecked =
        $filterOptions.find('.filter-option[value!=""]:checked').length > 0;

      if (!anyChecked) {
        // If no options are selected, check "All" and show all
        $filterOptions.find('.filter-option[value=""]').prop("checked", true);
        eoiTable.column(columnIndex).search("").draw();
      } else {
        // Update filter with remaining selected options
        const checkedValues = [];
        $filterOptions
          .find('.filter-option[value!=""]:checked')
          .each(function () {
            checkedValues.push($(this).val());
          });

        const searchRegex =
          "^(" +
          checkedValues
            .map((value) => value.replace(/[.*+?^${}()|[\]\\]/g, "\\$&"))
            .join("|") +
          ")$";
        eoiTable.column(columnIndex).search(searchRegex, true, false).draw();
      }
    }
  }

  function handleResponsiveBehavior() {
    const isMobile = window.innerWidth <= 749;
    const isTab = window.innerWidth <= 900 && window.innerWidth > 749;

    if (isMobile) {
      $(".mobile-hidde").hide();
      $(".tab-hidde").hide();
      // Ensure key columns are visible

    } else if (isTab) {
      $(".mobile-hidde").hide();
      $(".tab-hidde").show();


    } else {
      $(".tab-hidde").show();
      $(".mobile-hidde").show();
    }

    $("#eoiTable tbody tr").each(function () {
      const $row = $(this);
      if (!$row.hasClass("details-row")) {
        const detailsContent = createDetailsContent($row);
        const eoiId = $row.find("td:eq(0) .eoi-id-section").text();
        let $detailsRow = $row.next(".details-row");

        if (detailsContent) {
          if ($detailsRow.length) {
            $detailsRow.find(".details-content").html(detailsContent);
          } else {
            $detailsRow = $(`
                        <tr class="details-row" data-parent-id="${eoiId}">
                            <td colspan="12">
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
      }
    });
  }



  function monitorColumnVisibility() {
    // Listen for column visibility changes
    eoiTable.on('column-visibility.dt', function (e, settings, column, state) {
      // Wait a moment for the UI to update
      setTimeout(function () {
        // Repopulate all filter dropdowns
        populateAllFilterDropdowns();
      }, 100);
    });
  }

  // NEW FUNCTION: Populate all filter dropdowns
  function populateAllFilterDropdowns() {
    const filterHeaders = document.querySelectorAll(".filter-header");

    filterHeaders.forEach((header) => {
      const columnIndex = parseInt(
        header.querySelector(".filter-search-input")?.dataset.column
      );

      if (isNaN(columnIndex)) return;

      const dropdown = header.querySelector(".filter-options");
      if (!dropdown) return;

      // Repopulate based on column index
      switch (columnIndex) {
        case 1: // Customer Name
          populateCustomerNameFilter(dropdown);
          break;
        case 2: // Builder Name
          populateBuilderNameFilter(dropdown);
          break;
        case 3: // Project Name
          populateProjectNameFilter(dropdown);
          break;
        case 4: // Contact Number
          populateContactNumberFilter(dropdown);
          break;
        case 5: // Email
          populateEmailFilter(dropdown);
          break;
        case 6: // Project Type
          populateProjectTypeFilter(dropdown);
          break;
        case 7: // Booking Date
          populateBookingDateFilter(dropdown);
          break;
        case 8: // Booking Month
          populateBookingMonthFilter(dropdown);
          break;
        default:
          // For other columns, use generic population
          populateGenericFilter(dropdown, columnIndex);
      }
    });
  }



  // New function to populate customer name filter
  function populateCustomerNameFilter(dropdown) {
    // Get currently checked values first
    const currentlyChecked = [];
    $(dropdown)
      .find(".filter-option:checked")
      .each(function () {
        if ($(this).val() !== "") {
          currentlyChecked.push($(this).val());
        }
      });

    // Clear and recreate options (your existing code)
    const allCustomersOption = dropdown.querySelector(
      '.filter-option[value=""]'
    );
    dropdown.innerHTML = "";

    // Add "All Customers" option
    const allLabel = document.createElement("label");
    allLabel.innerHTML = `
    <input type="checkbox" class="filter-option" 
           data-column="1" value=""> All Customers
  `;
    dropdown.appendChild(allLabel);

    // Add customer options
    const uniqueCustomers = getUniqueCustomerNames();
    uniqueCustomers.forEach((customerName) => {
      if (!customerName || customerName.trim() === "") return;

      const isChecked = currentlyChecked.includes(customerName.trim());

      const label = document.createElement("label");
      label.innerHTML = `
      <input type="checkbox" class="filter-option" 
             data-column="1" value="${escapeHtml(customerName.trim())}"
             ${isChecked ? "checked" : ""}>
      ${escapeHtml(customerName.trim())}
    `;
      dropdown.appendChild(label);
    });

    // If nothing was checked before, check "All Customers"
    if (currentlyChecked.length === 0) {
      $(dropdown).find('.filter-option[value=""]').prop("checked", true);
    }
  }

  // Function to get unique customer names from DataTable
  function getUniqueCustomerNames() {
    const customerNames = new Set();

    // Get customer names from all rows in the DataTable
    eoiTable.rows({ search: "applied" }).every(function () {
      const data = this.data();
      if (data && data.customerName) {
        const customerName = data.customerName.trim();
        if (customerName) {
          customerNames.add(customerName);
        }
      }
    });

    return Array.from(customerNames).sort();
  }


  // Select ALL date & month inputs inside modals
  const dateInputs = document.querySelectorAll(
    "#bdateo input[type='date'], \
     #bdate input[type='date'], \
     #isolatedFilterEndDate1 input[type='date'], \
     #isolatedFilterStartDate1 input[type='month']"
  );

  dateInputs.forEach(input => {
    const fieldset = input.closest(".fieldset-label");
    if (fieldset) {
      fieldset.addEventListener("click", (e) => {
        // Prevent double trigger when clicking directly on input
        if (e.target !== input) {
          if (input.showPicker) {
            input.showPicker(); // modern browsers
          } else {
            input.focus(); // fallback
          }
        }
      });
    }
  });

  // Keep booking month in YYYY-MM format in both add and edit forms
  $(document).on("input change", "#bdateo", () => syncBookingMonth("#bdateo", "#bmontho"));
  $(document).on("input change", "#bdate", () => syncBookingMonth("#bdate", "#bmonth"));
  // Initialize defaults on load
  syncBookingMonth("#bdateo", "#bmontho");

  const bdateInput = document.getElementById("bdate");

  // Open datepicker when clicking anywhere on the fieldset
  bdateInput.closest("fieldset").addEventListener("click", function () {
    bdateInput.showPicker(); // Native method to open the calendar
  });

  const dateStartInput = document.getElementById("isolatedFilterEndDate1");

  // Open datepicker when clicking anywhere on the fieldset
  dateStartInput.closest("fieldset").addEventListener("click", function () {
    dateStartInput.showPicker(); // Native method to open the calendar
  });

  const dateEndInput = document.getElementById("isolatedFilterStartDate1");

  // Open datepicker when clicking anywhere on the fieldset
  dateEndInput.closest("fieldset").addEventListener("click", function () {
    dateEndInput.showPicker(); // Native method to open the calendar
  });

  function initFilterDropdowns() {
    $(document).on("click", ".filter-header-btn", function (e) {
      e.stopPropagation();
      e.preventDefault();
      const $header = $(this).closest(".filter-header");
      const $dropdown = $header.find(".filter-dropdown");
      $(".filter-dropdown").not($dropdown).hide();
      $dropdown.toggle();
    });

    $(document).on("click", function () {
      $(".filter-dropdown").hide();
    });

    $(document).on("click", ".filter-dropdown", function (e) {
      e.stopPropagation();
    });

    // Handle search input in dropdowns
    $(document).on("keyup", ".filter-search-input", function () {
      const searchTerm = $(this).val().toLowerCase();
      const $dropdown = $(this).closest(".filter-dropdown");
      const columnIndex = parseInt($(this).data("column"));

      if (searchTerm === "") {
        $dropdown.find(".filter-options label").show();
      } else {
        $dropdown.find(".filter-options label").each(function () {
          const labelText = $(this).text().toLowerCase();
          $(this).toggle(labelText.includes(searchTerm));
        });
      }
    });

    // Filter option selection handler
    $(document).on("change", ".filter-dropdown .filter-option", function () {
      const columnIndex = parseInt($(this).data("column"));
      const filterValue = $(this).val();
      const isChecked = $(this).is(":checked");
      const $filterOptions = $(this).closest(".filter-options");

      if (columnIndex === 1) {
        handleCustomerFilter($(this), isChecked, filterValue, $filterOptions);
      } else if (columnIndex === 2) {
        handleBuilderFilter($(this), isChecked, filterValue, $filterOptions);
      } else if (columnIndex === 3) {
        handleProjectFilter($(this), isChecked, filterValue, $filterOptions);
      } else if (columnIndex === 4) {
        handleContactFilter($(this), isChecked, filterValue, $filterOptions);
      } else if (columnIndex === 5) {
        handleEmailFilter($(this), isChecked, filterValue, $filterOptions);
      } else if (columnIndex === 6) {
        handleProjectTypeFilter($(this), isChecked, filterValue, $filterOptions);
      } else if (columnIndex === 7) {
        handleBookingDateFilter($(this), isChecked, filterValue, $filterOptions);
      } else if (columnIndex === 8) {
        handleBookingMonthFilter($(this), isChecked, filterValue, $filterOptions);
      } else {
        handleGenericFilter($(this), isChecked, filterValue, columnIndex, $filterOptions);
      }

      // Update floating clear-filters button visibility
      if (window.eoiClearFiltersManager) {
        window.eoiClearFiltersManager.updateVisibility();
      }
    });
  }

  // Customer name filter handler (special case)
  function handleCustomerFilter(
    $checkbox,
    isChecked,
    filterValue,
    $filterOptions
  ) {
    if (isChecked) {
      if (filterValue === "") {
        $filterOptions.find('.filter-option[value!=""]').prop("checked", false);
        $checkbox.prop("checked", true);
        eoiTable.column(1).search("").draw();
      } else {
        $filterOptions.find('.filter-option[value=""]').prop("checked", false);

        const checkedCustomers = [];
        $filterOptions
          .find('.filter-option[value!=""]:checked')
          .each(function () {
            checkedCustomers.push($(this).val());
          });

        if (checkedCustomers.length > 0) {
          const searchRegex =
            "^(" +
            checkedCustomers
              .map((customer) => escapeRegex(customer))
              .join("|") +
            ")$";
          eoiTable.column(1).search(searchRegex, true, false).draw();
        } else {
          eoiTable.column(1).search("").draw();
        }
      }
    } else {
      if (filterValue === "") return;

      const anyChecked =
        $filterOptions.find('.filter-option[value!=""]:checked').length > 0;

      if (!anyChecked) {
        $filterOptions.find('.filter-option[value=""]').prop("checked", true);
        eoiTable.column(1).search("").draw();
      } else {
        const checkedCustomers = [];
        $filterOptions
          .find('.filter-option[value!=""]:checked')
          .each(function () {
            checkedCustomers.push($(this).val());
          });

        const searchRegex =
          "^(" +
          checkedCustomers.map((customer) => escapeRegex(customer)).join("|") +
          ")$";
        eoiTable.column(1).search(searchRegex, true, false).draw();
      }
    }
  }
  function handleBuilderFilter(
    $checkbox,
    isChecked,
    filterValue,
    $filterOptions
  ) {


    if (isChecked) {
      if (filterValue === "") {
        // If "All" is checked, uncheck all individual options
        $filterOptions.find('.filter-option[value!=""]').prop("checked", false);
        eoiTable.column(2).search("").draw();
      } else {
        // If specific builder is checked, uncheck "All" option
        $filterOptions.find('.filter-option[value=""]').prop("checked", false);

        // Get all checked builder values
        const checkedBuilders = [];
        $filterOptions
          .find('.filter-option[value!=""]:checked')
          .each(function () {
            checkedBuilders.push($(this).val());
          });

        if (checkedBuilders.length > 0) {
          // Create regex for exact match
          const searchRegex =
            "^(" +
            checkedBuilders.map((builder) => escapeRegex(builder)).join("|") +
            ")$";
          eoiTable.column(2).search(searchRegex, true, false).draw();
        } else {
          eoiTable.column(2).search("").draw();
        }
      }
    } else {
      // If unchecking an option (not "All")
      if (filterValue === "") return;

      const anyChecked =
        $filterOptions.find('.filter-option[value!=""]:checked').length > 0;

      if (!anyChecked) {
        $filterOptions.find('.filter-option[value=""]').prop("checked", true);
        eoiTable.column(2).search("").draw();
      } else {
        const checkedBuilders = [];
        $filterOptions
          .find('.filter-option[value!=""]:checked')
          .each(function () {
            checkedBuilders.push($(this).val());
          });

        const searchRegex =
          "^(" +
          checkedBuilders.map((builder) => escapeRegex(builder)).join("|") +
          ")$";
        eoiTable.column(2).search(searchRegex, true, false).draw();
      }
    }
  }

  function populateBookingDateFilter(dropdown) {
    const currentlyChecked = [];
    $(dropdown)
      .find(".filter-option:checked")
      .each(function () {
        if ($(this).val() !== "") {
          currentlyChecked.push($(this).val());
        }
      });

    dropdown.innerHTML = "";

    // Add "All Dates" option
    const allLabel = document.createElement("label");
    allLabel.innerHTML = `
        <input type="checkbox" class="filter-option" 
               data-column="7" value=""> All Dates
    `;
    dropdown.appendChild(allLabel);

    // Get unique booking dates from DataTable
    const dates = new Set();
    eoiTable.rows({ search: "applied" }).every(function () {
      const data = this.data();
      if (data && data.bookingDate) {
        const date = data.bookingDate.trim();
        if (date) {
          dates.add(date);
        }
      }
    });

    Array.from(dates)
      .sort()
      .forEach((date) => {
        const isChecked = currentlyChecked.includes(date);
        const label = document.createElement("label");
        label.innerHTML = `
            <input type="checkbox" class="filter-option" 
                   data-column="7" value="${escapeHtml(date)}"
                   ${isChecked ? "checked" : ""}>
            ${escapeHtml(date)}
        `;
        dropdown.appendChild(label);
      });

    if (currentlyChecked.length === 0) {
      $(dropdown).find('.filter-option[value=""]').prop("checked", true);
    }
  }

  function populateBookingMonthFilter(dropdown) {
    const currentlyChecked = [];
    $(dropdown)
      .find(".filter-option:checked")
      .each(function () {
        if ($(this).val() !== "") {
          currentlyChecked.push($(this).val());
        }
      });

    dropdown.innerHTML = "";

    // Add "All Months" option
    const allLabel = document.createElement("label");
    allLabel.innerHTML = `
        <input type="checkbox" class="filter-option" 
               data-column="8" value=""> All Months
    `;
    dropdown.appendChild(allLabel);

    // Get unique booking months from DataTable
    const months = new Set();
    eoiTable.rows({ search: "applied" }).every(function () {
      const data = this.data();
      if (data && data.bookingMonth) {
        const month = data.bookingMonth.trim();
        if (month) {
          months.add(month);
        }
      }
    });

    Array.from(months)
      .sort()
      .forEach((month) => {
        const isChecked = currentlyChecked.includes(month);
        const label = document.createElement("label");
        label.innerHTML = `
            <input type="checkbox" class="filter-option" 
                   data-column="8" value="${escapeHtml(month)}"
                   ${isChecked ? "checked" : ""}>
            ${escapeHtml(month)}
        `;
        dropdown.appendChild(label);
      });

    if (currentlyChecked.length === 0) {
      $(dropdown).find('.filter-option[value=""]').prop("checked", true);
    }
  }

  function handleBookingDateFilter(
    $checkbox,
    isChecked,
    filterValue,
    $filterOptions
  ) {

    if (isChecked) {
      if (filterValue === "") {
        $filterOptions.find('.filter-option[value!=""]').prop("checked", false);
        eoiTable.column(7).search("").draw();
      } else {
        $filterOptions.find('.filter-option[value=""]').prop("checked", false);
        const checkedDates = [];
        $filterOptions
          .find('.filter-option[value!=""]:checked')
          .each(function () {
            checkedDates.push($(this).val());
          });
        if (checkedDates.length > 0) {
          const searchRegex =
            "^(" + checkedDates.map(escapeRegex).join("|") + ")$";
          eoiTable.column(7).search(searchRegex, true, false).draw();
        } else {
          eoiTable.column(7).search("").draw();
        }
      }
    } else {
      if (filterValue === "") return;
      const anyChecked =
        $filterOptions.find('.filter-option[value!=""]:checked').length > 0;
      if (!anyChecked) {
        $filterOptions.find('.filter-option[value=""]').prop("checked", true);
        eoiTable.column(7).search("").draw();
      } else {
        const checkedDates = [];
        $filterOptions
          .find('.filter-option[value!=""]:checked')
          .each(function () {
            checkedDates.push($(this).val());
          });
        const searchRegex =
          "^(" + checkedDates.map(escapeRegex).join("|") + ")$";
        eoiTable.column(7).search(searchRegex, true, false).draw();
      }
    }
  }

  function handleBookingMonthFilter(
    $checkbox,
    isChecked,
    filterValue,
    $filterOptions
  ) {

    if (isChecked) {
      if (filterValue === "") {
        $filterOptions.find('.filter-option[value!=""]').prop("checked", false);
        eoiTable.column(8).search("").draw();
      } else {
        $filterOptions.find('.filter-option[value=""]').prop("checked", false);
        const checkedMonths = [];
        $filterOptions
          .find('.filter-option[value!=""]:checked')
          .each(function () {
            checkedMonths.push($(this).val());
          });
        if (checkedMonths.length > 0) {
          const searchRegex =
            "^(" + checkedMonths.map(escapeRegex).join("|") + ")$";
          eoiTable.column(8).search(searchRegex, true, false).draw();
        } else {
          eoiTable.column(8).search("").draw();
        }
      }
    } else {
      if (filterValue === "") return;
      const anyChecked =
        $filterOptions.find('.filter-option[value!=""]:checked').length > 0;
      if (!anyChecked) {
        $filterOptions.find('.filter-option[value=""]').prop("checked", true);
        eoiTable.column(8).search("").draw();
      } else {
        const checkedMonths = [];
        $filterOptions
          .find('.filter-option[value!=""]:checked')
          .each(function () {
            checkedMonths.push($(this).val());
          });
        const searchRegex =
          "^(" + checkedMonths.map(escapeRegex).join("|") + ")$";
        eoiTable.column(8).search(searchRegex, true, false).draw();
      }
    }
  }

  function handleProjectFilter(
    $checkbox,
    isChecked,
    filterValue,
    $filterOptions
  ) {


    if (isChecked) {
      if (filterValue === "") {
        $filterOptions.find('.filter-option[value!=""]').prop("checked", false);
        eoiTable.column(3).search("").draw();
      } else {
        $filterOptions.find('.filter-option[value=""]').prop("checked", false);

        const checkedProjects = [];
        $filterOptions
          .find('.filter-option[value!=""]:checked')
          .each(function () {
            checkedProjects.push($(this).val());
          });

        if (checkedProjects.length > 0) {
          const searchRegex =
            "^(" +
            checkedProjects.map((project) => escapeRegex(project)).join("|") +
            ")$";
          eoiTable.column(3).search(searchRegex, true, false).draw();
        } else {
          eoiTable.column(3).search("").draw();
        }
      }
    } else {
      if (filterValue === "") return;

      const anyChecked =
        $filterOptions.find('.filter-option[value!=""]:checked').length > 0;

      if (!anyChecked) {
        $filterOptions.find('.filter-option[value=""]').prop("checked", true);
        eoiTable.column(3).search("").draw();
      } else {
        const checkedProjects = [];
        $filterOptions
          .find('.filter-option[value!=""]:checked')
          .each(function () {
            checkedProjects.push($(this).val());
          });

        const searchRegex =
          "^(" +
          checkedProjects.map((project) => escapeRegex(project)).join("|") +
          ")$";
        eoiTable.column(3).search(searchRegex, true, false).draw();
      }
    }
  }

  function populateBuilderNameFilter(dropdown) {
    const currentlyChecked = [];
    $(dropdown)
      .find(".filter-option:checked")
      .each(function () {
        if ($(this).val() !== "") {
          currentlyChecked.push($(this).val());
        }
      });

    dropdown.innerHTML = "";

    // Add "All Builders" option
    const allLabel = document.createElement("label");
    allLabel.innerHTML = `
        <input type="checkbox" class="filter-option" 
               data-column="2" value=""> All Builders
    `;
    dropdown.appendChild(allLabel);

    // Get unique builder names from DataTable
    const builderNames = new Set();
    eoiTable.rows({ search: "applied" }).every(function () {
      const data = this.data();
      if (data && data.builderName) {
        const builderName = data.builderName.trim();
        if (builderName) {
          builderNames.add(builderName);
        }
      }
    });

    Array.from(builderNames)
      .sort()
      .forEach((builderName) => {
        const isChecked = currentlyChecked.includes(builderName);
        const label = document.createElement("label");
        label.innerHTML = `
            <input type="checkbox" class="filter-option" 
                   data-column="2" value="${escapeHtml(builderName)}"
                   ${isChecked ? "checked" : ""}>
            ${escapeHtml(builderName)}
        `;
        dropdown.appendChild(label);
      });

    if (currentlyChecked.length === 0) {
      $(dropdown).find('.filter-option[value=""]').prop("checked", true);
    }
  }

  function populateProjectNameFilter(dropdown) {
    const currentlyChecked = [];
    $(dropdown)
      .find(".filter-option:checked")
      .each(function () {
        if ($(this).val() !== "") {
          currentlyChecked.push($(this).val());
        }
      });

    dropdown.innerHTML = "";

    // Add "All Projects" option
    const allLabel = document.createElement("label");
    allLabel.innerHTML = `
        <input type="checkbox" class="filter-option" 
               data-column="3" value=""> All Projects
    `;
    dropdown.appendChild(allLabel);

    // Get unique project names from DataTable
    const projectNames = new Set();
    eoiTable.rows({ search: "applied" }).every(function () {
      const data = this.data();
      if (data && data.projectName) {
        const projectName = data.projectName.trim();
        if (projectName) {
          projectNames.add(projectName);
        }
      }
    });

    Array.from(projectNames)
      .sort()
      .forEach((projectName) => {
        const isChecked = currentlyChecked.includes(projectName);
        const label = document.createElement("label");
        label.innerHTML = `
            <input type="checkbox" class="filter-option" 
                   data-column="3" value="${escapeHtml(projectName)}"
                   ${isChecked ? "checked" : ""}>
            ${escapeHtml(projectName)}
        `;
        dropdown.appendChild(label);
      });

    if (currentlyChecked.length === 0) {
      $(dropdown).find('.filter-option[value=""]').prop("checked", true);
    }
  }

  function populateEmailFilter(dropdown) {
    // Get currently checked values first
    const currentlyChecked = [];
    $(dropdown)
      .find(".filter-option:checked")
      .each(function () {
        if ($(this).val() !== "") {
          currentlyChecked.push($(this).val());
        }
      });

    // Clear existing options
    dropdown.innerHTML = "";

    // Add "All Emails" option
    const allLabel = document.createElement("label");
    allLabel.innerHTML = `
        <input type="checkbox" class="filter-option" 
               data-column="5" value=""> All Emails
    `;
    dropdown.appendChild(allLabel);

    // Get unique emails from DataTable
    const emails = new Set();
    eoiTable.rows({ search: "applied" }).every(function () {
      const data = this.data();
      if (data && data.email) {
        const email = data.email.trim();
        if (email) {
          emails.add(email);
        }
      }
    });

    // Add email options
    Array.from(emails)
      .sort()
      .forEach((email) => {
        const isChecked = currentlyChecked.includes(email);

        const label = document.createElement("label");
        label.innerHTML = `
            <input type="checkbox" class="filter-option" 
                   data-column="5" value="${escapeHtml(email)}"
                   ${isChecked ? "checked" : ""}>
            ${escapeHtml(email)}
        `;
        dropdown.appendChild(label);
      });

    // If nothing was checked before, check "All Emails"
    if (currentlyChecked.length === 0) {
      $(dropdown).find('.filter-option[value=""]').prop("checked", true);
    }
  }

  function populateProjectTypeFilter(dropdown) {
    // Get currently checked values first
    const currentlyChecked = [];
    $(dropdown)
      .find(".filter-option:checked")
      .each(function () {
        if ($(this).val() !== "") {
          currentlyChecked.push($(this).val());
        }
      });

    // Clear existing options
    dropdown.innerHTML = "";

    // Add "All Types" option
    const allLabel = document.createElement("label");
    allLabel.innerHTML = `
        <input type="checkbox" class="filter-option" 
               data-column="6" value=""> All Types
    `;
    dropdown.appendChild(allLabel);

    // Get unique project types from DataTable
    const types = new Set();
    eoiTable.rows({ search: "applied" }).every(function () {
      const data = this.data();
      if (data && data.projectType) {
        const type = data.projectType.trim();
        if (type) {
          types.add(type);
        }
      }
    });

    // Add project type options
    Array.from(types)
      .sort()
      .forEach((type) => {
        const isChecked = currentlyChecked.includes(type);

        const label = document.createElement("label");
        label.innerHTML = `
            <input type="checkbox" class="filter-option" 
                   data-column="6" value="${escapeHtml(type)}"
                   ${isChecked ? "checked" : ""}>
            ${escapeHtml(type)}
        `;
        dropdown.appendChild(label);
      });

    // If nothing was checked before, check "All Types"
    if (currentlyChecked.length === 0) {
      $(dropdown).find('.filter-option[value=""]').prop("checked", true);
    }
  }

  function handleEmailFilter(
    $checkbox,
    isChecked,
    filterValue,
    $filterOptions
  ) {

    if (isChecked) {
      if (filterValue === "") {
        $filterOptions.find('.filter-option[value!=""]').prop("checked", false);
        eoiTable.column(5).search("").draw();
      } else {
        $filterOptions.find('.filter-option[value=""]').prop("checked", false);
        const checkedEmails = [];
        $filterOptions
          .find('.filter-option[value!=""]:checked')
          .each(function () {
            checkedEmails.push($(this).val());
          });
        if (checkedEmails.length > 0) {
          const searchRegex =
            "^(" + checkedEmails.map(escapeRegex).join("|") + ")$";
          eoiTable.column(5).search(searchRegex, true, false).draw();
        } else {
          eoiTable.column(5).search("").draw();
        }
      }
    } else {
      if (filterValue === "") return;
      const anyChecked =
        $filterOptions.find('.filter-option[value!=""]:checked').length > 0;
      if (!anyChecked) {
        $filterOptions.find('.filter-option[value=""]').prop("checked", true);
        eoiTable.column(5).search("").draw();
      } else {
        const checkedEmails = [];
        $filterOptions
          .find('.filter-option[value!=""]:checked')
          .each(function () {
            checkedEmails.push($(this).val());
          });
        const searchRegex =
          "^(" + checkedEmails.map(escapeRegex).join("|") + ")$";
        eoiTable.column(5).search(searchRegex, true, false).draw();
      }
    }
  }

  function handleProjectTypeFilter(
    $checkbox,
    isChecked,
    filterValue,
    $filterOptions
  ) {

    if (isChecked) {
      if (filterValue === "") {
        $filterOptions.find('.filter-option[value!=""]').prop("checked", false);
        eoiTable.column(6).search("").draw();
      } else {
        $filterOptions.find('.filter-option[value=""]').prop("checked", false);
        const checkedTypes = [];
        $filterOptions
          .find('.filter-option[value!=""]:checked')
          .each(function () {
            checkedTypes.push($(this).val());
          });
        if (checkedTypes.length > 0) {
          const searchRegex =
            "^(" + checkedTypes.map(escapeRegex).join("|") + ")$";
          eoiTable.column(6).search(searchRegex, true, false).draw();
        } else {
          eoiTable.column(6).search("").draw();
        }
      }
    } else {
      if (filterValue === "") return;
      const anyChecked =
        $filterOptions.find('.filter-option[value!=""]:checked').length > 0;
      if (!anyChecked) {
        $filterOptions.find('.filter-option[value=""]').prop("checked", true);
        eoiTable.column(6).search("").draw();
      } else {
        const checkedTypes = [];
        $filterOptions
          .find('.filter-option[value!=""]:checked')
          .each(function () {
            checkedTypes.push($(this).val());
          });
        const searchRegex =
          "^(" + checkedTypes.map(escapeRegex).join("|") + ")$";
        eoiTable.column(6).search(searchRegex, true, false).draw();
      }
    }
  }

  function escapeRegex(string) {
    return string.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
  }

  // Generic filter handler for other columns
  function handleGenericFilter(
    $checkbox,
    isChecked,
    filterValue,
    columnIndex,
    $filterOptions
  ) {


    if (isChecked) {
      if (filterValue === "") {
        $filterOptions.find('.filter-option[value!=""]').prop("checked", false);
        $checkbox.prop("checked", true);
        eoiTable.column(columnIndex).search("").draw();
      } else {
        $filterOptions.find('.filter-option[value=""]').prop("checked", false);
        $checkbox.prop("checked", true);

        const checkedValues = [];
        $filterOptions
          .find('.filter-option[value!=""]:checked')
          .each(function () {
            checkedValues.push($(this).val());
          });

        if (checkedValues.length > 0) {
          // Use exact match regex
          const searchRegex =
            "^(" +
            checkedValues
              .map((value) => value.replace(/[.*+?^${}()|[\]\\]/g, "\\$&"))
              .join("|") +
            ")$";
          eoiTable.column(columnIndex).search(searchRegex, true, false).draw();
        } else {
          eoiTable.column(columnIndex).search("").draw();
        }
      }
    } else {
      if (filterValue === "") return;

      const anyChecked =
        $filterOptions.find('.filter-option[value!=""]:checked').length > 0;

      if (!anyChecked) {
        $filterOptions.find('.filter-option[value=""]').prop("checked", true);
        eoiTable.column(columnIndex).search("").draw();
      } else {
        const checkedValues = [];
        $filterOptions
          .find('.filter-option[value!=""]:checked')
          .each(function () {
            checkedValues.push($(this).val());
          });

        // Use exact match regex
        const searchRegex =
          "^(" +
          checkedValues
            .map((value) => value.replace(/[.*+?^${}()|[\]\\]/g, "\\$&"))
            .join("|") +
          ")$";
        eoiTable.column(columnIndex).search(searchRegex, true, false).draw();
      }
    }
  }
  function populateContactNumberFilter(dropdown) {
    // Get currently checked values first
    const currentlyChecked = [];
    $(dropdown)
      .find(".filter-option:checked")
      .each(function () {
        if ($(this).val() !== "") {
          currentlyChecked.push($(this).val());
        }
      });

    // Clear and recreate options
    dropdown.innerHTML = "";

    // Add "All Contacts" option
    const allLabel = document.createElement("label");
    allLabel.innerHTML = `
        <input type="checkbox" class="filter-option" 
               data-column="4" value=""> All Contacts
    `;
    dropdown.appendChild(allLabel);

    // Get unique contact numbers from DataTable
    const contacts = new Set();
    eoiTable.rows({ search: "applied" }).every(function () {
      const data = this.data();
      if (data && data.contactNumber) {
        const contact = data.contactNumber.trim();
        if (contact) {
          contacts.add(contact);
        }
      }
    });

    // Add contact options
    Array.from(contacts)
      .sort()
      .forEach((contact) => {
        const isChecked = currentlyChecked.includes(contact);

        const label = document.createElement("label");
        label.innerHTML = `
                <input type="checkbox" class="filter-option" 
                       data-column="4" value="${escapeHtml(contact)}"
                       ${isChecked ? "checked" : ""}>
                ${escapeHtml(contact)}
            `;
        dropdown.appendChild(label);
      });

    // If nothing was checked before, check "All Contacts"
    if (currentlyChecked.length === 0) {
      $(dropdown).find('.filter-option[value=""]').prop("checked", true);
    }
  }

  // Add this to your filter handler section
  function handleContactFilter($checkbox, isChecked, filterValue, $filterOptions) {
    if (isChecked) {
      if (filterValue === "") {
        $filterOptions.find('.filter-option[value!=""]').prop("checked", false);
        eoiTable.column(4).search("").draw();
      } else {
        $filterOptions.find('.filter-option[value=""]').prop("checked", false);
        const checkedContacts = [];
        $filterOptions.find('.filter-option[value!=""]:checked').each(function () {
          checkedContacts.push($(this).val());
        });
        if (checkedContacts.length > 0) {
          const searchRegex = "^(" + checkedContacts.map(escapeRegex).join("|") + ")$";
          eoiTable.column(4).search(searchRegex, true, false).draw();
        } else {
          eoiTable.column(4).search("").draw();
        }
      }
    } else {
      if (filterValue === "") return;
      const anyChecked = $filterOptions.find('.filter-option[value!=""]:checked').length > 0;
      if (!anyChecked) {
        $filterOptions.find('.filter-option[value=""]').prop("checked", true);
        eoiTable.column(4).search("").draw();
      } else {
        const checkedContacts = [];
        $filterOptions.find('.filter-option[value!=""]:checked').each(function () {
          checkedContacts.push($(this).val());
        });
        const searchRegex = "^(" + checkedContacts.map(escapeRegex).join("|") + ")$";
        eoiTable.column(4).search(searchRegex, true, false).draw();
      }
    }
  }



  // Enhanced function to populate filter dropdowns
  function populateFilterDropdowns() {
    // Get all unique values from the table data first
    const uniqueValues = {};

    // Collect all unique values from the original data (not just visible data)
    eoiTable.data().each(function (row) {
      Object.keys(row).forEach((key) => {
        if (!uniqueValues[key]) {
          uniqueValues[key] = new Set();
        }
        if (row[key]) {
          uniqueValues[key].add(row[key].toString().trim());
        }
      });
    });

    // Populate each filter dropdown
    const filterHeaders = document.querySelectorAll(".filter-header");
    filterHeaders.forEach((header) => {
      const columnIndex = parseInt(
        header.querySelector(".filter-search-input")?.dataset.column
      );
      if (isNaN(columnIndex)) return;

      const dropdown = header.querySelector(".filter-options");
      if (!dropdown) return;

      // Get currently checked values
      const currentlyChecked = new Set();
      $(dropdown)
        .find(".filter-option:checked")
        .each(function () {
          if ($(this).val() !== "") {
            currentlyChecked.add($(this).val());
          }
        });

      // Clear dropdown content
      dropdown.innerHTML = "";

      // Add "All" option
      const allLabel = document.createElement("label");
      const columnName = getColumnName(columnIndex);
      allLabel.innerHTML = `
            <input type="checkbox" class="filter-option" 
                   data-column="${columnIndex}" value=""> All ${columnName}
        `;
      dropdown.appendChild(allLabel);

      // Get the appropriate values for this column
      let values = [];

      // Use the specific population functions for each column type
      switch (columnIndex) {
        case 1: // Customer Name
          values = Array.from(uniqueValues["customerName"] || []);
          break;
        case 2: // Builder Name
          values = Array.from(uniqueValues["builderName"] || []);
          break;
        case 3: // Project Name
          values = Array.from(uniqueValues["projectName"] || []);
          break;
        case 4: // Contact Number
          values = Array.from(uniqueValues["contactNumber"] || []);
          break;
        case 5: // Email
          values = Array.from(uniqueValues["email"] || []);
          break;
        case 6: // Project Type
          values = Array.from(uniqueValues["projectType"] || []);
          break;
        case 7: // Booking Date
          values = Array.from(uniqueValues["bookingDate"] || []);
          break;
        case 8: // Booking Month
          values = Array.from(uniqueValues["bookingMonth"] || []);
          break;
        default:
          // For other columns, get values directly
          values = Array.from(uniqueValues[Object.keys(uniqueValues)[columnIndex]] || []);
      }

      // Sort values alphabetically
      values.sort((a, b) => a.toString().localeCompare(b.toString()));

      // Add options for each value
      values.forEach((value) => {
        if (!value || value.trim() === "") return;

        const isChecked = currentlyChecked.has(value.trim());
        const label = document.createElement("label");
        label.innerHTML = `
                <input type="checkbox" class="filter-option" 
                       data-column="${columnIndex}" 
                       value="${escapeHtml(value.trim())}"
                       ${isChecked ? "checked" : ""}>
                ${escapeHtml(value.trim())}
            `;
        dropdown.appendChild(label);
      });

      // If nothing was checked before, check "All"
      if (currentlyChecked.size === 0) {
        $(dropdown).find('.filter-option[value=""]').prop("checked", true);
      }
    });
  }


  // Helper function to get column name
  function getColumnName(columnIndex) {
    const columnNames = {
      0: "IDs",
      1: "Customers",
      2: "Builders",
      3: "Projects",
      5: "Emails",
      6: "Types",
      7: "Dates",
      8: "Months",
    };
    return columnNames[columnIndex] || "";
  }

  // Customer name filter population (special case)
  function populateCustomerNameFilter(dropdown) {
    // Get currently checked values first
    const currentlyChecked = [];
    $(dropdown)
      .find(".filter-option:checked")
      .each(function () {
        if ($(this).val() !== "") {
          currentlyChecked.push($(this).val());
        }
      });

    // Clear and recreate options
    dropdown.innerHTML = "";

    // Add "All Customers" option
    const allLabel = document.createElement("label");
    allLabel.innerHTML = `
        <input type="checkbox" class="filter-option" 
               data-column="1" value=""> All Customers
    `;
    dropdown.appendChild(allLabel);

    // Get unique customer names from DataTable
    const customerNames = new Set();
    eoiTable.rows({ search: "applied" }).every(function () {
      const data = this.data();
      if (data && data.customerName) {
        const customerName = data.customerName.trim();
        if (customerName) {
          customerNames.add(customerName);
        }
      }
    });

    // Add customer options
    Array.from(customerNames)
      .sort()
      .forEach((customerName) => {
        const isChecked = currentlyChecked.includes(customerName);

        const label = document.createElement("label");
        label.innerHTML = `
            <input type="checkbox" class="filter-option" 
                   data-column="1" value="${escapeHtml(customerName)}"
                   ${isChecked ? "checked" : ""}>
            ${escapeHtml(customerName)}
        `;
        dropdown.appendChild(label);
      });

    // If nothing was checked before, check "All Customers"
    if (currentlyChecked.length === 0) {
      $(dropdown).find('.filter-option[value=""]').prop("checked", true);
    }
  }

  // Generic filter population for other columns
  function populateGenericFilter(dropdown, columnIndex) {
    // Get currently checked values first
    const currentlyChecked = [];
    $(dropdown)
      .find(".filter-option:checked")
      .each(function () {
        if ($(this).val() !== "") {
          currentlyChecked.push($(this).val());
        }
      });

    // Clear and recreate options
    const allOption = dropdown.querySelector('.filter-option[value=""]');
    dropdown.innerHTML = "";

    // Add "All" option
    const allLabel = document.createElement("label");
    allLabel.innerHTML = `
        <input type="checkbox" class="filter-option" 
               data-column="${columnIndex}" value=""> All
    `;
    dropdown.appendChild(allLabel);

    // Get unique values for the column
    const uniqueValues = getUniqueColumnValues(columnIndex);

    // Add options for each unique value
    uniqueValues.forEach((value) => {
      if (!value || value.trim() === "") return;

      const isChecked = currentlyChecked.includes(value.trim());

      const label = document.createElement("label");
      label.innerHTML = `
            <input type="checkbox" class="filter-option" 
                   data-column="${columnIndex}" value="${escapeHtml(
        value.trim()
      )}"
                   ${isChecked ? "checked" : ""}>
            ${escapeHtml(value.trim())}
        `;
      dropdown.appendChild(label);
    });

    // If nothing was checked before, check "All"
    if (currentlyChecked.length === 0) {
      $(dropdown).find('.filter-option[value=""]').prop("checked", true);
    }
  }

  // Add this event listener to handle the cancel button click
  $(document).on("click", "#closeEditEOIModalBtn", function () {
    // Close the modal
    $("#editEOIModal").fadeOut(200);

    // Reset the entire form
    $("#edit-eoi-form")[0].reset();

    // Uncheck the converted checkbox
    $("#toggleFields1").prop("checked", false);

    // Hide the additional fields section
    $("#additional-fields1").hide();

    // Remove required attribute from additional fields
    $("#additional-fields1 input[required]").prop("required", false);

    // Enable the update button
    $("#edit-eoi-btn").prop("disabled", false);

    // Reset attachment preview UI
    resetEoiAttachmentUI();
  });

  // Also add the same functionality to the close button (X)
  $(document).on("click", "#closeEditEOIModal", function () {
    // Close the modal
    $("#editEOIModal").fadeOut(200);

    // Reset the entire form
    $("#edit-eoi-form")[0].reset();

    // Uncheck the converted checkbox
    $("#toggleFields1").prop("checked", false);

    // Hide the additional fields section
    $("#additional-fields1").hide();

    // Remove required attribute from additional fields
    $("#additional-fields1 input[required]").prop("required", false);

    // Enable the update button
    $("#edit-eoi-btn").prop("disabled", false);

    // Reset attachment preview UI
    resetEoiAttachmentUI();
  });

  // Add this event listener for the cancel button in the Add EOI modal
  $(document).on("click", "#closeEOIModalBtn", function () {
    // Close the modal
    $("#addNewEOIModal").fadeOut(200);

    // Reset the entire form
    $("#add-eoi-form")[0].reset();
  });

  // Also add the same functionality to the close button (X) in Add EOI modal
  $(document).on("click", "#closeEOIModal", function () {
    // Close the modal
    $("#addNewEOIModal").fadeOut(200);

    // Reset the entire form
    $("#add-eoi-form")[0].reset();
  });

  // Update the existing event listener for clicking outside the modal to also reset the form
  $(document).on("click", ".modal-overlay", function (e) {
    if ($(e.target).hasClass("modal-overlay")) {
      $("#addNewEOIModal").fadeOut(200);
      $("#add-eoi-form")[0].reset(); // Reset form when clicking outside
    }
  });

  // Helper function to get unique values from a column
  function getUniqueColumnValues(columnIndex) {
    const values = new Set();

    // Get values from all rows
    eoiTable.rows({ search: "applied" }).every(function () {
      const data = this.data();
      let value;

      // Handle different column data structures
      if (typeof data === "object") {
        // If using objects with named properties
        const columnKeys = Object.keys(data);
        if (columnIndex < columnKeys.length) {
          value = data[columnKeys[columnIndex]];
        }
      } else if (Array.isArray(data)) {
        // If using arrays
        value = data[columnIndex];
      }

      // Clean and add the value
      if (value !== undefined && value !== null) {
        // Handle different value types
        if (typeof value === "string") {
          value = value.trim();
          if (value) values.add(value);
        } else if (typeof value === "number") {
          values.add(value.toString());
        } else if (value.textContent) {
          // For DOM elements (if your DataTable uses rendered cells)
          values.add(value.textContent.trim());
        }
      }
    });

    return Array.from(values).sort();
  }

  // Helper function to escape HTML (for safe value insertion)
  function escapeHtml(unsafe) {
    return unsafe
      .toString()
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function createDetailsContent(row) {
    const hiddenColumns = [];
    const headers = $("#eoiTable thead th");
    const excludedColumns = [0, 5, 6, 7, 10, 11]; // Exclude ID, expand button, and customer name columns

    // For mobile view, use DataTable row data directly
    if (window.innerWidth <= 900) {
      let rowData;
      if (
        typeof eoiTable !== "undefined" &&
        row instanceof jQuery &&
        row.length
      ) {
        rowData = eoiTable.row(row).data();
      }

      if (rowData) {
        const eoiId = rowData.id || "N/A";
        const bookingDate = rowData.bookingDate
          ? formatDate(rowData.bookingDate)
          : "N/A";
        const bookingMonth = rowData.bookingMonth || "N/A";
        const builderName = rowData.builderName || "N/A";
        const projectName = rowData.projectName || "N/A";
        const customerName = rowData.customerName || "N/A";
        const contactNumber = rowData.contactNumber || "N/A";
        const email = rowData.email || "N/A";
        const projectType = rowData.projectType || "N/A";

        return `
                <div class="details-block details-block-left">
                    <div class="mobile-details-section">
                        <h4>EOI Details</h4>
                        
                        
                        <div class="flexxx">
                            <strong>Builder: &nbsp;</strong>
                            <div class="text-toggle detail-row-text">${builderName}</div>
                        </div>
                        <div class="flexxx">
                            <strong>Project: &nbsp;</strong>
                            <div class="text-toggle detail-row-text">${projectName}</div>
                        </div>
                        <div class="flexxx">
                            <strong>Project Type: &nbsp;</strong>
                            <div class="text-toggle detail-row-text">${projectType}</div>
                        </div>
                        <div class="flexxx">
                            <strong>Email: &nbsp;</strong>
                            <div class="text-toggle detail-row-text">${email}</div>
                        </div>
                        
                    </div>
                </div>
                <div class="details-block details-block-right">
        <h4>Actions</h4>
        <div class="action-buttons-leads leads-complete-mobile mobile">
            
                <div class="action-buttons-eoi mobile">
                <button class="action-btn-eoi complete-btn" 
                        data-row='${JSON.stringify(rowData)}'>
                    Complete <i class="fas fa-check"></i>
                </button>
            </div>
            </div>
                `;
      }
    }

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
      let detailsHTML = `
            <div class="details-block details-block-left">
                <div class="desktop-details-section">
                    <h4>EOI Details</h4>`;

      hiddenColumns.forEach((column) => {
        detailsHTML += `
                    <div class="flexxx">
                        <strong>${column.header}: &nbsp;</strong>
                        <div class="detail-row-text">${column.content}</div>
                    </div>
                `;
      });

      detailsHTML += `
                </div>
            </div>
            <div class="details-block details-block-right">
                <h4>Actions</h4>
                <div class="action-buttons-eoi">
                    <button class="action-btn-eoi complete-btn update-button update-status-btn call tooltip" data-tooltip="Call">Complete <i class="fas fa-check"></i></button>
                </div>
            </div>`;

      return detailsHTML;
    }
    return "";
  }

  function initMobileButtons() {
    $(document).on("click", ".assign-btn-mobile", function () {
      $(".assign-users-btn").click();
    });

    $(document).on("click", ".delete-btn-mobile", function () {
      $(".delete-selected-btn").click();
    });

    $(document).on("click", ".columns-btn-mobile", function (e) {
      e.preventDefault();
      e.stopPropagation();
      openColumnsModal(eoiTable, this);
    });
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
  function initDateRangeFilter() {
    // Ensure only one base date filter exists
    if (window.eoiDateRangeFilter) {
      $.fn.dataTable.ext.search = $.fn.dataTable.ext.search.filter(
        (fn) => fn !== window.eoiDateRangeFilter
      );
    }

    // Custom search function for date range filtering
    window.eoiDateRangeFilter = function eoiDateRangeFilter(settings, data, dataIndex) {
      const fromDate = $("#dateFrom").val();
      const toDate = $("#dateTo").val();

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
    };

    $.fn.dataTable.ext.search.push(window.eoiDateRangeFilter);

    // Event listeners for date inputs
    $("#dateFrom, #dateTo").on("change", function () {
      eoiTable.draw();
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

    // Open modal when calendar button is clicked
    document
      .getElementById("openDateRangePicker")
      .addEventListener("click", function () {
        // Set current values in modal
        document.getElementById("modalDateFrom").value =
          document.getElementById("dateFrom").value;
        document.getElementById("modalDateTo").value =
          document.getElementById("dateTo").value;

        datePickerModal.style.display = "flex";
      });

    datePickerModal
      .querySelector(".date-picker-btn.reset")
      .addEventListener("click", function () {
        // Clear the date inputs in the modal
        document.getElementById("modalDateFrom").value = "";
        document.getElementById("modalDateTo").value = "";

        // Also clear the hidden inputs that store the filter values
        document.getElementById("dateFrom").value = "";
        document.getElementById("dateTo").value = "";

        // Trigger the table filtering (which will show all dates since filters are cleared)
        eoiTable.draw();

        // // Close the modal
        // datePickerModal.style.display = 'none';
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

    // Apply date range
    datePickerModal
      .querySelector(".date-picker-btn.apply")
      .addEventListener("click", function () {
        const fromDate = document.getElementById("modalDateFrom").value;
        const toDate = document.getElementById("modalDateTo").value;

        // Update the hidden inputs
        document.getElementById("dateFrom").value = fromDate;
        document.getElementById("dateTo").value = toDate;

        // Trigger the table filtering
        eoiTable.draw();

        datePickerModal.style.display = "none";
      });

    // Close modal when clicking outside
    datePickerModal.addEventListener("click", function (e) {
      if (e.target === datePickerModal) {
        datePickerModal.style.display = "none";
      }
    });
  }





  // Your existing event handlers...
  function initExpandButtons() {
    // Remove any existing handlers first to prevent duplicates
    $(document).off("click", ".expand-eoi");

    $(document).on("click", ".expand-eoi", function (e) {
      e.stopPropagation();
      e.preventDefault();

      const $btn = $(this);
      const $row = $btn.closest("tr");
      const $detailsRow = $row.next(".details-row");

      // Close any previously expanded row
      if (currentExpandedRow && currentExpandedRow !== $row[0]) {
        const $prevRow = $(currentExpandedRow);
        const $prevDetails = $prevRow.next(".details-row");
        const $prevBtn = $prevRow.find(".expand-eoi");

        $prevDetails.hide();
        $prevBtn.find(".down-arrow").show();
        $prevBtn.find(".up-arrow").hide();
        $prevRow.removeClass("highlight-row");
      }

      // Toggle current row
      if ($detailsRow.is(":visible")) {
        $detailsRow.hide();
        $btn.find(".down-arrow").show();
        $btn.find(".up-arrow").hide();
        $row.removeClass("highlight-row");
      } else {
        // Ensure details content is up to date
        const detailsContent = createDetailsContent($row);
        $detailsRow.find(".details-content").html(detailsContent);

        $detailsRow.show();
        $btn.find(".down-arrow").hide();
        $btn.find(".up-arrow").show();
        $row.addClass("highlight-row");
      }

      // Update tracking variable
      currentExpandedRow = $detailsRow.is(":visible") ? $row[0] : null;
    });
  }

  $(document).on("click", "#eoiTable tbody tr", function (e) {
    // Skip if clicking on action buttons, dropdowns, checkboxes, or expand button
    if (
      $(e.target).closest(
        '.action-btn, .filter-dropdown, .filter-header-btn, a, input[type="checkbox"]'
      ).length
    ) {
      return;
    }

    // Skip if clicking on details rows
    if ($(this).hasClass("details-row")) {
      return;
    }

    const $currentRow = $(this);
    const $expandBtn = $currentRow.find(".expand-eoi");

    // Trigger the expand button click
    $expandBtn.trigger("click");
  });

  function addCalculate(formId) {
    // Get input values with fallback to 0 if empty/invalid
    var agreementValue =
      parseFloat(document.getElementById("cagreement-" + formId).value) || 0;
    var commissionPercentage =
      parseFloat(document.getElementById("ccashback-" + formId).value) || 0;
    var cashbackPercentage =
      parseFloat(document.getElementById("cccashback-" + formId).value) || 0;

    // Calculate Revenue Amount (Commission % of Agreement Value)
    var revenueAmount = agreementValue * (commissionPercentage / 100);
    document.getElementById("crevenue-" + formId).value =
      revenueAmount.toFixed(2);

    // Calculate Final Revenue After Cashback Deduction
    var revenueAfterCashback =
      revenueAmount - agreementValue * (cashbackPercentage / 100);
    document.getElementById("ccrevenue-" + formId).value =
      revenueAfterCashback.toFixed(2);
  }

  function formatFileSize(bytes) {
    if (!bytes || bytes < 1024) return (bytes || 0) + " B";
    const kb = bytes / 1024;
    if (kb < 1024) return kb.toFixed(kb < 10 ? 1 : 0) + " KB";
    const mb = kb / 1024;
    return mb.toFixed(mb < 10 ? 1 : 0) + " MB";
  }

  function resetEoiAttachmentUI() {
    const uploader = document.getElementById("eoiUploader");
    const prompt = document.getElementById("eoiUploadPrompt");
    const list = document.getElementById("eoiFileList");
    if (uploader) uploader.classList.remove("has-files");
    if (prompt) prompt.style.display = "flex";
    if (list) list.innerHTML = "";
  }

  function renderEoiAttachmentUI() {
    const fileInput = document.getElementById("eoiFileInput");
    const uploader = document.getElementById("eoiUploader");
    const prompt = document.getElementById("eoiUploadPrompt");
    const list = document.getElementById("eoiFileList");

    if (!fileInput || !uploader || !prompt || !list) return;

    list.innerHTML = "";
    const file = fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;

    if (!file) {
      resetEoiAttachmentUI();
      return;
    }

    uploader.classList.add("has-files");
    prompt.style.display = "none";

    const row = document.createElement("div");
    row.className = "file-item";
    row.innerHTML = `
      <div class="file-meta">
        <div class="name">${file.name}</div>
        <div class="sub">${formatFileSize(file.size)}</div>
      </div>
      <button type="button" class="remove" id="removeEoiFile" aria-label="Remove file" title="Remove file">✕</button>
    `;
    list.appendChild(row);
  }

  function bindEoiUploader() {
    const fileInput = document.getElementById("eoiFileInput");
    const browseBtn = document.getElementById("eoiBrowseBtn");
    const uploader = document.getElementById("eoiUploader");

    if (!fileInput || !browseBtn || !uploader) return;

    browseBtn.addEventListener("click", function () {
      fileInput.click();
    });

    fileInput.addEventListener("change", function () {
      const file = this.files && this.files[0] ? this.files[0] : null;
      if (file && file.type !== "application/pdf") {
        showNotification("Only PDF files are allowed", "error");
        this.value = "";
      }
      renderEoiAttachmentUI();
    });

    ["dragenter", "dragover"].forEach((evt) => {
      uploader.addEventListener(evt, function (e) {
        e.preventDefault();
        uploader.classList.add("dragover");
      });
    });

    ["dragleave", "drop"].forEach((evt) => {
      uploader.addEventListener(evt, function (e) {
        e.preventDefault();
        uploader.classList.remove("dragover");
      });
    });

    uploader.addEventListener("drop", function (e) {
      const dropped = e.dataTransfer && e.dataTransfer.files ? e.dataTransfer.files[0] : null;
      if (!dropped) return;
      if (dropped.type !== "application/pdf") {
        showNotification("Only PDF files are allowed", "error");
        return;
      }

      const dt = new DataTransfer();
      dt.items.add(dropped);
      fileInput.files = dt.files;
      renderEoiAttachmentUI();
    });

    document.addEventListener("click", function (e) {
      if (e.target && e.target.id === "removeEoiFile") {
        fileInput.value = "";
        renderEoiAttachmentUI();
      }
    });
  }

  bindEoiUploader();
  $(document).on(
    "keyup",
    "#cagreement-2, #ccashback-2, #cccashback-2",
    function () {
      addCalculate(2); // Use formId 2 for edit modal
    }
  );

  $(document).on("input", "#cagreement-2, #cagreement-confirm-2", function () {
    const $confirm = $("#cagreement-confirm-2");
    if (!$confirm.length) return;

    const confirmRaw = $confirm.val().trim();
    if (!confirmRaw) {
      $confirm.removeClass("is-invalid");
      $confirm[0].setCustomValidity("");
      return;
    }

    const agreementRaw = $("#cagreement-2").val().trim();
    const normalizeNumber = (value) => value.replace(/[,\s]/g, "");
    const agreementNorm = normalizeNumber(agreementRaw);
    const confirmNorm = normalizeNumber(confirmRaw);
    const agreementValue = parseFloat(agreementNorm);
    const confirmValue = parseFloat(confirmNorm);
    const matches = Number.isFinite(agreementValue) && Number.isFinite(confirmValue)
      ? agreementValue === confirmValue
      : agreementNorm === confirmNorm;

    if (matches) {
      $confirm.removeClass("is-invalid");
      $confirm[0].setCustomValidity("");
    } else {
      $confirm.addClass("is-invalid");
      $confirm[0].setCustomValidity("Agreement values do not match");
    }
  });



  // Event listener for dynamically loaded Complete buttons
  $(document).on("click", ".complete-btn", function () {
    // Get the row data from the button's data attribute
    const rowData = $(this).data("row");

    // Populate the modal form fields
    $('#editEOIModal input[name="id"]').val(rowData.id);
    $('#editEOIModal input[name="bdate"]').val(rowData.bookingDate);
    const normalizedBookingMonth =
      toYearMonth(rowData.bookingDate) ||
      toYearMonth(rowData.bookingMonth) ||
      rowData.bookingMonth || "";
    $('#editEOIModal input[name="bmonth"]').val(normalizedBookingMonth);
    $('#editEOIModal input[name="developer"]').val(rowData.builderName);
    $('#editEOIModal input[name="bproject"]').val(rowData.projectName);
    $('#editEOIModal input[name="cname"]').val(rowData.customerName);
    $('#editEOIModal input[name="cnumber"]').val(rowData.contactNumber);
    $('#editEOIModal input[name="cemail"]').val(rowData.email);
    $('#editEOIModal input[name="tproject"]').val(rowData.projectType);
    $("#cityNameEoi").val(rowData.city || "");
    $("#leadSourceEoi").val(rowData.leadSource || "");
    $("#remarksEoi").val(rowData.remarks || "");
    resetEoiAttachmentUI();
    $("#cagreement-confirm-2").val("");

    // Check if this is a converted booking
    if (rowData.converted) {
      $("#toggleFields1").prop("checked", true).trigger("change");

      // Populate calculation fields
      $("#cagreement-2").val(rowData.agreementValue);
      $("#ccashback-2").val(rowData.commissionPercentage);
      $("#crevenue-2").val(rowData.revenueAmount);
      $("#cccashback-2").val(rowData.cashbackPercentage);
      $("#ccrevenue-2").val(rowData.cashbackRevenue);

      // Trigger calculation to ensure consistency
      addCalculate(2);
    }

    // Check if booking is canceled
    if (rowData.canceled) {
      $("#canceleoi").prop("checked", true);
    }

    // Show the modal
    $("#editEOIModal").fadeIn(200);
  });
  // Toggle additional fields based on 'Converted' checkbox in edit form
  $("#toggleFields").change(function () {
    $("#additional-fields").toggle(this.checked);
  });

  $("#toggleFields1").change(function () {
    const isChecked = $(this).is(":checked");
    $("#additional-fields1").toggle(isChecked);

    // Toggle required attribute based on checkbox state
    $("#additional-fields1 input[required], #additional-fields1 select[required]").prop("required", isChecked);

    validateConvertedFields();
  });



  // Remove the existing event listeners and replace with this:
  convertedCheckbox.addEventListener("change", function () {
    if (this.checked) {
      // Uncheck cancel when converted is selected
      document.getElementById("canceleoi").checked = false;
    }
    checkAndToggleFields();
  });

  // Update the cancel checkbox handler
  document.getElementById("canceleoi").addEventListener("change", function () {
    if (this.checked) {
      // Uncheck converted when cancel is selected
      convertedCheckbox.checked = false;
    }
    checkAndToggleFields();
  });
  $("#edit-eoi-form").submit(async function (event) {
    event.preventDefault();

    // If cancel is checked, don't require converted fields
    if ($("#canceleoi").is(":checked")) {
      $("#additional-fields1 input[required], #additional-fields1 select[required]").prop("required", false);
    }

    // Remove required attribute from hidden fields before validation
    if (!$("#toggleFields1").is(":checked")) {
      $("#additional-fields1 input[required], #additional-fields1 select[required]").prop("required", false);
    }

    // Manually validate visible required fields
    let isValid = true;
    $("#edit-eoi-form input:visible[required], #edit-eoi-form select:visible[required]").each(function () {
      if (!$(this).val().trim()) {
        isValid = false;
        $(this).addClass("is-invalid");
        return false; // break out of loop
      } else {
        $(this).removeClass("is-invalid");
      }
    });

    if (!isValid) {
      showNotification("Please fill all required fields", "error");
      return;
    }

    if ($("#toggleFields1").is(":checked")) {
      const agreementRaw = $("#cagreement-2").val().trim();
      const confirmRaw = $("#cagreement-confirm-2").val().trim();
      const normalizeNumber = (value) => value.replace(/[,\s]/g, "");
      const agreementNorm = normalizeNumber(agreementRaw);
      const confirmNorm = normalizeNumber(confirmRaw);
      const agreementValue = parseFloat(agreementNorm);
      const confirmValue = parseFloat(confirmNorm);
      const matches = Number.isFinite(agreementValue) && Number.isFinite(confirmValue)
        ? agreementValue === confirmValue
        : agreementNorm === confirmNorm;

      if (!matches) {
        const $confirm = $("#cagreement-confirm-2");
        $confirm.addClass("is-invalid").focus();
        if ($confirm.length) {
          $confirm[0].setCustomValidity("Agreement values do not match");
        }
        showNotification("Agreement values do not match", "error");
        return;
      }

      const $confirm = $("#cagreement-confirm-2");
      $confirm.removeClass("is-invalid");
      if ($confirm.length) {
        $confirm[0].setCustomValidity("");
      }
    }

    const $submitBtn = $("#edit-eoi-btn");
    const originalText = $submitBtn.html();

    // If converted is checked, validate unit number uniqueness BEFORE submitting
    if ($("#toggleFields1").is(":checked")) {
      const unitNo = $("#unitno").val().trim();

      if (!unitNo) {
        showNotification("Please enter a unit number", "error");
        return;
      }

      // Set loading state while checking
      $submitBtn.prop("disabled", true);
      $submitBtn.html("Checking unit...");
      $submitBtn.addClass("button-loading");

      try {
        // Check if unit number already exists
        const response = await fetch(`eoiaction.php?action=check_unit&unitno=${encodeURIComponent(unitNo)}`);
        const data = await response.json();

        if (data.exists) {
          // Unit number already exists - block form submission
          showNotification(data.message, "error");
          $submitBtn.prop("disabled", false);
          $submitBtn.html(originalText);
          $submitBtn.removeClass("button-loading");

          // Highlight the unit number field
          $("#unitno").addClass("is-invalid").focus();
          return; // STOP - do not submit the form
        }

        // Remove invalid class if unit is available
        $("#unitno").removeClass("is-invalid");

      } catch (error) {
        console.error("Error checking unit number:", error);
        showNotification("Error checking unit number. Please try again.", "error");
        $submitBtn.prop("disabled", false);
        $submitBtn.html(originalText);
        $submitBtn.removeClass("button-loading");
        return;
      }
    }

    // Set loading state
    $submitBtn.prop("disabled", true);
    $submitBtn.html("Updating...");
    $submitBtn.addClass("button-loading");

    // Ensure calculations are up-to-date before submission if converted
    if ($("#toggleFields1").is(":checked")) {
      addCalculate(2);
    }

    const formData = new FormData(this);

    // Determine the action based on checkboxes
    if ($("#toggleFields1").is(":checked")) {
      formData.append("action", "convert");
    } else if ($("#canceleoi").is(":checked")) {
      formData.append("action", "cancel");
    } else {
      formData.append("action", "update");
    }

    $.ajax({
      url: "eoiaction.php",
      type: "POST",
      data: formData,
      processData: false,
      contentType: false,
      success: function (response) {
        // Check if response contains an error message
        if (response.toLowerCase().includes("error:")) {
          showNotification(response, "error");
          return;
        }

        // Show appropriate success message
        if ($("#toggleFields1").is(":checked")) {
          showNotification(
            "Record moved to admin table successfully!",
            "success"
          );
        } else if ($("#canceleoi").is(":checked")) {
          showNotification("EOI canceled successfully!", "success");
        } else {
          showNotification("EOI updated successfully!", "success");
        }

        // Hide modal and reset form
        $("#editEOIModal").fadeOut(200);
        $("#edit-eoi-form")[0].reset();
        $("#toggleFields1").prop("checked", false).trigger("change");
        $("#canceleoi").prop("checked", false);
        $("#additional-fields1").hide();
        resetEoiAttachmentUI();

        // Reload DataTable
        if ($.fn.DataTable.isDataTable("#eoiTable")) {
          $("#eoiTable").DataTable().ajax.reload(null, false);
        }

        // Refresh hierarchy counts
        refreshHierarchyCounts();
      },
      error: function (xhr, status, error) {
        console.error("Error: " + error);
        showNotification("Error occurred while updating data.", "error");
      },
      complete: function () {
        // Reset button state
        $submitBtn.prop("disabled", false);
        $submitBtn.html(originalText);
        $submitBtn.removeClass("button-loading");
      },
    });
  });

  $(document).on("click", ".filter-btn:not(.hierarchy-user-btn)", function () {
    $("#filterModal1").fadeIn(200); // fades in and sets display to block
  });
  $(document).ready(function () {
    $("#openFilterModal1").on("click", function () {
      $("#filterModal1").show();
    });
    // Apply filters
    $("#filterForm1").on("submit", function (e) {
      e.preventDefault();
      eoiTable.ajax.reload(function () {
        initExpandButtons();
        handleResponsiveBehavior();
      }, false);
      $("#filterModal1").hide();
    });

    // Reset filters
    $("#isolatedClearFiltersBtn1").on("click", function () {
      $("#filterForm1")[0].reset();
      eoiTable.ajax.reload(function () {
        initExpandButtons();
        handleResponsiveBehavior();
      }, false);
    });

    // Close modal (optional)
    $("#closeFilterModal1").on("click", function () {
      $("#filterModal1").hide();
    });

    $(document).on("click", ".add-btn-mobile", function () {
      $("#addNewEOIModal").fadeIn(200); // fades in and sets display to block
    });

    // Close modal when clicking the close button
    $(document).on("click", ".action-btn-eoi", function () {
      $("#editEOIModal").fadeIn(200); // fades in and sets display to block
    });

    // Optional: Close modal on clicking the close button
    $(document).on("click", "#closeEditEOIModal", function () {
      $("#editEOIModal").fadeOut(200); // fades out and sets display to none
    });

    $(document).on(
      "click",
      ".floating-add-btn .action-button:not(.assign-users-btn):not(.delete-selected-btn)",
      function () {
        $("#addNewEOIModal").fadeIn(200);
      }
    );

    // Close modal when clicking the close button
    $(document).on("click", "#closeEOIModal", function () {
      $("#addNewEOIModal").fadeOut(200);
    });

    // Close modal when clicking outside the modal
    $(document).on("click", ".modal-overlay", function (e) {
      if ($(e.target).hasClass("modal-overlay")) {
        $("#addNewEOIModal").fadeOut(200);
      }
    });
    $(document).on("click", ".filter-close-btn", function (e) {
      e.stopPropagation();
      $(this).closest(".filter-dropdown").hide();
    });

    // Validation function for converted fields
    function validateConvertedFields() {
      const requiredInputs = $("#additional-fields1").find("input[required], select[required]");
      let allFilled = true;

      requiredInputs.each(function () {
        if (!$(this).val().trim()) {
          allFilled = false;
          return false; // break the loop
        }
      });

      $("#edit-eoi-btn").prop("disabled", !allFilled);
    }
    // Monitor input fields in additional fields section
    $(document).on(
      "input change",
      "#additional-fields1 input[required], #additional-fields1 select[required]",
      validateConvertedFields
    );

    // Submit form for adding EOI
    // Submit form for adding EOI - Updated version
    $("#add-eoi-form").submit(function (event) {
      event.preventDefault();
      const $submitBtn = $("#add-eoi-btn");
      const originalText = $submitBtn.html();
      const normalizedMonth = toYearMonth($("#bmonth").val());
      if (normalizedMonth) {
        $("#bmonth").val(normalizedMonth);
      }
      // Set loading state
      $submitBtn.prop("disabled", true);
      $submitBtn.html("Adding...");
      $submitBtn.addClass("button-loading");

      $.ajax({
        url: "eoiaction.php",
        type: "POST",
        data: $(this).serialize(),
        success: function (response) {
          showNotification(`EOI Added successfully!`, "success");
          $("#add-eoi-form")[0].reset();
          $("#addNewEOIModal").fadeOut(200);

          // Reload DataTable and reinitialize expand buttons
          if ($.fn.DataTable.isDataTable("#eoiTable")) {
            eoiTable.ajax.reload(function () {
              initExpandButtons(); // Reinitialize expand buttons
              handleResponsiveBehavior(); // Reinitialize responsive behavior
            }, false);
          }

          // Refresh hierarchy counts
          refreshHierarchyCounts();
        },
        error: function (xhr, status, error) {
          console.error("Error: " + error);
          showNotification("Error occurred while submitting data.", "error");
        },
        complete: function () {
          $submitBtn.prop("disabled", false);
          $submitBtn.html(originalText);
          $submitBtn.removeClass("button-loading");
        },
      });
    });
  });

  $("#filterForm1").on("submit", function (e) {
    e.preventDefault();

    // Clear any existing filters first
    $.fn.dataTable.ext.search = $.fn.dataTable.ext.search.filter(
      (fn) => fn === window.eoiDateRangeFilter
    );

    // Get all filter values
    const startDate = $("#isolatedFilterStartDate1").val();
    const endDate = $("#isolatedFilterEndDate1").val();
    const customerName = $("#isolatedFilterCustumername1").val().toLowerCase();
    const email = $("#isolatedFilterEmail1").val().toLowerCase();
    const contactNumber = $("#isolatedFilterContactnumber1")
      .val()
      .toLowerCase();
    const builderName = $("#isolatedFilterBuilderName").val().toLowerCase();
    const projectType = $("#isolatedFilterProjectType").val().toLowerCase();
    const projectName = $("#isolatedFilterProjectName").val().toLowerCase();

    // Create a custom filter function
    $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
      const rowData = eoiTable.row(dataIndex).data();

      // Date range filter
      if (startDate || endDate) {
        const rowDate = new Date(rowData.bookingDate);
        const fromDate = startDate ? new Date(startDate) : null;
        const toDate = endDate ? new Date(endDate) : null;

        if (fromDate && rowDate < fromDate) return false;
        if (toDate && rowDate > toDate) return false;
      }

      // Customer name filter
      if (
        customerName &&
        !rowData.customerName.toLowerCase().includes(customerName)
      ) {
        return false;
      }

      // Email filter
      if (
        email &&
        (!rowData.email || !rowData.email.toLowerCase().includes(email))
      ) {
        return false;
      }

      // Contact number filter
      if (
        contactNumber &&
        (!rowData.contactNumber ||
          !rowData.contactNumber.toLowerCase().includes(contactNumber))
      ) {
        return false;
      }

      // Builder name filter
      if (
        builderName &&
        (!rowData.builderName ||
          !rowData.builderName.toLowerCase().includes(builderName))
      ) {
        return false;
      }

      // Project type filter
      if (
        projectType &&
        projectType !== "" &&
        (!rowData.projectType ||
          rowData.projectType.toLowerCase() !== projectType)
      ) {
        return false;
      }

      // Project name filter
      if (
        projectName &&
        (!rowData.projectName ||
          !rowData.projectName.toLowerCase().includes(projectName))
      ) {
        return false;
      }

      // If all filters pass, include the row
      return true;
    });

    // Apply the filters
    eoiTable.draw();

    // Close the modal
    $("#filterModal1").hide();
  });

  // Floating clear-all-filters button (mirrors Leads page behavior)
  function initEoiClearFiltersButton() {
    if (!$("#clearAllFiltersBtn").length) {
      $("body").append(`
        <button class="clear-filters-btn" id="clearAllFiltersBtn">
          <i class="fas fa-times-circle"></i>
          Clear All Filters
        </button>
      `);
    }

    const $clearBtn = $("#clearAllFiltersBtn");

    function resetHeaderDropdowns() {
      $(".filter-header").each(function () {
        const $dropdown = $(this).find(".filter-dropdown, .filter-options");
        const $allOption = $dropdown.find('.filter-option[value=""]');
        if ($allOption.length) {
          $dropdown.find('.filter-option').prop('checked', false);
          $allOption.prop('checked', true);
        }
      });
    }

    function keepBaseDateFilterOnly() {
      if (window.eoiDateRangeFilter) {
        $.fn.dataTable.ext.search = $.fn.dataTable.ext.search.filter(
          (fn) => fn === window.eoiDateRangeFilter
        );
      } else {
        $.fn.dataTable.ext.search.length = 0;
      }
    }

    function clearAllEoiFilters() {
      // Clear isolated filter inputs
      const isolatedSelectors = [
        '#isolatedFilterCustumername1',
        '#isolatedFilterEmail1',
        '#isolatedFilterContactnumber1',
        '#isolatedFilterBuilderName',
        '#isolatedFilterProjectType',
        '#isolatedFilterProjectName',
        '#isolatedFilterStartDate1',
        '#isolatedFilterEndDate1'
      ];
      isolatedSelectors.forEach((selector) => $(selector).val(""));

      // Clear date range inputs (desktop and modal)
      $('#dateFrom, #dateTo, #modalDateFrom, #modalDateTo').val('');

      // Reset filter form if present
      if ($("#filterForm1")[0]) {
        $("#filterForm1")[0].reset();
      }

      // Reset dropdown checkboxes to "All"
      resetHeaderDropdowns();

      // Remove custom search filters but keep base date filter
      if (!window.eoiDateRangeFilter && typeof initDateRangeFilter === 'function') {
        initDateRangeFilter();
      }
      keepBaseDateFilterOnly();

      // Clear DataTable searches
      if (eoiTable) {
        eoiTable.search('');
        eoiTable.columns().search('');
        eoiTable.draw();
      }

      // Reset hierarchy/user selection to All if applicable
      if (typeof currentSelectedUser !== 'undefined' && currentSelectedUser !== 'all') {
        const allBtn = document.querySelector('#eoiHierarchyContainer button[data-user="all"]');
        if (allBtn && typeof switchToUser === 'function') {
          switchToUser('all', allBtn);
        }
      }

      // Drop dashboard URL params
      const url = new URL(window.location);
      ['start_date', 'end_date', 'filterUser', 'managerView'].forEach((param) => url.searchParams.delete(param));
      window.history.replaceState({}, document.title, url.pathname);

      // Close modal if open
      $("#filterModal1").hide();

      showNotification('All filters have been cleared', 'success');
      updateClearButtonVisibility();
    }

    function areEoiFiltersActive() {
      // Global search
      if (eoiTable && eoiTable.search() && eoiTable.search().trim() !== '') return true;

      // Column searches
      if (eoiTable) {
        let hasColumnSearch = false;
        eoiTable.columns().every(function () {
          if (this.search()) {
            hasColumnSearch = true;
            return false;
          }
          return true;
        });
        if (hasColumnSearch) return true;
      }

      // Dropdown checkbox filters
      const checkedSpecific = $('.filter-option:checked').filter(function () {
        const val = $(this).val();
        return val !== '' && val !== null && val !== undefined;
      });
      if (checkedSpecific.length > 0) return true;

      // Isolated inputs (modal/mobile filters)
      const isolatedSelectors = [
        '#isolatedFilterCustumername1',
        '#isolatedFilterEmail1',
        '#isolatedFilterContactnumber1',
        '#isolatedFilterBuilderName',
        '#isolatedFilterProjectType',
        '#isolatedFilterProjectName',
        '#isolatedFilterStartDate1',
        '#isolatedFilterEndDate1'
      ];
      for (const selector of isolatedSelectors) {
        if ($(selector).val() && $(selector).val().trim() !== '') {
          return true;
        }
      }

      // Date filters (desktop picker)
      if ($('#dateFrom').val() || $('#dateTo').val()) return true;

      // Extra filters applied via custom search functions (besides base date filter)
      if (
        $.fn.dataTable.ext.search &&
        $.fn.dataTable.ext.search.some((fn) => fn !== window.eoiDateRangeFilter)
      ) {
        return true;
      }

      // Hierarchy/user filter
      if (typeof currentSelectedUser !== 'undefined' && currentSelectedUser !== 'all') return true;

      return false;
    }

    function updateClearButtonVisibility() {
      if (areEoiFiltersActive()) {
        $clearBtn.addClass('show').fadeIn(200);
      } else {
        $clearBtn.removeClass('show').fadeOut(200);
      }
    }

    $clearBtn.on('click', clearAllEoiFilters);

    const eventsToMonitor = [
      { event: 'change', target: '#dateFrom, #dateTo, #isolatedFilterStartDate1, #isolatedFilterEndDate1' },
      { event: 'input change', target: '#isolatedFilterCustumername1, #isolatedFilterEmail1, #isolatedFilterContactnumber1, #isolatedFilterBuilderName, #isolatedFilterProjectType, #isolatedFilterProjectName' },
      { event: 'change', target: '.filter-option' },
      { event: 'keyup', target: '.dataTables_filter input' }
    ];

    eventsToMonitor.forEach(({ event, target }) => {
      $(document).on(event, target, () => {
        setTimeout(updateClearButtonVisibility, 150);
      });
    });

    $(document).on('draw.dt', function () {
      setTimeout(updateClearButtonVisibility, 150);
    });

    // Initial visibility check
    setTimeout(updateClearButtonVisibility, 800);

    return {
      updateVisibility: updateClearButtonVisibility,
      clearAll: clearAllEoiFilters
    };
  }

  // Initialize floating clear-filters button after table is ready
  setTimeout(() => {
    window.eoiClearFiltersManager = initEoiClearFiltersButton();
  }, 1500);

  $("#isolatedClearFiltersBtn1").on("click", function () {
    if (window.eoiClearFiltersManager) {
      window.eoiClearFiltersManager.clearAll();
    } else {
      // Fallback: reset form and redraw
      $("#filterForm1")[0].reset();
      $.fn.dataTable.ext.search = $.fn.dataTable.ext.search.filter(
        (fn) => fn === window.eoiDateRangeFilter
      );
      if (eoiTable) {
        eoiTable.search('');
        eoiTable.columns().search('');
        eoiTable.draw();
      }
      $("#filterModal1").hide();
    }
  });

  // Code to disable/enable Add EOI button based on form validation
  const addForm = document.getElementById("add-eoi-form");
  const addButton = document.getElementById("add-eoi-btn");

  // Initially disable the button
  addButton.disabled = true;

  // Function to check if all required fields are filled
  function validateAddForm() {
    const requiredFields = addForm.querySelectorAll("input[required]");
    let allFilled = true;
    requiredFields.forEach(field => {
      if (!field.value.trim()) {
        allFilled = false;
      }
    });
    addButton.disabled = !allFilled;
  }

  // Add event listeners to required inputs
  const requiredInputsAdd = addForm.querySelectorAll("input[required]");
  requiredInputsAdd.forEach(input => {
    input.addEventListener("input", validateAddForm);
    input.addEventListener("change", validateAddForm);
  });

  // ═══════════════════════════════════════════════════════════════════════════
  // +Add Button Functionality for Edit EOI Modal (Mini Popup)
  // ═══════════════════════════════════════════════════════════════════════════

  // Check if mini modal exists, if not create it (use same markup as bookings page)
  let miniOverlay = document.getElementById('miniOverlay');
  if (!miniOverlay) {
    const miniHTML = `
      <div class="modal-overlay" id="miniOverlay" style="display: none;">
          <div class="modal-container-eoi add-option-modal">
              <div class="modal-header">
                  <h3 id="miniTitle">Add</h3>
                  <button type="button" class="modal-close-btn" onclick="closeMini()">&times;</button>
              </div>

              <div class="modal-body">
                  <form id="miniForm">
                      <div class="section">
                          <h3>Details</h3>
                          <div class="">
                              <div class="field full-row">
                                  <fieldset class="fieldset-label">
                                      <legend class="field-legend" id="miniLabel">Value</legend>
                                      <input type="text" id="miniInput" placeholder="Enter value" required aria-label="New value" />
                                  </fieldset>
                                  <p class="invalid-feedback">This field is required.</p>
                              </div>
                          </div>
                      </div>

                      <input type="hidden" id="optionType" />
                  </form>
              </div>

              <div class="form-actions">
                  <button type="button" class="cancel-btn btn" onclick="closeMini()">Cancel</button>
                  <button type="button" class="submit-btn btn" onclick="submitOption()">Save</button>
              </div>
          </div>
      </div>
    `;
    document.body.insertAdjacentHTML('beforeend', miniHTML);
    miniOverlay = document.getElementById('miniOverlay');
  }

  const miniTitle = document.getElementById('miniTitle');
  const miniLabel = document.getElementById('miniLabel');
  const miniInput = document.getElementById('miniInput');
  let miniTarget = null;

  const addMapEOI = {
    edit: {
      builder: { input: '#developer', list: 'builderList', label: 'New builder name' },
      project: { input: '#bproject', list: 'projectList', label: 'New project name' },
      ptype: { input: '#tproject', list: 'ptypeList', label: 'New project type' },
      customer: { input: '#cname', list: null, label: 'New customer name' },
      contact: { input: '#cnumber', list: null, label: 'New contact number' },
      email: { input: '#cemail', list: null, label: 'Enter email address' }
    },
    add: {
      builder: { input: '#addDeveloper', list: 'developer-list', label: 'New builder name' },
      project: { input: '#addProject', list: 'project-list', label: 'New project name' },
      ptype: { input: '#addProjectType', list: null, label: 'New project type' },
      customer: { input: '#addCustomerName', list: null, label: 'New customer name' },
      contact: { input: '#addContactNo', list: null, label: 'New contact number' },
      email: { input: '#addEmail', list: null, label: 'Enter email address' }
    }
  };

  window.openMiniAdd = function(key, variant = 'edit') {
    const cfg = addMapEOI[variant] && addMapEOI[variant][key];
    if (!cfg) return;
    miniTarget = cfg;
    miniTitle.textContent = cfg.label;
    miniLabel.textContent = cfg.label;
    miniInput.value = '';
    miniOverlay.style.display = 'flex';
    setTimeout(() => miniInput.focus(), 20);
  };

  window.closeMini = function() {
    miniOverlay.style.display = 'none';
    miniTarget = null;
  };

  window.submitOption = function() {
    if (!miniTarget) return;
    const val = miniInput.value.trim();
    if (!val) return miniInput.focus();

    // If target has an associated datalist/select, add the new option and persist
    if (miniTarget.list) {
      const dl = document.getElementById(miniTarget.list);
      if (dl) {
        const opt = document.createElement('option');
        opt.value = val;
        dl.appendChild(opt);
        if (typeof persistList === 'function') persistList(miniTarget.list);
      }
    }

    const inputEl = document.querySelector(miniTarget.input);
    if (inputEl) {
      const currentValue = inputEl.value.trim();
      if (currentValue) {
        inputEl.value = currentValue + ', ' + val;
      } else {
        inputEl.value = val;
      }
    }

    window.closeMini();
  };

  // Attach click handlers to all .add-btn elements in the EOI forms
  document.querySelectorAll('#editEOIModal .add-btn, #addNewEOIModal .add-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      const addType = btn.getAttribute('data-add');
      const modal = btn.closest('.modal-overlay');
      const variant = modal && modal.id === 'addNewEOIModal' ? 'add' : 'edit';
      window.openMiniAdd(addType, variant);
    });
  });

  // Close mini modal when pressing Escape
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && miniOverlay && miniOverlay.style.display === 'flex') {
      window.closeMini();
    }
  });

  // Close mini modal when clicking outside
  miniOverlay.addEventListener('click', (e) => {
    if (e.target === miniOverlay) {
      window.closeMini();
    }
  });

  // Allow Enter key to submit the mini form
  miniInput.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') {
      window.submitOption();
    }
  });

  // Add button hover effects
  const addButtonHoverStyle = `
    #miniOverlay button[onclick="window.closeMini()"]:hover {
      background-color: #c82333 !important;
      border-color: #c82333 !important;
    }
    #miniOverlay button[onclick="window.submitOption()"]:hover {
      background-color: #0056b3 !important;
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0, 123, 255, 0.4);
    }
    /* match bookings modal close button hover */
    .modal-container-eoi.add-option-modal .modal-close-btn:hover {
      background: #dc3545 !important;
      color: #fff !important;
      border-color: #dc3545 !important;
      transform: none !important;
    }
  `;
  const styleEl = document.createElement('style');
  styleEl.textContent = addButtonHoverStyle;
  document.head.appendChild(styleEl);

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
      0: "ID",
      1: "Customer Name",
      2: "Builder Name",
      3: "Project Name",
      4: "Contact",
      5: "Email",
      6: "Project Type",
      7: "Booking Date",
      8: "Booking Month",
    };

    table.columns().every(function () {
      const index = this.index();
      const headerNode = this.header();

      // Skip Expand (9) and Actions (10) as they are utility columns and shouldn't be hidden
      if (index === 9 || index === 10) return;

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

});

