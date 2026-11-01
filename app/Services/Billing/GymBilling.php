<?php

namespace App\Services\Billing;

use App\Models\Gym;
use App\Models\SaasCoupon;
use App\Models\SaasPayment;
use App\Models\SaasPlan;
use App\Support\Country;
use App\Support\Money;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonInterface;
use Illuminate\Mail\Message;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * What gyms owe the platform: prices, credit for unused time on plan changes,
 * discount codes, tax, and the tax invoice for every payment.
 *
 * India: plan prices are before GST (18% added). Australia: prices include GST (10%).
 */
class GymBilling
{
    public const PERIODS = ['monthly' => 1, 'yearly' => 12];

    /** The platform company that bills this gym (India or Australia). */
    public function entity(Gym $gym): array
    {
        $country = $gym->country === 'AU' ? 'AU' : 'IN';
        $entity = (array) config("gym.billing.entities.{$country}") + ['country' => $country];
        $entity['legal_name'] = filled($entity['legal_name'] ?? null) ? $entity['legal_name'] : config('app.name');
        $entity['state_code'] = filled($entity['state_code'] ?? null) ? $entity['state_code'] : (filled($entity['tax_id'] ?? null) && $country === 'IN' ? substr($entity['tax_id'], 0, 2) : null);
        // No GSTIN / ABN means the platform is not registered, so no GST is charged.
        $entity['tax_rate'] = filled($entity['tax_id'] ?? null) ? (float) ($entity['tax_rate'] ?? 0) : 0.0;

        return $entity;
    }

    public function taxInclusive(Gym $gym): bool
    {
        return (bool) Country::get($gym->country, 'tax_inclusive');
    }

    /** Last day the gym can still make changes (end date plus grace period). */
    public function graceEndsAt(Gym $gym): ?CarbonInterface
    {
        return $gym->subscription_ends_at?->copy()->addDays((int) config('gym.billing.grace_days'));
    }

    /** Value of the unused days on the current paid plan, in list-price terms. */
    public function unusedCredit(Gym $gym, SaasPlan $newPlan): float
    {
        if ($gym->status !== 'active' || ! $gym->subscription_ends_at?->isFuture() || ! $gym->saas_plan_id || $gym->saas_plan_id === $newPlan->id) {
            return 0.0;
        }

        $last = $gym->saasPayments()->where('kind', 'subscription')->where('saas_plan_id', $gym->saas_plan_id)->latest('paid_at')->first();
        $plan = $gym->saasPlan;

        if (! $plan) {
            return 0.0;
        }

        $period = $last?->period ?? ($last?->months >= 12 ? 'yearly' : 'monthly');
        $months = self::PERIODS[$period];
        $price = $plan->price($gym->currency, $period);
        $periodDays = max(1, now()->diffInDays(now()->addMonthsNoOverflow($months)));
        $daysLeft = max(0, now()->diffInDays($gym->subscription_ends_at));

        return round($price * min(1, $daysLeft / $periodDays), 2);
    }

    /** When a payment for this plan would start and end. */
    public function periodFor(Gym $gym, SaasPlan $plan, int $months): array
    {
        $start = now();

        if ($gym->saas_plan_id === $plan->id || $gym->status === 'trial') {
            // Renewing: continue from the current end (unused days, the trial, or days used in the grace period are kept).
            $end = $gym->status === 'trial' ? $gym->trial_ends_at : $gym->subscription_ends_at;
            $graceEnd = $gym->status === 'trial' ? $end : ($end ? $this->graceEndsAt($gym) : null);

            if ($end && $graceEnd?->isFuture()) {
                $start = $end->copy();
            }
        }

        return [Carbon::parse($start), Carbon::parse($start)->addMonthsNoOverflow($months)];
    }

    /**
     * Price breakdown for buying a plan now.
     *
     * @return array{currency: string, period: string, months: int, list_price: float, credit: float, discount: float, subtotal: float, tax_amount: float, tax_rate: float, total: float, coupon: ?SaasCoupon, coupon_error: ?string, starts_at: Carbon, ends_at: Carbon, change: bool}
     */
    public function quote(Gym $gym, SaasPlan $plan, string $period, ?string $couponCode = null): array
    {
        $period = array_key_exists($period, self::PERIODS) ? $period : 'monthly';
        $currency = $gym->currency;
        $list = $plan->price($currency, $period);
        $credit = min($list, $this->unusedCredit($gym, $plan));

        $coupon = null;
        $couponError = null;

        if (filled($couponCode)) {
            $coupon = SaasCoupon::query()->where('code', strtoupper(trim($couponCode)))->first();
            $couponError = $coupon ? $coupon->problemFor($gym, $plan) : 'That code is not valid.';
            $coupon = $couponError ? null : $coupon;
        }

        $discount = $coupon ? $coupon->discountFor($list - $credit) : 0.0;
        $price = round($list - $credit - $discount, 2);
        $rate = $this->entity($gym)['tax_rate'];

        if ($this->taxInclusive($gym)) {
            $total = $price;
        } else {
            $total = round($price * (1 + $rate / 100), 2);
        }

        [$starts, $ends] = $this->periodFor($gym, $plan, self::PERIODS[$period]);

        return [
            'currency' => $currency,
            'period' => $period,
            'months' => self::PERIODS[$period],
            'list_price' => $list,
            'credit' => $credit,
            'discount' => $discount,
            'coupon' => $coupon,
            'coupon_error' => $couponError,
            'tax_rate' => $rate,
            'tax_amount' => $this->taxIn($total, $rate),
            'subtotal' => round($total - $this->taxIn($total, $rate), 2),
            'total' => $total,
            'starts_at' => $starts,
            'ends_at' => $ends,
            'change' => $gym->saas_plan_id !== $plan->id && $gym->status === 'active' && (bool) $gym->subscription_ends_at?->isFuture(),
        ];
    }

    /** GST contained in an amount that includes it. */
    public function taxIn(float $total, float $rate): float
    {
        return $rate > 0 ? round($total * $rate / (100 + $rate), 2) : 0.0;
    }

    /**
     * Records a software plan payment, moves the gym to that plan and extends it,
     * and issues the tax invoice. Gateway payment ids make this safe to call twice.
     */
    public function recordPlanPayment(Gym $gym, SaasPlan $plan, string $period, float $total, array $extra = []): ?SaasPayment
    {
        if (filled($extra['gateway_payment_id'] ?? null) && SaasPayment::query()->where('gateway_payment_id', $extra['gateway_payment_id'])->exists()) {
            return null;
        }

        $months = (int) ($extra['months'] ?? self::PERIODS[$period] ?? 1);
        $changingPlan = $gym->saas_plan_id !== $plan->id;
        [$starts, $ends] = $this->periodFor($gym, $plan, $months);

        $payment = DB::transaction(function () use ($gym, $plan, $period, $total, $extra, $months, $starts, $ends) {
            $gym->update([
                'saas_plan_id' => $plan->id,
                'status' => 'active',
                'subscription_ends_at' => $ends,
                'suspension_reason' => null,
            ]);

            $coupon = isset($extra['saas_coupon_id']) ? SaasCoupon::query()->lockForUpdate()->find($extra['saas_coupon_id']) : null;
            $coupon?->increment('redemptions');

            $label = $plan->name.' plan, '.($months === 12 ? 'yearly' : ($months === 1 ? 'monthly' : "{$months} months"));

            return $this->createPayment($gym, $total, [
                'kind' => 'subscription',
                'saas_plan_id' => $plan->id,
                'saas_coupon_id' => $coupon?->id,
                'months' => $months,
                'period' => $period,
                'period_start' => $starts,
                'period_end' => $ends,
            ] + array_intersect_key($extra, array_flip(['gateway', 'gateway_payment_id', 'reference', 'recorded_by', 'paid_at'])), [
                'description' => $label.' ('.$starts->format('d M Y').' to '.$ends->copy()->subDay()->format('d M Y').')',
                'list_price' => (float) ($extra['list_price'] ?? 0),
                'credit' => (float) ($extra['credit'] ?? 0),
                'discount' => (float) ($extra['discount'] ?? 0),
                'coupon' => $coupon?->code,
            ]);
        });

        // A plan change paid once replaces an auto-renew set up for the old plan.
        if ($changingPlan && $gym->auto_renew && empty($extra['renewal'])) {
            rescue(fn () => app(BillingCheckout::class)->cancelAutoRenew($gym), report: true);
        }

        $this->emailInvoice($payment);

        return $payment;
    }

    /** Creates the payment with its invoice number and a snapshot of seller, buyer and tax. */
    public function createPayment(Gym $gym, float $total, array $attributes, array $line): SaasPayment
    {
        $entity = $this->entity($gym);
        $rate = $entity['tax_rate'];
        $tax = $this->taxIn($total, $rate);
        $interState = $entity['country'] === 'IN' && filled($gym->state_code) && filled($entity['state_code']) && $gym->state_code !== $entity['state_code'];
        $paidAt = Carbon::parse($attributes['paid_at'] ?? now());

        return $gym->saasPayments()->create($attributes + [
            'amount' => $total,
            'subtotal' => round($total - $tax, 2),
            'tax_amount' => $tax,
            'currency' => $gym->currency,
            'paid_at' => $paidAt,
            'invoice_number' => $this->nextInvoiceNumber($entity, $paidAt),
            'invoice' => [
                'seller' => array_intersect_key($entity, array_flip(['country', 'legal_name', 'address', 'tax_id', 'state_code'])),
                'buyer' => [
                    'name' => $gym->name,
                    'address' => collect([$gym->address, $gym->city, $gym->state, $gym->pincode])->filter()->implode(', '),
                    'tax_id_label' => $gym->taxIdLabel(),
                    'tax_id' => $gym->taxId(),
                    'state_code' => $gym->state_code,
                    'email' => $gym->email,
                ],
                'line' => $line,
                'tax_rate' => $rate,
                'tax_inclusive' => $this->taxInclusive($gym),
                'tax_split' => $entity['country'] === 'IN' && $rate > 0 ? ($interState ? 'igst' : 'cgst_sgst') : null,
                'sac' => $entity['country'] === 'IN' ? '998314' : null,
            ],
        ]);
    }

    /** Gap-free number per company and financial year, e.g. GF-IN-2627-0001. */
    public function nextInvoiceNumber(array $entity, CarbonInterface $date): string
    {
        $fyStartMonth = $entity['country'] === 'AU' ? 7 : 4;
        $fy = $date->month >= $fyStartMonth ? $date->year : $date->year - 1;
        $series = ($entity['invoice_prefix'] ?: 'GF-'.$entity['country']).'-'.substr((string) $fy, 2).substr((string) ($fy + 1), 2);

        return DB::transaction(function () use ($series) {
            DB::table('billing_sequences')->insertOrIgnore(['series' => $series, 'next' => 1, 'created_at' => now(), 'updated_at' => now()]);
            $row = DB::table('billing_sequences')->where('series', $series)->lockForUpdate()->first();
            DB::table('billing_sequences')->where('id', $row->id)->update(['next' => $row->next + 1, 'updated_at' => now()]);

            return $series.'-'.str_pad((string) $row->next, 4, '0', STR_PAD_LEFT);
        });
    }

    public function invoicePdf(SaasPayment $payment): \Barryvdh\DomPDF\PDF
    {
        return Pdf::loadView('pdf.saas-invoice', ['payment' => $payment->loadMissing('gym', 'saasPlan')]);
    }

    public function emailInvoice(SaasPayment $payment): void
    {
        $gym = $payment->gym;
        $to = $gym->users()->wherePivot('role', 'owner')->first()?->email ?? $gym->email;

        if (blank($to) || blank($payment->invoice_number)) {
            return;
        }

        try {
            $pdf = $this->invoicePdf($payment)->output();
            $amount = Money::format((float) $payment->amount, $payment->currency);

            Mail::raw(
                "Hi {$gym->name},\n\nThank you for your payment of {$amount}. Your tax invoice {$payment->invoice_number} is attached.\n\n"
                .($payment->kind === 'subscription' && $gym->subscription_ends_at ? 'Your plan is active until '.$gym->subscription_ends_at->format('d M Y').".\n\n" : '')
                .config('app.name'),
                fn (Message $message) => $message->to($to)
                    ->subject('Tax invoice '.$payment->invoice_number.' from '.config('app.name'))
                    ->attachData($pdf, $payment->invoice_number.'.pdf', ['mime' => 'application/pdf'])
            );
        } catch (Throwable $e) {
            report($e);
        }
    }
}
