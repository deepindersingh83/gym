<?php

namespace App\Services;

use App\Enums\MessageChannel;
use App\Enums\StaffRole;
use App\Filament\Gym\Pages\MessageCredits;
use App\Models\CreditTransaction;
use App\Models\Gym;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** A gym's prepaid SMS / WhatsApp credit balance and its ledger. */
class Credits
{
    /** Credits one message costs, or 0 when the gym sends it through its own provider account. */
    public function costFor(Gym $gym, MessageChannel $channel): int
    {
        return match ($channel) {
            MessageChannel::WhatsApp => filled(data_get($gym->integrations, 'whatsapp_token')) ? 0 : (int) config('gym.credits.cost.whatsapp'),
            MessageChannel::Sms => filled(data_get($gym->integrations, 'msg91_auth_key')) ? 0 : (int) config('gym.credits.cost.sms'),
            default => 0,
        };
    }

    /** Takes credits if the balance allows it. Returns the ledger entry, or null when there are not enough. */
    public function debit(Gym $gym, int $credits, string $description): ?CreditTransaction
    {
        if ($credits <= 0) {
            return null;
        }

        return DB::transaction(function () use ($gym, $credits, $description) {
            // Conditional update: two messages sent at once can never overdraw the balance.
            $taken = Gym::query()->whereKey($gym->id)->where('message_credits', '>=', $credits)->decrement('message_credits', $credits);

            if (! $taken) {
                return null;
            }

            $balance = (int) Gym::query()->whereKey($gym->id)->value('message_credits');
            $gym->message_credits = $balance;

            return $this->record($gym, 'usage', -$credits, $balance, $description);
        });
    }

    public function add(Gym $gym, int $credits, string $type, string $description, array $attributes = []): CreditTransaction
    {
        return DB::transaction(function () use ($gym, $credits, $type, $description, $attributes) {
            Gym::query()->whereKey($gym->id)->increment('message_credits', $credits);
            $balance = (int) Gym::query()->whereKey($gym->id)->value('message_credits');
            $gym->message_credits = $balance;

            return $this->record($gym, $type, $credits, $balance, $description, $attributes);
        });
    }

    /** Gives back credits for a message that could not be delivered. */
    public function refund(Gym $gym, CreditTransaction $usage): void
    {
        $this->add($gym, -$usage->credits, 'adjustment', 'Refund: message not delivered');
        $usage->delete();
    }

    public function isOut(Gym $gym, MessageChannel $channel): bool
    {
        $cost = $this->costFor($gym, $channel);

        return $cost > 0 && (int) Gym::query()->whereKey($gym->id)->value('message_credits') < $cost;
    }

    /** At most one bell alert per gym per day when the balance is low or empty. */
    public function alertIfLow(Gym $gym): void
    {
        $balance = (int) $gym->message_credits;

        if ($balance >= (int) config('gym.credits.low_balance') || ! Cache::add("credits-alert:{$gym->id}:".today()->toDateString(), true, now()->endOfDay())) {
            return;
        }

        $owners = $gym->users()->wherePivot('role', StaffRole::Owner->value)->wherePivot('is_active', true)->get();

        Notification::make()
            ->title($balance > 0 ? "Only {$balance} message credits left" : 'Out of message credits')
            ->body($balance > 0 ? 'Top up so WhatsApp and SMS reminders keep going out.' : 'WhatsApp and SMS reminders are paused until you top up.')
            ->icon('heroicon-o-chat-bubble-left-ellipsis')
            ->color($balance > 0 ? 'warning' : 'danger')
            ->actions([Action::make('topUp')->label('Buy credits')->url(MessageCredits::getUrl(tenant: $gym, panel: 'gym'))])
            ->sendToDatabase($owners);
    }

    protected function record(Gym $gym, string $type, int $credits, int $balance, string $description, array $attributes = []): CreditTransaction
    {
        return CreditTransaction::withoutGlobalScopes()->create([
            'gym_id' => $gym->id,
            'type' => $type,
            'credits' => $credits,
            'balance_after' => $balance,
            'description' => mb_substr($description, 0, 250),
            'created_by' => auth()->id(),
            ...$attributes,
        ]);
    }
}
