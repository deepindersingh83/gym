<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Models\Member;
use App\Models\Payment;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentsAndSignupTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoices_and_receipts_are_only_available_to_the_gym_and_the_member(): void
    {
        $gym = $this->createGym(['gstin' => '27ABCDE1234F1Z5']);
        $other = $this->createGym(['name' => 'Other Gym']);
        $memberUser = User::factory()->create();
        $member = $this->createMember($gym, ['user_id' => $memberUser->id]);
        $subscription = app(SubscriptionService::class)->create($member, $this->createPlan($gym), paidAmount: 500, paymentMethod: PaymentMethod::Cash);
        $payment = Payment::withoutGlobalScopes()->firstOrFail();

        $this->actingAs($this->ownerOf($gym))->get(route('invoices.show', $subscription))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($this->ownerOf($gym))->get(route('receipts.show', $payment))->assertOk();
        $this->actingAs($memberUser)->get(route('invoices.show', $subscription))->assertOk();
        $this->actingAs($this->ownerOf($other))->get(route('invoices.show', $subscription))->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('receipts.show', $payment))->assertForbidden();
    }

    public function test_customers_can_sign_up_on_the_gyms_public_page(): void
    {
        $gym = $this->createGym(['name' => 'Iron Temple']);
        $this->createPlan($gym, ['name' => 'Monthly Basic']);

        $this->get(route('join.show', $gym))->assertOk()->assertSee('Iron Temple')->assertSee('Monthly Basic');

        $this->post(route('join.store', $gym), [
            'name' => 'Neha Kapoor',
            'phone' => '9811122233',
            'email' => 'neha@example.com',
            'password' => 'secret-pass',
            'height_cm' => 165,
            'weight_kg' => 62,
            'health_questionnaire' => ['asthma'],
            'consent' => '1',
        ])->assertRedirect(route('join.welcome', $gym));

        $member = Member::withoutGlobalScopes()->where('phone', '9811122233')->firstOrFail();
        $this->assertSame('online', $member->source);
        $this->assertSame(['asthma'], $member->health_questionnaire);
        $this->assertSame('neha@example.com', $member->user->email);
        $this->assertAuthenticatedAs($member->user);

        $this->get('/member')->assertOk()->assertSee('Neha Kapoor');
    }

    public function test_signup_cannot_take_over_an_existing_account(): void
    {
        $gym = $this->createGym();
        User::factory()->create(['email' => 'taken@example.com']);

        $this->post(route('join.store', $gym), [
            'name' => 'Someone', 'phone' => '9811100000', 'email' => 'taken@example.com', 'password' => 'wrong-password', 'consent' => '1',
        ])->assertSessionHasErrors('email');

        $this->assertSame(0, Member::withoutGlobalScopes()->count());
    }

    public function test_signup_is_closed_when_the_gym_turns_it_off(): void
    {
        $gym = $this->createGym(['settings' => ['allow_online_signup' => false]]);

        $this->post(route('join.store', $gym), ['name' => 'X', 'phone' => '9811100001', 'email' => 'x@example.com', 'password' => 'password1', 'consent' => '1'])
            ->assertForbidden();
    }
}
