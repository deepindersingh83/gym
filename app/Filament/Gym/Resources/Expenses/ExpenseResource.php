<?php

namespace App\Filament\Gym\Resources\Expenses;

use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use App\Filament\Gym\Concerns\ChecksGymAccess;
use App\Filament\Gym\Resources\Expenses\Pages\ManageExpenses;
use App\Models\Expense;
use App\Support\GymOptions;
use App\Support\Money;
use App\Support\Tenancy;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ExpenseResource extends Resource
{
    use ChecksGymAccess;

    protected static ?string $model = Expense::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string|UnitEnum|null $navigationGroup = 'Billing';

    protected static ?int $navigationSort = 4;

    protected static function accessArea(): string
    {
        return 'expenses';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('category')->options(ExpenseCategory::class)->required(),
            TextInput::make('amount')->numeric()->minValue(0.01)->prefix(Money::symbol())->required(),
            DatePicker::make('spent_on')->default(today())->required(),
            Select::make('method')->label('Paid by')->options(fn () => PaymentMethod::options())->default(PaymentMethod::Cash->value)->required()
                ->helperText('Cash expenses are deducted in the day-end cash closing.'),
            Select::make('branch_id')->label('Branch')->options(fn () => GymOptions::branches())
                ->default(fn () => Tenancy::branchId())->placeholder('Whole gym')
                ->disabled(fn () => ! Tenancy::canSwitchBranch())->dehydrated(),
            TextInput::make('reference')->label('Bill / reference no.'),
            TextInput::make('description')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('spent_on', 'desc')
            ->columns([
                TextColumn::make('spent_on')->date('d M Y')->sortable(),
                TextColumn::make('category')->badge(),
                TextColumn::make('description')->limit(40)->placeholder('—'),
                TextColumn::make('amount')->formatStateUsing(fn ($state) => Money::format($state))->sortable()
                    ->summarize(Sum::make()->formatStateUsing(fn ($state) => Money::format($state))),
                TextColumn::make('method')->badge()->color('gray'),
                TextColumn::make('branch.name')->placeholder('Whole gym')->toggleable(),
                TextColumn::make('createdBy.name')->label('Added by')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category')->options(ExpenseCategory::class),
                Filter::make('spent_on')
                    ->schema([DatePicker::make('from')->default(today()->startOfMonth()), DatePicker::make('until')])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('spent_on', '>=', $d))
                        ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('spent_on', '<=', $d))),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageExpenses::route('/')];
    }
}
