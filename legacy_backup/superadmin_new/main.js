const addForm = document.getElementById("add-user-form");
const updateForm = document.getElementById("edit-user-form");
const showAlert = document.getElementById("showAlert");
const addModal = new bootstrap.Modal(document.getElementById("addNewUserModal"));
const editModal = new bootstrap.Modal(document.getElementById("editUserModal"));
const tbody = document.getElementById("pagedata");
var filterModal = new bootstrap.Modal(document.getElementById("filterModal"));

// Add New User Ajax Request
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
    showAlert.innerHTML = response;
    document.getElementById("add-user-btn").value = "Add Booking";
    addForm.reset();
    addForm.classList.remove("was-validated");
    addModal.hide();
    fetchAllUsers(true);
  }
});

// Fetch All Users Ajax Request
const fetchAllUsers = async (flag) => {
  const data = await fetch("action.php?read=1", {
    method: "GET",
  });
  const response = await data.text();
  tbody.innerHTML = response;
  if (flag){
      applyFilters();
  }
};
fetchAllUsers(false);

// Edit User Ajax Request
tbody.addEventListener("click", (e) => {
  if (e.target && e.target.matches("a.editLink")) {
    e.preventDefault();
    let id = e.target.getAttribute("id");
    editUser(id);
  }
});

const editUser = async (id) => {
  const data = await fetch(`action.php?edit=1&id=${id}`, {
    method: "GET",
  });
  const response = await data.json();
  document.getElementById("id").value = response.id;
  document.getElementById("bdate").value = response.booking_date;
  document.getElementById("bmonth").value = response.booking_month;
  document.getElementById("developer").value = response.builder;
  document.getElementById("bproject").value = response.project;
  document.getElementById("cname").value = response.customer_name;
  document.getElementById("cnumber").value = response.contact_number;
  document.getElementById("cemail").value = response.email_id;
  document.getElementById("tproject").value = response.project_type;
  document.getElementById("unitno").value = response.unit_no;
  document.getElementById("psize").value = response.size;
  document.getElementById("cagreement").value = response.agreement_value;
  document.getElementById("ccashback").value = response.cashback;
  document.getElementById("crevenue").value = response.revenue;
  document.getElementById("cccashback").value = response.ccashback;
  document.getElementById("ccrevenue").value = response.crevenue;
  document.getElementsByName("cstatus").value = response.astatus;
  document.getElementById("brecived").value = response.recived_amt;
  document.getElementById("source_table").value = response.source_table;
  document.getElementById("invoice_raised").value = response.invoice_raise;
  document.getElementById("update_user_checkbox").checked = response.update_in_user_table == "1";
  document.getElementById("update_invoice_checkbox").checked = response.update_in_invoice_table == "1";
  document.getElementById("cashbackverify").checked = response.cashbackverify == "1";
  document.getElementById("selected_user_label").innerHTML = response.source_table;
  // console.log("This is user",response.update_in_user_table); 
  // console.log("This is invoice",response.update_in_invoice_table); 
};

// Update User Ajax Request
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

    showAlert.innerHTML = response;
    document.getElementById("edit-user-btn").value = "Update Booking";
    updateForm.reset();
    updateForm.classList.remove("was-validated");
    editModal.hide();
    fetchAllUsers(true);
  }
});

// Delete User Ajax Request
tbody.addEventListener("click", (e) => {
  if (e.target && e.target.matches("a.deleteLink")) {
    e.preventDefault();
    let id = e.target.getAttribute("id");
    deleteUser(id);
  }
});

const deleteUser = async (id) => {
  const data = await fetch(`action.php?delete=1&id=${id}`, {
    method: "GET",
  });
  const response = await data.text();
  showAlert.innerHTML = response;
  fetchAllUsers(true);
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

    $("#filterdata tr").each(function() {
        var row = $(this);
        var showRow = true;

        filterInputs.forEach(function(inputInfo) {
            var input = $("#" + inputInfo.id);
            var filterValue = input.val().toLowerCase();
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
            } else {
                if (cellValue.indexOf(filterValue) === -1) {
                    showRow = false;
                    return false; // Break out of forEach loop
                }
            }

            if (filterValue.trim() !== "") {
                activeFilters.push(filterValue); // Add non-empty filters to activeFilters
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
  $(".custom-filtered-row").each(function() {
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
  $(".filterable .btn-filter1").click(function() {
    $("#filterModal").modal("show");
  });

  // Apply filters and update the table
  $("#applyFiltersBtn").click(function() {
    console.log("Apply Filters button clicked"); // Check if this message is logged
    applyFilters();
    $("#emptotaldata").css("display", "block");
    filterModal.hide();
});

  // Clear filters and update the table when modal is closed
  $("#filterModal").on("hidden.bs.modal", function() {
    $(".filterable .filters input").val("");
    if (!isFilterApplied) {
      $("#filterdata tr").show();
    }
    applyFilters(); // Reapply filters if they were applied
  });

   // Close filters and update the table
   $("#closeFilter").click(function() {
    applyFilters();
    $("#filterModal").modal("hide");
  });

  
   // Cancle filters and update the table
   $("#cancleFilter").click(function() {
    applyFilters();
    $("#filterModal").modal("hide");
  });
  // this script is for clear the filter which is applied
  $(document).ready(function () {
    // Clear Filters button click event
    $("#clearFiltersBtn").click(function () {
        // Clear all filter inputs
        $("#filterID, #filterBookingDateStart, #filterBookingDateEnd, #filterMonth, #filterBuilder, #filterProject, #filterContactnumber, #filterCustumername, #filterEmail, #filterType, #filterUnit, #filterSize, #filterAgreement, #filterCommission, #filterTrevenue, #filterCashBack, #filterActualRevenue, #filterStatus, #filterReceived, #filterSales").val("");
        $("#emptotaldata").css("display", "none");
    });
  });
  $("#clearFiltersBtn").click(function() {
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

    data.forEach(function(row) {
        csvContent += row.join(",") + "\n";
    });

    var encodedUri = encodeURI(csvContent);
    var link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", filename);
    document.body.appendChild(link);
    link.click();
}

$("#downloadCsvBtn").click(function() {
    var csvData = [];

    // Loop through table rows to collect data
    $("#filterdata tr.custom-filtered-row").each(function() {
        var rowData = [];
        var isExcludedHeaderRow = false;

        // Loop through table cells
        $(this).find("td").each(function(index) {
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
$(document).on("click", ".fold-table tr.view", function() {
    if ($(this).hasClass("open")) {
        $(this).removeClass("open").next(".fold").removeClass("open");
        $(this).removeClass("financialtrsticky");
    } else {
        $(".fold-table tr.view").removeClass("open").next(".fold").removeClass("open").removeClass("financialtrsticky");
        $(this).addClass("open").next(".fold").addClass("open");
        $(".fold-table tr.view.open").addClass("financialtrsticky");
    }
});