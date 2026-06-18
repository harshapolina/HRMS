const addForm = document.getElementById("add-user-form");
const updateForm = document.getElementById("edit-user-form");
const showAlert = document.getElementById("showAlert");
const addModal = new bootstrap.Modal(document.getElementById("addNewUserModal"));
const editModal = new bootstrap.Modal(document.getElementById("editUserModal"));
const tbody = document.getElementById("incentiveuser");

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
    document.getElementById("add-user-btn").value = "Add Employee";
    addForm.reset();
    addForm.classList.remove("was-validated");
    addModal.hide();
    fetchAllUsers();
  }
});

// Fetch All Users Ajax Request
const fetchAllUsers = async () => {
  const data = await fetch("../hrlogin/action.php?read=1", {
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
  const data = await fetch(`../hrlogin/action.php?edit=1&id=${id}`, {
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
  updateSelectedTags();
  updateHiddenInput();
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

    const data = await fetch("../hrlogin/action.php", {
      method: "POST",
      body: formData,
    });
    const response = await data.text();

    showAlert.innerHTML = response;
    document.getElementById("edit-user-btn").value = "Update";
    updateForm.reset();
    updateForm.classList.remove("was-validated");
    editModal.hide();
    fetchAllUsers();
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
  const data = await fetch(`../hrlogin/action.php?delete=1&id=${id}`, {
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
    "user_type",
    "Action"
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
  $("#incentiveuser tr.custom-filtered-row").each(function() {
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
document.addEventListener("DOMContentLoaded", function() {
  // Function to filter table rows based on status
  const filterTableRows = (status) => {
    const rows = document.querySelectorAll("#incentiveuser tr");
    rows.forEach(row => {
      const statusCell = row.querySelector("td:nth-child(2)").textContent.trim();
      if ((status === "active" && statusCell === "Active") || 
          (status === "inactive" && statusCell === "Inactive")) {
        row.style.display = ""; // Show the row
      } else {
        row.style.display = "none"; // Hide the row
      }
    });
  };

  // Add event listeners to the counter buttons
  document.getElementById("activeCounter").addEventListener("click", () => {
    filterTableRows("active");
  });

  document.getElementById("inactiveCounter").addEventListener("click", () => {
    filterTableRows("inactive");
  });
});
// Note: These script tags must be included in your HTML file, not inside this JS file.
// Example (place in your HTML <head> or before </body>):
// <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
// <script src="hrmain.js"></script>