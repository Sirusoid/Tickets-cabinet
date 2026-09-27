-- Массовые уведомления клиентов об изменении сеанса.
-- Выполнить один раз на рабочей базе до использования UI уведомлений.

CREATE TABLE IF NOT EXISTS schedule_notifications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    schedule_id INT UNSIGNED NOT NULL,
    change_key CHAR(64) NOT NULL,
    notification_type VARCHAR(20) NOT NULL,
    old_start_time DATETIME NULL,
    old_end_time DATETIME NULL,
    new_start_time DATETIME NULL,
    new_end_time DATETIME NULL,
    reason TEXT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'sending',
    total_tickets INT UNSIGNED NOT NULL DEFAULT 0,
    total_recipients INT UNSIGNED NOT NULL DEFAULT 0,
    sent_recipients INT UNSIGNED NOT NULL DEFAULT 0,
    failed_recipients INT UNSIGNED NOT NULL DEFAULT 0,
    no_email_tickets INT UNSIGNED NOT NULL DEFAULT 0,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sent_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_schedule_notifications_change_key (change_key),
    KEY idx_schedule_notifications_schedule (schedule_id),
    KEY idx_schedule_notifications_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS schedule_notification_recipients (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    notification_id BIGINT UNSIGNED NOT NULL,
    email VARCHAR(255) NOT NULL,
    customer_name VARCHAR(255) NULL,
    order_numbers TEXT NULL,
    ticket_uids TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'queued',
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    sent_at DATETIME NULL,
    last_error TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_schedule_notification_recipient (notification_id, email),
    KEY idx_schedule_notification_recipient_status (notification_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
