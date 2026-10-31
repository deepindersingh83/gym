<?php

namespace Tests\Feature;

use App\Enums\StaffRole;
use App\Filament\Admin\Resources\Gyms\Pages\CreateGym;
use App\Filament\Admin\Resources\Gyms\Pages\ListGyms;
use App\Models\Gym;
use App\Models\User;
use Database\Seeders\SaasPlanSeeder;
use Filament\Auth\Notifications\ResetPassword;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class AdminGymManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SaasPlanSeeder::class);
        $this->admin = User::factory()->create(['email' => 'admin@syberinfo.com.au']);
        $this->admin->forceFill(['is_super_admin' => true])->save();
        $this->actingAs($this->admin);
        Filament::setCurrentPanel('admin');
    }

    protected function gymForm(array $owner = []): array
    {
        return [
            'name' => 'Power House Fitness',
            'phone' => '9811100000',
            'city' => 'Pune',
            'owner' => ['name' => 'Meena Rao', 'email' => 'meena@example.com', 'password' => 'owner-pass-1', ...$owner],
        ];
    }

    public function test_super_admin_adds_a_gym_with_its_owner_login(): void
    {
        $this->get('/admin/gyms')->assertOk()->assertSee('Add gym');
        $this->get('/admin/gyms/create')->assertOk();

        Livewire::test(CreateGym::class)
            ->fillForm($this->gymForm())
            ->call('create')
            ->assertHasNoFormErrors();

        $gym = Gym::query()->where('name', 'Power House Fitness')->firstOrFail();
        $owner = User::query()->where('email', 'meena@example.com')->firstOrFail();

        $this->assertSame('power-house-fitness', $gym->slug);
        $this->assertSame(StaffRole::Owner, $owner->roleIn($gym));
        $this->assertTrue(Hash::check('owner-pass-1', $owner->password));
        $this->assertTrue($gym->onTrial());
        $this->assertSame(1, $gym->branches()->withoutGlobalScopes()->count());
        $this->assertSame(3, $gym->membershipPlans()->withoutGlobalScopes()->count());
        $this->assertGreaterThan(0, $gym->messageTemplates()->withoutGlobalScopes()->count());

        // The owner can now sign in to their gym.
        // The owner signs in from their own browser, so start a fresh session.
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->actingAs($owner)->get("/app/{$gym->slug}")->assertOk();
    }

    public function test_owner_without_a_password_gets_a_set_password_email(): void
    {
        Notification::fake();

        Livewire::test(CreateGym::class)
            ->fillForm($this->gymForm(['password' => null]))
            ->call('create')
            ->assertHasNoFormErrors();

        $owner = User::query()->where('email', 'meena@example.com')->firstOrFail();
        Notification::assertSentTo($owner, ResetPassword::class, fn ($n) => str_contains($n->url, '/app/password-reset/reset'));
    }

    public function test_an_existing_account_is_reused_and_keeps_its_password(): void
    {
        $existing = User::factory()->create(['email' => 'meena@example.com', 'password' => 'her-own-pass']);

        Livewire::test(CreateGym::class)
            ->fillForm($this->gymForm(['email' => 'MEENA@example.com', 'password' => 'ignored-pass']))
            ->call('create')
            ->assertHasNoFormErrors();

        $existing->refresh();
        $this->assertTrue(Hash::check('her-own-pass', $existing->password));
        $this->assertSame(StaffRole::Owner, $existing->roleIn(Gym::query()->firstOrFail()));
        $this->assertSame(2, User::query()->count());
    }

    public function test_super_admin_can_open_any_gym_panel_with_owner_rights(): void
    {
        $gym = $this->createGym();
        $this->createMember($gym, ['name' => 'Support Check']);

        $this->get("/app/{$gym->slug}")->assertOk();
        $this->get("/app/{$gym->slug}/members")->assertOk()->assertSee('Support Check');
        $this->get("/app/{$gym->slug}/payments")->assertOk();
        $this->get("/app/{$gym->slug}/profile")->assertOk();
    }

    public function test_super_admin_can_set_an_owner_password(): void
    {
        $gym = $this->createGym();
        $owner = $this->ownerOf($gym);

        Livewire::test(ListGyms::class)
            ->callTableAction('ownerPassword', $gym, data: ['user_id' => $owner->id, 'password' => 'brand-new-pass'])
            ->assertHasNoTableActionErrors();

        $this->assertTrue(Hash::check('brand-new-pass', $owner->fresh()->password));
    }

    public function test_reserved_and_duplicate_slugs_are_rejected(): void
    {
        $this->createGym(['name' => 'Taken', 'slug' => 'taken']);

        Livewire::test(CreateGym::class)->fillForm([...$this->gymForm(), 'slug' => 'admin'])->call('create')->assertHasFormErrors(['slug']);
        Livewire::test(CreateGym::class)->fillForm([...$this->gymForm(), 'slug' => 'taken'])->call('create')->assertHasFormErrors(['slug']);
    }
}
