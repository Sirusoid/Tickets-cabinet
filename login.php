<?php
require_once __DIR__ . '/init.php';

if (!empty($_SESSION['user'])) {
    require_login();
    redirect('dashboard.php');
}

$error = null;
$username = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $now = time();
    $lockedUntil = (int)($_SESSION['login_locked_until'] ?? 0);
    $maxAttempts = security_setting_int('security.max_login_attempts', 5);
    $lockoutMinutes = security_setting_int('security.login_lockout_minutes', 15);

    if ($lockedUntil > $now) {
        $error = 'Слишком много попыток. Повторите вход позже.';
    } elseif (!validate_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Сессия входа устарела. Обновите страницу.';
    } elseif (login_user($username, $password)) {
        redirect('dashboard.php');
    } else {
        $_SESSION['login_attempts'] = (int)($_SESSION['login_attempts'] ?? 0) + 1;
        if ($_SESSION['login_attempts'] >= $maxAttempts) {
            $_SESSION['login_locked_until'] = $now + ($lockoutMinutes * 60);
        }
        $error = 'Неверный логин или пароль.';
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Вход — <?= h(APP_NAME) ?></title>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js" integrity="sha256-/xUj+3OJ+Y5l7U68/7vQd5fjk5nHV5+5sXygfOtC/tA=" crossorigin="anonymous"></script>
	<link rel="stylesheet" href="/assets/css/login.css">
    <script>
        if (typeof window.jQuery === 'undefined') {
            var s = document.createElement('script');
            s.src = '/assets/js/jquery.min.js';
            s.defer = true;
            document.head.appendChild(s);
        }
    </script>
</head>
<body class="login-page">
    <div class="login-box">
		<img class="site-logo" src="/uploads/images/logo.png" alt="Логотип">
        <h1>Вход в админку</h1>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= h($error) ?></div>
        <?php endif; ?>
        <form method="post" action="/login.php">
            <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token'] ?? '') ?>">
            <div class="form-group">
                <label>Имя пользователя</label>
                <input type="text" name="username" value="<?= h($username) ?>" required>
            </div>
            <div class="form-group">
                <label>Пароль</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit">Войти</button>
        </form>
    </div>
</body>
</html>
