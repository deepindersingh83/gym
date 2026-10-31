<?php

namespace App\Filament\Partner\Pages;

use App\Models\PartnerCommission;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class Commissions extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyRupee;

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.gym.pages.table-page';

    public function table(Table $table): Table
    {
        return $table
            ->query(PartnerCommission::query()->where('partner_id', auth()->user()->partner->id)->with(['gym', 'saasPayment']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Date')->date('d M Y'),
                TextColumn::make('gym.name'),
                TextColumn::make('saasPayment.amount')->label('Gym paid')->money('INR'),
                TextColumn::make('rate')->suffix('%'),
                TextColumn::make('amount')->label('Your commission')->money('INR')->weight('bold')->summarize(Sum::make()->money('INR')),
                TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => $state === 'pending' ? 'To be paid' : ucfirst($state))
                    ->color(fn ($state) => $state === 'paid' ? 'success' : 'warning'),
                TextColumn::make('paid_at')->label('Paid on')->date('d M Y')->placeholder('—'),
            ])
            ->filters([SelectFilter::make('status')->options(['pending' => 'To be paid', 'paid' => 'Paid'])])
            ->emptyStateHeading('No commissions yet');
    }
}
