<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum MessageChannel: string implements HasLabel
{
    case WhatsApp = 'whatsapp';
    case Sms = 'sms';
    case Email = 'email';
    case InApp = 'in_app';

    public function getLabel(): string
    {
        return match ($this) {
            self::WhatsApp => 'WhatsApp',
            self::Sms => 'SMS',
            self::Email => 'Email',
            self::InApp => 'Member app notification',
        };
    }
}
