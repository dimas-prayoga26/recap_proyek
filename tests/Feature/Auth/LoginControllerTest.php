<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class LoginControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected bool $authenticateRequests = false;

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::updateOrCreate(
            ['email' => 'superadmin@gmail.com'],
            [
                'name' => 'Super Admin',
                'password' => 'password',
            ],
        );

        $response = $this->post(route('login.store'), [
            'email' => 'superadmin@gmail.com',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_forgets_cached_exchange_rate_so_it_refetches(): void
    {
        Cache::put('exchange-rate.usd-idr', 12345, 60);
        User::updateOrCreate(
            ['email' => 'superadmin@gmail.com'],
            [
                'name' => 'Super Admin',
                'password' => 'password',
            ],
        );

        $this->post(route('login.store'), [
            'email' => 'superadmin@gmail.com',
            'password' => 'password',
        ]);

        $this->assertNull(Cache::get('exchange-rate.usd-idr'));
    }

    public function test_user_cannot_login_with_invalid_password(): void
    {
        User::updateOrCreate(
            ['email' => 'superadmin@gmail.com'],
            [
                'name' => 'Super Admin',
                'password' => 'password',
            ],
        );

        $response = $this->post(route('login.store'), [
            'email' => 'superadmin@gmail.com',
            'password' => 'salah',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
