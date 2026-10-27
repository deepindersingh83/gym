<?php

namespace App\Filament\Gym\Resources\Broadcasts\Pages;

use App\Filament\Gym\Resources\Broadcasts\BroadcastResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageBroadcasts extends ManageRecords
{
    protected static string $resource = BroadcastResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
