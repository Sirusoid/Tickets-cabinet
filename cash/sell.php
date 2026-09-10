<?php
// /cash/sell.php - страница продажи билета кассиром
require_once __DIR__ . '/../init.php';
require_login();
// Временно работаем без проверки ролей — доступ для авторизованного администратора
$session_id = isset($_GET['session_id']) ? (int)$_GET['session_id'] : 0;
$csrf = $_SESSION['csrf_token'] ?? '';

$use_sidebar = true;
$active_menu = 'cash';
$page_title_meta = 'Продажа';
$panel_title = 'Продажа';
$panel_subtitle = 'Страница оформления продажи билета';
$panel_actions = [
    ['href'=>'/cash/index.php','label'=>'Назад в кассу','class'=>'btn btn-primary btn-sm js-panel-back']
];

$page_scripts = ['/assets/js/ticket_viewer.js',
                 '/assets/js/schedule-seating-canvas.js',
                 '/assets/js/legend-canvas.js',
                 '/assets/js/cashier.js'];

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/panel.php';


/**
 * Вспомогательные функции для разбора price_ranges и seat_map
 */
if (!function_exists('analyze_price_ranges')) {
    function analyze_price_ranges($json) {
        $res = ['min_price' => null, 'max_price' => null, 'total_seats' => null];
        if (empty($json)) return $res;
        $data = json_decode($json, true);
        if (!is_array($data)) return $res;

        $prices = [];
        $totalSeats = 0;
        $hasCounts = false;

        $processRanges = function($ranges) use (&$totalSeats, &$hasCounts) {
            if (!is_array($ranges)) return;
            foreach ($ranges as $r) {
                if (!is_array($r)) continue;
                if (isset($r['from']) && isset($r['to']) && is_numeric($r['from']) && is_numeric($r['to'])) {
                    $from = (int)$r['from']; $to = (int)$r['to'];
                    if ($to >= $from) { $totalSeats += ($to - $from + 1); $hasCounts = true; }
                } elseif (isset($r['start']) && isset($r['end']) && is_numeric($r['start']) && is_numeric($r['end'])) {
                    $start = (int)$r['start']; $end = (int)$r['end'];
                    if ($end >= $start) { $totalSeats += ($end - $start + 1); $hasCounts = true; }
                } elseif (isset($r['seats']) && is_array($r['seats'])) {
                    $totalSeats += count($r['seats']); $hasCounts = true;
                }
            }
        };

        $isAssoc = array_keys($data) !== range(0, count($data) - 1);

        if ($isAssoc) {
            foreach ($data as $k => $v) {
                if (is_numeric($k)) $prices[] = (int) round((float)$k);
                if (is_array($v)) {
                    if (isset($v['price']) && is_numeric($v['price'])) $prices[] = (int) round((float)$v['price']);
                    if (isset($v['value']) && is_numeric($v['value'])) $prices[] = (int) round((float)$v['value']);
                    if (isset($v['count']) && is_numeric($v['count'])) { $totalSeats += (int)$v['count']; $hasCounts = true; }
                    if (isset($v['seats']) && is_array($v['seats'])) { $totalSeats += count($v['seats']); $hasCounts = true; }
                    if (isset($v['ranges'])) $processRanges($v['ranges']);
                } elseif (is_numeric($v)) {
                    $prices[] = (int) round((float)$v);
                }
            }
        } else {
            foreach ($data as $item) {
                if (is_array($item)) {
                    if (isset($item['price']) && is_numeric($item['price'])) $prices[] = (int) round((float)$item['price']);
                    if (isset($item['value']) && is_numeric($item['value'])) $prices[] = (int) round((float)$item['value']);
                    if (isset($item['count']) && is_numeric($item['count'])) { $totalSeats += (int)$item['count']; $hasCounts = true; }
                    if (isset($item['seats']) && is_array($item['seats'])) { $totalSeats += count($item['seats']); $hasCounts = true; }
                    if (isset($item['ranges'])) $processRanges($item['ranges']);
                } elseif (is_numeric($item)) {
                    $prices[] = (int) round((float)$item);
                }
            }
        }

        if (empty($prices)) {
            foreach ($data as $k => $v) if (is_numeric($k)) $prices[] = (int) round((float)$k);
        }

        if (!empty($prices)) {
            $res['min_price'] = min($prices);
            $res['max_price'] = max($prices);
        }
        if ($hasCounts && $totalSeats > 0) $res['total_seats'] = $totalSeats;

        return $res;
    }
}

if (!function_exists('price_groups_info')) {
    function price_groups_info($json) {
        $out = [];
        if (empty($json)) return $out;
        $data = json_decode($json, true);
        if (!is_array($data)) return $out;

        $processRangesCount = function($ranges) {
            $cnt = 0;
            if (!is_array($ranges)) return 0;
            foreach ($ranges as $r) {
                if (!is_array($r)) continue;
                if (isset($r['from']) && isset($r['to']) && is_numeric($r['from']) && is_numeric($r['to'])) {
                    $cnt += max(0, (int)$r['to'] - (int)$r['from'] + 1);
                } elseif (isset($r['start']) && isset($r['end']) && is_numeric($r['start']) && is_numeric($r['end'])) {
                    $cnt += max(0, (int)$r['end'] - (int)$r['start'] + 1);
                } elseif (isset($r['seats']) && is_array($r['seats'])) {
                    $cnt += count($r['seats']);
                }
            }
            return $cnt;
        };

        $isAssoc = array_keys($data) !== range(0, count($data) - 1);

        if ($isAssoc) {
            foreach ($data as $k => $v) {
                $priceKey = (string)$k;
                $color = null;
                $count = 0;
                $ranges = [];
                if (is_array($v)) {
                    if (isset($v['color'])) $color = $v['color'];
                    if (isset($v['count']) && is_numeric($v['count'])) $count += (int)$v['count'];
                    if (isset($v['seats']) && is_array($v['seats'])) $count += count($v['seats']);
                    if (isset($v['ranges']) && is_array($v['ranges'])) {
                        $ranges = $v['ranges'];
                        $count += $processRangesCount($v['ranges']);
                    }
                } elseif (is_numeric($v)) {
                    $priceKey = (string)$v;
                }
                if ($count > 0) {
                    if (!isset($out[$priceKey])) $out[$priceKey] = ['count' => 0, 'color' => $color, 'ranges' => []];
                    $out[$priceKey]['count'] += $count;
                    if ($color && empty($out[$priceKey]['color'])) $out[$priceKey]['color'] = $color;
                    if (!empty($ranges)) $out[$priceKey]['ranges'] = array_merge($out[$priceKey]['ranges'], $ranges);
                }
            }
        } else {
            foreach ($data as $item) {
                if (!is_array($item)) continue;
                $priceKey = null;
                $color = null;
                $count = 0;
                $ranges = [];
                if (isset($item['price']) && is_numeric($item['price'])) $priceKey = (string)$item['price'];
                elseif (isset($item['value']) && is_numeric($item['value'])) $priceKey = (string)$item['value'];
                if (isset($item['color'])) $color = $item['color'];
                if (isset($item['count']) && is_numeric($item['count'])) $count += (int)$item['count'];
                if (isset($item['seats']) && is_array($item['seats'])) $count += count($item['seats']);
                if (isset($item['ranges']) && is_array($item['ranges'])) {
                    $ranges = $item['ranges'];
                    $count += $processRangesCount($item['ranges']);
                }
                if ($priceKey !== null && $count > 0) {
                    if (!isset($out[$priceKey])) $out[$priceKey] = ['count' => 0, 'color' => $color, 'ranges' => []];
                    $out[$priceKey]['count'] += $count;
                    if ($color && empty($out[$priceKey]['color'])) $out[$priceKey]['color'] = $color;
                    if (!empty($ranges)) $out[$priceKey]['ranges'] = array_merge($out[$priceKey]['ranges'], $ranges);
                }
            }
        }

        uksort($out, function($a, $b){ return ((float)$a) <=> ((float)$b); });

        return $out;
    }
}

if (!function_exists('count_seats_from_seatmap')) {
    function count_seats_from_seatmap($seatmap_json) {
        if (empty($seatmap_json)) return null;
        $data = json_decode($seatmap_json, true);
        if (!is_array($data)) return null;
        $count = 0;
        if (isset($data['rows']) && is_array($data['rows'])) {
            foreach ($data['rows'] as $row) {
                if (isset($row['seats']) && is_array($row['seats'])) { $count += count($row['seats']); continue; }
                if (isset($row['start']) && isset($row['end'])) { $count += max(0, (int)$row['end'] - (int)$row['start'] + 1); continue; }
                if (isset($row['seatCount'])) { $count += (int)$row['seatCount']; continue; }
            }
            return $count > 0 ? $count : null;
        }
        if (isset($data['seats']) && is_array($data['seats'])) return count($data['seats']);
        return null;
    }
}
// --- Получаем данные сеанса из БД -------------------------------------------
if (function_exists('schedule_refresh_statuses') && isset($pdo) && $pdo instanceof PDO) {
  schedule_refresh_statuses($pdo, (int)$session_id);
}

$stmt = $pdo->prepare("SELECT s.*, e.title AS event_title, h.name AS hall_name FROM schedules s
                       LEFT JOIN events e ON e.id = s.event_id
                       LEFT JOIN halls h ON h.id = s.hall_id
                       WHERE s.id = :id LIMIT 1");
$stmt->execute([':id' => $session_id]);
$session = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$session) {
    http_response_code(404);
    echo "Сеанс не найден";
    require __DIR__ . '/../includes/footer.php';
    exit;
}

// --- Вычисляем total seats (из seat_map -> seats keys preferred) ----------
$totalSeats = null;
$prRaw = $session['price_ranges'] ?? null;

// Prefer explicit seat_map "seats" keys first (per your instruction)
if (!empty($session['seat_map'])) {
    $cnt = count_seats_from_seatmap($session['seat_map']);
    if (is_int($cnt)) $totalSeats = $cnt;
}

// fallback to price_ranges / capacity if seat_map didn't provide
if ($totalSeats === null && !empty($prRaw)) {
    $an = analyze_price_ranges(is_string($prRaw) ? $prRaw : json_encode($prRaw, JSON_UNESCAPED_UNICODE));
    if (!empty($an['total_seats'])) $totalSeats = $an['total_seats'];
}
if ($totalSeats === null && isset($session['capacity']) && $session['capacity'] !== null && $session['capacity'] !== '') {
    $totalSeats = (int)$session['capacity'];
}
if ($totalSeats === null && !empty($session['seat_map'])) {
    // if earlier count failed, try again with seatmap fallback
    $cnt = count_seats_from_seatmap($session['seat_map']);
    if (is_int($cnt)) $totalSeats = $cnt;
}

// --- Получаем количество проданных билетов (status = 'issued') ---------------
$soldCount = 0;
try {
    $q = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE schedule_id = :sid AND status = 'issued'");
    $q->execute([':sid' => $session_id]);
    $soldCount = (int)$q->fetchColumn();
} catch (Exception $e) {
    error_log("cash/sell.php sold count error: " . $e->getMessage());
    $soldCount = 0;
}

$freeSeats = null;
if (is_int($totalSeats)) {
    $freeSeats = max(0, $totalSeats - $soldCount);
}

// --- Собираем информацию по ценовым группам (количество мест, цвет, ranges) ----------
$priceGroups = [];
if (!empty($prRaw)) {
    $priceGroups = price_groups_info(is_string($prRaw) ? $prRaw : json_encode($prRaw, JSON_UNESCAPED_UNICODE));
}

// --- Получаем список проданных билетов (для сопоставления с группами) ----------
$soldTickets = [];
try {
    $q = $pdo->prepare("SELECT id, seat_id, seat_identifier FROM tickets WHERE schedule_id = :sid AND status = 'issued'");
    $q->execute([':sid' => $session_id]);
    $soldTickets = $q->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("cash/sell.php sold tickets fetch error: " . $e->getMessage());
    $soldTickets = [];
}

// --- Сопоставляем проданные места с ценовыми группами (best-effort) ----------
$groupSold = []; // price => sold_count (by matched seats)
foreach ($priceGroups as $price => $info) {
    $groupSold[$price] = 0;
}

// helper parse seat identifier
function parse_seat_identifier($s) {
    if (empty($s) || !is_string($s)) return null;
    $s = trim($s);
    if (preg_match('/^\s*(\d{1,3})\s*[-:\/]\s*(\d{1,3})\s*$/', $s, $m)) return [(int)$m[1], (int)$m[2]];
    if (preg_match('/(?:row|r|ряд)\D*(\d{1,3}).*?(?:seat|s|место)\D*(\d{1,3})/iu', $s, $m)) return [(int)$m[1], (int)$m[2]];
    if (preg_match('/^\s*(\d{1,3})\s+(\d{1,3})\s*$/', $s, $m)) return [(int)$m[1], (int)$m[2]];
    if (preg_match_all('/\d{1,3}/', $s, $m) && count($m[0]) >= 2) return [(int)$m[0][0], (int)$m[0][1]];
    return null;
}

function seat_in_ranges($row, $seat, $ranges) {
    if (!is_array($ranges)) return false;
    foreach ($ranges as $r) {
        if (!is_array($r)) continue;
        $rRow = isset($r['row']) && is_numeric($r['row']) ? (int)$r['row'] : null;
        $from = isset($r['from']) && is_numeric($r['from']) ? (int)$r['from'] : null;
        $to = isset($r['to']) && is_numeric($r['to']) ? (int)$r['to'] : null;
        $start = isset($r['start']) && is_numeric($r['start']) ? (int)$r['start'] : null;
        $end = isset($r['end']) && is_numeric($r['end']) ? (int)$r['end'] : null;
        if ($from !== null && $to !== null) {
            if ($rRow !== null) {
                if ($row === $rRow && $seat >= $from && $seat <= $to) return true;
            } else {
                if ($seat >= $from && $seat <= $to) return true;
            }
        } elseif ($start !== null && $end !== null) {
            if ($rRow !== null) {
                if ($row === $rRow && $seat >= $start && $seat <= $end) return true;
            } else {
                if ($seat >= $start && $seat <= $end) return true;
            }
        } elseif (isset($r['seats']) && is_array($r['seats'])) {
            foreach ($r['seats'] as $sitem) {
                if (is_array($sitem)) {
                    $srow = isset($sitem['row']) ? (int)$sitem['row'] : null;
                    $snum = isset($sitem['num']) ? (int)$sitem['num'] : (isset($sitem['seat']) ? (int)$sitem['seat'] : null);
                    if ($srow !== null && $snum !== null && $srow === $row && $snum === $seat) return true;
                } elseif (is_numeric($sitem)) {
                    if ($seat === (int)$sitem) return true;
                } elseif (is_string($sitem)) {
                    $p = parse_seat_identifier($sitem);
                    if ($p && $p[0] === $row && $p[1] === $seat) return true;
                }
            }
        }
    }
    return false;
}

$matchedTickets = 0;
$unmatchedTickets = 0;

foreach ($soldTickets as $t) {
    $matched = false;
    $rowSeat = null;
    if (!empty($t['seat_identifier'])) $rowSeat = parse_seat_identifier($t['seat_identifier']);
    if ($rowSeat) {
        $row = $rowSeat[0];
        $seat = $rowSeat[1];
        foreach ($priceGroups as $price => $info) {
            $ranges = $info['ranges'] ?? [];
            if (!empty($ranges) && seat_in_ranges($row, $seat, $ranges)) {
                $groupSold[$price] += 1;
                $matched = true;
                $matchedTickets++;
                break;
            }
        }
    }
    if (!$matched) $unmatchedTickets++;
}

// compute free per group
$groupFree = [];
$totalFreeFromGroups = 0;
foreach ($priceGroups as $price => $info) {
    $grpTotal = isset($info['count']) ? (int)$info['count'] : 0;
    $soldInGroup = isset($groupSold[$price]) ? (int)$groupSold[$price] : 0;
    $free = max(0, $grpTotal - $soldInGroup);
    $groupFree[$price] = $free;
    $totalFreeFromGroups += $free;
}

// distribute unmatched sold tickets proportionally (best-effort)
if ($unmatchedTickets > 0 && $totalFreeFromGroups > 0) {
    $remaining = $unmatchedTickets;
    $alloc = [];
    foreach ($groupFree as $price => $free) {
        $alloc[$price] = floor($unmatchedTickets * ($free / $totalFreeFromGroups));
        $remaining -= $alloc[$price];
    }
    if ($remaining > 0) {
        arsort($groupFree);
        foreach ($groupFree as $price => $free) {
            if ($remaining <= 0) break;
            if ($free - ($alloc[$price] ?? 0) <= 0) continue;
            $alloc[$price] = ($alloc[$price] ?? 0) + 1;
            $remaining--;
        }
    }
    foreach ($alloc as $price => $take) {
        $groupFree[$price] = max(0, $groupFree[$price] - $take);
    }
}
// --- Загрузка настроек скидок из таблицы settings ---------------------------
$discounts = ['adult' => 0, 'child' => 0, 'student' => 0, 'senior' => 0];
$allowManualDiscount = false;
try {
  $stmtSet = $pdo->prepare("SELECT `key`, `value` FROM settings WHERE `key` IN ('tickets.discount_adult_percent','tickets.discount_child_percent','tickets.discount_student_percent','tickets.discount_senior_percent','tickets.custom_discount_enabled')");
    $stmtSet->execute();
    $rows = $stmtSet->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $k = $r['key'];
        $v = $r['value'];
        if ($k === 'tickets.discount_adult_percent') $discounts['adult'] = is_numeric($v) ? (float)$v : 0;
        if ($k === 'tickets.discount_child_percent') $discounts['child'] = is_numeric($v) ? (float)$v : 0;
        if ($k === 'tickets.discount_student_percent') $discounts['student'] = is_numeric($v) ? (float)$v : 0;
        if ($k === 'tickets.discount_senior_percent') $discounts['senior'] = is_numeric($v) ? (float)$v : 0;
        if ($k === 'tickets.custom_discount_enabled') $allowManualDiscount = in_array(strtolower(trim((string)$v)), ['1', 'true', 'yes', 'on'], true);
    }
      if (!$allowManualDiscount && function_exists('settings_get_value')) {
        $allowManualDiscount = in_array(strtolower(trim((string)settings_get_value($pdo, 'tickets.custom_discount_enabled', '1'))), ['1', 'true', 'yes', 'on'], true);
      }
} catch (Exception $e) {
    error_log("Failed to load discount settings: " . $e->getMessage());
    // оставляем значения по умолчанию (0)
}

// --- Подготовка данных для JS (безопасно) ----------------------------------
$jsSession = [
    'id' => (int)$session['id'],
    'event_title' => $session['event_title'] ?? '',
    'hall_name' => $session['hall_name'] ?? '',
    'start_time' => $session['start_time'] ?? null,
    'end_time' => $session['end_time'] ?? null,
    'base_price' => isset($session['base_price']) ? $session['base_price'] : null,
    'price_ranges' => $prRaw ? $prRaw : null,
    'seat_map' => $session['seat_map'] ?? null,
    'total_seats' => $totalSeats,
    'sold_count' => $soldCount,
    'free_seats' => $freeSeats,
    'price_groups' => $priceGroups,
    'group_free' => $groupFree,
    'sold_list' => array_map(function($t){
        $key = $t['seat_identifier'] ?? null;
        return ['id' => $t['id'], 'seat_identifier' => $key, 'seat_key' => $key];
    }, $soldTickets)
];
?>

<link rel="stylesheet" href="/assets/css/cashier.css">
<link rel="stylesheet" href="/assets/css/actor_form.css">
<link rel="stylesheet" href="/assets/css/seatmap.css">

<!-- Inline styles to visually indicate disabled/enabled state of release button -->
<style>
  /* Make disabled release button visually distinct */
  #releaseHoldBtn[disabled] {
    opacity: 0.6;
    cursor: not-allowed;
    filter: grayscale(20%);
    box-shadow: none !important;
    transform: none !important;
  }
  /* Optional stronger visual when enabled */
  #releaseHoldBtn:not([disabled]) {
    opacity: 1;
    cursor: pointer;
    transition: transform 0.08s ease, box-shadow 0.08s ease;
  }
  #releaseHoldBtn:not([disabled]):hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 14px rgba(0,0,0,0.08);
  }
  /* Accessible focus outline */
  #releaseHoldBtn:focus {
    outline: 3px solid rgba(255, 221, 87, 0.35);
    outline-offset: 2px;
  }
</style>

<div class="page">
  <div class="card" style="margin-bottom:12px;">
    <div id="sessionHeader" style="display:flex; gap:16px; align-items:center;">
      <div>
        <h3 id="sessionTitle" style="margin:0; font-size:18px;">
          <?= h(!empty($session['event_title']) ? ('«' . $session['event_title'] . '» — Сеанс #' . $session_id) : ('Сеанс #' . $session_id)) ?>
        </h3>
      </div>
      <div style="margin-left:auto; color:#444; font-size:13px; display:flex; gap:18px; align-items:center;">
        <div><strong>Дата:</strong> <span id="sessionDate"><?= h(!empty($session['start_time']) ? date('d/m/Y', strtotime($session['start_time'])) : '—') ?></span></div>
        <div><strong>Начало:</strong> <span id="sessionStart"><?= h(!empty($session['start_time']) ? date('H:i', strtotime($session['start_time'])) : '—') ?></span></div>
        <div><strong>Окончание:</strong> <span id="sessionEnd"><?= h(!empty($session['end_time']) ? date('H:i', strtotime($session['end_time'])) : '—') ?></span></div>
        <div style="display:flex; gap:12px; align-items:center;">
          <div><strong>Продано:</strong> <span id="soldCountHeader"><?= h((string)$soldCount) ?></span></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Клиентские данные — на всю ширину -->
  <div class="profile-layout" style="grid-template-columns: 1fr 320px; margin-top: 17px; align-items:start;">
    <div class="profile-card profile-bio-full" style="grid-column: 1 / -1; margin-bottom: -10px; align-self:start;">
      <div class="card-title">Данные клиента</div>

      <div class="actors-row" style="margin-bottom:12px;">
        <div style="position:relative;">
          <label class="label">Клиент</label>
          <input id="custFullName" class="input" type="text" placeholder="ФИО клиента" autocomplete="off" />
          <div id="custNameResults" style="position:absolute; left:0; right:0; top:100%; z-index:100; background:#fff; border:1px solid #e0e0e0; border-radius:6px; margin-top:4px; max-height:200px; overflow:auto; display:none; box-shadow:0 6px 18px rgba(0,0,0,0.08);"></div>
        </div>
        <div>
          <label class="label">Тип билета</label>
          <select id="customer_segment" name="customer_segment" class="input">
            <option value="adult" data-discount="<?= h((string)($discounts['adult'] ?? 0)) ?>">Взрослый <?= '(' . h((string)($discounts['adult'] ?? 0)) . '%)' ?></option>
            <option value="child" data-discount="<?= h((string)($discounts['child'] ?? 0)) ?>">Детский <?= '(' . h((string)($discounts['child'] ?? 0)) . '%)' ?></option>
            <option value="student" data-discount="<?= h((string)($discounts['student'] ?? 0)) ?>">Студенческий <?= '(' . h((string)($discounts['student'] ?? 0)) . '%)' ?></option>
            <option value="senior" data-discount="<?= h((string)($discounts['senior'] ?? 0)) ?>">Пенсионный <?= '(' . h((string)($discounts['senior'] ?? 0)) . '%)' ?></option>
            <?php if ($allowManualDiscount): ?>
              <option value="manual">Ручная скидка</option>
            <?php endif; ?>
          </select>
        </div>
      </div>

      <div class="actors-row" style="margin-bottom:12px;">
        <div style="position:relative;">
          <label class="label">Номер телефона (обязательно)</label>
          <input id="custPhone" class="input" type="tel" placeholder="+7 700 000 0000" autocomplete="off" />
          <div id="custPhoneResults" style="position:absolute; left:0; right:0; top:100%; z-index:100; background:#fff; border:1px solid #e0e0e0; border-radius:6px; margin-top:4px; max-height:200px; overflow:auto; display:none; box-shadow:0 6px 18px rgba(0,0,0,0.08);"></div>
        </div>
        <div style="position:relative;">
          <label class="label">Email</label>
          <input id="custEmail" class="input" type="email" placeholder="email@example.com" />
        </div>
      </div>

      <div class="actors-row" style="margin-bottom:6px;">
        <div>
          <label class="label">Пол</label>
          <select id="custGender" class="input">
            <option value="">Не указан</option>
            <option value="male">Мужской</option>
            <option value="female">Женский</option>
            <option value="other">Другое</option>
          </select>
        </div>
        <div>
          <label class="label">Город</label>
          <input id="custCity" class="input" type="text" placeholder="Город" />
        </div>
      </div>
    </div>
<!-- Схема рассадки (левая, широкая) -->
    <div class="profile-card" style="grid-column: 1 / 2;">
      <h3 class="card-title" style="margin-top:0;">Схема рассадки</h3>
      <div id="seatmapContainer" style="position:relative; height:640px;">
        <canvas id="cash-seatmap" width="1200" height="800" style="width:100%; height:100%; border:1px solid #e6e6e6; background:#fff; display:block;"></canvas>
      </div>
    </div>

    <!-- Корзина (правая колонка) -->
    <aside class="profile-card" style="grid-column: 2 / 3; align-self:start;">
      <div class="card-title">Корзина</div>
      <div id="cartList" style="min-height:60px; margin-bottom:8px;">
        <div style="color:#666;">Пусто</div>
      </div>

      <div style="margin-top:8px; padding:8px; border:1px solid #f1f1f1; border-radius:6px; background:#fafafa;">
        <div style="display:flex; justify-content:space-between; margin-bottom:6px;">
          <div style="color:#666;">Базовая сумма</div>
          <div id="baseTotalTg" style="font-weight:700;">—</div>
        </div>
        <div style="display:flex; justify-content:space-between; margin-bottom:6px;">
          <div style="color:#666;">Скидка</div>
          <div id="discountTotalTg" style="font-weight:800;">0 тг</div>
        </div>
        <div style="display:flex; justify-content:space-between; margin-bottom:6px;">
          <div style="color:#666;">Итого</div>
          <div id="finalTotalTg" style="font-weight:800;">—</div>
        </div>
      </div>

      <div style="margin-top:8px;">
        <label class="label">Оплата</label>
        <select id="paymentMethod" class="input" style="width:100%;">
          <option value="cash">Наличные</option>
          <option value="card">Карта</option>
        </select>

        <?php if ($allowManualDiscount): ?>
        <div style="margin-top:12px;">
          <label class="label">Ручная скидка</label>
          <input id="manualDiscountAmount" class="input" type="number" min="0" step="1" value="0" disabled />
          <small id="manualDiscountHint" style="display:block; margin-top:6px; color:#5d6f83; font-size:12px;">
            Активируется только при выборе пункта «Ручная скидка»
          </small>
        </div>
        <?php endif; ?>

        <label class="label" style="margin-top:12px;">Срок резерва (минут)</label>
        <input type="number" id="reserveTtl" class="input" value="10" min="1" max="240" style="margin-top:8px;" />
      </div>

      <div style="margin-top:10px; display:flex; gap:8px; flex-wrap:wrap;">
        <button id="reserveBtn" class="btn">Резерв</button>
        <button id="sellBtn" class="btn btn-primary">Продать</button>
        <button id="cancelSaleBtn" class="btn" style="background:#fff; border:1px solid #e0e0e0;">Отменить</button>

        <!-- Кнопка Снять резерв (по умолчанию disabled) -->
        <button id="releaseHoldBtn" class="btn btn-warning" type="button" disabled style="background:#ffdd57; border-color:#f0c000; color:#222;">
          Снять резерв
        </button>
      </div>

      <div id="holdInfo" style="margin-top:12px; color:#555; font-size:13px; line-height:1.4;">Если билет в резерве, выберите место и нажмите «Продать».</div>
      <div id="sellResult" style="margin-top:12px;"></div>
    </aside>
  </div>
</div>

<div id="ticketPreviewModal" style="display:none; position:fixed; inset:0; z-index:2200; align-items:center; justify-content:center; background:rgba(0,0,0,0.65);">
  <div style="position:relative; width:min(1120px,100%); max-height:90vh; background:#fff; border-radius:10px; box-shadow:0 18px 48px rgba(0,0,0,0.28); overflow:hidden; display:flex; flex-direction:column; min-height:calc(100vh - 120px);">
    <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; padding:12px 16px; border-bottom:1px solid #e9ecef; background:#fafafa;">
      <div id="ticketPreviewTitle" style="font-size:16px; font-weight:700; color:#222;">Просмотр билета</div>
      <div style="display:flex; gap:8px; flex-wrap:wrap;">
        <button id="ticketPreviewDownload" type="button" class="btn btn-secondary btn-sm">Скачать</button>
        <button id="ticketPreviewOpenNewTab" type="button" class="btn btn-ghost btn-sm">Открыть в новой вкладке</button>
        <button id="ticketPreviewClose" type="button" class="btn btn-ghost btn-xs">✕</button>
      </div>
    </div>
    <iframe id="ticketPreviewIframe" src="about:blank" style="flex:1; width:100%; border:none; min-height:0;"></iframe>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
  var sessionData = <?= json_encode($jsSession, JSON_UNESCAPED_UNICODE) ?>;
  var csrfToken = <?= json_encode($csrf) ?>;
  var sessionId = <?= json_encode($session_id) ?>;
  window.currentCashierUserId = <?= json_encode((int)($_SESSION['user_id'] ?? 0)) ?>;
  window.currentCustomerId = null;

  function _localToast(msg, type, duration) {
    duration = duration || 3000;
    var wrap = document.getElementById('toast-wrap');
    var el = document.createElement('div');
    el.textContent = msg;
    el.style.background = (type === 'success') ? '#2d9c2d' : ((type === 'error') ? '#c0392b' : '#2d6fa3');
    el.style.color = '#fff';
    el.style.padding = '8px 12px';
    el.style.borderRadius = '8px';
    el.style.marginTop = '6px';
    el.style.boxShadow = '0 6px 18px rgba(0,0,0,0.08)';
    el.style.pointerEvents = 'auto';
    if (wrap) wrap.appendChild(el);
    setTimeout(function(){ try { el.remove(); } catch(e){} }, duration);
  }

  function showToastMessage(msg, type, duration) {
    duration = duration || 3000;
    if (window.showToast && typeof window.showToast === 'function') {
      try { window.showToast(msg, type || 'info', { duration: duration }); return; } catch (e) {}
    }
    _localToast(msg, type, duration);
  }

  (function overrideAlert(){
    try { window._original_alert = window.alert; } catch(e){}
    window.alert = function(message){
      try { showToastMessage(String(message || ''), 'error', 3500); } catch(e){ try { window._original_alert && window._original_alert(String(message || '')); } catch(e){} }
    };
  })();

  // Customer search autocomplete
  (function setupCustomerSearch(){
    var phoneEl = document.getElementById('custPhone');
    var nameEl = document.getElementById('custFullName');
    var phoneResults = document.getElementById('custPhoneResults');
    var nameResults = document.getElementById('custNameResults');
    var phoneTimer = null;
    var nameTimer = null;

    function normalizePhone(p) { return (p || '').replace(/\D+/g, ''); }

    function formatPhoneDisplay(phone) {
      var p = normalizePhone(phone);
      if (p.length === 11 && p.charAt(0) === '7') {
        return '+7 ' + p.substr(1,3) + ' ' + p.substr(4,3) + ' ' + p.substr(7,2) + ' ' + p.substr(9,2);
      }
      return p;
    }

    function normalizeSearchPhone(p) {
      var digits = normalizePhone(p);
      if (digits.charAt(0) === '8') digits = '7' + digits.substr(1);
      if (digits.length > 0 && digits.charAt(0) !== '7') digits = '7' + digits;
      return digits;
    }

    function fillCustomer(c) {
      window.currentCustomerId = c.id || null;
      if (nameEl) nameEl.value = c.full_name || '';
      if (phoneEl) phoneEl.value = formatPhoneDisplay(c.phone || '');
      var emailEl = document.getElementById('custEmail');
      var cityEl = document.getElementById('custCity');
      var genderEl = document.getElementById('custGender');
      if (emailEl) emailEl.value = c.email || '';
      if (cityEl) cityEl.value = c.city || '';
      if (genderEl) genderEl.value = c.gender || '';
      if (phoneResults) phoneResults.style.display = 'none';
      if (nameResults) nameResults.style.display = 'none';
    }

    function renderResults(container, items) {
      if (!container) return;
      container.innerHTML = '';
      if (!items || !items.length) {
        container.style.display = 'none';
        return;
      }
      items.forEach(function(c) {
        var row = document.createElement('div');
        row.style.padding = '8px 10px';
        row.style.cursor = 'pointer';
        row.style.borderBottom = '1px solid #f1f1f1';
        row.style.fontSize = '13px';

        var nameSpan = document.createElement('span');
        nameSpan.style.fontWeight = '700';
        nameSpan.textContent = c.full_name || '—';

        var phoneSpan = document.createElement('span');
        phoneSpan.style.color = '#555';
        phoneSpan.style.marginLeft = '8px';
        phoneSpan.textContent = formatPhoneDisplay(c.phone || '');

        row.appendChild(nameSpan);
        row.appendChild(phoneSpan);
        row.addEventListener('mousedown', function(ev){ ev.preventDefault(); fillCustomer(c); });
        row.addEventListener('mouseenter', function(){ row.style.background = '#f3f8ff'; });
        row.addEventListener('mouseleave', function(){ row.style.background = ''; });
        container.appendChild(row);
      });
      container.style.display = 'block';
    }

    function searchByPhone() {
      var q = normalizeSearchPhone(phoneEl ? phoneEl.value : '');
      if (!q || q.length < 3) { if (phoneResults) phoneResults.style.display = 'none'; return; }
      fetch('/ajax/cash.php?action=customer_search&phone=' + encodeURIComponent(q), { credentials: 'same-origin' })
        .then(function(r){ return r.json(); })
        .then(function(res){ if (res && res.success) renderResults(phoneResults, res.data); })
        .catch(function(e){ console.error('customer_search phone error', e); });
    }

    function searchByName() {
      var q = (nameEl ? nameEl.value : '').trim();
      if (!q || q.length < 2) { if (nameResults) nameResults.style.display = 'none'; return; }
      fetch('/ajax/cash.php?action=customer_search&query=' + encodeURIComponent(q), { credentials: 'same-origin' })
        .then(function(r){ return r.json(); })
        .then(function(res){ if (res && res.success) renderResults(nameResults, res.data); })
        .catch(function(e){ console.error('customer_search name error', e); });
    }

    if (phoneEl) {
      phoneEl.addEventListener('input', function(){
        if (phoneTimer) clearTimeout(phoneTimer);
        phoneTimer = setTimeout(searchByPhone, 300);
      });
      phoneEl.addEventListener('blur', function(){ setTimeout(function(){ if (phoneResults) phoneResults.style.display = 'none'; }, 200); });
      phoneEl.addEventListener('focus', function(){ if (phoneResults && phoneResults.children.length) phoneResults.style.display = 'block'; });
    }

    if (nameEl) {
      nameEl.addEventListener('input', function(){
        if (nameTimer) clearTimeout(nameTimer);
        nameTimer = setTimeout(searchByName, 300);
      });
      nameEl.addEventListener('blur', function(){ setTimeout(function(){ if (nameResults) nameResults.style.display = 'none'; }, 200); });
      nameEl.addEventListener('focus', function(){ if (nameResults && nameResults.children.length) nameResults.style.display = 'block'; });
    }

    document.addEventListener('click', function(e){
      if (phoneResults && !phoneEl.contains(e.target) && !phoneResults.contains(e.target)) phoneResults.style.display = 'none';
      if (nameResults && !nameEl.contains(e.target) && !nameResults.contains(e.target)) nameResults.style.display = 'none';
    });
  })();

  // init cashier
  if (typeof Cashier !== 'undefined' && typeof Cashier.initSell === 'function') {
    Cashier.initSell({
      csrf: csrfToken,
      currentUserId: <?= json_encode((int)($_SESSION['user_id'] ?? 0), JSON_UNESCAPED_UNICODE) ?>,
      sessionId: sessionId,
      seatmapCanvasId: 'cash-seatmap',
      seatmapRenderer: null,
      legendContainerId: null,
      cartListId: 'cartList',
      paymentMethodId: 'paymentMethod',
      manualDiscountAmountId: 'manualDiscountAmount',
      reserveBtnId: 'reserveBtn',
      reserveTtlInputId: 'reserveTtl',
      holdInfoId: 'holdInfo',
      sellBtnId: 'sellBtn',
      discountConfig: {
        segment_percent: {
          adult: <?= json_encode((float)($discounts['adult'] ?? 0)) ?>,
          child: <?= json_encode((float)($discounts['child'] ?? 0)) ?>,
          student: <?= json_encode((float)($discounts['student'] ?? 0)) ?>,
          senior: <?= json_encode((float)($discounts['senior'] ?? 0)) ?>
        },
          custom_enabled: <?= json_encode((bool)$allowManualDiscount) ?>,
        custom_max_percent: 30,
        custom_max_amount: 0
      },
      session: sessionData,
      onSessionLoaded: function(session){
        var titleEl = document.getElementById('sessionTitle');
        var dateEl = document.getElementById('sessionDate');
        var startEl = document.getElementById('sessionStart');
        var endEl = document.getElementById('sessionEnd');
        if (titleEl) titleEl.textContent = session.event_title ? ('«' + session.event_title + '» — Сеанс #' + (session.id || '')) : ('Сеанс #' + (session.id || ''));
        if (dateEl) dateEl.textContent = session.start_time ? (new Date(session.start_time)).toLocaleDateString() : '—';
        if (startEl) startEl.textContent = session.start_time ? (new Date(session.start_time)).toLocaleTimeString([], { hour:'2-digit', minute:'2-digit' }) : '—';
        if (endEl) endEl.textContent = session.end_time ? (new Date(session.end_time)).toLocaleTimeString([], { hour:'2-digit', minute:'2-digit' }) : '—';
      }
    });
  }

  // cancel handlers
  function handleCancelSale() {
    var message = 'Вы уверены, что хотите отменить оформление продажи и вернуться в кассу?';
    if (typeof window.showModalDelete === 'function') {
      window.showModalDelete(message, {});
      var confirmBtn = document.getElementById('modal-delete-confirm');
      if (confirmBtn) {
        var onConfirm = function () {
          try { if (typeof window.hideModalDelete === 'function') window.hideModalDelete(); } catch(e){}
          showToastMessage('Продажа отменена', 'success', 1500);
          setTimeout(function(){ window.location.href = '/cash/index.php'; }, 700);
        };
        confirmBtn.addEventListener('click', onConfirm, { once: true });
      } else {
        showToastMessage('Продажа отменена', 'success', 1500);
        setTimeout(function(){ window.location.href = '/cash/index.php'; }, 700);
      }
    } else {
      showToastMessage('Продажа отменена', 'success', 1500);
      setTimeout(function(){ window.location.href = '/cash/index.php'; }, 700);
    }
  }

  var cancelBtn = document.getElementById('cancelSaleBtn');
  if (cancelBtn) {
    cancelBtn.addEventListener('click', function (e) {
      e.preventDefault();
      handleCancelSale();
    });
  }

  var panelBackEls = document.querySelectorAll('.js-panel-back');
  if (panelBackEls && panelBackEls.length) {
    panelBackEls.forEach(function(el){
      el.addEventListener('click', function (ev) {
        ev.preventDefault();
        handleCancelSale();
      });
    });
  }
});
</script>
<!-- Release hold button logic: activates only for reserved seats and calls AJAX to release -->
<script>
(function(){
  // expose server session data if not already
  if (typeof window.jsSession === 'undefined') {
    window.jsSession = <?= json_encode($jsSession, JSON_UNESCAPED_UNICODE) ?>;
  }
  if (typeof window.csrfToken === 'undefined') {
    window.csrfToken = <?= json_encode($csrf, JSON_UNESCAPED_UNICODE) ?>;
  }

  var btnRelease = document.getElementById('releaseHoldBtn');
  var cartList = document.getElementById('cartList');

  function getSelectedSeatElement() {
    var el = document.querySelector('.seat.selected');
    if (el) return el;
    var input = document.querySelector('.seat input[type="radio"]:checked, .seat input[type="checkbox"]:checked');
    if (input) return input.closest('.seat') || input;
    var checked = document.querySelector('input[name="seat"]:checked');
    if (checked) return checked.closest('.seat') || checked;
    var cartSelected = cartList ? cartList.querySelector('.cart-item.selected, .cart-item[data-selected="1"], .cart-row.selected, [data-selected="1"]') : null;
    if (cartSelected) return cartSelected;
    return null;
  }

  function readSeatInfoFromElement(el) {
    if (!el) return null;
    var scheduleId = el.getAttribute('data-sid') || el.getAttribute('data-schedule-id') || (window.jsSession && window.jsSession.id ? String(window.jsSession.id) : null);
    var seatKey = el.getAttribute('data-seat-key') || el.getAttribute('data-key') || el.getAttribute('data-seat') || el.getAttribute('data-seat-identifier') || el.getAttribute('data-seat_identifier') || null;
    if (!seatKey) {
      var inp = el.querySelector('input[name="seat"], input[data-seat-key], input[data-seat]');
      if (inp) seatKey = inp.value || inp.getAttribute('data-seat-key') || inp.getAttribute('data-seat');
    }
    var reservedAttr = el.getAttribute('data-reserved');
    var isReserved = false;
    if (reservedAttr !== null) {
      isReserved = (reservedAttr === '1' || reservedAttr === 'true' || reservedAttr === 'yes');
    } else {
      isReserved = el.classList.contains('reserved') || el.classList.contains('seat--reserved') || el.classList.contains('is-reserved') || !!el.querySelector('.badge-reserved, .seat-badge.reserved');
    }
    if (!isReserved && el.textContent && /резерв|reserved|hold|held/i.test(el.textContent)) isReserved = true;
    return {
      element: el,
      schedule_id: scheduleId ? scheduleId : null,
      seat_key: seatKey ? seatKey : null,
      is_reserved: !!isReserved
    };
  }

  function updateReleaseButtonState() {
    if (!btnRelease) return;
    var el = getSelectedSeatElement();
    var info = readSeatInfoFromElement(el);
    var shouldEnable = (info && info.is_reserved && info.schedule_id && info.seat_key);
    // set disabled attribute
    btnRelease.disabled = !shouldEnable;
    // also set aria-disabled for accessibility
    btnRelease.setAttribute('aria-disabled', btnRelease.disabled ? 'true' : 'false');
    // visual class toggles are handled by CSS via [disabled] selector, but keep a class for legacy styling if needed
    if (btnRelease.disabled) {
      btnRelease.classList.add('is-disabled');
      btnRelease.classList.remove('is-enabled');
    } else {
      btnRelease.classList.remove('is-disabled');
      btnRelease.classList.add('is-enabled');
    }
  }

  function applyCountsUpdate(scheduleId, counts) {
    if (!scheduleId || !counts) return;
    var soldHeader = document.getElementById('soldCountHeader');
    var freeHeader = document.getElementById('freeSeatsHeader');
    var totalHeader = document.getElementById('totalSeatsHeader');

    if (typeof counts.sold_count !== 'undefined' && soldHeader) soldHeader.textContent = String(counts.sold_count);
    if (typeof counts.total !== 'undefined' && totalHeader) totalHeader.textContent = String(counts.total);
    if (typeof counts.available !== 'undefined' && freeHeader) freeHeader.textContent = String(counts.available);

    var soldEl = document.querySelector('.sold-number[data-sid="'+scheduleId+'"]');
    var resEl = document.querySelector('.reserved-number[data-sid="'+scheduleId+'"]');
    var availEl = document.querySelector('.seats-number[data-sid="'+scheduleId+'"], .seats-loader[data-sid="'+scheduleId+'"], .col-seats [data-sid="'+scheduleId+'"]');

    if (soldEl && typeof counts.sold_count !== 'undefined') soldEl.textContent = String(counts.sold_count);
    if (resEl && typeof counts.reserved_count !== 'undefined') resEl.textContent = String(counts.reserved_count);
    if (availEl && (typeof counts.available !== 'undefined' || typeof counts.free !== 'undefined')) {
      var v = (typeof counts.available !== 'undefined') ? counts.available : counts.free;
      availEl.textContent = (v === null || v === undefined) ? '—' : String(v);
    }
  }

  function releaseReservation(scheduleId, seatKey, onDone) {
    if (!scheduleId || !seatKey) {
      if (typeof onDone === 'function') onDone({ success: false, message: 'Неверные параметры' });
      return;
    }

    // disable button immediately to prevent double clicks
    if (btnRelease) {
      btnRelease.disabled = true;
      btnRelease.setAttribute('aria-disabled', 'true');
      btnRelease.classList.add('is-disabled');
    }
    var prevText = btnRelease ? btnRelease.textContent : 'Снятие...';
    if (btnRelease) btnRelease.textContent = 'Снятие...';

    var payload = {
      action: 'release_reservation',
      schedule_id: scheduleId,
      seat_key: seatKey,
      csrf_token: window.csrfToken || ''
    };

    fetch('/ajax/cash.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      credentials: 'same-origin',
      body: JSON.stringify(payload)
    }).then(function(resp){ return resp.json(); })
    .then(function(json){
      if (!json || !json.success) {
        var msg = (json && json.message) ? json.message : 'Ошибка при снятии резерва';
        alert(msg);
        if (btnRelease) {
          btnRelease.textContent = prevText;
          updateReleaseButtonState();
        }
        if (typeof onDone === 'function') onDone({ success: false, message: msg });
        return;
      }

      if (json.data) {
        var counts = null;
        if (json.data[String(scheduleId)]) counts = json.data[String(scheduleId)];
        else counts = json.data;
        applyCountsUpdate(String(scheduleId), counts);
      }

      try {
        var el = getSelectedSeatElement();
        if (el) {
          el.classList.remove('reserved', 'seat--reserved', 'is-reserved');
          el.removeAttribute('data-reserved');
          var badge = el.querySelector('.badge-reserved, .seat-badge.reserved');
          if (badge) badge.remove();
        }

        var cartItem = cartList ? cartList.querySelector('[data-seat-key="'+seatKey+'"], [data-seat="'+seatKey+'"], [data-seat-identifier="'+seatKey+'"]') : null;
        if (cartItem) {
          cartItem.classList.remove('reserved', 'is-reserved');
          cartItem.removeAttribute('data-reserved');
          var badge2 = cartItem.querySelector('.badge-reserved, .hold-label');
          if (badge2) badge2.remove();
        }
      } catch(e){ console.error(e); }

      if (btnRelease) {
        btnRelease.textContent = prevText;
        updateReleaseButtonState();
      }
      if (typeof onDone === 'function') onDone({ success: true, data: json.data });
    }).catch(function(err){
      console.error(err);
      alert('Ошибка сети при снятии резерва');
      if (btnRelease) {
        btnRelease.textContent = prevText;
        updateReleaseButtonState();
      }
      if (typeof onDone === 'function') onDone({ success: false, message: 'network' });
    });
  }

  if (btnRelease) {
    btnRelease.addEventListener('click', function (event) {
      event.preventDefault();
      var el = getSelectedSeatElement();
      var info = readSeatInfoFromElement(el);
      if (!info || !info.is_reserved || !info.schedule_id || !info.seat_key) {
        updateReleaseButtonState();
        return;
      }
      releaseReservation(info.schedule_id, info.seat_key, function (result) {
        if (result && result.success && window.Cashier && typeof window.Cashier.refreshHoldState === 'function') {
          window.Cashier.refreshHoldState();
        }
      });
    });
  }

document.addEventListener('click', function(e){
    if (e.target.closest && e.target.closest('.seat')) {
      setTimeout(updateReleaseButtonState, 10);
    }
  }, true);
  document.addEventListener('change', function(e){
    if (e.target.closest && e.target.closest('.seat')) setTimeout(updateReleaseButtonState, 10);
  }, true);

  if (cartList) {
    cartList.addEventListener('click', function(e){
      var item = e.target.closest('.cart-item, .cart-row, [data-seat-key]');
      if (!item) return;
      var prev = cartList.querySelector('.cart-item.selected, .cart-row.selected');
      if (prev) prev.classList.remove('selected');
      item.classList.add('selected');
      setTimeout(updateReleaseButtonState, 10);
    });

    try {
      var cartObserver = new MutationObserver(function () {
        setTimeout(updateReleaseButtonState, 10);
      });
      cartObserver.observe(cartList, { childList: true, subtree: true, attributes: true, attributeFilter: ['class', 'data-selected', 'data-reserved', 'data-seat-key'] });
      window._cashierCartObserver = cartObserver;
    } catch (e) {}
  }

// MutationObserver fallback — вставить в sell.php
(function(){
  var observerTarget = document.getElementById('seatmapContainer') || document.body;
  var obs = new MutationObserver(function(mutations){
    for (var m of mutations) {
      if (m.type === 'attributes' && m.target && m.target.classList && m.target.classList.contains('seat')) {
        // найдено изменение класса у места
        var el = m.target;
        if (el.classList.contains('selected')) {
          // триггерим локальную логику включения кнопки
          var evt = new CustomEvent('seat:changed', { detail: {
            seat_key: el.getAttribute('data-seat-key') || el.getAttribute('data-seat') || null,
            schedule_id: window.jsSession && window.jsSession.id ? window.jsSession.id : null,
            is_reserved: !!(el.getAttribute('data-reserved') === '1' || el.classList.contains('reserved')),
            element: el
          }});
          document.dispatchEvent(evt);
        } else {
          // если сняли выделение — тоже уведомим
          var evt2 = new CustomEvent('seat:changed', { detail: { seat_key: null, schedule_id: window.jsSession && window.jsSession.id ? window.jsSession.id : null, is_reserved: false, element: el }});
          document.dispatchEvent(evt2);
        }
      }
    }
  });
  obs.observe(observerTarget, { attributes: true, subtree: true, attributeFilter: ['class', 'data-reserved', 'data-seat-key'] });
})();
	
  setTimeout(updateReleaseButtonState, 100);
})();	
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>