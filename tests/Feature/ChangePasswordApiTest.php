<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ChangePasswordApiTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(): User
    {
        return User::factory()->create([
            'password' => Hash::make('OriginalPassword123!'),
            'status' => 'active',
        ]);
    }

    private function passwordData(): array
    {
        return [
            'current_password' => 'OriginalPassword123!',
            'password' => 'NewSecurePassword456!',
            'password_confirmation' => 'NewSecurePassword456!',
        ];
    }

    public function test_guest_cannot_change_password(): void
    {
        $this->postJson(
            '/api/v1/auth/change-password',
            $this->passwordData()
        )->assertUnauthorized();
    }

    public function test_user_can_change_password(): void
    {
        $user = $this->createUser();

        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson(
                '/api/v1/auth/change-password',
                $this->passwordData()
            )
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Password changed successfully.'
            );

        $this->assertTrue(
            Hash::check(
                'NewSecurePassword456!',
                $user->fresh()->password
            )
        );

        $this->assertFalse(
            Hash::check(
                'OriginalPassword123!',
                $user->fresh()->password
            )
        );
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        $user = $this->createUser();

        $token = $user->createToken('test')->plainTextToken;

        $data = $this->passwordData();
        $data['current_password'] = 'WrongPassword123!';

        $this->withToken($token)
            ->postJson('/api/v1/auth/change-password', $data)
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'current_password',
            ]);

        $this->assertTrue(
            Hash::check(
                'OriginalPassword123!',
                $user->fresh()->password
            )
        );
    }

    public function test_weak_password_is_rejected(): void
    {
        $user = $this->createUser();

        $token = $user->createToken('test')->plainTextToken;

        $data = $this->passwordData();
        $data['password'] = 'short';
        $data['password_confirmation'] = 'short';

        $this->withToken($token)
            ->postJson('/api/v1/auth/change-password', $data)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_current_password_cannot_be_reused(): void
    {
        $user = $this->createUser();

        $token = $user->createToken('test')->plainTextToken;

        $data = [
            'current_password' => 'OriginalPassword123!',
            'password' => 'OriginalPassword123!',
            'password_confirmation' => 'OriginalPassword123!',
        ];

        $this->withToken($token)
            ->postJson('/api/v1/auth/change-password', $data)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_password_change_revokes_other_tokens(): void
    {
        $user = $this->createUser();

        $currentToken = $user->createToken(
            'current-device'
        )->plainTextToken;

        $otherToken = $user->createToken(
            'other-device'
        )->plainTextToken;

        $this->assertDatabaseCount(
            'personal_access_tokens',
            2
        );

        $this->withToken($currentToken)
            ->postJson(
                '/api/v1/auth/change-password',
                $this->passwordData()
            )
            ->assertOk();

        $this->assertDatabaseCount(
            'personal_access_tokens',
            1
        );

        auth()->forgetGuards();

        $this->withToken($otherToken)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();

        auth()->forgetGuards();

        $this->withToken($currentToken)
            ->getJson('/api/v1/auth/me')
            ->assertOk();
    }
}
