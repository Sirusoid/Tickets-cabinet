-- Точечная миграция: не импортировать полный SQL-дамп в production.
ALTER TABLE tickets
    ADD COLUMN IF NOT EXISTS discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER discount;
