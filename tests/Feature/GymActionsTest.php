<?php

namespace Tests\Feature;

use App\Enums\StaffRole;
use App\Filament\Gym\Pages\Dashboard;
use App\Filament\Gym\Pages\OutstandingDues;
use App\Filament\Gym\Resources\CashClosings\Pages\ManageCashClosings;
use App\Filament\Gym\Resources\Members\Pages\ListMembers;
use App\Filament\Gym\Resources\Members\Pages\ViewMember;
use App\Filament\Gym\Resources\Members\RelationManagers\AttendancesRelationManager;
use App\Filament\Gym\Resources\Members\RelationManagers\MeasurementsRelationManager;
use App\Filament\Gym\Resources\Members\RelationManagers\PaymentsRelationManager;
use App\Filament\Gym\Resources\Members\RelationManagers\SubscriptionsRelationManager;
use App\Filament\Gym\Resources\Staff\Pages\ManageStaff;
use App\Filament\Gym\Widgets\CollectionsByMethodChart;
use App\Filament\Gym\Widgets\KeyStats;
use App\Filament\Gym\Widgets\PeriodSummary;
use App\Filament\Gym\Widgets\RenewalsDue;
use App\Filament\Gym\Widgets\RevenueChart;
use App\Models\CashClosing;
use App\Models\Gym;
use App\Models\Member;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GymActionsTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DemoSeeder::class);
        $this->gym = Gym::query()->firstOrFail();
        $this->actingAs(User::query()->where('email', 'owner@example.com')->firstOrFail());
        Filament::setCurrentPanel('gym');
        Filament::setTenant($this->gym);
    }

    public function test_dashboard_widgets_render(): void
    {
        foreach ([KeyStats::class, PeriodSummary::class, RevenueChart::class, CollectionsByMethodChart::class, RenewalsDue::class] as $widget) {
            Livewire::test($widget, ['pageFilters' => ['from' => today()->startOfYear()->toDateString(), 'until' => today()->toDateString()]])->assertOk();
        }

        Livewire::test(KeyStats::class)->assertSee('Revenue today')->assertSee('Dues outstanding');
        Livewire::test(Dashboard::class)->assertOk();
    }

    public function test_member_view_tabs_render(): void
    {
        $member = Member::query()->firstOrFail();

        foreach ([SubscriptionsRelationManager::class, PaymentsRelationManager::class, MeasurementsRelationManager::class, AttendancesRelationManager::class] as $manager) {
            Livewire::test($manager, ['ownerRecord' => $member, 'pageClass' => ViewMember::class])->assertOk();
        }
    }

    public function test_member_list_filters_and_renewals_work(): void
    {
        Livewire::test(ListMembers::class)
            ->filterTable('membership', 'dues')
            ->assertOk();

        $member = Member::query()->whereHas('latestSubscription')->firstOrFail();
        $before = $member->subscriptions()->count();

        Livewire::test(ViewMember::class, ['record' => $member->getKey()])
            ->callAction('renew', data: ['amount_paid' => 100, 'payment_method' => 'cash'])
            ->assertHasNoActionErrors();

        $this->assertSame($before + 1, $member->subscriptions()->count());
    }

    public function test_record_payment_action_reduces_dues(): void
    {
        $member = Member::query()->whereHas('subscriptions', fn ($q) => $q->withDues())->firstOrFail();
        $due = $member->balance_due;

        Livewire::test(ViewMember::class, ['record' => $member->getKey()])
            ->callAction('recordPayment', data: ['amount' => 100, 'method' => 'upi', 'reference' => 'UTR1', 'paid_at' => now()])
            ->assertHasNoActionErrors();

        $this->assertSame(round($due - 100, 2), $member->fresh()->balance_due);
        Livewire::test(OutstandingDues::class)->assertOk()->assertCanSeeTableRecords($member->subscriptions()->withDues()->get());
    }

    public function test_cash_closing_compares_counted_cash_with_cash_payments(): void
    {
        $branch = $this->gym->branches()->first();
        $member = Member::query()->where('branch_id', $branch->id)->firstOrFail();
        Payment::create(['member_id' => $member->id, 'branch_id' => $branch->id, 'amount' => 1200, 'method' => 'cash', 'paid_at' => now()]);

        Livewire::test(ManageCashClosings::class)
            ->callAction('create', data: ['branch_id' => $branch->id, 'closing_date' => today()->toDateString(), 'opening_balance' => 500, 'counted_cash' => 1650])
            ->assertHasNoActionErrors();

        $closing = CashClosing::query()->firstOrFail();
        $this->assertGreaterThanOrEqual(1200, (float) $closing->cash_collected);
        $this->assertSame(round(500 + (float) $closing->cash_collected - (float) $closing->cash_expenses, 2), (float) $closing->expected_cash);
        $this->assertSame(round(1650 - (float) $closing->expected_cash, 2), (float) $closing->difference);
    }

    public function test_owner_can_add_staff_who_then_sees_the_gym(): void
    {
        Livewire::test(ManageStaff::class)
            ->callAction('create', data: ['name' => 'Sam Coach', 'email' => 'sam@example.com', 'password' => 'password123', 'role' => 'trainer'])
            ->assertHasNoActionErrors();

        $sam = User::query()->where('email', 'sam@example.com')->firstOrFail();
        $this->assertSame(StaffRole::Trainer, $sam->roleIn($this->gym));
    }

    public function test_actions_are_recorded_in_the_audit_log(): void
    {
        $this->assertDatabaseHas('activity_log', ['gym_id' => $this->gym->id, 'subject_type' => Payment::class, 'event' => 'created']);
    }
}
