<?php
// add_or_update_admin.php
require_once __DIR__ . '/init.php';

try {
    $pdo = db_connect();
    if (!$pdo || !($pdo instanceof PDO)) {
        die("Ошибка: db_connect() не вернул объект PDO");
    }

    $username      = "admin";
    $plainPassword = "admin123";
    $passwordHash  = password_hash($plainPassword, PASSWORD_DEFAULT);

    $fullName = "Администратор";
    $role     = "admin";
    $email    = "admin@example.com";

    // Проверяем, есть ли пользователь admin
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // Обновляем пароль и данные
        $stmt = $pdo->prepare("
            UPDATE users 
            SET password_hash = ?, full_name = ?, role = ?, email = ? 
            WHERE username = ?
        ");
        $stmt->execute([$passwordHash, $fullName, $role, $email, $username]);
        echo "Пароль пользователя '{$username}' обновлён! Новый пароль: {$plainPassword}";
    } else {
        // Добавляем нового пользователя
        $stmt = $pdo->prepare("
            INSERT INTO users (username, password_hash, full_name, role, email, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$username, $passwordHash, $fullName, $role, $email]);
        echo "Пользователь '{$username}' добавлен! Пароль: {$plainPassword}";
    }
} catch (Throwable $e) {
    echo "Ошибка: " . $e->getMessage();
}
