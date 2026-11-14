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

if (!function_exists('sales_analytics_allocated_seats')) {
    function sales_analytics_allocated_seats(array $performance, array $seatsByHall): ?int
    {
        $capacity = (int)($performance['capacity'] ?? 0);
        if (general_admission_is_session($performance)) {
            return $capacity;
        }

        $scheduleSeatCount = sales_analytics_seat_map_count($performance['seat_map'] ?? null);
        if ($scheduleSeatCount !== null) {
            return $scheduleSeatCount;
        }

        $hallId = (int)($performance['hall_id'] ?? 0);
        if (!empty($seatsByHall[$hallId])) {
            return (int)$seatsByHall[$hallId];
        }

        $hallSeatCount = sales_analytics_seat_map_count($performance['hall_seat_map'] ?? null);
        if ($hallSeatCount !== null) {
            return $hallSeatCount;
        }
        if ($capacity > 0) {
            return $capacity;
        }

        return sales_analytics_price_range_count($performance['price_ranges'] ?? null);
    }
}

if (!function_exists('sales_analytics_channel_counts')) {
    function sales_analytics_channel_counts(array $tickets, int $scheduleId): array
    {
        $counts = ['online' => 0, 'cash' => 0, 'external' => 0];
        foreach ($tickets as $ticket) {
            if ((int)$ticket['schedule_id'] !== $scheduleId) {
                continue;
            }
            $channel = strtolower(trim((string)($ticket['channel'] ?? '')));
            if (in_array($channel, ['web', 'mobile'], true)) {
                $counts['online']++;
            } elseif ($channel === 'kassa') {
                $counts['cash']++;
            } else {
                $counts['external']++;
            }
        }
        $counts['own_sold'] = $counts['online'] + $counts['cash'];
        $counts['sold_total'] = $counts['own_sold'] + $counts['external'];
        return $counts;
    }
}

if (!function_exists('sales_analytics_curve_path')) {
    function sales_analytics_curve_path(array $values, int $periodDays, float $left, float $top, float $width, float $height): string
    {
        $points = [];
        foreach ($values as $index => $value) {
            $x = $left + (($periodDays - $index) / max(1, $periodDays)) * $width;
            $y = $top + (1 - (max(0, min(100, (float)$value)) / 100)) * $height;
            $points[] = number_format($x, 2, '.', '') . ',' . number_format($y, 2, '.', '');
        }
        return $points ? 'M ' . implode(' L ', $points) : '';
    }
}

$page_title_meta = 'Аналитика продаж';
$panel_title = 'Аналитика';
$panel_subtitle = 'Продажи спектаклей и прогноз по квоте ближайшего сеанса';
$active_menu = 'sales_analytics';
$use_sidebar = true;
$page_styles = ['/assets/css/reports.css?v=sales-analytics-20261114-2'];

$eventId = max(0, (int)($_GET['event_id'] ?? 0));
$periodDays = (int)($_GET['period'] ?? 60);
if (!in_array($periodDays, [30, 60, 90], true)) {
    $periodDays = 60;
}

$events = [];
$eventNames = [];
$futureSchedules = [];
$eventCards = [];
$errorText = '';
$selectedEvent = null;
$nextSchedule = null;
$historySchedules = [];
$historyMetrics = [];
$chartSeries = [];
$forecast = null;
$ticketsInDetail = [];
$seatsByHall = [];

try {
    $events = db_fetch_all(
        "SELECT DISTINCT e.id, e.title
         FROM schedules s
         JOIN events e ON e.id = s.event_id
         WHERE s.start_time >= NOW()
           AND COALESCE(s.status, '') NOT IN ('cancelled', 'draft', 'archive')
         ORDER BY e.title"
    );
    foreach ($events as $event) {
        $eventNames[(int)$event['id']] = (string)$event['title'];
    }

    if ($eventId > 0 && !array_key_exists($eventId, $eventNames)) {
        $errorText = 'У выбранного спектакля нет предстоящих сеансов.';
    } else {
        $scheduleSql = "SELECT s.id, s.event_id, s.hall_id, s.start_time, s.status, s.capacity, s.seat_map, s.price_ranges,
                               e.title AS event_title, h.name AS hall_name, h.seat_map AS hall_seat_map
                        FROM schedules s
                        LEFT JOIN events e ON e.id = s.event_id
                        LEFT JOIN halls h ON h.id = s.hall_id
                        WHERE s.start_time >= NOW()
                          AND COALESCE(s.status, '') NOT IN ('cancelled', 'draft', 'archive')";
        $scheduleParams = [];
        if ($eventId > 0) {
            $scheduleSql .= ' AND s.event_id = ?';
            $scheduleParams[] = $eventId;
        }
        $scheduleSql .= ' ORDER BY s.start_time ASC';
        $futureSchedules = db_fetch_all($scheduleSql, $scheduleParams);

        $scheduleIds = array_values(array_filter(array_map(static function ($performance) {
            return (int)($performance['id'] ?? 0);
        }, $futureSchedules)));
        $salesBySchedule = [];
        if ($scheduleIds !== []) {
            $placeholders = implode(',', array_fill(0, count($scheduleIds), '?'));
            $salesRows = db_fetch_all(
                "SELECT schedule_id,
                        COUNT(*) AS sold_total,
                        SUM(CASE WHEN LOWER(COALESCE(channel, '')) IN ('web', 'mobile') THEN 1 ELSE 0 END) AS online_sold,
                        SUM(CASE WHEN LOWER(COALESCE(channel, '')) = 'kassa' THEN 1 ELSE 0 END) AS cash_sold,
                        SUM(CASE WHEN LOWER(COALESCE(channel, '')) NOT IN ('web', 'mobile', 'kassa') THEN 1 ELSE 0 END) AS external_sold,
                        SUM(CASE WHEN LOWER(COALESCE(channel, '')) IN ('web', 'mobile')
                                      AND purchased_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS recent_online,
                        SUM(CASE WHEN LOWER(COALESCE(channel, '')) = 'kassa'
                                      AND purchased_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS recent_cash,
                        SUM(CASE WHEN LOWER(COALESCE(channel, '')) IN ('web', 'mobile')
                                      AND purchased_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
                                      AND purchased_at < DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS previous_online,
                        SUM(CASE WHEN LOWER(COALESCE(channel, '')) = 'kassa'
                                      AND purchased_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
                                      AND purchased_at < DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS previous_cash
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
                    'cash_sold' => (int)($salesRow['cash_sold'] ?? 0),
                    'external_sold' => (int)($salesRow['external_sold'] ?? 0),
                    'recent_online' => (int)($salesRow['recent_online'] ?? 0),
                    'recent_cash' => (int)($salesRow['recent_cash'] ?? 0),
                    'previous_online' => (int)($salesRow['previous_online'] ?? 0),
                    'previous_cash' => (int)($salesRow['previous_cash'] ?? 0),
                ];
            }

            $hallIds = array_values(array_unique(array_filter(array_map(static function ($performance) {
                return (int)($performance['hall_id'] ?? 0);
            }, $futureSchedules))));
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
        }

        foreach ($futureSchedules as &$performance) {
            $scheduleIdForRow = (int)$performance['id'];
            $sales = $salesBySchedule[$scheduleIdForRow] ?? [
                'sold_total' => 0, 'online_sold' => 0, 'cash_sold' => 0, 'external_sold' => 0,
                'recent_online' => 0, 'recent_cash' => 0,
                'previous_online' => 0, 'previous_cash' => 0,
            ];
            $performance = array_merge($performance, $sales);
            $performance['allocated'] = sales_analytics_allocated_seats($performance, $seatsByHall);
            $performance['remaining'] = $performance['allocated'] !== null
                ? max(0, (int)$performance['allocated'] - (int)$performance['sold_total'])
                : null;
            $performance['online_percent'] = $performance['allocated'] > 0
                ? min(100, ((int)$performance['online_sold'] / (int)$performance['allocated']) * 100)
                : 0;
            $performance['cash_percent'] = $performance['allocated'] > 0
                ? min(100, ((int)$performance['cash_sold'] / (int)$performance['allocated']) * 100)
                : 0;

            if ($eventId === 0) {
                $currentEventId = (int)($performance['event_id'] ?? 0);
                if (!isset($eventCards[$currentEventId])) {
                    $eventCards[$currentEventId] = [
                        'event_id' => $currentEventId,
                        'event_title' => (string)($performance['event_title'] ?? 'Без названия'),
                        'sessions' => 0,
                        'allocated' => 0,
                        'online_sold' => 0,
                        'cash_sold' => 0,
                        'external_sold' => 0,
                        'own_sold' => 0,
                        'sold_total' => 0,
                        'remaining' => 0,
                        'next' => $performance,
                    ];
                }
                $eventCards[$currentEventId]['sessions']++;
                $eventCards[$currentEventId]['online_sold'] += (int)$performance['online_sold'];
                $eventCards[$currentEventId]['cash_sold'] += (int)$performance['cash_sold'];
                $eventCards[$currentEventId]['external_sold'] += (int)$performance['external_sold'];
                $eventCards[$currentEventId]['own_sold'] += (int)$performance['online_sold'] + (int)$performance['cash_sold'];
                $eventCards[$currentEventId]['sold_total'] += (int)$performance['sold_total'];
                if ($performance['allocated'] !== null) {
                    $eventCards[$currentEventId]['allocated'] += (int)$performance['allocated'];
                    $eventCards[$currentEventId]['remaining'] += (int)$performance['remaining'];
                }
            }

        }
        unset($performance);

        if ($eventId > 0) {
            $selectedEvent = ['id' => $eventId, 'title' => $eventNames[$eventId]];
            $nextSchedule = $futureSchedules[0] ?? null;
            if (!$nextSchedule) {
                $errorText = 'Предстоящих сеансов этого спектакля нет.';
            } else {
                $historySchedules = db_fetch_all(
                    "SELECT s.id, s.event_id, s.hall_id, s.start_time, s.status, s.capacity, s.seat_map, s.price_ranges,
                            e.title AS event_title, h.name AS hall_name, h.seat_map AS hall_seat_map
                     FROM schedules s
                     LEFT JOIN events e ON e.id = s.event_id
                     LEFT JOIN halls h ON h.id = s.hall_id
                     WHERE s.event_id = ?
                       AND s.start_time < NOW()
                       AND COALESCE(s.status, '') NOT IN ('cancelled', 'draft')
                     ORDER BY s.start_time DESC
                     LIMIT 5",
                    [$eventId]
                );
                $relatedSchedules = array_merge($historySchedules, [$nextSchedule]);
                $relatedIds = array_values(array_filter(array_map(static function ($performance) {
                    return (int)($performance['id'] ?? 0);
                }, $relatedSchedules)));
                $relatedPlaceholders = implode(',', array_fill(0, count($relatedIds), '?'));
                $detailHallIds = array_values(array_unique(array_filter(array_map(static function ($performance) {
                    return (int)($performance['hall_id'] ?? 0);
                }, $relatedSchedules))));
                if ($detailHallIds !== []) {
                    $detailHallPlaceholders = implode(',', array_fill(0, count($detailHallIds), '?'));
                    try {
                        $seatRows = db_fetch_all(
                            "SELECT hall_id, COUNT(*) AS seat_count
                             FROM seats
                             WHERE hall_id IN ($detailHallPlaceholders)
                             GROUP BY hall_id",
                            $detailHallIds
                        );
                        foreach ($seatRows as $seatRow) {
                            $seatsByHall[(int)$seatRow['hall_id']] = (int)$seatRow['seat_count'];
                        }
                    } catch (Throwable $exception) {
                        error_log('Sales analytics: unable to read historical seat counts: ' . $exception->getMessage());
                    }
                }

                $ticketRows = db_fetch_all(
                    "SELECT schedule_id, purchased_at, channel
                     FROM tickets
                     WHERE schedule_id IN ($relatedPlaceholders)
                       AND payment_status = 'paid'
                       AND status <> 'cancelled'
                       AND COALESCE(refund_status, 'none') <> 'refunded'
                     ORDER BY purchased_at ASC",
                    $relatedIds
                );
                $allScheduleStats = [];
                foreach ($relatedSchedules as $performance) {
                    $scheduleIdForDetail = (int)$performance['id'];
                    $stats = sales_analytics_channel_counts($ticketRows, $scheduleIdForDetail);
                    $stats['allocated'] = sales_analytics_allocated_seats($performance, $seatsByHall);
                    $stats['schedule'] = $performance;
                    $allScheduleStats[$scheduleIdForDetail] = $stats;
                }

                $nextSchedule = $allScheduleStats[(int)$nextSchedule['id']]['schedule'];
                $historyCount = 0;
                $historyFinal = ['online' => 0.0, 'cash' => 0.0, 'external' => 0.0, 'own' => 0.0, 'total' => 0.0];
                $historicalCurves = ['own' => [], 'online' => [], 'cash' => [], 'external' => []];
                $currentTicketDays = ['own' => [], 'online' => [], 'cash' => [], 'external' => []];
                $nextScheduleId = (int)$nextSchedule['id'];
                $nextStart = strtotime((string)$nextSchedule['start_time']) ?: time();
                $daysUntilNext = max(0, (int)ceil(($nextStart - time()) / 86400));
                $recentOnline = 0;
                $recentCash = 0;
                $recentTotal = 0;

                foreach ($ticketRows as $ticket) {
                    $ticketScheduleId = (int)$ticket['schedule_id'];
                    $scheduleInfo = $allScheduleStats[$ticketScheduleId]['schedule'] ?? null;
                    if (!$scheduleInfo) {
                        continue;
                    }
                    $showTimestamp = strtotime((string)$scheduleInfo['start_time']) ?: 0;
                    $purchaseTimestamp = strtotime((string)$ticket['purchased_at']) ?: 0;
                    $channel = strtolower(trim((string)($ticket['channel'] ?? '')));
                    if ($ticketScheduleId === $nextScheduleId && $purchaseTimestamp >= time() - 7 * 86400) {
                        $recentTotal++;
                        if (in_array($channel, ['web', 'mobile'], true)) {
                            $recentOnline++;
                        } elseif ($channel === 'kassa') {
                            $recentCash++;
                        }
                    }
                    $daysBefore = (int)floor(($showTimestamp - $purchaseTimestamp) / 86400);
                    if ($daysBefore < 0 || $daysBefore > $periodDays) {
                        continue;
                    }
                    if ($ticketScheduleId === $nextScheduleId) {
                        if (in_array($channel, ['web', 'mobile'], true)) {
                            foreach (['online', 'own'] as $curveType) {
                                $currentTicketDays[$curveType][$daysBefore] = ($currentTicketDays[$curveType][$daysBefore] ?? 0) + 1;
                            }
                        } elseif ($channel === 'kassa') {
                            foreach (['cash', 'own'] as $curveType) {
                                $currentTicketDays[$curveType][$daysBefore] = ($currentTicketDays[$curveType][$daysBefore] ?? 0) + 1;
                            }
                        } else {
                            $currentTicketDays['external'][$daysBefore] = ($currentTicketDays['external'][$daysBefore] ?? 0) + 1;
                        }
                    }
                }

                foreach ($historySchedules as $history) {
                    $historyScheduleId = (int)$history['id'];
                    $stats = $allScheduleStats[$historyScheduleId];
                    $allocation = (int)($stats['allocated'] ?? 0);
                    if ($allocation <= 0) {
                        continue;
                    }
                    $historyCount++;
                    $historyMetrics[] = $stats;
                    foreach (['online', 'cash'] as $curveType) {
                        $historyFinal[$curveType] += min(100, ($stats[$curveType] / $allocation) * 100);
                    }
                    $historyFinal['external'] += min(100, ($stats['external'] / $allocation) * 100);
                    $historyFinal['own'] += min(100, (($stats['online'] + $stats['cash']) / $allocation) * 100);
                    $historyFinal['total'] += min(100, ($stats['sold_total'] / $allocation) * 100);
                    foreach ($ticketRows as $ticket) {
                        if ((int)$ticket['schedule_id'] !== $historyScheduleId) {
                            continue;
                        }
                        $channel = strtolower(trim((string)($ticket['channel'] ?? '')));
                        $showTimestamp = strtotime((string)$history['start_time']) ?: 0;
                        $purchaseTimestamp = strtotime((string)$ticket['purchased_at']) ?: 0;
                        $daysBefore = (int)floor(($showTimestamp - $purchaseTimestamp) / 86400);
                        if ($daysBefore >= 0 && $daysBefore <= $periodDays) {
                            if (in_array($channel, ['web', 'mobile'], true)) {
                                foreach (['online', 'own'] as $curveType) {
                                    $historicalCurves[$curveType][$historyScheduleId][$daysBefore] =
                                        ($historicalCurves[$curveType][$historyScheduleId][$daysBefore] ?? 0) + 1;
                                }
                            } elseif ($channel === 'kassa') {
                                foreach (['cash', 'own'] as $curveType) {
                                    $historicalCurves[$curveType][$historyScheduleId][$daysBefore] =
                                        ($historicalCurves[$curveType][$historyScheduleId][$daysBefore] ?? 0) + 1;
                                }
                            } else {
                                $historicalCurves['external'][$historyScheduleId][$daysBefore] =
                                    ($historicalCurves['external'][$historyScheduleId][$daysBefore] ?? 0) + 1;
                            }
                        }
                    }
                }

                if ($historyCount > 0) {
                    foreach ($historyFinal as $key => $value) {
                        $historyFinal[$key] = $value / $historyCount;
                    }
                }

                $currentStats = $allScheduleStats[$nextScheduleId];
                $currentAllocation = (int)($currentStats['allocated'] ?? 0);
                $projectedTotalPercent = $currentAllocation > 0
                    ? min(100, (($currentStats['sold_total'] + ($recentTotal / 7) * $daysUntilNext) / $currentAllocation) * 100)
                    : 0;
                $projectedOwnPercent = $currentAllocation > 0
                    ? min(100, (($currentStats['own_sold'] + (($recentOnline + $recentCash) / 7) * $daysUntilNext) / $currentAllocation) * 100)
                    : 0;

                if ($currentAllocation <= 0) {
                    $forecast = ['warning', 'Не удалось определить квоту ближайшего сеанса. Проверьте настройки схемы и выделенные места.'];
                } elseif ($historyCount < 2) {
                    $forecast = ['muted', 'Для надёжного прогноза нужно не менее двух завершённых сеансов этого спектакля. Продажи сайта и кассы учитываются вместе, внешние площадки показаны отдельно.'];
                } elseif ($historyFinal['own'] >= 70 && $projectedOwnPercent >= 65) {
                    $forecast = ['success', 'Спрос на наши каналы устойчивый: прошлые сеансы в среднем реализовали ' . number_format($historyFinal['own'], 0) . '% квоты через сайт и кассу, прогноз ближайшего — ' . number_format($projectedOwnPercent, 0) . '%. Для следующего сеанса можно тестово увеличить нашу квоту примерно на 10%, сверив остатки Ticketon.kz и Kino.kz.'];
                } elseif ($historyFinal['own'] >= 70) {
                    $forecast = ['steady', 'Ранее сайт и касса продавали хорошо, но текущий прогноз пока ниже (' . number_format($projectedOwnPercent, 0) . '%). Пока не расширяйте нашу квоту; проверьте динамику через несколько дней. Внешние продажи в показатель наших каналов не входят.'];
                } elseif ($historyFinal['own'] < 40 && $historyFinal['external'] >= 25) {
                    $forecast = ['warning', 'Ticketon.kz и Kino.kz продают заметную долю мест, но наши сайт и касса пока отстают. Не увеличивайте нашу квоту до подтверждения спроса через собственные каналы.'];
                } elseif ($projectedOwnPercent >= 70) {
                    $forecast = ['success', 'Сайт и касса вместе показывают сильный темп (прогноз ' . number_format($projectedOwnPercent, 0) . '%). Рассмотрите небольшое тестовое увеличение нашей квоты на следующий сеанс.'];
                } elseif ($projectedTotalPercent >= 70) {
                    $forecast = ['steady', 'Общие продажи, включая внешние площадки, идут хорошо, но продажи сайта и кассы пока не подтверждают увеличение нашей квоты.'];
                } else {
                    $forecast = ['muted', 'Пока нет устойчивого сигнала для увеличения нашей квоты. Сохраните распределение и повторно оцените продажи сайта и кассы ближе к спектаклю.'];
                }

                $averageCurves = ['own' => []];
                for ($day = $periodDays; $day >= 0; $day--) {
                    $totalPercent = 0.0;
                    foreach ($historySchedules as $history) {
                        $historyScheduleId = (int)$history['id'];
                        $allocation = (int)($allScheduleStats[$historyScheduleId]['allocated'] ?? 0);
                        if ($allocation <= 0) {
                            continue;
                        }
                        $soldAtDay = 0;
                        foreach (($historicalCurves['own'][$historyScheduleId] ?? []) as $purchaseDay => $quantity) {
                            if ((int)$purchaseDay >= $day) {
                                $soldAtDay += (int)$quantity;
                            }
                        }
                        $totalPercent += min(100, ($soldAtDay / $allocation) * 100);
                    }
                    $averageCurves['own'][] = $historyCount > 0 ? $totalPercent / $historyCount : 0;

                    foreach (['own', 'online', 'cash', 'external'] as $curveType) {
                        if ($day > $daysUntilNext) {
                            $chartSeries[$curveType][] = 0;
                            continue;
                        }
                        $soldAtDay = 0;
                        foreach ($currentTicketDays[$curveType] as $purchaseDay => $quantity) {
                            if ((int)$purchaseDay >= $day) {
                                $soldAtDay += (int)$quantity;
                            }
                        }
                        $chartSeries[$curveType][] = $currentAllocation > 0
                            ? min(100, ($soldAtDay / $currentAllocation) * 100)
                            : 0;
                    }
                }
                $chartSeries['average_own'] = $averageCurves['own'];

                $plot = ['left' => 56.0, 'top' => 18.0, 'width' => 790.0, 'height' => 240.0];
                $chartPaths = [];
                foreach ($chartSeries as $seriesKey => $values) {
                    $chartPaths[$seriesKey] = sales_analytics_curve_path(
                        $values,
                        $periodDays,
                        $plot['left'],
                        $plot['top'],
                        $plot['width'],
                        $plot['height']
                    );
                }
            }
        }
    }
} catch (Throwable $exception) {
    error_log('Sales analytics: unable to load data: ' . $exception->getMessage());
    $errorText = 'Не удалось загрузить аналитику продаж. Попробуйте обновить страницу позже.';
}

if ($eventId === 0) {
    uasort($eventCards, static function ($left, $right) {
        return strcmp((string)$left['next']['start_time'], (string)$right['next']['start_time']);
    });
}

require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/panel.php';
?>

<div class="reports-page page container-full sales-analytics">
    <div class="analytics-toolbar card">
        <div>
            <span class="analytics-eyebrow">Выборка по спектаклям</span>
            <h2>Анализ продаж через кабинет</h2>
        </div>
        <form method="get" class="analytics-filter">
            <label for="analytics-event">Спектакль</label>
            <select id="analytics-event" name="event_id" class="form-control">
                <option value="0" <?= $eventId === 0 ? 'selected' : '' ?>>Все спектакли</option>
                <?php foreach ($events as $event): ?>
                    <option value="<?= (int)$event['id'] ?>" <?= $eventId === (int)$event['id'] ? 'selected' : '' ?>><?= h($event['title']) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($eventId > 0): ?>
                <label for="analytics-period">Период до сеанса</label>
                <select id="analytics-period" name="period" class="form-control">
                    <?php foreach ([30, 60, 90] as $periodOption): ?>
                        <option value="<?= $periodOption ?>" <?= $periodDays === $periodOption ? 'selected' : '' ?>><?= $periodOption ?> дней</option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary"><?= $eventId > 0 ? 'Обновить график' : 'Выбрать' ?></button>
        </form>
    </div>

    <?php if ($errorText !== ''): ?>
        <div class="card reports-alert reports-alert--danger"><?= h($errorText) ?></div>
    <?php elseif (!$selectedEvent): ?>
        <?php if (!$eventCards): ?>
            <section class="card analytics-empty">
                <span class="analytics-empty__icon" aria-hidden="true">◷</span>
                <h2>Предстоящих спектаклей пока нет</h2>
                <p>Когда в расписании появятся сеансы, здесь будут доступны продажи, тенденции и прогнозы.</p>
            </section>
        <?php else: ?>
            <?php
            $overviewOnline = 0;
            $overviewCash = 0;
            $overviewExternal = 0;
            $overviewRemaining = 0;
            foreach ($eventCards as $card) {
                $overviewOnline += (int)$card['online_sold'];
                $overviewCash += (int)$card['cash_sold'];
                $overviewExternal += (int)$card['external_sold'];
                $overviewRemaining += (int)$card['remaining'];
            }
            ?>
            <section class="analytics-overview-kpis">
                <article class="card analytics-overview-kpi"><span>Спектаклей в продаже</span><strong><?= number_format(count($eventCards), 0, '.', ' ') ?></strong></article>
                <article class="card analytics-overview-kpi analytics-overview-kpi--own"><span>Продано нами</span><strong><?= number_format($overviewOnline + $overviewCash, 0, '.', ' ') ?></strong><small>сайт + касса</small></article>
                <article class="card analytics-overview-kpi analytics-overview-kpi--online"><span>Сайт и приложение</span><strong><?= number_format($overviewOnline, 0, '.', ' ') ?></strong></article>
                <article class="card analytics-overview-kpi analytics-overview-kpi--cash"><span>Касса</span><strong><?= number_format($overviewCash, 0, '.', ' ') ?></strong></article>
                <article class="card analytics-overview-kpi analytics-overview-kpi--external"><span>Ticketon.kz + Kino.kz</span><strong><?= number_format($overviewExternal, 0, '.', ' ') ?></strong></article>
                <article class="card analytics-overview-kpi"><span>Свободно в кабинете</span><strong><?= number_format($overviewRemaining, 0, '.', ' ') ?></strong></article>
            </section>
            <div class="analytics-note">
                <span class="analytics-note__icon" aria-hidden="true">i</span>
                <p><strong>Продано нами = сайт + касса.</strong> Именно эту сумму прогноз использует для решения об увеличении нашей квоты. Продажи Ticketon.kz и Kino.kz показаны отдельной группой и влияют на остаток мест, но не прибавляются к нашим продажам.</p>
            </div>
            <div class="analytics-event-grid">
                <?php foreach ($eventCards as $card): ?>
                    <?php
                    $next = $card['next'];
                    $allocation = (int)($next['allocated'] ?? 0);
                    $online = (int)$next['online_sold'];
                    $cash = (int)$next['cash_sold'];
                    $external = (int)$next['external_sold'];
                    $ownSold = $online + $cash;
                    $soldPct = $allocation > 0 ? min(100, ($ownSold / $allocation) * 100) : 0;
                    $onlinePct = $allocation > 0 ? min(100, ($online / $allocation) * 100) : 0;
                    $cashPct = $allocation > 0 ? min(100 - $onlinePct, ($cash / $allocation) * 100) : 0;
                    $externalPct = $allocation > 0 ? min(max(0, 100 - $onlinePct - $cashPct), ($external / $allocation) * 100) : 0;
                    $recentOwn = (int)$next['recent_online'] + (int)$next['recent_cash'];
                    $previousOwn = (int)$next['previous_online'] + (int)$next['previous_cash'];
                    $trendLabel = $recentOwn > $previousOwn
                        ? 'Продажи ускорились'
                        : ($recentOwn < $previousOwn ? 'Темп снизился' : 'Темп стабильный');
                    ?>
                    <a class="card analytics-event-card" href="/reports/analytics.php?<?= h(http_build_query(['event_id' => $card['event_id'], 'period' => $periodDays])) ?>">
                        <div class="analytics-event-card__main">
                            <span class="analytics-event-card__meta"><?= number_format((int)$card['sessions'], 0, '.', ' ') ?> предстоящих сеансов · ближайший <?= h(reporting_format_date($next['start_time'], true)) ?></span>
                            <h3><?= h($card['event_title']) ?></h3>
                            <span class="analytics-event-card__hall"><?= h($next['hall_name'] ?? 'Зал не указан') ?></span>
                            <div class="analytics-event-card__stats">
                                <span><i class="analytics-legend__dot analytics-legend__dot--online"></i>Сайт <strong><?= number_format($online, 0, '.', ' ') ?></strong></span>
                                <span><i class="analytics-legend__dot analytics-legend__dot--cash"></i>Касса <strong><?= number_format($cash, 0, '.', ' ') ?></strong></span>
                                <span><i class="analytics-legend__dot analytics-legend__dot--external"></i>Ticketon/Kino <strong><?= number_format($external, 0, '.', ' ') ?></strong></span>
                                <span>Свободно <strong><?= $next['remaining'] !== null ? number_format((int)$next['remaining'], 0, '.', ' ') : '—' ?></strong></span>
                            </div>
                            <span class="analytics-event-card__trend">Наши продажи: <?= number_format($ownSold, 0, '.', ' ') ?> · <?= h($trendLabel) ?> за 7 дней</span>
                        </div>
                        <div class="analytics-donut" role="img" aria-label="Сайт и касса реализовали <?= number_format($soldPct, 0) ?> процентов квоты ближайшего сеанса" style="--analytics-donut: conic-gradient(#2563eb 0 <?= number_format($onlinePct, 2, '.', '') ?>%, #8b5cf6 <?= number_format($onlinePct, 2, '.', '') ?>% <?= number_format($onlinePct + $cashPct, 2, '.', '') ?>%, #f59e0b <?= number_format($onlinePct + $cashPct, 2, '.', '') ?>% <?= number_format($onlinePct + $cashPct + $externalPct, 2, '.', '') ?>%, #e8eef7 <?= number_format($onlinePct + $cashPct + $externalPct, 2, '.', '') ?>% 100%)">
                            <span><strong><?= number_format($soldPct, 0) ?>%</strong><small>наши продажи</small></span>
                        </div>
                        <span class="analytics-event-card__open">Открыть аналитику <b aria-hidden="true">→</b></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php elseif (!$nextSchedule): ?>
        <section class="card analytics-empty">
            <h2>Нет данных по ближайшему сеансу</h2>
            <p>У выбранного спектакля пока нет предстоящих сеансов.</p>
        </section>
    <?php else: ?>
        <?php
        $currentStats = $allScheduleStats[(int)$nextSchedule['id']] ?? ['allocated' => null, 'online' => 0, 'cash' => 0, 'external' => 0, 'own_sold' => 0, 'sold_total' => 0];
        $currentAllocation = (int)($currentStats['allocated'] ?? 0);
        $currentOnlinePercent = $currentAllocation > 0 ? min(100, ($currentStats['online'] / $currentAllocation) * 100) : 0;
        $currentCashPercent = $currentAllocation > 0 ? min(100 - $currentOnlinePercent, ($currentStats['cash'] / $currentAllocation) * 100) : 0;
        $currentExternalPercent = $currentAllocation > 0
            ? min(max(0, 100 - $currentOnlinePercent - $currentCashPercent), ($currentStats['external'] / $currentAllocation) * 100)
            : 0;
        $currentPercent = $currentAllocation > 0 ? min(100, (($currentStats['online'] + $currentStats['cash']) / $currentAllocation) * 100) : 0;
        $projectedOwnPercent = $projectedOwnPercent ?? 0;
        $projectedTotalPercent = $projectedTotalPercent ?? 0;
        $historyCount = $historyCount ?? 0;
        $historyFinal = $historyFinal ?? ['online' => 0, 'cash' => 0, 'external' => 0, 'own' => 0, 'total' => 0];
        $forecast = $forecast ?? ['muted', 'Для прогноза недостаточно данных.'];
        $chartPaths = $chartPaths ?? [];
        $plot = $plot ?? ['left' => 56.0, 'top' => 18.0, 'width' => 790.0, 'height' => 240.0];
        $chartHasSales = !empty($currentStats['sold_total']) || $historyCount > 0;
        $daysUntilNext = max(0, (int)ceil(((strtotime((string)$nextSchedule['start_time']) ?: time()) - time()) / 86400));
        ?>
        <section class="analytics-detail-hero card">
            <div>
                <a class="analytics-back-button" href="/reports/analytics.php">← Вернуться к списку спектаклей</a>
                <span class="analytics-eyebrow">Аналитика спектакля</span>
                <h1><?= h($selectedEvent['title']) ?></h1>
                <p>Ближайший сеанс: <?= h(reporting_format_date($nextSchedule['start_time'], true)) ?> · <?= h($nextSchedule['hall_name'] ?? 'Зал не указан') ?></p>
            </div>
            <div class="analytics-detail-donut" role="img" aria-label="Сайт и касса реализовали <?= number_format($currentPercent, 0) ?> процентов квоты ближайшего сеанса" style="--analytics-donut: conic-gradient(#2563eb 0 <?= number_format($currentOnlinePercent, 2, '.', '') ?>%, #8b5cf6 <?= number_format($currentOnlinePercent, 2, '.', '') ?>% <?= number_format($currentOnlinePercent + $currentCashPercent, 2, '.', '') ?>%, #f59e0b <?= number_format($currentOnlinePercent + $currentCashPercent, 2, '.', '') ?>% <?= number_format($currentOnlinePercent + $currentCashPercent + $currentExternalPercent, 2, '.', '') ?>%, #e8eef7 <?= number_format($currentOnlinePercent + $currentCashPercent + $currentExternalPercent, 2, '.', '') ?>% 100%)">
                <span><strong><?= number_format($currentPercent, 0) ?>%</strong><small>продано нами</small></span>
            </div>
        </section>

        <div class="analytics-detail-kpis">
            <article class="card analytics-overview-kpi analytics-overview-kpi--own"><span>Продано нами · сайт + касса</span><strong><?= number_format((int)$currentStats['own_sold'], 0, '.', ' ') ?></strong></article>
            <article class="card analytics-overview-kpi analytics-overview-kpi--online"><span>Сайт и приложение</span><strong><?= number_format((int)$currentStats['online'], 0, '.', ' ') ?></strong></article>
            <article class="card analytics-overview-kpi analytics-overview-kpi--cash"><span>Касса</span><strong><?= number_format((int)$currentStats['cash'], 0, '.', ' ') ?></strong></article>
            <article class="card analytics-overview-kpi analytics-overview-kpi--external"><span>Ticketon.kz + Kino.kz</span><strong><?= number_format((int)$currentStats['external'], 0, '.', ' ') ?></strong></article>
        </div>

        <section class="card analytics-chart-card">
            <div class="analytics-section-heading">
                <div>
                    <span class="analytics-eyebrow">Кривая продаж</span>
                    <h2>Как продаются места до сеанса</h2>
                    <p>Накопительные продажи в процентах от квоты: сайт и касса показаны отдельно, их сумма — наши продажи. Пунктир — среднее прошлых сеансов.</p>
                </div>
                <span class="analytics-history-badge">История: <?= number_format($historyCount, 0, '.', ' ') ?> сеансов</span>
            </div>
            <div class="analytics-chart-wrap">
                <svg class="analytics-line-chart" viewBox="0 0 900 310" role="img" aria-labelledby="analytics-chart-title analytics-chart-desc">
                    <title id="analytics-chart-title">Кривая продаж спектакля <?= h($selectedEvent['title']) ?></title>
                    <desc id="analytics-chart-desc">Процент квоты, проданный сайтом, кассой и внешними площадками по дням до сеанса; также показаны общие продажи нашими каналами.</desc>
                    <?php foreach ([0, 25, 50, 75, 100] as $tick): ?>
                        <?php $y = $plot['top'] + (1 - ($tick / 100)) * $plot['height']; ?>
                        <line x1="<?= number_format($plot['left'], 2, '.', '') ?>" y1="<?= number_format($y, 2, '.', '') ?>" x2="<?= number_format($plot['left'] + $plot['width'], 2, '.', '') ?>" y2="<?= number_format($y, 2, '.', '') ?>" class="analytics-chart-grid"></line>
                        <text x="45" y="<?= number_format($y + 4, 2, '.', '') ?>" class="analytics-chart-axis-label" text-anchor="end"><?= $tick ?>%</text>
                    <?php endforeach; ?>
                    <?php foreach (array_unique([$periodDays, (int)round($periodDays * .66), (int)round($periodDays * .33), 0]) as $tick): ?>
                        <?php $x = $plot['left'] + (($periodDays - $tick) / max(1, $periodDays)) * $plot['width']; ?>
                        <line x1="<?= number_format($x, 2, '.', '') ?>" y1="<?= number_format($plot['top'], 2, '.', '') ?>" x2="<?= number_format($x, 2, '.', '') ?>" y2="<?= number_format($plot['top'] + $plot['height'], 2, '.', '') ?>" class="analytics-chart-grid analytics-chart-grid--vertical"></line>
                        <text x="<?= number_format($x, 2, '.', '') ?>" y="281" class="analytics-chart-axis-label" text-anchor="middle"><?= $tick ?> дн.</text>
                    <?php endforeach; ?>
                    <?php if (!empty($chartPaths['average_own'])): ?><path d="<?= h($chartPaths['average_own']) ?>" class="analytics-chart-line analytics-chart-line--average-own"></path><?php endif; ?>
                    <?php if (!empty($chartPaths['own'])): ?><path d="<?= h($chartPaths['own']) ?>" class="analytics-chart-line analytics-chart-line--own"></path><?php endif; ?>
                    <?php if (!empty($chartPaths['online'])): ?><path d="<?= h($chartPaths['online']) ?>" class="analytics-chart-line analytics-chart-line--online"></path><?php endif; ?>
                    <?php if (!empty($chartPaths['cash'])): ?><path d="<?= h($chartPaths['cash']) ?>" class="analytics-chart-line analytics-chart-line--cash"></path><?php endif; ?>
                    <?php if (!empty($chartPaths['external'])): ?><path d="<?= h($chartPaths['external']) ?>" class="analytics-chart-line analytics-chart-line--external"></path><?php endif; ?>
                    <?php if ($daysUntilNext <= $periodDays): ?>
                        <?php $currentX = $plot['left'] + (($periodDays - $daysUntilNext) / max(1, $periodDays)) * $plot['width']; ?>
                        <line x1="<?= number_format($currentX, 2, '.', '') ?>" y1="<?= number_format($plot['top'], 2, '.', '') ?>" x2="<?= number_format($currentX, 2, '.', '') ?>" y2="<?= number_format($plot['top'] + $plot['height'], 2, '.', '') ?>" class="analytics-chart-now"></line>
                        <text x="<?= number_format($currentX + 5, 2, '.', '') ?>" y="15" class="analytics-chart-now-label">сегодня</text>
                    <?php endif; ?>
                </svg>
            </div>
            <div class="analytics-chart-legend">
                <span><i class="analytics-chart-legend__line analytics-chart-legend__line--own"></i>Сайт + касса — ближайший сеанс</span>
                <span><i class="analytics-chart-legend__line analytics-chart-legend__line--online"></i>Сайт и приложение — ближайший сеанс</span>
                <span><i class="analytics-chart-legend__line analytics-chart-legend__line--cash"></i>Касса — ближайший сеанс</span>
                <span><i class="analytics-chart-legend__line analytics-chart-legend__line--external"></i>Ticketon.kz + Kino.kz — ближайший сеанс</span>
                <span><i class="analytics-chart-legend__line analytics-chart-legend__line--average-own"></i>Сайт + касса — среднее прошлых сеансов</span>
            </div>
            <?php if (!$chartHasSales): ?>
                <p class="analytics-chart-empty">За выбранный период продаж не найдено.</p>
            <?php endif; ?>
        </section>

        <section class="analytics-forecast analytics-forecast--<?= h($forecast[0]) ?>">
            <div class="analytics-forecast__heading">
                <span class="analytics-eyebrow">Прогноз наших продаж на ближайший сеанс</span>
                <strong><?= number_format($projectedOwnPercent, 0) ?>% квоты</strong>
            </div>
            <p><?= h($forecast[1]) ?></p>
            <small>Прогноз увеличения квоты основан на сумме сайта и кассы за последние 7 дней; Ticketon.kz и Kino.kz в эту сумму не входят.</small>
        </section>

        <section class="card analytics-history-card">
            <div class="analytics-section-heading">
                <div>
                    <span class="analytics-eyebrow">Предыдущие показы</span>
                    <h2>Как продавались прошлые сеансы</h2>
                </div>
            </div>
            <?php if (!$historyMetrics): ?>
                <p class="analytics-chart-empty">Завершённых сеансов с известной квотой пока нет.</p>
            <?php else: ?>
                <div class="analytics-history-list">
                    <?php foreach ($historyMetrics as $historyMetric): ?>
                        <?php
                        $historyAllocation = (int)$historyMetric['allocated'];
                        $historyOnlinePct = $historyAllocation > 0 ? min(100, ($historyMetric['online'] / $historyAllocation) * 100) : 0;
                        $historyCashPct = $historyAllocation > 0 ? min(100, ($historyMetric['cash'] / $historyAllocation) * 100) : 0;
                        $historyExternalPct = $historyAllocation > 0 ? min(100, ($historyMetric['external'] / $historyAllocation) * 100) : 0;
                        $historyOwnPct = $historyAllocation > 0 ? min(100, (($historyMetric['online'] + $historyMetric['cash']) / $historyAllocation) * 100) : 0;
                        $historyTotalPct = $historyAllocation > 0 ? min(100, ($historyMetric['sold_total'] / $historyAllocation) * 100) : 0;
                        ?>
                        <article class="analytics-history-row">
                            <div class="analytics-history-row__title">
                                <strong><?= h(reporting_format_date($historyMetric['schedule']['start_time'], true)) ?></strong>
                                <span><?= h($historyMetric['schedule']['hall_name'] ?? 'Зал не указан') ?></span>
                            </div>
                            <div class="analytics-history-row__bar" role="img" aria-label="Наши продажи: <?= number_format($historyOwnPct, 0) ?> процентов, внешние площадки: <?= number_format($historyExternalPct, 0) ?> процентов квоты">
                                <i class="analytics-history-row__online" style="width:<?= h(number_format($historyOnlinePct, 2, '.', '')) ?>%"></i>
                                <i class="analytics-history-row__cash" style="width:<?= h(number_format(min($historyCashPct, 100 - $historyOnlinePct), 2, '.', '')) ?>%"></i>
                                <i class="analytics-history-row__external" style="width:<?= h(number_format(min($historyExternalPct, max(0, 100 - $historyOnlinePct - $historyCashPct)), 2, '.', '')) ?>%"></i>
                            </div>
                            <div class="analytics-history-row__metrics">
                                <span>Квота <?= number_format($historyAllocation, 0, '.', ' ') ?></span>
                                <span>Сайт <?= number_format((int)$historyMetric['online'], 0, '.', ' ') ?></span>
                                <span>Касса <?= number_format((int)$historyMetric['cash'], 0, '.', ' ') ?></span>
                                <span>Ticketon/Kino <?= number_format((int)$historyMetric['external'], 0, '.', ' ') ?></span>
                                <span>Всего <?= number_format($historyTotalPct, 0) ?>%</span>
                                <strong>Нами <?= number_format($historyOwnPct, 0) ?>%</strong>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
