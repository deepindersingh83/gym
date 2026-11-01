<?php

namespace App\Filament\Gym\Resources\Members;

use App\Filament\Forms\Components\CameraPhoto;
use App\Filament\Gym\Actions\MemberActions;
use App\Filament\Gym\Concerns\ChecksGymAccess;
use App\Filament\Gym\Resources\Members\Pages\CreateMember;
use App\Filament\Gym\Resources\Members\Pages\EditMember;
use App\Filament\Gym\Resources\Members\Pages\ListMembers;
use App\Filament\Gym\Resources\Members\Pages\ViewMember;
use App\Filament\Gym\Resources\Members\RelationManagers\AttendancesRelationManager;
use App\Filament\Gym\Resources\Members\RelationManagers\MeasurementsRelationManager;
use App\Filament\Gym\Resources\Members\RelationManagers\PaymentsRelationManager;
use App\Filament\Gym\Resources\Members\RelationManagers\SubscriptionsRelationManager;
use App\Filament\Gym\Support\HeightField;
use App\Models\Member;
use App\Support\GymOptions;
use App\Support\Height;
use App\Support\Money;
use App\Support\Tenancy;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class MemberResource extends Resource
{
    use ChecksGymAccess;

    protected static ?string $model = Member::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Members';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    protected static function accessArea(): string
    {
        return 'members';
    }

    protected static function saasLimit(): ?string
    {
        return 'members';
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'phone', 'member_code', 'email'];
    }

    /** PAR-Q style health questions. */
    public const HEALTH_QUESTIONS = [
        'heart_condition' => 'Heart condition or chest pain',
        'blood_pressure' => 'High blood pressure',
        'diabetes' => 'Diabetes',
        'asthma' => 'Asthma or breathing problems',
        'joint_problems' => 'Bone, joint or back problems',
        'recent_surgery' => 'Surgery in the last 12 months',
        'pregnant' => 'Pregnant or recently gave birth',
        'medication' => 'Currently taking prescribed medication',
        'dizziness' => 'Dizziness or fainting',
    ];

    public const GOALS = [
        'weight_loss' => 'Weight loss',
        'muscle_gain' => 'Muscle gain',
        'general_fitness' => 'General fitness',
        'strength' => 'Strength',
        'endurance' => 'Endurance',
        'flexibility' => 'Flexibility',
        'rehab' => 'Rehabilitation',
    ];

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Personal details')->columns(3)->schema([
                CameraPhoto::make('photo_path')
                    ->label('Photo')
                    ->directory(fn () => 'members/'.Tenancy::gymId())
                    ->columnSpanFull(),
                TextInput::make('name')->required()->maxLength(120),
                TextInput::make('phone')->tel()->required()->maxLength(20)->scopedUnique(ignoreRecord: true),
                TextInput::make('email')->email()->maxLength(120),
                Select::make('gender')->options(['male' => 'Male', 'female' => 'Female', 'other' => 'Other']),
                DatePicker::make('date_of_birth')->maxDate(today()),
                DatePicker::make('anniversary_date'),
                TextInput::make('occupation'),
                Select::make('blood_group')->options(array_combine($g = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'], $g)),
                Textarea::make('address')->rows(2)->columnSpanFull(),
            ]),
            Section::make('Body and goals')->columns(4)->schema([
                HeightField::make(),
                TextInput::make('weight_kg')->label('Weight (kg)')->numeric()->minValue(10)->maxValue(400)
                    ->helperText('Updated automatically from measurements.'),
                Select::make('goal')->options(self::GOALS),
                TextInput::make('target_weight_kg')->label('Target weight (kg)')->numeric(),
            ]),
            Section::make('Health and emergency contact')->columns(2)->collapsible()->schema([
                TextInput::make('emergency_contact_name'),
                TextInput::make('emergency_contact_phone')->tel(),
                CheckboxList::make('health_questionnaire')
                    ->label('Health questionnaire (PAR-Q): tick any that apply')
                    ->options(self::HEALTH_QUESTIONS)
                    ->columns(2)
                    ->columnSpanFull(),
                Textarea::make('medical_conditions')->label('Medical notes / injuries')->rows(2)->columnSpanFull(),
            ]),
            Section::make('Gym')->columns(3)->schema([
                Select::make('branch_id')
                    ->label('Branch')
                    ->options(fn () => GymOptions::branches())
                    ->default(fn () => Tenancy::branchId() ?? GymOptions::branches()->keys()->first())
                    ->required()
                    ->disabled(fn () => ! Tenancy::canSwitchBranch())
                    ->dehydrated(),
                Select::make('trainer_id')->label('Trainer')->options(fn () => GymOptions::trainers()),
                DatePicker::make('joined_on')->default(today()),
                Select::make('source')->options(['walk_in' => 'Walk-in', 'online' => 'Online signup', 'referral' => 'Referral', 'social' => 'Social media', 'other' => 'Other'])->default('walk_in'),
                Toggle::make('is_active')->label('Active')->default(true)->inline(false),
                Textarea::make('notes')->rows(2)->columnSpanFull(),
                Checkbox::make('consent')
                    ->label('Member agreed to the gym storing their details, photo and health information')
                    ->helperText(fn (?Member $record) => $record?->consented_at ? 'Agreed on '.$record->consented_at->format('d M Y').' ('.str_replace('_', ' ', (string) $record->consent_source).').' : 'Ask the member before saving their details.')
                    ->default(false)
                    ->disabled(fn (?Member $record) => (bool) $record?->consented_at)
                    ->formatStateUsing(fn (?Member $record) => (bool) $record?->consented_at)
                    ->dehydrated(fn (?Member $record) => ! $record?->consented_at)
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(4)->schema([
                ImageEntry::make('photo_path')->hiddenLabel()->disk('public')->circular()->imageHeight(96)->defaultImageUrl(fn (Member $record) => 'https://ui-avatars.com/api/?name='.urlencode($record->name)),
                Grid::make(3)->columnSpan(3)->schema([
                    TextEntry::make('name')->weight('bold'),
                    TextEntry::make('member_code')->label('Member ID')->copyable(),
                    TextEntry::make('phone')->url(fn (Member $record) => 'tel:'.$record->phone),
                    TextEntry::make('currentSubscription.plan.name')->label('Plan')->placeholder('No active membership'),
                    TextEntry::make('latestSubscription.end_date')->label('Renewal date')->date('d M Y')
                        ->color(fn (Member $record) => match (true) {
                            ! $record->latestSubscription => 'gray',
                            $record->latestSubscription->days_left < 0 => 'danger',
                            $record->latestSubscription->days_left <= 7 => 'warning',
                            default => 'success',
                        }),
                    TextEntry::make('balance_due')->label('Balance due')->formatStateUsing(fn ($state) => Money::format($state))
                        ->color(fn ($state) => $state > 0 ? 'danger' : 'success'),
                ]),
            ]),
            Section::make('Details')->columns(4)->collapsible()->schema([
                TextEntry::make('email')->placeholder('—'),
                TextEntry::make('gender')->placeholder('—')->formatStateUsing(fn ($state) => ucfirst((string) $state)),
                TextEntry::make('date_of_birth')->date('d M Y')->placeholder('—'),
                TextEntry::make('anniversary_date')->date('d M Y')->placeholder('—'),
                TextEntry::make('height_cm')->label('Height')->formatStateUsing(fn ($state) => Height::format($state))->placeholder('—'),
                TextEntry::make('weight_kg')->label('Weight')->suffix(' kg')->placeholder('—'),
                TextEntry::make('bmi')->label('BMI')->placeholder('—'),
                TextEntry::make('goal')->formatStateUsing(fn ($state) => self::GOALS[$state] ?? $state)->placeholder('—'),
                TextEntry::make('trainer.name')->label('Trainer')->placeholder('—'),
                TextEntry::make('branch.name')->label('Branch')->placeholder('—'),
                TextEntry::make('joined_on')->date('d M Y'),
                TextEntry::make('blood_group')->placeholder('—'),
                TextEntry::make('emergency_contact_name')->label('Emergency contact')
                    ->formatStateUsing(fn (Member $record) => trim($record->emergency_contact_name.' '.$record->emergency_contact_phone))->placeholder('—'),
                TextEntry::make('health_questionnaire')->label('Health flags')->badge()->color('danger')
                    ->formatStateUsing(fn ($state) => self::HEALTH_QUESTIONS[$state] ?? $state)->placeholder('None')->columnSpan(2),
                TextEntry::make('medical_conditions')->placeholder('—')->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['latestSubscription.plan'])->withBalanceDue())
            ->defaultSort('created_at', 'desc')
            ->columns([
                ImageColumn::make('photo_path')->label('')->disk('public')->circular()->imageSize(36)
                    ->defaultImageUrl(fn (Member $record) => 'https://ui-avatars.com/api/?size=64&name='.urlencode($record->name)),
                TextColumn::make('member_code')->label('ID')->searchable()->sortable(),
                TextColumn::make('name')->searchable()->sortable()->description(fn (Member $record) => $record->phone),
                TextColumn::make('phone')->searchable()->hidden(),
                TextColumn::make('latestSubscription.plan.name')->label('Plan')->placeholder('—'),
                TextColumn::make('latestSubscription.end_date')->label('Renewal date')->date('d M Y')
                    ->color(fn (Member $record) => match (true) {
                        ! $record->latestSubscription => 'gray',
                        $record->latestSubscription->days_left < 0 => 'danger',
                        $record->latestSubscription->days_left <= 7 => 'warning',
                        default => 'success',
                    })
                    ->description(fn (Member $record) => $record->latestSubscription
                        ? ($record->latestSubscription->days_left < 0 ? 'Expired' : $record->latestSubscription->days_left.' days left')
                        : null),
                TextColumn::make('due')->label('Due')
                    ->state(fn (Member $record) => max(0, (float) $record->total_billed - (float) $record->total_paid))
                    ->formatStateUsing(fn ($state) => $state > 0 ? Money::format($state) : '—')
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'gray'),
                TextColumn::make('trainer.name')->label('Trainer')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('branch.name')->label('Branch')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('joined_on')->date('d M Y')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('membership')
                    ->options([
                        'active' => 'Active',
                        'expiring' => 'Expiring in 7 days',
                        'expired' => 'Expired',
                        'none' => 'Never subscribed',
                        'dues' => 'Has pending dues',
                    ])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'active' => $query->whereHas('subscriptions', fn ($q) => $q->active()->whereDate('end_date', '>=', today())),
                        'expiring' => $query->whereHas('subscriptions', fn ($q) => $q->active()->endingBetween(today(), today()->addDays(7))),
                        'expired' => $query->whereHas('subscriptions')->whereDoesntHave('subscriptions', fn ($q) => $q->active()->whereDate('end_date', '>=', today())),
                        'none' => $query->whereDoesntHave('subscriptions'),
                        'dues' => $query->whereHas('subscriptions', fn ($q) => $q->withDues()),
                        default => $query,
                    }),
                SelectFilter::make('trainer_id')->label('Trainer')->options(fn () => GymOptions::trainers()),
                SelectFilter::make('branch_id')->label('Branch')->options(fn () => GymOptions::branches())->visible(fn () => Tenancy::canSwitchBranch()),
                TernaryFilter::make('is_active')->label('Active profile'),
            ])
            ->recordActions([
                MemberActions::renew(),
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    MemberActions::recordPayment(),
                    MemberActions::checkIn(),
                    MemberActions::whatsapp(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            SubscriptionsRelationManager::class,
            PaymentsRelationManager::class,
            MeasurementsRelationManager::class,
            AttendancesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMembers::route('/'),
            'create' => CreateMember::route('/create'),
            'view' => ViewMember::route('/{record}'),
            'edit' => EditMember::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $count = Member::query()->whereHas('subscriptions', fn ($q) => $q->active()->endingBetween(today(), today()->addDays(7)))->count();

        return $count ?: null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Renewals due in the next 7 days';
    }
}
