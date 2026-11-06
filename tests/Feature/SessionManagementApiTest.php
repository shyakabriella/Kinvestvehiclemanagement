<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionManagementApiTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create([
            'status' => 'active',
        ]);
    }

    public function test_guest_cannot_view_sessions(): void
    {
        $this->getJson('/api/v1/auth/sessions')
            ->assertUnauthorized();
    }

    public function test_user_can_view_own_sessions(): void
    {
        $user = $this->makeUser();

        $token = $user->createToken(
            'laptop',
            ['*'],
            now()->addHours(12)
        )->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/auth/sessions')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('sessions.0.name', 'laptop')
            ->assertJsonPath('sessions.0.is_current', true)
            ->assertJsonMissingPath('sessions.0.token');
    }

    public function test_user_can_see_multiple_sessions(): void
    {
        $user = $this->makeUser();

        $token = $user->createToken('laptop')->plainTextToken;
        $user->createToken('phone');

        $this->withToken($token)
            ->getJson('/api/v1/auth/sessions')
            ->assertOk()
            ->assertJsonPath('total', 2);
    }

    public function test_expired_sessions_are_not_listed(): void
    {
        $user = $this->makeUser();

        $token = $user->createToken('active-device')
            ->plainTextToken;

        $user->createToken(
            'expired-device',
            ['*'],
            now()->subMinute()
        );

        $this->withToken($token)
            ->getJson('/api/v1/auth/sessions')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath(
                'sessions.0.name',
                'active-device'
            );
    }

    public function test_user_can_revoke_own_other_session(): void
    {
        $user = $this->makeUser();

        $currentToken = $user->createToken('laptop')
            ->plainTextToken;

        $other = $user->createToken('phone');

        $this->withToken($currentToken)
            ->deleteJson(
                "/api/v1/auth/sessions/{$other->accessToken->id}"
            )
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Session revoked successfully.'
            );

        $this->assertDatabaseCount(
            'personal_access_tokens',
            1
        );
    }

    public function test_user_cannot_revoke_another_users_session(): void
    {
        $user = $this->makeUser();
        $otherUser = $this->makeUser();

        $token = $user->createToken('my-device')
            ->plainTextToken;

        $foreign = $otherUser->createToken('foreign-device');

        $this->withToken($token)
            ->deleteJson(
                "/api/v1/auth/sessions/{$foreign->accessToken->id}"
            )
            ->assertNotFound();

        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $foreign->accessToken->id,
        ]);
    }

    public function test_revoked_session_cannot_authenticate(): void
    {
        $user = $this->makeUser();

        $token = $user->createToken('laptop')
            ->plainTextToken;

        $current = $user->tokens()->firstOrFail();

        $this->withToken($token)
            ->deleteJson(
                "/api/v1/auth/sessions/{$current->id}"
            )
            ->assertOk();

        $this->assertDatabaseCount(
            'personal_access_tokens',
            0
        );

        // Clear the test application's authentication state.
        auth()->forgetGuards();

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    public function test_guest_cannot_revoke_session(): void
    {
        $this->deleteJson('/api/v1/auth/sessions/1')
            ->assertUnauthorized();
    }
}
