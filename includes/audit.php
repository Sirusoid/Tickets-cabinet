<?php
// Единый журнал действий сотрудников. Таблица audit_logs уже используется проектом.

if (!function_exists('audit_log_event')) {
    function audit_log_event(
        PDO $pdo,
        string $action,
        string $entityType,
        ?int $entityId = null,
        ?string $entityName = null,
        array $before = [],
        array $after = []
    ): void {
        try {
            $user = $_SESSION['user'] ?? [];
            $userId = !empty($user['id']) ? (int)$user['id'] : null;
            $role = (string)($user['role'] ?? '');
            $performedByType = $role === 'admin' ? 'admin' : ($userId ? 'staff' : 'system');
            $ip = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
            $userAgent = trim((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));

            $stmt = $pdo->prepare(
                'INSERT INTO audit_logs
                    (user_id, performed_by_type, action, entity_type, entity_name, entity_id,
                     before_data, after_data, ip_address, user_agent, created_at)
                 VALUES
                    (:user_id, :performed_by_type, :action, :entity_type, :entity_name, :entity_id,
                     :before_data, :after_data, :ip_address, :user_agent, NOW())'
            );
            $stmt->execute([
                ':user_id' => $userId,
                ':performed_by_type' => $performedByType,
                ':action' => mb_substr($action, 0, 100),
                ':entity_type' => mb_substr($entityType, 0, 100),
                ':entity_name' => $entityName !== null ? mb_substr($entityName, 0, 255) : null,
                ':entity_id' => $entityId,
                ':before_data' => $before ? json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                ':after_data' => $after ? json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                ':ip_address' => $ip !== '' ? mb_substr($ip, 0, 50) : null,
                ':user_agent' => $userAgent !== '' ? mb_substr($userAgent, 0, 255) : null,
            ]);
        } catch (Throwable $e) {
            error_log('[AUDIT] Не удалось записать действие: ' . $e->getMessage());
        }
    }
}
