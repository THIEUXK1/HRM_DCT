<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\EmploymentContract;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\InitialHrDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmploymentContractBulkTest extends TestCase
{
    use RefreshDatabase;

    private function headers(User $user, Company $company): array
    {
        return [
            'Authorization' => 'Bearer '.$user->api_token,
            'X-Company-Id' => (string) $company->id,
        ];
    }

    public function test_bulk_store_creates_one_contract_per_employee(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $this->seed(InitialHrDataSeeder::class);

        $company = Company::where('code', 'COMP-001')->firstOrFail();
        $base = Employee::where('company_id', $company->id)->firstOrFail();

        $employees = collect();
        foreach (['EMP-BULK-A', 'EMP-BULK-B'] as $code) {
            $employees->push(Employee::create([
                'company_id' => $company->id,
                'branch_id' => $base->branch_id,
                'department_id' => $base->department_id,
                'position_id' => $base->position_id,
                'employee_code' => $code,
                'first_name' => 'Test',
                'last_name' => $code,
                'full_name' => "NV {$code}",
                'email' => strtolower($code).'@test.local',
                'hire_date' => '2026-01-01',
                'employment_status' => 'active',
                'is_active' => true,
            ]));
        }
        $employeeIds = $employees->pluck('id')->all();
        $this->assertCount(2, $employeeIds);

        Role::firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create([
            'tenant_id' => $company->tenant_id,
            'password' => Hash::make('Admin@123'),
            'default_company_id' => $company->id,
        ]);
        $user->assignRole('admin');
        $user->forceFill(['api_token' => 'bulk-contract-tok'])->save();

        $payload = [
            'employee_ids' => $employeeIds,
            'contract_type' => 'definite',
            'start_date' => '2026-05-01',
            'end_date' => '2026-12-31',
            'salary_base' => 15_000_000,
            'insurance_salary' => 15_000_000,
            'status' => 'active',
            'contract_number_prefix' => 'CTR-BULK',
        ];

        $response = $this->withHeaders($this->headers($user, $company))
            ->postJson('/api/v1/employment-contracts/bulk', $payload);

        $response->assertCreated();
        $response->assertJsonPath('data.created_count', 2);

        foreach ($employees as $employee) {
            $this->assertDatabaseHas('employment_contracts', [
                'employee_id' => $employee->id,
                'contract_type' => 'definite',
            ]);
        }

        $numbers = EmploymentContract::whereIn('employee_id', $employeeIds)->pluck('contract_number');
        $this->assertCount(count($employeeIds), $numbers);
        $this->assertSame(count($employeeIds), $numbers->unique()->count());
        $this->assertStringContainsString('CTR-BULK', $numbers->first());
    }
}
