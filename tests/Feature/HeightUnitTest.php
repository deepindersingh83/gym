<?php

namespace Tests\Feature;

use App\Filament\Gym\Pages\Tenancy\EditGymProfile;
use App\Filament\Gym\Resources\Measurements\Pages\ManageMeasurements;
use App\Filament\Gym\Resources\Members\Pages\CreateMember;
use App\Filament\Gym\Resources\Members\Pages\EditMember;
use App\Models\Gym;
use App\Models\Measurement;
use App\Models\Member;
use App\Support\Height;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HeightUnitTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = $this->createGym();
        $this->actingAs($this->ownerOf($this->gym));
        Filament::setCurrentPanel('gym');
        Filament::setTenant($this->gym);
    }

    protected function useFeet(): void
    {
        $this->gym->update(['settings' => ['height_unit' => 'ft']]);
        Filament::setTenant($this->gym->fresh());
    }

    public function test_conversions_round_trip(): void
    {
        $this->assertSame(170.2, Height::fromFeet(5, 7));
        $this->assertSame([5, 7.0], Height::toFeet(170.2));
        $this->assertSame([6, 0.0], Height::toFeet(182.88));
        $this->assertNull(Height::fromFeet(null, ''));
        $this->assertSame('170 cm', Height::format(170, $this->gym));
        $this->assertSame('5′ 7″', Height::format(170.2, $this->gym->replicate()->fill(['settings' => ['height_unit' => 'ft']])));
    }

    public function test_owner_chooses_the_unit_in_gym_settings(): void
    {
        $this->assertSame('cm', Height::unit($this->gym));

        Livewire::test(EditGymProfile::class)
            ->fillForm(['settings' => ['height_unit' => 'ft']])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('ft', Height::unit($this->gym->fresh()));
    }

    public function test_member_height_is_entered_in_feet_and_stored_in_centimetres(): void
    {
        $this->useFeet();

        Livewire::test(CreateMember::class)
            ->fillForm(['name' => 'Ravi', 'phone' => '9876500099', 'height_feet' => 5, 'height_inches' => 10, 'weight_kg' => 70])
            ->call('create')
            ->assertHasNoFormErrors();

        $member = Member::query()->where('phone', '9876500099')->firstOrFail();
        $this->assertSame(177.8, (float) $member->height_cm);
        $this->assertSame(22.1, $member->bmi);

        // Editing shows the stored height back in feet and inches.
        Livewire::test(EditMember::class, ['record' => $member->getKey()])
            ->assertSchemaStateSet(['height_feet' => 5, 'height_inches' => 10.0]);

        $this->get("/app/{$this->gym->slug}/members/{$member->id}")->assertOk()->assertSee('5′ 10″', false);
    }

    public function test_centimetre_gyms_keep_the_single_field(): void
    {
        Livewire::test(CreateMember::class)
            ->fillForm(['name' => 'Asha', 'phone' => '9876500098', 'height_cm' => 162])
            ->call('create')
            ->assertHasNoFormErrors();

        $member = Member::query()->where('phone', '9876500098')->firstOrFail();
        $this->assertSame(162.0, (float) $member->height_cm);
        $this->get("/app/{$this->gym->slug}/members/{$member->id}")->assertSee('162 cm');
    }

    public function test_measurements_accept_feet_and_inches(): void
    {
        $this->useFeet();
        $member = $this->createMember($this->gym);

        Livewire::test(ManageMeasurements::class)
            ->callAction('create', data: ['member_id' => $member->id, 'recorded_on' => today()->toDateString(), 'weight_kg' => 80, 'height_feet' => 6, 'height_inches' => 0])
            ->assertHasNoActionErrors();

        $this->assertSame(182.9, (float) Measurement::query()->firstOrFail()->height_cm);
    }

    public function test_public_signup_uses_the_gyms_unit(): void
    {
        $this->useFeet();
        auth()->logout();

        $this->get(route('join.show', $this->gym))->assertOk()->assertSee('Height (feet and inches)');

        $this->post(route('join.store', $this->gym), [
            'name' => 'Kiran', 'phone' => '9811122299', 'email' => 'kiran@example.com', 'password' => 'secret-pass',
            'height_feet' => 5, 'height_inches' => 4, 'consent' => '1',
        ])->assertRedirect();

        $this->assertSame(162.6, (float) Member::withoutGlobalScopes()->where('phone', '9811122299')->value('height_cm'));
    }
}
