<?php

namespace App\Filament\Gym\Resources\Members\RelationManagers;

use App\Filament\Gym\Actions\MemberActions;
use App\Filament\Gym\Resources\Subscriptions\SubscriptionResource;
use App\Support\Access;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class SubscriptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'subscriptions';

    protected static ?string $title = 'Memberships';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Access::can('subscriptions.view');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('end_date', 'desc')
            ->columns([
                TextColumn::make('invoice_number')->label('Invoice'),
                TextColumn::make('plan.name'),
                TextColumn::make('start_date')->date('d M Y'),
                TextColumn::make('end_date')->date('d M Y'),
                TextColumn::make('total_amount')->label('Total')->formatStateUsing(fn ($state) => Money::format($state)),
                TextColumn::make('amount_paid')->label('Paid')->formatStateUsing(fn ($state) => Money::format($state)),
                TextColumn::make('balance_due')->label('Due')->formatStateUsing(fn ($state) => $state > 0 ? Money::format($state) : '—')->color(fn ($state) => $state > 0 ? 'danger' : 'gray'),
                TextColumn::make('status')->badge(),
            ])
            ->headerActions([
                MemberActions::sellMembership()->record(fn () => $this->getOwnerRecord()),
            ])
            ->recordActions([
                MemberActions::recordPayment(),
                Action::make('invoice')->icon(Heroicon::OutlinedDocumentText)->color('gray')
                    ->url(fn ($record) => route('invoices.show', $record), shouldOpenInNewTab: true),
                Action::make('open')->icon(Heroicon::OutlinedArrowTopRightOnSquare)->color('gray')
                    ->url(fn ($record) => SubscriptionResource::getUrl('view', ['record' => $record])),
            ]);
    }
}
