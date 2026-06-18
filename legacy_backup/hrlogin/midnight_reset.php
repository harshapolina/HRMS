<?php
date_default_timezone_set('Asia/Kolkata');
require_once __DIR__ . '/config.php';

class MidnightReset extends Config {
    public function executeReset() {
        $conn = $this->getConnection();
        if (!$conn) {
            exit("Database connection failed.\n");
        }
        
        $sql = "UPDATE user_attendance SET 
                today_date = NULL,
                today_punch_in = NULL, 
                today_punch_out = NULL, 
                today_status = NULL, 
                today_lat_in = NULL, 
                today_lng_in = NULL, 
                today_lat_out = NULL, 
                today_lng_out = NULL, 
                today_ip = NULL,
                today_total_hours = NULL";
                
        try {
            $conn->exec($sql);
            echo "Successfully reset today cache columns in user_attendance.\n";
        } catch (Exception $e) {
            echo "Error resetting today cache columns: " . $e->getMessage() . "\n";
        }
    }
}

$reset = new MidnightReset();
$reset->executeReset();
