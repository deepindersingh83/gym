<?php

namespace App\Filament\Gym\Resources\MessageTemplates\Pages;

use App\Filament\Gym\Resources\MessageTemplates\MessageTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageMessageTemplates extends ManageRecords
{
    protected static string $resource = MessageTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
