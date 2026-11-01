<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/** For an admin who lost their phone and recovery codes: they set up the authenticator again at next sign-in. */
class ResetTwoFactor extends Command
{
    protected $signature = 'gym:reset-2fa {email}';

    protected $description = 'Turn off two-step login for a user so they can set it up again';

    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('No user with that email.');

            return self::FAILURE;
        }

        $user->forceFill(['app_authentication_secret' => null, 'app_authentication_recovery_codes' => null])->save();
        activity('admin')->performedOn($user)->log('Two-step login reset from the server');
        $this->info("Two-step login reset for {$user->email}. They will be asked to set it up again when they sign in.");

        return self::SUCCESS;
    }
}
