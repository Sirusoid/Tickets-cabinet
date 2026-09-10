<?php
// ajax/cash.php
// Полный обработчик AJAX для кассовых операций: customer_search, session, sessions_list, hold, free_seats, sell и др.
// Ключи мест всегда в формате hyphen: "row-seat" (например "6-17").

require_once __DIR__ . '/../init.php';
require_login();

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// Не выводим ошибки в тело ответа (чтобы не ломать JSON)
@ini_set('display_errors', '0');
@ini_set('display_startup_errors', '0');
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

// --- Helpers ----------------------------------------------------------------

function json_response($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function current_user_id() {
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
}

function audit_log($pdo, $user_id, $action, $target_type = null, $target_id = null, $details = null) {
    try {
        if (!isset($pdo) || !($pdo instanceof PDO)) return;
        $stmt = $pdo->prepare("INSERT INTO cash_audit_log (user_id, action, target_type, target_id, details, created_at) VALUES (:user_id, :action, :target_type, :target_id, :details, NOW())");
        $stmt->execute([
            ':user_id' => $user_id,
            ':action' => $action,
            ':target_type' => $target_type,
            ':target_id' => $target_id,
            ':details' => $details
        ]);
    } catch (Exception $e) {
        error_log("Audit log error: " . $e->getMessage());
    }
}

function column_exists($pdo, $table, $column) {
    try {
        $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c");
        $st->execute([':t' => $table, ':c' => $column]);
        return (bool)$st->fetchColumn();
    } catch (Exception $e) {
        error_log("column_exists error: {$table}.{$column} - " . $e->getMessage());
        return false;
    }
}

/**
 * Всегда считаем места только по ключам в seat_map.seats
 * Возвращаем int или null
 */
function count_seats_from_seatmap_seatsonly($seatmap_json) {
    if (empty($seatmap_json)) return null;

    if (!is_string($seatmap_json)) {
        $data = $seatmap_json;
    } else {
        $data = json_decode($seatmap_json, true);
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }
    }
    if (!is_array($data)) return null;

    if (isset($data['seats']) && is_array($data['seats'])) {
        // keys expected in hyphen format; count unique hyphen keys
        $keys = array_keys($data['seats']);
        $normalized = [];
        foreach ($keys as $k) {
            if (!is_string($k)) continue;
            $hy = str_replace(':', '-', $k);
            $normalized[$hy] = true;
        }
        $cnt = count($normalized);
        return $cnt > 0 ? $cnt : null;
    }

    return null;
}

/**
 * Разбор price_ranges: возвращает ['min_price'=>float|null,'max_price'=>float|null,'total_seats'=>int|null]
 * Используется только как fallback, основной источник — seats.
 */
function analyze_price_ranges($json) {
    $res = ['min_price' => null, 'max_price' => null, 'total_seats' => null];
    if (empty($json)) return $res;
    $data = json_decode($json, true);
    if (!is_array($data)) return $res;

    $prices = [];
    $totalSeats = 0;
    $hasSeatCounts = false;

    $isAssoc = array_keys($data) !== range(0, count($data) - 1);

    if ($isAssoc) {
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                if (isset($v['price']) && is_numeric($v['price'])) $prices[] = (float)$v['price'];
                if (isset($v['count']) && is_numeric($v['count'])) { $totalSeats += (int)$v['count']; $hasSeatCounts = true; }
                if (isset($v['seats']) && is_array($v['seats'])) { $totalSeats += count($v['seats']); $hasSeatCounts = true; }
                if (isset($v['ranges']) && is_array($v['ranges'])) {
                    foreach ($v['ranges'] as $r) {
                        if (!is_array($r)) continue;
                        if (isset($r['from']) && isset($r['to']) && is_numeric($r['from']) && is_numeric($r['to'])) {
                            $from = (int)$r['from'];
                            $to = (int)$r['to'];
                            if ($to >= $from) { $totalSeats += ($to - $from + 1); $hasSeatCounts = true; }
                        }
                    }
                }
                if (isset($v['seat_map']) && !empty($v['seat_map'])) {
                    $cnt = count_seats_from_seatmap_seatsonly($v['seat_map']);
                    if (is_int($cnt)) { $totalSeats += $cnt; $hasSeatCounts = true; }
                }
            } elseif (is_numeric($v)) {
                $prices[] = (float)$v;
            } elseif (is_numeric($k)) {
                $prices[] = (float)$k;
            }
        }
    } else {
        foreach ($data as $g) {
            if (is_array($g)) {
                if (isset($g['price']) && is_numeric($g['price'])) $prices[] = (float)$g['price'];
                elseif (isset($g['value']) && is_numeric($g['value'])) $prices[] = (float)$g['value'];
                elseif (isset($g['p']) && is_numeric($g['p'])) $prices[] = (float)$g['p'];

                if (isset($g['count']) && is_numeric($g['count'])) { $totalSeats += (int)$g['count']; $hasSeatCounts = true; }
                if (isset($g['qty']) && is_numeric($g['qty'])) { $totalSeats += (int)$g['qty']; $hasSeatCounts = true; }
                if (isset($g['seats']) && is_array($g['seats'])) { $totalSeats += count($g['seats']); $hasSeatCounts = true; }
                if (isset($g['ranges']) && is_array($g['ranges'])) {
                    foreach ($g['ranges'] as $r) {
                        if (!is_array($r)) continue;
                        if (isset($r['from']) && isset($r['to']) && is_numeric($r['from']) && is_numeric($r['to'])) {
                            $from = (int)$r['from'];
                            $to = (int)$r['to'];
                            if ($to >= $from) { $totalSeats += ($to - $from + 1); $hasSeatCounts = true; }
                        }
                    }
                }
                if (isset($g['seat_map']) && !empty($g['seat_map'])) {
                    $cnt = count_seats_from_seatmap_seatsonly($g['seat_map']);
                    if (is_int($cnt)) { $totalSeats += $cnt; $hasSeatCounts = true; }
                }
            } elseif (is_numeric($g)) {
                $prices[] = (float)$g;
            }
        }
    }

    if (!empty($prices)) {
        $res['min_price'] = min($prices);
        $res['max_price'] = max($prices);
    }

    if ($hasSeatCounts && $totalSeats > 0) $res['total_seats'] = $totalSeats;

    return $res;
}

/**
 * Нормализация входных seats (используется в hold/sell)
 * Всегда возвращаем identifier в hyphen формате (замена ':' -> '-')
 */
function normalize_seats($seats_raw) {
    $result = [];
    if (is_string($seats_raw)) {
        $decoded = json_decode($seats_raw, true);
        if (is_array($decoded)) $seats_raw = $decoded;
    }
    if (!is_array($seats_raw)) return $result;

    foreach ($seats_raw as $s) {
        if (is_string($s)) {
            $s = trim($s);
            if ($s !== '') {
                // normalize colon -> hyphen
                $s = str_replace(':', '-', $s);
                $result[] = ['id' => null, 'identifier' => $s, 'price' => null];
            }
        } elseif (is_array($s)) {
            $seatId = null;
            if (isset($s['id']) && $s['id'] !== '') $seatId = (int)$s['id'];
            elseif (isset($s['seat_id']) && $s['seat_id'] !== '') $seatId = (int)$s['seat_id'];

            $identifier = null;
            if (isset($s['identifier']) && $s['identifier'] !== '') $identifier = (string)$s['identifier'];
            elseif (isset($s['seat_identifier']) && $s['seat_identifier'] !== '') $identifier = (string)$s['seat_identifier'];

            // normalize identifier to hyphen form
            if (is_string($identifier)) {
                $identifier = str_replace(':', '-', $identifier);
            }

            $price = null;
            if (isset($s['price']) && $s['price'] !== '') $price = (float)$s['price'];
            elseif (isset($s['final_price']) && $s['final_price'] !== '') $price = (float)$s['final_price'];
            elseif (isset($s['final_price_cents']) && $s['final_price_cents'] !== '') $price = ((int)$s['final_price_cents']) / 100.0;

            $segment = null;
            if (isset($s['customer_segment'])) $segment = $s['customer_segment'];
            elseif (isset($s['segment'])) $segment = $s['segment'];

            if ($identifier !== null || $seatId !== null) {
                $entry = ['id' => $seatId, 'identifier' => $identifier, 'price' => $price];
                if ($segment !== null) $entry['customer_segment'] = $segment;
                $result[] = $entry;
            }
        }
    }
    return $result;
}

// --- Input parsing ---------------------------------------------------------

$raw = file_get_contents('php://input');
$input = [];
if ($raw) {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) $input = $decoded;
}
if (!empty($_POST) && is_array($_POST)) {
    $input = array_merge($input, $_POST);
}

$action = isset($_GET['action']) ? $_GET['action'] : (isset($input['action']) ? $input['action'] : '');
if (!$action) json_response(['success' => false, 'message' => 'No action specified']);

$debug = (isset($_GET['debug']) && ((string)$_GET['debug'] === '1')) || (isset($input['debug']) && ((string)$input['debug'] === '1'));

// For unsafe actions require CSRF token
$unsafe_actions = ['hold', 'sell', 'reserve', 'update'];
if (in_array($action, $unsafe_actions, true)) {
    $token = null;
    if (isset($input['csrf_token'])) $token = $input['csrf_token'];
    elseif (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) $token = $_SERVER['HTTP_X_CSRF_TOKEN'];
    elseif (isset($_SERVER['HTTP_X_CSRFTOKEN'])) $token = $_SERVER['HTTP_X_CSRFTOKEN'];
    // safe compare
    if (empty($token) || !isset($_SESSION['csrf_token']) || !function_exists('hash_equals') ? ((string)$_SESSION['csrf_token'] !== (string)$token) : !hash_equals((string)$_SESSION['csrf_token'], (string)$token)) {
        json_response(['success' => false, 'message' => 'Invalid CSRF token']);
    }
}

// --- Main switch -----------------------------------------------------------

try {
    switch ($action) {
// ---------------- sell --------------------------------------------
case 'sell':
    // Read and normalize input (existing code)
    $session_id = isset($input['session_id']) ? (int)$input['session_id'] : 0;
    $seats_raw = isset($input['seats']) ? $input['seats'] : [];
    $payment_method = isset($input['payment_method']) ? trim((string)$input['payment_method']) : '';
    if ($payment_method === '' && isset($input['payment_type'])) {
        $payment_method = trim((string)$input['payment_type']);
    }
    if ($payment_method === '' && isset($input['method'])) {
        $payment_method = trim((string)$input['method']);
    }
    $payment_method = mb_strtolower($payment_method, 'UTF-8');
    $payment_method_map = ['cash' => 'cash', 'card' => 'card', 'terminal' => 'card', 'наличные' => 'cash', 'карта' => 'card', 'картой' => 'card'];
    if (isset($payment_method_map[$payment_method])) {
        $payment_method = $payment_method_map[$payment_method];
    }
    $amount_cents = isset($input['amount_cents']) ? (int)$input['amount_cents'] : 0;
    $user_id = current_user_id();

    // customer and customer_segment from input (customer may be JSON string or array)
    $customer_input = isset($input['customer']) ? $input['customer'] : [];
    if (is_string($customer_input)) {
        $decoded = json_decode($customer_input, true);
        if (is_array($decoded)) $customer_input = $decoded;
    }
    $customer_input = is_array($customer_input) ? $customer_input : [];

    $customer_phone = isset($customer_input['phone']) ? normalize_customer_phone((string)$customer_input['phone']) : '';
    $customer_full_name = isset($customer_input['full_name']) ? trim($customer_input['full_name']) : '';
    $customer_email = isset($customer_input['email']) ? trim($customer_input['email']) : null;
    $customer_gender = isset($customer_input['gender']) ? trim($customer_input['gender']) : null;
    $customer_city = isset($customer_input['city']) ? trim($customer_input['city']) : null;

    $customer_segment = isset($input['customer_segment']) ? trim($input['customer_segment']) : (isset($customer_input['customer_segment']) ? trim($customer_input['customer_segment']) : null);
    $allowed_segments = ['adult','child','student','senior','manual'];
    if (!in_array($customer_segment, $allowed_segments, true)) $customer_segment = 'adult';

    $discount_input = isset($input['discount']) ? $input['discount'] : [];
    if (is_string($discount_input)) {
        $decodedDiscount = json_decode($discount_input, true);
        if (is_array($decodedDiscount)) {
            $discount_input = $decodedDiscount;
        }
    }
    if (!is_array($discount_input)) {
        $discount_input = [];
    }

    // Additional flags for customer handling
    $provided_customer_id = isset($input['customer_id']) ? (int)$input['customer_id'] : null;
    $create_new_customer = isset($input['create_new_customer']) ? (bool)$input['create_new_customer'] : false;
    $customer_snapshot = isset($input['customer_snapshot']) ? $input['customer_snapshot'] : null;
    if (is_string($customer_snapshot)) {
        $tmp = json_decode($customer_snapshot, true);
        if (is_array($tmp)) $customer_snapshot = $tmp;
    }
    $update_customer = isset($input['update_customer']) ? (bool)$input['update_customer'] : false;

    // Normalize seats
    $seats = normalize_seats($seats_raw);

    if (!$session_id || !is_array($seats) || empty($seats)) {
        json_response(['success' => false, 'message' => 'session_id и seats обязательны']);
    }
    if (!$payment_method) {
        json_response(['success' => false, 'message' => 'Не выбран способ оплаты']);
    }
    if (!in_array($payment_method, ['cash', 'card'], true)) {
        json_response(['success' => false, 'message' => 'Некорректный способ оплаты']);
    }

    // phone required
    if ($customer_phone === '') {
        json_response(['success' => false, 'message' => 'Номер телефона клиента обязателен']);
    }

    $now = (new DateTime())->format('Y-m-d H:i:s');

    // Получаем данные сеанса (event_id, hall_id)
    $stmtSession = $pdo->prepare("SELECT id, event_id, hall_id FROM schedules WHERE id = :id LIMIT 1");
    $stmtSession->execute([':id' => $session_id]);
    $sessionRow = $stmtSession->fetch(PDO::FETCH_ASSOC);
    if (!$sessionRow) {
        json_response(['success' => false, 'message' => 'Сеанс не найден']);
    }
    $event_id = isset($sessionRow['event_id']) ? (int)$sessionRow['event_id'] : null;
    $hall_id = isset($sessionRow['hall_id']) ? (int)$sessionRow['hall_id'] : null;

    if (empty($event_id) || empty($hall_id)) {
        json_response(['success' => false, 'message' => 'Сеанс не содержит event_id или hall_id. Невозможно завершить продажу.']);
    }

    // Build base seat prices from seats[].original_price (we will store original_price into tickets.price)
    $baseSeatPrices = [];
    foreach ($seats as $seat) {
        $p = (isset($seat['original_price']) && is_numeric($seat['original_price'])) ? (float)$seat['original_price'] : (isset($seat['price']) && is_numeric($seat['price']) ? (float)$seat['price'] : 0.0);
        $baseSeatPrices[] = max(0.0, $p);
    }
    $baseTotal = array_sum($baseSeatPrices);

    // discount functions expected to exist in project; fallback if not
    if (!function_exists('cash_get_discount_settings')) {
        function cash_get_discount_settings($pdo) { return []; }
    }
    if (!function_exists('cash_apply_sale_discounts')) {
        function cash_apply_sale_discounts($baseTotal, $customer_segment, $discount_input, $discountSettings) {
            $segment = (string)$customer_segment;
            $base = max(0.0, (float)$baseTotal);
            $segmentPercent = 0.0;
            if ($segment !== 'manual' && isset($discountSettings['segment_percent'][$segment]) && is_numeric($discountSettings['segment_percent'][$segment])) {
                $segmentPercent = max(0.0, min(100.0, (float)$discountSettings['segment_percent'][$segment]));
            }
            $autoAmount = round($base * $segmentPercent / 100, 2);
            $afterAuto = max(0.0, $base - $autoAmount);

            $customType = isset($discount_input['custom_type']) ? (string)$discount_input['custom_type'] : 'none';
            $customValue = isset($discount_input['custom_value']) && is_numeric($discount_input['custom_value']) ? max(0.0, (float)$discount_input['custom_value']) : 0.0;
            if ($segment === 'manual' && isset($discount_input['manual_amount']) && is_numeric($discount_input['manual_amount'])) {
                $customType = 'fixed';
                $customValue = max(0.0, (float)$discount_input['manual_amount']);
            }

            $customAmount = 0.0;
            if ($customType === 'percent') {
                $maxPercent = isset($discountSettings['custom_max_percent']) && is_numeric($discountSettings['custom_max_percent']) ? max(0.0, (float)$discountSettings['custom_max_percent']) : 0.0;
                if ($maxPercent > 0) {
                    $customValue = min($customValue, $maxPercent);
                }
                $customValue = min($customValue, 100.0);
                $customAmount = round($afterAuto * $customValue / 100, 2);
            } elseif ($customType === 'fixed') {
                $maxAmount = isset($discountSettings['custom_max_amount']) && is_numeric($discountSettings['custom_max_amount']) ? max(0.0, (float)$discountSettings['custom_max_amount']) : 0.0;
                if ($maxAmount > 0) {
                    $customValue = min($customValue, $maxAmount);
                }
                $customAmount = min($afterAuto, round($customValue, 2));
            }

            $finalTotal = max(0.0, round($afterAuto - $customAmount, 2));
            return [
                'final_total' => $finalTotal,
                'applied' => [
                    'segment' => $segment,
                    'auto_percent' => $segmentPercent,
                    'auto_amount' => $autoAmount,
                    'custom_type' => $customType,
                    'custom_value' => $customValue,
                    'custom_amount' => $customAmount,
                    'total_discount' => max(0.0, round($base - $finalTotal, 2)),
                ]
            ];
        }
    }
    if (!function_exists('cash_distribute_prices')) {
        function cash_distribute_prices($baseSeatPrices, $final_total) {
            $sum = array_sum($baseSeatPrices);
            if ($sum <= 0) {
                $n = count($baseSeatPrices);
                return array_fill(0, $n, $n ? round($final_total / $n, 2) : 0.0);
            }
            $out = [];
            foreach ($baseSeatPrices as $p) {
                $out[] = round($final_total * ($p / $sum), 2);
            }
            return $out;
        }
    }

    $discountSettings = cash_get_discount_settings($pdo);
    $discountApplied = cash_apply_sale_discounts($baseTotal, $customer_segment, $discount_input, $discountSettings);
    $finalSeatPrices = cash_distribute_prices($baseSeatPrices, $discountApplied['final_total']);

    // If client provided final prices per-seat in input, prefer them (override server distribution)
    foreach ($seats as $idx => $sraw) {
        if (is_array($sraw)) {
            if (isset($sraw['final_price']) && $sraw['final_price'] !== '') {
                $finalSeatPrices[$idx] = (float)$sraw['final_price'];
            } elseif (isset($sraw['final_price_cents']) && $sraw['final_price_cents'] !== '') {
                $finalSeatPrices[$idx] = ((int)$sraw['final_price_cents']) / 100.0;
            }
        }
    }

    // amount_cents is final total in cents (integer)
    if ($amount_cents <= 0) {
        $amount_cents = (int)round($discountApplied['final_total'] * 100);
    }

    // --- формирование per-seat итогов (seats_final) ---
    $seats_final = [];
    foreach ($seats as $idx => $seat) {
        // identifier
        $identifier = null;
        if (is_array($seat)) {
            if (!empty($seat['identifier'])) $identifier = (string)$seat['identifier'];
            elseif (!empty($seat['seat_identifier'])) $identifier = (string)$seat['seat_identifier'];
        } elseif (is_string($seat)) {
            $identifier = $seat;
        }
        if ($identifier === null) $identifier = (string)$idx;
        $identifier = str_replace(':', '-', $identifier);

        // prices
        $orig = isset($baseSeatPrices[$idx]) ? (float)$baseSeatPrices[$idx] : 0.0;
        $final = isset($finalSeatPrices[$idx]) ? (float)$finalSeatPrices[$idx] : 0.0;

        // if client provided explicit final_price override it
        if (is_array($seat)) {
            if (isset($seat['final_price']) && $seat['final_price'] !== '') $final = (float)$seat['final_price'];
            elseif (isset($seat['final_price_cents']) && $seat['final_price_cents'] !== '') $final = ((int)$seat['final_price_cents']) / 100.0;
        }

        // discount amount in currency (orig - final)
        $discount_amount = round(max(0.0, $orig - $final), 2);
        $discount_cents = (int) round($discount_amount * 100);

        // discount percent (prefer explicit percent if provided)
        $discount_percent = 0;
        if (is_array($seat) && isset($seat['discount_percent']) && $seat['discount_percent'] !== '') {
            $discount_percent = (int)$seat['discount_percent'];
        } elseif ($orig > 0.000001) {
            $discount_percent = (int) round(($discount_amount / $orig) * 100);
        }
        if ($discount_percent < 0) $discount_percent = 0;
        if ($discount_percent > 100) $discount_percent = 100;

        // Ensure cents fields for payload
        $manualAmount = isset($discount_input['manual_amount']) && is_numeric($discount_input['manual_amount']) ? max(0.0, (float)$discount_input['manual_amount']) : 0.0;
        $seats_final[] = [
            'identifier' => $identifier,
            'original_price' => round($orig, 2),
			'final_price' => round($final, 2),
            'discount_amount' => round(max(0.0, $orig - $final), 2),
            'discount_percent' => $orig > 0.000001 ? (int)round((max(0.0, $orig - $final) / $orig) * 100) : 0,
			'custom_discount' => (int)round($manualAmount),
            'customer_segment' => isset($seat['customer_segment']) ? $seat['customer_segment'] : ($customer_segment ?? null),
        ];
    }

    $customer_segment_store = ($customer_segment === 'manual') ? 'adult' : $customer_segment;

    // helper: generate ticket uid (robust)
    $generate_ticket_uid = function($bytes = 8) {
        try {
            return bin2hex(random_bytes($bytes));
        } catch (Exception $e) {
            return uniqid('t_', true);
        }
    };

    // Build seat keys (strings) for SQL IN and for responses: prefer identifier, fallback to id
    $seat_keys = [];
    foreach ($seats as $s) {
        if (is_array($s) && !empty($s['identifier'])) $seat_keys[] = (string)$s['identifier'];
        elseif (is_array($s) && !empty($s['id'])) $seat_keys[] = (string)$s['id'];
        elseif (is_string($s)) $seat_keys[] = (string)$s;
        else $seat_keys[] = '';
    }
    // normalize to hyphen and unique
    $seat_keys = array_values(array_filter(array_unique(array_map(function($v){ return str_replace(':','-',$v); }, $seat_keys)), function($v){ return $v !== ''; }));
    if (empty($seat_keys)) {
        json_response(['success' => false, 'message' => 'Неверный формат seats']);
    }

    $pdo->beginTransaction();
    try {
        // 1) Проверка, что места не проданы (используем seat_identifier IN ...)
        $placeholders = implode(',', array_fill(0, count($seat_keys), '?'));
        $params = array_merge([$session_id], $seat_keys);

        $sold_conflicts = [];
        try {
            $sqlSold = "SELECT seat_identifier FROM tickets WHERE schedule_id = ? AND seat_identifier IN ($placeholders) AND status = 'issued' FOR UPDATE";
            $stmtSold = $pdo->prepare($sqlSold);
            $stmtSold->execute($params);
            $sold_conflicts = $stmtSold->fetchAll(PDO::FETCH_COLUMN, 0);
            // normalize
            $sold_conflicts = array_map(function($v){ return is_string($v) ? str_replace(':','-',$v) : $v; }, $sold_conflicts);
        } catch (Exception $e) {
            $sold_conflicts = [];
        }
        if (!empty($sold_conflicts)) {
            $pdo->rollBack();
            json_response(['success' => false, 'message' => 'Некоторые места уже проданы', 'sold' => $sold_conflicts]);
        }

        // 2) Проверка холдов
        $conflicting_holds = [];
        $sqlHolds = "SELECT seat_key, user_id, expires_at FROM cash_holds WHERE session_id = ? AND seat_key IN ($placeholders) FOR UPDATE";
        $stmtH = $pdo->prepare($sqlHolds);
        $stmtH->execute($params);
        while ($row = $stmtH->fetch(PDO::FETCH_ASSOC)) {
            $existingKey = is_string($row['seat_key']) ? str_replace(':','-',$row['seat_key']) : $row['seat_key'];
            if ($row['expires_at'] > $now && (int)$row['user_id'] !== $user_id) {
                $conflicting_holds[] = $existingKey;
            }
        }
        if (!empty($conflicting_holds)) {
            $pdo->rollBack();
            json_response(['success' => false, 'message' => 'Некоторые места удерживаются другим пользователем', 'conflicts' => $conflicting_holds]);
        }

        // 3) Создаём запись транзакции (session_id хранит schedule_id)
        $txStmt = $pdo->prepare("INSERT INTO cash_transactions (type, session_id, user_id, amount_cents, currency, payment_method, payload, created_at) VALUES (:type, :session_id, :user_id, :amount_cents, :currency, :payment_method, :payload, NOW())");
        $payload = json_encode([
            'action' => 'sale',
            'seats_final' => $seats_final,
            'customer' => $customer_input,
            'customer_segment' => $customer_segment,
            'manual_discount_amount' => $manualAmount,
            'final_total' => $discountApplied['final_total'],
            'total_discount' => $discountApplied['applied']['total_discount'] ?? null
        ], JSON_UNESCAPED_UNICODE);
        $currency = 'KZT';
        $txStmt->execute([
            ':type' => 'sale',
            ':session_id' => $session_id,
            ':user_id' => $user_id,
            ':amount_cents' => $amount_cents,
            ':currency' => $currency,
            ':payment_method' => $payment_method,
            ':payload' => $payload
        ]);
        $transaction_id = $pdo->lastInsertId();
		
		// --- TEMP DEBUG: log transaction payload that will be stored in cash_transactions.payload ---
			try {
				// raw JSON string (as stored)
				error_log('DEBUG cash_transactions.payload (raw JSON): ' . $payload);
				// decoded array for easier inspection in logs (optional)
				$tmpPayload = json_decode($payload, true);
				if (is_array($tmpPayload)) {
					error_log('DEBUG cash_transactions.payload (decoded): ' . json_encode($tmpPayload, JSON_UNESCAPED_UNICODE));
				}
			} catch (Throwable $e) {
				// ignore logging errors
			}
			// --- END TEMP DEBUG ---
		

        // --- find or create customer (by phone, fallback email) ---
        $customer_id = null;

        if ($create_new_customer && is_array($customer_snapshot)) {
            $c_full = $customer_snapshot['full_name'] ?? $customer_full_name;
            $c_gender = $customer_snapshot['gender'] ?? $customer_gender;
            $c_email = $customer_snapshot['email'] ?? $customer_email;
            $c_phone = $customer_snapshot['phone'] ?? $customer_phone;
            $c_city = $customer_snapshot['city'] ?? $customer_city;
            try { $custUid = bin2hex(random_bytes(8)); } catch (Exception $e) { $custUid = uniqid('c_', true); }
            $insCust = $pdo->prepare("INSERT INTO customers (customer_uid, full_name, gender, email, phone, city, created_at, updated_at) VALUES (:customer_uid, :full_name, :gender, :email, :phone, :city, NOW(), NOW())");
            $insCust->execute([
                ':customer_uid' => $custUid,
                ':full_name' => $c_full ?: 'Гость',
                ':gender' => $c_gender,
                ':email' => $c_email,
                ':phone' => $c_phone,
                ':city' => $c_city
            ]);
            $customer_id = (int)$pdo->lastInsertId();
            audit_log($pdo, $user_id, 'customer:create_from_sale', 'customer', $customer_id, json_encode(['via' => 'kassa', 'phone' => $c_phone], JSON_UNESCAPED_UNICODE));
        } else {
            if (!empty($provided_customer_id)) {
                $stmtFind = $pdo->prepare("SELECT id FROM customers WHERE id = :id LIMIT 1");
                $stmtFind->execute([':id' => $provided_customer_id]);
                $found = $stmtFind->fetch(PDO::FETCH_ASSOC);
                if ($found && isset($found['id'])) {
                    $customer_id = (int)$found['id'];
                    if ($update_customer) {
                        $updFields = [];
                        $updParams = [':id' => $customer_id];
                        if ($customer_full_name !== '') { $updFields[] = "full_name = :full_name"; $updParams[':full_name'] = $customer_full_name; }
                        if ($customer_email) { $updFields[] = "email = :email"; $updParams[':email'] = $customer_email; }
                        if ($customer_gender) { $updFields[] = "gender = :gender"; $updParams[':gender'] = $customer_gender; }
                        if ($customer_city) { $updFields[] = "city = :city"; $updParams[':city'] = $customer_city; }
                        if (!empty($updFields)) {
                            $sqlUpd = "UPDATE customers SET " . implode(', ', $updFields) . ", updated_at = NOW() WHERE id = :id";
                            $stmtU = $pdo->prepare($sqlUpd);
                            $stmtU->execute($updParams);
                        }
                    }
                } else {
                    $provided_customer_id = null;
                }
            }

            if (empty($customer_id)) {
                $stmtFind = $pdo->prepare("SELECT id FROM customers WHERE phone = :phone LIMIT 1");
                $stmtFind->execute([':phone' => $customer_phone]);
                $row = $stmtFind->fetch(PDO::FETCH_ASSOC);
                if ($row && isset($row['id'])) {
                    $customer_id = (int)$row['id'];
                    if ($update_customer) {
                        $updFields = [];
                        $updParams = [':id' => $customer_id];
                        if ($customer_full_name !== '') { $updFields[] = "full_name = :full_name"; $updParams[':full_name'] = $customer_full_name; }
                        if ($customer_email) { $updFields[] = "email = :email"; $updParams[':email'] = $customer_email; }
                        if ($customer_gender) { $updFields[] = "gender = :gender"; $updParams[':gender'] = $customer_gender; }
                        if ($customer_city) { $updFields[] = "city = :city"; $updParams[':city'] = $customer_city; }
                        if (!empty($updFields)) {
                            $sqlUpd = "UPDATE customers SET " . implode(', ', $updFields) . ", updated_at = NOW() WHERE id = :id";
                            $stmtU = $pdo->prepare($sqlUpd);
                            $stmtU->execute($updParams);
                        }
                    }
                } else {
                    if ($customer_email) {
                        $stmtFind2 = $pdo->prepare("SELECT id FROM customers WHERE email = :email LIMIT 1");
                        $stmtFind2->execute([':email' => $customer_email]);
                        $row2 = $stmtFind2->fetch(PDO::FETCH_ASSOC);
                        if ($row2 && isset($row2['id'])) {
                            $customer_id = (int)$row2['id'];
                            if ($update_customer) {
                                $stmtU2 = $pdo->prepare("UPDATE customers SET phone = :phone, updated_at = NOW() WHERE id = :id");
                                $stmtU2->execute([':phone' => $customer_phone, ':id' => $customer_id]);
                            }
                        }
                    }
                }
            }

            if (empty($customer_id)) {
                try { $custUid = bin2hex(random_bytes(8)); } catch (Exception $e) { $custUid = uniqid('c_', true); }
                $insCust = $pdo->prepare("INSERT INTO customers (customer_uid, full_name, gender, email, phone, city, created_at, updated_at) VALUES (:customer_uid, :full_name, :gender, :email, :phone, :city, NOW(), NOW())");
                $insCust->execute([
                    ':customer_uid' => $custUid,
                    ':full_name' => $customer_full_name ?: 'Гость',
                    ':gender' => $customer_gender,
                    ':email' => $customer_email,
                    ':phone' => $customer_phone,
                    ':city' => $customer_city
                ]);
                $customer_id = (int)$pdo->lastInsertId();
                audit_log($pdo, $user_id, 'customer:create_from_sale', 'customer', $customer_id, json_encode(['via' => 'kassa', 'phone' => $customer_phone], JSON_UNESCAPED_UNICODE));
            }
        }

        if (!empty($customer_id) && !empty($transaction_id)) {
            $txCustUpdate = $pdo->prepare("UPDATE cash_transactions SET customer_id = :customer_id WHERE id = :id");
            $txCustUpdate->execute([':customer_id' => $customer_id, ':id' => $transaction_id]);
        }

        // 4) Обновляем/вставляем билеты — генерируем ticket_uid и записываем channel='kassa' и customer_id, customer_segment
        $generated_ticket_uids = [];

        $chkUidStmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE ticket_uid = :uid");

        $ticket_has_customer_name = column_exists($pdo, 'tickets', 'customer_name');
        $ticket_has_customer_phone = column_exists($pdo, 'tickets', 'customer_phone');
        $ticket_has_customer_email = column_exists($pdo, 'tickets', 'customer_email');
        $ticket_has_customer_city = column_exists($pdo, 'tickets', 'customer_city');
        $ticket_has_customer_gender = column_exists($pdo, 'tickets', 'customer_gender');
        $ticket_has_discount = column_exists($pdo, 'tickets', 'discount') || column_exists($pdo, 'tickets', 'discount_cents');

        // Extend baseCols to include any existing extra columns (we will not add new DB columns)
        $baseCols = "schedule_id, event_id, hall_id, seat_identifier, seat_id, price, status, payment_status, payment_transaction_id, ticket_uid, channel, customer_id, customer_segment, purchased_at, created_at, updated_at";
        $baseVals = ":sid, :event_id, :hall_id, :sk, :seat_id, :price, 'issued', 'paid', :txid, :ticket_uid, 'kassa', :customer_id, :customer_segment, NOW(), NOW(), NOW()";

        $extraCols = [];
        $extraVals = [];
        if ($ticket_has_customer_name) { $extraCols[] = 'customer_name'; $extraVals[] = ':customer_name'; }
        if ($ticket_has_customer_phone) { $extraCols[] = 'customer_phone'; $extraVals[] = ':customer_phone'; }
        if ($ticket_has_customer_email) { $extraCols[] = 'customer_email'; $extraVals[] = ':customer_email'; }
        if ($ticket_has_customer_city) { $extraCols[] = 'customer_city'; $extraVals[] = ':customer_city'; }
        if ($ticket_has_customer_gender) { $extraCols[] = 'customer_gender'; $extraVals[] = ':customer_gender'; }
        if ($ticket_has_discount) { $extraCols[] = 'discount'; $extraVals[] = ':discount'; }

        if (!empty($extraCols)) {
            $baseCols .= ', ' . implode(', ', $extraCols);
            $baseVals .= ', ' . implode(', ', $extraVals);
        }

        $insSql = "INSERT INTO tickets ({$baseCols}) VALUES ({$baseVals})";
        $insStmt = $pdo->prepare($insSql);

        // Prepare transaction-level meta for PDF generation (map by uid later)
        $meta_base_total = null;
        $meta_final_total = null;
        $meta_auto_percent = null;

        if (isset($discount_input['base_total']) && is_numeric($discount_input['base_total'])) {
            $meta_base_total = (float)$discount_input['base_total'];
        } else {
            $meta_base_total = (float)round($baseTotal, 2);
        }

        if (isset($discount_input['final_total']) && is_numeric($discount_input['final_total'])) {
            $meta_final_total = (float)$discount_input['final_total'];
        } elseif ($amount_cents > 0) {
            $meta_final_total = (float)($amount_cents / 100.0);
        } else {
            $meta_final_total = isset($discountApplied['final_total']) ? (float)round($discountApplied['final_total'], 2) : (float)$meta_base_total;
        }

        if (isset($discount_input['auto_percent']) && is_numeric($discount_input['auto_percent'])) {
            $meta_auto_percent = (int)$discount_input['auto_percent'];
        } elseif (isset($discountApplied['applied']) && isset($discountApplied['applied']['auto_percent'])) {
            $meta_auto_percent = (int)$discountApplied['applied']['auto_percent'];
        } else {
            $meta_auto_percent = isset($discount_input['auto_percent']) ? (int)$discount_input['auto_percent'] : 0;
        }

        // Build ticket_meta_map for PDF generation (map by uid later)
        $ticket_meta_map = [];

        foreach ($seats as $idx => $seat) {
            // generate unique ticket_uid (with DB check)
            $ticket_uid = null;
            $maxAttempts = 6;
            for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
                $candidate = $generate_ticket_uid(8);
                if (empty($candidate)) continue;
                $chkUidStmt->execute([':uid' => $candidate]);
                $cnt = (int)$chkUidStmt->fetchColumn();
                if ($cnt === 0) { $ticket_uid = $candidate; break; }
            }
            if (empty($ticket_uid)) {
                $ticket_uid = uniqid('t_', true);
            }

            // seat fields
            $seatId = null;
            $seatIdentifier = null;
            $seatPrice = null;
            if (is_array($seat)) {
                if (!empty($seat['id'])) $seatId = (int)$seat['id'];
                if (!empty($seat['identifier'])) $seatIdentifier = (string)$seat['identifier'];
                if (isset($seat['price']) && $seat['price'] !== null && $seat['price'] !== '') $seatPrice = (float)$seat['price'];
            }
            if (is_string($seatIdentifier)) $seatIdentifier = str_replace(':','-',$seatIdentifier);

            // Determine values from seats_final (we built seats_final earlier)
            $sf = isset($seats_final[$idx]) ? $seats_final[$idx] : null;
            $orig_price = $sf ? (float)$sf['original_price'] : ($seatPrice !== null ? (float)$seatPrice : 0.0);
            $final_price = $sf ? (float)$sf['final_price'] : ($seatPrice !== null ? (float)$seatPrice : 0.0);

            // Store the amount actually paid for this ticket row.
            $ticket_price_to_store = number_format((float)$final_price, 2, '.', '');

            // Determine discount percent to store in tickets.discount: use transaction-level auto_percent if present
            $discount_percent_to_store = null;
            if (isset($discount_input['auto_percent']) && is_numeric($discount_input['auto_percent'])) {
                $discount_percent_to_store = (int)$discount_input['auto_percent'];
            } elseif (isset($discount['auto_percent']) && is_numeric($discount['auto_percent'])) {
                $discount_percent_to_store = (int)$discount['auto_percent'];
            } elseif ($meta_auto_percent !== null) {
                $discount_percent_to_store = (int)$meta_auto_percent;
            } else {
                // fallback: compute per-seat percent from seats_final if available
                if ($orig_price > 0.000001) {
                    $discount_amount_local = round(max(0.0, $orig_price - $final_price), 2);
                    $discount_percent_to_store = (int) round(($discount_amount_local / $orig_price) * 100);
                } else {
                    $discount_percent_to_store = 0;
                }
            }
            if ($discount_percent_to_store < 0) $discount_percent_to_store = 0;
            if ($discount_percent_to_store > 100) $discount_percent_to_store = 100;

            // auto_discount and custom_discount amounts per seat (if present in input)
            $auto_discount_amount = null;
            $custom_discount_amount = null;
            if (is_array($seat)) {
                if (isset($seat['auto_discount'])) $auto_discount_amount = (float)$seat['auto_discount'];
                elseif (isset($seat['auto_discount_cents'])) $auto_discount_amount = ((int)$seat['auto_discount_cents']) / 100.0;
                if (isset($seat['custom_discount'])) $custom_discount_amount = (float)$seat['custom_discount'];
                elseif (isset($seat['custom_discount_cents'])) $custom_discount_amount = ((int)$seat['custom_discount_cents']) / 100.0;
            }
            // ensure numeric
            $auto_discount_amount = $auto_discount_amount !== null ? round($auto_discount_amount, 2) : 0.0;
            $custom_discount_amount = $custom_discount_amount !== null ? round($custom_discount_amount, 2) : 0.0;
            $ticket_discount_amount = round(max(0.0, $orig_price - $final_price), 2);

            // Build pdf_fields for this ticket (PRICE = original_price, PAYMENT = final_price)
            $pdf_fields_arr = [
                'price_label' => round((float)$orig_price, 2),                      // ЦЕНА (original_price)
                'discount_label_percent' => $discount_percent_to_store,            // процент скидки (from discount.auto_percent)
                'discount_label_amount' => $ticket_discount_amount, // сумма скидки по билету
                'payment_label' => round((float)$final_price, 2),                  // ОПЛАТА (final_price)
                'payment_type' => (function_exists('map_payment_type_display') ? map_payment_type_display($payment_method ?? '', $payment_method ?? null) : ($payment_method ?: 'kassa')),
                // keep some legacy keys for compatibility
                'price_per_seat' => (float)number_format((float)$final_price, 2, '.', '')
            ];
            $pdf_fields_json = json_encode($pdf_fields_arr, JSON_UNESCAPED_UNICODE);

            // Bind values for insert
            $bind = [
                ':sid' => $session_id,
                ':event_id' => $event_id,
                ':hall_id' => $hall_id,
                ':sk' => $seatIdentifier,
                ':seat_id' => $seatId,
                // Per instruction: tickets.price = original_price
                ':price' => $ticket_price_to_store,
                ':txid' => $transaction_id,
                ':ticket_uid' => $ticket_uid,
                ':customer_id' => $customer_id,
                ':customer_segment' => $customer_segment_store
            ];

            if ($ticket_has_customer_name) $bind[':customer_name'] = is_array($customer_snapshot) && isset($customer_snapshot['full_name']) ? $customer_snapshot['full_name'] : $customer_full_name;
            if ($ticket_has_customer_phone) $bind[':customer_phone'] = is_array($customer_snapshot) && isset($customer_snapshot['phone']) ? $customer_snapshot['phone'] : $customer_phone;
            if ($ticket_has_customer_email) $bind[':customer_email'] = is_array($customer_snapshot) && isset($customer_snapshot['email']) ? $customer_snapshot['email'] : $customer_email;
            if ($ticket_has_customer_city) $bind[':customer_city'] = is_array($customer_snapshot) && isset($customer_snapshot['city']) ? $customer_snapshot['city'] : $customer_city;
            if ($ticket_has_customer_gender) $bind[':customer_gender'] = is_array($customer_snapshot) && isset($customer_snapshot['gender']) ? $customer_snapshot['gender'] : $customer_gender;
            if ($ticket_has_discount) {
                // store discount percent (from discount.auto_percent if present)
                $bind[':discount'] = $discount_percent_to_store;
            }

            $insStmt->execute($bind);

            $generated_ticket_uids[] = $ticket_uid;

            // prepare ticket_meta_map entry for this uid (for PDF generation)
            $ticket_meta_map[$ticket_uid] = [
                // per-ticket pdf fields (preferred)
                'pdf_price' => $pdf_fields_arr['price_label'],
                'pdf_discount_percent' => $pdf_fields_arr['discount_label_percent'],
                'pdf_discount_amount' => $pdf_fields_arr['discount_label_amount'],
                'pdf_payment' => $pdf_fields_arr['payment_label'],
                'pdf_payment_type' => $pdf_fields_arr['payment_type'],

                // legacy / fallback keys
                'price' => $ticket_price_to_store,
                'discount_percent' => $discount_percent_to_store,
                'seat_identifier' => $seatIdentifier,
                'price_per_seat' => (float)$pdf_fields_arr['price_per_seat']
            ];
        }

        // 5) Удаляем холды для этих мест (используем $seat_keys)
        if (!empty($seat_keys)) {
            $delSql = "DELETE FROM cash_holds WHERE session_id = ? AND seat_key IN ($placeholders)";
            $delStmt = $pdo->prepare($delSql);
            $delParams = array_merge([$session_id], $seat_keys);
            $delStmt->execute($delParams);
        }

        // 6) Записываем проданные места в seat_occupancy
        if (!empty($generated_ticket_uids)) {
            $existingOcc = [];
            $occStmt = $pdo->prepare("SELECT id, seat_identifier FROM seat_occupancy WHERE schedule_id = ? AND seat_identifier IN ($placeholders) FOR UPDATE");
            $occStmt->execute($params);
            while ($occRow = $occStmt->fetch(PDO::FETCH_ASSOC)) {
                $existingOcc[$occRow['seat_identifier']] = $occRow['id'];
            }

            $insOcc = $pdo->prepare("INSERT INTO seat_occupancy (schedule_id, seat_identifier, ticket_id, reserved_at, created_at) VALUES (:session_id, :seat_identifier, :ticket_id, NOW(), NOW())");
            $updOcc = $pdo->prepare("UPDATE seat_occupancy SET ticket_id = :ticket_id, reserved_at = NOW() WHERE id = :id");

            foreach ($generated_ticket_uids as $ticket_uid) {
                $stmt = $pdo->prepare("SELECT id, ticket_uid, seat_identifier FROM tickets WHERE ticket_uid = :uid LIMIT 1");
                $stmt->execute([':uid' => $ticket_uid]);
                $ticketRow = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$ticketRow || empty($ticketRow['seat_identifier'])) {
                    continue;
                }

                $ticketId = (int)$ticketRow['id'];
                $seatIdentifier = (string)$ticketRow['seat_identifier'];

                if (isset($existingOcc[$seatIdentifier])) {
                    $updOcc->execute([':ticket_id' => $ticketId, ':id' => $existingOcc[$seatIdentifier]]);
                } else {
                    $insOcc->execute([':session_id' => $session_id, ':seat_identifier' => $seatIdentifier, ':ticket_id' => $ticketId]);
                }
            }
        }

        // 7) Обновляем cash_transactions.ticket_uids (если поле присутствует)
        if (!empty($generated_ticket_uids)) {
            try {
                $txUpdate = $pdo->prepare("UPDATE cash_transactions SET ticket_uids = :uids WHERE id = :id");
                $txUpdate->execute([':uids' => json_encode($generated_ticket_uids, JSON_UNESCAPED_UNICODE), ':id' => $transaction_id]);
            } catch (Exception $e) {
                error_log("Sell warning: failed to update cash_transactions.ticket_uids: " . $e->getMessage());
            }
        }

        $pdo->commit();

        // Generate ticket PDFs immediately after successful sale (pass per-ticket meta)
        $pdf_generation_errors = [];
        if (!empty($generated_ticket_uids)) {
            foreach ($generated_ticket_uids as $ticket_uid) {
                $error = null;
                $meta = isset($ticket_meta_map[$ticket_uid]) ? $ticket_meta_map[$ticket_uid] : null;
                if (!function_exists('ticket_pdf_generate_by_ticket_uid') || !ticket_pdf_generate_by_ticket_uid($ticket_uid, false, $error, $meta)) {
                    $pdf_generation_errors[$ticket_uid] = $error ?: 'Не удалось сгенерировать PDF';
                    error_log('ajax/cash.php PDF generation failed for ticket_uid=' . $ticket_uid . ': ' . ($error ?: 'unknown error'));
                }
            }
        }

        // Build detailed tickets array for response (use $ticket_meta_map to enrich)
        $sold_tickets = [];
        if (!empty($generated_ticket_uids)) {
            $in = implode(',', array_fill(0, count($generated_ticket_uids), '?'));
            try {
                $stmtT = $pdo->prepare("SELECT id, ticket_uid, seat_identifier, price, discount, purchased_at FROM tickets WHERE ticket_uid IN ($in)");
                $stmtT->execute($generated_ticket_uids);
                while ($tr = $stmtT->fetch(PDO::FETCH_ASSOC)) {
                    $pdfPath = null;
                    $pdfUrl = null;
                    if (function_exists('ticket_pdf_file_path')) {
                        $pdfPath = ticket_pdf_file_path($tr['ticket_uid']);
                        if (is_file($pdfPath) && filesize($pdfPath) > 0 && function_exists('ticket_pdf_public_url')) {
                            $pdfUrl = ticket_pdf_public_url($tr['ticket_uid']);
                        }
                    }

                    // enrich from ticket_meta_map if available
                    $meta = isset($ticket_meta_map[$tr['ticket_uid']]) ? $ticket_meta_map[$tr['ticket_uid']] : null;
                    $discountPercent = null;
                    $discountAmount = null;
                    if ($meta) {
                        $discountPercent = isset($meta['pdf_discount_percent']) ? (int)$meta['pdf_discount_percent'] : (isset($meta['discount_percent']) ? (int)$meta['discount_percent'] : null);

                        if (isset($meta['pdf_discount_amount'])) {
                            $discountAmount = (float)$meta['pdf_discount_amount'];
                        } elseif (isset($meta['pdf_price']) && isset($meta['price_per_seat'])) {
                            $discountAmount = round((float)$meta['pdf_price'] - (float)$meta['price_per_seat'], 2);
                        } elseif (isset($meta['discount_amount'])) {
                            $discountAmount = (float)$meta['discount_amount'];
                        }
                    } else {
                        // fallback to DB discount (int) and compute amount from price and percent if possible
                        if (isset($tr['discount'])) $discountPercent = (int)$tr['discount'];
                        if ($discountPercent !== null && is_numeric($tr['price'])) {
                            $discountAmount = number_format(((float)$tr['price'] * $discountPercent) / (100 - $discountPercent), 2, '.', '');
                        }
                    }

                    $sold_tickets[] = [
                        'id' => isset($tr['id']) ? (int)$tr['id'] : null,
                        'ticket_uid' => $tr['ticket_uid'],
                        'seat_identifier' => $tr['seat_identifier'],
                        'price' => is_numeric($tr['price']) ? (float)$tr['price'] : null,
                        'discount' => $discountPercent !== null ? ((int)$discountPercent) . '%' : null,
                        'discount_percent' => $discountPercent,
                        'discount_amount' => $discountAmount !== null ? (float)$discountAmount : null,
                        'purchased_at' => isset($tr['purchased_at']) ? $tr['purchased_at'] : null,
                        'pdf_url' => $pdfUrl
                    ];
                }
            } catch (Exception $e) {
                error_log("Sell warning: failed to fetch created tickets: " . $e->getMessage());
                $sold_tickets = [];
            }
        }

        audit_log($pdo, $user_id, 'sale:create', 'session', $session_id, json_encode(['seats' => $seat_keys, 'tx_id' => $transaction_id, 'amount' => $amount_cents, 'uids' => $generated_ticket_uids, 'customer_id' => $customer_id, 'discount' => $discountApplied], JSON_UNESCAPED_UNICODE));

        $response = [
            'success' => true,
            'message' => 'Продано',
            'sold' => $seat_keys,
            'transaction_id' => $transaction_id,
            'ticket_uids' => $generated_ticket_uids,
            'tickets' => $sold_tickets,
            'customer_id' => $customer_id,
            'discount' => $discountApplied
        ];
		
// --- TEMP DEBUG: include transaction payload in API response when requested ---
// If client sent __debug_tx = true in input, include the payload object in response for inspection.
// Otherwise include only when server-side debug flag is enabled (optional).
if (isset($input['__debug_tx']) && $input['__debug_tx']) {
    // decode payload to array for JSON response
    $decodedPayload = json_decode($payload, true);
    $response['debug_tx_payload'] = is_array($decodedPayload) ? $decodedPayload : $payload;
    // also include raw JSON string if needed
    $response['debug_tx_payload_raw'] = $payload;
}
		
		
		
        if (!empty($pdf_generation_errors)) {
            $response['pdf_generation_errors'] = $pdf_generation_errors;
        }
        json_response($response);
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Sell debug: schedule_id=" . intval($session_id) . ", last_ticket_uid='" . (isset($ticket_uid) ? $ticket_uid : '') . "'");
        error_log("Sell error: " . $e->getMessage());
        if ($debug) json_response(['success' => false, 'message' => 'Ошибка при продаже', 'error' => $e->getMessage()]);
        json_response(['success' => false, 'message' => 'Ошибка при продаже']);
    }
    break;

        // ---------------- customer_search ---------------------------------
        case 'customer_search':
            // Accept either phone or generic query (name/email)
            $phoneRaw = isset($_GET['phone']) ? trim($_GET['phone']) : (isset($input['phone']) ? trim($input['phone']) : '');
            $queryRaw = isset($_GET['query']) ? trim($_GET['query']) : (isset($input['query']) ? trim($input['query']) : '');

            if ($phoneRaw !== '') {
                $norm = normalize_customer_phone($phoneRaw);
                $normDigits = customer_phone_digits($norm);
                if ($norm === '') json_response(['success' => true, 'data' => []]);
                try {
                    $stmt = $pdo->prepare("SELECT id, customer_uid, full_name, phone, email, city, gender, created_at FROM customers WHERE phone = :phone OR phone = :phone_digits LIMIT 10");
                    $stmt->execute([':phone' => $norm, ':phone_digits' => $normDigits]);
                    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    if (empty($rows)) {
                        $like = $norm . '%';
                        $likeDigits = $normDigits . '%';
                        $stmt2 = $pdo->prepare("SELECT id, customer_uid, full_name, phone, email, city, gender, created_at FROM customers WHERE phone LIKE :like OR phone LIKE :like_digits ORDER BY created_at DESC LIMIT 10");
                        $stmt2->execute([':like' => $like, ':like_digits' => $likeDigits]);
                        $rows = $stmt2->fetchAll(PDO::FETCH_ASSOC);
                    }
                } catch (Exception $e) {
                    error_log("customer_search error (phone): " . $e->getMessage());
                    if ($debug) json_response(['success' => false, 'message' => 'Search error', 'error' => $e->getMessage()]);
                    json_response(['success' => false, 'message' => 'Search error']);
                }
					} elseif ($queryRaw !== '') {
						// Generic text search (name / email)
						try {
							// build LIKE pattern from incoming query
							$q = '%' . $queryRaw . '%';

							$stmt = $pdo->prepare(
								"SELECT id, customer_uid, full_name, phone, email, city, gender, created_at
								 FROM customers
								 WHERE full_name LIKE :q1 OR email LIKE :q2
								 ORDER BY created_at DESC
								 LIMIT 20"
							);
							$stmt->execute([':q1' => $q, ':q2' => $q]);
							$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
						} catch (Exception $e) {
							error_log("customer_search error (query): " . $e->getMessage());
							if ($debug) json_response(['success' => false, 'message' => 'Search error', 'error' => $e->getMessage()]);
							json_response(['success' => false, 'message' => 'Search error']);
						}
					}else {
                json_response(['success' => true, 'data' => []]);
            }

            $out = [];
            foreach ($rows as $r) {
                $out[] = [
                    'id' => (int)$r['id'],
                    'customer_uid' => $r['customer_uid'] ?? null,
                    'full_name' => $r['full_name'] ?? '',
                    'phone' => $r['phone'] ?? '',
                    'email' => $r['email'] ?? '',
                    'city' => $r['city'] ?? '',
                    'gender' => $r['gender'] ?? '',
                    'created_at' => $r['created_at'] ?? null
                ];
            }
            json_response(['success' => true, 'data' => $out]);
            break;

        // ---------------- session ------------------------------------------
        case 'session':
            $session_id = isset($_GET['session_id']) ? (int)$_GET['session_id'] : (isset($input['session_id']) ? (int)$input['session_id'] : 0);
            if (!$session_id) json_response(['success' => false, 'message' => 'session_id required']);

            $stmt = $pdo->prepare("SELECT s.*, COALESCE(e.title, '') AS event_title, h.name AS hall_name, h.seat_map AS hall_seatmap FROM schedules s LEFT JOIN events e ON s.event_id = e.id LEFT JOIN halls h ON s.hall_id = h.id WHERE s.id = :id LIMIT 1");
            $stmt->execute([':id' => $session_id]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$session) {
                json_response(['success' => false, 'message' => 'Session not found']);
            }

            // seat_map JSON decode
            if (!empty($session['hall_seatmap']) && is_string($session['hall_seatmap'])) {
                $decoded = json_decode($session['hall_seatmap'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $session['hall_seatmap'] = $decoded;
                }
            }

            // sold seats (normalize to hyphen)
            $sold = [];
            try {
                $q = $pdo->prepare("SELECT seat_identifier FROM tickets WHERE schedule_id = :sid AND status = 'issued'");
                $q->execute([':sid' => $session_id]);
                $rows = $q->fetchAll(PDO::FETCH_COLUMN, 0);
                foreach ($rows as $r) {
                    if (!is_string($r)) continue;
                    $sold[] = str_replace(':', '-', $r);
                }
            } catch (Exception $e) {
                $sold = [];
            }

            // holds
            $held = [];
            try {
                $hst = $pdo->prepare("SELECT seat_key, user_id, expires_at, meta FROM cash_holds WHERE session_id = :sid AND expires_at > NOW()");
                $hst->execute([':sid' => $session_id]);
                while ($row = $hst->fetch(PDO::FETCH_ASSOC)) {
                    $key = is_string($row['seat_key']) ? str_replace(':', '-', $row['seat_key']) : $row['seat_key'];
                    $held[] = [
                        'seat_key' => $key,
                        'user_id' => isset($row['user_id']) ? (int)$row['user_id'] : null,
                        'expires_at' => $row['expires_at'],
                        'meta' => $row['meta'] ? json_decode($row['meta'], true) : null
                    ];
                }
            } catch (Exception $e) {
                $held = [];
            }

            // price ranges
            $priceRanges = null;
            try {
                if (!empty($session['price_ranges'])) {
                    $tmp = is_string($session['price_ranges']) ? json_decode($session['price_ranges'], true) : $session['price_ranges'];
                    if (is_array($tmp)) $priceRanges = $tmp;
                }
            } catch (Exception $e) { $priceRanges = null; }

            json_response(['success' => true, 'data' => ['session' => $session, 'sold_seats' => $sold, 'held_seats' => $held, 'price_ranges' => $priceRanges]]);
            break;

        // ---------------- sessions_list (POST-only, lightweight) -------------------------
        case 'sessions_list':
        case 'sessions':
        case 'list_sessions':
            try {
                $raw = file_get_contents('php://input');
                $body = [];
                if ($raw) {
                    $tmp = json_decode($raw, true);
                    if (is_array($tmp)) $body = $tmp;
                }
                if (!empty($_POST) && is_array($_POST)) $body = array_merge($body, $_POST);

                // helper: count seats from seat_map JSON (prefer explicit "seats" object keys)
                if (!function_exists('count_seats_from_seatmap')) {
                    function count_seats_from_seatmap($seatmap_json) {
                        if (empty($seatmap_json)) return null;
                        $data = json_decode($seatmap_json, true);
                        if (!is_array($data)) return null;

                        // If there is a top-level "seats" object where keys are seat identifiers, count keys
                        if (isset($data['seats']) && is_array($data['seats'])) {
                            return count($data['seats']);
                        }

                        // Some seat maps use nested rows/segments structure — fall back to previous logic
                        $count = 0;
                        if (isset($data['rows']) && is_array($data['rows'])) {
                            foreach ($data['rows'] as $row) {
                                if (isset($row['segments']) && is_array($row['segments'])) {
                                    foreach ($row['segments'] as $seg) {
                                        if (isset($seg['start']) && isset($seg['end']) && is_numeric($seg['start']) && is_numeric($seg['end'])) {
                                            $count += max(0, (int)$seg['end'] - (int)$seg['start'] + 1);
                                            continue;
                                        }
                                        if (isset($seg['seats']) && is_array($seg['seats'])) { $count += count($seg['seats']); continue; }
                                        if (isset($seg['seatCount']) && is_numeric($seg['seatCount'])) { $count += (int)$seg['seatCount']; continue; }
                                    }
                                }
                                if (isset($row['seats']) && is_array($row['seats'])) { $count += count($row['seats']); continue; }
                                if (isset($row['start']) && isset($row['end']) && is_numeric($row['start']) && is_numeric($row['end'])) {
                                    $count += max(0, (int)$row['end'] - (int)$row['start'] + 1);
                                    continue;
                                }
                                if (isset($row['seatCount']) && is_numeric($row['seatCount'])) { $count += (int)$row['seatCount']; continue; }
                            }
                            return $count > 0 ? $count : null;
                        }

                        // If data itself is an array of seats
                        if (array_values($data) === $data && is_array($data)) return count($data);

                        return null;
                    }
                }

                // filters & pagination
                $event_id = isset($body['event_id']) && $body['event_id'] !== '' ? (int)$body['event_id'] : null;
                $hall_id  = isset($body['hall_id'])  && $body['hall_id']  !== '' ? (int)$body['hall_id']  : null;
                $date     = isset($body['date'])     && $body['date']     !== '' ? $body['date'] : null;
                $qtext    = isset($body['q']) ? trim($body['q']) : (isset($body['query']) ? trim($body['query']) : null);

                $page = isset($body['page']) ? max(1, (int)$body['page']) : 1;
                $perPage = isset($body['per_page']) ? max(1, min(500, (int)$body['per_page'])) : 100;
                $offset = ($page - 1) * $perPage;

                $where = []; $params = [];
                if (!empty($event_id)) { $where[] = "s.event_id = :event_id"; $params[':event_id'] = $event_id; }
                if (!empty($hall_id))  { $where[] = "s.hall_id = :hall_id";   $params[':hall_id'] = $hall_id; }
                if (!empty($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) { $where[] = "DATE(s.start_time) = :date"; $params[':date'] = $date; }
                if (!empty($qtext)) { $where[] = "(e.title LIKE :q)"; $params[':q'] = '%' . $qtext . '%'; }

                if (function_exists('schedule_refresh_statuses')) schedule_refresh_statuses($pdo);
                if (column_exists($pdo, 'schedules', 'status')) $where[] = "(s.status = 'active' OR s.status = 'upcoming')";

                $whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

                // total count
                $countSql = "SELECT COUNT(*) FROM schedules s LEFT JOIN events e ON s.event_id = e.id $whereSql";
                $countStmt = $pdo->prepare($countSql);
                if (!empty($params)) $countStmt->execute($params); else $countStmt->execute();
                $total = (int)$countStmt->fetchColumn();

                // select fields
                $selectFields = [
                    "s.id","s.event_id","COALESCE(e.title,'') AS event_title","s.hall_id","s.start_time","s.end_time"
                ];
                if (column_exists($pdo, 'schedules', 'seat_map')) $selectFields[] = "COALESCE(s.seat_map, '') AS seat_map";
                if (column_exists($pdo, 'schedules', 'capacity')) $selectFields[] = "s.capacity";
                if (column_exists($pdo, 'schedules', 'price_ranges')) $selectFields[] = "s.price_ranges";
                if (column_exists($pdo, 'schedules', 'base_price')) $selectFields[] = "s.base_price";
                if (column_exists($pdo, 'schedules', 'status')) $selectFields[] = "s.status";
                $selectFields[] = "COALESCE(s.notes, '') AS notes";
                $selectFields[] = "COALESCE(h.name, '') AS hall_name";

                $selectSql = implode(', ', $selectFields);
                $order_dir = (isset($body['sort']) && $body['sort'] === 'date_asc') ? 'ASC' : 'DESC';

                $sql = "SELECT {$selectSql}
                        FROM schedules s
                        LEFT JOIN events e ON s.event_id = e.id
                        LEFT JOIN halls h ON s.hall_id = h.id
                        {$whereSql}
                        ORDER BY s.start_time {$order_dir}
                        LIMIT :limit OFFSET :offset";

                $stmt = $pdo->prepare($sql);
                if (!empty($params)) {
                    foreach ($params as $k => $v) {
                        if (is_int($v)) $stmt->bindValue($k, $v, PDO::PARAM_INT);
                        else $stmt->bindValue($k, $v, PDO::PARAM_STR);
                    }
                }
                $stmt->bindValue(':limit', (int)$perPage, PDO::PARAM_INT);
                $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
                $stmt->execute();
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // prepare ids
                $sessionIds = array_map('intval', array_column($rows, 'id'));
                $soldMap = []; $reservedTickets = []; $heldCounts = []; $overlapCounts = [];

                if (!empty($sessionIds)) {
                    $ph = implode(',', array_fill(0, count($sessionIds), '?'));

                    // sold
                    $q = $pdo->prepare("SELECT schedule_id AS sid, COUNT(*) AS sold_count FROM tickets WHERE schedule_id IN ($ph) AND status = 'issued' GROUP BY schedule_id");
                    $q->execute($sessionIds);
                    while ($r = $q->fetch(PDO::FETCH_ASSOC)) $soldMap[(int)$r['sid']] = (int)$r['sold_count'];

                    // check whether tickets.seat_key and cash_holds.seat_key exist
                    $ticketsHasSeatKey = column_exists($pdo, 'tickets', 'seat_key');
                    $holdsTableExists = false;
                    try {
                        $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cash_holds'");
                        $st->execute();
                        $holdsTableExists = (bool)$st->fetchColumn();
                    } catch (Exception $e) { $holdsTableExists = false; }

                    $holdsHasSeatKey = $holdsTableExists ? column_exists($pdo, 'cash_holds', 'seat_key') : false;

                    // tickets reserved: prefer distinct seat_key if available, else count rows
                    if ($ticketsHasSeatKey) {
                        try {
                            $q1 = $pdo->prepare("SELECT schedule_id AS sid, COUNT(DISTINCT NULLIF(seat_key,'')) AS reserved_count FROM tickets WHERE schedule_id IN ($ph) AND status IN ('reserved','held') GROUP BY schedule_id");
                            $q1->execute($sessionIds);
                            while ($r = $q1->fetch(PDO::FETCH_ASSOC)) $reservedTickets[(int)$r['sid']] = (int)$r['reserved_count'];
                        } catch (Exception $e) {
                            $q1b = $pdo->prepare("SELECT schedule_id AS sid, COUNT(*) AS reserved_count FROM tickets WHERE schedule_id IN ($ph) AND status IN ('reserved','held') GROUP BY schedule_id");
                            $q1b->execute($sessionIds);
                            while ($r = $q1b->fetch(PDO::FETCH_ASSOC)) $reservedTickets[(int)$r['sid']] = (int)$r['reserved_count'];
                        }
                    } else {
                        $q1b = $pdo->prepare("SELECT schedule_id AS sid, COUNT(*) AS reserved_count FROM tickets WHERE schedule_id IN ($ph) AND status IN ('reserved','held') GROUP BY schedule_id");
                        $q1b->execute($sessionIds);
                        while ($r = $q1b->fetch(PDO::FETCH_ASSOC)) $reservedTickets[(int)$r['sid']] = (int)$r['reserved_count'];
                    }

                    // holds counts if table exists
                    if ($holdsTableExists) {
                        try {
                            $q2 = $pdo->prepare("SELECT session_id AS sid, COUNT(DISTINCT " . ($holdsHasSeatKey ? "NULLIF(seat_key,'')" : "id") . ") AS held_count FROM cash_holds WHERE session_id IN ($ph) AND (expires_at IS NULL OR expires_at > NOW()) GROUP BY session_id");
                            $q2->execute($sessionIds);
                            while ($r = $q2->fetch(PDO::FETCH_ASSOC)) $heldCounts[(int)$r['sid']] = (int)$r['held_count'];
                        } catch (Exception $e) {
                            // leave heldCounts empty on error
                        }
                    }

                    // overlap: only if both sides have seat_key; otherwise overlap = 0
                    if ($ticketsHasSeatKey && $holdsTableExists && $holdsHasSeatKey) {
                        try {
                            $q3 = $pdo->prepare(
                                "SELECT t.schedule_id AS sid, COUNT(DISTINCT t.seat_key) AS overlap_count
                                 FROM tickets t
                                 JOIN cash_holds h ON h.session_id = t.schedule_id AND h.seat_key = t.seat_key
                                 WHERE t.schedule_id IN ($ph) AND t.status IN ('reserved','held') AND (h.expires_at IS NULL OR h.expires_at > NOW()) AND t.seat_key IS NOT NULL AND t.seat_key <> ''
                                 GROUP BY t.schedule_id"
                            );
                            $q3->execute($sessionIds);
                            while ($r = $q3->fetch(PDO::FETCH_ASSOC)) $overlapCounts[(int)$r['sid']] = (int)$r['overlap_count'];
                        } catch (Exception $e) {
                            // on error, leave overlapCounts empty
                        }
                    } else {
                        $overlapCounts = [];
                    }

                    // seats table preference (if you have seats table, but user requested seat_map seats as primary)
                    $seatsTableExists = false;
                    try {
                        $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'seats'");
                        $st->execute();
                        $seatsTableExists = (bool)$st->fetchColumn();
                    } catch (Exception $e) { $seatsTableExists = false; }

                    $seatsBySchedule = []; $seatsByHall = [];
                    if ($seatsTableExists) {
                        $hasSeatScheduleCol = column_exists($pdo, 'seats', 'schedule_id');
                        $hasSeatHallCol = column_exists($pdo, 'seats', 'hall_id');

                        if ($hasSeatScheduleCol) {
                            $qSeats = $pdo->prepare("SELECT schedule_id AS sid, COUNT(*) AS seat_count FROM seats WHERE schedule_id IN ($ph) GROUP BY schedule_id");
                            $qSeats->execute($sessionIds);
                            while ($r = $qSeats->fetch(PDO::FETCH_ASSOC)) $seatsBySchedule[(int)$r['sid']] = (int)$r['seat_count'];
                        } elseif ($hasSeatHallCol) {
                            $hallIds = [];
                            foreach ($rows as $rr) { $hid = isset($rr['hall_id']) ? (int)$rr['hall_id'] : 0; if ($hid) $hallIds[$hid] = $hid; }
                            if (!empty($hallIds)) {
                                $hph = implode(',', array_fill(0, count($hallIds), '?'));
                                $qSeatsH = $pdo->prepare("SELECT hall_id AS hid, COUNT(*) AS seat_count FROM seats WHERE hall_id IN ($hph) GROUP BY hall_id");
                                $qSeatsH->execute(array_values($hallIds));
                                while ($r = $qSeatsH->fetch(PDO::FETCH_ASSOC)) $seatsByHall[(int)$r['hid']] = (int)$r['seat_count'];
                            }
                        }
                    }
                }

                // build output
                $out = [];
                foreach ($rows as $s) {
                    $sid = (int)$s['id'];
                    $computed = function_exists('schedule_resolved_status') ? schedule_resolved_status($s) : ($s['status'] ?? 'upcoming');
                    if (!in_array($computed, ['upcoming','active'], true)) continue;
                    if (isset($body['status']) && $body['status'] !== '' && $computed !== $body['status']) continue;

                    $soldCount = isset($soldMap[$sid]) ? (int)$soldMap[$sid] : 0;
                    $ticketsReserved = isset($reservedTickets[$sid]) ? (int)$reservedTickets[$sid] : 0;
                    $holdsReserved = isset($heldCounts[$sid]) ? (int)$heldCounts[$sid] : 0;
                    $overlap = isset($overlapCounts[$sid]) ? (int)$overlapCounts[$sid] : 0;
                    $reservedTotal = max(0, $ticketsReserved + $holdsReserved - $overlap);

                    // total seats: prefer explicit seat_map "seats" keys -> seats table -> hall seats -> capacity -> seat_map fallback -> price_ranges last
                    $totalSeats = null;

                    // 1) seat_map explicit seats (preferred per your instruction)
                    if (!empty($s['seat_map'])) {
                        $ts = count_seats_from_seatmap($s['seat_map']);
                        if ($ts !== null) $totalSeats = (int)$ts;
                    }

                    // 2) seats table (if exists and seat_map didn't provide)
                    if ($totalSeats === null && !empty($seatsBySchedule[$sid])) {
                        $totalSeats = (int)$seatsBySchedule[$sid];
                    } elseif ($totalSeats === null && !empty($s['hall_id']) && !empty($seatsByHall[(int)$s['hall_id']])) {
                        $totalSeats = (int)$seatsByHall[(int)$s['hall_id']];
                    }

                    // 3) capacity
                    if ($totalSeats === null && !empty($s['capacity'])) {
                        $totalSeats = (int)$s['capacity'];
                    }

                    // 4) price_ranges as last resort
                    if ($totalSeats === null && !empty($s['price_ranges'])) {
                        $an = function_exists('analyze_price_ranges') ? analyze_price_ranges(is_string($s['price_ranges']) ? $s['price_ranges'] : json_encode($s['price_ranges'], JSON_UNESCAPED_UNICODE)) : ['total_seats' => null];
                        if (!empty($an['total_seats'])) $totalSeats = (int)$an['total_seats'];
                    }

                    $available = null;
                    if ($totalSeats !== null) $available = max(0, $totalSeats - $soldCount - $reservedTotal);

                    // price hints
                    $minPrice = null; $maxPrice = null;
                    if (isset($s['price_ranges']) && $s['price_ranges'] !== '') {
                        $an = function_exists('analyze_price_ranges') ? analyze_price_ranges(is_string($s['price_ranges']) ? $s['price_ranges'] : json_encode($s['price_ranges'], JSON_UNESCAPED_UNICODE)) : ['min_price'=>null,'max_price'=>null];
                        if ($an['min_price'] !== null) $minPrice = (int)$an['min_price'];
                        if ($an['max_price'] !== null) $maxPrice = (int)$an['max_price'];
                    }
                    if ($minPrice === null && $maxPrice === null && isset($s['base_price']) && $s['base_price'] !== null && $s['base_price'] !== '') {
                        $val = (int) round((float)$s['base_price']);
                        $minPrice = $maxPrice = $val;
                    }

                    $out[] = [
                        'id' => $sid,
                        'event_id' => (int)$s['event_id'],
                        'event_title' => $s['event_title'],
                        'hall_id' => (int)$s['hall_id'],
                        'hall_name' => $s['hall_name'],
                        'start_time' => $s['start_time'],
                        'end_time' => $s['end_time'],
                        'status' => $s['status'] ?? null,
                        '_computed_status' => $computed,
                        'notes' => $s['notes'] ?? '',
                        'min_price' => $minPrice,
                        'max_price' => $maxPrice,
                        'sold_count' => $soldCount,
                        'reserved_count' => $reservedTotal,
                        'total_seats' => $totalSeats,
                        'available' => $available,
                    ];
                }

                json_response(['success' => true, 'data' => $out, 'meta' => ['total'=>$total,'page'=>$page,'per_page'=>$perPage]]);
            } catch (Exception $e) {
                error_log("sessions_list error: " . $e->getMessage());
                json_response(['success' => false, 'message' => 'Internal server error']);
            }
            break;

        // ---------------- hold ----------------------------------------------
        case 'hold':
            $session_id = isset($input['session_id']) ? (int)$input['session_id'] : 0;
            $seats_raw = isset($input['seats']) ? $input['seats'] : [];
            $ttl_minutes = isset($input['ttl_minutes']) ? (int)$input['ttl_minutes'] : 10;
            $user_id = current_user_id();

            $seats_norm = normalize_seats($seats_raw);

            if (!$session_id || !is_array($seats_norm) || empty($seats_norm)) {
                json_response(['success' => false, 'message' => 'session_id и seats обязательны']);
            }

            $now = (new DateTime())->format('Y-m-d H:i:s');
            $expires_at = (new DateTime("+{$ttl_minutes} minutes"))->format('Y-m-d H:i:s');

            $seat_keys = [];
            foreach ($seats_norm as $s) {
                if (!empty($s['identifier'])) $seat_keys[] = (string)$s['identifier'];
                elseif (!empty($s['id'])) $seat_keys[] = (string)$s['id'];
                else $seat_keys[] = '';
            }
            // ensure hyphen-only keys and unique
            $seat_keys = array_values(array_filter(array_unique(array_map(function($v){ return str_replace(':','-',$v); }, $seat_keys)), function($v){ return $v !== ''; }));

            if (empty($seat_keys)) {
                json_response(['success' => false, 'message' => 'Неверный формат seats']);
            }

            $pdo->beginTransaction();
            try {
                $placeholders = implode(',', array_fill(0, count($seat_keys), '?'));
                $params = array_merge([$session_id], $seat_keys);

                // check active holds
                $sql = "SELECT seat_key, expires_at FROM cash_holds WHERE session_id = ? AND seat_key IN ($placeholders) FOR UPDATE";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $conflicts = [];
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $existingKey = is_string($row['seat_key']) ? str_replace(':', '-', $row['seat_key']) : $row['seat_key'];
                    if ($row['expires_at'] > $now) $conflicts[] = $existingKey;
                }
                if (!empty($conflicts)) {
                    $pdo->rollBack();
                    json_response(['success' => false, 'message' => 'Некоторые места уже зарезервированы', 'conflicts' => $conflicts]);
                }

                // check sold seats by seat_identifier
                $sold_conflicts = [];
                try {
                    $sql2 = "SELECT seat_identifier FROM tickets WHERE schedule_id = ? AND seat_identifier IN ($placeholders) AND status = 'issued' LIMIT 1";
                    $stmt2 = $pdo->prepare($sql2);
                    $stmt2->execute($params);
                    $sold_conflicts = $stmt2->fetchAll(PDO::FETCH_COLUMN, 0);
                    // normalize sold conflicts to hyphen
                    $sold_conflicts = array_map(function($v){ return is_string($v) ? str_replace(':','-',$v) : $v; }, $sold_conflicts);
                } catch (Exception $e) {
                    $sold_conflicts = [];
                }
                if (!empty($sold_conflicts)) {
                    $pdo->rollBack();
                    json_response(['success' => false, 'message' => 'Некоторые места уже проданы', 'sold' => $sold_conflicts]);
                }

                // insert holds (delete old then insert)
                $insertStmt = $pdo->prepare("INSERT INTO cash_holds (session_id, seat_key, user_id, created_at, expires_at, meta) VALUES (:session_id, :seat_key, :user_id, :created_at, :expires_at, :meta)");
                foreach ($seat_keys as $sk) {
                    $del = $pdo->prepare("DELETE FROM cash_holds WHERE session_id = :sid AND seat_key = :sk");
                    $del->execute([':sid' => $session_id, ':sk' => $sk]);

                    $insertStmt->execute([
                        ':session_id' => $session_id,
                        ':seat_key' => $sk,
                        ':user_id' => $user_id,
                        ':created_at' => $now,
                        ':expires_at' => $expires_at,
                        ':meta' => null
                    ]);
                }

                $pdo->commit();

                audit_log($pdo, $user_id, 'hold:create', 'session', $session_id, json_encode(['seats' => $seat_keys, 'ttl' => $ttl_minutes], JSON_UNESCAPED_UNICODE));

                json_response(['success' => true, 'message' => 'Резерв создан', 'held' => $seat_keys, 'expires_at' => $expires_at]);
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log("Hold error: " . $e->getMessage());
                if ($debug) json_response(['success' => false, 'message' => 'Ошибка при создании резерва', 'error' => $e->getMessage()]);
                json_response(['success' => false, 'message' => 'Ошибка при создании резерва']);
            }
            break;

        // ---------------- free_seats (POST-only, fallback) -------------------------
        case 'free_seats':
            try {
                $raw = file_get_contents('php://input');
                $body = [];
                if ($raw) {
                    $tmp = json_decode($raw, true);
                    if (is_array($tmp)) $body = $tmp;
                }
                if (!empty($_POST) && is_array($_POST)) $body = array_merge($body, $_POST);

                $ids = isset($body['ids']) && is_array($body['ids']) ? array_values(array_map('intval', $body['ids'])) : [];
                $resp = [];

                if (!empty($ids)) {
                    $ph = implode(',', array_fill(0, count($ids), '?'));

                    // 1) prefer seat_map "seats" keys -> seats table -> hall -> schedule fallbacks
                    $schedTotals = [];

                    // fetch schedules first (to get seat_map/hall_id/capacity/price_ranges)
                    try {
                        $qSched = $pdo->prepare("SELECT id, hall_id, capacity, seat_map, price_ranges FROM schedules WHERE id IN ($ph)");
                        $qSched->execute($ids);
                        $schedulesInfo = [];
                        $hallIds = [];
                        while ($r = $qSched->fetch(PDO::FETCH_ASSOC)) {
                            $sid = (int)$r['id'];
                            $schedulesInfo[$sid] = $r;
                            if (!empty($r['hall_id'])) $hallIds[(int)$r['hall_id']] = (int)$r['hall_id'];
                        }
                    } catch (Exception $e) {
                        $schedulesInfo = [];
                    }

                    // 1a) seat_map explicit seats
                    foreach ($schedulesInfo as $sid => $info) {
                        $total = null;
                        if (!empty($info['seat_map'])) {
                            // count_seats_from_seatmap prefers explicit "seats" keys
                            $ts = function_exists('count_seats_from_seatmap') ? count_seats_from_seatmap($info['seat_map']) : null;
                            if ($ts !== null) $total = (int)$ts;
                        }
                        if ($total !== null) $schedTotals[$sid] = $total;
                    }

                    // 1b) seats table (if exists) for schedules not covered by seat_map
                    $seatsTableExists = false;
                    try {
                        $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'seats'");
                        $st->execute();
                        $seatsTableExists = (bool)$st->fetchColumn();
                    } catch (Exception $e) { $seatsTableExists = false; }

                    if ($seatsTableExists) {
                        $hasSeatScheduleCol = column_exists($pdo, 'seats', 'schedule_id');
                        $hasSeatHallCol = column_exists($pdo, 'seats', 'hall_id');

                        if ($hasSeatScheduleCol) {
                            // fetch seat counts per schedule for missing schedules
                            $missing = [];
                            foreach ($ids as $id) if (!array_key_exists($id, $schedTotals)) $missing[] = $id;
                            if (!empty($missing)) {
                                $mph = implode(',', array_fill(0, count($missing), '?'));
                                try {
                                    $q = $pdo->prepare("SELECT schedule_id AS sid, COUNT(*) AS seat_count FROM seats WHERE schedule_id IN ($mph) GROUP BY schedule_id");
                                    $q->execute($missing);
                                    while ($r = $q->fetch(PDO::FETCH_ASSOC)) $schedTotals[(int)$r['sid']] = (int)$r['seat_count'];
                                } catch (Exception $e) { /* ignore */ }
                            }
                        } elseif ($hasSeatHallCol && !empty($hallIds)) {
                            // fetch seat counts per hall and apply to schedules missing totals
                            try {
                                $hph = implode(',', array_fill(0, count($hallIds), '?'));
                                $qSeatsH = $pdo->prepare("SELECT hall_id AS hid, COUNT(*) AS seat_count FROM seats WHERE hall_id IN ($hph) GROUP BY hall_id");
                                $qSeatsH->execute(array_values($hallIds));
                                $seatsByHall = [];
                                while ($r = $qSeatsH->fetch(PDO::FETCH_ASSOC)) $seatsByHall[(int)$r['hid']] = (int)$r['seat_count'];
                                foreach ($schedulesInfo as $sid => $info) {
                                    if (!array_key_exists($sid, $schedTotals)) {
                                        $hid = isset($info['hall_id']) ? (int)$info['hall_id'] : 0;
                                        if ($hid && isset($seatsByHall[$hid])) $schedTotals[$sid] = $seatsByHall[$hid];
                                    }
                                }
                            } catch (Exception $e) { /* ignore */ }
                        }
                    }

                    // 1c) schedule-level fallbacks for any remaining missing schedules: capacity -> seat_map fallback -> price_ranges
                    $missing = [];
                    foreach ($ids as $id) if (!array_key_exists($id, $schedTotals)) $missing[] = $id;
                    if (!empty($missing)) {
                        foreach ($missing as $sid) {
                            $info = isset($schedulesInfo[$sid]) ? $schedulesInfo[$sid] : null;
                            $total = null;
                            if ($info) {
                                if (!empty($info['capacity'])) $total = (int)$info['capacity'];
                                elseif (!empty($info['seat_map'])) {
                                    $ts = function_exists('count_seats_from_seatmap') ? count_seats_from_seatmap($info['seat_map']) : null;
                                    if ($ts !== null) $total = (int)$ts;
                                } elseif (!empty($info['price_ranges'])) {
                                    $an = function_exists('analyze_price_ranges') ? analyze_price_ranges(is_string($info['price_ranges']) ? $info['price_ranges'] : json_encode($info['price_ranges'], JSON_UNESCAPED_UNICODE)) : ['total_seats'=>null];
                                    if (!empty($an['total_seats'])) $total = (int)$an['total_seats'];
                                }
                            }
                            $schedTotals[$sid] = $total;
                        }
                    }

                    // sold, reserved, holds, overlap (same logic as sessions_list)
                    $sold = []; $rt = []; $holds = []; $overlap = [];

                    try {
                        $q2 = $pdo->prepare("SELECT schedule_id AS sid, COUNT(*) AS sold FROM tickets WHERE schedule_id IN ($ph) AND status = 'issued' GROUP BY schedule_id");
                        $q2->execute($ids);
                        while ($r = $q2->fetch(PDO::FETCH_ASSOC)) $sold[(int)$r['sid']] = (int)$r['sold'];
                    } catch (Exception $e) {}

                    $ticketsHasSeatKey = column_exists($pdo, 'tickets', 'seat_key');
                    if ($ticketsHasSeatKey) {
                        try {
                            $q3 = $pdo->prepare("SELECT schedule_id AS sid, COUNT(DISTINCT NULLIF(seat_key,'')) AS reserved FROM tickets WHERE schedule_id IN ($ph) AND status IN ('reserved','held') GROUP BY schedule_id");
                            $q3->execute($ids);
                            while ($r = $q3->fetch(PDO::FETCH_ASSOC)) $rt[(int)$r['sid']] = (int)$r['reserved'];
                        } catch (Exception $e) {
                            $q3b = $pdo->prepare("SELECT schedule_id AS sid, COUNT(*) AS reserved FROM tickets WHERE schedule_id IN ($ph) AND status IN ('reserved','held') GROUP BY schedule_id");
                            $q3b->execute($ids);
                            while ($r = $q3b->fetch(PDO::FETCH_ASSOC)) $rt[(int)$r['sid']] = (int)$r['reserved'];
                        }
                    } else {
                        $q3b = $pdo->prepare("SELECT schedule_id AS sid, COUNT(*) AS reserved FROM tickets WHERE schedule_id IN ($ph) AND status IN ('reserved','held') GROUP BY schedule_id");
                        $q3b->execute($ids);
                        while ($r = $q3b->fetch(PDO::FETCH_ASSOC)) $rt[(int)$r['sid']] = (int)$r['reserved'];
                    }

                    try {
                        $st = $pdo->prepare("SELECT session_id AS sid, COUNT(DISTINCT " . (column_exists($pdo,'cash_holds','seat_key') ? "NULLIF(seat_key,'')" : "id") . ") AS held FROM cash_holds WHERE session_id IN ($ph) AND (expires_at IS NULL OR expires_at > NOW()) GROUP BY session_id");
                        $st->execute($ids);
                        while ($r = $st->fetch(PDO::FETCH_ASSOC)) $holds[(int)$r['sid']] = (int)$r['held'];
                    } catch (Exception $e) {}

                    if ($ticketsHasSeatKey && column_exists($pdo,'cash_holds','seat_key')) {
                        try {
                            $st2 = $pdo->prepare(
                                "SELECT t.schedule_id AS sid, COUNT(DISTINCT t.seat_key) AS overlap
                                 FROM tickets t
                                 JOIN cash_holds h ON h.session_id = t.schedule_id AND h.seat_key = t.seat_key
                                 WHERE t.schedule_id IN ($ph) AND t.status IN ('reserved','held') AND (h.expires_at IS NULL OR h.expires_at > NOW()) AND t.seat_key IS NOT NULL AND t.seat_key <> ''
                                 GROUP BY t.schedule_id"
                            );
                            $st2->execute($ids);
                            while ($r = $st2->fetch(PDO::FETCH_ASSOC)) $overlap[(int)$r['sid']] = (int)$r['overlap'];
                        } catch (Exception $e) {}
                    } else {
                        $overlap = [];
                    }

                    // assemble response
                    foreach ($ids as $sid) {
                        $sid = (int)$sid;
                        $total = array_key_exists($sid, $schedTotals) ? $schedTotals[$sid] : null;
                        $soldCount = isset($sold[$sid]) ? $sold[$sid] : 0;
                        $ticketsReserved = isset($rt[$sid]) ? $rt[$sid] : 0;
                        $holdsReserved = isset($holds[$sid]) ? $holds[$sid] : 0;
                        $ov = isset($overlap[$sid]) ? $overlap[$sid] : 0;
                        $reservedTotal = max(0, $ticketsReserved + $holdsReserved - $ov);

                        $free = null;
                        if ($total !== null) $free = max(0, $total - $soldCount - $reservedTotal);

                        $resp[$sid] = [
                            'total' => $total,
                            'free' => $free,
                            'available' => $free,
                            'sold_count' => $soldCount,
                            'reserved_count' => $reservedTotal
                        ];
                    }
                }

                json_response(['success' => true, 'data' => $resp]);
            } catch (Exception $e) {
                error_log("free_seats error: " . $e->getMessage());
                json_response(['success' => false, 'message' => 'Internal server error']);
            }
            break;

        // ---------------- release_reservation -------------------------
        case 'release_reservation':
            try {
                // read JSON body + merge with $_POST
                $raw = file_get_contents('php://input');
                $body = [];
                if ($raw) {
                    $tmp = json_decode($raw, true);
                    if (is_array($tmp)) $body = $tmp;
                }
                if (!empty($_POST) && is_array($_POST)) $body = array_merge($body, $_POST);

                // Accept either schedule_id OR session_id (session_id used by hold)
                $schedule_id = isset($body['schedule_id']) ? (int)$body['schedule_id'] : (isset($body['session_id']) ? (int)$body['session_id'] : 0);
                $csrf_in = isset($body['csrf_token']) ? (string)$body['csrf_token'] : null;

                // Determine seats input: single seat_key OR seats array (like hold)
                $seat_keys = [];

                if (isset($body['seat_key']) && $body['seat_key'] !== '') {
                    // single key provided
                    $seat_keys[] = (string)trim($body['seat_key']);
                } elseif (isset($body['seats']) && is_array($body['seats']) && !empty($body['seats'])) {
                    // seats array provided (as in hold) - normalize using server helper
                    $seats_norm = normalize_seats($body['seats']);
                    if (is_array($seats_norm) && !empty($seats_norm)) {
                        foreach ($seats_norm as $s) {
                            if (is_array($s)) {
                                if (!empty($s['identifier'])) $seat_keys[] = (string)$s['identifier'];
                                elseif (!empty($s['id'])) $seat_keys[] = (string)$s['id'];
                            } elseif (is_string($s) && $s !== '') {
                                $seat_keys[] = (string)$s;
                            }
                        }
                    }
                }

                // Fallback: maybe client sent seat_keys[] as form data
                if (empty($seat_keys) && isset($body['seat_keys']) && is_array($body['seat_keys'])) {
                    foreach ($body['seat_keys'] as $sk) {
                        if (is_string($sk) && $sk !== '') $seat_keys[] = $sk;
                    }
                }

                // Normalize keys to hyphen-only and unique
                $seat_keys = array_values(array_filter(array_unique(array_map(function($v){ return is_string($v) ? str_replace(':','-', trim($v)) : ''; }, $seat_keys)), function($v){ return $v !== ''; }));

                if (!$schedule_id || empty($seat_keys)) {
                    json_response(['success' => false, 'message' => 'Неверные параметры']);
                }

                // CSRF basic check (if you use a different mechanism, adapt accordingly)
                if (isset($_SESSION['csrf_token']) && $_SESSION['csrf_token'] !== '' && $csrf_in !== $_SESSION['csrf_token']) {
                    json_response(['success' => false, 'message' => 'CSRF token mismatch']);
                }

                // determine column names available in DB
                $ticketsHasSeatKey = column_exists($pdo, 'tickets', 'seat_key');
                $ticketsHasSeatIdentifier = column_exists($pdo, 'tickets', 'seat_identifier') && !$ticketsHasSeatKey;
                $holdsTableExists = false;
                try {
                    $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cash_holds'");
                    $st->execute();
                    $holdsTableExists = (bool)$st->fetchColumn();
                } catch (Exception $e) {
                    $holdsTableExists = false;
                }
                $holdsHasSeatKey = $holdsTableExists ? column_exists($pdo, 'cash_holds', 'seat_key') : false;
                $holdsHasId = $holdsTableExists ? column_exists($pdo, 'cash_holds', 'id') : false;

                // perform DB changes in transaction
                $pdo->beginTransaction();
                $affected = 0;

                // If multiple seat keys - prepare placeholders
                $placeholders = implode(',', array_fill(0, count($seat_keys), '?'));

                // 1) delete from cash_holds if table and column exist
                if ($holdsTableExists) {
                    try {
                        if ($holdsHasSeatKey) {
                            $sql = "DELETE FROM cash_holds WHERE session_id = ? AND seat_key IN ($placeholders)";
                            $params = array_merge([$schedule_id], $seat_keys);
                            $del = $pdo->prepare($sql);
                            $del->execute($params);
                            $affected += $del->rowCount();
                        } else {
                            // if cash_holds has seat_identifier column
                            if (column_exists($pdo, 'cash_holds', 'seat_identifier')) {
                                $sql = "DELETE FROM cash_holds WHERE session_id = ? AND seat_identifier IN ($placeholders)";
                                $params = array_merge([$schedule_id], $seat_keys);
                                $del = $pdo->prepare($sql);
                                $del->execute($params);
                                $affected += $del->rowCount();
                            } else {
                                // fallback: try deleting by id if seat keys are ids (unlikely)
                                // nothing to do here
                            }
                        }
                    } catch (Exception $e) {
                        error_log("release_reservation: cash_holds delete error: " . $e->getMessage());
                    }
                }

                // 2) update tickets: reserved/held -> available (or other desired status)
                if ($ticketsHasSeatKey) {
                    try {
                        $sql = "UPDATE tickets SET status = 'available' WHERE schedule_id = :sid AND seat_key IN ($placeholders) AND status IN ('reserved','held')";
                        $namedPlaceholders = [];
                        $i = 0;
                        foreach ($seat_keys as $sk) { $namedPlaceholders[':p'.$i] = $sk; $i++; }
                        $stmtSql = str_replace($placeholders, implode(',', array_keys($namedPlaceholders)), $sql);
                        $upd = $pdo->prepare($stmtSql);
                        $execParams = array_merge([':sid' => $schedule_id], $namedPlaceholders);
                        $upd->execute($execParams);
                        $affected += $upd->rowCount();
                    } catch (Exception $e) {
                        error_log("release_reservation: tickets update (seat_key) error: " . $e->getMessage());
                    }
                } elseif ($ticketsHasSeatIdentifier) {
                    try {
                        $sql = "UPDATE tickets SET status = 'available' WHERE schedule_id = :sid AND seat_identifier IN ($placeholders) AND status IN ('reserved','held')";
                        $namedPlaceholders = [];
                        $i = 0;
                        foreach ($seat_keys as $sk) { $namedPlaceholders[':p'.$i] = $sk; $i++; }
                        $stmtSql = str_replace($placeholders, implode(',', array_keys($namedPlaceholders)), $sql);
                        $upd = $pdo->prepare($stmtSql);
                        $execParams = array_merge([':sid' => $schedule_id], $namedPlaceholders);
                        $upd->execute($execParams);
                        $affected += $upd->rowCount();
                    } catch (Exception $e) {
                        error_log("release_reservation: tickets update (seat_identifier) error: " . $e->getMessage());
                    }
                } else {
                    // no seat column in tickets — nothing to update
                }

                $pdo->commit();

                // After changes, recalc counts for this schedule (safe queries)
                $sold = 0;
                try {
                    $q = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE schedule_id = :sid AND status = 'issued'");
                    $q->execute([':sid' => $schedule_id]);
                    $sold = (int)$q->fetchColumn();
                } catch (Exception $e) { $sold = 0; }

                // reserved tickets count (prefer distinct seat key if available)
                $ticketsReserved = 0;
                try {
                    if ($ticketsHasSeatKey) {
                        $q = $pdo->prepare("SELECT COUNT(DISTINCT NULLIF(seat_key,'')) FROM tickets WHERE schedule_id = :sid AND status IN ('reserved','held')");
                        $q->execute([':sid' => $schedule_id]);
                        $ticketsReserved = (int)$q->fetchColumn();
                    } elseif ($ticketsHasSeatIdentifier) {
                        $q = $pdo->prepare("SELECT COUNT(DISTINCT NULLIF(seat_identifier,'')) FROM tickets WHERE schedule_id = :sid AND status IN ('reserved','held')");
                        $q->execute([':sid' => $schedule_id]);
                        $ticketsReserved = (int)$q->fetchColumn();
                    } else {
                        $q = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE schedule_id = :sid AND status IN ('reserved','held')");
                        $q->execute([':sid' => $schedule_id]);
                        $ticketsReserved = (int)$q->fetchColumn();
                    }
                } catch (Exception $e) { $ticketsReserved = 0; }

                // holds count
                $holdsCount = 0;
                if ($holdsTableExists) {
                    try {
                        if ($holdsHasSeatKey) {
                            $q = $pdo->prepare("SELECT COUNT(DISTINCT NULLIF(seat_key,'')) FROM cash_holds WHERE session_id = :sid AND (expires_at IS NULL OR expires_at > NOW())");
                            $q->execute([':sid' => $schedule_id]);
                            $holdsCount = (int)$q->fetchColumn();
                        } elseif (column_exists($pdo, 'cash_holds', 'seat_identifier')) {
                            $q = $pdo->prepare("SELECT COUNT(DISTINCT NULLIF(seat_identifier,'')) FROM cash_holds WHERE session_id = :sid AND (expires_at IS NULL OR expires_at > NOW())");
                            $q->execute([':sid' => $schedule_id]);
                            $holdsCount = (int)$q->fetchColumn();
                        } else {
                            $q = $pdo->prepare("SELECT COUNT(*) FROM cash_holds WHERE session_id = :sid AND (expires_at IS NULL OR expires_at > NOW())");
                            $q->execute([':sid' => $schedule_id]);
                            $holdsCount = (int)$q->fetchColumn();
                        }
                    } catch (Exception $e) { $holdsCount = 0; }
                }

                // overlap between tickets reserved and holds (only if both sides have seat key/identifier)
                $overlap = 0;
                if ($ticketsHasSeatKey && $holdsTableExists && $holdsHasSeatKey) {
                    try {
                        $q = $pdo->prepare("SELECT COUNT(DISTINCT t.seat_key) FROM tickets t JOIN cash_holds h ON h.session_id = t.schedule_id AND h.seat_key = t.seat_key WHERE t.schedule_id = :sid AND t.status IN ('reserved','held') AND (h.expires_at IS NULL OR h.expires_at > NOW()) AND t.seat_key IS NOT NULL AND t.seat_key <> ''");
                        $q->execute([':sid' => $schedule_id]);
                        $overlap = (int)$q->fetchColumn();
                    } catch (Exception $e) { $overlap = 0; }
                } elseif ($ticketsHasSeatIdentifier && $holdsTableExists && column_exists($pdo,'cash_holds','seat_identifier')) {
                    try {
                        $q = $pdo->prepare("SELECT COUNT(DISTINCT t.seat_identifier) FROM tickets t JOIN cash_holds h ON h.session_id = t.schedule_id AND h.seat_identifier = t.seat_identifier WHERE t.schedule_id = :sid AND t.status IN ('reserved','held') AND (h.expires_at IS NULL OR h.expires_at > NOW()) AND t.seat_identifier IS NOT NULL AND t.seat_identifier <> ''");
                        $q->execute([':sid' => $schedule_id]);
                        $overlap = (int)$q->fetchColumn();
                    } catch (Exception $e) { $overlap = 0; }
                }

                $reservedTotal = max(0, $ticketsReserved + $holdsCount - $overlap);

                // total seats (prefer seat_map explicit seats -> seats table -> capacity -> price_ranges)
                $totalSeats = null;
                try {
                    $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'seats'");
                    $st->execute();
                    $seatsTableExists = (bool)$st->fetchColumn();
                } catch (Exception $e) { $seatsTableExists = false; }

                if ($seatsTableExists && column_exists($pdo,'seats','schedule_id')) {
                    try {
                        $q = $pdo->prepare("SELECT COUNT(*) FROM seats WHERE schedule_id = :sid");
                        $q->execute([':sid' => $schedule_id]);
                        $totalSeats = (int)$q->fetchColumn();
                    } catch (Exception $e) { $totalSeats = null; }
                }

                if ($totalSeats === null) {
                    try {
                        $q = $pdo->prepare("SELECT capacity, seat_map, price_ranges FROM schedules WHERE id = :sid LIMIT 1");
                        $q->execute([':sid' => $schedule_id]);
                        $s = $q->fetch(PDO::FETCH_ASSOC);
                        if ($s) {
                            if (!empty($s['seat_map']) && function_exists('count_seats_from_seatmap')) {
                                $ts = count_seats_from_seatmap($s['seat_map']);
                                if ($ts !== null) $totalSeats = (int)$ts;
                            }
                            if ($totalSeats === null && !empty($s['capacity'])) $totalSeats = (int)$s['capacity'];
                            if ($totalSeats === null && !empty($s['price_ranges']) && function_exists('analyze_price_ranges')) {
                                $an = analyze_price_ranges(is_string($s['price_ranges']) ? $s['price_ranges'] : json_encode($s['price_ranges'], JSON_UNESCAPED_UNICODE));
                                if (!empty($an['total_seats'])) $totalSeats = (int)$an['total_seats'];
                            }
                        }
                    } catch (Exception $e) { /* ignore */ }
                }

                $available = null;
                if ($totalSeats !== null) $available = max(0, $totalSeats - $sold - $reservedTotal);

                // prepare response
                $respData = [
                    $schedule_id => [
                        'total' => $totalSeats,
                        'sold_count' => $sold,
                        'reserved_count' => $reservedTotal,
                        'available' => $available
                    ]
                ];

                json_response(['success' => true, 'data' => $respData, 'message' => 'Резерв снят', 'affected' => $affected, 'released' => $seat_keys]);
            } catch (Exception $e) {
                if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
                error_log("release_reservation error: " . $e->getMessage());
                json_response(['success' => false, 'message' => 'Ошибка при снятии резерва']);
            }
            break;

        // ---------------- default ------------------------------------------
        default:
            json_response(['success' => false, 'message' => 'Unknown action']);
    }
} catch (Exception $e) {
    error_log("ajax/cash.php fatal: " . $e->getMessage());
    if (isset($debug) && $debug) json_response(['success' => false, 'message' => 'Internal server error', 'error' => $e->getMessage()]);
    json_response(['success' => false, 'message' => 'Internal server error']);
}
