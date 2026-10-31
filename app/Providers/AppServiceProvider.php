<?php

namespace App\Providers;

use App\Services\GymHealth;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One health report per request, shared by the usage page and dashboard widget.
        $this->app->scoped(GymHealth::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
