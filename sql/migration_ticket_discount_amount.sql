-- Каноническая схема цены билета.
-- Выполнять после резервной копии; полный SQL-дамп в production не импортировать.
ALTER TABLE tickets
    ADD COLUMN IF NOT EXISTS original_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    ADD COLUMN IF NOT EXISTS final_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    ADD COLUMN IF NOT EXISTS discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00;

ALTER TABLE tickets
    MODIFY customer_segment ENUM('adult','child','senior','student','manual') NOT NULL DEFAULT 'adult';

-- Перенос старой итоговой цены в канонические поля.
UPDATE tickets
SET final_price = price
WHERE final_price = 0;

UPDATE tickets
SET original_price = final_price + discount_amount
WHERE discount_amount > 0;

UPDATE tickets
SET
    discount_amount = CASE
        WHEN discount_amount > 0 THEN discount_amount
        WHEN discount > 0 AND discount < 100 THEN
            final_price * discount / (100 - discount)
        ELSE 0
    END,
    original_price = CASE
        WHEN original_price > 0 AND original_price <> final_price THEN original_price
        WHEN discount > 0 AND discount < 100 THEN final_price / (1 - discount / 100)
        ELSE final_price
    END;

UPDATE tickets
SET discount_amount = GREATEST(0, original_price - final_price)
WHERE discount_amount = 0;

ALTER TABLE tickets
    DROP COLUMN price;
