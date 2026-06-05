<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminLoginPageTest extends TestCase
{
    public function test_admin_login_page_renders_server_side_login_form(): void
    {
        $response = $this->get('/admin/login');

        $response
            ->assertOk()
            ->assertSee('Login')
            ->assertSee('name="email"', false)
            ->assertSee('name="password"', false)
            ->assertDontSee('<div id="app"></div>', false);
    }
}
