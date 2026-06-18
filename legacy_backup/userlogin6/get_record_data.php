<?php
require_once 'config.php';
$config = new Config();
$conn = $config->getConnection();

$id = $_GET['id'];

// Use PDO methods to prepare and execute the SQL query
$sql = "SELECT * FROM usereoidata WHERE id = :id";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':id', $id, PDO::PARAM_INT);
$stmt->execute();
$data = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode($data);

?>