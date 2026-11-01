<?php

namespace App\Providers;

use App\Models\User;
use App\Services\GymHealth;
use App\Services\PlatformSettings;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
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
        // Settings edited in /admin override the .env / config defaults.
        PlatformSettings::apply();

        // "Last sign-in" on the admin team page.
        Event::listen(Login::class, function ($event): void {
            if ($event->user instanceof User && $event->user->isPlatformAdmin()) {
                $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
            }
        });
    }
}
