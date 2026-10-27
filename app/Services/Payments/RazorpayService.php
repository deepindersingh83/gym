<?php

namespace App\Services\Payments;

use App\Enums\PaymentMethod;
use App\Models\Gym;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\Subscription;
use App\Services\Messaging\PhoneNumber;
use App\Services\SubscriptionService;
use App\Support\Tenancy;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Online payments through each gym's own Razorpay account: payment links for
 * dues and renewals, and Razorpay Subscriptions for UPI AutoPay renewals.
 */
class RazorpayService
{
    public function __construct(protected Gym $gym) {}

    public static function for(Gym $gym): static
    {
        return new static($gym);
    }

    public function isConfigured(): bool
    {
        return filled($this->keyId()) && filled($this->keySecret());
    }

    /** Payment link for the unpaid balance of a membership. */
    public function paymentLinkForDues(Subscription $subscription): string
    {
        return $this->createPaymentLink(
            $subscription->member,
            $subscription->balance_due,
            "{$this->gym->name} — {$subscription->plan->name} ({$subscription->invoice_number})",
            ['type' => 'dues', 'subscription_id' => (string) $subscription->id],
        );
    }

    /** Payment link that renews the member on the given plan once paid. */
    public function paymentLinkForRenewal(Member $member, MembershipPlan $plan): string
    {
        $quote = app(SubscriptionService::class)->quote($this->gym, $plan, null, ! $member->subscriptions()->exists());

        return $this->createPaymentLink(
            $member,
            $quote['total_amount'],
            "{$this->gym->name} — {$plan->name} membership",
            ['type' => 'renewal', 'member_id' => (string) $member->id, 'plan_id' => (string) $plan->id],
        );
    }

    /**
     * Sets up UPI AutoPay (Razorpay Subscription) that charges the plan price on each renewal date.
     * Returns the link the member opens to approve the mandate in their UPI app.
     */
    public function setUpAutoPay(Subscription $subscription): string
    {
        $plan = $subscription->plan;
        $total = app(SubscriptionService::class)->quote($this->gym, $plan, null, false)['total_amount'];

        if ($plan->duration_unit !== 'month') {
            throw new RuntimeException('UPI AutoPay works with plans measured in months.');
        }

        if (! $plan->razorpay_plan_id) {
            $created = $this->request()->post('plans', [
                'period' => 'monthly',
                'interval' => $plan->duration_value,
                'item' => ['name' => "{$this->gym->name} {$plan->name}", 'amount' => (int) round($total * 100), 'currency' => $this->gym->currency],
                'notes' => ['gym_id' => (string) $this->gym->id, 'plan_id' => (string) $plan->id],
            ])->throw()->json();
            $plan->forceFill(['razorpay_plan_id' => $created['id']])->save();
        }

        $response = $this->request()->post('subscriptions', [
            'plan_id' => $plan->razorpay_plan_id,
            'total_count' => 60,
            'quantity' => 1,
            'customer_notify' => 1,
            'start_at' => $subscription->end_date->copy()->addDay()->setTime(6, 0)->max(now()->addHour())->timestamp,
            'notes' => ['gym_id' => (string) $this->gym->id, 'subscription_id' => (string) $subscription->id],
        ])->throw()->json();

        $subscription->forceFill(['gateway_subscription_id' => $response['id'], 'auto_renew' => true])->save();

        return $response['short_url'];
    }

    public function cancelAutoPay(Subscription $subscription): void
    {
        if ($subscription->gateway_subscription_id) {
            $this->request()->post("subscriptions/{$subscription->gateway_subscription_id}/cancel", ['cancel_at_cycle_end' => 0]);
        }

        $subscription->forceFill(['auto_renew' => false])->save();
    }

    public function verifyWebhookSignature(string $payload, ?string $signature): bool
    {
        $secret = $this->gym->integration('razorpay_webhook_secret', 'gym.razorpay.webhook_secret');

        return filled($secret) && filled($signature) && hash_equals(hash_hmac('sha256', $payload, $secret), $signature);
    }

    /** Records payments for the webhook events we subscribe to. Safe to receive twice. */
    public function handleWebhook(array $event): void
    {
        Tenancy::forGym($this->gym, function () use ($event) {
            match ($event['event'] ?? null) {
                'payment_link.paid' => $this->onPaymentLinkPaid($event['payload']),
                'subscription.charged' => $this->onSubscriptionCharged($event['payload']),
                default => null,
            };
        });
    }

    protected function onPaymentLinkPaid(array $payload): void
    {
        $payment = $payload['payment']['entity'];
        $notes = $payload['payment_link']['entity']['notes'] ?? [];

        if ($this->alreadyRecorded($payment['id'])) {
            return;
        }

        DB::transaction(function () use ($payment, $notes) {
            $service = app(SubscriptionService::class);
            $amount = $payment['amount'] / 100;

            if (($notes['type'] ?? null) === 'dues') {
                $subscription = Subscription::query()->findOrFail($notes['subscription_id']);
            } else {
                $member = Member::query()->findOrFail($notes['member_id']);
                $plan = MembershipPlan::query()->findOrFail($notes['plan_id']);
                $latest = $member->latestSubscription;
                $subscription = $latest ? $service->renew($latest, $plan) : $service->create($member, $plan);
            }

            $service->recordPayment($subscription, $amount, PaymentMethod::Online, $payment['id'], ['gateway_payment_id' => $payment['id']]);
        });
    }

    protected function onSubscriptionCharged(array $payload): void
    {
        $payment = $payload['payment']['entity'];
        $gatewaySubscriptionId = $payload['subscription']['entity']['id'];

        if ($this->alreadyRecorded($payment['id'])) {
            return;
        }

        $current = Subscription::query()->where('gateway_subscription_id', $gatewaySubscriptionId)->latest('end_date')->first();

        if (! $current) {
            Log::warning('Razorpay subscription charge for unknown subscription', ['id' => $gatewaySubscriptionId]);

            return;
        }

        DB::transaction(function () use ($current, $payment, $gatewaySubscriptionId) {
            $service = app(SubscriptionService::class);
            $renewed = $service->renew($current, attributes: ['gateway_subscription_id' => $gatewaySubscriptionId, 'auto_renew' => true]);
            $service->recordPayment($renewed, $payment['amount'] / 100, PaymentMethod::Online, $payment['id'], ['gateway_payment_id' => $payment['id']]);
        });
    }

    protected function alreadyRecorded(string $gatewayPaymentId): bool
    {
        return Payment::withoutGlobalScopes()->where('gateway_payment_id', $gatewayPaymentId)->exists();
    }

    protected function createPaymentLink(Member $member, float $amount, string $description, array $notes): string
    {
        if ($amount <= 0) {
            throw new RuntimeException('Nothing to pay.');
        }

        $response = $this->request()->post('payment_links', [
            'amount' => (int) round($amount * 100),
            'currency' => $this->gym->currency,
            'description' => mb_substr($description, 0, 2048),
            'customer' => array_filter([
                'name' => $member->name,
                'contact' => '+'.PhoneNumber::international($member->phone),
                'email' => $member->email,
            ]),
            'notify' => ['sms' => true, 'email' => filled($member->email)],
            'reminder_enable' => true,
            'notes' => ['gym_id' => (string) $this->gym->id, ...$notes],
            'callback_url' => route('filament.member.pages.dashboard'),
            'callback_method' => 'get',
        ])->throw()->json();

        return $response['short_url'];
    }

    protected function request(): PendingRequest
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Add your Razorpay keys under Gym settings → Integrations first.');
        }

        return Http::baseUrl('https://api.razorpay.com/v1/')
            ->withBasicAuth($this->keyId(), $this->keySecret())
            ->acceptJson()
            ->timeout(20);
    }

    protected function keyId(): ?string
    {
        return $this->gym->integration('razorpay_key_id', 'gym.razorpay.key_id');
    }

    protected function keySecret(): ?string
    {
        return $this->gym->integration('razorpay_key_secret', 'gym.razorpay.key_secret');
    }
}
