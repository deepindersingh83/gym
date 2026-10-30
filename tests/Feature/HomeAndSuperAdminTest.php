<?php

namespace Tests\Feature;

use App\Models\SaasPlan;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class HomeAndSuperAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_shows_live_pricing(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->get('/')->assertOk()
            ->assertSee('Run your gym.', false)
            ->assertSee('Enterprise')
            ->assertSee(route('filament.gym.auth.register'));
    }

    public function test_seeding_creates_the_configured_super_admin(): void
    {
        config(['gym.super_admin.password' => 'a-strong-password']);

        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->where('email', 'admin@syberinfo.com.au')->firstOrFail();
        $this->assertTrue($admin->is_super_admin);
        $this->assertTrue(Hash::check('a-strong-password', $admin->password));

        $this->actingAs($admin)->get('/admin')->assertOk();
    }

    public function test_reseeding_keeps_the_existing_password(): void
    {
        $existing = User::factory()->create(['email' => 'admin@syberinfo.com.au', 'password' => 'original-pass']);
        config(['gym.super_admin.password' => 'different-pass']);

        $this->seed(DatabaseSeeder::class);

        $existing->refresh();
        $this->assertTrue($existing->is_super_admin);
        $this->assertTrue(Hash::check('original-pass', $existing->password));
    }

    public function test_install_command_sets_everything_up_and_can_be_rerun(): void
    {
        config(['gym.super_admin.password' => 'installer-pass']);

        // The test database is already migrated, so both runs report an update.
        $this->artisan('gym:install', ['--skip-cache' => true])
            ->expectsOutputToContain('Database connection (sqlite)')
            ->expectsOutputToContain('Super admin created: admin@syberinfo.com.au')
            ->assertSuccessful();

        $this->artisan('gym:install', ['--skip-cache' => true])
            ->expectsOutputToContain('Update complete')
            ->assertSuccessful();

        $this->assertSame(1, User::query()->where('email', 'admin@syberinfo.com.au')->where('is_super_admin', true)->count());
        $this->assertSame(3, SaasPlan::query()->count());
    }
}
