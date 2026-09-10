<?php
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/settings_manager.php';

if (isset($pdo) && $pdo instanceof PDO) {
	$securitySettings = [
		['security.session_idle_minutes', 'Таймаут неактивной сессии (минуты)', '480', 'int', 'Через сколько минут бездействия потребуется повторный вход. Пример: 480 = 8 часов.', 10],
		['security.max_login_attempts', 'Максимум неудачных входов', '5', 'int', 'После этого числа попыток вход временно блокируется.', 20],
		['security.login_lockout_minutes', 'Время блокировки входа (минуты)', '15', 'int', 'Пауза после превышения лимита попыток. Пример: 15 минут.', 30],
		['security.password_min_length', 'Минимальная длина пароля', '8', 'int', 'Минимальное число символов для новых паролей пользователей.', 40],
		['security.password_require_uppercase', 'Требовать заглавную букву в пароле', '0', 'bool', 'Например, пароль должен содержать A–Z.', 50],
		['security.password_require_lowercase', 'Требовать строчную букву в пароле', '0', 'bool', 'Например, пароль должен содержать a–z.', 60],
		['security.password_require_number', 'Требовать цифру в пароле', '1', 'bool', 'Например, пароль должен содержать 0–9.', 70],
		['security.password_require_special', 'Требовать специальный символ', '0', 'bool', 'Например: !, @, #, $, %. ', 80],
	];
	foreach ($securitySettings as [$key, $label, $value, $type, $description, $sortOrder]) {
		settings_upsert_value($pdo, $key, $label, $value, $type, 'security', $description, 1, $sortOrder);
	}
}

$settingsPageKey = 'security';
require __DIR__ . '/_page.php';