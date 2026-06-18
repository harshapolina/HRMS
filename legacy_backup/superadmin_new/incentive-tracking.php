<?php include('htmlopen.php'); ?>
<style>
     .side-menu li.sideactive3{
      background: var(--shicol);
    position: relative
  }
  .side-menu li.sideactive3 a{
      color: white
  }
  .addNewUserModal,.downloadCsvBtn{ 
  display: none;
  }
</style>
<?php include('header.php'); ?>
<!-- Main Content -->
<div class="content">
<div class="contentinside">
  <div class="container">
    <div class="row">
      <div class="col-lg-12">
        <div class="table-container">
          <table id="example" class="stripe row-border order-column display" cellspacing="0" style="width:100%">
            <thead>
              <tr>
                <th>ID</th>
                <th>Month</th>
                <th>Generated Revenue</th>
                <th>Recent Payment</th>
                <th>Remaining Amount</th>
                <th>Build Amount</th>
                <th>User Name</th>
                <th>Booking Number</th>
              </tr>
            </thead>
            <tbody id="trackings">
            <?php
            // Check if there is any data in the tracking table
            if (!empty($tracking_data)) {
                foreach ($tracking_data as $row) {
                    echo "<tr>";
                    echo "<td>" . $row['id'] . "</td>";
                    echo "<td>" . $row['month'] . "</td>";
                    echo "<td>₹ " . $row['gen_revenue'] . "</td>";
                    echo "<td>₹ " . $row['recent_pay'] . "</td>";
                    echo "<td>₹ " . $row['remaning_amt'] . "</td>";
                    echo "<td>₹ " . $row['send_amt'] . "</td>";
                    echo "<td>" . $row['user_name'] . "</td>";
                    echo "<td>" . $row['bookin_number'] . "</td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='8'>No data found</td></tr>";
            }
            ?>
        </tbody>
            <tfoot>
              <tr>
                <td>ID</td>
                <td>Month</td>
                <td>Generated Revenue</td>
                <td>Recent Payment</td>
                <td>Remaining Amount</td>
                <td>Build Amount</td>
                <td>User Name</td>
                <td>Booking Number</td>
              </tr>
            </tfoot>
          </table>
          <button id="scroll-left"><i class='bx bx-left-arrow-alt'></i></button>
          <button id="scroll-right"><i class='bx bx-right-arrow-alt'></i></button>
        </div>
      </div>
    </div>
<!-- Filter Rows Modal Start -->
  <div class="modal fade" tabindex="-1" id="filterModal">
              <div class="modal-dialog modal-lg modal-dialog-centered">
                  <div class="modal-content">
                      <div class="modal-header">
                          <h5 class="modal-title">Filter Data</h5>
                          <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close"
                              id="closeFilter"></button>
                      </div>
                      <div class="modal-body">
                          <div class="container">
                              <div class="row">
                                  <!-- Filter inputs -->
                                  <div class="col-md-4 mb-2">
                                  <div class="form-item">
                                      <label for="ID">ID</label>
                                      <input type="text" class="form-control form-control-lg" id="filterID">
                                  </div>
                              </div>
                                  <div class="col-md-4 mb-2">
                                  <div class="form-item">
                                      <label for="Month">Month</label>
                                      <input type="text" class="form-control form-control-lg" id="Month">
                                  </div>
                              </div>
                                  <div class="col-md-4 mb-2">
                                  <div class="form-item">
                                      <label for="Generated Revenue">Generated revenue</label>
                                      <input type="text" class="form-control form-control-lg" id="generatedamt">
                                  </div>
                              </div>
                                  <div class="col-md-4 mb-2">
                                  <div class="form-item">
                                      <label for="Recent Pay">Recent pay</label>
                                      <input type="text" class="form-control form-control-lg" id="RecentPay">
                                  </div>
                              </div>
                                  <div class="col-md-4 mb-2">
                                  <div class="form-item">
                                      <label for="Remaining amount">Remaining amount</label>
                                      <input type="text" class="form-control form-control-lg" id="Remainingamt">
                                  </div>
                              </div>
                                  <div class="col-md-4 mb-2">
                                  <div class="form-item">
                                      <label for="User name">User name</label>
                                      <input type="text" class="form-control form-control-lg" id="Username">
                                  </div>
                              </div>
                                  <div class="col-md-4 mb-2">
                                  <div class="form-item">
                                      <label for="Booking number">Booking number</label>
                                      <input type="text" class="form-control form-control-lg" id="Bookingno">
                                  </div>
                              </div>
                                  <div class="col-md-4 mb-2">
                                  <div class="form-item">
                                      <label for="Sent amount">Sent amount</label>
                                      <input type="text" class="form-control form-control-lg" id="Sentamount">
                                  </div>
                              </div>
                              </div>
                          </div>
                      </div>
                      <div class="modal-footer" style="margin: 0 auto;">
                          <!-- Close Modal button -->
                          <button type="button" class="btn btn-secondary" data-dismiss="modal" id="cancleFilter">Close</button>
                          <!-- Clear Filters button -->
                          <button type="button" class="btn btn-danger" id="clearFiltersBtn">Clear Filters</button>
                          <!-- Apply Filters button -->
                          <button type="button" class="btn btn-primary" id="applyFiltersBtn">Apply Filters</button>
                      </div>
                  </div>
              </div>
          </div>
<!-- filter rows Modal End -->
  </div>
</div>
</div>
<!--End Main Content -->
<script>
  function applyFilters() {
    var filterInputs = [
        { id: "filterID", columnIndex: 0 },
        { id: "Month", columnIndex: 1 },
        { id: "generatedamt", columnIndex: 2 },
        { id: "RecentPay", columnIndex: 3 },
        { id: "Remainingamt", columnIndex: 4 },
        { id: "Sentamount", columnIndex: 5 },
        { id: "Username", columnIndex: 6 },
        { id: "Bookingno", columnIndex: 7 }
    ];
    var activeFilters = [];
    $("#trackings tr").each(function() {
        var row = $(this);
        var showRow = true;
        filterInputs.forEach(function(inputInfo) {
            var input = $("#" + inputInfo.id);
            var filterValue = input.val().toLowerCase();
            var cellValue = row.find("td:eq(" + inputInfo.columnIndex + ")").text().toLowerCase();
            if (cellValue.indexOf(filterValue) === -1) {
                showRow = false;
                return false;
            }
            if (filterValue.trim() !== "") {
                activeFilters.push(filterValue);
            }
        });
        if (showRow) {
            row.addClass("custom-filtered-row");
        } else {
            row.removeClass("custom-filtered-row");
        }
    });

    $("#trackings tr").hide();
    applyCustomFilter();
}

function applyCustomFilter() {
    $(".custom-filtered-row").show();
}

$(document).ready(function() {
    $(".filterable .btn-filter1").click(function() {
        $("#filterModal").modal("show");
    });

    $("#applyFiltersBtn").click(function() {
        applyFilters();
        $("#closeFilter").click(); // Simulate a click on the close button
    });

    $("#filterModal").on("hidden.bs.modal", function() {
        $(".filterable .filters input").val("");
        applyFilters();
    });

    $("#closeFilter").click(function() {
        applyFilters();
        $("#filterModal").modal("hide");
    });

    $("#cancleFilter").click(function() {
        applyFilters();
        $("#filterModal").modal("hide");
    });

    $("#clearFiltersBtn").click(function() {
        $("#filterID, #Month, #generatedamt, #RecentPay, #Remainingamt, #Sentamount, #Username, #Bookingno").val("");
        applyFilters();
        $("#closeFilter").click();
    });
});
</script>
<?php include('htmlclose.php'); ?>
<?php
// Close the connection
$conn->close();
?>