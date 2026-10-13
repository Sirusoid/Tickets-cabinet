<?php

require_once __DIR__ . '/settings_manager.php';

if (!function_exists('promo_normalize_code')) {
    function promo_normalize_code(string $code): string
    {
        return strtoupper(trim($code));
    }
}

if (!function_exists('promo_feature_enabled')) {
    // Без записи в настройках функция считается включённой.
    function promo_feature_enabled(PDO $pdo): bool
    {
        try {
            $value = settings_get_value($pdo, 'promo.enabled', '1');
        } catch (Throwable $exception) {
            return true;
        }
        return !in_array(strtolower(trim((string)$value)), ['0', 'false', 'off', 'no'], true);
    }
}

if (!function_exists('promo_set_feature_enabled')) {
    function promo_set_feature_enabled(PDO $pdo, bool $enabled): bool
    {
        $value = $enabled ? '1' : '0';
        settings_upsert_value(
            $pdo,
            'promo.enabled',
            'Промокоды при онлайн-покупке',
            $value,
            'bool',
            'promo',
            'Если выключено, поле промокода скрыто в форме покупки и скидки не применяются.',
            0,
            10
        );
        return settings_set_value($pdo, 'promo.enabled', $value);
    }
}

if (!function_exists('promo_has_description_column')) {
    function promo_has_description_column(PDO $pdo, bool $createIfMissing = false): bool
    {
        static $known = false;
        if ($known) {
            return true;
        }
        try {
            $exists = (bool)$pdo->query("SHOW COLUMNS FROM promo_codes LIKE 'description'")->fetch(PDO::FETCH_ASSOC);
            if (!$exists && $createIfMissing) {
                $pdo->exec('ALTER TABLE promo_codes ADD COLUMN description VARCHAR(255) NULL DEFAULT NULL AFTER discount_value');
                $exists = true;
            }
        } catch (Throwable $exception) {
            error_log('promo description column check failed: ' . $exception->getMessage());
            return false;
        }
        return $known = $exists;
    }
}

if (!function_exists('promo_discount_label')) {
    function promo_discount_label(array $promo): string
    {
        $value = (float)($promo['discount_value'] ?? 0);
        if ((string)($promo['discount_type'] ?? '') === 'percent') {
            return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.') . '%';
        }
        return rtrim(rtrim(number_format($value, 2, '.', ' '), '0'), '.') . ' ₸';
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

        if (!promo_feature_enabled($pdo)) {
            throw new InvalidArgumentException('Промокоды сейчас не принимаются.');
        }

        $stmt = $pdo->prepare('SELECT * FROM promo_codes WHERE code = :code AND is_active = 1 LIMIT 1');
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
