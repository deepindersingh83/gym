<?php

namespace Tests\Feature;

use App\Filament\Gym\Resources\Members\Pages\CreateMember;
use App\Filament\Gym\Resources\Members\Pages\ViewMember;
use App\Filament\Member\Pages\Auth\Login;
use App\Models\Gym;
use App\Models\Member;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Gyms without any WhatsApp / SMS: members use mobile number + password. */
class PasswordOnlyGymTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected function setUp(): void
    {
        parent::setUp();

        config(['gym.whatsapp.token' => null, 'gym.whatsapp.phone_number_id' => null, 'gym.sms.msg91_auth_key' => null]);
        $this->gym = $this->createGym();
    }

    public function test_code_sign_in_is_hidden_without_whatsapp_or_sms(): void
    {
        $this->get('/member/login')->assertOk()
            ->assertDontSee('Sign in with a one-time code')
            ->assertSee('Ask your gym', false);
        $this->get('/member/login-code')->assertRedirect('/member/login');
    }

    public function test_code_sign_in_appears_once_the_platform_has_whatsapp(): void
    {
        config(['gym.whatsapp.token' => 'platform', 'gym.whatsapp.phone_number_id' => '1']);

        $this->get('/member/login')->assertOk()->assertSee('Sign in with a one-time code');
        $this->get('/member/login-code')->assertOk();
    }

    public function test_a_gym_with_its_own_whatsapp_offers_codes_on_its_own_domain(): void
    {
        config(['app.url' => 'https://gym.syberinfo.com.au']);
        $this->gym->forceFill([
            'custom_domain' => 'members.fitzone.in', 'custom_domain_status' => 'active',
            'integrations' => ['whatsapp_token' => 'own', 'whatsapp_phone_number_id' => '9'],
        ])->save();

        $this->get('https://members.fitzone.in/member/login')->assertOk()->assertSee('Sign in with a one-time code');
        $this->get('https://gym.syberinfo.com.au/member/login')->assertOk()->assertDontSee('Sign in with a one-time code');
    }

    public function test_front_desk_sets_the_app_password_when_adding_a_member(): void
    {
        $this->actingAs($this->ownerOf($this->gym));
        Filament::setCurrentPanel('gym');
        Filament::setTenant($this->gym);

        Livewire::test(CreateMember::class)
            ->fillForm(['name' => 'Desk Member', 'phone' => '9811122200', 'app_password' => 'desk-pass-123'])
            ->call('create')
            ->assertHasNoFormErrors();

        $member = Member::query()->where('phone', '9811122200')->sole();
        $this->assertNotNull($member->user_id);
        $this->assertNull($member->user->email);

        // Phone-only member + no messaging: invite is disabled, Set app password is the way.
        Livewire::test(ViewMember::class, ['record' => $member->getKey()])
            ->assertActionDisabled('invite')
            ->assertActionEnabled('setAppPassword');

        $this->flushSession();
        $this->app['auth']->forgetGuards();
        Filament::setCurrentPanel('member');
        Livewire::test(Login::class)->fillForm(['login' => '98111 22200', 'password' => 'desk-pass-123'])->call('authenticate')->assertHasNoFormErrors();
        $this->assertAuthenticatedAs($member->user);
    }
}
