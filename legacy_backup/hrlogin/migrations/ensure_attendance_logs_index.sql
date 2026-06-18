-- Ensures punch idempotency and fast lookups by user + date.
-- Run on production if capacity_diagnostics.php reports the index is missing.

ALTER TABLE attendance_logs
    ADD UNIQUE KEY uniq_user_punch_date (user_id, punch_date);

-- Optional: speeds up dashboard/report queries by date
-- ALTER TABLE attendance_logs ADD INDEX idx_punch_date (punch_date);
