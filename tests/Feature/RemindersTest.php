<?php

namespace Tests\Feature;

use App\Enums\MessageChannel;
use App\Enums\MessageEvent;
use App\Enums\PaymentMethod;
use App\Models\Broadcast;
use App\Models\MessageLog;
use App\Models\MessageTemplate;
use App\Services\Messaging\Messenger;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RemindersTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_job_sends_each_reminder_once(): void
    {
        $gym = $this->createGym(['settings' => ['channels' => ['whatsapp'], 'reminder_days_before' => [3]]]);
        $service = app(SubscriptionService::class);
        $plan = $this->createPlan($gym, ['duration_value' => 1, 'duration_unit' => 'day']);

        $renewing = $this->createMember($gym, ['name' => 'Renew Me']);
        $service->create($renewing, $plan, today()->addDays(3), paidAmount: 10000, paymentMethod: PaymentMethod::Cash);

        $lapsed = $this->createMember($gym, ['name' => 'Lapsed']);
        $service->create($lapsed, $plan, today()->subDay(), paidAmount: 10000, paymentMethod: PaymentMethod::Cash);

        $owing = $this->createMember($gym, ['name' => 'Owes Money']);
        $service->create($owing, $this->createPlan($gym), paidAmount: 100, paymentMethod: PaymentMethod::Cash);

        $this->createMember($gym, ['name' => 'Birthday', 'date_of_birth' => today()->subYears(30)]);

        $this->artisan('gym:send-reminders')->assertSuccessful();
        $this->artisan('gym:send-reminders')->assertSuccessful();

        $events = MessageLog::withoutGlobalScopes()->with('member')->get()
            ->map(fn ($log) => $log->event->value.':'.$log->member->name)->sort()->values()->all();

        $this->assertSame([
            'birthday:Birthday',
            'dues_reminder:Owes Money',
            'expired:Lapsed',
            'renewal_reminder:Renew Me',
        ], $events);
        $this->assertSame(['logged'], MessageLog::withoutGlobalScopes()->distinct()->pluck('status')->all());
    }

    public function test_messages_use_the_gyms_template_and_whatsapp_cloud_api(): void
    {
        config(['gym.whatsapp.token' => 'token', 'gym.whatsapp.phone_number_id' => '123']);
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid']]])]);

        $gym = $this->createGym(['name' => 'Iron Temple']);
        $member = $this->createMember($gym, ['name' => 'Asha', 'phone' => '98765 43210']);
        MessageTemplate::withoutGlobalScopes()->create([
            'gym_id' => $gym->id, 'event' => MessageEvent::DuesReminder, 'channel' => 'whatsapp',
            'body' => 'Namaste {name}, please clear {due} at {gym}.', 'provider_template' => 'dues_v1',
        ]);
        app(SubscriptionService::class)->create($member, $this->createPlan($gym, ['price' => 500, 'gst_rate' => 0]));

        $sent = app(Messenger::class)->send($member, MessageEvent::DuesReminder, channels: [MessageChannel::WhatsApp]);

        $this->assertSame(1, $sent);
        Http::assertSent(fn ($request) => $request['to'] === '919876543210'
            && $request['template']['name'] === 'dues_v1'
            && $request['template']['components'][0]['parameters'][0]['text'] === 'Asha'
            && $request['template']['components'][0]['parameters'][1]['text'] === '500.00');
        $this->assertSame('Namaste Asha, please clear 500.00 at Iron Temple.', MessageLog::withoutGlobalScopes()->value('body'));
    }

    public function test_broadcast_reaches_only_the_chosen_audience(): void
    {
        $gym = $this->createGym();
        $service = app(SubscriptionService::class);
        $service->create($this->createMember($gym, ['name' => 'Active']), $this->createPlan($gym));
        $service->create($this->createMember($gym, ['name' => 'Expired']), $this->createPlan($gym), today()->subMonths(3));
        $service->expireLapsed();

        $broadcast = Broadcast::withoutGlobalScopes()->create([
            'gym_id' => $gym->id, 'title' => 'Diwali hours', 'body' => 'Hi {name}, we close at 2 PM on Diwali.',
            'channels' => ['whatsapp'], 'audience' => 'active',
        ]);

        $this->assertSame(1, app(Messenger::class)->sendBroadcast($broadcast));
        $this->assertSame('Hi Active, we close at 2 PM on Diwali.', MessageLog::withoutGlobalScopes()->value('body'));
        $this->assertNotNull($broadcast->fresh()->sent_at);
    }
}
