<?php

namespace App\Filament\Admin\Resources\Gyms\Pages;

use App\Filament\Admin\Resources\Gyms\GymResource;
use App\Models\Gym;
use App\Services\Compliance\GymClosure;
use App\Support\AdminLog;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\EditRecord;

class EditGym extends EditRecord
{
    protected static string $resource = GymResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->label('Close account')
                ->modalDescription('The gym can no longer sign in and auto-renew stops. It can be restored for 30 days, then all its data is deleted for good.')
                ->schema([TextInput::make('reason')->label('Reason')->maxLength(200)])
                ->using(function (Gym $record, array $data) {
                    app(GymClosure::class)->close($record, $data['reason'] ?? 'Closed by platform admin');
                    AdminLog::record('Closed gym account', $record, ['reason' => $data['reason'] ?? null, 'deletes_on' => $record->deletion_scheduled_at?->toDateString()]);

                    return true;
                }),
            RestoreAction::make()
                ->using(function (Gym $record) {
                    app(GymClosure::class)->restore($record);
                    AdminLog::record('Restored gym account', $record);

                    return true;
                }),
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
