<?php
// init.php — централизованная инициализация приложения

// 1) Подключаем конфиг первым — все основные константы должны быть в config.php
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
} else {
    // Если config.php отсутствует — фатальная ошибка с понятным логом
    error_log('init.php: missing config.php');
    http_response_code(500);
    echo "Server configuration error (missing config).";
    exit;
}

// 2) Базовые настройки окружения и отображение ошибок
// APP_ENV и DEBUG_SHOW_ERRORS задаются в config.php
if (!defined('APP_ENV')) {
    define('APP_ENV', getenv('APP_ENV') ?: 'production');
}

if (defined('DEBUG_SHOW_ERRORS') && (string)DEBUG_SHOW_ERRORS === 'true') {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}

// 3) Безопасный запуск сессии (до любого вывода)
if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
    session_name('zhassahna_admin');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', $isHttps ? '1' : '0');
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

// 4) Пути
if (!defined('APP_ROOT')) {
    define('APP_ROOT', realpath(__DIR__));
}
if (!defined('APP_NAME')) {
    // Если по какой-то причине APP_NAME не задан в config.php — ставим безопасный дефолт
    define('APP_NAME', 'Application');
}

// 5) Подключаем зависимости (проверяем наличие файлов для понятных ошибок)
$requiredFiles = [
    __DIR__ . '/includes/db.php',
    __DIR__ . '/includes/helper.php',
    __DIR__ . '/includes/auth.php',
];

foreach ($requiredFiles as $f) {
    if (!file_exists($f)) {
        error_log("init.php: required file missing: {$f}");
        http_response_code(500);
        echo "Server configuration error (missing file).";
        exit;
    }
    require_once $f;
}

// 6) Подключаем БД и сохраняем PDO в $pdo (с обработкой ошибок)
$pdo = null;
try {
    // db_connect() должен быть определён в includes/db.php
    $pdo = db_connect();
    if (!$pdo) {
        throw new RuntimeException('db_connect() вернул пустое значение');
    }
} catch (Throwable $ex) {
    error_log('init.php: DB connection error: ' . $ex->getMessage());
    http_response_code(500);
    echo "Database connection error.";
    exit;
}

// 7) Подключаем PDF-хелперы, если они есть
if (file_exists(__DIR__ . '/includes/pdf_helpers.php')) {
    require_once __DIR__ . '/includes/pdf_helpers.php';
}

// 8) Глобальные хелперы — безопасные fallback'ы
if (!function_exists('h')) {
    function h($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

// 8) Инициализация CSRF токена (если ещё нет)
if (empty($_SESSION['csrf_token'])) {
    try {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    } catch (Throwable $e) {
        $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(16) ?: uniqid('', true));
    }
}

if (!function_exists('validate_csrf')) {
    /**
     * validate_csrf
     * Проверяет переданный токен CSRF против токена в сессии.
     * Возвращает true при совпадении, false в противном случае.
     *
     * @param string|null $token
     * @return bool
     */
    function validate_csrf($token) {
        if (!session_id()) @session_start();
        $sess = $_SESSION['csrf_token'] ?? '';
        if (!$sess || !$token) return false;
        if (function_exists('hash_equals')) {
            return hash_equals((string)$sess, (string)$token);
        }
        return ((string)$sess === (string)$token);
    }
}


// 9) Текущий пользователь (если auth.php предоставляет функцию get_current_user)
$currentUser = null;
if (function_exists('get_current_user')) {
    try {
        $currentUser = get_current_user(); // ожидается массив или null
    } catch (Throwable $e) {
        error_log('init.php: get_current_user() error: ' . $e->getMessage());
        $currentUser = null;
    }
}
$GLOBALS['currentUser'] = $currentUser;

// 10) Удобные короткие алиасы (если не определены в includes/db.php)
if (!function_exists('db_fetch_all')) {
    function db_fetch_all($sql, $params = []) {
        global $pdo;
        if (!isset($pdo) || !($pdo instanceof PDO)) {
            throw new RuntimeException('Database connection is not available');
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
if (!function_exists('db_fetch_one')) {
    function db_fetch_one($sql, $params = []) {
        global $pdo;
        if (!isset($pdo) || !($pdo instanceof PDO)) {
            throw new RuntimeException('Database connection is not available');
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
if (!function_exists('db_query')) {
    function db_query($sql, $params = []) {
        global $pdo;
        if (!isset($pdo) || !($pdo instanceof PDO)) {
            throw new RuntimeException('Database connection is not available');
        }
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
    }
}

// 11) Логирование окружения (опционально, только в development)
if (defined('APP_ENV') && APP_ENV === 'development') {
    error_log('init.php loaded. APP_ENV=' . APP_ENV . ' USER=' . ($currentUser['id'] ?? 'guest'));
}

// Единые защитные заголовки для административных страниц и AJAX.
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

// 12) Готово — $pdo, константы из config.php и $_SESSION доступны для остальных модулей
