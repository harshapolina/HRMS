<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config.php';
$config = new Config();
$conn = $config->getConnection();

$tablename = $_SESSION['tablename'];
$salary = $_SESSION['salary'];
$assign_person = $_SESSION['assign_user'];

function build_assign_person_list_up($conn, $startTable)
{
    if (empty($startTable)) {
        return '';
    }

    $chain = [];
    $visited = [];
    $sql = "SELECT assign_user FROM accounts WHERE tablename = :tablename LIMIT 1";
    $stmt = $conn->prepare($sql);

    $current = $startTable;

    while (true) {
        $stmt->execute([':tablename' => $current]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || empty($row['assign_user'])) {
            break;
        }

        $parent = trim((string) $row['assign_user']);
        if ($parent === '' || $parent === $current || isset($visited[$parent])) {
            break;
        }

        $chain[] = $parent;
        $visited[$parent] = true;
        $current = $parent;
    }

    return empty($chain) ? '' : implode(',', $chain);
}

function calculate_deduct_agreement($agreementValue, $cashbackPct)
{
    $agreement = is_numeric($agreementValue) ? (float) $agreementValue : 0.0;
    $cashback = is_numeric($cashbackPct) ? (float) $cashbackPct : 0.0;

    if ($agreement <= 0) {
        return 0.0;
    }

    $reductionPct = 0.0;
    if ($cashback >= 0.1 && $cashback <= 0.50) {
        $reductionPct = 25.0;
    } elseif ($cashback > 0.50 && $cashback <= 1.00) {
        $reductionPct = 50.0;
    } elseif ($cashback > 1.00 && $cashback <= 1.50) {
        $reductionPct = 75.0;
    } elseif ($cashback > 1.50) {
        $reductionPct = 100.0;
    }

    return round($agreement - ($agreement * ($reductionPct / 100.0)), 2);
}

// Handle GET request for checking unit number uniqueness
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['action']) && $_GET['action'] == 'check_unit') {
    header('Content-Type: application/json');
    $unitno = $_GET['unitno'] ?? '';
    
    if (empty($unitno)) {
        echo json_encode(['exists' => false, 'message' => 'No unit number provided']);
        exit;
    }
    
    try {
        $sql_check = "SELECT id, project, customer_name FROM admintable WHERE unit_no = :unitno LIMIT 1";
        $stmt_check = $conn->prepare($sql_check);
        $stmt_check->bindParam(':unitno', $unitno, PDO::PARAM_STR);
        $stmt_check->execute();
        $existing_booking = $stmt_check->fetch(PDO::FETCH_ASSOC);
        
        if ($existing_booking) {
            echo json_encode([
                'exists' => true, 
                'message' => 'Unit number ' . $unitno . ' already exists in the system (Project: ' . $existing_booking['project'] . ', Customer: ' . $existing_booking['customer_name'] . '). Please use a unique unit number.'
            ]);
        } else {
            echo json_encode(['exists' => false, 'message' => 'Unit number is available']);
        }
    } catch (PDOException $e) {
        echo json_encode(['exists' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $bdate = $_POST['bdate'];
    $bmonth = $_POST['bmonth'];
    $developer = $_POST['developer'];
    $bproject = $_POST['bproject'];
    $cname = $_POST['cname'];
    $cnumber = $_POST['cnumber'];
    $cemail = $_POST['cemail'];
    $tproject = $_POST['tproject'];
    $canceleoi = isset($_POST['canceleoi']) ? 1 : 0;
    $converted = isset($_POST['converted']) ? 1 : 0;
    $eoi_id = $_POST['id'] ?? null; // Get EOI ID for update

    // Additional fields for the converted case
    $unitno = $_POST['unitno'] ?? null;
    $psize = $_POST['psize'] ?? null;
    $cagreement = $_POST['cagreement'] ?? null;
    $ccashback = $_POST['ccashback'] ?? null;
    $crevenue = $_POST['crevenue'] ?? null;
    $cccashback = $_POST['cccashback'] ?? null;
    $ccrevenue = $_POST['ccrevenue'] ?? null;
    $city = $_POST['city'] ?? '';
    $leadsource = trim($_POST['leadsource'] ?? '');
    $bremarks = trim($_POST['bremarks'] ?? '');
    $cstatus = $_POST['cstatus'] ?? null;
    $brecived = $_POST['brecived'] ?? null;
    $filePathStored = null;

    if (isset($_FILES['document']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['document']['tmp_name'];
        $fileName = time() . '_' . basename($_FILES['document']['name']);
        $uploadDir = '../superadmin/uploads_form/';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $filePath = $uploadDir . $fileName;
        if (move_uploaded_file($fileTmpPath, $filePath)) {
            $filePathStored = $filePath;
        } else {
            echo "Error: File upload failed.";
            exit;
        }
    }

    try {
        if ($canceleoi) {
            // Delete from usereoidata
            $sql_delete = "DELETE FROM usereoidata WHERE email_id = :cemail";
            $stmt = $conn->prepare($sql_delete);
            $stmt->bindParam(':cemail', $cemail, PDO::PARAM_STR);
            $stmt->execute();
            echo "Record deleted from usereoidata successfully.";
        } elseif ($converted) {
            // Check if unit number already exists in admintable (must be globally unique)
            $sql_check = "SELECT id, project, customer_name FROM admintable 
                         WHERE unit_no = :unitno 
                         LIMIT 1";
            $stmt_check = $conn->prepare($sql_check);
            $stmt_check->bindParam(':unitno', $unitno, PDO::PARAM_STR);
            $stmt_check->execute();
            $existing_booking = $stmt_check->fetch(PDO::FETCH_ASSOC);

            if ($existing_booking) {
                // Unit number already exists in admintable - reject the booking
                echo "Error: Unit number " . htmlspecialchars($unitno) . 
                     " already exists in the system (Project: " . htmlspecialchars($existing_booking['project']) . 
                     ", Customer: " . htmlspecialchars($existing_booking['customer_name']) . "). Please use a unique unit number.";
            } else {
                // Insert into admintable and delete from usereoidata
                $deduct_agreement_value = calculate_deduct_agreement($cagreement, $cccashback);
                $assign_person_hierarchy = build_assign_person_list_up($conn, $tablename);
                if ($assign_person_hierarchy === null) {
                    $assign_person_hierarchy = '';
                }

                $sql_insert = "INSERT INTO admintable (
                                booking_date, booking_month, builder, project, customer_name, contact_number, email_id, 
                                project_type, unit_no, size, agreement_value, cashback, revenue, ccashback, crevenue, 
                    astatus, update_date_column, source_table, msalary, assign_user, document_path, source_lead, remarks, deduct_agreement, city)
                               VALUES (:bdate, :bmonth, :developer, :bproject, :cname, :cnumber, :cemail, :tproject, 
                                       :unitno, :psize, :cagreement, :ccashback, :crevenue, :cccashback, :ccrevenue, 
                           :cstatus, NOW(), :tablename, :salary, :assign_person, :document_path, :leadsource, :bremarks, :deduct_agreement_value, :city)";
                $stmt = $conn->prepare($sql_insert);
                $stmt->bindParam(':bdate', $bdate);
                $stmt->bindParam(':bmonth', $bmonth);
                $stmt->bindParam(':developer', $developer);
                $stmt->bindParam(':bproject', $bproject);
                $stmt->bindParam(':cname', $cname);
                $stmt->bindParam(':cnumber', $cnumber);
                $stmt->bindParam(':cemail', $cemail);
                $stmt->bindParam(':tproject', $tproject);
                $stmt->bindParam(':unitno', $unitno);
                $stmt->bindParam(':psize', $psize);
                $stmt->bindParam(':cagreement', $cagreement);
                $stmt->bindParam(':ccashback', $ccashback);
                $stmt->bindParam(':crevenue', $crevenue);
                $stmt->bindParam(':cccashback', $cccashback);
                $stmt->bindParam(':ccrevenue', $ccrevenue);
                $stmt->bindParam(':cstatus', $cstatus);
                $stmt->bindParam(':tablename', $tablename);
                $stmt->bindParam(':salary', $salary);
                $stmt->bindParam(':assign_person', $assign_person_hierarchy);
                $stmt->bindParam(':document_path', $filePathStored);
                $stmt->bindParam(':leadsource', $leadsource);
                $stmt->bindParam(':bremarks', $bremarks);
                $stmt->bindParam(':deduct_agreement_value', $deduct_agreement_value);
                $stmt->bindParam(':city', $city);
                $stmt->execute();

                // Delete from usereoidata
                $sql_delete = "DELETE FROM usereoidata WHERE email_id = :cemail";
                $stmt = $conn->prepare($sql_delete);
                $stmt->bindParam(':cemail', $cemail, PDO::PARAM_STR);
                $stmt->execute();
                echo "Record moved to admintable successfully.";
            }
        } else {
            // Check if this is an update (has ID) or a new insert
            if (!empty($eoi_id)) {
                // UPDATE existing EOI record
                $sql_update = "UPDATE usereoidata SET
                              booking_date = :bdate,
                              booking_month = :bmonth,
                              builder = :developer,
                              project = :bproject,
                              customer_name = :cname,
                              contact_number = :cnumber,
                              email_id = :cemail,
                              project_type = :tproject,
                              canceleoi = :canceleoi,
                              astatus = :cstatus
                              WHERE id = :eoi_id";
                $stmt = $conn->prepare($sql_update);
                $stmt->bindParam(':bdate', $bdate);
                $stmt->bindParam(':bmonth', $bmonth);
                $stmt->bindParam(':developer', $developer);
                $stmt->bindParam(':bproject', $bproject);
                $stmt->bindParam(':cname', $cname);
                $stmt->bindParam(':cnumber', $cnumber);
                $stmt->bindParam(':cemail', $cemail);
                $stmt->bindParam(':tproject', $tproject);
                $stmt->bindParam(':canceleoi', $canceleoi, PDO::PARAM_INT);
                $stmt->bindParam(':cstatus', $cstatus);
                $stmt->bindParam(':eoi_id', $eoi_id, PDO::PARAM_INT);
                $stmt->execute();
                echo "EOI updated successfully.";
            } else {
                // INSERT new EOI record
                $sql_insert = "INSERT INTO usereoidata (
                                booking_date, booking_month, builder, project, customer_name, contact_number, email_id, 
                                project_type, canceleoi, source_table, astatus)
                               VALUES (:bdate, :bmonth, :developer, :bproject, :cname, :cnumber, :cemail, 
                                       :tproject, :canceleoi, :tablename, :cstatus)";
                $stmt = $conn->prepare($sql_insert);
                $stmt->bindParam(':bdate', $bdate);
                $stmt->bindParam(':bmonth', $bmonth);
                $stmt->bindParam(':developer', $developer);
                $stmt->bindParam(':bproject', $bproject);
                $stmt->bindParam(':cname', $cname);
                $stmt->bindParam(':cnumber', $cnumber);
                $stmt->bindParam(':cemail', $cemail);
                $stmt->bindParam(':tproject', $tproject);
                $stmt->bindParam(':canceleoi', $canceleoi, PDO::PARAM_INT);
                $stmt->bindParam(':tablename', $tablename);
                $stmt->bindParam(':cstatus', $cstatus);
                $stmt->execute();
                echo "EOI added to your Account successfully.";
            }
        }
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }

    $conn = null;
}
?>