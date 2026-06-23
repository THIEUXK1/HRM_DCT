<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentContract;
use App\Models\OvertimeRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Hr\HrComplianceAlertService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HrComplianceAlertTest extends TestCase
{
    use RefreshDatabase;

    private function seedCompanyWithEmployee(): array
    {
        Role::firstOrCreate(['name' => 'admin']);
        $tenant = Tenant::create(['code' => 'T1', 'name' => 'T1']);
        $company = Company::create(['tenant_id' => $tenant->id, 'code' => 'CO', 'name' => 'Cty A']);
        $branch = Branch::create(['company_id' => $company->id, 'code' => 'HQ', 'name' => 'HQ']);
        $dept = Department::create(['branch_id' => $branch->id, 'code' => 'HR', 'name' => 'HR']);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'default_company_id' => $company->id,
            'password' => Hash::make('Admin@123'),
        ]);
        $user->assignRole('admin');
        $user->forceFill(['api_token' => 'tok-alert'])->save();

        $employee = Employee::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'department_id' => $dept->id,
            'employee_code' => 'NV01',
            'first_name' => 'Test',
            'last_name' => 'User',
            'full_name' => 'Test User',
            'email' => 'test@local.test',
            'employment_status' => 'active',
            'is_active' => true,
        ]);

        return compact('user', 'company', 'employee');
    }

    public function test_api_returns_contract_missing_alert(): void
    {
        $ctx = $this->seedCompanyWithEmployee();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$ctx['user']->api_token,
            'X-Company-Id' => $ctx['company']->id,
        ])->getJson('/api/v1/hr-alerts');

        $response->assertOk();
        $response->assertJsonFragment(['category' => 'contract_missing']);
    }

    public function test_detects_expiring_contract(): void
    {
        $ctx = $this->seedCompanyWithEmployee();

        EmploymentContract::create([
            'employee_id' => $ctx['employee']->id,
            'contract_number' => 'HD-001',
            'contract_type' => 'definite',
            'start_date' => now()->subMonths(11),
            'end_date' => now()->addDays(10),
            'signed_date' => now()->subMonths(11),
            'salary_base' => 10_000_000,
            'status' => 'active',
        ]);

        $service = app(HrComplianceAlertService::class);
        $items = $service->list((int) $ctx['company']->id);

        $this->assertTrue(collect($items)->contains(fn ($a) => $a['category'] === 'contract_expiring'));
    }

    public function test_detects_ot_monthly_exceeded(): void
    {
        $ctx = $this->seedCompanyWithEmployee();
        $period = now()->format('Y-m');

        OvertimeRequest::create([
            'company_id' => $ctx['company']->id,
            'employee_id' => $ctx['employee']->id,
            'work_date' => Carbon::createFromFormat('Y-m', $period)->startOfMonth()->toDateString(),
            'hours' => 45,
            'status' => 'approved',
            'ot_type' => 'weekday',
        ]);

        $this->assertDatabaseHas('overtime_requests', [
            'employee_id' => $ctx['employee']->id,
            'status' => 'approved',
        ]);

        $service = app(HrComplianceAlertService::class);
        $items = $service->list((int) $ctx['company']->id, $period);

        $this->assertTrue(
            collect($items)->contains(fn ($a) => $a['category'] === 'ot_monthly_exceeded'),
        );
    }
}
