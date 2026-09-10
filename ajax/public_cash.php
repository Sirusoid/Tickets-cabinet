<?php
// ajax/public_cash.php
// Публичный AJAX-обработчик для виджета: только чтение схемы зала и доступности мест.

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/settings_manager.php';

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

// CORS для виджета на zhassahna.kz
$allowedOrigin = defined('TILDA_WIDGET_ORIGIN') ? (string)constant('TILDA_WIDGET_ORIGIN') : 'https://zhassahna.kz';
if ($allowedOrigin !== '') {
    header('Access-Control-Allow-Origin: ' . $allowedOrigin);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

function json_response($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function public_ticket_setting(PDO $pdo, string $key, $default = null) {
    $value = function_exists('settings_get_value') ? settings_get_value($pdo, $key, $default) : $default;
    return $value === null || $value === '' ? $default : $value;
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    json_response(['success' => false, 'message' => 'База данных недоступна']);
}

$raw = file_get_contents('php://input');
$input = [];
if ($raw) {
    $tmp = json_decode($raw, true);
    if (is_array($tmp)) $input = $tmp;
}
if (!empty($_POST) && is_array($_POST)) $input = array_merge($input, $_POST);

$action = isset($_GET['action']) ? trim((string)$_GET['action']) : (isset($input['action']) ? trim((string)$input['action']) : '');

switch ($action) {
    case 'session':
        $session_id = isset($_GET['session_id']) ? (int)$_GET['session_id'] : (isset($input['session_id']) ? (int)$input['session_id'] : 0);
        if (!$session_id) json_response(['success' => false, 'message' => 'Не указан сеанс']);

        $stmt = $pdo->prepare("SELECT s.*, COALESCE(e.title, '') AS event_title, h.name AS hall_name, h.seat_map AS hall_seatmap
            FROM schedules s
            LEFT JOIN events e ON s.event_id = e.id
            LEFT JOIN halls h ON s.hall_id = h.id
            WHERE s.id = :id AND s.status IN ('upcoming','active') AND s.start_time >= NOW()
            LIMIT 1");
        $stmt->execute([':id' => $session_id]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$session) {
            json_response(['success' => false, 'message' => 'Сеанс не найден или продажа недоступна']);
        }

        if (!empty($session['hall_seatmap']) && is_string($session['hall_seatmap'])) {
            $decoded = json_decode($session['hall_seatmap'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $session['hall_seatmap'] = $decoded;
            }
        }

        $sold = [];
        try {
            $q = $pdo->prepare("SELECT seat_identifier FROM tickets WHERE schedule_id = :sid AND status = 'issued'");
            $q->execute([':sid' => $session_id]);
            $rows = $q->fetchAll(PDO::FETCH_COLUMN, 0);
            foreach ($rows as $r) {
                if (!is_string($r)) continue;
                $sold[] = str_replace(':', '-', $r);
            }
        } catch (Exception $e) {
            $sold = [];
        }

        $held = [];
        try {
            $hst = $pdo->prepare("SELECT seat_key FROM cash_holds WHERE session_id = :sid AND expires_at > NOW()");
            $hst->execute([':sid' => $session_id]);
            while ($row = $hst->fetch(PDO::FETCH_ASSOC)) {
                $held[] = is_string($row['seat_key']) ? str_replace(':', '-', $row['seat_key']) : $row['seat_key'];
            }
        } catch (Exception $e) {
            $held = [];
        }

        $priceRanges = null;
        if (!empty($session['price_ranges'])) {
            $tmp = is_string($session['price_ranges']) ? json_decode($session['price_ranges'], true) : $session['price_ranges'];
            if (is_array($tmp)) $priceRanges = $tmp;
        }

        $clientReservationEnabled = in_array(strtolower(trim((string)public_ticket_setting($pdo, 'tickets.client_reservation_enabled', '0'))), ['1', 'true', 'yes', 'on'], true);
        $clientReservationMinutes = max(1, min(240, (int)public_ticket_setting($pdo, 'tickets.client_reservation_minutes', '15')));
        $maxTickets = max(1, (int)public_ticket_setting($pdo, 'tickets.max_tickets_per_user', '6'));
        json_response(['success' => true, 'data' => [
            'session' => $session,
            'sold_seats' => $sold,
            'held_seats' => $held,
            'price_ranges' => $priceRanges,
            'client_reservation_enabled' => $clientReservationEnabled,
            'client_reservation_minutes' => $clientReservationMinutes,
            'max_tickets_per_user' => $maxTickets,
        ]]);
        break;

    case 'client_hold':
        $session_id = isset($input['session_id']) ? (int)$input['session_id'] : 0;
        $token = trim((string)($input['token'] ?? ''));
        $seatsRaw = $input['seats'] ?? [];
        $enabled = in_array(strtolower(trim((string)public_ticket_setting($pdo, 'tickets.client_reservation_enabled', '0'))), ['1', 'true', 'yes', 'on'], true);
        $ttl = max(1, min(240, (int)public_ticket_setting($pdo, 'tickets.client_reservation_minutes', '15')));
        if (!$enabled) json_response(['success' => false, 'message' => 'Резервирование клиентом временно недоступно']);
        if (!$session_id || !is_array($seatsRaw) || empty($seatsRaw) || strlen($token) < 16) {
            json_response(['success' => false, 'message' => 'Неверные параметры резерва'], 400);
        }

        $seatKeys = [];
        foreach ($seatsRaw as $seat) {
            $key = is_array($seat) ? ($seat['identifier'] ?? $seat['key'] ?? '') : $seat;
            $key = str_replace(':', '-', trim((string)$key));
            if ($key !== '') $seatKeys[] = $key;
        }
        $seatKeys = array_values(array_unique($seatKeys));
        if (empty($seatKeys)) json_response(['success' => false, 'message' => 'Места не выбраны'], 400);

        $sessionStmt = $pdo->prepare("SELECT id FROM schedules WHERE id = :id AND status IN ('upcoming','active') AND start_time >= NOW() LIMIT 1");
        $sessionStmt->execute([':id' => $session_id]);
        if (!$sessionStmt->fetchColumn()) json_response(['success' => false, 'message' => 'Сеанс недоступен'], 404);

        $placeholders = implode(',', array_fill(0, count($seatKeys), '?'));
        $pdo->beginTransaction();
        try {
            $soldStmt = $pdo->prepare("SELECT seat_identifier FROM tickets WHERE schedule_id = ? AND seat_identifier IN ($placeholders) AND status = 'issued' FOR UPDATE");
            $soldStmt->execute(array_merge([$session_id], $seatKeys));
            if ($soldStmt->fetchColumn()) throw new RuntimeException('Некоторые места уже проданы');

            $holdStmt = $pdo->prepare("SELECT seat_key, meta FROM cash_holds WHERE session_id = ? AND seat_key IN ($placeholders) AND expires_at > NOW() FOR UPDATE");
            $holdStmt->execute(array_merge([$session_id], $seatKeys));
            $conflicts = [];
            while ($hold = $holdStmt->fetch(PDO::FETCH_ASSOC)) {
                $meta = json_decode((string)($hold['meta'] ?? ''), true);
                if (!is_array($meta) || ($meta['token'] ?? '') !== $token) $conflicts[] = $hold['seat_key'];
            }
            if (!empty($conflicts)) throw new RuntimeException('Некоторые места уже зарезервированы');

            $expiresAt = (new DateTime("+{$ttl} minutes"))->format('Y-m-d H:i:s');
            $insert = $pdo->prepare("INSERT INTO cash_holds (session_id, seat_key, user_id, created_at, expires_at, meta)
                VALUES (:session_id, :seat_key, 0, NOW(), :expires_at, :meta)
                ON DUPLICATE KEY UPDATE expires_at = VALUES(expires_at), meta = VALUES(meta)");
            $meta = json_encode(['source' => 'public_widget_client', 'token' => $token], JSON_UNESCAPED_UNICODE);
            foreach ($seatKeys as $seatKey) {
                $insert->execute([':session_id' => $session_id, ':seat_key' => $seatKey, ':expires_at' => $expiresAt, ':meta' => $meta]);
            }
            $pdo->commit();
            json_response(['success' => true, 'message' => 'Места зарезервированы', 'held' => $seatKeys, 'expires_at' => $expiresAt]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            json_response(['success' => false, 'message' => $e->getMessage()], 409);
        }
        break;

    default:
        json_response(['success' => false, 'message' => 'Неизвестное действие']);
}
