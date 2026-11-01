<?php

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Pages\Revenue;
use App\Models\Gym;
use App\Models\Member;
use App\Services\Billing\RevenueReport;
use App\Support\AdminAccess;
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

        $mrr = collect(['INR', 'AUD'])
            ->map(fn ($currency) => [$currency, (new RevenueReport($currency))->mrr()])
            ->filter(fn ($row) => $row[1] > 0 || $row[0] === 'INR')
            ->map(fn ($row) => Money::format($row[1], $row[0]))
            ->implode(' + ');

        return [
            Stat::make('Gyms', Gym::query()->count()),
            Stat::make('On trial', Gym::query()->where('status', 'trial')->where('trial_ends_at', '>', now())->count())->color('warning'),
            Stat::make('Paying', $paying->count())->color('success'),
            ...(AdminAccess::can('revenue') ? [Stat::make('Monthly recurring revenue', $mrr)->description('Before GST · see Revenue')->url(Revenue::getUrl())] : []),
            Stat::make('Members across all gyms', number_format(Member::withoutGlobalScopes()->whereNull('deleted_at')->count())),
            Stat::make('Trials ending in 7 days', Gym::query()->where('status', 'trial')->whereBetween('trial_ends_at', [now(), now()->addDays(7)])->count())->color('danger'),
        ];
    }
}
