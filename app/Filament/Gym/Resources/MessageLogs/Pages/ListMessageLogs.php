<?php

namespace App\Filament\Gym\Resources\MessageLogs\Pages;

use App\Filament\Gym\Resources\MessageLogs\MessageLogResource;
use Filament\Resources\Pages\ListRecords;

class ListMessageLogs extends ListRecords
{
    protected static string $resource = MessageLogResource::class;
}
