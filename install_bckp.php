<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

function install_database()
{
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    } catch (PDOException $e) {
        return [false, 'Ошибка подключения к базе: ' . $e->getMessage()];
    }

    $schema = file_get_contents(__DIR__ . '/sql/schema.sql');
    if ($schema === false) {
        return [false, 'Не удалось прочитать файл схемы.'];
    }

    $statements = array_filter(array_map('trim', explode(';', $schema)));
    try {
        foreach ($statements as $sql) {
            if ($sql === '') {
                continue;
            }
            $pdo->exec($sql);
        }

        $existingUsers = $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        if ($existingUsers == 0) {
            $password = password_hash('admin123', PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, full_name, role, email) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute(['admin', $password, 'Администратор', 'admin', 'admin@zhassahna.kz']);
        }
    } catch (PDOException $e) {
        return [false, 'Ошибка установки схемы: ' . $e->getMessage()];
    }

    return [true, 'Установка завершена успешно. Пароль администратора: admin123'];
}

$message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$success, $result] = install_database();
    $message = ($success ? 'OK: ' : 'Ошибка: ') . $result;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Установка проекта — <?= h(APP_NAME) ?></title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; padding: 40px; }
        .box { background: #fff; max-width: 720px; margin: 0 auto; padding: 24px; border-radius: 10px; box-shadow: 0 12px 30px rgba(0, 0, 0, 0.08); }
        button { padding: 12px 18px; background: #1a73e8; color: #fff; border: none; border-radius: 6px; cursor: pointer; }
        .message { margin: 20px 0; padding: 14px; border-radius: 6px; background: #eef; }
    </style>
</head>
<body>
    <div class="box">
        <h1>Установка базы данных</h1>
        <?php if ($message): ?>
            <div class="message"><?= h($message) ?></div>
        <?php endif; ?>
        <p>Этот скрипт автоматически создаст структуру таблиц для CRM продаж билетов.</p>
        <form method="post">
            <button type="submit">Запустить установку</button>
        </form>
        <p>После успешной установки закройте доступ к этому файлу.</p>
    </div>
</body>
</html>
