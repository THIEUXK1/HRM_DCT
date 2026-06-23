<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private function createAdminUser(): User
    {
        Role::firstOrCreate(['name' => 'admin']);

        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('Admin@123'),
        ]);

        $user->assignRole('admin');
        $user->forceFill(['api_token' => 'admin-token'])->save();

        return $user;
    }

    public function test_audit_logs_are_listed_for_admin(): void
    {
        $user = $this->createAdminUser();

        AuditLog::create([
            'actor_id' => $user->id,
            'actor_name' => $user->name,
            'entity_type' => 'App\\Models\\Company',
            'entity_id' => 1,
            'action' => 'created',
            'field_name' => 'name',
            'old_value' => null,
            'new_value' => 'HRM Global',
            'ip_address' => '127.0.0.1',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $user->api_token,
        ])->getJson('/api/v1/audit-logs');

        $response->assertOk();
        $response->assertJsonStructure(['data' => ['data']]);
        $response->assertJsonPath('data.data.0.action', 'created');
    }

    public function test_admin_can_view_single_audit_log_entry(): void
    {
        $user = $this->createAdminUser();

        $log = AuditLog::create([
            'actor_id' => $user->id,
            'actor_name' => $user->name,
            'entity_type' => 'App\\Models\\Company',
            'entity_id' => 1,
            'action' => 'updated',
            'field_name' => 'address',
            'old_value' => 'Old Address',
            'new_value' => 'New Address',
            'ip_address' => '127.0.0.1',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $user->api_token,
        ])->getJson("/api/v1/audit-logs/{$log->id}");

        $response->assertOk();
        $response->assertJsonPath('data.action', 'updated');
        $response->assertJsonPath('data.field_name', 'address');
    }

    public function test_audit_logs_are_protected_by_api_token(): void
    {
        $response = $this->getJson('/api/v1/audit-logs');

        $response->assertUnauthorized();
    }
}
