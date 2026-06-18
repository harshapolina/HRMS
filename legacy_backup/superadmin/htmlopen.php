<?php
session_start();
// If the user is not logged in redirect to the login page...
if (!isset($_SESSION['loggedin'])) {
    header('Location: /');
    exit;
}
// Check if the user's role is allowed to access this page
$allowed_roles = array('hradminuser');
$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : '';

if (!in_array($user_role, $allowed_roles)) {
    header('Location: access_denied.html');
    exit;
}
$nameuser = isset($_SESSION['username']) ? $_SESSION['username'] : '';
if ($nameuser === $nameuser) {
    $_SESSION['is_superadmin'] = true;
}
if (!(isset($_SESSION['loggedin']) && isset($_SESSION['is_superadmin']) && $_SESSION['is_superadmin'] === true)) {
    header('Location: access_denied.html');
    exit;
}

require_once dirname(__FILE__) . '/hr_paths.inc.php';

$servername = "localhost";
$username = "u797909128_demoproject";
$password = "QK&0/aF@5";
$dbname = "u797909128_demo";

$result = false;
$result_pay = false;
$result_assets = false;
$conn = null;

if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_OFF);
}

if (class_exists('mysqli')) {
    $tmpConn = @new mysqli($servername, $username, $password, $dbname);
    if ($tmpConn && $tmpConn->connect_errno === 0) {
        $conn = $tmpConn;
        $sql_tracking = "SELECT id, month, gen_revenue, recent_pay, remaning_amt, user_name, bookin_number, send_amt FROM tracking_table";
        $result = @$conn->query($sql_tracking);
        $sql_payment = "SELECT id, overall_earn, overall_paid, advance_pay, remaning_payment, user_name, bookin_number FROM payment_table";
        $result_pay = @$conn->query($sql_payment);
        $sql_assets = "SELECT id, employee_name, employee_id, phone_number, laptop_id, project, office_location,
        laptop_brand, sim_cad, company_laptop, laptop_charger, company_mouse, datesignature
        FROM company_assets";
        $result_assets = @$conn->query($sql_assets);
    } else {
        if ($tmpConn instanceof mysqli) {
            @$tmpConn->close();
        }
        $conn = null;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Hr Admin Dashboard</title>
    <link rel="shortcut icon" type="nobglogo.png" href="../assets/images/nobglogo.png" alt="text">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/5.0.0-alpha2/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-alpha2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- DataTables Core & Extensions CDN -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/fixedcolumns/4.3.0/css/fixedColumns.dataTables.min.css">
    
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.colVis.min.js"></script>
    <script src="https://cdn.datatables.net/fixedcolumns/4.3.0/js/dataTables.fixedColumns.min.js"></script>

    <link rel="stylesheet" href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <?php if (!isset($skip_superadmin_css) || !$skip_superadmin_css): ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($hr_superadmin_css_href, ENT_QUOTES); ?>" />
    <?php endif; ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($hr_overrides_css_href, ENT_QUOTES); ?>" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Lexend+Deca:wght@100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/loader.css" />