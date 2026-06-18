<?php
session_start();
include 'config.php';

// Check if the user is logged in
if (!isset($_SESSION['loggedin'])) {
    header('Location: /');
    exit;
}

$config = new Config();
$conn = $config->getConnection();

// Retrieve the user ID from the session
$userId = $_SESSION['id'];

// Prepare the SQL statement using PDO
$stmt = $conn->prepare('SELECT * FROM accounts_copy WHERE id = :id');
$stmt->bindValue(':id', $userId, PDO::PARAM_INT);  // Bind the user ID parameter

// Execute the query
$stmt->execute();

// Fetch the result
$row = $stmt->fetch();

if ($row) {
    $_SESSION['username'] = $row['username'];
    $_SESSION['tablename'] = $row['tablename'];
    $_SESSION['project_type'] = $row['project_type'];
    $_SESSION['user_type'] = $row['user_type'];
    $_SESSION['assign_user'] = $row['assign_user'];
    $_SESSION['salary'] = $row['salary'];
    $_SESSION['one_amt'] = $row['one_amt'];
    $_SESSION['two_amt'] = $row['two_amt'];
    $_SESSION['thrid_amt'] = $row['thrid_amt'];
    $_SESSION['forth_amt'] = $row['forth_amt'];
    $_SESSION['fifth_amt'] = $row['fifth_amt'];
    $_SESSION['sixth_amt'] = $row['sixth_amt'];
}

header('Content-Type: application/json');
echo json_encode([
    'username' => $_SESSION['username'],
    'tablename' => $_SESSION['tablename'],
    'project_type' => $_SESSION['project_type'],
    'user_type' => $_SESSION['user_type'],
    'assign_user' => $_SESSION['assign_user'],
    'salary' => $_SESSION['salary'],
    'one_amt' => $_SESSION['one_amt'],
    'two_amt' => $_SESSION['two_amt'],
    'thrid_amt' => $_SESSION['thrid_amt'],
    'forth_amt' => $_SESSION['forth_amt'],
    'fifth_amt' => $_SESSION['fifth_amt'],
    'sixth_amt' => $_SESSION['sixth_amt']
]);
?>