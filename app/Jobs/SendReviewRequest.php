<?php

namespace App\Jobs;

use App\Enums\MessageEvent;
use App\Models\Attendance;
use App\Models\Member;
use App\Services\Messaging\Messenger;
use App\Support\Tenancy;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Asks a member for a Google review once they reach the gym's check-in milestone. */
class SendReviewRequest implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $memberId) {}

    public function handle(Messenger $messenger): void
    {
        $member = Member::withoutGlobalScopes()->with(['gym', 'user'])->find($this->memberId);
        $gym = $member?->gym;

        if (! $gym || ! $gym->hasModule('google_reviews') || ! $gym->setting('review_request_enabled') || blank($gym->setting('google_review_url'))) {
            return;
        }

        Tenancy::forGym($gym, function () use ($member, $gym, $messenger) {
            $visits = Attendance::query()->where('member_id', $member->id)->count();

            if ($visits >= max(2, (int) $gym->setting('review_request_after_visits'))) {
                // The dedupe key makes this a once-per-member message.
                $messenger->send($member, MessageEvent::ReviewRequest, ['visits' => $visits], dedupeKey: "review:{$member->id}");
            }
        });
    }
}
