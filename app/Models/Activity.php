<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Spatie\Activitylog\Models\Activity as BaseActivity;

/** Audit log entry, scoped to a gym like every other gym-owned row. */
class Activity extends BaseActivity
{
    use BelongsToGym;
}
