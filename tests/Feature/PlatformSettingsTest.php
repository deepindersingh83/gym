<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Filament\Admin\Pages\Settings;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\Payments\PlatformBilling;
use App\Services\PlatformSettings;
use Database\Seeders\SaasPlanSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class PlatformSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(AdminRole $role = AdminRole::Owner): User
    {
        $user = User::factory()->create();
        $user->forceFill(['admin_role' => $role])->save();

        return $user;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SaasPlanSeeder::class);
        Filament::setCurrentPanel('admin');
    }

    public function test_owner_changes_settings_and_they_apply_immediately(): void
    {
        $this->actingAs($this->admin());
        $this->get('/admin/settings')->assertOk();

        Livewire::test(Settings::class)
            ->fillForm([
                'app' => ['name' => 'FitCloud'],
                'gym' => [
                    'trial_days' => 21,
                    'whatsapp' => ['token' => 'EAAG-secret-token', 'phone_number_id' => '1098'],
                    'billing' => ['entities' => ['AU' => ['legal_name' => 'Syber Info Pty Ltd', 'tax_id' => '51824753556', 'tax_rate' => 10, 'invoice_prefix' => 'GF-AU']]],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified('Settings saved');

        $this->assertSame('FitCloud', config('app.name'));
        $this->assertSame(21, (int) config('gym.trial_days'));
        $this->assertSame('EAAG-secret-token', config('gym.whatsapp.token'));
        $this->assertSame('Syber Info Pty Ltd', config('gym.billing.entities.AU.legal_name'));

        // Stored encrypted, never in plain text.
        $raw = DB::table('platform_settings')->where('key', 'gym.whatsapp.token')->value('value');
        $this->assertStringNotContainsString('EAAG-secret-token', $raw);

        // A fresh request picks the saved values up from the database.
        config(['gym.trial_days' => 14]);
        Cache::forget(PlatformSettings::CACHE_KEY);
        PlatformSettings::apply();
        $this->assertSame(21, (int) config('gym.trial_days'));
    }

    public function test_secrets_are_never_shown_and_empty_keeps_the_saved_one(): void
    {
        $this->actingAs($this->admin());
        PlatformSettings::save(['gym' => ['whatsapp' => ['token' => 'keep-me']]] + PlatformSettings::formState());

        Livewire::test(Settings::class)
            ->assertSchemaStateSet(['gym.whatsapp.token' => null])
            ->fillForm(['gym' => ['trial_days' => 30]])
            ->call('save');

        $this->assertSame('keep-me', PlatformSetting::query()->where('key', 'gym.whatsapp.token')->value('value'));
    }

    public function test_credit_packs_are_priced_per_currency(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(Settings::class)
            ->fillForm(['gym' => ['credits' => ['packs' => [['credits' => 1000, 'INR' => 499, 'AUD' => null]]]]])
            ->call('save')
            ->assertHasNoFormErrors();

        $billing = app(PlatformBilling::class);
        $this->assertSame([1000 => 499.0], $billing->packs('INR'));
        $this->assertSame([], $billing->packs('AUD'));
    }

    public function test_only_the_platform_owner_can_change_settings(): void
    {
        $this->actingAs($this->admin(AdminRole::Support))->get('/admin/settings')->assertForbidden();
    }
}
