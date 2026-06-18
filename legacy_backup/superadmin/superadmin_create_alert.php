<?php
include('htmlopen.php');
session_start();
require_once 'config.php';

$allow_access = isset($_SESSION['allow_access']) ? $_SESSION['allow_access'] : '';

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

$current_path = strtok($_SERVER['REQUEST_URI'], '?');

if ($allow_access !== 'full access' && in_array($current_path, $restricted_paths)) {
    error_log("Unauthorized access attempt to $current_path by user role: $user_role");
    header('Location: access_denied.html');
    exit;
}

if ($_SESSION['role'] !== 'superuseradmin') {
    header('Location: access_denied.html');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $alertMessage = $_POST['alert_message'];
    
    $config = new Config();
    $conn = $config->getConnection();

    $insertAlert = "INSERT INTO alerts (alert_message) VALUES (:alert_message)";
    $stmt = $conn->prepare($insertAlert);
    $stmt->bindValue(':alert_message', $alertMessage, PDO::PARAM_STR);
    
    if ($stmt->execute()) {
        $_SESSION['alert_success'] = 'Alert created successfully!';
    } else {
        $_SESSION['alert_error'] = 'Failed to create alert. Please try again.';
    }

    header('Location: superadmin_create_alert');
    exit;
}
?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.6.0/tinymce.min.js" referrerpolicy="no-referrer"></script>
<script>
    tinymce.init({
        selector: '#alert_message',
        plugins: 'advlist autolink lists link image charmap preview anchor',
        toolbar: 'undo redo | bold italic underline | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | help',
        menubar: false,
        setup: function (editor) {
            editor.on('change', function () {
                editor.save();
            });
        }
    });

    function submitForm() {
        tinymce.get('alert_message').save();
        return true;
    }
</script>
<style>
    .alert-form {
        width: 100%;
        max-width: 600px;
        margin: 0 auto;
        padding: 20px;
        border: 1px solid #ddd;
        border-radius: 8px;
        background-color: #f9f9f9;
        transition: background-color 0.3s, border-color 0.3s;
    }
    .form-label {
        display: block;
        margin-bottom: 10px;
        font-weight: 700;
        color: #333;
        transition: color 0.3s;
    }
    .form-textarea {
        width: 100%;
        height: 300px;
        border: 1px solid #ddd;
        border-radius: 4px;
        background-color: #fff;
        color: #333;
        transition: background-color 0.3s, border-color 0.3s, color 0.3s;
    }
    .form-button {
        display: inline-block;
        padding: 10px 20px;
        margin-top: 10px;
        font-size: 16px;
        color: #fff;
        background-color: #007bff;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        transition: background-color 0.2s;
    }
    .form-button:hover { background-color: #0056b3; }

    /* ── Dark Mode ── */
    body.dark-mode .alert-form {
        background-color: #1e2a3a;
        border-color: #2d3f55;
    }
    body.dark-mode .form-label {
        color: #cbd5e1;
    }
    body.dark-mode .form-textarea {
        background-color: #162032;
        border-color: #2d3f55;
        color: #e2e8f0;
    }
    body.dark-mode .form-button {
        background-color: #2563eb;
    }
    body.dark-mode .form-button:hover {
        background-color: #1d4ed8;
    }

    /* Dark mode SweetAlert2 toast */
    body.dark-mode .custom-toast-popup {
        background-color: #1e2a3a !important;
        color: #e2e8f0 !important;
        border: 1px solid #2d3f55 !important;
    }
    body.dark-mode .custom-toast-popup .swal2-title {
        color: #e2e8f0 !important;
    }
    body.dark-mode .custom-toast-popup .swal2-icon.swal2-success .swal2-success-ring {
        border-color: rgba(72, 199, 142, 0.3) !important;
    }

    .side-menu li.sideactive7 {
        background: var(--shicol);
        position: relative;
    }
    .side-menu li.sideactive7 a { color: white; }
    #togglerightsidebar { display: none; }
</style>
<?php include('header.php'); ?>
<div class="content">
    <form method="POST" action="superadmin_create_alert.php" class="alert-form" onsubmit="return submitForm();">
        <label for="alert_message" class="form-label">Enter Alert Message:</label>
        <textarea name="alert_message" id="alert_message" class="form-textarea" required></textarea>
        <button type="submit" class="form-button">Create Alert</button>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const notifBtn      = document.querySelector('#notificationBtn');
    const notifBox      = document.querySelector('#notif-content_box');
    const profileIcon   = document.querySelector('#more_profile_icon');
    const profileBox    = document.querySelector('#profile-content_box');
    const closeNotif    = document.querySelector('.closebtn');
    const closeProfile  = document.querySelector('.closebtn1');

    if (notifBtn && notifBox) {
        notifBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            notifBox.style.display = notifBox.style.display === 'block' ? 'none' : 'block';
            if (profileBox) profileBox.style.display = 'none';
        });
    }

    if (profileIcon && profileBox) {
        profileIcon.addEventListener('click', function (e) {
            e.stopPropagation();
            profileBox.style.display = profileBox.style.display === 'block' ? 'none' : 'block';
            if (notifBox) notifBox.style.display = 'none';
        });
    }

    if (closeNotif) {
        closeNotif.addEventListener('click', function () {
            if (notifBox) notifBox.style.display = 'none';
            if (profileBox) profileBox.style.display = 'none';
        });
    }

    if (closeProfile) {
        closeProfile.addEventListener('click', function () {
            if (profileBox) profileBox.style.display = 'none';
        });
    }

    // Close on outside click
    document.addEventListener('click', function (e) {
        if (notifBox && !notifBox.contains(e.target) && e.target !== notifBtn) {
            notifBox.style.display = 'none';
        }
        if (profileBox && !profileBox.contains(e.target) && e.target !== profileIcon) {
            profileBox.style.display = 'none';
        }
    });
});
</script>

<?php
$toastType = '';
$toastMsg  = '';
if (isset($_SESSION['alert_success'])) {
    $toastType = 'success';
    $toastMsg  = $_SESSION['alert_success'];
    unset($_SESSION['alert_success']);
} elseif (isset($_SESSION['alert_error'])) {
    $toastType = 'error';
    $toastMsg  = $_SESSION['alert_error'];
    unset($_SESSION['alert_error']);
}
?>
<?php if ($toastType): ?>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        Swal.fire({
            toast: true,
            position: 'bottom',
            icon: '<?php echo $toastType; ?>',
            title: '<?php echo addslashes($toastMsg); ?>',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            customClass: {
                popup: 'custom-toast-popup'
            }
        });
    });
</script>
<?php endif; ?>
<?php include('htmlclose.php'); ?>
