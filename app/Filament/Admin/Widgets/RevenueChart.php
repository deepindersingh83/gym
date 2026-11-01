<?php

namespace App\Filament\Admin\Widgets;

use App\Services\Billing\RevenueReport;
use App\Support\Money;
use Filament\Widgets\ChartWidget;

/** Monthly net revenue (before GST) for one currency, shown on the Revenue page. */
class RevenueChart extends ChartWidget
{
    protected static bool $isDiscovered = false;

    protected ?string $maxHeight = '300px';

    protected int|string|array $columnSpan = 'full';

    public string $currency = 'INR';

    public function getHeading(): ?string
    {
        return 'Revenue by month ('.Money::symbol($this->currency).', before GST)';
    }

    protected function getData(): array
    {
        $data = (new RevenueReport($this->currency))->monthly();

        return [
            'datasets' => [
                ['label' => 'Software plans', 'data' => $data['plans'], 'backgroundColor' => '#6366f1', 'borderRadius' => 4, 'stack' => 'r'],
                ['label' => 'Message credits', 'data' => $data['credits'], 'backgroundColor' => '#f59e0b', 'borderRadius' => 4, 'stack' => 'r'],
            ],
            'labels' => $data['labels'],
        ];
    }

    protected function getOptions(): array
    {
        return ['scales' => ['x' => ['stacked' => true], 'y' => ['stacked' => true, 'beginAtZero' => true]]];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
