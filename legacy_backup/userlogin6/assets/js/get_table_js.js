// <!-- active row script start -->
document.addEventListener('DOMContentLoaded', function () {
    const table = document.getElementById('myTable');
    const rows = table.querySelectorAll('tbody tr');
    function clearActiveRows() {
        rows.forEach(row => {
            row.classList.remove('active-row');
        });
    }
    rows.forEach(row => {
        row.addEventListener('click', function () {
            clearActiveRows(); 
            this.classList.add('active-row'); 
        });
    });
});
// <!-- active row script end -->
// <!-- search bar script for table start-->
document.addEventListener('DOMContentLoaded', function () {
const searchTerms = document.querySelectorAll('.searchTerm');  // Select all search inputs
const rows = document.querySelectorAll('#myTable tbody tr');
const tableBody = document.querySelector('#myTable tbody');

function showNoDataRow() {
    if (!document.getElementById('noDataRow')) {
        const noDataRow = document.createElement('tr');
        noDataRow.id = 'noDataRow';
        const noDataCell = document.createElement('td');
        noDataCell.colSpan = document.querySelectorAll('#myTable thead th').length;
        noDataCell.innerText = 'Data not found';
        noDataRow.appendChild(noDataCell);
        tableBody.appendChild(noDataRow);
    }
}

function removeNoDataRow() {
    const noDataRow = document.getElementById('noDataRow');
    if (noDataRow) {
        noDataRow.remove();
    }
}

function filterRows() {
    let found = false;

    rows.forEach(row => {
        let rowText = row.innerText.toLowerCase();
        let showRow = true;

        // Loop through all search inputs and apply each filter
        searchTerms.forEach(input => {
            const searchTerm = input.value.toLowerCase();
            if (searchTerm && !rowText.includes(searchTerm)) {
                showRow = false;
            }
        });

        if (showRow) {
            row.style.display = '';
            found = true;
        } else {
            row.style.display = 'none';
        }
    });

    if (!found) {
        showNoDataRow();
    } else {
        removeNoDataRow();
    }
}

function debounce(func, delay) {
    let debounceTimeout;
    return function () {
        clearTimeout(debounceTimeout);
        debounceTimeout = setTimeout(func, delay);
    };
}
const debouncedFilterRows = debounce(filterRows, 300);
// Add event listeners to all search inputs
searchTerms.forEach(input => {
    input.addEventListener('input', debouncedFilterRows);
});
});
// <!-- search bar script for table end-->
// <!-- column selector script for table start-->
document.addEventListener('DOMContentLoaded', function () {
    function populateDropdown() {
        const columnSelector = document.getElementById('columnSelector');
        const headers = document.querySelectorAll('#myTable thead th');
        headers.forEach((header, index) => {
            const columnIndex = index + 1; 
            const label = document.createElement('label');
            label.innerHTML = `<input type="checkbox" value="${columnIndex}" checked> ${header.innerText}`;
            columnSelector.appendChild(label);
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
                    cell.style.display = isChecked ? '' : 'none'; 
                });
            }
        });
    }
    populateDropdown();
    toggleColumnVisibility();
});
// <!-- column selector script for table end-->