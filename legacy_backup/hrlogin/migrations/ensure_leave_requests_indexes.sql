-- Speeds up leave management filters and date-range queries.
-- Run on production if leave list loads slowly with many requests.

ALTER TABLE leave_requests
    ADD INDEX idx_leave_status (status),
    ADD INDEX idx_leave_dates (start_date, end_date),
    ADD INDEX idx_leave_user_created (user_id, created_at);

ALTER TABLE leave_balances
    ADD INDEX idx_leave_balance_user_type_year (user_id, leave_type_id, year);
