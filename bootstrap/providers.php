<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\GymPanelProvider;
use App\Providers\Filament\MemberPanelProvider;
use App\Providers\Filament\PartnerPanelProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    GymPanelProvider::class,
    MemberPanelProvider::class,
    PartnerPanelProvider::class,
];
