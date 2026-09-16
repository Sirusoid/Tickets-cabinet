<?php
require_once __DIR__ . '/../init.php';
require_login();

if (!is_admin()) {
    http_response_code(403);
    exit('Доступ запрещён.');
}

if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || $_SESSION['csrf_token'] === '') {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$recipient = trim((string)($_POST['recipient'] ?? ''));
$order = trim((string)($_POST['order'] ?? ''));
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string)($_POST['csrf_token'] ?? '');
    if ($token === '' || !hash_equals((string)$_SESSION['csrf_token'], $token)) {
        $result = ['success' => false, 'message' => 'Неверный CSRF-токен. Обновите страницу и повторите тест.'];
    } elseif ($recipient === '') {
        $result = ['success' => false, 'message' => 'Укажите email получателя.'];
    } else {
        $result = send_order_test_email($recipient, $order);
    }
}

$emailSettings = order_email_settings($pdo instanceof PDO ? $pdo : null);
$pdfDir = function_exists('ticket_pdf_dir')
    ? ticket_pdf_dir()
    : dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'tickets';
$isWindows = PHP_OS_FAMILY === 'Windows';
$diagnostics = [
    ['Проверка', 'Значение', 'Статус'],
    ['PHP', PHP_VERSION, true],
    ['ОС', PHP_OS_FAMILY, true],
    ['PHP mail()', function_exists('mail') ? 'Доступна' : 'Недоступна', function_exists('mail')],
    ['sendmail_path', (string)(ini_get('sendmail_path') ?: 'не задан'), $isWindows || (bool)ini_get('sendmail_path')],
    ['SMTP', (string)(ini_get('SMTP') ?: 'не задан'), !$isWindows || (bool)ini_get('SMTP')],
    ['SMTP port', (string)(ini_get('smtp_port') ?: 'не задан'), !$isWindows || (bool)ini_get('smtp_port')],
    ['OpenSSL', extension_loaded('openssl') ? 'Подключён' : 'Не подключён', extension_loaded('openssl')],
    ['mbstring', extension_loaded('mbstring') ? 'Подключён' : 'Не подключён', extension_loaded('mbstring')],
    ['Каталог PDF', $pdfDir, is_dir($pdfDir) && is_writable($pdfDir)],
    ['PUBLIC_BASE_URL', defined('PUBLIC_BASE_URL') ? (string)PUBLIC_BASE_URL : 'не задан', defined('PUBLIC_BASE_URL') && (string)PUBLIC_BASE_URL !== ''],
    ['Автоматическая отправка', $emailSettings['enabled'] ? 'Включена' : 'Выключена', true],
    ['Email отправителя', (string)$emailSettings['from'], filter_var($emailSettings['from'], FILTER_VALIDATE_EMAIL) !== false],
];

$pageTitle = 'Тест отправки email';
$use_sidebar = true;
$active_menu = 'email_test';
$page_title_meta = $pageTitle;
$panel_title = $pageTitle;
$panel_subtitle = 'Проверка PHP mail(), настроек хостинга и письма с билетами';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/panel.php';
?>

<div class="page container-full">
    <?php if ($result): ?>
        <div class="alert <?= !empty($result['success']) ? 'settings-alert settings-alert--success' : 'alert--danger' ?>">
            <?= h((string)($result['message'] ?? 'Тест завершён.')) ?>
            <?php if (!empty($result['attachments'])): ?>
                <br><small>PDF-вложений: <?= (int)$result['attachments'] ?></small>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="profile-card" style="padding:20px; margin-bottom:16px;">
        <h2 style="margin-top:0;">Отправить тестовое письмо</h2>
        <p style="color:#64748b; max-width:760px;">
            Без номера заказа будет отправлено диагностическое письмо. Если указать номер заказа,
            письмо будет собрано в настоящем формате: дата и время сеанса, ссылки на PDF,
            PDF-файлы во вложении и ссылка на возврат.
        </p>
        <form method="post" style="display:grid; gap:14px; max-width:680px;">
            <input type="hidden" name="csrf_token" value="<?= h((string)$_SESSION['csrf_token']) ?>">
            <div>
                <label for="email-test-recipient">Email получателя</label>
                <input id="email-test-recipient" class="form-control" type="email" name="recipient" required value="<?= h($recipient) ?>" placeholder="admin@example.com">
            </div>
            <div>
                <label for="email-test-order">Номер заказа для проверки письма с билетами (необязательно)</label>
                <input id="email-test-order" class="form-control" type="text" name="order" value="<?= h($order) ?>" placeholder="Например: 202609160845123456">
            </div>
            <div>
                <button type="submit" class="btn btn-primary">Отправить тест</button>
            </div>
        </form>
    </div>

    <div class="profile-card" style="padding:20px;">
        <h2 style="margin-top:0;">Диагностика хостинга</h2>
        <div style="overflow:auto;">
            <table class="admin-table" style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr>
                        <?php foreach ($diagnostics[0] as $heading): ?>
                            <th><?= h($heading) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($diagnostics, 1) as $diagnostic): ?>
                        <tr>
                            <td><?= h($diagnostic[0]) ?></td>
                            <td style="word-break:break-word;"><?= h($diagnostic[1]) ?></td>
                            <td>
                                <span class="badge" style="background:<?= $diagnostic[2] ? '#1a7f37' : '#a00' ?>; color:#fff;">
                                    <?= $diagnostic[2] ? 'Готово' : 'Проверить' ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p style="margin:16px 0 0; color:#64748b;">
            После изменения PHP-настроек на хостинге очистите OPcache или перезапустите PHP-FPM.
            Настройка DNS/SPF/DKIM/DMARC выполняется в панели домена или у хостинга.
        </p>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
