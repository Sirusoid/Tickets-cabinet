<?php
require_once __DIR__ . '/../init.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

function respond($payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function require_customer_csrf(): void
{
    $token = (string)($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if ($token === '' || empty($_SESSION['csrf_token']) || !hash_equals((string)$_SESSION['csrf_token'], $token)) {
        respond(['success' => false, 'message' => 'Неверный CSRF токен.'], 403);
    }
}

$action = trim($_REQUEST['action'] ?? '');

if ($action === '') {
    respond(['success' => false, 'message' => 'Отсутствует действие (action).'], 400);
}

switch ($action) {
    case 'list':
        $name = trim((string)($_GET['name'] ?? ''));
        $phoneRaw = trim((string)($_GET['phone'] ?? ''));
        $search = trim((string)($_GET['search'] ?? ''));
        $legacySearch = $name === '' && $phoneRaw === '' && $search !== '' ? $search : '';
        $name = $name !== '' ? $name : ($legacySearch === '' ? '' : $search);
        $phone = normalize_customer_phone($phoneRaw);
        $phoneDigits = customer_phone_digits($phone);
        $page = max(1, (int)($_GET['page'] ?? 1));
        $requestedPerPage = (int)($_GET['per_page'] ?? 25);
        $perPage = in_array($requestedPerPage, [25, 50, 100, 500], true) ? $requestedPerPage : 25;
        $where = [];
        $params = [];

        if ($name !== '') {
            if ($legacySearch !== '') {
                $where[] = '(full_name LIKE :search_name OR email LIKE :search_email OR phone LIKE :search_phone)';
                $params[':search_name'] = '%' . $legacySearch . '%';
                $params[':search_email'] = '%' . $legacySearch . '%';
                $params[':search_phone'] = '%' . $legacySearch . '%';
            } else {
                $where[] = 'full_name LIKE :name';
                $params[':name'] = '%' . $name . '%';
            }
        }
        if ($phone !== '') {
            $where[] = '(phone LIKE :phone OR phone LIKE :phone_digits)';
            $params[':phone'] = '%' . $phone . '%';
            $params[':phone_digits'] = '%' . $phoneDigits . '%';
        }

        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $totalRow = db_fetch_one('SELECT COUNT(*) AS total FROM customers' . $whereSql, $params);
        $total = (int)($totalRow['total'] ?? 0);
        $totalPages = max(1, (int)ceil($total / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;
        $customers = db_fetch_all(
            'SELECT id, full_name, email, phone, note, created_at
             FROM customers' . $whereSql . '
             ORDER BY full_name ASC, id ASC
             LIMIT ' . $perPage . ' OFFSET ' . $offset,
            $params
        );
        respond([
            'success' => true,
            'data' => $customers,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
        ]);
        break;

    case 'get':
        $customerId = intval($_GET['id'] ?? 0);
        if ($customerId <= 0) {
            respond(['success' => false, 'message' => 'Неверный ID клиента.'], 400);
        }

        $customer = db_fetch_one('SELECT id, full_name, email, phone, city, birth_date, gender, note, created_at FROM customers WHERE id = ? LIMIT 1', [$customerId]);
        if (!$customer) {
            respond(['success' => false, 'message' => 'Клиент не найден.'], 404);
        }

        respond(['success' => true, 'data' => $customer]);
        break;

    case 'create':
        require_customer_csrf();
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = normalize_customer_phone((string)($_POST['phone'] ?? ''));
        $note = trim($_POST['note'] ?? '');

        if ($fullName === '') {
            respond(['success' => false, 'message' => 'Укажите имя клиента.'], 400);
        }

        db_query(
            'INSERT INTO customers (full_name, email, phone, note, created_at) VALUES (?, ?, ?, ?, NOW())',
            [$fullName, $email, $phone, $note]
        );

        $customerId = db_connect()->lastInsertId();
        $customer = db_fetch_one('SELECT id, full_name, email, phone, note, created_at FROM customers WHERE id = ? LIMIT 1', [$customerId]);
        respond(['success' => true, 'message' => 'Клиент создан.', 'data' => $customer]);
        break;

    case 'update':
        require_customer_csrf();
        $customerId = intval($_POST['id'] ?? 0);
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = normalize_customer_phone((string)($_POST['phone'] ?? ''));
        $city = trim((string)($_POST['city'] ?? ''));
        $birthDate = trim((string)($_POST['birth_date'] ?? ''));
        $gender = trim((string)($_POST['gender'] ?? ''));
        $note = trim($_POST['note'] ?? '');

        if ($customerId <= 0) {
            respond(['success' => false, 'message' => 'Неверный ID клиента.'], 400);
        }
        if ($fullName === '') {
            respond(['success' => false, 'message' => 'Укажите имя клиента.'], 400);
        }

        $existing = db_fetch_one('SELECT id FROM customers WHERE id = ? LIMIT 1', [$customerId]);
        if (!$existing) {
            respond(['success' => false, 'message' => 'Клиент не найден.'], 404);
        }

        if ($birthDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthDate)) {
            respond(['success' => false, 'message' => 'Неверный формат даты рождения.'], 400);
        }
        if (!in_array($gender, ['', 'male', 'female', 'other'], true)) {
            respond(['success' => false, 'message' => 'Неверное значение пола.'], 400);
        }

        db_query(
            'UPDATE customers SET full_name = ?, email = ?, phone = ?, city = ?, birth_date = ?, gender = ?, note = ?, updated_at = NOW() WHERE id = ?',
            [$fullName, $email, $phone, $city, $birthDate !== '' ? $birthDate : null, $gender !== '' ? $gender : null, $note, $customerId]
        );

        $customer = db_fetch_one('SELECT id, full_name, email, phone, city, birth_date, gender, note, created_at FROM customers WHERE id = ? LIMIT 1', [$customerId]);
        respond(['success' => true, 'message' => 'Клиент обновлён.', 'data' => $customer]);
        break;

    case 'delete':
        require_customer_csrf();
        $customerId = intval($_POST['id'] ?? 0);
        if ($customerId <= 0) {
            respond(['success' => false, 'message' => 'Неверный ID клиента.'], 400);
        }

        $existing = db_fetch_one('SELECT id FROM customers WHERE id = ? LIMIT 1', [$customerId]);
        if (!$existing) {
            respond(['success' => false, 'message' => 'Клиент не найден.'], 404);
        }

        db_query('DELETE FROM customers WHERE id = ?', [$customerId]);
        respond(['success' => true, 'message' => 'Клиент удалён.']);
        break;

    default:
        respond(['success' => false, 'message' => 'Неизвестное действие: ' . $action], 400);
        break;
}
