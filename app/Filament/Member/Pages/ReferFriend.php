<?php

namespace App\Filament\Member\Pages;

use App\Models\MemberReferral;
use App\Services\Referrals;
use App\Support\CurrentMember;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

/** The member's invite link and the free days they have earned. */
class ReferFriend extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGift;

    protected static ?string $title = 'Refer a friend';

    protected static ?int $navigationSort = 6;

    protected string $view = 'filament.member.pages.refer-friend';

    public static function canAccess(): bool
    {
        $member = CurrentMember::get();

        return $member !== null && app(Referrals::class)->enabled($member->gym);
    }

    protected function getViewData(): array
    {
        $member = CurrentMember::get();
        $gym = $member->gym;
        $link = app(Referrals::class)->linkFor($member);
        $referrerDays = (int) $gym->setting('referral_referrer_days');
        $friendDays = (int) $gym->setting('referral_friend_days');

        return [
            'gym' => $gym,
            'code' => $member->fresh()->referral_code,
            'link' => $link,
            'referrerDays' => $referrerDays,
            'friendDays' => $friendDays,
            'whatsapp' => 'https://wa.me/?text='.rawurlencode("Join me at {$gym->name}!".($friendDays ? " You get {$friendDays} free days with my link: " : ' ').$link),
            'referrals' => MemberReferral::withoutGlobalScopes()->where('referrer_id', $member->id)->with('referred')->latest()->get()
                ->map(fn (MemberReferral $r) => [
                    'name' => Str::of($r->referred?->name ?? 'Friend')->before(' ')->toString(),
                    'joined' => $r->created_at,
                    'rewarded' => $r->status === 'rewarded',
                    'days' => $r->referrer_days,
                ]),
            'bonusDays' => (int) $member->bonus_days,
        ];
    }
}
