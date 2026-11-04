<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A member who brought a friend, and the free days each of them got. */
class MemberReferral extends Model
{
    use BelongsToGym;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['rewarded_at' => 'datetime'];
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'referrer_id')->withoutGlobalScopes();
    }

    public function referred(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'referred_id')->withoutGlobalScopes();
    }
}
