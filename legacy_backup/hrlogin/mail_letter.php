<?php
header('Content-Type: application/json');
session_start();
if (!isset($_SESSION['loggedin'])) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit;
}

// Load Composer's autoloader for PHPMailer
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dompdf\Dompdf;
use Dompdf\Options;

require_once __DIR__ . '/hr_paths.inc.php';
require_once __DIR__ . '/letter_helpers.php';

function resolveOfferLetterLogoDataUri() {
    $candidates = [
        dirname(__DIR__) . '/superadmin/assets/dataimage/hlogo.png',
        __DIR__ . '/assets/images/nobglogo.png',
        __DIR__ . '/assets/dataimage/nobglogo.png',
    ];
    foreach ($candidates as $path) {
        if (!is_readable($path)) {
            continue;
        }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = in_array($ext, ['jpg', 'jpeg'], true) ? 'image/jpeg' : 'image/png';
        return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
    }
    return null;
}

function stripOfferLetterFixedElements($html) {
    if (empty($html)) return $html;
    
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    
    $xpath = new DOMXPath($dom);
    
    // 1. Remove fixed repeating elements
    $classes = ['header-fixed', 'footer-fixed', 'letter-watermark'];
    foreach ($classes as $class) {
        $nodes = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' $class ')]");
        foreach ($nodes as $node) {
            $node->parentNode->removeChild($node);
        }
    }
    
    // 2. Convert table layout to clean divs to solve DOMPDF table row break limits
    $pageContentNodes = $xpath->query("//div[contains(concat(' ', normalize-space(@class), ' '), ' page-content ')]");
    if ($pageContentNodes->length > 0) {
        $newDoc = $dom->createElement('div');
        $newDoc->setAttribute('class', 'offer-letter-doc');
        
        foreach ($pageContentNodes as $pageContentNode) {
            $clonedNode = $pageContentNode->cloneNode(true);
            
            // If the ancestor table row has page-break-before, copy it to the div style
            $trNode = $pageContentNode->parentNode->parentNode;
            if ($trNode && $trNode->nodeName === 'tr') {
                $style = $trNode->getAttribute('style');
                if (strpos($style, 'page-break-before') !== false) {
                    $clonedNode->setAttribute('style', 'page-break-before: always;');
                }
            }
            
            $newDoc->appendChild($clonedNode);
        }
        return $dom->saveHTML($newDoc);
    }
    
    // Fallback: return the offer-letter-doc node fragment
    $docNodes = $xpath->query("//div[contains(concat(' ', normalize-space(@class), ' '), ' offer-letter-doc ')]");
    if ($docNodes->length > 0) {
        return $dom->saveHTML($docNodes->item(0));
    }
    
    return $dom->saveHTML();
}

function embedOfferLetterLogoForPdf($html) {
    $dataUri = resolveOfferLetterLogoDataUri();
    if (!$dataUri) {
        return $html;
    }
    $html = preg_replace_callback(
        '/<img\b(?=[^>]*\boffer-letter-logo\b)[^>]*>/i',
        function ($m) use ($dataUri) {
            $tag = $m[0];
            if (preg_match('/\ssrc\s*=\s*["\'][^"\']*["\']/i', $tag)) {
                return preg_replace('/\ssrc\s*=\s*["\'][^"\']*["\']/i', ' src="' . $dataUri . '"', $tag, 1);
            }
            return preg_replace('/<img\b/i', '<img src="' . $dataUri . '"', $tag, 1);
        },
        $html
    );
    return $html;
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

function getOfferLetterDompdfStyles() {
    return '
                body { font-family: DejaVu Sans, sans-serif; margin: 0; padding: 0; background: #fff; color: #333; font-size: 11px; line-height: 1.5; }
                @page { margin: 115px 45px 145px 45px; }
                .offer-letter-doc { }
                .offer-letter-logo { max-height: 52px; width: auto; display: block; }
                .offer-letter-logo--watermark { max-width: 380px; width: 65%; margin: 0 auto; opacity: 0.08; }
                .header-border { height: 2px; background: #115b82; margin-top: 8px; }
                .letter-title { text-align: center; font-size: 17px; font-weight: bold; text-decoration: underline; margin: 18px 0 22px; color: #115b82; }
                .company-info { text-align: right; font-size: 11px; line-height: 1.7; font-weight: 600; }
                .offer-letter-doc .content-body { font-size: 13px; text-align: justify; }
                .offer-letter-doc .content-body p { margin-bottom: 10px; }
                .footer-title { margin: 0; font-size: 20px; color: #222; font-weight: 800; }
                .footer-address { margin: 2px 0 8px; font-size: 12px; color: #444; }
                .footer-bottom-bar { background: #115b82; color: #fff; font-size: 10px; text-align: center; padding: 8px 12px; }
                .offer-letter-doc .letter-layout-table { width: 100%; border-collapse: collapse; }
                .offer-letter-doc .letter-layout-table td { border: none; vertical-align: top; padding: 0; }
                .header-fixed { position: fixed; top: -105px; left: 0; right: 0; height: 100px; background: #fff; z-index: 1000; padding: 0 12px; box-sizing: border-box; }
                .footer-fixed { position: fixed; bottom: -135px; left: 0; right: 0; height: 130px; background: #fff; z-index: 1000; }
                .letter-watermark { position: fixed; top: 38%; left: 0; right: 0; text-align: center; z-index: -1; opacity: 0.08; }
                .offer-letter-doc thead { display: table-header-group; }
                .offer-letter-doc tfoot { display: table-footer-group; }
                .offer-letter-doc .header-space { height: 120px; }
                .offer-letter-doc .footer-space { height: 130px; }
                .offer-letter-doc .page-content { padding: 0 4px; }
                .offer-letter-doc .salary-table { width: 100%; border-collapse: collapse; font-size: 10px; margin-top: 10px; }
                .offer-letter-doc .salary-table th, .offer-letter-doc .salary-table td { border: 1px solid #ccc; padding: 7px 8px; text-align: center; }
                .offer-letter-doc .salary-table td:first-child { text-align: left; font-weight: 600; }
                .offer-letter-doc .salary-table .category-row, .offer-letter-doc .salary-table .total-row { background: #004d80; color: #fff; font-weight: bold; }
                .offer-letter-doc .salary-table .category-row td { text-align: left; }
                .offer-letter-doc .sig-placeholder { display: none !important; }
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
                p { margin: 0 0 10px 0; }
                h2, h3, h4 { margin: 14px 0 8px 0; }
                ol { margin: 0 0 10px 18px; padding: 0; }
    ';
}

require_once __DIR__ . '/includes/db_mysqli.php';
try {
    $conn = hr_mysqli_connect();
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => 'Connection failed.']);
    exit;
}

$user_id = $_POST['user_id'] ?? '';
$document_type = $_POST['document_type'] ?? '';

if (empty($user_id) || empty($document_type)) {
    echo json_encode(["status" => "error", "message" => "Missing required fields."]);
    exit;
}

// Map document_type to human readable string
$docNames = [
    'appointment_letter' => 'Appointment Letter',
    'offer_letter' => 'Offer Letter',
    'no_dues_certificate' => 'No Dues Certificate',
    'relieving_letter' => 'Relieving Letter'
];
$docName = $docNames[$document_type] ?? 'Document';

// Get email
$stmt = $conn->prepare("SELECT useremail, username FROM accounts WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user || empty($user['useremail'])) {
    echo json_encode(["status" => "error", "message" => "User email not found."]);
    exit;
}

$to = $user['useremail'];
$userName = $user['username'];

// Get document content
$stmt = $conn->prepare("SELECT content FROM user_documents WHERE user_id = ? AND document_type = ?");
$stmt->bind_param("is", $user_id, $document_type);
$stmt->execute();
$result = $stmt->get_result();
$doc = $result->fetch_assoc();
$stmt->close();

if (!$doc || empty($doc['content'])) {
    echo json_encode(["status" => "error", "message" => "Document not generated yet."]);
    exit;
}

$content = $doc['content'];

// Mail content setup
$subject = "Your " . $docName;
if ($document_type === 'appointment_letter') {
    $message = "<html><body>";
    $message .= "<p>Dear " . htmlspecialchars($userName) . ",</p>";
    $message .= "<p>Please find attached your " . $docName . " as a PDF document.</p>";
    $message .= "<p>Best regards,<br/>HR Department</p>";
    $message .= "</body></html>";
} else {
    $message = "<html><body>";
    $message .= "<p>Dear " . htmlspecialchars($userName) . ",</p>";
    $message .= "<p>Please find your " . $docName . " below:</p>";
    $message .= "<hr/>";
    $message .= $content;
    $message .= "</body></html>";
}

// -------------------------------------------------------------
// Fetch GMAIL SMTP CONFIGURATION from database
// -------------------------------------------------------------
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
    echo json_encode(["status" => "error", "message" => "SMTP settings not configured. Please set them in HR Settings."]);
    exit;
}
// -------------------------------------------------------------

$mail = new PHPMailer(true);

try {
    // Server settings
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = $gmail_username;
    $mail->Password   = $gmail_app_password;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    // Recipients
    $mail->setFrom($gmail_username, 'HR Department');
    $mail->addAddress($to, $userName);

    // Content
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body    = $message;
    $mail->AltBody = strip_tags($message); // Plain text version for non-HTML mail clients

    // Generate PDF Attachment
    try {
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->setChroot([__DIR__, dirname(__DIR__)]);
        // Use print CSS so @media print rules in the saved letter match browser Print
        if ($document_type === 'offer_letter') {
            $options->setDefaultMediaType('print');
        }
        
        $dompdf = new Dompdf($options);
        
        $logoDataUri = resolveOfferLetterLogoDataUri();
        if ($document_type === 'offer_letter') {
            $content = stripOfferLetterFixedElements($content);
        }
        $content = stripSignaturePlaceholders($content);
        $content = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $content);
        $content = embedOfferLetterLogoForPdf(trim($content));
        $content = preprocessSignatureHtml($content);
        
        $headerHtml = '';
        $footerHtml = '';
        $watermarkHtml = '';
        
        if ($document_type === 'offer_letter') {
            $headerHtml = '
            <div class="header-fixed">
                <table style="width:100%; border-collapse:collapse; margin-top:12px;">
                    <tr>
                        <td style="vertical-align:bottom; border:none;">
                            ' . ($logoDataUri ? '<img class="offer-letter-logo" src="' . $logoDataUri . '" />' : '') . '
                        </td>
                        <td class="company-info" style="vertical-align:bottom; border:none;">
                            <span style="color:#115b82">&#9742;</span> +91 63600 16650<br>
                            <span style="color:#115b82">&#9993;</span> contact@searchhomesindia.com<br>
                            <span style="color:#115b82">&#127760;</span> www.searchhomesindia.com
                        </td>
                    </tr>
                </table>
                <div class="header-border"></div>
            </div>';
            
            $footerHtml = '
            <div class="footer-fixed">
                <svg width="100%" height="90" viewBox="0 0 1000 90" preserveAspectRatio="none" style="position:absolute; bottom:35px; left:0;">
                    <path d="M0,90 L0,40 Q80,60 150,90 Z" fill="#115b82"/>
                    <path d="M0,90 L0,70 Q60,85 110,90 Z" fill="#20a163"/>
                    <path d="M1000,90 L1000,20 Q880,60 750,90 Z" fill="#20a163"/>
                    <path d="M1000,90 L1000,50 Q900,80 820,90 Z" fill="#115b82"/>
                    <path d="M1000,90 L1000,85 Q940,100 890,90 Z" fill="#e63946"/>
                    <path d="M1000,90 L1000,105 Q965,115 930,90 Z" fill="#f5a623"/>
                </svg>
                <div style="text-align:center; position:relative; z-index:2; padding-bottom:42px;">
                    <h2 class="footer-title">Search Homes India Pvt. Ltd.</h2>
                    <p class="footer-address">No 280, 3rd Floor, 5th Main Rd, 6th Sector, HSR Layout Bengaluru, Karnataka 560102</p>
                </div>
                <div class="footer-bottom-bar">
                    &bull; CIN: U70109KA2015PTC084843 &nbsp;&nbsp;&nbsp; &bull; GSTIN: 29AAWCS6824M1Z9
                </div>
            </div>';
            
            $watermarkHtml = '
            <div class="letter-watermark">
                ' . ($logoDataUri ? '<img class="offer-letter-logo--watermark" src="' . $logoDataUri . '" />' : '') . '
            </div>';
        }
        
        $pdfExtraStyles = ($document_type === 'offer_letter') ? getOfferLetterDompdfStyles() : '
                body { font-family: DejaVu Sans, sans-serif; margin: 0; padding: 20px; }
                table { width: 100%; border-collapse: collapse; }
                td, th { padding: 8px; border: 1px solid #ddd; }
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
                hr { border-top: 1px dashed #ccc; margin: 30px 0; }
        ';
        $pdfHtml = "
        <html>
        <head>
            <style>
                $pdfExtraStyles
            </style>
        </head>
        <body>
            $content
            $headerHtml
            $footerHtml
            $watermarkHtml
        </body>
        </html>";
        
        file_put_contents(__DIR__ . '/debug_pdf.html', $pdfHtml);
        $dompdf->loadHtml($pdfHtml);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $pdfOutput = $dompdf->output();
        
        $pdfFilename = str_replace(' ', '_', $docName) . "_" . str_replace(' ', '_', $userName) . ".pdf";
        $mail->addStringAttachment($pdfOutput, $pdfFilename);
    } catch (Exception $pdfError) {
        // Log PDF error but continue sending email if possible, or handle as needed
    }

    $mail->send();
    echo json_encode(["status" => "success", "message" => "Mail sent successfully to " . htmlspecialchars($to)]);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => "Message could not be sent. Mailer Error: {$mail->ErrorInfo}"]);
}

$conn->close();
?>
