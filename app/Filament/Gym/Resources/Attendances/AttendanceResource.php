<?php

namespace App\Filament\Gym\Resources\Attendances;

use App\Filament\Gym\Concerns\ChecksGymAccess;
use App\Filament\Gym\Resources\Attendances\Pages\ListAttendances;
use App\Models\Attendance;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class AttendanceResource extends Resource
{
    use ChecksGymAccess;

    protected static ?string $model = Attendance::class;

    protected static ?string $navigationLabel = 'Attendance';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Members';

    protected static ?int $navigationSort = 3;

    protected static function accessArea(): string
    {
        return 'attendance';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereHas('member');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('checked_in_at', 'desc')
            ->columns([
                TextColumn::make('checked_in_at')->label('Time')->dateTime('D, d M Y h:i A')->sortable(),
                TextColumn::make('member.member_code')->label('ID'),
                TextColumn::make('member.name')->label('Member')->searchable(),
                TextColumn::make('method')->badge(),
                TextColumn::make('branch.name')->toggleable(),
            ])
            ->filters([
                Filter::make('date')
                    ->schema([DatePicker::make('on')->default(today())])
                    ->query(fn (Builder $query, array $data) => $query->when($data['on'] ?? null, fn ($q, $d) => $q->whereDate('checked_in_at', $d))),
                SelectFilter::make('method')->options(['manual' => 'Front desk', 'qr' => 'QR scan']),
            ])
            ->recordActions([DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ListAttendances::route('/')];
    }
}
