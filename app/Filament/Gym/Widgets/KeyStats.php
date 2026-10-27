<?php

namespace App\Filament\Gym\Widgets;

use App\Enums\StaffRole;
use App\Models\Member;
use App\Models\Payment;
use App\Models\Subscription;
use App\Support\Access;
use App\Support\Money;
use App\Support\Tenancy;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Headline numbers for the owner: revenue, members, renewals and dues. */
class KeyStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return in_array(Tenancy::role(), [StaffRole::Owner, StaffRole::Manager, StaffRole::Receptionist], true);
    }

    protected function getColumns(): int
    {
        return Access::can('reports.view') ? 4 : 3;
    }

    protected function getStats(): array
    {
        $activeNow = fn ($q) => $q->active()->whereDate('end_date', '>=', today());
        $active = Member::query()->whereHas('subscriptions', $activeNow)->count();
        $expired = Member::query()->whereHas('subscriptions')->whereDoesntHave('subscriptions', $activeNow)->count();
        $renewals = Subscription::query()->active()->endingBetween(today(), today()->addDays(7))->count();
        $dues = (float) Subscription::query()->withDues()->selectRaw('COALESCE(SUM(total_amount - amount_paid), 0) as due')->value('due');

        $stats = [];

        if (Access::can('reports.view')) {
            $sum = fn ($from) => (float) Payment::query()->where('paid_at', '>=', $from)->sum('amount');
            $daily = collect(range(13, 0))->map(fn ($d) => (float) Payment::query()->whereDate('paid_at', today()->subDays($d))->sum('amount'))->all();

            $stats[] = Stat::make('Revenue today', Money::format($sum(today())))->chart($daily)->color('success');
            $stats[] = Stat::make('Revenue this month', Money::format($sum(today()->startOfMonth())));
            $stats[] = Stat::make('Revenue this year', Money::format($sum(today()->startOfYear())));
        }

        $stats[] = Stat::make('Active members', number_format($active))->color('success')->icon('heroicon-o-user-group');
        $stats[] = Stat::make('Expired members', number_format($expired))->color('danger')
            ->description('Win them back with a broadcast')->icon('heroicon-o-user-minus');
        $stats[] = Stat::make('Renewals due this week', number_format($renewals))->color('warning')->icon('heroicon-o-arrow-path');
        $stats[] = Stat::make('Dues outstanding', Money::format($dues))->color($dues > 0 ? 'danger' : 'success')->icon('heroicon-o-exclamation-triangle');

        return $stats;
    }
}
