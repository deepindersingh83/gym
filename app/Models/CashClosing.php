<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToGym;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class CashClosing extends Model
{
    use Auditable, BelongsToGym;

    protected static bool $scopedByBranch = true;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'closing_date' => 'date',
            'opening_balance' => 'decimal:2',
            'cash_collected' => 'decimal:2',
            'cash_expenses' => 'decimal:2',
            'expected_cash' => 'decimal:2',
            'counted_cash' => 'decimal:2',
            'difference' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (CashClosing $closing) => $closing->closed_by ??= auth()->id());

        static::saving(function (CashClosing $closing): void {
            // "saving" runs before BelongsToGym's "creating" hook stamps the gym.
            $closing->gym_id ??= Tenancy::gymId();
            $closing->fill(static::systemFigures($closing->gym_id, $closing->branch_id, $closing->closing_date));
            $closing->expected_cash = round((float) $closing->opening_balance + (float) $closing->cash_collected - (float) $closing->cash_expenses, 2);
            $closing->difference = round((float) $closing->counted_cash - (float) $closing->expected_cash, 2);
        });
    }

    /** @return array{cash_collected: float, cash_expenses: float} */
    public static function systemFigures(int $gymId, int $branchId, Carbon|string $date): array
    {
        $date = Carbon::parse($date)->toDateString();

        return [
            'cash_collected' => round((float) Payment::withoutGlobalScopes()
                ->where('gym_id', $gymId)->where('branch_id', $branchId)
                ->where('method', PaymentMethod::Cash)
                ->whereDate('paid_at', $date)
                ->sum('amount'), 2),
            'cash_expenses' => round((float) Expense::withoutGlobalScopes()
                ->where('gym_id', $gymId)->where('branch_id', $branchId)
                ->where('method', 'cash')
                ->whereDate('spent_on', $date)
                ->sum('amount'), 2),
        ];
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
