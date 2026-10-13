-- Описание промокода, которое покупатель видит после применения кода.
-- Можно выполнять повторно: колонка добавляется только если её ещё нет.
-- Страница «Промокоды» пытается добавить колонку сама; файл нужен, если у пользователя БД нет права ALTER.

SET @promo_description_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'promo_codes'
      AND COLUMN_NAME = 'description'
);

SET @promo_description_sql := IF(
    @promo_description_exists = 0,
    'ALTER TABLE promo_codes ADD COLUMN description VARCHAR(255) NULL DEFAULT NULL AFTER discount_value',
    'SELECT 1'
);

PREPARE promo_description_stmt FROM @promo_description_sql;
EXECUTE promo_description_stmt;
DEALLOCATE PREPARE promo_description_stmt;
