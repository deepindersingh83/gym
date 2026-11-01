<?php

namespace App\Filament\Admin\Resources\AdminLogs\Pages;

use App\Filament\Admin\Resources\AdminLogs\AdminLogResource;
use Filament\Resources\Pages\ListRecords;

class ListAdminLogs extends ListRecords
{
    protected static string $resource = AdminLogResource::class;
}
