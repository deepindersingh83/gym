<?php

namespace App\Filament\Gym\Widgets;

use App\Enums\PaymentMethod;
use App\Models\Expense;
use App\Models\Payment;
use App\Support\Access;
use App\Support\Money;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

/** Collections, expenses and profit for the period picked on the dashboard. */
class PeriodSummary extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 2;

    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return Access::can('reports.view');
    }

    protected function getHeading(): ?string
    {
        [$from, $until] = $this->range();

        return 'Profit & loss: '.$from->format('d M Y').' – '.$until->format('d M Y');
    }

    protected function getStats(): array
    {
        [$from, $until] = $this->range();
        $payments = Payment::query()->whereBetween('paid_at', [$from, $until->copy()->endOfDay()]);
        $income = (float) (clone $payments)->sum('amount');
        $expenses = (float) Expense::query()->whereBetween('spent_on', [$from->toDateString(), $until->toDateString()])->sum('amount');
        $byMethod = (clone $payments)->selectRaw('method, SUM(amount) as total')->groupBy('method')->pluck('total', 'method');

        $breakdown = collect(PaymentMethod::cases())
            ->filter(fn ($m) => ($byMethod[$m->value] ?? 0) > 0)
            ->map(fn ($m) => $m->getLabel().' '.Money::format($byMethod[$m->value]))
            ->implode(' · ');

        return [
            Stat::make('Collections', Money::format($income))->description($breakdown ?: 'No payments')->color('success'),
            Stat::make('Expenses', Money::format($expenses))->color('danger'),
            Stat::make('Net profit', Money::format($income - $expenses))->color($income >= $expenses ? 'success' : 'danger'),
        ];
    }

    /** @return array{0: Carbon, 1: Carbon} */
    protected function range(): array
    {
        $from = Carbon::parse($this->pageFilters['from'] ?? today()->startOfMonth())->startOfDay();
        $until = Carbon::parse($this->pageFilters['until'] ?? today())->startOfDay();

        return [$from, $until->max($from)];
    }
}
