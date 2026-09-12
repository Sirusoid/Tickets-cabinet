<?php
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/reporting.php';
require_login();

$reportsEmbedded = !empty($reportsEmbedded);
$use_sidebar = true;
$active_menu = $reportsEmbedded ? 'dashboard' : 'reports';
$page_title_meta = $reportsEmbedded ? 'Панель' : 'Отчёты — продажи';
$panel_title = $reportsEmbedded ? 'Панель' : 'Отчёты';
$panel_subtitle = 'Выручка, скидки и чистая сумма по проданным билетам';
$page_styles = ['/assets/css/reports.css'];

if (!$reportsEmbedded) {
	require __DIR__ . '/../includes/header.php';
	require __DIR__ . '/../includes/panel.php';
}

$dateFrom = trim((string)($_GET['date_from'] ?? date('Y-m-01')));
$dateTo = trim((string)($_GET['date_to'] ?? date('Y-m-d')));
$segmentFilter = trim((string)($_GET['segment'] ?? ''));
$eventFilter = (int)($_GET['event_id'] ?? 0);

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
		$dateFrom = date('Y-m-01');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
		$dateTo = date('Y-m-d');
}

$params = [':date_from' => $dateFrom . ' 00:00:00', ':date_to' => $dateTo . ' 23:59:59'];
$segmentSql = '';
if ($segmentFilter !== '') {
		$segmentSql = ' AND t.customer_segment = :segment ';
		$params[':segment'] = $segmentFilter;
}
$eventSql = '';
if ($eventFilter > 0) {
		$eventSql = ' AND COALESCE(t.event_id, s.event_id) = :event_id ';
		$params[':event_id'] = $eventFilter;
}

$rows = [];
$errorText = '';
$events = [];
try {
		$events = db_fetch_all('SELECT id, title FROM events ORDER BY title');
		$sql = "SELECT
								t.id,
								t.schedule_id,
								t.price,
								t.discount,
								t.seat_identifier,
								t.customer_segment,
								t.purchased_at,
								t.payment_status,
								t.status,
								t.refund_status,
								t.payment_transaction_id,
								COALESCE(tx.payment_method, CASE WHEN t.channel IN ('web', 'mobile') THEN 'card' ELSE 'cash' END) AS payment_method,
								tx.payload AS tx_payload,
								e.title AS event_title,
								s.start_time AS schedule_start
						FROM tickets t
						LEFT JOIN schedules s ON s.id = t.schedule_id
						LEFT JOIN events e ON e.id = COALESCE(t.event_id, s.event_id)
						LEFT JOIN cash_transactions tx ON tx.id = t.payment_transaction_id
						WHERE t.purchased_at BETWEEN :date_from AND :date_to
							AND t.payment_status = 'paid'
							AND t.status <> 'cancelled'
							AND COALESCE(t.refund_status, 'none') IN ('none', '', 'no')
							$segmentSql
							$eventSql
						ORDER BY t.purchased_at DESC, t.id DESC";
		$rows = db_fetch_all($sql, $params);
} catch (Throwable $e) {
		$errorText = 'Ошибка загрузки данных: ' . $e->getMessage();
}

$totals = [
		'tickets' => 0,
		'gross' => 0.0,
		'discount' => 0.0,
		'net' => 0.0,
];
$bySegment = [];
$byPerformance = [];
$byPaymentMethod = [
		'card' => ['label' => 'Карта', 'tickets' => 0, 'net' => 0.0],
		'cash' => ['label' => 'Наличные', 'tickets' => 0, 'net' => 0.0],
		'other' => ['label' => 'Другое', 'tickets' => 0, 'net' => 0.0],
];
$refundTotals = ['tickets' => 0, 'amount' => 0.0];
$refundByPerformance = [];

foreach ($rows as $row) {
		$segmentKey = (string)($row['customer_segment'] ?? 'other');
		if ($segmentKey === '') {
				$segmentKey = 'other';
		}
		if (!isset($bySegment[$segmentKey])) {
				$bySegment[$segmentKey] = [
						'segment' => reporting_segment_label($segmentKey),
						'tickets' => 0,
						'gross' => 0.0,
						'discount' => 0.0,
						'net' => 0.0,
				];
		}

		$financials = reporting_ticket_financials($row);
		$price = $financials['paid'];
		$ticketDiscount = $financials['discount'];
		$base = $financials['original'];

		$totals['tickets']++;
		$totals['gross'] += $base;
		$totals['discount'] += $ticketDiscount;
		$totals['net'] += $price;

		$paymentMethod = strtolower(trim((string)($row['payment_method'] ?? '')));
		if ($paymentMethod === '' || strpos($paymentMethod, 'card') !== false || $paymentMethod === 'bcc' || in_array($paymentMethod, ['web', 'online', 'mobile'], true)) {
			$paymentMethod = 'card';
		} elseif (in_array($paymentMethod, ['cash', 'nal', 'cashier'], true)) {
			$paymentMethod = 'cash';
		} else {
			$paymentMethod = 'other';
		}
		$byPaymentMethod[$paymentMethod]['tickets']++;
		$byPaymentMethod[$paymentMethod]['net'] += $price;

		$bySegment[$segmentKey]['tickets']++;
		$bySegment[$segmentKey]['gross'] += $base;
		$bySegment[$segmentKey]['discount'] += $ticketDiscount;
		$bySegment[$segmentKey]['net'] += $price;

		$performanceKey = (string)($row['schedule_start'] ?? '') . '|' . (string)($row['event_title'] ?? '');
		if (!isset($byPerformance[$performanceKey])) {
				$byPerformance[$performanceKey] = [
								'schedule_id' => (int)($row['schedule_id'] ?? 0),
						'event_title' => (string)($row['event_title'] ?? 'Без названия'),
						'schedule_start' => (string)($row['schedule_start'] ?? ''),
						'tickets' => 0,
						'original' => 0.0,
						'discount' => 0.0,
						'paid' => 0.0,
				];
		}
		$byPerformance[$performanceKey]['tickets']++;
		$byPerformance[$performanceKey]['original'] += $base;
		$byPerformance[$performanceKey]['discount'] += $ticketDiscount;
		$byPerformance[$performanceKey]['paid'] += $price;
}

try {
		$refundParams = [':refund_date_from' => $dateFrom . ' 00:00:00', ':refund_date_to' => $dateTo . ' 23:59:59'];
		$refundSegmentSql = '';
		if ($segmentFilter !== '') {
			$refundSegmentSql = ' AND t.customer_segment = :refund_segment ';
			$refundParams[':refund_segment'] = $segmentFilter;
		}
		$refundEventSql = '';
		if ($eventFilter > 0) {
			$refundEventSql = ' AND COALESCE(t.event_id, s.event_id) = :refund_event_id ';
			$refundParams[':refund_event_id'] = $eventFilter;
		}
		$refundRow = db_fetch_one("SELECT COUNT(*) AS tickets, COALESCE(SUM(t.price), 0) AS amount
			FROM tickets t
			LEFT JOIN schedules s ON s.id = t.schedule_id
			WHERE t.refund_at BETWEEN :refund_date_from AND :refund_date_to
				AND t.payment_status = 'paid'
				AND t.refund_status = 'refunded'
				$refundSegmentSql
				$refundEventSql", $refundParams);
		$refundTotals = [
			'tickets' => (int)($refundRow['tickets'] ?? 0),
			'amount' => (float)($refundRow['amount'] ?? 0),
		];
	$refundRows = db_fetch_all("SELECT
		s.id AS schedule_id,
		e.title AS event_title,
			s.start_time AS schedule_start,
			COUNT(*) AS tickets,
			COALESCE(SUM(t.price), 0) AS amount
		FROM tickets t
		LEFT JOIN schedules s ON s.id = t.schedule_id
		LEFT JOIN events e ON e.id = COALESCE(t.event_id, s.event_id)
		WHERE t.refund_at BETWEEN :refund_date_from AND :refund_date_to
			AND t.payment_status = 'paid'
			AND t.refund_status = 'refunded'
			$refundSegmentSql
			$refundEventSql
		GROUP BY e.title, s.start_time
		ORDER BY s.start_time DESC", $refundParams);
	foreach ($refundRows as $refundItem) {
		$key = (string)($refundItem['schedule_start'] ?? '') . '|' . (string)($refundItem['event_title'] ?? '');
		$refundByPerformance[$key] = [
			'schedule_id' => (int)($refundItem['schedule_id'] ?? 0),
			'event_title' => (string)($refundItem['event_title'] ?? 'Без названия'),
			'schedule_start' => (string)($refundItem['schedule_start'] ?? ''),
			'tickets' => (int)($refundItem['tickets'] ?? 0),
			'amount' => (float)($refundItem['amount'] ?? 0),
		];
	}
} catch (Throwable $e) {
		$errorText = $errorText !== '' ? $errorText : 'Ошибка загрузки возвратов: ' . $e->getMessage();
}

usort($bySegment, function ($a, $b) {
		return ($b['net'] <=> $a['net']);
});
usort($byPerformance, function ($a, $b) {
		return strcmp($b['schedule_start'], $a['schedule_start']);
});

$netAfterRefunds = max(0.0, $totals['net'] - $refundTotals['amount']);
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$displayDateRange = reporting_format_date_range($dateFrom, $dateTo);
$exportUrl = '/reports/export_excel.php?' . http_build_query([
		'date_from' => $dateFrom,
		'date_to' => $dateTo,
		'segment' => $segmentFilter,
		'event_id' => $eventFilter,
]);

?>

<div class="reports-page page container-full">
	<div class="reports-toolbar card">
		<div class="reports-toolbar__intro">
			<div class="reports-eyebrow">Сводка продаж</div>
			<h3>Параметры отчёта</h3>
			<p>Выберите период и тип билета, чтобы обновить показатели и таблицы.</p>
		</div>
		<form method="get" class="reports-filters">
			<div>
				<label for="report-date-from">Дата от</label>
				<input id="report-date-from" type="date" name="date_from" class="form-control" value="<?= h($dateFrom) ?>">
			</div>
			<div>
				<label for="report-date-to">Дата до</label>
				<input id="report-date-to" type="date" name="date_to" class="form-control" value="<?= h($dateTo) ?>">
			</div>
			<div>
				<label for="report-segment">Тип билета</label>
				<select id="report-segment" name="segment" class="form-control">
					<option value="">Все типы</option>
					<option value="adult" <?= $segmentFilter === 'adult' ? 'selected' : '' ?>>Взрослый</option>
					<option value="child" <?= $segmentFilter === 'child' ? 'selected' : '' ?>>Детский</option>
					<option value="student" <?= $segmentFilter === 'student' ? 'selected' : '' ?>>Студенческий</option>
					<option value="senior" <?= $segmentFilter === 'senior' ? 'selected' : '' ?>>Пенсионный</option>
					<option value="vip" <?= $segmentFilter === 'vip' ? 'selected' : '' ?>>VIP</option>
				</select>
			</div>
			<div>
				<label for="report-event">Спектакль</label>
				<select id="report-event" name="event_id" class="form-control">
					<option value="0">Все спектакли</option>
					<?php foreach ($events as $event): ?>
						<option value="<?= (int)$event['id'] ?>" <?= $eventFilter === (int)$event['id'] ? 'selected' : '' ?>><?= h($event['title']) ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="reports-filters__actions">
				<a href="/reports/sales.php" class="btn btn-ghost">Сбросить</a>
				<a href="?date_from=<?= h($today) ?>&date_to=<?= h($today) ?>&segment=<?= h($segmentFilter) ?>&event_id=<?= (int)$eventFilter ?>" class="btn btn-ghost">Сегодня</a>
				<a href="?date_from=<?= h($yesterday) ?>&date_to=<?= h($yesterday) ?>&segment=<?= h($segmentFilter) ?>&event_id=<?= (int)$eventFilter ?>" class="btn btn-ghost">Вчера</a>
				<button type="submit" class="btn btn-primary">Показать</button>
				<a href="<?= h($exportUrl) ?>" class="btn btn-secondary">Скачать XLSX</a>
			</div>
		</form>
	</div>

	<?php if ($errorText !== ''): ?>
		<div class="card reports-alert reports-alert--danger"><?= h($errorText) ?></div>
	<?php else: ?>
		<div class="reports-kpi-grid">
			<div class="card reports-kpi reports-kpi--accent"><span>Продано билетов</span><strong><?= number_format((float)$totals['tickets'], 0, '.', ' ') ?></strong><small>за выбранный период</small></div>
			<div class="card reports-kpi"><span>Выручка до скидок</span><strong><?= number_format((float)$totals['gross'], 2, '.', ' ') ?> <em>тг</em></strong><small>номинальная стоимость</small></div>
			<div class="card reports-kpi reports-kpi--success"><span>Чистая выручка</span><strong><?= number_format((float)$totals['net'], 2, '.', ' ') ?> <em>тг</em></strong><small>после скидок</small></div>
			<div class="card reports-kpi reports-kpi--dark"><span>После возвратов</span><strong><?= number_format((float)$netAfterRefunds, 2, '.', ' ') ?> <em>тг</em></strong><small><?= number_format((float)$refundTotals['tickets'], 0, '.', ' ') ?> возвращённых билетов</small></div>
		</div>

		<div class="reports-meta-row">
			<div><span>Период</span><strong><?= h($displayDateRange) ?></strong></div>
			<div class="reports-meta-row__refund"><span>Возвраты</span><strong>-<?= number_format((float)$refundTotals['amount'], 2, '.', ' ') ?> тг</strong></div>
		</div>

		<section class="card reports-section reports-section--wide">
			<div class="reports-section__head">
				<div>
					<h3>Отчёт по спектаклям и датам</h3>
					<p>Продажи сгруппированы по конкретному сеансу; скидка уже вычтена из оплаченной суммы.</p>
				</div>
			</div>
			<div class="reports-table-wrap">
				<table class="admin-table table--compact reports-table reports-table--wide">
					<thead>
						<tr>
							<th>Спектакль</th>
							<th>Дата и время</th>
							<th>Билетов</th>
							<th>Цена без скидки</th>
							<th>Скидка</th>
							<th>Продажи</th>
							<th>Возвраты</th>
							<th>Итого после возвратов</th>
							<th>Excel</th>
						</tr>
					</thead>
					<tbody>
					<?php if (empty($byPerformance) && empty($refundByPerformance)): ?>
						<tr><td colspan="9" class="reports-empty">Данные за выбранный период не найдены</td></tr>
					<?php else:
						$performanceKeys = array_unique(array_merge(array_keys($byPerformance), array_keys($refundByPerformance)));
						foreach ($performanceKeys as $performanceKey):
							$item = $byPerformance[$performanceKey] ?? [
								'event_title' => $refundByPerformance[$performanceKey]['event_title'] ?? 'Без названия',
								'schedule_id' => $refundByPerformance[$performanceKey]['schedule_id'] ?? 0,
								'schedule_start' => $refundByPerformance[$performanceKey]['schedule_start'] ?? '',
								'tickets' => 0,
								'original' => 0.0,
								'discount' => 0.0,
								'paid' => 0.0,
							];
							$refundItem = $refundByPerformance[$performanceKey] ?? ['tickets' => 0, 'amount' => 0.0];
							$netPerformance = max(0.0, (float)$item['paid'] - (float)$refundItem['amount']);
					?>
						<tr>
							<td><strong><?= h($item['event_title']) ?></strong></td>
							<td><?= $item['schedule_start'] !== '' ? h(reporting_format_date($item['schedule_start'], true)) : '—' ?></td>
							<td><?= number_format((float)$item['tickets'], 0, '.', ' ') ?></td>
							<td><?= number_format((float)$item['original'], 2, '.', ' ') ?> тг</td>
							<td class="reports-number--discount">-<?= number_format((float)$item['discount'], 2, '.', ' ') ?> тг</td>
							<td><?= number_format((float)$item['paid'], 2, '.', ' ') ?> тг</td>
							<td class="reports-number--refund">-<?= number_format((float)$refundItem['amount'], 2, '.', ' ') ?> тг</td>
							<td><strong><?= number_format($netPerformance, 2, '.', ' ') ?> тг</strong></td>
							<td>
								<?php if ((int)$item['schedule_id'] > 0): ?>
									<a class="btn btn-secondary btn-sm" href="/reports/session_export_excel.php?session_id=<?= (int)$item['schedule_id'] ?>">Excel</a>
								<?php else: ?>
									—
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; endif; ?>
					</tbody>
				</table>
			</div>
		</section>

		<div class="reports-table-grid">
			<section class="card reports-section">
				<div class="reports-section__head"><div><h3>Продажи по типам</h3><p>Количество билетов и сумма после скидок.</p></div></div>
				<div class="reports-table-wrap">
					<table class="admin-table table--compact reports-table">
						<thead><tr><th>Тип билета</th><th>Билетов</th><th>До скидки</th><th>Скидка</th><th>Итого</th></tr></thead>
						<tbody>
						<?php if (empty($bySegment)): ?><tr><td colspan="5" class="reports-empty">Данные за выбранный период не найдены</td></tr>
						<?php else: foreach ($bySegment as $item): ?><tr><td><strong><?= h($item['segment']) ?></strong></td><td><?= number_format((float)$item['tickets'], 0, '.', ' ') ?></td><td><?= number_format((float)$item['gross'], 2, '.', ' ') ?> тг</td><td class="reports-number--success">-<?= number_format((float)$item['discount'], 2, '.', ' ') ?> тг</td><td><strong><?= number_format((float)$item['net'], 2, '.', ' ') ?> тг</strong></td></tr><?php endforeach; endif; ?>
						</tbody>
					</table>
				</div>
			</section>

			<section class="card reports-section">
				<div class="reports-section__head"><div><h3>Способы оплаты</h3><p>Распределение выручки по каналам.</p></div></div>
				<div class="reports-table-wrap">
					<table class="admin-table table--compact reports-table">
						<thead><tr><th>Способ</th><th>Билетов</th><th>Выручка</th></tr></thead>
						<tbody><?php foreach ($byPaymentMethod as $method): ?><tr><td><strong><?= h($method['label']) ?></strong></td><td><?= number_format((float)$method['tickets'], 0, '.', ' ') ?></td><td><strong><?= number_format((float)$method['net'], 2, '.', ' ') ?> тг</strong></td></tr><?php endforeach; ?></tbody>
					</table>
				</div>
			</section>
		</div>

	<?php endif; ?>
</div>

<?php if (!$reportsEmbedded): ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
<?php endif; ?>
