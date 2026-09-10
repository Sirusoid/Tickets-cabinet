<?php
//ajax/seats.php

require_once __DIR__ . '/../init.php';
require_login();
header('Content-Type: application/json; charset=utf-8');

function respond($payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

$action = trim($_REQUEST['action'] ?? '');
if ($action === '') {
    respond(['success' => false, 'message' => 'Отсутствует действие (action).'], 400);
}

switch ($action) {
    case 'list':
        $hall_id = intval($_GET['hall_id'] ?? 0);
        if ($hall_id <= 0) respond(['success' => false, 'message' => 'Неверный ID зала.'], 400);
        $seats = db_fetch_all('SELECT * FROM seats WHERE hall_id = ? ORDER BY row_number, seat_number', [$hall_id]);
        respond(['success' => true, 'data' => $seats]);
        break;

    case 'get':
        $id = intval($_GET['id'] ?? 0);
        if ($id <= 0) respond(['success' => false, 'message' => 'Неверный ID места.'], 400);
        $seat = db_fetch_one('SELECT * FROM seats WHERE id = ? LIMIT 1', [$id]);
        if (!$seat) respond(['success' => false, 'message' => 'Место не найдено.'], 404);
        respond(['success' => true, 'data' => $seat]);
        break;

    case 'create':
        $hall_id     = intval($_POST['hall_id'] ?? 0);
        $row_number  = intval($_POST['row_number'] ?? 0);
        $seat_number = intval($_POST['seat_number'] ?? 0);
        $is_available= intval($_POST['is_available'] ?? 1);
        $base_price  = floatval($_POST['base_price'] ?? 0);

        if ($hall_id <= 0) respond(['success' => false, 'message' => 'Неверный ID зала.'], 400);

        db_query('INSERT INTO seats (hall_id, row_number, seat_number, is_available, base_price, created_at, updated_at) 
                  VALUES (?, ?, ?, ?, ?, NOW(), NOW())',
                  [$hall_id, $row_number, $seat_number, $is_available, $base_price]);

        $id = db_connect()->lastInsertId();
        $seat = db_fetch_one('SELECT * FROM seats WHERE id = ? LIMIT 1', [$id]);
        respond(['success' => true, 'message' => 'Место создано.', 'data' => $seat]);
        break;

    case 'update':
        $id          = intval($_POST['id'] ?? 0);
        $hall_id     = intval($_POST['hall_id'] ?? 0);
        $row_number  = intval($_POST['row_number'] ?? 0);
        $seat_number = intval($_POST['seat_number'] ?? 0);
        $is_available= intval($_POST['is_available'] ?? 1);
        $base_price  = floatval($_POST['base_price'] ?? 0);

        if ($id <= 0) respond(['success' => false, 'message' => 'Неверный ID места.'], 400);

        $exists = db_fetch_one('SELECT id FROM seats WHERE id = ? LIMIT 1', [$id]);
        if (!$exists) respond(['success' => false, 'message' => 'Место не найдено.'], 404);

        db_query('UPDATE seats SET hall_id = ?, row_number = ?, seat_number = ?, is_available = ?, base_price = ?, updated_at = NOW() WHERE id = ?',
                 [$hall_id, $row_number, $seat_number, $is_available, $base_price, $id]);

        $seat = db_fetch_one('SELECT * FROM seats WHERE id = ? LIMIT 1', [$id]);
        respond(['success' => true, 'message' => 'Место обновлено.', 'data' => $seat]);
        break;

    case 'delete':
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) respond(['success' => false, 'message' => 'Неверный ID места.'], 400);
        $exists = db_fetch_one('SELECT id FROM seats WHERE id = ? LIMIT 1', [$id]);
        if (!$exists) respond(['success' => false, 'message' => 'Место не найдено.'], 404);
        db_query('DELETE FROM seats WHERE id = ?', [$id]);
        respond(['success' => true, 'message' => 'Место удалено.']);
        break;

    default:
        respond(['success' => false, 'message' => 'Неизвестное действие: ' . $action], 400);
}
