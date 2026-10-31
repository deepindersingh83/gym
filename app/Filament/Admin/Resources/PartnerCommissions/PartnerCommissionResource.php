<?php

namespace App\Filament\Admin\Resources\PartnerCommissions;

use App\Filament\Admin\Resources\PartnerCommissions\Pages\ListPartnerCommissions;
use App\Models\PartnerCommission;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use UnitEnum;

class PartnerCommissionResource extends Resource
{
    protected static ?string $model = PartnerCommission::class;

    protected static ?string $navigationLabel = 'Commissions';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyRupee;

    protected static string|UnitEnum|null $navigationGroup = 'Partners';

    protected static ?int $navigationSort = 2;

    public static function getNavigationBadge(): ?string
    {
        return PartnerCommission::query()->where('status', 'pending')->count() ?: null;
    }

    public static function table(Table $table): Table
    {
        $markPaid = fn (Collection $records, array $data) => $records->where('status', 'pending')->each->update([
            'status' => 'paid', 'paid_at' => now(), 'payout_reference' => $data['payout_reference'] ?? null,
        ]);

        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Date')->date('d M Y')->sortable(),
                TextColumn::make('partner.name')->searchable(),
                TextColumn::make('gym.name')->searchable(),
                TextColumn::make('saasPayment.amount')->label('Gym paid')->money('INR'),
                TextColumn::make('rate')->suffix('%'),
                TextColumn::make('amount')->label('Commission')->money('INR')->weight('bold')
                    ->summarize(Sum::make()->money('INR')),
                TextColumn::make('status')->badge()->color(fn ($state) => match ($state) {
                    'paid' => 'success', 'pending' => 'warning', default => 'gray'
                }),
                TextColumn::make('payout_reference')->label('Payout ref.')->placeholder('—')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(['pending' => 'To pay', 'paid' => 'Paid', 'cancelled' => 'Cancelled'])->default('pending'),
                SelectFilter::make('partner_id')->label('Partner')->relationship('partner', 'name'),
            ])
            ->recordActions([
                Action::make('markPaid')
                    ->label('Mark paid')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->visible(fn (PartnerCommission $record) => $record->status === 'pending')
                    ->schema([TextInput::make('payout_reference')->label('Payout reference (UTR / UPI ref.)')])
                    ->action(fn (PartnerCommission $record, array $data) => $markPaid(collect([$record]), $data)),
            ])
            ->toolbarActions([
                BulkAction::make('markPaidBulk')
                    ->label('Mark selected paid')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->schema([TextInput::make('payout_reference')->label('Payout reference')])
                    ->action($markPaid)
                    ->deselectRecordsAfterCompletion(),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => ListPartnerCommissions::route('/')];
    }
}
