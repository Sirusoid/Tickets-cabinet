<?php
// tickets/public.php
// Публичная страница просмотра купленных билетов после онлайн-оплаты.
// Доступна без авторизации по order + token из BACKREF-ответа банка.

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/payment/bcc_refund.php';

$order = isset($_GET['order']) ? trim((string)$_GET['order']) : '';
$token = isset($_GET['token']) ? trim((string)$_GET['token']) : '';

$error = null;
$session = null;
$tickets = [];
$event = null;
$schedule = null;

if ($order === '' || $token === '' || !ticket_public_token_verify($order, $token)) {
    $error = 'Ссылка недействительна или устарела.';
} elseif (!isset($pdo) || !($pdo instanceof PDO)) {
    $error = 'Ошибка подключения к базе данных.';
} else {
    $stmt = $pdo->prepare("SELECT ps.*, e.title AS event_title, s.start_time AS schedule_start, h.name AS hall_name
        FROM payment_sessions ps
        LEFT JOIN events e ON e.id = ps.event_id
        LEFT JOIN schedules s ON s.id = ps.session_id
        LEFT JOIN halls h ON h.id = ps.hall_id
        WHERE ps.order_number = :order LIMIT 1");
    $stmt->execute([':order' => $order]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$session) {
        $error = 'Заказ не найден.';
    }
}

if (!$error && $session && $pdo instanceof PDO) {
    if ((string)$session['status'] === 'paid') {
        $uids = json_decode($session['ticket_uids'] ?? '[]', true);
        if (is_array($uids) && !empty($uids)) {
            $placeholders = implode(',', array_fill(0, count($uids), '?'));
            $stmt = $pdo->prepare("SELECT t.id, t.ticket_uid, t.seat_identifier, t.price, t.customer_segment, t.channel,
                        t.status, t.payment_status, t.refund_status, t.refund_at, t.purchased_at,
                        COALESCE(c.full_name, '') AS customer_name,
                        COALESCE(e.title, '') AS event_title,
                        s.start_time AS schedule_start,
                        h.name AS hall_name
                    FROM tickets t
                    LEFT JOIN customers c ON c.id = t.customer_id
                    LEFT JOIN events e ON e.id = t.event_id
                    LEFT JOIN schedules s ON s.id = t.schedule_id
                    LEFT JOIN halls h ON h.id = t.hall_id
                    WHERE t.ticket_uid IN ($placeholders)
                    ORDER BY t.id ASC");
            $stmt->execute($uids);
            $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
}

$page_title_meta = 'Ваши билеты / Сіздің билеттеріңіз';
$skip_require_login = true;
$use_sidebar = false;
$hide_admin_header = true;
$publicSiteUrl = defined('TILDA_WIDGET_ORIGIN') ? (string)TILDA_WIDGET_ORIGIN : 'https://zhassahna.kz';
$refundInfo = ['state' => 'unavailable', 'message' => '', 'deadline' => null];
if (!$error && $session && $pdo instanceof PDO && (string)$session['status'] === 'paid') {
    $refundInfo = bcc_self_refund_status($pdo, $session, $tickets);
}

$formatPublicDate = static function ($datetime) {
    if (!$datetime) return '';
    try {
        $date = new DateTime((string)$datetime);
        $weekdays = ['вс.', 'пн.', 'вт.', 'ср.', 'чт.', 'пт.', 'сб.'];
        $months = ['января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];
        return $weekdays[(int)$date->format('w')] . ' ' . $date->format('j') . ' ' . $months[(int)$date->format('n') - 1] . ' ' . $date->format('Y') . ' г.';
    } catch (Throwable $exception) {
        return '';
    }
};

$formatPublicTime = static function ($datetime) {
    if (!$datetime) return '';
    try {
        return (new DateTime((string)$datetime))->format('H:i');
    } catch (Throwable $exception) {
        return '';
    }
};

$formatPublicPhone = static function ($phone) {
    return format_customer_phone($phone);
};

$formatPublicSeat = static function ($identifier) {
    $parts = preg_split('/\s*[-:\/]\s*/', trim((string)$identifier), 2);
    if (count($parts) === 2 && $parts[0] !== '' && $parts[1] !== '') {
        return ['row' => $parts[0], 'seat' => $parts[1]];
    }
    return ['row' => '', 'seat' => (string)$identifier];
};

$publicEventTitle = mb_strtoupper(trim((string)($session['event_title'] ?? 'Спектакль')), 'UTF-8');
$publicDate = $formatPublicDate($session['schedule_start'] ?? null);
$publicTime = $formatPublicTime($session['schedule_start'] ?? null);
$publicPhone = $formatPublicPhone($session['customer_phone'] ?? '');
$publicErrorText = $error;
if ($error === 'Ссылка недействительна или устарела.') $publicErrorText = 'Ссылка недействительна или устарела. / Сілтеме жарамсыз немесе мерзімі өткен.';
if ($error === 'Ошибка подключения к базе данных.') $publicErrorText = 'Ошибка подключения к базе данных. / Дерекқорға қосылу қатесі.';
if ($error === 'Заказ не найден.') $publicErrorText = 'Заказ не найден. / Тапсырыс табылмады.';
$refundDeadlineLabel = '';
if (!empty($refundInfo['deadline'])) {
    $refundDeadlineLabel = $formatPublicDate($refundInfo['deadline']) . ' ' . $formatPublicTime($refundInfo['deadline']);
}
$refundNoteRu = 'Самостоятельно оформить возврат возможно до указанной даты. В случае отмены покупки после указанной даты обращайтесь по номерам телефонов: +7 776 711 78 78 или +7 727 259 65 98. Будьте готовы сообщить контактные данные, указанные при оформлении (имя, номер телефона, e-mail), а также № заказа и № билета.';
$refundNoteKz = 'Өздігінен қайтаруды көрсетілген күнге дейін рәсімдеуге болады. Сатып алудан бас тарту көрсетілген күннен кейін қажет болса, мына телефон нөмірлеріне хабарласыңыз: +7 776 711 78 78 немесе +7 727 259 65 98. Өтінімді рәсімдеу кезінде көрсетілген байланыс деректерін (аты-жөні, телефон нөмірі, e-mail), сондай-ақ тапсырыс нөмірі мен билет нөмірін хабарлауға дайын болыңыз.';

require_once __DIR__ . '/../includes/header.php';
?>

<style>
    .order-bilingual { display:inline-flex; flex-wrap:wrap; gap:4px 10px; align-items:baseline; }
    .order-bilingual__kz { color:#6b7280; font-weight:400; }
    .order-bilingual--block { display:flex; flex-direction:column; gap:2px; }
    .order-note { text-align:left; }
    .ticket-card-layout { display:grid; grid-template-columns:minmax(0,1fr) 150px auto; gap:18px; align-items:center; }
    .ticket-card-info { min-width:0; }
    .ticket-card-actions { display:flex; gap:8px; flex-wrap:wrap; justify-content:flex-end; }
    .ticket-pdf-button { font-weight:700; border:1px solid #2563eb; color:#1d4ed8; background:#eff6ff; }
    .ticket-pdf-button:hover { background:#dbeafe; border-color:#1d4ed8; }
    .ticket-action-label { display:inline-flex; flex-direction:column; line-height:1.15; text-align:center; }
    @media (max-width: 700px) {
        .ticket-card-layout { grid-template-columns:minmax(0,1fr) 116px; }
        .ticket-card-actions { grid-column:1 / -1; justify-content:flex-start; }
    }
    @media print {
        .ticket-card-layout { grid-template-columns:minmax(0,1fr) 130px; gap:12px; }
        .ticket-card-info { min-width:0; }
        .ticket-card-actions { display:none !important; }
    }
</style>

<div class="page container-full" style="max-width: 900px; margin: 0 auto; padding: 24px 16px;">
    <?php if ($error): ?>
        <div class="card" style="text-align:center; padding: 40px 24px;">
            <div style="font-size: 48px; margin-bottom: 12px;">⚠</div>
            <h2 class="order-bilingual order-bilingual--block"><span>Не удалось открыть страницу</span><span class="order-bilingual__kz">Бетті ашу мүмкін болмады</span></h2>
            <p style="color:#6b7280;"><?= h($publicErrorText) ?></p>
            <a href="<?= h($publicSiteUrl) ?>/widget-test" class="btn btn-primary mt-3">Вернуться к покупке / Сатып алуға оралу</a>
        </div>
    <?php elseif (!$session || (string)$session['status'] !== 'paid'): ?>
        <div class="card" style="text-align:center; padding: 40px 24px;" data-order="<?= h($order) ?>" id="publicPendingCard">
            <div style="font-size: 48px; margin-bottom: 12px;">⏳</div>
            <h2 class="order-bilingual order-bilingual--block"><span>Оплата обрабатывается</span><span class="order-bilingual__kz">Төлем өңделуде</span></h2>
            <p style="color:#6b7280;">
                Заказ / Тапсырыс: <strong><?= h($order) ?></strong><br>
                Заказ пока не подтверждён банком. / Тапсырыс банк тарапынан әлі расталған жоқ.<br>
                Как только платёж пройдёт, здесь появятся ваши билеты. / Төлем расталғаннан кейін билеттеріңіз осында пайда болады.
            </p>
            <p style="color:#6b7280;" id="publicPendingHint">Страница обновится автоматически. / Бет автоматты түрде жаңартылады.</p>
            <a href="<?= h($publicSiteUrl) ?>/widget-test" class="btn btn-primary mt-3">Вернуться к покупке / Сатып алуға оралу</a>
        </div>
    <?php else: ?>
        <div class="card" style="padding: 28px; margin-bottom: 20px;">
            <div style="text-align:center; margin-bottom: 24px;">
                <div style="font-size: 48px; margin-bottom: 8px;">✓</div>
                <h2 class="order-bilingual order-bilingual--block" style="margin-bottom: 6px;"><span>Оплата прошла успешно!</span><span class="order-bilingual__kz">Төлем сәтті өтті!</span></h2>
                <p style="color:#6b7280; margin: 0;">
                    Заказ / Тапсырыс <strong><?= h($order) ?></strong> ·
                    Сумма / Сома <strong><?= number_format((int)$session['amount_cents'] / 100, 2, '.', ' ') ?> ₸</strong>
                </p>
            </div>

            <div style="background:#f9fafb; border-radius:8px; padding: 16px; margin-bottom: 24px;">
                <div style="font-weight:700; font-size:16px; margin-bottom:8px;">«<?= h($publicEventTitle) ?>»</div>
                <?php if ($publicDate !== ''): ?>
                    <div style="font-weight:700; color:#111827;"><?= h($publicDate) ?></div>
                <?php endif; ?>
                <?php if ($publicTime !== ''): ?>
                    <div style="font-weight:700; color:#111827; margin-top:2px;"><?= h($publicTime) ?></div>
                <?php endif; ?>
                <?php if (!empty($session['hall_name'])): ?>
                    <div style="color:#4b5563; margin-top:4px;"><?= h($session['hall_name']) ?></div>
                <?php endif; ?>
                <?php if (!empty($session['customer_name'])): ?>
                    <div style="color:#4b5563; margin-top:4px;">Клиент / Клиент: <?= h($session['customer_name']) ?></div>
                <?php endif; ?>
                <?php if ($publicPhone !== ''): ?>
                    <div style="color:#4b5563;">Телефон / Телефон: <?= h($publicPhone) ?></div>
                <?php endif; ?>
                <?php if (!empty($session['customer_email'])): ?>
                    <div style="color:#4b5563;">Email / E-mail: <?= h($session['customer_email']) ?></div>
                <?php endif; ?>
            </div>

            <div style="display:flex; flex-wrap:wrap; gap: 10px; margin-bottom: 20px;">
                <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">Печать / Басып шығару</button>
                <?php if ($refundInfo['state'] === 'allowed'): ?>
                    <button type="button" class="btn btn-danger btn-sm" id="selfRefundBtn">Отменить покупку / Сатып алудан бас тарту</button>
                    <span id="selfRefundHint" style="align-self:center; color:#6b7280; font-size:13px;">Возврат доступен до / Қайтару мерзімі: <?= h($refundDeadlineLabel) ?></span>
                <?php elseif ($refundInfo['state'] === 'processing'): ?>
                    <span id="selfRefundHint" class="alert alert-warning" style="margin:0; padding:8px 12px;">Запрос на возврат обрабатывается. / Қайтару сұрауы өңделуде.</span>
                <?php elseif ($refundInfo['state'] === 'refunded'): ?>
                    <span class="alert alert-success" style="margin:0; padding:8px 12px;">Возврат по заказу выполнен. / Тапсырыс қайтарылды.</span>
                <?php endif; ?>
            </div>

            <div style="margin:-8px 0 22px; padding:12px 14px; border-left:3px solid #d1d5db; background:#f9fafb; color:#6b7280; font-size:12px; line-height:1.5; font-style:italic;">
                <div><strong>Важно / Маңызды:</strong> <?= h($refundNoteRu) ?></div>
                <div style="margin-top:8px;"><?= h($refundNoteKz) ?></div>
            </div>

            <?php if (empty($tickets)): ?>
                <div class="alert alert-warning">
                    Билеты в базе пока не обнаружены, но платёж зафиксирован.<br>
                    Билеттер базада әлі табылған жоқ, бірақ төлем тіркелді.<br>
                    Обновите страницу через несколько секунд или обратитесь в кассу театра.<br>
                    Бірнеше секундтан кейін бетті жаңартыңыз немесе театр кассасына хабарласыңыз.
                </div>
            <?php else: ?>
                <h3 class="order-bilingual" style="margin-bottom: 16px;"><span>Ваши билеты</span><span class="order-bilingual__kz">Сіздің билеттеріңіз</span></h3>
                <div class="tickets-list">
                    <?php foreach ($tickets as $ticket):
                        $uid = (string)$ticket['ticket_uid'];
                        if ($uid === '') continue;
                        $pdfToken = ticket_public_token('pdf:' . $uid);
                        $pdfUrl = '/tickets/generate.php?uid=' . rawurlencode($uid) . '&t=' . rawurlencode($pdfToken);
                        $publicSeat = $formatPublicSeat($ticket['seat_identifier'] ?? '');
                    ?>
                        <?php $qrDataUri = function_exists('ticket_qr_png_data_uri') ? ticket_qr_png_data_uri($uid, 5, 2) : null; ?>
                        <div class="card" style="padding: 18px; margin-bottom: 16px; border: 1px solid #e5e7eb;">
                            <div class="ticket-card-layout">
                                <div class="ticket-card-info">
                                    <div style="font-size:15px; font-weight:700; margin-bottom:4px;">Билет № / Билет № <?= h($uid) ?></div>
                                    <div style="color:#4b5563;">
                                        <?php if ($publicSeat['row'] !== ''): ?><strong>Ряд: <?= h($publicSeat['row']) ?></strong><br><?php endif; ?>
                                        <strong>Место: <?= h($publicSeat['seat']) ?></strong>
                                    </div>
                                    <div style="color:#4b5563;">Цена / Бағасы: <?= number_format((float)$ticket['price'], 2, '.', ' ') ?> ₸</div>
                                    <?php if (($ticket['refund_status'] ?? 'none') === 'refunded'): ?>
                                        <div style="color:#b91c1c; font-weight:600;">Билет возвращён / Билет қайтарылды</div>
                                    <?php endif; ?>
                                    <?php if (!empty($ticket['customer_name'])): ?>
                                        <div style="color:#4b5563;">Клиент / Клиент: <?= h($ticket['customer_name']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div style="display:flex; justify-content:center; align-items:center; min-height:130px;">
                                    <?php if ($qrDataUri): ?>
                                        <img src="<?= h($qrDataUri) ?>" alt="QR <?= h($uid) ?>" style="width:128px; height:128px; padding:6px; box-sizing:border-box; border:1px solid #e5e7eb; border-radius:8px; background:#fff;">
                                    <?php endif; ?>
                                </div>
                                <div class="ticket-card-actions">
                                    <a href="<?= h($pdfUrl) ?>" class="btn btn-ghost btn-sm ticket-pdf-button"><span class="ticket-action-label"><span>Открыть PDF</span><span>PDF ашу</span></span></a>
                                    <a href="<?= h($pdfUrl . '&download=1') ?>" class="btn btn-secondary btn-sm"><span class="ticket-action-label"><span>Скачать</span><span>Жүктеу</span></span></a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div style="text-align:center; margin-top: 24px;">
                <a href="#" class="btn btn-primary" id="backToTildaBtn">Вернуться на сайт / Сайтқа оралу</a>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
(function () {
    'use strict';
    const pendingCard = document.getElementById('publicPendingCard');
    if (pendingCard) {
        const order = pendingCard.dataset.order;
        let attempts = 0;
        const maxAttempts = 36; // 3 минуты
        const timer = setInterval(function () {
            attempts++;
            if (attempts > maxAttempts) {
                clearInterval(timer);
                const hint = document.getElementById('publicPendingHint');
                if (hint) hint.textContent = 'Обновите страницу вручную или обратитесь в кассу. / Бетті жаңартыңыз немесе театр кассасына хабарласыңыз.';
                return;
            }
            fetch('/ajax/public_payment.php?action=check_status', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ order: order })
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success && data.status === 'paid') {
                    clearInterval(timer);
                    window.location.reload();
                }
            })
            .catch(function () {});
        }, 5000);
    }

    const backToTildaBtn = document.getElementById('backToTildaBtn');
    if (backToTildaBtn) {
        backToTildaBtn.addEventListener('click', function (e) {
            e.preventDefault();
            var tildaOrigin = <?= json_encode(defined('TILDA_WIDGET_ORIGIN') ? constant('TILDA_WIDGET_ORIGIN') : 'https://zhassahna.kz') ?>;
            var order = <?= json_encode($order) ?>;
            var amount = <?= json_encode(number_format((int)$session['amount_cents'] / 100, 2, '.', ' ')) ?>;
            var tickets = <?= json_encode(array_map(function ($t) {
                return [
                    'uid' => $t['ticket_uid'],
                    'seat' => $t['seat_identifier'],
                    'price' => number_format((float)$t['price'], 2, '.', ' ')
                ];
            }, $tickets)) ?>;
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({
                    type: 'zhassahna:payment:success',
                    order: order,
                    amount: amount,
                    tickets: tickets
                }, tildaOrigin);
            }
            window.location.href = tildaOrigin + '/?order=' + encodeURIComponent(order);
        });
    }

    const selfRefundBtn = document.getElementById('selfRefundBtn');
    const selfRefundHint = document.getElementById('selfRefundHint');
    if (selfRefundBtn) {
        selfRefundBtn.addEventListener('click', function () {
            if (!window.confirm('Отменить покупку и оформить возврат всех билетов заказа? / Сатып алудан бас тартып, барлық билеттерге қайтарым жасайсыз ба?')) return;
            selfRefundBtn.disabled = true;
            selfRefundBtn.textContent = 'Отправка запроса... / Сұрау жіберілуде...';
            fetch('/ajax/public_refund.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    order: <?= json_encode($order) ?>,
                    token: <?= json_encode($token) ?>
                })
            })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (!data.success && data.state !== 'processing') {
                        throw new Error((data.message || 'Не удалось отправить запрос на возврат.') + ' / Қайтару сұрауын жіберу мүмкін болмады.');
                    }
                    if (selfRefundHint) selfRefundHint.textContent = (data.message || 'Запрос на возврат обрабатывается.') + ' / Қайтару сұрауы өңделуде.';
                    selfRefundBtn.remove();
                    window.setTimeout(function () { window.location.reload(); }, 2500);
                })
                .catch(function (error) {
                    selfRefundBtn.disabled = false;
                    selfRefundBtn.textContent = 'Отменить покупку / Сатып алудан бас тарту';
                    if (selfRefundHint) selfRefundHint.textContent = error.message;
                });
        });
    }
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
