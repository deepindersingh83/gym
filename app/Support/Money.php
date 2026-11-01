<?php

namespace App\Support;

class Money
{
    public static function symbol(?string $currency = null): string
    {
        return match ($currency ?? Tenancy::gym()?->currency ?? 'INR') {
            'INR' => '₹',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'AUD' => '$',
            default => ($currency ?? '').' ',
        };
    }

    public static function format(mixed $amount, ?string $currency = null): string
    {
        return static::symbol($currency).number_format((float) $amount, 2);
    }
}
