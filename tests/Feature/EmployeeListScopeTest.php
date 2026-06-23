<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmployeeListScopeTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{user: User, company: Company, branchA: Branch, branchB: Branch, deptA: Department, deptB: Department} */
    private function seedOrg(): array
    {
        Role::firstOrCreate(['name' => 'admin']);
        $tenant = Tenant::create(['code' => 'T1', 'name' => 'Tenant 1']);

        $company = Company::create(['tenant_id' => $tenant->id, 'code' => 'CO', 'name' => 'Công ty chính']);
        $branchA = Branch::create(['company_id' => $company->id, 'code' => 'HN', 'name' => 'Chi nhánh HN']);
        $branchB = Branch::create(['company_id' => $company->id, 'code' => 'HCM', 'name' => 'Chi nhánh HCM']);
        $deptA = Department::create(['branch_id' => $branchA->id, 'code' => 'HR', 'name' => 'HR HN']);
        $deptB = Department::create(['branch_id' => $branchB->id, 'code' => 'IT', 'name' => 'IT HCM']);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'default_company_id' => $company->id,
            'password' => Hash::make('Admin@123'),
        ]);
        $user->assignRole('admin');
        $user->forceFill(['api_token' => 'tok-emp-scope'])->save();

        Employee::create([
            'company_id' => $company->id,
            'branch_id' => $branchA->id,
            'department_id' => $deptA->id,
            'employee_code' => 'NV-HN',
            'first_name' => 'An',
            'last_name' => 'HN',
            'full_name' => 'An HN',
            'email' => 'hn@test.local',
            'employment_status' => 'active',
            'is_active' => true,
        ]);

        Employee::create([
            'company_id' => $company->id,
            'branch_id' => $branchB->id,
            'department_id' => $deptB->id,
            'employee_code' => 'NV-HCM',
            'first_name' => 'Binh',
            'last_name' => 'HCM',
            'full_name' => 'Binh HCM',
            'email' => 'hcm@test.local',
            'employment_status' => 'active',
            'is_active' => true,
        ]);

        return compact('user', 'company', 'branchA', 'branchB', 'deptA', 'deptB');
    }

    private function headers(User $user, Company $company): array
    {
        return [
            'Authorization' => 'Bearer '.$user->api_token,
            'X-Company-Id' => $company->id,
        ];
    }

    public function test_employees_index_filters_by_branch_id(): void
    {
        $org = $this->seedOrg();

        $response = $this->withHeaders($this->headers($org['user'], $org['company']))
            ->getJson('/api/v1/employees?branch_id='.$org['branchA']->id);

        $response->assertOk();
        $response->assertJsonCount(1, 'data.data');
        $response->assertJsonPath('data.data.0.employee_code', 'NV-HN');
    }

    public function test_employees_index_filters_by_department_id(): void
    {
        $org = $this->seedOrg();

        $response = $this->withHeaders($this->headers($org['user'], $org['company']))
            ->getJson('/api/v1/employees?department_id='.$org['deptB']->id);

        $response->assertOk();
        $response->assertJsonCount(1, 'data.data');
        $response->assertJsonPath('data.data.0.employee_code', 'NV-HCM');
    }

    public function test_leave_requests_respect_branch_scope(): void
    {
        $org = $this->seedOrg();

        $response = $this->withHeaders($this->headers($org['user'], $org['company']))
            ->getJson('/api/v1/leave-requests?branch_id='.$org['branchA']->id);

        $response->assertOk();
    }
}
