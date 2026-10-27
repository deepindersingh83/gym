<?php

namespace App\Filament\Gym\Resources\Activities;

use App\Filament\Gym\Concerns\ChecksGymAccess;
use App\Filament\Gym\Resources\Activities\Pages\ListActivities;
use App\Models\Activity;
use App\Models\CashClosing;
use App\Models\Coupon;
use App\Models\Expense;
use App\Models\GymUser;
use App\Models\Member;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\Subscription;
use App\Support\GymOptions;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/** Who created, changed or deleted what, and when. */
class ActivityResource extends Resource
{
    use ChecksGymAccess;

    protected static ?string $model = Activity::class;

    protected static ?string $navigationLabel = 'Audit log';

    protected static ?string $modelLabel = 'audit entry';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Setup';

    protected static ?int $navigationSort = 9;

    protected static function accessArea(): string
    {
        return 'audit';
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            TextEntry::make('created_at')->label('When')->dateTime('d M Y h:i:s A'),
            TextEntry::make('causer.name')->label('Who')->placeholder('System'),
            TextEntry::make('description')->label('What')->formatStateUsing(fn (Activity $record) => static::describe($record)),
            KeyValueEntry::make('properties.old')->label('Before')->columnSpanFull()->visible(fn (Activity $record) => filled($record->properties['old'] ?? null)),
            KeyValueEntry::make('properties.attributes')->label('After')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime('d M Y h:i A'),
                TextColumn::make('causer.name')->label('Who')->placeholder('System'),
                TextColumn::make('event')->badge()
                    ->color(fn ($state) => match ($state) {
                        'created' => 'success', 'deleted' => 'danger', default => 'warning'
                    }),
                TextColumn::make('subject_type')->label('Record')->formatStateUsing(fn (Activity $record) => static::describe($record)),
            ])
            ->filters([
                SelectFilter::make('event')->options(['created' => 'Created', 'updated' => 'Updated', 'deleted' => 'Deleted']),
                SelectFilter::make('subject_type')->label('Record type')->options([
                    Payment::class => 'Payments',
                    Subscription::class => 'Memberships',
                    Member::class => 'Members',
                    Expense::class => 'Expenses',
                    CashClosing::class => 'Cash closing',
                    MembershipPlan::class => 'Plans',
                    Coupon::class => 'Coupons',
                    GymUser::class => 'Staff',
                ]),
                SelectFilter::make('causer_id')->label('Who')->options(fn () => GymOptions::staff()),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function describe(Activity $record): string
    {
        return str(class_basename($record->subject_type))->headline().' #'.$record->subject_id;
    }

    public static function getPages(): array
    {
        return ['index' => ListActivities::route('/')];
    }
}
