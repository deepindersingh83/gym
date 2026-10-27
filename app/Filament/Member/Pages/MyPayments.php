<?php

namespace App\Filament\Member\Pages;

use App\Models\Subscription;
use App\Support\CurrentMember;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/** Membership history with invoices and payments. */
class MyPayments extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static ?string $title = 'Memberships & payments';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.gym.pages.table-page';

    public function table(Table $table): Table
    {
        return $table
            ->query(Subscription::query()->where('member_id', CurrentMember::get()->id)->with(['plan', 'payments']))
            ->defaultSort('end_date', 'desc')
            ->columns([
                TextColumn::make('plan.name'),
                TextColumn::make('start_date')->date('d M Y'),
                TextColumn::make('end_date')->label('Ends')->date('d M Y'),
                TextColumn::make('total_amount')->label('Total')->formatStateUsing(fn ($state) => Money::format($state)),
                TextColumn::make('amount_paid')->label('Paid')->formatStateUsing(fn ($state) => Money::format($state)),
                TextColumn::make('balance_due')->label('Due')->formatStateUsing(fn ($state) => $state > 0 ? Money::format($state) : '—')->color(fn ($state) => $state > 0 ? 'danger' : null),
                TextColumn::make('status')->badge(),
            ])
            ->recordActions([
                Action::make('invoice')->icon(Heroicon::OutlinedDocumentText)->url(fn (Subscription $record) => route('invoices.show', $record), shouldOpenInNewTab: true),
            ]);
    }
}
