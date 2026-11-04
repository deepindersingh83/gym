<?php

namespace Tests\Feature;

use App\Enums\MessageEvent;
use App\Models\Broadcast;
use App\Models\MessageLog;
use App\Services\Messaging\Messenger;
use App\Services\SubscriptionService;
use App\Support\RenewLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WinBackRemindersTest extends TestCase
{
    use RefreshDatabase;

    public function test_lapsed_members_get_one_win_back_offer_with_a_renew_link(): void
    {
        $gym = $this->createGym(['settings' => ['channels' => ['email'], 'win_back_enabled' => true, 'win_back_after_days' => 30, 'win_back_offer' => '20% off with code COMEBACK']]);
        $plan = $this->createPlan($gym, ['price' => 1000, 'duration_value' => 1, 'duration_unit' => 'month']);
        $lapsed = $this->createMember($gym, ['email' => 'lapsed@example.com']);
        $optedOut = $this->createMember($gym, ['email' => 'no@example.com', 'marketing_opt_out' => true]);

        foreach ([$lapsed, $optedOut] as $member) {
            $s = app(SubscriptionService::class)->create($member, $plan, today()->subMonths(2));
            $s->update(['end_date' => today()->subDays(30)]);
        }

        $this->artisan('gym:send-reminders')->assertSuccessful();
        $this->artisan('gym:send-reminders')->assertSuccessful(); // not sent twice

        $logs = MessageLog::withoutGlobalScopes()->where('event', MessageEvent::WinBack)->get();
        $this->assertCount(1, $logs);
        $this->assertSame($lapsed->id, $logs[0]->member_id);
        $this->assertStringContainsString('20% off with code COMEBACK', $logs[0]->body);
        $this->assertStringContainsString(RenewLink::for($lapsed), $logs[0]->body);
    }

    public function test_renew_link_goes_to_payment_or_a_contact_page(): void
    {
        $gym = $this->createGym(['phone' => '9876543210']);
        $member = $this->createMember($gym, ['name' => 'Asha Rao']);
        app(SubscriptionService::class)->create($member, $this->createPlan($gym, ['name' => 'Monthly']));

        $this->get(RenewLink::for($member))->assertOk()->assertSee('Renew at Iron Temple')->assertSee('wa.me/919876543210');
        $this->get('/r/'.base_convert((string) $member->id, 10, 36).'-0000000000')->assertNotFound();

        // With Razorpay set up and online payments in the plan, it opens the payment page.
        Http::fake(['api.razorpay.com/*' => Http::response(['short_url' => 'https://rzp.io/renew'])]);
        $gym->update(['integrations' => ['razorpay_key_id' => 'rzp_k', 'razorpay_key_secret' => 's']]);
        $this->get(RenewLink::for($member))->assertRedirect('https://rzp.io/renew');
    }

    public function test_opted_out_and_erased_members_are_left_out_of_broadcasts(): void
    {
        $gym = $this->createGym();
        $this->createMember($gym);
        $this->createMember($gym, ['marketing_opt_out' => true]);
        $this->createMember($gym, ['erased_at' => now()]);

        $broadcast = new Broadcast(['gym_id' => $gym->id, 'audience' => 'all']);
        $this->assertSame(1, app(Messenger::class)->broadcastAudience($broadcast)->count());
    }
}
