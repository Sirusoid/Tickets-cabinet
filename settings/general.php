<?php
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/settings_manager.php';

if (isset($pdo) && $pdo instanceof PDO) {
	settings_upsert_value($pdo, 'system.maintenance_enabled', 'Режим обслуживания', '0', 'bool', 'system', 'Если включено, публичная афиша и форма покупки покажут вежливое сообщение о временной паузе.', 1, 10);
	settings_upsert_value($pdo, 'system.maintenance_title', 'Заголовок режима обслуживания', 'Мы скоро вернёмся', 'string', 'system', 'Короткий заголовок сообщения для страницы Tilda.', 1, 11);
	settings_upsert_value($pdo, 'system.maintenance_message', 'Сообщение режима обслуживания', 'Мы обновляем афишу и платёжную часть. Спасибо за терпение — скоро всё снова заработает.', 'text', 'system', 'Текст показывается посетителям вместо афиши. Пример: «Приносим извинения за паузу и скоро вернёмся».', 1, 12);
	settings_upsert_value(
		$pdo,
		'system.order_return_url',
		'Ссылка «Вернуться на сайт» после заказа',
		(defined('TILDA_WIDGET_ORIGIN') ? rtrim((string)TILDA_WIDGET_ORIGIN, '/') : 'https://zhassahna.kz') . '/?order={order}',
		'string',
		'system',
		'URL кнопки на странице успешного заказа. Используйте {order}, чтобы подставить номер заказа; без {order} будет открыта указанная фиксированная ссылка.',
		1,
		20
	);
}

$settingsPageKey = 'general';
require __DIR__ . '/_page.php';
