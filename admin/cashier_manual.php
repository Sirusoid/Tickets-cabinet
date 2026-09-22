<?php
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/permissions.php';
require_login();

if (!isset($pdo) || !($pdo instanceof PDO) || !user_has_permission($pdo, 'cashier_manual', false)) {
    http_response_code(403);
    exit('Доступ к руководству кассира запрещён.');
}

$pdfPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'CASHIER_MANUAL_RU.pdf';
if (!is_file($pdfPath) || !is_readable($pdfPath)) {
    http_response_code(404);
    exit('Файл руководства кассира не найден.');
}

$download = isset($_GET['download']) && (string)$_GET['download'] === '1';
$filename = 'CASHIER_MANUAL_RU.pdf';

header('Content-Type: application/pdf');
header('Content-Length: ' . (string)filesize($pdfPath));
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . $filename . '"');
readfile($pdfPath);
exit;
