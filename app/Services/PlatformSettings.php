<?php

namespace App\Services;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Settings the platform owner manages in /admin instead of .env. Each key is a
 * config path; saved values override the config/.env default on every request.
 */
class PlatformSettings
{
    public const CACHE_KEY = 'platform-settings';

    /** Config path => true when the value is a secret (never shown back in the form). */
    public const KEYS = [
        'app.name' => false,
        'gym.trial_days' => false,
        'gym.default_saas_plan' => false,
        'gym.support.email' => false,
        'gym.support.phone' => false,

        'gym.billing.grace_days' => false,
        'gym.billing.reminder_days' => false,
        'gym.billing.trial_full_access' => false,
        'gym.billing.entities' => false,

        'gym.platform_razorpay.key_id' => false,
        'gym.platform_razorpay.key_secret' => true,
        'gym.platform_razorpay.webhook_secret' => true,
        'gym.stripe.secret_key' => true,
        'gym.stripe.webhook_secret' => true,

        'gym.whatsapp.token' => true,
        'gym.whatsapp.phone_number_id' => false,
        'gym.sms.msg91_auth_key' => true,
        'gym.sms.msg91_sender' => false,
        'gym.sms.msg91_flow_id' => false,
        'gym.credits.cost' => false,
        'gym.credits.welcome' => false,
        'gym.credits.low_balance' => false,
        'gym.credits.packs' => false,

        'mail.default' => false,
        'mail.mailers.smtp.host' => false,
        'mail.mailers.smtp.port' => false,
        'mail.mailers.smtp.username' => false,
        'mail.mailers.smtp.password' => true,
        'mail.mailers.smtp.scheme' => false,
        'mail.from.address' => false,
        'mail.from.name' => false,

        'gym.meta.app_secret' => true,
        'gym.meta.verify_token' => true,
        'gym.custom_domains.cname_target' => false,
        'gym.custom_domains.cloudflare_zone_id' => false,
        'gym.custom_domains.cloudflare_api_token' => true,
    ];

    /** Puts saved settings over the config defaults. Safe before the table exists (fresh install). */
    public static function apply(): void
    {
        try {
            $saved = Cache::rememberForever(self::CACHE_KEY, fn () => PlatformSetting::query()->pluck('value', 'key')->all());
        } catch (Throwable) {
            return;
        }

        foreach ($saved as $key => $value) {
            if (array_key_exists($key, self::KEYS) && $value !== null) {
                config([$key => $value]);
            }
        }
    }

    /** Current values for the settings form; secrets are left empty. @return array<string, mixed> */
    public static function formState(): array
    {
        $state = [];

        foreach (self::KEYS as $key => $secret) {
            data_set($state, $key, $secret ? null : config($key));
        }

        return $state;
    }

    public static function hasSecret(string $key): bool
    {
        return filled(config($key));
    }

    /** Saves the form; an empty secret keeps the stored one. @param array<string, mixed> $state */
    public static function save(array $state): void
    {
        foreach (self::KEYS as $key => $secret) {
            $value = data_get($state, $key);

            if ($secret && blank($value)) {
                continue;
            }

            PlatformSetting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }

        Cache::forget(self::CACHE_KEY);
        static::apply();
    }

    /** Removes a stored secret, falling back to .env. */
    public static function clear(string $key): void
    {
        PlatformSetting::query()->where('key', $key)->delete();
        Cache::forget(self::CACHE_KEY);
    }
}
