<?php

namespace App\Filament\Admin\Resources\SaasPlans\Pages;

use App\Filament\Admin\Resources\SaasPlans\SaasPlanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSaasPlans extends ManageRecords
{
    protected static string $resource = SaasPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
