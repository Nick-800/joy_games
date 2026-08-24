<?php

namespace App\Services\Billing;

class CurrencyHelper
{
    public static function formatLyd(int $millimes, bool $includeSymbol = true): string
    {
        $amount = number_format($millimes / 1000, 3, '.', '');

        return $includeSymbol ? "{$amount} LYD" : $amount;
    }

    public static function formatArabicLyd(int $millimes): string
    {
        $amount = number_format($millimes / 1000, 3, '.', '');

        return "{$amount} د.ل";
    }

    public static function toMillimes(float $lyd): int
    {
        return (int) round($lyd * 1000);
    }
}
