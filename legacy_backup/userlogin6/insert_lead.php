<?php
ob_start();
session_start();
require_once 'config.php';
require_once __DIR__ . '/wa_auto_send.php';  // Auto WhatsApp on new lead
header('Content-Type: application/json');
date_default_timezone_set('Asia/Kolkata');
$created_at = date('Y-m-d H:i:s');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $number = trim($_POST['number']);
    $emailInput = trim($_POST['email']);
    $email = filter_var($emailInput, FILTER_VALIDATE_EMAIL) ? $emailInput : 'noemail@gmail.com';
    $project = trim($_POST['project']);
    $leadsource = trim($_POST['leadsource']);
    $assign_to_user = $_SESSION['tablename'];
    $source = "$leadsource Lead Inserted By $assign_to_user";
    $location = trim($_POST['leadlocation']);

    $config = new Config();
    $conn = $config->getConnection();

    try {
        // Check if the same lead already exists for the same project
        $checkStmt = $conn->prepare("SELECT id FROM shi_upload_data 
                                     WHERE name = :name AND number = :number AND email = :email AND project = :project");
        $checkStmt->execute([
            ':name' => $name,
            ':number' => $number,
            ':email' => $email,
            ':project' => $project
        ]);

        if ($checkStmt->rowCount() > 0) {
            echo json_encode([
                'status' => 'error',
                'message' => '❌ Duplicate lead found: The same lead already exists for this project.'
            ]);
            exit;
        }

        // Insert the new lead
        $stmt = $conn->prepare("INSERT INTO shi_upload_data (name, number, email, project, assign_to_user, source_of_lead, created_at, location) 
                                VALUES (:name, :number, :email, :project, :assign_to_user, :source, :created_at, :location)");
        $stmt->execute([
            ':name' => $name,
            ':number' => $number,
            ':email' => $email,
            ':project' => $project,
            ':assign_to_user' => $assign_to_user,
            ':source' => $source,
            ':created_at' => $created_at,
            ':location' => $location
        ]);

        $id_of_shi_upload_data = $conn->lastInsertId();

        $stmt2 = $conn->prepare("INSERT INTO user_remarks (upload_data_id, user_unique_id, assign_project_name)
                                 VALUES (:upload_data_id, :user_unique_id, :assign_project_name)");
        $stmt2->execute([
            ':upload_data_id' => $id_of_shi_upload_data,
            ':user_unique_id' => $assign_to_user,
            ':assign_project_name' => $project
        ]);

        $id_of_user_remarks = $conn->lastInsertId();

        // Send the success response first so the UI can close the popup immediately.
        session_write_close();
        echo json_encode([
            'status' => 'success',
            'message' => '✅ Lead added successfully!'
        ]);

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            @ob_flush();
            @flush();
        }

        triggerAutoWhatsApp($conn, $id_of_user_remarks);
    } catch (PDOException $e) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Database Error: ' . $e->getMessage()
        ]);
    }

    exit;
}
