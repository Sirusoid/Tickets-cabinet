-- Первый этап очистки production.
-- Выполнять после резервной копии базы.
-- Полный SQL-дамп не импортировать.

START TRANSACTION;

-- Удаляем только истёкшие временные резервы.
DELETE FROM cash_holds
WHERE expires_at < NOW();

-- Удаляем только просроченную занятость мест без связанного билета.
DELETE so
FROM seat_occupancy so
LEFT JOIN tickets t ON t.id = so.ticket_id
WHERE so.ticket_id IS NULL
  AND so.reserved_until IS NOT NULL
  AND so.reserved_until < NOW();

COMMIT;

-- Проверка кандидата на удаление.
-- Таблица schedule_prices не используется текущим PHP-кодом и в дампе не содержит INSERT.
-- Выполняйте DROP только если результат COUNT(*) равен 0.
SELECT COUNT(*) AS schedule_prices_rows
FROM schedule_prices;

-- После проверки нулевого результата выполните отдельно:
-- DROP TABLE schedule_prices;
