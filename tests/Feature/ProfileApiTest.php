<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function login(): User
    {
        $user = User::factory()->create([
            'status' => 'active',
            'password' => \Illuminate\Support\Facades\Hash::make(
                'ProfilePassword123!'
            ),
        ]);

        $user->assignRole('employee');

        Sanctum::actingAs($user);

        return $user;
    }

    public function test_guest_cannot_update_profile(): void
    {
        $this->patchJson('/api/v1/auth/me', [
            'name' => 'New Name',
        ])->assertUnauthorized();
    }

    public function test_employee_can_update_own_name(): void
    {
        $user = $this->login();

        $this->patchJson('/api/v1/auth/me', [
            'name' => 'Jean Claude',
        ])
            ->assertOk()
            ->assertJsonPath('user.name', 'Jean Claude');

        $this->assertEquals(
            'Jean Claude',
            $user->fresh()->name
        );
    }

    public function test_employee_can_update_email(): void
    {
        $user = $this->login();

        $this->patchJson('/api/v1/auth/me', [
            'email' => 'newemail@example.com',
            'current_password' => 'ProfilePassword123!',
        ])
            ->assertOk()
            ->assertJsonPath(
                'user.email',
                'newemail@example.com'
            );

        $this->assertEquals(
            'newemail@example.com',
            $user->fresh()->email
        );
    }

    public function test_duplicate_email_is_rejected(): void
    {
        $this->login();

        $other = User::factory()->create();

        $this->patchJson('/api/v1/auth/me', [
            'email' => $other->email,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_employee_cannot_change_status_or_role(): void
    {
        $user = $this->login();

        $this->patchJson('/api/v1/auth/me', [
            'name' => 'Updated Name',
            'status' => 'suspended',
            'roles' => ['system_admin'],
        ])->assertOk();

        $user->refresh();

        $this->assertEquals('active', $user->status);
        $this->assertTrue($user->hasRole('employee'));
        $this->assertFalse(
            $user->hasRole('system_admin')
        );
    }

    public function test_employee_cannot_modify_another_user(): void
    {
        $this->login();

        $other = User::factory()->create();

        $this->patchJson(
            "/api/v1/users/{$other->id}",
            ['name' => 'Unauthorized']
        )->assertForbidden();
    }

    public function test_empty_profile_update_is_rejected(): void
    {
        $this->login();

        $this->patchJson(
            '/api/v1/auth/me',
            []
        )->assertUnprocessable();
    }
}
