<?php
// Единые расчёты отчётности по билетам и транзакциям.

if (!function_exists('reporting_decode_payload')) {
    function reporting_decode_payload($payload): array
    {
        if (is_array($payload)) {
            return $payload;
        }
        if (!is_string($payload) || trim($payload) === '') {
            return [];
        }
        $decoded = json_decode($payload, true);
        return is_array($decoded) ? $decoded : [];
    }
}

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

if (!function_exists('reporting_ticket_financials')) {
    function reporting_ticket_financials(array $row): array
    {
        $paid = max(0.0, (float)($row['price'] ?? 0));
        $payload = reporting_decode_payload($row['tx_payload'] ?? null);
        $seatIdentifier = str_replace(':', '-', trim((string)($row['seat_identifier'] ?? '')));

        $seatData = null;
        $seatsFinal = $payload['seats_final'] ?? null;
        if (is_array($seatsFinal)) {
            foreach ($seatsFinal as $seat) {
                if (!is_array($seat)) {
                    continue;
                }
                $candidate = str_replace(':', '-', trim((string)($seat['identifier'] ?? $seat['seat_identifier'] ?? '')));
                if ($seatIdentifier !== '' && $candidate === $seatIdentifier) {
                    $seatData = $seat;
                    break;
                }
            }
        }

        if (is_array($seatData)) {
            $final = isset($seatData['final_price']) && is_numeric($seatData['final_price'])
                ? max(0.0, (float)$seatData['final_price'])
                : $paid;
            $original = isset($seatData['original_price']) && is_numeric($seatData['original_price'])
                ? max($final, (float)$seatData['original_price'])
                : $final;
            $discount = isset($seatData['discount_amount']) && is_numeric($seatData['discount_amount'])
                ? max(0.0, (float)$seatData['discount_amount'])
                : max(0.0, $original - $final);
            return [
                'original' => round($original, 2),
                'discount' => round($discount, 2),
                'paid' => round($final, 2),
            ];
        }

        $storedDiscount = is_numeric($row['discount'] ?? null) ? (float)$row['discount'] : 0.0;
        if ($storedDiscount > 0 && $storedDiscount < 100) {
            $original = $paid / (1 - ($storedDiscount / 100));
            return [
                'original' => round($original, 2),
                'discount' => round($original - $paid, 2),
                'paid' => round($paid, 2),
            ];
        }

        $discount = is_array($payload['discount'] ?? null) ? $payload['discount'] : [];
        $totalDiscount = is_numeric($discount['total_discount'] ?? null)
            ? max(0.0, (float)$discount['total_discount'])
            : 0.0;
        $finalTotal = is_numeric($discount['final_total'] ?? null)
            ? max(0.0, (float)$discount['final_total'])
            : 0.0;
        if ($totalDiscount > 0 && $finalTotal > 0) {
            $allocatedDiscount = $totalDiscount * ($paid / $finalTotal);
            return [
                'original' => round($paid + $allocatedDiscount, 2),
                'discount' => round($allocatedDiscount, 2),
                'paid' => round($paid, 2),
            ];
        }

        return [
            'original' => round($paid, 2),
            'discount' => 0.0,
            'paid' => round($paid, 2),
        ];
    }
}
