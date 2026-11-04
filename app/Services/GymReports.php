<?php

namespace App\Services;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Attendance;
use App\Models\Gym;
use App\Models\Lead;
use App\Models\Member;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\TrainerRating;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Owner reports for the current gym (and branch, through the usual scopes):
 * retention, revenue per trainer, busiest hours, enquiry conversion and the daily summary.
 */
class GymReports
{
    public function __construct(protected Gym $gym) {}

    /**
     * Of the memberships that ended each month, how many renewed (a later membership starting within 30 days).
     *
     * @return Collection<int, array{month: string, ended: int, renewed: int, rate: float}>
     */
    public function retention(int $months = 6): Collection
    {
        $start = now()->startOfMonth()->subMonths($months - 1);
        $ended = Subscription::query()->where('status', '!=', SubscriptionStatus::Cancelled)
            ->whereBetween('end_date', [$start->toDateString(), now()->toDateString()])
            ->get(['id', 'member_id', 'end_date']);

        $later = Subscription::query()->where('status', '!=', SubscriptionStatus::Cancelled)
            ->whereIn('member_id', $ended->pluck('member_id')->unique())
            ->get(['id', 'member_id', 'start_date'])
            ->groupBy('member_id');

        return collect(range(0, $months - 1))->map(function (int $i) use ($start, $ended, $later) {
            $month = $start->copy()->addMonths($i);
            $inMonth = $ended->filter(fn (Subscription $s) => $s->end_date->isSameMonth($month));
            $renewed = $inMonth->filter(fn (Subscription $s) => ($later[$s->member_id] ?? collect())
                ->contains(fn ($next) => $next->id !== $s->id && $next->start_date->between($s->end_date->copy()->subDays(15), $s->end_date->copy()->addDays(30))))
                ->count();

            return [
                'month' => $month->format('M Y'),
                'ended' => $inMonth->count(),
                'renewed' => $renewed,
                'rate' => $inMonth->count() ? round($renewed / $inMonth->count() * 100, 1) : 0.0,
            ];
        });
    }

    /** @return Collection<int, array{trainer: string, members: int, collected: float, rating: ?float, ratings: int}> */
    public function revenuePerTrainer(Carbon $from, Carbon $to): Collection
    {
        $members = Member::query()->whereNotNull('trainer_id')->get(['id', 'trainer_id', 'is_active'])->groupBy('trainer_id');
        $memberTrainer = $members->flatten()->pluck('trainer_id', 'id');

        $collected = Payment::query()->whereBetween('paid_at', [$from, $to])->whereIn('member_id', $memberTrainer->keys())->get(['member_id', 'amount'])
            ->groupBy(fn (Payment $p) => $memberTrainer[$p->member_id])
            ->map(fn ($rows) => (float) $rows->sum('amount'));

        $ratings = TrainerRating::query()->whereBetween('created_at', [$from, $to])->get(['trainer_id', 'rating'])->groupBy('trainer_id');

        return User::query()->whereIn('id', $members->keys())->get(['id', 'name'])->map(fn (User $trainer) => [
            'trainer' => $trainer->name,
            'members' => $members[$trainer->id]->where('is_active', true)->count(),
            'collected' => round($collected[$trainer->id] ?? 0, 2),
            'rating' => isset($ratings[$trainer->id]) ? round($ratings[$trainer->id]->avg('rating'), 1) : null,
            'ratings' => isset($ratings[$trainer->id]) ? $ratings[$trainer->id]->count() : 0,
        ])->sortByDesc('collected')->values();
    }

    /**
     * Check-ins by weekday and hour in the gym's own time zone.
     *
     * @return array{grid: array<int, array<int, int>>, max: int, peak: ?string, total: int} grid[1..7 = Mon..Sun][hour]
     */
    public function busiestHours(int $days = 30): array
    {
        $grid = array_fill(1, 7, array_fill(0, 24, 0));

        Attendance::query()->where('checked_in_at', '>=', now()->subDays($days))->pluck('checked_in_at')
            ->each(function ($at) use (&$grid) {
                $local = Carbon::parse($at)->setTimezone($this->gym->timezone ?: config('app.timezone'));
                $grid[$local->dayOfWeekIso][$local->hour]++;
            });

        $max = max(array_map('max', $grid));
        $peak = null;

        if ($max > 0) {
            foreach ($grid as $day => $hours) {
                if (($hour = array_search($max, $hours, true)) !== false) {
                    $peak = Carbon::now()->startOfWeek()->addDays($day - 1)->format('l').' '.Carbon::createFromTime($hour)->format('g A');
                    break;
                }
            }
        }

        return ['grid' => $grid, 'max' => $max, 'peak' => $peak, 'total' => array_sum(array_map('array_sum', $grid))];
    }

    /** @return Collection<int, array{source: string, leads: int, trials: int, joined: int, rate: float}> */
    public function leadConversion(Carbon $from, Carbon $to): Collection
    {
        return Lead::query()->whereBetween('created_at', [$from, $to])->get(['source', 'status', 'trial_date', 'member_id'])
            ->groupBy(fn (Lead $lead) => $lead->source instanceof LeadSource ? $lead->source->value : (string) $lead->source)
            ->map(function ($leads, $source) {
                $joined = $leads->filter(fn (Lead $l) => $l->status === LeadStatus::Converted || $l->member_id)->count();

                return [
                    'source' => LeadSource::tryFrom($source)?->getLabel() ?? ucfirst(str_replace('_', ' ', $source)),
                    'leads' => $leads->count(),
                    'trials' => $leads->filter(fn (Lead $l) => $l->trial_date || $l->status === LeadStatus::TrialBooked)->count(),
                    'joined' => $joined,
                    'rate' => round($joined / $leads->count() * 100, 1),
                ];
            })->sortByDesc('leads')->values();
    }

    /** What happened today, for the owner's evening summary. */
    public function daily(?Carbon $date = null): array
    {
        $date ??= now()->setTimezone($this->gym->timezone ?: config('app.timezone'));
        $from = $date->copy()->startOfDay()->setTimezone(config('app.timezone'));
        $to = $date->copy()->endOfDay()->setTimezone(config('app.timezone'));
        $payments = Payment::query()->whereBetween('paid_at', [$from, $to])->get(['amount', 'method']);

        return [
            'date' => $date->format('l, d M Y'),
            'collected' => (float) $payments->sum('amount'),
            'by_method' => $payments->groupBy(fn ($p) => $p->method?->getLabel() ?? 'Other')->map(fn ($rows) => (float) $rows->sum('amount'))->all(),
            'new_members' => Member::query()->whereBetween('created_at', [$from, $to])->count(),
            'memberships_sold' => Subscription::query()->whereBetween('created_at', [$from, $to])->count(),
            'check_ins' => Attendance::query()->whereBetween('checked_in_at', [$from, $to])->count(),
            'enquiries' => Lead::query()->whereBetween('created_at', [$from, $to])->count(),
            'ending_soon' => Subscription::query()->active()->whereBetween('end_date', [$date->toDateString(), $date->copy()->addDays(7)->toDateString()])->count(),
            'dues' => (float) Subscription::query()->withDues()->get()->sum(fn ($s) => $s->balance_due),
        ];
    }
}
