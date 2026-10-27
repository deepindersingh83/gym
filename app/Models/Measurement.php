<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Measurement extends Model
{
    use BelongsToGym, HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'recorded_on' => 'date',
            'weight_kg' => 'decimal:1',
            'height_cm' => 'decimal:1',
            'bmi' => 'decimal:1',
            'body_fat_pct' => 'decimal:1',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Measurement $measurement): void {
            $measurement->recorded_by ??= auth()->id();
            $measurement->height_cm ??= Member::withoutGlobalScopes()->whereKey($measurement->member_id)->value('height_cm');
            $measurement->bmi = static::bmiFor($measurement->weight_kg, $measurement->height_cm);
        });

        // The member profile always shows the latest weight and height.
        static::saved(function (Measurement $measurement): void {
            $latest = static::withoutGlobalScopes()->where('member_id', $measurement->member_id)
                ->latest('recorded_on')->latest('id')->first();

            Member::withoutGlobalScopes()->whereKey($measurement->member_id)->update(array_filter([
                'weight_kg' => $latest?->weight_kg,
                'height_cm' => $latest?->height_cm,
            ], fn ($value) => $value !== null));
        });
    }

    public static function bmiFor(mixed $weightKg, mixed $heightCm): ?float
    {
        if (! $weightKg || ! $heightCm) {
            return null;
        }

        $metres = (float) $heightCm / 100;

        return round((float) $weightKg / ($metres * $metres), 1);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
