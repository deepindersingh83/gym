<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Auth\MultiFactor\Http\Middleware\EnsureMultiFactorAuthenticationIsEnabled;
use Illuminate\Http\Request;

/** Sends admins without an authenticator app to set one up (can be switched off with ADMIN_REQUIRE_2FA=false). */
class RequireAdminTwoFactor extends EnsureMultiFactorAuthenticationIsEnabled
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (! config('gym.admin.require_two_factor')) {
            return $next($request);
        }

        return parent::handle($request, $next);
    }
}
