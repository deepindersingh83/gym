<?php

namespace App\Filament\Gym\Resources\Payments;

use App\Enums\PaymentMethod;
use App\Filament\Gym\Concerns\ChecksGymAccess;
use App\Filament\Gym\Resources\Payments\Pages\ListPayments;
use App\Models\Payment;
use App\Support\GymOptions;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class PaymentResource extends Resource
{
    use ChecksGymAccess;

    protected static ?string $model = Payment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Billing';

    protected static ?int $navigationSort = 2;

    protected static function accessArea(): string
    {
        return 'payments';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['member', 'subscription', 'receivedBy']))
            ->defaultSort('paid_at', 'desc')
            ->columns([
                TextColumn::make('receipt_number')->label('Receipt')->searchable(),
                TextColumn::make('paid_at')->label('Date')->dateTime('d M Y, h:i A')->sortable(),
                TextColumn::make('member.name')->searchable()->description(fn (Payment $record) => $record->member?->phone),
                TextColumn::make('subscription.invoice_number')->label('Invoice')->toggleable(),
                TextColumn::make('method')->badge(),
                TextColumn::make('reference')->placeholder('—')->toggleable(),
                TextColumn::make('amount')->formatStateUsing(fn ($state) => Money::format($state))->sortable()
                    ->summarize(Sum::make()->label('Total')->formatStateUsing(fn ($state) => Money::format($state))),
                TextColumn::make('receivedBy.name')->label('Received by')->placeholder('Online')->toggleable(),
                TextColumn::make('branch.name')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('method')->options(fn () => PaymentMethod::options())->multiple(),
                SelectFilter::make('received_by')->label('Received by')->options(fn () => GymOptions::staff()),
                Filter::make('paid_at')
                    ->schema([DatePicker::make('from'), DatePicker::make('until')])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('paid_at', '>=', $d))
                        ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('paid_at', '<=', $d))),
            ])
            ->recordActions([
                Action::make('receipt')->icon(Heroicon::OutlinedPrinter)->color('gray')
                    ->url(fn (Payment $record) => route('receipts.show', $record), shouldOpenInNewTab: true),
                DeleteAction::make()->modalDescription('The membership balance is recalculated. This is recorded in the audit log.'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListPayments::route('/')];
    }
}
