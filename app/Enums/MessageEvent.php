<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum MessageEvent: string implements HasLabel
{
    case RenewalReminder = 'renewal_reminder';
    case Expired = 'expired';
    case DuesReminder = 'dues_reminder';
    case Birthday = 'birthday';
    case Anniversary = 'anniversary';
    case PaymentReceived = 'payment_received';
    case Broadcast = 'broadcast';

    public function getLabel(): string
    {
        return match ($this) {
            self::RenewalReminder => 'Renewal reminder',
            self::Expired => 'Membership expired',
            self::DuesReminder => 'Pending dues',
            self::Birthday => 'Birthday wish',
            self::Anniversary => 'Anniversary wish',
            self::PaymentReceived => 'Payment received',
            self::Broadcast => 'Broadcast',
        };
    }

    /** Default text; gyms can override per channel in Message templates. */
    public function defaultBody(): string
    {
        return match ($this) {
            self::RenewalReminder => 'Hi {name}, your {plan} membership at {gym} ends on {end_date}. Renew now to keep training without a break.',
            self::Expired => 'Hi {name}, your {plan} membership at {gym} expired on {end_date}. Visit the front desk or renew online to continue.',
            self::DuesReminder => 'Hi {name}, you have a pending balance of {currency}{due} at {gym}. Please clear it at your next visit.',
            self::Birthday => 'Happy birthday {name}! Wishing you a strong and healthy year ahead. - Team {gym}',
            self::Anniversary => 'Happy anniversary {name}! Best wishes from all of us at {gym}.',
            self::PaymentReceived => 'Hi {name}, we received {currency}{amount} at {gym}. Receipt: {receipt}. Thank you!',
            self::Broadcast => '{message}',
        };
    }
}
