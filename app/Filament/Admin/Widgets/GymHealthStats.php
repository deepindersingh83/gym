<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Pages\Usage;
use App\Services\GymHealth;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class GymHealthStats extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected static ?int $sort = 2;

    protected function getHeading(): ?string
    {
        return 'Gym health';
    }

    protected function getStats(): array
    {
        $health = app(GymHealth::class);
        $url = fn (string $status) => Usage::getUrl(['filters' => ['health' => ['value' => $status]]]);

        return [
            Stat::make('Active', count($health->idsWithStatus('active')))->color('success')->description('Using it regularly')->url($url('active')),
            Stat::make('At risk', count($health->idsWithStatus('at_risk')))->color('warning')->description('Call them this week')->url($url('at_risk')),
            Stat::make('Inactive', count($health->idsWithStatus('inactive')))->color('danger')->description('Quiet for 30+ days or lapsed')->url($url('inactive')),
        ];
    }
}
