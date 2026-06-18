<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
// Include Composer's autoloader for PhpSpreadsheet
require '../vendor/autoload.php';

// Include the Config class for database connection
require 'config.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

if (isset($_POST['submit'])) {
    if (isset($_FILES['file']['name']) && !empty($_FILES['file']['name'])) {
        $fileName = $_FILES['file']['tmp_name'];

        // Load the spreadsheet file
        $spreadsheet = IOFactory::load($fileName);
        $sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);

        // Create a new Config instance to connect to the database
        $config = new Config();
        $conn = $config->getConnection();

        // Insert query for new rows
        $insertQuery = "INSERT INTO shi_upload_data (name, email, number, location, created_at, project) 
                        VALUES (:name, :email, :number, :location, NOW(), :project)";
        $insertStmt = $conn->prepare($insertQuery);

        // Update query for rows where the project is empty
        $updateQuery = "UPDATE shi_upload_data SET project = :project WHERE name = :name AND email = :email AND number = :number AND (project IS NULL OR project = '')";
        $updateStmt = $conn->prepare($updateQuery);

        // Check if the combination of name, email, and number exists
        $checkQuery = "SELECT project FROM shi_upload_data WHERE name = :name AND email = :email AND number = :number";
        $checkStmt = $conn->prepare($checkQuery);

        $insertedRows = 0;
        $updatedRows = 0;
        $skippedRows = 0;

        foreach ($sheetData as $index => $row) {
            if ($index == 1) {
                continue; // Skip the first row (header)
            }

            $name = $row['A'];
            $email = $row['B'];
            $number = $row['C'];
            $location = $row['D'];
            $project = $row['E'];

            // Check if the row with the same name, email, and number already exists
            $checkStmt->bindValue(':name', $name);
            $checkStmt->bindValue(':email', $email);
            $checkStmt->bindValue(':number', $number);
            $checkStmt->execute();
            $existingRow = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($existingRow) {
                // If the record exists, check if the project is empty
                if (empty($existingRow['project'])) {
                    // If project is empty, update it
                    $updateStmt->bindValue(':project', $project);
                    $updateStmt->bindValue(':name', $name);
                    $updateStmt->bindValue(':email', $email);
                    $updateStmt->bindValue(':number', $number);
                    $updateStmt->execute();
                    $updatedRows++;
                } else {
                    // If project is already filled, skip this row
                    $skippedRows++;
                }
            } else {
                // If the record doesn't exist, insert a new row
                $insertStmt->bindValue(':name', $name);
                $insertStmt->bindValue(':email', $email);
                $insertStmt->bindValue(':number', $number);
                $insertStmt->bindValue(':location', $location);
                $insertStmt->bindValue(':project', $project);
                $insertStmt->execute();
                $insertedRows++;
            }
        }

        // Redirect to data with status message
        $statusMessage = "Data upload complete! $insertedRows rows inserted, $updatedRows rows updated, $skippedRows duplicate rows skipped.";
        header("Location: /superadmin/upload_data?status=" . urlencode($statusMessage));
        exit;
    } else {
        echo "Please upload a valid Excel file.";
    }
}
