<?php

namespace Tests\Feature;

use App\Models\AttendanceSummary;
use App\Models\Company;
use App\Models\Employee;
use App\Models\PayrollCycle;
use App\Models\PayrollResult;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AttendancePayrollLockTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Company, 2: Employee, 3: PayrollCycle} */
    private function setupCompany(string $role = 'admin'): array
    {
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'hr_manager']);

        $tenant = Tenant::create(['code' => 'T'.uniqid(), 'name' => 'Tenant 1']);
        $company = Company::create([
            'tenant_id' => $tenant->id,
            'code' => 'C'.uniqid(),
            'name' => 'Cty test',
        ]);

        $employee = Employee::create([
            'company_id' => $company->id,
            'employee_code' => 'NV001',
            'first_name' => 'A',
            'last_name' => 'B',
            'full_name' => 'B A',
            'email' => 'nv001-'.uniqid().'@test.local',
            'is_active' => true,
        ]);

        AttendanceSummary::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'period' => '2026-05',
            'work_days' => 22,
            'leave_days' => 0,
            'ot_hours' => 0,
            'late_minutes' => 0,
            'is_locked' => false,
        ]);

        $cycle = PayrollCycle::create([
            'company_id' => $company->id,
            'period' => '2026-05',
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-31',
            'status' => 'calculated',
        ]);

        PayrollResult::create([
            'payroll_cycle_id' => $cycle->id,
            'employee_id' => $employee->id,
            'gross_salary' => 10000000,
            'bhxh_employee' => 0,
            'bhxh_employer' => 0,
            'pit_amount' => 0,
            'other_deductions' => 0,
            'net_salary' => 10000000,
            'breakdown' => [],
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'password' => Hash::make('Admin@123'),
            'default_company_id' => $company->id,
        ]);
        $user->assignRole($role);
        $user->forceFill(['api_token' => 'tok-'.uniqid()])->save();

        return [$user, $company, $employee, $cycle];
    }

    private function makeUserForCompany(Company $company, string $role): User
    {
        $user = User::factory()->create([
            'tenant_id' => $company->tenant_id,
            'password' => Hash::make('Admin@123'),
            'default_company_id' => $company->id,
        ]);
        $user->assignRole($role);
        $user->forceFill(['api_token' => 'tok-'.uniqid()])->save();

        return $user;
    }

    public function test_admin_can_lock_and_unlock_attendance_period(): void
    {
        [$user, $company] = $this->setupCompany('admin');

        $lock = $this->withHeaders([
            'Authorization' => 'Bearer '.$user->api_token,
            'X-Company-Id' => $company->id,
        ])->postJson('/api/v1/attendance-summaries/lock', [
            'company_id' => $company->id,
            'period' => '2026-05',
        ]);

        $lock->assertOk();
        $this->assertDatabaseHas('attendance_period_locks', [
            'company_id' => $company->id,
            'period' => '2026-05',
        ]);
        $this->assertTrue(
            AttendanceSummary::where('company_id', $company->id)
                ->where('period', '2026-05')
                ->where('is_locked', true)
                ->exists()
        );

        $unlock = $this->withHeaders([
            'Authorization' => 'Bearer '.$user->api_token,
            'X-Company-Id' => $company->id,
        ])->postJson('/api/v1/attendance-summaries/unlock', [
            'company_id' => $company->id,
            'period' => '2026-05',
        ]);

        $unlock->assertOk();
        $this->assertDatabaseHas('attendance_period_locks', [
            'company_id' => $company->id,
            'period' => '2026-05',
        ]);
        $this->assertFalse(
            AttendanceSummary::where('company_id', $company->id)
                ->where('period', '2026-05')
                ->where('is_locked', true)
                ->exists()
        );
    }

    public function test_hr_manager_cannot_unlock_attendance_period(): void
    {
        [$admin, $company] = $this->setupCompany('admin');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$admin->api_token,
            'X-Company-Id' => $company->id,
        ])->postJson('/api/v1/attendance-summaries/lock', [
            'company_id' => $company->id,
            'period' => '2026-05',
        ])->assertOk();

        $hr = $this->makeUserForCompany($company, 'hr_manager');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$hr->api_token,
            'X-Company-Id' => $company->id,
        ])->postJson('/api/v1/attendance-summaries/unlock', [
            'company_id' => $company->id,
            'period' => '2026-05',
        ]);

        $response->assertForbidden();
    }

    public function test_admin_can_lock_and_unlock_payroll_cycle(): void
    {
        [$user, $company, , $cycle] = $this->setupCompany('admin');

        $lock = $this->withHeaders([
            'Authorization' => 'Bearer '.$user->api_token,
            'X-Company-Id' => $company->id,
        ])->postJson("/api/v1/payroll-cycles/{$cycle->id}/lock");

        $lock->assertOk();
        $cycle->refresh();
        $this->assertSame('locked', $cycle->status);
        $this->assertNotNull($cycle->locked_at);

        $unlock = $this->withHeaders([
            'Authorization' => 'Bearer '.$user->api_token,
            'X-Company-Id' => $company->id,
        ])->postJson("/api/v1/payroll-cycles/{$cycle->id}/unlock");

        $unlock->assertOk();
        $cycle->refresh();
        $this->assertSame('calculated', $cycle->status);
        $this->assertNotNull($cycle->unlocked_at);
    }

    public function test_hr_manager_cannot_unlock_payroll_cycle(): void
    {
        [$admin, $company, , $cycle] = $this->setupCompany('admin');

        $this->withHeaders([
            'Authorization' => 'Bearer '.$admin->api_token,
            'X-Company-Id' => $company->id,
        ])->postJson("/api/v1/payroll-cycles/{$cycle->id}/lock")->assertOk();

        $hr = $this->makeUserForCompany($company, 'hr_manager');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$hr->api_token,
            'X-Company-Id' => $company->id,
        ])->postJson("/api/v1/payroll-cycles/{$cycle->id}/unlock");

        $response->assertForbidden();
        $cycle->refresh();
        $this->assertSame('locked', $cycle->status);
    }
}
