<?php
include '../config.php';

$config = new Config();
$conn = $config->getConnection();

// For JSON-based history fetching
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true); // get POSTed JSON

    if (isset($data['fetchHistory'])) {
        $id = $data['rowId'];
        $useruniqueId = $data['user_unique_id'];

        try {
            $query = "SELECT history, created_at, assigned_by FROM user_remarks WHERE upload_data_id = :id AND user_unique_id = :useruniqueId";
            $stmt = $conn->prepare($query);
            $stmt->bindValue(':id', $id);
            $stmt->bindValue(':useruniqueId', $useruniqueId);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            $query_leads = "SELECT name, number FROM shi_upload_data WHERE id = :id";
            $stmt_leads = $conn->prepare($query_leads);
            $stmt_leads->bindValue(':id', $id);
            $stmt_leads->execute();
            $result_leads = $stmt_leads->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                $history = isset($result['history']) ? json_decode($result['history'], true) : [];
                echo json_encode([
                    'status' => 'success',
                    'history' => $history,
                    'assignedDate' => $result['created_at'] ?? null,
                    'assignedBy' => $result['assigned_by'] ?? null,
                    'lead_user' => $result_leads['name'] ?? null,
                    'lead_number' => $result_leads['number'] ?? null
                ]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'No history data found']);
            }
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit; // STOP here
    }

    if (isset($data['fetchCallHistory'])) {
        $id = $data['rowId'];
        $useruniqueId = $data['user_unique_id'];

        try {
            $query = "SELECT call_history, created_at, assigned_by FROM user_remarks WHERE upload_data_id = :id AND user_unique_id = :useruniqueId";
            $stmt = $conn->prepare($query);
            $stmt->bindValue(':id', $id);
            $stmt->bindValue(':useruniqueId', $useruniqueId);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            $query_leads = "SELECT name, number FROM shi_upload_data WHERE id = :id";
            $stmt_leads = $conn->prepare($query_leads);
            $stmt_leads->bindValue(':id', $id);
            $stmt_leads->execute();
            $result_leads = $stmt_leads->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                $call_history = isset($result['call_history']) ? json_decode($result['call_history'], true) : [];
                echo json_encode([
                    'status' => 'success',
                    'history' => $call_history,
                    'assignedDate' => $result['created_at'] ?? null,
                    'assignedBy' => $result['assigned_by'] ?? null,
                    'lead_user' => $result_leads['name'] ?? null,
                    'lead_number' => $result_leads['number'] ?? null
                ]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'No call history found']);
            }
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }
}

// For HTML rows (no POST, normal table loading)
if (isset($_GET['upload_data_id']) && isset($_GET['type'])) {
    $uploadDataId = $_GET['upload_data_id'];
    $type = $_GET['type'];

    if ($type == 'status') {
        $sql = "SELECT user_remarks.user_unique_id, user_remarks.status, user_remarks.assign_project_name, user_remarks.upload_data_id, shi_upload_data.recording_url FROM user_remarks LEFT JOIN shi_upload_data ON user_remarks.upload_data_id = shi_upload_data.id WHERE user_remarks.upload_data_id = :upload_data_id";
    } elseif ($type == 'remarks') {
        $sql = "SELECT user_unique_id, remarks, assign_project_name, upload_data_id FROM user_remarks WHERE upload_data_id = :upload_data_id";
    }

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':upload_data_id', $uploadDataId);
    $stmt->execute();
    $userRemarks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($userRemarks) > 0) {
        foreach ($userRemarks as $remark) {
            $statusClass = '';
            if ($type == 'status') {
                switch ($remark['status']) {
                    case 'Pending':
                        $statusClass = 'status-pending';
                        break;
                    case 'Fake':
                        $statusClass = 'status-fake';
                        break;
                    case 'RNR':
                        $statusClass = 'status-rnr';
                        break;
                    case 'Call Back':
                        $statusClass = 'status-callback';
                        break;
                    case 'Already Booked':
                        $statusClass = 'status-booked';
                        break;
                    case 'Not Interested':
                        $statusClass = 'status-not-interested';
                        break;
                    case 'Interested':
                        $statusClass = 'status-interested';
                        break;
                    case 'Follow Up':
                        $statusClass = 'status-follow-up';
                        break;
                    case 'Fix Site Visit':
                        $statusClass = 'status-visit';
                        break;
                    case 'Site Visit Done':
                        $statusClass = 'status-visit-done';
                        break;
                    case 'Converted':
                        $statusClass = 'status-eoi-collected';
                        break;
                    case 'Not Connected':
                        $statusClass = 'not-connected';
                        break;
                    default:
                        $statusClass = '';
                        break;
                }
            }

            echo "<tr>
                    <td data-label='User Unique ID'>{$remark['user_unique_id']}</td>;
                    <td data-label='Project name'>{$remark['assign_project_name']}</td>";
            if ($type == 'status') {
                echo "<td data-label='Status'><button type='button' class='status-modal-cls {$statusClass}'>{$remark['status']}</button></td>";
            } 
            // elseif ($type == 'remarks') {
            //     echo "<td>{$remark['remarks']}</td>";
            // }
            $recUrl = isset($remark['recording_url']) ? $remark['recording_url'] : '';
            $hasRecording = !empty(trim($recUrl));
            $recBtnStyle = $hasRecording ? 'background: #22c55e !important; border: 2px solid #22c55e !important; color: white !important; min-width: 32px !important; min-height: 32px !important; border-radius: 6px !important; cursor: pointer !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; padding: 0 !important;' : 'background: #e5e7eb !important; border: 2px solid #e5e7eb !important; color: #9ca3af !important; min-width: 32px !important; min-height: 32px !important; border-radius: 6px !important; cursor: not-allowed !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; padding: 0 !important;';
            $recDisabled = !$hasRecording ? 'disabled' : '';
            
            if ($type == 'status') {
                echo "<td data-label='History'>
                            <div class='different-wrapper unique-toggle-btn' data-id='{$remark['upload_data_id']}' data-userid='{$remark['user_unique_id']}'>
                                <button class='history-button different-buttons'>
                                    <i class='bi bi-clock-history'></i>
                                </button>
                            </div>
                        </td>";
                echo "<td data-label='Recording'>
                            <div style='display:flex; justify-content:center; align-items:center; min-height: 32px;'>
                                <button class='play-recording-btn' data-tooltip='Recording' data-url='{$recUrl}' {$recDisabled} style='{$recBtnStyle}'>
                                    <svg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='currentColor' viewBox='0 0 16 16'><path d='M11.536 14.01A8.47 8.47 0 0 0 14.026 8a8.47 8.47 0 0 0-2.49-6.01l-.708.707A7.48 7.48 0 0 1 13.025 8c0 2.071-.84 3.946-2.197 5.303z'/><path d='M10.121 12.596A6.48 6.48 0 0 0 12.025 8a6.48 6.48 0 0 0-1.904-4.596l-.707.707A5.48 5.48 0 0 1 11.025 8a5.48 5.48 0 0 1-1.61 3.89z'/><path d='M8.707 11.182A4.5 4.5 0 0 0 10.025 8a4.5 4.5 0 0 0-1.318-3.182L8 5.525A3.5 3.5 0 0 1 9.025 8 3.5 3.5 0 0 1 8 10.475zM6.717 3.55A.5.5 0 0 1 7 4v8a.5.5 0 0 1-.812.39L3.825 10.5H1.5A.5.5 0 0 1 1 10V6a.5.5 0 0 1 .5-.5h2.325l2.363-1.89a.5.5 0 0 1 .529-.06z'/></svg>
                                </button>
                            </div>
                        </td>";
            }
            echo "</tr>";
        }
    } else {
        echo "<tr><td colspan='5'>No data found for this row.</td></tr>";
    }
} else {
    echo "<tr><td colspan='4'>Invalid request. Missing parameters.</td></tr>";
}
?>