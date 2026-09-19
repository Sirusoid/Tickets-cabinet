<?php
require_once __DIR__ . '/init.php';

if (!empty($_SESSION['user'])) {
    require_login();
    redirect('/dashboard.php');
}

$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$error = null;
$success = null;
$tokenData = null;
$passwordResetPdo = $pdo;
if (!$passwordResetPdo instanceof PDO) {
    $error = 'Сервис восстановления временно недоступен.';
}

try {
    if ($passwordResetPdo instanceof PDO) {
        $tokenData = password_reset_find_token($passwordResetPdo, $token);
    }
} catch (Throwable $exception) {
    error_log('[PASSWORD RESET] Token lookup failed: ' . $exception->getMessage());
    $error = 'Сервис восстановления временно недоступен.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === null) {
    $newPassword = (string)($_POST['password'] ?? '');
    $confirmPassword = (string)($_POST['password_confirm'] ?? '');
    $policyErrors = password_policy_errors($newPassword);
    if (!$tokenData) {
        $error = 'Ссылка недействительна или срок её действия истёк.';
    } elseif (!validate_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Сессия устарела. Обновите страницу и повторите попытку.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Пароли не совпадают.';
    } elseif ($policyErrors) {
        $error = implode(' ', $policyErrors);
    } else {
        try {
            $db = $passwordResetPdo;
            if (!$db instanceof PDO) {
                throw new RuntimeException('Сервис восстановления временно недоступен.');
            }
            $db->beginTransaction();
            $lock = $db->prepare('SELECT id, user_id FROM password_reset_tokens WHERE id = :id AND used_at IS NULL AND expires_at > NOW() FOR UPDATE');
            $lock->execute([':id' => (int)$tokenData['reset_id']]);
            $lockedToken = $lock->fetch(PDO::FETCH_ASSOC);
            if (!$lockedToken) {
                throw new RuntimeException('Ссылка недействительна или уже использована.');
            }

            $update = $db->prepare('UPDATE users SET password_hash = :password_hash, password_changed_at = NOW(), failed_attempts = 0, locked_until = NULL, updated_at = NOW() WHERE id = :id');
            $update->execute([
                ':password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
                ':id' => (int)$lockedToken['user_id'],
            ]);
            $used = $db->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE id = :id');
            $used->execute([':id' => (int)$tokenData['reset_id']]);
            $db->prepare('DELETE FROM password_reset_tokens WHERE user_id = :user_id AND id <> :id')->execute([
                ':user_id' => (int)$lockedToken['user_id'],
                ':id' => (int)$tokenData['reset_id'],
            ]);
            if (function_exists('audit_log_event')) {
                audit_log_event($db, 'auth.password_reset', 'user', (int)$lockedToken['user_id'], (string)$tokenData['username']);
            }
            $db->commit();
            $success = 'Пароль изменён. Теперь можно войти в админку.';
            $tokenData = null;
        } catch (Throwable $exception) {
            if ($passwordResetPdo instanceof PDO && $passwordResetPdo->inTransaction()) $passwordResetPdo->rollBack();
            $error = $exception->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Новый пароль — <?= h(APP_NAME) ?></title>
    <link rel="stylesheet" href="/assets/css/login.css">
</head>
<body class="login-page">
    <div class="login-box">
        <img class="site-logo" src="/uploads/images/logo.png" alt="Логотип">
        <h1>Новый пароль</h1>
        <?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= h($success) ?></div><a class="login-link" href="/login.php">Вернуться ко входу</a><?php endif; ?>
        <?php if (!$success && $tokenData): ?>
            <form method="post" action="/reset_password.php">
                <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token'] ?? '') ?>">
                <input type="hidden" name="token" value="<?= h($token) ?>">
                <div class="form-group"><label for="password">Новый пароль</label><input id="password" type="password" name="password" minlength="8" required></div>
                <div class="form-group"><label for="password_confirm">Повторите пароль</label><input id="password_confirm" type="password" name="password_confirm" minlength="8" required></div>
                <button type="submit">Сохранить пароль</button>
            </form>
        <?php elseif (!$success && !$error): ?>
            <div class="alert alert-danger">Ссылка недействительна или срок её действия истёк.</div>
        <?php endif; ?>
        <?php if (!$success): ?><a class="login-link" href="/login.php">Вернуться ко входу</a><?php endif; ?>
    </div>
    <script src="/assets/js/app_ui.js"></script>
</body>
</html>