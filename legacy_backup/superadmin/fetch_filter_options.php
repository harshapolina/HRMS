<?php
// fetch_filter_options.php - API endpoint to fetch filter options
session_start();
require_once 'config.php';

// Verify user is logged in
if (!isset($_SESSION['loggedin'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$config = new Config();
$conn = $config->getConnection();

$field = isset($_GET['field']) ? $_GET['field'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$results = [];

try {
    // Map field names to database columns
    $fieldMap = [
        'id' => 'id',
        'builder' => 'builder',
        'project' => 'project',
        'type' => 'project_type',
        'unit' => 'unit_no',
        'contact' => 'contact_number',
        'email' => 'email_id',
        'customer' => 'customer_name',
        'username' => 'source_table',
        'leadsource' => 'source_lead',
        'status' => 'astatus'
    ];
    
    if (!array_key_exists($field, $fieldMap)) {
        throw new Exception('Invalid field');
    }
    
    $column = $fieldMap[$field];
    
    // Build query to get distinct values
    $sql = "SELECT DISTINCT $column as value 
            FROM admintable 
            WHERE $column IS NOT NULL 
            AND $column != ''";
    
    // Add search filter if provided
    if ($search !== '') {
        $sql .= " AND $column LIKE :search";
    }
    
    $sql .= " ORDER BY $column ASC LIMIT 50";
    
    $stmt = $conn->prepare($sql);
    
    if ($search !== '') {
        $stmt->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
    }
    
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format for Select2
    foreach ($rows as $row) {
        $results[] = [
            'id' => $row['value'],
            'text' => $row['value']
        ];
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

header('Content-Type: application/json');
echo json_encode($results);
