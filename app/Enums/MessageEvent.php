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
    case ReviewRequest = 'review_request';
    case LoginCode = 'login_code';
    case AppInvite = 'app_invite';
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
            self::ReviewRequest => 'Google review request',
            self::LoginCode => 'Member app login code',
            self::AppInvite => 'Member app invite',
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
            self::ReviewRequest => 'Hi {name}, you have completed {visits} workouts at {gym} — amazing! 💪 Would you take 30 seconds to rate us on Google? It really helps: {review_link}',
            self::LoginCode => '{code} is your {gym} login code. It expires in 10 minutes. Do not share it with anyone.',
            self::AppInvite => 'Hi {name}, your {gym} member app is ready: see your membership, payments, receipts and progress. Open {app_url} and sign in with your mobile number and a one-time code.',
            self::Broadcast => '{message}',
        };
    }
}
