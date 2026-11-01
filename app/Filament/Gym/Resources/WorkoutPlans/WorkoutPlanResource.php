<?php

namespace App\Filament\Gym\Resources\WorkoutPlans;

use App\Filament\Gym\Concerns\ChecksGymAccess;
use App\Filament\Gym\Resources\WorkoutPlans\Pages\ManageWorkoutPlans;
use App\Models\WorkoutPlan;
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

class WorkoutPlanResource extends Resource
{
    use ChecksGymAccess;

    protected static ?string $model = WorkoutPlan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFire;

    protected static string|UnitEnum|null $navigationGroup = 'Fitness';

    protected static ?int $navigationSort = 2;

    public const DAYS = [
        'mon' => 'Monday', 'tue' => 'Tuesday', 'wed' => 'Wednesday', 'thu' => 'Thursday',
        'fri' => 'Friday', 'sat' => 'Saturday', 'sun' => 'Sunday', 'any' => 'Any day',
    ];

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
            Repeater::make('exercises')
                ->schema([
                    Select::make('day')->options(self::DAYS)->required(),
                    TextInput::make('exercise')->required()->columnSpan(2),
                    TextInput::make('sets')->numeric()->minValue(1),
                    TextInput::make('reps')->placeholder('e.g. 10-12'),
                    TextInput::make('rest')->placeholder('e.g. 60s'),
                ])
                ->columns(6)
                ->addActionLabel('Add exercise')
                ->collapsible()
                ->itemLabel(fn (array $state) => trim((self::DAYS[$state['day'] ?? ''] ?? '').' · '.($state['exercise'] ?? ''), ' ·'))
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
                TextColumn::make('exercises')->label('Items')->state(fn (WorkoutPlan $record) => count($record->exercises ?? [])),
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
        return ['index' => ManageWorkoutPlans::route('/')];
    }
}
