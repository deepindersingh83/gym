<?php

namespace App\Filament\Gym\Resources\MembershipPlans\Pages;

use App\Filament\Gym\Resources\MembershipPlans\MembershipPlanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageMembershipPlans extends ManageRecords
{
    protected static string $resource = MembershipPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
