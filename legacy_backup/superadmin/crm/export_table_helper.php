<?php
// Shared helper to ensure the export_jobs table exists
function ensureExportJobsTable(PDO $conn): void {
    $ddl = "CREATE TABLE IF NOT EXISTS export_jobs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id VARCHAR(255) NOT NULL,
        token VARCHAR(64) NOT NULL,
        params LONGTEXT NULL,
        status ENUM('pending','processing','done','failed') DEFAULT 'pending',
        file_path VARCHAR(500) DEFAULT NULL,
        file_name VARCHAR(255) DEFAULT NULL,
        error TEXT DEFAULT NULL,
        expires_at DATETIME DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_status (status),
        INDEX idx_expires (expires_at),
        UNIQUE KEY uniq_token (token)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    try {
        $conn->exec($ddl);
    } catch (Throwable $e) {
        // Surface a clear message so the caller can return JSON instead of HTML error pages.
        throw new RuntimeException('Failed to ensure export_jobs table: ' . $e->getMessage(), (int)$e->getCode(), $e);
    }
}
