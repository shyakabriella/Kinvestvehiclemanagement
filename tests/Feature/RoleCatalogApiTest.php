<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleCatalogApiTest extends TestCase
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

    public function test_guest_cannot_view_role_catalog(): void
    {
        $this->getJson('/api/v1/roles')
            ->assertUnauthorized();
    }

    public function test_employee_cannot_view_role_catalog(): void
    {
        Sanctum::actingAs($this->makeUser('employee'));

        $this->getJson('/api/v1/roles')
            ->assertForbidden();
    }

    public function test_fleet_admin_cannot_view_role_catalog(): void
    {
        Sanctum::actingAs($this->makeUser('fleet_admin'));

        $this->getJson('/api/v1/roles')
            ->assertForbidden();
    }

    public function test_management_cannot_view_role_catalog(): void
    {
        Sanctum::actingAs($this->makeUser('management'));

        $this->getJson('/api/v1/roles')
            ->assertForbidden();
    }

    public function test_system_admin_can_view_all_roles(): void
    {
        Sanctum::actingAs($this->makeUser('system_admin'));

        $this->getJson('/api/v1/roles')
            ->assertOk()
            ->assertJsonPath('total', 4)
            ->assertJsonCount(4, 'roles')
            ->assertJsonStructure([
                'roles' => [
                    '*' => [
                        'id',
                        'name',
                        'users_count',
                        'permissions_count',
                        'permissions',
                    ],
                ],
                'total',
            ]);
    }

    public function test_role_counts_and_permissions_are_correct(): void
    {
        $admin = $this->makeUser('system_admin');

        $this->makeUser('employee');
        $this->makeUser('employee');
        $this->makeUser('fleet_admin');

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/v1/roles')
            ->assertOk();

        $roles = collect($response->json('roles'))
            ->keyBy('name');

        $this->assertSame(4, $roles->count());

        $this->assertSame(
            1,
            $roles['system_admin']['users_count']
        );

        $this->assertSame(
            2,
            $roles['employee']['users_count']
        );

        $this->assertSame(
            1,
            $roles['fleet_admin']['users_count']
        );

        $this->assertSame(
            0,
            $roles['management']['users_count']
        );

        $this->assertContains(
            'users.assign_roles',
            $roles['system_admin']['permissions']
        );

        $this->assertSame(
            Role::findByName('system_admin', 'web')
                ->permissions()
                ->count(),
            $roles['system_admin']['permissions_count']
        );
    }
}
