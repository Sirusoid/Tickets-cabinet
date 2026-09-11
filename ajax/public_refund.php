<?php
// ajax/public_refund.php
// Публичный возврат заказа через BCC TRTYPE=14.

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/payment/bcc_refund.php';

@ini_set('display_errors', '0');
error_reporting(E_ALL);

$allowedOrigin = defined('TILDA_WIDGET_ORIGIN') ? (string)constant('TILDA_WIDGET_ORIGIN') : 'https://zhassahna.kz';
header('Access-Control-Allow-Origin: ' . $allowedOrigin);
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
header('Content-Type: application/json; charset=utf-8');

function public_refund_response(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = file_get_contents('php://input');
$input = [];
if ($raw !== '') {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $input = $decoded;
    }
}
if (!empty($_POST)) {
    $input = array_merge($input, $_POST);
}

$action = trim((string)($input['action'] ?? $_GET['action'] ?? 'request'));
$order = trim((string)($input['order'] ?? ''));
$token = trim((string)($input['token'] ?? ''));

if ($order === '' || $token === '' || !ticket_public_token_verify($order, $token)) {
    public_refund_response(['success' => false, 'message' => 'Ссылка на заказ недействительна.'], 403);
}
if (!isset($pdo) || !($pdo instanceof PDO)) {
    public_refund_response(['success' => false, 'message' => 'База данных недоступна.'], 500);
}
if (!($pdo instanceof PDO)) {
    public_refund_response(['success' => false, 'message' => 'База данных недоступна.'], 500);
}
/** @var PDO $pdo */

$sessionStmt = $pdo->prepare("SELECT ps.*, s.start_time AS schedule_start
    FROM payment_sessions ps
    LEFT JOIN schedules s ON s.id = ps.session_id
    WHERE ps.order_number = :order LIMIT 1");
$sessionStmt->execute([':order' => $order]);
$session = $sessionStmt->fetch(PDO::FETCH_ASSOC);
if (!$session) {
    public_refund_response(['success' => false, 'message' => 'Заказ не найден.'], 404);
}

$ticketStmt = $pdo->prepare("SELECT id, ticket_uid, price, refund_status, status
    FROM tickets WHERE payment_session_id = :payment_session_id ORDER BY id ASC");
$ticketStmt->execute([':payment_session_id' => (int)$session['id']]);
$tickets = $ticketStmt->fetchAll(PDO::FETCH_ASSOC);
$statusInfo = bcc_self_refund_status($pdo, $session, $tickets);

if ($action === 'check') {
    public_refund_response([
        'success' => true,
        'state' => $statusInfo['state'],
        'message' => $statusInfo['message'],
        'deadline' => $statusInfo['deadline'],
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    public_refund_response(['success' => false, 'message' => 'Неверный метод запроса.'], 405);
}
if ($statusInfo['state'] !== 'allowed') {
    public_refund_response([
        'success' => false,
        'state' => $statusInfo['state'],
        'message' => $statusInfo['message'],
        'deadline' => $statusInfo['deadline'],
    ], 409);
}
if (empty($tickets)) {
    public_refund_response(['success' => false, 'message' => 'В заказе нет билетов.'], 400);
}

$originalResponse = json_decode((string)($session['provider_response'] ?? ''), true);
if (!is_array($originalResponse)) {
    $originalResponse = [];
}
if (trim((string)($originalResponse['RRN'] ?? '')) === '' || trim((string)($originalResponse['INT_REF'] ?? '')) === '') {
    public_refund_response(['success' => false, 'message' => 'В заказе отсутствуют данные банковской транзакции.'], 409);
}

try {
    $pdo->beginTransaction();

    $lockSession = $pdo->prepare("SELECT id, status FROM payment_sessions WHERE id = :id LIMIT 1 FOR UPDATE");
    $lockSession->execute([':id' => (int)$session['id']]);
    $lockedSession = $lockSession->fetch(PDO::FETCH_ASSOC);
    if (!$lockedSession || (string)$lockedSession['status'] !== 'paid') {
        throw new RuntimeException('Оплата заказа не подтверждена.');
    }

    $ticketIds = array_map('intval', array_column($tickets, 'id'));
    $placeholders = implode(',', array_fill(0, count($ticketIds), '?'));
    $existingRefund = $pdo->prepare("SELECT refund_status FROM refunds WHERE ticket_id IN ($placeholders) AND refund_status IN ('requested', 'refunded') LIMIT 1");
    $existingRefund->execute($ticketIds);
    if ($existingRefund->fetchColumn()) {
        throw new RuntimeException('Запрос на возврат по этому заказу уже создан.');
    }

    $insertRefund = $pdo->prepare("INSERT INTO refunds (ticket_id, ticket_uid, schedule_id, refund_amount, refund_status, refund_method, refund_provider, reason, created_at)
        VALUES (:ticket_id, :ticket_uid, :schedule_id, :refund_amount, 'requested', 'bank', 'bcc', :reason, NOW())");
    foreach ($tickets as $ticket) {
        $insertRefund->execute([
            ':ticket_id' => (int)$ticket['id'],
            ':ticket_uid' => $ticket['ticket_uid'],
            ':schedule_id' => (int)$session['session_id'],
            ':refund_amount' => number_format((float)$ticket['price'], 2, '.', ''),
            ':reason' => 'Самостоятельный возврат клиента',
        ]);
    }
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    public_refund_response(['success' => false, 'message' => $e->getMessage()], 409);
}

$cfg = bcc_config($pdo);
$refundForm = bcc_build_refund_form($cfg, $session, $originalResponse, (int)$session['amount_cents']);
$refundBody = http_build_query($refundForm['fields'], '', '&');

// Optional one-run capture for bank support; keep the file outside the web root.
$debugDumpPath = getenv('ZHASSAHNA_BCC_REFUND_DEBUG_FILE');
if (is_string($debugDumpPath) && trim($debugDumpPath) !== '') {
    $debugDumpPath = trim($debugDumpPath);
    $written = file_put_contents($debugDumpPath, $refundBody, LOCK_EX);
    if ($written === false) {
        error_log('[BCC REFUND] Could not write debug request dump: ' . $debugDumpPath);
    } else {
        chmod($debugDumpPath, 0600);
    }
}

$ch = curl_init($refundForm['action']);
if ($ch === false) {
    public_refund_response(['success' => true, 'state' => 'processing', 'message' => 'Запрос на возврат принят и обрабатывается.'], 202);
}

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $refundBody,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
]);
$body = curl_exec($ch);
$curlError = curl_error($ch);
curl_close($ch);

if ($body === false || $curlError !== '') {
    error_log('[BCC REFUND] Gateway request error for ' . $order . ': ' . $curlError);
    try {
        // Сетевой сбой означает, что BCC не подтвердил получение запроса.
        // Освобождаем заявку, чтобы возврат можно было повторить после восстановления банка.
        bcc_mark_refund_result($pdo, $order, [
            'ACTION' => '',
            'RC' => 'TRANSPORT_ERROR',
            'RC_TEXT' => $curlError !== '' ? $curlError : 'BCC gateway connection failed',
            'ORDER' => $order,
        ], false, 'bcc_refund_transport_error');
    } catch (Throwable $exception) {
        error_log('[BCC REFUND] Could not mark transport error for ' . $order . ': ' . $exception->getMessage());
    }
    public_refund_response([
        'success' => false,
        'state' => 'rejected',
        'code' => 'transport_error',
        'message' => 'Не удалось подключиться к банку. Возврат не отправлен, повторите попытку позже.',
    ], 502);
}

$responseData = bcc_parse_gateway_response((string)$body);
if (empty($responseData)) {
    public_refund_response([
        'success' => true,
        'state' => 'processing',
        'message' => 'Запрос на возврат принят. Банк подтвердит результат отдельно.',
    ], 202);
}

$success = bcc_refund_response_success($responseData);
$result = ['status' => 'processing'];
try {
    $result = bcc_mark_refund_result($pdo, $order, $responseData, $success, 'bcc_refund');
} catch (Throwable $e) {
    error_log('[BCC REFUND] Result processing error for ' . $order . ': ' . $e->getMessage());
    public_refund_response(['success' => false, 'message' => 'Не удалось обработать ответ банка.'], 500);
}

if (!$success) {
    public_refund_response([
        'success' => false,
        'state' => 'rejected',
        'message' => 'Банк не подтвердил возврат. ' . trim((string)($responseData['RC_TEXT'] ?? '')),
    ], 400);
}

public_refund_response([
    'success' => true,
    'state' => $result['status'],
    'message' => 'Запрос на возврат принят банком. Зачисление обычно выполняется после клиринга.',
]);
