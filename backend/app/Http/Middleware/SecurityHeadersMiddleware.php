<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeadersMiddleware
{
    /**
     * Apply baseline response security headers.
     *
     * Header policy remains config-driven so local debugging can stay flexible while
     * production defaults remain strict.
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (! (bool) config('security.headers.enabled', true)) {
            return $response;
        }

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', (string) config('security.headers.referrer_policy', 'strict-origin-when-cross-origin'));
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');
        $response->headers->set('Permissions-Policy', (string) config('security.headers.permissions_policy', 'camera=(), microphone=(), geolocation=()'));

        $this->applyContentSecurityPolicy($response);
        $this->applyHsts($request, $response);

        return $response;
    }

    private function applyContentSecurityPolicy(Response $response): void
    {
        if (! (bool) config('security.headers.content_security_policy.enabled', true)) {
            return;
        }

        $policy = trim((string) config('security.headers.content_security_policy.value', ''));
        if ($policy === '') {
            return;
        }

        if ($this->shouldAllowDevViteOrigins()) {
            $policy = $this->withDevViteOrigins($policy);
        }

        $header = (bool) config('security.headers.content_security_policy.report_only', false)
            ? 'Content-Security-Policy-Report-Only'
            : 'Content-Security-Policy';

        $response->headers->set($header, $policy);
    }

    private function shouldAllowDevViteOrigins(): bool
    {
        return config('app.env') !== 'production'
            && (bool) config('security.headers.content_security_policy.dev_vite.enabled', false);
    }

    private function withDevViteOrigins(string $policy): string
    {
        $httpOrigins = $this->configuredSources('security.headers.content_security_policy.dev_vite.http_origins');
        $wsOrigins = $this->configuredSources('security.headers.content_security_policy.dev_vite.ws_origins');

        if ($httpOrigins === [] && $wsOrigins === []) {
            return $policy;
        }

        $directives = $this->parseCspDirectives($policy);

        // WHY: Blade pages load Vite over HTTP/WebSocket in local Docker only; production must use built assets.
        $directives = $this->appendCspSources($directives, 'script-src', $httpOrigins);
        $directives = $this->appendCspSources($directives, 'style-src', $httpOrigins);
        $directives = $this->appendCspSources($directives, 'connect-src', [...$httpOrigins, ...$wsOrigins]);

        return $this->buildCspPolicy($directives);
    }

    /**
     * @return array<int, string>
     */
    private function configuredSources(string $key): array
    {
        $sources = config($key, []);

        if (is_string($sources)) {
            $sources = array_filter(array_map('trim', explode(',', $sources)));
        }

        if (! is_array($sources)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $source): string => trim((string) $source),
            $sources
        ))));
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function parseCspDirectives(string $policy): array
    {
        $directives = [];

        foreach (array_filter(array_map('trim', explode(';', $policy))) as $directive) {
            $parts = preg_split('/\s+/', $directive) ?: [];
            $name = array_shift($parts);

            if (is_string($name) && $name !== '') {
                $directives[$name] = array_values(array_filter($parts));
            }
        }

        return $directives;
    }

    /**
     * @param array<string, array<int, string>> $directives
     * @param array<int, string> $sources
     * @return array<string, array<int, string>>
     */
    private function appendCspSources(array $directives, string $directive, array $sources): array
    {
        if ($sources === []) {
            return $directives;
        }

        $directives[$directive] = array_values(array_unique([
            ...($directives[$directive] ?? []),
            ...$sources,
        ]));

        return $directives;
    }

    /**
     * @param array<string, array<int, string>> $directives
     */
    private function buildCspPolicy(array $directives): string
    {
        return collect($directives)
            ->map(static fn (array $sources, string $directive): string => trim($directive.' '.implode(' ', $sources)))
            ->implode('; ').';';
    }

    private function applyHsts(Request $request, Response $response): void
    {
        // HSTS is added only for secure requests to avoid unsafe preload/redirect assumptions in local HTTP flows.
        if (! (bool) config('security.headers.hsts.enabled', false)) {
            return;
        }

        $forwardedProto = mb_strtolower((string) $request->headers->get('X-Forwarded-Proto', ''));
        $isSecure = $request->isSecure() || $forwardedProto === 'https';
        if (! $isSecure) {
            return;
        }

        $maxAge = max(0, (int) config('security.headers.hsts.max_age', 31536000));
        $parts = ["max-age={$maxAge}"];

        if ((bool) config('security.headers.hsts.include_subdomains', true)) {
            $parts[] = 'includeSubDomains';
        }

        if ((bool) config('security.headers.hsts.preload', false)) {
            $parts[] = 'preload';
        }

        $response->headers->set('Strict-Transport-Security', implode('; ', $parts));
    }
}
