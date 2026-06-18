<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['loggedin'])) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}
session_write_close();

require_once 'db.php';
require_once 'util.php';

$db = new Database;
$util = new Util;

$db->createOfferLettersTable();

header('Content-Type: application/json');

if (isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action == 'upsert') {
        $isUpdate = isset($_POST['id']) && $_POST['id'] > 0;
        $offerStatus = 'Draft';
        if ($isUpdate) {
            $existing = $db->getOfferLetter((int)$_POST['id']);
            $offerStatus = $existing['offer_status'] ?? 'Draft';
        }
        $data = [
            'user_id' => isset($_POST['user_id']) && $_POST['user_id'] !== '' ? (int)$_POST['user_id'] : null,
            'candidate_name' => $util->testInput($_POST['candidate_name']),
            'email' => $util->testInput($_POST['email']),
            'phone' => $util->testInput($_POST['phone']),
            'position' => $util->testInput($_POST['position']),
            'department' => $util->testInput($_POST['department']),
            'monthly_salary' => (float)$_POST['monthly_salary'],
            'joining_date' => $util->testInput($_POST['joining_date']),
            'reporting_manager' => $util->testInput($_POST['reporting_manager']),
            'offer_status' => $offerStatus
        ];
        if ($isUpdate) {
            $data['id'] = (int)$_POST['id'];
        }

        if ($db->upsertOfferLetter($data)) {
            echo json_encode(['status' => 'success', 'message' => 'Offer letter saved successfully!']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to save offer letter.']);
        }
    }

    if ($action == 'delete') {
        $id = (int)$_POST['id'];
        if ($db->deleteOfferLetter($id)) {
            echo json_encode(['status' => 'success', 'message' => 'Offer letter deleted!']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to delete.']);
        }
    }

    if ($action == 'update_status') {
        $id = (int)($_POST['id'] ?? 0);
        $status = $util->testInput($_POST['status'] ?? '');
        $allowedStatuses = ['Accepted', 'Rejected'];
        if (!in_array($status, $allowedStatuses, true)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid offer status.']);
            exit;
        }

        $offer = $id > 0 ? $db->getOfferLetter($id) : null;
        if (!$offer) {
            echo json_encode(['status' => 'error', 'message' => 'Offer letter not found.']);
            exit;
        }

        if (empty($offer['emailed_at'])) {
            echo json_encode(['status' => 'error', 'message' => 'Please send the offer email first.']);
            exit;
        }

        if ($db->updateOfferLetterStatus($id, $status)) {
            echo json_encode(['status' => 'success', 'message' => 'Offer status updated.']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to update offer status.']);
        }
    }

    if ($action == 'fetch_one') {
        $id = (int)$_POST['id'];
        $letter = $db->getOfferLetter($id);
        if ($letter) {
            echo json_encode(['status' => 'success', 'data' => $letter]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Not found.']);
        }
    }
    exit;
}

if (isset($_GET['action']) && $_GET['action'] == 'fetch_all') {
    $filters = [
        'search' => $util->testInput($_GET['search'] ?? ''),
        'status' => $util->testInput($_GET['status'] ?? ''),
        'from' => $util->testInput($_GET['from'] ?? ''),
        'to' => $util->testInput($_GET['to'] ?? ''),
        'limit' => (int)($_GET['limit'] ?? 10),
        'page' => (int)($_GET['page'] ?? 1)
    ];
    echo json_encode($db->getOfferLettersPaged($filters));
    exit;
}
?>
