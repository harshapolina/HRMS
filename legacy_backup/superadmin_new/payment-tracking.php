<?php include('htmlopen.php'); ?>
<style>
     .side-menu li.sideactive4{
      background: var(--shicol);
    position: relative
  }
  .side-menu li.sideactive4 a{
      color: white
  }
  .addNewUserModal,.downloadCsvBtn{ 
  display: none;
  }
  .save-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 5px 15px;
            background-color: #28a745;
            color: white;
            border: none;
            border-radius: 25px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s ease, box-shadow 0.3s ease;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .save-button:hover {
            background-color: #218838;
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.2);
        }

        .save-button:active {
            background-color: #1e7e34;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .save-button .icon {
            margin-right: 8px;
            font-size: 18px;
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
                <th>Overall Earning</th>
                <th>Overall Build</th>
                <th>Advance Amount</th>
                <th>Remaining Payment</th>
                <th>User Name</th>
                <th>Booking Number</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody id="paymenttable">
              <?php
              if (!empty($payment_data)) {
                foreach ($payment_data as $row) {
                  echo "<tr>";
                  echo "<td>" . $row['id'] . "</td>";
                  echo "<td>" . $row['overall_earn'] . "</td>";
                  echo "<td>₹ " . $row['overall_paid'] . "</td>";

                  // Add an input field for editing the advance_pay
                  echo "<td><input type='text' id='edit_advance_pay_" . $row['id'] . "' value='" . $row['advance_pay'] . "'></td>";

                  echo "<td>₹ " . $row['remaning_payment'] . "</td>";
                  echo "<td>" . $row['user_name'] . "</td>";
                  echo "<td> " . $row['bookin_number'] . "</td>";

                  // Add an Edit button to submit changes
                  echo "<td onclick='saveData();'><button class='save-button' onclick='editAdvancePay(" . $row['id'] . ")'>Save</button></td>";

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
                <td>Overall Earning</td>
                <td>Overall Build</td>
                <td>Advance Amount</td>
                <td>Remaining Payment</td>
                <td>User Name</td>
                <td>Booking Number</td>
                <td>Action</td>
              </tr>
            </tfoot>
          </table>
          <!-- data submitted successfully popup -->
            <div id="notificationPopup" style="background-color:green;color:white">Your data has been submitted successfully</div>
          <!-- data submitted successfully popup end -->
          <!-- overlay for background -->
            <div id="overlay"></div>
          <!-- overlay for background end-->
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
                                        <label for="Overallearn">Overall earn</label>
                                        <input type="text" class="form-control form-control-lg" id="Overallearn">
                                    </div>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <div class="form-item">
                                        <label for="Overallpaid">Overall paid</label>
                                        <input type="text" class="form-control form-control-lg" id="Overallpaid">
                                    </div>
                                </div>
                                    <div class="col-md-4 mb-2">
                                    <div class="form-item">
                                        <label for="Advancepay">Advance pay</label>
                                        <input type="text" class="form-control form-control-lg" id="Advancepay">
                                    </div>
                                </div>
                                    <div class="col-md-4 mb-2">
                                    <div class="form-item">
                                        <label for="Remaningpayment">Remaning payment</label>
                                        <input type="text" class="form-control form-control-lg" id="Remaningpay">
                                    </div>
                                </div>
                                    <div class="col-md-4 mb-2">
                                    <div class="form-item">
                                        <label for="Username">User name</label>
                                        <input type="text" class="form-control form-control-lg" id="Username">
                                    </div>
                                </div>
                                    <div class="col-md-12 mb-2">
                                    <div class="form-item">
                                        <label for="Bookingnumber">Booking number</label>
                                        <input type="text" class="form-control form-control-lg" id="Bookingno">
                                    </div>
                                </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer" style="margin: 0 auto;">
                            <!-- Close Modal button -->
                            <button type="button" class="btn btn-secondary" data-dismiss="modal"
                                id="cancleFilter">Close</button>
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
        { id: "Overallearn", columnIndex: 1 },
        { id: "Overallpaid", columnIndex: 2 },
        { id: "Advancepay", columnIndex: 3 },
        { id: "Remaningpay", columnIndex: 4 },
        { id: "Username", columnIndex: 5 },
        { id: "Bookingno", columnIndex: 6 }
    ];
    var activeFilters = [];
    $("#paymenttable tr").each(function() {
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
    $("#paymenttable tr").hide();
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
        $("#filterID,#Overallearn,#Overallpaid,#Advancepay,#Remaningpay,#Username,#Bookingno").val("");
        applyFilters();
        $("#closeFilter").click(); // Simulate a click on the close button
    });
});
</script>
<script>
  function editAdvancePay(id) {
    var newAdvancePay = document.getElementById('edit_advance_pay_' + id).value;

    // Assuming you have an AJAX function to send the updated value to the server
    // You need to implement the updateAdvancePay function in your action.php file
    updateAdvancePay(id, newAdvancePay);
  }

  function updateAdvancePay(id, newAdvancePay) {
    // Use AJAX to send the updated value to the server
    // You can use jQuery.ajax or fetch API for this purpose
    // Send an AJAX request to update the "advance_pay" in the database
    // Example using jQuery.ajax:
    $.ajax({
      type: 'POST',
      url: 'action.php',
      data: {
        action: 'update_advance_pay',
        id: id,
        newAdvancePay: newAdvancePay
      },
      success: function(response) {
        // Handle the response from the server (e.g., show a success message)
        console.log(response);
      },
      error: function(error) {
        // Handle the error (e.g., show an error message)
        console.error(error);
      }
    });
  }
</script>
<script>
    function saveData() {
      var notificationPopup = document.getElementById('notificationPopup');
      var overlay = document.getElementById('overlay');
      overlay.style.display = 'block';
      notificationPopup.style.display = 'block';
      setTimeout(function() {
        notificationPopup.style.display = 'none';
        overlay.style.display = 'none';
      }, 2000);
    }
  </script>
<?php include('htmlclose.php'); ?>
<?php
// Close the connection
$conn->close();
?>