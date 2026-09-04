<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected bool $authenticateRequests = true;

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->shouldAuthenticateRequests()) {
            $this->actingAs($this->authenticatedTestUser());
        }
    }

    private function shouldAuthenticateRequests(): bool
    {
        return str_starts_with(static::class, 'Tests\\Feature\\') && $this->authenticateRequests;
    }

    private function authenticatedTestUser(): User
    {
        return User::firstOrCreate(
            ['email' => 'superadmin@gmail.com'],
            [
                'name' => 'Super Admin',
                'password' => 'password',
            ],
        );
    }
}
