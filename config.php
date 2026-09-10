<?php
// config.php — централизованные настройки приложения
// Все основные константы должны задаваться только в этом файле.

// Локальный fallback для Plesk/Apache, где переменные окружения PHP-FPM не проброшены.
// В production предпочтительно задавать их в окружении домена, а не хранить в web-root.
$envFile = __DIR__ . DIRECTORY_SEPARATOR . '.env';
if (is_file($envFile) && is_readable($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $envLine) {
        $envLine = trim($envLine);
        if ($envLine === '' || $envLine[0] === '#' || strpos($envLine, '=') === false) {
            continue;
        }
        [$envKey, $envValue] = explode('=', $envLine, 2);
        $envKey = trim($envKey);
        $envValue = trim($envValue);
        if ($envValue !== '' && (($envValue[0] === '"' && substr($envValue, -1) === '"') || ($envValue[0] === "'" && substr($envValue, -1) === "'"))) {
            $envValue = substr($envValue, 1, -1);
        }
        if ($envKey !== '' && getenv($envKey) === false) {
            putenv($envKey . '=' . $envValue);
        }
    }
}

// База данных
// База данных. Значения задаются переменными окружения на сервере.
define('DB_HOST', getenv('ZHASSAHNA_DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('ZHASSAHNA_DB_PORT') ?: '3306');
define('DB_NAME', getenv('ZHASSAHNA_DB_NAME') ?: '');
define('DB_USER', getenv('ZHASSAHNA_DB_USER') ?: '');
define('DB_PASS', getenv('ZHASSAHNA_DB_PASS') ?: '');

// Окружение приложения: 'development' или 'production'
// Раскомментируйте для локальной разработки:
define('APP_ENV', getenv('ZHASSAHNA_APP_ENV') ?: 'production');
if (!defined('APP_ENV')) {
    define('APP_ENV', getenv('APP_ENV') ?: 'production');
}

// Показывать ошибки (строго как строка 'true'/'false' для совместимости с существующим кодом)
define('DEBUG_SHOW_ERRORS', (defined('APP_ENV') && APP_ENV === 'development') ? 'true' : 'false');

// Название приложения
define('APP_NAME', 'Алматинский театр "Жас сахна" им. Б.Омарова');

// Базовый URL админки/кабинета (без завершающего слэша)
define('BASE_URL', 'https://cabinet.zhassahna.kz');

// Публичный URL для онлайн-продаж и эквайринг-колбэков (без завершающего слэша)
define('PUBLIC_BASE_URL', 'https://cabinet.zhassahna.kz');

// Путь для BACKREF-возврата после оплаты BCC (публичная страница результата)
define('PUBLIC_BCC_BACKREF_PATH', '/payment/bcc/return.php');

// Домен, на котором установлен виджет афиши (для CORS)
define('TILDA_WIDGET_ORIGIN', 'https://zhassahna.kz');

// Секрет для подписи публичных ссылок на билеты (order + uid).
// При смене этого ключа старые ссылки перестанут работать.
define('TICKET_PUBLIC_SECRET', getenv('ZHASSAHNA_TICKET_PUBLIC_SECRET') ?: '');

// Таймзона по умолчанию
define('DEFAULT_TIMEZONE', 'Asia/Almaty');
date_default_timezone_set(DEFAULT_TIMEZONE);

// Утилита получения значения окружения (совместимость с существующим кодом)
function env($key, $default = null)
{
    return defined($key) ? constant($key) : (getenv($key) !== false ? getenv($key) : $default);
}
