<?php

namespace App\Filament\Gym\Resources\MembershipPlans;

use App\Enums\PlanType;
use App\Filament\Gym\Concerns\ChecksGymAccess;
use App\Filament\Gym\Resources\MembershipPlans\Pages\ManageMembershipPlans;
use App\Models\MembershipPlan;
use App\Support\Country;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use UnitEnum;

class MembershipPlanResource extends Resource
{
    use ChecksGymAccess;

    protected static ?string $model = MembershipPlan::class;

    protected static ?string $navigationLabel = 'Plans';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static string|UnitEnum|null $navigationGroup = 'Setup';

    protected static ?int $navigationSort = 1;

    protected static function accessArea(): string
    {
        return 'plans';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            TextInput::make('name')->required()->maxLength(80)->columnSpan(2),
            Select::make('type')->options(PlanType::class)->required()->live()
                ->afterStateUpdated(function ($state, Set $set) {
                    if ($state) {
                        [$value, $unit, $persons] = PlanType::from($state instanceof PlanType ? $state->value : $state)->defaults();
                        $set('duration_value', $value);
                        $set('duration_unit', $unit);
                        $set('max_persons', $persons);
                    }
                }),
            TextInput::make('duration_value')->label('Duration')->numeric()->minValue(1)->required(),
            Select::make('duration_unit')->options(['day' => 'Days', 'month' => 'Months'])->default('month')->required(),
            TextInput::make('max_persons')->label('People covered')->numeric()->minValue(1)->maxValue(10)->default(1)->required(),
            TextInput::make('price')->numeric()->minValue(0)->prefix(Money::symbol())->required()
                ->helperText(fn () => Country::get(Country::of(), 'tax_inclusive') ? 'Including GST.' : 'Before GST.'),
            TextInput::make('admission_fee')->numeric()->minValue(0)->prefix(Money::symbol())->default(0)->helperText('Charged on a member\'s first membership only.'),
            TextInput::make('gst_rate')->label('GST %')->numeric()->minValue(0)->maxValue(28)
                ->default(fn () => Country::get(Country::of(), 'tax_rate'))
                ->helperText(fn () => Country::isAustralia() ? 'Applies only if the gym is registered for GST.' : 'Applies only if the gym has a GSTIN.'),
            TextInput::make('pt_sessions')->label('PT sessions included')->numeric()->minValue(0),
            Toggle::make('is_active')->label('Available for sale')->default(true)->inline(false),
            Textarea::make('description')->rows(2)->columnSpanFull()->helperText('Shown on the public signup page.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('price')
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('type')->badge(),
                TextColumn::make('duration_label')->label('Duration'),
                TextColumn::make('price')->formatStateUsing(fn ($state) => Money::format($state))->sortable(),
                TextColumn::make('admission_fee')->formatStateUsing(fn ($state) => Money::format($state))->toggleable(),
                TextColumn::make('gst_rate')->label('GST')->suffix('%')->toggleable(),
                TextColumn::make('max_persons')->label('People'),
                ToggleColumn::make('is_active')->label('On sale'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageMembershipPlans::route('/')];
    }
}
