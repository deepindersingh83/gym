<?php

namespace App\Filament\Gym\Pages;

use App\Models\MemberReferral;
use App\Services\Referrals as ReferralService;
use App\Support\Access;
use App\Support\Tenancy;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/** Members who brought friends, and the free days given. */
class Referrals extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGift;

    protected static string|UnitEnum|null $navigationGroup = 'Members';

    protected static ?int $navigationSort = 20;

    protected static ?string $title = 'Referrals';

    protected string $view = 'filament.gym.pages.table-page';

    public static function canAccess(): bool
    {
        return Access::can('members.view') && Tenancy::gym() && app(ReferralService::class)->enabled(Tenancy::gym());
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(MemberReferral::query()->with(['referrer', 'referred']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Joined')->date('d M Y'),
                TextColumn::make('referrer.name')->label('Referred by')->description(fn (MemberReferral $r) => $r->referrer?->member_code),
                TextColumn::make('referred.name')->label('Friend')->description(fn (MemberReferral $r) => $r->referred?->member_code),
                TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => $state === 'rewarded' ? 'Rewarded' : 'Waiting for first payment')
                    ->color(fn ($state) => $state === 'rewarded' ? 'success' : 'gray'),
                TextColumn::make('referrer_days')->label('Days given')
                    ->formatStateUsing(fn ($state, MemberReferral $r) => $r->status === 'rewarded' ? "{$r->referrer_days} + {$r->friend_days}" : '—'),
            ])
            ->filters([SelectFilter::make('status')->options(['pending' => 'Waiting', 'rewarded' => 'Rewarded'])])
            ->emptyStateHeading('No referrals yet')
            ->emptyStateDescription('Members share their link from the member app (Refer a friend).');
    }
}
