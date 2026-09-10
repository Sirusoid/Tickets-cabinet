<?php
// payment/bcc/return.php
// BACKREF-страница: возвращаем клиента сюда после оплаты

$skip_require_login = true;
require_once __DIR__ . '/../../init.php';
require_once __DIR__ . '/../../includes/payment/bcc.php';

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

$data = $_POST;
if (empty($data) && !empty($_GET)) {
    $data = $_GET;
}

$parsed = bcc_parse_response($data);
$order = $parsed['order'];

if (!isset($pdo) || !($pdo instanceof PDO)) {
    global $pdo;
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        $pdo = db_connect();
    }
}

$session = null;
$status = 'failed';
$message = $parsed['success']
    ? 'Не удалось найти заказ после ответа банка.'
    : 'Оплата не была завершена. Пожалуйста, попробуйте снова.';

if ($order !== '' && $pdo instanceof PDO) {
    $stmt = $pdo->prepare("SELECT id, session_id, event_id, hall_id, status, amount_cents, seats_payload, ticket_uids, customer_phone, customer_name, customer_email
        FROM payment_sessions WHERE order_number = :order LIMIT 1");
    $stmt->execute([':order' => $order]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($session) {
        $paymentSessionId = (int)($session['id'] ?? 0);
        // В return.php НЕ меняем статус на paid — это делает только notify.
        // Фиксируем факт возврата клиента и неудачный статус, если банк явно сообщил об отказе.
        $newStatus = $parsed['success'] ? $session['status'] : 'failed';
        $upd = $pdo->prepare("UPDATE payment_sessions SET return_visited_at = NOW(), provider_response = :response, status = CASE WHEN status = 'paid' THEN status ELSE :new_status END, updated_at = NOW() WHERE id = :id");
        $upd->execute([
            ':response' => json_encode($data, JSON_UNESCAPED_UNICODE),
            ':new_status' => $newStatus,
            ':id' => $paymentSessionId,
        ]);

        // BACKREF и NOTIFY приходят независимо друг от друга. Страница заказа
        // сама дождётся NOTIFY и обновит данные внутри текущего iframe.
        $stmt2 = $pdo->prepare("SELECT id, session_id, event_id, hall_id, status, amount_cents, seats_payload, ticket_uids, customer_phone, customer_name, customer_email
            FROM payment_sessions WHERE id = :id LIMIT 1");
        $stmt2->execute([':id' => $paymentSessionId]);
        $session = $stmt2->fetch(PDO::FETCH_ASSOC);

        if ($parsed['success']) {
            $cfg = bcc_config($pdo);
            $requiredFields = ['ACTION', 'RC', 'P_SIGN', 'AMOUNT', 'CURRENCY', 'MERCHANT', 'TERMINAL', 'TIMESTAMP', 'NONCE'];
            $hasRequiredFields = true;
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || trim((string)$data[$field]) === '') {
                    $hasRequiredFields = false;
                    break;
                }
            }
            $expectedAmount = number_format(((int)$session['amount_cents']) / 100, 2, '.', '');
            $responseMatchesOrder = (string)($data['MERCHANT'] ?? '') === trim((string)($cfg['merchant'] ?? ''))
                && (string)($data['TERMINAL'] ?? '') === trim((string)($cfg['terminal'] ?? ''))
                && (string)($data['CURRENCY'] ?? '') === '398'
                && number_format((float)($data['AMOUNT'] ?? 0), 2, '.', '') === $expectedAmount
                && bcc_validate_response_signature($data, $cfg);

            if ($hasRequiredFields && $responseMatchesOrder) {
                // В тестовом контуре BCC иногда не отправляет NOTIFY_URL.
                // Завершаем заказ напрямую, без внутреннего HTTP-вызова к самому себе.
                require_once __DIR__ . '/../../includes/payment/bcc_finalize.php';
                try {
                    bcc_finalize_payment_session($pdo, $session, $data, 'bcc_backref');
                } catch (Throwable $e) {
                    error_log('[BCC RETURN] Ticket finalization error: ' . $e->getMessage());
                }
                $status = 'paid';
                $message = 'Оплата прошла успешно!';
            } else {
                $status = 'failed';
                $message = 'Ответ банка не прошёл проверку.';
            }

            if ($responseMatchesOrder && $status === 'paid') {
                $token = ticket_public_token($order);
                if ($token !== '') {
                    header('Location: /tickets/public.php?order=' . rawurlencode($order) . '&token=' . rawurlencode($token));
                    exit;
                }
            }
        }
    }
}

?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Результат оплаты</title>
    <style>
        :root { color-scheme: light; font-family: Arial, sans-serif; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #f3f4f6; color: #111827; }
        .payment-result { width: min(520px, calc(100% - 32px)); padding: 32px 24px; text-align: center; background: #fff; border-radius: 16px; box-shadow: 0 12px 36px rgba(15, 23, 42, .12); box-sizing: border-box; }
        .payment-result__icon { font-size: 44px; margin-bottom: 12px; }
        .payment-result__title { margin: 0 0 12px; font-size: 22px; }
        .payment-result__text { margin: 0; color: #6b7280; line-height: 1.5; }
        .payment-result__button { display: inline-block; margin-top: 24px; padding: 11px 18px; border-radius: 9px; background: #2563eb; color: #fff; text-decoration: none; }
    </style>
</head>
<body>
    <main class="payment-result">
        <div class="payment-result__icon"><?= $status === 'paid' ? '✓' : '✕' ?></div>
        <h1 class="payment-result__title"><?= h($message) ?></h1>
        <?php if ($order !== ''): ?>
            <p class="payment-result__text">Номер заказа: <strong><?= h($order) ?></strong></p>
        <?php endif; ?>
        <?php if ($session): ?>
            <p class="payment-result__text">Сумма: <strong><?= number_format((int)$session['amount_cents'] / 100, 2, '.', ' ') ?> ₸</strong></p>
        <?php endif; ?>
        <a class="payment-result__button" href="https://zhassahna.kz/widget-test">Вернуться к покупке</a>
    </main>
</body>
</html>
