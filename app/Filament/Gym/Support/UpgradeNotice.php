<?php

namespace App\Filament\Gym\Support;

use App\Support\Modules;
use Filament\Schemas\Components\Callout;

/** Shown in place of a feature the gym's plan does not include. */
class UpgradeNotice
{
    public static function make(string $module): Callout
    {
        return Callout::make(Modules::label($module).' is not included in your plan')
            ->description(Modules::descriptions()[$module].' Upgrade your plan to turn it on.')
            ->warning()
            ->visible(fn () => ! Modules::enabled($module));
    }
}
