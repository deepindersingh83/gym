<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    use Auditable, BelongsToGym, HasFactory;

    protected $guarded = ['id'];

    protected $attributes = [
        'category' => 'offer',
        'used_count' => 0,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'valid_from' => 'date',
            'valid_until' => 'date',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(fn (Coupon $coupon) => $coupon->code = strtoupper(trim($coupon->code)));
    }

    public function isUsable(): bool
    {
        $today = today();

        return $this->is_active
            && (! $this->valid_from || $this->valid_from->lte($today))
            && (! $this->valid_until || $this->valid_until->gte($today))
            && ($this->max_uses === null || $this->used_count < $this->max_uses);
    }

    public function discountFor(float $amount): float
    {
        $discount = $this->discount_type === 'percent'
            ? $amount * (float) $this->value / 100
            : (float) $this->value;

        return round(min($discount, $amount), 2);
    }
}
