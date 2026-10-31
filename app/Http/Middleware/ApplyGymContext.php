<?php

namespace App\Http\Middleware;

use App\Models\Gym;
use App\Support\Tenancy;
use Closure;
use Filament\Facades\Filament;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentColor;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Sets the current gym and applies its brand colour to the panel. */
class ApplyGymContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $gym = Filament::getTenant();

        if ($gym instanceof Gym) {
            Tenancy::setGym($gym);

            // Usage tracking for the platform owner; support visits by the super admin don't count.
            if (! $request->user()?->is_super_admin && (! $gym->last_activity_at || $gym->last_activity_at->lt(now()->subMinutes(5)))) {
                $gym->forceFill(['last_activity_at' => now()])->saveQuietly();
            }

            if ($gym->primary_color) {
                FilamentColor::register(['primary' => Color::hex($gym->primary_color)]);
            }
        }

        return $next($request);
    }
}
