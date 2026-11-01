<?php

namespace App\Support;

use App\Models\Gym;
use Illuminate\Database\Eloquent\Model;

/** Records what platform admins do (extend trials, add credits, change settings, ...). */
class AdminLog
{
    public const NAME = 'admin';

    public static function record(string $description, ?Model $subject = null, array $properties = []): void
    {
        $activity = activity(self::NAME)->causedBy(auth()->user())->withProperties($properties);

        if ($subject) {
            $activity->performedOn($subject);
        }

        $activity->tap(function ($activity) use ($subject) {
            $activity->gym_id = $subject?->getAttribute('gym_id') ?? ($subject instanceof Gym ? $subject->id : null);
        })->log($description);
    }
}
