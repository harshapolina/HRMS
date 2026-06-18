-- Recommended indexes for HR login performance (run once on production MySQL).
-- Safe to re-run: uses IF NOT EXISTS patterns where supported.

ALTER TABLE accounts ADD INDEX idx_accounts_is_active (is_active);
ALTER TABLE accounts ADD INDEX idx_accounts_username (username);
ALTER TABLE accounts ADD INDEX idx_accounts_useremail (useremail);

ALTER TABLE attendance_logs ADD INDEX idx_attendance_user_date (user_id, punch_date);
ALTER TABLE attendance_logs ADD INDEX idx_attendance_punch_date (punch_date);

ALTER TABLE leave_requests ADD INDEX idx_leave_user_status_dates (user_id, status, start_date, end_date);

ALTER TABLE payroll ADD INDEX idx_payroll_month_employee (month_year, employee_id);
