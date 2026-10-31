<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\Usage;
use App\Models\Attendance;
use App\Models\User;
use App\Services\GymHealth;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UsageDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function health(): GymHealth
    {
        return new GymHealth;
    }

    public function test_gyms_are_classified_with_reasons(): void
    {
        $busy = $this->createGym(['name' => 'Busy', 'last_activity_at' => now()->subHour(), 'trial_ends_at' => now()->addDays(10)]);
        $this->createMember($busy);

        $endingTrial = $this->createGym(['name' => 'Ending trial', 'last_activity_at' => now(), 'trial_ends_at' => now()->addDays(3)]);
        $this->createMember($endingTrial);

        $quiet = $this->createGym(['name' => 'Quiet', 'last_activity_at' => now()->subDays(10)]);
        $this->createMember($quiet);

        $gone = $this->createGym(['name' => 'Gone', 'last_activity_at' => now()->subDays(45)]);
        $lapsed = $this->createGym(['name' => 'Lapsed', 'last_activity_at' => now(), 'trial_ends_at' => now()->subDay()]);

        $dropping = $this->createGym(['name' => 'Dropping', 'last_activity_at' => now()]);
        $member = $this->createMember($dropping);
        foreach (range(1, 30) as $i) {
            Attendance::withoutGlobalScopes()->create(['gym_id' => $dropping->id, 'member_id' => $member->id, 'checked_in_at' => now()->subDays(31 + ($i % 25))]);
        }
        foreach (range(1, 5) as $i) {
            Attendance::withoutGlobalScopes()->create(['gym_id' => $dropping->id, 'member_id' => $member->id, 'checked_in_at' => now()->subDays($i)]);
        }

        $report = $this->health();

        $this->assertSame('active', $report->for($busy)['status']);
        $this->assertSame('at_risk', $report->for($endingTrial)['status']);
        $this->assertStringContainsString('Trial ends', $report->for($endingTrial)['reasons'][0]);
        $this->assertSame(['No staff login for 10 days'], $report->for($quiet)['reasons']);
        $this->assertSame('inactive', $report->for($gone)['status']);
        $this->assertSame(['Trial ended without payment'], $report->for($lapsed)['reasons']);
        $this->assertSame('at_risk', $report->for($dropping)['status']);
        $this->assertStringContainsString('Check-ins down 83%', implode(' ', $report->for($dropping)['reasons']));
    }

    public function test_staff_activity_is_tracked_but_super_admin_visits_are_not(): void
    {
        $gym = $this->createGym();
        $this->actingAs($this->ownerOf($gym))->get("/app/{$gym->slug}")->assertOk();
        $this->assertNotNull($first = $gym->fresh()->last_activity_at);

        $gym->forceFill(['last_activity_at' => now()->subDays(20)])->save();
        $admin = User::factory()->create();
        $admin->forceFill(['is_super_admin' => true])->save();
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->actingAs($admin)->get("/app/{$gym->slug}")->assertOk();

        $this->assertTrue($gym->fresh()->last_activity_at->lt(now()->subDays(19)));
    }

    public function test_super_admin_sees_the_usage_page_and_filters_by_health(): void
    {
        $active = $this->createGym(['name' => 'Happy Gym', 'last_activity_at' => now()]);
        $this->createMember($active);
        $inactive = $this->createGym(['name' => 'Silent Gym', 'last_activity_at' => now()->subDays(60)]);
        $admin = User::factory()->create();
        $admin->forceFill(['is_super_admin' => true])->save();

        $this->actingAs($admin)->get('/admin/usage')->assertOk();
        $this->get('/admin')->assertOk();

        Filament::setCurrentPanel('admin');
        Livewire::test(Usage::class)
            ->assertCanSeeTableRecords([$active, $inactive])
            ->filterTable('health', 'inactive')
            ->assertCanSeeTableRecords([$inactive])
            ->assertCanNotSeeTableRecords([$active])
            ->assertSee('No staff login for 60 days');
    }
}
