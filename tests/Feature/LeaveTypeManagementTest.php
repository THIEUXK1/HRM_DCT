<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LeaveTypeManagementTest extends TestCase
{
    use RefreshDatabase;

    private function createAdminUser(): User
    {
        Role::firstOrCreate(['name' => 'admin']);

        $user = User::factory()->create([
            'email' => 'admin_' . uniqid() . '@example.com',
            'password' => Hash::make('Admin@123'),
        ]);
        $user->assignRole('admin');
        $user->forceFill(['api_token' => 'admin-token-' . uniqid()])->save();

        return $user;
    }

    private function headers(User $user, Company $company): array
    {
        return [
            'Authorization' => 'Bearer ' . $user->api_token,
            'X-Company-Id' => (string) $company->id,
        ];
    }

    public function test_admin_can_crud_leave_types(): void
    {
        $user = $this->createAdminUser();
        $company = Company::create(['name' => 'Test Co', 'code' => 'TC']);
        \App\Support\CompanyContext::set($company->id);
        $headers = $this->headers($user, $company);

        $create = $this->withHeaders($headers)->postJson('/api/v1/leave-types', [
            'code' => 'custom_leave',
            'name' => 'Nghỉ đặc biệt',
            'cell_symbol' => 'DB',
            'is_paid' => true,
            'day_count_mode' => 'workday',
            'sort_order' => 99,
        ]);

        $create->assertCreated();
        $create->assertJsonPath('data.code', 'CUSTOM_LEAVE');
        $create->assertJsonPath('data.cell_symbol', 'DB');
        $id = $create->json('data.id');

        $this->withHeaders($headers)
            ->putJson("/api/v1/leave-types/{$id}", [
                'code' => 'custom_leave',
                'name' => 'Nghỉ đặc biệt (sửa)',
                'cell_symbol' => 'D2',
                'is_paid' => false,
                'day_count_mode' => 'calendar',
                'requires_approval' => true,
                'sort_order' => 100,
            ])
            ->assertOk()
            ->assertJsonPath('data.cell_symbol', 'D2')
            ->assertJsonPath('data.is_paid', false);

        $this->withHeaders($headers)->deleteJson("/api/v1/leave-types/{$id}")->assertNoContent();
    }

    public function test_seed_standard_syncs_vn_catalog(): void
    {
        $user = $this->createAdminUser();
        $company = Company::create(['name' => 'Test Co', 'code' => 'TC2']);
        \App\Support\CompanyContext::set($company->id);

        $response = $this->withHeaders($this->headers($user, $company))
            ->postJson('/api/v1/leave-types/seed-standard');

        $response->assertOk();
        $this->assertGreaterThanOrEqual(8, count($response->json('data')));
        $this->assertNotNull(
            LeaveType::where('company_id', $company->id)->where('code', 'PHEP')->value('cell_symbol')
        );
    }

    public function test_cannot_delete_leave_type_with_requests(): void
    {
        $user = $this->createAdminUser();
        $company = Company::create(['name' => 'Test Co', 'code' => 'TC3']);
        \App\Support\CompanyContext::set($company->id);

        $employee = \App\Models\Employee::create([
            'company_id' => $company->id,
            'employee_code' => 'NV001',
            'first_name' => 'Test',
            'last_name' => 'User',
            'full_name' => 'Test User',
            'email' => 'test_' . uniqid() . '@local.test',
            'employment_status' => 'active',
            'is_active' => true,
        ]);
        $leaveType = LeaveType::create([
            'company_id' => $company->id,
            'code' => 'PHEP',
            'name' => 'Phép năm',
            'cell_symbol' => 'P',
            'is_paid' => true,
            'requires_approval' => true,
        ]);

        LeaveRequest::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'total_days' => 1,
            'status' => 'pending',
        ]);

        $this->withHeaders($this->headers($user, $company))
            ->deleteJson("/api/v1/leave-types/{$leaveType->id}")
            ->assertStatus(422);
    }
}
