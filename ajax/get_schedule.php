<?php
// ajax/get_schedule.php
// Возвращает JSON с данными сеанса (schedule) по schedule_id.
// Ожидает GET-параметр schedule_id (int).
// Подключается init.php из корня, использует $pdo (PDO) для выборки.

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

$scheduleId = isset($_GET['schedule_id']) ? (int)$_GET['schedule_id'] : 0;
if ($scheduleId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid schedule_id']);
    exit;
}

try {
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new RuntimeException('Database connection not available');
    }

    $sql = "SELECT id, event_id, hall_id, start_time, end_time, sales_start_time, sales_end_time,
                   base_price, min_price, max_price, status, is_sold_out, seat_map, price_ranges, notes, created_at, updated_at
            FROM schedules
            WHERE id = :id
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $scheduleId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Schedule not found']);
        exit;
    }

    $seatMapRaw = $row['seat_map'] ?? null;
    $priceRangesRaw = $row['price_ranges'] ?? null;

    $seatMap = null;
    if ($seatMapRaw !== null && $seatMapRaw !== '') {
        $decoded = json_decode($seatMapRaw, true);
        $seatMap = json_last_error() === JSON_ERROR_NONE ? $decoded : $seatMapRaw;
    }

    $priceRanges = null;
    if ($priceRangesRaw !== null && $priceRangesRaw !== '') {
        $decoded = json_decode($priceRangesRaw, true);
        $priceRanges = json_last_error() === JSON_ERROR_NONE ? $decoded : $priceRangesRaw;
    }

    $data = [
        'id' => (int)$row['id'],
        'event_id' => (int)$row['event_id'],
        'hall_id' => (int)$row['hall_id'],
        'start_time' => $row['start_time'],
        'end_time' => $row['end_time'],
        'sales_start_time' => $row['sales_start_time'],
        'sales_end_time' => $row['sales_end_time'],
        'base_price' => $row['base_price'],
        'min_price' => $row['min_price'],
        'max_price' => $row['max_price'],
        'status' => $row['status'],
        'is_sold_out' => (bool)$row['is_sold_out'],
        'seat_map' => $seatMap,
        'price_ranges' => $priceRanges,
        'notes' => $row['notes'],
        'created_at' => $row['created_at'],
        'updated_at' => $row['updated_at'],
    ];

    echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
    exit;
}
