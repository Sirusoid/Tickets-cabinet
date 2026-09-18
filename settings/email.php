<?php
require_once __DIR__ . '/../init.php';
require_login();
require_once __DIR__ . '/../includes/settings_manager.php';

if (isset($pdo) && $pdo instanceof PDO) {
    $emailSettings = [
        ['notifications.order_email_enabled', 'Отправлять письма с билетами', '1', 'bool', 'После успешной онлайн-покупки клиент получает благодарность, дату и время сеанса, ссылки на PDF, PDF-вложения и ссылку на возврат.', 1],
        ['notifications.email_from', 'Email отправителя', getenv('ZHASSAHNA_EMAIL_FROM') ?: 'noreply@zhassahna.kz', 'string', 'Адрес отправителя. Желательно использовать адрес домена театра.', 2],
        ['notifications.email_from_name', 'Имя отправителя', defined('APP_NAME') ? (string)APP_NAME : 'Театр «Жас сахна»', 'string', 'Название, которое увидит клиент в почтовом ящике.', 3],
        ['notifications.email_reply_to', 'Адрес для ответа', getenv('ZHASSAHNA_EMAIL_REPLY_TO') ?: '', 'string', 'Ответы клиентов будут направляться на этот адрес.', 4],
        ['notifications.email_feedback', 'Email обратной связи', getenv('ZHASSAHNA_EMAIL_FEEDBACK') ?: '', 'string', 'Адрес обратной связи театра. Используется, если адрес для ответа не задан.', 5],
    ];

    foreach ($emailSettings as [$key, $label, $value, $type, $description, $sortOrder]) {
        settings_upsert_value($pdo, $key, $label, $value, $type, 'email', $description, 1, $sortOrder);
    }

    // Перенос существующих почтовых настроек из старой категории без изменения ключей/значений.
    $pdo->exec("UPDATE settings SET category = 'email' WHERE category = 'notifications' AND `key` IN ('notifications.order_email_enabled', 'notifications.email_from', 'notifications.email_from_name', 'notifications.email_reply_to', 'notifications.email_feedback')");
}

$settingsPageKey = 'email';
require __DIR__ . '/_page.php';