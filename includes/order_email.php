<?php
// Отправка безопасной ссылки на страницу заказа после онлайн-покупки.

if (!function_exists('settings_get_value') && file_exists(__DIR__ . '/settings_manager.php')) {
    require_once __DIR__ . '/settings_manager.php';
}

if (!function_exists('send_order_access_email')) {
    function send_order_access_email(string $email, string $order): bool
    {
        global $pdo;
        $email = trim($email);
        $order = trim($order);
        if ($order === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $emailEnabled = true;
        $from = getenv('ZHASSAHNA_EMAIL_FROM');
        $fromName = defined('APP_NAME') ? (string)APP_NAME : 'Театр «Жас сахна»';
        $replyTo = getenv('ZHASSAHNA_EMAIL_REPLY_TO');
        $feedbackEmail = getenv('ZHASSAHNA_EMAIL_FEEDBACK');
        if ($pdo instanceof PDO && function_exists('settings_get_value')) {
            $emailEnabled = in_array(
                strtolower(trim((string)settings_get_value($pdo, 'notifications.order_email_enabled', '1'))),
                ['1', 'true', 'yes', 'on'],
                true
            );
            $from = settings_get_value($pdo, 'notifications.email_from', $from);
            $fromName = (string)settings_get_value($pdo, 'notifications.email_from_name', $fromName);
            $replyTo = settings_get_value($pdo, 'notifications.email_reply_to', $replyTo);
            $feedbackEmail = settings_get_value($pdo, 'notifications.email_feedback', $feedbackEmail);
        }
        if (!$emailEnabled) {
            return false;
        }
        $fromName = trim(preg_replace('/[\r\n]+/', ' ', $fromName));

        $token = ticket_public_token($order);
        if ($token === '') {
            error_log('[ORDER EMAIL] Не удалось создать подписанную ссылку для заказа ' . $order);
            return false;
        }

        $baseUrl = defined('PUBLIC_BASE_URL') ? rtrim((string)PUBLIC_BASE_URL, '/') : '';
        if ($baseUrl === '') {
            error_log('[ORDER EMAIL] Не настроен PUBLIC_BASE_URL для заказа ' . $order);
            return false;
        }

        $orderUrl = $baseUrl . '/tickets/public.php?order=' . rawurlencode($order) . '&token=' . rawurlencode($token);
        $safeOrder = h($order);
        $safeUrl = h($orderUrl);
        $safeFromName = h($fromName);
        $safeFeedbackEmail = is_string($feedbackEmail) && filter_var($feedbackEmail, FILTER_VALIDATE_EMAIL)
            ? h($feedbackEmail)
            : '';
        $subject = 'Ваши билеты — заказ ' . $order;
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $from = is_string($from) && filter_var($from, FILTER_VALIDATE_EMAIL)
            ? $from
            : 'noreply@zhassahna.kz';

        $feedbackBlock = $safeFeedbackEmail !== ''
            ? '<p style="margin:24px 0 0;color:#64748b;font-size:13px">Вопросы по заказу? Напишите нам: <a href="mailto:' . $safeFeedbackEmail . '" style="color:#1d4ed8">' . $safeFeedbackEmail . '</a></p>'
            : '<p style="margin:24px 0 0;color:#64748b;font-size:13px">Если у вас есть вопросы по заказу, ответьте на это письмо.</p>';
        $body = '<!doctype html><html lang="ru"><body style="margin:0;background:#f3f6fa;font-family:Arial,sans-serif;color:#172b4d;line-height:1.5">'
            . '<div style="max-width:620px;margin:24px auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 8px 28px rgba(23,43,77,.10)">'
            . '<div style="padding:24px 28px;background:#173b67;color:#fff">'
            . '<div style="font-size:12px;letter-spacing:.12em;text-transform:uppercase;opacity:.78">Жас сахна</div>'
            . '<h1 style="margin:8px 0 0;font-size:25px;line-height:1.2">Ваши билеты готовы</h1>'
            . '</div>'
            . '<div style="padding:28px">'
            . '<p style="margin:0 0 16px">Здравствуйте!</p>'
            . '<p style="margin:0">Оплата прошла успешно. Номер заказа:</p>'
            . '<p style="margin:8px 0 22px;font-size:22px;font-weight:700;color:#173b67">' . $safeOrder . '</p>'
            . '<a href="' . $safeUrl . '" style="display:inline-block;padding:13px 20px;border-radius:9px;background:#18a957;color:#fff;text-decoration:none;font-weight:700">Открыть билеты</a>'
            . '<p style="margin:18px 0 0;color:#64748b;font-size:13px">На странице заказа можно открыть и скачать PDF-билеты. Если возврат разрешён правилами покупки и срок не истёк, там же будет доступна кнопка возврата.</p>'
            . $feedbackBlock
            . '<p style="margin:24px 0 0;color:#94a3b8;font-size:12px">С уважением,<br>' . $safeFromName . '</p>'
            . '</div></div></body></html>';

        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: =?UTF-8?B?' . base64_encode($fromName) . '?= <' . $from . '>',
        ];
        $replyTo = is_string($replyTo) && filter_var($replyTo, FILTER_VALIDATE_EMAIL)
            ? $replyTo
            : (is_string($feedbackEmail) && filter_var($feedbackEmail, FILTER_VALIDATE_EMAIL) ? $feedbackEmail : '');
        if ($replyTo !== '') {
            $headers[] = 'Reply-To: ' . $replyTo;
        }
        $sent = mail($email, $encodedSubject, $body, implode("\r\n", $headers));
        if (!$sent) {
            error_log('[ORDER EMAIL] Не удалось отправить письмо для заказа ' . $order);
        }
        return $sent;
    }
}
