<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** A reseller or affiliate who refers gyms to the platform for a commission. */
class Partner extends Model
{
    protected $guarded = ['id'];

    protected $attributes = [
        'commission_rate' => 20,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'commission_rate' => 'decimal:2',
            'payout_details' => 'encrypted',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Partner $partner): void {
            $partner->code = strtoupper($partner->code ?: static::uniqueCode($partner->name));
        });
    }

    public static function uniqueCode(string $name): string
    {
        $base = strtoupper(Str::limit(preg_replace('/[^A-Za-z0-9]/', '', $name) ?: 'PARTNER', 8, ''));

        do {
            $code = $base.random_int(10, 99);
        } while (static::query()->where('code', $code)->exists());

        return $code;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function gyms(): HasMany
    {
        return $this->hasMany(Gym::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(PartnerCommission::class);
    }

    public function referralUrl(): string
    {
        return route('home', ['ref' => $this->code]);
    }
}
