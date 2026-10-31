<?php

namespace App\Filament\Admin\Resources\SaasPayments\Pages;

use App\Filament\Admin\Resources\SaasPayments\SaasPaymentResource;
use Filament\Resources\Pages\ListRecords;

class ListSaasPayments extends ListRecords
{
    protected static string $resource = SaasPaymentResource::class;
}
