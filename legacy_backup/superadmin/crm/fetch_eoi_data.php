<?php
session_start();
require_once '../config.php';


try {
    // Use Config class to establish a database connection
    $config = new Config();
    $conn = $config->getConnection();

    // Prepare the query using placeholders
    $sql = "SELECT * FROM usereoidata";
    $stmt = $conn->prepare($sql);
    $stmt->execute();

    // Fetch and display data
    if ($stmt->rowCount() > 0) {
        while ($row = $stmt->fetch()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['id']) . "</td>";
            echo "<td>" . htmlspecialchars($row['source_table']) . "</td>";
            echo "<td>" . htmlspecialchars($row['booking_date']) . "</td>";
            echo "<td>" . htmlspecialchars($row['booking_month']) . "</td>";
            echo "<td>" . htmlspecialchars($row['builder']) . "</td>";
            echo "<td>" . htmlspecialchars($row['project']) . "</td>";
            echo "<td>" . htmlspecialchars($row['customer_name']) . "</td>";
            echo "<td>" . htmlspecialchars($row['contact_number']) . "</td>";
            echo "<td>" . htmlspecialchars($row['email_id']) . "</td>";
            echo "<td>" . htmlspecialchars($row['project_type']) . "</td>";
            echo "</tr>";
        }
    } else {
        echo "<tr><td colspan='9'>No records found</td></tr>";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
