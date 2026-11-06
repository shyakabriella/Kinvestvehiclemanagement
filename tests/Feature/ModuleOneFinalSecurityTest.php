<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ModuleOneFinalSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create([
            'status' => 'active',
            'password' => Hash::make('SecurePassword123!'),
        ]);

        $user->assignRole($role);
        return $user;
    }

    public function test_email_change_requires_password(): void
    {
        $user = $this->user('employee');
        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/auth/me', [
            'email' => 'changed@example.com',
        ])->assertUnprocessable();

        $this->assertNotEquals(
            'changed@example.com',
            $user->fresh()->email
        );
    }

    public function test_email_change_creates_audit_record(): void
    {
        $user = $this->user('employee');
        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/auth/me', [
            'email' => 'changed@example.com',
            'current_password' => 'SecurePassword123!',
        ])->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->id,
            'event' => 'user.profile_updated',
            'subject_id' => $user->id,
        ]);
    }

    public function test_account_suspension_is_audited(): void
    {
        $admin = $this->user('system_admin');
        $employee = $this->user('employee');

        Sanctum::actingAs($admin);

        $this->patchJson(
            "/api/v1/users/{$employee->id}/status",
            ['status' => 'suspended']
        )->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'event' => 'user.status_changed',
            'subject_id' => $employee->id,
        ]);
    }

    public function test_role_assignment_is_audited(): void
    {
        $admin = $this->user('system_admin');
        $employee = $this->user('employee');

        Sanctum::actingAs($admin);

        $this->putJson(
            "/api/v1/users/{$employee->id}/roles",
            ['roles' => ['fleet_admin']]
        )->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'event' => 'user.roles_changed',
            'subject_id' => $employee->id,
        ]);
    }

    public function test_password_change_is_audited_without_password(): void
    {
        $user = $this->user('employee');
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/auth/change-password', [
            'current_password' => 'SecurePassword123!',
            'password' => 'NewSecurePassword456!',
            'password_confirmation' => 'NewSecurePassword456!',
        ])->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'user.password_changed',
            'subject_id' => $user->id,
        ]);

        $details = \Illuminate\Support\Facades\DB::table('audit_logs')
            ->where('event', 'user.password_changed')
            ->first()->details;

        $this->assertStringNotContainsString(
            'NewSecurePassword456!',
            (string) $details
        );
    }
}
