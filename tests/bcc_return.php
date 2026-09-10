<?php
// tests/bcc_return.php
// Публичная BACKREF-страница возврата из BCC 3DS
// НЕ требует авторизации, чтобы банк мог сюда вернуть клиента

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/payment/bcc.php';

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
$status = $parsed['success'] ? 'paid' : 'failed';
$message = $parsed['success'] ? 'Оплата прошла успешно!' : 'Оплата не была завершена. Пожалуйста, попробуйте снова.';

if ($order !== '' && $pdo instanceof PDO) {
    $stmt = $pdo->prepare("SELECT id, status, ticket_uids, amount_cents, session_id FROM payment_sessions WHERE order_number = :order LIMIT 1");
    $stmt->execute([':order' => $order]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($session) {
        $newStatus = $parsed['success'] ? $session['status'] : 'failed';
        $upd = $pdo->prepare("UPDATE payment_sessions SET return_visited_at = NOW(), provider_response = :response, status = CASE WHEN status = 'paid' THEN status ELSE :new_status END, updated_at = NOW() WHERE id = :id");
        $upd->execute([
            ':response' => json_encode($data, JSON_UNESCAPED_UNICODE),
            ':new_status' => $newStatus,
            ':id' => $session['id'],
        ]);

        $stmt2 = $pdo->prepare("SELECT status, ticket_uids, amount_cents FROM payment_sessions WHERE id = :id LIMIT 1");
        $stmt2->execute([':id' => $session['id']]);
        $session = $stmt2->fetch(PDO::FETCH_ASSOC);

        if ($session && (string)$session['status'] === 'paid') {
            $token = ticket_public_token($order);
            if ($token !== '') {
                header('Location: /tickets/public.php?order=' . rawurlencode($order) . '&token=' . rawurlencode($token));
                exit;
            }
            $status = 'paid';
            $message = 'Оплата прошла успешно! Ваши билеты оформлены.';
        } elseif ($parsed['success']) {
            $status = 'pending';
            $message = 'Оплата принята банком. Билеты появятся после подтверждения — обычно в течение нескольких секунд.';
        }
    }
}

?><!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Результат оплаты — <?= h(APP_NAME ?? 'Zhas Sahna') ?></title>
  <link rel="stylesheet" href="/assets/css/admin.css">
  <style>
    body { background: #f5f6f8; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
    .result-card { background: #fff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,.08); max-width: 520px; width: 100%; padding: 40px 32px; text-align: center; }
    .icon { width: 72px; height: 72px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 36px; margin-bottom: 16px; }
    .icon.success { background: #d1fae5; color: #065f46; }
    .icon.pending { background: #fef3c7; color: #92400e; }
    .icon.fail { background: #fee2e2; color: #991b1b; }
    .order { color: #6b7280; margin: 12px 0; }
    .actions { margin-top: 24px; }
    .actions a { display: inline-block; margin: 6px; }
    pre.debug { text-align: left; background: #1f2937; color: #e5e7eb; padding: 16px; border-radius: 8px; overflow: auto; margin-top: 24px; font-size: 12px; }
  </style>
</head>
<body>
  <div class="result-card">
    <div class="icon <?= $status === 'paid' ? 'success' : ($status === 'pending' ? 'pending' : 'fail') ?>">
      <?= $status === 'paid' ? '✓' : ($status === 'pending' ? '⏳' : '✕') ?>
    </div>
    <h2><?= h($message) ?></h2>
    <?php if ($order !== ''): ?>
      <p class="order">Номер заказа: <strong><?= h($order) ?></strong></p>
    <?php endif; ?>
    <?php if ($session): ?>
      <p>Сумма: <strong><?= number_format($session['amount_cents'] / 100, 2) ?> ₸</strong></p>
    <?php endif; ?>
    <div class="actions">
      <?php if ($status === 'paid' && !empty($session['ticket_uids'])):
        $ticketUids = json_decode($session['ticket_uids'], true);
        if (is_array($ticketUids) && !empty($ticketUids)):
            $publicToken = ticket_public_token($order);
            $ticketLink = $publicToken !== ''
                ? '/tickets/public.php?order=' . rawurlencode($order) . '&token=' . rawurlencode($publicToken)
                : '/tickets/view.php?uids=' . h(implode(',', $ticketUids));
        ?>
          <a href="<?= h($ticketLink) ?>" class="btn btn-success">Посмотреть билеты</a>
        <?php endif;
      elseif ($status === 'pending' && $order !== ''):
          $publicToken = ticket_public_token($order);
          if ($publicToken !== ''): ?>
            <a href="/tickets/public.php?order=<?= rawurlencode($order) ?>&token=<?= rawurlencode($publicToken) ?>" class="btn btn-warning">Открыть страницу заказа</a>
          <?php endif;
      endif; ?>
      <a href="/tests/bcc_payment.php" class="btn btn-primary">Вернуться к тестовой странице</a>
      <a href="/" class="btn btn-outline">На главную</a>
    </div>
    <?php if (defined('DEBUG') && DEBUG && !empty($data)): ?>
      <pre class="debug"><code><?= h(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></code></pre>
    <?php endif; ?>
  </div>
</body>
</html>
