<?php

namespace App\Filament\Partner\Pages;

use App\Filament\Partner\Widgets\PartnerStats;
use App\Models\Gym;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

/** Partner home: referral link, earnings and the gyms they referred. */
class Dashboard extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static ?string $title = 'Partner dashboard';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.partner.dashboard';

    protected function getHeaderWidgets(): array
    {
        return [PartnerStats::class];
    }

    protected function getViewData(): array
    {
        $partner = auth()->user()->partner;

        return ['partner' => $partner, 'link' => $partner->referralUrl()];
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Gyms you referred')
            ->query(Gym::query()->where('partner_id', auth()->user()->partner->id)->with('saasPlan'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('city')->placeholder('—'),
                TextColumn::make('saasPlan.name')->label('Plan')->badge(),
                TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => ucfirst($state))
                    ->color(fn ($state) => $state === 'active' ? 'success' : ($state === 'trial' ? 'warning' : 'gray')),
                TextColumn::make('created_at')->label('Joined')->date('d M Y'),
            ])
            ->emptyStateHeading('No gyms yet')
            ->emptyStateDescription('Share your referral link with gym owners. Gyms that sign up through it appear here.');
    }
}
