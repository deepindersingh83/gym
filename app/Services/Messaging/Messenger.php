<?php

namespace App\Services\Messaging;

use App\Enums\MessageChannel;
use App\Enums\MessageEvent;
use App\Enums\SubscriptionStatus;
use App\Models\Broadcast;
use App\Models\CreditTransaction;
use App\Models\Gym;
use App\Models\Member;
use App\Models\MessageLog;
use App\Models\MessageTemplate;
use App\Models\Subscription;
use App\Services\Credits;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends member messages over the gym's chosen channels using its templates,
 * logs every attempt, and skips anything already sent for the same dedupe key.
 */
class Messenger
{
    public function __construct(protected Credits $credits) {}

    /**
     * @param  array<string, mixed>  $vars  Extra placeholder values.
     * @param  list<MessageChannel>|null  $channels  Defaults to the gym's reminder channels.
     * @return int Number of messages actually sent.
     */
    public function send(
        Member $member,
        MessageEvent $event,
        array $vars = [],
        ?Subscription $subscription = null,
        ?string $dedupeKey = null,
        ?array $channels = null,
        ?Broadcast $broadcast = null,
        array $secret = [],
    ): int {
        $gym = $member->gym;
        $channels ??= array_map(fn ($c) => MessageChannel::from($c), (array) $gym->setting('channels'));
        $vars = [...$this->variables($gym, $member, $subscription), ...$vars];
        $sent = 0;

        foreach ($channels as $channel) {
            if ($dedupeKey && MessageLog::withoutGlobalScopes()->where('gym_id', $gym->id)->where('dedupe_key', $dedupeKey)->where('channel', $channel)->whereIn('status', ['sent', 'logged'])->exists()) {
                continue;
            }

            $recipient = match ($channel) {
                MessageChannel::Email => $member->email,
                MessageChannel::InApp => $member->user?->email,
                default => $member->phone,
            };

            if (blank($recipient)) {
                continue;
            }

            $template = MessageTemplate::withoutGlobalScopes()
                ->where('gym_id', $gym->id)->where('event', $event)->where('channel', $channel)->first();

            if ($template && ! $template->is_active) {
                continue;
            }

            $rawBody = $broadcast?->body ?? $template?->body ?? $event->defaultBody();
            $body = $this->render($rawBody, $vars);
            $subject = $broadcast?->title ?? ($template?->subject ? $this->render($template->subject, $vars) : $event->getLabel().' — '.$gym->name);

            [$status, $error, $usage] = $this->deliver($gym, $channel, $member, $recipient, $body, $subject, $template?->provider_template, $this->orderedValues($rawBody, $vars), chargeable: $event !== MessageEvent::LoginCode);

            // Secrets (login codes) never reach the log that gym staff can read.
            $loggedBody = $secret ? $this->render($rawBody, [...$vars, ...array_fill_keys($secret, '••••••')]) : $body;

            $log = MessageLog::withoutGlobalScopes()->create([
                'gym_id' => $gym->id,
                'member_id' => $member->id,
                'subscription_id' => $subscription?->id,
                'broadcast_id' => $broadcast?->id,
                'event' => $event,
                'channel' => $channel,
                'recipient' => $recipient,
                'body' => $loggedBody,
                'status' => $status,
                'error' => $error,
                'dedupe_key' => $dedupeKey,
            ]);

            $usage?->update(['message_log_id' => $log->id]);
            $sent += $status === 'failed' ? 0 : 1;
        }

        return $sent;
    }

    public function broadcastAudience(Broadcast $broadcast): Builder
    {
        $activeNow = fn ($q) => $q->where('status', SubscriptionStatus::Active)->whereDate('end_date', '>=', today());

        return Member::withoutGlobalScopes()
            ->where('gym_id', $broadcast->gym_id)
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->when($broadcast->branch_id, fn ($q, $id) => $q->where('branch_id', $id))
            ->when($broadcast->audience === 'active', fn ($q) => $q->whereHas('subscriptions', $activeNow))
            ->when($broadcast->audience === 'expired', fn ($q) => $q->whereHas('subscriptions')->whereDoesntHave('subscriptions', $activeNow));
    }

    public function sendBroadcast(Broadcast $broadcast): int
    {
        $members = 0;
        $channels = array_map(fn ($c) => MessageChannel::from($c), $broadcast->channels);

        $this->broadcastAudience($broadcast)->with(['gym', 'user'])->lazyById()->each(function (Member $member) use ($broadcast, $channels, &$members) {
            $members += $this->send($member, MessageEvent::Broadcast, ['message' => $broadcast->body], channels: $channels, broadcast: $broadcast, dedupeKey: "broadcast:{$broadcast->id}:{$member->id}") > 0 ? 1 : 0;
        });

        $broadcast->forceFill(['sent_at' => now(), 'recipients_count' => $members])->save();

        return $members;
    }

    /** @return array<string, string> */
    protected function variables(Gym $gym, Member $member, ?Subscription $subscription): array
    {
        $subscription ??= $member->latestSubscription;

        return [
            'name' => $member->name,
            'gym' => $gym->name,
            'member_code' => $member->member_code,
            'phone' => $gym->phone ?? '',
            'currency' => Money::symbol($gym->currency),
            'plan' => $subscription?->plan?->name ?? '',
            'end_date' => $subscription?->end_date?->format('d M Y') ?? '',
            'days_left' => (string) ($subscription?->days_left ?? ''),
            'due' => number_format($subscription?->balance_due ?? $member->balance_due, 2),
            'review_link' => (string) $gym->setting('google_review_url'),
        ];
    }

    public function render(string $body, array $vars): string
    {
        return preg_replace_callback('/\{(\w+)\}/', fn ($m) => array_key_exists($m[1], $vars) ? (string) $vars[$m[1]] : $m[0], $body);
    }

    /** Placeholder values in the order they appear, for providers' numbered template variables. */
    protected function orderedValues(string $body, array $vars): array
    {
        preg_match_all('/\{(\w+)\}/', $body, $matches);

        return array_map(fn ($key) => (string) ($vars[$key] ?? ''), $matches[1]);
    }

    /** @return array{0: string, 1: ?string, 2: ?CreditTransaction} status (sent, logged, failed), error, credits used */
    protected function deliver(Gym $gym, MessageChannel $channel, Member $member, string $recipient, string $body, string $subject, ?string $providerTemplate, array $params, bool $chargeable = true): array
    {
        $usage = null;

        try {
            $driver = match ($channel) {
                MessageChannel::WhatsApp => new WhatsAppChannel($gym),
                MessageChannel::Sms => new SmsChannel($gym),
                MessageChannel::Email => new EmailChannel($gym),
                MessageChannel::InApp => new InAppChannel($gym),
            };

            if (! $driver->isConfigured()) {
                Log::info("[{$channel->value}] to {$recipient}: {$body}");

                return ['logged', 'No provider configured; message written to the log only.', null];
            }

            // Messages through the platform's WhatsApp / SMS account use the gym's prepaid credits.
            // Login codes are free so members can always get into the app.
            if ($chargeable && ($cost = $this->credits->costFor($gym, $channel))) {
                $usage = $this->credits->debit($gym, $cost, $channel->getLabel().' to '.$recipient);

                if (! $usage) {
                    $this->credits->alertIfLow($gym);

                    return ['failed', 'Out of message credits. Top up under Messaging → Message credits.', null];
                }
            }

            $driver->send($member, $recipient, $body, $subject, $providerTemplate, $params);

            if ($usage) {
                $this->credits->alertIfLow($gym);
            }

            return ['sent', null, $usage];
        } catch (Throwable $e) {
            report($e);

            if ($usage) {
                $this->credits->refund($gym, $usage);
            }

            return ['failed', mb_substr($e->getMessage(), 0, 1000), null];
        }
    }
}
