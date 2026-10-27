<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_sign_in_and_admin_routes_require_authentication(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/login')->assertOk();

        $admin = User::factory()->create(['email' => 'admin@example.test', 'password' => 'test-password']);
        $this->post('/login', ['email' => $admin->email, 'password' => 'test-password'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($admin);
        $this->get('/participants')->assertOk();
        $this->get('/games/create')->assertOk();
    }

    public function test_invalid_login_does_not_authenticate(): void
    {
        User::factory()->create(['email' => 'admin@example.test', 'password' => 'test-password']);
        $this->from('/login')->post('/login', ['email' => 'admin@example.test', 'password' => 'incorrect'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
