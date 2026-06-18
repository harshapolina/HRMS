<?php include('htmlopen.php'); ?>
<style>
     .side-menu li.sideactive5{
      background: var(--shicol);
    position: relative
  }
  .side-menu li.sideactive5 a{
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
                <th>Employee Name</th>
                <th>Employee Id</th>
                <th>Phone Number</th>
                <th>Project</th>
                <th>Office Location</th>
                <th>Company Laptop</th>
                <th>Laptop Brand</th>
                <th>Laptop Id</th>
                <th>Laptop Charger</th>
                <th>Company Mouse</th>
                <th>SIM Card</th>
                <th>DateSignature</th>
                <th>Operations</th>
              </tr>
            </thead>
            <tbody id="compamyassets">
              <?php
              if (!empty($assets_data)) {
                foreach ($assets_data as $row) {
                  echo "<tr>";
                  echo "<td>" . $row['id'] . "</td>";
                  echo "<td>" . $row['employee_name'] . "</td>";
                  echo "<td>" . $row['employee_id'] . "</td>";
                  echo "<td>" . $row['phone_number'] . "</td>";
                  echo "<td>" . $row['project'] . "</td>";
                  echo "<td>" . $row['office_location'] . "</td>";
                  echo "<td>" . $row['company_laptop'] . "</td>";
                  echo "<td>" . $row['laptop_brand'] . "</td>";
                  echo "<td>" . $row['laptop_id'] . "</td>";
                  echo "<td>" . $row['laptop_charger'] . "</td>";
                  echo "<td>" . $row['company_mouse'] . "</td>";
                  echo "<td>" . $row['sim_cad'] . "</td>";
                  echo "<td>" . $row['datesignature'] . "</td>";
                  echo "<td>";
                  echo "<button class='edit-btn' onclick='editRow(" . $row['id'] . ")'>Edit</button>";
                  echo "<button class='delete-btn' onclick='deleteRow(" . $row['id'] . ")'>Delete</button>";
                  echo "</td>";
                  echo "</tr>";
                }
              } else {
                echo "<tr><td colspan='13'>No data found</td></tr>";
              }
              ?>
            </tbody>
            <tfoot>
              <tr>
                <td>ID</td>
                <td>Employee Name</td>
                <td>Employee Id</td>
                <td>Phone Number</td>
                <td>Project</td>
                <td>Office Location</td>
                <td>Company Laptop</td>
                <td>Laptop Brand</td>
                <td>Laptop Id</td>
                <td>Laptop Charger</td>
                <td>Company Mouse</td>
                <td>SIM Card</td>
                <td>DateSignature</td>
                <td>Operations</td>
              </tr>
            </tfoot>
          </table>
          <button id="scroll-left"><i class='bx bx-left-arrow-alt'></i></button>
          <button id="scroll-right"><i class='bx bx-right-arrow-alt'></i></button>
        </div>
      </div>
    </div>
  </div>
<!-- Filter Rows Modal Start -->
  <div class="modal fade" tabindex="-1" id="filterModal">
              <div class="modal-dialog modal-dialog-centered">
                  <div class="modal-content">
                      <div class="modal-header">
                          <h5 class="modal-title">Filter Data</h5>
                          <button type="button" class="btn-close" data-dismiss="modal" aria-label="Close"
                              id="closeFilter"></button>
                      </div>
                      <div class="modal-body">
                          <div class="container">
                              <div class="row">
                                  
                              <div class="col-md-6 mb-2">
                                  <div class="form-item">
                                      <label for="ID">ID</label>
                                      <input type="text" class="form-control form-control-lg" id="filterID">
                                  </div>
                              </div>

                              <div class="col-md-6 mb-2">
                                  <div class="form-item">
                                      <label for="Employeename">Employee name</label>
                                      <input type="text" class="form-control form-control-lg" id="Employeename">
                                  </div>
                              </div>

                              <div class="col-md-6 mb-2">
                                  <div class="form-item">
                                      <label for="Employeeid">Employee id</label>
                                      <input type="text" class="form-control form-control-lg" id="Employeeid">
                                  </div>
                              </div>

                              <div class="col-md-6 mb-2">
                                  <div class="form-item">
                                      <label for="Phonenumber">Phone number</label>
                                      <input type="text" class="form-control form-control-lg" id="Phoneno">
                                  </div>
                              </div>

                              <div class="col-md-6 mb-2">
                                  <div class="form-item">
                                      <label for="Project">Project</label>
                                      <input type="text" class="form-control form-control-lg" id="Project">
                                  </div>
                              </div>

                              <div class="col-md-6 mb-2">
                                  <div class="form-item">
                                      <label for="Officelocation">Office location</label>
                                      <input type="text" class="form-control form-control-lg" id="location">
                                  </div>
                              </div>

                              <div class="col-md-6 mb-2">
                                  <div class="form-item">
                                      <label for="CompanyLaptop">Company Laptop</label>
                                      <input type="text" class="form-control form-control-lg" id="CompanyLaptop">
                                  </div>
                              </div>

                              <div class="col-md-6 mb-2">
                                  <div class="form-item">
                                      <label for="Laptop Brand">Laptop Brand</label>
                                      <input type="text" class="form-control form-control-lg" id="LaptopBrand">
                                  </div>
                              </div>

                              <div class="col-md-6 mb-2">
                                  <div class="form-item">
                                      <label for="Laptopid">Laptop id</label>
                                      <input type="text" class="form-control form-control-lg" id="Laptopid">
                                  </div>
                              </div>

                              <div class="col-md-6 mb-2">
                                  <div class="form-item">
                                      <label for="Laptopcharger">Laptop charger</label>
                                      <input type="text" class="form-control form-control-lg" id="Laptopcharger">
                                  </div>
                              </div>

                              <div class="col-md-6 mb-2">
                                  <div class="form-item">
                                      <label for="Companymouse">Company mouse</label>
                                      <input type="text" class="form-control form-control-lg" id="Companymouse">
                                  </div>
                              </div>
                                  
                              <div class="col-md-6 mb-2">
                                  <div class="form-item">
                                      <label for="Simcard">Sim card</label>
                                      <input type="text" class="form-control form-control-lg" id="Simcard">
                                  </div>
                              </div>
                                    
                              <div class="col-md-12 mb-2">
                                  <div class="form-item">
                                      <label for="Datesignature">Date signature</label>
                                      <input type="text" class="form-control form-control-lg" id="Datesignature">
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
<!--End Main Content -->
<script>
  function applyFilters() {
            var filterInputs = [
                { id: "filterID", columnIndex: 0 },
                { id: "Employeename", columnIndex: 1 },
                { id: "Employeeid", columnIndex: 2 },
                { id: "Phoneno", columnIndex: 3 },
                { id: "Project", columnIndex: 4 },
                { id: "location", columnIndex: 5 },
                { id: "CompanyLaptop", columnIndex: 6 },
                { id: "LaptopBrand", columnIndex: 7 },
                { id: "Laptopid", columnIndex: 8 },
                { id: "Laptopcharger", columnIndex: 9 },
                { id: "Companymouse", columnIndex: 10 },
                { id: "Simcard", columnIndex: 11 },
                { id: "Datesignature", columnIndex: 12 }
            ];
            var activeFilters = [];
            $("#compamyassets tr").each(function() {
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
            $("#compamyassets tr").hide();
            applyCustomFilter();
        }
        applyCustomFilter();

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
                $("#filterID, #Employeename, #Employeeid, #Phoneno, #Project, #location, #CompanyLaptop, #LaptopBrand, #Laptopid, #Laptopcharger, #Companymouse,#Simcard, #Datesignature").val("");
                applyFilters();
                $("#closeFilter").click();
            });
        });
</script>
<script>
  $("#downloadCsvBtn").click(function() {
    var csvData = [];

    // Loop through table rows to collect data
    $("#compamyassets tr.custom-filtered-row").each(function() {
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
            if (rowText !== "*,*,*,*,*,*,*,*,*,*,*,*,*,*") {
                csvData.push(rowData);
            }
        }
    });

    downloadCsv(csvData, "filtered_data.csv");
});
</script>
<?php include('htmlclose.php'); ?>
<?php $conn->close();?>