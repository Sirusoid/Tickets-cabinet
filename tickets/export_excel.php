<?php
require_once __DIR__ . '/../init.php';
require_login();
require_once __DIR__ . '/../includes/excel.php';

if (!function_exists('excel_autoload') || !excel_autoload()) {
	http_response_code(500);
	exit('PhpSpreadsheet не установлен или недоступен.');
}

$filters = [
	'date_from' => trim((string)($_GET['date_from'] ?? '')),
	'date_to' => trim((string)($_GET['date_to'] ?? '')),
	'session' => trim((string)($_GET['session'] ?? '')),
	'uid' => trim((string)($_GET['uid'] ?? '')),
	'customer' => trim((string)($_GET['customer'] ?? '')),
	'status' => trim((string)($_GET['status'] ?? '')),
	'payment_status' => trim((string)($_GET['payment_status'] ?? '')),
	'channel' => trim((string)($_GET['channel'] ?? '')),
	'customer_segment' => trim((string)($_GET['customer_segment'] ?? '')),
	'refund' => trim((string)($_GET['refund'] ?? '')),
];

$where = [];
$params = [];
if ($filters['date_from'] !== '') {
	$where[] = 't.purchased_at >= :date_from';
	$params[':date_from'] = $filters['date_from'] . ' 00:00:00';
}
if ($filters['date_to'] !== '') {
	$where[] = 't.purchased_at <= :date_to';
	$params[':date_to'] = $filters['date_to'] . ' 23:59:59';
}
if ($filters['status'] !== '') {
	$where[] = 't.status = :status';
	$params[':status'] = $filters['status'];
}
if ($filters['payment_status'] !== '') {
	$where[] = 't.payment_status = :payment_status';
	$params[':payment_status'] = $filters['payment_status'];
}
if ($filters['channel'] !== '') {
	$where[] = 't.channel = :channel';
	$params[':channel'] = $filters['channel'];
}
if ($filters['customer_segment'] !== '') {
	$where[] = 't.customer_segment = :customer_segment';
	$params[':customer_segment'] = $filters['customer_segment'];
}
if ($filters['session'] !== '') {
	if (is_numeric($filters['session'])) {
		$where[] = 't.schedule_id = :schedule_id';
		$params[':schedule_id'] = (int)$filters['session'];
	} else {
		$where[] = '(e.title LIKE :session_title OR s.start_time LIKE :session_time)';
		$params[':session_title'] = '%' . $filters['session'] . '%';
		$params[':session_time'] = '%' . $filters['session'] . '%';
	}
}
if ($filters['uid'] !== '') {
	$where[] = 't.ticket_uid LIKE :uid';
	$params[':uid'] = '%' . $filters['uid'] . '%';
}
if ($filters['customer'] !== '') {
	$where[] = 'c.full_name LIKE :customer';
	$params[':customer'] = '%' . $filters['customer'] . '%';
}
if ($filters['refund'] === 'yes') {
	$where[] = "t.refund_status = 'refunded'";
} elseif ($filters['refund'] === 'no') {
	$where[] = "(t.refund_status IS NULL OR t.refund_status = 'none')";
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

function ticket_export_discount_info($payload): array
{
	$decoded = is_string($payload) ? json_decode($payload, true) : null;
	$discount = is_array($decoded) && is_array($decoded['discount'] ?? null) ? $decoded['discount'] : [];
	return [
		'total' => is_numeric($discount['total_discount'] ?? null) ? max(0, (float)$discount['total_discount']) : 0,
		'final_total' => is_numeric($discount['final_total'] ?? null) ? max(0, (float)$discount['final_total']) : 0,
	];
}

function ticket_export_payment_label($channel, $method): string
{
	$method = strtolower(trim((string)$method));
	$channel = strtolower(trim((string)$channel));
	if ($method !== '' && (strpos($method, 'card') !== false || strpos($method, 'visa') !== false || strpos($method, 'master') !== false || $method === 'bcc')) {
		return 'Картой';
	}
	if (in_array($channel, ['web', 'online', 'card', 'terminal', 'mobile'], true)) {
		return 'Картой';
	}
	return 'Наличные';
}

$segmentNames = [
	'adult' => 'Взрослый', 'child' => 'Детский', 'children' => 'Детский',
	'student' => 'Студенческий', 'senior' => 'Пенсионный', 'pensioner' => 'Пенсионный', 'vip' => 'VIP',
];
$statusNames = ['issued' => 'Выдан', 'cancelled' => 'Отменён', 'used' => 'Использован'];
$paymentNames = ['paid' => 'Оплачен', 'pending' => 'Ожидает', 'failed' => 'Ошибка'];
$channelNames = ['web' => 'Web', 'mobile' => 'Mobile', 'kassa' => 'Касса', 'agent' => 'Agent', 'qr' => 'QR', 'admin' => 'Admin'];

$sql = "SELECT
		t.id, t.ticket_uid, t.seat_identifier, t.purchased_at, t.price, t.discount,
		t.channel, t.payment_status, t.status, t.refund_status, t.customer_segment,
		tx.payload AS tx_payload, tx.payment_method AS tx_payment_method,
		ps.order_number, COALESCE(c.full_name, '') AS customer_name,
		COALESCE(e.title, '') AS event_title, s.start_time AS session_start
	FROM tickets t
	LEFT JOIN customers c ON c.id = t.customer_id
	LEFT JOIN schedules s ON s.id = t.schedule_id
	LEFT JOIN events e ON e.id = s.event_id
	LEFT JOIN cash_transactions tx ON tx.id = t.payment_transaction_id
	LEFT JOIN payment_sessions ps ON ps.id = t.payment_session_id
	$whereSql
	ORDER BY t.purchased_at DESC, t.id DESC";

try {
	$rows = db_fetch_all($sql, $params);
} catch (Throwable $exception) {
	error_log('tickets/export_excel.php query: ' . $exception->getMessage());
	http_response_code(500);
	exit('Не удалось загрузить билеты для экспорта.');
}

$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Билеты');
$filterText = [];
$filterLabels = [
	'date_from' => 'Дата от', 'date_to' => 'Дата до', 'session' => 'Сеанс', 'uid' => 'UID',
	'customer' => 'Клиент', 'status' => 'Статус', 'payment_status' => 'Оплата',
	'channel' => 'Канал', 'customer_segment' => 'Тип билета', 'refund' => 'Возврат',
];
foreach ($filterLabels as $key => $label) {
	if ($filters[$key] !== '') {
		$value = $filters[$key];
		if ($key === 'status') $value = $statusNames[$value] ?? $value;
		if ($key === 'payment_status') $value = $paymentNames[$value] ?? $value;
		if ($key === 'channel') $value = $channelNames[$value] ?? $value;
		if ($key === 'customer_segment') $value = $segmentNames[$value] ?? $value;
		if ($key === 'refund') $value = $value === 'yes' ? 'Да' : 'Нет';
		$filterText[] = $label . ': ' . $value;
	}
}
$filterTitle = $filterText ? implode(' | ', $filterText) : 'Все билеты';

$totals = ['tickets' => 0, 'base' => 0.0, 'discount' => 0.0, 'price' => 0.0];
$sheet->fromArray([
	['Отчёт по билетам', null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null],
	['Фильтры', $filterTitle, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null],
	['Итоги', null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null],
	['Билетов', 0, 'База, тг', 0, 'Скидка, тг', 0, 'Итого, тг', 0, null, null, null, null, null, null, null, null, null],
	[],
	['№', 'ID', 'Дата заказа', 'Сеанс', 'Клиент', 'Ряд - Место', 'Тип', 'База, тг', 'Скидка, тг', 'Цена, тг', 'Канал', 'Форма оплаты', 'Номер заказа', 'UID билета', 'Оплата', 'Статус', 'Возврат'],
], null, 'A1');

$rowNumber = 7;
foreach ($rows as $index => $row) {
	$price = is_numeric($row['price'] ?? null) ? (float)$row['price'] : 0.0;
	$dbDiscount = is_numeric($row['discount'] ?? null) ? (int)$row['discount'] : null;
	$discountInfo = ticket_export_discount_info($row['tx_payload'] ?? null);
	if ($dbDiscount !== null && $dbDiscount > 0 && $dbDiscount < 100) {
		$base = round($price / (1 - ($dbDiscount / 100)), 2);
		$discount = round($base - $price, 2);
	} else {
		$discount = $discountInfo['total'] > 0 && $discountInfo['final_total'] > 0
			? round($discountInfo['total'] * ($price / $discountInfo['final_total']), 2)
			: 0.0;
		$base = round($price + $discount, 2);
	}
	$totals['tickets']++;
	$totals['base'] += $base;
	$totals['discount'] += $discount;
	$totals['price'] += $price;
	$seat = str_replace(':', ' - ', (string)($row['seat_identifier'] ?? ''));
	$session = (string)($row['event_title'] ?? '');
	if (!empty($row['session_start'])) $session .= ' / ' . date('d.m.Y H:i', strtotime($row['session_start']));
	$sheet->fromArray([[
		$index + 1, (int)$row['id'], !empty($row['purchased_at']) ? date('d.m.Y H:i', strtotime($row['purchased_at'])) : '',
		$session, (string)($row['customer_name'] ?? ''), $seat,
		$segmentNames[(string)($row['customer_segment'] ?? '')] ?? (string)($row['customer_segment'] ?? '—'),
		$base, $discount, $price, $channelNames[(string)($row['channel'] ?? '')] ?? (string)($row['channel'] ?? ''),
		ticket_export_payment_label($row['channel'] ?? '', $row['tx_payment_method'] ?? ''), (string)($row['order_number'] ?? ''),
		(string)($row['ticket_uid'] ?? ''), $paymentNames[(string)($row['payment_status'] ?? '')] ?? (string)($row['payment_status'] ?? ''),
		$statusNames[(string)($row['status'] ?? '')] ?? (string)($row['status'] ?? ''),
		(($row['refund_status'] ?? '') === 'refunded' ? 'Да' : 'Нет'),
	]], null, 'A' . $rowNumber++);
}

$sheet->setCellValue('B4', $totals['tickets']);
$sheet->setCellValue('D4', $totals['base']);
$sheet->setCellValue('F4', $totals['discount']);
$sheet->setCellValue('H4', $totals['price']);
$sheet->mergeCells('A1:Q1');
$sheet->mergeCells('B2:Q2');
$sheet->getStyle('A1:Q1')->getFont()->setBold(true)->setSize(16);
$sheet->getStyle('A3:H4')->getFont()->setBold(true);
$sheet->getStyle('A6:Q6')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
$sheet->getStyle('A6:Q6')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FF2563EB');
$sheet->getStyle('D4:H4')->getNumberFormat()->setFormatCode('#,##0.00');
$sheet->getStyle('H7:J' . max(7, $rowNumber - 1))->getNumberFormat()->setFormatCode('#,##0.00');
$sheet->freezePane('A7');
foreach (range('A', 'Q') as $column) $sheet->getColumnDimension($column)->setAutoSize(true);
$sheet->getColumnDimension('D')->setWidth(34);
$sheet->getColumnDimension('E')->setWidth(28);
$sheet->getColumnDimension('M')->setWidth(22);
$sheet->getColumnDimension('N')->setWidth(24);

$filename = 'tickets-' . date('Y-m-d') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
try {
	$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
	$writer->save('php://output');
} catch (Throwable $exception) {
	error_log('tickets/export_excel.php writer: ' . $exception->getMessage());
	if (!headers_sent()) {
		http_response_code(500);
		header('Content-Type: text/plain; charset=utf-8');
	}
	echo 'Не удалось сформировать XLSX-файл.';
}
exit;
