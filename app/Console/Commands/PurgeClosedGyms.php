<?php

namespace App\Console\Commands;

use App\Services\Compliance\GymClosure;
use App\Services\Compliance\GymDataExport;
use Illuminate\Console\Command;

class PurgeClosedGyms extends Command
{
    protected $signature = 'gym:purge-closed';

    protected $description = 'Permanently delete gym accounts closed more than 30 days ago, and expired data downloads';

    public function handle(GymClosure $closure): int
    {
        $count = $closure->purgeDue();
        $this->info("Deleted {$count} closed gym ".str('account')->plural($count).'.');

        $exports = app(GymDataExport::class)->pruneExpired();
        $this->info("Removed {$exports} expired data ".str('download')->plural($exports).'.');

        return self::SUCCESS;
    }
}
