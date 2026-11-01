<?php

namespace App\Filament\Gym\Resources\Payments\Pages;

use App\Filament\Gym\Resources\Payments\PaymentResource;
use App\Models\Payment;
use App\Support\Access;
use App\Support\Modules;
use App\Support\SpreadsheetExport;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export to Excel')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->visible(fn () => Access::can('reports.view') && Modules::enabled('exports'))
                ->action(fn () => SpreadsheetExport::download(
                    'payments-'.now()->format('Y-m-d').'.xlsx',
                    ['Receipt', 'Date', 'Member', 'Phone', 'Invoice', 'Method', 'Reference', 'Amount', 'Received by', 'Branch'],
                    $this->getFilteredSortedTableQuery()->with(['member', 'subscription', 'receivedBy', 'branch'])->lazy()->map(fn (Payment $p) => [
                        $p->receipt_number, $p->paid_at->format('Y-m-d H:i'), $p->member?->name, $p->member?->phone,
                        $p->subscription?->invoice_number, $p->method->getLabel(), $p->reference, (float) $p->amount,
                        $p->receivedBy?->name ?? 'Online', $p->branch?->name,
                    ]),
                )),
        ];
    }
}
