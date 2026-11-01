<?php

namespace App\Support;

use GdImage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Turns a photo sent from the browser (data URL) into a clean JPEG on the
 * public disk: decoded, checked to really be an image, resized and
 * re-encoded, which also drops EXIF data such as GPS location.
 */
class PhotoStore
{
    public const MAX_BYTES = 8 * 1024 * 1024;

    public const MAX_SIDE = 1000;

    public static function isDataUrl(mixed $value): bool
    {
        return is_string($value) && str_starts_with($value, 'data:image/');
    }

    public static function isValidImage(string $dataUrl): bool
    {
        return static::decode($dataUrl) !== null;
    }

    public static function store(string $dataUrl, string $directory): string
    {
        $image = static::decode($dataUrl) ?? throw new RuntimeException('Invalid image.');

        [$width, $height] = [imagesx($image), imagesy($image)];
        $scale = min(1, self::MAX_SIDE / max($width, $height));
        $resized = imagecreatetruecolor(max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)));
        imagefill($resized, 0, 0, imagecolorallocate($resized, 255, 255, 255));
        imagecopyresampled($resized, $image, 0, 0, 0, 0, imagesx($resized), imagesy($resized), $width, $height);

        ob_start();
        imagejpeg($resized, null, 85);
        $jpeg = ob_get_clean();

        $path = trim($directory, '/').'/'.Str::uuid().'.jpg';
        Storage::disk('public')->put($path, $jpeg, 'public');

        return $path;
    }

    protected static function decode(string $dataUrl): ?GdImage
    {
        if (! preg_match('#^data:image/(jpeg|jpg|png|webp);base64,#', $dataUrl, $m) || strlen($dataUrl) > self::MAX_BYTES * 1.4) {
            return null;
        }

        $binary = base64_decode(substr($dataUrl, strlen($m[0])), true);

        if ($binary === false || strlen($binary) > self::MAX_BYTES || @getimagesizefromstring($binary) === false) {
            return null;
        }

        $image = @imagecreatefromstring($binary);

        return $image instanceof GdImage ? $image : null;
    }
}
