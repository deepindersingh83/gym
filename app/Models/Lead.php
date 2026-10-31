<?php

namespace App\Models;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An enquiry from a prospective member. */
class Lead extends Model
{
    use Auditable, BelongsToGym;

    protected static bool $scopedByBranch = true;

    protected $guarded = ['id'];

    protected $attributes = [
        'source' => 'walk_in',
        'status' => 'new',
    ];

    protected function casts(): array
    {
        return [
            'source' => LeadSource::class,
            'status' => LeadStatus::class,
            'trial_date' => 'date',
            'follow_up_at' => 'datetime',
            'raw' => 'array',
        ];
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', LeadStatus::open());
    }
}
