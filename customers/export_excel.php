<?php
require_once __DIR__ . '/../init.php';
require_login();
require_once __DIR__ . '/../includes/excel.php';

if (!function_exists('excel_autoload') || !excel_autoload()) {
    http_response_code(500);
    exit('PhpSpreadsheet не установлен или недоступен.');
}

$nameFilter = trim((string)($_GET['full_name'] ?? ''));
$phoneFilter = normalize_customer_phone(trim((string)($_GET['phone'] ?? '')));
$where = [];
$params = [];

if ($nameFilter !== '') {
    $where[] = 'full_name LIKE :full_name';
    $params[':full_name'] = '%' . $nameFilter . '%';
}
if ($phoneFilter !== '') {
    $where[] = '(phone LIKE :phone OR phone LIKE :phone_digits)';
    $params[':phone'] = '%' . $phoneFilter . '%';
    $params[':phone_digits'] = '%' . customer_phone_digits($phoneFilter) . '%';
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
try {
    $customers = db_fetch_all(
        'SELECT id, full_name, phone, email
         FROM customers' . $whereSql . '
         ORDER BY full_name ASC, id ASC',
        $params
    );
} catch (Throwable $exception) {
    error_log('customers/export_excel.php: ' . $exception->getMessage());
    http_response_code(500);
    exit('Не удалось загрузить список клиентов для экспорта.');
}

$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Клиенты');
$sheet->fromArray([
    ['Клиенты', null, null, null, null],
    ['Фильтр по ФИО', $nameFilter !== '' ? $nameFilter : 'Все', null, null, null],
    ['Фильтр по телефону', $phoneFilter !== '' ? $phoneFilter : 'Все', null, null, null],
    [],
    ['№', 'ID', 'Полное имя', 'Телефон', 'E-mail'],
], null, 'A1');

$rowNumber = 6;
foreach ($customers as $index => $customer) {
    $sheet->fromArray([[
        $index + 1,
        (int)$customer['id'],
        (string)($customer['full_name'] ?? ''),
        format_customer_phone($customer['phone'] ?? ''),
        (string)($customer['email'] ?? ''),
    ]], null, 'A' . $rowNumber++);
}

$sheet->mergeCells('A1:E1');
$sheet->mergeCells('B2:E2');
$sheet->mergeCells('B3:E3');
$sheet->getStyle('A1:E1')->getFont()->setBold(true)->setSize(16);
$sheet->getStyle('A5:E5')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
$sheet->getStyle('A5:E5')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FF2563EB');
$sheet->freezePane('A6');
foreach (range('A', 'E') as $column) {
    $sheet->getColumnDimension($column)->setAutoSize(true);
}
$sheet->getColumnDimension('C')->setWidth(32);
$sheet->getColumnDimension('D')->setWidth(20);
$sheet->getColumnDimension('E')->setWidth(32);

$filename = 'customers-' . date('Y-m-d') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

try {
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
} catch (Throwable $exception) {
    error_log('customers/export_excel.php XLSX writer: ' . $exception->getMessage());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo 'Не удалось сформировать XLSX-файл.';
}
exit;