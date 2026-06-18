<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');
if (!isset($_SESSION['loggedin'])) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit;
}

session_write_close();
require_once __DIR__ . '/includes/db_mysqli.php';
try {
    $conn = hr_mysqli_connect();
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => 'Connection failed.']);
    exit;
}

// Ensure table exists
$tableQuery = "CREATE TABLE IF NOT EXISTS user_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    document_type VARCHAR(50) NOT NULL,
    content LONGTEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY user_doc_type (user_id, document_type)
)";
$conn->query($tableQuery);

$user_id = $_POST['user_id'] ?? '';
$document_type = $_POST['document_type'] ?? 'appointment_letter';
$content = $_POST['content'] ?? '';

if (empty($user_id) || empty($content)) {
    echo json_encode(["status" => "error", "message" => "Missing required fields."]);
    exit;
}

$stmt = $conn->prepare("INSERT INTO user_documents (user_id, document_type, content) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE content = ?, updated_at = CURRENT_TIMESTAMP");
$stmt->bind_param("isss", $user_id, $document_type, $content, $content);

if ($stmt->execute()) {
    if ($document_type === 'offer_letter') {
        try {
            // Check if user already has an offer letter in offer_letters table
            $checkStmt = $conn->prepare("SELECT id FROM offer_letters WHERE user_id = ?");
            $checkStmt->bind_param("i", $user_id);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            $existingOffer = $checkResult->fetch_assoc();
            $checkStmt->close();

            // Fetch user details from accounts table
            $userStmt = $conn->prepare("SELECT username, useremail, phonenumber, salary, doj, project_name, user_type, assign_user FROM accounts WHERE id = ?");
            $userStmt->bind_param("i", $user_id);
            $userStmt->execute();
            $userResult = $userStmt->get_result();
            $userRow = $userResult->fetch_assoc();
            $userStmt->close();

            if ($userRow) {
                $candidate_name = $userRow['username'] ?? '';
                $email = $userRow['useremail'] ?? '';
                $phone = $userRow['phonenumber'] ?? '';
                $position = $userRow['user_type'] ?? '';
                $department = $userRow['project_name'] ?? '';
                $monthly_salary = (float)($userRow['salary'] ?? 0);
                $joining_date = $userRow['doj'] ?? null;
                $reporting_manager = $userRow['assign_user'] ?? '';
                
                if ($existingOffer) {
                    $updateStmt = $conn->prepare("UPDATE offer_letters SET candidate_name = ?, email = ?, phone = ?, position = ?, department = ?, monthly_salary = ?, joining_date = ?, reporting_manager = ? WHERE user_id = ?");
                    $updateStmt->bind_param("sssssdsdi", $candidate_name, $email, $phone, $position, $department, $monthly_salary, $joining_date, $reporting_manager, $user_id);
                    $updateStmt->execute();
                    $updateStmt->close();
                } else {
                    $status = 'Sent';
                    $insertStmt = $conn->prepare("INSERT INTO offer_letters (user_id, candidate_name, email, phone, position, department, monthly_salary, joining_date, reporting_manager, offer_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $insertStmt->bind_param("isssssdsss", $user_id, $candidate_name, $email, $phone, $position, $department, $monthly_salary, $joining_date, $reporting_manager, $status);
                    $insertStmt->execute();
                    $insertStmt->close();
                }
            }
        } catch (Exception $e) {
            // ignore
        }
    }
    echo json_encode(["status" => "success", "message" => "Document saved successfully."]);
} else {
    echo json_encode(["status" => "error", "message" => "Failed to save document."]);
}
$stmt->close();
$conn->close();
?>
