<!DOCTYPE html>
<html>
<head>
    <title>Setup Overdue Tracking Table</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .success {
            color: green;
            padding: 15px;
            background: #d4edda;
            border-radius: 5px;
            margin-top: 20px;
        }
        .error {
            color: red;
            padding: 15px;
            background: #f8d7da;
            border-radius: 5px;
            margin-top: 20px;
        }
        h1 {
            color: #333;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🗄️ Setup Overdue Tracking Table</h1>
        <?php
        /**
         * Setup script to create overdue_leads_tracking table
         * Run this once to create the table for tracking overdue lead popups
         */

        require_once 'config.php';
        $config = new Config();
        $conn = $config->getConnection();

        try {
            // Create table to track which overdue leads have been shown today
            $sql = "CREATE TABLE IF NOT EXISTS overdue_leads_tracking (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_unique_id VARCHAR(255) NOT NULL,
                lead_id INT NOT NULL,
                shown_date DATE NOT NULL,
                status ENUM('shown', 'updated', 'skipped') DEFAULT 'shown',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_user_lead_date (user_unique_id, lead_id, shown_date),
                INDEX idx_shown_date (shown_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $conn->exec($sql);
            
            echo '<div class="success">';
            echo '<strong>✅ Success!</strong><br>';
            echo 'Table <code>overdue_leads_tracking</code> created successfully!<br><br>';
            echo 'You can now use the overdue lead popup system.<br>';
            echo '<a href="user_lead.php">Go to Leads Page</a>';
            echo '</div>';
            
        } catch (PDOException $e) {
            echo '<div class="error">';
            echo '<strong>❌ Error!</strong><br>';
            echo 'Failed to create table: ' . htmlspecialchars($e->getMessage());
            echo '</div>';
        }
        ?>
    </div>
</body>
</html>
