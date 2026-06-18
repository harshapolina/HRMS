<?php
include '../../config.php';

header('Content-Type: application/json');

// Define your verify token
$VERIFY_TOKEN = "EAAQBjhe8rVQBOZCoU1uSH3lPbh9iVjpHYz2x5Rz5JZCQnFlmuUSp7sSKIWPRMXrCibhdFlWrXcDln9aYHUEPyK29WKbuZBiR9hu3m8xOfWxwkXxZABEdhf0JajN8QaICwrxgZCFUKu6l1TlZBOKB0cCGKNSfxLhGrWmB5x3v1SSBAyThlOrLF7UlndKuKNEh4OXWdKwzxB";

// Webhook Verification
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['hub_verify_token'], $_GET['hub_challenge'])) {
        if ($_GET['hub_verify_token'] === $VERIFY_TOKEN) {
            echo $_GET['hub_challenge'];
            exit;
        } else {
            http_response_code(403);
            echo json_encode(["status" => "error", "message" => "Invalid verify token"]);
            exit;
        }
    }
}

// Initialize database connection
$config = new Config();
$db = $config->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = json_decode(file_get_contents("php://input"), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid JSON payload"]);
        exit;
    }

    // Validate payload structure
    if (!isset($payload['entry'][0]['changes'][0]['value'])) {
        file_put_contents('invalid_payloads.log', json_encode($payload, JSON_PRETTY_PRINT), FILE_APPEND);
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid payload structure"]);
        exit;
    }

    $leadData = $payload['entry'][0]['changes'][0]['value'];
    $lead_id = $leadData['leadgen_id'] ?? '';
    $form_id = $leadData['form_id'] ?? '';
    $created_time = $leadData['created_time'] ?? '';

    // Validate form ID
    if (empty($form_id)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Missing form ID"]);
        exit;
    }

    // Fetch project API details
    $query = "SELECT * FROM project_apis WHERE fb_form_id = :form_id";
    $stmt = $db->prepare($query);
    $stmt->execute(['form_id' => $form_id]);
    $project = $stmt->fetch();

    if (!$project) {
        http_response_code(404);
        echo json_encode(["status" => "error", "message" => "No matching project found for this form ID"]);
        exit;
    }

    $assign_users = explode(',', $project['assign_user']);
    if (empty($assign_users)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "No users assigned to this campaign"]);
        exit;
    }

    $last_assigned_user = $project['last_assigned_user'];
    $current_user_index = array_search($last_assigned_user, $assign_users);
    $next_user = ($current_user_index === false || $current_user_index === count($assign_users) - 1) ? $assign_users[0] : $assign_users[$current_user_index + 1];

    // Extract field data
    $field_data = [];
    if (isset($leadData['field_data']) && is_array($leadData['field_data'])) {
        foreach ($leadData['field_data'] as $field) {
            $field_data[$field['name']] = $field['values'][0] ?? '';
        }
    }

    $name = $field_data['name'] ?? 'No Name';
    $email = $field_data['email'] ?? 'No Email';
    $number = $field_data['phone_number'] ?? 'No Number';
    $location = $field_data['location'] ?? 'No Location';
    $created_at = date("Y-m-d H:i:s");

    // Check for duplicate leads
    $duplicateQuery = "SELECT COUNT(*) FROM shi_upload_data WHERE email = :email AND project = :project";
    $stmt = $db->prepare($duplicateQuery);
    $stmt->execute(['email' => $email, 'project' => $project['project_name']]);
    if ($stmt->fetchColumn() > 0) {
        echo json_encode(["status" => "error", "message" => "Duplicate lead detected"]);
        exit;
    }

    try {
        $db->beginTransaction();

        // Insert into shi_upload_data
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

        // Insert into user_remarks
        $upload_id = $db->lastInsertId();
        $insertQuery2 = "INSERT INTO user_remarks (upload_data_id, user_unique_id, assign_project_name)
                         VALUES (:upload_id, :assign_user, :project)";
        $stmt2 = $db->prepare($insertQuery2);
        $stmt2->execute([
            'upload_id' => $upload_id,
            'assign_user' => $next_user,
            'project' => $project['project_name']
        ]);

        // Update last assigned user
        $updateQuery = "UPDATE project_apis SET last_assigned_user = :last_user WHERE fb_form_id = :form_id";
        $updateStmt = $db->prepare($updateQuery);
        $updateStmt->execute([
            'last_user' => $next_user,
            'form_id' => $form_id
        ]);

        $db->commit();

        echo json_encode(["status" => "success", "message" => "Facebook lead successfully processed"]);
    } catch (Exception $e) {
        $db->rollBack();
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Database operation failed", "error" => $e->getMessage()]);
    }
}
?>