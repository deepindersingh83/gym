<?php

namespace App\Filament\Partner\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PartnerStats extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $partner = auth()->user()->partner;
        $gyms = $partner->gyms()->get();
        $paying = $gyms->filter(fn ($gym) => $gym->status === 'active' && $gym->hasActiveSaasSubscription())->count();
        $money = fn ($v) => '₹'.number_format((float) $v, 2);

        return [
            Stat::make('Gyms referred', $gyms->count())->description($paying.' paying'),
            Stat::make('On free trial', $gyms->filter->onTrial()->count()),
            Stat::make('Commission to be paid', $money($partner->commissions()->where('status', 'pending')->sum('amount')))->color('warning'),
            Stat::make('Commission paid', $money($partner->commissions()->where('status', 'paid')->sum('amount')))->color('success'),
        ];
    }
}
