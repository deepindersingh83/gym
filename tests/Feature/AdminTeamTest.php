<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Filament\Admin\Resources\AdminTeam\AdminTeamResource;
use App\Filament\Admin\Resources\AdminTeam\Pages\ManageAdminTeam;
use App\Filament\Admin\Resources\Gyms\Pages\ListGyms;
use App\Models\Activity;
use App\Models\User;
use App\Support\AdminLog;
use Filament\Facades\Filament;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class AdminTeamTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(AdminRole $role = AdminRole::Owner): User
    {
        $user = User::factory()->create();
        $user->forceFill(['admin_role' => $role])->save();

        return $user;
    }

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    public function test_each_role_only_sees_its_part_of_the_admin_panel(): void
    {
        $gym = $this->createGym();

        $this->actingAs($this->admin(AdminRole::Support));
        $this->get('/admin/gyms')->assertOk();
        $this->get('/admin/settings')->assertForbidden();
        $this->get('/admin/team')->assertForbidden();
        $this->get('/admin/saas-payments')->assertForbidden();
        $this->get('/app/'.$gym->slug)->assertOk(); // support can open a gym to help

        $this->actingAs($this->admin(AdminRole::Finance));
        $this->get('/admin/saas-payments')->assertOk();
        $this->get('/admin/settings')->assertForbidden();
        $this->assertFalse(auth()->user()->canAccessTenant($gym)); // finance cannot open gym panels

        $this->actingAs($this->admin(AdminRole::Sales));
        $this->get('/admin/partners')->assertOk();
        $this->get('/admin/discount-codes')->assertOk();
        $this->get('/admin/saas-payments')->assertForbidden();

        // A gym owner who is not on the team cannot reach /admin at all.
        $this->actingAs($this->ownerOf($gym));
        $this->get('/admin')->assertForbidden();
    }

    public function test_support_cannot_record_payments_or_delete_gyms(): void
    {
        $gym = $this->createGym();
        $this->actingAs($this->admin(AdminRole::Support));

        Livewire::test(ListGyms::class)
            ->assertTableActionHidden('activate', $gym)
            ->assertTableActionVisible('extendTrial', $gym)
            ->callTableAction('extendTrial', $gym, ['days' => 7]);

        $entry = Activity::query()->where('log_name', AdminLog::NAME)->latest('id')->first();
        $this->assertSame('Extended trial', $entry->description);
        $this->assertSame($gym->id, $entry->gym_id);
        $this->assertSame(auth()->id(), $entry->causer_id);
    }

    public function test_owner_invites_a_team_member_who_sets_their_own_password(): void
    {
        Notification::fake();
        $this->actingAs($this->admin());

        Livewire::test(ManageAdminTeam::class)
            ->callAction('create', ['name' => 'Priya', 'email' => 'Priya@Example.com', 'admin_role' => 'finance'])
            ->assertHasNoActionErrors();

        $priya = User::query()->where('email', 'priya@example.com')->sole();
        $this->assertSame(AdminRole::Finance, $priya->adminRole());
        Notification::assertSentTo($priya, ResetPassword::class);
        $this->assertTrue(Activity::query()->where('description', 'Added admin team member')->exists());
    }

    public function test_the_last_owner_cannot_be_removed(): void
    {
        $owner = $this->admin();
        $this->actingAs($owner);
        $support = $this->admin(AdminRole::Support);

        $this->assertTrue(AdminTeamResource::isLastOwner($owner));
        $this->assertFalse(AdminTeamResource::isLastOwner($support));

        Livewire::test(ManageAdminTeam::class)
            ->assertTableActionHidden('remove', $owner)
            ->callTableAction('remove', $support);

        $this->assertFalse($support->fresh()->isPlatformAdmin());
    }

    public function test_admins_must_set_up_an_authenticator_app_when_required(): void
    {
        config(['gym.admin.require_two_factor' => true]);
        $admin = $this->admin();
        $this->actingAs($admin);

        $this->get('/admin/gyms')->assertRedirectContains('multi-factor-authentication/set-up');

        $admin->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
        $this->get('/admin/gyms')->assertOk();
    }

    public function test_suspending_a_gym_locks_changes_and_shows_the_reason(): void
    {
        $gym = $this->createGym(['status' => 'active', 'subscription_ends_at' => now()->addMonth()]);
        $this->actingAs($this->admin(AdminRole::Support));

        Livewire::test(ListGyms::class)->callTableAction('suspend', $gym, ['reason' => 'Payment disputed']);

        $gym->refresh();
        $this->assertSame('suspended', $gym->status);
        $this->assertSame('Payment disputed', $gym->suspension_reason);
        $this->assertFalse($gym->hasActiveSaasSubscription());

        $this->actingAs($this->ownerOf($gym))->get('/app/'.$gym->slug)->assertSee('Payment disputed');

        // The gym's own audit log does not list platform staff actions.
        $this->get('/app/'.$gym->slug.'/activities')->assertDontSee('Suspended gym');
    }
}
