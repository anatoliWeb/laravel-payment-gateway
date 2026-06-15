<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

class EnsureFirstPartyApiRequestsAreStateful extends EnsureFrontendRequestsAreStateful
{
    public static function fromFrontend($request): bool
    {
        if (parent::fromFrontend($request)) {
            return true;
        }

        if ((string) $request->headers->get('origin', '') !== '') {
            return false;
        }

        if ((string) $request->headers->get('referer', '') !== '' && ! static::hasSameHostReferer($request)) {
            return false;
        }

        return static::isConfiguredStatefulHost($request);
    }

    private static function hasSameHostReferer(Request $request): bool
    {
        $referer = (string) $request->headers->get('referer', '');
        $refererHost = parse_url($referer, PHP_URL_HOST);

        if (! is_string($refererHost) || $refererHost === '') {
            return false;
        }

        $refererPort = parse_url($referer, PHP_URL_PORT);
        $refererHttpHost = $refererPort ? "{$refererHost}:{$refererPort}" : $refererHost;

        return Str::lower($refererHttpHost) === Str::lower($request->getHttpHost());
    }

    private static function isConfiguredStatefulHost(Request $request): bool
    {
        $host = Str::lower($request->getHttpHost());

        foreach ((array) config('sanctum.stateful', []) as $statefulDomain) {
            $configured = Str::lower(trim((string) $statefulDomain));

            if ($configured === '') {
                continue;
            }

            if ($configured === $host) {
                return true;
            }

            if (! str_contains($configured, ':') && Str::before($host, ':') === $configured) {
                return true;
            }
        }

        return false;
    }
}
