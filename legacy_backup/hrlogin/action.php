<?php
  error_reporting(E_ALL);
  ini_set('display_errors', 1);
  if (isset($_POST['record_live_location'])) {
      session_name('HRSESSID');
  }
  session_start();
  require_once 'db.php';
  require_once 'util.php';
  require_once 'payroll_payslip_builder.php';
  $db = new Database;
  $util = new Util;
  // Ensure Payroll table exists
  // $db->createPayrollTable();
  // $db->createDeductionsTable();

  // --- Company Holiday Calendar (HR only) ---
  if (isset($_GET['holidays_list']) || isset($_POST['save_holiday']) || isset($_POST['delete_holiday'])) {
    header('Content-Type: application/json');
    if (!isset($_SESSION['loggedin']) || ($_SESSION['role'] ?? '') !== 'hradminuser') {
      http_response_code(403);
      echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
      exit;
    }

    // List holidays for a month or year
    if (isset($_GET['holidays_list'])) {
      $year = (int)($_GET['year'] ?? (int)date('Y'));
      $month = (int)($_GET['month'] ?? 0);
      if ($month >= 1 && $month <= 12) {
        $start = sprintf('%04d-%02d-01', $year, $month);
        $end = date('Y-m-t', strtotime($start));
      } else {
        $start = sprintf('%04d-01-01', $year);
        $end = sprintf('%04d-12-31', $year);
      }

      $rows = $db->listCompanyHolidays($start, $end);
      echo json_encode(['status' => 'success', 'data' => $rows]);
      exit;
    }

    // Save / upsert holiday
    if (isset($_POST['save_holiday'])) {
      $date = trim((string)($_POST['date'] ?? ''));
      $reason = trim((string)($_POST['reason'] ?? ''));
      if ($date === '' || $reason === '') {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Missing date or reason']);
        exit;
      }
      $createdBy = isset($_SESSION['id']) ? (int)$_SESSION['id'] : null;
      $ok = $db->upsertCompanyHoliday($date, $reason, $createdBy);
      echo json_encode(['status' => $ok ? 'success' : 'error']);
      exit;
    }

    // Delete holiday
    if (isset($_POST['delete_holiday'])) {
      $date = trim((string)($_POST['date'] ?? ''));
      if ($date === '') {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Missing date']);
        exit;
      }
      $ok = $db->deleteCompanyHoliday($date);
      echo json_encode(['status' => $ok ? 'success' : 'error']);
      exit;
    }
  }

  if (isset($_GET['check_db'])) {
    try {
        $res = $db->getConnection()->query('DESC accounts')->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($res);
    } catch (Exception $e) {
        echo "DB Error: " . $e->getMessage();
    }
    exit;
  }
  if (isset($_GET['check_email'])) {
    header('Content-Type: application/json');
    $email = $util->testInput($_GET['email'] ?? '');
    $excludeId = isset($_GET['exclude_id']) ? (int)$_GET['exclude_id'] : null;
    echo json_encode(['exists' => $db->emailExists($email, $excludeId)]);
    exit;
  }
  if (isset($_POST['add'])) {
    $doj = $util->testInput($_POST['doj']);
    $dob = $util->testInput($_POST['dob']);
    $ename = $util->testInput($_POST['ename']);
    $eemail = $util->testInput($_POST['eemail']);
    $enumber = $util->testInput($_POST['enumber']);
    $epass = $util->testInput($_POST['epass']);
    $esalary = $util->testInput($_POST['esalary']);
    $etable = $util->testInput($_POST['etable']);
    $emid = $util->testInput($_POST['emid']);
    $ecode = $util->testInput($_POST['ecode'] ?? '');
    $amountO = $util->testInput($_POST['amountO'] ?? 0);
    $amountT = $util->testInput($_POST['amountT'] ?? 0);
    $amountTh = $util->testInput($_POST['amountTh'] ?? 0);
    $amountF = $util->testInput($_POST['amountF'] ?? 0);
    $amountFf = $util->testInput($_POST['amountFf'] ?? 0);
    $amountS = $util->testInput($_POST['amountS'] ?? 0);
    $project_name = $util->testInput($_POST['project_name'] ?? '');
    $D_project = $util->testInput($_POST['D_project'] ?? '');
    $user_type = $util->testInput($_POST['user_type'] ?? '');
    $assign_user = $_POST['assign_user'] ?? '';
    $city = $util->testInput($_POST['city'] ?? '');
    $is_active = (int)($util->testInput($_POST['is_active'] ?? 1));

    if ($ename === '' || $eemail === '' || $enumber === '' || $epass === '') {
      echo $util->showMessage('danger', 'Please complete all required Basic Information fields: Full Name, Email, Contact No., and Password.');
    } elseif ($db->emailExists($eemail)) {
      echo $util->showMessage('danger', 'Email "' . htmlspecialchars($eemail) . '" is already registered to another employee. Each user must have a unique email address.');
    } elseif ($etable !== '' && $db->tablenameExists($etable)) {
      echo $util->showMessage('danger', 'Unique ID "' . htmlspecialchars($etable) . '" is already used by another user. Please enter a different Unique ID.');
    } elseif ($db->insert($doj, $dob, $ename, $eemail, $enumber, $epass, $esalary, $etable, $emid, $ecode, $amountO, $amountT, $amountTh, $amountF, $amountFf, $amountS, $project_name, $D_project, $user_type, $assign_user, $is_active, $city)) {
      echo $util->showMessage('success', 'User created successfully and is active.');
    } else {
      echo $util->showMessage('danger', 'Could not create user. The email or Unique ID may already exist, or required data is invalid.');
    }
  }
  // Handle Fetch All Users Ajax Request
  if (isset($_GET['read'])) {
    $users = $db->read();
    $output = '';
    if ($users) {
        $rowCount = 0; // Initialize the rowCount variable
        foreach ($users as $row) {
          $rowCount++; // Increment the rowCount for each iteration
      }
      foreach ($users as $row) {
        $statusText = ($row['is_active'] == 1) ? 'Active' : 'Inactive';
        $statusClass = ($row['is_active'] == 1) ? 'text-success fw-bold' : 'text-danger fw-bold';
        
        $inactive_at = ($row['deactivated_at'] === null) ? '<span>NA</span>' : '<span>' . $row['deactivated_at'] . '</span>';
        $output .= '<tr class="user-data-row">
                      <td class="checkbox-col"><input type="checkbox" class="user-row-checkbox"></td>
                      <td>' . $row['id'] . '</td>
                      <td class="' . $statusClass . '">' . $statusText . '</td>
                      <td>' . $row['username'] . '</td>
                      <td>' . $row['useremail'] . '</td>
                      <td>' . $row['phonenumber'] . '</td>
                      <td>••••••••</td>
                      <td>' . $row['salary'] . '</td>
                      <td>' . $row['doj'] . '</td>
                      <td>' . $row['dob'] . '</td>
                      <td>' . $row['tablename'] . '</td>
                      <td>' . $row['employee_id'] . '</td>
                      <td>' . $row['one_amt'] . '</td>
                      <td>' . $row['two_amt'] . '</td>
                      <td>' . $row['thrid_amt'] . '</td>
                      <td>' . $row['forth_amt'] . '</td>
                      <td>' . $row['fifth_amt'] . '</td>
                      <td>' . $row['sixth_amt'] . '</td>
                      <td>' . $row['project_name'] . '</td>
                      <td>' . $row['project_type'] . '</td>
                      <td>' . ($row['city'] ?? '') . '</td>
                      <td>' . $row['user_type'] . '</td>
                      <td>' . $row['assign_user'] . '</td>
                      <td>' . $row['created_at'] . '</td>
                      <td>' . $inactive_at . '</td>';
        $output .= '<td>
                      <div class="action-buttons">
                        <a href="#" id="' . $row['id'] . '" class="action-btn edit-btn editLink" data-bs-toggle="modal" data-bs-target="#editUserModal">
                          <i class="bi bi-pencil-square"></i>
                        </a>
                        <a href="#" id="' . $row['id'] . '" class="action-btn delete-btn deleteLink">
                          <i class="bi bi-trash"></i>
                        </a>
                      </div>
                    </td>
                  </tr>';
    }
    
      echo $output;
    } else {
      echo '<tr>
              <td colspan="20">No Users Found in the Database!</td>
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
    $id = $util->testInput($_POST['id']);
    $doj = $util->testInput($_POST['doj'] ?? '');
    $dob = $util->testInput($_POST['dob'] ?? '');
    $ename = $util->testInput($_POST['ename']);
    $eemail = $util->testInput($_POST['eemail']);
    $enumber = $util->testInput($_POST['enumber']);
    $epass = $util->testInput($_POST['epass']);
    $esalary = $util->testInput($_POST['esalary'] ?? 0);
    $etable = $util->testInput($_POST['etable'] ?? '');
    $emid = $util->testInput($_POST['emid'] ?? '');
    $amountO = $util->testInput($_POST['amountO'] ?? 0);
    $amountT = $util->testInput($_POST['amountT'] ?? 0);
    $amountTh = $util->testInput($_POST['amountTh'] ?? 0);
    $amountF = $util->testInput($_POST['amountF'] ?? 0);
    $amountFf = $util->testInput($_POST['amountFf'] ?? 0);
    $amountS = $util->testInput($_POST['amountS'] ?? 0);
    $project_name = $util->testInput($_POST['project_name'] ?? '');
    $D_project = $util->testInput($_POST['D_project'] ?? '');
    $user_type = $util->testInput($_POST['user_type'] ?? 'employee');
    $assign_user = $_POST['assign_user'] ?? '';
    $city = $util->testInput($_POST['city'] ?? '');
    $is_active = $util->testInput($_POST['is_active'] ?? 1);
    if ($db->emailExists($eemail, $id)) {
      echo $util->showMessage('danger', 'Email "' . htmlspecialchars($eemail) . '" is already registered to another employee. Each user must have a unique email address.');
    } elseif ($etable !== '' && $db->tablenameExists($etable, $id)) {
      echo $util->showMessage('danger', 'Unique ID "' . htmlspecialchars($etable) . '" is already used by another user. Please choose a different Unique ID.');
    } elseif ($db->update($id, $doj, $dob, $ename, $eemail, $enumber, 
    $epass, $esalary, $etable, $emid, $amountO, $amountT, 
    $amountTh, $amountF, $amountFf, $amountS, $project_name, 
    $D_project, $user_type, $assign_user, $is_active, $city)) {
      echo $util->showMessage('success', 'User updated successfully!');
    } else {
      echo $util->showMessage('danger', 'Could not update user. The email or Unique ID may already exist, or required data is invalid.');
    }
  }
  // Handle Delete User Ajax Request
  if (isset($_GET['delete'])) {
    $id = $_GET['id'];
    if ($db->delete($id)) {
      echo $util->showMessage('info', 'User deleted successfully!');
    } else {
      echo $util->showMessage('danger', 'Something went wrong!');
    }
  }
  // Get the user count function
  if (isset($_GET['active_users']) && $_GET['active_users'] == 1) {
    $result = $db->active_users(); // Call the read function
    // Prepare a response array with Active and Inactive counts
    $response = [
        'active' => 0,
        'inactive' => 0,
    ];
    foreach ($result as $row) {
        if ($row['is_active'] == 1) {
            $response['active'] = $row['user_count']; // Active users
        } else if ($row['is_active'] == 0) {
            $response['inactive'] = $row['user_count']; // Inactive users
        }
    }
    echo json_encode($response); // Send the result as a JSON response
}
if (isset($_POST['action']) && $_POST['action'] == 'update_advance_pay') {
    $id = $_POST['id'];
    $newAdvancePay = $_POST['newAdvancePay'];
    $db->updateAdvancePay($id, $newAdvancePay);
    echo "Advance Pay updated successfully.";
}

// Handle Update User Status (Activation Toggle)
if (isset($_POST['action']) && $_POST['action'] == 'update_user_status') {
    $id = $_POST['id'] ?? 0;
    $status = $_POST['status'] ?? 0;
    
    // Use database method for status update
    $success = $db->updateUserStatus($id, $status);
    
    if ($success) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => 'User status updated successfully.']);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Failed to update user status.']);
    }
    exit;
}

// Handle Update User Salary (Salary Structure Update)
if (isset($_POST['action']) && $_POST['action'] == 'update_user_salary') {
    $id = $_POST['id'] ?? 0;
    $salary = $_POST['salary'] ?? 0;
    $basic = $_POST['basic'] ?? 0;
    $hra = $_POST['hra'] ?? 0;
    $conveyance = $_POST['conveyance'] ?? 0;
    $special = $_POST['special'] ?? 0;
    $pf_employer = $_POST['pf_employer'] ?? 0;
    $deductions = $_POST['deductions'] ?? 0;
    
    // Update salary and components in accounts table
    $query = "UPDATE accounts SET 
                old_salary = salary, 
                salary = :salary,
                one_amt = :basic,
                two_amt = :hra,
                thrid_amt = :conveyance,
                forth_amt = :special,
                fifth_amt = :pf_employer,
                sixth_amt = :deductions
              WHERE id = :id";
    $stmt = $db->getConnection()->prepare($query);
    $success = $stmt->execute([
        'salary' => $salary, 
        'basic' => $basic,
        'hra' => $hra,
        'conveyance' => $conveyance,
        'special' => $special,
        'pf_employer' => $pf_employer,
        'deductions' => $deductions,
        'id' => $id
    ]);
    
    if ($success) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success', 'message' => 'Salary structure updated successfully.']);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Failed to update salary structure.']);
    }
    exit;
}
// --- Payroll AJAX Routes ---
// Export payroll CSV for a month
if (isset($_GET['export_payroll'])) {
    $month = trim((string) ($_GET['month'] ?? ''));
    if ($month === '') {
        http_response_code(400);
        exit('Month is required.');
    }

    $rows = $db->getPayrollByMonth($month);
    $safeMonth = preg_replace('/[^A-Za-z0-9 _-]/', '', $month);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="payroll_' . $safeMonth . '.csv"');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($output, ['ID', 'Employee', 'Emp Code', 'Base Salary', 'Present Days', 'Total Days', 'Deductions', 'Net Salary', 'Status', 'Month']);
    foreach ($rows as $row) {
        fputcsv($output, [
            $row['id'] ?? '',
            $row['employee_name'] ?? '',
            $row['emp_code'] ?? '',
            $row['base_salary'] ?? '',
            $row['present_days'] ?? '',
            $row['total_days'] ?? '',
            $row['deductions'] ?? '',
            $row['net_salary'] ?? '',
            $row['status'] ?? '',
            $row['month_year'] ?? $month,
        ]);
    }
    fclose($output);
    exit;
}

// Fetch Payroll Data for a specific month (JSON)
if (isset($_GET['fetch_payroll'])) {
    $month = $_GET['month'];
    header('Content-Type: application/json');
    $payroll = $db->getPayrollByMonth($month);
    echo json_encode($payroll);
    exit;
}
// Run Payroll for a specific month (Process all employees with REAL Attendance)
if (isset($_POST['run_payroll'])) {
    @set_time_limit(300);
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    $month = trim((string) ($_POST['month'] ?? ''));
    $payrollContext = $db->buildPayrollRunContext($month);
    if (!$payrollContext) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Invalid payroll month.']);
        exit;
    }

    $db->initializeUserPayslipsTable();
    $users = $db->readActiveForPayroll();
    $employeeIds = array_map(static fn($u) => (int) $u['id'], $users);
    $prefetchMaps = $db->prefetchPayrollAttendanceMaps($employeeIds, $payrollContext);
    $successCount = 0;

    foreach ($users as $user) {
        $attendanceMetrics = $db->getPayrollAttendanceMetrics((int) $user['id'], $month, $payrollContext, $prefetchMaps);
        $built = payroll_build_auto_payslip($user, $attendanceMetrics, $month);
        if (!$built) {
            continue;
        }

        $payrollSaved = $db->upsertPayroll($built['payroll']);
        $payslipSaved = $db->upsertUserPayslip(
            $built['user_payslip']['user_id'],
            $built['user_payslip']['month'],
            $built['user_payslip']['year'],
            $built['user_payslip']['net_pay'],
            $built['user_payslip']['payslip_data'],
            false
        );

        if ($payrollSaved && $payslipSaved) {
            $successCount++;
        }
    }

    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'success',
        'processed' => $successCount,
        'payslips_saved' => $successCount,
    ]);
    exit;
}
// Fetch single record for Payslip
if (isset($_GET['fetch_payslip_data'])) {
    $pid = $_GET['id'];
    header('Content-Type: application/json');
    $record = $db->getPayrollRecord($pid);
    if ($record) {
        $record['deduction_list'] = $db->getActiveDeductions($_SESSION['id'] ?? 0);
    }
    echo json_encode($record);
    exit;
}
// --- Company Asset AJAX Routes (Group D) ---
// Fetch All Assets from registry
if (isset($_GET['fetch_assets_list'])) {
    header('Content-Type: application/json');
    $search = trim((string) ($_GET['search'] ?? ''));
    $status = trim((string) ($_GET['status'] ?? ''));
    $type = trim((string) ($_GET['type'] ?? ''));
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $limit = min(500, max(10, (int) ($_GET['limit'] ?? 200)));
    $offset = ($page - 1) * $limit;
    $assets = $db->getAllAssets(
        $limit,
        $offset,
        $search !== '' ? $search : null,
        $status !== '' ? $status : null,
        $type !== '' ? $type : null
    );
    echo json_encode([
        'data' => $assets,
        'total' => $db->countAssets(
            $search !== '' ? $search : null,
            $status !== '' ? $status : null,
            $type !== '' ? $type : null
        ),
        'page' => $page,
        'limit' => $limit,
    ]);
    exit;
}
// Fetch Active Asset Assignments
if (isset($_GET['fetch_active_assignments'])) {
    header('Content-Type: application/json');
    $assignments = $db->getActiveAssignments();
    echo json_encode($assignments);
    exit;
}
// Fetch Active Asset Assignments for a specific User
if (isset($_GET['fetch_user_assets'])) {
    $uid = (int)$_GET['user_id'];
    header('Content-Type: application/json');
    $assignments = $db->getActiveAssignmentsByUser($uid);
    echo json_encode($assignments);
    exit;
}
// Add New Asset
if (isset($_POST['add_asset_action'])) {
    $data = [
        'name' => $util->testInput($_POST['asset_name']),
        'type' => $util->testInput($_POST['asset_type']),
        'sn'   => $util->testInput($_POST['serial_number'])
    ];
    if ($db->addAsset($data)) {
        echo $util->showMessage('success', 'Asset registered successfully!');
    } else {
        echo $util->showMessage('danger', 'Failed to register asset. Serial number might be duplicate.');
    }
    exit;
}
// Assign Asset to Employee
if (isset($_POST['assign_asset_action'])) {
    $aid   = (int)$_POST['asset_id'];
    $eid   = (int)$_POST['employee_id'];
    $date  = $util->testInput($_POST['assigned_date']);
    $notes = $util->testInput($_POST['notes']);
    if ($db->assignAsset($aid, $eid, $date, $notes)) {
        echo $util->showMessage('success', 'Asset assigned successfully!');
    } else {
        echo $util->showMessage('danger', 'Failed to assign asset.');
    }
    exit;
}
// Process Asset Return
if (isset($_POST['return_asset_action'])) {
    $id    = (int)$_POST['assignment_id'];
    $date  = $util->testInput($_POST['returned_date']);
    if ($db->returnAsset($id, $date)) {
        echo $util->showMessage('success', 'Asset returned successfully!');
    } else {
        echo $util->showMessage('danger', 'Failed to process return.');
    }
    exit;
}
// Fetch Employees List for Payslip Dropdown (JSON)
if (isset($_GET['fetch_users_json'])) {
    header('Content-Type: application/json');
    echo json_encode($db->getPayslipEmployeesDropdown());
    exit;
}
// Search Payslips History (JSON) — list rows + summary in one response
if (isset($_GET['search_payslips'])) {
    header('Content-Type: application/json');
    $eid = !empty($_GET['eid']) ? $_GET['eid'] : null;
    $month = !empty($_GET['month']) ? $_GET['month'] : null;
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $limit = min(200, max(10, (int) ($_GET['limit'] ?? 50)));
    $offset = ($page - 1) * $limit;
    $total = $db->countPayslips($month, $eid);
    echo json_encode([
        'data' => $db->searchPayslips($month, $eid, $limit, $offset),
        'total' => $total,
        'page' => $page,
        'limit' => $limit,
        'total_pages' => max(1, (int) ceil($total / $limit)),
        'summary' => $db->getSalarySummary($month, $eid),
    ]);
    exit;
}
// Fetch Salary Summary statistics (JSON)
if (isset($_GET['fetch_salary_summary'])) {
    header('Content-Type: application/json');
    $eid = !empty($_GET['eid']) ? $_GET['eid'] : null;
    $month = !empty($_GET['month']) ? $_GET['month'] : null;
    $summary = $db->getSalarySummary($month, $eid);
    echo json_encode($summary);
    exit;
}

// --- Live Location Tracking (Periodic) ---
if (isset($_POST['record_live_location'])) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $db->createLocationHistoryTable();
    $uid = $_SESSION['id'] ?? null;
    $lat = $_POST['latitude'] ?? null;
    $lng = $_POST['longitude'] ?? null;
    
    if ($uid && $lat && $lng) {
        $db->recordLocation($uid, $lat, $lng);
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid data or session']);
    }
    exit;
}

// --- Update Employee Attendance Logs (From Admin Panel Calendar) ---
if (isset($_POST['action']) && $_POST['action'] == 'update_attendance') {
    date_default_timezone_set('Asia/Kolkata');
    $id = $_POST['user_id'] ?? 0;
    $date = $_POST['date'] ?? '';
    $punch_in = $_POST['punch_in'] ?? null;
    $punch_out = $_POST['punch_out'] ?? null;
    $status = $_POST['status'] ?? 'Present';
    
    if (empty($punch_in)) {
        $punch_in = null;
    } else {
        $punch_in = date('H:i:s', strtotime($punch_in));
    }
    if (empty($punch_out)) {
        $punch_out = null;
    } else {
        $punch_out = date('H:i:s', strtotime($punch_out));
    }
    
    // Calculate total hours if both are provided
    $total_hours = 0.00;
    if ($punch_in && $punch_out) {
        $start = new DateTime($punch_in);
        $end = new DateTime($punch_out);
        $interval = $start->diff($end);
        $hours = $interval->h + ($interval->i / 60) + ($interval->s / 3600);
        
        $break_mins = 0;
        $res = $db->getConnection()->query("SELECT setting_value FROM hr_settings WHERE setting_key = 'break_time_minutes'");
        if ($row = $res->fetch(PDO::FETCH_ASSOC)) {
            $break_mins = (int)$row['setting_value'];
        }
        $total_hours = max(0, $hours - ($break_mins / 60));
    }
    
    // Check if the record already exists
    // Update both tables to keep them in sync
    // 1. Update/Insert into attendance_logs (legacy support)
    $query = "SELECT COUNT(*) FROM attendance_logs WHERE user_id = :uid AND punch_date = :pdate";
    $stmt = $db->getConnection()->prepare($query);
    $stmt->execute(['uid' => $id, 'pdate' => $date]);
    $exists = (int)$stmt->fetchColumn() > 0;
    
    if ($exists) {
        $query = "UPDATE attendance_logs SET punch_in = :pin, punch_out = :pout, total_hours = :hours, status = :status WHERE user_id = :uid AND punch_date = :pdate";
        $stmt = $db->getConnection()->prepare($query);
        $success = $stmt->execute([
            'pin' => $punch_in,
            'pout' => $punch_out,
            'hours' => $total_hours,
            'status' => $status,
            'uid' => $id,
            'pdate' => $date
        ]);
    } else {
        $query = "INSERT INTO attendance_logs (user_id, punch_date, punch_in, punch_out, total_hours, status, ip_address) VALUES (:uid, :pdate, :pin, :pout, :hours, :status, :ip)";
        $stmt = $db->getConnection()->prepare($query);
        $success = $stmt->execute([
            'uid' => $id,
            'pdate' => $date,
            'pin' => $punch_in,
            'pout' => $punch_out,
            'hours' => $total_hours,
            'status' => $status,
            'ip' => 'Admin Edited'
        ]);
    }

    // 2. Update user_attendance (new architecture ledger)
    $uaQuery = "SELECT history_json FROM user_attendance WHERE user_id = :uid";
    $uaStmt = $db->getConnection()->prepare($uaQuery);
    $uaStmt->execute(['uid' => $id]);
    $uaRow = $uaStmt->fetch(PDO::FETCH_ASSOC);
    
    $history_arr = [];
    $has_ua_row = false;
    if ($uaRow) {
        $has_ua_row = true;
        $history_arr = json_decode($uaRow['history_json'] ?? '', true) ?: [];
    }
    
    // Update date-keyed entry
    $history_arr[$date] = [
        'punch_in' => $punch_in,
        'punch_out' => $punch_out,
        'status' => $status,
        'latitude_in' => null,
        'longitude_in' => null,
        'latitude_out' => null,
        'longitude_out' => null,
        'ip_address' => 'Admin Edited',
        'total_hours' => $total_hours
    ];
    $history_json = json_encode($history_arr);
    
    $todayStr = date('Y-m-d');
    $is_today = ($date === $todayStr);
    
    if (!$has_ua_row) {
        if ($is_today) {
            $insertQuery = "INSERT INTO user_attendance (user_id, today_date, today_punch_in, today_punch_out, today_status, today_ip, today_total_hours, history_json) VALUES (:uid, :today_date, :pin, :pout, :status, 'Admin Edited', :hours, :history)";
            $insertStmt = $db->getConnection()->prepare($insertQuery);
            $successUa = $insertStmt->execute([
                'uid' => $id,
                'today_date' => $date,
                'pin' => $punch_in,
                'pout' => $punch_out,
                'status' => $status,
                'hours' => $total_hours,
                'history' => $history_json
            ]);
        } else {
            $insertQuery = "INSERT INTO user_attendance (user_id, history_json) VALUES (:uid, :history)";
            $insertStmt = $db->getConnection()->prepare($insertQuery);
            $successUa = $insertStmt->execute([
                'uid' => $id,
                'history' => $history_json
            ]);
        }
    } else {
        if ($is_today) {
            $updateQuery = "UPDATE user_attendance SET today_date = :today_date, today_punch_in = :pin, today_punch_out = :pout, today_status = :status, today_ip = 'Admin Edited', today_total_hours = :hours, history_json = :history WHERE user_id = :uid";
            $updateStmt = $db->getConnection()->prepare($updateQuery);
            $successUa = $updateStmt->execute([
                'today_date' => $date,
                'pin' => $punch_in,
                'pout' => $punch_out,
                'status' => $status,
                'hours' => $total_hours,
                'history' => $history_json,
                'uid' => $id
            ]);
        } else {
            $updateQuery = "UPDATE user_attendance SET history_json = :history WHERE user_id = :uid";
            $updateStmt = $db->getConnection()->prepare($updateQuery);
            $successUa = $updateStmt->execute([
                'history' => $history_json,
                'uid' => $id
            ]);
        }
    }
    
    header('Content-Type: application/json');
    if ($success) {
        echo json_encode(['status' => 'success', 'message' => 'Attendance updated successfully.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update attendance.']);
    }
    exit;
}

// --- Delete/Reset Employee Attendance Log ---
if (isset($_POST['action']) && $_POST['action'] == 'delete_attendance') {
    $id = $_POST['user_id'] ?? 0;
    $date = $_POST['date'] ?? '';
    
    // 1. Delete from legacy logs
    $query = "DELETE FROM attendance_logs WHERE user_id = :uid AND punch_date = :pdate";
    $stmt = $db->getConnection()->prepare($query);
    $success = $stmt->execute(['uid' => $id, 'pdate' => $date]);
    
    // 2. Delete/Reset in user_attendance JSON history
    $uaQuery = "SELECT history_json FROM user_attendance WHERE user_id = :uid";
    $uaStmt = $db->getConnection()->prepare($uaQuery);
    $uaStmt->execute(['uid' => $id]);
    $uaRow = $uaStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($uaRow) {
        $history_arr = json_decode($uaRow['history_json'] ?? '', true) ?: [];
        if (isset($history_arr[$date])) {
            unset($history_arr[$date]);
        }
        $history_json = json_encode($history_arr);
        
        $todayStr = date('Y-m-d');
        if ($date === $todayStr) {
            $updateQuery = "UPDATE user_attendance SET today_date = NULL, today_punch_in = NULL, today_punch_out = NULL, today_status = NULL, today_ip = NULL, today_total_hours = NULL, history_json = :history WHERE user_id = :uid";
        } else {
            $updateQuery = "UPDATE user_attendance SET history_json = :history WHERE user_id = :uid";
        }
        $updateStmt = $db->getConnection()->prepare($updateQuery);
        $successUa = $updateStmt->execute(['history' => $history_json, 'uid' => $id]);
    }
    
    header('Content-Type: application/json');
    if ($success) {
        echo json_encode(['status' => 'success', 'message' => 'Attendance log deleted successfully.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to delete attendance log.']);
    }
    exit;
}
?>