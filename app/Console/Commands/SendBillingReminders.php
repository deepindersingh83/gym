<?php

namespace App\Console\Commands;

use App\Filament\Gym\Pages\Billing;
use App\Models\Gym;
use Illuminate\Console\Command;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Throwable;

/** Emails gym owners before their trial or plan ends, when it has ended, and before changes lock. */
class SendBillingReminders extends Command
{
    protected $signature = 'gym:billing-reminders';

    protected $description = 'Email gym owners about trials and plans that are ending';

    public function handle(): int
    {
        $days = collect(config('gym.billing.reminder_days', [7, 3, 1]))->map(fn ($d) => (int) $d)->filter(fn ($d) => $d > 0)->unique();
        $grace = (int) config('gym.billing.grace_days');
        $today = now()->startOfDay();
        $sent = 0;

        Gym::query()->whereIn('status', ['trial', 'active'])->with('saasPlan')->each(function (Gym $gym) use ($days, $grace, $today, &$sent) {
            $end = $gym->status === 'trial' ? $gym->trial_ends_at : $gym->subscription_ends_at;

            if (! $end) {
                return;
            }

            $daysLeft = (int) $today->diffInDays($end->copy()->startOfDay(), false);
            $what = $gym->status === 'trial' ? 'free trial' : ($gym->saasPlan?->name ?? 'software').' plan';
            $message = null;

            if ($days->contains($daysLeft)) {
                $message = $gym->auto_renew
                    ? ["Your plan renews automatically on {$end->format('d M Y')}", "Your {$what} renews automatically on {$end->format('d M Y')}. No action is needed. You can turn auto-renew off on the Plan & billing page."]
                    : ["Your {$what} ends in {$daysLeft} ".str('day')->plural($daysLeft), "Your {$what} ends on {$end->format('d M Y')}. Renew now so your team can keep working without interruption."];

                // Auto-renew only needs one heads-up, on the earliest reminder day.
                if ($gym->auto_renew && $daysLeft !== $days->max()) {
                    $message = null;
                }
            } elseif ($daysLeft === 0) {
                $message = $gym->status === 'trial'
                    ? ['Your free trial ends today', 'Your free trial ends today. Choose a plan to keep using the software. Your data stays safe.']
                    : ($gym->auto_renew ? null : ['Your plan ends today', 'Your plan ends today. '.($grace > 0 ? "You have {$grace} more ".str('day')->plural($grace).' before changes are locked.' : 'Renew now to avoid changes being locked.')]);
            } elseif ($gym->status === 'active' && $grace > 0 && $daysLeft === -$grace + 1) {
                $message = ['Last day before your account is locked', 'Your plan ended on '.$end->format('d M Y').'. From tomorrow you can only view your data until you renew.'];
            }

            if ($message && $this->send($gym, ...$message)) {
                $sent++;
            }
        });

        $this->info("Sent {$sent} billing reminders.");

        return self::SUCCESS;
    }

    protected function send(Gym $gym, string $subject, string $body): bool
    {
        $to = $gym->users()->wherePivot('role', 'owner')->first()?->email ?? $gym->email;

        if (blank($to)) {
            return false;
        }

        try {
            $url = Billing::getUrl(panel: 'gym', tenant: $gym);
            Mail::raw("Hi {$gym->name},\n\n{$body}\n\nPlan & billing: {$url}\n\n".config('app.name'), fn (Message $m) => $m->to($to)->subject($subject));

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}
