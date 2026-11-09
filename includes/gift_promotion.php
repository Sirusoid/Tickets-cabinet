<?php

if (!function_exists('gift_promotion_is_enabled')) {
    function gift_promotion_is_enabled(array $schedule): bool
    {
        return (int)($schedule['promotion_enabled'] ?? 0) === 1
            && trim((string)($schedule['promotion_gift_name'] ?? '')) !== ''
            && (int)($schedule['promotion_gift_price'] ?? 0) > 0;
    }
}

if (!function_exists('gift_promotion_details')) {
    function gift_promotion_details(array $schedule): array
    {
        $enabled = gift_promotion_is_enabled($schedule);
        return [
            'enabled' => $enabled,
            'gift_name' => $enabled ? trim((string)$schedule['promotion_gift_name']) : '',
            'gift_price' => $enabled ? max(0, (int)$schedule['promotion_gift_price']) : 0,
        ];
    }
}

if (!function_exists('gift_promotion_selected')) {
    function gift_promotion_selected($value): bool
    {
        return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on'], true);
    }
}

if (!function_exists('gift_promotion_apply_to_seat')) {
    function gift_promotion_apply_to_seat(array $seat, array $schedule): array
    {
        $promotion = gift_promotion_details($schedule);
        $selected = $promotion['enabled'] && gift_promotion_selected($seat['gift_selected'] ?? false);
        $seat['gift_selected'] = $selected ? 1 : 0;
        $seat['gift_name'] = $selected ? $promotion['gift_name'] : null;
        $seat['gift_price'] = $selected ? $promotion['gift_price'] : 0;
        return $seat;
    }
}

if (!function_exists('gift_promotion_normalize_config')) {
    function gift_promotion_normalize_config($enabled, $name, $price): array
    {
        $enabled = gift_promotion_selected($enabled);
        $name = trim((string)$name);
        $price = filter_var($price, FILTER_VALIDATE_INT);
        if ($price === false || $price === null) {
            $price = 0;
        }
        $price = max(0, $price);

        if (!$enabled) {
            return ['enabled' => 0, 'name' => null, 'price' => 0];
        }
        if ($name === '') {
            throw new InvalidArgumentException('Укажите название подарка для акции.');
        }
        if ($price < 1) {
            throw new InvalidArgumentException('Цена подарка должна быть целым числом больше нуля.');
        }
        return ['enabled' => 1, 'name' => $name, 'price' => $price];
    }
}
