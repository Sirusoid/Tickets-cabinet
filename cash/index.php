<?php
// cash/index.php
// Касса — список сеансов с фильтрами и динамической подгрузкой свободных мест (AJAX POST)
// Исправления:
// - клиентский рендер использует server-provided min_price/max_price и notes
// - при AJAX-обновлении бейдж статуса получает корректный цвет (как при серверном рендере)
// - AJAX остаётся POST к /ajax/cash.php?action=sessions_list
// - добавлены колонки "Продано" и "Резерв", колонка "Места" переименована в "Доступно"
// - подсчёт резерва и продано выполняется в ajax/cash.php (единственный источник правды)

require_once __DIR__ . '/../init.php';
require_login();

$page_scripts = $page_scripts ?? [];

$halls = function_exists('db_fetch_all') ? db_fetch_all('SELECT id, name FROM halls ORDER BY name') : [];
$events = function_exists('db_fetch_all') ? db_fetch_all('SELECT id, title FROM events ORDER BY title') : [];

$use_sidebar = true;
$active_menu = 'cash';
$page_title_meta = 'Касса';
$panel_title = 'Касса';
$panel_subtitle = 'Сеансы для продажи';
$panel_actions = [
  ['href'=>'/dashboard.php','label'=>'На главную','class'=>'btn btn-primary btn-sm']
];

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/panel.php';

// --- Helpers (self-contained copies) ---
if (!function_exists('column_exists')) {
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
}
if (!function_exists('analyze_price_ranges')) {
    function analyze_price_ranges($json) {
        $res = ['min_price' => null, 'max_price' => null, 'total_seats' => null];
        if (empty($json)) return $res;
        $data = json_decode($json, true);
        if (!is_array($data)) return $res;

        $prices = [];
        $totalSeats = 0;
        $hasCounts = false;

        $processItem = function($k, $v) use (&$prices, &$totalSeats, &$hasCounts) {
            if (is_numeric($k)) $prices[] = (float)$k;
            if (is_array($v)) {
                if (isset($v['price']) && is_numeric($v['price'])) $prices[] = (float)$v['price'];
                if (isset($v['count']) && is_numeric($v['count'])) { $totalSeats += (int)$v['count']; $hasCounts = true; }
                if (isset($v['seats']) && is_array($v['seats'])) { $totalSeats += count($v['seats']); $hasCounts = true; }
                if (isset($v['ranges']) && is_array($v['ranges'])) {
                    foreach ($v['ranges'] as $r) {
                        if (!is_array($r)) continue;
                        if (isset($r['from']) && isset($r['to']) && is_numeric($r['from']) && is_numeric($r['to'])) {
                            $from = (int)$r['from'];
                            $to = (int)$r['to'];
                            if ($to >= $from) { $totalSeats += ($to - $from + 1); $hasCounts = true; }
                        }
                    }
                }
                if (isset($v['seat_map']) && is_array($v['seat_map'])) {
                    foreach ($v['seat_map'] as $row) {
                        if (isset($row['seats']) && is_array($row['seats'])) $totalSeats += count($row['seats']);
                        elseif (isset($row['start']) && isset($row['end'])) $totalSeats += max(0, (int)$row['end'] - (int)$row['start'] + 1);
                    }
                    $hasCounts = true;
                }
            } else {
                if (is_numeric($v)) $prices[] = (float)$v;
            }
        };

        $isAssoc = array_keys($data) !== range(0, count($data) - 1);

        if ($isAssoc) {
            foreach ($data as $k => $v) $processItem($k, $v);
        } else {
            foreach ($data as $item) {
                if (is_array($item)) $processItem(null, $item);
                elseif (is_numeric($item)) $prices[] = (float)$item;
            }
        }

        if (!empty($prices)) {
            $res['min_price'] = min($prices);
            $res['max_price'] = max($prices);
        }
        if ($hasCounts && $totalSeats > 0) $res['total_seats'] = $totalSeats;

        return $res;
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
        if (isset($data['seats']) && is_array($data['seats'])) return count($data['seats']);
        if (array_values($data) === $data && is_array($data)) return count($data);
        return null;
    }
}

// --- Initial server render (GET only for initial UI state) ---
$filter_hall = isset($_GET['hall_id']) && $_GET['hall_id'] !== '' ? (int)$_GET['hall_id'] : null;
$filter_event = isset($_GET['event_id']) && $_GET['event_id'] !== '' ? (int)$_GET['event_id'] : null;
$filter_date = isset($_GET['date']) && $_GET['date'] !== '' ? $_GET['date'] : null;
$filter_status = isset($_GET['status']) ? (string)$_GET['status'] : '';
$sort = isset($_GET['sort']) ? (string)$_GET['sort'] : 'date_desc';

if (function_exists('schedule_refresh_statuses') && isset($pdo) && $pdo instanceof PDO) {
  schedule_refresh_statuses($pdo);
}

$where = [];
$params = [];
if ($filter_hall) { $where[] = 's.hall_id = :hall_id'; $params[':hall_id'] = $filter_hall; }
if ($filter_event) { $where[] = 's.event_id = :event_id'; $params[':event_id'] = $filter_event; }
if ($filter_date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filter_date)) { $where[] = "DATE(s.start_time) = :date"; $params[':date'] = $filter_date; }

$order_by = $sort === 'date_asc' ? 's.start_time ASC' : 's.start_time DESC';
$where_sql = !empty($where) ? ' WHERE ' . implode(' AND ', $where) : '';

$has_price_ranges = column_exists($pdo, 'schedules', 'price_ranges');
$has_base_price = column_exists($pdo, 'schedules', 'base_price');
$has_seat_map = column_exists($pdo, 'schedules', 'seat_map');

$selectFields = [
    "s.id",
    "s.event_id",
    "s.hall_id",
    "s.start_time",
    "s.end_time",
    "s.status",
];

if ($has_base_price) $selectFields[] = "s.base_price";
if ($has_price_ranges) $selectFields[] = "s.price_ranges";
if ($has_seat_map) $selectFields[] = "COALESCE(s.seat_map, '') AS seat_map";
$selectFields[] = "COALESCE(s.notes, '') AS notes";
$selectFields[] = "COALESCE(e.title, '') AS event_title";
$selectFields[] = "COALESCE(h.name, '') AS hall_name";

$selectSql = implode(', ', $selectFields);

$sql = "SELECT {$selectSql}
        FROM schedules s
        LEFT JOIN events e ON e.id = s.event_id
        LEFT JOIN halls h ON h.id = s.hall_id
        {$where_sql}
        ORDER BY {$order_by}
        LIMIT 500";

try {
    if (empty($params)) {
        if (function_exists('db_fetch_all')) {
            $schedules_raw = db_fetch_all($sql);
        } else {
            $stmt = $pdo->query($sql);
            $schedules_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } else {
        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        $schedules_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log("cash/index.php fetch error: " . $e->getMessage());
    $schedules_raw = [];
}

// --- IMPORTANT: do not compute sold/reserved here to avoid duplication with ajax/cash.php
// Prepare schedules for initial render with price hints and placeholders; AJAX will fill real sold/reserved/available
$schedules = [];
foreach ($schedules_raw as $s) {
    $computed = function_exists('schedule_resolved_status') ? schedule_resolved_status($s) : 'upcoming';
    if (!in_array($computed, ['upcoming', 'active'], true)) continue;
    if ($filter_status !== '' && $computed !== $filter_status) continue;

    $s['_computed_status'] = $computed;

    // compute min/max price hints (safe)
    $minPrice = null; $maxPrice = null;
    if ($has_price_ranges && !empty($s['price_ranges'])) {
        $prJson = is_string($s['price_ranges']) ? $s['price_ranges'] : json_encode($s['price_ranges'], JSON_UNESCAPED_UNICODE);
        $an = analyze_price_ranges($prJson);
        if ($an['min_price'] !== null) $minPrice = (int)$an['min_price'];
        if ($an['max_price'] !== null) $maxPrice = (int)$an['max_price'];
    }
    if ($minPrice === null && $maxPrice === null && $has_base_price && isset($s['base_price']) && $s['base_price'] !== null && $s['base_price'] !== '') {
        $val = (int) round((float)$s['base_price']);
        $minPrice = $maxPrice = $val;
    }
    $s['min_price'] = $minPrice;
    $s['max_price'] = $maxPrice;

    // placeholders — AJAX will fill real values
    $s['sold_count'] = 0;
    $s['reserved_count'] = 0;
    $s['total_seats'] = null;
    $s['available'] = null;

    $schedules[] = $s;
}
?>
<link rel="stylesheet" href="/assets/css/schedule.css">
<link rel="stylesheet" href="/assets/css/cashier.css">

<div class="card container-full" style="padding: 18px;">

    <form id="filterForm" style="display:flex; gap:8px; margin-bottom:12px; align-items:center;" method="get" action="">
      <select id="filter_hall" name="hall_id" class="form-control" style="width:220px;">
        <option value="">Все залы</option>
        <?php foreach ($halls as $h): ?>
          <option value="<?= h($h['id']) ?>" <?= ($filter_hall == $h['id']) ? 'selected' : '' ?>><?= h($h['name']) ?></option>
        <?php endforeach; ?>
      </select>

      <select id="filter_event" name="event_id" class="form-control" style="width:220px;">
        <option value="">Все события</option>
        <?php foreach ($events as $ev): ?>
          <option value="<?= h($ev['id']) ?>" <?= ($filter_event == $ev['id']) ? 'selected' : '' ?>><?= h($ev['title']) ?></option>
        <?php endforeach; ?>
      </select>

      <input type="date" id="filter_date" name="date" value="<?= h($filter_date ?? '') ?>" class="form-control" style="width:160px;" />

      <select id="filter_status" name="status" class="form-control" style="width:160px;">
        <option value="">Все статусы</option>
        <option value="active" <?= $filter_status === 'active' ? 'selected' : '' ?>>Активный</option>
        <option value="upcoming" <?= $filter_status === 'upcoming' ? 'selected' : '' ?>>Ожидается</option>
      </select>

      <select id="filter_sort" name="sort" class="form-control" style="width:160px;">
        <option value="date_desc" <?= $sort === 'date_desc' ? 'selected' : '' ?>>Сначала новые</option>
        <option value="date_asc" <?= $sort === 'date_asc' ? 'selected' : '' ?>>Сначала старые</option>
      </select>

      <button id="btnFilter" type="button" class="btn btn-secondary">Применить</button>
      <a id="btnClear" class="btn btn-ghost" href="/cash/index.php">Сброс</a>
    </form>

    <div id="schedulesContainer">
      <table class="table admin-table table--compact" id="schedulesTable">
        <thead>
          <tr>
            <th style="width:6%;">#</th>
            <th style="width:12%;">Дата</th>
            <th style="width:18%;">Событие</th>
			<th style="width:10%;">Статус</th>
            <th style="width:8%;">Доступно</th>
            <th style="width:8%;">Продано</th>
            <th style="width:8%;">Резерв</th>
            <th style="width:8%;">Начало</th>
            <th style="width:8%;">Окончание</th>
            <th style="width:12%;">Зал</th>
            <th style="width:8%;">Цена</th>
            <th style="width:10%;">Примечание</th>
            <th class="actions-col" style="width:6%;">Действия</th>
          </tr>
        </thead>
        <tbody id="schedulesTbody">
          <?php if (empty($schedules)): ?>
            <tr><td colspan="13" style="text-align:center; color:#666; padding:18px;">Сеансов не найдено</td></tr>
          <?php else: ?>
            <?php foreach ($schedules as $s): ?>
              <?php
                $startTs = !empty($s['start_time']) ? strtotime($s['start_time']) : false;
                $endTs = !empty($s['end_time']) ? strtotime($s['end_time']) : false;
                $date = $startTs ? date('d/m/Y', $startTs) : '';
                $startTime = $startTs ? date('H:i', $startTs) : '';
                $endTime = $endTs ? date('H:i', $endTs) : '';
                $status = $s['_computed_status'] ?? 'upcoming';

                $status_label = 'Ожидается';
                $status_color = '#2d6fa3';
                if ($status === 'active') { $status_label = 'Активный'; $status_color = '#1a7f37'; }
                elseif ($status === 'draft') { $status_label = 'Черновик'; $status_color = '#b36b00'; }
                elseif ($status === 'archive') { $status_label = 'В архиве'; $status_color = '#555555'; }
                elseif ($status === 'cancelled') { $status_label = 'Отменён'; $status_color = '#a00'; }

                $normColor = null;
                if (preg_match('/^#?([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$/', $status_color)) {
                  $normColor = (strpos($status_color, '#') === 0) ? $status_color : ('#' . $status_color);
                }
                $textColor = $normColor ? ((hexdec(substr($normColor,1,2))*0.299 + hexdec(substr($normColor,3,2))*0.587 + hexdec(substr($normColor,5,2))*0.114) > 186 ? '#000' : '#fff') : '#fff';
                $styleAttr = $normColor ? ' style="background:'.h($normColor).'; color:'.h($textColor).'; border-color:'.h($normColor).';"' : '';

                $event_title_attr = isset($s['event_title']) ? h($s['event_title']) : '';

                // seats loader (will be filled by AJAX)
                $seatsDisplay = '<span class="seats-loader" data-sid="'.h($s['id']).'"><span class="loader" aria-hidden="true"></span></span>';

                // placeholders (AJAX will update these)
                $soldCount = isset($s['sold_count']) ? (int)$s['sold_count'] : 0;
                $reservedCount = isset($s['reserved_count']) ? (int)$s['reserved_count'] : 0;

                $minPrice = $s['min_price'] ?? null;
                $maxPrice = $s['max_price'] ?? null;
                $priceDisplay = '<span style="color:#888">—</span>';
                if ($minPrice !== null && $maxPrice !== null) {
                  $minInt = (int)$minPrice;
                  $maxInt = (int)$maxPrice;
                  if ($minInt === $maxInt) $priceDisplay = h((string)$minInt);
                  else $priceDisplay = h((string)$minInt) . ' — ' . h((string)$maxInt);
                }

                $rawNote = $s['notes'] ?? '';
                $noteDisplay = $rawNote !== '' ? h($rawNote) : '<span style="color:#888">—</span>';
              ?>
              <tr data-id="<?= h($s['id']) ?>" data-event-title="<?= $event_title_attr ?>" style="cursor:default;">
                <td style="padding:10px; vertical-align:middle;"><?= h($s['id']) ?></td>
                <td style="padding:10px; vertical-align:middle;"><?= h($date) ?></td>
                <td style="padding:10px; vertical-align:middle;"><?= h($s['event_title'] ?? ('#' . $s['event_id'])) ?></td>
				<td style="padding:10px; vertical-align:middle;">
                  <span class="badge"<?= $styleAttr ?>><?= h($status_label) ?></span>
                </td>

                <td class="col-seats" style="padding:10px; vertical-align:middle;"><?= $seatsDisplay ?></td>

                <td class="col-sold" style="padding:10px; vertical-align:middle; text-align:center;"><?= h((string)$soldCount) ?></td>
                <td class="col-reserved" style="padding:10px; vertical-align:middle; text-align:center;"><?= h((string)$reservedCount) ?></td>

                <td style="padding:10px; vertical-align:middle;"><?= h($startTime) ?></td>
                <td style="padding:10px; vertical-align:middle;"><?= h($endTime) ?></td>
                <td style="padding:10px; vertical-align:middle;"><?= h($s['hall_name'] ?? ('#' . $s['hall_id'])) ?></td>
                <td style="padding:10px; vertical-align:middle; text-align:right;"><?= $priceDisplay ?></td>
                <td style="padding:10px; vertical-align:middle;"><?= $noteDisplay ?></td>
                <td class="actions-col" style="padding:10px; vertical-align:middle;">
                  <div class="action-buttons">
                    <a class="btn btn-primary btn-sm" href="/cash/sell.php?session_id=<?= h($s['id']) ?>">Продажа</a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
</div>

<script>
(function(){
  function esc(s){ return String(s === null || s === undefined ? '' : s); }
  function toIntSafe(v){ if (v === null || v === undefined || v === '') return 0; var n = parseInt(v,10); return isNaN(n)?0:n; }

  function readFilters(){
    return {
      hall_id: document.getElementById('filter_hall').value || '',
      event_id: document.getElementById('filter_event').value || '',
      date: document.getElementById('filter_date').value || '',
      status: document.getElementById('filter_status').value || '',
      sort: document.getElementById('filter_sort').value || 'date_desc'
    };
  }

  function debounce(fn, wait){
    var t;
    return function(){ var args = arguments; clearTimeout(t); t = setTimeout(function(){ fn.apply(null,args); }, wait); };
  }

  function showTableLoading(){
    var tbody = document.getElementById('schedulesTbody');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="13" style="text-align:center; padding:18px;"><span class="seats-loader"><span class="loader" aria-hidden="true"></span></span> Загрузка...</td></tr>';
  }

  function badgeStyleForStatus(status){
    var label = 'Ожидается', color = '#2d6fa3';
    if (status === 'active'){ label='Активный'; color='#1a7f37'; }
    else if (status === 'draft'){ label='Черновик'; color='#b36b00'; }
    else if (status === 'archive'){ label='В архиве'; color='#555555'; }
    else if (status === 'cancelled'){ label='Отменён'; color='#a00'; }
    var norm = /^#?[0-9A-Fa-f]{3,6}$/.test(color) ? ((color.indexOf('#')===0)?color:'#'+color) : null;
    var text = '#fff';
    if (norm) {
      try {
        var r = parseInt(norm.substr(1,2),16), g = parseInt(norm.substr(3,2),16), b = parseInt(norm.substr(5,2),16);
        text = ((r*0.299 + g*0.587 + b*0.114) > 186) ? '#000' : '#fff';
      } catch(e){ text='#fff'; }
    }
    var style = norm ? ' style="background:'+norm+'; color:'+text+'; border-color:'+norm+';"' : '';
    return { label: label, style: style };
  }

  function renderSchedules(rows){
    var tbody = document.getElementById('schedulesTbody');
    if (!tbody) return;
    if (!Array.isArray(rows) || rows.length === 0){
      tbody.innerHTML = '<tr><td colspan="13" style="text-align:center; color:#666; padding:18px;">Сеансов не найдено</td></tr>';
      return;
    }
    var html = '';
    rows.forEach(function(s){
      var startTs = s.start_time ? new Date(s.start_time) : null;
      var endTs = s.end_time ? new Date(s.end_time) : null;
      var date = startTs ? ('0'+startTs.getDate()).slice(-2)+'/'+('0'+(startTs.getMonth()+1)).slice(-2)+'/'+startTs.getFullYear() : '';
      var startTime = startTs ? ('0'+startTs.getHours()).slice(-2)+':'+('0'+startTs.getMinutes()).slice(-2) : '';
      var endTime = endTs ? ('0'+endTs.getHours()).slice(-2)+':'+('0'+endTs.getMinutes()).slice(-2) : '';

      var statusKey = s._computed_status || s.status || 'upcoming';
      var badge = badgeStyleForStatus(statusKey);

      // AVAILABLE: prefer s.available, fallback to s.free, fallback compute from total - sold - reserved, else dash
      var availableDisplay = '<span style="color:#888">—</span>';
      var avail = null;
      if (typeof s.available !== 'undefined' && s.available !== null) avail = s.available;
      else if (typeof s.free !== 'undefined' && s.free !== null) avail = s.free;
      else if (typeof s.total_seats !== 'undefined' && s.total_seats !== null) {
        var total = toIntSafe(s.total_seats);
        var sold = toIntSafe(s.sold_count);
        var reserved = toIntSafe(s.reserved_count);
        avail = Math.max(0, total - sold - reserved);
      }
      if (avail !== null) availableDisplay = '<span class="seats-number">'+esc(String(avail))+'</span>';

      var soldVal = (typeof s.sold_count !== 'undefined') ? s.sold_count : ((typeof s._sold_count !== 'undefined') ? s._sold_count : 0);
      var reservedVal = (typeof s.reserved_count !== 'undefined') ? s.reserved_count : ((typeof s._reserved_count !== 'undefined') ? s._reserved_count : 0);
      soldVal = toIntSafe(soldVal);
      reservedVal = toIntSafe(reservedVal);

      var soldCell = '<span class="sold-number" data-sid="'+esc(s.id)+'">'+esc(String(soldVal))+'</span>';
      var reservedCell = '<span class="reserved-number" data-sid="'+esc(s.id)+'">'+esc(String(reservedVal))+'</span>';

      var minP = (typeof s.min_price !== 'undefined' && s.min_price !== null) ? s.min_price : null;
      var maxP = (typeof s.max_price !== 'undefined' && s.max_price !== null) ? s.max_price : null;
      var priceDisplay = '<span style="color:#888">—</span>';
      if (minP !== null && maxP !== null){
        var minInt = parseInt(minP,10), maxInt = parseInt(maxP,10);
        priceDisplay = (minInt===maxInt) ? esc(String(minInt)) : esc(String(minInt)) + ' — ' + esc(String(maxInt));
      }

      var rawNote = s.notes || '';
      var noteDisplay = rawNote !== '' ? esc(rawNote) : '<span style="color:#888">—</span>';

      html += '<tr data-id="'+esc(s.id)+'" data-event-title="'+esc(s.event_title||('#'+s.event_id))+'" style="cursor:default;">' +
                '<td style="padding:10px; vertical-align:middle;">'+esc(s.id)+'</td>' +
                '<td style="padding:10px; vertical-align:middle;">'+esc(date)+'</td>' +
                '<td style="padding:10px; vertical-align:middle;">'+esc(s.event_title || ('#'+s.event_id))+'</td>' +
		        '<td style="padding:10px; vertical-align:middle;"><span class="badge"'+badge.style+'>'+esc(badge.label)+'</span></td>' +
                '<td class="col-seats" style="padding:10px; vertical-align:middle;">'+availableDisplay+'</td>' +
                '<td class="col-sold" style="padding:10px; vertical-align:middle; text-align:center;">'+soldCell+'</td>' +
                '<td class="col-reserved" style="padding:10px; vertical-align:middle; text-align:center;">'+reservedCell+'</td>' +
                '<td style="padding:10px; vertical-align:middle;">'+esc(startTime)+'</td>' +
                '<td style="padding:10px; vertical-align:middle;">'+esc(endTime)+'</td>' +
                '<td style="padding:10px; vertical-align:middle;">'+esc(s.hall_name || ('#'+s.hall_id))+'</td>' +
                '<td style="padding:10px; vertical-align:middle; text-align:right;">'+priceDisplay+'</td>' +
                '<td style="padding:10px; vertical-align:middle;">'+noteDisplay+'</td>' +
                '<td class="actions-col" style="padding:10px; vertical-align:middle;"><div class="action-buttons"><a class="btn btn-primary btn-sm" href="/cash/sell.php?session_id='+esc(s.id)+'">Продажа</a></div></td>' +
              '</tr>';
    });
    tbody.innerHTML = html;
  }

  // single source: sessions_list
  window.fetchSessions = function(filters){
    showTableLoading();
    var payload = Object.assign({}, filters || {});
    payload.action = 'sessions_list';
    payload.per_page = 500;
    payload.sort = payload.sort === 'date_asc' ? 'date_asc' : 'date_desc';

    fetch('/ajax/cash.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify(payload),
      credentials: 'same-origin'
    }).then(function(resp){ return resp.json(); })
    .then(function(json){
      if (!json || !json.success || !Array.isArray(json.data)){
        var tbody = document.getElementById('schedulesTbody');
        if (tbody) tbody.innerHTML = '<tr><td colspan="13" style="text-align:center; color:#666; padding:18px;">Ошибка загрузки сеансов</td></tr>';
        return;
      }
      // render everything from this single response (includes available, sold_count, reserved_count)
      renderSchedules(json.data);

      // update URL
      var qsObj = readFilters();
      var qs = Object.keys(qsObj).filter(function(k){ return qsObj[k] !== ''; }).map(function(k){ return encodeURIComponent(k)+'='+encodeURIComponent(qsObj[k]); }).join('&');
      history.replaceState(null, '', window.location.pathname + (qs ? ('?'+qs) : ''));
    }).catch(function(){
      var tbody = document.getElementById('schedulesTbody');
      if (tbody) tbody.innerHTML = '<tr><td colspan="13" style="text-align:center; color:#666; padding:18px;">Ошибка загрузки сеансов</td></tr>';
    });
  };

  // remove fetchFreeSeats usage: we rely on sessions_list only

  var debouncedFetch = debounce(function(){ window.fetchSessions(readFilters()); }, 300);

  document.getElementById('filter_hall').addEventListener('change', debouncedFetch);
  document.getElementById('filter_event').addEventListener('change', debouncedFetch);
  document.getElementById('filter_date').addEventListener('change', debouncedFetch);
  document.getElementById('filter_status').addEventListener('change', debouncedFetch);
  document.getElementById('filter_sort').addEventListener('change', debouncedFetch);

  document.getElementById('btnFilter').addEventListener('click', function(){ window.fetchSessions(readFilters()); });

  document.getElementById('btnClear').addEventListener('click', function(){ setTimeout(function(){ window.fetchSessions(readFilters()); }, 50); });

  document.addEventListener('DOMContentLoaded', function(){
    // single initial request
    window.fetchSessions(readFilters());
  });

  document.addEventListener('click', function(e){
    var row = e.target.closest('tr[data-id]');
    if (!row) return;
    if (e.target.closest('a, button, input, select, label')) return;
    var id = row.getAttribute('data-id');
    if (!id) return;
    window.location.href = '/cash/sell.php?session_id=' + encodeURIComponent(id);
  });

})();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>