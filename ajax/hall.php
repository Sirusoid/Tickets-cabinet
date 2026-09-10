<?php
// ajax/hall.php

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
        $halls = db_fetch_all('SELECT id, name, code, rows_count, cols_count, created_at FROM halls ORDER BY name');
        respond(['success' => true, 'data' => $halls]);
        break;

    case 'get':
        $id = intval($_GET['id'] ?? 0);
        if ($id <= 0) respond(['success' => false, 'message' => 'Неверный ID зала.'], 400);
        $hall = db_fetch_one('SELECT * FROM halls WHERE id = ? LIMIT 1', [$id]);
        if (!$hall) respond(['success' => false, 'message' => 'Зал не найден.'], 404);
        respond(['success' => true, 'data' => $hall]);
        break;

    case 'create':
        $name = trim($_POST['name'] ?? '');
        $code = trim($_POST['code'] ?? '');
        $rows = intval($_POST['rows_count'] ?? 0);
        $cols = intval($_POST['cols_count'] ?? 0);
        $seatMap = $_POST['seat_map'] ?? null; // expected JSON string or array

        if ($name === '' || $code === '') respond(['success' => false, 'message' => 'Укажите имя и код зала.'], 400);

        $seatMapJson = null;
        if ($seatMap !== null) {
            if (is_array($seatMap)) $seatMapJson = json_encode($seatMap, JSON_UNESCAPED_UNICODE);
            else $seatMapJson = $seatMap;
        }

        db_query('INSERT INTO halls (name, code, rows_count, cols_count, seat_map, created_at) VALUES (?, ?, ?, ?, ?, NOW())', [$name, $code, $rows, $cols, $seatMapJson]);
        $id = db_connect()->lastInsertId();
        $hall = db_fetch_one('SELECT * FROM halls WHERE id = ? LIMIT 1', [$id]);
        respond(['success' => true, 'message' => 'Зал создан.', 'data' => $hall]);
        break;

    case 'update':
        $id = intval($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $code = trim($_POST['code'] ?? '');
        $rows = intval($_POST['rows_count'] ?? 0);
        $cols = intval($_POST['cols_count'] ?? 0);
        $seatMap = $_POST['seat_map'] ?? null;

        if ($id <= 0) respond(['success' => false, 'message' => 'Неверный ID зала.'], 400);
        if ($name === '' || $code === '') respond(['success' => false, 'message' => 'Укажите имя и код зала.'], 400);

        $exists = db_fetch_one('SELECT id FROM halls WHERE id = ? LIMIT 1', [$id]);
        if (!$exists) respond(['success' => false, 'message' => 'Зал не найден.'], 404);

        $seatMapJson = null;
        if ($seatMap !== null) {
            if (is_array($seatMap)) $seatMapJson = json_encode($seatMap, JSON_UNESCAPED_UNICODE);
            else $seatMapJson = $seatMap;
        }

        db_query('UPDATE halls SET name = ?, code = ?, rows_count = ?, cols_count = ?, seat_map = ? WHERE id = ?', [$name, $code, $rows, $cols, $seatMapJson, $id]);
        $hall = db_fetch_one('SELECT * FROM halls WHERE id = ? LIMIT 1', [$id]);
        respond(['success' => true, 'message' => 'Зал обновлён.', 'data' => $hall]);
        break;

    case 'delete':
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) respond(['success' => false, 'message' => 'Неверный ID зала.'], 400);
        $exists = db_fetch_one('SELECT id FROM halls WHERE id = ? LIMIT 1', [$id]);
        if (!$exists) respond(['success' => false, 'message' => 'Зал не найден.'], 404);
        db_query('DELETE FROM halls WHERE id = ?', [$id]);
        respond(['success' => true, 'message' => 'Зал удалён.']);
        break;

    default:
        respond(['success' => false, 'message' => 'Неизвестное действие: ' . $action], 400);
}
