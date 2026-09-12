<?php

require_once __DIR__ . '/bcc.php';

if (file_exists(__DIR__ . '/../settings_manager.php')) {
    require_once __DIR__ . '/../settings_manager.php';
}

if (!function_exists('bcc_self_refund_config')) {
    function bcc_self_refund_config(?PDO $pdo = null): array
    {
        $defaults = [
            'enabled' => false,
            'window_minutes' => 30,
            'min_hours_before_event' => 2,
        ];

        if (!$pdo instanceof PDO || !function_exists('settings_get_value')) {
            return $defaults;
        }

        $enabled = settings_get_value($pdo, 'payments.bcc_self_refund_enabled', null);
        $window = settings_get_value($pdo, 'payments.bcc_self_refund_window_minutes', null);
        $minHours = settings_get_value($pdo, 'payments.bcc_self_refund_min_hours_before_event', null);

        if ($enabled !== null && $enabled !== '') {
            $defaults['enabled'] = in_array(strtolower(trim((string)$enabled)), ['1', 'true', 'yes', 'on'], true);
        }
        if ($window !== null && is_numeric($window)) {
            $defaults['window_minutes'] = max(1, min(43200, (int)$window));
        }
        if ($minHours !== null && is_numeric($minHours)) {
            $defaults['min_hours_before_event'] = max(0, min(720, (int)$minHours));
        }

        return $defaults;
    }
}

if (!function_exists('bcc_self_refund_status')) {
    function bcc_self_refund_status(PDO $pdo, array $session, array $tickets): array
    {
        $config = bcc_self_refund_config($pdo);
        $now = new DateTimeImmutable('now');
        $state = 'unavailable';
        $message = 'Самостоятельный возврат недоступен.';
        $deadline = null;

        if (empty($tickets)) {
            return compact('state', 'message', 'deadline');
        }

        $allRefunded = true;
        $hasRequested = false;
        foreach ($tickets as $ticket) {
            $refundStatus = (string)($ticket['refund_status'] ?? 'none');
            if ($refundStatus !== 'refunded') {
                $allRefunded = false;
            }
            if ($refundStatus === 'requested') {
                $hasRequested = true;
            }
        }

        if ($allRefunded) {
            return [
                'state' => 'refunded',
                'message' => 'Возврат по этому заказу уже выполнен.',
                'deadline' => null,
            ];
        }
        if ($hasRequested) {
            return [
                'state' => 'processing',
                'message' => 'Запрос на возврат уже обрабатывается.',
                'deadline' => null,
            ];
        }
        if (!$config['enabled']) {
            return compact('state', 'message', 'deadline');
        }
        if ((string)($session['status'] ?? '') !== 'paid') {
            return compact('state', 'message', 'deadline');
        }

        if (!empty($session['schedule_start'])) {
            try {
                $scheduleStart = new DateTimeImmutable((string)$session['schedule_start']);
                $deadlineDate = $scheduleStart->modify('-' . (int)$config['min_hours_before_event'] . ' hours');
                $deadline = $deadlineDate->format(DateTimeInterface::ATOM);
                if ($now > $deadlineDate) {
                    return [
                        'state' => 'expired',
                        'message' => 'Срок самостоятельного возврата истёк.',
                        'deadline' => $deadline,
                    ];
                }
            } catch (Throwable $e) {
                return compact('state', 'message', 'deadline');
            }
        } else {
            try {
                $createdAt = new DateTimeImmutable((string)$session['created_at']);
                $deadlineDate = $createdAt->modify('+' . (int)$config['window_minutes'] . ' minutes');
                $deadline = $deadlineDate->format(DateTimeInterface::ATOM);
                if ($now > $deadlineDate) {
                    return [
                        'state' => 'expired',
                        'message' => 'Срок самостоятельного возврата истёк.',
                        'deadline' => $deadline,
                    ];
                }
            } catch (Throwable $e) {
                return compact('state', 'message', 'deadline');
            }
        }

        return [
            'state' => 'allowed',
            'message' => 'Возврат доступен до указанной даты.',
            'deadline' => $deadline,
        ];
    }
}

if (!function_exists('bcc_build_refund_form')) {
    function bcc_build_refund_form(array $cfg, array $session, array $originalResponse, int $refundAmountCents): array
    {
        $order = trim((string)($session['order_number'] ?? ''));
        $orgAmount = number_format(((int)($session['amount_cents'] ?? 0)) / 100, 2, '.', '');
        $amount = number_format($refundAmountCents / 100, 2, '.', '');
        $currency = '398';
        $rrn = trim((string)($originalResponse['RRN'] ?? ''));
        $intRef = trim((string)($originalResponse['INT_REF'] ?? ''));
        $terminal = trim((string)($cfg['terminal'] ?? ''));
        $timestamp = bcc_timestamp();
        $merchGmt = '0';
        $trtype = '14';
        $nonce = bcc_generate_nonce();

        $macFields = [
            'ORDER' => $order,
            'ORG_AMOUNT' => $orgAmount,
            'AMOUNT' => $amount,
            'CURRENCY' => $currency,
            'RRN' => $rrn,
            'INT_REF' => $intRef,
            'TERMINAL' => $terminal,
            'TIMESTAMP' => $timestamp,
            'TRTYPE' => $trtype,
            'NONCE' => $nonce,
        ];
        $macString = '';
        foreach ($macFields as $value) {
            $value = (string)$value;
            $macString .= strlen($value) . $value;
        }

        return [
            'action' => bcc_endpoint($cfg),
            'fields' => [
                'ORG_AMOUNT' => $orgAmount,
                'AMOUNT' => $amount,
                'CURRENCY' => $currency,
                'ORDER' => $order,
                'MERCH_RN_ID' => (string)($session['merch_rn_id'] ?? ''),
                'RRN' => $rrn,
                'INT_REF' => $intRef,
                'TERMINAL' => $terminal,
                'TIMESTAMP' => $timestamp,
                'MERCH_GMT' => $merchGmt,
                'TRTYPE' => $trtype,
                'BACKREF' => bcc_backref_url($cfg),
                'LANG' => 'ru',
                'NONCE' => $nonce,
                'P_SIGN' => bcc_sign($macString, trim((string)($cfg['mac_key'] ?? ''))),
                'NOTIFY_URL' => bcc_notify_url($cfg),
            ],
            'mac_string' => $macString,
        ];
    }
}

if (!function_exists('bcc_parse_gateway_response')) {
    function bcc_parse_gateway_response(string $body): array
    {
        $trimmedBody = trim($body);
        if ($trimmedBody !== '') {
            $json = json_decode($trimmedBody, true);
            if (is_array($json)) {
                return $json;
            }
        }

        $data = [];
        parse_str($trimmedBody, $data);
        if (!empty($data)) {
            return $data;
        }

        if (preg_match_all('/name=["\']([^"\']+)["\'][^>]*value=["\']([^"\']*)["\']/i', $body, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $data[$match[1]] = html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }

        return $data;
    }
}

if (!function_exists('bcc_refund_response_success')) {
    function bcc_refund_response_success(array $data): bool
    {
        $action = trim((string)($data['ACTION'] ?? ''));
        $rc = trim((string)($data['RC'] ?? ''));
        return $action === '0' && ($rc === '' || $rc === '00');
    }
}

if (!function_exists('bcc_process_refund_request')) {
    function bcc_process_refund_request(PDO $pdo, array $session, array $tickets, array $originalResponse, string $source = 'bcc_refund'): array
    {
        $order = trim((string)($session['order_number'] ?? ''));
        if ($order === '' || empty($tickets)) {
            throw new RuntimeException('Данные заказа для возврата отсутствуют.');
        }
        if (trim((string)($originalResponse['RRN'] ?? '')) === '' || trim((string)($originalResponse['INT_REF'] ?? '')) === '') {
            throw new RuntimeException('В заказе отсутствуют данные банковской транзакции.');
        }

        $pdo->beginTransaction();
        try {
            $lockStmt = $pdo->prepare('SELECT id, status FROM payment_sessions WHERE id = :id LIMIT 1 FOR UPDATE');
            $lockStmt->execute([':id' => (int)$session['id']]);
            $lockedSession = $lockStmt->fetch(PDO::FETCH_ASSOC);
            if (!$lockedSession || (string)$lockedSession['status'] !== 'paid') {
                throw new RuntimeException('Оплата заказа не подтверждена.');
            }

            $ticketIds = array_map('intval', array_column($tickets, 'id'));
            $placeholders = implode(',', array_fill(0, count($ticketIds), '?'));
            $existingRefund = $pdo->prepare("SELECT refund_status FROM refunds WHERE ticket_id IN ($placeholders) AND refund_status IN ('requested', 'refunded') LIMIT 1");
            $existingRefund->execute($ticketIds);
            if ($existingRefund->fetchColumn()) {
                throw new RuntimeException('Запрос на возврат по этому заказу уже создан.');
            }

            $insertRefund = $pdo->prepare("INSERT INTO refunds
                (ticket_id, ticket_uid, schedule_id, refund_amount, refund_status, refund_method, refund_provider, reason, processed_by, created_at)
                VALUES (:ticket_id, :ticket_uid, :schedule_id, :refund_amount, 'requested', 'bank', 'bcc', :reason, :processed_by, NOW())");
            $refundOrigin = !empty($_SESSION['user']['id'])
                ? 'Возврат кассиром через BCC'
                : 'Самостоятельный возврат клиента';
            foreach ($tickets as $ticket) {
                $insertRefund->execute([
                    ':ticket_id' => (int)$ticket['id'],
                    ':ticket_uid' => $ticket['ticket_uid'],
                    ':schedule_id' => (int)$session['session_id'],
                    ':refund_amount' => number_format((float)$ticket['price'], 2, '.', ''),
                    ':reason' => $refundOrigin,
                    ':processed_by' => !empty($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null,
                ]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        $cfg = bcc_config($pdo);
        $refundForm = bcc_build_refund_form($cfg, $session, $originalResponse, (int)$session['amount_cents']);
        $ch = curl_init($refundForm['action']);
        if ($ch === false) {
            throw new RuntimeException('Не удалось создать соединение с BCC.');
        }
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($refundForm['fields'], '', '&'),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $body = curl_exec($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($body === false || $curlError !== '') {
            $data = [
                'ACTION' => '',
                'RC' => 'TRANSPORT_ERROR',
                'RC_TEXT' => $curlError !== '' ? $curlError : 'BCC gateway connection failed',
                'ORDER' => $order,
            ];
            bcc_mark_refund_result($pdo, $order, $data, false, $source . '_transport_error');
            return ['success' => false, 'state' => 'rejected', 'response' => $data, 'message' => 'Не удалось подключиться к банку.'];
        }

        $responseData = bcc_parse_gateway_response((string)$body);
        if (empty($responseData)) {
            return ['success' => true, 'state' => 'processing', 'response' => [], 'message' => 'Запрос на возврат принят и обрабатывается.'];
        }

        $success = bcc_refund_response_success($responseData);
        $result = bcc_mark_refund_result($pdo, $order, $responseData, $success, $source);
        return [
            'success' => $success,
            'state' => $result['status'],
            'response' => $responseData,
            'message' => $success
                ? 'Возврат подтверждён банком.'
                : 'Банк не подтвердил возврат. ' . trim((string)($responseData['RC_TEXT'] ?? '')),
        ];
    }
}

if (!function_exists('bcc_mark_refund_result')) {
    function bcc_mark_refund_result(PDO $pdo, string $order, array $data, bool $success, string $source = 'bcc_refund'): array
    {
        $stmt = $pdo->prepare("SELECT id, session_id, customer_id, amount_cents, status FROM payment_sessions WHERE order_number = :order LIMIT 1");
        $stmt->execute([':order' => $order]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$session) {
            throw new RuntimeException('Заказ не найден.');
        }

        $ticketStmt = $pdo->prepare("SELECT id, ticket_uid, price, refund_status FROM tickets WHERE payment_session_id = :payment_session_id ORDER BY id ASC");
        $ticketStmt->execute([':payment_session_id' => (int)$session['id']]);
        $tickets = $ticketStmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($tickets)) {
            throw new RuntimeException('Билеты заказа не найдены.');
        }

        $transactionRef = trim((string)($data['RRN'] ?? $data['INT_REF'] ?? $data['ORDER'] ?? ''));
        $responseJson = json_encode($data, JSON_UNESCAPED_UNICODE);
        $pdo->beginTransaction();
        try {
            if (!$success) {
                $reason = 'BCC response: ' . $responseJson;
                $updateRejected = $pdo->prepare("UPDATE refunds SET refund_status = 'rejected', refund_provider = 'bcc', refund_transaction_id = :transaction_id, reason = :reason WHERE ticket_id = :ticket_id AND refund_status = 'requested'");
                foreach ($tickets as $ticket) {
                    $updateRejected->execute([
                        ':transaction_id' => $transactionRef !== '' ? $transactionRef : null,
                        ':reason' => $reason,
                        ':ticket_id' => (int)$ticket['id'],
                    ]);
                }
                $pdo->commit();
                if (function_exists('audit_log_event')) {
                    audit_log_event($pdo, 'payment.refund_rejected', 'payment_session', (int)$session['id'], $order, [], [
                        'order' => $order,
                        'transaction_ref' => $transactionRef !== '' ? $transactionRef : null,
                        'action' => $data['ACTION'] ?? null,
                        'rc' => $data['RC'] ?? null,
                        'rc_text' => $data['RC_TEXT'] ?? null,
                    ]);
                }
                return ['status' => 'rejected', 'ticket_uids' => []];
            }

            $allRefunded = true;
            foreach ($tickets as $ticket) {
                if ((string)($ticket['refund_status'] ?? 'none') !== 'refunded') {
                    $allRefunded = false;
                    break;
                }
            }

            if ($allRefunded) {
                // Повторный callback не должен оставлять билет выданным или место занятым.
                $syncTicket = $pdo->prepare("UPDATE tickets
                    SET refund_status = 'refunded', refund_at = COALESCE(refund_at, NOW()),
                        status = 'cancelled', updated_at = NOW()
                    WHERE id = :id");
                $deleteOccupancy = $pdo->prepare("DELETE FROM seat_occupancy WHERE ticket_id = :ticket_id");
                foreach ($tickets as $ticket) {
                    $syncTicket->execute([':id' => (int)$ticket['id']]);
                    $deleteOccupancy->execute([':ticket_id' => (int)$ticket['id']]);
                }
                $pdo->commit();
                return ['status' => 'refunded', 'ticket_uids' => array_column($tickets, 'ticket_uid')];
            }

            $updateRefund = $pdo->prepare("UPDATE refunds SET refund_status = 'refunded', refund_method = 'bank', refund_provider = 'bcc', refund_transaction_id = :transaction_id, approved_at = NOW(), reason = :reason WHERE ticket_id = :ticket_id AND refund_status = 'requested'");
            $updateTicket = $pdo->prepare("UPDATE tickets SET refund_status = 'refunded', refund_at = NOW(), status = 'cancelled', updated_at = NOW() WHERE id = :id");
            $deleteOccupancy = $pdo->prepare("DELETE FROM seat_occupancy WHERE ticket_id = :ticket_id");
            $ticketUids = [];
            foreach ($tickets as $ticket) {
                $updateRefund->execute([
                    ':transaction_id' => $transactionRef !== '' ? $transactionRef : null,
                    ':reason' => $source . ': ' . $responseJson,
                    ':ticket_id' => (int)$ticket['id'],
                ]);
                $updateTicket->execute([':id' => (int)$ticket['id']]);
                $deleteOccupancy->execute([':ticket_id' => (int)$ticket['id']]);
                $ticketUids[] = $ticket['ticket_uid'];
            }

            $txStmt = $pdo->prepare("INSERT INTO cash_transactions (type, session_id, user_id, customer_id, amount_cents, currency, payment_method, payload, ticket_uids, created_at)
                VALUES ('refund', :session_id, 0, :customer_id, :amount_cents, 'KZT', 'card', :payload, :ticket_uids, NOW())");
            $txStmt->execute([
                ':session_id' => (int)$session['session_id'],
                ':customer_id' => $session['customer_id'] ?: null,
                ':amount_cents' => (int)$session['amount_cents'],
                ':payload' => $responseJson,
                ':ticket_uids' => json_encode($ticketUids, JSON_UNESCAPED_UNICODE),
            ]);

            $pdo->commit();
            if (function_exists('audit_log_event')) {
                audit_log_event($pdo, 'payment.refund_completed', 'payment_session', (int)$session['id'], $order, [], [
                    'order' => $order,
                    'amount_cents' => (int)$session['amount_cents'],
                    'transaction_ref' => $transactionRef !== '' ? $transactionRef : null,
                    'ticket_uids' => $ticketUids,
                ]);
            }
            return ['status' => 'refunded', 'ticket_uids' => $ticketUids];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
