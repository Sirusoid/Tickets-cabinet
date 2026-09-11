<?php
// Компактная рабочая панель кассира и администратора.
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/includes/reporting.php';
require_login();

$use_sidebar = true;
$active_menu = 'dashboard';
$page_title_meta = 'Панель';
$page_styles = ['/assets/css/reports.css'];

$todaySales = ['tickets' => 0, 'amount' => 0.0];
$todayRefunds = ['tickets' => 0, 'amount' => 0.0];
$todaySessions = [];
$dashboardError = '';

try {
    $todaySalesRow = db_fetch_one("SELECT COUNT(*) AS tickets, COALESCE(SUM(t.price), 0) AS amount
        FROM tickets t
        WHERE DATE(t.purchased_at) = CURDATE()
            AND t.payment_status = 'paid'
            AND t.status <> 'cancelled'
            AND COALESCE(t.refund_status, 'none') NOT IN ('refunded')");
    $todaySales = [
        'tickets' => (int)($todaySalesRow['tickets'] ?? 0),
        'amount' => (float)($todaySalesRow['amount'] ?? 0),
    ];

    $todayRefundRow = db_fetch_one("SELECT COUNT(*) AS tickets, COALESCE(SUM(t.price), 0) AS amount
        FROM tickets t
        WHERE DATE(t.refund_at) = CURDATE()
            AND t.payment_status = 'paid'
            AND t.refund_status = 'refunded'");
    $todayRefunds = [
        'tickets' => (int)($todayRefundRow['tickets'] ?? 0),
        'amount' => (float)($todayRefundRow['amount'] ?? 0),
    ];

    $todaySessions = db_fetch_all("SELECT
            s.id,
            s.start_time,
            e.title AS event_title,
            h.name AS hall_name,
            COUNT(t.id) AS sold_tickets,
            COALESCE(SUM(t.price), 0) AS sold_amount
        FROM schedules s
        LEFT JOIN events e ON e.id = s.event_id
        LEFT JOIN halls h ON h.id = s.hall_id
        LEFT JOIN tickets t ON t.schedule_id = s.id
            AND t.payment_status = 'paid'
            AND t.status <> 'cancelled'
            AND COALESCE(t.refund_status, 'none') <> 'refunded'
        WHERE DATE(s.start_time) = CURDATE()
        GROUP BY s.id, s.start_time, e.title, h.name
        ORDER BY s.start_time ASC");
} catch (Throwable $e) {
    $dashboardError = 'Не удалось загрузить сводку за сегодня.';
    error_log('[DASHBOARD] Ошибка загрузки сводки: ' . $e->getMessage());
}

require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/panel.php';
?>

<div class="reports-page page container-full">
    <?php if ($dashboardError !== ''): ?>
        <div class="card reports-alert reports-alert--danger"><?= h($dashboardError) ?></div>
    <?php endif; ?>

    <div class="reports-toolbar card dashboard-toolbar">
        <div class="reports-toolbar__intro">
            <div class="reports-eyebrow">Рабочий день</div>
            <h3>Сегодня, <?= h(reporting_format_date(date('Y-m-d'))) ?></h3>
            <p>Основные показатели кассы и ближайшие сеансы без лишних деталей.</p>
        </div>
        <div class="dashboard-actions">
            <a class="btn btn-primary" href="/cash/index.php">Открыть кассу</a>
            <a class="btn btn-secondary" href="/reports/sales.php?date_from=<?= h(date('Y-m-d')) ?>&date_to=<?= h(date('Y-m-d')) ?>">Отчёт за сегодня</a>
        </div>
    </div>

    <div class="reports-kpi-grid">
        <div class="card reports-kpi reports-kpi--accent">
            <span>Продано сегодня</span>
            <strong><?= number_format($todaySales['tickets'], 0, '.', ' ') ?></strong>
            <small>билетов</small>
        </div>
        <div class="card reports-kpi reports-kpi--success">
            <span>Выручка сегодня</span>
            <strong><?= number_format($todaySales['amount'], 2, '.', ' ') ?> <em>тг</em></strong>
            <small>после скидок</small>
        </div>
        <div class="card reports-kpi reports-kpi--dark">
            <span>Возвраты сегодня</span>
            <strong><?= number_format($todayRefunds['amount'], 2, '.', ' ') ?> <em>тг</em></strong>
            <small><?= number_format($todayRefunds['tickets'], 0, '.', ' ') ?> билетов</small>
        </div>
        <div class="card reports-kpi">
            <span>Чистый итог</span>
            <strong><?= number_format(max(0.0, $todaySales['amount'] - $todayRefunds['amount']), 2, '.', ' ') ?> <em>тг</em></strong>
            <small>продажи минус возвраты</small>
        </div>
    </div>

    <section class="card reports-section reports-section--wide">
        <div class="reports-section__head">
            <div>
                <h3>Сеансы сегодня</h3>
                <p>Продажи сгруппированы по спектаклю и времени сеанса.</p>
            </div>
        </div>
        <div class="reports-table-wrap">
            <table class="admin-table table--compact reports-table reports-table--wide">
                <thead>
                    <tr>
                        <th>Время</th>
                        <th>Спектакль</th>
                        <th>Зал</th>
                        <th>Продано</th>
                        <th>Выручка</th>
                        <th>Действие</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$todaySessions): ?>
                        <tr><td colspan="6" class="reports-empty">На сегодня сеансов нет.</td></tr>
                    <?php else: foreach ($todaySessions as $session): ?>
                        <tr>
                            <td><?= h(date('H:i', strtotime((string)$session['start_time']))) ?></td>
                            <td><strong><?= h($session['event_title'] ?? 'Без названия') ?></strong></td>
                            <td><?= h($session['hall_name'] ?? '—') ?></td>
                            <td><?= number_format((int)$session['sold_tickets'], 0, '.', ' ') ?></td>
                            <td><?= number_format((float)$session['sold_amount'], 2, '.', ' ') ?> тг</td>
                            <td><a class="btn btn-ghost btn-sm" href="/cash/sell.php?session_id=<?= (int)$session['id'] ?>">Открыть кассу</a></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
