<?php
session_start();
// require_once 'action.php';
// $counter = $db->printTableRowsCount();
// If the user is not logged in redirect to the login page...
if (!isset($_SESSION['loggedin'])) {
	header('Location: /');
	exit;
}
// Check if the user's role is allowed to access this page
$allowed_roles = ['superuseradmin']; // Define allowed roles for this page
$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : ''; // Get user's role from session
$allow_access = isset($_SESSION['allow_access']) ? $_SESSION['allow_access'] : ''; // Get user's access level

// Define restricted directories for "few access" users
$restricted_paths = [
    '/superadmin_new/payment-tracking',
    '/superadmin_new/companyassets',
    '/superadmin_new/accounts',
    '/superadmin_new/property-bookings',
    '/superadmin_new/expenses',
    '/superadmin_new/incentive-tracking',
    '/hrlogin/createuser/',
    '/superadmin_new/users'
];

// Get the current URL path without query parameters
$current_path = strtok($_SERVER['REQUEST_URI'], '?');

// Restrict access based on user role and access permissions
if ($allow_access !== 'full access' && in_array($current_path, $restricted_paths)) {
    // Log the unauthorized access attempt for debugging (optional)
    error_log("Unauthorized access attempt to $current_path by user role: $user_role");
    
    // Redirect to access denied page
    header('Location: access_denied.html');
    exit;
}

if (!in_array($user_role, $allowed_roles)) {
    // User's role is not allowed, redirect to an error page or homepage
    header('Location: access_denied.html'); // Redirect to an error page
    exit;
}
// fitch name from  account table to show to the user
$tablename = $_SESSION['tablename'];
$nameuser = $_SESSION['username'];
// Check if the logged-in user is a superadmin and set the session variable accordingly
if ($nameuser === $nameuser) { // Replace 'your_superadmin_username' with the actual superadmin's username
  $_SESSION['is_superadmin'] = true;
}
require 'config.php';
$config = new Config();
$conn = $config->getConnection();

// Fetch tracking table
$sql_tracking = "SELECT id, month, gen_revenue, recent_pay, remaning_amt, user_name, bookin_number, send_amt FROM tracking_table";
$stmt_tracking = $conn->prepare($sql_tracking);
$stmt_tracking->execute();
$tracking_data = $stmt_tracking->fetchAll(PDO::FETCH_ASSOC); // Fetch all rows from tracking table

// Fetch Payment Table
$sql_payment = "SELECT id, overall_earn, overall_paid, advance_pay, remaning_payment, user_name, bookin_number FROM payment_table";
$stmt_payment = $conn->prepare($sql_payment);
$stmt_payment->execute();
$payment_data = $stmt_payment->fetchAll(PDO::FETCH_ASSOC); // Fetch all rows from payment table

// Fetch Assets Query
$sql_assets = "SELECT id, employee_name, employee_id, phone_number, laptop_id, project, office_location,
        laptop_brand, sim_cad, company_laptop, laptop_charger, company_mouse, datesignature
        FROM company_assets";
$stmt_assets = $conn->prepare($sql_assets);
$stmt_assets->execute();
$assets_data = $stmt_assets->fetchAll(PDO::FETCH_ASSOC); // Fetch all rows from assets table
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Super Admin Dashboard</title>
  <link rel="shortcut icon" type="nobglogo.png" href="../assets/images/nobglogo.png" alt="text">
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/5.0.0-alpha2/css/bootstrap.min.css">
  <script src="../assets/js/bootstrap_alpha2.js"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <link rel="stylesheet" href="../assets/css/dataTable2.0.4.css" />
  <link rel="stylesheet" href="../assets/css/button_dataTable3.0.2.css" />
  <link rel="stylesheet" href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css"/>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" />
  <link rel="stylesheet" href="../assets/css/fixed_dataTable5.0.0.css"/>
  <link rel="stylesheet" href="../assets/css/jquery_dataTable.css">
  <link rel="stylesheet" href="../assets/css/style.css"/>
  <link rel="stylesheet" href="./assets/css/style1.css"/>
  <link rel="stylesheet" href="../assets/css/loader.css"/>
  <link rel="stylesheet" href="./assets/css/style_dashboard.css"/>
