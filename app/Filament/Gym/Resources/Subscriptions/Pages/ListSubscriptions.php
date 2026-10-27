<?php

namespace App\Filament\Gym\Resources\Subscriptions\Pages;

use App\Enums\SubscriptionStatus;
use App\Filament\Gym\Resources\Subscriptions\SubscriptionResource;
use App\Models\Subscription;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListSubscriptions extends ListRecords
{
    protected static string $resource = SubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Sell membership'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),
            'active' => Tab::make('Active')
                ->modifyQueryUsing(fn (Builder $query) => $query->active()->whereDate('end_date', '>=', today())),
            'renewals' => Tab::make('Renewals due (7 days)')
                ->badge(fn () => Subscription::query()->active()->endingBetween(today(), today()->addDays(7))->count() ?: null)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->active()->endingBetween(today(), today()->addDays(7))),
            'expired' => Tab::make('Expired')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', SubscriptionStatus::Expired)),
            'dues' => Tab::make('With dues')
                ->badge(fn () => Subscription::query()->withDues()->count() ?: null)
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->withDues()),
        ];
    }
}
