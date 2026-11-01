<?php

namespace App\Filament\Gym\Resources\Staff;

use App\Enums\StaffRole;
use App\Filament\Forms\Components\CameraPhoto;
use App\Filament\Gym\Concerns\ChecksGymAccess;
use App\Filament\Gym\Resources\Staff\Pages\ManageStaff;
use App\Models\GymUser;
use App\Models\User;
use App\Support\GymOptions;
use App\Support\Tenancy;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** Staff accounts of the gym and the role each one has. */
class StaffResource extends Resource
{
    use ChecksGymAccess;

    protected static ?string $model = GymUser::class;

    protected static ?string $modelLabel = 'staff member';

    protected static ?string $pluralModelLabel = 'staff';

    protected static ?string $tenantRelationshipName = 'staffMemberships';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static string|UnitEnum|null $navigationGroup = 'Staff';

    protected static ?int $navigationSort = 1;

    protected static function accessArea(): string
    {
        return 'staff';
    }

    protected static function saasLimit(): ?string
    {
        return 'staff';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Section::make('Login')->columns(2)->columnSpanFull()->visibleOn('create')->schema([
                TextInput::make('name')->required(),
                TextInput::make('email')->email()->required()
                    ->helperText('If this person already has an account, they are added to your gym with it.'),
                TextInput::make('phone')->tel(),
                TextInput::make('password')->password()->revealable()->minLength(8)
                    ->helperText('Needed only for a new account.'),
            ]),
            Select::make('role')->options(StaffRole::class)->required()->default(StaffRole::Receptionist->value)->live(),
            Select::make('branch_id')->label('Branch')->options(fn () => GymOptions::branches())
                ->placeholder('All branches')
                ->helperText('Managers, receptionists and trainers only see their branch.'),
            TextInput::make('designation')->placeholder('e.g. Senior trainer'),
            DatePicker::make('joined_on')->default(today()),
            TimePicker::make('shift_start')->seconds(false),
            TimePicker::make('shift_end')->seconds(false),
            Toggle::make('is_active')->label('Can sign in')->default(true)->inline(false),
            Section::make('Website profile')
                ->description('Shown on the gym website when "Show on website" is on.')
                ->columns(2)
                ->columnSpanFull()
                ->collapsed(fn (?GymUser $record) => ! $record?->show_on_website)
                ->schema([
                    Toggle::make('show_on_website')->label('Show on website')->columnSpanFull(),
                    CameraPhoto::make('photo_path')->label('Photo')->directory(fn () => 'staff/'.Tenancy::gymId())->columnSpanFull(),
                    TextInput::make('specialities')->placeholder('e.g. Strength, fat loss, mobility')->maxLength(150),
                    Textarea::make('bio')->rows(3)->maxLength(800)->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user'))
            ->columns([
                TextColumn::make('user.name')->label('Name')->searchable()->description(fn (GymUser $record) => $record->designation),
                TextColumn::make('user.email')->label('Email')->searchable(),
                TextColumn::make('user.phone')->label('Phone')->placeholder('—'),
                TextColumn::make('role')->badge(),
                TextColumn::make('branch_id')->label('Branch')
                    ->formatStateUsing(fn ($state) => GymOptions::branches()[$state] ?? '—')->placeholder('All branches'),
                TextColumn::make('shift_start')->label('Shift')
                    ->formatStateUsing(fn (GymUser $record) => $record->shift_start ? substr($record->shift_start, 0, 5).' – '.substr((string) $record->shift_end, 0, 5) : null)
                    ->placeholder('—'),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->filters([SelectFilter::make('role')->options(StaffRole::class)])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->label('Remove')
                    ->hidden(fn (GymUser $record) => static::isLastOwner($record) || $record->user_id === auth()->id()),
            ]);
    }

    public static function canEdit(Model $record): bool
    {
        return parent::canEdit($record) && ! static::isLastOwner($record);
    }

    public static function isLastOwner(GymUser $record): bool
    {
        return $record->role === StaffRole::Owner
            && GymUser::query()->where('role', StaffRole::Owner)->where('is_active', true)->count() <= 1;
    }

    /** Creates (or reuses) the login and adds it to the current gym. */
    public static function createStaff(array $data): GymUser
    {
        $user = User::query()->firstOrCreate(
            ['email' => strtolower($data['email'])],
            ['name' => $data['name'], 'phone' => $data['phone'] ?? null, 'password' => $data['password'] ?: str()->password(32)],
        );

        return GymUser::query()->updateOrCreate(
            ['gym_id' => Tenancy::gymId(), 'user_id' => $user->id],
            collect($data)->only(['role', 'branch_id', 'designation', 'joined_on', 'shift_start', 'shift_end', 'is_active', 'show_on_website', 'photo_path', 'specialities', 'bio'])->all(),
        );
    }

    public static function getPages(): array
    {
        return ['index' => ManageStaff::route('/')];
    }
}
