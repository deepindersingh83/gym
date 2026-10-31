<?php

namespace App\Filament\Admin\Resources\Gyms;

use App\Enums\StaffRole;
use App\Filament\Admin\Resources\Gyms\Pages\CreateGym;
use App\Filament\Admin\Resources\Gyms\Pages\EditGym;
use App\Filament\Admin\Resources\Gyms\Pages\ListGyms;
use App\Models\Gym;
use App\Models\SaasPlan;
use App\Services\Credits;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Every gym on the platform, with its SaaS plan, trial and status. */
class GymResource extends Resource
{
    protected static ?string $model = Gym::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    public const STATUSES = ['trial' => 'Trial', 'active' => 'Active (paying)', 'suspended' => 'Suspended', 'cancelled' => 'Cancelled'];

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Gym')->columns(3)->schema([
                TextInput::make('name')->required()->maxLength(120),
                TextInput::make('slug')
                    ->alphaDash()
                    ->maxLength(60)
                    ->unique(ignoreRecord: true)
                    ->notIn(config('gym.reserved_slugs'))
                    ->required(fn (string $operation) => $operation === 'edit')
                    ->helperText(fn (string $operation) => $operation === 'create'
                        ? 'Leave empty to create it from the name. Used in the gym\'s web addresses.'
                        : 'Used in the gym\'s web addresses. Changing it changes the gym\'s login links.'),
                TextInput::make('phone')->tel()->required()->maxLength(20),
                TextInput::make('email')->email()->maxLength(120),
                TextInput::make('whatsapp')->label('WhatsApp number')->tel()->maxLength(20),
                TextInput::make('gstin')->label('GSTIN')->length(15)
                    ->dehydrateStateUsing(fn (?string $state) => $state ? strtoupper($state) : null),
                Textarea::make('address')->rows(2)->columnSpan(3),
                TextInput::make('city'),
                TextInput::make('state'),
                TextInput::make('pincode')->maxLength(10),
            ]),
            Section::make('Owner login')
                ->description('The gym owner signs in at /app with this email. An existing account with the same email is reused and keeps its password.')
                ->columns(2)
                ->visibleOn('create')
                ->statePath('owner')
                ->schema([
                    TextInput::make('name')->label('Owner name')->required(),
                    TextInput::make('email')->label('Owner email')->email()->required(),
                    TextInput::make('phone')->label('Owner phone')->tel(),
                    TextInput::make('password')
                        ->password()
                        ->revealable()
                        ->minLength(8)
                        ->helperText('Leave empty to email the owner a link to set their own password.'),
                ]),
            Section::make('Subscription to the software')->columns(4)->schema([
                Select::make('saas_plan_id')->label('Plan')->relationship('saasPlan', 'name')->required()
                    ->default(fn () => SaasPlan::query()->where('slug', config('gym.default_saas_plan'))->value('id')),
                Select::make('status')->options(self::STATUSES)->required()->default('trial'),
                DateTimePicker::make('trial_ends_at')->default(fn () => now()->addDays((int) config('gym.trial_days'))),
                DateTimePicker::make('subscription_ends_at')->label('Paid until')->helperText('Empty = no end date.'),
                Select::make('partner_id')->label('Referred by partner')->relationship('partner', 'name')->searchable()->preload()
                    ->helperText('Earns this partner a commission on the gym\'s software payments.')->columnSpan(2),
            ]),
            Section::make('Starter setup')
                ->visibleOn('create')
                ->schema([
                    CheckboxList::make('starter_plans')
                        ->label('Create these membership plans (prices can be changed later)')
                        ->options([
                            'monthly' => 'Monthly',
                            'quarterly' => '3 months',
                            'half_yearly' => '6 months',
                            'yearly' => 'Yearly',
                        ])
                        ->default(['monthly', 'quarterly', 'yearly'])
                        ->columns(4),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('users')->withCount([
                'members' => fn ($q) => $q->withoutGlobalScopes()->whereNull('deleted_at'),
                'branches' => fn ($q) => $q->withoutGlobalScopes(),
                'staffMemberships' => fn ($q) => $q->withoutGlobalScopes(),
            ]))
            ->defaultSort('created_at', 'desc')
            ->columns([
                ImageColumn::make('logo_path')->label('')->disk('public')->circular(),
                TextColumn::make('name')->searchable()->description(fn (Gym $record) => $record->slug),
                TextColumn::make('owner')
                    ->label('Owner')
                    ->state(fn (Gym $record) => $record->users->firstWhere('pivot.role', StaffRole::Owner)?->email)
                    ->placeholder('—'),
                TextColumn::make('city')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('custom_domain')->label('Own domain')->placeholder('—')->toggleable(isToggledHiddenByDefault: true)
                    ->color(fn (Gym $record) => $record->custom_domain_status === 'active' ? 'success' : 'warning')
                    ->description(fn (Gym $record) => $record->custom_domain ? ucfirst((string) $record->custom_domain_status) : null),
                TextColumn::make('saasPlan.name')->label('Plan')->badge(),
                TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => self::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'active' => 'success', 'trial' => 'warning', default => 'danger'
                    }),
                TextColumn::make('trial_ends_at')->label('Trial ends')->date('d M Y')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('subscription_ends_at')->label('Paid until')->date('d M Y')->placeholder('—'),
                TextColumn::make('members_count')->label('Members'),
                TextColumn::make('message_credits')->label('Credits')->numeric()->sortable()
                    ->color(fn ($state) => $state < (int) config('gym.credits.low_balance') ? 'danger' : null),
                TextColumn::make('branches_count')->label('Branches')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('staff_memberships_count')->label('Staff')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->label('Signed up')->date('d M Y')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(self::STATUSES),
                SelectFilter::make('saas_plan_id')->label('Plan')->options(fn () => SaasPlan::query()->pluck('name', 'id')),
                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('openPanel')
                    ->label('Open')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (Gym $record) => Filament::getPanel('gym')->getUrl($record), shouldOpenInNewTab: true)
                    ->hidden(fn (Gym $record) => $record->trashed()),
                ActionGroup::make([
                    Action::make('extendTrial')
                        ->label('Extend trial')
                        ->icon(Heroicon::OutlinedClock)
                        ->schema([TextInput::make('days')->numeric()->minValue(1)->default(14)->required()])
                        ->action(function (Gym $record, array $data) {
                            $from = $record->trial_ends_at?->isFuture() ? $record->trial_ends_at : now();
                            $record->update(['status' => 'trial', 'trial_ends_at' => $from->copy()->addDays((int) $data['days'])]);
                            Notification::make()->title('Trial extended to '.$record->trial_ends_at->format('d M Y'))->success()->send();
                        }),
                    Action::make('activate')
                        ->label('Record payment')
                        ->icon(Heroicon::OutlinedBanknotes)
                        ->schema([
                            Select::make('saas_plan_id')->label('Plan')->options(fn () => SaasPlan::query()->pluck('name', 'id'))
                                ->default(fn (Gym $record) => $record->saas_plan_id)->required()->live()
                                ->afterStateUpdated(fn ($state, $get, $set) => $set('amount', static::suggestedAmount($state, $get('months')))),
                            Select::make('months')->options([1 => '1 month', 3 => '3 months', 6 => '6 months', 12 => '12 months'])
                                ->default(12)->required()->live()
                                ->afterStateUpdated(fn ($state, $get, $set) => $set('amount', static::suggestedAmount($get('saas_plan_id'), $state))),
                            TextInput::make('amount')->label('Amount received')->prefix('₹')->numeric()->minValue(0)->required()
                                ->default(fn (Gym $record) => static::suggestedAmount($record->saas_plan_id, 12)),
                            TextInput::make('reference')->label('Payment reference')->maxLength(100),
                        ])
                        ->action(function (Gym $record, array $data) {
                            $from = $record->status === 'active' && $record->subscription_ends_at?->isFuture() ? $record->subscription_ends_at : now();
                            $record->update([
                                'saas_plan_id' => $data['saas_plan_id'],
                                'status' => 'active',
                                'subscription_ends_at' => $from->copy()->addMonthsNoOverflow((int) $data['months']),
                            ]);
                            $payment = $record->saasPayments()->create([
                                'saas_plan_id' => $data['saas_plan_id'],
                                'recorded_by' => auth()->id(),
                                'kind' => 'subscription',
                                'amount' => $data['amount'],
                                'months' => $data['months'],
                                'reference' => $data['reference'] ?? null,
                                'paid_at' => now(),
                            ]);
                            Notification::make()
                                ->title('Active until '.$record->subscription_ends_at->format('d M Y'))
                                ->body($payment->commission ? 'Partner commission: ₹'.number_format((float) $payment->commission->amount, 2) : null)
                                ->success()->send();
                        }),
                    Action::make('credits')
                        ->label('Adjust message credits')
                        ->icon(Heroicon::OutlinedCircleStack)
                        ->schema([
                            TextInput::make('credits')->label('Credits to add (use a minus sign to remove)')->integer()->required()->notIn([0]),
                            TextInput::make('reason')->label('Reason')->default('Added by support')->required()->maxLength(200),
                        ])
                        ->action(function (Gym $record, array $data) {
                            app(Credits::class)->add($record, (int) $data['credits'], 'adjustment', $data['reason']);
                            Notification::make()->title('Balance is now '.number_format($record->message_credits).' credits')->success()->send();
                        }),
                    Action::make('ownerPassword')
                        ->label('Set owner password')
                        ->icon(Heroicon::OutlinedKey)
                        ->color('gray')
                        ->schema(fn (Gym $record) => [
                            Select::make('user_id')
                                ->label('Owner')
                                ->options($record->users()->wherePivot('role', StaffRole::Owner->value)->pluck('email', 'users.id'))
                                ->default($record->users()->wherePivot('role', StaffRole::Owner->value)->value('users.id'))
                                ->required(),
                            TextInput::make('password')->label('New password')->password()->revealable()->minLength(8)->required(),
                        ])
                        ->action(function (Gym $record, array $data) {
                            $owner = $record->users()->wherePivot('role', StaffRole::Owner->value)->findOrFail($data['user_id']);
                            $owner->update(['password' => $data['password']]);
                            Notification::make()->title("Password updated for {$owner->email}")->success()->send();
                        }),
                    EditAction::make(),
                ]),
            ]);
    }

    /** Plan price for the period: the yearly price for 12 months, otherwise monthly × months. */
    public static function suggestedAmount(mixed $planId, mixed $months): ?float
    {
        $plan = $planId ? SaasPlan::query()->find($planId) : null;

        if (! $plan) {
            return null;
        }

        return (int) $months === 12 && (float) $plan->yearly_price > 0
            ? (float) $plan->yearly_price
            : round((float) $plan->monthly_price * max(1, (int) $months), 2);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGyms::route('/'),
            'create' => CreateGym::route('/create'),
            'edit' => EditGym::route('/{record}/edit'),
        ];
    }
}
