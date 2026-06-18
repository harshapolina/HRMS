<?php
/**
 * ============================================================================
 * LEADS PAGE - BACKEND API ENDPOINT
 * ============================================================================
 * 
 * ORGANIZATION STRUCTURE:
 * 
 * 1. CONFIGURATION & INITIALIZATION
 *    - Session, Database connection
 *    - Error reporting setup
 * 
 * 2. HELPER FUNCTIONS
 *    - Role normalization & hierarchy
 *    - User access management
 * 
 * 3. TAG COUNT API (OPTIMIZED FOR 1L+ RECORDS)
 *    - Individual count endpoints (11 types)
 *    - Optimized SQL queries
 *    - Filter handling
 * 
 * 4. LEAD DATA API
 *    - DataTable server-side processing
 *    - Pagination, Search, Filtering
 * 
 * 5. LEAD OPERATIONS
 *    - Add, Update, Delete leads
 *    - Status updates, Assignments
 *    - Bulk operations
 * 
 * 6. UTILITY ENDPOINTS
 *    - Filter dropdown values
 *    - User hierarchy
 * 
 * ============================================================================
 * PERFORMANCE OPTIMIZATIONS:
 * - Optimized WHERE clause ordering
 * - Query timeout handling (30s)
 * - Efficient filter processing
 * - Indexed column usage
 * ============================================================================
 */

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include 'config.php';
$useruniqueId = $_SESSION['tablename'];
$mainUser = $useruniqueId ;
$userType = $_SESSION['user_type'];

$config = new Config();
$conn = $config->getConnection();

header('Content-Type: application/json'); // Ensure the response is JSON

// ============================================================================
// SECTION 1: HELPER FUNCTIONS
// ============================================================================

// Hierarchy helper functions
function normalize_role_update($rawType) {
    $s = strtolower(trim((string)$rawType));
    switch ($s) {
        case 'ceo':
        case 'promoter':
            return 'promoter';
        case 'business_head':
        case 'business head':
        case 'bh':
            return 'business_head';
        case 'manager':
        case 'm':
            return 'manager';
        case 'team_lead':
        case 'team lead':
        case 'tl':
        case 'team_leader':
            return 'team_lead';
        case 'sales_executive':
        case 'sales executive':
        case 'se':
        case 'sales':
        case 'user':
        case 'u':
        default:
            return 'user';
    }
}

function role_level_update($normalizedRole) {
    $map = [
        'promoter' => 1,
        'business_head' => 2,
        'manager' => 3,
        'team_lead' => 4,
        'user' => 5,
    ];
    return $map[strtolower(trim((string)$normalizedRole))] ?? 5;
}

function get_accessible_users_update(PDO $conn, $userTablename, $userRole) {
    $accessible = [$userTablename];
    $normalizedRole = normalize_role_update($userRole);
    $userLevel = role_level_update($normalizedRole);
    
    if ($userLevel < 5) {
        $subordinates = [];
        $visited = [];
        $queue = [$userTablename];
        $maxDepth = 10;
        $currentDepth = 0;
        
        while (!empty($queue) && $currentDepth < $maxDepth) {
            $currentLevel = [];
            foreach ($queue as $currentUser) {
                if (isset($visited[$currentUser])) continue;
                $visited[$currentUser] = true;
                
                // $stmt = $conn->prepare("
                //     SELECT tablename FROM accounts 
                //     WHERE assign_user = :manager AND is_active = 1
                // ");
                $stmt = $conn->prepare("
                    SELECT tablename FROM accounts 
                    WHERE FIND_IN_SET(:manager, assign_user) > 0 AND is_active = 1
                ");
                $stmt->bindParam(':manager', $currentUser, PDO::PARAM_STR);
                $stmt->execute();
                $directReports = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                foreach ($directReports as $report) {
                    if (!isset($visited[$report])) {
                        $subordinates[] = $report;
                        $currentLevel[] = $report;
                    }
                }
            }
            $queue = $currentLevel;
            $currentDepth++;
        }
        $accessible = array_merge($accessible, $subordinates);
    }
    return array_unique($accessible);
}

// Get request data for updates or page requests
$data = json_decode(file_get_contents("php://input"), true);
date_default_timezone_set('Asia/Kolkata'); // Set your desired timezone

$dateFrom = isset($_GET['start_date']) ? $_GET['start_date'] : (isset($_GET['month']) ? date('Y-m-01', mktime(0, 0, 0, $_GET['month'], 1, $_GET['year'])) : date('Y-m-01'));
$dateTo = isset($_GET['end_date']) ? $_GET['end_date'] : (isset($_GET['month']) ? date('Y-m-t', mktime(0, 0, 0, $_GET['month'], 1, $_GET['year'])) : date('Y-m-t'));

// Check if we have date filters from multiFilters
if (isset($_GET['multiFilters']) && !empty($_GET['multiFilters'])) {
    $multiFilters = json_decode($_GET['multiFilters'], true);
    
    if (isset($multiFilters['start_date']) && !empty($multiFilters['start_date'])) {
        $dateFrom = $multiFilters['start_date'];
    }
    
    if (isset($multiFilters['end_date']) && !empty($multiFilters['end_date'])) {
        $dateTo = $multiFilters['end_date'];
    }
}


// Handle the update request
if (isset($data['update'])) {
    $id = $data['rowId'];
    $status = $data['status'];
    $notes = $data['notes'];
    $followUpDate = $data['followUpDate'];
    $followUpTime = $data['followUpTime'];
    $requestedUserId = $data['user_unique_id'];
    $leadIdentity = isset($data['lead_identity']) ? $data['lead_identity'] : null;
    $budget = isset($data['budget']) ? $data['budget'] : null;
    $locationStatus = isset($data['location_status']) ? $data['location_status'] : null;
    date_default_timezone_set('Asia/Kolkata'); // Set your desired timezone

    if (empty($requestedUserId)) {
        echo json_encode(['status' => 'error', 'message' => 'User unique ID is missing.']);
        exit;
    }

    // Security check: Verify current user can access the requested user's data
    $currentUserType = $_SESSION['user_type'] ?? 'user';
    $accessibleUsers = get_accessible_users_update($conn, $useruniqueId, $currentUserType);
    
    // Get the actual lead owner from database
    // $verifyStmt = $conn->prepare("
    //     SELECT ur.user_unique_id 
    //     FROM user_remarks ur 
    //     WHERE ur.upload_data_id = :id AND ur.history_h = 0
    // ");
    // $verifyStmt->bindParam(':id', $id, PDO::PARAM_INT);
    // $verifyStmt->execute();
    // $leadOwner = $verifyStmt->fetch(PDO::FETCH_ASSOC);
    
    // if (!$leadOwner) {
    //     echo json_encode(['status' => 'error', 'message' => 'Lead not found']);
    //     exit;
    // }
    
    $actualLeadOwner = $requestedUserId;
    
    // Check if current user can access the actual lead owner's data
    // This handles both direct ownership and manager hierarchy access
    // if (!in_array($actualLeadOwner, $accessibleUsers)) {
    //     echo json_encode(['status' => 'error', 'message' => 'Access denied: You are not authorized to update this lead']);
    //     exit;
    // }

    try {
        // Fetch the existing history from the actual lead owner
        $query = "SELECT history FROM user_remarks WHERE upload_data_id = :id AND user_unique_id = :useruniqueId";
        $stmt = $conn->prepare($query);
        $stmt->bindValue(':id', $id);
        $stmt->bindValue(':useruniqueId', $actualLeadOwner);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $history = isset($result['history']) ? json_decode($result['history'], true) : [];
        if (!is_array($history)) {
            $history = [];
        }

        // Append the new entry to history
        $newEntry = [
            'status' => $status,
            'notes' => $notes,
            'followUpDate' => $followUpDate,
            'followUpTime' => $followUpTime,
            'leadIdentity' => $leadIdentity, // Include the new field
            'budget' => $budget, 
            'locationStatus' => $locationStatus,
            'timestamp' => date('Y-m-d H:i:s'),
            'update_by' => $mainUser // Record who made the update (could be manager)
        ];
        $history[] = $newEntry;

        // Update the row with the new status and history for the actual lead owner
        $query = "UPDATE user_remarks 
                  SET status = :status, remarks = :remarks, follow_up_date = :followUpDate, 
                      follow_up_time = :followUpTime, history = :history, lead_identity = :leadIdentity, budget = :budget, location_status = :locationStatus
                  WHERE upload_data_id = :id AND user_unique_id = :useruniqueId";
        $stmt = $conn->prepare($query);
        $stmt->bindValue(':id', $id);
        $stmt->bindValue(':status', $status);
        $stmt->bindValue(':remarks', $notes);
        $stmt->bindValue(':followUpDate', $followUpDate);
        $stmt->bindValue(':followUpTime', $followUpTime);
        $stmt->bindValue(':leadIdentity', $leadIdentity); // Correct binding for lead_identity
        $stmt->bindValue(':budget', $budget);
        $stmt->bindValue(':locationStatus', $locationStatus);
        $stmt->bindValue(':history', json_encode($history));
        $stmt->bindValue(':useruniqueId', $actualLeadOwner); // Use actual lead owner, not current user

        if ($stmt->execute()) {
            $response = [
                'status' => 'success',
                'message' => 'Row updated successfully',
                'updated_status' => $status,
                'updated_remarks' => $notes,
                'history' => $history,
                'google_calendar_event' => isset($event) ? $event->htmlLink : null,
                'google_calendar_error' => isset($response['google_calendar_error']) ? $response['google_calendar_error'] : null,
            ];
        } else {
            $errorInfo = $stmt->errorInfo();
            $response = [
                'status' => 'error',
                'message' => 'Database error: ' . implode(', ', $errorInfo)
            ];
        }
    } catch (Exception $e) {
        $response = [
            'status' => 'error',
            'message' => 'An error occurred: ' . $e->getMessage()
        ];
    }

    echo json_encode($response);
    exit;
}
// Validate request Call History
if (isset($data['history_call']) && $data['history_call'] === true) {
    // This block is for updating the call count when the user clicks
    $id = $data['rowId'];

    try {
        // Fetch current call history
        $query = "SELECT call_history FROM user_remarks WHERE upload_data_id = :id AND user_unique_id = :useruniqueId";
        $stmt = $conn->prepare($query);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':useruniqueId', $useruniqueId, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $call_history = isset($result['call_history']) ? json_decode($result['call_history'], true) : [];
        $call_history = is_array($call_history) ? $call_history : [];

        // Calculate the next click_attempted value
        $next_click_attempted = count($call_history) + 1;

        // Append new entry
        $newEntry = [
            'click_attempted' => $next_click_attempted,
            'timestamp' => date('Y-m-d H:i:s'),
        ];
        $call_history[] = $newEntry;

        // Update the database
        $updateQuery = "UPDATE user_remarks 
                        SET call_history = :call_history 
                        WHERE upload_data_id = :id AND user_unique_id = :useruniqueId";
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bindValue(':id', $id, PDO::PARAM_INT);
        $updateStmt->bindValue(':call_history', json_encode($call_history), PDO::PARAM_STR);
        $updateStmt->bindValue(':useruniqueId', $useruniqueId, PDO::PARAM_STR);

        if ($updateStmt->execute()) {
            echo json_encode([
                'status' => 'success',
                'message' => 'Call history updated successfully.',
                'call_history' => $call_history,
                'total_clicks' => count($call_history),
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to update call history.']);
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }

    exit;
} elseif (isset($data['history_call']) && $data['history_call'] === false) {
    // This block is for fetching the count without updating
    $id = $data['rowId'];

    try {
        $query = "SELECT call_history FROM user_remarks WHERE upload_data_id = :id AND user_unique_id = :useruniqueId";
        $stmt = $conn->prepare($query);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':useruniqueId', $useruniqueId, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $call_history = isset($result['call_history']) ? json_decode($result['call_history'], true) : [];

        echo json_encode([
            'status' => 'success',
            'total_clicks' => count($call_history), // Just return the count
        ]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }

    exit;
}
// This is the function is for get the count immeditly 
if (isset($data['rowId']) && isset($data['history_call'])) {
    $id = $data['rowId']; // Get row ID
    date_default_timezone_set('Asia/Kolkata');
    
    try {
        // Fetch the existing call history
        $query = "SELECT call_history FROM user_remarks WHERE upload_data_id = :id AND user_unique_id = :useruniqueId";
        $stmt = $conn->prepare($query);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':useruniqueId', $useruniqueId, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Initialize or fetch existing call history
        $call_history = isset($result['call_history']) ? json_decode($result['call_history'], true) : [];

        // Increment the call count in history
        $call_history[] = [
            'timestamp' => date('Y-m-d H:i:s'), // Record the timestamp of the call
            'rowId' => $id,
            'useruniqueId' => $useruniqueId,
        ];

        // Update the call history in the database
        $updateQuery = "UPDATE user_remarks SET call_history = :call_history WHERE upload_data_id = :id AND user_unique_id = :useruniqueId";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bindValue(':call_history', json_encode($call_history), PDO::PARAM_STR);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':useruniqueId', $useruniqueId, PDO::PARAM_STR);
        $stmt->execute();

        // Return the updated total number of clicks
        $totalClicks = count($call_history);

        echo json_encode([
            'status' => 'success',
            'total_clicks' => $totalClicks, // Send the updated count back
        ]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }

    exit;
}
// Handle the fetch history request
if (isset($data['fetchHistory'])) {
    $id = $data['rowId'];
    $useruniqueId = $data['user_unique_id'];
    try {
        // Fetch the history along with created_at and assigned_by
        $query = "SELECT history, created_at, assigned_by FROM user_remarks WHERE upload_data_id = :id AND user_unique_id = :useruniqueId";
        $stmt = $conn->prepare($query);
        $stmt->bindValue(':id', $id);
        $stmt->bindValue(':useruniqueId', $useruniqueId);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        // Fetch The leads Details
        $query_leads = "SELECT name, number 
                        FROM shi_upload_data 
                        WHERE id = :id";
        $stmt_leads = $conn->prepare($query_leads);
        $stmt_leads->bindValue(':id', $id);
        $stmt_leads->execute();
        $result_leads = $stmt_leads->fetch(PDO::FETCH_ASSOC);

        // Check if the result contains data
        if ($result) {
            $history = isset($result['history']) ? json_decode($result['history'], true) : [];
            $assignedDate = $result['created_at'];
            $assignedBy = $result['assigned_by'];
            $lead_user = $result_leads['name'];
            $lead_number = $result_leads['number'];

            // Return the data as JSON
            echo json_encode([
                'status' => 'success',
                'history' => $history,
                'assignedDate' => $assignedDate,
                'assignedBy' => $assignedBy,
                'lead_user' => $lead_user,
                'lead_number' => $lead_number
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'No data found']);
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}
// Handle Updare Request End
// Handle the fetch history request
if (isset($data['fetchCallHistory'])) {
    $id = $data['rowId'];
    $useruniqueId = $data['user_unique_id'];
    try {
        // Fetch the history along with created_at and assigned_by
        $query = "SELECT call_history, created_at, assigned_by FROM user_remarks WHERE upload_data_id = :id AND user_unique_id = :useruniqueId";
        $stmt = $conn->prepare($query);
        $stmt->bindValue(':id', $id);
        $stmt->bindValue(':useruniqueId', $useruniqueId);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        // Fetch The leads Details
        $query_leads = "SELECT name, number 
                        FROM shi_upload_data 
                        WHERE id = :id";
        $stmt_leads = $conn->prepare($query_leads);
        $stmt_leads->bindValue(':id', $id);
        $stmt_leads->execute();
        $result_leads = $stmt_leads->fetch(PDO::FETCH_ASSOC);

        // Check if the result contains data
        if ($result) {
            $call_history = isset($result['call_history']) ? json_decode($result['call_history'], true) : [];
            $assignedDate = $result['created_at'];
            $assignedBy = $result['assigned_by'];
            $lead_user = $result_leads['name'];
            $lead_number = $result_leads['number'];

            // Return the data as JSON
            echo json_encode([
                'status' => 'success',
                'history' => $call_history,
                'assignedDate' => $assignedDate ?? null,
                'assignedBy' => $assignedBy ?? null,
                'lead_user' => $lead_user ?? null,
                'lead_number' => $lead_number ?? null
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'No data found']);
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}


if (isset($data['reassign'])) {
    $id = (int)$data['reassignRowId'];
    $assignUser = $data['assignUser'];
    $projectName = $data['projectName'];
    $includeHistory = (bool)$data['includeHistory'];
    $useruniqueId = $_SESSION['tablename'] ?? null;
    date_default_timezone_set('Asia/Kolkata');

    if (!$useruniqueId) {
        echo json_encode(['status' => 'error', 'message' => 'User unique ID is missing.']);
        exit;
    }

    try {
        $conn->beginTransaction();

        // 1) Lock upload row to serialize operations on this lead
        $qLockUpload = "SELECT id, assign_to_user FROM shi_upload_data WHERE id = :id FOR UPDATE";
        $stmtLockUpload = $conn->prepare($qLockUpload);
        $stmtLockUpload->bindValue(':id', $id, PDO::PARAM_INT);
        $stmtLockUpload->execute();
        if ($stmtLockUpload->rowCount() === 0) {
            throw new Exception('Upload row not found.');
        }
        $uploadRow = $stmtLockUpload->fetch(PDO::FETCH_ASSOC);
        if ($uploadRow['assign_to_user'] === $assignUser) {
            $conn->commit();
            echo json_encode(['status' => 'success', 'message' => 'Lead already assigned to the specified user.']);
            exit;
        }

        // 2) Authorization + lock one accessible remark row
        $accessibleUsers = get_accessible_users_update($conn, $useruniqueId, $userType);
        $accessibleUsers = array_values(array_unique($accessibleUsers));

        if (empty($accessibleUsers)) {
            throw new Exception('You are not authorized to reassign this lead.');
        }

        $accessiblePlaceholders = [];
        foreach ($accessibleUsers as $idx => $accessibleUser) {
            $accessiblePlaceholders[] = ":accessibleUser{$idx}";
        }

        $queryAuth = "SELECT ur.id AS remark_id, ur.created_at, ur.user_unique_id 
                      FROM user_remarks ur 
                      WHERE ur.upload_data_id = :id 
                        AND ur.history_h = 0 
                        AND ur.user_unique_id IN (" . implode(',', $accessiblePlaceholders) . ") 
                      LIMIT 1 FOR UPDATE";

        $stmtAuth = $conn->prepare($queryAuth);
        $stmtAuth->bindValue(':id', $id, PDO::PARAM_INT);
        foreach ($accessibleUsers as $idx => $accessibleUser) {
            $stmtAuth->bindValue($accessiblePlaceholders[$idx], $accessibleUser, PDO::PARAM_STR);
        }
        $stmtAuth->execute();

        $previousRow = $stmtAuth->fetch(PDO::FETCH_ASSOC);
        if (!$previousRow) {
            throw new Exception('You are not authorized to reassign this lead.');
        }

        $remarkId = (int)$previousRow['remark_id'];
        $prevCreatedAt = $previousRow['created_at'];

        // Check if an active (history_h = 0) remark already exists for target user
        $qExists = "SELECT id FROM user_remarks 
                    WHERE upload_data_id = :id AND user_unique_id = :assign_user AND history_h = 0 LIMIT 1";
        $stmtExists = $conn->prepare($qExists);
        $stmtExists->bindValue(':id', $id, PDO::PARAM_INT);
        $stmtExists->bindValue(':assign_user', $assignUser);
        $stmtExists->execute();
        $existsForTarget = ($stmtExists->rowCount() > 0);

        if ($includeHistory) {
            // Move (update) behavior:
            if ($existsForTarget) {
                // There's already an active remark for the target user. Avoid updating remark to prevent unique constraint error.
                // We'll update shi_upload_data.assign_to_user only and mark current remark as history (optional).
                $stmtUpdateData = $conn->prepare("UPDATE shi_upload_data SET assign_to_user = :assign_user WHERE id = :id");
                $stmtUpdateData->bindValue(':assign_user', $assignUser);
                $stmtUpdateData->bindValue(':id', $id, PDO::PARAM_INT);
                $stmtUpdateData->execute();

                // mark current remark as history to avoid duplicate active remark
                $stmtMark = $conn->prepare("UPDATE user_remarks SET history_h = 1 WHERE id = :remark_id AND history_h = 0");
                $stmtMark->bindValue(':remark_id', $remarkId, PDO::PARAM_INT);
                $stmtMark->execute();

                $conn->commit();
                echo json_encode(['status'=>'success','message'=>'Lead assigned (existing active remark for target user found). No duplicate created.']);
                exit;
            } else {
                // Safe to update the locked remark row to assign to target user
                $stmtUpdateRemarks = $conn->prepare("
                    UPDATE user_remarks 
                    SET user_unique_id = :assign_user, assign_project_name = :project_name, assigned_by = :assigned_by
                    WHERE id = :remark_id
                ");
                $stmtUpdateRemarks->bindValue(':assign_user', $assignUser);
                $stmtUpdateRemarks->bindValue(':project_name', $projectName);
                $stmtUpdateRemarks->bindValue(':assigned_by', $useruniqueId);
                $stmtUpdateRemarks->bindValue(':remark_id', $remarkId, PDO::PARAM_INT);
                $stmtUpdateRemarks->execute();

                $stmtUpdateData = $conn->prepare("UPDATE shi_upload_data SET assign_to_user = :assign_user WHERE id = :id");
                $stmtUpdateData->bindValue(':assign_user', $assignUser);
                $stmtUpdateData->bindValue(':id', $id, PDO::PARAM_INT);
                $stmtUpdateData->execute();

                $conn->commit();
                echo json_encode(['status'=>'success','message'=>'Lead reassigned with history (moved).']);
                exit;
            }
        } else {
            // Fresh lead (create new active remark) behavior:
            // Mark old remark as history
            $stmtMark = $conn->prepare("UPDATE user_remarks SET history_h = 1 WHERE id = :remark_id AND history_h = 0");
            $stmtMark->bindValue(':remark_id', $remarkId, PDO::PARAM_INT);
            $stmtMark->execute();

            // If an active remark for target already exists, skip insert
            if ($existsForTarget) {
                // only update shi_upload_data
                $stmtUpdateData = $conn->prepare("UPDATE shi_upload_data SET assign_to_user = :assign_user WHERE id = :id");
                $stmtUpdateData->bindValue(':assign_user', $assignUser);
                $stmtUpdateData->bindValue(':id', $id, PDO::PARAM_INT);
                $stmtUpdateData->execute();

                $conn->commit();
                echo json_encode(['status'=>'success','message'=>'Target user already has an active remark — assignment applied to upload_data only.']);
                exit;
            }

            // Insert new remark; wrap in try/catch for duplicate-key safety
            try {
                $stmtInsert = $conn->prepare("
                    INSERT INTO user_remarks 
                    (upload_data_id, user_unique_id, assign_project_name, assigned_by, created_at, status, remarks, history, follow_up_date, follow_up_time, lead_identity, budget, location_status, call_history, history_h)
                    VALUES
                    (:id, :assign_user, :project_name, :assigned_by, :created_at, 'Pending', '', '[]', NULL, NULL, 'N/A', 'N/A', 'N/A', '[]', 0)
                ");
                $stmtInsert->bindValue(':id', $id, PDO::PARAM_INT);
                $stmtInsert->bindValue(':assign_user', $assignUser);
                $stmtInsert->bindValue(':project_name', $projectName);
                $stmtInsert->bindValue(':assigned_by', $useruniqueId);
                $stmtInsert->bindValue(':created_at', $prevCreatedAt);
                $stmtInsert->execute();
            } catch (PDOException $e) {
                // Duplicate key inserted by concurrent transaction — handle gracefully
                if ($e->getCode() == '23000') {
                    // Already exists; ignore insert
                } else {
                    throw $e;
                }
            }

            // Update shi_upload_data assignment
            $stmtUpdateData = $conn->prepare("UPDATE shi_upload_data SET assign_to_user = :assign_user WHERE id = :id");
            $stmtUpdateData->bindValue(':assign_user', $assignUser);
            $stmtUpdateData->bindValue(':id', $id, PDO::PARAM_INT);
            $stmtUpdateData->execute();

            $conn->commit();
            echo json_encode(['status'=>'success','message'=>'Lead reassigned as fresh (duplicate prevented if already existed).']);
            exit;
        }

    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'An error occurred: ' . $e->getMessage()]);
        exit;
    }
}


//This is function is for BUlk assign Rows
if (isset($data['bulkAssign']) && $data['bulkAssign'] === true) {
    $rowIds = array_map('intval', $data['rowIds'] ?? []);
    $assignUser = $data['assignUser'];
    $projectName = $data['projectName'];
    $includeHistory = (bool)$data['includeHistory'];
    $useruniqueId = $_SESSION['tablename'] ?? null;
    date_default_timezone_set('Asia/Kolkata');

    // Direct file logging since error_log isn't showing up
    $logFile = __DIR__ . '/bulk_assign_debug.log';
    $logMsg = "\n\n=== BULK ASSIGN: " . date('Y-m-d H:i:s') . " ===\n";
    $logMsg .= "Current User: $useruniqueId\n";
    $logMsg .= "Target User: $assignUser\n";
    $logMsg .= "Leads: " . implode(',', $rowIds) . "\n";
    $logMsg .= "Include History: " . ($includeHistory ? 'true' : 'false') . "\n";
    $logMsg .= "Session Data: " . print_r($_SESSION, true) . "\n";
    file_put_contents($logFile, $logMsg, FILE_APPEND);

    error_log("🚀 BULK ASSIGN START: current_user=$useruniqueId, target_user=$assignUser, leads=" . implode(',', $rowIds) . ", includeHistory=" . ($includeHistory ? 'true' : 'false'));
    error_log("📋 Session data: " . print_r($_SESSION, true));

    if (!$useruniqueId) {
        echo json_encode(['status' => 'error', 'message' => 'User unique ID is missing.']);
        exit;
    }

    if (empty($rowIds)) {
        echo json_encode(['status'=>'error','message'=>'No rows to assign.']);
        exit;
    }

    try {
        $conn->beginTransaction();
        error_log("✅ Transaction started");

        // Lock all upload_data rows for these ids (serialize)
        $inPlaceholders = implode(',', array_fill(0, count($rowIds), '?'));
        $stmtLock = $conn->prepare("SELECT id FROM shi_upload_data WHERE id IN ($inPlaceholders) FOR UPDATE");
        foreach ($rowIds as $k => $rid) $stmtLock->bindValue($k+1, $rid, PDO::PARAM_INT);
        $stmtLock->execute();

        // Pre-fetch existing active remarks for target assignUser among these ids
        $stmtExisting = $conn->prepare("
            SELECT upload_data_id 
            FROM user_remarks 
            WHERE upload_data_id IN ($inPlaceholders)
              AND user_unique_id = ?
              AND history_h = 0
        ");
        // bind ids (positional parameters)
        $paramIndex = 1;
        foreach ($rowIds as $rid) {
            $stmtExisting->bindValue($paramIndex++, $rid, PDO::PARAM_INT);
        }
        // bind assignUser as the next positional parameter
        $stmtExisting->bindValue($paramIndex, $assignUser);
        $stmtExisting->execute();
        $alreadyAssigned = $stmtExisting->fetchAll(PDO::FETCH_COLUMN, 0);
        $alreadyAssigned = array_flip($alreadyAssigned); // quick lookup

        // Now loop rows but do minimal DB work and skip duplicates
        $processedCount = 0;
        $skippedCount = 0;
        
        foreach ($rowIds as $id) {
            error_log("🔍 Processing lead $id - userType=$userType, user=$useruniqueId");
            
            // Authorization check per row (you can optimize by fetching user_remarks for all ids first)
            if ($userType === 'manager' || $userType === 'ceo') {
                $userCondition = "ur.user_unique_id = :userUniqueId OR ur.user_unique_id IN ( SELECT tablename FROM accounts WHERE FIND_IN_SET(:userUniqueId2, assign_user) > 0 )";
            } else {
                $userCondition = "ur.user_unique_id = :userUniqueId";
            }
            
            $authSQL = "SELECT id, created_at, user_unique_id FROM user_remarks ur WHERE upload_data_id = :id AND ($userCondition) LIMIT 1 FOR UPDATE";
            error_log("🔐 Auth SQL: $authSQL");
            error_log("🔐 Auth params: id=$id, userUniqueId=$useruniqueId");
            
            $stmtAuth = $conn->prepare($authSQL);
            $stmtAuth->bindValue(':id', $id, PDO::PARAM_INT);
            $stmtAuth->bindValue(':userUniqueId', $useruniqueId);
            
            // Bind userUniqueId2 for manager/CEO authorization
            if ($userType === 'manager' || $userType === 'ceo') {
                $stmtAuth->bindValue(':userUniqueId2', $useruniqueId);
            }
            
            $stmtAuth->execute();
            if ($stmtAuth->rowCount() === 0) {
                error_log("❌ Authorization failed for lead $id - no matching user_remarks found");
                $skippedCount++;
                continue; // skip unauthorized rows
            }
            
            $processedCount++;
            $orig = $stmtAuth->fetch(PDO::FETCH_ASSOC);
            $remarkId = (int)$orig['id'];
            $origCreatedAt = $orig['created_at'];
            $currentOwner = $orig['user_unique_id'];
            error_log("✅ Authorization passed for lead $id - remark_id=$remarkId, current_owner=$currentOwner, target=$assignUser");

            if ($includeHistory) {
                // If target already has active remark, skip moving remark to avoid duplicate
                if (isset($alreadyAssigned[$id])) {
                    error_log("⚠️ Lead $id already assigned to $assignUser - marking old remark as history");
                    // only update shi_upload_data and mark current remark history to avoid duplicates
                    $stmtUpdateData = $conn->prepare("UPDATE shi_upload_data SET assign_to_user = :assign_user WHERE id = :id");
                    $stmtUpdateData->bindValue(':assign_user', $assignUser);
                    $stmtUpdateData->bindValue(':id', $id, PDO::PARAM_INT);
                    $stmtUpdateData->execute();

                    $stmtMark = $conn->prepare("UPDATE user_remarks SET history_h = 1 WHERE id = :remark_id AND history_h = 0");
                    $stmtMark->bindValue(':remark_id', $remarkId, PDO::PARAM_INT);
                    $stmtMark->execute();
                    error_log("✅ Marked remark $remarkId as history for lead $id");
                    continue;
                }

                error_log("📝 Moving lead $id to $assignUser with history");

                // safe to update remark to new user
                $stmtUpdateRemarks = $conn->prepare("
                    UPDATE user_remarks
                    SET user_unique_id = :assign_user, assign_project_name = :project_name, assigned_by = :assigned_by
                    WHERE id = :remark_id
                ");
                $stmtUpdateRemarks->bindValue(':assign_user', $assignUser);
                $stmtUpdateRemarks->bindValue(':project_name', $projectName);
                $stmtUpdateRemarks->bindValue(':assigned_by', $useruniqueId);
                $stmtUpdateRemarks->bindValue(':remark_id', $remarkId, PDO::PARAM_INT);
                $stmtUpdateRemarks->execute();
                $rowsAffected = $stmtUpdateRemarks->rowCount();
                error_log("✅ Updated user_remarks remark_id=$remarkId to user=$assignUser (rows affected: $rowsAffected)");

                $stmtUpdateData = $conn->prepare("UPDATE shi_upload_data SET assign_to_user = :assign_user WHERE id = :id");
                $stmtUpdateData->bindValue(':assign_user', $assignUser);
                $stmtUpdateData->bindValue(':id', $id, PDO::PARAM_INT);
                $stmtUpdateData->execute();
                error_log("✅ Updated shi_upload_data id=$id to user=$assignUser");

            } else {
                // Fresh lead: mark old as history
                $stmtMark = $conn->prepare("UPDATE user_remarks SET history_h = 1 WHERE id = :remark_id AND history_h = 0");
                $stmtMark->bindValue(':remark_id', $remarkId, PDO::PARAM_INT);
                $stmtMark->execute();

                // If already assigned to target, skip insert
                if (isset($alreadyAssigned[$id])) {
                    $stmtUpdateData = $conn->prepare("UPDATE shi_upload_data SET assign_to_user = :assign_user WHERE id = :id");
                    $stmtUpdateData->bindValue(':assign_user', $assignUser);
                    $stmtUpdateData->bindValue(':id', $id, PDO::PARAM_INT);
                    $stmtUpdateData->execute();
                    continue;
                }

                // Insert new remark (wrap in try/catch for duplicate-key)
                try {
                    $stmtInsert = $conn->prepare("
                        INSERT INTO user_remarks
                        (upload_data_id, user_unique_id, assign_project_name, assigned_by, created_at, status, remarks, history, follow_up_date, follow_up_time, lead_identity, budget, location_status, call_history, history_h)
                        VALUES
                        (:id, :assign_user, :project_name, :assigned_by, :created_at, 'Pending', '', '[]', NULL, NULL, 'N/A', 'N/A', 'N/A', '[]', 0)
                    ");
                    $stmtInsert->bindValue(':id', $id, PDO::PARAM_INT);
                    $stmtInsert->bindValue(':assign_user', $assignUser);
                    $stmtInsert->bindValue(':project_name', $projectName);
                    $stmtInsert->bindValue(':assigned_by', $useruniqueId);
                    $stmtInsert->bindValue(':created_at', $origCreatedAt ?? date('Y-m-d H:i:s'));
                    $stmtInsert->execute();
                    error_log("✅ Inserted new user_remarks for id=$id, user=$assignUser");
                } catch (PDOException $e) {
                    if ($e->getCode() != '23000') throw $e; // rethrow unexpected errors
                    // duplicate key: someone else created it; ignore
                    error_log("⚠️ Duplicate key for id=$id, user=$assignUser (ignored)");
                }

                // CRITICAL: Update shi_upload_data.assign_to_user (was missing!)
                $stmtUpdateData = $conn->prepare("UPDATE shi_upload_data SET assign_to_user = :assign_user WHERE id = :id");
                $stmtUpdateData->bindValue(':assign_user', $assignUser);
                $stmtUpdateData->bindValue(':id', $id, PDO::PARAM_INT);
                $stmtUpdateData->execute();
                error_log("✅ Updated shi_upload_data id=$id to user=$assignUser (fresh lead)");
            }
        }

        $conn->commit();
        error_log("✅ Transaction committed successfully");
        error_log("📈 Summary: Processed=$processedCount, Skipped=$skippedCount, Total=" . count($rowIds));
        
        // Log final state for debugging
        foreach ($rowIds as $id) {
            $stmtCheck = $conn->prepare("SELECT sud.assign_to_user, ur.user_unique_id FROM shi_upload_data sud LEFT JOIN user_remarks ur ON sud.id = ur.upload_data_id WHERE sud.id = ? AND ur.history_h = 0 LIMIT 1");
            $stmtCheck->execute([$id]);
            $finalState = $stmtCheck->fetch(PDO::FETCH_ASSOC);
            error_log("📊 Final state for lead $id: shi_upload_data.assign_to_user=" . ($finalState['assign_to_user'] ?? 'NULL') . ", user_remarks.user_unique_id=" . ($finalState['user_unique_id'] ?? 'NULL'));
        }
        
        echo json_encode(['status'=>'success','message'=>'Leads assigned successfully.']);
    } catch (Exception $e) {
        $conn->rollBack();
        error_log("❌ Transaction rolled back: " . $e->getMessage());
        error_log("❌ Stack trace: " . $e->getTraceAsString());
        echo json_encode(['status'=>'error','message'=>'An error occurred: '.$e->getMessage()]);
    }
    exit;
}

// THis is for fitch rows based on the followup date
if (isset($data['fetchFollowUps'])) {
    date_default_timezone_set('Asia/Kolkata'); // Set your desired timezone
    $todayDate = date('Y-m-d'); // Get today's date

    try {
        // Fetch rows where follow-up date is today or earlier
        $query = "SELECT * FROM user_remarks WHERE follow_up_date = :todayDate";
        $stmt = $conn->prepare($query);
        $stmt->bindValue(':todayDate', $todayDate);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['status' => 'success', 'rows' => $rows]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}
// Get The Users In DropDown
if (isset($_GET['get_users']) && $_GET['get_users'] == 1) {
    // Get current user from session (using the same variable as used elsewhere in this file)
    $currentUserTablename = $_SESSION['tablename'] ?? $useruniqueId ?? null;
    
    if (!$currentUserTablename) {
        // If no current user, return empty
        echo "<option value=''>No users available</option>";
        exit;
    }
    
    // Get current user's role to determine if they can see subordinates
    $stmt = $conn->prepare("SELECT user_type FROM accounts WHERE tablename = :tablename LIMIT 1");
    $stmt->execute([':tablename' => $currentUserTablename]);
    $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);
    $userRole = $currentUser['user_type'] ?? 'user';
    
    // Get all accessible users (subordinates + current user) using existing function
    $accessibleUsers = get_accessible_users_update($conn, $currentUserTablename, $userRole);
    
    if (empty($accessibleUsers)) {
        // If no accessible users, only show current user
        $userQuery = "SELECT tablename, username FROM accounts WHERE tablename = :current_user AND is_active = 1";
        $userStmt = $conn->prepare($userQuery);
        $userStmt->execute([':current_user' => $currentUserTablename]);
    } else {
        // Build placeholders for IN clause
        $placeholders = str_repeat('?,', count($accessibleUsers) - 1) . '?';
        $userQuery = "SELECT tablename, username FROM accounts WHERE tablename IN ($placeholders) AND is_active = 1 ORDER BY username";
        $userStmt = $conn->prepare($userQuery);
        $userStmt->execute($accessibleUsers);
    }
    
    $users = $userStmt->fetchAll(PDO::FETCH_ASSOC);

    // Return users as options with username as display text
    foreach ($users as $user) {
        $tablename = htmlspecialchars($user['tablename'], ENT_QUOTES, 'UTF-8');
        $username = htmlspecialchars($user['username'] ?? $user['tablename'], ENT_QUOTES, 'UTF-8');
        echo "<option value='{$tablename}'>{$username}</option>";
    }
    exit; // Terminate script after sending user options
}

// Remote search for header dropdown options (unique values across full dataset)
// Used by Leads page header filters so search works beyond lazily loaded items.
if (isset($_GET['get_unique_values']) && (int)$_GET['get_unique_values'] === 1) {
    try {
        $columnIndex = isset($_GET['columnIndex']) ? (int)$_GET['columnIndex'] : 0;
        $fieldKey = isset($_GET['fieldKey']) ? trim((string)$_GET['fieldKey']) : '';
        $q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $perPage = isset($_GET['perPage']) ? max(5, min(200, (int)$_GET['perPage'])) : 40;
        $offset = ($page - 1) * $perPage;

        // Same context params as table fetching so results match current view
        $searchQuery = isset($_GET['searchQuery']) ? (string)$_GET['searchQuery'] : '';
        $filter = isset($_GET['filter']) ? trim((string)$_GET['filter']) : '';
        if (in_array($filter, ['followupLeads', 'followup', 'follow-up', 'FollowUp'], true)) {
            $filter = 'followLeads';
        }
        $multiFilters = isset($_GET['multiFilters']) ? json_decode($_GET['multiFilters'], true) : [];
        if (!is_array($multiFilters)) $multiFilters = [];
        $managerToggle = isset($_GET['managerToggle']) ? (int)$_GET['managerToggle'] : 0;
        $filterUserParam = isset($_GET['filterUser']) ? trim((string)$_GET['filterUser']) : '';
        $managerViewParam = isset($_GET['managerView']) ? filter_var($_GET['managerView'], FILTER_VALIDATE_BOOLEAN) : false;

        // Map dropdown request -> SQL field.
        // Supports:
        // - header dropdowns via columnIndex
        // - filter modal suggestions via fieldKey
        $fieldKeyMap = [
            'name' => ['sql' => 'sud.name', 'selfKey' => 'name'],
            'email' => ['sql' => 'sud.email', 'selfKey' => 'email'],
            'number' => ['sql' => 'sud.number', 'selfKey' => 'number'],
            'location' => ['sql' => 'ur.location_status', 'selfKey' => 'location'],
            'budget' => ['sql' => 'ur.budget', 'selfKey' => 'budget'],
            'source_of_lead' => ['sql' => 'sud.source_of_lead', 'selfKey' => 'source_of_lead'],
            'status' => ['sql' => 'ur.status', 'selfKey' => 'status'],
            'assign_project_name' => ['sql' => 'ur.assign_project_name', 'selfKey' => 'assign_project_name'],
            'user' => ['sql' => 'ur.user_unique_id', 'selfKey' => 'user'],
            'lead_identity' => ['sql' => 'ur.lead_identity', 'selfKey' => 'lead_identity'],
            // header-only / special
            'id' => ['sql' => 'sud.id', 'selfKey' => 'id'],
            'created_at' => ['sql' => 'ur.created_at', 'selfKey' => 'created_at'],
        ];
        $columnIndexToFieldKey = [
            1 => 'name',
            2 => 'assign_project_name',
            3 => 'id',
            4 => 'created_at',
            5 => 'email',
            6 => 'location',
            7 => 'budget',
            8 => 'user',
            9 => 'source_of_lead',
        ];

        $resolvedKey = '';
        if ($fieldKey !== '' && isset($fieldKeyMap[$fieldKey])) {
            $resolvedKey = $fieldKey;
        } elseif (isset($columnIndexToFieldKey[$columnIndex])) {
            $resolvedKey = $columnIndexToFieldKey[$columnIndex];
        }
        if ($resolvedKey === '' || !isset($fieldKeyMap[$resolvedKey])) {
            echo json_encode(['values' => [], 'hasMore' => false]);
            exit;
        }
        $valueField = $fieldKeyMap[$resolvedKey]['sql'];

        // Remove self-filter so user can still see all options while searching that dropdown
        $selfKey = $fieldKeyMap[$resolvedKey]['selfKey'] ?? '';
        if ($selfKey !== '' && isset($multiFilters[$selfKey])) {
            unset($multiFilters[$selfKey]);
        }

        // OPTIMIZED: Cache user role lookup (used in multiple places)
        static $userRoleCacheUnique = [];
        $cacheKeyUnique = $useruniqueId;
        if (!isset($userRoleCacheUnique[$cacheKeyUnique])) {
            $stmt = $conn->prepare("SELECT user_type FROM accounts WHERE tablename = :tablename LIMIT 1");
            $stmt->bindParam(':tablename', $useruniqueId, PDO::PARAM_STR);
            $stmt->execute();
            $currentUserRow = $stmt->fetch(PDO::FETCH_ASSOC);
            $userRoleCacheUnique[$cacheKeyUnique] = $currentUserRow['user_type'] ?? 'user';
        }
        $currentUserType = $userRoleCacheUnique[$cacheKeyUnique];

        $normalizedRole = normalize_role_update($currentUserType);
        $canUseManagerToggle = ($normalizedRole !== 'user');
        
        // OPTIMIZED: Cache accessible users (expensive operation)
        static $accessibleUsersCacheUnique = [];
        $usersCacheKeyUnique = $useruniqueId . '_' . $currentUserType;
        if (!isset($accessibleUsersCacheUnique[$usersCacheKeyUnique])) {
            $accessibleUsersCacheUnique[$usersCacheKeyUnique] = get_accessible_users_update($conn, $useruniqueId, $currentUserType);
        }
        $accessibleUsers = $accessibleUsersCacheUnique[$usersCacheKeyUnique];

        $whereClauses = [];
        $bindings = [];

        $forceSpecificUser = false;
        $useAccessibleUsers = false;
        if ($filterUserParam !== '' && in_array($filterUserParam, $accessibleUsers, true)) {
            $whereClauses[] = "ur.user_unique_id = :forcedUser";
            $bindings[':forcedUser'] = [$filterUserParam, PDO::PARAM_STR];
            $forceSpecificUser = true;
        } elseif ($managerToggle === 1 && $canUseManagerToggle) {
            $subordinateUsers = array_values(array_filter($accessibleUsers, function($u) use ($useruniqueId) { return $u !== $useruniqueId; }));
            if (count($subordinateUsers) > 0) {
                $ph = [];
                foreach ($subordinateUsers as $i => $u) {
                    $p = ":accessible_user_$i";
                    $ph[] = $p;
                    $bindings[$p] = [$u, PDO::PARAM_STR];
                }
                $whereClauses[] = "ur.user_unique_id IN (" . implode(',', $ph) . ")";
                $useAccessibleUsers = true;
            } else {
                $whereClauses[] = "ur.user_unique_id = :userUniqueId";
                $bindings[':userUniqueId'] = [$useruniqueId, PDO::PARAM_STR];
            }
        } else {
            $whereClauses[] = "ur.user_unique_id = :userUniqueId";
            $bindings[':userUniqueId'] = [$useruniqueId, PDO::PARAM_STR];
        }

        // Apply quick filter (same set as table; keep it minimal here)
        $filterConditions = [
            'myLeads' => "ur.user_unique_id = :userUniqueId",
            'bookedLeads' => "ur.status = 'Converted'",
            'droppedLeads' => "ur.status IN ('Not Interested', 'Fake')",
            'activeLeads' => "ur.status NOT IN ('Not Interested', 'Fake', 'Already Booked', 'Converted') AND ur.status IS NOT NULL AND ur.status != ''",
            'freshLeads' => "ur.created_at >= DATE_SUB(CURDATE(), INTERVAL 1 DAY)",
            'pendingLeads' => "ur.status = 'Pending'",
            'followLeads' => "LOWER(TRIM(ur.status)) = 'follow up'",
            'overdueLeads' => "ur.status IN ('Follow Up', 'RNR', 'Call Back', 'Pending', 'Interested', 'Fix Site Visit', 'Site Visit Done', 'Re site visit', 'Not Connected') AND ur.status != 'Converted' AND ur.status != 'Already Booked' AND ur.follow_up_date IS NOT NULL AND ur.follow_up_time IS NOT NULL AND CONCAT(ur.follow_up_date, ' ', ur.follow_up_time) < DATE_SUB(NOW(), INTERVAL 1 DAY)",
            'paidAds' => "LOWER(TRIM(sud.source_of_lead)) IN ('google ads', 'facebook ads', 'magicbricks ads', '99acres ads', 'housing.com ads')",
            'SHI_D' => "(sud.source_of_lead IS NULL OR LOWER(TRIM(sud.source_of_lead)) NOT IN ('google ads', 'facebook ads', 'magicbricks ads', '99acres ads', 'housing.com ads'))",
        ];
        if ($filter !== '' && isset($filterConditions[$filter])) {
            // Skip myLeads in team view; table does similar to avoid binding issues
            if (!($filter === 'myLeads' && $managerToggle === 1 && $canUseManagerToggle) && !$forceSpecificUser) {
                $whereClauses[] = $filterConditions[$filter];
            }
        }

        // Date range from multiFilters if present
        $startDate = !empty($multiFilters['start_date']) ? $multiFilters['start_date'] . ' 00:00:00' : null;
        $endDate = !empty($multiFilters['end_date']) ? $multiFilters['end_date'] . ' 23:59:59' : null;
        if (!empty($startDate) && !empty($endDate)) {
            $whereClauses[] = "ur.created_at BETWEEN :startDate AND :endDate";
            $bindings[':startDate'] = [$startDate, PDO::PARAM_STR];
            $bindings[':endDate'] = [$endDate, PDO::PARAM_STR];
        }
        unset($multiFilters['start_date'], $multiFilters['end_date']);

        // MultiFilters - OPTIMIZED: All columns now supported
        $allowedFilters = [
            'name' => 'sud.name',
            'email' => 'sud.email',
            'id' => 'sud.id',                    // NEW: ID column
            'created_at' => 'ur.created_at',     // NEW: Created At column
            'number' => 'sud.number',
            'location' => 'ur.location_status',
            'budget' => 'ur.budget',
            'source_of_lead' => 'LOWER(TRIM(sud.source_of_lead))',
            'status' => 'ur.status',
            'assign_project_name' => 'ur.assign_project_name',
            'user' => 'ur.user_unique_id',
            'lead_identity' => 'ur.lead_identity',
        ];

        foreach ($multiFilters as $key => $value) {
            if ($value === '' || $value === null || !isset($allowedFilters[$key])) continue;
            $field = $allowedFilters[$key];

            $normalizedValue = is_array($value) ? implode(',', $value) : (string)$value;
            if (strpos($normalizedValue, ',') !== false) {
                $values = array_values(array_filter(array_map('trim', explode(',', $normalizedValue)), function($v) { return $v !== ''; }));
                if (empty($values)) continue;
                $ph = [];
                foreach ($values as $i => $val) {
                    $p = ":filter_{$key}_{$i}";
                    $ph[] = $p;
                    $bindings[$p] = [$val, PDO::PARAM_STR];
                }
                $whereClauses[] = "$field IN (" . implode(',', $ph) . ")";
            } else {
                $p = ":filter_{$key}";
                $bindings[$p] = [
                    ($key === 'source_of_lead') ? ('%' . strtolower(trim($normalizedValue)) . '%') : ('%' . $normalizedValue . '%'),
                    PDO::PARAM_STR
                ];
                $whereClauses[] = "$field LIKE $p";
            }
        }

        // Global table searchQuery (match table behavior)
        if ($searchQuery !== '') {
            $whereClauses[] = "(
                sud.name LIKE :global_search OR
                sud.email LIKE :global_search OR
                sud.number LIKE :global_search OR
                ur.assign_project_name LIKE :global_search OR
                ur.user_unique_id LIKE :global_search OR
                sud.source_of_lead LIKE :global_search
            )";
            $bindings[':global_search'] = ['%' . $searchQuery . '%', PDO::PARAM_STR];
        }

        // Apply option query 'q' against the value field
        if ($q !== '') {
            if ($resolvedKey === 'source_of_lead') {
                $whereClauses[] = "LOWER(TRIM($valueField)) LIKE :q";
                $bindings[':q'] = ['%' . strtolower(trim($q)) . '%', PDO::PARAM_STR];
            } else {
                $whereClauses[] = "$valueField LIKE :q";
                $bindings[':q'] = ['%' . $q . '%', PDO::PARAM_STR];
            }
        }

        // OPTIMIZED: Build query with history_h filter first for performance
        $sql = "
            SELECT DISTINCT $valueField AS value
            FROM shi_upload_data sud
            LEFT JOIN user_remarks ur ON sud.id = ur.upload_data_id AND ur.history_h = 0
        ";
        // OPTIMIZED: Add history_h filter first, then other conditions
        $allWhereClauses = ["ur.history_h = 0", "$valueField IS NOT NULL", "TRIM($valueField) <> ''"];
        if (!empty($whereClauses)) {
            $allWhereClauses = array_merge($allWhereClauses, $whereClauses);
        }
        $sql .= " WHERE " . implode(' AND ', $allWhereClauses);
        $sql .= " ORDER BY value ASC LIMIT :limit OFFSET :offset";

        $stmt = $conn->prepare($sql);
        foreach ($bindings as $param => $info) {
            [$val, $type] = $info;
            $stmt->bindValue($param, $val, $type);
        }
        $stmt->bindValue(':limit', (int)$perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $values = [];
        foreach ($rows as $r) {
            $v = isset($r['value']) ? trim((string)$r['value']) : '';
            if ($v !== '') $values[] = $v;
        }

        // "hasMore" probe
        $hasMore = count($values) === $perPage;
        echo json_encode(['values' => $values, 'hasMore' => $hasMore]);
    } catch (Exception $e) {
        echo json_encode(['values' => [], 'hasMore' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
// Handle Count Logic
if (isset($_GET['get_data']) && $_GET['get_data'] == 1) {
    try {
        // Check if the toggle is enabled and if user can use it
        $toggleEnabled = isset($_GET['toggle_enabled']) && $_GET['toggle_enabled'] == '1';
        $filterUserParam = isset($_GET['filterUser']) ? trim($_GET['filterUser']) : '';
        $managerViewParam = isset($_GET['managerView']) ? filter_var($_GET['managerView'], FILTER_VALIDATE_BOOLEAN) : false;
        
        // Get additional filter parameters to match table filtering
        $dateFrom = isset($_GET['dateFrom']) ? $_GET['dateFrom'] : '';
        $dateTo = isset($_GET['dateTo']) ? $_GET['dateTo'] : '';
        $multiFilters = isset($_GET['multiFilters']) ? json_decode($_GET['multiFilters'], true) : [];
        $activeFilter = isset($_GET['filter']) ? $_GET['filter'] : '';
        $searchQuery = isset($_GET['searchQuery']) ? $_GET['searchQuery'] : '';
        $columnsSearch = isset($_GET['columns']) ? $_GET['columns'] : [];
        
        // Get current user's role and check if they can use manager toggle
        $stmt = $conn->prepare("SELECT user_type FROM accounts WHERE tablename = :tablename");
        $stmt->bindParam(':tablename', $useruniqueId, PDO::PARAM_STR);
        $stmt->execute();
        $currentUserRow = $stmt->fetch(PDO::FETCH_ASSOC);
        $currentUserType = $currentUserRow['user_type'] ?? 'user';
        $normalizedRole = normalize_role_update($currentUserType);
        $canUseManagerToggle = ($normalizedRole !== 'user');
        $accessibleUsers = get_accessible_users_update($conn, $useruniqueId, $currentUserType);
        
        // Build additional WHERE conditions based on filters
        $additionalConditions = [];
        $commonBindings = [];
        
        // Date range filter
        if (!empty($dateFrom) && !empty($dateTo)) {
            $dateFromParam = strlen($dateFrom) === 10 ? $dateFrom . ' 00:00:00' : $dateFrom;
            $dateToParam = strlen($dateTo) === 10 ? $dateTo . ' 23:59:59' : $dateTo;
            $additionalConditions[] = "ur.created_at BETWEEN :dateFrom AND :dateTo";
            $commonBindings[':dateFrom'] = [$dateFromParam, PDO::PARAM_STR];
            $commonBindings[':dateTo'] = [$dateToParam, PDO::PARAM_STR];
        }
        
        // Multi-filters (status, source, etc.) - OPTIMIZED: All columns now support filtering
        $multiFilterMap = [
            'status'             => ['field' => 'ur.status',                    'type' => 'equals'],
            'source_of_lead'     => ['field' => 'LOWER(TRIM(sud.source_of_lead))', 'type' => 'lower_like'],
            'name'               => ['field' => 'sud.name',                     'type' => 'like'],
            'email'              => ['field' => 'sud.email',                    'type' => 'like'],
            'number'             => ['field' => 'sud.number',                   'type' => 'like'],
            'location'           => ['field' => 'ur.location_status',           'type' => 'like'],
            'budget'             => ['field' => 'ur.budget',                    'type' => 'like'],
            'assign_project_name'=> ['field' => 'ur.assign_project_name',       'type' => 'like'],
            'user'               => ['field' => 'ur.user_unique_id',            'type' => 'like'],
            'lead_identity'      => ['field' => 'ur.lead_identity',             'type' => 'like'],
            'id'                 => ['field' => 'sud.id',                      'type' => 'equals'],  // NEW: ID column filtering
            'created_at'         => ['field' => 'ur.created_at',               'type' => 'like']     // NEW: Created At column filtering
        ];

        foreach ($multiFilters as $key => $value) {
            if ($value === '' || $value === 'All' || !isset($multiFilterMap[$key])) {
                continue;
            }

            // OPTIMIZED: Skip ID and created_at if they're already processed as column searches
            // This prevents duplicate filtering and ensures column search takes precedence
            $skipIfInColumns = false;
            if (($key === 'id' || $key === 'created_at') && !empty($columnsSearch)) {
                foreach ($columnsSearch as $colIdx => $col) {
                    if (!empty($col['search']['value'])) {
                        if (($key === 'id' && $colIdx == 3) || ($key === 'created_at' && $colIdx == 4)) {
                            $skipIfInColumns = true;
                            break;
                        }
                    }
                }
            }
            if ($skipIfInColumns) {
                continue; // Skip multiFilter processing if already handled as column search (prevents duplicate WHERE clauses)
            }

            $filterMeta = $multiFilterMap[$key];
            $field = $filterMeta['field'];
            $comparisonType = $filterMeta['type'];

            // OPTIMIZED: Handle CSV strings from header dropdown filters (e.g., "value1,value2")
            // Also handle arrays and single values
            if (is_array($value)) {
                $rawValues = $value;
            } elseif (is_string($value) && strpos($value, ',') !== false) {
                // Split CSV string into array (header dropdown filters use CSV format)
                $rawValues = array_map('trim', explode(',', $value));
            } else {
                $rawValues = [$value];
            }
            
            $cleanValues = [];
            foreach ($rawValues as $rawValue) {
                if ($rawValue !== '' && $rawValue !== 'All') {
                    $cleanValues[] = $rawValue;
                }
            }

            if (empty($cleanValues)) {
                continue;
            }

            // Build conditions and bindings
            if ($comparisonType === 'equals') {
                $placeholders = [];
                foreach ($cleanValues as $idx => $cleanValue) {
                    $paramName = ":filter_{$key}_{$idx}";
                    $placeholders[] = $paramName;
                    $commonBindings[$paramName] = [$cleanValue, PDO::PARAM_STR];
                }
                $additionalConditions[] = sprintf('%s IN (%s)', $field, implode(',', $placeholders));
            } else {
                $orConditions = [];
                foreach ($cleanValues as $idx => $cleanValue) {
                    $paramName = ":filter_{$key}_{$idx}";
                    $orConditions[] = sprintf('%s = %s', $field, $paramName);

                    if ($comparisonType === 'lower_like') {
                        $commonBindings[$paramName] = [strtolower(trim($cleanValue)), PDO::PARAM_STR];
                    } else {
                        $commonBindings[$paramName] = [$cleanValue, PDO::PARAM_STR];
                    }
                }
                if (!empty($orConditions)) {
                    $additionalConditions[] = '(' . implode(' OR ', $orConditions) . ')';
                }
            }
        }

        // Add column-specific search filters
        if (!empty($columnsSearch) && is_array($columnsSearch)) {
            $columnFields = [
                1 => 'sud.name',
                2 => 'ur.assign_project_name',
                3 => 'sud.id',
                4 => 'ur.created_at',
                5 => 'sud.email',
                6 => 'ur.location_status',
                7 => 'ur.budget',
                8 => 'ur.user_unique_id',
                9 => 'sud.source_of_lead'
            ];
            
            foreach ($columnsSearch as $i => $col) {
                if (!empty($col['search']['value']) && isset($columnFields[$i])) {
                    $searchVal = $col['search']['value'];
                    $field = $columnFields[$i];
                    
                    if (strpos($searchVal, '|') !== false) {
                        // Multiple values (OR condition) - OPTIMIZED for ID and Created At
                        $vals = explode('|', $searchVal);
                        $placeholders = [];
                        foreach ($vals as $idx => $val) {
                            $paramName = ":col_search_{$i}_{$idx}";
                            $placeholders[] = $paramName;
                            // OPTIMIZED: For ID (column 3) and Created At (column 4), ensure exact match
                            $commonBindings[$paramName] = [$val, PDO::PARAM_STR];
                        }
                        $additionalConditions[] = "$field IN (" . implode(',', $placeholders) . ")";
                    } else {
                        // Single value (exact match) - OPTIMIZED for ID and Created At
                        $paramName = ":col_search_{$i}";
                        if ($i == 3 || $i == 4) {
                            // ID (3) and Created At (4) columns - exact match
                            $additionalConditions[] = "$field = $paramName";
                        } else {
                            $additionalConditions[] = "$field = $paramName";
                        }
                        $commonBindings[$paramName] = [$searchVal, PDO::PARAM_STR];
                    }
                }
            }
        }

        // Add global search query
        if (!empty($searchQuery)) {
            $searchParts = [
                "sud.name LIKE :global_search",
                "sud.email LIKE :global_search",
                "sud.number LIKE :global_search",
                "sud.location LIKE :global_search",
                "sud.source_of_lead LIKE :global_search",
                "ur.status LIKE :global_search",
                "ur.assign_project_name LIKE :global_search",
                "ur.user_unique_id LIKE :global_search"
            ];
            $searchTerm = '%' . $searchQuery . '%';
            $commonBindings[':global_search'] = [$searchTerm, PDO::PARAM_STR];
            $additionalConditions[] = '(' . implode(' OR ', $searchParts) . ')';
        }

        $additionalWhereSQL = '';
        if (!empty($additionalConditions)) {
            $additionalWhereSQL = ' AND ' . implode(' AND ', $additionalConditions);
        }

        $bindCommonParams = function(PDOStatement $statement) use ($commonBindings) {
            foreach ($commonBindings as $param => $details) {
                $statement->bindValue($param, $details[0], $details[1]);
            }
        };

        // Base condition for where the user is assigned
        $forceSpecificUserForCount = false;
        if ($filterUserParam !== '' && in_array($filterUserParam, $accessibleUsers, true)) {
            $userCondition = "ur.user_unique_id = :forcedUser";
            $useAccessibleUsersForCount = false;
            $forceSpecificUserForCount = true;
        } elseif ($toggleEnabled && $canUseManagerToggle) {
            // Team view: get subordinates only (exclude current user)
            // Remove current user from accessible users for team view
            $subordinateUsers = array_filter($accessibleUsers, function($user) use ($useruniqueId) {
                return $user !== $useruniqueId;
            });
            
            if (count($subordinateUsers) > 0) {
                $placeholders = [];
                for ($i = 0; $i < count($subordinateUsers); $i++) {
                    $placeholders[] = ":accessible_user_count_$i";
                }
                $userCondition = "ur.user_unique_id IN (" . implode(',', $placeholders) . ")";
                $useAccessibleUsersForCount = true;
                $accessibleUsers = array_values($subordinateUsers); // Re-index array for binding
            } else {
                // User has no subordinates, show no data in team view
                $userCondition = "ur.user_unique_id = 'no_subordinates'";
                $useAccessibleUsersForCount = false;
            }
        } else {
            // Individual view or regular user
            $userCondition = "ur.user_unique_id = :userUniqueId";
            $useAccessibleUsersForCount = false;
        }

        // Fetch the count of rows where 'assign_to_user' matches the logged-in user or based on the toggle
        // Use DISTINCT to count unique leads only (avoid counting same lead multiple times)
        $sqlMyLeads = "
            SELECT COUNT(*) as myLeads 
            FROM user_remarks ur
            INNER JOIN shi_upload_data sud ON sud.id = ur.upload_data_id
            WHERE {$userCondition}
            AND ur.history_h = 0
            {$additionalWhereSQL}
        ";

        $stmtMyLeads = $conn->prepare($sqlMyLeads);
        
        // Bind parameters based on hierarchy
        if ($forceSpecificUserForCount && $filterUserParam !== '') {
            $stmtMyLeads->bindParam(':forcedUser', $filterUserParam, PDO::PARAM_STR);
        } elseif (isset($useAccessibleUsersForCount) && $useAccessibleUsersForCount) {
            for ($i = 0; $i < count($accessibleUsers); $i++) {
                $stmtMyLeads->bindValue(":accessible_user_count_$i", $accessibleUsers[$i], PDO::PARAM_STR);
            }
        } else {
            $stmtMyLeads->bindParam(':userUniqueId', $useruniqueId);
        }
        $bindCommonParams($stmtMyLeads);
        $stmtMyLeads->execute();
        $myLeadsResult = $stmtMyLeads->fetch(PDO::FETCH_ASSOC);

        // Fetch the total count of "Booked Leads" where status is "Converted"
        $sqlbooked = "
            SELECT COUNT(*) as bookedLeads 
            FROM user_remarks ur
            INNER JOIN shi_upload_data sud ON sud.id = ur.upload_data_id
            WHERE ur.status = 'Converted'
            AND {$userCondition}
            AND ur.history_h = 0
            {$additionalWhereSQL}
        ";
        $stmtbooked = $conn->prepare($sqlbooked);
        
        if ($forceSpecificUserForCount && $filterUserParam !== '') {
            $stmtbooked->bindParam(':forcedUser', $filterUserParam, PDO::PARAM_STR);
        } elseif (isset($useAccessibleUsersForCount) && $useAccessibleUsersForCount) {
            for ($i = 0; $i < count($accessibleUsers); $i++) {
                $stmtbooked->bindValue(":accessible_user_count_$i", $accessibleUsers[$i], PDO::PARAM_STR);
            }
        } else {
            $stmtbooked->bindParam(':userUniqueId', $useruniqueId);
        }
        $bindCommonParams($stmtbooked);
        $stmtbooked->execute();
        $bookedLeads = $stmtbooked->fetch(PDO::FETCH_ASSOC);

        // Fetch the count of "Dropped Leads" where ALL statuses for a lead are "Not Interested"
        $sqlDroppedLeads = "
            SELECT COUNT(*) as droppedLeads 
            FROM user_remarks ur 
            INNER JOIN shi_upload_data sud ON sud.id = ur.upload_data_id
            WHERE ur.status IN ('Not Interested', 'Fake')
            AND {$userCondition}
            AND ur.history_h = 0
            {$additionalWhereSQL}
        ";
        $stmtDroppedLeads = $conn->prepare($sqlDroppedLeads);
        
        if ($forceSpecificUserForCount && $filterUserParam !== '') {
            $stmtDroppedLeads->bindParam(':forcedUser', $filterUserParam, PDO::PARAM_STR);
        } elseif (isset($useAccessibleUsersForCount) && $useAccessibleUsersForCount) {
            for ($i = 0; $i < count($accessibleUsers); $i++) {
                $stmtDroppedLeads->bindValue(":accessible_user_count_$i", $accessibleUsers[$i], PDO::PARAM_STR);
            }
        } else {
            $stmtDroppedLeads->bindParam(':userUniqueId', $useruniqueId);
        }
        $bindCommonParams($stmtDroppedLeads);
        $stmtDroppedLeads->execute();
        $droppedLeadsResult = $stmtDroppedLeads->fetch(PDO::FETCH_ASSOC);


        // Fetch the count of "Active Leads" where the status is NOT "Not Interested", "Fake", "Already Booked", or "Converted" for any users
        $sqlActiveLeads = "
            SELECT COUNT(*) as activeLeads 
            FROM user_remarks ur
            INNER JOIN shi_upload_data sud ON sud.id = ur.upload_data_id
            WHERE ur.status NOT IN ('Not Interested', 'Fake', 'Already Booked', 'Converted')
            AND (ur.status IS NOT NULL AND ur.status != '') 
            AND {$userCondition}
            AND ur.history_h = 0
            {$additionalWhereSQL}
        ";
        $stmtActiveLeads = $conn->prepare($sqlActiveLeads);
        
        if ($forceSpecificUserForCount && $filterUserParam !== '') {
            $stmtActiveLeads->bindParam(':forcedUser', $filterUserParam, PDO::PARAM_STR);
        } elseif (isset($useAccessibleUsersForCount) && $useAccessibleUsersForCount) {
            for ($i = 0; $i < count($accessibleUsers); $i++) {
                $stmtActiveLeads->bindValue(":accessible_user_count_$i", $accessibleUsers[$i], PDO::PARAM_STR);
            }
        } else {
            $stmtActiveLeads->bindParam(':userUniqueId', $useruniqueId);
        }
        $bindCommonParams($stmtActiveLeads);
        $stmtActiveLeads->execute();
        $activeLeadsResult = $stmtActiveLeads->fetch(PDO::FETCH_ASSOC);

        // Fetch the count of "Fresh Leads" created in the last 5 days
        $sqlFreshLeads = "
            SELECT COUNT(*) as freshLeads 
            FROM user_remarks ur 
            INNER JOIN shi_upload_data sud ON sud.id = ur.upload_data_id
            WHERE ur.created_at >= DATE_SUB(CURDATE(), INTERVAL 1 DAY)
            AND {$userCondition}
            AND ur.history_h = 0
            {$additionalWhereSQL}
        ";
        $stmtFreshLeads = $conn->prepare($sqlFreshLeads);
        
        if ($forceSpecificUserForCount && $filterUserParam !== '') {
            $stmtFreshLeads->bindParam(':forcedUser', $filterUserParam, PDO::PARAM_STR);
        } elseif (isset($useAccessibleUsersForCount) && $useAccessibleUsersForCount) {
            for ($i = 0; $i < count($accessibleUsers); $i++) {
                $stmtFreshLeads->bindValue(":accessible_user_count_$i", $accessibleUsers[$i], PDO::PARAM_STR);
            }
        } else {
            $stmtFreshLeads->bindParam(':userUniqueId', $useruniqueId);
        }
        
        $bindCommonParams($stmtFreshLeads);
        $stmtFreshLeads->execute();
        $freshLeadsResult = $stmtFreshLeads->fetch(PDO::FETCH_ASSOC);        // Fetch the count of "Pending Leads" where any user has marked the status as "Pending"
        $sqlPendingLeads = "
            SELECT COUNT(*) as pendingLeads 
            FROM user_remarks ur 
            INNER JOIN shi_upload_data sud ON sud.id = ur.upload_data_id
            WHERE ur.status = 'Pending'
            AND {$userCondition}
            AND ur.history_h = 0
            {$additionalWhereSQL}
        ";
        $stmtPendingLeads = $conn->prepare($sqlPendingLeads);
        
        if ($forceSpecificUserForCount && $filterUserParam !== '') {
            $stmtPendingLeads->bindParam(':forcedUser', $filterUserParam, PDO::PARAM_STR);
        } elseif (isset($useAccessibleUsersForCount) && $useAccessibleUsersForCount) {
            for ($i = 0; $i < count($accessibleUsers); $i++) {
                $stmtPendingLeads->bindValue(":accessible_user_count_$i", $accessibleUsers[$i], PDO::PARAM_STR);
            }
        } else {
            $stmtPendingLeads->bindParam(':userUniqueId', $useruniqueId);
        }
        
        $bindCommonParams($stmtPendingLeads);
        $stmtPendingLeads->execute();
        $pendingLeadsResult = $stmtPendingLeads->fetch(PDO::FETCH_ASSOC);

        // Fetch the count of "Follow Up" where any user has marked the status as "Follow Up"
        $sqlFollowLeads = "
            SELECT COUNT(*) as followupLeads 
            FROM user_remarks ur
            INNER JOIN shi_upload_data sud ON sud.id = ur.upload_data_id
            WHERE ur.status = 'Follow Up'
            AND {$userCondition}
            AND ur.history_h = 0
            {$additionalWhereSQL}
        ";
        $stmtFollowLeads = $conn->prepare($sqlFollowLeads);
        
        if ($forceSpecificUserForCount && $filterUserParam !== '') {
            $stmtFollowLeads->bindParam(':forcedUser', $filterUserParam, PDO::PARAM_STR);
        } elseif (isset($useAccessibleUsersForCount) && $useAccessibleUsersForCount) {
            for ($i = 0; $i < count($accessibleUsers); $i++) {
                $stmtFollowLeads->bindValue(":accessible_user_count_$i", $accessibleUsers[$i], PDO::PARAM_STR);
            }
        } else {
            $stmtFollowLeads->bindParam(':userUniqueId', $useruniqueId);
        }
        
        $bindCommonParams($stmtFollowLeads);
        $stmtFollowLeads->execute();
        $followLeadsResult = $stmtFollowLeads->fetch(PDO::FETCH_ASSOC);

        // Fetch the count of "Overdue" leads
        $sqlOverdueLeads = "
            SELECT COUNT(*) as overdueLeads 
            FROM user_remarks ur
            INNER JOIN shi_upload_data sud ON sud.id = ur.upload_data_id
            WHERE ur.status IN ('Follow Up', 'RNR', 'Call Back', 'Pending', 'Interested', 'Fix Site Visit', 'Site Visit Done', 'Re site visit', 'Not Connected')
            AND ur.status != 'Converted'
            AND ur.status != 'Already Booked'
            AND ur.follow_up_date IS NOT NULL 
            AND ur.follow_up_time IS NOT NULL 
            AND CONCAT(ur.follow_up_date, ' ', ur.follow_up_time) < DATE_SUB(NOW(), INTERVAL 1 DAY)
            AND {$userCondition}
            AND ur.history_h = 0
            {$additionalWhereSQL}
        ";
        $stmtOverdueLeads = $conn->prepare($sqlOverdueLeads);
        
        if ($forceSpecificUserForCount && $filterUserParam !== '') {
            $stmtOverdueLeads->bindParam(':forcedUser', $filterUserParam, PDO::PARAM_STR);
        } elseif (isset($useAccessibleUsersForCount) && $useAccessibleUsersForCount) {
            for ($i = 0; $i < count($accessibleUsers); $i++) {
                $stmtOverdueLeads->bindValue(":accessible_user_count_$i", $accessibleUsers[$i], PDO::PARAM_STR);
            }
        } else {
            $stmtOverdueLeads->bindParam(':userUniqueId', $useruniqueId);
        }
    $bindCommonParams($stmtOverdueLeads);
    $stmtOverdueLeads->execute();
        $overdueLeadsResult = $stmtOverdueLeads->fetch(PDO::FETCH_ASSOC);

        // Fetch the count for today's collection
        $todayDate = date('Y-m-d');
        $todayStart = $todayDate . ' 00:00:00';
        $todayEnd = $todayDate . ' 23:59:59';
        $statuses = "('Follow Up', 'Interested', 'Call Back', 'RNR', 'Fix Site Visit')";

        $sqlTodayCollection = "
            SELECT COUNT(*) as todayCollection 
            FROM user_remarks ur
            INNER JOIN shi_upload_data sud ON sud.id = ur.upload_data_id
            WHERE {$userCondition}
            AND ur.status IN {$statuses}
            AND (
                (
                    ur.follow_up_date IS NOT NULL
                    AND ur.follow_up_date >= :todayStart
                    AND ur.follow_up_date < :todayEnd
                )
                OR (
                    JSON_TYPE(ur.history) = 'ARRAY'
                    AND JSON_LENGTH(ur.history) > 0
                    AND STR_TO_DATE(
                        JSON_UNQUOTE(
                            JSON_EXTRACT(ur.history, CONCAT('$[', JSON_LENGTH(ur.history) - 1, '].followUpDate'))
                        ), '%Y-%m-%d'
                    ) >= :todayStart
                    AND STR_TO_DATE(
                        JSON_UNQUOTE(
                            JSON_EXTRACT(ur.history, CONCAT('$[', JSON_LENGTH(ur.history) - 1, '].followUpDate'))
                        ), '%Y-%m-%d'
                    ) < :todayEnd
                )
            )
            AND ur.history_h = 0
            {$additionalWhereSQL}
        ";
        $stmtTodayCollection = $conn->prepare($sqlTodayCollection);
        
        if ($forceSpecificUserForCount && $filterUserParam !== '') {
            $stmtTodayCollection->bindParam(':forcedUser', $filterUserParam, PDO::PARAM_STR);
        } elseif (isset($useAccessibleUsersForCount) && $useAccessibleUsersForCount) {
            for ($i = 0; $i < count($accessibleUsers); $i++) {
                $stmtTodayCollection->bindValue(":accessible_user_count_$i", $accessibleUsers[$i], PDO::PARAM_STR);
            }
        } else {
            $stmtTodayCollection->bindParam(':userUniqueId', $useruniqueId);
        }
    $bindCommonParams($stmtTodayCollection);
        $stmtTodayCollection->bindParam(':todayStart', $todayStart);
        $stmtTodayCollection->bindParam(':todayEnd', $todayEnd);
        $stmtTodayCollection->execute();
        $todayCollectionResult = $stmtTodayCollection->fetch(PDO::FETCH_ASSOC);

        // Fetch the count for Google Ads and Facebook Ads leads (respecting toggle state)
        $sqlAdLeads = "
            SELECT COUNT(*) as adLeads 
            FROM shi_upload_data sud
            INNER JOIN user_remarks ur ON sud.id = ur.upload_data_id
            WHERE LOWER(TRIM(sud.source_of_lead)) IN ('google ads', 'facebook ads', 'magicbricks ads', '99acres ads', 'housing.com ads')
            AND {$userCondition}
            AND ur.history_h = 0
            {$additionalWhereSQL}
        ";
        $stmtAdLeads = $conn->prepare($sqlAdLeads);
        
        if ($forceSpecificUserForCount && $filterUserParam !== '') {
            $stmtAdLeads->bindParam(':forcedUser', $filterUserParam, PDO::PARAM_STR);
        } elseif (isset($useAccessibleUsersForCount) && $useAccessibleUsersForCount) {
            for ($i = 0; $i < count($accessibleUsers); $i++) {
                $stmtAdLeads->bindValue(":accessible_user_count_$i", $accessibleUsers[$i], PDO::PARAM_STR);
            }
        } else {
            $stmtAdLeads->bindParam(':userUniqueId', $useruniqueId);
        }
        $bindCommonParams($stmtAdLeads);
        $stmtAdLeads->execute();
        $adLeadsResult = $stmtAdLeads->fetch(PDO::FETCH_ASSOC);

        // Fetch the count for leads where source_of_lead is NOT ads (respecting toggle state)
        $sqlOtherLeads = "
            SELECT COUNT(*) as otherLeads 
            FROM shi_upload_data sud
            INNER JOIN user_remarks ur ON sud.id = ur.upload_data_id
            WHERE (
                sud.source_of_lead IS NULL OR 
                LOWER(TRIM(sud.source_of_lead)) NOT IN ('google ads', 'facebook ads', 'magicbricks ads', '99acres ads', 'housing.com ads')
            )
            AND {$userCondition}
            AND ur.history_h = 0
            {$additionalWhereSQL}
        ";
        $stmtOtherLeads = $conn->prepare($sqlOtherLeads);
        
        if ($forceSpecificUserForCount && $filterUserParam !== '') {
            $stmtOtherLeads->bindParam(':forcedUser', $filterUserParam, PDO::PARAM_STR);
        } elseif (isset($useAccessibleUsersForCount) && $useAccessibleUsersForCount) {
            for ($i = 0; $i < count($accessibleUsers); $i++) {
                $stmtOtherLeads->bindValue(":accessible_user_count_$i", $accessibleUsers[$i], PDO::PARAM_STR);
            }
        } else {
            $stmtOtherLeads->bindParam(':userUniqueId', $useruniqueId);
        }
    $bindCommonParams($stmtOtherLeads);
    $stmtOtherLeads->execute();
        $otherLeadsResult = $stmtOtherLeads->fetch(PDO::FETCH_ASSOC);


        // Return all counts as a JSON object
        echo json_encode([
            'myLeads' => $myLeadsResult['myLeads'],
            'bookedLeads' => $bookedLeads['bookedLeads'],
            'droppedLeads' => $droppedLeadsResult['droppedLeads'],
            'activeLeads' => $activeLeadsResult['activeLeads'],
            'freshLeads' => $freshLeadsResult['freshLeads'],
            'pendingLeads' => $pendingLeadsResult['pendingLeads'],
            'followupLeads' => $followLeadsResult['followupLeads'],
            'overdueLeads' => $overdueLeadsResult['overdueLeads'],
            'today_collection' => $todayCollectionResult['todayCollection'],
            'paidAds' => $adLeadsResult['adLeads'], // New count added here
            'shi_d' => $otherLeadsResult['otherLeads']  // New "SHI-D" Count
        ]);
    } catch (PDOException $e) {
        // Return 0 for all counts in case of error
        echo json_encode([ 
            'myLeads' => 0, 
            'bookedLeads' => 0, 
            'droppedLeads' => 0,
            'activeLeads' => 0, 
            'freshLeads' => 0, 
            'pendingLeads' => 0,
            'followupLeads' => 0,
            'overdueLeads' => 0,
            'today_collection' => 0,
            'paidAds' => 0, // Default to 0 in case of error
            'shi_d' => 0, // Default to 0 in case of error
        ]);
    }
    exit;
}
// Handle Count Logic End

// ============================================================================
// SECTION 3: TAG COUNT API (OPTIMIZED FOR 1L+ RECORDS)
// ============================================================================
// CONSOLIDATED endpoint - Returns ALL tag counts in a single API call
// This replaces 11 individual API calls with just 1 for maximum performance
// ============================================================================

// Handle ALL Tag Counts in Single Request - SUPER OPTIMIZED ENDPOINT
if (isset($_GET['get_all_tag_counts']) && $_GET['get_all_tag_counts'] == '1') {
    try {
        // Get filter parameters - Parse once, reuse for all counts
        $toggleEnabled = isset($_GET['toggle_enabled']) && $_GET['toggle_enabled'] == '1';
        $filterUserParam = isset($_GET['filterUser']) ? trim($_GET['filterUser']) : '';
        $managerViewParam = isset($_GET['managerView']) ? filter_var($_GET['managerView'], FILTER_VALIDATE_BOOLEAN) : false;
        $dateFrom = isset($_GET['dateFrom']) ? $_GET['dateFrom'] : '';
        $dateTo = isset($_GET['dateTo']) ? $_GET['dateTo'] : '';
        $multiFilters = isset($_GET['multiFilters']) ? json_decode($_GET['multiFilters'], true) : [];
        $searchQuery = isset($_GET['searchQuery']) ? trim($_GET['searchQuery']) : '';
        $columnsSearch = isset($_GET['columns']) ? $_GET['columns'] : [];
        
        // Cache user role lookup
        $stmt = $conn->prepare("SELECT user_type FROM accounts WHERE tablename = :tablename LIMIT 1");
        $stmt->bindParam(':tablename', $useruniqueId, PDO::PARAM_STR);
        $stmt->execute();
        $currentUserRow = $stmt->fetch(PDO::FETCH_ASSOC);
        $currentUserType = $currentUserRow['user_type'] ?? 'user';
        $normalizedRole = normalize_role_update($currentUserType);
        $canUseManagerToggle = ($normalizedRole !== 'user');
        
        // Cache accessible users
        $accessibleUsers = get_accessible_users_update($conn, $useruniqueId, $currentUserType);
        
        // Build additional WHERE conditions based on filters
        $additionalConditions = [];
        $commonBindings = [];
        
        // Date range filter - Check both GET params AND multiFilters
        if (!empty($dateFrom) && !empty($dateTo)) {
            $dateFromParam = strlen($dateFrom) === 10 ? $dateFrom . ' 00:00:00' : $dateFrom;
            $dateToParam = strlen($dateTo) === 10 ? $dateTo . ' 23:59:59' : $dateTo;
            $additionalConditions[] = "ur.created_at BETWEEN :dateFrom AND :dateTo";
            $commonBindings[':dateFrom'] = [$dateFromParam, PDO::PARAM_STR];
            $commonBindings[':dateTo'] = [$dateToParam, PDO::PARAM_STR];
        } elseif (isset($multiFilters['start_date']) && isset($multiFilters['end_date']) && 
                  !empty($multiFilters['start_date']) && !empty($multiFilters['end_date'])) {
            // Fallback: use dates from multiFilters if GET params are empty
            $dateFromParam = strlen($multiFilters['start_date']) === 10 ? $multiFilters['start_date'] . ' 00:00:00' : $multiFilters['start_date'];
            $dateToParam = strlen($multiFilters['end_date']) === 10 ? $multiFilters['end_date'] . ' 23:59:59' : $multiFilters['end_date'];
            $additionalConditions[] = "ur.created_at BETWEEN :dateFrom AND :dateTo";
            $commonBindings[':dateFrom'] = [$dateFromParam, PDO::PARAM_STR];
            $commonBindings[':dateTo'] = [$dateToParam, PDO::PARAM_STR];
        }
        
        // Multi-filters (INCLUDING status for tag counts)
        $multiFilterMap = [
            'status'             => ['field' => 'ur.status',                    'type' => 'equals'], // INCLUDED: Status filter affects tag counts
            'source_of_lead'     => ['field' => 'LOWER(TRIM(sud.source_of_lead))', 'type' => 'lower_like'],
            'name'               => ['field' => 'sud.name',                     'type' => 'like'],
            'email'              => ['field' => 'sud.email',                   'type' => 'like'],
            'number'             => ['field' => 'sud.number',                  'type' => 'like'],
            'location'           => ['field' => 'ur.location_status',          'type' => 'like'],
            'budget'             => ['field' => 'ur.budget',                   'type' => 'like'],
            'assign_project_name' => ['field' => 'ur.assign_project_name',     'type' => 'like'],
            'user'               => ['field' => 'ur.user_unique_id',            'type' => 'equals'],
            'lead_identity'      => ['field' => 'ur.lead_identity',             'type' => 'equals'],
            'id'                 => ['field' => 'sud.id',                      'type' => 'equals'],
            'created_at'         => ['field' => 'ur.created_at',               'type' => 'equals']
        ];
        
        foreach ($multiFilters as $key => $value) {
            if ($value === '' || $value === 'All' || !isset($multiFilterMap[$key])) {
                continue;
            }
            
            $filterMeta = $multiFilterMap[$key];
            $field = $filterMeta['field'];
            $comparisonType = $filterMeta['type'];
            
            if (is_array($value)) {
                $rawValues = $value;
            } elseif (is_string($value) && strpos($value, ',') !== false) {
                $rawValues = array_map('trim', explode(',', $value));
            } else {
                $rawValues = [$value];
            }
            
            $cleanValues = [];
            foreach ($rawValues as $rawValue) {
                if ($rawValue !== '' && $rawValue !== 'All') {
                    $cleanValues[] = $rawValue;
                }
            }
            
            if (empty($cleanValues)) {
                continue;
            }
            
            if ($comparisonType === 'equals') {
                $placeholders = [];
                foreach ($cleanValues as $idx => $cleanValue) {
                    $paramName = ":filter_{$key}_{$idx}";
                    $placeholders[] = $paramName;
                    if ($key === 'id') {
                        $commonBindings[$paramName] = [is_numeric($cleanValue) ? (int)$cleanValue : $cleanValue, is_numeric($cleanValue) ? PDO::PARAM_INT : PDO::PARAM_STR];
                    } else {
                        $commonBindings[$paramName] = [$cleanValue, PDO::PARAM_STR];
                    }
                }
                $additionalConditions[] = sprintf('%s IN (%s)', $field, implode(',', $placeholders));
            } else {
                $orConditions = [];
                foreach ($cleanValues as $idx => $cleanValue) {
                    $paramName = ":filter_{$key}_{$idx}";
                    $orConditions[] = sprintf('%s = %s', $field, $paramName);
                    
                    if ($comparisonType === 'lower_like') {
                        $commonBindings[$paramName] = [strtolower(trim($cleanValue)), PDO::PARAM_STR];
                    } else {
                        $commonBindings[$paramName] = [$cleanValue, PDO::PARAM_STR];
                    }
                }
                if (!empty($orConditions)) {
                    $additionalConditions[] = '(' . implode(' OR ', $orConditions) . ')';
                }
            }
        }
        
        // Global search query
        if (!empty($searchQuery)) {
            $searchParts = [
                "sud.name LIKE :global_search",
                "sud.email LIKE :global_search",
                "sud.number LIKE :global_search",
                "sud.location LIKE :global_search",
                "sud.source_of_lead LIKE :global_search",
                "ur.status LIKE :global_search",
                "ur.assign_project_name LIKE :global_search",
                "ur.user_unique_id LIKE :global_search"
            ];
            $searchTerm = '%' . $searchQuery . '%';
            $commonBindings[':global_search'] = [$searchTerm, PDO::PARAM_STR];
            $additionalConditions[] = '(' . implode(' OR ', $searchParts) . ')';
        }
        
        $additionalWhereSQL = '';
        if (!empty($additionalConditions)) {
            $additionalWhereSQL = ' AND ' . implode(' AND ', $additionalConditions);
        }
        
        // Build user condition
        $forceSpecificUser = false;
        $useAccessibleUsers = false;
        if ($filterUserParam !== '' && in_array($filterUserParam, $accessibleUsers, true)) {
            $userCondition = "ur.user_unique_id = :forcedUser";
            $forceSpecificUser = true;
        } elseif ($toggleEnabled && $canUseManagerToggle) {
            $subordinateUsers = array_filter($accessibleUsers, function($user) use ($useruniqueId) {
                return $user !== $useruniqueId;
            });
            
            if (count($subordinateUsers) > 0) {
                $placeholders = [];
                for ($i = 0; $i < count($subordinateUsers); $i++) {
                    $placeholders[] = ":accessible_user_$i";
                }
                $userCondition = "ur.user_unique_id IN (" . implode(',', $placeholders) . ")";
                $useAccessibleUsers = true;
                $accessibleUsers = array_values($subordinateUsers);
            } else {
                $userCondition = "ur.user_unique_id = 'no_subordinates'";
            }
        } else {
            $userCondition = "ur.user_unique_id = :userUniqueId";
        }
        
        // Today's date for specific counts
        $todayDate = date('Y-m-d');
        $todayStart = $todayDate . ' 00:00:00';
        $todayEnd = $todayDate . ' 23:59:59';
        
        // SINGLE OPTIMIZED QUERY - Get all counts at once using conditional aggregation
        $sql = "SELECT 
            COUNT(*) as myLeads,
            SUM(CASE WHEN ur.status = 'Converted' THEN 1 ELSE 0 END) as bookedLeads,
            SUM(CASE WHEN ur.status NOT IN ('Not Interested', 'Fake', 'Already Booked', 'Converted') AND ur.status IS NOT NULL AND ur.status != '' THEN 1 ELSE 0 END) as activeLeads,
            SUM(CASE WHEN ur.created_at >= DATE_SUB(CURDATE(), INTERVAL 1 DAY) THEN 1 ELSE 0 END) as freshLeads,
            SUM(CASE WHEN ur.status = 'Pending' THEN 1 ELSE 0 END) as pendingLeads,
            SUM(CASE WHEN ur.status IN ('Not Interested', 'Fake') THEN 1 ELSE 0 END) as droppedLeads,
            SUM(CASE WHEN ur.status = 'Follow Up' THEN 1 ELSE 0 END) as followLeads,
            SUM(CASE WHEN ur.status IN ('Follow Up', 'RNR', 'Call Back', 'Pending', 'Interested', 'Fix Site Visit', 'Site Visit Done', 'Re site visit', 'Not Connected') 
                AND ur.status != 'Converted' AND ur.status != 'Already Booked'
                AND ur.follow_up_date IS NOT NULL AND ur.follow_up_time IS NOT NULL 
                AND CONCAT(ur.follow_up_date, ' ', ur.follow_up_time) < DATE_SUB(NOW(), INTERVAL 1 DAY) THEN 1 ELSE 0 END) as overdueLeads,
            SUM(CASE WHEN ur.status IN ('Follow Up', 'Interested', 'Call Back', 'RNR', 'Fix Site Visit')
                AND ur.follow_up_date IS NOT NULL 
                AND ur.follow_up_date >= :todayStart AND ur.follow_up_date < :todayEnd THEN 1 ELSE 0 END) as today_collection,
            SUM(CASE WHEN LOWER(TRIM(sud.source_of_lead)) IN ('google ads', 'facebook ads', 'magicbricks ads', '99acres ads', 'housing.com ads') THEN 1 ELSE 0 END) as paidAds,
            SUM(CASE WHEN sud.source_of_lead IS NULL OR LOWER(TRIM(sud.source_of_lead)) NOT IN ('google ads', 'facebook ads', 'magicbricks ads', '99acres ads', 'housing.com ads') THEN 1 ELSE 0 END) as shi_d
        FROM user_remarks ur 
        INNER JOIN shi_upload_data sud ON sud.id = ur.upload_data_id 
        WHERE ur.history_h = 0 AND {$userCondition} {$additionalWhereSQL}";
        
        $stmt = $conn->prepare($sql);
        
        // Bind user parameter
        if ($forceSpecificUser && $filterUserParam !== '') {
            $stmt->bindParam(':forcedUser', $filterUserParam, PDO::PARAM_STR);
        } elseif ($useAccessibleUsers) {
            for ($i = 0; $i < count($accessibleUsers); $i++) {
                $stmt->bindValue(":accessible_user_$i", $accessibleUsers[$i], PDO::PARAM_STR);
            }
        } else {
            $stmt->bindParam(':userUniqueId', $useruniqueId);
        }
        
        // Bind today dates
        $stmt->bindParam(':todayStart', $todayStart);
        $stmt->bindParam(':todayEnd', $todayEnd);
        
        // Bind common filter parameters
        foreach ($commonBindings as $param => $details) {
            $stmt->bindValue($param, $details[0], $details[1]);
        }
        
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Return all counts in single response
        echo json_encode([
            'status' => 'success',
            'counts' => [
                'myLeads' => (int)($result['myLeads'] ?? 0),
                'bookedLeads' => (int)($result['bookedLeads'] ?? 0),
                'activeLeads' => (int)($result['activeLeads'] ?? 0),
                'freshLeads' => (int)($result['freshLeads'] ?? 0),
                'pendingLeads' => (int)($result['pendingLeads'] ?? 0),
                'droppedLeads' => (int)($result['droppedLeads'] ?? 0),
                'followLeads' => (int)($result['followLeads'] ?? 0),
                'overdueLeads' => (int)($result['overdueLeads'] ?? 0),
                'today_collection' => (int)($result['today_collection'] ?? 0),
                'paidAds' => (int)($result['paidAds'] ?? 0),
                'shi_d' => (int)($result['shi_d'] ?? 0)
            ]
        ]);
    } catch (PDOException $e) {
        error_log("Error fetching all tag counts: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'error' => $e->getMessage(), 'counts' => []]);
    }
    exit;
}

// Individual tag count endpoints (kept for backwards compatibility but deprecated)
// Handles: myLeads, bookedLeads, activeLeads, freshLeads, pendingLeads,
//          droppedLeads, followLeads, overdueLeads, today_collection,
//          paidAds, shi_d
// ============================================================================

// Handle Individual Tag Count Requests - DEPRECATED (use get_all_tag_counts instead)
if (isset($_GET['get_tag_count']) && !empty($_GET['get_tag_count'])) {
    $countType = $_GET['get_tag_count'];
    $validCountTypes = ['myLeads', 'bookedLeads', 'activeLeads', 'freshLeads', 'pendingLeads', 'droppedLeads', 'followLeads', 'overdueLeads', 'today_collection', 'paidAds', 'shi_d'];
    
    if (!in_array($countType, $validCountTypes)) {
        echo json_encode(['count' => 0, 'error' => 'Invalid count type']);
        exit;
    }
    
    try {
        // Get filter parameters - OPTIMIZED: Parse once, reuse
        $toggleEnabled = isset($_GET['toggle_enabled']) && $_GET['toggle_enabled'] == '1';
        $filterUserParam = isset($_GET['filterUser']) ? trim($_GET['filterUser']) : '';
        $managerViewParam = isset($_GET['managerView']) ? filter_var($_GET['managerView'], FILTER_VALIDATE_BOOLEAN) : false;
        $dateFrom = isset($_GET['dateFrom']) ? $_GET['dateFrom'] : '';
        $dateTo = isset($_GET['dateTo']) ? $_GET['dateTo'] : '';
        $multiFilters = isset($_GET['multiFilters']) ? json_decode($_GET['multiFilters'], true) : [];
        $searchQuery = isset($_GET['searchQuery']) ? trim($_GET['searchQuery']) : '';
        $columnsSearch = isset($_GET['columns']) ? $_GET['columns'] : [];
        
        // OPTIMIZED: Cache user role lookup (used in multiple places)
        static $userRoleCache = [];
        $cacheKey = $useruniqueId;
        if (!isset($userRoleCache[$cacheKey])) {
            $stmt = $conn->prepare("SELECT user_type FROM accounts WHERE tablename = :tablename LIMIT 1");
            $stmt->bindParam(':tablename', $useruniqueId, PDO::PARAM_STR);
            $stmt->execute();
            $currentUserRow = $stmt->fetch(PDO::FETCH_ASSOC);
            $userRoleCache[$cacheKey] = $currentUserRow['user_type'] ?? 'user';
        }
        $currentUserType = $userRoleCache[$cacheKey];
        $normalizedRole = normalize_role_update($currentUserType);
        $canUseManagerToggle = ($normalizedRole !== 'user');
        
        // OPTIMIZED: Cache accessible users (expensive operation)
        static $accessibleUsersCache = [];
        $usersCacheKey = $useruniqueId . '_' . $currentUserType;
        if (!isset($accessibleUsersCache[$usersCacheKey])) {
            $accessibleUsersCache[$usersCacheKey] = get_accessible_users_update($conn, $useruniqueId, $currentUserType);
        }
        $accessibleUsers = $accessibleUsersCache[$usersCacheKey];
        
        // Build additional WHERE conditions based on filters
        $additionalConditions = [];
        $commonBindings = [];
        
        // Date range filter
        if (!empty($dateFrom) && !empty($dateTo)) {
            $dateFromParam = strlen($dateFrom) === 10 ? $dateFrom . ' 00:00:00' : $dateFrom;
            $dateToParam = strlen($dateTo) === 10 ? $dateTo . ' 23:59:59' : $dateTo;
            $additionalConditions[] = "ur.created_at BETWEEN :dateFrom AND :dateTo";
            $commonBindings[':dateFrom'] = [$dateFromParam, PDO::PARAM_STR];
            $commonBindings[':dateTo'] = [$dateToParam, PDO::PARAM_STR];
        }
        
        // Multi-filters - OPTIMIZED: All columns now support filtering
        $multiFilterMap = [
            'status'             => ['field' => 'ur.status',                    'type' => 'equals'],
            'source_of_lead'     => ['field' => 'LOWER(TRIM(sud.source_of_lead))', 'type' => 'lower_like'],
            'name'               => ['field' => 'sud.name',                     'type' => 'like'],
            'email'              => ['field' => 'sud.email',                   'type' => 'like'],
            'number'             => ['field' => 'sud.number',                  'type' => 'like'],
            'location'           => ['field' => 'ur.location_status',          'type' => 'like'],
            'budget'             => ['field' => 'ur.budget',                   'type' => 'like'],
            'assign_project_name' => ['field' => 'ur.assign_project_name',     'type' => 'like'],
            'user'               => ['field' => 'ur.user_unique_id',            'type' => 'equals'],
            'lead_identity'      => ['field' => 'ur.lead_identity',             'type' => 'equals'],
            'id'                 => ['field' => 'sud.id',                      'type' => 'equals'],  // ID column filtering (exact match)
            'created_at'         => ['field' => 'ur.created_at',               'type' => 'equals'],  // Created At column filtering (exact match for date strings)
            'start_date'         => ['field' => 'ur.created_at',               'type' => 'date_range'],
            'end_date'           => ['field' => 'ur.created_at',               'type' => 'date_range']
        ];
        
        foreach ($multiFilters as $key => $value) {
            if ($value === '' || $value === 'All' || !isset($multiFilterMap[$key])) {
                continue;
            }
            
            $filterMeta = $multiFilterMap[$key];
            $field = $filterMeta['field'];
            $comparisonType = $filterMeta['type'];
            
            // OPTIMIZED: Handle CSV strings from header dropdown filters (e.g., "value1,value2")
            // Also handle arrays and single values
            if (is_array($value)) {
                $rawValues = $value;
            } elseif (is_string($value) && strpos($value, ',') !== false) {
                // Split CSV string into array (header dropdown filters use CSV format)
                $rawValues = array_map('trim', explode(',', $value));
            } else {
                $rawValues = [$value];
            }
            
            $cleanValues = [];
            foreach ($rawValues as $rawValue) {
                if ($rawValue !== '' && $rawValue !== 'All') {
                    $cleanValues[] = $rawValue;
                }
            }
            
            if (empty($cleanValues)) {
                continue;
            }
            
            if ($comparisonType === 'equals') {
                $placeholders = [];
                foreach ($cleanValues as $idx => $cleanValue) {
                    $paramName = ":filter_{$key}_{$idx}";
                    $placeholders[] = $paramName;
                    // OPTIMIZED: Handle ID as integer, Created At as string (date)
                    if ($key === 'id') {
                        $commonBindings[$paramName] = [is_numeric($cleanValue) ? (int)$cleanValue : $cleanValue, is_numeric($cleanValue) ? PDO::PARAM_INT : PDO::PARAM_STR];
                    } else {
                        $commonBindings[$paramName] = [$cleanValue, PDO::PARAM_STR];
                    }
                }
                $additionalConditions[] = sprintf('%s IN (%s)', $field, implode(',', $placeholders));
            } else {
                $orConditions = [];
                foreach ($cleanValues as $idx => $cleanValue) {
                    $paramName = ":filter_{$key}_{$idx}";
                    $orConditions[] = sprintf('%s = %s', $field, $paramName);
                    
                    if ($comparisonType === 'lower_like') {
                        $commonBindings[$paramName] = [strtolower(trim($cleanValue)), PDO::PARAM_STR];
                    } else {
                        $commonBindings[$paramName] = [$cleanValue, PDO::PARAM_STR];
                    }
                }
                if (!empty($orConditions)) {
                    $additionalConditions[] = '(' . implode(' OR ', $orConditions) . ')';
                }
            }
        }
        
        // Column-specific search filters
        if (!empty($columnsSearch) && is_array($columnsSearch)) {
            $columnFields = [
                1 => 'sud.name',
                2 => 'ur.assign_project_name',
                3 => 'sud.id',
                4 => 'ur.created_at',
                5 => 'sud.email',
                6 => 'ur.location_status',
                7 => 'ur.budget',
                8 => 'ur.user_unique_id',
                9 => 'sud.source_of_lead'
            ];
            
            foreach ($columnsSearch as $i => $col) {
                if (!empty($col['search']['value']) && isset($columnFields[$i])) {
                    $searchVal = $col['search']['value'];
                    $field = $columnFields[$i];
                    
                    if (strpos($searchVal, '|') !== false) {
                        $vals = explode('|', $searchVal);
                        $placeholders = [];
                        foreach ($vals as $idx => $val) {
                            $paramName = ":col_search_{$i}_{$idx}";
                            $placeholders[] = $paramName;
                            $commonBindings[$paramName] = [$val, PDO::PARAM_STR];
                        }
                        $additionalConditions[] = "$field IN (" . implode(',', $placeholders) . ")";
                    } else {
                        $paramName = ":col_search_{$i}";
                        $additionalConditions[] = "$field = $paramName";
                        $commonBindings[$paramName] = [$searchVal, PDO::PARAM_STR];
                    }
                }
            }
        }
        
        // Global search query
        if (!empty($searchQuery)) {
            $searchParts = [
                "sud.name LIKE :global_search",
                "sud.email LIKE :global_search",
                "sud.number LIKE :global_search",
                "sud.location LIKE :global_search",
                "sud.source_of_lead LIKE :global_search",
                "ur.status LIKE :global_search",
                "ur.assign_project_name LIKE :global_search",
                "ur.user_unique_id LIKE :global_search"
            ];
            $searchTerm = '%' . $searchQuery . '%';
            $commonBindings[':global_search'] = [$searchTerm, PDO::PARAM_STR];
            $additionalConditions[] = '(' . implode(' OR ', $searchParts) . ')';
        }
        
        $additionalWhereSQL = '';
        if (!empty($additionalConditions)) {
            $additionalWhereSQL = ' AND ' . implode(' AND ', $additionalConditions);
        }
        
        $bindCommonParams = function(PDOStatement $statement) use ($commonBindings) {
            foreach ($commonBindings as $param => $details) {
                $statement->bindValue($param, $details[0], $details[1]);
            }
        };
        
        // Build user condition
        $forceSpecificUserForCount = false;
        if ($filterUserParam !== '' && in_array($filterUserParam, $accessibleUsers, true)) {
            $userCondition = "ur.user_unique_id = :forcedUser";
            $useAccessibleUsersForCount = false;
            $forceSpecificUserForCount = true;
        } elseif ($toggleEnabled && $canUseManagerToggle) {
            $subordinateUsers = array_filter($accessibleUsers, function($user) use ($useruniqueId) {
                return $user !== $useruniqueId;
            });
            
            if (count($subordinateUsers) > 0) {
                $placeholders = [];
                for ($i = 0; $i < count($subordinateUsers); $i++) {
                    $placeholders[] = ":accessible_user_count_$i";
                }
                $userCondition = "ur.user_unique_id IN (" . implode(',', $placeholders) . ")";
                $useAccessibleUsersForCount = true;
                $accessibleUsers = array_values($subordinateUsers);
            } else {
                $userCondition = "ur.user_unique_id = 'no_subordinates'";
                $useAccessibleUsersForCount = false;
            }
        } else {
            $userCondition = "ur.user_unique_id = :userUniqueId";
            $useAccessibleUsersForCount = false;
        }
        
        // Build SQL based on count type - SUPER OPTIMIZED for 1L+ records
        // WHERE clause order: Most selective filters first (history_h, status, dates) -> user condition -> additional filters
        // Using COUNT(*) with proper WHERE ordering for optimal index usage
        $sql = '';
        switch ($countType) {
            case 'myLeads':
                // OPTIMIZED: history_h first (most selective ~90% reduction), then user, then additional filters
                $sql = "SELECT COUNT(*) as count FROM user_remarks ur INNER JOIN shi_upload_data sud ON sud.id = ur.upload_data_id WHERE ur.history_h = 0 AND {$userCondition} {$additionalWhereSQL}";
                break;
            case 'bookedLeads':
                // OPTIMIZED: status first (selective), then history_h, then user
                $sql = "SELECT COUNT(*) as count FROM user_remarks ur INNER JOIN shi_upload_data sud ON sud.id = ur.upload_data_id WHERE ur.status = 'Converted' AND ur.history_h = 0 AND {$userCondition} {$additionalWhereSQL}";
                break;
            case 'droppedLeads':
                // OPTIMIZED: status IN first, then history_h, then user
                $sql = "SELECT COUNT(*) as count FROM user_remarks ur INNER JOIN shi_upload_data sud ON sud.id = ur.upload_data_id WHERE ur.status IN ('Not Interested', 'Fake') AND ur.history_h = 0 AND {$userCondition} {$additionalWhereSQL}";
                break;
            case 'activeLeads':
                // OPTIMIZED: status NOT IN first, then history_h, then user
                $sql = "SELECT COUNT(*) as count FROM user_remarks ur INNER JOIN shi_upload_data sud ON sud.id = ur.upload_data_id WHERE ur.status NOT IN ('Not Interested', 'Fake', 'Already Booked', 'Converted') AND ur.status IS NOT NULL AND ur.status != '' AND ur.history_h = 0 AND {$userCondition} {$additionalWhereSQL}";
                break;
            case 'freshLeads':
                // OPTIMIZED: date filter first (uses created_at index), then history_h, then user
                $sql = "SELECT COUNT(*) as count FROM user_remarks ur INNER JOIN shi_upload_data sud ON sud.id = ur.upload_data_id WHERE ur.created_at >= DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND ur.history_h = 0 AND {$userCondition} {$additionalWhereSQL}";
                break;
            case 'pendingLeads':
                // OPTIMIZED: status first, then history_h, then user
                $sql = "SELECT COUNT(*) as count FROM user_remarks ur INNER JOIN shi_upload_data sud ON sud.id = ur.upload_data_id WHERE ur.status = 'Pending' AND ur.history_h = 0 AND {$userCondition} {$additionalWhereSQL}";
                break;
            case 'followLeads':
                // OPTIMIZED: status first, then history_h, then user
                $sql = "SELECT COUNT(*) as count FROM user_remarks ur INNER JOIN shi_upload_data sud ON sud.id = ur.upload_data_id WHERE ur.status = 'Follow Up' AND ur.history_h = 0 AND {$userCondition} {$additionalWhereSQL}";
                break;
            case 'overdueLeads':
                // OPTIMIZED: status filter first, then date comparison, then history_h, then user
                $sql = "SELECT COUNT(*) as count FROM user_remarks ur INNER JOIN shi_upload_data sud ON sud.id = ur.upload_data_id WHERE ur.status IN ('Follow Up', 'RNR', 'Call Back', 'Pending', 'Interested', 'Fix Site Visit', 'Site Visit Done', 'Re site visit', 'Not Connected') AND ur.status != 'Converted' AND ur.status != 'Already Booked' AND ur.follow_up_date IS NOT NULL AND ur.follow_up_time IS NOT NULL AND CONCAT(ur.follow_up_date, ' ', ur.follow_up_time) < DATE_SUB(NOW(), INTERVAL 1 DAY) AND ur.history_h = 0 AND {$userCondition} {$additionalWhereSQL}";
                break;
            case 'today_collection':
                $todayDate = date('Y-m-d');
                $todayStart = $todayDate . ' 00:00:00';
                $todayEnd = $todayDate . ' 23:59:59';
                $statuses = "('Follow Up', 'Interested', 'Call Back', 'RNR', 'Fix Site Visit')";
                // OPTIMIZED: status filter first, then date, then history_h, then user
                $sql = "SELECT COUNT(*) as count FROM user_remarks ur INNER JOIN shi_upload_data sud ON sud.id = ur.upload_data_id WHERE ur.status IN {$statuses} AND ur.history_h = 0 AND {$userCondition} AND ((ur.follow_up_date IS NOT NULL AND ur.follow_up_date >= :todayStart AND ur.follow_up_date < :todayEnd) OR (JSON_TYPE(ur.history) = 'ARRAY' AND JSON_LENGTH(ur.history) > 0 AND STR_TO_DATE(JSON_UNQUOTE(JSON_EXTRACT(ur.history, CONCAT('$[', JSON_LENGTH(ur.history) - 1, '].followUpDate'))), '%Y-%m-%d') >= :todayStart AND STR_TO_DATE(JSON_UNQUOTE(JSON_EXTRACT(ur.history, CONCAT('$[', JSON_LENGTH(ur.history) - 1, '].followUpDate'))), '%Y-%m-%d') < :todayEnd)) {$additionalWhereSQL}";
                break;
            case 'paidAds':
                // OPTIMIZED: source filter first, then history_h, then user
                $sql = "SELECT COUNT(*) as count FROM shi_upload_data sud INNER JOIN user_remarks ur ON sud.id = ur.upload_data_id WHERE LOWER(TRIM(sud.source_of_lead)) IN ('google ads', 'facebook ads', 'magicbricks ads', '99acres ads', 'housing.com ads') AND ur.history_h = 0 AND {$userCondition} {$additionalWhereSQL}";
                break;
            case 'shi_d':
                // OPTIMIZED: source filter first, then history_h, then user
                $sql = "SELECT COUNT(*) as count FROM shi_upload_data sud INNER JOIN user_remarks ur ON sud.id = ur.upload_data_id WHERE (sud.source_of_lead IS NULL OR LOWER(TRIM(sud.source_of_lead)) NOT IN ('google ads', 'facebook ads', 'magicbricks ads', '99acres ads', 'housing.com ads')) AND ur.history_h = 0 AND {$userCondition} {$additionalWhereSQL}";
                break;
        }
        
        if (empty($sql)) {
            echo json_encode(['count' => 0, 'error' => 'Invalid count type']);
            exit;
        }
        
        // Set query timeout for large datasets (30 seconds) - OPTIMIZED for 1L+ records
        try {
            $conn->setAttribute(PDO::ATTR_TIMEOUT, 30);
        } catch (Exception $e) {
            // Some PDO drivers don't support ATTR_TIMEOUT, ignore
        }
        
        $stmt = $conn->prepare($sql);
        
        // Bind user parameter
        if ($forceSpecificUserForCount && $filterUserParam !== '') {
            $stmt->bindParam(':forcedUser', $filterUserParam, PDO::PARAM_STR);
        } elseif (isset($useAccessibleUsersForCount) && $useAccessibleUsersForCount) {
            for ($i = 0; $i < count($accessibleUsers); $i++) {
                $stmt->bindValue(":accessible_user_count_$i", $accessibleUsers[$i], PDO::PARAM_STR);
            }
        } else {
            $stmt->bindParam(':userUniqueId', $useruniqueId);
        }
        
        // Bind common parameters
        $bindCommonParams($stmt);
        
        // Bind today_collection specific parameters
        if ($countType === 'today_collection') {
            $todayDate = date('Y-m-d');
            $todayStart = $todayDate . ' 00:00:00';
            $todayEnd = $todayDate . ' 23:59:59';
            $stmt->bindParam(':todayStart', $todayStart);
            $stmt->bindParam(':todayEnd', $todayEnd);
        }
        
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $count = (int)($result['count'] ?? 0);
        
        echo json_encode(['count' => $count, 'type' => $countType]);
    } catch (PDOException $e) {
        error_log("Error fetching tag count {$countType}: " . $e->getMessage());
        echo json_encode(['count' => 0, 'error' => $e->getMessage()]);
    }
    exit;
}

// Handle the GET request for data fetching
// Debugging: Log incoming parameters
try {
    // Get query parameters
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $rowsPerPage = isset($_GET['rowsPerPage']) ? (int)$_GET['rowsPerPage'] : 10;
    $searchQuery = isset($_GET['searchQuery']) ? $_GET['searchQuery'] : '';
    // Normalise filter names (handles minor variants for Follow Up etc.)
    $filter = isset($_GET['filter']) ? trim($_GET['filter']) : '';
    if (in_array($filter, ['followupLeads', 'followup', 'follow-up', 'FollowUp'], true)) {
        $filter = 'followLeads';
    }
    $multiFilters = isset($_GET['multiFilters']) ? json_decode($_GET['multiFilters'], true) : [];
    $managerToggle = isset($_GET['managerToggle']) ? (int)$_GET['managerToggle'] : 0;
    $filterUserParam = isset($_GET['filterUser']) ? trim($_GET['filterUser']) : '';
    $managerViewParam = isset($_GET['managerView']) ? filter_var($_GET['managerView'], FILTER_VALIDATE_BOOLEAN) : false;
    $startRow = ($page - 1) * $rowsPerPage;

    $whereClauses = [];

    $startDate = !empty($multiFilters['start_date']) ? $multiFilters['start_date'] . ' 00:00:00' : null;
    $endDate = !empty($multiFilters['end_date']) ? $multiFilters['end_date'] . ' 23:59:59' : null;

    // OPTIMIZED Base query - Using LEFT JOIN with history_h filter for performance
    // This ensures each lead appears only once, matching the COUNT(DISTINCT) logic in badge counts
    // OPTIMIZED: history_h filter in JOIN condition for better index usage
    $baseQuery = "
    SELECT sud.*, ur.status AS user_status, ur.remarks AS user_remarks, 
    ur.assign_project_name, ur.user_unique_id, ur.lead_identity, ur.budget, ur.location_status,
    ur.follow_up_date, ur.follow_up_time, ur.updated_at,
    CASE 
        WHEN ur.status = 'Pending' THEN DATEDIFF(NOW(), ur.created_at)
        ELSE NULL 
    END AS days_untouched,
    CASE 
    WHEN ur.status IN ('Follow Up', 'RNR', 'Call Back', 'Pending', 'Interested', 'Fix Site Visit', 'Site Visit Done', 'Re site visit', 'Not Connected') 
    AND ur.status != 'Converted'
    AND ur.status != 'Already Booked'
    AND ur.follow_up_date IS NOT NULL 
    AND ur.follow_up_time IS NOT NULL 
    AND CONCAT(ur.follow_up_date, ' ', ur.follow_up_time) < DATE_SUB(NOW(), INTERVAL 1 DAY)
    THEN 1 
    ELSE 0 
    END AS is_overdue,
    CASE 
        WHEN ur.status IN ('Follow Up', 'RNR', 'Call Back', 'Pending', 'Interested', 'Fix Site Visit', 'Site Visit Done', 'Re site visit', 'Not Connected') 
        AND ur.status != 'Converted'
        AND ur.status != 'Already Booked'
        AND ur.follow_up_date IS NOT NULL 
        AND ur.follow_up_time IS NOT NULL 
        AND CONCAT(ur.follow_up_date, ' ', ur.follow_up_time) < DATE_SUB(NOW(), INTERVAL 1 DAY)
        THEN DATEDIFF(NOW(), CONCAT(ur.follow_up_date, ' ', ur.follow_up_time))
        ELSE NULL 
    END AS overdue_from_days
    FROM shi_upload_data sud
    LEFT JOIN user_remarks ur ON sud.id = ur.upload_data_id AND ur.history_h = 0
";

    // OPTIMIZED: Cache user role lookup (used in multiple places)
    static $userRoleCacheMain = [];
    $cacheKeyMain = $useruniqueId;
    if (!isset($userRoleCacheMain[$cacheKeyMain])) {
        $stmt = $conn->prepare("SELECT user_type FROM accounts WHERE tablename = :tablename LIMIT 1");
        $stmt->bindParam(':tablename', $useruniqueId, PDO::PARAM_STR);
        $stmt->execute();
        $currentUserRow = $stmt->fetch(PDO::FETCH_ASSOC);
        $userRoleCacheMain[$cacheKeyMain] = $currentUserRow['user_type'] ?? 'user';
    }
    $currentUserType = $userRoleCacheMain[$cacheKeyMain];

    $normalizedRole = normalize_role_update($currentUserType);
    $canUseManagerToggle = ($normalizedRole !== 'user');
    
    // OPTIMIZED: Cache accessible users (expensive operation)
    static $accessibleUsersCacheMain = [];
    $usersCacheKeyMain = $useruniqueId . '_' . $currentUserType;
    if (!isset($accessibleUsersCacheMain[$usersCacheKeyMain])) {
        $accessibleUsersCacheMain[$usersCacheKeyMain] = get_accessible_users_update($conn, $useruniqueId, $currentUserType);
    }
    $accessibleUsers = $accessibleUsersCacheMain[$usersCacheKeyMain];

    $forceSpecificUser = false;
    if ($filterUserParam !== '' && in_array($filterUserParam, $accessibleUsers, true)) {
        // Honor explicit user selection coming from dashboard
        $whereClauses[] = "ur.user_unique_id = :forcedUser";
        $forceSpecificUser = true;
    } elseif ($managerToggle === 1 && $canUseManagerToggle) {
        // Team view: show data for subordinates only (exclude current user)
        error_log("📋 Team view for user=$useruniqueId: all_accessible=" . implode(',', $accessibleUsers));

        // Remove current user from accessible users for team view
        $subordinateUsers = array_filter($accessibleUsers, function($user) use ($useruniqueId) {
            return $user !== $useruniqueId;
        });

        error_log("📋 Subordinates only (excluding $useruniqueId): " . implode(',', $subordinateUsers));

        if (count($subordinateUsers) > 0) {
            $placeholders = [];
            for ($i = 0; $i < count($subordinateUsers); $i++) {
                $placeholders[] = ":accessible_user_$i";
            }
            $whereClauses[] = "ur.user_unique_id IN (" . implode(',', $placeholders) . ")";
            $useAccessibleUsers = true;
            $accessibleUsers = array_values($subordinateUsers); // Re-index array
        } else {
            // No subordinates: fall back to own data instead of returning empty
            $whereClauses[] = "ur.user_unique_id = :userUniqueId";
            $useAccessibleUsers = false;
        }
    } else {
        // Individual view or user role: show only own data
        $whereClauses[] = "ur.user_unique_id = :userUniqueId";
        $useAccessibleUsers = false;
    }

    // Filters
    // Determine which user parameter to use for myLeads filter
    $myLeadsUserParam = ($forceSpecificUser && $filterUserParam !== '') ? ':forcedUser' : ':userUniqueId';
    $filterConditions = [
    'myLeads' => "ur.user_unique_id = {$myLeadsUserParam}",
        'bookedLeads' => "ur.status = 'Converted'",
        'droppedLeads' => "ur.status IN ('Not Interested', 'Fake')",
        'activeLeads' => "ur.status NOT IN ('Not Interested', 'Fake', 'Already Booked', 'Converted') AND ur.status IS NOT NULL AND ur.status != ''",
        'freshLeads' => "ur.created_at >= DATE_SUB(CURDATE(), INTERVAL 1 DAY)",
        'pendingLeads' => "ur.status = 'Pending'",
        // Follow Up: allow case/whitespace variants
        'followLeads' => "LOWER(TRIM(ur.status)) = 'follow up'",
        'overdueLeads' => "ur.status IN ('Follow Up', 'RNR', 'Call Back', 'Pending', 'Interested', 'Fix Site Visit', 'Site Visit Done', 'Re site visit', 'Not Connected') AND ur.status != 'Converted' AND ur.status != 'Already Booked' AND ur.follow_up_date IS NOT NULL AND ur.follow_up_time IS NOT NULL AND CONCAT(ur.follow_up_date, ' ', ur.follow_up_time) < DATE_SUB(NOW(), INTERVAL 1 DAY)",
        'Fake' => "ur.status = 'Fake'",
        'RNR' => "ur.status = 'RNR'",
        'Call Back' => "ur.status = 'Call Back'",
        'Already Booked' => "ur.status = 'Already Booked'",
        'Not Interested' => "ur.status = 'Not Interested'",
        'Interested' => "ur.status = 'Interested'",
        // Special logic for Fix Site Visit: Count if "Fix Site Visit" appears in history AND "Site Visit Done" does NOT appear after it in history
        'Fix Site Visit' => "ur.history IS NOT NULL AND JSON_TYPE(ur.history) = 'ARRAY' AND JSON_SEARCH(ur.history, 'one', 'Fix Site Visit', NULL, '$[*].status') IS NOT NULL AND (JSON_SEARCH(ur.history, 'one', 'Site Visit Done', NULL, '$[*].status') IS NULL OR CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(JSON_SEARCH(ur.history, 'one', 'Site Visit Done', NULL, '$[*].status'), '[', -1), ']', 1) AS UNSIGNED) < CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(JSON_SEARCH(ur.history, 'one', 'Fix Site Visit', NULL, '$[*].status'), '[', -1), ']', 1) AS UNSIGNED))",
        // Special logic for Site Visit Done: Count if "Site Visit Done" appears anywhere in history (regardless of current status)
        'Site Visit Done' => "ur.history IS NOT NULL AND JSON_TYPE(ur.history) = 'ARRAY' AND JSON_SEARCH(ur.history, 'one', 'Site Visit Done', NULL, '$[*].status') IS NOT NULL",
        'Converted' => "ur.status = 'Converted'",
        'Re site visit' => "ur.status = 'Re site visit'",
        'NQFTP' => "ur.status = 'NQFTP'",
        'Not Connected' => "ur.status = 'Not Connected'",
    'paidAds' => "LOWER(TRIM(sud.source_of_lead)) IN ('google ads', 'facebook ads', 'magicbricks ads', '99acres ads', 'housing.com ads')",
    'SHI_D' => "(sud.source_of_lead IS NULL OR LOWER(TRIM(sud.source_of_lead)) NOT IN ('google ads', 'facebook ads', 'magicbricks ads', '99acres ads', 'housing.com ads'))",
        'today_collection' => "(
            LOWER(TRIM(ur.status)) IN ('follow up', 'interested', 'call back', 'rnr', 'fix site visit')
            AND (
                (
                    ur.follow_up_date IS NOT NULL
                    AND DATE(ur.follow_up_date) = CURDATE()
                )
                OR (
                    JSON_TYPE(ur.history) = 'ARRAY'
                    AND JSON_LENGTH(ur.history) > 0
                    AND (
                        DATE(
                            STR_TO_DATE(
                                JSON_UNQUOTE(
                                    JSON_EXTRACT(ur.history, CONCAT('$[', JSON_LENGTH(ur.history) - 1, '].followUpDate'))
                                ), '%Y-%m-%d'
                            )
                        ) = CURDATE()
                        OR DATE(
                            STR_TO_DATE(
                                JSON_UNQUOTE(
                                    JSON_EXTRACT(ur.history, CONCAT('$[', JSON_LENGTH(ur.history) - 1, '].followUpDate'))
                                ), '%Y-%m-%d %H:%i:%s'
                            )
                        ) = CURDATE()
                    )
                )
            )
        )"
    ];

    // Apply filter only if it's compatible with current view mode
    if (!empty($filter) && isset($filterConditions[$filter])) {
        // Special handling for 'myLeads' filter
        if ($filter === 'myLeads') {
            // Skip 'myLeads' filter in team view or when a specific user is already forced
            if ($managerToggle === 1 && $canUseManagerToggle) {
                // In team view, 'myLeads' filter doesn't make sense and causes binding errors
                error_log("⚠️ 'myLeads' filter skipped in team view - showing all team data instead");
            } elseif ($forceSpecificUser && $filterUserParam !== '') {
                // When a specific user is forced, 'myLeads' filter is redundant (already filtering by that user)
                error_log("⚠️ 'myLeads' filter skipped when specific user is forced - already filtering by user");
            } else {
                $whereClauses[] = $filterConditions[$filter];
            }
        } else {
            $whereClauses[] = $filterConditions[$filter];
        }
    } else {
        // Unrecognised filter names should not bypass filtering logic
        $filter = '';
    }

    // Date Range
    // if (!empty($startDate) && !empty($endDate)) {
    //     $whereClauses[] = "ur.created_at BETWEEN :startDate AND :endDate";
    // }
    if (!empty($startDate) && !empty($endDate)) {
        $whereClauses[] = "ur.created_at BETWEEN :startDate AND :endDate";
    }

    // Multi-filters
    $allowedFilters = [
        'name' => 'sud.name',
        'email' => 'sud.email',
        'number' => 'sud.number',
        'location' => 'ur.location_status',
        'budget' => 'ur.budget',
    'source_of_lead' => 'LOWER(TRIM(sud.source_of_lead))',
        'status' => 'ur.status',
        'assign_project_name' => 'ur.assign_project_name',
        'user' => 'ur.user_unique_id',
        'lead_identity' => 'ur.lead_identity'
    ];

    // Track bindings for status so we can apply history-aware matching (Fix Site Visit, Site Visit Done)
    $statusFilterBindings = [];

    if (!empty($multiFilters)) {
        foreach ($multiFilters as $column => $value) {
            if (empty($value) || !isset($allowedFilters[$column])) {
                continue;
            }

            // History-aware status filtering to include past Fix Site Visit / Site Visit Done
            if ($column === 'status') {
                $statusValues = is_array($value)
                    ? $value
                    : (strpos($value, ',') !== false ? array_map('trim', explode(',', $value)) : [$value]);
                $statusValues = array_values(array_filter($statusValues, function($v) { return $v !== ''; }));

                if (!empty($statusValues)) {
                    $statusConditions = [];
                    foreach ($statusValues as $idx => $statusVal) {
                        $paramName = ":filter_status_{$idx}";
                        $statusFilterBindings[$paramName] = $statusVal;

                        if (strcasecmp($statusVal, 'Fix Site Visit') === 0) {
                            $statusConditions[] = "(ur.status = {$paramName} OR (ur.history IS NOT NULL AND JSON_TYPE(ur.history) = 'ARRAY' AND JSON_SEARCH(ur.history, 'one', 'Fix Site Visit', NULL, '$[*].status') IS NOT NULL AND (JSON_SEARCH(ur.history, 'one', 'Site Visit Done', NULL, '$[*].status') IS NULL OR CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(JSON_SEARCH(ur.history, 'one', 'Site Visit Done', NULL, '$[*].status'), '[', -1), ']', 1) AS UNSIGNED) < CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(JSON_SEARCH(ur.history, 'one', 'Fix Site Visit', NULL, '$[*].status'), '[', -1), ']', 1) AS UNSIGNED))))";
                        } elseif (strcasecmp($statusVal, 'Site Visit Done') === 0) {
                            $statusConditions[] = "(ur.status = {$paramName} OR (ur.history IS NOT NULL AND JSON_TYPE(ur.history) = 'ARRAY' AND JSON_SEARCH(ur.history, 'one', 'Site Visit Done', NULL, '$[*].status') IS NOT NULL))";
                        } else {
                            $statusConditions[] = "{$allowedFilters[$column]} = {$paramName}";
                        }
                    }

                    if (!empty($statusConditions)) {
                        $whereClauses[] = '(' . implode(' OR ', $statusConditions) . ')';
                    }
                }

                continue;
            }

            // Default handling for non-status filters
            $normalizedValue = is_array($value) ? implode(',', $value) : $value;

            if (strpos($normalizedValue, ',') !== false) {
                // Multiple values: use IN clause
                $values = array_map('trim', explode(',', $normalizedValue));
                $values = array_filter($values, function($v) { return !empty($v); });
                if (!empty($values)) {
                    $placeholders = [];
                    foreach ($values as $idx => $val) {
                        $placeholders[] = ":filter_{$column}_{$idx}";
                    }
                    $whereClauses[] = "{$allowedFilters[$column]} IN (" . implode(',', $placeholders) . ")";
                }
            } else {
                // Single value: use exact match
                $whereClauses[] = "{$allowedFilters[$column]} = :filter_{$column}";
            }
        }
    }

     // Column filters from DataTables
    if (isset($_GET['columns'])) {
        $columns = $_GET['columns'];
        $columnFields = [
            1 => 'sud.name',
            2 => 'ur.assign_project_name',
            3 => 'sud.id',
            4 => 'ur.created_at',
            5 => 'sud.email',
            6 => 'ur.location_status',
            7 => 'ur.budget',
            8 => 'ur.user_unique_id',
            9 => 'sud.source_of_lead'
        ];
        foreach ($columns as $i => $col) {
            if (!empty($col['search']['value']) && isset($columnFields[$i])) {
                $searchVal = $col['search']['value'];
                $field = $columnFields[$i];
                if (strpos($searchVal, '|') !== false) {
                    $vals = explode('|', $searchVal);
                    $placeholders = [];
                    foreach ($vals as $idx => $val) {
                        $placeholders[] = ":col{$i}_{$idx}";
                    }
                    $whereClauses[] = "$field IN (" . implode(',', $placeholders) . ")";
                } else {
                    $whereClauses[] = "$field = :col{$i}";
                }
            }
        }
    }

    // OPTIMIZED: Search - Only add if search query exists
    // OPTIMIZED: Search - Only add if search query exists
    if (!empty($searchQuery)) {
        $searchTerm = '%' . trim($searchQuery) . '%';
        // OPTIMIZED: Use single parameter binding for all LIKE clauses (reduces memory)
        $searchParts = [
            "sud.name LIKE :search",
            "sud.email LIKE :search",
            "sud.number LIKE :search",
            "sud.location LIKE :search",
            "sud.source_of_lead LIKE :search",
            "ur.status LIKE :search",
            "ur.assign_project_name LIKE :search",
            "ur.user_unique_id LIKE :search"
        ];
        $whereClauses[] = '(' . implode(' OR ', $searchParts) . ')';
    }

    // OPTIMIZED: Final WHERE clause - Add history_h filter first for optimal performance
    // This ensures we only process active records (history_h = 0)
    if (!empty($whereClauses)) {
        // Ensure history_h = 0 is always first in WHERE clause for optimal index usage
        $historyFilter = "ur.history_h = 0";
        $otherClauses = array_filter($whereClauses, function($clause) {
            return strpos($clause, 'history_h') === false;
        });
        $baseQuery .= ' WHERE ' . $historyFilter . ' AND ' . implode(' AND ', $otherClauses);
    } else {
        // Even if no other filters, add history_h filter for performance
        $baseQuery .= ' WHERE ur.history_h = 0';
    }

    // Order
    $orderClause = " ORDER BY ur.created_at DESC";
    if ($filter === 'RNR') {
        $orderClause = " ORDER BY ur.status = 'RNR' DESC, ur.updated_at ASC, ur.created_at DESC";
    } elseif ($filter === 'today_collection') {
        $orderClause = " ORDER BY COALESCE(
            STR_TO_DATE(ur.follow_up_time, '%H:%i'),
            STR_TO_DATE(
                JSON_UNQUOTE(
                    JSON_EXTRACT(ur.history, CONCAT('$[', JSON_LENGTH(ur.history) - 1, '].followUpTime'))
                ), '%H:%i'
            )
        ) ASC";
    }
    $baseQuery .= $orderClause;

    // Pagination
    $baseQuery .= " LIMIT :startRow, :rowsPerPage";

    // Prepare and bind
    $stmt = $conn->prepare($baseQuery);
    
    // Handle hierarchy-based parameter binding
    if ($forceSpecificUser && $filterUserParam !== '') {
        $stmt->bindParam(':forcedUser', $filterUserParam, PDO::PARAM_STR);
    } elseif (isset($useAccessibleUsers) && $useAccessibleUsers) {
        // Bind accessible users array for IN clause using named parameters
        for ($i = 0; $i < count($accessibleUsers); $i++) {
            $stmt->bindValue(":accessible_user_$i", $accessibleUsers[$i], PDO::PARAM_STR);
        }
    } else {
        $stmt->bindParam(':userUniqueId', $useruniqueId, PDO::PARAM_STR);
    }

    if (!empty($startDate) && !empty($endDate)) {
        $stmt->bindParam(':startDate', $startDate);
        $stmt->bindParam(':endDate', $endDate);
    }

    if (!empty($statusFilterBindings)) {
        foreach ($statusFilterBindings as $param => $val) {
            $stmt->bindValue($param, $val, PDO::PARAM_STR);
        }
    }

    if (!empty($multiFilters)) {
        foreach ($multiFilters as $column => $value) {
            if (empty($value) || !isset($allowedFilters[$column])) {
                continue;
            }

            if ($column === 'status') {
                continue; // Status handled separately for history-aware matching
            }

            // Check if value is CSV (comma-separated) - used by column dropdown filters
            $normalizedValue = is_array($value) ? implode(',', $value) : $value;
            if (strpos($normalizedValue, ',') !== false) {
                // Multiple values: bind each value for IN clause
                $values = array_map('trim', explode(',', $normalizedValue));
                $values = array_filter($values, function($v) { return !empty($v); });
                foreach ($values as $idx => $val) {
                    $stmt->bindValue(":filter_{$column}_{$idx}", $val, PDO::PARAM_STR);
                }
            } else {
                // Single value: use exact match
                $normalizedVal = ($column === 'source_of_lead')
                    ? strtolower(trim($normalizedValue))
                    : $normalizedValue;
                $stmt->bindValue(":filter_$column", $normalizedVal, PDO::PARAM_STR);
            }
        }
    }

    if (isset($_GET['columns'])) {
        $columns = $_GET['columns'];
        foreach ($columns as $i => $col) {
            if (!empty($col['search']['value'])) {
                $searchVal = $col['search']['value'];
                if (strpos($searchVal, '|') !== false) {
                    $vals = explode('|', $searchVal);
                    foreach ($vals as $idx => $val) {
                        $stmt->bindValue(":col{$i}_{$idx}", $val, PDO::PARAM_STR);
                    }
                } else {
                    $stmt->bindValue(":col{$i}", $searchVal, PDO::PARAM_STR);
                }
            }
        }
    }

    // OPTIMIZED: Bind search parameter only if search exists
    if (!empty($searchQuery)) {
        $searchTerm = '%' . trim($searchQuery) . '%';
        $stmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
    }

    $stmt->bindParam(':startRow', $startRow, PDO::PARAM_INT);
    $stmt->bindParam(':rowsPerPage', $rowsPerPage, PDO::PARAM_INT);

    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // OPTIMIZED: Total count query - Same WHERE clause structure as main query
    $countQuery = "
        SELECT COUNT(*) AS totalRows
        FROM shi_upload_data sud
        LEFT JOIN user_remarks ur ON sud.id = ur.upload_data_id AND ur.history_h = 0
    ";
    // OPTIMIZED: Use same WHERE clause structure as main query (history_h first)
    if (!empty($whereClauses)) {
        $historyFilter = "ur.history_h = 0";
        $otherClauses = array_filter($whereClauses, function($clause) {
            return strpos($clause, 'history_h') === false;
        });
        $countQuery .= ' WHERE ' . $historyFilter . ' AND ' . implode(' AND ', $otherClauses);
    } else {
        $countQuery .= ' WHERE ur.history_h = 0';
    }
    $countStmt = $conn->prepare($countQuery);
    
    // Handle hierarchy-based parameter binding for count query
    if ($forceSpecificUser && $filterUserParam !== '') {
        $countStmt->bindParam(':forcedUser', $filterUserParam, PDO::PARAM_STR);
    } elseif (isset($useAccessibleUsers) && $useAccessibleUsers) {
        // Bind accessible users array for IN clause using named parameters
        for ($i = 0; $i < count($accessibleUsers); $i++) {
            $countStmt->bindValue(":accessible_user_$i", $accessibleUsers[$i], PDO::PARAM_STR);
        }
    } else {
        $countStmt->bindParam(':userUniqueId', $useruniqueId, PDO::PARAM_STR);
    }

    if (!empty($startDate) && !empty($endDate)) {
        $countStmt->bindParam(':startDate', $startDate);
        $countStmt->bindParam(':endDate', $endDate);
    }

    if (!empty($statusFilterBindings)) {
        foreach ($statusFilterBindings as $param => $val) {
            $countStmt->bindValue($param, $val, PDO::PARAM_STR);
        }
    }

    if (!empty($multiFilters)) {
        foreach ($multiFilters as $column => $value) {
            if (empty($value) || !isset($allowedFilters[$column])) {
                continue;
            }

            if ($column === 'status') {
                continue; // Status handled separately for history-aware matching
            }

            // Check if value is CSV (comma-separated) - used by column dropdown filters
            $normalizedValue = is_array($value) ? implode(',', $value) : $value;
            if (strpos($normalizedValue, ',') !== false) {
                // Multiple values: bind each value for IN clause
                $values = array_map('trim', explode(',', $normalizedValue));
                $values = array_filter($values, function($v) { return !empty($v); });
                foreach ($values as $idx => $val) {
                    $countStmt->bindValue(":filter_{$column}_{$idx}", $val, PDO::PARAM_STR);
                }
            } else {
                // Single value: use exact match
                $normalizedVal = ($column === 'source_of_lead')
                    ? strtolower(trim($normalizedValue))
                    : $normalizedValue;
                $countStmt->bindValue(":filter_$column", $normalizedVal, PDO::PARAM_STR);
            }
        }
    }

    if (isset($_GET['columns'])) {
        $columns = $_GET['columns'];
        foreach ($columns as $i => $col) {
            if (!empty($col['search']['value'])) {
                $searchVal = $col['search']['value'];
                if (strpos($searchVal, '|') !== false) {
                    $vals = explode('|', $searchVal);
                    foreach ($vals as $idx => $val) {
                        $countStmt->bindValue(":col{$i}_{$idx}", $val, PDO::PARAM_STR);
                    }
                } else {
                    $countStmt->bindValue(":col{$i}", $searchVal, PDO::PARAM_STR);
                }
            }
        }
    }

    // OPTIMIZED: Bind search parameter for count query
    if (!empty($searchQuery)) {
        $searchTerm = '%' . trim($searchQuery) . '%';
        $countStmt->bindParam(':search', $searchTerm, PDO::PARAM_STR);
    }

    $countStmt->execute();
    $totalRows = $countStmt->fetch(PDO::FETCH_ASSOC)['totalRows'];

    // Add hierarchy information to response
    $allAccessibleUsers = get_accessible_users_update($conn, $useruniqueId, $currentUserType);
    $hierarchyInfo = [
        'current_user_level' => role_level_update(normalize_role_update($currentUserType)),
        'accessible_user_count' => count($allAccessibleUsers),
        'can_manage_team' => count($allAccessibleUsers) > 1,
        'current_user_role' => normalize_role_update($currentUserType),
        'can_use_manager_toggle' => $canUseManagerToggle,
        'manager_toggle_enabled' => $managerToggle === 1 && $canUseManagerToggle
    ];

    // Output the data + count + hierarchy info
    echo json_encode([
        'data' => $data,
        'totalRows' => $totalRows,
        'currentPage' => $page,
        'rowsPerPage' => $rowsPerPage,
        'hierarchyInfo' => $hierarchyInfo,
        'managerToggleEnabled' => $managerToggle === 1
    ]);

} catch (Exception $e) {
    // OPTIMIZED: Return valid JSON error response for DataTables
    error_log("DataTables Ajax error: " . $e->getMessage());
    echo json_encode([
        'data' => [],
        'totalRows' => 0,
        'error' => $e->getMessage()
    ]);
}
?>
