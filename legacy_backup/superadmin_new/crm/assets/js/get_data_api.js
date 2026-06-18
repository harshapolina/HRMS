document.addEventListener("DOMContentLoaded", () => {
  // Load user options in the select box
  fetchUsers();
  // fetchFilterValue();
});
// $("#isolatedFilterModal").on("shown.bs.modal", function () {
//   fetchFilterValue();
// });

// Fetch user options and populate select element
const fetchUsers = async () => {
  const userSelect = document.getElementById("users");

  try {
    const response = await fetch("upload.php?get_users=1", { method: "GET" });
    const options = await response.text();
    userSelect.innerHTML = options;  // Populate the <select> element with options
  } catch (error) {
    console.error("Failed to fetch users:", error);
  }
};

const initializeFilterSelect2 = () => {
  const selectIds = {
      name: "isolatedFilterCustumername",
      email: "isolatedFilterEmail",
      number: "isolatedFilterContactnumber",
      location: "isolatedFilterLocation",
      source_of_lead: "isolatedFilterSourceOfLead",
      project: "isolatedFilterAssignedProjectName",
      assign_to_user: "isolatedFilterAssignedUserName",
      status: "isolatedFilterStatus"
  };

  Object.keys(selectIds).forEach(column => {
      let selectElement = document.getElementById(selectIds[column]);
      
      if (selectElement) {
          $(`#${selectIds[column]}`).select2({
              placeholder: "Search & select",
              allowClear: true,
              multiple: true,
              width: '100%',
              tags: true,
              closeOnSelect: false,
              ajax: {
                  url: "upload.php",
                  dataType: "json",
                  delay: 300, // Delay to reduce requests
                  data: function (params) {
                      return {
                          get_filter_value: 1, // Send filter request
                          column: column,      // Column to search
                          search: params.term || "" // Search term
                      };
                  },
                  processResults: function (data) {
                      if (data.status === "success") {
                          return {
                              results: data.filters.map(value => ({
                                  id: value,
                                  text: value
                              }))
                          };
                      } else {
                          console.error("Server error:", data.message);
                          return { results: [] };
                      }
                  },
                  cache: true
              }
          });
      } else {
          console.error(`Element #${selectIds[column]} not found.`);
      }
  });
};

// Ensure it runs **after DOM is fully loaded**
$(document).ready(function () {
  initializeFilterSelect2();
});

// these script is for delete select row or other operations
document.addEventListener('DOMContentLoaded', function () {
    
    // Handle Single Lead Insertion
    const addLeadForm = document.getElementById("addLeadForm");
    const responseMessage = document.getElementById("responseMessage");
    const submitLeadButton = document.getElementById("submitLead");

    if (!addLeadForm || !responseMessage || !submitLeadButton) {
        console.warn("Required elements not found");
        return;
    }

    console.log("All elements found. Attaching listener to #submitLead");

    submitLeadButton.addEventListener("click", async function () {
        console.log("#submitLead clicked");

        const formData = new FormData(addLeadForm);
        console.log("FormData prepared for lead insert");

        try {
            const response = await fetch("insert_lead.php", {
                method: "POST",
                body: formData
            });
            console.log("Request sent to insert_lead.php");

            if (!response.ok) {
                console.error("Response not ok:", response.status, response.statusText);
                throw new Error("Network response was not ok");
            }

            const res = await response.json();
            console.log("Response received:", res);

            responseMessage.style.display = "block";
            responseMessage.className = res.status === "success" ? "alert alert-success" : "alert alert-danger";
            responseMessage.textContent = res.message;

            let modalEl = document.getElementById("addLeadModal");
            let modal = bootstrap.Modal.getInstance(modalEl);
            if (!modal) {
                modal = new bootstrap.Modal(modalEl);
            }
            modal.hide();
            console.log("Modal closed");

            addLeadForm.reset();

            setTimeout(() => {
                responseMessage.style.display = "none";
            }, 3000);

            if (res.status === "success") {
                console.log("Insert success. Refreshing data...");
                fetchData();
            }

        } catch (error) {
            console.error("Insert error:", error);

            responseMessage.style.display = "block";
            responseMessage.className = "alert alert-danger";
            responseMessage.textContent = "An error occurred. Please try again later.";

            const modalEl = document.getElementById("addLeadModal");
            let modal = bootstrap.Modal.getInstance(modalEl);
            if (!modal) {
                modal = new bootstrap.Modal(modalEl);
            }
            modal.hide();

            addLeadForm.reset();

            setTimeout(() => {
                responseMessage.style.display = "none";
            }, 3000);
        }
    });
  // Handle file upload
  document.getElementById("uploadForm").addEventListener("submit", async (event) => {
        event.preventDefault();
        const formData = new FormData(event.target);
    
        try {
            const response = await fetch("upload.php", {
                method: "POST",
                body: formData,
            });
    
            if (!response.ok) {
                throw new Error("Network response was not ok");
            }
    
            const result = await response.json();
    
            const uploadMessage = document.getElementById("uploadMessage");
            uploadMessage.style.display = "block";
            uploadMessage.className = result.status === "success" ? "alert alert-success" : "alert alert-danger";
            uploadMessage.textContent = result.message;
    
            document.querySelector("#uploadExcelPopup .btn-secondary").click();
    
            setTimeout(() => {
                uploadMessage.style.display = "none";
            }, 5000);
    
            if (result.status === "success") {
                fetchData(); // Refresh data table after successful upload
            }
        } catch (error) {
            console.error("Error uploading file:", error);
            alert("There was an error uploading the file.");
        }
    });

  // Initialize variables
  const rowSelector = document.getElementById('rowSelector');
  const prevButton = document.getElementById('prevButton');
  const nextButton = document.getElementById('nextButton');
  const pageNumbersContainer = document.getElementById('pageNumbers');
  const jumpToPageInput = document.getElementById('jumpToPageInput');
  const jumpButton = document.getElementById('jumpButton');
  const rowInfo = document.getElementById('rowInfo');
  const searchInput = document.getElementById('searchInput');
  const uploaddata = document.getElementById('uploaddata'); // Table body
  const selectAllCheckbox = document.getElementById('select-all');
  const deleteSelectedBtn = document.getElementById('delete-selected-btn');

  let currentPage = 1;
  let rowsPerPage = parseInt(rowSelector.value, 10);
  let totalPages;
  let currentFilter = ''; // To track the active filter
  let multifilters = {}; // To store multi-column filters
  let showDeletedOnly = false;
  const leadTypeButtons = document.querySelectorAll('.accessbtn[data-lead-type]');

  // Function to trigger the close button programmatically
  function closeModal() {
    const closeButton = document.getElementById('isolatedCancleFilter');
    if (closeButton) {
        closeButton.click(); // Programmatically trigger the close button's click event
    }
  }
  // Function to handle multi-column filtering
  function applyMultiColumnFilter() {
    // Get values from modal inputs
    multifilters = {
        name: $('#isolatedFilterCustumername').val() || [],
        email: $('#isolatedFilterEmail').val() || [],
        number: $('#isolatedFilterContactnumber').val() || [],
        location: $('#isolatedFilterLocation').val() || [],
        source_of_lead: $('#isolatedFilterSourceOfLead').val() || [],
        project: $('#isolatedFilterAssignedProjectName').val() || [],
        assign_to_user: $('#isolatedFilterAssignedUserName').val() || [],
        status: $('#isolatedFilterStatus').val() || [],
        start_date: $('#isolatedFilterStartDate').val().trim() || null,  // Added Start Date
        end_date: $('#isolatedFilterEndDate').val().trim() || null 
        // name: document.getElementById('isolatedFilterCustumername').value.trim(),
        // email: document.getElementById('isolatedFilterEmail').value.trim(),
        // number: document.getElementById('isolatedFilterContactnumber').value.trim(),
        // location: document.getElementById('isolatedFilterLocation').value.trim(),
        // source_of_lead: document.getElementById('isolatedFilterSourceOfLead').value.trim(),
        // project: document.getElementById('isolatedFilterAssignedProjectName').value.trim(),
        // assign_to_user: document.getElementById('isolatedFilterAssignedUserName').value.trim(),
        // status: document.getElementById('isolatedFilterStatus').value.trim(),
    };

    // Log filters for debugging
    console.log('[DEBUG] Applying Multi-Column Filters:', multifilters);

    currentPage = 1; // Reset to the first page
    fetchData(currentPage, rowsPerPage, searchInput.value.trim(), '', multifilters); // Fetch data with multi-column filters

    // Hide the modal
    closeModal();
  }

  // Event listener for the "Apply Filters" button
  document.getElementById('isolatedApplyFiltersBtn').addEventListener('click', applyMultiColumnFilter);

  // Event listener for the "Clear Filters" button
  document.getElementById('isolatedClearFiltersBtn').addEventListener('click', () => {
    // IDs of all filter inputs
    const filterIds = [
        'isolatedFilterCustumername',
        'isolatedFilterEmail',
        'isolatedFilterContactnumber',
        'isolatedFilterLocation',
        'isolatedFilterSourceOfLead',
        'isolatedFilterAssignedProjectName',
        'isolatedFilterAssignedUserName',
        'isolatedFilterStatus',
        'isolatedFilterStartDate',
        'isolatedFilterEndDate'
    ];

    // Loop through each filter input
    filterIds.forEach(id => {
        const element = document.getElementById(id);
        if (element) {
            // Check if it's a Select2 dropdown
            if ($(element).hasClass("select2-hidden-accessible")) {
                $(element).val(null).trigger("change"); // Clear Select2 selections
            } else {
                element.value = ''; // Clear normal input fields
            }
        }
    });

    // Reset multi-column filters and pagination
    multifilters = {}; 
    currentPage = 1;

    // Fetch data without filters
    fetchData(currentPage, rowsPerPage, searchInput.value.trim());

    // Close the modal
    closeModal();
});
leadTypeButtons.forEach(button => {
  button.addEventListener('click', function () {
    // Remove highlight from all other lead-type buttons
    leadTypeButtons.forEach(btn => btn.classList.remove('active-deleted'));
    
    // Highlight clicked button
    this.classList.add('active-deleted');

    // Set filter based on data-lead-type
    const leadType = this.dataset.leadType;
    showDeletedOnly = leadType === 'deleted';
    currentFilter = leadType;
    currentPage = 1;

    // Call fetchData with updated filter
    fetchData(currentPage, rowsPerPage, searchInput.value.trim(), currentFilter, multifilters, showDeletedOnly);
  });
});
// document.getElementById("downloadCsv").addEventListener("click", function () {
//     // Use the same globals/state used in fetchData
//     const encodedQuery = searchInput.value.trim() ? encodeURIComponent(searchInput.value.trim()) : '';
//     const encodedFilters = multifilters ? encodeURIComponent(JSON.stringify(multifilters)) : '';
//     const startDate = multifilters.start_date ? encodeURIComponent(multifilters.start_date) : '';
//     const endDate = multifilters.end_date ? encodeURIComponent(multifilters.end_date) : '';

//     const url = `upload.php?download=1
//                 &searchQuery=${encodedQuery}
//                 &multiFilters=${encodedFilters}
//                 &startDate=${startDate}&endDate=${endDate}
//                 &showDeletedOnly=${showDeletedOnly ? 1 : 0}
//                 &currentFilter=${encodeURIComponent(currentFilter)}`;

//     window.open(url, "_blank"); // ✅ triggers CSV download
// });
  // Fetch data function
  async function fetchData(page = 1, rowsPerPage = 10, searchQuery = '', filterType = '', multiFilters = {}, showDeletedOnly = false) {
    try {
          const encodedQuery = searchQuery ? encodeURIComponent(searchQuery) : '';
          const encodedFilters = multiFilters ? encodeURIComponent(JSON.stringify(multiFilters)) : '';
          const startDate = multiFilters.start_date ? encodeURIComponent(multiFilters.start_date) : '';
          const endDate = multiFilters.end_date ? encodeURIComponent(multiFilters.end_date) : '';
    
        const url = `upload.php?page=${page}&rowsPerPage=${rowsPerPage}
              &searchQuery=${encodedQuery}
              &multiFilters=${encodedFilters}
              &startDate=${startDate}&endDate=${endDate}
              &showDeletedOnly=${showDeletedOnly ? 1 : 0}
              &currentFilter=${encodeURIComponent(filterType)}`;
        //   const url = `upload.php?page=${page}&rowsPerPage=${rowsPerPage}
        //   &searchQuery=${encodedQuery}&filter=${encodeURIComponent(filterType)}
        //   &multiFilters=${encodedFilters}&start_date=${startDate}&end_date=${endDate}`;

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
        updateTable(data.data, showDeletedOnly);
        updatePagination(data.totalRows, data.currentPage, data.rowsPerPage);
        // Call the user cell processing after the table is updated
        processUserCells();
        PopulateCheckedRow();
        getUserLeadsCount()
    } catch (error) {
      console.error('Fetch error:', error);
      alert(`Error fetching data: ${error.message}`);
    }
  }

  // <!-- column selector script for table start-->
  function PopulateCheckedRow() {
        function populateDropdown() {
            const columnSelector = document.getElementById('columnSelector');
            const headers = document.querySelectorAll('#myTable thead th');
            
            headers.forEach((header, index) => {
                const columnIndex = index + 1; 
                const label = document.createElement('label');
                // Check if the header has the 'hide-column' class
                const isChecked = !header.classList.contains('hide-column');
                // Create the checkbox with checked/unchecked state based on the class
                label.innerHTML = `<input type="checkbox" value="${columnIndex}" ${isChecked ? 'checked' : ''}> ${header.innerText}`;
                columnSelector.appendChild(label);
                // Hide the column if it has the 'hide-column' class
                if (!isChecked) {
                    const cells = document.querySelectorAll(`#myTable tr th:nth-child(${columnIndex}), #myTable tr td:nth-child(${columnIndex})`);
                    cells.forEach(cell => {
                        cell.style.display = 'none'; // Hide the column by default
                    });
                }
            });
    }

    function toggleColumnVisibility() {
        document.getElementById('columnSelector').addEventListener('change', function (event) {
            const checkbox = event.target;
            if (checkbox.tagName === 'INPUT' && checkbox.type === 'checkbox') {
                const column = checkbox.value;
                const isChecked = checkbox.checked;
                const cells = document.querySelectorAll(`#myTable tr th:nth-child(${column}), #myTable tr td:nth-child(${column})`);
                cells.forEach(cell => {
                    cell.style.display = isChecked ? '' : 'none'; // Show/Hide based on checkbox state
                });
            }
        });
    }
    populateDropdown();
    toggleColumnVisibility();
  }
  // <!-- column selector script for table end-->
  // This Javascript is for GET The Count of leads Status 
  function getUserLeadsCount() {
    const getAllCountData = async (flag = false) => {
      try {
        // Fetch the total count, unassigned count, and my leads count from the PHP script
        const response = await fetch("upload.php?get_data=1");
        const data = await response.json(); // Parse the response as JSON
  
        // Update the "Total" button with the total count of rows
        document.getElementById("totalLeads").innerHTML = `<i class="bi bi-activity"></i> Total (${data.total})`;
  
        // Update the "Unassigned" button with the count of unassigned rows
        document.getElementById("totalUnassigned").innerHTML = `<i class="bi bi-bell-slash"></i> Unassigned (${data.unassigned})`;
  
        // Update the "My Leads" button with the count of leads assigned to the current user
        document.getElementById("myLeads").innerHTML = `<i class="bi bi-graph-up"></i> My Leads (${data.myLeads})`;
        
        // Update the "My Leads" button with the count of leads assigned to the current user
        document.getElementById("bookedLeads").innerHTML = `<i class="bi bi-journal-richtext"></i> Booked (${data.bookedLeads})`;
  
        // Update the "Active Leads" button with the count of active leads
        document.getElementById("activeLeads").innerHTML = `<i class="bi bi-activity"></i> Active (${data.activeLeads})`;
  
        // Update the "Dropped Leads" button with the count of dropped leads
        document.getElementById("droppedLeads").innerHTML = `<i class="bi bi-droplet"></i> Dropped (${data.droppedLeads})`;
  
        // Update the "Fresh Leads" button with the count of leads from the last 5 days
        document.getElementById("freshLeads").innerHTML = `<i class="bi bi-cloud-plus"></i> New (${data.freshLeads})`;
  
        // Update the "Pending Leads" button with the count of pending leads
        document.getElementById("pendingLeads").innerHTML = `<i class="bi bi-hourglass"></i> Pending (${data.pendingLeads})`;

        // Update the "Pending Leads" button with the count of pending leads
        document.getElementById("eoicounterdata").innerHTML = `<i class="bi bi-card-checklist"></i> EOI (${data.totaleoi})`;
        
        // Update the "Deleted Leads" button with the count of pending leads
        document.getElementById("deletedLeads").innerHTML = `<i class="bi bi-trash" style="color:red"></i>Deleted Leads (${data.totaldelete})`;
  
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
  }
  // This Javascript is for GET The Count of leads Status End
  // Update the table
  document.addEventListener("click", function (e) {
        const cell = e.target.closest(".masked-number");
        if (!cell) return;
    
        // Hide ALL previously shown numbers
        document.querySelectorAll(".masked-number[data-visible='true']").forEach(otherCell => {
            const full = otherCell.dataset.full;
            const masked = full.replace(/\d(?=\d{4})/g, "*");
            otherCell.textContent = masked;
            otherCell.dataset.visible = "false";
        });
    
        // Show the clicked one
        cell.textContent = cell.dataset.full;
        cell.dataset.visible = "true";
    });
  function updateTable(rows) {
      uploaddata.innerHTML = ''; // Clear the table body

      rows.forEach(row => {
          const tr = document.createElement('tr');
          let numberCell = "";

        if (CURRENT_USER === "subham323" || CURRENT_USER === "NoUser323") {
            numberCell = `
                <td class="masked-number">XXXXXXXXXX</td>
            `;
        } else {
            const maskedNumber = row.number
                ? row.number.replace(/\d(?=\d{4})/g, "*")
                : "";
        
            numberCell = `
                <td class="masked-number" data-full="${row.number}" data-visible="false">
                    ${maskedNumber}
                </td>
            `;
        }
          tr.innerHTML = `
              <td><input type="checkbox" class="select-row" value="${row.id}"></td>
              <td>
                <div class="user-info">
                    <div class="avatar-wrapper">
                    <img src="./assets/images/avatar.jpeg" alt="${row.name} Image">
                    ${typeof row.lead_count !== 'undefined' && row.lead_count !== null && row.lead_count !== '' 
                        ? `<span class="lead-counter">${row.lead_count}</span>` 
                        : ''
                    }
                    </div>
                    <span class="user-name">${row.name}</span>
                </div>
              </td>
              <td>${row.email}</td>
              ${numberCell}
              <td>${row.location}</td>
              <td>${row.project}</td>
              <td>${row.source_of_lead}</td>
              <td class="user-cell">${row.assign_to_user}</td>
              <td>
                ${showDeletedOnly
                    ? `
                        <button type="button" class="btn btn-sm btn-outline-primary recover-lead me-1" data-id="${row.original_id || row.id}" title="Recover Lead">
                        <i class="bi bi-arrow-clockwise"></i> Recover
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger permanently-delete-lead" data-id="${row.original_id || row.id}" title="Delete Permanently">
                        <i class="bi bi-x-octagon"></i> Delete
                        </button>
                    `
                    : `
                        <button type="button" class="btn btn-sm btn-outline-secondary status-modal-cls-cmmn view-status" data-bs-toggle="modal" data-bs-target="#viewStatusModal" data-id="${row.id}" title="View Status">
                        <i class="bi bi-eye"></i> Status
                        </button>
                    `
                }
             </td>
              <td>${row.created_at}</td>
          `;
          uploaddata.appendChild(tr);
      });
      if (showDeletedOnly) {
        // Recover Lead
        document.querySelectorAll('.recover-lead').forEach(btn => {
        btn.addEventListener('click', async () => {
            const id = btn.getAttribute('data-id');

            const confirmResult = await Swal.fire({
            title: 'Recover Lead?',
            text: "Are you sure you want to recover this lead?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, recover it!',
            cancelButtonText: 'Cancel'
            });

            if (confirmResult.isConfirmed) {
            try {
                const res = await fetch('bulk_delete.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `recover_id=${encodeURIComponent(id)}`
                });
                const result = await res.json();
                if (result.status === 'success') {
                Swal.fire('Recovered!', 'Lead successfully recovered.', 'success');
                } else {
                Swal.fire('Error!', result.message, 'error');
                }
                fetchData(1, 10, '', '', {}, true);
            } catch (e) {
                Swal.fire('Error!', 'Error recovering lead.', 'error');
            }
            }
        });
        });

        // Permanently Delete Lead
        document.querySelectorAll('.permanently-delete-lead').forEach(btn => {
        btn.addEventListener('click', async () => {
            const id = btn.getAttribute('data-id');

            const confirmResult = await Swal.fire({
            title: 'Delete Permanently?',
            text: "This action cannot be undone. Are you sure?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'Cancel'
            });

            if (confirmResult.isConfirmed) {
            try {
                const res = await fetch('bulk_delete.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `permanent_delete_id=${encodeURIComponent(id)}`
                });
                const result = await res.json();
                if (result.status === 'success') {
                Swal.fire('Deleted!', 'Lead permanently deleted.', 'success');
                } else {
                Swal.fire('Error!', result.message, 'error');
                }
                fetchData(1, 10, '', '', {}, true);
            } catch (e) {
                Swal.fire('Error!', 'Error deleting lead.', 'error');
            }
            }
        });
        });
    }
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
          fetchData(pageNumber, rowsPerPage, searchInput.value.trim(), currentFilter, multifilters, showDeletedOnly);
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
    fetchData(currentPage, rowsPerPage, searchInput.value.trim(), currentFilter, multifilters, showDeletedOnly);
});

searchInput.addEventListener('keyup', function () {
    const searchQuery = this.value;
    currentPage = 1;
    fetchData(currentPage, rowsPerPage, searchQuery, currentFilter, multifilters, showDeletedOnly);
});

prevButton.addEventListener('click', function () {
    if (currentPage > 1) {
        currentPage--;
        fetchData(currentPage, rowsPerPage, searchInput.value.trim(), currentFilter, multifilters, showDeletedOnly);
    }
});

nextButton.addEventListener('click', function () {
    if (currentPage < totalPages) {
        currentPage++;
        fetchData(currentPage, rowsPerPage, searchInput.value.trim(), currentFilter, multifilters, showDeletedOnly);
    }
});

jumpButton.addEventListener('click', function () {
    const pageNumber = parseInt(jumpToPageInput.value, 10);
    if (pageNumber >= 1 && pageNumber <= totalPages) {
        currentPage = pageNumber;
        fetchData(pageNumber, rowsPerPage, searchInput.value.trim(), currentFilter, multifilters, showDeletedOnly);
    } else {
        alert('Please enter a valid page number.');
    }
});

document.getElementById('totalUnassigned').addEventListener('click', function() {
  currentPage = 1; // Reset to first page
  fetchData(currentPage, rowsPerPage, '', 'unassigned');
});

document.getElementById('myLeads').addEventListener('click', function() {
  currentPage = 1; // Reset to first page
  fetchData(currentPage, rowsPerPage, '', 'myLeads');
});

document.getElementById('freshLeads').addEventListener('click', function() {
  currentPage = 1; // Reset to first page
  fetchData(currentPage, rowsPerPage, '', 'freshLeads');
});

document.getElementById('totalLeads').addEventListener('click', function() {
  currentPage = 1; // Reset to first page
  fetchData(currentPage, rowsPerPage, '', 'total');
});

  // Handle row selection (individual and bulk)
  selectAllCheckbox.addEventListener('change', function () {
    const allRowCheckboxes = document.querySelectorAll('.select-row');
    selectedIds = []; // Reset selected IDs array

    allRowCheckboxes.forEach(checkbox => {
        checkbox.checked = this.checked; // Check/uncheck all rows
        if (this.checked) {
            selectedIds.push(checkbox.value); // Add all row IDs to selectedIds array
        }
    });

    selectedIdsInput.value = selectedIds.join(','); // Update hidden input
    toggleAssignButton(); // Recheck button state
});

uploaddata.addEventListener('change', function (event) {
      if (event.target.classList.contains('select-row')) {
          if (!event.target.checked) {
              selectAllCheckbox.checked = false; // Uncheck "Select All" if any row is unchecked
          } else {
              const allRowCheckboxes = document.querySelectorAll('.select-row');
              const allChecked = Array.from(allRowCheckboxes).every(checkbox => checkbox.checked);
              selectAllCheckbox.checked = allChecked;
          }
      }
  });

 //  this is javascript is for trim assigned users name 
  // Select all the cells with class 'user-cell'
  
// Handle bulk deletion
deleteSelectedBtn.addEventListener('click', function (event) {
  event.preventDefault();
  const selectedCheckboxes = document.querySelectorAll('.select-row:checked');
  const numSelected = selectedCheckboxes.length;

  if (numSelected > 0) {
      Swal.fire({
          title: 'Are you sure?',
          text: `You are about to delete ${numSelected} selected row(s). This action cannot be undone.`,
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'Yes, delete them!',
          cancelButtonText: 'No, cancel!'
      }).then((result) => {
          if (result.isConfirmed) {
              const selectedIds = Array.from(selectedCheckboxes).map(checkbox => checkbox.value);

              // Perform the AJAX request for deletion
              fetch('bulk_delete.php', {
                  method: 'POST',
                  headers: {
                      'Content-Type': 'application/x-www-form-urlencoded',
                  },
                  body: `row_ids=${encodeURIComponent(JSON.stringify(selectedIds))}` // Send the selected IDs
              })
              .then(response => response.json())
              .then(data => {
                  if (data.status === 'success') {
                      // Remove the deleted rows from the table
                      selectedCheckboxes.forEach(checkbox => {
                          const row = checkbox.closest('tr');
                          row.remove();
                      });

                      Swal.fire('Deleted!', 'Selected rows have been deleted.', 'success');

                      // Check if all rows on the current page have been deleted
                      const remainingRows = document.querySelectorAll('#uploaddata tr').length;

                      if (remainingRows === 0 && currentPage > 1) {
                          // If no rows remain on the current page, go to the previous page
                          currentPage--;
                      }
                      // Fetch updated data while staying on the current page
                      fetchData(currentPage, rowsPerPage, searchInput.value.trim(), currentFilter, multifilters, showDeletedOnly);
                      // Update the count 
                      getUserLeadsCount(); 
                  } else {
                      Swal.fire('Error!', 'There was a problem deleting the rows.', 'error');
                  }
              })
              .catch(error => {
                  console.error('Error deleting rows:', error);
                  Swal.fire('Error!', 'There was an error processing your request.', 'error');
              });
          }
      });
  } else {
      Swal.fire('No rows selected', 'Please select rows to delete.', 'info');
  }
});
// Handle bulk deletion End
// Variables for row selection and assign user
const assignButton = document.getElementById('assign-button');
const userSelect = document.getElementById('users');
const assignedUsersContainer = document.getElementById('assigned-users-container');
const hiddenUsersInput = document.getElementById('hidden-users');
const selectedIdsInput = document.getElementById('selected-ids');
const selectedCountElement = document.getElementById('selected-count'); // Element to show selected row count
let selectedIds = [];
let selectedUsers = [];

// Function to enable/disable the assign button
function toggleAssignButton() {
  // Enable the button if at least one row is selected
  assignButton.disabled = selectedIds.length === 0;

  // Update the selected row count in the modal
  selectedCountElement.textContent = selectedIds.length; 
}

// Handle row selection (checkboxes)
document.getElementById('uploaddata').addEventListener('change', function (event) {
  if (event.target.classList.contains('select-row')) {
    const rowId = event.target.value; // Get the row ID
    if (event.target.checked) {
      selectedIds.push(rowId); // Add to selected rows
    } else {
      selectedIds = selectedIds.filter(id => id !== rowId); // Remove from selected rows
      selectAllCheckbox.checked = false;
    }
    selectedIdsInput.value = selectedIds.join(','); // Update hidden input
    toggleAssignButton(); // Recheck button state
  }
});

// Handle user selection
userSelect.addEventListener('change', function () {
  const selectedOptions = Array.from(userSelect.selectedOptions);
  assignedUsersContainer.innerHTML = ''; // Clear assigned users container
  selectedUsers = [];

  selectedOptions.forEach(option => {
    const userValue = option.value;
    const userText = option.text;
    selectedUsers.push(userValue);

    const userDiv = document.createElement('div');
    userDiv.classList.add('assigned-user');
    userDiv.textContent = userText;

    const removeButton = document.createElement('button');
    removeButton.classList.add('remove-user');
    removeButton.textContent = 'x';
    removeButton.addEventListener('click', function () {
      selectedUsers = selectedUsers.filter(u => u !== userValue);
      updateHiddenInput();
      userDiv.remove();
      toggleAssignButton(); // Recheck button state
    });

    userDiv.appendChild(removeButton);
    assignedUsersContainer.appendChild(userDiv);
  });
  updateHiddenInput();
  toggleAssignButton(); // Recheck button state
});

// Function to update hidden input for users
function updateHiddenInput() {
  hiddenUsersInput.value = selectedUsers.join(','); // Update hidden input with selected user IDs
}

// Handle Assign User button click
document.getElementById('modal-assign-button').addEventListener('click', function (event) {
  event.preventDefault();  // Prevent default form submission

  if (selectedIds.length === 0) {
    alert('Please select at least one rows to assign.');
    return;
  }

  const formData = new FormData();
  formData.append('selected_ids', selectedIds.join(','));
  formData.append('users', selectedUsers.join(',')); // Pass selected users if necessary
  formData.append('assignprojectname', document.getElementById('assignprojectname').value);

  // Make the fetch request to assign users
  fetch('assign_users.php', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())  // Parse the JSON response
  .then(data => {
    const uploadMessageassign = document.getElementById("uploadMessage");

    // Show the message for 5 seconds
    uploadMessageassign.style.display = "block";
    uploadMessageassign.className = data.status === "success" ? "alert alert-success" : "alert alert-danger";
    uploadMessageassign.textContent = data.message;
    
    // Refresh table data (if needed)
    if (data.status === 'success') {
      // Hide the modal after a successful assignment
      // document.querySelector('#assignModal .close').click();  // Simulate the close button click
      const assignUserModal = bootstrap.Modal.getInstance(document.getElementById('assignModal'));
      assignUserModal.hide();

      // Clear the form fields
      document.getElementById('assign-form').reset();  // Reset the form fields
      assignedUsersContainer.innerHTML = '';  // Clear assigned users container
      selectedIds = [];  // Clear selected IDs
      selectedUsers = [];  // Clear selected users

      // Reset hidden inputs
      selectedIdsInput.value = '';
      hiddenUsersInput.value = '';

      // Disable the assign button
      assignButton.disabled = true;
      // Reset the selected row count in the modal
      selectedCountElement.textContent = selectedIds.length;

      // Check if all rows on the current page have been deleted
      const remainingRows = document.querySelectorAll('#uploaddata tr').length;

      if (remainingRows === 0 && currentPage > 1) {
        // If no rows remain on the current page, go to the previous page
        currentPage--;
      }

      // Fetch updated data while staying on the current page
      fetchData(currentPage, rowsPerPage, searchInput.value.trim(), currentFilter, multifilters, showDeletedOnly);
      getUserLeadsCount();
    }

    // Hide the message after 5 seconds
    setTimeout(() => {
      uploadMessageassign.style.display = "none";
    }, 5000);
  })
  .catch(error => {
    console.error('Error assigning users:', error);
    alert('There was an error processing the request.');
  });
});
// Prevent form submission and reload
const assignForm = document.getElementById('assign-form');
assignForm.addEventListener('submit', function (event) {
  event.preventDefault();  // Prevent form submission
  assignButton.click();  // Trigger the assign button click
});
// Assign User modle script end here
// Function to process user cells (run this after table is updated)
function processUserCells() {
  const userCells = document.querySelectorAll('.user-cell');
  userCells.forEach(cell => {
      // Get the full list of users from the td content
      const userList = cell.innerText.trim().split(',');

      // Display only the first name followed by "more..." if there are multiple names
      if (userList.length > 1) {
          cell.innerHTML = `${userList[0]} ...more`;
      }

      // Add a click event listener to the cell
      cell.addEventListener('click', function() {
          // Set the full list of users in the modal
          document.getElementById('assignUserList').innerText = userList.join(', ');

          // Trigger the modal to show
          const assignUserModal = new bootstrap.Modal(document.getElementById('assignUserModal'));
          assignUserModal.show();
      });
  });
}
// Initial fetch of data
fetchData(currentPage, rowsPerPage);
// this javascript is for trim the assign user ENd
getUserLeadsCount();  // Refresh count after filter change
});
// this is loader javascript 
    document.addEventListener("DOMContentLoaded", function() {
      var loader = document.getElementById('loader');
      // Show loader initially
      loader.style.opacity = '1';
      loader.style.top = '0';
      loader.style.zIndex = '1002'; // Set initial z-index to 999
      // Hide loader after 5 seconds with smooth transition
      setTimeout(function() {
        loader.style.transition = 'opacity 1s ease, top 1s ease, z-index 1s'; // Add z-index transition
        loader.style.opacity = '0';
        loader.style.top = '-100px'; // Move loader smoothly upward
        loader.style.zIndex = '0'; // Set z-index to 0 when hiding loader
      }, 2000);
    });
// this is loader javascript End
// this script is for showing the status popup model
$(document).ready(function() {
  // Event delegation: Use a parent element that exists when the page loads
  $(document).on('click', '.view-status', function(event) {
      event.preventDefault(); // Prevent the default action (page refresh)
      var uploadDataId = $(this).data('id');
      fetchData(uploadDataId, 'status');
  });

  $(document).on('click', '.view-remarks', function(event) {
      event.preventDefault(); // Prevent the default action (page refresh)
      var uploadDataId = $(this).data('id');
      fetchData(uploadDataId, 'remarks');
  });

// Function to fetch data for either status or remarks
  function fetchData(uploadDataId, type) {
      $.ajax({
          url: 'get_user_updates.php',
          method: 'GET',
          data: { upload_data_id: uploadDataId, type: type },
          success: function(response) {
              if (type === 'status') {
                  $('#statusModalData').html(response); // Inject data into the modal body
                  $('#viewStatusModal').modal('show'); // Show the Status modal
              } else if (type === 'remarks') {
                  $('#remarksModalData').html(response); // Inject data into the modal body
                  $('#viewRemarksModal').modal('show'); // Show the Remarks modal
              }
          },
          error: function(xhr, status, error) {
              alert('Error fetching data. Please try again.');
              console.error('AJAX Error: ', error); // Log any error
          }
      });
  }
});
// this script is for showing the status popup model End 
// Download Excle Sheet
document.getElementById('download-excel-ex').addEventListener('click', function() {
  window.location.href = 'example_format.xlsx';
});
// Download Excle Sheet End
// This is script for sidebar toggel start
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
        fetch('get_user_updates.php', {
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
                    data.history.forEach(entry => {
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
                                        <span><b>Date & Time</b>: ${
                                            entry.followUpDate || 'N/A'
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
    fetch('get_user_updates.php', {
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
                    data.history.forEach(entry => {
                    // Styling CSS END FOR STATUS
                        const li = document.createElement('li');
                        li.classList.add('unique-step', 'unique-active-timeline');
                        li.innerHTML = `
                            <div class="unique-dot"></div>
                            <div class="unique-content">
                                <span class="unique-status-info">Call Attempted: ${entry.click_attempted}</span>
                                <span class="unique-arrow">→</span> 
                                <span class="unique-status-view"><a href="#.">Date</a></span> 
                                <span class="unique-arrow">→</span>
                                <span class="unique-date-time">${entry.timestamp}</span>
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
// Logic is history model End