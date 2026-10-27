<?php

namespace App\Filament\Gym\Resources\Members\RelationManagers;

use App\Support\Access;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Access::can('payments.view');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('paid_at', 'desc')
            ->columns([
                TextColumn::make('receipt_number')->label('Receipt'),
                TextColumn::make('paid_at')->dateTime('d M Y, h:i A'),
                TextColumn::make('amount')->formatStateUsing(fn ($state) => Money::format($state)),
                TextColumn::make('method')->badge(),
                TextColumn::make('reference')->placeholder('—'),
                TextColumn::make('subscription.invoice_number')->label('Invoice'),
                TextColumn::make('receivedBy.name')->label('Received by')->placeholder('Online'),
            ])
            ->recordActions([
                Action::make('receipt')->icon(Heroicon::OutlinedPrinter)->color('gray')
                    ->url(fn ($record) => route('receipts.show', $record), shouldOpenInNewTab: true),
            ]);
    }
}
