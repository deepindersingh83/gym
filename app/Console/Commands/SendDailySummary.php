<?php

namespace App\Console\Commands;

use App\Filament\Gym\Pages\Reports;
use App\Models\Gym;
use App\Services\Billing\GymBilling;
use App\Services\GymReports;
use App\Support\Money;
use App\Support\Tenancy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/** Emails each gym owner the day's numbers at their chosen hour (gym's own time zone). Runs hourly. */
class SendDailySummary extends Command
{
    protected $signature = 'gym:daily-summary {--gym= : Only this gym (slug)} {--now : Send now, whatever the hour}';

    protected $description = "Email gym owners today's collections, sign-ups, check-ins and what needs attention";

    public function handle(GymBilling $billing): int
    {
        $sent = 0;

        Gym::query()
            ->when($this->option('gym'), fn ($q, $slug) => $q->where('slug', $slug))
            ->get()
            ->filter(fn (Gym $gym) => $gym->hasActiveSaasSubscription() && $gym->setting('daily_summary_enabled', true))
            ->each(function (Gym $gym) use ($billing, &$sent) {
                $local = now()->setTimezone($gym->timezone ?: config('app.timezone'));

                if (! $this->option('now') && $local->hour !== (int) $gym->setting('daily_summary_hour', 21)) {
                    return;
                }

                // Once per gym per day, even if the scheduler runs twice.
                if (! $this->option('now') && ! Cache::add("daily-summary:{$gym->id}:{$local->toDateString()}", true, now()->addDays(2))) {
                    return;
                }

                $day = Tenancy::forGym($gym, fn () => (new GymReports($gym))->daily($local));
                $m = fn ($v) => Money::format($v, $gym->currency);
                $methods = collect($day['by_method'])->map(fn ($v, $k) => "{$k} {$m($v)}")->implode(', ');

                $body = "Here is {$day['date']} at {$gym->name}:\n\n"
                    ."Collected: {$m($day['collected'])}".($methods ? " ({$methods})" : '')."\n"
                    ."New members: {$day['new_members']}\n"
                    ."Memberships sold: {$day['memberships_sold']}\n"
                    ."Check-ins: {$day['check_ins']}\n"
                    ."New enquiries: {$day['enquiries']}\n\n"
                    ."Needs attention:\n"
                    ."- {$day['ending_soon']} memberships end in the next 7 days\n"
                    .'- '.$m($day['dues'])." in pending dues\n\n"
                    .'Reports: '.Reports::getUrl(panel: 'gym', tenant: $gym)."\n\n"
                    .'Turn this email off or change its time in Gym settings, Reminders.';

                if ($billing->emailOwner($gym, "{$gym->name}: today ".$m($day['collected']).' collected', $body)) {
                    $sent++;
                }
            });

        $this->info("Queued {$sent} daily ".str('summary')->plural($sent).'.');

        return self::SUCCESS;
    }
}
