<?php

namespace App\Filament\Admin\Resources\SaasPlans;

use App\Filament\Admin\Resources\SaasPlans\Pages\ManageSaasPlans;
use App\Models\SaasPlan;
use App\Support\AdminAccess;
use App\Support\Modules;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

/** Plans the SaaS sells to gym owners (Basic, Pro, Enterprise) and their limits. */
class SaasPlanResource extends Resource
{
    public static function canViewAny(): bool
    {
        return AdminAccess::can('plans');
    }

    protected static ?string $model = SaasPlan::class;

    protected static ?string $navigationLabel = 'Software plans';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Plan')->columns(3)->schema([
                TextInput::make('name')->required(),
                TextInput::make('slug')->required()->alphaDash()->unique(ignoreRecord: true),
                TextInput::make('sort')->numeric()->default(0),
                Toggle::make('is_active')->label('Offered to new gyms')->default(true)->inline(false),
            ]),
            Section::make('Prices')->columns(4)->schema([
                TextInput::make('monthly_price')->label('Monthly (India)')->numeric()->prefix('₹')->required(),
                TextInput::make('yearly_price')->label('Yearly (India)')->numeric()->prefix('₹')->required(),
                TextInput::make('monthly_price_aud')->label('Monthly (Australia)')->numeric()->prefix('A$')->required()->default(0),
                TextInput::make('yearly_price_aud')->label('Yearly (Australia)')->numeric()->prefix('A$')->required()->default(0),
            ]),
            Section::make('Limits')->columns(3)->schema([
                TextInput::make('max_members')->numeric()->minValue(1)->helperText('Empty = unlimited'),
                TextInput::make('max_branches')->numeric()->minValue(1)->helperText('Empty = unlimited'),
                TextInput::make('max_staff')->numeric()->minValue(1)->helperText('Empty = unlimited'),
            ]),
            Section::make('Features included')
                ->description('Gyms on this plan can use only the ticked features; the rest show an upgrade message.')
                ->schema([
                    Toggle::make('all_modules')
                        ->label('Everything, including features added in future')
                        ->dehydrated(false)
                        ->live()
                        ->afterStateHydrated(fn (Toggle $component, ?SaasPlan $record) => $component->state($record ? $record->modules === null : false)),
                    CheckboxList::make('modules')
                        ->hiddenLabel()
                        ->options(Modules::options())
                        ->descriptions(Modules::descriptions())
                        ->columns(2)
                        ->hidden(fn ($get) => $get('all_modules'))
                        ->dehydratedWhenHidden()
                        ->dehydrateStateUsing(fn ($state, $get) => $get('all_modules') ? null : array_values($state ?? [])),
                    TagsInput::make('features')->label('Extra selling points shown on the pricing page')->placeholder('e.g. Priority support'),
                ]),
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
                TextColumn::make('monthly_price_aud')->label('Monthly A$')->money('AUD'),
                TextColumn::make('modules')->label('Features')
                    ->state(fn (SaasPlan $record) => $record->modules === null ? 'Everything' : count($record->modules).' of '.count(Modules::ALL)),
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
