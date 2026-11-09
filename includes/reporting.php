<?php
// Единые расчёты отчётности по билетам и транзакциям.

if (!function_exists('reporting_segment_label')) {
    function reporting_segment_label($segment): string
    {
        $labels = [
            'adult' => 'Взрослый',
            'child' => 'Детский',
            'children' => 'Детский',
            'student' => 'Студенческий',
            'senior' => 'Пенсионный',
            'pensioner' => 'Пенсионный',
            'vip' => 'VIP',
            'manual' => 'Ручная скидка',
        ];
        $key = strtolower(trim((string)$segment));
        return $labels[$key] ?? ($key !== '' ? $key : 'Не указан');
    }
}

if (!function_exists('reporting_format_date')) {
    function reporting_format_date($value, bool $withTime = false): string
    {
        $timestamp = is_int($value) ? $value : strtotime((string)$value);
        if ($timestamp === false) {
            return '';
        }
        $months = [
            1 => 'янв.', 2 => 'февр.', 3 => 'март', 4 => 'апр.',
            5 => 'май', 6 => 'июнь', 7 => 'июль', 8 => 'авг.',
            9 => 'сен.', 10 => 'окт.', 11 => 'нояб.', 12 => 'дек.',
        ];
        $formatted = date('d', $timestamp) . ' ' . $months[(int)date('n', $timestamp)] . ' ' . date('Y', $timestamp);
        if ($withTime) {
            $formatted .= ' ' . date('H:i', $timestamp);
        }
        return $formatted;
    }
}

if (!function_exists('reporting_format_date_range')) {
    function reporting_format_date_range($from, $to): string
    {
        $fromFormatted = reporting_format_date($from);
        $toFormatted = reporting_format_date($to);
        if ($fromFormatted === '' || $toFormatted === '') {
            return trim((string)$from . ' — ' . (string)$to);
        }
        return $fromFormatted . ' — ' . $toFormatted;
    }
}

if (!function_exists('reporting_payment_label')) {
    function reporting_payment_label($method, $channel = ''): string
    {
        $method = strtolower(trim((string)$method));
        $channel = strtolower(trim((string)$channel));
        if (
            $method !== ''
            && (strpos($method, 'card') !== false
                || strpos($method, 'visa') !== false
                || strpos($method, 'master') !== false
                || $method === 'bcc')
        ) {
            return 'Карта';
        }
        if (in_array($method, ['cash', 'nal', 'cashier'], true) || $channel === 'kassa') {
            return 'Наличные';
        }
        if (in_array($channel, ['web', 'online', 'mobile'], true)) {
            return 'Карта';
        }
        return $method !== '' ? $method : 'Другое';
    }
}

if (!function_exists('reporting_source_options')) {
    function reporting_source_options(): array
    {
        return [
            'cash_cash' => 'Касса наличные',
            'cash_card' => 'Касса картой',
            'online' => 'Онлайн',
        ];
    }
}

if (!function_exists('reporting_source_key')) {
    function reporting_source_key($method, $channel): string
    {
        $method = strtolower(trim((string)$method));
        $channel = strtolower(trim((string)$channel));
        if ($channel !== 'kassa') {
            return 'online';
        }

        $isCard = strpos($method, 'card') !== false
            || in_array($method, ['bcc', 'terminal', 'visa', 'master', 'mastercard'], true);
        return $isCard ? 'cash_card' : 'cash_cash';
    }
}

if (!function_exists('reporting_source_label')) {
    function reporting_source_label($method, $channel): string
    {
        $options = reporting_source_options();
        return $options[reporting_source_key($method, $channel)] ?? 'Онлайн';
    }
}

if (!function_exists('reporting_normalize_source_filter')) {
    function reporting_normalize_source_filter($raw, bool $isProvided = false): array
    {
        $options = reporting_source_options();
        if (!$isProvided) {
            return array_keys($options);
        }

        if (!is_array($raw)) {
            $raw = [$raw];
        }

        return array_values(array_intersect(array_keys($options), array_map('strval', $raw)));
    }
}

if (!function_exists('reporting_source_sql')) {
    function reporting_source_sql(array $selectedSources): string
    {
        $selectedSources = array_values(array_intersect(array_keys(reporting_source_options()), $selectedSources));
        if ($selectedSources === []) {
            return ' AND 1 = 0 ';
        }

        $cardMethodSql = "LOWER(COALESCE(tx.payment_method, ''))";
        $cardMethodCondition = sprintf(
            "(%1\$s LIKE '%%card%%' OR %1\$s IN ('bcc', 'terminal', 'visa', 'master', 'mastercard'))",
            $cardMethodSql
        );
        $conditions = [];

        if (in_array('cash_cash', $selectedSources, true)) {
            $conditions[] = "(t.channel = 'kassa' AND NOT {$cardMethodCondition})";
        }
        if (in_array('cash_card', $selectedSources, true)) {
            $conditions[] = "(t.channel = 'kassa' AND {$cardMethodCondition})";
        }
        if (in_array('online', $selectedSources, true)) {
            $conditions[] = "(COALESCE(t.channel, '') <> 'kassa')";
        }

        return $conditions === [] ? ' AND 1 = 0 ' : ' AND (' . implode(' OR ', $conditions) . ') ';
    }
}

if (!function_exists('reporting_discount_label')) {
    function reporting_discount_label(array $row): string
    {
        $discountAmount = is_numeric($row['discount_amount'] ?? null) ? (float)$row['discount_amount'] : 0.0;
        $segment = (string)($row['customer_segment'] ?? '');
        if ($discountAmount <= 0) {
            return 'Без скидки';
        }
        if ($discountAmount > 0 && $segment === 'manual') {
            return 'Ручная скидка';
        }
        $promoCode = trim((string)($row['promo_code'] ?? ''));
        if ($discountAmount > 0 && $promoCode !== '') {
            return 'Промокод ' . $promoCode;
        }
        if ($discountAmount > 0 && $segment !== '') {
            return 'По типу: ' . reporting_segment_label($segment);
        }
        return 'Скидка';
    }
}

if (!function_exists('reporting_ticket_financials')) {
    function reporting_ticket_financials(array $row): array
    {
        $canonicalOriginal = is_numeric($row['original_price'] ?? null) ? (float)$row['original_price'] : 0.0;
        $canonicalFinal = is_numeric($row['final_price'] ?? null) ? (float)$row['final_price'] : 0.0;
        $canonicalDiscount = is_numeric($row['discount_amount'] ?? null) ? (float)$row['discount_amount'] : 0.0;
        if ($canonicalOriginal > 0 || $canonicalFinal > 0 || $canonicalDiscount > 0) {
            $paid = max(0.0, $canonicalFinal);
            $original = max($paid, $canonicalOriginal > 0 ? $canonicalOriginal : $paid + $canonicalDiscount);
            $discount = max(0.0, $canonicalDiscount > 0 ? $canonicalDiscount : $original - $paid);
            return [
                'original' => round($original, 2),
                'discount' => round($discount, 2),
                'paid' => round($paid, 2),
            ];
        }

        return [
            'original' => round($canonicalOriginal, 2),
            'discount' => round($canonicalDiscount, 2),
            'paid' => round($canonicalFinal, 2),
        ];
    }
}
