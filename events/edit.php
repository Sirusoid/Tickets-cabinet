<?php
// events/edit.php
require __DIR__ . '/../init.php';
require_login();

require_once __DIR__ . '/../includes/image_helpers.php';

$use_sidebar = true;
$active_menu = 'events';
$page_title_meta = 'Редактировать событие - админка';
$panel_title = 'Редактировать событие';
$panel_subtitle = 'Изменение данных события';
$panel_actions = [
    ['href'=>'/events/list.php','label'=>'К списку мероприятий','class'=>'btn btn-primary btn-sm js-panel-back']
];
// версия скрипта увеличена, чтобы сбросить кеш при обновлениях
$page_scripts[] = '/assets/js/events-add.js?v=1.0.7';

$categories = function_exists('db_fetch_all') ? db_fetch_all('SELECT id, name FROM event_categories ORDER BY name ASC') : [];
$genres     = function_exists('db_fetch_all') ? db_fetch_all('SELECT id, name FROM event_genres ORDER BY name ASC') : [];
$languages  = function_exists('db_fetch_all') ? db_fetch_all('SELECT id, code, label FROM event_languages ORDER BY id ASC') : [];

function safe_trim($v) { return is_scalar($v) ? trim((string)$v) : ''; }

$error = null;
$event = null;

// Determine event id (GET or POST)
$id = intval($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    redirect('/events/list.php');
}

// Load existing event
if (function_exists('db_fetch_one')) {
    $event = db_fetch_one('SELECT * FROM events WHERE id = ? LIMIT 1', [$id]);
} else {
    $rows = function_exists('db_fetch_all') ? db_fetch_all('SELECT * FROM events WHERE id = ? LIMIT 1', [$id]) : [];
    $event = !empty($rows) ? $rows[0] : null;
}

if (!$event) {
    $error = 'Мероприятие не найдено.';
}

// Prepare $old values from event or defaults
$old = [
    'id' => $id,
    'title' => $event['title'] ?? '',
    'short_description' => $event['short_description'] ?? '',
    'full_description' => $event['full_description'] ?? '',
    'category' => $event['category_id'] ?? '',
    'genre_id' => $event['genre_id'] ?? '',
    'image' => $event['image'] ?? '',
    'status' => $event['status'] ?? 'published',
    'duration_minutes' => $event['duration_minutes'] ?? 100,
    // canonical field in DB is cast_list (JSON string). We'll display human-readable names in textarea.
    'cast_list' => $event['cast_list'] ?? '',
    'director' => $event['director'] ?? '',
    'producer' => $event['producer'] ?? '',
    'choreographer' => $event['choreographer'] ?? '',
    'sound_director' => $event['sound_director'] ?? '',
    'lighting_director' => $event['lighting_director'] ?? '',
    'costume_designer' => $event['costume_designer'] ?? '',
    'age_limit' => $event['age_limit'] ?? 0,
    'other_details' => $event['other_details'] ?? '',
    'language_id' => $event['language_id'] ?? '',
    // NEW: page URL (nullable)
    'page_url' => $event['page_url'] ?? ''
];

// If cast_list in DB is JSON array -> convert to human-readable "Name1, Name2"
if (!empty($old['cast_list'])) {
    $raw = $old['cast_list'];
    $decoded = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $names = array_values(array_filter(array_map(function($v){ return is_scalar($v) ? trim((string)$v) : null; }, $decoded)));
        $old['cast_list'] = implode(', ', $names);
    } else {
        // If it's legacy numeric IDs list, try to resolve to names
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
                    if (!empty($names)) $old['cast_list'] = implode(', ', $names);
                }
            }
        } else {
            // otherwise leave as-is (string of names)
            $old['cast_list'] = (string)$raw;
        }
    }
}

/*
  Server-side POST handling (non-AJAX fallback).
  Normalizes cast_list into JSON string before saving.
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    $csrf = $_POST['csrf_token'] ?? '';
    if (empty($csrf) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
        $error = 'Неверный CSRF токен.';
    } else {
        // Collect posted values
        $old['title'] = safe_trim($_POST['title'] ?? '');
        $old['short_description'] = safe_trim($_POST['short_description'] ?? '');
        $old['full_description'] = safe_trim($_POST['full_description'] ?? '');
        $old['category'] = intval($_POST['category'] ?? 0);
        $old['genre_id'] = intval($_POST['genre_id'] ?? 0);
        $old['status'] = in_array($_POST['status'] ?? '', ['draft','published','archived'], true) ? $_POST['status'] : 'published';
        $old['duration_minutes'] = max(0, intval($_POST['duration_minutes'] ?? 120));
        // read cast_list from POST (may be JSON string or human-readable)
        $posted_cast = isset($_POST['cast_list']) ? $_POST['cast_list'] : (isset($_POST['actors']) ? $_POST['actors'] : '');
        $old['cast_list'] = is_scalar($posted_cast) ? (string)$posted_cast : '';
        $old['director'] = safe_trim($_POST['director'] ?? '');
        $old['producer'] = safe_trim($_POST['producer'] ?? '');
        $old['choreographer'] = safe_trim($_POST['choreographer'] ?? '');
        $old['sound_director'] = safe_trim($_POST['sound_director'] ?? '');
        $old['lighting_director'] = safe_trim($_POST['lighting_director'] ?? '');
        $old['costume_designer'] = safe_trim($_POST['costume_designer'] ?? '');
        $old['age_limit'] = max(0, intval($_POST['age_limit'] ?? 0));
        $old['other_details'] = safe_trim($_POST['other_details'] ?? '');
        $old['language_id'] = intval($_POST['language_id'] ?? 0);

        // NEW: page URL
        $old['page_url'] = safe_trim($_POST['page_url'] ?? '');

        // Non-JS fallback: handle uploaded file if present
        if (!empty($_FILES['image_file']) && ($_FILES['image_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $f = $_FILES['image_file'];
            $allowedExt = ['jpg','jpeg','png','gif','webp'];
            $mimeMap = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];
            $ext = strtolower(pathinfo($f['name'] ?? '', PATHINFO_EXTENSION));
            $mime = $f['type'] ?? '';
            if (isset($mimeMap[$mime])) $ext = $mimeMap[$mime];
            if (!in_array($ext, $allowedExt, true)) {
                $error = 'Недопустимый тип файла изображения.';
            } else {
                $projectRoot = realpath(__DIR__ . '/..') ?: (__DIR__ . '/..');
                $uploadBaseFs = rtrim($projectRoot, '/') . '/uploads/images';
                $targetFsDir = $uploadBaseFs . '/events';
                if (!is_dir($targetFsDir)) {
                    if (!@mkdir($targetFsDir, 0755, true)) {
                        $error = 'Не удалось создать папку для загрузки изображений.';
                    }
                }
                if (!$error && !is_writable($targetFsDir)) {
                    $error = 'Папка для загрузки изображений недоступна для записи.';
                }
                if (!$error) {
                    $safeName = function_exists('transliterate_filename') ? transliterate_filename($f['name'] ?? ('image.' . $ext)) : preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', basename($f['name'] ?? ('image.' . $ext)));
                    $rand = function_exists('rand_hex') ? rand_hex(6) : bin2hex(random_bytes(3));
                    $basename = time() . '_' . $rand . '_' . $safeName;
                    $destFs = rtrim($targetFsDir, '/') . '/' . $basename;
                    if (@move_uploaded_file($f['tmp_name'], $destFs)) {
                        @chmod($destFs, 0644);
                        // remove old image if exists
                        if (!empty($event['image']) && function_exists('safe_unlink_in_dir')) {
                            safe_unlink_in_dir($event['image'], '/uploads/images/events');
                        }
                        $old['image'] = '/uploads/images/events/' . $basename;
                    } else {
                        $error = 'Не удалось сохранить загруженное изображение.';
                    }
                }
            }
        } else {
            // keep existing image if not replaced
            $old['image'] = $event['image'] ?? '';
        }

        if (!$error && $old['title'] === '') {
            $error = 'Укажите название мероприятия.';
        }

        if (!$error) {
            // Normalize cast_list into JSON string for DB
            $cast_list_json = null;
            $raw = trim((string)($old['cast_list'] ?? ''));

            if ($raw !== '') {
                // try parse JSON first
                $decoded = json_decode($raw, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $arr = array_values(array_filter(array_map(function($v){ return is_scalar($v) ? trim((string)$v) : null; }, $decoded)));
                    $cast_list_json = json_encode(array_values($arr), JSON_UNESCAPED_UNICODE);
                } else {
                    // legacy numeric IDs -> resolve to names
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
                        // treat as human-readable list of names
                        $parts = array_filter(array_map(function($s){ return trim((string)$s); }, preg_split('/[,\n;]+/', $raw)));
                        $cast_list_json = json_encode(array_values($parts), JSON_UNESCAPED_UNICODE);
                    }
                }
            } else {
                $cast_list_json = json_encode([], JSON_UNESCAPED_UNICODE);
            }

            // Prepare update
            if (!function_exists('db_query')) {
                error_log('events/edit.php: db_query not available');
                $error = 'Внутренняя ошибка сервера (БД недоступна).';
            } else {
                try {
                    db_query('UPDATE events SET
                        title = ?, short_description = ?, full_description = ?, image = ?,
                        category_id = ?, genre_id = ?, duration_minutes = ?, status = ?,
                        cast_list = ?, director = ?, producer = ?, choreographer = ?, sound_director = ?,
                        lighting_director = ?, costume_designer = ?, age_limit = ?, other_details = ?, language_id = ?, page_url = ?
                        WHERE id = ?',
                        [
                            $old['title'],
                            $old['short_description'],
                            $old['full_description'],
                            $old['image'] ?: null,
                            $old['category'] ?: null,
                            $old['genre_id'] ?: null,
                            $old['duration_minutes'],
                            $old['status'],
                            $cast_list_json,
                            $old['director'],
                            $old['producer'],
                            $old['choreographer'],
                            $old['sound_director'],
                            $old['lighting_director'],
                            $old['costume_designer'],
                            $old['age_limit'],
                            $old['other_details'],
                            $old['language_id'] ?: null,
                            $old['page_url'] ?: null,
                            $id
                        ]
                    );
                    redirect('events/list.php');
                } catch (Throwable $e) {
                    error_log('events/edit.php update error: ' . $e->getMessage());
                    $error = 'Ошибка при обновлении мероприятия.';
                }
            }
        }
    }
}

// If server-side error exists, pass to JS for modal display
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/panel.php';
?>
<link rel="stylesheet" href="/assets/css/forms.css">
<link rel="stylesheet" href="/assets/css/admin.css">
<link rel="stylesheet" href="/assets/css/actors_modal.css">

<div class="panel panel--content">
    <div class="panel__inner">

        <form id="event-form" method="post" action="edit.php?id=<?= h($id) ?>" enctype="multipart/form-data" class="form-grid" novalidate>
            <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="image" id="selected-image" value="<?= h($old['image']) ?>">
            <input type="hidden" name="id" value="<?= h($id) ?>">

            <!-- Основная информация -->
            <div class="form-block">
                <h2>Основная информация</h2>

                <div class="form-group">
                    <label>Название</label>
                    <input type="text" name="title" value="<?= h($old['title']) ?>" required>
                </div>

                <div class="form-group">
                    <label>URL страницы</label>
                    <input type="text" name="page_url" value="<?= h($old['page_url']) ?>" placeholder="/events/slug-or-path">
                </div>

                <div class="form-group">
                    <label>Краткое описание</label>
                    <textarea name="short_description" rows="3"><?= h($old['short_description']) ?></textarea>
                </div>

                <div class="form-group">
                    <label>Полное описание</label>
                    <textarea name="full_description" rows="6"><?= h($old['full_description']) ?></textarea>
                </div>

                <div class="form-row-3">
                    <div class="form-group">
                        <label>Возрастные ограничения</label>
                        <input type="number" name="age_limit" min="0" max="100" value="<?= h($old['age_limit']) ?>">
                    </div>

                    <div class="form-group">
                        <label>Продолжительность (минут)</label>
                        <input type="number" name="duration_minutes" min="0" value="<?= h($old['duration_minutes']) ?>">
                    </div>

                    <div class="form-group">
                        <label>Статус</label>
                        <select name="status">
                            <option value="published" <?= $old['status']==='published'?'selected':'' ?>>Опубликовано</option>
                            <option value="draft" <?= $old['status']==='draft'?'selected':'' ?>>Черновик</option>
                            <option value="archived" <?= $old['status']==='archived'?'selected':'' ?>>В архиве</option>
                        </select>
                    </div>
                </div>

                <div class="form-row-3">
                    <div class="form-group">
                        <label>Категория</label>
                        <select name="category">
                            <option value="">Выберите категорию</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= h($category['id']) ?>" <?= $old['category']==$category['id']?'selected':'' ?>>
                                    <?= h($category['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Жанр</label>
                        <select name="genre_id">
                            <option value="">Выберите жанр</option>
                            <?php foreach ($genres as $g): ?>
                                <option value="<?= h($g['id']) ?>" <?= $old['genre_id']==$g['id']?'selected':'' ?>>
                                    <?= h($g['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Язык мероприятия</label>
                        <select name="language_id" id="event-language">
                            <option value="">Выберите язык</option>
                            <?php foreach ($languages as $lang): ?>
                                <option value="<?= intval($lang['id']) ?>" <?= $old['language_id']==intval($lang['id'])?'selected':'' ?>>
                                    <?= h($lang['label']) ?> (<?= h(strtoupper($lang['code'])) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Афиша -->
            <div class="form-block">
                <h2>Афиша</h2>

                <div id="image-preview" class="poster-preview">
                    <?php if ($old['image']): ?>
                        <img id="poster-img" src="<?= h($old['image']) ?>" alt="Превью">
                    <?php else: ?>
                        <span id="poster-empty">Нет изображения</span>
                    <?php endif; ?>
                </div>

                <!-- Hidden file input; upload-first handled by JS -->
                <input type="file" id="image-file-input" accept="image/*" name="image_file" style="display:none;">

                <div class="image-actions-inline">
                    <button type="button" id="select-image-button" class="btn btn-ghost">Выбрать</button>
                    <button type="button" id="delete-image-button" class="btn btn-danger">Удалить</button>
                </div>
            </div>

            <!-- Участники -->
            <div class="form-block">
                <h2>Участники</h2>

                <div class="form-row-3">
                    <div class="form-group"><label>Режиссёр</label><input type="text" name="director" maxlength="40" value="<?= h($old['director']) ?>"></div>
                    <div class="form-group"><label>Продюсер</label><input type="text" name="producer" maxlength="40" value="<?= h($old['producer']) ?>"></div>
                    <div class="form-group"><label>Хореограф</label><input type="text" name="choreographer" maxlength="40" value="<?= h($old['choreographer']) ?>"></div>
                </div>

                <div class="form-row-3">
                    <div class="form-group"><label>Звукорежиссёр</label><input type="text" name="sound_director" maxlength="40" value="<?= h($old['sound_director']) ?>"></div>
                    <div class="form-group"><label>Режиссёр по свету</label><input type="text" name="lighting_director" maxlength="40" value="<?= h($old['lighting_director']) ?>"></div>
                    <div class="form-group"><label>Костюмер</label><input type="text" name="costume_designer" maxlength="40" value="<?= h($old['costume_designer']) ?>"></div>
                </div>

                <div class="form-row-2 actors-row">
                    <div class="form-group">
                        <label>Актёры</label>
                        <!-- textarea name is cast_list (canonical); id kept as actors-field for JS compatibility -->
                        <textarea name="cast_list" id="actors-field" rows="7"><?= h($old['cast_list']) ?></textarea>

                        <button type="button" id="open-actors-modal" class="btn btn-primary btn-sm">
                            Добавить актёров
                        </button>
                    </div>

                    <div class="form-group">
                        <label>Другие участники</label>
                        <textarea name="other_details" class="other-details-textarea"><?= h($old['other_details']) ?></textarea>
                    </div>
                </div>
            </div>
        </form>

        <div class="form-actions-bottom">
            <button type="submit" form="event-form" class="btn btn-primary">Сохранить</button>
            <a href="/events/list.php" class="btn btn-ghost js-back">Отмена</a>
        </div>

    </div>
</div>

<!-- Actors modal (UI only; content loaded by JS) -->
<div id="actors-modal" class="actors-modal" aria-hidden="true" style="display:none;">
  <div class="actors-modal-backdrop" id="actors-modal-backdrop"></div>

  <div class="actors-modal-content" role="dialog" aria-modal="true" aria-labelledby="actors-modal-title" tabindex="-1">
    <div class="actors-modal-header">
      <h2 id="actors-modal-title">Выберите актёров</h2>
      <button type="button" class="actors-modal-close btn btn-ghost" id="actors-modal-close" aria-label="Закрыть">&times;</button>
    </div>

    <div class="actors-modal-body" id="actors-list">
      <div class="loading">Загрузка...</div>
    </div>

    <div class="actors-modal-actions">
      <button type="button" id="actors-apply" class="btn btn-primary">Добавить выбранных</button>
      <button type="button" id="actors-cancel" class="btn btn-ghost">Отмена</button>
    </div>
  </div>
</div>

<script>
/* Передаём серверную ошибку в JS — footer предоставляет модалку/toast */
<?php if (!empty($error)): ?>
window.serverErrorMessage = <?= json_encode($error, JSON_UNESCAPED_UNICODE) ?>;
<?php else: ?>
window.serverErrorMessage = null;
<?php endif; ?>
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
