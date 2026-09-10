<?php
// /tools/cron_refresh_schedule_statuses.php - обновление статусов в расписании, запускается с помощью cron команды
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "CLI only\n";
    exit(1);
}

require_once __DIR__ . '/../init.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    fwrite(STDERR, "DB connection is not available\n");
    exit(1);
}

if (!function_exists('schedule_refresh_statuses')) {
    fwrite(STDERR, "schedule_refresh_statuses() is not available\n");
    exit(1);
}

try {
    $updated = schedule_refresh_statuses($pdo);
    $now = date('Y-m-d H:i:s');
    echo '[' . $now . '] schedule statuses refreshed: ' . (int)$updated . " updated\n";
    exit(0);
} catch (Throwable $e) {
    error_log('cron_refresh_schedule_statuses failed: ' . $e->getMessage());
    fwrite(STDERR, 'cron_refresh_schedule_statuses failed: ' . $e->getMessage() . "\n");
    exit(1);
}