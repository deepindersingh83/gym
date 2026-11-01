<?php

namespace App\Filament\Gym\Resources\Members\Pages;

use App\Filament\Gym\Actions\MemberActions;
use App\Filament\Gym\Resources\Members\MemberResource;
use App\Filament\Gym\Support\MembershipFields;
use App\Support\Access;
use App\Support\Modules;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CreateMember extends CreateRecord
{
    protected static string $resource = MemberResource::class;

    /** Membership details entered alongside the member; sold after the member is saved. */
    protected array $membership = [];

    /** Member app password typed at the desk; applied after the member is saved. */
    protected ?string $appPassword = null;

    public function form(Schema $schema): Schema
    {
        $schema = parent::form($schema);

        return $schema->components([
            ...$schema->getComponents(),
            Section::make('Member app login')
                ->visible(fn () => Modules::enabled('member_app'))
                ->description('Optional. The member signs in at '.Filament::getPanel('member')->getLoginUrl().' with their mobile number and this password. No SMS or WhatsApp needed.')
                ->collapsible()
                ->schema([
                    TextInput::make('app_password')
                        ->label('Member app password')
                        ->password()
                        ->revealable()
                        ->minLength(8)
                        ->helperText('At least 8 characters. Let the member type it themselves.'),
                ]),
            ...(Access::can('subscriptions.create') ? [
                Section::make('First membership')
                    ->description('Optional. You can also add a membership later.')
                    ->statePath('membership')
                    ->schema(MembershipFields::make(required: false)),
            ] : []),
        ]);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->membership = $data['membership'] ?? [];
        $this->appPassword = $data['app_password'] ?? null;
        unset($data['membership'], $data['app_password']);

        if (! empty($data['consent'])) {
            $data['consented_at'] = now();
            $data['consent_source'] = 'staff';
        }
        unset($data['consent']);

        return $data;
    }

    protected function afterCreate(): void
    {
        if (filled($this->appPassword)) {
            MemberActions::appUserFor($this->record)->update(['password' => $this->appPassword]);
        }

        if (filled($this->membership['membership_plan_id'] ?? null)) {
            MemberActions::sell($this->record, $this->membership);
        }
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->record]);
    }
}
