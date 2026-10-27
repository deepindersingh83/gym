<?php

namespace App\Filament\Gym\Resources\Staff\Pages;

use App\Filament\Gym\Resources\Staff\StaffResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageStaff extends ManageRecords
{
    protected static string $resource = StaffResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Add staff')->using(fn (array $data) => StaffResource::createStaff($data)),
        ];
    }
}
