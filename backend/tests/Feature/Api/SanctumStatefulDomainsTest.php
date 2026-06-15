<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class SanctumStatefulDomainsTest extends TestCase
{
    public function test_stateful_domains_include_local_vue_and_angular_dev_origins(): void
    {
        $domains = config('sanctum.stateful');

        $this->assertIsArray($domains);
        $this->assertContains('localhost:5173', $domains);
        $this->assertContains('localhost:4200', $domains);
        $this->assertContains('127.0.0.1:5173', $domains);
        $this->assertContains('127.0.0.1:4200', $domains);
    }
}
