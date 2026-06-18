<?php
session_start();

$data = json_decode(file_get_contents('php://input'), true);

if ($data) {
    $_SESSION['username'] = $data['username'];
    $_SESSION['tablename'] = $data['tablename'];
    $_SESSION['project_type'] = $data['project_type'];
    $_SESSION['user_type'] = $data['user_type'];
    $_SESSION['assign_user'] = $data['assign_user'];
    $_SESSION['salary'] = $data['salary'];
    $_SESSION['one_amt'] = $data['one_amt'];
    $_SESSION['two_amt'] = $data['two_amt'];
    $_SESSION['thrid_amt'] = $data['thrid_amt'];
    $_SESSION['forth_amt'] = $data['forth_amt'];
    $_SESSION['fifth_amt'] = $data['fifth_amt'];
    $_SESSION['sixth_amt'] = $data['sixth_amt'];
}

echo json_encode(['status' => 'success']);
?>
