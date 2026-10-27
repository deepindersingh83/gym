<?php

namespace App\Models\Concerns;

use App\Models\Branch;
use App\Models\Gym;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Keeps each gym's rows separate: queries are limited to the current gym (and
 * to the user's branch for models with a branch_id), and new rows are stamped
 * with the current gym.
 *
 * Models opt into branch scoping with `protected static bool $scopedByBranch = true;`.
 */
trait BelongsToGym
{
    public static function bootBelongsToGym(): void
    {
        static::addGlobalScope('gym', function (Builder $query): void {
            $model = $query->getModel();

            if ($gymId = Tenancy::gymId()) {
                $query->where($model->qualifyColumn('gym_id'), $gymId);
            }

            if (($model::$scopedByBranch ?? false) && ($branchId = Tenancy::branchId())) {
                $query->where($model->qualifyColumn('branch_id'), $branchId);
            }
        });

        static::creating(function (Model $model): void {
            if (! $model->gym_id && ($gymId = Tenancy::gymId())) {
                $model->gym_id = $gymId;
            }

            if (($model::$scopedByBranch ?? false) && ! $model->branch_id && ($branchId = Tenancy::branchId())) {
                $model->branch_id = $branchId;
            }
        });
    }

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
