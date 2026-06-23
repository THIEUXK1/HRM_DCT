<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeePolicySetting;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\OvertimeRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Company\CompanyPolicyResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BulkEmployeeRegistrationTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Company, 2: Department, 3: list<Employee>} */
    private function seedHrContext(): array
    {
        Role::firstOrCreate(['name' => 'admin']);
        $tenant = Tenant::create(['code' => 'T1', 'name' => 'T1']);
        $company = Company::create(['tenant_id' => $tenant->id, 'code' => 'C1', 'name' => 'C1']);
        $branch = Branch::create(['company_id' => $company->id, 'code' => 'CN1', 'name' => 'CN1']);
        $this->assertNotNull($branch->id);
        $dept = Department::create([
            'branch_id' => $branch->id,
            'code' => 'IT',
            'name' => 'IT',
        ]);

        $employees = [];
        foreach (['E01', 'E02', 'E03'] as $code) {
            $employees[] = Employee::create([
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'department_id' => $dept->id,
                'employee_code' => $code,
                'first_name' => $code,
                'last_name' => 'Test',
                'full_name' => "NV {$code}",
                'email' => strtolower($code).'@test.local',
                'is_active' => true,
            ]);
        }

        LeaveType::create([
            'company_id' => $company->id,
            'code' => 'PHEP',
            'name' => 'Phép năm',
            'is_paid' => true,
            'day_count_mode' => 'workday',
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'default_company_id' => $company->id,
            'password' => Hash::make('Admin@123'),
        ]);
        $user->assignRole('admin');
        $user->forceFill(['api_token' => 'tok-'.uniqid()])->save();

        return [$user, $company, $dept, $employees];
    }

    public function test_bulk_leave_requests_by_department(): void
    {
        [$user, $company, $dept, $employees] = $this->seedHrContext();
        $leaveType = LeaveType::where('company_id', $company->id)->first();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$user->api_token,
            'X-Company-Id' => $company->id,
        ])->postJson('/api/v1/leave-requests', [
            'company_id' => $company->id,
            'department_id' => $dept->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-02',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.created_count', 3);
        $this->assertEquals(3, LeaveRequest::where('company_id', $company->id)->count());
    }

    public function test_bulk_overtime_by_employee_ids(): void
    {
        [$user, $company, , $employees] = $this->seedHrContext();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$user->api_token,
            'X-Company-Id' => $company->id,
        ])->postJson('/api/v1/overtime-requests', [
            'company_id' => $company->id,
            'employee_ids' => [$employees[0]->id, $employees[1]->id],
            'work_date' => '2026-06-10',
            'hours' => 2,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.created_count', 2);
        $this->assertEquals(2, OvertimeRequest::count());
    }

    public function test_resolver_reads_employee_policy_overlay(): void
    {
        [, $company, , $employees] = $this->seedHrContext();

        EmployeePolicySetting::create([
            'company_id' => $company->id,
            'employee_id' => $employees[0]->id,
            'domain' => 'attendance',
            'key' => 'standard_working_days',
            'value' => '20',
            'effective_from' => '2026-06-01',
        ]);

        CompanyPolicyResolver::flushCache();
        $days = CompanyPolicyResolver::for($company->id, '2026-06', $employees[0]->id)
            ->getString('standard_working_days');

        $this->assertSame('20', $days);
    }

    public function test_apply_policy_to_employees_overrides_resolver(): void
    {
        [$user, $company, , $employees] = $this->seedHrContext();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$user->api_token,
            'X-Company-Id' => $company->id,
        ])->postJson('/api/v1/company-policies/apply-to-employees', [
            'domain' => 'attendance',
            'employee_ids' => [$employees[0]->id],
            'settings' => ['standard_working_days' => '20'],
            'effective_from' => '2026-06-01',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.applied_count', 1);

        $this->assertDatabaseHas('employee_policy_settings', [
            'employee_id' => $employees[0]->id,
            'key' => 'standard_working_days',
            'value' => '20',
        ]);

        CompanyPolicyResolver::flushCache();
        $empDays = CompanyPolicyResolver::for($company->id, '2026-06', $employees[0]->id)
            ->getString('standard_working_days');
        $otherDays = CompanyPolicyResolver::for($company->id, '2026-06', $employees[1]->id)
            ->getString('standard_working_days');

        $this->assertEquals('20', $empDays);
        $this->assertNotEquals('20', $otherDays);
    }
}
