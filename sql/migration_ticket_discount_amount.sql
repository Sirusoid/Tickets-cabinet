-- Точечная миграция: не импортировать полный SQL-дамп в production.
ALTER TABLE tickets
    ADD COLUMN IF NOT EXISTS original_price DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER price,
    ADD COLUMN IF NOT EXISTS final_price DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER original_price,
    ADD COLUMN IF NOT EXISTS discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER discount;
