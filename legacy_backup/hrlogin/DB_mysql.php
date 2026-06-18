<?php
/**
 * Shared mysqli bootstrap for legacy scripts.
 */
require_once dirname(__DIR__) . '/config.php';

function hr_mysqli_connect(): mysqli
{
    $config = new Config();
    $con = $config->getMysqliConnection();
    if (!$con) {
        throw new RuntimeException('Database connection failed. Check config.php (Hostinger hPanel → MySQL hostname).');
    }
    return $con;
}

function hr_ensure_user_attendance_table(mysqli $con): void
{
    $sql = "CREATE TABLE IF NOT EXISTS user_attendance (
        user_id INT NOT NULL,
        today_date DATE DEFAULT NULL,
        today_punch_in TIME DEFAULT NULL,
        today_punch_out TIME DEFAULT NULL,
        today_status VARCHAR(20) DEFAULT NULL,
        today_lat_in VARCHAR(50) DEFAULT NULL,
        today_lng_in VARCHAR(50) DEFAULT NULL,
        today_lat_out VARCHAR(50) DEFAULT NULL,
        today_lng_out VARCHAR(50) DEFAULT NULL,
        today_ip VARCHAR(50) DEFAULT NULL,
        today_total_hours DECIMAL(5,2) DEFAULT NULL,
        history_json JSON DEFAULT NULL,
        PRIMARY KEY (user_id),
        INDEX (today_status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $con->query($sql);

    // Self-healing migration to add today_date column if it doesn't exist
    $colCheck = $con->query("SHOW COLUMNS FROM user_attendance LIKE 'today_date'");
    if ($colCheck && $colCheck->num_rows === 0) {
        $con->query("ALTER TABLE user_attendance ADD COLUMN today_date DATE DEFAULT NULL AFTER user_id");
    }
}
