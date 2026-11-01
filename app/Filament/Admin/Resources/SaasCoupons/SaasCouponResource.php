<?php

namespace App\Filament\Admin\Resources\SaasCoupons;

use App\Filament\Admin\Resources\SaasCoupons\Pages\ManageSaasCoupons;
use App\Models\SaasCoupon;
use App\Models\SaasPlan;
use App\Support\AdminAccess;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

/** Discount codes gyms can use when paying for a software plan. */
class SaasCouponResource extends Resource
{
    protected static ?string $model = SaasCoupon::class;

    protected static ?string $slug = 'discount-codes';

    protected static ?string $navigationLabel = 'Discount codes';

    protected static ?string $modelLabel = 'discount code';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static ?int $navigationSort = 4;

    public static function canViewAny(): bool
    {
        return AdminAccess::can('coupons');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('code')->required()->maxLength(40)->alphaDash()->unique(ignoreRecord: true)
                ->helperText('Gyms type this at checkout. Not case sensitive.'),
            TextInput::make('description')->maxLength(150)->placeholder('e.g. Diwali offer'),
            Select::make('discount_type')->label('Discount')->options(['percent' => 'Percentage', 'fixed' => 'Fixed amount'])->default('percent')->required()->live(),
            TextInput::make('value')->numeric()->minValue(0.01)->required()
                ->suffix(fn (Get $get) => $get('discount_type') === 'percent' ? '%' : null)
                ->maxValue(fn (Get $get) => $get('discount_type') === 'percent' ? 100 : null),
            Select::make('currency')->options(['INR' => 'Indian rupees (₹)', 'AUD' => 'Australian dollars (A$)'])
                ->visible(fn (Get $get) => $get('discount_type') === 'fixed')->required(fn (Get $get) => $get('discount_type') === 'fixed'),
            CheckboxList::make('plans')->label('Only for these plans')->helperText('None ticked = all plans.')
                ->options(fn () => SaasPlan::query()->orderBy('sort')->pluck('name', 'slug'))->columns(3)->columnSpanFull(),
            TextInput::make('max_redemptions')->label('Maximum uses')->numeric()->minValue(1)->helperText('Empty = unlimited'),
            DatePicker::make('valid_until')->label('Valid until')->helperText('Empty = no end date'),
            Toggle::make('first_payment_only')->label("Only for a gym's first payment")->inline(false),
            Toggle::make('is_active')->label('Active')->default(true)->inline(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('code')->weight('bold')->searchable()->copyable(),
                TextColumn::make('description')->placeholder('—')->toggleable(),
                TextColumn::make('value')->label('Discount')
                    ->formatStateUsing(fn ($state, SaasCoupon $record) => $record->discount_type === 'percent' ? rtrim(rtrim((string) $state, '0'), '.').'%' : Money::format((float) $state, $record->currency)),
                TextColumn::make('plans')->label('Plans')->badge()->placeholder('All plans'),
                TextColumn::make('redemptions')->label('Used')->formatStateUsing(fn ($state, SaasCoupon $record) => $state.($record->max_redemptions ? ' / '.$record->max_redemptions : '')),
                TextColumn::make('valid_until')->date('d M Y')->placeholder('No end'),
                ToggleColumn::make('is_active')->label('Active'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageSaasCoupons::route('/')];
    }
}
