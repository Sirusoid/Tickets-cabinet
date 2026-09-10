<?php
require_once __DIR__ . '/../init.php';
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

$rows = [];
$errorText = '';
try {
		$sql = "SELECT
								t.id,
								t.price,
								t.customer_segment,
								t.purchased_at,
								t.payment_status,
								t.status,
								t.refund_status,
								t.payment_transaction_id,
								COALESCE(tx.payment_method, CASE WHEN t.channel IN ('web', 'mobile') THEN 'card' ELSE 'cash' END) AS payment_method,
								tx.payload AS tx_payload,
								(SELECT COUNT(*) FROM tickets t2 WHERE t2.payment_transaction_id = t.payment_transaction_id) AS tx_ticket_count
						FROM tickets t
						LEFT JOIN cash_transactions tx ON tx.id = t.payment_transaction_id
						WHERE t.purchased_at BETWEEN :date_from AND :date_to
							AND t.payment_status = 'paid'
							AND t.status <> 'cancelled'
							AND COALESCE(t.refund_status, 'none') IN ('none', '', 'no')
							$segmentSql
						ORDER BY t.purchased_at DESC, t.id DESC";
		$rows = db_fetch_all($sql, $params);
} catch (Throwable $e) {
		$errorText = 'Ошибка загрузки данных: ' . $e->getMessage();
}

$segmentNames = [
		'adult' => 'Взрослый',
		'child' => 'Детский',
		'children' => 'Детский',
		'student' => 'Студенческий',
		'senior' => 'Пенсионный',
		'pensioner' => 'Пенсионный',
		'vip' => 'VIP',
];

$safeNum = function ($v) {
		return is_numeric($v) ? (float)$v : 0.0;
};

$parseDiscount = function ($payloadRaw) use ($safeNum) {
		$result = [
				'total_discount' => 0.0,
				'final_total' => 0.0,
		];
		if (!is_string($payloadRaw) || trim($payloadRaw) === '') {
				return $result;
		}
		$payload = json_decode($payloadRaw, true);
		if (!is_array($payload)) {
				return $result;
		}
		$discount = isset($payload['discount']) && is_array($payload['discount']) ? $payload['discount'] : [];
		if (empty($discount)) {
				return $result;
		}
		$result['total_discount'] = max(0.0, $safeNum($discount['total_discount'] ?? 0));
		$result['final_total'] = max(0.0, $safeNum($discount['final_total'] ?? 0));
		return $result;
};

$calcTicketDiscount = function ($price, array $discountInfo, $count) use ($safeNum) {
		$ticketPrice = max(0.0, $safeNum($price));
		$totalDiscount = max(0.0, $safeNum($discountInfo['total_discount'] ?? 0));
		$finalTotal = max(0.0, $safeNum($discountInfo['final_total'] ?? 0));
		$count = max(1, (int)$count);

		if ($totalDiscount <= 0.0) {
				return 0.0;
		}
		if ($finalTotal > 0.0 && $ticketPrice > 0.0) {
				return round($totalDiscount * ($ticketPrice / $finalTotal), 2);
		}
		return round($totalDiscount / $count, 2);
};

$totals = [
		'tickets' => 0,
		'gross' => 0.0,
		'discount' => 0.0,
		'net' => 0.0,
];
$bySegment = [];
$byPaymentMethod = [
		'card' => ['label' => 'Карта', 'tickets' => 0, 'net' => 0.0],
		'cash' => ['label' => 'Наличные', 'tickets' => 0, 'net' => 0.0],
		'other' => ['label' => 'Другое', 'tickets' => 0, 'net' => 0.0],
];
$refundTotals = ['tickets' => 0, 'amount' => 0.0];

foreach ($rows as $row) {
		$segmentKey = (string)($row['customer_segment'] ?? 'other');
		if ($segmentKey === '') {
				$segmentKey = 'other';
		}
		if (!isset($bySegment[$segmentKey])) {
				$bySegment[$segmentKey] = [
						'segment' => $segmentNames[$segmentKey] ?? $segmentKey,
						'tickets' => 0,
						'gross' => 0.0,
						'discount' => 0.0,
						'net' => 0.0,
				];
		}

		$price = max(0.0, $safeNum($row['price'] ?? 0));
		$discountInfo = $parseDiscount($row['tx_payload'] ?? null);
		$ticketDiscount = $calcTicketDiscount($price, $discountInfo, $row['tx_ticket_count'] ?? 1);
		$base = $price + $ticketDiscount;

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
}

try {
		$refundParams = [':refund_date_from' => $dateFrom . ' 00:00:00', ':refund_date_to' => $dateTo . ' 23:59:59'];
		$refundSegmentSql = '';
		if ($segmentFilter !== '') {
			$refundSegmentSql = ' AND t.customer_segment = :refund_segment ';
			$refundParams[':refund_segment'] = $segmentFilter;
		}
		$refundRow = db_fetch_one("SELECT COUNT(*) AS tickets, COALESCE(SUM(t.price), 0) AS amount
			FROM tickets t
			WHERE t.refund_at BETWEEN :refund_date_from AND :refund_date_to
				AND t.payment_status = 'paid'
				AND t.refund_status = 'refunded'
				$refundSegmentSql", $refundParams);
		$refundTotals = [
			'tickets' => (int)($refundRow['tickets'] ?? 0),
			'amount' => (float)($refundRow['amount'] ?? 0),
		];
} catch (Throwable $e) {
		$errorText = $errorText !== '' ? $errorText : 'Ошибка загрузки возвратов: ' . $e->getMessage();
}

usort($bySegment, function ($a, $b) {
		return ($b['net'] <=> $a['net']);
});

$avgDiscount = $totals['tickets'] > 0 ? ($totals['discount'] / $totals['tickets']) : 0.0;
$netAfterRefunds = max(0.0, $totals['net'] - $refundTotals['amount']);
$exportUrl = '/reports/export_excel.php?' . http_build_query([
		'date_from' => $dateFrom,
		'date_to' => $dateTo,
		'segment' => $segmentFilter,
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
			<div class="reports-filters__actions">
				<a href="/reports/sales.php" class="btn btn-ghost">Сбросить</a>
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
			<div><span>Период</span><strong><?= h($dateFrom) ?> — <?= h($dateTo) ?></strong></div>
			<div><span>Средняя скидка</span><strong><?= number_format((float)$avgDiscount, 2, '.', ' ') ?> тг на билет</strong></div>
			<div class="reports-meta-row__refund"><span>Возвраты</span><strong>-<?= number_format((float)$refundTotals['amount'], 2, '.', ' ') ?> тг</strong></div>
		</div>

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
