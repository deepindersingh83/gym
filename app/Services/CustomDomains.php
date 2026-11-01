<?php

namespace App\Services;

use App\Models\Gym;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Gym-owned domains (e.g. members.fitzone.in). With Cloudflare for SaaS set up,
 * each domain is registered as a custom hostname and Cloudflare issues its SSL
 * certificate; otherwise the domain counts as live once its DNS points here.
 */
class CustomDomains
{
    /** Gym whose live custom domain is serving this request, if any. */
    public static function gymForHost(?string $host): ?Gym
    {
        $host = static::normalize((string) $host);

        if ($host === '' || $host === static::platformHost()) {
            return null;
        }

        $id = Cache::remember("custom-domain:{$host}", 300, fn () => Gym::query()
            ->where('custom_domain', $host)
            ->where('custom_domain_status', 'active')
            ->value('id') ?? 0);

        $gym = $id ? Gym::query()->find($id) : null;

        return $gym?->hasModule('custom_domain') ? $gym : null;
    }

    public static function current(): ?Gym
    {
        return app()->runningInConsole() && ! app()->runningUnitTests()
            ? null
            : static::gymForHost(request()->getHost());
    }

    public static function normalize(string $domain): string
    {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('#^https?://#', '', $domain);

        return rtrim(explode('/', $domain)[0], '.');
    }

    public static function platformHost(): string
    {
        return strtolower((string) parse_url(config('app.url'), PHP_URL_HOST));
    }

    /** Where gyms point their CNAME. */
    public static function target(): string
    {
        return config('gym.custom_domains.cname_target') ?: static::platformHost();
    }

    public static function usesCloudflare(): bool
    {
        return filled(config('gym.custom_domains.cloudflare_zone_id')) && filled(config('gym.custom_domains.cloudflare_api_token'));
    }

    public static function isValid(string $domain): bool
    {
        $platform = static::platformHost();

        return (bool) preg_match('/^(?=.{4,253}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $domain)
            && $domain !== $platform
            && ! str_ends_with($domain, '.'.$platform);
    }

    /** Saves a new domain for the gym and starts activation. */
    public function assign(Gym $gym, ?string $domain): void
    {
        $domain = $domain ? static::normalize($domain) : null;
        $old = $gym->custom_domain;

        if ($old === $domain) {
            return;
        }

        if ($gym->cloudflare_hostname_id && static::usesCloudflare()) {
            rescue(fn () => $this->cloudflare()->delete("custom_hostnames/{$gym->cloudflare_hostname_id}"), report: false);
        }

        $gym->forceFill([
            'custom_domain' => $domain,
            'custom_domain_status' => $domain ? 'pending' : null,
            'cloudflare_hostname_id' => null,
            'custom_domain_verified_at' => null,
        ])->save();

        static::forget($old);

        if ($domain) {
            $this->refresh($gym);
        }
    }

    /** Re-checks a pending domain; returns a short status message for the owner. */
    public function refresh(Gym $gym): string
    {
        if (blank($gym->custom_domain)) {
            return 'No custom domain set.';
        }

        try {
            $live = static::usesCloudflare() ? $this->refreshViaCloudflare($gym) : $this->pointsHere($gym->custom_domain);
        } catch (Throwable $e) {
            report($e);
            $live = false;
            $gym->forceFill(['custom_domain_status' => 'pending'])->save();

            return 'Could not check the domain right now: '.$e->getMessage();
        }

        $gym->forceFill([
            'custom_domain_status' => $live ? 'active' : 'pending',
            'custom_domain_verified_at' => $live ? ($gym->custom_domain_verified_at ?? now()) : null,
        ])->save();
        static::forget($gym->custom_domain);

        return $live
            ? "{$gym->custom_domain} is live with SSL."
            : 'Waiting for DNS: add a CNAME record for '.$gym->custom_domain.' pointing to '.static::target().'. This can take up to a few hours.';
    }

    protected function refreshViaCloudflare(Gym $gym): bool
    {
        $client = $this->cloudflare();

        if (! $gym->cloudflare_hostname_id) {
            $created = $client->post('custom_hostnames', [
                'hostname' => $gym->custom_domain,
                'ssl' => ['method' => 'http', 'type' => 'dv', 'settings' => ['min_tls_version' => '1.2']],
            ])->json();

            $id = $created['result']['id'] ?? null;

            if (! $id) {
                $message = $created['errors'][0]['message'] ?? 'Cloudflare did not accept the domain.';
                throw new RuntimeException($message);
            }

            $gym->forceFill(['cloudflare_hostname_id' => $id])->save();
        }

        $result = $client->get("custom_hostnames/{$gym->cloudflare_hostname_id}")->throw()->json('result');

        return ($result['status'] ?? null) === 'active' && ($result['ssl']['status'] ?? null) === 'active';
    }

    /** True when the domain's CNAME (or A record) leads to this platform. */
    public function pointsHere(string $domain): bool
    {
        $target = static::target();
        $cnames = collect(@dns_get_record($domain, DNS_CNAME) ?: [])->pluck('target')->map(fn ($t) => rtrim(strtolower($t), '.'));

        if ($cnames->contains($target)) {
            return true;
        }

        $ours = collect(@dns_get_record($target, DNS_A) ?: [])->pluck('ip');
        $theirs = collect(@dns_get_record($domain, DNS_A) ?: [])->pluck('ip');

        return $ours->isNotEmpty() && $theirs->intersect($ours)->isNotEmpty();
    }

    public static function forget(?string $domain): void
    {
        if ($domain) {
            Cache::forget('custom-domain:'.static::normalize($domain));
        }
    }

    protected function cloudflare(): PendingRequest
    {
        return Http::baseUrl('https://api.cloudflare.com/client/v4/zones/'.config('gym.custom_domains.cloudflare_zone_id').'/')
            ->withToken(config('gym.custom_domains.cloudflare_api_token'))
            ->acceptJson()
            ->timeout(20);
    }
}
