<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum StaffRole: string implements HasLabel
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Trainer = 'trainer';
    case Receptionist = 'receptionist';

    public function getLabel(): string
    {
        return match ($this) {
            self::Owner => 'Gym Owner',
            self::Manager => 'Branch Manager',
            self::Trainer => 'Trainer',
            self::Receptionist => 'Receptionist',
        };
    }

    /** Roles that see every branch of the gym rather than only their own. */
    public function seesAllBranches(): bool
    {
        return $this === self::Owner;
    }
}
