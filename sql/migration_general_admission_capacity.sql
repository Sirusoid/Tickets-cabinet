-- Run once to enable capacity-based (general admission) sessions in halls without a seat map.
SET @capacity_column_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'schedules'
      AND COLUMN_NAME = 'capacity'
);
SET @capacity_ddl = IF(
    @capacity_column_exists = 0,
    'ALTER TABLE schedules ADD COLUMN capacity INT UNSIGNED DEFAULT NULL AFTER hall_id',
    'SELECT 1'
);
PREPARE capacity_statement FROM @capacity_ddl;
EXECUTE capacity_statement;
DEALLOCATE PREPARE capacity_statement;
