<?php

namespace App\Filament\Gym\Resources\Leads;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Filament\Gym\Concerns\ChecksGymAccess;
use App\Filament\Gym\Resources\Leads\Pages\ListLeads;
use App\Filament\Gym\Resources\Members\MemberResource;
use App\Models\Lead;
use App\Models\Member;
use App\Services\Messaging\PhoneNumber;
use App\Support\Access;
use App\Support\GymOptions;
use App\Support\Tenancy;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/** Enquiries from walk-ins, calls, the gym website and Facebook / Instagram lead ads. */
class LeadResource extends Resource
{
    use ChecksGymAccess;

    protected static ?string $model = Lead::class;

    protected static ?string $modelLabel = 'enquiry';

    protected static ?string $pluralModelLabel = 'enquiries';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static string|UnitEnum|null $navigationGroup = 'Members';

    protected static ?int $navigationSort = 0;

    protected static ?string $recordTitleAttribute = 'name';

    protected static function accessArea(): string
    {
        return 'leads';
    }

    public static function getNavigationBadge(): ?string
    {
        return Lead::query()->where('status', LeadStatus::New)->count() ?: null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'New enquiries';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            TextInput::make('name')->required()->maxLength(120),
            TextInput::make('phone')->tel()->maxLength(20)->requiredWithout('email'),
            TextInput::make('email')->email()->maxLength(120),
            Select::make('source')->options(LeadSource::class)->default(LeadSource::WalkIn->value)->required(),
            Select::make('status')->options(LeadStatus::class)->default(LeadStatus::New->value)->required(),
            TextInput::make('interest')->placeholder('e.g. Yearly plan, weight loss, PT')->maxLength(120),
            DatePicker::make('trial_date')->label('Free trial on'),
            TextInput::make('trial_time')->label('Trial time')->placeholder('e.g. 7:00 AM')->maxLength(30),
            DateTimePicker::make('follow_up_at')->label('Follow up on')->seconds(false),
            Select::make('assigned_to')->label('Assigned to')->options(fn () => GymOptions::staff())->default(fn () => auth()->id()),
            Select::make('branch_id')->label('Branch')->options(fn () => GymOptions::branches())
                ->default(fn () => Tenancy::branchId() ?? GymOptions::branches()->keys()->first()),
            Textarea::make('notes')->rows(3)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->searchable()->weight('bold')
                    ->description(fn (Lead $record) => collect([$record->phone, $record->email])->filter()->implode(' · ')),
                TextColumn::make('phone')->searchable()->hidden(),
                TextColumn::make('source')->badge()->color('gray'),
                TextColumn::make('status')->badge(),
                TextColumn::make('interest')->placeholder('—')->toggleable(),
                TextColumn::make('trial_date')->label('Trial')->date('D, d M')->placeholder('—')
                    ->description(fn (Lead $record) => $record->trial_time)->sortable(),
                TextColumn::make('follow_up_at')->label('Follow up')->dateTime('d M, h:i A')->placeholder('—')->sortable()
                    ->color(fn (Lead $record) => $record->follow_up_at?->isPast() && in_array($record->status, LeadStatus::open(), true) ? 'danger' : null),
                TextColumn::make('assignedTo.name')->label('Assigned to')->placeholder('—')->toggleable(),
                TextColumn::make('created_at')->label('Received')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('source')->options(LeadSource::class)->multiple(),
                SelectFilter::make('status')->options(LeadStatus::class)->multiple(),
                SelectFilter::make('assigned_to')->label('Assigned to')->options(fn () => GymOptions::staff()),
            ])
            ->recordActions([
                static::convertAction(),
                ActionGroup::make([
                    Action::make('whatsapp')
                        ->label('WhatsApp')
                        ->icon(Heroicon::OutlinedChatBubbleOvalLeft)
                        ->visible(fn (Lead $record) => filled($record->phone))
                        ->url(fn (Lead $record) => 'https://wa.me/'.PhoneNumber::international($record->phone), shouldOpenInNewTab: true),
                    Action::make('contacted')
                        ->label('Mark contacted')
                        ->icon(Heroicon::OutlinedPhone)
                        ->visible(fn (Lead $record) => $record->status === LeadStatus::New)
                        ->action(fn (Lead $record) => $record->update(['status' => LeadStatus::Contacted])),
                    Action::make('lost')
                        ->label('Mark lost')
                        ->icon(Heroicon::OutlinedXCircle)
                        ->color('danger')
                        ->visible(fn (Lead $record) => in_array($record->status, LeadStatus::open(), true))
                        ->schema([Textarea::make('reason')->label('Why? (optional)')->rows(2)])
                        ->action(fn (Lead $record, array $data) => $record->update([
                            'status' => LeadStatus::Lost,
                            'notes' => trim($record->notes."\nLost: ".($data['reason'] ?? '')),
                        ])),
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ]);
    }

    /** Creates the member from the enquiry (or links an existing one with the same phone) and opens it. */
    public static function convertAction(): Action
    {
        return Action::make('convert')
            ->label('Convert to member')
            ->icon(Heroicon::OutlinedUserPlus)
            ->color('success')
            ->visible(fn (Lead $record) => $record->status !== LeadStatus::Converted && Access::can('members.create'))
            ->disabled(fn (Lead $record) => blank($record->phone))
            ->tooltip(fn (Lead $record) => blank($record->phone) ? 'Add a phone number first' : null)
            ->requiresConfirmation()
            ->modalDescription('Creates a member profile from this enquiry. You can then add their membership and payment.')
            ->action(function (Lead $record) {
                $member = filled($record->phone) ? Member::query()->where('phone', $record->phone)->first() : null;

                $member ??= Member::create([
                    'branch_id' => $record->branch_id ?? Tenancy::branchId() ?? GymOptions::branches()->keys()->first(),
                    'name' => $record->name,
                    'phone' => $record->phone,
                    'email' => $record->email,
                    'source' => match ($record->source) {
                        LeadSource::Referral => 'referral',
                        LeadSource::Website => 'online',
                        LeadSource::Facebook, LeadSource::Instagram => 'social',
                        default => 'walk_in',
                    },
                    'notes' => $record->notes,
                ]);

                $record->update(['status' => LeadStatus::Converted, 'member_id' => $member->id]);
                Notification::make()->title("{$member->name} is now a member")->body('Add their first membership from the member page.')->success()->send();

                return redirect(MemberResource::getUrl('view', ['record' => $member]));
            });
    }

    public static function getPages(): array
    {
        return ['index' => ListLeads::route('/')];
    }
}
