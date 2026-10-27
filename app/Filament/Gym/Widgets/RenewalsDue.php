<?php

namespace App\Filament\Gym\Widgets;

use App\Filament\Gym\Actions\MemberActions;
use App\Filament\Gym\Resources\Members\MemberResource;
use App\Models\Subscription;
use App\Support\Access;
use App\Support\Money;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RenewalsDue extends TableWidget
{
    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Renewals due in the next 7 days';

    public static function canView(): bool
    {
        return Access::can('subscriptions.view');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Subscription::query()->active()->endingBetween(today(), today()->addDays(7))->with(['member', 'plan']))
            ->defaultSort('end_date')
            ->paginated([5, 10, 25])
            ->columns([
                TextColumn::make('member.name')->url(fn (Subscription $record) => MemberResource::getUrl('view', ['record' => $record->member_id])),
                TextColumn::make('member.phone')->label('Phone'),
                TextColumn::make('plan.name'),
                TextColumn::make('end_date')->label('Ends')->date('D, d M')->description(fn (Subscription $record) => $record->days_left.' days left'),
                TextColumn::make('balance_due')->label('Due')->formatStateUsing(fn ($state) => $state > 0 ? Money::format($state) : '—'),
            ])
            ->recordActions([MemberActions::renew()])
            ->emptyStateHeading('No renewals due this week');
    }
}
