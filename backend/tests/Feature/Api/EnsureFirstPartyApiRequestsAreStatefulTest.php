<?php

namespace Tests\Feature\Api;

use App\Http\Middleware\EnsureFirstPartyApiRequestsAreStateful;
use Illuminate\Http\Request;
use Tests\TestCase;

class EnsureFirstPartyApiRequestsAreStatefulTest extends TestCase
{
    public function test_request_with_configured_host_and_no_origin_is_stateful(): void
    {
        config()->set('sanctum.stateful', ['localhost:8080']);

        $request = Request::create(
            '/api/v1/stats',
            'GET',
            server: [
                'HTTP_HOST' => 'localhost:8080',
            ],
        );

        $this->assertTrue(EnsureFirstPartyApiRequestsAreStateful::fromFrontend($request));
    }

    public function test_existing_cross_origin_header_is_not_overridden_by_host_fallback(): void
    {
        config()->set('sanctum.stateful', ['localhost:8080']);

        $request = Request::create(
            '/api/v1/stats',
            'GET',
            server: [
                'HTTP_HOST' => 'localhost:8080',
                'HTTP_ORIGIN' => 'https://evil.example',
            ],
        );

        $this->assertFalse(EnsureFirstPartyApiRequestsAreStateful::fromFrontend($request));
    }

    public function test_same_host_referer_can_use_host_fallback_when_parent_sanctum_check_fails(): void
    {
        config()->set('sanctum.stateful', ['localhost']);

        $request = Request::create(
            '/api/v1/stats',
            'GET',
            server: [
                'HTTP_HOST' => 'localhost:8080',
                'HTTP_REFERER' => 'http://localhost:8080/admin/dashboard',
            ],
        );

        $this->assertTrue(EnsureFirstPartyApiRequestsAreStateful::fromFrontend($request));
    }

    public function test_cross_host_referer_without_valid_origin_is_not_stateful(): void
    {
        config()->set('sanctum.stateful', ['localhost:8080']);

        $request = Request::create(
            '/api/v1/stats',
            'GET',
            server: [
                'HTTP_HOST' => 'localhost:8080',
                'HTTP_REFERER' => 'https://evil.example/dashboard',
            ],
        );

        $this->assertFalse(EnsureFirstPartyApiRequestsAreStateful::fromFrontend($request));
    }

    public function test_unconfigured_host_without_origin_is_not_stateful(): void
    {
        config()->set('sanctum.stateful', ['localhost:8080']);

        $request = Request::create(
            '/api/v1/stats',
            'GET',
            server: [
                'HTTP_HOST' => 'example.test',
            ],
        );

        $this->assertFalse(EnsureFirstPartyApiRequestsAreStateful::fromFrontend($request));
    }
}
