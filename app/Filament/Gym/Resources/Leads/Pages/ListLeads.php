<?php

namespace App\Filament\Gym\Resources\Leads\Pages;

use App\Enums\LeadStatus;
use App\Filament\Gym\Resources\Leads\LeadResource;
use App\Models\Lead;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListLeads extends ListRecords
{
    protected static string $resource = LeadResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Add enquiry')];
    }

    public function getTabs(): array
    {
        return [
            'open' => Tab::make('Open')
                ->modifyQueryUsing(fn (Builder $query) => $query->open()),
            'trials' => Tab::make('Upcoming trials')
                ->badge(fn () => Lead::query()->open()->whereDate('trial_date', '>=', today())->count() ?: null)
                ->modifyQueryUsing(fn (Builder $query) => $query->open()->whereDate('trial_date', '>=', today())->reorder('trial_date')),
            'follow_up' => Tab::make('Follow-ups due')
                ->badge(fn () => Lead::query()->open()->where('follow_up_at', '<=', now())->count() ?: null)
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->open()->where('follow_up_at', '<=', now())),
            'converted' => Tab::make('Joined')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', LeadStatus::Converted)),
            'lost' => Tab::make('Lost')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', LeadStatus::Lost)),
            'all' => Tab::make('All'),
        ];
    }
}
