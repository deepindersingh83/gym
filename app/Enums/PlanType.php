<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PlanType: string implements HasLabel
{
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case HalfYearly = 'half_yearly';
    case Yearly = 'yearly';
    case DayPass = 'day_pass';
    case PersonalTraining = 'personal_training';
    case Couple = 'couple';
    case Family = 'family';
    case Custom = 'custom';

    public function getLabel(): string
    {
        return match ($this) {
            self::Monthly => 'Monthly',
            self::Quarterly => 'Quarterly (3 months)',
            self::HalfYearly => 'Half-yearly (6 months)',
            self::Yearly => 'Yearly',
            self::DayPass => 'Day pass',
            self::PersonalTraining => 'Personal training package',
            self::Couple => 'Couple',
            self::Family => 'Family',
            self::Custom => 'Custom',
        };
    }

    /** @return array{0: int, 1: string, 2: int} Suggested duration value, unit and number of persons. */
    public function defaults(): array
    {
        return match ($this) {
            self::Monthly, self::PersonalTraining, self::Custom => [1, 'month', 1],
            self::Quarterly => [3, 'month', 1],
            self::HalfYearly => [6, 'month', 1],
            self::Yearly => [12, 'month', 1],
            self::DayPass => [1, 'day', 1],
            self::Couple => [12, 'month', 2],
            self::Family => [12, 'month', 4],
        };
    }
}
