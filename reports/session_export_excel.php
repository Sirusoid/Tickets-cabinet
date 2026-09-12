<?php
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/reporting.php';
require_login();

require_once __DIR__ . '/../includes/excel.php';
if (!function_exists('excel_autoload') || !excel_autoload()) {
    http_response_code(500);
    exit('PhpSpreadsheet не установлен или недоступен.');
}

$sessionId = (int)($_GET['session_id'] ?? 0);
if ($sessionId <= 0) {
    http_response_code(400);
    exit('Не указан сеанс.');
}

try {
    $session = db_fetch_one("SELECT s.id, s.start_time, e.title AS event_title, h.name AS hall_name
        FROM schedules s
        LEFT JOIN events e ON e.id = s.event_id
        LEFT JOIN halls h ON h.id = s.hall_id
        WHERE s.id = :id
        LIMIT 1", [':id' => $sessionId]);
    if (!$session) {
        http_response_code(404);
        exit('Сеанс не найден.');
    }

    $rows = db_fetch_all("SELECT
            t.id, t.ticket_uid, t.seat_identifier, t.price, t.discount,
            t.customer_segment, t.channel, t.payment_status, t.status,
            t.refund_status, t.refund_at, t.purchased_at,
            tx.payload AS tx_payload,
            tx.payment_method AS tx_payment_method,
            ps.order_number,
            COALESCE(c.full_name, '') AS customer_name,
            r.refund_amount AS refund_record_amount,
            r.refund_transaction_id AS refund_record_transaction_id,
            r.processed_by AS refund_processed_by
        FROM tickets t
        LEFT JOIN customers c ON c.id = t.customer_id
        LEFT JOIN cash_transactions tx ON tx.id = t.payment_transaction_id
        LEFT JOIN payment_sessions ps ON ps.id = t.payment_session_id
        LEFT JOIN refunds r ON r.id = (
            SELECT MAX(r2.id) FROM refunds r2 WHERE r2.ticket_id = t.id
        )
        WHERE t.schedule_id = :session_id
            AND t.payment_status = 'paid'
        ORDER BY t.purchased_at ASC, t.id ASC", [':session_id' => $sessionId]);
} catch (Throwable $e) {
    error_log('[REPORT] Ошибка экспорта сеанса: ' . $e->getMessage());
    http_response_code(500);
    exit('Не удалось подготовить отчёт по сеансу.');
}

$summary = [
    'sold_tickets' => 0,
    'refunded_tickets' => 0,
    'original' => 0.0,
    'discount' => 0.0,
    'sales' => 0.0,
    'refunds' => 0.0,
];
$details = [];
foreach ($rows as $row) {
    $financials = reporting_ticket_financials($row);
    $isRefunded = (string)($row['refund_status'] ?? '') === 'refunded';
    $refundAmount = $isRefunded ? max(0.0, (float)($row['refund_record_amount'] ?? $financials['paid'])) : 0.0;
    $paymentMethod = reporting_payment_label($row['tx_payment_method'] ?? '', $row['channel'] ?? '');
    $purchaseSource = in_array(strtolower((string)($row['channel'] ?? '')), ['web', 'mobile', 'online'], true)
        || $paymentMethod === 'Карта' ? 'Онлайн' : 'Касса';
    if ($isRefunded) {
        $summary['refunded_tickets']++;
        $summary['refunds'] += $refundAmount;
    } elseif ((string)($row['status'] ?? '') !== 'cancelled') {
        $summary['sold_tickets']++;
        $summary['original'] += $financials['original'];
        $summary['discount'] += $financials['discount'];
        $summary['sales'] += $financials['paid'];
    }

    $details[] = [
        $isRefunded ? 'Возврат' : 'Покупка',
        $isRefunded ? ((int)($row['refund_processed_by'] ?? 0) > 0 ? 'Кассир' : 'Клиент') : $purchaseSource,
        !empty($row['purchased_at']) ? reporting_format_date($row['purchased_at'], true) : '',
        $isRefunded && !empty($row['refund_at']) ? reporting_format_date($row['refund_at'], true) : '',
        str_replace(':', ' - ', (string)($row['seat_identifier'] ?? '')),
        (string)($row['ticket_uid'] ?? ''),
        reporting_segment_label($row['customer_segment'] ?? ''),
        reporting_discount_label($row),
        $financials['original'],
        $financials['discount'],
        $financials['paid'],
        $refundAmount,
        max(0.0, $financials['paid'] - $refundAmount),
        $paymentMethod,
        (string)($row['channel'] ?? ''),
        (string)($row['order_number'] ?? ''),
        (string)($row['customer_name'] ?? ''),
        $isRefunded ? 'Возвращён' : ((string)($row['status'] ?? '') === 'cancelled' ? 'Отменён' : 'Выдан'),
        (string)($row['refund_record_transaction_id'] ?? ''),
    ];
}

if (isset($pdo) && $pdo instanceof PDO && function_exists('audit_log_event')) {
    audit_log_event($pdo, 'report.session_export_xlsx', 'schedule', $sessionId, $session['event_title'] ?? null, [], [
        'session_id' => $sessionId,
        'tickets' => count($rows),
    ]);
}

$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
$summarySheet = $spreadsheet->getActiveSheet();
$summarySheet->setTitle('Сводка');
$summarySheet->fromArray([
    ['Отчёт по сеансу', null, null, null],
    ['Спектакль', (string)($session['event_title'] ?? 'Без названия'), null, null],
    ['Дата и время', reporting_format_date($session['start_time'], true), null, null],
    ['Зал', (string)($session['hall_name'] ?? '—'), null, null],
    [],
    ['Показатель', 'Значение', null, null],
    ['Продано билетов', $summary['sold_tickets'], null, null],
    ['Цена без скидки, тг', round($summary['original'], 2), null, null],
    ['Скидка, тг', round($summary['discount'], 2), null, null],
    ['Продажи, тг', round($summary['sales'], 2), null, null],
    ['Возвращено билетов', $summary['refunded_tickets'], null, null],
    ['Возвраты, тг', round($summary['refunds'], 2), null, null],
    ['Итого после возвратов, тг', max(0.0, $summary['sales'] - $summary['refunds']), null, null],
], null, 'A1');

$detailSheet = $spreadsheet->createSheet();
$detailSheet->setTitle('Билеты');
$detailSheet->fromArray([
    ['Операция', 'Источник', 'Дата покупки', 'Дата возврата', 'Ряд - Место', 'UID билета',
        'Тип билета', 'Тип скидки', 'Цена без скидки, тг', 'Скидка, тг', 'Продажа, тг',
        'Возврат, тг', 'Итог, тг', 'Форма оплаты', 'Канал', 'Номер заказа', 'Клиент',
        'Статус', 'Транзакция возврата'],
], null, 'A1');
foreach ($details as $index => $detail) {
    $detailSheet->fromArray([$detail], null, 'A' . ($index + 2));
}

$summarySheet->getStyle('A1:B1')->getFont()->setBold(true)->setSize(15);
$summarySheet->getStyle('A6:B6')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
$summarySheet->getStyle('A6:B6')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FF2563EB');
$detailSheet->getStyle('A1:S1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
$detailSheet->getStyle('A1:S1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FF2563EB');
$summarySheet->getStyle('B8:B13')->getNumberFormat()->setFormatCode('#,##0.00');
$detailSheet->getStyle('I2:M' . max(2, count($details) + 1))->getNumberFormat()->setFormatCode('#,##0.00');
$summarySheet->freezePane('A7');
$detailSheet->freezePane('A2');
foreach ([$summarySheet, $detailSheet] as $sheet) {
    foreach (range('A', $sheet->getHighestColumn()) as $column) {
        $sheet->getColumnDimension($column)->setAutoSize(true);
    }
}

$filename = 'session-' . $sessionId . '-' . date('Y-m-d') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

try {
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
} catch (Throwable $e) {
    error_log('[REPORT] Ошибка записи XLSX сеанса: ' . $e->getMessage());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo 'Не удалось сформировать XLSX-файл.';
}
exit;
