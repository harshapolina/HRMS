<?php
header('Content-Type: application/json');
session_start();
if (!isset($_SESSION['loggedin'])) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit;
}

require 'vendor/autoload.php';
require_once 'db.php';
require_once 'util.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dompdf\Dompdf;
use Dompdf\Options;

function resolveLogoBase64() {
    $candidates = [
        dirname(__DIR__) . '/superadmin/assets/dataimage/hlogo.png',
        __DIR__ . '/assets/images/nobglogo.png',
        __DIR__ . '/assets/dataimage/nobglogo.png',
    ];
    foreach ($candidates as $path) {
        if (!is_readable($path)) continue;
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = in_array($ext, ['jpg', 'jpeg'], true) ? 'image/jpeg' : 'image/png';
        return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
    }
    return null;
}

function parseFloat($val) {
    if ($val === null) return 0;
    return floatval(str_replace(',', '', (string)$val));
}

function preprocessSignatureHtml($content) {
    return preg_replace_callback('/<img\s+[^>]*+class=["\'][^"\']*signature-stamp[^"\']*["\'][^>]*+>/is', function($matches) {
        $imgTag = $matches[0];
        if (preg_match('/style=["\']([^"\']+)["\']/i', $imgTag, $styleMatch)) {
            $stylesStr = $styleMatch[1];
            $styles = [];
            foreach (explode(';', $stylesStr) as $part) {
                $part = trim($part);
                if (empty($part)) continue;
                $kv = explode(':', $part, 2);
                if (count($kv) === 2) {
                    $styles[strtolower(trim($kv[0]))] = trim($kv[1]);
                }
            }
            $position = $styles['position'] ?? '';
            $left = $styles['left'] ?? '';
            $top = $styles['top'] ?? '';
            
            if (!empty($left) || !empty($top) || strtolower($position) === 'relative' || strtolower($position) === 'absolute') {
                $wrapperStyles = [];
                $wrapperStyles[] = "display: inline-block";
                $wrapperStyles[] = "position: " . (!empty($position) ? $position : "relative");
                if (!empty($left)) $wrapperStyles[] = "left: " . $left;
                if (!empty($top)) $wrapperStyles[] = "top: " . $top;
                
                foreach (['margin', 'margin-top', 'margin-bottom', 'margin-left', 'margin-right'] as $marginKey) {
                    if (isset($styles[$marginKey])) {
                        $wrapperStyles[] = "$marginKey: " . $styles[$marginKey];
                    }
                }
                
                $imgStyles = [];
                foreach ($styles as $k => $v) {
                    if (in_array($k, ['position', 'left', 'top', 'margin', 'margin-top', 'margin-bottom', 'margin-left', 'margin-right'])) {
                        continue;
                    }
                    $imgStyles[] = "$k: $v";
                }
                
                $newImgStylesStr = implode('; ', $imgStyles);
                $cleanImgTag = preg_replace('/style=["\'][^"\']*["\']/i', 'style="' . $newImgStylesStr . '"', $imgTag);
                return '<span style="' . implode('; ', $wrapperStyles) . '">' . $cleanImgTag . '</span>';
            }
        }
        return $imgTag;
    }, $content);
}

try {
    $db = new Database();
    $util = new Util();

    $user_id = $_POST['user_id'] ?? '';
    if (empty($user_id)) {
        echo json_encode(["status" => "error", "message" => "Missing user ID."]);
        exit;
    }

    // 1. Fetch User Info
    $conn = $db->getConnection();
    $stmt = $conn->prepare("SELECT * FROM accounts WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user || empty($user['useremail'])) {
        echo json_encode(["status" => "error", "message" => "User or email not found."]);
        exit;
    }

    $userEmail = $user['useremail'];
    $userName = $user['username'];
    $userRole = $user['user_type'] ?: 'Employee';

    // 2. Fetch Exit Letters
    $letters = [];
    $docTypes = ['no_dues_certificate', 'relieving_letter'];
    foreach ($docTypes as $type) {
        $stmt = $conn->prepare("SELECT content FROM user_documents WHERE user_id = ? AND document_type = ?");
        $stmt->execute([$user_id, $type]);
        if ($row = $stmt->fetch()) {
            $letters[$type] = $row['content'];
        }
    }

    // 3. Fetch All Payslips
    $payslips = $db->searchPayslips(null, $user_id);

    // 4. Setup PDF Options
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    $logoBase64 = resolveLogoBase64();

    // 5. Prepare PHPMailer
    $mail = new PHPMailer(true);

    // Fetch SMTP settings
    $stmt = $conn->prepare("SELECT setting_key, setting_value FROM hr_settings WHERE setting_key IN ('gmail_username', 'gmail_app_password')");
    $stmt->execute();
    $settings = [];
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    $gmail_username = $settings['gmail_username'] ?? '';
    $gmail_app_password = $settings['gmail_app_password'] ?? '';

    if (empty($gmail_username) || empty($gmail_app_password)) {
        echo json_encode(["status" => "error", "message" => "SMTP settings not configured."]);
        exit;
    }

    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = $gmail_username;
    $mail->Password   = $gmail_app_password;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    $mail->setFrom($gmail_username, 'HR Department');
    $mail->addAddress($userEmail, $userName);
    $mail->isHTML(true);
    $mail->Subject = "Full & Final Settlement Documents - $userName";

    $mailBody = "
        <div style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <h2>Full & Final Settlement - $userName</h2>
            <p>Dear $userName,</p>
            <p>Please find attached the following documents related to your Full & Final settlement:</p>
            <ul>";
    
    if (isset($letters['no_dues_certificate'])) $mailBody .= "<li>No Dues Certificate</li>";
    if (isset($letters['relieving_letter'])) $mailBody .= "<li>Relieving Letter</li>";
    if (!empty($payslips)) $mailBody .= "<li>Past Payslips History</li>";
    
    $mailBody .= "
            </ul>
            <p>If you have any questions regarding your settlement or these documents, please feel free to reach out to the HR department.</p>
            <p>We wish you the very best in your future endeavors.</p>
            <br>
            <p>Best Regards,<br><strong>HR Department</strong><br>Search Homes India Pvt Ltd</p>
        </div>";
    
    $mail->Body = $mailBody;

    // Attach Letters
    foreach ($letters as $type => $content) {
        $content = preprocessSignatureHtml($content);
        $cleanType = ($type === 'no_dues_certificate') ? 'No_Dues_Certificate' : 'Relieving_Letter';
        
        $html = "
        <html>
        <head>
            <style>
                body { font-family: DejaVu Sans, sans-serif; font-size: 12px; margin: 40px; }
                .logo { max-height: 60px; margin-bottom: 20px; }
                .header-border { height: 2px; background: #115b82; margin-bottom: 30px; }
                .signature-container {
                    position: relative !important;
                    display: inline-block !important;
                    width: 0 !important;
                    height: 0 !important;
                    vertical-align: bottom !important;
                    overflow: visible !important;
                }
                .signature-stamp, img.signature-stamp {
                    position: absolute !important;
                    max-width: 220px !important;
                    height: auto !important;
                }
            </style>
        </head>
        <body>
            " . ($logoBase64 ? "<img src='$logoBase64' class='logo'>" : "") . "
            <div class='header-border'></div>
            <div>
                $content
            </div>
        </body>
        </html>";

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $pdfOutput = $dompdf->output();
        $mail->addStringAttachment($pdfOutput, "{$cleanType}_{$userName}.pdf");
    }

    // Attach Payslips
    foreach ($payslips as $ps) {
        $monthlyCTC = parseFloat($ps['base_salary'] ?? 0);
        $totalDays = (int)($ps['total_days'] ?? 30) ?: 30;
        $presentDays = (float)($ps['present_days'] ?? 0);
        $factor = ($totalDays > 0) ? ($presentDays / $totalDays) : 1;
        
        $hasManual = false;
        if ($user) {
            $hasManual = (
                (!empty($user['one_amt']) && (float)$user['one_amt'] > 0) ||
                (!empty($user['two_amt']) && (float)$user['two_amt'] > 0) ||
                (!empty($user['thrid_amt']) && (float)$user['thrid_amt'] > 0) ||
                (!empty($user['forth_amt']) && (float)$user['forth_amt'] > 0) ||
                (!empty($user['fifth_amt']) && (float)$user['fifth_amt'] > 0) ||
                (!empty($user['sixth_amt']) && (float)$user['sixth_amt'] > 0)
            );
        }

        $basicBase = $hasManual ? (float)($user['one_amt'] ?? 0) : round($monthlyCTC * 0.5);
        $hraBase = $hasManual ? (float)($user['two_amt'] ?? 0) : round($monthlyCTC * 0.2);
        $convBase = $hasManual ? (float)($user['thrid_amt'] ?? 0) : round($monthlyCTC * 0.07);
        $pfEmployer = $hasManual ? (float)($user['fifth_amt'] ?? 0) : min(1800, round(($monthlyCTC * 0.5) * 0.12));
        $monthlyGross = $monthlyCTC - $pfEmployer;
        $specBase = $hasManual ? (float)($user['forth_amt'] ?? 0) : ($monthlyGross - ($basicBase + $hraBase + $convBase));
        
        $customDeductions = $hasManual ? (float)($user['sixth_amt'] ?? 0) : ($pfEmployer + 200 + 817);

        $e_basic = round($basicBase * $factor);
        $e_hra = round($hraBase * $factor);
        $e_conv = round($convBase * $factor);
        $e_spec = round($specBase * $factor);
        $totalE = $e_basic + $e_hra + $e_conv + $e_spec;
        
        $d_pf = $pfEmployer;
        $d_pt = 200;
        $d_med = 817;
        
        if (!$hasManual) {
            $d_custom = (float)($ps['deductions'] ?? 0) - ($d_pf + $d_pt + $d_med);
        } else {
            $d_custom = $customDeductions - ($d_pf + $d_pt + $d_med);
        }
        if ($d_custom < 0) {
            $d_custom = 0;
        }
        $totalD = $d_pf + $d_pt + $d_med + $d_custom;

        $lopDays = $totalDays - $presentDays;
        
        $psHtml = "
        <html><head><style>
            body { font-family: DejaVu Sans, sans-serif; padding: 20px; font-size: 11px; }
            .header { text-align: center; margin-bottom: 20px; }
            .header h1 { color: #005691; font-size: 20px; margin: 0; }
            .details { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            .details td { border: 1px solid #ddd; padding: 6px; }
            .salary-table { width: 100%; border-collapse: collapse; }
            .salary-table td { border: 1px solid #ddd; padding: 5px; vertical-align: top; }
            .total-row { background: #f9f9f9; font-weight: bold; }
            .net-box { margin-top: 20px; padding: 10px; border: 1px solid #ddd; text-align: right; font-size: 14px; font-weight: bold; }
        </style></head><body>
        <div class='header'>
            <h1>Search Homes India Pvt Ltd</h1>
            <p>Payslip for {$ps['month_year']}</p>
        </div>
        <table class='details'>
            <tr><td><strong>Employee:</strong> $userName</td><td><strong>Designation:</strong> $userRole</td></tr>
            <tr><td><strong>Total Days:</strong> $totalDays</td><td><strong>Loss of Pay:</strong> $lopDays Days</td></tr>
        </table>
        <table class='salary-table'>
            <tr><th width='50%'>Earnings</th><th width='50%'>Deductions</th></tr>
            <tr>
                <td>
                    <table width='100%'>
                        <tr><td>Basic</td><td align='right'>₹" . number_format($e_basic) . "</td></tr>
                        <tr><td>HRA</td><td align='right'>₹" . number_format($e_hra) . "</td></tr>
                        <tr><td>Conveyance</td><td align='right'>₹" . number_format($e_conv) . "</td></tr>
                        <tr><td>Special Allowance</td><td align='right'>₹" . number_format($e_spec) . "</td></tr>
                        <tr class='total-row'><td>Total Earnings</td><td align='right'>₹" . number_format($totalE) . "</td></tr>
                    </table>
                </td>
                <td>
                    <table width='100%'>
                        <tr><td>PF</td><td align='right'>₹" . number_format($d_pf) . "</td></tr>
                        <tr><td>Professional Tax</td><td align='right'>₹" . number_format($d_pt) . "</td></tr>
                        <tr><td>Medical Benefit</td><td align='right'>₹" . number_format($d_med) . "</td></tr>
                        <tr><td>Other Deductions</td><td align='right'>₹" . number_format($d_custom) . "</td></tr>
                        <tr class='total-row'><td>Total Deductions</td><td align='right'>₹" . number_format($totalD) . "</td></tr>
                    </table>
                </td>
            </tr>
        </table>
        <div class='net-box'>Net Pay: ₹" . number_format($ps['net_salary'] ?? 0) . "</div>
        </body></html>";

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($psHtml);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $pdfOutput = $dompdf->output();
        $mail->addStringAttachment($pdfOutput, "Payslip_" . str_replace(' ', '_', $ps['month_year']) . ".pdf");
    }

    $mail->send();
    echo json_encode(["status" => "success", "message" => "Consolidated FNF documents sent successfully to $userEmail"]);

} catch (Throwable $e) {
    echo json_encode(["status" => "error", "message" => "Error: " . $e->getMessage()]);
}
