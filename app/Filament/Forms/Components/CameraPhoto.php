<?php

namespace App\Filament\Forms\Components;

use App\Support\PhotoStore;
use Closure;
use Filament\Forms\Components\Field;
use Illuminate\Support\Facades\Storage;

/**
 * Photo field with "Take photo" (phone or laptop camera) and "Upload".
 * The browser sends a resized JPEG as a data URL; on save it is checked,
 * re-encoded and stored, and the field's value becomes the file path.
 */
class CameraPhoto extends Field
{
    protected string $view = 'filament.forms.camera-photo';

    protected string|Closure $directory = 'photos';

    protected string $facingMode = 'user';

    protected function setUp(): void
    {
        parent::setUp();

        $this->rule(fn () => function (string $attribute, $value, Closure $fail) {
            if (PhotoStore::isDataUrl($value) && ! PhotoStore::isValidImage($value)) {
                $fail('That photo could not be read. Please take or upload it again.');
            }
        });

        $this->dehydrateStateUsing(fn ($state) => PhotoStore::isDataUrl($state)
            ? PhotoStore::store($state, $this->getDirectory())
            : $state);
    }

    public function directory(string|Closure $directory): static
    {
        $this->directory = $directory;

        return $this;
    }

    /** "environment" opens the back camera on phones first (good for documents), "user" the selfie camera. */
    public function backCamera(bool $condition = true): static
    {
        $this->facingMode = $condition ? 'environment' : 'user';

        return $this;
    }

    public function getDirectory(): string
    {
        return (string) $this->evaluate($this->directory);
    }

    public function getFacingMode(): string
    {
        return $this->facingMode;
    }

    public function getStoredUrl(): ?string
    {
        $state = $this->getState();

        return is_string($state) && $state !== '' && ! PhotoStore::isDataUrl($state)
            ? Storage::disk('public')->url($state)
            : null;
    }
}
