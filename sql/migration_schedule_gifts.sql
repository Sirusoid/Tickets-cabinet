-- Акция с одним платным подарком на сеанс.
-- Цена подарка хранится только целым числом в валютных единицах.

SET @schedules_promotion_enabled_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'schedules'
      AND COLUMN_NAME = 'promotion_enabled'
);
SET @schedules_promotion_enabled_sql := IF(
    @schedules_promotion_enabled_exists = 0,
    'ALTER TABLE schedules ADD COLUMN promotion_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER notes',
    'SELECT 1'
);
PREPARE schedules_promotion_enabled_stmt FROM @schedules_promotion_enabled_sql;
EXECUTE schedules_promotion_enabled_stmt;
DEALLOCATE PREPARE schedules_promotion_enabled_stmt;

SET @schedules_promotion_gift_name_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'schedules'
      AND COLUMN_NAME = 'promotion_gift_name'
);
SET @schedules_promotion_gift_name_sql := IF(
    @schedules_promotion_gift_name_exists = 0,
    'ALTER TABLE schedules ADD COLUMN promotion_gift_name VARCHAR(255) DEFAULT NULL AFTER promotion_enabled',
    'SELECT 1'
);
PREPARE schedules_promotion_gift_name_stmt FROM @schedules_promotion_gift_name_sql;
EXECUTE schedules_promotion_gift_name_stmt;
DEALLOCATE PREPARE schedules_promotion_gift_name_stmt;

SET @schedules_promotion_gift_price_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'schedules'
      AND COLUMN_NAME = 'promotion_gift_price'
);
SET @schedules_promotion_gift_price_sql := IF(
    @schedules_promotion_gift_price_exists = 0,
    'ALTER TABLE schedules ADD COLUMN promotion_gift_price INT UNSIGNED NOT NULL DEFAULT 0 AFTER promotion_gift_name',
    'SELECT 1'
);
PREPARE schedules_promotion_gift_price_stmt FROM @schedules_promotion_gift_price_sql;
EXECUTE schedules_promotion_gift_price_stmt;
DEALLOCATE PREPARE schedules_promotion_gift_price_stmt;

SET @tickets_gift_selected_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tickets'
      AND COLUMN_NAME = 'gift_selected'
);
SET @tickets_gift_selected_sql := IF(
    @tickets_gift_selected_exists = 0,
    'ALTER TABLE tickets ADD COLUMN gift_selected TINYINT(1) NOT NULL DEFAULT 0 AFTER discount_amount',
    'SELECT 1'
);
PREPARE tickets_gift_selected_stmt FROM @tickets_gift_selected_sql;
EXECUTE tickets_gift_selected_stmt;
DEALLOCATE PREPARE tickets_gift_selected_stmt;

SET @tickets_gift_name_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tickets'
      AND COLUMN_NAME = 'gift_name'
);
SET @tickets_gift_name_sql := IF(
    @tickets_gift_name_exists = 0,
    'ALTER TABLE tickets ADD COLUMN gift_name VARCHAR(255) DEFAULT NULL AFTER gift_selected',
    'SELECT 1'
);
PREPARE tickets_gift_name_stmt FROM @tickets_gift_name_sql;
EXECUTE tickets_gift_name_stmt;
DEALLOCATE PREPARE tickets_gift_name_stmt;

SET @tickets_gift_price_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tickets'
      AND COLUMN_NAME = 'gift_price'
);
SET @tickets_gift_price_sql := IF(
    @tickets_gift_price_exists = 0,
    'ALTER TABLE tickets ADD COLUMN gift_price INT UNSIGNED NOT NULL DEFAULT 0 AFTER gift_name',
    'SELECT 1'
);
PREPARE tickets_gift_price_stmt FROM @tickets_gift_price_sql;
EXECUTE tickets_gift_price_stmt;
DEALLOCATE PREPARE tickets_gift_price_stmt;
