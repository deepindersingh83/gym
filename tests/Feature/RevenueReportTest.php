<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\Partner;
use App\Models\SaasPlan;
use App\Models\User;
use App\Services\Billing\GymBilling;
use App\Services\Billing\RevenueReport;
use Database\Seeders\SaasPlanSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RevenueReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-10 10:00:00');
        $this->seed(SaasPlanSeeder::class);
        config([
            'gym.billing.entities.IN' => ['tax_id' => '29ABCDE1234F1Z5', 'tax_rate' => 18, 'invoice_prefix' => 'GF-IN'],
            'gym.billing.entities.AU' => ['tax_id' => '51824753556', 'tax_rate' => 10, 'invoice_prefix' => 'GF-AU'],
        ]);
    }

    protected function plan(string $slug): SaasPlan
    {
        return SaasPlan::query()->where('slug', $slug)->firstOrFail();
    }

    public function test_mrr_churn_conversion_and_breakdowns_are_per_currency_and_before_gst(): void
    {
        $billing = app(GymBilling::class);
        $partner = Partner::create(['name' => 'Ravi', 'email' => 'ravi@example.com', 'code' => 'RAVI', 'commission_rate' => 20]);

        // Yearly Pro in India: ₹19,990 + GST, so ₹1,665.83 a month.
        $yearly = $this->createGym(['status' => 'trial', 'trial_ends_at' => now()->subDays(5), 'partner_id' => $partner->id]);
        $billing->recordPlanPayment($yearly, $this->plan('pro'), 'yearly', 23588.20, ['gateway' => 'razorpay', 'list_price' => 19990]);

        // Monthly Basic in India.
        $monthly = $this->createGym(['status' => 'active', 'saas_plan_id' => $this->plan('basic')->id, 'subscription_ends_at' => now()->subDays(1)]);
        $billing->recordPlanPayment($monthly, $this->plan('basic'), 'monthly', 1178.82, ['gateway' => 'razorpay']);

        // Lapsed 10 days ago (past the 3-day grace): churned.
        $this->createGym(['status' => 'active', 'saas_plan_id' => $this->plan('basic')->id, 'subscription_ends_at' => now()->subDays(10)]);

        // Trial that ended without paying.
        $this->createGym(['status' => 'trial', 'trial_ends_at' => now()->subDays(20)]);

        // Australia is reported separately: A$59 incl. GST = A$53.64 a month.
        $au = $this->createGym(['country' => 'AU', 'abn' => '51824753556', 'gst_registered' => true]);
        $billing->recordPlanPayment($au, $this->plan('pro'), 'monthly', 59, ['gateway' => 'stripe']);

        $india = new RevenueReport('INR');
        $this->assertSame(2, $india->payingGyms()->count());
        $this->assertSame(round(19990 / 12 + 999, 2), $india->mrr());
        $this->assertSame(20989.0, $india->revenue(now()->startOfMonth(), now()));
        $this->assertSame(['lost' => 1, 'rate' => 33.3], $india->churn());
        $this->assertSame(['ended' => 2, 'paid' => 1, 'rate' => 50.0], $india->trialConversion());

        $byPlan = $india->byPlan()->keyBy('plan');
        $this->assertSame(19990.0, $byPlan['Pro']['revenue']);
        $this->assertSame(1, $byPlan['Basic']['gyms']);

        $byPartner = $india->byPartner()->first();
        $this->assertSame('Ravi', $byPartner['partner']);
        $this->assertSame(19990.0, $byPartner['revenue']);

        $months = $india->monthly();
        $this->assertSame('Nov 26', end($months['labels']));
        $this->assertSame(20989.0, end($months['plans']));

        $this->assertSame(53.64, (new RevenueReport('AUD'))->mrr());
        $this->assertSame(1, $india->renewalsDue()->count()); // the monthly gym renews in a month
    }

    public function test_only_owner_and_finance_see_revenue(): void
    {
        Filament::setCurrentPanel('admin');
        $finance = User::factory()->create();
        $finance->forceFill(['admin_role' => AdminRole::Finance])->save();
        $sales = User::factory()->create();
        $sales->forceFill(['admin_role' => AdminRole::Sales])->save();

        $this->actingAs($finance)->get('/admin/revenue?currency=AUD')->assertOk()->assertSee('Australia (A$)');
        $this->actingAs($sales)->get('/admin/revenue')->assertForbidden();
    }
}
