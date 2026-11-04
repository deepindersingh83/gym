<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Enums\StaffRole;
use App\Filament\Gym\Pages\Reports;
use App\Models\Attendance;
use App\Models\Lead;
use App\Services\GymReports;
use App\Services\SubscriptionService;
use App\Support\Tenancy;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GymReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_retention_trainer_revenue_busiest_hours_and_lead_conversion(): void
    {
        $this->travelTo('2026-11-10 10:00:00');
        $gym = $this->createGym();
        Tenancy::setGym($gym);
        $trainer = $this->addStaff($gym, StaffRole::Trainer);
        $plan = $this->createPlan($gym, ['price' => 1000]);
        $service = app(SubscriptionService::class);

        // Two memberships ended in October; one renewed.
        $stayed = $this->createMember($gym, ['trainer_id' => $trainer->id]);
        $left = $this->createMember($gym);
        $service->create($stayed, $plan, today()->setDate(2026, 9, 15))->update(['end_date' => '2026-10-14']);
        $service->create($left, $plan, today()->setDate(2026, 9, 10))->update(['end_date' => '2026-10-09']);
        $renewal = $service->create($stayed, $plan, today()->setDate(2026, 10, 15));
        $service->recordPayment($renewal, 1000, 'cash');

        $october = (new GymReports($gym))->retention(6)->firstWhere('month', 'Oct 2026');
        $this->assertSame(['month' => 'Oct 2026', 'ended' => 2, 'renewed' => 1, 'rate' => 50.0], $october);

        $trainers = (new GymReports($gym))->revenuePerTrainer(now()->subDays(90), now());
        $this->assertSame(1000.0, $trainers[0]['collected']);
        $this->assertSame(1, $trainers[0]['members']);

        Attendance::create(['member_id' => $stayed->id, 'checked_in_at' => now()->setTime(18, 15)]);
        Attendance::create(['member_id' => $left->id, 'checked_in_at' => now()->setTime(18, 40)]);
        Attendance::create(['member_id' => $left->id, 'checked_in_at' => now()->setTime(7, 0)]);
        $hours = (new GymReports($gym))->busiestHours(30);
        $this->assertSame(3, $hours['total']);
        $this->assertSame(2, $hours['grid'][now()->dayOfWeekIso][18]);
        $this->assertSame('Tuesday 6 PM', $hours['peak']);

        Lead::create(['name' => 'A', 'source' => 'facebook', 'status' => LeadStatus::Converted]);
        Lead::create(['name' => 'B', 'source' => 'facebook', 'status' => LeadStatus::Lost]);
        Lead::create(['name' => 'C', 'source' => 'walk_in', 'status' => LeadStatus::New, 'trial_date' => today()]);
        $leads = (new GymReports($gym))->leadConversion(now()->subDays(30), now())->keyBy('source');
        $this->assertSame(50.0, $leads['Facebook']['rate']);
        $this->assertSame(1, $leads['Walk-in']['trials'] ?? $leads->first(fn ($r) => $r['source'] !== 'Facebook')['trials']);

        $this->actingAs($this->ownerOf($gym));
        Filament::setCurrentPanel('gym');
        Filament::setTenant($gym);
        Livewire::test(Reports::class)->assertOk()->assertSee('Member retention')->assertSee('Tuesday 6 PM')->call('$set', 'days', 30)->assertOk();

        $this->actingAs($this->addStaff($gym, StaffRole::Receptionist));
        $this->get(Reports::getUrl(tenant: $gym))->assertForbidden();
    }

    public function test_owner_gets_the_daily_summary_once_at_their_hour(): void
    {
        config(['mail.default' => 'array']);
        $gym = $this->createGym(['settings' => ['daily_summary_hour' => 21]]);
        Tenancy::setGym($gym);
        $member = $this->createMember($gym);
        app(SubscriptionService::class)->recordPayment(app(SubscriptionService::class)->create($member, $this->createPlan($gym, ['price' => 1500])), 1500, 'upi');
        Tenancy::setGym(null);

        $this->travelTo(now()->setTimezone($gym->timezone)->setTime(20, 5)->setTimezone(config('app.timezone')));
        $this->artisan('gym:daily-summary')->expectsOutput('Queued 0 daily summaries.');

        $this->travel(1)->hours();
        $this->artisan('gym:daily-summary')->expectsOutput('Queued 1 daily summary.');
        $this->artisan('gym:daily-summary')->expectsOutput('Queued 0 daily summaries.');

        $mail = collect(app('mailer')->getSymfonyTransport()->messages()->all())->last()->getOriginalMessage();
        $this->assertStringContainsString('1,500', $mail->getSubject());
        $this->assertStringContainsString('New members: 1', $mail->getTextBody());
    }
}
