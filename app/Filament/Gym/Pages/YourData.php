<?php

namespace App\Filament\Gym\Pages;

use App\Enums\StaffRole;
use App\Services\Compliance\GymClosure;
use App\Services\Compliance\GymDataExport;
use App\Support\Tenancy;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** Owner tools for the gym's data: download everything, or close the account. */
class YourData extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Setup';

    protected static ?int $navigationSort = 95;

    protected static ?string $title = 'Your data';

    protected static ?string $slug = 'your-data';

    protected string $view = 'filament.gym.pages.your-data';

    public static function canAccess(): bool
    {
        return Tenancy::role() === StaffRole::Owner;
    }

    public function downloadAction(): Action
    {
        return Action::make('download')
            ->label('Prepare download (ZIP)')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->action(function () {
                $gym = Tenancy::gym();
                app(GymDataExport::class)->request($gym, auth()->user());
                activity()->causedBy(auth()->user())->performedOn($gym)->tap(fn ($a) => $a->gym_id = $gym->id)->log('Asked for a download of all gym data');

                Notification::make()->title('Preparing your download')
                    ->body('We will email '.auth()->user()->email.' a link when it is ready, usually within a few minutes. It also appears on this page.')
                    ->success()->send();
            });
    }

    protected function getViewData(): array
    {
        return [
            'exports' => \App\Models\GymDataExport::query()->where('gym_id', Tenancy::gymId())->latest('id')->limit(5)->get(),
        ];
    }

    public function closeAction(): Action
    {
        return Action::make('close')
            ->label('Close account')
            ->color('danger')
            ->icon(Heroicon::OutlinedTrash)
            ->modalHeading('Close your gym account?')
            ->modalDescription('Staff and members will no longer be able to sign in, and any auto-renew is stopped. Your data is kept for '.GymClosure::KEEP_DAYS.' days in case you change your mind (contact support to restore it), then deleted for good. Download your data first.')
            ->modalSubmitActionLabel('Close account')
            ->schema([
                Textarea::make('reason')->label('Why are you leaving? (optional)')->rows(2)->maxLength(500),
                TextInput::make('confirm')->label(fn () => 'Type "'.Tenancy::gym()->name.'" to confirm')->required()
                    ->rule(fn () => 'in:'.Tenancy::gym()->name)
                    ->validationMessages(['in' => 'The gym name does not match.']),
            ])
            ->action(function (array $data) {
                $gym = Tenancy::gym();
                activity()->causedBy(auth()->user())->performedOn($gym)->tap(fn ($a) => $a->gym_id = $gym->id)->log('Closed the gym account');
                app(GymClosure::class)->close($gym, $data['reason'] ?: 'Closed by the owner');

                Notification::make()->title('Your account is closed')
                    ->body('It will be deleted on '.now()->addDays(GymClosure::KEEP_DAYS)->format('d M Y').'. Contact support before then to restore it.')
                    ->success()->persistent()->send();

                return redirect(Filament::getPanel('gym')->getUrl());
            });
    }
}
