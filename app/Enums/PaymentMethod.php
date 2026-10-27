<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PaymentMethod: string implements HasLabel
{
    case Cash = 'cash';
    case GPay = 'gpay';
    case Upi = 'upi';
    case Card = 'card';
    case BankTransfer = 'bank_transfer';
    case Online = 'online';

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
