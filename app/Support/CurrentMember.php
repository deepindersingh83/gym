<?php

namespace App\Support;

use App\Models\Member;

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

        $profiles = $user->memberProfiles()->whereNull('deleted_at')->with('gym')->get();

        return static::$member = $profiles->firstWhere('id', session('member_profile_id')) ?? $profiles->first();
    }

    public static function set(?Member $member): void
    {
        static::$member = $member;
    }
}
