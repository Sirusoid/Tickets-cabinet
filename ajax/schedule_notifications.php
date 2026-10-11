<?php
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/schedule_notifications.php';
require_once __DIR__ . '/../includes/payment/bcc_refund.php';

require_login();
header('Content-Type: application/json; charset=utf-8');

function schedule_notification_json_response(bool $success, string $message = '', array $data = []): void
{
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message,
    ], $data), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function schedule_notification_process_online_refunds(PDO $pdo, int $scheduleId, string $source): array
{
    $orders = schedule_notification_collect_online_refund_orders($pdo, $scheduleId);
    $results = [];
    $summary = [
        'orders' => count($orders),
        'refunded' => 0,
        'processing' => 0,
        'failed' => 0,
        'amount_cents' => 0,
        'details' => [],
    ];

    foreach ($orders as $order) {
        $orderNumber = trim((string)($order['order_number'] ?? ''));
        $paymentSessionId = (int)$order['id'];
        $ticketStmt = $pdo->prepare(
            'SELECT id, ticket_uid, final_price, refund_status
             FROM tickets
             WHERE payment_session_id = :payment_session_id
             ORDER BY id ASC'
        );
        $ticketStmt->execute([':payment_session_id' => $paymentSessionId]);
        $tickets = $ticketStmt->fetchAll(PDO::FETCH_ASSOC);
        $ticketStatuses = array_column($tickets, 'refund_status');

        if (in_array('requested', $ticketStatuses, true)) {
            $result = [
                'order' => $orderNumber,
                'state' => 'processing',
                'amount_cents' => (int)$order['amount_cents'],
                'message' => 'Запрос на возврат уже ожидает ответа банка.',
            ];
        } elseif (in_array('refunded', $ticketStatuses, true)) {
            $result = [
                'order' => $orderNumber,
                'state' => 'failed',
                'amount_cents' => (int)$order['amount_cents'],
                'message' => 'В заказе уже есть возвращённые билеты; требуется ручная проверка.',
            ];
        } else {
            $originalResponse = json_decode((string)($order['provider_response'] ?? ''), true);
            if (!is_array($originalResponse)) {
                $originalResponse = [];
            }
            try {
                $refund = bcc_process_refund_request(
                    $pdo,
                    $order,
                    $tickets,
                    $originalResponse,
                    $source
                );
                $refundState = (string)($refund['state'] ?? 'rejected');
                $result = [
                    'order' => $orderNumber,
                    'state' => $refundState === 'refunded' ? 'refunded' : ($refundState === 'processing' ? 'processing' : 'failed'),
                    'amount_cents' => (int)$order['amount_cents'],
                    'message' => (string)($refund['message'] ?? 'Не удалось получить результат возврата.'),
                ];
            } catch (Throwable $exception) {
                error_log('[SCHEDULE CANCELLATION REFUND] Order ' . $orderNumber . ': ' . $exception->getMessage());
                $result = [
                    'order' => $orderNumber,
                    'state' => 'failed',
                    'amount_cents' => (int)$order['amount_cents'],
                    'message' => 'Возврат не завершён. Проверьте банковские данные и журнал ошибок.',
                ];
            }
        }

        $summary['amount_cents'] += (int)$result['amount_cents'];
        if ($result['state'] === 'refunded') {
            $summary['refunded']++;
        } elseif ($result['state'] === 'processing') {
            $summary['processing']++;
        } else {
            $summary['failed']++;
        }
        $results[] = $result;
        $summary['details'][] = $result;
    }

    return $summary;
}

global $pdo;
if (!isset($pdo) || !($pdo instanceof PDO)) {
    schedule_notification_json_response(false, 'База данных недоступна.');
}

if (!user_has_permission($pdo, 'schedule_notifications', false)) {
    http_response_code(403);
    schedule_notification_json_response(false, 'Недостаточно прав для уведомления клиентов.');
}

$csrfToken = (string)($_REQUEST['csrf_token'] ?? '');
if (!function_exists('validate_csrf') || !validate_csrf($csrfToken)) {
    http_response_code(403);
    schedule_notification_json_response(false, 'Сессия истекла. Обновите страницу и повторите действие.');
}

$action = trim((string)($_REQUEST['action'] ?? ''));
$scheduleId = (int)($_REQUEST['schedule_id'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    schedule_notification_json_response(false, 'Неверный метод запроса.');
}
if ($scheduleId <= 0) {
    schedule_notification_json_response(false, 'Не указан сеанс.');
}

try {
    if ($action === 'retry_refunds') {
        $schedule = schedule_notification_fetch_schedule($pdo, $scheduleId);
        if ((string)$schedule['status'] !== 'cancelled') {
            schedule_notification_json_response(false, 'Повторный возврат доступен только для отменённого сеанса.');
        }
        $cancellationStmt = $pdo->prepare(
            "SELECT id FROM schedule_notifications
             WHERE schedule_id = :schedule_id AND notification_type = 'cancelled'
             ORDER BY id DESC LIMIT 1"
        );
        $cancellationStmt->execute([':schedule_id' => $scheduleId]);
        $notificationId = (int)$cancellationStmt->fetchColumn();
        if ($notificationId <= 0) {
            schedule_notification_json_response(false, 'Запись об отмене сеанса не найдена.');
        }

        $refunds = schedule_notification_process_online_refunds($pdo, $scheduleId, 'schedule_cancellation_bcc_refund_retry');
        if (function_exists('audit_log_event')) {
            audit_log_event($pdo, 'schedule.cancellation_refund_retry', 'schedule', $scheduleId, (string)($schedule['event_title'] ?? ''), [], [
                'notification_id' => $notificationId,
                'refunds' => $refunds,
            ]);
        }
        schedule_notification_json_response(true, 'Повторная обработка возвратов завершена.', [
            'refunds' => $refunds,
        ]);
    }

    $payload = schedule_notification_validate_payload($_REQUEST);
    $schedule = schedule_notification_fetch_schedule($pdo, $scheduleId);
    $recipients = schedule_notification_collect_recipients($pdo, $scheduleId);
    $onlineRefundOrders = $payload['notification_type'] === 'cancelled'
        ? schedule_notification_collect_online_refund_orders($pdo, $scheduleId)
        : [];
    $onlineRefundAmountCents = array_sum(array_map(static function (array $order): int {
        return (int)($order['amount_cents'] ?? 0);
    }, $onlineRefundOrders));

    if ($action === 'preview') {
        $previewRecipient = [
            'customer_name' => 'клиент',
            'order_numbers' => [],
            'ticket_uids' => [],
        ];
        $message = schedule_notification_build_message(
            $schedule,
            $previewRecipient,
            $payload['notification_type'],
            (string)$schedule['start_time'],
            (string)$schedule['end_time'],
            $payload['new_start_time'],
            $payload['new_end_time'],
            $payload['template_text']
        );

        schedule_notification_json_response(true, 'Предпросмотр подготовлен.', [
            'schedule' => [
                'id' => (int)$schedule['id'],
                'event_title' => (string)($schedule['event_title'] ?? 'Спектакль'),
                'hall_name' => (string)($schedule['hall_name'] ?? ''),
                'start_time' => (string)$schedule['start_time'],
                'end_time' => (string)$schedule['end_time'],
            ],
            'counts' => [
                'total_tickets' => $recipients['total_tickets'],
                'total_recipients' => $recipients['total_recipients'],
                'no_email_tickets' => $recipients['no_email_tickets'],
                'online_refund_orders' => count($onlineRefundOrders),
                'online_refund_amount_cents' => $onlineRefundAmountCents,
            ],
            'subject' => $message['subject'],
            'preview_html' => $message['html'],
        ]);
    }

    if ($action !== 'notify') {
        schedule_notification_json_response(false, 'Неизвестное действие.');
    }
    if (
        $payload['notification_type'] === 'cancelled'
        && (string)($_REQUEST['cancel_refunds_confirmed'] ?? '') !== '1'
    ) {
        schedule_notification_json_response(false, 'Для отмены сеанса требуется подтвердить автоматический возврат онлайн-покупателям.');
    }

    $settings = order_email_settings($pdo);
    if (empty($settings['enabled'])) {
        schedule_notification_json_response(false, 'Отправка email отключена в настройках.');
    }

    $oldStart = (string)$schedule['start_time'];
    $oldEnd = (string)$schedule['end_time'];
    $changeKey = schedule_notification_change_key(
        $scheduleId,
        $payload['notification_type'],
        $oldStart,
        $oldEnd,
        $payload['new_start_time'],
        $payload['new_end_time'],
        $payload['template_text']
    );

    $pdo->beginTransaction();
    try {
        $lockedSchedule = schedule_notification_fetch_schedule($pdo, $scheduleId, true);
        if ($payload['notification_type'] === 'cancelled' && (string)$lockedSchedule['status'] === 'cancelled') {
            throw new RuntimeException('Сеанс уже отменён.');
        }

        $lockedRecipients = schedule_notification_collect_recipients($pdo, $scheduleId);
        $insertNotification = $pdo->prepare(
            'INSERT INTO schedule_notifications
                (schedule_id, change_key, notification_type, old_start_time, old_end_time,
                 new_start_time, new_end_time, reason, status, total_tickets, total_recipients,
                 no_email_tickets, created_by, created_at)
             VALUES
                (:schedule_id, :change_key, :notification_type, :old_start_time, :old_end_time,
                 :new_start_time, :new_end_time, :reason, :status, :total_tickets, :total_recipients,
                 :no_email_tickets, :created_by, NOW())'
        );
        $insertNotification->execute([
            ':schedule_id' => $scheduleId,
            ':change_key' => $changeKey,
            ':notification_type' => $payload['notification_type'],
            ':old_start_time' => $oldStart,
            ':old_end_time' => $oldEnd,
            ':new_start_time' => $payload['new_start_time'] !== '' ? $payload['new_start_time'] : null,
            ':new_end_time' => $payload['new_end_time'] !== '' ? $payload['new_end_time'] : null,
            ':reason' => $payload['template_text'],
            ':status' => 'sending',
            ':total_tickets' => $lockedRecipients['total_tickets'],
            ':total_recipients' => $lockedRecipients['total_recipients'],
            ':no_email_tickets' => $lockedRecipients['no_email_tickets'],
            ':created_by' => !empty($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null,
        ]);
        $notificationId = (int)$pdo->lastInsertId();

        $insertRecipient = $pdo->prepare(
            'INSERT INTO schedule_notification_recipients
                (notification_id, email, customer_name, order_numbers, ticket_uids, status, created_at)
             VALUES
                (:notification_id, :email, :customer_name, :order_numbers, :ticket_uids, :status, NOW())'
        );
        foreach ($lockedRecipients['recipients'] as $recipient) {
            $insertRecipient->execute([
                ':notification_id' => $notificationId,
                ':email' => $recipient['email'],
                ':customer_name' => $recipient['customer_name'] !== '' ? $recipient['customer_name'] : null,
                ':order_numbers' => json_encode($recipient['order_numbers'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ':ticket_uids' => json_encode($recipient['ticket_uids'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ':status' => 'queued',
            ]);
        }

        if ($payload['notification_type'] === 'cancelled') {
            $updateSchedule = $pdo->prepare("UPDATE schedules SET status = 'cancelled', updated_at = NOW() WHERE id = :id");
            $updateSchedule->execute([':id' => $scheduleId]);
        } else {
            $updateSchedule = $pdo->prepare(
                'UPDATE schedules
                 SET start_time = :start_time, end_time = :end_time, updated_at = NOW()
                 WHERE id = :id'
            );
            $updateSchedule->execute([
                ':start_time' => $payload['new_start_time'],
                ':end_time' => $payload['new_end_time'],
                ':id' => $scheduleId,
            ]);
        }

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if (strpos($exception->getMessage(), 'uq_schedule_notifications_change_key') !== false) {
            schedule_notification_json_response(false, 'Это изменение уже было обработано. Повторная отправка не выполнена.');
        }
        throw $exception;
    }

    $mailSchedule = $schedule;
    if ($payload['notification_type'] === 'postponed') {
        $mailSchedule['start_time'] = $payload['new_start_time'];
        $mailSchedule['end_time'] = $payload['new_end_time'];
    }

    $refundSummary = $payload['notification_type'] === 'cancelled'
        ? schedule_notification_process_online_refunds($pdo, $scheduleId, 'schedule_cancellation_bcc_refund')
        : null;

    $recipientRowsStmt = $pdo->prepare(
        'SELECT id, email, customer_name, order_numbers, ticket_uids
         FROM schedule_notification_recipients
         WHERE notification_id = :notification_id
         ORDER BY id ASC'
    );
    $recipientRowsStmt->execute([':notification_id' => $notificationId]);
    $sentCount = 0;
    $failedCount = 0;
    $updateRecipient = $pdo->prepare(
        'UPDATE schedule_notification_recipients
         SET status = :status, attempts = attempts + 1, sent_at = :sent_at, last_error = :last_error
         WHERE id = :id'
    );

    while ($recipientRow = $recipientRowsStmt->fetch(PDO::FETCH_ASSOC)) {
        $recipient = [
            'email' => (string)$recipientRow['email'],
            'customer_name' => (string)($recipientRow['customer_name'] ?? ''),
            'order_numbers' => json_decode((string)($recipientRow['order_numbers'] ?? '[]'), true) ?: [],
            'ticket_uids' => json_decode((string)($recipientRow['ticket_uids'] ?? '[]'), true) ?: [],
        ];
        $sendError = null;
        $sent = false;
        try {
            $message = schedule_notification_build_message(
                $mailSchedule,
                $recipient,
                $payload['notification_type'],
                $oldStart,
                $oldEnd,
                $payload['new_start_time'],
                $payload['new_end_time'],
                $payload['template_text']
            );
            $sent = order_email_send_message(
                $recipient['email'],
                $message['subject'],
                $message['html'],
                $settings,
                []
            );
            if (!$sent) {
                $sendError = 'Почтовая служба вернула отказ.';
            }
        } catch (Throwable $exception) {
            $sendError = $exception->getMessage();
        }

        if ($sent) {
            $sentCount++;
            $updateRecipient->execute([
                ':status' => 'sent',
                ':sent_at' => date('Y-m-d H:i:s'),
                ':last_error' => null,
                ':id' => (int)$recipientRow['id'],
            ]);
        } else {
            $failedCount++;
            $updateRecipient->execute([
                ':status' => 'failed',
                ':sent_at' => null,
                ':last_error' => $sendError ?: 'Неизвестная ошибка отправки.',
                ':id' => (int)$recipientRow['id'],
            ]);
        }
    }

    $noEmailCount = (int)$recipients['no_email_tickets'];
    $finalStatus = $failedCount === 0 && $noEmailCount === 0
        ? 'sent'
        : ($sentCount > 0 ? 'partial' : 'failed');
    $updateNotification = $pdo->prepare(
        'UPDATE schedule_notifications
         SET status = :status, sent_recipients = :sent_recipients,
             failed_recipients = :failed_recipients, sent_at = NOW()
         WHERE id = :id'
    );
    $updateNotification->execute([
        ':status' => $finalStatus,
        ':sent_recipients' => $sentCount,
        ':failed_recipients' => $failedCount,
        ':id' => $notificationId,
    ]);

    if (function_exists('audit_log_event')) {
        audit_log_event(
            $pdo,
            'schedule.notification_sent',
            'schedule',
            $scheduleId,
            (string)($schedule['event_title'] ?? ''),
            ['start_time' => $oldStart, 'end_time' => $oldEnd, 'status' => $schedule['status']],
            [
                'notification_type' => $payload['notification_type'],
                'new_start_time' => $payload['new_start_time'],
                'new_end_time' => $payload['new_end_time'],
                'status' => $finalStatus,
                'sent_recipients' => $sentCount,
                'failed_recipients' => $failedCount,
                'no_email_tickets' => $noEmailCount,
            ]
        );
    }

    $message = $finalStatus === 'sent'
        ? 'Изменение сохранено, уведомления отправлены всем клиентам.'
        : 'Изменение сохранено частично: отправлено ' . $sentCount . ', ошибок ' . $failedCount . ', без email билетов ' . $noEmailCount . '.';
    if ($refundSummary !== null) {
        $message .= ' Возвраты онлайн-заказов: подтверждено ' . $refundSummary['refunded']
            . ', в обработке у банка ' . $refundSummary['processing']
            . ', требуют внимания ' . $refundSummary['failed'] . '.';
    }
    schedule_notification_json_response(true, $message, [
        'notification_id' => $notificationId,
        'status' => $finalStatus,
        'refunds' => $refundSummary,
        'counts' => [
            'sent' => $sentCount,
            'failed' => $failedCount,
            'no_email_tickets' => $noEmailCount,
            'online_refund_orders' => $refundSummary['orders'] ?? 0,
            'online_refunds_confirmed' => $refundSummary['refunded'] ?? 0,
            'online_refunds_processing' => $refundSummary['processing'] ?? 0,
            'online_refunds_failed' => $refundSummary['failed'] ?? 0,
        ],
    ]);
} catch (InvalidArgumentException $exception) {
    schedule_notification_json_response(false, $exception->getMessage());
} catch (Throwable $exception) {
    error_log('[SCHEDULE NOTIFICATION] ' . $exception->getMessage());
    schedule_notification_json_response(false, 'Не удалось обработать уведомление. Проверьте миграцию schedule_notifications и журнал ошибок.');
}
