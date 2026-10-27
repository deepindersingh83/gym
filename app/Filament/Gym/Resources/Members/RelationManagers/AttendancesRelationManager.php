<?php

namespace App\Filament\Gym\Resources\Members\RelationManagers;

use App\Support\Access;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class AttendancesRelationManager extends RelationManager
{
    protected static string $relationship = 'attendances';

    protected static ?string $title = 'Attendance';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Access::can('attendance.view');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('checked_in_at', 'desc')
            ->columns([
                TextColumn::make('checked_in_at')->label('Checked in')->dateTime('D, d M Y h:i A'),
                TextColumn::make('method')->badge(),
                TextColumn::make('branch.name')->label('Branch'),
            ]);
    }
}
