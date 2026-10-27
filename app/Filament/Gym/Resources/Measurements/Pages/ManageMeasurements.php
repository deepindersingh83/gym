<?php

namespace App\Filament\Gym\Resources\Measurements\Pages;

use App\Filament\Gym\Resources\Measurements\MeasurementResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageMeasurements extends ManageRecords
{
    protected static string $resource = MeasurementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
