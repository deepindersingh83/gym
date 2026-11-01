<?php

namespace App\Services\Messaging;

class PhoneNumber
{
    /**
     * Digits with country code and no "+", as WhatsApp and MSG91 expect
     * (919876543210, 61412345678). Without a country code given, Australian
     * mobiles (04xx xxx xxx) are recognised and anything else is treated as
     * Indian, so a member can type their number the way they always do.
     */
    public static function international(string $phone, ?string $countryCode = null): string
    {
        $hasPlus = str_starts_with(trim($phone), '+');
        $digits = preg_replace('/\D/', '', $phone);

        if ($hasPlus || str_starts_with($digits, '00')) {
            return ltrim($digits, '0');
        }

        if ($countryCode === null && preg_match('/^0?4\d{8}$/', $digits)) {
            return '61'.ltrim($digits, '0');
        }

        $countryCode ??= (string) config('gym.whatsapp.default_country_code', '91');
        $digits = ltrim($digits, '0');

        // Already includes the country code, e.g. 919876543210 or 61412345678.
        if (str_starts_with($digits, $countryCode) && strlen($digits) >= strlen($countryCode) + 9) {
            return $digits;
        }

        return $countryCode.$digits;
    }
}
