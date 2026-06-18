<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start(); // buffer any accidental output
session_start();
require '../config.php';
header('Content-Type: application/json');
date_default_timezone_set('Asia/Kolkata');
$created_at = date('Y-m-d H:i:s');
$location = 'Bangaluru Karnataka';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $number = trim($_POST['number']);
    $email = trim($_POST['email']);
    $project = trim($_POST['project']);
    $assign_to_user = isset($_SESSION['tablename']) ? $_SESSION['tablename'] : 'UnknownUser';
    $source = "Inserted By $assign_to_user";

    $config = new Config();
    $conn = $config->getConnection();

    try {
        // Check for duplicate
        $checkStmt = $conn->prepare("SELECT id FROM shi_upload_data 
                                     WHERE name = :name AND number = :number AND email = :email AND project = :project");
        $checkStmt->execute([
            ':name' => $name,
            ':number' => $number,
            ':email' => $email,
            ':project' => $project
        ]);

        if ($checkStmt->rowCount() > 0) {
            $response = [
                'status' => 'error',
                'message' => '❌ Duplicate lead found: The same lead already exists for this project.'
            ];
        } else {
            // Insert the lead
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

            // Insert user remarks
            $stmt2 = $conn->prepare("INSERT INTO user_remarks (upload_data_id, user_unique_id, assign_project_name)
                                     VALUES (:upload_data_id, :user_unique_id, :assign_project_name)");
            $stmt2->execute([
                ':upload_data_id' => $id_of_shi_upload_data,
                ':user_unique_id' => $assign_to_user,
                ':assign_project_name' => $project
            ]);

            $response = [
                'status' => 'success',
                'message' => '✅ Lead added successfully!'
            ];
        }
    } catch (PDOException $e) {
        $response = [
            'status' => 'error',
            'message' => 'Database Error: ' . $e->getMessage()
        ];
    }

    // Capture any unexpected output
    $output = ob_get_clean();
    if (!empty($output)) {
        $response = [
            'status' => 'error',
            'message' => 'Unexpected output: ' . $output
        ];
    }

    // Send final JSON response
    echo json_encode($response);
    exit;
}
