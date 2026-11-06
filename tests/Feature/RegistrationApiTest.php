<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RegistrationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function makeUser(string $role): User
    {
        $user = User::factory()->create([
            'status' => 'active',
        ]);

        $user->assignRole($role);

        return $user;
    }

    private function registrationData(): array
    {
        return [
            'name' => 'Jean Claude',
            'email' => 'jean@example.com',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ];
    }

    public function test_guest_cannot_register_employee(): void
    {
        $this->postJson(
            '/api/v1/auth/register',
            $this->registrationData()
        )->assertUnauthorized();
    }

    public function test_system_admin_can_register_employee(): void
    {
        Sanctum::actingAs($this->makeUser('system_admin'));

        $this->postJson(
            '/api/v1/auth/register',
            $this->registrationData()
        )
            ->assertCreated()
            ->assertJsonPath('user.email', 'jean@example.com')
            ->assertJsonPath('user.status', 'active');

        $user = User::where('email', 'jean@example.com')->firstOrFail();

        $this->assertTrue($user->hasRole('employee'));
        $this->assertDatabaseCount('users', 2);
    }

    public function test_duplicate_email_is_rejected(): void
    {
        Sanctum::actingAs($this->makeUser('system_admin'));

        User::factory()->create([
            'email' => 'jean@example.com',
        ]);

        $this->postJson(
            '/api/v1/auth/register',
            $this->registrationData()
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_weak_password_is_rejected(): void
    {
        Sanctum::actingAs($this->makeUser('system_admin'));

        $data = $this->registrationData();

        $data['password'] = '12345';
        $data['password_confirmation'] = '12345';

        $this->postJson('/api/v1/auth/register', $data)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_employee_cannot_register_other_users(): void
    {
        Sanctum::actingAs($this->makeUser('employee'));

        $this->postJson(
            '/api/v1/auth/register',
            $this->registrationData()
        )->assertForbidden();

        $this->assertDatabaseMissing('users', [
            'email' => 'jean@example.com',
        ]);
    }

    public function test_system_admin_can_register_fleet_admin(): void
    {
        Sanctum::actingAs($this->makeUser('system_admin'));

        $data = $this->registrationData();
        $data['role'] = 'fleet_admin';

        $this->postJson('/api/v1/auth/register', $data)
            ->assertCreated();

        $user = User::where('email', 'jean@example.com')->firstOrFail();

        $this->assertTrue($user->hasRole('fleet_admin'));
    }

    public function test_employee_cannot_assign_system_admin_role(): void
    {
        Sanctum::actingAs($this->makeUser('employee'));

        $data = $this->registrationData();
        $data['role'] = 'system_admin';

        $this->postJson('/api/v1/auth/register', $data)
            ->assertForbidden();

        $this->assertDatabaseMissing('users', [
            'email' => 'jean@example.com',
        ]);
    }
}
