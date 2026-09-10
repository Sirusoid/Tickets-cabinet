<?php
require_once __DIR__ . '/../init.php';
require_login();

$excelAutoload = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
if (!is_file($excelAutoload)) {
    http_response_code(500);
    exit('PhpSpreadsheet не установлен или недоступен.');
}
require_once $excelAutoload;
if (!class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
    http_response_code(500);
    exit('PhpSpreadsheet не установлен или недоступен.');
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

$rows = db_fetch_all("SELECT
        t.purchased_at,
        e.title AS event_title,
        s.start_time AS schedule_start,
        t.ticket_uid,
        REPLACE(t.seat_identifier, ':', ' - ') AS seat,
        t.customer_segment,
        t.price,
        COALESCE(tx.payment_method, CASE WHEN t.channel IN ('web', 'mobile') THEN 'card' ELSE 'cash' END) AS payment_method,
        t.channel,
        t.status
    FROM tickets t
    LEFT JOIN schedules s ON s.id = t.schedule_id
    LEFT JOIN events e ON e.id = t.event_id
    LEFT JOIN cash_transactions tx ON tx.id = t.payment_transaction_id
    WHERE t.purchased_at BETWEEN :date_from AND :date_to
        AND t.payment_status = 'paid'
        AND t.status <> 'cancelled'
        AND COALESCE(t.refund_status, 'none') IN ('none', '', 'no')
        $segmentSql
    ORDER BY t.purchased_at DESC, t.id DESC", $params);

$segmentNames = [
    'adult' => 'Взрослый', 'child' => 'Детский', 'children' => 'Детский',
    'student' => 'Студенческий', 'senior' => 'Пенсионный', 'pensioner' => 'Пенсионный', 'vip' => 'VIP',
];

$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Продажи');
$sheet->fromArray([
    ['Отчёт продаж', null, null, null, null, null, null, null, null],
    ['Период', $dateFrom . ' — ' . $dateTo, null, null, null, null, null, null, null],
    [],
    ['Дата', 'Спектакль', 'Сеанс', 'UID билета', 'Место', 'Тип билета', 'Цена, тг', 'Оплата', 'Канал'],
], null, 'A1');

$rowNumber = 5;
foreach ($rows as $row) {
    $sheet->fromArray([[
        !empty($row['purchased_at']) ? date('d.m.Y H:i', strtotime($row['purchased_at'])) : '',
        (string)($row['event_title'] ?? ''),
        !empty($row['schedule_start']) ? date('d.m.Y H:i', strtotime($row['schedule_start'])) : '',
        (string)($row['ticket_uid'] ?? ''),
        (string)($row['seat'] ?? ''),
        $segmentNames[(string)($row['customer_segment'] ?? '')] ?? (string)($row['customer_segment'] ?? '—'),
        (float)($row['price'] ?? 0),
        (string)($row['payment_method'] ?? ''),
        (string)($row['channel'] ?? ''),
    ]], null, 'A' . $rowNumber++);
}

$sheet->mergeCells('A1:I1');
$sheet->mergeCells('B2:I2');
$sheet->getStyle('A1:I1')->getFont()->setBold(true)->setSize(16);
$sheet->getStyle('A4:I4')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
$sheet->getStyle('A4:I4')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FF2563EB');
$sheet->getStyle('G5:G' . max(5, $rowNumber - 1))->getNumberFormat()->setFormatCode('#,##0.00');
$sheet->freezePane('A5');

foreach (range('A', 'I') as $column) {
    $sheet->getColumnDimension($column)->setAutoSize(true);
}
$sheet->getColumnDimension('B')->setWidth(32);
$sheet->getColumnDimension('D')->setWidth(24);

$filename = 'sales-report-' . $dateFrom . '-' . $dateTo . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
$writer->save('php://output');
exit;