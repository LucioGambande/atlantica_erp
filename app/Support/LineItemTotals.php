<?php

namespace App\Support;

class LineItemTotals
{
    public static function discountedLineTotal(float $unitPrice, int $quantity, float $discountPercent): float
    {
        $discountPercent = max(0, min(100, $discountPercent));

        return round($unitPrice * $quantity * (1 - $discountPercent / 100), 2);
    }
}
