<?php

namespace App\Filament\Gym\Pages\Tenancy;

use App\Enums\MessageChannel;
use App\Filament\Gym\Schemas\GymDetailsFields;
use App\Support\Access;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\Tenancy\EditTenantProfile;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

/** Gym settings: details, branding, opening hours, reminders and integrations. */
class EditGymProfile extends EditTenantProfile
{
    public static function getLabel(): string
    {
        return 'Gym settings';
    }

    public static function canView(Model $tenant): bool
    {
        return Access::can('settings.edit');
    }

    public function form(Schema $schema): Schema
    {
        $gym = $this->tenant;

        return $schema->components([
            Tabs::make()->tabs([
                Tab::make('Details')->schema([...GymDetailsFields::basics(), ...GymDetailsFields::location()]),
                Tab::make('Branding')->schema([
                    ...GymDetailsFields::branding(),
                    Section::make('Public signup page')
                        ->description(fn () => 'New members can join at '.route('join.show', $gym))
                        ->schema([
                            Toggle::make('settings.allow_online_signup')->label('Allow online signup')->default(true),
                        ]),
                ]),
                Tab::make('Opening hours')->schema(GymDetailsFields::openingHours()),
                Tab::make('Invoices')->schema([
                    Grid::make(3)->schema([
                        TextInput::make('invoice_prefix')->required()->maxLength(10),
                        TextInput::make('member_code_prefix')->required()->maxLength(10),
                        TextInput::make('currency')->required()->length(3)->default('INR'),
                    ]),
                ]),
                Tab::make('Reminders')->schema([
                    TagsInput::make('settings.reminder_days_before')
                        ->label('Send renewal reminders this many days before expiry')
                        ->placeholder('e.g. 7')
                        ->default(config('gym.defaults.reminder_days_before')),
                    TextInput::make('settings.dues_reminder_every_days')
                        ->label('Repeat pending-dues reminders every (days)')
                        ->numeric()->minValue(1)
                        ->default(config('gym.defaults.dues_reminder_every_days')),
                    CheckboxList::make('settings.channels')
                        ->label('Send reminders by')
                        ->options(MessageChannel::class)
                        ->default(config('gym.defaults.channels')),
                    Grid::make(3)->schema([
                        Toggle::make('settings.send_birthday_wishes')->label('Birthday wishes')->default(true),
                        Toggle::make('settings.send_anniversary_wishes')->label('Anniversary wishes')->default(true),
                        Toggle::make('settings.send_payment_receipts')->label('Payment receipts')->default(true),
                    ]),
                ]),
                Tab::make('Integrations')->schema([
                    Section::make('Razorpay (online payments and UPI AutoPay)')
                        ->description(fn () => 'Webhook URL: '.route('webhooks.razorpay', $gym).' — events: payment_link.paid, subscription.charged')
                        ->schema([
                            Grid::make(2)->schema([
                                TextInput::make('integrations.razorpay_key_id')->label('Key ID'),
                                TextInput::make('integrations.razorpay_key_secret')->label('Key secret')->password()->revealable(),
                                TextInput::make('integrations.razorpay_webhook_secret')->label('Webhook secret')->password()->revealable(),
                            ]),
                        ]),
                    Section::make('WhatsApp Cloud API')
                        ->description('Optional. Leave empty to use the platform WhatsApp number.')
                        ->schema([
                            Grid::make(2)->schema([
                                TextInput::make('integrations.whatsapp_phone_number_id')->label('Phone number ID'),
                                TextInput::make('integrations.whatsapp_token')->label('Access token')->password()->revealable(),
                            ]),
                        ]),
                    Section::make('SMS (MSG91)')
                        ->description('Optional. Leave empty to use the platform SMS account.')
                        ->schema([
                            Grid::make(2)->schema([
                                TextInput::make('integrations.msg91_auth_key')->label('Auth key')->password()->revealable(),
                                TextInput::make('integrations.msg91_sender')->label('Sender ID'),
                            ]),
                        ]),
                ]),
            ])->persistTabInQueryString(),
        ]);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['settings'] = [...($this->tenant->settings ?? []), ...($data['settings'] ?? [])];
        $data['settings']['reminder_days_before'] = array_values(array_unique(array_map('intval', $data['settings']['reminder_days_before'] ?? [])));
        $data['integrations'] = array_filter([...($this->tenant->integrations ?? []), ...($data['integrations'] ?? [])], fn ($v) => filled($v));

        return $data;
    }
}
