-- Выполнить один раз на рабочей базе до создания промокодов и онлайн-продаж со скидкой.

CREATE TABLE IF NOT EXISTS promo_codes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code VARCHAR(12) NOT NULL,
    discount_type ENUM('percent', 'fixed') NOT NULL,
    discount_value DECIMAL(10,2) NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_promo_codes_code (code),
    KEY idx_promo_codes_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE tickets
    ADD COLUMN promo_code VARCHAR(12) DEFAULT NULL AFTER discount_amount,
    ADD KEY idx_tickets_promo_code (promo_code);
