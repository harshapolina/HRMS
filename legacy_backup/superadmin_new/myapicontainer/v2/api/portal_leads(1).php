<?php
include '../../config.php';

// Initialize database connection
$config = new Config();
$db = $config->getConnection();

// Get the raw POST body and decode it
$data = json_decode(file_get_contents("php://input"), true);

// Validate required fields
$requiredFields = ['name', 'email', 'number', 'location', 'project'];
foreach ($requiredFields as $field) {
    if (empty($data[$field])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Missing field: $field"]);
        exit;
    }
}

// Get API-Key from headers
$headers = getallheaders();
if (!isset($headers['API-Key'])) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Missing API-Key in headers."]);
    exit;
}
$apiKey = $headers['API-Key'];

// Fetch lead_source using API-Key
$query = "SELECT lead_source FROM project_apis WHERE api_key = :api_key";
$stmt = $db->prepare($query);
$stmt->execute(['api_key' => $apiKey]);
$result = $stmt->fetch();

if (!$result) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Invalid API-Key."]);
    exit;
}

$leadSource = $result['lead_source'];
$projectName = $data['project'];
$defaultUser = "Vipul0001";
$originalApiKey = $headers['API-Key'];
$projectName = $data['project'];
$apiKeyn = md5($originalApiKey . '_' . $projectName); // Unique api key

// Insert into portal_leads_data (you can change this table name as needed)
try {
    $insertQuery = "INSERT INTO project_apis (project_name, api_key, lead_source, assign_user) 
                    VALUES (:project_name, :api_key, :lead_source, :assign_user)";
    $insertStmt = $db->prepare($insertQuery);
    $insertStmt->execute([
        'project_name' => $projectName,
        'api_key' => $apiKeyn,
        'lead_source' => $leadSource,
        'assign_user' => $defaultUser
    ]);
} catch (PDOException $e) {
    // If it's a duplicate entry error, continue silently
    if ($e->errorInfo[1] == 1062) {
        // Duplicate entry, ignore and continue
    } else {
        // For any other DB error, rethrow
        throw $e;
    }
}

// Validate API Key
$query = "SELECT * FROM project_apis WHERE project_name = :project_name AND lead_source = :lead_source";
$stmt = $db->prepare($query);
$stmt->execute([
    'project_name' => $projectName,
    'lead_source' => $leadSource
]);
$project = $stmt->fetch();

if (!$project) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Invalid API key"]);
    exit;
}

// $assign_users = explode(',', $project['assign_user']);
$assign_users = array_map('trim', explode(',', $project['assign_user']));
$last_assigned_user = $project['last_assigned_user'];
$current_user_index = array_search($last_assigned_user, $assign_users);

// Determine the next user in the round-robin assignment
$next_user_index = ($current_user_index === false || $current_user_index === count($assign_users) - 1) ? 0 : $current_user_index + 1;
$next_user = $assign_users[$next_user_index];
// Determine the next user in the round-robin assignment
// $next_user = $assign_users[($current_user_index === false || $current_user_index === count($assign_users) - 1) ? 0 : $current_user_index + 1];

// Fetch the assigned user's number
$userQuery = "SELECT phonenumber FROM accounts WHERE tablename = :tablename";
$userStmt = $db->prepare($userQuery);
$userStmt->execute(['tablename' => $next_user]);
$user = $userStmt->fetch();

// Add user fetch details to debug info
$debug_info["fetched_user"] = $user;

if (!$user || empty($user['phonenumber'])) {
    // Include debug info in the response for better analysis
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Assigned user's phone number not found.",
        "debug_info" => $debug_info
    ]);
}

$user_number = $user['phonenumber'];

// Extract payload data
$name = $data['name'] ?? '';
$email = $data['email'] ?? '';
$number = $data['number'] ?? '';
$location = $data['location'] ?? '';
$created_at = date("Y-m-d H:i:s");

try {
    // Start a transaction
    $db->beginTransaction();

    // Check for duplicate in `shi_upload_data`
    $checkDuplicateQuery = "SELECT id FROM shi_upload_data WHERE email = :email AND number = :number AND project = :project";
    $checkStmt = $db->prepare($checkDuplicateQuery);
    $checkStmt->execute([
        'email' => $email,
        'number' => $number,
        'project' => $project['project_name']
    ]);

    if ($checkStmt->rowCount() > 0) {
        // Record already exists
        http_response_code(409);
        echo json_encode(["status" => "error", "message" => "Duplicate lead detected."]);
        $db->rollBack(); // Rollback the transaction
        exit;
    }

    // Insert into `shi_upload_data` table
    $insertQuery1 = "INSERT INTO shi_upload_data (name, email, number, location, type, source_of_lead, assign_to_user, created_at, project)
                     VALUES (:name, :email, :number, :location, '3 BHK', :source_of_lead, :assign_to_user, :created_at, :project)";
    $stmt1 = $db->prepare($insertQuery1);
    $stmt1->execute([
        'name' => $name,
        'email' => $email,
        'number' => $number,
        'location' => $location,
        'source_of_lead' => $project['lead_source'],
        'assign_to_user' => $next_user,
        'created_at' => $created_at,
        'project' => $project['project_name']
    ]);

    // Get the last inserted ID
    $id_of_shi_upload_data = $db->lastInsertId();

    // Insert into `user_remarks` table
    $insertQuery2 = "INSERT INTO user_remarks (upload_data_id, user_unique_id, assign_project_name)
                     VALUES (:id_of_shi_upload_data, :assign_to_user, :project_name)";
    $stmt2 = $db->prepare($insertQuery2);
    $stmt2->execute([
        'id_of_shi_upload_data' => $id_of_shi_upload_data,
        'assign_to_user' => $next_user,
        'project_name' => $project['project_name']
    ]);

    // Update the last assigned user
    $updateQuery = "UPDATE project_apis SET last_assigned_user = :last_assigned_user WHERE project_name = :project_name AND lead_source = :source_of_lead";
    $updateStmt = $db->prepare($updateQuery);
    $updateStmt->execute([
        'last_assigned_user' => $next_user,
        'project_name' => $project['project_name'],
        'source_of_lead' => $project['lead_source']
    ]);
    
    // Google leads counter Start
    // Calculate the count of leads from `shi_upload_data`
    $leadCountQuery = "SELECT COUNT(*) AS lead_count FROM shi_upload_data 
                            WHERE project = :project_name AND source_of_lead = :source_of_lead";
                        $leadCountStmt = $db->prepare($leadCountQuery);
                        $leadCountStmt->execute([
                        'project_name' => $project['project_name'],
                        'source_of_lead' => $project['lead_source']
                        ]);
    $leadCount = $leadCountStmt->fetchColumn();

    // Update the count_form_leads column in the project_apis table
    $updateCountQuery = "UPDATE project_apis SET fb_form_leads = :lead_count 
                            WHERE project_name = :project_name AND lead_source = :source_of_lead";
                        $updateCountStmt = $db->prepare($updateCountQuery);
                        $updateCountStmt->execute([
                        'lead_count' => $leadCount,
                        'project_name' => $project['project_name'],
                        'source_of_lead' => $project['lead_source']
                        ]);
    // Google leads counter End

    // Fast2SMS API Intrigration Start
    $shortProjectName = strlen($project['project_name']) > 10 ? substr($project['project_name'], 0, 10) : $project['project_name'];
    $shortName = strlen($name) > 7 ? substr($name, 0, 7) : $name;
    $shortEmail = strlen($email) > 7 ? substr($email, 0, 8): $email;
    $fields = array(
        "sender_id" => "SHHOME",
        "message" => "Property- $shortProjectName. Name- $shortName, Mobile-.XXXXX., Email- $shortEmail
        Regards, SearchHomes india pvt ltd",
        "template_id" => "1207163731895114985",
        "entity_id" => "1201159178483176795",
        "route" => "dlt_manual",
        "numbers" => $user_number, // Corrected variable
    );
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => "https://www.fast2sms.com/dev/bulkV2",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => json_encode($fields),
        CURLOPT_HTTPHEADER => [
            "authorization: RKnKg7po5EXg8lVwwYLYnZHFcoBBHEqWKfh4juLfSuuuZCCbPj4nFjzsSnGV",
            "accept: */*",
            "cache-control: no-cache",
            "content-type: application/json"
        ],
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) {
        throw new Exception("Fast2SMS API Error: $err");
    }
    // Fast2SMS API Intrigration End

    // Commit the transaction
    $db->commit();

    echo json_encode(["status" => "success", "message" => "Lead successfully inserted."]);
} catch (Exception $e) {
    $db->rollBack();
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Failed to insert lead.", "error" => $e->getMessage()]);
}
exit
?>
