<?php

if (!function_exists('general_admission_layout_has_seats')) {
    function general_admission_layout_has_seats($layout): bool
    {
        if (is_string($layout)) {
            $layout = json_decode($layout, true);
        }
        if (!is_array($layout)) {
            return false;
        }
        if (!empty($layout['seats']) && is_array($layout['seats'])) {
            return true;
        }
        foreach (($layout['rows'] ?? []) as $row) {
            foreach (($row['segments'] ?? []) as $segment) {
                if (!is_array($segment)) {
                    continue;
                }
                if (isset($segment['start'], $segment['end']) && is_numeric($segment['start']) && is_numeric($segment['end']) && (int)$segment['start'] > 0 && (int)$segment['end'] >= (int)$segment['start']) {
                    return true;
                }
                if (!empty($segment['seats']) && is_array($segment['seats'])) {
                    return true;
                }
                if (!empty($segment['seatCount']) && (int)$segment['seatCount'] > 0) {
                    return true;
                }
            }
        }
        return false;
    }
}

if (!function_exists('general_admission_is_session')) {
    function general_admission_is_session(array $session): bool
    {
        if ((int)($session['capacity'] ?? 0) <= 0) {
            return false;
        }
        $scheduleMap = $session['seat_map'] ?? null;
        if (general_admission_layout_has_seats($scheduleMap)) {
            return false;
        }
        return !general_admission_layout_has_seats($session['hall_seatmap'] ?? null);
    }
}

if (!function_exists('general_admission_identifier')) {
    function general_admission_identifier(int $number): string
    {
        return 'GA-' . str_pad((string)$number, 5, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('general_admission_identifier_number')) {
    function general_admission_identifier_number(string $identifier): ?int
    {
        if (!preg_match('/^GA-(\d{1,5})$/i', str_replace(':', '-', trim($identifier)), $matches)) {
            return null;
        }
        $number = (int)$matches[1];
        return $number > 0 ? $number : null;
    }
}

if (!function_exists('general_admission_display_label')) {
    function general_admission_display_label(string $identifier): string
    {
        $number = general_admission_identifier_number($identifier);
        return $number !== null ? 'Свободное место № ' . $number : $identifier;
    }
}

if (!function_exists('general_admission_inventory')) {
    function general_admission_inventory(PDO $pdo, int $scheduleId): array
    {
        $soldStmt = $pdo->prepare(
            "SELECT seat_identifier FROM tickets
             WHERE schedule_id = :schedule_id
               AND payment_status = 'paid'
               AND status <> 'cancelled'
               AND COALESCE(refund_status, 'none') <> 'refunded'"
        );
        $soldStmt->execute([':schedule_id' => $scheduleId]);
        $sold = [];
        foreach ($soldStmt->fetchAll(PDO::FETCH_COLUMN, 0) as $identifier) {
            $number = general_admission_identifier_number((string)$identifier);
            if ($number !== null) {
                $sold[$number] = true;
            }
        }

        $heldStmt = $pdo->prepare(
            'SELECT seat_key FROM cash_holds WHERE session_id = :schedule_id AND expires_at > NOW()'
        );
        $heldStmt->execute([':schedule_id' => $scheduleId]);
        $held = [];
        foreach ($heldStmt->fetchAll(PDO::FETCH_COLUMN, 0) as $identifier) {
            $number = general_admission_identifier_number((string)$identifier);
            if ($number !== null) {
                $held[$number] = true;
            }
        }

        return ['sold' => $sold, 'held' => $held];
    }
}

if (!function_exists('general_admission_available_identifiers')) {
    function general_admission_available_identifiers(int $capacity, array $sold, array $held): array
    {
        $available = [];
        for ($number = 1; $number <= $capacity; $number++) {
            if (empty($sold[$number]) && empty($held[$number])) {
                $available[] = general_admission_identifier($number);
            }
        }
        return $available;
    }
}
