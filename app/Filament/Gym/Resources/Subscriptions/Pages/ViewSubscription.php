<?php

namespace App\Filament\Gym\Resources\Subscriptions\Pages;

use App\Filament\Gym\Actions\MemberActions;
use App\Filament\Gym\Actions\SubscriptionActions;
use App\Filament\Gym\Resources\Subscriptions\SubscriptionResource;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\ViewRecord;

class ViewSubscription extends ViewRecord
{
    protected static string $resource = SubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SubscriptionActions::invoice(),
            MemberActions::recordPayment(),
            MemberActions::renew(),
            ActionGroup::make([
                SubscriptionActions::paymentLink(),
                SubscriptionActions::autoPay(),
                SubscriptionActions::changeDates(),
                SubscriptionActions::cancel(),
            ]),
        ];
    }
}
