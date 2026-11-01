<?php

namespace App\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

/** Roles of the people who run the platform (not gym staff). */
enum AdminRole: string implements HasDescription, HasLabel
{
    case Owner = 'owner';
    case Support = 'support';
    case Sales = 'sales';
    case Finance = 'finance';

    public function getLabel(): string
    {
        return ucfirst($this->value);
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Owner => 'Everything, including settings, plans and the admin team.',
            self::Support => 'Help gyms: open their panel, extend trials, reset owner passwords, add credits.',
            self::Sales => 'Gyms, trials and partners. No payments or settings.',
            self::Finance => 'Payments, invoices, commissions and revenue. Cannot open gym panels.',
        };
    }
}
