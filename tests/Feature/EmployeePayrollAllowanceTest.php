<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeePayrollAllowance;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Payroll\EmployeePayrollAllowanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmployeePayrollAllowanceTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): array
    {
        Role::firstOrCreate(['name' => 'admin']);

        $tenant = Tenant::create(['code' => 'T1', 'name' => 'T1']);
        $company = Company::create(['tenant_id' => $tenant->id, 'code' => 'BP', 'name' => 'BestPacific']);

        $employee = Employee::create([
            'company_id' => $company->id,
            'employee_code' => 'V260864',
            'first_name' => 'Ha',
            'last_name' => 'Sam',
            'full_name' => 'Sầm Văn Hà',
            'email' => 'ha@test.local',
            'is_active' => true,
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'password' => Hash::make('Admin@123'),
            'default_company_id' => $company->id,
        ]);
        $user->assignRole('admin');
        $user->forceFill(['api_token' => 'admin-tok-'.uniqid()])->save();

        return [$user, $company, $employee];
    }

    public function test_api_upsert_and_list_allowances(): void
    {
        [$user, $company, $employee] = $this->adminUser();
        $headers = [
            'Authorization' => 'Bearer '.$user->api_token,
            'X-Company-Id' => $company->id,
        ];

        $response = $this->withHeaders($headers)->postJson('/api/v1/payroll-allowances', [
            'employee_id' => $employee->id,
            'period' => '2026-05',
            'allowances' => [
                'allowance_position' => 1_000_000,
                'allowance_housing_distance' => 500_000,
                'allowance_health_check' => 150_000,
            ],
            'travel_support_amount' => 0,
            'travel_eligible' => false,
        ]);

        $response->assertCreated();

        $list = $this->withHeaders($headers)->getJson('/api/v1/payroll-allowances?period=2026-05');
        $list->assertOk();
        $rows = $list->json('data.rows');
        $row = collect($rows)->firstWhere('employee_id', $employee->id);
        $this->assertEquals(1_000_000, $row['allowances']['allowance_position']);
        $this->assertEquals(1_650_000, $row['total_allowances']);
    }

    public function test_merge_for_payroll_includes_taxable_allowances(): void
    {
        [$user, $company, $employee] = $this->adminUser();

        EmployeePayrollAllowance::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'period' => '2026-05',
            'allowances' => [
                'allowance_position' => 1_000_000,
                'allowance_probation_insurance' => 1_241_625,
            ],
            'travel_support_amount' => 0,
            'travel_eligible' => false,
        ]);

        $merge = app(EmployeePayrollAllowanceService::class)->mergeForPayroll(
            $employee->id,
            $company->id,
            '2026-05',
        );

        $this->assertEquals(1_000_000, $merge['taxable_total']);
        $this->assertEquals(1_241_625, $merge['non_taxable_total']);
        $this->assertEquals(1_000_000, $merge['fields']['allowance_position']);
        $this->assertEquals(1_241_625, $merge['fields']['allowance_probation_insurance']);
    }

    public function test_environment_allowance_zero_outside_june_to_october(): void
    {
        [$user, $company, $employee] = $this->adminUser();

        EmployeePayrollAllowance::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'period' => '2026-05',
            'allowances' => [
                'allowance_environment' => 300_000,
            ],
            'travel_support_amount' => 0,
            'travel_eligible' => false,
        ]);

        $merge = app(EmployeePayrollAllowanceService::class)->mergeForPayroll(
            $employee->id,
            $company->id,
            '2026-05',
        );

        $this->assertArrayNotHasKey('allowance_environment', $merge['fields']);
        $this->assertEquals(0, $merge['taxable_total']);
    }
}
