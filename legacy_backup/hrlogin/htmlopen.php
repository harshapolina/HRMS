<?php
if (!isset($HR_WEB_BASE)) {
    require_once dirname(__FILE__) . '/hr_paths.inc.php';
}
$hr_web = rtrim(isset($HR_WEB_BASE) ? $HR_WEB_BASE : '', '/');
$hr_header_logo_href = ($HR_APP_BASE !== '' ? $HR_APP_BASE : '..') . '/superadmin/assets/dataimage/hlogo.png';
$hr_company_logo_url = $hr_header_logo_href;

// CSS and JS paths initialized from paths file
$hr_superadmin_css_href = isset($hr_superadmin_css_href) ? $hr_superadmin_css_href : 'assets/css/style_dashboard.css';
$hr_overrides_css_href = isset($hr_overrides_css_href) ? $hr_overrides_css_href : 'assets/css/hr_soft.css';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Hr Admin Dashboard</title>
    <link rel="shortcut icon" type="nobglogo.png" href="assets/images/nobglogo.png" alt="text">
    
    <!-- Core Dependencies (CDN) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css?v=1.2" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- UI Patterns & Layouts Labels -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css?v=1.2" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css?v=1.2" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <!-- Summernote Document Editor -->
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js"></script>
    
    <!-- Application Themes -->
    <link rel="stylesheet" href="assets/css/style_dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/unified_table_styles.css?v=<?php echo time(); ?>">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lexend+Deca:wght@100..900&family=Dancing+Script:wght@400..700&family=Mrs+Saint+Delafield&family=Pinyon+Script&display=swap" rel="stylesheet">

    <style>
        /* Fallback for missing loader.css */
        #loader {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(255,255,255,0.8); z-index: 10000;
            display: flex; justify-content: center; align-items: center;
        }
        .loading__ring {
            width: 40px; height: 40px; border: 4px solid #f3f3f3;
            border-top: 4px solid #0d9488; border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
    <script>window.HR_COMPANY_LOGO_URL = <?php echo json_encode($hr_company_logo_url); ?>;</script>
</head>
<body>