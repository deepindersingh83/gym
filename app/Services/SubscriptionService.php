<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\SubscriptionStatus;
use App\Models\Coupon;
use App\Models\Gym;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SubscriptionService
{
    /**
     * Price breakdown for a plan: discount on the plan price, then GST (split
     * equally into CGST and SGST) on plan price less discount plus admission fee.
     *
     * @return array<string, float>
     */
    public function quote(Gym $gym, MembershipPlan $plan, ?Coupon $coupon = null, bool $withAdmissionFee = true): array
    {
        $price = (float) $plan->price;
        $admission = $withAdmissionFee ? (float) $plan->admission_fee : 0.0;
        $discount = $coupon?->isUsable() ? $coupon->discountFor($price) : 0.0;
        $taxable = round($price - $discount + $admission, 2);
        $rate = $gym->isGstRegistered() ? (float) $plan->gst_rate : 0.0;
        $halfTax = round($taxable * $rate / 200, 2);

        return [
            'plan_price' => round($price, 2),
            'admission_fee' => round($admission, 2),
            'discount_amount' => $discount,
            'taxable_amount' => $taxable,
            'gst_rate' => $rate,
            'cgst_amount' => $halfTax,
            'sgst_amount' => $halfTax,
            'total_amount' => round($taxable + 2 * $halfTax, 2),
        ];
    }

    /**
     * Sells a plan to a member, optionally recording the first payment.
     *
     * @param  array<int>  $additionalMemberIds  Other people on a couple / family plan.
     */
    public function create(
        Member $member,
        MembershipPlan $plan,
        ?Carbon $startDate = null,
        ?Coupon $coupon = null,
        ?float $paidAmount = null,
        PaymentMethod|string|null $paymentMethod = null,
        array $additionalMemberIds = [],
        array $attributes = [],
    ): Subscription {
        if ($plan->gym_id !== $member->gym_id || ($coupon && $coupon->gym_id !== $member->gym_id)) {
            throw new InvalidArgumentException('Plan, coupon and member must belong to the same gym.');
        }

        return DB::transaction(function () use ($member, $plan, $startDate, $coupon, $paidAmount, $paymentMethod, $additionalMemberIds, $attributes) {
            $gym = $member->gym;
            $start = ($startDate ?? today())->copy()->startOfDay();
            $isFirst = ! $member->subscriptions()->withoutGlobalScopes()->where('status', '!=', SubscriptionStatus::Cancelled)->exists();

            $subscription = Subscription::create([
                'gym_id' => $member->gym_id,
                'branch_id' => $member->branch_id,
                'member_id' => $member->getKey(),
                'membership_plan_id' => $plan->getKey(),
                'coupon_id' => $coupon?->isUsable() ? $coupon->getKey() : null,
                'created_by' => auth()->id(),
                'start_date' => $start,
                'end_date' => $plan->endDateFrom($start),
                'pt_sessions_total' => $plan->pt_sessions,
                'status' => SubscriptionStatus::Active,
                ...$this->quote($gym, $plan, $coupon, $isFirst),
                ...$attributes,
            ]);

            $extra = collect($additionalMemberIds)->reject(fn ($id) => (int) $id === $member->getKey())
                ->take(max(0, $plan->max_persons - 1));
            $subscription->additionalMembers()->sync(
                Member::withoutGlobalScopes()->where('gym_id', $member->gym_id)->whereIn('id', $extra)->pluck('id')
            );

            if ($paidAmount !== null && $paidAmount > 0) {
                $this->recordPayment($subscription, $paidAmount, $paymentMethod ?? PaymentMethod::Cash);
            }

            return $subscription->refresh();
        });
    }

    /** Renews from the day after the current membership ends (or today if it has already lapsed). */
    public function renew(
        Subscription $previous,
        ?MembershipPlan $plan = null,
        ?Coupon $coupon = null,
        ?float $paidAmount = null,
        PaymentMethod|string|null $paymentMethod = null,
        array $attributes = [],
    ): Subscription {
        $plan ??= $previous->plan;
        $start = $previous->end_date->copy()->addDay()->max(today());

        return $this->create(
            $previous->member,
            $plan,
            $start,
            $coupon,
            $paidAmount,
            $paymentMethod,
            $previous->additionalMembers()->pluck('members.id')->all(),
            ['renewed_from_id' => $previous->getKey(), 'auto_renew' => $previous->auto_renew, ...$attributes],
        );
    }

    public function recordPayment(
        Subscription $subscription,
        float $amount,
        PaymentMethod|string $method,
        ?string $reference = null,
        array $attributes = [],
    ): Payment {
        return Payment::create([
            'gym_id' => $subscription->gym_id,
            'branch_id' => $subscription->branch_id,
            'member_id' => $subscription->member_id,
            'subscription_id' => $subscription->getKey(),
            'amount' => round($amount, 2),
            'method' => $method instanceof PaymentMethod ? $method : PaymentMethod::from($method),
            'reference' => $reference,
            ...$attributes,
        ]);
    }

    /** Marks memberships whose end date has passed as expired. Returns how many changed. */
    public function expireLapsed(): int
    {
        return Subscription::withoutGlobalScopes()
            ->where('status', SubscriptionStatus::Active)
            ->whereDate('end_date', '<', today())
            ->update(['status' => SubscriptionStatus::Expired, 'updated_at' => now()]);
    }
}
