// Base endpoint - using local action.php in superadmin folder
const endpointBase = './action.php';

const addForm = document.getElementById("add-user-form");
const updateForm = document.getElementById("edit-user-form");
const showAlert = document.getElementById("showAlert");
let addModal = null;
let editModal = null;
try { addModal = new bootstrap.Modal(document.getElementById("addNewUserModal")); } catch (e) { }
try { editModal = new bootstrap.Modal(document.getElementById("editUserModal")); } catch (e) { }
const tbody = document.getElementById("expensesdata");

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
    }

    const btn = document.getElementById("add-user-btn");
    if (btn) btn.value = "Please Wait...";

    try {
      const resp = await fetch(endpointBase, { method: "POST", body: formData });
      if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
      const text = await resp.text();
      if (showAlert) showAlert.innerHTML = text;
      if (btn) btn.value = "Add Booking";
      addForm.reset();
      addForm.classList.remove("was-validated");
      if (addModal && typeof addModal.hide === 'function') addModal.hide();
      fetchAllUsers();
    } catch (err) {
      console.error('Add booking failed', err);
      if (showAlert) showAlert.innerHTML = `<div class="alert alert-danger">Error: ${err.message}</div>`;
      if (btn) btn.value = "Add Booking";
    }
  });
}

// Fetch All Users Ajax Request
async function fetchAllUsers() {
  try {
    const resp = await fetch(endpointBase + '?read_expenses=1', { method: 'GET', cache: 'no-store' });
    if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
    const ct = (resp.headers.get('content-type') || '').toLowerCase();
    if (ct.includes('application/json')) {
      const data = await resp.json();
      // Check if data is an error response
      if (data.error) {
        throw new Error(data.error);
      }
      renderExpensesFromJson(data);
    } else {
      const text = await resp.text();
      if (tbody) tbody.innerHTML = text;
      // Reinitialize pagination after HTML data is loaded
      if (typeof window.initExpensePagination === 'function') {
        window.initExpensePagination();
      }
    }
  } catch (err) {
    console.error('fetchAllUsers error', err);
    if (showAlert) showAlert.innerHTML = `<div class="alert alert-danger">Failed to load expenses data: ${err.message}. Please check your network connection or contact support.</div>`;
    // Show empty table message
    if (tbody) tbody.innerHTML = '<tr><td colspan="9" style="text-align:center; padding:20px;">Unable to load expenses data. Please refresh the page.</td></tr>';
  }
};
fetchAllUsers();

// Edit User Ajax Request
if (tbody) {
  tbody.addEventListener("click", (e) => {
    if (e.target && e.target.matches("a.editLink")) {
      e.preventDefault();
      let id = e.target.getAttribute("id");
      editUser(id);
    }
  });
} else {
  // fallback: delegate from document
  document.addEventListener('click', (e) => {
    if (e.target && e.target.matches && e.target.matches('a.editLink')) {
      e.preventDefault();
      let id = e.target.getAttribute('id');
      editUser(id);
    }
  });
}

async function editUser(id) {
  try {
    const resp = await fetch(`${endpointBase}?edit=1&id=${encodeURIComponent(id)}`, { method: 'GET' });
    if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
    const ct = (resp.headers.get('content-type') || '').toLowerCase();
    let response;
    if (ct.includes('application/json')) response = await resp.json();
    else response = parseHtmlToObject(await resp.text());

    if (response) {
      const setIf = (sel, val) => { const el = document.getElementById(sel); if (el) el.value = val || ''; };
      setIf('id', response.id);
      setIf('bdate', response.booking_date);
      setIf('bmonth', response.booking_month);
      setIf('developer', response.builder);
      setIf('cagreement', response.agreement_value);
      setIf('cname', response.customer_name);
      setIf('tproject', response.project_type);
      setIf('unitno', response.unit_no);
      setIf('bproject', response.project);
      if (editModal && typeof editModal.show === 'function') editModal.show();
    }
  } catch (err) {
    console.error('editUser failed', err);
    if (showAlert) showAlert.innerHTML = `<div class="alert alert-danger">Error loading record: ${err.message}</div>`;
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
    }

    const btn = document.getElementById("edit-user-btn");
    if (btn) btn.value = "Please Wait...";

    try {
      const resp = await fetch(endpointBase, { method: 'POST', body: formData });
      if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
      const text = await resp.text();
      if (showAlert) showAlert.innerHTML = text;
      if (btn) btn.value = "Update Booking";
      updateForm.reset();
      updateForm.classList.remove("was-validated");
      if (editModal && typeof editModal.hide === 'function') editModal.hide();
      fetchAllUsers();
    } catch (err) {
      console.error('Update failed', err);
      if (showAlert) showAlert.innerHTML = `<div class="alert alert-danger">Error: ${err.message}</div>`;
      if (btn) btn.value = "Update Booking";
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

async function deleteUser(id) {
  try {
    const resp = await fetch(`${endpointBase}?delete=1&id=${encodeURIComponent(id)}`, { method: 'GET' });
    if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
    const text = await resp.text();
    if (showAlert) showAlert.innerHTML = text;
    fetchAllUsers();
  } catch (err) {
    console.error('deleteUser failed', err);
    if (showAlert) showAlert.innerHTML = `<div class="alert alert-danger">Delete failed: ${err.message}</div>`;
  }
};

// Helper: naive HTML->object parser for single-record HTML responses
function parseHtmlToObject(html) {
  try {
    // Attempt to extract value attributes from inputs if server returned a small HTML fragment
    const tmp = document.createElement('div');
    tmp.innerHTML = html;
    const inputs = tmp.querySelectorAll('input, select, textarea');
    const obj = {};
    inputs.forEach(i => { if (i.name) obj[i.name] = i.value; if (i.id) obj[i.id] = i.value; });
    return Object.keys(obj).length ? obj : null;
  } catch (e) { return null; }
}

function renderExpensesFromJson(items) {
  if (!tbody) return;
  if (!Array.isArray(items)) { tbody.innerHTML = '<tr><td colspan="10">No data</td></tr>'; return; }
  tbody.innerHTML = items.map(row => `
    <tr class="view data-row" 
        data-financial-year="${escapeHtml(row.financial_year)}"
        data-facebook-exp="${escapeHtml(row.facebook_exp)}"
        data-google-exp="${escapeHtml(row.google_exp)}"
        data-hr-exp="${escapeHtml(row.hr_exp)}"
        data-it-exp="${escapeHtml(row.it_exp)}"
        data-shi-exp="${escapeHtml(row.shi_exp)}"
        data-accounts-exp="${escapeHtml(row.accounts_exp)}"
        data-others-exp="${escapeHtml(row.others_exp)}">
      <td>${escapeHtml(row.financial_year)}</td>
      <td>${escapeHtml(row.facebook_exp)}</td>
      <td>${escapeHtml(row.google_exp)}</td>
      <td>${escapeHtml(row.hr_exp)}</td>
      <td>${escapeHtml(row.it_exp)}</td>
      <td>${escapeHtml(row.shi_exp)}</td>
      <td>${escapeHtml(row.accounts_exp)}</td>
      <td>${escapeHtml(row.others_exp)}</td>
      <td class="chevron-col">
        <div class="chevron-icon">
          <i class="bi bi-chevron-right"></i>
        </div>
      </td>
    </tr>
  `).join('');

  // Reinitialize pagination after data is loaded
  if (typeof window.initExpensePagination === 'function') {
    window.initExpensePagination();
  }
}

function escapeHtml(s) { return String(s === undefined || s === null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])); }

// Handle row click to expand/collapse expense details
$(document).ready(function () {
  // Toggle details when clicking on data rows
  $(document).on('click', '.data-row', function (e) {
    // Only allow expansion on screens 1024px and below
    if (window.innerWidth > 1024) {
      return; // Do nothing on large screens
    }

    const $clickedRow = $(this);
    const $existingDetails = $clickedRow.next('.details-row');

    // Check if this row is already expanded
    if ($existingDetails.length > 0) {
      // Collapse this row
      $clickedRow.removeClass('expanded');
      $existingDetails.remove();
    } else {
      // Close any other open rows
      $('.data-row.expanded').removeClass('expanded');
      $('.details-row').remove();

      // Expand this row
      $clickedRow.addClass('expanded');

      // Get data from row attributes
      const rowData = {
        financialYear: $clickedRow.data('financial-year'),
        facebookExp: $clickedRow.data('facebook-exp'),
        googleExp: $clickedRow.data('google-exp'),
        hrExp: $clickedRow.data('hr-exp'),
        itExp: $clickedRow.data('it-exp'),
        shiExp: $clickedRow.data('shi-exp'),
        accountsExp: $clickedRow.data('accounts-exp'),
        othersExp: $clickedRow.data('others-exp')
      };

      // Create details HTML
      const detailsHTML = `
        <tr class="details-row">
          <td colspan="9">
            <div class="details-container">
              <div class="details-title">Expense Details</div>
              <div class="detail-item">
                <span class="detail-label">Financial Year:</span>
                <span class="detail-value">${rowData.financialYear}</span>
              </div>
              <div class="detail-item">
                <span class="detail-label">Facebook Exp.:</span>
                <span class="detail-value">${rowData.facebookExp}</span>
              </div>
              <div class="detail-item">
                <span class="detail-label">Google Exp.:</span>
                <span class="detail-value">${rowData.googleExp}</span>
              </div>
              <div class="detail-item">
                <span class="detail-label">HR Exp.:</span>
                <span class="detail-value">${rowData.hrExp}</span>
              </div>
              <div class="detail-item">
                <span class="detail-label">IT Exp.:</span>
                <span class="detail-value">${rowData.itExp}</span>
              </div>
              <div class="detail-item">
                <span class="detail-label">SHI Exp.:</span>
                <span class="detail-value">${rowData.shiExp}</span>
              </div>
              <div class="detail-item">
                <span class="detail-label">Accounts Exp.:</span>
                <span class="detail-value">${rowData.accountsExp}</span>
              </div>
              <div class="detail-item">
                <span class="detail-label">Others Exp.:</span>
                <span class="detail-value">${rowData.othersExp}</span>
              </div>
            </div>
          </td>
        </tr>
      `;

      // Insert details row after the clicked row
      $clickedRow.after(detailsHTML);
    }
  });
});

// Pagination script for expenses
$(document).ready(function () {
  let currentPage = 1;
  let rowsPerPage = 10;
  let allRows = [];

  function initPagination() {
    // Get all data rows (excluding details rows)
    allRows = $('#expensesdata tr.data-row').toArray();

    // Initialize pagination
    updatePagination();
  }

  // Expose initPagination globally so it can be called after data loads
  window.initExpensePagination = initPagination;

  function updatePagination() {
    const totalRows = allRows.length;
    const totalPages = Math.ceil(totalRows / rowsPerPage);

    // Update row info
    const startRow = totalRows === 0 ? 0 : (currentPage - 1) * rowsPerPage + 1;
    const endRow = Math.min(currentPage * rowsPerPage, totalRows);
    $('#expenseRowInfo').text(`Showing ${startRow} to ${endRow} of ${totalRows} entries`);

    // Show/hide rows
    allRows.forEach((row, index) => {
      const rowPage = Math.floor(index / rowsPerPage) + 1;
      if (rowPage === currentPage) {
        $(row).show();
      } else {
        $(row).hide();
        // Also hide any expanded details for hidden rows
        $(row).next('.details-row').remove();
        $(row).removeClass('expanded');
      }
    });

    // Update pagination buttons
    $('#expensePrevButton').prop('disabled', currentPage === 1);
    $('#expenseNextButton').prop('disabled', currentPage === totalPages || totalPages === 0);

    // Generate page numbers
    generatePageNumbers(currentPage, totalPages);
  }

  function generatePageNumbers(current, total) {
    const $pageNumbers = $('#expensePageNumbers');
    $pageNumbers.empty();

    if (total === 0) return;

    // Show max 5 page numbers with current page in the middle when possible
    let startPage = Math.max(1, current - 2);
    let endPage = Math.min(total, startPage + 4);

    // Adjust if we're near the end
    if (endPage - startPage < 4) {
      startPage = Math.max(1, endPage - 4);
    }

    for (let i = startPage; i <= endPage; i++) {
      const btn = $(`<button>${i}</button>`);
      if (i === current) {
        btn.addClass('active');
      }
      btn.on('click', function () {
        currentPage = i;
        updatePagination();
      });
      $pageNumbers.append(btn);
    }
  }

  // Previous button
  $('#expensePrevButton').on('click', function () {
    if (currentPage > 1) {
      currentPage--;
      updatePagination();
    }
  });

  // Next button
  $('#expenseNextButton').on('click', function () {
    const totalPages = Math.ceil(allRows.length / rowsPerPage);
    if (currentPage < totalPages) {
      currentPage++;
      updatePagination();
    }
  });

  // Jump to page
  $('#expenseJumpButton').on('click', function () {
    const pageNum = parseInt($('#expenseJumpInput').val());
    const totalPages = Math.ceil(allRows.length / rowsPerPage);

    if (pageNum >= 1 && pageNum <= totalPages) {
      currentPage = pageNum;
      updatePagination();
      $('#expenseJumpInput').val('');
    } else {
      alert(`Please enter a page number between 1 and ${totalPages}`);
    }
  });

  // Enter key on jump input
  $('#expenseJumpInput').on('keypress', function (e) {
    if (e.which === 13) {
      $('#expenseJumpButton').click();
    }
  });

  // Initialize pagination on page load
  initPagination();

  // ===================
  // SEARCH FUNCTIONALITY
  // ===================
  $('#searchInput').on('keyup', function () {
    const searchTerm = $(this).val().toLowerCase();

    $('#expensesdata tr.data-row').each(function () {
      const $row = $(this);
      const rowText = $row.text().toLowerCase();

      if (rowText.includes(searchTerm)) {
        $row.show();
      } else {
        $row.hide();
        // Hide expanded details if row is hidden
        $row.next('.details-row').remove();
        $row.removeClass('expanded');
      }
    });

    // Update pagination with visible rows
    allRows = $('#expensesdata tr.data-row:visible').toArray();
    currentPage = 1;
    updatePagination();
  });


  // ==========================
  // ROWS PER PAGE SELECTOR
  // ==========================
  $('#rowSelector').on('change', function () {
    rowsPerPage = parseInt($(this).val());
    currentPage = 1;
    updatePagination();
  });
});