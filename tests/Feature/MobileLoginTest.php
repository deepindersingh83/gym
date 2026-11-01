<?php

namespace Tests\Feature;

use App\Enums\MessageEvent;
use App\Filament\Gym\Resources\Members\Pages\ViewMember;
use App\Filament\Member\Pages\Auth\EditProfile;
use App\Filament\Member\Pages\Auth\Login;
use App\Filament\Member\Pages\Auth\LoginWithCode;
use App\Models\Gym;
use App\Models\Member;
use App\Models\MessageLog;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

class MobileLoginTest extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config(['gym.whatsapp.token' => 'platform-token', 'gym.whatsapp.phone_number_id' => '123']);
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'w1']]])]);
        $this->gym = $this->createGym(['name' => 'Iron Temple', 'message_credits' => 0]);
        $this->user = User::create(['name' => 'Kavya', 'email' => null, 'phone' => '98765 01234', 'password' => 'kavya-pass-1']);
        $this->createMember($this->gym, ['name' => 'Kavya', 'phone' => '9876501234', 'user_id' => $this->user->id]);
        Filament::setCurrentPanel('member');
    }

    protected function sentCode(): string
    {
        $request = collect(Http::recorded())->map(fn ($pair) => $pair[0])->last(fn ($r) => str_contains($r->url(), 'graph.facebook.com'));
        preg_match('/\b(\d{6})\b/', $request['text']['body'], $m);

        return $m[1];
    }

    public function test_members_sign_in_with_their_mobile_number_in_any_format(): void
    {
        foreach (['9876501234', '+91 98765 01234', '098765-01234'] as $typed) {
            Livewire::test(Login::class)
                ->fillForm(['login' => $typed, 'password' => 'kavya-pass-1'])
                ->call('authenticate')
                ->assertHasNoFormErrors();

            $this->assertAuthenticatedAs($this->user);
            Filament::auth()->logout();
        }

        Livewire::test(Login::class)
            ->fillForm(['login' => '9876501234', 'password' => 'wrong'])
            ->call('authenticate')
            ->assertHasFormErrors(['login']);
        $this->assertGuest();
    }

    public function test_email_still_works_and_staff_cannot_use_the_member_app(): void
    {
        $this->user->update(['email' => 'kavya@example.com']);
        Livewire::test(Login::class)->fillForm(['login' => 'KAVYA@example.com', 'password' => 'kavya-pass-1'])->call('authenticate')->assertHasNoFormErrors();
        $this->assertAuthenticatedAs($this->user);
        Filament::auth()->logout();

        $owner = $this->ownerOf($this->gym);
        $owner->update(['phone' => '9000011111', 'password' => 'owner-pass-1']);
        Livewire::test(Login::class)->fillForm(['login' => '9000011111', 'password' => 'owner-pass-1'])->call('authenticate')->assertHasFormErrors(['login']);
        $this->assertGuest();
    }

    public function test_one_time_code_by_whatsapp(): void
    {
        $this->get('/member/login')->assertOk()->assertSee('Sign in with a one-time code');
        $this->get('/member/login-code')->assertOk();

        $page = Livewire::test(LoginWithCode::class)
            ->fillForm(['phone' => '+91 98765 01234'])
            ->call('sendCode')
            ->assertHasNoFormErrors()
            ->assertSet('codeSent', true);

        $code = $this->sentCode();
        $log = MessageLog::withoutGlobalScopes()->where('event', MessageEvent::LoginCode)->sole();
        $this->assertStringNotContainsString($code, $log->body, 'staff must not see the code');
        $this->assertSame('sent', $log->status, 'login codes work even with 0 credits');

        $page->fillForm(['code' => '000000'])->call('verify')->assertHasFormErrors(['code']);
        $this->assertGuest();

        $page->fillForm(['code' => $code])->call('verify')->assertRedirect();
        $this->assertAuthenticatedAs($this->user);

        // The code can't be used twice.
        Filament::auth()->logout();
        Livewire::test(LoginWithCode::class)->set('codeSent', true)->fillForm(['phone' => '9876501234', 'code' => $code])->call('verify')->assertHasFormErrors(['code']);
    }

    public function test_codes_lock_after_five_wrong_tries_and_sending_is_rate_limited(): void
    {
        $page = Livewire::test(LoginWithCode::class)->fillForm(['phone' => '9876501234'])->call('sendCode');
        $code = $this->sentCode();

        foreach (range(1, 5) as $i) {
            $page->fillForm(['code' => '111111'])->call('verify');
        }
        $page->fillForm(['code' => $code])->call('verify')->assertHasFormErrors(['code']);
        $this->assertGuest();

        RateLimiter::clear('login-code:phone:919876501234');
        foreach (range(1, 3) as $i) {
            Livewire::test(LoginWithCode::class)->fillForm(['phone' => '9876501234'])->call('sendCode')->assertHasNoFormErrors();
        }
        Livewire::test(LoginWithCode::class)->fillForm(['phone' => '9876501234'])->call('sendCode')->assertHasFormErrors(['phone']);
    }

    public function test_unknown_numbers_get_the_same_answer_and_no_message(): void
    {
        Livewire::test(LoginWithCode::class)->fillForm(['phone' => '9111111111'])->call('sendCode')->assertHasNoFormErrors()->assertSet('codeSent', true);

        $this->assertSame(0, MessageLog::withoutGlobalScopes()->count());
    }

    public function test_after_a_code_sign_in_the_member_sets_a_password_without_the_old_one(): void
    {
        $this->actingAs($this->user);
        session([EditProfile::SESSION_SIGNED_IN_WITH_CODE => true]);

        Livewire::test(EditProfile::class)
            ->fillForm(['password' => 'Brand-New-Pass-9', 'passwordConfirmation' => 'Brand-New-Pass-9'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue(Hash::check('Brand-New-Pass-9', $this->user->fresh()->password));
        $this->assertNull($this->user->fresh()->email);
    }

    public function test_staff_invite_and_set_a_password_for_a_phone_only_member(): void
    {
        $this->gym->update(['message_credits' => 10]);
        $member = $this->createMember($this->gym, ['name' => 'Ramesh', 'phone' => '9811100077', 'email' => null]);
        $this->actingAs($this->ownerOf($this->gym));
        Filament::setCurrentPanel('gym');
        Filament::setTenant($this->gym);

        Livewire::test(ViewMember::class, ['record' => $member->getKey()])->callAction('invite')->assertNotified('Invite sent');

        $user = $member->fresh()->user;
        $this->assertSame('919811100077', $user->phone_e164);
        $this->assertNull($user->email);
        $invite = MessageLog::withoutGlobalScopes()->where('event', MessageEvent::AppInvite)->sole();
        $this->assertStringContainsString('/member/login', $invite->body);

        Livewire::test(ViewMember::class, ['record' => $member->getKey()])
            ->callAction('setAppPassword', data: ['password' => 'desk-set-pass'])
            ->assertHasNoActionErrors();
        $this->assertTrue(Hash::check('desk-set-pass', $user->fresh()->password));
    }

    public function test_signup_without_email_then_sign_in_with_mobile(): void
    {
        $this->post(route('join.store', $this->gym), [
            'name' => 'No Email', 'phone' => '9822033333', 'password' => 'my-secret-1', 'consent' => '1',
        ])->assertRedirect(route('join.welcome', $this->gym));

        $member = Member::withoutGlobalScopes()->where('phone', '9822033333')->sole();
        $this->assertNull($member->user->email);

        Filament::auth()->logout();
        Livewire::test(Login::class)->fillForm(['login' => '98220 33333', 'password' => 'my-secret-1'])->call('authenticate')->assertHasNoFormErrors();
        $this->assertAuthenticatedAs($member->user);
    }
}
