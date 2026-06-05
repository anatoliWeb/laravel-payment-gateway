<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminLoginCspTest extends TestCase
{
    public function test_admin_login_csp_allows_vite_dev_server_outside_production(): void
    {
        config()->set('app.env', 'local');
        config()->set('security.headers.enabled', true);
        config()->set('security.headers.content_security_policy.enabled', true);
        config()->set('security.headers.content_security_policy.report_only', false);
        config()->set('security.headers.content_security_policy.dev_vite.enabled', true);

        $response = $this->get('/admin/login')->assertOk();
        $csp = (string) $response->headers->get('Content-Security-Policy');

        $this->assertNotSame('', $csp);
        $this->assertDirectiveContains($csp, 'script-src', 'http://localhost:5173');
        $this->assertDirectiveContains($csp, 'style-src', 'http://localhost:5173');
        $this->assertDirectiveContains($csp, 'connect-src', 'http://localhost:5173');
        $this->assertDirectiveContains($csp, 'connect-src', 'ws://localhost:5173');
    }

    public function test_admin_login_csp_does_not_allow_vite_dev_server_in_production(): void
    {
        config()->set('app.env', 'production');
        config()->set('security.headers.enabled', true);
        config()->set('security.headers.content_security_policy.enabled', true);
        config()->set('security.headers.content_security_policy.report_only', false);
        config()->set('security.headers.content_security_policy.dev_vite.enabled', true);

        $response = $this->get('/admin/login')->assertOk();
        $csp = (string) $response->headers->get('Content-Security-Policy');

        $this->assertNotSame('', $csp);
        $this->assertStringNotContainsString('localhost:5173', $csp);
        $this->assertStringNotContainsString('127.0.0.1:5173', $csp);
    }

    private function assertDirectiveContains(string $policy, string $directive, string $source): void
    {
        $directives = collect(array_filter(array_map('trim', explode(';', $policy))))
            ->mapWithKeys(function (string $entry): array {
                $parts = preg_split('/\s+/', $entry) ?: [];
                $name = array_shift($parts);

                return is_string($name) ? [$name => $parts] : [];
            });

        $this->assertContains($source, $directives->get($directive, []));
    }
}
