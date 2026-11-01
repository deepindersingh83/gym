<?php

namespace App\Filament\Gym\Pages;

use App\Models\CreditTransaction;
use App\Services\Billing\BillingCheckout;
use App\Services\Payments\PlatformBilling;
use App\Support\Access;
use App\Support\Tenancy;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Throwable;
use UnitEnum;

/** Prepaid SMS / WhatsApp credits: balance, packs to buy, and history. */
class MessageCredits extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Messaging';

    protected static ?int $navigationSort = 4;

    protected static ?string $title = 'Message credits';

    protected string $view = 'filament.gym.pages.message-credits';

    public static function canAccess(): bool
    {
        return Access::can('messaging.view');
    }

    public static function getNavigationBadge(): ?string
    {
        $balance = (int) Tenancy::gym()?->message_credits;

        return $balance < (int) config('gym.credits.low_balance') ? (string) $balance : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    protected function getViewData(): array
    {
        $billing = app(PlatformBilling::class);

        return [
            'balance' => (int) Tenancy::gym()->fresh()->message_credits,
            'packs' => $billing->packs(Tenancy::gym()->currency),
            'currency' => Tenancy::gym()->currency,
            'canBuyOnline' => app(BillingCheckout::class)->isAvailable(Tenancy::gym()),
            'canBuy' => Access::can('settings.edit'),
            'costs' => config('gym.credits.cost'),
            'ownWhatsApp' => filled(data_get(Tenancy::gym()->integrations, 'whatsapp_token')),
            'ownSms' => filled(data_get(Tenancy::gym()->integrations, 'msg91_auth_key')),
        ];
    }

    public function buyAction(): Action
    {
        return Action::make('buy')
            ->label('Buy')
            ->visible(fn () => Access::can('settings.edit'))
            ->action(function (array $arguments) {
                try {
                    return redirect()->away(app(BillingCheckout::class)->buyCredits(Tenancy::gym(), (int) $arguments['credits']));
                } catch (Throwable $e) {
                    Notification::make()->title('Could not start the payment')->body($e->getMessage())->danger()->send();
                }
            });
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Credit history')
            ->query(CreditTransaction::query()->with('messageLog.member'))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime('d M Y, h:i A'),
                TextColumn::make('type')->badge()->formatStateUsing(fn ($state) => ucfirst($state))
                    ->color(fn ($state) => match ($state) {
                        'purchase' => 'success', 'usage' => 'gray', default => 'warning'
                    }),
                TextColumn::make('description')->wrap(),
                TextColumn::make('messageLog.member.name')->label('Member')->placeholder('—'),
                TextColumn::make('credits')->formatStateUsing(fn ($state) => ($state > 0 ? '+' : '').$state)
                    ->color(fn ($state) => $state > 0 ? 'success' : null)->weight('bold'),
                TextColumn::make('balance_after')->label('Balance'),
            ])
            ->filters([
                SelectFilter::make('type')->options(['usage' => 'Used', 'purchase' => 'Bought', 'adjustment' => 'Adjusted']),
            ]);
    }
}
