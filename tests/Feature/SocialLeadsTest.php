<?php

namespace Tests\Feature;

use App\Enums\LeadSource;
use App\Filament\Gym\Pages\Tenancy\EditGymProfile;
use App\Models\Gym;
use App\Models\Lead;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class SocialLeadsTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected function setUp(): void
    {
        parent::setUp();

        config(['gym.meta.app_secret' => 'app-secret', 'gym.meta.verify_token' => 'verify-me']);
        $this->gym = $this->createGym(['facebook_page_id' => '1122334455', 'integrations' => ['facebook_page_token' => 'PAGE_TOKEN']]);
    }

    protected function leadgenPayload(string $pageId = '1122334455', string $leadgenId = '9001'): array
    {
        return ['object' => 'page', 'entry' => [['id' => $pageId, 'changes' => [[
            'field' => 'leadgen',
            'value' => ['page_id' => $pageId, 'leadgen_id' => $leadgenId, 'form_id' => '77'],
        ]]]]];
    }

    protected function postSigned(array $payload, string $secret = 'app-secret')
    {
        $body = json_encode($payload);

        return $this->call('POST', route('webhooks.meta'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, $secret),
        ], $body);
    }

    public function test_meta_webhook_verification_handshake(): void
    {
        $this->get(route('webhooks.meta.verify', ['hub_mode' => 'subscribe', 'hub_verify_token' => 'verify-me', 'hub_challenge' => '12345']))
            ->assertOk()->assertSeeText('12345');
        $this->get(route('webhooks.meta.verify', ['hub_mode' => 'subscribe', 'hub_verify_token' => 'wrong', 'hub_challenge' => '1']))
            ->assertForbidden();
    }

    public function test_instagram_lead_form_becomes_an_enquiry_once(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response([
            'id' => '9001',
            'platform' => 'ig',
            'ad_name' => 'Diwali offer',
            'field_data' => [
                ['name' => 'full_name', 'values' => ['Sneha Kulkarni']],
                ['name' => 'phone_number', 'values' => ['+919822012345']],
                ['name' => 'email', 'values' => ['sneha@example.com']],
                ['name' => 'preferred_time', 'values' => ['Evening']],
            ],
        ])]);

        $this->postSigned($this->leadgenPayload())->assertOk();
        $this->postSigned($this->leadgenPayload())->assertOk(); // Meta can deliver twice

        $lead = Lead::withoutGlobalScopes()->sole();
        $this->assertSame('Sneha Kulkarni', $lead->name);
        $this->assertSame('+919822012345', $lead->phone);
        $this->assertSame(LeadSource::Instagram, $lead->source);
        $this->assertSame('Diwali offer', $lead->interest);
        $this->assertStringContainsString('Preferred time: Evening', $lead->notes);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/9001') && $request['access_token'] === 'PAGE_TOKEN');
        $this->assertSame(1, $this->ownerOf($this->gym)->notifications()->count());
    }

    public function test_unsigned_or_unknown_page_webhooks_are_ignored(): void
    {
        Http::fake();

        $this->postSigned($this->leadgenPayload(), 'wrong-secret')->assertUnauthorized();
        $this->postSigned($this->leadgenPayload('999'))->assertOk();

        Http::assertNothingSent();
        $this->assertSame(0, Lead::withoutGlobalScopes()->count());
    }

    public function test_generic_webhook_accepts_leads_from_zapier_or_make(): void
    {
        $this->gym->forceFill(['lead_webhook_token' => 'tok_abc123'])->save();

        $this->postJson(route('webhooks.leads', 'tok_abc123'), ['full_name' => 'Arjun', 'phone_number' => '9811100099', 'source' => 'facebook', 'message' => 'Call after 6'])
            ->assertCreated();
        $this->postJson(route('webhooks.leads', 'tok_abc123'), ['name' => 'No contact'])->assertUnprocessable();
        $this->postJson(route('webhooks.leads', 'bad-token'), ['name' => 'X', 'phone' => '1'])->assertNotFound();

        $lead = Lead::withoutGlobalScopes()->sole();
        $this->assertSame(LeadSource::Facebook, $lead->source);
        $this->assertSame('Call after 6', $lead->notes);
    }

    public function test_owner_creates_the_webhook_address_and_connects_the_page(): void
    {
        Http::fake(['graph.facebook.com/*/subscribed_apps' => Http::response(['success' => true])]);
        $this->actingAs($this->ownerOf($this->gym));
        Filament::setCurrentPanel('gym');
        Filament::setTenant($this->gym);

        Livewire::test(EditGymProfile::class)
            ->callAction(TestAction::make('leadWebhook')->schemaComponent('leadWebhook', 'form'))
            ->assertNotified()
            ->callAction(TestAction::make('connectFacebook')->schemaComponent('facebookLeads', 'form'))
            ->assertNotified('Facebook page connected');

        $this->assertNotNull($this->gym->fresh()->lead_webhook_token);
        Http::assertSent(fn ($request) => str_contains($request->url(), '1122334455/subscribed_apps') && $request['subscribed_fields'] === 'leadgen');
    }
}
