<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum LeadSource: string implements HasLabel
{
    case WalkIn = 'walk_in';
    case Phone = 'phone';
    case Website = 'website';
    case Facebook = 'facebook';
    case Instagram = 'instagram';
    case Referral = 'referral';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::WalkIn => 'Walk-in',
            self::Phone => 'Phone call',
            self::Website => 'Website',
            self::Facebook => 'Facebook',
            self::Instagram => 'Instagram',
            self::Referral => 'Referral',
            self::Other => 'Other',
        };
    }
}
