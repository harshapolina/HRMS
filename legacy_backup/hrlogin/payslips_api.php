<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
require_once __DIR__ . '/payroll_attendance_rules.php';
session_start();
if (!isset($_SESSION['loggedin'])) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit;
}
session_write_close();

require_once __DIR__ . '/includes/db_mysqli.php';
try {
    $conn = hr_mysqli_connect();
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => 'Connection failed.']);
    exit;
}

function payslips_api_ensure_schema(mysqli $conn): void
{
    static $ready = false;
    if ($ready) {
        return;
    }
    $ready = true;

    $conn->query("CREATE TABLE IF NOT EXISTS user_payslips (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        month INT NOT NULL,
        year INT NOT NULL,
        net_pay DECIMAL(10,2) NOT NULL,
        payslip_data LONGTEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY user_month_year (user_id, month, year)
    )");
    $conn->query("ALTER TABLE user_payslips ADD COLUMN IF NOT EXISTS is_mailed TINYINT(1) DEFAULT 0");
    $conn->query("CREATE TABLE IF NOT EXISTS company_holidays (
        id INT AUTO_INCREMENT PRIMARY KEY,
        holiday_date DATE NOT NULL,
        reason VARCHAR(255) NOT NULL,
        created_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_holiday_date (holiday_date)
    )");

    $colRes = $conn->query("SHOW COLUMNS FROM payroll WHERE Field = 'present_days'");
    if ($colRes && ($col = $colRes->fetch_assoc()) && stripos($col['Type'], 'int') !== false) {
        $conn->query("ALTER TABLE payroll MODIFY present_days DECIMAL(6,2) NOT NULL");
    }
}

payslips_api_ensure_schema($conn);

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$user_id = $_GET['user_id'] ?? $_POST['user_id'] ?? 0;

if (!$user_id) {
    echo json_encode(["status" => "error", "message" => "Missing user_id"]);
    exit;
}

if ($action === 'history') {
    // 1. Fetch from user_payslips (manual/detailed)
    $stmt = $conn->prepare("SELECT id, month, year, net_pay, created_at FROM user_payslips WHERE user_id = ? ORDER BY year DESC, month DESC");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $history = [];
    $seen = [];
    
    while ($row = $result->fetch_assoc()) {
        $row['source'] = 'user_payslips';
        $history[] = $row;
        $seen["{$row['month']}_{$row['year']}"] = true;
    }
    $stmt->close();
    
    // 2. Fetch from payroll (bulk)
    $payrollStmt = $conn->prepare("SELECT id, month_year, net_salary, created_at FROM payroll WHERE employee_id = ? ORDER BY created_at DESC");
    $payrollStmt->bind_param("i", $user_id);
    $payrollStmt->execute();
    $payrollRes = $payrollStmt->get_result();
    
    $monthNames = ["Jan" => 1, "Feb" => 2, "Mar" => 3, "Apr" => 4, "May" => 5, "Jun" => 6, "Jul" => 7, "Aug" => 8, "Sep" => 9, "Oct" => 10, "Nov" => 11, "Dec" => 12];
    
    while ($pRow = $payrollRes->fetch_assoc()) {
        $parts = explode(' ', $pRow['month_year']);
        if (count($parts) === 2) {
            $mStr = substr($parts[0], 0, 3);
            $monthVal = $monthNames[$mStr] ?? 0;
            $yearVal = (int)$parts[1];
            
            if ($monthVal > 0 && $yearVal > 0) {
                $key = "{$monthVal}_{$yearVal}";
                if (!isset($seen[$key])) {
                    $history[] = [
                        'id' => $pRow['id'],
                        'month' => $monthVal,
                        'year' => $yearVal,
                        'net_pay' => $pRow['net_salary'],
                        'created_at' => $pRow['created_at'],
                        'source' => 'payroll'
                    ];
                    $seen[$key] = true;
                }
            }
        }
    }
    $payrollStmt->close();
    
    // Sort combined history by year DESC, month DESC
    usort($history, function($a, $b) {
        if ($a['year'] != $b['year']) {
            return $b['year'] - $a['year'];
        }
        return $b['month'] - $a['month'];
    });
    
    echo json_encode(["status" => "success", "data" => $history]);
} 
elseif ($action === 'get_payslip') {
    $id = (int)($_GET['id'] ?? 0);
    $month = (int)($_GET['month'] ?? 0);
    $year = (int)($_GET['year'] ?? 0);
    $source = $_GET['source'] ?? '';

    $data = null;

    // Generated payslips (user_payslips) — skip when caller knows this row is bulk payroll
    if ($source !== 'payroll') {
        $row = null;
        if ($id > 0) {
            $stmt = $conn->prepare("SELECT payslip_data FROM user_payslips WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $id, $user_id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        } elseif ($month > 0 && $year > 0) {
            $stmt = $conn->prepare("SELECT payslip_data FROM user_payslips WHERE user_id = ? AND month = ? AND year = ?");
            $stmt->bind_param("iii", $user_id, $month, $year);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }

        if ($row && !empty($row['payslip_data'])) {
            $decoded = json_decode($row['payslip_data'], true);
            if (is_array($decoded) && (isset($decoded['earnings']) || isset($decoded['net_pay']))) {
                $data = $decoded;
            }
        }
    }

    // Bulk payroll records (older payslips)
    if ($data === null) {
        $payrollRow = null;
        if ($id > 0) {
            $pStmt = $conn->prepare("SELECT * FROM payroll WHERE id = ? AND employee_id = ?");
            $pStmt->bind_param("ii", $id, $user_id);
            $pStmt->execute();
            $payrollRow = $pStmt->get_result()->fetch_assoc();
            $pStmt->close();
        } elseif ($month > 0 && $year > 0) {
            $monthName = date('M', mktime(0, 0, 0, $month, 10));
            $monthYearStr = "$monthName $year";
            $pStmt = $conn->prepare("SELECT * FROM payroll WHERE employee_id = ? AND month_year = ?");
            $pStmt->bind_param("is", $user_id, $monthYearStr);
            $pStmt->execute();
            $payrollRow = $pStmt->get_result()->fetch_assoc();
            $pStmt->close();
        }

        if ($payrollRow) {
            $pParts = explode(' ', $payrollRow['month_year']);
            $pMonth = $month;
            $pYear = $year;
            if (count($pParts) === 2) {
                $monthNames = ["Jan" => 1, "Feb" => 2, "Mar" => 3, "Apr" => 4, "May" => 5, "Jun" => 6, "Jul" => 7, "Aug" => 8, "Sep" => 9, "Oct" => 10, "Nov" => 11, "Dec" => 12];
                $mStr = substr($pParts[0], 0, 3);
                $pMonth = $monthNames[$mStr] ?? $pMonth;
                $pYear = (int)$pParts[1];
            }

            $monthlyCTC = (float)$payrollRow['base_salary'];
            $factor = $payrollRow['total_days'] > 0 ? ($payrollRow['present_days'] / $payrollRow['total_days']) : 1;

            $accStmt = $conn->prepare("SELECT * FROM accounts WHERE id = ?");
            $accStmt->bind_param("i", $user_id);
            $accStmt->execute();
            $userAcc = $accStmt->get_result()->fetch_assoc();
            $accStmt->close();

            $hasManual = false;
            if ($userAcc) {
                $hasManual = (
                    (!empty($userAcc['one_amt']) && (float)$userAcc['one_amt'] > 0) ||
                    (!empty($userAcc['two_amt']) && (float)$userAcc['two_amt'] > 0) ||
                    (!empty($userAcc['thrid_amt']) && (float)$userAcc['thrid_amt'] > 0) ||
                    (!empty($userAcc['forth_amt']) && (float)$userAcc['forth_amt'] > 0) ||
                    (!empty($userAcc['fifth_amt']) && (float)$userAcc['fifth_amt'] > 0) ||
                    (!empty($userAcc['sixth_amt']) && (float)$userAcc['sixth_amt'] > 0)
                );
            }

            $pfEmployer = $hasManual ? (float)($userAcc['fifth_amt'] ?? 0) : 1800;
            $monthlyGross = $monthlyCTC - $pfEmployer;

            $basicBase = round($monthlyGross * 0.50);
            $hraBase = round($monthlyGross * 0.20);
            $convBase = round($monthlyGross * 0.07);
            $specBase = $monthlyGross - ($basicBase + $hraBase + $convBase);

            $customDeductions = $hasManual ? (float)($userAcc['sixth_amt'] ?? 0) : (1800 + 200 + 817);
            $pfEmployee = $hasManual ? $customDeductions : 1800;
            $ptEmployee = $hasManual ? 0 : 200;
            $medEmployee = $hasManual ? 0 : 817;
            $customDed = 0;
            if (!$hasManual) {
                $customDed = max(0, (float)$payrollRow['deductions'] - 2817);
            } else {
                $pfEmployee = 0;
                $ptEmployee = 0;
                $medEmployee = 0;
                $customDed = $customDeductions;
            }

            $presentDays = (float)$payrollRow['present_days'];
            $totalDays = (int)$payrollRow['total_days'];
            $lops = round(max(0, $totalDays - $presentDays), 2);

            $earnedBasic = round($basicBase * $factor);
            $earnedHra = round($hraBase * $factor);
            $earnedConv = round($convBase * $factor);
            $earnedSpec = round($specBase * $factor);

            $totalEarnings = $earnedBasic + $earnedHra + $earnedConv + $earnedSpec;
            $lopAmount = max(0, $monthlyGross - $totalEarnings);

            $data = [
                'month_year' => $payrollRow['month_year'],
                'month' => $pMonth,
                'year' => $pYear,
                'total_days' => $totalDays,
                'paid_days' => $presentDays,
                'lops' => $lops,
                'lop_amount' => $lopAmount,
                'earnings' => [
                    'basic' => $earnedBasic,
                    'hra' => $earnedHra,
                    'conveyance' => $earnedConv,
                    'special' => $earnedSpec,
                    'bonus' => 0
                ],
                'deductions' => [
                    'pf' => $pfEmployee,
                    'pt' => $ptEmployee,
                    'medical' => $medEmployee,
                    'custom' => $customDed
                ],
                'net_pay' => (float)$payrollRow['net_salary']
            ];
        }
    }

    if ($data !== null) {
        echo json_encode(["status" => "success", "data" => $data]);
    } else {
        echo json_encode(["status" => "error", "message" => "Payslip details not found for this period"]);
    }
}
elseif ($action === 'attendance_summary') {
    $month = (int)($_GET['month'] ?? date('m'));
    $year = (int)($_GET['year'] ?? date('Y'));
    
    // Fetch HR Settings
    $hr_settings = [];
    $res = $conn->query("SELECT setting_key, setting_value FROM hr_settings");
    while($r = $res->fetch_assoc()) $hr_settings[$r['setting_key']] = $r['setting_value'];

    // Fetch existing records from attendance_logs (source of truth)
    $start_date = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01";
    $end_date = date("Y-m-t", strtotime($start_date));

    $days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);

    $records = [];
    $stmt = $conn->prepare("SELECT status, punch_date FROM attendance_logs WHERE user_id = ? AND (punch_date BETWEEN ? AND ?)");
    if ($stmt) {
        $stmt->bind_param("iss", $user_id, $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $key = payroll_normalize_punch_date((string)$row['punch_date']);
            $records[$key] = strtolower($row['status']);
        }
        $stmt->close();
    }

    // Fetch approved leaves for this user in this month
    $approved_leaves = [];
    $leaveStmt = $conn->prepare("
        SELECT lr.start_date, lr.end_date, COALESCE(t.is_paid, 1) as is_paid
        FROM leave_requests lr
        JOIN leave_types t ON lr.leave_type_id = t.id
        WHERE lr.user_id = ? AND lr.status = 'Approved'
        AND (lr.start_date <= ? AND lr.end_date >= ?)
    ");
    if ($leaveStmt) {
        $leaveStmt->bind_param("iss", $user_id, $end_date, $start_date);
        $leaveStmt->execute();
        $leaveRes = $leaveStmt->get_result();
        while ($lrow = $leaveRes->fetch_assoc()) {
            $s_date = new DateTime(max($start_date, $lrow['start_date']));
            $e_date = new DateTime(min($end_date, $lrow['end_date']));
            while ($s_date <= $e_date) {
                $approved_leaves[$s_date->format('Y-m-d')] = (int)$lrow['is_paid'];
                $s_date->modify('+1 day');
            }
        }
        $leaveStmt->close();
    }

    // Fetch company holidays in this month (paid; holiday wins)
    $holidays = [];
    $hStmt = $conn->prepare("SELECT holiday_date, reason FROM company_holidays WHERE holiday_date BETWEEN ? AND ? ORDER BY holiday_date ASC");
    if ($hStmt) {
        $hStmt->bind_param("ss", $start_date, $end_date);
        $hStmt->execute();
        $hRes = $hStmt->get_result();
        while ($hRow = $hRes->fetch_assoc()) {
            $holidays[$hRow['holiday_date']] = $hRow['reason'];
        }
        $hStmt->close();
    }

    $metrics = payroll_calculate_attendance_metrics($month, $year, $records, $approved_leaves, $holidays, $hr_settings);

    echo json_encode([
        "status" => "success",
        "data"   => $metrics
    ]);
}
elseif ($action === 'save') {
    $month = $_POST['month'] ?? 0;
    $year = $_POST['year'] ?? 0;
    $net_pay = $_POST['net_pay'] ?? 0;
    $payslip_data = $_POST['payslip_data'] ?? '';
    
    if (!$month || !$year || !$payslip_data) {
        echo json_encode(["status" => "error", "message" => "Missing required fields"]);
        exit;
    }

    // Get user details for payroll table
    $userStmt = $conn->prepare("SELECT username FROM accounts WHERE id = ?");
    $userStmt->bind_param("i", $user_id);
    $userStmt->execute();
    $userRes = $userStmt->get_result();
    $userData = $userRes->fetch_assoc();
    $username = $userData['username'] ?? 'Unknown';
    $userStmt->close();

    $monthName = date('M', mktime(0, 0, 0, $month, 10));
    $monthYear = "$monthName $year";
    
    // 1. Save to user_payslips (detailed JSON for our generator/printer)
    $stmt = $conn->prepare("INSERT INTO user_payslips (user_id, month, year, net_pay, payslip_data) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE net_pay = ?, payslip_data = ?, updated_at = CURRENT_TIMESTAMP");
    $stmt->bind_param("iiidsds", $user_id, $month, $year, $net_pay, $payslip_data, $net_pay, $payslip_data);
    
    if (!$stmt->execute()) {
        echo json_encode(["status" => "error", "message" => "Failed to save detailed payslip: " . $stmt->error]);
        $stmt->close();
        exit;
    }
    $stmt->close();

    // 2. Save to payroll table (flat record for standard system reports)
    $monthName = date('M', mktime(0, 0, 0, $month, 10));
    $monthYear = "$monthName $year";
    
    $decodedData = json_decode($payslip_data, true);
    $baseSalary = (float)($decodedData['earnings']['basic'] ?? 0) + 
                  (float)($decodedData['earnings']['hra'] ?? 0) + 
                  (float)($decodedData['earnings']['conveyance'] ?? 0) + 
                  (float)($decodedData['earnings']['special'] ?? 0);
    $totalDays = (int)($decodedData['pay_denominator'] ?? $decodedData['working_days'] ?? $decodedData['total_days'] ?? 30);
    if (isset($decodedData['paid_days'])) {
        $presentDays = round((float)$decodedData['paid_days'], 2);
    } else {
        $presentDays = round(max(0, $totalDays - (float)($decodedData['lops'] ?? 0)), 2);
    }
    $totalDeductions = (float)($decodedData['deductions']['pf'] ?? 0) + 
                      (float)($decodedData['deductions']['pt'] ?? 0) + 
                      (float)($decodedData['deductions']['medical'] ?? 0) + 
                      (float)($decodedData['deductions']['custom'] ?? 0) + 
                      (float)($decodedData['lop_amount'] ?? 0);

    $payrollStmt = $conn->prepare("INSERT INTO payroll (employee_id, employee_name, month_year, base_salary, present_days, total_days, deductions, net_salary, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Processed') ON DUPLICATE KEY UPDATE employee_name = VALUES(employee_name), base_salary = VALUES(base_salary), present_days = VALUES(present_days), total_days = VALUES(total_days), deductions = VALUES(deductions), net_salary = VALUES(net_salary), status = 'Processed', created_at = CURRENT_TIMESTAMP");
    $payrollStmt->bind_param("issddidd", $user_id, $username, $monthYear, $baseSalary, $presentDays, $totalDays, $totalDeductions, $net_pay);
    
    if ($payrollStmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Payslip saved successfully"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Failed to save payroll record: " . $payrollStmt->error]);
    }
    $payrollStmt->close();
}
else {
    echo json_encode(["status" => "error", "message" => "Invalid action"]);
}

$conn->close();
?>
