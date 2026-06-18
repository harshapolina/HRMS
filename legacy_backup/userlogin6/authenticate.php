<?php
require_once dirname(__DIR__) . '/env_loader.php';
session_start();
// Change this to your connection info.
$DATABASE_HOST = getenv('DB_INCENTIVE_HOST') ?: 'localhost';
$DATABASE_USER = getenv('DB_INCENTIVE_USER') ?: 'u903436302_shincentiveapp';
$DATABASE_PASS = getenv('DB_INCENTIVE_PASS_ALT') ?: 'SearchIncentive@$#@!007';
$DATABASE_NAME = getenv('DB_INCENTIVE_NAME') ?: 'u903436302_incentiveApp';
// Try and connect using the info above.
$con = mysqli_connect($DATABASE_HOST, $DATABASE_USER, $DATABASE_PASS, $DATABASE_NAME);
if ( mysqli_connect_errno() ) {
	// If there is an error with the connection, stop the script and display the error.
	exit('Failed to connect to MySQL: ' . mysqli_connect_error());
}
// Now we check if the data from the login form was submitted, isset() will check if the data exists.
if ( !isset($_POST['useremail'], $_POST['password']) ) {
	// Could not get the data that should have been sent.
	exit('Please fill both the username and password fields!');
}
// Prepare our SQL, preparing the SQL statement will prevent SQL injection.
if ($stmt = $con->prepare('SELECT id, epassword, tablename, username, 
    salary, user_type, old_salary, one_amt, two_amt, thrid_amt, forth_amt, 
    fifth_amt, sixth_amt, project_name, project_type, role, assign_user, 
    flag_user_login, is_active FROM accounts_copy WHERE useremail = ?')) {
	// Bind parameters (s = string, i = int, b = blob, etc), in our case the username is a string so we use "s"
	$stmt->bind_param('s', $_POST['useremail']);
	$stmt->execute();
	// Store the result so we can check if the account exists in the database.
	$stmt->store_result(); 
	if ($stmt->num_rows > 0) {
	$stmt->bind_result($id, $password, 
	$table, $username, 
	$salary, $user_type, 
	$oldSalary, $frist, 
	$secound, $third, 
	$forth, $fifth, 
	$sixth, $project_n, 
	$Project_type, $role, 
	$assign_person, $flag_user_login,
	$is_active);
	$stmt->fetch();
	// Account exists, now we verify the password.
	// Note: remember to use password_hash in your registration file to store the hashed passwords.
	if ($_POST['password'] === $password) {
		// Verification success! User has logged in!
		if ($is_active == 1) {
		// Create sessions, so we know the user is logged in, they basically act like cookies but remember the data on the server.
		session_regenerate_id();
		$_SESSION['loggedin'] = TRUE;
		$_SESSION['name'] = $_POST['useremail'];
		$_SESSION['id'] = $id;
		$_SESSION['tablename'] = $table;
		$_SESSION['username'] = $username;
		$_SESSION['salary'] = $salary;
		$_SESSION['old_salary'] = $oldSalary;
		$_SESSION['one_amt'] = $frist;
		$_SESSION['two_amt'] = $secound;
		$_SESSION['thrid_amt'] = $third;
		$_SESSION['forth_amt'] = $forth;
		$_SESSION['fifth_amt'] = $fifth;
		$_SESSION['sixth_amt'] = $sixth;
		$_SESSION['user_type'] = $user_type;
		$_SESSION['project_type'] = $Project_type;
		$_SESSION['role'] = 'regularuser';
		$_SESSION['assign_user'] = $assign_person;
		$_SESSION['flag_user_login'] = $flag_user_login; 
		header('Location: /userlogin/dashboard');
		} else {
			echo '<script>alert("Your account is inactive. Please contact the administrator")</script>';
			echo '<script>window.location = "/";</script>';
		}
	} else {
		'<h2>Incorrect Password</h2>';
		
	}
} else {
	'<h2>Incorrect Username</h2>';
	
}

	$stmt->close();
}
else {
    echo '<h2>Could not prepare statement!</h2>';
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