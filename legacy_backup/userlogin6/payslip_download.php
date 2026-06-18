<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['loggedin'])) {
    http_response_code(401);
    echo 'Unauthorized';
    exit;
}

require_once 'config.php';

$userId = (int)($_SESSION['id'] ?? 0);
$month = (int)($_GET['month'] ?? 0);
$year = (int)($_GET['year'] ?? 0);
$source = trim((string)($_GET['source'] ?? ''));
$payslipId = (int)($_GET['id'] ?? 0);

if (!$userId || ((!$month || !$year) && $payslipId === 0)) {
    http_response_code(400);
    echo 'Missing or invalid parameters.';
    exit;
}

$config = new Config();
$conn = $config->getConnection();

$userStmt = $conn->prepare('SELECT username, employee_id, user_type, one_amt, two_amt, thrid_amt, forth_amt, fifth_amt, sixth_amt FROM accounts WHERE id = :uid');
$userStmt->execute(['uid' => $userId]);
$userRow = $userStmt->fetch();

$userName = $userRow['username'] ?? 'Employee';
$empCode = $userRow['employee_id'] ?? $userId;
$designation = $userRow['user_type'] ?? 'Employee';

$data = null;
$payrollRow = null;
$usePayrollRow = false;

$monthName = date('M', mktime(0, 0, 0, $month, 10));
$monthYear = $monthName . ' ' . $year;

if ($source === 'payroll' && $payslipId > 0) {
  $payrollStmt = $conn->prepare('SELECT * FROM payroll WHERE id = :pid AND employee_id = :uid LIMIT 1');
  $payrollStmt->execute(['pid' => $payslipId, 'uid' => $userId]);
  $payrollRow = $payrollStmt->fetch();
  if ($payrollRow) {
    $usePayrollRow = true;
    $monthYear = $payrollRow['month_year'] ?? $monthYear;
    $parts = explode(' ', $monthYear);
    if (count($parts) === 2) {
      $monthMap = ['Jan'=>1,'Feb'=>2,'Mar'=>3,'Apr'=>4,'May'=>5,'Jun'=>6,'Jul'=>7,'Aug'=>8,'Sep'=>9,'Oct'=>10,'Nov'=>11,'Dec'=>12];
      $mStr = substr($parts[0], 0, 3);
      $month = $monthMap[$mStr] ?? $month;
      $year = (int)$parts[1];
    }
  }
}

if (!$usePayrollRow) {
  if ($source === 'user_payslips' && $payslipId > 0) {
    $payslipStmt = $conn->prepare('SELECT payslip_data FROM user_payslips WHERE id = :pid AND user_id = :uid LIMIT 1');
    $payslipStmt->execute(['pid' => $payslipId, 'uid' => $userId]);
  } else {
    $payslipStmt = $conn->prepare('SELECT payslip_data FROM user_payslips WHERE user_id = :uid AND month = :m AND year = :y LIMIT 1');
    $payslipStmt->execute(['uid' => $userId, 'm' => $month, 'y' => $year]);
  }
  $payslipRow = $payslipStmt->fetch();
  if ($payslipRow && !empty($payslipRow['payslip_data'])) {
    $decoded = json_decode($payslipRow['payslip_data'], true);
    if (is_array($decoded)) {
      $data = $decoded;
    }
  }
}

if (!$data && !$usePayrollRow) {
  $payrollStmt = $conn->prepare('SELECT * FROM payroll WHERE employee_id = :uid AND month_year = :my LIMIT 1');
  $payrollStmt->execute(['uid' => $userId, 'my' => $monthYear]);
  $payrollRow = $payrollStmt->fetch();
  if ($payrollRow) {
    $usePayrollRow = true;
  }
}

if (!$data && !$usePayrollRow) {
  http_response_code(404);
  echo 'Payslip not found for this period.';
  exit;
}

$monthNames = ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
$period = $monthNames[$month] . ' ' . $year;

$basicAmount = 0.0;
$hraOtherAmount = 0.0;
$incentiveAmount = 0.0;
$pfAmount = 0.0;
$ptAmount = 0.0;
$medAmount = 0.0;
$totalE = 0.0;
$totalD = 0.0;
$netPay = 0.0;
$totalDaysVal = 0;
$lopDaysVal = 0;

if ($usePayrollRow && $payrollRow) {
  $base = (float)($payrollRow['base_salary'] ?? 0);
  $allow = (float)($payrollRow['allowances'] ?? 0);
  $inc = (float)($payrollRow['incentives'] ?? 0);
  $ded = (float)($payrollRow['deductions'] ?? 0);
  $presentDays = (int)($payrollRow['present_days'] ?? 0);
  $totalDays = (int)($payrollRow['total_days'] ?? 0);
  $basePro = $totalDays > 0 ? ($base / $totalDays) * $presentDays : 0;

  $hasManual = false;
  if ($userRow) {
    $hasManual = (
      (!empty($userRow['one_amt']) && (float)$userRow['one_amt'] > 0) ||
      (!empty($userRow['two_amt']) && (float)$userRow['two_amt'] > 0) ||
      (!empty($userRow['thrid_amt']) && (float)$userRow['thrid_amt'] > 0) ||
      (!empty($userRow['forth_amt']) && (float)$userRow['forth_amt'] > 0) ||
      (!empty($userRow['fifth_amt']) && (float)$userRow['fifth_amt'] > 0) ||
      (!empty($userRow['sixth_amt']) && (float)$userRow['sixth_amt'] > 0)
    );
  }

  $pfAmount = $hasManual ? (float)($userRow['fifth_amt'] ?? 0) : min(1800, round($base * 0.5 * 0.12));
  $ptAmount = 200;
  $medAmount = 817;

  $basicAmount = $basePro;
  $hraOtherAmount = $allow;
  $incentiveAmount = $inc;
  $totalE = $basicAmount + $hraOtherAmount + $incentiveAmount;
  $totalD = $ded;
  $netPay = (float)($payrollRow['net_salary'] ?? 0);
  $totalDaysVal = $totalDays;
  $lopDaysVal = max(0, $totalDays - $presentDays);
} else {
  $e = $data['earnings'] ?? [];
  $d = $data['deductions'] ?? [];

  $basicAmount = (float)($e['basic'] ?? 0);
  $hraOtherAmount = (float)($e['hra'] ?? 0) + (float)($e['conveyance'] ?? 0) + (float)($e['special'] ?? 0);
  $incentiveAmount = (float)($e['bonus'] ?? 0);
  $pfAmount = (float)($d['pf'] ?? 0);
  $ptAmount = (float)($d['pt'] ?? 0);
  $medAmount = (float)($d['medical'] ?? 0);
  $totalE = $basicAmount + $hraOtherAmount + $incentiveAmount;
  $totalD = $pfAmount + $ptAmount + $medAmount;
  $netPay = (float)($data['net_pay'] ?? 0);
  $totalDaysVal = (int)($data['total_days'] ?? 0);
  $lopDaysVal = (int)($data['lops'] ?? 0);
}

function fmt_money($val) {
    return number_format((float)$val, 2);
}

$filenameBase = preg_replace('/[^A-Za-z0-9_\-]/', '_', $userName . '_' . $period);
$auto = (int)($_GET['auto'] ?? 0) === 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Payslip <?php echo htmlspecialchars($period); ?></title>
  <style>
    body {
      font-family: Arial, Helvetica, sans-serif;
      background: #f8fafc;
      color: #1e293b;
      margin: 0;
      padding: 20px;
    }
    .page {
      max-width: 900px;
      margin: 0 auto;
    }
    .card {
      background: #fff;
      border: 1px solid #e2e8f0;
      border-radius: 12px;
      padding: 24px;
      box-shadow: 0 8px 30px rgba(15, 23, 42, 0.06);
    }
    .header {
      text-align: center;
      margin-bottom: 22px;
    }
    .header h1 {
      margin: 0;
      font-size: 24px;
      color: #0f172a;
    }
    .header p {
      margin: 6px 0 0;
      color: #64748b;
      font-size: 14px;
    }
    .info-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 12px;
      margin-bottom: 18px;
    }
    .info-box {
      border: 1px solid #e2e8f0;
      border-radius: 10px;
      padding: 10px 12px;
      font-size: 13px;
    }
    .section-title {
      font-size: 12px;
      font-weight: 700;
      text-transform: uppercase;
      color: #94a3b8;
      margin: 18px 0 10px;
    }
    .tables {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 18px;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 13px;
    }
    th, td {
      padding: 8px 10px;
      border-bottom: 1px solid #e2e8f0;
      text-align: left;
    }
    td.amount {
      text-align: right;
      font-weight: 600;
    }
    .totals {
      display: flex;
      justify-content: space-between;
      margin-top: 18px;
      padding-top: 12px;
      border-top: 1px dashed #e2e8f0;
      font-size: 14px;
      font-weight: 700;
    }
    .net-pay {
      margin-top: 20px;
      padding: 14px 16px;
      border: 1px solid #e2e8f0;
      border-radius: 10px;
      background: #f1f5f9;
      text-align: right;
      font-size: 16px;
      font-weight: 800;
    }
    .actions {
      display: flex;
      justify-content: flex-end;
      gap: 10px;
      margin-top: 16px;
    }

    .btn-download {
      border: none;
      background: #0d9488;
      color: #fff;
      padding: 10px 14px;
      border-radius: 8px;
      font-weight: 600;
      cursor: pointer;
    }
    .btn-download:hover {
      background: #0f766e;
    }
    @media (max-width: 640px) {
      .info-grid, .tables {
        grid-template-columns: 1fr;
      }
      .net-pay {
        text-align: left;
      }
      .actions {
        justify-content: flex-start;
      }
    }
  </style>
</head>
<body>
  <div class="page">
    <div class="card" id="payslipPdf">
      <div class="header">
        <h1>Search Homes India Pvt Ltd</h1>
        <p>Payslip for the period of <?php echo htmlspecialchars($period); ?></p>
      </div>

      <div class="info-grid">
        <div class="info-box"><strong>Employee Name:</strong> <?php echo htmlspecialchars($userName); ?></div>
        <div class="info-box"><strong>Designation:</strong> <?php echo htmlspecialchars($designation); ?></div>
        <div class="info-box"><strong>Total Days:</strong> <?php echo (int)$totalDaysVal; ?></div>
        <div class="info-box"><strong>Loss of Pay (Days):</strong> <?php echo (int)$lopDaysVal; ?></div>
      </div>

      <div class="section-title">Salary Details</div>
      <div class="tables">
        <div>
          <table>
            <thead>
              <tr><th>Earnings</th><th class="amount">Amount (Rs.)</th></tr>
            </thead>
            <tbody>
              <tr><td>Basic Salary</td><td class="amount"><?php echo fmt_money($basicAmount); ?></td></tr>
              <tr><td>HRA &amp; Other</td><td class="amount"><?php echo fmt_money($hraOtherAmount); ?></td></tr>
              <tr><td>Incentives</td><td class="amount"><?php echo fmt_money($incentiveAmount); ?></td></tr>
            </tbody>
          </table>
        </div>
        <div>
          <table>
            <thead>
              <tr><th>Deductions</th><th class="amount">Amount (Rs.)</th></tr>
            </thead>
            <tbody>
              <tr><td>Provident Fund</td><td class="amount"><?php echo fmt_money($pfAmount); ?></td></tr>
              <tr><td>Professional Tax</td><td class="amount"><?php echo fmt_money($ptAmount); ?></td></tr>
              <tr><td>Medical Benefit</td><td class="amount"><?php echo fmt_money($medAmount); ?></td></tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="totals">
        <div>Total Earnings: Rs. <?php echo fmt_money($totalE); ?></div>
        <div>Total Deductions: Rs. <?php echo fmt_money($totalD); ?></div>
      </div>

      <div class="net-pay">Net Pay: Rs. <?php echo fmt_money($netPay); ?></div>
    </div>

    <div class="actions"<?php echo $auto ? ' style="display:none;"' : ''; ?>>
      <button class="btn-download" type="button" onclick="downloadPayslip()">Download PDF</button>
    </div>
  </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
  <script>
    function downloadPayslip() {
      const element = document.getElementById('payslipPdf');
      const opt = {
        margin: 10,
        filename: 'Payslip_<?php echo htmlspecialchars($filenameBase); ?>.pdf',
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true },
        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
      };
      html2pdf().set(opt).from(element).save();
    }

    window.addEventListener('load', function () {
      if (window.html2pdf && <?php echo $auto ? 'true' : 'false'; ?>) {
        setTimeout(function () {
          downloadPayslip();
          setTimeout(function () {
            window.close();
          }, 1200);
        }, 200);
      }
    });
  </script>
</body>
</html>
