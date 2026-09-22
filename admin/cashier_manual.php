<?php
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/permissions.php';
require_login();

if (!isset($pdo) || !($pdo instanceof PDO) || !user_has_permission($pdo, 'cashier_manual', false)) {
    http_response_code(403);
    exit('Доступ к руководству кассира запрещён.');
}

$projectRoot = dirname(__DIR__);
$pdfPath = $projectRoot . DIRECTORY_SEPARATOR . 'CASHIER_MANUAL_RU.pdf';
$htmlPath = $projectRoot . DIRECTORY_SEPARATOR . 'CASHIER_MANUAL_RU.html';
$download = isset($_GET['download']) && (string)$_GET['download'] === '1';

if ($download) {
    if (!is_file($pdfPath) || !is_readable($pdfPath)) {
        http_response_code(404);
        exit('PDF-файл руководства кассира не найден.');
    }
    header('Content-Type: application/pdf');
    header('Content-Length: ' . (string)filesize($pdfPath));
    header('X-Content-Type-Options: nosniff');
    header('Content-Disposition: attachment; filename="CASHIER_MANUAL_RU.pdf"');
    readfile($pdfPath);
    exit;
}

if (!is_file($htmlPath) || !is_readable($htmlPath)) {
    http_response_code(404);
    exit('HTML-версия руководства не найдена. Запустите tools/generate_cashier_manual.php.');
}

$manualHtml = (string)file_get_contents($htmlPath);
$downloadUrl = '/admin/cashier_manual.php?download=1';
$toolbar = '<div style="position:sticky;top:0;z-index:1000;display:flex;justify-content:flex-end;gap:10px;padding:12px 18px;background:#173b67;box-shadow:0 2px 10px rgba(15,23,42,.18);font-family:Arial,sans-serif;">'
    . '<a href="' . htmlspecialchars($downloadUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" style="display:inline-block;padding:9px 16px;border-radius:7px;background:#18a957;color:#fff;text-decoration:none;font-weight:700;font-size:14px;">Скачать PDF</a>'
    . '</div>';

if (preg_match('/<body[^>]*>/i', $manualHtml)) {
    $manualHtml = preg_replace('/(<body[^>]*>)/i', '$1' . $toolbar, $manualHtml, 1);
} else {
    $manualHtml = $toolbar . $manualHtml;
}

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
echo $manualHtml;
