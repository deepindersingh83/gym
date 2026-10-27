<?php

namespace App\Filament\Gym\Resources\Subscriptions\Pages;

use App\Filament\Gym\Actions\MemberActions;
use App\Filament\Gym\Resources\Subscriptions\SubscriptionResource;
use App\Filament\Gym\Support\MembershipFields;
use App\Models\Member;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class CreateSubscription extends CreateRecord
{
    protected static string $resource = SubscriptionResource::class;

    protected static ?string $title = 'Sell membership';

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Select::make('member_id')
                ->label('Member')
                ->searchable()
                ->getSearchResultsUsing(fn (string $search) => Member::query()
                    ->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")->orWhere('member_code', 'like', "%{$search}%"))
                    ->limit(20)->get()->mapWithKeys(fn (Member $m) => [$m->id => "{$m->name} ({$m->phone})"]))
                ->getOptionLabelUsing(fn ($value) => Member::query()->find($value)?->name)
                ->default(request()->integer('member') ?: null)
                ->required(),
            ...MembershipFields::make(),
        ]);
    }

    protected function handleRecordCreation(array $data): Model
    {
        return MemberActions::sell(Member::query()->findOrFail($data['member_id']), $data);
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->record]);
    }
}
