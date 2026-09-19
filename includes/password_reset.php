<?php

if (!function_exists('password_reset_ensure_table')) {
    function password_reset_ensure_table(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS password_reset_tokens (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            token_hash CHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_password_reset_token_hash (token_hash),
            KEY idx_password_reset_user (user_id),
            KEY idx_password_reset_expiry (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
}

if (!function_exists('password_reset_settings')) {
    function password_reset_settings(PDO $pdo): array
    {
        $settings = function_exists('order_email_settings')
            ? order_email_settings($pdo)
            : [
                'enabled' => true,
                'from' => getenv('ZHASSAHNA_EMAIL_FROM') ?: 'noreply@zhassahna.kz',
                'from_name' => defined('APP_NAME') ? APP_NAME : 'Театр «Жас сахна»',
                'reply_to' => '',
            ];
        return $settings;
    }
}

if (!function_exists('password_reset_request')) {
    function password_reset_request(PDO $pdo, string $username): array
    {
        $username = trim($username);
        if ($username === '') {
            return ['success' => false, 'message' => 'Укажите логин.'];
        }

        password_reset_ensure_table($pdo);
        $pdo->exec('DELETE FROM password_reset_tokens WHERE expires_at < NOW() OR used_at IS NOT NULL');

        $stmt = $pdo->prepare('SELECT id, username, full_name, email, is_active FROM users WHERE username = :username LIMIT 1');
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            return ['success' => false, 'message' => 'Пользователь с таким логином не найден.'];
        }
        if ((int)($user['is_active'] ?? 0) !== 1) {
            return ['success' => false, 'message' => 'Эта учетная запись отключена. Обратитесь к администратору.'];
        }

        $email = trim((string)($user['email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Для пользователя не указан корректный email. Обратитесь к администратору.'];
        }

        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $insert = $pdo->prepare('INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) VALUES (:user_id, :token_hash, DATE_ADD(NOW(), INTERVAL 60 MINUTE))');
        $insert->execute([
            ':user_id' => (int)$user['id'],
            ':token_hash' => $tokenHash,
        ]);

        $baseUrl = defined('BASE_URL') ? rtrim((string)BASE_URL, '/') : '';
        if ($baseUrl === '') {
            throw new RuntimeException('Не настроен BASE_URL для ссылки восстановления.');
        }
        $resetUrl = $baseUrl . '/reset_password.php?token=' . rawurlencode($token);
        $name = htmlspecialchars((string)($user['full_name'] ?: $user['username']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeUrl = htmlspecialchars($resetUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $html = '<!doctype html><html lang="ru"><body style="font-family:Arial,sans-serif;color:#1f2937;line-height:1.5">'
            . '<h2>Восстановление пароля</h2>'
            . '<p>Здравствуйте, ' . $name . '.</p>'
            . '<p>Для создания нового пароля перейдите по ссылке:</p>'
            . '<p><a href="' . $safeUrl . '">Восстановить пароль</a></p>'
            . '<p>Ссылка действительна 60 минут и может быть использована один раз.</p>'
            . '<p>Если вы не запрашивали восстановление, просто проигнорируйте это письмо.</p>'
            . '</body></html>';

        $settings = password_reset_settings($pdo);
        $sent = function_exists('order_email_send_message')
            && order_email_send_message($email, 'Восстановление пароля — ' . (defined('APP_NAME') ? APP_NAME : 'Жас сахна'), $html, $settings);
        if (!$sent) {
            $pdo->prepare('DELETE FROM password_reset_tokens WHERE token_hash = :token_hash')->execute([':token_hash' => $tokenHash]);
            return ['success' => false, 'message' => 'Не удалось отправить письмо. Обратитесь к администратору.'];
        }

        return ['success' => true, 'message' => 'Ссылка для восстановления отправлена на email пользователя.'];
    }
}

if (!function_exists('password_reset_find_token')) {
    function password_reset_find_token(PDO $pdo, string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) return null;
        password_reset_ensure_table($pdo);
        $stmt = $pdo->prepare('SELECT pr.id AS reset_id, pr.user_id, u.username, u.full_name
            FROM password_reset_tokens pr
            INNER JOIN users u ON u.id = pr.user_id
            WHERE pr.token_hash = :token_hash AND pr.used_at IS NULL AND pr.expires_at > NOW()
            LIMIT 1');
        $stmt->execute([':token_hash' => hash('sha256', $token)]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}