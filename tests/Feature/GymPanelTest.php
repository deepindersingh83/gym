<?php

namespace Tests\Feature;

use App\Enums\StaffRole;
use App\Filament\Gym\Pages\CheckIn;
use App\Filament\Gym\Pages\Tenancy\RegisterGym;
use App\Filament\Gym\Resources\Members\Pages\CreateMember;
use App\Models\Attendance;
use App\Models\Gym;
use App\Models\Member;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GymPanelTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DemoSeeder::class);
        $this->gym = Gym::query()->firstOrFail();
    }

    protected function actingAsDemo(string $email): User
    {
        $user = User::query()->where('email', $email)->firstOrFail();
        $this->actingAs($user);
        Filament::setCurrentPanel('gym');
        Filament::setTenant($this->gym);

        return $user;
    }

    public static function ownerPages(): array
    {
        return array_map(fn ($path) => [$path], [
            '', '/members', '/members/create', '/subscriptions', '/subscriptions/create', '/payments', '/outstanding-dues',
            '/expenses', '/cash-closings', '/membership-plans', '/coupons', '/branches', '/staff', '/staff-attendances',
            '/trainer-ratings', '/measurements', '/workout-plans', '/diet-plans', '/attendances', '/check-in',
            '/message-templates', '/broadcasts', '/message-logs', '/activities', '/profile',
        ]);
    }

    #[DataProvider('ownerPages')]
    public function test_owner_can_open_every_page(string $path): void
    {
        $this->actingAsDemo('owner@example.com');

        $this->get("/app/{$this->gym->slug}{$path}")->assertOk();
    }

    public function test_owner_can_open_member_and_membership_records(): void
    {
        $this->actingAsDemo('owner@example.com');
        $member = Member::withoutGlobalScopes()->firstOrFail();
        $subscription = Subscription::withoutGlobalScopes()->firstOrFail();

        $this->get("/app/{$this->gym->slug}/members/{$member->id}")->assertOk()->assertSee($member->name);
        $this->get("/app/{$this->gym->slug}/members/{$member->id}/edit")->assertOk();
        $this->get("/app/{$this->gym->slug}/subscriptions/{$subscription->id}")->assertOk()->assertSee($subscription->invoice_number);
    }

    public function test_receptionist_is_kept_out_of_owner_areas(): void
    {
        $this->actingAsDemo('reception@example.com');
        $base = "/app/{$this->gym->slug}";

        $this->get("{$base}/members")->assertOk();
        $this->get("{$base}/payments")->assertOk();
        $this->get("{$base}/check-in")->assertOk();
        $this->get("{$base}/expenses")->assertForbidden();
        $this->get("{$base}/staff")->assertForbidden();
        $this->get("{$base}/activities")->assertForbidden();
        $this->get("{$base}/profile")->assertNotFound();
    }

    public function test_trainer_sees_fitness_but_not_billing(): void
    {
        $this->actingAsDemo('trainer@example.com');
        $base = "/app/{$this->gym->slug}";

        $this->get("{$base}/members")->assertOk();
        $this->get("{$base}/workout-plans")->assertOk();
        $this->get("{$base}/payments")->assertForbidden();
        $this->get("{$base}/outstanding-dues")->assertForbidden();
    }

    public function test_staff_can_add_a_member_with_a_first_membership_and_part_payment(): void
    {
        $this->actingAsDemo('reception@example.com');
        $plan = $this->gym->membershipPlans()->withoutGlobalScopes()->where('name', 'Monthly')->firstOrFail();

        Livewire::test(CreateMember::class)
            ->fillForm([
                'name' => 'New Joiner',
                'phone' => '9000011111',
                'membership' => [
                    'membership_plan_id' => $plan->id,
                    'start_date' => today()->toDateString(),
                    'amount_paid' => 500,
                    'payment_method' => 'gpay',
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $member = Member::withoutGlobalScopes()->where('phone', '9000011111')->firstOrFail();
        $subscription = $member->subscriptions()->withoutGlobalScopes()->firstOrFail();

        $this->assertSame($plan->id, $subscription->membership_plan_id);
        $this->assertSame(500.0, (float) $subscription->amount_paid);
        $this->assertGreaterThan(0, $subscription->balance_due);
    }

    public function test_check_in_desk_accepts_qr_codes_and_rejects_expired_members(): void
    {
        $this->actingAsDemo('owner@example.com');
        $active = Member::withoutGlobalScopes()->whereHas('subscriptions', fn ($q) => $q->withoutGlobalScopes()->active())->firstOrFail();
        $expired = Member::withoutGlobalScopes()->whereDoesntHave('subscriptions', fn ($q) => $q->withoutGlobalScopes()->active())->firstOrFail();

        Livewire::test(CheckIn::class)->set('code', $active->check_in_code)->call('checkIn')->assertSet('result.ok', true);
        Livewire::test(CheckIn::class)->set('code', $expired->member_code)->call('checkIn')->assertSet('result.ok', false);

        $this->assertSame(1, Attendance::withoutGlobalScopes()->count());
        $this->assertSame('qr', Attendance::withoutGlobalScopes()->value('method'));
    }

    public function test_writes_are_locked_when_the_trial_has_ended(): void
    {
        $this->gym->update(['trial_ends_at' => now()->subDay()]);
        $this->actingAsDemo('owner@example.com');

        $this->get("/app/{$this->gym->slug}/members")->assertOk()->assertSee('has ended');
        $this->get("/app/{$this->gym->slug}/members/create")->assertForbidden();
    }

    public function test_new_owner_can_register_a_gym_with_the_onboarding_wizard(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get('/app/new')->assertOk();

        Filament::setCurrentPanel('gym');
        Livewire::test(RegisterGym::class)
            ->fillForm(['name' => 'Muscle Factory', 'phone' => '9888877777', 'starter_plans' => ['monthly', 'yearly']])
            ->call('register')
            ->assertHasNoFormErrors();

        $gym = Gym::query()->where('name', 'Muscle Factory')->firstOrFail();
        $this->assertSame(StaffRole::Owner, $user->roleIn($gym));
        $this->assertSame(2, $gym->membershipPlans()->withoutGlobalScopes()->count());
        $this->assertSame(1, $gym->branches()->withoutGlobalScopes()->count());
        $this->assertTrue($gym->onTrial());
    }
}
