<?php

namespace App\Enums;

use App\Models\Gym;
use App\Support\Country;
use Filament\Support\Contracts\HasLabel;

enum PaymentMethod: string implements HasLabel
{
    case Cash = 'cash';
    case GPay = 'gpay';
    case Upi = 'upi';
    case Card = 'card';
    case BankTransfer = 'bank_transfer';
    case Online = 'online';

    /** @return array<string, string> Payment methods used in the gym's country (no UPI / GPay in Australia). */
    public static function options(?Gym $gym = null): array
    {
        return collect(Country::get(Country::of($gym), 'payment_methods'))
            ->mapWithKeys(fn (string $value) => [$value => self::from($value)->labelFor(Country::of($gym))])
            ->all();
    }

    public function labelFor(string $country): string
    {
        return $country === 'AU' && $this === self::BankTransfer ? 'Bank transfer / PayID' : $this->getLabel();
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::GPay => 'GPay',
            self::Upi => 'UPI (other apps)',
            self::Card => 'Card',
            self::BankTransfer => 'Bank transfer',
            self::Online => 'Online (Razorpay)',
        };
    }
}
