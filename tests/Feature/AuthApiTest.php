<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(
        string $status = 'active'
    ): User {
        return User::factory()->create([
            'email' => 'employee@example.com',
            'password' => Hash::make('TestPassword123!'),
            'status' => $status,
        ]);
    }

    public function test_active_user_can_login(): void
    {
        $this->createUser();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'employee@example.com',
            'password' => 'TestPassword123!',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Login successful.')
            ->assertJsonPath('user.status', 'active')
            ->assertJsonStructure([
                'access_token',
                'token_type',
                'expires_in',
                'user' => [
                    'id',
                    'name',
                    'email',
                    'status',
                    'roles',
                    'permissions',
                ],
            ]);

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_invalid_password_is_rejected(): void
    {
        $this->createUser();

        $this->postJson('/api/v1/auth/login', [
            'email' => 'employee@example.com',
            'password' => 'WrongPassword123!',
        ])->assertUnprocessable();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_suspended_user_cannot_login(): void
    {
        $this->createUser('suspended');

        $this->postJson('/api/v1/auth/login', [
            'email' => 'employee@example.com',
            'password' => 'TestPassword123!',
        ])->assertForbidden();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_protected_routes_require_authentication(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertUnauthorized();

        $this->postJson('/api/v1/auth/logout')
            ->assertUnauthorized();
    }

    public function test_authenticated_user_can_view_profile(): void
    {
        $user = $this->createUser();

        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('user.email', $user->email);
    }

    public function test_suspended_user_cannot_use_existing_token(): void
    {
        $user = $this->createUser();

        $token = $user->createToken('test')->plainTextToken;

        $user->update(['status' => 'suspended']);

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertForbidden();
    }

    public function test_logout_revokes_current_token(): void
    {
        $user = $this->createUser();

        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logout successful.');

        $this->assertDatabaseCount('personal_access_tokens', 0);

        // Clear authentication state retained by the test application.
        // The next request must authenticate using the revoked token.
        auth()->forgetGuards();

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }
}
