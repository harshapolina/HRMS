<?php
session_start();
require_once 'config.php';

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

// Check if the superadmin is logged in and has the right role
if ($_SESSION['role'] !== 'superuseradmin') {
    header('Location: access_denied.html');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $alertMessage = $_POST['alert_message'];
    
    $config = new Config();
    $conn = $config->getConnection(); // Retrieve the connection using getConnection()

    $insertAlert = "INSERT INTO alerts (alert_message) VALUES (:alert_message)";
    $stmt = $conn->prepare($insertAlert);
    $stmt->bindValue(':alert_message', $alertMessage, PDO::PARAM_STR);
    
    if ($stmt->execute()) {
        $_SESSION['alert_success'] = 'Alert created successfully!';
    } else {
        $_SESSION['alert_error'] = 'Failed to create alert. Please try again.';
    }

    header('Location: superadmin_create_alert'); // Adjust the redirect path as needed
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Super Create Alert</title>
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
  <script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.6.0/tinymce.min.js" integrity="sha512-..." crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script>
        tinymce.init({
            selector: '#alert_message',
            plugins: 'advlist autolink lists link image charmap preview anchor textcolor',
            toolbar: 'undo redo | bold italic underline | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | help',
            menubar: false,
            setup: function (editor) {
                editor.on('change', function () {
                    editor.save();
                });
            }
        });

        function submitForm() {
            // Ensure TinyMCE content is updated in the textarea
            tinymce.get('alert_message').save();
            return true; // Proceed with form submission
        }
    </script>
<style>
    .alert-form{width:100%;max-width:600px;margin:0 auto;padding:20px;border:1px solid #ddd;border-radius:8px;background-color:#f9f9f9}.form-label{display:block;margin-bottom:10px;font-weight:700}.form-textarea{width:100%;height:300px;border:1px solid #ddd;border-radius:4px}.form-button{display:inline-block;padding:10px 20px;margin-top:10px;font-size:16px;color:#fff;background-color:#007bff;border:none;border-radius:4px;cursor:pointer}.form-button:hover{background-color:#0056b3}.error-message,.success-message{margin-top:20px;padding:10px;border-radius:4px}.success-message{background-color:#d4edda;color:#155724;border:1px solid #c3e6cb}.error-message{background-color:#f8d7da;color:#721c24;border:1px solid #f5c6cb}
    .side-menu li.sideactive7{
      background: var(--shicol);
    position: relative
  }
  .side-menu li.sideactive7 a{
      color: white
  }
  #togglerightsidebar{display:none}
</style>
<?php include('header.php'); ?>
<div class="content">
    <form method="POST" action="superadmin_create_alert.php" class="alert-form" onsubmit="return submitForm();">
        <label for="alert_message" class="form-label">Enter Alert Message:</label>
        <textarea name="alert_message" id="alert_message" class="form-textarea" required></textarea>
        <button type="submit" class="form-button">Create Alert</button>
    </form>

    <?php if (isset($_SESSION['alert_success'])): ?>
        <p class="success-message"><?php echo htmlspecialchars($_SESSION['alert_success']); unset($_SESSION['alert_success']); ?></p>
    <?php elseif (isset($_SESSION['alert_error'])): ?>
        <p class="error-message"><?php echo htmlspecialchars($_SESSION['alert_error']); unset($_SESSION['alert_error']); ?></p>
    <?php endif; ?>
</div>
<?php include('htmlclose.php'); ?>