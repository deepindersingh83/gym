<?php

namespace App\Filament\Admin\Pages;

use App\Models\SaasPlan;
use App\Services\PlatformSettings;
use App\Support\AdminAccess;
use App\Support\AdminLog;
use BackedEnum;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/** Everything the platform owner used to set in .env: company, keys, prices, email. */
class Settings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $title = 'Platform settings';

    protected static ?int $navigationSort = 90;

    protected string $view = 'filament.admin.pages.settings';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return AdminAccess::can('settings');
    }

    public function mount(): void
    {
        $this->form->fill(PlatformSettings::formState());
    }

    /** Password field that never shows the stored value. */
    protected function secret(string $key, string $label): TextInput
    {
        return TextInput::make($key)
            ->label($label)
            ->password()
            ->revealable()
            ->placeholder(fn () => PlatformSettings::hasSecret($key) ? 'Saved, leave empty to keep' : 'Not set')
            ->autocomplete('new-password');
    }

    /** @return list<Component> */
    protected function entityFields(string $country, string $taxIdLabel, string $taxIdHelp): array
    {
        $base = "gym.billing.entities.{$country}";

        return [
            TextInput::make("{$base}.legal_name")->label('Legal / trading name')->maxLength(150),
            TextInput::make("{$base}.tax_id")->label($taxIdLabel)->helperText($taxIdHelp)->maxLength(20),
            Textarea::make("{$base}.address")->label('Address')->rows(2)->columnSpanFull(),
            TextInput::make("{$base}.tax_rate")->label('GST %')->numeric()->minValue(0)->maxValue(30),
            TextInput::make("{$base}.invoice_prefix")->label('Invoice number prefix')->maxLength(10),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Tabs::make()->persistTabInQueryString()->tabs([
                    Tab::make('General')->schema([
                        Grid::make(2)->schema([
                            TextInput::make('app.name')->label('Platform name')->required()->maxLength(60),
                            Select::make('gym.default_saas_plan')->label('Plan for new gyms')
                                ->options(fn () => SaasPlan::query()->orderBy('sort')->pluck('name', 'slug'))->required(),
                            TextInput::make('gym.trial_days')->label('Free trial (days)')->numeric()->minValue(0)->maxValue(365)->required(),
                            Toggle::make('gym.billing.trial_full_access')->label('Trial gyms can use every feature')->inline(false)
                                ->helperText('Off: trial gyms only get the features of their plan.'),
                            TextInput::make('gym.support.email')->label('Support email shown to gyms')->email(),
                            TextInput::make('gym.support.phone')->label('Support phone / WhatsApp')->tel(),
                        ]),
                    ]),

                    Tab::make('Your company')->schema([
                        Text::make('Shown on the tax invoices gyms receive from you. Gyms are billed by the entity of their country.'),
                        Section::make('India')->columns(2)->schema($this->entityFields('IN', 'GSTIN', '15 characters. Leave empty if not GST registered.')),
                        Section::make('Australia')->columns(2)->schema($this->entityFields('AU', 'ABN', '11 digits.')),
                        Section::make('Renewals')->columns(2)->schema([
                            TagsInput::make('gym.billing.reminder_days')->label('Remind gyms this many days before their plan ends')->placeholder('e.g. 7'),
                            TextInput::make('gym.billing.grace_days')->label('Grace period after the end date (days)')->numeric()->minValue(0)->maxValue(30)
                                ->helperText('Gyms keep full access this long before changes are locked.'),
                        ]),
                    ]),

                    Tab::make('Payments')->schema([
                        Section::make('Razorpay (gyms in India pay you here)')
                            ->description(fn () => 'Webhook URL: '.route('webhooks.platform').' (events: payment_link.paid, subscription.authenticated, subscription.charged, subscription.cancelled, subscription.halted)')
                            ->columns(3)
                            ->schema([
                                TextInput::make('gym.platform_razorpay.key_id')->label('Key ID'),
                                $this->secret('gym.platform_razorpay.key_secret', 'Key secret'),
                                $this->secret('gym.platform_razorpay.webhook_secret', 'Webhook secret'),
                            ]),
                        Section::make('Stripe (gyms in Australia pay you here)')
                            ->description(fn () => 'Webhook URL: '.route('webhooks.stripe').' (events: checkout.session.completed, invoice.paid, customer.subscription.deleted)')
                            ->columns(2)
                            ->schema([
                                $this->secret('gym.stripe.secret_key', 'Secret key (sk_live_…)'),
                                $this->secret('gym.stripe.webhook_secret', 'Webhook signing secret (whsec_…)'),
                            ]),
                    ]),

                    Tab::make('Messaging & credits')->schema([
                        Section::make('WhatsApp Cloud API (platform account)')->columns(2)->schema([
                            TextInput::make('gym.whatsapp.phone_number_id')->label('Phone number ID'),
                            $this->secret('gym.whatsapp.token', 'Access token'),
                        ]),
                        Section::make('SMS: MSG91 (platform account)')->columns(3)->schema([
                            $this->secret('gym.sms.msg91_auth_key', 'Auth key'),
                            TextInput::make('gym.sms.msg91_sender')->label('Sender ID'),
                            TextInput::make('gym.sms.msg91_flow_id')->label('Default flow ID'),
                        ]),
                        Section::make('Message credits')->columns(4)->schema([
                            TextInput::make('gym.credits.cost.whatsapp')->label('Credits per WhatsApp')->numeric()->minValue(0)->required(),
                            TextInput::make('gym.credits.cost.sms')->label('Credits per SMS')->numeric()->minValue(0)->required(),
                            TextInput::make('gym.credits.welcome')->label('Free credits for new gyms')->numeric()->minValue(0)->required(),
                            TextInput::make('gym.credits.low_balance')->label('Low-balance alert below')->numeric()->minValue(0)->required(),
                            Repeater::make('gym.credits.packs')
                                ->label('Credit packs gyms can buy')
                                ->schema([
                                    TextInput::make('credits')->numeric()->minValue(1)->required(),
                                    TextInput::make('INR')->label('Price in ₹')->numeric()->minValue(0)->helperText('Empty: not sold in India'),
                                    TextInput::make('AUD')->label('Price in A$')->numeric()->minValue(0)->helperText('Empty: not sold in Australia'),
                                ])
                                ->columns(3)
                                ->addActionLabel('Add pack')
                                ->columnSpanFull(),
                        ]),
                    ]),

                    Tab::make('Email')->schema([
                        Grid::make(3)->schema([
                            Select::make('mail.default')->label('Send email with')
                                // Keep any other mailer the server already uses (ses, sendmail, ...).
                                ->options(fn () => ['smtp' => 'SMTP server', 'log' => 'Do not send (write to log)'] + [config('mail.default') => ucfirst((string) config('mail.default'))])
                                ->required(),
                            TextInput::make('mail.mailers.smtp.host')->label('SMTP host'),
                            TextInput::make('mail.mailers.smtp.port')->label('Port')->numeric(),
                            TextInput::make('mail.mailers.smtp.username')->label('Username'),
                            $this->secret('mail.mailers.smtp.password', 'Password'),
                            Select::make('mail.mailers.smtp.scheme')->label('Security')->options(['smtp' => 'STARTTLS (587)', 'smtps' => 'SSL (465)'])->placeholder('Automatic'),
                            TextInput::make('mail.from.address')->label('From address')->email(),
                            TextInput::make('mail.from.name')->label('From name'),
                        ]),
                    ]),

                    Tab::make('Integrations')->schema([
                        Section::make('Gym custom domains')->columns(3)->schema([
                            TextInput::make('gym.custom_domains.cname_target')->label('CNAME target gyms point to'),
                            TextInput::make('gym.custom_domains.cloudflare_zone_id')->label('Cloudflare zone ID'),
                            $this->secret('gym.custom_domains.cloudflare_api_token', 'Cloudflare API token'),
                        ]),
                        Section::make('Facebook & Instagram lead ads (Meta app)')
                            ->description(fn () => 'Callback URL: '.route('webhooks.meta'))
                            ->columns(2)
                            ->schema([
                                $this->secret('gym.meta.app_secret', 'App secret'),
                                $this->secret('gym.meta.verify_token', 'Verify token'),
                            ]),
                    ]),
                ]),
            ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $state['gym']['billing']['reminder_days'] = array_values(array_map('intval', $state['gym']['billing']['reminder_days'] ?? []));
        $state['gym']['credits']['packs'] = array_values(array_map(fn ($pack) => [
            'credits' => (int) $pack['credits'],
            'INR' => filled($pack['INR'] ?? null) ? (float) $pack['INR'] : null,
            'AUD' => filled($pack['AUD'] ?? null) ? (float) $pack['AUD'] : null,
        ], $state['gym']['credits']['packs'] ?? []));

        PlatformSettings::save($state);
        AdminLog::record('Changed platform settings');

        $this->form->fill(PlatformSettings::formState());
        Notification::make()->title('Settings saved')->success()->send();
    }
}
