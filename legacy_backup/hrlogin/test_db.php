<?php
require_once 'db.php';
$db = new Database();
$conn = $db->getConnection();

echo "<h1>DB Diagnostic</h1>";

if (!$conn) {
    echo "Connection Failed!";
    exit;
}

$tables = ['accounts', 'payroll', 'user_attendance', 'attendance_logs'];

foreach ($tables as $t) {
    try {
        $q = $conn->query("SELECT COUNT(*) as count FROM $t");
        $res = $q->fetch();
        echo "Table <b>$t</b> has <b>" . $res['count'] . "</b> rows.<br>";
        
        if ($res['count'] > 0) {
            $q2 = $conn->query("SELECT * FROM $t LIMIT 1");
            $row = $q2->fetch();
            echo "First row sample:<pre>"; print_r($row); echo "</pre><hr>";
        }
    } catch (Exception $e) {
        echo "Error on table $t: " . $e->getMessage() . "<br><hr>";
    }
}
?>
