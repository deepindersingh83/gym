<?php

namespace App\Filament\Gym\Resources\Members\Pages;

use App\Filament\Gym\Actions\MemberActions;
use App\Filament\Gym\Resources\Members\MemberResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewMember extends ViewRecord
{
    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            MemberActions::renew(),
            MemberActions::recordPayment(),
            MemberActions::sellMembership(),
            ActionGroup::make([
                MemberActions::checkIn(),
                MemberActions::whatsapp(),
                MemberActions::invite(),
                MemberActions::setAppPassword(),
                EditAction::make(),
                MemberActions::downloadData(),
                MemberActions::erase(),
            ]),
        ];
    }
}
