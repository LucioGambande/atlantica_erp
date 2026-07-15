<?php

namespace App\Support;

class VatTotals
{
    public static function rate(): float
    {
        return (float) config('invoices.default_vat_rate', 0.21);
    }

    public static function factor(): float
    {
        return round(1 + static::rate(), 4);
    }

    public static function grossFromNet(float $net): float
    {
        if ($net <= 0) {
            return 0.0;
        }

        return round($net * static::factor(), 2);
    }

    public static function netFromGross(float $gross): float
    {
        if ($gross <= 0) {
            return 0.0;
        }

        return round($gross / static::factor(), 2);
    }
}
