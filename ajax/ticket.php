<?php
// ajax/ticket.php
// Backend for tickets list, refund, suggestions, ticket details and ticket creation
// seat_occupancy-backed implementation:
// - seat_occupancy is the source of truth for current occupied seats
// - Create ticket: atomically reserve seat in seat_occupancy, then INSERT new ticket (always new id), then link occupancy -> ticket_id
// - Refund: update tickets.refund_status = 'refunded' and remove occupancy row (free seat). No inserts into refunds table.

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/payment/bcc_refund.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

$action = isset($_REQUEST['action']) ? trim((string)$_REQUEST['action']) : 'list';

function json_resp($data, $http_code = 200) {
    http_response_code($http_code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function check_csrf() {
    $token = $_POST['csrf_token'] ?? $_REQUEST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($token) || empty($_SESSION['csrf_token']) || !hash_equals((string)($_SESSION['csrf_token']), (string)$token)) {
        json_resp(['success' => false, 'message' => 'Неверный CSRF токен'], 403);
    }
}

function safe_int($v, $default = 0) {
    if ($v === null || $v === '') return $default;
    if (is_numeric($v)) return (int)$v;
    return $default;
}

function generate_ticket_uid($length = 16) {
    try {
        $bytes = random_bytes((int)ceil($length / 2));
        return substr(bin2hex($bytes), 0, $length);
    } catch (Exception $e) {
        return substr(md5(uniqid('', true)), 0, $length);
    }
}

/**
 * Проверяет, существует ли колонка в таблице в текущей БД
 * Возвращает true/false
 */
function column_exists(PDO $pdo, $table, $column) {
    $sql = "SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute([':table' => $table, ':column' => $column]);
    return (bool)$st->fetchColumn();
}

function ticket_debug_log($msg) {
    global $DEBUG_TICKET_LOG;
    if (!$DEBUG_TICKET_LOG) return;
    @file_put_contents('/tmp/ticket_debug.log', date('[Y-m-d H:i:s] ') . $msg . PHP_EOL, FILE_APPEND);
}

function extract_discount_from_transaction_payload($payloadRaw) {
    $out = [
        'total_discount' => 0.0,
        'base_total' => null,
        'final_total' => null,
        'auto_percent' => null,
        'custom_type' => null,
    ];

    if (!is_string($payloadRaw) || trim($payloadRaw) === '') {
        return $out;
    }

    $payload = json_decode($payloadRaw, true);
    if (!is_array($payload)) {
        return $out;
    }
    $discount = isset($payload['discount']) && is_array($payload['discount']) ? $payload['discount'] : [];
    if (empty($discount)) {
        return $out;
    }

    $out['total_discount'] = isset($discount['total_discount']) && is_numeric($discount['total_discount'])
        ? max(0.0, (float)$discount['total_discount'])
        : 0.0;
    $out['base_total'] = isset($discount['base_total']) && is_numeric($discount['base_total'])
        ? (float)$discount['base_total']
        : null;
    $out['final_total'] = isset($discount['final_total']) && is_numeric($discount['final_total'])
        ? (float)$discount['final_total']
        : null;
    $out['auto_percent'] = isset($discount['auto_percent']) && is_numeric($discount['auto_percent'])
        ? (float)$discount['auto_percent']
        : null;
    $out['custom_type'] = isset($discount['custom_type']) ? (string)$discount['custom_type'] : null;

    return $out;
}

function allocate_ticket_discount($ticketPrice, array $discountInfo, $txTicketCount = 1) {
    $ticketPrice = is_numeric($ticketPrice) ? max(0.0, (float)$ticketPrice) : 0.0;
    $txTicketCount = max(1, (int)$txTicketCount);
    $totalDiscount = isset($discountInfo['total_discount']) ? (float)$discountInfo['total_discount'] : 0.0;
    $finalTotal = isset($discountInfo['final_total']) && is_numeric($discountInfo['final_total'])
        ? max(0.0, (float)$discountInfo['final_total'])
        : 0.0;

    if ($totalDiscount <= 0.0) {
        return 0.0;
    }

    if ($finalTotal > 0.0 && $ticketPrice > 0.0) {
        return round($totalDiscount * ($ticketPrice / $finalTotal), 2);
    }

    return round($totalDiscount / $txTicketCount, 2);
}

/**
 * Map channel and/or cash_transactions.payment_method to payment type display string.
 * Priority:
 *  - If transaction payment_method is present, use it (treat 'card' and variants as card).
 *  - Else, if channel implies card (web, online, card, terminal, mobile) => 'Картой'
 *  - Otherwise => 'Наличные'
 */
function map_payment_type_display($channel, $tx_payment_method = null) {
    $cardMethods = ['card', 'card_online', 'card_terminal', 'bank_card', 'card_payment', 'card_pos'];
    if (!empty($tx_payment_method)) {
        $pm = strtolower((string)$tx_payment_method);
        if (in_array($pm, $cardMethods, true) || $pm === 'card') {
            return 'Картой';
        }
        if (strpos($pm, 'card') !== false || strpos($pm, 'visa') !== false || strpos($pm, 'master') !== false) {
            return 'Картой';
        }
        if ($pm === 'cash' || $pm === 'nal' || $pm === 'cashier') {
            return 'Наличные';
        }
    }

    $ch = strtolower((string)$channel);
    $cardChannels = ['web', 'online', 'card', 'terminal', 'mobile'];
    if (in_array($ch, $cardChannels, true)) {
        return 'Картой';
    }
    return 'Наличные';
}

// --- SUGGEST: sessions ---
if ($action === 'suggest_sessions') {
    $q = trim((string)($_GET['q'] ?? ''));
    $filter_uid = trim((string)($_GET['uid'] ?? ''));
    $filter_customer = trim((string)($_GET['customer'] ?? ''));

    if ($q === '') json_resp(['success' => true, 'data' => []]);

    try {
        $sql = "SELECT DISTINCT s.id AS schedule_id, COALESCE(e.title, '') AS title, s.start_time
                FROM schedules s
                LEFT JOIN events e ON e.id = s.event_id
                WHERE (e.title LIKE :q1 OR s.start_time LIKE :q2 OR s.id = :id)";

        $params = [
            'q1' => '%' . $q . '%',
            'q2' => '%' . $q . '%',
            'id' => is_numeric($q) ? (int)$q : 0
        ];

        if ($filter_uid !== '') {
            $sql .= " AND EXISTS (SELECT 1 FROM tickets t2 WHERE t2.schedule_id = s.id AND t2.ticket_uid LIKE :fuid)";
            $params['fuid'] = '%' . $filter_uid . '%';
        }

        if ($filter_customer !== '') {
            $sql .= " AND EXISTS (SELECT 1 FROM tickets t3 JOIN customers c3 ON c3.id = t3.customer_id WHERE t3.schedule_id = s.id AND c3.full_name LIKE :fcust)";
            $params['fcust'] = '%' . $filter_customer . '%';
        }

        $sql .= " ORDER BY s.start_time ASC LIMIT 20";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $out = [];
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $out[] = [
                'id' => $r['schedule_id'],
                'title' => $r['title'],
                'start_time' => $r['start_time']
            ];
        }
        json_resp(['success' => true, 'data' => $out]);
    } catch (Exception $e) {
        error_log('ajax/ticket.php suggest_sessions error: ' . $e->getMessage());
        json_resp(['success' => false, 'message' => 'Ошибка подсказок сеансов']);
    }
}

// --- SUGGEST: uid / customer ---
if ($action === 'suggest_q') {
    $q = trim((string)($_GET['q'] ?? ''));
    $filter_session = trim((string)($_GET['session'] ?? ''));
    $filter_uid = trim((string)($_GET['uid'] ?? ''));
    $filter_customer = trim((string)($_GET['customer'] ?? ''));

    if ($q === '') json_resp(['success' => true, 'data' => []]);

    try {
        $out = [];

        $sqlUid = "SELECT DISTINCT t.ticket_uid
                   FROM tickets t
                   LEFT JOIN schedules s ON s.id = t.schedule_id
                   LEFT JOIN events e ON e.id = s.event_id
                   LEFT JOIN customers c ON c.id = t.customer_id
                   WHERE t.ticket_uid LIKE :q_uid";
        $paramsUid = ['q_uid' => '%' . $q . '%'];

        if ($filter_session !== '') {
            if (is_numeric($filter_session)) {
                $sqlUid .= " AND t.schedule_id = :fsched";
                $paramsUid['fsched'] = (int)$filter_session;
            } else {
                $sqlUid .= " AND (e.title LIKE :fsession OR s.start_time LIKE :fsession2)";
                $paramsUid['fsession'] = '%' . $filter_session . '%';
                $paramsUid['fsession2'] = '%' . $filter_session . '%';
            }
        }

        if ($filter_customer !== '') {
            $sqlUid .= " AND c.full_name LIKE :fcust";
            $paramsUid['fcust'] = '%' . $filter_customer . '%';
        }

        $sqlUid .= " LIMIT 20";
        $sUid = $pdo->prepare($sqlUid);
        $sUid->execute($paramsUid);
        while ($r = $sUid->fetch(PDO::FETCH_ASSOC)) {
            if (!empty($r['ticket_uid'])) $out[] = ['type' => 'uid', 'value' => $r['ticket_uid']];
        }

        $sqlCust = "SELECT DISTINCT c.full_name
                    FROM customers c
                    JOIN tickets t ON t.customer_id = c.id
                    LEFT JOIN schedules s ON s.id = t.schedule_id
                    LEFT JOIN events e ON e.id = s.event_id
                    WHERE c.full_name LIKE :q_cust";
        $paramsCust = ['q_cust' => '%' . $q . '%'];

        if ($filter_session !== '') {
            if (is_numeric($filter_session)) {
                $sqlCust .= " AND t.schedule_id = :fsched_c";
                $paramsCust['fsched_c'] = (int)$filter_session;
            } else {
                $sqlCust .= " AND (e.title LIKE :fsession_c OR s.start_time LIKE :fsession2_c)";
                $paramsCust['fsession_c'] = '%' . $filter_session . '%';
                $paramsCust['fsession2_c'] = '%' . $filter_session . '%';
            }
        }

        if ($filter_uid !== '') {
            $sqlCust .= " AND t.ticket_uid LIKE :fuid_c";
            $paramsCust['fuid_c'] = '%' . $filter_uid . '%';
        }

        $sqlCust .= " LIMIT 20";
        $sCust = $pdo->prepare($sqlCust);
        $sCust->execute($paramsCust);
        while ($r = $sCust->fetch(PDO::FETCH_ASSOC)) {
            if (!empty($r['full_name'])) $out[] = ['type' => 'customer', 'value' => $r['full_name']];
        }

        $out = array_slice($out, 0, 20);
        json_resp(['success' => true, 'data' => $out]);
    } catch (Exception $e) {
        error_log('ajax/ticket.php suggest_q error: ' . $e->getMessage());
        json_resp(['success' => false, 'message' => 'Ошибка подсказок поиска']);
    }
}

// --- GET single ticket details for modal ---
if ($action === 'get_ticket') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if (!$id) json_resp(['success' => false, 'message' => 'Неверный id'], 400);
    try {
        $sql = "SELECT t.id, t.ticket_uid, t.seat_identifier, REPLACE(t.seat_identifier, ':', ' - ') AS seat_label,
                       t.seat_id, t.purchased_at, t.created_at, t.price, t.channel, t.payment_status, t.status, t.refund_status,
                   t.customer_segment, t.customer_id, t.schedule_id, t.payment_transaction_id,
                   t.payment_provider, t.payment_session_id,
                   tx.payload AS tx_payload, tx.payment_method AS tx_payment_method,
                   ps.order_number, ps.amount_cents AS payment_amount_cents, ps.provider_response,
                   ps.merch_rn_id,
                       COALESCE(c.full_name, '') AS customer_name,
                       COALESCE(e.title, '') AS event_title,
                       s.start_time AS session_start
                FROM tickets t
                LEFT JOIN customers c ON c.id = t.customer_id
                LEFT JOIN schedules s ON s.id = t.schedule_id
                LEFT JOIN events e ON e.id = s.event_id
                LEFT JOIN cash_transactions tx ON tx.id = t.payment_transaction_id
                LEFT JOIN payment_sessions ps ON ps.id = t.payment_session_id
                WHERE t.id = :id LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$r) json_resp(['success' => false, 'message' => 'Билет не найден'], 404);
        $providerResponse = [];
        if (!empty($r['provider_response'])) {
            $decodedProviderResponse = json_decode((string)$r['provider_response'], true);
            if (is_array($decodedProviderResponse)) {
                $providerResponse = $decodedProviderResponse;
            }
        }
        $isOnlineBcc = (int)($r['payment_session_id'] ?? 0) > 0
            && strtolower(trim((string)($r['payment_provider'] ?? ''))) === 'bcc';
        $bccConfig = $isOnlineBcc ? bcc_config($pdo) : [];
        $out = [
            'id' => (string)$r['id'],
            'ticket_uid' => $r['ticket_uid'],
            'seat_identifier' => $r['seat_identifier'],
            'seat_label' => $r['seat_label'],
            'seat' => $r['seat_label'] ?: $r['seat_identifier'],
            'price' => $r['price'],
            'channel' => $r['channel'],
            'payment_status' => $r['payment_status'],
            'payment_type' => map_payment_type_display($r['channel'] ?? '', $r['tx_payment_method'] ?? null),
            'status' => $r['status'],
            'refund_status' => $r['refund_status'] ?? 'none',
            'discount' => extract_discount_from_transaction_payload($r['tx_payload'] ?? null),
            'customer_name' => $r['customer_name'],
            'customer_id' => $r['customer_id'],
            'event_title' => $r['event_title'],
            'session_start' => $r['session_start'],
            'schedule_id' => $r['schedule_id'],
            'is_online_bcc' => $isOnlineBcc,
            'refund_mode' => $isOnlineBcc ? 'bcc' : 'local',
            'refund_order' => $isOnlineBcc ? ($r['order_number'] ?? '') : '',
            'refund_original_amount' => $isOnlineBcc
                ? number_format(((int)($r['payment_amount_cents'] ?? 0)) / 100, 2, '.', '')
                : number_format((float)($r['price'] ?? 0), 2, '.', ''),
            'refund_currency' => $isOnlineBcc ? '398' : 'KZT',
            'refund_rrn' => $isOnlineBcc ? ($providerResponse['RRN'] ?? '') : '',
            'refund_int_ref' => $isOnlineBcc ? ($providerResponse['INT_REF'] ?? '') : '',
            'refund_merch_rn_id' => $isOnlineBcc ? ($r['merch_rn_id'] ?? '') : '',
            'refund_terminal' => $isOnlineBcc ? ($bccConfig['terminal'] ?? '') : '',
        ];
        json_resp(['success' => true, 'data' => $out]);
    } catch (Exception $e) {
        error_log('ajax/ticket.php get_ticket error: ' . $e->getMessage());
        json_resp(['success' => false, 'message' => 'Ошибка получения билета']);
    }
}

// --- LIST action ---
if ($action === 'list') {
    $date_from = trim((string)($_POST['date_from'] ?? $_GET['date_from'] ?? ''));
    $date_to = trim((string)($_POST['date_to'] ?? $_GET['date_to'] ?? ''));
    $session_q = trim((string)($_POST['session'] ?? $_GET['session'] ?? ''));
    $uid = trim((string)($_POST['uid'] ?? $_GET['uid'] ?? ''));
    $customer = trim((string)($_POST['customer'] ?? $_GET['customer'] ?? ''));
    $status = trim((string)($_POST['status'] ?? $_GET['status'] ?? ''));
    $payment_status = trim((string)($_POST['payment_status'] ?? $_GET['payment_status'] ?? ''));
    $channel = trim((string)($_POST['channel'] ?? $_GET['channel'] ?? ''));
    $customer_segment = trim((string)($_POST['customer_segment'] ?? $_GET['customer_segment'] ?? ''));
    $refund = trim((string)($_POST['refund'] ?? $_GET['refund'] ?? ''));
    $page = max(1, safe_int($_POST['page'] ?? $_GET['page'] ?? 1, 1));
    $per_page = max(1, min(500, safe_int($_POST['per_page'] ?? $_GET['per_page'] ?? 25, 25)));
    $export = isset($_GET['export']) ? trim((string)$_GET['export']) : null;

    $offset = ($page - 1) * $per_page;

    $where = [];
    $params = [];

    if ($date_from) {
        $where[] = 't.purchased_at >= :date_from';
        $params['date_from'] = $date_from . ' 00:00:00';
    }
    if ($date_to) {
        $where[] = 't.purchased_at <= :date_to';
        $params['date_to'] = $date_to . ' 23:59:59';
    }
    if ($status) {
        $where[] = 't.status = :status';
        $params['status'] = $status;
    }
    if ($payment_status) {
        $where[] = 't.payment_status = :payment_status';
        $params['payment_status'] = $payment_status;
    }
    if ($channel) {
        $where[] = 't.channel = :channel';
        $params['channel'] = $channel;
    }
    if ($customer_segment) {
        $where[] = 't.customer_segment = :segment';
        $params['segment'] = $customer_segment;
    }

    if ($session_q !== '') {
        if (is_numeric($session_q)) {
            $where[] = 't.schedule_id = :schedid';
            $params['schedid'] = (int)$session_q;
        } else {
            $where[] = '(e.title LIKE :session_q OR s.start_time LIKE :session_q2)';
            $params['session_q'] = '%' . $session_q . '%';
            $params['session_q2'] = '%' . $session_q . '%';
        }
    }

    if ($uid !== '') {
        $where[] = 't.ticket_uid LIKE :uid';
        $params['uid'] = '%' . $uid . '%';
    }

    if ($customer !== '') {
        $where[] = 'c.full_name LIKE :customer';
        $params['customer'] = '%' . $customer . '%';
    }

    // For filtering by refund, use tickets.refund_status
    if ($refund === 'yes') {
        $where[] = "t.refund_status = 'refunded'";
    } elseif ($refund === 'no') {
        $where[] = "(t.refund_status IS NULL OR t.refund_status = 'none')";
    }

    $where_sql = '';
    if (!empty($where)) $where_sql = 'WHERE ' . implode(' AND ', $where);

    try {
        $countSql = "SELECT COUNT(*) FROM tickets t
                     LEFT JOIN schedules s ON s.id = t.schedule_id
                     LEFT JOIN events e ON e.id = s.event_id
                     LEFT JOIN customers c ON c.id = t.customer_id
                     $where_sql";
        $stmt = $pdo->prepare($countSql);
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        error_log('ajax/ticket.php count error: ' . $e->getMessage());
        json_resp(['success' => false, 'message' => 'Ошибка подсчёта']);
    }

    try {
        $sql = "SELECT
                    t.id, t.ticket_uid, t.seat_identifier, REPLACE(t.seat_identifier, ':', ' - ') AS seat_label,
                    t.seat_id, t.purchased_at, t.created_at, t.price, t.discount, t.channel, t.payment_status, t.status, t.refund_status,
                    t.customer_segment, t.payment_transaction_id,
                    tx.payload AS tx_payload, tx.payment_method AS tx_payment_method,
                    ps.order_number,
                    (SELECT COUNT(*) FROM tickets t2 WHERE t2.payment_transaction_id = t.payment_transaction_id) AS tx_ticket_count,
                    COALESCE(c.full_name, '') AS customer_name,
                    COALESCE(e.title, '') AS event_title,
                    s.start_time AS session_start
                FROM tickets t
                LEFT JOIN customers c ON c.id = t.customer_id
                LEFT JOIN schedules s ON s.id = t.schedule_id
                LEFT JOIN events e ON e.id = s.event_id
                LEFT JOIN cash_transactions tx ON tx.id = t.payment_transaction_id
                LEFT JOIN payment_sessions ps ON ps.id = t.payment_session_id
                $where_sql
                ORDER BY t.purchased_at DESC, t.id DESC
                LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($sql);

        $execParams = $params;
        $execParams['limit'] = (int)$per_page;
        $execParams['offset'] = (int)$offset;

        $stmt->execute($execParams);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('ajax/ticket.php fetch error: ' . $e->getMessage());
        json_resp(['success' => false, 'message' => 'Ошибка выборки']);
    }

    if ($export === 'csv') {
        try {
            $csvSql = "SELECT
                        t.id, t.ticket_uid, t.seat_identifier, REPLACE(t.seat_identifier, ':', ' - ') AS seat_label,
                        t.purchased_at, t.created_at, t.price, t.discount, t.channel, t.payment_status, t.status, t.refund_status,
                        t.customer_segment, t.payment_transaction_id,
                        tx.payload AS tx_payload, tx.payment_method AS tx_payment_method,
                        ps.order_number,
                        (SELECT COUNT(*) FROM tickets t2 WHERE t2.payment_transaction_id = t.payment_transaction_id) AS tx_ticket_count,
                        COALESCE(c.full_name, '') AS customer_name,
                        COALESCE(e.title, '') AS event_title,
                        s.start_time AS session_start
                    FROM tickets t
                    LEFT JOIN customers c ON c.id = t.customer_id
                    LEFT JOIN schedules s ON s.id = t.schedule_id
                    LEFT JOIN events e ON e.id = s.event_id
                    LEFT JOIN cash_transactions tx ON tx.id = t.payment_transaction_id
                    LEFT JOIN payment_sessions ps ON ps.id = t.payment_session_id
                    $where_sql
                    ORDER BY t.purchased_at DESC, t.id DESC";
            $csvStmt = $pdo->prepare($csvSql);
            $csvStmt->execute($params);
            $all = $csvStmt->fetchAll(PDO::FETCH_ASSOC);

            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="tickets_export.csv"');
            echo "\xEF\xBB\xBF";
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID','UID','Ряд - Место','Клиент','Событие','Сеанс','Тип','База, тг','Скидка, тг','Цена, тг','Канал','Форма оплаты','Номер заказа','Статус','Возврат','Покупка']);
            foreach ($all as $a) {
                $sessionLabel = ($a['event_title'] ?? '') . ($a['session_start'] ? ' / ' . $a['session_start'] : '');
                $seatOut = $a['seat_label'] ?: ($a['seat_identifier'] ?? '');
                $discountInfo = extract_discount_from_transaction_payload($a['tx_payload'] ?? null);
                $ticketDiscount = allocate_ticket_discount($a['price'] ?? 0, $discountInfo, $a['tx_ticket_count'] ?? 1);
                $ticketBasePrice = round(((float)($a['price'] ?? 0)));
                $paymentType = map_payment_type_display($a['channel'] ?? '', $a['tx_payment_method'] ?? null);
                fputcsv($out, [
                    $a['id'],
                    $a['ticket_uid'],
                    $seatOut,
                    $a['customer_name'],
                    $a['event_title'],
                    $a['session_start'],
                    $a['customer_segment'],
                    $ticketBasePrice,
                    $ticketDiscount,
                    $a['price'],
                    $a['channel'],
                    $paymentType,
                    $a['order_number'] ?? '',
                    $a['status'],
                    $a['refund_status'],
                    $a['purchased_at']
                ]);
            }
            fclose($out);
            exit;
        } catch (Exception $e) {
            error_log('ajax/ticket.php csv export error: ' . $e->getMessage());
            json_resp(['success' => false, 'message' => 'Ошибка экспорта CSV']);
        }
    }

    $outRows = [];
    foreach ($rows as $r) {
        // Prefer discount stored in tickets.discount (percent) when available
        $dbDiscountPercent = null;
        if (isset($r['discount']) && $r['discount'] !== null && $r['discount'] !== '') {
            if (is_numeric($r['discount'])) {
                $dbDiscountPercent = (int)$r['discount'];
            } else {
                // try to cast numeric-like strings
                $tmp = filter_var($r['discount'], FILTER_SANITIZE_NUMBER_INT);
                if ($tmp !== '') $dbDiscountPercent = (int)$tmp;
            }
        }

        $priceValue = is_numeric($r['price']) ? (float)$r['price'] : 0.0;

        // If DB discount percent is present and price is numeric, compute base and discount amount from percent
        if ($dbDiscountPercent !== null && $priceValue !== null) {
            $discount_percent = max(0, min(100, $dbDiscountPercent));
            if ($discount_percent >= 100) {
                // avoid division by zero; treat base as price (no meaningful base)
                $ticketDiscount = round((float)0, 2);
                $ticketBasePrice = round($priceValue, 2);
            } else {
                // base = price / (1 - p/100)
                $ticketBasePrice = round($priceValue / (1 - ($discount_percent / 100.0)), 2);
                $ticketDiscount = round($ticketBasePrice - $priceValue, 2);
            }
        } else {
            // Fallback: use transaction payload allocation as before
            $discountInfo = extract_discount_from_transaction_payload($r['tx_payload'] ?? null);
            $ticketDiscount = allocate_ticket_discount($r['price'] ?? 0, $discountInfo, $r['tx_ticket_count'] ?? 1);
            $ticketBasePrice = round(((float)($r['price'] ?? 0)) + $ticketDiscount, 2);
        }

        // Ensure numeric formatting for price
        $priceOut = is_numeric($r['price']) ? (float)$r['price'] : null;

        $outRows[] = [
            'id' => (string)$r['id'],
            'created_at' => $r['created_at'],
            'purchased_at' => $r['purchased_at'] ?? null,
            'event_title' => $r['event_title'] ?? null,
            'session_date' => $r['session_start'] ? date('d/m/Y H:i', strtotime($r['session_start'])) : null,
            'session_start_raw' => $r['session_start'] ?? null,
            'customer_name' => $r['customer_name'] ?? null,
            'customer_segment' => $r['customer_segment'] ?? null,
            'base_price' => $priceOut,
            'discount_amount' => $priceOut*($dbDiscountPercent/100),
            'price' => $priceOut - $priceOut*($dbDiscountPercent/100),
            'channel' => $r['channel'],
            'payment_type' => map_payment_type_display($r['channel'] ?? '', $r['tx_payment_method'] ?? null),
            'tx_payment_method' => $r['tx_payment_method'] ?? null,
            'order_number' => $r['order_number'] ?? null,
            'ticket_uid' => $r['ticket_uid'],
            'seat_identifier' => $r['seat_identifier'] ?? null,
            'seat_label' => isset($r['seat_label']) ? $r['seat_label'] : ($r['seat_identifier'] ? str_replace(':', ' - ', $r['seat_identifier']) : null),
            'payment_status' => $r['payment_status'],
            'status' => $r['status'],
            'refund_status' => $r['refund_status'] ?? 'none',
            'discount' => isset($dbDiscountPercent) && $dbDiscountPercent !== null ? $dbDiscountPercent : null
        ];
    }

    json_resp(['success' => true, 'data' => ['tickets' => $outRows, 'total' => $total, 'page' => $page, 'per_page' => $per_page]]);
}


// --- CREATE TICKET action (seat_occupancy-backed) ---
if ($action === 'create_ticket') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_resp(['success' => false, 'message' => 'Неверный метод'], 405);
    check_csrf();

    $schedule_id = isset($_POST['schedule_id']) ? intval($_POST['schedule_id']) : 0;
    $customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;
    $seat_identifier = trim((string)($_POST['seat_identifier'] ?? ''));
    $price = isset($_POST['price']) ? (float)$_POST['price'] : 0.0;
    $channel = trim((string)($_POST['channel'] ?? 'kassa'));
    $payment_status = trim((string)($_POST['payment_status'] ?? 'paid'));
    $customer_segment = trim((string)($_POST['customer_segment'] ?? ''));

    if (!$schedule_id || $seat_identifier === '') {
        json_resp(['success' => false, 'message' => 'Не указаны schedule_id или seat_identifier'], 400);
    }

    ticket_debug_log("create_ticket called schedule={$schedule_id} seat='{$seat_identifier}' customer={$customer_id} price={$price}");

    // Внутри create_ticket, сразу перед попыткой INSERT в seat_occupancy
ticket_debug_log("DEBUG: about to insert into seat_occupancy schedule={$schedule_id} seat='{$seat_identifier}' user=" . ($_SESSION['user_id'] ?? 'null'));

try {
    $insOcc = $pdo->prepare("INSERT INTO seat_occupancy (schedule_id, seat_identifier, ticket_id, reserved_at, created_at) VALUES (:sched, :seat, NULL, NOW(), NOW())");
    $insOcc->execute([':sched' => $schedule_id, ':seat' => $seat_identifier]);
    $occId = (int)$pdo->lastInsertId();
    ticket_debug_log("DEBUG: seat_occupancy inserted id={$occId} rowCount=" . $insOcc->rowCount());
} catch (PDOException $e) {
    $err = [
        'msg' => $e->getMessage(),
        'sqlstate' => $e->errorInfo[0] ?? null,
        'code' => $e->errorInfo[1] ?? null
    ];
    ticket_debug_log("DEBUG: seat_occupancy insert failed: " . json_encode($err, JSON_UNESCAPED_UNICODE));
    // Also dump current occupancy rows for this seat
    try {
        $dbg = $pdo->prepare("SELECT * FROM seat_occupancy WHERE schedule_id = :sched AND seat_identifier = :seat");
        $dbg->execute([':sched' => $schedule_id, ':seat' => $seat_identifier]);
        $rows = $dbg->fetchAll(PDO::FETCH_ASSOC);
        ticket_debug_log("DEBUG: existing occupancy rows: " . json_encode($rows, JSON_UNESCAPED_UNICODE));
    } catch (Exception $_e) {
        ticket_debug_log("DEBUG: failed to select seat_occupancy: " . $_e->getMessage());
    }
    // Re-throw or return a detailed error for diagnostics (temporary)
    $pdo->rollBack();
    json_resp(['success' => false, 'message' => 'Ошибка резервирования места (diagnostic)', 'db_error' => $err], 500);
}
    
    try {
        $pdo->beginTransaction();

        // 1) Try to reserve seat in seat_occupancy (atomic via unique constraint)
        $insOcc = $pdo->prepare("INSERT INTO seat_occupancy (schedule_id, seat_identifier, ticket_id, reserved_at, created_at) VALUES (:sched, :seat, NULL, NOW(), NOW())");
        try {
            $insOcc->execute([':sched' => $schedule_id, ':seat' => $seat_identifier]);
            $occId = (int)$pdo->lastInsertId();
            ticket_debug_log("seat_occupancy inserted id={$occId}");
        } catch (PDOException $e) {
            // Duplicate key -> seat already occupied
            $pdo->rollBack();
            $sqlState = $e->errorInfo[0] ?? null;
            $driverCode = isset($e->errorInfo[1]) ? (int)$e->errorInfo[1] : null;
            ticket_debug_log("seat_occupancy insert failed sqlstate={$sqlState} code={$driverCode} msg=" . $e->getMessage());
            if ($driverCode === 1062 || $sqlState === '23000') {
                json_resp(['success' => false, 'message' => 'Место уже занято'], 409);
            }
            error_log('ajax/ticket.php create_ticket seat_occupancy insert error: ' . $e->getMessage());
            json_resp(['success' => false, 'message' => 'Ошибка резервирования места'], 500);
        }

        // 2) Generate unique ticket_uid
        $ticket_uid = generate_ticket_uid(16);
        $tries = 0;
        while ($tries < 6) {
            $checkUid = $pdo->prepare("SELECT id FROM tickets WHERE ticket_uid = :uid LIMIT 1");
            $checkUid->execute([':uid' => $ticket_uid]);
            if (!$checkUid->fetch(PDO::FETCH_ASSOC)) break;
            $ticket_uid = generate_ticket_uid(16);
            $tries++;
        }

        // 3) Insert new ticket row (always INSERT)
        $ins = $pdo->prepare("INSERT INTO tickets
            (ticket_uid, schedule_id, customer_id, seat_identifier, price, channel, payment_status, status, refund_status, customer_segment, purchased_at, created_at, updated_at)
            VALUES
            (:ticket_uid, :schedule_id, :customer_id, :seat_identifier, :price, :channel, :payment_status, 'issued', 'none', :customer_segment, NOW(), NOW(), NOW())");
        $ins->execute([
            ':ticket_uid' => $ticket_uid,
            ':schedule_id' => $schedule_id,
            ':customer_id' => $customer_id ?: null,
            ':seat_identifier' => $seat_identifier,
            ':price' => $price,
            ':channel' => $channel,
            ':payment_status' => $payment_status,
            ':customer_segment' => $customer_segment
        ]);

        $newTicketId = (int)$pdo->lastInsertId();
        ticket_debug_log("ticket inserted id={$newTicketId} uid={$ticket_uid}");

        // 4) Link occupancy -> ticket_id
        $updOcc = $pdo->prepare("UPDATE seat_occupancy SET ticket_id = :tid WHERE id = :id");
        $updOcc->execute([':tid' => $newTicketId, ':id' => $occId]);

        if (function_exists('audit_log_event')) {
            audit_log_event($pdo, 'ticket.create', 'ticket', $newTicketId, $ticket_uid, [], [
                'schedule_id' => $schedule_id,
                'seat' => $seat_identifier,
            ]);
        }

        $pdo->commit();
        json_resp(['success' => true, 'ticket_id' => $newTicketId, 'ticket_uid' => $ticket_uid]);
    } catch (Exception $e) {
        try { $pdo->rollBack(); } catch (Exception $_) {}
        ticket_debug_log("create_ticket exception: " . $e->getMessage());
        error_log('ajax/ticket.php create_ticket error: ' . $e->getMessage());
        json_resp(['success' => false, 'message' => 'Ошибка создания билета'], 500);
    }
}

// --- REFUND action (update tickets.refund_status to refunded, remove occupancy) ---
if ($action === 'refund') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_resp(['success' => false, 'message' => 'Неверный метод'], 405);
    check_csrf();

    $ticket_id = isset($_POST['ticket_id']) ? intval($_POST['ticket_id']) : 0;
    if (!$ticket_id) json_resp(['success' => false, 'message' => 'Неверный ticket_id'], 400);

    $refund_amount_raw = trim((string)($_POST['refund_amount'] ?? ''));
    $refund_method = trim((string)($_POST['refund_method'] ?? 'cash'));
    $refund_provider = trim((string)($_POST['refund_provider'] ?? ''));
    $refund_transaction_id = trim((string)($_POST['refund_transaction_id'] ?? ''));
    $reason = trim((string)($_POST['reason'] ?? ''));

    // validate allowed refund methods
    $allowed_methods = ['cash','noncash','bank'];
    if (!in_array($refund_method, $allowed_methods, true)) {
        $refund_method = 'cash';
    }

    // validate amount (allow empty -> set to ticket price later)
    if ($refund_amount_raw !== '') {
        if (!is_numeric($refund_amount_raw)) json_resp(['success' => false, 'message' => 'Неверная сумма возврата'], 400);
        $refund_amount = number_format((float)$refund_amount_raw, 2, '.', '');
    } else {
        $refund_amount = null;
    }

    ticket_debug_log("refund called ticket_id={$ticket_id} amount={$refund_amount} method={$refund_method}");

    try {
        $pdo->beginTransaction();

        // lock ticket row and fetch info (include channel and customer_id for cash_transactions logic)
        $q = $pdo->prepare("SELECT id, ticket_uid, price, refund_status, status, schedule_id, seat_identifier, channel, customer_id, payment_provider, payment_session_id FROM tickets WHERE id = :id LIMIT 1 FOR UPDATE");
        $q->execute(['id' => $ticket_id]);
        $t = $q->fetch(PDO::FETCH_ASSOC);
        if (!$t) {
            $pdo->rollBack();
            json_resp(['success' => false, 'message' => 'Билет не найден'], 404);
        }
        if (isset($t['refund_status']) && $t['refund_status'] === 'refunded') {
            $pdo->rollBack();
            json_resp(['success' => false, 'message' => 'Билет уже возвращён'], 400);
        }

        $isOnlineBcc = (int)($t['payment_session_id'] ?? 0) > 0
            && strtolower(trim((string)($t['payment_provider'] ?? ''))) === 'bcc';
        if ($isOnlineBcc) {
            $pdo->rollBack();
            if (!function_exists('bcc_process_refund_request')) {
                error_log('[BCC REFUND] Не загружен обработчик cashier BCC refund.');
                json_resp(['success' => false, 'message' => 'Сервис возврата BCC не обновлён на сервере.'], 500);
            }
            try {
                $sessionStmt = $pdo->prepare("SELECT ps.*, s.start_time AS schedule_start
                    FROM payment_sessions ps
                    LEFT JOIN schedules s ON s.id = ps.session_id
                    WHERE ps.id = :id LIMIT 1");
                $sessionStmt->execute([':id' => (int)$t['payment_session_id']]);
                $paymentSession = $sessionStmt->fetch(PDO::FETCH_ASSOC);
                if (!$paymentSession) {
                    json_resp(['success' => false, 'message' => 'Платёжная сессия онлайн-заказа не найдена'], 404);
                }

                $ticketStmt = $pdo->prepare("SELECT id, ticket_uid, price, refund_status
                    FROM tickets WHERE payment_session_id = :payment_session_id ORDER BY id ASC");
                $ticketStmt->execute([':payment_session_id' => (int)$paymentSession['id']]);
                $orderTickets = $ticketStmt->fetchAll(PDO::FETCH_ASSOC);
                $originalResponse = json_decode((string)($paymentSession['provider_response'] ?? ''), true);
                if (!is_array($originalResponse)) {
                    $originalResponse = [];
                }

                $refundResult = bcc_process_refund_request(
                    $pdo,
                    $paymentSession,
                    $orderTickets,
                    $originalResponse,
                    'cashier_bcc_refund'
                );
                $bankResponse = is_array($refundResult['response'] ?? null) ? $refundResult['response'] : [];
                json_resp([
                    'success' => !empty($refundResult['success']),
                    'state' => $refundResult['state'] ?? 'rejected',
                    'message' => $refundResult['message'] ?? 'Возврат обработан.',
                    'bank_response' => array_intersect_key($bankResponse, array_flip(['ACTION', 'RC', 'RC_TEXT', 'ORDER', 'RRN', 'INT_REF'])),
                ], !empty($refundResult['success']) ? 200 : 400);
            } catch (Throwable $e) {
                error_log('[BCC REFUND] Ошибка возврата кассиром: ' . $e->getMessage());
                json_resp(['success' => false, 'message' => 'Не удалось выполнить возврат через BCC.'], 500);
            }
        }

        // determine amount if not provided
        if ($refund_amount === null) {
            $refund_amount = isset($t['price']) ? number_format((float)$t['price'], 2, '.', '') : '0.00';
        }

        // update tickets table: set refund_status to refunded, keep the ticket row
        $u = $pdo->prepare("UPDATE tickets SET refund_status = 'refunded', refund_at = NOW(), status = 'cancelled', updated_at = NOW() WHERE id = :id");
        $u->execute(['id' => $ticket_id]);

        // remove occupancy row (free seat)
        try {
            $delOcc = $pdo->prepare("DELETE FROM seat_occupancy WHERE ticket_id = :tid");
            $delOcc->execute([':tid' => $ticket_id]);
            ticket_debug_log("seat_occupancy deleted for ticket_id={$ticket_id}, affected=" . $delOcc->rowCount());
        } catch (Exception $e) {
            // log but do not rollback refund update
            ticket_debug_log("refund delete seat_occupancy error: " . $e->getMessage());
            error_log('ajax/ticket.php refund delete seat_occupancy error: ' . $e->getMessage());
        }

        $refundInsert = $pdo->prepare("INSERT INTO refunds
            (ticket_id, ticket_uid, schedule_id, refund_amount, refund_status, refund_method,
             refund_provider, refund_transaction_id, approved_at, reason, processed_by, created_at)
            VALUES (:ticket_id, :ticket_uid, :schedule_id, :refund_amount, 'refunded', :refund_method,
                :refund_provider, :refund_transaction_id, NOW(), :reason, :processed_by, NOW())");
        $refundInsert->execute([
            ':ticket_id' => $ticket_id,
            ':ticket_uid' => $t['ticket_uid'] ?? null,
            ':schedule_id' => (int)$t['schedule_id'],
            ':refund_amount' => $refund_amount,
            ':refund_method' => $refund_method,
            ':refund_provider' => $refund_provider !== '' ? $refund_provider : null,
            ':refund_transaction_id' => $refund_transaction_id !== '' ? $refund_transaction_id : null,
            ':reason' => $reason !== '' ? $reason : 'Возврат кассиром',
            ':processed_by' => !empty($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null,
        ]);

        // cash_transactions trace for refund
        try {
            if (column_exists($pdo, 'cash_transactions', 'type') && column_exists($pdo, 'cash_transactions', 'ticket_uids')) {
                $refundAmountCents = (int) round((float) $refund_amount * 100);
                $txPayload = json_encode([
                    'action' => 'refund',
                    'ticket_id' => $ticket_id,
                    'ticket_uid' => $t['ticket_uid'] ?? null,
                    'schedule_id' => $t['schedule_id'] ?? null,
                    'seat_identifier' => $t['seat_identifier'] ?? null,
                    'refund_amount' => $refund_amount,
                    'refund_method' => $refund_method,
                    'refund_provider' => $refund_provider,
                    'refund_transaction_id' => $refund_transaction_id,
                    'reason' => $reason
                ], JSON_UNESCAPED_UNICODE);

                // Per requirement: in cash_transactions, for non-kassa channels always record payment_method = 'card';
                // for kassa keep the provided refund_method (cash/noncash/bank) as payment_method.
                $paymentMethodToRecord = 'card';
                if (isset($t['channel']) && strtolower((string)$t['channel']) === 'kassa') {
                    // keep refund_method as payment_method for kassa
                    $paymentMethodToRecord = $refund_method;
                } else {
                    // for non-kassa, record 'card' per instruction
                    $paymentMethodToRecord = 'card';
                }

                $txStmt = $pdo->prepare("INSERT INTO cash_transactions (type, session_id, user_id, customer_id, amount_cents, currency, payment_method, payload, ticket_uids, created_at) VALUES (:type, :session_id, :user_id, :customer_id, :amount_cents, :currency, :payment_method, :payload, :ticket_uids, NOW())");
                $txStmt->execute([
                    ':type' => 'refund',
                    ':session_id' => $t['schedule_id'],
                    ':user_id' => $_SESSION['user_id'] ?? null,
                    ':customer_id' => $t['customer_id'] ?? null,
                    ':amount_cents' => $refundAmountCents,
                    ':currency' => 'KZT',
                    ':payment_method' => $paymentMethodToRecord,
                    ':payload' => $txPayload,
                    ':ticket_uids' => json_encode([$t['ticket_uid'] ?? null], JSON_UNESCAPED_UNICODE)
                ]);
            }
        } catch (Exception $e) {
            ticket_debug_log('cash_transactions refund insert error: ' . $e->getMessage());
            error_log('ajax/ticket.php cash_transactions refund insert error: ' . $e->getMessage());
        }

        if (function_exists('audit_log_event')) {
            audit_log_event($pdo, 'ticket.refund', 'ticket', $ticket_id, $t['ticket_uid'] ?? null, [], [
                'schedule_id' => $t['schedule_id'] ?? null,
                'refund_amount' => $refund_amount,
                'refund_method' => $refund_method,
                'reason' => $reason,
            ]);
        }

        $pdo->commit();
        json_resp(['success' => true, 'message' => 'Возврат выполнен, статус билета обновлён']);
    } catch (Exception $e) {
        try { $pdo->rollBack(); } catch (Exception $_) {}
        ticket_debug_log("refund exception: " . $e->getMessage());
        error_log('ajax/ticket.php refund error: ' . $e->getMessage());
        json_resp(['success' => false, 'message' => 'Ошибка при возврате'], 500);
    }
}

json_resp(['success' => false, 'message' => 'Неизвестное действие'], 400);
