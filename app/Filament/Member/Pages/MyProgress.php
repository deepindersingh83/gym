<?php

namespace App\Filament\Member\Pages;

use App\Filament\Member\Widgets\WeightProgressChart;
use App\Models\Measurement;
use App\Support\CurrentMember;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class MyProgress extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $title = 'My progress';

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.gym.pages.table-page';

    protected function getHeaderWidgets(): array
    {
        return [WeightProgressChart::class];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Measurement::query()->where('member_id', CurrentMember::get()->id))
            ->defaultSort('recorded_on', 'desc')
            ->columns([
                TextColumn::make('recorded_on')->label('Date')->date('d M Y'),
                TextColumn::make('weight_kg')->label('Weight')->suffix(' kg'),
                TextColumn::make('bmi')->label('BMI'),
                TextColumn::make('body_fat_pct')->label('Body fat')->suffix('%')->placeholder('—'),
                TextColumn::make('chest_cm')->label('Chest')->placeholder('—'),
                TextColumn::make('waist_cm')->label('Waist')->placeholder('—'),
                TextColumn::make('arms_cm')->label('Arms')->placeholder('—'),
            ]);
    }
}
