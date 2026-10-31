<?php

namespace Tests\Feature;

use App\Filament\Gym\Pages\Tenancy\EditGymProfile;
use App\Models\Gym;
use App\Models\Lead;
use App\Models\User;
use App\Services\CustomDomains;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class CustomDomainTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'https://gym.syberinfo.com.au']);
        $this->gym = $this->createGym(['name' => 'FitZone', 'website' => ['tagline' => 'FitZone own-domain site']]);
        $this->createPlan($this->gym);
    }

    protected function fakeCloudflare(string $status = 'active'): void
    {
        config(['gym.custom_domains.cloudflare_zone_id' => 'zone1', 'gym.custom_domains.cloudflare_api_token' => 'cf-token']);
        Http::fake([
            'api.cloudflare.com/client/v4/zones/zone1/custom_hostnames' => Http::response(['success' => true, 'result' => ['id' => 'ch_1']]),
            'api.cloudflare.com/client/v4/zones/zone1/custom_hostnames/ch_1' => Http::response(['result' => ['status' => $status, 'ssl' => ['status' => $status]]]),
        ]);
    }

    public function test_owner_adds_a_domain_and_cloudflare_issues_ssl(): void
    {
        $this->fakeCloudflare();
        $this->actingAs($this->ownerOf($this->gym));
        Filament::setCurrentPanel('gym');
        Filament::setTenant($this->gym);

        Livewire::test(EditGymProfile::class)
            ->fillForm(['custom_domain' => 'https://Members.FitZone.in/'])
            ->call('save')
            ->assertHasNoFormErrors();

        $gym = $this->gym->fresh();
        $this->assertSame('members.fitzone.in', $gym->custom_domain);
        $this->assertSame('active', $gym->custom_domain_status);
        $this->assertSame('ch_1', $gym->cloudflare_hostname_id);
        $this->assertSame('https://members.fitzone.in', $gym->websiteUrl());
        Http::assertSent(fn ($r) => $r->method() === 'POST' && $r['hostname'] === 'members.fitzone.in' && $r->hasHeader('Authorization', 'Bearer cf-token'));
    }

    public function test_pending_ssl_stays_pending_until_checked_again(): void
    {
        config(['gym.custom_domains.cloudflare_zone_id' => 'zone1', 'gym.custom_domains.cloudflare_api_token' => 'cf-token']);
        Http::fake([
            'api.cloudflare.com/client/v4/zones/zone1/custom_hostnames' => Http::response(['result' => ['id' => 'ch_1']]),
            'api.cloudflare.com/client/v4/zones/zone1/custom_hostnames/ch_1' => Http::sequence()
                ->push(['result' => ['status' => 'pending', 'ssl' => ['status' => 'pending_validation']]])
                ->push(['result' => ['status' => 'active', 'ssl' => ['status' => 'active']]]),
        ]);

        app(CustomDomains::class)->assign($this->gym, 'members.fitzone.in');
        $this->assertSame('pending', $this->gym->fresh()->custom_domain_status);

        $this->artisan('gym:check-domains')->assertSuccessful();
        $this->assertSame('active', $this->gym->fresh()->custom_domain_status);
    }

    public function test_without_cloudflare_the_domain_goes_live_once_dns_points_here(): void
    {
        $this->partialMock(CustomDomains::class, fn ($mock) => $mock->shouldReceive('pointsHere')->with('members.fitzone.in')->andReturn(false, true));

        app(CustomDomains::class)->assign($this->gym, 'members.fitzone.in');
        $this->assertSame('pending', $this->gym->fresh()->custom_domain_status);

        app(CustomDomains::class)->refresh($this->gym->fresh());
        $this->assertSame('active', $this->gym->fresh()->custom_domain_status);
    }

    public function test_the_gym_domain_serves_its_website_signup_and_trial_booking(): void
    {
        $this->gym->forceFill(['custom_domain' => 'members.fitzone.in', 'custom_domain_status' => 'active'])->save();

        $this->get('https://members.fitzone.in/')->assertOk()->assertSee('FitZone own-domain site')
            ->assertSee('<link rel="canonical" href="https://members.fitzone.in">', false);
        $this->get('https://members.fitzone.in/join')->assertOk()->assertSee('Join FitZone');

        $this->post('https://members.fitzone.in'.route('website.trial', $this->gym, false), [
            'name' => 'Own Domain Visitor', 'phone' => '9822011111', 'trial_date' => today()->toDateString(), 'trial_time' => 'Morning',
        ])->assertRedirect();
        $this->assertSame(1, Lead::withoutGlobalScopes()->count());

        // The platform's own address still shows the platform home page.
        $this->get('https://gym.syberinfo.com.au/')->assertOk()->assertSee('Run your gym.', false);
        $this->get('https://unknown.example.com/join')->assertNotFound();
    }

    public function test_member_app_on_the_gym_domain_shows_that_gyms_membership(): void
    {
        $other = $this->createGym(['name' => 'Other Gym']);
        $user = User::factory()->create();
        $this->createMember($other, ['user_id' => $user->id, 'name' => 'At other gym']);
        $this->createMember($this->gym, ['user_id' => $user->id, 'name' => 'At FitZone']);
        $this->gym->forceFill(['custom_domain' => 'members.fitzone.in', 'custom_domain_status' => 'active'])->save();

        $this->actingAs($user)->get('https://members.fitzone.in/member')->assertOk()->assertSee('FitZone');
    }

    public function test_invalid_and_duplicate_domains_are_rejected(): void
    {
        $this->createGym(['name' => 'Taken'])->forceFill(['custom_domain' => 'taken.example.com'])->save();
        $this->actingAs($this->ownerOf($this->gym));
        Filament::setCurrentPanel('gym');
        Filament::setTenant($this->gym);

        foreach (['not a domain', 'fitzone.gym.syberinfo.com.au', 'taken.example.com'] as $bad) {
            Livewire::test(EditGymProfile::class)->fillForm(['custom_domain' => $bad])->call('save')->assertHasFormErrors(['custom_domain']);
        }
    }
}
