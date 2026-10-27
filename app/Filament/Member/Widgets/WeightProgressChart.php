<?php

namespace App\Filament\Member\Widgets;

use App\Models\Measurement;
use App\Support\CurrentMember;
use Filament\Widgets\ChartWidget;

class WeightProgressChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Weight and BMI';

    protected ?string $maxHeight = '280px';

    protected int|string|array $columnSpan = 'full';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $rows = Measurement::query()->where('member_id', CurrentMember::get()->id)->orderBy('recorded_on')->get();

        return [
            'datasets' => [
                ['label' => 'Weight (kg)', 'data' => $rows->map(fn ($r) => (float) $r->weight_kg)->all(), 'borderColor' => '#f59e0b', 'tension' => 0.3],
                ['label' => 'BMI', 'data' => $rows->map(fn ($r) => (float) $r->bmi)->all(), 'borderColor' => '#2563eb', 'tension' => 0.3],
            ],
            'labels' => $rows->map(fn ($r) => $r->recorded_on->format('d M y'))->all(),
        ];
    }
}
