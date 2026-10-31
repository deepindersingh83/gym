<?php

namespace App\Providers\Filament;

use App\Filament\Gym\Pages\Tenancy\EditGymProfile;
use App\Filament\Gym\Pages\Tenancy\RegisterGym;
use App\Http\Middleware\ApplyGymContext;
use App\Http\Middleware\CaptureReferral;
use App\Models\Gym;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/** The panel gym owners and staff work in. Each gym is a Filament tenant. */
class GymPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $panel = $panel
            ->id('gym')
            ->path('app')
            ->login()
            ->registration()
            ->passwordReset()
            ->profile()
            ->tenant(Gym::class, slugAttribute: 'slug')
            ->tenantRegistration(RegisterGym::class)
            ->tenantProfile(EditGymProfile::class)
            ->tenantMiddleware([ApplyGymContext::class], isPersistent: true)
            ->brandName(fn () => Filament::getTenant()?->name ?? config('app.name'))
            ->brandLogo(fn () => Filament::getTenant()?->logo_url)
            ->brandLogoHeight('2.5rem')
            ->colors([
                'primary' => Color::Amber,
            ])
            ->databaseNotifications()
            ->sidebarCollapsibleOnDesktop()
            ->navigationGroups([
                NavigationGroup::make('Members')->icon(Heroicon::OutlinedUserGroup),
                NavigationGroup::make('Billing')->icon(Heroicon::OutlinedBanknotes),
                NavigationGroup::make('Fitness')->icon(Heroicon::OutlinedHeart),
                NavigationGroup::make('Staff')->icon(Heroicon::OutlinedIdentification),
                NavigationGroup::make('Messaging')->icon(Heroicon::OutlinedChatBubbleLeftRight),
                NavigationGroup::make('Setup')->icon(Heroicon::OutlinedCog6Tooth),
            ])
            ->renderHook(PanelsRenderHook::GLOBAL_SEARCH_BEFORE, fn () => view('filament.gym.branch-switcher'))
            ->renderHook(PanelsRenderHook::CONTENT_START, fn () => view('filament.gym.plan-banner'))
            ->discoverResources(in: app_path('Filament/Gym/Resources'), for: 'App\Filament\Gym\Resources')
            ->discoverPages(in: app_path('Filament/Gym/Pages'), for: 'App\Filament\Gym\Pages')
            ->discoverWidgets(in: app_path('Filament/Gym/Widgets'), for: 'App\Filament\Gym\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                CaptureReferral::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);

        // e.g. GYM_TENANT_DOMAIN=yourapp.com serves each gym at {slug}.yourapp.com/app
        if ($domain = config('gym.tenant_domain')) {
            $panel->tenantDomain('{tenant:slug}.'.$domain);
        }

        return $panel;
    }
}
