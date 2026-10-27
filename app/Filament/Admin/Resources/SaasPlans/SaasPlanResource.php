<?php

namespace App\Filament\Admin\Resources\SaasPlans;

use App\Filament\Admin\Resources\SaasPlans\Pages\ManageSaasPlans;
use App\Models\SaasPlan;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

/** Plans the SaaS sells to gym owners (Basic, Pro, Enterprise) and their limits. */
class SaasPlanResource extends Resource
{
    protected static ?string $model = SaasPlan::class;

    protected static ?string $navigationLabel = 'Software plans';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            TextInput::make('name')->required(),
            TextInput::make('slug')->required()->alphaDash()->unique(ignoreRecord: true),
            TextInput::make('sort')->numeric()->default(0),
            TextInput::make('monthly_price')->numeric()->prefix('₹')->required(),
            TextInput::make('yearly_price')->numeric()->prefix('₹')->required(),
            Toggle::make('is_active')->default(true)->inline(false),
            TextInput::make('max_members')->numeric()->minValue(1)->helperText('Empty = unlimited'),
            TextInput::make('max_branches')->numeric()->minValue(1)->helperText('Empty = unlimited'),
            TextInput::make('max_staff')->numeric()->minValue(1)->helperText('Empty = unlimited'),
            TagsInput::make('features')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        $limit = fn ($state) => $state ?? 'Unlimited';

        return $table
            ->defaultSort('sort')
            ->columns([
                TextColumn::make('name')->weight('bold'),
                TextColumn::make('monthly_price')->money('INR'),
                TextColumn::make('yearly_price')->money('INR'),
                TextColumn::make('max_members')->label('Members')->formatStateUsing($limit)->placeholder('Unlimited'),
                TextColumn::make('max_branches')->label('Branches')->formatStateUsing($limit)->placeholder('Unlimited'),
                TextColumn::make('max_staff')->label('Staff')->formatStateUsing($limit)->placeholder('Unlimited'),
                TextColumn::make('gyms_count')->counts('gyms')->label('Gyms'),
                ToggleColumn::make('is_active'),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageSaasPlans::route('/')];
    }
}
