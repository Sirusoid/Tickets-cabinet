<?php
/**
 * Временный скрипт для исправления устаревшего значения payments.bcc_backref_path в БД.
 * Загрузите файл в корень сайта, откройте в браузере, затем удалите с сервера.
 */
require_once __DIR__ . '/init.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    die('Database connection failed');
}

$stmt = $pdo->prepare("UPDATE settings SET value = :value WHERE `key` = 'payments.bcc_backref_path'");
$stmt->execute([':value' => '/payment/bcc/return.php']);

echo "Updated rows: " . $stmt->rowCount() . "<br>";

$check = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'payments.bcc_backref_path' LIMIT 1");
$check->execute();
$value = $check->fetchColumn();
echo "Current value: " . htmlspecialchars((string)$value) . "<br>";

echo "Done. Delete this file from the server.";
