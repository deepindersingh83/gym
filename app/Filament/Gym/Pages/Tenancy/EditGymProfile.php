<?php

namespace App\Filament\Gym\Pages\Tenancy;

use App\Enums\MessageChannel;
use App\Filament\Gym\Schemas\GymDetailsFields;
use App\Services\MetaLeadAds;
use App\Support\Access;
use App\Support\Height;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Tenancy\EditTenantProfile;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Throwable;

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
                Tab::make('Website')->schema([
                    Section::make('Your gym website')
                        ->description(fn () => 'Live at '.$gym->websiteUrl())
                        ->columns(2)
                        ->schema([
                            Toggle::make('website.enabled')->label('Website is public')->default(true)->columnSpanFull(),
                            TextInput::make('website.tagline')->label('Headline')->placeholder('e.g. Pune\'s friendliest strength gym')->maxLength(90),
                            TextInput::make('website.seo_description')->label('Search engine description')->maxLength(160)
                                ->helperText('Shown by Google under your gym\'s name. Up to 160 characters.'),
                            Textarea::make('website.about')->label('About the gym')->rows(5)->maxLength(3000)->columnSpanFull(),
                            FileUpload::make('website.cover')->label('Cover photo')->image()->disk('public')
                                ->directory(fn () => 'websites/'.$gym->id)->visibility('public')->maxSize(4096)->imageEditor(),
                            FileUpload::make('website.gallery')->label('Gallery (up to 12 photos)')->image()->multiple()->reorderable()
                                ->maxFiles(12)->disk('public')->directory(fn () => 'websites/'.$gym->id)->visibility('public')->maxSize(4096),
                            Toggle::make('website.show_trainers')->label('Show trainers')->default(true)
                                ->helperText('Choose which trainers appear under Staff → edit → Show on website.'),
                            Toggle::make('website.show_plans')->label('Show membership plans and prices')->default(true),
                            TextInput::make('website.map_url')->label('Google Maps link')->url()->placeholder('https://maps.app.goo.gl/...'),
                            TextInput::make('website.instagram')->label('Instagram link')->url(),
                            TextInput::make('website.facebook')->label('Facebook link')->url(),
                            TextInput::make('website.youtube')->label('YouTube link')->url(),
                        ]),
                ]),
                Tab::make('Units')->schema([
                    Radio::make('settings.height_unit')
                        ->label('Show and enter member height in')
                        ->options(Height::UNITS)
                        ->default('cm')
                        ->helperText('Heights are saved in centimetres, so switching later keeps every member\'s data and BMI correct.'),
                ]),
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
                Tab::make('Google reviews')->schema([
                    Toggle::make('settings.review_request_enabled')->label('Ask happy members for a Google review')->live(),
                    TextInput::make('settings.google_review_url')
                        ->label('Your Google review link')
                        ->url()
                        ->placeholder('https://g.page/r/.../review')
                        ->helperText('In Google Business Profile, choose "Ask for reviews" and copy the link.')
                        ->required(fn ($get) => (bool) $get('settings.review_request_enabled')),
                    TextInput::make('settings.review_request_after_visits')
                        ->label('Send after this many check-ins')
                        ->numeric()->minValue(2)->maxValue(200)->default(10)
                        ->helperText('Sent once per member, through your reminder channels. Edit the text under Messaging → Message templates.'),
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
                    Section::make('Facebook & Instagram lead ads')
                        ->key('facebookLeads')
                        ->description('Leads from your Facebook and Instagram lead forms appear in Enquiries automatically.')
                        ->headerActions([
                            Action::make('connectFacebook')
                                ->label('Connect page')
                                ->icon(Heroicon::OutlinedLink)
                                ->requiresConfirmation()
                                ->modalDescription('Save the page ID and token first. This subscribes your page so its lead-form submissions are sent here.')
                                ->action(function () {
                                    try {
                                        app(MetaLeadAds::class)->connectPage($this->tenant->fresh());
                                        Notification::make()->title('Facebook page connected')->body('New lead-form submissions will appear in Enquiries.')->success()->send();
                                    } catch (Throwable $e) {
                                        Notification::make()->title('Could not connect the page')->body($e->getMessage())->danger()->send();
                                    }
                                }),
                        ])
                        ->schema([
                            Grid::make(2)->schema([
                                TextInput::make('facebook_page_id')->label('Facebook page ID')->maxLength(40)
                                    ->helperText('Facebook page → About → Page transparency → Page ID. Instagram ads run from this page too.'),
                                TextInput::make('integrations.facebook_page_token')->label('Page access token')->password()->revealable()
                                    ->helperText('A long-lived page token with the leads_retrieval permission.'),
                            ]),
                        ]),
                    Section::make('Lead webhook (Zapier, Make, Pabbly)')
                        ->key('leadWebhook')
                        ->description('Send enquiries from any other tool to this address as JSON or a form post: name, phone, email, source, message.')
                        ->headerActions([
                            Action::make('leadWebhook')
                                ->label(fn () => $this->tenant->lead_webhook_token ? 'New address' : 'Create address')
                                ->icon(Heroicon::OutlinedKey)
                                ->requiresConfirmation(fn () => (bool) $this->tenant->lead_webhook_token)
                                ->modalDescription('The old address stops working. Update it in Zapier / Make.')
                                ->action(function () {
                                    $this->tenant->forceFill(['lead_webhook_token' => Str::random(40)])->save();
                                    Notification::make()->title('Webhook address ready')->body(route('webhooks.leads', $this->tenant->lead_webhook_token))->success()->persistent()->send();
                                }),
                        ])
                        ->schema([
                            Text::make(fn () => $this->tenant->lead_webhook_token
                                ? 'POST '.route('webhooks.leads', $this->tenant->lead_webhook_token)
                                : 'No address yet. Click "Create address".')->copyable(),
                        ]),
                ]),
            ])->persistTabInQueryString(),
        ]);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['settings'] = [...($this->tenant->settings ?? []), ...($data['settings'] ?? [])];
        $data['settings']['reminder_days_before'] = array_values(array_unique(array_map('intval', $data['settings']['reminder_days_before'] ?? [])));
        $data['website'] = [...($this->tenant->website ?? []), ...($data['website'] ?? [])];
        $data['integrations'] = array_filter([...($this->tenant->integrations ?? []), ...($data['integrations'] ?? [])], fn ($v) => filled($v));

        return $data;
    }
}
