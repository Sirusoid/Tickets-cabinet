<?php
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/settings_manager.php';

if (isset($pdo) && $pdo instanceof PDO) {
	settings_upsert_value($pdo, 'system.maintenance_enabled', 'Режим обслуживания', '0', 'bool', 'system', 'Если включено, публичная афиша и форма покупки покажут вежливое сообщение о временной паузе.', 1, 10);
	settings_upsert_value($pdo, 'system.maintenance_title', 'Заголовок режима обслуживания', 'Мы скоро вернёмся', 'string', 'system', 'Короткий заголовок сообщения для страницы Tilda.', 1, 11);
	settings_upsert_value($pdo, 'system.maintenance_message', 'Сообщение режима обслуживания', 'Мы обновляем афишу и платёжную часть. Спасибо за терпение — скоро всё снова заработает.', 'text', 'system', 'Текст показывается посетителям вместо афиши. Пример: «Приносим извинения за паузу и скоро вернёмся».', 1, 12);
}

$settingsPageKey = 'general';
require __DIR__ . '/_page.php';
