<?php

namespace App\Filament\Gym\Pages;

use App\Enums\StaffRole;
use App\Models\SaasPayment;
use App\Models\SaasPlan;
use App\Services\Billing\BillingCheckout;
use App\Services\Billing\GymBilling;
use App\Support\Modules;
use App\Support\Money;
use App\Support\Tenancy;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Url;
use Throwable;
use UnitEnum;

/** The gym owner's software plan: status, usage, plans to switch to, auto-renew and tax invoices. */
class Billing extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|UnitEnum|null $navigationGroup = 'Setup';

    protected static ?int $navigationSort = 90;

    protected static ?string $title = 'Plan & billing';

    protected static ?string $slug = 'billing';

    protected string $view = 'filament.gym.pages.billing';

    #[Url]
    public ?string $payment = null;

    public static function canAccess(): bool
    {
        return Tenancy::role() === StaffRole::Owner;
    }

    public function mount(): void
    {
        if ($this->payment === 'paid') {
            Notification::make()->title('Thank you! Your payment is being confirmed.')
                ->body('Your plan updates within a minute and the tax invoice is emailed to you.')->success()->send();
        } elseif ($this->payment === 'auto-renew') {
            Notification::make()->title('Auto-renew is being set up.')
                ->body('It shows as on once the payment provider confirms.')->success()->send();
        }

        $this->payment = null;
    }

    protected function getViewData(): array
    {
        $gym = Tenancy::gym()->fresh(['saasPlan']);
        $plans = SaasPlan::query()->where('is_active', true)->orderBy('sort')->get()
            ->filter(fn (SaasPlan $plan) => $plan->price($gym->currency, 'monthly') > 0 || $plan->price($gym->currency, 'yearly') > 0 || $plan->id === $gym->saas_plan_id);

        return [
            'gym' => $gym,
            'plans' => $plans,
            'currency' => $gym->currency,
            'inclusive' => app(GymBilling::class)->taxInclusive($gym),
            'online' => app(BillingCheckout::class)->isAvailable($gym),
            'graceEndsAt' => app(GymBilling::class)->graceEndsAt($gym),
            'usage' => [
                'Members' => [$gym->members()->withoutGlobalScopes()->whereNull('deleted_at')->count(), $gym->saasPlan?->max_members],
                'Branches' => [$gym->branches()->withoutGlobalScopes()->count(), $gym->saasPlan?->max_branches],
                'Staff' => [$gym->staffMemberships()->withoutGlobalScopes()->count(), $gym->saasPlan?->max_staff],
            ],
            'modules' => Modules::ALL,
            'support' => array_filter((array) config('gym.support')),
        ];
    }

    protected function quoteSummary(SaasPlan $plan, ?string $period, ?string $coupon): HtmlString
    {
        $gym = Tenancy::gym();
        $billing = app(GymBilling::class);
        $q = $billing->quote($gym, $plan, $period ?: 'yearly', $coupon);
        $m = fn ($v) => e(Money::format($v, $q['currency']));
        $inclusive = $billing->taxInclusive($gym);

        $rows = ['<tr><td>'.e($plan->name).' plan, '.e($q['period']).'</td><td style="text-align:right">'.$m($q['list_price']).'</td></tr>'];

        if ($q['credit'] > 0) {
            $rows[] = '<tr><td>Credit for unused days on your current plan</td><td style="text-align:right">−'.$m($q['credit']).'</td></tr>';
        }

        if ($q['discount'] > 0) {
            $rows[] = '<tr><td>Discount ('.e($q['coupon']->code).')</td><td style="text-align:right">−'.$m($q['discount']).'</td></tr>';
        }

        if (! $inclusive && $q['tax_rate'] > 0) {
            $rows[] = '<tr><td>GST '.rtrim(rtrim(number_format($q['tax_rate'], 2), '0'), '.').'%</td><td style="text-align:right">'.$m($q['tax_amount']).'</td></tr>';
        }

        $rows[] = '<tr style="font-weight:700;border-top:1px solid rgba(127,127,127,.35)"><td>Pay now</td><td style="text-align:right">'.$m($q['total']).'</td></tr>';

        if ($inclusive && $q['tax_amount'] > 0) {
            $rows[] = '<tr style="opacity:.65"><td>Includes GST of</td><td style="text-align:right">'.$m($q['tax_amount']).'</td></tr>';
        }

        $note = 'Active '.($q['starts_at']->isFuture() ? 'from '.$q['starts_at']->format('d M Y').' ' : '').'until '.$q['ends_at']->format('d M Y').'.';

        if ($q['coupon_error']) {
            $note = '<span style="color:rgb(220 38 38)">'.e($q['coupon_error']).'</span> '.$note;
        }

        return new HtmlString('<table style="width:100%;font-size:.9rem;border-collapse:collapse" cellpadding="4">'.implode('', $rows).'</table><p style="font-size:.85rem;opacity:.75;margin-top:.5rem">'.$note.'</p>');
    }

    public function checkoutAction(): Action
    {
        return Action::make('checkout')
            ->label(fn (array $arguments) => (int) ($arguments['plan'] ?? 0) === Tenancy::gym()->saas_plan_id ? 'Renew' : 'Choose')
            ->modalHeading(fn (array $arguments) => 'Pay for '.SaasPlan::query()->find($arguments['plan'] ?? 0)?->name)
            ->modalSubmitActionLabel('Continue to payment')
            ->modalWidth('md')
            ->schema(fn (array $arguments) => [
                Radio::make('period')->label('Billing')->options(['yearly' => 'Yearly', 'monthly' => 'Monthly'])->default('yearly')->inline()->live()->required(),
                TextInput::make('coupon')->label('Discount code')->maxLength(40)->live(onBlur: true),
                Text::make(fn (Get $get) => $this->quoteSummary(SaasPlan::query()->findOrFail($arguments['plan']), $get('period'), $get('coupon'))),
            ])
            ->action(function (array $data, array $arguments) {
                try {
                    $url = app(BillingCheckout::class)->payNow(Tenancy::gym(), SaasPlan::query()->findOrFail($arguments['plan']), $data['period'], $data['coupon'] ?? null);
                } catch (Throwable $e) {
                    report($e);
                    Notification::make()->title('Could not start the payment')->body($e->getMessage())->danger()->send();

                    return null;
                }

                if ($url === null) {
                    Notification::make()->title('Your plan is updated')->success()->send();

                    return redirect(static::getUrl());
                }

                return redirect()->away($url);
            });
    }

    public function autoRenewAction(): Action
    {
        return Action::make('autoRenew')
            ->label('Turn on auto-renew')
            ->icon(Heroicon::OutlinedArrowPath)
            ->modalHeading('Pay automatically')
            ->modalDescription(function () {
                $gym = Tenancy::gym();
                $end = $gym->status === 'trial' ? $gym->trial_ends_at : $gym->subscription_ends_at;

                return $end?->isFuture()
                    ? 'You approve a card or UPI mandate now. The first charge is on '.$end->format('d M Y').', then every period. Cancel any time.'
                    : 'You approve a card or UPI mandate now and the first payment is taken today, then every period. Cancel any time.';
            })
            ->modalSubmitActionLabel('Continue')
            ->modalWidth('md')
            ->schema([
                Select::make('plan')->label('Plan')
                    ->options(fn () => SaasPlan::query()->where('is_active', true)->orderBy('sort')->pluck('name', 'id'))
                    ->default(fn () => Tenancy::gym()->saas_plan_id)
                    ->disabled(fn () => Tenancy::gym()->status === 'active' && Tenancy::gym()->subscription_ends_at?->isFuture())
                    ->dehydratedWhenHidden()->dehydrated()->required(),
                Radio::make('period')->label('Billing')->options(['yearly' => 'Yearly', 'monthly' => 'Monthly'])->default(fn () => Tenancy::gym()->billing_period ?? 'yearly')->inline()->required(),
            ])
            ->action(function (array $data) {
                $gym = Tenancy::gym();

                try {
                    $url = app(BillingCheckout::class)->startAutoRenew($gym, SaasPlan::query()->findOrFail($data['plan'] ?? $gym->saas_plan_id), $data['period']);
                } catch (Throwable $e) {
                    report($e);
                    Notification::make()->title('Could not set up auto-renew')->body($e->getMessage())->danger()->send();

                    return null;
                }

                return redirect()->away($url);
            });
    }

    public function cancelAutoRenewAction(): Action
    {
        return Action::make('cancelAutoRenew')
            ->label('Turn off auto-renew')
            ->color('gray')
            ->requiresConfirmation()
            ->modalDescription(fn () => 'No further automatic payments will be taken. Your plan stays active until '.Tenancy::gym()->subscription_ends_at?->format('d M Y').'.')
            ->action(function () {
                try {
                    app(BillingCheckout::class)->cancelAutoRenew(Tenancy::gym());
                    Notification::make()->title('Auto-renew is off')->success()->send();
                } catch (Throwable $e) {
                    report($e);
                    Notification::make()->title('Could not turn off auto-renew')->body('Please try again or contact support.')->danger()->send();
                }
            });
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Invoices')
            ->query(SaasPayment::query()->where('gym_id', Tenancy::gymId()))
            ->defaultSort('paid_at', 'desc')
            ->paginated([10, 25])
            ->columns([
                TextColumn::make('paid_at')->label('Date')->date('d M Y'),
                TextColumn::make('invoice_number')->label('Invoice')->placeholder('—'),
                TextColumn::make('kind')->label('For')
                    ->formatStateUsing(fn ($state, SaasPayment $record) => $state === 'credits' ? 'Message credits' : ($record->saasPlan?->name ?? 'Software').' plan'),
                TextColumn::make('period_end')->label('Covers until')->date('d M Y')->placeholder('—'),
                TextColumn::make('amount')->formatStateUsing(fn ($state, SaasPayment $record) => Money::format((float) $state, $record->currency)),
            ])
            ->recordActions([
                Action::make('download')->label('PDF')->icon(Heroicon::OutlinedArrowDownTray)
                    ->visible(fn (SaasPayment $record) => filled($record->invoice_number))
                    ->url(fn (SaasPayment $record) => route('saas-invoices.show', $record), shouldOpenInNewTab: true),
            ])
            ->emptyStateHeading('No payments yet');
    }
}
