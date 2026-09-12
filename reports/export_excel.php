<?php
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/reporting.php';
require_login();

require_once __DIR__ . '/../includes/excel.php';
if (!function_exists('excel_autoload') || !excel_autoload()) {
    http_response_code(500);
    exit('PhpSpreadsheet не установлен или недоступен.');
}

$dateFrom = trim((string)($_GET['date_from'] ?? date('Y-m-01')));
$dateTo = trim((string)($_GET['date_to'] ?? date('Y-m-d')));
$segment = trim((string)($_GET['segment'] ?? ''));
$eventId = (int)($_GET['event_id'] ?? 0);
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $dateFrom = date('Y-m-01');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $dateTo = date('Y-m-d');
}

$params = [
    ':date_from' => $dateFrom . ' 00:00:00',
    ':date_to' => $dateTo . ' 23:59:59',
];
$segmentSql = '';
if ($segment !== '') {
    $segmentSql = ' AND t.customer_segment = :segment';
    $params[':segment'] = $segment;
}
$eventSql = '';
if ($eventId > 0) {
    $eventSql = ' AND COALESCE(t.event_id, s.event_id) = :event_id';
    $params[':event_id'] = $eventId;
}

try {
    $rows = db_fetch_all("SELECT
            t.id, t.ticket_uid, t.seat_identifier, t.price, t.discount, t.schedule_id,
            t.customer_segment, t.channel, t.payment_status, t.status,
            t.refund_status, t.refund_at, t.payment_provider, t.purchased_at,
            tx.payload AS tx_payload,
            tx.payment_method AS tx_payment_method,
            ps.order_number,
            COALESCE(c.full_name, '') AS customer_name,
            COALESCE(e.title, '') AS event_title,
            s.start_time AS session_start,
            r.refund_amount AS refund_record_amount,
            r.refund_method AS refund_record_method,
            r.refund_provider AS refund_record_provider,
            r.refund_transaction_id AS refund_record_transaction_id,
            r.processed_by AS refund_processed_by,
            r.reason AS refund_record_reason
        FROM tickets t
        LEFT JOIN customers c ON c.id = t.customer_id
        LEFT JOIN schedules s ON s.id = t.schedule_id
        LEFT JOIN events e ON e.id = COALESCE(t.event_id, s.event_id)
        LEFT JOIN cash_transactions tx ON tx.id = t.payment_transaction_id
        LEFT JOIN payment_sessions ps ON ps.id = t.payment_session_id
        LEFT JOIN refunds r ON r.id = (
            SELECT MAX(r2.id) FROM refunds r2 WHERE r2.ticket_id = t.id
        )
        WHERE t.purchased_at BETWEEN :date_from AND :date_to
            AND t.payment_status = 'paid'
            $segmentSql
            $eventSql
        ORDER BY t.purchased_at DESC, t.id DESC", $params);
} catch (Throwable $e) {
    error_log('[REPORT] Ошибка подготовки XLSX: ' . $e->getMessage());
    http_response_code(500);
    exit('Не удалось подготовить отчёт.');
}

$segmentOptions = [
    'adult' => 'Взрослый', 'child' => 'Детский', 'children' => 'Детский',
    'student' => 'Студенческий', 'senior' => 'Пенсионный', 'pensioner' => 'Пенсионный', 'vip' => 'VIP',
];
$channelOptions = ['web' => 'Веб', 'mobile' => 'Мобильный', 'kassa' => 'Касса', 'agent' => 'Агент', 'qr' => 'QR', 'admin' => 'Админ'];

$details = [];
$summary = [];
$totals = ['tickets' => 0, 'original' => 0.0, 'discount' => 0.0, 'paid' => 0.0, 'refunds' => 0.0, 'refunded_tickets' => 0];
foreach ($rows as $row) {
    $financials = reporting_ticket_financials($row);
    $isRefunded = (string)($row['refund_status'] ?? '') === 'refunded';
    $refundAmount = $isRefunded ? max(0.0, (float)($row['refund_record_amount'] ?? $financials['paid'])) : 0.0;
    $paymentMethod = reporting_payment_label($row['tx_payment_method'] ?? '', $row['channel'] ?? '');
    $purchaseSource = in_array(strtolower((string)($row['channel'] ?? '')), ['web', 'mobile', 'online'], true)
        || $paymentMethod === 'Карта' ? 'Онлайн' : 'Касса';
    $key = (string)($row['session_start'] ?? '') . '|' . (string)($row['event_title'] ?? '');
    if (!isset($summary[$key])) {
        $summary[$key] = [
            'event_title' => (string)($row['event_title'] ?? 'Без названия'),
            'session_start' => (string)($row['session_start'] ?? ''),
            'tickets' => 0,
            'original' => 0.0,
            'discount' => 0.0,
            'paid' => 0.0,
            'refunds' => 0.0,
        ];
    }
    if (!$isRefunded && (string)($row['status'] ?? '') !== 'cancelled') {
        $summary[$key]['tickets']++;
        $summary[$key]['original'] += $financials['original'];
        $summary[$key]['discount'] += $financials['discount'];
        $summary[$key]['paid'] += $financials['paid'];
        $totals['tickets']++;
        $totals['original'] += $financials['original'];
        $totals['discount'] += $financials['discount'];
        $totals['paid'] += $financials['paid'];
    }
    if ($isRefunded) {
        $summary[$key]['refunds'] = ($summary[$key]['refunds'] ?? 0.0) + $refundAmount;
        $totals['refunds'] += $refundAmount;
        $totals['refunded_tickets']++;
    }

    $details[] = [
        $isRefunded ? 'Возврат' : 'Покупка',
        $isRefunded ? ((int)($row['refund_processed_by'] ?? 0) > 0 ? 'Кассир' : 'Клиент') : $purchaseSource,
        !empty($row['purchased_at']) ? reporting_format_date($row['purchased_at'], true) : '',
        $isRefunded && !empty($row['refund_at']) ? reporting_format_date($row['refund_at'], true) : '',
        (string)($row['event_title'] ?? ''),
        !empty($row['session_start']) ? reporting_format_date($row['session_start'], true) : '',
        (string)($row['ticket_uid'] ?? ''),
        str_replace(':', ' - ', (string)($row['seat_identifier'] ?? '')),
        reporting_segment_label($row['customer_segment'] ?? ''),
        reporting_discount_label($row),
        $financials['original'],
        $financials['discount'],
        $financials['paid'],
        $refundAmount,
        max(0.0, $financials['paid'] - $refundAmount),
        $paymentMethod,
        $channelOptions[(string)($row['channel'] ?? '')] ?? (string)($row['channel'] ?? 'Другое'),
        (string)($row['order_number'] ?? ''),
        (string)($row['customer_name'] ?? ''),
        (string)($row['payment_status'] ?? ''),
        (string)($row['status'] ?? ''),
        (string)($row['refund_status'] ?? 'none'),
        (string)($row['refund_record_transaction_id'] ?? ''),
    ];
}

if (isset($pdo) && $pdo instanceof PDO && function_exists('audit_log_event')) {
    audit_log_event($pdo, 'report.export_xlsx', 'report', null, 'Продажи', [], [
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'segment' => $segment,
        'event_id' => $eventId,
        'tickets' => $totals['tickets'],
    ]);
}

$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
$summarySheet = $spreadsheet->getActiveSheet();
$summarySheet->setTitle('Сводка');
$summarySheet->fromArray([
    ['Отчёт продаж', null, null, null, null, null],
    ['Период', reporting_format_date_range($dateFrom, $dateTo), null, null, null, null],
    ['Билетов', $totals['tickets'], 'Цена без скидки, тг', $totals['original'], 'Скидка, тг', $totals['discount'], 'Возвраты, тг', $totals['refunds']],
    ['Оплачено, тг', $totals['paid'], 'Возвращено билетов', $totals['refunded_tickets'], 'Итого после возвратов, тг', max(0.0, $totals['paid'] - $totals['refunds']), null, null],
    [],
    ['Спектакль', 'Дата и время сеанса', 'Билетов', 'Цена без скидки, тг', 'Скидка, тг', 'Продажи, тг', 'Возвраты, тг', 'Итого после возвратов, тг'],
], null, 'A1');
$summaryRow = 7;
foreach ($summary as $item) {
    $summarySheet->fromArray([[
        $item['event_title'],
        $item['session_start'] !== '' ? reporting_format_date($item['session_start'], true) : '',
        $item['tickets'],
        round($item['original'], 2),
        round($item['discount'], 2),
        round($item['paid'], 2),
        round($item['refunds'], 2),
        round($item['paid'] - $item['refunds'], 2),
    ]], null, 'A' . $summaryRow++);
}

$detailSheet = $spreadsheet->createSheet();
$detailSheet->setTitle('Билеты');
$detailSheet->fromArray([
    ['Операция', 'Источник', 'Дата покупки', 'Дата возврата', 'Спектакль', 'Сеанс',
        'UID билета', 'Ряд - Место', 'Тип билета', 'Тип скидки', 'Цена без скидки, тг',
        'Скидка, тг', 'Продажа, тг', 'Возврат, тг', 'Итог, тг', 'Форма оплаты', 'Канал',
        'Номер заказа', 'Клиент', 'Оплата', 'Статус', 'Возврат', 'Транзакция возврата'],
], null, 'A1');
$detailRow = 2;
foreach ($details as $detail) {
    $detailSheet->fromArray([$detail], null, 'A' . $detailRow++);
}

foreach ([$summarySheet, $detailSheet] as $sheet) {
    $sheet->getStyle($sheet->calculateWorksheetDimension())->getAlignment()->setVertical(
        \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP
    );
    $headerRow = $sheet === $summarySheet ? 6 : 1;
    $sheet->getStyle('A' . $headerRow . ':' . $sheet->getHighestColumn() . $headerRow)->getFont()->setBold(true);
    $sheet->getStyle('A' . $headerRow . ':' . $sheet->getHighestColumn() . $headerRow)->getFill()
        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
        ->getStartColor()->setARGB('FF2563EB');
    $sheet->getStyle('A' . $headerRow . ':' . $sheet->getHighestColumn() . $headerRow)->getFont()->getColor()->setARGB('FFFFFFFF');
    foreach (range('A', $sheet->getHighestColumn()) as $column) {
        $sheet->getColumnDimension($column)->setAutoSize(true);
    }
    $sheet->freezePane($sheet === $summarySheet ? 'A7' : 'A2');
}
$summarySheet->getStyle('D3:H' . max(3, $summaryRow - 1))->getNumberFormat()->setFormatCode('#,##0.00');
$summarySheet->getStyle('A7:H' . max(7, $summaryRow - 1))->getNumberFormat()->setFormatCode('#,##0.00');
$detailSheet->getStyle('K2:O' . max(2, $detailRow - 1))->getNumberFormat()->setFormatCode('#,##0.00');

$filename = 'sales-report-' . $dateFrom . '-' . $dateTo . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

try {
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
} catch (Throwable $e) {
    error_log('[REPORT] Ошибка записи XLSX: ' . $e->getMessage());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo 'Не удалось сформировать XLSX-файл.';
}
exit;
