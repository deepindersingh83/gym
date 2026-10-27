<?php

namespace App\Services\Messaging;

class PhoneNumber
{
    /** Digits with country code and no "+", as WhatsApp and MSG91 expect (e.g. 919876543210). */
    public static function international(string $phone, ?string $countryCode = null): string
    {
        $countryCode ??= config('gym.whatsapp.default_country_code');
        $hasPlus = str_starts_with(trim($phone), '+');
        $digits = preg_replace('/\D/', '', $phone);

        if ($hasPlus || str_starts_with($digits, '00')) {
            return ltrim($digits, '0');
        }

        $digits = ltrim($digits, '0');

        return strlen($digits) <= 10 ? $countryCode.$digits : $digits;
    }
}
