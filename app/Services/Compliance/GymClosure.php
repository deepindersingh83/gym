<?php

namespace App\Services\Compliance;

use App\Models\Gym;
use App\Services\Billing\BillingCheckout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Closing a gym account: it is hidden at once and can be restored for 30 days,
 * then every row and file is deleted for good. The platform's own tax invoices are kept.
 */
class GymClosure
{
    public const KEEP_DAYS = 30;

    public function close(Gym $gym, ?string $reason = null): void
    {
        if ($gym->auto_renew) {
            rescue(fn () => app(BillingCheckout::class)->cancelAutoRenew($gym), report: true);
        }

        $gym->forceFill(['deletion_scheduled_at' => now()->addDays(self::KEEP_DAYS), 'closure_reason' => $reason])->save();
        $gym->delete();
    }

    public function restore(Gym $gym): void
    {
        $gym->restore();
        $gym->forceFill(['deletion_scheduled_at' => null, 'closure_reason' => null])->save();
    }

    /** Deletes the gym, all its data and files. */
    public function purge(Gym $gym): void
    {
        $paths = array_filter([$gym->logo_path]);

        DB::transaction(function () use ($gym) {
            // Memberships point at plans with a restrict rule, so they go first; the rest cascades from the gym.
            DB::table('subscriptions')->where('gym_id', $gym->id)->delete();
            DB::table('activity_log')->where('gym_id', $gym->id)->where(fn ($q) => $q->whereNull('log_name')->orWhere('log_name', '!=', 'admin'))->delete();
            DB::table('activity_log')->where('gym_id', $gym->id)->update(['gym_id' => null]);
            // Invoices the platform issued keep their buyer snapshot.
            DB::table('saas_payments')->where('gym_id', $gym->id)->update(['gym_id' => null]);
            DB::table('partner_commissions')->where('gym_id', $gym->id)->update(['gym_id' => null]);
            $gym->forceDelete();
        });

        $disk = Storage::disk('public');

        foreach (["members/{$gym->id}", "staff/{$gym->id}", "websites/{$gym->id}"] as $directory) {
            $disk->deleteDirectory($directory);
        }

        $disk->delete($paths);

        activity('admin')->withProperties(['gym' => $gym->name, 'slug' => $gym->slug])->log('Permanently deleted closed gym account');
    }

    /** @return int number of gyms deleted */
    public function purgeDue(): int
    {
        $due = Gym::onlyTrashed()->whereNotNull('deletion_scheduled_at')->where('deletion_scheduled_at', '<=', now())->get();
        $due->each(fn (Gym $gym) => $this->purge($gym));

        return $due->count();
    }
}
