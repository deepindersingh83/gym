<?php

namespace App\Filament\Gym\Resources\Measurements;

use App\Filament\Gym\Concerns\ChecksGymAccess;
use App\Filament\Gym\Resources\Measurements\Pages\ManageMeasurements;
use App\Models\Measurement;
use App\Models\Member;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class MeasurementResource extends Resource
{
    use ChecksGymAccess;

    protected static ?string $model = Measurement::class;

    protected static ?string $navigationLabel = 'Body measurements';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|UnitEnum|null $navigationGroup = 'Fitness';

    protected static ?int $navigationSort = 1;

    protected static function accessArea(): string
    {
        return 'fitness';
    }

    /** Trainers see only their own members' records (via the Member trainer scope). */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereHas('member');
    }

    public static function form(Schema $schema, bool $withMember = true): Schema
    {
        $cm = fn (string $name, string $label) => TextInput::make($name)->label($label)->numeric()->minValue(1)->maxValue(300)->suffix('cm');

        return $schema->columns(4)->components([
            Select::make('member_id')->label('Member')->relationship('member', 'name')->searchable()->preload()->required()
                ->columnSpan(2)->visible($withMember),
            DatePicker::make('recorded_on')->default(today())->maxDate(today())->required()->columnSpan($withMember ? 2 : 1),
            TextInput::make('weight_kg')->label('Weight')->numeric()->minValue(10)->maxValue(400)->suffix('kg')->required(),
            TextInput::make('height_cm')->label('Height')->numeric()->minValue(50)->maxValue(250)->suffix('cm')
                ->helperText('Leave empty to use the last recorded height.'),
            TextInput::make('body_fat_pct')->label('Body fat')->numeric()->minValue(1)->maxValue(70)->suffix('%'),
            $cm('chest_cm', 'Chest'),
            $cm('waist_cm', 'Waist'),
            $cm('hips_cm', 'Hips'),
            $cm('arms_cm', 'Arms'),
            $cm('thighs_cm', 'Thighs'),
            Textarea::make('notes')->rows(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('recorded_on', 'desc')
            ->columns([
                TextColumn::make('recorded_on')->label('Date')->date('d M Y')->sortable(),
                TextColumn::make('member.name')->searchable(),
                TextColumn::make('weight_kg')->label('Weight')->suffix(' kg'),
                TextColumn::make('bmi')->label('BMI')
                    ->color(fn ($state) => match (true) {
                        $state === null => null, $state < 18.5 || $state >= 30 => 'danger', $state >= 25 => 'warning', default => 'success'
                    }),
                TextColumn::make('body_fat_pct')->label('Body fat')->suffix('%')->placeholder('—'),
                TextColumn::make('chest_cm')->label('Chest')->placeholder('—')->toggleable(),
                TextColumn::make('waist_cm')->label('Waist')->placeholder('—')->toggleable(),
                TextColumn::make('arms_cm')->label('Arms')->placeholder('—')->toggleable(),
                TextColumn::make('hips_cm')->label('Hips')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('thighs_cm')->label('Thighs')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('member_id')->label('Member')->options(fn () => Member::query()->orderBy('name')->pluck('name', 'id'))->searchable(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageMeasurements::route('/')];
    }
}
