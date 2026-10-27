<?php

namespace App\Filament\Gym\Resources\Coupons;

use App\Filament\Gym\Concerns\ChecksGymAccess;
use App\Filament\Gym\Resources\Coupons\Pages\ManageCoupons;
use App\Models\Coupon;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use UnitEnum;

class CouponResource extends Resource
{
    use ChecksGymAccess;

    protected static ?string $model = Coupon::class;

    protected static ?string $navigationLabel = 'Coupons & offers';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGift;

    protected static string|UnitEnum|null $navigationGroup = 'Setup';

    protected static ?int $navigationSort = 2;

    protected static function accessArea(): string
    {
        return 'coupons';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('code')->required()->maxLength(30)->scopedUnique(ignoreRecord: true)
                ->helperText('e.g. DIWALI25'),
            Select::make('category')->options([
                'offer' => 'General offer', 'festival' => 'Festival', 'referral' => 'Referral', 'corporate' => 'Corporate tie-up',
            ])->default('offer')->required(),
            Select::make('discount_type')->options(['percent' => 'Percentage', 'fixed' => 'Fixed amount'])->default('percent')->required(),
            TextInput::make('value')->numeric()->minValue(0.01)->required(),
            DatePicker::make('valid_from'),
            DatePicker::make('valid_until')->afterOrEqual('valid_from'),
            TextInput::make('max_uses')->numeric()->minValue(1)->helperText('Leave empty for unlimited.'),
            Toggle::make('is_active')->default(true)->inline(false),
            TextInput::make('description')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->searchable()->weight('bold')->copyable(),
                TextColumn::make('category')->badge()->formatStateUsing(fn ($state) => ucfirst((string) $state)),
                TextColumn::make('value')->formatStateUsing(fn ($state, Coupon $record) => $record->discount_type === 'percent' ? (float) $state.'%' : Money::format($state)),
                TextColumn::make('valid_until')->date('d M Y')->placeholder('No expiry'),
                TextColumn::make('used_count')->label('Used')->formatStateUsing(fn ($state, Coupon $record) => $record->max_uses ? "{$state} / {$record->max_uses}" : $state),
                ToggleColumn::make('is_active')->label('Active'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageCoupons::route('/')];
    }
}
