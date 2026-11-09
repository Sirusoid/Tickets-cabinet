<?php
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/pdf_helpers.php';
require_login();

$rawUids = $_GET['uids'] ?? [];
if (!is_array($rawUids)) {
    $rawUids = explode(',', (string)$rawUids);
}
$uids = array_values(array_unique(array_filter(array_map('trim', $rawUids))));
$uids = array_slice($uids, 0, 100);
$orderNumber = trim((string)($_GET['order_number'] ?? ''));

if ($uids === []) {
    http_response_code(400);
    exit('Не выбраны билеты.');
}

if (!class_exists('ZipArchive')) {
    http_response_code(500);
    exit('Расширение ZIP недоступно на сервере.');
}

$placeholders = implode(',', array_fill(0, count($uids), '?'));
$stmt = $pdo->prepare("SELECT ticket_uid FROM tickets WHERE ticket_uid IN ($placeholders) ORDER BY id ASC");
$stmt->execute($uids);
$foundUids = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

if ($foundUids === []) {
    http_response_code(404);
    exit('Билеты не найдены.');
}

$tempPath = tempnam(sys_get_temp_dir(), 'tickets-');
if ($tempPath === false) {
    http_response_code(500);
    exit('Не удалось подготовить архив.');
}

$zip = new ZipArchive();
if ($zip->open($tempPath, ZipArchive::OVERWRITE) !== true) {
    @unlink($tempPath);
    http_response_code(500);
    exit('Не удалось создать архив.');
}

try {
    foreach ($foundUids as $uid) {
        $pdfError = null;
        if (!ticket_pdf_generate_by_ticket_uid((string)$uid, false, $pdfError)) {
            throw new RuntimeException($pdfError ?: 'Не удалось создать PDF.');
        }
        $pdfPath = ticket_pdf_file_path((string)$uid);
        if (!is_file($pdfPath) || filesize($pdfPath) <= 0) {
            throw new RuntimeException('PDF билет не найден.');
        }
        $zip->addFile($pdfPath, preg_replace('/[^A-Za-z0-9_-]+/', '_', (string)$uid) . '.pdf');
    }
    $zip->close();
} catch (Throwable $e) {
    $zip->close();
    @unlink($tempPath);
    error_log('tickets/download_batch.php: ' . $e->getMessage());
    http_response_code(500);
    exit('Не удалось собрать архив билетов.');
}

header('Content-Type: application/zip');
$safeOrderNumber = preg_replace('/[^A-Za-z0-9_-]+/', '_', $orderNumber);
$archiveName = $safeOrderNumber !== '' ? 'ticket_' . $safeOrderNumber . '.zip' : 'tickets-' . date('Y-m-d-His') . '.zip';
header('Content-Disposition: attachment; filename="' . $archiveName . '"');
header('Content-Length: ' . filesize($tempPath));
header('Cache-Control: no-store');
readfile($tempPath);
@unlink($tempPath);
exit;
