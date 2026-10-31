<?php

namespace App\Services\Payments;

use App\Models\Gym;
use App\Models\SaasPayment;
use App\Services\Credits;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Payments from gyms to the platform (message credit packs) through the platform's own Razorpay account. */
class PlatformBilling
{
    public function isConfigured(): bool
    {
        return filled(config('gym.platform_razorpay.key_id')) && filled(config('gym.platform_razorpay.key_secret'));
    }

    /** @return array<int, float> credits => price */
    public function packs(): array
    {
        return collect(config('gym.credits.packs'))->mapWithKeys(fn ($price, $credits) => [(int) $credits => (float) $price])->sortKeys()->all();
    }

    public function creditsPaymentLink(Gym $gym, int $credits): string
    {
        $price = $this->packs()[$credits] ?? throw new RuntimeException('Unknown credit pack.');

        if (! $this->isConfigured()) {
            throw new RuntimeException('Online top-up is not available yet. Please contact support to add credits.');
        }

        $owner = $gym->users()->wherePivot('role', 'owner')->first();

        $response = Http::baseUrl('https://api.razorpay.com/v1/')
            ->withBasicAuth(config('gym.platform_razorpay.key_id'), config('gym.platform_razorpay.key_secret'))
            ->acceptJson()
            ->timeout(20)
            ->post('payment_links', [
                'amount' => (int) round($price * 100),
                'currency' => 'INR',
                'description' => number_format($credits).' message credits for '.$gym->name,
                'customer' => array_filter(['name' => $owner?->name ?? $gym->name, 'email' => $owner?->email ?? $gym->email, 'contact' => $gym->phone]),
                'notify' => ['email' => true, 'sms' => false],
                'notes' => ['type' => 'credits', 'gym_id' => (string) $gym->id, 'credits' => (string) $credits],
                'callback_url' => route('filament.gym.pages.message-credits', ['tenant' => $gym]),
                'callback_method' => 'get',
            ])
            ->throw()
            ->json();

        return $response['short_url'];
    }

    public function verifyWebhookSignature(string $payload, ?string $signature): bool
    {
        $secret = config('gym.platform_razorpay.webhook_secret');

        return filled($secret) && filled($signature) && hash_equals(hash_hmac('sha256', $payload, $secret), $signature);
    }

    /** Adds purchased credits once the payment link is paid. Safe to receive twice. */
    public function handleWebhook(array $event): void
    {
        if (($event['event'] ?? null) !== 'payment_link.paid') {
            return;
        }

        $payment = $event['payload']['payment']['entity'] ?? [];
        $notes = $event['payload']['payment_link']['entity']['notes'] ?? [];

        if (($notes['type'] ?? null) !== 'credits' || blank($payment['id'] ?? null)) {
            return;
        }

        $gym = Gym::query()->find($notes['gym_id'] ?? 0);
        $credits = (int) ($notes['credits'] ?? 0);

        if (! $gym || $credits <= 0 || SaasPayment::query()->where('gateway_payment_id', $payment['id'])->exists()) {
            return;
        }

        DB::transaction(function () use ($gym, $credits, $payment) {
            $saasPayment = SaasPayment::create([
                'gym_id' => $gym->id,
                'kind' => 'credits',
                'amount' => $payment['amount'] / 100,
                'reference' => $payment['id'],
                'gateway_payment_id' => $payment['id'],
                'paid_at' => now(),
            ]);

            app(Credits::class)->add($gym, $credits, 'purchase', number_format($credits).' credits bought online', ['saas_payment_id' => $saasPayment->id]);
        });
    }
}
