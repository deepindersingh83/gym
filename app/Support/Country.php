<?php

namespace App\Support;

use App\Models\Gym;

/** Everything that differs between the countries the platform sells in. */
class Country
{
    public const ALL = [
        'IN' => [
            'name' => 'India',
            'currency' => 'INR',
            'phone_code' => '91',
            'tax_inclusive' => false, // GST is added to prices and split into CGST + SGST
            'tax_rate' => 18,
            'timezones' => ['Asia/Kolkata' => 'India (IST)'],
            'payment_methods' => ['cash', 'gpay', 'upi', 'card', 'bank_transfer', 'online'],
        ],
        'AU' => [
            'name' => 'Australia',
            'currency' => 'AUD',
            'phone_code' => '61',
            'tax_inclusive' => true, // prices include 10% GST
            'tax_rate' => 10,
            'timezones' => [
                'Australia/Sydney' => 'Sydney, Canberra (NSW, ACT)',
                'Australia/Melbourne' => 'Melbourne (VIC)',
                'Australia/Brisbane' => 'Brisbane (QLD)',
                'Australia/Adelaide' => 'Adelaide (SA)',
                'Australia/Perth' => 'Perth (WA)',
                'Australia/Hobart' => 'Hobart (TAS)',
                'Australia/Darwin' => 'Darwin (NT)',
            ],
            'payment_methods' => ['cash', 'card', 'bank_transfer', 'online'],
        ],
    ];

    /** @return array<string, string> code => name */
    public static function options(): array
    {
        return array_map(fn ($c) => $c['name'], self::ALL);
    }

    public static function get(?string $code, string $key): mixed
    {
        return self::ALL[$code ?? 'IN'][$key] ?? self::ALL['IN'][$key];
    }

    public static function of(?Gym $gym = null): string
    {
        $gym ??= Tenancy::gym();

        return array_key_exists((string) $gym?->country, self::ALL) ? $gym->country : 'IN';
    }

    public static function isAustralia(?Gym $gym = null): bool
    {
        return static::of($gym) === 'AU';
    }
}
