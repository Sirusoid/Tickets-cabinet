<?php
// ajax/event.php
require_once __DIR__ . '/../init.php';
if (function_exists('require_login')) require_login();

header('Content-Type: application/json; charset=utf-8');

function respond($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function norm_str($v) {
    $s = trim((string)($v ?? ''));
    return $s === '' ? null : $s;
}

// Only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['success' => false, 'message' => 'Invalid request method'], 405);
}

$action = norm_str($_POST['action'] ?? $_REQUEST['action'] ?? '');
if (!$action) {
    respond(['success' => false, 'message' => 'Missing action parameter'], 400);
}
if (!in_array($action, ['create','update','edit','delete'], true)) {
    respond(['success' => false, 'message' => 'Unsupported action'], 400);
}

// CSRF check
$csrfProvided = trim($_POST['csrf_token'] ?? $_REQUEST['csrf_token'] ?? '');
if (empty($csrfProvided) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfProvided)) {
    respond(['success' => false, 'message' => 'Invalid CSRF token'], 403);
}

// Helper: ensure db helpers exist
if (!function_exists('db_query')) {
    respond(['success' => false, 'message' => 'DB helper not available'], 500);
}

/* -----------------------
   DELETE
   ----------------------- */
if ($action === 'delete') {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if ($id <= 0) {
        respond(['success' => false, 'message' => 'Missing or invalid id'], 400);
    }

    try {
        // Optionally: check permissions here (require admin, etc.)
        // Remove event row
        db_query('DELETE FROM events WHERE id = ?', [$id]);
        respond(['success' => true, 'message' => 'Мероприятие удалено']);
    } catch (Throwable $e) {
        error_log('ajax/event.php delete error: ' . $e->getMessage());
        respond(['success' => false, 'message' => 'Ошибка при удалении мероприятия'], 500);
    }
}

/* -----------------------
   CREATE / UPDATE
   ----------------------- */
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$title = norm_str($_POST['title'] ?? '');
$short_description = norm_str($_POST['short_description'] ?? '');
$full_description = norm_str($_POST['full_description'] ?? '');
// canonical field: cast_list (may be JSON string or human-readable)
$cast_list_raw = isset($_POST['cast_list']) ? $_POST['cast_list'] : (isset($_POST['actors']) ? $_POST['actors'] : null);
$image = norm_str($_POST['image'] ?? '');
$category = isset($_POST['category']) && $_POST['category'] !== '' ? intval($_POST['category']) : null;
$genre_id = isset($_POST['genre_id']) && $_POST['genre_id'] !== '' ? intval($_POST['genre_id']) : null;
$duration_minutes = isset($_POST['duration_minutes']) ? max(0, intval($_POST['duration_minutes'])) : null;
$status = norm_str($_POST['status'] ?? 'published');
$director = norm_str($_POST['director'] ?? '');
$producer = norm_str($_POST['producer'] ?? '');
$choreographer = norm_str($_POST['choreographer'] ?? '');
$sound_director = norm_str($_POST['sound_director'] ?? '');
$lighting_director = norm_str($_POST['lighting_director'] ?? '');
$costume_designer = norm_str($_POST['costume_designer'] ?? '');
$age_limit = isset($_POST['age_limit']) ? max(0, intval($_POST['age_limit'])) : null;
$other_details = norm_str($_POST['other_details'] ?? '');
$language_id = isset($_POST['language_id']) && $_POST['language_id'] !== '' ? intval($_POST['language_id']) : null;

// NEW: page_url field
$page_url = norm_str($_POST['page_url'] ?? '');

// Basic validation
if (empty($title)) {
    respond(['success' => false, 'message' => 'Укажите название мероприятия.'], 400);
}

// Normalize cast_list: accept JSON array, or comma/semicolon/newline-separated names, or legacy numeric IDs list
$cast_list_json = null;
if ($cast_list_raw !== null) {
    $raw = trim((string)$cast_list_raw);

    // 1) If it's valid JSON array -> use it
    $decoded = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $arr = array_values(array_filter(array_map(function($v){ return is_scalar($v) ? trim((string)$v) : null; }, $decoded)));
        $cast_list_json = json_encode(array_values($arr), JSON_UNESCAPED_UNICODE);
    } else {
        // 2) If it looks like numeric ID list (legacy) -> try to resolve to names
        if (preg_match('/^[0-9,\s;]+$/', $raw)) {
            $ids = array_filter(array_map('intval', preg_split('/[,\s;]+/', $raw)));
            if (!empty($ids) && function_exists('db_fetch_all')) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $rows = db_fetch_all("SELECT id, actor_name FROM event_actors WHERE id IN ($placeholders)", $ids);
                if ($rows) {
                    $map = [];
                    foreach ($rows as $r) $map[intval($r['id'])] = $r['actor_name'];
                    $names = [];
                    foreach ($ids as $iid) {
                        if (isset($map[$iid])) $names[] = $map[$iid];
                    }
                    $cast_list_json = json_encode(array_values($names), JSON_UNESCAPED_UNICODE);
                } else {
                    $cast_list_json = json_encode([], JSON_UNESCAPED_UNICODE);
                }
            } else {
                $cast_list_json = json_encode([], JSON_UNESCAPED_UNICODE);
            }
        } else {
            // 3) Otherwise treat as human-readable list of names separated by comma/semicolon/newline
            $parts = array_filter(array_map(function($s){ return trim((string)$s); }, preg_split('/[,\n;]+/', $raw)));
            $cast_list_json = json_encode(array_values($parts), JSON_UNESCAPED_UNICODE);
        }
    }
} else {
    $cast_list_json = json_encode([], JSON_UNESCAPED_UNICODE);
}

// Database operations
try {
    if ($action === 'create') {
        $sql = 'INSERT INTO events (
            title, short_description, full_description, image,
            category_id, genre_id, duration_minutes, status,
            cast_list, director, producer, choreographer, sound_director,
            lighting_director, costume_designer, age_limit, other_details,
            language_id, page_url, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())';

        db_query($sql, [
            $title,
            $short_description,
            $full_description,
            $image ?: null,
            $category,
            $genre_id,
            $duration_minutes,
            $status,
            $cast_list_json,
            $director,
            $producer,
            $choreographer,
            $sound_director,
            $lighting_director,
            $costume_designer,
            $age_limit,
            $other_details,
            $language_id,
            $page_url ?: null
        ]);

        respond(['success' => true, 'message' => 'Мероприятие создано']);
    }

    // update/edit
    if (in_array($action, ['update','edit'], true)) {
        if ($id <= 0) {
            respond(['success' => false, 'message' => 'Missing or invalid id'], 400);
        }

        // Ensure event exists
        $existing = null;
        if (function_exists('db_fetch_one')) {
            $existing = db_fetch_one('SELECT id, image FROM events WHERE id = ? LIMIT 1', [$id]);
        } else {
            $rows = function_exists('db_fetch_all') ? db_fetch_all('SELECT id, image FROM events WHERE id = ? LIMIT 1', [$id]) : [];
            $existing = !empty($rows) ? $rows[0] : null;
        }
        if (!$existing) {
            respond(['success' => false, 'message' => 'Мероприятие не найдено'], 404);
        }

        // If image changed, try to remove old file (best-effort)
        $oldImage = $existing['image'] ?? null;
        if ($image && $oldImage && $image !== $oldImage) {
            if (function_exists('safe_unlink_in_dir')) {
                try { safe_unlink_in_dir($oldImage, '/uploads/images/events'); } catch (Throwable $e) {}
            }
        }

        $fields = [
            'title' => $title,
            'short_description' => $short_description,
            'full_description' => $full_description,
            'image' => $image ?: null,
            'category_id' => $category,
            'genre_id' => $genre_id,
            'duration_minutes' => $duration_minutes,
            'status' => $status,
            'cast_list' => $cast_list_json,
            'director' => $director,
            'producer' => $producer,
            'choreographer' => $choreographer,
            'sound_director' => $sound_director,
            'lighting_director' => $lighting_director,
            'costume_designer' => $costume_designer,
            'age_limit' => $age_limit,
            'other_details' => $other_details,
            'language_id' => $language_id,
            'page_url' => $page_url
        ];

        $setParts = [];
        $params = [];
        foreach ($fields as $col => $val) {
            $setParts[] = "$col = ?";
            $params[] = $val;
        }
        $params[] = $id;

        $sql = 'UPDATE events SET ' . implode(', ', $setParts) . ' WHERE id = ?';
        db_query($sql, $params);

        respond(['success' => true, 'message' => 'Мероприятие обновлено']);
    }

    respond(['success' => false, 'message' => 'Unknown action'], 400);

} catch (Throwable $e) {
    error_log('ajax/event.php error: ' . $e->getMessage());
    respond(['success' => false, 'message' => 'Внутренняя ошибка сервера'], 500);
}
