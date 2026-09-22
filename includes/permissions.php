<?php
// Проверка прав по матрице security.role_permissions.

if (!function_exists('user_has_permission')) {
    function user_has_permission(PDO $pdo, string $permission, bool $fallback = false): bool
    {
        $role = (string)($_SESSION['user']['role'] ?? '');
        if ($role === 'admin') {
            return true;
        }
        if ($permission === '') {
            return $fallback;
        }
        if (!function_exists('settings_get_value')) {
            require_once __DIR__ . '/settings_manager.php';
        }
        $rawMatrix = function_exists('settings_get_value')
            ? (string)settings_get_value($pdo, 'security.role_permissions', '')
            : '';
        $matrix = function_exists('settings_decode_json_value')
            ? settings_decode_json_value($rawMatrix, [])
            : [];
        if (!is_array($matrix) || empty($matrix)) {
            return $fallback;
        }
        $roleMatrix = $matrix[$role] ?? null;
        if (!is_array($roleMatrix)) {
            return $fallback;
        }
        if (!array_key_exists($permission, $roleMatrix)) {
            // Совместимость со старыми сохранёнными матрицами до добавления новых разделов.
            $defaultPermissions = [
                'cashier_manual' => [
                    'manager' => true,
                    'cashier' => true,
                ],
            ];
            if (isset($defaultPermissions[$permission][$role])) {
                return (bool)$defaultPermissions[$permission][$role];
            }
            return $fallback;
        }
        return !empty($roleMatrix[$permission]);
    }
}
