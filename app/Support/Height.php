<?php

namespace App\Support;

use App\Models\Gym;

/**
 * Heights are always stored in centimetres; each gym chooses whether staff and
 * members see and enter them in centimetres or in feet and inches.
 */
class Height
{
    public const CM_PER_INCH = 2.54;

    public const UNITS = [
        'cm' => 'Centimetres (170 cm)',
        'ft' => 'Feet and inches (5′ 7″)',
    ];

    public static function unit(?Gym $gym = null): string
    {
        $unit = ($gym ?? Tenancy::gym())?->setting('height_unit');

        return array_key_exists((string) $unit, self::UNITS) ? $unit : 'cm';
    }

    public static function usesFeet(?Gym $gym = null): bool
    {
        return static::unit($gym) === 'ft';
    }

    public static function fromFeet(mixed $feet, mixed $inches): ?float
    {
        if (blank($feet) && blank($inches)) {
            return null;
        }

        return round(((float) $feet * 12 + (float) $inches) * self::CM_PER_INCH, 1);
    }

    /** @return array{0: int, 1: float} Whole feet and remaining inches (one decimal). */
    public static function toFeet(float $cm): array
    {
        $totalInches = round($cm / self::CM_PER_INCH, 1);
        $feet = (int) floor($totalInches / 12);
        $inches = round($totalInches - $feet * 12, 1);

        if ($inches >= 12) {
            [$feet, $inches] = [$feet + 1, 0.0];
        }

        return [$feet, $inches];
    }

    /** "170 cm" or "5′ 7″", in the gym's chosen unit. */
    public static function format(mixed $cm, ?Gym $gym = null): ?string
    {
        if (blank($cm)) {
            return null;
        }

        if (! static::usesFeet($gym)) {
            return rtrim(rtrim(number_format((float) $cm, 1, '.', ''), '0'), '.').' cm';
        }

        [$feet, $inches] = static::toFeet((float) $cm);

        return $feet.'′ '.rtrim(rtrim(number_format($inches, 1, '.', ''), '0'), '.').'″';
    }
}
