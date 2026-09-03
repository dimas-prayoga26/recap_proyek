<?php

namespace Tests\Feature;

use Tests\TestCase;

class LoginPageTest extends TestCase
{
    public function test_login_page_renders_berry_login_component(): void
    {
        $response = $this->get(route('login'));

        $response
            ->assertSee('Login | Pencatatan Proyek')
            ->assertSee('auth-main', false)
            ->assertSee('auth-wrapper v3', false)
            ->assertSee('Masuk dengan Google')
            ->assertSee('assets/berry/images/authentication/google-icon.svg', false)
            ->assertSee('assets/berry/css/style.css', false)
            ->assertSee('name="_token"', false)
            ->assertSee('type="password"', false)
            ->assertDontSee('pc-sidebar', false)
            ->assertDontSee('pc-header', false);
    }
}
