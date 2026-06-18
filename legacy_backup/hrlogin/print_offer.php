<?php
require_once 'db.php';
require_once 'util.php';
session_start();

if (!isset($_SESSION['loggedin']) || !isset($_GET['id'])) {
    exit('Unauthorized or Missing ID');
}

$db = new Database;
$offer = $db->getOfferLetter((int)$_GET['id']);

if (!$offer) {
    exit('Offer letter not found');
}

$today = date('d-m-Y');
$joining_date = date('d-m-Y', strtotime($offer['joining_date'] ?? date('Y-m-d')));
$monthly_salary = (float)$offer['monthly_salary'];
$annual_salary = $monthly_salary * 12;

// Dynamic Salary Components
$basic = round($monthly_salary * 0.5);
$hra = round($monthly_salary * 0.2);
$conveyance = round($monthly_salary * 0.07);
$pf_employer = min(1800, round(($basic) * 0.12));
$monthly_gross = $monthly_salary - $pf_employer;
$special = $monthly_gross - ($basic + $hra + $conveyance);
$pf_employee = $pf_employer;
$pt = 200;
$medical = 817;
$deductions = $pf_employee + $pt + $medical;
$net_pay = $monthly_gross - $deductions;

// Always use the static template for printing.
// Custom editor content (with signatures) is intentionally ignored here
// so the print output is always clean and consistent.
$custom_content = '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Offer Letter - <?= htmlspecialchars($offer['candidate_name']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-blue: #115b82;
            --accent-orange: #f5a623;
            --accent-red: #e63946;
            --accent-green: #20a163;
        }
        body { font-family: 'Inter', sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background: #eef2f3; }
        .page { width: 21cm; min-height: 29.7cm; padding: 0; margin: 1cm auto; background: white; box-shadow: 0 0 10px rgba(0,0,0,0.1); position: relative; overflow: hidden; }
        .page-content { padding: 1.5cm 2cm; padding-bottom: 3.5cm; } /* Space for footer */
        
        /* Header Customization */
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px; }
        .logo-container { display: flex; flex-direction: column; }
        .logo { font-weight: 800; line-height: 1; letter-spacing: -0.5px; }
        .logo-main { color: var(--primary-blue); font-size: 32px; display: flex; align-items: center; }
        .logo-house { color: var(--accent-green); position: relative; display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; margin: 0 -2px; }
        .logo-house i { font-size: 24px; position: relative; top: -1px; }
        .logo-sub { color: var(--accent-green); font-size: 14px; align-self: flex-end; font-weight: 700; letter-spacing: 2px; margin-top: -2px; }
        
        .company-info { text-align: right; font-size: 11px; color: #333; line-height: 1.8; font-weight: 600; }
        .company-info i { color: var(--primary-blue); margin-right: 5px; font-size: 12px; }
        .header-border { height: 2px; background: linear-gradient(to right, var(--primary-blue) 0%, var(--primary-blue) 75%, var(--accent-orange) 75%, var(--accent-orange) 88%, var(--accent-red) 88%, var(--accent-red) 100%); margin-top: 10px; margin-bottom: 30px; }
        
        .letter-title { text-align: center; font-size: 18px; font-weight: 800; text-decoration: underline; margin: 20px 0 30px 0; color: var(--primary-blue); }
        .date-section { text-align: left; font-weight: bold; margin-bottom: 10px; }
        .recipient { font-weight: bold; margin-bottom: 25px; line-height: 1.4; }
        
        .content-body { margin-bottom: 30px; font-size: 13.5px; text-align: justify; color: #1a1a1a; }
        .content-body p { margin-bottom: 15px; }
        .content-body strong { color: #000; }
        
        /* Salary Table */
        .salary-table { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 12px; }
        .salary-table th, .salary-table td { border: 1px solid #ddd; padding: 10px 12px; text-align: center; }
        .salary-table thead th { background: var(--primary-blue); color: white; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .salary-table .category-row { background: var(--primary-blue); color: white; font-weight: 700; text-align: left; }
        .salary-table .total-row { background: var(--primary-blue); color: white; font-weight: 700; }
        .salary-table td:first-child { text-align: left; font-weight: 600; }
        
        /* Header Customization */
        .header-fixed { position: absolute; top: 0; left: 0; width: 100%; z-index: 100; background: white; }
        
        /* Watermark Customization */
        .letter-watermark { position: absolute; top: 38%; left: 0; width: 100%; text-align: center; z-index: -1; opacity: 0.08; pointer-events: none; }
        
        /* Footer Customization */
        .footer-fixed { position: absolute; bottom: 0; left: 0; width: 100%; height: 140px; background: white; z-index: 100; display: flex; flex-direction: column; justify-content: flex-end; }
        .footer-bg-container { position: absolute; bottom: 0; left: 0; width: 100%; height: 100%; pointer-events: none; }
        .footer-content { position: relative; z-index: 10; text-align: center; padding-bottom: 45px; }
        .footer-title { margin: 0; font-size: 22px; color: #222; font-weight: 800; }
        .footer-address { margin: 2px 0; font-size: 12px; color: #444; font-weight: 600; }
        
        .footer-bottom-bar { background: var(--primary-blue); height: 35px; display: flex; align-items: center; justify-content: center; position: relative; z-index: 15; width: 100%; }
        .footer-tags { color: white; font-size: 10px; font-weight: 700; letter-spacing: 0.5px; }
        .footer-tags span { margin: 0 15px; display: inline-flex; align-items: center; }
        .footer-tags span::before { content: "\F287"; font-family: "bootstrap-icons"; margin-right: 6px; font-size: 8px; }

        @media print {
            body { background: none; margin: 0; padding: 0; }
            .page { margin: 0; box-shadow: none; width: 100%; border: none; overflow: visible; }
            .no-print { display: none; }
            .header-fixed { position: fixed; top: 0; left: 0; width: 100%; background: white; z-index: 1000; padding: 1cm 2cm 0 2cm; }
            .footer-fixed { position: fixed; bottom: 0; left: 0; width: 100%; background: white; z-index: 1000; height: 140px; }
            .header-space { height: 140px; }
            .footer-space { height: 150px; }
            thead { display: table-header-group; }
            tfoot { display: table-footer-group; }
            .letter-watermark { position: fixed; top: 38%; left: 0; width: 100%; text-align: center; z-index: -1; opacity: 0.08; }

            /* Custom Document Print support */
            .offer-letter-doc { padding-top: 0; padding-bottom: 0; }
            .offer-letter-doc thead { display: table-header-group; }
            .offer-letter-doc tfoot { display: table-footer-group; }
            .offer-letter-doc .header-space { height: 140px; }
            .offer-letter-doc .footer-space { height: 150px; }
        }

        .btn-print, .btn-pdf { position: fixed; right: 20px; padding: 12px 24px; border: none; border-radius: 50px; cursor: pointer; font-weight: 800; box-shadow: 0 10px 20px rgba(0,0,0,0.15); z-index: 1000; display: flex; align-items: center; gap: 8px; transition: all 0.3s ease; }
        .btn-print { top: 20px; background: var(--primary-blue); color: white; }
        .btn-pdf { top: 80px; background: var(--accent-red); color: white; }
        .btn-print:hover, .btn-pdf:hover { transform: translateY(-2px); box-shadow: 0 15px 25px rgba(0,0,0,0.2); }

        /* Custom Document and Summernote Support */
        .offer-letter-doc { font-family: 'Inter', sans-serif; line-height: 1.6; color: #333; position: relative; }
        
        .offer-letter-logo { max-height: 58px !important; width: auto !important; display: block !important; }
        .offer-letter-logo.offer-letter-logo--watermark { max-width: 420px !important; max-height: none !important; width: 70% !important; margin: 0 auto !important; opacity: 0.08 !important; display: block !important; }
        
        .offer-letter-doc .company-info { text-align: right; font-size: 11px; color: #333; line-height: 1.7; font-weight: 600; }
        .offer-letter-doc .header-border { height: 2px; background: linear-gradient(to right, #115b82 0%, #115b82 75%, #f5a623 75%, #f5a623 88%, #e63946 88%, #e63946 100%); margin-top: 8px; }

        .print-table, .letter-layout-table { width: 100%; border-collapse: collapse; }
        .letter-layout-table td { border: none; vertical-align: top; padding: 0; }
        .offer-letter-doc thead { display: table-header-group; }
        .offer-letter-doc tfoot { display: table-footer-group; }
        .offer-letter-doc .header-space { height: 140px; }
        .offer-letter-doc .footer-space { height: 150px; }
        .offer-letter-doc .page-content { padding: 1.5cm 2cm; padding-bottom: 3.5cm; }
        .offer-letter-doc .salary-table { width: 100%; border-collapse: collapse; font-size: 12px; margin-top: 10px; }
        .offer-letter-doc .salary-table th, .offer-letter-doc .salary-table td { border: 1px solid #ddd; padding: 10px 12px; text-align: center; }
        .offer-letter-doc .salary-table td:first-child { text-align: left; font-weight: 600; }
        .offer-letter-doc .salary-table .category-row, .offer-letter-doc .salary-table .total-row { background: var(--primary-blue); color: #fff; font-weight: bold; }
        .offer-letter-doc .salary-table .category-row td { text-align: left; }
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
            z-index: 10;
        }
        p { margin: 0 0 10px 0; }
        h2, h3, h4 { margin: 14px 0 8px 0; }
        ol { margin: 0 0 10px 18px; padding: 0; }
    </style>
</head>
<body>

<button class="btn-print no-print" onclick="window.print()"><i class="bi bi-printer-fill"></i> Print Offer Letter</button>
<button class="btn-pdf no-print" onclick="downloadOfferPDF()"><i class="bi bi-file-earmark-pdf-fill"></i> Download PDF</button>

<div class="page">
    <!-- Global Watermark -->
    <div class="letter-watermark">
        <img src="../superadmin/assets/dataimage/hlogo.png" alt="" class="offer-letter-logo offer-letter-logo--watermark">
    </div>

    <?php if (false): /* static template always used */ ?>
        <?= $custom_content ?>
    <?php else: ?>
    <table class="print-table" style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr><td><div class="header-space"></div></td></tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <div class="page-content">
                        <!-- First Page Content -->
                        <div class="letter-title">Offer Letter</div>
                        
                        <div class="date-section">Date: <?= $today ?></div>
                        <div class="recipient">
                            <strong>To,</strong><br>
                            <?= htmlspecialchars($offer['candidate_name']) ?>
                        </div>

                        <div class="content-body">
                            <p>We are pleased to offer you employment at Search Homes India Pvt Ltd. We believe your skills and background will be valuable assets to our team and contribute significantly to our success.</p>
                            
                            <p>As per our discussion, your position will be <strong><?= htmlspecialchars($offer['position']) ?></strong> with a fixed Annual Cost to Company (CTC) of <strong>INR <?= number_format($annual_salary) ?> LPA</strong>. Enclosed with this letter, you'll find our employee handbook, which outlines additional benefits, including Provident Fund (PF) and Insurance.</p>
                            
                            <p><strong>Probation Period</strong></p>
                            <p>You will be on a 90 days probationary period, during which the company reserves the right to terminate employment without notice or remuneration if your performance is not deemed satisfactory or you abscond / or as part of your employment you are expected to meet specific benchmark of Minimum 2 confirmed bookings within 60 days. Additionally, please note that no leave will be granted during the probationary period, and any absence will be considered as Loss of Pay (LOP).</p>
                            
                            <p><strong>Dress Code Guidelines</strong></p>
                            <ol style="margin-left: -20px;">
                                <li><strong>Business Casual:</strong> Acceptable for most office days. This includes collared shirts, blouses, trousers, skirts, and dresses.</li>
                                <li><strong>Formal Attire:</strong> On days when you have client meetings or special events, formal business attire is required. This includes suits, ties, blazers, formal skirts, and dresses.</li>
                                <li><strong>Inappropriate Attire:</strong> Please avoid casual wear like T-shirts, shorts, flip-flops, and any clothing with logos, slogans, or Graphics not aligned with our company's image.</li>
                            </ol>

                            <p><strong>Notice Period</strong></p>
                            <p>During your employment, a 15-day notice period is required by either party to terminate this contract. The notice period starts from the date your resignation letter is received by your manager. However, in case of a breach of company policy, the company may terminate the contract with immediate effect.</p>

                            <p><strong>Full & Final Settlement</strong></p>
                            <p>Any employee wishing to resign must communicate his intent in writing for acceptance by management. On acceptance of resignation and after serving notice period by employee, FNF and deductions settlements process is initiated after post last working day and final amount (includes the employees unpaid salary only), shall be credited to the respective Employees Bank Account within 30 to 45 after relieving.</p>
                            
                            <p>If you choose to accept this offer, please sign and return the enclosed copy of this letter in the provided self-addressed, stamped envelope. We are excited to welcome you to the Search Homes India family.</p>
                        </div>

                        <div class="closing" style="margin-top: 40px;">
                            <p style="font-weight:bold; margin-bottom: 40px;">Warm regards,</p>
                            <div style="display: flex; justify-content: space-between; align-items: flex-end;">
                                <div>
                                    <p style="font-weight:bold; margin-bottom: 0;">Shivali V Rai</p>
                                    <p style="margin:0; font-size:13px; font-weight: 600;">HR Manager</p>
                                    <p style="margin:0; font-size:13px; font-weight: 600;">Search Homes India Pvt Ltd</p>
                                </div>
                            </div>
                            
                            <div style="margin-top: 60px; display: flex; justify-content: space-between;">
                                <div>
                                    <div style="border-top: 1.5px solid #000; width: 220px; padding-top: 5px; font-weight: bold; font-size: 13px;">Employee Signature</div>
                                    <div style="font-size: 13px; margin-top: 10px; font-weight: bold;">Date:</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </td>
            </tr>
            <!-- Page Break for Annexure -->
            <tr style="page-break-before: always;">
                <td>
                    <div class="page-content" style="padding-top: 40px;">
                        <h3 style="text-align:center; text-decoration:underline; font-weight: 800; color: var(--primary-blue); margin-bottom: 10px;">ANNEXURE - A</h3>
                        <p style="text-align:center; font-weight:bold; font-size: 16px; margin-bottom:25px;"><?= htmlspecialchars($offer['candidate_name']) ?></p>
                        
                        <table class="salary-table">
                            <thead>
                                <tr>
                                    <th style="background: white; color: var(--primary-blue); font-size: 16px;">CTC</th>
                                    <th>Monthly CTC</th>
                                    <th>Yearly CTC</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="total-row">
                                    <td>Earning</td>
                                    <td><?= round($monthly_salary) ?></td>
                                    <td><?= round($annual_salary) ?></td>
                                </tr>
                                <tr><td>Basic</td><td><?= round($basic) ?></td><td><?= round($basic * 12) ?></td></tr>
                                <tr><td>HRA</td><td><?= round($hra) ?></td><td><?= round($hra * 12) ?></td></tr>
                                <tr><td>Conveyance Allowance</td><td><?= round($conveyance) ?></td><td><?= round($conveyance * 12) ?></td></tr>
                                <tr><td>Special Allowance</td><td><?= round($special) ?></td><td><?= round($special * 12) ?></td></tr>
                                <tr class="category-row"><td colspan="3">Statutory Benefit</td></tr>
                                <tr><td>PF (Employer Part)</td><td><?= round($pf_employer) ?></td><td><?= round($pf_employer * 12) ?></td></tr>
                                <tr class="total-row"><td>Monthly Gross</td><td><?= round($monthly_gross) ?></td><td><?= round($monthly_gross * 12) ?></td></tr>
                                <tr><td>PF (Employee Part)</td><td><?= round($pf_employee) ?></td><td><?= round($pf_employee * 12) ?></td></tr>
                                <tr><td>PT</td><td><?= round($pt) ?></td><td><?= round($pt * 12) ?></td></tr>
                                <tr><td>Medical Benefit</td><td><?= round($medical) ?></td><td><?= round($medical * 12) ?></td></tr>
                                <tr class="total-row"><td>Net Pay</td><td><?= round($net_pay) ?></td><td><?= round($net_pay * 12) ?></td></tr>
                            </tbody>
                        </table>
                        <p style="font-size: 11px; font-weight: 800; color: #444; margin-top: 15px;">Note: 1) Income Tax will be deducted as per the provision of Income Tax act 1961</p>
                    </div>
                </td>
            </tr>
        </tbody>
        <tfoot>
            <tr><td><div class="footer-space"></div></td></tr>
        </tfoot>
    </table>
    <?php endif; ?>

    <!-- Fixed Header -->
    <div class="header-fixed">
        <div class="header" style="padding: 0 2cm;">
            <div class="logo-container">
                <img src="../superadmin/assets/dataimage/hlogo.png" alt="Search Homes India" style="max-height: 58px; width: auto; display: block;">
            </div>
            <div class="company-info">
                <div><i class="bi bi-telephone-fill"></i> +91 63600 16650</div>
                <div><i class="bi bi-envelope-fill"></i> contact@searchhomesindia.com</div>
                <div><i class="bi bi-globe"></i> www.searchhomesindia.com</div>
            </div>
        </div>
        <div class="header-border" style="margin: 10px 2cm 0 2cm;"></div>
    </div>

    <!-- Fixed Footer -->
    <div class="footer-fixed">
        <div class="footer-bg-container">
            <svg width="100%" height="140" viewBox="0 0 1000 140" preserveAspectRatio="none" style="position: absolute; bottom: 0; left: 0;">
                <!-- Bottom Left Curves -->
                <path d="M0,140 L0,40 Q80,60 150,140 Z" fill="var(--primary-blue)" opacity="0.9"/>
                <path d="M0,140 L0,70 Q60,85 110,140 Z" fill="var(--accent-green)" opacity="0.9"/>
                
                <!-- Bottom Right Curves -->
                <path d="M1000,140 L1000,20 Q880,60 750,140 Z" fill="var(--accent-green)" opacity="0.9"/>
                <path d="M1000,140 L1000,50 Q900,80 820,140 Z" fill="var(--primary-blue)" opacity="0.9"/>
                <path d="M1000,140 L1000,85 Q940,100 890,140 Z" fill="var(--accent-red)" opacity="0.9"/>
                <path d="M1000,140 L1000,105 Q965,115 930,140 Z" fill="var(--accent-orange)" opacity="0.9"/>
            </svg>
        </div>
        <div class="footer-content">
            <h2 class="footer-title">Search Homes India Pvt. Ltd.</h2>
            <p class="footer-address">No 280, 3rd Floor, 5th Main Rd, 6th Sector, HSR Layout Bengaluru, Karnataka 560102</p>
        </div>
        <div class="footer-bottom-bar">
            <div class="footer-tags">
                <span>CIN: U70109KA2015PTC084843</span>
                <span>GSTIN: 29AAWCS6824M1Z9</span>
            </div>
        </div>
    </div>
</div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script>
        function downloadOfferPDF() {
            const element = document.querySelector('.page');
            const candidateName = "<?= addslashes($offer['candidate_name']) ?>";
            const opt = {
                margin: 0,
                filename: `Offer_Letter_${candidateName.replace(/\s+/g, '_')}.pdf`,
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { 
                    scale: 2, 
                    useCORS: true,
                    logging: false,
                    letterRendering: true,
                    scrollY: 0,
                    scrollX: 0
                },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
                pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }
            };

            const buttons = document.querySelectorAll('.no-print');
            buttons.forEach(b => b.style.display = 'none');

            html2pdf().set(opt).from(element).save().then(() => {
                buttons.forEach(b => b.style.display = '');
            });
        }
    </script>
</body>
</html>
