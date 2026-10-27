<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    use Auditable, BelongsToGym, HasFactory;

    protected static bool $scopedByBranch = true;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'plan_price' => 'decimal:2',
            'admission_fee' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'taxable_amount' => 'decimal:2',
            'gst_rate' => 'decimal:2',
            'cgst_amount' => 'decimal:2',
            'sgst_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'auto_renew' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Subscription $subscription): void {
            $subscription->invoice_number ??= Gym::query()->findOrFail($subscription->gym_id)->nextInvoiceNumber();
        });

        static::created(function (Subscription $subscription): void {
            if ($subscription->coupon_id) {
                Coupon::withoutGlobalScopes()->whereKey($subscription->coupon_id)->increment('used_count');
            }
        });
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MembershipPlan::class, 'membership_plan_id');
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function renewedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'renewed_from_id');
    }

    /** Extra people on a couple / family plan. */
    public function additionalMembers(): BelongsToMany
    {
        return $this->belongsToMany(Member::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function getBalanceDueAttribute(): float
    {
        return round(max(0, (float) $this->total_amount - (float) $this->amount_paid), 2);
    }

    public function getDaysLeftAttribute(): int
    {
        return (int) today()->diffInDays($this->end_date, false);
    }

    public function refreshAmountPaid(): void
    {
        $this->forceFill(['amount_paid' => $this->payments()->withoutGlobalScopes()->sum('amount')])->saveQuietly();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', SubscriptionStatus::Active);
    }

    public function scopeWithDues(Builder $query): Builder
    {
        return $query->where('status', '!=', SubscriptionStatus::Cancelled)
            ->whereColumn('amount_paid', '<', 'total_amount');
    }

    public function scopeEndingBetween(Builder $query, $from, $to): Builder
    {
        return $query->whereBetween('end_date', [$from->toDateString(), $to->toDateString()]);
    }
}
