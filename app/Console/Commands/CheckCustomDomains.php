<?php

namespace App\Console\Commands;

use App\Models\Gym;
use App\Services\CustomDomains;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('gym:check-domains')]
#[Description('Activate gym custom domains whose DNS and SSL are ready')]
class CheckCustomDomains extends Command
{
    public function handle(CustomDomains $domains): int
    {
        Gym::query()->where('custom_domain_status', 'pending')->each(function (Gym $gym) use ($domains) {
            $this->line($gym->custom_domain.': '.$domains->refresh($gym));
        });

        return self::SUCCESS;
    }
}
