<?php

namespace App\Filament\Admin\Resources\AdminLogs;

use App\Filament\Admin\Resources\AdminLogs\Pages\ListAdminLogs;
use App\Models\Activity;
use App\Models\User;
use App\Support\AdminAccess;
use App\Support\AdminLog;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** Who on the admin team did what: trials, payments, credits, suspensions, settings, team changes. */
class AdminLogResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static ?string $slug = 'admin-log';

    protected static ?string $navigationLabel = 'Admin log';

    protected static ?string $modelLabel = 'admin log entry';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Platform';

    protected static ?int $navigationSort = 92;

    public static function canViewAny(): bool
    {
        return AdminAccess::can('admin_log');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('log_name', AdminLog::NAME)->with(['causer', 'gym']);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('created_at')->label('When')->dateTime('d M Y, h:i:s A'),
            TextEntry::make('causer.email')->label('Who')->placeholder('Server / system'),
            TextEntry::make('description')->label('What'),
            TextEntry::make('gym.name')->label('Gym')->placeholder('—'),
            KeyValueEntry::make('properties')->label('Details')
                ->state(fn (Activity $record) => collect($record->properties)->map(fn ($v) => is_scalar($v) || $v === null ? (string) $v : json_encode($v))->all()),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime('d M Y, h:i A')->sortable(),
                TextColumn::make('causer.name')->label('Who')->description(fn (Activity $record) => $record->causer?->email)->placeholder('Server / system'),
                TextColumn::make('description')->label('What')->searchable()->wrap(),
                TextColumn::make('gym.name')->label('Gym')->placeholder('—')->searchable(),
            ])
            ->filters([
                SelectFilter::make('causer_id')->label('Team member')
                    ->options(fn () => User::query()->where(fn ($q) => $q->whereNotNull('admin_role')->orWhere('is_super_admin', true))->pluck('name', 'id')),
                SelectFilter::make('gym_id')->label('Gym')->relationship('gym', 'name')->searchable()->preload(),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => ListAdminLogs::route('/')];
    }
}
