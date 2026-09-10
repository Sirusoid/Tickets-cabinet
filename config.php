<?php
// config.php — централизованные настройки приложения
// Все основные константы должны задаваться только в этом файле.

// База данных
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'p-318444_theater_db');
define('DB_USER', 'p-318444_theater_user');
define('DB_PASS', 'Zhas_sahna_123');

// Окружение приложения: 'development' или 'production'
// Раскомментируйте для локальной разработки:
define('APP_ENV', 'development');
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
define('TICKET_PUBLIC_SECRET', '90675a6dd98235c5a29e9369a4f473aa7cd047726055a2f5bf46801be4eae25a');

// Таймзона по умолчанию
define('DEFAULT_TIMEZONE', 'Asia/Almaty');
date_default_timezone_set(DEFAULT_TIMEZONE);

// Утилита получения значения окружения (совместимость с существующим кодом)
function env($key, $default = null)
{
    return defined($key) ? constant($key) : (getenv($key) !== false ? getenv($key) : $default);
}
