<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** A payment a gym made to the platform (software plan or message credits). */
class SaasPayment extends Model
{
    protected $guarded = ['id'];

    protected $attributes = [
        'kind' => 'subscription',
        'gateway' => 'manual',
        'currency' => 'INR',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'invoice' => 'array',
            'period_start' => 'date',
            'period_end' => 'date',
        ];
    }

    protected static function booted(): void
    {
        // Referred gyms earn their partner a commission on every software payment.
        static::created(function (SaasPayment $payment): void {
            $partner = $payment->gym?->partner;

            if ($payment->kind !== 'subscription' || ! $partner?->is_active) {
                return;
            }

            $payment->commission()->create([
                'partner_id' => $partner->id,
                'gym_id' => $payment->gym_id,
                'rate' => $partner->commission_rate,
                'amount' => round((float) $payment->amount * (float) $partner->commission_rate / 100, 2),
            ]);
        });
    }

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    public function saasPlan(): BelongsTo
    {
        return $this->belongsTo(SaasPlan::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(SaasCoupon::class, 'saas_coupon_id');
    }

    public function commission(): HasOne
    {
        return $this->hasOne(PartnerCommission::class);
    }
}
