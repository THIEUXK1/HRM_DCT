<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeTermination;
use App\Models\Position;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ResignationRequestTest extends TestCase
{
    use RefreshDatabase;

    private function setupEmployeeUser(): array
    {
        $this->seed(RolePermissionSeeder::class);

        $company = Company::create(['name' => 'Test Co', 'code' => 'TC']);
        $branch = Branch::create(['company_id' => $company->id, 'name' => 'HN', 'code' => 'HN']);
        $dept = Department::create(['branch_id' => $branch->id, 'name' => 'HR', 'code' => 'HR']);
        $pos = Position::create(['department_id' => $dept->id, 'name' => 'Staff', 'code' => 'ST']);

        $employee = Employee::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'department_id' => $dept->id,
            'position_id' => $pos->id,
            'employee_code' => 'EMP-R01',
            'first_name' => 'An',
            'last_name' => 'Test',
            'full_name' => 'Test An',
            'email' => 'an@test.local',
            'employment_status' => 'active',
            'is_active' => true,
            'hire_date' => '2026-01-01',
        ]);

        Role::firstOrCreate(['name' => 'employee']);
        $user = User::factory()->create([
            'tenant_id' => $company->tenant_id,
            'password' => Hash::make('pass'),
            'default_company_id' => $company->id,
            'employee_id' => $employee->id,
        ]);
        $user->assignRole('employee');
        $user->forceFill(['api_token' => 'emp-resign-tok'])->save();

        Role::firstOrCreate(['name' => 'admin']);
        $admin = User::factory()->create([
            'tenant_id' => $company->tenant_id,
            'password' => Hash::make('pass'),
            'default_company_id' => $company->id,
        ]);
        $admin->assignRole('admin');
        $admin->forceFill(['api_token' => 'admin-resign-tok'])->save();

        return compact('company', 'employee', 'user', 'admin');
    }

    public function test_employee_can_submit_and_hr_can_approve_resignation(): void
    {
        ['company' => $company, 'employee' => $employee, 'user' => $user, 'admin' => $admin] = $this->setupEmployeeUser();

        $headers = ['Authorization' => 'Bearer '.$user->api_token, 'X-Company-Id' => (string) $company->id];
        $adminHeaders = ['Authorization' => 'Bearer '.$admin->api_token, 'X-Company-Id' => (string) $company->id];

        $submit = $this->withHeaders($headers)->postJson('/api/v1/self-service/resignation-requests', [
            'termination_date' => now()->addDays(30)->toDateString(),
            'reason' => 'Xin nghỉ việc vì lý do cá nhân và chuyển địa phương làm việc mới.',
            'notice_period_days' => 30,
            'handover_note' => 'Sẽ bàn giao dự án ERP trước ngày nghỉ.',
        ]);

        $submit->assertCreated();
        $terminationId = $submit->json('data.id');
        $this->assertDatabaseHas('employee_terminations', [
            'id' => $terminationId,
            'employee_id' => $employee->id,
            'status' => 'pending',
            'type' => 'resignation',
        ]);

        $this->withHeaders($headers)->postJson('/api/v1/self-service/resignation-requests', [
            'termination_date' => now()->addDays(35)->toDateString(),
            'reason' => 'Đơn thứ hai không được phép gửi khi còn pending.',
        ])->assertStatus(422);

        $approve = $this->withHeaders($adminHeaders)
            ->postJson("/api/v1/employee-terminations/{$terminationId}/approve");
        $approve->assertOk();

        $employee->refresh();
        $this->assertSame('terminated', $employee->employment_status);
        $this->assertFalse($employee->is_active);
    }

    public function test_hr_can_reject_resignation_request(): void
    {
        ['company' => $company, 'employee' => $employee, 'user' => $user, 'admin' => $admin] = $this->setupEmployeeUser();

        $termination = EmployeeTermination::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'submitted_by_user_id' => $user->id,
            'requested_at' => now(),
            'decision_number' => 'XN-TEST-001',
            'termination_date' => now()->addDays(20),
            'effective_date' => now()->addDays(20),
            'reason' => 'Xin nghỉ việc do không phù hợp môi trường làm việc hiện tại.',
            'type' => 'resignation',
            'reason_type' => 'resignation',
            'status' => 'pending',
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$admin->api_token,
            'X-Company-Id' => (string) $company->id,
        ])->postJson("/api/v1/employee-terminations/{$termination->id}/reject", [
            'rejection_reason' => 'Cần hoàn thành bàn giao dự án trước.',
        ])->assertOk();

        $termination->refresh();
        $this->assertSame('rejected', $termination->status);
        $employee->refresh();
        $this->assertSame('active', $employee->employment_status);
    }
}
