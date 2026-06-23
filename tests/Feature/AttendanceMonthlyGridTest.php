<?php

namespace Tests\Feature;

use App\Models\AttendanceSummary;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Attendance\AttendanceSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AttendanceMonthlyGridTest extends TestCase
{
    use RefreshDatabase;

    public function test_monthly_grid_api_returns_phased_layout(): void
    {
        Role::firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create(['api_token' => 'grid-'.uniqid()]);
        $user->assignRole('admin');

        $tenant = Tenant::create(['code' => 'T-GRID', 'name' => 'T-GRID']);
        $company = Company::create(['tenant_id' => $tenant->id, 'code' => 'C-GRID', 'name' => 'C-GRID']);
        \App\Support\CompanyContext::set($company->id);

        $employee = Employee::create([
            'company_id' => $company->id,
            'employee_code' => 'NV-GRID',
            'first_name' => 'Grid',
            'last_name' => 'Test',
            'full_name' => 'Grid Test',
            'email' => 'grid@test.local',
            'hire_date' => '2026-05-01',
            'probation_end_date' => '2026-05-15',
            'official_start_date' => '2026-05-16',
            'is_active' => true,
        ]);

        app(AttendanceSummaryService::class)->buildForPeriod($company->id, '2026-05');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$user->api_token,
            'X-Company-Id' => (string) $company->id,
        ])->getJson('/api/v1/attendance-reports/monthly-grid?period=2026-05');

        $response->assertOk();
        $response->assertJsonPath('data.title', 'BẢNG CÔNG');
        $this->assertArrayHasKey('probation', $response->json('data.layout.phases'));
        $this->assertArrayHasKey('official', $response->json('data.layout.phases'));
        $row = collect($response->json('data.rows'))->firstWhere('employee_id', $employee->id);
        $this->assertNotNull($row);
        $this->assertArrayHasKey('probation_work_days', $row);
        $this->assertArrayHasKey('official_ot_150', $row);
    }
}
