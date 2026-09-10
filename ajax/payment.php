<?php
// ajax/payment.php
// AJAX-обработчик для онлайн-оплаты через BCC

require_once __DIR__ . '/../init.php';

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/payment/bcc.php';

if (!function_exists('settings_get_value')) {
    require_once __DIR__ . '/../includes/settings_manager.php';
}

function json_response($data)
{
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function current_user_id()
{
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : (isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : 0);
}

function validate_csrf_safe($token)
{
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

$raw = file_get_contents('php://input');
$input = [];
if ($raw) {
    $tmp = json_decode($raw, true);
    if (is_array($tmp)) $input = $tmp;
}
if (!empty($_POST) && is_array($_POST)) $input = array_merge($input, $_POST);

$action = isset($_GET['action']) ? trim((string)$_GET['action']) : (isset($input['action']) ? trim((string)$input['action']) : '');

if (!isset($pdo) || !($pdo instanceof PDO)) {
    json_response(['success' => false, 'message' => 'Database not available']);
}

if (!bcc_is_enabled($pdo)) {
    json_response(['success' => false, 'message' => 'BCC acquiring is disabled']);
}

switch ($action) {
    case 'create_bcc_session':
        $session_id = isset($input['session_id']) ? (int)$input['session_id'] : 0;
        $seats_raw = isset($input['seats']) ? $input['seats'] : [];
        $customer_name = isset($input['customer_name']) ? trim((string)$input['customer_name']) : '';
        $customer_phone = isset($input['customer_phone']) ? trim((string)$input['customer_phone']) : '';
        $customer_email = isset($input['customer_email']) ? trim((string)$input['customer_email']) : '';
        $csrf = isset($input['csrf_token']) ? trim((string)$input['csrf_token']) : '';

        if (!validate_csrf_safe($csrf)) {
            json_response(['success' => false, 'message' => 'Invalid CSRF token']);
        }
        if (!$session_id) {
            json_response(['success' => false, 'message' => 'session_id required']);
        }
        if (!is_array($seats_raw) || empty($seats_raw)) {
            json_response(['success' => false, 'message' => 'Seats required']);
        }
        if ($customer_phone === '') {
            json_response(['success' => false, 'message' => 'Phone required']);
        }

        $normalizedPhone = normalize_customer_phone($customer_phone);
        $phoneDigits = customer_phone_digits($normalizedPhone);
        if (strlen($phoneDigits) < 10) {
            json_response(['success' => false, 'message' => 'Invalid phone number']);
        }

        // Получаем данные сеанса
        $stmt = $pdo->prepare("SELECT s.*, e.title AS event_title FROM schedules s LEFT JOIN events e ON s.event_id = e.id WHERE s.id = :id LIMIT 1");
        $stmt->execute([':id' => $session_id]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$session) {
            json_response(['success' => false, 'message' => 'Session not found']);
        }

        $event_id = (int)$session['event_id'];
        $hall_id = (int)$session['hall_id'];

        // Нормализуем места
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
            json_response(['success' => false, 'message' => 'No valid seats']);
        }

        // Проверяем, что места не проданы и не в резерве
        $placeholders = implode(',', array_fill(0, count($seat_keys), '?'));
        $params = array_merge([$session_id], $seat_keys);

        try {
            $soldStmt = $pdo->prepare("SELECT seat_identifier FROM tickets WHERE schedule_id = ? AND seat_identifier IN ($placeholders) AND status = 'issued'");
            $soldStmt->execute($params);
            $sold = $soldStmt->fetchAll(PDO::FETCH_COLUMN, 0);
            if (!empty($sold)) {
                json_response(['success' => false, 'message' => 'Some seats are already sold', 'seats' => $sold]);
            }

            $holdStmt = $pdo->prepare("SELECT seat_key FROM cash_holds WHERE session_id = ? AND seat_key IN ($placeholders) AND expires_at > NOW()");
            $holdStmt->execute($params);
            $held = $holdStmt->fetchAll(PDO::FETCH_COLUMN, 0);
            if (!empty($held)) {
                json_response(['success' => false, 'message' => 'Some seats are reserved', 'seats' => $held]);
            }
        } catch (Exception $e) {
            json_response(['success' => false, 'message' => 'Seat check error']);
        }

        // Считаем сумму
        $amountCents = (int) round(array_sum(array_column($seats, 'price')) * 100);
        if ($amountCents <= 0) {
            json_response(['success' => false, 'message' => 'Amount must be greater than 0']);
        }

        // Создаём резерв мест на 20 минут (чтобы никто не купил, пока клиент платит)
        $reserveMinutes = 20;
        $now = (new DateTime())->format('Y-m-d H:i:s');
        $expires_at = (new DateTime("+{$reserveMinutes} minutes"))->format('Y-m-d H:i:s');
        $user_id = current_user_id();

        $pdo->beginTransaction();
        try {
            $holdIns = $pdo->prepare("INSERT INTO cash_holds (session_id, seat_key, user_id, expires_at, meta) VALUES (:session_id, :seat_key, :user_id, :expires_at, :meta)");
            foreach ($seat_keys as $sk) {
                $holdIns->execute([
                    ':session_id' => $session_id,
                    ':seat_key' => $sk,
                    ':user_id' => $user_id,
                    ':expires_at' => $expires_at,
                    ':meta' => json_encode(['source' => 'bcc_online_payment'], JSON_UNESCAPED_UNICODE),
                ]);
            }

            // Создаём payment_session
            $order = bcc_generate_order();
            $merchRnId = bcc_generate_merch_rn_id();
            $desc = 'Tickets ' . $session['event_title'];
            if (mb_strlen($desc) > 225) {
                $desc = mb_substr($desc, 0, 222) . '...';
            }

            $ins = $pdo->prepare("INSERT INTO payment_sessions
                (session_id, event_id, hall_id, order_number, merch_rn_id, amount_cents, currency, status, provider, customer_name, customer_phone, customer_email, seats_payload)
                VALUES
                (:session_id, :event_id, :hall_id, :order_number, :merch_rn_id, :amount_cents, :currency, 'pending', 'bcc', :customer_name, :customer_phone, :customer_email, :seats_payload)");
            $ins->execute([
                ':session_id' => $session_id,
                ':event_id' => $event_id,
                ':hall_id' => $hall_id,
                ':order_number' => $order,
                ':merch_rn_id' => $merchRnId,
                ':amount_cents' => $amountCents,
                ':currency' => 'KZT',
                ':customer_name' => $customer_name ?: null,
                ':customer_phone' => $normalizedPhone,
                ':customer_email' => $customer_email ?: null,
                ':seats_payload' => json_encode($seats, JSON_UNESCAPED_UNICODE),
            ]);
            $payment_session_id = (int)$pdo->lastInsertId();

            $cfg = bcc_config($pdo);
            $clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $form = bcc_build_payment_form($cfg, $order, $merchRnId, $amountCents, $desc, $clientIp, $customer_phone);

            $pdo->commit();

            json_response([
                'success' => true,
                'payment_session_id' => $payment_session_id,
                'order' => $order,
                'action' => $form['action'],
                'fields' => $form['fields'],
            ]);
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('create_bcc_session error: ' . $e->getMessage());
            json_response(['success' => false, 'message' => 'Failed to create payment session']);
        }
        break;

    case 'check_status':
        $order = isset($input['order']) ? trim((string)$input['order']) : '';
        if ($order === '') {
            json_response(['success' => false, 'message' => 'ORDER required']);
        }
        try {
            $stmt = $pdo->prepare("SELECT id, status, provider_response, ticket_uids, amount_cents FROM payment_sessions WHERE order_number = :order LIMIT 1");
            $stmt->execute([':order' => $order]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                json_response(['success' => false, 'message' => 'Payment session not found']);
            }
            json_response([
                'success' => true,
                'status' => $row['status'],
                'provider_response' => $row['provider_response'] ? json_decode($row['provider_response'], true) : null,
                'ticket_uids' => $row['ticket_uids'] ? json_decode($row['ticket_uids'], true) : [],
                'amount_cents' => (int)$row['amount_cents'],
            ]);
        } catch (Exception $e) {
            error_log('check_status error: ' . $e->getMessage());
            json_response(['success' => false, 'message' => 'Status check error']);
        }
        break;

    default:
        json_response(['success' => false, 'message' => 'Unknown action']);
}
