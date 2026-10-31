<?php

namespace App\Jobs;

use App\Models\Gym;
use App\Services\MetaLeadAds;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ImportMetaLead implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public array $backoff = [30, 120, 600];

    public function __construct(public int $gymId, public string $leadgenId) {}

    public function handle(MetaLeadAds $meta): void
    {
        $meta->import(Gym::query()->findOrFail($this->gymId), $this->leadgenId);
    }
}
