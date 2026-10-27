<?php

namespace App\Filament\Admin\Resources\Gyms;

use App\Filament\Admin\Resources\Gyms\Pages\EditGym;
use App\Filament\Admin\Resources\Gyms\Pages\ListGyms;
use App\Models\Gym;
use App\Models\SaasPlan;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
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
                TextInput::make('name')->required(),
                TextInput::make('slug')->required()->alphaDash()->unique(ignoreRecord: true)
                    ->helperText('Used in the gym\'s URLs and subdomain.'),
                TextInput::make('phone'),
                TextInput::make('email')->email(),
                TextInput::make('city'),
                TextInput::make('gstin')->label('GSTIN'),
            ]),
            Section::make('Subscription to the software')->columns(3)->schema([
                Select::make('saas_plan_id')->label('Plan')->relationship('saasPlan', 'name')->required(),
                Select::make('status')->options(self::STATUSES)->required(),
                DateTimePicker::make('trial_ends_at'),
                DateTimePicker::make('subscription_ends_at')->label('Paid until')->helperText('Empty = no end date.'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount([
                'members' => fn ($q) => $q->withoutGlobalScopes()->whereNull('deleted_at'),
                'branches' => fn ($q) => $q->withoutGlobalScopes(),
                'staffMemberships' => fn ($q) => $q->withoutGlobalScopes(),
            ]))
            ->defaultSort('created_at', 'desc')
            ->columns([
                ImageColumn::make('logo_path')->label('')->disk('public')->circular(),
                TextColumn::make('name')->searchable()->description(fn (Gym $record) => $record->slug),
                TextColumn::make('city')->searchable()->toggleable(),
                TextColumn::make('saasPlan.name')->label('Plan')->badge(),
                TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => self::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'active' => 'success', 'trial' => 'warning', default => 'danger'
                    }),
                TextColumn::make('trial_ends_at')->label('Trial ends')->date('d M Y')->toggleable(),
                TextColumn::make('subscription_ends_at')->label('Paid until')->date('d M Y')->placeholder('—'),
                TextColumn::make('members_count')->label('Members'),
                TextColumn::make('branches_count')->label('Branches'),
                TextColumn::make('staff_memberships_count')->label('Staff'),
                TextColumn::make('created_at')->label('Signed up')->date('d M Y')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(self::STATUSES),
                SelectFilter::make('saas_plan_id')->label('Plan')->options(fn () => SaasPlan::query()->pluck('name', 'id')),
                TrashedFilter::make(),
            ])
            ->recordActions([
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
                        Select::make('saas_plan_id')->label('Plan')->options(fn () => SaasPlan::query()->pluck('name', 'id'))->default(fn (Gym $record) => $record->saas_plan_id)->required(),
                        Select::make('months')->options([1 => '1 month', 3 => '3 months', 6 => '6 months', 12 => '12 months'])->default(12)->required(),
                    ])
                    ->action(function (Gym $record, array $data) {
                        $from = $record->status === 'active' && $record->subscription_ends_at?->isFuture() ? $record->subscription_ends_at : now();
                        $record->update([
                            'saas_plan_id' => $data['saas_plan_id'],
                            'status' => 'active',
                            'subscription_ends_at' => $from->copy()->addMonthsNoOverflow((int) $data['months']),
                        ]);
                        Notification::make()->title('Active until '.$record->subscription_ends_at->format('d M Y'))->success()->send();
                    }),
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGyms::route('/'),
            'edit' => EditGym::route('/{record}/edit'),
        ];
    }
}
