<?php

namespace App\Filament\Gym\Resources\Members\RelationManagers;

use App\Filament\Gym\Resources\Measurements\MeasurementResource;
use App\Support\Access;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class MeasurementsRelationManager extends RelationManager
{
    protected static string $relationship = 'measurements';

    protected static ?string $title = 'Body measurements';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Access::can('fitness.view');
    }

    public function form(Schema $schema): Schema
    {
        return MeasurementResource::form($schema, withMember: false);
    }

    public function table(Table $table): Table
    {
        return MeasurementResource::table($table)
            ->headerActions([CreateAction::make()->visible(fn () => Access::can('fitness.create'))])
            ->recordActions([
                EditAction::make()->visible(fn () => Access::can('fitness.edit')),
                DeleteAction::make()->visible(fn () => Access::can('fitness.delete')),
            ]);
    }
}
