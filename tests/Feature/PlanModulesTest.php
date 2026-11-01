<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\SaasPlans\Pages\ManageSaasPlans;
use App\Filament\Gym\Resources\Members\Pages\ListMembers;
use App\Filament\Member\Pages\Auth\Login;
use App\Models\Gym;
use App\Models\SaasPlan;
use App\Models\User;
use Database\Seeders\SaasPlanSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PlanModulesTest extends TestCase
{
    use RefreshDatabase;

    protected function paidGym(string $plan): Gym
    {
        $this->seed(SaasPlanSeeder::class);

        return $this->createGym([
            'name' => ucfirst($plan).' Gym',
            'status' => 'active',
            'subscription_ends_at' => now()->addYear(),
            'saas_plan_id' => SaasPlan::query()->where('slug', $plan)->value('id'),
        ]);
    }

    protected function asOwner(Gym $gym): void
    {
        $this->actingAs($this->ownerOf($gym));
        Filament::setCurrentPanel('gym');
        Filament::setTenant($gym);
    }

    public function test_basic_plan_locks_the_features_it_does_not_include(): void
    {
        $gym = $this->paidGym('basic');
        $this->asOwner($gym);
        $base = "/app/{$gym->slug}";

        $this->get("{$base}/members")->assertOk();
        $this->get("{$base}/broadcasts")->assertForbidden();
        $this->get("{$base}/workout-plans")->assertForbidden();
        $this->get("{$base}/activities")->assertForbidden();
        $this->get("{$base}/profile?tab=website::data::tab")->assertOk()->assertSee('Gym website is not included in your plan');

        Livewire::test(ListMembers::class)->assertActionVisible('export');

        $this->get(route('website.show', $gym))->assertNotFound();
        $this->post(route('webhooks.leads', 'x'))->assertNotFound();
    }

    public function test_pro_plan_includes_the_website_but_not_an_own_domain(): void
    {
        $gym = $this->paidGym('pro');
        $this->asOwner($gym);

        $this->get("/app/{$gym->slug}/broadcasts")->assertOk();
        $this->get(route('website.show', $gym))->assertOk();
        $this->get("/app/{$gym->slug}/profile?tab=website::data::tab")->assertSee('Own domain is not included in your plan');
    }

    public function test_trial_gyms_get_everything_unless_turned_off(): void
    {
        $this->seed(SaasPlanSeeder::class);
        $gym = $this->createGym(['saas_plan_id' => SaasPlan::query()->where('slug', 'basic')->value('id')]);
        $this->assertTrue($gym->onTrial());
        $this->assertTrue($gym->hasModule('broadcasts'));

        config(['gym.billing.trial_full_access' => false]);
        $this->assertFalse($gym->fresh()->hasModule('broadcasts'));
        $this->assertTrue($gym->fresh()->hasModule('member_app'));
    }

    public function test_member_app_follows_the_plan(): void
    {
        $gym = $this->paidGym('basic');
        SaasPlan::query()->where('slug', 'basic')->update(['modules' => json_encode(['exports'])]);
        $user = User::create(['name' => 'M', 'phone' => '9876500011', 'password' => 'member-pass']);
        $this->createMember($gym, ['phone' => '9876500011', 'user_id' => $user->id]);
        Filament::setCurrentPanel('member');

        Livewire::test(Login::class)->fillForm(['login' => '9876500011', 'password' => 'member-pass'])->call('authenticate')->assertHasFormErrors(['login']);

        SaasPlan::query()->where('slug', 'basic')->update(['modules' => json_encode(['exports', 'member_app'])]);
        Livewire::test(Login::class)->fillForm(['login' => '9876500011', 'password' => 'member-pass'])->call('authenticate')->assertHasNoFormErrors();
    }

    public function test_admin_chooses_plan_features(): void
    {
        $this->seed(SaasPlanSeeder::class);
        $admin = User::factory()->create();
        $admin->forceFill(['admin_role' => 'owner'])->save();
        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');
        $basic = SaasPlan::query()->where('slug', 'basic')->sole();

        Livewire::test(ManageSaasPlans::class)
            ->callTableAction('edit', $basic, data: ['modules' => ['member_app', 'website'], 'all_modules' => false, 'monthly_price_aud' => 35])
            ->assertHasNoTableActionErrors();
        $this->assertSame(['member_app', 'website'], $basic->fresh()->modules);
        $this->assertSame(35.0, (float) $basic->fresh()->monthly_price_aud);

        Livewire::test(ManageSaasPlans::class)
            ->callTableAction('edit', $basic, data: ['all_modules' => true])
            ->assertHasNoTableActionErrors();
        $this->assertNull($basic->fresh()->modules);
    }
}
