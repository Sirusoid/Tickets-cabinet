<?php
function current_user()
{
    return $_SESSION['user'] ?? null;
}

function security_setting_int(string $key, int $default): int
{
    global $pdo;
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        return $default;
    }
    return max(1, (int)app_setting_value($pdo, $key, $default));
}

function password_policy_errors(string $password): array
{
    global $pdo;
    $minLength = security_setting_int('security.password_min_length', 8);
    $errors = [];
    if (strlen($password) < $minLength) {
        $errors[] = 'Пароль должен содержать минимум ' . $minLength . ' символов.';
    }
    if (isset($pdo) && $pdo instanceof PDO) {
        if (app_setting_enabled($pdo, 'security.password_require_uppercase') && !preg_match('/[A-ZА-ЯЁ]/u', $password)) $errors[] = 'Пароль должен содержать заглавную букву.';
        if (app_setting_enabled($pdo, 'security.password_require_lowercase') && !preg_match('/[a-zа-яё]/u', $password)) $errors[] = 'Пароль должен содержать строчную букву.';
        if (app_setting_enabled($pdo, 'security.password_require_number') && !preg_match('/\d/', $password)) $errors[] = 'Пароль должен содержать цифру.';
        if (app_setting_enabled($pdo, 'security.password_require_special') && !preg_match('/[^\p{L}\p{N}]/u', $password)) $errors[] = 'Пароль должен содержать специальный символ.';
    }
    return $errors;
}

function require_login()
{
    $user = $_SESSION['user'] ?? null;
    $now = time();
    $idleTimeout = security_setting_int('security.session_idle_minutes', 480) * 60;

    if (!is_array($user) || empty($user['id'])) {
        redirect('/login.php');
    }

    if (!empty($_SESSION['last_activity']) && ($now - (int)$_SESSION['last_activity']) > $idleTimeout) {
        logout_user();
        redirect('/login.php?expired=1');
    }
    $_SESSION['last_activity'] = $now;

    global $pdo;
    if (isset($pdo) && $pdo instanceof PDO) {
        $freshUser = db_fetch_one('SELECT id, username, full_name, role, is_active FROM users WHERE id = ? LIMIT 1', [(int)$user['id']]);
        if (!$freshUser || (isset($freshUser['is_active']) && (int)$freshUser['is_active'] !== 1)) {
            logout_user();
            redirect('/login.php?expired=1');
        }
        $_SESSION['user']['username'] = $freshUser['username'];
        $_SESSION['user']['full_name'] = $freshUser['full_name'];
        $_SESSION['user']['role'] = $freshUser['role'];
    }
}

function login_user($username, $password)
{
    $user = db_fetch_one('SELECT * FROM users WHERE username = ? LIMIT 1', [$username]);

    if (!$user || (isset($user['is_active']) && (int)$user['is_active'] !== 1)) {
        return false;
    }

    if (!password_verify($password, $user['password_hash'])) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id' => $user['id'],
        'username' => $user['username'],
        'full_name' => $user['full_name'],
        'role' => $user['role'],
    ];
    $_SESSION['last_activity'] = time();
    unset($_SESSION['login_attempts'], $_SESSION['login_locked_until']);

    global $pdo;
    if (isset($pdo) && $pdo instanceof PDO && function_exists('audit_log_event')) {
        audit_log_event($pdo, 'auth.login', 'user', (int)$user['id'], (string)$user['username']);
    }

    return true;
}

function logout_user()
{
    global $pdo;
    $user = $_SESSION['user'] ?? [];
    if (isset($pdo) && $pdo instanceof PDO && !empty($user['id']) && function_exists('audit_log_event')) {
        audit_log_event($pdo, 'auth.logout', 'user', (int)$user['id'], (string)($user['username'] ?? ''));
    }
    $params = session_get_cookie_params();
    $_SESSION = [];
    if (session_id() !== '') {
        session_destroy();
    }
    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $params['path'] ?? '/',
        'domain' => $params['domain'] ?? '',
        'secure' => (bool)($params['secure'] ?? false),
        'httponly' => (bool)($params['httponly'] ?? true),
        'samesite' => $params['samesite'] ?? 'Lax',
    ]);
}

function is_admin()
{
    return !empty($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin';
}
