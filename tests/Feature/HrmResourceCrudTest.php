<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HrmResourceCrudTest extends TestCase
{
    use RefreshDatabase;

    private function createAdminUser(): User
    {
        Role::create(['name' => 'admin']);

        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('Admin@123'),
        ]);

        $user->assignRole('admin');
        $user->forceFill(['api_token' => 'admin-token'])->save();

        return $user;
    }

    public function test_admin_can_manage_branch_department_position_employee_and_contract_resources(): void
    {
        $user = $this->createAdminUser();
        $headers = ['Authorization' => 'Bearer ' . $user->api_token];

        $companyData = [
            'name' => 'HRM Global',
            'code' => 'COMP-001',
            'tax_code' => 'HRM2026001',
            'address' => '123 Main Street, Hanoi',
            'phone' => '+84 24 1234 5678',
            'email' => 'contact@hrmglobal.local',
            'is_active' => true,
        ];

        $companyResponse = $this->withHeaders($headers)->postJson('/api/v1/companies', $companyData);
        $companyResponse->assertCreated();
        $companyId = $companyResponse->json('data.id');

        $branchData = [
            'company_id' => $companyId,
            'name' => 'Hanoi Headquarters',
            'code' => 'BR-001',
            'address' => 'Floor 10, Tech Tower, Hanoi',
            'is_active' => true,
        ];

        $branchResponse = $this->withHeaders($headers)->postJson('/api/v1/branches', $branchData);
        $branchResponse->assertCreated();
        $branchId = $branchResponse->json('data.id');

        $branchResponse->assertJsonPath('data.name', 'Hanoi Headquarters');

        $branchUpdate = [
            ...$branchData,
            'name' => 'Hanoi HQ Updated',
            'address' => 'Floor 11, Tech Tower, Hanoi',
        ];

        $this->withHeaders($headers)
            ->putJson("/api/v1/branches/{$branchId}", $branchUpdate)
            ->assertOk()
            ->assertJsonPath('data.name', 'Hanoi HQ Updated');

        $departmentData = [
            'branch_id' => $branchId,
            'name' => 'Human Resources',
            'code' => 'DEP-HR',
            'is_active' => true,
        ];

        $departmentResponse = $this->withHeaders($headers)->postJson('/api/v1/departments', $departmentData);
        $departmentResponse->assertCreated();
        $departmentId = $departmentResponse->json('data.id');

        $departmentUpdate = [
            ...$departmentData,
            'name' => 'HR Department',
        ];

        $this->withHeaders($headers)
            ->putJson("/api/v1/departments/{$departmentId}", $departmentUpdate)
            ->assertOk()
            ->assertJsonPath('data.name', 'HR Department');

        $positionData = [
            'department_id' => $departmentId,
            'name' => 'HR Manager',
            'code' => 'POS-HR-MGR',
            'level' => 'Senior',
            'job_description' => 'Manage HR operations and recruitment.',
            'is_active' => true,
        ];

        $positionResponse = $this->withHeaders($headers)->postJson('/api/v1/positions', $positionData);
        $positionResponse->assertCreated();
        $positionId = $positionResponse->json('data.id');

        $positionUpdate = [
            ...$positionData,
            'name' => 'Senior HR Manager',
        ];

        $this->withHeaders($headers)
            ->putJson("/api/v1/positions/{$positionId}", $positionUpdate)
            ->assertOk()
            ->assertJsonPath('data.name', 'Senior HR Manager');

        $employeeData = [
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'department_id' => $departmentId,
            'position_id' => $positionId,
            'employee_code' => 'EMP-001',
            'first_name' => 'Nguyen',
            'last_name' => 'An',
            'full_name' => 'Nguyen An',
            'email' => 'nguyen.an@hrmglobal.local',
            'phone' => '+84 912 345 678',
            'gender' => 'male',
            'hire_date' => '2026-05-27',
            'employment_status' => 'active',
            'work_email' => 'nguyen.an@hrmglobal.local',
            'work_phone' => '+84 912 345 678',
            'is_active' => true,
        ];

        $employeeResponse = $this->withHeaders($headers)->postJson('/api/v1/employees', $employeeData);
        $employeeResponse->assertCreated();
        $employeeId = $employeeResponse->json('data.id');

        $employeeUpdate = [
            ...$employeeData,
            'last_name' => 'Anh',
            'full_name' => 'Nguyen Anh',
            'address' => 'No. 10 Nguyen Trai, Hanoi',
        ];

        $this->withHeaders($headers)
            ->putJson("/api/v1/employees/{$employeeId}", $employeeUpdate)
            ->assertOk()
            ->assertJsonPath('data.full_name', 'Nguyen Anh');

        $contractData = [
            'employee_id' => $employeeId,
            'contract_number' => 'CTR-001',
            'contract_type' => 'indefinite',
            'start_date' => '2026-05-27',
            'probation_months' => 2,
            'salary_base' => 15000000,
            'insurance_salary' => 15000000,
            'salary_currency' => 'VND',
            'working_hours' => 'full_time_48',
            'work_schedule' => 'Mon-Fri 8:30-17:30',
            'status' => 'active',
        ];

        $contractResponse = $this->withHeaders($headers)->postJson('/api/v1/employment-contracts', $contractData);
        $contractResponse->assertCreated();
        $contractId = $contractResponse->json('data.id');

        $this->withHeaders($headers)
            ->getJson("/api/v1/employment-contracts/{$contractId}")
            ->assertOk()
            ->assertJsonPath('data.contract_number', 'CTR-001');
    }
}
