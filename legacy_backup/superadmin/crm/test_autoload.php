<?php
// test_autoload.php
$possible = [
    __DIR__ . '/../../vendor/autoload.php',
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/vendor/autoload.php'
];

$found = false;
foreach ($possible as $p) {
    if (file_exists($p)) { require $p; $found = true; break; }
}

header('Content-Type: text/plain');
echo "Checked paths:\n" . implode("\n", $possible) . "\n\n";
echo "autoload present: " . ($found ? "yes\n" : "no\n");
echo "PhpSpreadsheet available: " . (class_exists('\\PhpOffice\\PhpSpreadsheet\\Spreadsheet') ? "yes\n" : "no\n");
echo "memory_limit: " . ini_get('memory_limit') . "\n";
echo "max_execution_time: " . ini_get('max_execution_time') . "\n";
