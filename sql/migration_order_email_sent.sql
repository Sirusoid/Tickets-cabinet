-- Точечная миграция: не импортировать полный SQL-дамп в production.
ALTER TABLE payment_sessions
    ADD COLUMN IF NOT EXISTS order_email_sent_at DATETIME NULL AFTER customer_email;
