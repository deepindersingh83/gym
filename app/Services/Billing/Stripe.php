<?php

namespace App\Services\Billing;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/** Minimal Stripe API client for billing gyms in Australia (Checkout, subscriptions, webhooks). */
class Stripe
{
    public function isConfigured(): bool
    {
        return filled(config('gym.stripe.secret_key'));
    }

    public function post(string $path, array $params = []): array
    {
        return $this->client()->post($path, $params)->throw()->json();
    }

    public function delete(string $path): array
    {
        return $this->client()->delete($path)->throw()->json();
    }

    /** Checks the Stripe-Signature header: t=timestamp,v1=HMAC-SHA256("t.payload"). */
    public function verifyWebhook(string $payload, ?string $header, int $tolerance = 300): bool
    {
        $secret = config('gym.stripe.webhook_secret');

        if (blank($secret) || blank($header)) {
            return false;
        }

        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);

            if ($key === 't') {
                $timestamp = (int) $value;
            } elseif ($key === 'v1' && $value) {
                $signatures[] = $value;
            }
        }

        if (! $timestamp || abs(time() - $timestamp) > $tolerance) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    protected function client(): PendingRequest
    {
        return Http::baseUrl('https://api.stripe.com/v1/')
            ->withToken((string) config('gym.stripe.secret_key'))
            ->asForm()
            ->acceptJson()
            ->timeout(20);
    }
}
