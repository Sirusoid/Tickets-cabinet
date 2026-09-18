#!/usr/bin/env php
<?php
// Удаляет записи error_logs старше срока из Настройки → Общие.

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    error_log('cron_cleanup_error_logs: CLI only');
    exit(1);
}

$lockFile = sys_get_temp_dir() . '/cleanup_error_logs.lock';
$fp = @fopen($lockFile, 'c');
if (!$fp) {
    error_log('cron_cleanup_error_logs: cannot open lock file');
    exit(1);
}
if (!flock($fp, LOCK_EX | LOCK_NB)) {
    fclose($fp);
    exit(0);
}

try {
    require_once __DIR__ . '/../init.php';
    require_once __DIR__ . '/../includes/settings_manager.php';
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new RuntimeException('PDO is unavailable.');
    }

    $retentionDays = function_exists('settings_get_value')
        ? (int)settings_get_value($pdo, 'system.error_log_retention_days', 90)
        : 90;
    $retentionDays = max(1, min(3650, $retentionDays));
    $cutoff = (new DateTimeImmutable('now'))->modify('-' . $retentionDays . ' days')->format('Y-m-d H:i:s');

    $stmt = $pdo->prepare('DELETE FROM error_logs WHERE created_at < :cutoff');
    $stmt->execute([':cutoff' => $cutoff]);
    error_log('cron_cleanup_error_logs: deleted ' . (int)$stmt->rowCount() . ' records older than ' . $retentionDays . ' days');

    flock($fp, LOCK_UN);
    fclose($fp);
    exit(0);
} catch (Throwable $exception) {
    error_log('cron_cleanup_error_logs: ' . $exception->getMessage());
    flock($fp, LOCK_UN);
    fclose($fp);
    exit(1);
}
