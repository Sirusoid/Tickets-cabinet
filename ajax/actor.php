<?php
// ajax/actor.php
require_once __DIR__ . '/../init.php';
if (function_exists('require_login')) require_login();

header('Content-Type: application/json; charset=utf-8');

/**
 * JSON responder
 */
function respond(array $payload, int $status = 200): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Normalize string to null if empty
 */
function norm_str($v): ?string {
    $s = trim((string)($v ?? ''));
    return $s === '' ? null : $s;
}

/**
 * CSRF check
 */
function check_csrf($token): bool {
    return !empty($token) && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Role check helper
 */
function require_role(array $roles): void {
    $role = $_SESSION['user']['role'] ?? null;
    if (!$role || !in_array($role, $roles, true)) {
        respond(['success' => false, 'message' => 'Недостаточно прав.'], 403);
    }
}

/**
 * Convert public URL to filesystem path
 */
function public_to_fs(string $publicUrl): string {
    $fsBase = realpath(__DIR__ . '/../public') ?: (__DIR__ . '/../public');
    if (strpos($publicUrl, '/') === 0) {
        return rtrim($fsBase, '/') . $publicUrl;
    }
    return rtrim($fsBase, '/') . '/' . ltrim($publicUrl, '/');
}

/**
 * Safely unlink a file only if it is inside allowed public directory
 * allowedPublicDir example: '/uploads/images/actors'
 */
function safe_unlink_in_dir(string $publicUrl, string $allowedPublicDir): bool {
    if (empty($publicUrl)) return false;
    $fs = public_to_fs($publicUrl);
    $allowedFs = realpath(__DIR__ . '/../public' . $allowedPublicDir);
    if ($allowedFs === false) return false;
    $allowedFs = rtrim($allowedFs, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    $real = realpath($fs);
    if ($real === false) return false;
    if (strpos($real, $allowedFs) !== 0) {
        error_log('safe_unlink_in_dir: attempt to delete outside allowed dir: ' . $real);
        return false;
    }
    if (is_file($real)) return @unlink($real);
    return false;
}

/**
 * Simple DB helpers (wrap existing helpers if available)
 */
function db_fetch_one_safe($sql, $params = []) {
    try {
        return db_fetch_one($sql, $params);
    } catch (Throwable $e) {
        error_log('db_fetch_one_safe error: ' . $e->getMessage());
        return false;
    }
}

function db_query_safe($sql, $params = []) {
    try {
        return db_query($sql, $params);
    } catch (Throwable $e) {
        error_log('db_query_safe error: ' . $e->getMessage());
        return false;
    }
}

// Read action
$action = norm_str($_REQUEST['action'] ?? '');
if ($action === null) respond(['success' => false, 'message' => 'Не указано действие (action).'], 400);

try {
    $pdo = db_connect();
} catch (Throwable $e) {
    respond(['success' => false, 'message' => 'Ошибка подключения к БД.'], 500);
}

// ROUTES
try {

    // LIST — simple list for modal
    if ($action === 'list') {
        $rows = db_fetch_all("SELECT id, actor_name, photo_url FROM event_actors ORDER BY actor_name ASC");
        respond(['success' => true, 'data' => $rows]);
    }

    // GET — single actor
    if ($action === 'get') {
        $id = intval($_GET['id'] ?? 0);
        if ($id <= 0) respond(['success' => false, 'message' => 'Неверный ID'], 400);

        $actor = db_fetch_one("SELECT * FROM event_actors WHERE id = ? LIMIT 1", [$id]);
        if (!$actor) respond(['success' => false, 'message' => 'Актёр не найден'], 404);

        respond(['success' => true, 'data' => $actor]);
    }

    // CREATE
    if ($action === 'create') {
        require_role(['admin', 'manager']);

        $csrf = $_POST['csrf_token'] ?? '';
        if (!check_csrf($csrf)) respond(['success' => false, 'message' => 'CSRF token invalid'], 403);

        $actor_name = norm_str($_POST['actor_name'] ?? '');
        if (!$actor_name) respond(['success' => false, 'message' => 'Введите имя актёра'], 400);

        $birth_date     = norm_str($_POST['birth_date'] ?? null);
        $email          = norm_str($_POST['email'] ?? null);
        $phone          = norm_str($_POST['phone'] ?? null);
        $photo_url      = norm_str($_POST['photo_url'] ?? null);
        $role_name      = norm_str($_POST['role_name'] ?? null);
        $character_name = norm_str($_POST['character_name'] ?? null);
        $is_main_cast   = intval($_POST['is_main_cast'] ?? 1);
        $is_guest       = intval($_POST['is_guest'] ?? 0);
        $sort_order     = norm_str($_POST['sort_order'] ?? null);
        $bio            = norm_str($_POST['bio'] ?? null);
        $social_links   = norm_str($_POST['social_links'] ?? null);

        $res = db_query_safe("
            INSERT INTO event_actors (
                actor_uid, event_id, actor_name, birth_date, email, phone,
                photo_url, role_name, character_name, is_main_cast, is_guest,
                sort_order, bio, social_links, created_at, updated_at
            ) VALUES (
                NULL, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW()
            )
        ", [
            $actor_name, $birth_date, $email, $phone,
            $photo_url, $role_name, $character_name,
            $is_main_cast, $is_guest, $sort_order,
            $bio, $social_links
        ]);

        if ($res === false) respond(['success' => false, 'message' => 'Ошибка при создании актёра'], 500);

        $id = $pdo->lastInsertId();
        $actor = db_fetch_one("SELECT id, actor_name, photo_url FROM event_actors WHERE id = ?", [$id]);

        respond(['success' => true, 'message' => 'Актёр создан', 'data' => $actor]);
    }

    // UPDATE
    if ($action === 'update') {
        require_role(['admin', 'manager']);

        $csrf = $_POST['csrf_token'] ?? '';
        if (!check_csrf($csrf)) respond(['success' => false, 'message' => 'CSRF token invalid'], 403);

        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) respond(['success' => false, 'message' => 'Неверный ID'], 400);

        $exists = db_fetch_one("SELECT id, photo_url FROM event_actors WHERE id = ? LIMIT 1", [$id]);
        if (!$exists) respond(['success' => false, 'message' => 'Актёр не найден'], 404);

        // If remove_photo requested
        if (!empty($_POST['remove_photo'])) {
            $old = $exists['photo_url'] ?? '';
            if (!empty($old)) {
                // delete only inside actors folder
                safe_unlink_in_dir($old, '/uploads/images/actors');
            }
            $ok = db_query_safe("UPDATE event_actors SET photo_url = NULL, updated_at = NOW() WHERE id = ?", [$id]);
            if ($ok === false) respond(['success' => false, 'message' => 'Ошибка при удалении фото'], 500);
            respond(['success' => true, 'message' => 'Фото удалено']);
        }

        // Collect fields
        $actor_name     = norm_str($_POST['actor_name'] ?? '');
        if (!$actor_name) respond(['success' => false, 'message' => 'Введите имя актёра'], 400);

        $birth_date     = norm_str($_POST['birth_date'] ?? null);
        $email          = norm_str($_POST['email'] ?? null);
        $phone          = norm_str($_POST['phone'] ?? null);
        $photo_url_post = norm_str($_POST['photo_url'] ?? null); // optional direct URL from image upload
        $role_name      = norm_str($_POST['role_name'] ?? null);
        $character_name = norm_str($_POST['character_name'] ?? null);
        $is_main_cast   = intval($_POST['is_main_cast'] ?? 1);
        $is_guest       = intval($_POST['is_guest'] ?? 0);
        $sort_order     = norm_str($_POST['sort_order'] ?? null);
        $bio            = norm_str($_POST['bio'] ?? null);
        $social_links   = norm_str($_POST['social_links'] ?? null);

        // If social_links not provided, build from fields (backwards compatibility)
        if ($social_links === null) {
            $social_links = json_encode([
                'email' => $email,
                'phone' => $phone
            ], JSON_UNESCAPED_UNICODE);
        }

        // Fetch current photo_url BEFORE update so we can delete it after successful replacement
        $oldPhoto = $exists['photo_url'] ?? '';

        // Determine new photo URL (if provided)
        $new_photo_url = $photo_url_post;

        // Update DB
        if ($new_photo_url !== null) {
            $sql = "UPDATE event_actors
                    SET actor_name = ?, birth_date = ?, email = ?, phone = ?,
                        photo_url = ?, role_name = ?, character_name = ?,
                        is_main_cast = ?, is_guest = ?, sort_order = ?,
                        bio = ?, social_links = ?, updated_at = NOW()
                    WHERE id = ?";
            $params = [
                $actor_name, $birth_date, $email, $phone,
                $new_photo_url, $role_name, $character_name,
                $is_main_cast, $is_guest, $sort_order,
                $bio, $social_links, $id
            ];
        } else {
            $sql = "UPDATE event_actors
                    SET actor_name = ?, birth_date = ?, email = ?, phone = ?,
                        role_name = ?, character_name = ?,
                        is_main_cast = ?, is_guest = ?, sort_order = ?,
                        bio = ?, social_links = ?, updated_at = NOW()
                    WHERE id = ?";
            $params = [
                $actor_name, $birth_date, $email, $phone,
                $role_name, $character_name,
                $is_main_cast, $is_guest, $sort_order,
                $bio, $social_links, $id
            ];
        }

        $ok = db_query_safe($sql, $params);
        if ($ok === false) {
            // If a new photo was uploaded but DB update failed, try to remove the newly uploaded file to avoid orphan files
            if (!empty($new_photo_url)) {
                safe_unlink_in_dir($new_photo_url, '/uploads/images/actors');
            }
            respond(['success' => false, 'message' => 'Ошибка при сохранении профиля'], 500);
        }

        // If we uploaded a new photo and there was an old one — delete the old file (only if different)
        if ($new_photo_url !== null && !empty($oldPhoto) && $oldPhoto !== $new_photo_url) {
            safe_unlink_in_dir($oldPhoto, '/uploads/images/actors');
        }

        $actor = db_fetch_one("SELECT id, actor_name, photo_url FROM event_actors WHERE id = ?", [$id]);
        respond(['success' => true, 'message' => 'Актёр обновлён', 'data' => $actor]);
    }

    // DELETE actor
    if ($action === 'delete') {
        require_role(['admin']);

        $csrf = $_POST['csrf_token'] ?? '';
        if (!check_csrf($csrf)) respond(['success' => false, 'message' => 'CSRF token invalid'], 403);

        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) respond(['success' => false, 'message' => 'Неверный ID'], 400);

        $exists = db_fetch_one("SELECT id, photo_url FROM event_actors WHERE id = ?", [$id]);
        if (!$exists) respond(['success' => false, 'message' => 'Актёр не найден'], 404);

        // delete photo file if exists
        $old = $exists['photo_url'] ?? '';
        if (!empty($old)) {
            safe_unlink_in_dir($old, '/uploads/images/actors');
        }

        db_query_safe("DELETE FROM event_actors WHERE id = ?", [$id]);

        respond(['success' => true, 'message' => 'Актёр удалён']);
    }

    // Unknown action
    respond(['success' => false, 'message' => 'Unknown action'], 400);

} catch (Throwable $e) {
    error_log('ajax/actor.php error: ' . $e->getMessage());
    respond(['success' => false, 'message' => 'Внутренняя ошибка сервера'], 500);
}
