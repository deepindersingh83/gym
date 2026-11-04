<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;

/** A ZIP of all of a gym's data, prepared in the background. */
class GymDataExport extends Model
{
    public const KEEP_DAYS = 7;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'downloaded_at' => 'datetime'];
    }

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isReady(): bool
    {
        return $this->status === 'ready' && $this->expires_at?->isFuture();
    }

    /** Signed link that stops working when the file is removed. */
    public function downloadUrl(): string
    {
        return URL::temporarySignedRoute('data-exports.download', $this->expires_at ?? now()->addDays(self::KEEP_DAYS), ['export' => $this->id]);
    }
}
