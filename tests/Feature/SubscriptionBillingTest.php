<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\SubscriptionStatus;
use App\Models\Coupon;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SubscriptionBillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_gst_is_split_into_cgst_and_sgst_when_the_gym_has_a_gstin(): void
    {
        $gym = $this->createGym(['gstin' => '27ABCDE1234F1Z5']);
        $plan = $this->createPlan($gym, ['price' => 1000, 'admission_fee' => 500]);

        $quote = app(SubscriptionService::class)->quote($gym, $plan);

        $this->assertSame(1500.0, $quote['taxable_amount']);
        $this->assertSame(135.0, $quote['cgst_amount']);
        $this->assertSame(135.0, $quote['sgst_amount']);
        $this->assertSame(1770.0, $quote['total_amount']);
    }

    public function test_no_gst_is_charged_without_a_gstin_and_coupons_discount_the_plan_price(): void
    {
        $gym = $this->createGym();
        $plan = $this->createPlan($gym, ['price' => 2000, 'admission_fee' => 300]);
        $coupon = Coupon::withoutGlobalScopes()->create(['gym_id' => $gym->id, 'code' => 'off10', 'discount_type' => 'percent', 'value' => 10]);

        $quote = app(SubscriptionService::class)->quote($gym, $plan, $coupon);

        $this->assertSame('OFF10', $coupon->code);
        $this->assertSame(200.0, $quote['discount_amount']);
        $this->assertSame(0.0, $quote['cgst_amount']);
        $this->assertSame(2100.0, $quote['total_amount']);
    }

    public function test_end_dates_follow_the_plan_duration(): void
    {
        $gym = $this->createGym();
        $start = Carbon::parse('2026-01-15');

        $this->assertSame('2026-02-14', $this->createPlan($gym)->endDateFrom($start)->toDateString());
        $this->assertSame('2026-04-14', $this->createPlan($gym, ['duration_value' => 3])->endDateFrom($start)->toDateString());
        $this->assertSame('2027-01-14', $this->createPlan($gym, ['duration_value' => 12])->endDateFrom($start)->toDateString());
        $this->assertSame('2026-01-15', $this->createPlan($gym, ['duration_value' => 1, 'duration_unit' => 'day'])->endDateFrom($start)->toDateString());

        // Month ends never overflow into the following month.
        $this->assertSame('2026-02-27', $this->createPlan($gym)->endDateFrom(Carbon::parse('2026-01-31'))->toDateString());
    }

    public function test_part_payments_track_the_balance_due_and_invoice_numbers_are_sequential(): void
    {
        $gym = $this->createGym();
        $member = $this->createMember($gym);
        $service = app(SubscriptionService::class);

        $first = $service->create($member, $this->createPlan($gym, ['price' => 3000]), paidAmount: 1000, paymentMethod: PaymentMethod::Cash);
        $this->assertSame(2000.0, $first->balance_due);

        $payment = $service->recordPayment($first, 1500, PaymentMethod::GPay, 'UPI123');
        $this->assertSame(500.0, $first->fresh()->balance_due);
        $this->assertNotNull($payment->receipt_number);

        $payment->delete();
        $this->assertSame(2000.0, $first->fresh()->balance_due);

        $second = $service->create($this->createMember($gym), $this->createPlan($gym));
        $this->assertNotSame($first->invoice_number, $second->invoice_number);
        $this->assertStringEndsWith('00002', $second->invoice_number);
    }

    public function test_renewal_starts_the_day_after_the_current_membership_and_skips_the_admission_fee(): void
    {
        $gym = $this->createGym();
        $member = $this->createMember($gym);
        $plan = $this->createPlan($gym, ['admission_fee' => 500]);
        $service = app(SubscriptionService::class);

        $current = $service->create($member, $plan, today()->subDays(10));
        $this->assertSame(500.0, (float) $current->admission_fee);

        $renewed = $service->renew($current);

        $this->assertTrue($renewed->start_date->equalTo($current->end_date->copy()->addDay()));
        $this->assertSame(0.0, (float) $renewed->admission_fee);
        $this->assertSame($current->id, $renewed->renewed_from_id);
    }

    public function test_lapsed_memberships_are_marked_expired(): void
    {
        $gym = $this->createGym();
        $service = app(SubscriptionService::class);
        $old = $service->create($this->createMember($gym), $this->createPlan($gym), today()->subMonths(2));
        $current = $service->create($this->createMember($gym), $this->createPlan($gym));

        $this->assertSame(1, $service->expireLapsed());
        $this->assertSame(SubscriptionStatus::Expired, $old->fresh()->status);
        $this->assertSame(SubscriptionStatus::Active, $current->fresh()->status);
    }

    public function test_couple_plans_cover_additional_members(): void
    {
        $gym = $this->createGym();
        $plan = $this->createPlan($gym, ['max_persons' => 2]);
        $partner = $this->createMember($gym);
        $other = $this->createMember($gym);

        $subscription = app(SubscriptionService::class)->create($this->createMember($gym), $plan, additionalMemberIds: [$partner->id, $other->id]);

        $this->assertSame([$partner->id], $subscription->additionalMembers()->pluck('members.id')->all());
    }
}
