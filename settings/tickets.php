<?php
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/settings_manager.php';
require_login();

if (isset($pdo) && $pdo instanceof PDO) {
	$ticketSettings = [
		[
			'key' => 'tickets.max_tickets_per_user',
			'label' => 'Максимум билетов на пользователя',
			'value' => '6',
			'type' => 'int',
			'description' => 'Максимум оплаченных билетов на один сеанс для одного номера телефона.',
			'sort' => 3,
		],
		[
			'key' => 'tickets.client_reservation_enabled',
			'label' => 'Разрешить резервирование клиентом',
			'value' => '0',
			'type' => 'bool',
			'description' => 'Клиент сможет временно забронировать места в публичном виджете.',
			'sort' => 5,
		],
		[
			'key' => 'tickets.client_reservation_minutes',
			'label' => 'Время резерва клиентом (минуты)',
			'value' => '15',
			'type' => 'int',
			'description' => 'На сколько минут место блокируется в виджете. Рекомендуемое значение: 15 минут.',
			'sort' => 6,
		],
	];

	foreach ($ticketSettings as $setting) {
		$existing = db_fetch_one('SELECT id FROM settings WHERE `key` = ? LIMIT 1', [$setting['key']]);
		if (!$existing) {
			$stmt = $pdo->prepare('INSERT INTO settings (`key`, label, value, type, options, category, description, is_editable, sort_order) VALUES (:key, :label, :value, :type, NULL, :category, :description, 1, :sort_order)');
			$stmt->execute([
				':key' => $setting['key'],
				':label' => $setting['label'],
				':value' => $setting['value'],
				':type' => $setting['type'],
				':category' => 'tickets',
				':description' => $setting['description'],
				':sort_order' => $setting['sort'],
			]);
		}
	}
}

$settingsPageKey = 'tickets';
require __DIR__ . '/_page.php';