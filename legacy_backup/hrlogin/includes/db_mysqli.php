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
}
