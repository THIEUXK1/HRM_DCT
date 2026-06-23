<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmploymentContract;
use App\Models\OvertimeRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkShift;
use App\Services\Attendance\AttendanceSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AttendanceTimesheetPhaseSplitTest extends TestCase
{
    use RefreshDatabase;

    private function seedContext(): array
    {
        Role::firstOrCreate(['name' => 'admin']);
        $user = User::factory()->create(['api_token' => 'phase-split-'.uniqid()]);
        $user->assignRole('admin');

        $tenant = Tenant::create(['code' => 'T-PS', 'name' => 'T-PS']);
        $company = Company::create(['tenant_id' => $tenant->id, 'code' => 'C-PS', 'name' => 'C-PS']);
        \App\Support\CompanyContext::set($company->id);

        WorkShift::create([
            'company_id' => $company->id,
            'code' => 'CA1',
            'name' => 'Ca 1',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'break_minutes' => 60,
            'is_active' => true,
        ]);

        $employee = Employee::create([
            'company_id' => $company->id,
            'employee_code' => 'NV-PHASE',
            'first_name' => 'Phase',
            'last_name' => 'Split',
            'full_name' => 'Phase Split',
            'email' => 'phase_'.uniqid().'@test.local',
            'hire_date' => '2026-05-01',
            'probation_end_date' => '2026-05-15',
            'official_start_date' => '2026-05-16',
            'employment_status' => 'active',
            'is_active' => true,
        ]);

        EmploymentContract::create([
            'employee_id' => $employee->id,
            'contract_number' => 'HD-PHASE-'.uniqid(),
            'contract_type' => 'fixed_term',
            'status' => 'active',
            'start_date' => '2026-05-01',
            'salary_base' => 20_000_000,
            'probation_salary' => 17_000_000,
            'probation_months' => 2,
        ]);

        foreach (['2026-05-01', '2026-05-04', '2026-05-05', '2026-05-06', '2026-05-07', '2026-05-08', '2026-05-11', '2026-05-12', '2026-05-13', '2026-05-14', '2026-05-15'] as $date) {
            AttendanceLog::create([
                'company_id' => $company->id,
                'employee_id' => $employee->id,
                'work_date' => $date,
                'check_in_at' => "{$date} 08:00:00",
                'check_out_at' => "{$date} 17:00:00",
                'source' => 'manual',
            ]);
        }

        foreach (['2026-05-19', '2026-05-20', '2026-05-21', '2026-05-22', '2026-05-26', '2026-05-27', '2026-05-28'] as $date) {
            AttendanceLog::create([
                'company_id' => $company->id,
                'employee_id' => $employee->id,
                'work_date' => $date,
                'check_in_at' => "{$date} 08:00:00",
                'check_out_at' => "{$date} 17:00:00",
                'source' => 'manual',
            ]);
        }

        OvertimeRequest::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'work_date' => '2026-05-10',
            'hours' => 2,
            'ot_type' => 'weekday',
            'status' => 'approved',
            'approved_at' => now(),
        ]);
        OvertimeRequest::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'work_date' => '2026-05-25',
            'hours' => 4,
            'ot_type' => 'weekday',
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        return compact('user', 'company', 'employee');
    }

    public function test_daily_timesheet_marks_phase_split_and_counts_tv_ct(): void
    {
        $ctx = $this->seedContext();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$ctx['user']->api_token,
            'X-Company-Id' => (string) $ctx['company']->id,
        ])->getJson('/api/v1/attendance-reports/timesheet?period=2026-05');

        $response->assertOk();
        $row = collect($response->json('data.employees'))->firstWhere('employee_id', $ctx['employee']->id);
        $this->assertNotNull($row);
        $this->assertTrue($row['has_phase_split']);
        $this->assertSame('2026-05-15', $row['probation_end_date_raw'] ?? $row['probation_end_date']);
        $this->assertSame(10, $row['totals']['probation_days']);
        $this->assertSame(7, $row['totals']['official_days']);
        $this->assertSame(17, $row['totals']['present']);
        $this->assertSame(2.0, (float) $row['totals']['probation_ot_hours']);
        $this->assertSame(4.0, (float) $row['totals']['official_ot_hours']);
        $this->assertSame(2.0, (float) $row['totals']['probation_ot_150']);
        $this->assertSame(4.0, (float) $row['totals']['official_ot_150']);
        $this->assertArrayHasKey('layout', $response->json('data'));
        $this->assertArrayHasKey('phases', $response->json('data.layout'));
    }

    public function test_phased_monthly_report_returns_two_lines(): void
    {
        $ctx = $this->seedContext();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$ctx['user']->api_token,
            'X-Company-Id' => (string) $ctx['company']->id,
        ])->getJson('/api/v1/attendance-reports/phased-monthly?period=2026-05');

        $response->assertOk();
        $lines = collect($response->json('data.rows'))->where('employee_id', $ctx['employee']->id)->values();
        $this->assertCount(2, $lines);
        $this->assertSame('probation', $lines[0]['phase']);
        $this->assertSame('official', $lines[1]['phase']);
        $this->assertSame(10, $lines[0]['work_days']);
        $this->assertSame(7, $lines[1]['work_days']);
        $this->assertSame(2.0, (float) $lines[0]['ot_150']);
        $this->assertSame(4.0, (float) $lines[1]['ot_150']);
        $this->assertSame(2.0, (float) $lines[0]['ot_hours']);
        $this->assertSame(4.0, (float) $lines[1]['ot_hours']);
        $this->assertArrayHasKey('layout', $response->json('data'));
        $this->assertArrayHasKey('metrics', $response->json('data.layout'));
        $this->assertTrue($lines[0]['has_phase_split']);
    }

    public function test_contract_sync_sets_probation_end_when_missing_on_employee(): void
    {
        $ctx = $this->seedContext();
        $employee = $ctx['employee'];
        $employee->update(['probation_end_date' => null, 'official_start_date' => null]);

        $contract = EmploymentContract::where('employee_id', $employee->id)->first();
        app(\App\Services\Hr\EmployeeProbationSyncService::class)->syncFromContract($contract);

        $employee->refresh();
        $this->assertSame('2026-06-30', $employee->probation_end_date?->format('Y-m-d'));
        $this->assertSame('2026-07-01', $employee->official_start_date?->format('Y-m-d'));
    }

    public function test_summary_build_keeps_tv_and_ct_days(): void
    {
        $ctx = $this->seedContext();
        app(AttendanceSummaryService::class)->buildForPeriod($ctx['company']->id, '2026-05');

        $summary = \App\Models\AttendanceSummary::where('employee_id', $ctx['employee']->id)
            ->where('period', '2026-05')
            ->first();

        $this->assertNotNull($summary);
        $this->assertEquals(11.0, (float) $summary->probation_work_days);
        $this->assertEquals(7.0, (float) $summary->official_work_days);
        $this->assertTrue($summary->attendance_breakdown['meta']['has_phase_split']);
        $this->assertSame(2.0, (float) $summary->attendance_breakdown['ot_by_phase']['totals']['probation_hours']);
        $this->assertSame(4.0, (float) $summary->attendance_breakdown['ot_by_phase']['totals']['official_hours']);
        $this->assertArrayHasKey('leave_by_phase', $summary->attendance_breakdown);
    }
}
