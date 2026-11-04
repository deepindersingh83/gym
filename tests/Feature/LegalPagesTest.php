<?php

namespace Tests\Feature;

use App\Filament\Gym\Pages\Tenancy\RegisterGym;
use App\Models\User;
use Database\Seeders\SaasPlanSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_privacy_terms_and_gym_notice_pages(): void
    {
        config(['gym.legal.privacy_email' => 'privacy@example.com', 'gym.legal.grievance_officer' => 'Asha Menon']);

        $this->get('/privacy')->assertOk()->assertSee('Digital Personal Data Protection Act')->assertSee('Australian Privacy Principles')
            ->assertSee('privacy@example.com')->assertSee('Asha Menon');
        $this->get('/terms')->assertOk()->assertSee('Terms of service')->assertSee('Australian Consumer Law');

        $india = $this->createGym(['name' => 'Iron Temple', 'settings' => ['allow_online_signup' => true]]);
        $this->get(route('legal.gym-privacy', $india))->assertOk()->assertSee('Iron Temple')->assertSee('Data Protection Board');
        $au = $this->createGym(['name' => 'Bondi Strength', 'country' => 'AU']);
        $this->get(route('legal.gym-privacy', $au))->assertOk()->assertSee('oaic.gov.au');

        $this->get(route('join.show', $india))->assertSee(route('legal.gym-privacy', $india));
        $this->get('/')->assertSee(route('legal.privacy'));
    }

    public function test_new_gyms_must_accept_the_terms(): void
    {
        $this->seed(SaasPlanSeeder::class);
        $this->actingAs(User::factory()->create());
        Filament::setCurrentPanel('gym');

        Livewire::test(RegisterGym::class)
            ->fillForm(['name' => 'No Terms Gym', 'phone' => '9000000001'])
            ->call('register')
            ->assertHasFormErrors(['accept_terms' => 'accepted']);
    }
}
