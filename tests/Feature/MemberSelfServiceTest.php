<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Filament\Member\Pages\MyDetails;
use App\Filament\Member\Pages\MyReceipts;
use App\Models\Gym;
use App\Models\Member;
use App\Models\Payment;
use App\Models\User;
use App\Services\SubscriptionService;
use App\Support\Tenancy;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MemberSelfServiceTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected Member $member;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gym = $this->createGym();
        $this->user = User::factory()->create();
        $this->member = $this->createMember($this->gym, ['user_id' => $this->user->id, 'name' => 'Kavya Iyer', 'phone' => '9876501234', 'height_cm' => 160]);
        $this->actingAs($this->user);
        Filament::setCurrentPanel('member');
        Tenancy::setGym($this->gym);
    }

    public function test_member_sees_and_updates_their_own_details(): void
    {
        $this->get('/member/my-details')->assertOk()->assertSee('Kavya Iyer')->assertSee('9876501234');

        Livewire::test(MyDetails::class)
            ->assertSchemaStateSet(['height_cm' => '160.0'])
            ->fillForm([
                'address' => '22 Hill Road',
                'emergency_contact_name' => 'Ravi Iyer',
                'emergency_contact_phone' => '9876500000',
                'health_questionnaire' => ['asthma'],
                'goal' => 'weight_loss',
                'height_cm' => 162,
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified('Your details are saved');

        $member = $this->member->fresh();
        $this->assertSame('22 Hill Road', $member->address);
        $this->assertSame('Ravi Iyer', $member->emergency_contact_name);
        $this->assertSame(['asthma'], $member->health_questionnaire);
        $this->assertSame(162.0, (float) $member->height_cm);
    }

    public function test_member_cannot_change_details_only_the_gym_controls(): void
    {
        Livewire::test(MyDetails::class)
            ->set('data.name', 'Someone Else')
            ->set('data.phone', '9000000000')
            ->set('data.trainer_id', 99)
            ->set('data.address', 'New address')
            ->call('save');

        $member = $this->member->fresh();
        $this->assertSame('Kavya Iyer', $member->name);
        $this->assertSame('9876501234', $member->phone);
        $this->assertNull($member->trainer_id);
        $this->assertSame('New address', $member->address);
    }

    public function test_member_sees_their_receipts_only(): void
    {
        $service = app(SubscriptionService::class);
        $service->create($this->member, $this->createPlan($this->gym), paidAmount: 700, paymentMethod: PaymentMethod::GPay);
        $other = $this->createMember($this->gym);
        $service->create($other, $this->createPlan($this->gym), paidAmount: 999, paymentMethod: PaymentMethod::Cash);

        $mine = Payment::withoutGlobalScopes()->where('member_id', $this->member->id)->sole();
        $theirs = Payment::withoutGlobalScopes()->where('member_id', $other->id)->sole();

        $this->get('/member/my-receipts')->assertOk();
        Livewire::test(MyReceipts::class)->assertCanSeeTableRecords([$mine])->assertCanNotSeeTableRecords([$theirs]);

        $this->get(route('receipts.show', $mine))->assertOk();
        $this->get(route('receipts.show', $theirs))->assertForbidden();
    }
}
