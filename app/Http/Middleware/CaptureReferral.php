<?php

namespace App\Http\Middleware;

use App\Models\Partner;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/** Remembers a partner's ?ref=CODE for 60 days so the gym they refer is credited to them. */
class CaptureReferral
{
    public const COOKIE = 'gym_ref';

    public function handle(Request $request, Closure $next): Response
    {
        $code = strtoupper(trim((string) $request->query('ref')));

        if ($code !== '' && preg_match('/^[A-Z0-9]{3,20}$/', $code) && Partner::query()->where('code', $code)->where('is_active', true)->exists()) {
            Cookie::queue(self::COOKIE, $code, 60 * 24 * 60);
        }

        return $next($request);
    }

    public static function partnerId(Request $request): ?int
    {
        $code = $request->cookie(self::COOKIE);

        return $code ? Partner::query()->where('code', $code)->where('is_active', true)->value('id') : null;
    }
}
