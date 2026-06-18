<?php
$conn = mysqli_connect(
    'localhost',
    'u797909128_demoproject',
    'QK&0/aF@5',
    'u797909128_demo'
);

if (!$conn) {
    die(json_encode([]));
}

/* 🔥🔥🔥 THIS IS THE MISSING LOGIC */
if (isset($_GET['id'])) {

    $id = (int) $_GET['id'];

    $query = "SELECT 
        id,
        username,
        useremail,
        phonenumber,
        epassword,
        salary,
        doj,
        dob,
        tablename AS uniqueid,

        one_amt AS first_amount,
        two_amt AS second_amount,
        thrid_amt AS third_amount,
        forth_amt AS fourth_amount,
        fifth_amt AS fifth_amount,
        sixth_amt AS sixth_amount,

        project_name,
        project_type,
        user_type,
        assign_user,
        created_at,
        deactivated_at AS inactive_at,
        is_active

    FROM accounts
    WHERE id = $id
    LIMIT 1";

    $result = mysqli_query($conn, $query);

    if ($row = mysqli_fetch_assoc($result)) {
        echo json_encode($row);
    } else {
        echo json_encode(["error" => "User not found"]);
    }

    exit;
}

/* ✅ EXISTING ALL USERS QUERY */
$query = "SELECT 
    id,
    username,
    useremail,
    phonenumber,
    epassword,
    salary,
    doj,
    dob,
    tablename AS uniqueid,

    one_amt AS first_amount,
    two_amt AS second_amount,
    thrid_amt AS third_amount,
    forth_amt AS fourth_amount,
    fifth_amt AS fifth_amount,
    sixth_amt AS sixth_amount,

    project_name,
    project_type,
    user_type,
    assign_user,
    created_at,
    deactivated_at AS inactive_at,
    is_active

FROM accounts";

$result = mysqli_query($conn, $query);

$data = [];

while ($row = mysqli_fetch_assoc($result)) {
    $data[] = $row;
}

echo json_encode($data);
