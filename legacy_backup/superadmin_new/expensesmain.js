const addForm = document.getElementById("add-user-form");
const updateForm = document.getElementById("edit-user-form");
const showAlert = document.getElementById("showAlert");
const addModal = new bootstrap.Modal(document.getElementById("addNewUserModal"));
const editModal = new bootstrap.Modal(document.getElementById("editUserModal"));
const tbody = document.getElementById("expensesdata");

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

    const data = await fetch("../company_expenses/action.php", {
      method: "POST",
      body: formData,
    });
    const response = await data.text();
    showAlert.innerHTML = response;
    document.getElementById("add-user-btn").value = "Add Booking";
    addForm.reset();
    addForm.classList.remove("was-validated");
    addModal.hide();
    fetchAllUsers();
  }
});

// Fetch All Users Ajax Request
const fetchAllUsers = async () => {
  const data = await fetch("../company_expenses/action.php?read=1", {
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
  const data = await fetch(`../company_expenses/action.php?edit=1&id=${id}`, {
    method: "GET",
  });
  const response = await data.json();
  document.getElementById("id").value = response.id;
  document.getElementById("bdate").value = response.booking_date;
  document.getElementById("bmonth").value = response.booking_month;
  document.getElementById("developer").value = response.builder;
  document.getElementById("cagreement").value = response.agreement_value;
  document.getElementById("cname").value = response.customer_name;
  document.getElementById("tproject").value = response.project_type;
  document.getElementById("unitno").value = response.unit_no;
  document.getElementById("bproject").value = response.project;
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

    const data = await fetch("../company_expenses/action.php", {
      method: "POST",
      body: formData,
    });
    const response = await data.text();

    showAlert.innerHTML = response;
    document.getElementById("edit-user-btn").value = "Update Booking";
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
  const data = await fetch(`../company_expenses/action.php?delete=1&id=${id}`, {
    method: "GET",
  });
  const response = await data.text();
  showAlert.innerHTML = response;
  fetchAllUsers();
};
// This is nested Table script
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
// This is nested child Table script
$(document).on("click", ".fold-table1 tr.view1", function() {
    if ($(this).hasClass("open1")) {
      $(this).removeClass("open1").next(".fold1").removeClass("open1");
      $(this).removeClass("financialtrsticky1");
    } else {
      $(".fold-table1 tr.view1").removeClass("open1").next(".fold1").removeClass("open1").removeClass("financialtrsticky1");
      $(this).addClass("open1").next(".fold1").addClass("open1");
      $(".fold-table1 tr.view1.open1").addClass("financialtrsticky1");
    }
});