CREATE TABLE IF NOT EXISTS error_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    level VARCHAR(20) NOT NULL DEFAULT 'error',
    source VARCHAR(100) NOT NULL,
    message VARCHAR(1000) NOT NULL,
    context LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL,
    user_id INT UNSIGNED NULL,
    order_number VARCHAR(64) NULL,
    ticket_uid VARCHAR(100) NULL,
    response_code VARCHAR(32) NULL,
    ip_address VARCHAR(50) NULL,
    user_agent VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_error_logs_created_at (created_at),
    KEY idx_error_logs_source (source),
    KEY idx_error_logs_level (level),
    KEY idx_error_logs_order_number (order_number),
    KEY idx_error_logs_response_code (response_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

