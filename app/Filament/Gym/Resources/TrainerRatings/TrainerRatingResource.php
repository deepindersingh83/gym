<?php

namespace App\Filament\Gym\Resources\TrainerRatings;

use App\Filament\Gym\Concerns\ChecksGymAccess;
use App\Filament\Gym\Resources\TrainerRatings\Pages\ListTrainerRatings;
use App\Models\TrainerRating;
use App\Support\Access;
use App\Support\GymOptions;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Ratings and feedback members leave for their trainer from the member app. */
class TrainerRatingResource extends Resource
{
    use ChecksGymAccess;

    protected static ?string $model = TrainerRating::class;

    protected static ?string $navigationLabel = 'Trainer feedback';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static string|UnitEnum|null $navigationGroup = 'Staff';

    protected static ?int $navigationSort = 3;

    protected static function accessArea(): string
    {
        return 'ratings';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->when(Access::isTrainer(), fn ($q) => $q->where('trainer_id', auth()->id()));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Date')->date('d M Y')->sortable(),
                TextColumn::make('trainer.name')->label('Trainer'),
                TextColumn::make('rating')->formatStateUsing(fn ($state) => str_repeat('★', $state).str_repeat('☆', 5 - $state))->color('warning'),
                TextColumn::make('comment')->wrap()->placeholder('—'),
                TextColumn::make('member.name')->label('Member'),
            ])
            ->filters([
                SelectFilter::make('trainer_id')->label('Trainer')->options(fn () => GymOptions::trainers())->hidden(fn () => Access::isTrainer()),
                SelectFilter::make('rating')->options([5 => '5 stars', 4 => '4 stars', 3 => '3 stars', 2 => '2 stars', 1 => '1 star']),
            ])
            ->recordActions([DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ListTrainerRatings::route('/')];
    }
}
