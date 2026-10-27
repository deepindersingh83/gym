<?php

namespace App\Filament\Gym\Resources\Subscriptions;

use App\Enums\SubscriptionStatus;
use App\Filament\Gym\Actions\MemberActions;
use App\Filament\Gym\Actions\SubscriptionActions;
use App\Filament\Gym\Concerns\ChecksGymAccess;
use App\Filament\Gym\Resources\Members\MemberResource;
use App\Filament\Gym\Resources\Subscriptions\Pages\CreateSubscription;
use App\Filament\Gym\Resources\Subscriptions\Pages\ListSubscriptions;
use App\Filament\Gym\Resources\Subscriptions\Pages\ViewSubscription;
use App\Models\Subscription;
use App\Support\GymOptions;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class SubscriptionResource extends Resource
{
    use ChecksGymAccess;

    protected static ?string $model = Subscription::class;

    protected static ?string $modelLabel = 'membership';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|UnitEnum|null $navigationGroup = 'Billing';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'invoice_number';

    protected static function accessArea(): string
    {
        return 'subscriptions';
    }

    public static function infolist(Schema $schema): Schema
    {
        $money = fn ($state) => Money::format($state);

        return $schema->components([
            Section::make('Membership')->columns(4)->schema([
                TextEntry::make('member.name')->url(fn (Subscription $record) => MemberResource::getUrl('view', ['record' => $record->member_id])),
                TextEntry::make('member.phone')->label('Phone'),
                TextEntry::make('plan.name'),
                TextEntry::make('status')->badge(),
                TextEntry::make('start_date')->date('d M Y'),
                TextEntry::make('end_date')->label('Renewal date')->date('d M Y'),
                TextEntry::make('days_left')->label('Days left')->color(fn ($state) => $state < 0 ? 'danger' : ($state <= 7 ? 'warning' : 'success')),
                TextEntry::make('invoice_number'),
                TextEntry::make('pt_sessions_total')->label('PT sessions')
                    ->formatStateUsing(fn (Subscription $record) => "{$record->pt_sessions_used} of {$record->pt_sessions_total} used")
                    ->visible(fn (Subscription $record) => (bool) $record->pt_sessions_total),
                TextEntry::make('additionalMembers.name')->label('Also covers')->badge()->visible(fn (Subscription $record) => $record->additionalMembers()->exists()),
                TextEntry::make('auto_renew')->label('UPI AutoPay')->formatStateUsing(fn ($state) => $state ? 'On' : 'Off')->badge()->color(fn ($state) => $state ? 'success' : 'gray'),
                TextEntry::make('createdBy.name')->label('Sold by')->placeholder('Online'),
            ]),
            Section::make('Billing')->columns(4)->schema([
                TextEntry::make('plan_price')->formatStateUsing($money),
                TextEntry::make('admission_fee')->formatStateUsing($money),
                TextEntry::make('discount_amount')->label('Discount')->formatStateUsing($money)
                    ->helperText(fn (Subscription $record) => $record->coupon?->code),
                TextEntry::make('taxable_amount')->formatStateUsing($money),
                TextEntry::make('cgst_amount')->label(fn (Subscription $record) => 'CGST '.((float) $record->gst_rate / 2).'%')->formatStateUsing($money),
                TextEntry::make('sgst_amount')->label(fn (Subscription $record) => 'SGST '.((float) $record->gst_rate / 2).'%')->formatStateUsing($money),
                TextEntry::make('total_amount')->label('Total')->formatStateUsing($money)->weight('bold'),
                TextEntry::make('balance_due')->label('Balance due')->formatStateUsing($money)->color(fn ($state) => $state > 0 ? 'danger' : 'success')->weight('bold'),
            ]),
            Section::make('Payments')->schema([
                RepeatableEntry::make('payments')->hiddenLabel()->columns(5)->schema([
                    TextEntry::make('receipt_number'),
                    TextEntry::make('paid_at')->dateTime('d M Y h:i A'),
                    TextEntry::make('amount')->formatStateUsing($money),
                    TextEntry::make('method')->badge(),
                    TextEntry::make('reference')->placeholder('—'),
                ])->placeholder('No payments yet.'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['member', 'plan']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('invoice_number')->label('Invoice')->searchable(),
                TextColumn::make('member.name')->searchable()->description(fn (Subscription $record) => $record->member?->phone),
                TextColumn::make('plan.name'),
                TextColumn::make('start_date')->date('d M Y')->sortable()->toggleable(),
                TextColumn::make('end_date')->label('Renewal date')->date('d M Y')->sortable()
                    ->color(fn (Subscription $record) => $record->days_left < 0 ? 'danger' : ($record->days_left <= 7 ? 'warning' : null)),
                TextColumn::make('total_amount')->label('Total')->formatStateUsing(fn ($state) => Money::format($state))->sortable(),
                TextColumn::make('balance_due')->label('Due')
                    ->formatStateUsing(fn ($state) => $state > 0 ? Money::format($state) : '—')
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'gray'),
                TextColumn::make('status')->badge(),
            ])
            ->filters([
                SelectFilter::make('status')->options(SubscriptionStatus::class),
                SelectFilter::make('membership_plan_id')->label('Plan')->options(fn () => GymOptions::plans()),
                Filter::make('end_date')
                    ->schema([DatePicker::make('ends_from')->label('Renewal from'), DatePicker::make('ends_until')->label('Renewal until')])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['ends_from'] ?? null, fn ($q, $d) => $q->whereDate('end_date', '>=', $d))
                        ->when($data['ends_until'] ?? null, fn ($q, $d) => $q->whereDate('end_date', '<=', $d))),
            ])
            ->recordActions([
                MemberActions::recordPayment(),
                ActionGroup::make([
                    ViewAction::make(),
                    MemberActions::renew(),
                    SubscriptionActions::invoice(),
                    SubscriptionActions::paymentLink(),
                    SubscriptionActions::autoPay(),
                    SubscriptionActions::changeDates(),
                    SubscriptionActions::cancel(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSubscriptions::route('/'),
            'create' => CreateSubscription::route('/create'),
            'view' => ViewSubscription::route('/{record}'),
        ];
    }
}
