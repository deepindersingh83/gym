<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ExpenseCategory: string implements HasLabel
{
    case Rent = 'rent';
    case Salary = 'salary';
    case Equipment = 'equipment';
    case Electricity = 'electricity';
    case Maintenance = 'maintenance';
    case Marketing = 'marketing';
    case Other = 'other';

    public function getLabel(): string
    {
        return ucfirst($this->value);
    }
}
