<?php
// ajax/schedule.php
// Универсальный обработчик AJAX для расписаний (create / update / list / delete).
// Использует $pdo из init.php. Включает безопасную проверку CSRF.

require_once __DIR__ . '/../init.php';
header('Content-Type: application/json; charset=utf-8');

$action = $_REQUEST['action'] ?? '';

function json_resp($ok, $msg = '', $data = []) {
    echo json_encode(array_merge(['success' => $ok, 'message' => $msg], $data));
    exit;
}

function check_csrf($token) {
    if (function_exists('validate_csrf')) {
        try {
            return (bool) validate_csrf($token);
        } catch (Throwable $e) {
            return false;
        }
    }
    if (!session_id()) @session_start();
    $sess = $_SESSION['csrf_token'] ?? '';
    if (!$sess || !$token) return false;
    if (function_exists('hash_equals')) {
        return hash_equals((string)$sess, (string)$token);
    }
    return ((string)$sess === (string)$token);
}

function compact_json_encode($data) {
    $s = json_encode($data, JSON_UNESCAPED_UNICODE);
    if ($s === false) return '';
    return $s;
}

global $pdo;

/*
  CREATE action
*/
if ($action === 'create') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!check_csrf($csrf)) {
        json_resp(false, 'CSRF token invalid');
    }

    $event_id = isset($_POST['event_id']) ? (int)$_POST['event_id'] : null;
    $hall_id = isset($_POST['hall_id']) ? (int)$_POST['hall_id'] : null;
    $start_time = isset($_POST['start_time']) ? trim($_POST['start_time']) : '';
    $end_time = isset($_POST['end_time']) ? trim($_POST['end_time']) : '';

    // Новые поля: начало/окончание продаж (опционально)
    $sales_start_time = isset($_POST['sales_start_time']) ? trim($_POST['sales_start_time']) : null;
    $sales_end_time = isset($_POST['sales_end_time']) ? trim($_POST['sales_end_time']) : null;

    if (!$event_id || !$hall_id || !$start_time || !$end_time) {
        json_resp(false, 'Заполните обязательные поля');
    }

    $seat_map_raw = $_POST['seat_map'] ?? '';
    $seat_map_compact = '';
    if ($seat_map_raw !== '') {
        $decoded = json_decode($seat_map_raw, true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            json_resp(false, 'Неверный формат seat_map JSON');
        } else {
            $seat_map_compact = compact_json_encode($decoded);
        }
    }

    $price_ranges_raw = $_POST['price_ranges'] ?? '';

    $notes = $_POST['notes'] ?? '';
    $base_price = (isset($_POST['base_price']) && $_POST['base_price'] !== '') ? (int)$_POST['base_price'] : null;
    $status = $_POST['status'] ?? null;

    try {
        if (!isset($pdo) || !$pdo) {
            if (function_exists('db_connect')) {
                $pdo = db_connect();
            }
        }
        if (!isset($pdo) || !$pdo) {
            throw new RuntimeException('Database connection not available');
        }

        // ВАЖНО: не включаем price_ranges_count в INSERT, чтобы избежать ошибок при несовпадении схемы.
        $sql = "INSERT INTO schedules
                (event_id, hall_id, start_time, end_time, sales_start_time, sales_end_time, base_price, price_ranges, seat_map, notes, status, created_at, updated_at)
                VALUES
                (:event_id, :hall_id, :start_time, :end_time, :sales_start_time, :sales_end_time, :base_price, :price_ranges, :seat_map, :notes, :status, NOW(), NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':event_id' => $event_id,
            ':hall_id' => $hall_id,
            ':start_time' => $start_time,
            ':end_time' => $end_time,
            ':sales_start_time' => $sales_start_time !== '' ? $sales_start_time : null,
            ':sales_end_time' => $sales_end_time !== '' ? $sales_end_time : null,
            ':base_price' => $base_price,
            ':price_ranges' => $price_ranges_raw,
            ':seat_map' => $seat_map_compact,
            ':notes' => $notes,
            ':status' => $status
        ]);

        $newId = (int)$pdo->lastInsertId();
        json_resp(true, 'Сеанс создан', ['id' => $newId]);
    } catch (Throwable $e) {
        error_log('Schedule create error: ' . $e->getMessage());
        json_resp(false, 'Ошибка создания на сервере: ' . $e->getMessage());
    }
}

/*
  UPDATE action
*/
if ($action === 'update') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!check_csrf($csrf)) {
        json_resp(false, 'CSRF token invalid');
    }

    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if (!$id) json_resp(false, 'Missing id');

    $event_id = isset($_POST['event_id']) ? (int)$_POST['event_id'] : null;
    $hall_id = isset($_POST['hall_id']) ? (int)$_POST['hall_id'] : null;
    $start_time = isset($_POST['start_time']) ? trim($_POST['start_time']) : '';
    $end_time = isset($_POST['end_time']) ? trim($_POST['end_time']) : '';

    // Новые поля: начало/окончание продаж (опционально)
    $sales_start_time = isset($_POST['sales_start_time']) ? trim($_POST['sales_start_time']) : null;
    $sales_end_time = isset($_POST['sales_end_time']) ? trim($_POST['sales_end_time']) : null;

    if (!$event_id || !$hall_id || !$start_time || !$end_time) {
        json_resp(false, 'Заполните обязательные поля');
    }

    $seat_map_raw = $_POST['seat_map'] ?? '';
    $seat_map_compact = '';
    if ($seat_map_raw !== '') {
        $decoded = json_decode($seat_map_raw, true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            json_resp(false, 'Неверный формат seat_map JSON');
        } else {
            $seat_map_compact = compact_json_encode($decoded);
        }
    }

    $price_ranges_raw = $_POST['price_ranges'] ?? '';
    $notes = $_POST['notes'] ?? '';
    $base_price = (isset($_POST['base_price']) && $_POST['base_price'] !== '') ? (int)$_POST['base_price'] : null;
    $status = $_POST['status'] ?? null;

    try {
        if (!isset($pdo) || !$pdo) {
            if (function_exists('db_connect')) $pdo = db_connect();
        }
        if (!isset($pdo) || !$pdo) {
            throw new RuntimeException('Database connection not available');
        }

        $sql = "UPDATE schedules
                SET event_id = :event_id,
                    hall_id = :hall_id,
                    start_time = :start_time,
                    end_time = :end_time,
                    sales_start_time = :sales_start_time,
                    sales_end_time = :sales_end_time,
                    base_price = :base_price,
                    price_ranges = :price_ranges,
                    seat_map = :seat_map,
                    notes = :notes,
                    status = :status,
                    updated_at = NOW()
                WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':event_id' => $event_id,
            ':hall_id' => $hall_id,
            ':start_time' => $start_time,
            ':end_time' => $end_time,
            ':sales_start_time' => $sales_start_time !== '' ? $sales_start_time : null,
            ':sales_end_time' => $sales_end_time !== '' ? $sales_end_time : null,
            ':base_price' => $base_price,
            ':price_ranges' => $price_ranges_raw,
            ':seat_map' => $seat_map_compact,
            ':notes' => $notes,
            ':status' => $status,
            ':id' => $id
        ]);

        json_resp(true, 'Сеанс сохранён', ['id' => $id]);
    } catch (Throwable $e) {
        error_log('Schedule update error: ' . $e->getMessage());
        json_resp(false, 'Ошибка сохранения на сервере: ' . $e->getMessage());
    }
}

/*
  LIST action
  Возвращаем: id, event_title, hall_name, start_time, end_time, sales_start_time, sales_end_time, base_price, price_ranges, status
*/
if ($action === 'list') {
    try {
        // Попробуем выполнить корректный JOIN, чтобы получить названия события и зала
        if (!isset($pdo) || !$pdo) {
            if (function_exists('db_connect')) $pdo = db_connect();
        }

        $sql = "SELECT
                    s.id,
                    COALESCE(e.title, '') AS event_title,
                    COALESCE(h.name, '') AS hall_name,
                    s.start_time,
                    s.end_time,
                    s.sales_start_time,
                    s.sales_end_time,
                    s.base_price,
                    s.price_ranges,
                    s.status
                FROM schedules s
                LEFT JOIN events e ON e.id = s.event_id
                LEFT JOIN halls h ON h.id = s.hall_id
                ORDER BY s.start_time DESC";

        $stmt = $pdo->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        json_resp(true, '', ['data' => $rows]);
    } catch (Throwable $e) {
        error_log('Schedule list error: ' . $e->getMessage());
        json_resp(false, 'Ошибка получения списка: ' . $e->getMessage());
    }
}

/*
  DELETE action
*/
if ($action === 'delete') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!check_csrf($csrf)) json_resp(false, 'CSRF token invalid');
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if (!$id) json_resp(false, 'Missing id');

    try {
        if (!isset($pdo) || !$pdo) {
            if (function_exists('db_connect')) $pdo = db_connect();
        }
        $stmt = $pdo->prepare('DELETE FROM schedules WHERE id = ?');
        $stmt->execute([$id]);
        json_resp(true, 'Сеанс удалён');
    } catch (Throwable $e) {
        error_log('Schedule delete error: ' . $e->getMessage());
        json_resp(false, 'Ошибка удаления');
    }
}

/*
  DUPLICATE action
  Копирует запись schedules -> создаёт новую с теми же полями.
  Опционально можно принимать overrides: start_time, end_time, hall_id, event_id, status
*/
if ($action === 'duplicate') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!check_csrf($csrf)) json_resp(false, 'CSRF token invalid');

    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if (!$id) json_resp(false, 'Missing id');

    // Optional overrides (если хотите дать возможность менять при дублировании)
    $override_start = isset($_POST['start_time']) ? trim($_POST['start_time']) : null;
    $override_end = isset($_POST['end_time']) ? trim($_POST['end_time']) : null;
    $override_hall = isset($_POST['hall_id']) && $_POST['hall_id'] !== '' ? (int)$_POST['hall_id'] : null;
    $override_event = isset($_POST['event_id']) && $_POST['event_id'] !== '' ? (int)$_POST['event_id'] : null;
    $override_status = isset($_POST['status']) ? trim($_POST['status']) : null;

    try {
        if (!isset($pdo) || !$pdo) {
            if (function_exists('db_connect')) $pdo = db_connect();
        }
        // fetch existing schedule
        $stmt = $pdo->prepare("SELECT event_id, hall_id, start_time, end_time, sales_start_time, sales_end_time, base_price, price_ranges, seat_map, notes, status FROM schedules WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) json_resp(false, 'Сеанс не найден');

        // apply overrides if provided
        $new_event_id = $override_event !== null ? $override_event : $row['event_id'];
        $new_hall_id = $override_hall !== null ? $override_hall : $row['hall_id'];
        $new_start = $override_start !== null ? $override_start : $row['start_time'];
        $new_end = $override_end !== null ? $override_end : $row['end_time'];
        $new_sales_start = $row['sales_start_time'];
        $new_sales_end = $row['sales_end_time'];
        $new_base_price = $row['base_price'];
        $new_price_ranges = $row['price_ranges'];
        $new_seat_map = $row['seat_map'];
        $new_notes = $row['notes'];
        $new_status = $override_status !== null ? $override_status : $row['status'];

        // Basic validation: require start/end
        if (!$new_start || !$new_end) {
            json_resp(false, 'Невозможно дублировать: отсутствует время начала/окончания');
        }

        // Insert new schedule in transaction
        $pdo->beginTransaction();
        $ins = $pdo->prepare("INSERT INTO schedules
            (event_id, hall_id, start_time, end_time, sales_start_time, sales_end_time, base_price, price_ranges, seat_map, notes, status, created_at, updated_at)
            VALUES
            (:event_id, :hall_id, :start_time, :end_time, :sales_start_time, :sales_end_time, :base_price, :price_ranges, :seat_map, :notes, :status, NOW(), NOW())");
        $ins->execute([
            ':event_id' => $new_event_id,
            ':hall_id' => $new_hall_id,
            ':start_time' => $new_start,
            ':end_time' => $new_end,
            ':sales_start_time' => $new_sales_start !== '' ? $new_sales_start : null,
            ':sales_end_time' => $new_sales_end !== '' ? $new_sales_end : null,
            ':base_price' => $new_base_price,
            ':price_ranges' => $new_price_ranges,
            ':seat_map' => $new_seat_map,
            ':notes' => $new_notes,
            ':status' => $new_status
        ]);
        $newId = (int)$pdo->lastInsertId();
        $pdo->commit();

        json_resp(true, 'Сеанс дублирован', ['id' => $newId]);
    } catch (Throwable $e) {
        try { $pdo->rollBack(); } catch (Exception $_) {}
        error_log('Schedule duplicate error: ' . $e->getMessage());
        json_resp(false, 'Ошибка дублирования: ' . $e->getMessage());
    }
}

/*
  CHECK_OVERLAP action
  Проверяет пересечение по hall_id и временам start_time/end_time.
  Ожидает POST: start_time, end_time, hall_id, exclude_id (optional)
  Возвращает JSON: success, overlap (bool), conflicting (optional)
*/
if ($action === 'check_overlap') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!check_csrf($csrf)) json_resp(false, 'CSRF token invalid');

    $start_time = isset($_POST['start_time']) ? trim($_POST['start_time']) : '';
    $end_time = isset($_POST['end_time']) ? trim($_POST['end_time']) : '';
    $hall_id = isset($_POST['hall_id']) ? (int)$_POST['hall_id'] : 0;
    $exclude_id = isset($_POST['exclude_id']) ? (int)$_POST['exclude_id'] : 0;

    if (!$start_time || !$end_time || !$hall_id) {
        json_resp(false, 'Missing parameters');
    }

    try {
        if (!isset($pdo) || !$pdo) {
            if (function_exists('db_connect')) $pdo = db_connect();
        }

        // Overlap condition: existing.start_time < new_end AND existing.end_time > new_start
        // Also ensure same hall_id
        $sql = "SELECT s.id, s.start_time, s.end_time, s.event_id, COALESCE(e.title, '') AS event_title
                FROM schedules s
                LEFT JOIN events e ON e.id = s.event_id
                WHERE s.hall_id = :hall_id
                  AND s.start_time < :end_time
                  AND s.end_time > :start_time";
        $params = [
            ':hall_id' => $hall_id,
            ':start_time' => $start_time,
            ':end_time' => $end_time
        ];
        if ($exclude_id) {
            $sql .= " AND s.id != :exclude_id";
            $params[':exclude_id'] = $exclude_id;
        }
        $sql .= " ORDER BY s.start_time ASC LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            json_resp(true, '', ['overlap' => true, 'conflicting' => [
                'id' => (int)$row['id'],
                'start_time' => $row['start_time'],
                'end_time' => $row['end_time'],
                'event_id' => isset($row['event_id']) ? (int)$row['event_id'] : null,
                'event_title' => $row['event_title'] ?? ''
            ]]);
        } else {
            json_resp(true, '', ['overlap' => false]);
        }
    } catch (Throwable $e) {
        error_log('Schedule check_overlap error: ' . $e->getMessage());
        json_resp(false, 'Ошибка проверки пересечений');
    }
}

json_resp(false, 'Unknown action');
