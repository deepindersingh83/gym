<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Models\Payment;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RazorpayWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function postWebhook($gym, array $event, string $secret = 'whsec')
    {
        $body = json_encode($event);

        return $this->call('POST', route('webhooks.razorpay', $gym), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_RAZORPAY_SIGNATURE' => hash_hmac('sha256', $body, $secret),
        ], $body);
    }

    public function test_paid_payment_link_settles_dues_once(): void
    {
        $gym = $this->createGym(['integrations' => ['razorpay_webhook_secret' => 'whsec']]);
        $subscription = app(SubscriptionService::class)->create($this->createMember($gym), $this->createPlan($gym, ['price' => 1000, 'gst_rate' => 0]));

        $event = [
            'event' => 'payment_link.paid',
            'payload' => [
                'payment' => ['entity' => ['id' => 'pay_123', 'amount' => 100000]],
                'payment_link' => ['entity' => ['notes' => ['type' => 'dues', 'subscription_id' => (string) $subscription->id]]],
            ],
        ];

        $this->postWebhook($gym, $event)->assertOk();
        $this->postWebhook($gym, $event)->assertOk();

        $this->assertSame(1, Payment::withoutGlobalScopes()->count());
        $this->assertSame(PaymentMethod::Online, Payment::withoutGlobalScopes()->first()->method);
        $this->assertSame(0.0, $subscription->fresh()->balance_due);
    }

    public function test_autopay_charge_renews_the_membership(): void
    {
        $gym = $this->createGym(['integrations' => ['razorpay_webhook_secret' => 'whsec']]);
        $current = app(SubscriptionService::class)->create($this->createMember($gym), $this->createPlan($gym, ['price' => 1000, 'gst_rate' => 0]));
        $current->update(['gateway_subscription_id' => 'sub_abc', 'auto_renew' => true]);

        $this->postWebhook($gym, [
            'event' => 'subscription.charged',
            'payload' => [
                'subscription' => ['entity' => ['id' => 'sub_abc']],
                'payment' => ['entity' => ['id' => 'pay_auto1', 'amount' => 100000]],
            ],
        ])->assertOk();

        $renewed = Subscription::withoutGlobalScopes()->latest('id')->first();
        $this->assertSame($current->id, $renewed->renewed_from_id);
        $this->assertTrue($renewed->start_date->equalTo($current->end_date->copy()->addDay()));
        $this->assertSame(0.0, $renewed->balance_due);
    }

    public function test_webhooks_with_a_bad_signature_are_rejected(): void
    {
        $gym = $this->createGym(['integrations' => ['razorpay_webhook_secret' => 'whsec']]);

        $this->postWebhook($gym, ['event' => 'payment_link.paid'], 'wrong')->assertUnauthorized();
    }
}
