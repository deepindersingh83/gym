<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\Gyms\Pages\ListGyms;
use App\Filament\Admin\Resources\PartnerCommissions\Pages\ListPartnerCommissions;
use App\Filament\Admin\Resources\Partners\Pages\CreatePartner;
use App\Filament\Gym\Pages\Tenancy\RegisterGym;
use App\Filament\Partner\Pages\Dashboard as PartnerDashboard;
use App\Http\Middleware\CaptureReferral;
use App\Models\Gym;
use App\Models\Partner;
use App\Models\PartnerCommission;
use App\Models\SaasPayment;
use App\Models\User;
use Database\Seeders\SaasPlanSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PartnerProgrammeTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        $admin = User::factory()->create();
        $admin->forceFill(['is_super_admin' => true])->save();

        return $admin;
    }

    public function test_admin_adds_a_partner_with_a_portal_login(): void
    {
        $this->actingAs($this->admin());
        Filament::setCurrentPanel('admin');

        Livewire::test(CreatePartner::class)
            ->fillForm(['name' => 'Vikram Fitness Consulting', 'email' => 'vikram@example.com', 'commission_rate' => 25, 'password' => 'partner-pass'])
            ->call('create')
            ->assertHasNoFormErrors();

        $partner = Partner::query()->sole();
        $this->assertMatchesRegularExpression('/^VIKRAMFI\d{2}$/', $partner->code);
        $this->assertSame('vikram@example.com', $partner->user->email);
        $this->assertStringContainsString('ref='.$partner->code, $partner->referralUrl());
    }

    public function test_gym_signing_up_through_a_referral_link_is_credited_and_earns_commission(): void
    {
        $this->seed(SaasPlanSeeder::class);
        $partner = Partner::create(['name' => 'Vikram', 'email' => 'v@example.com', 'commission_rate' => 20]);

        // Visitor opens the referral link, then signs up and creates a gym.
        $this->get('/?ref='.strtolower($partner->code))->assertOk()->assertCookie(CaptureReferral::COOKIE, $partner->code);

        $owner = User::factory()->create();
        $this->actingAs($owner);
        Filament::setCurrentPanel('gym');

        Livewire::withCookies([CaptureReferral::COOKIE => $partner->code])->test(RegisterGym::class)
            ->fillForm(['name' => 'Referred Gym', 'phone' => '9000000000'])
            ->call('register')
            ->assertHasNoFormErrors();

        $gym = Gym::query()->where('name', 'Referred Gym')->sole();
        $this->assertSame($partner->id, $gym->partner_id);

        // The super admin records the gym's first yearly payment.
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->admin());
        Filament::setCurrentPanel('admin');

        Livewire::test(ListGyms::class)
            ->callTableAction('activate', $gym, data: ['saas_plan_id' => $gym->saas_plan_id, 'months' => 12, 'amount' => 9990, 'reference' => 'UTR123'])
            ->assertHasNoTableActionErrors();

        $commission = PartnerCommission::query()->sole();
        $this->assertSame(1998.0, (float) $commission->amount);
        $this->assertSame('pending', $commission->status);
        $this->assertSame('active', $gym->fresh()->status);
        $this->assertSame(9990.0, (float) SaasPayment::query()->sole()->amount);

        Livewire::test(ListPartnerCommissions::class)
            ->callTableAction('markPaid', $commission, data: ['payout_reference' => 'PAYOUT-1'])
            ->assertHasNoTableActionErrors();
        $this->assertSame('paid', $commission->fresh()->status);
    }

    public function test_gyms_without_a_partner_create_no_commission(): void
    {
        $gym = $this->createGym();

        SaasPayment::create(['gym_id' => $gym->id, 'amount' => 999, 'paid_at' => now()]);

        $this->assertSame(0, PartnerCommission::query()->count());
    }

    public function test_partner_portal_shows_link_gyms_and_commissions_only_to_partners(): void
    {
        $user = User::factory()->create();
        $partner = Partner::create(['name' => 'Vikram', 'email' => $user->email, 'user_id' => $user->id]);
        $gym = $this->createGym(['name' => 'Partner Gym', 'partner_id' => $partner->id]);
        $this->createGym(['name' => 'Someone Else Gym']);
        SaasPayment::create(['gym_id' => $gym->id, 'amount' => 1000, 'paid_at' => now()]);
        $this->assertSame(200.0, (float) PartnerCommission::query()->sole()->amount);

        $this->actingAs($user);
        $this->get('/partner')->assertRedirect('/partner/dashboard');
        $this->get('/partner/dashboard')->assertOk()->assertSee($partner->code);
        Filament::setCurrentPanel('partner');
        Livewire::test(PartnerDashboard::class)->assertSee('Partner Gym')->assertDontSee('Someone Else Gym');
        $this->get('/partner/commissions')->assertOk();

        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->actingAs(User::factory()->create())->get('/partner/dashboard')->assertForbidden();
    }
}
