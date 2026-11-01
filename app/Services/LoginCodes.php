<?php

namespace App\Services;

use App\Enums\MessageChannel;
use App\Enums\MessageEvent;
use App\Models\User;
use App\Services\Messaging\Messenger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * One-time login codes for the member app, sent by WhatsApp (or SMS when
 * WhatsApp fails). Codes are stored hashed, expire after 10 minutes, allow
 * 5 tries, and sending is rate limited per number and per IP address.
 */
class LoginCodes
{
    public const TTL_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    /**
     * Sends a code if the number belongs to a member. Returns false only when
     * the caller is rate limited; unknown numbers look the same as known ones.
     */
    public function send(string $phone, string $ip): bool
    {
        $number = User::normalizePhone($phone);

        if (! $number) {
            return true;
        }

        foreach (["login-code:phone:{$number}" => 3, "login-code:ip:{$ip}" => 15] as $key => $max) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                return false;
            }
        }

        RateLimiter::hit("login-code:phone:{$number}", 600);
        RateLimiter::hit("login-code:ip:{$ip}", 3600);

        $user = User::findByPhone($number);
        $member = $user?->memberProfiles()->whereNull('deleted_at')->with(['gym', 'user'])->latest('id')->first();

        if (! $member) {
            return true;
        }

        $code = (string) random_int(100000, 999999);
        Cache::put($this->key($number), ['user_id' => $user->id, 'hash' => Hash::make($code), 'attempts' => 0], now()->addMinutes(self::TTL_MINUTES));

        $messenger = app(Messenger::class);
        $sent = $messenger->send($member, MessageEvent::LoginCode, ['code' => $code], channels: [MessageChannel::WhatsApp], secret: ['code'])
            ?: $messenger->send($member, MessageEvent::LoginCode, ['code' => $code], channels: [MessageChannel::Sms], secret: ['code']);

        if (! $sent) {
            Log::warning('Member login code could not be sent', ['user_id' => $user->id]);
        }

        return true;
    }

    /** The user the code belongs to, or null if it is wrong, expired or used up. */
    public function verify(string $phone, string $code): ?User
    {
        $number = User::normalizePhone($phone);
        $entry = $number ? Cache::get($this->key($number)) : null;

        if (! $entry || $entry['attempts'] >= self::MAX_ATTEMPTS) {
            return null;
        }

        if (! Hash::check(preg_replace('/\D/', '', $code), $entry['hash'])) {
            $entry['attempts']++;
            Cache::put($this->key($number), $entry, now()->addMinutes(self::TTL_MINUTES));

            return null;
        }

        Cache::forget($this->key($number));

        return User::query()->find($entry['user_id']);
    }

    protected function key(string $number): string
    {
        return "login-code:{$number}";
    }
}
