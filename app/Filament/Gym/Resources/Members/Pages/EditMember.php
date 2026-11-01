<?php

namespace App\Filament\Gym\Resources\Members\Pages;

use App\Filament\Gym\Resources\Members\MemberResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditMember extends EditRecord
{
    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! empty($data['consent']) && ! $this->record->consented_at) {
            $data['consented_at'] = now();
            $data['consent_source'] = 'staff';
        }
        unset($data['consent']);

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->record]);
    }
}
