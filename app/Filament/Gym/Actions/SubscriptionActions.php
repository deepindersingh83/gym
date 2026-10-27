<?php

namespace App\Filament\Gym\Actions;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Services\Payments\RazorpayService;
use App\Support\Access;
use App\Support\Tenancy;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Throwable;

class SubscriptionActions
{
    public static function invoice(): Action
    {
        return Action::make('invoice')
            ->label('Invoice PDF')
            ->icon(Heroicon::OutlinedDocumentText)
            ->color('gray')
            ->url(fn (Subscription $record) => route('invoices.show', $record), shouldOpenInNewTab: true);
    }

    /** Creates a Razorpay payment link for the balance and shows it to copy or share. */
    public static function paymentLink(): Action
    {
        return Action::make('paymentLink')
            ->label('Send payment link')
            ->icon(Heroicon::OutlinedLink)
            ->color('gray')
            ->visible(fn (Subscription $record) => Access::can('payments.create') && $record->balance_due > 0)
            ->requiresConfirmation()
            ->modalDescription('Razorpay sends the member an SMS (and email) with a link to pay the balance by UPI, card or net banking. The payment is recorded here automatically.')
            ->action(function (Subscription $record): void {
                try {
                    $url = RazorpayService::for(Tenancy::gym())->paymentLinkForDues($record);
                    Notification::make()->title('Payment link sent')->body($url)->success()->persistent()->send();
                } catch (Throwable $e) {
                    Notification::make()->title('Could not create payment link')->body($e->getMessage())->danger()->send();
                }
            });
    }

    public static function autoPay(): Action
    {
        return Action::make('autoPay')
            ->label(fn (Subscription $record) => $record->auto_renew ? 'Stop UPI AutoPay' : 'Set up UPI AutoPay')
            ->icon(Heroicon::OutlinedArrowPathRoundedSquare)
            ->color('gray')
            ->visible(fn (Subscription $record) => Access::can('subscriptions.edit') && $record->status === SubscriptionStatus::Active)
            ->requiresConfirmation()
            ->modalDescription(fn (Subscription $record) => $record->auto_renew
                ? 'Future renewals will no longer be charged automatically.'
                : 'Razorpay sends the member a link to approve a UPI AutoPay mandate. Each renewal is then charged automatically and recorded here.')
            ->action(function (Subscription $record): void {
                try {
                    $razorpay = RazorpayService::for(Tenancy::gym());

                    if ($record->auto_renew) {
                        $razorpay->cancelAutoPay($record);
                        Notification::make()->title('UPI AutoPay stopped')->success()->send();

                        return;
                    }

                    $url = $razorpay->setUpAutoPay($record);
                    Notification::make()->title('AutoPay link created')->body("Share this with the member to approve: {$url}")->success()->persistent()->send();
                } catch (Throwable $e) {
                    Notification::make()->title('Razorpay error')->body($e->getMessage())->danger()->send();
                }
            });
    }

    public static function changeDates(): Action
    {
        return Action::make('changeDates')
            ->label('Adjust')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->color('gray')
            ->visible(fn () => Access::can('subscriptions.edit'))
            ->fillForm(fn (Subscription $record) => $record->only(['start_date', 'end_date', 'pt_sessions_used', 'notes']))
            ->schema([
                DatePicker::make('start_date')->required(),
                DatePicker::make('end_date')->label('Renewal date')->required()->afterOrEqual('start_date'),
                TextInput::make('pt_sessions_used')->label('PT sessions used')->numeric()->minValue(0)
                    ->visible(fn (Subscription $record) => (bool) $record->pt_sessions_total),
                Textarea::make('notes')->rows(2),
            ])
            ->action(function (Subscription $record, array $data): void {
                $data['status'] = $record->status === SubscriptionStatus::Cancelled ? $record->status
                    : (Carbon::parse($data['end_date'])->lt(today()) ? SubscriptionStatus::Expired : SubscriptionStatus::Active);
                $record->update($data);
                Notification::make()->title('Membership updated')->success()->send();
            });
    }

    public static function cancel(): Action
    {
        return Action::make('cancel')
            ->label('Cancel membership')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn (Subscription $record) => Access::can('subscriptions.edit') && $record->status !== SubscriptionStatus::Cancelled)
            ->requiresConfirmation()
            ->modalDescription('The membership stops counting as active and its unpaid balance is no longer shown as due. Payments already taken are kept.')
            ->action(function (Subscription $record): void {
                if ($record->auto_renew) {
                    rescue(fn () => RazorpayService::for(Tenancy::gym())->cancelAutoPay($record));
                }

                $record->update(['status' => SubscriptionStatus::Cancelled, 'auto_renew' => false]);
                Notification::make()->title('Membership cancelled')->success()->send();
            });
    }
}
