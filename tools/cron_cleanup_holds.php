#!/usr/bin/env php
<?php
// /tools/cron_cleanup_holds.php
// Удаляет просроченные записи из cash_holds
// Запуск: /usr/bin/php /tools/cron_cleanup_holds.php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    error_log("cron_cleanup_holds: CLI only");
    exit(1);
}

$lockFile = sys_get_temp_dir() . '/cleanup_holds.lock';
$maxDeletePerRun = 1000; // лимит удаляемых записей за один запуск (0 = без лимита)

// Блокировка процесса (файл-лок)
$fp = @fopen($lockFile, 'c');
if (!$fp) {
    error_log("cron_cleanup_holds: ERROR cannot open lock file $lockFile");
    exit(1);
}
if (!flock($fp, LOCK_EX | LOCK_NB)) {
    // другой процесс уже работает
    error_log("cron_cleanup_holds: INFO another cleanup process is running, exiting");
    fclose($fp);
    exit(0);
}

try {
    // Подключение приложения: поправьте путь под ваш проект
    // Этот файл должен инициализировать $pdo (PDO instance)
    require_once __DIR__ . '/../init.php';
    require_once __DIR__ . '/../includes/settings_manager.php';

    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new Exception('PDO not initialized (check init.php path)');
    }

    $autoCancelHours = function_exists('settings_get_value')
        ? (int)settings_get_value($pdo, 'tickets.auto_cancel_hours', 0)
        : 0;

    // Выполняем очистку холдов и старых неоплаченных онлайн-сессий в одной транзакции.
    $pdo->beginTransaction();

    if ($maxDeletePerRun && is_int($maxDeletePerRun) && $maxDeletePerRun > 0) {
        // Удаляем пачкой с LIMIT (MySQL поддерживает LIMIT в DELETE)
        $stmt = $pdo->prepare("DELETE FROM cash_holds WHERE expires_at IS NOT NULL AND expires_at <= NOW() LIMIT :lim");
        $stmt->bindValue(':lim', (int)$maxDeletePerRun, PDO::PARAM_INT);
        $stmt->execute();
        $deleted = $stmt->rowCount();
    } else {
        $stmt = $pdo->prepare("DELETE FROM cash_holds WHERE expires_at IS NOT NULL AND expires_at <= NOW()");
        $stmt->execute();
        $deleted = $stmt->rowCount();
    }

    $cancelledSessions = 0;
    if ($autoCancelHours > 0) {
        $cutoff = (new DateTimeImmutable('now'))->modify('-' . $autoCancelHours . ' hours')->format('Y-m-d H:i:s');
        $sessionStmt = $pdo->prepare("SELECT id FROM payment_sessions
            WHERE status = 'pending' AND provider = 'bcc' AND created_at <= :cutoff
            LIMIT 1000 FOR UPDATE");
        $sessionStmt->execute([':cutoff' => $cutoff]);
        $sessionIds = $sessionStmt->fetchAll(PDO::FETCH_COLUMN, 0);
        if (!empty($sessionIds)) {
            $sessionPlaceholders = implode(',', array_fill(0, count($sessionIds), '?'));
            $updateSessions = $pdo->prepare("UPDATE payment_sessions SET status = 'cancelled', updated_at = NOW() WHERE id IN ($sessionPlaceholders) AND status = 'pending'");
            $updateSessions->execute($sessionIds);
            $cancelledSessions = $updateSessions->rowCount();

            // Удаляем только холды, созданные онлайн-платежом BCC.
            $deleteOnlineHolds = $pdo->prepare("DELETE FROM cash_holds WHERE session_id IN ($sessionPlaceholders) AND meta LIKE '%widget_online_payment%'");
            $deleteOnlineHolds->execute($sessionIds);
        }
    }

    $pdo->commit();

    error_log('cron_cleanup_holds: OK deleted ' . (int)$deleted . ' expired holds, cancelled ' . (int)$cancelledSessions . ' unpaid online sessions');

    // Освобождаем блокировку
    flock($fp, LOCK_UN);
    fclose($fp);
    exit(0);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('cron_cleanup_holds: ERROR cleanup failed: ' . $e->getMessage());
    try { flock($fp, LOCK_UN); fclose($fp); } catch (Exception $ex) {}
    exit(1);
}
