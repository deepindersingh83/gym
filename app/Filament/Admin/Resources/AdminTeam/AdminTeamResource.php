<?php

namespace App\Filament\Admin\Resources\AdminTeam;

use App\Enums\AdminRole;
use App\Filament\Admin\Resources\AdminTeam\Pages\ManageAdminTeam;
use App\Models\User;
use App\Support\AdminAccess;
use App\Support\AdminLog;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** The people who run the platform and what each may do. */
class AdminTeamResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'team';

    protected static ?string $navigationLabel = 'Admin team';

    protected static ?string $modelLabel = 'team member';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Platform';

    protected static ?int $navigationSort = 91;

    public static function canViewAny(): bool
    {
        return AdminAccess::can('team');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where(fn (Builder $q) => $q->whereNotNull('admin_role')->orWhere('is_super_admin', true));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(100),
            TextInput::make('email')->email()->required()->maxLength(150)
                ->disabledOn('edit')
                ->unique(User::class, 'email', ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->whereNotNull('admin_role'))
                ->validationMessages(['unique' => 'This person is already on the team.']),
            Radio::make('admin_role')->label('Role')->options(AdminRole::class)->descriptions(collect(AdminRole::cases())->mapWithKeys(fn (AdminRole $r) => [$r->value => $r->getDescription()])->all())
                ->default(AdminRole::Support->value)->required()
                ->disableOptionWhen(fn (string $value, ?User $record) => $record && static::isLastOwner($record) && $value !== AdminRole::Owner->value),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->description(fn (User $record) => $record->email),
                TextColumn::make('role')->state(fn (User $record) => $record->adminRole()?->getLabel())->badge()
                    ->color(fn (User $record) => $record->adminRole() === AdminRole::Owner ? 'primary' : 'gray'),
                IconColumn::make('two_factor')->label('Two-step login')->state(fn (User $record) => filled($record->app_authentication_secret))->boolean(),
                TextColumn::make('last_login_at')->label('Last sign-in')->since()->placeholder('Never'),
            ])
            ->recordActions([
                EditAction::make()
                    ->after(fn (User $record) => AdminLog::record('Changed admin role', $record, ['email' => $record->email, 'role' => $record->adminRole()?->value])),
                Action::make('resetTwoFactor')
                    ->label('Reset two-step login')
                    ->icon(Heroicon::OutlinedDevicePhoneMobile)
                    ->color('gray')
                    ->visible(fn (User $record) => filled($record->app_authentication_secret) && $record->isNot(auth()->user()))
                    ->requiresConfirmation()
                    ->modalDescription('Use this when someone lost their phone and recovery codes. They will set up the authenticator app again at next sign-in.')
                    ->action(function (User $record) {
                        $record->forceFill(['app_authentication_secret' => null, 'app_authentication_recovery_codes' => null])->save();
                        AdminLog::record('Reset two-step login', $record, ['email' => $record->email]);
                        Notification::make()->title('Two-step login reset')->success()->send();
                    }),
                Action::make('remove')
                    ->label('Remove')
                    ->icon(Heroicon::OutlinedUserMinus)
                    ->color('danger')
                    ->hidden(fn (User $record) => $record->is(auth()->user()) || static::isLastOwner($record))
                    ->requiresConfirmation()
                    ->modalDescription('They lose access to the admin panel. Their account and any gym access stay as they are.')
                    ->action(function (User $record) {
                        $record->forceFill(['admin_role' => null, 'is_super_admin' => false])->save();
                        AdminLog::record('Removed admin team member', $record, ['email' => $record->email]);
                        Notification::make()->title("{$record->email} removed from the team")->success()->send();
                    }),
            ]);
    }

    public static function isLastOwner(User $user): bool
    {
        return $user->adminRole() === AdminRole::Owner
            && static::getEloquentQuery()->get()->filter(fn (User $u) => $u->adminRole() === AdminRole::Owner)->count() <= 1;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => ManageAdminTeam::route('/')];
    }
}
