<?php

namespace App\Models;

use App\Enums\MessageChannel;
use App\Enums\MessageEvent;
use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageLog extends Model
{
    use BelongsToGym;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'event' => MessageEvent::class,
            'channel' => MessageChannel::class,
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
