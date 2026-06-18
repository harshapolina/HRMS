<?php
session_start();
header('Content-Type: application/json');

// Check session validity
if (!isset($_SESSION['tablename']) || empty($_SESSION['tablename'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
    exit;
}

include 'config.php';
$config = new Config();
$conn = $config->getConnection();

$input = json_decode(file_get_contents('php://input'), true);
$remarkIds = $input['remark_ids'] ?? [];
$uploadIds = $input['upload_ids'] ?? [];

if (empty($remarkIds) && empty($uploadIds)) {
    echo json_encode(['status' => 'success', 'counts' => []]);
    exit;
}

try {
    $counts = [];

    // Prioritize remark_id lookups
    if (!empty($remarkIds)) {
        // filter out non-numeric
        $cleanRemarkIds = array_filter(array_map('intval', $remarkIds));
        if (!empty($cleanRemarkIds)) {
            $inClause = implode(',', $cleanRemarkIds);
            $stmt = $conn->query("SELECT id as remark_id, unread_wa_count FROM user_remarks WHERE id IN ($inClause)");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $counts['remark_' . $row['remark_id']] = (int)$row['unread_wa_count'];
            }
        }
    }

    // Fallback lookups for upload_data_id
    if (!empty($uploadIds)) {
        $cleanUploadIds = array_filter(array_map('intval', $uploadIds));
        if (!empty($cleanUploadIds)) {
            $inClause = implode(',', $cleanUploadIds);
            // using MAX/SUM to combine duplicates safely, although should be unique per project
            $stmt = $conn->query("SELECT upload_data_id, SUM(unread_wa_count) as unread_wa_count FROM user_remarks WHERE upload_data_id IN ($inClause) GROUP BY upload_data_id");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $counts['upload_' . $row['upload_data_id']] = (int)$row['unread_wa_count'];
            }
        }
    }

    echo json_encode(['status' => 'success', 'counts' => $counts]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
