<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Gym;
use App\Models\Member;
use App\Support\Money;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PlatformStats extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $paying = Gym::query()->where('status', 'active')->with('saasPlan')->get()
            ->filter->hasActiveSaasSubscription();

        return [
            Stat::make('Gyms', Gym::query()->count()),
            Stat::make('On trial', Gym::query()->where('status', 'trial')->where('trial_ends_at', '>', now())->count())->color('warning'),
            Stat::make('Paying', $paying->count())->color('success'),
            Stat::make('Monthly recurring revenue', Money::format($paying->sum(fn (Gym $gym) => (float) $gym->saasPlan?->monthly_price), 'INR')),
            Stat::make('Members across all gyms', number_format(Member::withoutGlobalScopes()->whereNull('deleted_at')->count())),
            Stat::make('Trials ending in 7 days', Gym::query()->where('status', 'trial')->whereBetween('trial_ends_at', [now(), now()->addDays(7)])->count())->color('danger'),
        ];
    }
}
