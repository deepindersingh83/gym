<?php

namespace App\Filament\Gym\Resources\StaffAttendances\Pages;

use App\Filament\Gym\Resources\StaffAttendances\StaffAttendanceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageStaffAttendances extends ManageRecords
{
    protected static string $resource = StaffAttendanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
