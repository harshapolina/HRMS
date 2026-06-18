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

// Ensure table exists so the SELECT query doesn't fail if this is the first call
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

$user_id = $_GET['user_id'] ?? '';
$document_type = $_GET['document_type'] ?? 'appointment_letter';

if (empty($user_id)) {
    echo json_encode(["status" => "error", "message" => "Missing user ID."]);
    exit;
}

$stmt = $conn->prepare("SELECT content FROM user_documents WHERE user_id = ? AND document_type = ?");
if ($stmt) {
    $stmt->bind_param("is", $user_id, $document_type);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        echo json_encode(["status" => "success", "data" => $row['content']]);
    } else {
        echo json_encode(["status" => "success", "data" => null]);
    }
    $stmt->close();
} else {
    echo json_encode(["status" => "success", "data" => null]);
}
$conn->close();
?>
