<?php

namespace App\Support;

use App\Models\Member;

/**
 * Short, unguessable "renew now" link for one member, used in WhatsApp / SMS reminders,
 * e.g. https://app.example.com/r/2s-9f3a1c7b2e. It never expires but only ever renews that member.
 */
class RenewLink
{
    public static function for(Member $member): string
    {
        return route('renew.link', ['code' => static::code($member->id)]);
    }

    public static function code(int $memberId): string
    {
        return base_convert((string) $memberId, 10, 36).'-'.static::signature($memberId);
    }

    public static function resolve(string $code): ?Member
    {
        [$id36, $signature] = array_pad(explode('-', $code, 2), 2, '');
        $id = (int) base_convert($id36, 36, 10);

        if ($id <= 0 || ! hash_equals(static::signature($id), $signature)) {
            return null;
        }

        return Member::withoutGlobalScopes()->with('gym')->whereNull('erased_at')->find($id);
    }

    protected static function signature(int $memberId): string
    {
        return substr(hash_hmac('sha256', 'renew:'.$memberId, (string) config('app.key')), 0, 10);
    }
}
