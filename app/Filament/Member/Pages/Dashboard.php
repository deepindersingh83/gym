<?php

namespace App\Filament\Member\Pages;

use App\Models\MembershipPlan;
use App\Models\TrainerRating;
use App\Services\Payments\RazorpayService;
use App\Support\CurrentMember;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;
use Throwable;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'My membership';

    protected function getHeaderActions(): array
    {
        $member = CurrentMember::get();
        $online = RazorpayService::for($member->gym)->isConfigured();

        return [
            Action::make('payDues')
                ->label(fn () => 'Pay dues '.Money::format($member->balance_due))
                ->icon(Heroicon::OutlinedCreditCard)
                ->color('danger')
                ->visible(fn () => $online && $member->balance_due > 0)
                ->action(function () use ($member) {
                    $subscription = $member->subscriptions()->withDues()->orderBy('end_date')->first();

                    return $this->goToPayment(fn () => RazorpayService::for($member->gym)->paymentLinkForDues($subscription));
                }),
            Action::make('renew')
                ->label('Renew online')
                ->icon(Heroicon::OutlinedArrowPath)
                ->visible($online)
                ->schema([
                    Select::make('plan_id')
                        ->label('Plan')
                        ->options(MembershipPlan::query()->where('is_active', true)->orderBy('price')->get()
                            ->mapWithKeys(fn (MembershipPlan $p) => [$p->id => "{$p->name} — ".Money::format($p->price)]))
                        ->default($member->latestSubscription?->membership_plan_id)
                        ->required(),
                ])
                ->modalDescription('You will be taken to a secure Razorpay page to pay by UPI, card or net banking. Your new membership starts the day after the current one ends.')
                ->action(fn (array $data) => $this->goToPayment(fn () => RazorpayService::for($member->gym)
                    ->paymentLinkForRenewal($member, MembershipPlan::query()->findOrFail($data['plan_id'])))),
            Action::make('rateTrainer')
                ->label('Rate my trainer')
                ->icon(Heroicon::OutlinedStar)
                ->color('gray')
                ->visible(fn () => $member->trainer_id !== null)
                ->modalHeading(fn () => 'How is '.$member->trainer?->name.' doing?')
                ->schema([
                    ToggleButtons::make('rating')->options([1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5'])->inline()->required()->default(5),
                    Textarea::make('comment')->rows(3)->maxLength(1000),
                ])
                ->action(function (array $data) use ($member) {
                    TrainerRating::create([
                        'gym_id' => $member->gym_id,
                        'member_id' => $member->id,
                        'trainer_id' => $member->trainer_id,
                        'rating' => (int) $data['rating'],
                        'comment' => $data['comment'] ?? null,
                    ]);
                    Notification::make()->title('Thanks for your feedback!')->success()->send();
                }),
        ];
    }

    protected function goToPayment(callable $makeLink)
    {
        try {
            return redirect()->away($makeLink());
        } catch (Throwable $e) {
            report($e);
            Notification::make()->title('Online payment is not available right now')->body('Please pay at the front desk.')->danger()->send();
        }
    }
}
