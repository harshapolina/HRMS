<?php
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

header('Content-Type: application/json');
session_start();
if (!isset($_SESSION['loggedin'])) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit;
}

require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dompdf\Dompdf;
use Dompdf\Options;

session_write_close();
require_once __DIR__ . '/includes/db_mysqli.php';
try {
    $conn = hr_mysqli_connect();
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => 'Connection failed.']);
    exit;
}

$user_id = $_POST['user_id'] ?? '';
$payslip_id = $_POST['payslip_id'] ?? '';

if (empty($user_id) || empty($payslip_id)) {
    echo json_encode(["status" => "error", "message" => "Missing required fields."]);
    exit;
}

// Get user details
$stmt = $conn->prepare("SELECT useremail, username FROM accounts WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user || empty($user['useremail'])) {
    echo json_encode(["status" => "error", "message" => "User email not found."]);
    exit;
}

$to = $user['useremail'];
$userName = $user['username'];

// Get payslip data
$stmt = $conn->prepare("SELECT month, year, net_pay, payslip_data FROM user_payslips WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $payslip_id, $user_id);
$stmt->execute();
$payslip = $stmt->get_result()->fetch_assoc();
$stmt->close();

$data = [];
if ($payslip) {
    $data = json_decode($payslip['payslip_data'] ?? '{}', true) ?? [];
} else {
    // Fallback to payroll table
    $pStmt = $conn->prepare("SELECT * FROM payroll WHERE id = ? AND employee_id = ?");
    $pStmt->bind_param("ii", $payslip_id, $user_id);
    $pStmt->execute();
    $payrollRow = $pStmt->get_result()->fetch_assoc();
    $pStmt->close();
    
    if ($payrollRow) {
        $pParts = explode(' ', $payrollRow['month_year']);
        $pMonth = 0;
        $pYear = 0;
        if (count($pParts) === 2) {
            $monthNamesMap = ["Jan" => 1, "Feb" => 2, "Mar" => 3, "Apr" => 4, "May" => 5, "Jun" => 6, "Jul" => 7, "Aug" => 8, "Sep" => 9, "Oct" => 10, "Nov" => 11, "Dec" => 12];
            $mStr = substr($pParts[0], 0, 3);
            $pMonth = $monthNamesMap[$mStr] ?? 0;
            $pYear = (int)$pParts[1];
        }
        
        $monthlyCTC = (float)$payrollRow['base_salary'];
        $factor = $payrollRow['total_days'] > 0 ? ($payrollRow['present_days'] / $payrollRow['total_days']) : 1;
        
        // Check manual salary structure in accounts table
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
        
        $basicBase = $hasManual ? (float)($userAcc['one_amt'] ?? 0) : round($monthlyCTC * 0.5);
        $hraBase = $hasManual ? (float)($userAcc['two_amt'] ?? 0) : round($monthlyCTC * 0.2);
        $convBase = $hasManual ? (float)($userAcc['thrid_amt'] ?? 0) : round($monthlyCTC * 0.07);
        $pfEmployer = $hasManual ? (float)($userAcc['fifth_amt'] ?? 0) : min(1800, round(($monthlyCTC * 0.5) * 0.12));
        $monthlyGross = $monthlyCTC - $pfEmployer;
        $specBase = $hasManual ? (float)($userAcc['forth_amt'] ?? 0) : ($monthlyGross - ($basicBase + $hraBase + $convBase));
        
        $customDeductions = $hasManual ? (float)($userAcc['sixth_amt'] ?? 0) : ($pfEmployer + 200 + 817);
        
        $pfEmployee = $pfEmployer;
        $ptEmployee = 200;
        $medEmployee = 817;
        if (!$hasManual) {
            $customDed = (float)$payrollRow['deductions'] - ($pfEmployee + $ptEmployee + $medEmployee);
        } else {
            $customDed = $customDeductions - ($pfEmployee + $ptEmployee + $medEmployee);
        }
        if ($customDed < 0) {
            $customDed = 0;
        }
        
        $presentDays = (float)$payrollRow['present_days'];
        $totalDays = (int)$payrollRow['total_days'];
        $lops = round(max(0, $totalDays - $presentDays), 2);
        
        $earnedBasic = round($basicBase * $factor);
        $earnedHra = round($hraBase * $factor);
        $earnedConv = round($convBase * $factor);
        $earnedSpec = round($specBase * $factor);
        
        $totalEarnings = $earnedBasic + $earnedHra + $earnedConv + $earnedSpec;
        $lopAmount = $monthlyGross - $totalEarnings;
        
        $payslip = [
            'month' => $pMonth,
            'year' => $pYear,
            'net_pay' => (float)$payrollRow['net_salary']
        ];
        
        $data = [
            'month_year' => $payrollRow['month_year'],
            'total_days' => $totalDays,
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
            ]
        ];
    } else {
        echo json_encode(["status" => "error", "message" => "Payslip not found."]);
        exit;
    }
}

$monthNames = ["", "January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
$period = $monthNames[$payslip['month']] . " " . $payslip['year'];
$e = $data['earnings'] ?? [];
$d = $data['deductions'] ?? [];

$totalE = (float)($e['basic'] ?? 0) + (float)($e['hra'] ?? 0) + (float)($e['conveyance'] ?? 0) + (float)($e['special'] ?? 0) + (float)($e['bonus'] ?? 0);
$totalD = (float)($d['pf'] ?? 0) + (float)($d['pt'] ?? 0) + (float)($d['medical'] ?? 0) + (float)($d['custom'] ?? 0);
$lopAmt = (float)($data['lop_amount'] ?? 0);

// Construct HTML matching the print version as closely as possible
$message = "
<html>
<head>
<meta charset='UTF-8'>
<style>
    body { font-family: 'DejaVu Sans', sans-serif; padding: 20px; color: #333; line-height: 1.5; }
    .header { text-align: center; margin-bottom: 30px; }
    .header h1 { margin: 0; color: #005691; font-size: 28px; font-weight: 800; }
    .header p { margin: 5px 0; color: #666; font-size: 16px; }
    .hr-line { border-top: 2px solid #005691; margin: 15px 0; }
    .details-table { width: 100%; margin-bottom: 25px; border-collapse: collapse; }
    .details-table td { padding: 10px 12px; border: 1px solid #e2e8f0; font-size: 14px; }
    .section-title { font-size: 12px; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-bottom: 8px; padding: 0 5px; }
    .salary-table { width: 100%; border-collapse: collapse; margin-bottom: 25px; border: 1px solid #cbd5e1; border-radius: 4px; }
    .inner-table { width: 100%; border-collapse: collapse; }
    .inner-table td { padding: 8px 12px; border: 1px solid #e2e8f0; font-size: 13px; }
    .totals-row { background: #f8fafc; font-weight: 700; font-size: 14px; }
    .totals-row td { padding: 12px; border: 1px solid #cbd5e1; }
    .net-pay-box { margin-top: 30px; padding: 20px; border: 1px solid #e2e8f0; border-radius: 12px; background: #f8fafc; text-align: right; }
    .net-pay-box span { font-size: 22px; font-weight: bold; color: #1e293b; }
</style>
</head>
<body>
    <div class='header'>
        <h1>Search Homes India Pvt Ltd</h1>
        <p>Payslip for the period of " . $period . "</p>
    </div>
    <div class='hr-line'></div>
    <table class='details-table'>
        <tr>
            <td style='width: 50%;'><strong>Employee Name:</strong> " . htmlspecialchars($userName) . "</td>
            <td style='width: 50%;'><strong>Designation:</strong> Employee</td>
        </tr>
        <tr>
            <td><strong>Total Days:</strong> " . ($data['total_days'] ?? 30) . "</td>
            <td><strong>Loss of Pay (Days):</strong> " . ($data['lops'] ?? 0) . "</td>
        </tr>
    </table>
    <table style='width: 100%;'>
        <tr>
            <td style='width: 50%;'><div class='section-title'>Earnings</div></td>
            <td style='width: 50%;'><div class='section-title'>Deductions</div></td>
        </tr>
    </table>
    <table class='salary-table'>
        <tr>
            <td style='width: 50%; vertical-align: top; padding: 8px; border-right: 1px solid #cbd5e1;'>
                <table class='inner-table'>
                    <tr><td style='width: 60%;'>Basic</td><td style='text-align: right;'>₹" . number_format((float)$e['basic']) . "</td></tr>
                    <tr><td>HRA</td><td style='text-align: right;'>₹" . number_format((float)$e['hra']) . "</td></tr>
                    <tr><td>Conveyance</td><td style='text-align: right;'>₹" . number_format((float)$e['conveyance']) . "</td></tr>
                    <tr><td>Special Allowance</td><td style='text-align: right;'>₹" . number_format((float)$e['special']) . "</td></tr>
                    <tr><td>Bonus/Incentive</td><td style='text-align: right;'>₹" . number_format((float)$e['bonus']) . "</td></tr>
                </table>
            </td>
            <td style='width: 50%; vertical-align: top; padding: 8px;'>
                <table class='inner-table'>
                    <tr><td style='width: 60%;'>PF</td><td style='text-align: right;'>₹" . number_format((float)$d['pf']) . "</td></tr>
                    <tr><td>Professional Tax</td><td style='text-align: right;'>₹" . number_format((float)$d['pt']) . "</td></tr>
                    <tr><td>Medical Benefit</td><td style='text-align: right;'>₹" . number_format((float)$d['medical']) . "</td></tr>
                    <tr><td>Other Deductions</td><td style='text-align: right;'>₹" . number_format((float)$d['custom']) . "</td></tr>
                    " . ($lopAmt > 0 ? "<tr><td>LOP Deduction</td><td style='text-align: right; color: #ef4444;'>₹" . number_format((float)$lopAmt) . "</td></tr>" : "<tr><td style='border:none;'>&nbsp;</td><td style='border:none;'>&nbsp;</td></tr>") . "
                </table>
            </td>
        </tr>
        <tr class='totals-row'>
            <td style='border-right: 1px solid #cbd5e1; padding: 10px;'>
                <div style='display: flex; justify-content: space-between;'>
                    <span>Total Earnings:</span>
                    <span style='float: right;'>₹" . number_format((float)$totalE) . "</span>
                </div>
            </td>
            <td style='padding: 10px;'>
                <div style='display: flex; justify-content: space-between;'>
                    <span>Total Deductions:</span>
                    <span style='float: right;'>₹" . number_format((float)($totalD + $lopAmt)) . "</span>
                </div>
            </td>
        </tr>
    </table>
    <div class='net-pay-box'>
        <span>Net Pay: ₹" . number_format((float)$payslip['net_pay']) . "</span>
    </div>
    <p style='text-align: center; color: #777; margin-top: 40px; font-size: 11px;'>This is a computer-generated document and does not require a physical signature.</p>
</body>
</html>";

// Fetch SMTP settings
$settings = [];
$res = $conn->query("SELECT setting_key, setting_value FROM hr_settings WHERE setting_key IN ('gmail_username', 'gmail_app_password')");
if ($res) {
    while($row = $res->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}
$gmail_username = $settings['gmail_username'] ?? '';
$gmail_app_password = $settings['gmail_app_password'] ?? '';

if (empty($gmail_username) || empty($gmail_app_password)) {
    echo json_encode(["status" => "error", "message" => "SMTP settings not configured."]);
    exit;
}

$mail = new PHPMailer(true);

try {
    $mail->CharSet = 'UTF-8';
    $mail->isSMTP();

    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = $gmail_username;
    $mail->Password   = $gmail_app_password;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    $mail->setFrom($gmail_username, 'HR Department');
    $mail->addAddress($to, $userName);

    $mail->isHTML(true);
    $mail->Subject = "Payslip for " . $period . " - Search Homes India";
    $mail->Body    = $message;
    $mail->AltBody = strip_tags($message);

    // Generate PDF
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($message);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $pdfOutput = $dompdf->output();

    // Attach PDF
    $filename = "Payslip_" . str_replace(' ', '_', $period) . ".pdf";
    $mail->addStringAttachment($pdfOutput, $filename);

    $mail->send();
    
    echo json_encode(["status" => "success", "message" => "Payslip mailed successfully to " . $to]);
} catch (Throwable $e) {
    echo json_encode(["status" => "error", "message" => "Server Error: " . $e->getMessage() . " at line " . $e->getLine()]);
}

$conn->close();
