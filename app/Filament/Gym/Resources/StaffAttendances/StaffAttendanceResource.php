<?php

namespace App\Filament\Gym\Resources\StaffAttendances;

use App\Filament\Gym\Concerns\ChecksGymAccess;
use App\Filament\Gym\Resources\StaffAttendances\Pages\ManageStaffAttendances;
use App\Models\StaffAttendance;
use App\Support\GymOptions;
use App\Support\Tenancy;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

class StaffAttendanceResource extends Resource
{
    use ChecksGymAccess;

    protected static ?string $model = StaffAttendance::class;

    protected static ?string $navigationLabel = 'Staff attendance';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Staff';

    protected static ?int $navigationSort = 2;

    public const STATUSES = ['present' => 'Present', 'absent' => 'Absent', 'half_day' => 'Half day', 'leave' => 'On leave'];

    protected static function accessArea(): string
    {
        return 'staff_attendance';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('user_id')->label('Staff member')->options(fn () => GymOptions::staff())->required()->searchable(),
            DatePicker::make('date')->default(today())->maxDate(today())->required()
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('gym_id', Tenancy::gymId())->where('user_id', $get('user_id')))
                ->validationMessages(['unique' => 'Attendance for this day is already recorded.']),
            Select::make('status')->options(self::STATUSES)->default('present')->required(),
            Select::make('branch_id')->label('Branch')->options(fn () => GymOptions::branches())->default(fn () => Tenancy::branchId()),
            TimePicker::make('check_in')->seconds(false)->default(now()->format('H:i')),
            TimePicker::make('check_out')->seconds(false),
            TextInput::make('notes')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('date')->date('D, d M Y')->sortable(),
                TextColumn::make('user.name')->label('Staff member')->searchable(),
                TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => self::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'present' => 'success', 'absent' => 'danger', default => 'warning'
                    }),
                TextColumn::make('check_in')->time('h:i A')->placeholder('—'),
                TextColumn::make('check_out')->time('h:i A')->placeholder('—'),
                TextColumn::make('notes')->placeholder('—')->limit(30),
            ])
            ->filters([
                SelectFilter::make('user_id')->label('Staff member')->options(fn () => GymOptions::staff()),
                Filter::make('date')
                    ->schema([DatePicker::make('from')->default(today()->startOfMonth()), DatePicker::make('until')])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('date', '>=', $d))
                        ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('date', '<=', $d))),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageStaffAttendances::route('/')];
    }
}
