<?php
require_once __DIR__ . '/init.php';

if (!empty($_SESSION['user'])) {
    require_login();
    redirect('dashboard.php');
}

$error = null;
$recoveryMessage = null;
$recoveryError = null;
$username = '';
$passwordResetPdo = $pdo;
if (!$passwordResetPdo instanceof PDO) {
    $recoveryError = 'Сервис восстановления временно недоступен.';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['action'] ?? '') === 'recover_password') {
        $recoveryUsername = trim((string)($_POST['recovery_username'] ?? ''));
        if (!validate_csrf($_POST['csrf_token'] ?? '')) {
            $recoveryError = 'Сессия устарела. Обновите страницу и повторите попытку.';
        } elseif (!$passwordResetPdo instanceof PDO) {
            $recoveryError = 'Сервис восстановления временно недоступен.';
        } else {
            try {
                $recoveryResult = password_reset_request($passwordResetPdo, $recoveryUsername);
                if (!empty($recoveryResult['success'])) {
                    $recoveryMessage = $recoveryResult['message'];
                } else {
                    $recoveryError = $recoveryResult['message'] ?? 'Не удалось запустить восстановление пароля.';
                }
            } catch (Throwable $exception) {
                error_log('[PASSWORD RESET] Request failed: ' . $exception->getMessage());
                $recoveryError = 'Не удалось выполнить восстановление пароля. Попробуйте позже.';
            }
        }
    } else {
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
        <?php if ($recoveryMessage): ?><div class="alert alert-success"><?= h($recoveryMessage) ?></div><?php endif; ?>
        <?php if ($recoveryError): ?><div class="alert alert-danger"><?= h($recoveryError) ?></div><?php endif; ?>
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
        <button type="button" class="login-link login-link-button" id="showRecovery">Восстановить пароль</button>
        <form method="post" action="/login.php" id="recoveryForm" class="recovery-form" hidden>
            <input type="hidden" name="action" value="recover_password">
            <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token'] ?? '') ?>">
            <div class="form-group"><label for="recovery_username">Логин</label><input id="recovery_username" type="text" name="recovery_username" required></div>
            <button type="submit">Отправить ссылку</button>
        </form>
    </div>
    <script>
        (function () {
            var trigger = document.getElementById('showRecovery');
            var form = document.getElementById('recoveryForm');
            if (trigger && form) trigger.addEventListener('click', function () {
                form.hidden = false;
                trigger.hidden = true;
                var input = document.getElementById('recovery_username');
                if (input) input.focus();
            });
        })();
    </script>
    <script src="/assets/js/app_ui.js"></script>
</body>
</html>
