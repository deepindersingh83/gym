<?php

namespace App\Filament\Gym\Actions;

use App\Enums\MessageEvent;
use App\Enums\PaymentMethod;
use App\Enums\SubscriptionStatus;
use App\Filament\Gym\Support\MembershipFields;
use App\Models\Attendance;
use App\Models\Coupon;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Messaging\Messenger;
use App\Services\Messaging\PhoneNumber;
use App\Services\SubscriptionService;
use App\Support\Access;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Auth\Notifications\ResetPassword;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/** Actions used on member and membership screens. */
class MemberActions
{
    /** Sell a plan to a member: first membership or a new one after the last ended. */
    public static function sellMembership(): Action
    {
        return Action::make('sellMembership')
            ->label('New membership')
            ->icon(Heroicon::OutlinedPlusCircle)
            ->visible(fn () => Access::can('subscriptions.create'))
            ->schema(fn (Member $record) => MembershipFields::make(
                withAdmissionFee: ! $record->subscriptions()->exists(),
                defaultStart: fn () => static::nextStartDate($record),
            ))
            ->action(function (Member $record, array $data): void {
                static::sell($record, $data);
                Notification::make()->title('Membership created')->success()->send();
            });
    }

    /** Renew a member's latest membership, starting the day after it ends. */
    public static function renew(): Action
    {
        return Action::make('renew')
            ->label('Renew')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('success')
            ->visible(fn ($record) => Access::can('subscriptions.create') && static::subscriptionFor($record) !== null)
            ->fillForm(fn ($record) => [
                'membership_plan_id' => static::subscriptionFor($record)?->membership_plan_id,
                'start_date' => static::nextStartDate(static::subscriptionFor($record)->member),
                'amount_paid' => null,
            ])
            ->schema(MembershipFields::make(withAdmissionFee: false))
            ->action(function ($record, array $data): void {
                $previous = static::subscriptionFor($record);
                $subscription = static::sell($previous->member, $data, $previous);
                Notification::make()
                    ->title('Renewed until '.$subscription->end_date->format('d M Y'))
                    ->success()->send();
            });
    }

    /** Take a payment (full or part) against a membership with dues. */
    public static function recordPayment(): Action
    {
        return Action::make('recordPayment')
            ->label('Record payment')
            ->icon(Heroicon::OutlinedBanknotes)
            ->color('warning')
            ->visible(fn ($record) => Access::can('payments.create') && static::subscriptionsWithDues($record)->isNotEmpty())
            ->fillForm(function ($record) {
                $subscription = static::subscriptionsWithDues($record)->first();

                return ['subscription_id' => $subscription?->id, 'amount' => $subscription?->balance_due, 'method' => PaymentMethod::Cash->value, 'paid_at' => now()];
            })
            ->schema(fn ($record) => [
                Select::make('subscription_id')
                    ->label('Membership')
                    ->options(static::subscriptionsWithDues($record)->mapWithKeys(fn (Subscription $s) => [
                        $s->id => "{$s->invoice_number} · {$s->plan?->name} · due ".Money::format($s->balance_due),
                    ]))
                    ->required(),
                TextInput::make('amount')->numeric()->minValue(1)->prefix(Money::symbol())->required(),
                Select::make('method')->options(PaymentMethod::class)->required(),
                TextInput::make('reference')->label('UPI / transaction ref.')->maxLength(100),
                DateTimePicker::make('paid_at')->required()->maxDate(now()->endOfDay()),
            ])
            ->action(function ($record, array $data): void {
                $subscription = Subscription::query()->findOrFail($data['subscription_id']);
                app(SubscriptionService::class)->recordPayment($subscription, (float) $data['amount'], $data['method'], $data['reference'] ?? null, ['paid_at' => $data['paid_at']]);
                Notification::make()->title('Payment of '.Money::format($data['amount']).' recorded')->success()->send();
            });
    }

    public static function checkIn(): Action
    {
        return Action::make('checkIn')
            ->label('Check in')
            ->icon(Heroicon::OutlinedArrowRightEndOnRectangle)
            ->color('gray')
            ->visible(fn () => Access::can('attendance.create'))
            ->requiresConfirmation()
            ->modalDescription(fn (Member $record) => $record->currentSubscription
                ? 'Membership valid until '.$record->currentSubscription->end_date->format('d M Y').'.'
                : 'This member has no active membership.')
            ->action(function (Member $record): void {
                Attendance::create(['member_id' => $record->id, 'branch_id' => $record->branch_id, 'checked_in_at' => now(), 'method' => 'manual']);
                Notification::make()->title($record->name.' checked in')->success()->send();
            });
    }

    /**
     * Gives the member a member-app login. With an email they get a set-password
     * link; otherwise a WhatsApp / SMS message to sign in with their mobile and a code.
     */
    public static function invite(): Action
    {
        return Action::make('invite')
            ->label(fn (Member $record) => $record->user_id ? 'Resend member app invite' : 'Invite to member app')
            ->icon(Heroicon::OutlinedDevicePhoneMobile)
            ->color('gray')
            ->visible(fn () => Access::can('members.edit'))
            ->requiresConfirmation()
            ->modalDescription(fn (Member $record) => filled($record->email)
                ? "We'll email {$record->email} a link to set a password. They can also sign in with their mobile number and a one-time code."
                : "We'll message {$record->phone} on WhatsApp / SMS. They sign in with their mobile number and a one-time code.")
            ->action(function (Member $record): void {
                $user = static::appUserFor($record);

                if (filled($user->email)) {
                    $status = Password::broker(Filament::getPanel('member')->getAuthPasswordBroker())
                        ->sendResetLink(['email' => $user->email], function (User $user, string $token) {
                            $notification = new ResetPassword($token);
                            $notification->url = Filament::getPanel('member')->getResetPasswordUrl($token, $user);
                            $user->notify($notification);
                        });
                    $sent = $status === Password::RESET_LINK_SENT;
                } else {
                    $sent = app(Messenger::class)->send($record, MessageEvent::AppInvite, ['app_url' => Filament::getPanel('member')->getLoginUrl()]) > 0;
                }

                Notification::make()
                    ->title($sent ? 'Invite sent' : 'Member app login created')
                    ->body($sent
                        ? 'They can sign in at '.Filament::getPanel('member')->getLoginUrl()
                        : 'The message could not be sent (check Messaging → Sent messages). Use "Set app password" to give them a password instead.')
                    ->status($sent ? 'success' : 'warning')
                    ->send();
            });
    }

    /** Lets the front desk set a password with the member, e.g. for someone without email or WhatsApp. */
    public static function setAppPassword(): Action
    {
        return Action::make('setAppPassword')
            ->label('Set app password')
            ->icon(Heroicon::OutlinedKey)
            ->color('gray')
            ->visible(fn () => Access::can('members.edit'))
            ->modalDescription(fn (Member $record) => "{$record->name} signs in at ".Filament::getPanel('member')->getLoginUrl()." with mobile number {$record->phone} and this password.")
            ->schema([
                TextInput::make('password')->password()->revealable()->required()->minLength(8)
                    ->helperText('At least 8 characters. Ask the member to type it themselves.'),
            ])
            ->action(function (Member $record, array $data): void {
                $user = static::appUserFor($record);
                $user->update(['password' => $data['password']]);

                Notification::make()->title('Member app password set')->success()->send();
            });
    }

    /**
     * The member's app login: the one already linked, else an existing login with the
     * same mobile or email (one person, one login across gyms), else a new one.
     */
    public static function appUserFor(Member $member): User
    {
        $user = $member->user
            ?? User::findByPhone($member->phone)
            ?? (filled($member->email) ? User::query()->where('email', strtolower($member->email))->first() : null)
            ?? User::create([
                'name' => $member->name,
                'email' => $member->email ?: null,
                'phone' => $member->phone,
                'password' => Str::password(32),
            ]);

        if (blank($user->phone_e164)) {
            $user->update(['phone' => $member->phone]);
        }

        if ($member->user_id !== $user->id) {
            $member->update(['user_id' => $user->id]);
        }

        return $user;
    }

    public static function whatsapp(): Action
    {
        return Action::make('whatsapp')
            ->label('WhatsApp')
            ->icon(Heroicon::OutlinedChatBubbleOvalLeft)
            ->color('success')
            ->url(fn (Member $record) => 'https://wa.me/'.preg_replace('/\D/', '', PhoneNumber::international($record->phone)), shouldOpenInNewTab: true);
    }

    // ---- helpers ------------------------------------------------------------

    public static function sell(Member $member, array $data, ?Subscription $renewing = null): Subscription
    {
        $service = app(SubscriptionService::class);
        $plan = MembershipPlan::query()->findOrFail($data['membership_plan_id']);
        $coupon = filled($data['coupon_id'] ?? null) ? Coupon::query()->find($data['coupon_id']) : null;
        $paid = filled($data['amount_paid'] ?? null) ? (float) $data['amount_paid'] : null;
        $method = $data['payment_method'] ?? PaymentMethod::Cash->value;
        $start = Carbon::parse($data['start_date']);

        $subscription = $service->create(
            $member, $plan, $start, $coupon, null, null, $data['additional_member_ids'] ?? [],
            $renewing ? ['renewed_from_id' => $renewing->id, 'auto_renew' => false] : [],
        );

        if ($paid > 0) {
            $service->recordPayment($subscription, $paid, $method, $data['payment_reference'] ?? null);
        }

        return $subscription->refresh();
    }

    protected static function nextStartDate(Member $member): Carbon
    {
        $lastEnd = $member->subscriptions()->where('status', '!=', SubscriptionStatus::Cancelled)->max('end_date');

        return $lastEnd ? Carbon::parse($lastEnd)->addDay()->max(today()) : today();
    }

    protected static function subscriptionFor(Member|Subscription $record): ?Subscription
    {
        return $record instanceof Subscription ? $record : $record->latestSubscription;
    }

    protected static function subscriptionsWithDues(Member|Subscription $record)
    {
        return $record instanceof Subscription
            ? collect($record->balance_due > 0 ? [$record] : [])
            : $record->subscriptions()->withDues()->with('plan')->orderBy('end_date')->get();
    }
}
