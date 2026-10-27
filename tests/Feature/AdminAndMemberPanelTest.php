<?php

namespace Tests\Feature;

use App\Filament\Member\Widgets\MembershipCard;
use App\Models\Gym;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminAndMemberPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DemoSeeder::class);
    }

    public function test_super_admin_manages_all_gyms(): void
    {
        $this->actingAs(User::query()->where('email', 'admin@example.com')->first());

        $this->get('/admin')->assertOk();
        $this->get('/admin/gyms')->assertOk()->assertSee('FitZone Gym');
        $this->get('/admin/saas-plans')->assertOk()->assertSee('Enterprise');
        $this->get('/admin/gyms/'.Gym::query()->first()->id.'/edit')->assertOk();
    }

    public function test_gym_owners_cannot_open_the_admin_panel(): void
    {
        $this->actingAs(User::query()->where('email', 'owner@example.com')->first());

        $this->get('/admin')->assertForbidden();
    }

    public function test_member_app_shows_membership_qr_payments_progress_and_plans(): void
    {
        $this->actingAs(User::query()->where('email', 'member@example.com')->first());

        $this->get('/member')->assertOk()->assertSee('My membership');

        Filament::setCurrentPanel('member');
        Livewire::test(MembershipCard::class)->assertSee('Aarav Patel')->assertSee('Check-in QR code')->assertSee('data:image/svg+xml;base64', false);

        $this->get('/member/my-payments')->assertOk();
        $this->get('/member/my-progress')->assertOk();
        $this->get('/member/my-plans')->assertOk()->assertSee('Beginner full body');
    }

    public function test_members_cannot_open_the_gym_staff_panel(): void
    {
        $this->actingAs(User::query()->where('email', 'member@example.com')->first());

        $this->get('/app')->assertForbidden();
    }
}
