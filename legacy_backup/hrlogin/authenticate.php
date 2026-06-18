<?php
session_start();
require_once __DIR__ . '/includes/db_mysqli.php';
try {
    $con = hr_mysqli_connect();
} catch (Throwable $e) {
    exit('Failed to connect to MySQL: ' . $e->getMessage());
}
// Now we check if the data from the login form was submitted.
if (!isset($_POST['useremail'], $_POST['password'])) {
    exit('Please fill both the username and password fields!');
}
$useremail = $_POST['useremail'];
$password = $_POST['password'];
// STEP 1: Check Superadmin Table (Admins)
if ($stmt = $con->prepare('SELECT id, epassword, username, role, tablename FROM superadmin WHERE useremail = ?')) {
    $stmt->bind_param('s', $useremail);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->bind_result($id, $db_password, $username, $role, $tablename);
        $stmt->fetch();
        if ($password === $db_password) {
            session_regenerate_id();
            $_SESSION['loggedin'] = TRUE;
            $_SESSION['name'] = $useremail;
            $_SESSION['id'] = $id;
            $_SESSION['username'] = $username;
            $_SESSION['tablename'] = !empty($tablename) ? $tablename : $username;
            $_SESSION['role'] = 'hradminuser'; // Identified as Admin
            header('Location: users.php');
            exit;
        }
    }
    $stmt->close();
}
// STEP 2: Check Accounts Table (Employees) if not found in Superadmin
if ($stmt = $con->prepare('SELECT id, epassword, username FROM accounts WHERE useremail = ?')) {
    $stmt->bind_param('s', $useremail);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->bind_result($id, $db_password, $username);
        $stmt->fetch();
        if ($password === $db_password) {
            session_regenerate_id();
            $_SESSION['loggedin'] = TRUE;
            $_SESSION['name'] = $useremail;
            $_SESSION['id'] = $id;
            $_SESSION['username'] = $username;
            $_SESSION['role'] = 'employee'; // Identified as Employee
            header('Location: employee_portal.php');
            exit;
        }
    }
    $stmt->close();
}
// If we reached here, login failed
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login Failed</title>
</head>
<body>
    <p style="color:red;">
        <?php
        echo '<script>alert("Incorrect email and/or password!")</script>';
        echo '<script>window.location = "index1.html";</script>';
        ?>
    </p>
</body>
</html>