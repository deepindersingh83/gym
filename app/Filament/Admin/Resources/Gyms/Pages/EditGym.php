<?php

namespace App\Filament\Admin\Resources\Gyms\Pages;

use App\Filament\Admin\Resources\Gyms\GymResource;
use App\Support\AdminLog;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditGym extends EditRecord
{
    protected static string $resource = GymResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->label('Close account')->modalDescription('The gym can no longer sign in. Its data is kept and can be restored.')
                ->after(fn () => AdminLog::record('Closed gym account', $this->getRecord())),
            RestoreAction::make()->after(fn () => AdminLog::record('Restored gym account', $this->getRecord())),
        ];
    }

    protected function afterSave(): void
    {
        $changes = collect($this->getRecord()->getChanges())->except(['updated_at'])->keys()->all();

        if ($changes) {
            AdminLog::record('Edited gym', $this->getRecord(), ['fields' => $changes]);
        }
    }
}
