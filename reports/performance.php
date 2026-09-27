<?php
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/reporting.php';
require_login();

$scheduleId = (int)($_GET['session_id'] ?? 0);
if ($scheduleId <= 0) {
    redirect('/reports/sales.php');
}

$session = db_fetch_one(
    "SELECT s.id, s.start_time, s.end_time, s.status, s.hall_id, s.seat_map,
            e.title AS event_title, h.name AS hall_name
     FROM schedules s
     LEFT JOIN events e ON e.id = s.event_id
     LEFT JOIN halls h ON h.id = s.hall_id
     WHERE s.id = ?
     LIMIT 1",
    [$scheduleId]
);

if (!$session) {
    http_response_code(404);
    exit('Сеанс не найден.');
}

$tickets = db_fetch_all(
    "SELECT
        t.id, t.ticket_uid, t.seat_identifier, t.customer_name, t.customer_segment,
        t.original_price, t.final_price, t.discount_amount, t.channel,
        t.payment_status, t.status, t.refund_status, t.is_checked_in,
        t.purchased_at,
        COALESCE(tx.payment_method, CASE WHEN t.channel IN ('web', 'mobile') THEN 'card' ELSE 'cash' END) AS payment_method,
        COALESCE(ps.order_number, '') AS order_number
     FROM tickets t
     LEFT JOIN cash_transactions tx ON tx.id = t.payment_transaction_id
     LEFT JOIN payment_sessions ps ON ps.id = t.payment_session_id
     WHERE t.schedule_id = ?
     ORDER BY t.purchased_at DESC, t.id DESC",
    [$scheduleId]
);

$capacity = 0;
try {
    $capacityRow = db_fetch_one('SELECT COUNT(*) AS total FROM seats WHERE hall_id = ?', [(int)$session['hall_id']]);
    $capacity = (int)($capacityRow['total'] ?? 0);
} catch (Throwable $exception) {
    $capacity = 0;
}

if ($capacity <= 0 && !empty($session['seat_map'])) {
    $seatMap = json_decode((string)$session['seat_map'], true);
    if (is_array($seatMap) && isset($seatMap['seats']) && is_array($seatMap['seats'])) {
        $capacity = count($seatMap['seats']);
    }
}

$soldTickets = [];
$refundedTickets = [];
$soldAmount = 0.0;
$grossAmount = 0.0;
$discountAmount = 0.0;
$refundAmount = 0.0;
$checkedIn = 0;
$bySegment = [];
$byPayment = [
    'card' => ['label' => 'Карта / онлайн', 'tickets' => 0, 'amount' => 0.0],
    'cash' => ['label' => 'Наличные', 'tickets' => 0, 'amount' => 0.0],
    'other' => ['label' => 'Другое', 'tickets' => 0, 'amount' => 0.0],
];

foreach ($tickets as $ticket) {
    if (($ticket['payment_status'] ?? '') !== 'paid' || ($ticket['status'] ?? '') === 'cancelled') {
        continue;
    }

    $financials = reporting_ticket_financials($ticket);
    $isRefunded = ($ticket['refund_status'] ?? 'none') === 'refunded';
    if ($isRefunded) {
        $refundedTickets[] = $ticket;
        $refundAmount += $financials['paid'];
        continue;
    }

    $soldTickets[] = $ticket;
    $soldAmount += $financials['paid'];
    $grossAmount += $financials['original'];
    $discountAmount += $financials['discount'];
    if (!empty($ticket['is_checked_in'])) {
        $checkedIn++;
    }

    $segmentKey = (string)($ticket['customer_segment'] ?? 'other');
    if ($segmentKey === '') {
        $segmentKey = 'other';
    }
    if (!isset($bySegment[$segmentKey])) {
        $bySegment[$segmentKey] = [
            'label' => reporting_segment_label($segmentKey),
            'tickets' => 0,
            'amount' => 0.0,
        ];
    }
    $bySegment[$segmentKey]['tickets']++;
    $bySegment[$segmentKey]['amount'] += $financials['paid'];

    $paymentLabel = reporting_payment_label($ticket['payment_method'] ?? '', $ticket['channel'] ?? '');
    $paymentKey = $paymentLabel === 'Наличные'
        ? 'cash'
        : ($paymentLabel === 'Карта' ? 'card' : 'other');
    $byPayment[$paymentKey]['tickets']++;
    $byPayment[$paymentKey]['amount'] += $financials['paid'];
}

$soldCount = count($soldTickets);
$refundedCount = count($refundedTickets);
$netAmount = max(0.0, $soldAmount - $refundAmount);
$occupancy = $capacity > 0 ? min(100, ($soldCount / $capacity) * 100) : null;
$averageTicket = $soldCount > 0 ? $soldAmount / $soldCount : 0.0;

$chartColors = ['#2563eb', '#16a34a', '#d97706', '#9333ea', '#0891b2', '#e11d48'];
$makeGradient = static function (array $items, array $colors): string {
    $total = 0;
    foreach ($items as $item) {
        $total += (int)($item['tickets'] ?? 0);
    }
    if ($total <= 0) {
        return 'conic-gradient(#e8eef7 0 100%)';
    }
    $parts = [];
    $cursor = 0.0;
    $index = 0;
    foreach ($items as $item) {
        $ticketsCount = (int)($item['tickets'] ?? 0);
        if ($ticketsCount <= 0) {
            continue;
        }
        $next = $cursor + ($ticketsCount / $total) * 100;
        $color = $colors[$index % count($colors)];
        $parts[] = $color . ' ' . round($cursor, 2) . '% ' . round($next, 2) . '%';
        $cursor = $next;
        $index++;
    }
    return 'conic-gradient(' . implode(', ', $parts) . ')';
};

$paymentItems = array_values($byPayment);
$segmentItems = array_values($bySegment);
$paymentGradient = $makeGradient($paymentItems, $chartColors);
$segmentGradient = $makeGradient($segmentItems, array_reverse($chartColors));
$statusLabel = [
    'draft' => 'Черновик',
    'upcoming' => 'Ожидается',
    'active' => 'Активный',
    'archive' => 'Архивный',
    'cancelled' => 'Отменён',
][(string)($session['status'] ?? '')] ?? 'Сеанс';

$page_title_meta = 'Детальный отчёт — ' . ($session['event_title'] ?? 'Спектакль');
$panel_title = 'Детальный отчёт';
$panel_subtitle = 'Продажи по конкретному сеансу';
$active_menu = 'reports';
$use_sidebar = true;
$page_styles = ['/assets/css/reports.css?v=visual-performance-20260927-2'];
$page_scripts = ['/assets/js/report-drilldown.js?v=visual-performance-20260927-2'];

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/panel.php';
?>

<div class="reports-page page container-full performance-report">
    <div class="performance-hero card">
        <div>
            <a class="performance-back-link" href="/reports/sales.php">← Вернуться к отчётам</a>
            <div class="reports-eyebrow">Детализация сеанса</div>
            <h1><?= h($session['event_title'] ?? 'Без названия') ?></h1>
            <div class="performance-hero__meta">
                <span><?= h(reporting_format_date($session['start_time'], true)) ?></span>
                <span><?= h($session['hall_name'] ?? 'Зал не указан') ?></span>
                <span class="performance-status"><?= h($statusLabel) ?></span>
            </div>
        </div>
        <div class="performance-hero__actions">
            <a class="btn btn-secondary" href="/cash/sell.php?session_id=<?= (int)$scheduleId ?>">Открыть кассу</a>
            <a class="btn btn-ghost" href="/reports/session_export_excel.php?session_id=<?= (int)$scheduleId ?>">Скачать Excel</a>
        </div>
    </div>

    <div class="reports-kpi-grid performance-kpi-grid">
        <div class="card reports-kpi reports-kpi--accent"><span>Продано билетов</span><strong><?= number_format($soldCount, 0, '.', ' ') ?></strong><small>активные оплаченные</small></div>
        <div class="card reports-kpi reports-kpi--success"><span>Выручка</span><strong><?= number_format($soldAmount, 2, '.', ' ') ?> <em>тг</em></strong><small>после скидок</small></div>
        <div class="card reports-kpi reports-kpi--discount"><span>Скидки</span><strong>-<?= number_format($discountAmount, 2, '.', ' ') ?> <em>тг</em></strong><small>от номинальной цены</small></div>
        <div class="card reports-kpi reports-kpi--dark"><span>Возвраты</span><strong><?= number_format($refundAmount, 2, '.', ' ') ?> <em>тг</em></strong><small><?= number_format($refundedCount, 0, '.', ' ') ?> билетов</small></div>
        <div class="card reports-kpi"><span>Средний билет</span><strong><?= number_format($averageTicket, 2, '.', ' ') ?> <em>тг</em></strong><small>чистая продажа</small></div>
    </div>

    <div class="performance-insight-grid">
        <section class="card performance-chart-card">
            <div class="performance-section-head">
                <div><span class="performance-card-kicker">Структура оплат</span><h2>Как покупали билеты</h2></div>
                <span class="performance-card-icon">₸</span>
            </div>
            <div class="performance-chart-layout">
                <div class="performance-donut" style="background: <?= h($paymentGradient) ?>;"><strong><?= number_format($soldCount, 0, '.', ' ') ?></strong><span>билетов</span></div>
                <div class="performance-legend">
                    <?php foreach ($paymentItems as $index => $item): ?>
                        <?php $percent = $soldCount > 0 ? ($item['tickets'] / $soldCount) * 100 : 0; ?>
                        <div class="performance-legend__item"><i style="background:<?= h($chartColors[$index % count($chartColors)]) ?>"></i><span><?= h($item['label']) ?></span><strong><?= number_format($item['tickets'], 0, '.', ' ') ?> <small><?= number_format($percent, 0) ?>%</small></strong></div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section class="card performance-chart-card">
            <div class="performance-section-head">
                <div><span class="performance-card-kicker">Типы билетов</span><h2>Кто пришёл на спектакль</h2></div>
                <span class="performance-card-icon">◔</span>
            </div>
            <div class="performance-chart-layout">
                <div class="performance-donut" style="background: <?= h($segmentGradient) ?>;"><strong><?= number_format($soldCount, 0, '.', ' ') ?></strong><span>продаж</span></div>
                <div class="performance-legend">
                    <?php foreach ($segmentItems as $index => $item): ?>
                        <?php $percent = $soldCount > 0 ? ($item['tickets'] / $soldCount) * 100 : 0; ?>
                        <div class="performance-legend__item"><i style="background:<?= h($chartColors[(count($chartColors) - 1 - $index) % count($chartColors)]) ?>"></i><span><?= h($item['label']) ?></span><strong><?= number_format($item['tickets'], 0, '.', ' ') ?> <small><?= number_format($percent, 0) ?>%</small></strong></div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    </div>

    <section class="card performance-progress-card">
        <div class="performance-section-head">
            <div><span class="performance-card-kicker">Заполняемость зала</span><h2>Продажи и вход на сеанс</h2></div>
            <strong class="performance-progress-value"><?= $occupancy !== null ? number_format($occupancy, 0) . '%' : '—' ?></strong>
        </div>
        <div class="performance-progress-track"><span style="width:<?= $occupancy !== null ? h(number_format($occupancy, 2, '.', '')) : '0' ?>%"></span></div>
        <div class="performance-progress-meta"><span><?= number_format($soldCount, 0, '.', ' ') ?> продано</span><span><?= number_format($checkedIn, 0, '.', ' ') ?> прошло по QR<?php if ($capacity > 0): ?> из <?= number_format($capacity, 0, '.', ' ') ?> мест<?php endif; ?></span></div>
    </section>

    <section class="card performance-sales-card">
        <div class="performance-section-head">
            <div><span class="performance-card-kicker">Детали продаж</span><h2>Проданные билеты</h2></div>
            <span class="performance-sales-count"><?= number_format($soldCount, 0, '.', ' ') ?> записей</span>
        </div>
        <div class="reports-table-wrap">
            <table class="admin-table table--compact reports-table performance-sales-table">
                <thead><tr><th>Место</th><th>Категория</th><th>Оплата</th><th>Заказ</th><th>Цена</th><th>Дата покупки</th></tr></thead>
                <tbody>
                <?php if (!$soldTickets): ?>
                    <tr><td colspan="6" class="reports-empty">Проданных билетов пока нет.</td></tr>
                <?php else: foreach ($soldTickets as $ticket): ?>
                    <?php $ticketFinancials = reporting_ticket_financials($ticket); ?>
                    <tr class="performance-ticket-row" data-ticket-url="/tickets/view.php?id=<?= (int)$ticket['id'] ?>" tabindex="0" role="link">
                        <td><strong><?= h($ticket['seat_identifier'] ?? '—') ?></strong></td>
                        <td><?= h(reporting_segment_label($ticket['customer_segment'] ?? '')) ?></td>
                        <td><?= h(reporting_payment_label($ticket['payment_method'] ?? '', $ticket['channel'] ?? '')) ?></td>
                        <td><?= h($ticket['order_number'] ?? '—') ?></td>
                        <td><?= number_format($ticketFinancials['paid'], 2, '.', ' ') ?> тг</td>
                        <td><?= h(reporting_format_date($ticket['purchased_at'] ?? '', true)) ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
