<?php
// ajax/public_payment.php
// Публичный AJAX-обработчик создания BCC-платёжной сессии для виджета.

require_once __DIR__ . '/../init.php';

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

$allowedOrigin = defined('TILDA_WIDGET_ORIGIN') ? (string)constant('TILDA_WIDGET_ORIGIN') : 'https://zhassahna.kz';
if ($allowedOrigin !== '') {
    header('Access-Control-Allow-Origin: ' . $allowedOrigin);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/payment/bcc.php';
require_once __DIR__ . '/../includes/settings_manager.php';
require_once __DIR__ . '/../includes/promo_codes.php';
require_once __DIR__ . '/../includes/general_admission.php';

function json_response($data)
{
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function public_payment_setting(PDO $pdo, string $key, $default = null) {
    $value = function_exists('settings_get_value') ? settings_get_value($pdo, $key, $default) : $default;
    return $value === null || $value === '' ? $default : $value;
}

function public_payment_normalize_seats(array $seatsRaw, array $session): array
{
    if (general_admission_is_session($session)) {
        $normalized = [];
        $seen = [];
        $capacity = (int)$session['capacity'];
        $price = (float)($session['base_price'] ?? 0);
        if ($price <= 0) {
            throw new InvalidArgumentException('Цена билета для сеанса свободной рассадки не задана.');
        }
        foreach ($seatsRaw as $seatRaw) {
            $rawIdentifier = is_array($seatRaw) ? (string)($seatRaw['identifier'] ?? '') : (string)$seatRaw;
            $identifier = str_replace(':', '-', strtoupper(trim($rawIdentifier)));
            $number = general_admission_identifier_number($identifier);
            if ($number === null || $number > $capacity) {
                throw new InvalidArgumentException('Выбрано место за пределами вместимости сеанса.');
            }
            if (isset($seen[$number])) {
                throw new InvalidArgumentException('Одно и то же место выбрано несколько раз.');
            }
            $seen[$number] = true;
            $normalized[] = [
                'identifier' => general_admission_identifier($number),
                'original_price' => round($price, 2),
                'final_price' => round($price, 2),
            ];
        }
        return $normalized;
    }

    $seatMap = json_decode((string)($session['seat_map'] ?? ''), true);
    $hallSeatMap = json_decode((string)($session['hall_seatmap'] ?? ''), true);
    $layoutMap = is_array($seatMap) && !empty($seatMap['rows'])
        ? $seatMap
        : $hallSeatMap;
    $seatPrices = is_array($hallSeatMap) && isset($hallSeatMap['seats']) && is_array($hallSeatMap['seats'])
        ? $hallSeatMap['seats']
        : [];
    if (is_array($seatMap) && isset($seatMap['seats']) && is_array($seatMap['seats'])) {
        $seatPrices = array_replace($seatPrices, $seatMap['seats']);
    }
    $priceRanges = json_decode((string)($session['price_ranges'] ?? ''), true);
    $priceRanges = is_array($priceRanges) ? $priceRanges : [];
    $normalized = [];
    $seen = [];

    foreach ($seatsRaw as $seatRaw) {
        $identifier = is_array($seatRaw) && isset($seatRaw['identifier'])
            ? str_replace(':', '-', trim((string)$seatRaw['identifier']))
            : (is_string($seatRaw) ? str_replace(':', '-', trim($seatRaw)) : '');
        if ($identifier === '' || isset($seen[$identifier])) {
            continue;
        }
        $seen[$identifier] = true;
        $parts = preg_split('/[-:]/', $identifier, 2);
        if (count($parts) !== 2 || !is_numeric($parts[0]) || !is_numeric($parts[1])) {
            throw new InvalidArgumentException('Некорректный номер выбранного места.');
        }
        $rowNumber = (int)$parts[0];
        $seatNumber = (int)$parts[1];
        $validSeat = false;
        if (is_array($layoutMap) && !empty($layoutMap['rows']) && is_array($layoutMap['rows'])) {
            foreach ($layoutMap['rows'] as $row) {
                if (!is_array($row) || (int)($row['number'] ?? 0) !== $rowNumber) {
                    continue;
                }
                foreach (($row['segments'] ?? []) as $segment) {
                    if (!is_array($segment)) {
                        continue;
                    }
                    $start = (int)($segment['start'] ?? 0);
                    $end = (int)($segment['end'] ?? 0);
                    if ($start > 0 && $end > 0 && $seatNumber >= min($start, $end) && $seatNumber <= max($start, $end)) {
                        $validSeat = true;
                        break 2;
                    }
                }
            }
        } else {
            $validSeat = isset($seatPrices[$identifier]) || isset($seatPrices[str_replace('-', ':', $identifier)]);
        }
        if (!$validSeat) {
            throw new InvalidArgumentException('Выбранное место ' . $identifier . ' отсутствует в схеме зала.');
        }

        $price = 0.0;
        $seatData = $seatPrices[$identifier] ?? $seatPrices[str_replace('-', ':', $identifier)] ?? null;
        $hasDirectPrice = false;
        if (is_array($seatData)) {
            $seatPrice = $seatData['price'] ?? $seatData['meta']['price'] ?? null;
            if (is_numeric($seatPrice)) {
                $price = (float)$seatPrice;
                $hasDirectPrice = true;
            }
        }

        foreach ($priceRanges as $range) {
            if ($hasDirectPrice) {
                break;
            }
            if (!is_array($range)) {
                continue;
            }
            $rangePrice = $range['price'] ?? $range['value'] ?? $range['p'] ?? null;
            if (!is_numeric($rangePrice) || (float)$rangePrice <= 0) {
                continue;
            }
            $seatIds = $range['seat_ids'] ?? [];
            $matchesSeat = is_array($seatIds) && (
                in_array($identifier, $seatIds, true)
                || in_array(str_replace('-', ':', $identifier), $seatIds, true)
            );
            $rowStart = $range['row_start'] ?? $range['row_end'] ?? null;
            $rowEnd = $range['row_end'] ?? $range['row_start'] ?? null;
            $matchesRow = is_numeric($rowStart) && is_numeric($rowEnd) && $rowNumber > 0
                && $rowNumber >= min((int)$rowStart, (int)$rowEnd)
                && $rowNumber <= max((int)$rowStart, (int)$rowEnd);
            if ($matchesSeat || $matchesRow) {
                $price = (float)$rangePrice;
            }
        }

        if ($price <= 0) {
            throw new InvalidArgumentException('Не удалось определить цену места ' . $identifier . '. Обновите схему зала.');
        }
        $normalized[] = [
            'identifier' => $identifier,
            'original_price' => round($price, 2),
            'final_price' => round($price, 2),
        ];
    }

    return $normalized;
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    json_response(['success' => false, 'message' => 'База данных недоступна']);
}

if (app_setting_enabled($pdo, 'system.maintenance_enabled')) {
    json_response(['success' => false, 'message' => 'Сайт временно обновляется. Скоро вернёмся.']);
}

if (!bcc_is_enabled($pdo)) {
    json_response(['success' => false, 'message' => 'Оплата картой временно недоступна']);
}

$raw = file_get_contents('php://input');
$input = [];
if ($raw) {
    $tmp = json_decode($raw, true);
    if (is_array($tmp)) $input = $tmp;
}
if (!empty($_POST) && is_array($_POST)) $input = array_merge($input, $_POST);

$action = isset($_GET['action']) ? trim((string)$_GET['action']) : (isset($input['action']) ? trim((string)$input['action']) : '');

switch ($action) {
    case 'validate_promo':
        $sessionId = (int)($input['session_id'] ?? 0);
        $seatsRaw = $input['seats'] ?? [];
        $code = promo_normalize_code((string)($input['promo_code'] ?? ''));
        if ($sessionId <= 0 || !is_array($seatsRaw) || !$seatsRaw || $code === '') {
            json_response(['success' => false, 'message' => 'Выберите места и введите промокод.']);
        }
        try {
            $sessionStmt = $pdo->prepare("SELECT s.*, h.seat_map AS hall_seatmap FROM schedules s
                LEFT JOIN halls h ON h.id = s.hall_id
                WHERE s.id = :id AND s.status IN ('upcoming', 'active') AND s.start_time >= NOW()
                LIMIT 1");
            $sessionStmt->execute([':id' => $sessionId]);
            $promoSession = $sessionStmt->fetch(PDO::FETCH_ASSOC);
            if (!$promoSession) {
                json_response(['success' => false, 'message' => 'Сеанс не найден или продажа уже закрыта.']);
            }
            $normalizedSeats = public_payment_normalize_seats($seatsRaw, $promoSession);
            $maxTickets = max(1, (int)public_payment_setting($pdo, 'tickets.max_tickets_per_user', '6'));
            if (!$normalizedSeats || count($normalizedSeats) > $maxTickets) {
                json_response(['success' => false, 'message' => 'Проверьте количество выбранных билетов.']);
            }
            $applied = promo_apply_to_seats($pdo, $code, $normalizedSeats);
            $subtotalCents = (int)round(array_sum(array_column($normalizedSeats, 'original_price')) * 100);
            json_response([
                'success' => true,
                'code' => $applied['promo']['code'],
                'discount_cents' => $applied['discount_cents'],
                'subtotal_cents' => $subtotalCents,
                'total_cents' => $subtotalCents - $applied['discount_cents'],
            ]);
        } catch (InvalidArgumentException $exception) {
            json_response(['success' => false, 'message' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            error_log('public_payment validate_promo error: ' . $exception->getMessage());
            json_response(['success' => false, 'message' => 'Не удалось проверить промокод.']);
        }
        break;

    case 'create_bcc_session':
        $session_id = isset($input['session_id']) ? (int)$input['session_id'] : 0;
        $seats_raw = isset($input['seats']) ? $input['seats'] : [];
        $customer_name = isset($input['customer_name']) ? trim((string)$input['customer_name']) : '';
        $customer_phone = isset($input['customer_phone']) ? trim((string)$input['customer_phone']) : '';
        $customer_email = isset($input['customer_email']) ? trim((string)$input['customer_email']) : '';
        $client_hold_token = trim((string)($input['client_hold_token'] ?? ''));

        if (!$session_id) {
            json_response(['success' => false, 'message' => 'Не указан сеанс']);
        }
        if (!is_array($seats_raw) || empty($seats_raw)) {
            json_response(['success' => false, 'message' => 'Не выбраны места']);
        }
        if ($customer_phone === '') {
            json_response(['success' => false, 'message' => 'Укажите номер телефона']);
        }

        $normalizedPhone = normalize_customer_phone($customer_phone);
        $phoneDigits = customer_phone_digits($normalizedPhone);
        if (strlen($phoneDigits) < 10) {
            json_response(['success' => false, 'message' => 'Некорректный номер телефона']);
        }

        $stmt = $pdo->prepare("SELECT s.*, e.title AS event_title, h.seat_map AS hall_seatmap
            FROM schedules s
            LEFT JOIN events e ON s.event_id = e.id
            LEFT JOIN halls h ON h.id = s.hall_id
            WHERE s.id = :id AND s.status IN ('upcoming','active') AND s.start_time >= NOW()
            LIMIT 1");
        $stmt->execute([':id' => $session_id]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$session) {
            json_response(['success' => false, 'message' => 'Сеанс не найден или продажа недоступна']);
        }

        $event_id = (int)$session['event_id'];
        $hall_id = (int)$session['hall_id'];

        $seats = [];
        $seat_keys = [];
        try {
            $seats = public_payment_normalize_seats($seats_raw, $session);
        } catch (InvalidArgumentException $exception) {
            json_response(['success' => false, 'message' => $exception->getMessage()]);
        }
        foreach ($seats as $seat) {
            $identifier = $seat['identifier'];
            $seat_keys[] = $identifier;
        }

        if (empty($seats)) {
            json_response(['success' => false, 'message' => 'Не удалось распознать выбранные места']);
        }

        $maxTickets = max(1, (int)public_payment_setting($pdo, 'tickets.max_tickets_per_user', '6'));
        if (count($seats) > $maxTickets) {
            json_response(['success' => false, 'message' => 'В одном заказе можно оформить не более ' . $maxTickets . ' билетов.']);
        }

        $placeholders = implode(',', array_fill(0, count($seat_keys), '?'));
        $params = array_merge([$session_id], $seat_keys);

        try {
            // Просроченные холды больше не блокируют место и могут мешать INSERT
            // из-за уникального ключа до очередного запуска cron.
            $expiredHoldStmt = $pdo->prepare("DELETE FROM cash_holds
                WHERE session_id = ?
                    AND seat_key IN ($placeholders)
                    AND expires_at <= NOW()");
            $expiredHoldStmt->execute($params);

            // Удаляем старые брони виджета для этих мест, чтобы пользователь мог повторить попытку
            if ($client_hold_token !== '') {
                $delHoldStmt = $pdo->prepare("DELETE FROM cash_holds WHERE session_id = ? AND seat_key IN ($placeholders) AND meta LIKE ?");
                $delHoldStmt->execute(array_merge($params, ['%' . '"token":"' . $client_hold_token . '"' . '%']));
            } else {
                $delHoldStmt = $pdo->prepare("DELETE FROM cash_holds WHERE session_id = ? AND seat_key IN ($placeholders) AND meta LIKE '%widget_online_payment%'");
                $delHoldStmt->execute($params);
            }

            $soldStmt = $pdo->prepare("SELECT seat_identifier FROM tickets WHERE schedule_id = ? AND seat_identifier IN ($placeholders) AND status IN ('issued', 'used') AND COALESCE(refund_status, 'none') <> 'refunded'");
            $soldStmt->execute($params);
            $sold = $soldStmt->fetchAll(PDO::FETCH_COLUMN, 0);
            if (!empty($sold)) {
                json_response(['success' => false, 'message' => 'Некоторые места уже проданы', 'seats' => $sold]);
            }

            $holdStmt = $pdo->prepare("SELECT seat_key FROM cash_holds WHERE session_id = ? AND seat_key IN ($placeholders) AND expires_at > NOW()");
            $holdStmt->execute($params);
            $held = $holdStmt->fetchAll(PDO::FETCH_COLUMN, 0);
            if (!empty($held)) {
                json_response(['success' => false, 'message' => 'Некоторые места уже забронированы', 'seats' => $held]);
            }
        } catch (Exception $e) {
            json_response(['success' => false, 'message' => 'Ошибка проверки мест']);
        }

        $promoCode = promo_normalize_code((string)($input['promo_code'] ?? ''));
        if ($promoCode !== '') {
            try {
                $appliedPromo = promo_apply_to_seats($pdo, $promoCode, $seats);
                $seats = $appliedPromo['seats'];
            } catch (InvalidArgumentException $exception) {
                json_response(['success' => false, 'message' => $exception->getMessage()]);
            }
        }

        $amountCents = (int)round(array_sum(array_column($seats, 'final_price')) * 100);
        if ($amountCents <= 0) {
            json_response(['success' => false, 'message' => 'Сумма заказа должна быть больше 0']);
        }

        $reserveMinutes = bcc_payment_hold_minutes();
        $expires_at = (new DateTime("+{$reserveMinutes} minutes"))->format('Y-m-d H:i:s');

        $pdo->beginTransaction();
        try {
            if (general_admission_is_session($session)) {
                $lockSchedule = $pdo->prepare('SELECT id FROM schedules WHERE id = :id FOR UPDATE');
                $lockSchedule->execute([':id' => $session_id]);
                if (!$lockSchedule->fetchColumn()) {
                    throw new RuntimeException('Сеанс недоступен.');
                }
                $inventory = general_admission_inventory($pdo, $session_id);
                foreach ($seat_keys as $seatKey) {
                    $number = general_admission_identifier_number((string)$seatKey);
                    if ($number === null || !empty($inventory['sold'][$number]) || !empty($inventory['held'][$number])) {
                        throw new RuntimeException('Одно или несколько выбранных мест уже заняты. Обновите страницу и выберите места заново.');
                    }
                }
                $occupied = $inventory['sold'] + $inventory['held'];
                if (count($occupied) + count($seat_keys) > (int)$session['capacity']) {
                    throw new RuntimeException('Свободных мест осталось меньше. Обновите страницу и выберите места заново.');
                }
            }

            $holdIns = $pdo->prepare("INSERT INTO cash_holds (session_id, seat_key, user_id, expires_at, meta) VALUES (:session_id, :seat_key, 0, :expires_at, :meta)");
            foreach ($seat_keys as $sk) {
                $holdIns->execute([
                    ':session_id' => $session_id,
                    ':seat_key' => $sk,
                    ':expires_at' => $expires_at,
                    ':meta' => json_encode(['source' => 'widget_online_payment'], JSON_UNESCAPED_UNICODE),
                ]);
            }

            $order = bcc_generate_order();
            $merchRnId = bcc_generate_merch_rn_id();
            $desc = 'Tickets ' . $session['event_title'];
            if (mb_strlen($desc) > 225) {
                $desc = mb_substr($desc, 0, 222) . '...';
            }

            $ins = $pdo->prepare("INSERT INTO payment_sessions
                (session_id, event_id, hall_id, order_number, merch_rn_id, amount_cents, currency, status, provider, customer_name, customer_phone, customer_email, seats_payload)
                VALUES
                (:session_id, :event_id, :hall_id, :order_number, :merch_rn_id, :amount_cents, 'KZT', 'pending', 'bcc', :customer_name, :customer_phone, :customer_email, :seats_payload)");
            $ins->execute([
                ':session_id' => $session_id,
                ':event_id' => $event_id,
                ':hall_id' => $hall_id,
                ':order_number' => $order,
                ':merch_rn_id' => $merchRnId,
                ':amount_cents' => $amountCents,
                ':customer_name' => $customer_name ?: null,
                ':customer_phone' => $normalizedPhone,
                ':customer_email' => $customer_email ?: null,
                ':seats_payload' => json_encode($seats, JSON_UNESCAPED_UNICODE),
            ]);

            $cfg = bcc_config($pdo);
            // Принудительно используем публичный BACKREF-обработчик, независимо от устаревшего значения в БД
            if (defined('PUBLIC_BCC_BACKREF_PATH')) {
                $cfg['backref_path'] = (string) constant('PUBLIC_BCC_BACKREF_PATH');
            }
            $clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $form = bcc_build_payment_form($cfg, $order, $merchRnId, $amountCents, $desc, $clientIp, $phoneDigits);

            $pdo->commit();

            json_response([
                'success' => true,
                'order' => $order,
                'action' => $form['action'],
                'fields' => $form['fields'],
            ]);
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('public_payment create_bcc_session error: ' . $e->getMessage());
            json_response(['success' => false, 'message' => 'Не удалось создать платёжную сессию']);
        }
        break;

    case 'check_status':
        $order = isset($input['order']) ? trim((string)$input['order']) : '';
        if ($order === '') {
            json_response(['success' => false, 'message' => 'Не указан номер заказа']);
        }
        try {
            $stmt = $pdo->prepare("SELECT id, status, provider_response, ticket_uids, amount_cents FROM payment_sessions WHERE order_number = :order LIMIT 1");
            $stmt->execute([':order' => $order]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                json_response(['success' => false, 'message' => 'Платёжная сессия не найдена']);
            }
            json_response([
                'success' => true,
                'status' => $row['status'],
                'provider_response' => $row['provider_response'] ? json_decode($row['provider_response'], true) : null,
                'ticket_uids' => $row['ticket_uids'] ? json_decode($row['ticket_uids'], true) : [],
                'amount_cents' => (int)$row['amount_cents'],
            ]);
        } catch (Exception $e) {
            error_log('public_payment check_status error: ' . $e->getMessage());
            json_response(['success' => false, 'message' => 'Ошибка проверки статуса']);
        }
        break;

    default:
        json_response(['success' => false, 'message' => 'Неизвестное действие']);
}
