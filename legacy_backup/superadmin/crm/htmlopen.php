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
  '/superadmin/payment-tracking',
  '/superadmin/companyassets',
  '/superadmin/accounts',
  '/superadmin/property-bookings',
  '/superadmin/expenses',
  '/superadmin/incentive-tracking',
  '/hrlogin/createuser/',
  '/superadmin/users'
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
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Super Admin Dashboard</title>
  <!-- Font Awesome (shared with userlogin6) + CDN fallbacks (FA6 + FA5) -->
  <link rel="stylesheet" href="../../userlogin6/assets/css/all.min.css?v=20250930">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw=="
    crossorigin="anonymous" referrerpolicy="no-referrer">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css"
    integrity="sha512-1ycn6IcaQQ40/MKBW2W4Rhis/DbILU74C1vSrLJxCq57o941Ym01SwNsOMqvEBFlcgUa6xLiPY/NS5R+E6ztJQ=="
    crossorigin="anonymous" referrerpolicy="no-referrer">
  <!-- Favicon: use site-wide fallback first to avoid 404s, then prefer project path if available -->
  <!-- Prevent browser from requesting /favicon.ico (avoid 404). We provide a small data-URI SVG favicon
      and also a fallback to a project logo if present. Update the fallback path if you have a real favicon. -->
  <link rel="icon" type="image/svg+xml"
    href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Crect width='16' height='16' rx='2' fill='%23007bff'/%3E%3Ctext x='8' y='11' font-size='9' text-anchor='middle' fill='white' font-family='Arial,sans-serif'%3ES%3C/text%3E%3C/svg%3E">
  <link rel="shortcut icon" href="../assets/dataimage/nobglogo.png" />
  <!-- Bootstrap CSS (official CDN) -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script> -->
  <!-- Bootstrap bundle (includes Popper) -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Lexend+Deca:wght@100..900&display=swap" rel="stylesheet">
  <!-- <link rel="stylesheet" href="./assets/css/crm_structure_style.css"/> -->
  <link rel="stylesheet" href="../assets/css/style1.css" />
  <!-- <link rel="stylesheet" href="../../assets/css/loader.css"/> -->
  <link rel="stylesheet" href="./assets/css/crm_table_style.css" />
  <link rel="stylesheet" href="../assets/css/style_dashboard.css" />