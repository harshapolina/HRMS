<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
// Database connection details
$servername = "localhost";
$username = "u903436302_shincentiveapp"; // Change this to your MySQL username
$password = "Search#$%Target8866@$#@!007"; // Change this to your MySQL password
$dbname = "u903436302_incentiveApp"; // Change this to your MySQL database name

// Create a new MySQLi connection
$conn = new mysqli($servername, $username, $password, $dbname);



// Check the connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if form data exists
if (!empty($_POST)) {
    // Retrieve form data
    $username = $_POST['username'];
    $number = $_POST['phone'];
    $salary = $_POST['salary'];
    $email = $_POST['email'];
    $table = $_POST['table'];
    $emId = $_POST['emId'];
    $password = $_POST['password'];
    $doj = $_POST['doj'];
    $dob = $_POST['dob'];
    $frist = $_POST['amountO'];
    $secound = $_POST['amountT'];
    $third = $_POST['amountTh'];
    $forth = $_POST['amountF'];
    $fifth = $_POST['amountFf'];
    $sixth = $_POST['amountS'];
    $project_n = $_POST['project_name'];
    $Project_type = $_POST['D_project'];
    $user_type = $_POST['user_type'];
    $user_assign = $_POST['assign_user'];
    $agent_city = $_POST['agent_city'];

    // Perform validation if needed

    // Start a transaction to ensure data integrity
    $conn->begin_transaction();

    try {
        // Prepare the SQL statement to insert into `accounts`
    $is_active = 1;
    $stmt = $conn->prepare("INSERT INTO accounts (username, phonenumber, salary, useremail, tablename, employee_id, epassword, user_type, doj, dob, one_amt, two_amt, thrid_amt, forth_amt, fifth_amt, sixth_amt, project_name, project_type, assign_user, agent_city, city, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssissssssssssssisssssi", $username, $number, $salary, $email, $table, $emId, $password, $user_type, $doj, $dob, $frist, $secound, $third, $forth, $fifth, $sixth, $project_n, $Project_type, $user_assign, $agent_city, $agent_city, $is_active);

        // Execute the statement
        if ($stmt->execute()) {
            // Get the ID of the newly inserted user
            $newUserId = $conn->insert_id;

            // Insert the current manager into `assign_user_history` with the current date
            $currentDate = date('Y-m-d');  // Today's date
            $historyStmt = $conn->prepare("INSERT INTO assign_user_history (user_id, assign_user, effective_date) VALUES (?, ?, ?)");
            $historyStmt->bind_param("iss", $newUserId, $user_assign, $currentDate);

            // Execute the history statement
            if ($historyStmt->execute()) {
                // Commit the transaction if everything is successful
                $conn->commit();
                echo "<script>
                        alert('New account created successfully');
                        window.location.href = '/hrlogin/createuser/';
                      </script>";
            } else {
                // If inserting into history fails, rollback the transaction
                $conn->rollback();
                echo "Error: " . $historyStmt->error;
            }
        } else {
            // If inserting into accounts fails, rollback the transaction
            $conn->rollback();
            echo "Error: " . $stmt->error;
        }
    } catch (Exception $e) {
        // Catch any exceptions and rollback the transaction
        $conn->rollback();
        echo "Transaction failed: " . $e->getMessage();
    }
}
// Close the database connection
$conn->close();
?>