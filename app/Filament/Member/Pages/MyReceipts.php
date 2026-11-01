<?php

namespace App\Filament\Member\Pages;

use App\Models\Payment;
use App\Support\CurrentMember;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/** Every payment the member made, each with its receipt PDF. */
class MyReceipts extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $title = 'Receipts';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.gym.pages.table-page';

    public function table(Table $table): Table
    {
        return $table
            ->query(Payment::query()->where('member_id', CurrentMember::get()->id)->with('subscription.plan'))
            ->defaultSort('paid_at', 'desc')
            ->columns([
                TextColumn::make('paid_at')->label('Date')->dateTime('d M Y, h:i A'),
                TextColumn::make('receipt_number')->label('Receipt'),
                TextColumn::make('subscription.plan.name')->label('For')->placeholder('—')
                    ->description(fn (Payment $record) => $record->subscription?->invoice_number),
                TextColumn::make('method')->label('Paid by')->badge()->color('gray'),
                TextColumn::make('amount')->formatStateUsing(fn ($state) => Money::format($state))->weight('bold')
                    ->summarize(Sum::make()->label('Total')->formatStateUsing(fn ($state) => Money::format($state))),
            ])
            ->recordActions([
                Action::make('receipt')
                    ->label('Receipt')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->url(fn (Payment $record) => route('receipts.show', $record), shouldOpenInNewTab: true),
            ])
            ->emptyStateHeading('No payments yet');
    }
}
