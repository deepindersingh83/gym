<?php

namespace App\Models;

use App\Jobs\SendReviewRequest;
use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use BelongsToGym, HasFactory;

    protected static bool $scopedByBranch = true;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['checked_in_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        // Runs on the queue after the check-in is saved, so the front desk never waits on WhatsApp.
        static::created(fn (Attendance $attendance) => SendReviewRequest::dispatch($attendance->member_id)->afterCommit());
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
