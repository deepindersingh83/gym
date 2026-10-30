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
                $this->command?->warn("Super admin {$email} created with password: {$password}");
                $this->command?->warn('Sign in at '.url('/admin').' and change it under your profile.');
            }
        }

        $user->forceFill(['is_super_admin' => true])->save();
    }
}
