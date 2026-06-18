<?php
  session_start();
  require_once 'config.php';

  //Session for admin login here
  $adminCode = $_SESSION['code'];
  
  class Database extends Config {
    // Insert User Into Database
    public function insert($bdate, $bmonth, $developer, $bproject, $cname, $cnumber,
    $cemail, $tproject, $unitno, $psize, $cagreement, $ccashback, $crevenue, $cccashback,
    $ccrevenue, $cstatus, $brecived, $leadsource, $bremarks, $filePathStored, $city = '') {
      $sql = 'INSERT INTO admintable (booking_date, booking_month, builder, project, customer_name, contact_number,
      email_id, project_type, unit_no, size, agreement_value, cashback, revenue, ccashback, crevenue, astatus, recived_amt,
      update_date_column, source_lead, remarks, document_path, city) VALUES (:bdate, :bmonth, :developer, :bproject, :cname, :cnumber, :cemail, 
      :tproject, :unitno, :psize, :cagreement, :ccashback, :crevenue, :cccashback, :ccrevenue, :cstatus, :brecived, NOW(), :leadsource, :bremarks, :document_path, :city)';
      $stmt = $this->conn->prepare($sql);
      $stmt->execute([
        'bdate' => $bdate,
        'bmonth' => $bmonth,
        'developer' => $developer,
        'bproject' => $bproject,
        'cname' => $cname,
        'cnumber' => $cnumber,
        'cemail' => $cemail,
        'tproject' => $tproject,
        'unitno' => $unitno,
        'psize' => $psize,
        'cagreement' => $cagreement,
        'ccashback' => $ccashback,
        'crevenue' => $crevenue,
        'cccashback' => $cccashback,
        'ccrevenue' => $ccrevenue,
        'cstatus' => $cstatus,
        'brecived' => $brecived,
        'leadsource' => $leadsource,
        'bremarks' => $bremarks,
        'document_path' => $filePathStored,
        'city' => $city,
      ]);
      return true;
    }

    // Fetch All vipul From Database
    public function read() {
            // Safer approach: fetch admintable and updaterowtable separately and merge in PHP
            // This avoids UNION cardinality issues when the two tables have different columns
            try {
                // Fetch all rows from admintable
                $sqlA = 'SELECT * FROM admintable';
                $stmtA = $this->conn->prepare($sqlA);
                $stmtA->execute();
                $admRows = $stmtA->fetchAll(PDO::FETCH_ASSOC);

                // Fetch all rows from updaterowtable (overrides)
                $sqlU = 'SELECT * FROM updaterowtable';
                $stmtU = $this->conn->prepare($sqlU);
                $stmtU->execute();
                $updRows = $stmtU->fetchAll(PDO::FETCH_ASSOC);

                // Index updaterowtable rows by id for quick lookup
                $updMap = [];
                foreach ($updRows as $r) {
                    if (isset($r['id'])) $updMap[$r['id']] = $r;
                }

                $result = [];
                // For each admintable row, prefer updaterowtable row if present
                foreach ($admRows as $a) {
                    if (isset($a['id']) && isset($updMap[$a['id']])) {
                        $result[] = $updMap[$a['id']];
                        // Remove from map so remaining updater rows can be appended later
                        unset($updMap[$a['id']]);
                    } else {
                        $result[] = $a;
                    }
                }

                // Append any updater rows that don't exist in admintable
                foreach ($updMap as $remaining) {
                    $result[] = $remaining;
                }

                // Sort by id DESC if id present
                usort($result, function($x, $y) {
                    $ix = isset($x['id']) ? (int)$x['id'] : 0;
                    $iy = isset($y['id']) ? (int)$y['id'] : 0;
                    return $iy <=> $ix;
                });

                return $result;
            } catch (PDOException $e) {
                error_log('[db.php] read() failed: ' . $e->getMessage());
                return [];
            }
  }   
  

    // Fetch the Most Recent Updated Row from the Database
    public function recent($month){
      $sql = 'SELECT id, recived_amt
      FROM admintable
      WHERE recived_amt != 0 AND booking_month = :month
      ORDER BY update_date_column DESC
      LIMIT 1';
      $stmt = $this->conn->prepare($sql);
      $stmt->bindParam(':month', $month);
      $stmt->execute();
      $result = $stmt->fetch();
      return $result;
    }


    // Store all send amount in recent amount send_amt column 
    public function status_counter($groupKey) {
      list($startYear, $endYear) = explode('-', $groupKey);
  
      // Define the start and end months for the financial year in the format "YYYY-MM"
      $start_month = "$startYear-04";
      $end_month = "$endYear-03";  // End year to include up to March 31st of the next year
  
      // Get all rows for this financial year (merged from admintable + updaterowtable)
      // This matches the same logic as read() function
      try {
          // Fetch all rows from admintable for the financial year
          $sqlA = 'SELECT id, astatus FROM admintable WHERE booking_month BETWEEN :start_month AND :end_month';
          $stmtA = $this->conn->prepare($sqlA);
          $stmtA->bindParam(':start_month', $start_month);
          $stmtA->bindParam(':end_month', $end_month);
          $stmtA->execute();
          $admRows = $stmtA->fetchAll(PDO::FETCH_ASSOC);

          // Fetch all rows from updaterowtable (overrides)
          $sqlU = 'SELECT id, astatus FROM updaterowtable';
          $stmtU = $this->conn->prepare($sqlU);
          $stmtU->execute();
          $updRows = $stmtU->fetchAll(PDO::FETCH_ASSOC);

          // Index updaterowtable rows by id for quick lookup
          $updMap = [];
          foreach ($updRows as $r) {
              if (isset($r['id'])) $updMap[$r['id']] = $r;
          }

          $result = [];
          // For each admintable row, prefer updaterowtable row if present
          foreach ($admRows as $a) {
              if (isset($a['id']) && isset($updMap[$a['id']])) {
                  $result[] = $updMap[$a['id']];
              } else {
                  $result[] = $a;
              }
          }

          // Count statuses from merged result
          $received_count = 0;
          $canceled_count = 0;
          $processing_count = 0;

          foreach ($result as $row) {
              $status = $row['astatus'] ?? '';
              if ($status === 'Received') {
                  $received_count++;
              } elseif ($status === 'Canceled' || $status === 'Cancled') {
                  // Handle both spellings for backward compatibility
                  $canceled_count++;
              } elseif ($status === 'Processing') {
                  $processing_count++;
              }
          }

          return [
              'received_count' => $received_count,
              'canceled_count' => $canceled_count,
              'processing_count' => $processing_count
          ];
      } catch (PDOException $e) {
          error_log('[db.php] status_counter() failed: ' . $e->getMessage());
          return [
              'received_count' => 0,
              'canceled_count' => 0,
              'processing_count' => 0
          ];
      }
    }  
    
     // this function is for aranging the amount according to yearwise and adding the amount according to that
    public function calculate_total_getamount($groupKey) {
      $total = 0;
      $sql = "SELECT getamount, booking_month FROM admintable";
      $stmt = $this->conn->prepare($sql);
      $stmt->execute();
      $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

      foreach ($rows as $row) {
          $monthYear = $row['booking_month'];
          $year = date('Y', strtotime($monthYear)); // Extract the year from the booking_month
          $month = date('n', strtotime($monthYear)); // Extract the month (1-12) from the booking_month

          // Adjust the year if the month is before April
          if ($month < 4) {
              $year--;
          }

          $groupStartYear = intval(explode('-', $groupKey)[0]);
          $groupEndYear = intval(explode('-', $groupKey)[1]);

          if (($year >= $groupStartYear && $year < $groupEndYear) || ($year === $groupStartYear && $month < 4)) {
              $total += $row['getamount'];
          }
      }
      return $total;
    } 

    // Get the sum of releaseamount values from column send_amt and getting the total of group row
    public function getTotalSendAmt(){
        $sql = 'SELECT SUM(send_amt) AS total FROM admintable';
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch();
        return $result['total'];
    } 
    
    // this function is collecting the amount from all users who have recived amount in between financial year
    public function totalGivenAmt($groupKey) {
      // Prepare the SQL query to fetch send_amt and booking_month from maintable
      $sql = "SELECT send_amt, booking_month FROM admintable";
      $stmt = $this->conn->prepare($sql);
      $stmt->execute();
      $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
      
      $total = 0;
  
      foreach ($rows as $row) {
          $monthYear = $row['booking_month'];
          $year = date('Y', strtotime($monthYear)); // Extract the year from the booking_month
          $month = date('n', strtotime($monthYear)); // Extract the month (1-12) from the booking_month
  
          // Adjust the year if the month is before April
          if ($month < 4) {
              $year--;
          }
  
          $groupStartYear = intval(explode('-', $groupKey)[0]);
          $groupEndYear = intval(explode('-', $groupKey)[1]);
  
          if (($year >= $groupStartYear && $year < $groupEndYear) || ($year === $groupStartYear && $month < 4)) {
              $total += $row['send_amt'];
          }
      }
      return $total;
  }
  // THis function is to get the total of managers incentive
  public function totalGivenAmtManager($groupKey) {
    // Prepare the SQL query to fetch send_amt and booking_month from maintable
    $sql = "SELECT send_amt, month FROM tracking_table WHERE user_type IN ('manager', 'teamlead', 'ceo')";
    $stmt = $this->conn->prepare($sql);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total = 0;

    foreach ($rows as $row) {
        $monthYear = $row['month'];
        $year = date('Y', strtotime($monthYear)); // Extract the year from the booking_month
        $month = date('n', strtotime($monthYear)); // Extract the month (1-12) from the booking_month

        // Adjust the year if the month is before April
        if ($month < 4) {
            $year--;
        }

        $groupStartYear = intval(explode('-', $groupKey)[0]);
        $groupEndYear = intval(explode('-', $groupKey)[1]);

        if (($year >= $groupStartYear && $year < $groupEndYear) || ($year === $groupStartYear && $month < 4)) {
            $total += $row['send_amt'];
        }
    }
    return $total;
  }
  //This function is for get the total of year
  public function totalGivenAmtSalary($groupKey) {
    // Prepare the SQL query to fetch send_amt and booking_month from maintable
    $sql = "SELECT send_amt, month FROM tracking_table WHERE user_type IN ('salary')";
    $stmt = $this->conn->prepare($sql);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total = 0;
    foreach ($rows as $row) {
        $monthYear = $row['month'];
        $year = date('Y', strtotime($monthYear)); // Extract the year from the booking_month
        $month = date('n', strtotime($monthYear)); // Extract the month (1-12) from the booking_month
        // Adjust the year if the month is before April
        if ($month < 4) {
            $year--;
        }
        $groupStartYear = intval(explode('-', $groupKey)[0]);
        $groupEndYear = intval(explode('-', $groupKey)[1]);
        if (($year >= $groupStartYear && $year < $groupEndYear) || ($year === $groupStartYear && $month < 4)) {
            $total += $row['send_amt'];
        }
    }
    return $total;
  }
    // Print table rows count function
    public function printTableRowsCount() {
      // Query to get the count of rows in maintable
      $sql = "SELECT COUNT(*) FROM admintable";
      $stmt = $this->conn->prepare($sql);
      $stmt->execute();
      $rowCount = $stmt->fetchColumn();

      // Return the count in an associative array format for consistency
      return ['admintable' => $rowCount];
      } 

    // Fetch Single User From Database
    public function readOne($id) {
      // Check if the row exists in updaterowtable
      $sqlCheckReferenceTable = 'SELECT COUNT(*) FROM updaterowtable WHERE id = :id';
      $stmtCheckReferenceTable = $this->conn->prepare($sqlCheckReferenceTable);
      $stmtCheckReferenceTable->execute(['id' => $id]);
      $rowExistsInReferenceTable = $stmtCheckReferenceTable->fetchColumn() > 0;
  
      // Fetch data based on the existence in updaterowtable
      if ($rowExistsInReferenceTable) {
          $sql = 'SELECT * FROM updaterowtable WHERE id = :id';
      } else {
          $sql = 'SELECT * FROM admintable WHERE id = :id';
      }
  
      $stmt = $this->conn->prepare($sql);
      $stmt->execute(['id' => $id]);
      $result = $stmt->fetch();
      return $result;
  }

  public function update(
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
    $cashbackverify,
    $filePathStored = null
) {
    // Determine if the row exists in updaterowtable
    $sqlCheckReferenceTable = 'SELECT COUNT(*) FROM updaterowtable WHERE id = :id';
    $stmtCheckReferenceTable = $this->conn->prepare($sqlCheckReferenceTable);
    $stmtCheckReferenceTable->execute(['id' => $id]);
    $rowExistsInReferenceTable = $stmtCheckReferenceTable->fetchColumn() > 0;

    // Fetch data from either updaterowtable or admintable
    if ($rowExistsInReferenceTable) {
        $sqlFetchRow = 'SELECT * FROM updaterowtable WHERE id = :id';
        $stmtFetchRow = $this->conn->prepare($sqlFetchRow);
        $stmtFetchRow->execute(['id' => $id]);
        $rowData = $stmtFetchRow->fetch(PDO::FETCH_ASSOC);
    } else {
        $sqlFetchRow = 'SELECT * FROM admintable WHERE id = :id';
        $stmtFetchRow = $this->conn->prepare($sqlFetchRow);
        $stmtFetchRow->execute(['id' => $id]);
        $rowData = $stmtFetchRow->fetch(PDO::FETCH_ASSOC);
    }

    // Update operation based on $updateInUserTable flag
    if ($updateInUserTable) {
        $sqlUpdateMainTable = 'UPDATE admintable SET
            booking_date = :bdate,
            booking_month = :bmonth,
            builder = :developer,
            project = :bproject,
            customer_name = :cname,
            contact_number = :cnumber,
            email_id = :cemail,
            project_type = :tproject,
            unit_no = :unitno,
            size = :psize,
            agreement_value = :cagreement,
            cashback = :ccashback,
            revenue = :crevenue,
            ccashback = :cccashback,
            crevenue = :ccrevenue,
            astatus = :cstatus,
            recived_amt = :brecived,
            invoice_raise = :invoice,
            source_table = :source_table,
            update_in_invoice_table = :updateInvoice,
            cashbackverify = :cashbackverify,
            update_in_user_table = 1';
        
        // Add document_path update only if a new file was uploaded
        if ($filePathStored !== null) {
            $sqlUpdateMainTable .= ', document_path = :document_path';
        }
        
        $sqlUpdateMainTable .= ' WHERE id = :id';

        $stmtUpdateMainTable = $this->conn->prepare($sqlUpdateMainTable);
        
        $params = [
            'bdate' => $bdate,
            'bmonth' => $bmonth,
            'developer' => $developer,
            'bproject' => $bproject,
            'cname' => $cname,
            'cnumber' => $cnumber,
            'cemail' => $cemail,
            'tproject' => $tproject,
            'unitno' => $unitno,
            'psize' => $psize,
            'cagreement' => $cagreement,
            'ccashback' => $ccashback,
            'crevenue' => $crevenue,
            'cccashback' => $cccashback,
            'ccrevenue' => $ccrevenue,
            'cstatus' => $cstatus,
            'brecived' => $brecived,
            'invoice' => $invoice,
            'source_table' => $tablename,
            'updateInvoice' => $updateInvoice,
            'cashbackverify' => $cashbackverify,
            'id' => $id
        ];
        
        if ($filePathStored !== null) {
            $params['document_path'] = $filePathStored;
        }
        
        $stmtUpdateMainTable->execute($params);

        // Delete the corresponding row from the reference table if it exists
        $sqlDeleteFromReferenceTable = 'DELETE FROM updaterowtable WHERE id = :id';
        $stmtDeleteFromReferenceTable = $this->conn->prepare($sqlDeleteFromReferenceTable);
        $stmtDeleteFromReferenceTable->execute(['id' => $id]);
    } else {
        // Insert or update the reference table for the superuser view
        $sqlInsertOrUpdateReferenceTable = 'INSERT INTO updaterowtable (
            id,
            booking_date,
            booking_month,
            builder,
            project,
            customer_name,
            contact_number,
            email_id,
            project_type,
            unit_no,
            size,
            agreement_value,
            cashback,
            revenue,
            ccashback,
            crevenue,
            astatus,
            recived_amt,
            invoice_raise,
            source_table,
            update_in_invoice_table,
            cashbackverify';
        
        if ($filePathStored !== null) {
            $sqlInsertOrUpdateReferenceTable .= ', document_path';
        }
        
        $sqlInsertOrUpdateReferenceTable .= ') VALUES (
            :id,
            :bdate,
            :bmonth,
            :developer,
            :bproject,
            :cname,
            :cnumber,
            :cemail,
            :tproject,
            :unitno,
            :psize,
            :cagreement,
            :ccashback,
            :crevenue,
            :cccashback,
            :ccrevenue,
            :cstatus,
            :brecived,
            :invoice,
            :source_table,
            :updateInvoice,
            :cashbackverify';
        
        if ($filePathStored !== null) {
            $sqlInsertOrUpdateReferenceTable .= ', :document_path';
        }
        
        $sqlInsertOrUpdateReferenceTable .= ') ON DUPLICATE KEY UPDATE
            booking_date = VALUES(booking_date),
            booking_month = VALUES(booking_month),
            builder = VALUES(builder),
            project = VALUES(project),
            customer_name = VALUES(customer_name),
            contact_number = VALUES(contact_number),
            email_id = VALUES(email_id),
            project_type = VALUES(project_type),
            unit_no = VALUES(unit_no),
            size = VALUES(size),
            agreement_value = VALUES(agreement_value),
            cashback = VALUES(cashback),
            revenue = VALUES(revenue),
            ccashback = VALUES(ccashback),
            crevenue = VALUES(crevenue),
            astatus = VALUES(astatus),
            recived_amt = VALUES(recived_amt),
            invoice_raise = VALUES(invoice_raise),
            source_table = VALUES(source_table),
            update_in_invoice_table = VALUES(update_in_invoice_table),
            cashbackverify = VALUES(cashbackverify)';
        
        if ($filePathStored !== null) {
            $sqlInsertOrUpdateReferenceTable .= ', document_path = VALUES(document_path)';
        }
            

        $stmtInsertOrUpdateReferenceTable = $this->conn->prepare($sqlInsertOrUpdateReferenceTable);
        
        $refParams = [
            'id' => $id,
            'bdate' => $bdate,
            'bmonth' => $bmonth,
            'developer' => $developer,
            'bproject' => $bproject,
            'cname' => $cname,
            'cnumber' => $cnumber,
            'cemail' => $cemail,
            'tproject' => $tproject,
            'unitno' => $unitno,
            'psize' => $psize,
            'cagreement' => $cagreement,
            'ccashback' => $ccashback,
            'crevenue' => $crevenue,
            'cccashback' => $cccashback,
            'ccrevenue' => $ccrevenue,
            'cstatus' => $cstatus,
            'brecived' => $brecived,
            'invoice' => $invoice,
            'source_table' => $tablename,
            'updateInvoice' => $updateInvoice,
            'cashbackverify' => $cashbackverify,
        ];
        
        if ($filePathStored !== null) {
            $refParams['document_path'] = $filePathStored;
        }
        
        $stmtInsertOrUpdateReferenceTable->execute($refParams);
    }
    return true;
} 
  
  //Function Get the user table update here
  public function getCheckboxValue($id) {
        // Check value in updaterowtable first
        $sql = 'SELECT update_in_user_table FROM updaterowtable WHERE id = :id';
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            // If the value exists in updaterowtable, return it
            return $result['update_in_user_table'];
        } else {
            // Otherwise, check the value in admintable
            $sql = 'SELECT update_in_user_table FROM admintable WHERE id = :id';
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            // Return the value of the checkbox column
            return $result['update_in_user_table'];
        }
    }
    public function getCheckboxCashBack($id) {
        // Check value in updaterowtable first
        $sql = 'SELECT cashbackverify FROM updaterowtable WHERE id = :id';
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            // If the value exists in updaterowtable, return it
            return $result['cashbackverify'];
        } else {
            // Otherwise, check the value in admintable
            $sql = 'SELECT cashbackverify FROM admintable WHERE id = :id';
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            // Return the value of the checkbox column
            return $result['cashbackverify'];
        }
    }

    // Delete User From Database
    public function delete($id) {
      // Delete from admintable
      $sqlDeleteAdminTable = 'DELETE FROM admintable WHERE id = :id';
      $stmtDeleteAdminTable = $this->conn->prepare($sqlDeleteAdminTable);
      $stmtDeleteAdminTable->execute(['id' => $id]);
  
      // Delete from updaterowtable
      $sqlDeleteReferenceTable = 'DELETE FROM updaterowtable WHERE id = :id';
      $stmtDeleteReferenceTable = $this->conn->prepare($sqlDeleteReferenceTable);
      $stmtDeleteReferenceTable->execute(['id' => $id]);
  
      return true;
  }  

    public function updateAdvancePay($id, $newAdvancePay) {
      $sql = 'UPDATE payment_table SET advance_pay = :newAdvancePay WHERE id = :id';
      $stmt = $this->conn->prepare($sql);
      $stmt->bindParam(':newAdvancePay', $newAdvancePay);
      $stmt->bindParam(':id', $id);
      $stmt->execute();
      // You might want to check for success or handle errors here
  }
  // this function is for insert the salary everymonth
  public function insertMonthlySalaryTotal() {
        // Get the previous month in 'YYYY-MM' format
        $previousMonth = date('Y-m', strtotime('first day of last month'));
        $user_type = 'salary';
        $user_name = 'Search Homes India';

        // Calculate the total salary of all active users
        $sql = "SELECT SUM(salary) AS total_salary, COUNT(*) AS active_users FROM accounts WHERE is_active = 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $totalSalary = $result['total_salary'];
        $activeUsers = $result['active_users'];

        // Check if an entry for the previous month with the same total salary already exists
        $checkSql = "SELECT COUNT(*) AS count FROM tracking_table WHERE month = :month AND send_amt = :salary";
        $checkStmt = $this->conn->prepare($checkSql);
        $checkStmt->bindParam(':month', $previousMonth);
        $checkStmt->bindParam(':salary', $totalSalary);
        $checkStmt->execute();
        $checkResult = $checkStmt->fetch(PDO::FETCH_ASSOC);

        // Check if there is any user assigned for the same month
        $userCheckSql = "SELECT COUNT(*) AS count FROM tracking_table WHERE month = :month AND user_name = :user_name";
        $userCheckStmt = $this->conn->prepare($userCheckSql);
        $userCheckStmt->bindParam(':month', $previousMonth);
        $userCheckStmt->bindParam(':user_name', $user_name);
        $userCheckStmt->execute();
        $userCheckResult = $userCheckStmt->fetch(PDO::FETCH_ASSOC);

        if ($checkResult['count'] == 0 && $userCheckResult['count'] == 0) {
            // No entry found for the previous month with the same salary and no user assigned, proceed to insert
            $insertSql = "INSERT INTO tracking_table (month, send_amt, user_name, user_type, bookin_number) VALUES (:month, :salary, :user_name, :user_type, :bookin_number)";
            $insertStmt = $this->conn->prepare($insertSql);
            $insertStmt->bindParam(':month', $previousMonth);
            $insertStmt->bindParam(':salary', $totalSalary);
            $insertStmt->bindParam(':user_name', $user_name);
            $insertStmt->bindParam(':user_type', $user_type);
            $insertStmt->bindParam(':bookin_number', $totalSalary); // Binding totalSalary to bookin_number
            $insertStmt->bindParam(':bookin_number', $activeUsers);

            if ($insertStmt->execute()) {
                return "Monthly salary total inserted successfully.";
            } else {
                return "Failed to insert monthly salary total.";
            }
        } else {
            return "Monthly salary total for the previous month already exists or user is already assigned.";
        }
    }
    // This function is for get the total of every finincial year expenses
    public function totalExpensesForFinancialYear($groupKey) {
        // Prepare the SQL query to fetch expenses_amount and month from expenses table
	    $sql = "SELECT expense_amount, expenses_month FROM company_expenses";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total = 0;

        foreach ($rows as $row) {
            $monthYear = $row['expenses_month'];
            $year = date('Y', strtotime($monthYear)); // Extract the year from the month
            $month = date('n', strtotime($monthYear)); // Extract the month (1-12) from the month

            // Adjust the year if the month is before April
            if ($month < 4) {
                $year--;
            }

            $groupStartYear = intval(explode('-', $groupKey)[0]);
            $groupEndYear = intval(explode('-', $groupKey)[1]);

            if (($year >= $groupStartYear && $year < $groupEndYear) || ($year === $groupStartYear && $month < 4)) {
                $total += $row['expense_amount'];
            }
        }

        return $total;
    }
    // This is to plot the graph profit and loss
    public function totalExpensesForFinancialYear_monthly($groupKey) {
        $monthlyTotals = array_fill(0, 12, 0); // Initialize array for 12 months with zero
        
        $sql = "SELECT expense_amount, expenses_month FROM company_expenses";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($rows as $row) {
            $monthYear = $row['expenses_month'];
            $year = date('Y', strtotime($monthYear));
            $month = date('n', strtotime($monthYear)); // Month (1-12)
    
            // Adjust the year if the month is before April
            if ($month < 4) { // Jan, Feb, Mar are in the previous financial year
                $year--;
            }
    
            $groupStartYear = intval(explode('-', $groupKey)[0]);
            $groupEndYear = intval(explode('-', $groupKey)[1]);
    
            if ($year == $groupStartYear || $year == $groupEndYear - 1) {
                $monthlyTotals[$month - 1] += $row['expense_amount']; // Adjust month index (0-11)
            }
        }
        
        return $monthlyTotals; // Return array of monthly totals
    }
    
    public function totalGivenAmtSalary_monthly($groupKey) {
        $monthlyTotals = array_fill(0, 12, 0); // Initialize array for 12 months with zero
        
        $sql = "SELECT send_amt, month FROM tracking_table WHERE user_type IN ('salary')";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($rows as $row) {
            $monthYear = $row['month'];
            $year = date('Y', strtotime($monthYear));
            $month = date('n', strtotime($monthYear)); // Month (1-12)
    
            // Adjust the year if the month is before April
            if ($month < 4) { // Jan, Feb, Mar are in the previous financial year
                $year--;
            }
    
            $groupStartYear = intval(explode('-', $groupKey)[0]);
            $groupEndYear = intval(explode('-', $groupKey)[1]);
    
            if ($year == $groupStartYear || $year == $groupEndYear - 1) {
                $monthlyTotals[$month - 1] += $row['send_amt']; // Adjust month index (0-11)
            }
        }
        
        return $monthlyTotals; // Return array of monthly totals
    }
    
    public function totalGivenAmtManager_monthly($groupKey) {
        $monthlyTotals = array_fill(0, 12, 0); // Initialize array for 12 months with zero
        
        $sql = "SELECT send_amt, month FROM tracking_table WHERE user_type IN ('manager', 'teamlead', 'ceo')";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($rows as $row) {
            $monthYear = $row['month'];
            $year = date('Y', strtotime($monthYear));
            $month = date('n', strtotime($monthYear)); // Month (1-12)
    
            // Adjust the year if the month is before April
            if ($month < 4) { // Jan, Feb, Mar are in the previous financial year
                $year--;
            }
    
            $groupStartYear = intval(explode('-', $groupKey)[0]);
            $groupEndYear = intval(explode('-', $groupKey)[1]);
    
            if ($year == $groupStartYear || $year == $groupEndYear - 1) {
                $monthlyTotals[$month - 1] += $row['send_amt']; // Adjust month index (0-11)
            }
        }
        
        return $monthlyTotals; // Return array of monthly totals
    }
    
    public function calculate_total_getamount_monthly($groupKey) {
        $monthlyTotals = array_fill(0, 12, 0); // Initialize array for 12 months with zero
        
        $sql = "SELECT getamount, booking_month FROM admintable";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($rows as $row) {
            $monthYear = $row['booking_month'];
            $year = date('Y', strtotime($monthYear));
            $month = date('n', strtotime($monthYear)); // Month (1-12)
    
            // Adjust the year if the month is before April
            if ($month < 4) { // Jan, Feb, Mar are in the previous financial year
                $year--;
            }
    
            $groupStartYear = intval(explode('-', $groupKey)[0]);
            $groupEndYear = intval(explode('-', $groupKey)[1]);
    
            if ($year == $groupStartYear || $year == $groupEndYear - 1) {
                $monthlyTotals[$month - 1] += $row['getamount']; // Adjust month index (0-11)
            }
        }
        
        return $monthlyTotals; // Return array of monthly totals
    }
    
    public function actual_revenue_monthly($groupKey) {
        $monthlyTotals = array_fill(0, 12, 0); // Initialize array for 12 months with zero
    
        $sql = "SELECT crevenue, booking_month FROM admintable";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
        foreach ($rows as $row) {
            $monthYear = $row['booking_month'];
            $year = date('Y', strtotime($monthYear));
            $month = date('n', strtotime($monthYear)); // Month (1-12)
    
            // Adjust the year if the month is before April
            if ($month < 4) { // Jan, Feb, Mar are in the previous financial year
                $year--;
            }
    
            $groupStartYear = intval(explode('-', $groupKey)[0]);
            $groupEndYear = intval(explode('-', $groupKey)[1]);
    
            if ($year == $groupStartYear || $year == $groupEndYear - 1) {
                $monthlyTotals[$month - 1] += $row['crevenue']; // Adjust month index (0-11)
            }
        }
    
        return $monthlyTotals; // Return array of monthly totals
    }  
}
?>