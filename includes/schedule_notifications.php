<?php
// Массовые уведомления клиентов об отмене или переносе сеанса.

if (!function_exists('schedule_notification_fetch_schedule')) {
    function schedule_notification_fetch_schedule(PDO $pdo, int $scheduleId, bool $forUpdate = false): array
    {
        $sql = "SELECT s.id, s.event_id, s.hall_id, s.start_time, s.end_time, s.status,
                       e.title AS event_title, h.name AS hall_name
                FROM schedules s
                LEFT JOIN events e ON e.id = s.event_id
                LEFT JOIN halls h ON h.id = s.hall_id
                WHERE s.id = :id
                LIMIT 1";
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $scheduleId]);
        $schedule = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$schedule) {
            throw new RuntimeException('Сеанс не найден.');
        }
        return $schedule;
    }
}

if (!function_exists('schedule_notification_collect_recipients')) {
    function schedule_notification_collect_recipients(PDO $pdo, int $scheduleId): array
    {
        $stmt = $pdo->prepare(
            "SELECT
                t.ticket_uid,
                t.customer_name AS ticket_customer_name,
                t.customer_email AS ticket_email,
                ps.order_number,
                ps.customer_name AS session_customer_name,
                ps.customer_email AS session_email,
                c.full_name AS customer_full_name,
                c.email AS customer_email
             FROM tickets t
             LEFT JOIN payment_sessions ps ON ps.id = t.payment_session_id
             LEFT JOIN customers c ON c.id = t.customer_id
             WHERE t.schedule_id = :schedule_id
               AND t.payment_status = 'paid'
               AND t.status <> 'cancelled'
               AND t.refund_status <> 'refunded'
             ORDER BY t.id ASC"
        );
        $stmt->execute([':schedule_id' => $scheduleId]);

        $recipients = [];
        $totalTickets = 0;
        $noEmailTickets = 0;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $totalTickets++;
            $email = '';
            foreach ([
                $row['ticket_email'] ?? '',
                $row['session_email'] ?? '',
                $row['customer_email'] ?? '',
            ] as $candidate) {
                $candidate = strtolower(trim((string)$candidate));
                if (filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
                    $email = $candidate;
                    break;
                }
            }

            if ($email === '') {
                $noEmailTickets++;
                continue;
            }

            if (!isset($recipients[$email])) {
                $recipients[$email] = [
                    'email' => $email,
                    'customer_name' => trim((string)($row['ticket_customer_name'] ?? ''))
                        ?: trim((string)($row['session_customer_name'] ?? ''))
                        ?: trim((string)($row['customer_full_name'] ?? '')),
                    'order_numbers' => [],
                    'ticket_uids' => [],
                ];
            }

            $orderNumber = trim((string)($row['order_number'] ?? ''));
            $ticketUid = trim((string)($row['ticket_uid'] ?? ''));
            if ($orderNumber !== '' && !in_array($orderNumber, $recipients[$email]['order_numbers'], true)) {
                $recipients[$email]['order_numbers'][] = $orderNumber;
            }
            if ($ticketUid !== '' && !in_array($ticketUid, $recipients[$email]['ticket_uids'], true)) {
                $recipients[$email]['ticket_uids'][] = $ticketUid;
            }
        }

        return [
            'total_tickets' => $totalTickets,
            'total_recipients' => count($recipients),
            'no_email_tickets' => $noEmailTickets,
            'recipients' => array_values($recipients),
        ];
    }
}

if (!function_exists('schedule_notification_validate_payload')) {
    function schedule_notification_validate_payload(array $payload): array
    {
        $type = trim((string)($payload['notification_type'] ?? ''));
        if (!in_array($type, ['postponed', 'cancelled'], true)) {
            throw new InvalidArgumentException('Выберите перенос или отмену сеанса.');
        }

        $reason = trim((string)($payload['reason'] ?? ''));
        if ($reason === '') {
            throw new InvalidArgumentException('Укажите уважительную причину для письма клиентам.');
        }
        if (mb_strlen($reason, 'UTF-8') > 2000) {
            throw new InvalidArgumentException('Причина не должна превышать 2000 символов.');
        }

        $newStart = trim((string)($payload['new_start_time'] ?? ''));
        $newEnd = trim((string)($payload['new_end_time'] ?? ''));
        if ($type === 'postponed') {
            if ($newStart === '' || $newEnd === '') {
                throw new InvalidArgumentException('Для переноса укажите новое начало и окончание сеанса.');
            }
            $newStartTimestamp = strtotime($newStart);
            $newEndTimestamp = strtotime($newEnd);
            if ($newStartTimestamp === false || $newEndTimestamp === false || $newStartTimestamp >= $newEndTimestamp) {
                throw new InvalidArgumentException('Новое начало должно быть раньше окончания сеанса.');
            }
        } else {
            $newStart = '';
            $newEnd = '';
        }

        return [
            'notification_type' => $type,
            'reason' => $reason,
            'new_start_time' => $newStart,
            'new_end_time' => $newEnd,
        ];
    }
}

if (!function_exists('schedule_notification_change_key')) {
    function schedule_notification_change_key(
        int $scheduleId,
        string $type,
        string $oldStart,
        string $oldEnd,
        string $newStart,
        string $newEnd,
        string $reason
    ): string {
        return hash('sha256', json_encode([
            $scheduleId,
            $type,
            $oldStart,
            $oldEnd,
            $newStart,
            $newEnd,
            $reason,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}

if (!function_exists('schedule_notification_build_message')) {
    function schedule_notification_build_message(
        array $schedule,
        array $recipient,
        string $type,
        string $oldStart,
        string $oldEnd,
        string $newStart,
        string $newEnd,
        string $reason
    ): array {
        $eventTitle = trim((string)($schedule['event_title'] ?? 'Спектакль'));
        $hallName = trim((string)($schedule['hall_name'] ?? ''));
        $hallLabel = $hallName !== '' ? $hallName . ' (Абая 117)' : '';
        $oldDateTime = order_email_format_datetime($oldStart);
        $newDateTime = order_email_format_datetime($newStart);
        $safeEvent = order_email_escape('«' . $eventTitle . '»');
        $safeHall = order_email_escape($hallLabel);
        $safeReason = nl2br(order_email_escape($reason));
        $safeOldDate = order_email_escape($oldDateTime['date'] ?: 'Дата уточняется');
        $safeOldTime = order_email_escape($oldDateTime['time'] ?: 'Время уточняется');
        $safeNewDate = order_email_escape($newDateTime['date'] ?: 'Дата уточняется');
        $safeNewTime = order_email_escape($newDateTime['time'] ?: 'Время уточняется');

        $orderNumbers = array_values(array_filter(array_map('trim', (array)($recipient['order_numbers'] ?? []))));
        $orderBlock = '';
        if (!empty($orderNumbers)) {
            $orderBlock = '<p style="margin:18px 0 0;color:#475569"><strong>Номер заказа:</strong> '
                . order_email_escape(implode(', ', $orderNumbers)) . '</p>';
        }

        if ($type === 'postponed') {
            $subject = 'Важное уведомление о переносе сеанса — ' . $eventTitle;
            $message = '<p style="margin:0 0 14px">Уважаемый клиент!</p>'
                . '<p style="margin:0 0 14px">Благодарим Вас за выбор театра «Жас сахна». Сообщаем, что сеанс '
                . $safeEvent . ' был перенесён.</p>'
                . '<p style="margin:0 0 6px"><strong>Первоначальные дата и время:</strong></p>'
                . '<p style="margin:0 0 4px"><strong>ДАТА:</strong> ' . $safeOldDate . '</p>'
                . '<p style="margin:0 0 12px"><strong>ВРЕМЯ:</strong> ' . $safeOldTime . '</p>'
                . '<p style="margin:0 0 6px"><strong>Новые дата и время:</strong></p>'
                . '<p style="margin:0 0 4px"><strong>ДАТА:</strong> ' . $safeNewDate . '</p>'
                . '<p style="margin:0 0 12px"><strong>ВРЕМЯ:</strong> ' . $safeNewTime . '</p>'
                . ($safeHall !== '' ? '<p style="margin:0 0 14px"><strong>ЗАЛ:</strong> ' . $safeHall . '</p>' : '')
                . '<p style="margin:0 0 14px">Ваши билеты остаются действительными на новый сеанс. '
                . 'Если новая дата или время Вам не подходят, пожалуйста, свяжитесь с нашей службой поддержки.</p>'
                . '<p style="margin:0 0 14px"><strong>Причина переноса:</strong><br>' . $safeReason . '</p>'
                . $orderBlock;
        } else {
            $subject = 'Важное уведомление об отмене сеанса — ' . $eventTitle;
            $message = '<p style="margin:0 0 14px">Уважаемый клиент!</p>'
                . '<p style="margin:0 0 14px">К сожалению, вынуждены сообщить, что сеанс '
                . $safeEvent . ' отменён. Приносим искренние извинения за доставленные неудобства.</p>'
                . '<p style="margin:0 0 4px"><strong>ДАТА:</strong> ' . $safeOldDate . '</p>'
                . '<p style="margin:0 0 4px"><strong>ВРЕМЯ:</strong> ' . $safeOldTime . '</p>'
                . ($safeHall !== '' ? '<p style="margin:0 0 14px"><strong>ЗАЛ:</strong> ' . $safeHall . '</p>' : '')
                . '<p style="margin:0 0 14px"><strong>Причина отмены:</strong><br>' . $safeReason . '</p>'
                . '<p style="margin:0 0 14px">Для оформления возврата, пожалуйста, обратитесь в службу поддержки театра. '
                . 'Мы обязательно поможем решить вопрос.</p>'
                . $orderBlock;
        }

        $html = '<!doctype html><html lang="ru"><head><meta name="color-scheme" content="light only"><meta name="supported-color-schemes" content="light"></head>'
            . '<body bgcolor="#ffffff" style="margin:0;background:#ffffff!important;font-family:Arial,sans-serif;color:#172b4d;line-height:1.5">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#ffffff" style="width:100%;background:#ffffff!important">'
            . '<tr><td align="center" bgcolor="#ffffff" style="padding:24px 12px;background:#ffffff!important">'
            . '<table role="presentation" width="680" cellpadding="0" cellspacing="0" border="0" bgcolor="#ffffff" style="width:100%;max-width:680px;background:#ffffff!important;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden">'
            . '<tr><td bgcolor="#ffffff" style="padding:28px;background:#ffffff!important;color:#172b4d">'
            . $message
            . '<p style="margin:24px 0 0;color:#64748b">Если у Вас возникнут вопросы, пожалуйста, напишите нам или позвоните:</p>'
            . '<p style="margin:6px 0 0;color:#475569">info@zhassahna.kz<br>+7 727 259 65 98<br>+7 776 711 78 78</p>'
            . '<p style="margin:24px 0 0;color:#94a3b8;font-size:12px">С уважением,<br>Театр «Жас сахна»</p>'
            . '</td></tr></table></td></tr></table></body></html>';

        return ['subject' => $subject, 'html' => $html];
    }
}
