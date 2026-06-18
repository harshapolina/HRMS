<?php
session_start();
// Change this to your connection info.
$DATABASE_HOST = 'localhost';
$DATABASE_USER = 'u797909128_demoproject';
$DATABASE_PASS = 'QK&0/aF@5';
$DATABASE_NAME = 'u797909128_demo';
// Try and connect using the info above.
$con = mysqli_connect($DATABASE_HOST, $DATABASE_USER, $DATABASE_PASS, $DATABASE_NAME);
if ( mysqli_connect_errno() ) {
	// If there is an error with the connection, stop the script and display the error.
	exit('Failed to connect to MySQL: ' . mysqli_connect_error());
}
// Now we check if the data from the login form was submitted, isset() will check if the data exists.
if ( !isset($_POST['useremail'], $_POST['password'], $_POST['code']) ) {
	// Could not get the data that should have been sent.
	exit('Please fill both the username and password fields!');
}
// Prepare our SQL, preparing the SQL statement will prevent SQL injection.
if ($stmt = $con->prepare('SELECT id, epassword, tablename, username, salary, code, role FROM adminaccounts WHERE useremail = ?')) {
	// Bind parameters (s = string, i = int, b = blob, etc), in our case the username is a string so we use "s"
	$stmt->bind_param('s', $_POST['useremail']);
	$stmt->execute();
	// Store the result so we can check if the account exists in the database.
	$stmt->store_result();
	if ($stmt->num_rows > 0) {
	$stmt->bind_result($id, $password, $table, $username, $salary, $adminCode, $role);
	$stmt->fetch();
	// Account exists, now we verify the password.
	// Note: remember to use password_hash in your registration file to store the hashed passwords.
	if ($_POST['password'] === $password) {
		// Verification success! User has logged-in!
		// Create sessions, so we know the user is logged in, they basically act like cookies but remember the data on the server.
		session_regenerate_id();
		$_SESSION['loggedin'] = TRUE;
		$_SESSION['name'] = $_POST['useremail'];
		$_SESSION['id'] = $id;
		$_SESSION['tablename'] = $table;
		$_SESSION['username'] = $username;
		$_SESSION['salary'] = $salary;
		$_SESSION['code'] = $_POST['code'];
		$_SESSION['codeadmin'] = $adminCode;
		$_SESSION['role'] = 'superuseradmin';
		header('Location: /superadmin_new/dashboard');
	} else {
		'<h2>Incorrect Password</h2>';
		
	}
} else {
	'<h2>Incorrect Username</h2>';
	
}

	$stmt->close();
}
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title></title>
</head>
<body>
<p style="color:red;"><?php
    echo '<script>alert("Incorrect username and/or password!")</script>';
    echo '<script>window.location = "/";</script>';
?></p>
</body>
</html>