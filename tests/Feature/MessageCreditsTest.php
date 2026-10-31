<?php

namespace Tests\Feature;

use App\Enums\MessageChannel;
use App\Enums\MessageEvent;
use App\Filament\Admin\Resources\Gyms\Pages\ListGyms;
use App\Filament\Gym\Pages\MessageCredits;
use App\Models\CreditTransaction;
use App\Models\Gym;
use App\Models\MessageLog;
use App\Models\SaasPayment;
use App\Models\User;
use App\Services\GymProvisioner;
use App\Services\Messaging\Messenger;
use Database\Seeders\SaasPlanSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class MessageCreditsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['gym.whatsapp.token' => 'platform-token', 'gym.whatsapp.phone_number_id' => '123']);
    }

    protected function sendWhatsApp(Gym $gym): int
    {
        return app(Messenger::class)->send($this->createMember($gym), MessageEvent::Birthday, channels: [MessageChannel::WhatsApp]);
    }

    public function test_platform_whatsapp_messages_use_credits(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'w1']]])]);
        $gym = $this->createGym(['message_credits' => 2]);

        $this->assertSame(1, $this->sendWhatsApp($gym));
        $this->assertSame(1, $this->sendWhatsApp($gym));
        $this->assertSame(0, $this->sendWhatsApp($gym));

        $this->assertSame(0, $gym->fresh()->message_credits);
        $this->assertSame(2, CreditTransaction::withoutGlobalScopes()->where('type', 'usage')->count());
        $this->assertNotNull(CreditTransaction::withoutGlobalScopes()->first()->message_log_id);
        $this->assertStringContainsString('Out of message credits', MessageLog::withoutGlobalScopes()->latest('id')->value('error'));
        Http::assertSentCount(2);
        $this->assertSame(1, $this->ownerOf($gym)->notifications()->count(), 'one low-balance alert per day');
    }

    public function test_gyms_using_their_own_whatsapp_account_are_not_charged(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'w1']]])]);
        $gym = $this->createGym(['message_credits' => 0, 'integrations' => ['whatsapp_token' => 'own', 'whatsapp_phone_number_id' => '9']]);

        $this->assertSame(1, $this->sendWhatsApp($gym));
        $this->assertSame(0, $gym->fresh()->message_credits);
        $this->assertSame(0, CreditTransaction::withoutGlobalScopes()->count());
    }

    public function test_failed_deliveries_are_refunded(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Template not approved']], 400)]);
        $gym = $this->createGym(['message_credits' => 100]);

        $this->assertSame(0, $this->sendWhatsApp($gym));

        $this->assertSame(100, $gym->fresh()->message_credits);
        $this->assertSame('failed', MessageLog::withoutGlobalScopes()->value('status'));
    }

    public function test_new_gyms_get_welcome_credits(): void
    {
        $this->seed(SaasPlanSeeder::class);
        $gym = app(GymProvisioner::class)->provision(['name' => 'New Gym', 'phone' => '9000000000'], User::factory()->create());

        $this->assertSame(50, $gym->fresh()->message_credits);
    }

    public function test_credit_packs_are_bought_online_through_the_platform_account(): void
    {
        config(['gym.platform_razorpay' => ['key_id' => 'rzp_platform', 'key_secret' => 'sec', 'webhook_secret' => 'plat-whsec']]);
        Http::fake(['api.razorpay.com/v1/payment_links' => Http::response(['short_url' => 'https://rzp.io/i/credits'])]);
        $gym = $this->createGym(['message_credits' => 10]);
        $this->actingAs($this->ownerOf($gym));
        Filament::setCurrentPanel('gym');
        Filament::setTenant($gym);

        $this->get("/app/{$gym->slug}/message-credits")->assertOk()->assertSee('2,000')->assertSee('₹999');

        Livewire::test(MessageCredits::class)
            ->callAction('buy', arguments: ['credits' => 2000])
            ->assertRedirect('https://rzp.io/i/credits');
        Http::assertSent(fn ($r) => $r['amount'] === 99900 && $r['notes']['credits'] === '2000' && $r->hasHeader('Authorization', 'Basic '.base64_encode('rzp_platform:sec')));

        $event = ['event' => 'payment_link.paid', 'payload' => [
            'payment' => ['entity' => ['id' => 'pay_credits_1', 'amount' => 99900]],
            'payment_link' => ['entity' => ['notes' => ['type' => 'credits', 'gym_id' => (string) $gym->id, 'credits' => '2000']]],
        ]];
        $body = json_encode($event);
        foreach ([1, 2] as $attempt) {
            $this->call('POST', route('webhooks.platform'), [], [], [], [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_RAZORPAY_SIGNATURE' => hash_hmac('sha256', $body, 'plat-whsec'),
            ], $body)->assertOk();
        }

        $this->assertSame(2010, $gym->fresh()->message_credits);
        $this->assertSame('credits', SaasPayment::query()->sole()->kind);
    }

    public function test_super_admin_adjusts_a_gyms_credits(): void
    {
        $gym = $this->createGym(['message_credits' => 5]);
        $admin = User::factory()->create();
        $admin->forceFill(['is_super_admin' => true])->save();
        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');

        Livewire::test(ListGyms::class)
            ->callTableAction('credits', $gym, data: ['credits' => 500, 'reason' => 'Festival bonus'])
            ->assertHasNoTableActionErrors();

        $this->assertSame(505, $gym->fresh()->message_credits);
        $this->assertSame('Festival bonus', CreditTransaction::withoutGlobalScopes()->sole()->description);
    }
}
