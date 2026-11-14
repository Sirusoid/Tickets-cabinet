<?php
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/reporting.php';
require_once __DIR__ . '/../includes/general_admission.php';
require_login();

if (!user_has_permission($pdo, 'sales_analytics', false)) {
    http_response_code(403);
    exit('Доступ запрещён.');
}

if (!function_exists('sales_analytics_seat_map_count')) {
    function sales_analytics_seat_map_count($raw): ?int
    {
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }
        if (!is_array($raw)) {
            return null;
        }
        if (isset($raw['seats']) && is_array($raw['seats'])) {
            return count($raw['seats']);
        }

        $count = 0;
        foreach (($raw['rows'] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $rowCount = 0;
            foreach (($row['segments'] ?? []) as $segment) {
                if (!is_array($segment)) {
                    continue;
                }
                if (isset($segment['start'], $segment['end']) && is_numeric($segment['start']) && is_numeric($segment['end'])) {
                    $rowCount += max(0, (int)$segment['end'] - (int)$segment['start'] + 1);
                } elseif (isset($segment['seats']) && is_array($segment['seats'])) {
                    $rowCount += count($segment['seats']);
                } elseif (isset($segment['seatCount']) && is_numeric($segment['seatCount'])) {
                    $rowCount += max(0, (int)$segment['seatCount']);
                }
            }
            if ($rowCount > 0) {
                $count += $rowCount;
            } elseif (isset($row['seats']) && is_array($row['seats'])) {
                $count += count($row['seats']);
            } elseif (isset($row['start'], $row['end']) && is_numeric($row['start']) && is_numeric($row['end'])) {
                $count += max(0, (int)$row['end'] - (int)$row['start'] + 1);
            } elseif (isset($row['seatCount']) && is_numeric($row['seatCount'])) {
                $count += max(0, (int)$row['seatCount']);
            }
        }

        return $count > 0 ? $count : null;
    }
}

if (!function_exists('sales_analytics_price_range_count')) {
    function sales_analytics_price_range_count($raw): ?int
    {
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }
        if (!is_array($raw)) {
            return null;
        }

        $total = 0;
        $found = false;
        foreach ($raw as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            foreach (['count', 'qty'] as $key) {
                if (isset($entry[$key]) && is_numeric($entry[$key])) {
                    $total += max(0, (int)$entry[$key]);
                    $found = true;
                    break;
                }
            }
            if (isset($entry['seats']) && is_array($entry['seats'])) {
                $total += count($entry['seats']);
                $found = true;
            }
            foreach (($entry['ranges'] ?? []) as $range) {
                if (is_array($range) && isset($range['from'], $range['to']) && is_numeric($range['from']) && is_numeric($range['to'])) {
                    $total += max(0, (int)$range['to'] - (int)$range['from'] + 1);
                    $found = true;
                }
            }
            if (!empty($entry['seat_map'])) {
                $seatMapCount = sales_analytics_seat_map_count($entry['seat_map']);
                if ($seatMapCount !== null) {
                    $total += $seatMapCount;
                    $found = true;
                }
            }
        }

        return $found && $total > 0 ? $total : null;
    }
}

if (!function_exists('sales_analytics_recommendation')) {
    function sales_analytics_recommendation(array $item, int $daysUntil): array
    {
        $allocated = $item['allocated'];
        if ($allocated === null) {
            return ['muted', 'Не удалось определить квоту. Проверьте схему зала и выделенные места.'];
        }
        if ($allocated <= 0) {
            return ['warning', 'В кабинете нет выделенных мест. Проверьте цены и настройки схемы перед стартом продаж.'];
        }

        $onlineSold = (int)$item['online_sold'];
        $onlineShare = min(100, ($onlineSold / $allocated) * 100);
        $remaining = max(0, $allocated - (int)$item['sold_total']);
        if ($remaining === 0) {
            return ['success', 'Квота кабинета занята. Сверьте остатки на других площадках и при наличии свободных мест добавьте следующую партию на сайт.'];
        }
        if ($onlineShare >= 70) {
            return ['success', 'Сильный спрос: сайт реализовал большую часть своей квоты. Рассмотрите постепенный перенос следующей небольшой партии мест с других площадок.'];
        }

        $recentPace = (int)$item['online_recent'] / 14;
        $daysToSellRemaining = $recentPace > 0 ? $remaining / $recentPace : null;
        if ($onlineShare >= 40 && $daysToSellRemaining !== null && $daysToSellRemaining <= max(1, $daysUntil * 0.7)) {
            return ['success', 'Темп продаж позволяет быстро освоить остаток квоты. Можно тестово добавить на сайт часть мест, сверяя остатки партнёров.'];
        }
        if ($daysUntil <= 7 && $onlineShare < 40) {
            return ['warning', 'До спектакля меньше недели, а онлайн-продажи пока невысоки. Не сокращайте другие каналы; проверьте продвижение и цену билетов.'];
        }
        if ($recentPace <= 0) {
            return ['muted', 'За последние 14 дней онлайн-продаж не было. Пока сохраните распределение мест и проверьте спрос и продвижение.'];
        }
        if ($onlineShare >= 40) {
            return ['steady', 'Продажи идут, но запас ещё есть. Сохраните текущую квоту и оцените темп снова через несколько дней.'];
        }

        return ['muted', 'Спрос на сайте пока невысокий. Не переносите квоту с других площадок; проверьте цену, описание и продвижение спектакля.'];
    }
}

$page_title_meta = 'Аналитика продаж';
$panel_title = 'Аналитика';
$panel_subtitle = 'Онлайн-продажи относительно квоты мест, выделенных в кабинете';
$active_menu = 'sales_analytics';
$use_sidebar = true;
$page_styles = ['/assets/css/reports.css?v=sales-analytics-20261114-1'];

$performances = [];
$errorText = '';
$now = time();
$salesWindowDays = 14;

try {
    $performances = db_fetch_all(
        "SELECT s.id, s.event_id, s.hall_id, s.start_time, s.status, s.capacity, s.seat_map, s.price_ranges,
                e.title AS event_title, h.name AS hall_name, h.seat_map AS hall_seat_map
         FROM schedules s
         LEFT JOIN events e ON e.id = s.event_id
         LEFT JOIN halls h ON h.id = s.hall_id
         WHERE s.start_time >= NOW()
           AND COALESCE(s.status, '') NOT IN ('cancelled', 'draft', 'archive')
         ORDER BY s.start_time ASC"
    );

    $scheduleIds = array_values(array_filter(array_map(static function ($performance) {
        return (int)($performance['id'] ?? 0);
    }, $performances)));
    $salesBySchedule = [];
    $seatsByHall = [];

    if ($scheduleIds !== []) {
        $placeholders = implode(',', array_fill(0, count($scheduleIds), '?'));
        $salesRows = db_fetch_all(
            "SELECT schedule_id,
                    COUNT(*) AS sold_total,
                    SUM(CASE WHEN LOWER(COALESCE(channel, '')) IN ('web', 'mobile') THEN 1 ELSE 0 END) AS online_sold,
                    SUM(CASE WHEN LOWER(COALESCE(channel, '')) IN ('web', 'mobile')
                                  AND purchased_at >= DATE_SUB(NOW(), INTERVAL 14 DAY) THEN 1 ELSE 0 END) AS online_recent
             FROM tickets
             WHERE schedule_id IN ($placeholders)
               AND payment_status = 'paid'
               AND status <> 'cancelled'
               AND COALESCE(refund_status, 'none') <> 'refunded'
             GROUP BY schedule_id",
            $scheduleIds
        );
        foreach ($salesRows as $salesRow) {
            $salesBySchedule[(int)$salesRow['schedule_id']] = [
                'sold_total' => (int)($salesRow['sold_total'] ?? 0),
                'online_sold' => (int)($salesRow['online_sold'] ?? 0),
                'online_recent' => (int)($salesRow['online_recent'] ?? 0),
            ];
        }

        $hallIds = array_values(array_unique(array_filter(array_map(static function ($performance) {
            return (int)($performance['hall_id'] ?? 0);
        }, $performances))));
        if ($hallIds !== []) {
            $hallPlaceholders = implode(',', array_fill(0, count($hallIds), '?'));
            try {
                $seatRows = db_fetch_all(
                    "SELECT hall_id, COUNT(*) AS seat_count
                     FROM seats
                     WHERE hall_id IN ($hallPlaceholders)
                     GROUP BY hall_id",
                    $hallIds
                );
                foreach ($seatRows as $seatRow) {
                    $seatsByHall[(int)$seatRow['hall_id']] = (int)$seatRow['seat_count'];
                }
            } catch (Throwable $exception) {
                error_log('Sales analytics: unable to read seats table: ' . $exception->getMessage());
            }
        }

        foreach ($performances as &$performance) {
            $scheduleId = (int)$performance['id'];
            $sales = $salesBySchedule[$scheduleId] ?? ['sold_total' => 0, 'online_sold' => 0, 'online_recent' => 0];
            $performance = array_merge($performance, $sales);
            $capacity = (int)($performance['capacity'] ?? 0);

            if (general_admission_is_session($performance)) {
                $performance['allocated'] = $capacity;
            } else {
                $scheduleSeatCount = sales_analytics_seat_map_count($performance['seat_map'] ?? null);
                $hallSeatCount = (int)($seatsByHall[(int)($performance['hall_id'] ?? 0)] ?? 0);
                $hallMapCount = sales_analytics_seat_map_count($performance['hall_seat_map'] ?? null);
                $priceRangeCount = sales_analytics_price_range_count($performance['price_ranges'] ?? null);

                if ($scheduleSeatCount !== null) {
                    $performance['allocated'] = $scheduleSeatCount;
                } elseif ($hallSeatCount > 0) {
                    $performance['allocated'] = $hallSeatCount;
                } elseif ($hallMapCount !== null) {
                    $performance['allocated'] = $hallMapCount;
                } elseif ($capacity > 0) {
                    $performance['allocated'] = $capacity;
                } else {
                    $performance['allocated'] = $priceRangeCount;
                }
            }

            $performance['remaining'] = $performance['allocated'] !== null
                ? max(0, (int)$performance['allocated'] - (int)$performance['sold_total'])
                : null;
            $performance['online_percent'] = $performance['allocated'] > 0
                ? min(100, ((int)$performance['online_sold'] / (int)$performance['allocated']) * 100)
                : 0;
            $performance['days_until'] = max(0, (int)floor((strtotime((string)$performance['start_time']) - $now) / 86400));
            $performance['recommendation'] = sales_analytics_recommendation($performance, $performance['days_until']);
        }
        unset($performance);
    }
} catch (Throwable $exception) {
    error_log('Sales analytics: unable to load data: ' . $exception->getMessage());
    $errorText = 'Не удалось загрузить аналитику продаж. Попробуйте обновить страницу позже.';
}

$totals = ['allocated' => 0, 'online_sold' => 0, 'remaining' => 0, 'known_allocations' => 0];
foreach ($performances as $performance) {
    if ($performance['allocated'] === null) {
        continue;
    }
    $totals['allocated'] += (int)$performance['allocated'];
    $totals['online_sold'] += (int)$performance['online_sold'];
    $totals['remaining'] += (int)$performance['remaining'];
    $totals['known_allocations']++;
}
$overallOnlinePercent = $totals['allocated'] > 0
    ? min(100, ($totals['online_sold'] / $totals['allocated']) * 100)
    : 0;

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/panel.php';
?>

<div class="reports-page page container-full sales-analytics">
    <section class="analytics-hero card">
        <div class="analytics-hero__copy">
            <span class="analytics-eyebrow">Планирование квот</span>
            <h1>Понимайте, когда расширять продажи на сайте</h1>
            <p>Сравнивайте онлайн-продажи с количеством мест, выделенных в кабинете, и принимайте решение по каждому сеансу.</p>
        </div>
        <div class="analytics-hero__metric">
            <span>Онлайн-продажи от общей квоты</span>
            <strong><?= number_format($overallOnlinePercent, 0) ?>%</strong>
            <small><?= number_format($totals['online_sold'], 0, '.', ' ') ?> из <?= number_format($totals['allocated'], 0, '.', ' ') ?> мест</small>
        </div>
    </section>

    <div class="reports-kpi-grid analytics-kpi-grid">
        <article class="card reports-kpi reports-kpi--accent"><span>Сеансы в продаже</span><strong><?= number_format(count($performances), 0, '.', ' ') ?></strong><small>предстоящие спектакли</small></article>
        <article class="card reports-kpi reports-kpi--success"><span>Продано онлайн</span><strong><?= number_format($totals['online_sold'], 0, '.', ' ') ?></strong><small>билетов с сайта и приложения</small></article>
        <article class="card reports-kpi"><span>Выделено в кабинете</span><strong><?= number_format($totals['allocated'], 0, '.', ' ') ?></strong><small>мест по сеансам с известной квотой</small></article>
        <article class="card reports-kpi reports-kpi--discount"><span>Свободно в кабинете</span><strong><?= number_format($totals['remaining'], 0, '.', ' ') ?></strong><small>после продаж по всем каналам кабинета</small></article>
    </div>

    <div class="analytics-note">
        <span class="analytics-note__icon" aria-hidden="true">i</span>
        <p><strong>Как читать:</strong> синяя часть — продажи сайта и приложения, фиолетовая — продажи через кассу и другие каналы кабинета, светлая — остаток. Продажи внешних площадок здесь не учитываются, поэтому перед переносом мест сверяйте их остатки.</p>
    </div>

    <?php if ($errorText !== ''): ?>
        <div class="card reports-alert reports-alert--danger"><?= h($errorText) ?></div>
    <?php elseif (!$performances): ?>
        <section class="card analytics-empty">
            <span class="analytics-empty__icon" aria-hidden="true">◷</span>
            <h2>Предстоящих спектаклей пока нет</h2>
            <p>Когда в расписании появятся сеансы с продажами, здесь отобразятся квоты, диаграммы и рекомендации.</p>
        </section>
    <?php else: ?>
        <section class="analytics-list-section" aria-labelledby="analytics-performances-title">
            <div class="analytics-list-heading">
                <div>
                    <span class="analytics-eyebrow">По каждому сеансу</span>
                    <h2 id="analytics-performances-title">Продажи и свободные места</h2>
                </div>
                <span class="analytics-list-count"><?= number_format(count($performances), 0, '.', ' ') ?> сеансов</span>
            </div>

            <div class="analytics-grid">
                <?php foreach ($performances as $performance): ?>
                    <?php
                    $allocated = $performance['allocated'];
                    $onlineSold = (int)$performance['online_sold'];
                    $otherSold = max(0, (int)$performance['sold_total'] - $onlineSold);
                    $chartTotal = max(1, (int)($allocated ?? 0));
                    $onlineBar = $allocated !== null ? min($chartTotal, $onlineSold) : 0;
                    $otherBar = $allocated !== null ? min(max(0, $chartTotal - $onlineBar), $otherSold) : 0;
                    $freeBar = $allocated !== null ? max(0, $chartTotal - $onlineBar - $otherBar) : 0;
                    $onlineWidth = $allocated !== null ? ($onlineBar / $chartTotal) * 100 : 0;
                    $otherWidth = $allocated !== null ? ($otherBar / $chartTotal) * 100 : 0;
                    $freeWidth = $allocated !== null ? ($freeBar / $chartTotal) * 100 : 0;
                    $recommendationClass = (string)$performance['recommendation'][0];
                    $recommendationText = (string)$performance['recommendation'][1];
                    $daysUntil = (int)$performance['days_until'];
                    $countdown = $daysUntil === 0 ? 'сегодня' : ($daysUntil === 1 ? 'завтра' : 'через ' . $daysUntil . ' дн.');
                    ?>
                    <article class="card analytics-performance-card">
                        <div class="analytics-performance-card__head">
                            <div>
                                <span class="analytics-performance-card__date"><?= h(reporting_format_date($performance['start_time'], true)) ?> · <?= h($countdown) ?></span>
                                <h3><?= h($performance['event_title'] ?? 'Без названия') ?></h3>
                                <span class="analytics-performance-card__hall"><?= h($performance['hall_name'] ?? 'Зал не указан') ?></span>
                            </div>
                            <div class="analytics-performance-card__rate">
                                <strong><?= $allocated !== null ? number_format((float)$performance['online_percent'], 0) . '%' : '—' ?></strong>
                                <span>квоты продано онлайн</span>
                            </div>
                        </div>

                        <div class="analytics-card-stats">
                            <div><span>Квота</span><strong><?= $allocated !== null ? number_format((int)$allocated, 0, '.', ' ') . ' мест' : 'Не определена' ?></strong></div>
                            <div><span>Онлайн</span><strong><?= number_format($onlineSold, 0, '.', ' ') ?> бил.</strong></div>
                            <div><span>Осталось в кабинете</span><strong><?= $performance['remaining'] !== null ? number_format((int)$performance['remaining'], 0, '.', ' ') . ' мест' : '—' ?></strong></div>
                            <div><span>Онлайн за 14 дней</span><strong><?= number_format((int)$performance['online_recent'], 0, '.', ' ') ?> бил.</strong></div>
                        </div>

                        <div class="analytics-chart-label">
                            <span>Использование выделенной квоты</span>
                            <strong><?= $allocated !== null ? number_format((int)$performance['sold_total'], 0, '.', ' ') . ' / ' . number_format((int)$allocated, 0, '.', ' ') : 'Нет данных по квоте' ?></strong>
                        </div>
                        <div class="analytics-stacked-bar" role="img" aria-label="Онлайн: <?= number_format($onlineSold, 0, '.', ' ') ?>; другие каналы кабинета: <?= number_format($otherSold, 0, '.', ' ') ?>; свободно: <?= $performance['remaining'] !== null ? number_format((int)$performance['remaining'], 0, '.', ' ') : 'неизвестно' ?>">
                            <?php if ($allocated !== null): ?>
                                <span class="analytics-stacked-bar__online" style="width:<?= h(number_format($onlineWidth, 2, '.', '')) ?>%"></span>
                                <span class="analytics-stacked-bar__other" style="width:<?= h(number_format($otherWidth, 2, '.', '')) ?>%"></span>
                                <span class="analytics-stacked-bar__free" style="width:<?= h(number_format($freeWidth, 2, '.', '')) ?>%"></span>
                            <?php else: ?>
                                <span class="analytics-stacked-bar__unknown"></span>
                            <?php endif; ?>
                        </div>
                        <div class="analytics-legend">
                            <span><i class="analytics-legend__dot analytics-legend__dot--online"></i>Сайт: <?= number_format($onlineSold, 0, '.', ' ') ?></span>
                            <span><i class="analytics-legend__dot analytics-legend__dot--other"></i>Другие каналы: <?= number_format($otherSold, 0, '.', ' ') ?></span>
                            <span><i class="analytics-legend__dot analytics-legend__dot--free"></i>Остаток: <?= $performance['remaining'] !== null ? number_format((int)$performance['remaining'], 0, '.', ' ') : '—' ?></span>
                        </div>

                        <div class="analytics-recommendation analytics-recommendation--<?= h($recommendationClass) ?>">
                            <strong>Рекомендация</strong>
                            <p><?= h($recommendationText) ?></p>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
