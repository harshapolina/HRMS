<?php
include '../config.php';
require '../../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Initialize database connection
$config = new Config();
$db = $config->getConnection();

// Validate and retrieve parameters
if (!isset($_GET['lead_source']) || !isset($_GET['project_name'])) {
    http_response_code(400);
    echo "Invalid or missing parameters.";
    exit;
}

$leadSource = $_GET['lead_source'];
$projectName = $_GET['project_name'];

try {
    // Fetch total lead counts
    $sqlTotal = "SELECT 
            ur.assign_project_name AS project_name,
            sud.source_of_lead,
            COUNT(*) AS total_leads,
            SUM(CASE WHEN ur.status = 'pending' THEN 1 ELSE 0 END) AS pending,
            SUM(CASE WHEN ur.status = 'Interested' THEN 1 ELSE 0 END) AS interested,
            SUM(CASE WHEN ur.status = 'follow up' THEN 1 ELSE 0 END) AS follow_up,
            SUM(CASE WHEN ur.status = 'rnr' THEN 1 ELSE 0 END) AS rnr,
            SUM(CASE WHEN ur.status = 'call back' THEN 1 ELSE 0 END) AS call_back,
            SUM(CASE WHEN ur.status = 'Not Interested' THEN 1 ELSE 0 END) AS not_interested,
            SUM(CASE WHEN ur.status = 'Fake' THEN 1 ELSE 0 END) AS fake,
            SUM(CASE WHEN ur.status = 'Fix Site Visit' THEN 1 ELSE 0 END) AS fix_site_visit,
            SUM(CASE WHEN ur.status = 'Converted' THEN 1 ELSE 0 END) AS converted,
            SUM(CASE WHEN ur.status = 'Not Connected' THEN 1 ELSE 0 END) AS not_connected
        FROM user_remarks ur
        JOIN shi_upload_data sud ON ur.upload_data_id = sud.id
        WHERE ur.assign_project_name = :project_name AND sud.source_of_lead = :source_of_lead";

    $stmtTotal = $db->prepare($sqlTotal);
    $stmtTotal->bindParam(':project_name', $projectName);
    $stmtTotal->bindParam(':source_of_lead', $leadSource);
    $stmtTotal->execute();
    $totalData = $stmtTotal->fetch(PDO::FETCH_ASSOC);

    // Fetch user-wise lead counts
    $sqlUserWise = "SELECT 
            ur.user_unique_id AS assigned_user,
            COUNT(*) AS total_leads,
            SUM(CASE WHEN ur.status = 'pending' THEN 1 ELSE 0 END) AS pending,
            SUM(CASE WHEN ur.status = 'Interested' THEN 1 ELSE 0 END) AS interested,
            SUM(CASE WHEN ur.status = 'follow up' THEN 1 ELSE 0 END) AS follow_up,
            SUM(CASE WHEN ur.status = 'rnr' THEN 1 ELSE 0 END) AS rnr,
            SUM(CASE WHEN ur.status = 'call back' THEN 1 ELSE 0 END) AS call_back,
            SUM(CASE WHEN ur.status = 'Not Interested' THEN 1 ELSE 0 END) AS not_interested,
            SUM(CASE WHEN ur.status = 'Fake' THEN 1 ELSE 0 END) AS fake,
            SUM(CASE WHEN ur.status = 'Fix Site Visit' THEN 1 ELSE 0 END) AS fix_site_visit,
            SUM(CASE WHEN ur.status = 'Converted' THEN 1 ELSE 0 END) AS converted,
            SUM(CASE WHEN ur.status = 'Not Connected' THEN 1 ELSE 0 END) AS not_connected
        FROM user_remarks ur
        JOIN shi_upload_data sud ON ur.upload_data_id = sud.id
        WHERE ur.assign_project_name = :project_name AND sud.source_of_lead = :source_of_lead
        GROUP BY ur.user_unique_id";

    $stmtUserWise = $db->prepare($sqlUserWise);
    $stmtUserWise->bindParam(':project_name', $projectName);
    $stmtUserWise->bindParam(':source_of_lead', $leadSource);
    $stmtUserWise->execute();
    $userData = $stmtUserWise->fetchAll(PDO::FETCH_ASSOC);

    if (!$totalData) {
        http_response_code(404);
        echo "No data found for the given parameters.";
        exit;
    }

    // Create the Excel file
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Add headers
    $sheet->setCellValue('A1', 'Project Name')
          ->setCellValue('B1', 'Lead Source')
          ->setCellValue('C1', 'Assigned User') 
          ->setCellValue('D1', 'Total Leads')
          ->setCellValue('E1', 'Pending')
          ->setCellValue('F1', 'Interested')
          ->setCellValue('G1', 'Follow Up')
          ->setCellValue('H1', 'RNR')
          ->setCellValue('I1', 'Call Back')
          ->setCellValue('J1', 'Not Interested')
          ->setCellValue('K1', 'Fake')
          ->setCellValue('L1', 'Fix Site Visit')
          ->setCellValue('M1', 'Converted')
          ->setCellValue('N1', 'Not Connected');

    $row = 2;

    // Add total data as the first row
    $sheet->setCellValue("A$row", $totalData['project_name'])
          ->setCellValue("B$row", $totalData['source_of_lead'])
          ->setCellValue("C$row", "All Users")
          ->setCellValue("D$row", $totalData['total_leads'])
          ->setCellValue("E$row", $totalData['pending'])
          ->setCellValue("F$row", $totalData['interested'])
          ->setCellValue("G$row", $totalData['follow_up'])
          ->setCellValue("H$row", $totalData['rnr'])
          ->setCellValue("I$row", $totalData['call_back'])
          ->setCellValue("J$row", $totalData['not_interested'])
          ->setCellValue("K$row", $totalData['fake'])
          ->setCellValue("L$row", $totalData['fix_site_visit'])
          ->setCellValue("M$row", $totalData['converted'])
          ->setCellValue("N$row", $totalData['not_connected']);

    $row++;

    // Add user-wise data
    foreach ($userData as $record) {
        $sheet->setCellValue("A$row", $totalData['project_name'])
              ->setCellValue("B$row", $totalData['source_of_lead'])
              ->setCellValue("C$row", $record['assigned_user'])
              ->setCellValue("D$row", $record['total_leads'])
              ->setCellValue("E$row", $record['pending'])
              ->setCellValue("F$row", $record['interested'])
              ->setCellValue("G$row", $record['follow_up'])
              ->setCellValue("H$row", $record['rnr'])
              ->setCellValue("I$row", $record['call_back'])
              ->setCellValue("J$row", $record['not_interested'])
              ->setCellValue("K$row", $record['fake'])
              ->setCellValue("L$row", $record['fix_site_visit'])
              ->setCellValue("M$row", $record['converted'])
              ->setCellValue("N$row", $record['not_connected']);
        $row++;
    }

    // Set HTTP headers for file download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="Lead_Report.xlsx"');
    header('Cache-Control: max-age=0');

    // Save and output the Excel file
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo "An error occurred: " . $e->getMessage();
    exit;
}
?>