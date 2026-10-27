<?php

namespace App\Filament\Gym\Resources\CashClosings\Pages;

use App\Filament\Gym\Resources\CashClosings\CashClosingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCashClosings extends ManageRecords
{
    protected static string $resource = CashClosingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
