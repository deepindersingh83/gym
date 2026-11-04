<?php

namespace App\Jobs;

use App\Models\GymDataExport;
use App\Services\Compliance\GymDataExport as Exporter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/** Builds the gym's data ZIP off the web request, then emails a 7-day download link. */
class BuildGymDataExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 1800;

    public function __construct(public int $exportId) {}

    public function handle(Exporter $exporter): void
    {
        $export = GymDataExport::query()->with(['gym', 'user'])->find($this->exportId);

        if (! $export || ! $export->gym) {
            return;
        }

        $temp = $exporter->build($export->gym);
        $path = "data-exports/{$export->gym_id}/".Str::uuid().'.zip';
        $stream = fopen($temp, 'r');
        Storage::disk('local')->writeStream($path, $stream);
        fclose($stream);
        $size = filesize($temp);
        @unlink($temp);

        $export->update(['status' => 'ready', 'path' => $path, 'size' => $size, 'expires_at' => now()->addDays(GymDataExport::KEEP_DAYS), 'error' => null]);

        $to = $export->user?->email;

        if ($to) {
            SendPlatformEmail::dispatch(
                $to,
                'Your data export for '.$export->gym->name.' is ready',
                "Hi {$export->user->name},\n\nThe ZIP with all of {$export->gym->name}'s data is ready (".number_format($size / 1048576, 1)." MB).\n\n"
                    .'Download it here: '.$export->downloadUrl()."\n\nThe link works for ".GymDataExport::KEEP_DAYS." days and needs you to be signed in. It contains members' personal data, so keep the file safe.\n\n".config('app.name'),
            );
        }
    }

    public function failed(Throwable $e): void
    {
        GymDataExport::query()->whereKey($this->exportId)->update(['status' => 'failed', 'error' => Str::limit($e->getMessage(), 500)]);
    }
}
