<?php

namespace App\Filament\Admin\Resources\SaasPayments;

use App\Filament\Admin\Resources\SaasPayments\Pages\ListSaasPayments;
use App\Models\SaasPayment;
use App\Services\Billing\Refunds;
use App\Support\AdminAccess;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Throwable;

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
                TextColumn::make('refunded_amount')->label('Refunded')->color('danger')
                    ->formatStateUsing(fn ($state, SaasPayment $record) => (float) $state > 0 ? Money::format((float) $state, $record->currency) : null)->placeholder('—'),
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
                Action::make('refund')
                    ->label('Refund')
                    ->icon(Heroicon::OutlinedReceiptRefund)
                    ->color('danger')
                    ->visible(fn (SaasPayment $record) => AdminAccess::can('payments') && $record->refundable() > 0 && filled($record->invoice_number))
                    ->modalHeading(fn (SaasPayment $record) => 'Refund '.$record->invoice_number)
                    ->modalDescription('A GST credit note is issued against the invoice and emailed to the gym owner. Unpaid partner commission is reduced to match.')
                    ->schema(fn (SaasPayment $record) => [
                        TextInput::make('amount')->label('Amount to refund (incl. GST)')
                            ->prefix(Money::symbol($record->currency))->numeric()->required()
                            ->minValue(0.01)->maxValue($record->refundable())->default($record->refundable())
                            ->helperText('Up to '.Money::format($record->refundable(), $record->currency).'.'),
                        TextInput::make('reason')->required()->maxLength(200)->placeholder('e.g. Charged twice / Closed in the first week'),
                        Radio::make('online')->label('How is the money returned?')
                            ->options([
                                '1' => 'Through '.ucfirst($record->gateway).' to the card / account it came from',
                                '0' => 'Already returned another way (bank transfer, cash); just record it',
                            ])
                            ->default(fn () => app(Refunds::class)->canRefundOnline($record) ? '1' : '0')
                            ->disableOptionWhen(fn (string $value) => $value === '1' && ! app(Refunds::class)->canRefundOnline($record))
                            ->required(),
                        Toggle::make('take_back')
                            ->label($record->kind === 'credits' ? 'Remove the matching credits from the gym' : 'Shorten the paid period to match')
                            ->default(true),
                    ])
                    ->action(function (SaasPayment $record, array $data) {
                        try {
                            $refund = app(Refunds::class)->refund($record, (float) $data['amount'], $data['reason'], (bool) $data['online'], (bool) $data['take_back'], auth()->user());
                        } catch (Throwable $e) {
                            report($e);
                            Notification::make()->title('Refund failed')->body($e->getMessage())->danger()->send();

                            return;
                        }

                        Notification::make()->title('Refunded. Credit note '.$refund->credit_note_number)
                            ->body($refund->status === 'pending' ? 'The gateway is still processing it.' : null)->success()->send();
                    }),
                Action::make('creditNotes')
                    ->label('Credit notes')
                    ->icon(Heroicon::OutlinedDocumentMinus)
                    ->color('gray')
                    ->visible(fn (SaasPayment $record) => (float) $record->refunded_amount > 0)
                    ->modalSubmitAction(false)
                    ->modalContent(fn (SaasPayment $record) => new HtmlString($record->refunds()->get()->map(fn ($r) => '<p><a style="text-decoration:underline" target="_blank" href="'.e(route('saas-credit-notes.show', $r)).'">'.e($r->credit_note_number).'</a>: '.e(Money::format((float) $r->amount, $r->currency)).' on '.$r->refunded_at->format('d M Y').' ('.e($r->reason).')</p>')->implode(''))),
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
