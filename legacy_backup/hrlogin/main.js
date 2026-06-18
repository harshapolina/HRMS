const addForm = document.getElementById("add-user-form");
const updateForm = document.getElementById("edit-user-form");
const showAlert = document.getElementById("showAlert");
const addModalEl = document.getElementById("addNewUserModal");
const editModalEl = document.getElementById("editUserModal");
const addModal = addModalEl ? new bootstrap.Modal(addModalEl) : null;
const editModal = editModalEl ? new bootstrap.Modal(editModalEl) : null;
const tbody = document.querySelector("tbody");

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
    showAlert.innerHTML = response;
    document.getElementById("add-user-btn").value = "Add Employee";
    addForm.reset();
    addForm.classList.remove("was-validated");
    if (addModal) addModal.hide();
    fetchAllUsers();
    }
  });
}

// Fetch All Users Ajax Request
const fetchAllUsers = async () => {
  const data = await fetch("action.php?read=1", {
    method: "GET",
  });
  const response = await data.text();
  tbody.innerHTML = response;
};
fetchAllUsers();

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
  document.getElementById("doj").value = response.doj;
  document.getElementById("dob").value = response.dob;
  document.getElementById("ename").value = response.username;
  document.getElementById("eemail").value = response.useremail;
  document.getElementById("enumber").value = response.phonenumber;
  document.getElementById("epass").value = response.epassword;
  document.getElementById("esalary").value = response.salary;
  document.getElementById("etable").value = response.tablename;
  document.getElementById("emid").value = response.employee_id;
  document.getElementById("amountO").value = response.one_amt;
  document.getElementById("amountT").value = response.two_amt;
  document.getElementById("amountTh").value = response.thrid_amt;
  document.getElementById("amountF").value = response.forth_amt;
  document.getElementById("amountFf").value = response.fifth_amt;
  document.getElementById("amountS").value = response.sixth_amt;
  document.getElementById("project_name").value = response.project_name;
  document.getElementById("D_project").value = response.project_type;
  document.getElementById("user_type").value = response.user_type;
  const assignUserArray = response.assign_user.split(",").map(val => val.trim());
  selectedUsers = []; // reset selected list

  // Loop through assignUserArray and match against dropdown items
  assignUserArray.forEach(val => {
    const dropdownItems = document.querySelectorAll('#dropdown .dropdown-item');
    dropdownItems.forEach(item => {
      const label = item.textContent.trim();
      const itemOnClick = item.getAttribute('onclick');
      const match = itemOnClick && itemOnClick.match(/selectUser\('([^']+)'/);
      const value = match ? match[1] : null;
      if (value === val) {
        selectedUsers.push({ value, label });
      }
    });
  });
  document.getElementById("is_active").value = response.is_active;
  
  // Sync the tags for the Multi-Select "Assign User"
  updateSelectedTags();
  updateHiddenInput();
};

/* ================= MULTI-SELECT ASSIGN USER LOGIC ================= */
window.toggleDropdown = function(e) {
  e.stopPropagation();
  const dropdown = document.getElementById("dropdown");
  if (!dropdown) return;
  dropdown.style.display = dropdown.style.display === "block" ? "none" : "block";
};

window.selectUser = function(val, label) {
  if (!selectedUsers.some(u => u.value === val)) {
    selectedUsers.push({ value: val, label: label });
    updateSelectedTags();
    updateHiddenInput();
  }
};

window.removeUser = function(val) {
  selectedUsers = selectedUsers.filter(u => u.value !== val);
  updateSelectedTags();
  updateHiddenInput();
};

function updateSelectedTags() {
  const container = document.getElementById("selected_tags");
  if (!container) return;
  container.innerHTML = "";
  selectedUsers.forEach(user => {
    const tag = document.createElement("div");
    tag.className = "tag";
    tag.innerHTML = `${user.label}<span class="remove" onclick="removeUser('${user.value}')">&times;</span>`;
    container.appendChild(tag);
  });
}

function updateHiddenInput() {
  const hiddenInput = document.getElementById("assign_user_hidden");
  if (!hiddenInput) return;
  const values = selectedUsers.map(u => u.value);
  hiddenInput.value = values.join(",");
}

window.filterDropdown = function() {
  const input = document.getElementById("search_user_input");
  const filter = input ? input.value.toLowerCase() : "";
  const items = document.querySelectorAll("#dropdown .dropdown-item");
  items.forEach(item => {
    const text = item.textContent || item.innerText;
    item.style.display = text.toLowerCase().includes(filter) ? "" : "none";
  });
};

document.addEventListener("click", function(e) {
  const dropdown = document.getElementById("dropdown");
  const multiselect = document.querySelector(".custom-multiselect");
  if (dropdown && multiselect && !multiselect.contains(e.target)) {
    dropdown.style.display = "none";
  }
});

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

    showAlert.innerHTML = response;
    document.getElementById("edit-user-btn").value = "Update";
    updateForm.reset();
    updateForm.classList.remove("was-validated");
    if (editModal) editModal.hide();
    fetchAllUsers();
    }
  });
}

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
  fetchAllUsers();
};
// this is for export the data from the database
function downloadCsv(data, filename) {
  var fixedColumnNames = [
    "ID",
    "Date Of Joining",
    "Date Of Birth",
    "Name",
    "Email",
    "Contact No.",
    "Password",
    "In Hand Salary",
    "Designation",
    "Employee Id",
    "1st Amount",
    "2nd Amount",
    "3rd Amount",
    "4th Amount",
    "5th Amount",
    "6th Amount",
    "Project Name",
    "Project Type",
    "Code",
    "Action"
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
  $("#incentiveuser tr.custom-filtered-row").each(function () {
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
// Counter API Call
const fetchAllUsersActive = async () => {
  const data = await fetch("../hrlogin/action.php?active_users=1", {
    method: "GET",
  });
  const response = await data.json(); // Parse the JSON response

  // Update the Active and Inactive user counts in the HTML
  document.getElementById("activeCount").textContent = response.active;
  document.getElementById("inactiveCount").textContent = response.inactive;
};
fetchAllUsersActive();

// Filter Script 
document.addEventListener("DOMContentLoaded", function () {
  // Function to filter table rows based on status
  const filterTableRowsStatus = (status) => {
    const rows = document.querySelectorAll(".user-data-row");
    rows.forEach(row => {
      const statusCell = row.querySelector("td:nth-child(2)").textContent.trim();
      if ((status === "active" && statusCell === "Active") ||
        (status === "inactive" && statusCell === "Inactive") ||
        status === "all") {
        row.style.display = ""; // Show the row
      } else {
        row.style.display = "none"; // Hide the row
      }
    });
  };

  // Add event listeners to the summary cards
  document.getElementById("activeCount")?.closest('.summary-card')?.addEventListener("click", () => {
    filterTableRowsStatus("active");
  });

  document.getElementById("inactiveCount")?.closest('.summary-card')?.addEventListener("click", () => {
    filterTableRowsStatus("inactive");
  });

  // --- Real-time Text Search ---
  const searchInput = document.getElementById("tableSearchInput");
  if (searchInput) {
    searchInput.addEventListener("keyup", function() {
      const query = this.value.toLowerCase();
      const rows = document.querySelectorAll(".user-data-row, #incentiveuser tr, #trackings tr, #paymenttable tr");
      
      rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(query) ? "" : "none";
      });
    });
  }
});