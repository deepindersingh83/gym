<?php

namespace App\Services\Compliance;

use App\Jobs\BuildGymDataExport;
use App\Models\Gym;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/** Everything a gym has stored, as CSV files and photos in one ZIP (for the owner, or support on request). */
class GymDataExport
{
    /** Tables exported as-is (rows of this gym), with columns that must never leave the server. */
    protected const TABLES = [
        'branches' => [],
        'members' => [],
        'membership_plans' => [],
        'coupons' => [],
        'subscriptions' => [],
        'payments' => [],
        'attendances' => [],
        'measurements' => [],
        'workout_plans' => [],
        'diet_plans' => [],
        'leads' => ['raw'],
        'expenses' => [],
        'cash_closings' => [],
        'staff_attendances' => [],
        'trainer_ratings' => [],
        'message_logs' => [],
        'broadcasts' => [],
        'message_templates' => [],
        'credit_transactions' => [],
        'saas_payments' => ['invoice', 'gateway_payment_id'],
    ];

    /** Gym columns left out: secrets and internal ids. */
    protected const GYM_HIDDEN = ['integrations', 'lead_webhook_token', 'cloudflare_hostname_id', 'billing_subscription_id'];

    /** Queues a ZIP for this person; a request already being prepared is reused. */
    public function request(Gym $gym, User $user): \App\Models\GymDataExport
    {
        $pending = \App\Models\GymDataExport::query()->where('gym_id', $gym->id)->where('status', 'pending')->where('created_at', '>', now()->subHour())->first();

        if ($pending) {
            return $pending;
        }

        $export = \App\Models\GymDataExport::create(['gym_id' => $gym->id, 'user_id' => $user->id]);
        BuildGymDataExport::dispatch($export->id)->afterCommit();

        return $export->refresh();
    }

    /** Removes downloads older than 7 days. */
    public function pruneExpired(): int
    {
        $expired = \App\Models\GymDataExport::query()->where(fn ($q) => $q->where('expires_at', '<', now())->orWhere(fn ($q) => $q->where('status', 'failed')->where('created_at', '<', now()->subDays(\App\Models\GymDataExport::KEEP_DAYS))))->get();

        foreach ($expired as $export) {
            if ($export->path) {
                Storage::disk('local')->delete($export->path);
            }
            $export->delete();
        }

        return $expired->count();
    }

    /** @return string path of a temporary ZIP file; delete it after sending */
    public function build(Gym $gym): string
    {
        $path = tempnam(sys_get_temp_dir(), 'gym-export-');
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the export file.');
        }

        $profile = collect(DB::table('gyms')->where('id', $gym->id)->first())->except(self::GYM_HIDDEN)->all();
        $zip->addFromString('gym.csv', $this->csv([$profile]));

        $staff = DB::table('gym_user')->join('users', 'users.id', '=', 'gym_user.user_id')->where('gym_user.gym_id', $gym->id)
            ->select('users.name', 'users.email', 'users.phone', 'gym_user.role', 'gym_user.branch_id', 'gym_user.designation', 'gym_user.is_active', 'gym_user.created_at')
            ->get()->map(fn ($row) => (array) $row)->all();
        $zip->addFromString('staff.csv', $this->csv($staff));

        foreach (self::TABLES as $table => $hidden) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'gym_id')) {
                continue;
            }

            $rows = DB::table($table)->where('gym_id', $gym->id)->orderBy('id')->get()
                ->map(fn ($row) => collect((array) $row)->except($hidden)->all())->all();
            $zip->addFromString("{$table}.csv", $this->csv($rows));
        }

        // Additional members on shared (couple / family) memberships.
        $shared = DB::table('member_subscription')->join('subscriptions', 'subscriptions.id', '=', 'member_subscription.subscription_id')
            ->where('subscriptions.gym_id', $gym->id)->select('member_subscription.*')->get()->map(fn ($row) => (array) $row)->all();
        $zip->addFromString('subscription_members.csv', $this->csv($shared));

        $disk = Storage::disk('public');

        foreach (["members/{$gym->id}", "staff/{$gym->id}", "websites/{$gym->id}"] as $directory) {
            foreach ($disk->allFiles($directory) as $file) {
                $zip->addFile($disk->path($file), 'files/'.$file);
            }
        }

        if ($gym->logo_path && $disk->exists($gym->logo_path)) {
            $zip->addFile($disk->path($gym->logo_path), 'files/'.$gym->logo_path);
        }

        $zip->addFromString('README.txt', "Data export for {$gym->name}\nCreated ".now()->toDateTimeString()." ({$gym->timezone})\n\n"
            ."Each .csv file is one table and opens in Excel or Google Sheets. Ids link the files together\n"
            ."(for example payments.member_id is members.id). Photos and website images are in the files folder.\n");

        $zip->close();

        return $path;
    }

    public function filename(Gym $gym): string
    {
        return $gym->slug.'-data-'.now()->format('Y-m-d').'.zip';
    }

    /** @param  list<array<string, mixed>>  $rows */
    protected function csv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows ₹ and names correctly

        if ($rows) {
            fputcsv($handle, array_keys($rows[0]), escape: '');

            foreach ($rows as $row) {
                fputcsv($handle, array_map(fn ($v) => is_array($v) || is_object($v) ? json_encode($v) : $v, array_values($row)), escape: '');
            }
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }
}
