<?php

namespace App\Services\Billing;

use App\Models\Gym;
use App\Models\Partner;
use App\Models\SaasPayment;
use App\Models\SaasPlan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Platform revenue figures for one currency (India ₹ and Australia A$ are never added together).
 * All amounts are before GST, which is collected for the government.
 */
class RevenueReport
{
    public function __construct(public readonly string $currency = 'INR') {}

    /** @return Collection<int, Gym> */
    public function payingGyms(): Collection
    {
        return Gym::query()->where('currency', $this->currency)->where('status', 'active')->with('saasPlan')->get()
            ->filter(fn (Gym $gym) => $gym->hasActiveSaasSubscription())
            ->values();
    }

    /** What one gym is worth per month, from its last plan payment (discounts included), else its plan's price. */
    public function monthlyValue(Gym $gym): float
    {
        $last = $gym->saasPayments()->where('kind', 'subscription')->where('amount', '>', 0)->latest('paid_at')->first();

        if ($last && $last->months) {
            $net = (float) $last->subtotal > 0 ? (float) $last->subtotal : (float) $last->amount - (float) $last->tax_amount;

            return round($net / $last->months, 2);
        }

        $price = $gym->saasPlan?->price($this->currency, 'monthly') ?? 0.0;
        // Australian prices include GST.
        $rate = (float) config('gym.billing.entities.'.($this->currency === 'AUD' ? 'AU' : 'IN').'.tax_rate');

        return $this->currency === 'AUD' && $rate > 0 ? round($price / (1 + $rate / 100), 2) : $price;
    }

    public function mrr(): float
    {
        return round($this->payingGyms()->sum(fn (Gym $gym) => $this->monthlyValue($gym)), 2);
    }

    /** Net revenue received in a period (plans and credits). */
    public function revenue(Carbon $from, Carbon $to, ?string $kind = null): float
    {
        return round((float) $this->payments()
            ->whereBetween('paid_at', [$from, $to])
            ->when($kind, fn ($q) => $q->where('kind', $kind))
            ->get()
            ->sum(fn (SaasPayment $p) => $this->net($p)), 2);
    }

    /** @return array{labels: list<string>, plans: list<float>, credits: list<float>} last 12 months */
    public function monthly(int $months = 12): array
    {
        $start = now()->startOfMonth()->subMonths($months - 1);
        $payments = $this->payments()->where('paid_at', '>=', $start)->get();
        $out = ['labels' => [], 'plans' => [], 'credits' => []];

        for ($i = 0; $i < $months; $i++) {
            $month = $start->copy()->addMonths($i);
            $in = $payments->filter(fn (SaasPayment $p) => $p->paid_at->isSameMonth($month));
            $out['labels'][] = $month->format('M y');
            $out['plans'][] = round($in->where('kind', 'subscription')->sum(fn ($p) => $this->net($p)), 2);
            $out['credits'][] = round($in->where('kind', 'credits')->sum(fn ($p) => $this->net($p)), 2);
        }

        return $out;
    }

    /**
     * Gyms whose paid time ran out in the last 30 days and did not renew, against gyms paying 30 days ago.
     *
     * @return array{lost: int, rate: float}
     */
    public function churn(int $days = 30): array
    {
        $grace = (int) config('gym.billing.grace_days');
        $lost = Gym::query()->where('currency', $this->currency)
            ->whereIn('status', ['active', 'cancelled'])
            ->whereBetween('subscription_ends_at', [now()->subDays($days + $grace), now()->subDays($grace)])
            ->get()
            ->reject(fn (Gym $gym) => $gym->hasActiveSaasSubscription())
            ->count();

        $base = $this->payingGyms()->count() + $lost;

        return ['lost' => $lost, 'rate' => $base > 0 ? round($lost / $base * 100, 1) : 0.0];
    }

    /**
     * Of the trials that ended in the last 90 days, how many paid.
     *
     * @return array{ended: int, paid: int, rate: float}
     */
    public function trialConversion(int $days = 90): array
    {
        $gyms = Gym::withTrashed()->where('currency', $this->currency)
            ->whereBetween('trial_ends_at', [now()->subDays($days), now()])
            ->withCount(['saasPayments as paid_count' => fn ($q) => $q->where('kind', 'subscription')])
            ->get();

        $paid = $gyms->where('paid_count', '>', 0)->count();

        return ['ended' => $gyms->count(), 'paid' => $paid, 'rate' => $gyms->count() ? round($paid / $gyms->count() * 100, 1) : 0.0];
    }

    /** @return Collection<int, array{plan: string, gyms: int, mrr: float, revenue: float}> last 12 months */
    public function byPlan(): Collection
    {
        $paying = $this->payingGyms();
        $revenue = $this->payments()->where('kind', 'subscription')->where('paid_at', '>=', now()->subYear())->get()->groupBy('saas_plan_id');

        return SaasPlan::query()->orderBy('sort')->get()->map(fn (SaasPlan $plan) => [
            'plan' => $plan->name,
            'gyms' => $paying->where('saas_plan_id', $plan->id)->count(),
            'mrr' => round($paying->where('saas_plan_id', $plan->id)->sum(fn (Gym $gym) => $this->monthlyValue($gym)), 2),
            'revenue' => round(($revenue[$plan->id] ?? collect())->sum(fn ($p) => $this->net($p)), 2),
        ])->filter(fn ($row) => $row['gyms'] > 0 || $row['revenue'] > 0)->values();
    }

    /** @return Collection<int, array{partner: string, gyms: int, revenue: float, commission: float, unpaid: float}> last 12 months */
    public function byPartner(): Collection
    {
        $payments = $this->payments()->where('kind', 'subscription')->where('paid_at', '>=', now()->subYear())
            ->whereHas('gym', fn ($q) => $q->whereNotNull('partner_id'))
            ->with(['gym:id,partner_id', 'commission'])
            ->get()
            ->groupBy(fn (SaasPayment $p) => $p->gym->partner_id);

        return Partner::query()->whereIn('id', $payments->keys())->get()->map(fn (Partner $partner) => [
            'partner' => $partner->company ?: $partner->name,
            'gyms' => $payments[$partner->id]->pluck('gym_id')->unique()->count(),
            'revenue' => round($payments[$partner->id]->sum(fn ($p) => $this->net($p)), 2),
            'commission' => round($payments[$partner->id]->sum(fn ($p) => (float) $p->commission?->amount), 2),
            'unpaid' => round($payments[$partner->id]->filter(fn ($p) => $p->commission?->status === 'pending')->sum(fn ($p) => (float) $p->commission->amount), 2),
        ])->sortByDesc('revenue')->values();
    }

    /** @return Collection<int, Gym> plans ending in the next N days */
    public function renewalsDue(int $days = 30): Collection
    {
        return Gym::query()->where('currency', $this->currency)->where('status', 'active')
            ->whereBetween('subscription_ends_at', [now(), now()->addDays($days)])
            ->with('saasPlan')
            ->orderBy('subscription_ends_at')
            ->get();
    }

    protected function payments()
    {
        return SaasPayment::query()->where('currency', $this->currency);
    }

    protected function net(SaasPayment $payment): float
    {
        return (float) $payment->amount - (float) $payment->tax_amount;
    }
}
