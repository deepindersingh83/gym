<?php

namespace App\Filament\Gym\Resources\Members\Pages;

use App\Filament\Gym\Resources\Members\MemberResource;
use App\Models\Member;
use App\Support\Access;
use App\Support\SpreadsheetExport;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListMembers extends ListRecords
{
    protected static string $resource = MemberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export to Excel')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->visible(fn () => Access::can('reports.view'))
                ->action(fn () => SpreadsheetExport::download(
                    'members-'.now()->format('Y-m-d').'.xlsx',
                    ['Member ID', 'Name', 'Phone', 'Email', 'Gender', 'Date of birth', 'Plan', 'Renewal date', 'Balance due', 'Trainer', 'Branch', 'Joined on'],
                    $this->getFilteredTableQuery()->with(['latestSubscription.plan', 'trainer', 'branch'])->lazy()->map(fn (Member $m) => [
                        $m->member_code, $m->name, $m->phone, $m->email, $m->gender, $m->date_of_birth?->toDateString(),
                        $m->latestSubscription?->plan?->name, $m->latestSubscription?->end_date?->toDateString(), $m->balance_due,
                        $m->trainer?->name, $m->branch?->name, $m->joined_on?->toDateString(),
                    ]),
                )),
            CreateAction::make()->label('Add member'),
        ];
    }
}
