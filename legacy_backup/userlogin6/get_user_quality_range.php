<?php
session_start();
include 'config.php';

header('Content-Type: application/json');

// Ensure user is logged in
if (!isset($_SESSION['tablename'])) { 
    echo json_encode(['status' => 'error', 'message' => 'User not logged in']);
    exit;
}

$user_id = $_GET['user_id'] ?? $_SESSION['tablename'];
$start_date = $_GET['start_date'] ?? null;
$end_date = $_GET['end_date'] ?? null;
$month = $_GET['month'] ?? null;
$year = $_GET['year'] ?? null;

try {
    $config = new Config();
    $conn = $config->getConnection();
    
    // Build date filter
    $dateFilter = "";
    $dateParams = [];
    
    if ($start_date && $end_date) {
        $dateFilter = " AND ur.created_at BETWEEN ? AND ?";
        $dateParams = [$start_date . ' 00:00:00', $end_date . ' 23:59:59'];
    } elseif ($month && $year) {
        $dateFilter = " AND MONTH(ur.created_at) = ? AND YEAR(ur.created_at) = ?";
        $dateParams = [$month, $year];
    }
    
    // Get all leads for the user with their history
    $query = "SELECT ur.upload_data_id, ur.history 
              FROM user_remarks ur 
              WHERE ur.user_unique_id = ? 
              AND ur.history_h = 0 
              $dateFilter";
    
    $stmt = $conn->prepare($query);
    $stmt->execute(array_merge([$user_id], $dateParams));
    $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $totalQualityRange = 0;
    $processedLeads = 0;
    
    foreach ($leads as $lead) {
        if (!empty($lead['history'])) {
            $history = json_decode($lead['history'], true);
            
            if (is_array($history) && count($history) > 0) {
                $qualityRange = calculateLeadQualityRange($history);
                $totalQualityRange += $qualityRange;
                $processedLeads++;
            }
        }
    }
    
    echo json_encode([
        'status' => 'success',
        'quality_range' => $totalQualityRange,
        'total_leads_processed' => $processedLeads
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}



function calculateLeadQualityRange($statusHistory) {
    if (!is_array($statusHistory) || count($statusHistory) === 0) {
        return 0;
    }
    
    // Sort history by timestamp to ensure chronological order
    usort($statusHistory, function($a, $b) {
        return strtotime($a['timestamp']) - strtotime($b['timestamp']);
    });
    
    // Status categories
    $zeroQualityStatuses = ['Pending', 'Already Booked', 'Not Interested', 'Fake'];
    $nonQualityStatuses = ['RNR', 'Not Connected', ...$zeroQualityStatuses];
    
    // Check first status
    $firstStatus = $statusHistory[0]['status'];
    
    // Case 1: If first status is in zero quality statuses, return 0
    if (in_array($firstStatus, $zeroQualityStatuses)) {
        return 0;
    }
    
    // Case 2: If first status is RNR or Not Connected, check second status
    if (in_array($firstStatus, ['RNR', 'Not Connected'])) {
        if (count($statusHistory) > 1) {
            $secondStatus = $statusHistory[1]['status'];
            // If second status is NOT in non-quality statuses, return 1
            if (!in_array($secondStatus, $nonQualityStatuses)) {
                return 1;
            }
        }
        return 0;
    }
    
    // Case 3: If first status is other than the above (good status), return 1
    return 1;
}

?>