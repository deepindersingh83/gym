<?php

namespace App\Http\Middleware;

use App\Support\CurrentMember;
use App\Support\Tenancy;
use Closure;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentColor;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Limits the member app to the signed-in member's own gym and applies its branding. */
class ApplyMemberContext
{
    public function handle(Request $request, Closure $next): Response
    {
        CurrentMember::set(null);
        $member = CurrentMember::get();

        abort_unless($member, 403, 'No member profile is linked to this account.');

        Tenancy::setGym($member->gym);
        FilamentTimezone::set($member->gym->timezone ?: config('app.timezone'));

        if ($member->gym->primary_color) {
            FilamentColor::register(['primary' => Color::hex($member->gym->primary_color)]);
        }

        return $next($request);
    }
}
