<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Gym;
use App\Models\Member;
use App\Models\Payment;
use Illuminate\Support\Collection;

/**
 * How actively each gym uses the software, for the platform owner:
 * active, at_risk (likely to cancel) or inactive, with the reasons why.
 */
class GymHealth
{
    public const LABELS = ['active' => 'Active', 'at_risk' => 'At risk', 'inactive' => 'Inactive'];

    public const COLORS = ['active' => 'success', 'at_risk' => 'warning', 'inactive' => 'danger'];

    /** @var Collection<int, array{status: string, reasons: list<string>, metrics: array<string, mixed>}>|null */
    protected ?Collection $report = null;

    /** @return Collection<int, array{status: string, reasons: list<string>, metrics: array<string, mixed>}> keyed by gym id */
    public function report(): Collection
    {
        return $this->report ??= $this->build();
    }

    /** @return array{status: string, reasons: list<string>, metrics: array<string, mixed>} */
    public function for(Gym $gym): array
    {
        return $this->report()->get($gym->id) ?? ['status' => 'inactive', 'reasons' => ['No data'], 'metrics' => []];
    }

    /** @return list<int> */
    public function idsWithStatus(string $status): array
    {
        return $this->report()->filter(fn ($row) => $row['status'] === $status)->keys()->all();
    }

    protected function build(): Collection
    {
        $now = now();
        $last30 = $now->copy()->subDays(30);
        $prev30 = $now->copy()->subDays(60);

        $count = fn ($query) => $query->withoutGlobalScopes()->selectRaw('gym_id, COUNT(*) as n')->groupBy('gym_id')->pluck('n', 'gym_id');
        $members = $count(Member::query()->whereNull('deleted_at'));
        $checkIns = $count(Attendance::query()->where('checked_in_at', '>=', $last30));
        $checkInsBefore = $count(Attendance::query()->whereBetween('checked_in_at', [$prev30, $last30]));
        $payments = $count(Payment::query()->where('paid_at', '>=', $last30));
        $newMembers = $count(Member::query()->whereNull('deleted_at')->where('created_at', '>=', $last30));

        return Gym::query()->get()->mapWithKeys(function (Gym $gym) use ($now, $members, $checkIns, $checkInsBefore, $payments, $newMembers) {
            $metrics = [
                'members' => (int) ($members[$gym->id] ?? 0),
                'check_ins_30d' => (int) ($checkIns[$gym->id] ?? 0),
                'check_ins_prev_30d' => (int) ($checkInsBefore[$gym->id] ?? 0),
                'payments_30d' => (int) ($payments[$gym->id] ?? 0),
                'new_members_30d' => (int) ($newMembers[$gym->id] ?? 0),
                'last_activity_at' => $gym->last_activity_at,
            ];

            $daysIdle = $gym->last_activity_at ? (int) $gym->last_activity_at->diffInDays($now) : null;
            $ageDays = (int) $gym->created_at->diffInDays($now);
            $inactive = [];
            $risk = [];

            if (in_array($gym->status, ['suspended', 'cancelled'], true)) {
                $inactive[] = 'Account '.$gym->status;
            } elseif (! $gym->hasActiveSaasSubscription()) {
                $inactive[] = $gym->status === 'trial' ? 'Trial ended without payment' : 'Paid plan expired';
            }

            if ($daysIdle !== null && $daysIdle >= 30) {
                $inactive[] = "No staff login for {$daysIdle} days";
            } elseif ($daysIdle === null && $ageDays >= 14) {
                $inactive[] = 'Never used since signing up';
            }

            if ($inactive === []) {
                if ($gym->status === 'trial' && $gym->trial_ends_at && $gym->trial_ends_at->lte($now->copy()->addDays(7))) {
                    $risk[] = 'Trial ends '.$gym->trial_ends_at->diffForHumans();
                }

                if ($gym->status === 'active' && $gym->subscription_ends_at && $gym->subscription_ends_at->lte($now->copy()->addDays(14))) {
                    $risk[] = 'Plan renews '.$gym->subscription_ends_at->diffForHumans();
                }

                if ($daysIdle !== null && $daysIdle >= 7) {
                    $risk[] = "No staff login for {$daysIdle} days";
                } elseif ($daysIdle === null && $ageDays >= 3) {
                    $risk[] = 'Not set up yet';
                }

                if ($metrics['check_ins_prev_30d'] >= 20 && $metrics['check_ins_30d'] < $metrics['check_ins_prev_30d'] / 2) {
                    $risk[] = 'Check-ins down '.round(100 - 100 * $metrics['check_ins_30d'] / $metrics['check_ins_prev_30d']).'% vs last month';
                }

                if ($metrics['members'] === 0 && $ageDays >= 7) {
                    $risk[] = 'No members added yet';
                }
            }

            $status = $inactive ? 'inactive' : ($risk ? 'at_risk' : 'active');

            return [$gym->id => ['status' => $status, 'reasons' => $inactive ?: $risk, 'metrics' => $metrics]];
        });
    }
}
