<?php

namespace App\Services\Payments;

use Illuminate\Support\Facades\Http;

/** The platform's own Razorpay account, which gyms in India pay (plans and message credits). */
class PlatformBilling
{
    public function isConfigured(): bool
    {
        return filled(config('gym.platform_razorpay.key_id')) && filled(config('gym.platform_razorpay.key_secret'));
    }

    /** @return array<int, float> credits => price in the given currency (packs without a price there are left out) */
    public function packs(string $currency = 'INR'): array
    {
        return collect(config('gym.credits.packs'))
            ->filter(fn ($pack) => (float) ($pack[$currency] ?? 0) > 0 && (int) ($pack['credits'] ?? 0) > 0)
            ->mapWithKeys(fn ($pack) => [(int) $pack['credits'] => (float) $pack[$currency]])
            ->sortKeys()
            ->all();
    }

    public function post(string $path, array $payload = []): array
    {
        return Http::baseUrl('https://api.razorpay.com/v1/')
            ->withBasicAuth((string) config('gym.platform_razorpay.key_id'), (string) config('gym.platform_razorpay.key_secret'))
            ->acceptJson()
            ->timeout(20)
            ->post($path, $payload)
            ->throw()
            ->json();
    }

    public function verifyWebhookSignature(string $payload, ?string $signature): bool
    {
        $secret = config('gym.platform_razorpay.webhook_secret');

        return filled($secret) && filled($signature) && hash_equals(hash_hmac('sha256', $payload, $secret), $signature);
    }
}
