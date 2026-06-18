<?php
// leads_cron.php – Excel → Lead Insert → Round-Robin → Notification → SMS
// Enable full PHP error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<pre>Starting leads_cron.php...</pre>\n";
include '../../config.php';
require __DIR__ . '/../../../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;  // ✅ Reader alias
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;  // ✅ Writer alias


$config = new Config();
$db = $config->getConnection();

ini_set("max_execution_time", 0);
set_time_limit(0);

// Excel absolute server path
$excelPath = __DIR__ . "/lead984940883e9487t5rufdjbhiv9c0u348yfoilsj1.xlsx";

// Excel URL (ONLY FOR READING IF PUBLIC)
$excelUrl  = "https://www.searchhomesindia.in/superadmin_new/myapicontainer/v2/api/lead984940883e9487t5rufdjbhiv9c0u348yfoilsj1.xlsx";

// Manual round-robin users
$roundRobinUsers = [
    ["code" => "ashish2050", "phone" => "9632137046"],
    ["code" => "Susil2059", "phone" => "8050831884"],
    ["code" => "Chinmaya2071", "phone" => "9035721650"]
    // ["code" => "Rashid1197", "phone" => "7309823053"],
    // ["code" => "Ram2223", "phone" => "9035783244"],
    // ["code" => "Sachin2224", "phone" => "9035753058"],
    // ["code" => "manoj2005", "phone" => "7760122117"],
    // ["code" => "umar2011", "phone" => "7996247989"],
    // ["code" => "Vishnu2069", "phone" => "8951674853"],
    // ["code" => "Subrata2084", "phone" => "7259395320"],
    // ["code" => "Amal2087", "phone" => "9108772275"],
    // ["code" => "Avinash2044", "phone" => "9036854194"],
    // ["code" => "nagadeepika2096", "phone" => "8105593876"],
    // ["code" => "Kaviya2097", "phone" => "8105017766"],
    // ["code" => "Shahid2072", "phone" => "7795128902"],
    // ["code" => "Niten2061", "phone" => "8951731912"],
    // ["code" => "Sreyas2074", "phone" => "8971985638"],
    // ["code" => "Sumit2062", "phone" => "7004679293"]
];

// Track last assigned user in local file
$roundRobinFile = __DIR__ . "/last_user1.txt";
if (!file_exists($roundRobinFile)) file_put_contents($roundRobinFile, "0");

// read last index
$lastIndex = intval(file_get_contents($roundRobinFile));
$nextIndex = ($lastIndex + 1) % count($roundRobinUsers);

// assigned user
$assigned_user = $roundRobinUsers[$nextIndex]["code"];
$assigned_phone = $roundRobinUsers[$nextIndex]["phone"];

// save new index
file_put_contents($roundRobinFile, $nextIndex);

// Helper functions
function normalizePhoneNumber($raw) {
    $num = preg_replace('/\D/', '', $raw);
    if (preg_match('/^(?:91|0)?(\d{10})$/', $num, $m)) return $m[1];
    return $num;
}
function maskNumber($n) {
    return str_repeat("x", strlen($n)-3) . substr($n,-3);
}
function maskEmail($email) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return "xxx@xxx.com";
    [$u,$d] = explode("@",$email);
    return substr($u,0,3) . str_repeat("x", max(0,strlen($u)-3)) . "@"
        . substr($d,0,2) . str_repeat("x", max(0,strlen($d)-2));
}

// Read Excel (ONLY FIRST UNPROCESSED ROW)
$tmpFile = tempnam(sys_get_temp_dir(), "excel_");
file_put_contents($tmpFile, file_get_contents($excelUrl));

$reader = new XlsxReader();
$spreadsheet = $reader->load($tmpFile);
$sheet = $spreadsheet->getActiveSheet();

// Expected columns: A=name, B=number, C=email, D=project, E=lead_source, F=location
$highestRow = $sheet->getHighestRow();

$selectedRow = null;
for ($r = 2; $r <= $highestRow; $r++) {
    $status = trim((string)$sheet->getCell("G".$r)->getValue());
    if ($status !== "DONE") {
        $selectedRow = $r;
        break;
    }
}

if (!$selectedRow) {
    echo json_encode(["status" => "done", "message" => "No pending rows in Excel."]);
    exit;
}

// Extract row values
$name         = trim((string)$sheet->getCell("A".$selectedRow)->getValue());
$number_raw   = trim((string)$sheet->getCell("B".$selectedRow)->getValue());
$email        = trim((string)$sheet->getCell("C".$selectedRow)->getValue());
$project_name = trim((string)$sheet->getCell("D".$selectedRow)->getValue());
$lead_source  = trim((string)$sheet->getCell("E".$selectedRow)->getValue());
$location     = trim((string)$sheet->getCell("F".$selectedRow)->getValue());

$number = normalizePhoneNumber($number_raw);

if (!$number) {
    echo json_encode(["status" => "error", "message" => "Invalid phone number in row $selectedRow"]);
    exit;
}

// Time
date_default_timezone_set("Asia/Kolkata");
$created_at = date("Y-m-d H:i:s");


try {
    $db->beginTransaction();

    // Duplicate check
    $chk = $db->prepare("SELECT id FROM shi_upload_data WHERE number = :n AND project = :p");
    $chk->execute([":n"=>$number, ":p"=>$project_name]);

    if ($chk->rowCount() > 0) {
        $existing = $chk->fetch(PDO::FETCH_ASSOC);

        // increase lead_count
        $upd = $db->prepare("UPDATE shi_upload_data SET lead_count = lead_count + 1, updated_at = :u WHERE id = :id");
        $upd->execute([":u"=>$created_at, ":id"=>$existing["id"]]);

        // mark excel row as DONE
        $sheet->setCellValue("G".$selectedRow, "DONE");

        // FIXED: pass $spreadsheet
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($tmpFile);
        file_put_contents($excelPath, file_get_contents($tmpFile));

        $db->commit();
        echo json_encode(["status"=>"duplicate","message"=>"Duplicate updated"]);
        exit;
    }

    // Insert Lead
    $q1 = $db->prepare("
        INSERT INTO shi_upload_data
        (name,email,number,location,type,source_of_lead,assign_to_user,created_at,project,lead_count)
        VALUES (:name,:email,:number,:location,'3 BHK',:source,:assign,:created_at,:project,1)
    ");
    $q1->execute([
        ":name"=>$name,
        ":email"=>$email,
        ":number"=>$number,
        ":location"=>$location,
        ":source"=>$lead_source,
        ":assign"=>$assigned_user,
        ":created_at"=>$created_at,
        ":project"=>$project_name
    ]);

    $lead_id = $db->lastInsertId();

    // remarks
    $q2 = $db->prepare("
        INSERT INTO user_remarks (upload_data_id,user_unique_id,assign_project_name,created_at)
        VALUES (:id,:user,:project,:created_at)
    ");
    $q2->execute([
        ":id"=>$lead_id,
        ":user"=>$assigned_user,
        ":project"=>$project_name,
        ":created_at"=>$created_at
    ]);

    // lead count update
    $cnt = $db->prepare("SELECT COUNT(*) FROM shi_upload_data WHERE project = :p AND source_of_lead = :s");
    $cnt->execute([":p"=>$project_name, ":s"=>$lead_source]);
    $leadCount = $cnt->fetchColumn();

    $upd2 = $db->prepare("UPDATE project_apis SET fb_form_leads = :c WHERE project_name=:p AND lead_source=:s");
    $upd2->execute([":c"=>$leadCount,":p"=>$project_name,":s"=>$lead_source]);

    $db->commit();

} catch(Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    echo json_encode(["status"=>"error","message"=>$e->getMessage()]);
    exit;
}

/* -------------------- NOTIFICATION -------------------- */
$CRM_API_KEY_SERVER = "533f4175837e145064605e15e12c7273f98746fec7459b0168af9394a22c6efab6bba75cce18a3555250e473f4907d22aaae3e3f12e46dd8ef22fac38737c537";
$maskedNumber = maskNumber($number);
$maskedEmail  = maskEmail($email);

$notifyPayload = [
    "title" => "📌 New Lead for {$project_name}",
    "body"  => "👤 {$name}\n📞 {$maskedNumber}\n✉️ {$maskedEmail}\n\nFollow up.",
    "user_codes" => [$assigned_user],
    "url" => "https://searchhomesindia.in"
];

$ch = curl_init("https://notification.mnts.in/api/notify-users");
curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
curl_setopt($ch,CURLOPT_POST,true);
curl_setopt($ch,CURLOPT_HTTPHEADER,[
    "Authorization: Bearer $CRM_API_KEY_SERVER",
    "Content-Type: application/json"
]);
curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($notifyPayload));
$notifyResp = curl_exec($ch);
curl_close($ch);

/* -------------------- FAST2SMS -------------------- */
$fields = [
    "sender_id" => "SHHOME",
    "message" => "Property- $project_name. Name- $name, Mobile- XXXXX., Regards, SHI",
    "template_id" => "1207163731895114985",
    "entity_id" => "1201159178483176795",
    "route" => "dlt_manual",
    "numbers" => normalizePhoneNumber($assigned_phone),
];

$curl = curl_init();
curl_setopt_array($curl,[
    CURLOPT_URL => "https://www.fast2sms.com/dev/bulkV2",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => "POST",
    CURLOPT_POSTFIELDS => json_encode($fields),
    CURLOPT_HTTPHEADER => [
        "authorization: RKnKg7po5EXg8lVwwYLYnZHFcoBBHEqWKfh4juLfSuuuZCCbPj4nFjzsSnGV",
        "content-type: application/json"
    ]
]);
$smsResp = curl_exec($curl);
curl_close($curl);

/* -------------------- MARK ROW AS DONE -------------------- */
$sheet->setCellValue("G".$selectedRow, "DONE");

// FIXED: pass $spreadsheet
$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
$writer->save($tmpFile);
file_put_contents($excelPath, file_get_contents($tmpFile));

echo json_encode([
    "status"=>"success",
    "message"=>"Lead inserted",
    "lead_id"=>$lead_id,
    "assigned_user"=>$assigned_user
]);
?>
