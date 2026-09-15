#!/usr/bin/env php
<?php
// /tools/cron_cleanup_holds.php
// Удаляет только просроченные ручные резервы из cash_holds
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

    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new Exception('PDO not initialized (check init.php path)');
    }

    // Cron очищает только ручные резервы кассира. Онлайн-холды управляются
    // callback-ами BCC и сроком payment hold, а клиентские — своим TTL.
    $pdo->beginTransaction();

    if ($maxDeletePerRun && is_int($maxDeletePerRun) && $maxDeletePerRun > 0) {
        // Удаляем пачкой с LIMIT (MySQL поддерживает LIMIT в DELETE)
        $stmt = $pdo->prepare("DELETE FROM cash_holds
            WHERE expires_at IS NOT NULL
                AND expires_at <= NOW()
                AND (meta IS NULL OR meta = '')
            LIMIT :lim");
        $stmt->bindValue(':lim', (int)$maxDeletePerRun, PDO::PARAM_INT);
        $stmt->execute();
        $deleted = $stmt->rowCount();
    } else {
        $stmt = $pdo->prepare("DELETE FROM cash_holds
            WHERE expires_at IS NOT NULL
                AND expires_at <= NOW()
                AND (meta IS NULL OR meta = '')");
        $stmt->execute();
        $deleted = $stmt->rowCount();
    }

    $pdo->commit();

    error_log('cron_cleanup_holds: OK deleted ' . (int)$deleted . ' expired manual holds');

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
