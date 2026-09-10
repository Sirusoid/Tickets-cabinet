<?php
// ajax/public_payment.php
// Публичный AJAX-обработчик создания BCC-платёжной сессии для виджета.

require_once __DIR__ . '/../init.php';

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

$allowedOrigin = defined('TILDA_WIDGET_ORIGIN') ? (string)constant('TILDA_WIDGET_ORIGIN') : 'https://zhassahna.kz';
if ($allowedOrigin !== '') {
    header('Access-Control-Allow-Origin: ' . $allowedOrigin);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/payment/bcc.php';
require_once __DIR__ . '/../includes/settings_manager.php';

function json_response($data)
{
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function public_payment_setting(PDO $pdo, string $key, $default = null) {
    $value = function_exists('settings_get_value') ? settings_get_value($pdo, $key, $default) : $default;
    return $value === null || $value === '' ? $default : $value;
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    json_response(['success' => false, 'message' => 'База данных недоступна']);
}

if (app_setting_enabled($pdo, 'system.maintenance_enabled')) {
    json_response(['success' => false, 'message' => 'Сайт временно обновляется. Скоро вернёмся.']);
}

if (!bcc_is_enabled($pdo)) {
    json_response(['success' => false, 'message' => 'Оплата картой временно недоступна']);
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
    case 'create_bcc_session':
        $session_id = isset($input['session_id']) ? (int)$input['session_id'] : 0;
        $seats_raw = isset($input['seats']) ? $input['seats'] : [];
        $customer_name = isset($input['customer_name']) ? trim((string)$input['customer_name']) : '';
        $customer_phone = isset($input['customer_phone']) ? trim((string)$input['customer_phone']) : '';
        $customer_email = isset($input['customer_email']) ? trim((string)$input['customer_email']) : '';
        $client_hold_token = trim((string)($input['client_hold_token'] ?? ''));

        if (!$session_id) {
            json_response(['success' => false, 'message' => 'Не указан сеанс']);
        }
        if (!is_array($seats_raw) || empty($seats_raw)) {
            json_response(['success' => false, 'message' => 'Не выбраны места']);
        }
        if ($customer_phone === '') {
            json_response(['success' => false, 'message' => 'Укажите номер телефона']);
        }

        $normalizedPhone = normalize_customer_phone($customer_phone);
        $phoneDigits = customer_phone_digits($normalizedPhone);
        if (strlen($phoneDigits) < 10) {
            json_response(['success' => false, 'message' => 'Некорректный номер телефона']);
        }

        $stmt = $pdo->prepare("SELECT s.*, e.title AS event_title FROM schedules s LEFT JOIN events e ON s.event_id = e.id WHERE s.id = :id AND s.status IN ('upcoming','active') AND s.start_time >= NOW() LIMIT 1");
        $stmt->execute([':id' => $session_id]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$session) {
            json_response(['success' => false, 'message' => 'Сеанс не найден или продажа недоступна']);
        }

        $event_id = (int)$session['event_id'];
        $hall_id = (int)$session['hall_id'];

        $seats = [];
        $seat_keys = [];
        foreach ($seats_raw as $s) {
            $identifier = is_array($s) && isset($s['identifier']) ? (string)$s['identifier'] : (is_string($s) ? $s : '');
            $identifier = str_replace(':', '-', trim($identifier));
            if ($identifier === '') continue;
            $price = is_array($s) && isset($s['price']) ? (float)$s['price'] : 0.0;
            $seats[] = ['identifier' => $identifier, 'price' => $price];
            $seat_keys[] = $identifier;
        }

        if (empty($seats)) {
            json_response(['success' => false, 'message' => 'Не удалось распознать выбранные места']);
        }

        $maxTickets = max(1, (int)public_payment_setting($pdo, 'tickets.max_tickets_per_user', '6'));
        $existingTicketsStmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE schedule_id = :schedule_id AND (customer_phone = :phone OR customer_phone = :phone_digits) AND payment_status = 'paid' AND status <> 'cancelled' AND COALESCE(refund_status, 'none') <> 'refunded'");
        $existingTicketsStmt->execute([':schedule_id' => $session_id, ':phone' => $normalizedPhone, ':phone_digits' => $phoneDigits]);
        $existingTickets = (int)$existingTicketsStmt->fetchColumn();
        if ($existingTickets + count($seats) > $maxTickets) {
            json_response(['success' => false, 'message' => 'Превышен лимит билетов на одного пользователя: ' . $maxTickets]);
        }

        $placeholders = implode(',', array_fill(0, count($seat_keys), '?'));
        $params = array_merge([$session_id], $seat_keys);

        try {
            // Удаляем старые брони виджета для этих мест, чтобы пользователь мог повторить попытку
            if ($client_hold_token !== '') {
                $delHoldStmt = $pdo->prepare("DELETE FROM cash_holds WHERE session_id = ? AND seat_key IN ($placeholders) AND meta LIKE ?");
                $delHoldStmt->execute(array_merge($params, ['%' . '"token":"' . $client_hold_token . '"' . '%']));
            } else {
                $delHoldStmt = $pdo->prepare("DELETE FROM cash_holds WHERE session_id = ? AND seat_key IN ($placeholders) AND meta LIKE '%widget_online_payment%'");
                $delHoldStmt->execute($params);
            }

            $soldStmt = $pdo->prepare("SELECT seat_identifier FROM tickets WHERE schedule_id = ? AND seat_identifier IN ($placeholders) AND status = 'issued'");
            $soldStmt->execute($params);
            $sold = $soldStmt->fetchAll(PDO::FETCH_COLUMN, 0);
            if (!empty($sold)) {
                json_response(['success' => false, 'message' => 'Некоторые места уже проданы', 'seats' => $sold]);
            }

            $holdStmt = $pdo->prepare("SELECT seat_key FROM cash_holds WHERE session_id = ? AND seat_key IN ($placeholders) AND expires_at > NOW()");
            $holdStmt->execute($params);
            $held = $holdStmt->fetchAll(PDO::FETCH_COLUMN, 0);
            if (!empty($held)) {
                json_response(['success' => false, 'message' => 'Некоторые места уже забронированы', 'seats' => $held]);
            }
        } catch (Exception $e) {
            json_response(['success' => false, 'message' => 'Ошибка проверки мест']);
        }

        $amountCents = (int) round(array_sum(array_column($seats, 'price')) * 100);
        if ($amountCents <= 0) {
            json_response(['success' => false, 'message' => 'Сумма заказа должна быть больше 0']);
        }

        $reserveMinutes = 20;
        $expires_at = (new DateTime("+{$reserveMinutes} minutes"))->format('Y-m-d H:i:s');

        $pdo->beginTransaction();
        try {
            $holdIns = $pdo->prepare("INSERT INTO cash_holds (session_id, seat_key, user_id, expires_at, meta) VALUES (:session_id, :seat_key, 0, :expires_at, :meta)");
            foreach ($seat_keys as $sk) {
                $holdIns->execute([
                    ':session_id' => $session_id,
                    ':seat_key' => $sk,
                    ':expires_at' => $expires_at,
                    ':meta' => json_encode(['source' => 'widget_online_payment'], JSON_UNESCAPED_UNICODE),
                ]);
            }

            $order = bcc_generate_order();
            $merchRnId = bcc_generate_merch_rn_id();
            $desc = 'Tickets ' . $session['event_title'];
            if (mb_strlen($desc) > 225) {
                $desc = mb_substr($desc, 0, 222) . '...';
            }

            $ins = $pdo->prepare("INSERT INTO payment_sessions
                (session_id, event_id, hall_id, order_number, merch_rn_id, amount_cents, currency, status, provider, customer_name, customer_phone, customer_email, seats_payload)
                VALUES
                (:session_id, :event_id, :hall_id, :order_number, :merch_rn_id, :amount_cents, 'KZT', 'pending', 'bcc', :customer_name, :customer_phone, :customer_email, :seats_payload)");
            $ins->execute([
                ':session_id' => $session_id,
                ':event_id' => $event_id,
                ':hall_id' => $hall_id,
                ':order_number' => $order,
                ':merch_rn_id' => $merchRnId,
                ':amount_cents' => $amountCents,
                ':customer_name' => $customer_name ?: null,
                ':customer_phone' => $normalizedPhone,
                ':customer_email' => $customer_email ?: null,
                ':seats_payload' => json_encode($seats, JSON_UNESCAPED_UNICODE),
            ]);

            $cfg = bcc_config($pdo);
            // Принудительно используем публичный BACKREF-обработчик, независимо от устаревшего значения в БД
            if (defined('PUBLIC_BCC_BACKREF_PATH')) {
                $cfg['backref_path'] = (string) constant('PUBLIC_BCC_BACKREF_PATH');
            }
            $clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $form = bcc_build_payment_form($cfg, $order, $merchRnId, $amountCents, $desc, $clientIp, $phoneDigits);

            $pdo->commit();

            json_response([
                'success' => true,
                'order' => $order,
                'action' => $form['action'],
                'fields' => $form['fields'],
            ]);
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('public_payment create_bcc_session error: ' . $e->getMessage());
            json_response(['success' => false, 'message' => 'Не удалось создать платёжную сессию']);
        }
        break;

    case 'check_status':
        $order = isset($input['order']) ? trim((string)$input['order']) : '';
        if ($order === '') {
            json_response(['success' => false, 'message' => 'Не указан номер заказа']);
        }
        try {
            $stmt = $pdo->prepare("SELECT id, status, provider_response, ticket_uids, amount_cents FROM payment_sessions WHERE order_number = :order LIMIT 1");
            $stmt->execute([':order' => $order]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                json_response(['success' => false, 'message' => 'Платёжная сессия не найдена']);
            }
            json_response([
                'success' => true,
                'status' => $row['status'],
                'provider_response' => $row['provider_response'] ? json_decode($row['provider_response'], true) : null,
                'ticket_uids' => $row['ticket_uids'] ? json_decode($row['ticket_uids'], true) : [],
                'amount_cents' => (int)$row['amount_cents'],
            ]);
        } catch (Exception $e) {
            error_log('public_payment check_status error: ' . $e->getMessage());
            json_response(['success' => false, 'message' => 'Ошибка проверки статуса']);
        }
        break;

    default:
        json_response(['success' => false, 'message' => 'Неизвестное действие']);
}
