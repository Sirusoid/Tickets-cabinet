<?php
require_once __DIR__ . '/init.php';

if (!empty($_SESSION['user'])) {
    require_login();
    redirect('/dashboard.php');
}

$error = null;
$success = null;
$username = '';
$passwordResetPdo = $pdo;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Сессия устарела. Обновите страницу и повторите попытку.';
    } elseif (!$passwordResetPdo instanceof PDO) {
        $error = 'Сервис восстановления временно недоступен.';
    } else {
        try {
            $result = password_reset_request($passwordResetPdo, $username);
            if (!empty($result['success'])) {
                $success = $result['message'];
            } else {
                $error = $result['message'] ?? 'Не удалось отправить ссылку для восстановления.';
            }
        } catch (Throwable $exception) {
            error_log('[PASSWORD RESET] Request failed: ' . $exception->getMessage());
            $error = 'Не удалось выполнить восстановление пароля. Попробуйте позже.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Восстановление пароля — <?= h(APP_NAME) ?></title>
    <link rel="stylesheet" href="/assets/css/login.css">
</head>
<body class="login-page">
    <div class="login-box">
        <img class="site-logo" src="/uploads/images/logo.png" alt="Логотип">
        <h1>Восстановление пароля</h1>
        <?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>
        <?php if (!$success): ?>
            <form method="post" action="/forgot_password.php">
                <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token'] ?? '') ?>">
                <div class="form-group">
                    <label for="username">Логин</label>
                    <input id="username" type="text" name="username" value="<?= h($username) ?>" autocomplete="username" required>
                </div>
                <button type="submit">Отправить ссылку</button>
            </form>
        <?php endif; ?>
        <a class="login-link" href="/login.php">Вернуться ко входу</a>
    </div>
    <script src="/assets/js/app_ui.js"></script>
</body>
</html>