<?php
// cabinet.zhassahna.kz/widget/diag.php
// Диагностический endpoint для проверки виджета афиши внутри Tickets cabinet.

require_once __DIR__ . '/../init.php';

header('Content-Type: application/json; charset=utf-8');

$checks = [];

// 1. Заголовки запроса
$checks['request_headers'] = function_exists('getallheaders') ? getallheaders() : [];
$checks['server_software'] = $_SERVER['SERVER_SOFTWARE'] ?? 'unknown';
$checks['request_method'] = $_SERVER['REQUEST_METHOD'] ?? 'unknown';

// 2. Конфигурация и файлы
$checks['files'] = [
    'config_exists' => file_exists(__DIR__ . '/../config.php'),
    'init_exists' => file_exists(__DIR__ . '/../init.php'),
];

// 3. Подключение к БД
$dbOk = false;
$dbError = null;
$rowCount = 0;
if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $dbOk = true;
        $stmt = $pdo->query("SELECT COUNT(*) AS cnt FROM schedules WHERE start_time >= NOW() AND status IN ('upcoming','active')");
        $rowCount = (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        $dbOk = false;
        $dbError = $e->getMessage();
    }
} else {
    $dbError = 'PDO not available from init.php';
}

$checks['db'] = [
    'connected' => $dbOk,
    'error' => $dbError,
    'upcoming_sessions_count' => $rowCount,
];

// 4. Константы (без пароля)
$checks['constants'] = [
    'PUBLIC_BASE_URL' => defined('PUBLIC_BASE_URL') ? constant('PUBLIC_BASE_URL') : null,
    'BASE_URL' => defined('BASE_URL') ? constant('BASE_URL') : null,
    'TILDA_WIDGET_ORIGIN' => defined('TILDA_WIDGET_ORIGIN') ? constant('TILDA_WIDGET_ORIGIN') : null,
    'DB_HOST' => defined('DB_HOST') ? constant('DB_HOST') : null,
    'DB_NAME' => defined('DB_NAME') ? constant('DB_NAME') : null,
];

echo json_encode($checks, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
