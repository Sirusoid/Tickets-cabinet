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

if (!function_exists('reporting_discount_label')) {
    function reporting_discount_label(array $row): string
    {
        $discountAmount = is_numeric($row['discount_amount'] ?? null) ? (float)$row['discount_amount'] : 0.0;
        $segment = (string)($row['customer_segment'] ?? '');
        if ($discountAmount <= 0) {
            return 'Без скидки';
        }
        if ($discountAmount > 0 && $segment === 'manual') {
            return 'Ручная, фиксированная';
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
