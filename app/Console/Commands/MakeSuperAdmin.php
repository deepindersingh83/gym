<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('gym:make-admin {email} {--name=Platform Admin}')]
#[Description('Create a super admin for /admin, or promote an existing user')]
class MakeSuperAdmin extends Command
{
    public function handle(): int
    {
        $user = User::query()->firstOrNew(['email' => strtolower($this->argument('email'))]);

        if (! $user->exists) {
            $user->name = $this->option('name');
            $user->password = $this->secret('Password (min 8 characters)') ?: throw new RuntimeException('A password is required.');
        }

        $user->forceFill(['is_super_admin' => true])->save();
        $this->info("{$user->email} is now a super admin. Sign in at ".url('/admin'));

        return self::SUCCESS;
    }
}
