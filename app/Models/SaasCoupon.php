<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Discount code for the software plans gyms buy from the platform. */
class SaasCoupon extends Model
{
    protected $guarded = ['id'];

    protected $attributes = [
        'redemptions' => 0,
        'is_active' => true,
        'first_payment_only' => false,
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'plans' => 'array',
            'first_payment_only' => 'boolean',
            'valid_until' => 'date',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(fn (SaasCoupon $coupon) => $coupon->code = strtoupper(trim($coupon->code)));
    }

    /** Why the code can't be used for this purchase, or null when it can. */
    public function problemFor(Gym $gym, SaasPlan $plan): ?string
    {
        return match (true) {
            ! $this->is_active => 'This code is no longer active.',
            $this->valid_until && $this->valid_until->isPast() && ! $this->valid_until->isToday() => 'This code has expired.',
            $this->max_redemptions !== null && $this->redemptions >= $this->max_redemptions => 'This code has been fully used.',
            ! empty($this->plans) && ! in_array($plan->slug, $this->plans, true) => "This code doesn't apply to the {$plan->name} plan.",
            $this->first_payment_only && $gym->saasPayments()->where('kind', 'subscription')->exists() => 'This code is for a first payment only.',
            $this->discount_type === 'fixed' && $this->currency && $this->currency !== $gym->currency => "This code can't be used in {$gym->currency}.",
            default => null,
        };
    }

    public function discountFor(float $amount): float
    {
        $discount = $this->discount_type === 'percent' ? $amount * (float) $this->value / 100 : (float) $this->value;

        return round(min($amount, max(0, $discount)), 2);
    }
}
