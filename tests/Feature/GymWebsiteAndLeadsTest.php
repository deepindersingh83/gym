<?php

namespace Tests\Feature;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\StaffRole;
use App\Filament\Gym\Resources\Leads\Pages\ListLeads;
use App\Models\Gym;
use App\Models\Lead;
use App\Models\Member;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GymWebsiteAndLeadsTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = $this->createGym(['name' => 'Iron Temple', 'city' => 'Pune', 'website' => ['tagline' => 'Lift heavy, live long', 'about' => 'Best gym in town.']]);
        $this->createPlan($this->gym, ['name' => 'Gold Monthly']);
        $trainer = $this->addStaff($this->gym, StaffRole::Trainer);
        $this->gym->staffMemberships()->withoutGlobalScopes()->where('user_id', $trainer->id)->update(['show_on_website' => true, 'bio' => 'Coach bio here']);
    }

    public function test_website_shows_gym_details_with_seo_tags(): void
    {
        $this->get(route('website.show', $this->gym))
            ->assertOk()
            ->assertSee('<title>Iron Temple — Gym in Pune</title>', false)
            ->assertSee('<meta name="description"', false)
            ->assertSee('"@type":"ExerciseGym"', false)
            ->assertSee('Lift heavy, live long')
            ->assertSee('Gold Monthly')
            ->assertSee('Coach bio here')
            ->assertSee('Book my free trial');

        $this->get(route('sitemap'))->assertOk()->assertSee(route('website.show', $this->gym));
    }

    public function test_hidden_or_expired_gym_websites_are_not_public(): void
    {
        $this->gym->update(['website' => ['enabled' => false]]);
        $this->get(route('website.show', $this->gym))->assertNotFound();

        $this->gym->update(['website' => ['enabled' => true], 'trial_ends_at' => now()->subDay()]);
        $this->get(route('website.show', $this->gym))->assertNotFound();
    }

    public function test_booking_a_free_trial_creates_an_enquiry_and_notifies_staff(): void
    {
        $owner = $this->ownerOf($this->gym);

        $this->from(route('website.show', $this->gym))->post(route('website.trial', $this->gym), [
            'name' => 'Priya Shah', 'phone' => '9822012345', 'trial_date' => today()->addDay()->toDateString(), 'trial_time' => 'Evening', 'interest' => 'Weight loss',
        ])->assertRedirect()->assertSessionHas('trial_booked');

        $lead = Lead::withoutGlobalScopes()->firstOrFail();
        $this->assertSame(LeadSource::Website, $lead->source);
        $this->assertSame(LeadStatus::TrialBooked, $lead->status);
        $this->assertSame('Evening', $lead->trial_time);
        $this->assertSame(1, $owner->notifications()->count());
    }

    public function test_spam_bots_filling_the_hidden_field_are_ignored(): void
    {
        $this->post(route('website.trial', $this->gym), [
            'name' => 'Bot', 'phone' => '9822012345', 'trial_date' => today()->toDateString(), 'trial_time' => 'Morning', 'website_url' => 'http://spam',
        ])->assertRedirect();

        $this->assertSame(0, Lead::withoutGlobalScopes()->count());
    }

    public function test_staff_convert_an_enquiry_into_a_member(): void
    {
        $this->actingAs($this->ownerOf($this->gym));
        Filament::setCurrentPanel('gym');
        Filament::setTenant($this->gym);
        $lead = Lead::create(['name' => 'Rahul', 'phone' => '9811100022', 'source' => LeadSource::Instagram]);

        $this->get("/app/{$this->gym->slug}/leads")->assertOk()->assertSee('Rahul');

        Livewire::test(ListLeads::class)->callTableAction('convert', $lead)->assertHasNoTableActionErrors();

        $member = Member::query()->where('phone', '9811100022')->firstOrFail();
        $this->assertSame('social', $member->source);
        $this->assertSame(LeadStatus::Converted, $lead->fresh()->status);
        $this->assertSame($member->id, $lead->fresh()->member_id);
    }

    public function test_trainers_cannot_see_enquiries(): void
    {
        $trainer = $this->addStaff($this->gym, StaffRole::Trainer);

        $this->actingAs($trainer)->get("/app/{$this->gym->slug}/leads")->assertForbidden();
    }
}
