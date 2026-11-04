<?php

namespace App\Services\Billing;

use App\Models\Gym;
use App\Models\SaasPayment;
use App\Models\SaasPlan;
use App\Services\Credits;
use App\Services\Payments\PlatformBilling;
use App\Support\AdminLog;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Online payments from gyms to the platform.
 * India pays through the platform's Razorpay account, Australia through Stripe.
 * Pay once (with discount codes and credit for unused time) or turn on auto-renew.
 */
class BillingCheckout
{
    public function __construct(
        protected GymBilling $billing,
        protected PlatformBilling $razorpay,
        protected Stripe $stripe,
    ) {}

    public function gateway(Gym $gym): string
    {
        return $gym->country === 'AU' ? 'stripe' : 'razorpay';
    }

    public function isAvailable(Gym $gym): bool
    {
        return $this->gateway($gym) === 'stripe' ? $this->stripe->isConfigured() : $this->razorpay->isConfigured();
    }

    // ---- Pay once ------------------------------------------------------------

    /** Returns the URL to pay at, or null when nothing is due (fully discounted) and the plan is already active. */
    public function payNow(Gym $gym, SaasPlan $plan, string $period, ?string $couponCode = null): ?string
    {
        $quote = $this->billing->quote($gym, $plan, $period, $couponCode);

        if ($quote['coupon_error']) {
            throw new RuntimeException($quote['coupon_error']);
        }

        $notes = [
            'type' => 'plan',
            'gym_id' => (string) $gym->id,
            'plan_id' => (string) $plan->id,
            'period' => $quote['period'],
            'list_price' => (string) $quote['list_price'],
            'credit' => (string) $quote['credit'],
            'discount' => (string) $quote['discount'],
            'coupon_id' => (string) ($quote['coupon']?->id ?? ''),
        ];

        if ($quote['total'] <= 0) {
            $this->recordPlan($gym, $notes, 0, 'manual', null, 'Fully covered by credit / discount');

            return null;
        }

        $this->ensureAvailable($gym);
        $description = "{$plan->name} plan ({$quote['period']}) for {$gym->name}";

        if ($this->gateway($gym) === 'stripe') {
            return $this->stripe->post('checkout/sessions', [
                'mode' => 'payment',
                'customer_email' => $this->ownerEmail($gym),
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => strtolower($quote['currency']),
                        'unit_amount' => (int) round($quote['total'] * 100),
                        'product_data' => ['name' => $description],
                    ],
                ]],
                'metadata' => $notes,
                'payment_intent_data' => ['metadata' => $notes, 'description' => $description],
                'success_url' => $this->returnUrl($gym, 'paid'),
                'cancel_url' => $this->returnUrl($gym),
            ])['url'];
        }

        return $this->razorpay->post('payment_links', [
            'amount' => (int) round($quote['total'] * 100),
            'currency' => $quote['currency'],
            'description' => $description,
            'customer' => $this->customer($gym),
            'notify' => ['email' => true, 'sms' => false],
            'notes' => $notes,
            'callback_url' => $this->returnUrl($gym, 'paid'),
            'callback_method' => 'get',
        ])['short_url'];
    }

    public function buyCredits(Gym $gym, int $credits): string
    {
        $price = $this->razorpay->packs($gym->currency)[$credits] ?? throw new RuntimeException('Unknown credit pack.');
        $this->ensureAvailable($gym, 'Online top-up is not available yet. Please contact support to add credits.');

        $rate = $this->billing->entity($gym)['tax_rate'];
        $total = $this->billing->taxInclusive($gym) ? $price : round($price * (1 + $rate / 100), 2);
        $notes = ['type' => 'credits', 'gym_id' => (string) $gym->id, 'credits' => (string) $credits];
        $description = number_format($credits).' message credits for '.$gym->name;
        $back = route('filament.gym.pages.message-credits', ['tenant' => $gym]);

        if ($this->gateway($gym) === 'stripe') {
            return $this->stripe->post('checkout/sessions', [
                'mode' => 'payment',
                'customer_email' => $this->ownerEmail($gym),
                'line_items' => [['quantity' => 1, 'price_data' => [
                    'currency' => strtolower($gym->currency),
                    'unit_amount' => (int) round($total * 100),
                    'product_data' => ['name' => $description],
                ]]],
                'metadata' => $notes,
                'success_url' => $back,
                'cancel_url' => $back,
            ])['url'];
        }

        return $this->razorpay->post('payment_links', [
            'amount' => (int) round($total * 100),
            'currency' => $gym->currency,
            'description' => $description,
            'customer' => $this->customer($gym),
            'notify' => ['email' => true, 'sms' => false],
            'notes' => $notes,
            'callback_url' => $back,
            'callback_method' => 'get',
        ])['short_url'];
    }

    // ---- Auto-renew ----------------------------------------------------------

    /** Sets up automatic payments; the first charge is taken when the current paid time (or trial) runs out. */
    public function startAutoRenew(Gym $gym, SaasPlan $plan, string $period): string
    {
        $this->ensureAvailable($gym);

        if ($gym->status === 'active' && $gym->subscription_ends_at?->isFuture() && $gym->saas_plan_id !== $plan->id) {
            throw new RuntimeException('To switch plans, pay for the new plan first. Then turn on auto-renew.');
        }

        $period = array_key_exists($period, GymBilling::PERIODS) ? $period : 'monthly';
        $price = $plan->price($gym->currency, $period);
        $rate = $this->billing->entity($gym)['tax_rate'];
        $amount = $this->billing->taxInclusive($gym) ? $price : round($price * (1 + $rate / 100), 2);
        $firstCharge = $this->renewalAnchor($gym);
        $notes = ['type' => 'plan', 'gym_id' => (string) $gym->id, 'plan_id' => (string) $plan->id, 'period' => $period, 'list_price' => (string) $price];

        if ($amount <= 0) {
            throw new RuntimeException('This plan has no price to charge.');
        }

        if ($this->gateway($gym) === 'stripe') {
            $subscription = ['metadata' => $notes];

            // Stripe needs a free period of at least two days; closer than that, the first charge is taken now.
            if ($firstCharge && $firstCharge->gt(now()->addHours(49))) {
                $subscription['trial_end'] = $firstCharge->getTimestamp();
            }

            return $this->stripe->post('checkout/sessions', [
                'mode' => 'subscription',
                'customer_email' => $this->ownerEmail($gym),
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => strtolower($gym->currency),
                        'unit_amount' => (int) round($amount * 100),
                        'recurring' => ['interval' => $period === 'yearly' ? 'year' : 'month'],
                        'product_data' => ['name' => "{$plan->name} plan ({$period})"],
                    ],
                ]],
                'metadata' => $notes,
                'subscription_data' => $subscription,
                'success_url' => $this->returnUrl($gym, 'auto-renew'),
                'cancel_url' => $this->returnUrl($gym),
            ])['url'];
        }

        $payload = [
            'plan_id' => $this->razorpayPlanId($plan, $period, (int) round($amount * 100)),
            'total_count' => $period === 'yearly' ? 10 : 120,
            'quantity' => 1,
            'customer_notify' => 1,
            'notes' => $notes,
        ];

        if ($firstCharge && $firstCharge->gt(now()->addMinutes(15))) {
            $payload['start_at'] = $firstCharge->getTimestamp();
        }

        $subscription = $this->razorpay->post('subscriptions', $payload);
        $gym->update(['billing_gateway' => 'razorpay', 'billing_period' => $period]);

        return $subscription['short_url'];
    }

    public function cancelAutoRenew(Gym $gym): void
    {
        $id = $gym->billing_subscription_id;

        if ($id) {
            match ($gym->billing_gateway) {
                'stripe' => $this->stripe->delete("subscriptions/{$id}"),
                'razorpay' => $this->razorpay->post("subscriptions/{$id}/cancel", ['cancel_at_cycle_end' => 0]),
                default => null,
            };
        }

        $gym->update(['auto_renew' => false, 'billing_subscription_id' => null]);
    }

    // ---- Webhooks ------------------------------------------------------------

    public function handleRazorpay(array $event): void
    {
        $type = $event['event'] ?? null;
        $payment = $event['payload']['payment']['entity'] ?? [];
        $subscription = $event['payload']['subscription']['entity'] ?? [];

        switch ($type) {
            case 'payment_link.paid':
                $notes = $event['payload']['payment_link']['entity']['notes'] ?? [];
                $this->recordFromNotes($notes, ($payment['amount'] ?? 0) / 100, 'razorpay', $payment['id'] ?? null);
                break;

            case 'subscription.authenticated':
            case 'subscription.activated':
                $this->autoRenewOn($subscription['notes'] ?? [], 'razorpay', $subscription['id'] ?? null);
                break;

            case 'subscription.charged':
                $notes = $subscription['notes'] ?? [];
                $this->autoRenewOn($notes, 'razorpay', $subscription['id'] ?? null);
                $this->recordFromNotes($notes, ($payment['amount'] ?? 0) / 100, 'razorpay', $payment['id'] ?? null, renewal: true);
                break;

            case 'subscription.pending':
                // A charge failed; Razorpay retries it over the next days.
                $this->paymentFailed($subscription['id'] ?? null, $payment['error_description'] ?? 'Your bank or card declined the automatic payment.', retrying: true);
                break;

            case 'subscription.halted':
                // All retries failed: Razorpay stops the mandate.
                $this->paymentFailed($subscription['id'] ?? null, 'Every retry of the automatic payment failed, so auto-renew has stopped.', retrying: false);
                $this->autoRenewOff($subscription['id'] ?? null);
                break;

            case 'subscription.cancelled':
            case 'subscription.completed':
                $this->autoRenewOff($subscription['id'] ?? null);
                break;

            case 'refund.processed':
                $refund = $event['payload']['refund']['entity'] ?? [];

                if (filled($refund['id'] ?? null) && filled($refund['payment_id'] ?? null)) {
                    app(Refunds::class)->recordFromGateway('razorpay', $refund['payment_id'], $refund['id'], ($refund['amount'] ?? 0) / 100);
                }
                break;
        }
    }

    public function handleStripe(array $event): void
    {
        $object = $event['data']['object'] ?? [];

        switch ($event['type'] ?? null) {
            case 'checkout.session.completed':
                if (($object['mode'] ?? null) === 'subscription') {
                    $this->autoRenewOn($object['metadata'] ?? [], 'stripe', $object['subscription'] ?? null);

                    if (filled($object['customer'] ?? null) && ($gym = Gym::query()->find($object['metadata']['gym_id'] ?? 0))) {
                        $gym->update(['billing_customer_id' => $object['customer']]);
                    }
                } elseif (($object['payment_status'] ?? null) === 'paid') {
                    $this->recordFromNotes($object['metadata'] ?? [], ($object['amount_total'] ?? 0) / 100, 'stripe', $object['payment_intent'] ?? $object['id'] ?? null);
                }
                break;

            case 'invoice.paid':
                $details = $object['parent']['subscription_details'] ?? $object['subscription_details'] ?? [];
                $subscriptionId = $details['subscription'] ?? $object['subscription'] ?? null;
                $notes = $details['metadata'] ?? [];

                if ($subscriptionId && blank($notes)) {
                    $gym = Gym::query()->where('billing_subscription_id', $subscriptionId)->first();
                    $notes = $gym ? ['type' => 'plan', 'gym_id' => $gym->id, 'plan_id' => $gym->saas_plan_id, 'period' => $gym->billing_period] : [];
                }

                // Trial invoices for A$0 are only the sign-up; nothing to record.
                if (($object['amount_paid'] ?? 0) > 0) {
                    $this->autoRenewOn($notes, 'stripe', $subscriptionId);
                    $this->recordFromNotes($notes, $object['amount_paid'] / 100, 'stripe', $object['id'] ?? null, renewal: true);
                }
                break;

            case 'invoice.payment_failed':
                $details = $object['parent']['subscription_details'] ?? $object['subscription_details'] ?? [];
                $retryAt = $object['next_payment_attempt'] ?? null;
                $this->paymentFailed(
                    $details['subscription'] ?? $object['subscription'] ?? null,
                    $object['last_finalization_error']['message'] ?? 'Your card was declined for the automatic payment.',
                    retrying: (bool) $retryAt,
                    retryAt: $retryAt ? Carbon::createFromTimestamp($retryAt) : null,
                );
                break;

            case 'customer.subscription.deleted':
                $this->autoRenewOff($object['id'] ?? null);
                break;

            case 'charge.refunded':
                foreach ($object['refunds']['data'] ?? [] as $refund) {
                    if (filled($object['payment_intent'] ?? null) && filled($refund['id'] ?? null)) {
                        app(Refunds::class)->recordFromGateway('stripe', $object['payment_intent'], $refund['id'], ($refund['amount'] ?? 0) / 100);
                    }
                }
                break;
        }
    }

    protected function recordFromNotes(array $notes, float $amount, string $gateway, ?string $gatewayId, bool $renewal = false): void
    {
        $gym = Gym::query()->find($notes['gym_id'] ?? 0);

        if (! $gym || blank($gatewayId) || SaasPayment::query()->where('gateway_payment_id', $gatewayId)->exists()) {
            return;
        }

        match ($notes['type'] ?? null) {
            'plan' => $this->recordPlan($gym, $notes, $amount, $renewal ? 'auto_renew' : $gateway, $gatewayId, $renewal ? 'Auto-renew ('.$gateway.')' : null, $gateway),
            'credits' => $this->recordCredits($gym, (int) ($notes['credits'] ?? 0), $amount, $gateway, $gatewayId),
            default => null,
        };
    }

    protected function recordPlan(Gym $gym, array $notes, float $amount, string $kind, ?string $gatewayId, ?string $reference = null, ?string $gateway = null): void
    {
        $plan = SaasPlan::query()->find($notes['plan_id'] ?? 0);

        if (! $plan) {
            return;
        }

        $this->billing->recordPlanPayment($gym, $plan, $notes['period'] ?? 'monthly', $amount, [
            'gateway' => $gateway ?? $kind,
            'renewal' => $kind === 'auto_renew',
            'gateway_payment_id' => $gatewayId,
            'reference' => $reference ?? $gatewayId,
            'list_price' => $notes['list_price'] ?? $plan->price($gym->currency, $notes['period'] ?? 'monthly'),
            'credit' => $notes['credit'] ?? 0,
            'discount' => $notes['discount'] ?? 0,
            'saas_coupon_id' => filled($notes['coupon_id'] ?? null) ? (int) $notes['coupon_id'] : null,
        ]);
    }

    protected function recordCredits(Gym $gym, int $credits, float $amount, string $gateway, string $gatewayId): void
    {
        if ($credits <= 0) {
            return;
        }

        $payment = DB::transaction(function () use ($gym, $credits, $amount, $gateway, $gatewayId) {
            $payment = $this->billing->createPayment($gym, $amount, [
                'kind' => 'credits',
                'gateway' => $gateway,
                'gateway_payment_id' => $gatewayId,
                'reference' => $gatewayId,
            ], ['description' => number_format($credits).' message credits (SMS / WhatsApp)']);

            app(Credits::class)->add($gym, $credits, 'purchase', number_format($credits).' credits bought online', ['saas_payment_id' => $payment->id]);

            return $payment;
        });

        $this->billing->emailInvoice($payment);
    }

    protected function autoRenewOn(array $notes, string $gateway, ?string $subscriptionId): void
    {
        $gym = Gym::query()->find($notes['gym_id'] ?? 0);

        if (! $gym || blank($subscriptionId) || $gym->billing_subscription_id === $subscriptionId) {
            return;
        }

        // Only one automatic payment at a time: stop the previous one.
        if ($gym->billing_subscription_id) {
            rescue(fn () => $this->cancelAutoRenew($gym), report: true);
        }

        $gym->update([
            'auto_renew' => true,
            'billing_gateway' => $gateway,
            'billing_subscription_id' => $subscriptionId,
            'billing_period' => $notes['period'] ?? 'monthly',
        ]);
    }

    /** Records a failed automatic payment and tells the owner what to do. Gyms keep the usual grace period. */
    protected function paymentFailed(?string $subscriptionId, string $reason, bool $retrying, ?Carbon $retryAt = null): void
    {
        $gym = blank($subscriptionId) ? null : Gym::query()->where('billing_subscription_id', $subscriptionId)->first();

        if (! $gym) {
            return;
        }

        $firstFailure = ! $gym->payment_failed_at;
        $gym->update(['payment_failed_at' => $gym->payment_failed_at ?? now(), 'payment_failure_reason' => Str::limit($reason, 250), 'payment_retry_at' => $retrying ? $retryAt : null]);

        // One email when it first fails, and one when retries have run out.
        if ($firstFailure || ! $retrying) {
            $next = $retrying
                ? 'We will try again'.($retryAt ? ' on '.$retryAt->format('d M Y') : ' in the next few days').'. '.($gym->billing_gateway === 'stripe' ? 'Update your card on the Plan & billing page so it goes through.' : 'Make sure your account or card has funds, or pay now on the Plan & billing page.')
                : 'Auto-renew has stopped. Pay now on the Plan & billing page'.($gym->subscription_ends_at ? ' before '.$gym->subscription_ends_at->copy()->addDays((int) config('gym.billing.grace_days'))->format('d M Y') : '').' to keep making changes.';

            $this->billing->emailOwner($gym, 'Your automatic payment failed', "We could not take your automatic payment for {$gym->name}: {$reason}\n\n{$next}\n\nPlan & billing: ".route('filament.gym.pages.billing', ['tenant' => $gym]));
        }

        AdminLog::record('Automatic payment failed', $gym, ['reason' => $reason, 'retrying' => $retrying]);
    }

    /** Stripe page where the owner updates their card (Australia). */
    public function updateCardUrl(Gym $gym): string
    {
        if ($gym->billing_gateway !== 'stripe' || blank($gym->billing_customer_id)) {
            throw new RuntimeException('Card details are managed by your bank for this payment method.');
        }

        return $this->stripe->post('billing_portal/sessions', [
            'customer' => $gym->billing_customer_id,
            'return_url' => $this->returnUrl($gym),
        ])['url'];
    }

    protected function autoRenewOff(?string $subscriptionId): void
    {
        if (blank($subscriptionId)) {
            return;
        }

        Gym::query()->where('billing_subscription_id', $subscriptionId)->update(['auto_renew' => false, 'billing_subscription_id' => null]);
    }

    // ---- Helpers ---------------------------------------------------------------

    /** When the next payment is due: end of the trial or of the paid time, if still ahead. */
    protected function renewalAnchor(Gym $gym): ?CarbonInterface
    {
        $end = $gym->status === 'trial' ? $gym->trial_ends_at : $gym->subscription_ends_at;

        return $end?->isFuture() ? $end : null;
    }

    protected function razorpayPlanId(SaasPlan $plan, string $period, int $paise): string
    {
        $key = "razorpay:{$period}:{$paise}";
        $ids = $plan->gateway_ids ?? [];

        if (empty($ids[$key])) {
            $ids[$key] = $this->razorpay->post('plans', [
                'period' => $period,
                'interval' => 1,
                'item' => ['name' => config('app.name')." {$plan->name} ({$period})", 'amount' => $paise, 'currency' => 'INR'],
                'notes' => ['saas_plan_id' => (string) $plan->id],
            ])['id'];

            $plan->update(['gateway_ids' => $ids]);
        }

        return $ids[$key];
    }

    protected function ensureAvailable(Gym $gym, string $message = 'Online payment is not set up yet. Please contact support to renew.'): void
    {
        if (! $this->isAvailable($gym)) {
            throw new RuntimeException($message);
        }
    }

    protected function ownerEmail(Gym $gym): ?string
    {
        return $gym->users()->wherePivot('role', 'owner')->first()?->email ?? $gym->email;
    }

    protected function customer(Gym $gym): array
    {
        $owner = $gym->users()->wherePivot('role', 'owner')->first();

        return array_filter(['name' => $owner?->name ?? $gym->name, 'email' => $owner?->email ?? $gym->email, 'contact' => $gym->phone]);
    }

    protected function returnUrl(Gym $gym, ?string $status = null): string
    {
        return route('filament.gym.pages.billing', array_filter(['tenant' => $gym, 'payment' => $status]));
    }
}
