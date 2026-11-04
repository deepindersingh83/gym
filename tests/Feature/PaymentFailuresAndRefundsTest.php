<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\SaasPayments\Pages\ListSaasPayments;
use App\Jobs\SendPlatformEmail;
use App\Models\Partner;
use App\Models\SaasPayment;
use App\Models\SaasPlan;
use App\Models\User;
use App\Services\Billing\BillingCheckout;
use App\Services\Billing\GymBilling;
use App\Services\Billing\Refunds;
use App\Services\Billing\RevenueReport;
use Database\Seeders\SaasPlanSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class PaymentFailuresAndRefundsTest extends TestCase
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
            'gym.billing.entities.IN' => ['legal_name' => 'GymFlow India', 'address' => 'Bengaluru', 'tax_id' => '29ABCDE1234F1Z5', 'state_code' => null, 'tax_rate' => 18, 'invoice_prefix' => 'GF-IN'],
            'gym.billing.entities.AU' => ['legal_name' => 'GymFlow Pty Ltd', 'address' => 'Sydney', 'tax_id' => '51824753556', 'state_code' => null, 'tax_rate' => 10, 'invoice_prefix' => 'GF-AU'],
            'gym.platform_razorpay' => ['key_id' => 'rzp_test', 'key_secret' => 'secret', 'webhook_secret' => 'rzp_hook'],
            'gym.stripe' => ['secret_key' => 'sk_test', 'webhook_secret' => 'whsec_test'],
        ]);
    }

    protected function plan(string $slug): SaasPlan
    {
        return SaasPlan::query()->where('slug', $slug)->firstOrFail();
    }

    protected function razorpayHook(array $event)
    {
        $payload = json_encode($event);

        return $this->call('POST', route('webhooks.platform'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_RAZORPAY_SIGNATURE' => hash_hmac('sha256', $payload, 'rzp_hook'),
        ], $payload);
    }

    protected function stripeHook(array $event)
    {
        $payload = json_encode($event);
        $t = time();

        return $this->call('POST', route('webhooks.stripe'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => "t={$t},v1=".hash_hmac('sha256', "{$t}.{$payload}", 'whsec_test'),
        ], $payload);
    }

    protected function subjects(): array
    {
        return collect(app('mailer')->getSymfonyTransport()->messages()->all())->map(fn ($m) => $m->getOriginalMessage()->getSubject())->all();
    }

    public function test_platform_emails_go_through_the_queue(): void
    {
        Queue::fake();
        $gym = $this->createGym();

        app(GymBilling::class)->recordPlanPayment($gym, $this->plan('basic'), 'monthly', 1178.82, ['gateway' => 'manual']);

        Queue::assertPushed(SendPlatformEmail::class, fn (SendPlatformEmail $job) => $job->to === $this->ownerOf($gym)->email && $job->attach['type'] === 'invoice');
        $this->assertSame(4, (new SendPlatformEmail('a@b.c', 's', 'b'))->tries);
    }

    public function test_razorpay_failed_auto_renew_warns_the_owner_then_stops_after_retries(): void
    {
        $gym = $this->createGym(['status' => 'active', 'saas_plan_id' => $this->plan('basic')->id, 'subscription_ends_at' => now()->addDay(),
            'auto_renew' => true, 'billing_gateway' => 'razorpay', 'billing_subscription_id' => 'sub_F1']);

        $pending = ['event' => 'subscription.pending', 'payload' => [
            'subscription' => ['entity' => ['id' => 'sub_F1']],
            'payment' => ['entity' => ['error_description' => 'Insufficient funds.']],
        ]];
        $this->razorpayHook($pending)->assertOk();
        $this->razorpayHook($pending)->assertOk(); // a second retry failing does not email again

        $gym->refresh();
        $this->assertNotNull($gym->payment_failed_at);
        $this->assertSame('Insufficient funds.', $gym->payment_failure_reason);
        $this->assertTrue($gym->auto_renew);
        $this->assertSame(['Your automatic payment failed'], $this->subjects());

        $this->actingAs($this->ownerOf($gym))->get('/app/'.$gym->slug)->assertSee('Your automatic payment failed')->assertSee('Insufficient funds.');

        $this->razorpayHook(['event' => 'subscription.halted', 'payload' => ['subscription' => ['entity' => ['id' => 'sub_F1']]]])->assertOk();
        $this->assertFalse($gym->fresh()->auto_renew);
        $this->assertCount(2, $this->subjects());

        // Paying clears the warning.
        app(GymBilling::class)->recordPlanPayment($gym->fresh(), $this->plan('basic'), 'monthly', 1178.82, ['gateway' => 'manual']);
        $this->assertNull($gym->fresh()->payment_failed_at);
    }

    public function test_stripe_failed_payment_records_next_retry_and_owner_can_update_card(): void
    {
        Http::fake(['api.stripe.com/v1/billing_portal/sessions' => Http::response(['url' => 'https://billing.stripe.com/p/1'])]);
        $gym = $this->createGym(['country' => 'AU', 'status' => 'active', 'saas_plan_id' => $this->plan('basic')->id, 'subscription_ends_at' => now(),
            'auto_renew' => true, 'billing_gateway' => 'stripe', 'billing_subscription_id' => 'sub_S1', 'billing_customer_id' => 'cus_1']);

        $this->stripeHook(['type' => 'invoice.payment_failed', 'data' => ['object' => [
            'id' => 'in_9', 'next_payment_attempt' => now()->addDays(3)->getTimestamp(),
            'parent' => ['subscription_details' => ['subscription' => 'sub_S1']],
        ]]])->assertOk();

        $this->assertSame(now()->addDays(3)->toDateString(), $gym->fresh()->payment_retry_at->toDateString());
        $this->assertSame('https://billing.stripe.com/p/1', app(BillingCheckout::class)->updateCardUrl($gym->fresh()));
        Http::assertSent(fn (Request $r) => $r['customer'] === 'cus_1');
    }

    public function test_full_refund_through_razorpay_issues_a_credit_note_and_shortens_the_plan(): void
    {
        Http::fake(['api.razorpay.com/v1/payments/pay_R/refund' => Http::response(['id' => 'rfnd_1', 'status' => 'processed'])]);
        $partner = Partner::create(['name' => 'Ravi', 'email' => 'ravi@example.com', 'code' => 'RAVI', 'commission_rate' => 20]);
        $gym = $this->createGym(['partner_id' => $partner->id, 'state_code' => '29']);
        $payment = app(GymBilling::class)->recordPlanPayment($gym, $this->plan('basic'), 'monthly', 1178.82, ['gateway' => 'razorpay', 'gateway_payment_id' => 'pay_R']);
        $endsBefore = $gym->fresh()->subscription_ends_at;

        $refund = app(Refunds::class)->refund($payment, 1178.82, 'Charged by mistake', online: true, takeBack: true);

        Http::assertSent(fn (Request $r) => $r['amount'] === 117882);
        $this->assertSame('GF-IN-CN-2627-0001', $refund->credit_note_number);
        $this->assertSame('179.82', $refund->tax_amount);
        $this->assertSame('GF-IN-2627-0001', $refund->credit_note['original_invoice']);
        $this->assertSame('cgst_sgst', $refund->credit_note['tax_split']);
        $this->assertSame(0.0, $payment->fresh()->refundable());
        $this->assertSame('cancelled', $payment->commission->fresh()->status);
        $this->assertTrue($gym->fresh()->subscription_ends_at->lt($endsBefore));
        $this->assertContains('Credit note GF-IN-CN-2627-0001 from '.config('app.name'), $this->subjects());

        $this->actingAs($this->ownerOf($gym))->get(route('saas-credit-notes.show', $refund))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs(User::factory()->create())->get(route('saas-credit-notes.show', $refund))->assertForbidden();

        $this->expectExceptionMessage('at most');
        app(Refunds::class)->refund($payment->fresh(), 1, 'Again', online: false, takeBack: false);
    }

    public function test_partial_manual_refund_from_admin_and_revenue_nets_it(): void
    {
        $gym = $this->createGym();
        $payment = app(GymBilling::class)->recordPlanPayment($gym, $this->plan('pro'), 'yearly', 23588.20, ['gateway' => 'manual']);

        $admin = User::factory()->create();
        $admin->forceFill(['admin_role' => 'finance'])->save();
        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');

        Livewire::test(ListSaasPayments::class)
            ->callTableAction('refund', $payment, ['amount' => 1180, 'reason' => 'Goodwill', 'online' => '0', 'take_back' => false])
            ->assertHasNoTableActionErrors();

        $payment->refresh();
        $this->assertSame('1180.00', $payment->refunded_amount);
        $this->assertSame('manual', $payment->refunds()->sole()->gateway);
        $this->assertSame(round(19990 - 1000, 2), (new RevenueReport('INR'))->revenue(now()->startOfMonth(), now()));
    }

    public function test_refunds_made_in_the_stripe_dashboard_get_a_credit_note_once(): void
    {
        $gym = $this->createGym(['country' => 'AU', 'abn' => '51824753556', 'gst_registered' => true]);
        app(GymBilling::class)->recordPlanPayment($gym, $this->plan('pro'), 'monthly', 59, ['gateway' => 'stripe', 'gateway_payment_id' => 'pi_7']);

        $event = ['type' => 'charge.refunded', 'data' => ['object' => ['payment_intent' => 'pi_7', 'refunds' => ['data' => [['id' => 're_1', 'amount' => 2200]]]]]];
        $this->stripeHook($event)->assertOk();
        $this->stripeHook($event)->assertOk();

        $payment = SaasPayment::query()->sole();
        $this->assertSame('22.00', $payment->refunded_amount);
        $refund = $payment->refunds()->sole();
        $this->assertSame('GF-AU-CN-2627-0001', $refund->credit_note_number);
        $this->assertSame('2.00', $refund->tax_amount);
    }
}
