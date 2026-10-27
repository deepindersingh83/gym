<?php

namespace App\Filament\Gym\Widgets;

use App\Models\Expense;
use App\Models\Payment;
use App\Support\Access;
use Filament\Widgets\ChartWidget;

class RevenueChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Collections vs expenses (last 12 months)';

    protected ?string $maxHeight = '260px';

    public static function canView(): bool
    {
        return Access::can('reports.view');
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $start = today()->startOfMonth()->subMonths(11);
        $months = collect(range(0, 11))->map(fn ($i) => $start->copy()->addMonths($i));

        $income = Payment::query()->where('paid_at', '>=', $start)->get(['paid_at', 'amount'])
            ->groupBy(fn ($p) => $p->paid_at->format('Y-m'))->map->sum('amount');
        $expenses = Expense::query()->whereDate('spent_on', '>=', $start)->get(['spent_on', 'amount'])
            ->groupBy(fn ($e) => $e->spent_on->format('Y-m'))->map->sum('amount');

        return [
            'datasets' => [
                ['label' => 'Collections', 'data' => $months->map(fn ($m) => round((float) ($income[$m->format('Y-m')] ?? 0), 2))->all(), 'backgroundColor' => '#16a34a'],
                ['label' => 'Expenses', 'data' => $months->map(fn ($m) => round((float) ($expenses[$m->format('Y-m')] ?? 0), 2))->all(), 'backgroundColor' => '#e11d48'],
            ],
            'labels' => $months->map(fn ($m) => $m->format('M y'))->all(),
        ];
    }
}
