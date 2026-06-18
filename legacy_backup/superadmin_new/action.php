<?php

  require_once 'db.php';
  require_once 'util.php';

  $db = new Database;
  $util = new Util;
  
//   this line of code is use for debugging the script to look the error 
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
//   this line of code is use for debugging the script to look the error 

  // Handle Add New User Ajax Request
  if (isset($_POST['add'])) {
    $bdate = $util->testInput($_POST['bdate']);
    $bmonth = $util->testInput($_POST['bmonth']);
    $developer = $util->testInput($_POST['developer']);
    $bproject = $util->testInput($_POST['bproject']);
    $cname = $util->testInput($_POST['cname']);
    $cnumber = $util->testInput($_POST['cnumber']);
    $cemail = $util->testInput($_POST['cemail']);
    $tproject = $util->testInput($_POST['tproject']);
    $unitno = $util->testInput($_POST['unitno']);
    $psize = $util->testInput($_POST['psize']);
    $cagreement = $util->testInput($_POST['cagreement']);
    $ccashback = $util->testInput($_POST['ccashback']);
    $crevenue = $util->testInput($_POST['crevenue']);
    $cccashback = $util->testInput($_POST['cccashback']);
    $ccrevenue = $util->testInput($_POST['ccrevenue']);
    $cstatus = $util->testInput($_POST['cstatus']);
    $brecived = $util->testInput($_POST['brecived']);
    $leadsource = $util->testInput($_POST['leadsource']);
    $bremarks = $util->testInput($_POST['bremarks']);
    
    $filePathStored = null;
    if (isset($_FILES['document']) && $_FILES['document']['error'] == 0) {
        $fileTmpPath = $_FILES['document']['tmp_name'];
        $fileName = time() . '_' . basename($_FILES['document']['name']);
        $uploadDir = 'uploads_form/'; 
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $filePath = $uploadDir . $fileName;

        if (move_uploaded_file($fileTmpPath, $filePath)) {
            $filePathStored = $filePath;
        } else {
            echo $util->showMessage('danger', 'File upload failed!');
            exit;
        }
    } 

    if ($db->insert($bdate, $bmonth, $developer, $bproject, $cname, 
    $cnumber, $cemail, $tproject, $unitno, $psize, $cagreement, $ccashback, 
    $crevenue, $cccashback, $ccrevenue, $cstatus, $brecived, $leadsource, $bremarks, $filePathStored)) {
      echo $util->showMessage('success', 'User inserted successfully!');
    } else {
      echo $util->showMessage('danger', 'Something went wrong!');
    }
  }

  // Handle Fetch All Users Ajax Request
  if (isset($_GET['read'])) {
    $users = $db->read();
    // Call the insertMonthlySalaryTotal function
    $response = $db->insertMonthlySalaryTotal();
    // Return the response
    // echo json_encode(['message' => $response]);
    if ($users) {
      // this script making group financial according to financial year which start from april
      $groupedRows = []; // Array to store grouped rows by year
      foreach ($users as $row) {
          $monthYear = $row['booking_month'];
          $year = date('Y', strtotime($monthYear)); // Extract the year from the booking_month
          $month = date('n', strtotime($monthYear)); // Extract the month (1-12) from the booking_month

          // Adjust the year if the month is before April
          if ($month < 4) {
              $year--;
          }

          $groupKey = $year . '-' . ($year + 1); // Generate the group key (e.g., 2022-2023)
          if (!isset($groupedRows[$groupKey])) {
              $groupedRows[$groupKey] = [];
          }
          $groupedRows[$groupKey][] = $row;
      }
      
      $output = '';
      foreach ($groupedRows as $month => $rows) {
      // this function is getting the recived amount from every user 
      $totalPaid = $db->totalGivenAmt($month);
      $totalPaidmanager = $db->totalGivenAmtManager($month);
      $totalPaidsalary = $db->totalGivenAmtSalary($month);
      $rowcount = count($rows);
      $totalRevenue = 0;
      $actualRevenue = 0;
      $recivedRevenue = 0;
      $invoice_raised = 0;
      $total_raised = 0;
      $status_counter = $db->status_counter($month);
      $year = $month;
      $received_count = $status_counter['received_count'];
      $canceled_count = $status_counter['canceled_count'];
      $processing_count  = $status_counter['processing_count'];
      foreach ($rows as $row){
        $totalRevenue += $row['revenue'];
        $actualRevenue += $row['crevenue'];
        $recivedRevenue += $row['recived_amt'];
        $invoice_raised += $row['invoice_raise'];
        $total_raised += $row['update_in_invoice_table']; 
      }
      // Here we are storeing the total amount to be paid for the sales team from blow function
      $totalAmtPay = $db->calculate_total_getamount($month);
      //This function is for to get the toatl of expenses
      $totalExpensesAmt = $db->totalExpensesForFinancialYear($month);
      // Calculate profit and expenses
      // $totalExpenses = $totalPaidsalary + $totalExpensesAmt + $totalAmtPay + $totalPaidmanager;
      // $profit = $actualRevenue - $totalExpenses;
      
      // // Store profit and loss data
      // $profitLossData[] = [
      //     'month' => $month,
      //     'profit' => $profit,
      //     'expenses' => $totalExpenses
      // ];

      $output .= '<tr class="group-header view">
                    <td class="financialyear">('.$month.')/('.$rowcount.')/('.$total_raised.')</td>			  
                    <td>₹ '.$totalRevenue.'</td>
                    <td>₹ '.$actualRevenue.'</td>
                    <td>₹ '.$actualRevenue-$invoice_raised.'</td>
                    <td>₹ '.$recivedRevenue.'</td>
                    <td>₹ '.$totalPaidsalary.'</td>
                    <td>₹ '.$totalExpensesAmt.'</td>
                    <td>₹ '.$totalAmtPay + $totalPaidmanager.'</td>
                    <td>₹ '.$totalPaid.'</td>
                  </tr>
                  <tr class="fold">
                    <td colspan="20">
                      <div class="fold-content">
                        <div class="newsec">
                          <h3>Financial Year - '.$year.'</h3>
                          <div class="totalbook">
                            <div class="totalbookchild">
                              <h6>Total Processing :- '.$processing_count.'</h6>
                            </div>
                            <div class="totalbookchild">
                              <h6>Total Cancelled :- '.$canceled_count.'</h6>
                            </div>
                            <div class="totalbookchild">
                              <h6>Total Received :- '.$received_count.'</h6>
                            </div>
                          </div>  
                        </div>
                        <div class="table-wrap">
                        <div class="table-container">
                        <table class="small-friendly" cellspacing="0" style="width: 100%">
                          <thead>
                            <tr class="filters">
                              <th>ID</th>
                              <th>Booking Date</th>
                              <th>Month</th>
                              <th>Builder</th>
                              <th>Project</th>
                              <th>Customer Name.</th>
                              <th>Contact No.</th>
                              <th>Email Id</th>
                              <th>Type</th>
                              <th>Unit No.</th>
                              <th>Size</th>
                              <th>Agreement Value</th>
                              <th>Commission %</th>
                              <th>Total Revenue</th>
                              <th>CashBack %</th>
                              <th>Actual Revenue</th>
                              <th>Status</th>
                              <th>Received Amt.</th>
                              <th>Sales Person</th>
                              <th>Action</th>
                              <th>Lead Source</th>
                            </tr>
                          </thead>
                          <tbody id="filterdata">';
        foreach ($rows as $row) {
            $row_id = $row['id'];
            $checkbox_value = $db->getCheckboxValue($row_id);
            $checkbox_cashBack = $db->getCheckboxCashBack($row_id);
            $output .= '<tr class="data-row" data-status="' . $row['astatus'] . '">
                        <td>' . $row['id'] . '</td>
                        <td>' . $row['booking_date'] . '</td>
                        <td>' . $row['booking_month'] . '</td>
                        <td>' . $row['builder'] . '</td>
                        <td>' . $row['project'] . '</td>
                        <td>' . $row['customer_name'] . '</td>
                        <td>' . $row['contact_number'] . '</td>
                        <td>' . $row['email_id'] . '</td>
                        <td>' . $row['project_type'] . '</td>
                        <td>' . $row['unit_no'] . '</td>
                        <td>' . $row['size'] . '</td>
                        <td>' . $row['agreement_value'] . '</td>
                        <td>' . $row['cashback'] . '%</td>
                        <td>' . $row['revenue'] . '</td>';

                    // Check the value of $checkbox_cashBack and display the appropriate icon
                    if ($checkbox_cashBack == '1') {
                        // If the value is '1' (true), display a green tick icon
                        $output .= '<td><div class="alignment">' . $row['ccashback'] . '%<div class="verified" style="color: white !important; background-color: green !important; border-radius: 50%; width:15px;height:15px;position:relative;margin-left: 70%;margin-top: -23%;"><i class="bi bi-check-lg" style="font-size:8px;position: absolute; left: 50%;top: 50%;transform: translate(-50%, -50%);"></i></div></div></td>';
                    } else {
                        // If the value is not '1' (false), display a red cross icon
                        $output .= '<td><div class="alignment">' . $row['ccashback'] . '%<div class="not-verified" style="color: white !important; background-color: red !important; border-radius: 50%; width:15px;height:15px;position:relative;margin-left: 70%;margin-top: -23%;"><i class="bi bi-x-lg" style="font-size:8px;position: absolute; left: 50%;top: 50%;transform: translate(-50%, -50%);"></i></div></div></td>';
                    }

                    $output .= '<td>' . $row['crevenue'] . '</td>
                        <td><div class="status ' . $row['astatus'] . '">' . $row['astatus'] . '</div></td>
                        <td>' . $row['recived_amt'] . '</td>';

                    // Check the value of $checkbox_value and display the appropriate icon
                    if ($checkbox_value == '1') {
                        // If the value is '1' (true), display a green tick icon
                        $output .= '<td><div class="alignment">' . $row['source_table'] . '<div class="verified" style="color: white !important; background-color: green !important; border-radius: 50%; width:25px;height:25px;position:relative;margin-left: 32%;"><i class="bi bi-check-lg" style="position: absolute; left: 50%;top: 50%;transform: translate(-50%, -50%);"></i></div></div></td>';
                    } else {
                        // If the value is not '1' (false), display a red cross icon
                        $output .= '<td><div class="alignment">' . $row['source_table'] . '<div class="not-verified" style="color: white !important; background-color: red !important; border-radius: 50%; width:25px;height:25px;position:relative;margin-left: 32%;"><i class="bi bi-x-lg" style="position: absolute; left: 50%;top: 50%;transform: translate(-50%, -50%);"></i></div></div></td>';
                    }

                    $output .= '<td>
                            <a href="#" id="' . $row['id'] . '" class="btn btn-success btn-sm rounded-pill py-0 editLink" data-toggle="modal" data-target="#editUserModal">Edit</a>';
                    $filePath = $row['document_path'];

                      if (!empty($filePath) && file_exists($filePath)) {
                          $output .= '
                          <a href="' . $filePath . '" target="_blank" class="btn" download>
                              <button class="btn-download"><i class="fa fa-download"></i></button>
                          </a>';
                      } else {
                          $output .= '
                          <button class="btn-download" style="background-color:#ccc; cursor:not-allowed;">
                              <i class="fa fa-download" style="font-size: 18px;"></i>
                          </button>';
                      }

                    if ($adminCode === 'Search6215Homes#$code!&%45') {
                        $output .= '<a href="#" id="' . $row['id'] . '" class="btn btn-danger btn-sm rounded-pill py-0 deleteLink">Delete</a>';
                    }

                    $output .= '</td>
                    <td>' . $row['source_lead'] . '</td>
                    </tr>';
        }
        $output .= '</table>
                        </div>
                      </div>
                    </div>
                  </td>
                </tr>';
      }

    echo $output;
    } else {
        echo '<tr>
                <td colspan="19" style="text-align: center;background-color: lightcyan;font-weight: 700;">No Users Found in the Database!</td>
              </tr>';
    }
}

if (isset($_GET['read_chart'])) {
  $users = $db->read();
  if ($users) {
      $grouped_data = [];
      $bar_chart_data = [];
      $profit_loss_data = [];

      foreach ($users as $row) {
          $monthYear = $row['booking_month'];
          list($year, $month) = explode('-', $monthYear);
          $month = intval($month);
          $year = intval($year);

          if ($month < 4) {
              $startYear = $year - 1;
              $endYear = $year;
          } else {
              $startYear = $year;
              $endYear = $year + 1;
          }

          $groupKey = $startYear . '-' . $endYear;

          if (!isset($grouped_data[$groupKey])) {
              $status_counter = $db->status_counter($groupKey);
              $grouped_data[$groupKey] = [
                  'year' => $groupKey,
                  'received_count' => $status_counter['received_count'],
                  'canceled_count' => $status_counter['canceled_count'],
                  'processing_count' => $status_counter['processing_count']
              ];
          }

          $actualRevenue = $row['crevenue'];
          $recivedRevenue = $row['recived_amt'];
          $invoice_raised = $row['invoice_raise'];

          if (!isset($bar_chart_data[$groupKey])) {
              $bar_chart_data[$groupKey] = [
                  'year' => $groupKey,
                  'actual_revenue' => $actualRevenue,
                  'invoice_include' => $actualRevenue - $invoice_raised,
                  'received_revenue' => $recivedRevenue
              ];
          } else {
              $bar_chart_data[$groupKey]['actual_revenue'] += $actualRevenue;
              $bar_chart_data[$groupKey]['invoice_include'] += $actualRevenue - $invoice_raised;
              $bar_chart_data[$groupKey]['received_revenue'] += $recivedRevenue;
          }
      }

      foreach (array_keys($grouped_data) as $groupKey) {
          $actualRevenue = $db->actual_revenue_monthly($groupKey);
          $totalPaidManager = $db->totalGivenAmtManager_monthly($groupKey);
          $totalPaidSalary = $db->totalGivenAmtSalary_monthly($groupKey);
          $totalAmtPay = $db->calculate_total_getamount_monthly($groupKey);
          $totalExpensesAmt = $db->totalExpensesForFinancialYear_monthly($groupKey);

          $totalExpenses = array_map(function(...$expenses) {
              return array_sum($expenses);
          }, $totalPaidSalary, $totalExpensesAmt, $totalAmtPay, $totalPaidManager);

          $profit = array_map(function($revenue, $expense) {
              return $revenue - $expense;
          }, $actualRevenue, $totalExpenses);

          $months = ['April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December', 'January', 'February', 'March'];

          $profit_loss_data[$groupKey] = [
              'year' => $groupKey,
              'months' => $months,
              'actual_revenue' => $actualRevenue,
              'expenses' => $totalExpenses,
              'profit' => $profit
          ];
      }

      $pie_chart_data = array_values($grouped_data);

      $response = [
          'pie_chart_data' => $pie_chart_data,
          'bar_chart_data' => array_values($bar_chart_data),
          'profit_loss_data' => array_values($profit_loss_data)
      ];

      header('Content-Type: application/json');
      echo json_encode($response);
      exit;
  }
}

  // Handle Edit User Ajax Request
  if (isset($_GET['edit'])) {
    $id = $_GET['id'];

    $user = $db->readOne($id);
    echo json_encode($user);
  }

  // Handle Update User Ajax Request
  if (isset($_POST['update'])) {
    $id = $util->testInput($_POST['id']);
    $bdate = $util->testInput($_POST['bdate']);
    $bmonth = $util->testInput($_POST['bmonth']);
    $developer = $util->testInput($_POST['developer']);
    $bproject = $util->testInput($_POST['bproject']);
    $cname = $util->testInput($_POST['cname']);
    $cnumber = $util->testInput($_POST['cnumber']);
    $cemail = $util->testInput($_POST['cemail']);
    $tproject = $util->testInput($_POST['tproject']);
    $unitno = $util->testInput($_POST['unitno']);
    $psize = $util->testInput($_POST['psize']);
    $cagreement = $util->testInput($_POST['cagreement']);
    $ccashback = $util->testInput($_POST['ccashback']);
    $crevenue = $util->testInput($_POST['crevenue']);
    $cccashback = $util->testInput($_POST['cccashback']);
    $ccrevenue = $util->testInput($_POST['ccrevenue']);
    $cstatus = $util->testInput($_POST['cstatus']);
    $brecived = $util->testInput($_POST['brecived']);
    $invoice = $util->testInput($_POST['invoice_raised']);
    $tablename = $util->testInput($_POST['source_table']);
    // Determine the value for update_in_user_table column
    $updateInUserTable = isset($_POST['update_user_checkbox']) && $_POST['update_user_checkbox'] === 'on' ? 1 : 0;
    $updateInvoice = isset($_POST['update_invoice_checkbox']) && $_POST['update_invoice_checkbox'] === 'on' ? 1 : 0;
    $cashbackverify = isset($_POST['cashbackverify']) && $_POST['cashbackverify'] === 'on' ? 1 : 0;

    if ($db->update(
        $id, 
        $bdate, 
        $bmonth, 
        $developer, 
        $bproject, 
        $cname, 
        $cnumber, 
        $cemail, 
        $tproject, 
        $unitno, 
        $psize, 
        $cagreement, 
        $ccashback, 
        $crevenue, 
        $cccashback, 
        $ccrevenue, 
        $cstatus, 
        $brecived,
        $invoice,
        $updateInUserTable,
        $updateInvoice,
        $tablename,
        $cashbackverify
    )) {
        echo $util->showMessage('success', 'Booking updated successfully!');
    } else {
        echo $util->showMessage('danger', 'Something went wrong!');
    }
}
  
  // Handle Delete User Ajax Request
  if (isset($_GET['delete'])) {
    $id = $_GET['id'];
    if ($db->delete($id)) {
      echo $util->showMessage('info', 'Booking deleted successfully!');
    } else {
      echo $util->showMessage('danger', 'Something went wrong!');
    }
  }
  
   if (isset($_POST['action']) && $_POST['action'] == 'update_advance_pay') {
    $id = $_POST['id'];
    $newAdvancePay = $_POST['newAdvancePay'];

    // Call the updateAdvancePay function to update the "advance_pay" column
    $db->updateAdvancePay($id, $newAdvancePay);

    // Respond with a success message or any relevant information
    echo "Advance Pay updated successfully.";
}

?>