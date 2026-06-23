<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\Attendance\LeaveDayCalculator;
use App\Services\Attendance\VietnamHolidayService;
use Carbon\Carbon;
use Database\Seeders\HcmPlatformSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LeavePayrollCategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_bhxh_leave_is_not_counted_as_unpaid(): void
    {
        Role::firstOrCreate(['name' => 'admin']);
        $company = Company::create(['name' => 'Co', 'code' => 'LPC']);
        \App\Support\CompanyContext::set($company->id);

        (new HcmPlatformSeeder())->syncLeaveTypesForCompany($company->id);

        $employee = Employee::create([
            'company_id' => $company->id,
            'employee_code' => 'NV-LPC',
            'first_name' => 'Test',
            'last_name' => 'BHXH',
            'full_name' => 'Test BHXH',
            'email' => 'bhxh_'.uniqid().'@test.local',
            'employment_status' => 'active',
            'is_active' => true,
        ]);

        $sickType = LeaveType::where('company_id', $company->id)->where('code', 'OM')->first();
        $this->assertSame('bhxh_benefit', $sickType->payroll_category);

        LeaveRequest::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'leave_type_id' => $sickType->id,
            'start_date' => '2026-05-12',
            'end_date' => '2026-05-12',
            'total_days' => 1,
            'status' => 'approved',
        ]);

        $start = Carbon::parse('2026-05-01');
        $end = Carbon::parse('2026-05-31');
        $holidays = VietnamHolidayService::forYear(2026);

        $stats = app(LeaveDayCalculator::class)->summarizeForEmployee($employee, $start, $end, $holidays);

        $this->assertSame(1.0, (float) $stats['bhxh_leave_days']);
        $this->assertSame(0.0, (float) $stats['unpaid_leave_days']);
        $this->assertSame(1.0, (float) $stats['total_leave_days']);
    }

    public function test_admin_can_seed_payroll_bonus_types(): void
    {
        Role::firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create(['api_token' => 'bonus-'.uniqid()]);
        $user->assignRole('admin');
        $company = Company::create(['name' => 'Co2', 'code' => 'BT']);
        \App\Support\CompanyContext::set($company->id);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$user->api_token,
            'X-Company-Id' => (string) $company->id,
        ])->postJson('/api/v1/payroll-bonus-types/seed-standard');

        $response->assertOk();
        $this->assertGreaterThanOrEqual(8, count($response->json('data')));
        $this->assertNotNull(
            \App\Models\PayrollBonusType::where('company_id', $company->id)->where('code', 'T_KPI')->value('name')
        );
    }
}
