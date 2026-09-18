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
}

