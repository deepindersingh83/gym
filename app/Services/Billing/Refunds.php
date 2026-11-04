<?php

namespace App\Services\Billing;

use App\Models\SaasPayment;
use App\Models\SaasRefund;
use App\Models\User;
use App\Services\Credits;
use App\Services\Payments\PlatformBilling;
use App\Support\AdminLog;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Refunds of what gyms paid the platform. Each refund issues a GST credit note that
 * points back to the original tax invoice, as GST law in India and Australia requires.
 */
class Refunds
{
    public function __construct(
        protected GymBilling $billing,
        protected PlatformBilling $razorpay,
        protected Stripe $stripe,
    ) {}

    /** Whether the money can be sent back through the gateway it came from. */
    public function canRefundOnline(SaasPayment $payment): bool
    {
        $id = (string) $payment->gateway_payment_id;

        return match ($payment->gateway) {
            'razorpay' => str_starts_with($id, 'pay_') && $this->razorpay->isConfigured(),
            'stripe' => (str_starts_with($id, 'pi_') || str_starts_with($id, 'in_')) && $this->stripe->isConfigured(),
            default => false,
        };
    }

    /**
     * Refunds part or all of a payment.
     *
     * @param  bool  $online  send the money through Razorpay / Stripe (false: already returned by bank transfer, cash, ...)
     * @param  bool  $takeBack  shorten the paid period (plans) or remove the credits (credit packs) in proportion
     */
    public function refund(SaasPayment $payment, float $amount, string $reason, bool $online, bool $takeBack, ?User $by = null): SaasRefund
    {
        $amount = round($amount, 2);

        if ($amount <= 0 || $amount > $payment->refundable()) {
            throw new RuntimeException('The refund must be more than zero and at most '.Money::format($payment->refundable(), $payment->currency).'.');
        }

        if ($online && ! $this->canRefundOnline($payment)) {
            throw new RuntimeException('This payment cannot be refunded online. Return the money another way and record it here.');
        }

        $gatewayRefund = $online ? $this->sendRefund($payment, $amount, $reason) : null;

        $refund = $this->record($payment, $amount, $reason, $online ? $payment->gateway : 'manual', $gatewayRefund['id'] ?? null, $gatewayRefund['status'] ?? 'processed', $takeBack, $by);

        AdminLog::record('Refunded a software payment', $payment->gym, [
            'invoice' => $payment->invoice_number,
            'credit_note' => $refund->credit_note_number,
            'amount' => $amount,
            'reason' => $reason,
        ]);

        return $refund;
    }

    /** Refunds made in the Razorpay / Stripe dashboard still get a credit note (the plan is not shortened). */
    public function recordFromGateway(string $gateway, string $paymentId, string $refundId, float $amount): ?SaasRefund
    {
        if (SaasRefund::query()->where('gateway_refund_id', $refundId)->exists()) {
            return null;
        }

        $payment = SaasPayment::query()->where('gateway', $gateway)->where('gateway_payment_id', $paymentId)->first();

        if (! $payment || $amount <= 0 || $amount > $payment->refundable() + 0.001) {
            return null;
        }

        return $this->record($payment, min($amount, $payment->refundable()), 'Refunded in the '.ucfirst($gateway).' dashboard', $gateway, $refundId, 'processed', false, null);
    }

    protected function record(SaasPayment $payment, float $amount, string $reason, string $gateway, ?string $gatewayRefundId, string $status, bool $takeBack, ?User $by): SaasRefund
    {
        $refund = DB::transaction(function () use ($payment, $amount, $reason, $gateway, $gatewayRefundId, $status, $takeBack, $by) {
            $payment = SaasPayment::query()->lockForUpdate()->findOrFail($payment->id);
            $invoice = $payment->invoice ?? [];
            $rate = (float) ($invoice['tax_rate'] ?? 0);
            $tax = $this->billing->taxIn($amount, $rate);
            $ratio = (float) $payment->amount > 0 ? $amount / (float) $payment->amount : 1;
            $entity = ['country' => $invoice['seller']['country'] ?? ($payment->currency === 'AUD' ? 'AU' : 'IN')]
                + (array) config('gym.billing.entities.'.($payment->currency === 'AUD' ? 'AU' : 'IN'));

            $refund = SaasRefund::create([
                'saas_payment_id' => $payment->id,
                'gym_id' => $payment->gym_id,
                'refunded_by' => $by?->id,
                'currency' => $payment->currency,
                'amount' => $amount,
                'subtotal' => round($amount - $tax, 2),
                'tax_amount' => $tax,
                'reason' => $reason,
                'gateway' => $gateway,
                'gateway_refund_id' => $gatewayRefundId,
                'status' => $status,
                'shortened_plan' => $takeBack,
                'credit_note_number' => $this->billing->nextInvoiceNumber($entity, now(), creditNote: true),
                'credit_note' => [
                    'seller' => $invoice['seller'] ?? null,
                    'buyer' => $invoice['buyer'] ?? null,
                    'tax_rate' => $rate,
                    'tax_inclusive' => $invoice['tax_inclusive'] ?? false,
                    'tax_split' => $invoice['tax_split'] ?? null,
                    'sac' => $invoice['sac'] ?? null,
                    'original_invoice' => $payment->invoice_number,
                    'original_date' => $payment->paid_at->toDateString(),
                    'description' => $invoice['line']['description'] ?? ($payment->kind === 'credits' ? 'Message credits' : 'Software subscription'),
                ],
                'refunded_at' => now(),
            ]);

            $payment->increment('refunded_amount', $amount);

            // Partner commission follows the money, while it has not been paid out yet.
            $commission = $payment->commission;

            if ($commission && $commission->status === 'pending') {
                $left = round((float) $commission->amount * (1 - $ratio), 2);
                $payment->refundable() <= 0.001 || $left <= 0
                    ? $commission->update(['status' => 'cancelled'])
                    : $commission->update(['amount' => $left]);
            }

            if ($takeBack && $payment->gym) {
                $this->takeBack($payment, $ratio);
            }

            return $refund;
        });

        if ($refund->payment->gym) {
            $this->billing->emailOwner(
                $refund->payment->gym,
                'Credit note '.$refund->credit_note_number.' from '.config('app.name'),
                'We have refunded '.Money::format((float) $refund->amount, $refund->currency).' for invoice '.$refund->payment->invoice_number.'. Your credit note is attached.'
                    .($refund->gateway !== 'manual' ? ' The money usually reaches your account in 5 to 7 working days.' : ''),
                ['type' => 'credit_note', 'id' => $refund->id],
            );
        }

        return $refund;
    }

    /** Shortens the paid period, or removes bought credits, in proportion to the refund. */
    protected function takeBack(SaasPayment $payment, float $ratio): void
    {
        $gym = $payment->gym;

        if ($payment->kind === 'credits') {
            $bought = (int) DB::table('credit_transactions')->where('saas_payment_id', $payment->id)->where('type', 'purchase')->sum('credits');
            $remove = min((int) round($bought * $ratio), max(0, (int) $gym->message_credits));

            if ($remove > 0) {
                app(Credits::class)->add($gym, -$remove, 'adjustment', 'Refund of credit pack ('.$payment->invoice_number.')');
            }

            return;
        }

        if (! $gym->subscription_ends_at || ! $payment->period_start || ! $payment->period_end) {
            return;
        }

        $days = (int) round(Carbon::parse($payment->period_start)->diffInDays($payment->period_end) * $ratio);
        $gym->update(['subscription_ends_at' => $gym->subscription_ends_at->copy()->subDays($days)]);
    }

    /** @return array{id: string, status: string} */
    protected function sendRefund(SaasPayment $payment, float $amount, string $reason): array
    {
        $minor = (int) round($amount * 100);

        if ($payment->gateway === 'razorpay') {
            $response = $this->razorpay->post("payments/{$payment->gateway_payment_id}/refund", [
                'amount' => $minor,
                'notes' => ['reason' => mb_substr($reason, 0, 250), 'invoice' => (string) $payment->invoice_number],
            ]);

            return ['id' => $response['id'], 'status' => ($response['status'] ?? 'processed') === 'processed' ? 'processed' : 'pending'];
        }

        $intent = $payment->gateway_payment_id;

        // Auto-renew charges are stored by Stripe invoice id; find the payment behind it.
        if (str_starts_with($intent, 'in_')) {
            $invoice = $this->stripe->get("invoices/{$intent}", ['expand' => ['payments']]);
            $intent = $invoice['payments']['data'][0]['payment']['payment_intent'] ?? $invoice['payment_intent'] ?? null;

            if (! $intent) {
                throw new RuntimeException('Stripe did not return the payment for this invoice. Refund it in the Stripe dashboard; the credit note is then created automatically.');
            }
        }

        $response = $this->stripe->post('refunds', [
            'payment_intent' => $intent,
            'amount' => $minor,
            'reason' => 'requested_by_customer',
            'metadata' => ['invoice' => (string) $payment->invoice_number],
        ]);

        return ['id' => $response['id'], 'status' => ($response['status'] ?? 'succeeded') === 'succeeded' ? 'processed' : 'pending'];
    }
}
