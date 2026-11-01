<?php

namespace Tests\Feature;

use App\Enums\StaffRole;
use App\Filament\Gym\Pages\Billing;
use App\Models\Gym;
use App\Models\SaasCoupon;
use App\Models\SaasPayment;
use App\Models\SaasPlan;
use App\Services\Billing\BillingCheckout;
use App\Services\Billing\GymBilling;
use Database\Seeders\SaasPlanSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class GymBillingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-10 10:00:00');
        $this->seed(SaasPlanSeeder::class);
        config([
            'mail.default' => 'array',
            'gym.billing.grace_days' => 3,
            'gym.billing.entities.IN' => ['legal_name' => 'GymFlow India Pvt Ltd', 'address' => 'Bengaluru', 'tax_id' => '29ABCDE1234F1Z5', 'state_code' => null, 'tax_rate' => 18, 'invoice_prefix' => 'GF-IN'],
            'gym.billing.entities.AU' => ['legal_name' => 'GymFlow Pty Ltd', 'address' => 'Sydney NSW', 'tax_id' => '51824753556', 'state_code' => null, 'tax_rate' => 10, 'invoice_prefix' => 'GF-AU'],
            'gym.platform_razorpay' => ['key_id' => 'rzp_test', 'key_secret' => 'secret', 'webhook_secret' => 'rzp_hook'],
            'gym.stripe' => ['secret_key' => 'sk_test', 'webhook_secret' => 'whsec_test'],
        ]);
    }

    protected function plan(string $slug): SaasPlan
    {
        return SaasPlan::query()->where('slug', $slug)->firstOrFail();
    }

    protected function auGym(array $attributes = []): Gym
    {
        return $this->createGym(['name' => 'Bondi Strength', 'country' => 'AU', 'abn' => '51824753556', 'gst_registered' => true, ...$attributes]);
    }

    protected function razorpayHook(array $event)
    {
        $payload = json_encode($event);

        return $this->call('POST', route('webhooks.platform'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_RAZORPAY_SIGNATURE' => hash_hmac('sha256', $payload, 'rzp_hook'),
        ], $payload);
    }

    protected function stripeHook(array $event, ?string $secret = 'whsec_test')
    {
        $payload = json_encode($event);
        $t = time();

        return $this->call('POST', route('webhooks.stripe'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => "t={$t},v1=".hash_hmac('sha256', "{$t}.{$payload}", $secret),
        ], $payload);
    }

    protected function sentMail(): array
    {
        return app('mailer')->getSymfonyTransport()->messages()->all();
    }

    public function test_india_prices_get_18_percent_gst_and_australia_prices_include_gst(): void
    {
        $billing = app(GymBilling::class);

        $india = $billing->quote($this->createGym(['status' => 'trial']), $this->plan('pro'), 'yearly');
        $this->assertSame(19990.0, $india['list_price']);
        $this->assertSame(23588.2, $india['total']);
        $this->assertSame(3598.2, $india['tax_amount']);

        $au = $billing->quote($this->auGym(), $this->plan('pro'), 'yearly');
        $this->assertSame('AUD', $au['currency']);
        $this->assertSame(590.0, $au['total']);
        $this->assertSame(53.64, $au['tax_amount']);

        // Without a GSTIN the platform is not registered, so no GST is added.
        config(['gym.billing.entities.IN.tax_id' => null]);
        $this->assertSame(19990.0, $billing->quote($this->createGym(), $this->plan('pro'), 'yearly')['total']);
    }

    public function test_razorpay_payment_activates_the_plan_and_issues_a_gst_invoice_once(): void
    {
        $gym = $this->createGym(['status' => 'trial', 'trial_ends_at' => now()->addDays(4), 'state_code' => '29']);
        $event = [
            'event' => 'payment_link.paid',
            'payload' => [
                'payment_link' => ['entity' => ['notes' => ['type' => 'plan', 'gym_id' => (string) $gym->id, 'plan_id' => (string) $this->plan('pro')->id, 'period' => 'yearly', 'list_price' => '19990', 'credit' => '0', 'discount' => '0', 'coupon_id' => '']]],
                'payment' => ['entity' => ['id' => 'pay_123', 'amount' => 2358820]],
            ],
        ];

        $this->razorpayHook($event)->assertOk();
        $this->razorpayHook($event)->assertOk();

        $gym->refresh();
        $this->assertSame('active', $gym->status);
        $this->assertSame($this->plan('pro')->id, $gym->saas_plan_id);
        // Paid during the trial: the year starts when the trial ends.
        $this->assertSame(now()->addDays(4)->addYear()->toDateString(), $gym->subscription_ends_at->toDateString());

        $payment = SaasPayment::query()->sole();
        $this->assertSame('GF-IN-2627-0001', $payment->invoice_number);
        $this->assertSame('razorpay', $payment->gateway);
        $this->assertSame('3598.20', $payment->tax_amount);
        $this->assertSame('cgst_sgst', $payment->invoice['tax_split']);
        $this->assertCount(1, $this->sentMail());
        $this->assertStringContainsString('GF-IN-2627-0001', $this->sentMail()[0]->getOriginalMessage()->getSubject());

        // Gym in another state: IGST.
        $other = $this->createGym(['state_code' => '27']);
        $second = app(GymBilling::class)->recordPlanPayment($other, $this->plan('basic'), 'monthly', 1178.82, ['gateway' => 'manual']);
        $this->assertSame('igst', $second->invoice['tax_split']);
        $this->assertSame('GF-IN-2627-0002', $second->invoice_number);

        $this->actingAs($this->ownerOf($gym))->get(route('saas-invoices.show', $payment))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($this->ownerOf($other))->get(route('saas-invoices.show', $payment))->assertForbidden();
    }

    public function test_pay_now_creates_a_razorpay_link_with_the_quoted_amount(): void
    {
        Http::fake(['api.razorpay.com/v1/payment_links' => Http::response(['short_url' => 'https://rzp.io/x'])]);
        $gym = $this->createGym();

        $url = app(BillingCheckout::class)->payNow($gym, $this->plan('basic'), 'monthly');

        $this->assertSame('https://rzp.io/x', $url);
        Http::assertSent(fn (Request $r) => $r['amount'] === 117882 && $r['currency'] === 'INR' && $r['notes']['type'] === 'plan' && $r['notes']['period'] === 'monthly');
    }

    public function test_stripe_one_off_payment_for_an_australian_gym(): void
    {
        Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response(['url' => 'https://checkout.stripe.com/c/1'])]);
        $gym = $this->auGym();

        $this->assertSame('https://checkout.stripe.com/c/1', app(BillingCheckout::class)->payNow($gym, $this->plan('pro'), 'yearly'));
        Http::assertSent(fn (Request $r) => $r['mode'] === 'payment' && $r['line_items'][0]['price_data']['unit_amount'] === 59000 && $r['line_items'][0]['price_data']['currency'] === 'aud');

        $event = ['type' => 'checkout.session.completed', 'data' => ['object' => [
            'id' => 'cs_1', 'mode' => 'payment', 'payment_status' => 'paid', 'amount_total' => 59000, 'payment_intent' => 'pi_1',
            'metadata' => ['type' => 'plan', 'gym_id' => (string) $gym->id, 'plan_id' => (string) $this->plan('pro')->id, 'period' => 'yearly', 'list_price' => '590'],
        ]]];

        $this->stripeHook($event, 'wrong')->assertUnauthorized();
        $this->stripeHook($event)->assertOk();
        $this->stripeHook($event)->assertOk();

        $payment = SaasPayment::query()->sole();
        $this->assertSame('GF-AU-2627-0001', $payment->invoice_number);
        $this->assertSame('AUD', $payment->currency);
        $this->assertSame('53.64', $payment->tax_amount);
        $this->assertTrue($payment->invoice['tax_inclusive']);
        $this->assertSame('active', $gym->fresh()->status);
    }

    public function test_auto_renew_with_stripe_charges_each_period_and_can_be_cancelled(): void
    {
        Http::fake([
            'api.stripe.com/v1/checkout/sessions' => Http::response(['url' => 'https://checkout.stripe.com/c/2']),
            'api.stripe.com/v1/subscriptions/*' => Http::response(['status' => 'canceled']),
        ]);
        $gym = $this->auGym(['status' => 'active', 'saas_plan_id' => $this->plan('basic')->id, 'subscription_ends_at' => now()->addDays(10)]);

        app(BillingCheckout::class)->startAutoRenew($gym, $this->plan('basic'), 'monthly');
        Http::assertSent(fn (Request $r) => $r['mode'] === 'subscription'
            && $r['line_items'][0]['price_data']['recurring']['interval'] === 'month'
            && $r['subscription_data']['trial_end'] === now()->addDays(10)->getTimestamp());

        $notes = ['type' => 'plan', 'gym_id' => (string) $gym->id, 'plan_id' => (string) $this->plan('basic')->id, 'period' => 'monthly', 'list_price' => '29'];
        $this->stripeHook(['type' => 'checkout.session.completed', 'data' => ['object' => ['mode' => 'subscription', 'subscription' => 'sub_1', 'metadata' => $notes]]])->assertOk();
        $this->assertTrue($gym->fresh()->auto_renew);

        $this->travel(10)->days();
        $this->stripeHook(['type' => 'invoice.paid', 'data' => ['object' => [
            'id' => 'in_1', 'amount_paid' => 2900, 'parent' => ['subscription_details' => ['subscription' => 'sub_1', 'metadata' => $notes]],
        ]]])->assertOk();

        $gym->refresh();
        $this->assertSame(now()->addMonthNoOverflow()->toDateString(), $gym->subscription_ends_at->toDateString());
        $this->assertSame(1, $gym->saasPayments()->count());

        app(BillingCheckout::class)->cancelAutoRenew($gym);
        $this->assertFalse($gym->fresh()->auto_renew);
        Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && str_ends_with($r->url(), 'subscriptions/sub_1'));
    }

    public function test_razorpay_subscription_charges_extend_from_the_current_end(): void
    {
        $gym = $this->createGym(['status' => 'active', 'saas_plan_id' => $this->plan('basic')->id, 'subscription_ends_at' => now()->addDay()]);
        $notes = ['type' => 'plan', 'gym_id' => (string) $gym->id, 'plan_id' => (string) $this->plan('basic')->id, 'period' => 'monthly'];

        $this->razorpayHook(['event' => 'subscription.charged', 'payload' => [
            'subscription' => ['entity' => ['id' => 'sub_R1', 'notes' => $notes]],
            'payment' => ['entity' => ['id' => 'pay_R1', 'amount' => 117882]],
        ]])->assertOk();

        $gym->refresh();
        $this->assertTrue($gym->auto_renew);
        $this->assertSame('sub_R1', $gym->billing_subscription_id);
        $this->assertSame(now()->addDay()->addMonthNoOverflow()->toDateString(), $gym->subscription_ends_at->toDateString());

        $this->razorpayHook(['event' => 'subscription.cancelled', 'payload' => ['subscription' => ['entity' => ['id' => 'sub_R1']]]])->assertOk();
        $this->assertFalse($gym->fresh()->auto_renew);
    }

    public function test_upgrading_gives_credit_for_unused_days(): void
    {
        $gym = $this->createGym(['status' => 'active', 'saas_plan_id' => $this->plan('basic')->id, 'subscription_ends_at' => now()->addDays(15)]);
        $gym->saasPayments()->create(['kind' => 'subscription', 'saas_plan_id' => $this->plan('basic')->id, 'amount' => 1178.82, 'months' => 1, 'period' => 'monthly', 'paid_at' => now()->subDays(15)]);

        $quote = app(GymBilling::class)->quote($gym, $this->plan('pro'), 'monthly');

        // 15 of 30 days left on Basic (₹999/month) = ₹499.50 credit.
        $this->assertSame(499.5, $quote['credit']);
        $this->assertSame(round((1999 - 499.5) * 1.18, 2), $quote['total']);
        $this->assertTrue($quote['change']);
        $this->assertSame(now()->addMonthNoOverflow()->toDateString(), $quote['ends_at']->toDateString());

        // Renewing the same plan gives no credit and continues from the end date.
        $renew = app(GymBilling::class)->quote($gym, $this->plan('basic'), 'monthly');
        $this->assertSame(0.0, $renew['credit']);
        $this->assertSame(now()->addDays(15)->toDateString(), $renew['starts_at']->toDateString());
    }

    public function test_discount_codes(): void
    {
        $gym = $this->createGym();
        $billing = app(GymBilling::class);
        SaasCoupon::create(['code' => 'launch20', 'discount_type' => 'percent', 'value' => 20]);
        SaasCoupon::create(['code' => 'OLD', 'discount_type' => 'percent', 'value' => 10, 'valid_until' => now()->subDay()]);
        SaasCoupon::create(['code' => 'PROONLY', 'discount_type' => 'percent', 'value' => 10, 'plans' => ['pro']]);
        SaasCoupon::create(['code' => 'AUD50', 'discount_type' => 'fixed', 'value' => 50, 'currency' => 'AUD']);

        $quote = $billing->quote($gym, $this->plan('basic'), 'yearly', 'Launch20');
        $this->assertSame(1998.0, $quote['discount']);
        $this->assertSame(round(7992 * 1.18, 2), $quote['total']);

        $this->assertSame('This code has expired.', $billing->quote($gym, $this->plan('basic'), 'yearly', 'old')['coupon_error']);
        $this->assertStringContainsString("doesn't apply to the Basic plan", $billing->quote($gym, $this->plan('basic'), 'yearly', 'PROONLY')['coupon_error']);
        $this->assertStringContainsString("can't be used in INR", $billing->quote($gym, $this->plan('basic'), 'yearly', 'AUD50')['coupon_error']);
        $this->assertSame('That code is not valid.', $billing->quote($gym, $this->plan('basic'), 'yearly', 'NOPE')['coupon_error']);
        $this->assertSame(50.0, $billing->quote($this->auGym(), $this->plan('basic'), 'yearly', 'aud50')['discount']);
    }

    public function test_a_fully_discounted_plan_is_activated_without_payment(): void
    {
        $gym = $this->createGym();
        $coupon = SaasCoupon::create(['code' => 'FREEYEAR', 'discount_type' => 'percent', 'value' => 100, 'max_redemptions' => 1]);

        $this->assertNull(app(BillingCheckout::class)->payNow($gym, $this->plan('pro'), 'yearly', 'FREEYEAR'));

        $this->assertSame('active', $gym->fresh()->status);
        $this->assertSame(1, $coupon->fresh()->redemptions);
        $this->assertSame('This code has been fully used.', $coupon->fresh()->problemFor($this->createGym(), $this->plan('pro')));
    }

    public function test_grace_period_before_changes_lock(): void
    {
        $gym = $this->createGym(['status' => 'active', 'subscription_ends_at' => now()->subDays(2)]);
        $this->assertTrue($gym->hasActiveSaasSubscription());
        $this->assertTrue($gym->inGracePeriod());

        $gym->update(['subscription_ends_at' => now()->subDays(4)]);
        $this->assertFalse($gym->fresh()->hasActiveSaasSubscription());
    }

    public function test_renewal_reminders_are_emailed(): void
    {
        config(['gym.billing.reminder_days' => [7, 1]]);
        $this->createGym(['status' => 'active', 'subscription_ends_at' => now()->addDays(7)]);
        $this->createGym(['status' => 'trial', 'trial_ends_at' => now()->addDay()]);
        $this->createGym(['status' => 'active', 'subscription_ends_at' => now()->addDays(5)]);
        $this->createGym(['status' => 'active', 'subscription_ends_at' => now()->subDays(2)]); // last grace day

        $this->artisan('gym:billing-reminders')->expectsOutput('Sent 3 billing reminders.')->assertSuccessful();

        $subjects = collect($this->sentMail())->map(fn ($m) => $m->getOriginalMessage()->getSubject());
        $this->assertContains('Your Basic plan ends in 7 days', $subjects);
        $this->assertContains('Your free trial ends in 1 day', $subjects);
        $this->assertContains('Last day before your account is locked', $subjects);
    }

    public function test_only_the_owner_sees_plan_and_billing(): void
    {
        $gym = $this->createGym(['status' => 'trial', 'trial_ends_at' => now()->addDays(10)]);
        $this->actingAs($this->ownerOf($gym));
        Filament::setCurrentPanel('gym');
        Filament::setTenant($gym);
        Livewire::test(Billing::class)->assertOk()->assertSee('Free trial')->assertSee('Pro')->assertSee('Choose');

        $this->actingAs($this->addStaff($gym, StaffRole::Receptionist));
        $this->get(Billing::getUrl(tenant: $gym))->assertForbidden();
    }
}
