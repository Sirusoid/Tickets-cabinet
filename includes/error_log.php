<?php
// Серверный журнал ошибок приложения. Детали сохраняются в БД без секретов подписи и данных карты.

if (!function_exists('system_error_log')) {
    function system_error_log(
        PDO $pdo,
        string $source,
        string $message,
        array $context = [],
        string $level = 'error'
    ): void {
        $level = in_array($level, ['error', 'warning', 'critical', 'info'], true) ? $level : 'error';
        $source = mb_substr(trim($source) !== '' ? trim($source) : 'application', 0, 100);
        $message = mb_substr(trim($message) !== '' ? trim($message) : 'Неизвестная ошибка.', 0, 1000);
        $contextJson = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $contextJson = $contextJson === false ? '{}' : $contextJson;

        $orderNumber = isset($context['ORDER']) ? (string)$context['ORDER'] : (string)($context['order'] ?? '');
        $responseCode = isset($context['RC']) ? (string)$context['RC'] : (string)($context['rc'] ?? '');
        $ticketUid = isset($context['ticket_uid']) ? (string)$context['ticket_uid'] : '';
        $userId = !empty($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null;

        try {
            $stmt = $pdo->prepare('INSERT INTO error_logs
                (level, source, message, context, user_id, order_number, ticket_uid, response_code, ip_address, user_agent, created_at)
                VALUES
                (:level, :source, :message, :context, :user_id, :order_number, :ticket_uid, :response_code, :ip_address, :user_agent, NOW())');
            $stmt->execute([
                ':level' => $level,
                ':source' => $source,
                ':message' => $message,
                ':context' => $contextJson,
                ':user_id' => $userId,
                ':order_number' => $orderNumber !== '' ? mb_substr($orderNumber, 0, 64) : null,
                ':ticket_uid' => $ticketUid !== '' ? mb_substr($ticketUid, 0, 100) : null,
                ':response_code' => $responseCode !== '' ? mb_substr($responseCode, 0, 32) : null,
                ':ip_address' => !empty($_SERVER['REMOTE_ADDR']) ? mb_substr((string)$_SERVER['REMOTE_ADDR'], 0, 50) : null,
                ':user_agent' => !empty($_SERVER['HTTP_USER_AGENT']) ? mb_substr((string)$_SERVER['HTTP_USER_AGENT'], 0, 255) : null,
            ]);
        } catch (Throwable $exception) {
            error_log('[APP ERROR] ' . $source . ': ' . $message . ' ' . $contextJson);
            error_log('[APP ERROR] Не удалось записать error_logs: ' . $exception->getMessage());
        }
    }

    if (!function_exists('system_error_level_from_severity')) {
        function system_error_level_from_severity(int $severity): string
        {
            if (in_array($severity, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
                return 'critical';
            }
            if (in_array($severity, [E_WARNING, E_CORE_WARNING, E_COMPILE_WARNING, E_USER_WARNING], true)) {
                return 'warning';
            }
            return 'info';
        }
    }

    if (!function_exists('system_error_request_context')) {
        function system_error_request_context(): array
        {
            return [
                'request_method' => (string)($_SERVER['REQUEST_METHOD'] ?? ''),
                'request_path' => (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: ''),
                'script' => (string)($_SERVER['SCRIPT_NAME'] ?? ''),
            ];
        }
    }

    if (!function_exists('system_register_error_handlers')) {
        function system_register_error_handlers(PDO $pdo): void
        {
            static $registered = false;
            if ($registered) {
                return;
            }
            $registered = true;

            set_error_handler(static function (
                int $severity,
                string $message,
                string $file,
                int $line
            ) use ($pdo): bool {
                // Suppressed errors (@) остаются в обычном server/PHP log и не создают шум в кабинете.
                if ((error_reporting() & $severity) === 0) {
                    return false;
                }
                system_error_log(
                    $pdo,
                    'php.error',
                    $message,
                    array_merge(system_error_request_context(), [
                        'severity' => $severity,
                        'file' => $file,
                        'line' => $line,
                    ]),
                    system_error_level_from_severity($severity)
                );
                return false;
            });

            set_exception_handler(static function (Throwable $exception) use ($pdo): void {
                system_error_log(
                    $pdo,
                    'php.exception',
                    $exception->getMessage(),
                    array_merge(system_error_request_context(), [
                        'exception' => get_class($exception),
                        'file' => $exception->getFile(),
                        'line' => $exception->getLine(),
                        'trace' => mb_substr($exception->getTraceAsString(), 0, 12000),
                    ]),
                    'critical'
                );
                error_log('[UNCAUGHT EXCEPTION] ' . get_class($exception) . ': ' . $exception->getMessage());

                if (!headers_sent()) {
                    http_response_code(500);
                    header('Content-Type: text/plain; charset=utf-8');
                }
                echo (defined('APP_ENV') && APP_ENV === 'development')
                    ? 'Server exception: ' . $exception->getMessage()
                    : 'Внутренняя ошибка сервера.';
            });

            register_shutdown_function(static function () use ($pdo): void {
                $lastError = error_get_last();
                if (!is_array($lastError)) {
                    return;
                }
                $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_CORE_WARNING, E_COMPILE_ERROR, E_COMPILE_WARNING, E_USER_ERROR];
                if (!in_array((int)($lastError['type'] ?? 0), $fatalTypes, true)) {
                    return;
                }
                system_error_log(
                    $pdo,
                    'php.shutdown',
                    (string)($lastError['message'] ?? 'Фатальная ошибка PHP.'),
                    array_merge(system_error_request_context(), [
                        'severity' => (int)($lastError['type'] ?? 0),
                        'file' => (string)($lastError['file'] ?? ''),
                        'line' => (int)($lastError['line'] ?? 0),
                    ]),
                    'critical'
                );
            });
        }
    }
}
