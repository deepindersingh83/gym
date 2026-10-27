<?php

namespace App\Filament\Gym\Resources\Attendances\Pages;

use App\Filament\Gym\Pages\CheckIn;
use App\Filament\Gym\Resources\Attendances\AttendanceResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListAttendances extends ListRecords
{
    protected static string $resource = AttendanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('checkIn')->label('Open check-in desk')->icon(Heroicon::OutlinedQrCode)->url(CheckIn::getUrl()),
        ];
    }
}
