<?php
/**
 * One-time CLI utility for recovering the administrator account.
 * Remove this file from the server after a successful run.
 */
if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

require_once __DIR__ . '/init.php';

$username = $argv[1] ?? 'admin';
$password = $argv[2] ?? 'admin123';

if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username)) {
	fwrite(STDERR, "Ошибка: некорректное имя пользователя.\n");
	exit(1);
}

if ($password === '' || strlen($password) < 8) {
	fwrite(STDERR, "Ошибка: пароль должен содержать минимум 8 символов.\n");
	exit(1);
}

try {
	$pdo = db_connect();
	if (!$pdo instanceof PDO) {
		throw new RuntimeException('Не удалось подключиться к базе данных.');
	}

	$stmt = $pdo->prepare('SELECT id FROM users WHERE username = :username LIMIT 1');
	$stmt->execute([':username' => $username]);
	$userId = $stmt->fetchColumn();

	if (!$userId) {
		fwrite(STDERR, "Ошибка: пользователь '{$username}' не найден.\n");
		exit(1);
	}

	$update = $pdo->prepare('UPDATE users
		SET password_hash = :password_hash,
			failed_attempts = 0,
			locked_until = NULL,
			password_changed_at = NOW(),
			updated_at = NOW()
		WHERE id = :id');
	$update->execute([
		':password_hash' => password_hash($password, PASSWORD_DEFAULT),
		':id' => (int)$userId,
	]);

	echo "Пароль пользователя '{$username}' сброшен. Войдите и сразу замените временный пароль.\n";
} catch (Throwable $exception) {
	fwrite(STDERR, "Ошибка: {$exception->getMessage()}\n");
	exit(1);
}
