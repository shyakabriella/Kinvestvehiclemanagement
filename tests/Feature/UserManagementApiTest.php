<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserManagementApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function createUser(string $role = 'employee'): User
    {
        $user = User::factory()->create([
            'status' => 'active',
        ]);

        $user->assignRole($role);

        return $user;
    }

    private function loginAs(string $role): User
    {
        $user = $this->createUser($role);

        Sanctum::actingAs($user);

        return $user;
    }

    public function test_guest_cannot_list_users(): void
    {
        $this->getJson('/api/v1/users')
            ->assertUnauthorized();
    }

    public function test_system_admin_can_list_users(): void
    {
        $this->loginAs('system_admin');

        $this->createUser();

        $this->getJson('/api/v1/users')
            ->assertOk()
            ->assertJsonStructure([
                'data',
                'current_page',
                'total',
            ])
            ->assertJsonPath('total', 2);
    }

    public function test_system_admin_can_search_users(): void
    {
        $this->loginAs('system_admin');

        $employee = $this->createUser();

        $employee->update([
            'name' => 'Jean Claude',
        ]);

        $this->getJson('/api/v1/users?search=Jean')
            ->assertOk()
            ->assertJsonPath('total', 1);
    }

    public function test_system_admin_can_update_user(): void
    {
        $this->loginAs('system_admin');

        $employee = $this->createUser();

        $this->patchJson(
            "/api/v1/users/{$employee->id}",
            ['name' => 'Updated Employee']
        )
            ->assertOk()
            ->assertJsonPath(
                'user.name',
                'Updated Employee'
            );

        $this->assertDatabaseHas('users', [
            'id' => $employee->id,
            'name' => 'Updated Employee',
        ]);
    }

    public function test_system_admin_can_suspend_user(): void
    {
        $this->loginAs('system_admin');

        $employee = $this->createUser();

        $employee->createToken('existing-token');

        $this->patchJson(
            "/api/v1/users/{$employee->id}/status",
            ['status' => 'suspended']
        )
            ->assertOk()
            ->assertJsonPath('user.status', 'suspended');

        $this->assertDatabaseHas('users', [
            'id' => $employee->id,
            'status' => 'suspended',
        ]);

        $this->assertDatabaseCount(
            'personal_access_tokens',
            0
        );
    }

    public function test_system_admin_can_reactivate_user(): void
    {
        $this->loginAs('system_admin');

        $employee = $this->createUser();

        $employee->update([
            'status' => 'suspended',
        ]);

        $this->patchJson(
            "/api/v1/users/{$employee->id}/status",
            ['status' => 'active']
        )
            ->assertOk()
            ->assertJsonPath('user.status', 'active');
    }

    public function test_system_admin_can_assign_roles(): void
    {
        $this->loginAs('system_admin');

        $employee = $this->createUser();

        $this->putJson(
            "/api/v1/users/{$employee->id}/roles",
            ['roles' => ['fleet_admin']]
        )->assertOk();

        $this->assertTrue(
            $employee->fresh()->hasRole('fleet_admin')
        );

        $this->assertFalse(
            $employee->fresh()->hasRole('employee')
        );
    }

    public function test_employee_cannot_manage_users(): void
    {
        $this->loginAs('employee');

        $target = $this->createUser();

        $this->getJson('/api/v1/users')
            ->assertForbidden();

        $this->patchJson(
            "/api/v1/users/{$target->id}",
            ['name' => 'Unauthorized Change']
        )->assertForbidden();

        $this->patchJson(
            "/api/v1/users/{$target->id}/status",
            ['status' => 'suspended']
        )->assertForbidden();

        $this->putJson(
            "/api/v1/users/{$target->id}/roles",
            ['roles' => ['system_admin']]
        )->assertForbidden();
    }

    public function test_admin_cannot_suspend_own_account(): void
    {
        $admin = $this->loginAs('system_admin');

        $this->patchJson(
            "/api/v1/users/{$admin->id}/status",
            ['status' => 'suspended']
        )->assertForbidden();

        $this->assertEquals(
            'active',
            $admin->fresh()->status
        );
    }

    public function test_cannot_remove_last_system_admin(): void
    {
        $this->loginAs('system_admin');

        $secondAdmin = $this->createUser('system_admin');

        $this->putJson(
            "/api/v1/users/{$secondAdmin->id}/roles",
            ['roles' => ['employee']]
        )->assertOk();

        $this->assertTrue(
            $secondAdmin->fresh()->hasRole('employee')
        );

        // The remaining admin cannot remove their own role.
        $this->putJson(
            '/api/v1/users/' .
            auth()->id() .
            '/roles',
            ['roles' => ['employee']]
        )->assertForbidden();
    }
}
