<?php
require_once __DIR__ . '/../init.php';
require_login();

if (!is_admin()) {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Доступ запрещён.'], JSON_UNESCAPED_UNICODE);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

function users_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function users_require_csrf(): void
{
    $token = (string)($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if ($token === '' || empty($_SESSION['csrf_token']) || !hash_equals((string)$_SESSION['csrf_token'], $token)) {
        users_response(['success' => false, 'message' => 'Неверный CSRF токен.'], 403);
    }
}

$action = trim((string)($_REQUEST['action'] ?? ''));
if ($action === '') {
    users_response(['success' => false, 'message' => 'Отсутствует действие.'], 400);
}

$roles = [
    'admin' => 'Администратор',
    'manager' => 'Менеджер',
    'cashier' => 'Кассир',
    'scanner' => 'Сканер',
];

try {
    if ($action === 'list') {
        $search = trim((string)($_GET['search'] ?? ''));
        $role = trim((string)($_GET['role'] ?? ''));
        $active = trim((string)($_GET['active'] ?? ''));
        $page = max(1, (int)($_GET['page'] ?? 1));
        $requestedPerPage = (int)($_GET['per_page'] ?? 25);
        $perPage = in_array($requestedPerPage, [25, 50, 100, 500], true) ? $requestedPerPage : 25;

        $where = [];
        $params = [];
        if ($search !== '') {
            $where[] = '(username LIKE :search_username OR full_name LIKE :search_name OR email LIKE :search_email)';
            $params[':search_username'] = '%' . $search . '%';
            $params[':search_name'] = '%' . $search . '%';
            $params[':search_email'] = '%' . $search . '%';
        }
        if (isset($roles[$role])) {
            $where[] = 'role = :role';
            $params[':role'] = $role;
        }
        if ($active === '1' || $active === '0') {
            $where[] = 'is_active = :is_active';
            $params[':is_active'] = (int)$active;
        }
        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $totalRow = db_fetch_one('SELECT COUNT(*) AS total FROM users' . $whereSql, $params);
        $total = (int)($totalRow['total'] ?? 0);
        $totalPages = max(1, (int)ceil($total / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;
        $users = db_fetch_all(
            'SELECT id, username, full_name, email, role, is_active, last_login_at, created_at
             FROM users' . $whereSql . '
             ORDER BY id ASC
             LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset,
            $params
        );
        users_response([
            'success' => true,
            'data' => $users,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
        ]);
    }

    if ($action === 'get') {
        $userId = (int)($_GET['id'] ?? 0);
        if ($userId <= 0) {
            users_response(['success' => false, 'message' => 'Неверный ID пользователя.'], 400);
        }
        $user = db_fetch_one('SELECT id, username, full_name, email, role, is_active FROM users WHERE id = ? LIMIT 1', [$userId]);
        if (!$user) {
            users_response(['success' => false, 'message' => 'Пользователь не найден.'], 404);
        }
        users_response(['success' => true, 'data' => $user]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        users_response(['success' => false, 'message' => 'Неверный метод запроса.'], 405);
    }
    users_require_csrf();

    if ($action === 'create') {
        $username = trim((string)($_POST['username'] ?? ''));
        $fullName = trim((string)($_POST['full_name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $role = trim((string)($_POST['role'] ?? 'manager'));
        $password = (string)($_POST['password'] ?? '');
        $isActive = !empty($_POST['is_active']) ? 1 : 0;
        $errors = [];

        if (!preg_match('/^[a-zA-Z0-9_.-]{3,50}$/', $username)) {
            $errors[] = 'Логин должен содержать 3-50 символов: латиница, цифры, ., _, -.';
        }
        if ($fullName === '' || strlen($fullName) < 3) {
            $errors[] = 'ФИО должно содержать минимум 3 символа.';
        }
        if (!isset($roles[$role])) {
            $errors[] = 'Указана недопустимая роль.';
        }
        $errors = array_merge($errors, password_policy_errors($password));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Некорректный email.';
        }
        if ($errors) {
            users_response(['success' => false, 'message' => implode(' ', $errors)], 422);
        }

        $exists = db_fetch_one('SELECT id FROM users WHERE username = ? OR (? <> "" AND email = ?) LIMIT 1', [$username, $email, $email]);
        if ($exists) {
            users_response(['success' => false, 'message' => 'Пользователь с таким логином или email уже существует.'], 409);
        }

        $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, full_name, role, is_active, email, created_at, updated_at, password_changed_at)
            VALUES (:username, :password_hash, :full_name, :role, :is_active, :email, NOW(), NOW(), NOW())');
        $stmt->execute([
            ':username' => $username,
            ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ':full_name' => $fullName,
            ':role' => $role,
            ':is_active' => $isActive,
            ':email' => $email !== '' ? $email : null,
        ]);
        $userId = (int)$pdo->lastInsertId();
        if (function_exists('audit_log_event')) {
            audit_log_event($pdo, 'user.create', 'user', $userId, $username, [], ['role' => $role, 'is_active' => $isActive]);
        }
        users_response(['success' => true, 'message' => 'Пользователь создан.']);
    }

    if ($action === 'update') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $fullName = trim((string)($_POST['full_name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $role = trim((string)($_POST['role'] ?? 'manager'));
        $isActive = !empty($_POST['is_active']) ? 1 : 0;
        $newPassword = (string)($_POST['new_password'] ?? '');
        $currentUserId = (int)($_SESSION['user']['id'] ?? 0);
        $errors = [];

        if ($userId <= 0) $errors[] = 'Некорректный идентификатор пользователя.';
        if ($fullName === '' || strlen($fullName) < 3) $errors[] = 'ФИО должно содержать минимум 3 символа.';
        if (!isset($roles[$role])) $errors[] = 'Указана недопустимая роль.';
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Некорректный email.';
        if ($userId === $currentUserId && $isActive === 0) $errors[] = 'Нельзя деактивировать собственную учетную запись.';
        if ($newPassword !== '') $errors = array_merge($errors, password_policy_errors($newPassword));
        if ($errors) users_response(['success' => false, 'message' => implode(' ', $errors)], 422);

        $target = db_fetch_one('SELECT id, username FROM users WHERE id = ? LIMIT 1', [$userId]);
        if (!$target) users_response(['success' => false, 'message' => 'Пользователь не найден.'], 404);
        $dup = db_fetch_one('SELECT id FROM users WHERE id <> ? AND email = ? LIMIT 1', [$userId, $email]);
        if ($email !== '' && $dup) users_response(['success' => false, 'message' => 'Этот email уже используется другим пользователем.'], 409);

        $fields = 'full_name = :full_name, email = :email, role = :role, is_active = :is_active, updated_at = NOW()';
        $params = [
            ':full_name' => $fullName,
            ':email' => $email !== '' ? $email : null,
            ':role' => $role,
            ':is_active' => $isActive,
            ':id' => $userId,
        ];
        if ($newPassword !== '') {
            $fields .= ', password_hash = :password_hash, password_changed_at = NOW(), failed_attempts = 0, locked_until = NULL';
            $params[':password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
        }
        $stmt = $pdo->prepare('UPDATE users SET ' . $fields . ' WHERE id = :id');
        $stmt->execute($params);
        if (function_exists('audit_log_event')) {
            audit_log_event($pdo, 'user.update', 'user', $userId, $fullName, [], ['role' => $role, 'is_active' => $isActive]);
            if ($newPassword !== '') {
                audit_log_event($pdo, 'auth.password_changed', 'user', $userId, $fullName, [], ['password_changed' => true]);
            }
        }
        users_response(['success' => true, 'message' => $newPassword !== '' ? 'Данные и пароль пользователя сохранены.' : 'Данные пользователя сохранены.']);
    }

    if ($action === 'delete') {
        $userId = (int)($_POST['id'] ?? 0);
        $currentUserId = (int)($_SESSION['user']['id'] ?? 0);
        if ($userId <= 0) users_response(['success' => false, 'message' => 'Некорректный идентификатор пользователя.'], 422);
        if ($userId === $currentUserId) users_response(['success' => false, 'message' => 'Нельзя удалить собственную учетную запись.'], 422);
        $target = db_fetch_one('SELECT username, full_name FROM users WHERE id = ? LIMIT 1', [$userId]);
        if (!$target) users_response(['success' => false, 'message' => 'Пользователь не найден.'], 404);
        $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute([':id' => $userId]);
        if (function_exists('audit_log_event')) {
            audit_log_event($pdo, 'user.delete', 'user', $userId, (string)$target['username'], [], ['full_name_removed' => true]);
        }
        users_response(['success' => true, 'message' => 'Пользователь удалён.']);
    }

    users_response(['success' => false, 'message' => 'Неизвестное действие.'], 400);
} catch (Throwable $exception) {
    error_log('[USERS API] ' . $exception->getMessage());
    users_response(['success' => false, 'message' => 'Ошибка обработки пользователя.'], 500);
}
