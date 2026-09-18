<?php
// Email с билетами после успешной онлайн-покупки.

if (!function_exists('settings_get_value') && file_exists(__DIR__ . '/settings_manager.php')) {
    require_once __DIR__ . '/settings_manager.php';
}

if (!function_exists('order_email_settings')) {
    function order_email_settings(?PDO $pdo = null): array
    {
        $settings = [
            'enabled' => true,
            'from' => getenv('ZHASSAHNA_EMAIL_FROM') ?: 'noreply@zhassahna.kz',
            'from_name' => defined('APP_NAME') ? (string)APP_NAME : 'Театр «Жас сахна»',
            'reply_to' => getenv('ZHASSAHNA_EMAIL_REPLY_TO') ?: '',
            'feedback_email' => getenv('ZHASSAHNA_EMAIL_FEEDBACK') ?: '',
        ];

        if ($pdo instanceof PDO && function_exists('settings_get_value')) {
            $settings['enabled'] = in_array(
                strtolower(trim((string)settings_get_value($pdo, 'notifications.order_email_enabled', '1'))),
                ['1', 'true', 'yes', 'on'],
                true
            );
            $settings['from'] = settings_get_value($pdo, 'notifications.email_from', $settings['from']);
            $settings['from_name'] = settings_get_value($pdo, 'notifications.email_from_name', $settings['from_name']);
            $settings['reply_to'] = settings_get_value($pdo, 'notifications.email_reply_to', $settings['reply_to']);
            $settings['feedback_email'] = settings_get_value($pdo, 'notifications.email_feedback', $settings['feedback_email']);
        }

        $settings['from'] = trim((string)$settings['from']);
        $settings['from_name'] = trim(preg_replace('/[\r\n]+/', ' ', (string)$settings['from_name']));
        $settings['reply_to'] = trim((string)$settings['reply_to']);
        $settings['feedback_email'] = trim((string)$settings['feedback_email']);
        return $settings;
    }
}

if (!function_exists('order_email_escape')) {
    function order_email_escape($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('order_email_format_datetime')) {
    function order_email_format_datetime($value): array
    {
        if (!$value) {
            return ['date' => '', 'time' => ''];
        }
        try {
            $date = new DateTimeImmutable((string)$value);
            return [
                'date' => $date->format('d.m.Y'),
                'time' => $date->format('H:i'),
            ];
        } catch (Throwable $exception) {
            return ['date' => '', 'time' => ''];
        }
    }
}

if (!function_exists('order_email_load_order')) {
    function order_email_load_order(PDO $pdo, string $order): array
    {
        $sessionStmt = $pdo->prepare("SELECT
                ps.id, ps.order_number, ps.customer_name, ps.customer_email,
                ps.status, ps.ticket_uids,
                e.title AS event_title,
                s.start_time AS schedule_start,
                h.name AS hall_name
            FROM payment_sessions ps
            LEFT JOIN events e ON e.id = ps.event_id
            LEFT JOIN schedules s ON s.id = ps.session_id
            LEFT JOIN halls h ON h.id = ps.hall_id
            WHERE ps.order_number = :order
            LIMIT 1");
        $sessionStmt->execute([':order' => $order]);
        $session = $sessionStmt->fetch(PDO::FETCH_ASSOC);
        if (!$session) {
            throw new RuntimeException('Заказ не найден.');
        }

        $ticketStmt = $pdo->prepare("SELECT
                id, ticket_uid, seat_identifier, original_price, final_price,
                discount, discount_amount
            FROM tickets
            WHERE payment_session_id = :payment_session_id
            ORDER BY id ASC");
        $ticketStmt->execute([':payment_session_id' => (int)$session['id']]);
        $tickets = $ticketStmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($tickets)) {
            throw new RuntimeException('В заказе не найдены билеты.');
        }

        $session['tickets'] = $tickets;
        return $session;
    }
}

if (!function_exists('order_email_prepare_ticket_assets')) {
    function order_email_prepare_ticket_assets(array $tickets, string $baseUrl): array
    {
        $links = [];
        $attachments = [];

        foreach ($tickets as $ticket) {
            $uid = trim((string)($ticket['ticket_uid'] ?? ''));
            if ($uid === '') {
                continue;
            }

            $pdfPath = function_exists('ticket_pdf_file_path') ? ticket_pdf_file_path($uid) : '';
            if ($pdfPath !== '' && (!is_file($pdfPath) || filesize($pdfPath) <= 0) && function_exists('ticket_pdf_generate_by_ticket_uid')) {
                $pdfError = null;
                if (!ticket_pdf_generate_by_ticket_uid($uid, false, $pdfError)) {
                    error_log('[ORDER EMAIL] PDF generation failed for ' . $uid . ': ' . ($pdfError ?: 'unknown error'));
                }
            }

            $pdfToken = function_exists('ticket_public_token') ? ticket_public_token('pdf:' . $uid) : '';
            if ($pdfToken !== '' && $baseUrl !== '') {
                $links[] = [
                    'uid' => $uid,
                    'url' => $baseUrl . '/tickets/generate.php?uid=' . rawurlencode($uid)
                        . '&t=' . rawurlencode($pdfToken) . '&download=1',
                ];
            }

            if ($pdfPath !== '' && is_file($pdfPath) && filesize($pdfPath) > 0) {
                $attachments[] = [
                    'path' => $pdfPath,
                    'name' => 'ticket-' . preg_replace('/[^A-Za-z0-9_-]+/', '_', $uid) . '.pdf',
                ];
            }
        }

        return ['links' => $links, 'attachments' => $attachments];
    }
}

if (!function_exists('order_email_send_message')) {
    function order_email_send_message(
        string $email,
        string $subject,
        string $html,
        array $settings,
        array $attachments = []
    ): bool {
        if (!function_exists('mail')) {
            error_log('[ORDER EMAIL] PHP mail() is unavailable.');
            return false;
        }

        $from = filter_var($settings['from'] ?? '', FILTER_VALIDATE_EMAIL)
            ? (string)$settings['from']
            : 'noreply@zhassahna.kz';
        $fromName = trim(preg_replace('/[\r\n]+/', ' ', (string)($settings['from_name'] ?? 'Театр «Жас сахна»')));
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $headers = [
            'MIME-Version: 1.0',
            'From: =?UTF-8?B?' . base64_encode($fromName) . '?= <' . $from . '>',
        ];

        $replyTo = (string)($settings['reply_to'] ?? '');
        $feedbackEmail = (string)($settings['feedback_email'] ?? '');
        if (!filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $replyTo = filter_var($feedbackEmail, FILTER_VALIDATE_EMAIL) ? $feedbackEmail : '';
        }
        if ($replyTo !== '') {
            $headers[] = 'Reply-To: ' . $replyTo;
        }

        if (empty($attachments)) {
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
            $headers[] = 'Content-Transfer-Encoding: base64';
            $body = chunk_split(base64_encode($html));
        } else {
            $boundary = '=_Zhassahna_' . bin2hex(random_bytes(12));
            $headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';
            $body = '--' . $boundary . "\r\n"
                . "Content-Type: text/html; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: base64\r\n\r\n"
                . chunk_split(base64_encode($html)) . "\r\n";

            foreach ($attachments as $attachment) {
                $path = (string)($attachment['path'] ?? '');
                if ($path === '' || !is_readable($path)) {
                    error_log('[ORDER EMAIL] Attachment is not readable: ' . $path);
                    continue;
                }
                $content = file_get_contents($path);
                if ($content === false) {
                    error_log('[ORDER EMAIL] Failed to read attachment: ' . $path);
                    continue;
                }
                $filename = preg_replace('/[^A-Za-z0-9_.-]+/', '_', (string)($attachment['name'] ?? basename($path)));
                $isInline = !empty($attachment['inline']) && !empty($attachment['cid']);
                $mime = (string)($attachment['mime'] ?? 'application/pdf');
                $body .= '--' . $boundary . "\r\n"
                    . 'Content-Type: ' . $mime . '; name="' . $filename . '"' . "\r\n"
                    . ($isInline ? 'Content-ID: <' . preg_replace('/[^A-Za-z0-9_.-]/', '', (string)$attachment['cid']) . '>\r\n' : '')
                    . 'Content-Disposition: ' . ($isInline ? 'inline' : 'attachment') . '; filename="' . $filename . '"' . "\r\n"
                    . "Content-Transfer-Encoding: base64\r\n\r\n"
                    . chunk_split(base64_encode($content)) . "\r\n";
            }
            $body .= '--' . $boundary . "--\r\n";
        }

        $extraParams = $from !== '' ? '-f' . $from : '';
        $sent = mail($email, $encodedSubject, $body, implode("\r\n", $headers), $extraParams);
        if (!$sent) {
            error_log('[ORDER EMAIL] mail() returned false for ' . $email);
        }
        return $sent;
    }
}

if (!function_exists('order_email_build_order_message')) {
    function order_email_build_order_message(PDO $pdo, string $email, string $order, bool $respectEnabled = true): array
    {
        $settings = order_email_settings($pdo);
        if ($respectEnabled && !$settings['enabled']) {
            return ['success' => false, 'message' => 'Автоматическая отправка писем отключена в настройках.'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Укажите корректный email получателя.'];
        }
        $orderData = order_email_load_order($pdo, $order);
        $baseUrl = defined('PUBLIC_BASE_URL') ? rtrim((string)PUBLIC_BASE_URL, '/') : '';
        if ($baseUrl === '') {
            throw new RuntimeException('Не настроен PUBLIC_BASE_URL.');
        }
        $orderToken = function_exists('ticket_public_token') ? ticket_public_token($order) : '';
        if ($orderToken === '') {
            throw new RuntimeException('Не удалось создать подписанную ссылку на заказ.');
        }

        $orderUrl = $baseUrl . '/tickets/public.php?order=' . rawurlencode($order) . '&token=' . rawurlencode($orderToken);
        $datetime = order_email_format_datetime($orderData['schedule_start'] ?? null);
        $assets = order_email_prepare_ticket_assets($orderData['tickets'], $baseUrl);
        $safeOrder = order_email_escape($order);
        $safeEvent = order_email_escape($orderData['event_title'] ?: 'Спектакль');
        $safeDate = order_email_escape($datetime['date'] ?: 'Дата уточняется');
        $safeTime = order_email_escape($datetime['time'] ?: 'Время уточняется');
        $safeHall = order_email_escape($orderData['hall_name'] ?: '');
        $safeOrderUrl = order_email_escape($orderUrl);
        $safeFromName = order_email_escape($settings['from_name']);
        // PNG is supported more reliably than external SVG in Mail.ru dark mode.
        $logoUrl = $baseUrl . '/uploads/images/logo.png';
        $logoHtml = '<img src="' . order_email_escape($logoUrl) . '" alt="Жас сахна" width="88" height="88" style="display:block;width:88px;height:88px;object-fit:contain;border:0;outline:none;text-decoration:none">';

        $ticketRows = '';
        foreach ($orderData['tickets'] as $index => $ticket) {
            $seat = order_email_escape(str_replace([':', '-'], [' / ', ' / '], (string)($ticket['seat_identifier'] ?? '')));
            $price = number_format((float)($ticket['final_price'] ?? 0), 2, '.', ' ');
            $ticketLink = '';
            foreach ($assets['links'] as $link) {
                if ($link['uid'] === (string)$ticket['ticket_uid']) {
                    $ticketLink = '<a href="' . order_email_escape($link['url']) . '" style="color:#1d4ed8">Скачать PDF</a>';
                    break;
                }
            }
            $ticketRows .= '<tr>'
                . '<td style="padding:9px 8px;border-bottom:1px solid #e5e7eb">' . ($index + 1) . '</td>'
                . '<td style="padding:9px 8px;border-bottom:1px solid #e5e7eb">' . $seat . '</td>'
                . '<td style="padding:9px 8px;border-bottom:1px solid #e5e7eb;white-space:nowrap">' . $price . ' ₸</td>'
                . '<td style="padding:9px 8px;border-bottom:1px solid #e5e7eb">' . $ticketLink . '</td>'
                . '</tr>';
        }

        $subject = 'Ваши билеты — заказ ' . $order;
        $feedbackEmail = filter_var($settings['feedback_email'], FILTER_VALIDATE_EMAIL)
            ? '<a href="mailto:' . order_email_escape($settings['feedback_email']) . '" style="color:#1d4ed8">' . order_email_escape($settings['feedback_email']) . '</a>'
            : 'ответьте на это письмо';
        $html = '<!doctype html><html lang="ru"><head><meta name="color-scheme" content="light only"><meta name="supported-color-schemes" content="light"><style>'
            . 'html,body,.email-outer,.email-card,.email-header,.email-content{background-color:#ffffff!important;background:#ffffff!important;color:#172b4d!important;}'
            . 'h1,h2,p,td,th,strong{color:inherit!important;}'
            . 'a{color:#1457b8!important;}'
            . '</style></head><body bgcolor="#ffffff" style="margin:0;background:#ffffff!important;font-family:Arial,sans-serif;color:#172b4d!important;line-height:1.5">'
            . '<table class="email-outer" role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#ffffff" style="width:100%;background:#ffffff!important"><tr><td align="center" bgcolor="#ffffff" style="padding:24px 12px;background:#ffffff!important">'
            . '<table class="email-card" role="presentation" width="680" cellpadding="0" cellspacing="0" border="0" bgcolor="#ffffff" style="width:100%;max-width:680px;background:#ffffff!important;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;color:#172b4d!important">'
            . '<tr><td class="email-header" bgcolor="#ffffff" style="padding:20px 26px;background:#ffffff!important;color:#111827!important;border-bottom:1px solid #e5e7eb">'
            . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse"><tr>'
            . '<td valign="middle" style="padding:0 26px 0 0;width:88px">' . $logoHtml . '</td>'
            . '<td valign="middle" style="padding:0 0 0 0"><h1 style="margin:0;font-size:24px;line-height:1.2;color:#111827!important;font-weight:700">Спасибо за покупку!</h1></td>'
            . '</tr></table>'
            . '</td></tr><tr><td class="email-content" bgcolor="#ffffff" style="padding:28px;background:#ffffff!important;color:#172b4d!important">'
            . '<p style="margin:0 0 14px">Желаем Вам приятного отдыха.</p>'
            . '<p style="margin:0 0 4px"><strong>' . $safeEvent . '</strong></p>'
            . '<p style="margin:0;color:#475569">Дата: ' . $safeDate . ' · Время: ' . $safeTime
            . ($safeHall !== '' ? ' · Зал: ' . $safeHall : '') . '</p>'
            . '<p style="margin:18px 0 4px;color:#64748b">Номер заказа</p>'
            . '<p style="margin:0 0 20px;font-size:22px;font-weight:700;color:#173b67">' . $safeOrder . '</p>'
            . '<a href="' . $safeOrderUrl . '" style="display:inline-block;padding:13px 20px;border-radius:9px;background:#18a957;color:#fff;text-decoration:none;font-weight:700">Открыть страницу заказа</a>'
            . '<h2 style="margin:28px 0 10px;font-size:18px">Ваши билеты</h2>'
            . '<table cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;font-size:14px">'
            . '<tr style="background:#f1f5f9"><th align="left" style="padding:9px 8px">№</th><th align="left" style="padding:9px 8px">Ряд / Место</th><th align="left" style="padding:9px 8px">Цена</th><th align="left" style="padding:9px 8px">Файл</th></tr>'
            . $ticketRows . '</table>'
            . '<p style="margin:20px 0 0;color:#475569">PDF-файлы билетов также прикреплены к этому письму. На странице заказа их можно скачать повторно.</p>'
            . '<p style="margin:18px 0 0"><a href="' . $safeOrderUrl . '" style="color:#b42318;font-weight:700">Оформить возврат</a> (если возврат доступен по правилам и срок ещё не истёк).</p>'
            . '<p style="margin:24px 0 0;color:#94a3b8;font-size:12px">С уважением,<br>' . $safeFromName . '</p>'
            . '</td></tr></table></td></tr></table></body></html>';

        $sent = order_email_send_message((string)$email, $subject, $html, $settings, $assets['attachments']);
        return [
            'success' => $sent,
            'message' => $sent ? 'Письмо передано почтовой службе хостинга. Доставка может занять несколько минут; проверьте также папку «Спам».' : 'PHP mail() не принял письмо. Проверьте настройки хостинга.',
            'attachments' => count(array_filter($assets['attachments'], static function (array $attachment): bool {
                return empty($attachment['inline']);
            })),
        ];
    }
}

if (!function_exists('send_order_access_email')) {
    function send_order_access_email(string $email, string $order): bool
    {
        global $pdo;
        $email = trim($email);
        $order = trim($order);
        if (!$pdo instanceof PDO || $order === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        try {
            $result = order_email_build_order_message($pdo, $email, $order, true);
            if (!$result['success']) {
                error_log('[ORDER EMAIL] ' . $result['message'] . ' Order=' . $order);
            }
            return (bool)$result['success'];
        } catch (Throwable $exception) {
            error_log('[ORDER EMAIL] Failed for order ' . $order . ': ' . $exception->getMessage());
            return false;
        }
    }
}

if (!function_exists('send_order_test_email')) {
    function send_order_test_email(string $email, string $order = ''): array
    {
        global $pdo;
        if (!$pdo instanceof PDO) {
            return ['success' => false, 'message' => 'База данных недоступна.'];
        }
        $email = trim($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Укажите корректный email получателя.'];
        }

        try {
            if ($order !== '') {
                return order_email_build_order_message($pdo, $email, trim($order), false);
            }

            $settings = order_email_settings($pdo);
            $subject = 'Тест email — ' . date('d.m.Y H:i:s');
            $safeFromName = order_email_escape($settings['from_name']);
            $safeHost = order_email_escape($_SERVER['HTTP_HOST'] ?? 'CLI/unknown');
            $html = '<!doctype html><html lang="ru"><body style="font-family:Arial,sans-serif;color:#172b4d">'
                . '<h1>Тест отправки email</h1>'
                . '<p>Письмо успешно сформировано приложением театра «Жас сахна».</p>'
                . '<p><strong>Время сервера:</strong> ' . order_email_escape(date('d.m.Y H:i:s')) . '</p>'
                . '<p><strong>Хост:</strong> ' . $safeHost . '</p>'
                . '<p><strong>Отправитель:</strong> ' . order_email_escape($settings['from']) . '</p>'
                . '<p>Если письмо доставлено, базовая настройка PHP mail() на хостинге работает.</p>'
                . '<p>С уважением,<br>' . $safeFromName . '</p>'
                . '</body></html>';
            $sent = order_email_send_message($email, $subject, $html, $settings);
            return [
                'success' => $sent,
                'message' => $sent ? 'Тестовое письмо передано почтовой службе хостинга. Доставка не гарантируется самим PHP; проверьте «Спам» и mail-log.' : 'PHP mail() не принял тестовое письмо.',
                'attachments' => 0,
            ];
        } catch (Throwable $exception) {
            error_log('[ORDER EMAIL TEST] ' . $exception->getMessage());
            return ['success' => false, 'message' => $exception->getMessage()];
        }
    }
}
