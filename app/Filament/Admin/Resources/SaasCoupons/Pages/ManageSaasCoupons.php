<?php

namespace App\Filament\Admin\Resources\SaasCoupons\Pages;

use App\Filament\Admin\Resources\SaasCoupons\SaasCouponResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSaasCoupons extends ManageRecords
{
    protected static string $resource = SaasCouponResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
