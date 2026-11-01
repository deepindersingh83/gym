<?php

namespace App\Console\Commands;

use App\Services\Compliance\GymClosure;
use Illuminate\Console\Command;

class PurgeClosedGyms extends Command
{
    protected $signature = 'gym:purge-closed';

    protected $description = 'Permanently delete gym accounts that were closed more than 30 days ago';

    public function handle(GymClosure $closure): int
    {
        $count = $closure->purgeDue();
        $this->info("Deleted {$count} closed gym ".str('account')->plural($count).'.');

        return self::SUCCESS;
    }
}
