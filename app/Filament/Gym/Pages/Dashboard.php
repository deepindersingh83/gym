<?php

namespace App\Filament\Gym\Pages;

use App\Support\Access;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    public function filtersForm(Schema $schema): Schema
    {
        if (! Access::can('reports.view')) {
            return $schema;
        }

        return $schema->components([
            Section::make()->columns(3)->columnSpanFull()->schema([
                Select::make('period')
                    ->label('Report period')
                    ->options([
                        'this_month' => 'This month',
                        'last_month' => 'Last month',
                        'this_quarter' => 'This quarter',
                        'this_year' => 'This year',
                        'custom' => 'Custom dates',
                    ])
                    ->default('this_month')
                    ->live()
                    ->afterStateUpdated(function (?string $state, Set $set) {
                        [$from, $until] = static::rangeFor($state);
                        $set('from', $from?->toDateString());
                        $set('until', $until?->toDateString());
                    }),
                DatePicker::make('from')->default(today()->startOfMonth()),
                DatePicker::make('until')->default(today()),
            ]),
        ]);
    }

    /** @return array{0: ?Carbon, 1: ?Carbon} */
    public static function rangeFor(?string $period): array
    {
        return match ($period) {
            'last_month' => [today()->subMonthNoOverflow()->startOfMonth(), today()->subMonthNoOverflow()->endOfMonth()],
            'this_quarter' => [today()->startOfQuarter(), today()],
            'this_year' => [today()->startOfYear(), today()],
            'custom' => [null, null],
            default => [today()->startOfMonth(), today()],
        };
    }
}
