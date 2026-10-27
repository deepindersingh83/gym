<?php

namespace App\Models;

use App\Enums\StaffRole;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/** A staff member's role at one gym. */
class GymUser extends Pivot
{
    use Auditable, BelongsToGym;

    protected $table = 'gym_user';

    public $incrementing = true;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'role' => StaffRole::class,
            'is_active' => 'boolean',
            'joined_on' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
