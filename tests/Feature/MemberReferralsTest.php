<?php

namespace Tests\Feature;

use App\Enums\MessageEvent;
use App\Filament\Gym\Pages\Referrals as ReferralsPage;
use App\Filament\Member\Pages\ReferFriend;
use App\Models\Member;
use App\Models\MemberReferral;
use App\Models\MessageLog;
use App\Services\Referrals;
use App\Services\SubscriptionService;
use App\Support\CurrentMember;
use App\Support\Tenancy;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MemberReferralsTest extends TestCase
{
    use RefreshDatabase;

    public function test_friend_joining_with_the_link_rewards_both_on_first_payment(): void
    {
        $gym = $this->createGym(['settings' => ['allow_online_signup' => true, 'channels' => ['email'], 'referral_referrer_days' => 15, 'referral_friend_days' => 7]]);
        $plan = $this->createPlan($gym, ['price' => 1000]);
        $asha = $this->createMember($gym, ['name' => 'Asha Rao', 'email' => 'asha@example.com']);
        $service = app(SubscriptionService::class);
        $ashaMembership = $service->create($asha, $plan);
        $ashaEnd = $ashaMembership->end_date->copy();

        $link = app(Referrals::class)->linkFor($asha);
        $code = $asha->fresh()->referral_code;
        $this->assertStringStartsWith('ASHA', $code);
        $this->assertStringContainsString('ref='.$code, $link);

        $this->get($link)->assertOk()->assertSee('Asha')->assertSee('7 free days');
        $this->post(route('join.store', $gym), [
            'name' => 'Neha Kapoor', 'phone' => '9812345678', 'password' => 'secret-pass', 'consent' => '1', 'ref' => $code,
        ])->assertRedirect();

        $neha = Member::withoutGlobalScopes()->where('name', 'Neha Kapoor')->sole();
        $this->assertSame($asha->id, $neha->referred_by_member_id);
        $this->assertSame('referral', $neha->source);
        $referral = MemberReferral::withoutGlobalScopes()->sole();
        $this->assertSame('pending', $referral->status);

        // First payment: Asha +15 days, Neha +7 days on her new membership.
        Tenancy::setGym($gym);
        $nehaMembership = $service->create($neha->fresh(), $plan);
        $nehaEnd = $nehaMembership->end_date->copy();
        $service->recordPayment($nehaMembership, 1000, 'upi');

        $this->assertSame('rewarded', $referral->fresh()->status);
        $this->assertSame($ashaEnd->addDays(15)->toDateString(), $ashaMembership->fresh()->end_date->toDateString());
        $this->assertSame($nehaEnd->addDays(7)->toDateString(), $nehaMembership->fresh()->end_date->toDateString());
        $this->assertSame(1, MessageLog::withoutGlobalScopes()->where('event', MessageEvent::ReferralReward)->where('member_id', $asha->id)->count());

        // A second payment does not reward again.
        $service->recordPayment($nehaMembership, 1, 'cash');
        $this->assertSame($ashaEnd->toDateString(), $ashaMembership->fresh()->end_date->toDateString());
    }

    public function test_days_are_saved_when_the_referrer_has_no_membership(): void
    {
        $gym = $this->createGym();
        Tenancy::setGym($gym);
        $plan = $this->createPlan($gym, ['price' => 500]);
        $referrer = $this->createMember($gym);
        $friend = $this->createMember($gym, ['referred_by_member_id' => $referrer->id]);

        $service = app(SubscriptionService::class);
        $service->recordPayment($service->create($friend, $plan), 500, 'cash');
        $this->assertSame(15, $referrer->fresh()->bonus_days);

        $next = $service->create($referrer->fresh(), $plan);
        $this->assertSame($plan->endDateFrom(today())->addDays(15)->toDateString(), $next->end_date->toDateString());
        $this->assertSame(0, $referrer->fresh()->bonus_days);
    }

    public function test_member_app_page_and_gym_list(): void
    {
        $gym = $this->createGym();
        $member = $this->createMember($gym, ['name' => 'Ravi Kumar']);
        $friend = $this->createMember($gym, ['name' => 'Sam Lee', 'referred_by_member_id' => $member->id]);

        $this->actingAs($this->ownerOf($gym));
        Filament::setCurrentPanel('gym');
        Filament::setTenant($gym);
        Livewire::test(ReferralsPage::class)->assertCanSeeTableRecords(MemberReferral::withoutGlobalScopes()->get())->assertSee('Sam Lee');

        Filament::setCurrentPanel('member');
        CurrentMember::set($member);
        Livewire::test(ReferFriend::class)->assertOk()->assertSee('Bring a friend')->assertSee('Share on WhatsApp')->assertSee('Sam')->assertSee('Waiting for first payment');

        $gym->update(['settings' => ['referral_enabled' => false]]);
        CurrentMember::set($member->fresh());
        $this->assertFalse(ReferFriend::canAccess());
        $this->assertSame(1, MemberReferral::withoutGlobalScopes()->count()); // existing ones are kept
        $this->assertSame($friend->id, MemberReferral::withoutGlobalScopes()->value('referred_id'));
    }
}
