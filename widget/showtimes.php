<?php
// JSON-афиша сеансов конкретного спектакля для вставки в Tilda.

require_once __DIR__ . '/../init.php';

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

$allowedOrigin = defined('TILDA_WIDGET_ORIGIN') ? (string)constant('TILDA_WIDGET_ORIGIN') : 'https://zhassahna.kz';
if ($allowedOrigin !== '') {
    header('Access-Control-Allow-Origin: ' . $allowedOrigin);
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

function showtimes_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function showtimes_price_values($raw): array
{
    if ($raw === null || $raw === '') {
        return [];
    }
    if (is_string($raw)) {
        $raw = json_decode($raw, true);
    }
    if (!is_array($raw)) {
        return [];
    }

    $values = [];
    $walk = function ($value, $key = null) use (&$walk, &$values): void {
        if (is_array($value)) {
            foreach ($value as $childKey => $childValue) {
                $walk($childValue, $childKey);
            }
            return;
        }
        if (is_numeric($value)) {
            $number = (float)$value;
            if ($number > 0 && (is_string($key) && in_array(strtolower($key), ['price', 'value', 'p'], true))) {
                $values[] = $number;
            }
        }
    };
    $walk($raw);

    if ($values === []) {
        foreach ($raw as $key => $value) {
            if (is_numeric($key) && (is_array($value) || is_numeric($value))) {
                $number = (float)$key;
                if ($number > 0) {
                    $values[] = $number;
                }
            }
        }
    }
    return $values;
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    showtimes_response(['success' => false, 'message' => 'База данных недоступна'], 500);
}

if (app_setting_enabled($pdo, 'system.maintenance_enabled')) {
    showtimes_response([
        'success' => true,
        'available' => false,
        'message' => 'Сайт временно обновляется. Скоро вернёмся.',
        'sessions' => [],
    ]);
}

$eventId = (int)($_GET['event_id'] ?? 0);
$eventTitle = trim((string)($_GET['event_title'] ?? $_GET['title'] ?? ''));
$eventUrl = trim((string)($_GET['event_url'] ?? $_GET['url'] ?? ''));

$where = [];
$params = [];
if ($eventId > 0) {
    $where[] = 'e.id = :event_id';
    $params[':event_id'] = $eventId;
} elseif ($eventTitle !== '') {
    $where[] = 'LOWER(TRIM(e.title)) = LOWER(TRIM(:event_title))';
    $params[':event_title'] = $eventTitle;
} elseif ($eventUrl !== '') {
    $eventPath = parse_url($eventUrl, PHP_URL_PATH) ?: $eventUrl;
    $eventSlug = trim(basename(rtrim($eventPath, '/')));
    $where[] = '(LOWER(TRIM(e.page_url)) IN (LOWER(TRIM(:event_url)), LOWER(TRIM(:event_path)), LOWER(TRIM(:event_slug))))';
    $params[':event_url'] = $eventUrl;
    $params[':event_path'] = $eventPath;
    $params[':event_slug'] = $eventSlug;
} else {
    showtimes_response([
        'success' => false,
        'available' => false,
        'message' => 'Не указан спектакль.',
        'sessions' => [],
    ], 400);
}

$whereSql = implode(' AND ', $where);
$eventStmt = $pdo->prepare("SELECT e.id, e.title FROM events e WHERE {$whereSql} LIMIT 1");
$eventStmt->execute($params);
$matchedEvent = $eventStmt->fetch(PDO::FETCH_ASSOC);
if (!$matchedEvent) {
    showtimes_response([
        'success' => true,
        'event_found' => false,
        'available' => false,
        'message' => '',
        'sessions' => [],
    ]);
}

$stmt = $pdo->prepare("SELECT
        s.id AS session_id,
        s.start_time,
        s.end_time,
        s.base_price,
        s.min_price,
        s.max_price,
        s.price_ranges,
        e.id AS event_id,
        e.title AS event_title
    FROM schedules s
    INNER JOIN events e ON e.id = s.event_id
    WHERE {$whereSql}
        AND s.status IN ('upcoming', 'active')
        AND s.start_time >= NOW()
        AND (s.sales_start_time IS NULL OR s.sales_start_time <= NOW())
        AND (s.sales_end_time IS NULL OR s.sales_end_time >= NOW())
    ORDER BY s.start_time ASC");
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$widgetBaseUrl = defined('PUBLIC_BASE_URL') ? rtrim((string)constant('PUBLIC_BASE_URL'), '/') : 'https://cabinet.zhassahna.kz';
$sessions = [];
$resolvedEvent = [
    'id' => (int)$matchedEvent['id'],
    'title' => (string)$matchedEvent['title'],
];

foreach ($rows as $row) {
    $prices = showtimes_price_values($row['price_ranges'] ?? null);
    if ($prices === [] && (float)$row['min_price'] > 0) {
        $prices[] = (float)$row['min_price'];
    }
    if ($prices === [] && (float)$row['base_price'] > 0) {
        $prices[] = (float)$row['base_price'];
    }
    $minPrice = $prices === [] ? null : min($prices);
    $maxPrice = $prices === [] ? null : max($prices);
    $timestamp = strtotime((string)$row['start_time']);
    $resolvedEvent = [
        'id' => (int)$row['event_id'],
        'title' => (string)$row['event_title'],
    ];

    $sessions[] = [
        'id' => (int)$row['session_id'],
        'start_time' => $row['start_time'],
        'end_time' => $row['end_time'],
        'date' => date('Y-m-d', $timestamp),
        'date_label' => date('d.m.Y', $timestamp),
        'time_label' => date('H:i', $timestamp),
        'price' => [
            'min' => $minPrice,
            'max' => $maxPrice,
            'label' => $minPrice === null
                ? 'Цена уточняется'
                : ($minPrice === $maxPrice
                    ? number_format($minPrice, 0, '.', ' ') . ' тг.'
                    : 'от ' . number_format($minPrice, 0, '.', ' ') . ' тг.'),
        ],
        'widget_url' => $widgetBaseUrl . '/tickets/widget.php?session_id=' . (int)$row['session_id'],
    ];
}

showtimes_response([
    'success' => true,
    'event_found' => true,
    'available' => $sessions !== [],
    'event' => $resolvedEvent,
    'message' => $sessions === [] ? 'Билетов на продажу нет' : '',
    'sessions' => $sessions,
]);
