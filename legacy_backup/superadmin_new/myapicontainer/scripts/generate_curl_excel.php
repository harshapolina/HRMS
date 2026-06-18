<?php
include '../config.php';
require '../../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$config = new Config();
$db = $config->getConnection();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid or missing project ID.");
}

$projectId = (int)$_GET['id'];

$query = "SELECT project_name, api_key, assign_user, lead_source FROM project_apis WHERE id = :id";
$stmt = $db->prepare($query);
$stmt->execute(['id' => $projectId]);
$project = $stmt->fetch();

if (!$project) {
    die("Project not found.");
}

$lead_source = strtolower(trim($project['lead_source']));
$portalSources = ['magicbricks ads', '99acres ads', 'housing.com ads'];

if (in_array($lead_source, $portalSources)) {
    $apiEndpoint = "https://www.searchhomesindia.in/superadmin_new/myapicontainer/v2/api/portal_leads";
    $payload = json_encode([
        "name" => "Test Name",
        "email" => "test@example.com",
        "number" => "1234567890",
        "location" => "Test Location",
        "project" => "Test Project",
        "subsource_of_lead" => "Sub Source of Leads"
    ], JSON_UNESCAPED_SLASHES);
} else {
    $apiEndpoint = "https://www.searchhomesindia.in/superadmin_new/myapicontainer/v2/api/googleads_leads";
    $payload = json_encode([
        "name" => "Test Name",
        "email" => "test@example.com",
        "number" => "1234567890",
        "location" => "Test Location",
        "subsource_of_lead" => "Sub Source of Leads"
    ], JSON_UNESCAPED_SLASHES);
}

$curlCommand = "curl -X POST {$apiEndpoint} "
             . "-H 'Content-Type: application/json' "
             . "-H 'API-Key: {$project['api_key']}' "
             . "-d '{$payload}'";

// Create Excel
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setCellValue('A1', 'Project Name')
      ->setCellValue('B1', 'API Key')
      ->setCellValue('C1', 'Assigned Users')
      ->setCellValue('D1', 'Lead Source')
      ->setCellValue('E1', 'cURL Command');

$sheet->setCellValue('A2', $project['project_name'])
      ->setCellValue('B2', $project['api_key'])
      ->setCellValue('C2', $project['assign_user'])
      ->setCellValue('D2', $project['lead_source'])
      ->setCellValue('E2', $curlCommand);

// Output file
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="API_Details.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
?>