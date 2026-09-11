<?php

if (!function_exists('bcc_finalize_payment_session')) {
    function bcc_finalize_payment_session(PDO $pdo, array $session, array $data, string $source = 'bcc_notify'): array
    {
        $paymentSessionId = (int)($session['id'] ?? 0);
        if ($paymentSessionId <= 0) {
            throw new RuntimeException('Payment session id is missing.');
        }

        $pdo->beginTransaction();
        try {
            $lockStmt = $pdo->prepare("SELECT id, session_id, event_id, hall_id, status, amount_cents, seats_payload, ticket_uids, customer_phone, customer_name, customer_email
                FROM payment_sessions WHERE id = :id LIMIT 1 FOR UPDATE");
            $lockStmt->execute([':id' => $paymentSessionId]);
            $lockedSession = $lockStmt->fetch(PDO::FETCH_ASSOC);
            if (!$lockedSession) {
                throw new RuntimeException('Payment session not found.');
            }

            $existingUids = json_decode($lockedSession['ticket_uids'] ?? '[]', true);
            if ((string)$lockedSession['status'] === 'paid' && is_array($existingUids) && !empty($existingUids)) {
                $pdo->commit();
                return ['ticket_uids' => $existingUids, 'already_paid' => true];
            }

            $seats = json_decode($lockedSession['seats_payload'] ?? '[]', true);
            if (!is_array($seats) || empty($seats)) {
                throw new RuntimeException('Seats payload is empty.');
            }

            $seatKeys = array_values(array_filter(array_map(function ($seat) {
                return str_replace(':', '-', trim((string)($seat['identifier'] ?? '')));
            }, $seats)));
            if (empty($seatKeys)) {
                throw new RuntimeException('Seat identifiers are empty.');
            }

            $existingTicketStmt = $pdo->prepare("SELECT ticket_uid FROM tickets WHERE payment_session_id = :payment_session_id ORDER BY id ASC");
            $existingTicketStmt->execute([':payment_session_id' => $paymentSessionId]);
            $existingTickets = $existingTicketStmt->fetchAll(PDO::FETCH_COLUMN, 0);
            if (!empty($existingTickets)) {
                $updateExisting = $pdo->prepare("UPDATE payment_sessions SET status = 'paid', ticket_uids = :ticket_uids, provider_response = :provider_response, updated_at = NOW() WHERE id = :id");
                $updateExisting->execute([
                    ':ticket_uids' => json_encode($existingTickets, JSON_UNESCAPED_UNICODE),
                    ':provider_response' => json_encode($data, JSON_UNESCAPED_UNICODE),
                    ':id' => $paymentSessionId,
                ]);
                $pdo->commit();
                return ['ticket_uids' => $existingTickets, 'already_paid' => true];
            }

            $placeholders = implode(',', array_fill(0, count($seatKeys), '?'));
            $deleteHolds = $pdo->prepare("DELETE FROM cash_holds WHERE session_id = ? AND seat_key IN ($placeholders)");
            $deleteHolds->execute(array_merge([(int)$lockedSession['session_id']], $seatKeys));

            $transactionSeats = [];
            $transactionTotal = 0.0;
            foreach ($seats as $seat) {
                $identifier = str_replace(':', '-', (string)($seat['identifier'] ?? ''));
                $price = isset($seat['price']) ? (float)$seat['price'] : 0.0;
                $transactionTotal += $price;
                $transactionSeats[] = [
                    'identifier' => $identifier,
                    'original_price' => round($price, 2),
                    'final_price' => round($price, 2),
                    'discount_amount' => 0,
                    'discount_percent' => 0,
                    'custom_discount' => 0,
                ];
            }
            $transactionPayload = [
                'action' => 'sale',
                'source' => $source,
                'provider' => 'bcc',
                'order' => $data['ORDER'] ?? '',
                'rrn' => $data['RRN'] ?? null,
                'int_ref' => $data['INT_REF'] ?? null,
                'seats_final' => $transactionSeats,
                'customer' => [
                    'full_name' => $lockedSession['customer_name'] ?: '',
                    'phone' => $lockedSession['customer_phone'] ?: '',
                    'email' => $lockedSession['customer_email'] ?: '',
                ],
                'customer_segment' => 'adult',
                'discount' => [
                    'total_discount' => 0,
                    'final_total' => round($transactionTotal, 2),
                ],
                'base_total' => round($transactionTotal, 2),
                'final_total' => round($transactionTotal, 2),
            ];
            $transactionStmt = $pdo->prepare("INSERT INTO cash_transactions (type, session_id, user_id, customer_id, amount_cents, currency, payment_method, payload, created_at)
                VALUES ('sale', :session_id, 0, :customer_id, :amount_cents, 'KZT', 'card', :payload, NOW())");
            $transactionStmt->execute([
                ':session_id' => (int)$lockedSession['session_id'],
                ':customer_id' => null,
                ':amount_cents' => (int)$lockedSession['amount_cents'],
                ':payload' => json_encode($transactionPayload, JSON_UNESCAPED_UNICODE),
            ]);
            $transactionId = (int)$pdo->lastInsertId();

            $customerId = null;
            if (!empty($lockedSession['customer_phone'])) {
                $normalizedCustomerPhone = normalize_customer_phone($lockedSession['customer_phone']);
                $customerPhoneDigits = customer_phone_digits($normalizedCustomerPhone);
                $lockedSession['customer_phone'] = $normalizedCustomerPhone;
                $customerStmt = $pdo->prepare("SELECT id FROM customers WHERE phone = :phone OR phone = :phone_digits LIMIT 1");
                $customerStmt->execute([':phone' => $normalizedCustomerPhone, ':phone_digits' => $customerPhoneDigits]);
                $customer = $customerStmt->fetch(PDO::FETCH_ASSOC);
                if ($customer) {
                    $customerId = (int)$customer['id'];
                } else {
                    $customerUid = bin2hex(random_bytes(8));
                    $insertCustomer = $pdo->prepare("INSERT INTO customers (customer_uid, full_name, phone, email, created_at, updated_at)
                        VALUES (:uid, :full_name, :phone, :email, NOW(), NOW())");
                    $insertCustomer->execute([
                        ':uid' => $customerUid,
                        ':full_name' => $lockedSession['customer_name'] ?: 'Гость',
                        ':phone' => $lockedSession['customer_phone'],
                        ':email' => $lockedSession['customer_email'] ?: null,
                    ]);
                    $customerId = (int)$pdo->lastInsertId();
                }
            }

            $updateTransactionCustomer = $pdo->prepare("UPDATE cash_transactions SET customer_id = :customer_id WHERE id = :id");
            $updateTransactionCustomer->execute([
                ':customer_id' => $customerId ?: null,
                ':id' => $transactionId,
            ]);

            $ticketUids = [];
            foreach ($seats as $seat) {
                $identifier = str_replace(':', '-', (string)($seat['identifier'] ?? ''));
                $price = isset($seat['price']) ? (float)$seat['price'] : 0.0;
                $ticketUid = bin2hex(random_bytes(8));

                $insertTicket = $pdo->prepare("INSERT INTO tickets
                    (schedule_id, event_id, hall_id, seat_identifier, price, status, payment_status, payment_provider, payment_transaction_id, payment_session_id, customer_id, customer_name, customer_phone, customer_email, ticket_uid, channel, purchased_at, created_at, updated_at)
                    VALUES
                    (:schedule_id, :event_id, :hall_id, :seat_identifier, :price, 'issued', 'paid', 'bcc', :txid, :payment_session_id, :customer_id, :customer_name, :customer_phone, :customer_email, :ticket_uid, 'web', NOW(), NOW(), NOW())");
                $insertTicket->execute([
                    ':schedule_id' => (int)$lockedSession['session_id'],
                    ':event_id' => (int)$lockedSession['event_id'],
                    ':hall_id' => (int)$lockedSession['hall_id'],
                    ':seat_identifier' => $identifier,
                    ':price' => number_format($price, 2, '.', ''),
                    ':txid' => $transactionId,
                    ':payment_session_id' => $paymentSessionId,
                    ':customer_id' => $customerId,
                    ':customer_name' => $lockedSession['customer_name'] ?: null,
                    ':customer_phone' => $lockedSession['customer_phone'] ?: null,
                    ':customer_email' => $lockedSession['customer_email'] ?: null,
                    ':ticket_uid' => $ticketUid,
                ]);
                $ticketUids[] = $ticketUid;
            }

            $updateSession = $pdo->prepare("UPDATE payment_sessions SET status = 'paid', customer_id = :customer_id, cash_transaction_id = :txid, ticket_uids = :ticket_uids, provider_response = :provider_response, notify_received_at = COALESCE(notify_received_at, NOW()), updated_at = NOW() WHERE id = :id");
            $updateSession->execute([
                ':customer_id' => $customerId,
                ':txid' => $transactionId,
                ':ticket_uids' => json_encode($ticketUids, JSON_UNESCAPED_UNICODE),
                ':provider_response' => json_encode($data, JSON_UNESCAPED_UNICODE),
                ':id' => $paymentSessionId,
            ]);

            $updateTransaction = $pdo->prepare("UPDATE cash_transactions SET ticket_uids = :ticket_uids, payload = :payload WHERE id = :id");
            $transactionPayload['ticket_uids'] = $ticketUids;
            $updateTransaction->execute([
                ':ticket_uids' => json_encode($ticketUids, JSON_UNESCAPED_UNICODE),
                ':payload' => json_encode($transactionPayload, JSON_UNESCAPED_UNICODE),
                ':id' => $transactionId,
            ]);

            $pdo->commit();

            if (function_exists('audit_log_event')) {
                audit_log_event($pdo, 'payment.sale_completed', 'payment_session', $paymentSessionId, (string)($data['ORDER'] ?? ''), [], [
                    'source' => $source,
                    'order' => $data['ORDER'] ?? null,
                    'amount_cents' => (int)$lockedSession['amount_cents'],
                    'transaction_id' => $transactionId,
                    'ticket_uids' => $ticketUids,
                ]);
            }

            if (!empty($ticketUids) && function_exists('ticket_pdf_generate_by_ticket_uid')) {
                foreach ($ticketUids as $ticketUid) {
                    $pdfError = null;
                    if (!ticket_pdf_generate_by_ticket_uid($ticketUid, false, $pdfError)) {
                        error_log('[BCC] PDF generation failed for ' . $ticketUid . ': ' . ($pdfError ?? 'unknown'));
                    }
                }
            }

            return ['ticket_uids' => $ticketUids, 'already_paid' => false];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}