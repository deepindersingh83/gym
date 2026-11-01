<?php

namespace App\Support;

use App\Models\Member;
use App\Services\CustomDomains;

/** The member profile the signed-in member is looking at in the member app. */
class CurrentMember
{
    protected static ?Member $member = null;

    public static function get(): ?Member
    {
        if (static::$member) {
            return static::$member;
        }

        $user = auth()->user();

        if (! $user) {
            return null;
        }

        // Only gyms whose plan includes the member app.
        $profiles = $user->memberProfiles()->whereNull('deleted_at')->with('gym.saasPlan')->get()
            ->filter(fn ($member) => $member->gym?->hasModule('member_app'))->values();

        // On a gym's own domain, show that gym's membership.
        $domainGymId = CustomDomains::current()?->id;

        return static::$member = ($domainGymId ? $profiles->firstWhere('gym_id', $domainGymId) : null)
            ?? $profiles->firstWhere('id', session('member_profile_id'))
            ?? $profiles->first();
    }

    public static function set(?Member $member): void
    {
        static::$member = $member;
    }
}
