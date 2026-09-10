<?php
// ajax/list_schedules.php
// Возвращает список сеансов для селекта: id и читабельную метку.
// Подключается init.php из корня, использует $pdo (PDO).

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../init.php';

if (function_exists('require_login')) {
    try {
        require_login();
    } catch (Exception $e) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
}

try {
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new RuntimeException('Database connection not available');
    }

    // Формируем метку: если есть таблица events с полем title — попробуем присоединить, иначе используем start_time
    $sql = "SELECT s.id, s.event_id, s.start_time, s.status, s.notes,
                   COALESCE(e.title, CONCAT('Сеанс #', s.id, ' — ', DATE_FORMAT(s.start_time, '%Y-%m-%d %H:%i'))) AS label
            FROM schedules s
            LEFT JOIN events e ON e.id = s.event_id
            ORDER BY s.start_time DESC
            LIMIT 200";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $list = [];
    foreach ($rows as $r) {
        $list[] = [
            'id' => (int)$r['id'],
            'event_id' => (int)$r['event_id'],
            'start_time' => $r['start_time'],
            'status' => $r['status'],
            'label' => $r['label'],
        ];
    }

    echo json_encode(['success' => true, 'data' => $list], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
    exit;
}
