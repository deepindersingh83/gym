<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Creates the platform super admin (login for /admin) from SUPER_ADMIN_EMAIL.
 * The password comes from SUPER_ADMIN_PASSWORD; if that is empty a random one
 * is generated and printed once. An existing user is promoted without
 * changing their password.
 */
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = strtolower((string) config('gym.super_admin.email'));

        if ($email === '') {
            return;
        }

        $user = User::query()->firstOrNew(['email' => $email]);

        if (! $user->exists) {
            $password = config('gym.super_admin.password') ?: Str::password(16, symbols: false);
            $user->fill(['name' => config('gym.super_admin.name'), 'password' => $password]);

            if (! config('gym.super_admin.password')) {
                $this->command?->newLine();
                $this->command?->warn('  ┌──────────────────────────────────────────────────────────┐');
                $this->command?->warn("    Super admin created: {$email}");
                $this->command?->warn("    Password:            {$password}");
                $this->command?->warn('    Copy it now — it is not shown again. Change it after');
                $this->command?->warn('    signing in at '.url('/admin').' (top-right menu → Profile).');
                $this->command?->warn('  └──────────────────────────────────────────────────────────┘');
                $this->command?->newLine();
            } else {
                $this->command?->info("  Super admin created: {$email} (password from SUPER_ADMIN_PASSWORD)");
            }
        }

        $user->forceFill(['is_super_admin' => true])->save();
    }
}
