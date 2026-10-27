<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use Auditable, BelongsToGym, HasFactory;

    protected static bool $scopedByBranch = true;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Payment $payment): void {
            $payment->paid_at ??= now();
            $payment->receipt_number ??= Gym::query()->findOrFail($payment->gym_id)->nextReceiptNumber();
            $payment->received_by ??= auth()->id();

            if (! $payment->branch_id && $payment->subscription_id) {
                $payment->branch_id = Subscription::withoutGlobalScopes()->whereKey($payment->subscription_id)->value('branch_id');
            }
        });

        // Keep the subscription's paid amount (and so its dues) in step with its payments.
        $sync = function (Payment $payment): void {
            foreach (array_filter([$payment->subscription_id, $payment->getOriginal('subscription_id')]) as $id) {
                Subscription::withoutGlobalScopes()->find($id)?->refreshAmountPaid();
            }
        };

        static::saved($sync);
        static::deleted($sync);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
