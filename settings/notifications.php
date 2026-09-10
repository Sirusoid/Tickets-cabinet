<?php
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/settings_manager.php';
require_login();

if (isset($pdo) && $pdo instanceof PDO) {
	$notificationSettings = [
		[
			'key' => 'notifications.bcc_notify_url',
			'label' => 'BCC NOTIFY URL',
			'value' => defined('PUBLIC_BASE_URL') ? (string)PUBLIC_BASE_URL : 'https://cabinet.zhassahna.kz',
			'type' => 'string',
			'description' => 'URL для уведомлений BCC. Должен быть доступен извне и использовать HTTPS.',
			'sort' => 10,
		],
		[
			'key' => 'notifications.bcc_notify_port',
			'label' => 'BCC NOTIFY порт',
			'value' => '443',
			'type' => 'int',
			'description' => 'BCC требует указывать порт в URL уведомлений. Обычно используется 443.',
			'sort' => 11,
		],
		[
			'key' => 'notifications.bcc_notify_method',
			'label' => 'BCC NOTIFY метод',
			'value' => 'POST',
			'type' => 'select',
			'description' => 'Метод отправки уведомления от BCC по документации.',
			'sort' => 12,
			'options' => '["POST","GET"]',
		],
		[
			'key' => 'notifications.bcc_basic_auth_enabled',
			'label' => 'BCC NOTIFY Basic Auth',
			'value' => '0',
			'type' => 'bool',
			'description' => 'Включить Basic Authentication для входящих уведомлений BCC.',
			'sort' => 13,
		],
		[
			'key' => 'notifications.bcc_notify_login',
			'label' => 'BCC NOTIFY логин',
			'value' => '',
			'type' => 'string',
			'description' => 'Логин Basic Auth, который нужно передать в BCC.',
			'sort' => 14,
		],
		[
			'key' => 'notifications.bcc_notify_password',
			'label' => 'BCC NOTIFY пароль',
			'value' => '',
			'type' => 'string',
			'description' => 'Пароль Basic Auth, который нужно передать в BCC.',
			'sort' => 15,
		],
		[
			'key' => 'notifications.bcc_tls12',
			'label' => 'BCC NOTIFY TLS 1.2',
			'value' => '1',
			'type' => 'bool',
			'description' => 'Подтверждение поддержки TLS 1.2 сервером уведомлений.',
			'sort' => 16,
		],
		[
			'key' => 'notifications.bcc_virtual_host',
			'label' => 'BCC NOTIFY виртуальный хост',
			'value' => '1',
			'type' => 'bool',
			'description' => 'Укажите, размещён ли endpoint уведомлений на виртуальном хосте.',
			'sort' => 17,
		],
	];

	foreach ($notificationSettings as $setting) {
		$existing = db_fetch_one('SELECT id FROM settings WHERE `key` = ? LIMIT 1', [$setting['key']]);
		if (!$existing) {
			$stmt = $pdo->prepare('INSERT INTO settings (`key`, label, value, type, options, category, description, is_editable, sort_order) VALUES (:key, :label, :value, :type, :options, :category, :description, 1, :sort_order)');
			$stmt->execute([
				':key' => $setting['key'],
				':label' => $setting['label'],
				':value' => $setting['value'],
				':type' => $setting['type'],
				':options' => $setting['options'] ?? null,
				':category' => 'notifications',
				':description' => $setting['description'],
				':sort_order' => $setting['sort'],
			]);
		}
	}
}

$settingsPageKey = 'notifications';
require __DIR__ . '/_page.php';