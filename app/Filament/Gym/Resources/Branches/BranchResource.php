<?php

namespace App\Filament\Gym\Resources\Branches;

use App\Filament\Gym\Concerns\ChecksGymAccess;
use App\Filament\Gym\Resources\Branches\Pages\ManageBranches;
use App\Models\Branch;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use UnitEnum;

class BranchResource extends Resource
{
    use ChecksGymAccess;

    protected static ?string $model = Branch::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|UnitEnum|null $navigationGroup = 'Setup';

    protected static ?int $navigationSort = 3;

    protected static function accessArea(): string
    {
        return 'branches';
    }

    protected static function saasLimit(): ?string
    {
        return 'branches';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->required()->maxLength(80),
            TextInput::make('phone')->tel(),
            TextInput::make('email')->email(),
            Toggle::make('is_active')->default(true)->inline(false),
            Textarea::make('address')->rows(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->weight('bold'),
                TextColumn::make('phone')->placeholder('—'),
                TextColumn::make('address')->limit(50)->placeholder('—'),
                TextColumn::make('members_count')->counts('members')->label('Members'),
                ToggleColumn::make('is_active')->label('Active'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->disabled(fn (Branch $record) => $record->members()->withoutGlobalScopes()->exists())
                    ->tooltip(fn (Branch $record) => $record->members()->withoutGlobalScopes()->exists() ? 'Move or remove its members first' : null),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageBranches::route('/')];
    }
}
