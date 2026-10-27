<?php

namespace App\Filament\Member\Widgets;

use App\Support\CurrentMember;
use chillerlan\QRCode\QRCode;
use Filament\Widgets\Widget;

/** Membership status and the QR code the member shows at the front desk. */
class MembershipCard extends Widget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.member.membership-card';

    protected function getViewData(): array
    {
        $member = CurrentMember::get()->load(['currentSubscription.plan', 'latestSubscription.plan', 'trainer']);

        return [
            'member' => $member,
            'subscription' => $member->currentSubscription ?? $member->latestSubscription,
            'qr' => (new QRCode)->render($member->check_in_code),
        ];
    }
}
