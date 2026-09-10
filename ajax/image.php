<?php
// ajax/image.php
// Обработчик загрузки изображений и удаления файлов
// Сохраняет файлы в <project_root>/uploads/images/<target>/
// Логи пишутся в <project_root>/logs/image_uploads_debug.log

require_once __DIR__ . '/../init.php';
if (function_exists('require_login')) require_login();

// Подключаем общие хелперы для работы с изображениями
require_once __DIR__ . '/../includes/image_helpers.php';

header('Content-Type: application/json; charset=utf-8');

function respond(array $payload, int $status = 200): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function norm_str($v): ?string {
    $s = trim((string)($v ?? ''));
    return $s === '' ? null : $s;
}

/* ----------------- Logging ----------------- */
$projectLogsDir = __DIR__ . '/../logs';
if (!is_dir($projectLogsDir)) {
    @mkdir($projectLogsDir, 0755, true);
}
$logFile = rtrim($projectLogsDir, '/') . '/image_uploads_debug.log';
function dbg_log($msg) {
    global $logFile;
    @file_put_contents($logFile, date('c') . ' ' . $msg . PHP_EOL, FILE_APPEND);
}

/* ----------------- Config ----------------- */
$debugMode = (isset($_REQUEST['debug']) && $_REQUEST['debug'] == '1') ? true : false;
dbg_log("REQUEST start action=" . (norm_str($_REQUEST['action'] ?? '') ?? 'null') . " debug=" . ($debugMode ? '1' : '0'));

/* ----------------- Allowed targets ----------------- */
$allowedTargets = ['actors', 'events'];

/* ----------------- Action routing ----------------- */
$action = norm_str($_REQUEST['action'] ?? '');
if ($action === 'delete') {
    // Delete a file by public URL (AJAX delete)
    // Expect POST: file_url (public path like /uploads/images/actors/xxx.png) and target param
    $fileUrlRaw = trim($_POST['file_url'] ?? $_REQUEST['file_url'] ?? '');
    $target = norm_str($_POST['target'] ?? $_REQUEST['target'] ?? '');

    // Validate target early
    if ($target === null || !in_array($target, $allowedTargets, true)) {
        respond(['success' => false, 'message' => 'Invalid or missing target parameter'], 400);
    }

    // Normalize file URL: accept full URL or path, convert to public path starting with '/'
    $fileUrl = $fileUrlRaw;
    if (preg_match('#^https?://#i', $fileUrl)) {
        $parts = parse_url($fileUrl);
        if ($parts !== false && isset($parts['path'])) {
            $fileUrl = $parts['path'];
            if (!empty($parts['query'])) $fileUrl .= '?' . $parts['query'];
        }
    }

    // If user passed a bare filename, prepend target public dir
    if ($fileUrl !== '' && strpos($fileUrl, '/') !== 0) {
        $fileUrl = '/uploads/images/' . trim($target, '/') . '/' . ltrim($fileUrl, '/');
    }

    dbg_log("Delete request normalized file_url=" . $fileUrl . " target=" . ($target ?? 'null'));

    if ($fileUrl === '') {
        respond(['success' => false, 'message' => 'Missing file_url parameter'], 400);
    }

    // Require CSRF token for delete
    $csrfProvided = trim($_POST['csrf_token'] ?? $_REQUEST['csrf_token'] ?? '');
    if (empty($csrfProvided) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfProvided)) {
        dbg_log("CSRF token mismatch for delete");
        respond(['success' => false, 'message' => 'Invalid CSRF token'], 403);
    }

    // Determine allowedPublicDir
    $allowedPublicDir = '/uploads/images/' . trim($target, '/');

    try {
        $ok = function_exists('safe_unlink_in_dir') ? safe_unlink_in_dir($fileUrl, $allowedPublicDir) : false;
        if ($ok) {
            dbg_log("Deleted file: {$fileUrl}");
            respond(['success' => true, 'message' => 'File deleted']);
        } else {
            dbg_log("Failed to delete file (not found or not allowed): {$fileUrl}");
            respond(['success' => false, 'message' => 'Failed to delete file (not found or not allowed)'], 500);
        }
    } catch (Exception $e) {
        dbg_log("Exception during delete: " . $e->getMessage());
        respond(['success' => false, 'message' => 'Server error while deleting file'], 500);
    }
}

/* ----------------- Upload handling ----------------- */
if ($action !== 'upload') {
    dbg_log("Unsupported action: " . var_export($action, true));
    respond(['success' => false, 'message' => 'Unsupported action'], 400);
}

$target = norm_str($_REQUEST['target'] ?? '');
if ($target === null || !in_array($target, $allowedTargets, true)) {
    dbg_log("Invalid or missing target parameter: " . var_export($target, true));
    respond(['success' => false, 'message' => 'Invalid or missing target parameter'], 400);
}

if (empty($_FILES['image_file'])) {
    dbg_log("No file in \$_FILES");
    respond(['success' => false, 'message' => 'No file uploaded (field image_file expected)'], 400);
}

$file = $_FILES['image_file'];
dbg_log("FILES info: name=" . ($file['name'] ?? '') . " tmp=" . ($file['tmp_name'] ?? '') . " size=" . ($file['size'] ?? 0) . " error=" . ($file['error'] ?? 'n/a'));

if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
    dbg_log("Upload error code: " . intval($file['error'] ?? -1));
    respond(['success' => false, 'message' => 'Upload error: ' . intval($file['error'] ?? -1)], 400);
}

if (!is_uploaded_file($file['tmp_name'])) {
    dbg_log("is_uploaded_file returned false for tmp: " . ($file['tmp_name'] ?? ''));
    $ini = [
        'upload_tmp_dir' => ini_get('upload_tmp_dir'),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size'),
        'file_uploads' => ini_get('file_uploads'),
        'open_basedir' => ini_get('open_basedir'),
    ];
    dbg_log("PHP ini: " . json_encode($ini));
    respond(['success' => false, 'message' => 'File not uploaded via HTTP POST', 'debug' => ['php_ini' => $ini]], 400);
}

/* ----------------- Limits and types ----------------- */
$maxBytes = 5 * 1024 * 1024; // 5 MB
if (($file['size'] ?? 0) > $maxBytes) {
    dbg_log("File too large: " . ($file['size'] ?? 0));
    respond(['success' => false, 'message' => 'File too large (max 5MB)'], 400);
}

$allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$mimeMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
$ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
$mime = $file['type'] ?? '';
if (isset($mimeMap[$mime])) $ext = $mimeMap[$mime];
if (!in_array($ext, $allowedExt, true)) {
    dbg_log("Invalid file type: mime={$mime} ext={$ext}");
    respond(['success' => false, 'message' => 'Invalid file type'], 400);
}

/* ----------------- Paths (project root /uploads/images) ----------------- */
$projectRoot = realpath(__DIR__ . '/..') ?: (__DIR__ . '/..');
$uploadBaseFs = rtrim($projectRoot, '/') . '/uploads/images';
$targetFsDir = $uploadBaseFs . '/' . $target;           // filesystem: <project_root>/uploads/images/<target>
$targetPublicDir = '/uploads/images/' . $target;       // public URL path

dbg_log("Paths: projectRoot={$projectRoot} uploadBaseFs={$uploadBaseFs} targetFsDir={$targetFsDir}");

if (!is_dir($targetFsDir)) {
    dbg_log("Target dir missing, attempting mkdir: {$targetFsDir}");
    if (!@mkdir($targetFsDir, 0755, true)) {
        dbg_log("mkdir failed for {$targetFsDir}");
        respond(['success' => false, 'message' => 'Failed to create upload directory'], 500);
    }
}
if (!is_writable($targetFsDir)) {
    dbg_log("Target dir not writable: {$targetFsDir}");
    respond(['success' => false, 'message' => 'Upload directory not writable'], 500);
}

/* ----------------- Build safe filename and move ----------------- */
$safeName = function_exists('transliterate_filename') ? transliterate_filename($file['name'] ?? ('image.' . $ext)) : preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', basename($file['name'] ?? ('image.' . $ext)));
$basename = time() . '_' . (function_exists('rand_hex') ? rand_hex(6) : bin2hex(random_bytes(3))) . '_' . $safeName;
$destFs = rtrim($targetFsDir, '/') . '/' . $basename;
$destPublic = rtrim($targetPublicDir, '/') . '/' . $basename;

dbg_log("DestFs={$destFs} destPublic={$destPublic}");

$moved = false;
$moveErr = null;
if (@move_uploaded_file($file['tmp_name'], $destFs)) {
    $moved = true;
    dbg_log("move_uploaded_file succeeded");
} else {
    $moveErr = error_get_last();
    dbg_log("move_uploaded_file failed: " . json_encode($moveErr));
    // fallback to copy
    if (@copy($file['tmp_name'], $destFs)) {
        $moved = true;
        dbg_log("copy fallback succeeded");
    } else {
        $copyErr = error_get_last();
        dbg_log("copy fallback failed: " . json_encode($copyErr));
    }
}

if (!$moved) {
    $diag = [
        'tmp_exists' => file_exists($file['tmp_name']),
        'tmp_is_file' => is_file($file['tmp_name']),
        'tmp_is_readable' => is_readable($file['tmp_name']),
        'target_dir_exists' => is_dir($targetFsDir),
        'target_dir_writable' => is_writable($targetFsDir),
        'upload_tmp_dir' => ini_get('upload_tmp_dir'),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size'),
        'file_uploads' => ini_get('file_uploads'),
        'last_error_move' => $moveErr,
    ];
    dbg_log("Move failed diag: " . json_encode($diag));
    $resp = ['success' => false, 'message' => 'Failed to save uploaded file', 'diagnostic' => $diag];
    if ($debugMode) $resp['debug_log'] = $logFile;
    respond($resp, 500);
}

@chmod($destFs, 0644);

/* ----------------- Optional: remove_old param ----------------- */
$removeOld = !empty($_POST['remove_old']) ? true : false;
$oldUrl = norm_str($_POST['old_url'] ?? '');
if ($removeOld && $oldUrl !== null) {
    dbg_log("Requested remove_old for: {$oldUrl}");
    if (function_exists('safe_unlink_in_dir')) {
        safe_unlink_in_dir($oldUrl, '/' . trim($targetPublicDir, '/'));
    }
}

/* ----------------- Response ----------------- */
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$fullUrl = $scheme . '://' . $host . $destPublic;

dbg_log("Saved file: {$destFs} url_full={$fullUrl}");

$resp = [
    'success' => true,
    'message' => 'File uploaded',
    'data' => [
        'name' => $basename,
        'url'  => $destPublic,
        'url_full' => $fullUrl
    ]
];

if ($debugMode) {
    $resp['debug'] = [
        'saved_fs' => $destFs,
        'log' => $logFile,
        'php_ini' => [
            'upload_tmp_dir' => ini_get('upload_tmp_dir'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'file_uploads' => ini_get('file_uploads'),
            'open_basedir' => ini_get('open_basedir'),
        ]
    ];
}

respond($resp);
