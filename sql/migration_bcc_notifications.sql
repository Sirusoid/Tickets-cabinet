-- BCC notification settings for the existing database.
-- Run once in phpMyAdmin. It is safe to run repeatedly.

INSERT INTO settings
(`key`, label, value, type, options, category, description, is_editable, sort_order)
VALUES
('notifications.bcc_notify_url', 'BCC NOTIFY URL', 'https://cabinet.zhassahna.kz', 'string', NULL, 'notifications', 'URL для уведомлений BCC. Должен быть доступен извне и использовать HTTPS.', 1, 10),
('notifications.bcc_notify_port', 'BCC NOTIFY порт', '443', 'int', NULL, 'notifications', 'BCC требует указывать порт в URL уведомлений. Обычно используется 443.', 1, 11),
('notifications.bcc_notify_method', 'BCC NOTIFY метод', 'POST', 'select', '["POST","GET"]', 'notifications', 'Метод отправки уведомления от BCC по документации.', 1, 12),
('notifications.bcc_basic_auth_enabled', 'BCC NOTIFY Basic Auth', '0', 'bool', NULL, 'notifications', 'Включить Basic Authentication для входящих уведомлений BCC.', 1, 13),
('notifications.bcc_notify_login', 'BCC NOTIFY логин', '', 'string', NULL, 'notifications', 'Логин Basic Auth, который нужно передать в BCC.', 1, 14),
('notifications.bcc_notify_password', 'BCC NOTIFY пароль', '', 'string', NULL, 'notifications', 'Пароль Basic Auth, который нужно передать в BCC.', 1, 15),
('notifications.bcc_tls12', 'BCC NOTIFY TLS 1.2', '1', 'bool', NULL, 'notifications', 'Подтверждение поддержки TLS 1.2 сервером уведомлений.', 1, 16),
('notifications.bcc_virtual_host', 'BCC NOTIFY виртуальный хост', '1', 'bool', NULL, 'notifications', 'Укажите, размещён ли endpoint уведомлений на виртуальном хосте.', 1, 17)
ON DUPLICATE KEY UPDATE
label = VALUES(label),
value = VALUES(value),
type = VALUES(type),
options = VALUES(options),
category = VALUES(category),
description = VALUES(description),
is_editable = VALUES(is_editable),
sort_order = VALUES(sort_order);
