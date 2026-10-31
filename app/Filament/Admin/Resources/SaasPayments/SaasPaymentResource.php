<?php

namespace App\Filament\Admin\Resources\SaasPayments;

use App\Filament\Admin\Resources\SaasPayments\Pages\ListSaasPayments;
use App\Models\SaasPayment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** Everything gyms have paid the platform. */
class SaasPaymentResource extends Resource
{
    protected static ?string $model = SaasPayment::class;

    protected static ?string $navigationLabel = 'Software payments';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?int $navigationSort = 3;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('paid_at', 'desc')
            ->columns([
                TextColumn::make('paid_at')->label('Date')->dateTime('d M Y')->sortable(),
                TextColumn::make('gym.name')->searchable(),
                TextColumn::make('kind')->badge()->formatStateUsing(fn ($state) => $state === 'credits' ? 'Message credits' : 'Software plan'),
                TextColumn::make('saasPlan.name')->label('Plan')->placeholder('—'),
                TextColumn::make('months')->suffix(' mo')->placeholder('—'),
                TextColumn::make('amount')->money('INR')->summarize(Sum::make()->money('INR')),
                TextColumn::make('reference')->placeholder('—')->toggleable(),
                TextColumn::make('commission.amount')->label('Partner commission')->money('INR')->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('kind')->options(['subscription' => 'Software plan', 'credits' => 'Message credits']),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => ListSaasPayments::route('/')];
    }
}
