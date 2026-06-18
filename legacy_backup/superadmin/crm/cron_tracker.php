<?php include('./htmlopen.php'); ?>
<?php
require_once '../config.php';

$config = new Config();
$conn = $config->getConnection();

$cronRows = [];
$cronError = '';

try {
  $stmt = $conn->prepare('SELECT * FROM cron_job ORDER BY id DESC');
  $stmt->execute();
  $cronRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $cronError = $e->getMessage();
}
?>

<link rel="stylesheet" href="../assets/css/unified_table_styles.css" />

<style>
  .content,
  .content *:not([class*="fa-"]):not(i):not(.fas):not(.far):not(.fab):not(.fal):not(.bi),
  .modal,
  .modal *:not([class*="fa-"]):not(i):not(.fas):not(.far):not(.fab):not(.fal):not(.bi),
  input,
  button,
  label,
  th,
  td {
    font-family: "Lexend Deca", sans-serif !important;
    font-optical-sizing: auto !important;
    font-style: normal !important;
  }

  .table-container {
    max-height: calc(100vh - 230px);
    overflow: auto;
  }

  .container {
    padding-left: 0 !important;
    padding-right: 0 !important;
  }

  #cronTable {
    width: 100%;
    min-width: 100% !important;
    table-layout: fixed;
  }

  #cronTable thead th:nth-child(1),
  #cronTable tbody td:nth-child(1) {
    width: 70px;
  }

  #cronTable thead th:last-child,
  #cronTable tbody td:last-child {
    width: 60px;
    text-align: center;
  }

  #cronTable.unified-table tbody td {
    padding: 14px !important;
  }

  #cronTable thead th,
  #cronTable tbody td {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  tr.details-row td {
    white-space: normal !important;
    overflow: visible !important;
    text-overflow: unset !important;
  }

  .chevron-cell {
    text-align: center;
    vertical-align: middle;
    cursor: pointer;
    padding: 8px !important;
  }

  .chevron-icon {
    width: 25px;
    height: 25px;
    background: #000;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.25s ease;
  }

  .chevron-icon i {
    color: #fff;
    font-size: 14px;
    transition: transform 0.25s ease;
  }

  tr.expanded .chevron-icon i {
    transform: rotate(90deg);
  }

  #cronTable tbody tr.data-row {
    cursor: pointer;
  }

  tr.details-row {
    background-color: #f8f9fa !important;
    cursor: default !important;
    display: table-row !important;
    position: relative;
    z-index: 3;
  }

  tr.details-row td {
    padding: 0 !important;
    border: none !important;
    background: #f8f9fa !important;
    border-radius: 10px !important;
  }

  .details-container {
    padding: 10px 14px;
  }

  .details-title {
    font-size: 18px;
    font-weight: 600;
    text-align: center;
    margin-bottom: 8px;
    color: #333;
  }

  .detail-item {
    margin-bottom: 5px;
    display: flex;
    align-items: baseline;
    justify-content: flex-start;
    gap: 10px;
    flex-wrap: wrap;
    width: 100%;
  }

  .detail-label {
    font-weight: 600;
    color: #6c757d;
    text-transform: uppercase;
    font-size: 12px;
    min-width: 180px;
    text-align: left !important;
    display: inline-block;
  }

  .detail-value {
    color: #333;
    font-size: 14px;
    font-weight: 400;
    word-break: break-word;
    text-align: left !important;
  }

  .assigned-user-cell {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    max-width: 100%;
  }

  .assigned-user-primary {
    display: inline-block;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    vertical-align: middle;
  }

  .assigned-users-trigger {
    border: none;
    background: transparent;
    color: #0d6efd;
    font-size: 13px;
    font-weight: 600;
    padding: 0;
    line-height: 1;
    cursor: pointer;
  }

  .assigned-users-trigger:hover {
    color: #0a58ca;
    text-decoration: underline;
  }

  #assignedUsersText {
    padding: 6px 0;
    font-size: 14px;
    color: #334155;
    word-break: break-word;
  }

  #assignedUsersModal .modal-content {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    box-shadow: 0 12px 30px rgba(15, 23, 42, 0.18);
  }

  #assignedUsersModal .modal-header {
    border-bottom: 1px solid #e2e8f0;
  }

  #assignedUsersModal .modal-title {
    color: #111827;
    font-weight: 700;
  }

  body.dark-mode #assignedUsersModal .modal-content {
    background: linear-gradient(145deg, #111827, #0f172a);
    border: none;
    box-shadow: 0 16px 40px rgba(0, 0, 0, 0.55);
  }

  body.dark-mode #assignedUsersModal .modal-header {
    background: rgba(30,30,30,0.95) !important;
    border-bottom: none !important;
  }

  body.dark-mode #assignedUsersModal .modal-body {
    background: rgba(30,30,30,0.95) !important;
  }

  body.dark-mode #assignedUsersModal .modal-title {
    color: #e2e8f0 !important;
  }

  body.dark-mode #assignedUsersText {
    color: #cbd5e1;
  }

  body.dark-mode #assignedUsersModal .btn-close {
    filter: invert(1) grayscale(100%) brightness(180%);
    opacity: 0.85;
  }

  body.dark-mode #assignedUsersModal .btn-close:hover {
    opacity: 1;
  }

  .rowSelector_wrap::after {
    content: '\25BE';
    position: absolute;
    right: 8px;
    top: 50%;
    transform: translateY(-50%);
    color: #495057;
    font-size: 20px;
    pointer-events: none;
  }

  .row{
    margin-right: 0px;
    margin-left: 0px;
  }

  #cronSearchInput:focus {
    outline: none;
    border-color: #007bff;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
  }

  .dt-layout-foot {
    background: transparent !important;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 5px;
    padding: 10px 0;
  }

  #cronPagination {
    display: flex;
    align-items: center;
    gap: 8px;
  }

  #cronPagination button,
  #cronPageNumbers button {
    border-radius: 6px !important;
    border: 1px solid #dee2e6 !important;
    background: #fff !important;
    padding: 6px 14px !important;
    transition: all 0.2s ease !important;
    font-size: 14px !important;
    font-weight: 500 !important;
    color: #495057 !important;
    min-width: auto !important;
    cursor: pointer;
    box-shadow: none !important;
  }

  #cronPagination button:hover:not(:disabled),
  #cronPageNumbers button:hover:not(:disabled) {
    background: #e9ecef !important;
    border-color: #adb5bd !important;
  }

  #cronPageNumbers button.active {
    background: #007bff !important;
    color: #fff !important;
    border-color: #007bff !important;
  }

  #cronPagination button:disabled {
    opacity: 0.5 !important;
    cursor: not-allowed !important;
  }

  #cronJumpToPage {
    display: flex;
    align-items: center;
    gap: 5px;
    width: max-content;
  }

  #cronJumpInput {
    width: 100px;
    padding: 6px 10px;
    border: none;
    border-radius: 6px 0 0 6px;
    font-size: 14px;
    background: #fff;
  }

  #cronJumpButton {
    padding: 6px 14px;
    background: #007bff;
    color: #fff;
    border: 1px solid #007bff;
    border-radius: 0 6px 6px 0;
    font-size: 14px;
    cursor: pointer;
    font-weight: 500;
  }

  #cronJumpButton:hover {
    background: #0056b3;
    border-color: #0056b3;
  }

  @media (min-width: 768px) {
    .container, .container-md, .container-sm {
        max-width: none;
    }
  }

  @media (min-width: 576px) {
    .container, .container-sm {
        max-width: none;
    }
}

  @media (max-width: 768px) {
    #cronTable {
      width: 100% !important;
      min-width: 100% !important;
      table-layout: fixed;
    }

    /* Override desktop colgroup sizing so hidden columns don't reserve width on mobile */
    #cronTable colgroup col {
      width: auto !important;
    }

    #cronTable col:nth-child(4),
    #cronTable col:nth-child(5) {
      display: none;
      width: 0 !important;
    }

    #cronTable col:nth-child(1) { width: 12% !important; }
    #cronTable col:nth-child(2) { width: 42% !important; }
    #cronTable col:nth-child(3) { width: 36% !important; }
    #cronTable col:nth-child(6) { width: 10% !important; }

    #cronTable thead th:nth-child(4),
    #cronTable tbody td:nth-child(4) {
      display: none;
    }

    #cronTable thead th:nth-child(5),
    #cronTable tbody td:nth-child(5) {
      display: none;
    }

    #cronTable thead th:nth-child(1),
    #cronTable tbody td:nth-child(1) { width: 12%; }
    #cronTable thead th:nth-child(2),
    #cronTable tbody td:nth-child(2) { width: 42%; }
    #cronTable thead th:nth-child(3),
    #cronTable tbody td:nth-child(3) { width: 36%; }
    #cronTable thead th:nth-child(6),
    #cronTable tbody td:nth-child(6) { width: 10%; }

    #cronTable thead th,
    #cronTable tbody td {
      padding-left: 10px !important;
      padding-right: 10px !important;
    }

    .dt-layout-foot {
      flex-direction: column;
      gap: 10px;
    }

    #cronRowInfo {
      text-align: center;
      order: 1;
    }

    #cronPagination {
      order: 2;
      justify-content: center;
    }

    #cronJumpToPage {
      order: 3;
      justify-content: center;
    }
  }

  @media (max-width: 425px) {
    /* Hide one more column on very small screens */
    #cronTable col:nth-child(3) {
      display: none;
      width: 0 !important;
    }

    #cronTable thead th:nth-child(3),
    #cronTable tbody td:nth-child(3) {
      display: none;
    }

    /* Reallocate width to remaining visible columns */
    #cronTable thead th:nth-child(1),
    #cronTable tbody td:nth-child(1) { width: 18%; }
    #cronTable thead th:nth-child(2),
    #cronTable tbody td:nth-child(2) { width: 72%; }
    #cronTable thead th:nth-child(6),
    #cronTable tbody td:nth-child(6) { width: 10%; }
  }

  @media (max-width: 1024px) {
    .table-container {
      overflow-x: hidden;
    }

    #cronTable {
      min-width: 100% !important;
      width: 100% !important;
      table-layout: fixed;
    }

    #cronTable thead th,
    #cronTable tbody td {
      padding: 10px 10px !important;
      font-size: 12px !important;
    }

    #cronTable thead th:nth-child(2),
    #cronTable tbody td:nth-child(2) {
      white-space: normal;
      word-break: break-word;
      line-height: 1.2;
    }
  }
</style>

<?php include('../header.php'); ?>

<div class="content">
  <div class="contentinside">
    <div class="container">
      <div class="row">
        <div class="col-lg-12">
          <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px; flex-wrap: nowrap;">
            <div style="flex: 1; min-width: 200px; position: relative;">
              <i class="bi bi-search" style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #6c757d; font-size: 16px;"></i>
              <input type="text" id="cronSearchInput" placeholder="Search cron jobs..." style="width: 100%; padding: 10px 12px 10px 38px; border: 1px solid #dee2e6; border-radius: 8px; font-size: 14px; transition: all 0.2s ease;" />
            </div>
            <div style="display: flex; align-items: center; gap: 8px;">
              <div class="rowSelector_wrap" style="position: relative;">
                <select id="cronRowsPerPage" style="padding: 10px 32px 10px 12px; border: 1px solid #dee2e6; border-radius: 8px; background: #fff; font-size: 14px; font-weight: 500; color: #495057; cursor: pointer; appearance: none;">
                  <option value="10">10</option>
                  <option value="25">25</option>
                  <option value="50">50</option>
                  <option value="100">100</option>
                </select>
              </div>
            </div>
          </div>

          <div class="table-container">
            <table id="cronTable" class="unified-table stripe row-border order-column display" cellspacing="0">
              <colgroup>
                <col style="width:10%;">
                <col style="width:24%;">
                <col style="width:22%;">
                <col style="width:18%;">
                <col style="width:16%;">
                <col style="width:10%;">
              </colgroup>
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Assigned User</th>
                  <th>Project Name</th>
                  <th>Remaining Leads</th>
                  <th>Interval Time</th>
                  <th></th>
                </tr>
              </thead>
              <tbody id="cronTrackings">
                <?php if ($cronError !== ''): ?>
                  <tr>
                    <td colspan="6" style="text-align:center; color:#dc3545;">Failed to load cron jobs: <?php echo htmlspecialchars($cronError); ?></td>
                  </tr>
                <?php elseif (!empty($cronRows)): ?>
                  <?php foreach ($cronRows as $row): ?>
                    <?php
                    $id = isset($row['id']) ? (string)$row['id'] : '';
                    $rowId = isset($row['row_id']) ? (string)$row['row_id'] : '';
                    $assignedUser = isset($row['assigned_user']) ? (string)$row['assigned_user'] : '';
                    $projectName = isset($row['project_name']) ? (string)$row['project_name'] : '';
                    $sourceLead = isset($row['source_lead']) ? (string)$row['source_lead'] : '';
                    $lastAssignedUser = isset($row['last_assigned_user']) ? (string)$row['last_assigned_user'] : '';
                    $intervalTime = isset($row['interval_time']) ? (string)$row['interval_time'] : '';
                    $location = isset($row['location']) ? (string)$row['location'] : '';

                    $remainingRowIds = array_values(array_filter(array_map('trim', explode(',', $rowId)), function($v) {
                      return $v !== '';
                    }));
                    $remainingCount = count($remainingRowIds);

                    $assignedUsersList = array_values(array_filter(array_map('trim', explode(',', $assignedUser)), function($v) {
                      return $v !== '';
                    }));
                    $primaryAssignedUser = count($assignedUsersList) > 0 ? $assignedUsersList[0] : '';
                    $hasMoreAssignedUsers = count($assignedUsersList) > 1;
                    $assignedUsersJson = htmlspecialchars(json_encode($assignedUsersList), ENT_QUOTES, 'UTF-8');
                    ?>
                    <tr class="data-row"
                      data-id="<?php echo htmlspecialchars($id, ENT_QUOTES); ?>"
                      data-remaining-count="<?php echo htmlspecialchars((string)$remainingCount, ENT_QUOTES); ?>"
                      data-assigned-user="<?php echo htmlspecialchars($assignedUser, ENT_QUOTES); ?>"
                      data-project-name="<?php echo htmlspecialchars($projectName, ENT_QUOTES); ?>"
                      data-source-lead="<?php echo htmlspecialchars($sourceLead, ENT_QUOTES); ?>"
                      data-last-assigned-user="<?php echo htmlspecialchars($lastAssignedUser, ENT_QUOTES); ?>"
                      data-interval-time="<?php echo htmlspecialchars($intervalTime, ENT_QUOTES); ?>"
                      data-location="<?php echo htmlspecialchars($location, ENT_QUOTES); ?>">
                      <td><?php echo htmlspecialchars($id); ?></td>
                      <td>
                        <span class="assigned-user-cell">
                          <span class="assigned-user-primary"><?php echo htmlspecialchars($primaryAssignedUser); ?></span>
                          <?php if ($hasMoreAssignedUsers): ?>
                            <button type="button" class="assigned-users-trigger" data-assigned-users="<?php echo $assignedUsersJson; ?>">...</button>
                          <?php endif; ?>
                        </span>
                      </td>
                      <td><?php echo htmlspecialchars($projectName); ?></td>
                      <td><?php echo htmlspecialchars((string)$remainingCount); ?></td>
                      <td><?php echo htmlspecialchars($intervalTime); ?></td>
                      <td class="chevron-cell"><div class="chevron-icon"><i class="fas fa-chevron-right"></i></div></td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="6" style="text-align:center;">No cron jobs found.</td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

          <div class="row mt-3">
            <div class="col-lg-12">
              <div class="dt-layout-foot">
                <div id="cronRowInfo" style="font-size: 14px; font-weight: 500;">Showing 0 to 0 of 0 entries</div>
                <div class="pagination" id="cronPagination">
                  <button id="cronPrevButton" disabled>←</button>
                  <span id="cronPageNumbers" style="display:flex; align-items:center; gap:5px;"></span>
                  <button id="cronNextButton" disabled>→</button>
                </div>
                <div id="cronJumpToPage" class="search">
                  <input type="number" id="cronJumpInput" class="searchTerm" placeholder="Page No." min="1" />
                  <button id="cronJumpButton" class="searchButton">Jump</button>
                </div>
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="assignedUsersModal" tabindex="-1" aria-labelledby="assignedUsersModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius: 14px;">
      <div class="modal-header" style="padding: 10px 16px;">
        <h5 class="modal-title" id="assignedUsersModalLabel" style="font-size: 22px;">Assigned Users</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" style="padding: 12px 16px 16px;">
        <div id="assignedUsersText"></div>
      </div>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script type="text/javascript" src="../assets/js/script.js"></script>

<script>
  $(document).ready(function () {
    function escapeHtml(value) {
      const str = value === null || value === undefined ? '' : String(value);
      return str
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    }

    function toggleCronDetails($clickedRow) {
      const $existingDetails = $clickedRow.next('.details-row');

      if ($existingDetails.length > 0) {
        $clickedRow.removeClass('expanded');
        $existingDetails.remove();
        return;
      }

      $('#cronTrackings tr.data-row.expanded').removeClass('expanded');
      $('#cronTrackings tr.details-row').remove();
      $clickedRow.addClass('expanded');

      const rowData = {
        id: $clickedRow.data('id'),
        remainingCount: $clickedRow.data('remaining-count'),
        assignedUser: $clickedRow.data('assigned-user'),
        projectName: $clickedRow.data('project-name'),
        sourceLead: $clickedRow.data('source-lead'),
        lastAssignedUser: $clickedRow.data('last-assigned-user'),
        intervalTime: $clickedRow.data('interval-time'),
        location: $clickedRow.data('location')
      };

      const detailsHtml = `
        <tr class="details-row" style="display: table-row;">
          <td colspan="6" style="display: table-cell; width: 100%;">
            <div class="details-container">
              <div class="details-title">Cron Job Details</div>
              <div class="detail-item"><span class="detail-label">ID:</span><span class="detail-value">${escapeHtml(rowData.id)}</span></div>
              <div class="detail-item"><span class="detail-label">REMAINING LEADS:</span><span class="detail-value">${escapeHtml(rowData.remainingCount)}</span></div>
              <div class="detail-item"><span class="detail-label">ASSIGNED USER:</span><span class="detail-value">${escapeHtml(rowData.assignedUser)}</span></div>
              <div class="detail-item"><span class="detail-label">PROJECT NAME:</span><span class="detail-value">${escapeHtml(rowData.projectName)}</span></div>
              <div class="detail-item"><span class="detail-label">SOURCE LEAD:</span><span class="detail-value">${escapeHtml(rowData.sourceLead)}</span></div>
              <div class="detail-item"><span class="detail-label">LAST ASSIGNED USER:</span><span class="detail-value">${escapeHtml(rowData.lastAssignedUser)}</span></div>
              <div class="detail-item"><span class="detail-label">INTERVAL TIME:</span><span class="detail-value">${escapeHtml(rowData.intervalTime)}</span></div>
              <div class="detail-item"><span class="detail-label">LOCATION:</span><span class="detail-value">${escapeHtml(rowData.location)}</span></div>
            </div>
          </td>
        </tr>
      `;

      $clickedRow.after(detailsHtml);
    }

    $(document).on('click', '#cronTrackings tr.data-row', function (e) {
      if ($(e.target).closest('button, input, a, select').length) {
        return;
      }
      toggleCronDetails($(this));
    });

    $(document).on('click', '#cronTrackings tr.data-row .chevron-cell, #cronTrackings tr.data-row .chevron-icon', function (e) {
      e.preventDefault();
      e.stopPropagation();
      toggleCronDetails($(this).closest('tr.data-row'));
    });

    $(document).on('click', '.assigned-users-trigger', function (e) {
      e.preventDefault();
      e.stopPropagation();

      let users = [];
      const rawUsers = $(this).attr('data-assigned-users') || '[]';

      try {
        users = JSON.parse(rawUsers);
      } catch (err) {
        users = [];
      }

      const $text = $('#assignedUsersText');
      $text.empty();

      if (!Array.isArray(users) || users.length === 0) {
        $text.text('No assigned users found');
      } else {
        const safeUsers = users.map(function (user) {
          return escapeHtml(user);
        });
        $text.html(safeUsers.join(', '));
      }

      const modalEl = document.getElementById('assignedUsersModal');
      const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
      modal.show();
    });

    let currentPage = 1;
    let rowsPerPage = parseInt($('#cronRowsPerPage').val(), 10) || 10;
    let filteredRows = [];

    function collectFilteredRows() {
      const searchTerm = ($('#cronSearchInput').val() || '').toLowerCase();
      filteredRows = $('#cronTrackings tr.data-row').filter(function () {
        return $(this).text().toLowerCase().includes(searchTerm);
      }).toArray();
    }

    function renderPage() {
      const totalRows = filteredRows.length;
      const totalPages = Math.max(1, Math.ceil(totalRows / rowsPerPage));
      if (currentPage > totalPages) {
        currentPage = totalPages;
      }

      $('#cronTrackings tr.data-row').hide().removeClass('expanded');
      $('#cronTrackings tr.details-row').remove();

      const startIdx = (currentPage - 1) * rowsPerPage;
      const endIdx = Math.min(startIdx + rowsPerPage, totalRows);
      for (let i = startIdx; i < endIdx; i += 1) {
        $(filteredRows[i]).show();
      }

      const startRow = totalRows === 0 ? 0 : startIdx + 1;
      const endRow = totalRows === 0 ? 0 : endIdx;
      $('#cronRowInfo').text(`Showing ${startRow} to ${endRow} of ${totalRows} entries`);

      const hasPrev = currentPage > 1;
      const hasNext = currentPage < totalPages;
      $('#cronPrevButton').prop('disabled', !hasPrev);
      $('#cronNextButton').prop('disabled', !hasNext);

      const $pageNumbers = $('#cronPageNumbers');
      $pageNumbers.empty();
      if (totalRows === 0) {
        return;
      }

      let startPage = Math.max(1, currentPage - 2);
      let endPage = Math.min(totalPages, startPage + 4);
      if (endPage - startPage < 4) {
        startPage = Math.max(1, endPage - 4);
      }

      for (let p = startPage; p <= endPage; p += 1) {
        const $btn = $('<button type="button"></button>').text(p);
        if (p === currentPage) {
          $btn.addClass('active');
        }
        $btn.on('click', function () {
          currentPage = p;
          renderPage();
        });
        $pageNumbers.append($btn);
      }
    }

    function applySearchAndRender(resetPage) {
      if (resetPage) {
        currentPage = 1;
      }
      collectFilteredRows();
      renderPage();
    }

    $('#cronSearchInput').on('keyup', function () {
      applySearchAndRender(true);
    });

    $('#cronRowsPerPage').on('change', function () {
      rowsPerPage = parseInt($(this).val(), 10) || 10;
      applySearchAndRender(true);
    });

    $('#cronPrevButton').on('click', function () {
      if (currentPage > 1) {
        currentPage -= 1;
        renderPage();
      }
    });

    $('#cronNextButton').on('click', function () {
      const totalPages = Math.max(1, Math.ceil(filteredRows.length / rowsPerPage));
      if (currentPage < totalPages) {
        currentPage += 1;
        renderPage();
      }
    });

    $('#cronJumpButton').on('click', function () {
      const pageNum = parseInt($('#cronJumpInput').val(), 10);
      const totalPages = Math.max(1, Math.ceil(filteredRows.length / rowsPerPage));

      if (!Number.isNaN(pageNum) && pageNum >= 1 && pageNum <= totalPages) {
        currentPage = pageNum;
        renderPage();
        $('#cronJumpInput').val('');
      } else {
        alert(`Please enter a page number between 1 and ${totalPages}`);
      }
    });

    $('#cronJumpInput').on('keypress', function (e) {
      if (e.which === 13) {
        $('#cronJumpButton').click();
      }
    });

    applySearchAndRender(true);
  });
</script>
