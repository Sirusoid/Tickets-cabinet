<?php
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/settings_manager.php';
require_login();

if (isset($pdo) && $pdo instanceof PDO) {
	settings_upsert_value(
		$pdo,
		'payments.bcc_backref_url',
		'BCC BACKREF публичный URL',
		defined('TILDA_WIDGET_ORIGIN') ? (string)TILDA_WIDGET_ORIGIN : '',
		'string',
		'payments',
		'Публичный домен для возврата клиента после оплаты. Путь задаётся отдельно; URL должен проксировать BACKREF на этот кабинет.',
		1,
		18
	);
	settings_upsert_value(
		$pdo,
		'payments.bcc_notify_url',
		'BCC NOTIFY серверный URL',
		defined('PUBLIC_BASE_URL') ? (string)PUBLIC_BASE_URL : '',
		'string',
		'payments',
		'Серверный домен для callback от BCC. Не указывайте здесь статический сайт Tilda.',
		1,
		20
	);
	settings_upsert_value(
		$pdo,
		'payments.bcc_self_refund_enabled',
		'Разрешить самостоятельный возврат BCC',
		'0',
		'bool',
		'payments',
		'Клиент сможет самостоятельно отменить оплаченный заказ через публичную страницу заказа.',
		1,
		22
	);
	settings_upsert_value(
		$pdo,
		'payments.bcc_self_refund_window_minutes',
		'Окно самостоятельного возврата (минуты)',
		'30',
		'int',
		'payments',
		'Рекомендуемое значение: 30 минут после покупки.',
		1,
		23
	);
	settings_upsert_value(
		$pdo,
		'payments.bcc_self_refund_min_hours_before_event',
		'Минимум до спектакля для возврата (часы)',
		'2',
		'int',
		'payments',
		'Возврат запрещается ближе указанного времени до начала спектакля.',
		1,
		24
	);
}

$settingsPageKey = 'payment';
require __DIR__ . '/_page.php';
