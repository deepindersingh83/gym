<?php

namespace App\Filament\Gym\Widgets;

use App\Enums\PaymentMethod;
use App\Models\Payment;
use App\Support\Access;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Carbon;

class CollectionsByMethodChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 4;

    protected ?string $heading = 'Collections by payment mode';

    protected ?string $maxHeight = '260px';

    public static function canView(): bool
    {
        return Access::can('reports.view');
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $from = Carbon::parse($this->pageFilters['from'] ?? today()->startOfMonth())->startOfDay();
        $until = Carbon::parse($this->pageFilters['until'] ?? today())->endOfDay();

        $totals = Payment::query()->whereBetween('paid_at', [$from, $until])
            ->selectRaw('method, SUM(amount) as total')->groupBy('method')->pluck('total', 'method');

        $methods = collect(PaymentMethod::cases())->filter(fn ($m) => ($totals[$m->value] ?? 0) > 0)->values();

        return [
            'datasets' => [[
                'data' => $methods->map(fn ($m) => round((float) $totals[$m->value], 2))->all(),
                'backgroundColor' => ['#16a34a', '#2563eb', '#9333ea', '#f59e0b', '#0891b2', '#e11d48'],
            ]],
            'labels' => $methods->map->getLabel()->all(),
        ];
    }
}
