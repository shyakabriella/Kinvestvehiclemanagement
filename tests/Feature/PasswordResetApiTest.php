<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetApiTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $status = 'active'): User
    {
        return User::factory()->create([
            'email' => 'employee@example.com',
            'password' => Hash::make('OriginalPassword123!'),
            'status' => $status,
        ]);
    }

    private function resetData(string $token): array
    {
        return [
            'email' => 'employee@example.com',
            'token' => $token,
            'password' => 'NewSecurePassword456!',
            'password_confirmation' => 'NewSecurePassword456!',
        ];
    }

    public function test_existing_user_can_request_reset(): void
    {
        Notification::fake();

        $user = $this->makeUser();

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => $user->email,
        ])->assertOk();

        Notification::assertSentTo(
            $user,
            ResetPassword::class
        );
    }

    public function test_unknown_email_gets_generic_response(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'unknown@example.com',
        ])
            ->assertOk()
            ->assertJsonPath(
                'message',
                'If the email belongs to an account, a password reset link will be sent.'
            );

        Notification::assertNothingSent();
    }

    public function test_valid_token_resets_password(): void
    {
        $user = $this->makeUser();

        $token = Password::createToken($user);

        $this->postJson(
            '/api/v1/auth/reset-password',
            $this->resetData($token)
        )
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Password reset successfully. Please log in again.'
            );

        $this->assertTrue(
            Hash::check(
                'NewSecurePassword456!',
                $user->fresh()->password
            )
        );

        $this->assertFalse(
            Password::tokenExists($user, $token)
        );
    }

    public function test_invalid_token_is_rejected(): void
    {
        $user = $this->makeUser();

        $this->postJson(
            '/api/v1/auth/reset-password',
            $this->resetData('invalid-token')
        )->assertUnprocessable();

        $this->assertTrue(
            Hash::check(
                'OriginalPassword123!',
                $user->fresh()->password
            )
        );
    }

    public function test_expired_token_is_rejected(): void
    {
        $user = $this->makeUser();

        $token = Password::createToken($user);

        $this->travel(61)->minutes();

        try {
            $this->postJson(
                '/api/v1/auth/reset-password',
                $this->resetData($token)
            )->assertUnprocessable();
        } finally {
            $this->travelBack();
        }

        $this->assertTrue(
            Hash::check(
                'OriginalPassword123!',
                $user->fresh()->password
            )
        );
    }

    public function test_suspended_user_cannot_reset_password(): void
    {
        $user = $this->makeUser();

        $token = Password::createToken($user);

        $user->update(['status' => 'suspended']);

        $this->postJson(
            '/api/v1/auth/reset-password',
            $this->resetData($token)
        )->assertUnprocessable();

        $this->assertTrue(
            Hash::check(
                'OriginalPassword123!',
                $user->fresh()->password
            )
        );
    }

    public function test_reset_revokes_existing_tokens(): void
    {
        $user = $this->makeUser();

        $apiToken = $user->createToken(
            'old-device'
        )->plainTextToken;

        $resetToken = Password::createToken($user);

        $this->postJson(
            '/api/v1/auth/reset-password',
            $this->resetData($resetToken)
        )->assertOk();

        $this->assertDatabaseCount(
            'personal_access_tokens',
            0
        );

        auth()->forgetGuards();

        $this->withToken($apiToken)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    public function test_weak_password_is_rejected(): void
    {
        $user = $this->makeUser();

        $token = Password::createToken($user);

        $data = $this->resetData($token);
        $data['password'] = 'short';
        $data['password_confirmation'] = 'short';

        $this->postJson(
            '/api/v1/auth/reset-password',
            $data
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);

        $this->assertTrue(
            Hash::check(
                'OriginalPassword123!',
                $user->fresh()->password
            )
        );
    }
}
