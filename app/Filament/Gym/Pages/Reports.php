<?php

namespace App\Filament\Gym\Pages;

use App\Services\GymReports;
use App\Support\Access;
use App\Support\Tenancy;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;

/** Retention, revenue per trainer, busiest hours and enquiry conversion. */
class Reports extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartPie;

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Reports';

    protected string $view = 'filament.gym.pages.reports';

    #[Url]
    public int $days = 90;

    public static function canAccess(): bool
    {
        return Access::can('reports.view');
    }

    protected function getViewData(): array
    {
        $this->days = in_array($this->days, [30, 90, 180, 365], true) ? $this->days : 90;
        $reports = new GymReports(Tenancy::gym());
        $from = now()->subDays($this->days)->startOfDay();

        return [
            'retention' => $reports->retention(6),
            'trainers' => $reports->revenuePerTrainer($from, now()),
            'hours' => $reports->busiestHours(min($this->days, 90)),
            'leads' => $reports->leadConversion($from, now()),
            'currency' => Tenancy::gym()->currency,
        ];
    }
}
