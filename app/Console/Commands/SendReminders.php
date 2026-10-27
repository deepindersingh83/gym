<?php

namespace App\Console\Commands;

use App\Enums\MessageEvent;
use App\Enums\SubscriptionStatus;
use App\Models\Gym;
use App\Models\Member;
use App\Models\MessageLog;
use App\Models\Subscription;
use App\Services\Messaging\Messenger;
use App\Services\SubscriptionService;
use App\Support\Tenancy;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Daily job: expires lapsed memberships, then sends renewal, expiry, pending
 * dues, birthday and anniversary messages for every gym with an active plan.
 */
#[Signature('gym:send-reminders {--gym= : Only this gym (slug)}')]
#[Description('Expire lapsed memberships and send renewal, dues, birthday and anniversary reminders')]
class SendReminders extends Command
{
    public function handle(Messenger $messenger, SubscriptionService $subscriptions): int
    {
        $expired = $subscriptions->expireLapsed();
        $this->info("Marked {$expired} memberships as expired.");

        Gym::query()
            ->when($this->option('gym'), fn ($q, $slug) => $q->where('slug', $slug))
            ->get()
            ->filter->hasActiveSaasSubscription()
            ->each(fn (Gym $gym) => Tenancy::forGym($gym, fn () => $this->remindGym($gym, $messenger)));

        return self::SUCCESS;
    }

    protected function remindGym(Gym $gym, Messenger $messenger): void
    {
        $counts = array_fill_keys(['renewal', 'expired', 'dues', 'birthday', 'anniversary'], 0);
        $today = today();

        // Upcoming renewals, e.g. 7, 3 and 1 day before the end date.
        foreach ((array) $gym->setting('reminder_days_before') as $days) {
            $date = $today->copy()->addDays((int) $days);

            Subscription::query()->active()->whereDate('end_date', $date)->with(['member.user', 'member.gym', 'plan'])->get()
                ->reject(fn (Subscription $s) => $s->auto_renew || $this->hasLaterMembership($s))
                ->each(function (Subscription $s) use ($messenger, $days, &$counts) {
                    $counts['renewal'] += $messenger->send($s->member, MessageEvent::RenewalReminder, subscription: $s, dedupeKey: "renewal:{$s->id}:{$days}");
                });
        }

        // Memberships that ended yesterday and were not renewed.
        Subscription::query()->where('status', SubscriptionStatus::Expired)->whereDate('end_date', $today->copy()->subDay())
            ->with(['member.user', 'member.gym', 'plan'])->get()
            ->reject(fn (Subscription $s) => $this->hasLaterMembership($s))
            ->each(function (Subscription $s) use ($messenger, &$counts) {
                $counts['expired'] += $messenger->send($s->member, MessageEvent::Expired, subscription: $s, dedupeKey: "expired:{$s->id}");
            });

        // Pending dues, repeated every N days.
        $every = max(1, (int) $gym->setting('dues_reminder_every_days'));
        Subscription::query()->withDues()->with(['member.user', 'member.gym', 'plan'])->get()
            ->each(function (Subscription $s) use ($messenger, $every, $today, &$counts) {
                $recent = MessageLog::query()->where('subscription_id', $s->id)->where('event', MessageEvent::DuesReminder)
                    ->where('created_at', '>', $today->copy()->subDays($every))->exists();

                if (! $recent) {
                    $counts['dues'] += $messenger->send($s->member, MessageEvent::DuesReminder, subscription: $s, dedupeKey: "dues:{$s->id}:{$today->toDateString()}");
                }
            });

        foreach (['birthday' => 'date_of_birth', 'anniversary' => 'anniversary_date'] as $kind => $column) {
            if (! $gym->setting("send_{$kind}_wishes")) {
                continue;
            }

            Member::query()->where('is_active', true)->whereMonth($column, $today->month)->whereDay($column, $today->day)
                ->with(['user', 'gym'])->get()
                ->each(function (Member $member) use ($messenger, $kind, $today, &$counts) {
                    $event = $kind === 'birthday' ? MessageEvent::Birthday : MessageEvent::Anniversary;
                    $counts[$kind] += $messenger->send($member, $event, dedupeKey: "{$kind}:{$member->id}:{$today->year}");
                });
        }

        $this->line(sprintf('%s: %s', $gym->name, collect($counts)->map(fn ($n, $k) => "{$k} {$n}")->implode(', ')));
    }

    protected function hasLaterMembership(Subscription $subscription): bool
    {
        return Subscription::query()
            ->where('member_id', $subscription->member_id)
            ->where('status', '!=', SubscriptionStatus::Cancelled)
            ->whereDate('end_date', '>', $subscription->end_date)
            ->exists();
    }
}
