<?php

namespace App\Filament\Gym\Resources\DietPlans;

use App\Filament\Gym\Concerns\ChecksGymAccess;
use App\Filament\Gym\Resources\DietPlans\Pages\ManageDietPlans;
use App\Models\DietPlan;
use App\Support\GymOptions;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class DietPlanResource extends Resource
{
    use ChecksGymAccess;

    protected static ?string $model = DietPlan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCake;

    protected static string|UnitEnum|null $navigationGroup = 'Fitness';

    protected static ?int $navigationSort = 3;

    protected static function module(): ?string
    {
        return 'workout_diet';
    }

    protected static function accessArea(): string
    {
        return 'fitness';
    }

    /** Trainers see only their own members' plans (via the Member trainer scope). */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereHas('member');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(4)->components([
            Select::make('member_id')->label('Member')->relationship('member', 'name')->searchable()->preload()->required()->columnSpan(2),
            TextInput::make('title')->required()->columnSpan(2),
            DatePicker::make('starts_on')->default(today()),
            DatePicker::make('ends_on')->afterOrEqual('starts_on'),
            Select::make('trainer_id')->label('Trainer')->options(fn () => GymOptions::trainers())->default(fn () => auth()->id()),
            Toggle::make('is_active')->label('Current plan')->default(true)->inline(false),
            TextInput::make('daily_calories')->label('Daily calories')->numeric()->minValue(500)->suffix('kcal'),
            Repeater::make('meals')
                ->schema([
                    TimePicker::make('time')->seconds(false),
                    TextInput::make('meal')->placeholder('e.g. Breakfast')->required(),
                    TextInput::make('items')->placeholder('e.g. 4 egg whites, oats, banana')->required()->columnSpan(2),
                    TextInput::make('calories')->numeric()->minValue(0),
                ])
                ->columns(5)
                ->addActionLabel('Add meal')
                ->collapsible()
                ->itemLabel(fn (array $state) => $state['meal'] ?? null)
                ->columnSpanFull(),
            Textarea::make('notes')->rows(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('member.name')->searchable(),
                TextColumn::make('title')->searchable(),
                TextColumn::make('meals')->label('Items')->state(fn (DietPlan $record) => count($record->meals ?? [])),
                TextColumn::make('trainer.name')->label('Trainer')->placeholder('—'),
                TextColumn::make('starts_on')->date('d M Y')->placeholder('—'),
                TextColumn::make('ends_on')->date('d M Y')->placeholder('—'),
                IconColumn::make('is_active')->label('Current')->boolean(),
            ])
            ->filters([
                SelectFilter::make('trainer_id')->label('Trainer')->options(fn () => GymOptions::trainers()),
            ])
            ->recordActions([
                EditAction::make(),
                ReplicateAction::make()->label('Copy')->excludeAttributes(['member_id'])
                    ->schema([Select::make('member_id')->label('Copy to member')->relationship('member', 'name')->searchable()->required()]),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageDietPlans::route('/')];
    }
}
