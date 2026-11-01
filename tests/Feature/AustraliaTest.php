<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Filament\Gym\Pages\Tenancy\RegisterGym;
use App\Filament\Member\Pages\Auth\Login;
use App\Models\Gym;
use App\Models\User;
use App\Services\Messaging\PhoneNumber;
use App\Services\SubscriptionService;
use App\Support\Tenancy;
use Barryvdh\DomPDF\Facade\Pdf;
use Database\Seeders\SaasPlanSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AustraliaTest extends TestCase
{
    use RefreshDatabase;

    protected function auGym(array $attributes = []): Gym
    {
        return $this->createGym(['name' => 'Bondi Strength', 'country' => 'AU', 'abn' => '51824753556', 'gst_registered' => true, ...$attributes]);
    }

    public function test_australian_gyms_use_aud_sydney_time_and_no_upi(): void
    {
        $gym = $this->auGym();

        $this->assertSame('AUD', $gym->currency);
        $this->assertSame('Australia/Sydney', $gym->timezone);
        $this->assertSame(['cash', 'card', 'bank_transfer', 'online'], array_keys(PaymentMethod::options($gym)));
        $this->assertSame('Bank transfer / PayID', PaymentMethod::options($gym)['bank_transfer']);
        $this->assertArrayHasKey('gpay', PaymentMethod::options($this->createGym()));
    }

    public function test_prices_include_ten_percent_gst(): void
    {
        $gym = $this->auGym();
        $plan = $this->createPlan($gym, ['price' => 55, 'admission_fee' => 0, 'gst_rate' => 10]);

        $quote = app(SubscriptionService::class)->quote($gym, $plan);
        $this->assertSame(55.0, $quote['total_amount']);
        $this->assertSame(5.0, $quote['tax_amount']);
        $this->assertSame(50.0, $quote['taxable_amount']);
        $this->assertSame(0.0, $quote['cgst_amount']);
        $this->assertTrue($quote['tax_inclusive']);

        // Not registered for GST: no GST at all.
        $gym->update(['gst_registered' => false]);
        $this->assertSame(0.0, app(SubscriptionService::class)->quote($gym->fresh(), $plan)['tax_amount']);
    }

    public function test_australian_tax_invoice_shows_abn_and_included_gst(): void
    {
        $gym = $this->auGym();
        $subscription = app(SubscriptionService::class)->create($this->createMember($gym), $this->createPlan($gym, ['price' => 66, 'gst_rate' => 10]));

        $html = view('pdf.invoice', ['subscription' => $subscription->load('member', 'plan', 'payments', 'additionalMembers'), 'gym' => $gym])->render();

        $this->assertStringContainsString('TAX INVOICE', $html);
        $this->assertStringContainsString('ABN: 51 824 753 556', $html);
        $this->assertStringContainsString('Total includes GST of', $html);
        $this->assertStringContainsString('$6.00', $html);
        $this->assertStringNotContainsString('CGST', $html);
        $this->assertStringNotContainsString('SAC', $html);
        Pdf::loadHTML($html)->output();
    }

    public function test_australian_mobile_numbers(): void
    {
        $this->assertSame('61412345678', PhoneNumber::international('0412 345 678'));
        $this->assertSame('61412345678', PhoneNumber::international('412345678'));
        $this->assertSame('61412345678', PhoneNumber::international('+61 412 345 678'));
        $this->assertSame('61412345678', PhoneNumber::international('0412345678', '61'));
        $this->assertSame('919876543210', PhoneNumber::international('98765 43210'));
        $this->assertSame('919876543210', PhoneNumber::international('919876543210'));
    }

    public function test_australian_member_signs_in_with_their_mobile(): void
    {
        $gym = $this->auGym();
        $user = User::create(['name' => 'Jack', 'phone' => '0423 111 222', 'password' => 'jack-pass-1']);
        $this->createMember($gym, ['phone' => '0423 111 222', 'user_id' => $user->id]);
        Filament::setCurrentPanel('member');

        Livewire::test(Login::class)->fillForm(['login' => '0423111222', 'password' => 'jack-pass-1'])->call('authenticate')->assertHasNoFormErrors();
        $this->assertAuthenticatedAs($user);
    }

    public function test_new_australian_gym_gets_aud_starter_plans_with_gst(): void
    {
        $this->seed(SaasPlanSeeder::class);
        $owner = User::factory()->create();
        $this->actingAs($owner);
        Filament::setCurrentPanel('gym');

        Livewire::test(RegisterGym::class)
            ->fillForm(['name' => 'Perth Power', 'phone' => '0400000000', 'country' => 'AU', 'timezone' => 'Australia/Perth', 'abn' => '51 824 753 556', 'gst_registered' => true, 'starter_plans' => ['monthly']])
            ->call('register')
            ->assertHasNoFormErrors();

        $gym = Gym::query()->where('name', 'Perth Power')->sole();
        Tenancy::setGym($gym);
        $plan = $gym->membershipPlans()->sole();

        $this->assertSame(['AU', 'AUD', 'Australia/Perth', '51824753556'], [$gym->country, $gym->currency, $gym->timezone, $gym->abn]);
        $this->assertSame([60.0, 10.0], [(float) $plan->price, (float) $plan->gst_rate]);
    }
}
