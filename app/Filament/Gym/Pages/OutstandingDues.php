<?php

namespace App\Filament\Gym\Pages;

use App\Enums\MessageEvent;
use App\Filament\Gym\Actions\MemberActions;
use App\Filament\Gym\Actions\SubscriptionActions;
use App\Filament\Gym\Resources\Members\MemberResource;
use App\Models\Subscription;
use App\Services\Messaging\Messenger;
use App\Support\Access;
use App\Support\Money;
use App\Support\SpreadsheetExport;
use App\Support\Tenancy;
use BackedEnum;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/** Every membership with an unpaid balance, oldest first. */
class OutstandingDues extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static string|UnitEnum|null $navigationGroup = 'Billing';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Outstanding dues';

    protected string $view = 'filament.gym.pages.table-page';

    public static function canAccess(): bool
    {
        return Access::can('payments.view');
    }

    public static function getNavigationBadge(): ?string
    {
        return Subscription::query()->withDues()->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    protected function query(): Builder
    {
        return Subscription::query()->withDues()->with(['member', 'plan']);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->query())
            ->defaultSort('end_date')
            ->columns([
                TextColumn::make('member.name')->searchable()
                    ->url(fn (Subscription $record) => MemberResource::getUrl('view', ['record' => $record->member_id])),
                TextColumn::make('member.phone')->label('Phone')->searchable(),
                TextColumn::make('invoice_number')->label('Invoice')->searchable(),
                TextColumn::make('plan.name'),
                TextColumn::make('end_date')->label('Membership ends')->date('d M Y')->sortable(),
                TextColumn::make('total_amount')->label('Billed')->formatStateUsing(fn ($state) => Money::format($state)),
                TextColumn::make('amount_paid')->label('Paid')->formatStateUsing(fn ($state) => Money::format($state)),
                TextColumn::make('balance_due')->label('Due')
                    ->formatStateUsing(fn ($state) => Money::format($state))->color('danger')->weight('bold')
                    ->summarize(Summarizer::make()->label('Total due')
                        ->using(fn (QueryBuilder $query) => $query->sum(DB::raw('total_amount - amount_paid')))
                        ->formatStateUsing(fn ($state) => Money::format($state))),
            ])
            ->recordActions([
                MemberActions::recordPayment(),
                Action::make('remind')
                    ->label('Remind')
                    ->icon(Heroicon::OutlinedBellAlert)
                    ->color('gray')
                    ->action(function (Subscription $record) {
                        $sent = app(Messenger::class)->send($record->member, MessageEvent::DuesReminder, subscription: $record);
                        Notification::make()->title($sent ? 'Reminder sent' : 'No reminder sent — check Sent messages for details')->status($sent ? 'success' : 'warning')->send();
                    }),
                SubscriptionActions::paymentLink(),
            ])
            ->emptyStateHeading('No pending dues')
            ->emptyStateIcon(Heroicon::OutlinedCheckCircle);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('excel')
                ->label('Excel')
                ->icon(Heroicon::OutlinedTableCells)
                ->color('gray')
                ->action(fn () => SpreadsheetExport::download(
                    'outstanding-dues-'.now()->format('Y-m-d').'.xlsx',
                    ['Member', 'Phone', 'Invoice', 'Plan', 'Membership ends', 'Billed', 'Paid', 'Due'],
                    $this->query()->orderBy('end_date')->lazy()->map(fn (Subscription $s) => [
                        $s->member?->name, $s->member?->phone, $s->invoice_number, $s->plan?->name, $s->end_date->toDateString(),
                        (float) $s->total_amount, (float) $s->amount_paid, $s->balance_due,
                    ]),
                )),
            Action::make('pdf')
                ->label('PDF')
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->color('gray')
                ->action(function () {
                    $rows = $this->query()->orderBy('end_date')->get();
                    $pdf = Pdf::loadView('pdf.dues-report', ['gym' => Tenancy::gym(), 'rows' => $rows]);

                    return response()->streamDownload(fn () => print ($pdf->output()), 'outstanding-dues-'.now()->format('Y-m-d').'.pdf');
                }),
        ];
    }
}
