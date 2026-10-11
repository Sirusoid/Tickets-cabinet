<?php

if (!function_exists('promo_normalize_code')) {
    function promo_normalize_code(string $code): string
    {
        return strtoupper(trim($code));
    }
}

if (!function_exists('promo_discount_cents')) {
    function promo_discount_cents(array $promo, int $subtotalCents): int
    {
        if ($subtotalCents <= 0) {
            return 0;
        }

        $value = (float)($promo['discount_value'] ?? 0);
        if ($value <= 0) {
            return 0;
        }

        $discountCents = (string)($promo['discount_type'] ?? '') === 'percent'
            ? (int)round($subtotalCents * min(100, $value) / 100)
            : (int)round($value * 100);

        return min($subtotalCents, max(0, $discountCents));
    }
}

if (!function_exists('promo_apply_to_seats')) {
    function promo_apply_to_seats(PDO $pdo, string $code, array $seats): array
    {
        $code = promo_normalize_code($code);
        if ($code === '') {
            return ['promo' => null, 'discount_cents' => 0, 'seats' => $seats];
        }

        $stmt = $pdo->prepare('SELECT code, discount_type, discount_value FROM promo_codes WHERE code = :code AND is_active = 1 LIMIT 1');
        $stmt->execute([':code' => $code]);
        $promo = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$promo) {
            throw new InvalidArgumentException('Промокод не найден или уже отключён.');
        }

        $priceCents = [];
        $subtotalCents = 0;
        foreach ($seats as $index => $seat) {
            $cents = (int)round((float)($seat['original_price'] ?? 0) * 100);
            if ($cents <= 0) {
                throw new InvalidArgumentException('Не удалось рассчитать стоимость выбранных мест.');
            }
            $priceCents[$index] = $cents;
            $subtotalCents += $cents;
        }

        $discountCents = promo_discount_cents($promo, $subtotalCents);
        if ($discountCents <= 0) {
            throw new InvalidArgumentException('Для этого заказа размер скидки по промокоду равен нулю.');
        }

        $allocatedCents = 0;
        $lastIndex = array_key_last($seats);
        foreach ($seats as $index => &$seat) {
            $seatDiscount = $index === $lastIndex
                ? $discountCents - $allocatedCents
                : (int)floor($discountCents * $priceCents[$index] / $subtotalCents);
            $seatDiscount = min($priceCents[$index], max(0, $seatDiscount));
            $allocatedCents += $seatDiscount;
            $seat['final_price'] = ($priceCents[$index] - $seatDiscount) / 100;
            $seat['promo_code'] = $code;
        }
        unset($seat);

        return ['promo' => $promo, 'discount_cents' => $discountCents, 'seats' => $seats];
    }
}
