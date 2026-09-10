<?php
// payment/bcc/notify.php
// Обработчик callback-уведомлений от BCC

require_once __DIR__ . '/../../init.php';
require_once __DIR__ . '/../../includes/payment/bcc.php';

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

function write_log($msg)
{
    error_log('[BCC NOTIFY] ' . $msg);
}

function json_response($data, $statusCode = 200)
{
    http_response_code((int)$statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    global $pdo;
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        $pdo = db_connect();
    }
}

$cfg = bcc_config($pdo);

// Проверка Basic Auth (если настроена)
$basicAuthEnabled = !empty($cfg['notify_basic_auth_enabled']);
$expectedLogin = trim((string)($cfg['notify_login'] ?? ''));
$expectedPassword = trim((string)($cfg['notify_password'] ?? ''));
if ($basicAuthEnabled) {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (strpos($authHeader, 'Basic ') === 0) {
        $decoded = base64_decode(substr($authHeader, 6));
        $parts = explode(':', $decoded, 2);
        $login = $parts[0] ?? '';
        $password = $parts[1] ?? '';
        if (!hash_equals($expectedLogin, $login) || !hash_equals($expectedPassword, $password)) {
            write_log('Basic auth failed');
            http_response_code(401);
            exit('Unauthorized');
        }
    } else {
        write_log('Missing Basic auth');
        http_response_code(401);
        header('WWW-Authenticate: Basic realm="BCC Notify"');
        exit('Unauthorized');
    }
}

$data = $_POST;
if (empty($data) && !empty($_GET)) {
    $data = $_GET;
}
if (empty($data)) {
    $raw = file_get_contents('php://input');
    if ($raw) {
        parse_str($raw, $data);
    }
}

write_log('Incoming: ' . json_encode($data, JSON_UNESCAPED_UNICODE));

$parsed = bcc_parse_response($data);
$order = $parsed['order'];

if ($order === '') {
    write_log('ORDER missing');
    json_response(['success' => false, 'message' => 'ORDER missing']);
}

$stmt = $pdo->prepare("SELECT id, session_id, event_id, hall_id, status, seats_payload, customer_phone, customer_name, customer_email FROM payment_sessions WHERE order_number = :order LIMIT 1");
$stmt->execute([':order' => $order]);
$session = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$session) {
    write_log('Payment session not found: ' . $order);
    json_response(['success' => false, 'message' => 'Session not found']);
}

if ((string)($data['TRTYPE'] ?? '') === '14') {
    require_once __DIR__ . '/../../includes/payment/bcc_refund.php';
    if (trim((string)($data['P_SIGN'] ?? '')) === '') {
        write_log('Refund callback signature missing: ' . $order);
        json_response(['success' => false, 'message' => 'Invalid refund callback'], 400);
    }
    try {
        $refundSuccess = bcc_refund_response_success($data);
        $refundResult = bcc_mark_refund_result($pdo, $order, $data, $refundSuccess, 'bcc_refund_notify');
        json_response([
            'success' => true,
            'message' => 'Refund callback received',
            'state' => $refundResult['status'],
        ]);
    } catch (Throwable $e) {
        write_log('Refund callback error: ' . $e->getMessage());
        json_response(['success' => false, 'message' => 'Refund callback error'], 500);
    }
}

// Не принимаем короткие или несоответствующие заказу callback-запросы.
$requiredFields = ['ACTION', 'RC', 'P_SIGN', 'AMOUNT', 'CURRENCY', 'MERCHANT', 'TERMINAL', 'TIMESTAMP', 'NONCE'];
foreach ($requiredFields as $field) {
    if (!isset($data[$field]) || trim((string)$data[$field]) === '') {
        write_log('Required response field missing: ' . $field . ' for ' . $order);
        json_response(['success' => false, 'message' => 'Invalid callback'], 400);
    }
}

$expectedAmount = number_format(((int)$session['amount_cents']) / 100, 2, '.', '');
if ((string)$data['MERCHANT'] !== trim((string)($cfg['merchant'] ?? ''))
    || (string)$data['TERMINAL'] !== trim((string)($cfg['terminal'] ?? ''))
    || (string)$data['CURRENCY'] !== '398'
    || number_format((float)$data['AMOUNT'], 2, '.', '') !== $expectedAmount
    || !bcc_validate_response_signature($data, $cfg)) {
    write_log('Callback validation failed for ' . $order);
    json_response(['success' => false, 'message' => 'Invalid callback'], 400);
}

if (!$parsed['success']) {
    $upd = $pdo->prepare("UPDATE payment_sessions SET status = 'failed', provider_response = :response, notify_received_at = NOW(), updated_at = NOW() WHERE id = :id");
    $upd->execute([
        ':response' => json_encode($data, JSON_UNESCAPED_UNICODE),
        ':id' => $session['id'],
    ]);
    json_response(['success' => true, 'message' => 'Received failed payment']);
}

require_once __DIR__ . '/../../includes/payment/bcc_finalize.php';
try {
    $result = bcc_finalize_payment_session($pdo, $session, $data, 'bcc_notify');
    write_log('Tickets finalized for order ' . $order);
    json_response(['success' => true, 'message' => 'Received', 'ticket_uids' => $result['ticket_uids']]);
} catch (Throwable $e) {
    write_log('Ticket finalization error: ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'Ticket creation error'], 500);
}
