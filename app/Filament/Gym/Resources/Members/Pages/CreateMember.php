<?php

namespace App\Filament\Gym\Resources\Members\Pages;

use App\Filament\Gym\Actions\MemberActions;
use App\Filament\Gym\Resources\Members\MemberResource;
use App\Filament\Gym\Support\MembershipFields;
use App\Support\Access;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CreateMember extends CreateRecord
{
    protected static string $resource = MemberResource::class;

    /** Membership details entered alongside the member; sold after the member is saved. */
    protected array $membership = [];

    public function form(Schema $schema): Schema
    {
        $schema = parent::form($schema);

        if (! Access::can('subscriptions.create')) {
            return $schema;
        }

        return $schema->components([
            ...$schema->getComponents(),
            Section::make('First membership')
                ->description('Optional. You can also add a membership later.')
                ->statePath('membership')
                ->schema(MembershipFields::make(required: false)),
        ]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->membership = $data['membership'] ?? [];
        unset($data['membership']);

        return $data;
    }

    protected function afterCreate(): void
    {
        if (filled($this->membership['membership_plan_id'] ?? null)) {
            MemberActions::sell($this->record, $this->membership);
        }
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->record]);
    }
}
