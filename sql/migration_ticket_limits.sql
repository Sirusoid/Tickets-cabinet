-- Ticket limit, client reservation and auto-cancel settings.
-- Run once in phpMyAdmin for an existing database.

INSERT INTO settings
(`key`, label, value, type, options, category, description, is_editable, sort_order)
VALUES
('tickets.client_reservation_enabled', 'Разрешить резервирование клиентом', '0', 'bool', NULL, 'tickets', 'Клиент сможет временно забронировать места в публичном виджете.', 1, 5),
('tickets.client_reservation_minutes', 'Время резерва клиентом (минуты)', '15', 'int', NULL, 'tickets', 'На сколько минут место блокируется в виджете. Рекомендуемое значение: 15 минут.', 1, 6)
ON DUPLICATE KEY UPDATE
label = VALUES(label),
value = VALUES(value),
type = VALUES(type),
category = VALUES(category),
description = VALUES(description),
is_editable = VALUES(is_editable),
sort_order = VALUES(sort_order);

-- Existing settings already contain:
-- tickets.max_tickets_per_user
-- tickets.auto_cancel_hours
