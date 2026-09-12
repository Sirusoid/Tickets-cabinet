<?php
// Отправка безопасной ссылки на страницу заказа после онлайн-покупки.

if (!function_exists('send_order_access_email')) {
    function send_order_access_email(string $email, string $order): bool
    {
        $email = trim($email);
        $order = trim($order);
        if ($order === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

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
        $subject = 'Ваши билеты — заказ ' . $order;
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $from = getenv('ZHASSAHNA_EMAIL_FROM');
        $from = is_string($from) && filter_var($from, FILTER_VALIDATE_EMAIL)
            ? $from
            : 'noreply@zhassahna.kz';

        $body = '<!doctype html><html lang="ru"><body style="font-family:Arial,sans-serif;color:#111827;line-height:1.5">'
            . '<h2>Ваши билеты</h2>'
            . '<p>Номер заказа: <strong>' . $safeOrder . '</strong></p>'
            . '<p><a href="' . $safeUrl . '">Открыть страницу заказа и скачать билеты</a></p>'
            . '<p>На странице заказа также доступен самостоятельный возврат, если он разрешён правилами покупки и срок возврата не истёк.</p>'
            . '<p style="color:#6b7280;font-size:12px">Если вы не совершали эту покупку, просто проигнорируйте письмо.</p>'
            . '</body></html>';

        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from,
        ];
        $sent = mail($email, $encodedSubject, $body, implode("\r\n", $headers));
        if (!$sent) {
            error_log('[ORDER EMAIL] Не удалось отправить письмо для заказа ' . $order);
        }
        return $sent;
    }
}
