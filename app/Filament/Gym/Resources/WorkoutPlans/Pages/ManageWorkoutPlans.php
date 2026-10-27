<?php

namespace App\Filament\Gym\Resources\WorkoutPlans\Pages;

use App\Filament\Gym\Resources\WorkoutPlans\WorkoutPlanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageWorkoutPlans extends ManageRecords
{
    protected static string $resource = WorkoutPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
