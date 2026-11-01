<?php

namespace App\Filament\Admin\Pages;

use App\Services\Billing\RevenueReport;
use App\Support\AdminAccess;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;

/** MRR, ARR, churn, trial conversion, revenue by plan and partner, and renewals due. One currency at a time. */
class Revenue extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.admin.pages.revenue';

    #[Url]
    public string $currency = 'INR';

    public static function canAccess(): bool
    {
        return AdminAccess::can('revenue');
    }

    public function mount(): void
    {
        $this->currency = in_array($this->currency, ['INR', 'AUD'], true) ? $this->currency : 'INR';
    }

    protected function getViewData(): array
    {
        $report = new RevenueReport($this->currency);
        $mrr = $report->mrr();
        $thisMonth = $report->revenue(now()->startOfMonth(), now());
        $lastMonth = $report->revenue(now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth());

        return [
            'mrr' => $mrr,
            'arr' => $mrr * 12,
            'paying' => $report->payingGyms()->count(),
            'thisMonth' => $thisMonth,
            'lastMonth' => $lastMonth,
            'last12' => $report->revenue(now()->subYear(), now()),
            'churn' => $report->churn(),
            'conversion' => $report->trialConversion(),
            'byPlan' => $report->byPlan(),
            'byPartner' => $report->byPartner(),
            'renewals' => $report->renewalsDue(),
            'report' => $report,
        ];
    }
}
