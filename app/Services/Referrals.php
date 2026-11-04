<?php

namespace App\Services;

use App\Enums\MessageEvent;
use App\Enums\SubscriptionStatus;
use App\Models\Gym;
use App\Models\Member;
use App\Models\MemberReferral;
use App\Models\Payment;
use App\Models\Subscription;
use App\Services\Messaging\Messenger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Member "bring a friend" rewards. The friend joins with the member's link or code; when the
 * friend makes their first payment, both get free days added to their membership.
 */
class Referrals
{
    public function enabled(Gym $gym): bool
    {
        return (bool) $gym->setting('referral_enabled');
    }

    /** The member's own code, created the first time it is needed (e.g. ASHA7K2). */
    public function codeFor(Member $member): string
    {
        if ($member->referral_code) {
            return $member->referral_code;
        }

        $base = Str::upper(Str::limit(preg_replace('/[^A-Za-z]/', '', Str::ascii($member->name)) ?: 'FIT', 4, ''));

        do {
            $code = $base.Str::upper(Str::random(3));
        } while (Member::withoutGlobalScopes()->where('gym_id', $member->gym_id)->where('referral_code', $code)->exists());

        $member->forceFill(['referral_code' => $code])->saveQuietly();

        return $code;
    }

    public function linkFor(Member $member): string
    {
        return route('join.show', ['gym' => $member->gym, 'ref' => $this->codeFor($member)]);
    }

    public function findReferrer(Gym $gym, ?string $code): ?Member
    {
        return blank($code) ? null : Member::withoutGlobalScopes()->where('gym_id', $gym->id)
            ->where('referral_code', Str::upper(trim($code)))->whereNull('erased_at')->first();
    }

    /** Called when a member is created with a referrer: records the pending referral. */
    public function register(Member $friend): void
    {
        $referrer = $friend->referred_by_member_id ? Member::withoutGlobalScopes()->find($friend->referred_by_member_id) : null;

        if (! $referrer || $referrer->id === $friend->id || $referrer->gym_id !== $friend->gym_id || ! $this->enabled($friend->gym)) {
            return;
        }

        MemberReferral::withoutGlobalScopes()->firstOrCreate(['referred_id' => $friend->id], [
            'gym_id' => $friend->gym_id,
            'referrer_id' => $referrer->id,
        ]);
    }

    /** First payment by a referred friend: give both their free days. */
    public function paymentReceived(Payment $payment): void
    {
        $referral = MemberReferral::withoutGlobalScopes()->where('referred_id', $payment->member_id)->where('status', 'pending')->first();

        if (! $referral || (float) $payment->amount <= 0) {
            return;
        }

        $gym = Gym::query()->find($referral->gym_id);
        $referrerDays = max(0, (int) $gym->setting('referral_referrer_days'));
        $friendDays = max(0, (int) $gym->setting('referral_friend_days'));

        DB::transaction(function () use ($referral, $payment, $referrerDays, $friendDays) {
            $referral->update(['status' => 'rewarded', 'referrer_days' => $referrerDays, 'friend_days' => $friendDays, 'payment_id' => $payment->id, 'rewarded_at' => now()]);
            $this->addDays($referral->referrer, $referrerDays);
            $this->addDays($referral->referred, $friendDays, $payment->subscription_id);
        });

        foreach ([[$referral->referrer, $referrerDays, $referral->referred], [$referral->referred, $friendDays, $referral->referrer]] as [$member, $days, $other]) {
            if ($days > 0 && $member) {
                rescue(fn () => app(Messenger::class)->send($member->fresh('gym'), MessageEvent::ReferralReward, [
                    'days' => (string) $days,
                    'friend' => Str::of($other->name)->before(' ')->toString(),
                ], dedupeKey: "referral:{$referral->id}:{$member->id}"), report: true);
            }
        }
    }

    /** Adds free days to the member's current membership, or keeps them for the next one. */
    public function addDays(?Member $member, int $days, ?int $subscriptionId = null): void
    {
        if (! $member || $days <= 0) {
            return;
        }

        $subscription = Subscription::withoutGlobalScopes()->where('member_id', $member->id)
            ->when($subscriptionId, fn ($q) => $q->whereKey($subscriptionId), fn ($q) => $q->where('status', SubscriptionStatus::Active)->whereDate('end_date', '>=', today())->orderByDesc('end_date'))
            ->first();

        if ($subscription) {
            $subscription->update(['end_date' => $subscription->end_date->copy()->addDays($days)]);

            return;
        }

        $member->increment('bonus_days', $days);
    }

    /** Applies days saved for a member who had no membership when they earned them. */
    public function applyBonusDays(Subscription $subscription): void
    {
        try {
            $member = Member::withoutGlobalScopes()->find($subscription->member_id);

            if ($member && $member->bonus_days > 0) {
                $subscription->update(['end_date' => $subscription->end_date->copy()->addDays($member->bonus_days)]);
                $member->forceFill(['bonus_days' => 0])->saveQuietly();
            }
        } catch (Throwable $e) {
            report($e);
        }
    }
}
