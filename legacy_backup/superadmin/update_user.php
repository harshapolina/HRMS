<?php
header('Content-Type: application/json');
require_once 'config.php';

try {

    $pdo = new PDO(
        "mysql:host=localhost;dbname=u797909128_demo",
        "u797909128_demoproject",
        "QK&0/aF@5",
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $id = $_POST['userId'] ?? null;

    if (!$id) {
        echo json_encode(["success" => false, "message" => "Missing user ID"]);
        exit;
    }

    /* 🔥 FETCH OLD VALUES (CRITICAL) */
    $stmtOld = $pdo->prepare("SELECT salary, is_active, assign_user FROM accounts WHERE id = :id");
    $stmtOld->execute(['id' => $id]);
    $oldRow = $stmtOld->fetch(PDO::FETCH_ASSOC);

    if (!$oldRow) {
        echo json_encode(["success" => false, "message" => "User not found"]);
        exit;
    }

    $oldSalary   = $oldRow['salary'];
    $oldIsActive = $oldRow['is_active'];

    /* 🔥 UPDATE QUERY (MATCHES BOSS SYSTEM) */
    $sql = "UPDATE accounts SET

        doj = :doj,
        dob = :dob,
        username = :username,
        useremail = :email,
        phonenumber = :contact,
        epassword = :password,
        salary = :salary,
        tablename = :tablename,
        employee_id = :employee_id,
        user_type = :user_type,

        old_salary = :old_salary,

        one_amt = :one_amt,
        two_amt = :two_amt,
        thrid_amt = :thrid_amt,
        forth_amt = :forth_amt,
        fifth_amt = :fifth_amt,
        sixth_amt = :sixth_amt,

        project_name = :project_name,
        project_type = :project_type,

        assign_user = :assign_user,
        is_active = :is_active,

        flag_user_login = CURRENT_TIMESTAMP,

        deactivated_at = CASE
            WHEN :is_active = 0 AND :oldIsActive = 1 THEN NOW()
            WHEN :is_active = 1 THEN NULL
            ELSE deactivated_at
        END

    WHERE id = :id";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([

        'id' => $id,

        'doj' => $_POST['userDOJ'] ?? null,
        'dob' => $_POST['userDOB'] ?? null,
        'username' => $_POST['userName'] ?? '',
        'email' => $_POST['userEmail'] ?? '',
        'contact' => $_POST['userContact'] ?? '',
        'password' => $_POST['userPassword'] ?? '',
        'salary' => $_POST['userMonthlyCTC'] ?? 0,
        'tablename' => $_POST['userUniqueID'] ?? '',
        'employee_id' => $_POST['userEmployeeID'] ?? '',
        'user_type' => $_POST['userRoleType'] ?? '',

        'old_salary' => $oldSalary,

        'one_amt' => $_POST['user1stAmount'] ?? 0,
        'two_amt' => $_POST['user2ndAmount'] ?? 0,
        'thrid_amt' => $_POST['user3rdAmount'] ?? 0,
        'forth_amt' => $_POST['user4thAmount'] ?? 0,
        'fifth_amt' => $_POST['user5thAmount'] ?? 0,
        'sixth_amt' => $_POST['user6thAmount'] ?? 0,

        'project_name' => $_POST['userProjectName'] ?? '',
        'project_type' => $_POST['userProjectType'] ?? '',

        'assign_user' => $_POST['assign_user'] ?? '',
        'is_active' => $_POST['userStatus'] ?? 1,

        'oldIsActive' => $oldIsActive
    ]);

    echo json_encode(["success" => true]);

} catch (Exception $e) {

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
