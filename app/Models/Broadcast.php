<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Broadcast extends Model
{
    use BelongsToGym;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'channels' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (Broadcast $broadcast) => $broadcast->created_by ??= auth()->id());
    }

    public function logs(): HasMany
    {
        return $this->hasMany(MessageLog::class);
    }
}
