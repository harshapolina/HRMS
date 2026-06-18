<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Handle AJAX requests BEFORE any HTML output
if (isset($_POST['action']) && ($_POST['action'] === 'update' || $_POST['action'] === 'delete')) {
    // Start output buffering to capture HTML from htmlopen.php
    ob_start();
    require_once 'htmlopen.php';
    ob_end_clean(); // Discard captured HTML output
    
    // UPDATE HANDLER
    if ($_POST['action'] === 'update') {
        $id = $_POST['id'];

        $stmt = $conn->prepare("
        UPDATE company_assets SET
          employee_name = :employee_name,
          phone_number = :phone_number,
          project = :project,
          office_location = :office_location,
          company_laptop = :company_laptop,
          laptop_brand = :laptop_brand,
          laptop_charger = :laptop_charger,
          company_mouse = :company_mouse,
          sim_cad = :sim_cad,
          datesignature = :datesignature
        WHERE id = :id
        ");

        if (!$stmt) {
            echo "error";
            exit;
        }

        $ok = $stmt->execute([
          ':employee_name'   => $_POST['employee_name'],
          ':phone_number'    => $_POST['phone_number'],
          ':project'         => $_POST['project'],
          ':office_location' => $_POST['office_location'],
          ':company_laptop'  => $_POST['company_laptop'],
          ':laptop_brand'    => $_POST['laptop_brand'],
          ':laptop_charger'  => $_POST['laptop_charger'],
          ':company_mouse'   => $_POST['company_mouse'],
          ':sim_cad'         => $_POST['sim_card'],
          ':datesignature'   => $_POST['datesignature'],
          ':id'              => $id
        ]);

        if (!$ok) {
            print_r($stmt->errorInfo());
            exit;
        }

        echo "success";
        exit;
    }

    // DELETE HANDLER
    if ($_POST['action'] === 'delete') {
        $id = $_POST['id'];

        $stmt = $conn->prepare("DELETE FROM company_assets WHERE id = :id");

        if (!$stmt) {
            echo "error";
            exit;
        }

        $ok = $stmt->execute([':id' => $id]);

        echo $ok ? "success" : "error";
        exit;
    }
}

// Normal page load - include HTML
require_once 'htmlopen.php'; // ONLY ONCE

// Check if $conn exists
if (!isset($conn)) {
    die("Database connection not available. Check htmlopen.php includes.");
}

// Pagination Configuration
$allowedLimits = [10, 50, 100, 200, 300];
$recordsPerPage = isset($_GET['limit']) && in_array((int)$_GET['limit'], $allowedLimits) ? (int)$_GET['limit'] : 10;
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($currentPage - 1) * $recordsPerPage;

// Preserve search parameter
$searchParam = isset($_GET['search']) ? trim($_GET['search']) : '';



// ================= FILTER + SEARCH LOGIC =================

$whereParts = [];

// Global Search
if (!empty($searchParam)) {
    $searchEscaped = addslashes($searchParam);

    $whereParts[] = "(
        employee_name LIKE '%$searchEscaped%' OR
        employee_id LIKE '%$searchEscaped%' OR
        phone_number LIKE '%$searchEscaped%' OR
        project LIKE '%$searchEscaped%' OR
        office_location LIKE '%$searchEscaped%' OR
        laptop_brand LIKE '%$searchEscaped%'
    )";
}

// Employee Name
if (!empty($_GET['employee_name'])) {

    $values = explode(',', $_GET['employee_name']);

    $multiParts = [];

    foreach ($values as $v) {
        $v = trim(addslashes($v));
        if ($v !== '') {
            $multiParts[] = "employee_name LIKE '%$v%'";
        }
    }

    if (!empty($multiParts)) {
        $whereParts[] = "(" . implode(" OR ", $multiParts) . ")";
    }
}

// Employee ID
if (!empty($_GET['employee_id'])) {

    $values = explode(',', $_GET['employee_id']);

    $multiParts = [];

    foreach ($values as $v) {
        $v = trim(addslashes($v));
        if ($v !== '') {
            $multiParts[] = "employee_id LIKE '%$v%'";
        }
    }

    if (!empty($multiParts)) {
        $whereParts[] = "(" . implode(" OR ", $multiParts) . ")";
    }
}

// Phone Number
if (!empty($_GET['phone_number'])) {

    $values = explode(',', $_GET['phone_number']);

    $multiParts = [];

    foreach ($values as $v) {
        $v = trim(addslashes($v));
        if ($v !== '') {
            $multiParts[] = "phone_number LIKE '%$v%'";
        }
    }

    if (!empty($multiParts)) {
        $whereParts[] = "(" . implode(" OR ", $multiParts) . ")";
    }
}

// Project
if (!empty($_GET['project'])) {

    $values = explode(',', $_GET['project']);

    $multiParts = [];

    foreach ($values as $v) {
        $v = trim(addslashes($v));
        if ($v !== '') {
            $multiParts[] = "project LIKE '%$v%'";
        }
    }

    if (!empty($multiParts)) {
        $whereParts[] = "(" . implode(" OR ", $multiParts) . ")";
    }
}

// Office Location
if (!empty($_GET['office_location'])) {

    $values = explode(',', $_GET['office_location']);

    $multiParts = [];

    foreach ($values as $v) {
        $v = trim(addslashes($v));
        if ($v !== '') {
            $multiParts[] = "office_location LIKE '%$v%'";
        }
    }

    if (!empty($multiParts)) {
        $whereParts[] = "(" . implode(" OR ", $multiParts) . ")";
    }
}

// Laptop Brand
if (!empty($_GET['laptop_brand'])) {

    $values = explode(',', $_GET['laptop_brand']);

    $multiParts = [];

    foreach ($values as $v) {
        $v = trim(addslashes($v));
        if ($v !== '') {
            $multiParts[] = "laptop_brand LIKE '%$v%'";
        }
    }

    if (!empty($multiParts)) {
        $whereParts[] = "(" . implode(" OR ", $multiParts) . ")";
    }
}

// Date Signature
if (!empty($_GET['date_signature'])) {
    $val = addslashes($_GET['date_signature']);
    $whereParts[] = "DATE(datesignature) = '$val'";
}

// Final WHERE clause
$whereClause = "";

if (!empty($whereParts)) {
    $whereClause = "WHERE " . implode(" AND ", $whereParts);
}

// ================= END FILTER LOGIC =================


// Build base WHERE clause (simple version - no complex filtering yet)
// $whereClause = "";
// $searchCondition = "";

// if (!empty($searchParam)) {
//     $searchEscaped = $conn->real_escape_string($searchParam);
//     $searchCondition = "(employee_name LIKE '%{$searchEscaped}%' OR 
//                         employee_id LIKE '%{$searchEscaped}%' OR 
//                         phone_number LIKE '%{$searchEscaped}%' OR 
//                         project LIKE '%{$searchEscaped}%' OR 
//                         office_location LIKE '%{$searchEscaped}%' OR 
//                         laptop_brand LIKE '%{$searchEscaped}%')";
//     $whereClause = "WHERE " . $searchCondition;
// }

// Get total records count
$countQuery = "SELECT COUNT(*) as total FROM company_assets {$whereClause}";
error_log("Count Query: " . $countQuery);

$countStmt = $conn->prepare($countQuery);
if (!$countStmt) {
    error_log("Count Query Error: " . print_r($conn->errorInfo(), true));
    die("Database error");
}


$countStmt->execute();
$totalRecords = (int)$countStmt->fetchColumn();
$totalPages = $totalRecords > 0 ? ceil($totalRecords / $recordsPerPage) : 1;

// Clamp current page to valid range
if ($currentPage > $totalPages) {
    $currentPage = $totalPages;
}
if ($currentPage < 1) {
    $currentPage = 1;
}

// Recalculate offset after clamping
$offset = ($currentPage - 1) * $recordsPerPage;

// Fetch paginated data
$dataQuery = "SELECT * FROM company_assets {$whereClause} ORDER BY id DESC LIMIT {$recordsPerPage} OFFSET {$offset}";
error_log("Data Query: " . $dataQuery);

$stmt = $conn->prepare($dataQuery);
$stmt->execute();
$result = $stmt;
$assets_data = [];

if ($result) {
    $assets_data = $result->fetchAll(PDO::FETCH_ASSOC);

}


// Calculate display range
$startRecord = $totalRecords > 0 ? $offset + 1 : 0;
$endRecord = min($offset + $recordsPerPage, $totalRecords);

error_log("Total Records: {$totalRecords}, Current Page: {$currentPage}, Total Pages: {$totalPages}");
error_log("Assets Data Count: " . count($assets_data));
?>

<?php
// ===== CA FILTER DROPDOWN DATA =====
// Fetch distinct values for searchable dropdowns (NO pagination, NO filters applied - full list)
$ddEmployeeNames = [];
$ddProjects      = [];
$ddLocations     = [];
$ddBrands        = [];
$ddEmployeeIds  = [];
$ddPhoneNumbers = [];
try {
    $ddStmt = $conn->query("SELECT DISTINCT employee_name FROM company_assets WHERE employee_name IS NOT NULL AND employee_name <> '' ORDER BY employee_name ASC");
    $ddEmployeeNames = $ddStmt ? $ddStmt->fetchAll(PDO::FETCH_COLUMN) : [];

    $ddStmt = $conn->query("SELECT DISTINCT employee_id FROM company_assets WHERE employee_id IS NOT NULL AND employee_id <> '' ORDER BY employee_id ASC");
    $ddEmployeeIds = $ddStmt ? $ddStmt->fetchAll(PDO::FETCH_COLUMN) : [];

    $ddStmt = $conn->query("SELECT DISTINCT phone_number FROM company_assets WHERE phone_number IS NOT NULL AND phone_number <> '' ORDER BY phone_number ASC");
    $ddPhoneNumbers = $ddStmt ? $ddStmt->fetchAll(PDO::FETCH_COLUMN) : [];

    /* ✅ CORRECT PROJECT QUERY */
$ddStmt = $conn->query("SELECT DISTINCT project 
                        FROM company_assets 
                        WHERE project IS NOT NULL 
                        AND project <> '' 
                        ORDER BY project ASC");
$ddProjects = $ddStmt ? $ddStmt->fetchAll(PDO::FETCH_COLUMN) : [];

    $ddStmt = $conn->query("SELECT DISTINCT office_location FROM company_assets WHERE office_location IS NOT NULL AND office_location <> '' ORDER BY office_location ASC");
    $ddLocations = $ddStmt ? $ddStmt->fetchAll(PDO::FETCH_COLUMN) : [];

    $ddStmt = $conn->query("SELECT DISTINCT laptop_brand FROM company_assets WHERE laptop_brand IS NOT NULL AND laptop_brand <> '' ORDER BY laptop_brand ASC");
    $ddBrands = $ddStmt ? $ddStmt->fetchAll(PDO::FETCH_COLUMN) : [];
} catch (Exception $e) {
    error_log("CA Dropdown fetch error: " . $e->getMessage());
}
?>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    document.body.classList.add('companyassets-page');
  });
</script>

<script>
// function openFilterModal() {
//     alert("Filter button clicked ✅");

//     const modal = document.getElementById("filterModalOverlay");

//     if (!modal) {
//         alert("❌ filterModalOverlay NOT FOUND");
//         console.log("❌ filterModalOverlay NOT FOUND");
//         return;
//     }

//     alert("✅ filterModalOverlay FOUND");
//     modal.style.display = "flex";
// }
</script>


<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Lexend+Deca:wght@100..900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="./assets/css/unified_table_styles.css"/>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="./assets/css/CompanyAssets.css?v=<?php echo time(); ?>"/>

<?php include('header.php'); ?>
<script>
// Add page-specific class to body for scoped CSS
document.body.classList.add('companyassets-page');
</script>

<div class="content companyassets-page-wrapper">
  <div class="contentinside">
    <div class="container-fluid">
      <div class="row">
        <div class="col-lg-12">
          
          <div class="ca-toolbar-container">
            <div class="ca-search-wrapper">
              <svg class="ca-search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
              </svg>
              <input type="text" class="ca-search-input" id="globalSearch" placeholder="Search assets...">
            </div>

            <div class="ca-action-buttons">
              <button type="button" class="ca-filter-btn" title="Filters">
               <i class="bi bi-filter"></i>
                <span class="ca-btn-text">Filters</span>
              </button>

              <div class="ca-column-visibility-wrapper" style="position:relative;">
                <button class="ca-column-visibility-btn" onclick="toggleColumnVisibility(event)" title="Column Visibility">
                  <i class="bi bi-layout-three-columns"></i>
                  <span class="ca-btn-text">Column Visibility</span>
                </button>
                
                <div class="ca-column-dropdown" id="columnVisibilityDropdown">
                  <!-- <div class="ca-dropdown-header">
                    <span>Column Visibility</span>
                    <span class="ca-mobile-close" onclick="closeColumnDropdown()" title="Close">&times;</span>
                  </div> -->
                  <div class="ca-dropdown-body">
                    <label class="ca-dropdown-item">
                      <input type="checkbox"  data-column="0"> <span>ID</span>
                    </label>
                    <label class="ca-dropdown-item">
                      <input type="checkbox"  data-column="1"> <span>Employee Name</span>
                    </label>
                    <label class="ca-dropdown-item">
                      <input type="checkbox"  data-column="2"> <span>Employee Id</span>
                    </label>
                    <label class="ca-dropdown-item">
                      <input type="checkbox"  data-column="3"> <span>Phone Number</span>
                    </label>
                    <label class="ca-dropdown-item">
                      <input type="checkbox"  data-column="4"> <span>Project</span>
                    </label>
                    <label class="ca-dropdown-item">
                      <input type="checkbox"  data-column="5"> <span>Office Location</span>
                    </label>
                    <label class="ca-dropdown-item">
                      <input type="checkbox"  data-column="6"> <span>Laptop Brand</span>
                    </label>

                    <label class="ca-dropdown-item">
                      <input type="checkbox"  data-column="7"> <span>Company Laptop</span>
                    </label>
                    <label class="ca-dropdown-item">
                      <input type="checkbox" data-column="8"> <span>Laptop ID</span>
                    </label>
                    <label class="ca-dropdown-item">
                      <input type="checkbox" data-column="9"> <span>Laptop Charger</span>
                    </label>
                    <label class="ca-dropdown-item">
                      <input type="checkbox" data-column="10"> <span>Company Mouse</span>
                    </label>
                    <label class="ca-dropdown-item">
                      <input type="checkbox" data-column="11"> <span>SIM Card</span>
                    </label>
                    <label class="ca-dropdown-item">
                      <input type="checkbox" data-column="12"> <span>Date Signature</span>
                      <label class="ca-dropdown-item" style="display:none;">
                        <input type="checkbox" data-column="14" checked>
                       <span>Mobile Toggle</span>
                    </label>

                    </label>
                  </div>
                </div>
              </div>

               <div class="ca-rows-dropdown">
                <select id="caRowsPerPage" class="ca-rows-select" title="Rows per page">
                  <?php foreach ($allowedLimits as $limit): ?>
                    <option value="<?= $limit ?>" <?= $recordsPerPage == $limit ? 'selected' : '' ?>><?= $limit ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
          </div> 
          
          <div class="ca-table-card">
            <div class="ca-table-wrapper">
            <table id="example" class="ca-table assets-table">
              <thead>
                <tr>
                   <th data-column="-1" class="ca-col-checkbox">
                  <input type="checkbox" id="selectAllAssets">
                      </th>
                  <th data-column="0">ID</th>
                  <th data-column="1">EMPLOYEE NAME</th>
                  <th data-column="2">EMPLOYEE ID</th>
                  <th data-column="3">PHONE NUMBER</th>
                  <th data-column="4">PROJECT</th>
                  
                  <!-- Desktop Only Columns (Hidden on Mobile via CSS) -->
                  <th data-column="5" class="ca-desktop-col">OFFICE LOCATION</th>
                  <th data-column="6" class="ca-desktop-col">LAPTOP BRAND</th>
                  <th data-column="7" class="ca-desktop-col">COMPANY LAPTOP</th>
                  <th data-column="8" class="ca-desktop-col">LAPTOP ID</th>
                  <th data-column="9" class="ca-desktop-col">LAPTOP CHARGER</th>
                  <th data-column="10" class="ca-desktop-col">COMPANY MOUSE</th>
                  <th data-column="11" class="ca-desktop-col">SIM CARD</th>
                  <th data-column="12" class="ca-desktop-col">DATE SIGNATURE</th>
                  <th data-column="13" class="ca-desktop-col" style="text-align: center;">ACTIONS</th>

                  <!-- Mobile Toggle Column (Visible only on Mobile) -->
                  <th data-column="14" class="ca-mobile-toggle-head"></th>
                </tr>
              </thead>
              <tbody id="compamyassets">
                <?php
if (!empty($assets_data)) {
  foreach ($assets_data as $row) {
    $rowId = $row['id'];
    $fullLaptopId = htmlspecialchars($row['laptop_id'] ?? '');
    $maskedLaptopId = strlen($fullLaptopId) > 5 ? substr($fullLaptopId, 0, 5) . '...' : $fullLaptopId;

    echo "<tr class='ca-table-row main-row' data-row-id='{$rowId}'>";
    
    /* Checkbox Column */
    echo "<td data-column='-1' class='ca-col-checkbox'>
          <input type='checkbox' class='assetCheckbox'>
          </td>";

    // Column 0: ID
    echo "<td data-column='0' class='ca-col-id'>" . $row['id'] . "</td>";
    
    // Column 1: NAME + ARROW (Merged)
   // Column 1: NAME (Clean - No Arrow here)
    echo "<td class='ca-employee-name' data-column='1'>" . strtoupper($row['employee_name']) . "</td>";
    
    // Column 2: EMP ID
    echo "<td data-column='2' class='ca-col-empid'>" . $row['employee_id'] . "</td>";
    
    // Column 3: PHONE
    echo "<td data-column='3' class='ca-col-phone'>" . $row['phone_number'] . "</td>";
    
    // Column 4: PROJECT
    echo "<td data-column='4' class='ca-col-project'>" . $row['project'] . "</td>";
    
    // Desktop Columns (5-12)
    echo "<td data-column='5' class='ca-desktop-col'>" . $row['office_location'] . "</td>";
    echo "<td data-column='6' class='ca-desktop-col'>" . $row['laptop_brand'] . "</td>";
    echo "<td data-column='7' class='ca-desktop-col'>" . $row['company_laptop'] . "</td>";
    
    echo "<td data-column='8' class='ca-desktop-col'>";
    if (strlen($fullLaptopId) > 5) {
        echo "<span class='ca-mask-toggle' data-full='{$fullLaptopId}' data-masked='{$maskedLaptopId}' title='Click to reveal'>{$maskedLaptopId}</span>";
    } else {
        echo $fullLaptopId;
    }
    echo "</td>";

    echo "<td data-column='9' class='ca-desktop-col'>" . $row['laptop_charger'] . "</td>";
    echo "<td data-column='10' class='ca-desktop-col'>" . $row['company_mouse'] . "</td>";
    echo "<td data-column='11' class='ca-desktop-col'>" . ($row['sim_card'] ?? '') . "</td>";
    echo "<td data-column='12' class='ca-desktop-col'>" . ($row['date_signature'] ?? '') . "</td>";

    // Actions Column
    // Actions Column
    echo "<td data-column='13' class='ca-actions'>";

    echo "<button
      type='button'
      class='ca-action-icon ca-edit btn-edit'
      data-id='{$row['id']}'
      data-name='{$row['employee_name']}'
      data-phone='{$row['phone_number']}'
      data-project='{$row['project']}'
      data-location='{$row['office_location']}'
      data-laptop='{$row['company_laptop']}'
      data-brand='{$row['laptop_brand']}'
      data-charger='{$row['laptop_charger']}'
      data-mouse='{$row['company_mouse']}'
      data-sim='" . ($row['sim_card'] ?? '') . "'
      data-date='" . ($row['date_signature'] ?? '') . "'
      >
      <i class='bi bi-pencil-square'></i>
      </button>";

      echo "<button
        type='button'
        class='ca-action-icon ca-delete btn-delete'
        data-id='{$row['id']}'
      >
      <i class='bi bi-trash'></i>
      </button>";

      echo "</td>";


        // REMOVED EXTRA TOGGLE TD HERE
        // ADD THIS: Mobile Toggle Column (Visible on Tablet/Mobile)
        echo "<td data-column='14' class='ca-mobile-toggle-cell mobile-only-arrow'>";
        echo "<button type='button' class='ca-row-toggle-btn' onclick='toggleAssetRow({$rowId}); event.stopPropagation();'>";
        echo "<svg width='20' height='20' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'></path></svg>";
        echo "</button>";
        echo "</td>";

        echo "</tr>"; // End of Row

        

        // HIDDEN EXPANSION ROW
        echo "<tr id='expandRow{$rowId}' class='ca-expand-row' style='display:none;'>";
        echo "<td colspan='16'>"; 
        echo "<div class='ca-expand-box'>";
        
        // Details Grid
        echo "<div class='ca-expand-grid'>";
        
        // Mobile hidden details
        echo "<div class='ca-detail-item ca-show-320' style='display:none;'><span class='ca-label'>ID:</span> <span class='ca-value'>" . $row['id'] . "</span></div>";
        echo "<div class='ca-detail-item ca-show-425' style='display:none;'><span class='ca-label'>Employee ID:</span> <span class='ca-value'>" . $row['employee_id'] . "</span></div>";
        echo "<div class='ca-detail-item ca-show-425' style='display:none;'><span class='ca-label'>Phone Number:</span> <span class='ca-value'>" . $row['phone_number'] . "</span></div>";
        echo "<div class='ca-detail-item ca-show-425' style='display:none;'><span class='ca-label'>Project:</span> <span class='ca-value'>" . $row['project'] . "</span></div>";

        // Other details
        echo "<div class='ca-detail-item'><span class='ca-label'>Office Location:</span> <span class='ca-value'>" . $row['office_location'] . "</span></div>";
        echo "<div class='ca-detail-item'><span class='ca-label'>Laptop Brand:</span> <span class='ca-value'>" . $row['laptop_brand'] . "</span></div>";
        echo "<div class='ca-detail-item'><span class='ca-label'>Company Laptop:</span> <span class='ca-value'>" . $row['company_laptop'] . "</span></div>";
        echo "<div class='ca-detail-item'><span class='ca-label'>Laptop ID:</span> <span class='ca-value'>";
        if (strlen($fullLaptopId) > 5) {
            $safeJsId = addslashes($row['laptop_id'] ?? '');
            echo "<span style='cursor:pointer; color:#2563eb; text-decoration:underline;' onclick='openLaptopIdPopup(\"{$safeJsId}\")' title='Click to see full ID'>{$maskedLaptopId}</span>";
        } else {
            echo $fullLaptopId;
        }
        echo "</span></div>";
        echo "<div class='ca-detail-item'><span class='ca-label'>Laptop Charger:</span> <span class='ca-value'>" . $row['laptop_charger'] . "</span></div>";
        echo "<div class='ca-detail-item'><span class='ca-label'>Company Mouse:</span> <span class='ca-value'>" . $row['company_mouse'] . "</span></div>";
        echo "<div class='ca-detail-item'><span class='ca-label'>SIM Card:</span> <span class='ca-value'>" . ($row['sim_card'] ?? ''). "</span></div>";
        echo "<div class='ca-detail-item'><span class='ca-label'>Date Signature:</span> <span class='ca-value'>" . ($row['date_signature'] ?? '') . "</span></div>";
        echo "</div>"; // End Grid

        // Mobile Actions
        // echo "<div class='ca-expand-actions'>";
        echo "<div class='ca-expand-actions'>";

        echo "<button
        class='ca-btn-mobile-edit btn-edit'

        data-id='{$row['id']}'
        data-name='{$row['employee_name']}'
        data-phone='{$row['phone_number']}'
        data-project='{$row['project']}'
        data-location='{$row['office_location']}'
        data-laptop='{$row['company_laptop']}'
        data-brand='{$row['laptop_brand']}'
        data-charger='{$row['laptop_charger']}'
        data-mouse='{$row['company_mouse']}'
        data-sim='" . ($row['sim_card'] ?? '') . "'
        data-date='" . ($row['date_signature'] ?? '') . "'
        >
        Edit
        </button>";

        echo "<button
          class='ca-btn-mobile-delete btn-delete'
          data-id='{$row['id']}'
        >
        Delete
        </button>";

        echo "</div>";


        echo "</div>"; // End Box
        echo "</td>";
        echo "</tr>";
      }
    } else {
      echo "<tr><td colspan='14' style='text-align: center; padding: 2rem;'>No data found</td></tr>";
    }
    ?>
              </tbody>
            </table>
            </div> <!-- End ca-table-wrapper -->
          </div> <!-- End ca-table-card -->
<?php if ($totalRecords > 0): ?>
<div class="ca-pagination-bar">
  <!-- Left: Info Text -->
  <div class="ca-pagination-info">
    Showing <?= $startRecord ?> to <?= $endRecord ?> of <?= $totalRecords ?> entries
  </div>

  <!-- Center: Pagination Controls -->
  <div class="ca-pagination-center">
    <?php
      $prevPage = max(1, $currentPage - 1);
      $nextPage = min($totalPages, $currentPage + 1);
    ?>

    <!-- Previous Button -->
    <?php if ($currentPage <= 1): ?>
      <span class="ca-page-btn disabled">←</span>
    <?php else: ?>
      <a class="ca-page-btn" href="?<?= http_build_query(array_merge($_GET, ['page' => $prevPage])) ?>">Previous</a>
    <?php endif; ?>

    <!-- Page Numbers -->
    <?php
    $startPage = max(1, $currentPage - 2);
    $endPage = min($totalPages, $currentPage + 2);

    // First page
    if ($currentPage > 3) {
      echo '<a class="ca-page-number" href="?' . http_build_query(array_merge($_GET, ['page' => 1])) . '">1</a>';
      if ($currentPage > 4) echo '<span class="ca-page-dots">...</span>';
    }

    // Middle pages
    for ($i = $startPage; $i <= $endPage; $i++) {
      $active = ($i == $currentPage) ? "active" : "";
      echo '<a class="ca-page-number ' . $active . '" href="?' .
           http_build_query(array_merge($_GET, ['page' => $i])) .
           '">' . $i . '</a>';
    }

    // Last page
    if ($currentPage < $totalPages - 2) {
      if ($currentPage < $totalPages - 3) echo '<span class="ca-page-dots">...</span>';
      echo '<a class="ca-page-number" href="?' .
           http_build_query(array_merge($_GET, ['page' => $totalPages])) .
           '">' . $totalPages . '</a>';
    }
    ?>

    <!-- Next Button -->
    <?php if ($currentPage >= $totalPages): ?>
      <span class="ca-page-btn disabled">→</span>
    <?php else: ?>
      <a class="ca-page-btn" href="?<?= http_build_query(array_merge($_GET, ['page' => $nextPage])) ?>">Next</a>
    <?php endif; ?>
  </div>

  <!-- Right: Jump Controls -->
  <div class="ca-pagination-right">
    <form class="ca-jump-form" method="get">
      <?php
      foreach ($_GET as $k => $v) {
        if ($k === 'page') continue;
        echo '<input type="hidden" name="' . htmlspecialchars($k) . '" value="' . htmlspecialchars($v) . '">';
      }
      ?>
      <input class="ca-jump-input" type="number" name="page" min="1" max="<?= $totalPages ?>" placeholder="Page No.">
      <!-- <span class="jump-divider">|</span> -->
      <button class="ca-jump-btn" type="submit">Jump</button>
    </form>
  </div>
</div>
<?php endif; ?>
</div>
</div>

<!-- Company Assets Filter Modal (Property Booking UI) -->
<div class="modal-overlay" id="filterModalOverlay" style="display: none;">
    <div class="modal-container-eoi">
        <div class="modal-header">
            <h3>FILTER DATA</h3>
            <button type="button" class="modal-close-btn" onclick="closeFilterModal()">&times;</button>
        </div>

        <div class="modal-body">
          <form id="filterForm" onsubmit="return false;">
            <input type="hidden" name="page" value="1">

            <div class="container">
                <div class="row">

                    <!-- Employee Name -->
                    <div class="col-md-6 mb-2">
                        <div class="form-item">
                            <label for="filterEmployeeName">Employee Name</label>
                            <div class="ca-custom-dropdown-wrapper" id="cadd-employeeName">
                                <input
                                    type="text"
                                    class="form-control form-control-lg ca-custom-dropdown-input"
                                    name="employee_name"
                                    id="filterEmployeeName"
                                    placeholder="Enter employee name"
                                    autocomplete="off"
                                    data-cadd="cadd-employeeName"
                                >
                                <div class="ca-custom-dropdown-list" id="cadd-employeeName-list">
                                    <?php foreach ($ddEmployeeNames as $opt): ?>
                                        <div class="ca-custom-dropdown-item" data-value="<?= htmlspecialchars($opt) ?>"><?= htmlspecialchars($opt) ?></div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Employee ID -->
                    <div class="col-md-6 mb-2">
    <div class="form-item">

        <label for="filterEmployeeID">Employee ID</label>

        <div class="ca-custom-dropdown-wrapper" id="cadd-employeeId">

            <input
                type="text"
                class="form-control form-control-lg ca-custom-dropdown-input"
                name="employee_id"
                id="filterEmployeeID"
                placeholder="Enter employee ID"
                autocomplete="off"
                data-cadd="cadd-employeeId"
            >

            <div class="ca-custom-dropdown-list" id="cadd-employeeId-list">
                <?php foreach ($ddEmployeeIds as $opt): ?>
                    <div class="ca-custom-dropdown-item" data-value="<?= htmlspecialchars($opt) ?>">
                        <?= htmlspecialchars($opt) ?>
                    </div>
                <?php endforeach; ?>
            </div>

        </div>

    </div>
</div>

                    <!-- Phone Number -->
                    <div class="col-md-6 mb-2">
    <div class="form-item">

        <label for="filterPhoneNumber">Phone Number</label>

        <div class="ca-custom-dropdown-wrapper" id="cadd-phoneNumber">

            <input
                type="text"
                class="form-control form-control-lg ca-custom-dropdown-input"
                name="phone_number"
                id="filterPhoneNumber"
                placeholder="Enter phone number"
                autocomplete="off"
                data-cadd="cadd-phoneNumber"
            >

            <div class="ca-custom-dropdown-list" id="cadd-phoneNumber-list">
                <?php foreach ($ddPhoneNumbers as $opt): ?>
                    <div class="ca-custom-dropdown-item" data-value="<?= htmlspecialchars($opt) ?>">
                        <?= htmlspecialchars($opt) ?>
                    </div>
                <?php endforeach; ?>
            </div>

        </div>

    </div>
</div>

                    <!-- Project -->
                    <div class="col-md-6 mb-2">
                        <div class="form-item">
                            <label for="filterProject">Project</label>
                            <div class="ca-custom-dropdown-wrapper" id="cadd-project">
                                <input
                                    type="text"
                                    class="form-control form-control-lg ca-custom-dropdown-input"
                                    name="project"
                                    id="filterProject"
                                    placeholder="Enter project"
                                    autocomplete="off"
                                    data-cadd="cadd-project"
                                >
                                <div class="ca-custom-dropdown-list" id="cadd-project-list">
                                    <?php foreach ($ddProjects as $opt): ?>
                                        <div class="ca-custom-dropdown-item" data-value="<?= htmlspecialchars($opt) ?>"><?= htmlspecialchars($opt) ?></div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Office Location -->
                    <div class="col-md-6 mb-2">
                        <div class="form-item">
                            <label for="filterOfficeLocation">Office Location</label>
                            <div class="ca-custom-dropdown-wrapper" id="cadd-officeLocation">
                                <input
                                    type="text"
                                    class="form-control form-control-lg ca-custom-dropdown-input"
                                    name="office_location"
                                    id="filterOfficeLocation"
                                    placeholder="Enter location"
                                    autocomplete="off"
                                    data-cadd="cadd-officeLocation"
                                >
                                <div class="ca-custom-dropdown-list" id="cadd-officeLocation-list">
                                    <?php foreach ($ddLocations as $opt): ?>
                                        <div class="ca-custom-dropdown-item" data-value="<?= htmlspecialchars($opt) ?>"><?= htmlspecialchars($opt) ?></div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Laptop Brand -->
                    <div class="col-md-6 mb-2">
                        <div class="form-item">
                            <label for="filterLaptopBrand">Laptop Brand</label>
                            <div class="ca-custom-dropdown-wrapper" id="cadd-laptopBrand">
                                <input
                                    type="text"
                                    class="form-control form-control-lg ca-custom-dropdown-input"
                                    name="laptop_brand"
                                    id="filterLaptopBrand"
                                    placeholder="Enter laptop brand"
                                    autocomplete="off"
                                    data-cadd="cadd-laptopBrand"
                                >
                                <div class="ca-custom-dropdown-list" id="cadd-laptopBrand-list">
                                    <?php foreach ($ddBrands as $opt): ?>
                                        <div class="ca-custom-dropdown-item" data-value="<?= htmlspecialchars($opt) ?>"><?= htmlspecialchars($opt) ?></div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Date Signature -->
                    <div class="col-md-12 mb-2">
                        <div class="form-item">
                            <label for="filterDateSignature">Date Signature</label>
                            <input type="date" class="form-control form-control-lg" name="date_signature" id="filterDateSignature">
                        </div>
                    </div>

                </div>
            </div>

            <!-- Footer Buttons (same UI as Property Bookings) -->
        <!-- Footer Buttons -->
<div class="modal-footer form-actions" style="margin: 0 auto; gap: 1rem; justify-content: center;">

    <!-- Close -->
    <button type="button"
            class="btn btn-secondary"
            onclick="closeFilterModal()">
        Close
    </button>

    <!-- Clear Filters -->
    <a href="companyassets.php"
       class="btn btn-danger">
        Clear Filters
    </a>

    <!-- Apply Filters -->
    <button type="button" onclick="applyServerFilters()" class="btn btn-primary filter-submit">
    Apply Filters
    </button>


</div>

          </form>
        </div>
    </div>
</div>


<!-- Company Assets Edit Modal (Property Bookings UI) -->
<div id="editAssetModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 11000; background: rgba(0,0,0,0.55); align-items: center; justify-content: center; flex-direction: column; overflow: auto;">

  <div class="modal-dialog modal-dialog-centered" style="position: relative; margin: auto; max-width: 720px; width: 92%; max-height: 90vh; overflow: hidden;">

    <div class="modal-content" style="max-height: 90vh; display: flex; flex-direction: column; overflow: hidden; border-radius: 18px;">

      <!-- Header -->
      <div class="modal-header" style="display:flex; align-items:center; justify-content:space-between; padding: 1rem 1.25rem; border-bottom:1px solid #dee2e6;">
        <h5 class="modal-title" style="margin:0; font-weight:700;">Edit Asset Details</h5>

        <button type="button" class="btn-close" aria-label="Close"
          onclick="closeEditAssetModal()"
          style="opacity: 1; visibility: visible; display: block; cursor: pointer; background: transparent url('data:image/svg+xml,%3csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 16 16\' fill=\'%23000\'%3e%3cpath d=\'M.293.293a1 1 0 011.414 0L8 6.586 14.293.293a1 1 0 111.414 1.414L9.414 8l6.293 6.293a1 1 0 01-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 01-1.414-1.414L6.586 8 .293 1.707a1 1 0 010-1.414z\'/%3e%3c/svg%3e') center/1em auto no-repeat; border: 0; border-radius: 0.375rem; width: 1em; height: 1em; padding: 0.5em; margin: 0;">
        </button>
      </div>

      <!-- Body -->
      <div class="modal-body" style="overflow-y: auto; overflow-x:hidden; max-height: calc(90vh - 140px); flex: 1 1 auto; padding: 1.25rem;">

        <form id="editAssetForm">
          <input type="hidden" id="editRowId">

          <div class="container">
            <div class="row">

              <!-- Employee Name -->
              <div class="col-md-6 mb-3">
                <div class="form-item">
                  <label for="editEmployeeName">Employee Name</label>
                  <input type="text" id="editEmployeeName" class="form-control form-control-lg" placeholder="Enter employee name">
                </div>
              </div>

              <!-- Phone Number -->
              <div class="col-md-6 mb-3">
                <div class="form-item">
                  <label for="editPhoneNumber">Phone Number</label>
                  <input type="text" id="editPhoneNumber" class="form-control form-control-lg" placeholder="Enter phone number">
                </div>
              </div>

              <!-- Project -->
              <div class="col-md-6 mb-3">
                <div class="form-item">
                  <label for="editProject">Project</label>
                  <input type="text" id="editProject" class="form-control form-control-lg" placeholder="Enter project">
                </div>
              </div>

              <!-- Office Location -->
              <div class="col-md-6 mb-3">
                <div class="form-item">
                  <label for="editOfficeLocation">Office Location</label>
                  <input type="text" id="editOfficeLocation" class="form-control form-control-lg" placeholder="Enter location">
                </div>
              </div>

              <!-- Company Laptop -->
              <div class="col-md-6 mb-3">
                <div class="form-item">
                  <label for="editCompanyLaptop">Company Laptop</label>
                  <select id="editCompanyLaptop" class="form-control form-control-lg">
                    <option value="Yes">Yes</option>
                    <option value="No">No</option>
                  </select>
                </div>
              </div>

              <!-- Laptop Brand -->
              <div class="col-md-6 mb-3">
                <div class="form-item">
                  <label for="editLaptopBrand">Laptop Brand</label>
                  <input type="text" id="editLaptopBrand" class="form-control form-control-lg" placeholder="Enter laptop brand">
                </div>
              </div>

              <!-- Laptop Charger -->
              <div class="col-md-6 mb-3">
                <div class="form-item">
                  <label for="editLaptopCharger">Laptop Charger</label>
                  <select id="editLaptopCharger" class="form-control form-control-lg">
                    <option value="Yes">Yes</option>
                    <option value="No">No</option>
                  </select>
                </div>
              </div>

              <!-- Company Mouse -->
              <div class="col-md-6 mb-3">
                <div class="form-item">
                  <label for="editCompanyMouse">Company Mouse</label>
                  <select id="editCompanyMouse" class="form-control form-control-lg">
                    <option value="Yes">Yes</option>
                    <option value="No">No</option>
                  </select>
                </div>
              </div>

              <!-- SIM Card -->
              <div class="col-md-6 mb-3">
                <div class="form-item">
                  <label for="editSimCard">SIM Card</label>
                  <select id="editSimCard" class="form-control form-control-lg">
                    <option value="Yes">Yes</option>
                    <option value="No">No</option>
                  </select>
                </div>
              </div>

              <!-- Date Signature -->
              <div class="col-md-6 mb-3">
                <div class="form-item">
                  <label for="editDateSignature">Date Signature</label>
                  <input type="date" id="editDateSignature" class="form-control form-control-lg">
                </div>
              </div>

            </div>
          </div>
        </form>

      </div>

      <!-- Footer -->
      <div class="modal-footer" style="padding: 1rem 1.25rem; border-top: 1px solid #dee2e6; display:flex; justify-content:flex-end; gap: 12px;">
        <button type="button" class="btn btn-secondary" onclick="closeEditAssetModal()">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="saveChanges()">Save Changes</button>
      </div>

    </div>
  </div>
</div>
<!-- Company Assets Edit Modal End -->

<!-- Custom Laptop ID Popup (Mobile) -->
<div id="laptopIdPopupOverlay" class="ca-popup-overlay" style="display:none;">
    <div class="ca-popup-box">
        <span class="ca-popup-close" onclick="closeLaptopIdPopup()">&times;</span>
        <div class="ca-popup-body">
            <label style="display:block; font-weight:bold; margin-bottom:8px; color:#333;">Laptop ID</label>
            <div id="laptopIdPopupText" style="word-break: break-all; font-size:1.1rem; color:#000;"></div>
        </div>
    </div>
</div>


<!-- <script>
document.addEventListener("DOMContentLoaded", function () {

    const checkboxes = document.querySelectorAll(".ca-dropdown-item input[type='checkbox']");

    function setDefaultColumns() {

        const width = window.innerWidth;

        // First: uncheck all
        checkboxes.forEach(cb => {
            cb.checked = false;
            toggleColumn(cb);
        });

        // DESKTOP (>768px) → Let existing system handle
        if (width > 768) {
            checkboxes.forEach(cb => {
                cb.checked = true;
                toggleColumn(cb);
            });
            return;
        }

        // TABLET (<=768px)
        if (width <= 768 && width > 425) {

            const tabletDefaults = [0, 1, 2, 3, 4]; 
            // ID, Name, EmpID, Phone, Project

            tabletDefaults.forEach(i => {
                const cb = document.querySelector(`input[data-column="${i}"]`);
                if (cb) {
                    cb.checked = true;
                    toggleColumn(cb);
                }
            });

            return;
        }

        // MOBILE (<=425px)
        if (width <= 425 && width > 320) {

            const mobileDefaults = [0, 1]; 
            // ID, Name

            mobileDefaults.forEach(i => {
                const cb = document.querySelector(`input[data-column="${i}"]`);
                if (cb) {
                    cb.checked = true;
                    toggleColumn(cb);
                }
            });

            return;
        }

        // SMALL MOBILE (<=320px)
        if (width <= 320) {

            const smallDefaults = [1]; 
            // Only Name

            smallDefaults.forEach(i => {
                const cb = document.querySelector(`input[data-column="${i}"]`);
                if (cb) {
                    cb.checked = true;
                    toggleColumn(cb);
                }
            });

        }
    }


    // Toggle column show/hide
    function toggleColumn(checkbox) {

        const colIndex = checkbox.getAttribute("data-column");

        const cells = document.querySelectorAll(
            `th[data-column="${colIndex}"], td[data-column="${colIndex}"]`
        );

        cells.forEach(cell => {
            if (checkbox.checked) {
                cell.style.display = "table-cell";
            } else {
                cell.style.display = "none";
            }
        });
    }


    // Apply when page loads
    setDefaultColumns();


    // Apply when screen resizes
    window.addEventListener("resize", function () {
        setDefaultColumns();
    });


    // Manual user toggle
    checkboxes.forEach(cb => {
        cb.addEventListener("change", function () {
            toggleColumn(cb);
        });
    });

});
</script> -->


<!-- <script>
document.addEventListener("DOMContentLoaded", function () {

  const checkboxes = document.querySelectorAll(".ca-dropdown-item input[type='checkbox']");

  // function setDefaultColumns() {

  //   const width = window.innerWidth;

  //   // First: Uncheck all
  //   checkboxes.forEach(cb => {
  //     cb.checked = false;
  //     cb.dispatchEvent(new Event("change"));
  //   });

  //   /* =========================
  //      DESKTOP ( >1024px )
  //   ========================= */
  //   if (width > 1024) {

  //     enableColumns([
  //       "ID",
  //       "Employee Name",
  //       "Employee Id",
  //       "Phone Number",
  //       "Project",
  //       "Office Location",
  //       "Laptop Brand",
  //       "Company Laptop"
  //     ]);
  //   }

  //   /* =========================
  //      TABLET (769px - 1024px)
  //   ========================= */
  //   else if (width <= 1024 && width > 768) {

  //     enableColumns([
  //       "ID",
  //       "Employee Name",
  //       "Employee Id",
  //       "Phone Number",
  //       "Project"
  //     ]);
  //   }

  //   /* =========================
  //      MOBILE (426px - 768px)
  //   ========================= */
  //   else if (width <= 768 && width > 425) {

  //     enableColumns([
  //       "ID",
  //       "Employee Name"
  //     ]);
  //   }

  //   /* =========================
  //      SMALL MOBILE (<=425px)
  //   ========================= */
  //   else if (width <= 425 && width > 320) {

  //     enableColumns([
  //       "ID",
  //       "Employee Name"
  //     ]);
  //   }

  //   /* =========================
  //      EXTRA SMALL (<=320px)
  //   ========================= */
  //   else {

  //     enableColumns([
  //       "Employee Name"
  //     ]);
  //   }
  // }


  function enableColumns(names) {

    checkboxes.forEach(cb => {

      const label = cb.closest(".ca-dropdown-item").innerText.trim();

      if (names.includes(label)) {
        cb.checked = true;
        cb.dispatchEvent(new Event("change"));
      }

    });
  }
});
</script> -->


<script>
/* (All original JS preserved) */
window.addEventListener('resize', function() {
  if (window.innerWidth > 768) {
    document.querySelectorAll('.ca-expand-row').forEach(row => row.style.display = 'none');
    document.querySelectorAll('.main-row.expanded').forEach(row => row.classList.remove('expanded'));
    document.querySelectorAll('.ca-row-toggle-btn svg').forEach(svg => svg.style.transform = 'rotate(0deg)');
  }
});

function openLaptopIdPopup(id) {
  const overlay = document.getElementById('laptopIdPopupOverlay');
  const text = document.getElementById('laptopIdPopupText');
  if(overlay && text) {
    text.textContent = id;
    overlay.style.display = 'flex';
  }
}

function closeLaptopIdPopup() {
  const overlay = document.getElementById('laptopIdPopupOverlay');
  if(overlay) {
    overlay.style.display = 'none';
  }
}

function toggleAssetRow(rowId) {
  const expandRow = document.getElementById('expandRow' + rowId);
  const mainRow = document.querySelector(`tr[data-row-id='${rowId}']`);
  const arrow = mainRow.querySelector('.ca-row-toggle-btn svg');
  
  if (!expandRow) return;

  const isHidden = expandRow.style.display === 'none';

  // Collapse all other open rows
  document.querySelectorAll('.ca-expand-row').forEach(row => {
    if (row.id !== 'expandRow' + rowId) {
      row.style.display = 'none';
      // Reset arrow
      const otherId = row.id.replace('expandRow', '');
      const otherMain = document.querySelector(`tr[data-row-id='${otherId}']`);
      if(otherMain) {
        const otherArrow = otherMain.querySelector('.ca-row-toggle-btn svg');
        if(otherArrow) otherArrow.style.transform = 'rotate(0deg)';
        otherMain.classList.remove('expanded');
      }
    }
  });

  if (isHidden) {
    expandRow.style.display = 'table-row';
    mainRow.classList.add('expanded');
    arrow.style.transform = 'rotate(180deg)';
  } else {
    expandRow.style.display = 'none';
    mainRow.classList.remove('expanded');
    arrow.style.transform = 'rotate(0deg)';
  }
}

function loadColumnVisibility() {
    const saved = localStorage.getItem('companyAssetsColumnVisibility');
    const checkboxes = document.querySelectorAll('.ca-dropdown-item input[type="checkbox"]');

    if (saved) {
        const visibility = JSON.parse(saved);

        Object.keys(visibility).forEach(col => {
            const checkbox = document.querySelector(`input[data-column="${col}"]`);
            if (checkbox) {
                checkbox.checked = visibility[col];
                toggleColumnDisplay(col, visibility[col], false); // isManual = false
            }
        });

    } else {
        checkboxes.forEach(cb => {
            toggleColumnDisplay(cb.dataset.column, cb.checked, false); // isManual = false
        });
    }
}


function saveColumnVisibility() {
  const checkboxes = document.querySelectorAll('.ca-dropdown-item input[type="checkbox"]');
  const visibility = {};
  checkboxes.forEach(cb => {
    visibility[cb.dataset.column] = cb.checked;
  });
  localStorage.setItem('companyAssetsColumnVisibility', JSON.stringify(visibility));
}

function toggleColumnDisplay(columnIndex, show, isManual = false) {
  const table = document.querySelector('.ca-table');
  if (!table) return;

  const headers = table.querySelectorAll(`thead th[data-column="${columnIndex}"]`);
  const cells = table.querySelectorAll(`tbody td[data-column="${columnIndex}"]`);

  headers.forEach(th => {
    th.style.display = show ? 'table-cell' : 'none';
    if (isManual && show) th.classList.add('ca-force-show');
    if (isManual && !show) th.classList.remove('ca-force-show');
  });

  cells.forEach(td => {
    td.style.display = show ? 'table-cell' : 'none';
    if (isManual && show) td.classList.add('ca-force-show');
    if (isManual && !show) td.classList.remove('ca-force-show');
  });
}


function toggleColumnVisibility(event) {
  if (event) event.stopPropagation();
  const dropdown = document.getElementById('columnVisibilityDropdown');
  dropdown.classList.toggle('show');
}

function closeColumnDropdown() {
    const dropdown = document.getElementById("columnVisibilityDropdown");

    if (dropdown) {
        dropdown.classList.remove("show");
    }
}




// Stop propagation to prevent immediate closing when clicking inside dropdown
document.addEventListener('DOMContentLoaded', function() {
    const dropdownContainer = document.getElementById('columnVisibilityDropdown');
    if(dropdownContainer) {
        dropdownContainer.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    }
});

document.addEventListener('click', function(e) {
  if (e.target.classList.contains('ca-mask-toggle')) {
    const el = e.target;
    const currentText = el.textContent;
    const full = el.getAttribute('data-full');
    const masked = el.getAttribute('data-masked');
    if (currentText.trim() === masked.trim()) {
      el.textContent = full;
    } else {
      el.textContent = masked;
    }
  }
});

// document.addEventListener('DOMContentLoaded', function() {
//   applyResponsiveDefaults();
//   // loadColumnVisibility();

//   function applyResponsiveDefaults() {

//   const w = window.innerWidth;

//   const checkboxes = document.querySelectorAll(
//     '.ca-dropdown-item input[type="checkbox"]'
//   );

//   // Reset all first
//   checkboxes.forEach(cb => {
//     cb.checked = false;
//     toggleColumnDisplay(cb.dataset.column, false);
//   });

//   let defaults = [];

//   /* ======================
//      DESKTOP > 1024px
//   ====================== */
//   if (w > 1024) {
//     defaults = [0,1,2,3,4,5,6,7,13];
//   }

//    /* =========================
//      TABLET (769px–1024px)
//   ========================= */
//   else if (w > 768 && w <= 1024) {
//     defaults = [0,1,2,3,4];
//   }


//   /* ======================
//      TABLET 769px–1024px
//   ====================== */
//   else if (w > 425 && w <= 768) {
//   defaults = [0,1,2,3,4,14]; // include toggle
//   }


//   /* ======================
//      MOBILE 426px–768px
//   ====================== */
//   else if (w >= 425) {
//     defaults = [0,1,14]; // ID + Name + Arrow
//   }

//   /* ======================
//      SMALL ≤425px
//   ====================== */
//   else {
//     defaults = [1,14]; // Name + Arrow
//   }

//   // Apply defaults
//   defaults.forEach(i => {

//     const cb = document.querySelector(
//       `input[data-column="${i}"]`
//     );

//     if (cb) {
//       cb.checked = true;
//       toggleColumnDisplay(i, true);
//     }
//   });

// }



  
//   // Delegated listener for Column Visibility Checkboxes
//   document.addEventListener('change', function(e) {
//     if (e.target && e.target.matches('.ca-dropdown-item input[type="checkbox"]')) {
//        const column = e.target.getAttribute('data-column'); // Use getAttribute for safety
//        const show = e.target.checked;
//        if (column !== null) {
//        if (column !== null) {
//            toggleColumnDisplay(column, show, true); // isManual = true
//            saveColumnVisibility();
//        }
//        }
//     }
//   });

//   /* 
//   // Old direct binding - removed for robustness
//   checkboxes.forEach(checkbox => {
//     checkbox.addEventListener('change', function() {
//       const column = this.dataset.column;
//       const show = this.checked;
//       toggleColumnDisplay(column, show);
//       saveColumnVisibility();
//     });
//   });
//   */
  
//   document.addEventListener('click', function(e) {
//     const dropdown = document.getElementById('columnVisibilityDropdown');
//     const btn = document.querySelector('.ca-column-visibility-btn');
//     if (dropdown && btn && !dropdown.contains(e.target) && !btn.contains(e.target)) {
//       dropdown.classList.remove('show');
//     }
//   });

//   document.addEventListener('keydown', function(e) {
//     if(e.key === "Escape") {
//       const dropdown = document.getElementById('columnVisibilityDropdown');
//       if(dropdown) dropdown.classList.remove('show');
//       closeFilterModal();
//       closeEditAssetModal();

//     }
//   });
// });

// function openFilterModal() {
//   document.getElementById('filterModal').classList.add('show');
//   document.getElementById('filterModal').style.display = 'block';
//   document.body.classList.add('modal-open');
//   // Use custom backdrop
//   // Logic mostly handled by CSS now but keeping basic show/hide
// }

// === NEW: Responsive Row Click to Toggle Dropdown ===
document.addEventListener("DOMContentLoaded", function () {
  const rows = document.querySelectorAll(".ca-table-row.main-row");

  rows.forEach((row) => {
    row.addEventListener("click", function (e) {
      // 1. Check if we are in responsive view (<= 768px as per user requirement)
      // Alternately, checking if toggle button is visible is safer, but user asked for width check or detecting dropdown existence.
      // Let's use window width to be safe and consistent with CSS media query.
      if (window.innerWidth > 768) return;

      // 2. Prevent toggle if clicking on interactive elements
      if (
  e.target.closest("button") ||
  e.target.closest("a") ||
  e.target.closest("input") ||
  e.target.closest("select") ||
  e.target.closest("textarea") ||
  e.target.closest(".ca-action-icon") ||
  e.target.closest(".ca-mask-toggle")
) {
  return;
}


      // 3. Get Row ID and toggle
      const rowId = this.getAttribute("data-row-id");
      if (rowId) {
        toggleAssetRow(rowId);
      }
    });
  });
});


// === NEW: Rows Per Page Change ===
document.addEventListener('DOMContentLoaded', function() {
  const rowsSelect = document.getElementById('caRowsPerPage');
  if (rowsSelect) {
    rowsSelect.addEventListener('change', function() {
      const limit = this.value;
      const url = new URL(window.location.href);
      url.searchParams.set('limit', limit);
      url.searchParams.set('page', 1); // Reset to page 1
      window.location.href = url.toString();
    });
  }
  // ✅ APPLY RESPONSIVE DEFAULTS
    // applyResponsiveDefaults();
});

// window.addEventListener('resize', function () {
//     applyResponsiveDefaults();
// });



// function closeFilterModal() {
//   document.getElementById('filterModal').classList.remove('show');
//   document.getElementById('filterModal').style.display = 'none';
//   document.body.classList.remove('modal-open');
// }

function clearFilters() {

    document.querySelectorAll("#filterModalOverlay input")
        .forEach(inp => inp.value = "");

    document.querySelectorAll(".ca-table tbody tr")
        .forEach(row => row.style.display = "");

}


function applyFilters() {

    const name     = document.getElementById("filterEmployeeName").value.toLowerCase();
    const empId    = document.getElementById("filterEmployeeID").value.toLowerCase();
    const phone    = document.getElementById("filterPhoneNumber").value.toLowerCase();
    const project  = document.getElementById("filterProject").value.toLowerCase();
    const location = document.getElementById("filterOfficeLocation").value.toLowerCase();
    const laptop   = document.getElementById("filterLaptopBrand").value.toLowerCase();
    const date     = document.getElementById("filterDateSignature").value;

    const rows = document.querySelectorAll(".ca-table tbody tr");

    rows.forEach(row => {

        const cols = row.querySelectorAll("td");

        const rowName     = cols[1]?.innerText.toLowerCase();
        const rowEmpId    = cols[2]?.innerText.toLowerCase();
        const rowPhone    = cols[3]?.innerText.toLowerCase();
        const rowProject  = cols[4]?.innerText.toLowerCase();
        const rowLocation = cols[5]?.innerText.toLowerCase();
        const rowLaptop   = cols[6]?.innerText.toLowerCase();

        let show = true;

        if (name && !rowName.includes(name)) show = false;
        if (empId && !rowEmpId.includes(empId)) show = false;
        if (phone && !rowPhone.includes(phone)) show = false;
        if (project && !rowProject.includes(project)) show = false;
        if (location && !rowLocation.includes(location)) show = false;
        if (laptop && !rowLaptop.includes(laptop)) show = false;

        if (show) {
            row.style.display = "";
        } else {
            row.style.display = "none";
        }

    });

    closeFilterModal();
}




// function openEditModal(rowData) {
//   document.getElementById('editModal').classList.add('show');
//   document.getElementById('editModal').style.display = 'block';
//   document.body.classList.add('modal-open');
  
//   document.getElementById('editRowId').value = rowData.id;
//   document.getElementById('editEmployeeName').value = rowData.employee_name;
//   document.getElementById('editPhoneNumber').value = rowData.phone_number;
//   document.getElementById('editProject').value = rowData.project;
//   document.getElementById('editOfficeLocation').value = rowData.office_location;
//   document.getElementById('editCompanyLaptop').value = rowData.company_laptop;
//   document.getElementById('editLaptopBrand').value = rowData.laptop_brand;
//   document.getElementById('editLaptopCharger').value = rowData.laptop_charger;
//   document.getElementById('editCompanyMouse').value = rowData.company_mouse;
//   document.getElementById('editSimCard').value = rowData.sim_cad;
//   document.getElementById('editDateSignature').value = rowData.datesignature;
// }

// function closeEditModal() {
//   document.getElementById('editModal').classList.remove('show');
//   document.getElementById('editModal').style.display = 'none';
//   document.body.classList.remove('modal-open');
// }

function saveChanges() {

  const data = new URLSearchParams();

  data.append("action", "update");
  data.append("id", document.getElementById("editRowId").value);

  data.append("employee_name", document.getElementById("editEmployeeName").value);
  data.append("phone_number", document.getElementById("editPhoneNumber").value);
  data.append("project", document.getElementById("editProject").value);
  data.append("office_location", document.getElementById("editOfficeLocation").value);

  data.append("company_laptop", document.getElementById("editCompanyLaptop").value);
  data.append("laptop_brand", document.getElementById("editLaptopBrand").value);
  data.append("laptop_charger", document.getElementById("editLaptopCharger").value);
  data.append("company_mouse", document.getElementById("editCompanyMouse").value);
  data.append("sim_card", document.getElementById("editSimCard").value);

  data.append("datesignature", document.getElementById("editDateSignature").value);


  fetch("companyassets.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded"
    },
    body: data.toString()
  })
  .then(res => res.text())
  .then(resp => {
    console.log("SERVER RESPONSE:", resp);

    if (resp.trim() === "success") {
      closeEditAssetModal();
      location.reload();
    } else {
      alert("Update failed");
    }

  });
}


function deleteRow(rowId) {
  if (confirm('Are you sure you want to delete this asset?')) {
    // UI delete logic
  }
}

document.getElementById('globalSearch').addEventListener('keyup', function(e) {
  const searchValue = this.value.trim();
  
  // If Enter key is pressed, submit search with page reset
  if (e.key === 'Enter') {
    const currentUrl = new URL(window.location.href);
    if (searchValue) {
      currentUrl.searchParams.set('search', searchValue);
      currentUrl.searchParams.set('page', '1'); // Reset to page 1
    } else {
      currentUrl.searchParams.delete('search');
      currentUrl.searchParams.delete('page');
    }
    window.location.href = currentUrl.toString();
    return;
  }
  
  // Real-time client-side filtering (for current page only)
  const rows = document.querySelectorAll('.ca-table-row.main-row');
  rows.forEach(row => {
    const text = row.textContent.toLowerCase();
    row.style.display = text.includes(searchValue.toLowerCase()) ? '' : 'none';
  });
});

// Close modal on backdrop click (if needed, but structure changed)
document.addEventListener('click', function(e) {
  if (e.target.classList.contains('ca-modal-backdrop')) {
    closeFilterModal();
    closeEditAssetModal();

  }
});
</script>

<script>
// window.openFilterModal = function () {
//   const modal = document.getElementById("filterModalOverlay");
//   if (!modal) {
//     console.error("filterModalOverlay not found in DOM!");
//     return;
//   }
//   modal.style.display = "flex";
// };

window.closeFilterModal = function () {
  const modal = document.getElementById("filterModalOverlay");
  if (modal) modal.style.display = "none";
};

/* ✅ FIX-1: Clear Filters button working */
// window.clearAllFilters = function () {
  // clear modal inputs
  // const ids = [
  //   "filterEmployeeName",
  //   "filterEmployeeID",
  //   "filterPhoneNumber",
  //   "filterProject",
  //   "filterOfficeLocation",
  //   "filterLaptopBrand",
  //   "filterDateSignature"
  // ];

  // ids.forEach(id => {
  //   const el = document.getElementById(id);
  //   if (el) el.value = "";
  // });

  // If you want after clearing, also close modal:
  // closeFilterModal();
// };


/* ✅ FIX-2: Apply Filters button working */
window.applyFiltersToIframe = function () {
const filters = {
    employee_name: document.getElementById("filterEmployeeName")?.value.trim() || "",
    employee_id: document.getElementById("filterEmployeeID")?.value.trim() || "",
    phone_number: document.getElementById("filterPhoneNumber")?.value.trim() || "",
    project: document.getElementById("filterProject")?.value.trim() || "",
    office_location: document.getElementById("filterOfficeLocation")?.value.trim() || "",
    laptop_brand: document.getElementById("filterLaptopBrand")?.value.trim() || "",
    date_signature: document.getElementById("filterDateSignature")?.value.trim() || ""
};

  // Build URL with filter parameters
  const url = new URL(window.location.href);
  url.searchParams.set('page', '1'); // Reset to page 1 when filtering
  
  Object.keys(filters).forEach(key => {
    if (filters[key]) {
      url.searchParams.set(key, filters[key]);
    } else {
      url.searchParams.delete(key);
    }
  });
  
  window.location.href = url.toString();
};

function openEditAssetModal(rowData) {

  // ✅ Fill modal fields
  document.getElementById("editEmployeeName").value = rowData.employee_name ?? "";
  document.getElementById("editPhoneNumber").value  = rowData.phone_number ?? "";
  document.getElementById("editProject").value      = rowData.project ?? "";
  document.getElementById("editOfficeLocation").value = rowData.office_location ?? "";

  document.getElementById("editCompanyLaptop").value = rowData.company_laptop ?? "No";
  document.getElementById("editLaptopBrand").value   = rowData.laptop_brand ?? "";
  document.getElementById("editLaptopCharger").value = rowData.laptop_charger ?? "No";
  document.getElementById("editCompanyMouse").value  = rowData.company_mouse ?? "No";
  document.getElementById("editSimCard").value = rowData.sim_card ?? "No";

  // Date
  document.getElementById("editDateSignature").value = rowData.date_signature ?? "";

  // ✅ Row ID hidden field (important for saving)
  document.getElementById("editRowId").value = rowData.id ?? "";

  // ✅ Open modal
  document.getElementById("editAssetModal").style.display = "flex";
}

function closeEditAssetModal() {
  document.getElementById("editAssetModal").style.display = "none";
}



</script>

<script>
document.addEventListener("DOMContentLoaded", function () {

    const filterBtn = document.querySelector(".ca-filter-btn");
    const modal = document.getElementById("filterModalOverlay");
    const closeBtn = document.querySelector(".modal-close-btn");

    // Open modal
    if (filterBtn && modal) {
        filterBtn.addEventListener("click", function () {
            modal.style.display = "flex";
            document.body.style.overflow = "hidden";
        });
    }

    // Close modal
    if (closeBtn && modal) {
        closeBtn.addEventListener("click", function () {
            modal.style.display = "none";
            document.body.style.overflow = "auto";
        });
    }

    // Close when clicking outside
    if (modal) {
        modal.addEventListener("click", function (e) {
            if (e.target === modal) {
                modal.style.display = "none";
                document.body.style.overflow = "auto";
            }
        });
    }

});
</script>

<script>
document.getElementById('selectAllAssets')?.addEventListener('change', function () {
  document.querySelectorAll('.assetCheckbox').forEach(cb => {
    cb.checked = this.checked;
  });
});
</script>

<script>
// document.addEventListener("DOMContentLoaded", function () {

//     const checkboxes = document.querySelectorAll(
//         '.ca-column-dropdown input[type="checkbox"]'
//     );

//     const table = document.querySelector('.ca-table-card table');

//     function updateColumns() {
//         checkboxes.forEach(cb => {
//             const colIndex = cb.getAttribute("data-column");

//             const cells = table.querySelectorAll(
//                 'tr > *:nth-child(' + (parseInt(colIndex) + 1) + ')'
//             );

//             if (cb.checked) {
//                 cells.forEach(cell => cell.style.display = "");
//             } else {
//                 cells.forEach(cell => cell.style.display = "none");
//             }
//         });
//     }


    // function setDefaults() {

    //     const width = window.innerWidth;

    //     // Clear all first
    //     checkboxes.forEach(cb => cb.checked = false);


    //     // DESKTOP ( >1024 )
    //     if (width > 1024) {

    //         checkboxes.forEach(cb => cb.checked = true);

    //     }

    //     // TABLET (768 - 1024)
    //     else if (width <= 1024 && width > 425) {

    //         // ID, Name, EmpID, Phone, Project
    //         [0,1,2,3,4].forEach(i => {
    //             const cb = document.querySelector(
    //               `.ca-column-dropdown input[data-column="${i}"]`
    //             );
    //             if (cb) cb.checked = true;
    //         });

    //     }

    //     // MOBILE (426 - 425)
    //     else if (width <= 425 && width > 320) {

    //         // ID + Name
    //         [0,1].forEach(i => {
    //             const cb = document.querySelector(
    //               `.ca-column-dropdown input[data-column="${i}"]`
    //             );
    //             if (cb) cb.checked = true;
    //         });

    //     }

    //     // SMALL MOBILE (<=320)
    //     else {

    //         // Only Name
    //         const cb = document.querySelector(
    //           `.ca-column-dropdown input[data-column="1"]`
    //         );
    //         if (cb) cb.checked = true;

    //     }

    //     updateColumns();
    // }


//     // Checkbox click
//     checkboxes.forEach(cb => {
//         cb.addEventListener("change", updateColumns);
//     });


//     // Resize
//     let resizeTimer;

//     window.addEventListener("resize", function () {

//     clearTimeout(resizeTimer);

//     resizeTimer = setTimeout(() => {
//         applyResponsiveDefaults();
//     }, 300);

// });

// });

// function applyResponsiveDefaults() {

//   const width = window.innerWidth;

//   const checkboxes = document.querySelectorAll(
//     '#columnVisibilityDropdown input[type="checkbox"]'
//   );

//   // Reset all first
//   checkboxes.forEach(cb => cb.checked = false);

//   // Desktop
//   if (width > 1024) {
//       // Default desktop columns:
//     // ID, Name, EmpID, Phone, Project, Office, Laptop Brand, Company Laptop, Actions
//     const defaults = [0,1,2,3,4,5,6,7,13];

//     checkboxes.forEach(cb => {

//         const index = Number(cb.dataset.column);

//         cb.checked = defaults.includes(index);

//     });
//   }

//   // Tablet (768px)
//   else if (width <= 1024 && width > 425) {

//     const defaults = [0,1,2,3,4]; // ID, Name, EmpID, Phone, Project

//     checkboxes.forEach(cb => {
//       if (defaults.includes(Number(cb.dataset.column))) {
//         cb.checked = true;
//       }
//     });

//   }

//   // Mobile (425px)
//   else if (width <= 425 && width > 320) {

//     const defaults = [0,1]; // ID, Name

//     checkboxes.forEach(cb => {
//       if (defaults.includes(Number(cb.dataset.column))) {
//         cb.checked = true;
//       }
//     });

//   }

//   // Small mobile (320px)
//   else {

//     const defaults = [1]; // Name only

//     checkboxes.forEach(cb => {
//       if (defaults.includes(Number(cb.dataset.column))) {
//         cb.checked = true;
//       }
//     });

//   }

//   // Apply visibility after setting
//   updateColumnsFromCheckboxes();
// }

// function updateColumnsFromCheckboxes() {

//   const checkboxes = document.querySelectorAll(
//     '#columnVisibilityDropdown input[type="checkbox"]'
//   );

//   const table = document.querySelector('.ca-table-card table');

//   if (!table) return;

//   checkboxes.forEach(cb => {

//     const index = Number(cb.dataset.column);

//     const visible = cb.checked;

//     table.querySelectorAll('tr').forEach(row => {

//       if (row.children[index]) {
//         row.children[index].style.display = visible ? '' : 'none';
//       }

//     });

//   });
// }

</script>

<script>
// document.addEventListener("DOMContentLoaded", function () {
  
  // 1. Define Checkboxes
  // const checkboxes = document.querySelectorAll('#columnVisibilityDropdown input[type="checkbox"]');
  // const table = document.querySelector('.ca-table');

  // 2. Function to Apply Visibility
  // function applyColumnVisibility() {
  //   checkboxes.forEach(cb => {
  //     const colIndex = cb.getAttribute('data-column');
  //     const isChecked = cb.checked;
      
  //     // Target Headers and Cells
  //     const cells = table.querySelectorAll(`th[data-column="${colIndex}"], td[data-column="${colIndex}"]`);
      
  //     cells.forEach(cell => {
  //       if (isChecked) {
  //         cell.style.display = ""; // Reset inline style
  //         cell.classList.add('ca-force-show'); // Add force class
  //       } else {
  //         cell.classList.remove('ca-force-show'); // Remove force class
  //         cell.style.display = "none"; // Hide
  //       }
  //     });
  //   });
  // }

  // 3. Set Defaults based on Screen Size (Runs once on load)
  // function setDefaultResponsiveColumns() {
  //   const width = window.innerWidth;

  //   // Reset all checkboxes first
  //   checkboxes.forEach(cb => cb.checked = false);

  //   let columnsToShow = [];

  //   if (width > 1024) {
  //     // Desktop: All Standard Columns
  //     columnsToShow = [0, 1, 2, 3, 4, 5, 6, 7]; 
  //   } 
  //   else if (width >= 768 && width <= 1024) {
  //     // Tablet: ID, Name, EmpID, Phone, Project
  //     columnsToShow = [0, 1, 2, 3, 4];
  //   } 
  //   else {
  //     // Mobile: ID, Name
  //     columnsToShow = [0, 1];
  //   }

  //   // Check the boxes programmatically
  //   checkboxes.forEach(cb => {
  //     if (columnsToShow.includes(parseInt(cb.getAttribute('data-column')))) {
  //       cb.checked = true;
  //     }
  //   });

  //   // Apply to table
  //   applyColumnVisibility();
  // }

  // 4. Event Listeners
  
  // Listen for manual checkbox changes
  // checkboxes.forEach(cb => {
  //   cb.addEventListener('change', applyColumnVisibility);
  // });

  // Listen for resize (Debounced) to reset defaults if orientation changes drastically
  // let resizeTimer;
  // window.addEventListener('resize', () => {
  //   clearTimeout(resizeTimer);
  //   resizeTimer = setTimeout(() => {
  //     // Only reset defaults if you want strict responsive behavior
  //     // removing this call keeps user manual selection active during resize
  //     // setDefaultResponsiveColumns(); 
  //   }, 250);
  // });

  // Initialize
  // setDefaultResponsiveColumns();
// });
</script>

<script>
// document.addEventListener("DOMContentLoaded", function () {
  
  // 1. Define Elements
  // const checkboxes = document.querySelectorAll('#columnVisibilityDropdown input[type="checkbox"]');
  // const table = document.querySelector('.ca-table');

  // 2. Function to Apply Visibility based on Checkboxes
  // function applyColumnVisibility() {
  //   checkboxes.forEach(cb => {
  //     const colIndex = cb.getAttribute('data-column');
  //     const isChecked = cb.checked;
      
  //     // Target Headers and Cells
  //     const cells = table.querySelectorAll(`th[data-column="${colIndex}"], td[data-column="${colIndex}"]`);
      
  //     cells.forEach(cell => {
  //       if (isChecked) {
  //         cell.style.display = ""; // Reset to default (table-cell)
  //         cell.classList.add('ca-force-show'); 
  //       } else {
  //         cell.classList.remove('ca-force-show');
  //         cell.style.display = "none";
  //       }
  //     });
  //   });
  // }

  // 3. Set Defaults based on Screen Size
//   function setDefaultResponsiveColumns() {
//     const width = window.innerWidth;

//     // Reset: Uncheck all boxes first
//     checkboxes.forEach(cb => cb.checked = false);

//     let columnsToShow = [];

//     // ============================================
//     // DESKTOP & 1024px TABLET LANDSCAPE
//     // Show 6 Columns: ID, Name, EmpID, Phone, Project, Actions
//     // (Arrow is hidden by CSS at 1024px+)
//     // ============================================
//     if (width >= 1024) {
//       columnsToShow = [0, 1, 2, 3, 4, 13]; // 0-4 are data, Actions is auto-shown
//       // Note: We show 0-4. If you want ALL columns on large screens (>1200), change this logic.
//       // But for now, this ensures 1024px looks clean.
//       if(width > 1200) { 
//          columnsToShow = [0, 1, 2, 3, 4, 5, 6, 7]; // Full Desktop
//       }
//     } 
    
//     // ============================================
//     // TABLET PORTRAIT (768px to 1023px)
//     // Show 5 Columns: ID, Name, EmpID, Phone, Project
//     // (Arrow is shown by CSS)
//     // ============================================
//     else if (width >= 768 && width < 1024) {
//       columnsToShow = [0, 1, 2, 3, 4];
//     } 
    
//     // ============================================
//     // MOBILE (< 768px)
//     // Show 2 Columns: ID, Name
//     // ============================================
//     else {
//       columnsToShow = [0, 1];
//     }

//     // Apply checks to the boxes
//     checkboxes.forEach(cb => {
//       if (columnsToShow.includes(parseInt(cb.getAttribute('data-column')))) {
//         cb.checked = true;
//       }
//     });

//     // Apply visibility to table
//     applyColumnVisibility();
//   }

//   // 4. Event Listeners
//   checkboxes.forEach(cb => {
//     cb.addEventListener('change', applyColumnVisibility);
//   });

//   // Handle Resize
//   let resizeTimer;
//   window.addEventListener('resize', () => {
//     clearTimeout(resizeTimer);
//     resizeTimer = setTimeout(() => {
//        // Optional: Uncomment next line if you want columns to reset automatically when rotating screen
//        // setDefaultResponsiveColumns(); 
//     }, 250);
//   });

//   // 5. Initialize on Load
//   setDefaultResponsiveColumns();
// });
// </script>

<script>
document.addEventListener("DOMContentLoaded", function () {

  // EDIT BUTTON
  document.addEventListener("click", function (e) {

    const btn = e.target.closest(".btn-edit");
    if (!btn) return;

    const data = {
      id: btn.dataset.id,
      employee_name: btn.dataset.name,
      phone_number: btn.dataset.phone,
      project: btn.dataset.project,
      office_location: btn.dataset.location,
      company_laptop: btn.dataset.laptop,
      laptop_brand: btn.dataset.brand,
      laptop_charger: btn.dataset.charger,
      company_mouse: btn.dataset.mouse,
      sim_card: btn.dataset.sim,
      date_signature: btn.dataset.date
    };

    openEditAssetModal(data);
  });


 // DELETE BUTTON
document.addEventListener("click", function (e) {

  const btn = e.target.closest(".btn-delete");
  if (!btn) return;

  const id = btn.dataset.id;

  Swal.fire({
    title: "Delete Asset?",
    text: "This action cannot be undone.",
    icon: "warning",
    showCancelButton: true,
    confirmButtonColor: "#ef4444",
    cancelButtonColor: "#64748b",
    confirmButtonText: "Yes, delete it",
    cancelButtonText: "Cancel"
  }).then((result) => {

    if (!result.isConfirmed) return;

    fetch("companyassets.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded"
      },
      body: "action=delete&id=" + id
    })
    .then(res => res.text())
    .then(resp => {

      if (resp.trim() === "success") {

        Swal.fire({
          title: "Deleted!",
          text: "Asset has been removed.",
          icon: "success",
          timer: 1500,
          showConfirmButton: false
        });

        setTimeout(() => location.reload(), 1200);

      } else {

        Swal.fire({
          title: "Error",
          text: "Delete failed",
          icon: "error"
        });

      }
    });

  });

});

});
</script>

<!-- <script>
document.addEventListener("DOMContentLoaded", function () {

  const STORAGE_KEY = "companyAssetsColumnVisibility_v2";

  const checkboxes = document.querySelectorAll(
    '#columnVisibilityDropdown input[type="checkbox"]'
  );

  const table = document.querySelector(".ca-table");

  if (!table || !checkboxes.length) return;


  /* ==========================
     APPLY COLUMN VISIBILITY
  ========================== */
  function applyVisibility(map) {

    Object.keys(map).forEach(col => {

      const show = map[col];

      const headers = table.querySelectorAll(
        `th[data-column="${col}"]`
      );

      const cells = table.querySelectorAll(
        `td[data-column="${col}"]`
      );

      headers.forEach(h => {
        h.style.display = show ? "table-cell" : "none";
      });

      cells.forEach(c => {
        c.style.display = show ? "table-cell" : "none";
      });

    });
  }


  /* ==========================
     DEFAULTS BY SCREEN
  ========================== */
  function getDefaults() {

    const w = window.innerWidth;

    // Desktop
    if (w > 1024) {
      return [0,1,2,3,4,5,6,7,8,9,10,11,12];
    }

    // Tablet
    if (w > 768) {
      return [0,1,2,3,4];
    }

    // Mobile
    if (w > 425) {
      return [0,1];
    }

    // Small Mobile
    return [1];
  }


  /* ==========================
     LOAD SETTINGS
  ========================== */
  function loadSettings() {

    let saved = localStorage.getItem(STORAGE_KEY);

    let map = {};

    if (saved) {
      map = JSON.parse(saved);
    } 
    else {

      const defaults = getDefaults();

      checkboxes.forEach(cb => {

        const col = cb.dataset.column;

        map[col] = defaults.includes(Number(col));

      });

      localStorage.setItem(STORAGE_KEY, JSON.stringify(map));
    }


    // Sync checkboxes
    checkboxes.forEach(cb => {
      cb.checked = !!map[cb.dataset.column];
    });


    applyVisibility(map);
  }


  /* ==========================
     SAVE SETTINGS
  ========================== */
  function saveSettings() {

    const map = {};

    checkboxes.forEach(cb => {
      map[cb.dataset.column] = cb.checked;
    });

    localStorage.setItem(STORAGE_KEY, JSON.stringify(map));

    applyVisibility(map);
  }


  /* ==========================
     EVENTS
  ========================== */

  checkboxes.forEach(cb => {
    cb.addEventListener("change", saveSettings);
  });


  /* ==========================
     INIT
  ========================== */
  loadSettings();

});
</script> -->

<script>
document.addEventListener("DOMContentLoaded", function () {

  const checkboxes = document.querySelectorAll(
    "#columnVisibilityDropdown input[type='checkbox']"
  );

  const table = document.querySelector(".ca-table");

  if (!table || !checkboxes.length) return;


  function applyColumns() {

  checkboxes.forEach(cb => {

    const col = cb.dataset.column;

    if (col === "-1") return;

    const headers = table.querySelectorAll(
      `thead th[data-column="${col}"]`
    );

    const cells = table.querySelectorAll(
      `tbody td[data-column="${col}"]`
    );

    headers.forEach(th => {
      th.classList.toggle("ca-col-hidden", !cb.checked);
    });

    cells.forEach(td => {
      td.classList.toggle("ca-col-hidden", !cb.checked);
    });

  });
}



  function setDefaults() {

  const w = window.innerWidth;

  let defaults = [];

  /* ========== DESKTOP >1024 ========== */
  if (w > 1024) {
    defaults = [0,1,2,3,4,5,6,7,13]; // Actions only on desktop
  }

  /* ========== TABLET 769–1024 ========== */
  else if (w > 768 && w <= 1024) {
    defaults = [0,1,2,3,4]; // ❌ NO actions
  }

  /* ========== MOBILE 426–768 ========== */
  else if (w > 425 && w <= 768) {
    defaults = [0,1,2,3,4,14]; // ID + Name + Arrow
  }

  /* ========== SMALL MOBILE ≤425 ========== */
  else {
    defaults = [0,1,14]; // ID + Name + Arrow
  }

  // Reset first
  checkboxes.forEach(cb => {
    cb.checked = false;
  });

  // Apply
  defaults.forEach(i => {
    const cb = document.querySelector(`input[data-column="${i}"]`);
    if (cb) cb.checked = true;
  });

  applyColumns();
  }




  // When user clicks checkbox
  checkboxes.forEach(cb => {
    cb.addEventListener("change", applyColumns);
  });


  // On load
  setDefaults();


  // On resize
  let timer;
  window.addEventListener("resize", function () {
    clearTimeout(timer);
    timer = setTimeout(setDefaults, 200);
  });

});
</script>

<script>
// NOTIFICATION & PROFILE DROPDOWN HANDLER for Company Assets Page
document.addEventListener('DOMContentLoaded', function() {
  const notifBtn = document.querySelector('#notificationBtn');
  const notifContent = document.querySelector('#notif-content_box');
  const profileIcon = document.querySelector('#more_profile_icon');
  const profileContent = document.querySelector('#profile-content_box');
  
  if (notifBtn && notifContent) {
    notifBtn.addEventListener('click', function(e) {
      e.stopPropagation();
      notifContent.style.display = (notifContent.style.display === 'block') ? 'none' : 'block';
      if (profileContent) profileContent.style.display = 'none';
    });
  }
  
  if (profileIcon && profileContent) {
    profileIcon.addEventListener('click', function(e) {
      e.stopPropagation();
      profileContent.style.display = (profileContent.style.display === 'block') ? 'none' : 'block';
      if (notifContent) notifContent.style.display = 'none';
    });
  }
});
</script>

<script>
function applyServerFilters() {

    const filters = {
        employee_name: document.getElementById("filterEmployeeName").value.trim(),
        employee_id: document.getElementById("filterEmployeeID").value.trim(),
        phone_number: document.getElementById("filterPhoneNumber").value.trim(),
        project: document.getElementById("filterProject").value.trim(),
        office_location: document.getElementById("filterOfficeLocation").value.trim(),
        laptop_brand: document.getElementById("filterLaptopBrand").value.trim(),
        date_signature: document.getElementById("filterDateSignature").value.trim()
    };

    const url = new URL(window.location.href);

    // Reset page
    url.searchParams.set("page", "1");

    // Set / Remove params
    Object.keys(filters).forEach(key => {
        if (filters[key]) {
            url.searchParams.set(key, filters[key]);
        } else {
            url.searchParams.delete(key);
        }
    });

    // ✅ Close Modal
    closeFilterModal();

    // ✅ Restore scroll
    document.body.style.overflow = "auto";

    // ✅ Redirect
    window.location.href = url.toString();
}
</script>

<script>
/* =========================================
   CA FILTER MODAL — Custom Search Dropdown
   Scoped to: #filterModalOverlay only
   Prefix: cadd (Company Assets Drop Down)
   =========================================
   Safe: Does NOT touch edit modal, column
   visibility, global search, SweetAlert,
   pagination, table, or any other JS.
   ========================================= */

(function () {
    'use strict';

    // ── Config ──────────────────────────────────────────────
    // IDs of the four dropdowns we manage
   var CADD_IDS = [
    'cadd-employeeName',
    'cadd-employeeId',     // ✅ ADD
    'cadd-phoneNumber',    // ✅ ADD
    'cadd-project',
    'cadd-officeLocation',
    'cadd-laptopBrand'
];

    // ── State ───────────────────────────────────────────────
    var activeWrapper = null; // Currently open wrapper element

    var selections = {};   // ✅ ADD THIS

function ensureSelectionStore(wrapperId) {
    if (!selections[wrapperId]) {
        selections[wrapperId] = [];
    }
}

function renderSelections(wrapperId, input) {

    var wrapper = document.getElementById(wrapperId);
    if (!wrapper) return;

    var chipBox = wrapper.querySelector('.ca-selection-chips');

    if (!chipBox) {
        chipBox = document.createElement('div');
        chipBox.className = 'ca-selection-chips';
        wrapper.insertBefore(chipBox, input.nextSibling);
    }

    chipBox.innerHTML = '';

    selections[wrapperId].forEach(function(val) {

        var chip = document.createElement('span');
        chip.className = 'ca-chip';
        chip.innerHTML = val + " <span data-remove='" + val + "'>&times;</span>";

        chipBox.appendChild(chip);
    });
}

function toggleSelection(wrapperId, value, input) {

    ensureSelectionStore(wrapperId);

    var index = selections[wrapperId].indexOf(value);

    if (index === -1) {
        selections[wrapperId].push(value);
    } else {
        selections[wrapperId].splice(index, 1);
    }

    input.value = selections[wrapperId].join(', ');
    renderSelections(wrapperId, input);
}

    // ── Helpers ─────────────────────────────────────────────

    /**
     * Get the list element for a given wrapper id.
     */
    function getList(wrapperId) {
        return document.getElementById(wrapperId + '-list');
    }

    /**
     * Get the input element inside a wrapper.
     */
    function getInput(wrapperId) {
        var wrapper = document.getElementById(wrapperId);
        return wrapper ? wrapper.querySelector('.ca-custom-dropdown-input') : null;
    }

    /**
     * Open a specific dropdown list.
     * Closes any other open dropdown first.
     */
    function openDropdown(wrapperId) {
        // Close others
        CADD_IDS.forEach(function (id) {
            if (id !== wrapperId) {
                closeDropdown(id);
            }
        });

        var list = getList(wrapperId);
        if (!list) return;

        list.classList.add('ca-dd-open');
        activeWrapper = wrapperId;
    }

    /**
     * Close a specific dropdown list.
     */
    function closeDropdown(wrapperId) {
        var list = getList(wrapperId);
        if (!list) return;
        list.classList.remove('ca-dd-open');
        if (activeWrapper === wrapperId) {
            activeWrapper = null;
        }
    }

    /**
     * Close ALL dropdowns.
     */
    function closeAllDropdowns() {
        CADD_IDS.forEach(function (id) {
            closeDropdown(id);
        });
    }

    /**
     * Filter list items based on query string.
     * Adds "No results" row when nothing matches.
     */
    function filterList(wrapperId, query) {
        var list = getList(wrapperId);
        if (!list) return;

        var normalised = query.trim().toLowerCase();
        var items = list.querySelectorAll('.ca-custom-dropdown-item');
        var visibleCount = 0;

        items.forEach(function (item) {
            var text = (item.getAttribute('data-value') || '').toLowerCase();
            var matches = (normalised === '' || text.indexOf(normalised) !== -1);
            item.style.display = matches ? '' : 'none';
            if (matches) visibleCount++;
        });

        // Remove any previous "no results" row
        var noResults = list.querySelector('.ca-custom-dropdown-no-results');
        if (noResults) noResults.parentNode.removeChild(noResults);

        // Show "no results" only when there is a query and nothing matches
        if (normalised !== '' && visibleCount === 0) {
            var msg = document.createElement('div');
            msg.className = 'ca-custom-dropdown-no-results';
            msg.textContent = 'No results found';
            list.appendChild(msg);
        }
    }

    /**
     * Pre-fill inputs from URL params when filter modal opens.
     * (Matches existing plain-input behaviour.)
     */
    function prefillFromURL() {
        var params = new URLSearchParams(window.location.search);
        var map = {
    'cadd-employeeName':   'employee_name',
    'cadd-employeeId':     'employee_id',      // ✅ ADD THIS
    'cadd-phoneNumber':    'phone_number',     // ✅ ADD THIS
    'cadd-project':        'project',
    'cadd-officeLocation': 'office_location',
    'cadd-laptopBrand':    'laptop_brand'
};
        Object.keys(map).forEach(function (wrapperId) {
            var inp = getInput(wrapperId);
            if (inp) {
                var val = params.get(map[wrapperId]) || '';
                inp.value = val;
            }
        });
    }

    // ── Bootstrap each dropdown ──────────────────────────────

    function initDropdown(wrapperId) {
        var wrapper = document.getElementById(wrapperId);
        var list    = getList(wrapperId);
        var input   = getInput(wrapperId);

        if (!wrapper || !list || !input) return;

        // 1. Click on input → open & filter
        input.addEventListener('click', function (e) {
            e.stopPropagation(); // prevent document handler from closing immediately
            filterList(wrapperId, input.value);
            openDropdown(wrapperId);
        });

        // 2. Typing → filter & keep open
        input.addEventListener('input', function () {
            filterList(wrapperId, input.value);
            openDropdown(wrapperId);
        });

        // 3. Selecting an item
        list.addEventListener('mousedown', function (e) {
            // Use mousedown (fires before blur) to capture click
            var item = e.target.closest('.ca-custom-dropdown-item');
            if (!item) return;

            e.preventDefault(); // prevent input blur before value is set
            var value = item.getAttribute('data-value') || item.textContent;

toggleSelection(wrapperId, value, input);

closeDropdown(wrapperId);
filterList(wrapperId, '');
input.focus();
            closeDropdown(wrapperId);
            filterList(wrapperId, ''); // reset filter for next open
            input.focus();
        });

        // 4. Clicking inside the list but not on an item — stop propagation
        list.addEventListener('click', function (e) {
            e.stopPropagation();
        });

        // 5. Clicking on the wrapper — stop propagation
        wrapper.addEventListener('click', function (e) {
            e.stopPropagation();
        });

        wrapper.addEventListener('click', function(e) {

    var removeBtn = e.target.closest('[data-remove]');
    if (!removeBtn) return;

    var value = removeBtn.getAttribute('data-remove');

    ensureSelectionStore(wrapperId);

    var index = selections[wrapperId].indexOf(value);

    if (index !== -1) {
        selections[wrapperId].splice(index, 1);
    }

    input.value = selections[wrapperId].join(', ');
    renderSelections(wrapperId, input);
});

        // 6. Blur on input — close after a short delay to allow mousedown to fire
        input.addEventListener('blur', function () {
            setTimeout(function () {
                closeDropdown(wrapperId);
            }, 150);
        });
    }

    // ── Document-level click to close all dropdowns ──────────
    // Scoped: only fires when filter modal is open, uses capture: false

    document.addEventListener('click', function (e) {
        if (!activeWrapper) return;

        // If click is inside filterModalOverlay, let individual wrapper handlers manage
        // Otherwise close all
        var modal = document.getElementById('filterModalOverlay');
        if (modal && modal.contains(e.target)) {
            // Handled by wrapper stopPropagation — just close others
            closeAllDropdowns();
        } else {
            closeAllDropdowns();
        }
    });

    
    // ── Init on DOMContentLoaded ─────────────────────────────

    document.addEventListener('DOMContentLoaded', function () {
        CADD_IDS.forEach(function (id) {
            initDropdown(id);
        });

        prefillFromURL();

        // Re-run prefill each time the filter modal opens
        // Hook into the existing openFilterModal function safely
        var originalOpen = window.openFilterModal;
        if (typeof originalOpen === 'function') {
            window.openFilterModal = function () {
                originalOpen.apply(this, arguments);
                prefillFromURL();
                closeAllDropdowns();
                // Reset all filters when freshly opened
                CADD_IDS.forEach(function (id) {
                    filterList(id, getInput(id) ? getInput(id).value : '');
                });
            };
        }

        // Close dropdowns when filter modal closes
        var originalClose = window.closeFilterModal;
        if (typeof originalClose === 'function') {
            window.closeFilterModal = function () {
                closeAllDropdowns();
                originalClose.apply(this, arguments);
            };
        }
    });

})();
</script>

<!-- Floating Clear Filters Button -->
<button id="clearFiltersFloatingBtn" class="ca-clear-filters-floating">
    <i class="bi bi-x-circle"></i>
    Clear Filters
</button>

<script>
/* =========================================
   Floating Clear Filters Button Logic
   ========================================= */

document.addEventListener("DOMContentLoaded", function () {

    const floatingBtn = document.getElementById("clearFiltersFloatingBtn");

    if (!floatingBtn) return;

    const url = new URL(window.location.href);

    /* Detect active filters */
    const filterKeys = [
        "employee_name",
        "employee_id",
        "phone_number",
        "project",
        "office_location",
        "laptop_brand",
        "date_signature"
    ];

    let filtersApplied = false;

    filterKeys.forEach(key => {
        if (url.searchParams.get(key)) {
            filtersApplied = true;
        }
    });

    /* Show button only if filters exist */
    if (filtersApplied) {
        floatingBtn.style.display = "flex";
    }

    /* Clear filters on click */
    floatingBtn.addEventListener("click", function () {

        filterKeys.forEach(key => {
            url.searchParams.delete(key);
        });

        /* Reset page */
        url.searchParams.set("page", "1");

        window.location.href = url.toString();
    });

});
</script>


<?php include('htmlclose.php'); ?>
<?php 
if (isset($pdo)) {
    $pdo = null;
}

?>