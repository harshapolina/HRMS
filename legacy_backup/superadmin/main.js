const addForm = document.getElementById("add-user-form");
const updateForm = document.getElementById("edit-user-form");
const showAlert = document.getElementById("showAlert");
const addModalElement = document.getElementById("addNewUserModal");
const editModalElement = document.getElementById("editUserModal");
const tbody = document.getElementById("pagedata");
// Support both filter modal IDs used across pages
const filterModalElement = document.getElementById("filterModal") || document.getElementById("filterModalOverlay");

// Only initialize modals if elements exist
const addModal = addModalElement ? new bootstrap.Modal(addModalElement) : null;
const editModal = editModalElement ? new bootstrap.Modal(editModalElement) : null;
var filterModal = filterModalElement ? new bootstrap.Modal(filterModalElement) : null;

// Add New User Ajax Request
if (addForm) {
  addForm.addEventListener("submit", async (e) => {
    e.preventDefault();

    const formData = new FormData(addForm);
    formData.append("add", 1);

    if (addForm.checkValidity() === false) {
      e.preventDefault();
      e.stopPropagation();
      addForm.classList.add("was-validated");
      return false;
    } else {
      document.getElementById("add-user-btn").value = "Please Wait...";

      const data = await fetch("action.php", {
        method: "POST",
        body: formData,
      });
      const response = await data.text();

      // Show toast notification instead of HTML alert
      if (response.includes('success') || response.includes('successfully')) {
        Swal.fire({
          toast: true,
          position: 'bottom',
          icon: 'success',
          title: 'Booking added successfully!',
          showConfirmButton: false,
          timer: 3000,
          timerProgressBar: true
        });
      } else {
        Swal.fire({
          toast: true,
          position: 'bottom',
          icon: 'error',
          title: 'Something went wrong!',
          showConfirmButton: false,
          timer: 3000,
          timerProgressBar: true
        });
      }

      const addBtn = document.getElementById("add-user-btn");
      if (addBtn) addBtn.value = "Add Booking";
      addForm.reset();
      addForm.classList.remove("was-validated");
      if (addModal) addModal.hide();
      fetchAllUsers(true);
    }
  });
}

// Fetch All Users Ajax Request
const fetchAllUsers = async (flag) => {
  if (!tbody) return; // Exit if tbody doesn't exist (iframe mode)
  const data = await fetch("action.php?read=1", {
    method: "GET",
  });
  const response = await data.text();
  tbody.innerHTML = response;
  if (flag) {
    if (typeof applyFilters === 'function') {
      applyFilters();
    }
  }
};
// Only fetch if tbody exists (not in iframe mode)
if (tbody) {
  fetchAllUsers(false);
}

// Edit User Ajax Request
if (tbody) {
  tbody.addEventListener("click", (e) => {
    if (e.target && e.target.matches("a.editLink")) {
      e.preventDefault();
      let id = e.target.getAttribute("id");
      editUser(id);
    }
  });
}

const editUser = async (id) => {
  try {
    const data = await fetch(`action.php?edit=1&id=${id}`, {
      method: "GET",
    });

    if (!data.ok) {
      throw new Error(`HTTP error! status: ${data.status}`);
    }

    const response = await data.json();
    const modal = document.getElementById('editUserModal');

    if (!modal) {
      Swal.fire({
        toast: true,
        position: 'bottom',
        icon: 'error',
        title: 'Edit modal not found. Please refresh the page.',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true
      });
      return;
    }

    // Populate form fields
    if (document.getElementById("id")) document.getElementById("id").value = response.id || '';
    if (document.getElementById("bdate")) document.getElementById("bdate").value = response.booking_date || '';
    if (document.getElementById("bmonth")) document.getElementById("bmonth").value = response.booking_month || '';
    if (document.getElementById("developer")) document.getElementById("developer").value = response.builder || '';
    if (document.getElementById("bproject")) document.getElementById("bproject").value = response.project || '';
    if (document.getElementById("cname")) document.getElementById("cname").value = response.customer_name || '';
    if (document.getElementById("cnumber")) document.getElementById("cnumber").value = response.contact_number || '';
    if (document.getElementById("cemail")) document.getElementById("cemail").value = response.email_id || '';
    if (document.getElementById("tproject")) document.getElementById("tproject").value = response.project_type || '';
    if (document.getElementById("unitno")) document.getElementById("unitno").value = response.unit_no || '';
    if (document.getElementById("psize")) document.getElementById("psize").value = response.size || '';
    if (document.getElementById("cagreement")) document.getElementById("cagreement").value = response.agreement_value || '';
    if (document.getElementById("ccashback")) document.getElementById("ccashback").value = response.cashback || '';
    if (document.getElementById("crevenue")) document.getElementById("crevenue").value = response.revenue || '';
    if (document.getElementById("cccashback")) document.getElementById("cccashback").value = response.ccashback || '';
    if (document.getElementById("ccrevenue")) document.getElementById("ccrevenue").value = response.crevenue || '';
    if (document.getElementById("brecived")) document.getElementById("brecived").value = response.recived_amt || '';
    if (document.getElementById("source_table")) document.getElementById("source_table").value = response.source_table || '';
    if (document.getElementById("unique_searchInput")) document.getElementById("unique_searchInput").value = response.source_table || '';
    if (document.getElementById("invoice_raised")) document.getElementById("invoice_raised").value = response.invoice_raise || '';
    if (document.getElementById("update_user_checkbox")) document.getElementById("update_user_checkbox").checked = response.update_in_user_table == "1";
    if (document.getElementById("update_invoice_checkbox")) document.getElementById("update_invoice_checkbox").checked = response.update_in_invoice_table == "1";
    if (document.getElementById("cashbackverify")) document.getElementById("cashbackverify").checked = response.cashbackverify == "1";
    if (document.getElementById("selected_user_label")) document.getElementById("selected_user_label").innerHTML = response.source_table || '';

    // Set status radio button
    if (response.astatus) {
      const statusRadios = document.getElementsByName("cstatus");
      for (let i = 0; i < statusRadios.length; i++) {
        if (statusRadios[i].value === response.astatus) {
          statusRadios[i].checked = true;
          break;
        }
      }
    }

    // Show current file if exists
    if (response.document_path && response.document_path.trim() !== '') {
      const currentFileDisplay = document.getElementById('currentFileDisplay');
      const currentFileName = document.getElementById('currentFileName');
      const currentFileDownload = document.getElementById('currentFileDownload');
      
      if (currentFileDisplay && currentFileName && currentFileDownload) {
        // Extract filename from path
        const fileName = response.document_path.split('/').pop();
        currentFileName.textContent = fileName;
        // Use absolute URL path
        currentFileDownload.href = '/incentiveapp_integration/userlogin1/superadmin/' + response.document_path;
        currentFileDisplay.style.display = 'block';
      }
    } else {
      const currentFileDisplay = document.getElementById('currentFileDisplay');
      if (currentFileDisplay) {
        currentFileDisplay.style.display = 'none';
      }
    }

    // Show modal - custom display with flexbox
    if (modal) {
      modal.style.display = 'flex';
      modal.style.flexDirection = 'column';
    }

  } catch (error) {
    console.error('Error loading booking data:', error);
    Swal.fire({
      toast: true,
      position: 'bottom',
      icon: 'error',
      title: 'Error loading booking data: ' + error.message,
      showConfirmButton: false,
      timer: 3000,
      timerProgressBar: true
    });
  }
};

// Update User Ajax Request
if (updateForm) {
  updateForm.addEventListener("submit", async (e) => {
    e.preventDefault();

    const formData = new FormData(updateForm);
    formData.append("update", 1);

    if (updateForm.checkValidity() === false) {
      e.preventDefault();
      e.stopPropagation();
      updateForm.classList.add("was-validated");
      return false;
    } else {
      document.getElementById("edit-user-btn").value = "Please Wait...";

      const data = await fetch("action.php", {
        method: "POST",
        body: formData,
      });
      const response = await data.text();

      // Show toast notification instead of HTML alert
      if (response.includes('success') || response.includes('successfully')) {
        Swal.fire({
          toast: true,
          position: 'bottom',
          icon: 'success',
          title: 'Booking updated successfully!',
          showConfirmButton: false,
          timer: 3000,
          timerProgressBar: true
        });
      } else {
        Swal.fire({
          toast: true,
          position: 'bottom',
          icon: 'error',
          title: 'Something went wrong!',
          showConfirmButton: false,
          timer: 3000,
          timerProgressBar: true
        });
      }

      const editBtn = document.getElementById("edit-user-btn");
      if (editBtn) editBtn.value = "Update Booking";
      if (updateForm) {
        updateForm.reset();
        updateForm.classList.remove("was-validated");
      }
      if (editModal) editModal.hide();
      if (tbody) fetchAllUsers(true);
    }
  });
}

// Delete User Ajax Request
if (tbody) {
  tbody.addEventListener("click", (e) => {
    if (e.target && e.target.matches("a.deleteLink")) {
      e.preventDefault();
      let id = e.target.getAttribute("id");
      deleteUser(id);
    }
  });
}

const deleteUser = async (id) => {
  const data = await fetch(`action.php?delete=1&id=${id}`, {
    method: "GET",
  });
  const response = await data.text();

  // Show toast notification instead of HTML alert
  if (response.includes('success') || response.includes('successfully')) {
    Swal.fire({
      toast: true,
      position: 'bottom',
      icon: 'success',
      title: 'Booking deleted successfully!',
      showConfirmButton: false,
      timer: 3000,
      timerProgressBar: true
    });
  } else {
    Swal.fire({
      toast: true,
      position: 'bottom',
      icon: 'error',
      title: 'Something went wrong!',
      showConfirmButton: false,
      timer: 3000,
      timerProgressBar: true
    });
  }

  if (tbody) fetchAllUsers(true);
};

//this function is for filter the rows and calcualte the total filter rows amt
var isFilterApplied = false; // Flag to track if filter is applied
var activeFilters = [];

// Function to apply the filters
function applyFilters() {
  var filterInputs = [
    { id: "filterID", columnIndex: 0 },
    { id: "filterBookingDateStart", columnIndex: 1 },
    { id: "filterBookingDateEnd", columnIndex: 1 },
    { id: "filterMonth", columnIndex: 2 },
    { id: "filterBuilder", columnIndex: 3 },
    { id: "filterProject", columnIndex: 4 },
    { id: "filterCustumername", columnIndex: 5 },
    { id: "filterContactnumber", columnIndex: 6 },
    { id: "filterEmail", columnIndex: 7 },
    { id: "filterType", columnIndex: 8 },
    { id: "filterUnit", columnIndex: 9 },
    { id: "filterSize", columnIndex: 10 },
    { id: "filterAgreement", columnIndex: 11 },
    { id: "filterCommission", columnIndex: 12 },
    { id: "filterTrevenue", columnIndex: 13 },
    { id: "filterCashBack", columnIndex: 14 },
    { id: "filterActualRevenue", columnIndex: 15 },
    { id: "filterStatus", columnIndex: 16 },
    { id: "filterReceived", columnIndex: 17 },
    { id: "filterSales", columnIndex: 18 },
  ];

  activeFilters = []; // Reset active filters

  $("#filterdata tr").each(function () {
    var row = $(this);
    var showRow = true;

    filterInputs.forEach(function (inputInfo) {
      var input = $("#" + inputInfo.id);
      var rawValue = input.val();
      var cellValue = row.find("td:eq(" + inputInfo.columnIndex + ")").text().toLowerCase();

      // Handle date range filtering
      if (inputInfo.id === "filterBookingDateStart" || inputInfo.id === "filterBookingDateEnd") {
        var startDate = new Date($("#filterBookingDateStart").val());
        var endDate = new Date($("#filterBookingDateEnd").val());
        var bookingDate = new Date(cellValue);

        if (!isNaN(startDate) && !isNaN(endDate)) {
          if (bookingDate < startDate || bookingDate > endDate) {
            showRow = false;
            return false; // Break out of forEach loop
          }
        }
      } else if (Array.isArray(rawValue)) {
        // Handle Select2 multi-select values (returns array)
        if (rawValue.length > 0) {
          var lowerValues = rawValue.map(function(v) { return v.toLowerCase(); });
          var hasMatch = lowerValues.some(function(v) { return cellValue.indexOf(v) !== -1; });
          if (!hasMatch) {
            showRow = false;
            return false;
          }
          activeFilters.push(rawValue.join(','));
        }
      } else {
        var filterValue = (rawValue || '').toLowerCase();
        if (filterValue && cellValue.indexOf(filterValue) === -1) {
          showRow = false;
          return false; // Break out of forEach loop
        }
        if (filterValue.trim() !== "") {
          activeFilters.push(filterValue);
        }
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

  // Loop through visible rows and calculate totals
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

  // Update the totals in your UI (adjust the IDs accordingly)
  $("#counter").text(counterRow);
  $("#totalTotalRevenue").text(totalTotalRevenue.toLocaleString());
  $("#totalActualRevenue").text(totalActualRevenue.toLocaleString());

  applyCustomFilter();
}
applyCustomFilter();
// Function to apply custom filter based on active filters
function applyCustomFilter() {
  $("#filterdata tr").hide(); // Hide all rows
  $(".custom-filtered-row").show(); // Show only the rows with the custom class

  isFilterApplied = true; // Mark filter as applied
}

// Show filter modal when button is clicked
$(".filterable .btn-filter1").click(function () {
  $("#filterModal").modal("show");
});

// Apply filters and update the table
$("#applyFiltersBtn").click(function () {
  console.log("Apply Filters button clicked"); // Check if this message is logged
  applyFilters();
  $("#emptotaldata").css("display", "block");
  filterModal.hide();
});

// Clear filters and update the table when modal is closed
$("#filterModal").on("hidden.bs.modal", function () {
  $(".filterable .filters input").val("");
  if (!isFilterApplied) {
    $("#filterdata tr").show();
  }
  applyFilters(); // Reapply filters if they were applied
});

// Close filters and update the table
$("#closeFilter").click(function () {
  applyFilters();
  $("#filterModal").modal("hide");
});


// Cancle filters and update the table
$("#cancleFilter").click(function () {
  applyFilters();
  $("#filterModal").modal("hide");
});
// this script is for clear the filter which is applied
$(document).ready(function () {
  // Clear Filters button click event
  $("#clearFiltersBtn").click(function () {
    // Clear all filter inputs (text inputs)
    $("#filterBookingDateStart, #filterBookingDateEnd, #filterMonth, #filterSize, #filterAgreement, #filterCommission, #filterTrevenue, #filterCashBack, #filterActualRevenue, #filterReceived").val("");
    // Clear all Select2 multi-select filters and trigger change to update UI
    $("#filterID, #filterBuilder, #filterProject, #filterContactnumber, #filterCustumername, #filterEmail, #filterType, #filterUnit, #filterStatus, #filterSales").val(null).trigger('change');
    $("#emptotaldata").css("display", "none");
  });
});
$("#clearFiltersBtn").click(function () {
  applyFilters();
  $("#filterModal").modal("hide");
});

// this is for export the data from the database
function downloadCsv(data, filename) {
  var fixedColumnNames = [
    "ID",
    "Booking Date",
    "Month",
    "Builder",
    "Project",
    "Customer Name",
    "Contact No.",
    "Email Id",
    "Type",
    "Unit No.",
    "Size",
    "Agreement Value",
    "Commission %",
    "Total Revenue",
    "CashBack %",
    "Actual Revenue",
    "Status",
    "Received Amt.",
    "Sales Person"
  ];

  var csvContent = "data:text/csv;charset=utf-8," + fixedColumnNames.join(",") + "\n";

  data.forEach(function (row) {
    csvContent += row.join(",") + "\n";
  });

  var encodedUri = encodeURI(csvContent);
  var link = document.createElement("a");
  link.setAttribute("href", encodedUri);
  link.setAttribute("download", filename);
  document.body.appendChild(link);
  link.click();
}

$("#downloadCsvBtn").click(function () {
  var csvData = [];

  // Loop through table rows to collect data
  $("#filterdata tr.custom-filtered-row").each(function () {
    var rowData = [];
    var isExcludedHeaderRow = false;

    // Loop through table cells
    $(this).find("td").each(function (index) {
      var cellText = $(this).text().trim();

      if (
        cellText === "Financial Year/Bookings:" ||
        cellText === "Total Revenue:" ||
        cellText === "Actual Revenue:" ||
        cellText === "Recived Amount:" ||
        cellText === "Amount To be Pay:" ||
        cellText === "Total Paid Amt:"
      ) {
        isExcludedHeaderRow = true;
        return false; // Stop checking cells once an excluded header is found
      }

      rowData.push(cellText);
    });

    if (!isExcludedHeaderRow) {
      // Exclude the row if it matches the specific content
      var rowText = rowData.join(',');
      if (rowText !== "*,*,*,*,*,*,*,*,*,*,*,*,*,*,*,*,*,*") {
        csvData.push(rowData);
      }
    }
  });

  downloadCsv(csvData, "filtered_data.csv");
});
$(document).on("click", ".fold-table tr.view", function () {
  if ($(this).hasClass("open")) {
    $(this).removeClass("open").next(".fold").removeClass("open");
    $(this).removeClass("financialtrsticky");
  } else {
    $(".fold-table tr.view").removeClass("open").next(".fold").removeClass("open").removeClass("financialtrsticky");
    $(this).addClass("open").next(".fold").addClass("open");
    $(".fold-table tr.view.open").addClass("financialtrsticky");
  }
});