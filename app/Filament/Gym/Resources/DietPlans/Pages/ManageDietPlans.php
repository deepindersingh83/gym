<?php

namespace App\Filament\Gym\Resources\DietPlans\Pages;

use App\Filament\Gym\Resources\DietPlans\DietPlanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageDietPlans extends ManageRecords
{
    protected static string $resource = DietPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
