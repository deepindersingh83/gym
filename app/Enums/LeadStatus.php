<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum LeadStatus: string implements HasColor, HasLabel
{
    case New = 'new';
    case Contacted = 'contacted';
    case TrialBooked = 'trial_booked';
    case Converted = 'converted';
    case Lost = 'lost';

    public function getLabel(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Contacted => 'Contacted',
            self::TrialBooked => 'Trial booked',
            self::Converted => 'Joined',
            self::Lost => 'Lost',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::New => 'info',
            self::Contacted => 'warning',
            self::TrialBooked => 'primary',
            self::Converted => 'success',
            self::Lost => 'gray',
        };
    }

    /** @return list<self> Statuses that still need someone to act. */
    public static function open(): array
    {
        return [self::New, self::Contacted, self::TrialBooked];
    }
}
