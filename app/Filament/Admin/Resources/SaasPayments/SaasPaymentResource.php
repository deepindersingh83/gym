<?php

namespace App\Filament\Admin\Resources\SaasPayments;

use App\Filament\Admin\Resources\SaasPayments\Pages\ListSaasPayments;
use App\Models\SaasPayment;
use App\Support\AdminAccess;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** Everything gyms have paid the platform. */
class SaasPaymentResource extends Resource
{
    protected static ?string $model = SaasPayment::class;

    protected static ?string $navigationLabel = 'Software payments';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?int $navigationSort = 3;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('paid_at', 'desc')
            ->columns([
                TextColumn::make('paid_at')->label('Date')->dateTime('d M Y')->sortable(),
                TextColumn::make('gym.name')->searchable(),
                TextColumn::make('kind')->badge()->formatStateUsing(fn ($state) => $state === 'credits' ? 'Message credits' : 'Software plan'),
                TextColumn::make('saasPlan.name')->label('Plan')->placeholder('—'),
                TextColumn::make('invoice_number')->label('Invoice')->placeholder('—')->searchable(),
                TextColumn::make('months')->suffix(' mo')->placeholder('—'),
                TextColumn::make('amount')->formatStateUsing(fn ($state, SaasPayment $record) => Money::format((float) $state, $record->currency)),
                TextColumn::make('tax_amount')->label('GST')->formatStateUsing(fn ($state, SaasPayment $record) => Money::format((float) $state, $record->currency))->toggleable(),
                TextColumn::make('gateway')->badge()->formatStateUsing(fn ($state) => ucfirst($state))->toggleable(),
                TextColumn::make('reference')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('commission.amount')->label('Partner commission')
                    ->formatStateUsing(fn ($state, SaasPayment $record) => Money::format((float) $state, $record->currency))->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('kind')->options(['subscription' => 'Software plan', 'credits' => 'Message credits']),
                SelectFilter::make('currency')->options(['INR' => 'India (₹)', 'AUD' => 'Australia (A$)']),
                SelectFilter::make('gateway')->options(['manual' => 'Recorded by hand', 'razorpay' => 'Razorpay', 'stripe' => 'Stripe']),
            ])
            ->recordActions([
                Action::make('invoice')->label('Invoice')->icon(Heroicon::OutlinedArrowDownTray)
                    ->visible(fn (SaasPayment $record) => filled($record->invoice_number))
                    ->url(fn (SaasPayment $record) => route('saas-invoices.show', $record), shouldOpenInNewTab: true),
            ]);
    }

    public static function canViewAny(): bool
    {
        return AdminAccess::can('payments');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => ListSaasPayments::route('/')];
    }
}
