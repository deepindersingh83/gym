<?php

namespace App\Filament\Gym\Resources\CashClosings;

use App\Filament\Gym\Concerns\ChecksGymAccess;
use App\Filament\Gym\Resources\CashClosings\Pages\ManageCashClosings;
use App\Models\CashClosing;
use App\Support\GymOptions;
use App\Support\Money;
use App\Support\Tenancy;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

/** Day-end closing: reception counts the cash drawer and the system shows any difference. */
class CashClosingResource extends Resource
{
    use ChecksGymAccess;

    protected static ?string $model = CashClosing::class;

    protected static ?string $modelLabel = 'day-end closing';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static string|UnitEnum|null $navigationGroup = 'Billing';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Cash closing';

    protected static function accessArea(): string
    {
        return 'cash_closings';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('branch_id')->label('Branch')->options(fn () => GymOptions::branches())
                ->default(fn () => Tenancy::branchId() ?? GymOptions::branches()->keys()->first())
                ->required()->live()
                ->disabled(fn () => ! Tenancy::canSwitchBranch())->dehydrated(),
            DatePicker::make('closing_date')->default(today())->maxDate(today())->required()->live()
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('branch_id', $get('branch_id')))
                ->validationMessages(['unique' => 'This branch is already closed for that day.']),
            TextInput::make('opening_balance')->label('Opening cash in drawer')->numeric()->prefix(Money::symbol())->default(0)->live(onBlur: true),
            TextInput::make('counted_cash')->label('Cash counted now')->numeric()->prefix(Money::symbol())->required()->live(onBlur: true),
            Text::make(function (Get $get) {
                if (! $get('branch_id') || ! $get('closing_date')) {
                    return '';
                }

                $figures = CashClosing::systemFigures(Tenancy::gymId(), (int) $get('branch_id'), $get('closing_date'));
                $expected = (float) $get('opening_balance') + $figures['cash_collected'] - $figures['cash_expenses'];
                $difference = (float) $get('counted_cash') - $expected;

                return sprintf(
                    'Cash payments %s − cash expenses %s → expected in drawer %s. Difference: %s',
                    Money::format($figures['cash_collected']),
                    Money::format($figures['cash_expenses']),
                    Money::format($expected),
                    ($difference >= 0 ? '+' : '−').Money::format(abs($difference)),
                );
            })->columnSpanFull(),
            Textarea::make('notes')->rows(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        $money = fn ($state) => Money::format($state);

        return $table
            ->defaultSort('closing_date', 'desc')
            ->columns([
                TextColumn::make('closing_date')->date('D, d M Y')->sortable(),
                TextColumn::make('branch.name'),
                TextColumn::make('cash_collected')->label('Cash in')->formatStateUsing($money),
                TextColumn::make('cash_expenses')->label('Cash out')->formatStateUsing($money),
                TextColumn::make('expected_cash')->label('Expected')->formatStateUsing($money),
                TextColumn::make('counted_cash')->label('Counted')->formatStateUsing($money),
                TextColumn::make('difference')->formatStateUsing($money)
                    ->color(fn ($state) => (float) $state < 0 ? 'danger' : ((float) $state > 0 ? 'warning' : 'success'))
                    ->weight('bold'),
                TextColumn::make('closedBy.name')->label('Closed by'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageCashClosings::route('/')];
    }
}
