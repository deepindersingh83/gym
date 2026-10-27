<?php

namespace App\Models;

use App\Enums\PlanType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class MembershipPlan extends Model
{
    use Auditable, BelongsToGym, HasFactory;

    protected $guarded = ['id'];

    protected $attributes = [
        'duration_unit' => 'month',
        'admission_fee' => 0,
        'max_persons' => 1,
        'gst_rate' => 18,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'type' => PlanType::class,
            'price' => 'decimal:2',
            'admission_fee' => 'decimal:2',
            'gst_rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /** Last day of a membership on this plan that starts on $start. */
    public function endDateFrom(Carbon $start): Carbon
    {
        $start = $start->copy()->startOfDay();

        return $this->duration_unit === 'day'
            ? $start->addDays(max(1, $this->duration_value) - 1)
            : $start->addMonthsNoOverflow($this->duration_value)->subDay();
    }

    public function getDurationLabelAttribute(): string
    {
        $unit = $this->duration_unit === 'day' ? 'day' : 'month';

        return $this->duration_value.' '.str($unit)->plural($this->duration_value);
    }
}
