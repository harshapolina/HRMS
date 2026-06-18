<?php
// ─── APPROVAL_MANAGER: the only promoter-side user who can see/use the Approvals popup.
// ─── Change this ONE value here to switch the approval user in future.
define('APPROVAL_MANAGER', 'rahul00761');

ob_start(); // Buffer output so PHP notices/warnings don't corrupt JSON responses

  require_once 'db.php';
  require_once 'util.php';

  $db = new Database;
  $util = new Util;
  //this code is for see the actual error "uncomment when needed"
  error_reporting(E_ALL);
  ini_set('display_errors', 0); // Keep OFF for AJAX — errors go to server log only
  // Handle Add New User Ajax Request
  if (isset($_POST['add'])) {
    $bdate = $util->testInput($_POST['bdate'] ?? '');
    $bmonth = $util->testInput($_POST['bmonth'] ?? '');
    $developer = $util->testInput($_POST['developer'] ?? '');
    $bproject = $util->testInput($_POST['bproject'] ?? '');
    $cname = $util->testInput($_POST['cname'] ?? '');
    $cnumber = $util->testInput($_POST['cnumber'] ?? '');
    $cemail = $util->testInput($_POST['cemail'] ?? '');
    $tproject = $util->testInput($_POST['tproject'] ?? '');
    $unitno = $util->testInput($_POST['unitno'] ?? '');
    $psize = $util->testInput($_POST['psize'] ?? '');
    $cagreement = $util->testInput($_POST['cagreement'] ?? '');
    $ccashback = $util->testInput($_POST['ccashback'] ?? '');
    $crevenue = $util->testInput($_POST['crevenue'] ?? '');
    $cccashback = $util->testInput($_POST['cccashback'] ?? '');
    $ccrevenue = $util->testInput($_POST['ccrevenue'] ?? '');
    $cstatus = $util->testInput($_POST['cstatus'] ?? '');
    $brecived = $util->testInput($_POST['brecived'] ?? '');
    $msalary = $util->testInput($_SESSION['salary'] ?? '');
    $leadsource = $util->testInput($_POST['leadsource'] ?? '');
    $bremarks = $util->testInput($_POST['bremarks'] ?? '');
    $deduct_agreement_value = $util->testInput($_POST['deduct_agreement_value'] ?? '');
    $city = $util->testInput($_POST['city'] ?? '');
    // Handle file upload
    $filePathStored = null;
    if (isset($_FILES['document']) && $_FILES['document']['error'] == 0) {
        $fileTmpPath = $_FILES['document']['tmp_name'];
        $fileName = time() . '_' . basename($_FILES['document']['name']);
        $uploadDir = '../superadmin/uploads_form/';
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

    try {
        $result = $db->insert($bdate, $bmonth, $developer, $bproject, 
        $cname, $cnumber, $cemail, $tproject, $unitno, $psize, $cagreement, 
        $ccashback, $crevenue, $cccashback, $ccrevenue, $cstatus, $brecived, 
        $msalary, $filePathStored, $leadsource, $bremarks, $deduct_agreement_value, $city);

        if ($result === 'pending_approval') {
            echo $util->showMessage('warning', '&#9200; Booking submitted for approval! It will appear in your bookings list once approved by your manager or admin.');
        } elseif ($result === 'duplicate') {
            echo $util->showMessage('danger', 'Duplicate Booking found. This unit no. already exists in admintable.');
        } elseif ($result === 'duplicate_pending') {
            echo $util->showMessage('danger', 'A booking with this unit no. is already pending approval.');
        } else {
            echo $util->showMessage('danger', 'Something went wrong!');
        }
    } catch (Exception $e) {
        error_log('Insert Booking Error: ' . $e->getMessage());
        echo $util->showMessage('danger', 'Error: ' . $e->getMessage());
    }
  
  }
  // Handle Fetch All Users Ajax Request
  if (isset($_GET['read'])) {
    $users = $db->read();
    $output = '';
    $overall_earn = 0;    
    $overall_paid = 0;
    $overall_booking = 0;
    $overall_amt = 0;
    $advancepay = 0;
    $outputY = '';
    if ($users) {
    // Group rows by month
    $groupedRows = []; // Array to store grouped rows by month
    foreach ($users as $row) {
        $revenues[] = $row['crevenue'];
        $month = $row['booking_month'];
        if (!isset($groupedRows[$month])) {
            $groupedRows[$month] = [];
        }
        $groupedRows[$month][] = $row;
    }
    // Generate table rows for each group
    $output = '';
    foreach ($groupedRows as $month => $rows) {
        // this calculation is for mandate project
        if ($Project_type === 'mandate' && $user_type === 'user'){
        $arr_forData = [];
        $storeVal = [];
        $store = 0;
        $afteramt = 0;
        $Rev = 0;
        $calculationPaid = array();
        // this calculation is for the mandate projects
        $filteredRows = array_filter($rows, function($row) {
          return $row['astatus'] !== 'Canceled';
        });
        $rowCount = count($filteredRows);
        /////////////////////////////////////////////////////CALCULATION MANDATE PROJECT 
        foreach ($rows as $row) {
          if ($row['ccashback'] !=0){
            $storeVal[] = $row['ccashback'];
            // $store = $row['ccashback'];
          }
          if ($row['astatus'] == 'Received'){
            $arr_forData[] = $row['astatus'];
          }
        }
        foreach ($rows as $row) {
            $Rev += $row['crevenue'];
        }
        // here we are geeting all the recent update value on the bases of ccashback
        $usersr = $db->recentCashback($month);
        if (is_array($usersr) && isset($usersr["ccashback"])) {
          $store = $usersr["ccashback"];
          $id = $usersr['id']; // this is for totaling the given amt
          //  echo $store;
        }
      
       //Actual calculation will be start from here when all data will come into the variables
        if ($rowCount === 1){
          $cashbackCounter = count($storeVal);
          if ($cashbackCounter != 0){
            $afteramt = ($frist * 0.30)*$cashbackCounter;
          }
          $Final_Amount = ($frist*($rowCount-$cashbackCounter)) + $afteramt;
          // here we are storing the value in to be pay amount for the team
          $bookingMonth = $month;
          $sourceTableName = $tablename;
          $db->insert_pay_amount($Final_Amount, $sourceTableName, $bookingMonth);
          $timesT = count($arr_forData);
          if ($timesT != 0){
            if ($store != 0){
              $co2 = ($frist * 0.30);
            }
            else{
              $co2 = $frist;
            }
            $updatamount = $db->updateAmt($co2, $id); //this function and inserting the value through "updateAmt"
            $paid_amount = $db->getTotalSendAmt($month); //we are geeting the value through "getTotalSendAmt" 
            $Final_Amount = $Final_Amount - $paid_amount;
          }
          else{
            $paid_amount = 0;
          }
        }
        elseif ($rowCount === 2){
          $cashbackCounter = count($storeVal);
          if ($cashbackCounter != 0){
            $afteramt = ($secound * 0.30)*$cashbackCounter;
          }
          $Final_Amount = ($secound*($rowCount-$cashbackCounter)) + $afteramt;
          // here we are storing the value in to be pay amount for the team
          $bookingMonth = $month;
          $sourceTableName = $tablename;
          $db->insert_pay_amount($Final_Amount, $sourceTableName, $bookingMonth);
          $timesT = count($arr_forData);
          if ($timesT != 0){
            if ($store != 0){
              $co2 = ($secound * 0.30);
            }
            else{
              $co2 = $secound;
            }
            $updatamount = $db->updateAmt($co2, $id); //this function and inserting the value through "updateAmt"
            $paid_amount = $db->getTotalSendAmt($month); //we are geeting the value through "getTotalSendAmt" 
            $Final_Amount = $Final_Amount - $paid_amount;
          }
          else{
            $paid_amount = 0;
          }
        }
        elseif ($rowCount === 3){
          $cashbackCounter = count($storeVal);
          if ($cashbackCounter != 0){
            $afteramt = ($third * 0.30)*$cashbackCounter;
          }
          $Final_Amount = ($third*($rowCount-$cashbackCounter)) + $afteramt;
          // here we are storing the value in to be pay amount for the team
          $bookingMonth = $month;
          $sourceTableName = $tablename;
          $db->insert_pay_amount($Final_Amount, $sourceTableName, $bookingMonth);
          $timesT = count($arr_forData);
          if ($timesT != 0){
            if ($store != 0){
              $co2 = ($third * 0.30);
            }
            else{
              $co2 = $third;
            }
            $updatamount = $db->updateAmt($co2, $id); //this function and inserting the value through "updateAmt"
            $paid_amount = $db->getTotalSendAmt($month); //we are geeting the value through "getTotalSendAmt" 
            $Final_Amount = $Final_Amount - $paid_amount;
          }
          else{
            $paid_amount = 0;
          }
        }
        elseif ($rowCount === 4){
          $cashbackCounter = count($storeVal);
          if ($cashbackCounter != 0){
            $afteramt = ($forth * 0.30)*$cashbackCounter;
          }
          $Final_Amount = ($forth*($rowCount-$cashbackCounter)) + $afteramt;
          // here we are storing the value in to be pay amount for the team
          $bookingMonth = $month;
          $sourceTableName = $tablename;
          $db->insert_pay_amount($Final_Amount, $sourceTableName, $bookingMonth);
          $timesT = count($arr_forData);
          if ($timesT != 0){
            if ($store != 0){
              $co2 = ($forth * 0.30);
            }
            else{
              $co2 = $forth;
            }
            $updatamount = $db->updateAmt($co2, $id); //this function and inserting the value through "updateAmt"
            $paid_amount = $db->getTotalSendAmt($month); //we are geeting the value through "getTotalSendAmt" 
            $Final_Amount = $Final_Amount - $paid_amount;
          }
          else{
            $paid_amount = 0;
          }
        }
        elseif ($rowCount === 5){
          $cashbackCounter = count($storeVal);
          if ($cashbackCounter != 0){
            $afteramt = ($fifth * 0.30)*$cashbackCounter;
          }
          $Final_Amount = ($fifth*($rowCount-$cashbackCounter)) + $afteramt;
          // here we are storing the value in to be pay amount for the team
          $bookingMonth = $month;
          $sourceTableName = $tablename;
          $db->insert_pay_amount($Final_Amount, $sourceTableName, $bookingMonth);
          $timesT = count($arr_forData);
          if ($timesT != 0){
            if ($store != 0){
              $co2 = ($fifth * 0.30);
            }
            else{
              $co2 = $fifth;
            }
            $updatamount = $db->updateAmt($co2, $id); //this function and inserting the value through "updateAmt"
            $paid_amount = $db->getTotalSendAmt($month); //we are geeting the value through "getTotalSendAmt" 
            $Final_Amount = $Final_Amount - $paid_amount; 
          }
          else{
            $paid_amount = 0;
          }
        }
        elseif ($rowCount >= 6){
          $cashbackCounter = count($storeVal);
          if ($cashbackCounter != 0){
            $afteramt = ($sixth * 0.30)*$cashbackCounter;
          }
          $Final_Amount = ($sixth*($rowCount-$cashbackCounter)) + $afteramt;
          // here we are storing the value in to be pay amount for the team
          $bookingMonth = $month;
          $sourceTableName = $tablename;
          $db->insert_pay_amount($Final_Amount, $sourceTableName, $bookingMonth);
          $timesT = count($arr_forData);
          if ($timesT != 0){
            if ($store != 0){
              $co2 = ($sixth * 0.30);
            }
            else{
              $co2 = $sixth;
            }
            // In blow all two function 
            $updatamount = $db->updateAmt($co2, $id); //this function and inserting the value through "updateAmt"
            $paid_amount = $db->getTotalSendAmt($month); //we are geeting the value through "getTotalSendAmt" 
            $Final_Amount = $Final_Amount - $paid_amount;
          }
          else{
            $paid_amount = 0;
          }
        }
        elseif ($rowCount === 0){
          $cashbackCounter = count($storeVal);
          if ($cashbackCounter != 0){
            $afteramt = ($sixth * 0.30)*$cashbackCounter;
          }
          $Final_Amount = ($sixth*($rowCount-$cashbackCounter)) + $afteramt;
          // here we are storing the value in to be pay amount for the team
          $bookingMonth = $month;
          $sourceTableName = $tablename;
          $db->insert_pay_amount($Final_Amount, $sourceTableName, $bookingMonth);
          $timesT = count($arr_forData);
          if ($timesT != 0){
            if ($store != 0){
              $co2 = ($sixth * 0.30);
            }
            else{
              $co2 = $sixth;
            }
            // In blow all two function 
            $updatamount = $db->updateAmt($co2, $id); //this function and inserting the value through "updateAmt"
            $paid_amount = $db->getTotalSendAmt($month); //we are geeting the value through "getTotalSendAmt" 
            $Final_Amount = $Final_Amount - $paid_amount;
          }
          else{
            $paid_amount = 0;
          }
        }
        $overall_earn += $Final_Amount;
        $overall_booking += $rowCount;
        $overall_paid += $paid_amount;
        //This function is for storing the data into the tracking table 
        $updatamount = $db->insertOrUpdateTrackingData($month, $Rev, $paid_amount, $Final_Amount, $tablename, $rowCount, $paid_amount, $user_type);
        //This fucntion is for storing the data into the tracking End
        $output .= '<tr class="group-header view">
                          <td class="financialyear">'.$month.'/('.$rowCount.')</td>			  
                          <td>₹ '.$Final_Amount.'</td>
                          <td>₹ '.$paid_amount.'</td>
                        </td>
                    </tr>';
      }
      /////////////////////////////////////////////////////CALCULATION MANDATE PROJECT END
      /////////////////////////////////////////////////////CALCULATION RETAIL PROJECT
      elseif ( $Project_type === 'retail' && $user_type === 'user') {
          // this function is for get recent salary update
          $D1 = $db->currentSalary($month);
          $recentUpdate = 0;
          $id = 0;
          $D2 = 0; // Reset total revenue for each month
          $Paid_Insentive = 0;
          $agreement_value = 0;

        // Calculate total revenue for the month
        foreach ($rows as $row) {
            $D2 += $row['crevenue'];
            $Paid_Insentive += $row['send_amt'];
            $agreement_value += $row['agreement_value'];
        }
      // Count the rows for the current month
      $rowCount = count($rows);
        
      $B3 = $D1*5;
      $B4 = $D1*10;
      $B5 = $D1*15;
      $B6 = $D1*20;
      $B7 = $D1*25;
      // Get the value as slab of the company
      // Calculate "C"
      $C3 = $D2 > $B3 ? $B3 : $D2;
      $C4 = $D2 > $B4 ? $B4 - $B3 : $D2 - $B3;
      $C5 = $D2 > $B5 ? $B5 - $B4 : ($D2 > $B4 ? $D2 - $B4 : 0);
      $C6 = $D2 > $B6 ? $B6 - $B5 : ($D2 > $B5 ? $D2 - $B5 : 0);
      $C7 = $D2 > $B7 ? $B7 - $B6 : ($D2 > $B6 ? $D2 - $B6 : 0);
      $C8 = $D2 > $B7 ? $D2 - $B3 : 0;
      //Get the Value of "D" for all the insentive calculation after slab
      // Calculate D3
      $D3 = (int)((4 / 100) * $C3);
      $D4 = (int)((8 / 100) * $C4);
      $D5 = (int)((12 / 100) * $C5);
      $D6 = (int)((16 / 100) * $C6);
      $D7 = (int)((20 / 100) * $C7);
      $sum_d3_d7 = array_sum([$D3, $D4, $D5, $D6, $D7]);
      $D8 = (int)((22 / 100) * $C8);
      $D10 = ($C8 == 0) ? $sum_d3_d7 : 0;
      $D11 = ($C8 > 0) ? ($D8 + $D3) : 0;
      //Now check which slap the person exist and cont the amount to be send
      $temp = ($D1 <= 40000 && $D2 <= $D1 * 25) || ($D1 > 40000 && $D2 <= $D1 * 25) ? $D10 : $D11;
      //here we are inserting the value of company will pay amount into the admintable
      $bookingMonth = $month;
      $sourceTableName = $tablename;
      $db->insert_pay_amount($temp, $sourceTableName, $bookingMonth);
       // this logic for geeting recent updated id value form receved amount
       $usersr = $db->recent($month);
       if (is_array($usersr) && isset($usersr["recived_amt"])) {
         $recentUpdate = $usersr["recived_amt"];
         $id = $usersr['id']; // this is for totaling the given amt
      }
      if ($D2 === 0){
        $co1 = 	0;
      }
      else{
        $co1 = 	$recentUpdate / $D2;
      }
      $co2 = (int)($temp * $co1);
      //store the value of co2 in database column send_amt
      $updatedRows = $db->updateAmt($co2, $id);
      //get total of all paid amount 
      $total = $db->getTotalSendAmt($month);
      //Now we are calcultaing the recived amt and remaning amt
      $co3 = $temp - $total;
      if ($co3 < 0) {
        $co3 = 0; // Set $co3 to 0 if it's negative
      }
      // Overall calculation Report
      $overall_earn += $co3;
      $overall_booking += $rowCount;
      $overall_paid += $Paid_Insentive;
      // Overall calculation Report
      //This function is for storing the data into the tracking table 
      $updatamount = $db->insertOrUpdateTrackingData($month, $D2, $co2, $co3, $tablename, $rowCount, $Paid_Insentive, $user_type);
      //This fucntion is for storing the data into the tracking End
      $cutoffMonth = "2025-04"; // April 2025

        if ($month >= $cutoffMonth) {
            // From April 2025 onwards
            $D2 = $agreement_value;
            $co3 = 0;
            $co2 = 0;
            $Paid_Insentive = 0;
        }
        
        $output .= '<tr class="group-header view">
                        <td class="financialyear">'.$month.'/('.$rowCount.')</td>
                        <td>₹ '.$D2.'</td>
                        <td>₹ '.$co3.'</td>
                        <td>₹ '.$co2.'</td>
                        <td>₹ '.$Paid_Insentive.'</td>
                   </tr>';
      }
      /////////////////////////////////////////////////////CALCULATION RETAIL PROJECT END
      /////////////////////////////////////////////////////CALCULATION MANAGER PROJECT START
      elseif (($Project_type === 'mandate' || $Project_type === 'retail') && $user_type === 'manager') {
        // Logic for mandate or retail project and user type manager
        $rowCount = count($rows);
        $D2 = 0; // Reset total revenue for each month
        $recent_pay = 0;
        $remaning_amt = 0;
        foreach ($rows as $row) {
            $D2 += $row['crevenue']; 
        }
        $overall_booking += $rowCount;
        $paid_salary = $db->getTotalAccountSalary($month);
        $expenses_amount = $db->getTotalExpenses($month);
        $incentive_amount = $db->getTotalIncentive($month);
        $get_amount = $paid_salary + $expenses_amount + $salary + $incentive_amount;
        $incentive_value = $get_amount + ($get_amount*30/100);
        // echo "PaidSalary:.$paid_salary.,Expense:.$expenses_amount.,MSalary:.$salary.,Incentive:.$incentive_amount.,IncentiveValue:.$incentive_value.";
        $PLI = $D2 - $incentive_value;
        $actual_incentive = $PLI*30/100;
        $manager_incentive_insert = $db->insertOrUpdateTrackingData($month, $D2, $recent_pay, $remaning_amt, $tablename, $rowCount, $actual_incentive, $user_type);
        $output .= '<tr class="group-header view">
                          <td class="financialyear">'.$month.'/('.$rowCount.')</td>
                          <td>₹ '.$D2.'</td>
                          <td>₹ '.$incentive_value.'</td>
                          <td>₹ '.$actual_incentive.'</td>
                    </tr>';
      }
      elseif (($Project_type === 'mandate' || $Project_type === 'retail') && $user_type === 'ceo') {
            // Logic for CEO (filtered rows already handled in read())
            $rowCount = count($rows);
            $D2 = 0;
        
            foreach ($rows as $row) {
                $D2 += $row['crevenue'];
            }
        
            $overall_booking += $rowCount;
        
            $output .= '<tr class="group-header view">
                          <td class="financialyear">' . $month . '/(' . $rowCount . ')</td>
                          <td>₹ ' . $D2 . '</td>
                          <td>₹ 0</td>
                          <td>₹ 0</td>
                      </tr>';
        }
      else {
          // Optional: Handle other cases or show an error message
          echo $Project_type, $user_type;
      }
      /////////////////////////////////////////////////////CALCULATION MANAGER PROJECT END
      $output .= '<tr class="fold">
<td colspan="20"> 
    <div class="fold-content">
    <div class="table-wrap">
        <div class="table-container">
        <table class="small-friendly" cellspacing="0" style="width: 100%">
            <thead>
            <tr>
                <th>ID</th>
                <th>Sales Person</th>
                <th>Booking Date</th>
                <th>Month</th>
                <th>Builder</th>
                <th>Project</th>
                <th>Customer Name</th>
                <th>Contact No.</th>
                <th>Email Id</th>
                <th>Type</th>
                <th>Unit No.</th>
                <th>Size</th>
                <th>Agreement Value</th>';

if ($Project_type !== "mandate") {
    $output .= '<th>Commission %</th>';
}

if ($Project_type !== "mandate") {
    $output .= '<th>Total Revenue</th>';
}

$output .= '<th>CashBack %</th>';

if ($Project_type !== "mandate") {
    $output .= '<th>Actual Revenue</th>';
}

$output .= '<th>Status</th>';

if ($Project_type !== "mandate") {
    $output .= '<th>Received Amt.</th>';
}

$output .= '</tr>
            </thead>
            <tbody id="pagedataaasx">';

                // This is Row Loop Inside Month
        foreach ($rows as $row) {
          $output .= '<tr data-status="' . $row['astatus'] . '">
          <td>' . $row['id'] . '</td>
          <td>' . $row['source_table'] . '</td>
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
          <td>' . $row['agreement_value'] . '</td>';
  
      if ($Project_type !== 'mandate') {
          $output .= '<td>' . $row['cashback'] . '%</td>';
      }
  
      if ($Project_type !== 'mandate') {
          $output .= '<td>' . $row['revenue'] . '</td>';
      }
  
      $output .= '<td>' . $row['ccashback'] . '%</td>';
  
      if ($Project_type !== 'mandate') {
          $output .= '<td>' . $row['crevenue'] . '</td>';
      }
  
      $output .= '<td><div class="' . $row['astatus'] . '"> ' . $row['astatus'] . '</div></td>';
  
      if ($Project_type !== 'mandate') {
          $output .= '<td>' . $row['recived_amt'] . '</td>';
      }
  
      if ('erkfdth' === 'Seas#$code!&%45') {
          $output .= '<td>
                          <a href="#" id="' . $row['id'] . '" class="btn btn-success btn-sm rounded-pill py-0 editLink" data-toggle="modal" data-target="#editUserModal">Edit</a>
                          <a href="#" id="' . $row['id'] . '" class="btn btn-danger btn-sm rounded-pill py-0 deleteLink">Delete</a>
                      </td>
                  </tr>';
  
          }
        }
        $output .= '</tbody>
                  </table>
                </div>
              </div>
            </div>
          </td>
          </tr>';
    }

    echo $output;
    if ( $Project_type === 'mandate' ){
      $advancepay = $db->getAdvancePayByUser($tablename);
      $overall_earn_collect = $overall_earn + $overall_paid;
      $overall_amt = $overall_earn_collect - ($overall_paid + $advancepay);
      $updatpayment = $db->insertOrUpdatePayment($overall_earn_collect, $overall_paid, $overall_amt, $tablename, $overall_booking);
    }
    elseif (($Project_type === 'mandate' || $Project_type === 'retail') && $user_type === 'manager'){
      $advancepay = $db->getAdvancePayByUser($tablename);
      $overall_earn_collect = 0;
      $overall_amt = 0;
      // $updatpayment = $db->insertOrUpdatePayment($overall_earn, $overall_paid, $overall_amt, $tablename, $overall_booking);
    }
    else{
      $advancepay = $db->getAdvancePayByUser($tablename);
      $overall_earn_collect = $overall_earn + $overall_paid;
      $overall_amt = $overall_earn_collect - ($overall_paid + $advancepay);
      $updatpayment = $db->insertOrUpdatePayment($overall_earn_collect, $overall_paid, $overall_amt, $tablename, $overall_booking);
    }
    
    $outputY .= '<tr>
    <td colspan="5">
      <div class="newsec">
        <!-- <h3>Financial year - 2023-2024</h3> -->
        <div class="totalbook">
          <div class="totalbookchild">
            <h6>Overall Bookings :- <span class="monthexp text-success">('.$overall_booking.')</span></h6>
          </div>
          <div class="divide">|</div>
          <div class="totalbookchild">
            <h6>Overall Earn :- <span class="monthexp text-success">₹ '.$overall_earn_collect.'</span></h6>
          </div>
          <div class="divide">|</div>
          <div class="totalbookchild">
            <h6>Overall Build :- <span class="monthexp text-success">₹ '.$overall_paid.'</span></h6>
          </div>
          <div class="divide">|</div>
          <div class="totalbookchild">
            <h6>Advance Paid :- <span class="monthexp text-success">₹ '.$advancepay.'</span></h6>
          </div>
          <div class="divide">|</div>
          <div class="totalbookchild">
            <h6> Final Remaning :- <span class="monthexp text-success">₹ '.$overall_amt.'</span></h6>
          </div>
        </div>
      </div>
    </td>
  </tr>';
echo $outputY;
} else {
    echo '<tr>
            <td colspan="19" style="text-align: center;background-color: lightcyan;font-weight: 700;">No Users Found in the Database!</td>
          </tr>';
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
    error_log('========== UPDATE REQUEST START ==========');
    error_log('UPDATE - Full POST data: ' . print_r($_POST, true));
    error_log('UPDATE - Session tablename: ' . ($tablename ?? 'NOT SET'));
    
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
    $recentreceved = $_POST['brecived'];
    
    error_log('UPDATE - Processed: ID=' . $id . ', Status=' . $cstatus . ', Contact=' . $cnumber . ', Email=' . $cemail);
    
    // Handle additional fields
    $invoice = isset($_POST['invoice_raised']) ? $util->testInput($_POST['invoice_raised']) : '';
    $source_table = isset($_POST['source_table']) ? $util->testInput($_POST['source_table']) : $tablename;
    $updateInUserTable = isset($_POST['update_user_checkbox']) && $_POST['update_user_checkbox'] === 'on' ? 1 : 0;
    $updateInvoice = isset($_POST['update_invoice_checkbox']) && $_POST['update_invoice_checkbox'] === 'on' ? 1 : 0;
    $cashbackverify = isset($_POST['cashbackverify']) && $_POST['cashbackverify'] === 'on' ? 1 : 0;
    
    error_log('UPDATE - Calling db->update()...');

    try {
        $result = $db->update($id, $bdate, $bmonth, $developer, $bproject, $cname, $cnumber, $cemail, $tproject, $unitno, $psize, $cagreement, $ccashback, $crevenue, $cccashback, $ccrevenue, $cstatus, $brecived, $invoice, $updateInUserTable, $updateInvoice, $source_table, $cashbackverify);
        error_log('UPDATE - db->update() returned: ' . var_export($result, true));
        if ($result) {
            error_log('========== UPDATE SUCCESS for ID: ' . $id . ' ==========');
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Booking updated successfully!',
                'id' => $id,
                'booking_month' => $bmonth,
                'customer_name' => $cname,
                'contact_number' => $cnumber,
                'unit_no' => $unitno,
                'project_type' => $tproject,
                'astatus' => $cstatus,
                'agreement_value' => $cagreement,
                'builder' => $developer,
                'project' => $bproject,
                'booking_date' => $bdate,
                'size' => $psize,
                'cashback' => $ccashback,
                'revenue' => $crevenue,
                'ccashback' => $cccashback,
                'crevenue' => $ccrevenue,
                'recived_amt' => $brecived,
                'invoice_raise' => $invoice,
            ]);
        } else {
            error_log('========== UPDATE FAILED for ID: ' . $id . ' ==========');
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Something went wrong!']);
        }
    } catch (Exception $e) {
        error_log('========== UPDATE ERROR: ' . $e->getMessage() . ' ==========');
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
  }

// ============================================================
// BOOKING APPROVAL SYSTEM — AJAX HANDLERS
// ============================================================


/**
 * GET: Fetch ALL pending bookings for the promoter approvals view.
 * Usage: action.php?get_pending_approvals=true
 */
if (isset($_GET['get_pending_approvals'])) {
    header('Content-Type: application/json');
    if (!isset($_SESSION['tablename'])) {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        exit;
    }
    try {
        // Fetch all columns so the edit form can pre-fill every field
        $conn = $db->getConnection();
        $stmt = $conn->query(
            "SELECT * FROM booking_approvals ORDER BY id DESC"
        );
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Add submitter_name as alias of source_table (no JOIN needed)
        foreach ($rows as &$r) {
            $r['submitter_name'] = $r['source_table'];
        }
        echo json_encode(['success' => true, 'data' => $rows]);
    } catch (Exception $e) {
        // Surface the real error so we can debug
        echo json_encode(['success' => false, 'data' => [], 'error' => $e->getMessage()]);
    }
    exit;
}

/**
 * GET: Fetch bookings submitted BY the current user (for the user's read-only view)
 * Usage: action.php?get_user_approvals=true
 */
if (isset($_GET['get_user_approvals'])) {
    header('Content-Type: application/json');
    if (!isset($_SESSION['tablename'])) {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        exit;
    }
    try {
        $myBookings = $db->getUserSubmittedApprovals($_SESSION['tablename']);
        echo json_encode(['success' => true, 'data' => $myBookings]);
    } catch (Exception $e) {
        echo json_encode(['success' => true, 'data' => [], 'notice' => 'Approval table not ready yet.']);
    }
    exit;
}

/**
 * GET: Fetch ALL pending bookings for superadmin
 * Usage: action.php?get_all_pending_approvals=true
 */
if (isset($_GET['get_all_pending_approvals'])) {
    header('Content-Type: application/json');
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superuseradmin') {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit;
    }
    $pending = $db->getPendingApprovalsForSuperadmin();
    echo json_encode(['success' => true, 'data' => $pending]);
    exit;
}

/**
 * POST: Approve a pending booking
 */
if (isset($_POST['approve_booking'])) {
    header('Content-Type: application/json');
    if (!isset($_SESSION['tablename'])) {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        exit;
    }
    // Only APPROVAL_MANAGER can approve on the promoter side
    if ($_SESSION['tablename'] !== APPROVAL_MANAGER) {
        echo json_encode(['success' => false, 'message' => 'Access denied.']);
        exit;
    }
    $approvalId    = (int) ($_POST['approval_id'] ?? 0);
    $approverTable = $_SESSION['tablename'];
    if ($approvalId <= 0) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Invalid approval ID.']);
        exit;
    }
    try {
        $result = $db->approveBooking($approvalId, $approverTable);
        ob_clean();
        echo json_encode($result);
    } catch (Exception $e) {
        error_log('approveBooking Error: ' . $e->getMessage());
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

/**
 * POST: Reject a pending booking
 * Usage: POST action.php with reject_booking=1, approval_id=X, rejection_reason=...
 */
if (isset($_POST['reject_booking'])) {
    header('Content-Type: application/json');
    if (!isset($_SESSION['tablename'])) {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        exit;
    }
    $approvalId = (int) ($_POST['approval_id'] ?? 0);
    $reason     = trim($_POST['rejection_reason'] ?? '');
    $rejector   = $_SESSION['tablename'];

    if ($approvalId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid approval ID.']);
        exit;
    }
    try {
        $result = $db->rejectBooking($approvalId, $rejector, $reason);
        echo json_encode($result);
    } catch (Exception $e) {
        error_log('rejectBooking Error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

/**
 * POST: Bulk approve multiple pending bookings
 * Usage: POST action.php with bulk_approve_bookings=1, approval_ids[]=X, ...
 */
if (isset($_POST['bulk_approve_bookings'])) {
    header('Content-Type: application/json');
    if (!isset($_SESSION['tablename']) || $_SESSION['tablename'] !== APPROVAL_MANAGER) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Access denied.']);
        exit;
    }
    $ids = array_filter(array_map('intval', $_POST['approval_ids'] ?? []));
    $approved = 0;
    foreach ($ids as $id) {
        try {
            $result = $db->approveBooking($id, $_SESSION['tablename']);
            if (!empty($result['success'])) $approved++;
        } catch (Exception $e) {
            error_log('bulkApprove Error for ID ' . $id . ': ' . $e->getMessage());
        }
    }
    ob_clean();
    echo json_encode(['success' => true, 'approved' => $approved]);
    exit;
}

/**
 * POST: Bulk reject multiple pending bookings
 * Usage: POST action.php with bulk_reject_bookings=1, approval_ids[]=X, rejection_reason=...
 */
if (isset($_POST['bulk_reject_bookings'])) {
    header('Content-Type: application/json');
    if (!isset($_SESSION['tablename']) || $_SESSION['tablename'] !== APPROVAL_MANAGER) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Access denied.']);
        exit;
    }
    $ids    = array_filter(array_map('intval', $_POST['approval_ids'] ?? []));
    $reason = trim($_POST['rejection_reason'] ?? '');
    $rejected = 0;
    foreach ($ids as $id) {
        try {
            $result = $db->rejectBooking($id, $_SESSION['tablename'], $reason);
            if (!empty($result['success'])) $rejected++;
        } catch (Exception $e) {
            error_log('bulkReject Error for ID ' . $id . ': ' . $e->getMessage());
        }
    }
    ob_clean();
    echo json_encode(['success' => true, 'rejected' => $rejected]);
    exit;
}

// ── Update a pending booking in booking_approvals ────────────────────────────
if (isset($_POST['update_booking_approval'])) {
    ob_clean();
    header('Content-Type: application/json');
    if (!isset($_SESSION['tablename'])) {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        exit;
    }
    $id = intval($_POST['approval_id'] ?? 0);
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'Invalid booking ID']);
        exit;
    }

    // Handle optional new file upload
    $newDocPath = null;
    if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../superadmin/uploads_form/';
        if (!is_dir($uploadDir)) { mkdir($uploadDir, 0755, true); }
        $fileName    = time() . '_' . basename($_FILES['document']['name']);
        $destPath    = $uploadDir . $fileName;
        if (move_uploaded_file($_FILES['document']['tmp_name'], $destPath)) {
            $newDocPath = $destPath;
        } else {
            echo json_encode(['success' => false, 'message' => 'File upload failed.']);
            exit;
        }
    }

    try {
        $conn = $db->getConnection();

        // Build the SET clause — only update document_path when a new file was supplied
        $setClause = "customer_name   = :cname,
                contact_number  = :phone,
                email_id        = :email,
                booking_date    = :bdate,
                booking_month   = :bmonth,
                builder         = :builder,
                project         = :project,
                project_type    = :ptype,
                unit_no         = :unit_no,
                size            = :size,
                agreement_value = :agreement,
                cashback        = :cashback,
                revenue         = :revenue,
                ccashback       = :ccashback,
                crevenue        = :crevenue,
                astatus         = :astatus,
                recived_amt     = :recived,
                source_lead     = :slead,
                city            = :city,
                remarks         = :remarks";
        if ($newDocPath !== null) {
            $setClause .= ",\n                document_path = :doc_path";
        }

        $stmt = $conn->prepare(
            "UPDATE booking_approvals SET $setClause
             WHERE id = :id AND approval_status = 'pending'"
        );

        $params = [
            'cname'     => trim($_POST['customer_name']    ?? ''),
            'phone'     => trim($_POST['contact_number']   ?? ''),
            'email'     => trim($_POST['email_id']         ?? ''),
            'bdate'     => $_POST['booking_date']          ?: null,
            'bmonth'    => trim($_POST['booking_month']    ?? ''),
            'builder'   => trim($_POST['builder']          ?? ''),
            'project'   => trim($_POST['project']          ?? ''),
            'ptype'     => trim($_POST['project_type']     ?? ''),
            'unit_no'   => trim($_POST['unit_no']          ?? ''),
            'size'      => trim($_POST['size']             ?? ''),
            'agreement' => floatval($_POST['agreement_value'] ?? 0),
            'cashback'  => floatval($_POST['cashback']     ?? 0),
            'revenue'   => floatval($_POST['revenue']      ?? 0),
            'ccashback' => floatval($_POST['ccashback']    ?? 0),
            'crevenue'  => floatval($_POST['crevenue']     ?? 0),
            'astatus'   => trim($_POST['astatus']          ?? ''),
            'recived'   => floatval($_POST['recived_amt']  ?? 0),
            'slead'     => trim($_POST['source_lead']      ?? ''),
            'city'      => trim($_POST['city']             ?? ''),
            'remarks'   => trim($_POST['remarks']          ?? ''),
            'id'        => $id,
        ];
        if ($newDocPath !== null) {
            $params['doc_path'] = $newDocPath;
        }

        $stmt->execute($params);

        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Booking updated successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'No changes saved. Booking may already be approved/rejected.']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}
  
  // Handle Delete User Ajax Request
  if (isset($_GET['delete'])) {
    $id = $_GET['id'];
    $currentUserId = $_SESSION['tablename'] ?? '';

    header('Content-Type: application/json');

    // Only APPROVAL_MANAGER can delete bookings
    if ($currentUserId !== APPROVAL_MANAGER) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied.']);
        exit;
    }

    if ($db->delete($id)) {
      echo json_encode(['success' => true, 'message' => 'Booking deleted successfully!']);
    } else {
      http_response_code(500);
      echo json_encode(['success' => false, 'message' => 'Something went wrong!']);
    }
    exit;
  }

  // Handle attachment download for a specific booking
  if (isset($_GET['download_attachment'])) {
    $id = $_GET['id'] ?? '';
    $currentUserId = $_SESSION['tablename'] ?? '';

    // Only APPROVAL_MANAGER can download attachments
    if ($currentUserId !== APPROVAL_MANAGER) {
        http_response_code(403);
        echo 'Access denied.';
        exit;
    }

    $booking = $db->readOne($id);
    $documentPath = $booking['document_path'] ?? '';
    if ($documentPath === '') {
      http_response_code(404);
      echo 'No attachment found.';
      exit;
    }

    $resolvedPath = realpath(__DIR__ . '/' . $documentPath);
    if ($resolvedPath === false || !is_file($resolvedPath)) {
      http_response_code(404);
      echo 'Attachment not found.';
      exit;
    }

    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($resolvedPath) . '"');
    header('Content-Length: ' . filesize($resolvedPath));
    readfile($resolvedPath);
    exit;
  }

  // Inline preview of an attachment (opens in browser tab)
  if (isset($_GET['preview_attachment'])) {
    $id = intval($_GET['id'] ?? 0);
    if (!$id) { http_response_code(400); echo 'Invalid ID.'; exit; }
    if (!isset($_SESSION['tablename'])) { http_response_code(403); echo 'Not logged in.'; exit; }

    // Query booking_approvals directly (readOne reads admintable, not approvals)
    try {
        $conn2 = $db->getConnection();
        $s = $conn2->prepare('SELECT document_path FROM booking_approvals WHERE id = :id LIMIT 1');
        $s->execute(['id' => $id]);
        $row = $s->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        http_response_code(500); echo 'DB error.'; exit;
    }

    $documentPath = $row['document_path'] ?? '';
    if ($documentPath === '') {
        http_response_code(404); echo 'No attachment found.'; exit;
    }

    // Resolve path: stored as "../superadmin/uploads_form/file.pdf" relative to action.php
    $resolvedPath = realpath(__DIR__ . '/' . $documentPath);
    if ($resolvedPath === false || !is_file($resolvedPath)) {
        // Try absolute path as stored
        $resolvedPath = realpath($documentPath);
    }
    if ($resolvedPath === false || !is_file($resolvedPath)) {
        http_response_code(404); echo 'File not found on server.'; exit;
    }

    $ext  = strtolower(pathinfo($resolvedPath, PATHINFO_EXTENSION));
    $mime = match($ext) {
        'pdf'        => 'application/pdf',
        'jpg','jpeg' => 'image/jpeg',
        'png'        => 'image/png',
        'gif'        => 'image/gif',
        'webp'       => 'image/webp',
        default      => 'application/octet-stream',
    };
    header('Content-Type: ' . $mime);
    header('Content-Disposition: inline; filename="' . basename($resolvedPath) . '"');
    header('Content-Length: ' . filesize($resolvedPath));
    readfile($resolvedPath);
    exit;
  }

 // Handle sending a message
if (isset($_POST['send_message'])) {
  // Sanitize input
  $sender_id = $_POST['sender_id'];
  $receiver_id = $_POST['receiver_id'];
  $message_content = $_POST['message_content'];
  // Send the message
  if ($db->sendMessage($sender_id, $receiver_id, $message_content)) {
      echo json_encode(array('success' => true, 'message' => 'Message sent successfully.'));
  } else {
      echo json_encode(array('success' => false, 'message' => 'Failed to send message.'));
  }
}

// Handle fetching messages for a user
if (isset($_POST['fetch_messages'])) {
  // Sanitize input
  $user_id = $_POST['user_id'];
  // Fetch messages
  $messages = $db->fetchMessages($user_id);
  // Return messages as JSON
  echo json_encode($messages);
}

// ----------------------------------------------------------------------------
// Handle Edit IVR Lead Name/Email
// ----------------------------------------------------------------------------
if (isset($_POST['edit_ivr_lead'])) {
    $lead_id = $_POST['lead_id'] ?? '';
    $new_name = $_POST['new_name'] ?? '';
    $new_email = $_POST['new_email'] ?? '';

    if (empty($lead_id) || empty($new_name)) {
        echo json_encode(['success' => false, 'message' => 'Lead ID and Name are required.']);
        exit;
    }

    try {
        $stmt = $db->getConnection()->prepare("UPDATE shi_upload_data SET name = :name, email = :email WHERE id = :id");
        $stmt->bindParam(':name', $new_name);
        $stmt->bindParam(':email', $new_email);
        $stmt->bindParam(':id', $lead_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'IVR Lead updated successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update database record.']);
        }
    } catch (PDOException $e) {
        error_log("Edit IVR Lead Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred.']);
    }
    exit;
}

?>