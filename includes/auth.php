<?php
function current_user()
{
    return $_SESSION['user'] ?? null;
}

function require_login()
{
    $user = $_SESSION['user'] ?? null;
    $now = time();
    $idleTimeout = 8 * 60 * 60;

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

    return true;
}

function logout_user()
{
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
