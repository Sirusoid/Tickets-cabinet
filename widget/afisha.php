<?php
// cabinet.zhassahna.kz/widget/afisha.php
// Возвращает HTML-афишу для вставки в Тильду. Работает внутри Tickets cabinet.

require_once __DIR__ . '/../init.php';

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

$allowedOrigin = defined('TILDA_WIDGET_ORIGIN') ? (string)constant('TILDA_WIDGET_ORIGIN') : 'https://zhassahna.kz';
if ($allowedOrigin !== '') {
    header('Access-Control-Allow-Origin: ' . $allowedOrigin);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
}
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

if (!isset($pdo) || !($pdo instanceof PDO)) {
    echo json_encode(['html' => '<h3>Ошибка подключения к базе данных</h3>'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (app_setting_enabled($pdo, 'system.maintenance_enabled')) {
    echo json_encode(['html' => maintenance_response_html($pdo), 'maintenance' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

$widgetBaseUrl = defined('PUBLIC_BASE_URL') ? (string)constant('PUBLIC_BASE_URL') : 'https://cabinet.zhassahna.kz';
$widgetBaseUrl = rtrim($widgetBaseUrl, '/');

$now = (new DateTime())->format('Y-m-d H:i:s');
$stmt = $pdo->prepare("SELECT s.id AS session_id, s.start_time, s.base_price, s.price_ranges,
        e.id AS event_id, e.title AS event_title, e.image AS event_image, e.age_limit, e.page_url AS event_page_url,
        h.name AS hall_name
    FROM schedules s
    LEFT JOIN events e ON s.event_id = e.id
    LEFT JOIN halls h ON s.hall_id = h.id
    WHERE s.start_time >= :now AND s.status IN ('upcoming','active')
    ORDER BY s.start_time ASC
    LIMIT 50");
$stmt->execute([':now' => $now]);
$sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$html = '';

foreach ($sessions as $session) {
    $sessionId = (int)$session['session_id'];
    $eventId = (int)$session['event_id'];
    $name = trim((string)$session['event_title']);
    $hall = trim((string)$session['hall_name']);
    $image = trim((string)$session['event_image']);
    $ageLimit = (int)($session['age_limit'] ?? 0);
    $eventPageUrlRaw = trim((string)($session['event_page_url'] ?? ''));
    $eventPageUrl = '';
    if ($eventPageUrlRaw !== '') {
        if (preg_match('/^https?:\/\//i', $eventPageUrlRaw)) {
            $eventPageUrl = $eventPageUrlRaw;
        } else {
            $publicSiteUrl = defined('TILDA_WIDGET_ORIGIN')
                ? rtrim((string)constant('TILDA_WIDGET_ORIGIN'), '/')
                : 'https://zhassahna.kz';
            $eventPageUrl = $publicSiteUrl . '/' . ltrim($eventPageUrlRaw, '/');
        }
    }

    $dt = strtotime($session['start_time']);
    $day = date('d', $dt);
    $month = getRussianMonth(date('n', $dt));
    $time = date('H:i', $dt);

    $minPrice = null;
    $maxPrice = null;
    if (!empty($session['price_ranges'])) {
        $ranges = is_string($session['price_ranges']) ? json_decode($session['price_ranges'], true) : $session['price_ranges'];
        if (is_array($ranges)) {
            foreach ($ranges as $r) {
                if (!is_array($r)) continue;
                $p = isset($r['price']) ? (float)$r['price'] : null;
                if ($p === null && isset($r['value'])) $p = (float)$r['value'];
                if ($p === null) continue;
                if ($minPrice === null || $p < $minPrice) $minPrice = $p;
                if ($maxPrice === null || $p > $maxPrice) $maxPrice = $p;
            }
        }
    }
    if ($minPrice === null && $session['base_price'] > 0) {
        $minPrice = (float)$session['base_price'];
        $maxPrice = $minPrice;
    }

    $priceText = '';
    if ($minPrice !== null && $maxPrice !== null) {
        if ($minPrice == $maxPrice) {
            $priceText = number_format($minPrice, 0, '.', ' ') . ' тг.';
        } else {
            $priceText = 'от ' . number_format($minPrice, 0, '.', ' ') . ' тг.';
        }
    }

    $widgetUrl = $widgetBaseUrl . '/tickets/widget.php?session_id=' . $sessionId;

    $ageBadge = $ageLimit > 0
        ? '<div aria-label="Возрастное ограничение ' . $ageLimit . '+" style="position:absolute; top:14px; left:14px; z-index:2; width:52px; height:52px; display:flex; align-items:center; justify-content:center; box-sizing:border-box; border-radius:50%; background-color:#00adb5; color:#ffffff; font-family:TildaSans,Arial,sans-serif; font-size:18px; font-weight:700; line-height:1;">'
            . $ageLimit . '+'
            . '</div>'
        : '';
    $imgWrapper = '<div style="position:relative; background-color:hsl(0,0%,90%); height:365px; border-top-left-radius:30px; border-top-right-radius:30px; overflow:hidden;">' . $ageBadge . '</div>';
    if ($image !== '') {
        if (strncasecmp($image, 'http://', 7) === 0 || strncasecmp($image, 'https://', 8) === 0) {
            $imgUrl = htmlspecialchars($image, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        } else {
            $imgUrl = $widgetBaseUrl . '/uploads/images/' . htmlspecialchars($image, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        $imgWrapper = '<div style="position:relative; background-color:hsl(0,0%,90%); height:365px; border-top-left-radius:30px; border-top-right-radius:30px; overflow:hidden;">'
                    . '<img alt="' . htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" src="' . $imgUrl . '" style="display:block; width:100%; height:365px; object-fit:cover; border-top-left-radius:30px; border-top-right-radius:30px;" loading="lazy" />'
                    . $ageBadge
                    . '</div>';
    }

    $priceBlock = '';
    if ($priceText !== '') {
        $priceBlock = '<div style="font-family:TildaSans,Arial,sans-serif;color:#ffffff;font-weight:500;font-size:32px;margin-bottom:62px;">'
                    . htmlspecialchars($priceText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                    . '</div>';
    }

    $detailsButton = '';
    if ($eventPageUrl !== '') {
        $detailsButton = '<div class="t774__btn-wrapper" style="margin-top:0;">'
            . '<a href="' . htmlspecialchars($eventPageUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" class="zhassahna-details-btn" style="display:inline-block;text-decoration:none;">'
            . '<div class="t774__btn t-btn t-btn_sm" style="color:#222831;background-color:#00adb5;border-radius:30px;font-family:TildaSans,Arial,sans-serif;font-weight:700;text-transform:uppercase;padding:10px 20px;">'
            . 'ПОДРОБНЕЕ'
            . '</div></a></div>';
    }

    $html .= '
    <div class="t774__col t-col t-col_3 t-align_center t-item" style="margin-bottom:1.5%; margin-top:1.5%; position:relative;">
        <div class="t774__wrapper repertuar-button-buy" role="presentation" style="background-color:#393e46;border-radius:30px;box-shadow:0 0 30px rgba(0,0,0,0.1); position:relative; overflow:hidden;">
            <div class="t774__imgwrapper" style="padding-bottom:10%;">' . $imgWrapper . '</div>

            <div class="t774__content" style="height:420px; max-height:480px; display:flex; flex-direction:column; justify-content:space-between;">
                <div>
                    <div style="color:#eeeeee;font-size:16px;font-family:TildaSans,Arial,sans-serif;text-transform:uppercase;line-height:14px;padding-bottom:18px;"></div>

                    <div class="t774__textwrapper" style="display:flex; flex-direction: column;align-items:center;">

                        <div class="t774__uptitle" style="color:#eeeeee;font-size:22px;font-weight:700;font-family:TildaSans,Arial,sans-serif;text-transform:uppercase;text-align:center;">
                            ' . htmlspecialchars($day . ' ' . $month, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '<br>' . htmlspecialchars($time, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '
                        </div>

                        <div class="t774__title" style="color:#eeeeee;font-size:25px;font-family:TildaSans,Arial,sans-serif;text-transform:uppercase;padding:20px 10px 0;text-align:center;">
                            ' . htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '
                        </div>
                    </div>
                </div>

                <div style="display:flex; flex-direction:column; align-items:center;">
                    ' . $priceBlock . '
                    <div style="display:flex; flex-direction:column; align-items:center; gap:8px;">
                    <div class="t774__btn-wrapper" style="margin-top:0;">
                        <a href="#" class="zhassahna-buy-btn" data-session-id="' . $sessionId . '" data-widget-url="' . htmlspecialchars($widgetUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">
                            <div class="t774__btn t-btn t-btn_sm" style="color:#222831;background-color:#00adb5;border-radius:30px;font-family:TildaSans,Arial,sans-serif;font-weight:700;text-transform:uppercase;padding:10px 20px;">
                                КУПИТЬ БИЛЕТ
                            </div>
                        </a>
                    </div>
                    ' . $detailsButton . '
                    </div>
                </div>
            </div>
        </div>
    </div>';
}

if ($html === '') {
    $html = '<h3>Нет предстоящих мероприятий</h3>';
}

echo json_encode(['html' => $html], JSON_UNESCAPED_UNICODE);

function getRussianMonth($monthNumber) {
    $months = [
        1 => 'января', 2 => 'февраля', 3 => 'марта', 4 => 'апреля',
        5 => 'мая', 6 => 'июня', 7 => 'июля', 8 => 'августа',
        9 => 'сентября', 10 => 'октября', 11 => 'ноября', 12 => 'декабря'
    ];
    return $months[(int)$monthNumber] ?? '';
}
